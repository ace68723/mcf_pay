<?php
namespace App\Providers\MgtService;

use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

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
        return ['total_count'=>$count, 'merchants'=>$merchants->toArray()];
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
        $results = DB::table('company_info')
            ->where($where)->update($newObj); //TODO
        return (array)$results;
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
        $results = DB::table('account_contract')
            ->where($where)->update($newObj); //TODO
        return (array)$results;
    }

}
