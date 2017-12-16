<?php

namespace App\Http\Controllers;

use Log;
use Illuminate\Http\Request;
use App\Exceptions\RttException;


/** should be dispatch controller
 */
class AdminController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->sp_mgt = app()->make('mgt_service');

        $this->consts['DEFAULT_PAGESIZE'] = 20;
        $this->consts['ALLOWED_ROLES'] = [
            'get_merchants'=>[999],
            'get_merchant_info'=>[999],
            'set_merchant_basic'=>[999],
            'set_merchant_contract'=>[999],
            'set_merchant_device'=>[999],
            'add_merchant_user'=>[999],
        ];
        foreach(['basic','user','device','channel','contract'] as $name) {
            $this->consts['GET_FUNC_CATEGORY_MAP'][$name] = [$this->sp_mgt, 'get_merchant_info_'.$name];
        }

        $this->consts['REQUEST_PARAS'] = [];
        $this->consts['REQUEST_PARAS']['get_merchants'] = [
            'page_size'=>[
                'checker'=>['is_int', [1,50]],
                'required'=>false,
                'default_value'=>$this->consts['DEFAULT_PAGESIZE'],
            ],
            'page_num'=>[
                'checker'=>['is_int', [1,'inf']],
                'required'=>false,
                'default_value'=>1,
                'description'=> 'starts from 1',
            ],
        ];
        $this->consts['REQUEST_PARAS']['get_merchant_info'] = [
            'account_id'=>[
                'checker'=>['is_int', ],
                'required'=>true,
            ],
            'category'=>[
                'checker'=>['is_string', ],
                'required'=>true,
                'description'=>'enum('.implode(',',array_keys($this->consts['GET_FUNC_CATEGORY_MAP'])).')',
            ],
            'page_num'=>[
                'checker'=>['is_int', [1,'inf']],
                'required'=>false,
                'default_value'=>1,
                'description'=> 'starts from 1',
            ],
            'page_size'=>[
                'checker'=>['is_int', [1,50]],
                'required'=>false,
                'default_value'=>$this->consts['DEFAULT_PAGESIZE'],
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['set_merchant_basic'] = [
            'account_id'=>[
                'checker'=>['is_int', ],
                'required'=>true,
            ],
            'display_name'=>[ 'checker'=>['is_string', ], ],
            'legal_name'=>[ 'checker'=>['is_string', ], ],
            'contact_person'=>[ 'checker'=>['is_string', ], ],
            'email'=>[ 'checker'=>['is_string', ], ],
            'cell'=>[ 'checker'=>['is_string', ], ],
            'address'=>[ 'checker'=>['is_string', ], ],
            'city'=>[ 'checker'=>['is_string', ], ],
            'province'=>[ 'checker'=>['is_string', ], ],
            'postal'=>[ 'checker'=>['is_string', ], ],
            'timezone'=>[ 'checker'=>['is_string', ], ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['set_merchant_contract'] = [
            'account_id'=>[
                'checker'=>['is_int', ],
                'required'=>true,
            ],
            'contract_price'=>[ 'checker'=>['is_string', ], ],
            'tip_mode'=>[ 'checker'=>['is_string', ], ],
            'remit_min_in_cent'=>[ 'checker'=>['is_int', ], ],
            'start_date'=>[ 'checker'=>['is_string', ], ],
            'end_date'=>[ 'checker'=>['is_string', ], ],
            'note'=>[ 'checker'=>['is_string', ], ],
            'bank_instit'=>[ 'checker'=>['is_string', ], ],
            'bank_transit'=>[ 'checker'=>['is_string', ], ],
            'bank_account'=>[ 'checker'=>['is_string', ], ],
            'is_deleted'=>[ 'checker'=>['is_int', ], ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['set_merchant_device'] = [
            'account_id'=>[
                'checker'=>['is_int', ],
                'required'=>true,
            ],
            'device_id'=>[
                'checker'=>['is_string', ],
                'required'=>true,
            ],
            'is_deleted'=>[ 'checker'=>['is_int', ], ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        $this->consts['REQUEST_PARAS']['add_merchant_user'] = [
            'account_id'=>[
                'checker'=>['is_int', ],
                'required'=>true,
            ],
            'username'=>[
                'checker'=>['is_string', ],
                'required'=>true,
            ],
            'role'=>[
                'checker'=>['is_int', [101,365] ],
                'required'=>true,
            ],
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters

        if (!$this->check_api_def())
            throw new RttException('SYSTEM_ERROR', "ERROR SETTING IN API SCHEMA");
    }

    public function get_merchants(Request $request){
        $userObj = $request->user('custom_mgt_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $la_paras = $this->parse_parameters($request, __FUNCTION__);
        $ret = $this->sp_mgt->get_merchants($la_paras);
        return $this->format_success_ret($ret);
    }
    public function get_merchant_info(Request $request){
        $userObj = $request->user('custom_mgt_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $la_paras = $this->parse_parameters($request, __FUNCTION__);
        $func = $this->consts['GET_FUNC_CATEGORY_MAP'][$la_paras['category']] ?? null;
        if (empty($func))
            throw new RttException('INVALID_PARAMETER', 'category');
        $ret = $func($la_paras);
        return $this->format_success_ret($ret);
    }
    public function set_merchant_basic(Request $request){
        $userObj = $request->user('custom_mgt_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $la_paras = $this->parse_parameters($request, __FUNCTION__);
        $ret = $this->sp_mgt->set_merchant_basic($la_paras);
        return $this->format_success_ret($ret);
    }
    public function set_merchant_contract(Request $request){
        $userObj = $request->user('custom_mgt_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $la_paras = $this->parse_parameters($request, __FUNCTION__);
        $ret = $this->sp_mgt->set_merchant_contract($la_paras);
        return $this->format_success_ret($ret);
    }
    public function set_merchant_device(Request $request){
        $userObj = $request->user('custom_mgt_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $la_paras = $this->parse_parameters($request, __FUNCTION__);
        $ret = $this->sp_mgt->set_merchant_device($la_paras);
        return $this->format_success_ret($ret);
    }
    public function add_merchant_user(Request $request){
        $userObj = $request->user('custom_mgt_token');
        $this->check_role($userObj->role, __FUNCTION__);
        $la_paras = $this->parse_parameters($request, __FUNCTION__);
        $ret = $this->sp_mgt->add_merchant_user($la_paras);
        return $this->format_success_ret($ret);
    }

}
