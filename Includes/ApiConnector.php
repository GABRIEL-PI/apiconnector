<?php

namespace ApiConnector;

if (!defined('ABSPATH')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit;
}

use ApiConnector\Admin\SettingsPanel;
use ApiConnector\Api\AuthorizationController;
use ApiConnector\Api\PostsController;
use ApiConnector\Api\MediaController;
use ApiConnector\Api\CategoriesController;
use ApiConnector\Api\RestAuthenticationController;

class ApiConnector
{
    private static $instance;

    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'setDomain'], 0);
    }

    /**
     * Get the instance
     *
     * @return self
     */
    public static function getInstance()
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Load the dependencies
     *
     * @return void
     */
    public function boot()
    {
        new RestAuthenticationController();
        new PostsController();
        new MediaController();
        new CategoriesController();
        new AjaxController();
        new AuthorizationController();
        new FrontPage();
        new SettingsPanel();
    }

    /**
     * Set translation domain
     *
     * @return void
     */
    public function setDomain()
    {
        $locale = determine_locale();
        $locale = apply_filters('plugin_locale', $locale, 'api-connector');

        unload_textdomain('api-connector');
        load_textdomain(
            'api-connector',
            WP_LANG_DIR . '/api-connector/' . $locale . '.mo'
        );
        load_plugin_textdomain('api-connector', false, APICONNECTOR_ROOT_DIRNAME . '/languages');
    }
} 