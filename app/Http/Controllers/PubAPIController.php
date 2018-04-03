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
            'notify_url'=>[
                'checker'=>['is_string', 256],
                'required'=>true,
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
            'out_trade_no'=>[
                'checker'=>['is_string', 64],
                'required'=>true,
                'description'=> 'MCF开头的交易单号',
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['get_txn_by_id'] = [
            'ref_id'=>[
                'checker'=>['is_string', 64],
                'required'=>true,
                'description'=> 'out_trade_no or refund_id',
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

        if (!$this->check_api_def())
            throw new RttException('SYSTEM_ERROR', "ERROR SETTING IN API SCHEMA");
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

    public function check_order_status(Request $request)
    {
        $userObj = $request->user('custom_api');
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__, $userObj);
        $la_paras['type'] = "refresh";
        $status = $this->sp_rtt->check_order_status($la_paras, $account_id);
        return $this->format_success_ret($status);
    }

    public function get_txn_by_id(Request $request){
        $userObj = $request->user('custom_api');
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__, $userObj);
        $ret = $this->sp_rtt->get_txn_by_id($la_paras, $account_id);
        $this->sp_rtt->txn_to_export($ret);
        return $this->format_success_ret($ret);
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
        dispatch(new \App\Jobs\NotifyJob("http://xxx",'{"name":"testtxn"}',0));
        return "OK";
    }
}
