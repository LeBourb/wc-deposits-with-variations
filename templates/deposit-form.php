<!-- class=" -->
<div class="wc-deposits-wrapper <?php echo WC_Deposits_Product_Manager::deposits_forced( $post->ID ) ? 'wc-deposits-forced' : 'wc-deposits-optional'; ?>">
	
	<?php if ( ! WC_Deposits_Product_Manager::deposits_forced( $post->ID ) ) : ?>
		<ul class="wc-deposits-option">
			<li><input type="radio" name="wc_deposit_option" value="yes" id="wc-option-pay-deposit" /><label for="wc-option-pay-deposit"><?php _e( "Pay Deposit",'woocommerce-deposits' ); ?></label></li>
			<li><input type="radio" name="wc_deposit_option" value="no" id="wc-option-pay-full" /><label for="wc-option-pay-full"><?php _e( "Pay in Full",'woocommerce-deposits' ); ?></label></li>
		</ul>
	<?php endif; ?>

	<?php if ( 'plan' === WC_Deposits_Product_Manager::get_deposit_type( $post->ID ) ) : ?>
		<ul class="wc-deposits-payment-plans">
			<?php 
			$plans = WC_Deposits_Plans_Manager::get_plans_for_product( $post->ID, true );
			foreach( $plans as $key => $plan ) { ?>
				<li class="wc-deposits-payment-plan <?php WC_Deposits_Plans_Manager::output_plan_classes( $plan ); ?>">
					<input type="radio" name="wc_deposit_payment_plan" <?php checked( $key, 0 ); ?> value="<?php echo esc_attr( $plan->get_id() ); ?>" id="wc-deposits-payment-plan-<?php echo esc_attr( $plan->get_id() ); ?>" /><label for="wc-deposits-payment-plan-<?php echo esc_attr( $plan->get_id() ); ?>">
						<strong class="wc-deposits-payment-plan-name"><?php echo esc_html( $plan->get_name() ); ?></strong>
						<small class="wc-deposits-payment-plan-description"><?php echo wp_kses_post( $plan->get_description() ); ?></small>
					</label>
				</li>
			<?php } ?>
		</ul>
	<?php else : ?>
		<div class="wc-deposits-payment-description">
			<?php echo WC_Deposits_Product_Manager::get_formatted_deposit_amount( $post->ID ); ?>
                    <ul><li>分割２回払い（今は半額だけ払い, 商品お届け前に残り半額を支払う）</li>
                        <li>全額前払い</li>
                       </ul> 

		</div>
	<?php endif; ?>
</div>