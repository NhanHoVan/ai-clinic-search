<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display the management page.
 */
function aiclinic_management_page()
{
?>
    <div style="padding-right: 20px;">
        <?php if (current_user_can('administrator')) : ?>
            <div id="clinic-management-admin-root"></div>
        <?php else : ?>
            <div id="clinic-management-root"></div>
        <?php endif; ?>
    </div>
<?php
}
