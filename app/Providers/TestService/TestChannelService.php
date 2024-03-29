<?php
namespace App\Providers\TestService;

use Log;
use Closure;
//use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\RttException;

class TestChannelService{

    public $consts;
    public function __construct(){
        $this->consts = array();
        $this->consts['VENDOR_TZ'] = "Asia/Shanghai";
        $this->consts['CHANNEL_NAME'] = "tc"; 
        $this->consts['CHANNEL_FLAG'] = app()->make('rtt_service')
            ->consts['CHANNELS'][strtoupper($this->consts['CHANNEL_NAME'])];
        $this->consts['TXN_ATTRS'] = [
            ['ref_id', ], 
            ['vendor_channel', $this->consts['CHANNEL_FLAG']],
            ['vendor_txn_id',],
            ['vendor_txn_time', ],
            ['txn_scenario','NATIVE'],
            ['txn_fee_in_cent', ],
            ['txn_fee_currency','CAD'],
            ['paid_fee_in_cent', ],
            ['paid_fee_currency', 'CAD'],
            ['exchange_rate', '1'],
            ['customer_id', 'test_customer'],
            ['status', ],
            ['device_id', ],
            ['user_id',],
        ];
        $this->consts['TO_RTT_TXN']['FROM_REFUND'] = [
            ['ref_id', 'partner_refund_id'], 
            ['vendor_channel', null, $this->consts['CHANNEL_FLAG']],
            ['vendor_txn_id', null, null],
            ['vendor_txn_time', null, function () { return time(); }],
            ['txn_scenario', null, null],
            ['txn_fee_in_cent', 'refund_amount', function ($x) {
                return bcmul($x, 100);
            }],
            ['txn_fee_currency','currency'],
            ['paid_fee_in_cent','refund_amount_cny', function ($x) {
                return bcmul($x, 100);
            }],
            ['paid_fee_currency', null, "CNY"],
            ['exchange_rate','exchange_rate'],
            ['customer_id', null, null],
            ['status', null, 'SUCCESS'],
            ['device_id', 'device_id'],
            ['user_id','_uid'],
        ];
        $this->consts['TO_RTT_TXN']['DEFAULT'] = [
            ['ref_id', 'partner_trans_id'],  //ali has both out_trade_no and partner_trans_id as the same thing
            ['vendor_channel', null, $this->consts['CHANNEL_FLAG']],
            ['vendor_txn_id', 'alipay_trans_id'],
            ['vendor_txn_time', 'alipay_pay_time', function ($dtstr) {
                $dt = new \DateTime($dtstr, new \DateTimeZone($this->consts['VENDOR_TZ'])); 
                return $dt->getTimestamp();
            }],
            ['txn_scenario', 'scenario'], //TODO may get the wrong scenario if ali has the same name
            ['txn_fee_in_cent', 'trans_amount', function ($x) {
                return bcmul($x, 100);
            }],
            ['txn_fee_currency','currency'],
            ['paid_fee_in_cent','trans_amount_cny', function ($x) {
                return bcmul($x, 100);
            }],
            ['paid_fee_currency', null, "CNY"],
            ['exchange_rate','exchange_rate'],
            ['customer_id', 'alipay_buyer_user_id'],
            ['status', 'alipay_trans_status', function($state) { 
                return $this->consts['STATE_MAP'][$state] ?? "OTHER-AL-".$state;
            }],
            ['device_id', 'device_id'],
            ['user_id','_uid'],
        ];
        $this->consts['TO_RTT_TXN']['FROM_AUTHPAY'] = $this->consts['TO_RTT_TXN']['DEFAULT'];
    }

    private function get_account_info($account_id, $b_emptyAsException = true){
        return null;
    }
    private function make_txn($input)
    {
        return [
            'ref_id'=>$input['ref_id'] ?? $input['out_trade_no'] ?? $input['_out_trade_no'],
            'vendor_channel'=>$this->consts['CHANNEL_FLAG'],
            'ref_id'=>$input['ref_id'] ?? $input['out_trade_no'] ?? $input['_out_trade_no'],
            'vendor_txn_time'=>time(),
            'txn_scenario'=>'NATIVE',
            'txn_fee_in_cent'=>$input['total_fee_in_cent'],
            'txn_fee_currency'=>$input['total_fee_currency'] ?? 'CAD',
            'paid_fee_in_cent'=>$input['total_fee_in_cent'],
            'paid_fee_currency'=>$input['total_fee_currency'] ?? 'CAD',
            'exchange_rate'=>'1',
            'customer_id'=>'test_customer',
            'status'=>'SUCCESS',
            'device_id'=>$input['device_id'],
            'user_id'=>$input['_uid'],
        ];
    }

    public function create_authpay($la_paras, $account_id, callable $cb_new_order, callable $cb_order_update){
        $ret = [ 'out_trade_no' => $la_paras['_out_trade_no'], ];
        if (!$cb_new_order($la_paras['_out_trade_no'], $account_id,
            $this->consts['CHANNEL_NAME'], $la_paras, null))
            throw  new RttException('SYSTEM_ERROR', "duplicate order according to out_trade_no");
        try {
            $response = $this->make_txn($la_paras);
            $is_success = 'T';
            $result_code = 'SUCCESS';
        }
        catch(\Exception $e) {
            $ret['status'] = 'WAIT';
            $cb_order_update($la_paras['_out_trade_no'], 'WAIT', $e->getMessage());
            return $ret;
        }
        if ($is_success=="T" && $result_code=="SUCCESS") {
            $ret['status'] = 'SUCCESS';
            $cb_order_update($la_paras['_out_trade_no'], 'SUCCESS',
                $this->vendor_txn_to_rtt_txn($response, $account_id, 'FROM_AUTHPAY', $la_paras));
            return $ret;
        }
        $cb_order_update($la_paras['_out_trade_no'], 'FAIL', $errmsg);
        throw new RttException('AL_ERROR_BIZ', $errmsg);
    }
    public function get_rate_in_e_4($account_id){
        return 100;//1%
    }

    public function create_order($la_paras, $account_id, callable $cb_new_order, callable $cb_order_update){
        $ret = [ 'out_trade_no' => $la_paras['_out_trade_no'], ];
        if (!$cb_new_order($la_paras['_out_trade_no'], $account_id,
            $this->consts['CHANNEL_NAME'], $la_paras, null))
            throw  new RttException('SYSTEM_ERROR', "duplicate order according to out_trade_no");
        try {
            $response = $this->make_txn($la_paras);
            $is_success = 'T';
            $result_code = 'SUCCESS';
        }
        catch(\Exception $e) {
            $ret['status'] = 'FAIL';
            $cb_order_update($la_paras['_out_trade_no'], 'FAIL', $e->getMessage());
            return $ret;
        }
        $ret['status'] = 'WAIT';
        $cb_order_update($la_paras['_out_trade_no'], 'WAIT', null);
        $cb_order_update($la_paras['_out_trade_no'], 'SUCCESS',
                $this->vendor_txn_to_rtt_txn($response, $account_id, 'DEFAULT', $la_paras));
        return $ret;
    }

    public function create_refund($la_paras, $account_id, callable $cb_new_order, callable $cb_order_update){
        if (!$cb_new_order($la_paras['_refund_id'], $account_id,
            $this->consts['CHANNEL_NAME'], $la_paras, null))
        {
            //throw  new RttException('SYSTEM_ERROR', "duplicate order according to out_trade_no");
            Log::INFO("Allowing repeating refund. refund_id:".$la_paras['_refund_id']);
        }
        try {
            $ret = DB::table('txn_base')->where('ref_id',$la_paras['out_trade_no'])->first();
            if (empty($ret) || $ret->txn_fee_in_cent < $la_paras['refund_fee_in_cent'] || $la_paras['refund_no']!=1)
                throw new RttException('SYSTEM_ERROR', 'order not exists');
        }
        catch (\Exception $e) {
            $cb_order_update($la_paras['_refund_id'], 'FAIL', $e->getMessage());
            throw $e;
        }
        $cb_order_update($la_paras['_refund_id'], 'SUCCESS',
            $this->vendor_txn_to_rtt_txn($ret, $account_id, 'FROM_REFUND', $la_paras));
        $txn->is_refund = true;
        $txn->ref_id = $la_paras['_refund_id'];
        return $txn;
    }

    public function query_charge_single($la_paras, $account_id){
        $out_trade_no = $la_paras['out_trade_no'];
        $result = DB::table('txn_base')->where('ref_id',$out_trade_no)->first();
        if (empty($result)) return null;
        return (array) $result;
    }

    public function vendor_txn_to_rtt_txn($vendor_txn, $account_id, $sc_selector='DEFAULT', $moreInfo=null) {
        $is_refund = $sc_selector == 'FROM_REFUND';
        $ret = [
            'is_refund' => $is_refund,
            'account_id' => $account_id,
        ];
        return array_merge($vendor_txn, $ret);
    }

    public function get_exchange_rate($account_id, $fee_type) {
        return "6.6666";
    }
    public function set_vendor_channel($account_id, $values) {
    }
    public function get_vendor_channel_config($account_id) {
        return ['rate'=>$this->get_rate_in_e_4($account_id)];
    }
    private function create_request_common() {
        $input = array();
        return $input;
    }
}
