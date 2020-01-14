jQuery(document).on( 'click', '.wll-update-notice-newsletter .notice-dismiss', function() {

    jQuery.ajax({
        url: ajaxurl,
        data: {
            action: 'wll_hide_subscription_notice'
        }
    })

});

jQuery(document).on( 'click', '#wll_subscribe_user', function(){

	var email_address = jQuery("#wll_user_subscribe_to_newsletter").val();
	var data = {
        action: 'wll_subscribe_user_newsletter',
        email: email_address
	}

	jQuery.post( ajaxurl, data, function( response ){
		if( response ){
			jQuery("#wll_user_subscribe_to_newsletter").attr( 'disabled', 'true');
			jQuery("#wll_subscribe_user").attr( 'disabled', 'true');
			jQuery(".wll-update-notice-newsletter").append("<p>You have been successfully subscribed to our newsletter and will receive your coupon code shortly. Thank you</p>");
		}

	});

});