<?php
namespace App\Providers\McfService;

use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class McfService{

    public $consts;
    public function __construct() {
        $this->consts = array();
    }

    public function resolve_account_id($uid) {
    }

}
