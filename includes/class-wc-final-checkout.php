<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main checkout class.
 *
 * The WooCommerce checkout class handles the checkout process, collecting user data and processing the payment.
 *
 * @class    WC_Final_Checkout
 * @package  WooCommerce/Classes
 * @category Class
 * @author   WooThemes
 */
class WC_Final_Checkout {

	/**
	 * The single instance of the class.
	 *
	 * @var WC_Final_Checkout|null
	 */
	protected static $instance = null;

	/**
	 * Checkout fields are stored here.
	 *
	 * @var array|null
	 */
	protected $fields = null;

	/**
	 * Holds posted data for backwards compatibility.
	 * @var array
	 */
	protected $legacy_posted_data = array();

	/**
	 * Gets the main WC_Final_Checkout Instance.
	 *
	 * @since 2.1
	 * @static
	 * @return WC_Final_Checkout Main instance
	 */
	public static function instance() {
                
            //status must be partially paid:
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			// Hook in actions once.
			//add_action( 'woocommerce_checkout_billing', array( self::$instance, 'checkout_form_billing' ) );
			//add_action( 'woocommerce_checkout_shipping', array( self::$instance, 'checkout_form_shipping' ) );
                        add_action( 'wc_ajax_final_checkout', array( self::$instance, 'final_checkout' ) );
                        add_action( 'wp_enqueue_scripts', array( self::$instance, 'wp_enqueue_scripts' ) );
			// woocommerce_checkout_init action is ran once when the class is first constructed.
			//do_action( 'woocommerce_checkout_init', self::$instance );
		}
		return self::$instance;
	}
        
        /**
	 * Scripts and styles
	 */
	public function wp_enqueue_scripts() {
            if(!is_account_page() )
                return;
            //throw new Exception(WC_DEPOSITS_PLUGIN_URL . '/assets/js/final-checkout.js');
            wp_register_script( 'wc-deposits-final-checkout', WC_DEPOSITS_PLUGIN_URL . '/assets/js/final-checkout.js', array( 'jquery' ), WC_DEPOSITS_VERSION, true );                                
            wp_localize_script( 'wc-deposits-final-checkout', 'wc_checkout_params', array(
					'ajax_url'                  => WC()->ajax_url(),
					'wc_ajax_url'               => WC_AJAX::get_endpoint( "%%endpoint%%" ),
					'update_order_review_nonce' => wp_create_nonce( 'update-order-review' ),
					'option_guest_checkout'     => get_option( 'woocommerce_enable_guest_checkout' ),
					'checkout_url'              => WC_AJAX::get_endpoint( "final_checkout" ),
					'is_checkout'               => 1,
					'debug_mode'                => defined( 'WP_DEBUG' ) && WP_DEBUG,
					'i18n_checkout_error'       => esc_attr__( 'Error processing checkout. Please try again.', 'woocommerce' ),
				) );
            //wp_enqueue_script( 'wc-final-checkout' );
            
            wp_enqueue_script( 'wc-deposits-final-checkout' );
	}



	/**
	 * See if variable is set. Used to support legacy public variables which are no longer defined.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function __isset( $key ) {
		return in_array( $key, array(
			'enable_signup',
			'enable_guest_checkout',
			'must_create_account',
			'checkout_fields',
			'posted',
			'shipping_method',
			'payment_method',
			'customer_id',
			'shipping_methods',
		) );
	}

	/**
	 * Sets the legacy public variables for backwards compatibility.
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function __set( $key, $value ) {
		switch ( $key ) {
			case 'enable_signup' :
				$bool_value = wc_string_to_bool( $value );

				if ( $bool_value !== $this->is_registration_enabled() ) {
					remove_filter( 'woocommerce_checkout_registration_enabled', '__return_true', 0 );
					remove_filter( 'woocommerce_checkout_registration_enabled', '__return_false', 0 );
					add_filter( 'woocommerce_checkout_registration_enabled', $bool_value ? '__return_true' : '__return_false', 0 );
				}
				break;
			case 'enable_guest_checkout' :
				$bool_value = wc_string_to_bool( $value );

				if ( $bool_value === $this->is_registration_required() ) {
					remove_filter( 'woocommerce_checkout_registration_required', '__return_true', 0 );
					remove_filter( 'woocommerce_checkout_registration_required', '__return_false', 0 );
					add_filter( 'woocommerce_checkout_registration_required', $bool_value ? '__return_false' : '__return_true', 0 );
				}
				break;
			case 'checkout_fields' :
				$this->fields = $value;
				break;
			case 'shipping_methods' :
				WC()->session->set( 'chosen_shipping_methods', $value );
				break;
			case 'posted' :
				$this->legacy_posted_data = $value;
				break;
		}
	}

	/**
	 * Gets the legacy public variables for backwards compatibility.
	 *
	 * @param string $key
	 *
	 * @return array|string
	 */
	public function __get( $key ) {
		if ( in_array( $key, array( 'posted', 'shipping_method', 'payment_method' ) ) && empty( $this->legacy_posted_data ) ) {
			$this->legacy_posted_data = $this->get_posted_data();
		}
		switch ( $key ) {
			case 'enable_signup' :
				return $this->is_registration_enabled();
			case 'enable_guest_checkout' :
				return ! $this->is_registration_required();
			case 'must_create_account' :
				return $this->is_registration_required() && ! is_user_logged_in();
			case 'checkout_fields' :
				return $this->get_checkout_fields();
			case 'posted' :
				wc_doing_it_wrong( 'WC_Final_Checkout->posted', 'Use $_POST directly.', '3.0.0' );
				return $this->legacy_posted_data;
			case 'shipping_method' :
				return $this->legacy_posted_data['shipping_method'];
			case 'payment_method' :
				return $this->legacy_posted_data['payment_method'];
			case 'customer_id' :
				return apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() );
			case 'shipping_methods' :
				return (array) WC()->session->get( 'chosen_shipping_methods' );
		}
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woocommerce' ), '2.1' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woocommerce' ), '2.1' );
	}

	/**
	 * Is registration required to checkout?
	 *
	 * @since  3.0.0
	 * @return boolean
	 */
	public function is_registration_required() {
		return apply_filters( 'woocommerce_checkout_registration_required', 'yes' !== get_option( 'woocommerce_enable_guest_checkout' ) );
	}

	/**
	 * Is registration enabled on the checkout page?
	 *
	 * @since  3.0.0
	 * @return boolean
	 */
	public function is_registration_enabled() {
		return apply_filters( 'woocommerce_checkout_registration_enabled', 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout' ) );
	}

	/**
	 * Get an array of checkout fields.
	 *
	 * @param  string $fieldset to get.
	 * @return array
	 */
	public function get_checkout_fields( $fieldset = '' ) {
		if ( is_null( $this->fields ) ) {
			$this->fields = array(
				'billing'  => WC()->countries->get_address_fields( $this->get_value( 'billing_country' ), 'billing_' ),
				'shipping' => WC()->countries->get_address_fields( $this->get_value( 'shipping_country' ), 'shipping_' ),
				'account'  => array(),
				'order'    => array(
					'order_comments' => array(
						'type'        => 'textarea',
						'class'       => array( 'notes' ),
						'label'       => __( 'Order notes', 'woocommerce' ),
						'placeholder' => esc_attr__( 'Notes about your order, e.g. special notes for delivery.', 'woocommerce' ),
					),
				),
			);
			if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) {
				$this->fields['account']['account_username'] = array(
					'type'         => 'text',
					'label'        => __( 'Account username', 'woocommerce' ),
					'required'     => true,
					'placeholder'  => esc_attr__( 'Username', 'woocommerce' ),
				);
			}

			if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) {
				$this->fields['account']['account_password'] = array(
					'type'         => 'password',
					'label'        => __( 'Create account password', 'woocommerce' ),
					'required'     => true,
					'placeholder'  => esc_attr__( 'Password', 'woocommerce' ),
				);
			}

			$this->fields = apply_filters( 'woocommerce_checkout_fields', $this->fields );
		}
		if ( $fieldset ) {
			return $this->fields[ $fieldset ];
		} else {
			return $this->fields;
		}
	}


	/**
	 * Output the billing form.
	 */
	public function checkout_form_billing() {
		wc_get_template( 'checkout/form-billing.php', array( 'checkout' => $this ) );
	}

	/**
	 * Output the shipping form.
	 */
	public function checkout_form_shipping() {
		wc_get_template( 'checkout/form-shipping.php', array( 'checkout' => $this ) );
	}

	/**
	 * Create an order. Error codes:
	 * 		520 - Cannot insert order into the database.
	 * 		521 - Cannot get order after creation.
	 * 		522 - Cannot update order.
	 * 		525 - Cannot create line item.
	 * 		526 - Cannot create fee item.
	 * 		527 - Cannot create shipping item.
	 * 		528 - Cannot create tax item.
	 * 		529 - Cannot create coupon item.
	 *
	 * @throws Exception
	 * @param  $data Posted data.
	 * @return int|WP_ERROR
	 */
	public function create_order( $data ) {
		// Give plugins the opportunity to create an order themselves.
		if ( $order_id = apply_filters( 'woocommerce_create_order', null, $this ) ) {
			return $order_id;
		}

		try {
			$order_id           = absint( WC()->session->get( 'order_awaiting_payment' ) );
			$cart_hash          = md5( json_encode( wc_clean( WC()->cart->get_cart_for_session() ) ) . WC()->cart->total );
			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

			/**
			 * If there is an order pending payment, we can resume it here so
			 * long as it has not changed. If the order has changed, i.e.
			 * different items or cost, create a new order. We use a hash to
			 * detect changes which is based on cart items + order total.
			 */
			if ( $order_id && ( $order = wc_get_order( $order_id ) ) && $order->has_cart_hash( $cart_hash ) && $order->has_status( array( 'pending', 'failed' ) ) ) {
				// Action for 3rd parties.
				do_action( 'woocommerce_resume_order', $order_id );

				// Remove all items - we will re-add them later.
				$order->remove_order_items();
			} else {
				$order = new WC_Order();
			}

			foreach ( $data as $key => $value ) {
				if ( is_callable( array( $order, "set_{$key}" ) ) ) {
					$order->{"set_{$key}"}( $value );

				// Store custom fields prefixed with wither shipping_ or billing_. This is for backwards compatibility with 2.6.x.
				// TODO: Fix conditional to only include shipping/billing address fields in a smarter way without str(i)pos.
				} elseif ( ( 0 === stripos( $key, 'billing_' ) || 0 === stripos( $key, 'shipping_' ) )
					&& ! in_array( $key, array( 'shipping_method', 'shipping_total', 'shipping_tax' ) ) ) {
					$order->update_meta_data( '_' . $key, $value );
				}
			}

			$order->set_created_via( 'checkout' );
			$order->set_cart_hash( $cart_hash );
			$order->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );
			$order->set_currency( get_woocommerce_currency() );
			$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
			$order->set_customer_ip_address( WC_Geolocation::get_ip_address() );
			$order->set_customer_user_agent( wc_get_user_agent() );
			$order->set_customer_note( isset( $data['order_comments'] ) ? $data['order_comments'] : '' );
			$order->set_payment_method( isset( $available_gateways[ $data['payment_method'] ] ) ? $available_gateways[ $data['payment_method'] ]  : $data['payment_method'] );
			$order->set_shipping_total( WC()->cart->get_shipping_total() );
			$order->set_discount_total( WC()->cart->get_discount_total() );
			$order->set_discount_tax( WC()->cart->get_discount_tax() );
			$order->set_cart_tax( WC()->cart->get_cart_contents_tax() + WC()->cart->get_fee_tax() );
			$order->set_shipping_tax( WC()->cart->get_shipping_tax() );
			$order->set_total( WC()->cart->get_total( 'edit' ) );
			$this->create_order_line_items( $order, WC()->cart );
			$this->create_order_fee_lines( $order, WC()->cart );
			$this->create_order_shipping_lines( $order, WC()->session->get( 'chosen_shipping_methods' ), WC()->shipping->get_packages() );
			$this->create_order_tax_lines( $order, WC()->cart );
			$this->create_order_coupon_lines( $order, WC()->cart );

			/**
			 * Action hook to adjust order before save.
			 * @since 3.0.0
			 */
			do_action( 'woocommerce_checkout_create_order', $order, $data );

			// Save the order.
			$order_id = $order->save();

			do_action( 'woocommerce_checkout_update_order_meta', $order_id, $data );

			return $order_id;
		} catch ( Exception $e ) {
			return new WP_Error( 'checkout-error', $e->getMessage() );
		}
	}

	/**
	 * Add line items to the order.
	 *
	 * @param  WC_Order $order
	 * @param WC_Cart $cart
	 */
	public function create_order_line_items( &$order, $cart ) {
		foreach ( $cart->get_cart() as $cart_item_key => $values ) {
			/**
			 * Filter hook to get initial item object.
			 * @since 3.1.0
			 */
			$item                       = apply_filters( 'woocommerce_checkout_create_order_line_item_object', new WC_Order_Item_Product(), $cart_item_key, $values, $order );
			$product                    = $values['data'];
			$item->legacy_values        = $values; // @deprecated For legacy actions.
			$item->legacy_cart_item_key = $cart_item_key; // @deprecated For legacy actions.
			$item->set_props( array(
				'quantity'     => $values['quantity'],
				'variation'    => $values['variation'],
				'subtotal'     => $values['line_subtotal'],
				'total'        => $values['line_total'],
				'subtotal_tax' => $values['line_subtotal_tax'],
				'total_tax'    => $values['line_tax'],
				'taxes'        => $values['line_tax_data'],
			) );
			if ( $product ) {
				$item->set_props( array(
					'name'         => $product->get_name(),
					'tax_class'    => $product->get_tax_class(),
					'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
					'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
				) );
			}
			$item->set_backorder_meta();

			/**
			 * Action hook to adjust item before save.
			 * @since 3.0.0
			 */
			do_action( 'woocommerce_checkout_create_order_line_item', $item, $cart_item_key, $values, $order );

			// Add item to order and save.
			$order->add_item( $item );
		}
	}

	/**
	 * Add fees to the order.
	 *
	 * @param  WC_Order $order
	 * @param WC_Cart $cart
	 */
	public function create_order_fee_lines( &$order, $cart ) {
		foreach ( $cart->get_fees() as $fee_key => $fee ) {
			$item                 = new WC_Order_Item_Fee();
			$item->legacy_fee     = $fee; // @deprecated For legacy actions.
			$item->legacy_fee_key = $fee_key; // @deprecated For legacy actions.
			$item->set_props( array(
				'name'      => $fee->name,
				'tax_class' => $fee->taxable ? $fee->tax_class: 0,
				'amount'    => $fee->amount,
				'total'     => $fee->total,
				'total_tax' => $fee->tax,
				'taxes'     => array(
					'total' => $fee->tax_data,
				),
			) );

			/**
			 * Action hook to adjust item before save.
			 * @since 3.0.0
			 */
			do_action( 'woocommerce_checkout_create_order_fee_item', $item, $fee_key, $fee, $order );

			// Add item to order and save.
			$order->add_item( $item );
		}
	}

	/**
	 * Add shipping lines to the order.
	 *
	 * @param  WC_Order $order
	 * @param array $chosen_shipping_methods
	 * @param array $packages
	 */
	public function create_order_shipping_lines( &$order, $chosen_shipping_methods, $packages ) {
		foreach ( $packages as $package_key => $package ) {
			if ( isset( $chosen_shipping_methods[ $package_key ], $package['rates'][ $chosen_shipping_methods[ $package_key ] ] ) ) {
				/** @var WC_Shipping_Rate $shipping_rate */
				$shipping_rate            = $package['rates'][ $chosen_shipping_methods[ $package_key ] ];
				$item                     = new WC_Order_Item_Shipping();
				$item->legacy_package_key = $package_key; // @deprecated For legacy actions.
				$item->set_props( array(
					'method_title' => $shipping_rate->label,
					'method_id'    => $shipping_rate->id,
					'total'        => wc_format_decimal( $shipping_rate->cost ),
					'taxes'        => array(
						'total' => $shipping_rate->taxes,
					),
				) );

				foreach ( $shipping_rate->get_meta_data() as $key => $value ) {
					$item->add_meta_data( $key, $value, true );
				}

				/**
				 * Action hook to adjust item before save.
				 * @since 3.0.0
				 */
				do_action( 'woocommerce_checkout_create_order_shipping_item', $item, $package_key, $package, $order );

				// Add item to order and save.
				$order->add_item( $item );
			}
		}
	}

	/**
	 * Add tax lines to the order.
	 *
	 * @param  WC_Order $order
	 * @param WC_Cart $cart
	 */
	public function create_order_tax_lines( &$order, $cart ) {
		foreach ( array_keys( $cart->get_cart_contents_taxes() + $cart->get_shipping_taxes() + $cart->get_fee_taxes() ) as $tax_rate_id ) {
			if ( $tax_rate_id && apply_filters( 'woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated' ) !== $tax_rate_id ) {
				$item = new WC_Order_Item_Tax();
				$item->set_props( array(
					'rate_id'            => $tax_rate_id,
					'tax_total'          => $cart->get_tax_amount( $tax_rate_id ),
					'shipping_tax_total' => $cart->get_shipping_tax_amount( $tax_rate_id ),
					'rate_code'          => WC_Tax::get_rate_code( $tax_rate_id ),
					'label'              => WC_Tax::get_rate_label( $tax_rate_id ),
					'compound'           => WC_Tax::is_compound( $tax_rate_id ),
				) );

				/**
				 * Action hook to adjust item before save.
				 * @since 3.0.0
				 */
				do_action( 'woocommerce_checkout_create_order_tax_item', $item, $tax_rate_id, $order );

				// Add item to order and save.
				$order->add_item( $item );
			}
		}
	}

	/**
	 * Add coupon lines to the order.
	 *
	 * @param WC_Order $order
	 * @param WC_Cart  $cart
	 */
	public function create_order_coupon_lines( &$order, $cart ) {
		foreach ( $cart->get_coupons() as $code => $coupon ) {
			$item = new WC_Order_Item_Coupon();
			$item->set_props( array(
				'code'         => $code,
				'discount'     => $cart->get_coupon_discount_amount( $code ),
				'discount_tax' => $cart->get_coupon_discount_tax_amount( $code ),
			) );
			$item->add_meta_data( 'coupon_data', $coupon->get_data() );

			/**
			 * Action hook to adjust item before save.
			 * @since 3.0.0
			 */
			do_action( 'woocommerce_checkout_create_order_coupon_item', $item, $code, $coupon, $order );

			// Add item to order and save.
			$order->add_item( $item );
		}
	}

	/**
	 * See if a fieldset should be skipped.
	 *
	 * @since 3.0.0
	 *
	 * @param string $fieldset_key
	 * @param array $data
	 *
	 * @return bool
	 */
	protected function maybe_skip_fieldset( $fieldset_key, $data ) {
		if ( 'shipping' === $fieldset_key && ( ! $data['ship_to_different_address'] || ! WC()->cart->needs_shipping_address() ) ) {
			return true;
		}
		if ( 'account' === $fieldset_key && ( is_user_logged_in() || ( ! $this->is_registration_required() && empty( $data['createaccount'] ) ) ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Get posted data from the checkout form.
	 *
	 * @since  3.1.0
	 * @return array of data.
	 */
	public function get_posted_data() {
		$skipped = array();
		$data    = array(
			'terms'                              => (int) isset( $_POST['terms'] ),
			'payment_method'                     => isset( $_POST['payment_method'] ) ? wc_clean( $_POST['payment_method'] ) : '',
			'shipping_method'                    => isset( $_POST['shipping_method'] ) ? wc_clean( $_POST['shipping_method'] ) : '',
			'ship_to_different_address'          => ! empty( $_POST['ship_to_different_address'] ) && ! wc_ship_to_billing_address_only(),
			'woocommerce_checkout_update_totals' => isset( $_POST['woocommerce_checkout_update_totals'] ),
                        'order_id' => isset( $_POST['order_id'] ) ? wc_clean( $_POST['order_id'] ) : '',
		);
		foreach ( $this->get_checkout_fields() as $fieldset_key => $fieldset ) {
			if ( $this->maybe_skip_fieldset( $fieldset_key, $data ) ) {
				$skipped[] = $fieldset_key;
				continue;
			}
			foreach ( $fieldset as $key => $field ) {
				$type = sanitize_title( isset( $field['type'] ) ? $field['type'] : 'text' );

				switch ( $type ) {
					case 'checkbox' :
						$value = isset( $_POST[ $key ] ) ? 1 : '';
						break;
					case 'multiselect' :
						$value = isset( $_POST[ $key ] ) ? implode( ', ', wc_clean( $_POST[ $key ] ) ) : '';
						break;
					case 'textarea' :
						$value = isset( $_POST[ $key ] ) ? wc_sanitize_textarea( $_POST[ $key ] ) : '';
						break;
					default :
						$value = isset( $_POST[ $key ] ) ? wc_clean( $_POST[ $key ] ) : '';
						break;
				}

				$data[ $key ] = apply_filters( 'woocommerce_process_final_checkout_' . $type . '_field', apply_filters( 'woocommerce_process_final_checkout_field_' . $key, $value ) );
			}
		}

		if ( in_array( 'shipping', $skipped ) && ( WC()->cart->needs_shipping_address() || wc_ship_to_billing_address_only() ) ) {
			foreach ( $this->get_checkout_fields( 'shipping' ) as $key => $field ) {
				$data[ $key ] = isset( $data[ 'billing_' . substr( $key, 9 ) ] ) ? $data[ 'billing_' . substr( $key, 9 ) ] : '';
			}
		}

		// BW compatibility.
		$this->legacy_posted_data = $data;

		return apply_filters( 'woocommerce_checkout_posted_data', $data );
	}

	
        public function final_checkout() {
            wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
            self::process_final_checkout();            
            wp_die( 0 );
        }

	/**
	 * Validates that the checkout has enough info to proceed.
	 *
	 * @since  3.0.0
	 * @param  array $data An array of posted data.
	 * @param  WP_Error $errors
	 */
	protected function validate_final_checkout( &$data, $order, &$errors ) {

		if ( empty( $data['woocommerce_final_checkout_update_totals'] ) && empty( $data['terms'] ) && apply_filters( 'woocommerce_final_checkout_show_terms', wc_get_page_id( 'terms' ) > 0 ) ) {
			$errors->add( 'terms', __( 'You must accept our Terms &amp; Conditions.', 'woocommerce' ) );
		}

		
                $available_gateways = WC()->payment_gateways->get_available_payment_gateways();

                if ( ! isset( $available_gateways[ $data['payment_method'] ] ) ) {
                        $errors->add( 'payment', __( 'Invalid payment method.', 'woocommerce' ) );
                } else {
                        $available_gateways[ $data['payment_method'] ]->validate_fields();
                }
                
                /*if( $order->get_status() !== 'partial-payment' ) {
                    $errors->add( 'order', __( 'Invalid order status, status must be "partial payment".', 'woocommerce' ) );
                }*/
                $remaining = WC_Deposits_Order_Manager::get_instance()->get_remaining_and_paid($order)['remaining'];
                if( !( $remaining > O ) ) {
                    $errors->add( 'remaining', __( 'Remaining payment must be positive !', 'woocommerce' ) );
                }
                
                        
	}

	/**
	 * Set address field for customer.
	 *
	 * @since 3.0.7
	 * @param $field string to update
	 * @param $key
	 * @param $data array of data to get the value from
	 */
	protected function set_customer_address_fields( $field, $key, $data ) {
		if ( isset( $data[ "billing_{$field}" ] ) ) {
			WC()->customer->{"set_billing_{$field}"}( $data[ "billing_{$field}" ] );
			WC()->customer->{"set_shipping_{$field}"}( $data[ "billing_{$field}" ] );
		}
		if ( isset( $data[ "shipping_{$field}" ] ) ) {
			WC()->customer->{"set_shipping_{$field}"}( $data[ "shipping_{$field}" ] );
		}
	}

	/**
	 * Update customer and session data from the posted checkout data.
	 *
	 * @since  3.0.0
	 * @param  array $data
	 */
	protected function update_session( $data , $order ) {
		// Update both shipping and billing to the passed billing address first if set.
		
		WC()->session->set( 'chosen_payment_method', $data['payment_method'] );
                
		// Update cart totals now we have customer address.                
		WC()->cart->total = WC_Deposits_Order_Manager::get_instance()->get_remaining_and_paid($order)['remaining'];
	}


	/**
	 * Process an order that does require payment.
	 *
	 * @since  3.0.0
	 * @param  int $order_id
	 * @param  string $payment_method
	 */
	protected function process_final_checkout_payment( $order, $payment_method ) {
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! isset( $available_gateways[ $payment_method ] ) ) {
			return;
		}
                
                $total_payment = WC()->cart->total;

		// Store Order ID in session so it can be re-used after payment failure
                
		WC()->session->set( 'order_awaiting_payment', $order->get_id());

		// Process Payment
		$result = $available_gateways[ $payment_method ]->process_payment( $order->get_id() );

		// Redirect to success/confirmation/payment page
		if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
                        
			$result = apply_filters( 'woocommerce_payment_successful_result', $result, $order->get_id() );
                        //$order->update_status( 'completed', __( 'All deposit items fully paid', 'woocommerce-deposits' ) ); 
                        $order->set_customer_note( sprintf( __('customer placed final payment of %s','woocommerce-deposits'), $total_payment));
                        
                        $order->add_meta_data('_final_checkout_transaction_amount',$total_payment,true);
                        $order->add_meta_data('_final_checkout_payment_method',$payment_method,true);
                        $order->add_meta_data('_final_checkout_date', date( 'Y-m-01', current_time( 'timestamp' ) ) ,true);                       
                        $order->save();
                        // stay on order page.
                        $result['redirect'] = '';
			if ( is_ajax() ) {
				wp_send_json( $result );
			} else {
				wp_redirect( $result['redirect'] );
				exit;
			}                        
		}
                
                
	}

	/**
	 * If checkout failed during an AJAX call, send failure response.
	 */
	protected function send_ajax_failure_response() {
		if ( is_ajax() ) {
			// only print notices if not reloading the checkout, otherwise they're lost in the page reload
			if ( ! isset( WC()->session->reload_checkout ) ) {
				ob_start();
				wc_print_notices();
				$messages = ob_get_clean();
			}

			$response = array(
				'result'   => 'failure',
				'messages' => isset( $messages ) ? $messages : '',
				'refresh'  => isset( WC()->session->refresh_totals ),
				'reload'   => isset( WC()->session->reload_checkout ),
			);

			unset( WC()->session->refresh_totals, WC()->session->reload_checkout );

			wp_send_json( $response );
		}
	}

	/**
	 * Process the checkout after the confirm order button is pressed.
	 */
	public function process_final_checkout() {
		try {
			/*if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'woocommerce-process_final_checkout' ) ) {
				WC()->session->set( 'refresh_totals', true );
				throw new Exception( __( 'We were unable to process your order, please try again.', 'woocommerce' ) );
			}*/

			wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
			wc_set_time_limit( 0 );

			$errors      = new WP_Error();
			$posted_data = $this->get_posted_data();
                        $order_id = $posted_data['order_id'];
                        if ( empty( $order_id ) || !wc_get_order($order_id)  ) {			
				throw new Exception( __( 'Your final checkout must be associated with an order!', 'woocommerce' ) );
			}
                        $order = wc_get_order($order_id);
                        


			// Validate posted data and cart items before proceeding.
			$this->validate_final_checkout( $posted_data, $order ,$errors );
                        
                        
			// Update session for customer and totals.
			$this->update_session( $posted_data , $order);

			foreach ( $errors->get_error_messages() as $message ) {
				wc_add_notice( $message, 'error' );
			}

			if ( 0 === wc_notice_count( 'error' ) ) {				
                                $this->process_final_checkout_payment( $order, $posted_data['payment_method'] );
				
			}
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
		}
		$this->send_ajax_failure_response();
	}

	/**
	 * Get a posted address field after sanitization and validation.
	 *
	 * @param string $key
	 * @param string $type billing for shipping
	 * @return string
	 */
	public function get_posted_address_data( $key, $type = 'billing' ) {
		if ( 'billing' === $type || false === $this->legacy_posted_data['ship_to_different_address'] ) {
			$return = isset( $this->legacy_posted_data[ 'billing_' . $key ] ) ? $this->legacy_posted_data[ 'billing_' . $key ] : '';
		} else {
			$return = isset( $this->legacy_posted_data[ 'shipping_' . $key ] ) ? $this->legacy_posted_data[ 'shipping_' . $key ] : '';
		}
		return $return;
	}

	/**
	 * Gets the value either from the posted data, or from the users meta data.
	 *
	 * @param string $input
	 * @return string
	 */
	public function get_value( $input ) {
		if ( ! empty( $_POST[ $input ] ) ) {
			return wc_clean( $_POST[ $input ] );

		} else {

			$value = apply_filters( 'woocommerce_final_checkout_get_value', null, $input );

			if ( null !== $value ) {
				return $value;
			}

			if ( is_callable( array( WC()->customer, "get_$input" ) ) ) {
				$value = WC()->customer->{"get_$input"}() ? WC()->customer->{"get_$input"}() : null;
			} elseif ( WC()->customer->meta_exists( $input ) ) {
				$value = WC()->customer->get_meta( $input, true );
			}

			return apply_filters( 'default_final_checkout_' . $input, $value, $input );
		}
	}
}

WC_Final_Checkout::instance();