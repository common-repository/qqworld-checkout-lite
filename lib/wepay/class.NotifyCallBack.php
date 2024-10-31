<?php
class PayNotifyCallBack extends WxPayNotify {
	var $text_domain = 'qqworld-checkout';

	//查询订单
	public function Queryorder($transaction_id) {
		$input = new WxPayOrderQuery();
		$input->SetTransaction_id($transaction_id);
		$result = WxPayApi::orderQuery($input);
		// Logs
        if ( 'yes' == WC_WEPAY_DEBUG ) {
            $log = new WC_Logger();
			$log->add('wepay', 'query: ' . json_encode($result));
        }
		if(array_key_exists("return_code", $result)
			&& array_key_exists("result_code", $result)
			&& $result["return_code"] == "SUCCESS"
			&& $result["result_code"] == "SUCCESS")
		{
			if ( 'yes' == WC_WEPAY_DEBUG ) {
				$log->add('wepay', 'payment successed');
			}
			define('WC_PAYMENT_WEPAY_SUCCESSED', true);
			define('WC_PAYMENT_WEPAY_TRANSACTION_ID', $transaction_id);
			define('WC_PAYMENT_WEPAY_OUT_TRADE_NO', $result['out_trade_no']);
			return true;
		}
		return false;
	}
	
	//重写回调处理函数
	public function NotifyProcess($data, &$msg) {
		// Logs
        if ( 'yes' == WC_WEPAY_DEBUG ) {
            $log = new WC_Logger();
			$log->add('wepay', "call back:" . json_encode($data));
        }
		$notfiyOutput = array();
		
		if(!array_key_exists("transaction_id", $data)){
			$msg = __('The input parameter is not correct', $this->text_domain);
			if ( 'yes' == WC_WEPAY_DEBUG ) {
				$log->add('wepay', "payment failure:" . $msg);
			}
			return false;
		}
		//查询订单，判断订单真实性
		if(!$this->Queryorder($data["transaction_id"])){
			$msg =  __('Order query failure', $this->text_domain);
			if ( 'yes' == WC_WEPAY_DEBUG ) {
				$log->add('wepay', "payment failure:" . $msg);
			}
			return false;
		}
		return true;
	}
}
?>