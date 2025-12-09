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

$category_id = intval($_POST['category_id'] ?? 0);
$name = sanitize($_POST['name'] ?? '');
$description = sanitize($_POST['description'] ?? '');

if (empty($name)) {
    $_SESSION['error'] = 'Category name is required.';
    redirect('categories_manage.php');
}

if ($category_id > 0) {
    // Update existing category
    $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $description, $category_id);
} else {
    // Insert new category
    $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $description);
}

if ($stmt->execute()) {
    $_SESSION['success'] = $category_id > 0 ? 'Category updated successfully!' : 'Category added successfully!';
} else {
    $_SESSION['error'] = 'Failed to save category.';
}

$stmt->close();
$conn->close();

redirect('categories_manage.php');
?>

