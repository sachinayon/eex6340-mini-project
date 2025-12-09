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

// Handle delivery status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_delivery'])) {
        $order_id = intval($_POST['order_id']);
        $delivery_person_id = intval($_POST['delivery_person_id']);
        $tracking_number = sanitize($_POST['tracking_number'] ?? '');
        $estimated_date = sanitize($_POST['estimated_delivery_date'] ?? '');
        
        // Check if delivery already exists
        $check_delivery = $conn->prepare("SELECT id FROM deliveries WHERE order_id = ?");
        $check_delivery->bind_param("i", $order_id);
        $check_delivery->execute();
        $delivery_exists = $check_delivery->get_result();
        
        if ($delivery_exists->num_rows > 0) {
            // Update existing delivery
            $update_stmt = $conn->prepare("UPDATE deliveries SET delivery_person_id = ?, tracking_number = ?, estimated_delivery_date = ?, status = 'assigned', updated_at = NOW() WHERE order_id = ?");
            $update_stmt->bind_param("issi", $delivery_person_id, $tracking_number, $estimated_date, $order_id);
            if ($update_stmt->execute()) {
                $message = 'Delivery assigned successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to assign delivery.';
                $message_type = 'danger';
            }
            $update_stmt->close();
        } else {
            // Create new delivery
            $insert_stmt = $conn->prepare("INSERT INTO deliveries (order_id, delivery_person_id, tracking_number, estimated_delivery_date, status) VALUES (?, ?, ?, ?, 'assigned')");
            $insert_stmt->bind_param("iiss", $order_id, $delivery_person_id, $tracking_number, $estimated_date);
            if ($insert_stmt->execute()) {
                $message = 'Delivery assigned successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to assign delivery.';
                $message_type = 'danger';
            }
            $insert_stmt->close();
        }
        $check_delivery->close();
    } elseif (isset($_POST['update_delivery_status'])) {
        $delivery_id = intval($_POST['delivery_id']);
        $new_status = sanitize($_POST['delivery_status']);
        $notes = sanitize($_POST['delivery_notes'] ?? '');
        $failure_reason = sanitize($_POST['failure_reason'] ?? '');
        
        $update_stmt = $conn->prepare("UPDATE deliveries SET status = ?, delivery_notes = ?, failure_reason = ? WHERE id = ?");
        $update_stmt->bind_param("sssi", $new_status, $notes, $failure_reason, $delivery_id);
        
        if ($update_stmt->execute()) {
            // Update order status if delivered
            if ($new_status === 'delivered') {
                $delivery = $conn->query("SELECT order_id FROM deliveries WHERE id = $delivery_id")->fetch_assoc();
                $conn->query("UPDATE orders SET status = 'delivered' WHERE id = " . $delivery['order_id']);
                $conn->query("UPDATE deliveries SET actual_delivery_date = NOW() WHERE id = $delivery_id");
            }
            $message = 'Delivery status updated successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to update delivery status.';
            $message_type = 'danger';
        }
        $update_stmt->close();
    }
}

// Get all deliveries with order and delivery person info
$deliveries_query = "SELECT d.*, o.order_number, o.total_amount, o.shipping_address, 
                            u.full_name as customer_name, u.phone as customer_phone,
                            dp.full_name as delivery_person_name, dp.phone as delivery_person_phone
                     FROM deliveries d
                     JOIN orders o ON d.order_id = o.id
                     JOIN users u ON o.user_id = u.id
                     LEFT JOIN users dp ON d.delivery_person_id = dp.id
                     ORDER BY d.created_at DESC";
$deliveries_result = $conn->query($deliveries_query);

// Get delivery personnel
$delivery_personnel_query = "SELECT id, full_name, phone FROM users WHERE role = 'delivery'";
$delivery_personnel_result = $conn->query($delivery_personnel_query);

$pageTitle = 'Manage Deliveries';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Deliveries</h2>
    <a href="<?php echo getUrlPath('admin/orders_manage.php'); ?>" class="btn btn-outline-primary">View Orders</a>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead>
            <tr>
                <th>Order Number</th>
                <th>Customer</th>
                <th>Delivery Person</th>
                <th>Tracking Number</th>
                <th>Status</th>
                <th>Estimated Delivery</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($deliveries_result && $deliveries_result->num_rows > 0): ?>
                <?php while ($delivery = $deliveries_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($delivery['order_number']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($delivery['customer_name']); ?><br>
                            <small class="text-muted"><?php echo htmlspecialchars($delivery['customer_phone'] ?? 'N/A'); ?></small>
                        </td>
                        <td>
                            <?php if ($delivery['delivery_person_name']): ?>
                                <?php echo htmlspecialchars($delivery['delivery_person_name']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($delivery['delivery_person_phone'] ?? 'N/A'); ?></small>
                            <?php else: ?>
                                <span class="text-muted">Not Assigned</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($delivery['tracking_number'] ?? 'N/A'); ?></td>
                        <td>
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
                        </td>
                        <td>
                            <?php echo $delivery['estimated_delivery_date'] ? date('Y-m-d', strtotime($delivery['estimated_delivery_date'])) : 'N/A'; ?>
                            <?php if ($delivery['actual_delivery_date']): ?>
                                <br><small class="text-success">Delivered: <?php echo date('Y-m-d H:i', strtotime($delivery['actual_delivery_date'])); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#deliveryModal<?php echo $delivery['id']; ?>">
                                Manage
                            </button>
                        </td>
                    </tr>
                    
                    <!-- Delivery Management Modal -->
                    <div class="modal fade" id="deliveryModal<?php echo $delivery['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Manage Delivery - <?php echo htmlspecialchars($delivery['order_number']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST" action="">
                                    <div class="modal-body">
                                        <input type="hidden" name="delivery_id" value="<?php echo $delivery['id']; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Delivery Status</label>
                                            <select name="delivery_status" class="form-select" required>
                                                <option value="pending" <?php echo ($delivery['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="assigned" <?php echo ($delivery['status'] === 'assigned') ? 'selected' : ''; ?>>Assigned</option>
                                                <option value="in_transit" <?php echo ($delivery['status'] === 'in_transit') ? 'selected' : ''; ?>>In Transit</option>
                                                <option value="out_for_delivery" <?php echo ($delivery['status'] === 'out_for_delivery') ? 'selected' : ''; ?>>Out for Delivery</option>
                                                <option value="delivered" <?php echo ($delivery['status'] === 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                                <option value="failed" <?php echo ($delivery['status'] === 'failed') ? 'selected' : ''; ?>>Failed</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Delivery Person</label>
                                            <select name="delivery_person_id" class="form-select">
                                                <option value="">Select Delivery Person</option>
                                                <?php 
                                                $dp_result = $conn->query($delivery_personnel_query);
                                                while ($dp = $dp_result->fetch_assoc()): 
                                                ?>
                                                    <option value="<?php echo $dp['id']; ?>" <?php echo ($delivery['delivery_person_id'] == $dp['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($dp['full_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Delivery Notes</label>
                                            <textarea name="delivery_notes" class="form-control" rows="3"><?php echo htmlspecialchars($delivery['delivery_notes'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Failure Reason (if failed)</label>
                                            <textarea name="failure_reason" class="form-control" rows="2"><?php echo htmlspecialchars($delivery['failure_reason'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Shipping Address</label>
                                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($delivery['shipping_address'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" name="update_delivery_status" class="btn btn-primary">Update Status</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">No deliveries found. Assign deliveries from order details.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$conn->close();
include __DIR__ . '/../includes/footer.php';
?>

