<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;


function with_exception_handler($request, $callback)
{
        $ls_result = array();
        $ls_result['error_code'] = 0;
        try {
            $ls_result['ev_data'] = $callback($request);
        } catch (\Exception $e) {
            $ls_result['error_code'] = $e->getCode();
            $ls_result['error_msg'] = $e->getMessage();
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
            $la_paras = $this->sp_rtt->parse_parameters($request);
            if (empty($la_paras['account_id']))
                throw new \Exception("ACCOUNT ERROR", 1);
            $infoObj = $this->sp_rtt->get_account_info($la_paras['account_id']);
            $la_paras['_out_trade_no'] = $this->sp_rtt->generate_ref_id($la_paras, $infoObj->ref_id, 'ORDER');
            $sp = $this->sp_rtt->resolve_channel_sp($la_paras['account_id'], $la_paras['vendor_channel']);
            return $sp->create_order($la_paras);
        });
    }

    public function create_refund(Request $request)
    {
        return with_exception_handler($request, function ($request) {
            $la_paras = $this->sp_rtt->parse_parameters($request);
            if (empty($la_paras['account_id']))
                throw new \Exception("ACCOUNT ERROR", 1);
            $la_paras['_refund_id'] = $this->sp_rtt->generate_ref_id($la_paras, null, 'REFUND');
            $sp = $this->sp_rtt->resolve_channel_sp($la_paras['account_id'], $la_paras['vendor_channel']);
            return $sp->create_refund($la_paras);
        });
    }
    public function query_txn_single(Request $request)
    {
        return with_exception_handler($request, function ($request) {
            $la_paras = $this->sp_rtt->parse_parameters($request);
            if (empty($la_paras['account_id']))
                throw new \Exception("ACCOUNT ERROR", 1);
            /*
            try {
                $ret = $this->rtt_sp->query_txn_single($la_paras);
                if (!empty(ret))
                    return $ret;
            } catch (\Exception $e) {
            }
            */
            $sp = $this->sp_rtt->resolve_channel_sp($la_paras['account_id'], $la_paras['vendor_channel']);
            if (strtoupper($la_paras['query_type']) != "REFUND") {
                $vendor_txn = $sp->query_charge_single($la_paras);
                $txn = $sp->vendor_txn_to_rtt_txn($vendor_txn, ['account_id'=>$la_paras['account_id']]);
            } else {
                $vendor_refund = $sp->query_refund_single($la_paras);
                $txn = $sp->vendor_refund_to_rtt_txn($vendor_refund, ['account_id'=>$la_paras['account_id']]);
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
