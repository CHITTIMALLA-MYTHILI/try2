<?php
$host = 'localhost';
$dbname = 'organ_blood_donation';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Create blood_bank_profiles
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blood_bank_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            bank_name VARCHAR(255) NOT NULL,
            location VARCHAR(255) NOT NULL,
            license_number VARCHAR(100) NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "Table blood_bank_profiles ensured.\n";

    // 2. Create/Update blood_inventory
    // Check if blood_inventory exists and drop if it has old structure (or just create new one it)
    $pdo->exec("DROP TABLE IF EXISTS blood_inventory");
    $pdo->exec("
        CREATE TABLE blood_inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            blood_bank_id INT NOT NULL,
            blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
            units_available INT NOT NULL DEFAULT 0,
            expiry_date DATE NOT NULL,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (blood_bank_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "Table blood_inventory initialized.\n";

    // 3. Ensure sample profile for existing blood_bank users
    $stmtUsers = $pdo->query("SELECT id, name FROM users WHERE role = 'blood_bank'");
    while ($user = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
        $stmtCheck = $pdo->prepare("SELECT id FROM blood_bank_profiles WHERE user_id = ?");
        $stmtCheck->execute([$user['id']]);
        if (!$stmtCheck->fetch()) {
            $stmtInsert = $pdo->prepare("INSERT INTO blood_bank_profiles (user_id, bank_name, location, license_number) VALUES (?, ?, 'Main City', 'LIC-123456')");
            $stmtInsert->execute([$user['id'], $user['name']]);
            echo "Created sample profile for {$user['name']}.\n";
        }
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
