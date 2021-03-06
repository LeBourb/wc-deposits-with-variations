<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Deposits_Order_Manager class
 */
class WC_Deposits_Order_Manager {

	/** @var object Class Instance */
	private static $instance;

	/**
	 * Get the class instance
	 */
	public static function get_instance() {
		return null === self::$instance ? ( self::$instance = new self ) : self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_status' ), 9 );
		add_filter( 'wc_order_statuses', array( $this, 'add_order_statuses' ) );
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'woocommerce_valid_order_statuses_for_payment_complete' ) );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'woocommerce_payment_complete_order_status' ), 10, 2 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'process_deposits_in_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'process_deposits_in_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'process_deposits_in_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_partial-payment', array( $this, 'process_deposits_in_order' ), 10, 1 );
		add_filter( 'woocommerce_attribute_label', array( $this, 'woocommerce_attribute_label' ), 10, 2 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'woocommerce_hidden_order_itemmeta' ) );
		add_action( 'woocommerce_before_order_itemmeta', array( $this, 'woocommerce_before_order_itemmeta' ), 10, 3 );
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'woocommerce_after_order_itemmeta' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'order_action_handler' ) );
		add_action( 'woocommerce_payment_complete', array( $this, 'payment_complete_handler' ) );
                
                
                //add_action('woocommerce_order_after_calculate_totals' , array( $this, 'after_calculate_totals' ), 10, 2);

		// View orders
		add_filter( 'woocommerce_my_account_my_orders_query', array( $this, 'woocommerce_my_account_my_orders_query' ) );
		add_filter( 'woocommerce_order_item_name', array( $this, 'woocommerce_order_item_name' ), 10, 2 );
                
                
		add_action( 'woocommerce_order_item_meta_end', array( $this, 'woocommerce_order_item_meta_end' ), 10, 3 );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'woocommerce_get_order_item_totals' ), 10, 2 );
		add_filter( 'request', array( $this, 'request_query' ) );
		//add_action( 'woocommerce_ajax_add_order_item_meta', array( $this, 'ajax_add_order_item_meta' ), 10, 2 ); 

		// Stock management
		add_filter( 'woocommerce_payment_complete_reduce_order_stock', array( $this, 'allow_reduce_order_stock' ), 10, 2 );
		add_filter( 'woocommerce_can_reduce_order_stock', array( $this, 'allow_reduce_order_stock' ), 10, 2 );
                
                add_filter( 'woocommerce_my_account_my_orders_actions',  array( $this, 'woocommerce_my_account_my_orders_actions' ), 10, 2 );
                
              add_action( 'woocommerce_checkout_order_processed', array( $this, 'checkout_order_processed' ), 10, 2 );
              

            add_action('woocommerce_view_order',  array( $this, 'woocommerce_remaining_checkout' ), 10, 2 );
            add_action( 'wp_loaded', array( $this, 'checkout_remaining_action' ), 80 );
            
            //admin
            add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'admin_order_totals_after_total' ) ,40 , 2 ); 
            
	}
        
        /*public function after_calculate_totals ($and_taxes, $order) {
            print_r($order->get_total());
            throw new Exception('Division par zéro.');
            return;
        }*/
        
        public function admin_order_totals_after_total ($order_id) {
            $order = wc_get_order($order_id);
            //status must be partially paid:
            if( $order->get_status() === 'partial-payment' ) {
                $remaining_and_paid =   $this->get_remaining_and_paid($order);
                ?>
                <tr>
			<td class="label"><?php _e( 'Paid', 'woocommerce' ); ?>:</td>
			<td width="1%"></td>
			<td class="total">
				<?php echo wc_price($remaining_and_paid['paid']); ?>
			</td>
		</tr>
                <tr>
			<td class="label"><?php _e( 'Remaining', 'woocommerce' ); ?>:</td>
			<td width="1%"></td>
			<td class="total">
				<?php echo wc_price($remaining_and_paid['remaining']); ?>
			</td>
		</tr>
            <?php 
            }
            
        }
        public function checkout_remaining_action () {
            if ( isset( $_POST['woocommerce_checkout_place_final_payment'] ) ) {
                nocache_headers();

                wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

                //WC()->checkout()->process_checkout();
                WC()->checkout()->process_order_payment( $order_id, $posted_data['payment_method'] );
                $order = wc_get_order( $order_id );

		// Mark as on-hold (we're awaiting the payment)
		$order->update_status( 'on-hold', __( 'Awaiting BACS payment', 'woocommerce' ) );
                //process_payment()
            }
        }
        
        
        function woocommerce_remaining_checkout($order_id)
        {
            $order = wc_get_order($order_id);
            $remaining_and_paid =   $this->get_remaining_and_paid($order);
            //status must be partially paid:
            $final_checkout_paid = $order->get_meta('_final_checkout_transaction_amount',true);
            $final_checkout_method = $order->get_meta('_final_checkout_payment_method',true);
            $final_checkout_date = $order->get_meta('_final_checkout_date',true);
            $remaining = $remaining_and_paid['remaining'];
            
            if( $final_checkout_paid ==  $remaining ) {
                echo '<section id="checkout-remaining">';
                echo '<h2 class="woocommerce-order-remaining_checkout">Paid</h2>';
                echo '<tr><th scope="row">You have paid:</th><td>' . wc_price($remaining_and_paid['remaining']) . '</td></tr>';
                echo '<tr><th scope="row">Your payment method:</th><td>' . $final_checkout_method . '</td></tr>';
                echo '<tr><th scope="row">Your payment date:</th><td>' . $final_checkout_date . '</td></tr>';
                
            }            
            else if ($remaining_and_paid['remaining'] > 0) {
                $order_button_text = 'Place Final Payment';
                echo '<section id="checkout-remaining">';
                echo '<h2 class="woocommerce-order-remaining_checkout">残額のお支払い</h2>';
                echo '<tr><th scope="row">お支払い金額&nbsp;</th><td>' . wc_price($remaining_and_paid['remaining']) . '</td></tr>';
                include ( plugin_dir_path( __FILE__ ) . 'views/html-payment-remaining.php');             
                echo '</section>';
            }
                

        }
        
        public function checkout_order_processed ($order_id, $posted_data, $order = null) {
             if( $order == null)
                $order    = wc_get_order( $order_id );
             if ( self::has_deposit( $order ) ) {                   
                $remaining = 0;
                $topay      = 0;
                foreach( $order->get_items() as $item ) {
                    if ( WC_Deposits_Order_Item_Manager::is_deposit( $item ) ) {

                            if ( ! WC_Deposits_Order_Item_Manager::get_payment_plan( $item ) ) {
                                // R14: create a new invoice
                                    $topay += $item['deposit_full_amount'];
                            }
                    }else {
                        $topay += $item['full_amount'];
                    }
                }        
                //add shipping to total => must be paid first!
                $topay += $order->get_shipping_total();
                // apply coupon 
                $total_discount = 0;
                foreach( WC()->session->get( 'coupon_discount_totals', array() ) as $discount ) {
                    $total_discount += $discount;
                }	
                if($total_discount>0)
                    $order->add_meta_data( 'future_payment_discount', strval($total_discount) , true  );
                // future_discount
                $order->set_total($topay);
                $order->save();
            }
        }

	/**
	 * Does the order contain a deposit
	 * @return boolean
	 */
	public static function has_deposit( $order ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}
                if(is_bool($order)) {
                    return false;
                }
		foreach( $order->get_items() as $item ) {
			if ( 'line_item' === $item['type'] && ! empty( $item['is_deposit'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Register our custom post statuses, used for order status.
	 */
	public function register_post_status() {
		register_post_status( 'wc-partial-payment', array(
			'label'                     => _x( 'Partially Paid', 'Order status', 'woocommerce-deposits' ),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Partially Paid <span class="count">(%s)</span>', 'Partially Paid <span class="count">(%s)</span>', 'woocommerce-deposits' )
		) );
		register_post_status( 'wc-scheduled-payment', array(
			'label'                     => _x( 'Scheduled', 'Order status', 'woocommerce-deposits' ),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Scheduled <span class="count">(%s)</span>', 'Scheduled <span class="count">(%s)</span>', 'woocommerce-deposits' )
		) );
	}

	/**
	 * Add order statusus to WooCommmerce
	 * @param array $order_statuses
	 * @return array
	 */
	public function add_order_statuses( $order_statuses ) {
		$order_statuses['wc-partial-payment'] = _x( '一部お支払い完了', 'Order status', 'woocommerce-deposits' );
		$order_statuses['wc-scheduled-payment'] = _x( 'Scheduled', 'Order status', 'woocommerce-deposits' );
		return $order_statuses;
	}

	/**
	 * Statuses that can be completed
	 * @param  array $statuses
	 * @return array
	 */
	public function woocommerce_valid_order_statuses_for_payment_complete( $statuses ) {
		$statuses = array_merge( $statuses, array( 'partial-payment', 'scheduled-payment' ) );
		return $statuses;
	}

	/**
	 * Complete order status
	 */
	public function woocommerce_payment_complete_order_status( $status, $order_id ) {
		if ( self::has_deposit( $order_id ) ) {
			$status = 'partial-payment';
		}
		return $status;
	}

	/**
	 * hide scheduled orders from account [age]
	 * @param  array $query
	 */
	public function woocommerce_my_account_my_orders_query( $query ) {                
		if(array_key_exists('post_status',$query)) {
                    $query['post_status'] = array_diff( $query['post_status'], array( 'wc-scheduled-payment' ) );
                } 
		return $query;
	}
        
        /*
         * Hage
         */
        public function woocommerce_my_account_my_orders_actions ($actions, $order ) {
            //
            $remaining =   $this->get_remaining_and_paid($order)['remaining'];
            //status must be partially paid:
            $final_checkout_paid = $order->get_meta('_final_checkout_transaction_amount',true);
            
            if ( $remaining > 0 && $remaining != $final_checkout_paid ) { // remaining not paid ! 
                $actions['pay remaining'] = array(
			'url'  => $order->get_view_order_url() . '/#checkout-remaining',
			'name' => __( '残額を支払う', 'woocommerce' ),
		);
            }
            return $actions;
        }

	/**
	 * Process deposits in an order after payment
	 */
	public function process_deposits_in_order( $order_id ) {
		$order     = wc_get_order( $order_id );
		$parent_id = wp_get_post_parent_id( $order_id );

		// Check if any items need scheduling
		foreach ( $order->get_items() as $order_item_id => $item ) {
			if ( 'line_item' === $item['type'] && ! empty( $item['payment_plan'] ) && empty( $item['payment_plan_scheduled'] ) ) {
				$payment_plan = new WC_Deposits_Plan( absint( $item['payment_plan'] ) );
				WC_Deposits_Scheduled_Order_Manager::schedule_orders_for_plan( $payment_plan, $order_id, array(
					'product'             => $order->get_product_from_item( $item ),
					'qty'                 => $item['qty'],
					'price_excluding_tax' => $item['deposit_full_amount_ex_tax']
				) );
				woocommerce_add_order_item_meta( $order_item_id, '_payment_plan_scheduled', 'yes' );
			}
		}

		// Has parent? See if partially paid
		if ( $parent_id ) {
			$parent_order = wc_get_order( $parent_id );
			if ( $parent_order && $parent_order->has_status( 'partial-payment' ) ) {
				$paid = true;
				foreach ( $parent_order->get_items() as $order_item_id => $item ) {
					if ( WC_Deposits_Order_Item_Manager::is_deposit( $item ) && ! WC_Deposits_Order_Item_Manager::is_fully_paid( $item ) ) {
						$paid = false;
						break;
					}
				}
				if ( $paid ) {
					// Update the parent order
					$parent_order->update_status( 'completed', __( 'All deposit items fully paid', 'woocommerce-deposits' ) );
				}
			}
		}
	}

	/**
	 * Create a scheduled order
	 * @param  string $payment_date
	 * @param  int $original_order_id
	 */
	public static function create_order( $payment_date, $original_order_id, $payment_number, $item, $status = '' ) {
		$original_order = wc_get_order( $original_order_id );
		$new_order      = wc_create_order( array(
			'status'        => $status,
			'customer_id'   => $original_order->get_user_id(),
			'customer_note' => $original_order->customer_note,
			'created_via'   => 'wc_deposits'
		) );
		if ( is_wp_error( $new_order ) ) {
			$original_order->add_order_note( sprintf( __( 'Error: Unable to create follow up payment (%s)', 'woocommerce-deposits' ), $scheduled_order->get_error_message() ) );
		} else {
			$new_order->set_address( array(
				'first_name' => $original_order->billing_first_name,
				'last_name'  => $original_order->billing_last_name,
				'company'    => $original_order->billing_company,
				'address_1'  => $original_order->billing_address_1,
				'address_2'  => $original_order->billing_address_2,
				'city'       => $original_order->billing_city,
				'state'      => $original_order->billing_state,
				'postcode'   => $original_order->billing_postcode,
				'country'    => $original_order->billing_country,
				'email'      => $original_order->billing_email,
				'phone'      => $original_order->billing_phone
			), 'billing' );
			$new_order->set_address( array(
				'first_name' => $original_order->shipping_first_name,
				'last_name'  => $original_order->shipping_last_name,
				'company'    => $original_order->shipping_company,
				'address_1'  => $original_order->shipping_address_1,
				'address_2'  => $original_order->shipping_address_2,
				'city'       => $original_order->shipping_city,
				'state'      => $original_order->shipping_state,
				'postcode'   => $original_order->shipping_postcode,
				'country'    => $original_order->shipping_country
			), 'shipping' );

			// Handle items
			$item_id = $new_order->add_product( $item['product'], $item['qty'], array(
				'totals' => array(
					'subtotal'     => $item['amount'],
					'total'        => $item['amount'],
					'subtotal_tax' => 0,
					'tax'          => 0
				)
			) );
			woocommerce_add_order_item_meta( $item_id, '_original_order_id', $original_order_id );
			wc_update_order_item( $item_id, array( 'order_item_name' => sprintf( __( 'Payment #%d for %s' ), $payment_number, $item['product']->get_title() ) ) );

			$new_order->calculate_totals( wc_tax_enabled() );

			// Set future date and parent
			$new_order_post = array(
				'ID'          => $new_order->get_id(),
				'post_date'   => date( 'Y-m-d H:i:s', $payment_date ),
				'post_parent' => $original_order_id
			);
			wp_update_post( $new_order_post );

			do_action( 'woocommerce_deposits_create_order', $new_order->get_id() );
			return $new_order->get_id();
		}
	}

	/**
	 * Rename meta keys
	 * @param  string $label
	 * @param  string $meta_key
	 * @return string
	 */
	public function woocommerce_attribute_label( $label, $meta_key ) {
		switch ( $meta_key ) {
			case '_deposit_full_amount' :
				$label = __( 'Full Amount', 'woocommerce-deposits' );
			break;
			case '_deposit_full_amount_ex_tax' :
				$label = __( 'Full Amount (excl. tax)', 'woocommerce-deposits' );
			break;
		}
		return $label;
	}

	/**
	 * Hide meta data
	 */
	public function woocommerce_hidden_order_itemmeta( $meta_keys ) {
		$meta_keys[] = '_is_deposit';
		//$meta_keys[] = '_deposit_full_amount';
		//$meta_keys[] = '_deposit_full_amount_ex_tax';
		$meta_keys[] = '_remaining_balance_order_id';
		$meta_keys[] = '_remaining_balance_paid';
		$meta_keys[] = '_original_order_id';
		$meta_keys[] = '_payment_plan_scheduled';
		$meta_keys[] = '_payment_plan';
		return $meta_keys;
	}

	/**
	 * Show info before order item meta
	 */
	public function woocommerce_before_order_itemmeta( $item_id, $item, $_product ) {
		global $wpdb;

		if ( ! WC_Deposits_Order_Item_Manager::is_deposit( $item ) ) {
			return;
		}

		if ( $payment_plan = WC_Deposits_Order_Item_Manager::get_payment_plan( $item ) ) {
			echo ' (' . $payment_plan->get_name() . ')';
		} else {
			echo ' (' . __( 'Deposit', 'woocommerce-deposits' ) . ')';
		}
	}

	/**
	 * Show info after order item meta
	 */
	public function woocommerce_after_order_itemmeta( $item_id, $item, $_product ) {
		global $wpdb;

		if ( WC_Deposits_Order_Item_Manager::is_deposit( $item ) ) {
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d", $item_id ) );
			$order    = wc_get_order( $order_id );

			// Plans
			if ( $payment_plan = WC_Deposits_Order_Item_Manager::get_payment_plan( $item ) ) {
				echo '<a href="' . admin_url( 'edit.php?post_status=wc-scheduled-payment&post_type=shop_order&post_parent=' . $order_id ) . '" target="_blank" class="button button-small">' . __( 'View Scheduled Payments', 'woocommerce-deposits' ) . '</a>';

			// Regular deposits
			} else {
				$remaining                  = $item['deposit_full_amount'] - $order->get_line_total( $item, true );
				$remaining_balance_order_id = ! empty( $item['remaining_balance_order_id'] ) ? absint( $item['remaining_balance_order_id'] ) : 0;
				$remaining_balance_paid     = ! empty( $item['remaining_balance_paid'] );

				if ( $remaining_balance_order_id && ( $remaining_balance_order = wc_get_order( $remaining_balance_order_id ) ) ) {
					echo '<a href="' . admin_url( 'post.php?post=' . absint( $remaining_balance_order_id ) . '&action=edit' ) . '" target="_blank" class="button button-small">' . sprintf( __( 'Remainder - Invoice #%1$s', 'woocommerce-deposits' ), $remaining_balance_order->get_order_number() ) . '</a>';
				} elseif( $remaining_balance_paid ) {
					printf( __( 'The remaining balance of %s (plus tax) for this item was paid offline.', 'woocommerce-deposits' ), wc_price( $remaining, array( 'currency' => $order->get_order_currency() ) ) );
					echo ' <a href="' . esc_url( wp_nonce_url( add_query_arg( array( 'mark_deposit_unpaid' => $item_id ) ), 'mark_deposit_unpaid', 'mark_deposit_unpaid_nonce' ) ) . '" class="button button-small">' . sprintf( __( 'Unmark as Paid', 'woocommerce-deposits' ) ) . '</a>';
				} else {
					?>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'invoice_remaining_balance' => $item_id ), admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ), 'invoice_remaining_balance', 'invoice_remaining_balance_nonce' ) ); ?>" class="button button-small"><?php _e( 'Invoice Remaining Balance', 'woocommerce-deposits' ); ?></a>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'mark_deposit_fully_paid' => $item_id ), admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ), 'mark_deposit_fully_paid', 'mark_deposit_fully_paid_nonce' ) ); ?>" class="button button-small"><?php printf( __( 'Mark Paid (offline)', 'woocommerce-deposits' ) ); ?></a>
					<?php
				}
			}
		} elseif ( ! empty( $item['original_order_id'] ) ) {
			echo '<a href="' . admin_url( 'post.php?post=' . absint( $item['original_order_id'] ) . '&action=edit' ) . '" target="_blank" class="button button-small">' . __( 'View Original Order', 'woocommerce-deposits' ) . '</a>';
		}
	}

	/**
	 * Create and redirect to an invoice
	 */
	public function order_action_handler() {
		global $wpdb;

		$action  = false;
		$item_id = false;

		if ( ! empty( $_GET['mark_deposit_unpaid'] ) && isset( $_GET['mark_deposit_unpaid_nonce'] ) && wp_verify_nonce( $_GET['mark_deposit_unpaid_nonce'], 'mark_deposit_unpaid' ) ) {
			$action  = 'mark_deposit_unpaid';
			$item_id = absint( $_GET['mark_deposit_unpaid'] );
		}

		if ( ! empty( $_GET['mark_deposit_fully_paid'] ) && isset( $_GET['mark_deposit_fully_paid_nonce'] ) && wp_verify_nonce( $_GET['mark_deposit_fully_paid_nonce'], 'mark_deposit_fully_paid' ) ) {
			$action  = 'mark_deposit_fully_paid';
			$item_id = absint( $_GET['mark_deposit_fully_paid'] );
		}

		if ( ! empty( $_GET['invoice_remaining_balance'] ) && isset( $_GET['invoice_remaining_balance_nonce'] ) && wp_verify_nonce( $_GET['invoice_remaining_balance_nonce'], 'invoice_remaining_balance' ) ) {
			$action  = 'invoice_remaining_balance';
			$item_id  = absint( $_GET['invoice_remaining_balance'] );
		}

		if ( ! $item_id ) {
			return;
		}

		$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d", $item_id ) );
		$order    = wc_get_order( $order_id );
		$item     = false;

		foreach ( $order->get_items() as $order_item_id => $order_item ) {
			if ( $item_id === $order_item_id ) {
				$item = $order_item;
			}
		}

		if ( ! $item || empty( $item['is_deposit'] ) ) {
			return;
		}

		switch ( $action ) {
			case 'mark_deposit_unpaid' :
				woocommerce_delete_order_item_meta( $item_id, '_remaining_balance_paid', 1, true );
				wp_redirect( admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' ) );
				exit;
			case 'mark_deposit_fully_paid' :
				woocommerce_add_order_item_meta( $item_id, '_remaining_balance_paid', 1 );
				wp_redirect( admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' ) );
				exit;
			case 'invoice_remaining_balance' :
				$create_item = array(
					'product' => $order->get_product_from_item( $item ),
					'qty'     => $item['qty'],
					'amount'  => $item['deposit_full_amount_ex_tax'] - $order->get_line_total( $item, false )
				);

				$new_order_id = $this->create_order( current_time( 'timestamp' ), $order_id, 2, $create_item );

				woocommerce_add_order_item_meta( $item_id, '_remaining_balance_order_id', $new_order_id );

				// Email invoice
				$emails = WC_Emails::instance();
				$emails->customer_invoice( wc_get_order( $new_order_id ) );

				wp_redirect( admin_url( 'post.php?post=' . absint( $new_order_id ) . '&action=edit' ) );
				exit;
		}
	}

	/**
	 * Sends an email when a partial payment is made
	 */
	public function payment_complete_handler( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'wc-partial-payment' !== $order->post_status ) {
			return;
		}

		$wc_emails = WC_Emails::instance();
		$customer_email = $wc_emails->emails['WC_Email_Customer_Processing_Order'];
		$admin_email    = $wc_emails->emails['WC_Email_New_Order'];
		$customer_email->trigger( $order );
		$admin_email->trigger( $order );
	}

	/**
	 * Append text to item names when viewing an order
	 */
	public function woocommerce_order_item_name( $item_name, $item ) {
		if ( WC_Deposits_Order_Item_Manager::is_deposit( $item ) ) {
			if ( $payment_plan = WC_Deposits_Order_Item_Manager::get_payment_plan( $item ) ) {
				$item_name .= ' (' . $payment_plan->get_name() . ')';
			} else {
				$item_name .= ' (分割２回払い)';
			}
		}
		return $item_name;
	}
        
 
        
        

	/**
	 * Add info about a deposit when viewing an order
	 */
	public function woocommerce_order_item_meta_end( $item_id, $item, $order ) {
		if ( WC_Deposits_Order_Item_Manager::is_deposit( $item ) && ! WC_Deposits_Order_Item_Manager::get_payment_plan( $item ) ) {
			$remaining                  = $item['deposit_full_amount'] - $order->get_line_total( $item, true );
			$remaining_balance_order_id = ! empty( $item['remaining_balance_order_id'] ) ? absint( $item['remaining_balance_order_id'] ) : 0;
			$remaining_balance_paid     = ! empty( $item['remaining_balance_paid'] );

			if ( $remaining_balance_order_id && ( $remaining_balance_order = wc_get_order( $remaining_balance_order_id ) ) ) {
				echo '<p class="wc-deposits-order-item-description"><a href="' . $remaining_balance_order->get_view_order_url() . '">' . sprintf( __( 'Remainder - Invoice #%1$s', 'woocommerce-deposits' ), $remaining_balance_order->get_order_number() ) . '</a></p>';
			} elseif( $remaining_balance_paid ) {
				printf( '<p class="wc-deposits-order-item-description">' . __( 'The remaining balance of %s for this item was paid offline.', 'woocommerce-deposits' ) . '</p>', wc_price( $remaining, array( 'currency' => $order->get_order_currency() ) ) );
			}
		}
	}

	/**
	 * Adjust totals display
	 * @param  array $total_rows
	 * @param  WC_Order $order
	 * @return array
	 */
	public function woocommerce_get_order_item_totals( $total_rows, $order ) {
            
		if ( $this->has_deposit( $order ) ) {
			/*$remaining = 0;
			$paid      = 0;
			foreach( $order->get_items() as $item ) {
				if ( WC_Deposits_Order_Item_Manager::is_deposit( $item ) ) {
                                     
					if ( ! WC_Deposits_Order_Item_Manager::get_payment_plan( $item ) ) {
                                            // R14: create a new invoice
                                            
						$remaining_balance_order_id = ! empty( $item['remaining_balance_order_id'] ) ? absint( $item['remaining_balance_order_id'] ) : 0;
						$remaining_balance_paid     = ! empty( $item['remaining_balance_paid'] );
						if ( empty( $remaining_balance_order_id ) && ! $remaining_balance_paid ) {
							$remaining += $item['deposit_full_amount'] - ( $order->get_line_subtotal( $item, true ) + $item['line_tax'] );
						}
                                                $paid += $item['deposit_full_amount'];
					}
				}else {
                                    $paid += $item['full_amount'];
                                }
			}

			// PAID scheduled orders
			$related_orders = WC_Deposits_Scheduled_Order_Manager::get_related_orders( $order->get_id() );

			foreach ( $related_orders as $related_order_id ) {
				$related_order = wc_get_order( $related_order_id );
				if ( $related_order->has_status( 'processing', 'completed' ) ) {
					$paid += $related_order->get_total();
				} else {
					$remaining += $related_order->get_total();
				}
			}
                        $remaining  =   $order->get_subtotal() + $order->get_shipping_total() - $paid;
			if ( $remaining && $paid ) {
                            	$total_rows['paid'] = array(
					'label' => __( 'Paid', 'woocommerce-deposits' ),
					'value'	=> '<del>' . wc_price( $paid ) . '</del> <ins>' . wc_price( $remaining ) . '</ins>'
				);
				$total_rows['future'] = array(
					'label' => __( 'Future&nbsp;Payments&nbsp;', 'woocommerce-deposits' ),
					'value'	=> '<del>' . wc_price( $remaining + $paid ) . '</del> <ins>' . wc_price( $remaining ) . '</ins>'
				);
			} elseif ( $remaining ) {*/
                                $remaining_paid = $this->get_remaining_and_paid($order);
                                /*$total_rows['paid'] = array(
					'label' => '今回のお支払い金額:',
					'value'	=> wc_price( $remaining_paid['paid'] )
				);*/
				$total_rows['future'] = array(
					'label' => '次回のお支払い金額:',
					'value'	=> wc_price( $remaining_paid['remaining'] )
				);
                                // chage the label for total if this is a deposit:
                                $total_rows['order_total']['label'] = '今回のお支払い合計:';
			//}
		}
		return $total_rows;
	}
        
        public function get_remaining_and_paid( $order ) {
                /*$related_orders = WC_Deposits_Scheduled_Order_Manager::get_related_orders( $order->get_id() );

			foreach ( $related_orders as $related_order_id ) {
				$related_order = wc_get_order( $related_order_id );
				if ( $related_order->has_status( 'processing', 'completed' ) ) {
					$paid += $related_order->get_total();
				} else {
					$remaining += $related_order->get_total();
				}
			}*/
            if ( $this->has_deposit( $order ) ) {                   
                $remaining = 0;
                $paid      = 0;
                foreach( $order->get_items() as $item ) {
                    if ( WC_Deposits_Order_Item_Manager::is_deposit( $item ) ) {

                            if ( ! WC_Deposits_Order_Item_Manager::get_payment_plan( $item ) ) {
                                // R14: create a new invoice

                                    $remaining_balance_order_id = ! empty( $item['remaining_balance_order_id'] ) ? absint( $item['remaining_balance_order_id'] ) : 0;
                                    $remaining_balance_paid     = ! empty( $item['remaining_balance_paid'] );
                                    if ( empty( $remaining_balance_order_id ) && ! $remaining_balance_paid ) {
                                            $remaining += $item['deposit_full_amount'] - ( $order->get_line_subtotal( $item, true ) + $item['line_tax'] );
                                    }
                                    $paid += $item['deposit_full_amount'];
                            }
                    }else {
                        $paid += $item['full_amount'];
                    }
                }
                $paid += $order->get_shipping_total();
                //$paid = $order->get_total();
                
                //apply coupon
                $discount = 0;
                $str_total_discount = $order->get_meta( 'future_payment_discount', true );
                if(isset($str_total_discount) && $str_total_discount != "") {
                    $discount = intval($str_total_discount);
                }
                
                //deposit paid ?
                
                
                $remaining  =  $order->get_subtotal() - $paid - $discount;
                return array('remaining' => $remaining, 'paid' => $paid);
            }
            else return null;
        }

	/**
	 * Admin filters
	 */
	public function request_query( $vars ) {
		global $typenow, $wp_query, $wp_post_statuses;

		if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ) ) ) {
			if ( isset( $_GET['post_parent'] ) && $_GET['post_parent'] > 0 ) {
				$vars['post_parent'] = absint( $_GET['post_parent'] );
			}
		}

		return $vars;
	}

	/**
	 * Triggered when adding an item in the backend.
	 *
	 * If deposits are forced, set all meta data.
	 */
	public function ajax_add_order_item_meta( $item_id, $item ) {
		if ( WC_Deposits_Product_Manager::deposits_forced( $item['product_id'] ) ) {
			$product = wc_get_product( absint( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] ) );
			woocommerce_add_order_item_meta( $item_id, '_is_deposit', 'yes' );
			woocommerce_add_order_item_meta( $item_id, '_deposit_full_amount', $item['line_total'] );
			woocommerce_add_order_item_meta( $item_id, '_deposit_full_amount_ex_tax', $item['line_total'] );

			if ( 'plan' === WC_Deposits_Product_Manager::get_deposit_type( $item['product_id'] ) ) {
				$plan_id = current( WC_Deposits_Plans_Manager::get_plan_ids_for_product( $item['product_id'] ) );
				woocommerce_add_order_item_meta( $item_id, '_payment_plan', $plan_id );
			} else {
				$plan_id = 0;
			}

			// Change line item costs
			//$deposit_amount = WC_Deposits_Product_Manager::get_deposit_amount( $product, $plan_id, 'order', $item['line_total'] );
			//wc_update_order_item_meta( $item_id, '_line_total', $deposit_amount );
                        //wc_update_order_item_meta( $item_id, '_line_subtotal', $deposit_amount );
		}
	}

	/**
	 * Should the order stock be reduced?
	 * @return bool
	 */
	public function allow_reduce_order_stock( $allowed, $order ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		// Don't reduce stock on follow up orders
		if ( 'wc_deposits' === $order->created_via ) {
			$allowed = false;
		}

		return $allowed;
	}
}
WC_Deposits_Order_Manager::get_instance();
