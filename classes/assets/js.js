/* === Panel Switch */
jQuery(document).ready(function($){

	var jigoshop_select = $('#jigoshop_order_id');
	jigoshop_select.bind('change', function(e) {

		if(this.value == 'email') {
			$('#jigoshop_order_email').toggle();
			$('#jigoshop_order_ftp').toggle();
		}

		if(this.value == 'ftp') {
			$('#jigoshop_order_email').toggle();
			$('#jigoshop_order_ftp').toggle();
		}

	});

});