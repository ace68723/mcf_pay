<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //
        //$sp = app()->make('wx_vendor_service');
        //$sp->sync_bill();
            /*
        $schedule->call(function () {
            $sp = app()->make('wx_service');
            $sp->sync_bill();
        })->everyDay();
             */
    }
}
