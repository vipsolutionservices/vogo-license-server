<?php
/**
 * Brand Version History Module.
 *
 * This file manages brand settings snapshots for the Brand Control Center.
 * It creates and uses a dedicated table that stores brand name, brand version,
 * save timestamp, and full serialized settings payload. It also exposes an
 * admin page for browsing history and restoring a selected snapshot.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolve the table name used for brand version history records.
 */
function vogo_brand_version_history_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'brand_versions';
}

/**
 * Ensure the brand version history table exists before history operations.
 */
function vogo_brand_version_history_ensure_table() {
    global $wpdb;

    $table = vogo_brand_version_history_table_name();
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

    if ($exists) {
        return $table;
    }

    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        brand_name VARCHAR(191) NOT NULL,
        brand_version VARCHAR(191) NOT NULL,
        settings_snapshot LONGTEXT NOT NULL,
        saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY brand_name (brand_name),
        KEY brand_version (brand_version),
        KEY saved_at (saved_at)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    return $table;
}

/**
 * Save one immutable snapshot row in the history table after each settings save.
 */
function vogo_brand_version_history_save_snapshot(array $snapshot_data) {
    global $wpdb;

    $table = vogo_brand_version_history_ensure_table();
    $brand_name = isset($snapshot_data['brand_name']) ? sanitize_text_field((string) $snapshot_data['brand_name']) : '';
    $brand_version = isset($snapshot_data['brand_version']) ? sanitize_text_field((string) $snapshot_data['brand_version']) : '';

    $wpdb->insert(
        $table,
        [
            'brand_name' => $brand_name,
            'brand_version' => $brand_version,
            'settings_snapshot' => wp_json_encode($snapshot_data),
            'saved_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%s']
    );
}

/**
 * Render the Version history admin page with restore actions.
 */
function vogo_brand_version_history_render_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized request.', 'Unauthorized', ['response' => 403]);
    }

    global $wpdb;

    $table = vogo_brand_version_history_ensure_table();
    $sort = isset($_GET['sort']) && $_GET['sort'] === 'asc' ? 'asc' : 'desc';
    $sort_sql = $sort === 'asc' ? 'ASC' : 'DESC';
    $rows = $wpdb->get_results("SELECT id, brand_name, brand_version, settings_snapshot, saved_at FROM {$table} ORDER BY saved_at {$sort_sql} LIMIT 300", ARRAY_A);

    echo '<div class="wrap vogo-brand-options">';
    echo '<h1>Brand Version history</h1>';
    echo '<p>Review all saved brand versions and inspect each snapshot in read-only mode.</p>';

    if (!empty($_GET['restored']) && $_GET['restored'] === '1') {
        echo '<div class="notice notice-success is-dismissible"><p>VOGO: Brand version snapshot restored successfully.</p></div>';
    }

    if (empty($rows)) {
        echo '<div class="notice notice-info"><p>No brand version snapshots found yet.</p></div>';
        echo '</div>';
        return;
    }

    /**
     * Build UI controls and row attributes used by the client-side selector.
     * We keep all snapshot payloads in data attributes so details can switch
     * instantly without triggering additional requests.
     */
    $sort_toggle = $sort === 'asc' ? 'desc' : 'asc';
    $sort_label = $sort === 'asc' ? 'Date ↑' : 'Date ↓';

    echo '<div class="vogo-history-toolbar">';
    echo '<input id="vogo-history-filter" class="regular-text" type="search" placeholder="Filter by brand or version..." />';
    echo '<a class="button button-secondary" href="' . esc_url(add_query_arg(['sort' => $sort_toggle])) . '">Sort: ' . esc_html($sort_label) . '</a>';
    echo '</div>';

    echo '<div class="vogo-history-table-shell">';
    echo '<table class="widefat striped vogo-history-table">';
    echo '<thead><tr><th>ID</th><th>Brand</th><th>Version</th><th>Saved at</th></tr></thead>';
    echo '<tbody id="vogo-history-body">';

    foreach ($rows as $index => $row) {
        $snapshot = json_decode((string) ($row['settings_snapshot'] ?? ''), true);
        $snapshot = is_array($snapshot) ? $snapshot : [];

        echo '<tr class="vogo-history-row' . ($index === 0 ? ' is-active' : '') . '" data-snapshot="' . esc_attr(wp_json_encode($snapshot)) . '">';
        echo '<td>' . esc_html((string) $row['id']) . '</td>';
        echo '<td>' . esc_html((string) $row['brand_name']) . '</td>';
        echo '<td>' . esc_html((string) $row['brand_version']) . '</td>';
        echo '<td>' . esc_html((string) $row['saved_at']) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';

    /**
     * Render a read-only snapshot panel grouped by Brand Options categories.
     * This keeps the same information architecture as Brand Options while
     * explicitly blocking edits by using disabled fields only.
     */
    $definitions = function_exists('vogo_brand_options_get_definitions') ? vogo_brand_options_get_definitions() : [];
    $category_map = [];
    foreach ($definitions as $key => $definition) {
        $category = isset($definition['category']) ? (string) $definition['category'] : 'Other settings';
        if (!isset($category_map[$category])) {
            $category_map[$category] = [];
        }
        $category_map[$category][$key] = $definition;
    }

    $first_snapshot = json_decode((string) ($rows[0]['settings_snapshot'] ?? ''), true);
    $first_snapshot = is_array($first_snapshot) ? $first_snapshot : [];

    /**
     * Image-aware field map used to keep visual media visible in history mode.
     * This mirrors the Brand Control Center behavior where image URLs are
     * displayed with a thumbnail preview below the corresponding input.
     */
    $image_field_keys = ['brand_icon', 'splash_image_top', 'login_top_image'];

    echo '<div class="vogo-history-details">';
    echo '<h2>Selected version details (read-only)</h2>';

    foreach ($category_map as $category_name => $category_fields) {
        echo '<section class="vogo-history-card">';
        echo '<h3>' . esc_html($category_name) . '</h3>';
        echo '<div class="vogo-history-grid">';

        foreach ($category_fields as $key => $definition) {
            $label = isset($definition['label']) ? (string) $definition['label'] : $key;
            $value = isset($first_snapshot[$key]) ? (string) $first_snapshot[$key] : '';
            $is_textarea = strlen($value) > 80 || strpos($value, "\n") !== false;
            $is_image_field = in_array($key, $image_field_keys, true);

            echo '<label class="vogo-history-field">';
            echo '<span>' . esc_html($label) . '</span>';

            if ($is_textarea) {
                echo '<textarea data-key="' . esc_attr($key) . '" readonly disabled rows="3">' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input data-key="' . esc_attr($key) . '" type="text" value="' . esc_attr($value) . '" readonly disabled />';
            }

            /**
             * Keep image previews visible so the read-only history page preserves
             * the same visual context and arrangement used in the main brand page.
             */
            if ($is_image_field) {
                $preview_style = $value !== '' ? '' : ' style="display:none;"';
                echo '<img class="vogo-history-preview" data-preview-key="' . esc_attr($key) . '" src="' . esc_url($value) . '" alt="' . esc_attr($label) . '"' . $preview_style . ' />';
            }

            echo '</label>';
        }

        echo '</div>';
        echo '</section>';
    }

    echo '</div>';

    /**
     * Version selector script:
     * - supports filter by brand/version/date text
     * - keeps one active row selection
     * - updates the lower read-only panel from selected row snapshot JSON
     */
    echo '<script>';
    echo 'document.addEventListener("DOMContentLoaded", function () {';
    echo '  const rows = Array.from(document.querySelectorAll(".vogo-history-row"));';
    echo '  const filterInput = document.getElementById("vogo-history-filter");';
    echo '  const fields = Array.from(document.querySelectorAll(".vogo-history-details [data-key]"));';
    echo '  const imagePreviews = Array.from(document.querySelectorAll(".vogo-history-preview[data-preview-key]"));';
    echo '  const applySnapshot = function(snapshot) {';
    echo '    fields.forEach(function(field) {';
    echo '      const key = field.getAttribute("data-key") || "";';
    echo '      const value = snapshot && Object.prototype.hasOwnProperty.call(snapshot, key) ? String(snapshot[key] ?? "") : "";';
    echo '      if (field.tagName === "TEXTAREA") {';
    echo '        field.textContent = value;';
    echo '      }';
    echo '      field.value = value;';
    echo '    });';
    echo '    imagePreviews.forEach(function(preview) {';
    echo '      const key = preview.getAttribute("data-preview-key") || "";';
    echo '      const value = snapshot && Object.prototype.hasOwnProperty.call(snapshot, key) ? String(snapshot[key] ?? "") : "";';
    echo '      preview.src = value;';
    echo '      preview.style.display = value ? "block" : "none";';
    echo '    });';
    echo '  };';
    echo '  const selectRow = function(targetRow) {';
    echo '    rows.forEach(function(row) { row.classList.remove("is-active"); });';
    echo '    targetRow.classList.add("is-active");';
    echo '    const raw = targetRow.getAttribute("data-snapshot") || "{}";';
    echo '    let snapshot = {};';
    echo '    try { snapshot = JSON.parse(raw); } catch (err) { snapshot = {}; }';
    echo '    applySnapshot(snapshot);';
    echo '  };';
    echo '  rows.forEach(function(row) {';
    echo '    row.addEventListener("click", function () { selectRow(row); });';
    echo '  });';
    echo '  if (rows.length > 0) { selectRow(rows[0]); }';
    echo '  if (filterInput) {';
    echo '    filterInput.addEventListener("input", function () {';
    echo '      const query = filterInput.value.trim().toLowerCase();';
    echo '      rows.forEach(function(row) {';
    echo '        const text = row.textContent.toLowerCase();';
    echo '        row.style.display = !query || text.indexOf(query) !== -1 ? "" : "none";';
    echo '      });';
    echo '    });';
    echo '  }';
    echo '});';
    echo '</script>';

    /**
     * Local styles for the two-zone history layout:
     * - top list area fixed to about five visible rows with vertical scroll
     * - bottom panel mirrors VOGO card style, but without green action header
     */
    echo '<style>';
    echo '.vogo-history-toolbar{display:flex;gap:10px;align-items:center;margin:14px 0;}';
    echo '.vogo-history-table-shell{border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;background:#fff;}';
    echo '.vogo-history-table thead,.vogo-history-table tbody tr{display:table;width:100%;table-layout:fixed;}';
    echo '.vogo-history-table tbody{display:block;max-height:210px;overflow-y:auto;}';
    echo '.vogo-history-row{cursor:pointer;transition:background .2s ease;}';
    echo '.vogo-history-row:hover{background:#f8fafc;}';
    echo '.vogo-history-row.is-active{background:#e7f7ef !important;outline:1px solid #0f9d58;}';
    echo '.vogo-history-details{margin-top:18px;display:grid;gap:14px;}';
    echo '.vogo-history-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px;}';
    echo '.vogo-history-card h3{margin:0 0 10px 0;color:#0f172a;}';
    echo '.vogo-history-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;}';
    echo '.vogo-history-field{display:flex;flex-direction:column;gap:6px;}';
    echo '.vogo-history-field span{font-weight:600;color:#334155;}';
    echo '.vogo-history-field input,.vogo-history-field textarea{width:100%;border:1px solid #d1d5db;border-radius:10px;padding:8px 10px;background:#f8fafc;color:#0f172a;}';
    echo '.vogo-history-field textarea{resize:vertical;min-height:76px;}';
    echo '.vogo-history-preview{display:block;max-width:180px;max-height:120px;object-fit:contain;border:1px solid #d1d5db;border-radius:10px;background:#fff;padding:6px;}';
    echo '</style>';

    echo '</div>';
}

/**
 * Handle restore requests from the Version history screen.
 */
function vogo_brand_version_history_restore() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized request.', 'Unauthorized', ['response' => 403]);
    }

    check_admin_referer('vogo_brand_version_restore');

    global $wpdb;

    $version_id = isset($_POST['version_id']) ? absint($_POST['version_id']) : 0;
    if ($version_id <= 0) {
        wp_safe_redirect(admin_url('admin.php?page=vogo-brand-version-history'));
        exit;
    }

    $table = vogo_brand_version_history_ensure_table();
    $row = $wpdb->get_row($wpdb->prepare("SELECT settings_snapshot FROM {$table} WHERE id = %d LIMIT 1", $version_id), ARRAY_A);
    if (!$row || empty($row['settings_snapshot'])) {
        wp_safe_redirect(admin_url('admin.php?page=vogo-brand-version-history'));
        exit;
    }

    $snapshot_data = json_decode((string) $row['settings_snapshot'], true);
    if (!is_array($snapshot_data)) {
        wp_safe_redirect(admin_url('admin.php?page=vogo-brand-version-history'));
        exit;
    }

    // Restore each saved key back into the master brand options table.
    $definitions = function_exists('vogo_brand_options_get_definitions') ? vogo_brand_options_get_definitions() : [];
    foreach ($snapshot_data as $key => $value) {
        if (!is_string($key)) {
            continue;
        }

        $definition = $definitions[$key] ?? null;
        $description = $definition['description'] ?? null;
        $category = $definition['category'] ?? null;
        vogo_brand_option_set($key, (string) $value, $description, $category);
    }

    wp_safe_redirect(add_query_arg(['page' => 'vogo-brand-version-history', 'restored' => '1'], admin_url('admin.php')));
    exit;
}

add_action('admin_post_vogo_brand_version_restore', 'vogo_brand_version_history_restore');
