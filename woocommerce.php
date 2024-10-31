<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Nibble_Settings_Tab {

    public static function init() {
        add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
        add_action( 'woocommerce_settings_tabs_nibble_settings_tab', __CLASS__ . '::settings_tab' );
        add_action( 'woocommerce_update_options_nibble_settings_tab', __CLASS__ . '::update_settings' );
    }
    
    
    public static function add_settings_tab( $settings_tabs ) {
        $settings_tabs['nibble_settings_tab'] = __( 'Nibble', 'nibble-settings-tab' );
        return $settings_tabs;
    }


    public static function settings_tab() {
        woocommerce_admin_fields( self::get_settings() );
    }


    public static function update_settings() {
        woocommerce_update_options( self::get_settings() );
    }


    public static function get_settings() {

        $defaults = [];
        global $nibbleWoocommerceConnector;
        $defaults['custom_css'] = $nibbleWoocommerceConnector->getCustomCss(true); //Get it raw
        if ($defaults['custom_css'] === null || $defaults['custom_css'] === false) {
            $defaults['custom_css'] = '.nibble-button-wrapper {
  margin-bottom: 1em;
}';
        }        
        $settings = array(
            'section1_title' => array(
                'name'     => __( 'API settings', 'nibble-settings-tab' ),
                'type'     => 'title',
                'desc'     => '
                    <p>Please contact <a href="mailto:hello@nibble.team" target="_blank">hello@nibble.team</a> to get subscribed.</p>
                    <p>
                    We will then issue you API Credentials and login details to our Nibble Admin Panel.
                    </p>
                ',
                'id'       => 'nibble_section1_title'
            ),           
            'api_url' => array(
                'name' => __( 'API url', 'nibble-settings-tab' ),
                'type' => 'text',
                'desc' => __( 'The API url provided by Nibble', 'nibble-settings-tab' ),
                'default' => 'https://api.nibble.website/v1/',
                'id'   => 'nibble_api_url',
                'class' => 'nibble_hidden',
            ),
            'api_key' => array(
                'name' => __( 'API Key', 'nibble-settings-tab' ),
                'type' => 'text',
                'desc' => __( 'The API Key provided by Nibble', 'nibble-settings-tab' ),
                'id'   => 'nibble_api_key'
            ),
            'secret_key' => array(
                'name' => __( 'API Secret', 'nibble-settings-tab' ),
                'type' => 'password',
                'desc' => __( 'The SECRET Key provided by Nibble', 'nibble-settings-tab' ).'<br/><br/><a id="nibble_test" href="#">Test credentials</a> (save before testing) <span id="nibble_test_result"></span>',
                'id'   => 'nibble_secret_key'
            ),		
            'section1_end' => array(
                 'type' => 'sectionend',
                 'id' => 'nibble_section1_end'
            ),
            'section2_title' => array(
                'name'     => __( 'Nibble settings', 'nibble-settings-tab' ),
                'type'     => 'title',
                'id'       => 'nibble_section2_title'
            ),           
            'nibble_discount_type' => array(
                'title'         => __( 'Cart discount coupon type'),
                'desc'          => __( 'Nibble will calculate coupon discounts using a fixed amount to avoid rounding errors;<br/><strong>on a multicurrecy store you should use a percentage one</strong> to avoid conversion errors. <br/>Please keep in mind that small rounding errors are possible as discount percentual is rounded to three decimal places.' ),
                'id'            => 'nibble_discount_type',
                'type'     => 'select',
                'options'  => array(
                    'fixed'      => __( 'Fixed amount', 'woocommerce' ),
                    'percentual' => __( 'Percentage (Beta feature)', 'woocommerce' ),
                ),
                'default'  => 'fixed'
            ),
            'section2_end' => array(
                 'type' => 'sectionend',
                 'id' => 'nibble_section2_end'
            ),            
            'section3_title' => array(
                'name'     => __( 'Signposting settings (Beta)', 'nibble-signposting-settings-tab' ),
                'type'     => 'title',
                'id'       => 'nibble_section3_title'
            ),
            'nibble_cross_sell_id' => array(
                'name' => __( 'Fallback product ID', 'nibble-settings-tab' ),
                'type' => 'text',
                'desc' => __( '<p>If there are no products with configured cross-sell in the cart, these ones will be used as a fallback. Configure the general cross-sell products in the product\'s Linked products tab<br/>If no product id is set, <strong>the signposting feature (AOV) will be disabled</strong> for products without configured cross-sell</p>', 'nibble-settings-tab' ),
                'id'   => 'nibble_cross_sell_id'
            ),
            'section3_end' => array(
                 'type' => 'sectionend',
                 'id' => 'nibble_section3_end'
            ),
            'section4_title' => array(
                'name'     => __( 'Frontend settings', 'nibble-settings-tab' ),
                'type'     => 'title',
                'desc'     => '<p>
                                <strong>Please note:</strong> after to enable the Nibble widget below it will not immediately display on your website. You now need to setup your rules in the Nibble Admin Panel at <a href="https://admin.nibble.website" target="_blank">https://admin.nibble.website</a>.                                
                                </p>',
                'id'       => 'nibble_section4_title'
            ),            
            'nibble_enable' => array(
                'title'         => __( 'Enable Nibble widget' ),
                'desc'          => __( 'Yes', 'nibble-settings-tab' ),
                'id'            => 'nibble_enable',
                'default'       => 'no',
                'type'          => 'checkbox'
            ),            
            'nibble_manual' => array(
                'title'         => __( 'I want to add the button manually to my theme' ),
                'desc'          => __( 'Yes', 'nibble-settings-tab' ),
                'id'            => 'nibble_manual',
                'default'       => 'no',
                'type'          => 'checkbox'
            ),
            'custom_css' => array(
                'name' => __( 'Custom css', 'nibble-settings-tab' ),
                'type' => 'textarea',
                'desc' => __( 'Do not add tags, this css will be added before the Nibble button in a style tag', 'nibble-settings-tab' ),
                'placeholder' => '.nibble-button-wrapper {
    ....
    margin-bottom: 1em;
}',
                'id'   => 'nibble_custom_css',                
            ),
            'nibble_cart_product' => array(
                'title'         => __( 'Allow Nibble on cart with nibbled products in it', 'nibble-settings-tab' ),
                'desc'          => __( 'Yes', 'nibble-settings-tab' ),
                'id'            => 'nibble_cart_product',
                'default'       => 'no',
                'type'          => 'checkbox'
            ),
            'section4_end' => array(
                 'type' => 'sectionend',
                 'id' => 'nibble_section4_end'
            ),
            'section5_title' => array(
                'name'     => __( 'Shortcodes', 'nibble-settings-tab' ),
                'type'     => 'title',
                'desc'     => '
                	<p>This shortcodes can be used to manually add the nibble button to a page if you do not want to use automatic buttons above</p>
					<p>
					<strong>Nibble product button</strong>: [nibble product_id="WOOCOMMERCE PRODUCT ID"] or [nibble] to get the current product
					</p>
					<p>
					<strong>Nibble cart button</strong>: [nibble_cart]
					</p>
                    <p>
                    If your theme is heavily customized or if you want to add Nibble\'s button outside the add to cart form you can use this shortcode, adding it to the product add to cart form.</br>
                    <strong>Nibble form locator</strong>: [nibble_token]
                    </p>
                ',
                'id'       => 'nibble_section5_title'
            ),
            'section5_end' => array(
                 'type' => 'sectionend',
                 'id' => 'nibble_section5_end'
            ),
        );

        foreach ($defaults as $k => $default) {
            if ('' !== $default && isset($settings[$k])) {
                $settings[$k]['value'] = $default;
            } 
        }

        return apply_filters( 'nibble_settings_page', $settings );
    }

}

Nibble_Settings_Tab::init();

class Nibble_WC_Tools {
    public function __construct() {

    }

    public function calculateCartToken($cart = false) {
        if (!$cart) {
            $cart = WC()->cart->get_cart();            
        }
        $cartData = array();
        foreach ($cart as $cart_item_key => $cart_item ) {  
           $product_id = $cart_item['product_id'];
           $quantity = $cart_item['quantity'];
           $cartData[] = 'P'.$product_id.'Q'.$quantity;
        }   
        asort($cartData);
        return implode(':',$cartData);
    }

    public function isNibbleCouponAlreadyApplied() {
        global $woocommerce;
        $coupons = $woocommerce->cart->get_applied_coupons();
        foreach ($coupons as $code) {
            if (strpos('-'.strtolower($code),'nb-') == 1) {
                return true;
            }
        }
        return false;
    }

    public function isNibbleDiscountApplied() {

        $cart = WC()->cart->get_cart(); 
        foreach ($cart as $cart_item_key => $cart_item ) {  
            if (isset($cart_item['nibble_price'])) {
                return true;
            }
        }   
        return false;
    }    

    public function areNotOnlyNibbledProducts() {

        $cart = WC()->cart->get_cart(); 
        foreach ($cart as $cart_item_key => $cart_item ) {  
            if (!isset($cart_item['nibble_price'])) {
                return true;
            }
        }   
        return false;
    }   

}

$NibbleWCTools = new Nibble_WC_Tools;

//WOOCOMMERCE RELATED FUNCTIONS

//WOOCOMMERCE CUSTOM FIELD

//PRODUCT LEVEL

//add_filter( 'woocommerce_product_data_tabs', 'nibble_add_product_data_tab', 99 , 1 );
function nibble_add_product_data_tab( $product_data_tabs ) {
    $product_data_tabs['nibble'] = array(
        'label' => __( 'Nibble', 'nibble' ), // translatable
        'target' => 'nibble_product_data', // translatable
    );
    return $product_data_tabs;
}

//add_action( 'woocommerce_product_data_panels', 'nibble_add_product_data_fields' );
function nibble_add_product_data_fields() {
    global $post;

    $post_id = $post->ID;

    echo '<div id="nibble_product_data" class="panel woocommerce_options_panel">';

    woocommerce_wp_text_input(
        array(
            'id' => 'nibble_product_id',
            'placeholder' => __('If not set, the SKU will be used instead','nibble'),
            'label' => __('NibbleID', 'nibble'),
            'description' => __('Type [NO] to disable Nibble for this product','nibble'),
            'desc_tip' => 'true'
        )
    );

    echo '</div>';
}

//add_action('woocommerce_process_product_meta', 'nibble_product_custom_fields_save' );
function nibble_product_custom_fields_save($post_id)
{
    $nibble_id = sanitize_text_field(nibble_get_from_post('nibble_product_id'));
    if (!empty($nibble_id)) {
        update_post_meta($post_id, 'nibble_product_id', esc_attr($nibble_id));
    }
}

//VARIATIONS

//add_action( 'woocommerce_variation_options_pricing', 'nibble_add_custom_field_to_variations', 10, 3 );
 
function nibble_add_custom_field_to_variations( $loop, $variation_data, $variation ) {
   woocommerce_wp_text_input( array(
    'id' => 'nibble_subproduct_id[' . $loop . ']',
    'class' => 'full',
    'label' => __( 'NibbleID', 'nibble' ),
    'value' => get_post_meta( $variation->ID, 'nibble_subproduct_id', true ),
    'placeholder' => __('If not set, the SKU will be used instead.'),
    'description' => __('Type [NO] to disable Nibble for this variation or [PARENT] to use the main product NibbleID'),
    'desc_tip' => 'true'
       ) );
}
 
//add_action( 'woocommerce_save_product_variation', 'nibble_save_custom_field_variations', 10, 2 );
 
function nibble_save_custom_field_variations( $variation_id, $i ) {
   $nibble_subproduct_id = sanitize_text_field($_POST['nibble_subproduct_id'][$i]);
   if ( isset( $nibble_subproduct_id ) ) update_post_meta( $variation_id, 'nibble_subproduct_id', esc_attr( $nibble_subproduct_id ) );
}
 
//add_filter( 'woocommerce_available_variation', 'nibble_add_custom_field_variation_data' );
 
function nibble_add_custom_field_variation_data( $variations ) {
   $variations['nibble_subproduct_id'] = '<div class="woocommerce_nibble_subproduct_id">Nibble sub-product ID: <span>' . get_post_meta( $variations[ 'variation_id' ], 'nibble_subproduct_id', true ) . '</span></div>';
   return $variations;
}