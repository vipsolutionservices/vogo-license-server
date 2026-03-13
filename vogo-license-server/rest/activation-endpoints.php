<?php
/**
 * Activation and update orchestration endpoint.
 *
 * File role:
 * - exposes the public activation/update REST endpoint used by mobile apps;
 * - validates activation code and expiry against encrypted licensing storage;
 * - requests brand payload from the paired brand server;
 * - returns a normalized response consumed by mobile update and branding flows.
 *
 * This file registers /vogo/v1/activateAndGetUpdates and coordinates:
 * - activation code validation;
 * - expiry checks;
 * - version comparison;
 * - brand data retrieval for clients that require updates.
 *
 * Note: brand data retrieval now supports a safe fallback endpoint when the
 * customer domain does not expose the expected JSON API response.
 *
 */

/** Register the public activation/update REST route. */
add_action('rest_api_init', function () {
    register_rest_route('vogo/v1', '/activateAndGetUpdates', [
        'methods' => 'POST',
        'callback' => 'vogo_activate_and_get_updates',
        'permission_callback' => 'vogo_permission_check',
    ]);
});

/**
 * Main activation/update endpoint callback.
 *
 * Validates the activation record and, if an update exists, requests the
 * remote brand payload and returns it to the caller.
 */
function vogo_activate_and_get_updates(WP_REST_Request $request) {
    global $wpdb;

    $MODULE_PHP = 'activation-endpoints.php';
    $module = $MODULE_PHP . '.vogo_activate_and_get_updates';

    $code = sanitize_text_field((string) $request->get_param('codActivare'));
    $current_version = sanitize_text_field((string) $request->get_param('currentVersion'));
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $current_db = DB_NAME;

    vogo_activate_and_get_updates_log_call($request, $module, [
        'cod_activare' => $code,
        'current_version' => $current_version,
    ]);

    vogo_error_log3("[activateAndGetUpdates] Start | DB:{$current_db} | IP:{$ip} | codActivare={$code}", $module);

    if ($code === '') {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Missing codActivare.',
            'module_bke' => $module,
        ], 400);
    }

    $record = vogo_activation_fetch_record($wpdb, $code, $module);
    if ($record === null) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Activation code not found.',
            'module_bke' => $module,
        ], 404);
    }

    $expiry_status = vogo_activation_check_expiry($record['valid_until'] ?? null, $module);
    if ($expiry_status['status'] !== 'ok') {
        return new WP_REST_Response([
            'status' => $expiry_status['status'],
            'message' => $expiry_status['message'],
            'expired_days' => $expiry_status['expired_days'],
            'module_bke' => $module,
        ], $expiry_status['http_status']);
    }

    /**
     * Load brand payload for both flows (update and no-update).
     *
     * The mobile brand details screen expects this payload on every valid call,
     * including when versions are already aligned.
     */
    $brand_result = vogo_fetch_brand_data($record, $code, $current_version, $module);
    $license_check = vogo_validate_brand_license_response($brand_result['brand_data'] ?? null, $record, $module);
    if ($license_check['status'] !== 'ok') {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => $license_check['message'],
            'module_bke' => $module,
        ], 403);
    }

    $brand_data = $brand_result['brand_data'] ?? null;
    if (
        is_array($brand_data) &&
        array_key_exists('success', $brand_data) &&
        $brand_data['success'] === false
    ) {
        $brand_error_message = isset($brand_data['error'])
            ? (string) $brand_data['error']
            : 'Brand data request failed.';
        $http_status = strtolower($brand_error_message) === 'license data not found.' ? 404 : 400;

        return new WP_REST_Response([
            'success' => false,
            'status' => 'error',
            'message' => $brand_error_message,
            'module_bke' => $module,
        ], $http_status);
    }

    $brand_status = is_array($brand_data) ? ($brand_data['status'] ?? null) : null;
    $clean_brand_data = $brand_data;
    if (is_array($brand_data) && array_key_exists('data', $brand_data)) {
        $clean_brand_data = $brand_data['data'];
    }
    if (is_array($clean_brand_data) && array_key_exists('Company data', $clean_brand_data)) {
        unset($clean_brand_data['Company data']);
    }

    /**
     * Version source of truth:
     *
     * latest_version MUST come from the paired brand endpoint response
     * (/brand-admin/getBrandData), not from local brand options.
     */
    $latest_version = '';
    if (is_array($brand_data) && isset($brand_data['brand_version'])) {
        $latest_version = sanitize_text_field((string) $brand_data['brand_version']);
    }

    /**
     * Update flag is derived only from the remote brand version returned above.
     */
    $update_available = $latest_version !== '' && $current_version !== ''
        ? version_compare($latest_version, $current_version, '>')
        : false;

    /** Update flow response with grouped brand data. */
    if ($update_available) {
        return new WP_REST_Response([
            'status' => $brand_status ?: 'new_version',
            'message' => 'Update available.',
            'update_available' => true,
            'current_version' => $current_version,
            'latest_version' => $latest_version,
            'brand_data' => $clean_brand_data,
            'body_request_to_brand' => $brand_result['body_request_to_brand'],
            'module_bke' => $module,
        ], 200);
    }

    /** No-update response still returns brand data to keep details screen populated. */
    return new WP_REST_Response([
        'status' => $brand_status ?: 'ok',
        'message' => 'No new version.',
        'update_available' => false,
        'current_version' => $current_version,
        'latest_version' => $latest_version,
        'brand_data' => $clean_brand_data,
        'body_request_to_brand' => $brand_result['body_request_to_brand'],
        'module_bke' => $module,
    ], 200);
}

/** Persist an audit entry for every activation/update request. */
function vogo_activate_and_get_updates_log_call(WP_REST_Request $request, string $module, array $context = []): void {
    global $wpdb;

    $table = $wpdb->prefix . 'activate_and_get_updates_calls';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $forwarded_for = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $server_name = $_SERVER['SERVER_NAME'] ?? '';
    $server_addr = $_SERVER['SERVER_ADDR'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';

    $data = [
        'created_at' => current_time('mysql'),
        'ip_address' => $ip,
        'forwarded_for' => $forwarded_for,
        'user_agent' => $user_agent,
        'request_method' => $request->get_method(),
        'request_route' => $request->get_route(),
        'request_uri' => $request_uri,
        'referer' => $referer,
        'server_name' => $server_name,
        'server_addr' => $server_addr,
        'host' => $host,
        'db_name' => defined('DB_NAME') ? DB_NAME : '',
        'cod_activare' => $context['cod_activare'] ?? '',
        'current_version' => $context['current_version'] ?? '',
        'query_params' => wp_json_encode($request->get_query_params(), JSON_UNESCAPED_UNICODE),
        'body_params' => wp_json_encode($request->get_body_params(), JSON_UNESCAPED_UNICODE),
        'json_params' => wp_json_encode($request->get_json_params(), JSON_UNESCAPED_UNICODE),
        'raw_body' => $request->get_body(),
        'headers' => wp_json_encode($request->get_headers(), JSON_UNESCAPED_UNICODE),
    ];

    $formats = [
        '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
        '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
    ];

    $inserted = $wpdb->insert($table, $data, $formats);
    if ($inserted === false) {
        vogo_error_log3("[activateAndGetUpdates] Audit insert error={$wpdb->last_error}", $module);
    }
}

/** Fetch a single activation record from the encrypted storage table. */
function vogo_activation_fetch_record($wpdb, string $code, string $module) {
    $table = $wpdb->prefix . 'cvogo_encrypted_data';
    vogo_error_log3("[activateAndGetUpdates] Lookup table={$table} code={$code}", $module);

    $query = $wpdb->prepare(
        "SELECT col1 AS client_key, col2 AS website_url, col3 AS valid_until, col4 AS account_type, col5 AS activation_pin, col6 AS fiscal_code
         FROM {$table}
         WHERE col5 = %s
         LIMIT 1",
        $code
    );

    $result = $wpdb->get_row($query, ARRAY_A);

    if ($result === null && $wpdb->last_error) {
        vogo_error_log3("[activateAndGetUpdates] SQL error={$wpdb->last_error}", $module);
        return null;
    }

    return $result ?: null;
}

/** Validate activation expiry and convert it to API-friendly status metadata. */
function vogo_activation_check_expiry($valid_until, string $module): array {
    if (empty($valid_until)) {
        return [
            'status' => 'ok',
            'message' => 'Activation valid.',
            'expired_days' => 0,
            'http_status' => 200,
        ];
    }

    $expiry_date = date_create($valid_until);
    if (!$expiry_date) {
        vogo_error_log3("[activateAndGetUpdates] Invalid expiry date={$valid_until}", $module);
        return [
            'status' => 'ok',
            'message' => 'Activation valid.',
            'expired_days' => 0,
            'http_status' => 200,
        ];
    }

    $now = new DateTimeImmutable('now', wp_timezone());
    $expiry = DateTimeImmutable::createFromMutable($expiry_date)->setTimezone(wp_timezone());
    if ($expiry >= $now) {
        return [
            'status' => 'ok',
            'message' => 'Activation valid.',
            'expired_days' => 0,
            'http_status' => 200,
        ];
    }

    $diff_days = (int) $now->diff($expiry)->format('%a');
    if ($diff_days <= 10) {
        return [
            'status' => 'info',
            'message' => 'Expired less than or equal to 10 days.',
            'expired_days' => $diff_days,
            'http_status' => 200,
        ];
    }

    return [
        'status' => 'error',
        'message' => 'Expired more than 10 days.',
        'expired_days' => $diff_days,
        'http_status' => 403,
    ];
}

/**
 * Build candidate brand-data endpoints.
 *
 * Primary endpoint uses the customer website from the activation record.
 * Fallback endpoint uses the current site, which hosts the brand admin API.
 */
function vogo_build_brand_data_candidate_urls(array $record): array {
    $candidate_urls = [];
    $website_url = strtolower(trim((string) ($record['website_url'] ?? '')));

    if ($website_url !== '') {
        $normalized_url = preg_replace('#^https?://#', '', $website_url);
        $candidate_urls[] = 'https://' . trailingslashit($normalized_url) . 'wp-json/vogo/v1/brand-admin/getBrandData';
    }

    $local_brand_url = site_url('/wp-json/vogo/v1/brand-admin/getBrandData');
    if (!in_array($local_brand_url, $candidate_urls, true)) {
        $candidate_urls[] = $local_brand_url;
    }

    return $candidate_urls;
}

/**
 * Fetch brand data with endpoint fallback.
 *
 * If the customer domain returns non-JSON (for example a parking HTML page),
 * the function automatically retries against the local brand-admin endpoint.
 */
function vogo_fetch_brand_data(array $record, string $code, string $current_version, string $module): array {
    $candidate_urls = vogo_build_brand_data_candidate_urls($record);
    $candidate_urls = apply_filters('vogo_brand_data_urls', $candidate_urls, $record);

    /** Backward compatibility: preserve existing single-URL override filter. */
    $legacy_brand_url = apply_filters('vogo_brand_data_url', '', $record);
    if (is_string($legacy_brand_url) && $legacy_brand_url !== '') {
        $candidate_urls = [$legacy_brand_url];
    }

    $payload = [
        'licensecode' => $record['client_key'] ?? '',
        'activationcode' => $record['activation_pin'] ?? $code,
        'url' => $record['website_url'] ?? '',
        'fiscalcode' => $record['fiscal_code'] ?? '',
        'existingversion' => $current_version,
    ];

    $result = [
        'body_request_to_brand' => $payload,
        'full_response_from_brand' => null,
        'brand_data' => null,
    ];

    /** Iterate candidate endpoints until one returns valid JSON. */
    foreach ($candidate_urls as $brand_url) {
        $brand_url = is_string($brand_url) ? trim($brand_url) : '';
        if ($brand_url === '') {
            continue;
        }

        vogo_error_log3('[activateAndGetUpdates] Fetching brand data url=' . $brand_url, $module);

        $response = wp_remote_post($brand_url, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            vogo_error_log3('[activateAndGetUpdates] Brand data request error=' . $response->get_error_message(), $module);
            $result['full_response_from_brand'] = [
                'error' => $response->get_error_message(),
                'invalid_url' => $brand_url,
            ];
            continue;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        $result['full_response_from_brand'] = [
            'status_code' => wp_remote_retrieve_response_code($response),
            'headers' => wp_remote_retrieve_headers($response),
            'body' => $body,
            'requested_url' => $brand_url,
        ];

        if (json_last_error() !== JSON_ERROR_NONE) {
            vogo_error_log3('[activateAndGetUpdates] Brand data JSON error=' . json_last_error_msg() . ' url=' . $brand_url, $module);
            continue;
        }

        $result['brand_data'] = $decoded;
        return $result;
    }

    /** Return a controlled error payload when no candidate returned valid JSON. */
    $result['brand_data'] = [
        'success' => false,
        'error' => 'Invalid JSON response from brand data endpoint.',
    ];

    return $result;
}

/** Validate that the fetched brand payload belongs to the same license. */
function vogo_validate_brand_license_response($brand_data, array $record, string $module): array {
    if (!is_array($brand_data)) {
        return [
            'status' => 'ok',
            'message' => 'No license validation needed.',
        ];
    }

    $response_license = '';
    if (isset($brand_data['license_code'])) {
        $response_license = (string) $brand_data['license_code'];
    } elseif (isset($brand_data['licensecode'])) {
        $response_license = (string) $brand_data['licensecode'];
    }

    if ($response_license === '') {
        return [
            'status' => 'ok',
            'message' => 'No license validation needed.',
        ];
    }

    $stored_license = (string) ($record['client_key'] ?? '');
    if ($stored_license === '') {
        return [
            'status' => 'ok',
            'message' => 'No stored license found.',
        ];
    }

    if ($response_license !== $stored_license) {
        vogo_error_log3('[activateAndGetUpdates] License mismatch response=' . $response_license . ' stored=' . $stored_license, $module);
        return [
            'status' => 'error',
            'message' => 'Invalid license.',
        ];
    }

    return [
        'status' => 'ok',
        'message' => 'License valid.',
    ];
}
