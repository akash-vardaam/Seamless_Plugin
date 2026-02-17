<?php

/**
 * Main plugin bootstrap class.
 *
 * Handles initialization of all plugin components with conditional loading
 * to optimize performance and reduce memory usage.
 *
 * @package Seamless
 */

namespace Seamless;

use Seamless\Admin\SettingsPage;
use Seamless\Admin\ContentRestrictionMeta;
use Seamless\Auth\SeamlessAuth;
use Seamless\Auth\SeamlessSSO;
use Seamless\Auth\AccessController;
use Seamless\Endpoints\Endpoints;
use Seamless\Public\SeamlessRender;


class SeamlessAutoLoader
{

	/**
	 * The single instance of the class.
	 *
	 * @var SeamlessAutoLoader|null
	 */
	private static $instance = null;
	private $components = array();

	public static function getInstance(): ?self
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
		$this->load_core_components();
		$this->load_conditional_components();
	}

	private function load_core_components()
	{
		$this->components['endpoints'] = new Endpoints();
		$this->components['auth']            = new SeamlessAuth();
		$this->components['sso']             = new SeamlessSSO();
		$this->components['access_control']  = new AccessController();
	}

	/**
	 * Load components conditionally based on request context.
	 *
	 * This improves performance by only loading classes when needed:
	 * - Admin components load in admin area (including AJAX from admin)
	 * - Frontend components load on public site (including AJAX from frontend)
	 * - SeamlessRender loads for frontend AND AJAX (contains AJAX handlers)
	 */
	private function load_conditional_components()
	{
		$is_ajax = $this->is_ajax_request();

		if (is_admin()) {
			$this->load_admin_components();
		}

		if (! is_admin() || $is_ajax) {
			$this->load_frontend_components();
		}

		if ($this->is_rest_request()) {
			// $this->load_rest_components();
		}
	}

	private function load_admin_components()
	{
		$this->components['settings'] = new SettingsPage();
		$this->components['content_restriction'] = new ContentRestrictionMeta();
	}

	private function load_frontend_components()
	{
		$this->components['render'] = new SeamlessRender();
	}

	private function is_ajax_request()
	{
		return defined('DOING_AJAX') && DOING_AJAX;
	}

	private function is_rest_request()
	{
		if (defined('REST_REQUEST') && REST_REQUEST) {
			return true;
		}

		if (! empty($_SERVER['REQUEST_URI'])) {
			$rest_prefix = trailingslashit(rest_get_url_prefix());
			return (false !== strpos($_SERVER['REQUEST_URI'], $rest_prefix));
		}

		return false;
	}

	/**
	 * Get a registered component by key.
	 *
	 * @param string $key Component key.
	 * @return object|null Component instance or null if not found.
	 */
	public function get_component($key)
	{
		return isset($this->components[$key]) ? $this->components[$key] : null;
	}
}
