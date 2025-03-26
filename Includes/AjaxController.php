<?php

namespace ApiConnector;

if (!defined('ABSPATH')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit;
}

use ApiConnector\Helpers\Request;
use ApiConnector\Helpers\Str;

class AjaxController
{
    public function __construct()
    {
        add_action('wp_ajax_api-connector-admin-ajax', [$this, 'listenAjax']);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function listenAjax()
    {
        $requestHelper = new Request();
        if ($requestHelper->exists('api-connector-admin-ajax')) {
            $rawResponse = $this->parseRequest();
            $output = $this->renderResponse($rawResponse);
            $this->output($output, $rawResponse);
        }
    }

    /**
     * Url to whole plugin folder.
     *
     * @param $query
     *
     * @return string
     */
    public function adminAjax($query = [])
    {
        $url = admin_url('admin-ajax.php');
        $query = array_merge(['action' => 'api-connector-admin-ajax'], $query);

        return add_query_arg($query, $url);
    }

    /**
     * @return mixed
     */
    protected function parseRequest()
    {
        $requestHelper = new Request();
        $action = $requestHelper->input('api-connector-action');
        $nonce = $requestHelper->input('api-connector-nonce');

        if ($this->verifyAdmin($nonce)) {
            $action = str_replace('-', '_', $action);
            $action = Str::camel($action);

            return apply_filters('api-connector-ajax-' . $requestHelper->input('api-connector-action'), $action);
        }

        return false;
    }

    /**
     * @param $response
     *
     * @return array
     */
    protected function renderResponse($response)
    {
        if (is_wp_error($response)) {
            return [
                'status' => 'error',
                'message' => $response->get_error_message(),
            ];
        }

        if ($response === false) {
            return [
                'status' => 'error',
                'message' => __('Unauthorized action', 'api-connector'),
            ];
        }

        return [
            'status' => 'success',
            'data' => $response,
        ];
    }

    /**
     * @param $output
     * @param $rawResponse
     *
     * @return void
     */
    protected function output($output, $rawResponse)
    {
        if (is_string($rawResponse) && $rawResponse === 'redirectToSettings') {
            wp_redirect(admin_url('admin.php?page=api-connector'));
            exit;
        }

        // Se for uma solicitação AJAX, envie a resposta JSON
        if (wp_doing_ajax()) {
            wp_send_json($output);
        } else {
            // Caso contrário, redirecione para a página de configurações
            wp_redirect(admin_url('admin.php?page=api-connector'));
            exit;
        }
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return wp_create_nonce('api-connector-once');
    }

    /**
     * @return mixed
     */
    public function admin()
    {
        return current_user_can('edit_posts') ? wp_create_nonce('api-connector-nonce-admin') : false;
    }

    /**
     * @param $nonce
     *
     * @return bool
     */
    public function verifyUser($nonce)
    {
        return !empty($nonce) && wp_verify_nonce($nonce, 'api-connector-nonce');
    }

    /**
     * @param $nonce
     *
     * @return bool
     */
    public function verifyAdmin($nonce)
    {
        return !empty($nonce) && current_user_can('edit_posts')
            && wp_verify_nonce(
                $nonce,
                'api-connector-nonce-admin'
            );
    }
}
