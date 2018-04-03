<?php

namespace App\Jobs;
use Log;

class NotifyJob extends Job
{
    protected $info;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($info="")
    {
        //
        $this->info = $info;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        Log::debug($this->info);
    }
}
