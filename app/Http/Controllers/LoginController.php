<?php

namespace App\Http\Controllers;

use Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
            'merchantID'=>[
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
                'required'=>true,
            ],
            'latlng'=>[
                'checker'=>['is_string', 32],
                'required'=>true,
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        if (!$this->check_api_def())
            throw new RttException('SYSTEM_ERROR', "ERROR SETTING IN API SCHEMA");
    }

    public function login(Request $request)
    {
        try {
            $la_paras = $this->parse_parameters($request, "login");
            $userObj = $this->sp_login->login($la_paras);
            $token = $this->sp_login->create_token($userObj);
            $channels = $this->sp_rtt->get_vendor_channel_info($userObj->account_id, true);
        }
        catch (Exception $e) {
            Log::DEBUG($e->getMessage());
            return response('Login Failed.', 401);
        }
        return ['token'=>$token, 'role'=>$userObj->role, 'channel'=>$channels];
    }

}
