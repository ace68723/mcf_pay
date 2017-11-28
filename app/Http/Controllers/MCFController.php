<?php

namespace App\Http\Controllers;

use Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
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
        //$this->sp_generic = app()->make('generic_service');
        $this->sp_rtt = app()->make('rtt_service');

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
            throw new RttException('SYSTEM_ERROR', "ERROR SETTING IN API SCHEMA");
    }

    public function create_order(Request $request)
    {
        $account_id = Auth::user()->account_id;
        $la_paras = $this->parse_parameters($request, "create_order");
        $infoObj = $this->sp_rtt->get_account_info($account_id);
        $la_paras['_out_trade_no'] = $this->sp_rtt->generate_txn_ref_id($la_paras, $infoObj->ref_id, 'ORDER');
        $sp = $this->sp_rtt->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
        $ret = $sp->create_order($la_paras, $account_id);
        return $this->format_success_ret($ret);
    }

    public function create_refund(Request $request)
    {
        $account_id = Auth::user()->account_id;
        $la_paras = $this->parse_parameters($request, "create_refund");
        $la_paras['_refund_id'] = $this->sp_rtt->generate_txn_ref_id($la_paras, null, 'REFUND');
        $sp = $this->sp_rtt->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
        $ret = $sp->create_refund($la_paras, $account_id);
        return $this->format_success_ret($ret);
    }
    public function query_txn_single(Request $request)
    {
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
        return $this->format_success_ret($ret);
    }

    public function handle_notify_wx(Request $request)
    {
        $sp = app()->make('wx_service');
        if (empty($sp)) return ;
        $sp->handle_notify(false);
    }
    //
    public function test(Request $request)
    {
        if (!Redis::exists('count')) {
            Redis::setEx('count', 3, 0);
        }
        $count = Redis::get('count')+1;
        Redis::set('count',$count);
        return $count;
        /*
        if (!Cache::has('count')) {
            Cache::put('count', 0, 3);
        }
        return Cache::increment('count');
         */
    }
}
