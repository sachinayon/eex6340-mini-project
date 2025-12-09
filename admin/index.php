<?php
require_once __DIR__ . '/../config/config.php';

if (!isAdmin()) {
    if (isLoggedIn()) {
        // If logged in as customer, redirect to homepage
        redirect('../index.php');
    } else {
        // If not logged in, redirect to login
        redirect('../login.php');
    }
}

$conn = getDBConnection();

// Get statistics
$stats = [];

// Total products
$result = $conn->query("SELECT COUNT(*) as count FROM products");
$stats['products'] = $result->fetch_assoc()['count'];

// Total orders
$result = $conn->query("SELECT COUNT(*) as count FROM orders");
$stats['orders'] = $result->fetch_assoc()['count'];

// Total users
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
$stats['users'] = $result->fetch_assoc()['count'];

// Total revenue
$result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid'");
$revenue = $result->fetch_assoc()['total'];
$stats['revenue'] = $revenue ? number_format($revenue, 2) : '0.00';

// Recent orders
$recent_orders_query = "SELECT o.*, u.username, u.full_name 
                        FROM orders o 
                        JOIN users u ON o.user_id = u.id 
                        ORDER BY o.created_at DESC 
                        LIMIT 5";
$recent_orders = $conn->query($recent_orders_query);

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<h2>Admin Dashboard</h2>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5>Total Products</h5>
                <h2><?php echo $stats['products']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5>Total Orders</h5>
                <h2><?php echo $stats['orders']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5>Total Customers</h5>
                <h2><?php echo $stats['users']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h5>Total Revenue</h5>
                <h2>LKR <?php echo $stats['revenue']; ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5>Recent Orders</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
                                <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($order['full_name']); ?></td>
                                        <td>LKR <?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo ucfirst($order['status']); ?></span>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                                        <td>
                                            <a href="<?php echo getUrlPath('admin/order_detail.php?id=' . $order['id']); ?>" class="btn btn-sm btn-primary">Manage</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No orders found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Close connection only if it's still open
if (isset($conn) && $conn instanceof mysqli) {
    try {
        $conn->close();
    } catch (Exception $e) {
        // Connection already closed, ignore error
    }
}
include __DIR__ . '/../includes/footer.php';
?>

