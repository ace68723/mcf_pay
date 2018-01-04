<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class RttServiceTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @group base
     * @group speed
     * @group dbSpeed
     * @return array headers
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
        print_r("logined");
        return $headers;
    }
    /**
     * @depends testLogin
     * @return string out_trade_no
     */
    public function testAuthPay($headers)
    {
        $data = [
            'total_fee_in_cent'=>random_int(1,1000000),
            'total_fee_currency'=>'CAD',
            'vendor_channel'=>'tc',
            'device_id'=>'FROM_WEB',
        ];
        $resp = $this->json('POST', '/api/v1/merchant/precreate_authpay', $data, $headers)->response;
        $this->assertEquals(200, $resp->status());
        $this->seeJson(['ev_error'=>0]);
        $ret_data = (array)json_decode($resp->getContent());
        $data['out_trade_no'] = $ret_data['ev_data']->out_trade_no;
        $data['auth_code'] = 'blabla';
        $resp = $this->json('POST', '/api/v1/merchant/create_authpay', $data, $headers)->response;
        $this->assertEquals(200, $resp->status());
        $this->seeJson(['ev_error'=>0]);
        $this->seeInDatabase('txn_base',['ref_id'=>$data['out_trade_no'], 'txn_fee_in_cent'=>$data['total_fee_in_cent']]);
        return $data['out_trade_no'];
    }
    /**
     * @depends testLogin
     */
    public function testNativePay($headers)
    {
        $data = [
            'total_fee_in_cent'=>random_int(1,1000000),
            'total_fee_currency'=>'CAD',
            'vendor_channel'=>'tc',
            'device_id'=>'FROM_WEB',
        ];
        $resp = $this->json('POST', '/api/v1/merchant/create_order', $data, $headers)->response;
        $this->assertEquals(200, $resp->status());
        $this->seeJson(['ev_error'=>0]);
        $ret_data = (array)json_decode($resp->getContent());
        $data['out_trade_no'] = $ret_data['ev_data']->out_trade_no;
        $this->seeInDatabase('txn_base',['ref_id'=>$data['out_trade_no'], 'txn_fee_in_cent'=>$data['total_fee_in_cent']]);
    }
    /**
     * @depends testLogin
     * @depends testAuthPay
     */
    public function testCheckOrderStatus($headers, $out_trade_no)
    {
        $resp = $this->json('POST', '/api/v1/merchant/check_order_status',[
            'out_trade_no'=>$out_trade_no,
            'type'=>'xxx',
            'vendor_channel'=>'tc',
        ], $headers)->response;
        $this->assertEquals(200, $resp->status());
        $this->seeJson(['ev_error'=>0]);
        $ret_data = (array)json_decode($resp->getContent());
        $this->assertEquals('SUCCESS', $ret_data['ev_data']->status);
    }
    /**
     * @group speed
     * @group redisSpeed
     * @depends testLogin
     * @depends testAuthPay
     */
    public function testCheckOrderStatusSpeed($headers, $out_trade_no)
    {
        $data = [
            'out_trade_no'=>$out_trade_no,
            'type'=>'xxx',
            'vendor_channel'=>'tc',
        ];
        $nRepeat = 1000;
        $timer = -microtime(true);
        for ($i=0; $i<$nRepeat; $i++) {
            $resp = $this->json('POST', '/api/v1/merchant/check_order_status', $data, $headers)->response;
        }
        $timer += microtime(true);
        print_r("\n".$nRepeat." run time:".$timer."\n");
        print_r("\nSpeed:".($nRepeat/$timer)." req per sec\n");
        $this->assertEquals(200, $resp->status());
        $this->seeJson(['ev_error'=>0]);
        $ret_data = (array)json_decode($resp->getContent());
        $this->assertEquals('SUCCESS', $ret_data['ev_data']->status);
    }
    public function getBenchmarkSpeed()
    {
        $nRepeat = 1000;
        $timer = -microtime(true);
        for ($i=0; $i<$nRepeat; $i++) {
            $this->get('/');
        }
        $timer += microtime(true);
        $this->assertEquals(200, $resp->status());
        /*
        print_r("\n".$nRepeat." run time:".$timer."\n");
        print_r("\nSpeed:".($nRepeat/$timer)." req per sec\n");
         */
        return ['nRepeat'=>$nRepeat, 'time'=>$timer];
    }
    /**
     * @group speed
     * @group dbSpeed
     * @depends testLogin
     */
    public function testDbQuerySpeed($headers)
    {
        app()->configureMonologUsing(function (\Monolog\Logger $monolog) {
            $handler = new \Monolog\Handler\StreamHandler(storage_path('logs/lumen.log'), \Monolog\Logger::INFO);
            $handler->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true, true));
            //$oldhandler=$monolog->popHandler();
            $monolog->pushHandler($handler);
        });
        $data = [
            'ref_id'=>'MCFALTESTBO201712140453570839', //make sure that this is not in the cache
        ];
        $nRepeat = 1000;
        $timer = -microtime(true);
        for ($i=0; $i<$nRepeat; $i++) {
            $resp = $this->json('POST', '/api/v1/merchant/get_txn_by_id', $data, $headers)->response;
        }
        $timer += microtime(true);
        print_r("\n".$nRepeat." run time:".$timer."\n");
        print_r("\nSpeed:".($nRepeat/$timer)." req per sec\n");
        $this->assertEquals(200, $resp->status());
        $this->seeJson(['ev_error'=>0]);
        $ret_data = (array)json_decode($resp->getContent());
        $this->assertEquals('CAD', $ret_data['ev_data']->amount_currency);
    }

}
