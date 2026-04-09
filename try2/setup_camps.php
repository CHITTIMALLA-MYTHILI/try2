<?php
$host = 'localhost';
$dbname = 'organ_blood_donation';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Drop existing if it has wrong structure
    $pdo->exec("DROP TABLE IF EXISTS blood_donation_camps");

    // 2. Create the exact table required
    $pdo->exec("
        CREATE TABLE blood_donation_camps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            blood_bank_id INT NOT NULL,
            camp_date DATE NOT NULL,
            location VARCHAR(255) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (blood_bank_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "Table blood_donation_camps created.\n";

    // 3. Ensure there is at least one blood_bank user
    $stmtUser = $pdo->prepare("SELECT id FROM users WHERE role = 'blood_bank' LIMIT 1");
    $stmtUser->execute();
    $bankUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$bankUser) {
        $pdo->exec("INSERT INTO users (name, email, phone, password, age, role) VALUES ('Central Blood Bank', 'central@bb.com', '9998887776', 'hashedpass', 0, 'blood_bank')");
        $bankUserId = $pdo->lastInsertId();
    } else {
        $bankUserId = $bankUser['id'];
    }

    // 4. Insert sample data
    $stmtInsert = $pdo->prepare("
        INSERT INTO blood_donation_camps (blood_bank_id, camp_date, location, description) 
        VALUES (?, ?, ?, ?)
    ");
    // Generate a date in the future
    $futureDate = date('Y-m-d', strtotime('+10 days'));
    $stmtInsert->execute([$bankUserId, $futureDate, 'City Hall Plaza, Downtown', 'Annual Mega Blood Donation Drive. Refreshments provided.']);
    echo "Sample data inserted.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>