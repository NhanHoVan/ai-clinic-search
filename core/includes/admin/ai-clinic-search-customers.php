<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display the customers page.
 */
function aiclinic_customers_page()
{
    $search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    $db_handler = new Ai_Clinic_Search_Db();

?>
    <div class="container-fluid mt-4">
        <h3 class="mb-4"><i class="fas fa-users"></i> <?php echo __('Customer List', 'ai-clinic-search') ?></h3>

        <div class="alert alert-info" role="alert">
            <i class="fas fa-info-circle"></i> <?php echo __('Unreleased Feature', 'ai-clinic-search') ?>
        </div>

        <form method="GET" class="mb-3">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="<?php echo __('Enter customer phone number or name...', 'ai-clinic-search') ?>" value="<?php echo esc_attr($search_term); ?>">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> <?php echo __('Search', 'ai-clinic-search') ?></button>
                </div>
            </div>
        </form>

        <?php if (!empty($customers)) : ?>
            <div id="customers-table-container" class="table-responsive"></div>
            <nav>
                <ul id="pagination-container" class="pagination mt-3"></ul>
            </nav>
        <?php else : ?>
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo __('No customers found.', 'ai-clinic-search') ?>
            </div>
        <?php endif; ?>
    </div>
<?php
}
