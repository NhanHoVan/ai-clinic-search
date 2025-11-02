<?php

/**
 * Plugin Name: AI Clinic Search
 * Description: Plugin integrates smart clinic search system using AI.
 * Version: 1.0.0
 * Author: Van Nhan
 * Author URI: https://ite-software.com/
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Config các apps
 */
function ai_react_get_apps_config()
{
    return array(
        'search' => array(
            'shortcode' => 'ai_react_search',
            'root_id' => 'ai-search-root',
            'app_path' => 'public/search/index.js',
            'title' => 'AI Search Module',
        ),
        // Thêm app mới ở đây
    );
}

/**
 * Tìm file có hash trong thư mục
 */
function ai_react_get_hashed_file($pattern)
{
    $build_dir = plugin_dir_path(__FILE__) . 'react-app/build/';
    $files = glob($build_dir . $pattern);
    if (!empty($files)) {
        return str_replace($build_dir, '', $files[0]);
    }
    return null;
}

/**
 * Kiểm tra xem trang có chứa shortcode nào không
 */
function ai_react_page_has_app()
{
    global $post;
    if (!isset($post->post_content)) {
        return false;
    }

    $apps = ai_react_get_apps_config();
    foreach ($apps as $app) {
        if (strpos($post->post_content, '[' . $app['shortcode'] . ']') !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Lấy danh sách apps được dùng trong trang
 */
function ai_react_get_active_apps()
{
    global $post;
    if (!isset($post->post_content)) {
        return array();
    }

    $active_apps = array();
    $apps = ai_react_get_apps_config();

    foreach ($apps as $key => $app) {
        if (strpos($post->post_content, '[' . $app['shortcode'] . ']') !== false) {
            $active_apps[$key] = $app;
        }
    }

    return $active_apps;
}

/**
 * Thêm type="module" cho scripts
 */
function ai_react_add_module_type($tag, $handle, $src)
{
    if (strpos($src, 'ai-clinic-search/react-app/build/') !== false) {
        $tag = str_replace('type=\'text/javascript\'', 'type="module"', $tag);
        $tag = str_replace("type='text/javascript'", 'type="module"', $tag);
        $tag = str_replace('type="text/javascript"', 'type="module"', $tag);
        if (strpos($tag, 'type="module"') === false) {
            $tag = str_replace(' src=', ' type="module" src=', $tag);
        }
    }
    return $tag;
}
add_filter('script_loader_tag', 'ai_react_add_module_type', 10, 3);

/**
 * Đăng ký vendors (dùng chung cho tất cả apps)
 */
function ai_react_enqueue_vendors()
{
    $plugin_url = plugin_dir_url(__FILE__) . 'react-app/build/';

    $css_file = ai_react_get_hashed_file('shared/assets/style-*.css');
    $vendor_react = ai_react_get_hashed_file('shared/chunks/vendor-react-*.js');
    $vendor_antd = ai_react_get_hashed_file('shared/chunks/vendor-antd-*.js');

    if ($css_file) {
        wp_enqueue_style(
            'ai-react-shared-css',
            $plugin_url . $css_file,
            array(),
            '1.0.0'
        );
    }

    if ($vendor_react) {
        wp_enqueue_script(
            'ai-react-vendor-react',
            $plugin_url . $vendor_react,
            array(),
            '1.0.0',
            true
        );
    }

    if ($vendor_antd) {
        wp_enqueue_script(
            'ai-react-vendor-antd',
            $plugin_url . $vendor_antd,
            array('ai-react-vendor-react'),
            '1.0.0',
            true
        );
    }
}

/**
 * Đăng ký app cụ thể
 */
function ai_react_enqueue_app($app_key, $app_config)
{
    $plugin_url = plugin_dir_url(__FILE__) . 'react-app/build/';
    $app_js_path = $app_config['app_path'];

    if (!file_exists(plugin_dir_path(__FILE__) . 'react-app/build/' . $app_js_path)) {
        return;
    }

    $handle = 'ai-react-app-' . $app_key;

    wp_enqueue_script(
        $handle,
        $plugin_url . $app_js_path,
        array('ai-react-vendor-react', 'ai-react-vendor-antd'),
        '1.0.0',
        true
    );

    wp_localize_script($handle, 'aiReactConfig', array(
        'restUrl' => esc_url(rest_url()),
        'siteUrl' => esc_url(home_url()),
        'nonce' => wp_create_nonce('wp_rest'),
        'appKey' => $app_key,
    ));
}

/**
 * Enqueue assets khi có shortcode
 */
function ai_react_enqueue_assets()
{
    if (!ai_react_page_has_app()) {
        return;
    }

    ai_react_enqueue_vendors();

    $active_apps = ai_react_get_active_apps();
    foreach ($active_apps as $key => $config) {
        ai_react_enqueue_app($key, $config);
    }
}
add_action('wp_enqueue_scripts', 'ai_react_enqueue_assets');

/**
 * Tạo shortcode động cho từng app
 */
function ai_react_register_shortcodes()
{
    $apps = ai_react_get_apps_config();

    foreach ($apps as $key => $config) {
        add_shortcode($config['shortcode'], function () use ($config) {
            return '<div id="' . esc_attr($config['root_id']) . '"></div>';
        });
    }
}
add_action('init', 'ai_react_register_shortcodes');

/**
 * Admin: Hiển thị danh sách shortcodes
 */
function ai_react_admin_notice()
{
    $screen = get_current_screen();

    $allowed_ids = array('post', 'page', 'edit-post', 'edit-page', 'site-editor');
    if (!in_array($screen->id, $allowed_ids, true)) {
        return;
    }

    $apps = ai_react_get_apps_config();
?>
    <div class="notice notice-info">
        <p><strong>Available React App Shortcodes:</strong></p>
        <ul>
            <?php foreach ($apps as $config): ?>
                <li>
                    <code>[<?php echo esc_html($config['shortcode']); ?>]</code> -
                    <?php echo esc_html($config['title']); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php
}
add_action('admin_notices', 'ai_react_admin_notice');
