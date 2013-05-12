<?php 
/*
Plugin Name: Postgiro/Bankgiro Payment gateway
Plugin URI: http://wordpress.org/extend/plugins/postgiorbankgiro-payment-method-for-woocoommerce
Description: Woocommerce payment gateway: Postgiro / Bankgiro (Swedish)
Author: developer-blog
Author URI: http://developer-blog.com
Version: 1.0.3
*/


/**
 * @package bc_payment_wc
 * @version 1.0.3
 */


add_action('plugins_loaded', 'init_bp_payment', 0);
function init_bp_payment() { 
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }
	class bp_payment_wc extends WC_Payment_Gateway{
	
		function __construct() {
			$this->id			= "bankgiro-postgiro";
			$this->icon			= "";
			$this->has_fields 	= false;
			
			$this->init_form_fields();
			$this->init_settings();
			
			$this->title 		= $this->settings['title'];
			$this->description 	= $this->settings['description'];
			$this->bankgironr	= $this->settings['bankgironr'];
            $this->postgironr	= $this->settings['postgironr'];
            if ( is_admin() ) {
                add_action( 'woocommerce_update_options_payment_gateways',              array( $this, 'process_admin_options' ) );  // WC < 2.0
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );  // WC >= 2.0
            }
                        

           
            //add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            
            add_action('woocommerce_thankyou_bankgiro-postgiro', array(&$this, 'thankyou_page'));
			add_action('woocommerce_email_before_order_table', array(&$this, 'email_instructions'), 10, 2);
		}
		
		function init_form_fields() {
			$this->form_fields = array(
				'enabled'	=> array(
					'title'		=> __( 'Enable/Disable', 'woothemes' ),
					'type'		=> 'checkbox',
					'label' 	=> __('Aktivera postgiro/bankgiro som betalningsmetod', 'woothemes' ),
					'default'	=> 'yes'
			),
			'title' => array(
			     'title' => __( 'Title', 'woothemes' ),
			     'type' => 'text',
			     'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
			     'default' => __( 'Bankgiro/Postgiro', 'woothemes' )
			 ),
			 'description' => array(
			     'title' => __( 'Customer Message', 'woothemes' ),
			     'type' => 'textarea',
			     'default'	=> 'Gör din betalning direkt till vårt bankgiro/postgiro. Använd order-ID som betalningsreferens. Din order levereras när vi mottagit din betalning.',
			     'description' => 'Ge kunden instruktioner hur man betalar via bankgiro/postgiro och låt kunden veta att ordern inte levereras innan betalningen mottagits.'
			 ),
			 'bankgironr' => array(
			      'title' => __( 'Bankgironummer', 'woothemes' ),
			      'type' => 'text',
			      'description' => __( 'Ditt bankgiornummer (Lämna tomt om du enbart vill använda postgiro)', 'woothemes' ),
			      'default' => ''
			  ),
			  'postgironr' => array(
			       'title' => __( 'Postgironummer', 'woothemes' ),
			       'type' => 'text',
			       'description' => __( 'Ditt postgironummer (Lämna tomt om du enbart vill använda bankgiro)', 'woothemes' ),
			       'default' => ''
			   )
			);
		}
		
		public function admin_options() {
		    ?>
		    <h3><?php _e('Bankgiro/Postgiro betalning', 'woothemes'); ?></h3>
		    <table class="form-table">
		    <?php
		        $this->generate_settings_html();
		    ?>
		    </table>
		    <?php
		}
		
		function payment_fields() {
		    if ($this->description) echo wpautop(wptexturize($this->description));
		}
		 
		function thankyou_page() {
			if ($this->description) echo wpautop(wptexturize($this->description));
			
			?><h2><?php _e('Betalning', 'woothemes') ?></h2><ul class="order_details bankgiro-postgiro_details"><?php
			
			$fields = array(
				'bankgironr' 	=> __('Bankgironummer', 'woothemes'), 
				'postgironr'=> __('Postgironummern', 'woothemes'),  
			);
			
			foreach ($fields as $key=>$value) :
				
			    if(!empty($this->$key)) :
			    	echo '<li class="'.$key.'">'.$value.': <strong>'.wptexturize($this->$key).'</strong></li>';
			    endif;
			endforeach;
			
			?></ul><?php
		}
		
		function email_instructions( $order, $sent_to_admin ) {
			
			if ( $sent_to_admin ) return;
			
			if ( $order->status !== 'on-hold') return;
			
			if ( $order->payment_method !== 'bankgiro-postgiro') return;
			
			if ($this->description) echo wpautop(wptexturize($this->description));
			
			?><h2><?php _e('Betalning', 'woothemes') ?></h2><ul class="order_details bankgiro-postgiro_details"><?php
			
			$fields = array(
				'bankgironr' 	=> __('Bankgironummer', 'woothemes'), 
				'postgironr'=> __('Postgironummern', 'woothemes'),  
			);
			
			foreach ($fields as $key=>$value) :
			    if(!empty($this->$key)) :
			    	echo '<li class="'.$key.'">'.$value.': <strong>'.wptexturize($this->$key).'</strong></li>';
			    endif;
			endforeach;
			
			?></ul><?php
		}
		
		
		function process_payment( $order_id ) {
		    global $woocommerce;
		 
		    $order = new WC_Order($order_id);
		 
		    // Mark as on-hold (we're awaiting the cheque)
		    $order->update_status('on-hold', __('Inväntar betalning', 'woothemes'));
		 
		 	// Reduce stock levels
		 	$order->reduce_order_stock();
		 	
		    // Remove cart
		    $woocommerce->cart->empty_cart();
		 
		    // Empty awaiting payment session
		    unset($_SESSION['order_awaiting_payment']);
		 
		    // Return thankyou redirect
		    return array(
		        'result'    => 'success',
		        'redirect'  => add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_thanks_page_id'))))
		    );
		 
		}
		
	}


	function add_bp_gateway( $methods ) {
	    $methods[] = 'bp_payment_wc'; return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'add_bp_gateway' );
}
