<?php
namespace App\Providers\WxService;

require_once __DIR__."/lib/WxPay.Api.php";
require_once __DIR__.'/lib/WxPay.Notify.php';

use Log;
//use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

function checkErrToThrow($result)
{
    if (!isset($result["return_code"]) || ($result["return_code"] != "SUCCESS"))
        throw new \Exception($result["return_msg"]??"Error msg missing!", 2);
    if (!isset($result["result_code"]) || ($result["result_code"] != "SUCCESS"))
        throw new \Exception($result["err_code"]??"Error msg missing!", 3);
}

class WxService
{

    public $consts;
    public function __construct(){
        $this->consts = array();
        $this->consts['GATEWAY_ADDR'] = "";
        $this->consts['VENDOR_TZ'] = "Asia/Shanghai";
        //$this->consts['CHANNEL_NAME'] = "WX"; 
        $this->consts['CHANNEL_FLAG'] = app()->make('rtt_service')->consts['CHANNELS']['WX'];
        //$this->consts['DEFAULT_CURRENCY'] = "CAD";
        //$this->consts['NOTIFY_URL'] = "http://paysdk.weixin.qq.com/example/notify.php";
        $this->consts['NOTIFY_URL'] = "http://www.rttpay.com/index.php/api/v1/test";
        $this->consts['DEFAULT_EXPIRE_SEC'] = 3600;
        $this->consts['SCENARIO_MAP'] = [
            'NATIVE'=>'NATIVE',
        ];
        $this->consts['STATE_MAP'] = array( //vendor to rtt 
            'REFUND'=>'REFUND',
            'SUCCESS'=>'SUCCESS',
        );
    }
    private function get_account_info($account_id, $b_emptyAsException = true){
        $ret = empty($account_id) ? null: 
            DB::table('vendor_wx')->where('account_id','=',$account_id)->first();
        if ($b_emptyAsException && empty($ret)) {
            throw new \Exception("Missing Vendor Wx Entry", 1);
        }
        return $ret;
    }

    public function create_order($la_paras){
        $vendor_wx_info = $this->get_account_info($la_paras['account_id']??null);
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
            throw  new \Exception("WRONG SCENARIO!", 1);
        $input->SetTrade_type("NATIVE");
        $input->SetProduct_id(date("YmdHis"));
        Log::info("Send to WxPay server:".json_encode($input->getValues()));
		$result = \WxPayApi::unifiedOrder($input, 10);
        Log::info("Received from WxPay server:".json_encode($result));
        checkErrToThrow($result);
        return array("out_trade_no"=>$la_paras['_out_trade_no'], "code_url"=>$result["code_url"]);
    }

    public function query_charge_single($la_paras) {
        $vendor_wx_info = $this->get_account_info($la_paras['account_id']??null);
        if (empty($la_paras['out_trade_no']))
            throw new \Exception("Out_trade_no Missing", 1);
		$input = new \WxPayOrderQuery();
		$input->SetOut_trade_no($la_paras['out_trade_no']);
        $input->SetSub_mch_id($vendor_wx_info->sub_mch_id);
        Log::DEBUG("query_txn_single_wx:sending:" . json_encode($input->GetValues()));
		$result = \WxPayApi::orderQuery($input);
		Log::DEBUG("query_txn_single_wx:received:" . json_encode($result));
        checkErrToThrow($result);
		return $result;
	}

    public function query_refund_single($la_paras) {
        $vendor_wx_info = $this->get_account_info($la_paras['account_id']??null);
        if (empty($la_paras['refund_id']))
            throw new \Exception("Refund_id Missing", 1);
	    $input = new \WxPayRefundQuery();
		$input->SetOut_refund_no($la_paras['refund_id']);
        $input->SetSub_mch_id($vendor_wx_info->sub_mch_id);
        Log::DEBUG("query_refund_single_wx:sending:" . json_encode($input->GetValues()));
		$result = \WxPayApi::refundQuery($input);
		Log::DEBUG("query_refund_single_wx:received:" . json_encode($result));
        checkErrToThrow($result);
		return $result;
	}

    public function create_refund($la_paras) {
        $vendor_wx_info = $this->get_account_info($la_paras['account_id']??null);
        if (empty($la_paras['out_trade_no']))
            throw new \Exception("Out_trade_no Missing", 1);
        if (!empty($la_paras['total_fee_currency']) 
            && $la_paras['refund_fee_currency'] != $la_paras['total_fee_currency'])
            throw new \Exception("Refund Currency must match!", 1);
        $input = new \WxPayRefund();
        //$input->SetTransaction_id($la_paras['wx_txn_id']);
        $input->SetOut_trade_no($la_paras['out_trade_no']);
        $input->SetTotal_fee($la_paras['total_fee_in_cent']);
        $input->SetRefund_fee($la_paras['refund_fee_in_cent']);
        $input->SetRefund_fee_type($la_paras['refund_fee_currency']);
        $input->SetOut_refund_no($la_paras['_refund_id']);
        $input->SetOp_user_id(\WxPayConfig::MCHID);
        $input->SetSub_mch_id($vendor_wx_info->sub_mch_id);
        Log::DEBUG("create_refund_wx:sending:" . json_encode($input->GetValues()));
		$result = \WxPayApi::refund($input);
		Log::DEBUG("create_refund_wx:received:" . json_encode($result));
        return $result;
        checkErrToThrow($result);
		return $result;
	}

    public function vendor_txn_to_rtt_txn($wx_txn, $side_info) {
        $ret = array();
        $attr_map = [
            ['ref_id', 'out_trade_no'], 
            ['is_refund', null, false],
            ['account_id', null, $side_info['account_id']], //TODO check this with sub_mch_id
            ['vendor_channel', null, $this->consts['CHANNEL_FLAG']],
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
            ['exchange_rate','rate', function ($rate) {
                return bcdiv($rate, 10**8, 8);
            }],
            ['customer_id', 'openid'],
            ['status', 'trade_state', function($state) {
                return $this->consts['STATE_MAP'][$state] ?? "OTHER-WX-".$state;
            }],
        ];
        foreach($attr_map as $item) {
            if (empty($item[1])) {
                $ret[$item[0]] = $item[2];
                continue;
            } elseif (empty($item[2])) {
                $ret[$item[0]] = $wx_txn[$item[1]] ?? null;
            } else {
                $ret[$item[0]] = $item[2]($wx_txn[$item[1]] ?? null);
            }
        }
        return $ret;
    }

    public function handle_notify($needSignOutput) {
        $notifyObj = new Notify();
        $notifyObj->Handle($needSignOutput);
    }
}

class Notify extends \WxPayNotify
{
	public function Queryorder($transaction_id, $sub_mch_id)
    {
		$input = new \WxPayOrderQuery();
		$input->SetTransaction_id($transaction_id);
        $input->SetSub_mch_id($sub_mch_id);
		$result = \WxPayApi::orderQuery($input);
		Log::DEBUG("query:" . json_encode($result));
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
		Log::DEBUG("call back:" . json_encode($data));
		$notfiyOutput = array();
		
		if(!array_key_exists("transaction_id", $data) || !array_key_exists("sub_mch_id", $data)){
			$msg = "输入参数不正确";
			return false;
		}
		//查询订单，判断订单真实性
		if(!$this->Queryorder($data["transaction_id"], $data["sub_mch_id"])){
			$msg = "订单查询失败";
			return false;
		}
		return true;
	}
}
