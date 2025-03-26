<?php
namespace ApiConnector\Api;

if (!defined('ABSPATH')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit;
}

use \WP_REST_Posts_Controller;
use \WP_REST_Attachments_Controller;
use \WP_REST_Server;

class PostsController extends WP_REST_Posts_Controller
{
    public function __construct()
    {
        parent::__construct('post');
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        $version = '1';
        $namespace = 'api-connector/v' . $version;

        register_rest_route($namespace, '/posts', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'create_item_permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
            ],
        ]);
        
        register_rest_route($namespace, '/posts/(?P<id>[\d]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'update_item_permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'delete_item_permissions_check'],
            ],
        ]);
    }

    public function uploadImages($preparedPost, $request)
    {
//        // Regular images.
//        if (isset($request['images'])) {
//            if (is_array($request['images'])) {
//                $preparedPost->images = $request['images'];
//            }
//        }
//
//        // Featured images.
//        if (isset($request['featured-image'])) {
//            if (is_string($request['featured-image'])) {
//                $preparedPost->featured_image = $request['featured-image'];
//            }
//        }
        $requestClone = clone $request;
        $attachments = new WP_REST_Attachments_Controller($this->post_type);
        $images = [];
        if (isset($request['images']) && is_array($request['images'])) {
            $images = $request['images'];

            foreach ($images as $image) {
                $requestClone->set_body(file_get_contents($image['file']));
                $requestClone->add_header('Content-Type:', wp_get_image_mime($image['file']));
                $requestClone->add_header(
                    'Content-Disposition',
                    'attachment; filename="' . basename($image['file']) . '"'
                );
                $images[] = $attachments->create_item($requestClone);
            }
        }

        return $preparedPost;
    }

    protected function insert_attachment( $request ) {
        // Get the file via $_FILES or raw data.
        $files   = $request->get_file_params();
        $headers = $request->get_headers();

        if ( ! empty( $files ) ) {
            $file = $this->upload_from_file( $files, $headers );
        } else {
            $file = $this->upload_from_data( $request->get_body(), $headers );
        }

        if ( is_wp_error( $file ) ) {
            return $file;
        }

        $name       = wp_basename( $file['file'] );
        $name_parts = pathinfo( $name );
        $name       = trim( substr( $name, 0, -( 1 + strlen( $name_parts['extension'] ) ) ) );

        $url  = $file['url'];
        $type = $file['type'];
        $file = $file['file'];

        // Include image functions to get access to wp_read_image_metadata().
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Use image exif/iptc data for title and caption defaults if possible.
        $image_meta = wp_read_image_metadata( $file );

        if ( ! empty( $image_meta ) ) {
            if ( empty( $request['title'] ) && trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) ) {
                $request['title'] = $image_meta['title'];
            }

            if ( empty( $request['caption'] ) && trim( $image_meta['caption'] ) ) {
                $request['caption'] = $image_meta['caption'];
            }
        }

        $attachment = $this->prepare_item_for_database( $request );

        $attachment->post_mime_type = $type;
        $attachment->guid           = $url;

        if ( empty( $attachment->post_title ) ) {
            $attachment->post_title = preg_replace( '/\.[^.]+$/', '', wp_basename( $file ) );
        }

        // $post_parent is inherited from $attachment['post_parent'].
        $id = wp_insert_attachment( wp_slash( (array) $attachment ), $file, 0, true, false );

        if ( is_wp_error( $id ) ) {
            if ( 'db_update_error' === $id->get_error_code() ) {
                $id->add_data( array( 'status' => 500 ) );
            } else {
                $id->add_data( array( 'status' => 400 ) );
            }

            return $id;
        }

        $attachment = get_post( $id );

        /**
         * Fires after a single attachment is created or updated via the REST API.
         *
         * @since 4.7.0
         *
         * @param WP_Post         $attachment Inserted or updated attachment
         *                                    object.
         * @param WP_REST_Request $request    The request sent to the API.
         * @param bool            $creating   True when creating an attachment, false when updating.
         */
        do_action( 'rest_insert_attachment', $attachment, $request, true );

        return array(
            'attachment_id' => $id,
            'file'          => $file,
        );
    }
}
