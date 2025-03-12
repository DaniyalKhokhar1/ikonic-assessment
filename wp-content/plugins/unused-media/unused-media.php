<?php

/**
 * Plugin Name: Unused Media Manager
 * Description: Lists and allows deletion of unused media files in WordPress.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add Admin Menu
function umm_add_admin_menu()
{
    add_menu_page('Unused Media', 'Unused Media', 'manage_options', 'unused-media', 'umm_display_unused_media', 'dashicons-trash', 20);
}
add_action('admin_menu', 'umm_add_admin_menu');

// Display Unused Media
function umm_display_unused_media()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions.', 'unused-media'));
    }
?>
    <div class="wrap">
        <h1>Unused Media Manager</h1>
        <table class="widefat fixed">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Filename</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="umm-media-list">
                <?php foreach (umm_get_unused_media() as $media) : ?>
                    <tr id="umm-row-<?php echo $media['id']; ?>">
                        <td><img src="<?php echo esc_url($media['url']); ?>" width="50" /></td>
                        <td><?php echo esc_html($media['filename']); ?></td>
                        <td>
                            <button class="button umm-delete" data-id="<?php echo esc_attr($media['id']); ?>">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $('.umm-delete').on('click', function() {
                var mediaId = $(this).data('id');
                if (confirm('Are you sure you want to delete this media?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'umm_delete_media',
                            media_id: mediaId,
                            security: '<?php echo wp_create_nonce("umm_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#umm-row-' + mediaId).remove();
                            } else {
                                alert(response.data);
                            }
                        }
                    });
                }
            });
        });
    </script>
<?php
}

// Get Unused Media
function umm_get_unused_media()
{
    global $wpdb;
    $attachments = $wpdb->get_results("SELECT ID, guid FROM {$wpdb->posts} WHERE post_type = 'attachment'");
    $unused = [];

    foreach ($attachments as $attachment) {
        $id = $attachment->ID;
        $url = $attachment->guid;
        $filename = basename($url);

        // Check if media is attached to a post
        $is_attached = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_parent = %d",
            $id
        ));

        // Check if media is used in post content
        $is_used_in_content = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s",
            '%' . $wpdb->esc_like($url) . '%'
        ));

        // Check if media ID is stored in post meta
        $is_used_in_meta = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value = %d",
            $id
        ));

        if (!$is_attached && !$is_used_in_content && !$is_used_in_meta) {
            $unused[] = ['id' => $id, 'url' => $url, 'filename' => $filename];
        }
    }
    return $unused;
}

// Delete Media File
function umm_delete_media()
{
    check_ajax_referer('umm_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized request.');
    }

    $media_id = intval($_POST['media_id']);
    if (wp_delete_attachment($media_id, true)) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to delete media.');
    }
}
add_action('wp_ajax_umm_delete_media', 'umm_delete_media');
