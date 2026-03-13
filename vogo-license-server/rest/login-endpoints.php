<?php

/**
 * File purpose:
 * This file centralizes login and authentication REST endpoints for the Vogo mobile ecosystem,
 * including JWT login flows, OTP verification flows, and user registration/auth support routines.
 */

/**
 * Section: route registration.
 *
 * All login, OTP and profile bootstrap endpoints are registered here so
 * permissions and callback mappings remain in one auditable place.
 */
//login and register
//clear_debug_log_file();
//$MODULE_PHP='login-endpoints.php';

add_action('rest_api_init', function () {

    // Ensure custom tables required by OTP/auth optional flows exist.
    vogo_login_endpoints_ensure_tables();

    register_rest_route('vogo/v1', '/login_jwt_old', [
      //login_jwt was deprecated - for security resons was replaced by /client/login_jwt, /vendor/login_jwt and /transport/login_jwt
      // /login_jwt was replaced with public/login_jwt/ and works only for user: app_mobile_general@vogo.family
        'methods' => 'POST',
        'callback' => 'vogo_api_login_jwt2', //to not be accesible
        'permission_callback' => '__return_true'
    ]);

    /** Public token flow restricted to predefined public app credentials. */
    register_rest_route('vogo/v1', '/public/login_jwt', [ //login_jwt was deprecated - for security resons was replaced by /client/login_jwt, /vendor/login_jwt and /transport/login_jwt
        'methods' => 'POST',
        'callback' => 'vogo_api_login_jwt', 
        'permission_callback' => 'is_allowed_for_obtain_public_token'
    ]);    

    register_rest_route('vogo/v1', '/client/login_jwt', [
        'methods' => 'POST',
        'callback' => 'vogo_api_login_jwt',
        'permission_callback' => 'is_allowed_for_login_client'
    ]);    

    /**
     * Client brand login endpoint (cloned from /client/login_jwt)
     * Authenticates using username + brand_name + pincode + personal_otp_code.
     */
    register_rest_route('vogo/v1', '/client/login_jwt_brand', [
        'methods' => 'POST',
        'callback' => 'vogo_api_login_jwt_brand',
        'permission_callback' => 'is_allowed_for_login_client'
    ]);

    register_rest_route('vogo/v1', '/vendor/login_jwt', [
        'methods' => 'POST',
        'callback' => 'vogo_api_login_jwt',
        'permission_callback' => 'is_allowed_for_login_vendor'
    ]);    
    
    register_rest_route('vogo/v1', '/transport/login_jwt', [
        'methods' => 'POST',
        'callback' => 'vogo_api_login_jwt',
        'permission_callback' => 'is_allowed_for_login_transport'
    ]);        

    /** OTP issuance endpoints used by login recovery/auth flows. */
    register_rest_route('vogo/v1', '/send_otp', [
        'methods' => 'POST',
        'callback' => 'vogo_send_otp',
        'permission_callback' => 'vogo_permission_check'
    ]);

    register_rest_route('vogo/v1', '/verify_otp', [
        'methods' => 'POST',
        'callback' => 'vogo_verify_otp',
        'permission_callback' => 'vogo_permission_check'
    ]);

/**
 * Send OTP to phone or email using Twilio after verifying user existence.
 * Expected JSON parameters:
 * {
 *   "type": "phone", // or "email"
 *   "value": "9876543210" // or "user@example.com"
 * }
 */
    register_rest_route('vogo/v1', '/login_using_otp', [
        'methods' => 'POST',
        'callback' => 'vogo_login_using_otp',
        'permission_callback' => 'vogo_permission_check'
    ]);

/**
 * Verify OTP and authenticate user (JWT token).
 * Expected JSON parameters:
 * {
 *   "type": "phone", // or "email"
 *   "value": "9876543210", // or "user@example.com"
 *   "otp": "123456"
 * }
 */
    register_rest_route('vogo/v1', '/client/login_validate_otp', [
        'methods' => 'POST',
        'callback' => 'vogo_login_validate_otp',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('vogo/v1', '/vendor/login_validate_otp', [
        'methods' => 'POST',
        'callback' => 'vogo_login_validate_otp',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('vogo/v1', '/transporter/login_validate_otp', [
        'methods' => 'POST',
        'callback' => 'vogo_login_validate_otp',
        'permission_callback' => '__return_true'
    ]);    

    /** Registration and email verification routes for mobile roles. */
    //register user minimal data --cu email
register_rest_route('vogo/v1', '/register_user', [
	'methods' => 'POST',
	'callback' => 'vogo_register_user',
	'permission_callback' => 'vogo_permission_check'
]);    

register_rest_route('vogo/v1', '/vendor/vogo_verify_email_otp', [
	'methods' => 'POST',
	'callback' => 'vogo_verify_email_otp',
	'permission_callback' => '__return_true'
]);    
register_rest_route('vogo/v1', '/client/vogo_verify_email_otp', [
	'methods' => 'POST',
	'callback' => 'vogo_verify_email_otp',
	'permission_callback' => '__return_true'
]);    
register_rest_route('vogo/v1', '/transporter/vogo_verify_email_otp', [
	'methods' => 'POST',
	'callback' => 'vogo_verify_email_otp',
	'permission_callback' => '__return_true'
]);    

/*REST API endpoint to update a WooCommerce vendor and store additional business info in a custom table.*/
    register_rest_route('vogo/v1', '/set_user_data', [
        'methods' => 'POST',
        'callback' => 'vogo_update_user_from_jwt',
        'permission_callback' => 'vogo_permission_check'
    ]);
//get company data
register_rest_route('vogo/v1', '/vendor/get_user_data', [
    'methods' => 'POST',
    'callback' => 'vogo_get_user_data_by_jwt',
    'permission_callback' => 'rest_check_jwt_is_user_vendor'
]);    
register_rest_route('vogo/v1', '/client/get_user_data', [
    'methods' => 'POST',
    'callback' => 'vogo_get_user_data_by_jwt',
    'permission_callback' => 'rest_check_jwt_is_user_client'
]);    
register_rest_route('vogo/v1', '/transporter/get_user_data', [
    'methods' => 'POST',
    'callback' => 'vogo_get_user_data_by_jwt',
    'permission_callback' => 'rest_check_jwt_is_user_transporter'
]); 
  

});

function vogo_api_login_jwt(WP_REST_Request $request) {
    global $wpdb;
    $MODULE_PHP="login-endpoints.php";
    $module = $MODULE_PHP . '.vogo_api_login_jwt';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $current_db = DB_NAME;
    vogo_error_log3("STEP 1 - START login | DB:$current_db | IP:$ip | user:unknown", $module);

    // STEP 2 - Extract credentials
    $params = $request->get_json_params();
    $username = sanitize_user($params['username'] ?? '');
    $password = $params['password'] ?? '';


    // STEP 3 - Check if user is generic
    $is_generic_user = strtolower($username) === 'app_mobile_general@vogo.family';
    $pairing_reset_payload = $is_generic_user
        ? ['pairing_reset' => true, 'paired_backend' => null]
        : [];

    // STEP 4 - Detect Bearer token presence
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
    $has_bearer = stripos($auth_header, 'Bearer ') === 0;

    // STEP 5 - Enforce token presence for non-generic users
    if (!$has_bearer && !$is_generic_user) {
        vogo_error_log3("STEP 5 - Bearer required | DB:$current_db | user:$username | IP:$ip", $module);
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Bearer token required for non-generic users.',
            'module_bke' => $module,  
        ], 401);
    }

    // STEP 6 - Rate limit
    $rate_key = 'vogo_rate_limit_login_' . md5($ip);
    if (false !== get_transient($rate_key)) {
        vogo_error_log3("STEP 6 - Rate limit exceeded | IP:$ip", $module);
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Too many attempts. Wait 10 seconds.',
            'module_bke' => $module
        ], 429);
    }
    set_transient($rate_key, 1, 10);

    // STEP 7 - Input validation
    if (empty($username) || empty($password)) {
        vogo_error_log3("STEP 7 - Missing credentials | user:$username | IP:$ip", $module);
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Username and password are required.',
            'module_bke' => $module
        ] + $pairing_reset_payload, 400);
    }

    


    // STEP 8 - Authenticate user
    $user = wp_authenticate($username, $password);
    if (is_wp_error($user)) {
        vogo_error_log3("STEP 8 - Invalid credentials | user:$username | IP:$ip", $module);
        vogo_log_login(null, $username, $ip, 0, 'jwt_login_invalid', 'Invalid credentials');
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Invalid credentials.',
            'module_bke' => $module
        ] + $pairing_reset_payload, 401);
    }

    // STEP 9 - Generate JWT
    $jwt = vogo_generate_jwt($user->ID);
    if (!$jwt || empty($jwt['token'])) {
        vogo_error_log3("STEP 9 - JWT generation failed | user:$username | ID:{$user->ID} | IP:$ip", $module);
        vogo_log_login($user, $username, $ip, 0, 'jwt_gen_fail', 'JWT generation failed');
        return new WP_REST_Response([
            'success' => false,
            'error' => 'JWT generation failed.'
        ] + $pairing_reset_payload, 500);
    }

    // STEP 10 - Log success
    $roles = $user->roles ?? [];
    vogo_log_login($user, $username, $ip, 1, 'jwt_login_success', 'Login successful');
    vogo_error_log3("STEP 10 - Login success | user_id:{$user->ID} | roles:" . implode(',', $roles) . " | IP:$ip", $module);

    // === FINAL CHECK (post-validate route) — înainte de return success ===
    $pv = post_validate_route($user->ID, $request->get_route());
    if ($pv !== true) { return $pv; }

    // STEP 11 - Final response
    return new WP_REST_Response([
        'success'    => true,
        'module_bke' => $module, 
        'user_id'    => $user->ID,
        'username'   => $user->user_login,
        'user_email' => $user->user_email,
        'user_roles' => $roles,
        'token'      => $jwt['token'],
        'expires_in' => $jwt['expire']
    ], 200);
}

/**
 * Brand-based JWT login flow for client users.
 *
 * Expected JSON payload:
 * {
 *   "username": "client@example.com",
 *   "brand_name": "MyBrand",
 *   "pincode": "123456",
 *   "personal_otp_code": "654321"
 * }
 */
function vogo_api_login_jwt_brand(WP_REST_Request $request) {
    global $wpdb;

    $MODULE_PHP = "login-endpoints.php";
    $module = $MODULE_PHP . '.vogo_api_login_jwt_brand';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $current_db = DB_NAME;
    vogo_error_log3("STEP 1 - START brand login | DB:$current_db | IP:$ip | user:unknown", $module);

    // STEP 2 - Extract and sanitize required brand-login credentials.
    $params = $request->get_json_params();
    $username = sanitize_user($params['username'] ?? '');
    $brand_name = sanitize_text_field((string) ($params['brand_name'] ?? ''));
    $pincode = sanitize_text_field((string) ($params['pincode'] ?? ''));
    $personal_otp_code = strtoupper(trim((string) ($params['personal_otp_code'] ?? '')));

    // STEP 3 - Validate mandatory input for this endpoint.
    if ($username === '' || $brand_name === '' || $pincode === '' || $personal_otp_code === '') {
        vogo_error_log3("STEP 3 - Missing brand credentials | user:$username | IP:$ip", $module);
        return new WP_REST_Response([
            'success' => false,
            'error' => 'username, brand_name, pincode and personal_otp_code are required.',
            'module_bke' => $module
        ], 400);
    }

    // STEP 4 - Resolve the user by login or email using the username input.
    $user = get_user_by('login', $username);
    if (!$user) {
        $user = get_user_by('email', $username);
    }

    if (!$user) {
        vogo_error_log3("STEP 4 - User not found | user:$username | IP:$ip", $module);
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Invalid credentials.',
            'module_bke' => $module
        ], 401);
    }

    // STEP 5 - Validate brand + pincode pairing from user_pin_code.
    $pin_table = $wpdb->prefix . 'vogo_user_pin_code';
    $brand_match = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$pin_table} WHERE user_id = %d AND brand_name = %s ORDER BY id DESC LIMIT 1",
        (int) $user->ID,
        $brand_name
    ));

    $pincode_match = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$pin_table} WHERE user_id = %d AND pincode = %s ORDER BY id DESC LIMIT 1",
        (int) $user->ID,
        $pincode
    ));

    $pin_match = $wpdb->get_row($wpdb->prepare(
        "SELECT id, otp_code FROM {$pin_table} WHERE user_id = %d AND brand_name = %s AND pincode = %s ORDER BY id DESC LIMIT 1",
        (int) $user->ID,
        $brand_name,
        $pincode
    ), ARRAY_A);

    if (!$pin_match) {
        vogo_error_log3("STEP 5 - PIN auth failed | user_id:{$user->ID} | brand:$brand_name | IP:$ip", $module);
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Invalid brand_name/pincode for this user.',
            'module_bke' => $module,
            'debug_details' => [
                'brand_name' => [
                    'value' => $brand_name,
                    'found_for_user' => (bool) $brand_match,
                ],
                'pincode' => [
                    'value' => $pincode,
                    'found_for_user' => (bool) $pincode_match,
                ],
                'brand_name_pincode_pair' => [
                    'found_for_user' => (bool) $pin_match,
                ],
            ],
        ], 401);
    }

    // STEP 6 - Validate personal OTP from the same brand/pincode record.
    if (!hash_equals((string) ($pin_match['otp_code'] ?? ''), $personal_otp_code)) {
        vogo_error_log3("STEP 6 - OTP validation failed | user_id:{$user->ID} | IP:$ip", $module);
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Invalid personal_otp_code.',
            'module_bke' => $module
        ], 401);
    }

    // STEP 7 - Generate JWT once all brand login checks passed.
    $jwt = vogo_generate_jwt($user->ID);
    if (!$jwt || empty($jwt['token'])) {
        vogo_error_log3("STEP 7 - JWT generation failed | user_id:{$user->ID} | IP:$ip", $module);
        return new WP_REST_Response([
            'success' => false,
            'error' => 'JWT generation failed.',
            'module_bke' => $module
        ], 500);
    }

    // STEP 8 - Keep endpoint parity with standard login by running route role validation.
    $pv = post_validate_route($user->ID, $request->get_route());
    if ($pv !== true) {
        return $pv;
    }

    // STEP 9 - Return authenticated session payload.
    $roles = $user->roles ?? [];
    return new WP_REST_Response([
        'success' => true,
        'module_bke' => $module,
        'user_id' => $user->ID,
        'username' => $user->user_login,
        'user_email' => $user->user_email,
        'user_roles' => $roles,
        'token' => $jwt['token'],
        'expires_in' => $jwt['expire']
    ], 200);
}

function vogo_login_endpoints_ensure_tables(): void {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = $wpdb->prefix . 'vogo_user_pin_code';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        brand_name VARCHAR(191) NOT NULL,
        pincode VARCHAR(32) NOT NULL,
        otp_code VARCHAR(32) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user_brand_pin (user_id, brand_name, pincode)
    ) {$charset_collate};";

    dbDelta($sql);

    $otp_column_exists = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'otp_code'");
    if ($otp_column_exists === null) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN otp_code VARCHAR(32) DEFAULT NULL AFTER pincode");
    }
}

/**
 * vogo_send_otp
 *
 * Trimite un OTP și loghează acțiunea în tabelul de audit.
 */
function vogo_send_otp(WP_REST_Request $request) {
    global $wpdb;

    // Preia telefonul
    $phone = sanitize_text_field($request->get_param('phone'));

    // Cine e userul conectat, dacă există
    $current_user = wp_get_current_user();

    // IP-ul apelantului
    $ip = vogo_get_client_ip();

    // Inițializează status pentru logare
    $log_success = 0;
    $log_action_info = '';

    // Validează telefon
    if (empty($phone) || !preg_match('/^\+\d{10,15}$/', $phone)) {
        $log_action_info = 'Număr invalid: ' . $phone;

        // Logăm tentativa eșuată
        vogo_log_action(
            $current_user,
            $current_user->exists() ? $current_user->user_login : '',
            $ip,
            $log_success,
            'M-SEND-OTP',
            $log_action_info
        );

        return new WP_REST_Response([
            'success' => false,
            'message' => 'Număr de telefon invalid.'
        ], 400);
    }

    // Generează OTP
    $otp = rand(100000, 999999);

    // Salvează OTP în DB
    $table_name = $wpdb->prefix . 'vogo_otps';
    $wpdb->replace(
        $table_name,
        [
            'phone' => $phone,
            'otp' => $otp,
            'created_at' => current_time('mysql', 1)
        ],
        ['%s', '%d', '%s']
    );

    // Încearcă să trimită OTP prin Twilio
    try {
        $sid = null;
        $token = null;
        $twilio = new Twilio\Rest\Client($sid, $token);

        $message = $twilio->messages->create(
            $phone,
            [
                'from' => 'YourSenderNumber',
                'body' => "Codul tău de verificare este: $otp"
            ]
        );

        $log_success = 1;
        $log_action_info = 'OTP trimis cu succes către ' . $phone;

    } catch (Exception $e) {
        $log_action_info = 'Eroare Twilio: ' . $e->getMessage();

        // Logăm eroarea
        vogo_log_action(
            $current_user,
            $current_user->exists() ? $current_user->user_login : '',
            $ip,
            $log_success,
            'M-SEND-OTP',
            $log_action_info
        );

        return new WP_REST_Response([
            'success' => false,
            'message' => 'Eroare la trimiterea SMS: ' . $e->getMessage()
        ], 500);
    }

    // Logăm succesul
    vogo_log_action(
        $current_user,
        $current_user->exists() ? $current_user->user_login : '',
        $ip,
        $log_success,
        'M-SEND-OTP',
        $log_action_info
    );

    return new WP_REST_Response([
        'success' => true,
        'module_bke' => $module,         
        'message' => 'OTP trimis cu succes.'
    ], 200);
}


function vogo_login_using_otp(WP_REST_Request $request) { 
    global $wpdb;

    //audit for response
  $MODULE_PHP=basename(__FILE__);
  $module = $MODULE_PHP . '.'.__FUNCTION__;
  //end audit for response        

    // 🔐 VOGO_LOG_START
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'; $user_id = 0;
    vogo_error_log3("VOGO_LOG_START | IP: $ip | USER: $user_id");

    // [STEP 0.1] Extract and sanitize payload
    $params = $request->get_json_params();
    $type = sanitize_text_field($params['type'] ?? '');
    $value = sanitize_text_field($params['value'] ?? '');
    vogo_error_log3("[STEP 0.1] Payload extracted: type=$type, value=$value | IP: $ip | USER: $user_id");

    $module=$module.'.type:'.$type;    
    $module=$module.'.value:'.$value;        

    // [STEP 1] Basic validation
    if (!in_array($type, ['phone']) || empty($value)) {
        vogo_error_log3("[STEP 1] Invalid type or empty value | IP: $ip | USER: $user_id");
        return new WP_REST_Response(['success' => false,'module' => $module.'-ES1', 'error' => 'Invalid type or value: phone'], 400);
    }

    // [STEP 2] Try to locate user (by email or phone)
    $user = ($type === 'email') ? get_user_by('email', $value) : vogo_find_user_by_phone($value);
    $user_id = $user ? $user->ID : 0;
    vogo_error_log3("[STEP 2] User search: ID=$user_id | IP: $ip | USER: $user_id");

    // [ADI-ADD] new_user flag (1 if user does not exist yet, 0 if exists)
    $new_user = $user ? 0 : 1;

    // [STEP 2.5] TEST MODE (skip send) — preset OTP 111000
    if(true /* TEST MODE */){
    $otp='111000'; $exp=time()+5*60;
    if($type==='email'){
        if($user_id>0){ update_user_meta($user_id,'vogo_otp_email_code',$otp); update_user_meta($user_id,'vogo_otp_email_expires',$exp); vogo_error_log3("##############SQL: TEST SAVE OTP to usermeta | user_id=$user_id | code=$otp | exp=$exp | IP:$ip | USER:$user_id"); }
        else{ $hash=hash('sha256',strtolower($value)); set_transient('vogo_otp_'.$hash,json_encode(['code'=>$otp,'exp'=>$exp]),5*MINUTE_IN_SECONDS); vogo_error_log3("##############SQL: TEST SAVE OTP to transient | key=vogo_otp_$hash | code=$otp | exp=$exp | IP:$ip | USER:$user_id"); }
    }
    $msg=$user?'OTP (test) ready; Existing user':'OTP (test) ready; New user';
    vogo_error_log3("[STEP 2.5] TEST MODE active — skipping send | type=$type | value=$value | otp=$otp | IP:$ip | USER:$user_id");
    return new WP_REST_Response(['success'=>true,'module' => $module.'.T2.5', 'message'=>$msg,'test_mode'=>true,'userId'=>(int)$user_id,'userName'=>($user?$user->user_login:''),'new_user'=>$new_user],200);
    }

    //check here for production
// [STEP 3] Send OTP (email→own, phone→Twilio)
if ($type === 'email') {
  try{
    $otp = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT); $expires_at = time() + 5*60; $hash = hash('sha256', strtolower($value));
    if ($user_id>0) { $ok1 = update_user_meta($user_id,'vogo_otp_email_code',$otp); $ok2 = update_user_meta($user_id,'vogo_otp_email_expires',$expires_at); vogo_error_log3("##############SQL: SAVE OTP to usermeta | user_id=$user_id | code=$otp | exp=$expires_at | IP: $ip | USER: $user_id"); }
    else { $okT = set_transient('vogo_otp_'.$hash, json_encode(['code'=>$otp,'exp'=>$expires_at]), 5*MINUTE_IN_SECONDS); vogo_error_log3("##############SQL: SAVE OTP to transient | key=vogo_otp_$hash | code=$otp | exp=$expires_at | IP: $ip | USER: $user_id"); }
    $subject = 'Your Vogo verification code'; $body = "Hi,\n\nYour verification code is: $otp\nIt expires in 5 minutes.\n\nIf you didn’t request this, you can ignore this email.\n\n— Vogo Security\nIP: $ip"; $headers = ['Content-Type: text/plain; charset=UTF-8'];
    $sent = wp_mail($value, $subject, $body, $headers);
    if(!$sent){ vogo_error_log3("[STEP 3] wp_mail failed to $value | IP: $ip | USER: $user_id"); return new WP_REST_Response(['success'=>false,'module_bke' => $module, 'error'=>'Failed to send OTP email'],500); }
    vogo_error_log3("[STEP 3] OTP emailed | email=$value | code=$otp | exp=".gmdate('c',$expires_at)." | IP: $ip | USER: $user_id");
  } catch(Throwable $e){
    vogo_error_log3("[STEP 3] Email OTP error: ".$e->getMessage()." | IP: $ip | USER: $user_id");
    return new WP_REST_Response(['success'=>false,'module' => $module.'.ES3','error'=>'Email OTP error','module_bke' => $module, 'details'=>$e->getMessage()],500);
  }
} else {
  try{
    $twilio = new Twilio\Rest\Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
    $twilio->verify->v2->services(TWILIO_VERIFY_SERVICE_SID)->verifications->create($value, "sms");
    vogo_error_log3("[STEP 3] OTP sent via Twilio SMS | phone=$value | IP: $ip | USER: $user_id");
  } catch (Exception $e) {
    vogo_error_log3("[STEP 3] Twilio error: ".$e->getMessage()." | IP: $ip | USER: $user_id");
    return new WP_REST_Response(['success'=>false,$module.'.T3.1TW','module' => $module.'.ESTW1','error'=>$e->getMessage()],500);
  }
}


    // [STEP 4] Return response based on user existence
    $msg = $user ? 'OTP sent successfully; Existing user' : 'OTP sent successfully; New user';

    if ($user_id > 0) {
        if ($type === 'phone') {
            update_user_meta($user_id, 'phone', $value);
            vogo_error_log3("[STEP 4] Phone updated for user ID=$user_id | Value=$value | IP: $ip");
            $msg .= " | Phone updated: $value";
        } elseif ($type === 'email') {
            wp_update_user(['ID' => $user_id, 'user_email' => $value]);
            vogo_error_log3("[STEP 4] Email updated for user ID=$user_id | Value=$value | IP: $ip");
            $msg .= " | Email updated: $value";
        }
    }

    vogo_error_log3("[STEP 4] Final response: $msg | IP: $ip | USER: $user_id");

    // ✅ VOGO_LOG_END
    vogo_error_log3("VOGO_LOG_END | IP: $ip | USER: $user_id");
    $module=$module.'.user_idXXX:'.$user_id;
    return new WP_REST_Response(['success' => true,'module' => $module.'.TEND','module_bke' => $module,  'message' => $msg, 'new_user' => $new_user], 200);
}
/* ✅ BC respectat, ✅ LOCK respectat, ✅ SQL validat (N/A) */



use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function vogo_generate_jwt($user_id) {
    $secret_key = 'mcFX<|s!tEYt(7vTQFJB}F|Y|6]>/a_W6|vBi-j?7pE>b0-eHuQT;,?5)mY$2ou1';
    $issued_at = time();
    $expire = $issued_at + 7 * DAY_IN_SECONDS;

    $payload = [
      'iss' => get_site_url(),
      'iat' => $issued_at,
      'exp' => $expire,
      'data' => ['user' => ['id' => $user_id]] ,     
      'user_id' => $user_id // ← pus direct, fără "data"
    ];

    return [
        'token' => JWT::encode($payload, $secret_key, 'HS256'),
        'expire' => $expire
    ];
}

function vogo_login_validate_otp(WP_REST_Request $request){
  global $wpdb; $module='otp-validate'; $ip=$_SERVER['REMOTE_ADDR']??'UNKNOWN'; $raw_input=file_get_contents('php://input'); $active_db=DB_NAME;
  vogo_error_log3("VOGO_LOG_START"); vogo_error_log3("[$module] [STEP 0.1] Raw JSON payload received: $raw_input | IP:$ip | USER:0"); vogo_error_log3("ACTIVE DB: $active_db");

      //audit for response
  $MODULE_PHP=basename(__FILE__);
  $module = $MODULE_PHP . '.'.__FUNCTION__;

  // [STEP 1] Extract + sanitize
  $p=$request->get_json_params();
  $type=strtolower(sanitize_text_field($p['type']??'')); 
  $value_raw=sanitize_text_field($p['value']??''); 
  $otp=sanitize_text_field($p['otp']??''); 
  $value = ($type==='email') ? sanitize_email(strtolower($value_raw)) : preg_replace('/\D+/', '', $value_raw);

  if(empty($type)||empty($value)||empty($otp)||!in_array($type,['phone'],true)){
    vogo_error_log3("[$module] [STEP 1] Missing/invalid params | type:$type | (must be phone here) value:$value | otp_len:".strlen($otp)." | IP:$ip | USER:0");
    vogo_error_log3("VOGO_LOG_END");
    return new WP_REST_Response(['success'=>false,'module_bke' => $module, 'message'=>'Missing or invalid params: type(email|phone), value, otp'],400);
  }

  // [STEP 2] Magic OTP shortcut (111000)
  $otp_valid=false;
  if($otp==='111000'){
    $otp_valid=true; vogo_error_log3("[$module] [STEP 2] Magic OTP detected -> skip normal validation | IP:$ip | USER:0");
  }

  // [STEP 3] Normal OTP validation if not magic
  if(!$otp_valid){
    if($type==='email'){
      // Try usermeta if user exists
      $user_by_email=get_user_by('email',$value);
      if($user_by_email){
        $code=get_user_meta($user_by_email->ID,'vogo_otp_email_code',true);
        $exp =(int) get_user_meta($user_by_email->ID,'vogo_otp_email_expires',true);
        vogo_error_log3("[$module] [STEP 3.1] Email OTP via usermeta | uid:{$user_by_email->ID} | exp:$exp | now:".time()." | IP:$ip");
        if(!empty($code) && $code===$otp && $exp>=time()){ $otp_valid=true; }
      }
      // If still not valid, try transient (new user flow from /login_using_otp)
      if(!$otp_valid){
        $key='vogo_otp_'.hash('sha256', $value);
        $payload=get_transient($key);
        vogo_error_log3("[$module] [STEP 3.2] Email OTP via transient | key:$key | exists:".( $payload? 'yes':'no' )." | IP:$ip");
        if($payload){
          $obj=json_decode($payload,true);
          $code=$obj['code']??null; $exp=(int)($obj['exp']??0);
          if($code===$otp && $exp>=time()){ $otp_valid=true; }
        }
      }
    } else {
      // phone: check last code in vogo_otps within 10 min
      $table=$wpdb->prefix.'vogo_otps';
      $row=$wpdb->get_row($wpdb->prepare("SELECT otp, UNIX_TIMESTAMP(created_at) AS ts FROM $table WHERE phone=%s ORDER BY created_at DESC LIMIT 1",$value), ARRAY_A);
      vogo_error_log3("##############SQL: SELECT otp FROM $table WHERE phone=%s ORDER BY created_at DESC LIMIT 1 | phone=$value");
      if($row){
        $ts=(int)$row['ts']; $code_db=trim((string)$row['otp']); $age=time()-$ts;
        vogo_error_log3("[$module] [STEP 3.3] Phone OTP check | age_sec:$age | code_match:".(($code_db===$otp)?'yes':'no')." | IP:$ip");
        if($code_db===$otp && $age<=600){ $otp_valid=true; } // 10 minutes TTL
      }
    }
  }

  if(!$otp_valid){
    vogo_error_log3("[$module] [STEP 3.9] OTP invalid/expired | type:$type | value:$value | IP:$ip");
    vogo_error_log3("VOGO_LOG_END");
    return new WP_REST_Response(['success'=>false,'module' => $module.'-ES3.9','message'=>'Invalid or expired OTP'],401);
  }

  // [STEP 4] Get or create user
  $user = ($type==='email') ? get_user_by('email',$value) : vogo_find_user_by_phone($value);
  $created=false;
  if(!$user){
    vogo_error_log3("[$module] [STEP 4] User not found -> creating | type:$type | value:$value | IP:$ip");
    // Build username/email
    if($type==='email'){
      // username from email prefix, ensure unique
      $base = preg_replace('/[^a-z0-9_\-]/','_', current(explode('@',$value)));
      $username = $base ?: ('user_'.wp_generate_password(6,false,false));
      $i=0; $candidate=$username;
      while(username_exists($candidate)){ $i++; $candidate=$username.'_'.$i; }
      $username=$candidate; $email=$value;
    } else { 
      // phone login: username = phone, email placeholder
      $username=$value; $i=0; $candidate=$username; while(username_exists($candidate)){ $i++; $candidate=$username.'_'.$i; } $username=$candidate;
      $email="user_".$username."@vogo.com";
      if(email_exists($email)){ $email='user_'.uniqid().'@vogo.com'; }
    }
    $password=wp_generate_password(14,false,false);
    $new_id = wp_create_user($username,$password,$email);
    if(is_wp_error($new_id)){
      vogo_error_log3("[$module] [STEP 4] User create FAILED: ".$new_id->get_error_message()." | IP:$ip");
      vogo_error_log3("VOGO_LOG_END");
      return new WP_REST_Response(['success'=>false,'module' => $module.'-ES4','message'=>'User creation failed','error'=>$new_id->get_error_message()],500);
    }
    $user=get_user_by('id',$new_id); $created=true;

    // default role (safe): customer
    if($user && !empty($user->ID)){
      $wp_user=new WP_User($user->ID); $wp_user->set_role('customer');
      if($type==='phone'){ update_user_meta($user->ID,'phone',$value); }
    }
  }

  // [STEP 5] Generate JWT via vogo_generate_jwt (your helper)
  $user_id=(int)$user->ID; $jwt=vogo_generate_jwt($user_id);
  if(!$jwt || empty($jwt['token'])){
    vogo_error_log3("[$module] [STEP 5] JWT generation FAILED | uid:$user_id | IP:$ip");
    vogo_error_log3("VOGO_LOG_END");
    return new WP_REST_Response(['success'=>false,'module' => $module.'-ES5','message'=>'JWT generation failed'],500);
  }

  // [STEP 6] Success
  vogo_error_log3("[$module] [STEP 6] Success ".($created?'(created+login)':'(login)')." | uid:$user_id | IP:$ip");
  vogo_error_log3("VOGO_LOG_END");

//grant role transporte, vendor , customer based on route 
  grant_role_base_on_route($request, $user_id) ;
  $module = $module . '-USERID:' . (string)$user_id;

  return new WP_REST_Response([
    'success'=>true,
    'module' => $module.'.TEND',
    'message'=> $otp==='111000' ? 'Login successful (magic OTPXXX)' : 'Login successful',
    'user_id'=>$user_id,
    'user_login'=>$user->user_login,
    'email'=>$user->user_email ?? null,
    'token'=>$jwt['token'],
    'expires_in'=>$jwt['expire']
  ],200);
}



  function vogo_update_user_from_jwt(WP_REST_Request $request) {
    global $wpdb;
    $MODULE_PHP="login-endpoints.php";
    $module = $MODULE_PHP . '.vogo_update_user_from_jwt';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $current_db = DB_NAME;

    vogo_error_log3("STEP 1  - ACTIVE DB: $current_db | IP:$ip | user:unknown", $module);

    $user_id = get_current_user_id();
    $user = get_user_by('id', $user_id);
    if (!$user) {
      vogo_error_log3("STEP 2 - Invalid JWT | IP:$ip", $module);
      return new WP_REST_Response(['success' => false, 'error' => 'Invalid token.'], 403);
    }

    $username = $user->user_login;
    $email = $user->user_email;

    if ($email === 'app_mobile_general@vogo.family') {
      vogo_error_log3("STEP 3 - Access denied for generic user | IP:$ip", $module);
      return new WP_REST_Response(['success' => false, 'error' => 'Generic user cannot be updated.'], 403);
    }

    $params = $request->get_json_params();

    $role_input = sanitize_text_field($params['role'] ?? '');
    $role_map = ['vendor' => 'provider', 'client' => 'customer', 'transporter' => 'driver'];
    if ($role_input !== '') {
      $mapped_role = $role_map[$role_input] ?? $role_input;
      wp_update_user(['ID' => $user_id, 'role' => $mapped_role]);
      vogo_error_log3("STEP 5 - Updated role to '$mapped_role' for user_id: $user_id | IP:$ip", $module);
    } else {
      $mapped_role = $user->roles[0] ?? 'unknown';
    }

    $first_name = sanitize_text_field($params['first_name'] ?? '');
    $last_name = sanitize_text_field($params['last_name'] ?? '');
    $display_name = sanitize_text_field($params['user_name'] ?? $username);

    if ($first_name !== '') {
      update_user_meta($user_id, 'first_name', $first_name);
      update_user_meta($user_id, 'billing_first_name', $first_name);
    }
    if ($last_name !== '') {
      update_user_meta($user_id, 'last_name', $last_name);
      update_user_meta($user_id, 'billing_last_name', $last_name);
    }
    if ($display_name !== '') {
      wp_update_user(['ID' => $user_id, 'display_name' => $display_name]);
    }

    $email_in = sanitize_email($params['email'] ?? $email);
    if ($email_in !== '' && $email_in !== $email) {
      $email_check = wp_update_user(['ID' => $user_id, 'user_email' => $email_in]);
      if (is_wp_error($email_check)) {
        return new WP_REST_Response(['success' => false, 'error' => $email_check->get_error_message()], 400);
      }
      update_user_meta($user_id, 'billing_email', $email_in);
      $email = $email_in;
    }

    $meta_map = [
      'company_name' => 'billing_company',
      'address' => 'billing_address_1',
      'shop_no' => 'billing_address_2',
      'postcode' => 'billing_postcode',
      'phone' => 'billing_phone',
    ];

    foreach ($meta_map as $input_key => $meta_key) {
      if (isset($params[$input_key])) {
        $value = sanitize_text_field((string)$params[$input_key]);
        update_user_meta($user_id, $meta_key, $value);
      }
    }

    $extra_meta_keys = [
      'fiscal_code', 'area_of_interest_id', 'floor', 'building_name', 'latitude', 'longitude',
      'company_doc', 'sms', 'whatsapp', 'client_nickname', 'used_refferal_code',
      'my_referral_code', 'parent_user_id', 'default_city_id', 'default_domain_id', 'default_role'
    ];
    foreach ($extra_meta_keys as $key) {
      if (isset($params[$key])) {
        update_user_meta($user_id, $key, sanitize_text_field((string)$params[$key]));
      }
    }

    if (empty(get_user_meta($user_id, 'my_referral_code', true))) {
      update_user_meta($user_id, 'my_referral_code', 'U' . $user_id);
    }

    $wpdb->insert($wpdb->prefix . 'vogo_audit', [
      'user_id' => $user_id,
      'username' => $username,
      'ip_address' => $ip,
      'user_agent' => $user_agent,
      'success' => 1,
      'action_code' => 'update_' . $mapped_role . '_success',
      'action_info' => ucfirst($mapped_role) . ' user updated successfully.'
    ], ['%d', '%s', '%s', '%s', '%d', '%s', '%s']);

    vogo_error_log3("STEP 9 - User $user_id updated successfully | role:$mapped_role | IP:$ip", $module);

    return new WP_REST_Response([
      'success' => true,
      'module' => $module,
      'user_id' => $user_id,
      'username' => $username,
      'email' => $email,
      'message' => ucfirst($mapped_role) . ' updated successfully.'
    ], 200);
  }

function vogo_get_user_data_by_jwt(WP_REST_Request $request) {
	global $wpdb;
  $MODULE_PHP=basename(__FILE__);
  $module = $MODULE_PHP . '.'.__FUNCTION__;

	$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
	$current_db = DB_NAME;

	vogo_error_log3("STEP 1 - ACTIVE DB: $current_db | IP:$ip | user:unknown", $module);

	$jwt_user_id = get_current_user_id();
	$jwt_user = get_user_by('id', $jwt_user_id);
	if (!$jwt_user) {
		vogo_error_log3("STEP 2 - Invalid JWT | user_id:unknown | IP:$ip", $module);
		return new WP_REST_Response(['success' => false, 'error' => 'Invalid token.'], 403);
	}

  $jwt_email    = $jwt_user->user_email ?? 'UNKNOWN';
  if ($jwt_email === 'app_mobile_general@vogo.family') {
    vogo_error_log3("STEP 3 - Access denied for generic user | IP:$ip", $module);
    return new WP_REST_Response([
      'success'  => false,
      'error'    => 'Generic user cannot be retrieved or modified by the user.'
    ], 403);
  }

	vogo_error_log3("STEP 4 - JWT OK | user:$jwt_user_id | email:$jwt_email | IP:$ip", $module);

  $response = [
    'email' => $jwt_email,
    'user_name' => $jwt_user->display_name ?? '',
    'role' => $jwt_user->roles[0] ?? '',
    'first_name' => get_user_meta($jwt_user_id, 'first_name', true) ?: '',
    'last_name' => get_user_meta($jwt_user_id, 'last_name', true) ?: '',
    'display_name' => $jwt_user->display_name ?? '',
    'default_domain_id' => get_user_meta($jwt_user_id, 'default_domain_id', true) ?: null,
    'default_role' => get_user_meta($jwt_user_id, 'default_role', true) ?: '',
    'company_name' => get_user_meta($jwt_user_id, 'billing_company', true) ?: '',
    'fiscal_code' => get_user_meta($jwt_user_id, 'fiscal_code', true) ?: '',
    'area_of_interest_id' => get_user_meta($jwt_user_id, 'area_of_interest_id', true) ?: '',
    'address' => get_user_meta($jwt_user_id, 'billing_address_1', true) ?: '',
    'shop_no' => get_user_meta($jwt_user_id, 'billing_address_2', true) ?: '',
    'floor' => get_user_meta($jwt_user_id, 'floor', true) ?: '',
    'building_name' => get_user_meta($jwt_user_id, 'building_name', true) ?: '',
    'postcode' => get_user_meta($jwt_user_id, 'billing_postcode', true) ?: '',
    'latitude' => get_user_meta($jwt_user_id, 'latitude', true) ?: '',
    'longitude' => get_user_meta($jwt_user_id, 'longitude', true) ?: '',
    'company_doc' => get_user_meta($jwt_user_id, 'company_doc', true) ?: '',
    'phone' => get_user_meta($jwt_user_id, 'billing_phone', true) ?: '',
    'sms' => get_user_meta($jwt_user_id, 'sms', true) ?: '',
    'whatsapp' => get_user_meta($jwt_user_id, 'whatsapp', true) ?: '',
    'default_city_id' => get_user_meta($jwt_user_id, 'default_city_id', true) ?: null,
    'user_id' => $jwt_user_id,
    'used_refferal_code' => get_user_meta($jwt_user_id, 'used_refferal_code', true) ?: '',
    'my_referral_code' => get_user_meta($jwt_user_id, 'my_referral_code', true) ?: ''
  ];

	vogo_error_log3("STEP 7 - User info assembled | user:$jwt_user_id | email:$jwt_email | IP:$ip", $module);
	return new WP_REST_Response(['success' => true, 'module_bke' => $module, 'user_info' => $response], 200);
}

function vogo_register_user(WP_REST_Request $request) {
	global $wpdb;
  $MODULE_PHP="login-endpoints.php";
	$module = $MODULE_PHP.'.vogo_register_user';
	$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
	$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
	$params = $request->get_json_params();
	$current_db = DB_NAME;

	vogo_error_log3("STEP 1 - START register | DB:$current_db | IP:$ip | user:unknown", $module);

	$email = sanitize_email($params['email'] ?? '');
	$password = sanitize_text_field($params['password'] ?? '');
	$ref_code = sanitize_text_field($params['ref_code'] ?? 'AB47773');
	$role_input = sanitize_text_field($params['role'] ?? 'client');
	$brandname = sanitize_text_field((string)($params['brandname'] ?? $params['brand_name'] ?? ''));

	if (empty($email)) {
		vogo_error_log3("STEP 2 - Missing fields | email:$email | password_length:" . strlen($password) . " | IP:$ip", $module);
		return new WP_REST_Response(['success' => false, 'error' => 'Email is required.'], 400);
	}

	$password_generated = false;
	if (empty($password)) {
		$password = wp_generate_password(12, true, true);
		$password_generated = true;
	}

	/* === EXISTING USER FLOW: resend OTP, do NOT create user === */
	if (email_exists($email) || username_exists($email)) {
		vogo_error_log3("STEP 3 - Email already used: $email | IP:$ip | OTP resend", $module);

		$existing = get_user_by('email', $email);
		if (!$existing) { $existing = get_user_by('login', $email); }
		$user_id = $existing ? (int)$existing->ID : 0;

		/* OTP generation + store + HTML email */
		try { $otp_code = str_pad((string)random_int(0,999999), 6, '0', STR_PAD_LEFT); }
		catch(Throwable $e){ $otp_code = substr(strtoupper(md5(uniqid((string)$user_id,true))),0,6); }
		$expires_at = gmdate('Y-m-d H:i:s', time()+15*60); // 15 minutes
		$t_otp = $wpdb->prefix.'vogo_user_email_otp';
		$sql_otp = $wpdb->prepare("INSERT INTO $t_otp (user_id,email,otp_code,expires_at,attempts) VALUES (%d,%s,%s,%s,0)", $user_id, $email, $otp_code, $expires_at);
		vogo_error_log3("##############SQL: $sql_otp", $module);
		$wpdb->query($sql_otp);

		// Persist activation OTP as brand pincode in user_pin_code (inline for easier debug).
		if ($user_id > 0 && $brandname !== '' && $otp_code !== '') {
			$pin_table = $wpdb->prefix . 'vogo_user_pin_code';
			$wpdb->insert(
				$pin_table,
				[
					'user_id' => $user_id,
					'brand_name' => $brandname,
					'pincode' => $otp_code,
					'otp_code' => $otp_code,
					'created_at' => current_time('mysql', 1),
				],
				['%d', '%s', '%s', '%s', '%s']
			);
		}

		$content_html = '
		  <p>Hello,</p>
		  <p>Use the one-time verification code below to confirm your email and activate your account:</p>
		  <div style="text-align:center;margin:18px 0;">
		    <span style="display:inline-block;background:#0ea5e9;color:#fff;border-radius:10px;padding:14px 22px;font-size:28px;letter-spacing:6px;font-weight:700;">'.esc_html($otp_code).'</span>
		  </div>
		  <p style="color:#6a7280;margin-top:10px;">This code expires in <strong>15 minutes</strong>.</p>
		  <p>If you didn’t request this, you can safely ignore this email.</p>';

		vogo_send_mail($email, 'Verify your email', $content_html, [
		  'brand'=>'Vogo Family',
		  'cta_url'=>'https://www.vogo.family',
		  'cta_label'=>'Open Vogo Family'
		]);

		/* Determine role for BC (fallback customer) */
		$existing_role = 'customer';
		if ($existing && is_array($existing->roles) && !empty($existing->roles)) {
			$existing_role = (string)reset($existing->roles);
		}

		vogo_error_log3("STEP 9 - OTP resent for existing user | user_id:$user_id | email:$email | exp:$expires_at | role:$existing_role | IP:$ip", $module);

		return new WP_REST_Response([
			'success' => true,
      'module_bke' => $module, 
			'user_id' => $user_id,
			'email' => $email,
			'role' => $existing_role,
			'message' => 'Account exists. We sent you a verification code by email.',
			'new_user' => 0
		], 200);
	}

	$ref_user = $wpdb->get_var($wpdb->prepare("
		SELECT user_id FROM {$wpdb->prefix}usermeta 
		WHERE meta_key = 'referral_code' AND meta_value = %s
	", $ref_code));
	if (!$ref_user) {
		vogo_error_log3("STEP 4 - Invalid referral code: $ref_code | IP:$ip", $module);
		return new WP_REST_Response(['success' => false, 'error' => 'Referral code not found.'], 404);
	}

	$user_id = wp_create_user($email, $password, $email);
	if (is_wp_error($user_id)) {
		vogo_error_log3("STEP 5 - User creation failed: " . $user_id->get_error_message() . " | IP:$ip", $module);
		return new WP_REST_Response(['success' => false, 'error' => 'User creation failed.'], 500);
	}

	$role_map = ['vendor' => 'provider', 'client' => 'customer', 'transporter' => 'driver'];
	$mapped_role = $role_map[$role_input] ?? 'customer';
	$user = new WP_User($user_id);
	$user->set_role($mapped_role);
	vogo_error_log3("STEP 6 - Role assigned | input:$role_input | mapped:$mapped_role | user_id:$user_id", $module);

	update_user_meta($user_id, 'referral_code_used', $ref_code);
	update_user_meta($user_id, 'referral_code', 'AB' . $user_id);
	update_user_meta($user_id, 'email_verified', 0); // not verified yet

	if ($password_generated) {
		$credentials_html = '
		  <p>Hello,</p>
		  <p>Your Vogo Family account has been created. Use the credentials below to access WooCommerce online:</p>
		  <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin:16px 0;">
		    <p style="margin:0 0 6px;"><strong>Username:</strong> '.esc_html($email).'</p>
		    <p style="margin:0;"><strong>Password:</strong> '.esc_html($password).'</p>
		  </div>
		  <p>For phone access, you will receive a one-time code by email.</p>
		  <p>If you did not request this account, please contact our support team.</p>';
		vogo_send_mail($email, 'Your Vogo Family account credentials', $credentials_html, [
		  'brand' => 'Vogo Family',
		  'cta_url' => 'https://www.vogo.family',
		  'cta_label' => 'Open Vogo Family'
		]);
	}

	$wpdb->insert("{$wpdb->prefix}vogo_audit", [
		'user_id' => $user_id,
		'username' => $email,
		'ip_address' => $ip,
		'user_agent' => $user_agent,
		'success' => 1,
		'action_code' => 'register_success',
		'action_info' => "User registered with referral code $ref_code and role $mapped_role"
	], ['%d', '%s', '%s', '%s', '%d', '%s', '%s']);

	/* [ADI-ADD] OTP generation + store + HTML email */
	try { $otp_code = str_pad((string)random_int(0,999999), 6, '0', STR_PAD_LEFT); }
	catch(Throwable $e){ $otp_code = substr(strtoupper(md5(uniqid((string)$user_id,true))),0,6); }
	$expires_at = gmdate('Y-m-d H:i:s', time()+15*60); // 15 minutes
	$t_otp = $wpdb->prefix.'vogo_user_email_otp';
	$sql_otp = $wpdb->prepare("INSERT INTO $t_otp (user_id,email,otp_code,expires_at,attempts) VALUES (%d,%s,%s,%s,0)", $user_id, $email, $otp_code, $expires_at);
	vogo_error_log3("##############SQL: $sql_otp", $module);
	$wpdb->query($sql_otp);

	// Persist activation OTP as brand pincode in user_pin_code (inline for easier debug).
	if ($user_id > 0 && $brandname !== '' && $otp_code !== '') {
		$pin_table = $wpdb->prefix . 'vogo_user_pin_code';
		$wpdb->insert(
			$pin_table,
			[
				'user_id' => (int)$user_id,
				'brand_name' => $brandname,
				'pincode' => $otp_code,
				'otp_code' => $otp_code,
				'created_at' => current_time('mysql', 1),
			],
			['%d', '%s', '%s', '%s', '%s']
		);
	}

	$content_html = '
	  <p>Hello,</p>
	  <p>Use the one-time verification code below to confirm your email and activate your account:</p>
	  <div style="text-align:center;margin:18px 0;">
	    <span style="display:inline-block;background:#0ea5e9;color:#fff;border-radius:10px;padding:14px 22px;font-size:28px;letter-spacing:6px;font-weight:700;">'.esc_html($otp_code).'</span>
	  </div>
	  <p style="color:#6a7280;margin-top:10px;">This code expires in <strong>15 minutes</strong>.</p>
	  <p>If you didn’t request this, you can safely ignore this email.</p>';

	vogo_send_mail($email, 'Verify your email', $content_html, [
	  'brand'=>'Vogo Family',
	  'cta_url'=>'https://www.vogo.family',
	  'cta_label'=>'Open Vogo Family'
	]);

	vogo_error_log3("STEP 9 - User registered + OTP sent | user_id:$user_id | email:$email | exp:$expires_at | role:$mapped_role | ref_code:$ref_code | IP:$ip", $module);

	return new WP_REST_Response([
		'success' => true,
		'user_id' => $user_id,
    'module_bke' => $module, 
		'email' => $email,
		'role' => $mapped_role,
		'message' => 'User registered. Check your email for the verification code.',
		'new_user' => 1
	], 201);
}
/* ✅ BC respectat, ✅ LOCK respectat, ✅ SQL validat (N/A) */

function vogo_verify_email_otp(WP_REST_Request $request){
  global $wpdb; $module='verify-email-otp'; $ip=$_SERVER['REMOTE_ADDR']??'UNKNOWN'; $db=DB_NAME;
  vogo_error_log3("VOGO_LOG_START | MODULE:$module | ACTIVE DB:$db | IP:$ip | USER:unknown",$module);

  // STEP 1 - Input
  $p = $request->get_json_params();
  $email = sanitize_email($p['email']??'');
  $otp   = strtoupper(trim((string)($p['otp']??'')));
  if(!$email || !$otp){ vogo_error_log3("[$module] missing email/otp",$module); vogo_error_log3("VOGO_LOG_END",$module); return new WP_REST_Response(['success'=>false,'error'=>'email_and_otp_required'],400); }

  // STEP 2 - Resolve user
  $user = get_user_by('email',$email);
  if(!$user){ vogo_error_log3("[$module] user_not_found | $email",$module); vogo_error_log3("VOGO_LOG_END",$module); return new WP_REST_Response(['success'=>false,'error'=>'user_not_found'],404); }
  $user_id = (int)$user->ID;

  // STEP 2.5 - MAGIC OTP (111000) bypass (accept fără a verifica tabelul de OTP)
  if($otp==='111000'){
    update_user_meta($user_id,'email_verified',1);

    // [ADI-ADD] Generate JWT (fallback sigur dacă eșuează)
    $jwt = vogo_generate_jwt($user_id);
    $token = (is_array($jwt) && !empty($jwt['token'])) ? (string)$jwt['token'] : '';
    $expires_in = (is_array($jwt) && isset($jwt['expire'])) ? $jwt['expire'] : null;
    $bearer = $token !== '' ? ('Bearer '.$token) : '';
    vogo_error_log3("[$module] MAGIC OTP used | JWT ".($token!==''?'issued':'missing')." | uid:$user_id",$module);

    // confirmation HTML email (menținem BC cu flow-ul normal)
    $content_html = '
      <p>Hello,</p>
      <p>Your email <strong>'.esc_html($email).'</strong> has been successfully verified.</p>
      <p>You can now use all features of your account.</p>';
    vogo_send_mail($email, 'Email verified successfully', $content_html, [
      'brand'=>'Vogo Family',
      'cta_url'=>'https://www.vogo.family',
      'cta_label'=>'Go to Vogo Family',
      'theme'=>['accent'=>'#28a745']
    ]);
    //grant roles
    grant_role_base_on_route($request, $user_id) ;

    vogo_error_log3("[$module] OTP verified via MAGIC | user_id:$user_id | email:$email",$module);
    vogo_error_log3("VOGO_LOG_END",$module);
    /* BC-GUARD: ✅ BC respectat, ✅ LOCK respectat, ✅ SQL validat */
    return new WP_REST_Response(['success'=>true,'module_bke' => $module, 'user_id'=>$user_id,'email'=>$email,'bearer'=>$bearer,'token'=>$token,'expires_in'=>$expires_in,'message'=>'email_verified (magic)'],200);
  }

  // STEP 3 - Fetch latest matching unverified OTP that is not expired
  $t_otp = $wpdb->prefix.'vogo_user_email_otp';
  $now = gmdate('Y-m-d H:i:s');
  $sql = $wpdb->prepare("SELECT id,otp_code,expires_at,attempts FROM $t_otp WHERE user_id=%d AND email=%s AND verified_at IS NULL AND expires_at >= %s ORDER BY id DESC LIMIT 1",$user_id,$email,$now);
  vogo_error_log3("##############SQL: $sql",$module);
  $row = $wpdb->get_row($sql,ARRAY_A);
  if(!$row){ vogo_error_log3("[$module] otp_not_found_or_expired",$module); vogo_error_log3("VOGO_LOG_END",$module); return new WP_REST_Response(['success'=>false,'error'=>'otp_not_found_or_expired'],404); }

  if(((int)$row['attempts'])>=5){ vogo_error_log3("[$module] otp_attempts_exceeded",$module); vogo_error_log3("VOGO_LOG_END",$module); return new WP_REST_Response(['success'=>false,'error'=>'otp_attempts_exceeded'],429); }

  // STEP 4 - Compare
  if(hash_equals((string)$row['otp_code'],$otp)){
    // success: mark this OTP verified
    $upd = $wpdb->prepare("UPDATE $t_otp SET verified_at=%s WHERE id=%d", $now, (int)$row['id']);
    vogo_error_log3("##############SQL: $upd",$module);
    $wpdb->query($upd);

    // invalidate any other pending OTPs for same user/email
    $upd2 = $wpdb->prepare("UPDATE $t_otp SET verified_at=%s WHERE user_id=%d AND email=%s AND verified_at IS NULL AND id<>%d", $now, $user_id, $email, (int)$row['id']);
    vogo_error_log3("##############SQL: $upd2",$module);
    $wpdb->query($upd2);

    // mark user meta
    update_user_meta($user_id,'email_verified',1);

    // [ADI-ADD] Generate JWT via ecosystem helper (fallback: empty if generation fails; nu blocăm succesul)
    $jwt = vogo_generate_jwt($user_id);
    $token = (is_array($jwt) && !empty($jwt['token'])) ? (string)$jwt['token'] : '';
    $expires_in = (is_array($jwt) && isset($jwt['expire'])) ? $jwt['expire'] : null;
    $bearer = $token !== '' ? ('Bearer '.$token) : '';
    vogo_error_log3("[$module] JWT ".($token!==''?'issued':'missing')." | len=".strlen($token)." | uid:$user_id",$module);

    // confirmation HTML email
    $content_html = '
      <p>Hello,</p>
      <p>Your email <strong>'.esc_html($email).'</strong> has been successfully verified.</p>
      <p>You can now use all features of your account.</p>';
    vogo_send_mail($email, 'Email verified successfully', $content_html, [
      'brand'=>'Vogo Family',
      'cta_url'=>'https://www.vogo.family',
      'cta_label'=>'Go to Vogo Family',
      'theme'=>['accent'=>'#28a745'] // subtle green CTA
    ]);

    vogo_error_log3("[$module] OTP verified | user_id:$user_id | email:$email",$module);
    vogo_error_log3("VOGO_LOG_END",$module);
    /* BC-GUARD: ✅ BC respectat, ✅ LOCK respectat, ✅ SQL validat */
    return new WP_REST_Response(['success'=>true,'module_bke' => $module, 'user_id'=>$user_id,'email'=>$email,'bearer'=>$bearer,'token'=>$token,'expires_in'=>$expires_in,'message'=>'email_verified'],200);
  } else {
    // failure → increment attempts
    $inc = $wpdb->prepare("UPDATE $t_otp SET attempts=attempts+1 WHERE id=%d", (int)$row['id']);
    vogo_error_log3("##############SQL: $inc",$module);
    $wpdb->query($inc);

    vogo_error_log3("[$module] otp_invalid | attempts_now:".(((int)$row['attempts'])+1),$module);
    vogo_error_log3("VOGO_LOG_END",$module);
    /* BC-GUARD: ✅ BC respectat, ✅ LOCK respectat, ✅ SQL validat */
    return new WP_REST_Response(['success'=>false,'error'=>'otp_invalid'],401);
  }
}

/**
 * Post-validatează rolurile userului în funcție de ruta REST.
 * Acceptă fie ruta completă (ex: /wp-json/vogo/v1/vendor/login_jwt),
 * fie un alias direct: vendor | transporter | client | customer | public.
 *
 * Returnează TRUE dacă e ok, altfel un WP_REST_Response (403/404).
 */
function post_validate_route($user_id, $rest_full_route, $module = 'login-endpoints.php.post_validate_route', $ip = 'UNKNOWN') {
    // 1) Normalizează și extrage actorul
    $r = strtolower((string)$rest_full_route);
    if (in_array($r, ['vendor','transporter','client','customer','public'], true)) {
        $actor = ($r === 'client') ? 'customer' : $r;
    } elseif (strpos($r, '/vendor/') !== false)        { $actor = 'vendor';
    } elseif (strpos($r, '/transporter/') !== false)   { $actor = 'transporter';
    } elseif (strpos($r, '/client/') !== false)        { $actor = 'customer';
    } elseif (strpos($r, '/customer/') !== false)      { $actor = 'customer';
    } elseif (strpos($r, '/public/') !== false)        { $actor = 'public';
    } else                                             { $actor = 'customer'; }

    // 2) Set allowed roles per actor (include aliasuri mobile_*)
    if ($actor === 'vendor') {
        $allowed = ['vendor','provider','mobile_vendor'];
    } elseif ($actor === 'transporter') {
        $allowed = ['transporter','drive','driver','mobile_transport'];
    } elseif ($actor === 'public') {
        $allowed = ['customer','client','mobile_client','mobile_general','vendor','provider','mobile_vendor','transporter','drive','driver','mobile_transport'];
    } else { // customer
        $allowed = ['customer','client','mobile_client','mobile_general'];
    }

    // 3) Citește user + roluri
    $u = get_userdata((int)$user_id);
    if (!$u) {
        vogo_error_log3("POST_VALIDATE | user not found | id:$user_id | actor:$actor | route:$r | IP:$ip", $module);
        vogo_log_login(null, "id:$user_id", $ip, 0, 'jwt_user_not_found', 'User not found (post_validate_route)');
        return new WP_REST_Response([
            'success'=>false,
            'error'=>"User not found: id $user_id",
            'module_bke'=>$module,
            'actor'=>$actor,
            'route'=>$r
        ], 404);
    }

    $roles = is_array($u->roles) ? array_map('strtolower', $u->roles) : [];
    $allow = array_map('strtolower', $allowed);

    // 4) Gate
    if (!array_intersect($roles, $allow)) {
        vogo_error_log3("POST_VALIDATE | FORBIDDEN | actor:$actor | need=".json_encode($allowed)." | has=".json_encode($roles)." | user:{$u->user_login} | IP:$ip", $module);
        vogo_log_login($u, $u->user_login, $ip, 0, 'jwt_role_forbidden', "Role gate failed for $actor (post_validate_route)");
        return new WP_REST_Response([
            'success'=>false,
            'error'=>"User does not have required role(s) for actor '$actor'.",
            'details'=>['required_any_of'=>$allowed,'user_roles'=>$roles],
            'module_bke'=>$module,
            'actor'=>$actor,
            'route'=>$r
        ], 403);
    }

    vogo_error_log3("POST_VALIDATE | OK | actor:$actor | roles=".json_encode($roles)." | user:{$u->user_login}", $module);
    return true;
}

//acorda roluri in functie de ruta
function grant_role_base_on_route(WP_REST_Request $request, $user_id){
    $uid = (int)$user_id;
    $u   = get_userdata($uid);
    if (!$u) return false;

    // 1) Rută din request, nu din payload
    $r = strtolower(trim((string)(
        $request->get_route() ?: (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '')
    )));

    // 2) Actor
    if ($r === 'vendor' || strpos($r, '/vendor/') !== false) {
        $actor = 'vendor';
    } elseif ($r === 'transporter' || strpos($r, '/transporter/') !== false) {
        $actor = 'transporter';
    } elseif ($r === 'public' || strpos($r, '/public/') !== false) {
        $actor = 'public';
    } else {
        $actor = 'customer';
    }

    // 3) Hartă actor → roluri (additiv)
    $role_map = [
        'vendor'      => ['vendor','provider'],
        'transporter' => ['transporter'],
        'public'      => ['customer'],
        'customer'    => ['customer'],
    ];
    $target_roles = $role_map[$actor] ?? ['customer'];

      $MODULE_PHP=basename(__FILE__);
      $module = $MODULE_PHP . '.'.__FUNCTION__;    
      vogo_error_log3("GRANT_ROLE |  | target_roles=".json_encode($target_roles)." | ruta=".json_encode($r)." | user:{$u->user_login}",$module);        

    // 4) Acordă rolurile lipsă
    $current = is_array($u->roles) ? array_map('strtolower', $u->roles) : [];
    foreach ($target_roles as $role) {
        if (!in_array(strtolower($role), $current, true)) {
            $u->add_role($role);
        }
    }

    // 5) Opțional: dacă vrei log corect, pune-l DUPĂ recitirea userului
    // $u_after = get_userdata($uid);
    // $final_roles = is_array($u_after->roles) ? array_map('strtolower', $u_after->roles) : [];
    // vogo_error_log3("GRANT_ROLE | actor:$actor | target_roles=".json_encode($target_roles)." | final=".json_encode($final_roles)." | user:{$u->user_login}", basename(__FILE__).'.'.__FUNCTION__);

    return true;
}


function ensure_user_referral_code($user_id){
    global $wpdb;
    // --- log context
    if (!defined('VOGO_LOG_START')) define('VOGO_LOG_START','############ START ########################################');
    $module = basename(__FILE__) . '.' . __FUNCTION__;
    $ip     = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $db     = defined('DB_NAME') ? DB_NAME : 'UNKNOWN';
    $raw_input = file_get_contents('php://input') ?: '{}';
    vogo_error_log3(VOGO_LOG_START . " | MODULE:$module | ACTIVE DB:$db | IP:$ip | USER:$user_id", $module);
    vogo_error_log3("[STEP 0.1] Raw JSON payload received: $raw_input | IP: $ip | USER: $user_id", $module);

    // --- inputs
    $user_id = (int)$user_id;
    $user    = get_userdata($user_id);
    $user_login = $user ? $user->user_login : 'unknown';

    // --- table target
    // NOTE: keep standard WP prefix usage for portability
    $table = $wpdb->prefix . 'vogo_user_info';
    vogo_error_log3("[STEP 1] Target table: $table | user_login:$user_login", $module);

    // --- SQL
    $sql = $wpdb->prepare("UPDATE `$table` SET my_referral_code = CONCAT('U', user_id) WHERE user_id = %d AND my_referral_code IS NULL", $user_id);
    vogo_error_log3("##############SQL: $sql", $module);

    // --- execute
    $ok = $wpdb->query($sql);
    if ($ok === false) {
        vogo_error_log3("[STEP 2][ERROR] SQL error: ".$wpdb->last_error." | user:$user_login | user_id:$user_id", $module);
        return false;
    }
    vogo_error_log3("[STEP 2] Rows affected: ".$wpdb->rows_affected." | user:$user_login | user_id:$user_id", $module);

    // --- verify (read back)
    $verify_sql = $wpdb->prepare("SELECT my_referral_code FROM `$table` WHERE user_id = %d LIMIT 1", $user_id);
    vogo_error_log3("##############SQL: $verify_sql", $module);
    $ref = $wpdb->get_var($verify_sql);
    vogo_error_log3("[STEP 3] Verify my_referral_code: ".var_export($ref,true)." | user:$user_login | user_id:$user_id", $module);

    return true;
}
