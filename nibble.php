<?php
/**
 * Nibble Make an Offer App
 *
 * @package           Nibble
 * @author            NibbleTechnology
 * @copyright         2022 NibbleTechnology
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Nibble Make an Offer App
 * Description:       A plugin used to integrate Nibble bot with a WooCommerce cart.
 * Version:           0.9.9
 * Requires at least: 5.8
 * Requires PHP:      7.4.3
 * Tested up to:      6.6.2
 * Author:            NibbleTechnology
 * Author URI:        https://www.nibbletechnology.com/
 * Text Domain:       nibble
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once(__DIR__.'/woocommerce.php');
require_once(__DIR__.'/connector.php');
require_once(__DIR__.'/shortcodes.php');
require_once(__DIR__.'/signposting.php');

//REQUIREMENTS

add_action( 'admin_init', 'nibble_requirements' );
function nibble_requirements() {
	//TODO: Add minimum woocommerce version
    if ( is_admin() && current_user_can( 'activate_plugins' ) &&  !is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
        add_action( 'admin_notices', 'nibble_plugin_notice' );

        deactivate_plugins( plugin_basename( __FILE__ ) ); 

        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
    }
}

function nibble_get_from_post($key) {
    if (!$key) {
        return null;
    }
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    return null;
}

function nibble_plugin_notice(){
    echo '<div class="error"><p>Sorry, Nibble integration needs Woocommerce to be installed and active.</p></div>';
}

add_filter( 'plugin_action_links_'.basename(dirname(__FILE__)).'/nibble.php', 'nibble_settings_link' );
function nibble_settings_link( $links ) {
    // Build and escape the URL.
    $url = esc_url( add_query_arg(
        array('page' => 'wc-settings', 'tab' => 'nibble_settings_tab'),
        get_admin_url() . 'admin.php'
    ) );
    // Create the link.
    $settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';
    // Adds the link to the end of the array.
    array_push(
        $links,
        $settings_link
    );
    return $links;
}

//Activation
register_activation_hook( __FILE__, 'nibble_activation_notice_activation_hook' );

function nibble_activation_notice_activation_hook() {
    set_transient( 'nibble-activation-notice', true, 5 );
}

add_action( 'admin_notices', 'nibble_activation_notice' );

function nibble_activation_notice(){

    /* Check transient, if available display notice */
    if( get_transient( 'nibble-activation-notice' ) ){
        $url = esc_url( add_query_arg(
            array('page' => 'wc-settings', 'tab' => 'nibble_settings_tab'),
            get_admin_url() . 'admin.php'
        ) );
        $settings_link = "<a href='$url'>" . __( 'settings' ) . '</a>';
        ?>
        <div class="updated notice is-dismissible">
            <p>Thank you for using <strong>Nibble Integration</strong> - you can now configure the <?php echo $settings_link ?>.</p>
        </div>
        <?php
        /* Delete transient, only display this notice once. */
        delete_transient( 'nibble-activation-notice' );
    }
}

//CSS 

function nibble_admin_style() {
  wp_enqueue_style('admin-styles', plugin_dir_url(__FILE__) . "css/admin.css");
}
add_action('admin_enqueue_scripts', 'nibble_admin_style');

//BUTTON SCRIPTS

function nibble_button($product_id = null) {
    
    if (!$product_id || is_null($product_id) || empty($product_id)) {
        $product = wc_get_product();
        if ($product) {
            $product_id = $product->get_id();
        } else {
            return '';
        }
    } else {
        $product = wc_get_product($product_id);
    } 
    
    if ($product && in_array($product->get_type(), array('simple','variable') )) {
        //Go on
    } else {
        //Type not supported
        return '';
    }

    global $nibbleWoocommerceConnector;
    $apiKey = $nibbleWoocommerceConnector->getApiKey();
    $productId = $nibbleWoocommerceConnector->getProductId($product_id);
    if (!$apiKey || !$productId || empty($apiKey) || empty($productId)) {
        return '';
    }
    $categories = array();
    $terms = get_the_terms($product_id, 'product_cat');
    if ($terms) {
        foreach ($terms as $term) {
            $categories[] = 'category:'.esc_attr(str_replace('"',"'",$term->name));
        }
    }
    $cats = '';
    if (count($categories)>0) {
        $cats = ' product-tags="'.implode(',',$categories).'" ';
    }
    $valid_variations = '';
    if ('variable' == $product->get_type()) {
        $valid_variations = $nibbleWoocommerceConnector->getNibbleVariationsString($product);
        if ($valid_variations != '') {
            $valid_variations = 'var nibble_valid_variations = "'.$valid_variations.'"';
        }
    }
    $css = $nibbleWoocommerceConnector->getCustomCss();
    $maxQuantity = $nibbleWoocommerceConnector->getMaxQuantity($product);
    if ($product->get_type() == 'simple' && $maxQuantity === 0) {
        //If maxQuantity is false it means that I'm not managing stock level
        //if it is 0 there are not valid products
        //I'm not managing variable products, trusting Woocommerce
        return '';
    }

    $currencyString = '';
    $localeString = '';
    if (get_woocommerce_currency()) {
        $currencyString = ' currency-code="'.get_woocommerce_currency().'" ';
    }
    if (get_locale()) {
        $localeString = ' language="'.get_locale().'" ';
    }

    return $css.'
    <div class="nibble-button-wrapper" style="display: block; clear: both; float: none;" 
        data-product_id="'.$product->get_id().'" data-product_type="'.$product->get_type().'" 
        data-prodict_variations="'.$valid_variations.'"
    >
    <nibble-button '.($nibbleWoocommerceConnector->isDev() ? 'api-version="dev"' : '').' '.($nibbleWoocommerceConnector->isDev() ? 'window-script-url="https://cdn.nibble.website/widget/dev/nibble-window.min.js"' : '').' 
        api-key="'.esc_attr($apiKey).'" product-id="'.esc_attr($productId).'" '.$cats.' 
        '.$currencyString.' '.$localeString.' ></nibble-button>
    </div>
    ';
}

function nibble_button_cart() {

    global $nibbleWoocommerceConnector;
    global $NibbleWCTools;

    $apiKey = $nibbleWoocommerceConnector->getApiKey();
    if (!$apiKey || empty($apiKey) || !(is_page( 'cart' ) || is_cart()) ) {
        return '';
    }

    if (!$nibbleWoocommerceConnector->canNibbleonNibble() && $NibbleWCTools->isNibbleDiscountApplied()) {        
        return '';
    }

    if (!$NibbleWCTools->areNotOnlyNibbledProducts()) {
        //No button if there are only Nibbled products
        return '';
    }

    $currencyString = '';
    $localeString = '';
    if (get_woocommerce_currency()) {
        $currencyString = ' currency-code="'.get_woocommerce_currency().'" ';
    }
    if (get_locale()) {
        $localeString = ' language="'.get_locale().'" ';
    }

    $css = $nibbleWoocommerceConnector->getCustomCss();
    return $css.'
    <div class="nibble-button-wrapper" style="display: block; clear: both; float: none;">
    <div id="nibble_add_to_cart_nonce" data-nonce="'.wp_create_nonce('add_to_cart_nonce').' style="display:none;"></div>
    <nibble-button  '.($nibbleWoocommerceConnector->isDev() ? 'api-version="dev"' : '').' '.($nibbleWoocommerceConnector->isDev() ? 'window-script-url="https://cdn.nibble.website/widget/dev/nibble-window.min.js"' : '').' 
        api-key="'.esc_attr($apiKey).'" negotiation-type="cart" 
        '.$currencyString.' '.$localeString.' 
        cart-value="'.esc_attr(WC()->cart->subtotal).'" show="true"></nibble-button></div>
    ';
}

//AJAX
function nibble_enqueue_scripts() {

    global $nibbleWoocommerceConnector;

    $buttonUrl = 'https://cdn.nibble.website/widget/nibble-button.min.js';
    if ($nibbleWoocommerceConnector->isDev()) {
        $buttonUrl = 'https://cdn.nibble.website/widget/dev/nibble-button.min.js';
    }

    wp_enqueue_script("nibble-cdn-script", $buttonUrl);
    wp_enqueue_script ("nibble-ajax-handle", plugin_dir_url(__FILE__) . "js/ajax.js", array('jquery')); 
    wp_localize_script('nibble-ajax-handle', 'nibble_connect', array('ajaxurl' =>admin_url('admin-ajax.php')));
    wp_localize_script('nibble-ajax-handle', 'nibble_verify', array('ajaxurl' =>admin_url('admin-ajax.php')));
    wp_localize_script('nibble-ajax-handle', 'nibble_test', array('ajaxurl' =>admin_url('admin-ajax.php')));
    wp_localize_script('nibble-ajax-handle', 'nibble_signposting', array('ajaxurl' =>admin_url('admin-ajax.php')));
} 
add_action("wp_enqueue_scripts", "nibble_enqueue_scripts");

function nibble_enqueue_admin_scripts() {
    wp_enqueue_script ("nibble-admin", plugin_dir_url(__FILE__) . "js/admin.js", array('jquery')); 
} 
add_action('admin_enqueue_scripts', 'nibble_enqueue_admin_scripts');
add_action("admin_enqueue_scripts", "nibble_enqueue_scripts");

function nibble_server_test() {
  
    global $nibbleWoocommerceConnector;

    $result = $nibbleWoocommerceConnector->testConnection();

    $data = array('status' => false, 'message' => 'Connection error');    
    if ($result) {
        $res = json_decode($result);
        if ($res->errorMessage == 'API Key is not valid.') {
            $data['message'] = __('API Key is not valid.','nibble');            
        } else if ($res->errorMessage == 'API Secret is not valid.') {
            $data['message'] = __('API Secret is not valid.','nibble');            
        } else if (strtolower($res->errorMessage) == 'internal server error' || $res->errorMessage == 'Missing Authentication Token') {
            $data['message'] = __('Server connection error.','nibble');
        } else {
            //All should be ok
            $data['status'] = true;
            $data['message'] = __('Credentials valid','nibble');
        }
    }
    die(json_encode($data));
}
  
add_action('wp_ajax_nopriv_nibble_server_test', 'nibble_server_test');
add_action('wp_ajax_nibble_server_test', 'nibble_server_test');

function nibble_server_connect() {
  
    global $nibbleWoocommerceConnector;

    $product_id = (int)nibble_get_from_post('product_id');
    $quantity = (int)nibble_get_from_post('quantity');
    $variant_id = (int)nibble_get_from_post('variant_id');
    if (0 == $quantity) {
        $quantity = 1;
    }
    if ($product_id == 0) {
        //Cart
        $result = $nibbleWoocommerceConnector->createConnection($product_id,0, null, true);
    } else {
        //product
        $result = $nibbleWoocommerceConnector->createConnection($product_id,$quantity, $variant_id > 0 ? $variant_id : null);
    }
    die($result);
}
  
add_action('wp_ajax_nopriv_nibble_server_connect', 'nibble_server_connect');
add_action('wp_ajax_nibble_server_connect', 'nibble_server_connect');

function nibble_server_verify() {
  
    global $nibbleWoocommerceConnector;

    $product_id = (int)nibble_get_from_post('product_id'); //For CART transactions product is 0
    $nibble_id = sanitize_text_field(nibble_get_from_post('nibble_id'));
    $token = sanitize_text_field(nibble_get_from_post('token'));
    $quantity = (int)nibble_get_from_post('quantity');
    $result = $nibbleWoocommerceConnector->verifyConnection($nibble_id, $token, $product_id, $product_id == 0);

    //Json result should be something like
    /*
    {
      "nibbleId": "AB1934",
      "sessionStatus": "successful",
      "productId": "ABCD",
      "subProductId": "DEF123",
      "negotiatedPrice": "130.50",
      "retailerSessionId": "XXXX",
      "additionalProducts": [
        "productId": "ZYXW",
        "negotiatedPrice": "20.00"
      ]
    }
    */
    $errors = [];
    if ($result) {
        $data = json_decode($result);
        if ($data->nibbleId !== $nibble_id) {
            $errors[] = __("Nibble id not valid",'nibble');
        }
        if ($data->sessionStatus !== "successful") {
            $errors[] = __("Session status not valid");
        }
        if ($product_id >0 && $data->productId !== $nibbleWoocommerceConnector->getProductId($product_id)) {
            $errors[] = __("Product id not valid");
        }
        if (!$nibbleWoocommerceConnector->checkValidSession($data->retailerSessionId, $token, $product_id) ) {
            $errors[] = __("Session not valid");
        }
    }

    $session = WC()->session;

    if (!$session) {
        $errors[] = __("Woocommerce Session not valid");
    }
 
    //HERE I CAN CREATE THE COUPON AND ADD THE PRODUCT TO THE CART
    $result = json_decode($result);
    if (count($errors)>0) {
        //TODO: return error code in header
        $result = array('errors' => $errors, 'add' => false);
    } else {

        //Data are ok, I'm saving it to the session so I can get it after the product has been added to the cart

        if (!$session->get('nibble')) {
            $session->set('nibble',array());
        }
        $data->wpid = $product_id;
        $currData = $session->get('nibble');
        $currData[] = $data;
        $session->set('nibble',$currData);
        $result = array('errors' => false, 'add' => true);
        if ($product_id == 0) {
            $result['url'] = wc_get_checkout_url();
        }
    }

    die(json_encode($result));
}
  
add_action('wp_ajax_nopriv_nibble_server_verify', 'nibble_server_verify');
add_action('wp_ajax_nibble_server_verify', 'nibble_server_verify');


//WOOCOMMERCE BUTTONS

//Automatically add button to product page
add_action('woocommerce_after_add_to_cart_button','nibble_additional_button');
function nibble_additional_button() {
    global $nibbleWoocommerceConnector;
    if ($nibbleWoocommerceConnector->canAddToProductPage()) {
        $productID = get_the_ID();
        echo nibble_button($productID);   
    }
}

//Automaticall add button to cart page
add_action('woocommerce_proceed_to_checkout','nibble_additional_cart_button');
function nibble_additional_cart_button() {
    global $nibbleWoocommerceConnector;
    global $NibbleWCTools;
    if ($nibbleWoocommerceConnector->canAddToCartPage() && !$NibbleWCTools->isNibbleCouponAlreadyApplied()) {
        echo nibble_button_cart();   
    }
}


//Add custom data to product based on nibble
function nibble_add_to_cart_item( $cart_item_data, $product_id, $variation_id ) {
    $session = WC()->session;
    if (!$session || !$session->get('nibble')) {
        return $cart_item_data;
    }

    $index = false;
    foreach ($session->get('nibble') as $i => $nibbled) {
        if ($nibbled->wpid == $product_id) { 
            $index = $i;
            break;
        }
    }

    if ($index !== false) {
        $d = $session->get('nibble');
        $data = $d[$index];
        $cart_item_data['nibble_price'] = $data->negotiatedPrice;
        if ($data->quantity > 1) {
            $cart_item_data['nibble_price'] = round($data->negotiatedPrice/$data->quantity,2);
        }
        $cart_item_data['nibble_quantity'] = $data->quantity;
        $data->base_price = $data->originalPrice;
        $cart_item_data['nibble_data'] = $data;
     
        unset($d[$index]);
        $session->set('nibble',$d);
        //wc_add_notice( __( 'Nibble data has been added to cartitem', 'nibble' ), 'success' );
    }
    
    return $cart_item_data;
}

add_filter( 'woocommerce_add_cart_item_data', 'nibble_add_to_cart_item', 10, 3 );

//Change product price after Nibble
add_action( 'woocommerce_before_calculate_totals', 'nibble_update_custom_price', 1, 1 );
function nibble_update_custom_price( $cart_object ) {
    foreach ( $cart_object->cart_contents as $cart_item_key => $value ) {            
        //if (isset($value['nibble_price'])) { var_dump($value['data']->get_price(),$value['nibble_price']); die(); }
        if (isset($value['nibble_price']) && $value['data']->get_price()!=$value['nibble_price']) {            
            //wc_add_notice( __( 'Nibbled price has been applied', 'nibble' ), 'success' );
            $value['data']->set_price($value['nibble_price']);
        }
        if (isset($value['nibble_quantity']) && $value['quantity']!=$value['nibble_quantity']) {
            //wc_add_notice( __( 'Nibbled price has been applied', 'nibble' ), 'success' );
            $cart_object->set_quantity( $cart_item_key, $value['nibble_quantity']);
        }
    }
}

//Do not allow to change quantity for nibbled item
add_action( 'woocommerce_after_cart_item_quantity_update', 'nibble_limit_cart_item_quantity', 20, 4 );
function nibble_limit_cart_item_quantity( $cart_item_key, $quantity, $old_quantity, $cart ){
    if( isset($cart->cart_contents[ $cart_item_key ]['nibble_price']) && $quantity != $old_quantity){
        if (isset($cart->cart_contents[ $cart_item_key ]['nibble_quantity']) && $quantity > 0 && $quantity == $cart->cart_contents[ $cart_item_key ]['nibble_quantity']) {
            //All ok, I'm changing the quantity based on the Nibble process            
        } else {
            // Change the quantity to the limit allowed
            $cart->cart_contents[ $cart_item_key ]['quantity'] = $old_quantity;
            // Add a custom notice
            wc_add_notice( __('You cannot change quantity for nibbled items'), 'notice' );
        }
    }
}

//Copy nibble data to order items
add_action( 'woocommerce_checkout_create_order_line_item', 'nibble_checkout_create_order_line_item', 20, 4 );
function nibble_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
    // Get cart item custom data and update order item meta
    if( isset( $values['nibble_data'] ) ) {
        //wc_add_notice( __('Nibble data added to order').print_r($nibble_data,true), 'notice' );
        $item->update_meta_data( 'nibble_data', $values['nibble_data'] );
    }
}

//Order item backend
add_action( 'woocommerce_before_order_itemmeta', 'nibble_woocommerce_before_order_itemmeta', 20, 4);
function nibble_woocommerce_before_order_itemmeta( $item_id, $item, $product  ){
    if (isset($item['nibble_data'])) {
        echo '<div class="nibble_data"><strong>Nibbled product</strong> (Nibble ID:'.$item['nibble_data']->nibbleId.', base price:'.$item['nibble_data']->originalPrice.')</div>';
    }
}

//Order creation, vale cart session details
add_action('woocommerce_new_order', 'nibble_new_order', 10, 1);
function nibble_new_order ($order_id) {
    $order = wc_get_order( $order_id );
    if (!$order) {
        return;
    }
    $session = WC()->session;
    if ($session && $session->get('nibble_cart_id')) {
        $order->update_meta_data( 'nibble_cart_id', $session->get('nibble_cart_id') ); //I need to be able to recover the transaction later
        $order->update_meta_data( 'nibble_coupon_amount', $session->get('nibble_coupon_amount') ); //I could need it for order detail in backend
        $order->update_meta_data( 'nibble_coupon_percentual', $session->get('nibble_coupon_percentual')); //Could be of use
        $order->update_meta_data( 'nibble_data_processed', -1); //WP manages false bad in queries
        $order->save();
        $session->set('nibble_cart_id', null);
        $session->set('nibble_coupon_id', null);
        $session->set('nibble_coupon_amount', null);
        $session->set('nibble_coupon_percentual', null);
    }
}
//Submit order to Nibble backend after success
add_action('woocommerce_thankyou', 'nibble_send_confirmation', 10, 1);
function nibble_send_confirmation( $order_id ) {

    if ( ! $order_id ) {
        return;
    }

    $order = wc_get_order( $order_id );
    //Let's check if it is a Nibble order first
    if (!get_post_meta( $order_id, 'nibble_data_processed', true )) {
        foreach ($order->get_items() as $item_id => $item ) {
            if (!isset($item['nibble_data'])) { 
                continue;
            }
            $order->update_meta_data('nibble_data_processed', -1);
            $order->save();
            break;
        }
    }

    //NOTE: I'm setting nibble_data_processing to -1 to be able to query orders that have not been sent yet
    //In case the "thankyou" process fails

    // Allow code execution only once 
    if( 1 != (int)get_post_meta( $order_id, 'nibble_data_processed', true ) ) {

        global $nibbleWoocommerceConnector;
        $result = $nibbleWoocommerceConnector->sendTransaction($order_id);
        if ($result && strtolower(str_replace('"','',$result)) == 'ok') {
            $order->update_meta_data( 'nibble_data_processed', true );
            $order->update_meta_data( 'nibble_data_sent', true );
            $order->update_meta_data( 'nibble_data_sent_at', time() );

            $note = __("Transaction confirmation sent to Nibble", 'nibble');

            // Add the note
            $order->add_order_note( $note );

            $order->save();
        } else if ($result == 'Nothing to send'){
            //Order without nibbled objects
            $order->update_meta_data( 'nibble_data_processed', true );
            $order->update_meta_data( 'nibble_data_sent', false );
            $order->save();            
        } else {
            //Something wrong, maybe manage all with a cron job?
        }

        $nibbleWoocommerceConnector->unsetSession();

    }
}

//Nibbled price in cart
add_filter( 'woocommerce_cart_item_price', 'nibble_cart_item_price', 10, 3 );
function nibble_cart_item_price( $price, $cart_item, $cart_item_key ) {

    if ( isset($cart_item[ 'nibble_price' ]) ) {
        $original = $cart_item['nibble_data']->originalPrice;
        $qty = 1;
        if ($cart_item['quantity'] > 1) {
            $original = sprintf("%.2f", round($original / $cart_item['quantity'],2));
        }
        $price.=' <span class="nibble-was" style="text-decoration: line-through;">'.$original.'</span>';
    }

    return $price;
}

//Apply cart discount

add_action('woocommerce_before_cart','nibble_apply_cart_coupon');
add_action('woocommerce_before_checkout_form','nibble_apply_cart_coupon_order');

function nibble_apply_cart_coupon_order() {
    global $NibbleWCTools;  
    if (!$NibbleWCTools->isNibbleCouponAlreadyApplied()) {
        nibble_apply_cart_coupon();
    }
}

function nibble_apply_cart_coupon(){    
    $session = WC()->session;

    if (!$session || !$session->get('nibble')) {
        //Nothing to do
        return;
    }

    $index = false;
    foreach ($session->get('nibble') as $i => $nibbled) {
        if ($nibbled->wpid == 0) { 
            //This is a cart discount
            $index = $i;
            break;
        }
    }

    if ($index !== false) {
        $d = $session->get('nibble');
        $data = $d[$index];
        $newPrice = $data->negotiatedPrice;
        
        $cart = WC()->cart;
        $subtotal = $cart->subtotal;
        if ($subtotal <= 0) {
            //No need of a coupon for free orders
            return;
        }
        $discount = $subtotal - $data->negotiatedPrice;
        if ($discount <= 0) {
            //Something weird, let's skip
            return;
        }
        $discount_percentual = round(($subtotal - $data->negotiatedPrice) / $subtotal * 100, 4);

        global $NibbleWCTools;
        global $nibbleWoocommerceConnector;

        $use_percentual_discount = $nibbleWoocommerceConnector->getCartdiscountType() == 'percentual';

        $cart_token = $NibbleWCTools->calculateCartToken();
        //Coupon as in Base-36
        $coupon_code = 'NB-'.base_convert(rand(100,200).str_pad(get_current_user_id(), 4, '0', STR_PAD_RIGHT).time(),10,36);
        unset($d[$index]);
        $session->set('nibble',$d);

        //Creating the coupon
        if ($use_percentual_discount) {
            $discount_type = 'percent'; // Type: fixed_cart, percent, fixed_product, percent_product
        } else {
            $discount_type = 'fixed_cart';
        }

        $coupon = array(
        'post_title' => $coupon_code,
        'post_content' => '',
        'post_status' => 'publish',
        'post_author' => 1,
        'post_type' => 'shop_coupon');

        $new_coupon_id = wp_insert_post( $coupon );

        // Add meta
        update_post_meta( $new_coupon_id, 'discount_type', $discount_type );
        update_post_meta( $new_coupon_id, 'coupon_amount', $use_percentual_discount ? $discount_percentual : $discount );
        update_post_meta( $new_coupon_id, 'individual_use', 'no' ); //Cumulable
        update_post_meta( $new_coupon_id, 'usage_limit', 1 );
        update_post_meta( $new_coupon_id, 'expiry_date', date("Y-m-d", strtotime("+2 day"))); //Not +1 as it will have problems with orders at late night
        update_post_meta( $new_coupon_id, 'apply_before_tax', 'yes' );
        update_post_meta( $new_coupon_id, 'free_shipping', 'no' );

        //Adding coupon to cart    
        //Storing in session to double-check for validity
        $session->set('nibble_coupon_code', $coupon_code); 

        if (WC()->cart->apply_coupon( $coupon_code )) {
            $session->set('nibble_cart_token', $cart_token);  
            $session->set('nibble_coupon_id', $new_coupon_id); 
            $session->set('nibble_coupon_amount', $discount);            
            $session->set('nibble_coupon_percentual', $discount_percentual);
            $session->set('nibble_cart_id', $data->nibbleId);
        } else {
            $session->set('nibble_coupon_code', null); 
        }


        //wc_add_notice( __( 'Nibble coupon has been added to cart', 'nibble' ), 'success' );
    }
    
    return;
}

//Check if cart has changed, remove coupon in case
//Any change should recalculate the total so I should get all the cases
//I do not need to check the order, any change to the cart will invalidate it, ideally
add_action( 'woocommerce_before_calculate_totals', 'nibble_check_cart', 1, 1 );
function nibble_check_cart( $cart_object) {
    $session = WC()->session;
    if (!$session) {
        //nothing to do
        return;
    }
    $nibble_coupon_code = $session->get('nibble_coupon_code');
    $nibble_cart_token = $session->get('nibble_cart_token');
    $nibble_coupon_id = $session->get('nibble_coupon_id');
    if (!$nibble_cart_token || !$nibble_coupon_id || !$nibble_coupon_code) {
        //nothing to do
        return;
    }
    global $NibbleWCTools;
    $cart_token = $NibbleWCTools->calculateCartToken($cart_object->cart_contents);

    if ($cart_token !== $nibble_cart_token) {
        //Something has chaged, remove nibble coupon
        WC()->cart->remove_coupon( $session->get('nibble_coupon_code') );
        //Just in case, remove from DB also
        //Deleting the coupon
        if (get_post_type($nibble_coupon_id) == 'shop_coupon') {
            //Let's play difensive
            wp_delete_post($nibble_coupon_id);
        }
        $session->set('nibble_coupon_code', null);
        $session->set('nibble_cart_token', null);
        $session->set('nibble_coupon_id', null);
        $session->set('nibble_cart_id', null); //Session is no longer valid   
        $session->set('nibble_coupon_amount', null); //Session is no longer valid  
        $session->set('nibble_coupon_percentual', null); //Session is no longer valid  
    }
    return;
}

add_action ( 'woocommerce_applied_coupon', 'nibble_coupon_check', 1, 1  );
 
function nibble_coupon_check( $code ) {
    //This is fired BEFORE nibble_apply_cart_coupon session delete part, so I still have session data

    //I'm checking if the coupon is in the session before applying it, to not allow a case in which a user gets a discount in
    //a cart and apply to another one

    if (strpos('-'.strtolower($code),'nb-') != 1) {
        //not a nibble code, likely
        return;
    }

    $session = WC()->session;
    if (!$session) {
        //nothing to do
        return;
    }
    $current_code = $session->get('nibble_coupon_code');    
    if (strtolower($code) == strtolower($current_code)) {
        //All ok
        return;
    }

    //Something wrong, let's throw and exception
    wc_add_notice( __( 'Code not valid for this transaction '.print_r($code,true).print_r($current_code, true), 'nibble' ), 'error' );
    //Remove the coupon
    WC()->cart->remove_coupon( $code );
    return;
}

//Cron logic
add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', 'nibble_order_custom_query_var', 10, 2 );
function nibble_order_custom_query_var( $query, $query_vars ) {
    if ( ! empty( $query_vars['nibble_data_processed'] ) ) {
        if (isset($query_vars['nibble_data_processed']) && $query_vars['nibble_data_processed'] == 'null') {
            $query['meta_query'][] = array(
                'key' => 'nibble_data_processed',
                'compare' => 'NOT EXISTS',
                'value' => 'null',
            );
        } else {            
            $query['meta_query'][] = array(
                'key' => 'nibble_data_processed',
                'value' => esc_attr($query_vars['nibble_data_processed']),
            );
        }
    }

    return $query;
}

// See http://codex.wordpress.org/Plugin_API/Filter_Reference/cron_schedules
add_filter( 'cron_schedules', 'nibble_add_every_five_minutes' );
function nibble_add_every_five_minutes( $schedules ) {
    $schedules['every_five_minutes'] = array(
            'interval'  => 60 * 5,
            'display'   => __( 'Every 5 Minutes', 'nibble' )
    );
    return $schedules;
}

// Schedule an action if it's not already scheduled
if ( ! wp_next_scheduled( 'nibble_add_every_five_minutes' ) ) {
    wp_schedule_event( time(), 'every_five_minutes', 'nibble_add_every_five_minutes' );
}

// Hook into that action that'll fire every five minutes
add_action( 'nibble_add_every_five_minutes', 'nibble_custom_cron' );
function nibble_custom_cron() {
    //Quering orders not sent to Nibble yet
    //Not checking order older than new plugin version
    $orders = wc_get_orders( array( 'nibble_data_processed' => -1 ) );
    $max = 5;
    $done = 0;
    foreach ($orders as $order) {        
        try {
            nibble_send_confirmation($order->get_id());
            $done++;
        } catch (Exception $e) {
            //let's fail silently for now            
        }
        if ($done >= $max) {
            break;
        }
    }
}
