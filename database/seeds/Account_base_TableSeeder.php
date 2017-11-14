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
            'name'=>'雁鸣画廊', 
            'status'=>'NORMAL', 
            'changed_by'=>'test_seeder', 
        ]);
        DB::table('account_base')->insert([
            'account_id'=>2, 
            'ref_id'=>'TESTAL', 
            'name'=>'TestAccount2', 
            'status'=>'NORMAL', 
            'changed_by'=>'test_seeder', 
        ]);
        DB::table('account_base')->insert([
            'account_id'=>3, 
            'ref_id'=>'TESTBO', 
            'name'=>'TestAccount3', 
            'status'=>'NORMAL', 
            'changed_by'=>'test_seeder', 
        ]);
        DB::table('account_base')->insert([
            'account_id'=>4, 
            'ref_id'=>'TESTNU', 
            'name'=>'TestAccount4', 
            'status'=>'NORMAL', 
            'changed_by'=>'test_seeder', 
        ]);
    }
}
