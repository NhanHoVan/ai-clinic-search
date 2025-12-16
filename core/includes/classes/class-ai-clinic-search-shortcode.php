<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AI_Clinic_Search_Shortcode
 *
 * Manage React app display via shortcode, load JS build, vendor...
 */
class AI_Clinic_Search_Shortcode
{
    public function __construct()
    {
        add_action('init', [$this, 'aiclinic_start_session']);
        add_action('wp', [$this, 'aiclinic_count_unique_visit']);
        add_shortcode('site_visit_count', [$this, 'aiclinic_shortcode_visit_count']);

        add_action('init', [$this, 'aiclinic_register_shortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'aiclinic_enqueue_assets']);
        // add_action('admin_notices', [$this, 'aiclinic_admin_notice']);
        add_filter('script_loader_tag', [$this, 'aiclinic_add_module_type'], 10, 3);
    }

    /**
     * React service list configuration
     */
    public function aiclinic_get_apps_config()
    {
        return [
            'search' => [
                'shortcode' => 'ai_react_search',
                'root_id' => 'ai-search-root',
                'app_path' => 'public/search/index.js',
                'title' => 'AI Search Module',
            ],
            // TODO add new service
        ];
    }

    /**
     * Get the file with the hash in the build directory
     */
    private function aiclinic_get_hashed_file($pattern)
    {
        $build_dir = CLINIC_SEARCH_PLUGIN_DIR . 'react-service/ui-dist/';
        $files = glob($build_dir . $pattern);
        if (!empty($files)) {
            return str_replace($build_dir, '', $files[0]);
        }
        return null;
    }

    /**
     * Check if the current page contains any apps
     */
    private function aiclinic_page_has_app()
    {
        global $post;
        if (!isset($post->post_content)) {
            return false;
        }

        $apps = $this->aiclinic_get_apps_config();
        foreach ($apps as $app) {
            if (strpos($post->post_content, '[' . $app['shortcode'] . ']') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the list of active apps on the page
     */
    private function aiclinic_get_active_apps()
    {
        global $post;
        if (!isset($post->post_content)) {
            return [];
        }

        $active = [];
        foreach ($this->aiclinic_get_apps_config() as $key => $app) {
            if (strpos($post->post_content, '[' . $app['shortcode'] . ']') !== false) {
                $active[$key] = $app;
            }
        }

        return $active;
    }

    /**
     * Add type="module" for script of react service
     */
    public function aiclinic_add_module_type($tag, $handle, $src)
    {
        if (strpos($src, 'react-service/ui-dist/') !== false) {
            $tag = str_replace('type=\'text/javascript\'', 'type="module"', $tag);
            $tag = str_replace('type="text/javascript"', 'type="module"', $tag);
            if (strpos($tag, 'type="module"') === false) {
                $tag = str_replace(' src=', ' type="module" src=', $tag);
            }
        }
        return $tag;
    }

    /**
     * Enqueue vendor assets (shared chunks: react, antd, css)
     */
    private function aiclinic_enqueue_vendors()
    {
        $build_url = CLINIC_SEARCH_PLUGIN_URL . 'react-service/ui-dist/';
        $css_file = $this->aiclinic_get_hashed_file('shared/assets/style-*.css');
        $vendor_react = $this->aiclinic_get_hashed_file('shared/chunks/vendor-react-*.js');
        $vendor_antd = $this->aiclinic_get_hashed_file('shared/chunks/vendor-antd-*.js');

        if ($css_file) {
            wp_enqueue_style('ai-react-shared-css', $build_url . $css_file, [], '1.0.0');
        }

        if ($vendor_react) {
            wp_enqueue_script('ai-react-vendor-react', $build_url . $vendor_react, [], '1.0.0', true);
        }

        if ($vendor_antd) {
            wp_enqueue_script(
                'ai-react-vendor-antd',
                $build_url . $vendor_antd,
                ['ai-react-vendor-react'],
                '1.0.0',
                true
            );
        }
    }

    /**
     * Enqueue JS service
     */
    private function aiclinic_enqueue_app($app_key, $config)
    {
        $build_dir = CLINIC_SEARCH_PLUGIN_DIR . 'react-service/ui-dist/';
        $build_url = CLINIC_SEARCH_PLUGIN_URL . 'react-service/ui-dist/';
        $app_path = $config['app_path'];

        if (!file_exists($build_dir . $app_path)) {
            return;
        }

        $handle = 'ai-react-app-' . $app_key;

        wp_enqueue_script(
            $handle,
            $build_url . $app_path,
            ['ai-react-vendor-react', 'ai-react-vendor-antd'],
            '1.0.0',
            true
        );

        wp_localize_script($handle, 'aiReactConfig', [
            'restUrl' => esc_url(rest_url()),
            'siteUrl' => esc_url(home_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'appKey' => $app_key,
        ]);
    }

    /**
     * Enqueue all assets (vendor + app)
     */
    public function aiclinic_enqueue_assets()
    {
        if (!$this->aiclinic_page_has_app()) {
            return;
        }

        $this->aiclinic_enqueue_vendors();
        foreach ($this->aiclinic_get_active_apps() as $key => $config) {
            $this->aiclinic_enqueue_app($key, $config);
        }
    }

    /**
     * Register shortcodes corresponding to each React Service
     */
    public function aiclinic_register_shortcodes()
    {
        foreach ($this->aiclinic_get_apps_config() as $key => $config) {
            add_shortcode($config['shortcode'], function () use ($config) {
                return '<div id="' . esc_attr($config['root_id']) . '"></div>';
            });
        }
    }

    /**
     * Display shortcode list in admin editor
     */
    public function aiclinic_admin_notice()
    {
        $screen = get_current_screen();
        $allowed_ids = ['post', 'page', 'edit-post', 'edit-page', 'site-editor'];

        if (!in_array($screen->id, $allowed_ids, true)) {
            return;
        }

        $apps = $this->aiclinic_get_apps_config();
?>
        <div class="notice notice-info">
            <p><strong>Available React App Shortcodes:</strong></p>
            <ul>
                <?php foreach ($apps as $config): ?>
                    <li>
                        <code>[<?php echo esc_html($config['shortcode']); ?>]</code> –
                        <?php echo esc_html($config['title']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
<?php
    }

    /**
     * Initialize PHP session if not already started.
     * 
     * @return void
     */
    public function aiclinic_start_session()
    {
        if (!session_id()) {
            session_start();
        }
    }

    /**
     * Count a unique site visit using PHP session.
     *
     * @return void
     */
    public function aiclinic_count_unique_visit()
    {
        if (!isset($_SESSION['aiclinic_visited'])) {
            $_SESSION['aiclinic_visited'] = true;

            $count = get_option('aiclinic_visit_count', 0);
            $count++;
            update_option('aiclinic_visit_count', $count);
        }
    }

    /**
     * Shortcode callback for displaying the total number of site visits.
     *
     * Usage: [site_visit_count]
     *
     * @return string Formatted visit count.
     */
    public function aiclinic_shortcode_visit_count()
    {
        return number_format(get_option('aiclinic_visit_count', 0));
    }
}
