<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call('Account_base_TableSeeder');
        $this->call('Account_vendor_TableSeeder');
        $this->call('Account_security_TableSeeder');
        $this->call('Vendor_wx_Seeder');
        $this->call('Vendor_ali_TableSeeder');
        $this->call('Mcf_user_base_TableSeeder'); //don't update this frequently
    }
}
