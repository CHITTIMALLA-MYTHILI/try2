<?php
session_start();

$host = 'localhost';
$dbname = 'organ_blood_donation';
$username = 'root';
$password = '';

$error = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if (isset($_GET['registered']) && $_GET['registered'] == 'success') {
    $success_msg = "Registration successful! Please login.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $login_id = trim($_POST['login_id']); // Email or phone
    $pass = $_POST['password'];
    if (!empty($login_id) && !empty($pass)) {
        // Find user by email or phone
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$login_id, $login_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($pass, $user['password'])) {
            // 5. Ensure session is set
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role']; // 6. Fix case sensitivity, match DB exactly

            // 4. Debug: Print user role after login to verify
            // echo $user['role']; 

            // 3. Ensure role-based redirect includes if/elseif
            if ($user['role'] == 'patient') {
                header("Location: patient_dashboard.php");
                exit();
            } elseif ($user['role'] == 'donor') {
                header("Location: donor_dashboard.php");
                exit();
            } elseif ($user['role'] == 'blood_bank') {
                header("Location: bloodbank_dashboard.php");
                exit();
            } elseif ($user['role'] == 'hospital') {
                header("Location: hospital_dashboard.php");
                exit();
            } elseif ($user['role'] == 'admin') {
                header("Location: admin_dashboard.php");
                exit();
            }
        } else {
            // 7. Add error handling
            $error = "Invalid credentials. Please verify your email/phone and password.";
        }
    } else {
        $error = "Please enter your email/phone and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Organ & Blood Donation System</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fe;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrapper {
            width: 100%;
            max-width: 450px;
            padding: 15px;
        }

        .login-card {
            background: #ffffff;
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #ff0844 0%, #ffb199 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }

        .login-header .icon-circle {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin: 0 auto 15px auto;
        }

        .login-header h3 {
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .login-body {
            padding: 40px 30px;
        }

        .form-label {
            font-weight: 600;
            color: #525f7f;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e9ecef;
            background-color: #f8f9fe;
            color: #32325d;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border-color: #ffb199;
            background-color: #ffffff;
        }

        .btn-login {
            background: linear-gradient(135deg, #ff0844 0%, #ffb199 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            font-size: 1.05rem;
            width: 100%;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(255, 8, 68, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 8, 68, 0.4);
            color: white;
        }

        .alert {
            border-radius: 10px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div class="icon-circle">
                    <i class="bi bi-heart-pulse-fill"></i>
                </div>
                <h3>Welcome Back</h3>
                <p class="mb-0 mt-2 opacity-75 small">Organ & Blood Donation System</p>
            </div>
            
            <div class="login-body">
                <?php if (isset($success_msg)): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <div><?php echo htmlspecialchars($success_msg); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Email or Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-person"></i></span>
                            <input type="text" name="login_id" class="form-control border-start-0 ps-0" required placeholder="Enter email or username">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control border-start-0 ps-0" required placeholder="Enter password">
                        </div>
                    </div>
                    

                    <button type="submit" name="login" class="btn btn-login mb-3">
                        Login <i class="bi bi-box-arrow-in-right ms-1"></i>
                    </button>

                    <div class="text-center mt-3" style="font-size: 0.9rem; color: #525f7f;">
                        Don't have an account? <a href="register.php" style="color: #1171ef; font-weight: 600; text-decoration: none;">Register here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
