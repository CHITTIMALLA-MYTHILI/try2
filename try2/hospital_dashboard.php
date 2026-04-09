<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Hospital') {
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

    // Handle Confirm Transplant Action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_transplant'])) {
        $patient_id = $_POST['patient_id'];
        
        $pdo->beginTransaction();
        try {
            // Update the patient status to fulfilled
            $stmt = $pdo->prepare("UPDATE patients SET status = 'fulfilled' WHERE patient_id = ?");
            $stmt->execute([$patient_id]);
            
            // Update donor availability to not_available
            $stmtUpdateDonor = $pdo->prepare("
                UPDATE donors 
                SET availability = 'not_available' 
                WHERE donor_id = (
                    SELECT donor_id 
                    FROM donor_responses 
                    WHERE patient_id = :patient_id AND response = 'accepted' 
                    LIMIT 1
                )
            ");
            $stmtUpdateDonor->execute([':patient_id' => $patient_id]);
            
            $pdo->commit();
            
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                            <i class='bi bi-check-circle-fill me-2'></i>Transplant successfully confirmed and recorded!
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Error confirming transplant: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // Fetch Summary Metrics (Organ Specific)
    $totalRequests = $pdo->query("SELECT COUNT(*) FROM patients WHERE request_type = 'organ'")->fetchColumn();
    $activeMatches = $pdo->query("SELECT COUNT(*) FROM patients WHERE request_type = 'organ' AND status IN ('waiting_for_donor', 'approved')")->fetchColumn();
    $completedTransplants = $pdo->query("SELECT COUNT(*) FROM patients WHERE request_type = 'organ' AND status = 'fulfilled'")->fetchColumn();

    // Fetch Organ Requests along with any matched Donors via the donor_responses table
    // We use a LEFT JOIN because some requests might be 'pending' without a donor yet.
    $query = "
        SELECT 
            p.patient_id, p.name as patient_name, p.organ_needed, p.blood_group as patient_blood, 
            p.priority_score, p.status, p.`condition`, p.age,
            d.donor_id, d.name as donor_name, d.blood_group as donor_blood, dr.response
        FROM patients p
        LEFT JOIN donor_responses dr ON p.patient_id = dr.patient_id AND dr.response != 'rejected'
        LEFT JOIN donors d ON dr.donor_id = d.donor_id
        WHERE p.request_type = 'organ'
        ORDER BY p.priority_score DESC, p.request_date DESC
    ";
    
    $organRequests = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Dashboard | Organ Management</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #333;
        }

        /* Medical Blue/Green Header */
        .med-header {
            background: linear-gradient(135deg, #00b4db 0%, #0083b0 100%);
            color: white;
            padding: 3.5rem 0 2.5rem 0;
            border-bottom-left-radius: 40px;
            border-bottom-right-radius: 40px;
            box-shadow: 0 10px 30px rgba(0, 131, 176, 0.3);
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
        }

        .med-header::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -10%;
            width: 40%;
            height: 200%;
            background: rgba(255,255,255,0.08);
            transform: rotate(30deg);
            pointer-events: none;
        }

        /* Summary Cards */
        .summary-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            padding: 1.8rem;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .summary-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px 0 rgba(0, 131, 176, 0.15);
        }

        .summary-icon {
            font-size: 3rem;
            position: absolute;
            right: 20px;
            bottom: 10px;
            opacity: 0.15;
        }

        .sc-blue .summary-icon { color: #00b4db; }
        .sc-orange .summary-icon { color: #f39c12; }
        .sc-green .summary-icon { color: #2ecc71; }

        /* Request Table Card */
        .glass-panel {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin-bottom: 3rem;
        }

        .table-responsive {
            border-radius: 12px;
        }

        .table thead th {
            border-bottom: 2px solid #e9ecef;
            color: #636e72;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .table tbody tr {
            transition: all 0.2s;
        }

        .table tbody tr:hover {
            background-color: #f1f8ff;
        }

        .table td {vertical-align: middle; font-weight: 500; color: #2d3436; }

        /* Dynamic Status Badges */
        .status-badge {
            padding: 0.5em 1.2em;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
        }
        
        .status-pending { background-color: rgba(241, 196, 15, 0.15); color: #f39c12; }
        .status-waiting { background-color: rgba(230, 126, 34, 0.15); color: #d35400; }
        .status-approved { background-color: rgba(52, 152, 219, 0.15); color: #2980b9; }
        .status-fulfilled { background-color: rgba(46, 204, 113, 0.15); color: #27ae60; }

        /* Action Buttons */
        .btn-action {
            border-radius: 50px;
            font-weight: 600;
            padding: 0.4rem 1rem;
            font-size: 0.85rem;
            transition: all 0.3s;
        }

        .btn-view {
            background-color: #f8f9fa;
            color: #2c3e50;
            border: 1px solid #ced4da;
        }

        .btn-view:hover {
            background-color: #e9ecef;
        }

        .btn-confirm {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 10px rgba(56, 239, 125, 0.3);
        }

        .btn-confirm:hover {
            transform: scale(1.05);
            color: white;
            box-shadow: 0 6px 15px rgba(56, 239, 125, 0.4);
        }

        /* Modal specific styling */
        .modal-content { border-radius: 20px; border: none; }
        .modal-header { border-top-left-radius: 20px; border-top-right-radius: 20px; }
        .organ-tag { background: #8e44ad; color: white; padding: 0.3rem 0.8rem; border-radius: 20px; font-weight: 600; font-size: 0.8rem; }
    </style>
</head>
<body>

    <!-- Med Header -->
    <header class="med-header">
        <div class="container d-flex justify-content-between align-items-center position-relative" style="z-index: 1;">
            <div>
                <h1 class="display-4 fw-bold mb-2"><i class="bi bi-hospital me-3"></i>Hospital Dashboard</h1>
                <p class="lead mb-0">Manage organ requests, track donors, and execute transplants.</p>
            </div>
            <div class="d-flex align-items-center gap-4">
                <div class="d-none d-md-block">
                    <i class="bi bi-lungs" style="font-size: 5rem; opacity: 0.4;"></i>
                </div>
                <a href="logout.php" class="btn btn-danger shadow-sm"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </header>

    <main class="container">
        
        <?php if (!empty($message)) echo $message; ?>

        <!-- Summary Cards -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="summary-card sc-blue h-100">
                    <h6 class="text-uppercase text-muted fw-bold mb-2">Total Organ Requests</h6>
                    <h1 class="fw-bold text-dark mb-0"><?php echo $totalRequests; ?></h1>
                    <i class="bi bi-file-earmark-medical summary-icon"></i>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card sc-orange h-100">
                    <h6 class="text-uppercase text-muted fw-bold mb-2">Active Matches in Progress</h6>
                    <h1 class="fw-bold text-dark mb-0"><?php echo $activeMatches; ?></h1>
                    <i class="bi bi-arrow-left-right summary-icon"></i>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card sc-green h-100">
                    <h6 class="text-uppercase text-muted fw-bold mb-2">Completed Transplants</h6>
                    <h1 class="fw-bold text-dark mb-0"><?php echo $completedTransplants; ?></h1>
                    <i class="bi bi-heart-pulse summary-icon"></i>
                </div>
            </div>
        </div>

        <!-- Organ Request Matrix -->
        <div class="glass-panel">
            <h4 class="fw-bold mb-4 text-dark"><i class="bi bi-clipboard-pulse text-info me-2"></i>Organ Request & Transplant Matrix</h4>
            
            <div class="table-responsive">
                <table class="table table-borderless align-middle">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Priority</th>
                            <th>Organ Details</th>
                            <th>Donor Match</th>
                            <th>Status</th>
                            <th class="text-end">Operations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($organRequests as $req): ?>
                            <tr class="border-bottom">
                                <!-- Patient Info -->
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <i class="bi bi-person text-secondary"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($req['patient_name']); ?></div>
                                            <div class="text-muted small">ID: #<?php echo $req['patient_id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Priority Score -->
                                <td>
                                    <h5 class="mb-0 fw-bold <?php echo ($req['priority_score'] > 75) ? 'text-danger' : 'text-primary'; ?>">
                                        <?php echo htmlspecialchars($req['priority_score']); ?>
                                    </h5>
                                </td>
                                
                                <!-- Organ & Blood -->
                                <td>
                                    <span class="organ-tag me-1"><i class="bi bi-boxes me-1"></i><?php echo htmlspecialchars($req['organ_needed']); ?></span>
                                    <span class="badge bg-danger rounded-pill"><i class="bi bi-droplet-fill"></i> <?php echo htmlspecialchars($req['patient_blood']); ?></span>
                                </td>
                                
                                <!-- Matched Donor -->
                                <td>
                                    <?php if(!empty($req['donor_name'])): ?>
                                        <div class="d-flex align-items-center bg-light px-2 py-1 rounded" style="display: inline-flex !important;">
                                            <i class="bi bi-person-heart text-success me-2"></i>
                                            <span class="fw-bold small"><?php echo htmlspecialchars($req['donor_name']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">Searching database...</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Badge -->
                                <td>
                                    <?php 
                                        $s = strtolower($req['status']);
                                        $cls = ''; $icon = ''; $text = '';
                                        
                                        if($s === 'pending') {
                                            $cls = 'status-pending'; $icon = 'bi-hourglass'; $text = 'Pending';
                                        } elseif($s === 'waiting_for_donor') {
                                            $cls = 'status-waiting'; $icon = 'bi-search'; $text = 'Waiting for Donor';
                                        } elseif($s === 'approved') {
                                            $cls = 'status-approved'; $icon = 'bi-check2-circle'; $text = 'Approved (Pending Surgery)';
                                        } elseif($s === 'fulfilled') {
                                            $cls = 'status-fulfilled'; $icon = 'bi-check-all'; $text = 'Transplant Fulfilled';
                                        }
                                    ?>
                                    <span class="status-badge <?php echo $cls; ?>">
                                        <i class="bi <?php echo $icon; ?> me-1"></i> <?php echo $text; ?>
                                    </span>
                                </td>
                                
                                <!-- Actions -->
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <!-- View Details Button triggering info injection -->
                                        <button class="btn btn-action btn-view" onclick='showDetails(<?php echo json_encode($req); ?>)'>
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                        
                                        <!-- Confirm Transplant Form -->
                                        <?php if($s === 'approved'): ?>
                                            <form action="hospital_dashboard.php" method="POST" class="m-0" onsubmit="return confirm('Ensure the transplant surgery was successful. Proceed?');">
                                                <input type="hidden" name="patient_id" value="<?php echo $req['patient_id']; ?>">
                                                <button type="submit" name="confirm_transplant" class="btn btn-action btn-confirm">
                                                    <i class="bi bi-clipboard-check"></i> Confirm
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if(count($organRequests) === 0): ?>
                            <tr><td colspan="6" class="text-center text-muted py-5">No organ requests active in the system.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-file-medical-fill text-info me-2"></i>Medical Request Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">Patient Overview</h6>
                    <div class="row mb-4">
                        <div class="col-6 mb-2"><strong>Name:</strong> <span id="md-pname"></span></div>
                        <div class="col-6 mb-2"><strong>Age:</strong> <span id="md-page"></span></div>
                        <div class="col-6 mb-2"><strong>Blood Group:</strong> <span class="badge bg-danger" id="md-pblood"></span></div>
                        <div class="col-6 mb-2"><strong>Condition:</strong> <span class="text-capitalize" id="md-pcond"></span></div>
                        <div class="col-12 mt-2"><strong>Target Organ:</strong> <span class="organ-tag" id="md-porgan"></span></div>
                    </div>
                    
                    <h6 class="fw-bold text-muted border-bottom pb-2 mb-3 mt-4">Donor Matching Status</h6>
                    <div id="md-donor-info" class="p-3 bg-light rounded border">
                        <!-- Loaded via JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function showDetails(data) {
            document.getElementById('md-pname').innerText = data.patient_name;
            document.getElementById('md-page').innerText = data.age;
            document.getElementById('md-pblood').innerText = data.patient_blood;
            document.getElementById('md-pcond').innerText = data.condition;
            document.getElementById('md-porgan').innerText = data.organ_needed;
            
            const donorDiv = document.getElementById('md-donor-info');
            if (data.donor_name) {
                donorDiv.innerHTML = `
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-person-check-fill text-success fs-4 me-2"></i>
                        <span class="fs-5 fw-bold text-dark">\${data.donor_name}</span>
                    </div>
                    <div><strong>Donor ID:</strong> #\${data.donor_id}</div>
                    <div><strong>Donor Blood:</strong> \${data.donor_blood}</div>
                    <div class="mt-2 text-primary fw-bold"><i class="bi bi-info-circle me-1"></i>Response State: \${(data.response || '').toUpperCase()}</div>
                `;
            } else {
                donorDiv.innerHTML = `
                    <div class="text-center text-muted py-2">
                        <i class="bi bi-search fs-3"></i>
                        <p class="mb-0 mt-2">No matching donor has responded to this request yet.</p>
                    </div>
                `;
            }
            
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }
    </script>
</body>
</html>
