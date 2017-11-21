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

$router->post('/create_order/', ['middleware'=>'auth', 'uses'=>'OrderController@create_order']);
$router->post('/create_refund/', ['middleware'=>'auth', 'uses'=>'OrderController@create_refund']);
$router->post('/query_txn_single/', ['middleware'=>'auth', 'uses'=>'OrderController@query_txn_single']);
$router->get('/show_base_api/', ['uses'=>'OrderController@api_doc_md']);

$router->post('/notify/wx/', ['uses'=>'OrderController@handle_notify_wx']);
$router->post('/notify/ali/', ['uses'=>'OrderController@handle_notify_ali']);

//$router->get('/query_single_txn/{txn_id}', ['uses'=>'TxnController@single_query']);
//$router->get('/query_range_txn/{start_date}/{end_date}', ['uses'=>'TxnController@range_query']);
