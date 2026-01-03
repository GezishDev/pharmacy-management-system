-- ============================================
-- PHARMACY MANAGEMENT SYSTEM - COMPLETE DATABASE
-- ============================================

-- Create Database
CREATE DATABASE IF NOT EXISTS pharmacy_db;
USE pharmacy_db;

-- ============================================
-- TABLE 1: users (for authentication)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100),
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'pharmacist') DEFAULT 'pharmacist',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE 2: suppliers
-- ============================================
CREATE TABLE IF NOT EXISTS suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE 3: medicines (main inventory)
-- ============================================
CREATE TABLE IF NOT EXISTS medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    price DECIMAL(10,2) NOT NULL,
    quantity INT DEFAULT 0,
    expiry_date DATE,
    supplier_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    INDEX idx_expiry (expiry_date),
    INDEX idx_category (category),
    INDEX idx_supplier (supplier_id)
);

-- ============================================
-- TABLE 4: sales (transactions)
-- ============================================
CREATE TABLE IF NOT EXISTS sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(20) UNIQUE,
    customer_name VARCHAR(100),
    customer_phone VARCHAR(20),
    medicine_id INT NOT NULL,
    quantity_sold INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    sold_by INT,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id),
    FOREIGN KEY (sold_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_invoice (invoice_number),
    INDEX idx_sale_date (sale_date),
    INDEX idx_customer (customer_name),
    INDEX idx_medicine (medicine_id)
);

-- ============================================
-- SAMPLE DATA INSERTION
-- ============================================

-- Insert default admin user (password: admin123)
INSERT INTO users (full_name, username, email, password, role) VALUES
('System Administrator', 'admin', 'admin@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample pharmacist (password: pharma123)
INSERT INTO users (full_name, username, email, password, role) VALUES
('John Pharmacist', 'pharmacist', 'pharmacist@pharmacy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pharmacist');

-- Insert sample suppliers
INSERT INTO suppliers (name, email, phone, address) VALUES
('Sun Pharmaceutical Industries Ltd.', 'contact@sunpharma.com', '+91-22-4324-4324', 'Mumbai, India'),
('Cipla Limited', 'info@cipla.com', '+91-22-2308-1234', 'Mumbai, India'),
('Dr. Reddy''s Laboratories', 'contact@drreddys.com', '+91-40-4900-1234', 'Hyderabad, India'),
('Abbott India Limited', 'customer.service@abbott.com', '+91-22-6685-1000', 'Mumbai, India'),
('GlaxoSmithKline Pharmaceuticals Ltd.', 'gsk.india@gsk.com', '+91-22-2495-1234', 'Mumbai, India');

-- Insert sample medicines
INSERT INTO medicines (name, category, price, quantity, expiry_date, supplier_id) VALUES
('Paracetamol 500mg', 'Tablet', 5.50, 150, '2026-12-31', 1),
('Amoxicillin 250mg', 'Capsule', 12.75, 80, '2025-06-30', 2),
('Cetirizine 10mg', 'Tablet', 3.25, 200, '2027-03-31', 3),
('Ibuprofen 400mg', 'Tablet', 8.90, 120, '2026-09-30', 1),
('Omeprazole 20mg', 'Capsule', 15.40, 90, '2025-12-31', 4),
('Salbutamol Inhaler', 'Inhaler', 125.00, 30, '2024-11-30', 2),
('Metformin 500mg', 'Tablet', 7.80, 180, '2027-01-31', 3),
('Atorvastatin 20mg', 'Tablet', 18.50, 70, '2026-08-31', 4),
('Aspirin 75mg', 'Tablet', 8.50, 200, '2026-11-30', 1),
('Vitamin C 500mg', 'Tablet', 12.00, 150, '2027-01-31', 2),
('Antacid Liquid', 'Syrup', 45.00, 60, '2025-08-31', 3),
('Bandage 5cm', 'Surgical', 25.00, 80, '2028-12-31', 4),
('Thermometer Digital', 'Device', 120.00, 20, '2027-06-30', 1);

-- Insert sample sales (for testing reports)
INSERT INTO sales (invoice_number, customer_name, customer_phone, medicine_id, quantity_sold, total_price, sold_by, sale_date) VALUES
('INV-20241201-001', 'John Doe', '9876543210', 1, 2, 11.00, 1, '2024-12-01 10:30:00'),
('INV-20241202-001', 'Jane Smith', '9876543211', 2, 1, 12.75, 1, '2024-12-02 14:20:00'),
('INV-20241203-001', NULL, NULL, 3, 3, 9.75, 1, '2024-12-03 09:15:00'),
('INV-20241204-001', 'Robert Brown', '9876543212', 4, 2, 17.80, 1, '2024-12-04 16:45:00'),
('INV-20241205-001', NULL, NULL, 5, 1, 15.40, 1, '2024-12-05 11:30:00');

-- Update medicines stock after sample sales
UPDATE medicines SET quantity = quantity - 2 WHERE id = 1;
UPDATE medicines SET quantity = quantity - 1 WHERE id = 2;
UPDATE medicines SET quantity = quantity - 3 WHERE id = 3;
UPDATE medicines SET quantity = quantity - 2 WHERE id = 4;
UPDATE medicines SET quantity = quantity - 1 WHERE id = 5;

-- ============================================
-- DATABASE VIEWS FOR REPORTING
-- ============================================

-- View: Medicine Stock Overview
CREATE OR REPLACE VIEW medicine_stock_view AS
SELECT 
    m.id,
    m.name,
    m.category,
    m.price,
    m.quantity,
    m.expiry_date,
    s.name as supplier_name,
    CASE 
        WHEN m.quantity <= 10 THEN 'CRITICAL'
        WHEN m.quantity <= 20 THEN 'LOW'
        ELSE 'OK'
    END as stock_status,
    CASE 
        WHEN m.expiry_date IS NULL THEN 'NO_EXPIRY'
        WHEN m.expiry_date < CURDATE() THEN 'EXPIRED'
        WHEN m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'NEAR_EXPIRY'
        ELSE 'OK'
    END as expiry_status
FROM medicines m
LEFT JOIN suppliers s ON m.supplier_id = s.id;

-- View: Daily Sales Summary
CREATE OR REPLACE VIEW daily_sales_summary AS
SELECT 
    DATE(sale_date) as sale_day,
    COUNT(*) as total_transactions,
    SUM(quantity_sold) as total_items_sold,
    SUM(total_price) as total_revenue,
    AVG(total_price) as average_sale
FROM sales
GROUP BY DATE(sale_date);

-- View: Top Selling Medicines
CREATE OR REPLACE VIEW top_selling_medicines AS
SELECT 
    m.name,
    m.category,
    SUM(s.quantity_sold) as total_sold,
    SUM(s.total_price) as total_revenue,
    COUNT(s.id) as times_sold
FROM sales s
JOIN medicines m ON s.medicine_id = m.id
GROUP BY m.id, m.name, m.category
ORDER BY total_sold DESC;

-- View: Customer Purchase History
CREATE OR REPLACE VIEW customer_purchases AS
SELECT 
    customer_name,
    customer_phone,
    COUNT(*) as total_purchases,
    SUM(total_price) as total_spent,
    MAX(sale_date) as last_purchase,
    AVG(total_price) as average_purchase
FROM sales
WHERE customer_name IS NOT NULL
GROUP BY customer_name, customer_phone
HAVING total_purchases > 0;

-- ============================================
-- STORED PROCEDURES
-- ============================================

-- Procedure: Process a sale with stock update
DELIMITER //
CREATE PROCEDURE ProcessSale(
    IN p_invoice_number VARCHAR(20),
    IN p_customer_name VARCHAR(100),
    IN p_customer_phone VARCHAR(20),
    IN p_medicine_id INT,
    IN p_quantity INT,
    IN p_total_price DECIMAL(10,2),
    IN p_sold_by INT
)
BEGIN
    DECLARE current_stock INT;
    DECLARE medicine_expiry DATE;
    
    -- Start transaction
    START TRANSACTION;
    
    -- Check stock and expiry
    SELECT quantity, expiry_date INTO current_stock, medicine_expiry
    FROM medicines WHERE id = p_medicine_id FOR UPDATE;
    
    IF current_stock < p_quantity THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Insufficient stock';
    END IF;
    
    IF medicine_expiry IS NOT NULL AND medicine_expiry < CURDATE() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Medicine is expired';
    END IF;
    
    -- Insert sale record
    INSERT INTO sales (invoice_number, customer_name, customer_phone, 
                      medicine_id, quantity_sold, total_price, sold_by)
    VALUES (p_invoice_number, p_customer_name, p_customer_phone,
            p_medicine_id, p_quantity, p_total_price, p_sold_by);
    
    -- Update stock
    UPDATE medicines 
    SET quantity = quantity - p_quantity 
    WHERE id = p_medicine_id;
    
    -- Commit transaction
    COMMIT;
END//
DELIMITER ;

-- Procedure: Get Low Stock Alert
DELIMITER //
CREATE PROCEDURE GetLowStockAlert(IN threshold INT)
BEGIN
    SELECT 
        id,
        name,
        category,
        quantity,
        price,
        expiry_date,
        CASE 
            WHEN quantity = 0 THEN 'OUT_OF_STOCK'
            WHEN quantity <= threshold THEN 'LOW_STOCK'
            ELSE 'OK'
        END as alert_level
    FROM medicines
    WHERE quantity <= threshold
    ORDER BY quantity ASC;
END//
DELIMITER ;

-- Procedure: Get Expiry Alert
DELIMITER //
CREATE PROCEDURE GetExpiryAlert(IN days_threshold INT)
BEGIN
    SELECT 
        id,
        name,
        category,
        quantity,
        price,
        expiry_date,
        DATEDIFF(expiry_date, CURDATE()) as days_to_expiry,
        CASE 
            WHEN expiry_date < CURDATE() THEN 'EXPIRED'
            WHEN DATEDIFF(expiry_date, CURDATE()) <= days_threshold THEN 'NEAR_EXPIRY'
            ELSE 'OK'
        END as expiry_status
    FROM medicines
    WHERE expiry_date IS NOT NULL 
    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL days_threshold DAY)
    ORDER BY expiry_date ASC;
END//
DELIMITER ;

-- ============================================
-- TRIGGERS
-- ============================================

-- Trigger: Prevent negative stock
DELIMITER //
CREATE TRIGGER before_medicine_update
BEFORE UPDATE ON medicines
FOR EACH ROW
BEGIN
    IF NEW.quantity < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Medicine quantity cannot be negative';
    END IF;
END//
DELIMITER ;

-- Trigger: Update user's last login timestamp
DELIMITER //
CREATE TRIGGER after_user_login
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.last_login IS NOT NULL AND OLD.last_login != NEW.last_login THEN
        UPDATE users 
        SET last_login = NEW.last_login 
        WHERE id = NEW.id;
    END IF;
END//
DELIMITER ;

-- Trigger: Log sale deletion (for audit)
DELIMITER //
CREATE TRIGGER after_sale_delete
AFTER DELETE ON sales
FOR EACH ROW
BEGIN
    INSERT INTO sale_deletion_log (sale_id, invoice_number, deleted_at, deleted_by)
    VALUES (OLD.id, OLD.invoice_number, NOW(), @current_user_id);
END//
DELIMITER ;

-- ============================================
-- AUDIT TABLE (Optional for security)
-- ============================================
CREATE TABLE IF NOT EXISTS sale_deletion_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    invoice_number VARCHAR(20),
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_by INT,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
);

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================
CREATE INDEX idx_medicines_name ON medicines(name);
CREATE INDEX idx_medicines_quantity ON medicines(quantity);
CREATE INDEX idx_sales_medicine_id ON sales(medicine_id);
CREATE INDEX idx_sales_sale_date ON sales(sale_date);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_suppliers_name ON suppliers(name);

-- ============================================
-- USER MANAGEMENT (Create application user)
-- ============================================
-- Note: Run these commands in MySQL console, not phpMyAdmin SQL tab

-- Create a dedicated database user (optional)
-- CREATE USER 'pharmacy_user'@'localhost' IDENTIFIED BY 'secure_password123';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON pharmacy_db.* TO 'pharmacy_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE pharmacy_db.* TO 'pharmacy_user'@'localhost';
-- FLUSH PRIVILEGES;

-- ============================================
-- QUERIES FOR TESTING/VERIFICATION
-- ============================================

-- Test Query 1: Check all tables
SELECT 'Checking table structure...' as '';
SHOW TABLES;

-- Test Query 2: Check user accounts
SELECT 'User Accounts:' as '';
SELECT id, username, role, created_at FROM users;

-- Test Query 3: Check medicine inventory
SELECT 'Medicine Inventory Summary:' as '';
SELECT 
    COUNT(*) as total_medicines,
    SUM(quantity) as total_stock,
    SUM(price * quantity) as inventory_value
FROM medicines;

-- Test Query 4: Check sales data
SELECT 'Sales Summary:' as '';
SELECT 
    COUNT(*) as total_sales,
    SUM(quantity_sold) as total_items_sold,
    SUM(total_price) as total_revenue
FROM sales;

-- Test Query 5: Check low stock medicines
SELECT 'Low Stock Alert (≤ 20):' as '';
SELECT name, quantity, price, expiry_date
FROM medicines 
WHERE quantity <= 20 
ORDER BY quantity ASC;

-- Test Query 6: Check expired/near expiry medicines
SELECT 'Expiry Alert:' as '';
SELECT 
    name,
    quantity,
    expiry_date,
    DATEDIFF(expiry_date, CURDATE()) as days_left,
    CASE 
        WHEN expiry_date < CURDATE() THEN 'EXPIRED'
        WHEN DATEDIFF(expiry_date, CURDATE()) <= 30 THEN 'NEAR EXPIRY'
        ELSE 'OK'
    END as status
FROM medicines 
WHERE expiry_date IS NOT NULL
ORDER BY expiry_date ASC;

-- ============================================
-- CLEANUP/REFRESH QUERIES (if needed)
-- ============================================

-- To reset database (CAUTION: Deletes all data):
-- DROP DATABASE IF EXISTS pharmacy_db;
-- CREATE DATABASE pharmacy_db;
-- USE pharmacy_db;

-- To clear all data but keep structure:
-- SET FOREIGN_KEY_CHECKS = 0;
-- TRUNCATE TABLE sales;
-- TRUNCATE TABLE medicines;
-- TRUNCATE TABLE suppliers;
-- TRUNCATE TABLE users;
-- SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- IMPORTANT NOTES:
-- ============================================
/*
1. Password hashing is done by PHP using password_hash()
2. Default admin password: admin123 (hashed in sample data)
3. Always use prepared statements in PHP to prevent SQL injection
4. Run this script in phpMyAdmin's SQL tab or MySQL command line
5. Test all features after database setup
*/

-- ============================================
-- SETUP COMPLETE MESSAGE
-- ============================================
SELECT '✅ Pharmacy Management System Database Setup Complete!' as '';
SELECT '📊 Database: pharmacy_db' as '';
SELECT '👥 Users Created: admin (admin123) and pharmacist (pharma123)' as '';
SELECT '💊 Sample Medicines: 13 items added' as '';
SELECT '💰 Sample Sales: 5 transactions added' as '';
SELECT '📈 Views & Procedures: Created for reporting' as '';
SELECT '' as '';
SELECT '🔧 Next Steps:' as '';
SELECT '1. Update config/database.php with your credentials' as '';
SELECT '2. Test login with username: admin, password: admin123' as '';
SELECT '3. Test all CRUD operations' as '';
SELECT '4. Make test sales to verify stock updates' as '';