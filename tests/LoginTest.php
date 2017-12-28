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
        $payload['merchant'] = ['merchant_id'=>'testMerchant','username'=>'testAdmin','password'=>env('TEST_PWD')];
        $payload['mgt'] = ['username'=>'admin','password'=>env('TEST_PWD_MGT')];
        $url['merchant'] = '/login';
        $url['mgt'] = '/mgt/login';
        foreach(['merchant','mgt'] as $kind) {
            // Success Test
            $resp = $this->json('POST', $url[$kind], $payload[$kind])->response;
            $this->assertEquals(200, $resp->status());
            $this->seeJson(['ev_error'=>0]);

            // wrong format
            $wrong = $payload[$kind];
            unset($wrong['username']);
            $resp = $this->json('POST',$url[$kind],$wrong)->response;
            $this->assertEquals(401, $resp->status());

            if ($kind == 'mgt') {
                // Test Throttle: Too Many Attempts
                $resp = $this->json('POST',$url[$kind], $payload[$kind])->response;
                $this->assertEquals(429, $resp->status());
                sleep(61);
                $resp = $this->json('POST',$url[$kind], $payload[$kind])->response;
                $this->assertEquals(200, $resp->status());
                sleep(60);
            }

            // wrong pwd
            $wrong = $payload[$kind];
            $wrong['password'] = str_random(4);
            $resp = $this->json('POST',$url[$kind],$wrong)->response;
            $this->assertEquals(401, $resp->status());

            // wrong username
            $wrong = $payload[$kind];
            $wrong['username'] = str_random(4);
            $resp = $this->json('POST',$url[$kind],$wrong)->response;
            $this->assertEquals(401, $resp->status());

            if ($kind == 'merchant') {
                // wrong merchant
                $wrong = $payload[$kind];
                $wrong['merchant_id'] = str_random(4);
                $resp = $this->json('POST',$url[$kind],$wrong)->response;
                $this->assertEquals(401, $resp->status());

                // Test Throttle: Too Many Attempts
                $resp = $this->json('POST',$url[$kind], $payload[$kind])->response;
                $this->assertEquals(429, $resp->status());
                sleep(61);
                $resp = $this->json('POST',$url[$kind], $payload[$kind])->response;
                $this->assertEquals(200, $resp->status());
            }

        }
    }
}
