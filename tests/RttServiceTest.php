<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class RttServiceTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return array
     */
    public function testLogin()
    {
        $payload['merchant'] = ['merchant_id'=>'testMerchantOnly','username'=>'testUser','password'=>env('TEST_PWD_TC')];
        $resp = $this->json('POST', '/login', $payload['merchant'])->response;
        $this->assertEquals(200, $resp->status());
        $login_data = (array)json_decode($resp->getContent());
        $headers = [
            'Auth-Token'=>$login_data['token'],
        ];
        return $headers;
    }
    /**
     * @depends testLogin
     */
    public function testAuthPay($headers)
    {
        $data = [
            'total_fee_in_cent'=>random_int(1,1000),
            'total_fee_currency'=>'CAD',
            'vendor_channel'=>'tc',
            'device_id'=>'FROM_WEB',
        ];
        $resp = $this->json('POST', '/api/v1/merchant/precreate_authpay', $data, $headers)->response;
        $this->assertEquals(200, $resp->status());
        $precreate_data = (array)json_decode($resp->getContent());
        $data['out_trade_no'] = $precreate_data['ev_data']->out_trade_no;
        $data['auth_code'] = 'blabla';
        $resp = $this->json('POST', '/api/v1/merchant/create_authpay', $data, $headers)->response;
        $this->assertEquals(200, $resp->status());
        $this->seeInDatabase('txn_base',['ref_id'=>$data['out_trade_no'], 'txn_fee_in_cent'=>$data['total_fee_in_cent']]);
    }
    /**
     * @depends testLogin
     */
    public function testNativePay($headers)
    {
        $data = [
            'total_fee_in_cent'=>random_int(1,1000),
            'total_fee_currency'=>'CAD',
            'vendor_channel'=>'tc',
            'device_id'=>'FROM_WEB',
        ];
        $resp = $this->json('POST', '/api/v1/merchant/create_order', $data, $headers)->response;
        $this->assertEquals(200, $resp->status());
        $ret_data = (array)json_decode($resp->getContent());
        $data['out_trade_no'] = $ret_data['ev_data']->out_trade_no;
        $this->seeInDatabase('txn_base',['ref_id'=>$data['out_trade_no'], 'txn_fee_in_cent'=>$data['total_fee_in_cent']]);
    }
}
