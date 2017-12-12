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
            'status'=>'NORMAL', 
            'currency_type'=>'CAD',
            'changed_by'=>'test_seeder', 
        ]);
        DB::table('account_base')->insert([
            'account_id'=>2, 
            'ref_id'=>'TESTAL', 
            'status'=>'NORMAL', 
            'currency_type'=>'CAD',
            'changed_by'=>'test_seeder', 
        ]);
        DB::table('account_base')->insert([
            'account_id'=>3, 
            'ref_id'=>'TESTBO', 
            'status'=>'NORMAL', 
            'currency_type'=>'CAD',
            'changed_by'=>'test_seeder', 
        ]);
        DB::table('account_base')->insert([
            'account_id'=>4, 
            'ref_id'=>'TESTNU', 
            'status'=>'NORMAL', 
            'currency_type'=>'CAD',
            'changed_by'=>'test_seeder', 
        ]);
    }
}
