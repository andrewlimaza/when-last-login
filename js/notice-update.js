jQuery(document).on('click', '.wll-update-notice-newsletter .notice-dismiss', function () {
	jQuery.ajax({
		url: ajaxurl,
		data: {
			action: 'wll_hide_subscription_notice',
			nonce: wll_notice_update.nonce
		}
	});
});