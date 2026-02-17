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
