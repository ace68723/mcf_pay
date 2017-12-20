<?php
namespace App\Providers\MgtService;

use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Exceptions\RttException;

class MgtService{

    public $consts;
    public function __construct() {
        $this->consts = array();
        $this->consts['TABS_LIST'] = [
                'basic','contract','channel','device','user',
        ];
    }

    public function get_merchants($la_paras) {
        $page_num = $la_paras['page_num'];
        $page_size = $la_paras['page_size'];
        $where = ['role'=>666, 'mcf_user_base.is_deleted'=>0];
        $count = DB::table('mcf_user_base')->where($where)->count();
        $merchants = DB::table('mcf_user_base')
            ->where($where)
            ->leftJoin('company_info', 'mcf_user_base.account_id','=','company_info.account_id')
            ->select(['merchant_id', 'mcf_user_base.account_id AS account_id', 'display_name',
                    'legal_name', 'cell'])
            ->offset(($page_num-1)*$page_size)->limit($page_size)
            ->get();
        return ['total_page'=>ceil($count/$page_size),
            'total_count'=>$count,
            'page_num'=>$page_num,
            'page_size'=>$page_size,
            'recs'=>$merchants->toArray()];
    }
    public function get_merchant_info_basic($la_paras) {
        $where = ['account_id'=>$la_paras['account_id'], 'is_deleted'=>0];
        $results = DB::table('company_info')
            ->where($where)->first();
        return (array)$results;
    }
    public function set_merchant_basic($la_paras) {
        $where = ['account_id'=>$la_paras['account_id']];
        $is_success = DB::table('company_info')->updateOrInsert($where,$la_paras);
        return $is_success;
    }
    public function get_merchant_info_contract($la_paras) {
        //$where = ['account_id'=>$la_paras['account_id'], 'is_deleted'=>0];
        $where = ['account_id'=>$la_paras['account_id']];
        $results = DB::table('account_contract')
            ->where($where)->first();
        return (array)$results;
    }
    public function set_merchant_contract($la_paras) {
        $where = ['account_id'=>$la_paras['account_id']];
        $is_success = DB::table('account_contract')->updateOrInsert($where,$la_paras);
        return $is_success;
    }
    public function get_merchant_info_device($la_paras) {
        $page_num = $la_paras['page_num'];
        $page_size = $la_paras['page_size'];
        $cols = ['device_id','account_id','is_deleted'];
        $where = ['account_id'=>$la_paras['account_id'], 'is_deleted'=>0];
        $count = DB::table('device')->where($where)->count();
        $results = DB::table('device')
            ->select($cols)
            ->where($where)
            ->offset(($page_num-1)*$page_size)->limit($page_size)
            ->get();
        return ['total_page'=>ceil($count/$page_size),
            'total_count'=>$count,
            'page_num'=>$page_num,
            'page_size'=>$page_size,
            'recs'=>$results->toArray()];
    }
    public function set_merchant_device($la_paras) {
        $where = ['account_id'=>$la_paras['account_id']];
        $cols = ['account_id','device_id','is_deleted'];
        $values = array_intersect_key($la_paras, array_flip($cols));
        $old_item = DB::table('device')->select('account_id')->where('device_id',$values['device_id'])->first();
        $old_account = $old_item->account_id ?? null;
        $is_success = DB::table('device')
            ->updateOrInsert(['device_id'=>$values['device_id']], $values);
        if ($is_success) {
            $sp = app()->make('rtt_service');
            $account_id = $values['account_id'];
            $sp->update_device($account_id);
            if (!empty($old_account) && $old_account != $account_id)
                $sp->update_device($old_account);
        }
        return $is_success;
    }
    public function get_merchant_info_channel($la_paras) {
        $sp_rtt = app()->make('rtt_service');
        $account_id = $la_paras['account_id'];
        $channels = $sp_rtt->get_vendor_channel_info($account_id, true);
        $results = [];
        foreach($channels as $channel) {
            $channel = strtolower($channel);
            $sp = $sp_rtt->resolve_channel_sp($account_id, $channel);
            $results[$channel] = $sp->get_vendor_channel_config($account_id);
        }
        return $results;
    }
    public function set_merchant_channel($la_paras) {
        $cols = ['account_id', 'channel', 'sub_mch_id', 'sub_mch_name', 'sub_mch_industry', 'is_deleted', 'rate'];
        $values = array_intersect_key($la_paras, array_flip($cols));
        $account_id = $la_paras['account_id'];
        $channel = strtolower($la_paras['channel']);
        $sp_rtt = app()->make('rtt_service');
        $sp_rtt->set_vendor_channel($account_id, $channel, $values);
        return true;
    }
    public function get_merchant_info_user($la_paras) {
        $page_num = $la_paras['page_num'];
        $page_size = $la_paras['page_size'];
        $where = ['account_id'=>$la_paras['account_id'], 'is_deleted'=>0];
        $select = ['merchant_id','username','account_id','role'];
        $count = DB::table('mcf_user_base')->where($where)->count();
        $results = DB::table('mcf_user_base')
            ->select($select)
            ->where($where)
            ->offset(($page_num-1)*$page_size)->limit($page_size)
            ->get();
        return ['total_page'=>ceil($count/$page_size),
            'total_count'=>$count,
            'page_num'=>$page_num,
            'page_size'=>$page_size,
            'recs'=>$results->toArray()];
    }
    private function gen_pwd($len, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        $str = '';
        $max = strlen($keyspace);
        for ($i = 0; $i < $len; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }
    private function get_merchant_id($account_id) {
        $where = ['account_id'=>$account_id,'role'=>666];
        $rootObj = DB::table('mcf_user_base')->select('merchant_id')->where($where)->first();
        if (empty($rootObj))
            throw new RttException('INVALID_PARAMETER', 'cannot find root obj');
        return $rootObj->merchant_id;
    }
    public function set_merchant_user($la_paras) {
        $cols = ['username', 'account_id', 'role', 'is_deleted'];
        $values = array_intersect_key($la_paras, array_flip($cols));
        if (!in_array($values['role'], [101,365]))
            throw new RttException('INVALID_PARAMETER', 'undefined role');
        $values['merchant_id'] = $this->get_merchant_id($la_paras['account_id']);
        $saltstring = bin2hex(random_bytes(32)); 
        $password = $this->gen_pwd(8);
        $values['password'] = md5($password.$saltstring);
        $values['saltstring'] = $saltstring;
        try{
            $where = ['account_id'=>$la_paras['account_id'],'username'=>$la_paras['username']];
            $is_success = DB::table('mcf_user_base')->where($where)->where('role','<>',666)
                ->update($values);
        }
        catch(\Exception $e) {
            throw new RttException('INVALID_PARAMETER', 'possibly duplicated username.');
        }
        if (!$is_success)
            throw new RttException('SYSTEM_ERROR', 'update failed. possibly user not found');
        return $password;
    }
    public function add_merchant_user($la_paras) {
        $cols = ['username', 'account_id', 'role'];
        $values = array_intersect_key($la_paras, array_flip($cols));
        if (!in_array($values['role'], [101,365]))
            throw new RttException('INVALID_PARAMETER', 'undefined role');
        $where = ['account_id'=>$la_paras['account_id'],'role'=>666];
        $rootObj = DB::table('mcf_user_base')->select('merchant_id')->where($where)->first();
        if (empty($rootObj))
            throw new RttException('INVALID_PARAMETER', 'cannot find root obj');
        $values['merchant_id'] = $rootObj->merchant_id;
        $saltstring = bin2hex(random_bytes(32)); 
        $password = $this->gen_pwd(8);
        $values['password'] = md5($password.$saltstring);
        $values['saltstring'] = $saltstring;
        try{
            DB::table('mcf_user_base')->insert($values);
        }
        catch(\Exception $e) {
            throw new RttException('INVALID_PARAMETER', 'possibly duplicated username.');
        }
        return $password;
    }
    public function create_new_account($la_paras) {
    }

}
