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
        
        default:
            return handleGeneralQuery($message_lower, $user_id, $user_role, $conn);
    }
}

/**
 * Detect user intent from message
 */
function detectIntent($message) {
    // Order status keywords
    if (preg_match('/\b(order|status|track|tracking|where is my order|order number)\b/', $message)) {
        return 'order_status';
    }
    
    // Order history keywords
    if (preg_match('/\b(my orders|order history|past orders|previous orders)\b/', $message)) {
        return 'order_history';
    }
    
    // Product search keywords
    if (preg_match('/\b(product|products|item|items|show|search|find|buy|purchase|price|cost)\b/', $message)) {
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
    
    // Payment keywords
    if (preg_match('/\b(payment|pay|paid|payment method|credit card|debit)\b/', $message)) {
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
            $response = "Order Status for " . htmlspecialchars($order['order_number']) . ":\n\n";
            $response .= "Status: " . ucfirst($order['status']) . "\n";
            $response .= "Amount: $" . number_format($order['total_amount'], 2) . "\n";
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
            $response .= "Amount: $" . number_format($order['total_amount'], 2) . "\n";
            
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
        $response .= "Total Spent: $" . number_format($stats['total_spent'], 2) . "\n\n";
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
            $response .= "   Price: $" . number_format($product['price'], 2) . "\n";
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
    } elseif ($user_role === 'admin') {
        $response .= "• Order management queries\n";
        $response .= "• Product information\n";
        $response .= "• Delivery status\n";
        $response .= "• User information\n";
    } else {
        $response .= "• Product information\n";
        $response .= "• Return policy\n";
        $response .= "• Shipping details\n";
        $response .= "• Payment methods\n";
    }
    
    $response .= "\nJust ask me anything!";
    
    return [
        'success' => true,
        'message' => $response,
        'type' => 'help'
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
    $response .= "• Payment methods\n\n";
    $response .= "Could you rephrase your question or ask about one of these topics?";
    
    return [
        'success' => true,
        'message' => $response,
        'type' => 'general'
    ];
}
?>

