<?php
/**
 * VOGO JWT Secure configuration module.
 *
 * This file provides a dedicated wp-admin screen used to configure JWT
 * settings for this server, test the JWT endpoint, and log all operations
 * using the adi-tehnic-vogo diagnostic style.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Returns the JWT option keys handled by this module.
 */
function vogo_jwt_config_fields() {
    return [
        'vogo_jwt_enabled' => ['default' => '0', 'label' => 'Enable JWT secure flow', 'type' => 'checkbox'],
        'vogo_jwt_secret_key' => ['default' => '', 'label' => 'JWT Secret Key', 'type' => 'text'],
        'vogo_jwt_issuer' => ['default' => home_url('/'), 'label' => 'JWT Issuer (iss)', 'type' => 'url'],
        'vogo_jwt_audience' => ['default' => home_url('/'), 'label' => 'JWT Audience (aud)', 'type' => 'url'],
        'vogo_jwt_algorithm' => ['default' => 'HS256', 'label' => 'JWT Algorithm', 'type' => 'select'],
        'vogo_jwt_token_ttl' => ['default' => '3600', 'label' => 'Token TTL (seconds)', 'type' => 'number'],
        'vogo_jwt_auth_endpoint' => ['default' => home_url('/wp-json/jwt-auth/v1/token'), 'label' => 'JWT Auth Endpoint', 'type' => 'url'],
        'vogo_jwt_test_user' => ['default' => 'peter@example.com', 'label' => 'JWT Test Username', 'type' => 'text'],
        'vogo_jwt_test_password' => ['default' => '12345', 'label' => 'JWT Test Password', 'type' => 'password'],
    ];
}

/**
 * Resolves the absolute path to the WordPress wp-config.php file.
 */
function vogo_jwt_config_get_wp_config_path() {
    $candidates = [
        trailingslashit(ABSPATH) . 'wp-config.php',
        dirname(untrailingslashit(ABSPATH)) . '/wp-config.php',
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && file_exists($candidate)) {
            return $candidate;
        }
    }

    return '';
}

/**
 * Builds a readable JWT block that can be inserted in wp-config.php.
 *
 * @param string $secret_key JWT signing secret.
 * @param string $algorithm  JWT hashing algorithm.
 */
function vogo_jwt_config_build_constants_block($secret_key, $algorithm) {
    $safe_secret = var_export((string) $secret_key, true);
    $safe_algorithm = var_export((string) $algorithm, true);

    return "\n/**\n * VOGO JWT secure constants (auto-managed).\n * These constants are maintained by the JWT Secure admin screen.\n */\n"
        . "define('JWT_AUTH_SECRET_KEY', {$safe_secret});\n"
        . "define('JWT_AUTH_CORS_ENABLE', true);\n"
        . "define('JWT_ALGO', {$safe_algorithm});\n";
}

/**
 * Inserts or updates required JWT constants in wp-config.php.
 *
 * @param string $secret_key JWT signing secret.
 * @param string $algorithm  JWT hashing algorithm.
 * @return array{ok:bool,message:string}
 */
function vogo_jwt_config_upsert_wp_config_constants($secret_key, $algorithm) {
    $wp_config_path = vogo_jwt_config_get_wp_config_path();
    if ($wp_config_path === '') {
        return ['ok' => false, 'message' => 'wp-config.php could not be located.'];
    }

    if (!is_writable($wp_config_path)) {
        return ['ok' => false, 'message' => 'wp-config.php is not writable by PHP.'];
    }

    $content = file_get_contents($wp_config_path);
    if ($content === false) {
        return ['ok' => false, 'message' => 'wp-config.php could not be read.'];
    }

    $block = vogo_jwt_config_build_constants_block($secret_key, $algorithm);
    $updated = $content;

    /** Replace existing constants first so we keep the file clean and deterministic. */
    $replacements = [
        "/define\(\s*'JWT_AUTH_SECRET_KEY'\s*,\s*.*?\);/" => "define('JWT_AUTH_SECRET_KEY', " . var_export((string) $secret_key, true) . ');',
        "/define\(\s*'JWT_AUTH_CORS_ENABLE'\s*,\s*.*?\);/" => "define('JWT_AUTH_CORS_ENABLE', true);",
        "/define\(\s*'JWT_ALGO'\s*,\s*.*?\);/" => "define('JWT_ALGO', " . var_export((string) $algorithm, true) . ');',
    ];

    foreach ($replacements as $pattern => $replacement) {
        $updated = (string) preg_replace($pattern, $replacement, $updated);
    }

    /** Append constants block near the WordPress marker when constants are missing. */
    if (strpos($updated, "define('JWT_AUTH_SECRET_KEY'") === false || strpos($updated, "define('JWT_AUTH_CORS_ENABLE'") === false || strpos($updated, "define('JWT_ALGO'") === false) {
        $stop_marker_pattern = "/\\/\\*\\s*That's all, stop editing! Happy publishing\\.\\s*\\*\\//";
        if (preg_match($stop_marker_pattern, $updated) === 1) {
            $updated = (string) preg_replace($stop_marker_pattern, trim($block) . "\n\n$0", $updated, 1);
        } else {
            $updated .= "\n" . trim($block) . "\n";
        }
    }

    if ($updated === $content) {
        return ['ok' => true, 'message' => 'JWT constants were already up to date in wp-config.php.'];
    }

    if (file_put_contents($wp_config_path, $updated) === false) {
        return ['ok' => false, 'message' => 'wp-config.php could not be updated on disk.'];
    }

    return ['ok' => true, 'message' => 'JWT constants were automatically synchronized to wp-config.php.'];
}

/**
 * Renders the dedicated JWT Secure admin page.
 */
function vogo_brand_options_render_jwt_config_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized request.', 'Unauthorized', ['response' => 403]);
    }

    vogo_error_log3('[adi-tehnic-vogo][jwt-secure][render] Rendering JWT Secure configuration page.');

    $fields = vogo_jwt_config_fields();
    $values = [];
    foreach ($fields as $key => $meta) {
        $values[$key] = vogo_brand_option_get($key, $meta['default']);
    }

    $test_result = isset($_GET['jwt_test_result']) ? sanitize_text_field((string) $_GET['jwt_test_result']) : '';
    $test_message = isset($_GET['jwt_test_message']) ? sanitize_text_field((string) $_GET['jwt_test_message']) : '';
    $wpconfig_sync_status = isset($_GET['wpconfig_sync_status']) ? sanitize_text_field((string) $_GET['wpconfig_sync_status']) : '';
    $wpconfig_sync_message = isset($_GET['wpconfig_sync_message']) ? sanitize_text_field((string) $_GET['wpconfig_sync_message']) : '';

    echo '<div class="wrap vogo-mobile-categories">';
    echo '<h1>JWT Secure</h1>';
    echo '<p>Configure complete JWT server settings for the VOGO mobile integration layer.</p>';
    echo '<div class="vogo-mobile-categories-form">';
    echo '<div class="vogo-mobile-category-toolbar">';
    echo '<div class="vogo-mobile-category-toolbar-left">';
    echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=vogo-license-control-center')) . '">Back to License Control Center</a>';
    echo '</div>';
    echo '</div>';

    if (!empty($_GET['updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>JWT Secure settings saved.</p></div>';
    }

    /** Save feedback section: shows automatic wp-config synchronization status. */
    if ($wpconfig_sync_status !== '' && $wpconfig_sync_message !== '') {
        $notice_class = $wpconfig_sync_status === 'ok' ? 'notice notice-success is-dismissible' : 'notice notice-warning is-dismissible';
        echo '<div class="' . esc_attr($notice_class) . '"><p>' . esc_html($wpconfig_sync_message) . '</p></div>';
    }

    /** JWT settings form section: stores all server-side JWT parameters. */
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vogo-jwt-form">';
    wp_nonce_field('vogo_jwt_config_save');
    echo '<input type="hidden" name="action" value="vogo_jwt_config_save" />';

    /** Responsive two-column grid section for a cleaner and more ergonomic admin layout. */
    echo '<div class="vogo-jwt-grid" role="list">';

    foreach ($fields as $key => $meta) {
        /** Individual field card section: renders label, key hint, and control in one visual block. */
        echo '<div class="vogo-jwt-field-card" role="listitem">';
        echo '<label class="vogo-jwt-field-label" for="' . esc_attr($key) . '">' . esc_html($meta['label']) . '</label>';
        echo '<span class="vogo-jwt-field-key">' . esc_html($key) . '</span>';
        echo '<div class="vogo-jwt-field-control">';

        if ($meta['type'] === 'checkbox') {
            echo '<label><input id="' . esc_attr($key) . '" type="checkbox" name="' . esc_attr($key) . '" value="1" ' . checked((string) $values[$key], '1', false) . ' /> Enabled</label>';
        } elseif ($meta['type'] === 'select') {
            echo '<select id="' . esc_attr($key) . '" name="' . esc_attr($key) . '">';
            foreach (['HS256', 'HS384', 'HS512'] as $algorithm) {
                echo '<option value="' . esc_attr($algorithm) . '" ' . selected((string) $values[$key], $algorithm, false) . '>' . esc_html($algorithm) . '</option>';
            }
            echo '</select>';
        } elseif ($meta['type'] === 'number') {
            echo '<input id="' . esc_attr($key) . '" type="number" min="60" step="60" name="' . esc_attr($key) . '" value="' . esc_attr((string) $values[$key]) . '" />';
        } elseif ($key === 'vogo_jwt_secret_key') {
            /** Secret key section: allows one-click random secret generation directly in the UI. */
            echo '<div class="vogo-jwt-inline-control">';
            echo '<input id="' . esc_attr($key) . '" type="text" name="' . esc_attr($key) . '" value="' . esc_attr((string) $values[$key]) . '" class="regular-text" />';
            echo '<button type="button" class="button button-secondary" id="vogo-jwt-generate-secret">Generate</button>';
            echo '</div>';
        } else {
            echo '<input id="' . esc_attr($key) . '" type="' . esc_attr($meta['type']) . '" name="' . esc_attr($key) . '" value="' . esc_attr((string) $values[$key]) . '" class="regular-text" />';
        }

        echo '</div>';
        echo '</div>';
    }

    echo '</div>';

    /** Action section: apply and persistence operations. */
    echo '<p><button type="submit" class="button button-primary">Apply</button></p>';
    echo '</form>';

    /** JWT validation section: includes action button and inline test feedback message. */
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vogo-jwt-test-form">';
    wp_nonce_field('vogo_jwt_config_test');
    echo '<input type="hidden" name="action" value="vogo_jwt_config_test" />';
    echo '<p class="vogo-jwt-test-row"><button type="submit" class="button">Test JWT Configuration</button>';
    if ($test_result !== '') {
        $result_class = $test_result === 'ok' ? 'vogo-jwt-test-message is-success' : 'vogo-jwt-test-message is-error';
        echo '<span class="' . esc_attr($result_class) . '"><strong>JWT test result:</strong> ' . esc_html($test_message) . '</span>';

        /** Fallback guidance section: if constants are still missing, open a detailed help popup. */
        if ($test_result !== 'ok' && strpos($test_message, 'JWT constants are missing in wp-config.php') !== false) {
            echo '<button type="button" class="button button-link vogo-jwt-help-trigger" id="vogo-jwt-show-help">Show me how</button>';
        }
    }
    echo '</p>';
    echo '</form>';

    /** Step-by-step fallback modal shown when wp-config.php cannot be auto-updated by PHP. */
    echo '<div class="vogo-jwt-help-modal" id="vogo-jwt-help-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="vogo-jwt-help-title">';
    echo '<div class="vogo-jwt-help-dialog">';
    echo '<button type="button" class="vogo-jwt-help-close" id="vogo-jwt-help-close" aria-label="Close">×</button>';
    echo '<h2 id="vogo-jwt-help-title">How to fix JWT constants in wp-config.php</h2>';
    echo '<p class="vogo-jwt-help-intro">Follow these exact steps in order. Do not skip any step.</p>';
    echo '<ol class="vogo-jwt-help-steps">';
    echo '<li><strong>Open your hosting file manager or SSH terminal.</strong> Go to your WordPress root folder where <code>wp-config.php</code> exists.</li>';
    echo '<li><strong>Create a backup first.</strong> Duplicate <code>wp-config.php</code> and name it <code>wp-config.php.bak</code>.</li>';
    echo '<li><strong>Edit <code>wp-config.php</code>.</strong> Find the line that contains: <code>That\'s all, stop editing! Happy publishing.</code></li>';
    echo '<li><strong>Paste this block immediately above that line:</strong><pre>' . esc_html(vogo_jwt_config_build_constants_block((string) $values['vogo_jwt_secret_key'], (string) $values['vogo_jwt_algorithm'])) . '</pre></li>';
    echo '<li><strong>Save the file</strong> and make sure permissions remain readable by WordPress (typically 644).</li>';
    echo '<li><strong>Return to this page</strong> and click <em>Test JWT Configuration</em> again.</li>';
    echo '</ol>';
    echo '<p class="vogo-jwt-help-note">Tip: if the secret is empty, generate one above, click <em>Apply</em>, then copy the updated constants block.</p>';
    echo '</div>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    echo '<style>
        .vogo-jwt-form input[type="text"], .vogo-jwt-form input[type="url"], .vogo-jwt-form input[type="password"], .vogo-jwt-form input[type="number"], .vogo-jwt-form select { width: 100%; max-width: 500px; }
        .vogo-mobile-category-toolbar .button { border-radius: 999px; padding: 6px 16px; border: 1px solid #0c542d; box-shadow: 0 6px 12px rgba(7, 52, 28, 0.12); font-weight: 600; }
        .vogo-jwt-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 14px; margin-top: 14px; }
        .vogo-jwt-field-card { border: 1px solid #d9e2ea; border-radius: 12px; background: #ffffff; padding: 14px; box-shadow: 0 8px 18px rgba(18, 38, 63, 0.06); }
        .vogo-jwt-field-label { display: block; font-weight: 600; margin-bottom: 4px; }
        .vogo-jwt-field-key { display: block; font-size: 12px; color: #5d6b82; margin-bottom: 10px; }
        .vogo-jwt-inline-control { display: flex; gap: 8px; align-items: center; }
        .vogo-jwt-inline-control input { flex: 1; }
        .vogo-jwt-test-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .vogo-jwt-test-message { display: inline-block; padding: 6px 10px; border-radius: 8px; font-size: 13px; }
        .vogo-jwt-test-message.is-success { background: #edf9f1; color: #0f5132; border: 1px solid #a7e2bb; }
        .vogo-jwt-test-message.is-error { background: #fff0f0; color: #842029; border: 1px solid #f1b0b7; }
        .vogo-jwt-help-trigger { color: #0f7a3a !important; font-weight: 600; text-decoration: underline; }
        .vogo-jwt-help-modal { position: fixed; inset: 0; background: rgba(8, 30, 18, 0.55); display: none; z-index: 100000; align-items: center; justify-content: center; padding: 20px; }
        .vogo-jwt-help-modal.is-visible { display: flex; }
        .vogo-jwt-help-dialog { width: min(860px, 100%); max-height: 90vh; overflow: auto; background: #f6fff9; border: 1px solid #8dd7a7; border-radius: 16px; box-shadow: 0 18px 40px rgba(7, 52, 28, 0.28); padding: 22px; position: relative; }
        .vogo-jwt-help-dialog h2 { margin-top: 0; color: #0f7a3a; }
        .vogo-jwt-help-intro { color: #1e4d2f; font-size: 14px; }
        .vogo-jwt-help-steps { margin-left: 20px; color: #123c25; }
        .vogo-jwt-help-steps li { margin-bottom: 10px; }
        .vogo-jwt-help-steps pre { background: #0f2b1b; color: #d6ffe5; border-radius: 8px; padding: 12px; overflow: auto; }
        .vogo-jwt-help-note { background: #e7f9ee; border: 1px solid #9fddba; border-radius: 10px; padding: 10px; color: #185436; }
        .vogo-jwt-help-close { position: absolute; right: 10px; top: 8px; border: 0; background: transparent; color: #185436; font-size: 28px; line-height: 1; cursor: pointer; }
    </style>';

    /** Inline admin-page JavaScript section: generates a strong random JWT secret in one click. */
    echo '<script>
        (function () {
            var generateButton = document.getElementById("vogo-jwt-generate-secret");
            var secretInput = document.getElementById("vogo_jwt_secret_key");
            if (!generateButton || !secretInput) {
                return;
            }

            generateButton.addEventListener("click", function () {
                var charset = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>?";
                var randomBytes = window.crypto && window.crypto.getRandomValues ? window.crypto.getRandomValues(new Uint8Array(64)) : null;
                var generatedSecret = "";

                for (var index = 0; index < 64; index += 1) {
                    var randomValue = randomBytes ? randomBytes[index] : Math.floor(Math.random() * charset.length);
                    generatedSecret += charset.charAt(randomValue % charset.length);
                }

                secretInput.value = generatedSecret;
                secretInput.dispatchEvent(new Event("change", { bubbles: true }));
            });

            var helpTrigger = document.getElementById("vogo-jwt-show-help");
            var helpModal = document.getElementById("vogo-jwt-help-modal");
            var helpClose = document.getElementById("vogo-jwt-help-close");

            if (helpTrigger && helpModal) {
                helpTrigger.addEventListener("click", function () {
                    helpModal.classList.add("is-visible");
                    helpModal.setAttribute("aria-hidden", "false");
                });

                helpModal.addEventListener("click", function (event) {
                    if (event.target === helpModal) {
                        helpModal.classList.remove("is-visible");
                        helpModal.setAttribute("aria-hidden", "true");
                    }
                });
            }

            if (helpClose && helpModal) {
                helpClose.addEventListener("click", function () {
                    helpModal.classList.remove("is-visible");
                    helpModal.setAttribute("aria-hidden", "true");
                });
            }
        })();
    </script>';
}

/**
 * Persists JWT configuration fields from the dedicated admin page.
 */
function vogo_jwt_config_save() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized request.', 'Unauthorized', ['response' => 403]);
    }

    check_admin_referer('vogo_jwt_config_save');

    $fields = vogo_jwt_config_fields();
    foreach ($fields as $key => $meta) {
        $raw = $_POST[$key] ?? '';

        if ($meta['type'] === 'checkbox') {
            $value = isset($_POST[$key]) ? '1' : '0';
        } elseif ($meta['type'] === 'number') {
            $value = (string) max(60, (int) $raw);
        } elseif ($meta['type'] === 'url') {
            $value = esc_url_raw((string) $raw);
        } else {
            $value = sanitize_text_field((string) $raw);
        }

        vogo_error_log3('[adi-tehnic-vogo][jwt-secure][save] Field=' . $key . ' raw=' . wp_json_encode($raw) . ' sanitized=' . wp_json_encode($value));
        vogo_brand_option_set($key, $value, 'JWT Secure field: ' . $meta['label'], 'Security / JWT');
    }

    /** Automatic wp-config synchronization section: keeps plugin settings and constants aligned. */
    $sync_result = vogo_jwt_config_upsert_wp_config_constants(
        (string) ($_POST['vogo_jwt_secret_key'] ?? ''),
        (string) ($_POST['vogo_jwt_algorithm'] ?? 'HS256')
    );
    vogo_error_log3('[adi-tehnic-vogo][jwt-secure][save] wp-config sync=' . wp_json_encode($sync_result));

    vogo_error_log3('[adi-tehnic-vogo][jwt-secure][save] JWT settings successfully updated.');
    wp_safe_redirect(add_query_arg([
        'page' => 'vogo-jwt-secure',
        'updated' => '1',
        'wpconfig_sync_status' => $sync_result['ok'] ? 'ok' : 'warning',
        'wpconfig_sync_message' => rawurlencode((string) $sync_result['message']),
    ], admin_url('admin.php')));
    exit;
}

add_action('admin_post_vogo_jwt_config_save', 'vogo_jwt_config_save');

/**
 * Tests JWT configuration by requesting a token from the configured endpoint.
 */
function vogo_jwt_config_test() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized request.', 'Unauthorized', ['response' => 403]);
    }

    check_admin_referer('vogo_jwt_config_test');

    $endpoint = vogo_brand_option_get('vogo_jwt_auth_endpoint', home_url('/wp-json/jwt-auth/v1/token'));
    $username = vogo_brand_option_get('vogo_jwt_test_user', 'peter@example.com');
    $password = vogo_brand_option_get('vogo_jwt_test_password', '12345');

    $result = 'error';
    $message = 'Unknown JWT test error.';

    /** Configuration verification section: validates endpoint, credentials, and essential JWT constants. */
    if (!defined('JWT_AUTH_SECRET_KEY') || !defined('JWT_AUTH_CORS_ENABLE') || !defined('JWT_ALGO')) {
        $message = 'JWT constants are missing in wp-config.php. Required: JWT_AUTH_SECRET_KEY, JWT_AUTH_CORS_ENABLE, JWT_ALGO.';
    } elseif (empty($endpoint)) {
        $message = 'JWT Auth Endpoint is empty.';
    } elseif ($username === '' || $password === '') {
        $message = 'JWT test credentials are missing.';
    } else {
        vogo_error_log3('[adi-tehnic-vogo][jwt-secure][test] Testing endpoint=' . $endpoint . ' user=' . $username);

        $response = wp_remote_post($endpoint, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'username' => $username,
                'password' => $password,
            ]),
        ]);

        if (is_wp_error($response)) {
            $message = 'HTTP error: ' . $response->get_error_message();
        } else {
            $status = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode((string) wp_remote_retrieve_body($response), true);
            $token = is_array($body) ? (string) ($body['token'] ?? '') : '';

            if ($status >= 200 && $status < 300 && $token !== '') {
                $result = 'ok';
                $message = 'JWT token received successfully (HTTP ' . $status . ').';
            } else {
                $message = 'JWT test failed (HTTP ' . $status . '). Response: ' . wp_json_encode($body);
            }
        }
    }

    vogo_error_log3('[adi-tehnic-vogo][jwt-secure][test] Result=' . $result . ' message=' . $message);

    wp_safe_redirect(add_query_arg([
        'page' => 'vogo-jwt-secure',
        'jwt_test_result' => $result,
        'jwt_test_message' => rawurlencode($message),
    ], admin_url('admin.php')));
    exit;
}

add_action('admin_post_vogo_jwt_config_test', 'vogo_jwt_config_test');
