<?php
/**
 * VOGO REST API - Shared backend library (vogolib).
 *
 * Responsibility:
 * - Provides shared helpers used across REST modules (logging, permissions, utils).
 * - Centralizes reusable backend utilities to keep endpoint files focused.
 */

    define('ADVANCED_DEBUG', true);
    $MODULE_PHP='vogolib.php';

    

/**
 * Logging constants section.
 * Keeps consistent start/end markers for debug traces across modules.
 */
// vogo-api/rest/util.php sau vogo-core.php
if (!defined('VOGO_LOG_START')) define('VOGO_LOG_START', '############ START ########################################');
if (!defined('VOGO_LOG_END')) define('VOGO_LOG_END', '############ END ##########################################');


if(!function_exists('vogo_normalize_phone')){
  function vogo_normalize_phone($raw){
    $ip=$_SERVER['REMOTE_ADDR']??'unknown'; $active_db=defined('DB_NAME')?DB_NAME:'unknown';
    $in=trim((string)$raw);
    vogo_error_log3("[PHONE_NORMALIZE] ACTIVE DB: $active_db | IP:$ip | USER:unknown | in=$in");
    $p=str_replace([' ','-','(',')','.'],"",$in); if(strpos($p,'00')===0){ $p='+'.substr($p,2); }
    if($p!=='' && $p[0]!=='+'){ $p=($p[0]==='0')?('+40'.substr($p,1)):'+'.$p; }          // assume RO for leading 0
    $p='+' . preg_replace('/\D/','',ltrim($p,'+'));                                        // keep + and digits
    $len=strlen(ltrim($p,'+')); if($len<8||$len>15){ vogo_error_log3("[PHONE_NORMALIZE] Length out of E164 range ($len) | out=$p | IP:$ip | USER:unknown"); }
    vogo_error_log3("[PHONE_NORMALIZE] out=$p | IP:$ip | USER:unknown");
    return $p;
  }
}


/**
 * File-based logging helpers section.
 * Provides log file resolution and low-level write helpers.
 */
if (!function_exists('vogo_get_log_file')) {
    function vogo_get_log_file() {
        // Use daily log files to keep size under control and make reading faster.
        $base_dir = defined('WP_CONTENT_DIR') ? rtrim(WP_CONTENT_DIR, '/\\') : __DIR__;
        $log_dir = $base_dir . '/vogo-logs';
        if (!is_dir($log_dir)) {
            // Ensure the log folder exists for daily log rotation.
            @mkdir($log_dir, 0755, true);
        }
        $date_suffix = date('Y-m-d');
        return $log_dir . '/vogo-log-' . $date_suffix . '.log';
    }
}

function vogo_error_log($message) {

    if (!ADVANCED_DEBUG) {
        return; // 🔇 Logging dezactivat
    }

    $log_file = vogo_get_log_file();
    error_log($message . PHP_EOL, 3, $log_file);
}

/**
 * Advanced structured logger section.
 * Writes contextual log entries with request id, source, hook, DB, and IP details.
 */
if (!function_exists('vogo_error_log3')) {
    function vogo_error_log3($message, $user_id = null, $module = 'vogo-core', array $context = []) {
        if (!defined('ADVANCED_DEBUG') || ADVANCED_DEBUG !== true) {
            return; // Logging disabled
        }

        // Resolve user id if not explicitly passed
        if ($user_id === null && function_exists('is_user_logged_in') && is_user_logged_in()) {
            $user_id = function_exists('get_current_user_id') ? get_current_user_id() : null;
        }
        $user_tag = $user_id ? "user:$user_id" : "user:unknown";

        // Current WP hook (if any)
        $hook = function_exists('current_filter') ? current_filter() : '';
        $hook_tag = $hook ? "hook:$hook" : "hook:-";

        // One request-id per PHP request (helps correlate lines)
        static $req_id = null;
        if ($req_id === null) {
            $req_id = substr(md5((string) microtime(true) . mt_rand()), 0, 8);
        }

        // Caller file:line + function via backtrace (caller is frame 1)
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3); // [0]=this func, [1]=caller, [2]=caller’s caller
        $src_file = isset($bt[1]['file']) ? $bt[1]['file'] : __FILE__;
        $src_line = isset($bt[1]['line']) ? $bt[1]['line'] : 0;
        $src_func = isset($bt[1]['function']) ? $bt[1]['function'] : '';
        if (defined('ABSPATH')) {
            $src_file = ltrim(str_replace(ABSPATH, '', $src_file), '/');
        }
        $src_tag = "src:$src_file:$src_line" . ($src_func ? " $src_func()" : '');

        // Extras: IP + DB (useful in multi-env)
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'CLI';
        $db = defined('DB_NAME') ? DB_NAME : '';

        // Optional context dump (kept compact)
        $ctx = '';
        if (!empty($context)) {
            $ctx = ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        // Final line (Adi-tehnic style)
        $timestamp = function_exists('current_time') ? current_time('Y-m-d H:i:s') : date('Y-m-d H:i:s');
        $log_file = vogo_get_log_file();
        error_log(sprintf(
            '[%s] [%s] [%s] [rid:%s] [%s] [%s] [db:%s] [ip:%s] %s%s',
            $timestamp,
            $module,
            $user_tag,
            $req_id,
            $hook_tag,
            $src_tag,
            $db,
            $ip,
            (string) $message,
            $ctx
        ) . PHP_EOL, 3, $log_file);
    }
}

/* stie si de modul */
function vogo_error_log2($message, $module = 'vogo-core') {
    if (!defined('ADVANCED_DEBUG') || ADVANCED_DEBUG !== true) {
        return; // 🔇 Logging oprit
    }

    // 🔖 Scriem în log cu etichetă de modul
    error_log("[$module] $message");
}


function always_allow() {
    vogo_error_log2("[PERMISSION DEBUG] [TEST] Forced allow via always_allow()");
    return true;
}

function always_deny() {
    vogo_error_log2("[PERMISSION DEBUG] [TEST] Forced deny via always_deny()");
    return false;
}


/**
 * Permission mapping section.
 * Applies route-based access control validation for all REST endpoints.
 */
function vogo_permission_check(WP_REST_Request $request) {
    global $MODULE_PHP;
    $module = $MODULE_PHP.'.vogo_permission_check';
    $route = $request->get_route(); // 
    // STEP 1: Extract route key for permission mapping
    $full_route = $request->get_route(); // e.g., /vogo/v1/predefined_qa
//    $route_key = basename($full_route);  // e.g., predefined_qa
    preg_match('#/v\d+/(.*?)($|/)#', $route, $matches);
    $route_key = $matches[1] ?? 'undefined';
    vogo_error_log2("[PERMISSION DEBUG] STEP 1: Extracted route_key: {$route_key}");

    vogo_error_log2("[PERMISSION DEBUG] STEP 1.1: Checking access for route: {$route_key}");


      
    // STEP 1.2: Explicit role errors only for selected groups (transport, client, vendor/orders-provider)
    $uid = get_current_user_id();
    $uname = '';
    $jwt_username = '';
    if ($route_key === 'public') {
    $req_user = strtolower(trim(get_username_from_request()));
    if ($req_user === 'app_mobile_general@vogo.family') { return true; } // safety fast-allow for legacy public login flow
    }

    // STEP 1.3: For non-public routes extract JWT user and used to response
    if ($route_key !== 'public') {
    $jwt_uid = extract_user_from_jwt_token($request);
    if ($jwt_uid instanceof WP_REST_Response) { return $jwt_uid; }
    $jwt_user_id = (int)$jwt_uid;
    $jwt_u = get_user_by('id', $jwt_user_id);
    $jwt_username = $jwt_u ? $jwt_u->user_login : '';
    vogo_error_log2("[PERMISSION DEBUG] STEP 1.3: JWT user extracted id={$jwt_user_id} username={$jwt_username}");
    }
     $uname = $jwt_username;
    // ✅ BC respected, ✅ LOCK respected, ✅ SQL validated (N/A: PHP-only)


    switch ($route_key) {
        case 'transport':
            if ( ! is_user_transporter_connected() ) {
                vogo_error_log2("[PERMISSION DEBUG] STEP 1.2: Missing required role: transporter/drive");
                return new WP_Error(
                    'rest_forbidden_role',
                    'Missing required role: transporter/drive', 
                    ['status'=>403,'required_role'=>'transporter','user_id'=>(int)$uid,'username'=>$uname,'module_bke' => $module]
                );
            }
            break;

        case 'client':
            if ( ! is_user_customer_connected() ) {
                vogo_error_log2("[PERMISSION DEBUG] STEP 1.2: Missing required role: customer");
                return new WP_Error(
                    'rest_forbidden_role',
                    'Missing required role: customer',
                    ['status'=>403,'required_role'=>'customer','user_id'=>(int)$uid,'username'=>$uname,'module_bke' => $module]
                );
            }
            break;

        case 'public':
            if ( ! is_user_allowed_for_public_api() ) {
                vogo_error_log2("[PERMISSION DEBUG] STEP 1.2: Missing required role: public user app_mobile_general@vogo.family");
                return new WP_Error(
                    'rest_forbidden_role',
                    'Missing user: app_mobile_general@vogo.family',
                    ['status'=>403,'required_user'=>'app_mobile_general@vogo.family','user_id'=>(int)$uid,'username'=>$uname,'module_bke' => $module]
                );
            }
            break;            

        case 'vendor':
        case 'provider':            
            // dacă permiți și admin aici, păstrează-l; altfel elimină current_user_can
            if ( ! is_user_vendor_connected() && ! current_user_can('administrator') ) {
                vogo_error_log2("[PERMISSION DEBUG] STEP 1.2: Missing required role: vendor/provider");
                return new WP_Error(
                    'rest_forbidden_role',
                    'Missing required role: vendor',
                    ['status'=>403,'required_role'=>'vendor','user_id'=>(int)$uid,'username'=>$uname,'module_bke' => $module]
                );
            }
            break;            
        case 'orders-provider':
            // dacă permiți și admin aici, păstrează-l; altfel elimină current_user_can
            if ( ! is_user_vendor_connected() && ! current_user_can('administrator') ) {
                vogo_error_log2("[PERMISSION DEBUG] STEP 1.2: Missing required role: vendor/provider");
                return new WP_Error(
                    'rest_forbidden_role',
                    'Missing required role: vendor',
                    ['status'=>403,'required_role'=>'vendor','user_id'=>(int)$uid,'username'=>$uname,'module_bke' => $module]
                );
            }
            break;

        default:
            // pentru toate celelalte rute -> comportament neschimbat (map-ul existent)
            break;
    }
    /* ✅ companatibility respectat, ✅ LOCK format respectat, ✅ SQL validat (N/A) */



    // STEP 2: Attempt to get current user ID from WordPress
    $current_user_id = get_current_user_id();
    $current_user = ($current_user_id) ? get_user_by('ID', $current_user_id) : null;
    if ($current_user) {
        vogo_error_log2("[PERMISSION DEBUG] STEP 2: WordPress user detected: ID {$current_user_id}");
    } else {
        vogo_error_log2("[PERMISSION DEBUG] STEP 2: No WordPress user detected.");
    }

    // STEP 3: Define permission map for routes
    $permission_map = [

//brand activation first step - public route, only for        
    'activateAndGetUpdates' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],      

//social
    'social' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],  
    'google-register' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],      
//nomenc        
    'lov' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],  

//upload document
    'upload-document' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],     
    'chat-upload-document' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],        
    'get-vogo-documents' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],          
    'debug' => [
        'always_allow',
    ],    

//payment - stripe

    'payment' => [
        'is_user_logged_in',
    ],  


//forum    

    'forum-chat' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'forum_post_answer' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
//user account

    'account' => [
        'is_user_logged_in',
    ],

    'my-account' => [
        'is_user_logged_in',
    ],

    'get_shippment_address' => [
        'is_user_logged_in',
    ],
    
    'get_shippment_address' => [
        'is_user_logged_in',
    ],    
    
//login - register


    'register_user' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'set_user_data' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'get_user_data' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'login_using_otp' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],


      // 🔹 Chatbot QA  /////////////////////////////////////////////////////////////////////
    'predefined_qa' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    'search_by_keyword' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Agenda (To-do)
    'agendaAddItem' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    'agendaShowUserItems' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    'agendaMarkDone' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    'agendaDeleteItem' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Shopping List
    'shopListAddItem' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    'shopListShowUserItems' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    'shopListMarkDone' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    'shopListDeleteItem' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    //documents endpoints  /////////////////////////////////////////////////////////////////////////////////////////////////
    'get_required_documents_by_role' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
        'admin_config' => [
            'current_user_can_admin',
        ],
    // 🔹 Ensure user document folder exists (e.g. /user-documents/{user_id}/)
    // Called by mobile app before uploading any user file
    'ensure-user-doc-dir' => [
        'validate_general_mobile_client_app_jwt', // ✅ Mobile app access with valid JWT
        'is_user_logged_in',                      // ✅ Allow fallback to logged-in WP users
    ],

    // 🔹 Save document metadata (name, description, status)
    // Called after file upload to store document info in DB
    'user_document_data' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Upload the actual document file (multipart/form-data)
    // Used by mobile app to upload file content to user-documents/{user_id}/{document_id}
    'upload-user-document' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Upload the actual document file (multipart/form-data)
    // Used by mobile app to upload file content to user-documents/{user_id}/{document_id}
    'get-user-doc' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],    

    // 🔹 Alternate upload route (legacy or fallback)
    // Performs the same logic as upload-user-document
    'upload-user-doc' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Get list of required documents for a given user role
    // Used to show document checklist in mobile onboarding
    'get_required_documents_by_role' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
// forum ///////////////////////////////////////////////////////////////////////////////////////////////////////////
    // 🔹 Create a new forum post (initial question/thread)
    // Called by mobile app when user submits a topic in the forum
    'forum_post' => [
        'validate_general_mobile_client_app_jwt', // ✅ JWT from mobile client
        'is_user_logged_in',                      // ✅ Or WP logged-in user
    ],

    // 🔹 Add an answer to a forum post
    // Triggered when user replies to a topic (1st level reply)
    'forum_post_answer' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Add a nested reply to a forum answer (2nd level reply)
    // Used for replying to specific users inside a thread
    'forum_post_answer_reply' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
///general ////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // 🔹 Retrieve list of predefined areas of interest
    // Used in mobile onboarding or filters
    'get_areas_of_interest' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Retrieve available cities (for filters, shipping, profiles, etc.)
    // Called by mobile client for dynamic select lists
    'get_cities' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    'vogofamily' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],    

    // 🔹 Add a new user role dynamically (dev/admin use)
    // ⚠️ Open access temporarily, should be protected in production
    'add_role' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    //// login - register ////////////////////////////////////////////////////////////////////////////////////

    // 🔹 Change password by username
    // Accepts username + new password, no login required
    'change_password_by_username' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Login via third-party provider (email, Google, etc.)
    'login_provider' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 JWT login (legacy)
    'login_jwt' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Main JWT login handler
    'login' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Send OTP to email or phone
    'send_otp' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Verify OTP and complete login
    'verify_otp' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    
    //// login specials roldes vendor client transporter//////////////////////////////////////////////////////////////////////
    'vendor/login_jwt' => [
        'validate_general_mobile_client_app_jwt',
    ],    

    // 🔹 Vendor report – performance metrics
    // Returns total orders, delivery rate, and other KPIs for vendor dashboard
    'vendor_report/performance' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Vendor report – low stock alerts
    // Lists products with stock below defined thresholds
    'vendor_report/low_stock' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Vendor report – profitability analysis
    // Computes margin, cost vs revenue per product or order
    'vendor_report/profitability' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],


    'support' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],


    ////// OLD STYLE //////////////////////////////////////////////

  // 🔹 Authentication
    'login' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'mobile-auth' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'SignUp' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'forgot' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'logout' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Profile
    'view-profile' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Products & Categories
    'category-list' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'brands' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'product-list' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'product-detail' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'products' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],    
    'products' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],       
    'product' => [
    'validate_general_mobile_client_app_jwt',
    'is_user_logged_in',
    ],

    // 🔹 Cart
    'cart' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],    
    'addtocart' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'cartlist' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'remove-product-cart' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'update-cart' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'empty-cart' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Address
    'Update-Address' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'Address-List' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Checkout
    'checkout' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Wishlist
    'add-to-wishlist' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'showwishlist' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'product-remove-wishlist' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Coupons
    'coupons' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Reviews
    'product-rate-and-review' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'product-review' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'order-review' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Orders

    

    'orders-provider' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'orders-client' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],        
    'order-list' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
    'order' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],    
    'client' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],        
    'order-detail' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Notifications
    'notification-list' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Social login
    'Social_login' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

    // 🔹 Password change
    'change-password' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],
        

//providers    
    'provider' => [
        'validate_general_mobile_client_app_jwt',
        'is_user_logged_in',
    ],

//transport    
    'transport' => [
        'is_user_transporter_connected',
    ],    

//client    
    'client' => [
        'is_user_customer_connected',
    ],        
];

    // STEP 4: Extract validators for the current route
    $validators = $permission_map[$route_key] ?? [];
    vogo_error_log2("[PERMISSION DEBUG] STEP 4: Validators for {$route_key}: " . implode(', ', $validators));

    // STEP 5: Run each validator
    foreach ($validators as $validator) {
        if (is_callable($validator)) {
            $result = call_user_func($validator);
            vogo_error_log2("[PERMISSION DEBUG] STEP 5: {$validator} returned: " . var_export($result, true));
            if ($result === true) {
                vogo_error_log2("[PERMISSION DEBUG] STEP 6: ✅ Access granted via: {$validator}");
                return true;
            }
        } else {
            vogo_error_log2("[PERMISSION DEBUG] STEP 5: ⚠️ {$validator} is not callable");
        }
    }

    // STEP 6: Access denied
        vogo_error_log2("[PERMISSION DEBUG] STEP 6: ❌ Access denied. No valid permission validator passed for {$route_key}");
        return new WP_Error(
        'rest_forbidden_permission',                            // custom code (not the generic one)
        'Permission denied for this route.',                    // clear message
        ['status'=>403,'route'=>$route_key,'module_bke' => $module,'user_id'=>(int)$uid,'username'=>$uname]
        ); // always return WP_Error on deny
        // ✅ BC respected, ✅ LOCK respected, ✅ SQL validated (N/A: PHP-only)


    return false;
}


/* used for jwt verification / authorization */

function vogo_verify_jwt_auth() {
    $headers = getallheaders();

    // 🧩 [STEP 1] Verificăm existența headerului Authorization
    if (!isset($headers['Authorization'])) {
        error_log('[vogo-api][STEP 1] ❌ Missing Authorization header');
        return false;
    }

    $auth_header = trim($headers['Authorization']);

    // 🧹 [STEP 2] Extragem tokenul după prefixul "Bearer "
    if (stripos($auth_header, 'Bearer ') === 0) {
        $token = trim(substr($auth_header, 7));
        error_log('[vogo-api][STEP 2] ✅ Token extras din Bearer');
    } else {
        error_log('[vogo-api][STEP 2] ❌ Invalid Bearer format: ' . $auth_header);
        return false;
    }

    // 🛡️ [STEP 3] Decodăm JWT și verificăm user_id
    try {
        $secret = 'mcFX<|s!tEYt(7vTQFJB}F|Y|6]>/a_W6|vBi-j?7pE>b0-eHuQT;,?5)mY$2ou1'; // ← înlocuiește cu secretul tău
        $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));

        if (!isset($decoded->user_id)) {
            error_log('[vogo-api][STEP 3] ❌ JWT valid dar fără user_id');
            return false;
        }

        // 👤 [STEP 4] Setăm utilizatorul curent în WordPress
        wp_set_current_user($decoded->user_id);
        error_log('[vogo-api][STEP 4] ✅ User autentificat cu ID: ' . $decoded->user_id);

        return true;
    } catch (Exception $e) {
        error_log('[vogo-api][STEP 3] ❌ JWT decoding failed: ' . $e->getMessage());
        return false;
    }
}



/** HELPERE
 * vogo_get_client_ip
 *
 * Returns the client's real IP address, considering reverse proxies.
 */
function vogo_get_client_ip() {
    // Check HTTP_CLIENT_IP header (some proxies set this)
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
    }

    // Check HTTP_X_FORWARDED_FOR (may contain multiple comma-separated IPs)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        // Take the first IP (the originating client)
        return sanitize_text_field(trim($ipList[0]));
    }

    // Fallback to REMOTE_ADDR (the direct connection IP)
    return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
}

// Validate allowed statuses
function vogo_valid_status($status) {
    return in_array($status, ['publish', 'draft', 'pending']);
}

// ==========================================================
// Rate limit helper with per-endpoint buckets
// ==========================================================
function vogo_rate_limit($route) {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $key = 'vogo_api_limit_' . md5($ip . $route);

    // [STEP 1] Define endpoint groups
    $limit_1s   = ['forum_post_answer','/vogo/v1/forum-chat/', '/vogo/v1/my-account/chat'];  // example: chat endpoints
    $limit_10s  = ['/vogo/v1/search_by_keyword', '/vogo/v1/searchByText']; // example: product search
    $limit_100s = ['/vogo/v1/provider/accepting_orders']; // example: heavy operations

    // [STEP 2] Pick rate limit by matching route
    $seconds = 10; // default
    foreach ($limit_1s as $p)   if (stripos($route, $p) !== false) { $seconds = 1; break; }
    foreach ($limit_10s as $p)  if (stripos($route, $p) !== false) { $seconds = 10; break; }
    foreach ($limit_100s as $p) if (stripos($route, $p) !== false) { $seconds = 100; break; }

    // [STEP 3] Check transient
    if (false !== get_transient($key)) return true;
    set_transient($key, 1, $seconds);
    return false;
}


// Consistent response helper
function vogo_response($success, $data = [], $code = 200) {
    return new WP_REST_Response([
        'success' => $success,
        'data' => $data
    ], $code);
}




function vogo_get_current_vendor_id() {
    $current_user = wp_get_current_user();
    return in_array('vendor', (array) $current_user->roles) ? $current_user->ID : 0;
}

// [ADI-ADD] vogo_find_user_by_phone() — lookup by last 9 digits, format-agnostic (+40/0/space/dash)
// ✅ BC respectat, ✅ LOCK respectat, ✅ SQL validat
  function vogo_find_user_by_phone($phone){
    global $wpdb; $module='find-user-by-phone'; $ip=$_SERVER['REMOTE_ADDR']??'UNKNOWN'; $db=DB_NAME;
    vogo_error_log3("VOGO_LOG_START | MODULE:$module | ACTIVE DB:$db | IP:$ip | USER:unknown",$module);

    // STEP 1 - Normalize input to last 9 digits (RO mobile core, e.g., 723313296)
    $digits=preg_replace('/\D+/','',(string)$phone); $last9=substr($digits,-9);
    if(strlen($last9)<9){ vogo_error_log3("[$module] Invalid phone input after normalize | phone:$phone | last9:$last9 | IP:$ip",$module); vogo_error_log3("VOGO_LOG_END",$module); return null; }

    // STEP 2 - SQL lookup in usermeta by last 9 digits (REGEXP_REPLACE to strip non-digits)
    $table = $wpdb->prefix.'usermeta';
    $sql = "SELECT user_id FROM $table WHERE meta_key='phone' AND RIGHT(REGEXP_REPLACE(meta_value,'[^0-9]',''),9)=%s LIMIT 1";
    $prepared = $wpdb->prepare($sql,$last9);
    vogo_error_log3("##############SQL: $prepared",$module);

    $uid = (int)$wpdb->get_var($prepared);
    if($wpdb->last_error){
      vogo_error_log3("[$module] SQL ERROR: ".$wpdb->last_error." | IP:$ip",$module);
    }

    // STEP 3 - Fallback safety (no change of state if null)
    $user = $uid>0 ? get_user_by('id',$uid) : null;
    vogo_error_log3("[$module] Result uid:$uid | found:".($user?'yes':'no')." | IP:$ip",$module);

    vogo_error_log3("VOGO_LOG_END",$module);
    return $user?:null; // safety
  }


function vogo_find_user_by_phone_old($phone) {
    global $wpdb;
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'billing_phone' AND meta_value = %s LIMIT 1",
        $phone
    ));
    return $user_id ? get_user_by('id', $user_id) : null;
}

function vogo_jwt_permission_check() {
    return is_user_logged_in(); // Adjust if using custom JWT check
}

/**
 * Log login attempts
 */
function vogo_log_login($user, $username, $ip, $success) {
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'vogo_audit',
        [
            'user_id' => $user ? $user->ID : null,
            'username' => $username,
            'ip_address' => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'success' => $success,
            'action_code' => 'M-VENDOR-LOGIN',
            'action_info' => 'Login attempt'
        ],
        [
            '%d', '%s', '%s', '%s', '%d', '%s', '%s'
        ]
    );
}


/**
 * Log attempts
 */
function vogo_log_action($user, $username, $ip, $success, $action_code, $action_info) {
    global $wpdb;

    // Check if $user is a WP_User object. If not, treat it as an ID.
    $user_id = is_a($user, 'WP_User') ? $user->ID : $user;

    $wpdb->insert(
        $wpdb->prefix . 'vogo_audit',
        [
            'user_id' => $user_id,
            'username' => $username,
            'ip_address' => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'success' => $success,
            'action_code' => $action_code,
            'action_info' => $action_info
        ],
        [
            '%d', '%s', '%s', '%s', '%d', '%s', '%s'
        ]
    );
}


/**
 * JWT validation for mobile app client – general access
 * This function is used to validate general JWT tokens for mobile client apps (not account-related).
 */
function validate_general_mobile_client_app_jwt() {
    // STEP 1: Retrieve the Authorization header
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$auth_header || !preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        error_log('[JWT DEBUG] STEP 1: Missing or invalid Authorization header');
        return false;
    }

    // STEP 2: Extract JWT token from header
    $jwt_token = $matches[1];
    error_log('[JWT DEBUG] STEP 2: JWT token extracted');

    // STEP 3: Decode JWT using defined global secret and algorithm
    try {
        $decoded = \Firebase\JWT\JWT::decode($jwt_token, new \Firebase\JWT\Key(JWT_AUTH_SECRET_KEY, JWT_ALGO));
        error_log('[JWT DEBUG] STEP 3: JWT decoded successfully');

        // STEP 4: Extract user_id from payload
        $user_id = isset($decoded->user_id) ? (int)$decoded->user_id : 0;
        if (!$user_id) {
            error_log('[JWT DEBUG] STEP 4: user_id missing in payload');
            return false;
        }

        // STEP 5: Fetch user by ID
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            error_log('[JWT DEBUG] STEP 5: User does not exist in WordPress');
            return false;
        }

        // STEP 6: Check if the user has the 'mobile_client' role
        if (!in_array('mobile_client', (array)$user->roles)) {
            error_log('[JWT DEBUG] STEP 6: User does not have "mobile_client" role');
            return false;
        }

        // STEP 7: Set user as current session user
        wp_set_current_user($user_id);
        error_log('[JWT DEBUG] STEP 7: Valid authentication for user_id: ' . $user_id);

        return true;

    } catch (Exception $e) {
        error_log('[JWT DEBUG] STEP 8: JWT decoding error: ' . $e->getMessage());
        return false;
    }
}


/**
 * JWT validation for mobile app vendor – general access
 * This function validates JWT tokens for mobile vendor app access (non-account specific).
 */
function validate_general_mobile_vendor_app_jwt() {
    // STEP 1: Retrieve the Authorization header
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$auth_header || !preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        error_log('[JWT DEBUG] STEP 1: Missing or invalid Authorization header');
        return false;
    }

    // STEP 2: Extract JWT token from header
    $jwt_token = $matches[1];
    error_log('[JWT DEBUG] STEP 2: JWT token extracted');

    // STEP 3: Decode JWT using defined global secret and algorithm
    try {
        $decoded = \Firebase\JWT\JWT::decode($jwt_token, new \Firebase\JWT\Key(JWT_AUTH_SECRET_KEY, JWT_ALGO));
        error_log('[JWT DEBUG] STEP 3: JWT decoded successfully');

        // STEP 4: Extract user_id from token payload
        $user_id = isset($decoded->user_id) ? (int)$decoded->user_id : 0;
        if (!$user_id) {
            error_log('[JWT DEBUG] STEP 4: user_id missing in payload');
            return false;
        }

        // STEP 5: Load user from WordPress by ID
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            error_log('[JWT DEBUG] STEP 5: User not found in WordPress');
            return false;
        }

        // STEP 6: Ensure user has 'mobile_vendor' role
        if (!in_array('mobile_vendor', (array)$user->roles)) {
            error_log('[JWT DEBUG] STEP 6: User does not have "mobile_vendor" role');
            return false;
        }

        // STEP 7: Set the current user for the session
        wp_set_current_user($user_id);
        error_log('[JWT DEBUG] STEP 7: Valid authentication for user_id: ' . $user_id);

        return true;

    } catch (Exception $e) {
        error_log('[JWT DEBUG] STEP 8: JWT decoding failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * JWT validation for mobile app transport – general access
 * This function validates JWT tokens for mobile transport app access (non-account specific).
 */
function validate_general_mobile_transport_app_jwt() {
    // STEP 1: Retrieve the Authorization header
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$auth_header || !preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        error_log('[JWT DEBUG] STEP 1: Missing or invalid Authorization header');
        return false;
    }

    // STEP 2: Extract JWT token from header
    $jwt_token = $matches[1];
    error_log('[JWT DEBUG] STEP 2: JWT token extracted');

    // STEP 3: Decode JWT using defined global secret and algorithm
    try {
        $decoded = \Firebase\JWT\JWT::decode($jwt_token, new \Firebase\JWT\Key(JWT_AUTH_SECRET_KEY, JWT_ALGO));
        error_log('[JWT DEBUG] STEP 3: JWT decoded successfully');

        // STEP 4: Extract user_id from token payload
        $user_id = isset($decoded->user_id) ? (int)$decoded->user_id : 0;
        if (!$user_id) {
            error_log('[JWT DEBUG] STEP 4: user_id missing in payload');
            return false;
        }

        // STEP 5: Load user from WordPress by ID
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            error_log('[JWT DEBUG] STEP 5: User not found in WordPress');
            return false;
        }

        // STEP 6: Ensure user has 'mobile_transport' role
        if (!in_array('mobile_transport', (array)$user->roles)) {
            error_log('[JWT DEBUG] STEP 6: User does not have "mobile_transport" role');
            return false;
        }

        // STEP 7: Set the current user for the session
        wp_set_current_user($user_id);
        error_log('[JWT DEBUG] STEP 7: Valid authentication for user_id: ' . $user_id);

        return true;

    } catch (Exception $e) {
        error_log('[JWT DEBUG] STEP 8: JWT decoding failed: ' . $e->getMessage());
        return false;
    }
}



/**
 * JWT validation for general mobile app users (role: mobile_general)
 * Use this for endpoints requiring read-only or generic access (not tied to orders/accounts).
 * Checks if token is valid and role is 'mobile_general'.
 */
function validate_general_mobile_general_app_jwt() {
    // STEP 1: Read Authorization header
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$auth_header || !preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        error_log('[JWT DEBUG] STEP 1: Missing or malformed Authorization header');
        return false;
    }

    // STEP 2: Extract JWT token from header
    $jwt_token = $matches[1];
    error_log('[JWT DEBUG] STEP 2: JWT token extracted');

    // STEP 3: Decode JWT using global constants
    try {
        $decoded = \Firebase\JWT\JWT::decode($jwt_token, new \Firebase\JWT\Key(JWT_AUTH_SECRET_KEY, JWT_ALGO));
        error_log('[JWT DEBUG] STEP 3: JWT decoded successfully');

        // STEP 4: Extract user_id from payload
        $user_id = isset($decoded->user_id) ? (int)$decoded->user_id : 0;
        if (!$user_id) {
            error_log('[JWT DEBUG] STEP 4: Missing user_id in token payload');
            return false;
        }

        // STEP 5: Load user from database
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            error_log('[JWT DEBUG] STEP 5: No WordPress user found with that user_id');
            return false;
        }

        // STEP 6: Ensure user has the 'mobile_general' role
        if (!in_array('mobile_general', (array)$user->roles)) {
            error_log('[JWT DEBUG] STEP 6: User does not have role "mobile_general"');
            return false;
        }

        // STEP 7: Authenticate this user for the request
        wp_set_current_user($user_id);
        error_log('[JWT DEBUG] STEP 7: Valid authentication for user_id: ' . $user_id);

        return true;

    } catch (Exception $e) {
        error_log('[JWT DEBUG] STEP 8: JWT decoding error: ' . $e->getMessage());
        return false;
    }
}



/**
 * Reusable HTML email sender with simple theming.
 * $content_html: inner HTML (safe, minimal), will be wrapped in a styled template.
 * $opts keys: 'from_name','from_email','logo_url','brand','cta_url','cta_label','theme' (assoc: bg, card, primary, accent, text, muted)
 */
function vogo_send_mail(string $to, string $subject, string $content_html, array $opts = []) : bool {
  $brand      = $opts['brand']      ?? 'Vogo Family';
  $from_name  = $opts['from_name']  ?? $brand;
  $from_email = $opts['from_email'] ?? 'no-reply@vogo.family';
  $logo_url   = $opts['logo_url']   ?? 'https://www.vogo.family/wp-content/uploads/2024/05/vogo-logo.png';
  $cta_url    = $opts['cta_url']    ?? 'https://www.vogo.family';
  $cta_label  = $opts['cta_label']  ?? 'Open '.$brand;

  $theme = array_merge([
    'bg'      => '#f6f8fb',
    'card'    => '#ffffff',
    'primary' => '#0ea5e9',
    'accent'  => '#22c55e',
    'text'    => '#222222',
    'muted'   => '#6b7280',
  ], $opts['theme'] ?? []);

  $year = date('Y');

  $html = '
  <html><body style="margin:0;background:'.$theme['bg'].';font-family:Arial,Helvetica,sans-serif;color:'.$theme['text'].';">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:'.$theme['bg'].';padding:30px 12px;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:'.$theme['card'].';border-radius:14px;box-shadow:0 6px 22px rgba(28,30,35,.08);overflow:hidden;">
        <tr>
          <td style="padding:28px 24px 10px 24px;text-align:center;">
            <img src="'.$logo_url.'" alt="'.esc_attr($brand).'" style="height:48px;margin-bottom:12px;">
            <h2 style="margin:0;font-size:22px;letter-spacing:.2px;">'.esc_html($subject).'</h2>
          </td>
        </tr>
        <tr><td style="padding:22px 24px;font-size:15px;line-height:1.6;color:'.$theme['text'].';">'.$content_html.'</td></tr>
        <tr>
          <td style="padding:0 24px 24px 24px;text-align:center;">
            <a href="'.esc_url($cta_url).'" style="display:inline-block;padding:12px 22px;background:'.$theme['accent'].';color:#ffffff;text-decoration:none;border-radius:8px;font-size:14px;">'.esc_html($cta_label).'</a>
          </td>
        </tr>
        <tr>
          <td style="background:#f3f4f6;padding:14px 24px;text-align:center;font-size:12px;color:'.$theme['muted'].';">
            © '.$year.' '.esc_html($brand).'. All rights reserved.
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
  </body></html>';

  $headers = [
    'Content-Type: text/html; charset=UTF-8',
    'From: '.$from_name.' <'.$from_email.'>'
  ];
  return @wp_mail($to, $subject, $html, $headers);
}



//users already connected in woo
function rest_check_jwt_is_user_transporter(WP_REST_Request $request){ //user in REST

    //audit for response
  $MODULE_PHP=basename(__FILE__);
  $module = $MODULE_PHP . '.'.__FUNCTION__;    

  $ok = (bool) (is_user_transporter_connected() || is_user_transporter_connected()); // keep original OR logic
  if ($ok) return true;

  // Pull JWT username only for message (does not change decision)
  $uid_or_err   = extract_user_from_jwt_token($request);
  $jwt_username = '';

  // Fallbacks sigure
  $roles = [];
  if (!($uid_or_err instanceof WP_REST_Response)) {
    $u = get_user_by('id', (int)$uid_or_err);
    $jwt_username = ($u instanceof WP_User) ? (string)$u->user_login : '';
    $roles = ($u instanceof WP_User && is_array($u->roles)) ? $u->roles : [];
  } else {
    // dacă extract_user_from_jwt_token a returnat o eroare, o propagăm fără dereferențieri riscante
    return $uid_or_err;
  }

  // Verificare roluri cu fallback (dacă $roles e gol, condiția pică controlat)
  $is_ok = in_array('transporter',$roles,true) || in_array('drive',$roles,true);
  if(!$is_ok){
    return new WP_Error('not_transporter', "rest_check_jwt_is_user_transporter: user $jwt_username is not a transporter", ['status'=>403]);
  }

  // 3) All good.
  return true;  
}


function rest_check_jwt_is_user_client(WP_REST_Request $request){ //user in REST
    //audit for response
  $MODULE_PHP=basename(__FILE__);
  $module = $MODULE_PHP . '.'.__FUNCTION__;    

  $ok = (bool) (is_user_customer_connected() || is_user_client_connected()); // keep original OR logic
  if ($ok) return true;

  // Pull JWT username only for message (does not change decision)
  $uid_or_err   = extract_user_from_jwt_token($request);
  $jwt_username = '';

  // Fallbacks sigure
  $roles = [];
  if (!($uid_or_err instanceof WP_REST_Response)) {
    $u = get_user_by('id', (int)$uid_or_err);
    $jwt_username = ($u instanceof WP_User) ? (string)$u->user_login : '';
    $roles = ($u instanceof WP_User && is_array($u->roles)) ? $u->roles : [];
  } else {
    // dacă extract_user_from_jwt_token a returnat o eroare, o propagăm fără dereferențieri riscante
    return $uid_or_err;
  }

  // Verificare roluri cu fallback (dacă $roles e gol, condiția pică controlat)
  $is_ok = in_array('client',$roles,true) || in_array('customer',$roles,true);
  if(!$is_ok){
    return new WP_Error('not_transporter', "rest_check_jwt_is_user_client: user $jwt_username is not a client", ['status'=>403]);
  }

  // 3) All good.
  return true;  
}

function rest_check_jwt_is_user_vendor(WP_REST_Request $request){ //user in REST
    //audit for response
  $MODULE_PHP=basename(__FILE__);
  $module = $MODULE_PHP . '.'.__FUNCTION__;    

  $ok = (bool) (is_user_vendor_connected()); // keep original OR logic
  if ($ok) return true;

  // Pull JWT username only for message (does not change decision)
  $uid_or_err   = extract_user_from_jwt_token($request);
  $jwt_username = '';

  // Fallbacks sigure
  $roles = [];
  if (!($uid_or_err instanceof WP_REST_Response)) {
    $u = get_user_by('id', (int)$uid_or_err);
    $jwt_username = ($u instanceof WP_User) ? (string)$u->user_login : '';
    $roles = ($u instanceof WP_User && is_array($u->roles)) ? $u->roles : [];
  } else {
    // dacă extract_user_from_jwt_token a returnat o eroare, o propagăm fără dereferențieri riscante
    return $uid_or_err;
  }

  // Verificare roluri cu fallback (dacă $roles e gol, condiția pică controlat)
  $is_ok = in_array('vendor',$roles,true) || in_array('provider',$roles,true);
  if(!$is_ok){
    return new WP_Error('not_transporter', "rest_check_jwt_is_user_vendor: user $jwt_username is not a vendor", ['status'=>403]);
  }

  // 3) All good.
  return true;  
}
//boolean check for wooconnected users for current sesion

function is_user_client_connected(){
  return  is_user_customer_connected();
}

function is_user_transporter_connected(){ //retun boolean - not use in REST
  if ( ! is_user_logged_in() ) { return false; }
  $u = wp_get_current_user();
  if ( ! ($u instanceof WP_User) ) { return false; }
  $roles = (array) $u->roles;
  return in_array('transporter', $roles, true);
}

//users already connected in woo //retun boolean - - not use in REST
function is_user_customer_connected(){
  if ( ! is_user_logged_in() ) { return false; }
  $u = wp_get_current_user();
  if ( ! ($u instanceof WP_User) ) { return false; }
  $roles = (array) $u->roles;
  return in_array('customer', $roles, true);
}

//users already connected in woo //retun boolean - - not use in REST
function is_user_vendor_connected(){
  if ( ! is_user_logged_in() ) { return false; }
  $u = wp_get_current_user();
  if ( ! ($u instanceof WP_User) ) { return false; }
  $roles = (array) $u->roles;
  return in_array('vendor', $roles, true) || in_array('provider', $roles, true);
}

// JWT-based vendor check (vendor or provider). 
function is_allowed_for_login_vendor(WP_REST_Request $request){

// user din Bearer JWT
  $user_or_err = extract_user_from_jwt_token($request);
  if($user_or_err instanceof WP_REST_Response){ return false; }

  $u = get_user_by('id', (int)$user_or_err);
  if(!($u instanceof WP_User)){ return false; }

  $roles = (array)$u->roles;
  $allowed = ['mobile_general','mobile_client','mobile_vendor','mobile_transport'];

// 1) If JWT user does NOT have any allowed mobile_* role → return false.
  $has_allowed = false;
  foreach($allowed as $r){ if(in_array($r,$roles,true)){ $has_allowed = true; break; } }
  if(!$has_allowed){ return false; }

  // 2) JWT ok → verify that the requested username is a VENDOR.
  $p = $request->get_json_params();
  $username = sanitize_text_field($p['username'] ?? '');
  if($username === ''){ return new WP_Error('username_required','username required', ['status'=>400]); }

  // Accept username or email
  $target_user = get_user_by('login', $username);
  if(!($target_user instanceof WP_User) && is_email($username)){
    $target_user = get_user_by('email', $username);
  }
  if(!($target_user instanceof WP_User)){
    return new WP_Error('user_not_found',"user $username not found", ['status'=>404]);
  }

    $tr = (array)$target_user->roles;
    if(!(in_array('vendor',$tr,true) || in_array('provider',$tr,true))){
    return new WP_Error('not_vendor_or_provider', "user $username is not a vendor/provider.", ['status'=>403]);
    }

  // 3) All good.
  return true;
}  



// JWT-based client check (client or customer).
function is_allowed_for_login_client(WP_REST_Request $request){
  // 1) JWT user from Bearer
  $user_or_err = extract_user_from_jwt_token($request);

  // Normalize extractor failures to WP_Error, preserving original code/message/status
  if ($user_or_err instanceof WP_Error) {
    return $user_or_err; // ex: code=jwt_expired, status=401
  }
  if ($user_or_err instanceof WP_REST_Response) {
    $status = method_exists($user_or_err,'get_status') ? $user_or_err->get_status() : 401;
    $data   = method_exists($user_or_err,'get_data')   ? $user_or_err->get_data()   : null;
    $code   = is_array($data) ? ($data['code'] ?? $data['error'] ?? 'jwt_invalid') : 'jwt_invalid';
    $msg    = is_array($data) ? ($data['message'] ?? $data['error_description'] ?? 'Invalid or expired token') : 'Invalid or expired token';
    return new WP_Error($code, $msg, ['status'=>$status]);
  }

  // 2) Resolve JWT user
  $u = get_user_by('id', (int)$user_or_err);
  if (!($u instanceof WP_User)) {
    return new WP_Error('jwt_user_not_found','User linked to JWT was not found', ['status'=>401]);
  }

  // 3) JWT user must have one of the allowed mobile_* roles
  $roles   = (array)$u->roles;
  $allowed = ['mobile_general','mobile_client','mobile_vendor','mobile_transport'];
  if (!array_intersect($allowed, $roles)) {
    return new WP_Error('jwt_not_authorized_app','JWT user not authorized for this app', ['status'=>403]);
  }

  // 4) Verify target username (login/email) is client/customer
  $username = trim((string)$request->get_param('username')); // GET/POST/JSON safe
  if ($username === '') {
    return new WP_Error('username_required','username required', ['status'=>400]);
  }

  $target_user = get_user_by('login', $username);
  if (!($target_user instanceof WP_User) && is_email($username)) {
    $target_user = get_user_by('email', $username);
  }
  if (!($target_user instanceof WP_User)) {
    return new WP_Error('user_not_found',"user $username not found", ['status'=>404]);
  }

  $tr = (array)$target_user->roles;
  if (!(in_array('client',$tr,true) || in_array('customer',$tr,true))) {
    return new WP_Error('not_customer', "user $username is not a customer/client.", ['status'=>403]);
  }

  return true;
}


// JWT-based vendor check (transporter). 
function is_allowed_for_login_transport(WP_REST_Request $request){
// user din Bearer JWT
  $user_or_err = extract_user_from_jwt_token($request);
  if($user_or_err instanceof WP_REST_Response){ return false; }

  $u = get_user_by('id', (int)$user_or_err);
  if(!($u instanceof WP_User)){ return false; }

  $roles = (array)$u->roles;
  $allowed = ['mobile_general','mobile_client','mobile_vendor','mobile_transport'];

// 1) If JWT user does NOT have any allowed mobile_* role → return false.
  $has_allowed = false;
  foreach($allowed as $r){ if(in_array($r,$roles,true)){ $has_allowed = true; break; } }
  if(!$has_allowed){ return false; }

  // 2) JWT ok → verify that the requested username is a VENDOR.
  $p = $request->get_json_params();
  $username = sanitize_text_field($p['username'] ?? '');
  if($username === ''){ return new WP_Error('username_required','username required', ['status'=>400]); }

  // Accept username or email
  $target_user = get_user_by('login', $username);
  if(!($target_user instanceof WP_User) && is_email($username)){
    $target_user = get_user_by('email', $username);
  }
  if(!($target_user instanceof WP_User)){
    return new WP_Error('user_not_found',"user $username not found", ['status'=>404]);
  }

    $tr = (array)$target_user->roles;
    if(!(in_array('transporter',$tr,true) || in_array('drive',$tr,true))){
    return new WP_Error('not_transporter', "user $username is not a transporter/drive.", ['status'=>403]);
    }

  // 3) All good.
  return true;
}


// JWT-based vendor check - first app token
function is_allowed_for_obtain_public_token() {
  $allowed_email = 'app_mobile_general@vogo.family'; // Whitelisted email

  // Extract "username" from JSON body; fallback to request params
  $raw = file_get_contents('php://input');
  $json = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
  $current = isset($json['username']) ? $json['username'] : (isset($_REQUEST['username']) ? $_REQUEST['username'] : 'unknown');

  // Normalize and compare
  $current_email = strtolower(trim((string)$current));
  $allowed = ($current_email === strtolower($allowed_email));

  // Log only on deny (expected vs current)
  if (!$allowed) { vogo_error_log3(sprintf('PUBLIC_API_DENY expected=%s current=%s', $allowed_email, $current_email)); }

  return $allowed;
}

function get_username_from_request() {
  $raw = file_get_contents('php://input');
  $json = (is_string($raw) && $raw !== '') ? json_decode($raw, true) : null;
  if (is_array($json) && isset($json['username'])) { return (string)$json['username']; }
  if (isset($_REQUEST['username'])) { return (string)$_REQUEST['username']; }
  return '';
}
/* ===============================================================
 * [ADI-ADD] checkBearer()
 * Acts as inline JWT validation filter.
 * - If invalid, sends JSON error response and terminates execution.
 * - If valid, continues silently.
 * Usage inside endpoint: checkBearer($request,'module_name');
 * =============================================================== */
/* [ADI-ADD] checkBearer() – filter-only, no payload shape assumption */
/* Usage: checkBearer($request,'all-account.php.update_address'); */
function checkBearer(WP_REST_Request $request, $module='generic'){
  $auth=$request->get_header('authorization');
  if(!$auth){ wp_send_json(['success'=>false,'error'=>'missing_bearer','module'=>$module],401); }
  if(!preg_match('/Bearer\s+(.+)/i',$auth,$m)){
    wp_send_json(['success'=>false,'error'=>'invalid_bearer_format','module'=>$module],401);
  }
  $token=trim($m[1]); if($token===''){
    wp_send_json(['success'=>false,'error'=>'invalid_bearer_empty','module'=>$module],401);
  }
  try{
    Firebase\JWT\JWT::decode($token,new Firebase\JWT\Key(VOGO_API_KEY,'HS256'));
    // Do not assert payload fields; only signature/exp validity.
  }catch(Firebase\JWT\ExpiredException $e){
    wp_send_json(['success'=>false,'error'=>'expired_bearer','module'=>$module],401);
  }catch(\Throwable $e){
    wp_send_json(['success'=>false,'error'=>'invalid_bearer','detail'=>$e->getMessage(),'module'=>$module],401);
  }
  /* valid → continue silently */
}

function sync_vogo_user_info($user_id){
    global $wpdb;
    $uid = (int)$user_id;
    if (!$uid) return false;

    $table = $wpdb->prefix . 'vogo_user_info';
    $m = function($k) use ($uid){ return get_user_meta($uid, $k, true); };
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table` WHERE user_id = %d LIMIT 1", $uid), ARRAY_A) ?: [];
    $pick = function($primary, $fallback){
        return ($primary !== null && $primary !== '') ? $primary : ($fallback ?? null);
    };
    $pick_int = function($primary, $fallback){
        if ($primary !== null && $primary !== '') {
            return (int)$primary;
        }
        return ($fallback !== null && $fallback !== '') ? (int)$fallback : null;
    };

    $addr1 = (string)$m('billing_address_1');
    $addr2 = (string)$m('billing_address_2');
    $address = trim($addr1 . ' ' . $addr2);

    $data = [
        'user_id'            => $uid,
        'company_name'       => $pick($m('billing_company'), $existing['company_name'] ?? null),
        'fiscal_code'        => $pick(($m('billing_vat') ?: $m('cui') ?: $m('fiscal_code')), $existing['fiscal_code'] ?? null),
        'area_of_interest_id'=> $pick_int($m('area_of_interest_id'), $existing['area_of_interest_id'] ?? null),
        'address'            => $pick(($address !== '' ? $address : null), $existing['address'] ?? null),
        'shop_no'            => $pick($m('shop_no'), $existing['shop_no'] ?? null),
        'floor'              => $pick($m('floor'), $existing['floor'] ?? null),
        'building_name'      => $pick($m('building_name'), $existing['building_name'] ?? null),
        'postcode'           => $pick($m('billing_postcode'), $existing['postcode'] ?? null),
        'latitude'           => $pick($m('latitude'), $existing['latitude'] ?? null),
        'longitude'          => $pick($m('longitude'), $existing['longitude'] ?? null),
        'company_doc'        => $pick($m('company_doc'), $existing['company_doc'] ?? null),
        'phone'              => $pick(($m('billing_phone') ?: $m('phone')), $existing['phone'] ?? null),
        'sms'                => $pick(($m('sms') ?: $m('billing_phone') ?: $m('phone')), $existing['sms'] ?? null),
        'whatsapp'           => $pick(($m('whatsapp') ?: $m('billing_phone') ?: $m('phone')), $existing['whatsapp'] ?? null),
        'default_city_id'    => $pick_int($m('default_city_id'), $existing['default_city_id'] ?? null),
        'client_nickname'    => $pick((function($v){ $v=(string)$v; return $v!=='' ? substr($v,0,10) : null; })($m('nickname') ?: $m('first_name')), $existing['client_nickname'] ?? null),
        'my_referral_code'   => $pick($m('my_referral_code'), $existing['my_referral_code'] ?? null),
        'parent_user_id'     => $pick_int($m('parent_user_id'), $existing['parent_user_id'] ?? null),
    ];

    $formats = [
        '%d','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%d'
    ];

    $id = $existing['id'] ?? $wpdb->get_var($wpdb->prepare("SELECT id FROM `$table` WHERE user_id = %d LIMIT 1", $uid));

    if ($id) {
        $wpdb->update($table, $data, ['id' => (int)$id], $formats, ['%d']);
    } else {
        if (empty($data['my_referral_code'])) {
            $data['my_referral_code'] = 'U' . $uid;
        }
        $data_with_id = ['id' => $uid] + $data;
        $formats_with_id = array_merge(['%d'], $formats);
        $wpdb->insert($table, $data_with_id, $formats_with_id);
    }

    ensure_user_referral_code ($user_id);

    return true;
}


// [ADI-ADD] Geocode and store end_lat/end_long from wp_orders.address_end

/**
 * vogo_order_set_latlong
 * - Reads wp_orders.address_end for given order
 * - Geocodes the address (OpenCage first, fallback Nominatim)
 * - Updates wp_orders.end_lat, wp_orders.end_long
 * - Returns JSON-like array with status and coords
 *
 * Requirements:
 * - Define OPENCAGE_KEY if you want OpenCage (recommended). Otherwise fallback uses Nominatim.
 * - Uses vogo_error_log3() for step-by-step logs (Adi-tehnic).
 */
function vogo_order_set_latlong($order_id){
  global $wpdb; $order_id=(int)$order_id;
  $ip=$_SERVER['REMOTE_ADDR']??'UNKNOWN'; $uid=(int)(get_current_user_id()?:0); $db=DB_NAME;
  vogo_error_log3("VOGO_LOG_START | ACTIVE DB: $db | IP:$ip | USER:$uid | FN:vogo_order_set_latlong | order_id:$order_id");

  // [STEP 1] Load address_end
  $table=$wpdb->prefix.'orders';
  $sql=$wpdb->prepare("SELECT address_end FROM {$table} WHERE id=%d",$order_id);
  vogo_error_log3("##############SQL: $sql | IP:$ip | USER:$uid");
  $address_end=$wpdb->get_var($sql);
  if($wpdb->last_error) vogo_error_log3("[STEP 1 ERR] ".$wpdb->last_error." | IP:$ip | USER:$uid");
  if(!is_string($address_end) || trim($address_end)===''){
    vogo_error_log3("[STEP 1] Empty address_end. Skip geocoding. | IP:$ip | USER:$uid");
    vogo_error_log3("VOGO_LOG_END");
    return ['success'=>false,'error'=>'empty_address_end','order_id'=>$order_id];
  }
  $address=trim($address_end);
  vogo_error_log3("[STEP 2] Address to geocode: ".$address." | IP:$ip | USER:$uid");

  // [STEP 3] Geocode
  $geo=vogo_geocode_address_min($address);
  if(!$geo['success']){
    vogo_error_log3("[STEP 3 ERR] ".$geo['error']." | IP:$ip | USER:$uid");
    vogo_error_log3("VOGO_LOG_END");
    return ['success'=>false,'error'=>$geo['error'],'provider'=>$geo['provider']??null,'order_id'=>$order_id];
  }
  $lat=(float)$geo['lat']; $lng=(float)$geo['lng'];
  vogo_error_log3("[STEP 3 OK] lat=$lat lng=$lng provider=".$geo['provider']." | IP:$ip | USER:$uid");

  // [STEP 4] Update wp_orders
  $sqlU=$wpdb->prepare("UPDATE {$table} SET end_lat=%f, end_long=%f WHERE id=%d",$lat,$lng,$order_id);
  vogo_error_log3("##############SQL: $sqlU | IP:$ip | USER:$uid");
  $ok=$wpdb->query($sqlU);
  if(false===$ok){ vogo_error_log3("[STEP 4 ERR] ".$wpdb->last_error." | IP:$ip | USER:$uid"); vogo_error_log3("VOGO_LOG_END"); return ['success'=>false,'error'=>'sql_update_failed','sql_error'=>$wpdb->last_error,'order_id'=>$order_id]; }

  vogo_error_log3("[STEP 5] Updated end_lat/end_long successfully. | IP:$ip | USER:$uid");
  vogo_error_log3("VOGO_LOG_END");

  return ['success'=>true,'order_id'=>$order_id,'lat'=>$lat,'lng'=>$lng,'provider'=>$geo['provider']];
}

/**
 * vogo_geocode_address_min
 * - Tries OpenCage if OPENCAGE_KEY defined and non-empty
 * - Fallback to Nominatim (OSM). Set a proper User-Agent.
 * - Returns ['success'=>true,'lat'=>..,'lng'=>..,'provider'=>'opencage'|'nominatim'] or error.
 */
function vogo_geocode_address_min($address){
  $address=trim($address); if($address==='') return ['success'=>false,'error'=>'empty_address'];
  $variants=[];
  // v1: original
  $variants[]=$address;
  // v2: fără cod poștal
  $variants[]=preg_replace('/\b\d{4,6}\b/','',$address);
  // v3: ASCII simplu
  $to_ascii=function($s){ $map=['ă'=>'a','â'=>'a','î'=>'i','ș'=>'s','ş'=>'s','ț'=>'t','ţ'=>'t','Ă'=>'A','Â'=>'A','Î'=>'I','Ș'=>'S','Ş'=>'S','Ț'=>'T','Ţ'=>'T']; return strtr($s,$map); };
  $variants[]=$to_ascii($address);
  // v4: “Str nr, Brașov, Romania”
  if(preg_match('/^([^,]+),\s*\d{4,6}\s*([^,]+)/u',$address,$m)) $variants[]=trim($m[1].', '.$m[2].', Romania');

  // OpenCage dacă ai cheie
  if(defined('OPENCAGE_KEY') && OPENCAGE_KEY){
    foreach($variants as $q){
      $url=add_query_arg(['q'=>$q,'key'=>OPENCAGE_KEY,'limit'=>1,'no_annotations'=>1,'language'=>'ro'],'https://api.opencagedata.com/geocode/v1/json');
      $resp=wp_remote_get($url,['timeout'=>12,'headers'=>['User-Agent'=>'vogo.family-geocoder/1.0']]);
      if(!is_wp_error($resp) && wp_remote_retrieve_response_code($resp)===200){
        $b=json_decode(wp_remote_retrieve_body($resp),true);
        if(!empty($b['results'][0]['geometry']['lat']) && !empty($b['results'][0]['geometry']['lng'])){
          return ['success'=>true,'lat'=>$b['results'][0]['geometry']['lat'],'lng'=>$b['results'][0]['geometry']['lng'],'provider'=>'opencage','q'=>$q];
        }
      }
    }
  }

  // Nominatim cu bias România + Brașov
  $bbox='25.45,45.55,25.75,45.75'; // viewbox Brașov aprox: lonmin,latmin,lonmax,latmax
  foreach($variants as $q){
    $args=['q'=>$q,'format'=>'json','limit'=>1,'addressdetails'=>0,'accept-language'=>'ro','countrycodes'=>'ro','bounded'=>1,'viewbox'=>$bbox];
    $url='https://nominatim.openstreetmap.org/search?'.http_build_query($args,'','&',PHP_QUERY_RFC3986);
    $resp=wp_remote_get($url,['timeout'=>12,'headers'=>['User-Agent'=>'vogo.family-geocoder/1.0 (contact@vogo.family)']]);
    if(is_wp_error($resp)) continue;
    $code=wp_remote_retrieve_response_code($resp); if($code==429) return ['success'=>false,'error'=>'rate_limited','provider'=>'nominatim'];
    if($code!==200) continue;
    $b=json_decode(wp_remote_retrieve_body($resp),true);
    if(!empty($b[0]['lat']) && !empty($b[0]['lon'])){
      return ['success'=>true,'lat'=>(float)$b[0]['lat'],'lng'=>(float)$b[0]['lon'],'provider'=>'nominatim','q'=>$q];
    }
  }

  return ['success'=>false,'error'=>'no_result','provider'=>(defined('OPENCAGE_KEY') && OPENCAGE_KEY)?'opencage+nominatim':'nominatim'];
}



function vogo_set_latlong_for_children($parent_id){
  global $wpdb; $parent_id=(int)$parent_id;
  $ip=$_SERVER['REMOTE_ADDR']??'UNKNOWN'; $uid=(int)(get_current_user_id()?:0); $db=DB_NAME;
  vogo_error_log3("VOGO_LOG_START | ACTIVE DB: $db | IP:$ip | USER:$uid | FN:vogo_set_latlong_for_children | parent_id:$parent_id");

  // [STEP 1] Collect child IDs from HPOS
  $tbl_wc=$wpdb->prefix.'wc_orders';
  $sql=$wpdb->prepare("SELECT id FROM {$tbl_wc} WHERE parent_order_id=%d",$parent_id);
  vogo_error_log3("##############SQL: $sql | IP:$ip | USER:$uid");
  $child_ids=$wpdb->get_col($sql);
  if($wpdb->last_error){ vogo_error_log3("[STEP 1 ERR] ".$wpdb->last_error." | IP:$ip | USER:$uid"); vogo_error_log3("VOGO_LOG_END"); return ['success'=>false,'error'=>'sql_error','sql_error'=>$wpdb->last_error]; }
  if(empty($child_ids)){ vogo_error_log3("[STEP 1] No children found | IP:$ip | USER:$uid"); vogo_error_log3("VOGO_LOG_END"); return ['success'=>true,'parent_id'=>$parent_id,'count'=>0,'results'=>[]]; }

  // [STEP 2] Geocode each child using existing function
  $results=[]; foreach($child_ids as $oid){ $results[$oid]=vogo_order_set_latlong((int)$oid); }

  vogo_error_log3("[STEP 3] Done. children=".count($child_ids)." | IP:$ip | USER:$uid");
  vogo_error_log3("VOGO_LOG_END");
  return ['success'=>true,'parent_id'=>$parent_id,'count'=>count($child_ids),'results'=>$results];
}
