<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customtest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'just a test';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $sp = app()->make('wx_vendor_service');
        $sp->sync_bill();
        return;
        //$map = $sp->get_mchid_aid_map();
        //echo json_encode($map);
        $sp = app()->make('rtt_service');
        $sp->compare("wx",1515436276,1515450444);
    }
}
