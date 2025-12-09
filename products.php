<?php
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Products';
include __DIR__ . '/includes/header.php';

$conn = getDBConnection();

// Get categories for filter
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);

// Get filter parameters
$category_filter = $_GET['category'] ?? '';
$search_query = sanitize($_GET['search'] ?? '');

// Build products query
$products_query = "SELECT p.*, c.name as category_name 
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   WHERE p.status = 'active'";

if ($category_filter) {
    $products_query .= " AND p.category_id = " . intval($category_filter);
}

if ($search_query) {
    $products_query .= " AND (p.name LIKE '%$search_query%' OR p.description LIKE '%$search_query%')";
}

$products_query .= " ORDER BY p.created_at DESC";
$products_result = $conn->query($products_query);
?>

<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header">
                <h5>Filter Products</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="mb-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Search products...">
                    </div>
                    <div class="mb-3">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <?php if ($categories_result): ?>
                                <?php while ($category = $categories_result->fetch_assoc()): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo ($category_filter == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                    <a href="<?php echo getUrlPath('products.php'); ?>" class="btn btn-secondary w-100 mt-2">Clear</a>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <h2>All Products</h2>
        <div class="row">
            <?php if ($products_result && $products_result->num_rows > 0): ?>
                <?php while ($product = $products_result->fetch_assoc()): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 style="height: 200px; object-fit: cover;">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="card-text text-muted small"><?php echo htmlspecialchars($product['category_name']); ?></p>
                                <p class="card-text"><?php echo substr($product['description'], 0, 80); ?>...</p>
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
                    <div class="alert alert-info">No products found matching your criteria.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$conn->close();
include __DIR__ . '/includes/footer.php';
?>

