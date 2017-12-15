<?php

use Illuminate\Database\Seeder;

class Mcf_user_base_TableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('mcf_user_base')->insert([
            'merchant_id'=>'testMerchant', 
            'username'=>'testUser', 
            'password'=>'tobemodified', 
            'account_id'=>3, 
            'role'=>101, 
            'saltstring'=>bin2hex(random_bytes(32)), 
        ]);
        DB::table('mcf_user_base')->insert([
            'merchant_id'=>'testMerchant', 
            'username'=>'testAdmin', 
            'password'=>'tobemodified', 
            'account_id'=>3, 
            'role'=>666, 
            'saltstring'=>bin2hex(random_bytes(32)), 
        ]);
        DB::table('mcf_user_base')->insert([
            'merchant_id'=>'mcfAdmin', 
            'username'=>'admin', 
            'password'=>'tobemodified', 
            'role'=>999, 
            'saltstring'=>bin2hex(random_bytes(32)), 
        ]);
    }
}
