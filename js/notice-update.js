jQuery(document).on( 'click', '.wll-update-notice .notice-dismiss', function() {
  console.log("clicked");

    jQuery.ajax({
        url: ajaxurl,
        data: {
            action: 'save_update_notice'
        }
    })
    console.log("after ajax");
})
