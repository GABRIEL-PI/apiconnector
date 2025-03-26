<?php

namespace ApiConnector;

if (!defined('ABSPATH')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit;
}

class FrontPage
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        //add_action('wp_enqueue_scripts', [$this, 'enqueueStyles']);
    }

    /**
     * Enqueue view page js scripts
     *
     * @return void
     */
    public function enqueueScripts()
    {
        wp_enqueue_script("jquery");
        wp_enqueue_script(
            'api-connector',
            plugins_url('public/dist/main.bundle.js', APICONNECTOR_ROOT),
            [],
            APICONNECTOR_VERSION,
            true
        );
    }

    /**
     * Enqueue view page styles
     *
     * @return void
     */
    public function enqueueStyles()
    {
        wp_enqueue_style(
            'api-connector',
            plugins_url('public/dist/style.bundle.css', APICONNECTOR_ROOT),
            [],
            APICONNECTOR_VERSION,
        );
    }
}
