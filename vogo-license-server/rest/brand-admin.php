<?php
/**
 * Brand Admin API module.
 *
 * Role of this file:
 * - register brand-admin REST endpoints;
 * - validate license/context payload sent by mobile clients;
 * - return grouped brand configuration data used by the app details screens.
 */

add_action('rest_api_init', function () {

    register_rest_route('vogo/v1', '/brand-admin/getBrandAdminOptions', [
        'methods' => 'POST',
        'callback' => 'vogo_get_brand_admin_options',
        'permission_callback' => 'vogo_permission_check'
    ]);

    register_rest_route('vogo/v1', '/brand-admin/getBrandData', [
        'methods' => 'POST',
        'callback' => 'vogo_get_brand_data',
        'permission_callback' => 'vogo_permission_check'
    ]);

});

function vogo_brand_options_grouped_by_category(array $results, array $definitions) {
    // Build a fast lookup map for saved option rows.
    $by_key = [];
    foreach ($results as $row) {
        $by_key[$row['option_key']] = $row;
    }
    // Compose grouped output by iterating the central option definitions list.
    $grouped = [];
    foreach ($definitions as $key => $definition) {
        $row = $by_key[$key] ?? [];
        $row_category = isset($row['option_category']) ? trim((string) $row['option_category']) : '';
        $category = $row_category !== '' ? $row_category : ($definition['category'] ?? 'Other');
        $row_description = isset($row['option_description']) ? trim((string) $row['option_description']) : '';
        $description = $row_description !== '' ? $row_description : ($definition['description'] ?? '');
        // Serve definition-level defaults when a value was not saved yet.
        $default_value = isset($definition['default']) ? (string) $definition['default'] : '';
        $value = array_key_exists('option_value', $row) ? (string) $row['option_value'] : $default_value;
        if (!isset($grouped[$category])) {
            $grouped[$category] = [];
        }
        $grouped[$category][] = [
            'element' => $key,
            'value' => $value,
            'description' => $description,
            'category' => $category,
            'label' => $definition['label'] ?? $key,
        ];
    }

    return $grouped;
}

function vogo_brand_options_fetch_grouped_data($module) {
    global $wpdb;

    $table = vogo_brand_options_ensure_table();
    $query = "SELECT option_key, option_value, option_description, option_category FROM {$table}";
    vogo_error_log3('[brand-admin][brandOptionsFetch] SQL=' . $query, $module);

    $results = $wpdb->get_results($query, ARRAY_A);
    if ($results === null) {
        vogo_error_log3('[brand-admin][brandOptionsFetch] SQL error=' . $wpdb->last_error, $module);
        return [
            'success' => false,
            'error' => 'Database error.',
        ];
    }

    vogo_error_log3('[brand-admin][brandOptionsFetch] Returned rows=' . count($results), $module);
    $definitions = vogo_brand_options_get_definitions();

    return [
        'success' => true,
        'data' => vogo_brand_options_grouped_by_category($results, $definitions),
    ];
}

function vogo_brand_version_is_valid($version) {
    if (!is_string($version) || $version === '') {
        return false;
    }

    if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
        return false;
    }

    return version_compare($version, '1.1.1', '>=') && version_compare($version, '9.9.9', '<=');
}

function vogo_brand_normalize_url($url) {
    if (!is_string($url) || $url === '') {
        return '';
    }

    $normalized = preg_replace('#^https?://#', '', trim($url));
    return rtrim($normalized, '/');
}

function vogo_get_brand_admin_options(WP_REST_Request $request) {
    global $wpdb;
    $MODULE_PHP = 'brand-admin.php';
    $module = $MODULE_PHP . '.vogo_get_brand_admin_options';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $current_db = DB_NAME;
    vogo_error_log3('[brand-admin][getBrandAdminOptions] Start | DB:' . $current_db . ' | IP:' . $ip, $module);

    // Build the table name using the WordPress prefix to support multisite setups.
    $options = vogo_brand_options_fetch_grouped_data($module);
    if (!$options['success']) {
        return new WP_REST_Response([
            'success' => false,
            'error' => $options['error'],
            'module_bke' => $module
        ], 500);
    }

    return new WP_REST_Response([
        'success' => true,
        'data' => $options['data'],
        'module_bke' => $module
    ], 200);
}

function vogo_get_brand_data(WP_REST_Request $request) {
    global $wpdb;
    $MODULE_PHP = 'brand-admin.php';
    $module = $MODULE_PHP . '.vogo_get_brand_data';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $current_db = DB_NAME;
    vogo_error_log3('[brand-admin][getBrandData] Start | DB:' . $current_db . ' | IP:' . $ip, $module);

    $params = $request->get_json_params();
    $activation_code = sanitize_text_field($params['activationcode'] ?? $params['activation_code'] ?? '');
    $fiscal_code = sanitize_text_field($params['fiscalcode'] ?? $params['fiscal_code'] ?? '');
    $requested_url = sanitize_text_field($params['url'] ?? '');
    $existing_version = sanitize_text_field($params['existingversion'] ?? $params['existing_version'] ?? '');

    if ($activation_code === '' || $fiscal_code === '' || $requested_url === '' || $existing_version === '') {
        vogo_error_log3('[brand-admin][getBrandData] Missing required params.', $module);
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Missing required parameters.',
            'module_bke' => $module
        ], 400);
    }

    if (!vogo_brand_version_is_valid($existing_version)) {
        vogo_error_log3('[brand-admin][getBrandData] Invalid existingversion=' . $existing_version, $module);
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Invalid existingversion. Must be between 1.1.1 and 9.9.9.',
            'module_bke' => $module
        ], 400);
    }

    $stored_license_code = vogo_brand_option_get('company_license_code', '');
    $stored_activation_code = vogo_brand_option_get('brand_activation_code', '');
    $stored_fiscal_code = vogo_brand_option_get('company_fiscal_code', '');
    $current_site_url = vogo_brand_normalize_url(home_url());
    $requested_site_url = vogo_brand_normalize_url($requested_url);

    vogo_error_log3('[brand-admin][getBrandData] Brand options check url=' . $requested_site_url, $module);

    if (
        $stored_license_code === '' ||
        $stored_activation_code === '' ||
        $stored_fiscal_code === '' ||
        $current_site_url === '' ||
        $requested_site_url === ''
    ) {
        vogo_error_log3('[brand-admin][getBrandData] Missing stored brand option data.', $module);
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Brand option data not configured.',
            'module_bke' => $module
        ], 404);
    }

    if (
        $activation_code !== $stored_activation_code ||
        $fiscal_code !== $stored_fiscal_code ||
        $requested_site_url !== $current_site_url
    ) {
        vogo_error_log3('[brand-admin][getBrandData] Brand option data mismatch.', $module);
        return new WP_REST_Response([
            'success' => false,
            'error' => 'License data not found.',
            'stored_activation_code' => $stored_activation_code,
            'stored_fiscal_code' => $stored_fiscal_code,
            'stored_site_url' => $current_site_url,
            'module_bke' => $module
        ], 404);
    }

    $latest_version = vogo_brand_option_get('brand_version', '');
    if ($latest_version === '') {
        $latest_version = '0.0.0';
    }

    /**
     * Always load grouped brand options once validation passed.
     *
     * Why: the mobile details screen needs full brand payload every time it
     * opens, not only when a version update is available.
     */
    $options = vogo_brand_options_fetch_grouped_data($module);
    if (!$options['success']) {
        return new WP_REST_Response([
            'success' => false,
            'error' => $options['error'],
            'module_bke' => $module
        ], 500);
    }

    /**
     * Section: update available flow.
     *
     * Keep status=new_version and include the grouped data payload.
     */
    if (version_compare($existing_version, $latest_version, '<')) {
        return new WP_REST_Response([
            'success' => true,
            'status' => 'new_version',
            'brand_version' => $latest_version,
            'license_code' => $stored_license_code,
            'data' => $options['data'],
            'module_bke' => $module
        ], 200);
    }

    /**
     * Section: no update flow.
     *
     * Return the same grouped data payload so the client can render brand
     * details consistently after back navigation or reopening the screen.
     */
    return new WP_REST_Response([
        'success' => true,
        'status' => 'no_new_version',
        'brand_version' => $latest_version,
        'license_code' => $stored_license_code,
        'data' => $options['data'],
        'module_bke' => $module
    ], 200);
}
