<?php
/*
Plugin Name: Ewire Payment Gateway for WooCommerce
Plugin URI: http://ewire.dk/en/integration.aspx
Description: With Ewire Direct you can accept payments from users with a Dankort,Visa,Mastercard or Ewire users. Read more about Ewire at <a href="http://ewire.dk/En">www.ewire.dk<a/>.
Version: 1.1
Author: Ewire
Author URI: http://ewire.dk/en/
*/

add_action('plugins_loaded', 'wc_ewire_pay_gateway', 0);

function wc_ewire_pay_gateway() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    add_filter( 'woocommerce_payment_gateways', 'wc_ewire_gateway' );

    function wc_ewire_gateway( $methods ) {
        $methods[] = 'WC_Gateway_EwirePayment';
        return $methods;
    }

	class WC_Gateway_EwirePayment extends WC_Payment_Gateway {

		public function __construct() {
			// Ewire values
			$this->id			= 'ewire';
			$this->method_title = __('Ewire Payment', 'woothemes');
	        $this->icon 		= WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/logo.png';
	        $this->has_fields 	= false;		
			$this->liveurl 		= 'https://secure.ewire.dk/payment/transaction.ew';
			$this->changeurl	= 'https://secure.ewire.dk/payment/transaction_change.ew';	        	

			// Load the form fields
			$this->init_form_fields();
			
			// Load the settings.
			$this->init_settings();
		
			// Define user set variables
			$this->enabled = $this->settings['enabled'];
			$this->title = $this->settings['title'];
			$this->companyid = $this->settings['companyid'];
			$this->emaillock = $this->settings['emaillock'];
			$this->autocapture = $this->settings['autocapture'];
			$this->testmode = $this->settings['testmode'];
			$this->expiredays = $this->settings['expiredays'];
			$this->subjecttext = $this->settings['subjecttext'];
			$this->paylanguage = $this->settings['paylanguage'];
			$this->encryptionkey = $this->settings['encryptionkey'];
			$this->description = $this->settings['description'];

			// Actions
			add_action( 'init', array(& $this, 'check_callback') );
			add_action( 'valid_ewire_callback', array( & $this, 'successful_request' ) );
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_callback' ) );
			add_action(	'woocommerce_update_options_payment_gateways', array(& $this, 'process_admin_options', ));
			add_action(	'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action(	'woocommerce_receipt_ewire', array( $this, 'receipt'));
			add_action(	'woocommerce_thankyou_ewire', array( $this, 'thankyou'));

			if ( !$this->is_valid_for_use() ) $this->enabled = false;			
		}

		/**
		* Check if this gateway is enabled and available in the users country
		*/		
		function is_valid_for_use() {
			if (!in_array(get_option('woocommerce_currency'), array('DKK', 'SEK', 'NOK'))) return false;
			return true;
		}
		public function admin_options(){
	    	?>
			<h3><?php _e('Ewire Payment', 'woothemes'); ?></h3>
			<p><?php _e('With Ewire you can accept payments from following paymentcards: Dankort, Visa, Visa Electron, Mastercard, Maestro, Mastercard Direct. For danish customers it is also possible to deposit with netbanking. If you need help for the payment module, <a href="http://www.ewire.dk/en/module-support/ewire-payment-module-support.aspx" target="_blank">click here for a Payment module description</a>', 'woothemes'); ?></p>
	    	<table class="form-table">
	    	<?php
	    		if ( $this->is_valid_for_use() ) :
	    	
	    			// Generate the HTML For the settings form.
	    			$this->generate_settings_html();
	    		
	    		else :
	    		
	    			?>
	            		<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woothemes' ); ?></strong>: <?php _e( 'Ewire Direct does not support your store currency.', 'woothemes' ); ?></p></div>
	        		<?php
	        		
	    		endif;
	    	?>
			</table><!--/.form-table-->
	    	<p class="auto-style1">&nbsp;</p>

	    	<?php
	    } // End admin_options()		

			
		function init_form_fields() {
			global $woocommerce;
			
			$this->form_fields = array(
				'enabled' => array(
				'title' => __( 'Enable/Disable', 'woothemes' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Ewire Payment', 'woothemes' ),
				'default' => 'yes'
				),
				'title' => array(
				'title' => __( 'Title', 'woothemes' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
				'default' => __( 'Ewire Payment', 'woothemes' )
				),
				'description' => array(
				'title' => __( 'Description', 'woothemes' ),
				'type' => 'text',
				'description' => __( 'Ewire Payment description', 'woothemes' ),
				'default' => __( 'Ewire is a secure payment provider. Simply select Ewire and complete the payment', 'woothemes' )
				),
				'companyid' => array(
				'title' => __( 'CompanyID', 'woothemes' ),
				'type' => 'text',
				'description' => __( 'You can find your CompanyID on your Ewire account at the page Business.', 'woothemes' ),
				'default' => __( 'XXXXXXX', 'woothemes' )
				),
				'encryptionkey' => array(
				'title' => __( 'EncryptionKey', 'woothemes' ),
				'type' => 'text',
				'description' => __( 'You can find your EncryptionKey on your Ewire account at the page Business.', 'woothemes' ),
				'default' => __( 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX', 'woothemes' )
				),
				'emaillock' => array(
				'title' => __( 'Ewire Lock E-mail', 'woothemes' ),
				'type' => 'checkbox',
				'label' => __( 'This will lock the customer e-mail-adress through the payment process.', 'woothemes' ),
				'default' => 'yes'
				),
				'paylanguage' => array(
				'title' => __( 'Language in payment window', 'woothemes' ), 
				'type' => 'select',
				'options' => array('DA' => 'Danish', 'en-gb' => 'English'),
				'description' => __( 'DA for Danish and en-gb for English.', 'woothemes' ),
				'default' => 'DA'
				),
				'autocapture' => array(
				'title' => __( 'Ewire Autocapture', 'woothemes' ),
				'type' => 'checkbox',
				'label' => __( 'This is only permitted if the product is delivered instantly, this will authorize and capture the payment in one step', 'woothemes' ),
				'default' => 'no'
				),
				'testmode' => array(
				'title' => __( 'Ewire Testmode', 'woothemes' ), 
				'type' => 'checkbox', 
				'label' => __( 'With Testmode enabled you can test the Accept and Decline url', 'woothemes' ), 
				'default' => 'no'
				),
				'expiredays' => array(
				'title' => __( 'Expire Days', 'woothemes' ), 
				'type' => 'select',
				'options' => array('7' => '7 Days', '14' => '14 Days', '21' => '21 Days'),
				'description' => __( 'Choose the number of days a payment should be active before automatically cancelled', 'woothemes' ),
				'default' => '14'
				),
				'subjecttext' => array(
				'title' => __( 'Payment Subject', 'woothemes' ),
				'type' => 'text',
				'description' => __( 'What will be shown in the Subject field on the Netpayment before the order id.', 'woothemes' ),
				'default' => __( 'Order:', 'woothemes' )
				),
			);
		}

		function payment_fields()	{
			if ($this->description) echo wpautop(wptexturize($this->description));
			}

		/**
		 * Process the payment and return the result
		 **/			
		
		function generate_ewire_form( $order_id ) {
			
			global $woocommerce;
			
			/* Email Lock */
			switch ($this->emaillock) {
				
				case 'yes':
					$emaillock = '1';
					break;

				case 'no':
					$emaillock = '0';
					break;			
						
				default:
					// no action
					break;
			}

			/* Autocapture */
			switch ($this->autocapture) {
				
				case 'yes':
					$autocapture = '1';
					break;
				
				case 'no':
					$autocapture = '0';
					break;
				default:
					// no action					
				break;						
			}

			/* Testmode */
			switch ($this->testmode) {
				
				case 'yes':
					$testmode = '1';
					break;
				
				case 'no':
					$testmode = '0';
					break;
				default:
					// no action					
				break;						
			}
			
			$order = new WC_order( $order_id );		
			
			/* Amount Conversion */
			$amountTotal = $order->order_total*100; 
			
			/* Subject */
			$ewireSubject = $this->subjecttext.''.$order->id;

			/* Currency */
			$ewireCurrency = get_option('woocommerce_currency');
			/* URLs */
			$acceptURL = $this->get_return_url( $order );
			$declineURL = $order->get_cancel_order_url();
			$callbackAcceptURL = add_query_arg ('wooorderid', $order_id, add_query_arg ('wc-api', 'WC_Gateway_EwirePayment', $this->get_return_url( $order )));
	
				$ewire_md5	= md5($this->encryptionkey . $this->companyid . $ewireSubject . $order->billing_email . $emaillock . $order->id . $amountTotal . $ewireCurrency . $this->expiredays . '0' . $acceptURL . $declineURL . $callbackAcceptURL . $autocapture);

				$ewiredirect_args = array(
					"companyId"	=>	$this->companyid,
					"lang"	=>	$this->paylanguage,
					"buyerEmail" =>	$order->billing_email,
					"lockBuyerEmail" =>	$emaillock,
					"subject" =>	$ewireSubject,
					"customerOrderId" =>	$order->id,
					"amount" =>	$amountTotal,
					"currency" =>	get_option('woocommerce_currency'),
					"itemURL" =>	home_url(),
					"autoCapture" => $autocapture,
					"testMode" => $testmode,					
					"expireDays" => $this->expiredays,
					"annonymousPayerAllowed" => '0',
					"acceptURL" =>	$acceptURL,
					"declineURL" => $declineURL,
					"callbackAcceptURL" => $callbackAcceptURL,
					"validateMD" => $ewire_md5
				);
				$ewiredirect_args_array = array();

				foreach ($ewiredirect_args as $key => $value) {
				$ewiredirect_args_array[] = '<input type="hidden" name="'.$key.'" value="'. $value .'" />';
				}
		$woocommerce->add_inline_js('
			jQuery("body").block({ 
					message: "<img src=\"'.esc_url( $woocommerce->plugin_url() ).'/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Ewire to make the payment.', 'woothemes').'", 
					overlayCSS: 
					{ 
						background: "#fff", 
						opacity: 0.6 
					},
					css: { 
				        padding:        20, 
				        textAlign:      "center", 
				        color:          "#555", 
				        border:         "3px solid #aaa", 
				        backgroundColor:"#fff", 
				        cursor:         "wait",
				        lineHeight:		"32px"
				    } 
				});
			jQuery("#submit_ewire_payment_form").click();
		');
				return '<form action="'.$this->liveurl.'" method="post" id="ewire_payment_form">
						' . implode('', $ewiredirect_args_array) . '
						<input type="submit" class="button-alt" id="submit_ewire_payment_form" value="'.__('Pay via Ewire', 'woothemes').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'woothemes').'</a>
						</form>';
		}



		/**
		 * Process the payment and return the result
		 **/
		function process_payment( $order_id ) {
			global $woocommerce;			
			
			$order = &new WC_Order( $order_id );
			
		 	// Return payment page
			return array(
			'result'    => 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
			);
		}
	
		/**
		 * callback_page
		 **/
		function check_callback()
		{
			$_GET = stripslashes_deep($_GET);
			do_action("valid_ewire_callback", $_GET);
		}
		
		/**
		 * Successful Payment!
		 **/
		function successful_request( $posted )
		{
			$order = new WC_Order((int)$posted["wooorderid"]);
			$postTransactionCheck = md5($this->encryptionkey . $posted["wooorderid"] . $posted["ewireTicket"]);
			//$postTransactionCheck = md5($this->encryptionkey . $posted["wooorderid"] . $posted["verifiedUser"] . $posted["paymentCardFee"] . $posted["ewireTicket"]); /* Activate this if you have extended parameters added to your Ewire merchant solution */
			
			if($postTransactionCheck != $posted["validateMD"])
				{
					echo 'log';
					error_log('MD5 Checksum validation failed. Check to see if data is corrupted for OrderID:' . $posted["wooorderid"]);
					exit;
				}
			
			if($order->status !== 'completed')
				{
					switch ($this->autocapture)
						{
							case 'yes':
		
							$order->add_order_note(__('Ewire callback was a success', 'woothemes'));
							$order->payment_complete();
						
							update_post_meta((int)$posted["wooorderid"], 'Ewireticket', $_GET['ewireTicket']);
							update_post_meta((int)$posted["wooorderid"], 'Autocapture', $this->autocapture);
							
							case 'no' :
		     				$order->update_status('Processing', __('Payment is authorized. Remember to complete in Ewire admin when order is shipped', 'woothemes'));
		     				
							update_post_meta((int)$posted["wooorderid"], 'Ewireticket', $_GET['ewireTicket']);
							update_post_meta((int)$posted["wooorderid"], 'Autocapture', $this->autocapture);
		
				            break;
		         
				            default:
				            	// No action
				            break;
						}
				}
			exit;
		}
	
		/**
		 * receipt_page
		**/
		function receipt( $order ) {
			echo '<p>'.__('You are being redirected to the Ewire Netpayment Window.', 'woothemes').'</p>';
			echo $this->generate_ewire_form( $order );
		}
	
		/**
		 * thankyou_page
		 **/
		function thankyou( $order ) {
			echo '<p>'.__('Thank you for your order.', 'woothemes').'</p>';
		}
	}
}