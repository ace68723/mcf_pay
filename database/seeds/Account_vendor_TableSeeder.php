<?php

use Illuminate\Database\Seeder;

class Account_vendor_TableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        DB::table('account_vendor')->insert(['account_id'=>1, 'vendor_channel'=>0x1]);
        DB::table('account_vendor')->insert(['account_id'=>2, 'vendor_channel'=>0x2]);
        DB::table('account_vendor')->insert(['account_id'=>3, 'vendor_channel'=>0x3]);
        DB::table('account_vendor')->insert(['account_id'=>4, 'vendor_channel'=>0x8000]);
    }
}
