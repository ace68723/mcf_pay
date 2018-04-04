<?php

function env($name) {
    return getenv($name);
}
function auth_append_sign(&$input) {
    $secret = env('MCF_ACCOUNT_SECRET');
    $account_key= env('MCF_ACCOUNT_KEY');
    if (empty($secret) || empty($account_key)) {
        throw new \Exception("must set account_key & account_secret");
    }
    $input['account_key']=$account_key; 
    $input['sign_type']='MD5';
    $input['salt_str']=bin2hex(random_bytes(32)); 
    ksort($input);
    $string = "";
    foreach ($input as $k => $v) {
        if($k != "sign" && $v != "" && !is_array($v)){
            $string .= $k . "=" . $v . "&";
        }
    }
    $string = trim($string, "&");
    $string = md5($string."&key=".$secret);
    $input['sign'] = $string;
}

function auth_check_sign($input) {
    $secret = env('MCF_ACCOUNT_SECRET');
    if (empty($secret)) {
        throw new \Exception("must set account_key & account_secret");
    }
    if (strtoupper($input['sign_type']) != 'MD5') {
        return false;
        //throw new \Exception("sign type not supported!");
    }
    ksort($input);
    $string = "";
    foreach ($input as $k => $v) {
        if($k != "sign" && $v != "" && !is_array($v)){
            $string .= $k . "=" . $v . "&";
        }
    }
    $string = trim($string, "&");
    $string = md5($string."&key=".$secret);
    return (strtoupper($string) == strtoupper($input['sign']));
}
