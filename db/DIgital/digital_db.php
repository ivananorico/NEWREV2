<?php
// revenue2/db/Digital/digital_db.php

function getDigitalDB() {
    $host = 'localhost';
    $dbname = 'digital';
    $username = 'root';
    $password = '';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        // Return null instead of dying, so the app can continue
        error_log("Digital DB Connection failed: " . $e->getMessage());
        return null;
    }
}
?>