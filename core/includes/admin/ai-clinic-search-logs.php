<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display the logs page.
 */
function aiclinic_logs_page()
{
    $page_slug = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    $page = max(1, intval($_GET['paged'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    $db = new Ai_Clinic_Search_Db();
    $logs = [];
    $total_items = 0;
    $total_pages = ceil($total_items / $limit);
?>

    <div class="container-fluid mt-4">
        <h3 class="mb-4"><i class="fas fa-list-alt me-2"></i><?php echo esc_html__('Logs Plugin', 'ai-clinic-search'); ?></h3>

        <form method="get" action="" class="mb-3">
            <input type="hidden" name="page" value="<?php echo esc_attr($page_slug); ?>">
            <div class="input-group">
                <input type="search" class="form-control" name="search" placeholder="<?php echo esc_attr__('Search by error action...', 'ai-clinic-search'); ?>" value="<?php echo esc_attr($search); ?>" maxlength="100">
                <div class="input-group-append">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i> <?php echo esc_html__('Search', 'ai-clinic-search'); ?>
                    </button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 20%;"><?php echo esc_html__('Action', 'ai-clinic-search'); ?></th>
                        <th style="width: 35%;"><?php echo esc_html__('Message', 'ai-clinic-search'); ?></th>
                        <th style="width: 25%;"><?php echo esc_html__('Context', 'ai-clinic-search'); ?></th>
                        <th style="width: 15%;"><?php echo esc_html__('Time', 'ai-clinic-search'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs): foreach ($logs as $index => $log): ?>
                            <tr>
                                <td><?php echo esc_html($total_items - ($offset + $index)); ?></td>
                                <td><?php echo esc_html($log['action']); ?></td>
                                <td><?php echo esc_html($log['message']); ?></td>
                                <td><code><?php echo esc_html($log['context']); ?></code></td>
                                <td><?php echo esc_html($log['created_at']); ?></td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="5" class="text-center"><?php echo esc_html__('No logs found.', 'ai-clinic-search'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo esc_url(admin_url("admin.php?page=$page_slug&paged=$i&search=" . urlencode($search))); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

<?php
}
