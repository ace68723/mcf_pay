<?php
namespace App\Providers\OrderCacheService;

use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;

class OrderCacheService{

    public $consts;
    public function __construct() {
        $this->consts = array();
        $this->consts['ORDER_CACHE_MINS'] = [
            'INIT'=>24*60,
            'WAIT'=>24*60,
            'SUCCESS'=>7*24*60,
            'FAIL'=>24*60,
        ];
    }
    use ByRedisFacade;
}

trait ByRedisFacade{
    public function cb_new_order($order_id, $account_id, $channel_name, $input, $req=null, $resp=null) {
        $key = "order:".$order_id;
        if (Redis::EXISTS($key)) {
            Log::DEBUG('cache new order failed because of duplicate order_id '.
                $order_id . ', this may happen for refund/authpay');
            return false;
        }
        $status = 'INIT';
        $item = [
            'account_id'=>$account_id,
            //'channel_name'=>$channel_name,
            'input'=>$input,
            //'req'=>$req, //seems not used. remove this for efficiency
            'resp'=>$resp,
            'status'=>$status,
        ];
        //Redis::setex($key, $this->consts['ORDER_CACHE_MINS'][$status]*60, serialize($item));
        Redis::HMSET($key, 'account_id', serialize($account_id), 'input', serialize($item['input']), 'resp', serialize($item['resp']), 'status', $status);
        Redis::EXPIRE($key, $this->consts['ORDER_CACHE_MINS'][$status]*60); //no need to use transactions here
        return true;
    }

    public function cb_order_update($order_id, $status, $newResp=null) {
        if (!$this->is_defined_status($status)) {
            Log::info(__FUNCTION__.': undefined status:'.$status);
            return ;
        }
        $key = "order:".$order_id;
        $old_status = $this->query_order_cache_field($order_id, 'status'); 
        if (empty($old_status)) {
            Log::info(__FUNCTION__.': updating non-exist order:'.$order_id);
            return;
        }
        if ($old_status != $status) {
            if (!empty($newResp)) {
                Redis::HSET($key, 'resp', serialize($newResp));
            }
            if ($status == 'SUCCESS') {
                if (empty($newResp)) {
                    $newResp = $this->query_order_cache_field($order_id, 'resp');
                }
                if (!empty($newResp)){
                    $txn = $newResp;
                    $input = $this->query_order_cache_field($order_id, 'input');
                    $txn['username'] = $input['_username'] ?? "unknown";
                    $account_id = $txn['account_id'] ?? null;
                    if (!empty($account_id)) {
                        $idx_id = "index:".$account_id;
                        Redis::zadd($idx_id, $txn['vendor_txn_time'], serialize($txn));
                        $now = time();
                        Redis::zremrangebyscore($idx_id, '-inf', $now-(60*$this->consts['ORDER_CACHE_MINS'][$status]));
                    }
                    else {
                        Log::INFO(__FUNCTION__.": cannot get the account_id of txn ".$key);
                    }
                    unset($txn['username']);
                    DB::table('txn_base')->updateOrInsert( ['ref_id'=>$txn['ref_id']], $txn);
                }
            }
            //Redis::multi();
            //Redis::exec();
            Redis::HSET($key, 'status', $status);
            Redis::EXPIRE($key, $this->consts['ORDER_CACHE_MINS'][$status]*60); //no need to use transactions here
        }
    }

    public function query_order_cache_field($order_id, $field) {
        $ret = Redis::HGET("order:".$order_id, $field);
        if (empty($ret))
            return null;
        return ($field == 'status')? $ret : unserialize($ret);
    }

    public function get_hot_txns($account_id, $page_num, $page_size) {
        $offset = ($page_num-1)*$page_size;
        $idx_id = "index:".$account_id;
        if (!Redis::EXISTS($idx_id))
            return ['total_page'=>0,
            'total_count'=>0,
            'page_num'=>1,
            'page_size'=>$page_size,
            'recs'=>[]];
        $txns = Redis::ZREVRANGE($idx_id, $offset, $offset+$page_size-1);
        array_walk($txns, function(&$x) {$x = unserialize($x);});
        $count = Redis::ZCARD($idx_id);
        return ['total_page'=>ceil($count/$page_size),
            'total_count'=>$count,
            'page_num'=>$page_num,
            'page_size'=>$page_size,
            'recs'=>$txns];
    }
    public function query_txns_by_time($account_id, $start_time, $end_time, $page_num, $page_size) {
        $offset = ($page_num-1)*$page_size;
        $idx_id = "index:".$account_id;
        if (!Redis::EXSITS($idx_id))
            return ['total_page'=>0,
            'total_count'=>0,
            'page_num'=>1,
            'page_size'=>$page_size,
            'recs'=>[]];
        $end_time = '('.$end_time;
        $txns = Redis::ZREVRANGEBYSCORE($idx_id, $end_time, $start_time, 'LIMIT '.$offset . ' '. $page_size);
        array_walk($txns, function(&$x) {$x = unserialize($x);});
        $count = Redis::ZREVRANGEBYSCORE($idx_id, $start_time, $end_time);
        return ['total_page'=>ceil($count/$page_size),
            'total_count'=>$count,
            'page_num'=>$page_num,
            'page_size'=>$page_size,
            'recs'=>$txns];
    }

    public function is_defined_status($status) {
        return !empty($this->consts['ORDER_CACHE_MINS'][$status]); // treat 0 cache mins as undefined
    }

}

