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

// Handle product deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $delete_stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $delete_stmt->bind_param("i", $delete_id);
    if ($delete_stmt->execute()) {
        $message = 'Product deleted successfully!';
        $message_type = 'success';
    } else {
        $message = 'Failed to delete product.';
        $message_type = 'danger';
    }
    $delete_stmt->close();
}

// Get all products
$products_query = "SELECT p.*, c.name as category_name 
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   ORDER BY p.created_at DESC";
$products_result = $conn->query($products_query);

$pageTitle = 'Manage Products';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Products</h2>
    <a href="<?php echo getUrlPath('admin/product_add.php'); ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Add New Product
    </a>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($products_result && $products_result->num_rows > 0): ?>
                <?php while ($product = $products_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $product['id']; ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                        <td>LKR <?php echo number_format($product['price'], 2); ?></td>
                        <td><?php echo $product['stock_quantity']; ?></td>
                        <td>
                            <span class="badge bg-<?php echo $product['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($product['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo getUrlPath('admin/product_edit.php?id=' . $product['id']); ?>" class="btn btn-sm btn-warning">Edit</a>
                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                <input type="hidden" name="delete_id" value="<?php echo $product['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">No products found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$conn->close();
include __DIR__ . '/../includes/footer.php';
?>

