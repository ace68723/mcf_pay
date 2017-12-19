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

    public function get_candidate_settle($la_paras) {
        $page_num = $la_paras['page_num'];
        $page_size = $la_paras['page_size'];
        $rawStr = <<<rawstr
    SELECT t4.account_id AS account_id, t3.mch_id AS merchant_id, t4.amount_in_cent AS amount_in_cent
        FROM (SELECT txn_base.account_id AS account_id, sum(txn_fee_in_cent*(1-2*is_refund)) AS amount_in_cent
        FROM txn_base
        LEFT JOIN 
        (SELECT t1.account_id, max(end_time) AS last_time FROM (
            SELECT distinct(account_id) FROM txn_base
            ) AS t1
            LEFT JOIN settlement on settlement.account_id = t1.account_id
            GROUP BY t1.account_id
        ) AS t2
        ON txn_base.account_id = t2.account_id 
        WHERE last_time IS NULL OR vendor_txn_time>=last_time 
        GROUP BY txn_base.account_id
        ) AS t4
    LEFT JOIN 
        (SELECT account_id, ANY_VALUE(merchant_id) AS mch_id FROM mcf_user_base GROUP BY account_id
        ) AS t3
        ON t4.account_id = t3.account_id
    LEFT JOIN 
        (SELECT account_id, remit_min_in_cent FROM account_contract
        ) AS t5
        ON t4.account_id = t5.account_id
    WHERE amount_in_cent >= t5.remit_min_in_cent
    ORDER BY amount_in_cent DESC
rawstr;
        $rawStr .= ' LIMIT '.$page_size;
        $rawStr .= ' OFFSET '.($page_size*($page_num-1));
        $result = DB::select(DB::raw($rawStr));
        return $result;
    }
    public function get_settlements($la_paras, $account_id) {
        $page_num = $la_paras['page_num'];
        $page_size = $la_paras['page_size'];
        $where = ['account_id'=>$account_id];
        $count = DB::table('settlement')->where($where)->count();
        $results = DB::table('settlement')
            ->where($where)
            ->offset(($page_num-1)*$page_size)->limit($page_size)
            ->get();
        return ['total_count'=>$count, 'recs'=>$results->toArray()];
    }
    public function set_settlements($la_paras) {
        $settle_id = $la_paras['settle_id'];
        $cols = ['is_remitted', 'note'];
        $values = array_intersect_key($la_paras, array_flip($cols));
        DB::table('settlement')->where('settle_id','=',$settle_id)->update($values);
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
            if ($settle['txn_num'] == 0)
                throw new RttException('SYSTEM_ERROR', 'no transaction to settle');
            $settle_id = DB::table('settlement')->insertGetId($settle);
            $settle['settle_id'] = $settle_id;
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