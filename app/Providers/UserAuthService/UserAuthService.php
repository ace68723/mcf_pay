<?php
namespace App\Providers\UserAuthService;

use Log;
use \Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
//use Illuminate\Http\Request;
use Exception;
use App\Exceptions\RttException;

class UserAuthService{

    public $consts;
    public function __construct() {
        $this->consts['token_expire_sec'] = 7*24*60*60;
    }

    public function check_token($token, $b_for_mgt=false) {
        /*
        $a = debug_backtrace();
        $b = json_encode($a, JSON_PARTIAL_OUTPUT_ON_ERROR, 2);
        Log::DEBUG($b);
         */
        if (empty($token)) {
            throw new RttException('INVALID_TOKEN');
        }
        try {
            $token_info = JWT::decode($token, env('APP_KEY'), array('HS256'));
        }
        catch (Exception $e) {
            throw new RttException('INVALID_TOKEN');
        }
        if (empty($token_info->uid)
            || empty($token_info->role)
            || (!$b_for_mgt && empty($token_info->username))
            || (!$b_for_mgt && empty($token_info->account_id))
            || empty($token_info->expire)
        )
        {
            throw new RttException('INVALID_TOKEN');
        }
        if (time() > $token_info->expire) {
            Log::DEBUG("token expire:".time().">".$token_info->expire);
            throw new RttException('TOKEN_EXPIRE');
        }
        $key = 'auth:token:'.$token_info->uid;
        $last_login = Redis::GET($key);
        if (empty($last_login))
            throw new RttException('TOKEN_EXPIRE');
        if ($last_login != $token_info->expire) { //compare int with string do not use !==
            Log::DEBUG("token kicked: (new)".($last_login)."!=(old)".$token_info->expire);
            throw new RttException('TOKEN_KICKED');
        }
        return $token_info;
    }
    /*
    public function decode_token($token) {
        //to be deleted
        if (empty($token))
            return false;
        try {
            $info = JWT::decode($token, env('APP_KEY'), array('HS256'));
        }
        catch (Exception $e) {
            return false;
        }
        return $info;
    }
     */
    public function mgt_login($la_paras) {
        $item = DB::table('mcf_user_base')
            ->select([ 'is_deleted', 'username', 'uid', 'saltstring', 'password', 'role' ])
            ->where([
                'username'=>$la_paras['username'],
                'is_deleted'=> 0,
            ])
            ->first();
        if (empty($item) || empty($item->saltstring))
            throw new Exception('LOGIN_FAIL');
        $cmp_str = md5($la_paras['password'].$item->saltstring);
        if (env('APP_DEBUG', false) && $item->password == 'tobemodified') {
            $this->set_pwd($item->uid, $cmp_str);
            $item->password = $cmp_str;
        }
        if (!hash_equals($item->password, $cmp_str))
            throw new Exception('LOGIN_FAIL');
        unset($item->password);
        return $item;
    }
    public function token_login($la_paras) {
        $item = $this->check_token($la_paras['token'], false);
        try {
            $this->update_login($la_paras, $item);
        }
        catch (Exception $e) {
            Log::INFO('Update Login Failed:'.$e->getMessage());
        }
        unset($item->password);
        return $item;
    }
    public function login($la_paras) {
        $item = DB::table('account_base')
            ->leftJoin('mcf_user_base', 'mcf_user_base.account_id','=','account_base.account_id')
            ->select(DB::raw('merchant_id, mcf_user_base.account_id AS account_id, (mcf_user_base.is_deleted + account_base.is_deleted) AS is_deleted, username, uid, saltstring, password, role'))
            ->where([
                'merchant_id'=>$la_paras['merchant_id'],
                'username'=>$la_paras['username'],
            ])
            ->having('is_deleted','=',0)
            ->first();
        if (empty($item) || empty($item->saltstring))
            throw new Exception('LOGIN_FAIL');
        $cmp_str = md5($la_paras['password'].$item->saltstring);
        if (env('APP_DEBUG', false) && $item->password == 'tobemodified') {
            $this->set_pwd($item->uid, $cmp_str);
            $item->password = $cmp_str;
        }
        if (!hash_equals($item->password, $cmp_str))
            throw new Exception('LOGIN_FAIL');
        try {
            $this->update_login($la_paras, $item);
        }
        catch (Exception $e) {
            Log::INFO('Update Login Failed:'.$e->getMessage());
        }
        unset($item->password);
        return $item;
    }
    private function set_pwd($uid, $pwdHash) {
        DB::table('mcf_user_base')->where('uid',$uid)->update(['password' => $pwdHash]);
    }
    public function mgt_create_token($userObj) {
        $info = array(
            'uid'=>$userObj->uid,
            'role'=>$userObj->role,
            'username'=>$userObj->username,
            'expire'=>time()+$this->consts['token_expire_sec'],
        );
        $key = 'auth:token:'.$info['uid'];
        Redis::SETEX($key, $info['expire']-time(), $info['expire']);
        return JWT::encode($info, env('APP_KEY'));
    }
    public function create_token($userObj) {
        $info = array(
            'uid'=>$userObj->uid,
            'role'=>$userObj->role,
            'username'=>$userObj->username,
            'account_id'=>$userObj->account_id,
            'expire'=>time()+$this->consts['token_expire_sec'],
        );//should not change the attributes' name, as we directly use the return of check_token as UserObj
        $key = 'auth:token:'.$info['uid'];
        Redis::SETEX($key, $info['expire']-time(), $info['expire']);
        return JWT::encode($info, env('APP_KEY'));
    }
    private function update_login($la_paras, $userObj) {
        DB::table('mcf_user_login')
            ->updateOrInsert(['uid'=>$userObj->uid],[
                'version'=>$la_paras['version'],
                'merchant_id'=>$la_paras['merchant_id']??"from_token_login",
                'lastlogin'=>time(),//new \Datetime(),
                'lat'=>explode(',',$la_paras['latlng'])[0],
                'lng'=>explode(',',$la_paras['latlng'])[1],
            ]);
    }
}
