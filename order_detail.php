<?php
require_once __DIR__ . '/config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Only customers can access their order details - admins should use admin panel
if (!isCustomer()) {
    if (isAdmin()) {
        redirect('admin/orders_manage.php');
    } else {
        redirect('index.php');
    }
}

$order_id = intval($_GET['id'] ?? 0);

if (!$order_id) {
    redirect('orders.php');
}

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get order details
$order_query = "SELECT * FROM orders WHERE id = $order_id AND user_id = $user_id";
$order_result = $conn->query($order_query);

if (!$order_result || $order_result->num_rows === 0) {
    $conn->close();
    redirect('orders.php');
}

$order = $order_result->fetch_assoc();

// Get order items
$items_query = "SELECT oi.*, p.name as product_name, p.image_url
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = $order_id";
$items_result = $conn->query($items_query);

// Get delivery information
$delivery_query = "SELECT d.*, dp.full_name as delivery_person_name, dp.phone as delivery_person_phone
                   FROM deliveries d
                   LEFT JOIN users dp ON d.delivery_person_id = dp.id
                   WHERE d.order_id = $order_id";
$delivery_result = $conn->query($delivery_query);
$delivery = $delivery_result->num_rows > 0 ? $delivery_result->fetch_assoc() : null;

$pageTitle = 'Order Details';
include __DIR__ . '/includes/header.php';
?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Order Details - <?php echo htmlspecialchars($order['order_number']); ?></h3>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Order Date:</strong><br>
                        <?php echo date('Y-m-d H:i:s', strtotime($order['created_at'])); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Order Status:</strong><br>
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
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Payment Method:</strong><br>
                        <?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Payment Status:</strong><br>
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
                    </div>
                </div>
                
                <div class="mb-3">
                    <strong>Shipping Address:</strong><br>
                    <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                </div>
                
                <?php if ($delivery): ?>
                    <hr>
                    <h5>Delivery Tracking</h5>
                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Status:</strong> 
                                        <?php
                                        $status_colors = [
                                            'pending' => 'secondary',
                                            'assigned' => 'info',
                                            'in_transit' => 'primary',
                                            'out_for_delivery' => 'warning',
                                            'delivered' => 'success',
                                            'failed' => 'danger'
                                        ];
                                        $color = $status_colors[$delivery['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?></span>
                                    </p>
                                    <p><strong>Tracking Number:</strong> <?php echo htmlspecialchars($delivery['tracking_number'] ?? 'N/A'); ?></p>
                                    <?php if ($delivery['delivery_person_name']): ?>
                                        <p><strong>Delivery Person:</strong> <?php echo htmlspecialchars($delivery['delivery_person_name']); ?></p>
                                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($delivery['delivery_person_phone'] ?? 'N/A'); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Estimated Delivery:</strong> <?php echo $delivery['estimated_delivery_date'] ? date('Y-m-d', strtotime($delivery['estimated_delivery_date'])) : 'N/A'; ?></p>
                                    <?php if ($delivery['actual_delivery_date']): ?>
                                        <p><strong>Delivered On:</strong> <?php echo date('Y-m-d H:i', strtotime($delivery['actual_delivery_date'])); ?></p>
                                    <?php endif; ?>
                                    <?php if ($delivery['delivery_notes']): ?>
                                        <p><strong>Notes:</strong> <?php echo htmlspecialchars($delivery['delivery_notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <hr>
                <h5>Order Items</h5>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $items_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                 style="width: 60px; height: 60px; object-fit: cover; margin-right: 10px;">
                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                                    <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total:</th>
                                <th>$<?php echo number_format($order['total_amount'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="mt-3">
                    <a href="<?php echo getUrlPath('orders.php'); ?>" class="btn btn-outline-secondary">Back to Orders</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
include __DIR__ . '/includes/footer.php';
?>

