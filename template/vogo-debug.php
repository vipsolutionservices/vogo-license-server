<?php


echo "<h3>=== registration_errors ===</h3><pre>";
print_r($wp_filter['registration_errors'] ?? 'NONE');
echo "</pre>";

echo "<h3>=== registration_errors (AFTER REMOVE) ===</h3><pre>";
print_r($wp_filter['registration_errors'] ?? 'NONE');
echo "</pre>";

echo "<h3>=== validate_password_reset (AFTER REMOVE) ===</h3><pre>";
print_r($wp_filter['validate_password_reset'] ?? 'NONE');
echo "</pre>";


// for /test07/wp-admin destination on server
// https://test07.vogo.family/wp-admin/vogo-debug.php 
require_once('../wp-load.php'); // 🚀 bootstrap WordPress fără să incluzi toată consola admin

/*
// 🔐 Doar pentru utilizatori logați și admin
if (!is_user_logged_in() || !current_user_can('administrator')) {
    wp_die('Access denied.'); 
}

echo "<h2>✅ Vogo Debug Panel</h2>";

// 🧠 Listare roluri
global $wp_roles;
if (!isset($wp_roles)) {
    $wp_roles = new WP_Roles();
}
echo "<h3>Defined Roles:</h3><pre>";
print_r(array_keys($wp_roles->roles));
echo "</pre>";

// 🧠 Verificare user_meta
$user_id = get_current_user_id();
echo "<h3>Metadate pentru utilizatorul curent (#$user_id):</h3><pre>";
print_r(get_user_meta($user_id));
echo "</pre>";

// ✅ Alte teste aici...
*/

// STEP 1: check active filters for password length and strength
global $wp_filter;

echo "<pre>";
echo "=== woocommerce_min_password_length ===\n";
print_r($wp_filter['woocommerce_min_password_length'] ?? 'NONE');

echo "\n=== woocommerce_min_password_strength ===\n";
print_r($wp_filter['woocommerce_min_password_strength'] ?? 'NONE');

echo "\n=== validate_password_reset ===\n";
print_r($wp_filter['validate_password_reset'] ?? 'NONE');
echo "</pre>";
?>
