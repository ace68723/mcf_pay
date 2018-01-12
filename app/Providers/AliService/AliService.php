<?php
namespace App\Providers\AliService;

require_once __DIR__."/lib/alipay_core.function.php";
require_once __DIR__."/lib/alipay_md5.function.php";

use Log;
use Closure;
//use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\RttException;

function sec_to_short_str($sec) {
    if ($sec % 3600 == 0) 
        return ($sec/3600)."h";
    if ($sec % 60 == 0) 
        return ($sec/60)."m";
    return $sec."s";
}

function my_check_sign($response, $sign, $key) {
    $sign_str = getSignString($response);
    return hash_equals(md5Sign($sign_str, $key), $sign);
}
function parse_xml_check_err_throw($xmlstr, $key) {
    $result = parse_xml_response($xmlstr);
    if (!isset($result["is_success"]) || ($result["is_success"] != "T"))
        throw new RttException('AL_ERROR_VALIDATION', $result["error"]??"Error msg missing!");
    if (!isset($result["response"]) || !isset($result["response"]["alipay"]))
        throw new RttException('AL_ERROR_VALIDATION', "Malformed Response From AliPay Server");
    $response = $result["response"]["alipay"];
    if (!my_check_sign($response, $result["sign"], $key))
        throw new RttException('AL_ERROR_VALIDATION', "vendor response sign error");
    if (!isset($response["result_code"]))
        throw new RttException('AL_ERROR_BIZ',
            $response["detail_error_code"] ?? $response["error"] ?? "Error msg missing!");
    if ($response["result_code"] != "SUCCESS") {
        if (($response["detail_error_code"]??null) == "TRADE_NOT_EXIST")
            throw new RttException('NOT_FOUND_REMOTE', "OTHER-ALI");
        else throw new RttException('AL_ERROR_BIZ',
            $response["detail_error_code"] ?? $response["error"] ?? "Error msg missing!");
    }
    return $response;
}

class AliService{

    public $consts;
    public function __construct(){
        $this->consts = array();
        $this->consts['GATEWAY_URL'] = "https://intlmapi.alipay.com/gateway.do";
        //$this->consts['WEB_GATEWAY_URL'] = "https://mapi.alipay.com/gateway.do";
        $this->consts['VENDOR_TZ'] = "Asia/Shanghai";
        $this->consts['CHANNEL_NAME'] = "ali"; 
        $this->consts['CHANNEL_FLAG'] = app()->make('rtt_service')
            ->consts['CHANNELS'][strtoupper($this->consts['CHANNEL_NAME'])];
        //$this->consts['PARTNER_ID'] = "2088021966388155"; //public test account
        //$this->consts['KEY'] = "w0nu2sn0o97s8ruzrpj64fgc8vj8wus6";
        $this->consts['PARTNER_ID'] = env('CHANNEL_ALI_PARTNER_ID');
        $this->consts['KEY'] = env('CHANNEL_ALI_KEY');
        //$this->consts['DEFAULT_CURRENCY'] = "CAD";
        $this->consts['NOTIFY_URL'] = "https://mcfpayapi.ca/notify/ali";
        $this->consts['DEFAULT_EXPIRE_SEC'] = 1200; //"<integer>[m|h|d]";
        $this->consts['SCENARIO_MAP'] = array( //rtt to vendor scenario
            'NATIVE'=>"OVERSEAS_MBARCODE_PAY",
            'AUTHPAY'=>"OVERSEAS_MBARCODE_PAY",
        //$input['product_code'] = "QR_CODE_OFFLINE";
        //$input['product_code'] = "NEW_OVERSEAS_SELLER";
        );
        $this->consts['STATE_MAP'] = array( //vendor to rtt 
            'WAIT_BUYER_PAY'=>'WAIT',
            'TRADE_SUCCESS'=>'SUCCESS',
            //'TRADE_CLOSED'=>'FAIL', //ali regards totally refunded txn as closed, not sure how to map this
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
        $this->consts['TO_RTT_TXN']['FROM_NOTIFY'] = $this->consts['TO_RTT_TXN']['DEFAULT'];
    }

    private function get_account_info($account_id, $b_emptyAsException = true){
        $ret = empty($account_id) ? null: 
            DB::table('vendor_ali')->where('account_id','=',$account_id)->first();
        if ($b_emptyAsException && empty($ret))
            throw new RttException('SYSTEM_ERROR', "Missing Vendor Ali Entry. account_id:".$account_id);
        return $ret;
    }
    public function get_rate_in_e_4($account_id){
        $ret = $this->get_account_info($account_id);
        return intval($ret->rate);
    }

    public function create_authpay($la_paras, $account_id, callable $cb_new_order, callable $cb_order_update){
        $vendor_ali_info = $this->get_account_info($account_id);
        $input = $this->create_request_common();
        $input['service'] = "alipay.acquire.overseas.spot.pay";
        $input['alipay_seller_id'] = $input['partner'];
        $input['trans_name'] = substr(($la_paras['description'] ?? "Description Missing"), 0, 256);
        $input['partner_trans_id'] = $la_paras['_out_trade_no'];
        $input['currency'] = $la_paras['total_fee_currency'];
        $input['trans_amount'] = number_format(($la_paras["total_fee_in_cent"])/100, 2, ".", "");
        $input['buyer_identity_code'] = $la_paras['auth_code'];
        $input['identity_code_type'] = 'qrcode';//'barcode' will also work
        $scenario = $la_paras['scenario'] ?? null;
        $scenario = $this->consts['SCENARIO_MAP'][$scenario] ?? null;
        if (empty($scenario) || $scenario != 'OVERSEAS_MBARCODE_PAY') {
            throw  new RttException('SYSTEM_ERROR', "WRONG SCENARIO!");
        }
        $input['biz_product'] = 'OVERSEAS_MBARCODE_PAY';
        $sub_mch_info = array();
        $sub_mch_info["SECONDARY_MERCHANT_ID"] = $vendor_ali_info->sub_mch_id;
        $sub_mch_info["SECONDARY_MERCHANT_NAME"] = $vendor_ali_info->sub_mch_name;
        $sub_mch_info["SECONDARY_MERCHANT_INDUSTRY"] = $vendor_ali_info->sub_mch_industry;
        $sub_mch_info["store_name"] = $vendor_ali_info->sub_mch_name;
        $sub_mch_info["store_id"] = $vendor_ali_info->sub_mch_id;
        $input['extend_info'] = json_encode($sub_mch_info);
        $signString = getSignString($input);
        $input['sign'] = md5Sign($signString, $this->consts['KEY']);
        $url = $this->consts['GATEWAY_URL']."?".createLinkstringUrlencode($input);
        $ret = [ 'out_trade_no' => $la_paras['_out_trade_no'], ];
        if (!$cb_new_order($la_paras['_out_trade_no'], $account_id,
            $this->consts['CHANNEL_NAME'], $la_paras, $input))
            throw  new RttException('SYSTEM_ERROR', "duplicate order according to out_trade_no");
        try {
            Log::info("Send to AliPay server:".json_encode($input)."\n Encode Url:".$url);
            $result = getHttpResponseGET($url, null, $errmsg);
            Log::info("Received from AliPay server:".$result."\nErrmsg:".$errmsg);
            if ($result === false)
                throw new \Exception($errmsg);
            $key = $this->consts['KEY'];
            $result = parse_xml_response($result);
            $is_success = $result["is_success"]??null;
            $non_biz_error = $result["error"]??null;
            $response = $result["response"]["alipay"]??null;
            $error = $response["error"]??null;
            $result_code = $response["result_code"]??null;
            if ( $is_success == "F" && $non_biz_error =='ERROR'
                 || $is_success == "T" && $result_code =='UNKNOW' 
                 || $is_success == "T" && $result_code =='FAIL' && $error=='SYSTEM_ERROR' )
                throw new \Exception("authpay ali case 2");
            if (empty($response))
                throw new \Exception('AL_ERROR_VALIDATION'. " Malformed Response From AliPay Server");
            if (!my_check_sign($response, ($result["sign"]??null), $key))
                throw new \Exception('AL_ERROR_VALIDATION'. " sign error");
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
        $input['service'] = "alipay.acquire.precreate";
        $input['notify_url'] = $this->consts['NOTIFY_URL'];
        $input['timestamp'] = isset($la_paras['timestamp']) ? $la_paras['timestamp']."000" : time()."010";
        //$input['terminal_timestamp'] = time()."810";
        $input['out_trade_no'] = $la_paras['_out_trade_no'];
        $input['subject'] = $la_paras['description'] ?? "Description Missing";
        $scenario = $la_paras['scenario'] ?? null;
        $scenario = $this->consts['SCENARIO_MAP'][$scenario] ?? null;
        if (empty($scenario) || $scenario != 'OVERSEAS_MBARCODE_PAY')
            throw  new RttException('SYSTEM_ERROR', "WRONG SCENARIO!");
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
        if (!$cb_new_order($la_paras['_out_trade_no'], $account_id,
            $this->consts['CHANNEL_NAME'], $la_paras, $input))
            throw  new RttException('SYSTEM_ERROR', "duplicate order according to out_trade_no");
        try {
            Log::info("Send to AliPay server:".json_encode($input)."\n Encode Url:".$url);
            $result = getHttpResponseGET($url, null, $errmsg);
            Log::info("Received from AliPay server:".$result."\nErrmsg:".$errmsg);
            if ($result === false)
                throw new RttException('AL_ERROR_VALIDATION', $errmsg);
            $ret = parse_xml_check_err_throw($result, $this->consts['KEY']);
        }
        catch (\Exception $e) {
            $cb_order_update($la_paras['_out_trade_no'], 'FAIL', $e->getMessage());
            throw $e;
        }
        $cb_order_update($la_paras['_out_trade_no'], 'WAIT', $ret);
        return ["out_trade_no"=>$la_paras['_out_trade_no'], "code_url"=>$ret["qr_code"]];
    }

    public function create_refund($la_paras, $account_id, callable $cb_new_order, callable $cb_order_update){
        //$vendor_ali_info = $this->get_account_info($account_id);
        $input = $this->create_request_common();
        $input['service'] = "alipay.acquire.overseas.spot.refund";
        $input['partner_trans_id'] = $la_paras['out_trade_no'];
        $input['partner_refund_id'] = $la_paras['_refund_id'];
        $input['refund_amount'] = number_format(($la_paras['refund_fee_in_cent']??0)/100, 2, ".","");
        $input['currency'] = $la_paras['refund_fee_currency']; 
        $input['is_sync'] = "Y";
        $signString = getSignString($input);
        $input['sign'] = md5Sign($signString, $this->consts['KEY']);
        $url = $this->consts['GATEWAY_URL']."?".createLinkstringUrlencode($input);
        if (!$cb_new_order($la_paras['_refund_id'], $account_id,
            $this->consts['CHANNEL_NAME'], $la_paras, $input)) {
            Log::INFO("Allowing repeating refund. refund_id:".$la_paras['_refund_id']);
        }
        try {
            Log::info("Send to AliPay server:".json_encode($input)."\n Encode Url:".$url);
            $result = getHttpResponseGET($url, null, $errmsg);
            Log::info("Received from AliPay server:".$result."\nErrmsg:".$errmsg);
            if ($result === false)
                throw new \Exception($errmsg, 2);
            $ret = parse_xml_check_err_throw($result, $this->consts['KEY']);
        }
        catch (\Exception $e) {
            $cb_order_update($la_paras['_refund_id'], 'WAIT', $e->getMessage());
            throw $e;
        }
        $cb_order_update($la_paras['_refund_id'], 'SUCCESS',
            $this->vendor_txn_to_rtt_txn($ret, $account_id, 'FROM_REFUND', $la_paras));
        return $ret;
    }

    public function query_charge_single($la_paras, $account_id){
        $input = $this->create_request_common();
        $input['service'] = "alipay.acquire.overseas.query";
        $input['partner_trans_id'] = $la_paras['out_trade_no'];
        $signString = getSignString($input);
        $input['sign'] = md5Sign($signString, $this->consts['KEY']);
        $url = $this->consts['GATEWAY_URL']."?".createLinkstringUrlencode($input);
        Log::info("Send to AliPay server:".json_encode($input)."\n Encode Url:".$url);
        $result = getHttpResponseGET($url, null, $errmsg);
        Log::info("Received from AliPay server:".$result."\nErrmsg:".$errmsg);
        if ($result === false)
            throw new RttException('AL_ERROR_VALIDATION', $errmsg);
        return parse_xml_check_err_throw($result, $this->consts['KEY']);
    }
    public function query_refund_single($la_paras, $account_id){
        throw new RttException('SYSTEM_ERROR', __FUNCTION__.": Function Not Supported.");
        //$vendor_ali_info = $this->get_account_info($account_id);
        $input = $this->create_request_common();
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
            throw new RttException('AL_ERROR_VALIDATION', $errmsg);
        return parse_xml_check_err_throw($result, $this->consts['KEY']);
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
        //supposing this function is not frequently called
        if (!$is_refund) {
            $this->update_cached_exchange_rate($ret['txn_fee_currency'],
                $ret['exchange_rate'], $ret['vendor_txn_time']);
        }
        return $ret;
    }

    private function update_cached_exchange_rate($fee_type, $exchange_rate, $release_time) {
        $cacheID = "ali:exchange_rate:".$fee_type;
        $old = Cache::get($cacheID);
        if (empty($old['release_time']) || ($old['release_time'] < $release_time)) {
            if (!empty($old))
                Cache::forget($cacheID);
            Cache::forever($cacheID, [
                'exchange_rate'=>$exchange_rate,
                'release_time'=>$release_time,
            ]);
            //TODO may have lock problem??
        }
    }

    public function get_exchange_rate($account_id, $fee_type) {
        $cacheID = "ali:exchange_rate:".$fee_type;
        $old = Cache::get($cacheID);
        if (empty($old)) 
            throw new RttException('SYSTEM_ERROR', "unknown alipay exchange rate for ".$fee_type);
        return $old;
        // we have to skip the following as our contract with alipay doesnot include the interface
        $input = $this->create_request_common();
        $input['service'] = "forex_rate_file";
        $signString = getSignString($input);
        $input['sign'] = md5Sign($signString, $this->consts['KEY']);
        $url = $this->consts['GATEWAY_URL']."?".createLinkstringUrlencode($input);
        Log::info("Send to AliPay server:".json_encode($input)."\n Encode Url:".$url);
        $result = getHttpResponseGET($url, null, $errmsg);
        Log::info("Received from AliPay server:".$result."\nErrmsg:".$errmsg);
        if ($result === false)
            throw new RttException('AL_ERROR_VALIDATION', $errmsg);
        return $result;
    }
    public function set_vendor_channel($account_id, $values) {
        $values = array_intersect_key($values,
            array_flip(['sub_mch_id','sub_mch_name','sub_mch_industry','rate','is_deleted']));
        if (!empty($values))
            DB::table('vendor_ali')->updateOrInsert(['account_id'=>$account_id],$values);
    }
    public function get_vendor_channel_config($account_id) {
        $res = DB::table('vendor_ali')->where('account_id','=',$account_id)->first();
        if (!empty($res)) {
            return array_intersect_key((array)$res, array_flip(['sub_mch_id','sub_mch_name','sub_mch_industry','rate']));
        }
        return [];
    }
    public function handle_notify($request) {
        $input = $request->all();
		Log::DEBUG("call back:ali:" . json_encode($input, JSON_UNESCAPED_UNICODE));
        if (my_check_sign($input, ($input["sign"]??null), $this->consts['KEY'])
            && !empty($input['out_trade_no'])) 
        {
            $out_trade_no = $input['out_trade_no'];
            $sp = app()->make('rtt_service');
            $status = $sp->sp_oc->query_order_cache_field($out_trade_no,'status');
            if (empty($status) || !in_array($status,['INIT','WAIT']))
                //return 'status:'.$status;
                return 'status:'.$status;
            try {
                $result = $this->query_charge_single(['out_trade_no'=>$out_trade_no],null);
            }
            catch(\Exception $e) {
                Log::DEBUG(__FUNCTION__.":query failed:".$e->getMessage());
                return;
            }
            try {
                $sp->notify($this,$out_trade_no,$result);
            }
            catch(\Exception $e) {
                Log::DEBUG(__FUNCTION__.":parent process throws exception:".$e->getMessage());
            }
            return 'success';
        }
    }
    private function create_request_common() {
        $input = array();
        $input['partner'] = $this->consts['PARTNER_ID'];
        $input['_input_charset'] = "utf-8";
        $input['sign_type'] = "MD5";
        return $input;
    }
}
