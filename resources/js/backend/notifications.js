jQuery(document).ready(function($) {
	jQuery(document).on('click', '.blink-review-notice button.notice-dismiss', function() {
		$.ajax({
			url: BlinkNotifications.ajax_url,
			type: 'post',
			data: {
				action: 'galoy_blink_notifications',
				nonce: BlinkNotifications.nonce
			}
		});
	});
});
