<?php
session_start();
// Handle case sensitivity for role (e.g. 'Donor' vs 'donor')
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'donor') {
    header("Location: login.php");
    exit();
}

$host = 'localhost';
$dbname = 'organ_blood_donation';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

$message = "";

// 1. Fetch User Profile
$stmtProf = $pdo->prepare("SELECT name, email, phone, age FROM users WHERE id = ?");
$stmtProf->execute([$_SESSION['user_id']]);
$userProfile = $stmtProf->fetch(PDO::FETCH_ASSOC);

if (!$userProfile) {
    die("User profile not found. Please log in again.");
}

$donorName = $userProfile['name'];
$donorAge = $userProfile['age'];
$donorPhone = $userProfile['phone'];

// Sync with `donors` table dynamically to preserve system legacy logic & availability toggling
$stmtCheckDonor = $pdo->prepare("SELECT donor_id, availability, blood_group FROM donors WHERE name = ?");
$stmtCheckDonor->execute([$donorName]);
$donorData = $stmtCheckDonor->fetch(PDO::FETCH_ASSOC);

if (!$donorData) {
    // Insert them gracefully so they can immediately act as a donor in the system
    $stmtInsert = $pdo->prepare("INSERT INTO donors (name, age, blood_group, donor_type, availability, contact) VALUES (?, ?, 'O+', 'blood', 'available', ?)");
    $stmtInsert->execute([$donorName, $donorAge, $donorPhone]);
    $donorData = ['availability' => 'available', 'blood_group' => 'O+'];
}

// Handle POST request for Availability Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_availability'])) {
    $new_status = $_POST['availability_status'];
    $stmtUpdate = $pdo->prepare("UPDATE donors SET availability = ? WHERE name = ?");
    if ($stmtUpdate->execute([$new_status, $donorName])) {
        $donorData['availability'] = $new_status;
        $statusText = $new_status == 'available' ? 'Available' : 'Not Available';
        $alertColor = $new_status == 'available' ? 'success' : 'warning';
        $message = "<div class='alert alert-{$alertColor} alert-dismissible fade show' role='alert'>
                        <i class='bi bi-info-circle-fill me-2'></i>Your status has successfully been updated to <strong>{$statusText}</strong>!
                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                    </div>";
    }
}

// Existing legacy block for accepting/rejecting match responses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response_id = $_POST['response_id'] ?? null;
    $patient_id = $_POST['patient_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if ($response_id && $patient_id && $action) {
        $pdo->beginTransaction();
        try {
            if ($action === 'accept') {
                $pdo->prepare("UPDATE donor_responses SET response = 'accepted' WHERE response_id = ?")->execute([$response_id]);
                $pdo->prepare("UPDATE patients SET status = 'approved' WHERE patient_id = ?")->execute([$patient_id]);
                
                // Immediately set availability to not_available visually and in DB
                $pdo->prepare("UPDATE donors SET availability = 'not_available' WHERE name = ?")->execute([$donorName]);
                $donorData['availability'] = 'not_available';

                $message = "<div class='alert alert-success d-flex align-items-center' role='alert'>
                                <i class='bi bi-check-circle-fill me-2'></i>Match successfully Accepted! Patient approved via your dashboard.
                            </div>";
            } elseif ($action === 'reject') {
                $pdo->prepare("UPDATE donor_responses SET response = 'rejected' WHERE response_id = ?")->execute([$response_id]);
                $pdo->prepare("UPDATE patients SET status = 'pending' WHERE patient_id = ?")->execute([$patient_id]);

                $message = "<div class='alert alert-warning d-flex align-items-center' role='alert'>
                                <i class='bi bi-x-circle-fill me-2'></i>Match Rejected. The patient has been returned to the pending queue.
                            </div>";
                // Trigger backend match attempt for that patient
                ob_start(); if(file_exists('matching_system.php')) include 'matching_system.php'; ob_end_clean();
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Error processing response.</div>";
        }
    }
}

// Fetch actively pending donor responses mapped to them
$stmtPending = $pdo->prepare("
    SELECT dr.response_id, dr.patient_id, p.organ_needed, p.blood_group 
    FROM donor_responses dr
    JOIN patients p ON dr.patient_id = p.patient_id
    JOIN donors d ON dr.donor_id = d.donor_id
    WHERE dr.response = 'pending' AND d.name = ?
");
$stmtPending->execute([$donorName]);
$pendingRequests = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch Blood Banks with Required Units
$bbStmt = $pdo->query("
    SELECT bb.name, bb.location, COALESCE(SUM(br.units_needed), 0) as required_units 
    FROM blood_banks bb 
    LEFT JOIN blood_requests br ON bb.bank_id = br.bank_id AND br.status IN ('pending', 'waiting_for_donor')
    GROUP BY bb.bank_id 
    ORDER BY required_units DESC
");
$bloodBanks = $bbStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch Blood Donation Camps
$campStmt = $pdo->query("
    SELECT c.camp_date, c.location, c.description, u.name as organizer
    FROM blood_donation_camps c
    JOIN users u ON c.blood_bank_id = u.id
    WHERE u.role = 'blood_bank'
    ORDER BY c.camp_date ASC
");
$camps = $campStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard | Organ & Blood Donation</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fe; color: #333; }

        .gradient-header {
            background: linear-gradient(135deg, #f5365c 0%, #f56036 100%);
            color: white;
            padding: 3rem 0;
            border-bottom-left-radius: 40px;
            border-bottom-right-radius: 40px;
            box-shadow: 0 10px 30px rgba(245, 54, 92, 0.2);
            position: relative;
            margin-bottom: 3rem;
            overflow: hidden;
        }

        .gradient-header::after {
            content: ''; position: absolute; top: -50%; right: -10%; width: 40%; height: 200%;
            background: rgba(255, 255, 255, 0.1); transform: rotate(-30deg); pointer-events: none;
        }

        .custom-card {
            background: white; border-radius: 20px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 1.5rem; margin-bottom: 2rem; border: none; height: 100%;
        }

        .card-header-title { font-weight: 700; color: #32325d; margin-bottom: 1.5rem; display: flex; align-items: center; }
        .card-header-title i { margin-right: 10px; color: #f5365c; }

        /* Profile Elements */
        .profile-line { display: flex; justify-content: space-between; padding: 0.8rem 0; border-bottom: 1px solid #f0f0f0; }
        .profile-line:last-child { border-bottom: none; }
        .profile-label { font-weight: 600; color: #525f7f; font-size: 0.9rem; }
        .profile-value { font-weight: 600; color: #32325d; font-size: 0.9rem; }

        /* Toggle Button Container */
        .availability-container form { background: #fdfbfb; padding: 1.5rem; border-radius: 15px; border: 1px dashed #e9ecef; }
        .form-select-sm { border-radius: 10px; font-weight: 600; padding: 0.5rem 1rem; }
        
        .btn-update {
            background: linear-gradient(135deg, #11cdef 0%, #1171ef 100%);
            color: white; border: none; border-radius: 10px; padding: 0.5rem 1.5rem;
            font-weight: 600; transition: all 0.3s;
        }
        .btn-update:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(17, 113, 239, 0.3); color: white; }

        /* Tables */
        .table thead th {
            border-bottom: 2px solid #e9ecef; color: #8898aa; font-weight: 600;
            text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px;
        }
        .table td { vertical-align: middle; color: #525f7f; font-size: 0.95rem; font-weight: 500; }
        
        /* Match Card */
        .match-card {
            background: linear-gradient(135deg, #ffffff 0%, #fdfbfb 100%);
            border: 1px solid #e9ecef; border-radius: 15px; padding: 1.5rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04); margin-bottom: 1.5rem; transition: all 0.3s;
        }
        .match-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08); }
        .btn-accept { background: #2dce89; color: white; border: none; border-radius: 50px; padding: 0.4rem 1.2rem; font-weight: 600; font-size: 0.9rem; }
        .btn-reject { background: #f5365c; color: white; border: none; border-radius: 50px; padding: 0.4rem 1.2rem; font-weight: 600; font-size: 0.9rem; }
        .btn-accept:hover, .btn-reject:hover { filter: brightness(1.1); color: white; }
    </style>
</head>
<body>

    <header class="gradient-header">
        <div class="container d-flex justify-content-between align-items-center">
            <div style="z-index: 1;">
                <h1 class="display-5 fw-bold mb-1"><i class="bi bi-droplet-half me-2"></i>Donor Dashboard</h1>
                <p class="lead mb-0 opacity-75">Welcome back, Hero <?php echo htmlspecialchars($donorName); ?></p>
            </div>
            <div class="d-flex align-items-center" style="z-index: 1;">
                <a href="logout.php" class="btn btn-light rounded-pill shadow-sm text-danger fw-bold"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </header>

    <main class="container">
        <?php if (!empty($message)) echo $message; ?>

        <div class="row align-items-stretch mb-4">
            
            <!-- 1. Profile & Status -->
            <div class="col-lg-5 mb-4 mb-lg-0">
                <div class="custom-card">
                    <h5 class="card-header-title"><i class="bi bi-person-lines-fill"></i> Your Profile</h5>
                    <div class="profile-line">
                        <span class="profile-label">Full Name</span>
                        <span class="profile-value"><?php echo htmlspecialchars($donorName); ?></span>
                    </div>
                    <div class="profile-line">
                        <span class="profile-label">Email</span>
                        <span class="profile-value"><?php echo htmlspecialchars($userProfile['email']); ?></span>
                    </div>
                    <div class="profile-line">
                        <span class="profile-label">Contact</span>
                        <span class="profile-value"><?php echo htmlspecialchars($donorPhone); ?></span>
                    </div>
                    <div class="profile-line border-0 mb-3">
                        <span class="profile-label">Blood Group</span>
                        <span class="profile-value"><span class="badge bg-danger rounded-pill px-3"><?php echo htmlspecialchars($donorData['blood_group']); ?></span></span>
                    </div>

                    <!-- 4. Allow Donor to Mark Availability -->
                    <div class="availability-container mt-4 pt-3 border-top">
                        <label class="form-label fw-bold text-muted small text-uppercase">Current Availability Status</label>
                        <form method="POST" action="" class="d-flex align-items-center gap-2 m-0 p-3">
                            <select name="availability_status" class="form-select border-0 shadow-sm <?php echo $donorData['availability'] == 'available' ? 'text-success' : 'text-warning'; ?>">
                                <option value="available" class="text-success" <?php if($donorData['availability'] == 'available') echo 'selected'; ?>>● Available to Donate</option>
                                <option value="not_available" class="text-warning" <?php if($donorData['availability'] == 'not_available') echo 'selected'; ?>>● Not Available / Rest</option>
                            </select>
                            <button type="submit" name="toggle_availability" class="btn btn-update shadow-sm"><i class="bi bi-arrow-repeat"></i> Update</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Pending Request Matches (Legacy system bridged) -->
            <div class="col-lg-7">
                <div class="custom-card border border-danger">
                    <h5 class="card-header-title text-danger"><i class="bi bi-bell-fill"></i> Direct Patient Matches</h5>
                    
                    <?php if (count($pendingRequests) > 0): ?>
                        <div style="max-height: 290px; overflow-y: auto; padding-right: 10px;">
                            <?php foreach ($pendingRequests as $req): ?>
                                <div class="match-card d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="fw-bold mb-1">Patient Request #<?php echo htmlspecialchars($req['patient_id']); ?></h6>
                                        <div class="d-flex gap-2">
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill"><i class="bi bi-droplet-fill me-1"></i><?php echo htmlspecialchars($req['blood_group']); ?></span>
                                            <?php if($req['organ_needed']): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary rounded-pill"><i class="bi bi-lungs-fill me-1"></i><?php echo htmlspecialchars($req['organ_needed']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="response_id" value="<?php echo htmlspecialchars($req['response_id']); ?>">
                                            <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($req['patient_id']); ?>">
                                            <button type="submit" class="btn btn-reject shadow-sm" title="Reject"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="accept">
                                            <input type="hidden" name="response_id" value="<?php echo htmlspecialchars($req['response_id']); ?>">
                                            <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($req['patient_id']); ?>">
                                            <button type="submit" class="btn btn-accept shadow-sm">Accept Match</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-shield-check text-muted mb-2" style="font-size: 3rem; opacity: 0.2;"></i>
                            <h6 class="fw-bold text-muted">No pending matches targeting you.</h6>
                            <p class="small text-muted mb-0">Check out the blood banks below if you'd like to proactively donate!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="row">
            <!-- 2. Display List of Blood Banks & Their Requirements -->
            <div class="col-lg-6 mb-4">
                <div class="custom-card">
                    <h5 class="card-header-title"><i class="bi bi-hospital"></i> Blood Banks & Shortages</h5>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead style="position: sticky; top: 0; background: white; z-index: 1;">
                                <tr>
                                    <th>Blood Bank</th>
                                    <th>Location</th>
                                    <th class="text-end">Required Units</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($bloodBanks) > 0): ?>
                                    <?php foreach ($bloodBanks as $bank): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($bank['name']); ?></td>
                                            <td><i class="bi bi-geo-alt text-muted me-1"></i><?php echo htmlspecialchars($bank['location']); ?></td>
                                            <td class="text-end">
                                                <?php if($bank['required_units'] > 0): ?>
                                                    <span class="badge bg-danger rounded-pill px-3 py-2 fw-bold" style="font-size: 0.9rem;">
                                                        <?php echo htmlspecialchars($bank['required_units']); ?> Needed
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-25 text-secondary rounded-pill px-3 py-2">Sufficient</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">No blood banks currently indexed.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 3. Show Blood Donation Camps -->
            <div class="col-lg-6 mb-4">
                <div class="custom-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-header-title mb-0"><i class="bi bi-calendar-event"></i> Upcoming Donation Camps</h5>
                        <span class="badge bg-success rounded-pill px-3">Open for walk-ins</span>
                    </div>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead style="position: sticky; top: 0; background: white; z-index: 1;">
                                <tr>
                                    <th>Camp Date</th>
                                    <th>Location</th>
                                    <th>Description</th>
                                    <th>Organized By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($camps) > 0): ?>
                                    <?php foreach ($camps as $camp): ?>
                                        <tr>
                                            <td class="fw-bold text-dark">
                                                <?php echo date('M d, Y \a\t g:i A', strtotime($camp['camp_date'])); ?>
                                            </td>
                                            <td><i class="bi bi-pin-map text-danger me-1"></i><?php echo htmlspecialchars($camp['location']); ?></td>
                                            <td><small class="text-muted"><?php echo htmlspecialchars($camp['description']); ?></small></td>
                                            <td><span class="badge bg-light text-dark border px-2"><?php echo htmlspecialchars($camp['organizer']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-5">
                                            <i class="bi bi-calendar-x opacity-25 d-block mb-2" style="font-size: 2rem;"></i>
                                            No donation camps available
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
