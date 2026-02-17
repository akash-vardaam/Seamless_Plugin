<?php

namespace Seamless\Endpoints;

use Seamless\Auth\SeamlessAuth;

class Endpoints
{

	public function __construct()
	{
		add_action('init', [$this, 'register_rewrite_rules']);
		add_filter('query_vars', [$this, 'add_query_vars']);
		add_filter('template_include', [$this, 'handle_template_redirect'], 100);
		add_action('update_option_seamless_event_list_endpoint', 'flush_rewrite_rules');
		add_action('update_option_seamless_single_event_endpoint', 'flush_rewrite_rules');
		add_action('update_option_seamless_ams_content_endpoint', 'flush_rewrite_rules');
		add_action('update_option_seamless_single_donation_endpoint', 'flush_rewrite_rules');
		add_action('update_option_seamless_membership_list_endpoint', 'flush_rewrite_rules');
		add_action('update_option_seamless_single_membership_endpoint', 'flush_rewrite_rules');

		// Add title filters for single event pages
		add_filter('document_title_parts', [$this, 'filter_event_title'], 10);
		add_filter('wp_title', [$this, 'filter_wp_title'], 10, 2);
	}

	/**
	 * Filter document title for single event pages
	 */
	public function filter_event_title($title_parts)
	{
		if (get_query_var('seamless_page') === 'single_event') {
			$event_slug = get_query_var('event_uuid');
			if ($event_slug) {
				$event_title = $this->get_event_title($event_slug);
				if ($event_title) {
					// Set only the event title, remove site name
					return [
						'title' => $event_title,
					];
				}
			}
		}
		return $title_parts;
	}

	/**
	 * Filter wp_title for single event pages
	 */
	public function filter_wp_title($title, $sep)
	{
		if (get_query_var('seamless_page') === 'single_event') {
			$event_slug = get_query_var('event_uuid');
			if ($event_slug) {
				$event_title = $this->get_event_title($event_slug);
				if ($event_title) {
					return $event_title;
				}
			}
		}
		return $title;
	}

	/**
	 * Get event title from API
	 */
	private function get_event_title($slug)
	{
		// Check transient cache first
		$cache_key = 'seamless_event_title_' . md5($slug);
		$cached_title = get_transient($cache_key);
		if ($cached_title !== false) {
			return $cached_title;
		}

		// Fetch from API
		$client_domain = get_option('seamless_client_domain', '');
		if (empty($client_domain)) {
			return null;
		}

		$client_domain = rtrim($client_domain, '/');

		// Try regular events endpoint first
		$response = wp_remote_get($client_domain . '/api/events/' . $slug, [
			'timeout' => 5,
			'sslverify' => false,
		]);

		if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
			$body = json_decode(wp_remote_retrieve_body($response), true);
			if (isset($body['data']['title'])) {
				$title = $body['data']['title'];
				set_transient($cache_key, $title, 300); // Cache for 5 minutes
				return $title;
			}
		}

		// Try group events endpoint
		$response = wp_remote_get($client_domain . '/api/group-events/' . $slug, [
			'timeout' => 5,
			'sslverify' => false,
		]);

		if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
			$body = json_decode(wp_remote_retrieve_body($response), true);
			if (isset($body['data']['title'])) {
				$title = $body['data']['title'];
				set_transient($cache_key, $title, 300); // Cache for 5 minutes
				return $title;
			}
		}

		return null;
	}

	public function register_rewrite_rules(): void
	{
		add_rewrite_rule('^' . preg_quote(get_option('seamless_event_list_endpoint', 'events'), '/') . '/?$', 'index.php?seamless_page=event_list', 'top');
		$slug = get_option('seamless_single_event_endpoint', 'event');
		add_rewrite_rule(
			'^' . preg_quote($slug, '/') . '/([^/]+)/?$',
			'index.php?seamless_page=single_event&event_uuid=$matches[1]',
			'top'
		);

		// AMS Content endpoint
		$ams_slug = get_option('seamless_ams_content_endpoint', 'ams-content');
		add_rewrite_rule(
			'^' . preg_quote($ams_slug, '/') . '/?$',
			'index.php?seamless_page=ams_content',
			'top'
		);
	}

	public function add_query_vars($vars)
	{
		$vars[] = 'seamless_page';
		$vars[] = 'event_uuid';
		$vars[] = 'donation_id';
		$vars[] = 'membership_uuid';
		return $vars;
	}

	public function handle_template_redirect($template)
	{
		$page = get_query_var('seamless_page');
		if ($page) {
			if ('event_list' === $page) {
				return plugin_dir_path(__DIR__) . 'Public/templates/tpl-event-container.php';
			} elseif ('single_event' === $page) {
				return plugin_dir_path(__DIR__) . 'Public/templates/tpl-single-event-wrapper.php';
			} elseif ('ams_content' === $page) {
				return plugin_dir_path(__DIR__) . 'Public/templates/tpl-seamless-ams-content.php';
			}
		}
		return $template;
	}
}
