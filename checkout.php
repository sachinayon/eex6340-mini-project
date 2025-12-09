<?php
require_once __DIR__ . '/config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Only customers can access checkout - admins should use admin panel
if (!isCustomer()) {
    if (isAdmin()) {
        redirect('admin/index.php');
    } else {
        redirect('index.php');
    }
}

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get user data
$user_query = "SELECT * FROM users WHERE id = $user_id";
$user_result = $conn->query($user_query);
$user = $user_result->fetch_assoc();

// Get cart items
$cart_query = "SELECT c.id, c.quantity, p.id as product_id, p.name, p.price, p.stock_quantity
               FROM cart c
               JOIN products p ON c.product_id = p.id
               WHERE c.user_id = $user_id";
$cart_result = $conn->query($cart_query);

if (!$cart_result || $cart_result->num_rows === 0) {
    $conn->close();
    redirect('cart.php');
}

$cart_items = [];
$total = 0;
while ($item = $cart_result->fetch_assoc()) {
    $subtotal = $item['price'] * $item['quantity'];
    $total += $subtotal;
    $cart_items[] = $item;
}

$error = '';
$success = '';

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_address = sanitize($_POST['shipping_address'] ?? '');
    $payment_method = sanitize($_POST['payment_method'] ?? '');
    
    if (empty($shipping_address)) {
        $error = 'Shipping address is required.';
    } else {
        // Generate unique order number
        $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(uniqid());
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Check stock availability
            foreach ($cart_items as $item) {
                $check_stock = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                $check_stock->bind_param("i", $item['product_id']);
                $check_stock->execute();
                $stock_result = $check_stock->get_result();
                $stock = $stock_result->fetch_assoc();
                
                if ($stock['stock_quantity'] < $item['quantity']) {
                    throw new Exception("Insufficient stock for {$item['name']}");
                }
            }
            
            // Create order
            $order_stmt = $conn->prepare("INSERT INTO orders (user_id, order_number, total_amount, shipping_address, payment_method) VALUES (?, ?, ?, ?, ?)");
            $order_stmt->bind_param("isdss", $user_id, $order_number, $total, $shipping_address, $payment_method);
            $order_stmt->execute();
            $order_id = $conn->insert_id;
            $order_stmt->close();
            
            // Create order items and update stock
            foreach ($cart_items as $item) {
                $subtotal = $item['price'] * $item['quantity'];
                
                $order_item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
                $order_item_stmt->bind_param("iiidd", $order_id, $item['product_id'], $item['quantity'], $item['price'], $subtotal);
                $order_item_stmt->execute();
                $order_item_stmt->close();
                
                // Update stock
                $update_stock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                $update_stock->bind_param("ii", $item['quantity'], $item['product_id']);
                $update_stock->execute();
                $update_stock->close();
            }
            
            // Clear cart
            $clear_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $clear_cart->bind_param("i", $user_id);
            $clear_cart->execute();
            $clear_cart->close();
            
            // Create delivery record with pending status
            $delivery_stmt = $conn->prepare("INSERT INTO deliveries (order_id, status) VALUES (?, 'pending')");
            $delivery_stmt->bind_param("i", $order_id);
            $delivery_stmt->execute();
            $delivery_stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $success = "Order placed successfully! Order Number: $order_number";
            redirect("order_detail.php?id=$order_id");
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$pageTitle = 'Checkout';
include __DIR__ . '/includes/header.php';
?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Checkout</h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <h5>Shipping Information</h5>
                    <div class="mb-3">
                        <label for="shipping_address" class="form-label">Shipping Address</label>
                        <textarea class="form-control" id="shipping_address" name="shipping_address" rows="4" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <h5 class="mt-4">Payment Method</h5>
                    <div class="mb-3">
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="">Select Payment Method</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="PayPal">PayPal</option>
                            <option value="Cash on Delivery">Cash on Delivery</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg">Place Order</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>Order Summary</h5>
            </div>
            <div class="card-body">
                <?php foreach ($cart_items as $item): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?php echo htmlspecialchars($item['name']); ?> x <?php echo $item['quantity']; ?></span>
                        <span>LKR <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
                <hr>
                <div class="d-flex justify-content-between">
                    <strong>Total:</strong>
                    <strong>LKR <?php echo number_format($total, 2); ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
include __DIR__ . '/includes/footer.php';
?>

