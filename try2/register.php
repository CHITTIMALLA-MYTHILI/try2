<?php
session_start();

$host = 'localhost';
$dbname = 'organ_blood_donation';
$username = 'root';
$password = '';

$error = '';
$success = '';

// Database Connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Ensure users table exists
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            phone VARCHAR(20) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            age INT NOT NULL,
            role ENUM('patient', 'donor', 'blood_bank', 'hospital', 'admin') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ";
    $pdo->exec($createTableQuery);

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Sanitize and get inputs
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $pass = $_POST['password'];
    $age = (int)$_POST['age'];
    $role = $_POST['role'];

    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($pass) || empty($age) || empty($role)) {
        $error = "All fields are required.";
    } elseif ($age < 18) {
        $error = "You must be at least 18 years old to register.";
    } else {
        // Check for duplicate email or phone
        $stmt = $pdo->prepare("SELECT email, phone FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$email, $phone]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            if ($existingUser['email'] === $email) {
                $error = "This email is already registered.";
            } elseif ($existingUser['phone'] === $phone) {
                $error = "This phone number is already registered.";
            }
        } else {
            // Hash password
            $hashed_password = password_hash($pass, PASSWORD_DEFAULT);

            // Db inserts
            $insertQuery = "INSERT INTO users (name, email, phone, password, age, role) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($insertQuery);
            
            if ($stmt->execute([$name, $email, $phone, $hashed_password, $age, $role])) {
                // Success, redirect to login page
                header("Location: login.php?registered=success");
                exit();
            } else {
                $error = "Registration failed. Please try again.";
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
    <title>Register | Organ & Blood Donation System</title>
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }

        .login-wrapper {
            width: 100%;
            max-width: 500px;
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
            background: linear-gradient(135deg, #11cdef 0%, #1171ef 100%);
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
            border-color: #1171ef;
            background-color: #ffffff;
        }

        .btn-login {
            background: linear-gradient(135deg, #11cdef 0%, #1171ef 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            font-size: 1.05rem;
            width: 100%;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(17, 113, 239, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(17, 113, 239, 0.4);
            color: white;
        }

        .alert {
            border-radius: 10px;
            font-size: 0.9rem;
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #525f7f;
        }
        
        .register-link a {
            color: #1171ef;
            font-weight: 600;
            text-decoration: none;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div class="icon-circle">
                    <i class="bi bi-person-plus-fill"></i>
                </div>
                <h3>Create Account</h3>
                <p class="mb-0 mt-2 opacity-75 small">Join Organ & Blood Donation System</p>
            </div>
            
            <div class="login-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-person"></i></span>
                            <input type="text" name="name" class="form-control border-start-0 ps-0" required placeholder="Enter actual name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control border-start-0 ps-0" required placeholder="Enter email address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-telephone"></i></span>
                                <input type="text" name="phone" class="form-control border-start-0 ps-0" required placeholder="Phone..." value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Age</label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-calendar-event"></i></span>
                                <input type="number" name="age" class="form-control border-start-0 ps-0" required placeholder="Age" min="18" value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control border-start-0 ps-0" required placeholder="Enter password">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Register As</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-shield-check"></i></span>
                            <select name="role" class="form-select border-start-0 ps-0" required>
                                <option value="" disabled selected>Select your role...</option>
                                <option value="patient" <?php if(isset($_POST['role']) && $_POST['role'] == 'patient') echo 'selected'; ?>>Patient</option>
                                <option value="donor" <?php if(isset($_POST['role']) && $_POST['role'] == 'donor') echo 'selected'; ?>>Donor</option>
                                <option value="blood_bank" <?php if(isset($_POST['role']) && $_POST['role'] == 'blood_bank') echo 'selected'; ?>>Blood Bank</option>
                                <option value="hospital" <?php if(isset($_POST['role']) && $_POST['role'] == 'hospital') echo 'selected'; ?>>Hospital</option>
                                <option value="admin" <?php if(isset($_POST['role']) && $_POST['role'] == 'admin') echo 'selected'; ?>>Admin</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" name="register" class="btn btn-login">
                        Register Account <i class="bi bi-person-check ms-1"></i>
                    </button>
                </form>

                <div class="register-link">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
