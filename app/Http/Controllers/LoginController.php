<?php

namespace App\Http\Controllers;

use Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\RttException;
use Exception;


/** should be dispatch controller
 */
class LoginController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->sp_login = app()->make('user_auth_service');
        $this->sp_rtt = app()->make('rtt_service');

        $this->consts['REQUEST_PARAS'] = [];
        $this->consts['IGNORED_REQ_PARAS'] = [
        //    'salt_str', 'account_key', 'sign', 'sign_type',
        ];
        $this->consts['REQUEST_PARAS']['login'] = [
            'merchant_id'=>[
                'checker'=>['is_string', 32],
                'required'=>true,
            ],
            'username'=>[
                'checker'=>['is_string', 32],
                'required'=>true,
            ],
            'password'=>[
                'checker'=>['is_string', 64],
                'required'=>true,
            ],
            'version'=>[
                'checker'=>['is_string', 10],
                'required'=>false,
                'default_value'=>"",
            ],
            'latlng'=>[
                'checker'=>['is_string', 32],
                'required'=>false,
                'default_value'=>"0,0",
            ],
        ];// parameter's name MUST NOT start with "_", which are reserved for internal populated parameters
        $this->consts['REQUEST_PARAS']['token_login'] = [
            'token'=>[
                'checker'=>['is_string',],
                'required'=>true,
            ],
            'version'=>[
                'checker'=>['is_string', 10],
                'required'=>false,
                'default_value'=>"",
            ],
            'latlng'=>[
                'checker'=>['is_string', 32],
                'required'=>false,
                'default_value'=>"0,0",
            ],
        ];// parameter's name MUST NOT start with "_", which are reserved for internal populated parameters
        $this->consts['REQUEST_PARAS']['mgt_login'] = [
            'username'=>[
                'checker'=>['is_string', 32],
                'required'=>true,
            ],
            'password'=>[
                'checker'=>['is_string', 64],
                'required'=>true,
            ],
            'version'=>[
                'checker'=>['is_string', 10],
                'required'=>false,
                'default_value'=>"",
            ],
            'latlng'=>[
                'checker'=>['is_string', 32],
                'required'=>false,
                'default_value'=>"0,0",
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        if (!$this->check_api_def())
            throw new RttException('SYSTEM_ERROR', "ERROR SETTING IN API SCHEMA");
    }

    public function mgt_login(Request $request)
    {
        try {
            $la_paras = $this->parse_parameters($request, __FUNCTION__);
            $userObj = $this->sp_login->mgt_login($la_paras);
            $token = $this->sp_login->mgt_create_token($userObj);
        }
        catch (Exception $e) {
            Log::DEBUG($e->getMessage());
            throw new RttException('LOGIN_FAIL');
        }
        Log::DEBUG('Success Login Uid:'.$userObj->uid);
        return ['ev_error'=>0, 'ev_message'=>"",
            'token'=>$token, 'role'=>$userObj->role];
    }
    public function login(Request $request)
    {
        try {
            $la_paras = $this->parse_parameters($request, __FUNCTION__);
            $userObj = $this->sp_login->login($la_paras);
            $channels = $this->sp_rtt->get_vendor_channel_info($userObj->account_id, true);
            $token = $this->sp_login->create_token($userObj);
        }
        catch (Exception $e) {
            Log::DEBUG($e->getFile().$e->getLine().$e->getMessage());
            throw new RttException('LOGIN_FAIL');
        }
        Log::DEBUG('Success Login Uid:'.$userObj->uid);
        return ['ev_error'=>0, 'ev_message'=>"",
            'token'=>$token, 'role'=>$userObj->role, 'channel'=>$channels];
    }
    public function token_login(Request $request)
    {
        try {
            $la_paras = $this->parse_parameters($request, __FUNCTION__);
            $userObj = $this->sp_login->token_login($la_paras);
            $channels = $this->sp_rtt->get_vendor_channel_info($userObj->account_id, true);
            $token = $this->sp_login->create_token($userObj);
        }
        catch (Exception $e) {
            Log::DEBUG($e->getFile().$e->getLine().$e->getMessage());
            throw new RttException('LOGIN_FAIL');
            //return response('Login Failed.', 401);
        }
        return ['ev_error'=>0, 'ev_message'=>"",
            'token'=>$token, 'role'=>$userObj->role, 'channel'=>$channels];
    }

}
