jQuery(document).ready(function(){

	jQuery("body").on("click", ".wll_records_select_all", function(){

		jQuery(".wll_record_checkboxes").each(function( key, val ){

			jQuery(this).click();

		});

	});

});