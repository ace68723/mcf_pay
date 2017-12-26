<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class LoginTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testLogin()
    {
        $resp = $this->call('POST','/login', ['merchant_id'=>'aaa','username'=>'bbb','password'=>'ccc']);
        $this->assertEquals(401, $resp->status());
    }
}
