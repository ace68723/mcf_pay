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
        ];

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
                'checker'=>['is_string', ],
                'required'=>true,
            ],
            'category'=>[
                'checker'=>['is_string', ],
                'required'=>true,
                'description'=>'enum(basic,contract,channel,device,user)',
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
        ]; // parameter's name MUST NOT start with "_", which are reserved for internal populated parameters
        $this->consts['REQUEST_PARAS']['set_merchant_contract'] = [
            'account_id'=>[
                'checker'=>['is_int', ],
                'required'=>true,
            ],
            'contract_price'=>[ 'checker'=>['is_string', ], ],
            'remit_min_in_cent'=>[ 'checker'=>['is_int', ], ],
            'start_date'=>[ 'checker'=>['is_string', ], ],
            'end_date'=>[ 'checker'=>['is_string', ], ],
            'note'=>[ 'checker'=>['is_string', ], ],
            'bank_instit'=>[ 'checker'=>['is_string', ], ],
            'bank_transit'=>[ 'checker'=>['is_string', ], ],
            'bank_account'=>[ 'checker'=>['is_string', ], ],
            'is_deleted'=>[ 'checker'=>['is_int', ], ],
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

}
