<?php
namespace qqworld_checkout\payments;
use qqworld_checkout\core;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class wepay extends core {
	var $slug;
	var $name;
	var $description;
	var $method;
	var $enabled_red_packet;

	var $log;

	public function init() {
		$this->register_payment();
		if ($this->is_activated($this->slug)) {
			include_once (__DIR__ . DIRECTORY_SEPARATOR . 'class.wc_wepay.php');
			add_filter('woocommerce_payment_gateways', array($this, 'woocommerce_add_gateway') );
		}
	}

	public function register_payment() {
		global $qqworld_checkout_payments;
		$this->slug = 'wepay';
		$this->name = __('Wepay', $this->text_domain);
		$this->description = __('Wepay is a simple, secure and fast online payment method, customer can pay via debit card, credit card or wepay balance.', $this->text_domain);
		$this->method = 'WC_Wepay';
		$qqworld_checkout_payments[] = $this;
	}

	public function woocommerce_add_gateway($methods) {
		$methods[] = $this->method;
		return $methods;
	}
}
?>