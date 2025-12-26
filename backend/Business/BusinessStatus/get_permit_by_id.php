<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once '../../../db/Business/business_db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(["status" => "error", "message" => "ID required"]);
    exit;
}

// Fetch the permit
$sql = "SELECT * FROM business_permits WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$permit = $stmt->fetch(PDO::FETCH_ASSOC);

if ($permit) {
    // Fetch quarterly taxes for this permit
    $taxSql = "SELECT * FROM business_quarterly_taxes WHERE business_permit_id = ?";
    $taxStmt = $pdo->prepare($taxSql);
    $taxStmt->execute([$id]);
    $quarterlyTaxes = $taxStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "permit" => $permit,
        "quarterlyTaxes" => $quarterlyTaxes
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Not found"]);
}
