/**
 * AI Search Admin JavaScript
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		var aliases = aiSearchAdminData.aliases || [];

		// ==================== 1. RENDER ALIASES TABLE ====================
		function renderAliasesTable() {
			var $tbody = $('#ai-alias-table tbody');
			$tbody.empty();

			if (!aliases || aliases.length === 0) {
				$tbody.append('<tr><td colspan="4" class="ai-empty-text" style="text-align:center; padding:18px;">No custom location aliases defined. Click "Add Location Alias" to add one.</td></tr>');
				return;
			}

			aliases.forEach(function(item, index) {
				var tax = item.taxonomy || 'property_city';
				var slug = item.slug || '';
				var aliasStr = Array.isArray(item.aliases) ? item.aliases.join(', ') : (item.aliases || '');

				var rowHtml = '<tr data-index="' + index + '">' +
					'<td>' +
						'<select class="ai-alias-tax ai-select" style="width:100%;">' +
							'<option value="property_city"' + (tax === 'property_city' ? ' selected' : '') + '>City</option>' +
							'<option value="property_area"' + (tax === 'property_area' ? ' selected' : '') + '>Area / Neighborhood</option>' +
							'<option value="property_state"' + (tax === 'property_state' ? ' selected' : '') + '>State</option>' +
							'<option value="property_country"' + (tax === 'property_country' ? ' selected' : '') + '>Country</option>' +
						'</select>' +
					'</td>' +
					'<td>' +
						'<input type="text" class="ai-alias-slug ai-input" value="' + escapeHtml(slug) + '" placeholder="e.g. new-york-city" />' +
					'</td>' +
					'<td>' +
						'<input type="text" class="ai-alias-list ai-input" value="' + escapeHtml(aliasStr) + '" placeholder="e.g. NYC, New York, NY" />' +
					'</td>' +
					'<td style="text-align:center;">' +
						'<button type="button" class="button button-link-delete ai-delete-alias-btn" data-index="' + index + '" title="Remove mapping">&times;</button>' +
					'</td>' +
				'</tr>';

				$tbody.append(rowHtml);
			});
		}

		function escapeHtml(str) {
			return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
		}

		// Initial render
		renderAliasesTable();

		// Add new alias row
		$('#ai-add-alias-row-btn').on('click', function(e) {
			e.preventDefault();
			collectAliasesFromDOM();
			aliases.push({
				taxonomy: 'property_city',
				slug: '',
				aliases: []
			});
			renderAliasesTable();
		});

		// Delete alias row
		$(document).on('click', '.ai-delete-alias-btn', function(e) {
			e.preventDefault();
			var index = parseInt($(this).data('index'), 10);
			if (!isNaN(index) && confirm(aiSearchAdminData.i18n.delete_alias_confirm)) {
				collectAliasesFromDOM();
				aliases.splice(index, 1);
				renderAliasesTable();
			}
		});

		function collectAliasesFromDOM() {
			var updated = [];
			$('#ai-alias-table tbody tr').each(function() {
				var $tr = $(this);
				var tax = $tr.find('.ai-alias-tax').val();
				var slug = $.trim($tr.find('.ai-alias-slug').val());
				var aliasStr = $.trim($tr.find('.ai-alias-list').val());

				if (slug || aliasStr) {
					var list = aliasStr.split(',').map(function(s) { return $.trim(s); }).filter(Boolean);
					updated.push({
						taxonomy: tax || 'property_city',
						slug: slug,
						aliases: list
					});
				}
			});
			aliases = updated;
		}

		// Add Miss as Alias
		$(document).on('click', '.ai-add-miss-as-alias-btn', function(e) {
			e.preventDefault();
			var phrase = $(this).data('phrase');
			collectAliasesFromDOM();
			aliases.push({
				taxonomy: 'property_city',
				slug: '',
				aliases: [phrase]
			});
			renderAliasesTable();
			$('html, body').animate({
				scrollTop: $('#ai-alias-table').offset().top - 100
			}, 400);
		});

		// Dismiss Miss
		$(document).on('click', '.ai-dismiss-miss-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var phrase = $btn.data('phrase');
			var $row = $btn.closest('tr');

			$.post(aiSearchAdminData.ajaxUrl, {
				action: 'ai_search_houzi_remove_miss',
				security: aiSearchAdminData.nonce,
				phrase: phrase
			}, function(res) {
				$row.fadeOut(200, function() {
					$(this).remove();
				});
			});
		});

		// ==================== 2. TOGGLE KEY VISIBILITY ====================
		$('#ai-toggle-key-visibility').on('click', function(e) {
			e.preventDefault();
			var $input = $('#ai_search_houzi_api_key');
			var isPassword = $input.attr('type') === 'password';
			$input.attr('type', isPassword ? 'text' : 'password');
			$(this).find('.dashicons').toggleClass('dashicons-visibility dashicons-hidden');
		});

		// ==================== 3. TEST CONNECTION ====================
		$('#ai-test-connection-btn').on('click', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var $feedback = $('#ai-test-feedback');
			var provider = $('#ai_search_houzi_provider').val();
			var apiKey = $('#ai_search_houzi_api_key').val();
			var model = $('#ai_search_houzi_model').val();

			$btn.prop('disabled', true).addClass('updating-message');
			$feedback.removeClass('ai-feedback-success ai-feedback-error').text(aiSearchAdminData.i18n.testing).slideDown(200);

			$.post(aiSearchAdminData.ajaxUrl, {
				action: 'ai_search_houzi_test_connection',
				security: aiSearchAdminData.nonce,
				provider: provider,
				api_key: apiKey,
				model: model
			}, function(res) {
				$btn.prop('disabled', false).removeClass('updating-message');
				if (res.success && res.data) {
					var data = res.data;
					$feedback.addClass('ai-feedback-success').html(
						'<strong>✓ Connected successfully!</strong><br/>' +
						'Status: HTTP ' + data.status_code + ' | Latency: ' + data.latency_ms + ' ms | Model: ' + (data.model || provider) + '<br/>' +
						'<em>Response: "' + escapeHtml(data.message) + '"</em>'
					);
				} else {
					var errMsg = (res.data && res.data.message) ? res.data.message : 'Unknown connection error';
					var code = (res.data && res.data.status_code) ? ' (HTTP ' + res.data.status_code + ')' : '';
					$feedback.addClass('ai-feedback-error').html(
						'<strong>✗ Connection failed' + code + '</strong><br/>' + escapeHtml(errMsg)
					);
				}
			}).fail(function() {
				$btn.prop('disabled', false).removeClass('updating-message');
				$feedback.addClass('ai-feedback-error').text('AJAX connection error.');
			});
		});

		// ==================== 4. SAVE SETTINGS ====================
		function saveSettings() {
			collectAliasesFromDOM();

			var $topBtn = $('#ai-save-settings-top-btn');
			var $sideBtn = $('#ai-save-settings-side-btn');
			var $feedback = $('#ai-save-feedback');

			$topBtn.prop('disabled', true);
			$sideBtn.prop('disabled', true);
			$feedback.removeClass('ai-feedback-success ai-feedback-error').text(aiSearchAdminData.i18n.saving).slideDown(200);

			var formData = $('#ai-search-settings-form').serializeArray();
			var postData = {
				action: 'ai_search_houzi_save_settings',
				security: aiSearchAdminData.nonce,
				ai_search_houzi_location_aliases: JSON.stringify(aliases)
			};

			formData.forEach(function(item) {
				postData[item.name] = item.value;
			});

			// If checkbox is unchecked, explicitly pass 0
			if (!$('input[name="ai_search_houzi_enabled"]').is(':checked')) {
				postData['ai_search_houzi_enabled'] = '0';
			}
			if (!$('input[name="ai_search_houzi_location_resolver_enabled"]').is(':checked')) {
				postData['ai_search_houzi_location_resolver_enabled'] = '0';
			}

			$.post(aiSearchAdminData.ajaxUrl, postData, function(res) {
				$topBtn.prop('disabled', false);
				$sideBtn.prop('disabled', false);

				if (res.success) {
					$feedback.addClass('ai-feedback-success').text(aiSearchAdminData.i18n.saved);
					setTimeout(function() {
						$feedback.fadeOut(400);
					}, 3000);
				} else {
					var err = (res.data && res.data.message) ? res.data.message : 'Save error';
					$feedback.addClass('ai-feedback-error').text(err);
				}
			}).fail(function() {
				$topBtn.prop('disabled', false);
				$sideBtn.prop('disabled', false);
				$feedback.addClass('ai-feedback-error').text('Network error while saving.');
			});
		}

		$('#ai-save-settings-top-btn, #ai-save-settings-side-btn').on('click', function(e) {
			e.preventDefault();
			saveSettings();
		});

		// ==================== 5. INSTALL HOMEPAGE ====================
		$('#ai-install-homepage-btn').on('click', function(e) {
			e.preventDefault();
			runHomepageInstall(false);
		});

		function runHomepageInstall(overwrite) {
			var $btn = $('#ai-install-homepage-btn');
			var $feedback = $('#ai-install-feedback');
			var preserveSections = $('#ai-preserve-sections-chk').is(':checked');

			$btn.prop('disabled', true);
			$feedback.removeClass('ai-feedback-success ai-feedback-error').text(aiSearchAdminData.i18n.installing).slideDown(200);

			$.post(aiSearchAdminData.ajaxUrl, {
				action: 'ai_search_houzi_install_homepage',
				security: aiSearchAdminData.nonce,
				overwrite: overwrite ? 'true' : 'false',
				preserve_sections: preserveSections ? 'true' : 'false'
			}, function(res) {
				$btn.prop('disabled', false);


				if (res.success && res.data) {
					var data = res.data;
					$feedback.addClass('ai-feedback-success').html(
						'<strong>✓ ' + escapeHtml(data.message) + '</strong><br/>' +
						'<a href="' + escapeHtml(data.view_url) + '" target="_blank" class="button button-small" style="margin-top:6px;">View Homepage</a> ' +
						'<a href="' + escapeHtml(data.edit_url) + '" target="_blank" class="button button-small" style="margin-top:6px;">Edit with Elementor</a>'
					);
				} else if (res.data && res.data.exists) {
					if (confirm(aiSearchAdminData.i18n.confirm_overwrite)) {
						runHomepageInstall(true);
					} else {
						$feedback.addClass('ai-feedback-success').html(
							'<strong>Page is available:</strong><br/>' +
							'<a href="' + escapeHtml(res.data.view_url) + '" target="_blank" class="button button-small" style="margin-top:6px;">View Page</a> ' +
							'<a href="' + escapeHtml(res.data.edit_url) + '" target="_blank" class="button button-small" style="margin-top:6px;">Edit with Elementor</a>'
						);
					}
				} else {
					var err = (res.data && res.data.message) ? res.data.message : 'Installation error';
					$feedback.addClass('ai-feedback-error').text(err);
				}
			}).fail(function() {
				$btn.prop('disabled', false);
				$feedback.addClass('ai-feedback-error').text('Network error during installation.');
			});
		}


		// ==================== 6. LICENSE ACTIVATION & DEACTIVATION ====================
		$('#ai-activate-license-btn').on('click', function(e) {
			e.preventDefault();

			var $btn = $(this);
			var $feedback = $('#ai-license-feedback');
			var licenseKey = $.trim($('#ai_search_houzi_license_key').val());

			if (!licenseKey) {
				$feedback.removeClass('ai-feedback-success').addClass('ai-feedback-error').text('Please enter a valid purchase code / license key.').slideDown(200);
				return;
			}

			$btn.prop('disabled', true);
			$feedback.removeClass('ai-feedback-success ai-feedback-error').text('Verifying purchase code with Envato...').slideDown(200);

			$.post(aiSearchAdminData.ajaxUrl, {
				action: 'ai_search_houzi_activate_license',
				security: aiSearchAdminData.nonce,
				license_key: licenseKey
			}, function(res) {
				$btn.prop('disabled', false);

				if (res.success) {
					var msg = (res.data && res.data.msg) ? res.data.msg : 'License verified successfully!';
					$feedback.addClass('ai-feedback-success').text(msg);
					setTimeout(function() {
						location.reload();
					}, 1000);
				} else {
					var errMsg = (res.data && res.data.message) ? res.data.message : 'Invalid purchase code, please check and try again.';
					$feedback.addClass('ai-feedback-error').text(errMsg);
				}
			}).fail(function() {
				$btn.prop('disabled', false);
				$feedback.addClass('ai-feedback-error').text('Network error during license verification. Please try again.');
			});
		});

		$('#ai-deactivate-license-btn').on('click', function(e) {
			e.preventDefault();

			if (!confirm('Are you sure you want to deactivate the license on this website?')) {
				return;
			}

			var $btn = $(this);
			var $feedback = $('#ai-license-feedback');

			$btn.prop('disabled', true);
			$feedback.removeClass('ai-feedback-success ai-feedback-error').text('Deactivating license...').slideDown(200);

			$.post(aiSearchAdminData.ajaxUrl, {
				action: 'ai_search_houzi_deactivate_license',
				security: aiSearchAdminData.nonce
			}, function(res) {
				$btn.prop('disabled', false);

				if (res.success) {
					$feedback.addClass('ai-feedback-success').text('License deactivated.');
					setTimeout(function() {
						location.reload();
					}, 800);
				} else {
					$feedback.addClass('ai-feedback-error').text('Could not deactivate license.');
				}
			}).fail(function() {
				$btn.prop('disabled', false);
				$feedback.addClass('ai-feedback-error').text('Network error during deactivation.');
			});
		});

	});

})(jQuery);


