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
	public function errorMessage()
	{
		return $this->getMessage();
	}
}
