<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['middleware'=>'auth:custom_api'], function ($router)
{
    $router->post('/create_order/', ['uses'=>'PubAPIController@create_order']);
    $router->post('/create_refund/', ['uses'=>'PubAPIController@create_refund']);
    $router->post('/query_txn_single/', ['uses'=>'PubAPIController@query_txn_single']);
});
//$router->get('/api_doc/', ['uses'=>'PubAPIController@api_doc_md']);
$router->get('/login_api_doc/', ['uses'=>'LoginController@api_doc_md']);

$router->get('/test/', ['uses'=>'PubAPIController@test']);
$router->post('/test/', ['uses'=>'PubAPIController@test']);

$router->group(['prefix'=>'api/v1/merchant','middleware'=>'auth:custom_token'], function ($router)
{
    $api_names = [
        'create_authpay',
        'precreate_authpay',
        'create_order',
        'create_refund',
        'check_order_status',
        'get_exchange_rate',
        'query_txns_by_time',
        'get_hot_txns',
        'get_settlements',
        'get_company_info',
    ];
    foreach($api_names as $api_name) {
        $router->post('/'.$api_name.'/', ['uses'=>'MCFController@'.$api_name]);
    }
    $router->get('/api_doc/', ['uses'=>'MCFController@api_doc_md']);
});


$router->group(['prefix'=>'api/v1/mgt','middleware'=>'auth:custom_mgt_token'], function ($router)
{
    $api_names = [
        'get_merchants',
        'get_merchant_info',
        'set_merchant_basic',
        'set_merchant_contract',
        'set_merchant_device',
        'set_merchant_user',
        'add_merchant_user',
        'set_merchant_channel',
        'get_merchant_settlement',
        'get_candidate_settle',
        'add_settle',
        'set_settlement',
    ];
    foreach($api_names as $api_name) {
        $router->post('/'.$api_name.'/', ['uses'=>'AdminController@'.$api_name]);
    }
    $router->get('/api_doc/', ['uses'=>'AdminController@api_doc_md']);
});


$router->post('/login/', ['middleware'=>'throttle:5,1', 'uses'=>'LoginController@login']); //5 times /1min
$router->post('/mgt/login/', ['middleware'=>'throttle:2,1', 'uses'=>'LoginController@mgt_login']); //2 times /1min


$router->post('/notify/wx/', ['uses'=>'PubAPIController@handle_notify_wx']);
$router->post('/notify/ali', ['uses'=>'PubAPIController@handle_notify_ali']);

