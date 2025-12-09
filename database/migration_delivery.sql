-- Migration script to add delivery management to existing database
-- Run this if you already have the database set up

USE ecommerce_db;

-- Update users table to add delivery role
ALTER TABLE users MODIFY role ENUM('customer', 'admin', 'delivery') DEFAULT 'customer';

-- Create delivery management table
CREATE TABLE IF NOT EXISTS deliveries (
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
);

-- Insert sample delivery personnel (password: delivery123)
INSERT INTO users (username, email, password, full_name, phone, role) VALUES
('delivery1', 'delivery1@ecommerce.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Delivery', '1234567890', 'delivery'),
('delivery2', 'delivery2@ecommerce.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Courier', '0987654321', 'delivery')
ON DUPLICATE KEY UPDATE username=username;

-- Create delivery records for existing orders (optional)
-- INSERT INTO deliveries (order_id, status) SELECT id, 'pending' FROM orders WHERE id NOT IN (SELECT order_id FROM deliveries);

