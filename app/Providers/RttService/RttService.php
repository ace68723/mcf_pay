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
        $this->consts['CHANNELS'] = ['WX'=>0x1, 'ALI'=>0x2,];
        $this->consts['CHANNELS_REV'] = [];
        foreach($this->consts['CHANNELS'] as $key=>$value) {
            $this->consts['CHANNELS_REV'][$value] = $key;
        }
        $this->consts['DEFAULT_PAGESIZE'] = 20;
        $this->sp_oc = app()->make('order_cache_service');
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

    public function precreate_authpay($la_paras, $account_id) {
        $infoObj = $this->get_account_info($account_id);
        if (!empty($infoObj->currency_type) && $infoObj->currency_type != $la_paras['total_fee_currency'])
            throw new RttException("INVALID_PARAMETER", "currency_type");
        $la_paras['_out_trade_no'] = $this->generate_txn_ref_id($la_paras, $infoObj->ref_id, 'ORDER');
        $cachedItem = $this->sp_oc->cb_new_order($la_paras['_out_trade_no'], $account_id, 
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
            (function(...$args) {}), [$this->sp_oc,'cb_order_update']); //null cb_new_order do not memorize auth_code
        return $ret;
    }

    public function create_order($la_paras, $account_id){
        $infoObj = $this->get_account_info($account_id);
        if (!empty($infoObj->currency_type) && $infoObj->currency_type != $la_paras['total_fee_currency'])
            throw new RttException("INVALID_PARAMETER", "currency_type");
        $la_paras['_out_trade_no'] = $this->generate_txn_ref_id($la_paras, $infoObj->ref_id, 'ORDER');
        $sp = $this->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
        $ret = $sp->create_order($la_paras, $account_id,
            [$this->sp_oc,'cb_new_order'], [$this->sp_oc,'cb_order_update']);
        return $ret;
    }

    public function create_refund($la_paras, $account_id){
        $la_paras['_refund_id'] = $this->generate_txn_ref_id($la_paras, null, 'REFUND');
        $sp = $this->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
        $ret = $sp->create_refund($la_paras, $account_id,
            [$this->sp_oc,'cb_new_order'], [$this->sp_oc,'cb_order_update']);
        return $ret;
    }

    public function check_order_status($la_paras, $account_id){
        $cached_order = $this->query_order_cache($la_paras['out_trade_no']);
        if (empty($cached_order))
            throw new RttException('NOT_FOUND', ["ORDER",$la_paras['out_trade_no']]);
        $status = $cached_order['status'];
        if ($la_paras['type'] == 'refresh' && $status != 'SUCCESS' ||
            $la_paras['type'] == 'force_remote') {
            $sp = $this->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
            $vendor_txn = $sp->query_charge_single($la_paras, $account_id);
            //only success query gets here
            $txn = $sp->vendor_txn_to_rtt_txn($vendor_txn, $account_id, 'DEFAULT', ($cached_order['input']??null));
            $status = $txn['status'];//TODO ensure the state map in wx/ali service consists with rtt config
            if (!$this->is_defined_status($status)) {
                Log::INFO('regard undefined status '. $status . ' as FAIL');
                $status = 'FAIL';
            }
            $this->sp_oc->cb_order_update($la_paras['out_trade_no'], $status, $txn, $cached_order);
        }
        return $status;
    }

    public function get_hot_txns($la_paras, $account_id) {
        $page_num = $la_paras['page_num'] ?? 0;
        $limit = $la_paras['page_size'];
        $offset = ($page_num-1)*$page_size;
        return $this->sp_oc->get_hot_txns($account_id, $offset, $limit);
    }
    public function query_txns_by_time($la_paras, $account_id){
        $where_cond = [
            ['account_id', '=', $account_id],
        ];
        if ($la_paras['start_time'] >= 0)
            $where_cond[] = ['vendor_txn_time', '>=', $la_paras['start_time']];
        if ($la_paras['end_time'] >= 0)
            $where_cond[] = ['vendor_txn_time', '<', $la_paras['end_time']];
        $count = DB::table('txn_base')->where($where_cond)->count();
        $page_size =$la_paras['page_size']??$this->consts['DEFAULT_PAGESIZE']; 
        $offset = ($la_paras['page_num']-1)*$page_size;
        $result = DB::table('txn_base')
            ->leftJoin('mcf_user_base', 'txn_base.user_id','=','mcf_user_base.uid')
            ->where($where_cond)
            ->orderBy('vendor_txn_time','DESC')
            ->offset($offset)
            ->limit($page_size)
            ->get();
        return ['total_count'=>$count, 'txns'=>$result];
    }

    public function txn_to_export(&$txn) {
        $new_txn = [
            'time'=>$txn->vendor_txn_time,
            'is_refund'=>$txn->is_refund,
            'amount_in_cent'=>$txn->total_fee_in_cent,
            'amount_currency'=>$txn->total_fee_currency,
            'vendor_channel'=>$this->consts['CHANNELS_REV'][$txn->vendor_channel],
            'username'=>$txn->username,
        ];
        $txn = $new_txn;
    }

    public function get_company_info($account_id) {
        $ret = DB::table('company_info')->where('account_id','=', $account_id)->first();
        if (empty($ret))
            throw new RttException('SYSTEM_ERROR', 'company_info not found');
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

    public function is_defined_status($status) {
        return $this->sp_oc->is_defined_status($status);
    }

    public function download_bills($start_date, $end_date) {
        Log::DEBUG("in ".__FUNCTION__);
    }

}
