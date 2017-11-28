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
        //
        DB::table('mcf_user_base')->insert([
            'merchant_id'=>'testMerchant', 
            'username'=>'testUser', 
            'password'=>'tobemodified', 
            'account_id'=>1, 
            'role'=>1, 
            'saltstring'=>bin2hex(random_bytes(32)), 
            'create_time'=>time(),
        ]);
    }
}
