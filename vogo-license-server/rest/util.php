<?php
$MODULE_PHP='util.php';
// UTIL.PHP - utilities

add_action('rest_api_init', function () {
    register_rest_route('vogo/v1', '/clone-category/', [
        'methods'             => 'POST',
        'callback'            => 'vogo_clone_category',
        'permission_callback' => '__return_true'
    ]);
});

/**
 * Clone a category using full payload from client
 */

/**
 * Clone a category using full payload from client
 */
function vogo_clone_category(WP_REST_Request $request) {
    // STEP 1: Extract payload and user context
    $data = $request->get_json_params();
    $slug = sanitize_text_field($data['slug'] ?? '');
    $name = sanitize_text_field($data['name'] ?? '');
    $desc = sanitize_textarea_field($data['description'] ?? '');
    $image_url = esc_url_raw($data['thumbnail_url'] ?? '');
    $position = isset($data['position']) ? (int)$data['position'] : 9999;
    $user = get_current_user_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    vogo_error_log3("STEP 1: Clone request for '$slug' from IP $ip", $user, 'category-clone');

    // STEP 1.1: Log active DB
    vogo_error_log3("STEP 1.1: Active DB is: " . DB_NAME, $user, 'category-clone');

    // STEP 2: Check if category already exists
    $existing_term = get_term_by('slug', $slug, 'product_cat');
    if ($existing_term) {
        $term_id = $existing_term->term_id;
        $is_mobile = get_term_meta($term_id, 'mobile_category', true);
        if ($is_mobile === '1') {
            vogo_error_log3("STEP 2: Category '$slug' exists and is already mobile", $user, 'category-clone');
            return ['status' => false, 'message' => "Category '$slug' already exists and is marked as mobile_category = 1"];
        } else {
            vogo_error_log3("STEP 2: Category '$slug' exists but not marked mobile", $user, 'category-clone');
            return ['status' => false, 'message' => "Category '$slug' exists but is not marked as mobile_category = 1"];
        }
    }

    // STEP 3: Insert category
    $insert = wp_insert_term($name, 'product_cat', ['slug' => $slug, 'description' => $desc]);
    if (is_wp_error($insert)) {
        vogo_error_log3("STEP 3: Insertion failed: " . $insert->get_error_message(), $user, 'category-clone');
        return ['status' => false, 'message' => $insert->get_error_message()];
    }

    $new_id = $insert['term_id'];
    vogo_error_log3("STEP 3: Category '$slug' inserted with ID $new_id", $user, 'category-clone');

    // STEP 4: Update term_order
    global $wpdb;
    $updated = $wpdb->update($wpdb->terms, ['term_order' => $position], ['term_id' => $new_id], ['%d'], ['%d']);
    vogo_error_log3("STEP 4: Position set to $position (affected: $updated)", $user, 'category-clone');

    // STEP 5: Handle thumbnail
    if (!empty($image_url)) {
        vogo_error_log3("STEP 5: Sideloading image from $image_url", $user, 'category-clone');
        $tmp = download_url($image_url);
        if (!is_wp_error($tmp)) {
            $file = [
                'name'     => basename($image_url),
                'type'     => mime_content_type($tmp),
                'tmp_name' => $tmp,
                'error'    => 0,
                'size'     => filesize($tmp)
            ];
            $att_id = media_handle_sideload($file, 0);
            if (!is_wp_error($att_id)) {
                update_term_meta($new_id, 'thumbnail_id', $att_id);
                vogo_error_log3("STEP 5: Thumbnail attached with ID $att_id", $user, 'category-clone');
            }
        }
    }

    // STEP 6: Mark as mobile category
    update_term_meta($new_id, 'mobile_category', '1');
    vogo_error_log3("STEP 6: mobile_category set to 1 for term ID $new_id", $user, 'category-clone');

    // STEP 7: Success
    return [
        'status'   => true,
        'message'  => "Category '$slug' cloned successfully and marked as mobile",
        'term_id'  => $new_id,
        'position' => $position
    ];
}




/**
 * Extract user_id from JWT Authorization header (Bearer token)
 * Returns: int $user_id on success OR WP_REST_Response on error
 */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;


function extract_user_from_jwt_token(WP_REST_Request $request) {
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $headers = getallheaders();
  $auth = $headers['Authorization'] ?? '';

  // STEP 1: Extract token from Authorization header
  if (!preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
 //   vogo_error_log3("[STEP 1] MISSING OR INVALID TOKEN | IP: $ip | User: unknown");
    return new WP_REST_Response(['success' => false, 'message' => 'Missing or invalid token'], 401);
  }

  $token = $matches[1];
  $secret_key = 'mcFX<|s!tEYt(7vTQFJB}F|Y|6]>/a_W6|vBi-j?7pE>b0-eHuQT;,?5)mY$2ou1';

  // STEP 2: Validate JWT secret
  if (!$secret_key) {
 //   vogo_error_log3("[STEP 2] MISSING JWT SECRET | IP: $ip | User: unknown");
    return new WP_REST_Response(['success' => false, 'message' => 'JWT secret not configured'], 500);
  }

  // STEP 3: Decode JWT and return user_id
  try {
    $payload = JWT::decode($token, new Key($secret_key, 'HS256'));
    $user_id = intval($payload->user_id ?? 0);
  } catch (\Firebase\JWT\ExpiredException $e) { // [ADI-ADD] explicit expired token handling
    return new WP_REST_Response(['success' => false, 'message' => 'Expired token?'], 401);
  } catch (Exception $e) {
 //    vogo_error_log3("[STEP 3] JWT DECODE FAILED: {$e->getMessage()} | IP: $ip | User: unknown");
    return new WP_REST_Response(['success' => false, 'message' => 'Token decode failed'], 403);
  }

  // STEP 4: Final log and return user_id
  $db_name = DB_NAME;
  vogo_error_log3("[STEP 4] JWT decoded | ACTIVE DB: $db_name | IP: $ip | User: $user_id");

  if (!$user_id) {
    vogo_error_log3("[STEP 5] INVALID user_id in token | IP: $ip | User: $user_id");
    return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 403);
  }

  return $user_id;
}


/**
 * Check if current JWT user has permission to view a document by its ID
 */
/**
 * Check if a user has permission to view a document by its ID
 * @param int $document_id
 * @param int $user_id
 * @return true|WP_REST_Response
 */
function has_permission_to_view_document_by_id($document_id, $user_id) {
    global $wpdb;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $active_db = DB_NAME;
    $table = $wpdb->prefix . 'vogo_documents';

  //  vogo_error_log3("[user:has_permission_to_view_document_by_id | IP: $ip | USER: $user_id] " . VOGO_LOG_START);
  //  vogo_error_log3("[STEP 0.1] Checking permission for document_id: $document_id | IP: $ip | USER: $user_id");
  //  vogo_error_log3("[STEP 1] ACTIVE DB: $active_db | IP: $ip | USER: $user_id");

    $document_id = intval($document_id);
    if ($document_id <= 0) {
        vogo_error_log3("[STEP 2] Invalid document_id: $document_id | IP: $ip | USER: $user_id");
        vogo_error_log3("[user:has_permission_to_view_document_by_id | IP: $ip | USER: $user_id] " . VOGO_LOG_END);
        return new WP_REST_Response(['success' => false, 'error' => 'Invalid document ID'], 400);
    }

    $query = $wpdb->prepare("SELECT * FROM $table WHERE id = %d LIMIT 1", $document_id);
    $document = $wpdb->get_row($query);

    if (!$document) {
        vogo_error_log3("[STEP 3] Document not found for id: $document_id | IP: $ip | USER: $user_id");
        vogo_error_log3("[user:has_permission_to_view_document_by_id | IP: $ip | USER: $user_id] " . VOGO_LOG_END);
        return new WP_REST_Response(['success' => false, 'error' => 'Document not found'], 404);
    }

    // TODO: Activate permission check in production if needed
    if (false) {
        vogo_error_log3("[STEP 4] Access denied to document id: $document_id | IP: $ip | USER: $user_id");
        vogo_error_log3("[user:has_permission_to_view_document_by_id | IP: $ip | USER: $user_id] " . VOGO_LOG_END);
        return new WP_REST_Response(['success' => false, 'error' => 'Access denied'], 403);
    }

    vogo_error_log3("[STEP 5] ✅ Access granted to document id: $document_id | IP: $ip | USER: $user_id");
    vogo_error_log3("[user:has_permission_to_view_document_by_id | IP: $ip | USER: $user_id] " . VOGO_LOG_END);
    return true;
}



/**
 * Clear the contents of wp-content/debug.log file
 */
function clear_debug_log_file() {
   /* $log_path = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($log_path)) {
     //   file_put_contents($log_path, ''); // ✅ Clear file contents
        vogo_error_log3('[STEP 1] debug.log has been cleared | IP: ' . $_SERVER['REMOTE_ADDR'] . ' | USER: system');
        return true;
    } else {
        vogo_error_log3('[STEP 1] debug.log not found at expected path: ' . $log_path . ' | IP: ' . $_SERVER['REMOTE_ADDR'] . ' | USER: system');
        return false;
    }*/
    return true;
}

function woo_has_role($p_user_id, $p_role_cod){
  $uid = (int)$p_user_id;
  if ($uid <= 0) { return false; }

  // Normalize input to an array of lowercase role codes
  $role_list = is_array($p_role_cod) ? $p_role_cod : [$p_role_cod];
  $role_list = array_values(array_filter(array_map(function($r){
    return strtolower(trim((string)$r));
  }, $role_list), function($r){ return $r !== ''; }));

  if (empty($role_list)) { return false; }

  // Resolve user and roles
  $u = get_user_by('id', $uid);
  if (!($u instanceof WP_User)) { return false; }

  $user_roles = array_map('strtolower', (array)$u->roles);

  // Match any role
  foreach ($role_list as $r){
    if (in_array($r, $user_roles, true)) { return true; }
  }
  return false;
}

function woo_grant_role($p_user_id, $p_role_cod){
  $u = get_user_by('id', (int)$p_user_id);
  if (!($u instanceof WP_User)) return false;

  $roles = array_values(array_filter(array_map(fn($r)=>strtolower(trim((string)$r)), (array)$p_role_cod)));
  if (!$roles) return false;

  $cur = array_map('strtolower', (array)$u->roles);
  $valid = array_values(array_filter($roles, fn($r)=>get_role($r)));

  foreach ($valid as $r) if (!in_array($r, $cur, true)) { $u->add_role($r); $cur[] = $r; }

  return (bool) array_intersect($cur, $roles);
}

function product_check_status($product_id){
  global $wpdb;
    //audit for response
  $MODULE_PHP=basename(__FILE__);
  $module = $MODULE_PHP . '.'.__FUNCTION__;
  $module=$module.'.product_id:'.$product_id;

  $row=$wpdb->get_row($wpdb->prepare("SELECT post_status FROM {$wpdb->posts} WHERE ID=%d AND post_type='product' LIMIT 1",$product_id),ARRAY_A);
  if($wpdb->last_error) return new WP_REST_Response(['status'=>false,'module' => $module.'-ES1','error'=>'SQL error','sql_error'=>$wpdb->last_error],500);
  if(!$row) return new WP_REST_Response(['status'=>false,'module' => $module.'-ES1','message'=>'Product not found in product_check_status '],404);
  if($row['post_status']!=='publish') return new WP_REST_Response(['status'=>false,'module' => $module.'-ES1','message'=>'Product found but status not published.','post_status'=>$row['post_status']],200);
  return true;
}
