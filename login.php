<?php
session_start();
require_once 'config/database.php';

// Check if user is already logged in
if(isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if(empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Prepare SQL statement to prevent SQL injection
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        // Verify password
        if($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Management - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
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
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #fcc;
        }
        
        .warnings {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 14px;
            color: #555;
        }
        
        .warnings p {
            margin-bottom: 10px;
            font-weight: 500;
            color: #ee1010ff;
            padding-left: 65px;
        }
         .warnings h3{
            font-size: 12px;
            padding-left: 8px;
            color: grey;
            opacity:0.5;
         }
          .model{
           color:red;
           font-weight:600px;
        }
        /* ================= LOGIN PAGE MOBILE RESPONSIVE ================= */
@media (max-width: 768px) {

    body {
        padding: 10px;
        height: auto;
    }

    .login-container {
        padding: 25px 20px;
        width: 100%;
        max-width: 350px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }

    .login-header h1 {
        font-size: 22px;
    }

    .login-header p {
        font-size: 14px;
    }

    .form-group label {
        font-size: 14px;
    }

    .form-group input {
        padding: 10px 12px;
        font-size: 14px;
    }

    .btn-login {
        padding: 12px;
        font-size: 15px;
    }

    .error-message {
        font-size: 13px;
        padding: 8px;
    }

    .warnings {
        font-size: 13px;
        padding: 10px;
    }

    .warnings p {
        padding-left: 10px;
        font-size: 13px;
    }

    .warnings h3 {
        font-size: 11px;
        padding-left: 5px;
    }

    /* Optional: auto-focus username on mobile */
    input#username {
        font-size: 14px;
    }
}

/* ================= VERY SMALL DEVICES (PHONES) ================= */
@media (max-width: 480px) {
    .login-container {
        padding: 20px 15px;
    }

    .login-header h1 {
        font-size: 20px;
    }

    .btn-login {
        font-size: 14px;
        padding: 10px;
    }

    .form-group input {
        font-size: 13px;
        padding: 10px;
    }

    .error-message, .warnings {
        font-size: 12px;
        padding: 8px;
    }
}
/* ===== LOGIN BOX HOVER LIFT ===== */
.login-container {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.login-container:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.25);
}

/* ===== SHAKE EFFECT FOR INCORRECT LOGIN ===== */
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-5px); }
    40%, 80% { transform: translateX(5px); }
}

.error-shake {
    animation: shake 0.5s;
    border: 1px solid #c33;
}

/* ================= LOGIN PAGE MOBILE RESPONSIVE + INTERACTIVE ================= */
@media (max-width: 768px) {

    body {
        margin-top:54px;
        padding: 10px;
        height: auto;
        background: linear-gradient(135deg, #667eea);
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }

    .login-container {
        padding: 25px 20px;
        width: 100%;
        max-width: 360px;
        box-shadow: 0 12px 30px rgba(0,0,0,0.2);
        border-radius: 15px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .login-container:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.25);
    }

    .login-header h1 {
        font-size: 22px;
        text-align: center;
    }

    .login-header p {
        font-size: 14px;
        text-align: center;
    }

    .form-group label {
        font-size: 14px;
    }

    .form-group input {
        padding: 12px 15px;
        font-size: 15px;
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .form-group input:focus {
        border-color: #764ba2;
        box-shadow: 0 0 10px rgba(118, 75, 162, 0.3);
    }

    .btn-login {
        width: 100%;
        padding: 14px;
        font-size: 16px;
        border-radius: 10px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        transition: all 0.3s ease;
    }

    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(102, 126, 234, 0.4);
    }

    .error-message {
        font-size: 13px;
        padding: 10px;
        border-radius: 8px;
    }

    .warnings {
        font-size: 13px;
        padding: 12px;
        text-align: center;
    }

    .warnings p {
        padding-left: 0;
        font-size: 13px;
    }

    .warnings h3 {
        font-size: 11px;
    }
}

/* ================= VERY SMALL PHONES ================= */
@media (max-width: 480px) {
    .login-container {
        padding: 20px 15px;
        max-width: 320px;
    }

    .login-header h1 {
        font-size: 20px;
    }

    .login-header p {
        font-size: 13px;
    }

    .form-group input {
        font-size: 14px;
        padding: 10px 12px;
    }

    .btn-login {
        font-size: 15px;
        padding: 12px;
    }

    .error-message, .warnings {
        font-size: 12px;
        padding: 8px;
    }
}

/* ================= OPTIONAL SHAKE EFFECT FOR ERROR ================= */
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-5px); }
    40%, 80% { transform: translateX(5px); }
}

.error-shake {
    animation: shake 0.5s;
    border: 1px solid #c33;
}


    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><span class="model">Model</span> Pharmacy</h1>
            <p>Sign in to access the system</p>
        </div>
        
        <?php if(!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" autocomplete="off">
    <div class="form-group">
        <label for="username">Username</label>
        <input type="text"
               id="username"
               name="username"
               autocomplete="off"
               required
               placeholder="Enter your username">
    </div>
    
    <div class="form-group">
        <label for="password">Password</label>
        <input type="password"
               id="password"
               name="password"
               autocomplete="new-password"
               required
               placeholder="Enter your password">
    </div>
    
    <button type="submit" class="btn-login">Login</button>
</form>

        
        <div class="warnings">
            <!-- <h4>Demo Credentials (You'll create these in next step):</h4>
            <p>Username: admin</p>
            <p>Password: admin123</p> -->
            <p >Authorized User Only</p>

            <h3>Wishing you a productive and wonderful day</h3>
        </div>
    </div>

    <script>
window.onload = function () {
    document.querySelector("form").reset();
};
</script>

</body>
</html>