<?php

namespace App\Providers;

use App\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\GenericUser;
use Log;
use App\Exceptions\RttException;

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
            /*
            Log::DEBUG(implode(';',[$request->input('account_key'),
                $request->input('salt_str'),
                $request->input('sign_type'),
                $request->input('sign')]));
             */
            if (empty($request->input('account_key')) || 
                empty($request->input('salt_str')) ||
                empty($request->input('sign_type')) ||
                empty($request->input('sign'))) 
            {
                throw new RttException('SIGN_ERROR', '0');
                return null;
            }
            $secInfo =  DB::table('account_base')
                ->leftJoin('account_security', 'account_security.account_id','=','account_base.account_id')
                ->select('account_base.account_id AS account_id',
                    'account_security.account_secret AS account_secret')
                ->where([
                    'account_security.account_key'=>$request->input('account_key'),
                    'account_base.is_deleted'=>0,
                    'account_security.is_deleted'=>0
                ])
                ->first();
            if (empty($secInfo) || empty($secInfo->account_secret)) {
                throw new RttException('SIGN_ERROR', "1");
                return null;
            }

            //IMPORTANT comment this *****************
            if (env('APP_DEBUG', false) || $request->input('sign')=='Test2Bcommented') {
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
            //Log::DEBUG("string to check sign before attach key:". utf8_decode($string));
		    $string = md5($string."&key=".$secInfo->account_secret);
            //Log::DEBUG("md5 result:". $string);
            if (strtoupper($string) != strtoupper($request->input('sign'))) {
                throw new RttException('SIGN_ERROR', "2");
                return null;
            }
            return new GenericUser(['account_id'=>$secInfo->account_id]);
        });

        $this->app['auth']->viaRequest('custom_mgt_token', function ($request) {
            $sp = app()->make('user_auth_service');
            $token_info = $sp->check_token($request->header('Auth-Token'), true);
            //$token_info = $sp->decode_token($request->header('Auth-Token'));
            if ($token_info->role < 999) {
                throw new RttException('PERMISSION_DENIED');
            }
            return new GenericUser([
                'uid'=>$token_info->uid,
                'role'=>$token_info->role,
            ]);
        });

        $this->app['auth']->viaRequest('custom_token', function ($request) {
            $sp = app()->make('user_auth_service');
            $token_info = $sp->check_token($request->header('Auth-Token'), false);
            //$token_info = $sp->decode_token($request->header('Auth-Token'));
            return new GenericUser([
                'uid'=>$token_info->uid,
                'role'=>$token_info->role,
                'username'=>$token_info->username,
                'account_id'=>$token_info->account_id,
            ]);
        });
    }
}
