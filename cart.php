<?php
require_once __DIR__ . '/config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Only customers can access cart - admins should use admin panel
if (!isCustomer()) {
    if (isAdmin()) {
        redirect('admin/index.php');
    } else {
        redirect('index.php');
    }
}

$conn = getDBConnection();
$user_id = getCurrentUserId();

$message = '';
$message_type = '';

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update') {
        $cart_id = intval($_POST['cart_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if ($quantity > 0) {
            $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $update_stmt->bind_param("iii", $quantity, $cart_id, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            $message = 'Cart updated successfully!';
            $message_type = 'success';
        }
    } elseif ($action === 'remove') {
        $cart_id = intval($_POST['cart_id'] ?? 0);
        
        $delete_stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $delete_stmt->bind_param("ii", $cart_id, $user_id);
        $delete_stmt->execute();
        $delete_stmt->close();
        $message = 'Item removed from cart!';
        $message_type = 'success';
    }
}

// Get cart items
$cart_query = "SELECT c.id, c.quantity, p.id as product_id, p.name, p.price, p.image_url, p.stock_quantity
               FROM cart c
               JOIN products p ON c.product_id = p.id
               WHERE c.user_id = $user_id
               ORDER BY c.created_at DESC";
$cart_result = $conn->query($cart_query);

$total = 0;

$pageTitle = 'Shopping Cart';
include __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<h2>Shopping Cart</h2>

<?php if ($cart_result && $cart_result->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $cart_result->fetch_assoc()): ?>
                    <?php
                    $subtotal = $item['price'] * $item['quantity'];
                    $total += $subtotal;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                        </td>
                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                        <td>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                       min="1" max="<?php echo $item['stock_quantity']; ?>" 
                                       class="form-control form-control-sm d-inline-block" style="width: 80px;">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Update</button>
                            </form>
                        </td>
                        <td>$<?php echo number_format($subtotal, 2); ?></td>
                        <td>
                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to remove this item?');">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i> Remove
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-end">Total:</th>
                    <th colspan="2">$<?php echo number_format($total, 2); ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <div class="text-end mt-4">
        <a href="<?php echo getUrlPath('products.php'); ?>" class="btn btn-outline-secondary me-2">Continue Shopping</a>
        <a href="<?php echo getUrlPath('checkout.php'); ?>" class="btn btn-primary btn-lg">Proceed to Checkout</a>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <h5>Your cart is empty!</h5>
        <p>Start shopping to add items to your cart.</p>
        <a href="<?php echo getUrlPath('products.php'); ?>" class="btn btn-primary">Browse Products</a>
    </div>
<?php endif; ?>

<?php
$conn->close();
include __DIR__ . '/includes/footer.php';
?>

