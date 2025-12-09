# E-Commerce Platform - Customer Support Chatbot System

This is a complete e-commerce platform built with PHP, MySQL, and Bootstrap, designed to support an AI-powered customer support chatbot (Scenario 03 - Mini Project).

## Features

### Customer Features
- **User Authentication**: Register, login, and logout
- **User Profile Management**: Update personal information and shipping address
- **Product Browsing**: View products by category, search functionality
- **Shopping Cart**: Add, update, and remove items from cart
- **Order Management**: Place orders, view order history, track order status
- **Return Policy & FAQ**: Accessible information pages

### Admin Features
- **Dashboard**: Overview of products, orders, customers, and revenue
- **Product Management**: Add, edit, and delete products
- **Order Management**: View and update order statuses
- **Category Management**: Organize products by categories

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB)
- Apache web server (XAMPP recommended)
- Web browser

## Installation

### Step 1: Database Setup

1. Open phpMyAdmin (usually at `http://localhost/phpmyadmin`)
2. Import the database schema:
   - Go to phpMyAdmin
   - Click on "Import" tab
   - Select the file `database/schema.sql`
   - Click "Go" to import

Alternatively, you can run the SQL file directly:
```sql
-- Open database/schema.sql and execute it in phpMyAdmin
```

**Note:** If you already have an existing database, run `database/migration_delivery.sql` to add delivery management features.

### Step 2: Database Configuration

1. Open `config/database.php`
2. Update database credentials if needed (default XAMPP settings):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Change if you have a password
   define('DB_NAME', 'ecommerce_db');
   ```

### Step 3: Site Configuration

1. Open `config/config.php`
2. Update the site URL if needed:
   ```php
   define('SITE_URL', 'http://localhost/my_works/ousl/eex6340-mini-project');
   ```

### Step 4: Access the Application

1. Start XAMPP (Apache and MySQL)
2. Navigate to: `http://localhost/my_works/ousl/eex6340-mini-project`
3. You should see the homepage

## Default Login Credentials

### Admin Account
- **Username**: `admin`
- **Password**: `admin123`

### Delivery Personnel Accounts
- **Username**: `delivery1` or `delivery2`
- **Password**: `delivery123`

### Customer Account
- Register a new account through the registration page

## Project Structure

```
eex6340-mini-project/
├── admin/                  # Admin panel pages
│   ├── index.php          # Admin dashboard
│   ├── products_manage.php # Product management
│   ├── product_add.php    # Add new product
│   ├── product_edit.php    # Edit product
│   ├── orders_manage.php   # Order management
│   └── order_detail.php    # Order details
├── auth/                   # Authentication pages
│   ├── login.php          # User login
│   ├── register.php       # User registration
│   └── logout.php         # User logout
├── config/                 # Configuration files
│   ├── config.php         # Main configuration
│   └── database.php       # Database connection
├── database/              # Database files
│   └── schema.sql        # Database schema
├── includes/              # Reusable components
│   ├── header.php        # Page header
│   └── footer.php        # Page footer
├── index.php             # Homepage
├── products.php          # Product listing
├── product_detail.php    # Product details
├── cart.php              # Shopping cart
├── checkout.php          # Checkout page
├── orders.php            # Order history
├── order_detail.php      # Order details
├── profile.php           # User profile
├── return_policy.php     # Return policy page
├── faq.php               # FAQ page
└── README.md             # This file
```

## Database Schema

The system uses the following main tables:

- **users**: Customer and admin accounts
- **categories**: Product categories
- **products**: Product information
- **cart**: Shopping cart items
- **orders**: Order information
- **order_items**: Individual items in orders
- **deliveries**: Delivery management and tracking
- **return_requests**: Return request tracking

## Usage Guide

### For Customers

1. **Register/Login**: Create an account or login
2. **Browse Products**: View products on the homepage or products page
3. **Add to Cart**: Click on a product to view details and add to cart
4. **Checkout**: Review cart and proceed to checkout
5. **Place Order**: Enter shipping address and payment method
6. **Track Orders**: View order history and status in "My Orders"

### For Admins

1. **Login**: Use admin credentials to access admin panel
2. **Dashboard**: View statistics and recent orders
3. **Manage Products**: Add, edit, or delete products
4. **Manage Orders**: Update order statuses and view order details
5. **Manage Deliveries**: Assign delivery personnel, track deliveries, update delivery status
6. **Manage Categories**: Add, edit, or delete product categories
7. **Manage Users**: View and manage user roles (customer, admin, delivery)

## For Chatbot Integration

This system provides the following data sources for your AI chatbot:

1. **Order Status Queries**: 
   - Orders table with status tracking
   - Order history per user
   - Order details with items

2. **Product Information**:
   - Product catalog with descriptions
   - Categories and pricing
   - Stock availability

3. **Return Policy**:
   - Return policy information
   - Return request tracking
   - FAQ data

4. **User Data**:
   - User profiles
   - Order history
   - Shopping preferences

## Delivery Management

The system includes comprehensive delivery management with the following features:

### Delivery Statuses
- **Pending**: Order placed, waiting for delivery assignment
- **Assigned**: Delivery person assigned to the order
- **In Transit**: Package is on the way
- **Out for Delivery**: Near customer location
- **Delivered**: Successfully delivered
- **Failed**: Delivery failed (customer not available, wrong address, etc.)

### Admin Functions
- Assign delivery personnel to orders
- Track delivery status
- Update delivery status
- Add tracking numbers
- Set estimated delivery dates
- Add delivery notes and failure reasons
- View delivery history

### Customer Functions
- View delivery status in order details
- See tracking number
- View estimated delivery date
- Contact delivery person (if assigned)

## Next Steps for Chatbot

To integrate an AI chatbot, you can:

1. Create a chatbot API endpoint that queries this database
2. Use the order, product, delivery, and user data to answer customer queries
3. Implement natural language processing to understand:
   - Order status questions
   - Delivery tracking queries
   - Product recommendations
   - Return policy questions
   - General FAQs

## Security Notes

- Passwords are hashed using PHP's `password_hash()` function
- SQL injection protection using prepared statements
- Input sanitization on user inputs
- Session-based authentication
- Role-based access control (admin/customer)

## Troubleshooting

### Database Connection Error
- Check if MySQL is running in XAMPP
- Verify database credentials in `config/database.php`
- Ensure database `ecommerce_db` exists

### Page Not Found
- Check if Apache is running
- Verify the correct path in `config/config.php`
- Check file permissions

### Session Issues
- Ensure PHP sessions are enabled
- Check `php.ini` for session configuration

## Development Notes

- Built with PHP 7.4+ (procedural style)
- Uses MySQLi for database operations
- Bootstrap 5.3 for responsive UI
- No external PHP frameworks (pure PHP)

## License

This project is created for educational purposes as part of the EEX6340 Mini Project.

## Author

Created for OUSL EEX6340 Mini Project - Scenario 03: Customer Support Chatbot

