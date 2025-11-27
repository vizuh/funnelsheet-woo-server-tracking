/**
 * WooCommerce Server-Side Tracking - Admin JavaScript
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Show/hide fields based on destination type
		function toggleDestinationFields() {
			var destination = $('input[name="fwst_destination_type"]:checked').val();
			
			if (destination === 'ga4') {
				$('.fwst-ga4-field').show();
				$('.fwst-sgtm-field').hide();
			} else {
				$('.fwst-ga4-field').hide();
				$('.fwst-sgtm-field').show();
			}
		}

		// Initial toggle
		toggleDestinationFields();

		// Toggle on change
		$('input[name="fwst_destination_type"]').on('change', toggleDestinationFields);

		// Test event button
		$('#fwst-test-event').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var $result = $('#fwst-test-result');
			
			// Disable button and show loading
			$button.prop('disabled', true).addClass('loading');
			$result.removeClass('success error').text('');
			
			// Send AJAX request
			$.ajax({
				url: fwstAdmin.ajax_url,
				type: 'POST',
				data: {
					action: 'fwst_test_event',
					nonce: fwstAdmin.nonce
				},
				success: function(response) {
					$button.prop('disabled', false).removeClass('loading');
					
					if (response.success) {
						$result.addClass('success').text('✓ ' + response.data.message);
					} else {
						$result.addClass('error').text('✗ ' + response.data.message);
					}
				},
				error: function() {
					$button.prop('disabled', false).removeClass('loading');
					$result.addClass('error').text('✗ Network error. Please try again.');
				}
			});
		});

		// Retry event button
		$('.fwst-retry-event').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var eventId = $button.data('event-id');
			
			if (!confirm('Are you sure you want to retry this event?')) {
				return;
			}
			
			// Disable button and show loading
			$button.prop('disabled', true).addClass('loading');
			
			// Send AJAX request
			$.ajax({
				url: fwstAdmin.ajax_url,
				type: 'POST',
				data: {
					action: 'fwst_retry_event',
					nonce: fwstAdmin.nonce,
					event_id: eventId
				},
				success: function(response) {
					if (response.success) {
						// Reload page to show updated status
						location.reload();
					} else {
						alert('Error: ' + response.data.message);
						$button.prop('disabled', false).removeClass('loading');
					}
				},
				error: function() {
					alert('Network error. Please try again.');
					$button.prop('disabled', false).removeClass('loading');
				}
			});
		});

		// Export CSV button
		$('#fwst-export-csv').on('click', function(e) {
			e.preventDefault();
			
			var statusFilter = $('#status-filter').val();
			var url = fwstAdmin.ajax_url + '?action=fwst_export_csv&nonce=' + fwstAdmin.nonce + '&status=' + statusFilter;
			
			// Trigger download
			window.location.href = url;
		});
	});

})(jQuery);
