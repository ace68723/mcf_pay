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

$router->group(['middleware'=>'auth:api'], function ($router)
{
    $router->post('/create_order/', ['uses'=>'PubAPIController@create_order']);
    $router->post('/create_refund/', ['uses'=>'PubAPIController@create_refund']);
    $router->post('/query_txn_single/', ['uses'=>'PubAPIController@query_txn_single']);
});
$router->get('/show_base_api/', ['uses'=>'PubAPIController@api_doc_md']);

$router->get('/test/', ['uses'=>'PubAPIController@test']);

$router->group(['prefix'=>'mcf','middleware'=>'auth:token'], function ($router)
{
    $router->post('/create_order/', ['uses'=>'MCFController@create_order']);
    $router->post('/create_refund/', ['uses'=>'MCFController@create_refund']);
    $router->post('/query_txn_single/', ['uses'=>'MCFController@query_txn_single']);
});
$router->post('/login/', ['uses'=>'LoginController@login']);


$router->post('/notify/wx/', ['uses'=>'PubAPIController@handle_notify_wx']);
$router->post('/notify/ali/', ['uses'=>'PubAPIController@handle_notify_ali']);

