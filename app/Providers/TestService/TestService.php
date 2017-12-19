<?php
namespace App\Providers\TestService;

use Log;
use Closure;
//use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\RttException;

class TestService{

    public $consts;
    public function __construct(){
        $this->consts = array();
        $this->consts['VENDOR_TZ'] = "Asia/Shanghai";
        $this->consts['CHANNEL_NAME'] = "test"; 
        $this->consts['CHANNEL_FLAG'] = app()->make('rtt_service')
            ->consts['CHANNELS'][strtoupper($this->consts['CHANNEL_NAME'])];
        $this->consts['PARTNER_ID'] = env('CHANNEL_ALI_PARTNER_ID');
        $this->consts['KEY'] = env('CHANNEL_ALI_KEY');
        $this->consts['STATE_MAP'] = array(
            'WAIT_BUYER_PAY'=>'WAIT',
            'TRADE_SUCCESS'=>'SUCCESS',
        );
        $this->consts['TO_RTT_TXN'] = [];
        $this->consts['TO_RTT_TXN']['FROM_AUTHPAY'] = [
            ['ref_id', 'partner_trans_id'], 
            ['vendor_channel', null, $this->consts['CHANNEL_FLAG']],
            ['vendor_txn_id', 'alipay_trans_id'],
            ['vendor_txn_time', 'alipay_pay_time', function ($dtstr) {
                $dt = new \DateTime($dtstr, new \DateTimeZone($this->consts['VENDOR_TZ'])); 
                return $dt->getTimestamp();
            }],
            ['txn_scenario', 'scenario',], //TODO may get the wrong scenario if ali has the same name
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
            ['status', null, 'SUCCESS'],
            ['device_id', 'device_id'],
            ['user_id','_uid'],
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
            //['ref_id', 'out_trade_no'], 
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
    }

    private function get_account_info($account_id, $b_emptyAsException = true){
        return null;
    }

    public function create_authpay($la_paras, $account_id, callable $cb_new_order, callable $cb_order_update){
        $vendor_ali_info = $this->get_account_info($account_id);
        $input = $this->create_request_common();
        $ret = [ 'out_trade_no' => $la_paras['_out_trade_no'], ];
        if (!$cb_new_order($la_paras['_out_trade_no'], $account_id,
            $this->consts['CHANNEL_NAME'], $la_paras, $input))
            throw  new RttException('SYSTEM_ERROR', "duplicate order according to out_trade_no");
        try {
            Log::info("Send to AliPay server:".json_encode($input)."\n Encode Url:".$url);
            $result = getHttpResponseGET($url, null, $errmsg);
            Log::info("Received from AliPay server:".$result."\nErrmsg:".$errmsg);
        }
        catch(\Exception $e) {
            Log::DEBUG("enter wait because:".$e->getMessage());
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
        $errmsg = $response["detail_error_code"] ??  $error ?? $non_biz_error ?? "Error msg missing!";
        $cb_order_update($la_paras['_out_trade_no'], 'FAIL', $errmsg);
        throw new RttException('AL_ERROR_BIZ', $errmsg);
    }

    public function create_order($la_paras, $account_id, callable $cb_new_order, callable $cb_order_update){
        $vendor_ali_info = $this->get_account_info($account_id);
        $input = $this->create_request_common();
        if (!$cb_new_order($la_paras['_out_trade_no'], $account_id,
            $this->consts['CHANNEL_NAME'], $la_paras, $input))
            throw  new RttException('SYSTEM_ERROR', "duplicate order according to out_trade_no");
        try {
            Log::info("Send to AliPay server:".json_encode($input)."\n Encode Url:".$url);
            $result = getHttpResponseGET($url, null, $errmsg);
            Log::info("Received from AliPay server:".$result."\nErrmsg:".$errmsg);
        }
        catch (\Exception $e) {
            $cb_order_update($la_paras['_out_trade_no'], 'FAIL', $e->getMessage());
            throw $e;
        }
        $cb_order_update($la_paras['_out_trade_no'], 'WAIT', $ret);
        return ["out_trade_no"=>$la_paras['_out_trade_no'], "code_url"=>$ret["qr_code"]];
    }

    public function create_refund($la_paras, $account_id, callable $cb_new_order, callable $cb_order_update){
        $input = $this->create_request_common();
        if (!$cb_new_order($la_paras['_refund_id'], $account_id,
            $this->consts['CHANNEL_NAME'], $la_paras, $input))
            throw  new RttException('SYSTEM_ERROR', "duplicate order according to out_trade_no");
        try {
            Log::info("Send to AliPay server:".json_encode($input)."\n Encode Url:".$url);
            $result = getHttpResponseGET($url, null, $errmsg);
            Log::info("Received from AliPay server:".$result."\nErrmsg:".$errmsg);
            if ($result === false)
                throw new \Exception($errmsg, 2);
            $ret = parse_xml_check_err_throw($result, $this->consts['KEY']);
        }
        catch (\Exception $e) {
            $cb_order_update($la_paras['_refund_id'], 'FAIL', $e->getMessage());
            throw $e;
        }
        $cb_order_update($la_paras['_refund_id'], 'SUCCESS',
            $this->vendor_txn_to_rtt_txn($ret, $account_id, 'FROM_REFUND', $la_paras));
        return $ret;
    }

    public function query_charge_single($la_paras, $account_id){
        $input = $this->create_request_common();
        return parse_xml_check_err_throw($result, $this->consts['KEY']);
    }

    public function handle_notify($needSignOutput) {
    }

    public function vendor_txn_to_rtt_txn($vendor_txn, $account_id, $sc_selector='DEFAULT', $moreInfo=null) {
        $is_refund = $sc_selector == 'FROM_REFUND';
        $ret = [
            'is_refund' => $is_refund,
            'account_id' => $account_id,
        ];
        $attr_map = $this->consts['TO_RTT_TXN'][$sc_selector];
        foreach($attr_map as $item) {
            if (empty($item[1])) {
                if ($item[2] instanceof \Closure)
                    $ret[$item[0]] = $item[2]();
                else
                    $ret[$item[0]] = $item[2];
            } elseif (empty($item[2])) {
                $ret[$item[0]] = $vendor_txn[$item[1]] ?? $moreInfo[$item[1]] ?? null;
            } else {
                $ret[$item[0]] = $item[2]($vendor_txn[$item[1]] ?? $moreInfo[$item[1]] ?? null);
            }
        }
        return $ret;
    }

    public function get_exchange_rate($account_id, $fee_type) {
        return "6.6666";
    }
    public function set_vendor_channel($account_id, $values) {
    }
    public function get_vendor_channel_config($account_id) {
        return [];
    }
    private function create_request_common() {
        $input = array();
        return $input;
    }
}
