<?php
namespace App\Providers\WxService;

require_once __DIR__."/lib/WxPay.Api.php";
require_once __DIR__.'/lib/WxPay.Notify.php';
require_once __DIR__.'/lib/WxPay.Exception.php';

use Log;
use Closure;
//use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\RttException;

function checkErrToThrow($result)
{
    if (!isset($result["return_code"]) || ($result["return_code"] != "SUCCESS"))
        throw new RttException('WX_ERROR_VALIDATION', $result["return_msg"]??"Error msg missing!");
    if (!isset($result["result_code"]) || ($result["result_code"] != "SUCCESS"))
        throw new RttException('WX_ERROR_BIZ', $result["err_code"]??"Error msg missing!");
}
function delInvisibleChars($str){
    $length = mb_strlen($str,'utf-8');
    $return = "";
    for ($i=0; $i < $length; $i++) {
        $_tmpStr = mb_substr($str,$i,1,'utf-8');
        $ascii = ord($_tmpStr);
        if($ascii < 32 || $ascii > 126){
            continue;
        }
        $return .= $_tmpStr;
    }
    return $return;
}

class WxService
{

    public $consts;
    public $data;
    public function __construct(){
        $this->consts = array();
        $this->consts['GATEWAY_ADDR'] = "";
        $this->consts['VENDOR_TZ'] = "Asia/Shanghai";
        $this->consts['EXCHANGE_RATE_UPDATE_HOUR'] = 10;
        $this->consts['CHANNEL_NAME'] = 'wx'; 
        $this->consts['CHANNEL_FLAG'] = app()->make('rtt_service')
            ->consts['CHANNELS'][strtoupper($this->consts['CHANNEL_NAME'])];
        //$this->consts['NOTIFY_URL'] = "http://paysdk.weixin.qq.com/example/notify.php";
        //$this->consts['NOTIFY_URL'] = "http://www.rttpay.com/index.php/api/v1/test";
        $this->consts['NOTIFY_URL'] = "https://mcfpayapi.ca/notify/wx";
        $this->consts['DEFAULT_EXPIRE_SEC'] = 3600;
        $this->consts['SCENARIO_MAP'] = [
            'NATIVE'=>'NATIVE',
            'AUTHPAY'=>'MICROPAY',
        ];
        $this->consts['STATE_MAP'] = array( //vendor to rtt 
            'REFUND'=>'SUCCESS', //wx REFUND means has refund
            'SUCCESS'=>'SUCCESS',
            'CLOSED'=>'FAIL',
            'USERPAYING'=>'WAIT',
            'NOTPAY'=>'WAIT',
            'REVOKED'=>'FAIL',
            'PAYERROR'=>'FAIL',
        );

        $this->consts['TO_RTT_TXN'] = [];
        $this->consts['TO_RTT_TXN']['DEFAULT'] = [
            ['vendor_channel', null, $this->consts['CHANNEL_FLAG']],
            ['ref_id', 'out_trade_no'], 
            ['vendor_txn_id', 'transaction_id'],
            ['vendor_txn_time', 'time_end', function ($dtstr) { 
                $dt = new \DateTime($dtstr, new \DateTimeZone($this->consts['VENDOR_TZ'])); 
                return $dt->getTimestamp();
            }],
            ['txn_scenario', 'trade_type', function($trade_type) { 
                foreach ($this->consts['SCENARIO_MAP'] as $key=>$value) {
                    if ($trade_type == $value) return $key;
                }
                return "WX-".$trade_type;
            }],
            ['txn_fee_in_cent', 'total_fee'],
            ['txn_fee_currency', 'fee_type'],
            ['paid_fee_in_cent', 'cash_fee'],
            ['paid_fee_currency', 'cash_fee_type'],
            ['customer_id', 'openid'],
            ['status', 'trade_state', function($state) {
                return $this->consts['STATE_MAP'][$state] ?? "OTHER-WX-".$state;
            }],
            ['exchange_rate','rate', function ($rate) {
                if (is_null($rate)) return null;
                return bcdiv($rate, 10**8, 8);
            }],
            ['device_id', 'device_id'],
            ['user_id', '_uid'],
        ];
        $this->consts['TO_RTT_TXN']['FROM_NOTIFY'] = $this->consts['TO_RTT_TXN']['DEFAULT'];
        $this->consts['TO_RTT_TXN']['FROM_REFUND'] = [
            ['vendor_channel', null, $this->consts['CHANNEL_FLAG']],
            ['ref_id', 'out_refund_no'],
            ['vendor_txn_id', 'refund_id'],
            ['vendor_txn_time', null, function () { return time(); }],
            ['txn_scenario', null, 'REFUND'],
            ['txn_fee_in_cent', 'refund_fee'],
            ['txn_fee_currency', 'refund_fee_type'],
            ['paid_fee_in_cent', 'cash_refund_fee'],
            ['paid_fee_currency', 'cash_refund_fee_type'],
            ['customer_id', null, null],
            ['status', null, 'SUCCESS' ],
            ['exchange_rate','rate', function ($rate) {
                if (is_null($rate)) return null;
                return bcdiv($rate, 10**8, 8);
            }],
            ['txn_link_id', 'out_trade_no'],
            ['device_id', 'device_id'],
            ['user_id', '_uid'],
        ];
        $this->consts['TO_RTT_TXN']['FROM_DB_RAW_CHARGE'] = [
            ['vendor_channel', null, $this->consts['CHANNEL_FLAG']],
            ['ref_id', 'out_transaction_id'],
            ['vendor_txn_id', 'transaction_id'],
            ['vendor_txn_time', 'transaction_time', ],
            ['txn_scenario', null, 'OTHER-C'],
            ['txn_fee_in_cent', 'total_fee'],
            ['txn_fee_currency', 'fee_type'],
            ['paid_fee_in_cent', 'cash_fee'],
            ['paid_fee_currency', 'cash_fee_type'],
            ['customer_id', 'openid'],
            ['status', null, 'SUCCESS' ],
            ['exchange_rate','exchange_rate', function ($rate) {
                if (is_null($rate)) return null;
                return bcdiv($rate, 10**8, 8);
            }],
            ['txn_link_id', null, null ],
            ['device_id', null, null],
            ['user_id', null, null],
        ];
        $this->consts['TO_RTT_TXN']['FROM_DB_RAW_REFUND'] = [
            ['vendor_channel', null, $this->consts['CHANNEL_FLAG']],
            ['ref_id', 'out_refund_no'],
            ['vendor_txn_id', 'refund_id'],
            ['vendor_txn_time', 'transaction_time', ],
            ['txn_scenario', null, 'OTHER-R'],
            ['txn_fee_in_cent', 'refund_fee'],
            ['txn_fee_currency', 'refund_currency_type'],
            ['paid_fee_in_cent', 'payers_refund_amount'],
            ['paid_fee_currency', 'payers_refund_currency_type'],
            ['customer_id', 'openid'],
            ['status', null, 'SUCCESS' ],
            ['exchange_rate','refund_exchange_rate', function ($rate) {
                if (is_null($rate)) return null;
                return bcdiv($rate, 10**8, 8);
            }],
            ['txn_link_id', 'out_transaction_id'],
            ['device_id', null, null],
            ['user_id', null, null],
        ];
    }
    private function get_account_info($account_id, $b_emptyAsException = true){
        $ret = empty($account_id) ? null: 
            DB::table('vendor_wx')->where('account_id','=',$account_id)->first();
        if ($b_emptyAsException && empty($ret)) {
            throw new RttException('SYSTEM_ERROR', "Missing Vendor Wx Entry");
        }
        return $ret;
    }
    public function get_rate_in_e_4($account_id){
        $ret = $this->get_account_info($account_id);
        return intval($ret->rate);
    }

    public function create_authpay($la_paras, $account_id, callable $cb_new_order, callable $cb_order_update){
        $vendor_wx_info = $this->get_account_info($account_id);
        $sub_mch_id = $vendor_wx_info->sub_mch_id;
        $input = new \WxPayMicroPay();
        $input->SetSub_mch_id($sub_mch_id);
        $input->SetAuth_code($la_paras['auth_code']);
        $input->SetBody(mb_strcut($la_paras["description"] ?? "Description Missing", 0, 32));
        // yes, the string length is different from native pay, 32 <-> 128
        $input->SetTotal_fee($la_paras["total_fee_in_cent"]);
        $input->SetFee_type($la_paras["total_fee_currency"]);
        $input->SetOut_trade_no($la_paras['_out_trade_no']);

        $scenario = $la_paras['scenario'] ?? null;
        $scenario = $this->consts['SCENARIO_MAP'][$scenario] ?? null;
        if (empty($scenario) || $scenario != 'MICROPAY')
            throw  new RttException('SYSTEM_ERROR', "WRONG SCENARIO!");
        $ret = [ 'out_trade_no' => $la_paras['_out_trade_no'], ];
        if (!$cb_new_order($la_paras['_out_trade_no'], $account_id,
            $this->consts['CHANNEL_NAME'], $la_paras, $input->GetValues()))
            throw  new RttException('SYSTEM_ERROR', "duplicate order according to out_trade_no");
        Log::info("Send to WxPay server:".json_encode($input->GetValues(), JSON_UNESCAPED_UNICODE));
        try {
            $result = \WxPayApi::micropay($input, 10);
        }
        catch(\Exception $e) {
            Log::DEBUG("exception in sending wx micropay!".$e->getMessage());
            $ret['status'] = 'WAIT';
            $cb_order_update($la_paras['_out_trade_no'], 'WAIT', $e->getMessage());
            return $ret;
        }
        Log::info("Received from WxPay server:".json_encode($result, JSON_UNESCAPED_UNICODE));
		if(!array_key_exists("return_code", $result)
            //|| !array_key_exists("out_trade_no", $result)
            //the wx official example code does not work here,
            //userpaying retrun message does not have an out_trade_no
            || !array_key_exists("result_code", $result)
            || ($result["return_code"] == "SUCCESS" && $result["result_code"] == "FAIL" && 
		        $result["err_code"] != "USERPAYING" && $result["err_code"] != "SYSTEMERROR"))
        {
            $errmsg = $result["err_code"]??"Error msg missing!";
            $cb_order_update($la_paras['_out_trade_no'], 'FAIL', $errmsg);
            throw new RttException('WX_ERROR_BIZ', $errmsg);
        }
        if ($result['result_code'] != 'SUCCESS') {
            // && in_array($result['err_code']??null, ['USERPAYING','SYSTEMERROR']))
            $ret['status'] = 'WAIT';
            $cb_order_update($la_paras['_out_trade_no'], 'WAIT', $result['err_code']);
            return $ret;
        }
        $ret['status'] = 'SUCCESS';
        $result['trade_state'] = 'SUCCESS'; // this is used in vendor_txn_to_rtt_txn
        $cb_order_update($la_paras['_out_trade_no'], 'SUCCESS',
            $this->vendor_txn_to_rtt_txn($result, $account_id, 'DEFAULT', $la_paras));
        return $ret;
    }

    public function create_order($la_paras, $account_id, callable $cb_new_order, callable $cb_order_update){
        $vendor_wx_info = $this->get_account_info($account_id);
        $sub_mch_id = $vendor_wx_info->sub_mch_id;
        $input = new \WxPayUnifiedOrder();
        $input->SetBody(mb_strcut($la_paras["description"] ?? "Description Missing", 0, 128));
        $input->SetOut_trade_no($la_paras['_out_trade_no']);
        $input->SetSub_mch_id($sub_mch_id);
        $input->SetTotal_fee($la_paras["total_fee_in_cent"]);
        $input->SetFee_type($la_paras["total_fee_currency"]);
        if (array_key_exists('expire_time_sec',$la_paras)) {
            $dt = new \DateTime("now", new \DateTimeZone($this->consts['VENDOR_TZ']));
            $dt->setTimestamp(time() + $la_paras['expire_time_sec']);
            $input->SetTime_expire($dt->format("YmdHis"));
            //error msg from WxPay when expire_time is previous: "time_expire时间过短，刷卡至少1分钟，其他5分钟"
            //$input->SetTime_start(date("YmdHis")); //Note the timezone
            //$input->SetTime_expire(date("YmdHis", time() + $this->consts['DEFAULT_EXPIRE_SEC']));
        }
        $input->SetNotify_url($this->consts['NOTIFY_URL']);
        $scenario = $la_paras['scenario'] ?? null;
        $scenario = $this->consts['SCENARIO_MAP'][$scenario] ?? null;
        if (empty($scenario) || $scenario != 'NATIVE')
            throw  new RttException('SYSTEM_ERROR', "WRONG SCENARIO!");
        $input->SetTrade_type("NATIVE");
        $input->SetProduct_id(date("YmdHis"));
        if (!$cb_new_order($la_paras['_out_trade_no'], $account_id,
            $this->consts['CHANNEL_NAME'], $la_paras, $input->GetValues()))
            throw  new RttException('SYSTEM_ERROR', "duplicate order according to out_trade_no");
        try {
            Log::info("Send to WxPay server:".json_encode($input->GetValues(), JSON_UNESCAPED_UNICODE));
            $result = \WxPayApi::unifiedOrder($input, 10);
            Log::info("Received from WxPay server:".json_encode($result, JSON_UNESCAPED_UNICODE));
            checkErrToThrow($result);
        } catch (\Exception $e) {
            $cb_order_update($la_paras['_out_trade_no'], 'FAIL', $e->getMessage());
            throw $e;
        }
        $cb_order_update($la_paras['_out_trade_no'], 'WAIT', $result);
        return array("out_trade_no"=>$la_paras['_out_trade_no'], "code_url"=>$result["code_url"]);
    }

    public function create_refund($la_paras, $account_id, callable $cb_new_order, callable $cb_order_update) {
        $vendor_wx_info = $this->get_account_info($account_id);
        if (empty($la_paras['out_trade_no']))
            throw new RttException('SYSTEM_ERROR', "Out_trade_no Missing");
        if (!empty($la_paras['total_fee_currency']) 
            && $la_paras['refund_fee_currency'] != $la_paras['total_fee_currency'])
            throw new RttException("Refund Currency must match!", 1);
        $input = new \WxPayRefund();
        //$input->SetTransaction_id($la_paras['wx_txn_id']);
        $input->SetOut_trade_no($la_paras['out_trade_no']);
        $input->SetTotal_fee($la_paras['total_fee_in_cent']);
        $input->SetRefund_fee($la_paras['refund_fee_in_cent']);
        $input->SetRefund_fee_type($la_paras['refund_fee_currency']);
        $input->SetOut_refund_no($la_paras['_refund_id']);
        $input->SetOp_user_id(\WxPayConfig_MCHID);
        $input->SetSub_mch_id($vendor_wx_info->sub_mch_id);
        if (!$cb_new_order($la_paras['_refund_id'], $account_id,
            $this->consts['CHANNEL_NAME'], $la_paras, $input->GetValues())) {
            Log::INFO("Allowing repeating refund. refund_id:".$la_paras['_refund_id']);
        }
        try {
            Log::info(__FUNCTION__.":wx:sending:". json_encode($input->GetValues(), JSON_UNESCAPED_UNICODE));
            $result = \WxPayApi::refund($input);
            Log::info(__FUNCTION__.":wx:received:". json_encode($result, JSON_UNESCAPED_UNICODE));
            checkErrToThrow($result);        
        } catch (\Exception $e) {
            $cb_order_update($la_paras['_refund_id'], 'WAIT', $e->getMessage());
            throw $e;
        }
        $cb_order_update($la_paras['_refund_id'], 'SUCCESS',
            $this->vendor_txn_to_rtt_txn($result, $account_id, 'FROM_REFUND', $la_paras));
		return $result;
	}

    public function query_charge_single($la_paras, $account_id) {
        $vendor_wx_info = $this->get_account_info($account_id);
        if (empty($la_paras['out_trade_no']))
            throw new RttException('SYSTEM_ERROR', "Out_trade_no Missing");
        $input = new \WxPayOrderQuery();
        $input->SetOut_trade_no($la_paras['out_trade_no']);
        $input->SetSub_mch_id($vendor_wx_info->sub_mch_id);
        Log::DEBUG("query_txn_single_wx:sending:" . json_encode($input->GetValues(), JSON_UNESCAPED_UNICODE));
        $result = \WxPayApi::orderQuery($input);
        Log::DEBUG("query_txn_single_wx:received:" . json_encode($result, JSON_UNESCAPED_UNICODE));
        checkErrToThrow($result);
        if (in_array($result['trade_state'], ['NOTPAY','USERPAYING'])) {
            throw new RttException('NOT_FOUND_REMOTE', 'OTHER-WX-'.$result['trade_state']);
        }
        return $result;
    }

    public function query_refund_single($la_paras, $account_id) {
        $vendor_wx_info = $this->get_account_info($account_id);
        if (empty($la_paras['ref_id']))
            throw new RttException('SYSTEM_ERROR', "Refund_id Missing");
	    $input = new \WxPayRefundQuery();
		$input->SetOut_refund_no($la_paras['ref_id']);
        $input->SetSub_mch_id($vendor_wx_info->sub_mch_id);
        Log::DEBUG("query_refund_single_wx:sending:" . json_encode($input->GetValues(), JSON_UNESCAPED_UNICODE));
		$result = \WxPayApi::refundQuery($input);
		Log::DEBUG("query_refund_single_wx:received:" . json_encode($result, JSON_UNESCAPED_UNICODE));
        checkErrToThrow($result);
		return $result;
	}

    public function vendor_txn_to_rtt_txn($vendor_txn, $account_id, $sc_selector='DEFAULT', $moreInfo=null,
        $is_refund=null)
    {
        if (is_null($is_refund))
            $is_refund = $sc_selector == 'FROM_REFUND';
        $ret = [
            'is_refund' => $is_refund,
            'account_id' => $account_id, //TODO check this with sub_mch_id ?
        ];
        $attr_map = $this->consts['TO_RTT_TXN'][$sc_selector];
        foreach($attr_map as $item) {
            if (empty($item[1])) {
                if ($item[2] instanceof Closure)
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
        $cacheID = "wx:exchange_rate:".$fee_type;
        $old = Cache::get($cacheID);
        if (empty($old)) {
            $vendor_wx_info = $this->get_account_info($account_id);
            $sub_mch_id = $vendor_wx_info->sub_mch_id;
		    $input = new \WxPayExchangeRateQuery();
		    $input->SetSub_mch_id($sub_mch_id);
		    $input->SetFee_type($fee_type);
            $dt = new \DateTime("now", new \DateTimeZone($this->consts['VENDOR_TZ']));
            $cached_hours = 24-$dt->format('H')+$this->consts['EXCHANGE_RATE_UPDATE_HOUR'];
            if ($dt->format('H')+0 < $this->consts['EXCHANGE_RATE_UPDATE_HOUR']) {
                $dt->add(\DateInterval::createFromDateString('-1 day'));
                $cached_hours -= 24;
            }
            Log::DEBUG(__FUNCTION__.":to cached hours:" . $cached_hours);
            $input->SetDate($dt->format('Ymd'));
            Log::DEBUG(__FUNCTION__.":sending:" . json_encode($input->GetValues(), JSON_UNESCAPED_UNICODE));
            $result = \WxPayApi::exchangerateQuery($input);
            Log::DEBUG(__FUNCTION__.":received:" . json_encode($result, JSON_UNESCAPED_UNICODE));
            //checkErrToThrow($result); //this return does not have a biz code
            if (!isset($result["return_code"]) || ($result["return_code"] != "SUCCESS"))
                throw new RttException('WX_ERROR_VALIDATION', $result["return_msg"]??"Error msg missing!");
            $release_time = \DateTime::createFromFormat("YmdH",
                $result['rate_time'].$this->consts['EXCHANGE_RATE_UPDATE_HOUR'],
                new \DateTimeZone($this->consts['VENDOR_TZ']));
            $result = [
                'exchange_rate'=>bcdiv($result['rate'], 10**8, 8),
                'release_time'=>$release_time->getTimestamp(),
            ];
            Cache::put($cacheID, $result, $cached_hours*60);
            $old = $result;
        }
        return $old;
    }
    public function set_vendor_channel($account_id, $values) {
        $values = array_intersect_key($values, array_flip(['sub_mch_id','rate','is_deleted']));
        if (!empty($values))
            DB::table('vendor_wx')->updateOrInsert(['account_id'=>$account_id],$values);
    }
    public function get_vendor_channel_config($account_id) {
        $res = DB::table('vendor_wx')->where('account_id','=',$account_id)->first();
        if (!empty($res)) {
            return array_intersect_key((array)$res, array_flip(['sub_mch_id','rate']));
        }
        return [];
    }
    private function download_bill(\DateTime $dt) {
        $input = new \WxPayDownloadBill();
        $input->SetBill_date($dt->format("Ymd"));
        $input->SetBill_type('ALL');
        $file = \WxPayApi::downloadBill($input);
        //echo $file;
        $ret = \WxPayApi::parseBill($file);
        return $ret;
    }
    private function calc_bill_sync_start() {
        //return new \DateTime("2018-01-09", new \DateTimeZone($this->consts['VENDOR_TZ']));
        $last_bill_time = DB::table('wx_raw_bills')->max('transaction_time');
        if (empty($last_bill_time)){
            return new \DateTime("2017-09-01", new \DateTimeZone($this->consts['VENDOR_TZ']));
        }
        $dt = new \DateTime("now", new \DateTimeZone($this->consts['VENDOR_TZ']));
        $dt->setTimestamp($last_bill_time);
        echo "last_bill_time:".$last_bill_time." str:".$dt->format('Y-m-d H:i:s')."\n";
        return $dt;
    }
    private function save_bill_to_db($recs) {
        $nException = 0;
        $duplicateAttr = [];
        array_walk($recs, function (&$bill) use($duplicateAttr) {
            $raw_bill = [];
            foreach($bill as $key=>$value) {
                $pos1 = strpos($key,"(");
                $pos2 = strrpos($key,")");
                if ($pos1 && $pos2 && $pos2>$pos1+1) {
                    $attr = substr($key, $pos1+1, $pos2-$pos1-1);
                }
                else {
                    $attr = str_replace(" ","_",$key);
                }
                $attr = delInvisibleChars(strtolower(trim($attr)));
                $attr = str_replace("'","",$attr);
                $attr = str_replace("\$","",$attr);
                if (isset($raw_bill[$attr])) {
                    $duplicateAttr[$attr] = 1;
                }
                else {
                    if ($attr == 'transaction_time') {
                        $dt = new \DateTime($value, new \DateTimeZone($this->consts['VENDOR_TZ'])); 
                        $value = $dt->getTimestamp();
                    }
                    elseif (in_array($attr, [
        'total_fee', 'coupon_amount', 'refund_fee', 'coupon_refund_amount', 'rate', 'cash_fee',
        'settlement_currency_amount', 'payers_refund_amount', 'refund_settlement_amount',]))
                    {
                        $value = round(floatval($value)*100);
                    }
                    elseif ($attr == 'fee') {
                        $value = round(floatval($value)*100000);
                    }
                    $raw_bill[$attr] = $value;
                }
            }
            $bill = $raw_bill;
        });
        if (!empty($duplicateAttr))
            Log::DEBUG('found duplicate attrs:'.(implode(",",array_keys($duplicateAttr))));
        try {
            DB::table('wx_raw_bills')->insert($recs);
        }
        catch(\PDOException $e) {
            if (23000 != $e->getCode()) throw $e;
            Log::DEBUG('found duplicate existing recs');
            foreach($recs as $rec) {
                DB::table('wx_raw_bills')
                    ->updateOrInsert(array_only($rec,['transaction_id','refund_id']), $rec);
            }
        }
        $str = count($recs) ." records saved/updated.";
        Log::DEBUG('save wx bill:'.$str);
    }
    public function sync_bill() {
        $curDt = new \DateTime("today", new \DateTimeZone($this->consts['VENDOR_TZ']));
        $iDate = $this->calc_bill_sync_start();
         do {
            Log::DEBUG("downloading wx bills for ".$iDate->format("Ymd"));
            list($recs, $sumData, $errors) = $this->download_bill($iDate);
            if ($errors && $errors['return_msg'] != "No Bill Exist") {
                Log::DEBUG("error:".$errors['return_msg']);
            }
            Log::DEBUG("Got ". count($recs). " bills from wx");
            if ($recs) {
                $this->save_bill_to_db($recs);
            }
            $iDate->modify("+1 day");
            if ($iDate < $curDt) sleep(1);
        }while($iDate < $curDt);
    }
    public function compare(int $start_time, int $end_time, $our_recs) {
        $whereConditions = [
            ['transaction_time','>=', $start_time],
            ['transaction_time','<',$end_time],
        ];
        /*
        if (!empty($id_contains))
            $whereConditions[] =['out_transaction_id','like', '%'.$id_contains.'%'];
         */
        $recs = DB::table('wx_raw_bills')->where($whereConditions)->get();
        Log::Debug("wx comparing ".count($recs)." (wx) <-> ".count($our_recs)." (our).time window:".
            $start_time."-".$end_time);
        $our_dict = [];
        foreach($our_recs as $our_rec) {
            $our_dict[$our_rec->ref_id] = 1;
        }
        $new_recs = [];
        foreach($recs as $rec) {
            $id = empty($rec->out_refund_no)? $rec->out_transaction_id : $rec->out_refund_no;
            if (empty($our_dict[$id])) {
                $new_recs[]= (array)$rec;
            }
            else {
                unset($our_dict[$id]);
            }
        }
        $not_found_recs = array_values($our_dict);
        Log::Debug("wx compare result:len(new)=".count($new_recs).".len(not_found)=".count($not_found_recs));
        $map = $this->get_mchid_aid_map();
        $unknown_mch_ids = [];
        $nDrop = 0;
        foreach($new_recs as $key=>$rec) {
            if (array_key_exists($rec['sub_mch_id'], $map)) {
                $is_refund = !empty($rec['out_refund_no']);
                $new_recs[$key] = $this->vendor_txn_to_rtt_txn($rec, $map[$rec['sub_mch_id']],
                    'FROM_DB_RAW_'.($is_refund?'REFUND':'CHARGE'), null, $is_refund);
            }
            else {
                $nDrop += 1;
                $unknown_mch_ids[$rec['sub_mch_id']] = 1;
                /*
                $is_refund = !empty($rec['out_refund_no']);
                $new_recs[$key] = $this->vendor_txn_to_rtt_txn($rec, -1,
                    'FROM_DB_RAW_'.($is_refund?'REFUND':'CHARGE'), null, $is_refund);
                 */
                unset($new_recs[$key]);
            }
        }
        Log::Debug('drop '.$nDrop.' records of unknown sub_mch_id:'.json_encode(array_keys($unknown_mch_ids)));
        return [$new_recs, $not_found_recs];
    }
    public function get_mchid_aid_map() {
        if (isset($this->data['mchid_aid_map']))
            return $this->data['mchid_aid_map'];
        $res = DB::table('account_base')
            ->leftJoin('vendor_wx', 'vendor_wx.account_id','=','account_base.account_id')
            ->select('account_base.account_id AS account_id','sub_mch_id')
            ->where(['account_base.is_deleted'=>0,'vendor_wx.is_deleted'=>0])
            ->get();
        $map = [];
        foreach($res as $pair) {
            if (!empty($pair->sub_mch_id)) {
                if (array_key_exists($pair->sub_mch_id, $map))
                    throw new RttException('SYSTEM_ERROR',
                        'multiple account share one wx sub_mch_id:'.$pair->sub_mch_id);
                $map[$pair->sub_mch_id] = $pair->account_id;
            }
        }
        $this->data['mchid_aid_map'] = $map;
        return $map;
    }
    public function handle_notify($request, $needSignOutput) {
        $notifyObj = new Notify($this);
        $notifyObj->Handle($needSignOutput);
    }
}

class Notify extends \WxPayNotify
{
    public $sp;
    public function __construct($sp) {
        $this->sp = $sp;
    }
	public function Queryorder($transaction_id, $sub_mch_id, &$result)
    {
		$input = new \WxPayOrderQuery();
		$input->SetTransaction_id($transaction_id);
        $input->SetSub_mch_id($sub_mch_id);
		$result = \WxPayApi::orderQuery($input);
		if(array_key_exists("return_code", $result)
			&& array_key_exists("result_code", $result)
			&& $result["return_code"] == "SUCCESS"
			&& $result["result_code"] == "SUCCESS")
		{
			return true;
		}
		return false;
	}
	public function NotifyProcess($data, &$msg)
    {
		Log::DEBUG("call back:" . json_encode($data, JSON_UNESCAPED_UNICODE));
		$notfiyOutput = array();
		
		if(!array_key_exists("transaction_id", $data) || !array_key_exists("sub_mch_id", $data)){
			$msg = "输入参数不正确";
			return false;
		}
		//查询订单，判断订单真实性
		if(!$this->Queryorder($data["transaction_id"], $data["sub_mch_id"], $result)){
			$msg = "订单查询失败";
			return false;
		}
        try {
            app()->make('rtt_service')->notify($this->sp,$data['out_trade_no'],$result);
        }
        catch(\Exception $e) {
            Log::DEBUG(__FUNCTION__.":parent process throws exception:".$e->getMessage());
        }
        return true;
	}
}
