<?php
namespace App\Providers\SettleService;

use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Exceptions\RttException;

class SettleService{

    public $consts;
    public function __construct() {
        $this->consts = array();
    }

    public function get_possible_settlements($la_paras) {
        $page_num = $la_paras['page_num'];
        $page_size = $la_paras['page_size'];
        DB::raw(
            'SELECT * FROM (
            )'
        );
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
        $end_time = $la_paras['end_time'] ?? time() - 24*60*60;
        //$start_time = $la_paras['start_time'] ?? 0;
        $sp_rtt = app()->make('rtt_service');
        $currency = DB::table('account_base')->select('currency_type')
            ->where('account_id','=',$account_id)->first()->currency_type;
        DB::beginTransaction();
        try {
            //if (empty($la_paras['start_time'])) {
            $last = DB::table('settlement')->where('account_id','=',$account_id)->max('end_time');
            $start_time = (empty($last))? 0: $last;
            //}
            $result = DB::table('txn_base')->where('account_id','=',$account_id)
                ->where('vendor_txn_time','>=',$start_time)
                ->where('vendor_txn_time','<',$end_time)
                ->select(DB::raw('vendor_channel, count(*) as txn_num, sum(txn_fee_in_cent*(1-2*is_refund)) as amount_in_cent'))
                ->groupby('vendor_channel')
                ->get()->toArray();
            $settle = [
                'amount_in_cent'=>0,
                'comm_in_cent'=>0,
                'txn_num'=>0,
                'account_id'=>$account_id,
                'start_time'=>$start_time,
                'end_time'=>$end_time,
                'settle_time'=>time(),
                'currency'=>$currency,
                'is_remitted'=>0,
                'remitted_at'=>0,
                'remitted_by'=>'',
                'notes'=>'',
                'updated_at'=>time(),
            ];
            $details = [];
            foreach($result as $rec) {
                $channel = strtolower($sp_rtt->consts['CHANNELS_REV'][$rec->vendor_channel]);
                $channelInfo = DB::table('vendor_'.$channel)->where('account_id','=',$account_id)->first();
                $rate = intval($channelInfo->rate);
                $rec->comm_in_cent = round($rec->amount_in_cent*$rate/10000);
                $settle['amount_in_cent'] += intval($rec->amount_in_cent);
                $settle['comm_in_cent'] += $rec->comm_in_cent;
                $settle['txn_num'] += $rec->txn_num;
                $details[] = [
                    'vendor_channel'=>$rec->vendor_channel,
                    'amount_in_cent'=>$rec->amount_in_cent,
                    'comm_in_cent'=>$rec->comm_in_cent,
                    'currency'=>$currency,
                    'txn_num'=>$rec->txn_num,
                    'rate'=>$rate,
                ];
            }
            $settle_id = DB::table('settlement')->insertGetId($settle);
            foreach($details as $detail) {
                $detail['settle_id']=$settle_id;
                DB::table('settle_detail')->insert($detail);
            }
            DB::commit();
        }
        catch(\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        return ['settle'=>$settle, 'details'=>$details];
    }

}
