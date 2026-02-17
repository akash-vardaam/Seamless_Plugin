<?php

/**
 * Template Part: Dashboard Orders
 */


$orders_per_page = $orders_per_page ?? 6; // From SeamlessRender.php, defaults to 6
$total_orders = count($orders);
$total_pages = $total_orders > 0 ? ceil($total_orders / $orders_per_page) : 1;
?>

<h4 class="seamless-user-dashboard-view-title"><?php _e('Order History', 'seamless-addon'); ?></h4>

<?php if (!empty($orders)): ?>
    <div class="seamless-user-dashboard-orders-container" data-per-page="<?php echo esc_attr($orders_per_page); ?>" data-total-pages="<?php echo esc_attr($total_pages); ?>">
        <div class="seamless-user-dashboard-order-table-container">
            <table class="seamless-user-dashboard-order-table">
                <thead>
                    <tr>
                        <th class="seamless-user-dashboard-col-customer"><?php _e('Customer', 'seamless-addon'); ?></th>
                        <th class="seamless-user-dashboard-col-items"><?php _e('No. Of Products', 'seamless-addon'); ?></th>
                        <th class="seamless-user-dashboard-col-products"><?php _e('Ordered Products', 'seamless-addon'); ?></th>
                        <th class="seamless-user-dashboard-col-status"><?php _e('Status', 'seamless-addon'); ?></th>
                        <th class="seamless-user-dashboard-col-total"><?php _e('Total', 'seamless-addon'); ?></th>
                        <th class="seamless-user-dashboard-col-date"><?php _e('Ordered Date', 'seamless-addon'); ?></th>
                        <th class="seamless-user-dashboard-col-action"><?php _e('Invoice', 'seamless-addon'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $index => $o):
                        // Customer name - use new API structure first
                        $cust = $o['customer_name'] ?? '';
                        if (empty($cust)) {
                            $first = $o['billing_info']['first_name'] ?? ($o['customer']['first_name'] ?? ($o['user']['first_name'] ?? ($o['billing_first_name'] ?? '')));
                            $last = $o['billing_info']['last_name'] ?? ($o['customer']['last_name'] ?? ($o['user']['last_name'] ?? ($o['billing_last_name'] ?? '')));
                            $cust = trim(($first . ' ' . $last)) ?: ($o['customer']['name'] ?? ($o['user']['name'] ?? '—'));
                        }

                        // Items - use new API structure first
                        $count_items = $o['products_count'] ?? 0;
                        $product_list = $o['ordered_product'] ?? '';

                        // If ordered_product is "Order Details", try to get course title from order_items
                        if ($product_list === 'Order Details' && !empty($o['raw']['order_items'][0]['orderable']['title'])) {
                            $product_list = $o['raw']['order_items'][0]['orderable']['title'];
                        }

                        // Fallback to old structure if new fields are empty
                        if (empty($product_list)) {
                            $items = $o['items'] ?? ($o['products'] ?? ($o['order_items'] ?? ($o['lines'] ?? ($o['memberships'] ?? []))));
                            if (!is_array($items)) $items = [];
                            if ($count_items === 0) $count_items = count($items);

                            $names = [];
                            foreach ($items as $it) {
                                $names[] = $it['name'] ?? ($it['title'] ?? ($it['plan']['label'] ?? ($it['product']['name'] ?? 'Item')));
                            }
                            $product_list = implode(', ', array_map('esc_html', array_filter($names)));
                        }

                        // Status
                        $status = $o['status'] ?? ($o['order_status'] ?? '—');
                        $status_key = strtolower((string)$status);
                        $st_class = 'neutral';
                        if (in_array($status_key, ['completed', 'paid', 'successful', 'success'])) {
                            $st_class = 'success';
                        } elseif (in_array($status_key, ['pending', 'processing', 'on-hold'])) {
                            $st_class = 'warning';
                        } elseif (in_array($status_key, ['failed', 'cancelled', 'canceled', 'refunded'])) {
                            $st_class = 'danger';
                        }

                        // Total - use new API structure first
                        $total_amount = $o['total'] ?? ($o['total_amount'] ?? ($o['amount'] ?? ($o['grand_total'] ?? '')));
                        $net_amount = $o['net_total'] ?? ($o['net_amount'] ?? '');
                        $refunded_amount = $o['total_refunded'] ?? ($o['refunded_amount'] ?? 0);
                        $has_refunds = !empty($o['has_refunds']) || ($refunded_amount > 0);

                        // Check if this is an upgrade/downgrade order by looking at notes
                        $order_notes = $o['raw']['notes'] ?? ($o['notes'] ?? '');
                        $has_tooltip = !empty($order_notes) && (
                            stripos($order_notes, 'upgrade') !== false ||
                            stripos($order_notes, 'downgrade') !== false ||
                            stripos($order_notes, 'proration') !== false
                        );

                        $total_fmt = '—';
                        if ($total_amount !== '' || $net_amount !== '') {
                            $ta = is_numeric($total_amount) ? number_format((float)$total_amount, 2) : (string)$total_amount;
                            $na = $net_amount !== '' ? (is_numeric($net_amount) ? number_format((float)$net_amount, 2) : (string)$net_amount) : '';
                            if ($has_refunds || ((string)$na !== '' && (string)$na !== (string)$ta)) {
                                $ref = is_numeric($refunded_amount) ? number_format((float)$refunded_amount, 2) : (string)$refunded_amount;
                                $total_fmt = '<span class="seamless-user-dashboard-strike">$' . esc_html($ta) . '</span> <span class="seamless-user-dashboard-amount-green">$' . esc_html($na) . '</span>' . ($refunded_amount ? ' <span class="seamless-user-dashboard-refunded">(Refunded: $' . esc_html($ref) . ')</span>' : '');
                            } else {
                                $total_fmt = '<span class="seamless-user-dashboard-amount-green">$' . esc_html($ta) . '</span>';
                            }

                            // Add tooltip icon if this is an upgrade/downgrade order
                            if ($has_tooltip) {
                                $total_fmt .= ' <span class="seamless-user-dashboard-info-icon" data-tooltip="' . esc_attr($order_notes) . '">';
                                $total_fmt .= '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
                                $total_fmt .= '<circle cx="12" cy="12" r="10"></circle>';
                                $total_fmt .= '<line x1="12" y1="16" x2="12" y2="12"></line>';
                                $total_fmt .= '<line x1="12" y1="8" x2="12.01" y2="8"></line>';
                                $total_fmt .= '</svg>';
                                $total_fmt .= '</span>';
                            }
                        }

                        // Date and invoice - use new API structure first
                        $date = $o['created_at'] ?? ($o['date'] ?? ($o['purchased_at'] ?? ''));

                        // Format date from ISO 8601 to readable format
                        if (!empty($date)) {
                            try {
                                $dt = new \DateTime($date);
                                $date = $dt->format('M j, Y');
                            } catch (\Exception $e) {
                                // Keep original date if parsing fails
                            }
                        }

                        $order_id = $o['id'] ?? ($o['order_id'] ?? '');
                        $invoice_url = $o['invoice_url'] ?? '';
                        if (empty($invoice_url) && !empty($client_domain) && $order_id !== '') {
                            $invoice_url = trailingslashit($client_domain) . rawurlencode((string)$order_id) . '/pdf/download';
                        }
                    ?>
                        <tr class="seamless-user-dashboard-order-row" data-order-index="<?php echo esc_attr($index); ?>">
                            <td class="seamless-user-dashboard-col-customer" data-label="<?php _e('Customer', 'seamless-addon'); ?>">
                                <?php echo esc_html($cust); ?>
                            </td>
                            <td class="seamless-user-dashboard-col-items" data-label="<?php _e('Items', 'seamless-addon'); ?>">
                                <span class="seamless-user-dashboard-count-badge"><?php echo esc_html($count_items); ?></span>
                            </td>
                            <td class="seamless-user-dashboard-col-products seamless-user-dashboard-ellipsis" data-label="<?php _e('Products', 'seamless-addon'); ?>" title="<?php echo esc_attr($product_list !== '' ? $product_list : '—'); ?>">
                                <?php echo $product_list !== '' ? $product_list : '—'; ?>
                            </td>
                            <td class="seamless-user-dashboard-col-status" data-label="<?php _e('Status', 'seamless-addon'); ?>">
                                <span class="seamless-user-dashboard-chip seamless-user-dashboard-chip-<?php echo esc_attr($st_class); ?>">
                                    <?php echo esc_html(ucfirst((string)$status)); ?>
                                </span>
                            </td>
                            <td class="seamless-user-dashboard-col-total" data-label="<?php _e('Total', 'seamless-addon'); ?>">
                                <?php echo $total_fmt; ?>
                            </td>
                            <td class="seamless-user-dashboard-col-date" data-label="<?php _e('Date', 'seamless-addon'); ?>">
                                <span class="seamless-user-dashboard-muted"><?php echo esc_html((string)$date); ?></span>
                            </td>
                            <td class="seamless-user-dashboard-col-action" data-label="<?php _e('Action', 'seamless-addon'); ?>">
                                <?php
                                // Don't show invoice for refunded orders
                                $show_invoice = $invoice_url && !in_array($status_key, ['refunded', 'canceled', 'cancelled']);
                                if ($show_invoice):
                                ?>
                                    <a class="seamless-user-dashboard-btn seamless-user-dashboard-btn-invoice" href="<?php echo esc_url($invoice_url); ?>" target="_blank" rel="noopener">
                                        <?php _e('Invoice', 'seamless-addon'); ?>
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="seamless-user-dashboard-pagination">
                <button class="seamless-user-dashboard-pagination-btn seamless-user-dashboard-pagination-prev" disabled>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <?php _e('Previous', 'seamless-addon'); ?>
                </button>
                <span class="seamless-user-dashboard-pagination-info">
                    <?php _e('Page', 'seamless-addon'); ?> <span class="seamless-user-dashboard-current-page">1</span> <?php _e('of', 'seamless-addon'); ?> <span class="seamless-user-dashboard-total-pages"><?php echo esc_html($total_pages); ?></span>
                </span>
                <button class="seamless-user-dashboard-pagination-btn seamless-user-dashboard-pagination-next">
                    <?php _e('Next', 'seamless-addon'); ?>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <p class="seamless-user-dashboard-empty"><?php _e('No orders found. Try Refreshing the page.', 'seamless-addon'); ?></p>
<?php endif; ?>