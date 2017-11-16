<?php
namespace App\Providers\AliService;

require_once __DIR__."/lib/alipay_core.function.php";
require_once __DIR__."/lib/alipay_md5.function.php";

use Log;
//use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

function sec_to_short_str($sec) {
    if ($sec % 3600 == 0) 
        return ($sec/3600)."h";
    if ($sec % 60 == 0) 
        return ($sec/60)."m";
    return $sec."s";
}

function parse_xml_check_err_throw($result) {
    $result = xml_to_array($result);
    if (!isset($result["is_success"]) || ($result["is_success"] != "T"))
        throw new \Exception($result["error"]??"Error msg missing!", 2);
    if (!isset($result["response"]) || !isset($result["response"]["alipay"]))
        throw new \Exception("Malformed Response From AliPay Server", 2);
    //$verify_sign = checkSign(); //TODO
    $response = $result["response"]["alipay"];
    if (!isset($response["result_code"]) || ($response["result_code"] != "SUCCESS"))
        throw new \Exception($response["detail_error_code"] ??
                        $response["error"] ?? "Error msg missing!", 3);
    return $response;
}

class AliService{

    public $consts;
    public function __construct(){
        $this->consts = array();
        $this->consts['GATEWAY_URL'] = "https://intlmapi.alipay.com/gateway.do";
        $this->consts['VENDOR_TZ'] = "Asia/Shanghai";
        //$this->consts['CHANNEL_NAME'] = "ALI"; 
        $this->consts['CHANNEL_FLAG'] = app()->make('rtt_service')->consts['CHANNELS']['ALI'];
        //$this->consts['PARTNER_ID'] = "2088021966388155"; //public test account
        //$this->consts['KEY'] = "w0nu2sn0o97s8ruzrpj64fgc8vj8wus6";
        $this->consts['PARTNER_ID'] = env('CHANNEL_ALI_PARTNER_ID');
        $this->consts['KEY'] = env('CHANNEL_ALI_KEY');
        //$this->consts['DEFAULT_CURRENCY'] = "CAD";
        $this->consts['NOTIFY_URL'] = "http://www.rttpay.com/index.php/api/v1/test";
        $this->consts['DEFAULT_EXPIRE_SEC'] = 1200; //"<integer>[m|h|d]";
        $this->consts['SCENARIO_MAP'] = array( //rtt to vendor scenario
            'NATIVE'=>"OVERSEAS_MBARCODE_PAY",
        //$input['product_code'] = "QR_CODE_OFFLINE";
        //$input['product_code'] = "NEW_OVERSEAS_SELLER";
        );
        $this->consts['STATE_MAP'] = array( //vendor to rtt 
            'TRADE_SUCCESS'=>'SUCCESS',
        );
    }

    private function get_account_info($account_id, $b_emptyAsException = true){
        $ret = empty($account_id) ? null: 
            DB::table('vendor_ali')->where('account_id','=',$account_id)->first();
        if ($b_emptyAsException && empty($ret))
            throw new \Exception("Missing Vendor Ali Entry", 1);
        return $ret;
    }

    public function create_order($la_paras, $account_id){
        $vendor_ali_info = $this->get_account_info($account_id);
        $input = $this->create_request_common($la_paras);
        $input['service'] = "alipay.acquire.precreate";
        $input['notify_url'] = $this->consts['NOTIFY_URL'];
        $input['timestamp'] = isset($la_paras['timestamp']) ? $la_paras['timestamp']."000" : time()."010";
        //$input['terminal_timestamp'] = time()."810";
        $input['out_trade_no'] = $la_paras['_out_trade_no'];
        $input['subject'] = $la_paras['description'] ?? "Description Missing";
        $scenario = $la_paras['scenario'] ?? null;
        $scenario = $this->consts['SCENARIO_MAP'][$scenario] ?? null;
        if (empty($scenario) || $scenario != 'OVERSEAS_MBARCODE_PAY')
            throw  new \Exception("WRONG SCENARIO!", 1);
        $input['product_code'] = 'OVERSEAS_MBARCODE_PAY';
        $input['total_fee'] = number_format(($la_paras["total_fee_in_cent"])/100, 2, ".", "");
        //$input['seller_id'] = $this->consts['PARTNER_ID'];
        $input['body'] = json_encode(array('salt_str'=>base64_encode(random_bytes(20))));
        //$input['show_url'] = $this->consts['NOTIFY_URL'];
        $input['currency'] = $la_paras['total_fee_currency'];
        $input['trans_currency'] = $input['currency'];
        $sub_mch_info = array();
        $sub_mch_info["SECONDARY_MERCHANT_ID"] = $vendor_ali_info->sub_mch_id;
        $sub_mch_info["SECONDARY_MERCHANT_NAME"] = $vendor_ali_info->sub_mch_name;
        $sub_mch_info["SECONDARY_MERCHANT_INDUSTRY"] = $vendor_ali_info->sub_mch_industry;
        $input['extend_params'] = json_encode($sub_mch_info);
        //'{"STORE_ID":"BJ_ZZ_001","STORE_NAME":"Muku in the Dreieichstrabe","SECONDARY_MERCHANT_ID":"A80001","SECONDARY_MERCHANT_NAME":"Muku","SECONDARY_MERCHANT_INDUSTRY":"7011"}'; 
        $input['it_b_pay'] = sec_to_short_str($la_paras['expire_time_sec'] ?? $this->consts['DEFAULT_EXPIRE_SEC']);
        //$input['passback_parameters'] = json_encode(array('salt_str'=>base64_encode(random_bytes(20))));
        $signString = getSignString($input);
        $input['sign'] = md5Sign($signString, $this->consts['KEY']);
        $url = $this->consts['GATEWAY_URL']."?".createLinkstringUrlencode($input);
        Log::info("Send to AliPay server:".json_encode($input)."\n Encode Url:".$url);
        $result = getHttpResponseGET($url, null, $errmsg);
        Log::info("Received from AliPay server:".$result."\nErrmsg:".$errmsg);
        if ($result === false)
            throw new \Exception($errmsg, 2);
        return parse_xml_check_err_throw($result);
    }

    public function query_charge_single($la_paras, $account_id){
        $vendor_ali_info = $this->get_account_info($account_id);
        $input = $this->create_request_common($la_paras);
        $input['service'] = "alipay.acquire.overseas.query";
        $input['partner_trans_id'] = $la_paras['out_trade_no'];
        $signString = getSignString($input);
        $input['sign'] = md5Sign($signString, $this->consts['KEY']);
        $url = $this->consts['GATEWAY_URL']."?".createLinkstringUrlencode($input);
        Log::info("Send to AliPay server:".json_encode($input)."\n Encode Url:".$url);
        $result = getHttpResponseGET($url, null, $errmsg);
        Log::info("Received from AliPay server:".$result."\nErrmsg:".$errmsg);
        if ($result === false)
            throw new \Exception($errmsg, 2);
        return parse_xml_check_err_throw($result);
    }
    public function query_refund_single($la_paras, $account_id){
        throw new \Exception("Not Supported.", 2);
        $vendor_ali_info = $this->get_account_info($account_id);
        $input = $this->create_request_common($la_paras);
        $input['service'] = "alipay.acquire.overseas.query";
        if (!empty($la_paras['refund_id']))
            $input['partner_refund_id'] = $la_paras['refund_id'];
        $input['partner_trans_id'] = $la_paras['out_trade_no'];
        $signString = getSignString($input);
        $input['sign'] = md5Sign($signString, $this->consts['KEY']);
        $url = $this->consts['GATEWAY_URL']."?".createLinkstringUrlencode($input);
        Log::info("Send to AliPay server:".json_encode($input)."\n Encode Url:".$url);
        $result = getHttpResponseGET($url, null, $errmsg);
        Log::info("Received from AliPay server:".$result."\nErrmsg:".$errmsg);
        if ($result === false)
            throw new \Exception($errmsg, 2);
        return parse_xml_check_err_throw($result);
    }

    public function create_refund($la_paras, $account_id){
        $vendor_ali_info = $this->get_account_info($account_id);
        $input = $this->create_request_common($la_paras);
        $input['service'] = "alipay.acquire.overseas.spot.refund";
        $input['partner_trans_id'] = $la_paras['out_trade_no'];
        $input['partner_refund_id'] = $la_paras['_refund_id'];
        $input['refund_amount'] = number_format(($la_paras['refund_fee_in_cent']??0)/100, 2, ".","");
        $input['currency'] = $la_paras['refund_fee_currency']; 
        $input['is_sync'] = "Y";
        $signString = getSignString($input);
        $input['sign'] = md5Sign($signString, $this->consts['KEY']);
        $url = $this->consts['GATEWAY_URL']."?".createLinkstringUrlencode($input);
        Log::info("Send to AliPay server:".json_encode($input)."\n Encode Url:".$url);
        $result = getHttpResponseGET($url, null, $errmsg);
        Log::info("Received from AliPay server:".$result."\nErrmsg:".$errmsg);
        if ($result === false)
            throw new \Exception($errmsg, 2);
        return parse_xml_check_err_throw($result);
    }

    public function handle_notify($needSignOutput) {
    }

    public function vendor_txn_to_rtt_txn($ali_txn, $account_id) {
        $ret = array();
        $attr_map = [
            ['ref_id', 'out_trade_no'], 
            ['is_refund', null, false],
            ['account_id', null, $account_id],
            ['vendor_channel', null, $this->consts['CHANNEL_FLAG']],
            ['vendor_txn_id', 'alipay_trans_id'],
            ['vendor_txn_time', 'alipay_pay_time', function ($dtstr) {
                $dt = new \DateTime($dtstr, new \DateTimeZone($this->consts['VENDOR_TZ'])); 
                return $dt->getTimestamp();
            }],
            ['txn_scenario', null, "NATIVE"],
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
        ];
        foreach($attr_map as $item) {
            if (empty($item[1])) {
                $ret[$item[0]] = $item[2];
                continue;
            } elseif (empty($item[2])) {
                $ret[$item[0]] = $ali_txn[$item[1]] ?? null;
            } else {
                $ret[$item[0]] = $item[2]($ali_txn[$item[1]] ?? null);
            }
        }
        return $ret;
    }

    private function create_request_common($la_paras) {
        $input = array();
        $input['partner'] = $this->consts['PARTNER_ID'];
        $input['_input_charset'] = "utf-8";
        $input['sign_type'] = "MD5";
        return $input;
    }
}
