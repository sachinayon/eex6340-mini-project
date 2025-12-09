<?php
/**
 * Chatbot API Endpoint
 * Handles chatbot queries and returns responses based on database data
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

// Start session to get user data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$message = isset($input['message']) ? trim($input['message']) : '';
$user_id = getCurrentUserId();
$user_role = getCurrentUserRole();

if (empty($message)) {
    echo json_encode([
        'success' => false,
        'message' => 'No message provided'
    ]);
    exit;
}

$conn = getDBConnection();
$response = processChatbotMessage($message, $user_id, $user_role, $conn);
$conn->close();

echo json_encode($response);

/**
 * Process chatbot message and return appropriate response
 */
function processChatbotMessage($message, $user_id, $user_role, $conn) {
    $message_lower = strtolower($message);
    
    // Intent detection using keyword matching
    $intent = detectIntent($message_lower);
    
    switch ($intent) {
        case 'order_status':
            return handleOrderStatus($message_lower, $user_id, $user_role, $conn);
        
        case 'order_history':
            return handleOrderHistory($user_id, $user_role, $conn);
        
        case 'product_search':
            return handleProductSearch($message_lower, $conn);
        
        case 'return_policy':
            return handleReturnPolicy();
        
        case 'shipping':
            return handleShipping($message_lower, $user_id, $user_role, $conn);
        
        case 'payment':
            return handlePayment();
        
        case 'greeting':
            return handleGreeting($user_role);
        
        case 'help':
            return handleHelp($user_role);
        
        case 'quantitative':
            return handleQuantitativeQuery($message_lower, $user_id, $user_role, $conn);
        
        default:
            return handleGeneralQuery($message_lower, $user_id, $user_role, $conn);
    }
}

/**
 * Detect user intent from message
 */
function detectIntent($message) {
    // Check for order number pattern FIRST (ORD-...)
    if (preg_match('/\b(ORD-[\w-]+)\b/i', $message)) {
        return 'order_status';
    }
    
    // Order status keywords
    if (preg_match('/\b(order|status|track|tracking|where is my order|order number|items of|items in)\b/', $message)) {
        return 'order_status';
    }
    
    // Order history keywords
    if (preg_match('/\b(my orders|order history|past orders|previous orders)\b/', $message)) {
        return 'order_history';
    }
    
    // Quantitative queries - Check EARLY to catch revenue, most ordered, date ranges, stock, etc.
    // Check for "most ordered", "revenue", "between", "stock", date ranges BEFORE product search
    if (preg_match('/\b(most ordered|top ordered|best selling|popular|frequently ordered|revenue|sales|income|between|from.*to|stock|available|availability)\b/i', $message)) {
        return 'quantitative';
    }
    
    if (preg_match('/\b(how many|how much|count|sum|total|average|avg|number of|quantity of|this month|last month|this year|last year)\b/', $message)) {
        return 'quantitative';
    }
    
    // Product search keywords (only if not quantitative and not order-related)
    if (preg_match('/\b(product|products|item|items|show|search|find|buy|purchase|price|cost)\b/', $message) && 
        !preg_match('/\b(ORD-|order|items of|items in)\b/i', $message)) {
        return 'product_search';
    }
    
    // Return policy keywords
    if (preg_match('/\b(return|refund|exchange|send back|return policy)\b/', $message)) {
        return 'return_policy';
    }
    
    // Shipping keywords
    if (preg_match('/\b(shipping|delivery|deliver|ship|when will|arrive|estimated)\b/', $message)) {
        return 'shipping';
    }
    
    // Payment keywords (only if not a quantitative query about orders)
    if (preg_match('/\b(payment|pay|payment method|credit card|debit)\b/', $message) && 
        !preg_match('/\b(total|count|how many|how much)\b.*\b(order|orders)\b/', $message)) {
        return 'payment';
    }
    
    // Greeting keywords
    if (preg_match('/\b(hi|hello|hey|greetings|good morning|good afternoon|good evening)\b/', $message)) {
        return 'greeting';
    }
    
    // Help keywords
    if (preg_match('/\b(help|support|assist|what can you do|how can you help)\b/', $message)) {
        return 'help';
    }
    
    return 'general';
}

/**
 * Handle order status queries
 */
function handleOrderStatus($message, $user_id, $user_role, $conn) {
    if (!$user_id) {
        return [
            'success' => true,
            'message' => 'Please login to check your order status. You can login from the top menu.',
            'type' => 'info'
        ];
    }
    
    // Check if asking for items
    $is_items_query = preg_match('/\b(items|item|products|product)\s+(of|in)\b/i', $message) || 
                      preg_match('/\b(items|item|products|product)\s+(ORD-|order)\b/i', $message);
    
    // Extract order number if mentioned
    preg_match('/\b(ORD-[\w-]+|\d+)\b/i', $message, $matches);
    $order_number = isset($matches[0]) ? $matches[0] : null;
    
    if ($order_number) {
        // Search by order number
        $query = "SELECT o.*, d.status as delivery_status, d.tracking_number, d.estimated_delivery_date
                  FROM orders o
                  LEFT JOIN deliveries d ON o.id = d.order_id
                  WHERE o.order_number = ?";
        
        if ($user_role === 'customer') {
            $query .= " AND o.user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $order_number, $user_id);
        } else {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $order_number);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $order = $result->fetch_assoc();
            $order_id = $order['id'];
            
            // If asking for items, show order items
            if ($is_items_query) {
                $items_query = "SELECT oi.*, p.name as product_name 
                               FROM order_items oi
                               JOIN products p ON oi.product_id = p.id
                               WHERE oi.order_id = ?
                               ORDER BY oi.id";
                $items_stmt = $conn->prepare($items_query);
                $items_stmt->bind_param("i", $order_id);
                $items_stmt->execute();
                $items_result = $items_stmt->get_result();
                
                if ($items_result->num_rows > 0) {
                    $response = "Items in Order " . htmlspecialchars($order['order_number']) . ":\n\n";
                    $item_num = 1;
                    while ($item = $items_result->fetch_assoc()) {
                        $response .= $item_num . ". " . htmlspecialchars($item['product_name']) . "\n";
                        $response .= "   Quantity: " . $item['quantity'] . "\n";
                        $response .= "   Price: LKR " . number_format($item['price'], 2) . "\n";
                        $response .= "   Subtotal: LKR " . number_format($item['subtotal'], 2) . "\n\n";
                        $item_num++;
                    }
                    $response .= "Total: LKR " . number_format($order['total_amount'], 2);
                } else {
                    $response = "No items found for order " . htmlspecialchars($order['order_number']) . ".";
                }
                
                $items_stmt->close();
            } else {
                // Regular order status response
            $response = "Order Status for " . htmlspecialchars($order['order_number']) . ":\n\n";
                $response .= "Status: " . ucfirst($order['status']) . "\n";
                $response .= "Amount: LKR " . number_format($order['total_amount'], 2) . "\n";
                $response .= "Payment: " . ucfirst($order['payment_status']) . "\n";
            
            if ($order['delivery_status']) {
                    $response .= "Delivery: " . ucfirst(str_replace('_', ' ', $order['delivery_status'])) . "\n";
                if ($order['tracking_number']) {
                        $response .= "Tracking: " . htmlspecialchars($order['tracking_number']) . "\n";
                }
                if ($order['estimated_delivery_date']) {
                        $response .= "Estimated Delivery: " . date('M d, Y', strtotime($order['estimated_delivery_date'])) . "\n";
                }
            }
            
            $response .= "\nOrdered on: " . date('M d, Y H:i', strtotime($order['created_at']));
            }
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'order_status',
                'data' => $order
            ];
        } else {
            return [
                'success' => true,
                'message' => "I couldn't find an order with that number. Please check your order number and try again.",
                'type' => 'error'
            ];
        }
    } else {
        // Get latest order
        $query = "SELECT o.*, d.status as delivery_status, d.tracking_number
                  FROM orders o
                  LEFT JOIN deliveries d ON o.id = d.order_id
                  WHERE o.user_id = ?
                  ORDER BY o.created_at DESC
                  LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $order = $result->fetch_assoc();
            $response = "Your latest order " . htmlspecialchars($order['order_number']) . ":\n\n";
            $response .= "Status: " . ucfirst($order['status']) . "\n";
            $response .= "Amount: LKR " . number_format($order['total_amount'], 2) . "\n";
            
            if ($order['delivery_status']) {
                $response .= "Delivery: " . ucfirst(str_replace('_', ' ', $order['delivery_status'])) . "\n";
            }
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'order_status',
                'data' => $order
            ];
        } else {
            return [
                'success' => true,
                'message' => "You don't have any orders yet. Browse our products to place your first order!",
                'type' => 'info'
            ];
        }
    }
}

/**
 * Handle order history queries
 */
function handleOrderHistory($user_id, $user_role, $conn) {
    if (!$user_id) {
        return [
            'success' => true,
            'message' => 'Please login to view your order history.',
            'type' => 'info'
        ];
    }
    
    $query = "SELECT COUNT(*) as total, SUM(total_amount) as total_spent
              FROM orders
              WHERE user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    
    if ($stats['total'] > 0) {
        $response = "Your Order History:\n\n";
        $response .= "Total Orders: " . $stats['total'] . "\n";
        $response .= "Total Spent: LKR " . number_format($stats['total_spent'], 2) . "\n\n";
        $response .= "You can view all your orders in the 'My Orders' section from the menu.";
        
        return [
            'success' => true,
            'message' => $response,
            'type' => 'order_history'
        ];
    } else {
        return [
            'success' => true,
            'message' => "You haven't placed any orders yet. Start shopping to see your order history!",
            'type' => 'info'
        ];
    }
}

/**
 * Handle product search queries
 */
function handleProductSearch($message, $conn) {
    // Extract product name or category
    $search_terms = [];
    preg_match_all('/\b(laptop|phone|smartphone|shirt|t-shirt|book|shoes|coffee|maker)\b/i', $message, $matches);
    if (!empty($matches[0])) {
        $search_terms = $matches[0];
    }
    
    // If no specific product mentioned, show categories
    if (empty($search_terms)) {
        $query = "SELECT c.name, COUNT(p.id) as product_count
                  FROM categories c
                  LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
                  GROUP BY c.id, c.name
                  ORDER BY c.name";
        
        $result = $conn->query($query);
        $response = "Our Product Categories:\n\n";
        
        while ($row = $result->fetch_assoc()) {
            $response .= "• " . htmlspecialchars($row['name']) . " (" . $row['product_count'] . " products)\n";
        }
        
        $response .= "\nYou can browse all products or search for specific items!";
        
        return [
            'success' => true,
            'message' => $response,
            'type' => 'product_list'
        ];
    }
    
    // Search for products
    $search = '%' . implode('%', $search_terms) . '%';
    $query = "SELECT p.*, c.name as category_name
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE (p.name LIKE ? OR p.description LIKE ?) AND p.status = 'active'
              LIMIT 5";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $response = "Found " . $result->num_rows . " product(s):\n\n";
        while ($product = $result->fetch_assoc()) {
            $response .= htmlspecialchars($product['name']) . "\n";
            $response .= "   Price: LKR " . number_format($product['price'], 2) . "\n";
            $response .= "   Category: " . htmlspecialchars($product['category_name']) . "\n";
            $response .= "   Stock: " . ($product['stock_quantity'] > 0 ? "In Stock" : "Out of Stock") . "\n\n";
        }
        $response .= "Visit our Products page to see more details and add to cart!";
        
        return [
            'success' => true,
            'message' => $response,
            'type' => 'product_search'
        ];
    } else {
        return [
            'success' => true,
            'message' => "I couldn't find products matching your search. Try browsing our product categories!",
            'type' => 'info'
        ];
    }
}

/**
 * Handle return policy queries
 */
function handleReturnPolicy() {
    $response = "Return Policy:\n\n";
    $response .= "• 30-day return policy on all products\n";
    $response .= "• Items must be in original condition\n";
    $response .= "• Original packaging required\n";
    $response .= "• Proof of purchase needed\n";
    $response .= "• Refunds processed within 5-7 business days\n\n";
    $response .= "For detailed information, visit our Return Policy page.";
    
    return [
        'success' => true,
        'message' => $response,
        'type' => 'return_policy'
    ];
}

/**
 * Handle shipping queries
 */
function handleShipping($message, $user_id, $user_role, $conn) {
    if (!$user_id) {
        return [
            'success' => true,
            'message' => 'Standard shipping takes 5-7 business days. Express shipping options available at checkout.',
            'type' => 'info'
        ];
    }
    
    // Get latest order delivery info
    $query = "SELECT o.order_number, d.status, d.tracking_number, d.estimated_delivery_date
              FROM orders o
              LEFT JOIN deliveries d ON o.id = d.order_id
              WHERE o.user_id = ?
              ORDER BY o.created_at DESC
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        $response = "Shipping Information:\n\n";
        
        if ($order['status']) {
            $response .= "Latest Order: " . htmlspecialchars($order['order_number']) . "\n";
            $response .= "Status: " . ucfirst(str_replace('_', ' ', $order['status'])) . "\n";
            
            if ($order['tracking_number']) {
                $response .= "Tracking: " . htmlspecialchars($order['tracking_number']) . "\n";
            }
            
            if ($order['estimated_delivery_date']) {
                $response .= "Estimated: " . date('M d, Y', strtotime($order['estimated_delivery_date'])) . "\n";
            }
        } else {
            $response .= "Standard shipping: 5-7 business days\n";
            $response .= "Express shipping available at checkout";
        }
        
        return [
            'success' => true,
            'message' => $response,
            'type' => 'shipping'
        ];
    } else {
        return [
            'success' => true,
            'message' => 'Standard shipping takes 5-7 business days. Express shipping options available at checkout.',
            'type' => 'info'
        ];
    }
}

/**
 * Handle payment queries
 */
function handlePayment() {
    $response = "Payment Methods:\n\n";
    $response .= "• Credit Card\n";
    $response .= "• Debit Card\n";
    $response .= "• PayPal\n";
    $response .= "• Cash on Delivery (where available)\n\n";
    $response .= "All payments are secure and encrypted.";
    
    return [
        'success' => true,
        'message' => $response,
        'type' => 'payment'
    ];
}

/**
 * Handle greetings
 */
function handleGreeting($user_role) {
    $greetings = [
        "Hello! How can I help you today?",
        "Hi there! What can I assist you with?",
        "Welcome! I'm here to help with your questions."
    ];
    
    $response = $greetings[array_rand($greetings)];
    
    if ($user_role === 'admin') {
        $response .= "\n\nAs an admin, I can help you with order management, product information, and system queries.";
    } elseif ($user_role === 'customer') {
        $response .= "\n\nI can help you with:\n• Order status\n• Product information\n• Returns & shipping\n• Payment questions";
    } else {
        $response .= "\n\nI can help you with product information, shipping details, and more!";
    }
    
    return [
        'success' => true,
        'message' => $response,
        'type' => 'greeting'
    ];
}

/**
 * Handle help queries
 */
function handleHelp($user_role) {
    $response = "I can help you with:\n\n";
    
    if ($user_role === 'customer') {
        $response .= "• Check order status\n";
        $response .= "• View order history\n";
        $response .= "• Search products\n";
        $response .= "• Return policy\n";
        $response .= "• Shipping information\n";
        $response .= "• Payment methods\n";
        $response .= "• Quantitative queries (how many orders, how much spent, average order value, date ranges, most ordered items, etc.)\n";
    } elseif ($user_role === 'admin') {
        $response .= "• Order management queries\n";
        $response .= "• Product information\n";
        $response .= "• Delivery status\n";
        $response .= "• User information\n";
        $response .= "• Quantitative queries (how many customers, total orders, revenue, etc.)\n";
    } else {
        $response .= "• Product information\n";
        $response .= "• Return policy\n";
        $response .= "• Shipping details\n";
        $response .= "• Payment methods\n";
        $response .= "• Quantitative queries (how many products, count, etc.)\n";
    }
    
    $response .= "\nJust ask me anything!";
    
    return [
        'success' => true,
        'message' => $response,
        'type' => 'help'
    ];
}

/**
 * Parse date range from message
 */
function parseDateRange($message, $conn) {
    $date_conditions = [];
    $date_params = [];
    
    // Detect time periods
    if (preg_match('/\b(this month|current month)\b/i', $message)) {
        $date_conditions[] = "DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
    } elseif (preg_match('/\b(last month|previous month)\b/i', $message)) {
        $date_conditions[] = "DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')";
    } elseif (preg_match('/\b(this year|current year)\b/i', $message)) {
        $date_conditions[] = "YEAR(created_at) = YEAR(NOW())";
    } elseif (preg_match('/\b(last year|previous year)\b/i', $message)) {
        $date_conditions[] = "YEAR(created_at) = YEAR(NOW()) - 1";
    } elseif (preg_match('/\b(today)\b/i', $message)) {
        $date_conditions[] = "DATE(created_at) = CURDATE()";
    } elseif (preg_match('/\b(yesterday)\b/i', $message)) {
        $date_conditions[] = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    } elseif (preg_match('/\b(this week|current week)\b/i', $message)) {
        $date_conditions[] = "YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)";
    } elseif (preg_match('/\b(last week|previous week)\b/i', $message)) {
        $date_conditions[] = "YEARWEEK(created_at, 1) = YEARWEEK(DATE_SUB(NOW(), INTERVAL 1 WEEK), 1)";
    }
    
    // Detect month names
    $months = [
        'january' => 1, 'jan' => 1, 'february' => 2, 'feb' => 2,
        'march' => 3, 'mar' => 3, 'april' => 4, 'apr' => 4,
        'may' => 5, 'june' => 6, 'jun' => 6, 'july' => 7, 'jul' => 7,
        'august' => 8, 'aug' => 8, 'september' => 9, 'sep' => 9, 'sept' => 9,
        'october' => 10, 'oct' => 10, 'november' => 11, 'nov' => 11,
        'december' => 12, 'dec' => 12
    ];
    
    // Detect "between X and Y" or "from X to Y"
    if (preg_match('/\b(between|from)\s+(\w+)\s+(and|to)\s+(\w+)\b/i', $message, $matches)) {
        $month1 = strtolower($matches[2]);
        $month2 = strtolower($matches[4]);
        
        if (isset($months[$month1]) && isset($months[$month2])) {
            $year = date('Y');
            // Check if year is mentioned
            if (preg_match('/\b(20\d{2})\b/', $message, $year_match)) {
                $year = intval($year_match[1]);
            }
            $start_date = "$year-" . str_pad($months[$month1], 2, '0', STR_PAD_LEFT) . "-01";
            $end_date = date('Y-m-t', strtotime("$year-" . str_pad($months[$month2], 2, '0', STR_PAD_LEFT) . "-01"));
            $date_conditions[] = "DATE(created_at) BETWEEN ? AND ?";
            $date_params[] = $start_date;
            $date_params[] = $end_date;
        }
    } elseif (preg_match('/\b(in|during)\s+(\w+)\b/i', $message, $matches)) {
        $month = strtolower($matches[2]);
        if (isset($months[$month])) {
            $year = date('Y');
            // Check if year is mentioned
            if (preg_match('/\b(20\d{2})\b/', $message, $year_match)) {
                $year = intval($year_match[1]);
            }
            $date_conditions[] = "MONTH(created_at) = ? AND YEAR(created_at) = ?";
            $date_params[] = $months[$month];
            $date_params[] = $year;
        }
    }
    
    return [
        'conditions' => $date_conditions,
        'params' => $date_params
    ];
}

/**
 * Handle quantitative queries (how many, how much, count, sum, average)
 */
function handleQuantitativeQuery($message, $user_id, $user_role, $conn) {
    // Detect operation type
    $is_count = preg_match('/\b(how many|count|number of|quantity of)\b/', $message);
    $is_sum = preg_match('/\b(how much|sum|total|spent|spending)\b/', $message);
    $is_average = preg_match('/\b(average|avg|mean)\b/', $message);
    $is_max = preg_match('/\b(max|maximum|highest|most|top|best)\b/', $message);
    $is_min = preg_match('/\b(min|minimum|lowest|least)\b/', $message);
    
    // Detect entity type
    $is_orders = preg_match('/\b(order|orders)\b/', $message);
    $is_products = preg_match('/\b(product|products|item|items)\b/', $message);
    $is_cart = preg_match('/\b(cart|items in cart|cart items)\b/', $message);
    $is_categories = preg_match('/\b(categor|categories)\b/', $message);
    $is_spending = preg_match('/\b(spent|spending|total spent|money spent|amount spent)\b/', $message);
    $is_most_ordered = preg_match('/\b(most ordered|top ordered|best selling|popular|frequently ordered)\b/i', $message);
    $is_stock = preg_match('/\b(stock|available|availability|quantity|in stock|out of stock)\b/i', $message);
    
    // Parse date range
    $date_range = parseDateRange($message, $conn);
    
    // Handle most ordered items / top products
    if ($is_most_ordered && ($is_orders || $is_products || preg_match('/\b(item|items)\b/', $message))) {
        // Get most ordered products - admin sees all, customers see all (it's public info)
        $limit = 5;
        if (preg_match('/\b(top|first)\s+(\d+)\b/i', $message, $matches)) {
            $limit = intval($matches[2]);
        }
        
        // Build WHERE clause with date range (apply to orders.created_at)
        $where_clauses = [];
        $where_params = [];
        $param_types = '';
        
        // Add date conditions (replace created_at with o.created_at for orders table)
        if (!empty($date_range['conditions'])) {
            foreach ($date_range['conditions'] as $condition) {
                $where_clauses[] = str_replace('created_at', 'o.created_at', $condition);
            }
            $where_params = array_merge($where_params, $date_range['params']);
            $param_types = str_repeat('s', count($date_range['params']));
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        $query = "SELECT p.name, SUM(oi.quantity) as total_ordered, SUM(oi.subtotal) as total_revenue
                 FROM order_items oi
                 JOIN products p ON oi.product_id = p.id
                 JOIN orders o ON oi.order_id = o.id
                 " . $where_sql . "
                 GROUP BY p.id, p.name
                 ORDER BY total_ordered DESC
                 LIMIT ?";
        
        $stmt = $conn->prepare($query);
        if (!empty($where_params)) {
            $params = array_merge($where_params, [$limit]);
            $types = $param_types . 'i';
            $stmt->bind_param($types, ...$params);
        } else {
            $stmt->bind_param("i", $limit);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $period_desc = '';
        if (preg_match('/\b(this year|current year)\b/i', $message)) {
            $period_desc = ' this year';
        } elseif (preg_match('/\b(this month|current month)\b/i', $message)) {
            $period_desc = ' this month';
        } elseif (preg_match('/\b(last month|previous month)\b/i', $message)) {
            $period_desc = ' last month';
        } elseif (preg_match('/\b(between|from)\s+(\w+)\s+(and|to)\s+(\w+)\b/i', $message)) {
            $period_desc = ' in the specified period';
        }
        
        if ($result->num_rows > 0) {
            $response = "Most Ordered Products" . $period_desc . ":\n\n";
            $rank = 1;
            while ($row = $result->fetch_assoc()) {
                $response .= $rank . ". " . htmlspecialchars($row['name']) . "\n";
                $response .= "   Quantity: " . $row['total_ordered'] . "\n";
                $response .= "   Revenue: LKR " . number_format($row['total_revenue'], 2) . "\n\n";
                $rank++;
            }
        } else {
            $response = "No orders found" . $period_desc . ".";
        }
        
        return [
            'success' => true,
            'message' => $response,
            'type' => 'quantitative'
        ];
    }
    
    // Handle orders queries
    if ($is_orders) {
        // Detect order status filters
        $order_status = null;
        $payment_status = null;
        if (preg_match('/\b(shipped|ship)\b/', $message)) {
            $order_status = 'shipped';
        } elseif (preg_match('/\b(pending|waiting)\b/', $message)) {
            $order_status = 'pending';
        } elseif (preg_match('/\b(delivered|delivery)\b/', $message)) {
            $order_status = 'delivered';
        } elseif (preg_match('/\b(processing|process)\b/', $message)) {
            $order_status = 'processing';
        } elseif (preg_match('/\b(cancelled|canceled|cancel)\b/', $message)) {
            $order_status = 'cancelled';
        } elseif (preg_match('/\b(returned|return)\b/', $message)) {
            $order_status = 'returned';
        }
        
        // Check for payment status separately (only if no order status was detected)
        if (!$order_status && preg_match('/\b(paid|payment)\b/', $message)) {
            $payment_status = 'paid';
        }
        
        // Build WHERE clause with date range and filters
        $where_clauses = [];
        $where_params = [];
        $param_types = '';
        
        if ($user_role !== 'admin' && $user_id) {
            $where_clauses[] = "user_id = ?";
            $where_params[] = $user_id;
            $param_types .= 'i';
        } elseif (!$user_id && $user_role !== 'admin') {
            return [
                'success' => true,
                'message' => 'Please login to check your order information.',
                'type' => 'info'
            ];
        }
        
        if ($order_status) {
            $where_clauses[] = "status = ?";
            $where_params[] = $order_status;
            $param_types .= 's';
        }
        
        if ($payment_status) {
            $where_clauses[] = "payment_status = ?";
            $where_params[] = $payment_status;
            $param_types .= 's';
        }
        
        // Add date conditions
        if (!empty($date_range['conditions'])) {
            $where_clauses = array_merge($where_clauses, $date_range['conditions']);
            $where_params = array_merge($where_params, $date_range['params']);
            $param_types .= str_repeat('s', count($date_range['params']));
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        // Get time period description
        $period_desc = '';
        if (preg_match('/\b(this month|current month)\b/i', $message)) {
            $period_desc = ' this month';
        } elseif (preg_match('/\b(last month|previous month)\b/i', $message)) {
            $period_desc = ' last month';
        } elseif (preg_match('/\b(this year|current year)\b/i', $message)) {
            $period_desc = ' this year';
        } elseif (preg_match('/\b(between|from)\s+(\w+)\s+(and|to)\s+(\w+)\b/i', $message)) {
            $period_desc = ' in the specified period';
        }
        
        // If status filter is present, treat as count query (even if "total" is mentioned)
        if ($is_count || ($is_sum && ($order_status || $payment_status))) {
            // Count orders (with optional status filter and date range)
            $query = "SELECT COUNT(*) as count FROM orders " . $where_sql;
            
            if (!empty($where_params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($param_types, ...$where_params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $data = $result->fetch_assoc();
            $count = $data['count'];
            
            if ($user_role === 'admin') {
                $status_text = $order_status ? $order_status . ' ' : ($payment_status ? 'paid ' : '');
                $response = "There are " . $count . " " . $status_text . "order" . ($count != 1 ? 's' : '') . $period_desc . " in the system.";
            } else {
                $status_text = $order_status ? $order_status . ' ' : ($payment_status ? 'paid ' : '');
                $response = "You have " . $count . " " . $status_text . "order" . ($count != 1 ? 's' : '') . $period_desc . ".";
            }
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'quantitative'
            ];
        } elseif ($is_sum || $is_spending) {
            // Total spending (with date range support)
            $query = "SELECT SUM(total_amount) as total FROM orders " . $where_sql;
            
            if (!empty($where_params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($param_types, ...$where_params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $data = $result->fetch_assoc();
            $total = $data['total'] ?? 0;
            
            if ($user_role === 'admin') {
                $response = "Total revenue" . $period_desc . " is LKR " . number_format($total, 2) . ".";
            } else {
                $response = "You have spent a total of LKR " . number_format($total, 2) . $period_desc . " on all your orders.";
            }
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'quantitative'
            ];
        } elseif ($is_average) {
            // Average order value (with date range support)
            $query = "SELECT AVG(total_amount) as avg_amount, COUNT(*) as count FROM orders " . $where_sql;
            
            if (!empty($where_params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($param_types, ...$where_params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $data = $result->fetch_assoc();
            
            if ($data['count'] > 0) {
                $avg = $data['avg_amount'] ?? 0;
                if ($user_role === 'admin') {
                    $response = "The average order value" . $period_desc . " is LKR " . number_format($avg, 2) . ".";
                } else {
                    $response = "Your average order value" . $period_desc . " is LKR " . number_format($avg, 2) . ".";
                }
            } else {
                $response = "No orders found" . $period_desc . " to calculate an average.";
            }
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'quantitative'
            ];
        } elseif ($is_max || $is_min) {
            // Highest/Lowest order value
            $order_by = $is_max ? 'DESC' : 'ASC';
            $query = "SELECT total_amount, order_number, created_at FROM orders " . $where_sql . " ORDER BY total_amount " . $order_by . " LIMIT 1";
            
            if (!empty($where_params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($param_types, ...$where_params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            if ($result->num_rows > 0) {
                $order = $result->fetch_assoc();
                $comparison = $is_max ? 'highest' : 'lowest';
                $response = "The " . $comparison . " order value" . $period_desc . " is LKR " . number_format($order['total_amount'], 2) . 
                           " (Order: " . htmlspecialchars($order['order_number']) . ").";
            } else {
                $response = "No orders found" . $period_desc . ".";
            }
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'quantitative'
            ];
        }
    }
    
    // Handle products queries
    if ($is_products) {
        // Detect product status filter
        $product_status = null;
        $show_all = preg_match('/\b(all|total)\s+products?\b/i', $message);
        
        if (preg_match('/\b(inactive|disabled|unavailable)\b/i', $message)) {
            $product_status = 'inactive';
        } elseif (preg_match('/\b(active|available)\b/i', $message)) {
            $product_status = 'active';
        }
        
        // Default to active if no status specified and not asking for inactive or all
        if ($product_status === null && !$show_all) {
            $product_status = 'active';
        }
        
        // Detect category filter
        $category_id = null;
        $category_name = null;
        if (preg_match('/\b(in|category|categories)\s+(\w+)\b/i', $message, $matches)) {
            // Try to find category name in message
            $category_query = "SELECT id, name FROM categories";
            $cat_result = $conn->query($category_query);
            
            // Check each word in message against category names
            $words = preg_split('/\s+/', strtolower($message));
            while ($cat = $cat_result->fetch_assoc()) {
                $cat_name_lower = strtolower($cat['name']);
                foreach ($words as $word) {
                    // Check if word matches category name or is part of it
                    if (strpos($cat_name_lower, $word) !== false && strlen($word) > 3) {
                        $category_id = $cat['id'];
                        $category_name = $cat['name'];
                        break 2;
                    }
                }
            }
            
            // Also check for common category names directly
            $common_categories = [
                'electronics' => ['electronics', 'electronic', 'tech'],
                'clothing' => ['clothing', 'clothes', 'apparel', 'fashion'],
                'home' => ['home', 'kitchen', 'household'],
                'books' => ['books', 'book', 'reading'],
                'sports' => ['sports', 'sport', 'fitness', 'athletic']
            ];
            
            foreach ($common_categories as $cat_key => $keywords) {
                foreach ($keywords as $keyword) {
                    if (preg_match('/\b' . $keyword . '\b/i', $message)) {
                        $cat_lookup = "SELECT id, name FROM categories WHERE LOWER(name) LIKE ?";
                        $cat_stmt = $conn->prepare($cat_lookup);
                        $search_term = '%' . $cat_key . '%';
                        $cat_stmt->bind_param("s", $search_term);
                        $cat_stmt->execute();
                        $cat_result2 = $cat_stmt->get_result();
                        if ($cat_result2->num_rows > 0) {
                            $cat_data = $cat_result2->fetch_assoc();
                            $category_id = $cat_data['id'];
                            $category_name = $cat_data['name'];
                            break 2;
                        }
                        $cat_stmt->close();
                    }
                }
            }
        }
        
        // Detect price range filter
        $price_min = null;
        $price_max = null;
        $price_condition = null;
        
        // Extract price values (numbers, possibly with currency)
        if (preg_match('/\b(below|under|less than|cheaper than|lower than)\s+(?:lkr\s*)?(\d+(?:\.\d+)?)\b/i', $message, $matches)) {
            $price_max = floatval($matches[2]);
            $price_condition = 'max';
        } elseif (preg_match('/\b(above|over|more than|greater than|higher than)\s+(?:lkr\s*)?(\d+(?:\.\d+)?)\b/i', $message, $matches)) {
            $price_min = floatval($matches[2]);
            $price_condition = 'min';
        } elseif (preg_match('/\b(between|from)\s+(?:lkr\s*)?(\d+(?:\.\d+)?)\s+(?:and|to)\s+(?:lkr\s*)?(\d+(?:\.\d+)?)\b/i', $message, $matches)) {
            $price_min = floatval($matches[2]);
            $price_max = floatval($matches[3]);
            $price_condition = 'range';
        } elseif (preg_match('/\b(?:lkr\s*)?(\d+(?:\.\d+)?)\s*(?:and|to)\s*(?:lkr\s*)?(\d+(?:\.\d+)?)\b/i', $message, $matches)) {
            // Just numbers with "and" or "to" between them
            $price_min = floatval($matches[1]);
            $price_max = floatval($matches[2]);
            $price_condition = 'range';
        }
        
        if ($is_count) {
            // Count products (with optional status, category, and price filter)
            $where_clauses = [];
            $where_params = [];
            $param_types = '';
            
            if ($product_status && !$show_all) {
                $where_clauses[] = "status = ?";
                $where_params[] = $product_status;
                $param_types .= 's';
            }
            
            if ($category_id) {
                $where_clauses[] = "category_id = ?";
                $where_params[] = $category_id;
                $param_types .= 'i';
            }
            
            // Add price conditions
            if ($price_condition === 'max' && $price_max !== null) {
                $where_clauses[] = "price < ?";
                $where_params[] = $price_max;
                $param_types .= 'd';
            } elseif ($price_condition === 'min' && $price_min !== null) {
                $where_clauses[] = "price > ?";
                $where_params[] = $price_min;
                $param_types .= 'd';
            } elseif ($price_condition === 'range' && $price_min !== null && $price_max !== null) {
                $where_clauses[] = "price BETWEEN ? AND ?";
                $where_params[] = $price_min;
                $where_params[] = $price_max;
                $param_types .= 'dd';
            }
            
            $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
            
            if ($show_all) {
                // Count all products regardless of status (with optional category and price filter)
                $all_where_clauses = [];
                $all_where_params = [];
                $all_param_types = '';
                
                if ($category_id) {
                    $all_where_clauses[] = "category_id = ?";
                    $all_where_params[] = $category_id;
                    $all_param_types .= 'i';
                }
                
                // Add price conditions
                if ($price_condition === 'max' && $price_max !== null) {
                    $all_where_clauses[] = "price < ?";
                    $all_where_params[] = $price_max;
                    $all_param_types .= 'd';
                } elseif ($price_condition === 'min' && $price_min !== null) {
                    $all_where_clauses[] = "price > ?";
                    $all_where_params[] = $price_min;
                    $all_param_types .= 'd';
                } elseif ($price_condition === 'range' && $price_min !== null && $price_max !== null) {
                    $all_where_clauses[] = "price BETWEEN ? AND ?";
                    $all_where_params[] = $price_min;
                    $all_where_params[] = $price_max;
                    $all_param_types .= 'dd';
                }
                
                $all_where_sql = !empty($all_where_clauses) ? 'WHERE ' . implode(' AND ', $all_where_clauses) : '';
                
                if (!empty($all_where_params)) {
                    $query = "SELECT COUNT(*) as count FROM products " . $all_where_sql;
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param($all_param_types, ...$all_where_params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    $query = "SELECT COUNT(*) as count FROM products";
                    $result = $conn->query($query);
                }
                $data = $result->fetch_assoc();
                $count = $data['count'];
                
                // Also get breakdown if no category or price filter
                if (!$category_id && !$price_condition) {
                    $active_query = "SELECT COUNT(*) as count FROM products WHERE status = 'active'";
                    $active_result = $conn->query($active_query);
                    $active_data = $active_result->fetch_assoc();
                    $active_count = $active_data['count'];
                    $inactive_count = $count - $active_count;
                    
                    $response = "We have " . $count . " total product" . ($count != 1 ? 's' : '') . " in our store:\n";
                    $response .= "• Active: " . $active_count . "\n";
                    $response .= "• Inactive: " . $inactive_count;
                } else {
                    $filter_parts = [];
                    if ($category_id) {
                        $filter_parts[] = "in the " . htmlspecialchars($category_name) . " category";
                    }
                    if ($price_condition === 'max' && $price_max !== null) {
                        $filter_parts[] = "below LKR " . number_format($price_max, 2);
                    } elseif ($price_condition === 'min' && $price_min !== null) {
                        $filter_parts[] = "above LKR " . number_format($price_min, 2);
                    } elseif ($price_condition === 'range' && $price_min !== null && $price_max !== null) {
                        $filter_parts[] = "between LKR " . number_format($price_min, 2) . " and LKR " . number_format($price_max, 2);
                    }
                    $filter_text = !empty($filter_parts) ? " " . implode(" and ", $filter_parts) : "";
                    $response = "We have " . $count . " total product" . ($count != 1 ? 's' : '') . $filter_text . ".";
                }
            } else {
                if (!empty($where_params)) {
                    $query = "SELECT COUNT(*) as count FROM products " . $where_sql;
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param($param_types, ...$where_params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    $query = "SELECT COUNT(*) as count FROM products";
                    $result = $conn->query($query);
                }
                $data = $result->fetch_assoc();
                
                $count = $data['count'];
                $status_text = $product_status === 'inactive' ? 'inactive' : 'active';
                
                // Build response with filters
                $filter_parts = [];
                if ($category_id) {
                    $filter_parts[] = "in the " . htmlspecialchars($category_name) . " category";
                }
                if ($price_condition === 'max' && $price_max !== null) {
                    $filter_parts[] = "below LKR " . number_format($price_max, 2);
                } elseif ($price_condition === 'min' && $price_min !== null) {
                    $filter_parts[] = "above LKR " . number_format($price_min, 2);
                } elseif ($price_condition === 'range' && $price_min !== null && $price_max !== null) {
                    $filter_parts[] = "between LKR " . number_format($price_min, 2) . " and LKR " . number_format($price_max, 2);
                }
                
                if (!empty($filter_parts)) {
                    $filter_text = " " . implode(" and ", $filter_parts);
                } else {
                    $filter_text = "";
                }
                
                if ($category_id || $price_condition) {
                    $response = "We have " . $count . " " . $status_text . " product" . ($count != 1 ? 's' : '') . $filter_text . ".";
                } else {
                    $response = "We have " . $count . " " . $status_text . " product" . ($count != 1 ? 's' : '') . " in our store.";
                }
            }
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'quantitative'
            ];
        } elseif ($is_sum && preg_match('/\b(value|worth|total value)\b/', $message)) {
            // Total value of products (with optional status, category, and price filter)
            $where_clauses = [];
            $where_params = [];
            $param_types = '';
            
            if ($product_status && !$show_all) {
                $where_clauses[] = "status = ?";
                $where_params[] = $product_status;
                $param_types .= 's';
            }
            
            if ($category_id) {
                $where_clauses[] = "category_id = ?";
                $where_params[] = $category_id;
                $param_types .= 'i';
            }
            
            // Add price conditions
            if ($price_condition === 'max' && $price_max !== null) {
                $where_clauses[] = "price < ?";
                $where_params[] = $price_max;
                $param_types .= 'd';
            } elseif ($price_condition === 'min' && $price_min !== null) {
                $where_clauses[] = "price > ?";
                $where_params[] = $price_min;
                $param_types .= 'd';
            } elseif ($price_condition === 'range' && $price_min !== null && $price_max !== null) {
                $where_clauses[] = "price BETWEEN ? AND ?";
                $where_params[] = $price_min;
                $where_params[] = $price_max;
                $param_types .= 'dd';
            }
            
            $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
            $query = "SELECT SUM(price * stock_quantity) as total_value FROM products " . $where_sql;
            
            if (!empty($where_params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($param_types, ...$where_params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $data = $result->fetch_assoc();
            $total = $data['total_value'] ?? 0;
            $status_text = $product_status === 'inactive' ? 'inactive' : 'active';
            
            $filter_parts = [];
            if ($category_id) {
                $filter_parts[] = "in " . htmlspecialchars($category_name);
            }
            if ($price_condition === 'max' && $price_max !== null) {
                $filter_parts[] = "below LKR " . number_format($price_max, 2);
            } elseif ($price_condition === 'min' && $price_min !== null) {
                $filter_parts[] = "above LKR " . number_format($price_min, 2);
            } elseif ($price_condition === 'range' && $price_min !== null && $price_max !== null) {
                $filter_parts[] = "between LKR " . number_format($price_min, 2) . " and LKR " . number_format($price_max, 2);
            }
            
            $filter_text = !empty($filter_parts) ? " " . implode(" and ", $filter_parts) : "";
            $response = "The total value of all " . $status_text . " products" . $filter_text . " in stock is LKR " . number_format($total, 2) . ".";
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'quantitative'
            ];
        } elseif ($is_average) {
            // Average product price (with optional status, category, and price filter)
            $where_clauses = [];
            $where_params = [];
            $param_types = '';
            
            if ($product_status && !$show_all) {
                $where_clauses[] = "status = ?";
                $where_params[] = $product_status;
                $param_types .= 's';
            }
            
            if ($category_id) {
                $where_clauses[] = "category_id = ?";
                $where_params[] = $category_id;
                $param_types .= 'i';
            }
            
            // Add price conditions
            if ($price_condition === 'max' && $price_max !== null) {
                $where_clauses[] = "price < ?";
                $where_params[] = $price_max;
                $param_types .= 'd';
            } elseif ($price_condition === 'min' && $price_min !== null) {
                $where_clauses[] = "price > ?";
                $where_params[] = $price_min;
                $param_types .= 'd';
            } elseif ($price_condition === 'range' && $price_min !== null && $price_max !== null) {
                $where_clauses[] = "price BETWEEN ? AND ?";
                $where_params[] = $price_min;
                $where_params[] = $price_max;
                $param_types .= 'dd';
            }
            
            $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
            $query = "SELECT AVG(price) as avg_price FROM products " . $where_sql;
            
            if (!empty($where_params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($param_types, ...$where_params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $data = $result->fetch_assoc();
            $avg = $data['avg_price'] ?? 0;
            $status_text = $product_status === 'inactive' ? 'inactive' : 'active';
            
            $filter_parts = [];
            if ($category_id) {
                $filter_parts[] = "in " . htmlspecialchars($category_name);
            }
            if ($price_condition === 'max' && $price_max !== null) {
                $filter_parts[] = "below LKR " . number_format($price_max, 2);
            } elseif ($price_condition === 'min' && $price_min !== null) {
                $filter_parts[] = "above LKR " . number_format($price_min, 2);
            } elseif ($price_condition === 'range' && $price_min !== null && $price_max !== null) {
                $filter_parts[] = "between LKR " . number_format($price_min, 2) . " and LKR " . number_format($price_max, 2);
            }
            
            $filter_text = !empty($filter_parts) ? " " . implode(" and ", $filter_parts) : "";
            $response = "The average " . $status_text . " product price" . $filter_text . " is LKR " . number_format($avg, 2) . ".";
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'quantitative'
            ];
        }
    }
    
    // Handle cart queries
    if ($is_cart) {
        if (!$user_id) {
            return [
                'success' => true,
                'message' => 'Please login to check your cart.',
                'type' => 'info'
            ];
        }
        
        if ($is_count) {
            // Count cart items
            $query = "SELECT COUNT(*) as count FROM cart WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            
            $count = $data['count'];
            if ($count > 0) {
                // Also get total quantity
                $qty_query = "SELECT SUM(quantity) as total_qty FROM cart WHERE user_id = ?";
                $qty_stmt = $conn->prepare($qty_query);
                $qty_stmt->bind_param("i", $user_id);
                $qty_stmt->execute();
                $qty_result = $qty_stmt->get_result();
                $qty_data = $qty_result->fetch_assoc();
                $total_qty = $qty_data['total_qty'] ?? 0;
                
                $response = "You have " . $count . " item" . ($count != 1 ? 's' : '') . 
                           " in your cart with a total quantity of " . $total_qty . ".";
            } else {
                $response = "Your cart is empty. You have 0 items.";
            }
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'quantitative'
            ];
        } elseif ($is_sum) {
            // Cart total value
            $query = "SELECT SUM(p.price * c.quantity) as total 
                     FROM cart c 
                     JOIN products p ON c.product_id = p.id 
                     WHERE c.user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            
            $total = $data['total'] ?? 0;
            if ($total > 0) {
                $response = "The total value of items in your cart is LKR " . number_format($total, 2) . ".";
            } else {
                $response = "Your cart is empty. Total value is $0.00.";
            }
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'quantitative'
            ];
        }
    }
    
    // Handle stock/availability queries (check early, before general product queries)
    if ($is_stock || preg_match('/\b(how many|count).*\b(stock|available|availability)\b/i', $message)) {
        // Try to extract product name from message
        $product_name = null;
        
        // First check for common product keywords (this is faster)
        $common_products = [
            'laptop' => ['laptop', 'notebook', 'computer'],
            'phone' => ['phone', 'smartphone', 'mobile'],
            'shirt' => ['shirt', 't-shirt', 'tshirt'],
            'book' => ['book', 'books'],
            'shoes' => ['shoes', 'shoe', 'footwear'],
            'coffee' => ['coffee', 'coffee maker', 'maker']
        ];
        
        $matched_product = null;
        $matched_keyword = null;
        
        foreach ($common_products as $prod_key => $keywords) {
            foreach ($keywords as $keyword) {
                if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $message)) {
                    $matched_keyword = $prod_key;
                    break 2;
                }
            }
        }
        
        if ($matched_keyword) {
            // Look up product by keyword
            $prod_lookup = "SELECT id, name FROM products WHERE LOWER(name) LIKE ? LIMIT 1";
            $prod_stmt = $conn->prepare($prod_lookup);
            $search_term = '%' . $matched_keyword . '%';
            $prod_stmt->bind_param("s", $search_term);
            $prod_stmt->execute();
            $prod_result = $prod_stmt->get_result();
            if ($prod_result->num_rows > 0) {
                $matched_product = $prod_result->fetch_assoc();
            }
            $prod_stmt->close();
        }
        
        // If no match from keywords, try matching against all product names
        if (!$matched_product) {
            $all_products_query = "SELECT id, name FROM products";
            $all_products_result = $conn->query($all_products_query);
            
            $words = preg_split('/\s+/', strtolower($message));
            
            while ($product = $all_products_result->fetch_assoc()) {
                $product_name_lower = strtolower($product['name']);
                foreach ($words as $word) {
                    // Check if word matches product name or is part of it (minimum 3 chars, exclude common words)
                    $exclude_words = ['how', 'many', 'stock', 'available', 'availability', 'count', 'quantity', 'the', 'is', 'are', 'in', 'of', 'for', 'with'];
                    if (strlen($word) > 2 && !in_array($word, $exclude_words) && 
                        (strpos($product_name_lower, $word) !== false || strpos($word, $product_name_lower) !== false)) {
                        $matched_product = $product;
                        break 2;
                    }
                }
            }
        }
        
        if ($matched_product) {
            // Get stock for specific product
            $stock_query = "SELECT name, stock_quantity, status FROM products WHERE id = ?";
            $stock_stmt = $conn->prepare($stock_query);
            $stock_stmt->bind_param("i", $matched_product['id']);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result();
            $product_data = $stock_result->fetch_assoc();
            
            $stock_qty = $product_data['stock_quantity'];
            $product_name = $product_data['name'];
            $status = $product_data['status'];
            
            if ($stock_qty > 0) {
                $response = htmlspecialchars($product_name) . " has " . $stock_qty . " unit" . ($stock_qty != 1 ? 's' : '') . " in stock.";
            } else {
                $response = htmlspecialchars($product_name) . " is currently out of stock.";
            }
            
            if ($status === 'inactive') {
                $response .= " (Product is inactive)";
            }
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'quantitative'
            ];
        } elseif ($is_stock && $is_products) {
            // General stock query for all products
            $stock_query = "SELECT SUM(stock_quantity) as total_stock FROM products WHERE status = 'active'";
            $stock_result = $conn->query($stock_query);
            $stock_data = $stock_result->fetch_assoc();
            
            $total_stock = $stock_data['total_stock'] ?? 0;
            $response = "Total stock available for all active products: " . $total_stock . " units.";
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'quantitative'
            ];
        }
    }
    
    // Handle categories count
    if ($is_categories && $is_count) {
        $query = "SELECT COUNT(*) as count FROM categories";
        $result = $conn->query($query);
        $data = $result->fetch_assoc();
        
        $count = $data['count'];
        $response = "We have " . $count . " categor" . ($count != 1 ? 'ies' : 'y') . ".";
        
        return [
            'success' => true,
            'message' => $response,
            'type' => 'quantitative'
        ];
    }
    
    // Handle revenue queries (for admins or general)
    $is_revenue = preg_match('/\b(revenue|total revenue|sales|total sales|income)\b/i', $message);
    if ($is_revenue) {
        // Build WHERE clause with date range
        $where_clauses = ["payment_status = 'paid'"];
        $where_params = [];
        $param_types = '';
        
        // Add date conditions
        if (!empty($date_range['conditions'])) {
            $where_clauses = array_merge($where_clauses, $date_range['conditions']);
            $where_params = array_merge($where_params, $date_range['params']);
            $param_types = str_repeat('s', count($date_range['params']));
        }
        
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        $query = "SELECT SUM(total_amount) as total FROM orders " . $where_sql;
        
        if (!empty($where_params)) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param($param_types, ...$where_params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($query);
        }
        
        $data = $result->fetch_assoc();
        $total = $data['total'] ?? 0;
        
        $period_desc = '';
        if (preg_match('/\b(this month|current month)\b/i', $message)) {
            $period_desc = ' this month';
        } elseif (preg_match('/\b(last month|previous month)\b/i', $message)) {
            $period_desc = ' last month';
        } elseif (preg_match('/\b(this year|current year)\b/i', $message)) {
            $period_desc = ' this year';
        } elseif (preg_match('/\b(between|from)\s+(\w+)\s+(and|to)\s+(\w+)\b/i', $message)) {
            $period_desc = ' in the specified period';
        }
        
        if ($user_role === 'admin') {
            $response = "Total revenue from all paid orders" . $period_desc . " is LKR " . number_format($total, 2) . ".";
        } else {
            $response = "Total revenue" . $period_desc . " is LKR " . number_format($total, 2) . ".";
        }
        
        return [
            'success' => true,
            'message' => $response,
            'type' => 'quantitative'
        ];
    }
    
    // Handle general spending queries
    if ($is_spending && !$is_orders) {
        if (!$user_id) {
            return [
                'success' => true,
                'message' => 'Please login to check your spending information.',
                'type' => 'info'
            ];
        }
        
        // Add date range support
        $where_clauses = ["user_id = ?"];
        $where_params = [$user_id];
        $param_types = 'i';
        
        if (!empty($date_range['conditions'])) {
            $where_clauses = array_merge($where_clauses, $date_range['conditions']);
            $where_params = array_merge($where_params, $date_range['params']);
            $param_types .= str_repeat('s', count($date_range['params']));
        }
        
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        $query = "SELECT SUM(total_amount) as total FROM orders " . $where_sql;
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($param_types, ...$where_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        $total = $data['total'] ?? 0;
        
        $period_desc = '';
        if (preg_match('/\b(this month|current month)\b/i', $message)) {
            $period_desc = ' this month';
        } elseif (preg_match('/\b(last month|previous month)\b/i', $message)) {
            $period_desc = ' last month';
        } elseif (preg_match('/\b(between|from)\s+(\w+)\s+(and|to)\s+(\w+)\b/i', $message)) {
            $period_desc = ' in the specified period';
        }
        
        $response = "You have spent a total of LKR " . number_format($total, 2) . $period_desc . ".";
        
        return [
            'success' => true,
            'message' => $response,
            'type' => 'quantitative'
        ];
    }
    
    // Admin-specific queries
    if ($user_role === 'admin') {
        $is_customers = preg_match('/\b(customer|customers|user|users)\b/', $message);
        $is_revenue = preg_match('/\b(revenue|total revenue|sales|total sales|income)\b/', $message);
        $is_all_orders = preg_match('/\b(all orders|total orders|all order)\b/', $message);
        
        // Count customers/users
        if ($is_customers && $is_count) {
            $query = "SELECT COUNT(*) as count FROM users WHERE role = 'customer'";
            $result = $conn->query($query);
            $data = $result->fetch_assoc();
            
            $count = $data['count'];
            $response = "There are " . $count . " customer" . ($count != 1 ? 's' : '') . " registered in the system.";
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'quantitative'
            ];
        }
        
        // Total orders (all orders in system) - with date range support
        if ($is_all_orders && $is_count) {
            $where_sql = !empty($date_range['conditions']) ? 'WHERE ' . implode(' AND ', $date_range['conditions']) : '';
            $query = "SELECT COUNT(*) as count FROM orders " . $where_sql;
            
            if (!empty($date_range['params'])) {
                $stmt = $conn->prepare($query);
                $types = str_repeat('s', count($date_range['params']));
                $stmt->bind_param($types, ...$date_range['params']);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $data = $result->fetch_assoc();
            $count = $data['count'];
            
            $period_desc = '';
            if (preg_match('/\b(this month|current month)\b/i', $message)) {
                $period_desc = ' this month';
            } elseif (preg_match('/\b(last month|previous month)\b/i', $message)) {
                $period_desc = ' last month';
            } elseif (preg_match('/\b(this year|current year)\b/i', $message)) {
                $period_desc = ' this year';
            }
            
            $response = "There are " . $count . " total order" . ($count != 1 ? 's' : '') . $period_desc . " in the system.";
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'quantitative'
            ];
        }
        
        // Total revenue - with date range support
        if ($is_revenue || ($is_sum && $is_orders && !$user_id)) {
            $where_clauses = ["payment_status = 'paid'"];
            $where_params = [];
            
            if (!empty($date_range['conditions'])) {
                $where_clauses = array_merge($where_clauses, $date_range['conditions']);
                $where_params = array_merge($where_params, $date_range['params']);
            }
            
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
            $query = "SELECT SUM(total_amount) as total FROM orders " . $where_sql;
            
            if (!empty($where_params)) {
                $stmt = $conn->prepare($query);
                $types = str_repeat('s', count($where_params));
                $stmt->bind_param($types, ...$where_params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $data = $result->fetch_assoc();
            $total = $data['total'] ?? 0;
            
            $period_desc = '';
            if (preg_match('/\b(this month|current month)\b/i', $message)) {
                $period_desc = ' this month';
            } elseif (preg_match('/\b(last month|previous month)\b/i', $message)) {
                $period_desc = ' last month';
            } elseif (preg_match('/\b(this year|current year)\b/i', $message)) {
                $period_desc = ' this year';
            } elseif (preg_match('/\b(between|from)\s+(\w+)\s+(and|to)\s+(\w+)\b/i', $message)) {
                $period_desc = ' in the specified period';
            }
            
            $response = "Total revenue from all paid orders" . $period_desc . " is LKR " . number_format($total, 2) . ".";
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'quantitative'
            ];
        }
        
        // Average order value (all orders) - with date range support
        if ($is_average && $is_orders) {
            $where_sql = !empty($date_range['conditions']) ? 'WHERE ' . implode(' AND ', $date_range['conditions']) : '';
            $query = "SELECT AVG(total_amount) as avg_amount, COUNT(*) as count FROM orders " . $where_sql;
            
            if (!empty($date_range['params'])) {
                $stmt = $conn->prepare($query);
                $types = str_repeat('s', count($date_range['params']));
                $stmt->bind_param($types, ...$date_range['params']);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $data = $result->fetch_assoc();
            
            if ($data['count'] > 0) {
                $avg = $data['avg_amount'] ?? 0;
                $period_desc = '';
                if (preg_match('/\b(this month|current month)\b/i', $message)) {
                    $period_desc = ' this month';
                } elseif (preg_match('/\b(last month|previous month)\b/i', $message)) {
                    $period_desc = ' last month';
                }
                $response = "The average order value across all orders" . $period_desc . " is LKR " . number_format($avg, 2) . ".";
            } else {
                $response = "There are no orders in the system yet.";
            }
            
            return [
                'success' => true,
                'message' => $response,
                'type' => 'quantitative'
            ];
        }
    }
    
    // Default response for unclear quantitative queries
    return [
        'success' => true,
        'message' => "I can help you with quantitative queries about:\n" .
                     "• How many orders (this month, last month, between dates)\n" .
                     "• How much you've spent (with date ranges)\n" .
                     "• Average order value\n" .
                     "• Total orders this month/year\n" .
                     "• Orders between January and March\n" .
                     "• Most ordered items / Top products\n" .
                     "• Highest/Lowest order values\n" .
                     "• Number of products\n" .
                     "• Cart items count\n" .
                     "• Number of categories\n\n" .
                     "Examples:\n" .
                     "• 'Total orders this month'\n" .
                     "• 'Orders between January and March'\n" .
                     "• 'Most ordered products'\n" .
                     "• 'Average order value this year'\n\n" .
                     "Please specify what you'd like to know about.",
        'type' => 'info'
    ];
}

/**
 * Handle general queries
 */
function handleGeneralQuery($message, $user_id, $user_role, $conn) {
    // Try to find relevant information
    $response = "I understand you're asking about: \"" . htmlspecialchars($message) . "\"\n\n";
    $response .= "I can help you with:\n";
    $response .= "• Order status and tracking\n";
    $response .= "• Product information\n";
    $response .= "• Return policy\n";
    $response .= "• Shipping information\n";
    $response .= "• Payment methods\n";
    $response .= "• Quantitative queries (how many, how much, count, sum, average, date ranges, most ordered items)\n\n";
    $response .= "Could you rephrase your question or ask about one of these topics?";
    
    return [
        'success' => true,
        'message' => $response,
        'type' => 'general'
    ];
}
?>

