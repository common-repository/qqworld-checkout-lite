<?php
/**
 * Plugin Name: QQWorld Checkout Lite
 * Plugin URI: http://www.qqworld.org/product/qqworld-checkout/
 * Description: QQWorld Checkout for WooCommerce, more payment methods.
 * Version: 1.1.2
 * Author: Michael Wang
 * Author URI: http://www.qqworld.org/
 * Text Domain: qqworld-checkout
 */

namespace qqworld_checkout;

use qqworld_checkout\lib\options;
use qqworld_checkout\payments\wepay;

$GLOBALS['qqworld_checkout_payments'] = array();

define('QQWORLD_CHECKOUT_DIR', __DIR__ . DIRECTORY_SEPARATOR);
define('QQWORLD_CHECKOUT_URL', plugin_dir_url(__FILE__));

include_once QQWORLD_CHECKOUT_DIR . 'options.php';

class core {
	var $text_domain = 'qqworld-checkout';
	var $options;

	public function __construct() {
		$this->options = new options;
	}

	public function outside_language() {
		__( 'Michael Wang', $this->text_domain );
	}

	public function init() {
		add_action( 'plugins_loaded', array($this, 'load_language') );
		add_action( 'admin_menu', array($this, 'admin_menu') );
		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_init', array($this, 'register_settings') );

		add_action( 'plugins_loaded', array($this, 'load_payments') );
		// Native pay check order status
		add_action( 'wp_ajax_qqworld_checkout_check_order_status', array($this, 'check_order_status') );
		add_action( 'wp_ajax_nopriv_qqworld_checkout_check_order_status', array($this, 'check_order_status') );
	}

	public function check_order_status() {
		if (!isset($_POST['order_id'])) return;
		$order_id = $_POST['order_id'];
		$order = get_post($order_id);
		echo $order->post_status;
		exit;
	}

	//add link to plugin action links
	public function plugin_action_links( $links, $file ) {
		if ( dirname(plugin_basename( __FILE__ )) . '/qqworld-checkout-lite.php' === $file ) {
			$settings_link = '<a href="' . menu_page_url( 'qqworld-checkout', 0 ) . '">' . __( 'Settings' ) . '</a>';
			array_unshift( $links, $settings_link ); // before other links
		}
		return $links;
	}

	public function load_payments() {
		if (!class_exists('WC_Payment_Gateway')) return;

		include_once QQWORLD_CHECKOUT_DIR . 'payments' . DIRECTORY_SEPARATOR . 'wepay' . DIRECTORY_SEPARATOR . 'init.php';
		$wepay = new wepay;
		$wepay->init();
	}

	public function register_settings() {
		register_setting($this->text_domain, 'qqworld-checkout-payments');
	}

	public function load_language() {
		load_plugin_textdomain( $this->text_domain, false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	}

	public function admin_menu() {
		$page_title = __('QQWorld Checkout', $this->text_domain);
		$menu_title = __('QQWorld Checkout', $this->text_domain);
		$capability = 'administrator';
		$menu_slug = $this->text_domain;
		$function = array($this, 'admin_page');
		$icon_url = 'none';
		$settings_page = add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, $icon_url);
	}

	public function admin_enqueue_scripts() {
		wp_enqueue_style( $this->text_domain, QQWORLD_CHECKOUT_URL . 'css/style.css' );
	}

	public function is_activated($payment) {
		return is_array($this->options->activated_payments) && in_array($payment, $this->options->activated_payments);
	}

	public function admin_page() {
		global $qqworld_checkout_payments;
?>
<div class="wrap" id="qqworld-checkout-container">
	<h2><?php _e('QQWorld Checkout Lite', $this->text_domain); ?></h2>
	<p><?php _e("QQWorld Chckout Lite for WooCommerce, only WeChat scanning QR code payment on the desktop is supported, Please purchase a commercial edition to use more payment methods.", $this->text_domain); ?></p>
	<img id="banner" src="<?php echo QQWORLD_CHECKOUT_URL; ?>images/banner-772x250.png" title="<?php _e('QQWorld Checkout', $this->text_domain); ?>" />
	<form action="options.php" method="post" id="update-form">
		<?php settings_fields($this->text_domain); ?>
		<div class="icon32 icon32-qqworld-checkout-settings" id="icon-qqworld-checkout"><br></div>
		<table class="wp-list-table widefat plugins">
			<thead>
				<tr>
					<td id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-1"><?php _e('Select All'); ?></label><input id="cb-select-all-1" type="checkbox" /></td>
					<th scope="col" id="title" class="manage-column column-payment column-primary"><?php _e('Payment Methods', $this->text_domain); ?></th>
					<th scope="col" id="author" class="manage-column column-description"><?php _e('Description', $this->text_domain); ?></th>
					<th scope="col" id="edit" class="manage-column column-edit"><?php _e('Edit'); ?></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-2"><?php _e('Select All'); ?></label><input id="cb-select-all-1" type="checkbox" /></td>
					<th scope="col" id="title" class="manage-column column-payment column-primary"><?php _e('Payment Methods', $this->text_domain); ?></th>
					<th scope="col" id="author" class="manage-column column-description"><?php _e('Description', $this->text_domain); ?></th>
					<th scope="col" id="edit" class="manage-column column-edit"><?php _e('Edit'); ?></th>
				</tr>
			</tfoot>

			<tbody id="the-list">
			<?php
			if (!empty($qqworld_checkout_payments)) :
				foreach ($qqworld_checkout_payments as $payment) :
					$is_activated = $this->is_activated($payment->slug);
					$edit_link = admin_url( 'admin.php?page=wc-settings&tab=checkout&section='.strtolower($payment->method));
			?>
				<tr id="payment-<?php echo $payment->slug; ?>" class="<?php echo $is_activated ? 'active' : 'inactive'; ?>">
					<th scope="row" class="check-column">
						<label class="screen-reader-text" for="cb-select-1"><?php echo $payment->slug; ?></label>
						<input id="cb-select-1" type="checkbox" name="qqworld-checkout-payments[]" value="<?php echo $payment->slug; ?>"<?php if ($is_activated) echo ' checked'; ?> />
						<div class="locked-indicator"></div>
					</th>
					<td class="title column-title has-row-actions column-primary page-title" data-colname="<?php _e('Payment Methods', $this->text_domain); ?>">
					<?php if ($is_activated) : ?>
						<strong><a class="row-title" href="<?php echo $edit_link; ?>" title="<?php _e('Edit'); ?>&#147;<?php echo $payment->name; ?>&#148;"><?php echo $payment->name; ?></a></strong>
						<div class="row-actions">
							<span class="edit"><a href="<?php echo $edit_link; ?>" title="<?php _e('Edit this item'); ?>"><?php _e('Edit'); ?></a>
						</div>
					<?php else: ?>
						<strong><?php echo $payment->name; ?></strong>
					<?php endif; ?>
					</td>
					<td class="date column-description"><?php echo $payment->description; ?></td>
					<td class="date column-edit">
					<?php if ($is_activated) : ?>
						<a href="<?php echo $edit_link; ?>" class="button"><?php _e('Edit'); ?></a>
					<?php else: ?>
						<input type="button" class="button" value="<?php _e('Edit'); ?>" disabled />
					<?php endif; ?>
					</td>
				</tr>
			<?php
				endforeach;
			else :
			?>
				<tr><td colspan="4"><?php _e('It seems no WooCommerce plugin found.', $this->text_domain)?></td></tr>
			<?php endif; ?>
			</tbody>
		</table>
		<?php submit_button(); ?>
	</form>
	<ul id="extension-list">
	<?php
	$gateways = array(
		'wepay'	=> array(
			'mode' => 'commercial', // and 'free'
			'label' => __('Wepay', $this->text_domain),
			'description' => __('Wepay is a simple, secure and fast online payment method, customer can pay via debit card, credit card or wepay balance.', $this->text_domain),
			'featured_image' => QQWORLD_CHECKOUT_URL . 'images/wepay/featured.png'
		),
		'alipay' => array(
			'mode' => 'commercial',
			'label' => __('Alipay', $this->text_domain),
			'description' => __("Alipay is ants gold service's third-party payment platform, you can pay online, the official application to support credit card free repayment, recharge Q coins, water, gas and gas payment.", $this->text_domain),
			'featured_image' => QQWORLD_CHECKOUT_URL . 'images/alipay/featured.png'
		),
		'unionpay' => array(
			'mode' => 'commercial',
			'label' => __('UnionPay', $this->text_domain),
			'description' => __('Is a Chinese financial services corporation headquartered in Shanghai, China. It provides bank card services and a major card scheme in mainland China.', $this->text_domain),
			'featured_image' => QQWORLD_CHECKOUT_URL . 'images/unionpay/featured.png'
		),
		'youzanpay' => array(
			'mode' => 'commercial',
			'label' => __('Youzan Pay', $this->text_domain),
			'description' => __('Youzan assists businessmen in the Internet era to privatize customer assets, expand the Internet customer base, and improve business efficiency through products and services, helping businesses succeed.', $this->text_domain),
			'featured_image' => QQWORLD_CHECKOUT_URL . 'images/youzanpay/featured.png',
		),
		'passpay' => array(
			'mode' => 'commercial',
			'label' => __('PassPay', $this->text_domain),
			'description' => __('PassPay is the first to provide one-stop solution to the cash register cloud service platform.', $this->text_domain),
			'featured_image' => QQWORLD_CHECKOUT_URL . 'images/passpay/featured.png'
		)
	);
	foreach ($gateways as $slug => $gageway) :
		$buy_url = 'https://www.qqworld.org/product/'.$this->text_domain;
		extract($gageway);
	?>
		<li class="extension <?php echo $gageway['mode']; ?>">
			<?php if ($gageway['mode'] == 'commercial') : ?><aside class="attr pay"><a href="<?php echo $buy_url; ?>" target="_blank"><?php _ex('$ Buy', 'extension', $this->text_domain); ?></a></aside><?php endif; ?>
			<figure class="extension-image" title="<?php echo $label; ?>"><img src="<?php echo $featured_image; ?>"></figure>
			<h3 class="extension-label"><?php echo $label; ?></h3>
			<p class="extension-description"><?php echo $description; ?></p>
		</li>
	<?php endforeach; ?>
	</ul>
</div>
<?php
	}
}
$core = new core;
$core->init();
?>