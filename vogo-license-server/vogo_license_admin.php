<?php
/**
 * VOGO License Admin Module.
 *
 * This file provides a dedicated admin experience for license-server operations:
 * - dashboard summary cards and navigation shortcuts;
 * - running status screen for operational checks;
 * - license table management (view/add/edit/delete) for `cvogo_encrypted_data`.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Returns the fully qualified table name that stores license rows.
 */
function vogo_license_admin_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'cvogo_encrypted_data';
}

/**
 * Handles add/update/delete operations for the license table admin page.
 */
function vogo_license_admin_handle_actions() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('vogo_license_admin_action');

    global $wpdb;
    $table = vogo_license_admin_table_name();
    $action = isset($_POST['vogo_license_action']) ? sanitize_key($_POST['vogo_license_action']) : '';

    // Shared field extraction used by add/update actions.
    $payload = [
        'col1' => sanitize_text_field(wp_unslash($_POST['col1'] ?? '')),
        'col2' => esc_url_raw(wp_unslash($_POST['col2'] ?? '')),
        'col3' => sanitize_text_field(wp_unslash($_POST['col3'] ?? '')),
        'col4' => sanitize_text_field(wp_unslash($_POST['col4'] ?? '')),
        'col5' => sanitize_text_field(wp_unslash($_POST['col5'] ?? '')),
        'col6' => sanitize_text_field(wp_unslash($_POST['col6'] ?? '')),
    ];

    if ($action === 'add') {
        $wpdb->insert($table, $payload, ['%s', '%s', '%s', '%s', '%s', '%s']);
    }

    if ($action === 'update') {
        // We use original activation code as row selector because activation lookups are based on col5.
        $original_col5 = sanitize_text_field(wp_unslash($_POST['original_col5'] ?? ''));
        if ($original_col5 !== '') {
            $wpdb->update(
                $table,
                $payload,
                ['col5' => $original_col5],
                ['%s', '%s', '%s', '%s', '%s', '%s'],
                ['%s']
            );
        }
    }

    if ($action === 'delete') {
        $delete_col5 = sanitize_text_field(wp_unslash($_POST['delete_col5'] ?? ''));
        if ($delete_col5 !== '') {
            $wpdb->delete($table, ['col5' => $delete_col5], ['%s']);
        }
    }

    wp_safe_redirect(add_query_arg([
        'page' => 'vogo-license-table',
        'updated' => '1',
    ], admin_url('admin.php')));
    exit;
}

add_action('admin_post_vogo_license_admin_action', 'vogo_license_admin_handle_actions');

/**
 * Renders the VOGO license operations dashboard with synthesis cards and quick links.
 */
function vogo_brand_options_render_license_dashboard_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    $license_table = vogo_license_admin_table_name();
    $call_table = $wpdb->prefix . 'activate_and_get_updates_calls';

    $license_rows = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$license_table}");
    $today_calls = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$call_table} WHERE DATE(created_at) = %s", current_time('Y-m-d')));
    $last_activity = $wpdb->get_var("SELECT MAX(created_at) FROM {$call_table}");

    echo '<div class="wrap vogo-license-dashboard">';
    echo '<h1>VOGO License Server Dashboard</h1>';
    echo '<p>Operational control panel for license-server management and diagnostics.</p>';

    /** Summary cards section. */
    echo '<div class="vogo-license-cards">';
    echo '<div class="vogo-license-card"><h3>License records</h3><p>' . (int) $license_rows . '</p></div>';
    echo '<div class="vogo-license-card"><h3>Activation calls (today)</h3><p>' . (int) $today_calls . '</p></div>';
    echo '<div class="vogo-license-card"><h3>Last activity</h3><p>' . esc_html($last_activity ?: 'No activity yet') . '</p></div>';
    echo '</div>';

    /** Main actions section. */
    echo '<div class="vogo-license-actions">';
    echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=vogo-running-status')) . '">Running status</a>';
    echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=vogo-view-logs')) . '">View logs</a>';
    echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=vogo-license-table')) . '">Manage licenses</a>';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=vogo-license-control-center')) . '">License Control Center</a>';
    echo '</div>';

    echo '</div>';

    echo '<style>
        .vogo-license-dashboard .vogo-license-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin:18px 0; }
        .vogo-license-dashboard .vogo-license-card { background:#fff; border:1px solid #dbe3ef; border-radius:14px; padding:16px; box-shadow:0 8px 20px rgba(15,23,42,.06); }
        .vogo-license-dashboard .vogo-license-card h3 { margin:0 0 10px; font-size:15px; color:#334155; }
        .vogo-license-dashboard .vogo-license-card p { margin:0; font-size:24px; font-weight:700; color:#0f172a; }
        .vogo-license-dashboard .vogo-license-actions { display:flex; flex-wrap:wrap; gap:10px; }
    </style>';
}

/**
 * Renders a running-status page with validated operational checks.
 */
function vogo_brand_options_render_running_status_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;

    $license_table = vogo_license_admin_table_name();
    $calls_table = $wpdb->prefix . 'activate_and_get_updates_calls';
    $log_dir = rtrim(WP_CONTENT_DIR, '/\\') . '/vogo-logs';

    /** Each check is based only on direct runtime data. */
    $checks = [
        'Plugin loaded' => defined('VOGO_PLUGIN_VERSION'),
        'License table available' => $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $license_table)) === $license_table,
        'Audit table available' => $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $calls_table)) === $calls_table,
        'Logs directory available' => is_dir($log_dir),
        'Logs directory writable' => is_dir($log_dir) && is_writable($log_dir),
        'REST API enabled' => function_exists('rest_get_url_prefix'),
    ];

    echo '<div class="wrap vogo-running-status">';
    echo '<h1>Running Status</h1>';
    echo '<p>Live operational checks for license-server runtime components.</p>';
    echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=vogo-license-dashboard')) . '">Back to dashboard</a></p>';
    echo '<table class="widefat striped"><thead><tr><th>Check</th><th>Status</th></tr></thead><tbody>';
    foreach ($checks as $label => $ok) {
        echo '<tr><td>' . esc_html($label) . '</td><td>' . ($ok ? '<span style="color:#15803d;font-weight:600;">Running</span>' : '<span style="color:#b91c1c;font-weight:600;">Issue</span>') . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
}

/**
 * Renders the license table management page (list + add + edit + delete).
 */
function vogo_brand_options_render_license_table_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    $table = vogo_license_admin_table_name();

    $edit_code = isset($_GET['edit_col5']) ? sanitize_text_field(wp_unslash($_GET['edit_col5'])) : '';
    $edit_row = null;
    if ($edit_code !== '') {
        $edit_row = $wpdb->get_row($wpdb->prepare("SELECT col1, col2, col3, col4, col5, col6 FROM {$table} WHERE col5 = %s LIMIT 1", $edit_code), ARRAY_A);
    }

    $rows = $wpdb->get_results("SELECT col1, col2, col3, col4, col5, col6 FROM {$table} ORDER BY col3 DESC LIMIT 200", ARRAY_A);

    echo '<div class="wrap vogo-license-table">';
    echo '<h1>License Table Management</h1>';
    echo '<p>Manage records from <code>' . esc_html($table) . '</code> used by activation and license validation flows.</p>';
    echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=vogo-license-dashboard')) . '">Back to dashboard</a></p>';

    if (!empty($_GET['updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>License table updated successfully.</p></div>';
    }

    /** Add / Edit form section. */
    echo '<div class="postbox" style="padding:16px;max-width:980px;">';
    echo '<h2 style="margin-top:0;">' . ($edit_row ? 'Edit license record' : 'Add license record') . '</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('vogo_license_admin_action');
    echo '<input type="hidden" name="action" value="vogo_license_admin_action" />';
    echo '<input type="hidden" name="vogo_license_action" value="' . esc_attr($edit_row ? 'update' : 'add') . '" />';
    if ($edit_row) {
        echo '<input type="hidden" name="original_col5" value="' . esc_attr($edit_row['col5']) . '" />';
    }

    $fields = [
        'col1' => 'License code',
        'col2' => 'Web API URL',
        'col3' => 'Valid until (YYYY-MM-DD)',
        'col4' => 'License level',
        'col5' => 'Activation code',
        'col6' => 'Fiscal code',
    ];

    echo '<table class="form-table"><tbody>';
    foreach ($fields as $key => $label) {
        $value = $edit_row[$key] ?? '';
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
        echo '<td><input type="text" class="regular-text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" /></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<p><button type="submit" class="button button-primary">' . ($edit_row ? 'Save changes' : 'Add record') . '</button></p>';
    echo '</form>';
    echo '</div>';

    /** Data grid section. */
    echo '<h2>Existing license records</h2>';
    echo '<table class="widefat striped"><thead><tr><th>License code</th><th>Web API URL</th><th>Valid until</th><th>License level</th><th>Activation code</th><th>Fiscal code</th><th>Actions</th></tr></thead><tbody>';
    if (empty($rows)) {
        echo '<tr><td colspan="7">No license records found.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $edit_url = add_query_arg(['page' => 'vogo-license-table', 'edit_col5' => $row['col5']], admin_url('admin.php'));

            echo '<tr>';
            echo '<td>' . esc_html($row['col1']) . '</td>';
            echo '<td>' . esc_html($row['col2']) . '</td>';
            echo '<td>' . esc_html($row['col3']) . '</td>';
            echo '<td>' . esc_html($row['col4']) . '</td>';
            echo '<td>' . esc_html($row['col5']) . '</td>';
            echo '<td>' . esc_html($row['col6']) . '</td>';
            echo '<td>';
            echo '<a class="button button-small" href="' . esc_url($edit_url) . '">Edit</a> ';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;">';
            wp_nonce_field('vogo_license_admin_action');
            echo '<input type="hidden" name="action" value="vogo_license_admin_action" />';
            echo '<input type="hidden" name="vogo_license_action" value="delete" />';
            echo '<input type="hidden" name="delete_col5" value="' . esc_attr($row['col5']) . '" />';
            echo '<button type="submit" class="button button-small" onclick="return confirm(\'Delete this license record?\');">Delete</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
    echo '</div>';
}
