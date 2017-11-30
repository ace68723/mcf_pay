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
            'create_order'=>[1,2],
            'create_refund'=>[1,2],
            'check_order_status'=>[1,2],
            'get_exchange_rate'=>[1,2],
            'get_bills_range'=>[1],
        ];
        $this->consts['REQUEST_PARAS'] = [];
        $this->consts['REQUEST_PARAS']['create_order'] = [
            'vendor_channel'=>[
                'checker'=>['is_string', 8],
                'required'=>true,
            ],
            'total_fee_in_cent'=>[
                'checker'=>'is_int',
                'required'=>true,
            ],
            'total_fee_currency'=>[
                'checker'=>['is_string', 16],
                'required'=>true,
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['check_order_status'] = [
            'vendor_channel'=>[
                'checker'=>['is_string', 8],
                'required'=>false,
            ],
            'type'=>[
                'checker'=>['is_string', 16],
                'required'=>true,
            ],
            'out_trade_no'=>[
                'checker'=>['is_string', 64],
                'required'=>true,
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['create_refund'] = [
            'vendor_channel'=>[
                'checker'=>['is_string', 8],
                'required'=>true,
            ],
            'refund_no'=>[
                'checker'=>['is_int', [1,5]],
                'required'=>true,
            ],
            'refund_fee_in_cent'=>[
                'checker'=>'is_int',
                'required'=>true,
            ],
            'refund_fee_currency'=>[
                'checker'=>['is_string', 16],
                'required'=>true,
            ],
            'total_fee_in_cent'=>[
                'checker'=>'is_int',
                'required'=>true,
            ],
            'total_fee_currency'=>[
                'checker'=>['is_string', 16],
                'required'=>true,
            ],
            'out_trade_no'=>[
                'checker'=>['is_string', 64],
                'required'=>true,
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters
        if (!$this->check_api_def())
            throw new RttException('SYSTEM_ERROR', "ERROR SETTING IN API SCHEMA");
    }

    public function create_order(Request $request)
    {
        $userObj = $request->user('custom_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, "create_order");
        $infoObj = $this->sp_rtt->get_account_info($account_id);
        if (!empty($infoObj->currency_type) && $infoObj->currency_type != $la_paras['total_fee_currency'])
            throw new RttException("INVALID_PARAMETER", "currency_type");
        $la_paras['_out_trade_no'] = $this->sp_rtt->generate_txn_ref_id($la_paras, $infoObj->ref_id, 'ORDER');
        $la_paras['scenario'] = 'NATIVE';
        $la_paras['description'] = '^_^ Supported by MCF.';
        $sp = $this->sp_rtt->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
        $ret = $sp->create_order($la_paras, $account_id);
        $this->sp_rtt->post_create_order($la_paras, $ret);
        $ret['total_fee_in_cent'] = $la_paras['total_fee_in_cent'];
        return $this->format_success_ret($ret);
    }

    public function create_refund(Request $request)
    {
        $userObj = $request->user('custom_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__);
        $la_paras['_refund_id'] = $this->sp_rtt->generate_txn_ref_id($la_paras, null, 'REFUND');
        $sp = $this->sp_rtt->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
        $ret = $sp->create_refund($la_paras, $account_id);
        return $this->format_success_ret($ret);
    }
    public function check_order_status(Request $request)
    {
        $userObj = $request->user('custom_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $account_id = $userObj->account_id;
        $la_paras = $this->parse_parameters($request, __FUNCTION__);
        $cached_order = $this->sp_rtt->query_order_cache($la_paras['out_trade_no']);
        if (empty($cached_order))
            throw new RttException('NOT_FOUND', ["ORDER",$la_paras['out_trade_no']]);
        $status = $cached_order['status'];
        if ($la_paras['type'] == 'refresh' && $status != 'SUCCESS') {
            $sp = $this->sp_rtt->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
            $vendor_txn = $sp->query_charge_single($la_paras, $account_id);
            $txn = $sp->vendor_txn_to_rtt_txn($vendor_txn, $account_id);
            $status = $txn['status'];
            $this->sp_rtt->update_order_cache($la_paras['out_trade_no'], $status, $cached_order);
        }
        return $this->format_success_ret($status);
    }

    public function handle_notify_wx(Request $request)
    {
        $sp = app()->make('wx_service');
        if (empty($sp)) return ;
        $sp->handle_notify(false);
    }
    //
}
