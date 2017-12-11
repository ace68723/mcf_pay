<?php
namespace App\Providers\OrderCacheService;

use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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
            'req'=>$req, //seems not used. TODO: remove this for efficiency
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
        return $order['resp']??null;
    }

    public function saved() {
    }

    public function is_defined_status($status) {
        return !empty($this->consts['ORDER_CACHE_MINS'][$status]); // treat 0 cache mins as undefined
    }

}
