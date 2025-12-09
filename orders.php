<?php
require_once __DIR__ . '/config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Only customers can access their orders - admins should use admin panel
if (!isCustomer()) {
    if (isAdmin()) {
        redirect('admin/orders_manage.php');
    } else {
        redirect('index.php');
    }
}

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get user orders
$orders_query = "SELECT * FROM orders WHERE user_id = $user_id ORDER BY created_at DESC";
$orders_result = $conn->query($orders_query);

$pageTitle = 'My Orders';
include __DIR__ . '/includes/header.php';
?>

<h2>My Orders</h2>

<?php if ($orders_result && $orders_result->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Order Number</th>
                    <th>Date</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Payment Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($order = $orders_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                        <td>LKR <?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <?php
                            $status_class = [
                                'pending' => 'warning',
                                'processing' => 'info',
                                'shipped' => 'primary',
                                'delivered' => 'success',
                                'cancelled' => 'danger',
                                'returned' => 'secondary'
                            ];
                            $class = $status_class[$order['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $class; ?>"><?php echo ucfirst($order['status']); ?></span>
                        </td>
                        <td>
                            <?php
                            $payment_class = [
                                'pending' => 'warning',
                                'paid' => 'success',
                                'failed' => 'danger',
                                'refunded' => 'secondary'
                            ];
                            $pclass = $payment_class[$order['payment_status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $pclass; ?>"><?php echo ucfirst($order['payment_status']); ?></span>
                        </td>
                        <td>
                            <a href="<?php echo getUrlPath('order_detail.php?id=' . $order['id']); ?>" class="btn btn-sm btn-primary">View Details</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <h5>No orders found!</h5>
        <p>You haven't placed any orders yet.</p>
        <a href="<?php echo getUrlPath('products.php'); ?>" class="btn btn-primary">Start Shopping</a>
    </div>
<?php endif; ?>

<?php
$conn->close();
include __DIR__ . '/includes/footer.php';
?>

