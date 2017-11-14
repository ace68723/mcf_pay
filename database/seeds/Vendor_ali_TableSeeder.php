<?php

use Illuminate\Database\Seeder;

class Vendor_ali_TableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        DB::table('vendor_ali')->insert([
            'account_id'=>2, 
            'rate'=>150,
            'sub_mch_id'=>'TEST2',
            'sub_mch_name'=>'TEST2-name',
            'sub_mch_industry'=>'7011',
        ]);
        DB::table('vendor_ali')->insert([
            'account_id'=>3, 
            'rate'=>150,
            'sub_mch_id'=>'TEST3',
            'sub_mch_name'=>'TEST3-name',
            'sub_mch_industry'=>'7011',
        ]);
    }
}
