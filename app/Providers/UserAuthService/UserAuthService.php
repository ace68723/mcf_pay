<?php
namespace App\Providers\UserAuthService;

use Log;
use \Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
//use Illuminate\Http\Request;
use Exception;

class UserAuthService{

    public $consts;
    public function __construct() {
        $this->consts['token_expire_sec'] = 1800;
    }

    public function check_token($token) {
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
        if (env('APP_DEBUG') && $item->password == 'tobemodified') {
            $this->set_pwd($item->uid, $cmp_str);
            $item->password = $cmp_str;
        }
        if (!hash_equals($item->password, $cmp_str))
            throw new Exception('LOGIN_FAIL');
        unset($item->password);
        return $item;
    }
    public function login($la_paras) {
        $item = DB::table('account_base')
            ->leftJoin('mcf_user_base', 'mcf_user_base.account_id','=','account_base.account_id')
            ->select(DB::raw('merchant_id, mcf_user_base.account_id AS account_id, (mcf_user_base.is_deleted + account_base.is_deleted) AS is_deleted, username, uid, saltstring, password, role'))
            ->where([
                'merchant_id'=>$la_paras['merchantID'],
                'username'=>$la_paras['username'],
            ])
            ->having('is_deleted','=',0)
            ->first();
        if (empty($item) || empty($item->saltstring))
            throw new Exception('LOGIN_FAIL');
        $cmp_str = md5($la_paras['password'].$item->saltstring);
        if (env('APP_DEBUG') && $item->password == 'tobemodified') {
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
        return JWT::encode($info, env('APP_KEY'));
    }
    public function create_token($userObj) {
        $info = array(
            'uid'=>$userObj->uid,
            'role'=>$userObj->role,
            'username'=>$userObj->username,
            'account_id'=>$userObj->account_id,
            'expire'=>time()+$this->consts['token_expire_sec'],
        );
        return JWT::encode($info, env('APP_KEY'));
    }
    private function update_login($la_paras, $userObj) {
        DB::table('mcf_user_login')
            ->updateOrInsert(['uid'=>$userObj->uid],[
                'version'=>$la_paras['version'],
                'merchant_id'=>$la_paras['merchantID'],
                'lastlogin'=>time(),//new \Datetime(),
                'lat'=>explode(',',$la_paras['latlng'])[0],
                'lng'=>explode(',',$la_paras['latlng'])[1],
            ]);
    }
}
