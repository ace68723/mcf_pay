<?php

namespace App\Providers;

use App\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\GenericUser;
use Log;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {
        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.

        $this->app['auth']->viaRequest('custom_api', function ($request) {
            if (empty($request->input('account_key')) || 
                empty($request->input('salt_str')) ||
                empty($request->input('sign_type')) ||
                empty($request->input('sign'))) 
                return null;
            $secInfo =  DB::table('account_security')
                ->where('account_key', '=', $request->input('account_key'))
                ->where('is_deleted','=',0)
                ->first();
            if (empty($secInfo) || empty($secInfo->account_secret))
                return null;

            //IMPORTANT TODO comment this *****************
            if (env('APP_DEBUG', false)) {
                return new GenericUser(['account_id'=>$secInfo->account_id]);
            }
            //********************************

            $input_paras = $request->json()->all();
		    ksort($input_paras);
            $string = "";
            foreach ($input_paras as $k => $v) {
                if($k != "sign" && $v != "" && !is_array($v)){
                    $string .= $k . "=" . $v . "&";
                }
            }
		    $string = trim($string, "&");
            Log::DEBUG("string to check sign before attach key:". utf8_decode($string));
		    $string = md5($string."&key=".$secInfo->account_secret);
            Log::DEBUG("md5 result:". $string);
            if (strtoupper($string) != strtoupper($request->input('sign')))
                return null;
            return new GenericUser(['account_id'=>$secInfo->account_id]);
        });

        $this->app['auth']->viaRequest('custom_mgt_token', function ($request) {
            $sp = app()->make('user_auth_service');
            $token_info = $sp->check_token($request->header('Auth-Token'));
            if (empty($token_info->uid)
                || empty($token_info->role)
                || $token_info->role < 999
                || empty($token_info->expire) )
            {
                Log::DEBUG("empty token_info or insufficient role");
                return null;
            }
            if (!env('APP_DEBUG') && time() > $token_info->expire) {
                Log::DEBUG("token expire:".time().">".$token_info->expire);
                return null;
            }
            return new GenericUser([
                'uid'=>$token_info->uid,
                'role'=>$token_info->role,
            ]);
        });

        $this->app['auth']->viaRequest('custom_token', function ($request) {
            /*
            $a = debug_backtrace();
            $b = json_encode($a, JSON_PARTIAL_OUTPUT_ON_ERROR, 2);
            Log::DEBUG($b);
             */
            $sp = app()->make('user_auth_service');
            $token_info = $sp->check_token($request->header('Auth-Token'));
            if (empty($token_info->uid)
                || empty($token_info->role)
                || empty($token_info->username)
                || empty($token_info->account_id)
                || empty($token_info->expire) )
            {
                Log::DEBUG("empty token_info");
                return null;
            }
            if (!env('APP_DEBUG') && time() > $token_info->expire) {
                Log::DEBUG("token expire:".time().">".$token_info->expire);
                return null;
            }
            return new GenericUser([
                'uid'=>$token_info->uid,
                'role'=>$token_info->role,
                'username'=>$token_info->username,
                'account_id'=>$token_info->account_id,
            ]);
        });
    }
}
