<?php
require_once 'lib/libmcfauth.php';
require_once 'lib/libmcfcurl.php';

/**
 * 流程：
 * 1、调用统一下单，取得code_url，生成二维码
 * 2、用户扫描二维码，进行支付
 * 3、支付完成之后，MCF服务器会通知支付成功
 * 4、在支付成功通知中需要查单确认是否真正支付成功
 */
$input = [];
$input['notify_url'] = "https://mcfpayapi.ca/test/echo";
$input['total_fee_in_cent'] = $_GET['cent'] ?? 1;
$input['total_fee_currency'] = 'CAD';
$input['vendor_channel'] = $_GET['channel'] ?? 'wx';
auth_append_sign($input);
$result = do_post_curl("https://mcfpayapi.ca/api/v1/web/create_order",$input);
var_dump($result);
$url1 = $result["ev_data"]["code_url"];
?>

<html>
<head>
    <meta http-equiv="content-type" content="text/html;charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1" /> 
    <title>MCF支付样例</title>
</head>
<body>
	<div style="margin-left: 10px;color:#556B2F;font-size:30px;font-weight: bolder;">Transaction QRCODE</div><br/>
	<img alt="Transaction QRCODE" src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($url1);?>" style="width:150px;height:150px;"/>
	<br/><br/><br/>
	
</body>
</html>
