<?php
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Home';
include __DIR__ . '/includes/header.php';

$conn = getDBConnection();

// Get featured products
$products_query = "SELECT p.*, c.name as category_name 
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   WHERE p.status = 'active' 
                   ORDER BY p.created_at DESC 
                   LIMIT 6";
$products_result = $conn->query($products_query);
?>

<div class="jumbotron bg-primary text-white p-5 rounded mb-4">
    <h1 class="display-4">Welcome to <?php echo SITE_NAME; ?></h1>
    <p class="lead">Your one-stop shop for all your needs. Browse our wide selection of products.</p>
    <a class="btn btn-light btn-lg" href="<?php echo getUrlPath('products.php'); ?>" role="button">Shop Now</a>
</div>

<h2 class="mb-4">Featured Products</h2>
<div class="row">
    <?php if ($products_result && $products_result->num_rows > 0): ?>
        <?php while ($product = $products_result->fetch_assoc()): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <p class="card-text text-muted"><?php echo htmlspecialchars($product['category_name']); ?></p>
                        <p class="card-text"><?php echo substr($product['description'], 0, 100); ?>...</p>
                        <div class="mt-auto">
                            <p class="card-text">
                                <strong class="text-primary">$<?php echo number_format($product['price'], 2); ?></strong>
                                <?php if ($product['stock_quantity'] > 0): ?>
                                    <span class="badge bg-success ms-2">In Stock</span>
                                <?php else: ?>
                                    <span class="badge bg-danger ms-2">Out of Stock</span>
                                <?php endif; ?>
                            </p>
                            <a href="<?php echo getUrlPath('product_detail.php?id=' . $product['id']); ?>" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12">
            <p class="text-muted">No products available at the moment.</p>
        </div>
    <?php endif; ?>
</div>

<?php
$conn->close();
include __DIR__ . '/includes/footer.php';
?>

