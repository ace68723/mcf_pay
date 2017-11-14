<?php
namespace App\Providers\GenericService;

use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class GenericService{

    public $consts;
    public function __construct() {
        $this->consts = array();
    }

    public function some_func_not_relate_to_any_pay_service($la_paras) {
    }

}
