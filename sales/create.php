<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

// Fetch medicines for dropdown
$medicineStmt = $pdo->query("SELECT id, name, price, quantity FROM medicines WHERE quantity > 0 ORDER BY name");
$medicines = $medicineStmt->fetchAll();

$errors = [];
$success = false;
$invoice_number = '';
$sale_id = null;

// Generate invoice number
function generateInvoiceNumber() {
    return 'INV-' . date('Ymd-His') . '-' . random_int(100, 999);
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
            
            // Check stock
            $stmt = $pdo->prepare("SELECT name, quantity, price as actual_price FROM medicines WHERE id = ?");
            $stmt->execute([$medicine_id]);
            $medicine = $stmt->fetch();
            
            if(!$medicine) {
                $errors[] = "Medicine not found";
                break;
            }
            
            if($medicine['quantity'] < $quantity) {
                $errors[] = "Insufficient stock for {$medicine['name']}. Available: {$medicine['quantity']}";
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
                'available_stock' => $medicine['quantity']
            ];
        }
    }
    
    // If no errors, process sale
    if(empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Get first medicine (required for FK)
$first_medicine_id = $sale_items[0]['medicine_id'];
$first_quantity    = $sale_items[0]['quantity'];

// Generate invoice number
$invoice_number = generateInvoiceNumber($pdo);

// Check invoice number
if ($invoice_number === '' || $invoice_number === null) {
    throw new Exception('Invoice number generation failed');
}

// Insert sale record WITH medicine_id
$sale_sql = "
INSERT INTO sales 
(invoice_number, customer_name, customer_phone, medicine_id, quantity_sold, total_price, sold_by)
VALUES (?, ?, ?, ?, ?, ?, ?)
";


$sale_stmt = $pdo->prepare($sale_sql);
$sale_stmt->execute([
    $invoice_number,
    $customer_name ?: null,
    $customer_phone ?: null,
    $first_medicine_id,   // ✅ FK satisfied here
    $first_quantity,
    $total_amount,
    $_SESSION['user_id']
]);

$sale_id = $pdo->lastInsertId();

            $pdo->commit();
            $success = true;
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Transaction failed: " . $e->getMessage();
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
        
        /* Success Receipt */
        .receipt {
            background: white;
            border: 2px solid #28a745;
            border-radius: 10px;
            padding: 30px;
            margin-top: 30px;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            color: #28a745;
        }
        
        .receipt-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .receipt-items {
            margin-top: 20px;
        }
        
        .print-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
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
        
        <div class="card">
            <div class="card-header">
                <h1>🛒 New Sale / Billing</h1>
                <p>Process a new sale transaction</p>
            </div>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <h3>✅ Sale Completed Successfully!</h3>
                    <p>Invoice Number: <strong><?php echo $invoice_number; ?></strong></p>
                    <p>Total Amount: <strong>₹<?php echo number_format($total_amount, 2); ?></strong></p>
                    <p>
                        <a href="view.php?id=<?php echo $sale_id; ?>" style="color: #155724; text-decoration: underline;">View Invoice</a> |
                        <a href="create.php" style="color: #155724; text-decoration: underline;">Create Another Sale</a>
                    </p>
                </div>
                
                <!-- Receipt Preview -->
                <div class="receipt">
                    <div class="receipt-header">
                        <h2>Pharmacy Management System</h2>
                        <p>Sale Receipt</p>
                    </div>
                    
                    <div class="receipt-details">
                        <div>
                            <p><strong>Invoice No:</strong> <?php echo $invoice_number; ?></p>
                            <p><strong>Date:</strong> <?php echo date('d M Y, h:i A'); ?></p>
                        </div>
                        <div>
                            <?php if(!empty($customer_name)): ?>
                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($customer_name); ?></p>
                            <?php endif; ?>
                            <?php if(!empty($customer_phone)): ?>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($customer_phone); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="receipt-items">
                        <table class="medicines-table">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($sale_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>Br<?php echo number_format($item['price'], 2); ?></td>
                                        <td>Br<?php echo number_format($item['total'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="summary-box" style="background: #f8f9fa; color: #333; margin-top: 20px;">
                            <div class="summary-row total-amount" style="justify-content: flex-end;">
                                <span>Grand Total: Br</Br><?php echo number_format($total_amount, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <button onclick="window.print()" class="print-btn">🖨️ Print Receipt</button>
                </div>
            <?php else: ?>
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
                                <!-- Rows will be added dynamically -->
                                <tr class="medicine-row">
                                    <td>
                                        <select name="medicines[]" class="medicine-select" onchange="updateMedicineInfo(this)" required>
                                            <option value="">-- Select Medicine --</option>
                                            <?php foreach($medicines as $med): ?>
                                                <option value="<?php echo $med['id']; ?>" 
                                                        data-price="<?php echo $med['price']; ?>"
                                                        data-stock="<?php echo $med['quantity']; ?>">
                                                    <?php echo htmlspecialchars($med['name']); ?> (₹<?php echo number_format($med['price'], 2); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="available-stock">-</td>
                                    <td>
                                        <input type="number" name="quantities[]" min="1" value="1" 
                                               onchange="calculateRowTotal(this)" oninput="calculateRowTotal(this)" required>
                                    </td>
                                    <td>
                                        <input type="number" name="prices[]" step="0.01" min="0" 
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
                        ${medicineData.map(med => 
                            `<option value="${med.id}" 
                                    data-price="${med.price}"
                                    data-stock="${med.quantity}">
                                ${med.name} (Br${parseFloat(med.price).toFixed(2)})
                            </option>`
                        ).join('')}
                    </select>
                </td>
                <td class="available-stock">-</td>
                <td>
                    <input type="number" name="quantities[]" min="1" value="1" 
                           onchange="calculateRowTotal(this)" oninput="calculateRowTotal(this)" required>
                </td>
                <td>
                    <input type="number" name="prices[]" step="0.01" min="0" 
                           onchange="calculateRowTotal(this)" oninput="calculateRowTotal(this)" required>
                </td>
                <td class="row-total">0.00</td>
                <td>
                    <button type="button" class="btn-remove" onclick="removeRow(this)">✕</button>
                </td>
            `;
            tbody.appendChild(newRow);
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
            const selectedOption = select.options[select.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            const stock = selectedOption.getAttribute('data-stock');
            
            // Update available stock
            row.querySelector('.available-stock').textContent = stock || '0';
            
            // Update price field
            const priceInput = row.querySelector('input[name="prices[]"]');
            if(price && !priceInput.value) {
                priceInput.value = parseFloat(price).toFixed(2);
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
            
            const tax = 0; // You can add tax calculation here
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
            
            medicineSelects.forEach(select => {
                if(select.value) hasMedicine = true;
            });
            
            if(!hasMedicine) {
                e.preventDefault();
                alert('Please add at least one medicine to the sale.');
                return false;
            }
            
            // Validate quantities
            const quantityInputs = document.querySelectorAll('input[name="quantities[]"]');
            let valid = true;
            
            quantityInputs.forEach(input => {
                const row = input.closest('tr');
                const select = row.querySelector('select[name="medicines[]"]');
                
                if(select.value) {
                    const availableStock = parseInt(row.querySelector('.available-stock').textContent) || 0;
                    const quantity = parseInt(input.value) || 0;
                    
                    if(quantity <= 0) {
                        alert('Quantity must be greater than 0');
                        valid = false;
                    } else if(quantity > availableStock) {
                        alert('Quantity exceeds available stock');
                        valid = false;
                    }
                }
            });
            
            if(!valid) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>


