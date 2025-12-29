<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

// Fetch suppliers for dropdown
$supplierStmt = $pdo->query("SELECT id, name FROM suppliers ORDER BY name");
$suppliers = $supplierStmt->fetchAll();

$errors = [];
$success = false;

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $price = trim($_POST['price']);
    $quantity = trim($_POST['quantity']);
    $expiry_date = trim($_POST['expiry_date']);
    $supplier_id = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null;
    
    // Validation
    if(empty($name)) {
        $errors[] = "Medicine name is required";
    }
    
    if(empty($price) || !is_numeric($price) || $price <= 0) {
        $errors[] = "Valid price is required";
    }
    
    if(empty($quantity) || !is_numeric($quantity) || $quantity < 0) {
        $errors[] = "Valid quantity is required";
    }
    
    if(!empty($expiry_date) && strtotime($expiry_date) === false) {
        $errors[] = "Invalid expiry date format";
    }
    
    // If no errors, insert into database
    if(empty($errors)) {
        try {
            $sql = "INSERT INTO medicines (name, category, price, quantity, expiry_date, supplier_id) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $name,
                $category ?: null,
                $price,
                $quantity,
                $expiry_date ?: null,
                $supplier_id
            ]);
            
            $success = true;
            
            // Clear form on success
            if($success) {
                $name = $category = $price = $quantity = $expiry_date = '';
                $supplier_id = null;
            }
            
        } catch(PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Medicine - Pharmacy Management</title>
    <style>
        /* Reuse previous styles */
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
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 14px;
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
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
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
            <a href="index.php">Medicines</a>
            <a href="../sales/">Sales</a>
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
        <a href="index.php" class="back-link">← Back to Medicines List</a>
        
        <div class="card">
            <div class="card-header">
                <h1>➕ Add New Medicine</h1>
                <p>Fill in the details below to add a new medicine to inventory</p>
            </div>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    ✅ Medicine added successfully! 
                    <a href="index.php" style="color: #155724; text-decoration: underline;">View all medicines</a>
                </div>
            <?php endif; ?>
            
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
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Medicine Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" 
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                           required placeholder="e.g., Paracetamol 500mg">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" 
                               value="<?php echo isset($_POST['category']) ? htmlspecialchars($_POST['category']) : ''; ?>" 
                               placeholder="e.g., Tablet, Syrup, Injection">
                        <div class="hint">Common categories: Tablet, Capsule, Syrup, Injection, Ointment</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Price (Birr) <span class="required">*</span></label>
                        <input type="number" id="price" name="price" 
                               value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" 
                               required min="0" step="0.01" placeholder="0.00">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity">Quantity in Stock <span class="required">*</span></label>
                        <input type="number" id="quantity" name="quantity" 
                               value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : ''; ?>" 
                               required min="0" placeholder="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="expiry_date">Expiry Date</label>
                        <input type="date" id="expiry_date" name="expiry_date" 
                               value="<?php echo isset($_POST['expiry_date']) ? htmlspecialchars($_POST['expiry_date']) : ''; ?>">
                        <div class="hint">Leave empty if no expiry date</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="supplier_id">Supplier (Optional)</label>
                    <select id="supplier_id" name="supplier_id">
                        <option value="">-- Select Supplier --</option>
                        <?php foreach($suppliers as $supplier): ?>
                            <option value="<?php echo htmlspecialchars($supplier['id']); ?>"
                                <?php echo (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">If supplier not listed, <a href="../suppliers/add.php">add new supplier first</a></div>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-submit">Add Medicine</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date to today for expiry date
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('expiry_date').min = today;
            
            // Focus on first input
            document.getElementById('name').focus();
            
            // Auto-calculate price with decimals
            document.getElementById('price').addEventListener('blur', function() {
                if(this.value) {
                    this.value = parseFloat(this.value).toFixed(2);
                }
            });
        });
    </script>
</body>
</html>