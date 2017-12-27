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
        $resp = $this->json('POST','/login', ['merchant_id'=>'aaa','username'=>'bbb','password'=>'ccc'])
            ->response;
        $this->assertEquals(401, $resp->status());
        $resp = $this->json('POST','/login', ['merchant_id'=>'testMerchant','username'=>'testAdmin','password'=>env('TEST_PWD')])->response;
        $this->assertEquals(200, $resp->status());
        $this->seeJson(['ev_error'=>'0']);
    }
}
