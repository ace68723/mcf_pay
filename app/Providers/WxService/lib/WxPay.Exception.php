<?php
/**
 * 
 * 微信支付API异常类
 * @author widyhu
 *
 */
 /**
  * @modified xunrui@chanmao.ca
  */
use App\Exceptions\RttException;
class WxPayException extends RttException {
    public function __construct($msg) {
        parent::__construct('WX_ERROR_RAW', $msg);
    }
	public function errorMessage()
	{
		return $this->getMessage();
	}
}
