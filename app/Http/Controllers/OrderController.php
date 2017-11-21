<?php

namespace App\Http\Controllers;

use Log;
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

        $is_str_max_len = function ($maxlen) {
            return function ($x) use ($maxlen) { return is_string($x) && strlen($x)<=$maxlen; };
        };
        $is_int_in_range = function ($larger_or_equal, $lesser_or_equal) {
            return function ($x) use ($larger_or_equal, $lesser_or_equal) {
                return is_int($x) && $x>=$larger_or_equal && $x<=$lesser_or_equal; 
            };
        };

        $this->consts['REQUEST_PARAS'] = [];
        $this->consts['IGNORED_REQ_PARAS'] = [
            'salt_str', 'account_key', 'sign', 'sign_type',
        ];
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
            'scenario'=>[
                'checker'=>['is_string', 16],
                'required'=>true,
            ],
            'description'=>[
                'checker'=>['is_string', 128],
            //    'required'=>false,
                'default_value'=>" Supported by ". $this->sp_rtt->consts['OUR_NAME'],
            ],
            'timestamp'=>[
                'checker'=>'is_int',
                'required'=>false,
            ],
            'expire_time_sec'=>[
                'checker'=>'is_int',
                'required'=>false,
            ],
            'passback_data'=>[
                'checker'=>['is_string', 256],
                'required'=>false,
            ],
            'extend_params'=>[
                'checker'=>'is_string',
                'required'=>false,
                'converter'=>'json_decode',
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['query_txn_single'] = [
            'vendor_channel'=>[
                'checker'=>['is_string', 8],
                'required'=>true,
            ],
            'query_type'=>[
                'checker'=>['is_string', 16],
                'required'=>true,
            ],
            'out_trade_no'=>[
                'checker'=>['is_string', 64],
                'required'=>true,
            ],
            'vendor_txn_id'=>[
                'checker'=>['is_string', 64],
                'required'=>false,
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
            throw new \Exception("ERROR SETTING IN API SCHEMA");
    }

    public function create_order(Request $request)
    {
        return with_exception_handler($request, function ($request) {
            $account_id = Auth::user()->account_id;
            $la_paras = $this->parse_parameters($request, "create_order");
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
            $la_paras = $this->parse_parameters($request, "create_refund");
            $la_paras['_refund_id'] = $this->sp_rtt->generate_txn_ref_id($la_paras, null, 'REFUND');
            $sp = $this->sp_rtt->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
            return $sp->create_refund($la_paras, $account_id);
        });
    }
    public function query_txn_single(Request $request)
    {
        return with_exception_handler($request, function ($request) {
            $account_id = Auth::user()->account_id;
            $la_paras = $this->parse_parameters($request, "query_txn_single");
            /*
            try {
                $ret = $this->rtt_sp->query_txn_single($la_paras, $account_id);
                if (!empty(ret))
                    return $ret;
            } catch (\Exception $e) {
            }
            */
            $sp = $this->sp_rtt->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
            if (strtoupper($la_paras['query_type']) == "CHARGE") {
                $vendor_txn = $sp->query_charge_single($la_paras, $account_id);
                $txn = $sp->vendor_txn_to_rtt_txn($vendor_txn, $account_id);
            } elseif (strtoupper($la_paras['query_type']) == "REFUND") {
                $vendor_refund = $sp->query_refund_single($la_paras, $account_id);
                $txn = $sp->vendor_refund_to_rtt_txn($vendor_refund, $account_id);
                /*
            } elseif (strtoupper($la_paras['query_type']) == "CHARGEBYMIN") {
                $find = false;
                $hint = $la_paras['out_trade_no'];
                $vendor_txn = null;
                for ($i=0; $i<6 && !$find; $i++) 
                    for ($j=0; $j<10 && !$find; $j++) {
                    try {
                        $la_paras['out_trade_no'] = $hint . $i . $j;
                        $vendor_txn = $sp->query_charge_single($la_paras, $account_id);
                        $find = true;
                    }
                    catch (\Exception $e) {
                        if ($e->getMessage() != "ORDERNOTEXIST") // only for wx
                            throw $e;
                    }
                }
                if (!$find)
                    throw new \Exception("NO BILL FIND", 3);
                $txn = $sp->vendor_txn_to_rtt_txn($vendor_txn, $account_id);
                 */
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
