<?php

function do_post_curl($url, $payload, $timeout=30) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    //post提交方式
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    $data = curl_exec($ch);
    if($data !== false){
        $data = json_decode($data, true);
        if ($data['ev_error'] != 0) {
            print("mcf error:".$data['ev_error'].":".$data['ev_message'].":".($data['ev_context']??""));
            return false;
        }
        curl_close($ch);
        return $data;
    }
    else {
        $error = curl_errno($ch);
        curl_close($ch);
        print("curl error no: $error");
    }
    return false;
}

