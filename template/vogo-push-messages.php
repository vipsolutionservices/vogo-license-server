<?php
/**
 * VOGO Push Messages Admin Module.
 *
 * Provides the admin UI and persistence layer for composing push messages,
 * storing delivery metadata, and manually triggering the delivery job.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolve the push messages table name with the active WordPress prefix.
 *
 * @return string
 */
function vogo_push_messages_table() {
    global $wpdb;
    return $wpdb->prefix . 'vogo_push_messages';
}

/**
 * Resolve the push delivery details table name with the active WordPress prefix.
 *
 * @return string
 */
function vogo_push_messages_details_table() {
    global $wpdb;
    return $wpdb->prefix . 'vogo_push_messages_details';
}

/**
 * Create or update required push message tables.
 */
function vogo_push_messages_ensure_tables() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();

    $sql_messages = 'CREATE TABLE ' . vogo_push_messages_table() . " (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        mesaj VARCHAR(1000) NOT NULL,
        image VARCHAR(500) DEFAULT '',
        ready_to_deliver TINYINT NOT NULL DEFAULT 0,
        creation_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        delivery_date DATETIME NULL,
        number_delivered INT NOT NULL DEFAULT 0,
        error_messaje TEXT NULL,
        PRIMARY KEY (id),
        KEY ready_to_deliver (ready_to_deliver)
    ) $charset_collate;";

    $sql_details = 'CREATE TABLE ' . vogo_push_messages_details_table() . " (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        id_mesaj BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        delivery_date DATETIME NULL,
        answer TEXT NULL,
        PRIMARY KEY (id),
        KEY id_mesaj (id_mesaj),
        KEY user_id (user_id)
    ) $charset_collate;";

    dbDelta($sql_messages);
    dbDelta($sql_details);
}

/**
 * Register the push messages submenu under Brand Control Center.
 */
function vogo_push_messages_register_menu() {
    add_submenu_page(
        'vogo-brand-options',
        'Push messages to clients',
        'Push messages to clients',
        'manage_options',
        'vogo-push-messages',
        'vogo_push_messages_render_page'
    );
}

add_action('admin_menu', 'vogo_push_messages_register_menu', 20);

/**
 * Load the WordPress media library scripts only on the push messages screen.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function vogo_push_messages_enqueue_admin_assets($hook_suffix) {
    if ($hook_suffix !== 'brand-control-center_page_vogo-push-messages') {
        return;
    }

    wp_enqueue_media();
}

add_action('admin_enqueue_scripts', 'vogo_push_messages_enqueue_admin_assets');

/**
 * Handle push message create submissions from the admin form.
 */
function vogo_push_messages_handle_create() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized request.', 'Unauthorized', ['response' => 403]);
    }

    check_admin_referer('vogo_push_messages_create');
    vogo_push_messages_ensure_tables();

    global $wpdb;
    $mesaj = sanitize_textarea_field((string) ($_POST['mesaj'] ?? ''));
    $image = esc_url_raw((string) ($_POST['image'] ?? ''));
    $ready = (int) ($_POST['ready_to_deliver'] ?? 0);
    if ($ready < 0 || $ready > 5) {
        $ready = 0;
    }

    if ($mesaj !== '') {
        $wpdb->insert(
            vogo_push_messages_table(),
            [
                'mesaj' => mb_substr($mesaj, 0, 1000),
                'image' => $image,
                'ready_to_deliver' => $ready,
                'creation_date' => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%s']
        );
    }

    wp_safe_redirect(add_query_arg(['page' => 'vogo-push-messages', 'created' => '1'], admin_url('admin.php')));
    exit;
}

add_action('admin_post_vogo_push_messages_create', 'vogo_push_messages_handle_create');

/**
 * Render push message management UI and delivery audit tables.
 */
function vogo_push_messages_render_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized request.', 'Unauthorized', ['response' => 403]);
    }

    vogo_push_messages_ensure_tables();

    global $wpdb;
    $messages = $wpdb->get_results('SELECT * FROM ' . vogo_push_messages_table() . ' ORDER BY id DESC LIMIT 100', ARRAY_A);
    $details = $wpdb->get_results('SELECT * FROM ' . vogo_push_messages_details_table() . ' ORDER BY id DESC LIMIT 300', ARRAY_A);

    echo '<div class="wrap">';
    echo '<h1>Push messages to clients</h1>';
    echo '<p><a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=vogo-brand-options')) . '">Back to Brand Control Center</a></p>';

    if (!empty($_GET['created'])) {
        echo '<div class="notice notice-success"><p>Push message saved.</p></div>';
    }
    if (!empty($_GET['job'])) {
        echo '<div class="notice notice-success"><p>Push job executed.</p></div>';
    }

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0 20px;">';
    wp_nonce_field('vogo_push_messages_run_job');
    echo '<input type="hidden" name="action" value="vogo_push_messages_run_job" />';
    echo '<button type="submit" class="button">Run push delivery job now</button>';
    echo '</form>';

    echo '<h2>Create push message</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('vogo_push_messages_create');
    echo '<input type="hidden" name="action" value="vogo_push_messages_create" />';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th scope="row"><label for="vogo-push-message">Mesaj</label></th><td><textarea id="vogo-push-message" name="mesaj" rows="4" maxlength="1000" style="width:100%"></textarea></td></tr>';
    // Section: image URL input with media picker button, matching brand admin UX.
    echo '<tr><th scope="row"><label for="vogo-push-image">Image URL</label></th><td><div style="display:flex; gap:8px; align-items:center;"><input id="vogo-push-image" type="url" name="image" style="width:100%" /><button type="button" class="button button-secondary vogo-brand-media-picker" data-target="vogo-push-image" title="Select image">...</button></div></td></tr>';
    echo '<tr><th scope="row"><label for="vogo-push-ready">Ready to deliver</label></th><td><select id="vogo-push-ready" name="ready_to_deliver"><option value="0">0 - not ready</option><option value="1">1 - ready</option></select></td></tr>';
    echo '</tbody></table>';
    echo '<p><button type="submit" class="button button-primary">Save push message</button></p>';
    echo '</form>';

    // Section: list of queued/processed push messages.
    echo '<h2>vogo_push_messages</h2>';
    echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Mesaj</th><th>Image</th><th>Ready</th><th>Creation</th><th>Delivery</th><th>Delivered</th><th>Error</th></tr></thead><tbody>';
    foreach ($messages as $row) {
        echo '<tr>';
        echo '<td>' . esc_html((string) $row['id']) . '</td>';
        echo '<td>' . esc_html((string) $row['mesaj']) . '</td>';
        echo '<td>' . esc_html((string) $row['image']) . '</td>';
        echo '<td>' . esc_html((string) $row['ready_to_deliver']) . '</td>';
        echo '<td>' . esc_html((string) $row['creation_date']) . '</td>';
        echo '<td>' . esc_html((string) $row['delivery_date']) . '</td>';
        echo '<td>' . esc_html((string) $row['number_delivered']) . '</td>';
        echo '<td>' . esc_html((string) $row['error_messaje']) . '</td>';
        echo '</tr>';
    }
    if (!$messages) {
        echo '<tr><td colspan="8">No push messages found.</td></tr>';
    }
    echo '</tbody></table>';

    // Section: per-user delivery history for push jobs.
    echo '<h2 style="margin-top:24px;">vogo_push_messages_details</h2>';
    echo '<table class="widefat striped"><thead><tr><th>ID</th><th>ID mesaj</th><th>User ID</th><th>Delivery date</th><th>Answer</th></tr></thead><tbody>';
    foreach ($details as $row) {
        echo '<tr>';
        echo '<td>' . esc_html((string) $row['id']) . '</td>';
        echo '<td>' . esc_html((string) $row['id_mesaj']) . '</td>';
        echo '<td>' . esc_html((string) $row['user_id']) . '</td>';
        echo '<td>' . esc_html((string) $row['delivery_date']) . '</td>';
        echo '<td>' . esc_html((string) $row['answer']) . '</td>';
        echo '</tr>';
    }
    if (!$details) {
        echo '<tr><td colspan="5">No delivery details found.</td></tr>';
    }
    echo '</tbody></table>';

    // Section: media picker behavior for selecting images from WordPress Media Library.
    echo '<script>
        (function() {
            "use strict";
            document.querySelectorAll(".vogo-brand-media-picker").forEach(function(button) {
                button.addEventListener("click", function() {
                    if (!window.wp || !wp.media) {
                        return;
                    }

                    const targetId = button.dataset.target || "";
                    if (!targetId) {
                        return;
                    }

                    const targetInput = document.getElementById(targetId);
                    if (!targetInput) {
                        return;
                    }

                    const frame = wp.media({
                        title: "Select image",
                        button: { text: "Use image" },
                        multiple: false,
                        library: { type: "image" }
                    });

                    frame.on("select", function() {
                        const attachment = frame.state().get("selection").first().toJSON();
                        targetInput.value = attachment.url || "";
                        targetInput.dispatchEvent(new Event("input", { bubbles: true }));
                        targetInput.dispatchEvent(new Event("change", { bubbles: true }));
                    });

                    frame.open();
                });
            });
        })();
    </script>';

    echo '</div>';
}
