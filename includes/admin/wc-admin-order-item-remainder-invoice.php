<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


// Adding Meta container admin shop_order pages
add_action( 'add_meta_boxes', 'order_item_remainder_invoice' );
if ( ! function_exists( 'order_item_remainder_invoice' ) )
{
    function order_item_remainder_invoice()
    {
        add_meta_box( 'mv_order_item_remainder_invoice', __('Remainder Invoice','woocommerce'), 'meta_order_item_remainder_invoice', 'shop_order' );
    }
}

if ( ! function_exists( 'meta_order_item_remainder_invoice' ) )
{
    function meta_order_item_remainder_invoice()
    {
        print('coco');
    }
}