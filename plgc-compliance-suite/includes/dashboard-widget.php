<?php
/**
 * Dashboard Widget
 *
 * @package PLGC_DocMgr
 */

defined('ABSPATH') || exit;

function plgc_docmgr_dashboard_widget() {
    wp_add_dashboard_widget(
        'plgc_docmgr_overview',
        '📋 Compliance Suite Overview',
        'plgc_docmgr_dashboard_content',
        null, null, 'normal', 'default'
    );
}
add_action('wp_dashboard_setup', 'plgc_docmgr_dashboard_widget');

function plgc_docmgr_dashboard_content() {
    global $wpdb;

    $counts = [
        'active'  => 0,
        'review'  => 0,
        'archived' => 0,
        'a11y_issue' => 0,
        'expiring' => 0,
    ];

    $counts['active'] = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_plgc_lifecycle'
         WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
         AND (pm.meta_value = 'active' OR pm.meta_value IS NULL)"
    );

    $counts['review'] = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE pm.meta_key = '_plgc_lifecycle' AND pm.meta_value = 'review'"
    );

    $counts['archived'] = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE pm.meta_key = '_plgc_lifecycle' AND pm.meta_value = 'archived'"
    );

    $counts['a11y_issue'] = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE pm.meta_key = '_plgc_a11y_status'
         AND pm.meta_value IN ('non_compliant','unknown')
         AND p.post_type = 'attachment' AND p.post_status = 'inherit'"
    );

    $counts['expiring'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_plgc_retention_date'
         INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_plgc_lifecycle' AND pm2.meta_value = 'active'
         WHERE pm.meta_value BETWEEN %s AND %s",
        wp_date('Y-m-d'), wp_date('Y-m-d', strtotime('+30 days'))
    ));

    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div style="padding:10px;background:#f0f0f0;border-radius:4px;text-align:center;">
            <div style="font-size:22px;font-weight:700;color:#567915;"><?php echo $counts['active']; ?></div>
            <div style="font-size:11px;color:#666;">Active</div>
        </div>
        <div style="padding:10px;background:<?php echo $counts['review'] > 0 ? '#fff3cd' : '#f0f0f0'; ?>;border-radius:4px;text-align:center;">
            <div style="font-size:22px;font-weight:700;color:<?php echo $counts['review'] > 0 ? '#856404' : '#666'; ?>;"><?php echo $counts['review']; ?></div>
            <div style="font-size:11px;color:#666;">Pending Review</div>
        </div>
        <div style="padding:10px;background:#f0f0f0;border-radius:4px;text-align:center;">
            <div style="font-size:22px;font-weight:700;color:#666;"><?php echo $counts['archived']; ?></div>
            <div style="font-size:11px;color:#666;">Archived</div>
        </div>
        <div style="padding:10px;background:<?php echo $counts['a11y_issue'] > 0 ? '#f8d7da' : '#f0f0f0'; ?>;border-radius:4px;text-align:center;">
            <div style="font-size:22px;font-weight:700;color:<?php echo $counts['a11y_issue'] > 0 ? '#721c24' : '#666'; ?>;"><?php echo $counts['a11y_issue']; ?></div>
            <div style="font-size:11px;color:#666;">Need A11y Review</div>
        </div>
    </div>
    <?php if ($counts['expiring'] > 0) : ?>
        <p style="margin-top:10px;padding:6px;background:#fff3cd;border-radius:4px;font-size:12px;">
            ⚠️ <strong><?php echo $counts['expiring']; ?></strong> document(s) expiring within 30 days.
        </p>
    <?php endif; ?>
    <p style="margin-top:10px;font-size:12px;">
        <a href="<?php echo admin_url('upload.php?plgc_lifecycle=review&mode=list'); ?>">Review queue →</a> |
        <a href="<?php echo admin_url('options-general.php?page=plgc-docmgr'); ?>">Settings →</a>
    </p>
    <?php
}
