<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$errors = [];
$success = false;

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Validation
    if(empty($name)) {
        $errors[] = "Supplier name is required";
    }
    
    if(!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address";
    }
    
    // If no errors, insert into database
    if(empty($errors)) {
        try {
            $sql = "INSERT INTO suppliers (name, email, phone, address) 
                    VALUES (?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $name,
                $email ?: null,
                $phone ?: null,
                $address ?: null
            ]);
            
            $success = true;
            
            // Clear form on success
            if($success) {
                $name = $email = $phone = $address = '';
            }
            
        } catch(PDOException $e) {
            // Check if it's a duplicate entry error
            if(strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $errors[] = "A supplier with this name or email already exists";
            } else {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Supplier - Model Pharmacy</title>
    <style>
        /* Reuse styles from medicine add.php */
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
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 14px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group input:focus, .form-group textarea:focus {
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
        <a href="../dashboard.php" class="logo">Model Pharmacy</a>
        <div class="nav-links">
            <a href="../dashboard.php">Dashboard</a>
            <a href="../medicines/">Medicines</a>
            <a href="../sales/">Sales</a>
            <a href="index.php">Suppliers</a>
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
        <a href="index.php" class="back-link">← Back to Suppliers List</a>
        
        <div class="card">
            <div class="card-header">
                <h1>🏢 Add New Supplier</h1>
                <p>Fill in the supplier details below</p>
            </div>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    ✅ Supplier added successfully! 
                    <a href="index.php" style="color: #155724; text-decoration: underline;">View all suppliers</a>
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
                    <label for="name">Supplier Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" 
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                           required placeholder="e.g., Sun Pharmaceutical">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               placeholder="e.g., contact@supplier.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                               placeholder="e.g., +91-22-4324-4324">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" 
                              placeholder="Enter full address..."><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-submit">Add Supplier</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on first input
            document.getElementById('name').focus();
            
            // Phone number formatting
            document.getElementById('phone').addEventListener('blur', function() {
                // Simple phone validation
                const phone = this.value.trim();
                if(phone && !/^[\d\s\-\+\(\)]+$/.test(phone)) {
                    alert('Please enter a valid phone number');
                    this.focus();
                }
            });
        });
    </script>
</body>
</html>