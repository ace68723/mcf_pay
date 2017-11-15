<?php

class Controller {
    protected $consts;
    public function __construct() {
        $is_str_max_len = function ($maxlen) { 
            return function ($x) use ($maxlen) { return is_string($x) && strlen($x)<=$maxlen; };
        };
        $this->consts = array();
        $this->consts['REQUEST_PARAS'] = [
            'id'=>['checker'=>'is_int', 'must_fill'=>true,],
            'message'=>[
                'checker'=>$is_str_max_len(20),
                'must_fill'=>false,
                'default_value'=>'msg missing',
            ],
            'datetime'=>[
                'checker'=>$is_str_max_len(20),
                'default_value'=>'now',
                'converter'=>function($x){return new DateTime($x);},
            ],
            'amount'=>['converter'=>function($x){return $x+0;}],
        ];
        // the following loop checks the correctness of the above meta setting. in case of typos, e.g. set 'checkers' instead of 'checker'
        // You may comment it in production.
        foreach($this->consts['REQUEST_PARAS'] as $item) {
            foreach($item as $key=>$value) {
                if (!in_array($key, ['checker', 'must_fill', 'default_value','converter',]))
                    throw new \Exception("ERROR SETTING IN USING THIS SNIPPET");
                if (in_array($key, ['checker','converter']) && !is_callable($value))
                    throw new \Exception("ERROR SETTING IN USING THIS SNIPPET");
            }
        }
    }

    public function check_parameters($la_paras) {
        /*
        if (is_object($la_paras)) {
            $la_paras = get_object_vars($la_paras);
        }
        */
        foreach ($this->consts['REQUEST_PARAS'] as $key=>$item) {
            $rename = $item['rename'] ?? $key;
            if (array_key_exists($key, $la_paras)) {
                if (isset($item['checker']) && !$item['checker']($la_paras[$key]))
                    return false;
                    //throw new \Exception("INVALID_PARAMETER");
            }
            elseif (!empty($item['must_fill'])) {
                return false;
                //throw new \Exception("INVALID_PARAMETER");
                //throw new \Exception("MISSING_PARAMETER:".$key);
            }
        }
        if (count($la_paras) > count($this->consts['REQUEST_PARAS'])) {
            return false;
            //throw new \Exception("INVALID_PARAMETER");
        }
        return true;
    }

    public function preprocess_parameters($la_paras) {
        $ret = array();
        foreach ($this->consts['REQUEST_PARAS'] as $key=>$item) {
            $rename = $item['rename'] ?? $key;
            if (array_key_exists($key, $la_paras)) {
                if (isset($item['checker']) && !$item['checker']($la_paras[$key]))
                    throw new \Exception("INVALID_PARAMETER");
                $value = $la_paras[$key];
                $ret[$rename] = (isset($item['converter'])) ? $item['converter']($value) : $value;
            }
            elseif (!empty($item['must_fill'])) {
                throw new \Exception("INVALID_PARAMETER");
            }
            elseif (array_key_exists('default_value', $item)) {
                $value = $item['default_value'];
                $ret[$rename] = (isset($item['converter'])) ? $item['converter']($value) : $value;
            }
        }
        return $ret;
    }

    public function test_snippet() {
        assert(false === $this->check_parameters(['id'=>'1'])); //string '1' is not int...
        assert(false === $this->check_parameters(['id'=>'1', 2, 3, 4, 5,])); //too many parameters
        assert(true === $this->check_parameters(['id'=>1, 2, 3, 4,])); //now is OK
        assert(false === $this->check_parameters(['id'=>1, 'message'=>'too long message 12345678901234567890', 3, 4,])); 
        assert(true === $this->check_parameters(['id'=>1, 'message'=>'short message ', 3, 4,])); 
        var_dump($this->preprocess_parameters(['id'=>1, 'message'=>'short message ', 3, 4,])); 
        var_dump($this->preprocess_parameters(['id'=>1, 'message'=>'short message ', 'datetime'=>"20171114", 4,])); 
    }
}

$obj = new Controller();
$obj->test_snippet();

