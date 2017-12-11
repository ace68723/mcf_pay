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

$router->group(['prefix'=>'mcf','middleware'=>'auth:custom_token'], function ($router)
{
    $api_names = ['create_authpay','precreate_authpay','create_order','create_refund',
        'check_order_status','get_exchange_rate', 'query_txns_by_time'];
    foreach($api_names as $api_name) {
        $router->post('/'.$api_name.'/', ['uses'=>'MCFController@'.$api_name]);
    }
    $router->get('/api_doc/', ['uses'=>'MCFController@api_doc_md']);
});
$router->post('/login/', ['middleware'=>'throttle:5,1', 'uses'=>'LoginController@login']); //5 times /1min


$router->post('/notify/wx/', ['uses'=>'PubAPIController@handle_notify_wx']);
$router->post('/notify/ali/', ['uses'=>'PubAPIController@handle_notify_ali']);

