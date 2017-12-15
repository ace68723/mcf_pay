<?php
namespace App\Providers\MgtService;

use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class MgtService{

    public $consts;
    public function __construct() {
        $this->consts = array();
    }

    public function get_merchants($la_paras) {
        $page_num = $la_paras['page_num'];
        $page_size = $la_paras['page_size'];
        $where = ['role', '=', 666];
        $count = DB::table('mcf_user_base')->where($where)->count();
        $merchants = DB::table('mcf_user_base')
            ->where($where)
            ->leftJoin('company_info', 'mcf_user_base.account_id','=','company_info.account_id')
            ->offset(($page_num-1)*$page_size)->limit($page_size);
        return $merchants->toArray();
    }

}
