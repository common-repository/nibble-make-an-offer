<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class NibbleWoocommerceConnector {

	private $options = false;
	private $wc_session_id = false;
	private $token = false;
	private $devMode = false;


	public function __construct() {
		$this->loadSettings();
		$this->buildSessionData();
	}

	private function getProtectedValue($obj, $name) {
		if ($obj === null) {
			//I'm likely in the admin
			return null;
		}
	    $reflectionClass = new ReflectionClass(get_class($obj));
	    $reflectionProperty = $reflectionClass->getProperty($name);
	    $reflectionProperty->setAccessible(true);
	    return $reflectionProperty->getValue($obj);
	}

	private function  loadSettings() {
		$this->options = array(
			'nibble_api_url' => get_option('nibble_api_url', 'https://api.nibble.website/v1/' ),
			'nibble_api_key' => get_option('nibble_api_key'),
			'nibble_secret_key' => get_option('nibble_secret_key'),
			'nibble_on_product_page' => get_option('nibble_on_product_page', 'no'),
			'nibble_on_cart_page' => get_option('nibble_on_cart_page', 'no'),
			'nibble_cart_product' => get_option('nibble_cart_product', 'no'),
			'nibble_enable' => get_option('nibble_enable', 'no'),
			'nibble_manual' => get_option('nibble_manual', 'no'),
			'nibble_custom_css' => get_option('nibble_custom_css', null),
			'nibble_discount_type' => get_option('nibble_discount_type', 'fixed'),
			'nibble_cross_sell_id' => get_option('nibble_cross_sell_id', null)
		);
	}

	private function buildSessionData() {
		$session = WC()->session;
		$this->wc_session_id =  $this->getProtectedValue($session,'_cookie');
		if (isset($_COOKIE['nibble_token'])) {
			$this->token = sanitize_text_field($_COOKIE['nibble_token']);
		} else {
			//Token not valid or expired, rebuild it
			$this->token = $this->buildToken();
		}
		$expiration_time = time()+(60*60*3);
		setcookie( 'nibble_token', $this->token, $expiration_time, COOKIEPATH, COOKIE_DOMAIN );
	}

	public function unsetSession() {
		if (isset($_COOKIE['nibble_token'])) {
		    unset($_COOKIE['nibble_token']); 
		    setcookie('nibble_token', null, -1, COOKIEPATH, COOKIE_DOMAIN ); 
		    return true;
		}
		return false;
	}

	public function isDev() {
		return $this->devMode;
	}

	public function setDev() {
		$this->devMode = true;
	}

	public function setProd() {
		$this->devMode = false;
	}

	public function buildSessionId($product_id = false, $token = false) {
		if (!$token) {
			$token = $this->token;
		}		
		return md5(get_current_user_id().$token.($product_id ? $product_id : 0));
	}

	public function arePermalinksEnabled() {
		return get_option('permalink_structure');
	}

	public function buildToken() {
		//Let's add a 3 hrs max expiration to session, not 2 days as in woocommerce 
		//I'm checking the current quarter and adding 3 hrs expiration
		$hour = (int)date('H');
		$adjustedHour = ($hour - 1) < 0 ? 23 : $hour - 1; // Shift the day start to 1AM.
		$quarter = floor($adjustedHour / 3);
		$date = ($hour - 1) < 0 ? date('Ymd', strtotime('-1 day')) : date('Ymd'); // If the hour is 00, use the previous day's date.
		$token = md5($this->getSecretKey() . $this->wc_session_id . $quarter . $date);
		return $token;
	}

	public function checkValidSession($session_id, $token, $product_id) {
		return ($this->buildSessionId($product_id, $token) === $session_id);
	}

	public function getApiUrl() {
		if (!$this->options) {
			$this->loadSettings();
		}
		return $this->options['nibble_api_url'];		
	}

	public function getApiKey() {
		if (!$this->options) {
			$this->loadSettings();
		}
		return $this->options['nibble_api_key'];
	}

	private function getSecretKey() {
		if (!$this->options) {
			$this->loadSettings();
		}
		return $this->options['nibble_secret_key'];
	}	

	public function getCrossSellId() {
		if (!$this->options) {
			$this->loadSettings();
		}
		return (int)$this->options['nibble_cross_sell_id'];		
	}

	public function getProductId($product_id = false, $variation = false) {
		if (!$product_id) {
			return false;
		}
		if (is_object($product_id)) {
			//TODO: check that it is a WC product
			$product = $product_id;
		} else if (!$product =  wc_get_product( $product_id )) {
			return false;
		}
		if (!$variation) {
			$npid = $product->get_meta('nibble_product_id', true);
		} else {
			$npid = $product->get_meta('nibble_subproduct_id', true);
			if (strtoupper($npid) == '[PARENT]') {
				$npid = $product->get_meta('nibble_product_id', true);
			}			
		}
		if (!$npid || '' == $npid) {
			//Default to Product SKU
			$npid = $product->get_sku();
		}
		if (strtoupper($npid) == '[NO]') {
			$npid = false;
		}
		return $npid;
	}

	public function getMaxQuantity($product) {
		$stock = $product->get_stock_quantity();
		if (is_null($stock)) {
			return false; //It is not managing it
		} else {
			$backorders = $product->get_backorders();
			if ('yes' == $backorders) {
				return false; //Not limited
			} 
			if ((int)$stock > 0) {
				return (int)$stock;
			}
		}
		return 0;
	}
 
    public function getNibbleVariationsString($product, $separator = ',') {
        $variations = array();
        if ($product && !is_object($product)) {
            $product =  wc_get_product( $product_id );        
        }
        if ($product) {
            foreach ($product->get_available_variations() as $variation) {
                $nibble_subproduct_id = $this->getProductId($variation['variation_id'], true);
                if ($nibble_subproduct_id) {
                    $variations[] = $variation['variation_id'];
                }
            }
        }
        return implode($separator,$variations);
    }

    public function isNibbleManual() {
    	return $this->options['nibble_manual'] == 'yes';
    }

    public function canNibble() {
    	return $this->options['nibble_enable'] == 'yes';
    }

	public function canAddToProductPage() {
		return !$this->isNibbleManual();
		//return $this->options['nibble_on_product_page'] == 'yes';
	}

	public function canAddToCartPage() {
		return !$this->isNibbleManual();
		//return $this->options['nibble_on_cart_page'] == 'yes';
	}

	public function canNibbleonNibble() {
		return $this->options['nibble_cart_product'] == 'yes';
	}

	public function createConnection($product_id, $quantity = 1, $variation_id = false, $cart = false) {

		if (!$cart) {

			if (!$product =  wc_get_product( $product_id )) {
				return false;
			}

			$nibble_product_id = $this->getProductId($product);

		}

		$url = $this->getApiUrl()."session";
		
		$headers = array(
	        'Content-Type' =>'application/json',
	        'X-Api-Key' => ''.$this->getApiKey(),
	        'X-Nibble-Api-Secret' => ''.$this->getSecretKey(),
	    );

		$data = array(
		  "retailerSessionId" => "".$this->buildSessionId($product_id, sanitize_text_field($_COOKIE['nibble_token'])),
		  "currencyCode" => "".get_woocommerce_currency(),
		);
		
		if (!$cart) {

			$data['productId'] = $nibble_product_id;
			$data['productName'] = $product->get_name();
			$data['productPrice'] = $product->get_price();
			$data['quantity'] = $quantity;
			$maxQuantity = $this->getMaxQuantity($product);
			if ($maxQuantity) {
				$data['maxQuantity'] = $maxQuantity;
			}

			$variation_name = false;
			$variation_nibble_id = false;
			if ($variation_id && $variation_id > 0) {
				//Checking variation validity
				$found = false;
				foreach ($product->get_available_variations() as $variation) {
					if ($variation['variation_id'] !== $variation_id) {
						continue;
					}
					$nibble_subproduct_id = $this->getProductId($variation_id, true);
					if ($nibble_subproduct_id) {
						$found = true;
						$variation_nibble_id = $nibble_subproduct_id;
						$nameparts = array($product->get_name());
						foreach (wc_get_product($variation['variation_id'])->get_variation_attributes() as $attr) {
				            $nameparts[] = wc_attribute_label( $attr ); //TODO: mind strange characters?
				        }
				        $variation_name = implode(' ',$nameparts);
				        break;
				    }
			    }
			    if (!$found) {
			    	//Variation not valid, proceeding with the base nibble product id
			   	} else {
					$data['subProductId'] = $variation_nibble_id;
					$data['subProductName'] = $variation_name;
					//I need to get the variation price
					$data['productPrice'] = wc_get_product($variation_id)->get_price();
				}
			}

		} else {
			$data["pageType"] = "cart";
			$data["negotiationType"] = "cart";
			$data["originalPrice"] = ''.WC()->cart->subtotal;
		}

		$args = array(
			'method'      => 'POST',
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => $headers,
			'body'        => json_encode($data),
			'cookies'     => array()
		);

		$request = wp_remote_post($url,$args);

		$output = wp_remote_retrieve_body($request);

		//var_dump($url, $args, $request, $output); die();

	    return $output; 
	}

	public function testConnection() {

		$url = $this->getApiUrl()."session";
		$headers = array(
	        'Content-Type' =>'application/json',
	        'X-Api-Key' => ''.$this->getApiKey(),
	        'X-Nibble-Api-Secret' => ''.$this->getSecretKey(),
	    );

		$data = array(
		  "retailerSessionId" => "".$this->buildSessionId(false, sanitize_text_field($_COOKIE['nibble_token'])),
		  "currencyCode" => "".get_woocommerce_currency(),
		);		

		$args = array(
			'method'      => 'POST',
		    'timeout'     => 60,
		    'redirection' => 5,
		    'blocking'    => true,
		    'httpversion' => '1.0',
		    'sslverify'	  => false,
			'headers'     => $headers,
			'body'        => json_encode($data),
			'cookies'     => array()
	    );

		$request = wp_remote_post($url, $args);

		$output = wp_remote_retrieve_body($request);

		//$code = wp_remote_retrieve_response_code($request);

	    return $output; 
	}

	public function verifyConnection($nibble_id, $token, $product_id, $isCart = false) {

		if (!$nibble_id || empty($nibble_id)) {
			return false;
		}

		if (!$isCart && !$product =  wc_get_product( $product_id )) {
			return false;
		}

		$url = $this->getApiUrl()."session/".$nibble_id;

		$headers = array(
	        'Content-Type' =>'application/json',
	        'X-Api-Key' => ''.$this->getApiKey(),
	        'X-Nibble-Api-Secret' => ''.$this->getSecretKey(),
	    );

		$data = array(
		);

		$args = array(
			'method'      => 'GET',
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => $headers,
	    );

		$request = wp_remote_request($url, $args);

		$output = wp_remote_retrieve_body($request);		

	    return $output; 
	}

	public function sendTransaction($order_id) {

		if (!$order_id) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ($order) {

		    $data = new stdClass();
		    $data->sales = array();
		    //Create sales data
		    //TODO: manage orders with MORE THAN 50 ITEMS
		    foreach ($order->get_items() as $item_id => $item ) {
		    	if (!isset($item['nibble_data'])) {	
		    		continue;
		    	}
		    	$sale = new stdClass;
		    	$sale->quantity  = $item->get_quantity();
		    	$sale->productId = $item['nibble_data']->productId;
		    	$sale->nibbleId = $item['nibble_data']->nibbleId;		    	
		    	$sale->purchaseDateTime = str_replace('+00:00', 'Z', $order->get_date_created()->date('Y-m-d\TH:iP')); //Backport to PHP < 8.0
		    	$sale->orderNumber = $order_id;
		    	$data->sales[] = $sale;
		    }

		    //Adding sales data for order coupons
		    $couponSession = get_post_meta( $order_id, 'nibble_cart_id', true );
		    if ($couponSession && '' != $couponSession) {
		    	$sale = new stdClass;
		    	$sale->nibbleId = $couponSession;
		    	$sale->purchaseDateTime = str_replace('+00:00', 'Z', $order->get_date_created()->date('Y-m-d\TH:iP')); //Backport to PHP < 8.0
		    	$sale->orderNumber = $order_id;
		    	$data->sales[] = $sale;
		    }

		    if (count($data->sales)>0) {
		    	//let's send, go on
		    } else {
		    	//Do nothing
		    	$output = 'Nothing to send';
		    	return $output;
		    }

		    $url = $this->getApiUrl()."sales";

			$headers = array(
		        'Content-Type' =>'application/json',
		        'X-Api-Key' => ''.$this->getApiKey(),
		        'X-Nibble-Api-Secret' => ''.$this->getSecretKey(),
		    );

			$args = array(
				'method'      => 'POST',
			    'timeout'     => 60,
			    'redirection' => 5,
			    'blocking'    => true,
			    'httpversion' => '1.0',
			    'sslverify'	  => false,
				'headers'     => $headers,
				'body'        => json_encode($data),
				'cookies'     => array()
		    );

			$request = wp_remote_post($url, $args);

			$output = wp_remote_retrieve_body($request);


		    return $output;
		}

		return false;

	}

	public function getCustomCss($raw = false) {
		//If getting the raw CSS I'm reloading the options
		//used in woocommerce settings page
		if (!$this->options || $raw) {
			$this->loadSettings();
		}
		$css = $this->options['nibble_custom_css'];
		if ($raw) {
			return $css;
		}
		$css = trim(strip_tags((string) $css)); //PHP8.1, strip tags warning if css is null
		if ('' != $css) {
			return '<style>'.$css.'</style>';
		}
		return '';
	}

	public function getCartdiscountType() {
		if (!$this->options) {
			$this->loadSettings();
		}
		return $this->options['nibble_discount_type'];
	}

}

add_action( 'woocommerce_init', 'nibble_woocommerce_connector_init', 30 );

$nibbleWoocommerceConnector = false;

function nibble_woocommerce_connector_init() {
	global $nibbleWoocommerceConnector;
	$nibbleWoocommerceConnector = new NibbleWoocommerceConnector();
}
