<?php
namespace ApiConnector\Api;

if (!defined('ABSPATH')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit;
}

use ApiConnector\Helpers\Request;

class AuthorizationController
{
    protected $requestHelper = false;

    public function __construct()
    {
        $this->requestHelper = new Request();
        add_action('admin_init', [$this, 'handleToken']);
        add_filter('api-connector-ajax-api-authorize-token-adminNonce', [$this, 'generateToken']);
        add_filter('api-connector-ajax-api-revoke-token-adminNonce', [$this, 'revokeToken']);
        add_filter('api-connector-ajax-api-revoke-all-tokens-adminNonce', [$this, 'revokeAllTokens']);
    }

    /**
     * Parse token
     *
     * @return void
     */
    public function handleToken()
    {
        $currentUser = wp_get_current_user();
        if (isset($_GET['token'], $_GET['page']) && $_GET['page'] === 'textbuilder') {
            update_user_meta($currentUser->ID, 'tb_token', sanitize_key($_GET['token']));
            update_user_meta($currentUser->ID, 'tb_token_created', current_time('mysql'));
            wp_redirect(esc_url_raw(strtok($this->settingsUrl(), '&')));
        }
    }

    /**
     * Generate a new token for the current user
     *
     * @return string
     */
    public function generateToken()
    {
        $currentUser = wp_get_current_user();
        $userId = $currentUser->ID;
        
        // Gerar um token aleatório seguro
        $token = bin2hex(random_bytes(32));
        
        // Salvar o token para o usuário
        update_user_meta($userId, 'api_token', $token);
        update_user_meta($userId, 'api_token_created', current_time('mysql'));
        
        // Retornar o token em vez de redirecionar
        return $token;
    }

    /**
     * Revoke token
     *
     * @return void
     */
    public function revokeToken()
    {
        $response = false;
        $currentUser = wp_get_current_user();
        $requestId = $this->requestHelper->input('userId');

        if ($currentUser->ID === (int)$requestId || current_user_can('remove_users')) {
            do_action('api-connector-unset-user-data', (int)$requestId);
            $response = true;
        }

        $redirectUrl = $this->settingsUrl();
        if ($response) {
            $redirectUrl = add_query_arg(['token-revoked' => '1'], $redirectUrl);
        } else {
            $redirectUrl = add_query_arg(['token-error' => '1'], $redirectUrl);
        }

        wp_redirect(esc_url_raw($redirectUrl));
        exit;
    }

    /**
     * Revoke all tokens
     *
     * @return void
     */
    public function revokeAllTokens($background = false)
    {
        $users = get_users(['meta_key' => 'api_token']);

        if (current_user_can('remove_users') && !empty($users)) {
            foreach ($users as $user) {
                do_action('api-connector-unset-user-data', $user->ID);
            }
        }

        if (!$background) {
            wp_redirect(esc_url_raw($this->settingsUrl() . '&all-tokens-revoked=1'));
            exit;
        }
    }

    /**
     * Get current URL
     *
     * @return string
     */
    protected function settingsUrl()
    {
        return set_url_scheme(admin_url('admin.php?page=api-connector'));
    }
}
