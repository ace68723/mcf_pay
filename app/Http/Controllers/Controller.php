<?php

namespace App\Http\Controllers;

use Log;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;
use App\Exceptions\RttException;

class Controller extends BaseController
{
    //
    public function __construct()
    {
        parent::__construct();
    }
    public function check_api_def()
    {
        $hasIgnorePara = !empty($this->consts['IGNORED_REQ_PARAS']);
        if (empty($this->consts['REQUEST_PARAS']))
            return false;
        foreach($this->consts['REQUEST_PARAS'] as $api_name=>$api_paras_def) {
            foreach($api_paras_def as $para_key=>$item) {
                if (substr($para_key, 0, 1) == "_")
                    return false;
                if ($hasIgnorePara && in_array($para_key, $this->consts['IGNORED_REQ_PARAS'])) 
                    return false;
                foreach($item as $key=>$value) {
                    if (!in_array($key, ['checker', 'required', 'default_value','converter', 'description']))
                        return false;
                    if (in_array($key, ['checker','converter'])) {
                        $tocheck = is_array($value) ? ($value[0]??null) : $value;
                        if (!is_callable($tocheck)) {
                            //throw new RttException('SYSTEM_ERROR', "invalid setting:".$api_name.":".$para_key);
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }
    /*
    public function check_parameters($input, $api_name=null) {
        $la_paras = (array)$input;
        $api_paras_def =  empty($api_name) ? $this->consts['REQUEST_PARAS'] : 
            $this->consts['REQUEST_PARAS'][$api_name];
        if (empty($api_paras_def))
            throw new \Exception('EMPTY_API_DEFINITION for '.$api_name);
        $para_count = 0;
        foreach ($api_paras_def as $key=>$item) {
            $rename = $item['rename'] ?? $key;
            if (array_key_exists($key, $la_paras)) {
                $para_count += 1;
                if (isset($item['checker']) && !$item['checker']($la_paras[$key]))
                    return false;
                    //throw new \Exception("INVALID_PARAMETER");
            }
            elseif (!empty($item['required'])) {
                return false;
                //throw new \Exception("INVALID_PARAMETER");
                //throw new \Exception("MISSING_PARAMETER:".$key);
            }
        }
        if (count($la_paras) > $para_count) {
            return false;
            //throw new \Exception("INVALID_PARAMETER");
            //throw new \Exception("HAS_UNDEFINED_PARAMETER");
        }
        return true;
    }
    public function test_self() {
        $is_str_max_len = function ($maxlen) { 
            return function ($x) use ($maxlen) { return is_string($x) && strlen($x)<=$maxlen; };
        };
        $this->consts = array();
        $this->consts['REQUEST_PARAS'] = [
            'id'=>['checker'=>'is_int', 'required'=>true,],
            'message'=>[
                'checker'=>$is_str_max_len(20),
                'required'=>false,
                'default_value'=>'msg missing',
            ],
            'datetime'=>[
                'checker'=>$is_str_max_len(20),
                'default_value'=>'now',
                'converter'=>function($x){return new DateTime($x);},
            ],
            'amount'=>['converter'=>function($x){return $x+0;}],
        ];
        assert($this->check_api_def());
        assert(false === $this->check_parameters(['id'=>'1'])); //string '1' is not int...
        assert(true === $this->check_parameters(['id'=>1])); //now is OK
        assert(false === $this->check_parameters(['id'=>'1', 'foo'=>2,])); //undefined parameters
        assert(false === $this->check_parameters(['id'=>1, 'message'=>'too long message 12345678901234567890',])); 
        assert(true === $this->check_parameters(['id'=>1, 'message'=>'short message ',])); 
        var_dump($this->preprocess_parameters(['id'=>1, 'message'=>'short message ',])); 
        var_dump($this->preprocess_parameters(['id'=>1, 'message'=>'short message ', 'datetime'=>"20171114",])); 
    }
     */

    public function api_doc_md($prefix='api/v1/merchant') {
        $output = "";
        foreach($this->consts['REQUEST_PARAS'] as $api_spec_name=>$api_spec) {
            $output .= "\n * [".$api_spec_name."](#".$api_spec_name.")";
        }
        $output .= "\n";
        foreach($this->consts['REQUEST_PARAS'] as $api_spec_name=>$api_spec) {
            $output .= "\n## ".$api_spec_name."\n\n";
            $output .= "|  Tables  |       说明       | 默认值  |\n";
            $output .= "| :------: | :------------: | :--: |\n";
            $output .= "|   URL    | /".$prefix.'/'.$api_spec_name."/ |      |\n";
            $output .= <<<doc
| HTTP请求方式 |      POST      |      |
|  是否需要登录  |       是        |      |
|  授权访问限制  |     Token      |      |
|  授权范围()  |      单次请求      |      |
|   支持格式   |  JSON (utf-8)  |      |

表头参数:

| Tables       | 类型及其范围 | 说明               | 默认值  |
| ------------ | ------ | ---------------- | ---- |
| Content-Type | string | application/json |      |
| Auth-Token | string | 登陆时返回的token |      |


Body参数:

doc;
            $table_header = <<<headerStr

| Tables             | 类型及其范围      | 必填   | 说明                            | 默认值/样例           |
| ------------------ | ----------- | ---- | ----------------------------- | ---------------- |

headerStr;
            $output .= (empty($api_spec))? "无": $table_header;
            foreach($api_spec as $para_name=>$para_spec) {
                $type_str = $para_spec['checker'] ?? null;
                if (is_array($type_str))
                    $type_str = $type_str[0] ?? null;
                if (!is_string($type_str)) {
                    $type_str = "customized";
                }
                if (substr($type_str, 0, 3) == "is_")
                    $type_str = substr($type_str, 3);
                $checker_para = is_array($para_spec['checker'])? ($para_spec['checker'][1]??null) :null;
                if (!empty($checker_para)) {
                    if (is_array($checker_para))
                        $type_str .= "(". implode(",",$checker_para) .")";
                    else
                        $type_str .= "(". $checker_para .")";
                }
                $required_str = !empty($para_spec['required']) ? "是" : "否";
                $desc_str = $para_spec['description'] ?? "----------------";
                $default_str = $para_spec['default_value'] ?? "----------------";
                $output .= "| ". $para_name . " | ". $type_str . " | " . $required_str
                    ." | ". $desc_str . " | ". $default_str. " | ";
                $output .= "\n";
            }
            $output .= "\n";
        }
        return response($output)->header('Content-Type', 'text/markdown; charset=utf-8');
    }

    private function format_caller_str($request, $caller) {
        $ret = $request->method().'|'.$request->server('SERVER_NAME').'|'.$request->path().'|'.$request->ip();
        if (!empty($caller)) {
            if (!empty($caller->account_id)) {
                $ret .= '|A_ID:'.$caller->account_id.'|';
            }
            if (!empty($caller->uid)) {
                $ret .= '|U_ID:'.$caller->uid.'|';
            }
            if (!empty($caller->username)) {
                $ret .= '|USER:'.$caller->username.'|';
            }
        }
        return $ret;
    }
    public function parse_parameters(Request $request, $api_name, $callerInfoObj = null) {
        $api_paras_def =  empty($api_name) ? $this->consts['REQUEST_PARAS'] : 
            $this->consts['REQUEST_PARAS'][$api_name];
        if (empty($api_paras_def))
            throw new RttException('SYSTEM_ERROR', 'EMPTY_API_DEFINITION for '.$api_name);
        $ret = array();
        $la_paras = $request->json()->all();
        try {
            //$payload = ($api_name != 'login') ? json_encode($la_paras) : "hide for login";
            $payload = json_encode(array_except($la_paras,['password','pwd']));
            $caller_str = $this->format_caller_str($request, $callerInfoObj);
            Log::DEBUG($caller_str . " called ".$api_name." payload_noPWD:". $payload);
        }
        catch (\Exception $e) {
            Log::DEBUG("Exception in logging parse_parameters:". $e->getMessage());
        }
        $para_count = 0;
        $resolve_func_and_call = function ($func_spec, $value) {
            $b_extra_para = is_array($func_spec) && count($func_spec)>=2;
            $func = is_array($func_spec)? $func_spec[0]: $func_spec;
            if ($b_extra_para && $func == 'is_int') {
                $new_value = is_int($value);
                if (is_int($func_spec[1][0]))
                    $new_value = $new_value && $value>=$func_spec[1][0];
                if (is_int($func_spec[1][1]))
                    $new_value = $new_value && $value<=$func_spec[1][1];
                $value = $new_value;
            }
            elseif ($b_extra_para && $func == 'is_string') {
                $new_value = is_string($value);
                if ($new_value) {
                    $len = strlen($value);
                    if (is_array($func_spec[1])) {
                        if (is_int($func_spec[1][0]))
                            $new_value = $new_value && $len>=$func_spec[1][0];
                        if (is_int($func_spec[1][1]))
                            $new_value = $new_value && $len<=$func_spec[1][1];
                    }
                    else {
                        $new_value = $new_value && $len<=$func_spec[1];
                    }
                }
                $value = $new_value;
            }
            else 
                $value = $b_extra_para ? $func($value, $func_spec[1]):$func($value);
            return $value;
        };
        foreach ($api_paras_def as $key=>$item) {
            $rename = $item['rename'] ?? $key;
            if (array_key_exists($key, $la_paras)) {
                $para_count += 1;
                if (isset($item['checker'])) {
                    if (!$resolve_func_and_call($item['checker'], $la_paras[$key]))
                        throw new RttException('INVALID_PARAMETER',
                            "check failed:".$key.", checker:".json_encode($item['checker']));
                }
                $value = $la_paras[$key];
                if (isset($item['converter'])) {
                    $value = $resolve_func_and_call($item['converter'], $value);
                }
                $ret[$rename] = $value;
            }
            elseif (!empty($item['required'])) {
                throw new RttException('INVALID_PARAMETER', " missing required:".$key);
            }
            elseif (array_key_exists('default_value', $item)) {
                $value = $item['default_value'];
                if (isset($item['converter'])) {
                    $value = $resolve_func_and_call($item['converter'], $value);
                }
                $ret[$rename] = $value;
            }
        }
        if (!empty($this->consts['IGNORED_REQ_PARAS'])) {
            foreach ($this->consts['IGNORED_REQ_PARAS'] as $ign_para) 
                $para_count += array_key_exists($ign_para, $la_paras) ? 1:0;
        }
        if (count($la_paras) > $para_count) {
            throw new RttException('INVALID_PARAMETER_NUM', "has undefined parameter. find ".count($la_paras).'parameters while only defined '.$para_count);
        }
        //Log::DEBUG("parsed(no pwd):".json_encode(array_except($ret,['password','pwd'])));
        /*
        if (in_array($api_name, ['login','mgt_login','token_login'])){//change to endWith?
            //Log::DEBUG("parsed parameters for login");
        }
        //elseif (!in_array($api_name, ['check_order_status','check_refund_status'])) { 
        else {
            //Log::DEBUG("called ".$api_name." parsed:".json_encode($ret));
        }
         */
        return $ret;
    }
    public function check_role($role, $api_name) {
        if (empty($this->consts['ALLOWED_ROLES'][$api_name]))
            throw new RttException('SYSTEM_ERROR', 'EMPTY_ROLE_CHECK_DEFINITION for '.$api_name);
        if (!in_array($role, $this->consts['ALLOWED_ROLES'][$api_name]))
            throw new RttException('PERMISSION_DENIED', [$role,$api_name]);
        return true;
    }
    public function format_success_ret($data) {
        if (env('APP_DEBUG',false))
            Log::DEBUG("SUCCESS return:".json_encode($data));
        return [
            'ev_error'=>0,
            'ev_message'=>"",
            'ev_data'=>$data,
        ];
    }
}

