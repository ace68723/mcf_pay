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
$router->get('/show_base_api/', ['uses'=>'PubAPIController@api_doc_md']);

$router->get('/test/', ['uses'=>'PubAPIController@test']);

$router->group(['prefix'=>'mcf','middleware'=>'auth:custom_token'], function ($router)
{
    $router->post('/create_authpay/', ['uses'=>'MCFController@create_authpay']);
    $router->post('/create_order/', ['uses'=>'MCFController@create_order']);
    $router->post('/create_refund/', ['uses'=>'MCFController@create_refund']);
    $router->post('/check_order_status/', ['uses'=>'MCFController@check_order_status']);
    $router->post('/get_exchange_rate/', ['uses'=>'MCFController@get_exchange_rate']);
});
$router->post('/login/', ['uses'=>'LoginController@login']);


$router->post('/notify/wx/', ['uses'=>'PubAPIController@handle_notify_wx']);
$router->post('/notify/ali/', ['uses'=>'PubAPIController@handle_notify_ali']);

