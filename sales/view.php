<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

// Check if ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];

// Fetch sale details
$stmt = $pdo->prepare("SELECT s.*, m.name as medicine_name, u.username as sold_by_name 
                      FROM sales s 
                      LEFT JOIN medicines m ON s.medicine_id = m.id 
                      LEFT JOIN users u ON s.sold_by = u.id 
                      WHERE s.id = ?");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if(!$sale) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $sale['invoice_number']; ?> - Pharmacy Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
        }
        
        .navbar {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: #555;
            padding: 8px 15px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .nav-links a:hover {
            background: #f0f2ff;
            color: #667eea;
        }
        
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .invoice-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .invoice-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f2ff;
        }
        
        .invoice-header h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .invoice-header p {
            color: #666;
        }
        
        .invoice-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .detail-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .detail-box h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .detail-item {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }
        
        .detail-item span:first-child {
            color: #666;
        }
        
        .detail-item span:last-child {
            font-weight: 600;
            color: #333;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .items-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            color: #555;
            font-weight: 600;
            border-bottom: 1px solid #eee;
        }
        
        .items-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .total-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-top: 30px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .grand-total {
            font-size: 28px;
            font-weight: 700;
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid rgba(255,255,255,0.3);
        }
        
        .invoice-footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 14px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
        }
        
        .btn-print, .btn-back {
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-print {
            background: #28a745;
            color: white;
        }
        
        .btn-print:hover {
            background: #218838;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        @media print {
            .navbar, .back-link, .action-buttons {
                display: none !important;
            }
            
            .container {
                margin: 0;
                padding: 0;
            }
            
            .invoice-container {
                box-shadow: none;
                padding: 20px;
            }
            
            body {
                background: white;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="../dashboard.php" class="logo">Pharmacy Management</a>
        <div class="nav-links">
            <a href="../dashboard.php">Dashboard</a>
            <a href="../medicines/">Medicines</a>
            <a href="index.php">Sales</a>
            <a href="../suppliers/">Suppliers</a>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="user-info">
                <?php echo htmlspecialchars($_SESSION['username']); ?>
            </div>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container">
        <a href="index.php" class="back-link">← Back to Sales Report</a>
        
        <div class="invoice-container">
            <!-- Invoice Header -->
            <div class="invoice-header">
                <h1>Pharmacy Management System</h1>
                <p>Tax Invoice</p>
                <h2>INVOICE #<?php echo htmlspecialchars($sale['invoice_number']); ?></h2>
            </div>
            
            <!-- Invoice Details -->
            <div class="invoice-details">
                <div class="detail-box">
                    <h3>Seller Details</h3>
                    <div class="detail-item">
                        <span>Pharmacy Name:</span>
                        <span>MediCare Pharmacy</span>
                    </div>
                    <div class="detail-item">
                        <span>Address:</span>
                        <span>123 Medical Street, City</span>
                    </div>
                    <div class="detail-item">
                        <span>Phone:</span>
                        <span>+91-9876543210</span>
                    </div>
                    <div class="detail-item">
                        <span>GSTIN:</span>
                        <span>27AAAAA0000A1Z5</span>
                    </div>
                </div>
                
                <div class="detail-box">
                    <h3>Customer Details</h3>
                    <div class="detail-item">
                        <span>Customer Name:</span>
                        <span><?php echo !empty($sale['customer_name']) ? htmlspecialchars($sale['customer_name']) : 'Walk-in Customer'; ?></span>
                    </div>
                    <?php if(!empty($sale['customer_phone'])): ?>
                    <div class="detail-item">
                        <span>Phone:</span>
                        <span><?php echo htmlspecialchars($sale['customer_phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <span>Invoice Date:</span>
                        <span><?php echo date('d M Y', strtotime($sale['sale_date'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <span>Invoice Time:</span>
                        <span><?php echo date('h:i A', strtotime($sale['sale_date'])); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td><?php echo htmlspecialchars($sale['medicine_name'] ?? 'Medicine'); ?></td>
                        <td><?php echo htmlspecialchars($sale['quantity_sold']); ?></td>
                        <td>Br<?php echo number_format($sale['total_price'] / $sale['quantity_sold'], 2); ?></td>
                        <td>Br<?php echo number_format($sale['total_price'], 2); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Total Section -->
            <div class="total-section">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>Br<?php echo number_format($sale['total_price'], 2); ?></span>
                </div>
                <div class="total-row">
                    <span>Tax (0%):</span>
                    <span>Br0.00</span>
                </div>
                <div class="total-row">
                    <span>Discount:</span>
                    <span>Br0.00</span>
                </div>
                <div class="total-row grand-total">
                    <span>Grand Total:</span>
                    <span>Br<?php echo number_format($sale['total_price'], 2); ?></span>
                </div>
            </div>
            
            <!-- Payment Info -->
            <div style="margin-top: 30px; color: #666; font-size: 14px;">
                <p><strong>Payment Method:</strong> Cash</p>
                <p><strong>Sold By:</strong> <?php echo htmlspecialchars($sale['sold_by_name']); ?></p>
            </div>
            
            <!-- Invoice Footer -->
            <div class="invoice-footer">
                <p>Thank you for your business!</p>
                <p>For any queries, please contact: support@pharmacy.com | Phone: +251-9876543210</p>
                <p>This is a computer-generated invoice. No signature required.</p>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <button onclick="window.print()" class="btn-print">🖨️ Print Invoice</button>
                <a href="index.php" class="btn-back">Back to Sales</a>
            </div>
        </div>
    </div>
</body>
</html>