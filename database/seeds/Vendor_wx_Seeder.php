<?php

use Illuminate\Database\Seeder;

class Vendor_wx_Seeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        DB::table('vendor_wx')->insert([
            'account_id'=>1, 
            'rate'=>150,
            'sub_mch_id'=>'50775702',
        ]);
        DB::table('vendor_wx')->insert([
            'account_id'=>3, 
            'rate'=>150,
            'sub_mch_id'=>'50775702',
        ]);
    }
}
