<?php
/**
 * Database Migration Runner
 * Run this file once to add the deliveries table to your existing database
 * Access via: http://localhost/my_works/ousl/eex6340-mini-project/database/migrate.php
 */

require_once __DIR__ . '/../config/database.php';

$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    try {
        $conn = getDBConnection();
        
        // Start transaction
        $conn->begin_transaction();
        
        // Update users table to add delivery role
        $conn->query("ALTER TABLE users MODIFY role ENUM('customer', 'admin', 'delivery') DEFAULT 'customer'");
        
        // Create delivery management table
        $create_deliveries = "CREATE TABLE IF NOT EXISTS deliveries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            delivery_person_id INT,
            status ENUM('pending', 'assigned', 'in_transit', 'out_for_delivery', 'delivered', 'failed') DEFAULT 'pending',
            tracking_number VARCHAR(100),
            estimated_delivery_date DATE,
            actual_delivery_date DATETIME,
            delivery_notes TEXT,
            failure_reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (delivery_person_id) REFERENCES users(id) ON DELETE SET NULL
        )";
        
        if ($conn->query($create_deliveries)) {
            // Check if delivery personnel already exist
            $check_delivery1 = $conn->query("SELECT id FROM users WHERE username = 'delivery1'");
            $check_delivery2 = $conn->query("SELECT id FROM users WHERE username = 'delivery2'");
            
            // Insert sample delivery personnel if they don't exist
            if ($check_delivery1->num_rows == 0) {
                $conn->query("INSERT INTO users (username, email, password, full_name, phone, role) VALUES
                    ('delivery1', 'delivery1@ecommerce.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Delivery', '1234567890', 'delivery')");
            }
            
            if ($check_delivery2->num_rows == 0) {
                $conn->query("INSERT INTO users (username, email, password, full_name, phone, role) VALUES
                    ('delivery2', 'delivery2@ecommerce.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Courier', '0987654321', 'delivery')");
            }
            
            // Commit transaction
            $conn->commit();
            $success = true;
            $message = 'Migration completed successfully! The deliveries table has been created.';
        } else {
            throw new Exception("Failed to create deliveries table: " . $conn->error);
        }
        
        $conn->close();
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
            $conn->close();
        }
        $error = 'Migration failed: ' . $e->getMessage();
    }
}

// Check if deliveries table exists
$table_exists = false;
try {
    $conn = getDBConnection();
    $result = $conn->query("SHOW TABLES LIKE 'deliveries'");
    $table_exists = ($result && $result->num_rows > 0);
    $conn->close();
} catch (Exception $e) {
    $error = 'Error checking database: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3>Database Migration - Add Deliveries Table</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <strong>Success!</strong> <?php echo htmlspecialchars($message); ?>
                                <p class="mt-3">
                                    <a href="../admin/index.php" class="btn btn-primary">Go to Admin Panel</a>
                                    <a href="../index.php" class="btn btn-outline-secondary">Go to Homepage</a>
                                </p>
                            </div>
                        <?php elseif ($table_exists): ?>
                            <div class="alert alert-info">
                                <strong>Info:</strong> The deliveries table already exists in your database.
                                <p class="mt-3">
                                    <a href="../admin/index.php" class="btn btn-primary">Go to Admin Panel</a>
                                    <a href="../index.php" class="btn btn-outline-secondary">Go to Homepage</a>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <strong>Notice:</strong> The deliveries table does not exist. Click the button below to create it.
                            </div>
                            
                            <p>This migration will:</p>
                            <ul>
                                <li>Update the users table to support 'delivery' role</li>
                                <li>Create the deliveries table</li>
                                <li>Add sample delivery personnel accounts</li>
                            </ul>
                            
                            <form method="POST" action="">
                                <button type="submit" name="run_migration" class="btn btn-primary btn-lg">
                                    Run Migration
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

