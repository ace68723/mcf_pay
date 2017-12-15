<?php

use Illuminate\Database\Seeder;

class Account_base_TableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        DB::table('account_base')->insert([
            'account_id'=>1, 
            'ref_id'=>'YMHL', 
            'currency_type'=>'CAD',
            'changed_by'=>'test_seeder', 
        ]);
        DB::table('account_base')->insert([
            'account_id'=>2, 
            'ref_id'=>'TESTAL', 
            'currency_type'=>'CAD',
            'changed_by'=>'test_seeder', 
        ]);
        DB::table('account_base')->insert([
            'account_id'=>3, 
            'ref_id'=>'TESTBO', 
            'currency_type'=>'CAD',
            'changed_by'=>'test_seeder', 
        ]);
        DB::table('account_base')->insert([
            'account_id'=>4, 
            'ref_id'=>'TESTNU', 
            'currency_type'=>'CAD',
            'changed_by'=>'test_seeder', 
        ]);
        DB::table('account_contract')->insert([
            'account_id'=>3,
            'device_amount'=>3,
            'tip_mode'=>'display',
            'remit_min_in_cent'=>10000,
        ]);
        DB::table('company_info')->insert([
            'account_id'=>3,
            'display_name'=>'测试商家',
            'cell'=>'123456789',
            'address'=>'Centre of the Universe',
        ]);
    }
}
