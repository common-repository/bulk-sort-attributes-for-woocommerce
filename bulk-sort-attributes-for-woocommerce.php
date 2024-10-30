<?php
	/*
		Plugin Name: Bulk Sort Attributes for WooCommerce
		Description: Bulk sort WooCommerce attributes when they are too numerous for custom sorting by hand.
		Version: 1.1.5
		Author: Inbound Horizons
		Author URI: https://www.inboundhorizons.com
	*/


	if (!defined('ABSPATH')) {
		exit; // Exit if accessed directly
	}
	
	class Bulk_Sort_Attributes_For_WooCommerce {
		
		private static $_instance = null;	// Get the static instance variable
		
		public static function Instantiate() {
			if (is_null(self::$_instance)) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}
		
		private function __construct() {
			add_action('woocommerce_init', array($this, 'InitHooks'));
		}
		
		public function InitHooks() {
			
			// Set a hook for every attribute taxonomy
				$attribute_taxonomies = wc_get_attribute_taxonomies();
				if (!empty($attribute_taxonomies)) {
					foreach ($attribute_taxonomies as $attribute) {
						add_action('views_edit-' . 'pa_' . $attribute->attribute_name, function() use ($attribute) {
							$this->OutputSortingButtonsHtml($attribute);
						});
					}
				}
			
			// Listen for AJAX trigger
				add_action('wp_ajax_WBSA_SORT_ATTRIBUTES', array($this, 'AJAXCustomOrderWooAttributes'));
			
		}
		
		public function OutputSortingButtonsHtml($attribute) {
		
			$nonce = wp_create_nonce('wbsa-sort-attributes');
			
			$att_orderby = $attribute->attribute_orderby;
			$taxonomy = 'pa_' . $attribute->attribute_name;
			
			$is_custom_order_by = ($att_orderby == 'menu_order');
			
			
			$select_disabled = 'disabled';
			$btn_disabled = 'button-disabled';
			$warning_disabled = '';
			if ($is_custom_order_by) {
				$warning_disabled = 'display:none;';
				$btn_disabled = '';
				$select_disabled = '';
			}
			
			echo '
				<div style="margin-top:5px;">
					<select id="wbsa_sorting" '.esc_attr($select_disabled).'>
						<option value="" selected disabled>- Bulk Sort Attributes for WooCommerce -</option>
							
						<optgroup label="Sort By: ID">
							<option value="id asc">ID - ASC</option>
							<option value="id desc">ID - DESC</option>
						</optgroup>
						
						<optgroup label="Sort By: Name">
							<option value="name asc">Name - ASC</option>
							<option value="name desc">Name - DESC</option>
						</optgroup>
							
						<optgroup label="Sort By: Name (Numeric)">
							<option value="name_num asc">Name (Numeric) - ASC</option>
							<option value="name_num desc">Name (Numeric) - DESC</option>
						</optgroup>
					</select>
					
					<button type="button" class="button button-secondary wbsa '.esc_attr($btn_disabled).'">
						Bulk Sort Attributes
					</button>
					
					
					<b style="'.esc_attr($warning_disabled).'"><span class="dashicons dashicons-warning" style="line-height:1.5;" title="Please edit the attribute and change the default sort order to &quot;Custom ordering&quot;"></span></b>
					
					<span id="wbsa-spinner" class="dashicons dashicons-update" style="display:none; animation:wbsa-spin 2s linear infinite"></span>
				</div>
				
				<style>
					@keyframes wbsa-spin {
						0% { transform: rotate(0deg); }
						100% { transform: rotate(360deg); }
					}
				</style>
				
				
				<script>
				
					jQuery(document).ready(function() {
						jQuery(document).on("click", ".wbsa:not(.button-disabled)", function() {
							
							var val = jQuery("#wbsa_sorting").val();
							
							if (val && (val != "")) {
								
								var sort_direction = val.split(" ");
							
								var sort = sort_direction[0];
								var direction = sort_direction[1];
								var taxonomy = "'.esc_html($taxonomy).'";
								
								var data = {
									"action": "WBSA_SORT_ATTRIBUTES",
									"sort": sort,
									"direction": direction,
									"taxonomy": taxonomy,
									"nonce": "'.esc_attr($nonce).'",
								};
								
								jQuery("#wbsa-spinner").show();
								jQuery.post(ajaxurl, data, function(response) {
									location.reload();	// Reload the page
								});
								
							}
							else {
								alert("Please select how to sort the attributes.");
								jQuery("#wbsa_sorting").focus();
							}
							
						});
					});
				
				</script>
				
			';
		}
		
		public function AJAXCustomOrderWooAttributes() {
			$ok = 0;
			
	
			$nonce_verified = wp_verify_nonce($_POST['nonce'], 'wbsa-sort-attributes');
			
			if ($nonce_verified) {
				$ok = 1;
			
				// Get and sanitize POSTed data
				$sort = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : '';
				$direction = isset($_POST['direction']) ? sanitize_text_field($_POST['direction']) : '';
				$taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';

				// Convert the strings to lower case
				$sort = strtolower($sort);
				$direction = strtolower($direction);

				// Sort the attribute terms
				$this->CustomOrderWooAttributes($taxonomy, $sort, $direction);
				
			}
				
			$json = array(
				'ok' => $ok,
			);
			
			header('Content-Type: application/json');
			echo json_encode($json);
			wp_die(); // This is required to terminate immediately and return a proper response
		}

		public function CustomOrderWooAttributes($taxonomy, $sort, $direction = 'asc') {
			
			$possible_sorts = array(
				'id',
				'name',
				'name_num',
			);
			
			$possible_sort_directions = array(
				'asc',
				'desc',
			);
			
			
			
			// 1.) Get the terms in an array
				$terms = get_terms(array(
					'taxonomy' => $taxonomy,
					'hide_empty' => false,
				));
				
			
			// 2.) Sort the array of terms
			
				if (in_array($sort, $possible_sorts) && in_array($direction, $possible_sort_directions)) {
					
					if ($sort == 'name') {
						if ($direction == 'desc') {
							usort($terms, function($a, $b) {
								return strcmp($b->name, $a->name);
							});
						}
						else {	// Assume 'asc'
							usort($terms, function($a, $b) {
								return strcmp($a->name, $b->name);
							});
						}
					}
					else if ($sort == 'name_num') {
						if ($direction == 'desc') {
							usort($terms, function($a, $b) {
								return intval($b->name) > intval($a->name);
							});
						}
						else {	// Assume 'asc'
							usort($terms, function($a, $b) {
								return intval($a->name) > intval($b->name);
							});
						}
					}
					else {	// Assume 'id'
						if ($direction == 'desc') {
							usort($terms, function($a, $b) {
								return intval($b->term_id) > intval($a->term_id);
							});
						}
						else {	// Assume 'asc'
							usort($terms, function($a, $b) {
								return intval($a->term_id) > intval($b->term_id);
							});
						}
					}
			
					
					// 3.) Commit the new order to the database
						for ($i = 0; $i < count($terms); $i++) {
							$term_id = intval($terms[$i]->term_id);
							wc_set_term_order($term_id, $i, $taxonomy);
						}
					
					
				}
			
		}

	
	}
	
	Bulk_Sort_Attributes_For_WooCommerce::Instantiate();	// Instantiate an instance of the class
	
	