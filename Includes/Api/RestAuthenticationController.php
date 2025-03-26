<?php
namespace ApiConnector\Api;

if (!defined('ABSPATH')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit;
}

use \WP_Error;

/**
 * REST API authentication class.
 */
class RestAuthenticationController
{
    /**
     * Authentication error.
     *
     * @var WP_Error
     */
    protected $error = null;

    /**
     * Logged in user data.
     *
     * @var stdClass
     */
    protected $user = null;

    /**
     * Current auth method.
     *
     * @var string
     */
    protected $authMethod = '';

    /**
     * Initialize authentication actions.
     */
    public function __construct()
    {
        add_filter('determine_current_user', [$this, 'authenticate'], 15);
        add_filter('rest_authentication_errors', [$this, 'authenticationFallback']);
        add_filter('rest_authentication_errors', [$this, 'checkAuthenticationError'], 15);
        add_filter('rest_post_dispatch', [$this, 'sendUnauthorizedHeaders'], 50);
        add_filter('rest_pre_dispatch', [$this, 'checkUserPermissions'], 10, 3);
    }

    /**
     * Check if is request to our REST API.
     *
     * @return bool
     */
    protected function isRequestToRestApi()
    {
        if (empty($_SERVER['REQUEST_URI'])) {
            return false;
        }

        $restPrefix = trailingslashit(rest_get_url_prefix());
        $requestUri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));

        // Check if the request is to the API endpoints.
        $apiRequest = (false !== strpos($requestUri, $restPrefix . 'api-connector/'));

        return apply_filters('api_connector_rest_is_request_to_rest_api', $apiRequest);
    }

    /**
     * Authenticate user.
     *
     * @param int|false $userId User ID if one has been determined, false otherwise.
     *
     * @return int|false
     */
    public function authenticate($userId)
    {
        // Do not authenticate twice and check if is a request to our endpoint in the WP REST API.
        if (!empty($userId) || !$this->isRequestToRestApi()) {
            return $userId;
        }

        $userId = $this->performBasicAuthentication();

        if ($userId) {
            return $userId;
        }
    }

    /**
     * Authenticate the user if authentication wasn't performed during the
     * determine_current_user action.
     *
     * @param WP_Error|null|bool $error Error data.
     *
     * @return WP_Error|null|bool
     */
    public function authenticationFallback($error)
    {
        if (!empty($error)) {
            // Another plugin has already declared a failure.
            return $error;
        }
        if (empty($this->error) && empty($this->authMethod) && empty($this->user) && 0 === get_current_user_id()) {
            // Authentication hasn't occurred during `determine_current_user`, so check auth.
            $userId = $this->authenticate(false);
            if ($userId) {
                wp_set_current_user($userId);

                return true;
            }
        }

        return $error;
    }

    /**
     * Check for authentication error.
     *
     * @param WP_Error|null|bool $error Error data.
     *
     * @return WP_Error|null|bool
     */
    public function checkAuthenticationError($error)
    {
        // Pass through other errors.
        if (!empty($error)) {
            return $error;
        }

        return $this->getError();
    }

    /**
     * Set authentication error.
     *
     * @param WP_Error $error Authentication error data.
     */
    protected function setError($error)
    {
        // Reset user.
        $this->user = null;

        $this->error = $error;
    }

    /**
     * Get authentication error.
     *
     * @return WP_Error|null.
     */
    protected function getError()
    {
        return $this->error;
    }

    /**
     * Basic Authentication.
     *
     * SSL-encrypted requests are not subject to sniffing or man-in-the-middle
     * attacks, so the request can be authenticated by simply looking up the user
     * associated with the given consumer key and confirming the consumer secret
     * provided is valid.
     *
     * @return int|bool
     */
    private function performBasicAuthentication()
    {
        $this->authMethod = 'basic_auth';
        $consumerKey = '';
        $consumerSecret = '';

        // If the above is not present, we will do full basic auth.
        if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
            $consumerKey = $_SERVER['PHP_AUTH_USER']; // WPCS: CSRF ok, sanitization ok.
            $consumerSecret = $_SERVER['PHP_AUTH_PW']; // WPCS: CSRF ok, sanitization ok.
        }

        // Stop if don't have any key.
        if (!$consumerKey || !$consumerSecret) {
            return false;
        }

        // Get user data.
        $this->user = $this->getUserDataByConsumerKey($consumerSecret);
        if (empty($this->user)) {
            $this->setError(
                new WP_Error(
                    'api_connector_rest_authentication_error',
                    __('User token is invalid.', 'api-connector'),
                    ['status' => 401]
                )
            );

            return false;
        }

        return $this->user->ID;
    }

    /**
     * Return the user data for the given consumer_secret.
     *
     * @param $consumerSecret
     *
     * @return false|mixed
     */
    private function getUserDataByConsumerKey($consumerSecret)
    {
        $users = get_users(['meta_key' => 'api_token', 'meta_value' => $consumerSecret]);

        if (!empty($users)) {
            return $users[0];
        }

        return false;
    }

    /**
     * Check that the API keys provided have the proper key-specific permissions to either read or write API resources.
     *
     * @param string $method Request method.
     *
     * @return bool|WP_Error
     */
    private function checkPermissions($method)
    {
        $permissions = $this->user->allcaps;

        switch ($method) {
            case 'POST':
            case 'GET':
                if (!isset($permissions['publish_posts']) || !$permissions['publish_posts']) {
                    return new WP_Error(
                        'api_connector_rest_authentication_error',
                        __('The API token provided does not have write permissions.', 'api-connector'),
                        ['status' => 401]
                    );
                }
                break;
            default:
                return new WP_Error(
                    'api_connector_rest_authentication_error',
                    __('Unknown request method.', 'api-connector'),
                    ['status' => 401]
                );
        }

        return true;
    }

    /**
     * Updated API Key last access datetime.
     */
    private function updateLastAccess()
    {
        update_user_meta($this->user->ID, 'api_token_last_used', current_time('mysql'));
    }

    /**
     * If the consumer_key and consumer_secret $_GET parameters are NOT provided
     * and the Basic auth headers are either not present or the consumer secret does not match the consumer
     * key provided, then return the correct Basic headers and an error message.
     *
     * @param \WP_REST_Response $response Current response being served.
     *
     * @return \WP_REST_Response
     */
    public function sendUnauthorizedHeaders($response)
    {
        if (is_wp_error($this->getError()) && 'basic_auth' === $this->authMethod) {
            $authMessage = __(
                'API Connector. Use a consumer key in the username field and a consumer secret in the password field.',
                'api-connector'
            );
            $response->header('WWW-Authenticate', 'Basic realm="' . $authMessage . '"', true);
        }

        return $response;
    }

    /**
     * Check for user permissions and register last access.
     *
     * @param mixed $result Response to replace the requested version with.
     * @param \WP_REST_Server $server Server instance.
     * @param \WP_REST_Request $request Request used to generate the response.
     *
     * @return mixed
     */
    public function checkUserPermissions($result, $server, $request)
    {
        if ($this->user) {
            // Check API Key permissions.
            $allowed = $this->checkPermissions($request->get_method());
            if (is_wp_error($allowed)) {
                return $allowed;
            }

            // Register last access.
            $this->updateLastAccess();
        }

        return $result;
    }
}
