<?php

namespace ApiConnector\Api;

if (!defined('ABSPATH')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit;
}

use \WP_REST_Terms_Controller;
use \WP_REST_Server;
use \WP_Error;

class CategoriesController extends WP_REST_Terms_Controller
{
    public function __construct()
    {
        parent::__construct('category');
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        $version = '1';
        $namespace = 'api-connector/v' . $version;

        register_rest_route($namespace, '/categories', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
                'args' => $this->get_collection_params(),
            ],
        ]);
    }

    /**
     * Checks if a given request has access to read terms.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
     */
    public function get_items_permissions_check($request)
    {
        if (!$this->check_is_taxonomy_allowed($this->taxonomy)) {
            return new WP_Error(
                'rest_taxonomy_invalid',
                __('The taxonomy does not exist.'),
                array(
                    'status' => 404,
                )
            );
        }

        if (!empty($request['post'])) {
            $post = get_post($request['post']);

            if (!$post) {
                return new WP_Error(
                    'rest_post_invalid_id',
                    __('Invalid post ID.'),
                    array(
                        'status' => 400,
                    )
                );
            }

            if (!$this->check_read_terms_permission_for_post($post, $request)) {
                return new WP_Error(
                    'rest_forbidden_context',
                    __('Sorry, you are not allowed to view terms for this post.'),
                    array(
                        'status' => rest_authorization_required_code(),
                    )
                );
            }
        }

        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'rest_forbidden_context',
                __('Sorry, you are not allowed to view terms.'),
                [
                    'status' => rest_authorization_required_code(),
                ]
            );
        }

        return true;
    }
}
