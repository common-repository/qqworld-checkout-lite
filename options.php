<?php
namespace qqworld_checkout\lib;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class options {
	var $activated_payments;

	public function __construct() {
		$this->activated_payments = get_option('qqworld-checkout-payments', array());
	}
}
