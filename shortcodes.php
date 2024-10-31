<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

//SHORTCODES

function nibble_button_shortcode($attr){
    $args = shortcode_atts( array(
        'product_id' => null,
    ), $attr ); 
    $pid = null; //To manage auto product discover
    if (isset($args['product_id'])) {
        if ((int)$args['product_id'] == $args['product_id']) {
            $pid = (int)$args['product_id'];
        } else {
            //something weird
            return '';
        } 
        if ($pid == 0) {
            //Should use the cart shortcode
            return '';
        }
    }
    $output = nibble_button($pid);
    return $output;
}
 
add_shortcode( 'nibble' , 'nibble_button_shortcode' );

function nibble_cart_button_shortcode($attr){
    $args = shortcode_atts(array(), $attr);
    $output = nibble_button_cart();
    return $output;
}
 
add_shortcode( 'nibble_cart' , 'nibble_cart_button_shortcode' );

function nibble_form_token_shortcode($attr){
    $args = shortcode_atts(array(), $attr);
    $output = '<nibblet></nibblet>';
    return $output;
}
 
add_shortcode( 'nibble_token' , 'nibble_form_token_shortcode' );