<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


function with_exception_handler($request, $callback)
{
        $ls_result = array();
        $ls_result['ev_error'] = 0;
        try {
            $ls_result['ev_data'] = $callback($request);
        } catch (\Exception $e) {
            $ls_result['ev_error'] = $e->getCode();
            $ls_result['ev_message'] = $e->getMessage();
        }
        return $ls_result;
}

/** should be dispatch controller
 */
class OrderController extends Controller
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
    }

    public function create_order(Request $request)
    {
        return with_exception_handler($request, function ($request) {
            $account_id = Auth::user()->account_id;
            $la_paras = $this->sp_rtt->parse_parameters($request);
            $infoObj = $this->sp_rtt->get_account_info($account_id);
            $la_paras['_out_trade_no'] = $this->sp_rtt->generate_txn_ref_id($la_paras, $infoObj->ref_id, 'ORDER');
            $sp = $this->sp_rtt->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
            return $sp->create_order($la_paras, $account_id);
        });
    }

    public function create_refund(Request $request)
    {
        return with_exception_handler($request, function ($request) {
            $account_id = Auth::user()->account_id;
            $la_paras = $this->sp_rtt->parse_parameters($request);
            $la_paras['_refund_id'] = $this->sp_rtt->generate_txn_ref_id($la_paras, null, 'REFUND');
            $sp = $this->sp_rtt->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
            return $sp->create_refund($la_paras, $account_id);
        });
    }
    public function query_txn_single(Request $request)
    {
        return with_exception_handler($request, function ($request) {
            $account_id = Auth::user()->account_id;
            $la_paras = $this->sp_rtt->parse_parameters($request);
            /*
            try {
                $ret = $this->rtt_sp->query_txn_single($la_paras, $account_id);
                if (!empty(ret))
                    return $ret;
            } catch (\Exception $e) {
            }
            */
            $sp = $this->sp_rtt->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
            if (strtoupper($la_paras['query_type']) != "REFUND") {
                $vendor_txn = $sp->query_charge_single($la_paras, $account_id);
                $txn = $sp->vendor_txn_to_rtt_txn($vendor_txn, $account_id);
            } else {
                $vendor_refund = $sp->query_refund_single($la_paras, $account_id);
                $txn = $sp->vendor_refund_to_rtt_txn($vendor_refund, $account_id);
            }
            //try {
                $this->sp_rtt->cache_txn($txn);
            //} catch (\Exception $e) { }
            $ret = $this->sp_rtt->txn_to_front_end($txn);
            return $ret;
        });
    }

    public function handle_notify_wx(Request $request)
    {
        $sp = app()->make('wx_service');
        if (empty($sp)) return ;
        $sp->handle_notify(false);
    }
    //
}
