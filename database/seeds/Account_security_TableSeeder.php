<?php

use Illuminate\Database\Seeder;

class Account_security_TableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        for($i=1; $i<=4; $i++) {
            DB::table('account_security')->insert([
                'account_id'=>$i, 
                'account_key'=>str_random(32), 
                'account_secret'=>str_random(32), 
                'changed_by'=>'test_seeder',
            ]);
        }
    }
}
