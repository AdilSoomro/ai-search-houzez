/**
 * AI Search Frontend JavaScript
 * Multi-turn natural language search, live filter tag edits, animations & AJAX
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		$('.ai-search-hero-container').each(function() {
			var $container = $(this);
			var postsPerPage = parseInt($container.data('posts-per-page'), 10) || 4;

			var activeFilters = {};
			var conversationId = null;
			var taxonomiesData = null;
			var termNamesMap = {};

			var $heroHeader     = $container.find('.ai-search-hero-header');
			var $panelSearch    = $container.find('.ai-search-panel-search');
			var $panelResults   = $container.find('.ai-search-panel-results');
			var $searchInput    = $container.find('.ai-search-input');
			var $submitBtn      = $container.find('.ai-search-submit-btn');
			var $btnText        = $submitBtn.find('.ai-btn-text');
			var $btnLoader      = $submitBtn.find('.ai-btn-loader');
			var $btnArrow       = $submitBtn.find('.ai-btn-arrow');
			var $activeState    = $container.find('.ai-search-active-state');
			var $explanationBox = $container.find('.ai-explanation-box');
			var $explanationText= $container.find('.ai-explanation-text');
			var $tagsContainer  = $container.find('.ai-tags-container');
			var $refineSection  = $container.find('.ai-refinements-section');
			var $refineList     = $container.find('.ai-refinements-list');
			var $refineInput    = $container.find('.ai-refine-input');
			var $resultsGrid    = $container.find('.ai-results-grid');
			var $skeleton       = $container.find('.ai-results-skeleton');
			var $emptyState     = $container.find('.ai-results-empty');
			var $footer         = $container.find('.ai-results-footer');
			var $footerCount    = $container.find('.ai-footer-count');
			var $countBadge     = $container.find('.ai-results-count-badge');
			var $viewAllBtn     = $container.find('.ai-view-all-btn');

			// Pagination Controls
			var $paginationWrap = $container.find('.ai-pagination-wrap');
			var $pagePrevBtn    = $container.find('.ai-page-prev');
			var $pageNextBtn    = $container.find('.ai-page-next');
			var $pageCurrent    = $container.find('.ai-page-current');
			var $pageTotal      = $container.find('.ai-page-total');
			var currentPage     = 1;

			function formatTermName(slug, filterKey) {
				if (!slug || typeof slug !== 'string') {
					return slug;
				}
				if (termNamesMap[slug]) {
					return termNamesMap[slug];
				}
				// Clean title-case fallback: "single-family-home" -> "Single Family Home"
				return slug
					.split(/[-_]+/)
					.map(function(word) {
						var lower = word.toLowerCase();
						if (lower === 'ac') return 'AC';
						if (lower === 'tv') return 'TV';
						return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
					})
					.join(' ');
			}

			// ==================== 1. SEARCH QUERY SUBMISSION ====================

			function submitQuery(queryText, isRefinement) {
				if (!queryText || $.trim(queryText) === '') {
					$searchInput.focus();
					return;
				}

				currentPage = 1;

				if (!isRefinement) {
					activeFilters = {};
					conversationId = null;
				}

				setLoading(true);

				var postData = {
					action: 'ai_search_houzi_process_query',
					security: aiSearchPublicData.nonce,
					query: queryText,
					posts_per_page: postsPerPage,
					paged: currentPage,
					conversation_id: (isRefinement && conversationId) ? conversationId : '',
					filters: isRefinement ? activeFilters : {}
				};

				$.post(aiSearchPublicData.ajaxUrl, postData, function(res) {
					setLoading(false);

					if (res.success && res.data) {
						var data = res.data;
						activeFilters = data.filters || {};
						conversationId = data.conversation_id || conversationId;

						if (data.term_names) {
							$.extend(termNamesMap, data.term_names);
						}

						// Activate Split View
						$container.addClass('ai-is-active');
						$activeState.show();

						// Populate Explanation
						if (data.explanation) {
							$explanationText.text(data.explanation);
							$explanationBox.show();
						} else {
							$explanationBox.hide();
						}


						// Populate Interactive Tags
						renderTags(activeFilters);

						// Populate Refinements
						renderRefinements(data.suggestions || []);

						// Render Results
						renderResults(data.cards_html, data.total_count, data.view_all_url, data.paged, data.max_pages);

						// Smooth scroll to ensure results sit clearly in view below navbar
						if ($container.length && $(window).scrollTop() > $container.offset().top) {
							$('html, body').animate({
								scrollTop: Math.max(0, $container.offset().top)
							}, 300);
						}


					} else {
						var msg = (res.data && res.data.message) ? res.data.message : 'An error occurred during search.';
						alert(msg);
					}

				}).fail(function(xhr, textStatus, errorThrown) {
					setLoading(false);
					console.error('AI Search request failed:', textStatus, errorThrown, xhr.responseText);
					var errMsg = 'Search request failed. Please try again.';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errMsg = xhr.responseJSON.data.message;
					}
					alert(errMsg);
				});
			}

			function setLoading(isLoading) {
				if (isLoading) {
					$submitBtn.prop('disabled', true);
					$btnText.hide();
					$btnArrow.hide();
					$btnLoader.show();

					$skeleton.show();
					$resultsGrid.hide();
					$emptyState.hide();
					$footer.hide();
				} else {
					$submitBtn.prop('disabled', false);
					$btnLoader.hide();
					$btnText.show();
					$btnArrow.show();
					$skeleton.hide();
				}
			}

			// Main Form Submit
			$container.find('.ai-search-form').on('submit', function(e) {
				e.preventDefault();
				submitQuery($.trim($searchInput.val()), false);
			});

			// Enter Key Submit (Shift+Enter for new line)
			$searchInput.on('keydown', function(e) {
				if ((e.key === 'Enter' || e.which === 13) && !e.shiftKey) {
					e.preventDefault();
					submitQuery($.trim($searchInput.val()), false);
				}
			});


			// Suggested Prompts Click
			$container.find('.ai-prompt-chip').on('click', function(e) {
				e.preventDefault();
				var pText = $(this).attr('data-prompt') || $(this).data('prompt') || $(this).text().trim();
				$searchInput.val(pText);
				submitQuery(pText, false);
			});



			// Multi-turn Refinement Submit
			$container.find('.ai-refine-form').on('submit', function(e) {
				e.preventDefault();
				var refQuery = $.trim($refineInput.val());
				if (refQuery) {
					$refineInput.val('');
					submitQuery(refQuery, true);
				}
			});

			// ==================== 2. TAG RENDERING & DIRECT DROPDOWN INTERACTION ====================
			function renderTags(filters) {
				$tagsContainer.empty();

				var labelMap = {
					'type': aiSearchPublicData.i18n.type || 'Property Type',
					'status': aiSearchPublicData.i18n.status || 'Property Status',
					'property_city': aiSearchPublicData.i18n.city || 'City',
					'property_area': aiSearchPublicData.i18n.area || 'Area',
					'property_state': aiSearchPublicData.i18n.state || 'State',
					'property_country': aiSearchPublicData.i18n.country || 'Country',
					'label': aiSearchPublicData.i18n.label || 'Label',
					'features': aiSearchPublicData.i18n.features || 'Features',
					'bedrooms': aiSearchPublicData.i18n.beds || 'Bedrooms',
					'bathrooms': aiSearchPublicData.i18n.baths || 'Bathrooms',
					'min_price': aiSearchPublicData.i18n.min_price || 'Min Price',
					'max_price': aiSearchPublicData.i18n.max_price || 'Max Price',
					'min_area': aiSearchPublicData.i18n.min_area || 'Min SqFt',
					'max_area': aiSearchPublicData.i18n.max_area || 'Max SqFt',
					'keyword': aiSearchPublicData.i18n.keyword || 'Keyword'
				};

				var count = 0;

				Object.keys(filters).forEach(function(key) {
					var val = filters[key];
					if (val === null || val === undefined || val === '' || (Array.isArray(val) && val.length === 0)) {
						return;
					}

					var displayLabel = labelMap[key] || (key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' '));
					var displayVal = '';

					if (Array.isArray(val)) {
						displayVal = val.map(function(item) {
							if (typeof item === 'object' && item.name) {
								return item.name;
							}
							return formatTermName(String(item), key);
						}).join(', ');
					} else {
						displayVal = formatTermName(String(val), key);
					}


					if (key === 'bedrooms') { displayVal = val + '+ Beds'; }
					if (key === 'bathrooms') { displayVal = val + '+ Baths'; }
					if (key === 'min_price') { displayVal = '> $' + Number(val).toLocaleString(); }
					if (key === 'max_price') { displayVal = '< $' + Number(val).toLocaleString(); }
					if (key === 'min_area') { displayVal = '> ' + Number(val).toLocaleString() + ' SqFt'; }
					if (key === 'max_area') { displayVal = '< ' + Number(val).toLocaleString() + ' SqFt'; }

					var $wrap = $('<div class="ai-tag-chip-wrap" data-filter-key="' + escapeHtml(key) + '">' +
						'<div class="ai-tag-chip">' +
							'<span class="ai-tag-label">' + escapeHtml(displayLabel) + ':</span> ' +
							'<span class="ai-tag-val">' + escapeHtml(displayVal) + '</span>' +
							'<svg class="ai-tag-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>' +
							'<span class="ai-tag-remove" title="Remove filter">&times;</span>' +
						'</div>' +
						'<div class="ai-chip-dropdown" style="display:none;">' +
							'<div class="ai-chip-dropdown-loading" style="padding:10px 16px; font-size:12px; color:#94a3b8;">Loading options...</div>' +
						'</div>' +
					'</div>');

					$tagsContainer.append($wrap);
					count++;
				});

				if (count === 0) {
					$tagsContainer.html('<em style="font-size:12px; color:#94a3b8;">No active filters</em>');
				}
			}

			// Tag Remove Click
			$container.on('click', '.ai-tag-remove', function(e) {
				e.preventDefault();
				e.stopPropagation();
				var $wrap = $(this).closest('.ai-tag-chip-wrap');
				var filterKey = $wrap.data('filter-key');

				delete activeFilters[filterKey];
				renderTags(activeFilters);
				refreshProperties();
			});

			// Reset All Filters
			$container.find('.ai-tags-reset-btn').on('click', function(e) {
				e.preventDefault();
				activeFilters = {};
				renderTags(activeFilters);
				refreshProperties();
			});

			// ==================== 3. DIRECT CHIP DROPDOWN MENU ====================
			$container.on('click', '.ai-tag-chip', function(e) {
				// If clicking remove button, ignore dropdown
				if ($(e.target).hasClass('ai-tag-remove') || $(e.target).closest('.ai-tag-remove').length) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();

				var $chip = $(this);
				var $wrap = $chip.closest('.ai-tag-chip-wrap');
				var $dropdown = $wrap.find('.ai-chip-dropdown');
				var filterKey = $wrap.data('filter-key');
				var currentVal = activeFilters[filterKey];

				var wasOpen = $chip.hasClass('ai-chip-open');

				// Close all other open dropdowns
				$container.find('.ai-tag-chip').removeClass('ai-chip-open');
				$container.find('.ai-chip-dropdown').hide();

				if (wasOpen) {
					return;
				}

				$chip.addClass('ai-chip-open');
				$dropdown.show();

				// Load options into dropdown
				loadTaxonomies(function(taxData) {
					var listHtml = '<ul class="ai-chip-dropdown-list">';

					if (filterKey === 'bedrooms' || filterKey === 'bathrooms') {
						var options = (taxData[filterKey] && taxData[filterKey].options) ? taxData[filterKey].options : [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
						var unitLabel = (filterKey === 'bedrooms' ? (aiSearchPublicData.i18n.beds || 'Bedrooms') : (aiSearchPublicData.i18n.baths || 'Bathrooms'));

						options.forEach(function(opt) {
							var isSelected = (parseInt(currentVal, 10) === parseInt(opt, 10));
							listHtml += '<li class="ai-chip-dropdown-item ' + (isSelected ? 'ai-selected' : '') + '" data-val="' + opt + '">' +
								'<span>' + opt + '+ ' + unitLabel + '</span>' +
								(isSelected ? '<span class="ai-item-check">&#10003;</span>' : '') +
							'</li>';
						});
						listHtml += '</ul>';
						$dropdown.html(listHtml);
					} else if (filterKey === 'min_price' || filterKey === 'max_price') {
						var priceBrackets = (filterKey === 'max_price')
							? [ 250000, 500000, 750000, 1000000, 1500000, 2000000, 3000000, 5000000, 10000000 ]
							: [ 100000, 250000, 500000, 750000, 1000000, 1500000, 2000000, 3000000 ];

						priceBrackets.forEach(function(price) {
							var isSelected = (parseFloat(currentVal) === price);
							var label = (filterKey === 'max_price' ? '< $' : '> $') + Number(price).toLocaleString();
							listHtml += '<li class="ai-chip-dropdown-item ' + (isSelected ? 'ai-selected' : '') + '" data-val="' + price + '">' +
								'<span>' + label + '</span>' +
								(isSelected ? '<span class="ai-item-check">&#10003;</span>' : '') +
							'</li>';
						});
						listHtml += '</ul>';
						listHtml += '<div class="ai-chip-dropdown-custom-input">' +
							'<input type="number" step="10000" min="0" placeholder="Custom Price" value="' + (currentVal || '') + '" />' +
							'<button type="button" class="ai-chip-dropdown-custom-btn">Set</button>' +
						'</div>';
						$dropdown.html(listHtml);
					} else {
						// Taxonomies
						var keyAliases = [filterKey, 'property_' + filterKey, filterKey.replace('property_', '')];
						var terms = [];
						for (var i = 0; i < keyAliases.length; i++) {
							var alias = keyAliases[i];
							if (taxData[alias] && taxData[alias].terms && taxData[alias].terms.length) {
								terms = taxData[alias].terms;
								break;
							}
						}

						if (!terms.length) {
							$dropdown.html('<div style="padding:10px 16px; font-size:12px; color:#94a3b8;">No options found</div>');
							return;
						}

						terms.forEach(function(t) {
							var isSelected = (Array.isArray(currentVal) ? currentVal.indexOf(t.slug) !== -1 : currentVal === t.slug);
							listHtml += '<li class="ai-chip-dropdown-item ' + (isSelected ? 'ai-selected' : '') + '" data-val="' + escapeHtml(t.slug) + '">' +
								'<span>' + escapeHtml(t.name) + '</span>' +
								(isSelected ? '<span class="ai-item-check">&#10003;</span>' : '') +
							'</li>';
						});
						listHtml += '</ul>';
						$dropdown.html(listHtml);
					}
				});
			});

			// Dropdown Option Item Click
			$container.on('click', '.ai-chip-dropdown-item', function(e) {
				e.preventDefault();
				e.stopPropagation();

				var $item = $(this);
				var $wrap = $item.closest('.ai-tag-chip-wrap');
				var filterKey = $wrap.data('filter-key');
				var newVal = $item.data('val');

				if (filterKey === 'bedrooms' || filterKey === 'bathrooms') {
					activeFilters[filterKey] = parseInt(newVal, 10);
				} else if (filterKey === 'min_price' || filterKey === 'max_price' || filterKey === 'min_area' || filterKey === 'max_area') {
					activeFilters[filterKey] = parseFloat(newVal);
				} else if (filterKey === 'type' || filterKey === 'status' || filterKey === 'features' || filterKey === 'property_city' || filterKey === 'property_area' || filterKey === 'property_state' || filterKey === 'property_country' || filterKey === 'label') {
					activeFilters[filterKey] = [newVal];
				} else {
					activeFilters[filterKey] = newVal;
				}

				$wrap.find('.ai-tag-chip').removeClass('ai-chip-open');
				$wrap.find('.ai-chip-dropdown').hide();

				renderTags(activeFilters);
				refreshProperties();
			});

			// Custom Price / Numeric input inside dropdown
			$container.on('click', '.ai-chip-dropdown-custom-btn', function(e) {
				e.preventDefault();
				e.stopPropagation();

				var $btn = $(this);
				var $wrap = $btn.closest('.ai-tag-chip-wrap');
				var filterKey = $wrap.data('filter-key');
				var rawVal = $wrap.find('.ai-chip-dropdown-custom-input input').val();
				var newVal = parseFloat(rawVal);

				if (!isNaN(newVal) && newVal >= 0) {
					activeFilters[filterKey] = newVal;
					$wrap.find('.ai-tag-chip').removeClass('ai-chip-open');
					$wrap.find('.ai-chip-dropdown').hide();
					renderTags(activeFilters);
					refreshProperties();
				}
			});

			// Close chip dropdowns on outside click
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.ai-tag-chip-wrap').length) {
					$('.ai-tag-chip').removeClass('ai-chip-open');
					$('.ai-chip-dropdown').hide();
				}
			});

			function loadTaxonomies(callback) {
				if (taxonomiesData) {
					if (typeof callback === 'function') {
						callback(taxonomiesData);
					}
					return;
				}

				$.post(aiSearchPublicData.ajaxUrl, {
					action: 'ai_search_houzi_get_taxonomies',
					security: aiSearchPublicData.nonce
				}, function(res) {
					if (res.success && res.data) {
						taxonomiesData = res.data;
						Object.keys(taxonomiesData).forEach(function(k) {
							if (taxonomiesData[k] && taxonomiesData[k].terms && Array.isArray(taxonomiesData[k].terms)) {
								taxonomiesData[k].terms.forEach(function(t) {
									if (t && t.slug && t.name) {
										termNamesMap[t.slug] = t.name;
									}
								});
							}
						});
						renderTags(activeFilters);
						if (typeof callback === 'function') {
							callback(taxonomiesData);
						}
					}
				});
			}

			// Pre-fetch taxonomies in the background for instant term name display
			loadTaxonomies();



			// ==================== 4. REFINEMENT PILLS ====================
			function normalizePatch(patch) {
				if (typeof patch === 'string') {
					try {
						patch = JSON.parse(patch);
					} catch(e) {
						return {};
					}
				}
				if (!patch || typeof patch !== 'object') {
					return {};
				}

				var keyMap = {
					'feature': 'features',
					'features': 'features',
					'type': 'type',
					'property_type': 'type',
					'status': 'status',
					'property_status': 'status',
					'label': 'label',
					'property_label': 'label',
					'city': 'property_city',
					'property_city': 'property_city',
					'area': 'property_area',
					'property_area': 'property_area',
					'state': 'property_state',
					'property_state': 'property_state',
					'country': 'property_country',
					'property_country': 'property_country',
					'beds': 'bedrooms',
					'bed': 'bedrooms',
					'bedrooms': 'bedrooms',
					'bedroom': 'bedrooms',
					'baths': 'bathrooms',
					'bath': 'bathrooms',
					'bathrooms': 'bathrooms',
					'bathroom': 'bathrooms',
					'min_price': 'min_price',
					'minprice': 'min_price',
					'price_min': 'min_price',
					'max_price': 'max_price',
					'maxprice': 'max_price',
					'price_max': 'max_price',
					'price': 'max_price',
					'min_area': 'min_area',
					'minarea': 'min_area',
					'max_area': 'max_area',
					'maxarea': 'max_area',
					'keyword': 'keyword'
				};

				var normalized = {};
				Object.keys(patch).forEach(function(rawKey) {
					var canonicalKey = keyMap[rawKey.toLowerCase()] || rawKey;
					normalized[canonicalKey] = patch[rawKey];
				});

				return normalized;
			}

			function applyPatchToFilters(patch) {
				var normalized = normalizePatch(patch);
				var arrayKeys = ['features', 'type', 'status', 'label', 'property_city', 'property_area', 'property_state', 'property_country'];

				Object.keys(normalized).forEach(function(k) {
					var val = normalized[k];
					if (arrayKeys.indexOf(k) !== -1) {
						var currentArr = Array.isArray(activeFilters[k]) ? activeFilters[k].slice() : (activeFilters[k] ? [activeFilters[k]] : []);
						var newItems = Array.isArray(val) ? val : [val];
						newItems.forEach(function(item) {
							var itemStr = String(item).trim();
							if (itemStr && currentArr.indexOf(itemStr) === -1) {
								currentArr.push(itemStr);
							}
						});
						activeFilters[k] = currentArr;
					} else if (k === 'bedrooms' || k === 'bathrooms') {
						activeFilters[k] = parseInt(val, 10);
					} else if (k === 'min_price' || k === 'max_price' || k === 'min_area' || k === 'max_area') {
						activeFilters[k] = parseFloat(val);
					} else {
						activeFilters[k] = val;
					}
				});
			}

			function renderRefinements(suggestions) {
				$refineList.empty();
				if (!suggestions || suggestions.length === 0) {
					$refineSection.hide();
					return;
				}

				suggestions.forEach(function(s) {
					if (!s || !s.label || !s.patch) return;
					var patchObj = s.patch;
					if (typeof patchObj === 'string') {
						try {
							patchObj = JSON.parse(patchObj);
						} catch(e) {
							return;
						}
					}
					var patchJson = JSON.stringify(patchObj);
					var $pill = $('<button type="button" class="ai-refinement-pill">' +
						'<span>' + escapeHtml(s.label) + '</span>' +
					'</button>');
					$pill.attr('data-patch', patchJson);
					$pill.data('patch', patchObj);
					$refineList.append($pill);
				});

				$refineSection.show();
			}

			$container.on('click', '.ai-refinement-pill', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var patch = $btn.data('patch');
				if (!patch) {
					var rawAttr = $btn.attr('data-patch');
					if (rawAttr) {
						try {
							patch = JSON.parse(rawAttr);
						} catch(err) {}
					}
				}
				if (patch) {
					applyPatchToFilters(patch);
					$btn.fadeOut(200, function() {
						$(this).remove();
						if ($refineList.children(':visible').length === 0) {
							$refineSection.hide();
						}
					});
					renderTags(activeFilters);
					refreshProperties();
				}
			});



			// ==================== 5. REFRESH PROPERTIES (NO LLM RE-QUERY) ====================
			function refreshProperties(paged) {
				paged = paged || 1;
				currentPage = paged;

				$skeleton.show();
				$resultsGrid.hide();
				$emptyState.hide();

				$.post(aiSearchPublicData.ajaxUrl, {
					action: 'ai_search_houzi_get_properties',
					security: aiSearchPublicData.nonce,
					filters: activeFilters,
					posts_per_page: postsPerPage,
					paged: currentPage
				}, function(res) {
					$skeleton.hide();
					if (res.success && res.data) {
						if (res.data.term_names) {
							$.extend(termNamesMap, res.data.term_names);
							renderTags(activeFilters);
						}
						if (res.data.explanation) {
							$explanationText.text(res.data.explanation);
							$explanationBox.show();
						} else {
							$explanationBox.hide();
						}
						renderResults(res.data.cards_html, res.data.total_count, res.data.view_all_url, res.data.paged, res.data.max_pages);
					}
				});
			}

			// ==================== 6. RENDER RESULTS & PAGINATION ====================
			function renderResults(cardsHtml, totalCount, viewAllUrl, paged, maxPages) {
				paged = parseInt(paged, 10) || 1;
				maxPages = parseInt(maxPages, 10) || 1;
				currentPage = paged;

				$resultsGrid.empty();

				if (totalCount > 0 && cardsHtml) {
					$resultsGrid.html(cardsHtml).show();
					$emptyState.hide();
					$footer.show();

					$countBadge.text(totalCount);
					$footerCount.text('Found ' + totalCount + ' matching properties');
					$viewAllBtn.attr('href', viewAllUrl);

					// Render Pagination State
					if (maxPages > 1) {
						$pageCurrent.text(paged);
						$pageTotal.text(maxPages);
						$pagePrevBtn.prop('disabled', paged <= 1);
						$pageNextBtn.prop('disabled', paged >= maxPages);
						$paginationWrap.show();
					} else {
						$paginationWrap.hide();
					}
				} else {
					$resultsGrid.hide();
					$emptyState.show();
					$footer.hide();
					$paginationWrap.hide();
					$countBadge.text('0');
				}
			}

			// Pagination Button Events
			$container.on('click', '.ai-page-prev', function(e) {
				e.preventDefault();
				if (currentPage > 1) {
					refreshProperties(currentPage - 1);
					scrollToResultsHeader();
				}
			});

			$container.on('click', '.ai-page-next', function(e) {
				e.preventDefault();
				refreshProperties(currentPage + 1);
				scrollToResultsHeader();
			});

			function scrollToResultsHeader() {
				if ($panelResults.length && $(window).scrollTop() > $panelResults.offset().top) {
					$('html, body').animate({
						scrollTop: Math.max(0, $panelResults.offset().top - 20)
					}, 250);
				}
			}


			// ==================== 7. CLOSE / RESET TO INITIAL SEARCH ====================
			function resetToInitialSearch() {
				$container.removeClass('ai-is-active');
				$activeState.hide();
				$panelResults.hide();
				activeFilters = {};
				conversationId = null;
				$searchInput.val('');
				setTimeout(function() {
					$searchInput.focus();
				}, 100);
			}

			$container.on('click', '.ai-new-search-btn', function(e) {
				e.preventDefault();
				resetToInitialSearch();
			});

			$container.find('.ai-results-close-btn').on('click', function(e) {
				e.preventDefault();
				resetToInitialSearch();
			});


			function escapeHtml(str) {
				return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
			}

		});

	});

})(jQuery);
