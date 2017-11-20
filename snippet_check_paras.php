<?php

class Controller {
    protected $consts;
    public function __construct() {
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
        // the following loop checks the correctness of the above meta setting.
        // in case of typos, e.g. set 'checkers' instead of 'checker'
        // You may comment it in production.
        // To support multiple api with the same controller, TODO: modify the checking codes.
        foreach($this->consts['REQUEST_PARAS'] as $item) {
            foreach($item as $key=>$value) {
                if (!in_array($key, ['checker', 'required', 'default_value','converter',]))
                    throw new \Exception("ERROR SETTING IN THIS SNIPPET");
                if (in_array($key, ['checker','converter']) && !is_callable($value))
                    throw new \Exception("ERROR SETTING IN THIS SNIPPET");
            }
        }
    }

    public function check_parameters($input, $api_name=null) {
        /*
        if (is_object($la_paras)) 
            $la_paras = get_object_vars($la_paras);
        elseif (instanceof(Request, $input)
            $la_paras = $request->json()->all();
        else
            $la_paras = $input;
        */
        $la_paras = $input;
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

    public function preprocess_parameters($input, $api_name=null) {
        /*
        if (is_object($la_paras)) 
            $la_paras = get_object_vars($la_paras);
        elseif (instanceof(Request, $input)
            $la_paras = $request->json()->all();
        else
            $la_paras = $input;
        */
        $la_paras = $input;
        $api_paras_def =  empty($api_name) ? $this->consts['REQUEST_PARAS'] : 
            $this->consts['REQUEST_PARAS'][$api_name];
        if (empty($api_paras_def))
            throw new \Exception('EMPTY_API_DEFINITION for '.$api_name);
        $ret = array();
        $para_count = 0;
        foreach ($api_paras_def as $key=>$item) {
            $rename = $item['rename'] ?? $key;
            if (array_key_exists($key, $la_paras)) {
                $para_count += 1;
                if (isset($item['checker']) && !$item['checker']($la_paras[$key]))
                    throw new \Exception("INVALID_PARAMETER");
                $value = $la_paras[$key];
                $ret[$rename] = (isset($item['converter'])) ? $item['converter']($value) : $value;
            }
            elseif (!empty($item['required'])) {
                throw new \Exception("INVALID_PARAMETER");
            }
            elseif (array_key_exists('default_value', $item)) {
                $value = $item['default_value'];
                $ret[$rename] = (isset($item['converter'])) ? $item['converter']($value) : $value;
            }
        }
        if (count($la_paras) > $para_count) {
            throw new \Exception("HAS_UNDEFINED_PARAMETER");
        }
        return $ret;
    }

    public function test_snippet() {
        assert(false === $this->check_parameters(['id'=>'1'])); //string '1' is not int...
        assert(true === $this->check_parameters(['id'=>1])); //now is OK
        assert(false === $this->check_parameters(['id'=>'1', 'foo'=>2,])); //undefined parameters
        assert(false === $this->check_parameters(['id'=>1, 'message'=>'too long message 12345678901234567890',])); 
        assert(true === $this->check_parameters(['id'=>1, 'message'=>'short message ',])); 
        var_dump($this->preprocess_parameters(['id'=>1, 'message'=>'short message ',])); 
        var_dump($this->preprocess_parameters(['id'=>1, 'message'=>'short message ', 'datetime'=>"20171114",])); 
    }
}

$obj = new Controller();
$obj->test_snippet();

