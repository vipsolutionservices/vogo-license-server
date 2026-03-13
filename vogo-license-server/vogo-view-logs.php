<?php
// Dedicated View Logs screen for the Brand Control Center.
if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions.');
}

// Read log files from the daily-rotated log directory.
$log_dir = rtrim(WP_CONTENT_DIR, '/\\') . '/vogo-logs';
$files = [];
if (is_dir($log_dir)) {
    $files = glob($log_dir . '/vogo-log-*.log') ?: [];
}
rsort($files);

// Resolve the selected file from query param, defaulting to the newest log.
$selected_file = isset($_GET['log_file']) ? basename(wp_unslash($_GET['log_file'])) : '';
if ($selected_file === '' && !empty($files)) {
    $selected_file = basename($files[0]);
}

$log_contents = '';
$log_status = '';
$partial_notice = '';
$selected_path = '';

if ($selected_file !== '') {
    // Allow only expected log file pattern to avoid path traversal.
    if (!preg_match('/^vogo-log-\d{4}-\d{2}-\d{2}\.log$/', $selected_file)) {
        $log_status = 'Invalid log file selected.';
        vogo_error_log3('[brand-options][logs] Invalid log file requested: ' . $selected_file);
    } else {
        $selected_path = $log_dir . '/' . $selected_file;
        if (!file_exists($selected_path)) {
            $log_status = 'Log file not found at ' . $selected_path . '.';
            vogo_error_log3('[brand-options][logs] Log file missing: ' . $selected_path);
        } elseif (!is_readable($selected_path)) {
            $log_status = 'Log file exists but is not readable.';
            vogo_error_log3('[brand-options][logs] Log file not readable: ' . $selected_path);
        } else {
            $file_size = filesize($selected_path);
            $max_bytes = 2 * 1024 * 1024;
            if ($file_size !== false && $file_size > $max_bytes) {
                // Read only the tail to avoid blocking the UI on very large files.
                $handle = fopen($selected_path, 'rb');
                if ($handle) {
                    $seek = max(0, $file_size - $max_bytes);
                    if ($seek > 0) {
                        fseek($handle, $seek);
                    }
                    $log_contents = stream_get_contents($handle);
                    fclose($handle);
                    $partial_notice = 'Showing last ' . round($max_bytes / 1024 / 1024, 1) . ' MB of ' . round($file_size / 1024 / 1024, 1) . ' MB.';
                    vogo_error_log3('[brand-options][logs] Loaded tail for large log: ' . $selected_file);
                } else {
                    $log_status = 'Unable to open the log file for reading.';
                    vogo_error_log3('[brand-options][logs] Failed to open log file: ' . $selected_path);
                }
            } else {
                $log_contents = file_get_contents($selected_path);
                if ($log_contents === false) {
                    $log_status = 'Unable to read the log file.';
                    $log_contents = '';
                    vogo_error_log3('[brand-options][logs] Failed to read log file: ' . $selected_path);
                } else {
                    vogo_error_log3('[brand-options][logs] Loaded log file: ' . $selected_file);
                }
            }
        }
    }
} else {
    $log_status = 'No log files available.';
    vogo_error_log3('[brand-options][logs] No log files found in ' . $log_dir);
}

echo '<div class="wrap vogo-brand-logs">';
echo '<h1>VOGO Logs</h1>';
echo '<p class="vogo-muted">Source folder: ' . esc_html($log_dir) . '</p>';
echo '<p><a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=vogo-brand-options')) . '">Back to Brand Control Center</a></p>';

if ($log_status !== '') {
    echo '<div class="notice notice-warning"><p>' . esc_html($log_status) . '</p></div>';
}

echo '<div class="vogo-log-layout">';
echo '<div class="vogo-log-sidebar">';
echo '<h2 class="vogo-log-title">Daily logs</h2>';
if (empty($files)) {
    echo '<div class="vogo-log-empty">No log files available.</div>';
} else {
    echo '<ul class="vogo-log-list">';
    foreach ($files as $file_path) {
        $file_name = basename($file_path);
        $is_active = ($file_name === $selected_file) ? ' is-active' : '';
        $link = add_query_arg(['page' => 'vogo-view-logs', 'log_file' => $file_name], admin_url('admin.php'));
        echo '<li class="vogo-log-item' . esc_attr($is_active) . '"><a href="' . esc_url($link) . '">' . esc_html($file_name) . '</a></li>';
    }
    echo '</ul>';
}
echo '</div>';

echo '<div class="vogo-log-content">';
echo '<h2 class="vogo-log-title">Log content</h2>';
if ($partial_notice !== '') {
    echo '<div class="notice notice-info vogo-log-notice"><p>' . esc_html($partial_notice) . '</p></div>';
}
if ($log_contents === '') {
    echo '<div class="vogo-log-empty">No log entries available.</div>';
} else {
    echo '<pre class="vogo-log-output" data-scroll-end="1">' . esc_html($log_contents) . '</pre>';
}
echo '</div>';
echo '</div>';

echo '</div>';

echo '<style>
    .vogo-brand-logs .vogo-log-layout { display: grid; grid-template-columns: 260px 1fr; gap: 16px; align-items: start; }
    .vogo-brand-logs .vogo-log-sidebar { background: #fff; border-radius: 12px; padding: 16px; border: 1px solid #e2e8f0; max-height: 70vh; overflow: auto; }
    .vogo-brand-logs .vogo-log-content { background: #fff; border-radius: 12px; padding: 16px; border: 1px solid #e2e8f0; }
    .vogo-brand-logs .vogo-log-title { margin: 0 0 12px; font-size: 16px; }
    .vogo-brand-logs .vogo-log-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
    .vogo-brand-logs .vogo-log-item a { display: block; padding: 6px 8px; border-radius: 8px; text-decoration: none; color: #0f172a; background: #f8fafc; }
    .vogo-brand-logs .vogo-log-item.is-active a { background: #0f172a; color: #fff; }
    .vogo-brand-logs .vogo-log-output { background: #0f172a; color: #e2e8f0; padding: 20px; border-radius: 12px; max-height: 70vh; overflow: auto; white-space: pre-wrap; }
    .vogo-brand-logs .vogo-log-empty { padding: 16px; background: #f8fafc; border-radius: 12px; border: 1px dashed #e2e8f0; color: #64748b; }
    .vogo-brand-logs .vogo-muted { color: #64748b; }
    .vogo-brand-logs .vogo-log-notice { margin: 0 0 12px; }
    @media (max-width: 900px) { .vogo-brand-logs .vogo-log-layout { grid-template-columns: 1fr; } }
</style>';

// Scroll to the end of the log output to show the latest entries.
echo '<script>
    (function () {
        var output = document.querySelector(".vogo-log-output[data-scroll-end=\\"1\\"]");
        if (output) { output.scrollTop = output.scrollHeight; }
    })();
</script>';
