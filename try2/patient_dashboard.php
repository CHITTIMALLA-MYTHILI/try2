<?php
session_start();
// Handle case sensitivity for role (e.g. 'Patient' vs 'patient')
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'patient') {
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

    $message = "";
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
    }

    // 1. Fetch User Profile
    $stmtProf = $pdo->prepare("SELECT name, email, phone, age FROM users WHERE id = ?");
    $stmtProf->execute([$_SESSION['user_id']]);
    $userProfile = $stmtProf->fetch(PDO::FETCH_ASSOC);

    // Fallback if not found
    if (!$userProfile) {
        $loggedInName = "Unknown";
        $loggedInAge = 18;
    } else {
        $loggedInName = $userProfile['name'];
        $loggedInAge = $userProfile['age'];
    }

    // Handle Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
        $request_type = $_POST['request_type'];
        $organ_needed = empty($_POST['organ_needed']) ? null : $_POST['organ_needed'];
        $condition = $_POST['condition'];
        $blood_group = $_POST['blood_group'];

        // Prevent duplicate inserts: check if exact request already exists and is pending
        $stmtCheck = $pdo->prepare("SELECT patient_id FROM patients WHERE name = ? AND age = ? AND blood_group = ? AND request_type = ? AND (organ_needed = ? OR (organ_needed IS NULL AND ? IS NULL)) AND status IN ('pending', 'waiting_for_donor')");
        $stmtCheck->execute([$loggedInName, $loggedInAge, $blood_group, $request_type, $organ_needed, $organ_needed]);
        
        if ($stmtCheck->fetchColumn()) {
            $_SESSION['message'] = "<div class='alert alert-warning alert-dismissible fade show' role='alert'>
                                        <i class='bi bi-exclamation-triangle-fill me-2'></i>A request with these details already exists and is being processed.
                                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                                    </div>";
        } else {
            $stmt = $pdo->prepare("INSERT INTO patients (name, age, blood_group, request_type, organ_needed, `condition`) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$loggedInName, $loggedInAge, $blood_group, $request_type, $organ_needed, $condition]);

            // Trigger priority evaluation and automatic matching scoped to ONLY the new insert
            ob_start();
            if(file_exists('update_priority.php')) include 'update_priority.php';
            if(file_exists('matching_system.php')) include 'matching_system.php';
            $debug_output = ob_get_clean();

            $_SESSION['message'] = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                            <i class='bi bi-check-circle-fill me-2'></i>Request successfully submitted and matching sequence executed!
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        }
        
        header("Location: patient_dashboard.php");
        exit();
    }

    // Summary Stats
    $totalReq = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE name = ? AND request_type IS NOT NULL");
    $totalReq->execute([$loggedInName]);
    $totalReq = $totalReq->fetchColumn();

    $approvedReq = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE name = ? AND status = 'approved'");
    $approvedReq->execute([$loggedInName]);
    $approvedReq = $approvedReq->fetchColumn();

    $pendingReq = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE name = ? AND status IN ('pending', 'waiting_for_donor')");
    $pendingReq->execute([$loggedInName]);
    $pendingReq = $pendingReq->fetchColumn();

    $fulfilledReq = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE name = ? AND status = 'fulfilled'");
    $fulfilledReq->execute([$loggedInName]);
    $fulfilledReq = $fulfilledReq->fetchColumn();

    // Requests Data
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE name = ? AND request_type IS NOT NULL ORDER BY request_date DESC");
    $stmt->execute([$loggedInName]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Blood Banks (aggregating inventory)
    $bbStmt = $pdo->query("
        SELECT bb.name, bb.location, 
               COALESCE(GROUP_CONCAT(bi.blood_group SEPARATOR ', '), 'None') as available_groups, 
               COALESCE(SUM(bi.units_available), 0) as total_units 
        FROM blood_banks bb 
        LEFT JOIN blood_inventory bi ON bb.bank_id = bi.bank_id 
        GROUP BY bb.bank_id
        ORDER BY bb.name
    ");
    $bloodBanks = $bbStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Hospitals 
    // Since organs are dynamically matched rather than statically stored in hospitals, we show a standard message or query future organ inventory.
    $hospStmt = $pdo->query("SELECT name, location, 'Check Direct Availability' as available_organs FROM hospitals ORDER BY name");
    $hospitals = $hospStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard | Healthcare System</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fe;
            color: #333;
        }

        .gradient-header {
            background: linear-gradient(135deg, #11cdef 0%, #1171ef 100%);
            color: white;
            padding: 3rem 0;
            border-bottom-left-radius: 40px;
            border-bottom-right-radius: 40px;
            box-shadow: 0 10px 30px rgba(17, 113, 239, 0.2);
            position: relative;
            margin-bottom: 3rem;
            overflow: hidden;
        }

        .gradient-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 40%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(-30deg);
            pointer-events: none;
        }

        .custom-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: none;
            height: 100%;
        }

        .card-header-title {
            font-weight: 700;
            color: #32325d;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .card-header-title i {
            margin-right: 10px;
            color: #1171ef;
        }

        /* Summary Stats Cards */
        .stat-card {
            border-radius: 15px;
            padding: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .stat-card-total { background: linear-gradient(135deg, #5e72e4 0%, #825ee4 100%); }
        .stat-card-approved { background: linear-gradient(135deg, #11cdef 0%, #1171ef 100%); }
        .stat-card-pending { background: linear-gradient(135deg, #fb6340 0%, #fbb140 100%); }
        .stat-card-fulfilled { background: linear-gradient(135deg, #2dce89 0%, #2dcecc 100%); }

        .stat-card i {
            font-size: 2.5rem;
            position: absolute;
            right: 1.5rem;
            bottom: 1rem;
            opacity: 0.3;
        }

        /* Profile Details */
        .profile-line {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .profile-line:last-child {
            border-bottom: none;
        }
        .profile-label {
            font-weight: 600;
            color: #525f7f;
            font-size: 0.9rem;
        }
        .profile-value {
            font-weight: 500;
            color: #32325d;
            font-size: 0.9rem;
        }

        /* Tables */
        .table thead th {
            border-bottom: 2px solid #e9ecef;
            color: #8898aa;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        .table td {
            vertical-align: middle;
            color: #525f7f;
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Status Badges */
        .status-badge { padding: 0.4em 1em; border-radius: 50px; font-weight: 600; font-size: 0.85rem; }
        .badge-pending { background-color: rgba(251, 99, 64, 0.1); color: #fb6340; }
        .badge-approved { background-color: rgba(17, 205, 239, 0.1); color: #11cdef; }
        .badge-waiting { background-color: rgba(94, 114, 228, 0.1); color: #5e72e4; }
        .badge-fulfilled { background-color: rgba(45, 206, 137, 0.1); color: #2dce89; }
        
        .req-type { font-weight: 700; text-transform: uppercase; font-size: 0.8rem; }
        .req-blood { color: #f5365c; }
        .req-organ { color: #8965e0; }

        .btn-request {
            background: linear-gradient(135deg, #11cdef 0%, #1171ef 100%);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 0.8rem 2rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(17, 113, 239, 0.3);
            transition: all 0.3s;
        }
        .btn-request:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(17, 113, 239, 0.4);
            color: white;
        }
    </style>
</head>

<body>

    <header class="gradient-header">
        <div class="container d-flex justify-content-between align-items-center">
            <div style="z-index: 1;">
                <h1 class="display-5 fw-bold mb-1"><i class="bi bi-person-heart me-2"></i>Patient Dashboard</h1>
                <p class="lead mb-0 opacity-75">Welcome back, <?php echo htmlspecialchars($loggedInName); ?></p>
            </div>
            <div class="d-flex align-items-center gap-3" style="z-index: 1;">
                <button class="btn btn-request" data-bs-toggle="modal" data-bs-target="#newRequestModal">
                    <i class="bi bi-plus-lg me-1"></i> New Request
                </button>
                <a href="logout.php" class="btn btn-light rounded-pill shadow-sm text-danger fw-bold"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </header>

    <main class="container">
        <?php if (!empty($message)) echo $message; ?>

        <div class="row mb-4">
            <!-- Profile Column -->
            <div class="col-lg-4 mb-4 mb-lg-0">
                <div class="custom-card">
                    <h5 class="card-header-title"><i class="bi bi-person-badge"></i> Profile Details</h5>
                    <?php if($userProfile): ?>
                        <div class="profile-line">
                            <span class="profile-label">Name</span>
                            <span class="profile-value"><?php echo htmlspecialchars($userProfile['name']); ?></span>
                        </div>
                        <div class="profile-line">
                            <span class="profile-label">Email</span>
                            <span class="profile-value"><?php echo htmlspecialchars($userProfile['email']); ?></span>
                        </div>
                        <div class="profile-line">
                            <span class="profile-label">Phone</span>
                            <span class="profile-value"><?php echo htmlspecialchars($userProfile['phone']); ?></span>
                        </div>
                        <div class="profile-line">
                            <span class="profile-label">Age</span>
                            <span class="profile-value"><?php echo htmlspecialchars($userProfile['age']); ?> Years</span>
                        </div>
                        <div class="profile-line">
                            <span class="profile-label">Role</span>
                            <span class="profile-value"><span class="badge bg-primary rounded-pill">Patient</span></span>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary">Profile data unavailable.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Column -->
            <div class="col-lg-8">
                <div class="row g-3 h-100">
                    <div class="col-sm-6">
                        <div class="stat-card stat-card-total h-100">
                            <h6 class="text-uppercase fw-bold mb-1 opacity-75">Total Requests</h6>
                            <h2 class="fw-bold mb-0"><?php echo $totalReq; ?></h2>
                            <i class="bi bi-folder2-open"></i>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="stat-card stat-card-approved h-100">
                            <h6 class="text-uppercase fw-bold mb-1 opacity-75">Approved</h6>
                            <h2 class="fw-bold mb-0"><?php echo $approvedReq; ?></h2>
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="stat-card stat-card-pending h-100">
                            <h6 class="text-uppercase fw-bold mb-1 opacity-75">Pending Matches</h6>
                            <h2 class="fw-bold mb-0"><?php echo $pendingReq; ?></h2>
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="stat-card stat-card-fulfilled h-100">
                            <h6 class="text-uppercase fw-bold mb-1 opacity-75">Fulfilled</h6>
                            <h2 class="fw-bold mb-0"><?php echo $fulfilledReq; ?></h2>
                            <i class="bi bi-heart-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Blood Banks List -->
            <div class="col-lg-6 mb-4">
                <div class="custom-card">
                    <h5 class="card-header-title"><i class="bi bi-hospital"></i> Blood Banks Network</h5>
                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead style="position: sticky; top: 0; background: white; z-index: 1;">
                                <tr>
                                    <th>Blood Bank</th>
                                    <th>Location</th>
                                    <th>Groups Available</th>
                                    <th>Total Units</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($bloodBanks) > 0): ?>
                                    <?php foreach ($bloodBanks as $bank): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($bank['name']); ?></td>
                                            <td><i class="bi bi-geo-alt text-muted me-1"></i><?php echo htmlspecialchars($bank['location']); ?></td>
                                            <td><span class="badge bg-danger rounded-pill"><?php echo htmlspecialchars($bank['available_groups']); ?></span></td>
                                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($bank['total_units']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">No blood banks partnered yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Hospitals List -->
            <div class="col-lg-6 mb-4">
                <div class="custom-card">
                    <h5 class="card-header-title"><i class="bi bi-building"></i> Partner Hospitals</h5>
                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead style="position: sticky; top: 0; background: white; z-index: 1;">
                                <tr>
                                    <th>Hospital Name</th>
                                    <th>Location</th>
                                    <th>Organ Access</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($hospitals) > 0): ?>
                                    <?php foreach ($hospitals as $hospital): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($hospital['name']); ?></td>
                                            <td><i class="bi bi-geo-alt text-muted me-1"></i><?php echo htmlspecialchars($hospital['location']); ?></td>
                                            <td><span class="badge bg-secondary rounded-pill"><?php echo htmlspecialchars($hospital['available_organs']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">No hospitals partnered yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Requests -->
        <div class="custom-card mb-5">
            <h5 class="card-header-title"><i class="bi bi-clock-history"></i> My Request History</h5>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Request Type</th>
                            <th>Blood Group</th>
                            <th>Organ Needed</th>
                            <th>Priority Score</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($patients) > 0): ?>
                            <?php foreach ($patients as $p): ?>
                                <tr>
                                    <td>
                                        <?php if ($p['request_type'] === 'blood'): ?>
                                            <span class="req-type req-blood"><i class="bi bi-droplet-fill me-1"></i>Blood</span>
                                        <?php else: ?>
                                            <span class="req-type req-organ"><i class="bi bi-lungs-fill me-1"></i>Organ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-secondary rounded-pill px-2 py-1"><?php echo htmlspecialchars($p['blood_group']); ?></span></td>
                                    <td><?php echo $p['organ_needed'] ? htmlspecialchars($p['organ_needed']) : '<span class="text-muted fst-italic">N/A</span>'; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="fw-bold me-2"><?php echo htmlspecialchars($p['priority_score']); ?></span>
                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                <?php
                                                $width = min(100, $p['priority_score']);
                                                $bgClass = $p['priority_score'] > 60 ? 'bg-danger' : 'bg-primary';
                                                ?>
                                                <div class="progress-bar <?php echo $bgClass; ?>" role="progressbar" style="width: <?php echo $width; ?>%;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $status = strtolower($p['status']);
                                        $badgeClass = 'badge-pending';
                                        $icon = 'bi-hourglass';
                                        $text = ucfirst($status);

                                        if ($status === 'approved') {
                                            $badgeClass = 'badge-approved'; $icon = 'bi-check-circle';
                                        } elseif ($status === 'fulfilled') {
                                            $badgeClass = 'badge-fulfilled'; $icon = 'bi-heart-fill';
                                        } elseif ($status === 'waiting_for_donor') {
                                            $badgeClass = 'badge-waiting'; $icon = 'bi-search'; $text = 'Waiting';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $badgeClass; ?>">
                                            <i class="bi <?php echo $icon; ?> me-1"></i><?php echo htmlspecialchars($text); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">You haven't made any requests yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Modal Form for New Request -->
    <div class="modal fade" id="newRequestModal" tabindex="-1" aria-labelledby="newRequestLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 15px 35px rgba(0,0,0,0.2);">
                <div class="modal-header" style="background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%); border-top-left-radius: 20px; border-top-right-radius: 20px;">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-file-earmark-medical me-2 text-primary"></i>New Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="patient_dashboard.php" method="POST">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">Your Blood Group</label>
                            <select name="blood_group" class="form-select" required>
                                <option value="" disabled selected>Select blood group...</option>
                                <option value="A+">A+</option><option value="A-">A-</option>
                                <option value="B+">B+</option><option value="B-">B-</option>
                                <option value="AB+">AB+</option><option value="AB-">AB-</option>
                                <option value="O+">O+</option><option value="O-">O-</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">Request Type</label>
                            <select name="request_type" id="requestTypeSelect" class="form-select" required onchange="toggleOrganNeeded()">
                                <option value="" disabled selected>Select Type...</option>
                                <option value="blood">Blood Donation</option>
                                <option value="organ">Organ Donation</option>
                            </select>
                        </div>
                        <div class="mb-3" id="organNeededDiv" style="display: none;">
                            <label class="form-label fw-bold text-muted small">Organ Needed</label>
                            <input type="text" name="organ_needed" id="organNeededInput" class="form-control" placeholder="e.g. Kidney, Liver">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">Condition Severity</label>
                            <select name="condition" class="form-select" required>
                                <option value="normal">Normal / Routine</option>
                                <option value="urgent">Urgent</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 py-3 px-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_request" class="btn btn-request rounded-pill border-0 shadow-sm m-0">Submit Request <i class="bi bi-send ms-1"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleOrganNeeded() {
            var requestType = document.getElementById('requestTypeSelect').value;
            var organDiv = document.getElementById('organNeededDiv');
            var organInput = document.getElementById('organNeededInput');

            if (requestType === 'organ') {
                organDiv.style.display = 'block';
                organInput.setAttribute('required', 'required');
            } else {
                organDiv.style.display = 'none';
                organInput.removeAttribute('required');
                organInput.value = '';
            }
        }
    </script>
</body>
</html>