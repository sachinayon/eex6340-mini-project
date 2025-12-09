<?php
require_once __DIR__ . '/config/config.php';

$product_id = intval($_GET['id'] ?? 0);

if (!$product_id) {
    redirect('products.php');
}

$conn = getDBConnection();

$product_query = "SELECT p.*, c.name as category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  WHERE p.id = $product_id AND p.status = 'active'";
$product_result = $conn->query($product_query);

if (!$product_result || $product_result->num_rows === 0) {
    $conn->close();
    redirect('products.php');
}

$product = $product_result->fetch_assoc();
$pageTitle = $product['name'];
include __DIR__ . '/includes/header.php';

$added_to_cart = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && isCustomer()) {
    $quantity = intval($_POST['quantity'] ?? 1);
    $user_id = getCurrentUserId();
    
    // Check if item already in cart
    $check_cart = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $check_cart->bind_param("ii", $user_id, $product_id);
    $check_cart->execute();
    $cart_result = $check_cart->get_result();
    
    if ($cart_result->num_rows > 0) {
        // Update quantity
        $cart_item = $cart_result->fetch_assoc();
        $new_quantity = $cart_item['quantity'] + $quantity;
        $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $update_stmt->bind_param("ii", $new_quantity, $cart_item['id']);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        // Insert new cart item
        $insert_stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $insert_stmt->bind_param("iii", $user_id, $product_id, $quantity);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    
    $check_cart->close();
    $added_to_cart = true;
}
?>

<div class="row">
    <div class="col-md-12">
        <h2><?php echo htmlspecialchars($product['name']); ?></h2>
        <p class="text-muted">Category: <?php echo htmlspecialchars($product['category_name']); ?></p>
        <p class="lead">
            <strong class="text-primary">LKR <?php echo number_format($product['price'], 2); ?></strong>
        </p>
        
        <?php if ($product['stock_quantity'] > 0): ?>
            <span class="badge bg-success mb-3">In Stock (<?php echo $product['stock_quantity']; ?> available)</span>
        <?php else: ?>
            <span class="badge bg-danger mb-3">Out of Stock</span>
        <?php endif; ?>
        
        <hr>
        <h5>Description</h5>
        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
        
        <?php if ($added_to_cart): ?>
            <div class="alert alert-success">Product added to cart successfully!</div>
        <?php endif; ?>
        
        <?php if (isLoggedIn() && isCustomer()): ?>
            <?php if ($product['stock_quantity'] > 0): ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" 
                               value="1" min="1" max="<?php echo $product['stock_quantity']; ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-cart-plus"></i> Add to Cart
                    </button>
                </form>
            <?php else: ?>
                <button class="btn btn-secondary btn-lg" disabled>Out of Stock</button>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <a href="<?php echo getUrlPath('login.php'); ?>">Login</a> to add items to cart.
            </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <a href="<?php echo getUrlPath('products.php'); ?>" class="btn btn-outline-secondary">Back to Products</a>
        </div>
    </div>
</div>

<?php
$conn->close();
include __DIR__ . '/includes/footer.php';
?>

