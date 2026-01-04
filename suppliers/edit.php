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

// Fetch supplier details
$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$id]);
$supplier = $stmt->fetch();

if(!$supplier) {
    header('Location: index.php');
    exit;
}

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
    
    // If no errors, update database
    if(empty($errors)) {
        try {
            $sql = "UPDATE suppliers SET 
                    name = ?, 
                    email = ?, 
                    phone = ?, 
                    address = ? 
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $name,
                $email ?: null,
                $phone ?: null,
                $address ?: null,
                $id
            ]);
            
            $success = true;
            
            // Refresh supplier data
            $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            $supplier = $stmt->fetch();
            
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
    <title>Edit Supplier - Pharmacy Management</title>
    <style>
        /* Same styles as add.php */
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
        
        .supplier-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            color: #666;
        }
        
        .supplier-info strong {
            color: #333;
        }
        @media (max-width: 992px) {
    /* Navbar stacking */
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

    /* User info and logout stacking */
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

    .logout-btn {
        width: 100%;
        text-align: center;
        padding: 14px;
        background: linear-gradient(135deg, #ff3b3b, #c70000);
        color: white !important;
        border-radius: 12px;
        font-weight: 600;
        box-shadow: 0 4px 10px rgba(0,0,0,0.25);
    }

    .logout-btn:active {
        transform: scale(0.96);
    }

    /* Card adjustments */
    .card {
        padding: 30px 20px;
    }

    .card-header h1 {
        font-size: 24px;
    }

    .card-header p {
        font-size: 14px;
    }

    .supplier-info {
        font-size: 13px;
    }

    /* Form row stacking */
    .form-row {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .form-group input,
    .form-group textarea {
        padding: 12px;
        font-size: 14px;
    }

    .btn-submit, .btn-cancel {
        padding: 14px;
        font-size: 14px;
    }

    .form-actions {
        flex-direction: column;
        gap: 10px;
    }
}

@media (max-width: 768px) {
    .container {
        padding: 0 15px;
    }

    .back-link {
        font-size: 14px;
    }

    .card-header h1 {
        font-size: 22px;
    }

    .card-header p {
        font-size: 13px;
    }

    .supplier-info {
        font-size: 12px;
    }

    .form-group input,
    .form-group textarea {
        padding: 10px;
        font-size: 14px;
    }

    .btn-submit, .btn-cancel {
        font-size: 14px;
        padding: 12px;
    }
}

@media (max-width: 480px) {
    /* Super small screens adjustments */
    .card {
        padding: 20px 15px;
    }

    .card-header h1 {
        font-size: 20px;
    }

    .card-header p {
        font-size: 12px;
    }

    .form-group input, 
    .form-group textarea {
        font-size: 13px;
        padding: 10px;
    }

    .btn-submit, .btn-cancel {
        font-size: 13px;
        padding: 12px;
    }

    .form-actions {
        flex-direction: column;
        gap: 8px;
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
                <h1>✏️ Edit Supplier</h1>
                <p>Update the details of supplier #<?php echo $supplier['id']; ?></p>
            </div>
            
            <div class="supplier-info">
                Supplier ID: <strong>#<?php echo $supplier['id']; ?></strong> | 
                Added on: <strong><?php echo date('d M Y', strtotime($supplier['created_at'])); ?></strong>
            </div>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    ✅ Supplier updated successfully!
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
                           value="<?php echo htmlspecialchars($supplier['name']); ?>" 
                           required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($supplier['phone'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address"><?php echo htmlspecialchars($supplier['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-submit">Update Supplier</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>