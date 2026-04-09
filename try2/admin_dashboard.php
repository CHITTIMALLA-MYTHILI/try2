<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
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

    // Fetch Overview Stats
    $stats = [
        'patients'  => $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn(),
        'donors'    => $pdo->query("SELECT COUNT(*) FROM donors")->fetchColumn(),
        'banks'     => $pdo->query("SELECT COUNT(*) FROM blood_banks")->fetchColumn(),
        'hospitals' => $pdo->query("SELECT COUNT(*) FROM hospitals")->fetchColumn(),
        'pending'   => $pdo->query("SELECT COUNT(*) FROM patients WHERE status IN ('pending', 'waiting_for_donor')")->fetchColumn(),
        'fulfilled' => $pdo->query("SELECT COUNT(*) FROM patients WHERE status = 'fulfilled'")->fetchColumn(),
    ];

    // Data for Charts
    $chartData = [
        'blood'    => $pdo->query("SELECT COUNT(*) FROM patients WHERE request_type = 'blood'")->fetchColumn(),
        'organ'    => $pdo->query("SELECT COUNT(*) FROM patients WHERE request_type = 'organ'")->fetchColumn(),
        'approved' => $pdo->query("SELECT COUNT(*) FROM patients WHERE status = 'approved'")->fetchColumn(),
    ];

    // All patient requests sorted by priority
    $patients = $pdo->query("SELECT * FROM patients ORDER BY priority_score DESC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Organ & Blood System</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f6f9;
            color: #333;
        }

        /* Dark Premium Blue Header */
        .admin-header {
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border-bottom: 4px solid #11cdef;
        }

        /* Overview Cards */
        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border-left: 5px solid transparent;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2sease, box-shadow 0.2s ease;
        }
        
        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .stat-box.primary { border-left-color: #5e72e4; }
        .stat-box.success { border-left-color: #2dce89; }
        .stat-box.info { border-left-color: #11cdef; }
        .stat-box.warning { border-left-color: #fb6340; }
        .stat-box.danger { border-left-color: #f5365c; }
        .stat-box.dark { border-left-color: #344675; }

        .stat-icon {
            font-size: 2.5rem;
            color: rgba(0,0,0,0.1);
            position: absolute;
            right: 20px;
            top: 20px;
        }

        /* Chart & Table Panels */
        .panel {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            margin-bottom: 2rem;
            padding: 1.5rem;
        }
        .panel-header {
            font-weight: 700;
            color: #32325d;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Table Styling */
        .table-hover tbody tr:hover {
            background-color: #f8f9fe;
            cursor: pointer;
        }
        .table th {
            text-transform: uppercase;
            font-size: 0.75rem;
            color: #8898aa;
            font-weight: 700;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9ecef !important;
        }
        .table td {
            vertical-align: middle;
            color: #525f7f;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Badges */
        .badge-soft {
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        .bg-soft-primary { background-color: rgba(94, 114, 228, 0.1); color: #5e72e4; }
        .bg-soft-warning { background-color: rgba(251, 99, 64, 0.1); color: #fb6340; }
        .bg-soft-danger { background-color: rgba(245, 54, 92, 0.1); color: #f5365c; }
        .bg-soft-success { background-color: rgba(45, 206, 137, 0.1); color: #2dce89; }
        .bg-soft-info { background-color: rgba(17, 205, 239, 0.1); color: #11cdef; }

        /* Filter Section */
        .filter-block {
            background: #fff;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="admin-header">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-shield-lock-fill text-info me-2"></i>Admin Dashboard</h2>
                <span class="opacity-75">System Overview & Monitoring Portal</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-light text-dark shadow-sm px-3 py-2"><i class="bi bi-broadcast me-1 text-success"></i> System Live</span>
                <a href="logout.php" class="btn btn-danger shadow-sm"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </header>

    <main class="container">
        <!-- Overview Cards -->
        <div class="row g-3 mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-box position-relative primary">
                    <p class="text-muted fw-bold mb-1" style="font-size: 0.8rem;">TOTAL PATIENTS</p>
                    <h3 class="mb-0 fw-bold"><?php echo $stats['patients']; ?></h3>
                    <i class="bi bi-people-fill stat-icon"></i>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-box position-relative success">
                    <p class="text-muted fw-bold mb-1" style="font-size: 0.8rem;">TOTAL DONORS</p>
                    <h3 class="mb-0 fw-bold"><?php echo $stats['donors']; ?></h3>
                    <i class="bi bi-person-heart stat-icon"></i>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-box position-relative danger">
                    <p class="text-muted fw-bold mb-1" style="font-size: 0.8rem;">BLOOD BANKS</p>
                    <h3 class="mb-0 fw-bold"><?php echo $stats['banks']; ?></h3>
                    <i class="bi bi-building-fill-add stat-icon"></i>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-box position-relative info">
                    <p class="text-muted fw-bold mb-1" style="font-size: 0.8rem;">HOSPITALS</p>
                    <h3 class="mb-0 fw-bold"><?php echo $stats['hospitals']; ?></h3>
                    <i class="bi bi-hospital-fill stat-icon"></i>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-box position-relative warning">
                    <p class="text-muted fw-bold mb-1" style="font-size: 0.8rem;">PENDING REQ.</p>
                    <h3 class="mb-0 fw-bold"><?php echo $stats['pending']; ?></h3>
                    <i class="bi bi-hourglass-top stat-icon"></i>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-box position-relative dark">
                    <p class="text-muted fw-bold mb-1" style="font-size: 0.8rem;">FULFILLED REQ.</p>
                    <h3 class="mb-0 fw-bold"><?php echo $stats['fulfilled']; ?></h3>
                    <i class="bi bi-check-circle-fill stat-icon"></i>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="panel h-100">
                    <div class="panel-header">
                        <span><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Requests By Type</span>
                    </div>
                    <canvas id="reqBarChart" height="100"></canvas>
                </div>
            </div>
            <div class="col-md-4">
                <div class="panel h-100">
                    <div class="panel-header">
                        <span><i class="bi bi-pie-chart-fill me-2 text-danger"></i>Status Distribution</span>
                    </div>
                    <canvas id="statusPieChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-block mb-4 d-flex gap-3 align-items-center flex-wrap">
            <div class="fw-bold text-muted"><i class="bi bi-funnel-fill me-1"></i> Filters:</div>
            <select id="filterType" class="form-select w-auto form-select-sm" onchange="filterTable()">
                <option value="all">All Types</option>
                <option value="Blood">Blood</option>
                <option value="Organ">Organ</option>
            </select>
            <select id="filterCondition" class="form-select w-auto form-select-sm" onchange="filterTable()">
                <option value="all">All Conditions</option>
                <option value="Critical">Critical</option>
                <option value="Urgent">Urgent</option>
                <option value="Normal">Normal</option>
            </select>
            <select id="filterStatus" class="form-select w-auto form-select-sm" onchange="filterTable()">
                <option value="all">All Statuses</option>
                <option value="Pending">Pending / Waiting</option>
                <option value="Approved">Approved</option>
                <option value="Fulfilled">Fulfilled</option>
            </select>
            <button class="btn btn-sm btn-light border ms-auto" onclick="resetFilters()"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
        </div>

        <!-- Priority Table Panel -->
        <div class="panel">
            <div class="panel-header">
                <span><i class="bi bi-list-columns-reverse me-2 text-dark"></i>Patient Priority Queue</span>
                <span class="badge bg-danger rounded-pill px-3">Live</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="priorityTable">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Request Type</th>
                            <th>Priority Score</th>
                            <th>Condition</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($patients as $p): ?>
                            <tr class="patient-row">
                                <td class="fw-bold text-dark">
                                    <i class="bi bi-person text-muted me-1"></i> <?php echo htmlspecialchars($p['name']); ?>
                                </td>
                                <td class="req-type-col">
                                    <?php if($p['request_type'] == 'blood'): ?>
                                        <span class="text-danger fw-bold"><i class="bi bi-droplet-fill me-1"></i>Blood</span>
                                    <?php else: ?>
                                        <span class="text-primary fw-bold"><i class="bi bi-lungs-fill me-1"></i>Organ</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $score = $p['priority_score'];
                                        $scoreClass = $score >= 80 ? 'text-danger' : ($score >= 50 ? 'text-warning' : 'text-success');
                                    ?>
                                    <h5 class="mb-0 fw-bold <?php echo $scoreClass; ?>"><?php echo htmlspecialchars($score); ?></h5>
                                </td>
                                <td class="cond-col">
                                    <?php 
                                        $cond = ucfirst($p['condition']);
                                        $cClass = '';
                                        if($cond == 'Critical') $cClass = 'bg-soft-danger';
                                        elseif($cond == 'Urgent') $cClass = 'bg-soft-warning';
                                        else $cClass = 'bg-soft-info';
                                    ?>
                                    <span class="badge-soft <?php echo $cClass; ?>"><i class="bi bi-activity me-1"></i><?php echo $cond; ?></span>
                                </td>
                                <td class="status-col">
                                    <?php 
                                        // Standardize status text for frontend filtering
                                        $rawStatus = strtolower($p['status']);
                                        $statText = ucfirst($rawStatus);
                                        $sClass = 'bg-soft-warning';
                                        
                                        if($rawStatus == 'waiting_for_donor' || $rawStatus == 'pending') {
                                            $statText = 'Pending'; 
                                            $sClass = 'bg-soft-warning';
                                        } elseif($rawStatus == 'approved') {
                                            $sClass = 'bg-soft-info';
                                        } elseif($rawStatus == 'fulfilled') {
                                            $sClass = 'bg-soft-success';
                                        }
                                    ?>
                                    <span class="badge-soft <?php echo $sClass; ?>"><?php echo $statText; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(count($patients) === 0): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No active patients found in the system.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Chart Data passed from PHP
        const chartData = {
            blood: <?php echo $chartData['blood']; ?>,
            organ: <?php echo $chartData['organ']; ?>,
            pending: <?php echo $stats['pending']; ?>,
            approved: <?php echo $chartData['approved']; ?>,
            fulfilled: <?php echo $stats['fulfilled']; ?>
        };

        // Render Bar Chart
        const ctxBar = document.getElementById('reqBarChart').getContext('2d');
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: ['Blood Requests', 'Organ Requests'],
                datasets: [{
                    label: 'Total Requests',
                    data: [chartData.blood, chartData.organ],
                    backgroundColor: [
                        'rgba(245, 54, 92, 0.8)', // Danger Red for Blood
                        'rgba(94, 114, 228, 0.8)'  // Primary Blue for Organ
                    ],
                    borderRadius: 6,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Render Pie Chart
        const ctxPie = document.getElementById('statusPieChart').getContext('2d');
        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Approved', 'Fulfilled'],
                datasets: [{
                    data: [chartData.pending, chartData.approved, chartData.fulfilled],
                    backgroundColor: [
                        '#fb6340', // Warning Orange
                        '#11cdef', // Info Blue
                        '#2dce89'  // Success Green
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                cutout: '70%',
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Filter Logic for Table
        function filterTable() {
            const typeFilter = document.getElementById('filterType').value.toLowerCase();
            const condFilter = document.getElementById('filterCondition').value.toLowerCase();
            const statFilter = document.getElementById('filterStatus').value.toLowerCase();
            
            const rows = document.querySelectorAll('.patient-row');
            
            rows.forEach(row => {
                const typeText = row.querySelector('.req-type-col').innerText.toLowerCase();
                const condText = row.querySelector('.cond-col').innerText.toLowerCase();
                const statText = row.querySelector('.status-col').innerText.toLowerCase();
                
                const matchType = typeFilter === 'all' || typeText.includes(typeFilter);
                const matchCond = condFilter === 'all' || condText.includes(condFilter);
                const matchStat = statFilter === 'all' || statText.includes(statFilter);
                
                if (matchType && matchCond && matchStat) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function resetFilters() {
            document.getElementById('filterType').value = 'all';
            document.getElementById('filterCondition').value = 'all';
            document.getElementById('filterStatus').value = 'all';
            filterTable();
        }
    </script>
</body>
</html>
