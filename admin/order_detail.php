<?php
require_once __DIR__ . '/../config/config.php';

if (!isAdmin()) {
    if (isLoggedIn()) {
        redirect('../index.php');
    } else {
        redirect('../login.php');
    }
}

$order_id = intval($_GET['id'] ?? 0);

if (!$order_id) {
    redirect('orders_manage.php');
}

$conn = getDBConnection();

// Get order details
$order_query = "SELECT o.*, u.username, u.full_name, u.email, u.phone 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.id = $order_id";
$order_result = $conn->query($order_query);

if (!$order_result || $order_result->num_rows === 0) {
    $conn->close();
    redirect('orders_manage.php');
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

// Get delivery personnel for assignment
$delivery_personnel_query = "SELECT id, full_name, phone FROM users WHERE role = 'delivery'";
$delivery_personnel_result = $conn->query($delivery_personnel_query);

$message = '';
$message_type = '';

// Handle delivery assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_delivery'])) {
    $delivery_person_id = intval($_POST['delivery_person_id']);
    $tracking_number = sanitize($_POST['tracking_number'] ?? '');
    $estimated_date = sanitize($_POST['estimated_delivery_date'] ?? '');
    
    if ($delivery) {
        // Update existing delivery
        $update_stmt = $conn->prepare("UPDATE deliveries SET delivery_person_id = ?, tracking_number = ?, estimated_delivery_date = ?, status = 'assigned' WHERE order_id = ?");
        $update_stmt->bind_param("issi", $delivery_person_id, $tracking_number, $estimated_date, $order_id);
        if ($update_stmt->execute()) {
            $message = 'Delivery assigned successfully!';
            $message_type = 'success';
            // Refresh delivery data
            $delivery_result = $conn->query($delivery_query);
            $delivery = $delivery_result->fetch_assoc();
        }
        $update_stmt->close();
    } else {
        // Create new delivery
        $insert_stmt = $conn->prepare("INSERT INTO deliveries (order_id, delivery_person_id, tracking_number, estimated_delivery_date, status) VALUES (?, ?, ?, ?, 'assigned')");
        $insert_stmt->bind_param("iiss", $order_id, $delivery_person_id, $tracking_number, $estimated_date);
        if ($insert_stmt->execute()) {
            $message = 'Delivery assigned successfully!';
            $message_type = 'success';
            // Refresh delivery data
            $delivery_result = $conn->query($delivery_query);
            $delivery = $delivery_result->fetch_assoc();
        }
        $insert_stmt->close();
    }
}

$pageTitle = 'Order Details';
include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Order Details - <?php echo htmlspecialchars($order['order_number']); ?></h3>
            </div>
            <div class="card-body">
                <h5>Customer Information</h5>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($order['full_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></p>
                
                <hr>
                
                <h5>Order Information</h5>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Order Date:</strong><br>
                        <?php echo date('Y-m-d H:i:s', strtotime($order['created_at'])); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Order Status:</strong><br>
                        <span class="badge bg-info"><?php echo ucfirst($order['status']); ?></span>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Payment Method:</strong><br>
                        <?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Payment Status:</strong><br>
                        <span class="badge bg-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($order['payment_status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <strong>Shipping Address:</strong><br>
                    <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                </div>
                
                <hr>
                <h5>Delivery Information</h5>
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($delivery): ?>
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
                                    <p><strong>Delivery Person:</strong> <?php echo htmlspecialchars($delivery['delivery_person_name'] ?? 'Not Assigned'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Estimated Delivery:</strong> <?php echo $delivery['estimated_delivery_date'] ? date('Y-m-d', strtotime($delivery['estimated_delivery_date'])) : 'N/A'; ?></p>
                                    <?php if ($delivery['actual_delivery_date']): ?>
                                        <p><strong>Actual Delivery:</strong> <?php echo date('Y-m-d H:i', strtotime($delivery['actual_delivery_date'])); ?></p>
                                    <?php endif; ?>
                                    <?php if ($delivery['delivery_notes']): ?>
                                        <p><strong>Notes:</strong> <?php echo htmlspecialchars($delivery['delivery_notes']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($delivery['failure_reason']): ?>
                                        <p><strong>Failure Reason:</strong> <span class="text-danger"><?php echo htmlspecialchars($delivery['failure_reason']); ?></span></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>No delivery assigned yet. Assign a delivery person below.</p>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><?php echo $delivery ? 'Update' : 'Assign'; ?> Delivery</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Delivery Person</label>
                                    <select name="delivery_person_id" class="form-select" required>
                                        <option value="">Select Delivery Person</option>
                                        <?php while ($dp = $delivery_personnel_result->fetch_assoc()): ?>
                                            <option value="<?php echo $dp['id']; ?>" <?php echo ($delivery && $delivery['delivery_person_id'] == $dp['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dp['full_name']); ?> (<?php echo htmlspecialchars($dp['phone']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tracking Number</label>
                                    <input type="text" name="tracking_number" class="form-control" 
                                           value="<?php echo htmlspecialchars($delivery['tracking_number'] ?? ''); ?>" 
                                           placeholder="Enter tracking number">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Estimated Delivery Date</label>
                                <input type="date" name="estimated_delivery_date" class="form-control" 
                                       value="<?php echo $delivery['estimated_delivery_date'] ? date('Y-m-d', strtotime($delivery['estimated_delivery_date'])) : ''; ?>" required>
                            </div>
                            <button type="submit" name="assign_delivery" class="btn btn-primary"><?php echo $delivery ? 'Update' : 'Assign'; ?> Delivery</button>
                        </form>
                    </div>
                </div>
                
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
                    <a href="<?php echo getUrlPath('admin/orders_manage.php'); ?>" class="btn btn-outline-secondary">Back to Orders</a>
                    <a href="<?php echo getUrlPath('admin/deliveries_manage.php'); ?>" class="btn btn-outline-primary">Manage Deliveries</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
include __DIR__ . '/../includes/footer.php';
?>

