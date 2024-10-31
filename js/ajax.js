function nibbleGetCookie(name) {
  let cookie = {};
  document.cookie.split(';').forEach(function(el) {
    let [k,v] = el.split('=');
    cookie[k.trim()] = v;
  })
  return cookie[name];
}

function nibbleSmartCart() {
	var t = jQuery('nibblet');
	if (t && t.length > 0) {
		//console.log('token found');
		if (t.closest('form') && t.closest('form').length > 0) {
			return t.closest('form');
		}
		//console.log('token not inside a form');
	} else {
		//console.log('no token found');
	}
	//console.log('looking for form.cart');
	return jQuery('form.cart');
}

function nibbleGetButton() {
  let nibbleButton = jQuery('nibble-button');
  if (!nibbleButton || nibbleButton.length == 0) {
  	return null;
  }
	return nibbleButton.first();
}

function nibbleGetWrapper() {
	let nibbleButton = nibbleGetButton();
	if (!nibbleButton) {
		return null;		
	}
	let nibbleWrapper = nibbleButton.closest('div.nibble-button-wrapper');
	if (nibbleWrapper.length == 0){
		return null;
	}
	return nibbleWrapper;
}

function nibbleIsLoaded() {	
	return nibbleGetButton !== null;
}

function isCart() {
	let nibbleButton = nibbleGetButton();	
	return nibbleButton.attr('negotiation-type') === 'cart';
}

function nibbleSessionStartFunction(event) {

	const successCallback = event.detail[0];
	const errorCallback = event.detail[1];

	let nibbleButton = nibbleGetButton();	

	if (!nibbleButton) {
		console.log('Cannot find the button');
		return;
	}

	let nibbleWrapper = nibbleGetWrapper();
	if (!nibbleWrapper) {
		console.log('Cannot find nibble wrapper');
	}

	const product_id = nibbleWrapper.data('product_id');
	const product_type = nibbleWrapper.data('product_type');

	var variant_id = false;
	var price = false;
	var quantity = false;

	const f = nibbleSmartCart();
	if (isCart() === true) {
		var url = 'token='+nibbleGetCookie('nibble_token')+'&action=nibble_server_connect&product_id=0';
	} else if (!f || f.length == 0) {
		//Weird, better to do nothing
		console.log('Cannot find the product form');
		return;
	} else {
		//Product page
		if (product_type == 'simple') {
			//Getting product details
			//I've already all, getting only the quantity
			quantity = f.find('input[name=quantity]').first().val();
		} else if (product_type == 'variable'){
			variant_id = f.find('input[name=variation_id]').first().val();
			quantity = f.find('input[name=quantity]').first().val();		
		} else {
			console.log('Product type not supported');
			return;
		}

		var url = 'token='+nibbleGetCookie('nibble_token')+'&action=nibble_server_connect&product_id='+product_id+'&quantity='+quantity;
		if (undefined !== variant_id) {
			url = url + '&variant_id='+variant_id;
		}
	}

	jQuery.ajax({
        type: "POST",
        dataType: "html",
        url: nibble_connect.ajaxurl,
        data: url,
        success: function (data) {
        	//console.log(data);
        	successCallback(JSON.parse(data));
        },
        error: function (jqXHR, textStatus, errorThrown) {
          //console.log(jqXHR,textStatus,errorThrown);
          errorCallback(textStatus);
        }

    });
}

function nibbleSessionEndFunction(event) {

  console.log('Session end function');

  const nibbleID = event.detail[0];
  const sessionStatus = event.detail[1];
  const finalizeCallback = event.detail[2];

	let nibbleButton = nibbleGetButton();
	
	if (!nibbleButton) {
		//do nothing
		console.log('Cannot find button');
		return;
	}

	let nibbleWrapper = nibbleGetWrapper();
	if (!nibbleWrapper) {
		console.log('Cannot find nibble wrapper');
	}

	const product_id = nibbleWrapper.data('product_id');
	const product_type = nibbleWrapper.data('product_type');

  if (isCart()) {
  	var url = 'token='+nibbleGetCookie('nibble_token')+'&action=nibble_server_verify&product_id=0&nibble_id='+nibbleID;
  } else {
  	var url = 'token='+nibbleGetCookie('nibble_token')+'&action=nibble_server_verify&product_id='+product_id+'&nibble_id='+nibbleID;
  }

  if (sessionStatus !== 'successful') {
    finalizeCallback({close: true})
  }
  if (sessionStatus === 'successful') {
  	jQuery.ajax({
        type: "POST",
        dataType: "html",
        url: nibble_verify.ajaxurl,
        data: url,
        success: function (data) {
        	data = JSON.parse(data);
        	if (data.errors == false && data.add == true) {
        		if (!isCart()) {
	        		finalizeCallback({close: true});
	        		var o = nibbleSmartCart();
	        		o.find('button[type="submit"]').trigger('click');
	        	} else {
	        		//finalizeCallback({link: data.url, target: '_self'}); //Reload the page to apply the coupon
	        		window.location.href = data.url;
	        	}
        	} else {
        		console.log('Something wrong with these:',data);
        	}
        },
        error: function (jqXHR, textStatus, errorThrown) {
        	console.log(jqXHR,textStatus,errorThrown);
        }
    });
  }
}

function nibbleConfiguredFunction(event) {
	//console.log(event);
  document.dispatchEvent(new CustomEvent('nibble-cart-button-show'));	
}

function nibbleGetRecommendations(event) {
	const minimumValue = parseFloat(event.detail[0]); // minimum product price
	const successCallback = event.detail[1]; // invoke this callback with recommendations
	const errorCallback = event.detail[2]; // invoke this callback on error

  var data = {
  'action': 'nibble_cross_sell', // The action hook
  'minimumValue' : minimumValue
  };
	
	console.log('GET recommendations');   

	// Get recommendations
	jQuery.ajax({
        type: "POST",
        dataType: "html",
        url: nibble_signposting.ajaxurl,
        data: data,
        success: function (data) {
        	data = JSON.parse(data);
        	if (data.success == true) {
  					successCallback(data.data);
        	} else {
        		console.log('Ajax error', data.error);
        	}       	
        },
        error: function (jqXHR, textStatus, errorThrown) {        		
        	console.log('Something wrong in recommendations connection', textStatus);
        }
    });
	
}


function nibbleAddToCart(event) {
	const product = event.detail[0]; // the same product object from the recommendations
  const successCallback = event.detail[1]; // invoke this callback when complete
  
  // add 1 of the product to cart (using product.productId and product.subProductId)

  var nonce = jQuery('#nibble_add_to_cart_nonce').data('nonce');
  var productId = product.productId;
  var variationId = product.subproductId;

  var data = {
      'action': 'nibble_cart_add',
      'security': nonce,
      'product_id': productId,
      'variation_id': variationId
  };

	jQuery.ajax({
      type: "POST",
      dataType: "html",
      url: nibble_signposting.ajaxurl,
      data: data,
      success: function (data) {
      	data = JSON.parse(data);
      	if (data.success === true) {
					console.log(data);
					successCallback();
          window.location.href = data.data.cart_url;
      	} else {
      		console.log('Ajax error', data.error);
      	}       	
      },
      error: function (jqXHR, textStatus, errorThrown) {        		
      	console.log('Something wrong in add to cart connection', textStatus);
      }
  });
	
}

jQuery(document).ready(function(){

	var o = nibbleSmartCart();
	if (!o || o.length == 0) {
		return;
	}

	var b = o.find('button[type="submit"]');
	if (!b || b.length == 0) {
		return;
	}

	var variant_not_valid = false;

	var validVariations = [];

	var nibbleButton = nibbleGetButton();
	if (!nibbleButton) {
		console.log('Cannot find button');
	} 
	var nibbleWrapper = nibbleGetWrapper();
	if (!nibbleWrapper) {
		console.log('Cannot find wrapper');
		return;
	}

	if (nibbleWrapper.data('prodict_variations')) {
		validVariations = nibbleWrapper.data('prodict_variations').split(',');		
	}

	var observer = new MutationObserver(function(mutations) {
	  mutations.forEach(function(mutation) {
	  	var nb = nibbleGetButton();
	    if (jQuery(mutation.target).hasClass('disabled') || jQuery(mutation.target).is(':disabled') || variant_not_valid) {
	    	nb.css('visibility','hidden');
	    	nb.css('display','none');
	    	nb.attr('show', false);
	    } else {
	    	nb.css('visibility','visible');		
	    	nb.css('display','inherit');
	    	nb.attr('show', true);
	    }		    
	  });
	});

	//Observer for button not valid
	observer.observe(b[0], {
	  attributes: true,
	  attributeFilter: ['class','disabled']
	});

	//Observer for variant change
	o.on('change','input[name=variation_id]',function(e) {		
	  	const i = jQuery(this);
	  	if (window.nibble_current_product_type == 'variable') {
				const vid = i.val();
				variant_not_valid = false;
				if (vid >0) {
					if (!validVariations.includes(vid)) {
						variant_not_valid = true;
					}
				}		
				if (variant_not_valid) {
		    	nb = nibbleGetButton();
		    	nb.css('visibility','hidden');
		    	nb.css('display','none');
		    	nb.attr('show', false);					
				} else {			
				 	b.toggleClass('nibble-fake'); //to fire the mutator observer
				}
	  	}
	});
});

jQuery(document).ready(function(){

	const test = jQuery('#nibble_test');

	if (test && test.length > 0) {
		const testT = jQuery('#nibble_test_result');
		test.on('click',function(e) {
			e.stopPropagation();
		  testT.removeClass('nibble_test_success');
		  testT.removeClass('nibble_test_success');
			testT.html('testing...');
			var url = 'token='+nibbleGetCookie('nibble_token')+'&action=nibble_server_test&';
			jQuery.ajax({
		        type: "POST",
		        dataType: "html",
		        url: nibble_test.ajaxurl,
		        data: url,
		        success: function (data) {
		        	data = JSON.parse(data);
		        	if (data.status == true) {
		        		testT.addClass('nibble_test_success');	
		        		testT.removeClass('nibble_test_error');	        		
		        	} else {
		        		testT.removeClass('nibble_test_success');
		        		testT.addClass('nibble_test_error');
		        	}
		        	testT.html(data.message);
		        },
		        error: function (jqXHR, textStatus, errorThrown) {
	        		testT.removeClass('nibble_test_success');
	        		testT.addClass('nibble_test_error');
		        	testT.html('Something wrong in the connection');
		        }
		    });
			return false;
		});
	}
});



function nibble_add_listeners(node) {
  //console.log('Adding listeners to nibble button');
	node.addEventListener('nibble-session-start', nibbleSessionStartFunction);
  node.addEventListener('nibble-session-end', nibbleSessionEndFunction);
  node.addEventListener('nibble-configured', nibbleConfiguredFunction);
  node.addEventListener('nibble-get-recommendations', nibbleGetRecommendations);
  node.addEventListener('nibble-add-product-to-basket', nibbleAddToCart);
}

//Signposting ajax

jQuery(document).ready(function(){
	var doing = false
	jQuery(document.body).on('nibble_cart_update added_to_cart wc_cart_emptied updated_wc_div updated_cart_totals', function(e) {
		if (doing) {
			return;
		}
    //re-adding listeners

		const customButton = document.querySelector('nibble-button');

		// IF the nibble button is added via HTML
		if (customButton) { 
			nibble_add_listeners(customButton); 
		} else {
			//do nothing, there are no buttons in the page
			return;
		}

    //console.log('Cart updated!', e.type);    

    //Getting cart data

    var data = {
	  'action': 'nibble_cart', // The action hook
    };

    doing = true;

		jQuery.ajax({
	        type: "POST",
	        dataType: "html",
	        url: nibble_signposting.ajaxurl,
	        data: data,
	        success: function (response) {
	        	data = JSON.parse(response);
	        	if (data.success) {
	        		//console.log('CART DATA', data);     

    					window.dispatchEvent(new CustomEvent('nibble-cart-update', {detail:data.data}));	
  						//document.dispatchEvent(new CustomEvent('nibble-cart-button-show'));	
	        	} else {
	        		console.log('Ajax error', response)
	        	}
	        	doing = false;	        	
	        },
	        error: function (jqXHR, textStatus, errorThrown) {        		
	        	console.log('Something wrong in cart connection', textStatus);
	        	doing = false;
	        }
	    });
	});
})

// Adding Nibble button listeners

document.addEventListener("DOMContentLoaded", function() {

	const customButton = document.querySelector('nibble-button');

	// IF the nibble button is added via HTML
	if (customButton) { 
		nibble_add_listeners(customButton); 
	} else {
		//no button, doing nothing
		return;
	}


	// IF the nibblr button is added via JS but not JQUERY
  const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
          if (mutation.addedNodes.length > 0) {
              mutation.addedNodes.forEach((addedNode) => {
                  if (addedNode.nodeName.toLowerCase() === 'nibble-button') {
                      nibble_add_listeners(addedNode);
                  }
              });
          }
      });
  });	


	// Start observing the body (or other target) for childList changes
	observer.observe(document.body, { childList: true, subtree: true });

	jQuery(document).ready(function() {
		// Fire first event in page if I'm in cart
		if (isCart()) {
  		jQuery(document.body).trigger('nibble_cart_update');	
  	}
	})
});
