<?php
/**
 * VOGO self-diagnostics and auto-repair routines.
 *
 * This file validates the database objects required by the plugin (tables,
 * procedures, triggers, views) and ensures baseline WordPress roles/users
 * exist for initial environments.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/setup_section_users.php';

/**
 * Load and parse the XML database specification used by the diagnostics flow.
 */
function vogo_brand_options_load_db_spec(&$log_lines) {
    $xml_path = plugin_dir_path(__FILE__) . 'rest/vogo.xml';
    if (!file_exists($xml_path)) {
        $log_lines[] = 'ERROR: Missing XML definition at ' . $xml_path;
        return null;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($xml_path);
    if (!$xml) {
        $log_lines[] = 'ERROR: Failed to parse XML definition at ' . $xml_path;
        foreach (libxml_get_errors() as $error) {
            $log_lines[] = trim($error->message);
        }
        libxml_clear_errors();
        return null;
    }

    return $xml;
}

/**
 * Return table names from XML sorted alphabetically (case-insensitive).
 *
 * @return string[]
 */
function vogo_brand_options_get_db_table_names_from_spec() {
    $log_lines = [];
    $xml = vogo_brand_options_load_db_spec($log_lines);
    if (!$xml) {
        return [];
    }

    $table_names = [];
    $tables = $xml->{'vogo-objects-tables'}->table ?? [];
    foreach ($tables as $table) {
        $table_name = trim((string)($table['name'] ?? ''));
        if ($table_name === '') {
            continue;
        }
        $table_names[] = $table_name;
    }

    $table_names = array_values(array_unique($table_names));
    natcasesort($table_names);
    return array_values($table_names);
}

/**
 * Return selectable diagnostics sections (tables + special non-table sections).
 *
 * @return string[]
 */
function vogo_brand_options_get_db_check_section_names_from_spec() {
    $sections = vogo_brand_options_get_db_table_names_from_spec();
    $sections[] = 'Section Server logic';
    $sections[] = 'Section Users & Roles';

    return array_values(array_unique($sections));
}

/**
 * Replace SQL template placeholders with runtime values.
 */
function vogo_brand_options_apply_sql_placeholders($sql, $charset_collate) {
    global $wpdb;
    $sql = str_replace('{prefix}', $wpdb->prefix, $sql);
    $sql = str_replace('{charset_collate}', $charset_collate, $sql);
    return trim($sql);
}

/**
 * Resolve the effective DB object name from XML into a runtime table name.
 *
 * Why this exists:
 * - Some XML entries already include the WP prefix (e.g., wp_vogo_city).
 * - Other entries may be defined as logical names without prefix.
 *
 * This helper avoids generating double-prefixed names such as wp_wp_vogo_city
 * during diagnostics checks.
 */
function vogo_brand_options_resolve_table_name($table_name_from_xml) {
    global $wpdb;

    $table_name_from_xml = trim((string)$table_name_from_xml);
    if ($table_name_from_xml === '') {
        return '';
    }

    if (strpos($table_name_from_xml, $wpdb->prefix) === 0) {
        return $table_name_from_xml;
    }

    return $wpdb->prefix . $table_name_from_xml;
}

/**
 * Extract only the SQL datatype portion from a full column definition.
 */
function vogo_brand_options_extract_column_type($definition) {
    $definition = trim(preg_replace('/\s+/', ' ', (string)$definition));
    if ($definition === '') {
        return '';
    }
    if (preg_match('/^(.+?)(\s+not null|\s+default|\s+auto_increment|\s+comment|\s+collate|\s+character set|\s+on update|$)/i', $definition, $matches)) {
        return strtolower(trim($matches[1]));
    }
    return strtolower($definition);
}

/**
 * Compare expected/actual column metadata and decide if an update is required.
 */
function vogo_brand_options_column_requires_update($expected_definition, $actual_column) {
    $expected_type = vogo_brand_options_extract_column_type($expected_definition);
    $actual_type = strtolower(trim((string)($actual_column['COLUMN_TYPE'] ?? '')));
    if ($expected_type !== '' && $actual_type !== $expected_type) {
        return true;
    }

    $expected_not_null = stripos($expected_definition, 'not null') !== false;
    $actual_not_null = strtoupper((string)($actual_column['IS_NULLABLE'] ?? '')) === 'NO';
    if ($expected_not_null !== $actual_not_null) {
        return true;
    }

    $expected_auto_increment = stripos($expected_definition, 'auto_increment') !== false;
    $actual_auto_increment = stripos((string)($actual_column['EXTRA'] ?? ''), 'auto_increment') !== false;
    if ($expected_auto_increment !== $actual_auto_increment) {
        return true;
    }

    return false;
}

/**
 * Validate brand activation code via the same REST activation flow used by mobile apps.
 */
function vogo_brand_options_check_activation_code_for_mobile(string $activation_code): array {
    if ($activation_code === '') {
        return [
            'status' => 'error',
            'message' => 'Brand activation code is empty in Company data.',
        ];
    }

    $endpoint_base = trim((string) vogo_brand_option_get('brand_prod_endpoint_url', home_url('/wp-json/vogo/v1')));
    if ($endpoint_base === '') {
        return [
            'status' => 'error',
            'message' => 'Brand endpoint URL is missing (brand_prod_endpoint_url).',
        ];
    }

    $endpoint_url = rtrim($endpoint_base, '/') . '/activateAndGetUpdates';
    $current_version = trim((string) vogo_brand_option_get('brand_version', '0.0.0'));
    if ($current_version === '') {
        $current_version = '0.0.0';
    }

    $response = wp_remote_post($endpoint_url, [
        'timeout' => 15,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode([
            'codActivare' => $activation_code,
            'currentVersion' => $current_version,
        ]),
    ]);

    if (is_wp_error($response)) {
        return [
            'status' => 'error',
            'message' => 'Activation REST request failed: ' . $response->get_error_message(),
        ];
    }

    $http_status = (int) wp_remote_retrieve_response_code($response);
    $raw_body = (string) wp_remote_retrieve_body($response);
    $decoded = json_decode($raw_body, true);

    if (!is_array($decoded)) {
        return [
            'status' => 'error',
            'message' => 'Activation REST response is not valid JSON (HTTP ' . $http_status . ').',
        ];
    }

    $status = strtolower((string) ($decoded['status'] ?? ''));
    $message = trim((string) ($decoded['message'] ?? ''));
    if ($message === '') {
        $message = 'Activation REST response received (HTTP ' . $http_status . ').';
    }

    if ($status === 'error' || $http_status >= 400) {
        return [
            'status' => 'error',
            'message' => $message,
        ];
    }

    if ($status === 'info') {
        return [
            'status' => 'warning',
            'message' => $message,
        ];
    }

    return [
        'status' => 'ok',
        'message' => $message,
    ];
}

/**
 * Run DB diagnostics and auto-repair based on XML specification.
 *
 * Scope:
 * - checks/creates tables and missing columns
 * - checks/creates stored procedures
 * - checks/creates triggers
 * - checks/creates views
 *
 * Output is appended to the diagnostics log textarea via option: vogo_plugin_check_log.
 */
function vogo_brand_options_plugin_check() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }

    check_admin_referer('vogo_plugin_check');

    global $wpdb;
    $log_lines = [];
    $log_lines[] = 'Starting Vogo plugin database structure check...';

    $xml = vogo_brand_options_load_db_spec($log_lines);
    if (!$xml) {
        vogo_brand_option_set('vogo_plugin_check_log', implode("\n", $log_lines));
        wp_safe_redirect(add_query_arg(['page' => 'vogo-brand-options', 'vogo-plugin-check' => 'error'], admin_url('admin.php')));
        exit;
    }

    $charset_collate = $wpdb->get_charset_collate();
    $check_mode = sanitize_text_field(wp_unslash($_POST['vogo_plugin_check_mode'] ?? 'full'));
    if (!in_array($check_mode, ['full', 'table', 'activation'], true)) {
        $check_mode = 'full';
    }

    $selected_table = trim(sanitize_text_field(wp_unslash($_POST['vogo_plugin_check_table'] ?? '')));
    if ($check_mode === 'table' && $selected_table === '') {
        $log_lines[] = 'ERROR: Choose section mode selected but no table was provided.';
        vogo_brand_option_set('vogo_plugin_check_log', implode("\n", $log_lines));
        wp_safe_redirect(add_query_arg(['page' => 'vogo-brand-options', 'vogo-plugin-check' => 'error'], admin_url('admin.php')));
        exit;
    }

    $selected_server_logic_section = strcasecmp($selected_table, 'Section Server logic') === 0;
    $selected_users_roles_section = strcasecmp($selected_table, 'Section Users & Roles') === 0;
    $selected_special_section = $selected_server_logic_section || $selected_users_roles_section;

    $run_tables_section = $check_mode === 'full' || ($check_mode === 'table' && !$selected_special_section);
    $run_server_logic_section = $check_mode === 'full' || ($check_mode === 'table' && $selected_server_logic_section);
    $run_users_roles_section = $check_mode === 'full' || ($check_mode === 'table' && $selected_users_roles_section);
    $run_activation_section = $check_mode === 'activation';

    if ($check_mode === 'full') {
        $log_lines[] = 'Run mode: FULL diagnostics for all sections defined in vogo.xml.';
    } elseif ($selected_special_section) {
        $log_lines[] = 'Run mode: SECTION diagnostics only for `' . $selected_table . '`.';
    } elseif ($check_mode === 'activation') {
        $log_lines[] = 'Run mode: ACTIVATION diagnostics only for Brand activation code.';
    } else {
        $log_lines[] = 'Run mode: TABLE diagnostics only for table `' . $selected_table . '`.';
    }

    // Section 1: Table structure checks and incremental repair.
    $matched_selected_table = false;
    if ($run_tables_section) {
        $log_lines[] = 'Checking database tables (vogo-objects-tables) ....';
        $tables = $xml->{'vogo-objects-tables'}->table ?? [];
        foreach ($tables as $table) {
        // Read logical table name from XML and resolve to a concrete DB table name.
        $base_name = (string)($table['name'] ?? '');
        if ($base_name === '') {
            continue;
        }
        if ($check_mode === 'table' && strcasecmp($base_name, $selected_table) !== 0) {
            continue;
        }

        if ($check_mode === 'table') {
            $matched_selected_table = true;
        }

            $table_name = vogo_brand_options_resolve_table_name($base_name);
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
            if ($exists) {
                $log_lines[] = 'Checking - ' . $base_name . ' > result: OK';

            $columns = $table->columns->column ?? [];
            if (!empty($columns)) {
                $log_lines[] = 'Checking - ' . $base_name . ' > columns verification ...';
                $existing_columns = $wpdb->get_results(
                    $wpdb->prepare(
                        'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                        $table_name
                    ),
                    ARRAY_A
                );
                $columns_map = [];
                foreach ($existing_columns as $column) {
                    $columns_map[strtolower($column['COLUMN_NAME'])] = $column;
                }

                foreach ($columns as $column) {
                    $column_name = (string)($column['name'] ?? '');
                    $column_definition = (string)($column['type'] ?? '');
                    if ($column_name === '' || $column_definition === '') {
                        continue;
                    }

                    $column_key = strtolower($column_name);
                    if (!isset($columns_map[$column_key])) {
                        $log_lines[] = 'Missing column ' . $column_name . ' on ' . $base_name . '. Adding ...';
                        $alter_sql = 'ALTER TABLE `' . $table_name . '` ADD COLUMN `' . $column_name . '` ' . $column_definition;
                        $log_lines[] = 'Executing SQL: ' . $alter_sql;
                        $result = $wpdb->query($alter_sql);
                        if ($result === false) {
                            $log_lines[] = 'Adding column ' . $column_name . ' ... error: ' . $wpdb->last_error;
                        } else {
                            $log_lines[] = 'Column ' . $column_name . ' added.';
                        }
                        continue;
                    }

                    $expected_type = vogo_brand_options_extract_column_type($column_definition);
                    $actual_type = strtolower(trim((string)($columns_map[$column_key]['COLUMN_TYPE'] ?? '')));
                    if ($expected_type !== '' && $actual_type !== '' && $expected_type !== $actual_type) {
                        $log_lines[] = 'Column ' . $column_name . ' on ' . $base_name . ' New datatype=' . $expected_type . ' existin=' . $actual_type . '; exists';
                    }
                }
            }
                continue;
            }

            $log_lines[] = 'Checking - ' . $base_name . ' > result: Object not exists. Creating object ...';
            $create_sql = trim((string)($table->{'create-sql'} ?? ''));
            if ($create_sql === '') {
                $log_lines[] = 'Creating object ... error: missing SQL definition.';
                continue;
            }

            $create_sql = vogo_brand_options_apply_sql_placeholders($create_sql, $charset_collate);
            $result = $wpdb->query($create_sql);
            if ($result === false) {
                $log_lines[] = 'Creating object ... error: ' . $wpdb->last_error;
            } else {
                $log_lines[] = 'Creating object ... success';
            }
        }
    } elseif ($check_mode === 'table') {
        $log_lines[] = 'Skipping table checks. Selected section is `' . $selected_table . '`.';
    }

    if ($check_mode === 'table' && !$selected_special_section && !$matched_selected_table) {
        $log_lines[] = 'ERROR: Selected table `' . $selected_table . '` was not found in vogo.xml.';
        vogo_brand_option_set('vogo_plugin_check_log', implode("\n", $log_lines));
        wp_safe_redirect(add_query_arg(['page' => 'vogo-brand-options', 'vogo-plugin-check' => 'error'], admin_url('admin.php')));
        exit;
    }

    // Section 2: Stored procedure checks and creation when missing.
    if ($run_server_logic_section) {
        $log_lines[] = 'Checking stored procedures (vogo-procedures) ....';
        $procedures = $xml->{'vogo-procedures'}->procedure ?? [];
        if (empty($procedures)) {
            $log_lines[] = 'No stored procedures defined.';
        }
        foreach ($procedures as $procedure) {
        $name = (string)($procedure['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT ROUTINE_NAME FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = 'PROCEDURE' AND ROUTINE_NAME = %s",
            $name
        ));
        if ($exists) {
            $log_lines[] = 'Checking - ' . $name . ' > result: OK';
            continue;
        }

        $log_lines[] = 'Checking - ' . $name . ' > result: Object not exists. Creating object ...';
        $create_sql = trim((string)($procedure->{'create-sql'} ?? ''));
        if ($create_sql === '') {
            $log_lines[] = 'Creating object ... error: missing SQL definition.';
            continue;
        }
        $create_sql = vogo_brand_options_apply_sql_placeholders($create_sql, $charset_collate);
        $result = $wpdb->query($create_sql);
        if ($result === false) {
            $log_lines[] = 'Creating object ... error: ' . $wpdb->last_error;
        } else {
            $log_lines[] = 'Creating object ... success';
        }
        }

        // Section 3: Trigger checks and creation when missing.
        $log_lines[] = 'Checking triggers (vogo-triggers) ....';
        $triggers = $xml->{'vogo-triggers'}->trigger ?? [];
        if (empty($triggers)) {
            $log_lines[] = 'No triggers defined.';
        }
        foreach ($triggers as $trigger) {
        $name = (string)($trigger['name'] ?? '');
        $name = vogo_brand_options_apply_sql_placeholders($name, $charset_collate);
        if ($name === '') {
            continue;
        }
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT TRIGGER_NAME FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = %s",
            $name
        ));
        if ($exists) {
            $log_lines[] = 'Checking - ' . $name . ' > result: OK';
            continue;
        }

        $log_lines[] = 'Checking - ' . $name . ' > result: Object not exists. Creating object ...';
        $create_sql = trim((string)($trigger->{'create-sql'} ?? ''));
        if ($create_sql === '') {
            $log_lines[] = 'Creating object ... error: missing SQL definition.';
            continue;
        }
        $create_sql = vogo_brand_options_apply_sql_placeholders($create_sql, $charset_collate);
        $result = $wpdb->query($create_sql);
        if ($result === false) {
            $log_lines[] = 'Creating object ... error: ' . $wpdb->last_error;
        } else {
            $log_lines[] = 'Creating object ... success';
        }
        }

        // Section 4: View checks and creation when missing.
        $log_lines[] = 'Checking database views (vogo-view) ....';
        $views = $xml->{'vogo-view'}->view ?? [];
        if (empty($views)) {
            $log_lines[] = 'No views defined.';
        }
        foreach ($views as $view) {
        $name = (string)($view['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
            $name
        ));
        if ($exists) {
            $log_lines[] = 'Checking - ' . $name . ' > result: OK';
            continue;
        }

        $log_lines[] = 'Checking - ' . $name . ' > result: Object not exists. Creating object ...';
        $create_sql = trim((string)($view->{'create-sql'} ?? ''));
        if ($create_sql === '') {
            $log_lines[] = 'Creating object ... error: missing SQL definition.';
            continue;
        }
        $create_sql = vogo_brand_options_apply_sql_placeholders($create_sql, $charset_collate);
        $result = $wpdb->query($create_sql);
        if ($result === false) {
            $log_lines[] = 'Creating object ... error: ' . $wpdb->last_error;
        } else {
            $log_lines[] = 'Creating object ... success';
        }
        }
    } elseif ($check_mode === 'table') {
        $log_lines[] = 'Skipping server logic checks (stored procedures, triggers, views).';
    }

    // Section 5: WordPress baseline bootstrap for required roles and users.
    if ($run_users_roles_section) {
        $log_lines[] = 'Checking WordPress setup roles and users ....';

    $required_roles = [
        'customer',
        'mobile_general',
        'client',
        'ai_croq',
        'ai_basic',
        'ai_rasa',
        'ai_vosk',
    ];

        foreach ($required_roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $log_lines[] = 'Checking role ' . $role_name . ' ... found.';
            continue;
        }

        $created_role = add_role($role_name, ucwords(str_replace('_', ' ', $role_name)), ['read' => true]);
        if ($created_role) {
            $log_lines[] = 'Checking role ' . $role_name . ' ... not found - created.';
        } else {
            $log_lines[] = 'Checking role ' . $role_name . ' ... not found - create failed.';
        }
        }

    $setup_users = [
        [
            'username' => 'app_mobile_general@vogo.family',
            'password' => 'Abc123$',
            'roles' => ['mobile_general'],
        ],
        [
            'username' => 'peter@example.com',
            'password' => '12345',
            'roles' => [
                'customer',
                'mobile_general',
                'client',
                'ai_croq',
                'ai_basic',
                'ai_rasa',
                'ai_vosk',
            ],
        ],
    ];

        foreach ($setup_users as $setup_user) {
        $username = $setup_user['username'];
        $password = $setup_user['password'];
        $user_roles = $setup_user['roles'];

        $user_id = username_exists($username);
        if (!$user_id) {
            $existing_email_user = get_user_by('email', $username);
            if ($existing_email_user) {
                $user_id = (int)$existing_email_user->ID;
            }
        }

        if ($user_id) {
            $log_lines[] = 'Checking user ' . $username . ' ... found.';
        } else {
            $new_user_id = wp_create_user($username, $password, $username);
            if (is_wp_error($new_user_id)) {
                $log_lines[] = 'Checking user ' . $username . ' ... not found - create failed: ' . $new_user_id->get_error_message();
                continue;
            }
            $user_id = (int)$new_user_id;
            $log_lines[] = 'Checking user ' . $username . ' ... not found - created.';
        }

        $wp_user = get_user_by('id', $user_id);
        if (!$wp_user) {
            $log_lines[] = 'Assigning roles for user ' . $username . ' ... failed: user object missing.';
            continue;
        }

        foreach ($user_roles as $role_name) {
            if (in_array($role_name, (array)$wp_user->roles, true)) {
                $log_lines[] = 'Checking user ' . $username . ' role ' . $role_name . ' ... found.';
                continue;
            }

            $wp_user->add_role($role_name);
            $log_lines[] = 'Checking user ' . $username . ' role ' . $role_name . ' ... not found - created.';
        }
        }

        vogo_run_setup_section_users($log_lines);
    } elseif ($check_mode === 'table') {
        $log_lines[] = 'Skipping users & roles checks.';
    }

    if ($run_activation_section) {
        $stored_activation_code = trim((string) vogo_brand_option_get('brand_activation_code', ''));
        $log_lines[] = 'Checking Brand activation code used by mobile apps ...';
        $activation_status = vogo_brand_options_check_activation_code_for_mobile($stored_activation_code);
        if ($activation_status['status'] === 'ok') {
            $log_lines[] = 'Checking Brand activation code ... result: OK. ' . $activation_status['message'];
        } elseif ($activation_status['status'] === 'warning') {
            $log_lines[] = 'Checking Brand activation code ... result: WARNING. ' . $activation_status['message'];
        } else {
            $log_lines[] = 'Checking Brand activation code ... result: ERROR. ' . $activation_status['message'];
        }
    }

    if ($check_mode === 'table') {
        $log_lines[] = 'Selected section diagnostics finished.';
    }

    vogo_brand_option_set('vogo_plugin_check_log', implode("\n", $log_lines));
    wp_safe_redirect(add_query_arg(['page' => 'vogo-brand-options', 'vogo-plugin-check' => 'done'], admin_url('admin.php')));
    exit;
}

add_action('admin_post_vogo_plugin_check', 'vogo_brand_options_plugin_check');
