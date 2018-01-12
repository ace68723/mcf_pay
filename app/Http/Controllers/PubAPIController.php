<?php

namespace App\Http\Controllers;

use Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
//use Illuminate\Support\Facades\Redis;
use App\Exceptions\RttException;


/** should be dispatch controller
 */
class PubAPIController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
        //$this->sp_generic = app()->make('generic_service');
        $this->sp_rtt = app()->make('rtt_service');

        $this->consts['IGNORED_REQ_PARAS'] = [
            'salt_str', 'account_key', 'sign', 'sign_type',
        ];
        $time_checker = function ($x) {
            if (is_int($x)) return true;
            if (is_string($x)) {
                try{
                    $dt = new \DateTime($x);
                    return true;
                }
                catch(\Exception $e){
                    return false;
                }
            }
            return false;
        };

        $this->consts['REQUEST_PARAS']['precreate_authpay'] = [
            'vendor_channel'=>[
                'checker'=>['is_string', 8],
                'required'=>true,
                'description'=> '付款渠道，目前支持wx或者ali',
            ],
            'total_fee_in_cent'=>[
                'checker'=>['is_int',[1,'inf']],
                'required'=>true,
                'description'=> '标价金额，以分为单位的整数',
            ],
            'total_fee_currency'=>[
                'checker'=>['is_string', 16],
                'required'=>true,
                'description'=> '标价金额的币种',
            ],
            'description'=>[
                'checker'=>['is_string', 32],
                'required'=>false,
                'default_value'=>" Supported by ". $this->sp_rtt->consts['OUR_NAME'],
                'description'=> '商品标题，将显示在顾客端',
            ],
        ];
        $this->consts['REQUEST_PARAS']['create_authpay'] = [
            'vendor_channel'=>[
                'checker'=>['is_string', 8],
                'required'=>true,
                'description'=> '付款渠道，目前支持wx或者ali',
            ],
            'total_fee_in_cent'=>[
                'checker'=>['is_int',[1,'inf']],
                'required'=>true,
                'description'=> '标价金额，以分为单位的整数',
            ],
            'total_fee_currency'=>[
                'checker'=>['is_string', 16],
                'required'=>true,
                'description'=> '标价金额的币种',
            ],
            'out_trade_no'=>[
                'checker'=>['is_string', 64],
                'required'=>true,
                'description'=> 'MCF开头的交易单号',
            ],
            'auth_code'=>[
                'checker'=>['is_string', 128],
                'required'=>true,
                'description'=> '顾客授权码',
            ],
        ];
        $this->consts['REQUEST_PARAS']['create_order'] = [
            'vendor_channel'=>[
                'checker'=>['is_string', 8],
                'required'=>true,
                'description'=> '付款渠道，目前支持wx或者ali',
            ],
            'total_fee_in_cent'=>[
                'checker'=>['is_int',[1,'inf']],
                'required'=>true,
                'description'=> '标价金额，以分为单位的整数',
            ],
            'total_fee_currency'=>[
                'checker'=>['is_string', 16],
                'required'=>true,
                'description'=> '标价金额的币种',
            ],
            'description'=>[
                'checker'=>['is_string', 32],
                'required'=>false,
                'default_value'=>" Supported by ". $this->sp_rtt->consts['OUR_NAME'],
                'description'=> '商品标题，将显示在顾客端',
            ],
            /*
            'timestamp'=>[
                'checker'=>'is_int',
                'required'=>false,
            ],
            'expire_time_sec'=>[
                'checker'=>'is_int',
                'required'=>false,
            ],
             */
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters
        $this->consts['REQUEST_PARAS']['check_order_status'] = [
            'vendor_channel'=>[
                'checker'=>['is_string', 8],
                'required'=>true,
                'description'=> '付款渠道，目前支持wx或者ali',
            ],
            'type'=>[
                'checker'=>['is_string', 16],
                'required'=>true,
                'description'=> 'enum("refresh","remote","pending"), refresh当缓存miss或者交易状态非成功时去支付渠道端查询，remote强制去支付渠道端查询',
            ],
            'out_trade_no'=>[
                'checker'=>['is_string', 64],
                'required'=>true,
                'description'=> 'MCF开头的交易单号',
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters
        $this->consts['REQUEST_PARAS']['check_refund_status'] = [
            'vendor_channel'=>[
                'checker'=>['is_string', 8],
                'required'=>true,
                'description'=> '付款渠道，目前支持wx或者ali',
            ],
            'type'=>[
                'checker'=>['is_string', 16],
                'required'=>true,
                'description'=> 'if type=refresh:当缓存miss或者交易状态非成功时去支付渠道端查询',
            ],
            'refund_id'=>[
                'checker'=>['is_string', 64],
                'required'=>true,
                'description'=> 'MCF开头,R1结尾的退款单号',
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['get_hot_txns'] = [
            'page_num'=>[
                'checker'=>['is_int', [1,'inf']],
                'required'=>false,
                'default_value'=>1,
                'description'=> 'starts from 1',
            ],
            'page_size'=>[
                'checker'=>['is_int', [1,50]],
                'required'=>false,
                'default_value'=>$this->sp_rtt->consts['DEFAULT_PAGESIZE'],
                'description'=> 'page size',
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['get_txn_by_id'] = [
            'ref_id'=>[
                'checker'=>['is_string', 64],
                'required'=>true,
                'description'=> 'out_trade_no or refund_id',
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['get_settlements'] = [
            'page_num'=>[
                'checker'=>['is_int', [1,'inf']],
                'required'=>false,
                'default_value'=>1,
                'description'=> 'starts from 1',
            ],
            'page_size'=>[
                'checker'=>['is_int', [1,50]],
                'required'=>false,
                'default_value'=>$this->sp_rtt->consts['DEFAULT_PAGESIZE'],
                'description'=> 'page size',
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['query_txns_by_time'] = [
            'start_time'=>[
                'checker'=>[$time_checker],
                'required'=>true,
                'description'=> '开始时间的unix timestamp or datetime string, inclusive',
            ],
            'end_time'=>[
                'checker'=>[$time_checker],
                'required'=>true,
                'description'=> '结束时间的unix timestamp or datetime string, exclusive',
            ],
            'timezone'=>[
                'checker'=>['is_string'],
                'required'=>false,
            ],
            'page_num'=>[
                'checker'=>['is_int', [1,'inf']],
                'required'=>false,
                'default_value'=>1,
                'description'=> 'starts from 1',
            ],
            'page_size'=>[
                'checker'=>['is_int', [1,50]],
                'required'=>false,
                'default_value'=>$this->sp_rtt->consts['DEFAULT_PAGESIZE'],
                'description'=> 'page size',
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['create_refund'] = [
            'vendor_channel'=>[
                'checker'=>['is_string', 8],
                'required'=>true,
                'description'=> '付款渠道，目前支持wx或者ali',
            ],
            'refund_no'=>[
                'checker'=>['is_int', [1,1]],
                'required'=>true,
                'description'=> '第几笔退款，目前仅支持1笔退款',
            ],
            'refund_fee_in_cent'=>[
                'checker'=>['is_int',[1,'inf']],
                'required'=>true,
                'description'=> '退款金额，以分为单位的整数',
            ],
            'refund_fee_currency'=>[
                'checker'=>['is_string', 16],
                'required'=>true,
                'description'=> '退款币种，必须与标价金额的币种一致',
            ],
            'total_fee_in_cent'=>[
                'checker'=>['is_int',[1,'inf']],
                'required'=>true,
                'description'=> '标价金额，以分为单位的整数',
            ],
            'total_fee_currency'=>[
                'checker'=>['is_string', 16],
                'required'=>true,
                'description'=> '标价金额的币种',
            ],
            'out_trade_no'=>[
                'checker'=>['is_string', 64],
                'required'=>true,
                'description'=> 'MCF开头的交易单号',
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['get_exchange_rate'] = [
            'vendor_channel'=>[
                'checker'=>['is_string', 8],
                'required'=>true,
                'description'=> '付款渠道，目前支持wx或者ali',
            ],
            'currency_type'=>[
                'checker'=>['is_string', 16],
                'required'=>true,
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['get_company_info'] = [
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        if (!$this->check_api_def())
            throw new RttException('SYSTEM_ERROR', "ERROR SETTING IN API SCHEMA");
    }

    public function precreate_authpay(Request $request){
        $userObj = $request->user('custom_api');
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__, $userObj);
        $la_paras['device_id'] = 'FROM_WEB';
        $la_paras['scenario'] = 'AUTHPAY';
        $la_paras['_uid'] = null;
        $la_paras['_username'] = null;
        $ret = $this->sp_rtt->precreate_authpay($la_paras, $account_id);
        return $this->format_success_ret($ret);
    }

    public function create_authpay(Request $request){
        $userObj = $request->user('custom_api');
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__, $userObj);
        $la_paras['device_id'] = 'FROM_WEB';
        $la_paras['scenario'] = 'AUTHPAY';
        $la_paras['_uid'] = null;
        $la_paras['_username'] = null;
        $ret = $this->sp_rtt->create_authpay($la_paras, $account_id);
        $ret['total_fee_in_cent'] = $la_paras['total_fee_in_cent'];
        $ret['total_fee_currency'] = $la_paras['total_fee_currency'];
        return $this->format_success_ret($ret);
    }

    public function create_order(Request $request)
    {
        $userObj = $request->user('custom_api');
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__, $userObj);
        $la_paras['device_id'] = 'FROM_WEB';
        $la_paras['scenario'] = 'NATIVE';
        $la_paras['_uid'] = null;
        $la_paras['_username'] = null;
        $ret = $this->sp_rtt->create_order($la_paras, $account_id);
        $ret['total_fee_in_cent'] = $la_paras['total_fee_in_cent'];
        $ret['total_fee_currency'] = $la_paras['total_fee_currency'];
        return $this->format_success_ret($ret);
    }

    public function create_refund(Request $request)
    {
        $userObj = $request->user('custom_api');
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__, $userObj);
        $la_paras['device_id'] = 'FROM_WEB';
        $la_paras['_uid'] = null;
        $la_paras['_username'] = null;
        $ret = $this->sp_rtt->create_refund($la_paras, $account_id);
        $this->sp_rtt->txn_to_export($ret);
        return $this->format_success_ret($ret);
    }

    public function check_order_status(Request $request)
    {
        $userObj = $request->user('custom_api');
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__, $userObj);
        $status = $this->sp_rtt->check_order_status($la_paras, $account_id);
        return $this->format_success_ret($status);
    }

    public function check_refund_status(Request $request)
    {
        $userObj = $request->user('custom_api');
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__, $userObj);
        $status = $this->sp_rtt->check_refund_status($la_paras, $account_id);
        return $this->format_success_ret($status);
    }

    public function get_hot_txns(Request $request){
        $userObj = $request->user('custom_api');
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__, $userObj);
        $ret = $this->sp_rtt->get_hot_txns($la_paras, $account_id);
        array_walk($ret['recs'], [$this->sp_rtt, 'txn_to_export']);
        return $this->format_success_ret($ret);
    }
    public function get_txn_by_id(Request $request){
        $userObj = $request->user('custom_api');
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__, $userObj);
        $ret = $this->sp_rtt->get_txn_by_id($la_paras, $account_id);
        $this->sp_rtt->txn_to_export($ret);
        return $this->format_success_ret($ret);
    }

    private function convert_time($type, $timestr, $tz) {
        if (is_int($timestr)) return $timestr;
        try {
            if ($type == 'end_time') {
                try {
                    $dt = new \DateTime($timestr." 23:59:59", new \DateTimeZone($tz));
                }
                catch(\Exception $e){
                }
            }
            if (empty($dt)) $dt = new \DateTime($timestr, new \DateTimeZone($tz));
            return $dt->getTimestamp();
        }
        catch(\Exception $e) {
            throw new RttException('INVALID_PARAMETER', $timestr."T".$tz);
        }
    }
    public function query_txns_by_time(Request $request){
        $userObj = $request->user('custom_api');
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__, $userObj);
        $mch_info = $this->sp_rtt->get_merchant_info_by_id($account_id);
        $la_paras['start_time'] = $this->convert_time('start_time', $la_paras['start_time'],
            $la_paras['timezone']??$mch_info['timezone']);
        $la_paras['end_time'] = $this->convert_time('end_time', $la_paras['end_time'],
            $la_paras['timezone']??$mch_info['timezone']);
        Log::DEBUG('start time:'.$la_paras['start_time'].'; end_time:'.$la_paras['end_time']);
        $ret = $this->sp_rtt->query_txns_by_time($la_paras, $account_id);
        array_walk($ret['recs'], [$this->sp_rtt, 'txn_to_export']);
        return $this->format_success_ret($ret);
    }

    public function get_settlements(Request $request){
        $userObj = $request->user('custom_api');
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__, $userObj);
        $ret = app()->make('settle_service')->get_settlements($la_paras, $account_id);
        return $this->format_success_ret($ret);
    }

    public function get_company_info(Request $request){
        $userObj = $request->user('custom_api');
        $account_id = $userObj->account_id;
        $ret = $this->sp_rtt->get_company_info($account_id);
        return $this->format_success_ret([
            'cell'=>$ret->cell,
            'address'=>$ret->address,
            'display_name'=>$ret->display_name,
            'timezone'=>$ret->timezone,
            'tip_mode'=>$ret->tip_mode ?? null,
        ]);
    }

    public function get_exchange_rate(Request $request)
    {
        $userObj = $request->user('custom_api');
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__, $userObj);
        $sp = $this->sp_rtt->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
        $ret = $sp->get_exchange_rate($account_id,$la_paras['currency_type']);
        return $this->format_success_ret($ret);
    }

    public function handle_notify_wx(Request $request)
    {
        $sp = app()->make('wx_vendor_service');
        if (empty($sp)) return ;
        $sp->handle_notify($request, false);
    }
    public function handle_notify_ali(Request $request)
    {
        $sp = app()->make('ali_vendor_service');
        if (empty($sp)) return ;
        return $sp->handle_notify($request);
    }

    public function test(Request $request)
    {
        return "OK";
    }
}
