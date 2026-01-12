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
        add_action('wp_enqueue_scripts', [$this, 'aiclinic_enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'aiclinic_enqueue_admin_assets']);
        // add_action('admin_notices', [$this, 'aiclinic_admin_notice']);
        add_filter('script_loader_tag', [$this, 'aiclinic_add_module_type'], 10, 3);
    }

    /**
     * Return React app configuration list.
     *
     * Each app defines shortcode, JS entry, context (public/admin),
     * and optional admin page slug.
     *
     * @return array<string, array>
     */
    public function aiclinic_get_apps_config()
    {
        return [
            'search' => [
                'shortcode' => 'ai_react_search',
                'root_id' => 'ai-search-root',
                'app_path' => 'public/search/index.js',
                'title' => 'AI Search Module',
                'context' => 'public',
            ],
            'management' => [
                'shortcode' => 'ai_react_clinic_management',
                'root_id' => 'clinic-management-root',
                'app_path' => 'admin/management/index.js',
                'title' => 'Clinic Management Module',
                'context' => 'admin',
                'admin_page' => 'aiclinic-management',
            ],
            'management-admin' => [
                'shortcode' => 'ai_react_clinic_management_admin',
                'root_id' => 'clinic-management-admin-root',
                'app_path' => 'admin/management-admin/index.js',
                'title' => 'Clinic Management Admin Module',
                'context' => 'admin',
                'admin_page' => 'aiclinic-management',
            ],
            // TODO add new service
        ];
    }

    /**
     * Get a built asset file matching a glob pattern.
     *
     * Used to resolve Vite hashed filenames.
     *
     * @param string $pattern
     * @return string|null Relative file path or null if not found.
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
     * Force type="module" for React build scripts.
     *
     * @param string $tag
     * @param string $handle
     * @param string $src
     * @return string
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
     * Enqueue shared vendor assets (React, AntD, CSS, reset).
     *
     * Loaded once per request when at least one app is active.
     *
     * @return void
     */
    private function aiclinic_enqueue_vendors()
    {
        $build_url = CLINIC_SEARCH_PLUGIN_URL . 'react-service/ui-dist/';
        $css_file = $this->aiclinic_get_hashed_file('shared/assets/style-*.css');
        $vendor_react = $this->aiclinic_get_hashed_file('shared/chunks/vendor-react-*.js');
        $vendor_antd = $this->aiclinic_get_hashed_file('shared/chunks/vendor-antd-*.js');
        $reset_js     = $this->aiclinic_get_hashed_file('shared/chunks/reset-*.js');
        $clinic_modal_js     = $this->aiclinic_get_hashed_file('shared/chunks/edit-clinic-modal-*.js');

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

        if ($reset_js) {
            wp_enqueue_script(
                'ai-react-reset',
                $build_url . $reset_js,
                ['ai-react-vendor-react'],
                '1.0.0',
                true
            );
        }

        if ($clinic_modal_js) {
            wp_enqueue_script(
                'ai-react-edit-clinic-modal',
                $build_url . $clinic_modal_js,
                ['ai-react-vendor-react'],
                '1.0.0',
                true
            );
        }
    }

    /**
     * Enqueue a specific React application bundle.
     *
     * @param string $app_key
     * @param array  $config
     * @return void
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
     * Check if current post content contains a shortcode.
     *
     * @param string $shortcode
     * @return bool
     */
    private function aiclinic_page_has_shortcode(string $shortcode): bool
    {
        global $post;

        if (!$post || empty($post->post_content)) {
            return false;
        }

        return preg_match('/\[' . preg_quote($shortcode, '/') . '(\s|\])/', $post->post_content);
    }

    /**
     * Filter apps by execution context.
     *
     * @param string $context public|admin
     * @return array
     */
    private function aiclinic_get_apps_by_context(string $context): array
    {
        return array_filter(
            $this->aiclinic_get_apps_config(),
            fn($app) => in_array($context, (array) ($app['context'] ?? 'public'), true)
        );
    }

    /**
     * Determine whether current public page contains any React shortcode.
     *
     * Used as a performance guard.
     *
     * @return bool
     */
    private function aiclinic_page_has_public_app()
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
     * Enqueue React assets for public pages.
     *
     * Loads vendor files once and app bundles based on detected shortcodes.
     *
     * @return void
     */
    public function aiclinic_enqueue_frontend_assets()
    {
        if (!$this->aiclinic_page_has_public_app()) {
            return;
        }

        $this->aiclinic_enqueue_vendors();

        foreach ($this->aiclinic_get_apps_by_context('public') as $key => $config) {
            if (!empty($config['shortcode']) && $this->aiclinic_page_has_shortcode($config['shortcode'])) {
                $this->aiclinic_enqueue_app($key, $config);
            }
        }
    }

    /**
     * Enqueue React assets for admin pages.
     *
     * Loads assets based on matched admin page slug.
     *
     * @param string $hook
     * @return void
     */
    public function aiclinic_enqueue_admin_assets($hook)
    {
        if (!isset($_GET['page'])) {
            return;
        }

        $current_page = sanitize_text_field($_GET['page']);

        if ($current_page === 'aiclinic-management') {
            $this->aiclinic_enqueue_vendors();

            if (current_user_can('administrator')) {
                $this->aiclinic_enqueue_app('management-admin', $this->aiclinic_get_apps_config()['management-admin']);
            } else {
                $this->aiclinic_enqueue_app('management', $this->aiclinic_get_apps_config()['management']);
            }
        }
    }

    /**
     * Register shortcodes for all configured React apps.
     *
     * @return void
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
