<!-- class=" -->
<script>
    window.addEventListener("DOMContentLoaded", function() {
        // Select the node that will be observed for mutations
        var targetNode = jQuery('.wc-deposits-wrapper');
        
        var $select = jQuery('form.variations_form.cart select');
        
        
         /**
            * See if attributes match.
            * @return {Boolean}
            */
         var isMatch = function( variation_attributes, attributes ) {
                   if(variation_attributes.length !== attributes.length)
                       return false;
                   
                   var match = true;
                   for ( var attr_name in variation_attributes ) {
                           if ( attributes.hasOwnProperty( attr_name ) ) {
                                   var val1 = variation_attributes[ attr_name ];
                                   var val2 = attributes[ attr_name ];
                                   if ( val1 !== val2 ) {
                                           match = false;
                                   }
                           }else {
                               match = false;
                           }
                   }
                   return match;
           };
        
        /**
            * Find matching variations for attributes.
            */
        var findMatchingVariations = function( variations, attributes ) {
                   var matching = [];
                   for ( var i = 0; i < variations.length; i++ ) {
                           var variation = variations[i];

                           if ( isMatch( variation.attributes, attributes ) ) {
                                   matching.push( variation );
                           }
                   }
                   return matching;
           };

          
        var onChange = function(evt) {
            var variationData = jQuery(document.querySelector('.variations_form.cart')).data( 'product_variations' );
            var selection = {};
            $select.each(function(idx, slc) {
                selection[jQuery(slc).data('attribute_name')] = slc.value;
            })
            
            var variations = findMatchingVariations( variationData, selection );
            if(variations.length == 1) {
                 if(variations[0].deposits_enabled == 'true') {
                     targetNode.show();
                 }else 
                     targetNode.hide();
                
            }
        }
        
        $select.change(onChange);
        
        onChange();
        
          
    });
</script>
<div class="wc-deposits-wrapper <?php echo WC_Deposits_Product_Manager::deposits_forced( $post->ID ) ? 'wc-deposits-forced' : 'wc-deposits-optional'; ?>">
	
	<?php if ( ! WC_Deposits_Product_Manager::deposits_forced( $post->ID ) ) : ?>
		<ul class="wc-deposits-option">			
			<li><input type="radio" name="wc_deposit_option" value="no" id="wc-option-pay-full" /><label for="wc-option-pay-full"><?php _e( "一括前払い",'woocommerce-deposits' ); ?></label></li>
                        <li><input type="radio" name="wc_deposit_option" value="yes" id="wc-option-pay-deposit" /><label for="wc-option-pay-deposit"><?php _e( "分割２回払い（半額ずつ）",'woocommerce-deposits' ); ?></label></li>
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
		<div class="wc-deposits-payment-description" style="display:none;">
			<?php //echo WC_Deposits_Product_Manager::get_formatted_deposit_amount( $post->ID ); ?>
                    <p>残金のお支払い時期 : 商品のお届け準備完了時</p>
                    <p>詳しくは、「<a href="<?php echo get_permalink(get_option('woocommerce_shopping_guide_page_id')); ?>">ご利用ガイド ③ お支払いについて</a>」をご参照ください</p>

		</div>
	<?php endif; ?>
</div>