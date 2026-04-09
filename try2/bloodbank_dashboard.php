<?php
session_start();

// 1. Session check: Only allow role = 'blood_bank'
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'blood_bank') {
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

$user_id = $_SESSION['user_id'];
$message = "";

// --- 5. Features Logic ---

// A & B: Add/Update Inventory
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_inventory'])) {
    $blood_group = $_POST['blood_group'];
    $units = intval($_POST['units_available']);
    $expiry = $_POST['expiry_date'];
    $inventory_id = $_POST['inventory_id'] ?? null;

    // 9. Validation
    $today = date('Y-m-d');
    if ($units <= 0) {
        $message = "<div class='alert alert-danger'>Units must be a positive number.</div>";
    } elseif ($expiry <= $today) {
        $message = "<div class='alert alert-danger'>Expiry date must be in the future.</div>";
    } else {
        if ($inventory_id) {
            // Update
            $stmt = $pdo->prepare("UPDATE blood_inventory SET blood_group = ?, units_available = ?, expiry_date = ? WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$blood_group, $units, $expiry, $inventory_id, $user_id])) {
                $message = "<div class='alert alert-success'>Inventory updated successfully!</div>";
            }
        } else {
            // Add
            $stmt = $pdo->prepare("INSERT INTO blood_inventory (user_id, blood_group, units_available, expiry_date) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$user_id, $blood_group, $units, $expiry])) {
                $message = "<div class='alert alert-success'>Blood units added successfully!</div>";
            }
        }
    }
}

// C: Delete Inventory (Optional)
if (isset($_GET['delete_inventory'])) {
    $id = intval($_GET['delete_inventory']);
    $stmt = $pdo->prepare("DELETE FROM blood_inventory WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$id, $user_id])) {
        $message = "<div class='alert alert-warning'>Inventory record deleted.</div>";
    }
}

// 7. Add Donation Camp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_camp'])) {
    $camp_date = $_POST['camp_date'];
    $location = $_POST['location'];
    $description = $_POST['description'];

    if ($camp_date <= date('Y-m-d')) {
        $message = "<div class='alert alert-danger'>Camp date must be in the future.</div>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO blood_donation_camps (user_id, camp_date, location, description) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $camp_date, $location, $description])) {
            $message = "<div class='alert alert-success'>Donation camp scheduled successfully!</div>";
        }
    }
}

// --- Fetch Data ---

// 3. Blood bank profile
$stmtProf = $pdo->prepare("SELECT * FROM blood_bank_profiles WHERE user_id = ?");
$stmtProf->execute([$user_id]);
$profile = $stmtProf->fetch(PDO::FETCH_ASSOC);

// Fallback: If profile not found, redirect to complete_profile.php
if (!$profile) {
    header("Location: complete_profile.php");
    exit();
}

// 6. Inventory table
$stmtInv = $pdo->prepare("SELECT * FROM blood_inventory WHERE user_id = ? ORDER BY blood_group ASC");
$stmtInv->execute([$user_id]);
$inventory = $stmtInv->fetchAll(PDO::FETCH_ASSOC);

// 7. Donation camps
$stmtCamps = $pdo->prepare("SELECT * FROM blood_donation_camps WHERE user_id = ? ORDER BY camp_date ASC");
$stmtCamps->execute([$user_id]);
$camps = $stmtCamps->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Bank Dashboard | Supply Management</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        :root {
            --primary-red: #e63946;
            --dark-red: #c1121f;
            --soft-bg: #f8f9fa;
            --glass: rgba(255, 255, 255, 0.9);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--soft-bg);
            color: #2b2d42;
        }

        .navbar {
            background: linear-gradient(135deg, var(--dark-red) 0%, var(--primary-red) 100%);
            box-shadow: 0 4px 12px rgba(230, 57, 70, 0.2);
        }

        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('https://images.unsplash.com/photo-1615461066870-43b22039ecad?auto=format&fit=crop&q=80&w=2000');
            background-size: cover;
            background-position: center;
            height: 250px;
            display: flex;
            align-items: center;
            color: white;
            border-radius: 0 0 40px 40px;
            margin-bottom: -50px;
        }

        .dashboard-container {
            position: relative;
            z-index: 10;
        }

        .custom-card {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin-bottom: 2rem;
            transition: transform 0.3s ease;
        }

        .custom-card:hover {
            transform: translateY(-5px);
        }

        .btn-red {
            background-color: var(--primary-red);
            color: white;
            border-radius: 12px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-red:hover {
            background-color: var(--dark-red);
            color: white;
            box-shadow: 0 4px 15px rgba(230, 57, 70, 0.3);
        }

        .table thead th {
            background-color: rgba(230, 57, 70, 0.05);
            color: var(--dark-red);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
            border: none;
        }

        .table td {
            vertical-align: middle;
            border-bottom: 1px solid #eee;
        }

        .badge-blood {
            background: rgba(230, 57, 70, 0.1);
            color: var(--primary-red);
            font-weight: 800;
            padding: 0.5rem 1rem;
            border-radius: 10px;
        }

        .profile-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-red);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            font-size: 1.5rem;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 25px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
            color: white;
            border-radius: 25px 25px 0 0;
        }

        .form-control {
            border-radius: 12px;
            padding: 0.8rem;
            border: 1px solid #ddd;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-dark navbar-expand-lg py-3 sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="#">
                <i class="bi bi-droplet-fill me-2 fs-3"></i>
                LifeStream <span class="fw-light ms-1">| Blood Bank</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <span class="text-white opacity-75 me-4">Welcome,
                            <strong><?php echo htmlspecialchars($profile['bank_name'] ?? 'Blood Bank'); ?></strong></span>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="btn btn-light rounded-pill px-4 text-danger fw-bold shadow-sm">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="hero-section">
        <div class="container py-4">
            <h1 class="display-4 fw-bold">Management Dashboard</h1>
            <p class="lead">Track your inventory and save lives through donation camps.</p>
        </div>
    </div>

    <div class="container dashboard-container">
        <?php echo $message; ?>

        <div class="row">
            <!-- a) Profile Section -->
            <div class="col-lg-4">
                <div class="custom-card">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <h5 class="fw-bold m-0"><i class="bi bi-info-circle me-2 text-danger"></i>Bank Profile</h5>
                        <span
                            class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3">Verified</span>
                    </div>
                    <?php if ($profile): ?>
                        <div class="profile-info mb-4">
                            <div class="profile-icon">
                                <i class="bi bi-hospital"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($profile['bank_name']); ?></h6>
                                <small class="text-muted">Registered Blood Bank</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="small text-muted text-uppercase fw-bold">Location</label>
                            <p class="mb-0"><i
                                    class="bi bi-geo-alt me-2 text-primary"></i><?php echo htmlspecialchars($profile['location']); ?>
                            </p>
                        </div>
                        <div class="mb-1">
                            <label class="small text-muted text-uppercase fw-bold">License Number</label>
                            <p class="mb-0"><i
                                    class="bi bi-file-earmark-check me-2 text-success"></i><code><?php echo htmlspecialchars($profile['license_number']); ?></code>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 mb-0">Profile incomplete. Please contact admin.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- b) Blood Inventory Management -->
            <div class="col-lg-8">
                <div class="custom-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold m-0"><i class="bi bi-box-seam me-2 text-danger"></i>Inventory Tracking</h5>
                        <button class="btn btn-red btn-sm" data-bs-toggle="modal" data-bs-target="#inventoryModal">
                            <i class="bi bi-plus-lg me-1"></i>Add Units
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Group</th>
                                    <th>Units</th>
                                    <th>Expiry Date</th>
                                    <th>Last Updated</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($inventory) > 0): ?>
                                    <?php foreach ($inventory as $row): ?>
                                        <tr>
                                            <td><span class="badge-blood"><?php echo $row['blood_group']; ?></span></td>
                                            <td><strong><?php echo $row['units_available']; ?></strong> <small
                                                    class="text-muted">Units</small></td>
                                            <td>
                                                <?php
                                                $expiry = strtotime($row['expiry_date']);
                                                $is_expired = $expiry < time();
                                                ?>
                                                <span class="<?php echo $is_expired ? 'text-danger fw-bold' : 'text-dark'; ?>">
                                                    <?php echo date('M d, Y', $expiry); ?>
                                                    <?php if ($is_expired)
                                                        echo ' (Expired)'; ?>
                                                </span>
                                            </td>
                                            <td><small
                                                    class="text-muted"><?php echo date('M d, H:i', strtotime($row['last_updated'])); ?></small>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary border-0"
                                                    onclick='editInventory(<?php echo json_encode($row); ?>)'
                                                    data-bs-toggle="modal" data-bs-target="#inventoryModal">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <a href="?delete_inventory=<?php echo $row['id']; ?>"
                                                    class="btn btn-sm btn-outline-danger border-0"
                                                    onclick="return confirm('Delete this record?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-5">No inventory records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- c) Donation Camps Management -->
        <div class="row">
            <div class="col-12">
                <div class="custom-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold m-0"><i class="bi bi-calendar-event me-2 text-danger"></i>Health Camps &
                            Drives</h5>
                        <button class="btn btn-outline-dark btn-sm rounded-pill px-4" data-bs-toggle="modal"
                            data-bs-target="#campModal">
                            <i class="bi bi-calendar-plus me-1"></i>Schedule New Camp
                        </button>
                    </div>

                    <div class="row">
                        <?php if (count($camps) > 0): ?>
                            <?php foreach ($camps as $camp): ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">
                                                <?php echo date('M d, Y', strtotime($camp['camp_date'])); ?>
                                            </span>
                                            <i class="bi bi-pin-map text-danger"></i>
                                        </div>
                                        <h6 class="fw-bold mt-2"><?php echo htmlspecialchars($camp['location']); ?></h6>
                                        <p class="small text-muted mb-3"><?php echo htmlspecialchars($camp['description']); ?>
                                        </p>
                                        <div class="mt-auto pt-3 border-top">
                                            <small class="text-muted d-block">Organized by</small>
                                            <span
                                                class="fw-bold text-dark"><?php echo htmlspecialchars($profile['bank_name'] ?? 'Your Bank'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 text-center py-5">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p class="mt-3 text-muted">No donation camps scheduled yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Manage Inventory -->
    <div class="modal fade" id="inventoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold" id="invModalTitle">Add Blood Units</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="manage_inventory" value="1">
                        <input type="hidden" name="inventory_id" id="inventory_id">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Blood Group</label>
                            <select name="blood_group" id="blood_group" class="form-control" required>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Units Available</label>
                            <input type="number" name="units_available" id="units_available" class="form-control"
                                placeholder="e.g. 50" min="1" required>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-bold">Expiry Date</label>
                            <input type="date" name="expiry_date" id="expiry_date" class="form-control"
                                min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-red px-5 shadow-sm">Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Add Camp -->
    <div class="modal fade" id="campModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 bg-dark text-white">
                    <h5 class="modal-title fw-bold">Schedule Donation Camp</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="add_camp" value="1">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Camp Date</label>
                            <input type="date" name="camp_date" class="form-control"
                                min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Location</label>
                            <input type="text" name="location" class="form-control" placeholder="Venue Address"
                                required>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-bold">Description</label>
                            <textarea name="description" class="form-control" rows="3"
                                placeholder="Additional details about the camp..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark px-5 shadow-sm">Post Camp</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function editInventory(data) {
            document.getElementById('invModalTitle').innerText = 'Update Blood Units';
            document.getElementById('inventory_id').value = data.id;
            document.getElementById('blood_group').value = data.blood_group;
            document.getElementById('units_available').value = data.units_available;
            document.getElementById('expiry_date').value = data.expiry_date;
        }

        // Reset modal on close
        document.getElementById('inventoryModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('invModalTitle').innerText = 'Add Blood Units';
            document.getElementById('inventory_id').value = '';
            document.getElementById('blood_group').selectedIndex = 0;
            document.getElementById('units_available').value = '';
            document.getElementById('expiry_date').value = '';
        });
    </script>

</body>

</html>