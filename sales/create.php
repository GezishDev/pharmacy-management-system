<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

// Fetch ONLY non-expired medicines with positive stock
$medicineStmt = $pdo->query("SELECT id, name, price, quantity, expiry_date 
                             FROM medicines 
                             WHERE quantity > 0 
                             AND (expiry_date IS NULL OR expiry_date > CURDATE())
                             ORDER BY name");
$medicines = $medicineStmt->fetchAll();

$errors = [];
$success = false;
$invoice_number = '';
$sale_id = null;
$total_amount = 0;
$sale_items = [];

// Generate invoice number - FIXED FUNCTION
function generateInvoiceNumber($pdo) {
    $date = date('Ymd');
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sales WHERE DATE(sale_date) = CURDATE()");
    $count = $stmt->fetch()['count'] + 1;
    return 'INV-' . $date . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $medicines_data = $_POST['medicines'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $prices = $_POST['prices'] ?? [];
    
    // Validate
    if(empty($medicines_data) || !is_array($medicines_data)) {
        $errors[] = "Please add at least one medicine to the sale";
    } else {
        // Check stock availability and calculate totals
        $total_amount = 0;
        $sale_items = [];
        
        foreach($medicines_data as $index => $medicine_id) {
            if(empty($medicine_id)) continue;
            
            $quantity = intval($quantities[$index] ?? 0);
            $price = floatval($prices[$index] ?? 0);
            
            if($quantity <= 0) {
                $errors[] = "Quantity must be greater than 0 for all medicines";
                break;
            }
            
            // Check stock AND expiry date
            $stmt = $pdo->prepare("SELECT name, quantity, price as actual_price, expiry_date 
                                   FROM medicines 
                                   WHERE id = ? 
                                   AND quantity >= ? 
                                   AND (expiry_date IS NULL OR expiry_date > CURDATE())");
            $stmt->execute([$medicine_id, $quantity]);
            $medicine = $stmt->fetch();
            
            if(!$medicine) {
                // Check if it exists but expired or low stock
                $checkStmt = $pdo->prepare("SELECT name, quantity, expiry_date FROM medicines WHERE id = ?");
                $checkStmt->execute([$medicine_id]);
                $medCheck = $checkStmt->fetch();
                
                if($medCheck) {
                    if(!empty($medCheck['expiry_date']) && strtotime($medCheck['expiry_date']) <= time()) {
                        $errors[] = "Medicine '{$medCheck['name']}' is EXPIRED and cannot be sold";
                    } elseif($medCheck['quantity'] < $quantity) {
                        $errors[] = "Insufficient stock for '{$medCheck['name']}'. Available: {$medCheck['quantity']}";
                    }
                } else {
                    $errors[] = "Medicine not found";
                }
                break;
            }
            
            // Use actual price from database if not provided
            if($price <= 0) {
                $price = $medicine['actual_price'];
            }
            
            $item_total = $price * $quantity;
            $total_amount += $item_total;
            
            $sale_items[] = [
                'medicine_id' => $medicine_id,
                'name' => $medicine['name'],
                'quantity' => $quantity,
                'price' => $price,
                'total' => $item_total,
                'available_stock' => $medicine['quantity'],
                'expiry_date' => $medicine['expiry_date']
            ];
        }
    }
    
    // If no errors, process sale
    if(empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Generate invoice number
            $invoice_number = generateInvoiceNumber($pdo);
            
            // Insert sale record for FIRST medicine (for FK constraint)
            $first_medicine_id = $sale_items[0]['medicine_id'];
            $first_quantity = $sale_items[0]['quantity'];
            
            $sale_sql = "INSERT INTO sales (invoice_number, customer_name, customer_phone, 
                        medicine_id, quantity_sold, total_price, sold_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $sale_stmt = $pdo->prepare($sale_sql);
            $sale_stmt->execute([
                $invoice_number,
                $customer_name ?: null,
                $customer_phone ?: null,
                $first_medicine_id,
                $first_quantity,
                $total_amount,
                $_SESSION['user_id']
            ]);
            
            $sale_id = $pdo->lastInsertId();
            
            // CRITICAL: Update stock for ALL medicines in the sale
            foreach($sale_items as $item) {
                $update_sql = "UPDATE medicines SET quantity = quantity - ? WHERE id = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([$item['quantity'], $item['medicine_id']]);
                
                // Verify stock update
                $verify_stmt = $pdo->prepare("SELECT quantity FROM medicines WHERE id = ?");
                $verify_stmt->execute([$item['medicine_id']]);
                $new_stock = $verify_stmt->fetch()['quantity'];
                
                if($new_stock < 0) {
                    throw new Exception("Stock would go negative for medicine: {$item['name']}");
                }
            }
            
            $pdo->commit();
            $success = true;
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Transaction failed: " . $e->getMessage();
        } catch(Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Sale - Pharmacy Management</title>
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
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        
        .card-header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .card-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .card-header p {
            color: #666;
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
        
        .form-section {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #f0f2ff;
        }
        
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title::before {
            content: '';
            width: 5px;
            height: 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 3px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 16px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-cancel {
            background: #f8f9fa;
            color: #666;
            border: 1px solid #ddd;
            padding: 16px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            flex: 1;
        }
        
        .btn-cancel:hover {
            background: #eee;
        }
        
        .hint {
            font-size: 13px;
            color: #888;
            margin-top: 5px;
        }
        
        .required {
            color: #dc3545;
        }
        
        /* Medicines Table Styles */
        .medicines-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .medicines-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            color: #555;
            font-weight: 600;
            border-bottom: 1px solid #eee;
        }
        
        .medicines-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .medicines-table input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .medicines-table select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .btn-add, .btn-remove {
            padding: 8px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-add {
            background: #28a745;
            color: white;
        }
        
        .btn-add:hover {
            background: #218838;
        }
        
        .btn-remove {
            background: #dc3545;
            color: white;
        }
        
        .btn-remove:hover {
            background: #c82333;
        }
        
        /* Summary Box */
        .summary-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-top: 30px;
        }
        
        .summary-box h3 {
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .total-amount {
            font-size: 28px;
            font-weight: 700;
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid rgba(255,255,255,0.3);
        }
        
        /* NEW: Receipt Styles - ADD THIS SECTION */
        .receipt-container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .receipt {
            padding: 40px;
            font-family: 'Arial', sans-serif;
            background: white;
        }
        
        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }
        
        .pharmacy-logo h1 {
            color: #2c7a7b;
            margin: 0;
            font-size: 24px;
        }
        
        .pharmacy-logo p {
            color: #666;
            margin: 5px 0 0 0;
        }
        
        .receipt-title {
            text-align: right;
        }
        
        .receipt-title h2 {
            color: #333;
            margin: 0;
            font-size: 20px;
        }
        
        .invoice-number {
            font-size: 18px;
            font-weight: bold;
            color: #2c7a7b;
            margin-top: 5px;
        }
        
        .pharmacy-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
            font-size: 14px;
            line-height: 1.5;
            border-left: 4px solid #2c7a7b;
        }
        
        .receipt-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f5f7fa;
            border-radius: 5px;
        }
        
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }
        
        .receipt-table thead {
            background: #2c7a7b;
            color: white;
        }
        
        .receipt-table th {
            padding: 12px 10px;
            font-weight: bold;
        }
        
        .receipt-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        
        .totals-section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .totals-table {
            width: 100%;
            max-width: 300px;
            margin-left: auto;
            font-size: 15px;
        }
        
        .totals-table td {
            padding: 8px 10px;
            border-bottom: 1px dotted #ddd;
        }
        
        .grand-total {
            font-size: 18px;
            color: #2c7a7b;
            border-top: 2px solid #333 !important;
            margin-top: 10px;
            padding-top: 10px;
        }
        
        .receipt-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #eee;
            font-size: 13px;
        }
        
        .thank-you {
            text-align: center;
            padding: 15px;
            background: #e3f2fd;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .important-notes {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .footer-contact {
            text-align: center;
            color: #666;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .barcode {
            text-align: center;
            margin: 20px 0;
        }
        
        /* Receipt Action Buttons */
        .receipt-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            flex-wrap: wrap;
        }
        
        .print-btn, .new-sale-btn, .back-btn {
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .print-btn {
            background: #28a745;
            color: white;
        }
        
        .print-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .new-sale-btn {
            background: #ffc107;
            color: #000;
            padding: 12px 25px;
            border-radius: 8px;
        }
        
        .new-sale-btn:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
        }
        
        .back-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        /* Print Instructions */
        .print-instructions {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #2196f3;
        }
        
        .print-instructions h4 {
            margin-top: 0;
            color: #0d47a1;
        }
        
        .print-instructions ol {
            margin: 10px 0 0 20px;
        }
        
        /* Print-specific styles */
        @media print {
            body * {
                visibility: hidden;
            }
            
            .receipt-container, .receipt-container * {
                visibility: visible;
            }
            
            .receipt-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }
            
            .receipt-actions, .print-instructions {
                display: none !important;
            }
        }
        
        /* Expiry warnings */
        .expired-warning {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            font-weight: bold;
        }
        
        .stock-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .low-stock {
            color: #ffc107;
            font-weight: bold;
        }
        
        .expired-option {
            color: #dc3545;
            text-decoration: line-through;
            opacity: 0.6;
        }
        .model{
           color:red;
           font-weight:600px;
        }
        
        @media (max-width: 768px) {
            .form-row, .receipt-details {
                grid-template-columns: 1fr;
            }
            
            .medicines-table {
                font-size: 14px;
            }
            
            .medicines-table th, 
            .medicines-table td {
                padding: 8px;
            }
            
            .receipt-actions {
                flex-direction: column;
            }
        }
        /* ===== MOBILE TOOLBAR FIX ===== */
@media (max-width: 768px) {


    /* NAVBAR */
    .navbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
        padding: 15px;
    }

    .logo {
        font-size: 20px;
    }

    .nav-links {
        flex-wrap: wrap;
        gap: 10px;
        width: 100%;
    }

    .nav-links a {
        flex: 1 1 45%;
        text-align: center;
        padding: 10px;
        font-size: 14px;
    }

    /* USER + LOGOUT */
    .navbar > div:last-child {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }

    .user-info {
        text-align: center;
        font-size: 14px;
        color: #555;
    }


    .user-info,
    .profile,
    .logout-btn {
        text-align: center;
        margin-top: 8px;
    }
    .logout-btn,
    a.logout,
    button.logout {
         width: 100%;
        display: block;
        text-align: center;
        padding: 14px;
        margin-top: 12px;
         text-decoration: none;

        background: linear-gradient(135deg, #ff3b3b, #c70000);
        color: #fff !important;

        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;

        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
        border: none;}
           .logout-btn:active,
    a.logout:active,
    button.logout:active {
        transform: scale(0.96);
    }

        
    
}
/* ===== RESPONSIVE TABLE (NO HTML CHANGE) ===== */
@media (max-width: 768px) {

    table {
        width: 100%;
        border-collapse: collapse;
    }

    table thead {
        display: none;
    }

    table,
    table tbody,
    table tr,
    table td {
        display: block;
        width: 100%;
    }

    table tr {
        margin-bottom: 15px;
        background: #fff;
        border-radius: 10px;
        padding: 10px;
        box-shadow: 0 0 8px rgba(0,0,0,0.05);
    }

    table td {
        display: flex;
        justify-content: space-between;
        padding: 8px 10px;
        border-bottom: 1px solid #eee;
        font-size: 14px;
    }

    table td:last-child {
        border-bottom: none;
    }
}
/* ===== FORM RESPONSIVE FIX ===== */
@media (max-width: 768px) {

    input,
    select,
    button,
    textarea {
        width: 100%;
        font-size: 16px;
    }

    .form-row,
    .form-group {
        flex-direction: column;
        width: 100%;
    }

    .btn,
    .btn-submit,
    .btn-add,
    .btn-remove {
        width: 100%;
        margin-top: 8px;
    }
}

        
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="../dashboard.php" class="logo"><span class="model">Model</span> Pharmacy</a>
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
        
        <div class="card">
            <div class="card-header">
                <h1>🛒 New Sale / Billing</h1>
                <p>Process a new sale transaction</p>
            </div>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <h3>✅ Sale Completed Successfully!</h3>
                    <p>Invoice Number: <strong><?php echo $invoice_number; ?></strong></p>
                    <p>Total Amount: <strong>Br<?php echo number_format($total_amount, 2); ?></strong></p>
                    <p><strong>✅ Stock has been updated for all medicines</strong></p>
                </div>
                
                <!-- PRINT INSTRUCTIONS - ADD THIS -->
                <div class="print-instructions">
                    <h4>🖨️ How to Save as PDF:</h4>
                    <ol>
                        <li>Click "Print Receipt" button below</li>
                        <li>In the print dialog, change <strong>Destination</strong> to "Save as PDF"</li>
                        <li>Select <strong>Layout: Portrait</strong> and <strong>Paper Size: A4</strong></li>
                        <li>Uncheck "Headers and footers" option</li>
                        <li>Click "Save" and choose location</li>
                    </ol>
                    <p style="margin: 10px 0 0 0; font-size: 14px;">
                        <strong>Shortcut:</strong> Press <kbd>Ctrl</kbd> + <kbd>P</kbd> (Windows) or <kbd>Cmd</kbd> + <kbd>P</kbd> (Mac)
                    </p>
                </div>
                
                <!-- Professional Receipt - ADD THIS -->
                <div class="receipt-container" id="receipt">
                    <div class="receipt">
                        <!-- Receipt Header -->
                        <div class="receipt-header">
                            <div class="pharmacy-logo">
                                <h1>🏥 Model Pharmacy</h1>
                                <p>Your Health, Our Priority</p>
                            </div>
                            <div class="receipt-title">
                                <h2>TAX INVOICE</h2>
                                <p class="invoice-number">#<?php echo $invoice_number; ?></p>
                            </div>
                        </div>
                        
                        <!-- Pharmacy Info -->
                        <div class="pharmacy-info">
                            <p><strong>Address:</strong> 123 Medical Street, Jimma, Ethiopia</p>
                            <p><strong>Phone:</strong> +251 11 123 4567 | <strong>Email:</strong> info@medicarepharmacy.com</p>
                            <p><strong>VAT Registration:</strong> VAT123456789</p>
                        </div>
                        
                        <!-- Receipt Details -->
                        <div class="receipt-details">
                            <div class="customer-info">
                                <h3>Bill To:</h3>
                                <p><strong>Name:</strong> <?php echo !empty($customer_name) ? htmlspecialchars($customer_name) : 'Walk-in Customer'; ?></p>
                                <?php if(!empty($customer_phone)): ?>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($customer_phone); ?></p>
                                <?php endif; ?>
                                <p><strong>Date:</strong> <?php echo date('d F Y, h:i A'); ?></p>
                            </div>
                            
                            <div class="sale-info">
                                <p><strong>Invoice #:</strong> <?php echo $invoice_number; ?></p>
                                <p><strong>Cashier:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                                <p><strong>Payment Mode:</strong> Cash</p>
                            </div>
                        </div>
                        
                        <!-- Items Table -->
                        <div class="receipt-items">
                            <table class="receipt-table">
                                <thead>
                                    <tr>
                                        <th class="text-left">S.N.</th>
                                        <th class="text-left">Description</th>
                                        <th class="text-right">Qty</th>
                                        <th class="text-right">Unit Price</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($sale_items as $index => $item): ?>
                                    <tr>
                                        <td class="text-left"><?php echo $index + 1; ?></td>
                                        <td class="text-left"><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td class="text-right"><?php echo $item['quantity']; ?></td>
                                        <td class="text-right">Br<?php echo number_format($item['price'], 2); ?></td>
                                        <td class="text-right">Br<?php echo number_format($item['total'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Totals Section -->
                        <div class="totals-section">
                            <table class="totals-table">
                                <tr>
                                    <td class="text-left">Subtotal:</td>
                                    <td class="text-right">Br<?php echo number_format($total_amount, 2); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-left">VAT (15%):</td>
                                    <td class="text-right">Br<?php echo number_format($total_amount * 0.15, 2); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-left">Discount:</td>
                                    <td class="text-right">Br0.00</td>
                                </tr>
                                <tr class="grand-total">
                                    <td class="text-left"><strong>GRAND TOTAL:</strong></td>
                                    <td class="text-right"><strong>Br<?php echo number_format($total_amount * 1.15, 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="text-left">Amount Paid:</td>
                                    <td class="text-right">Br<?php echo number_format($total_amount * 1.15, 2); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-left">Change:</td>
                                    <td class="text-right">Br0.00</td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Footer Messages -->
                        <div class="receipt-footer">
                            <div class="thank-you">
                                <p>🩺 <strong>Thank you for your purchase!</strong></p>
                                <p>We care about your health. Please take medications as prescribed by your doctor.</p>
                            </div>
                            
                            <div class="important-notes">
                                <p><strong>Important Notes:</strong></p>
                                <ul>
                                    <li>Goods once sold cannot be returned or exchanged</li>
                                    <li>Keep this receipt for warranty purposes</li>
                                    <li>Store medicines in a cool, dry place away from sunlight</li>
                                    <li>Consult your doctor before taking any medication</li>
                                </ul>
                            </div>
                            
                            <div class="footer-contact">
                                <p>For inquiries: ☎️ +251 11 123 4567 | 📧 info@modelpharmacy.com</p>
                                <p>Open: Mon-Sat 8:00 AM - 10:00 PM | Sun 9:00 AM - 8:00 PM</p>
                            </div>
                            
                            <div class="signature">
                                <p style="margin-top: 30px; border-top: 1px dashed #666; padding-top: 10px;">
                                    <strong>Authorized Signature:</strong> _________________________
                                </p>
                                <p style="text-align: center; font-size: 12px; color: #666; margin-top: 10px;">
                                    This is a computer-generated receipt. No signature required.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons - ADD THIS -->
                <div class="receipt-actions">
                    <button onclick="printReceipt()" class="print-btn" title="Press Ctrl+P">
                        🖨️ Print Receipt (Save as PDF)
                    </button>
                    <a href="create.php" class="new-sale-btn">
                        ➕ Create Another Sale
                    </a>
                    <a href="index.php" class="back-btn">
                        📋 Back to Sales Report
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Your existing form code remains here -->
                <?php if(!empty($errors)): ?>
                    <div class="alert alert-error">
                        <strong>Please fix the following errors:</strong>
                        <ul>
                            <?php foreach($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="saleForm">
                    <!-- Customer Information -->
                    <div class="form-section">
                        <h2 class="section-title">Customer Information (Optional)</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="customer_name">Customer Name</label>
                                <input type="text" id="customer_name" name="customer_name" 
                                       value="<?php echo isset($_POST['customer_name']) ? htmlspecialchars($_POST['customer_name']) : ''; ?>" 
                                       placeholder="Walk-in Customer">
                            </div>
                            <div class="form-group">
                                <label for="customer_phone">Phone Number</label>
                                <input type="text" id="customer_phone" name="customer_phone" 
                                       value="<?php echo isset($_POST['customer_phone']) ? htmlspecialchars($_POST['customer_phone']) : ''; ?>" 
                                       placeholder="Optional">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Medicines Selection -->
                    <div class="form-section">
                        <h2 class="section-title">Medicines</h2>
                        
                        <table class="medicines-table" id="medicinesTable">
                            <thead>
                                <tr>
                                    <th width="40%">Medicine</th>
                                    <th width="15%">Available</th>
                                    <th width="15%">Quantity</th>
                                    <th width="15%">Price (Birr)</th>
                                    <th width="10%">Total (Birr)</th>
                                    <th width="5%">Action</th>
                                </tr>
                            </thead>
                            <tbody id="medicineRows">
                                <!-- First row -->
                                <tr class="medicine-row">
                                    <td>
                                        <select name="medicines[]" class="medicine-select" onchange="updateMedicineInfo(this)" required>
                                            <option value="">-- Select Medicine --</option>
                                            <?php foreach($medicines as $med): 
                                                $is_expired = !empty($med['expiry_date']) && strtotime($med['expiry_date']) < time();
                                                $expiry_text = '';
                                                
                                                if(!empty($med['expiry_date'])) {
                                                    $expiry_date = date('d M Y', strtotime($med['expiry_date']));
                                                    $days_left = floor((strtotime($med['expiry_date']) - time()) / (60 * 60 * 24));
                                                    
                                                    if($days_left < 0) {
                                                        $expiry_text = " (EXPIRED!)";
                                                    } elseif($days_left <= 30) {
                                                        $expiry_text = " (Expires in $days_left days)";
                                                    }
                                                }
                                            ?>
                                                <option value="<?php echo $med['id']; ?>" 
                                                        data-price="<?php echo $med['price']; ?>"
                                                        data-stock="<?php echo $med['quantity']; ?>"
                                                        data-expiry="<?php echo $med['expiry_date'] ?? ''; ?>"
                                                        class="<?php echo $is_expired ? 'expired-option' : ''; ?>"
                                                        <?php echo $is_expired ? 'disabled' : ''; ?>>
                                                    <?php echo htmlspecialchars($med['name']); ?>
                                                    - Stock: <?php echo $med['quantity']; ?>
                                                    - Br<?php echo number_format($med['price'], 2); ?>
                                                    <?php echo $expiry_text; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="stock-info" id="stockInfo-0">Stock: -</div>
                                        <div class="expired-warning" id="expiryWarning-0"></div>
                                    </td>
                                    <td class="available-stock">-</td>
                                    <td>
                                        <input type="number" name="quantities[]" min="1" value="1" 
                                               onchange="calculateRowTotal(this)" oninput="calculateRowTotal(this)" required>
                                    </td>
                                    <td>
                                        <input type="number" name="prices[]" step="0.01" min="0.01" 
                                               onchange="calculateRowTotal(this)" oninput="calculateRowTotal(this)" required>
                                    </td>
                                    <td class="row-total">0.00</td>
                                    <td>
                                        <button type="button" class="btn-remove" onclick="removeRow(this)">✕</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <button type="button" class="btn-add" onclick="addMedicineRow()" style="margin-top: 15px;">＋ Add Another Medicine</button>
                    </div>
                    
                    <!-- Summary -->
                    <div class="summary-box">
                        <h3>Sale Summary</h3>
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span id="subtotal">Br0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Tax (0%):</span>
                            <span id="tax">Br0.00</span>
                        </div>
                        <div class="summary-row total-amount">
                            <span>Total Amount:</span>
                            <span id="grandTotal">Br0.00</span>
                        </div>
                        <input type="hidden" name="total_amount" id="totalAmount" value="0">
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="index.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-submit">💳 Process Sale</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        let medicineData = <?php echo json_encode($medicines); ?>;
        let rowCounter = 1;
        
        // Initialize first row
        document.addEventListener('DOMContentLoaded', function() {
            updateMedicineInfo(document.querySelector('.medicine-select'));
            calculateGrandTotal();
        });
        
        function addMedicineRow() {
            const tbody = document.getElementById('medicineRows');
            const newRow = document.createElement('tr');
            newRow.className = 'medicine-row';
            newRow.innerHTML = `
                <td>
                    <select name="medicines[]" class="medicine-select" onchange="updateMedicineInfo(this)" required>
                        <option value="">-- Select Medicine --</option>
                        ${medicineData.map(med => {
                            const is_expired = med.expiry_date && new Date(med.expiry_date) < new Date();
                            const expiry_text = '';
                            
                            if(med.expiry_date) {
                                const expiryDate = new Date(med.expiry_date);
                                const today = new Date();
                                const daysLeft = Math.floor((expiryDate - today) / (1000 * 60 * 60 * 24));
                                
                                if(daysLeft < 0) {
                                    expiry_text = " (EXPIRED!)";
                                } else if(daysLeft <= 30) {
                                    expiry_text = " (Expires in " + daysLeft + " days)";
                                }
                            }
                            
                            return `<option value="${med.id}" 
                                    data-price="${med.price}"
                                    data-stock="${med.quantity}"
                                    data-expiry="${med.expiry_date || ''}"
                                    class="${is_expired ? 'expired-option' : ''}"
                                    ${is_expired ? 'disabled' : ''}>
                                ${med.name} - Stock: ${med.quantity} - Br${parseFloat(med.price).toFixed(2)}${expiry_text}
                            </option>`;
                        }).join('')}
                    </select>
                    <div class="stock-info" id="stockInfo-${rowCounter}">Stock: -</div>
                    <div class="expired-warning" id="expiryWarning-${rowCounter}"></div>
                </td>
                <td class="available-stock">-</td>
                <td>
                    <input type="number" name="quantities[]" min="1" value="1" 
                           onchange="calculateRowTotal(this)" oninput="calculateRowTotal(this)" required>
                </td>
                <td>
                    <input type="number" name="prices[]" step="0.01" min="0.01" 
                           onchange="calculateRowTotal(this)" oninput="calculateRowTotal(this)" required>
                </td>
                <td class="row-total">0.00</td>
                <td>
                    <button type="button" class="btn-remove" onclick="removeRow(this)">✕</button>
                </td>
            `;
            tbody.appendChild(newRow);
            rowCounter++;
        }
        
        function removeRow(button) {
            const rows = document.querySelectorAll('.medicine-row');
            if(rows.length > 1) {
                button.closest('tr').remove();
                calculateGrandTotal();
            } else {
                alert('At least one medicine is required for a sale.');
            }
        }
        
        function updateMedicineInfo(select) {
            const row = select.closest('tr');
            const rowIndex = Array.from(row.parentNode.children).indexOf(row);
            const selectedOption = select.options[select.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            const stock = selectedOption.getAttribute('data-stock');
            const expiry = selectedOption.getAttribute('data-expiry');
            
            // Update available stock display
            row.querySelector('.available-stock').textContent = stock || '0';
            
            // Update stock info
            const stockInfo = document.getElementById('stockInfo-' + rowIndex) || row.querySelector('.stock-info');
            if(stockInfo) {
                stockInfo.textContent = 'Stock: ' + (stock || '0');
                
                // Show low stock warning
                if(stock && parseInt(stock) <= 10) {
                    stockInfo.innerHTML = 'Stock: <span class="low-stock">' + stock + ' (LOW STOCK)</span>';
                }
            }
            
            // Update price field
            const priceInput = row.querySelector('input[name="prices[]"]');
            if(price && !priceInput.value) {
                priceInput.value = parseFloat(price).toFixed(2);
            }
            
            // Check expiry
            const expiryWarning = document.getElementById('expiryWarning-' + rowIndex) || row.querySelector('.expired-warning');
            if(expiryWarning && expiry) {
                const expiryDate = new Date(expiry);
                const today = new Date();
                const daysLeft = Math.floor((expiryDate - today) / (1000 * 60 * 60 * 24));
                
                if(daysLeft < 0) {
                    expiryWarning.textContent = '⚠️ WARNING: This medicine is EXPIRED!';
                    expiryWarning.style.color = '#dc3545';
                    select.value = ''; // Clear selection if expired
                    alert('Cannot select expired medicine!');
                } else if(daysLeft <= 30) {
                    expiryWarning.textContent = '⚠️ WARNING: Expires in ' + daysLeft + ' days';
                    expiryWarning.style.color = '#ffc107';
                } else {
                    expiryWarning.textContent = '';
                }
            }
            
            // Update quantity max limit
            const quantityInput = row.querySelector('input[name="quantities[]"]');
            if(stock) {
                quantityInput.max = stock;
            }
            
            // Calculate row total
            calculateRowTotal(select);
        }
        
        function calculateRowTotal(input) {
            const row = input.closest('tr');
            const quantity = row.querySelector('input[name="quantities[]"]').value || 0;
            const price = row.querySelector('input[name="prices[]"]').value || 0;
            const total = parseFloat(quantity) * parseFloat(price);
            
            row.querySelector('.row-total').textContent = 'Br' + total.toFixed(2);
            calculateGrandTotal();
        }
        
        function calculateGrandTotal() {
            let subtotal = 0;
            const rows = document.querySelectorAll('.medicine-row');
            
            rows.forEach(row => {
                const totalText = row.querySelector('.row-total').textContent;
                const total = parseFloat(totalText.replace('Br', '')) || 0;
                subtotal += total;
            });
            
            const tax = 0;
            const grandTotal = subtotal + tax;
            
            document.getElementById('subtotal').textContent = 'Br' + subtotal.toFixed(2);
            document.getElementById('tax').textContent = 'Br' + tax.toFixed(2);
            document.getElementById('grandTotal').textContent = 'Br' + grandTotal.toFixed(2);
            document.getElementById('totalAmount').value = grandTotal.toFixed(2);
        }
        
        // Form validation
        document.getElementById('saleForm').addEventListener('submit', function(e) {
            const medicineSelects = document.querySelectorAll('select[name="medicines[]"]');
            let hasMedicine = false;
            let valid = true;
            
            medicineSelects.forEach((select, index) => {
                if(select.value) hasMedicine = true;
                
                // Check expiry for each medicine
                const selectedOption = select.options[select.selectedIndex];
                if(selectedOption.value) {
                    const expiry = selectedOption.getAttribute('data-expiry');
                    if(expiry) {
                        const expiryDate = new Date(expiry);
                        const today = new Date();
                        if(expiryDate < today) {
                            alert('Cannot sell expired medicine: ' + selectedOption.text);
                            valid = false;
                        }
                    }
                    
                    // Check quantity
                    const row = select.closest('tr');
                    const quantityInput = row.querySelector('input[name="quantities[]"]');
                    const availableStock = parseInt(selectedOption.getAttribute('data-stock') || 0);
                    const quantity = parseInt(quantityInput.value) || 0;
                    
                    if(quantity > availableStock) {
                        alert('Quantity exceeds available stock for: ' + selectedOption.text);
                        valid = false;
                    }
                }
            });
            
            if(!hasMedicine) {
                alert('Please add at least one medicine to the sale.');
                valid = false;
            }
            
            if(!valid) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        // PRINT FUNCTION - ADD THIS
        function printReceipt() {
            // Show instructions
            if (confirm('To save as PDF:\n1. Click OK to open print dialog\n2. Change "Destination" to "Save as PDF"\n3. Select "A4" paper size\n4. Click "Save"')) {
                // Focus on receipt
                document.getElementById('receipt').scrollIntoView();
                
                // Small delay to ensure rendering
                setTimeout(() => {
                    window.print();
                }, 100);
            }
        }
        
        // Add keyboard shortcut for printing
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printReceipt();
            }
        });
        
        // Add print button tooltip
        document.querySelector('.print-btn').title = 'Press Ctrl+P to print/save as PDF';
    </script>
</body>
</html>