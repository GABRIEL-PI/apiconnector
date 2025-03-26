<h2><?php esc_html_e('Tokens de API Autorizados', 'api-connector'); ?></h2>
<div class="application-passwords-list-table-wrapper">
    <table class="wp-list-table widefat fixed striped table-view-list application-passwords-user">
        <thead>
        <tr>
            <th scope="col" id="name" class="manage-column column-name column-primary">
                <?php esc_html_e('Nome', 'api-connector'); ?>
            </th>
            <th scope="col" id="created" class="manage-column column-created">
                <?php esc_html_e('Criado em', 'api-connector'); ?>
            </th>
            <th scope="col" id="last_used" class="manage-column column-last_used">
                <?php esc_html_e('Último uso', 'api-connector'); ?>
            </th>
            <th scope="col" id="token" class="manage-column column-token">
                <?php esc_html_e('Token', 'api-connector'); ?>
            </th>
            <th scope="col" id="revoke" class="manage-column column-revoke">
                <?php esc_html_e('Revogar', 'api-connector'); ?>
            </th>
        </tr>
        </thead>

        <tbody id="the-list">
        <?php foreach ($users as $user) : ?>
            <tr>
                <td class="name column-name has-row-actions column-primary"
                    data-colname="<?php esc_attr_e('Nome', 'api-connector'); ?>">
                    <?php echo esc_html($user->data->display_name); ?>
                </td>
                <td class="created column-created"
                    data-colname="<?php esc_attr_e('Criado em', 'api-connector'); ?>">
                    <?php
                        echo wp_date(
                            get_option('date_format') . ', ' . get_option('time_format'),
                            strtotime(get_user_meta($user->ID, 'api_token_created', true))
                        );
                    ?>
                </td>
                <td class="last_used column-last_used"
                    data-colname="<?php esc_attr_e('Último uso', 'api-connector'); ?>">
                    <?php if (get_user_meta($user->ID, 'api_token_last_used', true)) {
                        echo wp_date(
                            get_option('date_format') . ', ' . get_option('time_format'),
                            strtotime(get_user_meta($user->ID, 'api_token_last_used', true))
                        );
                    } else {
                        esc_html_e('Nunca', 'api-connector');
                    } ?>
                </td>
                <td class="token column-token"
                    data-colname="<?php esc_attr_e('Token', 'api-connector'); ?>">
                    <code><?php echo esc_html(get_user_meta($user->ID, 'api_token', true)); ?></code>
                </td>
                <td class="revoke column-revoke"
                    data-colname="<?php esc_attr_e('Revogar', 'api-connector'); ?>">
                    <a href="<?php echo esc_url(
                        $ajaxController->adminAjax(
                            [
                                'api-connector-action' => 'api-revoke-token-adminNonce',
                                'api-connector-nonce' => $ajaxController->admin(),
                                'userId' => $user->ID,
                            ]
                        )
                    ); ?>" class="button delete"><?php esc_html_e('Revogar', 'api-connector'); ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr>
            <th scope="col" id="name" class="manage-column column-name column-primary">
                <?php esc_html_e('Nome', 'api-connector'); ?>
            </th>
            <th scope="col" id="created" class="manage-column column-created">
                <?php esc_html_e('Criado em', 'api-connector'); ?>
            </th>
            <th scope="col" id="last_used" class="manage-column column-last_used">
                <?php esc_html_e('Último uso', 'api-connector'); ?>
            </th>
            <th scope="col" id="token" class="manage-column column-token">
                <?php esc_html_e('Token', 'api-connector'); ?>
            </th>
            <th scope="col" id="revoke" class="manage-column column-revoke">
                <?php esc_html_e('Revogar', 'api-connector'); ?>
            </th>
        </tr>
        </tfoot>

    </table>
    <?php if (current_user_can('remove_users')) : ?>
    <div class="tablenav bottom">
        <div class="alignright">
            <a type="button"
               href="<?php echo esc_url(
                   $ajaxController->adminAjax(
                       [
                           'api-connector-action' => 'api-revoke-all-tokens-adminNonce',
                           'api-connector-nonce' => $ajaxController->admin(),
                       ]
                   )
               ); ?>" class="button delete">
                <?php esc_html_e('Revogar todos os tokens', 'api-connector'); ?>
            </a>
        </div>
        <div class="alignleft actions bulkactions">
        </div>
        <br class="clear">
    </div>
    <?php endif; ?>
</div>