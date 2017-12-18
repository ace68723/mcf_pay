<?php
namespace App\Providers\SettleService;

use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SettleService{

    public $consts;
    public function __construct() {
        $this->consts = array();
    }

    public function get_possible_settlements($la_paras) {
    }
    public function get_settlements($la_paras, $account_id) {
        $page_num = $la_paras['page_num'];
        $page_size = $la_paras['page_size'];
        $where = ['account_id'=>$account_id, 'is_deleted'=>0];
        $count = DB::table('settlement')->where($where)->count();
        $results = DB::table('settlement')
            ->where($where)
            ->offset(($page_num-1)*$page_size)->limit($page_size)
            ->get();
        return $results->toArray();
    }
    public function settle($la_paras) {
        $account_id = $la_paras['account_id'];
        $end_time = time() - 24*60*60;
        $last = DB::table('settlement')->where('account_id','=',$account_id)->max('end_time');
        $start_time = (empty($last))? 0: $last;
        $result = DB::table('txn_base')->where('account_id','=',$account_id)
            ->where('vendor_txn_time','>=',$start_time)
            ->where('vendor_txn_time','<',$end_time)
            ->select(DB::raw('count(*) as txn_num'))
            ->get();
        return $result->toArray();
    }

}
