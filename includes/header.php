<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .navbar-brand {
            font-weight: bold;
        }
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cart-icon {
            position: relative;
        }
        footer {
            margin-top: 50px;
            padding: 20px 0;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo getUrlPath('index.php'); ?>">
                <i class="bi bi-shop"></i> <?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if (isAdmin()): ?>
                    <!-- Admin Navigation - Only Back Office Tasks -->
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getUrlPath('admin/index.php'); ?>">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getUrlPath('admin/products_manage.php'); ?>">Products</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getUrlPath('admin/orders_manage.php'); ?>">Orders</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getUrlPath('admin/deliveries_manage.php'); ?>">Deliveries</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getUrlPath('admin/categories_manage.php'); ?>">Categories</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getUrlPath('admin/users_manage.php'); ?>">Users</a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?php echo getUrlPath('logout.php'); ?>">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                <?php elseif (isLoggedIn() && isCustomer()): ?>
                    <!-- Customer Navigation - E-commerce Features -->
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getUrlPath('index.php'); ?>">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getUrlPath('products.php'); ?>">Products</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getUrlPath('orders.php'); ?>">My Orders</a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link cart-icon" href="<?php echo getUrlPath('cart.php'); ?>">
                                <i class="bi bi-cart3"></i> Cart
                                <?php
                                // Use existing connection if available, otherwise create new one
                                $header_conn = null;
                                $close_header_conn = false;
                                
                                if (isset($conn) && $conn instanceof mysqli) {
                                    $header_conn = $conn;
                                } else {
                                    $header_conn = getDBConnection();
                                    $close_header_conn = true;
                                }
                                
                                $user_id = getCurrentUserId();
                                $cart_count = 0;
                                if ($user_id && $header_conn) {
                                    $result = $header_conn->query("SELECT SUM(quantity) as total FROM cart WHERE user_id = $user_id");
                                    if ($result && $row = $result->fetch_assoc()) {
                                        $cart_count = $row['total'] ?? 0;
                                    }
                                }
                                
                                // Only close if we created a new connection
                                if ($close_header_conn && $header_conn) {
                                    $header_conn->close();
                                }
                                
                                if ($cart_count > 0):
                                ?>
                                <span class="cart-badge"><?php echo $cart_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?php echo getUrlPath('profile.php'); ?>">Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo getUrlPath('logout.php'); ?>">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                <?php else: ?>
                    <!-- Guest Navigation - Normal E-commerce -->
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getUrlPath('index.php'); ?>">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getUrlPath('products.php'); ?>">Products</a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getUrlPath('login.php'); ?>">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getUrlPath('register.php'); ?>">Register</a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <div class="container mt-4">

