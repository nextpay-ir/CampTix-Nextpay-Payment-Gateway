<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

include_once dirname(__FILE__).'/include/nextpay_payment.php';

class CampTix_Payment_Method_Nextpay extends CampTix_Payment_Method {
	public $id = 'camptix_Nextpay';
	public $name = 'Nextpay';
	public $description = 'CampTix Nextpay Payment Gateway.';
	public $supported_currencies = array( 'IRR' );

	/**
	 * We can have an array to store our options.
	 * Use $this->get_payment_options() to retrieve them.
	 */
	protected $options = array();

	function camptix_init() {
		$this->options = array_merge( array(
			'api_key' => ''
		), $this->get_payment_options() );

		// IPN Listener
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
	}

	function payment_settings_fields() {
		$this->add_settings_field_helper( 'api_key', 'Nextpay Api Key', array( $this, 'field_text' ) );
		
	}

	function validate_options( $input ) {
		$output = $this->options;

		if ( isset( $input['api_key'] ) )
			$output['api_key'] = $input['api_key'];


		return $output;
	}

	function template_redirect() {
		if ( ! isset( $_REQUEST['tix_payment_method'] ) || 'camptix_Nextpay' != $_REQUEST['tix_payment_method'] )
			return;

		if ( isset( $_GET['tix_action'] ) ) {
			

			if ( 'payment_return' == $_GET['tix_action'] )
				$this->payment_return();

			if ( 'payment_notify' == $_GET['tix_action'] )
				$this->payment_notify();
		}
	}

	function payment_return() {
	
		global $camptix;

		$this->log( sprintf( 'Running payment_return. Request data attached.' ), null, $_REQUEST );
		$this->log( sprintf( 'Running payment_return. Server data attached.' ), null, $_SERVER );

		$payment_token = ( isset( $_REQUEST['tix_payment_token'] ) ) ? trim( $_REQUEST['tix_payment_token'] ) : '';
		if ( empty( $payment_token ) )
			return;

		$attendees = get_posts(
			array(
				'posts_per_page' => 1,
				'post_type' => 'tix_attendee',
				'post_status' => array( 'draft', 'pending', 'publish', 'cancel', 'refund', 'failed' ),
				'meta_query' => array(
					array(
						'key' => 'tix_payment_token',
						'compare' => '=',
						'value' => $payment_token,
						'type' => 'CHAR',
					),
				),
			)
		);

		if ( empty( $attendees ) )
			return;

		$attendee = reset( $attendees );

		if ( 'draft' == $attendee->post_status ) {
			return $this->payment_result( $payment_token, CampTix_Plugin::PAYMENT_STATUS_PENDING );
		} else {
			$access_token = get_post_meta( $attendee->ID, 'tix_access_token', true );
			$url = add_query_arg( array(
				'tix_action' => 'access_tickets',
				'tix_access_token' => $access_token,
			), $camptix->get_tickets_url() );

			wp_safe_redirect( esc_url_raw( $url . '#tix' ) );
			die();
		}
	}



	/**
	 * Runs when Nextpay sends an ITN signal.
	 * Verify the payload and use $this->payment_result
	 * to signal a transaction result back to CampTix.
	 */
	function payment_notify() {
		global $camptix;

		$this->log( sprintf( 'Running payment_notify. Request data attached.' ), null, $_REQUEST );
		$this->log( sprintf( 'Running payment_notify. Server data attached.' ), null, $_SERVER );

		$payment_token = ( isset( $_REQUEST['tix_payment_token'] ) ) ? trim( $_REQUEST['tix_payment_token'] ) : '';

		$payload = stripslashes_deep( $_REQUEST);

		$data_string = '';
		$data_array = array();

		// Dump the submitted variables and calculate security signature
		foreach ( $payload as $key => $val ) {
			if ( $key != 'signature' ) {
				$data_string .= $key .'='. urlencode( $val ) .'&';
				$data_array[$key] = $val;
			}
		}
		
		$data_string = substr( $data_string, 0, -1 );
		$signature = md5( $data_string );

		$pfError = false;
		if ( 0 != strcmp( $signature, $payload['signature'] ) ) {
			$pfError = true;
			$this->log( sprintf( 'ITN request failed, signature mismatch: %s', $payload ) );
		}
		
		$order = $this->get_order( $payment_token );


		$parameters = array(
                        'api_key'	 => $this->options['api_key'],
                        'trans_id' 	 => $payload['trans_id'],
                        'order_id' 	 => $order['attendee_id'],
                        'amount'	 => $order['total'] / 10
                    );

        $nextpay = new Nextpay_Payment();
        $result = $nextpay->verify_request($parameters);

		if($result == 0){
			$this->payment_result( $payment_token, CampTix_Plugin::PAYMENT_STATUS_COMPLETED );
		} else {
			$this->payment_result( $payment_token, CampTix_Plugin::PAYMENT_STATUS_FAILED );
		}


		$access_token = get_post_meta( $attendee->ID, 'tix_access_token', true );
			$url = add_query_arg( array(
				'tix_action' => 'access_tickets',
				'tix_access_token' => $access_token,
			), $camptix->get_tickets_url() );

			wp_safe_redirect( esc_url_raw( $url . '#tix' ) );
			die();

	}

	public function payment_checkout( $payment_token ) {

		if ( ! $payment_token || empty( $payment_token ) )
			return false;

		if ( ! in_array( $this->camptix_options['currency'], $this->supported_currencies ) )
			die( __( 'The selected currency is not supported by this payment method.', 'camptix' ) );

		$return_url = add_query_arg( array(
			'tix_action' => 'payment_return',
			'tix_payment_token' => $payment_token,
			'tix_payment_method' => 'camptix_Nextpay',
		), $this->get_tickets_url() );



		$notify_url = add_query_arg( array(
			'tix_action' => 'payment_notify',
			'tix_payment_token' => $payment_token,
			'tix_payment_method' => 'camptix_Nextpay',
		), $this->get_tickets_url() );

		$order = $this->get_order( $payment_token );

		$payload = array(
			'api_key' => $this->options['api_key'],
			'return_url' => $return_url,
			'notify_url' => $notify_url,

			// Item details
			'm_payment_id' => $payment_token,
			'amount' => $order['total'] / 10 ,
			'item_name' => get_bloginfo( 'name' ) .' purchase, Order ' . $payment_token,
			'item_description' => sprintf( __( 'سفارش جدید  %s', 'woothemes' ), get_bloginfo( 'name' ) ),
            'order_id' => $order['attendee_id'],

			// Custom strings
			'custom_str1' => $payment_token,
			'source' => 'WordCamp-CampTix-Plugin'
		);


		$Nextpay_args_array = array();
		foreach ( $payload as $key => $value ) {
			$Nextpay_args_array[] = '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
		}
		
		$parameters = array (
	        "api_key"=> $payload['api_key'],
	        "order_id"=> $payload['order_id'],
	        "amount"=>$payload['amount'],
	        "callback_uri"=>$payload['notify_url']
	        );

        $nextpay = new Nextpay_Payment($parameters);

	    $result = $nextpay->token();

	//Redirect to URL You can do it also by creating a form
	if(intval($result->code) == -1)
	{
		Header('Location: http://api.nextpay.org/gateway/payment/'.$result->trans_id);
	} else {
		echo ' شماره خطا: '.$result->code.'<br />';
		echo '<br>'.$nextpay->code_error(intval($result->code));
	}

		echo '<div id="tix">
					<form action="' . $url . '" method="post" id="Nextpay_payment_form">
						' . implode( '', $Nextpay_args_array ) . '
						<script type="text/javascript">
							document.getElementById("Nextpay_payment_form").submit();
						</script>
					</form>
				</div>';
		return;
	}

}
?>