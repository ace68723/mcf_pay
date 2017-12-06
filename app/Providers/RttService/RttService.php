<?php
namespace App\Providers\RttService;

use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Exceptions\RttException;

class RttService{

    public $consts;
    public function __construct()
    {
        $this->consts = array();
        $this->consts['OUR_NAME'] = "MCF";
        $this->consts['CHANNELS'] = array('WX'=>0x1, 'ALI'=>0x2,);
        $this->consts['ORDER_CACHE_MINS'] = [
            'INIT'=>24*60,
            'WAIT'=>24*60,
            'SUCCESS'=>7*24*60,
            'FAIL'=>24*60,
        ];
    }

    public function get_vendor_channel_info($account_id, $b_readable=false) {
        if (empty($account_id))
            throw new RttException('SYSTEM_ERROR', "Invalid Account ID");
        $res = DB::table('account_vendor')->where('account_id','=',$account_id)->first();
        if ($b_readable) {
            $channels = array();
            $bitInd = $res->vendor_channel ?? 0;
            foreach($this->consts['CHANNELS'] as $key=>$value) {
                if ($value & $bitInd)
                    $channels[] = $key;
            }
            return $channels;
        }
        return $res;
    }

    public function resolve_channel_sp($account_id, $channel) {
        $res = $this->get_vendor_channel_info($account_id);
        if (!(($res->vendor_channel ?? 0) & ($this->consts["CHANNELS"][strtoupper($channel)] ?? 0)))
            throw new RttException('CHANNEL_NOT_ACTIVATED', ['account_id'=>$account_id, 'channel'=>$channel]);
        /*
        if (!$b_only_channel_name)
            return strtolower($channel);
         */
        $sp_name = strtolower($channel) ."_service";
        if (!app()->bound($sp_name))
            throw new RttException('CHANNEL_NOT_SUPPORTED', ['channel'=>$channel]);
        $sp = app()->make($sp_name);
        if (empty($sp))
            throw new RttException('CHANNEL_NOT_SUPPORTED', ['channel'=>$channel]);
        return $sp;
    }

    public function get_account_info($account_id) {
        return empty($account_id) ? null: 
            DB::table('account_base')->where('account_id','=',$account_id)->first();
    }

    public function generate_txn_ref_id($la_paras, $account_ref_id, $type, $max_length=32) {
        // default max_length 32 is because wxpay's out trade no is of string(32)
        if ($type == 'ORDER') {
            $vendor_channel = $la_paras['vendor_channel'];
            if (empty($vendor_channel))
                throw new RttException('SYSTEM_ERROR', __FUNCTION__.":Vendor_channel missing");
            $vendor_channel = strtoupper(substr($vendor_channel, 0, 2));
            if (empty($account_ref_id))
                throw new RttException('SYSTEM_ERROR', __FUNCTION__.": account_ref_id missing");
            $account_ref_id = substr($account_ref_id, 0, 6);
            $ref_id = $this->consts['OUR_NAME'].$vendor_channel.
                $account_ref_id.date("YmdHis").bin2hex(random_bytes(2));
            if (strlen($ref_id) > $max_length)
                throw new RttException('SYSTEM_ERROR', __FUNCTION__.": exceeds max_length");
            return substr($ref_id, 0, $max_length);
        } elseif ($type == 'REFUND') {
            $ref_id = $la_paras['out_trade_no']."R".$la_paras['refund_no'];
            if (strlen($ref_id) > $max_length)
                throw new RttException('SYSTEM_ERROR', __FUNCTION__.": exceeds max_length");
            return substr($ref_id, 0, $max_length);
        }
        throw new RttException('SYSTEM_ERROR', __FUNCTION__.":Unknown type:".$type);
    }

    public function txn_to_front_end($rtt_txn) {
        $channel = $rtt_txn['vendor_channel']; 
        unset($rtt_txn['account_id']);
        $rtt_txn['vendor_channel'] = 'unknown';
        foreach ($this->consts['CHANNELS'] as $key=>$value) {
            if ($channel == $value) {
                $rtt_txn['vendor_channel'] = $key;
                break;
            }
        }
        return $rtt_txn;
    }

    public function precreate_authpay($la_paras, $account_id) {
        $cachedItem = $this->cb_new_order($la_paras['_out_trade_no'], $account_id, 
            $la_paras['vendor_channel'], $la_paras);
        return ['out_trade_no'=>$la_paras['_out_trade_no'],];
    }
    public function create_authpay($new_la_paras, $account_id) {
        $cachedItem = $this->query_order_cache($new_la_paras['out_trade_no']);
        if (empty($cachedItem['input']) || ($cachedItem['status']??null) != 'INIT')
            throw new RttException("INVALID_PARAMETER", "out_trade_no");
        //TODO remember to unset APP_DEBUG in production env, which will prevent outputing exception context message
        //to frontend, since the above message may be used to verify if an order exists.
        //Although timing attack may still work, it shouldn't be a problem,
        //considering its hardness and the limited order expire time...
        $la_paras = $cachedItem['input'];
        if (($la_paras['_out_trade_no']??null) != $new_la_paras['out_trade_no'])
            throw new RttException("INVALID_PARAMETER", "out_trade_no"); //note that $la_paras['_out_trade_no'] is the real effecting parameter
        foreach (['scenario', 'total_fee_in_cent', 'total_fee_currency', 'vendor_channel'] as $pa_name) {
            if (($la_paras[$pa_name]??null) != $new_la_paras[$pa_name])
                throw new RttException("INVALID_PARAMETER", $pa_name);
        }
        $la_paras['auth_code'] = $new_la_paras['auth_code'];
        $sp = $this->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
        $ret = $sp->create_authpay($la_paras, $account_id,
            (function(...$args) {}), [$this,'cb_order_update']); //null cb_new_order do not memorize auth_code
        return $ret;
    }

    /*
    public function cache_txn($txn){
        //DB::table('txn_base')->updateOrCreate(['ref_id'=>$txn['ref_id']],$txn); //TODO:use Eloquent
        $old = DB::table('txn_base')->where('ref_id','=',$txn['ref_id'])->first();
        if (empty($old)) {
            DB::table('txn_base')->insert($txn);
        }
        else {
            DB::table('txn_base')->where('ref_id','=',$txn['ref_id'])->update($txn);
        }
    }
     */

    public function cb_new_order($order_id, $account_id, $channel_name, $input, $req=null, $resp=null) {
        $status = 'INIT';
        $item = [
            'account_id'=>$account_id,
            'channel_sp_name'=>$channel_name,
            'input'=>$input,
            'req'=>$req,
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
            Cache::forget("order:".$order_id); 
            Cache::put("order:".$order_id, $old, $this->consts['ORDER_CACHE_MINS'][$status]); 
        }
    }

    public function query_order_cache($order_id) {
        return Cache::get("order:".$order_id, null);
    }

    protected function cached_order_to_txn($order) {
    }

    public function is_defined_status($status) {
        return !empty($this->consts['ORDER_CACHE_MINS'][$status]); // treat 0 cache mins as undefined
    }

    public function download_bills($start_date, $end_date) {
        Log::DEBUG("in ".__FUNCTION__);
    }

}
