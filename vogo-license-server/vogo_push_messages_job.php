<?php
/**
 * VOGO Push Messages Delivery Job.
 *
 * Executes delivery of queued push messages by creating forum threads for each
 * customer, attaching optional image payloads, and recording delivery results.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolve or create the support user used as push message sender.
 *
 * @return int
 */
function check_user_suport_brand() {
    $brand_name = (string) vogo_brand_option_get('brand_name', get_bloginfo('name'));
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $brand_name), '-'));
    if ($slug === '') {
        $slug = 'brand';
    }

    $email = 'support@' . $slug . '.local';
    $user = get_user_by('email', $email);
    if ($user instanceof WP_User) {
        return (int) $user->ID;
    }

    $username = sanitize_user('support_' . $slug, true);
    if ($username === '') {
        $username = 'support_brand';
    }

    if (username_exists($username)) {
        $username .= '_' . wp_generate_password(4, false, false);
    }

    $password = wp_generate_password(16, true, true);
    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) {
        throw new RuntimeException('Support user create failed: ' . $user_id->get_error_message());
    }

    $created_user = new WP_User($user_id);
    $created_user->set_role('customer');

    return (int) $user_id;
}

/**
 * Add an optional image answer to a forum thread created by the delivery job.
 *
 * @param int    $thread_id
 * @param int    $support_user_id
 * @param string $image_url
 */
function vogo_push_messages_add_image_answer($thread_id, $support_user_id, $image_url) {
    global $wpdb;

    if ($image_url === '') {
        return;
    }

    $table_answers = $wpdb->prefix . 'vogo_forum_post_answer';
    $now = current_time('mysql');
    $mime_type = wp_check_filetype($image_url)['type'] ?? 'image/jpeg';
    $file_name = basename(parse_url($image_url, PHP_URL_PATH) ?: 'push-image.jpg');

    $wpdb->insert(
        $table_answers,
        [
            'PARENT_ID_vogo_forum_post' => (int) $thread_id,
            'post_author' => (int) $support_user_id,
            'post_answer' => '',
            'post_status' => 'active',
            'post_content' => '#FILE#:' . $image_url,
            'post_date' => $now,
            'mesaj' => $file_name,
            'comment_count' => 0,
            'post_image' => $image_url,
            'post_mime_type' => $mime_type,
            'created_at' => $now,
            'created_user' => (int) $support_user_id,
            'modified_at' => $now,
            'modified_user' => (int) $support_user_id,
        ],
        ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%d']
    );

    if ($wpdb->last_error) {
        throw new RuntimeException('Image answer insert failed: ' . $wpdb->last_error);
    }
}

/**
 * Process all queued push messages and deliver them to all target users.
 */
function vogo_push_messages_run_job() {
    global $wpdb;

    vogo_push_messages_ensure_tables();

    $table_messages = vogo_push_messages_table();
    $table_details = vogo_push_messages_details_table();
    $table_post = $wpdb->prefix . 'vogo_forum_post';
    $table_read = $wpdb->prefix . 'vogo_forum_post_no_to_read';

    $messages = $wpdb->get_results("SELECT * FROM $table_messages WHERE ready_to_deliver = 1 ORDER BY id ASC", ARRAY_A);

    // Iterate through each message marked as ready for delivery.
    foreach ($messages as $message) {
        $message_id = (int) $message['id'];
        $message_text = (string) $message['mesaj'];
        $image_url = esc_url_raw((string) ($message['image'] ?? ''));
        $delivered = 0;
        $errors = [];

        $wpdb->update($table_messages, ['ready_to_deliver' => 2, 'error_messaje' => ''], ['id' => $message_id], ['%d', '%s'], ['%d']);

        try {
            $support_user_id = check_user_suport_brand();
            $users = get_users(['fields' => ['ID']]);

            // Deliver the current message to each user except the support sender.
            foreach ($users as $user) {
                $target_user_id = (int) $user->ID;
                if ($target_user_id === $support_user_id) {
                    continue;
                }

                $now = current_time('mysql');
                $insert_thread = $wpdb->insert(
                    $table_post,
                    [
                        'post_author' => $support_user_id,
                        'chat_user_id' => $target_user_id,
                        'order_id' => 0,
                        'product_id' => 0,
                        'post_title' => $message_text,
                        'post_content' => $message_text,
                        'mesaj' => $message_text,
                        'post_status' => 'active',
                        'post_date' => $now,
                        'post_modified_date' => $now,
                        'post_modified_user' => $support_user_id,
                    ],
                    ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
                );

                if ($insert_thread === false) {
                    throw new RuntimeException('Thread insert failed for user ' . $target_user_id . ': ' . $wpdb->last_error);
                }

                $thread_id = (int) $wpdb->insert_id;

                $wpdb->query($wpdb->prepare(
                    "INSERT INTO $table_read (post_id, user_id, nr_to_read, created_at, created_by)
                     VALUES (%d, %d, 0, NOW(), %d)
                     ON DUPLICATE KEY UPDATE nr_to_read = 0, updated_at = NOW(), updated_by = VALUES(created_by)",
                    $thread_id,
                    $support_user_id,
                    $support_user_id
                ));

                $wpdb->query($wpdb->prepare(
                    "INSERT INTO $table_read (post_id, user_id, nr_to_read, created_at, created_by)
                     VALUES (%d, %d, 1, NOW(), %d)
                     ON DUPLICATE KEY UPDATE nr_to_read = 1, updated_at = NOW(), updated_by = VALUES(created_by)",
                    $thread_id,
                    $target_user_id,
                    $support_user_id
                ));

                vogo_push_messages_add_image_answer($thread_id, $support_user_id, $image_url);

                $wpdb->insert(
                    $table_details,
                    [
                        'id_mesaj' => $message_id,
                        'user_id' => $target_user_id,
                        'delivery_date' => $now,
                        'answer' => 'delivered',
                    ],
                    ['%d', '%d', '%s', '%s']
                );

                $delivered++;
            }
        } catch (Throwable $throwable) {
            $errors[] = $throwable->getMessage();
        }

        if (!empty($errors)) {
            $wpdb->update(
                $table_messages,
                [
                    'ready_to_deliver' => 5,
                    'error_messaje' => implode(' | ', $errors),
                ],
                ['id' => $message_id],
                ['%d', '%s'],
                ['%d']
            );
            continue;
        }

        $wpdb->update(
            $table_messages,
            [
                'ready_to_deliver' => 3,
                'delivery_date' => current_time('mysql'),
                'number_delivered' => $delivered,
                'error_messaje' => '',
            ],
            ['id' => $message_id],
            ['%d', '%s', '%d', '%s'],
            ['%d']
        );
    }
}

/**
 * Admin endpoint wrapper for manually running the push delivery job.
 */
function vogo_push_messages_run_job_admin() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized request.', 'Unauthorized', ['response' => 403]);
    }

    check_admin_referer('vogo_push_messages_run_job');
    vogo_push_messages_run_job();
    wp_safe_redirect(add_query_arg(['page' => 'vogo-push-messages', 'job' => 'done'], admin_url('admin.php')));
    exit;
}

add_action('admin_post_vogo_push_messages_run_job', 'vogo_push_messages_run_job_admin');
