<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display the settings page.
 */
function aiclinic_settings_page()
{
?>
    <div class="container-fluid mt-4">
        <h3 class="mb-4"><i class="fas fa-cog"></i> <?php echo esc_html__('General Settings', 'ai-clinic-search') ?></h3>
        <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') : ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo esc_html__('Settings have been saved.', 'ai-clinic-search') ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <!-- TODO -->
        </div>
    </div>
<?php
}
