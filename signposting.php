<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


// SIGNPOSTING FUNCTIONS - COMMON

function nibble_get_product_data($product_id) {
    $product = wc_get_product($product_id);
    
    // Check if product exists
    if (!$product) {
        return false;
    }

    /*
    name: "Product Name",
	price: "£25.00", // product price rendered in the current currency
    productId: "abcde",
    subProductId: "fghijk",
    url: "https://example.com/product",  // url of product detail page for this product
    imageUrl: "https://example.com/image.png" // url of thumbnail image for this product
    */

    $data = new stdClass();

    $data->productId = $product->get_id();
    $data->name = $product->get_name();
    $data->subproductId = null; //For now, only simple products are allowed
    $data->price = ''.html_entity_decode(get_woocommerce_currency_symbol()).$product->get_price();
    
    $image_id = $product->get_image_id();
    $imageUrl = wp_get_attachment_image_src($image_id, 'thumbnail');
    if ($imageUrl && count($imageUrl) > 0) {
    	$imageUrl = $imageUrl[0];
    } else {
    	$imageUrl = null;
    } 

    $data->imageUrl = $imageUrl;

    return $data;
}

function nibble_get_cross_sell_ids_by_product_id($product_id) {
    // Get the product object
    $product = wc_get_product($product_id);
    
    // Check if product exists
    if (!$product) {
        return false;
    }

    // Fetch cross-sell IDs for the product
    $cross_sell_ids = $product->get_cross_sell_ids();

    return $cross_sell_ids;
}

/* CART OPTIONS - REMOVE AFTER DEBUG */

/*
// $cart conditionals (if)
WC()->cart->is_empty()
WC()->cart->needs_payment()
WC()->cart->show_shipping()
WC()->cart->needs_shipping()
WC()->cart->needs_shipping_address()
WC()->cart->display_prices_including_tax()
 
// Get $cart totals
WC()->cart->get_cart_contents_count();
WC()->cart->get_cart_subtotal();
WC()->cart->subtotal_ex_tax;
WC()->cart->subtotal;
WC()->cart->get_displayed_subtotal();
WC()->cart->get_taxes_total();
WC()->cart->get_shipping_total();
WC()->cart->get_coupons();
WC()->cart->get_coupon_discount_amount( 'coupon_code' );
WC()->cart->get_fees();
WC()->cart->get_discount_total();
WC()->cart->get_total( 'edit' );
WC()->cart->total;
WC()->cart->get_tax_totals();
WC()->cart->get_cart_contents_tax();
WC()->cart->get_fee_tax();
WC()->cart->get_discount_tax();
WC()->cart->get_shipping_total();
WC()->cart->get_shipping_taxes();
  
// Loop over $cart items
foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
   $product = $cart_item['data'];
   $product_id = $cart_item['product_id'];
   $variation_id = $cart_item['variation_id'];
   $quantity = $cart_item['quantity'];
   $price = WC()->cart->get_product_price( $product );
   $subtotal = WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] );
   $link = $product->get_permalink( $cart_item );
   // Anything related to $product, check $product tutorial
   $attributes = $product->get_attributes();
   $whatever_attribute = $product->get_attribute( 'whatever' );
   $whatever_attribute_tax = $product->get_attribute( 'pa_whatever' );
   $any_attribute = $cart_item['variation']['attribute_whatever'];
   $meta = wc_get_formatted_cart_item_data( $cart_item );
}
 
// Get $cart customer billing / shipping
WC()->cart->get_customer()->get_billing_first_name();
WC()->cart->get_customer()->get_billing_last_name();
WC()->cart->get_customer()->get_billing_company();
WC()->cart->get_customer()->get_billing_email();
WC()->cart->get_customer()->get_billing_phone();
WC()->cart->get_customer()->get_billing_country();
WC()->cart->get_customer()->get_billing_state();
WC()->cart->get_customer()->get_billing_postcode();
WC()->cart->get_customer()->get_billing_city();
WC()->cart->get_customer()->get_billing_address();
WC()->cart->get_customer()->get_billing_address_2();
WC()->cart->get_customer()->get_shipping_first_name();
WC()->cart->get_customer()->get_shipping_last_name();
WC()->cart->get_customer()->get_shipping_company();
WC()->cart->get_customer()->get_shipping_country();
WC()->cart->get_customer()->get_shipping_state();
WC()->cart->get_customer()->get_shipping_postcode();
WC()->cart->get_customer()->get_shipping_city();
WC()->cart->get_customer()->get_shipping_address();
WC()->cart->get_customer()->get_shipping_address_2();
 
// Other stuff
WC()->cart->get_cross_sells();
WC()->cart->get_cart_item_tax_classes_for_shipping();
WC()->cart->get_cart_hash();
WC()->cart->get_customer();
*/

function nibble_get_cart_data($json = false) {

    $result = new stdClass();

    if (!WC()->cart) {
        //No cart available
        return array('data' => $result, 'status' => 404);
    }   

    if (WC()->cart->is_empty()) {
        return array('data' => $result, 'status' => 200);
    } 

    global $nibbleWoocommerceConnector;

    $originalTotalPrice = 0;
    $discountedTotalPrice = 0;

    $items = [];
    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		$product = $cart_item['data'];
		$productId = $cart_item['product_id'];
		$subProductId = null;
		if (isset($cart_item['variation_id']) && $cart_item['variation_id'] > 0) {
			$subProductId = $cart_item['variation_id'];
		}
		$quantity = $cart_item['quantity'];
		$nibbleSessionId = null;
		if (isset($cart_item[ 'nibble_price' ])) {
			$originalPrice = $cart_item['nibble_data']->originalPrice * $cart_item['quantity'];
			$discountedPrice = $cart_item['nibble_data']->base_price * $cart_item['quantity'];
			$nibbleSessionId = $cart_item['nibble_data']->nibbleId;
		} else {
			$originalPrice = (float)$product->get_price() * (int)$cart_item['quantity'];
			$discountedPrice = $originalPrice;
		}  

		$isAddonItem = false; //If added as a FREE gift during negotiations

		$originalTotalPrice += $originalPrice;
		$discountedTotalPrice += $discountedPrice;

		$item = new stdClass();

		$item->retailerCartItemId = "".$cart_item_key;
		$item->nibbleSessionId = $nibbleSessionId;
		$item->quantity = $quantity;
		$item->productId = "".$productId;
		$item->subProductId = "".$subProductId;
		$item->originalPrice = "".number_format($originalPrice, 2);
		$item->discountedPrice = "".number_format($discountedPrice, 2);
		$item->isAddonItem = $isAddonItem;

		$items[] = $item;
	}

	$result->originalTotalPrice = "".number_format($originalTotalPrice,2);
	$result->discountedPrice = "".number_format($discountedTotalPrice,2);
	$result->items = $items;    


    $result->retailerSessionId = "".$nibbleWoocommerceConnector->buildSessionId(0, sanitize_text_field($_COOKIE['nibble_token']));
    $result->currencyCode = "".get_woocommerce_currency();

	return array('data' => $json ? json_encode($result) : $result, 'status' => 200);
}

function nibble_get_cross_sell_products($min_value = false, $json = false) {

    if (!WC()->cart) {
        //No cart available
        return array('data' => array(), 'status' => 404);
    }    

    if (WC()->cart->is_empty()) {
        return array('data' => array(), 'status' => 200);
    }

    global $nibbleWoocommerceConnector;

    // Get default Woocommerce cross-sell products in the cart
    $cross_sell_ids = WC()->cart->get_cross_sells();

    // Filter out non-visible products and products that aren't simple
    $cross_sell_ids = array_filter($cross_sell_ids, function($product_id) {
        $product = wc_get_product($product_id);
        return $product && $product->is_visible() && $product->is_type('simple');
    });

    // Get product IDs currently in the cart
    $products_in_cart = array_map(function($item) {
        return $item['product_id'];
    }, WC()->cart->get_cart()); 

    // Filter out products that are already in the cart (should be part of get_cross_sells but... whatever, let's be paranoiac )
    $cross_sell_ids = array_filter($cross_sell_ids, function($product_id) use ($products_in_cart) {
        return !in_array($product_id, $products_in_cart);
    });    


    // Filter out non-visible products and products that aren't simple    
    $cross_sell_ids = array_filter($cross_sell_ids, function($product_id) {
        $product = wc_get_product($product_id);
        return $product && $product->is_visible() && $product->is_type('simple');
    });

    // Filter out products with a value less than the minValue, if set

    if ($min_value > 0) {   
	    $cross_sell_ids = array_filter($cross_sell_ids, function($product_id, $min_value) {
	        $product = wc_get_product($product_id);
	        return $product && $product->get_price() >= $min_value;
	    });    	
    }

    // If there are not cross sell ids I'm using the fallback products
    $fallback_product_id = $nibbleWoocommerceConnector->getCrossSellId();
    if ((!$cross_sell_ids || count($cross_sell_ids) == 0) && $fallback_products_ids = nibble_get_cross_sell_ids_by_product_id($fallback_product_id)) {
        //Filtering (AGAIN) products that are already in the cart
        $cross_sell_ids =  array_filter($fallback_products_ids, function($product_id) use ($products_in_cart) {
            return !in_array($product_id, $products_in_cart);
        });
        // Filter out (AGAIN) non-visible products and products that aren't simple        
        $cross_sell_ids = array_filter($cross_sell_ids, function($product_id) {
            $product = wc_get_product($product_id);
            return $product && $product->is_visible() && $product->is_type('simple');
        });

    }

    // Filter out products with a value less than the minValue, if set (again these are the fallaback)

    if ($min_value > 0) {   
	    $cross_sell_ids = array_filter($cross_sell_ids, function($product_id, $min_value) {
	        $product = wc_get_product($product_id);
	        return $product && $product->get_price() >= $min_value;
	    });    	
    }

    if (!$cross_sell_ids) {
        return array('data' => array(), 'status' => 200);
    }    

    //Return products data formatted for signposting
    $data = array_map('nibble_get_product_data',$cross_sell_ids);
    return array('data' => $json ? json_encode($data) : $data, 'status' => 200);
}

// AJAX REST FOR SIGNPOSTING

function nibble_cross_sell() {

	$min_value = $_POST['minimumValue'] ?? false;
  
    $result = nibble_get_cross_sell_products($min_value);

    if ($result['status'] == 200) {
        wp_send_json_success($result['data'], $result['status']);
    }
    wp_send_json_error(['error' => 'Error retrieving cross-sell'], $result['status']);
}
  
add_action('wp_ajax_nopriv_nibble_cross_sell', 'nibble_cross_sell');
add_action('wp_ajax_nibble_cross_sell', 'nibble_cross_sell');

function nibble_cart() {
  
    $result = nibble_get_cart_data();

    if ($result['status'] == 200) {
        wp_send_json_success($result['data'], $result['status']);
    }
    wp_send_json_error(['error' => 'Error retrieving cart data'], $result['status']);
}
  
add_action('wp_ajax_nopriv_nibble_cart', 'nibble_cart');
add_action('wp_ajax_nibble_cart', 'nibble_cart');

function nibble_cart_add() {

    $nonce = isset($_POST['security']) ? $_POST['security'] : 'bad_nonce';
    $quantity = (isset($_POST['quantity']) && $_POST['quantity']) ? $_POST['quantity'] : 1;
  
    // Check for nonce security
    /*if (!wp_verify_nonce($nonce, 'add_to_cart_nonce')) {
        wp_send_json_error(['error' => 'Security check failed.']);
    }*/
    
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
    
    if (!$product_id) {
        wp_send_json_error(['error' => 'Invalid Product ID.']);
    }
    
    $product = wc_get_product($product_id);
    if (!$product || (!$product->is_purchasable() && !$product->is_in_stock())) {
        wp_send_json_error(['error' => 'Product is not purchasable or not in stock.']);
    }
    
    // If the user is not logged in or user can't purchase
    /*
    if (!is_user_logged_in() || !current_user_can('read_product', $product_id)) {
        wp_send_json_error(['error' => 'You do not have the capability to add items to the cart.']);
    }
    */

    if ($variation_id > 0) {
        //I need to get also the attributes or the cart will be displayed incorrectly
        // Prepare the attributes array
        $arr = array();
        foreach ($attributes as $attribute => $term_slug) {
            $arr['attribute_' . sanitize_title($attribute)] = sanitize_title($term_slug);
        }
        $added = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $arr, null);
    } else {
        $added = WC()->cart->add_to_cart($product_id, $quantity);
    }

    if(!$added) {
        wp_send_json_error(['error' => 'Failed to add product to cart.']);
    }

    wp_send_json_success(['cart_url' => wc_get_cart_url()]);
}
  
add_action('wp_ajax_nopriv_nibble_cart_add', 'nibble_cart_add');
add_action('wp_ajax_nibble_cart_add', 'nibble_cart_add');