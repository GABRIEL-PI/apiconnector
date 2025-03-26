<?php

namespace ApiConnector\Admin;

use ApiConnector\AjaxController;
use ApiConnector\Api\AuthorizationController;

if (!defined('ABSPATH')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit;
}

class SettingsPanel
{
    public function __construct()
    {
        if (isset($_GET['page']) && $_GET['page'] === 'api-connector') {
            add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueStyles']);
        }
        add_action('admin_menu', [$this, 'createSection']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('plugin_action_links_api-connector/apiConnector.php', [$this, 'pluginSettingsLink'], 10, 1);
        add_action('activated_plugin', [$this, 'activationRedirect']);

        add_action('api-connector-unset-user-data', [$this, 'unsetData']);
        
        // Adicionar ação para salvar o token inserido manualmente
        add_action('admin_post_save_api_token', [$this, 'saveApiToken']);
    }

    /**
     * Salvar o token inserido manualmente
     */
    public function saveApiToken()
    {
        if (!current_user_can('edit_posts') || !isset($_POST['api_token_nonce']) || 
            !wp_verify_nonce($_POST['api_token_nonce'], 'save_api_token_action')) {
            wp_die(__('Ação não autorizada.', 'api-connector'));
        }
        
        $token = sanitize_text_field($_POST['api_token']);
        if (!empty($token)) {
            $currentUser = wp_get_current_user();
            update_user_meta($currentUser->ID, 'api_token', $token);
            update_user_meta($currentUser->ID, 'api_token_created', current_time('mysql'));
            
            wp_redirect(admin_url('admin.php?page=api-connector&token_saved=1'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=api-connector&token_error=1'));
            exit;
        }
    }

    /**
     * Enqueue view admin js scripts
     *
     * @return void
     */
    public function enqueueScripts()
    {
        wp_enqueue_script(
            'api-connector',
            plugins_url('public/dist/adminMain.bundle.js', APICONNECTOR_ROOT),
            [],
            APICONNECTOR_VERSION,
            true
        );
    }

    /**
     * Enqueue admin page styles
     *
     * @return void
     */
    public function enqueueStyles()
    {
        wp_enqueue_style(
            'api-connector',
            plugins_url('public/dist/adminMain.bundle.css', APICONNECTOR_ROOT),
            [],
            APICONNECTOR_VERSION,
        );
        
        // Adicionar estilos inline
        wp_add_inline_style('api-connector', '
            .api-token-form {
                margin-top: 20px;
                max-width: 600px;
            }
            .api-token-field {
                width: 100%;
                padding: 8px;
                font-family: monospace;
                margin-bottom: 10px;
            }
            .api-token-submit {
                background: #2271b1;
                border-color: #2271b1;
                color: #fff;
                text-decoration: none;
                padding: 8px 12px;
                border-radius: 3px;
                cursor: pointer;
                font-size: 14px;
            }
            .api-token-submit:hover {
                background: #135e96;
                border-color: #135e96;
            }
            .api-notice {
                padding: 10px 15px;
                margin: 15px 0;
                border-radius: 3px;
            }
            .api-notice-success {
                background-color: #ecf7ed;
                border-left: 4px solid #46b450;
            }
            .api-notice-error {
                background-color: #fbeaea;
                border-left: 4px solid #dc3232;
            }
        ');
    }

    /**
     * Add section in wp-admin
     *
     * @return void
     */
    public function createSection()
    {
        $cap = 'edit_posts';

        add_menu_page(
            __('API Connector', 'api-connector'),
            __('API Connector', 'api-connector'),
            $cap,
            'api-connector',
            [$this, 'content'],
            'dashicons-rest-api',
            80
        );
    }

    /**
     * Render content
     *
     * @return void
     */
    public function content()
    {
        $ajaxController = new AjaxController(); ?>
        <div class="api-connector-settings">
        <?php
        // Exibir mensagens de sucesso ou erro
        if (isset($_GET['token_saved']) && $_GET['token_saved'] == 1) {
            echo '<div class="api-notice api-notice-success">';
            echo '<p>' . esc_html__('Token salvo com sucesso!', 'api-connector') . '</p>';
            echo '</div>';
        }
        
        if (isset($_GET['token_error']) && $_GET['token_error'] == 1) {
            echo '<div class="api-notice api-notice-error">';
            echo '<p>' . esc_html__('Erro ao salvar o token. Por favor, tente novamente.', 'api-connector') . '</p>';
            echo '</div>';
        }
        
        if ($this->isAuthorize() || current_user_can('remove_users')) {
            if (current_user_can('remove_users')) {
                $users = get_users(['meta_key' => 'api_token',]);
            } elseif (!current_user_can('remove_users')) {
                $user = wp_get_current_user();
                $users = get_users(['meta_key' => 'api_token', 'include' => [$user->ID],]);
            }
            if (!empty($users)) {
                require_once 'View/TokenTable.php';
            }
        }
        ?>
            <div class="api-authorize-wrapper">
                <h1><?php esc_html_e('API Connector', 'api-connector'); ?></h1>
                <div class="api-content-wrapper">
                    <p><?php esc_html_e('Conecte seu WordPress a serviços externos via API REST personalizada.', 'api-connector'); ?></p>
                    
                    <?php if (!$this->isAuthorize()) : ?>
                    <div class="api-token-form">
                        <h3><?php esc_html_e('Adicionar Token de API', 'api-connector'); ?></h3>
                        <p><?php esc_html_e('Cole o token gerado pelo seu aplicativo externo:', 'api-connector'); ?></p>
                        
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="save_api_token">
                            <?php wp_nonce_field('save_api_token_action', 'api_token_nonce'); ?>
                            
                            <input type="text" name="api_token" class="api-token-field" placeholder="<?php esc_attr_e('Cole seu token aqui', 'api-connector'); ?>" required>
                            
                            <button type="submit" class="api-token-submit">
                                <?php esc_html_e('Salvar Token', 'api-connector'); ?>
                            </button>
                        </form>
                        
                        <p class="description">
                            <?php esc_html_e('Importante: Guarde seu token em um local seguro. Ele será necessário para autenticar suas solicitações de API.', 'api-connector'); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($this->isAuthorize()) : ?>
                        <h3><?php esc_html_e('Endpoints da API', 'api-connector'); ?></h3>
                        <p><?php esc_html_e('Use os seguintes endpoints para interagir com seu WordPress:', 'api-connector'); ?></p>
                        <ul>
                            <li><code><?php echo esc_html(rest_url('api-connector/v1/posts')); ?></code> - <?php esc_html_e('Gerenciar posts', 'api-connector'); ?></li>
                            <li><code><?php echo esc_html(rest_url('api-connector/v1/media')); ?></code> - <?php esc_html_e('Gerenciar mídia', 'api-connector'); ?></li>
                            <li><code><?php echo esc_html(rest_url('api-connector/v1/categories')); ?></code> - <?php esc_html_e('Listar categorias', 'api-connector'); ?></li>
                            <li><code><?php echo esc_html(rest_url('api-connector/v1/tags')); ?></code> - <?php esc_html_e('Listar tags', 'api-connector'); ?></li>
                        </ul>
                        <h3><?php esc_html_e('Autenticação', 'api-connector'); ?></h3>
                        <p><?php esc_html_e('Use autenticação básica HTTP com seu nome de usuário e o token salvo como senha.', 'api-connector'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Register/add settings fields
     *
     * @return void
     */
    public function registerSettings()
    {
        register_setting(
            'apiSettings',
            'apiSettings',
            'api_settings_args'
        );

        add_settings_section(
            'apiSettings',
            null,
            null,
            'apiSettingsFields'
        );
    }

    /**
     * Get admin url
     *
     * @return string
     */
    public function getAdminUrl()
    {
        return esc_url(
            add_query_arg(
                'page',
                'api-connector',
                get_admin_url() . 'admin.php'
            )
        );
    }

    /**
     * Add settings link to plugins page
     *
     * @param $links
     *
     * @return mixed
     */
    public function pluginSettingsLink($links)
    {
        return array_merge(
            [
                sprintf(
                    '<a href="%s">%s</a>',
                    esc_url($this->getAdminUrl()),
                    esc_html__('Settings', 'api-connector')
                ),
            ],
            $links
        );
    }

    /**
     * Redirect to settings after plugin activation
     *
     * @param $plugin
     *
     * @return void
     */
    public function activationRedirect($plugin)
    {
        if ($plugin == plugin_basename(APICONNECTOR_ROOT)) {
            exit(wp_redirect(esc_url($this->getAdminUrl())));
        }
    }

    /**
     * Check if user is authorized
     *
     * @return string
     */
    public function isAuthorize()
    {
        $currentUser = wp_get_current_user();
        $userId = $currentUser->ID;

        if (!empty(get_user_meta($userId, 'api_token', true))) {
            return true;
        }

        return false;
    }

    /**
     * Unset added data
     *
     * @param $id
     *
     * @return void
     */
    public function unsetData($id)
    {
        delete_user_meta($id, 'api_token');
        delete_user_meta($id, 'api_token_created');
        delete_user_meta($id, 'api_token_last_used');
    }
}
