<?php

function ikonic_assessment_setup()
{
    add_theme_support('wp-block-styles');
    add_theme_support('editor-styles');
    add_editor_style('style.css');
    add_theme_support('align-wide');
    register_nav_menus([
        'primary_menu' => __('Primary Menu', 'ikonic_assessment')
    ]);
}
add_action('after_setup_theme', 'ikonic_assessment_setup');

function ikonic_assessment_enqueue_styles() {
    wp_enqueue_style('ikonic-assessment-styles', get_stylesheet_uri());
}
add_action('wp_enqueue_scripts', 'ikonic_assessment_enqueue_styles');

function register_projects_cpt()
{
    $args = array(
        'label' => __('Projects', 'ikonic-assessment'),
        'public'      => true,
        'has_archive' => true,  // ✅ MUST BE TRUE
        'supports'    => array('title', 'editor', 'thumbnail', 'excerpt'),
        'show_in_rest' => true,
        'rewrite'     => array('slug' => 'projects'),
        'menu_icon'   => 'dashicons-portfolio',
    );
    register_post_type('projects', $args);
}
add_action('init', 'register_projects_cpt');

function add_project_meta_box()
{
    add_meta_box(
        'project_meta_box',           // Unique ID
        'Project Details',            // Box title
        'project_meta_callback',      // Callback function
        'projects',                   // Post type (ensure this is your custom post type)
        'normal',                      // Context (where it appears)
        'high'                         // Priority
    );
}
add_action('add_meta_boxes', 'add_project_meta_box');



function project_meta_callback($post)
{
    // Retrieve existing values
    $start_date = get_post_meta($post->ID, 'project_start_date', true);
    $end_date = get_post_meta($post->ID, 'project_end_date', true);
    
    // Security nonce field
    wp_nonce_field('save_project_meta_nonce', 'project_meta_nonce');
?>
    <p>
        <label for="project_start_date"><strong>Start Date:</strong></label><br>
        <input type="date" id="project_start_date" name="project_start_date" value="<?= esc_attr($start_date); ?>" />
    </p>
    <p>
        <label for="project_end_date"><strong>End Date:</strong></label><br>
        <input type="date" id="project_end_date" name="project_end_date" value="<?= esc_attr($end_date); ?>" />
    </p>
    <?php
}

function save_project_meta($post_id)
{
    // Verify the nonce
    if (!isset($_POST['project_meta_nonce']) || !wp_verify_nonce($_POST['project_meta_nonce'], 'save_project_meta_nonce')) {
        return;
    }

    // Prevent saving during autosave or bulk editing
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Ensure user has permission
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save start date
    if (isset($_POST['project_start_date'])) {
        update_post_meta($post_id, 'project_start_date', sanitize_text_field($_POST['project_start_date']));
    }

    // Save end date
    if (isset($_POST['project_end_date'])) {
        update_post_meta($post_id, 'project_end_date', sanitize_text_field($_POST['project_end_date']));
    }
}
add_action('save_post', 'save_project_meta');
function register_project_meta_fields()
{
    register_post_meta('projects', 'project_start_date', [
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string'
    ]);

    register_post_meta('projects', 'project_end_date', [
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string'
    ]);

    register_post_meta('projects', 'project_url', [
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string'
    ]);
}
add_action('init', 'register_project_meta_fields');

function get_projects_api()
{
    $projects = get_posts(array('post_type' => 'projects', 'numberposts' => -1));
    $data = array();

    foreach ($projects as $project) {
        $data[] = array(
            'title' => $project->post_title,
            'url' => get_permalink($project->ID),
            'start_date' => get_post_meta($project->ID, 'project_start_date', true),
            'end_date' => get_post_meta($project->ID, 'project_end_date', true),
        );
    }

    return rest_ensure_response($data);
}

add_action('rest_api_init', function () {
    register_rest_route('ikonic-assessment/v1', '/projects/', array(
        'methods' => 'GET',
        'callback' => 'get_projects_api',
    ));
});

function filter_projects_by_date($query)
{
    if ($query->is_post_type_archive('projects') && !is_admin()) {
        if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
            $query->set('meta_query', array(
                'relation' => 'AND',
                array(
                    'key' => 'project_start_date',
                    'value' => $_GET['start_date'],
                    'compare' => '>=',
                    'type' => 'DATE'
                ),
                array(
                    'key' => 'project_end_date',
                    'value' => $_GET['end_date'],
                    'compare' => '<=',
                    'type' => 'DATE'
                ),
            ));
        }
    }
}
add_action('pre_get_posts', 'filter_projects_by_date');

function get_project_meta_shortcode($atts)
{
    $atts = shortcode_atts(['field' => ''], $atts);
    if (!$atts['field']) return '';

    global $post;
    return esc_html(get_post_meta($post->ID, $atts['field'], true));
}
add_shortcode('project_meta', 'get_project_meta_shortcode');