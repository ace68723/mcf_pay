<?php
namespace App\Providers\RttService;

use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;
use App\Exceptions\RttException;

class RttService{

    public $consts;
    public $data;
    public function __construct()
    {
        $this->consts = array();
        $this->consts['OUR_NAME'] = "MCF";
        $this->consts['CHANNELS'] = ['WX'=>0x1, 'ALI'=>0x2,'TC'=>0x8000];
        $this->consts['CHANNELS_REV'] = array_flip($this->consts['CHANNELS']);
        $this->consts['DEFAULT_PAGESIZE'] = 20;
        $this->sp_oc = app()->make('order_cache_service');
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
    public function set_vendor_channel($account_id, $channel, $values) {
        if (empty($this->consts['CHANNELS'][strtoupper($channel)]))
            throw new RttException('INVALID_PARAMETER', 'channel not exists');
        $mask = $this->consts["CHANNELS"][strtoupper($channel)] ?? 0;
        $channel = strtolower($channel);
        $sp = app()->make($channel.'_vendor_service');
        if ($channel == 'ali') {
            unset($values['sub_mch_name']);
            unset($values['sub_mch_id']);
            $mch_info = $this->get_merchant_info_by_id($account_id);
            if (!empty($mch_info['merchant_id']) && !empty($mch_info['ref_id'])) {
                $values['sub_mch_name'] = $mch_info['merchant_id'];
                $values['sub_mch_id'] = $mch_info['ref_id'];
            }
        }
        $sp->set_vendor_channel($account_id, $values);
        $is_deleted = $values['is_deleted'] ?? false;
        $res = $this->get_vendor_channel_info($account_id);
        if ($is_deleted) {
            if (($res->vendor_channel ?? 0) & $mask) {
                DB::table('account_vendor')
                    ->where('account_id','=',$account_id)
                    ->update(['vendor_channel'=>$res->vendor_channel ^ $mask]);
            }
        }
        else {
            if (!(($res->vendor_channel ?? 0) & $mask)) {
                DB::table('account_vendor')
                    ->updateOrInsert(['account_id'=>$account_id],
                        ['vendor_channel'=>($res->vendor_channel??0) | $mask]);
            }
        }
    }

    public function resolve_channel_sp($account_id, $channel) {
        $res = $this->get_vendor_channel_info($account_id);
        if (!(($res->vendor_channel ?? 0) & ($this->consts["CHANNELS"][strtoupper($channel)] ?? 0)))
            throw new RttException('CHANNEL_NOT_ACTIVATED', ['account_id'=>$account_id, 'channel'=>$channel]);
        /*
        if (!$b_only_channel_name)
            return strtolower($channel);
         */
        $sp_name = strtolower($channel) ."_vendor_service";
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

    public function precreate_authpay($la_paras, $account_id) {
        $infoObj = $this->get_account_info($account_id);
        if (!empty($infoObj->currency_type) && $infoObj->currency_type != $la_paras['total_fee_currency'])
            throw new RttException("INVALID_PARAMETER", "currency_type");
        $la_paras['_out_trade_no'] = $this->generate_txn_ref_id($la_paras, $infoObj->ref_id, 'ORDER');
        if (!($this->sp_oc->cb_new_order($la_paras['_out_trade_no'], $account_id, 
            $la_paras['vendor_channel'], $la_paras)))
            throw new RttException("SYSTEM_ERROR", "may be caused by duplicate _out_trade_no");
        return ['out_trade_no'=>$la_paras['_out_trade_no'],];
    }

    public function create_authpay($new_la_paras, $account_id) {
        if ('INIT' != $this->sp_oc->query_order_cache_field($new_la_paras['out_trade_no'], 'status'))
            throw new RttException("INVALID_PARAMETER", "out_trade_no");
        $la_paras = $this->sp_oc->query_order_cache_field($new_la_paras['out_trade_no'], 'input');
        if (empty($la_paras))
            throw new RttException("INVALID_PARAMETER", "out_trade_no");
        if (($la_paras['_out_trade_no']??null) != $new_la_paras['out_trade_no'])
            throw new RttException("INVALID_PARAMETER", "out_trade_no"); //note that $la_paras['_out_trade_no'] is the real effecting parameter
        foreach (['scenario', 'total_fee_in_cent', 'total_fee_currency', 'vendor_channel'] as $pa_name) {
            if (($la_paras[$pa_name]??null) != $new_la_paras[$pa_name])
                throw new RttException("INVALID_PARAMETER", $pa_name);
        }
        $la_paras['auth_code'] = $new_la_paras['auth_code'];
        $sp = $this->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
        $ret = $sp->create_authpay($la_paras, $account_id,
            (function(...$args) {return true;}), [$this->sp_oc,'cb_order_update']);
        //null cb_new_order do not memorize auth_code
        return $ret;
    }

    public function create_order($la_paras, $account_id){
        $infoObj = $this->get_account_info($account_id);
        if (!empty($infoObj->currency_type) && $infoObj->currency_type != $la_paras['total_fee_currency'])
            throw new RttException("INVALID_PARAMETER", "currency_type");
        $la_paras['_out_trade_no'] = $this->generate_txn_ref_id($la_paras, $infoObj->ref_id, 'ORDER');
        $sp = $this->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
        $ret = $sp->create_order($la_paras, $account_id,
            [$this->sp_oc,'cb_new_order'], [$this->sp_oc,'cb_order_update']);
        return $ret;
    }

    public function create_refund($la_paras, $account_id){
        $la_paras['_refund_id'] = $this->generate_txn_ref_id($la_paras, null, 'REFUND');
        $old_account_id = $this->sp_oc->query_order_cache_field($la_paras['out_trade_no'], 'account_id');
        if ($old_account_id != $account_id) {
            throw new RttException('PERMISSION_DENIED');
        }
        $sp = $this->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
        $status = $this->sp_oc->query_order_cache_field($la_paras['_refund_id'], 'status');
        if (!empty($status) && $status == 'SUCCESS') {
            //prevent repeating a successful refund
            $txn = $this->sp_oc->query_order_cache_field($la_paras['_refund_id'], 'resp');
            $txn['is_repeated'] = 1;
            return $txn;
        }
        $ret = $sp->create_refund($la_paras, $account_id,
            [$this->sp_oc,'cb_new_order'], [$this->sp_oc,'cb_order_update']);
        $ret = $sp->vendor_txn_to_rtt_txn($ret, $account_id, 'FROM_REFUND', $la_paras);
        $ret['username'] = $la_paras['_username'] ?? null;
        return $ret;
    }

    public function check_refund_status($la_paras, $account_id){
        $status = $this->sp_oc->query_order_cache_field($la_paras['refund_id'], 'status');
        if (empty($status))
            throw new RttException('NOT_FOUND', ["REFUND",$la_paras['refund_id']]);
        if ($la_paras['type'] == 'refresh' && $status != 'SUCCESS'
            && strtolower($la_paras['vendor_channel'])=='wx') {
            //TODO: under construction...
            $sp = $this->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
            $vendor_txn = $sp->query_refund_single($la_paras, $account_id);
            //only success query gets here
            $cached_input = $this->sp_oc->query_order_cache_field($la_paras['refund_id'], 'input');
            $txn = $sp->vendor_txn_to_rtt_txn($vendor_txn, $account_id, 'FROM_REFUND', $cached_input);
            $status = $txn['status'];//TODO ensure the state map in wx/ali service consists with rtt config
            if (!$this->is_defined_status($status)) {
                Log::INFO('regard undefined status '. $status . ' as FAIL');
                $status = 'FAIL';
            }
            $this->sp_oc->cb_order_update($la_paras['ref_id'], $status, $txn);
        }
        return $status;
    }
    private function check_order_status_remote($la_paras, $account_id, $sp) {
        $vendor_txn = $sp->query_charge_single($la_paras, $account_id);
        //only success query gets here, TODO:we need to make sure this in the implementation of vendor sp
        $cached_input = $this->sp_oc->query_order_cache_field($la_paras['out_trade_no'], 'input');
        $txn = $sp->vendor_txn_to_rtt_txn($vendor_txn, $account_id, 'DEFAULT', $cached_input);
        $status = $txn['status'];//TODO ensure the state map in wx/ali service consists with rtt config
        if (!$this->is_defined_status($status)) {
            Log::INFO('regard undefined status '. $status . ' as FAIL');
            $status = 'FAIL';
        }
        $this->sp_oc->cb_order_update($la_paras['out_trade_no'], $status, $txn);
        return $status;
    }
    public function check_order_status($la_paras, $account_id){
        /*
        if ($la_paras['type'] == 'remote') {
            $sp = $this->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
            $status = $this->check_order_status_remote($la_paras, $account_id, $sp);
            return $status;
        }
         */
        $status = $this->sp_oc->query_order_cache_field($la_paras['out_trade_no'], 'status');
        if (empty($status))
            throw new RttException('NOT_FOUND', ["ORDER",$la_paras['out_trade_no']]);
        $sp = $this->resolve_channel_sp($account_id, $la_paras['vendor_channel']);
        if ($la_paras['type'] == 'pending') {
            for ($i=0; $i<30 && $status == 'WAIT'; $i++) {
                sleep(1);
                $status = $this->sp_oc->query_order_cache_field($la_paras['out_trade_no'], 'status');
            }
            for ($j=0; $j<3; $j++) {
                if ($status != 'WAIT') break;
                try {
                    $status = $this->check_order_status_remote($la_paras, $account_id, $sp);
                }catch(RttException $e) {
                    if ($e->getInnerCode() == RttException::NOT_FOUND_REMOTE) {
                        $status = 'WAIT';
                    }
                    else {
                        throw $e;
                    }
                }
                for ($i=0; $i<30 && $status == 'WAIT'; $i++) {
                    sleep(1);
                    $status = $this->sp_oc->query_order_cache_field($la_paras['out_trade_no'], 'status');
                }
            }
        }
        if (($la_paras['type'] == 'pending' && $status == 'WAIT') ||
            ($la_paras['type'] == 'refresh' && $status != 'SUCCESS') ||
            $la_paras['type'] == 'remote')
        {
            $status = $this->check_order_status_remote($la_paras, $account_id, $sp);
        }
        $ret = ['status'=>$status,];
        if ($status == 'SUCCESS') {
            $txn = $this->sp_oc->query_order_cache_field($la_paras['out_trade_no'], 'resp');
            $this->txn_to_export($txn);
            $ret = array_merge($ret, $txn);
        }
        return $ret;
    }

    public function get_hot_txns($la_paras, $account_id) {
        $page_num = $la_paras['page_num'] ?? 1;
        $page_size = $la_paras['page_size'];
        return $this->sp_oc->get_hot_txns($account_id, $page_num, $page_size);
    }
    public function get_txn_by_id($la_paras, $account_id) {
        $id = $la_paras['ref_id'];
        $status = $this->sp_oc->query_order_cache_field($id, 'status');
        if (!empty($status) && $status != 'SUCCESS')
            throw new RttException('NOT_FOUND','TRANSACTION NOT EXIST');
        if (!empty($status)) {
            $txn = $this->sp_oc->query_order_cache_field($id, 'resp');
        }
        else {
            $txn = DB::table('txn_base')
                ->select('txn_base.*','mcf_user_base.username')
                ->leftJoin('mcf_user_base', 'txn_base.user_id','=','mcf_user_base.uid')
                ->where("ref_id",$id)->first();
            if (empty($txn))
                throw new RttException('NOT_FOUND','TRANSACTION NOT EXIST');
            $txn = (array) $txn;
        }
        if ($txn['account_id'] != $account_id) {
            throw new RttException('PERMISSION_DENIED');
        }
        return $txn;
    }
    public function query_txns_by_time($la_paras, $account_id){
        $where_cond = [
            ['txn_base.account_id', '=', $account_id],
        ];
        if ($la_paras['start_time'] >= 0)
            $where_cond[] = ['vendor_txn_time', '>=', $la_paras['start_time']];
        if ($la_paras['end_time'] >= 0)
            $where_cond[] = ['vendor_txn_time', '<', $la_paras['end_time']];
        $count = DB::table('txn_base')->where($where_cond)->count();
        $page_size =$la_paras['page_size']??$this->consts['DEFAULT_PAGESIZE']; 
        $page_num = $la_paras['page_num']??1;
        $offset = ($page_num-1)*$page_size;
        $result = DB::table('txn_base')
            ->select('txn_base.*','mcf_user_base.username')
            ->leftJoin('mcf_user_base', 'txn_base.user_id','=','mcf_user_base.uid')
            ->where($where_cond)
            ->orderBy('vendor_txn_time','DESC')
            ->offset($offset)
            ->limit($page_size)
            ->get();
        return ['total_page'=>ceil($count/$page_size),
            'total_count'=>$count,
            'page_num'=>$page_num,
            'page_size'=>$page_size,
            'recs'=>$result->toArray()];
    }

    public function txn_to_export(&$txn) {
        if (!is_array($txn)) {
            $txn = (array)$txn;
        }
        $username = $txn['username']??null;
        $mchinfo = empty($txn['account_id']) ? null : $this->get_merchant_info_by_id($txn['account_id']);
        $exchange_rate = $txn['exchange_rate']??number_format($txn['paid_fee_in_cent']/$txn['txn_fee_in_cent'],7,'.','');
        $dt = new \DateTime('now', $this->create_datetimezone($mchinfo['timezone']??null));
        $dt->setTimestamp($txn['vendor_txn_time']);
        $new_txn = [
            //'time'=> $dt->format('Y-m-d H:i:sP'),
            'time'=> $dt->format('Y-m-d H:i:s'),
            'ref_id'=>$txn['ref_id'],
            'is_refund'=>$txn['is_refund'],
            'amount_in_cent'=>$txn['txn_fee_in_cent'],
            'amount_currency'=>$txn['txn_fee_currency'],
            'paid_fee_in_cent'=>$txn['paid_fee_in_cent'],
            'paid_fee_currency'=>$txn['paid_fee_currency'],
            'exchange_rate'=>$exchange_rate,
            'vendor_channel'=>$this->consts['CHANNELS_REV'][$txn['vendor_channel']]??null,
            'username'=>$username,
            'merchant_name'=>$mchinfo['display_name']??null,
            'cell'=>$mchinfo['cell']??null,
            'address'=>$mchinfo['address']??null,
        ];
        if (!empty($txn['is_repeated'])) {
            $new_txn['is_repeated'] = $txn['is_repeated'];
        }
        $txn = $new_txn;
    }

    public function get_merchant_info_by_id($account_id) {
        if (isset($this->data['mchNameMap'][$account_id])) {
            return $this->data['mchNameMap'][$account_id];
        }//TODO use cache but remember to reload it when modified
        Log::DEBUG('query mch name for account:'.$account_id);
        $value = DB::table('account_base')
            ->select(['display_name','cell','address','timezone','merchant_id','ref_id'])
            ->leftJoin('company_info', 'account_base.account_id','=','company_info.account_id')
            ->where('account_base.account_id',$account_id)
            ->first();
        if (empty($value))
            return null;
        $value = (array)$value;
        $this->data['mchNameMap'][$account_id] = $value;
        return $value;
    }
    public function get_company_info($account_id) {
        $ret = DB::table('company_info')
            ->select('company_info.*', 'account_contract.tip_mode')
            ->leftJoin('account_contract', 'account_contract.account_id','=','company_info.account_id')
            ->where('company_info.account_id','=', $account_id)->first();
        if (empty($ret))
            throw new RttException('SYSTEM_ERROR', 'company_info not found');
        return $ret;
    }

    public function is_defined_status($status) {
        return $this->sp_oc->is_defined_status($status);
    }

    public function check_device_id($account_id, $device_id) {
        if (empty($device_id))
            throw new RttException('INVALID_PARAMETER', 'device_id');
        if ($device_id == 'FROM_WEB') return true;
        $key = 'deviceSet:'.$account_id;
        if (!Redis::EXISTS($key)) {
            $this->update_device($account_id);
        }
        if (!Redis::SISMEMBER($key, $device_id))
            throw new RttException('INVALID_PARAMETER', 'device_id');
        return true;
    }
    public function update_device($account_id) {
        Log::DEBUG(__FUNCTION__.":".$account_id);
        $key = 'deviceSet:'.$account_id;
        if (Redis::EXISTS($key)) Redis::DEL($key);
        $devices = DB::table('device')->select('device_id')
            ->where('account_id','=',$account_id)
            ->where('is_deleted','=',0)
            ->get()->toArray();
        if (!empty($devices))
            Redis::SADD($key, ...(array_map(function($x){return $x->device_id;},$devices)));
    }
    public function compare($channel, int $start_time, int $end_time) {
        if ($end_time - $start_time > 180*24*60*60)
            throw new RttException('SYSTEM_ERROR', "TimeWindow Too Long:".$start_time."-".$end_time);
        $sp = app()->make($channel.'_vendor_service');
        $whereConditions = [
            ['vendor_txn_time','>=', $start_time],
            ['vendor_txn_time','<',$end_time],
            ['vendor_channel','=', $this->consts['CHANNELS'][strtoupper($channel)]],
        ];
        $recs = DB::table('txn_base')->where($whereConditions)->get();
        list($new_recs,$not_found_recs) = $sp->compare($start_time, $end_time, $recs->toArray());
        if (count($not_found_recs)>0)
            Log::ALERT('compare result: not found:'.json_encode($not_found_recs));
        if (count($new_recs) == 0) return;
        Log::DEBUG('compare result: new:'.json_encode(array_pluck($new_recs,'ref_id')));
        try {
            DB::table('txn_base')->insert($new_recs);
        }
        catch (\PDOException $e) {
            if (23000 != $e->getCode()) throw $e;
            echo $e->getMessage();
            throw new RttException('SYSTEM_ERROR',
                'insert violate unique constraint. Maybe caused by refund txn time diff. Try another TimeWindow');
        }
        Log::DEBUG("compare result: inserted ".count($new_recs)." records");
    }

    private function create_datetimezone($tzStr) {
        try{
            $tz = new \DateTimeZone($tzStr);
        }
        catch(\Exception $e) {
            $tz = new \DateTimeZone('Asia/Shanghai');
        }
        return $tz;
    }

    public function get_today_summary($account_id) {
        $mchinfo = $this->get_merchant_info_by_id($account_id);
        $dt = new \DateTime('today 3:00', $this->create_datetimezone($mchinfo['timezone']??null));
        $now = time();
        $start_time = $dt->getTimestamp();
        if ($now < $start_time) {
            $dt = new \DateTime('yesterday 3:00', $this->create_datetimezone($mchinfo['timezone']??null));
            $start_time = $dt->getTimestamp();
        }
        $data = $this->sp_oc->query_txns_by_time($account_id, $start_time, $now, 1, -1);
        $dict = [];
        foreach($data['recs'] as $txn) {
            $dict[$txn['ref_id']] = $txn;
        }
        $total = $refund = $tips = 0;
        foreach($data['recs'] as $txn) {
            if (!$txn['is_refund']) {
                $total += $txn['txn_fee_in_cent'];
                $tips += $txn['tips'] ?? 0;
            }
            else {
                $refund += $txn['txn_fee_in_cent'];
                $out_trade_no = $txn['txn_link_id'] ?? null;
                if (is_null($out_trade_no) && substr($txn['ref_id'],-2)=='R1') {
                    $out_trade_no = substr($txn['ref_id'], 0, -2);
                    Log::debug(__FUNCTION__.' utilizing generate_txn_ref_id rule. ref_id:'
                        .$txn['ref_id'].' out_trade_no:'.$out_trade_no);
                }
                $tips -= $dict[$out_trade_no]['tips'] ?? 0;
            }
        }
        $amount_due = $total - $refund;
        return [
            'start_time'=>$dt->format('Y-m-d H:i:s'),
            'total'=>$total/100,
            'count'=>count($data['recs']),
            'refund'=>$refund/100,
            'purchase'=>($amount_due-$tips)/100,
            'tips'=>$tips/100,
            'amount_due'=>$amount_due/100,
        ];
    }

    public function notify($sp, $out_trade_no, $vendor_txn) {
        Log::DEBUG(__FUNCTION__. ":".$out_trade_no);
        $status = $this->sp_oc->query_order_cache_field($out_trade_no, 'status');
        if (empty($status) || !in_array($status,['INIT','WAIT'])) return;
        $account_id = $this->sp_oc->query_order_cache_field($out_trade_no, 'account_id');
        if (is_null($account_id)) return;
        $cached_input = $this->sp_oc->query_order_cache_field($out_trade_no, 'input');
        $txn = $sp->vendor_txn_to_rtt_txn($vendor_txn, $account_id, 'FROM_NOTIFY', $cached_input);
        $this->sp_oc->cb_order_update($out_trade_no, 'SUCCESS', $txn);
    }

}
