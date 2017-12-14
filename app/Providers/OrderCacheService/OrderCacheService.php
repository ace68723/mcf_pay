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
        $old = $this->query_order_cache($order_id);
        if (!empty($old)) {
            Log::DEBUG('cache new order fail because of duplicate order_id '. $order_id . ', this may happen for refund');
            return $old;
        }
        $status = 'INIT';
        $item = [
            'account_id'=>$account_id,
            'channel_name'=>$channel_name,
            'input'=>$input,
            //'req'=>$req, //seems not used. remove this for efficiency
            'resp'=>$resp,
            'status'=>$status,
        ];
        Redis::setex("order:".$order_id, $this->consts['ORDER_CACHE_MINS'][$status]*60, serialize($item));
        return $item;
    }

    public function cb_order_update($order_id, $status, $newResp=null, $old=null) {
        if (!$this->is_defined_status($status)) {
            Log::info(__FUNCTION__.': undefined status:'.$status);
            return ;
        }
        $key = "order:".$order_id;
        if (empty($old)) {
            $old = Redis::get($key);
            if (empty($old)) {
                Log::info(__FUNCTION__.': updating non-exist order:'.$order_id);
                return;
            }
            $old = unserialize($old);
        }
        if ($old['status'] != $status) {
            $old['status'] = $status;
            if (!empty($newResp)) {
                $old['resp'] = $newResp;
            }
            if ($status == 'SUCCESS') {
                $txn = $this->cached_order_to_rtt_txn($old);
                if (!empty($txn)){
                    $account_id = $txn['account_id'] ?? null;
                    if (!empty($account_id)) {
                        $idx_id = "index:".$account_id;
                        if ($txn['vendor_txn_time'] < 0)
                            throw new RttException('SYSTEM_ERROR', 'vendor_txn_time < 0 for '.$key);
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
            Redis::setex($key, $this->consts['ORDER_CACHE_MINS'][$status]*60, serialize($old));
        }
    }

    public function query_order_cache($order_id) {
        $ret = Redis::get("order:".$order_id);
        return (empty($ret))? null:unserialize($ret);
    }

    protected function cached_order_to_rtt_txn($order) {
        if ($order['status'] != 'SUCCESS') {
            return null;
        }
        $txn = $order['resp']??null;
        if (!empty($txn)) {
            $txn['username'] = $order['input']['_username'] ?? "unknown";
        }
        return $txn;
    }

    public function get_hot_txns($account_id, $offset, $limit) {
        $idx_id = "index:".$account_id;
        if (!Redis::EXISTS($idx_id))
            return ['total_count'=>0, 'txns'=>[]];
        $txns = Redis::ZREVRANGE($idx_id, $offset, $offset+$limit-1);
        array_walk($txns, function(&$x) {$x = unserialize($x);});
        $count = Redis::ZCARD($idx_id);
        return ['total_count'=>$count, 'txns'=>$txns];
    }
    public function query_txns_by_time($account_id, $start_time, $end_time, $offset, $limit) {
        $idx_id = "index:".$account_id;
        if (!Redis::EXSITS($idx_id))
            return ['total_count'=>0, 'txns'=>[]];
        $end_time = '('.$end_time;
        $txns = Redis::ZREVRANGEBYSCORE($idx_id, $end_time, $start_time, 'LIMIT '.$offset . ' '. $limit);
        array_walk($txns, function(&$x) {$x = unserialize($x);});
        $count = Redis::ZREVRANGEBYSCORE($idx_id, $start_time, $end_time);
        return ['total_count'=>$count, 'txns'=>$txns];
    }

    public function is_defined_status($status) {
        return !empty($this->consts['ORDER_CACHE_MINS'][$status]); // treat 0 cache mins as undefined
    }

}

/*
trait ByCacheFacade{ //this is an incomplete implementation
    public function cb_new_order($order_id, $account_id, $channel_name, $input, $req=null, $resp=null) {
        $old = $this->query_order_cache($order_id);
        if (!empty($old)) {
            Log::DEBUG('cache new order fail because of duplicate order_id '. $order_id . ', this may happen for refund');
            return $old;
        }
        $status = 'INIT';
        $item = [
            'account_id'=>$account_id,
            'channel_name'=>$channel_name,
            'input'=>$input,
            //'req'=>$req, //seems not used. remove this for efficiency
            'resp'=>$resp,
            'status'=>$status,
        ];
        Cache::put("order:".$order_id, $item, $this->consts['ORDER_CACHE_MINS'][$status]);
        return $item;
    }

    public function cb_order_update($order_id, $status, $newResp=null, $old=null) {
        if (!$this->is_defined_status($status)) {
            Log::info(__FUNCTION__.': undefined status:'.$status);
            return ;
        }
        if (empty($old)) {
            $old = Cache::get("order:".$order_id, null);
            if (empty($old)) {
                Log::info(__FUNCTION__.': updating non-exist order:'.$order_id);
                return;
            }
        }
        if ($old['status'] != $status) {
            $old['status'] = $status;
            if (!empty($newResp)) {
                $old['resp'] = $newResp;
            }
            if ($status == 'SUCCESS') {
                $txn = $this->cached_order_to_rtt_txn($old);
                if (!empty($txn)) DB::table('txn_base')->updateOrInsert( ['ref_id'=>$txn['ref_id']], $txn);
            }
            Cache::forget("order:".$order_id); 
            //$tags = [$account_id, $status];
            //Cache::tags($tags)->put("order:".$order_id, $old, $this->consts['ORDER_CACHE_MINS'][$status]); 
            Cache::put("order:".$order_id, $old, $this->consts['ORDER_CACHE_MINS'][$status]); 
        }
    }

    public function query_order_cache($order_id) {
        return Cache::get("order:".$order_id, null);
    }

    protected function cached_order_to_rtt_txn($order) {
        if ($order['status'] != 'SUCCESS') {
            return null;
        }
        $txn = $order['resp']??null;
        if (!empty($txn)) {
            $txn['username'] = $order['input']['_username'];
        }
        return $txn;
    }

    public function is_defined_status($status) {
        return !empty($this->consts['ORDER_CACHE_MINS'][$status]); // treat 0 cache mins as undefined
    }

}
 */
