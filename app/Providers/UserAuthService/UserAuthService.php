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
        $this->consts = array();
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
    public function login($la_paras) {
        $item = DB::table('mcf_user_base')
            ->where([
                'merchant_id'=>$la_paras['merchantID'],
                'username'=>$la_paras['username'],
                'is_deleted'=> 0,
            ])
            ->first();
        if (empty($item) || empty($item->saltstring))
            throw new Exception('LOGIN_FAIL');
        $cmp_str = md5($la_paras['password'].$item->saltstring);
        //Log::DEBUG($cmp_str);
        if (!hash_equals($item->password, $cmp_str))
            throw new Exception('LOGIN_FAIL');
        try {
            $this->update_login($la_paras, $item);
        }
        catch (Exception $e) {
            Log::INFO('Update Login Failed:'.$e->getMessage());
        }
        return $item;
    }
    public function create_token($userObj) {
        $info = array(
            'uid'=>$userObj->uid,
            'role'=>$userObj->role,
            'account_id'=>$userObj->account_id,
            'expire'=>time()+$this->consts['token_expire_sec'],
        );
        return JWT::encode($info, env('APP_KEY'));
    }
    private function update_login($la_paras, $userObj) {
        DB::table('mcf_user_login')
            ->where('uid',$userObj->uid)
            ->update([
                'lastlogin'=>time(),
                'lat'=>explode(',',$la_paras['latlng'])[0],
                'lng'=>explode(',',$la_paras['latlng'])[1],
            ]);
    }
}
