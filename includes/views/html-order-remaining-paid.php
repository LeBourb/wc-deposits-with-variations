<div class="wrap woocommerce">
	<h2><?php _e( 'Deposit Remaining paid', 'woocommerce-deposits' ); ?> 
	<div class="wc-col-container">
		<div class="wc-col-right">
			<div class="wc-col-wrap">
                            <h3><?php _e( 'Transaction amout', 'woocommerce-deposits' ); ?></h3>
                            <p><?php echo wc_price($order->get_meta_data('_final_checkout_transaction_amount',true)); ?></p>
			</div>
                        <div class="wc-col-wrap">
                            <h3><?php _e( 'Final Payment Method', 'woocommerce-deposits' ); ?></h3>
                            <p><?php echo $order->get_meta_data('_final_checkout_payment_method',true); ?></p>
                        </div>
                        <div class="wc-col-wrap">
                            <h3><?php _e( 'Final Payment Date', 'woocommerce-deposits' ); ?></h3>
                            <p><?php echo strtotime($order->get_meta_data('_final_checkout_date' ,true)); ?></p>
                        </div>
		</div>
	</div>
</div>