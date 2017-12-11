<?php

namespace App\Http\Controllers;

use Log;
use Illuminate\Http\Request;
use App\Exceptions\RttException;


/** should be dispatch controller
 */
class MCFController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->sp_rtt = app()->make('rtt_service');

        $this->consts['ALLOWED_ROLES'] = [
            'precreate_authpay'=>[1,2,101,666],
            'create_authpay'=>[1,2,101,666],
            'create_order'=>[1,2,101,666],
            'create_refund'=>[1,2,101,666],
            'check_order_status'=>[1,2,101,666],
            'get_exchange_rate'=>[1,2,101,666],
            'get_bills_range'=>[1,666],
        ];

        $this->consts['REQUEST_PARAS'] = [];
        $this->consts['REQUEST_PARAS']['precreate_authpay'] = [
            'vendor_channel'=>[
                'checker'=>['is_string', 8],
                'required'=>true,
                'description'=> '付款渠道，目前支持wx或者ali',
            ],
            'total_fee_in_cent'=>[
                'checker'=>'is_int',
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
                'checker'=>'is_int',
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
                'checker'=>'is_int',
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
                'description'=> 'enum("long_pulling","refresh","force_remote"), long_pulling仅查询缓存，refresh当缓存miss或者交易状态非成功时去支付渠道端查询，forece_remote强制去远端查询',
            ],
            'out_trade_no'=>[
                'checker'=>['is_string', 64],
                'required'=>true,
                'description'=> 'MCF开头的交易单号',
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['query_txns_by_time'] = [
            'start_time'=>[
                'checker'=>['is_int'],
                'required'=>true,
                'description'=> '开始时间的unix timestamp, inclusive',
            ],
            'end_time'=>[
                'checker'=>['is_int'],
                'required'=>true,
                'description'=> '结束时间的unix timestamp, exclusive',
            ],
            'offset'=>[
                'checker'=>['is_int'],
                'required'=>false,
                'default_value'=>0,
                'description'=> 'offset',
            ],
            'page_size'=>[
                'checker'=>['is_int', [1,50]],
                'required'=>false,
                'default_value'=>$this->sp_rtt->consts['DEFAULT_PAGESIZE'],
                'description'=> 'page size',
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
                'checker'=>'is_int',
                'required'=>true,
                'description'=> '退款金额，以分为单位的整数',
            ],
            'refund_fee_currency'=>[
                'checker'=>['is_string', 16],
                'required'=>true,
                'description'=> '退款币种，必须与标价金额的币种一致',
            ],
            'total_fee_in_cent'=>[
                'checker'=>'is_int',
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
        if (!$this->check_api_def())
            throw new RttException('SYSTEM_ERROR', "ERROR SETTING IN API SCHEMA");
    }

    public function precreate_authpay(Request $request){
        $userObj = $request->user('custom_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__);
        $la_paras['scenario'] = 'AUTHPAY';
        $ret = $this->sp_rtt->precreate_authpay($la_paras, $account_id);
        return $this->format_success_ret($ret);
    }

    public function create_authpay(Request $request){
        $userObj = $request->user('custom_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__);
        $la_paras['scenario'] = 'AUTHPAY'; 
        $ret = $this->sp_rtt->create_authpay($la_paras, $account_id);
        $ret['total_fee_in_cent'] = $la_paras['total_fee_in_cent'];
        $ret['total_fee_currency'] = $la_paras['total_fee_currency'];
        return $this->format_success_ret($ret);
    }

    public function create_order(Request $request)
    {
        $userObj = $request->user('custom_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__);
        $la_paras['scenario'] = 'NATIVE';
        $ret = $this->sp_rtt->create_order($la_paras, $account_id);
        $ret['total_fee_in_cent'] = $la_paras['total_fee_in_cent'];
        $ret['total_fee_currency'] = $la_paras['total_fee_currency'];
        return $this->format_success_ret($ret);
    }

    public function create_refund(Request $request)
    {
        $userObj = $request->user('custom_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__);
        $ret = $this->create_refund($la_paras, $account_id);
        return $this->format_success_ret($ret);
    }

    public function check_order_status(Request $request)
    {
        $userObj = $request->user('custom_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__);
        $status = $this->check_order_status($la_paras, $account_id);
        return $this->format_success_ret($status);
    }

    public function query_txns_by_time(Request $request){
        $userObj = $request->user('custom_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__);
        $infoObj = $this->sp_rtt->get_account_info($account_id);
        $ret = $this->sp_rtt->query_txns_by_time($la_paras, $account_id);
        return $this->format_success_ret($ret);
    }

    public function get_exchange_rate(Request $request)
    {
        $userObj = $request->user('custom_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__);
        $infoObj = $this->sp_rtt->get_account_info($account_id);
        if (!empty($infoObj->currency_type) && $infoObj->currency_type != $la_paras['currency_type'])
            throw new RttException("INVALID_PARAMETER", "currency_type");
        $sp = $this->sp_rtt->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
        $ret = $sp->get_exchange_rate($account_id,$la_paras['currency_type']);
        return $this->format_success_ret($ret);
    }

    public function handle_notify_wx(Request $request)
    {
        $sp = app()->make('wx_service');
        if (empty($sp)) return ;
        $sp->handle_notify(false);
    }
    //
}
