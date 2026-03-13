<?php
/**
 * Setup helpers for the diagnostics "Section Users & Roles".
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build support email from current brand name, with a safe domain fallback.
 */
function vogo_support_setup_get_support_email() {
    $brand_name = '';
    if (function_exists('vogo_brand_option_get')) {
        $brand_name = (string)vogo_brand_option_get('brand_name', '');
    }

    $brand_name = strtolower(trim($brand_name));
    $brand_name = str_replace([' ', '_'], '-', $brand_name);
    $brand_name = preg_replace('/[^a-z0-9.\-]/', '', $brand_name);
    $brand_name = trim((string)$brand_name, '.-');

    if ($brand_name === '') {
        $brand_name = 'vogo.family';
    }

    if (strpos($brand_name, '.') === false) {
        $brand_name .= '.local';
    }

    return 'suport@' . $brand_name;
}

/**
 * Ensure support admin user exists and is mapped in support users table.
 */
function vogo_run_setup_section_users(&$log_lines) {
    global $wpdb;

    $support_email = vogo_support_setup_get_support_email();
    $support_password = '12345$7Abb';

    $log_lines[] = 'Running support setup for ' . $support_email . ' ...';

    $user_id = username_exists($support_email);
    if (!$user_id) {
        $email_user = get_user_by('email', $support_email);
        if ($email_user) {
            $user_id = (int)$email_user->ID;
        }
    }

    if (!$user_id) {
        $created_user_id = wp_create_user($support_email, $support_password, $support_email);
        if (is_wp_error($created_user_id)) {
            $log_lines[] = 'Support user create failed: ' . $created_user_id->get_error_message();
            return;
        }
        $user_id = (int)$created_user_id;
        $log_lines[] = 'Support user created: ' . $support_email . ' (ID ' . $user_id . ').';
    } else {
        $log_lines[] = 'Support user already exists: ' . $support_email . ' (ID ' . $user_id . ').';
    }

    $wp_user = get_user_by('id', $user_id);
    if (!$wp_user) {
        $log_lines[] = 'Support user role setup failed: user object missing for ID ' . $user_id . '.';
        return;
    }

    if (!in_array('administrator', (array)$wp_user->roles, true)) {
        $wp_user->add_role('administrator');
        $log_lines[] = 'Administrator role added for support user.';
    } else {
        $log_lines[] = 'Administrator role already present for support user.';
    }

    $support_table = $wpdb->prefix . 'vogo_support_users';
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $support_table));
    if (!$table_exists) {
        $log_lines[] = 'Support users table missing (' . $support_table . '). Mapping skipped.';
        return;
    }

    $support_row = $wpdb->get_row(
        $wpdb->prepare('SELECT user_id FROM `' . $support_table . '` WHERE user_id = %d LIMIT 1', $user_id),
        ARRAY_A
    );

    if ($support_row) {
        $log_lines[] = 'Support user already mapped in ' . $support_table . '.';
        return;
    }

    $nickname = $wp_user->display_name !== '' ? $wp_user->display_name : 'Support';
    $inserted = $wpdb->insert(
        $support_table,
        [
            'user_id' => $user_id,
            'nickname' => mb_substr($nickname, 0, 80),
            'available' => 1,
            'created_by' => get_current_user_id() ?: null,
            'modified_by' => get_current_user_id() ?: null,
        ],
        [
            '%d',
            '%s',
            '%d',
            '%d',
            '%d',
        ]
    );

    if ($inserted === false) {
        $log_lines[] = 'Support user mapping insert failed: ' . $wpdb->last_error;
        return;
    }

    $log_lines[] = 'Support user inserted in ' . $support_table . '.';
}

