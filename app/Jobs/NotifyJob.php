<?php

namespace App\Jobs;
use Log;
use Queue;

class NotifyJob extends Job
{
    protected $url;
    protected $txn;
    protected $idx;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($url, $txn, $idx=0)
    {
        $this->url = $url;
        $this->txn = $txn;
        $this->idx = $idx;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $notify_intervals = [5, 15, 30, 60];
        Log::debug($this->url.":".json_encode($this->txn).":".$this->idx);
        if ($this->idx == 0) {
            $job = new NotifyJob($this->url,$this->txn,1);
            Queue::later($notify_intervals[$this->idx], $job);
        }
    }
}
