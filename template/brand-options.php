<?php
/**
 * VOGO Brand Options Admin Module.
 *
 * This file defines the admin UI, validation, and persistence logic used by
 * the Brand Control Center page available in WordPress admin.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vogo_user_roles.php';
require_once __DIR__ . '/brand-version-history.php';

function vogo_brand_options_table_name() {
    global $wpdb;
    // Resolve the full options table name for this site.
    return $wpdb->prefix . 'vogo_brand_options';
}

/**
 * Enqueue media and page-specific assets used by Brand Control Center screens.
 */
function vogo_brand_options_enqueue_admin_assets($hook) {
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    if (!in_array($page, ['vogo-brand-options', 'vogo-mobile-categories', 'vogo-jwt-secure'], true)) {
        return;
    }

    wp_enqueue_media();

    if ($page !== 'vogo-mobile-categories') {
        return;
    }

    wp_enqueue_script(
        'vogo-sortablejs',
        'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js',
        [],
        '1.15.2',
        true
    );
}

add_action('admin_enqueue_scripts', 'vogo_brand_options_enqueue_admin_assets');

/**
 * Return the master options definition map used by admin forms and storage.
 */
function vogo_brand_options_get_definitions() {
    // Central field registry used by admin forms, storage metadata, and REST grouping.
    return [
        'brand_prod_endpoint_url' => [
            'label' => 'Brand Endpoint URL (Production)',
            'description' => 'Production API base URL exposed to mobile clients.',
            'category' => 'Master page – Quick links',
            'default' => home_url('/wp-json/vogo/v1'),
        ],
        'brand_name' => [
            'label' => 'Brand name',
            'description' => 'App brand name (e.g., header title, splash screen).',
            'category' => 'Brand / application identity',
        ],
        'brand_icon' => [
            'label' => 'Brand icon (URL)',
            'description' => 'Brand icon/logo (used in splash or header).',
            'category' => 'Other settings / Brand settings',
        ],
        'brand_version' => [
            'label' => 'Brand version',
            'description' => 'Brand version (shown in About/Settings).',
            'category' => 'Brand / application identity',
        ],
        'brand_splash_bkcolor' => [
            'label' => 'Splash background color',
            'description' => 'Splash background color.',
            'category' => 'Splash screen / launch screen',
        ],
        'brand_splash_forecolor' => [
            'label' => 'Splash fore color',
            'description' => 'Splash foreground/text color.',
            'category' => 'Splash screen / launch screen',
        ],
        'splash_image_top' => [
            'label' => 'Splash top image (URL)',
            'description' => 'Top image/logo on the splash screen.',
            'category' => 'Master page – Quick links',
        ],
        'splash_text_down' => [
            'label' => 'Splash bottom text',
            'description' => 'Bottom text on the splash screen (tagline/message).',
            'category' => 'Splash screen / launch screen',
        ],
        'login_hero_text_h1' => [
            'label' => 'Login hero title (H1)',
            'description' => 'Main heading text for the login screen hero.',
            'category' => 'Login screen',
        ],
        'login_hero_text_h2' => [
            'label' => 'Login hero subtitle (H2)',
            'description' => 'Secondary heading text for the login screen hero.',
            'category' => 'Login screen',
        ],
        'login_hero_forecolor' => [
            'label' => 'Hero forecolor',
            'description' => 'Foreground/text color for the login screen hero.',
            'category' => 'Login screen',
        ],
        'login_hero_h1_size' => [
            'label' => 'H1 size',
            'description' => 'Font size for the login screen H1 (in px).',
            'category' => 'Login screen',
        ],
        'login_hero_bkcolor' => [
            'label' => 'Hero bkcolor',
            'description' => 'Background color for the login screen hero.',
            'category' => 'Login screen',
        ],
        'login_top_image' => [
            'label' => 'Login top image (URL)',
            'description' => 'Top image/logo shown on the login screen.',
            'category' => 'Login screen',
        ],
        'register_screen_top_small_text' => [
            'label' => 'Register screen top small text',
            'description' => 'Small text displayed at the top of the register screen.',
            'category' => 'Login screen',
        ],
        'link_new_product_request' => [
            'label' => 'New product request link',
            'description' => 'Link for new product requests (support/requests screen).',
            'category' => 'Master page – Quick links',
        ],
        'link_new_product_recommend' => [
            'label' => 'New product recommendation link',
            'description' => 'Link for new product recommendations.',
            'category' => 'Master page – Quick links',
        ],
        'link_register' => [
            'label' => 'Register link',
            'description' => 'Registration link (Login/Register screen).',
            'category' => 'Master page – Quick links',
        ],
        'link_login' => [
            'label' => 'Login link',
            'description' => 'Login link (Login/Register screen).',
            'category' => 'Master page – Quick links',
        ],
        'link_forgot_password' => [
            'label' => 'Forgot password link',
            'description' => 'Forgot password link (Login/Register screen).',
            'category' => 'Master page – Quick links',
        ],
        'link_policy' => [
            'label' => 'Privacy policy / terms link',
            'description' => 'Link to the privacy policy/terms (Settings/Legal).',
            'category' => 'Master page – Quick links',
        ],
        'link_terms_conditions' => [
            'label' => 'Terms and conditions link',
            'description' => 'Link to the terms and conditions (Settings/Legal).',
            'category' => 'Master page – Quick links',
        ],
        'general_buttons_bkcolor' => [
            'label' => 'General buttons bkcolor',
            'description' => 'Background color for general buttons.',
            'category' => 'Master page – Quick links',
        ],
        'general_buttons_forecolor' => [
            'label' => 'General buttons forecolor',
            'description' => 'Foreground/text color for general buttons.',
            'category' => 'Master page – Quick links',
        ],
        'general_top_bkcolor' => [
            'label' => 'General top bkcolor',
            'description' => 'Background color for the general top area.',
            'category' => 'Master page – Quick links',
        ],
        'general_top_forecolor' => [
            'label' => 'General top forecolor',
            'description' => 'Foreground/text color for the general top area.',
            'category' => 'Master page – Quick links',
        ],
        'company_license_code' => [
            'label' => 'Your Vogo license code',
            'description' => 'Vogo license code for the company.',
            'category' => 'Company data',
        ],
        'brand_activation_code' => [
            'label' => 'Brand activation code',
            'description' => 'Brand activation code for the company.',
            'category' => 'Company data',
        ],
        'ecommerce_delivery_cost' => [
            'label' => 'Delivery cost',
            'description' => 'Default delivery cost displayed in the mobile checkout.',
            'category' => 'eCommerce options',
        ],
        'ecommerce_currency' => [
            'label' => 'Dropdown currency',
            'description' => 'Default currency used for eCommerce transactions.',
            'category' => 'eCommerce options',
        ],
        'ecommerce_minimum_order_value' => [
            'label' => 'Minimum order value',
            'description' => 'Minimum order value required before checkout can continue.',
            'category' => 'eCommerce options',
        ],
        'ecommerce_about_us_text' => [
            'label' => 'About us text',
            'description' => 'Text content shown in the app About us section.',
            'category' => 'eCommerce options',
        ],
        'Push_message_scroll_mobile_app' => [
            'label' => 'Push message scroll mobile app',
            'description' => 'Scrollable push message content displayed in the mobile app.',
            'category' => 'eCommerce options',
        ],
        'market_pickup_address' => [
            'label' => 'Market pick-up address',
            'description' => 'Address used for market pick-up in checkout flows.',
            'category' => 'Company data',
        ],
        'market_pickup_city' => [
            'label' => 'Market pick-up city',
            'description' => 'City used for market pick-up in checkout flows.',
            'category' => 'eCommerce options',
        ],
        'market_pickup_street' => [
            'label' => 'Market pick-up street',
            'description' => 'Street used for market pick-up in checkout flows.',
            'category' => 'eCommerce options',
        ],
        'market_pickup_number' => [
            'label' => 'Market pick-up number',
            'description' => 'Street number used for market pick-up in checkout flows.',
            'category' => 'eCommerce options',
        ],
        'market_pickup_details' => [
            'label' => 'Market pick-up details',
            'description' => 'Additional details for market pick-up address in checkout flows.',
            'category' => 'eCommerce options',
        ],
        'market_pickup_lat' => [
            'label' => 'Market pick-up latitude',
            'description' => 'Latitude coordinate used for market pick-up in checkout flows.',
            'category' => 'eCommerce options',
        ],
        'market_pickup_long' => [
            'label' => 'Market pick-up longitude',
            'description' => 'Longitude coordinate used for market pick-up in checkout flows.',
            'category' => 'eCommerce options',
        ],
    ];
}


function vogo_brand_options_get_woocommerce_login_url() {
    return wc_get_page_permalink('myaccount');
}

function vogo_brand_options_get_woocommerce_forgot_password_url() {
    return wp_lostpassword_url(vogo_brand_options_get_woocommerce_login_url());
}

function vogo_brand_options_render_label($label, $description = '') {
    echo '<label>' . esc_html($label);
    if ($description !== '') {
        echo ' <span class="dashicons dashicons-editor-help vogo-brand-tooltip" title="' . esc_attr($description) . '" aria-hidden="true"></span>';
    }
    echo '</label>';
}

/**
 * Ensure the brand options table exists before read/write operations occur.
 */
function vogo_brand_options_ensure_table() {
    global $wpdb;

    // Ensure the options table exists before any read/write operation.
    $table = vogo_brand_options_table_name();
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if (!$exists) {
        // Create the table on demand and report status for debugging.
        vogo_error_log3('[brand-options][table] Missing table=' . $table . ' -> creating.');
        vogo_brand_options_install();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        vogo_error_log3('[brand-options][table] Post-create exists=' . ($exists ? 'yes' : 'no') . ' last_error=' . $wpdb->last_error);
    }
    if ($exists) {
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        if (is_array($columns)) {
            $missing = array_diff(['option_description', 'option_category'], $columns);
            if (!empty($missing)) {
                vogo_error_log3('[brand-options][table] Missing columns=' . implode(',', $missing) . ' -> running dbDelta.');
                vogo_brand_options_install();
            }
        }
    }
    return $table;
}

/**
 * Install hook entry point that initializes plugin persistence structures.
 */
function vogo_brand_options_install() {
    global $wpdb;

    $table = vogo_brand_options_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        option_key VARCHAR(191) NOT NULL,
        option_value LONGTEXT NOT NULL,
        option_description TEXT NULL,
        option_category VARCHAR(191) NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY option_key (option_key)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/**
 * Read a brand option value and fallback to defaults when no row exists.
 */
function vogo_brand_option_get($key, $default = '') {
    global $wpdb;

    $table = vogo_brand_options_ensure_table();
    $value = $wpdb->get_var(
        $wpdb->prepare("SELECT option_value FROM {$table} WHERE option_key = %s LIMIT 1", $key)
    );

    if ($value === null) {
        return $default;
    }

    return $value;
}

/**
 * Upsert a brand option value together with metadata used in admin screens.
 */
function vogo_brand_option_set($key, $value, $description = null, $category = null) {
    global $wpdb;

    $table = vogo_brand_options_ensure_table();
    $existing = $wpdb->get_var(
        $wpdb->prepare("SELECT id FROM {$table} WHERE option_key = %s LIMIT 1", $key)
    );

    if ($existing) {
        $data = ['option_value' => $value];
        $format = ['%s'];
        if ($description !== null) {
            $data['option_description'] = $description;
            $format[] = '%s';
        }
        if ($category !== null) {
            $data['option_category'] = $category;
            $format[] = '%s';
        }
        $result = $wpdb->update(
            $table,
            $data,
            ['option_key' => $key],
            $format,
            ['%s']
        );
        // Log update outcome for each option.
        vogo_error_log3('[brand-options][save] Update option_key=' . $key . ' result=' . $result . ' last_error=' . $wpdb->last_error . ' table=' . $table);
        return;
    }

    $data = ['option_key' => $key, 'option_value' => $value];
    $format = ['%s', '%s'];
    if ($description !== null) {
        $data['option_description'] = $description;
        $format[] = '%s';
    }
    if ($category !== null) {
        $data['option_category'] = $category;
        $format[] = '%s';
    }
    $result = $wpdb->insert(
        $table,
        $data,
        $format
    );
    // Log insert outcome for each option.
    vogo_error_log3('[brand-options][save] Insert option_key=' . $key . ' result=' . $result . ' last_error=' . $wpdb->last_error . ' table=' . $table);
}

/**
 * Register Brand Control Center and related submenu entries in admin.
 */
function vogo_brand_options_register_menu() {
    add_menu_page(
        'VOGO WooCommerce Mobile Apps',
        'VOGO WooCommerce Mobile Apps',
        'manage_options',
        'vogo-brand-options',
        'vogo_brand_options_render_page_override',
        'dashicons-store',
        56
    );
    add_submenu_page(
        'vogo-brand-options',
        'Version history',
        'Version history',
        'manage_options',
        'vogo-brand-version-history',
        'vogo_brand_version_history_render_page'
    );
    add_submenu_page(
        'vogo-brand-options',
        'View logs',
        'View logs',
        'manage_options',
        'vogo-view-logs',
        'vogo_brand_options_render_logs_page'
    );
    add_submenu_page(
        'vogo-brand-options',
        'Mobile app categories',
        'Mobile app categories',
        'manage_options',
        'vogo-mobile-categories',
        'vogo_brand_options_render_mobile_categories_page'
    );
    add_submenu_page(
        'vogo-brand-options',
        'User roles',
        'User roles',
        'manage_options',
        'vogo-user-roles',
        'vogo_brand_options_render_user_roles_page'
    );
    add_submenu_page(
        'vogo-brand-options',
        'JWT Secure',
        'JWT Secure',
        'manage_options',
        'vogo-jwt-secure',
        'vogo_brand_options_render_jwt_config_page'
    );
    // Master page only: no legacy subpages.
}

add_action('admin_menu', 'vogo_brand_options_register_menu');

/**
 * Render the updated Brand Control Center page.
 * Keeps quick links editable and stored in the custom table, removes subpage buttons,
 * and shows an empty "Other settings" section.
 */
/**
 * Render the custom Brand Control Center page layout and grouped sections.
 */
function vogo_brand_options_render_page_override() {
    // Log the page render for diagnostics.
    vogo_error_log3('[brand-options] Rendering master page override.');

    $definitions = vogo_brand_options_get_definitions();

    $values = [
        // Default production endpoint used when the setting has not been saved yet.
        'brand_prod_endpoint_url' => vogo_brand_option_get('brand_prod_endpoint_url', home_url('/wp-json/vogo/v1')),
        'brand_name' => vogo_brand_option_get('brand_name'),
        'brand_icon' => vogo_brand_option_get('brand_icon'),
        'brand_version' => vogo_brand_option_get('brand_version'),
        'brand_splash_bkcolor' => vogo_brand_option_get('brand_splash_bkcolor', '#0f172a'),
        'brand_splash_forecolor' => vogo_brand_option_get('brand_splash_forecolor', '#ffffff'),
        'splash_image_top' => vogo_brand_option_get('splash_image_top'),
        'splash_text_down' => vogo_brand_option_get('splash_text_down', 'Best Splash bottom text here'),
        'login_hero_text_h1' => vogo_brand_option_get('login_hero_text_h1', 'Mobile app Login hero title (H1) here'),
        'login_hero_text_h2' => vogo_brand_option_get('login_hero_text_h2', 'Login hero subtitle (H2) for mobile application here'),
        'login_hero_forecolor' => vogo_brand_option_get('login_hero_forecolor', '#ffffff'),
        'login_hero_bkcolor' => vogo_brand_option_get('login_hero_bkcolor', '#0c542d'),
        'login_hero_h1_size' => vogo_brand_option_get('login_hero_h1_size', '24'),
        'login_top_image' => vogo_brand_option_get('login_top_image'),
        'register_screen_top_small_text' => vogo_brand_option_get('register_screen_top_small_text', 'Text for mobile app Register screen top small text'),
        'link_new_product_request' => vogo_brand_option_get('link_new_product_request'),
        'link_register' => vogo_brand_option_get('link_register'),
        'link_new_product_recommend' => vogo_brand_option_get('link_new_product_recommend'),
        'link_login' => vogo_brand_option_get('link_login', vogo_brand_options_get_woocommerce_login_url()),
        'link_forgot_password' => vogo_brand_option_get('link_forgot_password', vogo_brand_options_get_woocommerce_forgot_password_url()),
        'link_policy' => vogo_brand_option_get('link_policy'),
        'link_terms_conditions' => vogo_brand_option_get('link_terms_conditions'),
        'general_buttons_bkcolor' => vogo_brand_option_get('general_buttons_bkcolor', '#0c542d'),
        'general_buttons_forecolor' => vogo_brand_option_get('general_buttons_forecolor', '#ffffff'),
        'general_top_bkcolor' => vogo_brand_option_get('general_top_bkcolor', '#0c542d'),
        'general_top_forecolor' => vogo_brand_option_get('general_top_forecolor', '#ffffff'),
        'company_name' => vogo_brand_option_get('company_name'),
        'company_fiscal_code' => vogo_brand_option_get('company_fiscal_code'),
        'company_address' => vogo_brand_option_get('company_address'),
        'company_payment_bank_account' => vogo_brand_option_get('company_payment_bank_account'),
        'company_swift_code' => vogo_brand_option_get('company_swift_code'),
        'company_license_code' => vogo_brand_option_get('company_license_code'),
        'brand_activation_code' => vogo_brand_option_get('brand_activation_code'),
        'ecommerce_delivery_cost' => vogo_brand_option_get('ecommerce_delivery_cost'),
        'ecommerce_currency' => vogo_brand_option_get('ecommerce_currency', 'RON'),
        'ecommerce_minimum_order_value' => vogo_brand_option_get('ecommerce_minimum_order_value'),
        'ecommerce_about_us_text' => vogo_brand_option_get('ecommerce_about_us_text'),
        'Push_message_scroll_mobile_app' => vogo_brand_option_get('Push_message_scroll_mobile_app'),
        'market_pickup_address' => vogo_brand_option_get('market_pickup_address'),
        'market_pickup_city' => vogo_brand_option_get('market_pickup_city'),
        'market_pickup_street' => vogo_brand_option_get('market_pickup_street'),
        'market_pickup_number' => vogo_brand_option_get('market_pickup_number'),
        'market_pickup_details' => vogo_brand_option_get('market_pickup_details'),
        'market_pickup_lat' => vogo_brand_option_get('market_pickup_lat'),
        'market_pickup_long' => vogo_brand_option_get('market_pickup_long'),
    ];

    $api_url = home_url();
    $brand_activation_code = trim((string) $values['brand_activation_code']);
    $has_brand_activation_code = $brand_activation_code !== '';
    $qr_payload = $has_brand_activation_code ? $brand_activation_code : 'https://www.vogo.me';
    $qr_src = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . rawurlencode($qr_payload);
    // Build reusable support links for admin CTAs rendered on this page.
    $support_number = defined('VOGO-WHATSAPPP-SUPPORT') ? constant('VOGO-WHATSAPPP-SUPPORT') : '+40723313296';
    $support_number_digits = preg_replace('/\D+/', '', $support_number);
    $support_message = 'VOGO plugin support message - client name: _______ issue: _________';
    $support_url = 'https://wa.me/' . $support_number_digits . '?text=' . rawurlencode($support_message);
    // Log the QR payload source for mobile pairing diagnostics.
    vogo_error_log3('[brand-options] Hero QR source=' . ($has_brand_activation_code ? 'brand_activation_code' : 'fallback_vogo_me'));
    // Log the version shown on the hero panel for traceability.
    vogo_error_log3('[brand-options] Hero version=' . VOGO_PLUGIN_VERSION);

    echo '<div class="wrap vogo-brand-options">';
    echo '<div class="vogo-brand-header">';
    echo '<h1>VOGO WooCommerce – Brand Control Center</h1>';
    echo '<button type="submit" class="button button-primary vogo-brand-header-action" form="vogo-brand-options-form">Save settings</button>';
    echo '</div>';

    $reminder_class = ' vogo-version-reminder--hidden';
    echo '<div class="notice notice-info vogo-version-reminder' . esc_attr($reminder_class) . '" data-reminder-scope="brand-options"><p>You updated configuration: Do not forget to change version to next one X.X.X in order to push to your mobile application.</p></div>';
    if (!empty($_GET['updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>VOGO: Settings were saved.</p></div>';
    }

    // Hero zone: high-visibility platform shortcuts and quick status widgets.
    echo '<div class="vogo-brand-hero">';
    echo '<div class="vogo-brand-hero-info">';
    echo '<span class="vogo-brand-label">API URL (current site)</span>';
    echo '<h2>' . esc_html($api_url) . '</h2>';
    echo '<p>Scan the QR code to pair the mobile app quickly.</p>';
    // Show the public site shortcuts under the API URL.
    echo '<p class="vogo-brand-links-inline">Powered by: <a href="https://vogo.me" target="_blank" rel="noreferrer">vogo.me</a> | <a href="https://vogo.family" target="_blank" rel="noreferrer">vogo.family</a></p>';
    // Display the static plugin version on the hero panel.
    echo '<p>Version = ' . esc_html(VOGO_PLUGIN_VERSION) . '</p>';
    // Administrative quick links with tooltip guidance for the most-used WP/Woo screens.
    // Version history is intentionally rendered in the center column to keep the top shortcut row compact.
    $admin_shortcuts = [
        ['label' => 'Users', 'url' => admin_url('users.php'), 'tooltip' => 'Open WordPress users management.'],
        ['label' => 'Categories', 'url' => admin_url('edit-tags.php?taxonomy=product_cat&post_type=product'), 'tooltip' => 'Open WooCommerce product categories.'],
        ['label' => 'Products', 'url' => admin_url('edit.php?post_type=product'), 'tooltip' => 'Open WooCommerce products list.'],
        ['label' => 'Pages', 'url' => admin_url('edit.php?post_type=page'), 'tooltip' => 'Open WordPress pages list.'],
        ['label' => 'Orders', 'url' => admin_url('edit.php?post_type=shop_order'), 'tooltip' => 'Open WooCommerce orders.'],
        ['label' => 'Media', 'url' => admin_url('upload.php'), 'tooltip' => 'Open WordPress media library.'],
        ['label' => 'Version history', 'url' => admin_url('admin.php?page=vogo-brand-version-history'), 'tooltip' => 'Open brand version history and restore options.'],
        ['label' => 'Contact live support', 'url' => $support_url, 'tooltip' => 'Contact live WhatsApp support.'],
        ['label' => 'Contact VOGO', 'url' => 'https://vogo.me/ecomm-contact-request/', 'tooltip' => 'Open the VOGO contact request form.'],
    ];
    echo '</div>';
    echo '<div class="vogo-brand-hero-center">';
    echo '<div class="vogo-brand-hero-title">VOGO eCommerce native mobile apps</div>';
    echo '<div class="vogo-brand-hero-stores">';
    echo '<a href="https://apps.apple.com/ro/" target="_blank" rel="noreferrer"><img src="https://vogo.family/wp-content/uploads/2026/02/apples-store.png" alt="App Store" decoding="async" /></a>';
    echo '<a href="https://play.google.com/store/apps?hl=ro" target="_blank" rel="noreferrer"><img src="https://vogo.family/wp-content/uploads/2026/02/google-play-1.webp" alt="Google Play" decoding="async" /></a>';
    echo '</div>';
    echo '</div>';
    echo '<div class="vogo-brand-hero-shortcuts" aria-label="WordPress admin shortcuts">';
    foreach ($admin_shortcuts as $shortcut) {
        echo '<a class="button vogo-brand-hero-shortcut" href="' . esc_url($shortcut['url']) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr($shortcut['tooltip']) . '">' . esc_html($shortcut['label']) . '</a>';
    }
    echo '</div>';
    echo '<div class="vogo-brand-hero-qr">';
    echo '<img src="' . esc_url($qr_src) . '" alt="QR brand activation" />';
    echo '<p class="vogo-brand-hero-qr-text">' . esc_html($qr_payload) . '</p>';
    if (!$has_brand_activation_code) {
        echo '<p class="vogo-brand-hero-qr-note"><em>Please activate your plugin</em></p>';
    }
    echo '</div>';
    echo '</div>';

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vogo-brand-form" id="vogo-brand-options-form">';
    wp_nonce_field('vogo_brand_options_save');
    echo '<input type="hidden" name="action" value="vogo_brand_options_save" />';

    echo '<div class="vogo-brand-grid">';
    $animate_plugin_check = !empty($_GET['vogo-plugin-check']);

    echo '<div class="vogo-brand-card vogo-brand-check-card">';
    echo '<h3><span class="dashicons dashicons-admin-links" aria-hidden="true"></span>General Settings</h3>';
    echo '<div class="vogo-brand-links">';
    // Keep JWT quick access as the first General Settings action.
    echo '<div class="vogo-brand-link">';
    echo '<div class="vogo-brand-link-row">';
    echo '<div class="vogo-brand-link-input">Configure JWT security module from a dedicated settings page.</div>';
    echo '<div class="vogo-brand-link-actions">';
    echo '<a class="button button-secondary vogo-brand-button--vogo" href="' . esc_url(admin_url('admin.php?page=vogo-jwt-secure')) . '">JWT Secure</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Render endpoint/link settings shown in the General Settings card.
    $links = [
        'brand_prod_endpoint_url',
        'splash_image_top',
        'link_new_product_request',
        'link_register',
        'link_new_product_recommend',
        'link_login',
        'link_forgot_password',
        'link_policy',
        'link_terms_conditions',
    ];
    foreach ($links as $key) {
        $external = $values[$key];
        echo '<div class="vogo-brand-link">';
        vogo_brand_options_render_label($definitions[$key]['label'], $definitions[$key]['description']);
        $show_open_button = $key !== 'brand_prod_endpoint_url' && (!empty($external) || in_array($key, ['brand_icon', 'splash_image_top', 'link_forgot_password', 'link_terms_conditions'], true));
        if ($key === 'brand_icon') {
            echo '<div class="vogo-brand-link-row vogo-brand-link-row--with-preview">';
            echo '<div class="vogo-brand-link-input">';
            echo '<input type="url" name="' . esc_attr($key) . '" value="' . esc_attr($external) . '" placeholder="https://..." />';
            echo '<div class="vogo-brand-link-actions">';
            if ($key === 'splash_image_top') {
                echo '<button type="button" class="button button-secondary vogo-brand-media-picker" data-target="' . esc_attr($key) . '" title="Select image">...</button>';
            } elseif ($show_open_button) {
                if (!empty($external)) {
                    echo '<a class="button button-secondary vogo-brand-link-button" href="' . esc_url($external) . '" target="_blank" rel="noreferrer" aria-label="Open link" title="Open link">...</a>';
                } else {
                    echo '<button type="button" class="button button-secondary vogo-brand-link-button" disabled aria-disabled="true" title="Add a URL to open">...</button>';
                }
            }
            echo '</div>';
            echo '</div>';
            if (!empty($external)) {
                echo '<div class="vogo-brand-link-preview">';
                echo '<img class="vogo-brand-preview-inline" src="' . esc_url($external) . '" alt="' . esc_attr($definitions[$key]['label']) . '" />';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<div class="vogo-brand-link-row">';
            echo '<input type="url" name="' . esc_attr($key) . '" value="' . esc_attr($external) . '" placeholder="https://..." />';
            echo '<div class="vogo-brand-link-actions">';
            if ($key === 'splash_image_top') {
                echo '<button type="button" class="button button-secondary vogo-brand-media-picker" data-target="' . esc_attr($key) . '" title="Select image">...</button>';
            } elseif ($show_open_button) {
                if (!empty($external)) {
                    echo '<a class="button button-secondary vogo-brand-link-button" href="' . esc_url($external) . '" target="_blank" rel="noreferrer" aria-label="Open link" title="Open link">...</a>';
                } else {
                    echo '<button type="button" class="button button-secondary vogo-brand-link-button" disabled aria-disabled="true" title="Add a URL to open">...</button>';
                }
            }
            echo '</div>';
            echo '</div>';
            if ($key === 'splash_image_top' && !empty($external)) {
                echo '<img class="vogo-brand-preview" src="' . esc_url($external) . '" alt="' . esc_attr($definitions[$key]['label']) . '" />';
            }
        }
        echo '</div>';
    }
    echo '</div>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--two vogo-brand-link-colors">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['general_buttons_bkcolor']['label'], $definitions['general_buttons_bkcolor']['description']);
    echo '<input type="color" name="general_buttons_bkcolor" value="' . esc_attr($values['general_buttons_bkcolor']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['general_buttons_forecolor']['label'], $definitions['general_buttons_forecolor']['description']);
    echo '<input type="color" name="general_buttons_forecolor" value="' . esc_attr($values['general_buttons_forecolor']) . '" />';
    echo '</div>';
    echo '</div>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--two vogo-brand-link-colors">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['general_top_bkcolor']['label'], $definitions['general_top_bkcolor']['description']);
    echo '<input type="color" name="general_top_bkcolor" value="' . esc_attr($values['general_top_bkcolor']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['general_top_forecolor']['label'], $definitions['general_top_forecolor']['description']);
    echo '<input type="color" name="general_top_forecolor" value="' . esc_attr($values['general_top_forecolor']) . '" />';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="vogo-brand-card">';
    echo '<h3><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>Other settings</h3>';
    echo '<p class="vogo-brand-other-actions">';
    echo '<a class="button button-secondary vogo-brand-button--vogo" href="' . esc_url(admin_url('admin.php?page=vogo-view-logs')) . '">View logs</a>';
    echo '<a class="button button-secondary vogo-brand-button--vogo" href="' . esc_url(admin_url('admin.php?page=vogo-mobile-categories')) . '">Mobile app categories</a>';
    echo '<a class="button button-secondary vogo-brand-button--vogo" href="' . esc_url(admin_url('admin.php?page=vogo-user-roles')) . '">User-Roles</a>';
    echo '</p>';
    echo '<div class="vogo-brand-other-settings-fields">';
    echo '<div class="vogo-brand-subsection">';
    echo '<h4>Brand settings</h4>';
    echo '<div class="vogo-brand-inline-fields">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['brand_name']['label'], $definitions['brand_name']['description']);
    echo '<input type="text" name="brand_name" value="' . esc_attr($values['brand_name']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['brand_version']['label'], $definitions['brand_version']['description']);
    echo '<input type="text" name="brand_version" value="' . esc_attr($values['brand_version']) . '" />';
    echo '</div>';
    echo '</div>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--two">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['brand_splash_bkcolor']['label'], $definitions['brand_splash_bkcolor']['description']);
    echo '<input type="color" name="brand_splash_bkcolor" value="' . esc_attr($values['brand_splash_bkcolor']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['brand_splash_forecolor']['label'], $definitions['brand_splash_forecolor']['description']);
    echo '<input type="color" name="brand_splash_forecolor" value="' . esc_attr($values['brand_splash_forecolor']) . '" />';
    echo '</div>';
    echo '</div>';
    vogo_brand_options_render_label($definitions['brand_icon']['label'], $definitions['brand_icon']['description']);
    echo '<div class="vogo-brand-url-row">';
    echo '<input type="url" name="brand_icon" value="' . esc_attr($values['brand_icon']) . '" placeholder="https://..." />';
    echo '<div class="vogo-brand-url-actions">';
    echo '<button type="button" class="button button-secondary vogo-brand-media-picker" data-target="brand_icon" title="Select image">...</button>';
    echo '</div>';
    echo '</div>';
    if (!empty($values['brand_icon'])) {
        echo '<img class="vogo-brand-preview" src="' . esc_url($values['brand_icon']) . '" alt="' . esc_attr($definitions['brand_icon']['label']) . '" />';
    }
    echo '</div>';
    echo '<div class="vogo-brand-subsection">';
    echo '<h4>Splash screen</h4>';
    vogo_brand_options_render_label($definitions['splash_text_down']['label'], $definitions['splash_text_down']['description']);
    echo '<input type="text" name="splash_text_down" value="' . esc_attr($values['splash_text_down']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-subsection">';
    echo '<h4>Login screen</h4>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--two">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['login_hero_text_h1']['label'], $definitions['login_hero_text_h1']['description']);
    echo '<input type="text" name="login_hero_text_h1" value="' . esc_attr($values['login_hero_text_h1']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['login_hero_text_h2']['label'], $definitions['login_hero_text_h2']['description']);
    echo '<input type="text" name="login_hero_text_h2" value="' . esc_attr($values['login_hero_text_h2']) . '" />';
    echo '</div>';
    echo '</div>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--three">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['login_hero_bkcolor']['label'], $definitions['login_hero_bkcolor']['description']);
    echo '<input type="color" name="login_hero_bkcolor" value="' . esc_attr($values['login_hero_bkcolor']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['login_hero_forecolor']['label'], $definitions['login_hero_forecolor']['description']);
    echo '<input type="color" name="login_hero_forecolor" value="' . esc_attr($values['login_hero_forecolor']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['login_hero_h1_size']['label'], $definitions['login_hero_h1_size']['description']);
    echo '<select name="login_hero_h1_size" class="vogo-brand-select--quarter">';
    foreach (['16', '18', '20', '22', '24', '26', '28', '30', '35', '40', '45', '50'] as $size) {
        $selected = selected($values['login_hero_h1_size'], $size, false);
        echo '<option value="' . esc_attr($size) . '"' . $selected . '>' . esc_html($size) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';
    vogo_brand_options_render_label($definitions['login_top_image']['label'], $definitions['login_top_image']['description']);
    echo '<div class="vogo-brand-url-row">';
    echo '<input type="url" name="login_top_image" value="' . esc_attr($values['login_top_image']) . '" placeholder="https://..." />';
    echo '<div class="vogo-brand-url-actions">';
    echo '<button type="button" class="button button-secondary vogo-brand-media-picker" data-target="login_top_image" title="Select image">...</button>';
    echo '</div>';
    echo '</div>';
    if (!empty($values['login_top_image'])) {
        echo '<img class="vogo-brand-preview" src="' . esc_url($values['login_top_image']) . '" alt="' . esc_attr($definitions['login_top_image']['label']) . '" />';
    }
    echo '</div>';
    echo '<div class="vogo-brand-subsection">';
    echo '<h4>Register with screen</h4>';
    vogo_brand_options_render_label($definitions['register_screen_top_small_text']['label'], $definitions['register_screen_top_small_text']['description']);
    echo '<input type="text" name="register_screen_top_small_text" value="' . esc_attr($values['register_screen_top_small_text']) . '" />';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    $plugin_check_log = vogo_brand_option_get('vogo_plugin_check_log');
    $empty_log_message = 'Press above button in order to start automatick self-checking for vogo plugin database structure self diagnosis and repair...';
    $plugin_check_log_display = $animate_plugin_check ? $plugin_check_log : $empty_log_message;
    $plugin_check_tables = function_exists('vogo_brand_options_get_db_check_section_names_from_spec') ? vogo_brand_options_get_db_check_section_names_from_spec() : [];

    echo '<div class="vogo-brand-card vogo-brand-diagnostics">';
    echo '<h3><span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>Vogo plugin self diagnostics tool</h3>';
    /** Diagnostics action menu: keep one primary CTA and reveal advanced choices on hover/click. */
    echo '<div class="vogo-brand-check-actions" id="vogo-plugin-check-menu">';
    echo '<input type="hidden" id="vogo-plugin-check-mode" name="vogo_plugin_check_mode" form="vogo-plugin-check-form" value="full" />';
    echo '<input type="hidden" id="vogo-plugin-check-table" name="vogo_plugin_check_table" form="vogo-plugin-check-form" value="" />';
    echo '<button type="button" class="button" id="vogo-plugin-check-button" data-default-label="Vogo plugin check" data-busy-label="Stop checking ..." aria-haspopup="true" aria-expanded="false">Vogo plugin check</button>';
    echo '<div class="vogo-plugin-check-menu-panel" id="vogo-plugin-check-options" aria-hidden="true">';
    echo '<div class="vogo-plugin-check-option vogo-plugin-check-option--table">';
    echo '<label for="vogo-plugin-check-table-combobox" class="screen-reader-text">Choose section from vogo.xml</label>';
    echo '<div class="vogo-plugin-check-table-autocomplete">';
    echo '<input type="text" id="vogo-plugin-check-table-combobox" class="vogo-plugin-check-table-combobox" placeholder="Choose section..." autocomplete="off" />';
    echo '<div class="vogo-plugin-check-table-list" id="vogo-plugin-check-table-list" role="listbox" aria-label="Available sections"></div>';
    echo '</div>';
    echo '<datalist id="vogo-plugin-check-table-source">';
    foreach ($plugin_check_tables as $plugin_check_table_name) {
        echo '<option value="' . esc_attr($plugin_check_table_name) . '"></option>';
    }
    echo '</datalist>';
    echo '<button type="button" class="button vogo-plugin-check-option vogo-plugin-check-option--icon" data-check-mode="table" title="Run selected section" aria-label="Run selected section">&gt;</button>';
    echo '</div>';
    echo '<button type="button" class="button vogo-plugin-check-option" data-check-mode="full">Run full diagnostics</button>';
    echo '<button type="button" class="button vogo-plugin-check-option" data-check-mode="activation">Check activation</button>';
    echo '</div>';
    // Support CTAs were moved near the mobile apps block (next to Version history) to keep diagnostics focused on checks/logs.
    echo '<button type="button" class="button button-secondary vogo-brand-copy-log" title="Copy to clipboard" aria-label="Copy to clipboard"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span></button>';
    echo '</div>';
    echo '<label>Vogo plugin check logs</label>';
    echo '<textarea class="vogo-brand-log" readonly rows="10" data-full-log="' . esc_attr($plugin_check_log) . '" data-animate="' . ($animate_plugin_check ? '1' : '0') . '">' . esc_textarea($plugin_check_log_display) . '</textarea>';
    echo '</div>';
    echo '</div>';

    echo '<div class="vogo-brand-grid">';
    echo '<div class="vogo-brand-card">';
    echo '<h3><span class="dashicons dashicons-id-alt" aria-hidden="true"></span>Company data</h3>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--wide-narrow">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label('Company name');
    echo '<input type="text" name="company_name" value="' . esc_attr($values['company_name']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label('Fiscal code');
    echo '<input type="text" name="company_fiscal_code" value="' . esc_attr($values['company_fiscal_code']) . '" />';
    echo '</div>';
    echo '</div>';
    vogo_brand_options_render_label('Company address');
    echo '<textarea name="company_address" rows="3">' . esc_textarea($values['company_address']) . '</textarea>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--wide-narrow">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label('Payment bank account');
    echo '<input type="text" name="company_payment_bank_account" value="' . esc_attr($values['company_payment_bank_account']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label('SWIFT code');
    echo '<input type="text" name="company_swift_code" value="' . esc_attr($values['company_swift_code']) . '" />';
    echo '</div>';
    echo '</div>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--wide-narrow">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['company_license_code']['label'], $definitions['company_license_code']['description']);
    echo '<input type="text" name="company_license_code" value="' . esc_attr($values['company_license_code']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['brand_activation_code']['label'], $definitions['brand_activation_code']['description']);
    echo '<input type="text" name="brand_activation_code" value="' . esc_attr($values['brand_activation_code']) . '" />';
    echo '</div>';
    echo '</div>';
    vogo_brand_options_render_label($definitions['market_pickup_address']['label'], $definitions['market_pickup_address']['description']);
    // Anchor target used by the eCommerce quick action for configuring local pickup.
    echo '<table id="vogo-brand-pickup-settings-main" class="vogo-brand-pickup-table">';
    echo '<tr>';
    echo '<td><label for="market_pickup_city">City</label></td>';
    echo '<td><label for="market_pickup_street">Street</label></td>';
    echo '<td><label for="market_pickup_number">Number</label></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><input id="market_pickup_city" type="text" name="market_pickup_city" maxlength="100" value="' . esc_attr($values['market_pickup_city']) . '" /></td>';
    echo '<td><input id="market_pickup_street" type="text" name="market_pickup_street" maxlength="120" value="' . esc_attr($values['market_pickup_street']) . '" /></td>';
    echo '<td><input id="market_pickup_number" type="text" name="market_pickup_number" maxlength="20" value="' . esc_attr($values['market_pickup_number']) . '" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><label for="market_pickup_details" class="vogo-brand-label-regular">Address details</label></td>';
    echo '<td><label for="market_pickup_lat">Latitude</label></td>';
    echo '<td><label for="market_pickup_long">Longitude</label></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><input id="market_pickup_details" type="text" name="market_pickup_details" maxlength="255" value="' . esc_attr($values['market_pickup_details']) . '" /></td>';
    echo '<td><input id="market_pickup_lat" type="text" name="market_pickup_lat" maxlength="20" value="' . esc_attr($values['market_pickup_lat']) . '" /></td>';
    echo '<td><input id="market_pickup_long" type="text" name="market_pickup_long" maxlength="20" value="' . esc_attr($values['market_pickup_long']) . '" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td colspan="3"><a href="https://www.latlong.net/" target="_blank" rel="noreferrer">Finder: https://www.latlong.net/</a></td>';
    echo '</tr>';
    echo '</table>';
    echo '<div class="vogo-brand-actions-top vogo-brand-actions-center">';
  //  echo '<button type="button" class="button vogo-validate-button">Validate company license</button>';
    echo '</div>';
    echo '</div>';

    echo '<div class="vogo-brand-card">';
    echo '<h3><span class="dashicons dashicons-info-outline" aria-hidden="true"></span>eCommerce options</h3>';
    echo '<div class="vogo-brand-other-actions"><button type="button" class="button button-secondary vogo-setup-delivery-point" data-target-id="vogo-brand-pickup-settings-main">Setup delivery point</button><a class="button button-secondary vogo-brand-button--vogo" href="' . esc_url(admin_url('admin.php?page=vogo-push-messages')) . '">Push messages to clients</a></div>';
    echo '<div class="vogo-brand-inline-fields">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label('Delivery cost');
    echo '<input type="text" name="ecommerce_delivery_cost" value="' . esc_attr($values['ecommerce_delivery_cost']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label('Dropdown currency');
    echo '<select name="ecommerce_currency" class="vogo-brand-select--currency">';
    $currency_options = [
        'AED', 'AUD', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EUR', 'GBP',
        'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'JPY', 'KRW', 'MXN', 'MYR', 'NOK',
        'NZD', 'PHP', 'PLN', 'RON', 'SEK', 'SGD', 'THB', 'TRY', 'UAH', 'USD',
    ];
    foreach ($currency_options as $currency_code) {
        $selected = selected($values['ecommerce_currency'], $currency_code, false);
        echo '<option value="' . esc_attr($currency_code) . '"' . $selected . '>' . esc_html($currency_code) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';
    vogo_brand_options_render_label('Payment processing fee 3%');
    vogo_brand_options_render_label('Minimum order value');
    echo '<input type="text" name="ecommerce_minimum_order_value" value="' . esc_attr($values['ecommerce_minimum_order_value']) . '" />';
    vogo_brand_options_render_label($definitions['Push_message_scroll_mobile_app']['label'], $definitions['Push_message_scroll_mobile_app']['description']);
    echo '<input type="text" name="Push_message_scroll_mobile_app" value="' . esc_attr($values['Push_message_scroll_mobile_app']) . '" />';
    vogo_brand_options_render_label('About us text');
    wp_editor(
        $values['ecommerce_about_us_text'],
        'ecommerce_about_us_text',
        [
            'textarea_name' => 'ecommerce_about_us_text',
            'textarea_rows' => 8,
            'media_buttons' => false,
            'teeny' => false,
        ]
    );
    echo '<div class="vogo-brand-actions-top vogo-brand-actions-center">';
    echo '<button type="submit" class="button button-primary">Save</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="vogo-brand-actions">';
    echo '<button type="submit" class="button button-primary">Save settings</button>';
    echo '</div>';
    echo '</form>';

    echo '<form id="vogo-plugin-check-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('vogo_plugin_check');
    echo '<input type="hidden" name="action" value="vogo_plugin_check" />';
    echo '</form>';

    echo '</div>';

    echo '<style>
        .vogo-brand-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
        .vogo-brand-header h1 { margin: 0; font-weight: 700; color: #0c542d; }
        .vogo-brand-hero { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); align-items: start; gap: 24px; background: #0e7f4e; color: #fff; padding: 24px; border-radius: 16px; margin-bottom: 24px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.2); }
        .vogo-brand-hero-info { text-align: left; }
        .vogo-brand-hero-info h2 { margin: 8px 0 0; font-size: 22px; color: #e2e8f0; }
        .vogo-brand-hero-shortcuts { grid-column: 1 / span 2; display: flex; flex-wrap: nowrap; gap: 8px; overflow-x: auto; padding-bottom: 4px; }
        .vogo-brand-hero-shortcut.button { background: #0b5f3a; border-color: #0a5132; color: #ffffff; box-shadow: 0 4px 10px rgba(6, 78, 59, 0.35); }
        .vogo-brand-hero-shortcut.button:hover,
        .vogo-brand-hero-shortcut.button:focus { background: #0a5132; border-color: #073d26; color: #ffffff; }
        .vogo-brand-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.12em; color: #94a3b8; }
        .vogo-brand-links-inline { margin: 8px 0 0; }
        .vogo-brand-links-inline a { color: #ffffff; text-decoration: underline; }
        .vogo-brand-hero-center { text-align: center; display: grid; gap: 12px; justify-items: center; align-self: start; }
        .vogo-brand-hero-shortcut--center { margin-top: 2px; }
        .vogo-brand-hero-title { font-size: 24px; font-weight: 700; color: #ffffff; }
        .vogo-brand-hero-stores { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; align-items: center; }
        .vogo-brand-hero-stores a { background: transparent; border: 0; padding: 0; border-radius: 0; display: inline-flex; align-items: center; justify-content: center; }
        .vogo-brand-hero-stores img { display: block; width: 190px; height: 64px; object-fit: contain; }
        .vogo-brand-hero-qr { grid-column: 3; grid-row: 1 / span 2; justify-self: end; align-self: start; text-align: right; }
        .vogo-brand-hero-qr img { background: #fff; padding: 12px; border-radius: 16px; }
        .vogo-brand-hero-qr-text { margin: 8px 0 0; font-weight: 700; color: #ffffff; word-break: break-word; }
        .vogo-brand-hero-qr-note { margin: 4px 0 0; color: #e2e8f0; }
        .vogo-brand-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .vogo-brand-card { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08); }
        .vogo-brand-card h3 { margin-top: 0; display: flex; align-items: center; gap: 8px; }
        .vogo-brand-card h3 .dashicons { font-size: 20px; color: #0f766e; }
        .vogo-brand-other-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
        .vogo-brand-links { display: grid; gap: 4px; }
        .vogo-brand-link { display: flex; flex-direction: column; gap: 2px; padding: 4px; border-radius: 12px; background: #fff; }
        .vogo-brand-inline-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .vogo-brand-inline-fields--wide-narrow { grid-template-columns: minmax(0, 3fr) minmax(0, 1fr); }
        .vogo-brand-inline-fields--two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .vogo-brand-inline-fields--three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .vogo-brand-pickup-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .vogo-brand-pickup-table td { padding: 4px 6px; vertical-align: top; }
        .vogo-brand-pickup-table label { margin: 0; display: inline-block; }
        .vogo-brand-pickup-table input { width: 100%; }
        .vogo-brand-pickup-table.is-targeted { box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.25); border-radius: 8px; transition: box-shadow 0.2s ease-in-out; }
        .vogo-brand-label-regular { font-weight: 400 !important; }
        .vogo-brand-link-row { display: flex; gap: 6px; align-items: center; flex-wrap: nowrap; }
        .vogo-brand-link-row--with-preview { align-items: center; }
        .vogo-brand-link-input { display: flex; gap: 6px; align-items: center; flex: 1 1 70%; min-width: 0; }
        .vogo-brand-link-input input { flex: 1; min-width: 0; }
        .vogo-brand-link-preview { flex: 0 0 30%; display: flex; justify-content: flex-end; }
        .vogo-brand-link-actions { display: flex; align-items: center; flex-shrink: 0; }
        .vogo-brand-link label { font-weight: 600; margin: 0; }
        .vogo-brand-link input { width: 100%; padding: 6px 8px; border-radius: 8px; border: 1px solid #e2e8f0; flex: 1; min-width: 0; }
        .vogo-brand-link-button { min-width: 32px; text-align: center; padding: 2px 8px; }
        .vogo-brand-url-row { display: flex; gap: 6px; align-items: center; flex-wrap: nowrap; }
        .vogo-brand-url-row input { flex: 1; min-width: 0; }
        .vogo-brand-url-actions { display: flex; align-items: center; flex-shrink: 0; }
        .vogo-brand-other-settings-fields { display: grid; grid-template-columns: 1fr; gap: 20px; margin-top: 16px; }
        .vogo-brand-field { display: flex; flex-direction: column; }
        .vogo-brand-subsection { background: transparent; border-radius: 12px; padding: 16px; border: 1px solid #e2e8f0; }
        .vogo-brand-subsection h4 { margin: 0 0 12px; font-size: 16px; }
        .vogo-brand-form label { display: block; font-weight: 600; margin: 12px 0 6px; }
        .vogo-brand-form input[type="text"],
        .vogo-brand-form input[type="url"],
        .vogo-brand-form textarea,
        .vogo-brand-form select { width: 100%; padding: 8px 10px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .vogo-brand-form .vogo-brand-select--currency { width: 120px; max-width: 100%; }
        .vogo-brand-form .vogo-brand-select--quarter { width: 25%; min-width: 72px; }
        .vogo-brand-form input[type="color"] { height: 40px; width: 80px; border: none; background: transparent; }
        .vogo-brand-preview { margin-top: 12px; max-width: 120px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .vogo-brand-preview-inline { max-width: 100%; height: auto; border-radius: 12px; border: 1px solid #e2e8f0; }
        .vogo-brand-link-colors { margin-top: 12px; }
        .vogo-brand-tooltip { font-size: 16px; color: #64748b; vertical-align: middle; cursor: help; }
        .vogo-brand-actions { margin-top: 20px; }
        .vogo-brand-actions-top { margin-top: 5px; margin-bottom: 16px; display: flex; justify-content: flex-end; }
        .vogo-brand-actions-center { justify-content: center; }
        .vogo-version-reminder { border-left-color: #0e7f4e; background: #ecfdf3; color: #ff0000; font-weight: 600; }
        .vogo-version-reminder--hidden { display: none; }
        .vogo-version-reminder p { margin: 0; font-size: 14px; }
        .vogo-brand-options .notice.notice-success {
            background: #0e7f4e;
            border-left-color: #0e7f4e;
            color: #ffffff;
            box-shadow: 0 8px 16px rgba(14, 127, 78, 0.25);
        }
        .vogo-brand-options .notice.notice-success p { margin: 0; color: #ffffff; font-weight: 700; }
        .vogo-brand-options .notice.notice-success .notice-dismiss:before { color: #ffffff; }
        .vogo-brand-options .button {
            padding: 2px 10px;
            min-height: 28px;
            line-height: 24px;
        }
        .vogo-brand-options .button.vogo-brand-header-action {
            padding: 6px 14px;
        }
        .vogo-brand-options .button.vogo-brand-button--vogo {
            background: #ffffff;
            border-color: #0e7f4e;
            color: #0e7f4e;
        }
        .vogo-brand-options .button.vogo-brand-button--vogo:hover,
        .vogo-brand-options .button.vogo-brand-button--vogo:focus {
            background: #f0fdf4;
            border-color: #0b6b42;
            color: #0b6b42;
        }
        .vogo-validate-button {
            background: #0e7f4e;
            border-color: #0e7f4e;
            color: #ffffff;
            font-weight: 600;
            font-size: 15px;
            padding: 10px 22px;
            min-height: auto;
            line-height: normal;
            border-radius: 999px;
            min-width: 240px;
            text-align: center;
            box-shadow: 0 10px 20px rgba(14, 127, 78, 0.3);
        }
        .vogo-validate-button:hover,
        .vogo-validate-button:focus {
            background: #0b6b42;
            border-color: #0b6b42;
            color: #ffffff;
        }
        .vogo-muted { color: #64748b; font-size: 13px; }
        .vogo-brand-metric { display: grid; gap: 6px; font-size: 14px; color: #0f172a; }
        /* Diagnostics actions: keep one clean CTA and reveal contextual options as an attached panel. */
        .vogo-brand-check-actions { position: relative; display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px; align-items: center; }
        .vogo-plugin-check-menu-panel {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            z-index: 20;
            min-width: 320px;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            box-shadow: 0 14px 28px rgba(14, 127, 78, 0.16);
        }
        .vogo-brand-check-actions.is-expanded .vogo-plugin-check-menu-panel,
        .vogo-brand-check-actions:focus-within .vogo-plugin-check-menu-panel { display: grid; gap: 10px; }
        .vogo-plugin-check-option--table { display: grid; grid-template-columns: minmax(0, 80%) auto; gap: 8px; align-items: center; }
        .vogo-plugin-check-table-autocomplete { position: relative; width: 100%; }
        .vogo-plugin-check-table-combobox {
            width: 100%;
            min-height: 40px;
            border-radius: 5px;
            border: 1px solid #0e7f4e;
            background: #ffffff;
            color: #0e7f4e;
            box-sizing: border-box;
        }
        .vogo-plugin-check-table-list {
            display: none;
            position: absolute;
            top: calc(100% + 2px);
            left: 0;
            right: 0;
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid #0e7f4e;
            border-radius: 5px;
            background: #ffffff;
            z-index: 25;
        }
        .vogo-plugin-check-table-list.is-open { display: block; }
        .vogo-plugin-check-table-item {
            width: 100%;
            text-align: left;
            border: 0;
            background: #ffffff;
            color: #0e7f4e;
            padding: 7px 10px;
            cursor: pointer;
        }
        .vogo-plugin-check-table-item:hover,
        .vogo-plugin-check-table-item:focus {
            background: #f0fdf4;
            color: #0b6b42;
        }
        .vogo-brand-check-actions .button {
            background: #ffffff;
            border: 1px solid #0e7f4e;
            color: #0e7f4e;
            border-radius: 5px;
        }
        .vogo-brand-check-actions .button:hover,
        .vogo-brand-check-actions .button:focus {
            background: #f0fdf4;
            border-color: #0b6b42;
            color: #0b6b42;
        }
        .vogo-plugin-check-option--icon {
            min-width: 34px;
            width: 34px;
            min-height: 40px;
            padding: 0;
            line-height: 1;
            font-weight: 700;
            display: inline-flex;
            justify-content: center;
            align-items: center;
        }
        .vogo-brand-check-actions .vogo-brand-copy-log {
            padding: 0 10px;
            min-width: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .vogo-brand-check-actions .vogo-brand-copy-log .dashicons {
            margin: 0;
        }
        .vogo-brand-check-card { display: flex; flex-direction: column; min-height: 0; }
        .vogo-brand-diagnostics { display: flex; flex-direction: column; min-height: 320px; }
        .vogo-brand-diagnostics .vogo-brand-log { flex: 0 0 80%; height: 80%; max-height: 80%; }
        .vogo-brand-log { width: 100%; flex: 0 0 auto; height: 240px; max-height: 240px; padding: 10px 12px; border-radius: 12px; border: 1px solid #e2e8f0; background: #000 !important; color: #fff !important; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 12px; overflow: auto; }
        @media (max-width: 600px) {
            .vogo-brand-header { flex-direction: column; align-items: flex-start; }
            .vogo-brand-hero { grid-template-columns: 1fr; justify-items: start; gap: 16px; }
            .vogo-brand-hero-center { text-align: left; justify-items: start; }
            .vogo-brand-hero-qr { grid-column: auto; grid-row: auto; justify-self: start; text-align: left; }
        }
    </style>';

    echo '<script>
        (function() {
            const logEl = document.querySelector(".vogo-brand-log[data-full-log]");
            const buttonEl = document.getElementById("vogo-plugin-check-button");
            const copyButtonEl = document.querySelector(".vogo-brand-copy-log");
            const checkModeEl = document.getElementById("vogo-plugin-check-mode");
            const checkTableEl = document.getElementById("vogo-plugin-check-table");
            const checkTableComboEl = document.getElementById("vogo-plugin-check-table-combobox");
            const checkTableListEl = document.getElementById("vogo-plugin-check-table-list");
            const checkTableSourceEl = document.getElementById("vogo-plugin-check-table-source");
            const checkMenuEl = document.getElementById("vogo-plugin-check-menu");
            const checkMenuPanelEl = document.getElementById("vogo-plugin-check-options");
            const checkOptionEls = document.querySelectorAll(".vogo-plugin-check-option[data-check-mode]");
            const checkFormEl = document.getElementById("vogo-plugin-check-form");
            const reminderEl = document.querySelector(".vogo-version-reminder[data-reminder-scope=\"brand-options\"]");
            const brandForm = document.getElementById("vogo-brand-options-form");
            const brandHeaderEl = document.querySelector(".vogo-brand-header");
            const storageKey = "vogoPluginCheckRequested";
            let activeTimer = null;
            let isAnimating = false;
            const copyLabel = "Copy to clipboard";
            const copiedLabel = "Copied!";
            const setCopyLabel = function(label) {
                if (!copyButtonEl) {
                    return;
                }
                copyButtonEl.setAttribute("title", label);
                copyButtonEl.setAttribute("aria-label", label);
            };

            /**
             * Diagnostics menu helpers:
             * - keep one main CTA visible by default
             * - reveal full/table options only on click
             * - use one combobox (with browser autofilter) for section selection
             */
            const setMenuExpanded = function(expanded) {
                if (!checkMenuEl || !checkMenuPanelEl || !buttonEl) {
                    return;
                }
                checkMenuEl.classList.toggle("is-expanded", expanded);
                buttonEl.setAttribute("aria-expanded", expanded ? "true" : "false");
                checkMenuPanelEl.setAttribute("aria-hidden", expanded ? "false" : "true");
            };
            const resetDiagnosticsSelection = function() {
                if (checkModeEl) {
                    checkModeEl.value = "full";
                }
                if (checkTableEl) {
                    checkTableEl.value = "";
                }
                if (checkTableComboEl) {
                    checkTableComboEl.value = "";
                }
            };
            const collectSectionOptions = function() {
                if (!checkTableSourceEl) {
                    return [];
                }
                return Array.from(checkTableSourceEl.querySelectorAll("option"))
                    .map(function(optionNode) {
                        return optionNode.value || "";
                    })
                    .filter(function(value) {
                        return value.trim() !== "";
                    });
            };
            const closeSectionDropdown = function() {
                if (!checkTableListEl) {
                    return;
                }
                checkTableListEl.classList.remove("is-open");
                checkTableListEl.innerHTML = "";
            };
            const renderSectionDropdown = function(filterValue) {
                if (!checkTableListEl) {
                    return;
                }
                const normalizedFilter = (filterValue || "").toLowerCase();
                const matches = collectSectionOptions().filter(function(value) {
                    return value.toLowerCase().indexOf(normalizedFilter) !== -1;
                });
                if (!matches.length) {
                    closeSectionDropdown();
                    return;
                }
                checkTableListEl.innerHTML = matches.map(function(value) {
                    return "<button type=\"button\" class=\"vogo-plugin-check-table-item\" role=\"option\" data-table-name=\"" + value.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\"/g, "&quot;") + "\">" + value.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;") + "</button>";
                }).join("");
                checkTableListEl.classList.add("is-open");
            };
            const setupSectionAutocomplete = function() {
                if (!checkTableComboEl || !checkTableListEl) {
                    return;
                }
                checkTableComboEl.addEventListener("focus", function() {
                    renderSectionDropdown(checkTableComboEl.value.trim());
                });
                checkTableComboEl.addEventListener("input", function() {
                    renderSectionDropdown(checkTableComboEl.value.trim());
                });
                checkTableListEl.addEventListener("click", function(event) {
                    const optionButton = event.target.closest(".vogo-plugin-check-table-item");
                    if (!optionButton || !checkTableComboEl) {
                        return;
                    }
                    checkTableComboEl.value = optionButton.dataset.tableName || "";
                    closeSectionDropdown();
                    checkTableComboEl.focus();
                });
                document.addEventListener("click", function(event) {
                    if (!checkMenuPanelEl) {
                        return;
                    }
                    if (checkMenuPanelEl.contains(event.target)) {
                        return;
                    }
                    closeSectionDropdown();
                });
            };
            const submitDiagnostics = function(mode) {
                if (!checkFormEl || !checkModeEl || !checkTableEl || !buttonEl) {
                    return;
                }
                if (mode === "table") {
                    const selectedTable = (checkTableComboEl ? checkTableComboEl.value.trim() : "");
                    if (!selectedTable) {
                        window.alert("Choose a section before running diagnostics.");
                        return;
                    }
                    checkModeEl.value = "table";
                    checkTableEl.value = selectedTable;
                } else if (mode === "activation") {
                    checkModeEl.value = "activation";
                    checkTableEl.value = "";
                } else {
                    checkModeEl.value = "full";
                    checkTableEl.value = "";
                }
                if (logEl) {
                    logEl.value = "";
                    logEl.dataset.fullLog = "";
                    logEl.scrollTop = 0;
                }
                buttonEl.textContent = buttonEl.dataset.busyLabel || "Stop checking ...";
                buttonEl.setAttribute("aria-busy", "true");
                try {
                    sessionStorage.setItem(storageKey, "1");
                } catch (error) {
                    // No-op for storage errors.
                }
                checkFormEl.requestSubmit();
            };
            const copyLogText = function(text) {
                if (!text || !text.trim()) {
                    return Promise.reject(new Error("Empty log"));
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    return navigator.clipboard.writeText(text);
                }
                return new Promise(function(resolve, reject) {
                    const helper = document.createElement("textarea");
                    helper.value = text;
                    helper.setAttribute("readonly", "readonly");
                    helper.style.position = "absolute";
                    helper.style.left = "-9999px";
                    document.body.appendChild(helper);
                    helper.select();
                    const successful = document.execCommand("copy");
                    document.body.removeChild(helper);
                    if (successful) {
                        resolve();
                    } else {
                        reject(new Error("Copy failed"));
                    }
                });
            };
            const showReminder = function() {
                if (reminderEl) {
                    reminderEl.classList.remove("vogo-version-reminder--hidden");
                }
            };
            const normalizeHeaderTitle = function() {
                if (!brandHeaderEl) {
                    return;
                }

                let hasPrimaryHeading = false;
                brandHeaderEl.childNodes.forEach(function(node) {
                    if (node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== "") {
                        node.remove();
                        return;
                    }

                    if (node.nodeType !== Node.ELEMENT_NODE) {
                        return;
                    }

                    if (node.classList.contains("vogo-brand-header-action")) {
                        return;
                    }

                    if (node.tagName === "H1") {
                        if (hasPrimaryHeading) {
                            node.remove();
                            return;
                        }
                        hasPrimaryHeading = true;
                        return;
                    }

                    if (/Brand Control Center/i.test(node.textContent || "")) {
                        node.remove();
                    }
                });
            };
            const stopAnimation = function(showFullLog) {
                if (!logEl) {
                    return;
                }
                if (activeTimer) {
                    window.clearTimeout(activeTimer);
                    activeTimer = null;
                }
                if (showFullLog) {
                    logEl.value = logEl.dataset.fullLog || "";
                    logEl.scrollTop = logEl.scrollHeight;
                }
                isAnimating = false;
                if (buttonEl) {
                    buttonEl.textContent = buttonEl.dataset.defaultLabel || "Vogo plugin check";
                    buttonEl.removeAttribute("aria-busy");
                    buttonEl.disabled = false;
                }
                try {
                    sessionStorage.removeItem(storageKey);
                } catch (error) {
                    // No-op for storage errors.
                }
            };
            resetDiagnosticsSelection();
            setupSectionAutocomplete();
            // Smooth-scroll and briefly highlight pickup fields when the delivery point shortcut is used.
            const setupDeliveryPointButtons = document.querySelectorAll(".vogo-setup-delivery-point[data-target-id]");
            setupDeliveryPointButtons.forEach(function(actionButton) {
                actionButton.addEventListener("click", function() {
                    const targetId = actionButton.getAttribute("data-target-id");
                    const pickupSection = targetId ? document.getElementById(targetId) : null;
                    if (!pickupSection) {
                        return;
                    }
                    pickupSection.scrollIntoView({ behavior: "smooth", block: "start" });
                    pickupSection.classList.add("is-targeted");
                    window.setTimeout(function() {
                        pickupSection.classList.remove("is-targeted");
                    }, 1800);
                });
            });

            if (buttonEl) {
                buttonEl.addEventListener("click", function(event) {
                    event.preventDefault();
                    if (isAnimating) {
                        stopAnimation(true);
                        setMenuExpanded(false);
                        return;
                    }
                    setMenuExpanded(!checkMenuEl || !checkMenuEl.classList.contains("is-expanded"));
                });
            }
            if (checkMenuEl) {
                document.addEventListener("click", function(event) {
                    if (!checkMenuEl.contains(event.target) && !isAnimating) {
                        setMenuExpanded(false);
                    }
                });
            }
            if (checkOptionEls.length) {
                checkOptionEls.forEach(function(optionButton) {
                    optionButton.addEventListener("click", function() {
                        submitDiagnostics(optionButton.dataset.checkMode || "full");
                        setMenuExpanded(false);
                    });
                });
            }
            if (copyButtonEl) {
                setCopyLabel(copyLabel);
                copyButtonEl.addEventListener("click", function() {
                    if (!logEl) {
                        return;
                    }
                    const logText = logEl.value || logEl.dataset.fullLog || "";
                    copyLogText(logText)
                        .then(function() {
                            setCopyLabel(copiedLabel);
                            window.setTimeout(function() {
                                setCopyLabel(copyLabel);
                            }, 1500);
                        })
                        .catch(function() {
                            setCopyLabel(copyLabel);
                    });
                });
            }
            if (brandForm) {
                brandForm.addEventListener("input", showReminder, { once: true });
                brandForm.addEventListener("change", showReminder, { once: true });
            }
            document.querySelectorAll(".vogo-brand-media-picker").forEach(function(button) {
                button.addEventListener("click", function() {
                    if (!window.wp || !wp.media) {
                        return;
                    }
                    const targetName = button.dataset.target || "";
                    if (!targetName) {
                        return;
                    }
                    const targetInput = brandForm ? brandForm.querySelector("input[name=\"" + targetName + "\"]") : document.querySelector("input[name=\"" + targetName + "\"]");
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
            normalizeHeaderTitle();
            if (!logEl) {
                return;
            }
            const fullLog = logEl.dataset.fullLog || "";
            const shouldAnimate = logEl.dataset.animate === "1";
            let shouldAutoAnimate = false;
            try {
                shouldAutoAnimate = sessionStorage.getItem(storageKey) === "1";
            } catch (error) {
                shouldAutoAnimate = false;
            }
            if (!shouldAnimate || !shouldAutoAnimate || fullLog.trim() === "") {
                return;
            }
            const lines = fullLog.split("\\n");
            let index = 0;
            logEl.value = "";
            const typeNextLine = function() {
                if (index >= lines.length) {
                    stopAnimation(false);
                    return;
                }
                logEl.value += (index ? "\\n" : "") + lines[index];
                logEl.scrollTop = logEl.scrollHeight;
                index += 1;
                activeTimer = window.setTimeout(typeNextLine, 120);
            };
            if (buttonEl) {
                buttonEl.textContent = buttonEl.dataset.busyLabel || "Stop checking ...";
                buttonEl.setAttribute("aria-busy", "true");
                buttonEl.disabled = false;
            }
            isAnimating = true;
            activeTimer = window.setTimeout(typeNextLine, 200);
        })();
    </script>';
}

/**
 * Process submitted Brand Control Center settings and persist sanitized values.
 */
function vogo_brand_options_save() {
    // Log the start of a save request with caller context.
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $user_id = get_current_user_id();
    vogo_error_log3('[brand-options][save] Start save request | ip=' . $ip . ' user_id=' . $user_id);

    if (!current_user_can('manage_options')) {
        // Log permission failure before stopping the request.
        vogo_error_log3('[brand-options][save] Permission denied for manage_options.');
        wp_die('Insufficient permissions.');
    }

    check_admin_referer('vogo_brand_options_save');

    // Confirm nonce validation before processing fields.
    vogo_error_log3('[brand-options][save] Nonce validated, begin field processing.');

    $link_fields = [
        'brand_prod_endpoint_url',
        'brand_icon',
        'splash_image_top',
        'link_new_product_request',
        'link_register',
        'link_new_product_recommend',
        'link_login',
        'link_forgot_password',
        'link_policy',
        'link_terms_conditions',
    ];

    $fields = [
        // Production backend endpoint consumed by mobile clients.
        'brand_prod_endpoint_url' => 'esc_url_raw',
        'brand_name' => 'sanitize_text_field',
        'brand_icon' => 'esc_url_raw',
        'brand_version' => 'sanitize_text_field',
        'brand_splash_bkcolor' => 'sanitize_hex_color',
        'brand_splash_forecolor' => 'sanitize_hex_color',
        'splash_image_top' => 'esc_url_raw',
        'splash_text_down' => 'sanitize_text_field',
        'login_hero_text_h1' => 'sanitize_text_field',
        'login_hero_text_h2' => 'sanitize_text_field',
        'login_hero_forecolor' => 'sanitize_hex_color',
        'login_hero_bkcolor' => 'sanitize_hex_color',
        'login_hero_h1_size' => 'absint',
        'login_top_image' => 'esc_url_raw',
        'register_screen_top_small_text' => 'sanitize_text_field',
        'link_new_product_request' => 'esc_url_raw',
        'link_register' => 'esc_url_raw',
        'link_new_product_recommend' => 'esc_url_raw',
        'link_login' => 'esc_url_raw',
        'link_forgot_password' => 'esc_url_raw',
        'link_policy' => 'esc_url_raw',
        'link_terms_conditions' => 'esc_url_raw',
        'general_buttons_bkcolor' => 'sanitize_hex_color',
        'general_buttons_forecolor' => 'sanitize_hex_color',
        'general_top_bkcolor' => 'sanitize_hex_color',
        'general_top_forecolor' => 'sanitize_hex_color',
        'company_name' => 'sanitize_text_field',
        'company_fiscal_code' => 'sanitize_text_field',
        'company_address' => 'sanitize_textarea_field',
        'company_payment_bank_account' => 'sanitize_text_field',
        'company_swift_code' => 'sanitize_text_field',
        'company_license_code' => 'sanitize_text_field',
        'brand_activation_code' => 'sanitize_text_field',
        'ecommerce_delivery_cost' => 'sanitize_text_field',
        'ecommerce_currency' => 'sanitize_text_field',
        'ecommerce_minimum_order_value' => 'sanitize_text_field',
        'ecommerce_about_us_text' => 'wp_kses_post',
        'Push_message_scroll_mobile_app' => 'sanitize_text_field',
        'market_pickup_address' => 'sanitize_text_field',
        'market_pickup_city' => 'sanitize_text_field',
        'market_pickup_street' => 'sanitize_text_field',
        'market_pickup_number' => 'sanitize_text_field',
        'market_pickup_details' => 'sanitize_text_field',
        'market_pickup_lat' => 'sanitize_text_field',
        'market_pickup_long' => 'sanitize_text_field',
    ];

    $field_max_lengths = [
        'market_pickup_city' => 100,
        'market_pickup_street' => 120,
        'market_pickup_number' => 20,
        'market_pickup_details' => 255,
        'market_pickup_lat' => 20,
        'market_pickup_long' => 20,
    ];

    $definitions = vogo_brand_options_get_definitions();
    // Build a sanitized snapshot payload that will be saved into version history.
    $snapshot_data = [];

    foreach ($fields as $key => $sanitizer) {
        $raw = $_POST[$key] ?? '';
        if (in_array($key, $link_fields, true)) {
            // Ensure the URL has a scheme so it persists after saving.
            $raw = trim((string)$raw);
            if ($raw !== '' && !preg_match('#^https?://#i', $raw)) {
                $raw = 'https://' . $raw;
            }
        }
        $value = call_user_func($sanitizer, $raw);
        if (isset($field_max_lengths[$key])) {
            $value = mb_substr((string) $value, 0, $field_max_lengths[$key]);
        }
        if ($key === 'brand_splash_bkcolor' && $value === null) {
            // Default to VOGO green when no splash background color is provided.
            $value = '#0c542d';
            vogo_error_log3('[brand-options][save] Defaulted brand_splash_bkcolor to VOGO green.');
        }
        if ($key === 'brand_prod_endpoint_url' && $value === '') {
            $value = home_url('/wp-json/vogo/v1');
        }
        if ($key === 'link_login' && $value === '') {
            $value = vogo_brand_options_get_woocommerce_login_url();
        }
        if ($key === 'link_forgot_password' && $value === '') {
            $value = vogo_brand_options_get_woocommerce_forgot_password_url();
        }
        if ($key === 'splash_text_down' && $value === '') {
            $value = 'Best Splash bottom text here';
        }
        if ($key === 'login_hero_text_h1' && $value === '') {
            $value = 'Mobile app Login hero title (H1) here';
        }
        if ($key === 'login_hero_text_h2' && $value === '') {
            $value = 'Login hero subtitle (H2) for mobile application here';
        }
        if ($key === 'register_screen_top_small_text' && $value === '') {
            $value = 'Text for mobile app Register screen top small text';
        }
        // Log each field value before persistence for troubleshooting.
        $previous = vogo_brand_option_get($key);
        vogo_error_log3('[brand-options][save] Field=' . $key . ' raw=' . wp_json_encode($raw) . ' sanitized=' . wp_json_encode($value) . ' previous=' . wp_json_encode($previous));
        $definition = $definitions[$key] ?? null;
        $description = $definition['description'] ?? null;
        $category = $definition['category'] ?? null;
        vogo_brand_option_set($key, $value, $description, $category);
        $snapshot_data[$key] = $value;
    }

    $pickup_parts = [
        trim((string) ($_POST['market_pickup_city'] ?? '')),
        trim((string) ($_POST['market_pickup_street'] ?? '')),
        trim((string) ($_POST['market_pickup_number'] ?? '')),
        trim((string) ($_POST['market_pickup_details'] ?? '')),
    ];
    $pickup_parts = array_values(array_filter($pickup_parts, static function ($part) {
        return $part !== '';
    }));
    $legacy_pickup_address = implode(', ', $pickup_parts);
    vogo_brand_option_set(
        'market_pickup_address',
        sanitize_text_field($legacy_pickup_address),
        $definitions['market_pickup_address']['description'] ?? null,
        $definitions['market_pickup_address']['category'] ?? null
    );
    $snapshot_data['market_pickup_address'] = sanitize_text_field($legacy_pickup_address);

    // Persist a complete version snapshot used by the Version history page.
    vogo_brand_version_history_save_snapshot($snapshot_data);

    // Log completion of the save request.
    vogo_error_log3('[brand-options][save] Finished save request.');

    wp_safe_redirect(add_query_arg(['page' => 'vogo-brand-options', 'updated' => '1'], admin_url('admin.php')));
    exit;
}

add_action('admin_post_vogo_brand_options_save', 'vogo_brand_options_save');

require_once plugin_dir_path(__FILE__) . 'vogo-self-diagnostics.php';
require_once __DIR__ . '/vogo-push-messages.php';
require_once __DIR__ . '/vogo_push_messages_job.php';

/**
 * Run additional settings diagnostics focused on required user/account setup.
 *
 * Scope:
 * - ensure technical JWT app user exists and has expected role
 * - ensure sample WooCommerce user exists as peter@example.com (customer)
 * - migrate legacy paul@example.com to peter@example.com when legacy account is found
 */
/**
 * Validate additional settings for known schema or dependency constraints.
 */
function vogo_brand_options_additional_settings_check() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized request.', 'Unauthorized', ['response' => 403]);
    }

    check_admin_referer('vogo_plugin_additional_settings');

    $username = 'app_mobile_general@vogo.family';
    $password = 'Abc123$';
    $role_slug = 'mobile_general';
    $role_name = 'Mobile General';
    $redirect_status = 'created';

    $setup_log_lines = [];
    // Section 1: JWT technical role/user setup used by mobile general app flow.
    $setup_log_lines[] = 'Checking general JWT role "' . $role_slug . '" ...';
    if (get_role($role_slug) instanceof WP_Role) {
        $setup_log_lines[] = 'Checking general JWT role "' . $role_slug . '" ... Found.';
    } else {
        foreach ($other_requirements as $requirement) {
            $name = (string)($requirement['name'] ?? '');
            $type = (string)($requirement['type'] ?? '');
            if ($name === '' || $type === '') {
                continue;
            }
            $log_lines[] = 'Checking - ' . $type . ' ' . $name . ' > result: manual check required.';
        }
    }

    vogo_brand_option_set('vogo_plugin_check_log', implode("\n", $log_lines));
    wp_safe_redirect(add_query_arg(['page' => 'vogo-brand-options', 'vogo-plugin-check' => 'done'], admin_url('admin.php')));
    exit;
}

add_action('admin_post_vogo_plugin_check', 'vogo_brand_options_plugin_check');

/**
 * Load mobile categories admin module from a dedicated file.
 * This keeps the wp-admin page implementation separated and reusable.
 */
require_once __DIR__ . '/vogo_mobile_categories.php';
require_once __DIR__ . '/vogo_jwt_config.php';
/**
 * Backwards-compatible renderer that delegates to the override implementation.
 */
function vogo_brand_options_render_page() {
    // Log render event to correlate admin activity with settings screens.
    vogo_error_log3('[brand-options] Rendering Brand Control Center page.');

    $definitions = vogo_brand_options_get_definitions();

    $values = [
        // Default production endpoint used when the setting has not been saved yet.
        'brand_prod_endpoint_url' => vogo_brand_option_get('brand_prod_endpoint_url', home_url('/wp-json/vogo/v1')),
        'brand_name' => vogo_brand_option_get('brand_name'),
        'brand_icon' => vogo_brand_option_get('brand_icon'),
        'brand_version' => vogo_brand_option_get('brand_version'),
        // Use VOGO green as the UI fallback when no splash background color is stored.
        'brand_splash_bkcolor' => vogo_brand_option_get('brand_splash_bkcolor', '#0c542d'),
        'brand_splash_forecolor' => vogo_brand_option_get('brand_splash_forecolor', '#ffffff'),
        'splash_image_top' => vogo_brand_option_get('splash_image_top'),
        'splash_text_down' => vogo_brand_option_get('splash_text_down', 'Best Splash bottom text here'),
        'login_hero_text_h1' => vogo_brand_option_get('login_hero_text_h1', 'Mobile app Login hero title (H1) here'),
        'login_hero_text_h2' => vogo_brand_option_get('login_hero_text_h2', 'Login hero subtitle (H2) for mobile application here'),
        'login_hero_forecolor' => vogo_brand_option_get('login_hero_forecolor', '#ffffff'),
        'login_hero_bkcolor' => vogo_brand_option_get('login_hero_bkcolor', '#0c542d'),
        'login_hero_h1_size' => vogo_brand_option_get('login_hero_h1_size', '24'),
        'login_top_image' => vogo_brand_option_get('login_top_image'),
        'register_screen_top_small_text' => vogo_brand_option_get('register_screen_top_small_text', 'Text for mobile app Register screen top small text'),
        'link_new_product_request' => vogo_brand_option_get('link_new_product_request'),
        'link_register' => vogo_brand_option_get('link_register'),
        'link_new_product_recommend' => vogo_brand_option_get('link_new_product_recommend'),
        'link_login' => vogo_brand_option_get('link_login', vogo_brand_options_get_woocommerce_login_url()),
        'link_forgot_password' => vogo_brand_option_get('link_forgot_password', vogo_brand_options_get_woocommerce_forgot_password_url()),
        'link_policy' => vogo_brand_option_get('link_policy'),
        'link_terms_conditions' => vogo_brand_option_get('link_terms_conditions'),
        'general_buttons_bkcolor' => vogo_brand_option_get('general_buttons_bkcolor', '#0c542d'),
        'general_buttons_forecolor' => vogo_brand_option_get('general_buttons_forecolor', '#ffffff'),
        'general_top_bkcolor' => vogo_brand_option_get('general_top_bkcolor', '#0c542d'),
        'general_top_forecolor' => vogo_brand_option_get('general_top_forecolor', '#ffffff'),
        'company_name' => vogo_brand_option_get('company_name'),
        'company_fiscal_code' => vogo_brand_option_get('company_fiscal_code'),
        'company_address' => vogo_brand_option_get('company_address'),
        'company_payment_bank_account' => vogo_brand_option_get('company_payment_bank_account'),
        'company_swift_code' => vogo_brand_option_get('company_swift_code'),
        'company_license_code' => vogo_brand_option_get('company_license_code'),
        'brand_activation_code' => vogo_brand_option_get('brand_activation_code'),
        'ecommerce_delivery_cost' => vogo_brand_option_get('ecommerce_delivery_cost'),
        'ecommerce_currency' => vogo_brand_option_get('ecommerce_currency', 'RON'),
        'ecommerce_minimum_order_value' => vogo_brand_option_get('ecommerce_minimum_order_value'),
        'ecommerce_about_us_text' => vogo_brand_option_get('ecommerce_about_us_text'),
        'Push_message_scroll_mobile_app' => vogo_brand_option_get('Push_message_scroll_mobile_app'),
        'market_pickup_address' => vogo_brand_option_get('market_pickup_address'),
        'market_pickup_city' => vogo_brand_option_get('market_pickup_city'),
        'market_pickup_street' => vogo_brand_option_get('market_pickup_street'),
        'market_pickup_number' => vogo_brand_option_get('market_pickup_number'),
        'market_pickup_details' => vogo_brand_option_get('market_pickup_details'),
        'market_pickup_lat' => vogo_brand_option_get('market_pickup_lat'),
        'market_pickup_long' => vogo_brand_option_get('market_pickup_long'),
    ];

    $api_url = home_url();
    $qr_src = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . rawurlencode($api_url);
    // Build reusable support links for admin CTAs rendered on this page.
    $support_number = defined('VOGO-WHATSAPPP-SUPPORT') ? constant('VOGO-WHATSAPPP-SUPPORT') : '+40723313296';
    $support_number_digits = preg_replace('/\D+/', '', $support_number);
    $support_message = 'VOGO plugin support message - client name: _______ issue: _________';
    $support_url = 'https://wa.me/' . $support_number_digits . '?text=' . rawurlencode($support_message);

    echo '<div class="wrap vogo-brand-options">';
    echo '<h1>VOGO WooCommerce – Brand Control Center</h1>';

    $reminder_class = ' vogo-version-reminder--hidden';
    echo '<div class="notice notice-info vogo-version-reminder' . esc_attr($reminder_class) . '" data-reminder-scope="brand-options"><p>You updated configuration: Do not forget to change version to next one X.X.X in order to push to your mobile application.</p></div>';
    if (!empty($_GET['updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    echo '<div class="vogo-brand-hero">';
    echo '<div class="vogo-brand-hero-info">';
    echo '<span class="vogo-brand-label">API URL (current site)</span>';
    echo '<h2>' . esc_html($api_url) . '</h2>';
    echo '<p>Scan the QR code to pair the mobile app quickly.</p>';
    echo '<p class="vogo-brand-links-inline">Powered by: <a href="https://vogo.me" target="_blank" rel="noreferrer">vogo.me</a> | <a href="https://vogo.family" target="_blank" rel="noreferrer">vogo.family</a></p>';
    echo '<p>Version = ' . esc_html(VOGO_PLUGIN_VERSION) . '</p>';
    echo '</div>';
    echo '<div class="vogo-brand-hero-center">';
    echo '<div class="vogo-brand-hero-title">VOGO eCommerce native mobile apps</div>';
    echo '<div class="vogo-brand-hero-stores">';
    echo '<a href="https://apps.apple.com/ro/" target="_blank" rel="noreferrer"><img src="https://vogo.family/wp-content/uploads/2026/02/apples-store.png" alt="App Store" decoding="async" /></a>';
    echo '<a href="https://play.google.com/store/apps?hl=ro" target="_blank" rel="noreferrer"><img src="https://vogo.family/wp-content/uploads/2026/02/google-play-1.webp" alt="Google Play" decoding="async" /></a>';
    echo '</div>';
    // Group Version history and support CTAs inside the mobile apps panel for quick access.
    echo '<a class="button vogo-brand-hero-shortcut vogo-brand-hero-shortcut--center" href="' . esc_url(admin_url('admin.php?page=vogo-brand-version-history')) . '" target="_blank" rel="noopener noreferrer" title="Open brand version history and restore options.">Version history</a>';
    echo '<a class="button vogo-brand-hero-shortcut vogo-brand-hero-shortcut--center" href="' . esc_url($support_url) . '" target="_blank" rel="noreferrer" title="Contact live WhatsApp support.">Contact live support</a>';
    echo '<a class="button vogo-brand-hero-shortcut vogo-brand-hero-shortcut--center" href="https://vogo.me/ecomm-contact-request/" target="_blank" rel="noreferrer" title="Open the VOGO contact request form.">Contact VOGO</a>';
    echo '</div>';
    echo '<div class="vogo-brand-hero-qr">';
    echo '<img src="' . esc_url($qr_src) . '" alt="QR API URL" />';
    echo '</div>';
    echo '</div>';

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vogo-brand-form">';
    wp_nonce_field('vogo_brand_options_save');
    echo '<input type="hidden" name="action" value="vogo_brand_options_save" />';

    echo '<div class="vogo-brand-grid">';
    $animate_plugin_check = !empty($_GET['vogo-plugin-check']);

    echo '<div class="vogo-brand-card vogo-brand-check-card">';
    echo '<h3><span class="dashicons dashicons-admin-links" aria-hidden="true"></span>General Settings</h3>';
    echo '<div class="vogo-brand-links">';
    // Render endpoint/link settings shown in the General Settings card.
    $links = [
        'brand_prod_endpoint_url',
        'splash_image_top',
        'link_new_product_request',
        'link_register',
        'link_new_product_recommend',
        'link_login',
        'link_forgot_password',
        'link_policy',
        'link_terms_conditions',
    ];
    foreach ($links as $key) {
        $external = $values[$key];
        echo '<div class="vogo-brand-link">';
        vogo_brand_options_render_label($definitions[$key]['label'], $definitions[$key]['description']);
        $show_open_button = $key !== 'brand_prod_endpoint_url' && (!empty($external) || in_array($key, ['brand_icon', 'splash_image_top', 'link_forgot_password', 'link_terms_conditions'], true));
        if ($key === 'brand_icon') {
            echo '<div class="vogo-brand-link-row vogo-brand-link-row--with-preview">';
            echo '<div class="vogo-brand-link-input">';
            echo '<input type="url" name="' . esc_attr($key) . '" value="' . esc_attr($external) . '" placeholder="https://..." />';
            echo '<div class="vogo-brand-link-actions">';
            if ($show_open_button) {
                if (!empty($external)) {
                    echo '<a class="button button-secondary vogo-brand-link-open" href="' . esc_url($external) . '" target="_blank" rel="noreferrer" aria-label="Open link" title="Open link">...</a>';
                } 
else {
                    echo '<button type="button" class="button button-secondary vogo-brand-link-open" disabled aria-disabled="true" title="Add a URL to open">...</button>';
                }
            }
            echo '</div>';
            echo '</div>';
            if (!empty($external)) {
                echo '<div class="vogo-brand-link-preview">';
                echo '<img class="vogo-brand-preview-inline" src="' . esc_url($external) . '" alt="' . esc_attr($definitions[$key]['label']) . '" />';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<div class="vogo-brand-link-row">';
            echo '<input type="url" name="' . esc_attr($key) . '" value="' . esc_attr($external) . '" placeholder="https://..." />';
            echo '<div class="vogo-brand-link-actions">';
            if ($show_open_button) {
                if (!empty($external)) {
                    echo '<a class="button button-secondary vogo-brand-link-open" href="' . esc_url($external) . '" target="_blank" rel="noreferrer" aria-label="Open link" title="Open link">...</a>';
                } else {
                    echo '<button type="button" class="button button-secondary vogo-brand-link-open" disabled aria-disabled="true" title="Add a URL to open">...</button>';
                }
            }
            echo '</div>';
            echo '</div>';
            if ($key === 'splash_image_top' && !empty($external)) {
                echo '<img class="vogo-brand-preview" src="' . esc_url($external) . '" alt="' . esc_attr($definitions[$key]['label']) . '" />';
            }
        }
        echo '</div>';
    }
    // Shortcut button to open the dedicated JWT configuration module.
    echo '<div class="vogo-brand-link">';
    echo '<label class="vogo-brand-label">JWT authentication</label>';
    echo '<div class="vogo-brand-link-row">';
    echo '<div class="vogo-brand-link-input">Configure JWT security module from a dedicated settings page.</div>';
    echo '<div class="vogo-brand-link-actions">';
    echo '<a class="button button-secondary vogo-brand-button--vogo" href="' . esc_url(admin_url('admin.php?page=vogo-jwt-secure')) . '">JWT Secure</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--two vogo-brand-link-colors">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['general_buttons_bkcolor']['label'], $definitions['general_buttons_bkcolor']['description']);
    echo '<input type="color" name="general_buttons_bkcolor" value="' . esc_attr($values['general_buttons_bkcolor']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['general_buttons_forecolor']['label'], $definitions['general_buttons_forecolor']['description']);
    echo '<input type="color" name="general_buttons_forecolor" value="' . esc_attr($values['general_buttons_forecolor']) . '" />';
    echo '</div>';
    echo '</div>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--two vogo-brand-link-colors">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['general_top_bkcolor']['label'], $definitions['general_top_bkcolor']['description']);
    echo '<input type="color" name="general_top_bkcolor" value="' . esc_attr($values['general_top_bkcolor']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['general_top_forecolor']['label'], $definitions['general_top_forecolor']['description']);
    echo '<input type="color" name="general_top_forecolor" value="' . esc_attr($values['general_top_forecolor']) . '" />';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="vogo-brand-card">';
    echo '<h3><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>Other settings</h3>';
    echo '<p class="vogo-brand-other-actions">';
    echo '<a class="button button-secondary vogo-brand-button--vogo" href="' . esc_url(admin_url('admin.php?page=vogo-view-logs')) . '">View logs</a>';
    echo '<a class="button button-secondary vogo-brand-button--vogo" href="' . esc_url(admin_url('admin.php?page=vogo-mobile-categories')) . '">Mobile app categories</a>';
    echo '<a class="button button-secondary vogo-brand-button--vogo" href="' . esc_url(admin_url('admin.php?page=vogo-user-roles')) . '">User-Roles</a>';
    echo '</p>';
    echo '<div class="vogo-brand-other-settings-fields">';
    echo '<div class="vogo-brand-subsection">';
    echo '<h4>Brand settings</h4>';
    echo '<div class="vogo-brand-inline-fields">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['brand_name']['label'], $definitions['brand_name']['description']);
    echo '<input type="text" name="brand_name" value="' . esc_attr($values['brand_name']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['brand_version']['label'], $definitions['brand_version']['description']);
    echo '<input type="text" name="brand_version" value="' . esc_attr($values['brand_version']) . '" />';
    echo '</div>';
    echo '</div>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--two">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['brand_splash_bkcolor']['label'], $definitions['brand_splash_bkcolor']['description']);
    echo '<input type="color" name="brand_splash_bkcolor" value="' . esc_attr($values['brand_splash_bkcolor']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['brand_splash_forecolor']['label'], $definitions['brand_splash_forecolor']['description']);
    echo '<input type="color" name="brand_splash_forecolor" value="' . esc_attr($values['brand_splash_forecolor']) . '" />';
    echo '</div>';
    echo '</div>';
    vogo_brand_options_render_label($definitions['brand_icon']['label'], $definitions['brand_icon']['description']);
    echo '<div class="vogo-brand-url-row">';
    echo '<input type="url" name="brand_icon" value="' . esc_attr($values['brand_icon']) . '" placeholder="https://..." />';
    echo '<div class="vogo-brand-url-actions">';
    echo '<button type="button" class="button button-secondary vogo-brand-media-picker" data-target="brand_icon" title="Select image">...</button>';
    echo '</div>';
    echo '</div>';
    if (!empty($values['brand_icon'])) {
        echo '<img class="vogo-brand-preview" src="' . esc_url($values['brand_icon']) . '" alt="' . esc_attr($definitions['brand_icon']['label']) . '" />';
    }
    echo '</div>';
    echo '<div class="vogo-brand-subsection">';
    echo '<h4>Splash screen</h4>';
    vogo_brand_options_render_label($definitions['splash_text_down']['label'], $definitions['splash_text_down']['description']);
    echo '<input type="text" name="splash_text_down" value="' . esc_attr($values['splash_text_down']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-subsection">';
    echo '<h4>Login screen</h4>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--two">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['login_hero_text_h1']['label'], $definitions['login_hero_text_h1']['description']);
    echo '<input type="text" name="login_hero_text_h1" value="' . esc_attr($values['login_hero_text_h1']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['login_hero_text_h2']['label'], $definitions['login_hero_text_h2']['description']);
    echo '<input type="text" name="login_hero_text_h2" value="' . esc_attr($values['login_hero_text_h2']) . '" />';
    echo '</div>';
    echo '</div>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--three">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['login_hero_bkcolor']['label'], $definitions['login_hero_bkcolor']['description']);
    echo '<input type="color" name="login_hero_bkcolor" value="' . esc_attr($values['login_hero_bkcolor']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['login_hero_forecolor']['label'], $definitions['login_hero_forecolor']['description']);
    echo '<input type="color" name="login_hero_forecolor" value="' . esc_attr($values['login_hero_forecolor']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['login_hero_h1_size']['label'], $definitions['login_hero_h1_size']['description']);
    echo '<select name="login_hero_h1_size" class="vogo-brand-select--quarter">';
    foreach (['16', '18', '20', '22', '24', '26', '28', '30', '35', '40', '45', '50'] as $size) {
        $selected = selected($values['login_hero_h1_size'], $size, false);
        echo '<option value="' . esc_attr($size) . '"' . $selected . '>' . esc_html($size) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';
    vogo_brand_options_render_label($definitions['login_top_image']['label'], $definitions['login_top_image']['description']);
    echo '<div class="vogo-brand-url-row">';
    echo '<input type="url" name="login_top_image" value="' . esc_attr($values['login_top_image']) . '" placeholder="https://..." />';
    echo '<div class="vogo-brand-url-actions">';
    echo '<button type="button" class="button button-secondary vogo-brand-media-picker" data-target="login_top_image" title="Select image">...</button>';
    echo '</div>';
    echo '</div>';
    if (!empty($values['login_top_image'])) {
        echo '<img class="vogo-brand-preview" src="' . esc_url($values['login_top_image']) . '" alt="' . esc_attr($definitions['login_top_image']['label']) . '" />';
    }
    echo '</div>';
    echo '<div class="vogo-brand-subsection">';
    echo '<h4>Register with screen</h4>';
    vogo_brand_options_render_label($definitions['register_screen_top_small_text']['label'], $definitions['register_screen_top_small_text']['description']);
    echo '<input type="text" name="register_screen_top_small_text" value="' . esc_attr($values['register_screen_top_small_text']) . '" />';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    $plugin_check_log = vogo_brand_option_get('vogo_plugin_check_log');
    $default_check_message = 'Press above button in order to start automatick self-checking for vogo plugin database structure self diagnosis and repair...';
    $plugin_check_log_display = $animate_plugin_check ? $plugin_check_log : '';
    if (!$animate_plugin_check && trim($plugin_check_log) === '') {
        $plugin_check_log_display = $default_check_message;
    }
    $plugin_check_tables = function_exists('vogo_brand_options_get_db_check_section_names_from_spec') ? vogo_brand_options_get_db_check_section_names_from_spec() : [];

    echo '<div class="vogo-brand-card vogo-brand-diagnostics">';
    echo '<h3><span class="dashicons dashicons-shield" aria-hidden="true"></span>Vogo plugin self diagnostics tool</h3>';
    /** Diagnostics action menu: keep one primary CTA and reveal advanced choices on hover/click. */
    echo '<div class="vogo-brand-check-actions" id="vogo-plugin-check-menu">';
    echo '<input type="hidden" id="vogo-plugin-check-mode" name="vogo_plugin_check_mode" form="vogo-plugin-check-form" value="full" />';
    echo '<input type="hidden" id="vogo-plugin-check-table" name="vogo_plugin_check_table" form="vogo-plugin-check-form" value="" />';
    echo '<button type="button" class="button" id="vogo-plugin-check-button" data-default-label="Vogo plugin check" data-busy-label="Stop checking ..." aria-haspopup="true" aria-expanded="false">Vogo plugin check</button>';
    echo '<div class="vogo-plugin-check-menu-panel" id="vogo-plugin-check-options" aria-hidden="true">';
    echo '<div class="vogo-plugin-check-option vogo-plugin-check-option--table">';
    echo '<label for="vogo-plugin-check-table-combobox" class="screen-reader-text">Choose section from vogo.xml</label>';
    echo '<div class="vogo-plugin-check-table-autocomplete">';
    echo '<input type="text" id="vogo-plugin-check-table-combobox" class="vogo-plugin-check-table-combobox" placeholder="Choose section..." autocomplete="off" />';
    echo '<div class="vogo-plugin-check-table-list" id="vogo-plugin-check-table-list" role="listbox" aria-label="Available sections"></div>';
    echo '</div>';
    echo '<datalist id="vogo-plugin-check-table-source">';
    foreach ($plugin_check_tables as $plugin_check_table_name) {
        echo '<option value="' . esc_attr($plugin_check_table_name) . '"></option>';
    }
    echo '</datalist>';
    echo '<button type="button" class="button vogo-plugin-check-option vogo-plugin-check-option--icon" data-check-mode="table" title="Run selected section" aria-label="Run selected section">&gt;</button>';
    echo '</div>';
    echo '<button type="button" class="button vogo-plugin-check-option" data-check-mode="full">Run full diagnostics</button>';
    echo '<button type="button" class="button vogo-plugin-check-option" data-check-mode="activation">Check activation</button>';
    echo '</div>';
    // Support CTAs were moved near the mobile apps block (next to Version history) to keep diagnostics focused on checks/logs.
    echo '<button type="button" class="button button-secondary vogo-brand-copy-log" title="Copy to clipboard" aria-label="Copy to clipboard"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span></button>';
    echo '</div>';
    echo '<label>Vogo plugin check logs</label>';
    echo '<textarea class="vogo-brand-log" readonly rows="10" data-full-log="' . esc_attr($plugin_check_log) . '" data-animate="' . ($animate_plugin_check ? '1' : '0') . '">' . esc_textarea($plugin_check_log_display) . '</textarea>';
    echo '</div>';
    echo '</div>';

    echo '<div class="vogo-brand-grid">';
    echo '<div class="vogo-brand-card">';
    echo '<h3><span class="dashicons dashicons-id-alt" aria-hidden="true"></span>Company data</h3>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--wide-narrow">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label('Company name');
    echo '<input type="text" name="company_name" value="' . esc_attr($values['company_name']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label('Fiscal code');
    echo '<input type="text" name="company_fiscal_code" value="' . esc_attr($values['company_fiscal_code']) . '" />';
    echo '</div>';
    echo '</div>';
    vogo_brand_options_render_label('Company address');
    echo '<textarea name="company_address" rows="3">' . esc_textarea($values['company_address']) . '</textarea>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--wide-narrow">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label('Payment bank account');
    echo '<input type="text" name="company_payment_bank_account" value="' . esc_attr($values['company_payment_bank_account']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label('SWIFT code');
    echo '<input type="text" name="company_swift_code" value="' . esc_attr($values['company_swift_code']) . '" />';
    echo '</div>';
    echo '</div>';
    echo '<div class="vogo-brand-inline-fields vogo-brand-inline-fields--wide-narrow">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['company_license_code']['label'], $definitions['company_license_code']['description']);
    echo '<input type="text" name="company_license_code" value="' . esc_attr($values['company_license_code']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label($definitions['brand_activation_code']['label'], $definitions['brand_activation_code']['description']);
    echo '<input type="text" name="brand_activation_code" value="' . esc_attr($values['brand_activation_code']) . '" />';
    echo '</div>';
    echo '</div>';
    vogo_brand_options_render_label($definitions['market_pickup_address']['label'], $definitions['market_pickup_address']['description']);
    // Anchor target used by the eCommerce quick action for configuring local pickup.
    echo '<table id="vogo-brand-pickup-settings-alt" class="vogo-brand-pickup-table">';
    echo '<tr>';
    echo '<td><label for="market_pickup_city_2">City</label></td>';
    echo '<td><label for="market_pickup_street_2">Street</label></td>';
    echo '<td><label for="market_pickup_number_2">Number</label></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><input id="market_pickup_city_2" type="text" name="market_pickup_city" maxlength="100" value="' . esc_attr($values['market_pickup_city']) . '" /></td>';
    echo '<td><input id="market_pickup_street_2" type="text" name="market_pickup_street" maxlength="120" value="' . esc_attr($values['market_pickup_street']) . '" /></td>';
    echo '<td><input id="market_pickup_number_2" type="text" name="market_pickup_number" maxlength="20" value="' . esc_attr($values['market_pickup_number']) . '" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><label for="market_pickup_details_2" class="vogo-brand-label-regular">Address details</label></td>';
    echo '<td><label for="market_pickup_lat_2">Latitude</label></td>';
    echo '<td><label for="market_pickup_long_2">Longitude</label></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td><input id="market_pickup_details_2" type="text" name="market_pickup_details" maxlength="255" value="' . esc_attr($values['market_pickup_details']) . '" /></td>';
    echo '<td><input id="market_pickup_lat_2" type="text" name="market_pickup_lat" maxlength="20" value="' . esc_attr($values['market_pickup_lat']) . '" /></td>';
    echo '<td><input id="market_pickup_long_2" type="text" name="market_pickup_long" maxlength="20" value="' . esc_attr($values['market_pickup_long']) . '" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td colspan="3"><a href="https://www.latlong.net/" target="_blank" rel="noreferrer">Finder: https://www.latlong.net/</a></td>';
    echo '</tr>';
    echo '</table>';
    echo '<div class="vogo-brand-actions-top">';
    echo '<button type="button" class="button">Validate</button>';
    echo '</div>';
    echo '</div>';

    echo '<div class="vogo-brand-card">';
    echo '<h3><span class="dashicons dashicons-info-outline" aria-hidden="true"></span>eCommerce options</h3>';
    echo '<div class="vogo-brand-other-actions"><button type="button" class="button button-secondary vogo-setup-delivery-point" data-target-id="vogo-brand-pickup-settings-alt">Setup delivery point</button><a class="button button-secondary vogo-brand-button--vogo" href="' . esc_url(admin_url('admin.php?page=vogo-push-messages')) . '">Push messages to clients</a></div>';
    echo '<div class="vogo-brand-inline-fields">';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label('Delivery cost');
    echo '<input type="text" name="ecommerce_delivery_cost" value="' . esc_attr($values['ecommerce_delivery_cost']) . '" />';
    echo '</div>';
    echo '<div class="vogo-brand-field">';
    vogo_brand_options_render_label('Dropdown currency');
    echo '<select name="ecommerce_currency" class="vogo-brand-select--currency">';
    $currency_options = [
        'AED', 'AUD', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EUR', 'GBP',
        'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'JPY', 'KRW', 'MXN', 'MYR', 'NOK',
        'NZD', 'PHP', 'PLN', 'RON', 'SEK', 'SGD', 'THB', 'TRY', 'UAH', 'USD',
    ];
    foreach ($currency_options as $currency_code) {
        $selected = selected($values['ecommerce_currency'], $currency_code, false);
        echo '<option value="' . esc_attr($currency_code) . '"' . $selected . '>' . esc_html($currency_code) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';
    vogo_brand_options_render_label('Payment processing fee 3%');
    vogo_brand_options_render_label('Minimum order value');
    echo '<input type="text" name="ecommerce_minimum_order_value" value="' . esc_attr($values['ecommerce_minimum_order_value']) . '" />';
    vogo_brand_options_render_label($definitions['Push_message_scroll_mobile_app']['label'], $definitions['Push_message_scroll_mobile_app']['description']);
    echo '<input type="text" name="Push_message_scroll_mobile_app" value="' . esc_attr($values['Push_message_scroll_mobile_app']) . '" />';
    vogo_brand_options_render_label('About us text');
    wp_editor(
        $values['ecommerce_about_us_text'],
        'ecommerce_about_us_text',
        [
            'textarea_name' => 'ecommerce_about_us_text',
            'textarea_rows' => 8,
            'media_buttons' => false,
            'teeny' => false,
        ]
    );
    echo '<div class="vogo-brand-actions-top vogo-brand-actions-center">';
    echo '<button type="submit" class="button button-primary">Save</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="vogo-brand-actions">';
    echo '<button type="submit" class="button button-primary">Save settings</button>';
    echo '</div>';
    echo '</form>';

    echo '<form id="vogo-plugin-check-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('vogo_plugin_check');
    echo '<input type="hidden" name="action" value="vogo_plugin_check" />';
    echo '</form>';

    echo '</div>';

    echo '<style>
        .vogo-brand-options h1 { margin-bottom: 16px; }
        .vogo-version-reminder { border-left-color: #0e7f4e; background: #ecfdf3; color: #ff0000; font-weight: 600; }
        .vogo-version-reminder--hidden { display: none; }
        .vogo-version-reminder p { margin: 0; font-size: 14px; }
        .vogo-brand-options .notice.notice-success {
            background: #0e7f4e;
            border-left-color: #0e7f4e;
            color: #ffffff;
            box-shadow: 0 8px 16px rgba(14, 127, 78, 0.25);
        }
        .vogo-brand-options .notice.notice-success p { margin: 0; color: #ffffff; font-weight: 700; }
        .vogo-brand-options .notice.notice-success .notice-dismiss:before { color: #ffffff; }
        .vogo-brand-hero { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); align-items: start; gap: 24px; background: #0e7f4e; color: #fff; padding: 24px; border-radius: 16px; margin-bottom: 24px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.2); }
        .vogo-brand-hero-info { text-align: left; }
        .vogo-brand-hero-info h2 { margin: 8px 0 0; font-size: 22px; color: #e2e8f0; }
        .vogo-brand-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.12em; color: #94a3b8; }
        .vogo-brand-links-inline { margin: 8px 0 0; }
        .vogo-brand-links-inline a { color: #ffffff; text-decoration: underline; }
        .vogo-brand-hero-center { text-align: center; display: grid; gap: 12px; justify-items: center; width: 100%; align-self: start; }
        .vogo-brand-hero-title { font-size: 36px; font-weight: 700; color: #ffffff; text-align: center; width: 100%; }
        .vogo-brand-hero-stores { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; align-items: center; }
        .vogo-brand-hero-stores a { background: transparent; border: 0; padding: 0; border-radius: 0; display: inline-flex; align-items: center; justify-content: center; }
        .vogo-brand-hero-stores img { display: block; width: 190px; height: 64px; object-fit: contain; }
        .vogo-brand-hero-qr { grid-column: 3; grid-row: 1 / span 2; justify-self: end; align-self: start; text-align: right; }
        .vogo-brand-hero-qr img { background: #fff; padding: 12px; border-radius: 16px; }
        .vogo-brand-hero-qr-text { margin: 8px 0 0; font-weight: 700; color: #ffffff; word-break: break-word; }
        .vogo-brand-hero-qr-note { margin: 4px 0 0; color: #e2e8f0; }
        .vogo-brand-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .vogo-brand-card { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08); }
        .vogo-brand-card h3 { margin-top: 0; display: flex; gap: 8px; align-items: center; }
        .vogo-brand-card h3 .dashicons { color: #0f172a; }
        .vogo-brand-links { display: grid; gap: 8px; }
        .vogo-brand-link { display: flex; flex-direction: column; gap: 6px; padding: 8px; border-radius: 12px; background: #fff; }
        .vogo-brand-link-row { display: flex; gap: 10px; align-items: center; }
        .vogo-brand-link-row--with-preview { align-items: center; }
        .vogo-brand-link-input { display: flex; gap: 10px; align-items: center; flex: 1 1 70%; min-width: 0; }
        .vogo-brand-link-input input { flex: 1; min-width: 0; }
        .vogo-brand-link-preview { flex: 0 0 30%; display: flex; justify-content: flex-end; }
        .vogo-brand-link-actions { display: flex; align-items: center; }
        .vogo-brand-link label { font-weight: 600; margin: 0 0 4px; }
        .vogo-brand-link input { width: 100%; padding: 8px 10px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .vogo-brand-link-open { min-width: 32px; padding: 0 8px; height: 32px; line-height: 30px; text-align: center; }
        .vogo-brand-url-row { display: flex; gap: 10px; align-items: center; }
        .vogo-brand-url-row input { flex: 1; min-width: 0; }
        .vogo-brand-url-actions { display: flex; align-items: center; flex-shrink: 0; }
        .vogo-brand-other-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
        .vogo-brand-button--vogo { background: #ffffff; border-color: #0e7f4e; color: #0e7f4e; }
        .vogo-brand-button--vogo:hover,
        .vogo-brand-button--vogo:focus { background: #f0fdf4; border-color: #0b6b42; color: #0b6b42; }
        .vogo-brand-other-settings-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-top: 16px; }
        .vogo-brand-inline-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .vogo-brand-inline-fields--wide-narrow { grid-template-columns: minmax(0, 3fr) minmax(0, 1fr); }
        .vogo-brand-inline-fields--two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .vogo-brand-inline-fields--three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .vogo-brand-pickup-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .vogo-brand-pickup-table td { padding: 4px 6px; vertical-align: top; }
        .vogo-brand-pickup-table label { margin: 0; display: inline-block; }
        .vogo-brand-pickup-table input { width: 100%; }
        .vogo-brand-pickup-table.is-targeted { box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.25); border-radius: 8px; transition: box-shadow 0.2s ease-in-out; }
        .vogo-brand-label-regular { font-weight: 400 !important; }
        .vogo-brand-field { display: flex; flex-direction: column; }
        .vogo-brand-subsection { background: transparent; border-radius: 12px; padding: 16px; border: 1px solid #e2e8f0; }
        .vogo-brand-subsection h4 { margin: 0 0 12px; font-size: 16px; }
        .vogo-brand-form label { display: block; font-weight: 600; margin: 12px 0 6px; }
        .vogo-brand-form input[type="text"],
        .vogo-brand-form input[type="url"],
        .vogo-brand-form textarea,
        .vogo-brand-form select { width: 100%; padding: 8px 10px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .vogo-brand-form .vogo-brand-select--currency { width: 120px; max-width: 100%; }
        .vogo-brand-form .vogo-brand-select--quarter { width: 25%; min-width: 72px; }
        .vogo-brand-form input[type="color"] { height: 40px; width: 80px; border: none; background: transparent; }
        .vogo-brand-preview { margin-top: 12px; max-width: 120px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .vogo-brand-preview-inline { max-width: 100%; height: auto; border-radius: 12px; border: 1px solid #e2e8f0; }
        .vogo-brand-link-colors { margin-top: 12px; }
        .vogo-brand-actions { margin-top: 20px; }
        .vogo-brand-actions-top { margin-bottom: 20px; display: flex; justify-content: flex-end; }
        .vogo-brand-actions-center { justify-content: center; }
        .vogo-muted { color: #64748b; font-size: 13px; }
        .vogo-brand-metric { display: grid; gap: 6px; font-size: 14px; color: #0f172a; }
        /* Diagnostics actions: keep one clean CTA and reveal contextual options as an attached panel. */
        .vogo-brand-check-actions { position: relative; display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px; align-items: center; }
        .vogo-plugin-check-menu-panel {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            z-index: 20;
            min-width: 320px;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            box-shadow: 0 14px 28px rgba(14, 127, 78, 0.16);
        }
        .vogo-brand-check-actions.is-expanded .vogo-plugin-check-menu-panel,
        .vogo-brand-check-actions:focus-within .vogo-plugin-check-menu-panel { display: grid; gap: 10px; }
        .vogo-plugin-check-option--table { display: grid; grid-template-columns: minmax(0, 80%) auto; gap: 8px; align-items: center; }
        .vogo-plugin-check-table-autocomplete { position: relative; width: 100%; }
        .vogo-plugin-check-table-combobox {
            width: 100%;
            min-height: 40px;
            border-radius: 5px;
            border: 1px solid #0e7f4e;
            background: #ffffff;
            color: #0e7f4e;
            box-sizing: border-box;
        }
        .vogo-plugin-check-table-list {
            display: none;
            position: absolute;
            top: calc(100% + 2px);
            left: 0;
            right: 0;
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid #0e7f4e;
            border-radius: 5px;
            background: #ffffff;
            z-index: 25;
        }
        .vogo-plugin-check-table-list.is-open { display: block; }
        .vogo-plugin-check-table-item {
            width: 100%;
            text-align: left;
            border: 0;
            background: #ffffff;
            color: #0e7f4e;
            padding: 7px 10px;
            cursor: pointer;
        }
        .vogo-plugin-check-table-item:hover,
        .vogo-plugin-check-table-item:focus {
            background: #f0fdf4;
            color: #0b6b42;
        }
        .vogo-brand-check-actions .button {
            background: #ffffff;
            border: 1px solid #0e7f4e;
            color: #0e7f4e;
            border-radius: 5px;
        }
        .vogo-brand-check-actions .button:hover,
        .vogo-brand-check-actions .button:focus {
            background: #f0fdf4;
            border-color: #0b6b42;
            color: #0b6b42;
        }
        .vogo-plugin-check-option--icon {
            min-width: 34px;
            width: 34px;
            min-height: 40px;
            padding: 0;
            line-height: 1;
            font-weight: 700;
            display: inline-flex;
            justify-content: center;
            align-items: center;
        }
        .vogo-brand-check-actions .vogo-brand-copy-log {
            padding: 0 10px;
            min-width: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .vogo-brand-check-actions .vogo-brand-copy-log .dashicons {
            margin: 0;
        }
        .vogo-brand-check-card { display: flex; flex-direction: column; min-height: calc(100vh - 260px); }
        .vogo-brand-diagnostics { display: flex; flex-direction: column; min-height: 320px; }
        .vogo-brand-diagnostics .vogo-brand-log { flex: 0 0 80%; height: 80%; max-height: 80%; }
        .vogo-brand-log { width: 100%; flex: 1; min-height: 100%; padding: 10px 12px; border-radius: 12px; border: 1px solid #e2e8f0; background: #000 !important; color: #fff !important; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 12px; }
        @media (max-width: 900px) { .vogo-brand-hero { grid-template-columns: 1fr; justify-items: start; } .vogo-brand-hero-center { text-align: left; justify-items: start; } .vogo-brand-hero-qr { grid-column: auto; grid-row: auto; justify-self: start; text-align: left; } }
    </style>';

    echo '<script>
        (function() {
            const logEl = document.querySelector(".vogo-brand-log[data-full-log]");
            const buttonEl = document.getElementById("vogo-plugin-check-button");
            const checkModeEl = document.getElementById("vogo-plugin-check-mode");
            const checkTableEl = document.getElementById("vogo-plugin-check-table");
            const checkTableComboEl = document.getElementById("vogo-plugin-check-table-combobox");
            const checkTableListEl = document.getElementById("vogo-plugin-check-table-list");
            const checkTableSourceEl = document.getElementById("vogo-plugin-check-table-source");
            const checkMenuEl = document.getElementById("vogo-plugin-check-menu");
            const checkMenuPanelEl = document.getElementById("vogo-plugin-check-options");
            const checkOptionEls = document.querySelectorAll(".vogo-plugin-check-option[data-check-mode]");
            const checkFormEl = document.getElementById("vogo-plugin-check-form");
            const reminderEl = document.querySelector(".vogo-version-reminder[data-reminder-scope=\"brand-options\"]");
            const brandForm = document.querySelector(".vogo-brand-form");
            const storageKey = "vogoPluginCheckRequested";
            let activeTimer = null;
            let isAnimating = false;
            /**
             * Diagnostics menu helpers:
             * - keep one main CTA visible by default
             * - reveal full/table options only on click
             * - use one combobox (with browser autofilter) for section selection
             */
            const setMenuExpanded = function(expanded) {
                if (!checkMenuEl || !checkMenuPanelEl || !buttonEl) {
                    return;
                }
                checkMenuEl.classList.toggle("is-expanded", expanded);
                buttonEl.setAttribute("aria-expanded", expanded ? "true" : "false");
                checkMenuPanelEl.setAttribute("aria-hidden", expanded ? "false" : "true");
            };
            const resetDiagnosticsSelection = function() {
                if (checkModeEl) {
                    checkModeEl.value = "full";
                }
                if (checkTableEl) {
                    checkTableEl.value = "";
                }
                if (checkTableComboEl) {
                    checkTableComboEl.value = "";
                }
            };
            const collectSectionOptions = function() {
                if (!checkTableSourceEl) {
                    return [];
                }
                return Array.from(checkTableSourceEl.querySelectorAll("option"))
                    .map(function(optionNode) {
                        return optionNode.value || "";
                    })
                    .filter(function(value) {
                        return value.trim() !== "";
                    });
            };
            const closeSectionDropdown = function() {
                if (!checkTableListEl) {
                    return;
                }
                checkTableListEl.classList.remove("is-open");
                checkTableListEl.innerHTML = "";
            };
            const renderSectionDropdown = function(filterValue) {
                if (!checkTableListEl) {
                    return;
                }
                const normalizedFilter = (filterValue || "").toLowerCase();
                const matches = collectSectionOptions().filter(function(value) {
                    return value.toLowerCase().indexOf(normalizedFilter) !== -1;
                });
                if (!matches.length) {
                    closeSectionDropdown();
                    return;
                }
                checkTableListEl.innerHTML = matches.map(function(value) {
                    return "<button type=\"button\" class=\"vogo-plugin-check-table-item\" role=\"option\" data-table-name=\"" + value.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\"/g, "&quot;") + "\">" + value.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;") + "</button>";
                }).join("");
                checkTableListEl.classList.add("is-open");
            };
            const setupSectionAutocomplete = function() {
                if (!checkTableComboEl || !checkTableListEl) {
                    return;
                }
                checkTableComboEl.addEventListener("focus", function() {
                    renderSectionDropdown(checkTableComboEl.value.trim());
                });
                checkTableComboEl.addEventListener("input", function() {
                    renderSectionDropdown(checkTableComboEl.value.trim());
                });
                checkTableListEl.addEventListener("click", function(event) {
                    const optionButton = event.target.closest(".vogo-plugin-check-table-item");
                    if (!optionButton || !checkTableComboEl) {
                        return;
                    }
                    checkTableComboEl.value = optionButton.dataset.tableName || "";
                    closeSectionDropdown();
                    checkTableComboEl.focus();
                });
                document.addEventListener("click", function(event) {
                    if (!checkMenuPanelEl) {
                        return;
                    }
                    if (checkMenuPanelEl.contains(event.target)) {
                        return;
                    }
                    closeSectionDropdown();
                });
            };
            const submitDiagnostics = function(mode) {
                if (!checkFormEl || !checkModeEl || !checkTableEl || !buttonEl) {
                    return;
                }
                if (mode === "table") {
                    const selectedTable = (checkTableComboEl ? checkTableComboEl.value.trim() : "");
                    if (!selectedTable) {
                        window.alert("Choose a section before running diagnostics.");
                        return;
                    }
                    checkModeEl.value = "table";
                    checkTableEl.value = selectedTable;
                } else if (mode === "activation") {
                    checkModeEl.value = "activation";
                    checkTableEl.value = "";
                } else {
                    checkModeEl.value = "full";
                    checkTableEl.value = "";
                }
                if (logEl) {
                    logEl.value = "";
                    logEl.dataset.fullLog = "";
                    logEl.scrollTop = 0;
                }
                buttonEl.textContent = buttonEl.dataset.busyLabel || "Stop checking ...";
                buttonEl.setAttribute("aria-busy", "true");
                try {
                    sessionStorage.setItem(storageKey, "1");
                } catch (error) {
                    // No-op for storage errors.
                }
                checkFormEl.requestSubmit();
            };
            const showReminder = function() {
                if (reminderEl) {
                    reminderEl.classList.remove("vogo-version-reminder--hidden");
                }
            };
            const stopAnimation = function(showFullLog) {
                if (!logEl) {
                    return;
                }
                if (activeTimer) {
                    window.clearTimeout(activeTimer);
                    activeTimer = null;
                }
                if (showFullLog) {
                    logEl.value = logEl.dataset.fullLog || "";
                    logEl.scrollTop = logEl.scrollHeight;
                }
                isAnimating = false;
                if (buttonEl) {
                    buttonEl.textContent = buttonEl.dataset.defaultLabel || "Vogo plugin check";
                    buttonEl.removeAttribute("aria-busy");
                    buttonEl.disabled = false;
                }
                try {
                    sessionStorage.removeItem(storageKey);
                } catch (error) {
                    // No-op for storage errors.
                }
            };
            resetDiagnosticsSelection();
            setupSectionAutocomplete();
            // Smooth-scroll and briefly highlight pickup fields when the delivery point shortcut is used.
            const setupDeliveryPointButtons = document.querySelectorAll(".vogo-setup-delivery-point[data-target-id]");
            setupDeliveryPointButtons.forEach(function(actionButton) {
                actionButton.addEventListener("click", function() {
                    const targetId = actionButton.getAttribute("data-target-id");
                    const pickupSection = targetId ? document.getElementById(targetId) : null;
                    if (!pickupSection) {
                        return;
                    }
                    pickupSection.scrollIntoView({ behavior: "smooth", block: "start" });
                    pickupSection.classList.add("is-targeted");
                    window.setTimeout(function() {
                        pickupSection.classList.remove("is-targeted");
                    }, 1800);
                });
            });
            if (buttonEl) {
                buttonEl.addEventListener("click", function(event) {
                    event.preventDefault();
                    if (isAnimating) {
                        stopAnimation(true);
                        setMenuExpanded(false);
                        return;
                    }
                    setMenuExpanded(!checkMenuEl || !checkMenuEl.classList.contains("is-expanded"));
                });
            }
            if (checkMenuEl) {
                document.addEventListener("click", function(event) {
                    if (!checkMenuEl.contains(event.target) && !isAnimating) {
                        setMenuExpanded(false);
                    }
                });
            }
            if (checkOptionEls.length) {
                checkOptionEls.forEach(function(optionButton) {
                    optionButton.addEventListener("click", function() {
                        submitDiagnostics(optionButton.dataset.checkMode || "full");
                        setMenuExpanded(false);
                    });
                });
            }
            if (brandForm) {
                brandForm.addEventListener("input", showReminder, { once: true });
                brandForm.addEventListener("change", showReminder, { once: true });
            }
            document.querySelectorAll(".vogo-brand-media-picker").forEach(function(button) {
                button.addEventListener("click", function() {
                    if (!window.wp || !wp.media) {
                        return;
                    }
                    const targetName = button.dataset.target || "";
                    if (!targetName) {
                        return;
                    }
                    const targetInput = brandForm ? brandForm.querySelector("input[name=\"" + targetName + "\"]") : document.querySelector("input[name=\"" + targetName + "\"]");
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
            if (!logEl) {
                return;
            }
            const fullLog = logEl.dataset.fullLog || "";
            const shouldAnimate = logEl.dataset.animate === "1";
            let shouldAutoAnimate = false;
            try {
                shouldAutoAnimate = sessionStorage.getItem(storageKey) === "1";
            } catch (error) {
                shouldAutoAnimate = false;
            }
            if (!shouldAnimate || !shouldAutoAnimate || fullLog.trim() === "") {
                return;
            }
            const lines = fullLog.split("\\n");
            let index = 0;
            logEl.value = "";
            const typeNextLine = function() {
                if (index >= lines.length) {
                    stopAnimation(false);
                    return;
                }
                logEl.value += (index ? "\\n" : "") + lines[index];
                logEl.scrollTop = logEl.scrollHeight;
                index += 1;
                activeTimer = window.setTimeout(typeNextLine, 120);
            };
            if (buttonEl) {
                buttonEl.textContent = buttonEl.dataset.busyLabel || "Stop checking ...";
                buttonEl.setAttribute("aria-busy", "true");
                buttonEl.disabled = false;
            }
            isAnimating = true;
            activeTimer = window.setTimeout(typeNextLine, 200);
        })();
    </script>';
}
