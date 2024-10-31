<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define('WC_WEPAY_LIB_PATH', QQWORLD_CHECKOUT_DIR . 'lib' . DIRECTORY_SEPARATOR . 'wepay');

class WC_Wepay extends WC_Payment_Gateway {
	var $text_domain = 'qqworld-checkout';

	var $current_currency;
	var $multi_currency_enabled;
	var $supported_currencies;
	var $lib_path;
	var $charset;

	public function __construct() {

		// WPML + Multi Currency related settings
		$this->current_currency	   = get_option('woocommerce_currency');
		$this->multi_currency_enabled = in_array( 'woocommerce-multilingual/wpml-woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) && get_option( 'icl_enable_multi_currency' ) == 'yes';
		$this->supported_currencies   = array( 'RMB', 'CNY' );
		$this->lib_path			   = WC_WEPAY_LIB_PATH;

		$this->charset				= strtolower( get_bloginfo( 'charset' ) );
		if( !in_array( $this->charset, array( 'gbk', 'utf-8') ) ) {
			$this->charset = 'utf-8';
		}

		// WooCommerce required settings
		$this->id					 = 'wepay';
		$this->icon				   = apply_filters( 'woocommerce_wepay_icon', QQWORLD_CHECKOUT_URL. 'images/wepay.png' );
		$this->has_fields			 = false;
		$this->method_title		   = __( 'Wepay', $this->text_domain );
		$this->order_button_text	  = __( 'Proceed to Wepay', $this->text_domain );
		$this->notify_url			 = WC()->api_request_url( __CLASS__ );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title				  = $this->get_option( 'title' );
		$this->description			= $this->get_option( 'description' );
		$this->payment_mode			  = $this->get_option( 'payment_mode' );
		$this->AppId				  = $this->get_option( 'AppId' );
		$this->AppSecret			  = $this->get_option( 'AppSecret' );
		$this->merchantId			  = $this->get_option( 'merchantId' );
		$this->signKey				  = $this->get_option( 'signKey' );
		$this->enabled_red_packet	  = $this->get_option( 'enabled_red_packet' ) == 'yes' ? true : false;
		$this->order_title_format	 = $this->get_option( 'order_title_format' );
		$this->debug				  = $this->get_option( 'debug' );

		if (!defined('WC_WEPAY_APPID')) define('WC_WEPAY_APPID',  $this->AppId);
		if (!defined('WC_WEPAY_APPSECRET')) define('WC_WEPAY_APPSECRET',  $this->AppSecret);
		if (!defined('WC_WEPAY_MERCHANTID')) define('WC_WEPAY_MERCHANTID',  $this->merchantId);
		if (!defined('WC_WEPAY_SIGNKEY')) define('WC_WEPAY_SIGNKEY',  $this->signKey);
		if (!defined('WC_WEPAY_DEBUG')) define('WC_WEPAY_DEBUG',  $this->debug);

		// Logs
		if ( 'yes' == $this->debug ) {
			$this->log = new WC_Logger();
		}

		// Actions
		add_action( 'admin_notices', array( $this, 'requirement_checks' ) );		
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) ); // WC <= 1.6.6
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) ); // WC >= 2.0
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_' . strtolower(__CLASS__), array( $this, 'check_ipn_response' ) );

		// Display Wepay Trade No. in the backend.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_order_meta_for_admin' ) );
	}

	/**
	 * Check if this gateway is enabled and available for the selected main currency
	 *
	 * @access public
	 * @return bool
	 */
	function is_available() {

		$is_available = ( 'yes' === $this->enabled ) ? true : false;

		if ($this->multi_currency_enabled) {
			if ( !in_array( get_woocommerce_currency(), array( 'RMB', 'CNY') ) && !$this->exchange_rate) {
				$is_available = false;
			}
		} else if ( !in_array( $this->current_currency, array( 'RMB', 'CNY') ) && !$this->exchange_rate) {
			$is_available = false;
		}

		return $is_available;
	}

	/**
	 * Check if requirements are met and display notices
	 *
	 * @access public
	 * @return void
	 */
	function requirement_checks() { 
		if ( !in_array( $this->current_currency, array( 'RMB', 'CNY') ) && !$this->exchange_rate ) {
			echo '<div class="error"><p>' . sprintf( __('Wepay is enabled, but the store currency is not set to Chinese Yuan. Please <a href="%1s">set the %2s against the Chinese Yuan exchange rate</a>.', $this->text_domain ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_wepay#woocommerce_wepay_exchange_rate' ), $this->current_currency ) . '</p></div>';
		}
	}

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and account etc.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_options() {

		?>
		<h3><?php _e('Wepay', $this->text_domain); ?></h3>
		<p><?php _e('Wepay is a simple, secure and fast online payment method, customer can pay via debit card, credit card or wepay balance.', $this->text_domain); ?></p>
	   
		<table class="form-table">
			<?php
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
			?>
		</table><!--/.form-table-->
		<?php
	}
	
	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * @access public
	 * @return void
	 */
	function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'			=> __('Enable/Disable', $this->text_domain),
				'type'			=> 'checkbox',
				'label'			=> __('Enable Wepay Payment', $this->text_domain),
				'default'		=> 'no'
			),
			'title' => array(
				'title'			=> __('Title', $this->text_domain),
				'type'			=> 'text',
				'description'	=> __('This controls the title which the user sees during checkout.', $this->text_domain),
				'default'		=> __('Wepay', $this->text_domain),
				'desc_tip'		=> true,
			),
			'description' => array(
				'title'			=> __('Description', $this->text_domain),
				'type'			=> 'textarea',
				'description'	=> __('This controls the description which the user sees during checkout.', $this->text_domain),
				'default'		=> __('Pay via Wepay, use your mobile device secure and fast to pay.', $this->text_domain),
				'desc_tip'		=> true,
			),
			'AppId' => array(
				'title'			=> __('APP ID', $this->text_domain),
				'type'			=> 'text',
				'description'	=> __('Please enter the APP ID<br />If you don\'t have one, <a href="https://mp.weixin.qq.com" target="_blank">click here</a> to get.', $this->text_domain),
				'css'			=> 'width:400px'
			),
			'AppSecret' => array(
				'title'			=> __('APP Secret', $this->text_domain),
				'type'			=> 'text',
				'description'	=> sprintf(__('Please enter the APP Secret<br />If you don\'t have one, <a href="%s" target="_blank">click here</a> to get.', $this->text_domain), 'https://mp.weixin.qq.com/advanced/advanced?action=dev&t=advanced/dev&token=2005451881&lang=zh_CN'),
				'css'			=> 'width:400px'
			),
			'merchantId' => array(
				'title'			=> __('Merchant ID', $this->text_domain),
				'type'			=> 'text',
				'description'	=> __('Please enter your Wepay Merchant ID, this is needed in order to take payment.', $this->text_domain),
				'css'			=> 'width:200px',
				'desc_tip'		=> true,
			),
			'signKey' => array(
				'title'			=> __( 'API Key', $this->text_domain ),
				'type'			=> 'text',
				'description'	=> sprintf(__( 'Please enter the API Key<br />If you don\'t have one, <a href="%s" target="_blank">Click here</a> to get.', $this->text_domain ), 'https://pay.weixin.qq.com/index.php/account/api_cert'),
			),
			'order_title_format' => array(
				'title'			=> __('Preferred format for order title', $this->text_domain),
				'type'			=> 'select',
				'label'			=> __('Select your preferred order title format', $this->text_domain),
				'description'	=> __('Select the format of order title when making payment at Wepay', $this->text_domain),
				'options'		=> array(
					'customer_name' => __('Customer Full Name - #Order ID', $this->text_domain),
					'product_title' => __('Name of the first Product - #Order ID', $this->text_domain),
					'shop_name'		=> sprintf( __( '[Customer Full Name]\'s Order From %s - #Order ID', $this->text_domain ), get_bloginfo('name') )
				),
				'desc_tip'	=> true,
			),
			'enabled_red_packet' => array(
				'title'			=> __('Red Packet', $this->text_domain) . sprintf(__('(<a href="%s" target="_blank">Purchase Pro</a>)', $this->text_domain), 'http://www.qqworld.org/product/qqworld-checkout/'),
				'type'			=> 'checkbox',
				'label'			=> __('Enable/Disable WeiXin Red Packet', $this->text_domain),
				'description'	=> sprintf(__('System will automatically send a red pack to a user when the product which shared by this user has purchased.<br />If the order quantity is 2, the red pack amount will be double.<br />Needs certificates, please download by this path: WeChat business platform (pay.weixin.qq.com) --&gt; account settings --&gt; API Security --&gt; Download Certificates.<br />And put Certificates(<em>apiclient_cert.pem</em>, <em>apiclient_key.pem</em>, <em>rootca.pem</em>) into <strong>%s</strong> folder.', $this->text_domain), WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . 'wepay' . DIRECTORY_SEPARATOR ),
				'default'		=> 'no'
			),
			'red_packet_amount' => array(
				'title'			=> __('Red Packet Amount', $this->text_domain) . sprintf(__('(<a href="%s" target="_blank">Purchase Pro</a>)', $this->text_domain), 'http://www.qqworld.org/product/qqworld-checkout/'),
				'type'			=> 'number',
				'description'	=> __('The amount of each red packet. (Number: 100-20000, Unit: RMB Fen)', $this->text_domain),
				'css'			=> 'width: 80px',
				'default'		=> '100'
			),
			'send_name' => array(
				'title'			=> __('Sender Name', $this->text_domain) . sprintf(__('(<a href="%s" target="_blank">Purchase Pro</a>)', $this->text_domain), 'http://www.qqworld.org/product/qqworld-checkout/'),
				'type'			=> 'text',
				'description'	=> __('The name of red packet sender. (No more than 10 chinese characters)', $this->text_domain),
				'default'		=> ''
			),
			'wishing' => array(
				'title'			=> __('Wishing', $this->text_domain) . sprintf(__('(<a href="%s" target="_blank">Purchase Pro</a>)', $this->text_domain), 'http://www.qqworld.org/product/qqworld-checkout/'),
				'type'			=> 'textarea',
				'description'	=> __('Wishing of red packet.', $this->text_domain),
				'default'		=> __('May you be happy and prosperous', $this->text_domain),
				'desc_tip'		=> true
			),
			'act_name' => array(
				'title'			=> __('Activity Name', $this->text_domain) . sprintf(__('(<a href="%s" target="_blank">Purchase Pro</a>)', $this->text_domain), 'http://www.qqworld.org/product/qqworld-checkout/'),
				'type'			=> 'text',
				'description'	=> __('Activity name of red packet.', $this->text_domain),
				'default'		=> '',
				'desc_tip'		=> true
			),
			'remark' => array(
				'title'			=> __('remark', $this->text_domain) . sprintf(__('(<a href="%s" target="_blank">Purchase Pro</a>)', $this->text_domain), 'http://www.qqworld.org/product/qqworld-checkout/'),
				'type'			=> 'textarea',
				'description'	=> __('Activity remark of red packet.', $this->text_domain),
				'default'		=> '',
				'desc_tip'		=> true
			),
			'debug' => array(
				'title'	   => __('Debug Log', $this->text_domain),
				'type'		=> 'checkbox',
				'label'	   => __('Enable logging', $this->text_domain),
				'default'	 => 'no',
				'description' => __('Log Wepay events, such as trade status, inside <code>woocommerce/logs/wepay.txt</code>', $this->text_domain)
			)
			
		);

		// For WC2.2+
		if(  function_exists( 'wc_get_log_file_path' ) ){
			 $this->form_fields['debug']['description'] = sprintf(__('Log Wepay events, such as trade status, inside <code>%s</code>', $this->text_domain), wc_get_log_file_path( 'wepay' ) );
		}

		if (!in_array( $this->current_currency, array( 'RMB', 'CNY') )) {

			$this->form_fields['exchange_rate'] = array(
				'title'	   => __('Exchange Rate', $this->text_domain),
				'type'		=> 'text',
				'description' => sprintf(__("Please set the %s against Chinese Yuan exchange rate, eg if your currency is US Dollar, then you should enter 6.19", $this->text_domain), $this->current_currency),
				'css'		 => 'width:80px;',
				'desc_tip'	=> true,
			);
		}
	}

	/**
	 * Return page of Wepay JSAPI, show We Trade No. 
	 *
	 * @access public
	 * @param mixed Sync Notification
	 * @return void
	 */
	function thankyou_page( $order_id ) {
		if ( 'yes' == $this->debug ) {
			$this->log->add('wepay', "get payment response: " . print_r($_REQUEST, true));
		}

		require_once $this->lib_path . DIRECTORY_SEPARATOR . 'WxPay.Api.php';
		require_once $this->lib_path . DIRECTORY_SEPARATOR . 'WxPay.Notify.php';
		require_once $this->lib_path . DIRECTORY_SEPARATOR . 'class.NotifyCallBack.php';

		$order = new WC_Order( $order_id );

		if ($order->post_status == 'wc-pending') {
			$notify = new PayNotifyCallBack();
			$notify->Handle(false);

			if (defined('WC_PAYMENT_WEPAY_SUCCESSED') && WC_PAYMENT_WEPAY_SUCCESSED) {
				if (defined('WC_PAYMENT_WEPAY_OUT_TRADE_NO')) {
					$out_trade_no = WC_PAYMENT_WEPAY_OUT_TRADE_NO;

					$args = array(
						'post_type'	=> 'shop_order',
						'meta_key' => 'Wepay Out Trade No.',
						'meta_value' => $out_trade_no
					);
					$order = get_posts($args);

					if (!empty($order)) {

						if ( 'yes' == $this->debug ) {
							$this->log->add('wepay', "Successed order: " . print_r($order, true));
						}

						$order = new WC_Order( $order_id );

						update_post_meta( $order->id, '_transaction_id', wc_clean( WC_PAYMENT_WEPAY_TRANSACTION_ID ) );
						delete_post_meta( $order->id, 'Wepay Out Trade No.' );
						update_post_meta( $order->id, 'Wepay Out Trade No.', $out_trade_no ); //将之前生成的所有交易号覆盖掉

						$enabled_calc_shipping = ( get_option('woocommerce_calc_shipping') == 'no' ) ? false : true;
						if ($enabled_calc_shipping) {
							$order->update_status( 'processing', __( 'Payment received, awaiting fulfilment. ', $this->text_domain ) );
						} else {
							$order->payment_complete();
						}
					} else {
						_e('The order not exists.', $this->text_domain);
					}
				}
			} else {
				_e('Wepay Notification Request Failure', $this->text_domain);
			}
		} elseif ($order->post_status == 'wc-cancelled') {
			_e('This order has been canceled.', $this->text_domain);
		}
	}

	/**
	 * Generate the Wepay QRCode and JSAPI Pay
	 *
	 * @access public
	 * @param mixed $order_id
	 * @return string
	 */
	function generate_form( $order_id ) {
		$order = new WC_Order($order_id);

		if ($order->post_status != 'wc-pending') wp_safe_redirect( urldecode( $this->get_return_url( $order ) ) );

		require_once $this->lib_path . DIRECTORY_SEPARATOR . "WxPay.Api.php";

		echo '<p>' . __('Thank you for your order, please scan QR-Code below to pay with Wepay.', $this->text_domain) . '</p>';
		// NATIVE MODE
		require_once $this->lib_path . DIRECTORY_SEPARATOR . "WxPay.NativePay.php";

		//模式二
		/**
		 * 流程：
		 * 1、调用统一下单，取得code_url，生成二维码
		 * 2、用户扫描二维码，进行支付
		 * 3、支付完成之后，微信服务器会通知支付成功
		 * 4、在支付成功通知中需要查单确认是否真正支付成功（见：notify.php）
		 */
		$notify = new NativePay();
		$input = new WxPayUnifiedOrder();
		$input->SetBody($this->format_order_title($order));
		//$input->SetAttach("test"); //去掉即可重复生成订单
		$out_trade_no = WxPayConfig::MCHID.date("YmdHis");
		add_post_meta($order->id, 'Wepay Out Trade No.', $out_trade_no); //记录商户单号用来在接收通知消息时查询对应的订单ID
		$input->SetOut_trade_no($out_trade_no); //设置商户单号
		$input->SetTotal_fee($order->get_total()*100);
		$time = current_time('timestamp');
		$input->SetTime_start( date("YmdHis", $time) );
		$input->SetTime_expire( date("YmdHis", $time + 600) );
		//$input->SetGoods_tag("test");
		$input->SetNotify_url(urldecode( $this->notify_url ));
		$input->SetTrade_type("NATIVE");
		$input->SetProduct_id($order->id);
		$result = $notify->GetPayUrl($input);
		$url = $result["code_url"];

		if ( 'yes' == $this->debug ) {
			$this->log->add('wepay', "Placed order: " . print_r($result, true));
		}

		wc_enqueue_js("
			$('#native-pay').qrcode({
				render: 'image',
				ecLevel: 'L',
				quiet: 1,
				text: '{$url}',
				size: 500
			});
		");
		ob_start();
?>
	<script src="<?php echo QQWORLD_CHECKOUT_URL; ?>js/jquery.qrcode.min.js"></script>
	<div id="native-pay" style="width: 260px;max-width: 100%;border: 1px solid #ddd;"></div>
	<img src="<?php echo QQWORLD_CHECKOUT_URL; ?>images/wepay-readme.png" />
<script>
jQuery(function($) {
	var return_url = '<?php echo urldecode( $this->get_return_url( $order ) ); ?>';
	function check_order_status() {
		var loop;
		$.ajax({
			url: woocommerce_params.ajax_url,
			data: {
				action: 'qqworld_checkout_check_order_status',
				order_id: <?php echo $order->id; ?>
			},
			type: 'POST',
			success: function(data) {
				console.log(data)
				if (data != '0') {
					if (data == 'wc-cancelled' || data == 'wc-refunded') {
						window.location.reload();
					} else if (data == 'wc-failed') {
						alert('<?php _e('Payment Failed, please return and retry.', $this->text_domain); ?>');
						clearTimeout(loop);
					} else if (data!='wc-pending') {
						window.location.replace(return_url);
					}
				}
				loop = setTimeout(check_order_status, 3000);
			}
		});
	};
	setTimeout(check_order_status, 3000);
});
</script>
<?php
		$content = ob_get_contents();
		ob_end_clean();
		
		return $content;
	}

	/**
	 * Process the payment and return the result
	 *
	 * @access public
	 * @param  int $order_id
	 * @return array
	 */
	function process_payment( $order_id ) {

		$order = new WC_Order( $order_id );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true )
		);
	}

	/**
	 * Output for the order received page.
	 *
	 * @access public
	 * @return void
	 */
	function receipt_page( $order ) {
		echo $this->generate_form( $order );
	}

	/**
	 * Notify for Wepay, Check for Wepay Instant Payment Notification Response
	 *
	 * @access public
	 * @return void
	 */

	function check_ipn_response() {
		if ( 'yes' == $this->debug ) {
			$this->log->add('wepay', "get payment response: " . print_r($_REQUEST, true));
		}

		require_once $this->lib_path . DIRECTORY_SEPARATOR . 'WxPay.Api.php';
		require_once $this->lib_path . DIRECTORY_SEPARATOR . 'WxPay.Notify.php';
		require_once $this->lib_path . DIRECTORY_SEPARATOR . 'class.NotifyCallBack.php';

		$notify = new PayNotifyCallBack();
		$notify->Handle(false);

		if (defined('WC_PAYMENT_WEPAY_SUCCESSED') && WC_PAYMENT_WEPAY_SUCCESSED) {
			if (defined('WC_PAYMENT_WEPAY_OUT_TRADE_NO')) {
				$out_trade_no = WC_PAYMENT_WEPAY_OUT_TRADE_NO;

				$args = array(
					'post_type'	=> 'shop_order',
					'meta_key' => 'Wepay Out Trade No.',
					'meta_value' => $out_trade_no
				);
				$order = get_posts($args);

				if (!empty($order)) {

					if ( 'yes' == $this->debug ) {
						$this->log->add('wepay', "Successed order: " . print_r($order, true));
					}

					$order = new WC_Order( $order[0]->ID );

					if ($order->post_status == 'wc-pending') {

						update_post_meta( $order->id, '_transaction_id', wc_clean( WC_PAYMENT_WEPAY_TRANSACTION_ID ) );
						delete_post_meta( $order->id, 'Wepay Out Trade No.' );
						update_post_meta( $order->id, 'Wepay Out Trade No.', $out_trade_no ); //将之前生成的所有交易号覆盖掉

						$enabled_calc_shipping = ( get_option('woocommerce_calc_shipping') == 'no' ) ? false : true;
						if ($enabled_calc_shipping) {
							if ( 'yes' == $this->debug ) {
								$this->log->add('wepay', "Order status change to: processing.");
							}
							$order->update_status( 'processing', __( 'Payment received, awaiting fulfilment. ', $this->text_domain ) );
						} else {
							if ( 'yes' == $this->debug ) {
								$this->log->add('wepay', "Order status change to: complete.");
							}
							$order->payment_complete();
						}
					}
				} else {
					if ( 'yes' == $this->debug ) {
						$this->log->add('wepay', 'The order not exists.');
					}
					wp_die( __('The order not exists.', $this->text_domain) );
				}
			}
		} else {
			if ( 'yes' == $this->debug ) {
				$this->log->add('wepay', "Wepay Notification Request Failure");
			}
			wp_die( __('Wepay Notification Request Failure', $this->text_domain) );
		}
	}

	/**
	 * Allow completing order when status is processing
	 *
	 * @param array $statuses	
	 * @since 1.3.4
	 * @return array
	 */
	function valid_order_statuses( $statuses ){
		return array( 'processing' );
	}

	function complete_order_status( $new_order_status ){
		return 'completed';
	}

	/**
	 * Format order title
	 *
	 * @access public
	 * @param mixed $order
	 * @param int $length
	 * @since 1.3
	 * @return string
	 */
	function format_order_title( $order, $length = 256 ){

		$order_id = $order->id;

		if( empty($this->order_title_format) ){
			$this->order_title_format = 'customer_name';
		}

		$title = '';

		switch ( $this->order_title_format ){

			case 'customer_name' :

				if( !empty( $order->billing_last_name ) || !empty( $order->billing_first_name ) ){
					$title = $order->billing_last_name . $order->billing_first_name.' - #'.$order_id;
				}				
				break;

			case 'product_title' :

				$line_items = $order->get_items();

				if( count($line_items) > 0 ){
					foreach( $line_items as $line_item ){
						$title = $line_item['name'];
						break;
					}
				}
				if ( strlen( $title ) > $length ) {
					$title = mb_strimwidth( $title, 0, ($length-3), '...' );
				}			   

				if( count($line_items) > 1 ){
					$title .= __( ' etc.', $this->text_domain);
				}

				$title .= ' - #'.$order_id;
				break;

			case 'shop_name' :
				if( !empty( $order->billing_last_name ) || !empty( $order->billing_first_name ) ){
					$customer_name = $order->billing_last_name . $order->billing_first_name;
				}
				if( !empty($customer_name) ){
					$title = sprintf( __( "Order of %1s from %2s", $this->text_domain), $customer_name, get_bloginfo( 'name' ) );
				} else{
					$title = sprintf( __( 'Your order from %s', $this->text_domain ) , get_bloginfo( 'name' ) );
				}			   
				$title .= ' - #'.$order_id;
				break;

			default :
				break;
		}

		$title = $this->clean( $title );
		if( empty( $title ) ) $title = '#'.$order_id;

		$title = apply_filters( 'woocommerce_wepay_order_name', $title, $title, $order );

		return $title;
	}

	/**
	 * Sanitize user input
	 *
	 * @access public
	 * @param string $str
	 * @since 1.3
	 * @return string
	 */
	function clean( $str = ''){
		$clean = str_replace( array('%'), '', $str );
		$clean = sanitize_text_field( $clean );
		$clean = html_entity_decode(  $clean , ENT_NOQUOTES );
		return $clean;
	}

	/**
	 * Display Wepay Out Trade No and ransaction ID. in the backend.
	 * 
	 * @access public
	 * @param mixed $order
	 * @since 1.3
	 * @return void
	 */
	function display_order_meta_for_admin( $order ){
		$out_trade_no = get_post_meta( $order->id, 'Wepay Out Trade No.', true );
		$transaction_id = get_post_meta( $order->id, '_transaction_id', true );
		
		if( !empty($trade_no ) ){
			echo '<p><strong>' . __( 'Wepay Out Trade No.', $this->text_domain) . '</strong><br />' .$trade_no. '</p>';
			echo '<p><strong>' . __( 'Wepay Transaction ID', $this->text_domain) . '</strong><br />' .$transaction_id. '</p>';
		}
	}
}
?>