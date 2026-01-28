<?php
// revenue2/citizen_dashboard/rpt/rpt_application/view_rpt_bill.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$registration_id = $_GET['registration_id'] ?? 0;

require_once '../../../db/RPT/rpt_db.php';
$pdo = getDatabaseConnection();

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Function to format date
function formatDate($date, $format = 'F j, Y') {
    return ($date && $date != '0000-00-00' && $date != '0000-00-00 00:00:00') ? date($format, strtotime($date)) : '-';
}

// Fetch application details
$application = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            pr.*,
            po.first_name,
            po.last_name,
            po.middle_name,
            po.suffix,
            po.address as owner_address,
            po.city as owner_city,
            po.province as owner_province
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        WHERE pr.id = ? AND po.user_id = ?
    ");
    $stmt->execute([$registration_id, $user_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching application: " . $e->getMessage());
}

if (!$application) {
    die("Application not found or access denied.");
}

// Fetch land assessment data
$land_data = [];
try {
    $land_stmt = $pdo->prepare("
        SELECT lp.*, lc.classification
        FROM land_properties lp
        JOIN land_configurations lc ON lp.land_config_id = lc.id
        WHERE lp.registration_id = ?
    ");
    $land_stmt->execute([$registration_id]);
    $land_data = $land_stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching land data: " . $e->getMessage());
}

// Fetch building assessment data
$building_data = [];
$total_building_tax = 0;
if ($application['has_building'] == 'yes') {
    try {
        $building_stmt = $pdo->prepare("
            SELECT bp.*, pc.material_type
            FROM building_properties bp
            JOIN property_configurations pc ON bp.property_config_id = pc.id
            WHERE bp.land_id = ?
        ");
        $building_stmt->execute([$land_data['id'] ?? 0]);
        $building_data = $building_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($building_data as $building) {
            $total_building_tax += $building['annual_tax'] ?? 0;
        }
    } catch(PDOException $e) {
        die("Error fetching building data: " . $e->getMessage());
    }
}

// Fetch total tax data
$total_tax_data = [];
try {
    $total_stmt = $pdo->prepare("
        SELECT * FROM property_totals 
        WHERE registration_id = ?
    ");
    $total_stmt->execute([$registration_id]);
    $total_tax_data = $total_stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching total tax data: " . $e->getMessage());
}

// Fetch quarterly taxes for current year
$quarterly_taxes = [];
$current_year = date('Y');
if (!empty($total_tax_data)) {
    try {
        $quarterly_stmt = $pdo->prepare("
            SELECT * FROM quarterly_taxes 
            WHERE property_total_id = ? AND year = ?
            ORDER BY FIELD(quarter, 'Q1', 'Q2', 'Q3', 'Q4')
        ");
        $quarterly_stmt->execute([$total_tax_data['id'], $current_year]);
        $quarterly_taxes = $quarterly_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        die("Error fetching quarterly taxes: " . $e->getMessage());
    }
}

// Calculate totals
$total_land_tax = $land_data['annual_tax'] ?? 0;
$total_annual_tax = $total_land_tax + $total_building_tax;
$quarterly_amount = $total_annual_tax / 4;

// Check discount eligibility
function isEligibleForAnnualDiscount($quarterly_taxes) {
    $current_date = date('Y-m-d');
    $current_month = date('n');
    $current_day = date('j');
    
    if ($current_month != 1 || $current_day > 31) {
        return false;
    }
    
    foreach ($quarterly_taxes as $quarter) {
        if ($quarter['payment_status'] == 'paid' || $quarter['payment_status'] == 'overdue') {
            return false;
        }
    }
    
    return true;
}

// Get discount percentage
function getDiscountPercentage($pdo) {
    try {
        $discount_stmt = $pdo->prepare("
            SELECT discount_percent 
            FROM discount_configurations 
            WHERE status = 'active' 
            LIMIT 1
        ");
        $discount_stmt->execute();
        $discount = $discount_stmt->fetch(PDO::FETCH_ASSOC);
        
        return $discount['discount_percent'] ?? 10.00;
    } catch(PDOException $e) {
        return 10.00;
    }
}

$eligible_for_discount = isEligibleForAnnualDiscount($quarterly_taxes);
$discount_percent = getDiscountPercentage($pdo);
$discount_amount = $eligible_for_discount ? ($total_annual_tax * ($discount_percent / 100)) : 0;
$discounted_total = $eligible_for_discount ? ($total_annual_tax - $discount_amount) : $total_annual_tax;

// Generate billing reference number
$billing_reference = "RPT-BILL-" . date('Ymd-His') . "-" . rand(1000, 9999);
$bill_date = date('F j, Y');

// Get owner name
$owner_name = trim($application['first_name'] . ' ' . 
    (!empty($application['middle_name']) ? $application['middle_name'] . ' ' : '') . 
    $application['last_name'] . 
    (!empty($application['suffix']) ? ' ' . $application['suffix'] : ''));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPT Billing Statement - <?php echo $application['reference_number']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;