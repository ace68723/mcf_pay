<?php
namespace App\Providers\RttService;

use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class RttService{

    public $consts;
    public function __construct()
    {
        $this->consts = array();
        $this->consts['OUT_TRADE_NO_PREFIX'] = "MCF";
        $this->consts['CHANNELS'] = array('WX'=>0x1, 'ALI'=>0x2,);

        $this->consts['REQUEST_PARAS'] = [
            ['key_name'=>'account_id'],
            ['key_name'=>'vendor_channel'],
            ['key_name'=>'total_fee_in_cent'],
            ['key_name'=>'total_fee_currency'],
            ['key_name'=>'scenario'],
            ['key_name'=>'description'],
            ['key_name'=>'timestamp'],
            ['key_name'=>'expire_time_sec'],
            ['key_name'=>'passback_data'],
            ['key_name'=>'extend_params'],
            ['key_name'=>'query_type'],
            ['key_name'=>'out_trade_no'],
            ['key_name'=>'vendor_txn_id'],
            ['key_name'=>'refund_id'],
            ['key_name'=>'refund_no'],
            ['key_name'=>'refund_fee_in_cent'],
            ['key_name'=>'refund_fee_currency'],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters
    }

    public function resolve_channel_sp($account_id, $channel) {
        if (empty($account_id))
            throw new \Exception("Invalid Account ID", 1);
        $res = DB::table('account_vendor')->where('account_id','=',$account_id)->first();
        if (!(($res->vendor_channel ?? 0) & ($this->consts["CHANNELS"][strtoupper($channel)] ?? 0)))
            throw new \Exception("Channel Not Activated", 1);
        $sp_name = strtolower($channel) ."_service";
        if (!app()->bound($sp_name))
            throw new \Exception("Channel Not Supported", 1);
        $sp = app()->make($sp_name);
        if (empty($sp))
            throw new \Exception("Channel Not Supported", 1);
        return $sp;
    }

    public function get_account_info($account_id) {
        return empty($account_id) ? null: 
            DB::table('account_base')->where('account_id','=',$account_id)->first();
    }

    public function parse_parameters(Request $request) {
        $la_res = array();
        $jsonObj = $request->json();
        foreach ($this->consts['REQUEST_PARAS'] as $attr) {
            if ($jsonObj->has($attr['key_name'])){
                $la_res[$attr['key_name']] = $jsonObj->get($attr['key_name']);
            }
        }
        Log::DEBUG("parsed:".json_encode($la_res));
        return $la_res;
    }

    public function generate_ref_id($la_paras, $account_ref_id, $type, $max_length=32) {
        // default max_length 32 is because wxpay's out trade no is of string(32)
        if ($type == 'ORDER') {
            $vendor_channel = $la_paras['vendor_channel'];
            if (empty($vendor_channel))
                throw new \Exception("generate_ref_id:Vendor_channel missing", 1);
            $vendor_channel = strtoupper(substr($vendor_channel, 0, 2));
            if (empty($account_ref_id))
                throw new \Exception("generate_ref_id:account_ref_id missing", 1);
            $account_ref_id = substr($account_ref_id, 0, 6);
            $ref_id = $this->consts['OUT_TRADE_NO_PREFIX'].$vendor_channel.
                $account_ref_id.date("YmdHis").bin2hex(random_bytes(2));
            if (strlen($ref_id) > $max_length)
                throw new \Exception("generate_ref_id: exceeds max_length", 1);
            return substr($ref_id, 0, $max_length);
        } elseif ($type == 'REFUND') {
            $ref_id = $la_paras['out_trade_no']."R".$la_paras['refund_no'];
            if (strlen($ref_id) > $max_length)
                throw new \Exception("generate_ref_id: exceeds max_length", 1);
            return substr($ref_id, 0, $max_length);
        }
        throw new \Exception("generate_ref_id:Unknown type:".$type, 1);
    }

    public function txn_to_front_end($rtt_txn) {
        $channel = $rtt_txn['vendor_channel']; 
        unset($rtt_txn['account_id']);
        $rtt_txn['vendor_channel'] = 'unknown';
        foreach ($this->consts['CHANNELS'] as $key=>$value) {
            if ($channel == $value) {
                $rtt_txn['vendor_channel'] = $key;
                break;
            }
        }
        return $rtt_txn;
    }

    public function cache_txn($txn){
        //DB::table('txn_base')->updateOrCreate(['ref_id'=>$txn['ref_id']],$txn); //TODO:use Eloquent
        $old = DB::table('txn_base')->where('ref_id','=',$txn['ref_id'])->first();
        if (empty($old)) {
            DB::table('txn_base')->insert($txn);
        }
        else {
            DB::table('txn_base')->where('ref_id','=',$txn['ref_id'])->update($txn);
        }
    }

}
