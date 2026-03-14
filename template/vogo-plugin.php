<?php
/*
Plugin Name: Vogo Mobile App Plugin
Plugin URI: https://vogo.me
Description: Core REST API endpoints for Vogo (JWT auth, forum-chat, orders, mobile).
Version: 3.0.1.23
Author: Vogo
Author URI: https://vogo.me
Text Domain: vogo-plugin
Requires at least: 6.0
Requires PHP: 8.1
*/

if (!defined('ABSPATH')) { exit; } // no direct access

// Global variables and constants.
define('VOGO_PLUGIN_VERSION', '3.0.1.23');

// Optional: load Composer autoloader if exists
$autoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($autoload)) { require_once $autoload; }

// Bootstrap
add_action('plugins_loaded', function () {
    // e.g., include your endpoint files here
    $rest_dir = __DIR__ . '/rest';
    if (!is_dir($rest_dir)) {
        return;
    }

    foreach (glob($rest_dir . '/*.php') as $file) {
        if (basename($file) === 'footer.php' && !function_exists('woodmart_get_opt')) {
            continue;
        }

        require_once $file;
    }
});

/**
 * SOCIAL networks: Facebook, Google, Apple
 */

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

define('VOGO_LOG_START', '############ START ########################################');
define('VOGO_LOG_END', '############ END ########################################');
define('VOGO-WHATSAPPP-SUPPORT', '+40723313296');


$rest_dir = plugin_dir_path(__FILE__) . 'rest/';
require_once $rest_dir . 'vogolib.php'; //general libraries / utilities
require_once plugin_dir_path(__FILE__) . 'brand-options.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

add_action('rest_api_init', function () {
    $headers = getallheaders();

  //  error_log('📥 RAW AUTH: ' . print_r($headers['Authorization'] ?? 'NONE', true));    

    if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        $token = $matches[1];

        // 🔒 Secretul tău JWT
        $secret = 'mcFX<|s!tEYt(7vTQFJB}F|Y|6]>/a_W6|vBi-j?7pE>b0-eHuQT;,?5)mY$2ou1';
        if (!defined('VOGO_API_KEY')) define('VOGO_API_KEY', $secret);        

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            if (isset($decoded->user_id)) {
                wp_set_current_user($decoded->user_id);
                error_log('✅ JWT OK. Set user to: ' . $decoded->user_id);
            } else {
                error_log('❌ JWT decoded but no user_id found.');
            }

        } catch (Exception $e) {
            error_log('❌ JWT decode failed: ' . $e->getMessage());
        }

    } else {
       // error_log('❌ Authorization header missing or invalid format.');
    }
});


//include endpoints - routes and definitions on each category / subject
require_once $rest_dir . 'util.php';
require_once $rest_dir . 'push_notifications.php';
require_once $rest_dir . 'chat_agora.php';
require_once $rest_dir . 'login-endpoints.php';
require_once $rest_dir . 'social-endpoints.php';
require_once $rest_dir . 'forum-endpoints.php';
require_once $rest_dir . 'document-endpoints.php';
require_once $rest_dir . 'chatbot-endpoints.php';
require_once $rest_dir . 'general-endpoints.php';
require_once $rest_dir . 'vendor-endpoints.php';
require_once $rest_dir . 'forum-endpoints.php';
require_once $rest_dir . 'chat-endpoints.php';
require_once $rest_dir . 'transporter-endpoints.php';
require_once $rest_dir . 'providers.php';
require_once $rest_dir . 'orders-provider.php';
require_once $rest_dir . 'client-endpoints.php';
require_once $rest_dir . 'activation-endpoints.php';


require_once $rest_dir . 'all-products.php';
require_once $rest_dir . 'all-categories.php';
require_once $rest_dir . 'all-cart.php';
require_once $rest_dir . 'all-checkout.php';
require_once $rest_dir . 'all-account.php';
require_once $rest_dir . 'all-multivendor.php';
require_once $rest_dir . 'all-orders.php';

register_activation_hook(__FILE__, 'vogo_brand_options_install');

//payments
require_once $rest_dir.'vogo-payments-tokens.php';   // baza/vault + DB
require_once $rest_dir.'vogo-payments-netopia.php';  // redirect, IPN, status
require_once $rest_dir.'vogo-payments-stripe.php';  // WooPayments/Stripe extra




require_once $rest_dir . 'import-endpoints.php';



require_once $rest_dir . 'vogo-api-cron.php';

//require_once $rest_dir . 'all-categories.php';

//do not use - is not working
function adi_safe_include($relative_path) {
    $user = get_current_user_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $abs_path = plugin_dir_path(__FILE__) . $relative_path; // full absolute path

    // Log absolute path being used
    vogo_error_log3('[adi-safe-include] STEP 0: Using absolute path: ' . $abs_path . ' | IP: ' . $ip . ' | USER: ' . $user);

    try {
        @include_once $abs_path;
        vogo_error_log3('[adi-safe-include] STEP 2: File included with @include_once: ' . $abs_path . ' | IP: ' . $ip . ' | USER: ' . $user);
    } catch (Throwable $e) {
        vogo_error_log3('[adi-safe-include] STEP 3: Catchable error: ' . $e->getMessage() . ' | FILE: ' . $abs_path . ' | IP: ' . $ip . ' | USER: ' . $user);
    }
}
 //add safe libraries - do not include damaged librarie that are blocking all system
//add_action('plugins_loaded', function () {
//    adi_safe_include('rest/forum-endpoints.php');
 //   adi_safe_include('rest/chat-endpoints.php');    
//});
