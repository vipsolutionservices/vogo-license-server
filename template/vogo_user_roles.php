<?php
/**
 * VOGO User Roles admin screen module.
 *
 * Provides the interface and persistence layer used by administrators to
 * inspect users and manage their WordPress role assignments in one place.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retrieve users ordered for deterministic rendering in the roles UI.
 */
function vogo_brand_options_get_sorted_users_for_roles_page() {
    $users = get_users([
        'orderby' => 'display_name',
        'order' => 'ASC',
        'fields' => ['ID', 'display_name', 'user_login', 'user_email'],
    ]);

    usort($users, static function ($left, $right) {
        return strcasecmp((string) $left->display_name, (string) $right->display_name);
    });

    return $users;
}

/**
 * Persist role assignments submitted from the User-Roles screen.
 */
function vogo_brand_options_save_user_roles() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('vogo_user_roles_save');

    $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $target_user = $user_id > 0 ? get_user_by('id', $user_id) : false;
    if (!$target_user) {
        wp_safe_redirect(add_query_arg(['page' => 'vogo-user-roles', 'updated' => '0'], admin_url('admin.php')));
        exit;
    }

    $roles_registry = wp_roles();
    $available_roles = is_object($roles_registry) && is_array($roles_registry->roles) ? array_keys($roles_registry->roles) : [];

    $requested_roles = isset($_POST['roles']) && is_array($_POST['roles']) ? array_map('sanitize_key', wp_unslash($_POST['roles'])) : [];
    $requested_roles = array_values(array_intersect($requested_roles, $available_roles));

    $user = new WP_User($user_id);
    $current_roles = is_array($user->roles) ? $user->roles : [];

    // Reconcile each available role against the submitted checkbox state.
    foreach ($available_roles as $role_slug) {
        $has_role = in_array($role_slug, $current_roles, true);
        $should_have_role = in_array($role_slug, $requested_roles, true);

        if ($has_role && !$should_have_role) {
            $user->remove_role($role_slug);
        }

        if (!$has_role && $should_have_role) {
            $user->add_role($role_slug);
        }
    }

    wp_safe_redirect(add_query_arg([
        'page' => 'vogo-user-roles',
        'updated' => '1',
        'selected_user' => $user_id,
    ], admin_url('admin.php')));
    exit;
}

add_action('admin_post_vogo_save_user_roles', 'vogo_brand_options_save_user_roles');

/**
 * Render the User-Roles management page in WordPress admin.
 */
function vogo_brand_options_render_user_roles_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $users = vogo_brand_options_get_sorted_users_for_roles_page();

    $selected_user_id = isset($_GET['selected_user']) ? (int) $_GET['selected_user'] : 0;
    if ($selected_user_id <= 0 && !empty($users)) {
        $selected_user_id = (int) $users[0]->ID;
    }

    $selected_user = $selected_user_id > 0 ? get_user_by('id', $selected_user_id) : false;

    $roles_registry = wp_roles();
    $roles = [];
    if (is_object($roles_registry) && is_array($roles_registry->roles)) {
        foreach ($roles_registry->roles as $slug => $role_data) {
            $roles[] = [
                'slug' => (string) $slug,
                'name' => isset($role_data['name']) ? translate_user_role($role_data['name']) : (string) $slug,
            ];
        }
    }

    usort($roles, static function ($left, $right) {
        return strcasecmp($left['name'], $right['name']);
    });

    $selected_user_roles = [];
    if ($selected_user instanceof WP_User) {
        $selected_user_roles = is_array($selected_user->roles) ? $selected_user->roles : [];
    }

    echo '<div class="wrap vogo-user-roles-page">';
    echo '<h1>User-Roles</h1>';
    echo '<p>Manage role assignments for each user. Select a user, tick roles, and save your changes.</p>';

    if (!empty($_GET['updated']) && (string) $_GET['updated'] === '1') {
        echo '<div class="notice notice-success is-dismissible"><p>User roles updated.</p></div>';
    }

    echo '<div class="vogo-user-roles-toolbar">';
    echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=vogo-brand-options')) . '">Back to Brand Control Center</a>';
    echo '</div>';

    echo '<div class="vogo-user-roles-layout">';

    // Left panel: searchable user list used to select the editing target.
    echo '<aside class="vogo-user-roles-users">';
    echo '<label class="screen-reader-text" for="vogo-user-search">Filter users</label>';
    echo '<input type="search" id="vogo-user-search" class="vogo-user-search" placeholder="Filter users..." autocomplete="off" />';
    echo '<ul class="vogo-user-list" id="vogo-user-list">';
    foreach ($users as $user_item) {
        $is_selected = ((int) $user_item->ID === (int) $selected_user_id);
        $label = trim((string) $user_item->display_name) !== '' ? $user_item->display_name : $user_item->user_login;
        echo '<li data-filter-text="' . esc_attr(strtolower($label . ' ' . $user_item->user_login . ' ' . $user_item->user_email)) . '">';
        echo '<a class="vogo-user-list-item' . ($is_selected ? ' is-active' : '') . '" href="' . esc_url(add_query_arg(['page' => 'vogo-user-roles', 'selected_user' => (int) $user_item->ID], admin_url('admin.php'))) . '">';
        echo '<strong>' . esc_html($label) . '</strong>';
        echo '<span>@' . esc_html($user_item->user_login) . '</span>';
        echo '</a>';
        echo '</li>';
    }
    echo '</ul>';
    echo '</aside>';

    // Right panel: role checklist editor for the currently selected user.
    echo '<section class="vogo-user-roles-editor">';
    if (!$selected_user instanceof WP_User) {
        echo '<p class="vogo-user-roles-empty">No user selected.</p>';
    } else {
        echo '<h2>' . esc_html($selected_user->display_name ?: $selected_user->user_login) . '</h2>';
        echo '<p class="description">Login: <strong>@' . esc_html($selected_user->user_login) . '</strong></p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vogo-user-roles-form">';
        wp_nonce_field('vogo_user_roles_save');
        echo '<input type="hidden" name="action" value="vogo_save_user_roles" />';
        echo '<input type="hidden" name="user_id" value="' . (int) $selected_user->ID . '" />';

        echo '<div class="vogo-role-grid">';
        foreach ($roles as $role) {
            $checked = in_array($role['slug'], $selected_user_roles, true);
            echo '<label class="vogo-role-item">';
            echo '<input type="checkbox" name="roles[]" value="' . esc_attr($role['slug']) . '"' . checked($checked, true, false) . ' />';
            echo '<span>' . esc_html($role['name']) . ' <code>(' . esc_html($role['slug']) . ')</code></span>';
            echo '</label>';
        }
        echo '</div>';

        echo '<p><button type="submit" class="button button-primary">Save</button></p>';
        echo '</form>';
    }
    echo '</section>';

    echo '</div>';
    echo '</div>';

    // Inline page styles scoped to the User-Roles admin screen.
    echo '<style>
        .vogo-user-roles-page h1 { color: #0c542d; }
        .vogo-user-roles-layout { display: grid; grid-template-columns: 320px 1fr; gap: 20px; margin-top: 16px; }
        .vogo-user-roles-users, .vogo-user-roles-editor { background: #ffffff; border: 1px solid #d7e5db; border-radius: 12px; padding: 16px; }
        .vogo-user-search { width: 100%; margin-bottom: 12px; border: 1px solid #b4d1be; border-radius: 8px; }
        .vogo-user-list { margin: 0; padding: 0; list-style: none; max-height: 520px; overflow: auto; }
        .vogo-user-list-item { display: block; padding: 10px 12px; border-radius: 8px; color: #0f172a; text-decoration: none; border: 1px solid transparent; }
        .vogo-user-list-item:hover { background: #f2f9f5; border-color: #b4d1be; }
        .vogo-user-list-item.is-active { background: #0c542d; color: #ffffff; }
        .vogo-user-list-item span { display: block; opacity: .8; font-size: 12px; margin-top: 4px; }
        .vogo-role-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px 14px; margin: 18px 0; }
        .vogo-role-item { display: flex; align-items: center; gap: 8px; border: 1px solid #d7e5db; border-radius: 8px; padding: 10px; background: #f8fcf9; }
        .vogo-role-item code { color: #0c542d; font-size: 11px; }
        @media (max-width: 900px) { .vogo-user-roles-layout { grid-template-columns: 1fr; } }
    </style>';

    // Client-side filtering helper for the users list.
    echo '<script>
        (function () {
            const searchInput = document.getElementById("vogo-user-search");
            const userList = document.getElementById("vogo-user-list");
            if (!searchInput || !userList) {
                return;
            }
            searchInput.addEventListener("input", function () {
                const query = (searchInput.value || "").toLowerCase().trim();
                userList.querySelectorAll("li").forEach(function (item) {
                    const text = item.getAttribute("data-filter-text") || "";
                    item.style.display = !query || text.indexOf(query) !== -1 ? "" : "none";
                });
            });
        }());
    </script>';
}
