<?php

namespace App\Jobs;
use Log;
use Queue;

class NotifyJob extends Job
{
    protected $url;
    protected $txn;
    protected $idx;
    protected $secret;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($url, $txn, $idx=0, $secret=null)
    {
        $this->url = $url;
        $this->txn = $txn;
        $this->idx = $idx;
        $this->secret = $secret;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $notify_intervals = [30, 60, 120];
        Log::debug("notify_job:".$this->url.":".($this->txn['ref_id']??"").":".$this->idx);
        $ret = $this->do_notify();
        if (!$ret && $this->idx < count($notify_intervals)) {
            $job = new NotifyJob($this->url,$this->txn,$this->idx+1,$this->secret);
            Queue::later($notify_intervals[$this->idx], $job);
        }
    }

    private function do_notify($timeout = 30) {
        $txn = $this->txn;
        try{
            if (is_null($this->secret)) {
                $secInfo =  DB::table('account_base')
                    ->leftJoin('account_security', 'account_security.account_id','=','account_base.account_id')
                    ->select('account_base.account_id AS account_id',
                        'account_security.account_secret AS account_secret')
                        ->where([
                            'account_base.account_id'=>$txn['account_id'],
                            'account_base.is_deleted'=>0,
                            'account_security.is_deleted'=>0
                        ])
                        ->first();
                $this->secret = $secInfo['account_secret'];
            }
            $payload = array_only($txn, ['ref_id', 'txn_fee_in_cent', 'txn_fee_currency']);
            $payload['sign_type']='MD5';
            ksort($payload);
            $string = "";
            foreach ($payload as $k => $v) {
                if($k != "sign" && $v != "" && !is_array($v)){
                    $string .= $k . "=" . $v . "&";
                }
            }
            $string = trim($string, "&");
            //Log::DEBUG("string to check sign before attach key:". utf8_decode($string));
            $string = md5($string."&key=".$this->secret);
            $payload['sign'] = $string;
            $rets = $this->do_post_curl($this->url, $payload, $timeout);
        }
        catch (\Exception $e) {
            Log::debug('notify_job: failed:'.$e->getMessage());
            return false;
        }
        return true;
    }
    private function do_post_curl($url, $payload, $timeout) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        $data = curl_exec($ch);
        if($data !== false){
            $ret_code = curl_getinfo($ch);
            if ($ret_code['http_code'] != 200) {
                Log::debug("notify_job: got http_code:".$ret_code['http_code']);
                return false;
            }
            curl_close($ch);
            return true;
        }
        else {
            $error = curl_errno($ch);
            curl_close($ch);
            Log::debug("notify_job: curl error no: $error");
        }
        return false;
    }
}
