<?php
$host = 'localhost';
$dbname = 'organ_blood_donation';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    $tables = ['blood_bank_profiles', 'blood_inventory', 'blood_donation_camps'];
    foreach ($tables as $table) {
        echo "--- Table: $table ---\n";
        try {
            $stmt = $pdo->query("DESCRIBE $table");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                print_r($row);
            }
        } catch (Exception $e) {
            echo "Table $table does not exist or error: " . $e->getMessage() . "\n";
        }
    }
} catch (PDOException $e) {
    echo "Connection error: " . $e->getMessage() . "\n";
}
?>
