<?php
/**
 * VOGO Mobile Categories admin module.
 *
 * This file isolates the wp-admin page and related actions for:
 * - selecting mobile categories,
 * - ordering mobile categories,
 * - add/remove/move operations (admin + ajax).
 *
 * It is loaded from brand-options.php to keep the main file focused.
 */

add_action('admin_post_vogo_save_mobile_categories', 'vogo_brand_options_save_mobile_categories');

/**
 * Builds the fully qualified table name used to persist mobile category ordering.
 */
function vogo_brand_options_mobile_categories_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'vogo_mobile_categories';
}

/**
 * Ensures the custom mobile-categories table exists before any read/write operation.
 *
 * The static guard avoids re-running dbDelta multiple times during the same request.
 */
function vogo_brand_options_ensure_mobile_categories_table() {
    global $wpdb;

    static $table_ready = null;
    if ($table_ready !== null) {
        return $table_ready;
    }

    $table_name = vogo_brand_options_mobile_categories_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table_name} (
        term_id BIGINT(20) UNSIGNED NOT NULL,
        position DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (term_id),
        KEY position (position)
    ) {$charset_collate};";

    dbDelta($sql);

    $table_ready = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name);
    if ($table_ready) {
        vogo_brand_options_migrate_mobile_categories_table();
    }

    return $table_ready;
}

/**
 * Migrates legacy `mobile_category` term metadata into the dedicated ordering table.
 *
 * Runs only when the new table is still empty, preserving already curated order.
 */
function vogo_brand_options_migrate_mobile_categories_table() {
    global $wpdb;

    $table_name = vogo_brand_options_mobile_categories_table_name();
    if ($wpdb->get_var("SELECT COUNT(1) FROM {$table_name}") > 0) {
        return;
    }

    $legacy_terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'meta_query' => [
            [
                'key' => 'mobile_category',
                'value' => '1',
            ],
        ],
    ]);

    if (is_wp_error($legacy_terms) || empty($legacy_terms)) {
        return;
    }

    usort($legacy_terms, static function ($a, $b) {
        $meta_a = (int) get_term_meta((int) $a->term_id, 'mobile_category_position', true);
        $meta_b = (int) get_term_meta((int) $b->term_id, 'mobile_category_position', true);

        if ($meta_a < 1) {
            $meta_a = (int) ($a->term_order ?? 0);
        }
        if ($meta_b < 1) {
            $meta_b = (int) ($b->term_order ?? 0);
        }

        if ($meta_a === $meta_b) {
            return strcasecmp($a->name, $b->name);
        }

        return $meta_a <=> $meta_b;
    });

    foreach ($legacy_terms as $index => $term) {
        $wpdb->replace(
            $table_name,
            [
                'term_id' => (int) $term->term_id,
                'position' => round((float) ($index + 1), 2),
                'updated_at' => current_time('mysql', true),
            ],
            ['%d', '%f', '%s']
        );
    }
}

/**
 * Returns active mobile category IDs in deterministic display order.
 */
function vogo_brand_options_get_mobile_category_ids_in_order() {
    global $wpdb;

    if (!vogo_brand_options_ensure_mobile_categories_table()) {
        return [];
    }

    $table_name = vogo_brand_options_mobile_categories_table_name();
    $term_taxonomy = $wpdb->term_taxonomy;

    return array_map('intval', (array) $wpdb->get_col(
        "SELECT vmc.term_id
         FROM {$table_name} vmc
         INNER JOIN {$term_taxonomy} tt ON tt.term_id = vmc.term_id AND tt.taxonomy = 'product_cat'
         ORDER BY vmc.position ASC, vmc.term_id ASC"
    ));
}

/**
 * Returns a [term_id => position] map for quick in-memory lookups.
 */
function vogo_brand_options_get_mobile_category_positions_map() {
    global $wpdb;

    if (!vogo_brand_options_ensure_mobile_categories_table()) {
        return [];
    }

    $table_name = vogo_brand_options_mobile_categories_table_name();
    $rows = (array) $wpdb->get_results("SELECT term_id, position FROM {$table_name}", ARRAY_A);
    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['term_id']] = round((float) $row['position'], 2);
    }

    return $map;
}

/**
 * Persists one category position and refreshes taxonomy cache.
 */
function vogo_brand_options_update_term_order($term_id, $order) {
    global $wpdb;

    if (!vogo_brand_options_ensure_mobile_categories_table()) {
        return false;
    }

    $term_id = (int) $term_id;
    $order = max(0.01, round((float) $order, 2));
    $table_name = vogo_brand_options_mobile_categories_table_name();

    $result = $wpdb->replace(
        $table_name,
        [
            'term_id' => $term_id,
            'position' => number_format($order, 2, '.', ''),
            'updated_at' => current_time('mysql', true),
        ],
        ['%d', '%f', '%s']
    );

    clean_term_cache($term_id, 'product_cat');

    return $result !== false;
}

/**
 * Loads all selected mobile categories as term objects ordered by saved position.
 */
function vogo_brand_options_get_active_mobile_categories() {
    if (!vogo_brand_options_ensure_mobile_categories_table()) {
        return [];
    }

    $term_ids = vogo_brand_options_get_mobile_category_ids_in_order();
    if (empty($term_ids)) {
        return [];
    }

    $terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'include' => $term_ids,
        'orderby' => 'include',
    ]);

    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    $position_by_id = vogo_brand_options_get_mobile_category_positions_map();
    foreach ($terms as $term) {
        $term->term_order = isset($position_by_id[(int) $term->term_id]) ? (float) $position_by_id[(int) $term->term_id] : 0.0;
    }

    return $terms;
}

/**
 * Core reorder operation used by both classic form posts and AJAX handlers.
 */
function vogo_brand_options_move_mobile_category_internal($term_id, $direction) {
    if (!$term_id || !in_array($direction, ['up', 'down'], true)) {
        return new WP_Error('invalid_request', 'Invalid request.');
    }

    $ordered_ids = vogo_brand_options_get_mobile_category_ids_in_order();
    $current_index = array_search((int) $term_id, $ordered_ids, true);

    if ($current_index === false) {
        return new WP_Error('not_found', 'Category not found.');
    }

    $swap_index = $direction === 'up' ? $current_index - 1 : $current_index + 1;
    if (!isset($ordered_ids[$swap_index])) {
        return ['order' => $ordered_ids];
    }

    $tmp = $ordered_ids[$current_index];
    $ordered_ids[$current_index] = $ordered_ids[$swap_index];
    $ordered_ids[$swap_index] = $tmp;

    foreach ($ordered_ids as $index => $ordered_id) {
        vogo_brand_options_update_term_order($ordered_id, $index + 1);
    }

    return ['order' => $ordered_ids];
}

/**
 * Handles admin-post move requests (up/down) for one mobile category.
 */
function vogo_brand_options_move_mobile_category() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('vogo_mobile_category_move');

    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;
    $direction = sanitize_text_field($_POST['direction'] ?? '');

    $result = vogo_brand_options_move_mobile_category_internal($term_id, $direction);
    if (is_wp_error($result)) {
        wp_safe_redirect(add_query_arg(['page' => 'vogo-mobile-categories', 'updated' => '0'], admin_url('admin.php')));
        exit;
    }

    wp_safe_redirect(add_query_arg(['page' => 'vogo-mobile-categories', 'updated' => '1'], admin_url('admin.php')));
    exit;
}

add_action('admin_post_vogo_move_mobile_category', 'vogo_brand_options_move_mobile_category');

/**
 * AJAX variant for moving one mobile category up/down.
 */
function vogo_brand_options_move_mobile_category_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    check_ajax_referer('vogo_mobile_category_move_ajax', 'nonce');

    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;
    $direction = sanitize_text_field($_POST['direction'] ?? '');

    $result = vogo_brand_options_move_mobile_category_internal($term_id, $direction);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 400);
    }

    wp_send_json_success($result);
}

add_action('wp_ajax_vogo_move_mobile_category', 'vogo_brand_options_move_mobile_category_ajax');

/**
 * Saves full drag-and-drop ordering from admin post requests.
 */
function vogo_brand_options_save_mobile_category_order() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('vogo_mobile_category_reorder');

    $order_raw = sanitize_text_field($_POST['order'] ?? '');
    $order_ids = array_filter(array_map('intval', explode(',', $order_raw)));

    $active_ids = vogo_brand_options_get_mobile_category_ids_in_order();
    $ordered_ids = array_values(array_unique(array_intersect($order_ids, $active_ids)));
    $remaining_ids = array_values(array_diff($active_ids, $ordered_ids));
    $final_order = array_merge($ordered_ids, $remaining_ids);

    foreach ($final_order as $index => $ordered_id) {
        vogo_brand_options_update_term_order($ordered_id, $index + 1);
    }

    wp_safe_redirect(add_query_arg(['page' => 'vogo-mobile-categories', 'updated' => '1'], admin_url('admin.php')));
    exit;
}

add_action('admin_post_vogo_save_mobile_category_order', 'vogo_brand_options_save_mobile_category_order');

/**
 * Saves full drag-and-drop ordering via AJAX and returns canonical order.
 */
function vogo_brand_options_save_mobile_category_order_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    check_ajax_referer('vogo_mobile_category_reorder_ajax', 'nonce');

    $order_raw = sanitize_text_field($_POST['order'] ?? '');
    $order_ids = array_filter(array_map('intval', explode(',', $order_raw)));

    $active_ids = vogo_brand_options_get_mobile_category_ids_in_order();
    $ordered_ids = array_values(array_unique(array_intersect($order_ids, $active_ids)));
    $remaining_ids = array_values(array_diff($active_ids, $ordered_ids));
    $final_order = array_merge($ordered_ids, $remaining_ids);

    foreach ($final_order as $index => $ordered_id) {
        vogo_brand_options_update_term_order($ordered_id, $index + 1);
    }

    wp_send_json_success([
        'order' => $final_order,
    ]);
}

add_action('wp_ajax_vogo_save_mobile_category_order', 'vogo_brand_options_save_mobile_category_order_ajax');

/**
 * Replaces the full mobile-category selection submitted from the checklist form.
 */
function vogo_brand_options_save_mobile_categories() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('vogo_mobile_categories_save');

    $selected = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['vogo_mobile_categories'] ?? [])))));

    if (vogo_brand_options_ensure_mobile_categories_table()) {
        global $wpdb;
        $table_name = vogo_brand_options_mobile_categories_table_name();
        $wpdb->query("TRUNCATE TABLE {$table_name}");

        foreach ($selected as $index => $term_id) {
            vogo_brand_options_update_term_order($term_id, $index + 1);
        }
    }

    wp_safe_redirect(add_query_arg(['page' => 'vogo-mobile-categories', 'updated' => '1'], admin_url('admin.php')));
    exit;
}

/**
 * Adds one category into the active list at a requested insertion position.
 */
function vogo_brand_options_add_mobile_category() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('vogo_mobile_category_add');

    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;
    $insert_position = isset($_POST['insert_position']) ? (int) $_POST['insert_position'] : 0;
    if ($term_id) {
        $active_ids = vogo_brand_options_get_mobile_category_ids_in_order();
        $active_ids = array_values(array_diff($active_ids, [$term_id]));

        if ($insert_position < 1 || $insert_position > count($active_ids) + 1) {
            $insert_position = count($active_ids) + 1;
        }

        array_splice($active_ids, $insert_position - 1, 0, [$term_id]);
        foreach ($active_ids as $index => $active_id) {
            vogo_brand_options_update_term_order($active_id, $index + 1);
        }
    }

    wp_safe_redirect(add_query_arg(['page' => 'vogo-mobile-categories', 'updated' => '1'], admin_url('admin.php')));
    exit;
}

add_action('admin_post_vogo_add_mobile_category', 'vogo_brand_options_add_mobile_category');

/**
 * Removes one category from the active mobile list and re-normalizes positions.
 */
function vogo_brand_options_remove_mobile_category_internal($term_id) {
    global $wpdb;

    $term_id = (int) $term_id;
    if (!$term_id) {
        return new WP_Error('invalid_request', 'Invalid request.');
    }

    if (!vogo_brand_options_ensure_mobile_categories_table()) {
        return new WP_Error('db_error', 'Could not initialize mobile categories table.');
    }

    $table_name = vogo_brand_options_mobile_categories_table_name();
    $wpdb->delete($table_name, ['term_id' => $term_id], ['%d']);

    $updated_ids = vogo_brand_options_get_mobile_category_ids_in_order();
    foreach ($updated_ids as $index => $updated_id) {
        vogo_brand_options_update_term_order($updated_id, $index + 1);
    }

    return [
        'order' => $updated_ids,
    ];
}

/**
 * Handles admin-post remove requests for one mobile category.
 */
function vogo_brand_options_remove_mobile_category() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('vogo_mobile_category_remove');

    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;
    $result = vogo_brand_options_remove_mobile_category_internal($term_id);
    if (is_wp_error($result)) {
        wp_safe_redirect(add_query_arg(['page' => 'vogo-mobile-categories', 'updated' => '0'], admin_url('admin.php')));
        exit;
    }

    wp_safe_redirect(add_query_arg(['page' => 'vogo-mobile-categories', 'updated' => '1'], admin_url('admin.php')));
    exit;
}

add_action('admin_post_vogo_remove_mobile_category', 'vogo_brand_options_remove_mobile_category');


/**
 * AJAX variant for removing one category from the active mobile list.
 */
function vogo_brand_options_remove_mobile_category_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    check_ajax_referer('vogo_mobile_category_remove_ajax', 'nonce');

    $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;
    $result = vogo_brand_options_remove_mobile_category_internal($term_id);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 400);
    }

    wp_send_json_success($result);
}

add_action('wp_ajax_vogo_remove_mobile_category', 'vogo_brand_options_remove_mobile_category_ajax');

/**
 * Small wrapper used by tab pages to keep a single rendering entrypoint.
 */
function vogo_brand_options_render_subpage($title) {
    echo '<div class="wrap vogo-brand-subpage">';
    echo '<h1>' . esc_html($title) . '</h1>';
    echo '<p>This page is ready for VOGO WooCommerce module content.</p>';
    echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=vogo-brand-options')) . '">Back to master page</a></p>';
    echo '</div>';
}

/**
 * Renders the logs tab from the mobile categories module.
 */
function vogo_brand_options_render_logs_page() {
    // Load the dedicated logs screen implementation.
    require_once __DIR__ . '/vogo-view-logs.php';
}

/**
 * Renders the full admin UI for listing, ordering and editing mobile categories.
 */
function vogo_brand_options_render_mobile_categories_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $active_terms = vogo_brand_options_get_active_mobile_categories();
    $all_terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);
    if (is_wp_error($all_terms)) {
        $all_terms = [];
    }

    $active_ids = array_map(static function ($term) {
        return (int) $term->term_id;
    }, $active_terms);

    echo '<div class="wrap vogo-mobile-categories">';
    echo '<h1>Mobile app categories</h1>';
    echo '<p>Manage the categories returned by <code>/category-list</code> for the Flutter mobile app. Adjust display order, add new items, or remove existing ones.</p>';

    $reminder_class = ' vogo-version-reminder--hidden';
    echo '<div class="notice notice-info vogo-version-reminder' . esc_attr($reminder_class) . '" data-reminder-scope="mobile-categories"><p>You updated configuration: Do not forget to change version to next one X.X.X in main VOGO plugin screen in order to push updates to your mobile application.</p></div>';
    if (!empty($_GET['updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Mobile categories updated.</p></div>';
    }

    echo '<div class="vogo-mobile-categories-form">';
    echo '<div class="vogo-mobile-category-toolbar">';
    echo '<div class="vogo-mobile-category-toolbar-left">';
    echo '<button type="button" class="button button-primary" id="vogo-open-add-modal" title="Add a category to the mobile list">Add category</button>';
    echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=vogo-brand-options')) . '" title="Return to the Brand Control Center">Back to Brand Control Center</a>';
    echo '</div>';
    echo '</div>';
    echo '<p class="vogo-mobile-category-hint">Drag the handle to reorder categories. The current position is shown in the first column.</p>';
    echo '<div class="vogo-mobile-category-list">';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="vogo-mobile-category-order-form">';
    wp_nonce_field('vogo_mobile_category_reorder');
    echo '<input type="hidden" name="action" value="vogo_save_mobile_category_order" />';
    echo '<input type="hidden" name="order" id="vogo-mobile-category-order" value="' . esc_attr(implode(',', $active_ids)) . '" />';
    echo '<div class="vogo-mobile-category-header" role="row">';
    echo '<div class="vogo-mobile-category-cell vogo-col-position" role="columnheader">Position</div>';
    echo '<div class="vogo-mobile-category-cell vogo-col-category" role="columnheader">Category</div>';
    echo '<div class="vogo-mobile-category-cell vogo-col-actions" role="columnheader">Actions</div>';
    echo '</div>';
    echo '<div class="vogo-mobile-category-rows" id="vogo-mobile-category-rows" role="rowgroup">';
    if (empty($active_terms)) {
        echo '<div class="vogo-mobile-category-empty">No mobile categories selected yet.</div>';
    } else {
        foreach ($active_terms as $index => $term) {
            $position = $index + 1;
            echo '<div class="vogo-mobile-category-row" data-term-id="' . esc_attr($term->term_id) . '" data-term-name="' . esc_attr($term->name) . '" role="row">';
            echo '<div class="vogo-mobile-category-cell vogo-col-position" role="cell">';
            echo '<span class="vogo-drag-handle" aria-hidden="true" title="Drag to reorder">⋮⋮</span>';
            echo '<span class="vogo-mobile-category-position">' . (int) $position . '</span>';
            echo '</div>';
            echo '<div class="vogo-mobile-category-cell vogo-col-category" role="cell">';
            echo '<strong>' . esc_html($term->name) . '</strong><br><span class="vogo-mobile-category-slug">' . esc_html($term->slug) . '</span>';
            echo '</div>';
            echo '<div class="vogo-mobile-category-cell vogo-col-actions" role="cell">';
            echo '<div class="vogo-mobile-category-actions">';
            echo '<button type="button" class="button button-small vogo-icon-button vogo-action-button" data-action="add-modal" data-position="' . (int) $position . '" aria-label="Add category at this position" title="Add category at this position"><span class="dashicons dashicons-plus vogo-icon-glyph" aria-hidden="true"></span><span class="vogo-action-label">Add</span></button>';
            echo '<button type="button" class="button button-small vogo-icon-button vogo-action-button" data-action="move-modal" data-term-id="' . esc_attr($term->term_id) . '" aria-label="Move category" title="Move category"><span class="vogo-icon-glyph" aria-hidden="true">&lt;&gt;</span><span class="vogo-action-label">Change category position</span></button>';
            echo '<button type="button" class="button button-small vogo-icon-button vogo-action-button is-delete" data-action="remove" data-term-id="' . esc_attr($term->term_id) . '" data-term-name="' . esc_attr($term->name) . '" data-term-slug="' . esc_attr($term->slug) . '" aria-label="Remove category" title="Remove category"><span class="dashicons dashicons-trash vogo-icon-glyph" aria-hidden="true"></span><span class="vogo-action-label">Delete</span></button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
    }
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="vogo-modal" id="vogo-add-modal" aria-hidden="true">';
    echo '<div class="vogo-modal__content" role="dialog" aria-modal="true" aria-labelledby="vogo-add-modal-title">';
    echo '<h2 id="vogo-add-modal-title">Add category to mobile view</h2>';
    echo '<p>Select a product category to add to the mobile list.</p>';
    echo '<input type="text" class="vogo-modal__search" placeholder="Search category..." id="vogo-category-search" />';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="vogo-add-category-form">';
    wp_nonce_field('vogo_mobile_category_add');
    echo '<input type="hidden" name="action" value="vogo_add_mobile_category" />';
    echo '<input type="hidden" name="insert_position" id="vogo-add-insert-position" value="" />';
    echo '<select name="term_id" id="vogo-category-select" size="8">';
    foreach ($all_terms as $term) {
        if (in_array((int) $term->term_id, $active_ids, true)) {
            continue;
        }
        echo '<option value="' . esc_attr($term->term_id) . '">' . esc_html($term->name) . '</option>';
    }
    echo '</select>';
    echo '<div class="vogo-modal__actions">';
    echo '<button type="submit" class="button button-primary" title="Add the selected category">Add</button>';
    echo '<button type="button" class="button" id="vogo-close-add-modal" title="Cancel and close">Cancel</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    echo '<div class="vogo-modal" id="vogo-move-modal" aria-hidden="true">';
    echo '<div class="vogo-modal__content" role="dialog" aria-modal="true" aria-labelledby="vogo-move-modal-title">';
    echo '<h2 id="vogo-move-modal-title">Move category <span id="vogo-move-category-name"></span></h2>';
    echo '<p>Choose a new position for this category.</p>';
    echo '<div class="vogo-modal__label">Move category <span id="vogo-move-category-name-label"></span> from position <span id="vogo-move-current">-</span> to position</div>';
    echo '<select id="vogo-move-position" class="vogo-modal__select" aria-label="Move to position"></select>';
    echo '<div class="vogo-modal__actions">';
    echo '<button type="button" class="button button-primary" id="vogo-apply-move" title="Move to the selected position">Move</button>';
    echo '<button type="button" class="button" id="vogo-cancel-move" title="Cancel and close">Cancel</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<style>
        .vogo-mobile-categories-form { max-width: 1020px; }
        .vogo-version-reminder { border-left-color: #0c542d; background: #f0faf4; color: #ff0000; font-weight: 600; }
        .vogo-version-reminder--hidden { display: none; }
        .vogo-version-reminder p { margin: 0; font-size: 14px; }
        .vogo-mobile-category-toolbar { margin-bottom: 12px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; justify-content: space-between; }
        .vogo-mobile-category-toolbar-left,
        .vogo-mobile-category-toolbar-right { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .vogo-mobile-category-toolbar-right { margin-left: auto; justify-content: flex-end; }
        .vogo-mobile-category-toolbar .button { border-radius: 999px; padding: 6px 16px; border: 1px solid #0c542d; box-shadow: 0 6px 12px rgba(7, 52, 28, 0.12); font-weight: 600; text-transform: none; }
        .vogo-mobile-category-toolbar .button-primary { background: linear-gradient(135deg, #0c542d, #108749); border-color: #0c542d; }
        .vogo-mobile-category-toolbar .button-primary:hover { background: linear-gradient(135deg, #0b4c2a, #0e753f); }
        .vogo-mobile-category-toolbar .button-secondary { background: #f5fbf7; color: #0c542d; }
        .vogo-mobile-category-hint { margin: 0 0 12px; color: #4f5f55; }
        .vogo-mobile-category-list { background: #ffffff; border: 1px solid #d8e1dc; border-radius: 16px; overflow: hidden; box-shadow: 0 16px 30px rgba(7, 52, 28, 0.12); }
        .vogo-mobile-category-list .vogo-mobile-category-header { display: grid; grid-template-columns: 120px 1fr 420px; gap: 16px; align-items: center; padding: 14px 18px; background: linear-gradient(135deg, #0c542d, #108749); color: #ffffff; font-weight: 600; letter-spacing: 0.2px; }
        .vogo-mobile-category-list .vogo-mobile-category-rows { display: flex; flex-direction: column; }
        .vogo-mobile-category-list .vogo-mobile-category-row { display: grid; grid-template-columns: 120px 1fr 420px; gap: 16px; align-items: center; padding: 14px 18px; border-bottom: 1px solid #edf2ef; background: #ffffff; transition: background 0.2s ease, box-shadow 0.2s ease; }
        .vogo-mobile-category-list .vogo-mobile-category-row:last-child { border-bottom: none; }
        .vogo-mobile-category-list .vogo-mobile-category-row.is-dragging { box-shadow: 0 16px 30px rgba(10, 66, 36, 0.16); background: #f6faf7; }
        .vogo-mobile-category-list .vogo-mobile-category-row.is-floating { opacity: 0.9; }
        .vogo-mobile-category-list .vogo-mobile-category-row.is-drop-target { outline: 2px dashed #7bb897; outline-offset: -6px; background: #f0faf4; }
        .vogo-mobile-category-list .vogo-mobile-category-row:hover { background: #f3f9f5; }
        .vogo-mobile-category-list .vogo-mobile-category-cell { display: flex; align-items: center; gap: 8px; }
        .vogo-mobile-category-list .vogo-col-actions { justify-content: center; }
        .vogo-mobile-category-list .vogo-mobile-category-slug { color: #62766b; font-size: 12px; }
        .vogo-mobile-category-list .vogo-mobile-category-actions { display: flex; gap: 6px; flex-wrap: nowrap; justify-content: center; align-items: center; }
        .vogo-mobile-category-list .vogo-mobile-category-position { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; border-radius: 999px; background: #e7f3ec; color: #0c542d; font-weight: 700; }
        .vogo-mobile-category-list .vogo-drag-handle { cursor: grab; font-size: 16px; line-height: 1; color: #0c542d; user-select: none; padding: 6px 8px; border-radius: 8px; background: #d8eee2; }
        .vogo-mobile-category-list .vogo-mobile-category-row:active .vogo-drag-handle { cursor: grabbing; }
        .vogo-mobile-category-list .vogo-sortable-ghost { border: 2px dashed #7bb897; border-radius: 12px; background: rgba(12, 84, 45, 0.08); }
        .vogo-mobile-category-list .vogo-mobile-category-empty { padding: 20px; color: #5e6d65; font-style: italic; }
        .vogo-inline-form { display: inline; margin: 0; }
        .vogo-icon-button { padding: 4px 10px; min-height: 32px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; background: linear-gradient(135deg, #f3faf6, #e8f4ed); border: 1px solid #cfe3d6; box-shadow: 0 6px 14px rgba(12, 84, 45, 0.08), inset 0 0 0 1px rgba(12, 84, 45, 0.04); cursor: default; }
        .vogo-icon-button .vogo-icon-glyph { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; font-size: 16px; line-height: 1; text-align: center; color: #0c542d; }
        .vogo-icon-button .dashicons.vogo-icon-glyph { font-size: 18px; width: 18px; height: 18px; line-height: 1; display: inline-flex; align-items: center; justify-content: center; vertical-align: middle; }
        .vogo-action-label { font-size: 12px; font-weight: 600; color: #0c542d; white-space: nowrap; }
        .vogo-icon-button.is-delete { background: #f6f8f7; border-color: #d9e0dd; }
        .vogo-icon-button.is-delete .dashicons { color: #0c542d; }
        .vogo-mobile-category-actions .vogo-action-button { cursor: default; }
        .vogo-mobile-category-actions .button { cursor: default; }
        .vogo-modal { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.4); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .vogo-modal.is-open { display: flex; }
        .vogo-modal__content { background: #fff; padding: 20px; border-radius: 16px; width: 420px; max-width: 90%; box-shadow: 0 20px 30px rgba(0, 0, 0, 0.2); border: 1px solid #e2ebe6; }
        .vogo-modal__search { width: 100%; margin: 12px 0; border-radius: 8px; border-color: #cfe1d5; }
        #vogo-category-select { width: 100%; border-radius: 8px; border-color: #cfe1d5; }
        .vogo-modal__label { display: block; margin-top: 12px; font-weight: 600; }
        .vogo-modal__select { width: 100%; margin-top: 8px; border-radius: 8px; border-color: #cfe1d5; }
        .vogo-modal__actions { margin-top: 16px; display: flex; gap: 8px; justify-content: flex-end; }
    </style>';

    echo '<script>
        (function() {
            var openBtn = document.getElementById("vogo-open-add-modal");
            var closeBtn = document.getElementById("vogo-close-add-modal");
            var modal = document.getElementById("vogo-add-modal");
            var moveModal = document.getElementById("vogo-move-modal");
            var moveSelect = document.getElementById("vogo-move-position");
            var moveApply = document.getElementById("vogo-apply-move");
            var moveCancel = document.getElementById("vogo-cancel-move");
            var moveCurrent = document.getElementById("vogo-move-current");
            var moveCategoryName = document.getElementById("vogo-move-category-name");
            var moveCategoryNameLabel = document.getElementById("vogo-move-category-name-label");
            var search = document.getElementById("vogo-category-search");
            var select = document.getElementById("vogo-category-select");
            var rowContainer = document.getElementById("vogo-mobile-category-rows");
            var orderInput = document.getElementById("vogo-mobile-category-order");
            var status = document.getElementById("vogo-order-status");
            var insertPositionInput = document.getElementById("vogo-add-insert-position");
            var reminderEl = document.querySelector(".vogo-version-reminder[data-reminder-scope=\"mobile-categories\"]");
            var ajaxConfig = {
                ajaxUrl: "' . esc_url(admin_url('admin-ajax.php')) . '",
                reorderNonce: "' . esc_attr(wp_create_nonce('vogo_mobile_category_reorder_ajax')) . '",
                removeNonce: "' . esc_attr(wp_create_nonce('vogo_mobile_category_remove_ajax')) . '"
            };

            var hasAddModal = openBtn && closeBtn && modal && search && select;
            var hasMoveModal = moveModal && moveSelect && moveApply && moveCancel && moveCurrent && moveCategoryName && moveCategoryNameLabel;
            var hasOrderControls = rowContainer && orderInput;
            var activeMoveRow = null;
            var showReminder = function() {
                if (reminderEl) {
                    reminderEl.classList.remove("vogo-version-reminder--hidden");
                }
            };

            function openModal(insertPosition) {
                modal.classList.add("is-open");
                modal.setAttribute("aria-hidden", "false");
                search.value = "";
                filterOptions("");
                if (insertPositionInput) {
                    insertPositionInput.value = insertPosition ? String(insertPosition) : "";
                }
                search.focus();
            }

            function closeModal() {
                modal.classList.remove("is-open");
                modal.setAttribute("aria-hidden", "true");
            }

            function openMoveModal(row, preference) {
                if (!hasMoveModal) {
                    return;
                }
                activeMoveRow = row;
                var rows = rowContainer ? rowContainer.querySelectorAll(".vogo-mobile-category-row[data-term-id]") : [];
                moveSelect.innerHTML = "";
                Array.prototype.forEach.call(rows, function(rowItem, index) {
                    var option = document.createElement("option");
                    option.value = String(index + 1);
                    option.textContent = String(index + 1);
                    moveSelect.appendChild(option);
                });
                var currentIndex = row ? Array.prototype.indexOf.call(rows, row) : -1;
                var categoryName = "";
                if (row) {
                    categoryName = row.getAttribute("data-term-name") || "";
                    if (!categoryName) {
                        var nameNode = row.querySelector(".vogo-col-category strong");
                        if (nameNode) {
                            categoryName = nameNode.textContent || "";
                        }
                    }
                }
                moveCategoryName.textContent = categoryName ? "\"" + categoryName + "\"" : "";
                moveCategoryNameLabel.textContent = categoryName ? "\"" + categoryName + "\"" : "this category";
                if (currentIndex >= 0) {
                    moveCurrent.textContent = String(currentIndex + 1);
                } else {
                    moveCurrent.textContent = "-";
                }
                if (preference === "top") {
                    moveSelect.value = "1";
                } else if (preference === "bottom") {
                    moveSelect.value = String(rows.length);
                } else if (row) {
                    if (currentIndex >= 0) {
                        moveSelect.value = String(currentIndex + 1);
                    }
                }
                moveModal.classList.add("is-open");
                moveModal.setAttribute("aria-hidden", "false");
                moveSelect.focus();
            }

            function closeMoveModal() {
                if (!hasMoveModal) {
                    return;
                }
                moveModal.classList.remove("is-open");
                moveModal.setAttribute("aria-hidden", "true");
                activeMoveRow = null;
            }

            function filterOptions(query) {
                var term = query.toLowerCase();
                Array.prototype.forEach.call(select.options, function(option) {
                    var match = option.text.toLowerCase().indexOf(term) !== -1;
                    option.hidden = !match;
                });
            }

            function setDirty(isDirty) {
                if (!status) {
                    return;
                }
                status.textContent = isDirty ? "Reordering..." : "";
            }

            function updateOrder() {
                if (!rowContainer || !orderInput) {
                    return;
                }
                var ids = [];
                var rows = rowContainer.querySelectorAll(".vogo-mobile-category-row[data-term-id]");
                Array.prototype.forEach.call(rows, function(row, index) {
                    var position = row.querySelector(".vogo-mobile-category-position");
                    if (position) {
                        position.textContent = index + 1;
                    }
                    var addButton = row.querySelector(".vogo-action-button[data-action=\"add-modal\"]");
                    if (addButton) {
                        addButton.setAttribute("data-position", String(index + 1));
                    }
                    ids.push(row.getAttribute("data-term-id"));
                });
                orderInput.value = ids.join(",");
            }

            function updatePositionsByOrder(orderIds) {
                if (!rowContainer || !orderInput) {
                    return;
                }
                var rows = rowContainer.querySelectorAll(".vogo-mobile-category-row[data-term-id]");
                var rowMap = {};
                Array.prototype.forEach.call(rows, function(row) {
                    rowMap[row.getAttribute("data-term-id")] = row;
                });
                var fragment = document.createDocumentFragment();
                Array.prototype.forEach.call(orderIds, function(id) {
                    if (rowMap[id]) {
                        fragment.appendChild(rowMap[id]);
                    }
                });
                rowContainer.appendChild(fragment);
                updateOrder();
            }

            function persistOrder() {
                showReminder();
                updateOrder();
                setDirty(false);
                updateActionStates();
                setStatus("Saving settings...", false);
                return sendAction({
                    action: "vogo_save_mobile_category_order",
                    order: orderInput.value,
                    nonce: ajaxConfig.reorderNonce
                }).then(function(response) {
                    if (!response || !response.success) {
                        throw new Error((response && response.data && response.data.message) ? response.data.message : "Request failed.");
                    }
                    setStatus("Settings saved.", false);
                }).catch(function(error) {
                    setDirty(true);
                    setStatus(error.message || "Request failed.", true);
                    throw error;
                });
            }

            function setStatus(message, isError) {
                if (!status) {
                    return;
                }
                status.textContent = message || "";
                status.style.color = isError ? "#a52828" : "#0c542d";
            }

            function updateActionStates() {
                if (!rowContainer) {
                    return;
                }
            }

            function ensureEmptyState() {
                if (!rowContainer) {
                    return;
                }
                var rows = rowContainer.querySelectorAll(".vogo-mobile-category-row[data-term-id]");
                var emptyState = rowContainer.querySelector(".vogo-mobile-category-empty");
                if (!rows.length) {
                    if (!emptyState) {
                        emptyState = document.createElement("div");
                        emptyState.className = "vogo-mobile-category-empty";
                        emptyState.textContent = "No mobile categories selected yet.";
                        rowContainer.appendChild(emptyState);
                    }
                } else if (emptyState) {
                    emptyState.remove();
                }
            }

            function sendAction(payload) {
                var params = new URLSearchParams(payload);
                return fetch(ajaxConfig.ajaxUrl, {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
                    },
                    body: params.toString()
                }).then(function(response) {
                    return response.json();
                });
            }

            if (hasAddModal) {
                var addForm = document.getElementById("vogo-add-category-form");
                openBtn.addEventListener("click", function() {
                    var rowCount = rowContainer ? rowContainer.querySelectorAll(".vogo-mobile-category-row[data-term-id]").length : 0;
                    openModal(rowCount + 1);
                });
                closeBtn.addEventListener("click", closeModal);
                modal.addEventListener("click", function(event) {
                    if (event.target === modal) {
                        closeModal();
                    }
                });
                document.addEventListener("keydown", function(event) {
                    if (event.key === "Escape") {
                        closeModal();
                    }
                });
                search.addEventListener("input", function(event) {
                    filterOptions(event.target.value);
                });
                if (addForm) {
                    addForm.addEventListener("submit", function() {
                        showReminder();
                    });
                }
            }

            if (hasMoveModal) {
                moveCancel.addEventListener("click", closeMoveModal);
                moveModal.addEventListener("click", function(event) {
                    if (event.target === moveModal) {
                        closeMoveModal();
                    }
                });
                moveApply.addEventListener("click", function() {
                    if (!activeMoveRow || !rowContainer) {
                        closeMoveModal();
                        return;
                    }
                    var targetPosition = parseInt(moveSelect.value || "0", 10);
                    var rows = rowContainer.querySelectorAll(".vogo-mobile-category-row[data-term-id]");
                    var rowsArray = Array.prototype.slice.call(rows);
                    if (!targetPosition || targetPosition < 1 || targetPosition > rowsArray.length) {
                        closeMoveModal();
                        return;
                    }
                    var currentIndex = rowsArray.indexOf(activeMoveRow);
                    if (currentIndex !== -1) {
                        rowsArray.splice(currentIndex, 1);
                    }
                    var targetIndex = Math.max(0, Math.min(targetPosition - 1, rowsArray.length));
                    var targetRow = rowsArray[targetIndex] || null;
                    if (targetRow !== activeMoveRow) {
                        rowContainer.insertBefore(activeMoveRow, targetRow);
                    }
                    showReminder();
                    updateOrder();
                    updateActionStates();
                    closeMoveModal();
                    persistOrder().catch(function() {
                        // handled in persistOrder
                    });
                });
                document.addEventListener("keydown", function(event) {
                    if (event.key === "Escape") {
                        closeMoveModal();
                    }
                });
            }

            function clearDropTargets() {
                if (!rowContainer) {
                    return;
                }
                var targets = rowContainer.querySelectorAll(".vogo-mobile-category-row.is-drop-target");
                Array.prototype.forEach.call(targets, function(row) {
                    row.classList.remove("is-drop-target");
                });
            }

            function initPointerReorder() {
                if (!rowContainer) {
                    return;
                }
                var activeRow = null;
                var activeHandle = null;
                var isDragging = false;
                var activePointerId = null;
                var dragMoveHandler = null;
                var dragEndHandler = null;
                var previousDraggable = null;

                function findDropTarget(clientY) {
                    var rows = rowContainer.querySelectorAll(".vogo-mobile-category-row[data-term-id]");
                    var candidate = null;
                    Array.prototype.forEach.call(rows, function(row) {
                        if (row === activeRow) {
                            return;
                        }
                        var box = row.getBoundingClientRect();
                        if (clientY < box.top + box.height / 2 && !candidate) {
                            candidate = row;
                        }
                    });
                    return candidate;
                }

                function handlePointerMove(event) {
                    if (!isDragging || !activeRow) {
                        return;
                    }
                    clearDropTargets();
                    var targetRow = findDropTarget(event.clientY);
                    if (targetRow) {
                        targetRow.classList.add("is-drop-target");
                        if (activeRow !== targetRow && activeRow.nextElementSibling !== targetRow) {
                            rowContainer.insertBefore(activeRow, targetRow);
                        }
                        return;
                    }
                    var lastRow = rowContainer.querySelector(".vogo-mobile-category-row[data-term-id]:last-of-type");
                    if (lastRow && activeRow !== lastRow) {
                        rowContainer.appendChild(activeRow);
                        lastRow.classList.add("is-drop-target");
                    }
                }

                function handlePointerEnd(event) {
                    if (!isDragging) {
                        return;
                    }
                    if (activeHandle && activePointerId !== null) {
                        activeHandle.releasePointerCapture(activePointerId);
                    }
                    clearDropTargets();
                    if (activeRow) {
                        activeRow.classList.remove("is-dragging", "is-floating");
                        if (previousDraggable !== null) {
                            activeRow.setAttribute("draggable", previousDraggable);
                        } else {
                            activeRow.removeAttribute("draggable");
                        }
                    }
                    if (dragMoveHandler) {
                        document.removeEventListener("pointermove", dragMoveHandler);
                    }
                    if (dragEndHandler) {
                        document.removeEventListener("pointerup", dragEndHandler);
                        document.removeEventListener("pointercancel", dragEndHandler);
                    }
                    activeRow = null;
                    activeHandle = null;
                    activePointerId = null;
                    isDragging = false;
                    previousDraggable = null;
                    persistOrder().catch(function() {
                        // handled in persistOrder
                    });
                }

                rowContainer.addEventListener("pointerdown", function(event) {
                    if (event.button && event.button !== 0) {
                        return;
                    }
                    var handle = event.target.closest(".vogo-drag-handle");
                    if (!handle) {
                        return;
                    }
                    var row = handle.closest(".vogo-mobile-category-row");
                    if (!row) {
                        return;
                    }
                    event.preventDefault();
                    activeRow = row;
                    activeHandle = handle;
                    isDragging = true;
                    activePointerId = event.pointerId;
                    activeRow.classList.add("is-dragging", "is-floating");
                    previousDraggable = row.getAttribute("draggable");
                    row.setAttribute("draggable", "false");
                    activeHandle.setPointerCapture(event.pointerId);
                    dragMoveHandler = handlePointerMove;
                    dragEndHandler = handlePointerEnd;
                    document.addEventListener("pointermove", dragMoveHandler);
                    document.addEventListener("pointerup", dragEndHandler);
                    document.addEventListener("pointercancel", dragEndHandler);
                });
            }

            function initHtmlDragReorder() {
                if (!rowContainer) {
                    return;
                }
                var dragRow = null;

                function enableDraggableRows() {
                    var rows = rowContainer.querySelectorAll(".vogo-mobile-category-row[data-term-id]");
                    Array.prototype.forEach.call(rows, function(row) {
                        row.setAttribute("draggable", "true");
                    });
                }

                function findRowForDrag(event) {
                    var target = event.target.closest(".vogo-mobile-category-row");
                    if (!target || !rowContainer.contains(target)) {
                        return null;
                    }
                    var handle = event.target.closest(".vogo-drag-handle");
                    if (!handle) {
                        return null;
                    }
                    return target;
                }

                enableDraggableRows();

                rowContainer.addEventListener("dragstart", function(event) {
                    var row = findRowForDrag(event);
                    if (!row) {
                        event.preventDefault();
                        return;
                    }
                    dragRow = row;
                    row.classList.add("is-dragging", "is-floating");
                    if (event.dataTransfer) {
                        event.dataTransfer.effectAllowed = "move";
                        event.dataTransfer.setData("text/plain", row.getAttribute("data-term-id") || "");
                    }
                });

                rowContainer.addEventListener("dragover", function(event) {
                    if (!dragRow) {
                        return;
                    }
                    event.preventDefault();
                    if (event.dataTransfer) {
                        event.dataTransfer.dropEffect = "move";
                    }
                    clearDropTargets();
                    var target = event.target.closest(".vogo-mobile-category-row");
                    if (!target || target === dragRow) {
                        return;
                    }
                    target.classList.add("is-drop-target");
                    var targetBox = target.getBoundingClientRect();
                    var shouldInsertBefore = event.clientY < targetBox.top + targetBox.height / 2;
                    rowContainer.insertBefore(dragRow, shouldInsertBefore ? target : target.nextSibling);
                });

                rowContainer.addEventListener("dragend", function() {
                    if (!dragRow) {
                        return;
                    }
                    clearDropTargets();
                    dragRow.classList.remove("is-dragging", "is-floating");
                    dragRow = null;
                    persistOrder().catch(function() {
                        // handled in persistOrder
                    });
                });
            }

            if (hasOrderControls) {
                updateOrder();
                updateActionStates();
                if (window.Sortable && rowContainer) {
                    window.Sortable.create(rowContainer, {
                        animation: 160,
                        handle: ".vogo-drag-handle",
                        forceFallback: true,
                        fallbackTolerance: 4,
                        filter: "button, a, input, select, textarea, label",
                        ghostClass: "vogo-sortable-ghost",
                        chosenClass: "is-dragging",
                        onEnd: function() {
                            persistOrder().catch(function() {
                                // handled in persistOrder
                            });
                        }
                    });
                } else {
                    initHtmlDragReorder();
                    initPointerReorder();
                }
            }

            if (rowContainer) {
                rowContainer.addEventListener("click", function(event) {
                    var button = event.target.closest(".vogo-action-button");
                    if (!button || button.disabled) {
                        return;
                    }
                    var action = button.getAttribute("data-action");
                    var termId = button.getAttribute("data-term-id");
                    if (!action) {
                        return;
                    }
                    if (action === "add-modal") {
                        var insertPosition = parseInt(button.getAttribute("data-position") || "0", 10);
                        openModal(insertPosition);
                        return;
                    }
                    if (!termId) {
                        return;
                    }

                    if (action === "move-modal") {
                        var row = button.closest(".vogo-mobile-category-row");
                        if (row) {
                            openMoveModal(row, "");
                        }
                    } else if (action === "remove") {
                        var rowToRemove = button.closest(".vogo-mobile-category-row");
                        var name = button.getAttribute("data-term-name") || "this category";
                        if (!window.confirm("Remove " + name + " from mobile categories?")) {
                            return;
                        }
                        showReminder();
                        sendAction({
                            action: "vogo_remove_mobile_category",
                            term_id: termId,
                            nonce: ajaxConfig.removeNonce
                        }).then(function(response) {
                            if (!response || !response.success) {
                                throw new Error((response && response.data && response.data.message) ? response.data.message : "Request failed.");
                            }
                            if (rowToRemove) {
                                rowToRemove.remove();
                            }
                            if (response.data && response.data.order) {
                                updatePositionsByOrder(response.data.order.map(String));
                            } else {
                                updateOrder();
                            }
                            setDirty(false);
                            ensureEmptyState();
                            if (select && name) {
                                var existingOption = select.querySelector("option[value=\"" + termId + "\"]");
                                if (!existingOption) {
                                    var option = document.createElement("option");
                                    option.value = termId;
                                    option.textContent = name;
                                    select.appendChild(option);
                                }
                            }
                        }).catch(function(error) {
                            setStatus(error.message || "Request failed.", true);
                        });
                    }
                });
            }
        })();
    </script>';
}

/**
 * Recursively renders a category tree with checkbox state and hierarchy indentation.
 */
function vogo_brand_options_render_mobile_category_tree($parent_id, $by_parent, $selected_ids, $depth) {
    if (empty($by_parent[$parent_id])) {
        return;
    }

    echo '<ul class="vogo-mobile-category-group">';
    foreach ($by_parent[$parent_id] as $term) {
        $checked = in_array((int) $term->term_id, $selected_ids, true);
        $indent = max(0, (int) $depth) * 20;
        echo '<li class="vogo-mobile-category-row" style="margin-left:' . (int) $indent . 'px;">';
        echo '<label class="vogo-mobile-category-label">';
        echo '<input type="checkbox" name="vogo_mobile_categories[]" value="' . esc_attr($term->term_id) . '" ' . checked($checked, true, false) . ' />';
        echo '<span class="vogo-mobile-category-name">' . esc_html($term->name) . '</span>';
        echo ' <span class="vogo-mobile-category-slug">(' . esc_html($term->slug) . ')</span>';
        echo '</label>';
        echo '</li>';
        vogo_brand_options_render_mobile_category_tree($term->term_id, $by_parent, $selected_ids, $depth + 1);
    }
    echo '</ul>';
}
