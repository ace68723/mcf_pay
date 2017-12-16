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
        $where = ['role'=>666, 'is_deleted'=>0];
        $count = DB::table('mcf_user_base')->where($where)->count();
        $merchants = DB::table('mcf_user_base')
            ->where($where)
            ->leftJoin('company_info', 'mcf_user_base.account_id','=','company_info.account_id')
            ->select(['merchant_id', 'mcf_user_base.account_id AS account_id', 'display_name',
                    'legal_name', 'cell'])
            ->offset(($page_num-1)*$page_size)->limit($page_size)
            ->get();
        return ['total_count'=>$count, 'recs'=>$merchants->toArray()];
    }
    public function get_merchant_info_basic($la_paras) {
        $where = ['account_id'=>$la_paras['account_id'], 'is_deleted'=>0];
        $results = DB::table('company_info')
            ->where($where)->first();
        return (array)$results;
    }
    public function set_merchant_basic($la_paras) {
        $where = ['account_id'=>$la_paras['account_id']];
        $newObj = $la_paras;
        unset($newObj['account_id']);
        $is_success = DB::table('company_info')
            ->where($where)->update($newObj); //TODO
        return $is_success;
    }
    public function get_merchant_info_contract($la_paras) {
        $where = ['account_id'=>$la_paras['account_id'], 'is_deleted'=>0];
        $results = DB::table('account_contract')
            ->where($where)->first();
        return (array)$results;
    }
    public function set_merchant_contract($la_paras) {
        $where = ['account_id'=>$la_paras['account_id']];
        $newObj = $la_paras;
        unset($newObj['account_id']);
        $is_success = DB::table('account_contract')
            ->where($where)->update($newObj); //TODO
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
        return ['total_count'=>$count, 'recs'=>$results->toArray()];
    }
    public function set_merchant_device($la_paras) {
        $where = ['account_id'=>$la_paras['account_id']];
        $cols = ['account_id','device_id','is_deleted'];
        $values = array_intersect_key($la_paras, array_flip($cols));
        $is_success = DB::table('device')
            ->updateOrInsert(['device_id'=>$values['device_id']], $values);
        return $is_success;
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
        return ['total_count'=>$count, 'recs'=>$results->toArray()];
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
    public function add_merchant_user($la_paras) {
        $cols = ['username','account_id', 'role'];
        $values = array_intersect_key($la_paras, array_flip($cols));
        if (!in_array($values['role'], [101,365]))
            throw new RttException('INVALID_PARAMETER', 'undefined role');
        $where = ['account_id'=>$la_paras['account_id'],'role'=>666];
        $rootObj = DB::table('mcf_user_base')->select('merchant_id')->where($where)->first();
        if (empty($rootObj))
            throw new RttException('INVALID_PARAMETER', 'account_id');
        $values['merchant_id'] = $rootObj->merchant_id;
        $saltstring = bin2hex(random_bytes(32)); 
        $password = $this->gen_pwd(8);
        $values['password'] = md5($password.$saltstring);
        $values['saltstring'] = $saltstring;
        try{
            DB::table('mcf_user_base')->insert($values);
        }
        catch(\Exception $e) {
            throw new RttException('INVALID_PARAMETER', 'may be duplicate username.');
        }
        return $password;
    }

}
