<?php

namespace App\Console;

use Log;
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
        Commands\TestCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // note that this function will be executed every time artisan is called, independent of which command
        //Log::DEBUG('setting schedule func called');
        $schedule->call(function () {
            Log::DEBUG('running scheduled_wx_sync');
            $sp = app()->make('wx_vendor_service');
            $sp->sync_bill();
        })->dailyAt('04:00');
        $schedule->call(function () {
            Log::DEBUG('running scheduled_compare');
            $rtt_sp = app()->make('rtt_service');
            $start = new \DateTime("now");
            $end = new \DateTime("now");
            $start->modify("-4 day");
            $end->modify("-1 day");
            $rtt_sp->compare("wx",$start->getTimestamp(),$end->getTimestamp());
        })->dailyAt('05:00');
    }
}
