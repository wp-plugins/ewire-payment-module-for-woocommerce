<?php
/*
Plugin Name: Ewire Direct Payment Gateway for WooCommerce
Plugin URI: http://ewire.dk/en/integration.aspx
Description: With Ewire Direct you can accept payments from users with a Dankort,Visa,Mastercard or Ewire users. Read more about Ewire at <a href="http://ewire.dk/En">www.ewire.dk<a/>.
Version: 1.0
Author: Ewire
Author URI: http://ewire.dk/en/
*/

add_action('plugins_loaded', 'woocommerce_ewiredirect_init', 0);

function woocommerce_ewiredirect_init() {

	/* Load the translation of the plugin. */
	load_plugin_textdomain( 'ewirepayment', false, 'woocommerce-gateway-ewiredirect/languages' );

	if (!class_exists('woocommerce_payment_gateway')) return;

	define('EWIREDIRECT_DIR', WP_PLUGIN_DIR . "/" . plugin_basename( dirname(__FILE__) ) . '/');


	class woocommerce_ewiredirect extends woocommerce_payment_gateway {

		public function __construct() {

			// Ewire values
			$this->id			= 'ewiredirect';
			$this->method_title = __('Ewire Direct', 'ewirepayment');
	        $this->icon 		= WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/logo.png';
	        $this->has_fields 	= false;		
			$this->liveurl 		= 'https://secure.ewire.dk/payment/transaction.ew';
			$this->changeurl	= 'https://secure.ewire.dk/payment/transaction_change.ew';	        	

			// Load the form fields
			$this->init_form_fields();
			
			// Load the settings.
			$this->init_settings();
		
			// Define user set variables
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
			$this->debug = $this->settings['debug'];	
		
			// Actions
			add_action( 'init', array(&$this, 'callback_page') );
			add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
			add_action('woocommerce_receipt_ewiredirect', array(&$this, 'receipt_page'));
			add_action('woocommerce_thankyou_ewiredirect', array(&$this, 'thankyou_page'));


			if ( !$this->is_valid_for_use() ) $this->enabled = false;			
		}

		/**
		* Check if this gateway is enabled and available in the users country
		*/		
		function is_valid_for_use() {
			if (!in_array(get_option('woocommerce_currency'), array('DKK', 'SEK', 'NOK'))) return false;
			return true;
		}
		public function admin_options() {

	    	?>
			<h3><?php _e('Ewire Direct Payment', 'ewirepayment'); ?></h3>
			<p><?php _e('With Ewire you can accept payments from following paymentcards: Dankort, Visa, Visa Electron, Mastercard, Maestro, Mastercard Direct. For danish customers it is also possible to deposit with netbanking. If you need help for the payment module, <a href="http://www.ewire.dk/en/module-support/ewire-payment-module-support.aspx" target="_blank">click here for a Payment module description</a>', 'ewirepayment'); ?></p>
	    	<table class="form-table">
	    	<?php
	    		if ( $this->is_valid_for_use() ) :
	    	
	    			// Generate the HTML For the settings form.
	    			$this->generate_settings_html();
	    		
	    		else :
	    		
	    			?>
	            		<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'ewirepayment' ); ?></strong>: <?php _e( 'Ewire Direct does not support your store currency.', 'ewirepayment' ); ?></p></div>
	        		<?php
	        		
	    		endif;
	    	?>
			</table><!--/.form-table-->
	    	<?php
	    } // End admin_options()		

			
		function init_form_fields() {
			
			$this->form_fields = array(
				'enabled' => array(
				'title' => __( 'Enable/Disable', 'ewirepayment' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Ewire Direct Payment', 'ewirepayment' ),
				'default' => 'yes'
				),
				'title' => array(
				'title' => __( 'Title', 'ewirepayment' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'ewirepayment' ),
				'default' => __( 'Ewire Direct Payment', 'ewirepayment' )
				),
				'description' => array(
				'title' => __( 'Description', 'ewirepayment' ),
				'type' => 'text',

				'description' => __( 'Ewire is a secure real-time online bank transfer service and card service. Simply select Ewire and complete the on-screen instructions, and your payment will be received by the merchant.', 'ewirepayment' ),
				'default' => __( 'Ewire Direct Payment', 'ewirepayment' )
				),
				'companyid' => array(
				'title' => __( 'CompanyID', 'ewirepayment' ),
				'type' => 'text',
				'description' => __( 'You can find your CompanyID on your Ewire account at the page Business.', 'ewirepayment' ),
				'default' => __( 'XXXXXXX', 'ewirepayment' )
				),
				'encryptionkey' => array(
				'title' => __( 'EncryptionKey', 'ewirepayment' ),
				'type' => 'text',
				'description' => __( 'You can find your EncryptionKey on your Ewire account at the page Business.', 'ewirepayment' ),
				'default' => __( 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX', 'ewirepayment' )
				),
				'emaillock' => array(
				'title' => __( 'Ewire Lock E-mail', 'ewirepayment' ),
				'type' => 'checkbox',
				'label' => __( 'This will lock the customer e-mail-adress through the payment process', 'ewirepayment' ),
				'default' => 'yes'
				),
				'paylanguage' => array(
				'title' => __( 'Ewire Payment Language', 'ewirepayment' ),
				'type' => 'text',
				'label' => __( 'DA for Danish and en-gb for English.', 'ewirepayment' ),
				'default' => 'DA'
				),				
				'autocapture' => array(
				'title' => __( 'Ewire Autocapture', 'ewirepayment' ),
				'type' => 'checkbox',
				'label' => __( 'This is only permitted if the product is delivered instantly, this will authorize and capture the payment.', 'ewirepayment' ),
				'default' => 'no'
				),
				'testmode' => array(
				'title' => __( 'Ewire Testmode', 'ewirepayment' ),
				'type' => 'checkbox',
				'label' => __( 'With Testmode enabled you can test your webshop.', 'ewirepayment' ),
				'default' => 'no'
				),
				'expiredays' => array(
				'title' => __( 'Expire Days', 'ewirepayment' ),
				'type' => 'text',
				'description' => __( 'Choose the number of days a payment should be active before automatically cancelled.', 'ewirepayment' ),
				'default' => '21'
				),
				'subjecttext' => array(
				'title' => __( 'Payment Subject', 'ewirepayment' ),
				'type' => 'text',
				'description' => __( 'What will be shown in the Subject field on the Netpayment before the order id.', 'ewirepayment' ),
				'default' => __( 'Order:', 'ewirepayment' )
				),
				'testmode' => array(
				'title' => __( 'Ewire Testmode', 'ewirepayment' ), 
				'type' => 'checkbox', 
				'label' => __( 'Enable Ewire testmode', 'ewirepayment' ), 
				'default' => 'yes'
				),
			);
		}

		function payment_fields()	{
			if ($this->description) echo wpautop(wptexturize($this->description));
			}

		/**
		 * Process the payment and return the result
		 **/			
		
		function generate_ewiredirect_form( $order_id ) {
			
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
			
			$order = &new woocommerce_order( $order_id );		
			
			/* Amount Conversion */
			$amountTotal = $order->order_total*100; 
			
			/* Subject */
			$ewireSubject = $this->subjecttext.''.$order->id;

			/* Currency */
			$ewireCurrency = get_option('woocommerce_currency');
			/* URLs */
			$acceptURL = $this->get_return_url( $order );
			$declineURL = $order->get_cancel_order_url();
			$callbackAcceptURL = trailingslashit(home_url()).'?ewirecall=1';
	
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
					message: "<img src=\"'.esc_url( $woocommerce->plugin_url() ).'/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Ewire to make the payment.', 'ewirepayment').'", 
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
			jQuery("#submit_ewiredirect_payment_form").click();
		');
				return '<form action="'.$this->liveurl.'" method="post" id="ewiredirect_payment_form">
						' . implode('', $ewiredirect_args_array) . '
						<input type="submit" class="button-alt" id="submit_ewiredirect_payment_form" value="'.__('Pay via Ewire Direct', 'ewirepayment').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'ewirepayment').'</a>
						</form>';
		}



		/**
		 * Process the payment and return the result
		 **/
		function process_payment( $order_id ) {
			global $woocommerce;			
			
			$order = &new woocommerce_order( $order_id );
			
		 	// Return payment page
			return array(
			'result'    => 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
			);
		}
	
	/**
	 * callback_page
	 **/
	function callback_page( $order ) {
	
		if (isset($_GET['ewirecall']) && $_GET['ewirecall'] == '1') {
	
      		$EwireOrderID = $_GET['customerOrderId'];
      		$order = new woocommerce_order( (int) $EwireOrderID );
					
			// Ewire security check
			//$postTransactionCheck = md5($this->encryptionkey . (int) $EwireOrderID . $_GET['verifiedUser'] . $_GET['paymentCardFee'] . $_GET['ewireTicket']);
			$postTransactionCheck = md5($this->encryptionkey . (int) $EwireOrderID . $_GET['ewireTicket']);
			
			if ($postTransactionCheck != $_GET['validateMD']) {
				
				// Ewire Data added
				update_post_meta( (int) $EwireOrderID, 'Ewireticket', $_GET['ewireTicket']);
				update_post_meta( (int) $EwireOrderID, 'Autocaptured', $this->autocapture);
				update_post_meta( (int) $EwireOrderID, 'Errormessage',  sprintf(__('The payment was denied because the checksum validation failed.', 'ewirepayment')));
				
				// Order is completed
	        	$order->update_status('failed', sprintf(__($orderCaptureNote, 'woothemes') ) );				

				// Send the mail
				woocommerce_mail( get_option('woocommerce_new_order_email_recipient'), sprintf(__('Payment for order #%s failed', 'ewirepayment'), $order->id), $message );					
			} else {
			
      		
      		// Check order not already completed
	        if ($order->status == 'completed') exit;
       	
	        // Payment completed
	        $orderCaptureNote = 'Ewire Netpayment was authorized and captured';
	        $orderNote = 'Ewire Netpayment was processed';
	       
			switch ($this->autocapture) {
				case 'yes':
					
					// Ewire Data added
					update_post_meta( (int) $EwireOrderID, 'Ewireticket', $_GET['ewireTicket']);
					update_post_meta( (int) $EwireOrderID, 'Autocaptured', $this->autocapture);
					
					// Order is completed
	        		$order->update_status('completed', sprintf(__($orderCaptureNote, 'ewirepayment') ) );
	        		break;
				                	
	            case 'no' :
	     			
	     			// Ewire Data added
	     			update_post_meta( (int) $EwireOrderID, 'Ewireticket', $_GET['ewireTicket']);
					update_post_meta( (int) $EwireOrderID, 'Autocaptured', $this->autocapture);

					// Order is processed
					$order->add_order_note( __($orderNote, 'ewirepayment') );
	        		$order->payment_complete();
		            break;
	            
	            default:
	            	// No action
	            break;
			}	      
			}          
		}	
	} 

	/**
	 * receipt_page
	 **/
	function receipt_page( $order ) {
		
		echo '<p>'.__('You are being redirected to the Ewire Netpayment Window.', 'ewirepayment').'</p>';
		
				echo $this->generate_ewiredirect_form( $order );
		
	}

	/**
	 * thankyou_page
	 **/
	function thankyou_page( $order ) {

		echo '<p>'.__('Thank you for your order.', 'ewirepayment').'</p>';
	}

	}
	function add_ewiredirect_gateway( $methods ) {
		$methods[] = 'woocommerce_ewiredirect'; return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'add_ewiredirect_gateway' );	
}