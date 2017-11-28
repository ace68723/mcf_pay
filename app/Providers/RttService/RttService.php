<?php
namespace App\Providers\RttService;

use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Exceptions\RttException;

class RttService{

    public $consts;
    public function __construct()
    {
        $this->consts = array();
        $this->consts['OUR_NAME'] = "MCF";
        $this->consts['CHANNELS'] = array('WX'=>0x1, 'ALI'=>0x2,);

    }

    public function get_vendor_channel_info($account_id, $b_readable=false) {
        if (empty($account_id))
            throw new RttException('SYSTEM_ERROR', "Invalid Account ID");
        $res = DB::table('account_vendor')->where('account_id','=',$account_id)->first();
        if ($b_readable) {
            $channels = array();
            $bitInd = $res->vendor_channel ?? 0;
            foreach($this->consts['CHANNELS'] as $key=>$value) {
                if ($value & $bitInd)
                    $channels[] = $key;
            }
            return $channels;
        }
        return $res;
    }

    public function resolve_channel_sp($account_id, $channel) {
        $res = $this->get_vendor_channel_info($account_id);
        if (!(($res->vendor_channel ?? 0) & ($this->consts["CHANNELS"][strtoupper($channel)] ?? 0)))
            throw new RttException('CHANNEL_NOT_ACTIVATED', ['account_id'=>$account_id, 'channel'=>$channel]);
        $sp_name = strtolower($channel) ."_service";
        if (!app()->bound($sp_name))
            throw new RttException('CHANNEL_NOT_SUPPORTED', ['channel'=>$channel]);
        $sp = app()->make($sp_name);
        if (empty($sp))
            throw new RttException('CHANNEL_NOT_SUPPORTED', ['channel'=>$channel]);
        return $sp;
    }

    public function get_account_info($account_id) {
        return empty($account_id) ? null: 
            DB::table('account_base')->where('account_id','=',$account_id)->first();
    }

    public function generate_txn_ref_id($la_paras, $account_ref_id, $type, $max_length=32) {
        // default max_length 32 is because wxpay's out trade no is of string(32)
        if ($type == 'ORDER') {
            $vendor_channel = $la_paras['vendor_channel'];
            if (empty($vendor_channel))
                throw new RttException('SYSTEM_ERROR', __FUNCTION__.":Vendor_channel missing");
            $vendor_channel = strtoupper(substr($vendor_channel, 0, 2));
            if (empty($account_ref_id))
                throw new RttException('SYSTEM_ERROR', __FUNCTION__.": account_ref_id missing");
            $account_ref_id = substr($account_ref_id, 0, 6);
            $ref_id = $this->consts['OUR_NAME'].$vendor_channel.
                $account_ref_id.date("YmdHis").bin2hex(random_bytes(2));
            if (strlen($ref_id) > $max_length)
                throw new RttException('SYSTEM_ERROR', __FUNCTION__.": exceeds max_length");
            return substr($ref_id, 0, $max_length);
        } elseif ($type == 'REFUND') {
            $ref_id = $la_paras['out_trade_no']."R".$la_paras['refund_no'];
            if (strlen($ref_id) > $max_length)
                throw new RttException('SYSTEM_ERROR', __FUNCTION__.": exceeds max_length");
            return substr($ref_id, 0, $max_length);
        }
        throw new RttException('SYSTEM_ERROR', __FUNCTION__.":Unknown type:".$type);
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

    public function download_bills($start_date, $end_date) {
        Log::DEBUG("in ".__FUNCTION__);
    }

}
