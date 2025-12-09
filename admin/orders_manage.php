<?php
require_once __DIR__ . '/../config/config.php';

if (!isAdmin()) {
    if (isLoggedIn()) {
        redirect('../index.php');
    } else {
        redirect('../login.php');
    }
}

$conn = getDBConnection();

$message = '';
$message_type = '';

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = sanitize($_POST['status']);
    
    $update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_status, $order_id);
    
    if ($update_stmt->execute()) {
        $message = 'Order status updated successfully!';
        $message_type = 'success';
    } else {
        $message = 'Failed to update order status.';
        $message_type = 'danger';
    }
    
    $update_stmt->close();
}

// Get all orders with delivery status
// Check if deliveries table exists first
$deliveries_table_exists = false;
$check_table = $conn->query("SHOW TABLES LIKE 'deliveries'");
if ($check_table && $check_table->num_rows > 0) {
    $deliveries_table_exists = true;
}

if ($deliveries_table_exists) {
    $orders_query = "SELECT o.*, u.username, u.full_name, u.email,
                            d.status as delivery_status, d.tracking_number
                     FROM orders o 
                     JOIN users u ON o.user_id = u.id 
                     LEFT JOIN deliveries d ON o.id = d.order_id
                     ORDER BY o.created_at DESC";
} else {
    $orders_query = "SELECT o.*, u.username, u.full_name, u.email,
                            NULL as delivery_status, NULL as tracking_number
                     FROM orders o 
                     JOIN users u ON o.user_id = u.id 
                     ORDER BY o.created_at DESC";
}
$orders_result = $conn->query($orders_query);

$pageTitle = 'Manage Orders';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$deliveries_table_exists): ?>
    <div class="alert alert-warning alert-dismissible fade show">
        <strong>Notice:</strong> The deliveries table is missing. 
        <a href="../database/migrate.php" class="alert-link">Run database migration</a> to enable delivery management features.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<h2>Manage Orders</h2>

<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead>
            <tr>
                <th>Order Number</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Delivery Status</th>
                <th>Payment Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                <?php while ($order = $orders_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($order['full_name']); ?><br>
                            <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                        </td>
                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <select name="status" class="form-select form-select-sm d-inline-block" style="width: auto;" onchange="this.form.submit()">
                                    <option value="pending" <?php echo ($order['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo ($order['status'] === 'processing') ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo ($order['status'] === 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo ($order['status'] === 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="cancelled" <?php echo ($order['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="returned" <?php echo ($order['status'] === 'returned') ? 'selected' : ''; ?>>Returned</option>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                        </td>
                        <td>
                            <?php if ($order['delivery_status']): 
                                $delivery_colors = [
                                    'pending' => 'secondary',
                                    'assigned' => 'info',
                                    'in_transit' => 'primary',
                                    'out_for_delivery' => 'warning',
                                    'delivered' => 'success',
                                    'failed' => 'danger'
                                ];
                                $dcolor = $delivery_colors[$order['delivery_status']] ?? 'secondary';
                            ?>
                                <span class="badge bg-<?php echo $dcolor; ?>" title="Tracking: <?php echo htmlspecialchars($order['tracking_number'] ?? 'N/A'); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $order['delivery_status'])); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Not Assigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                        <td>
                            <a href="<?php echo getUrlPath('admin/order_detail.php?id=' . $order['id']); ?>" class="btn btn-sm btn-primary">View</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center">No orders found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$conn->close();
include __DIR__ . '/../includes/footer.php';
?>

