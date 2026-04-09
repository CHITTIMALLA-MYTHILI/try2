<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'blood_bank') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Profile | Blood Bank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body text-center py-5">
                        <h2 class="fw-bold mb-4">Complete Your Profile</h2>
                        <p class="text-muted mb-4">You must complete your blood bank profile details before accessing the dashboard.</p>
                        <!-- In a real app, this would be a form -->
                        <div class="alert alert-info">Profile setup logic would go here.</div>
                        <a href="bloodbank_dashboard.php" class="btn btn-primary">Refresh Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
