<?php

namespace TextBuilder;

if (!defined('ABSPATH')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit;
}

use TextBuilder\Admin\SettingsPanel;
use TextBuilder\Api\AuthorizationController;
use TextBuilder\Api\PostsController;
use TextBuilder\Api\MediaController;
use TextBuilder\Api\CategoriesController;
use TextBuilder\Api\TagsController;
use TextBuilder\Api\RestAuthenticationController;

class TextBuilder
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
        new TagsController();
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
        $locale = apply_filters('plugin_locale', $locale, 'textbuilder');

        unload_textdomain('textbuilder');
        load_textdomain(
            'textbuilder',
            WP_LANG_DIR . '/textbuilder/' . $locale . '.mo'
        );
        load_plugin_textdomain('textbuilder', false, TEXTBUILDER_ROOT_DIRNAME . '/languages');
    }
}
