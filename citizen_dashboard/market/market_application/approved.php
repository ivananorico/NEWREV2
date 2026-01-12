<?php
// revenue2/citizen_dashboard/market/market_application/approved.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
include_once '../../../db/Market/market_db.php';

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Function to get document URL
function getDocumentUrl(string $dbPath): string
{
    $clean = str_replace(['../', './'], '', $dbPath);
    $clean = ltrim($clean, '/');
    if (strpos($clean, 'revenue2/') === 0) {
        $clean = substr($clean, strlen('revenue2/'));
    }
    return '/revenue2/' . $clean;
}

// Fetch user's approved applications
$applications = [];
$total_applications = 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            rr.*,
            ro.*,
            rs.business_name,
            rs.business_type,
            rs.start_date,
            rs.status as stall_status,
            rs.end_date as stall_end_date,
            s.name as stall_name,
            s.class_id,
            sr.class_name,
            sr.price as class_price,
            m.name as market_name,
            rr.payment_date,
            rr.reference_number,
            rr.stall_rights_no,
            rt.monthly_rent as contract_monthly_rent,
            rt.start_date as contract_start,
            rt.end_date as contract_end,
            rt.status as contract_status
        FROM rental_registration rr
        JOIN renter_owner ro ON rr.id = ro.registration_id
        LEFT JOIN rent_stall rs ON rr.id = rs.registration_id
        LEFT JOIN stalls s ON rr.stall_id = s.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        LEFT JOIN maps m ON rr.map_id = m.id
        LEFT JOIN rent_totals rt ON rr.id = rt.registration_id
        WHERE ro.user_id = ? 
          AND rr.application_status = 'approved'
        ORDER BY rr.payment_date DESC, rr.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_applications = count($applications);
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Applications - Market Rent Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge { 
            display: inline-flex; 
            align-items: center; 
            padding: 0.25rem 0.75rem; 
            border-radius: 9999px; 
            font-size: 0.75rem; 
            font-weight: 600; 
        }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-overdue { background-color: #fee2e2; color: #991b1b; }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-paid { background-color: #dbeafe; color: #1e40af; }
        .status-active { background-color: #dcfce7; color: #166534; }
        .status-inactive { background-color: #f3f4f6; color: #374151; }
        
        .card {
            background: white;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .info-section {
            background: #f9fafb;
            border-radius: 0.5rem;
            padding: 1rem;
        }
        
        .value-highlight {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
        }
        
        .value-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .progress-container {
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
            margin: 1rem 0;
        }
        .progress-fill {
            height: 100%;
            background: #059669;
            border-radius: 3px;
        }
        
        .info-card-header { 
            display: flex; 
            align-items: center; 
            margin-bottom: 1.25rem; 
            padding-bottom: 0.75rem; 
            border-bottom: 2px solid #f3f4f6; 
        }
        .icon-circle { 
            width: 48px; 
            height: 48px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-right: 1rem; 
        }
        .info-label { 
            font-size: 0.875rem; 
            color: #6b7280; 
            font-weight: 500; 
            margin-bottom: 0.25rem; 
        }
        .info-value { 
            font-size: 1rem; 
            color: #111827; 
            font-weight: 500; 
        }
        
        .section-header {
            border-left: 5px solid #4a90e2;
            padding-left: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            padding: 20px;
        }
        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90vh;
            border-radius: 8px;
        }
        .modal-close {
            position: absolute;
            top: 20px;
            right: 35px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            z-index: 1001;
        }
        .modal-close:hover {
            color: #fbbf24;
        }
        .modal-caption {
            text-align: center;
            color: white;
            padding: 10px 20px;
            position: absolute;
            bottom: 0;
            width: 100%;
            background: rgba(0,0,0,0.7);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
<?php include '../../navbar.php'; ?>

<!-- Image Modal -->
<div id="imageModal" class="modal">
    <span class="modal-close">&times;</span>
    <img class="modal-content" id="modalImage">
    <div id="modalCaption" class="modal-caption"></div>
</div>

<main class="max-w-6xl mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex items-center mb-4">
            <a href="../market_services.php" class="text-blue-600 hover:text-blue-800 mr-3 flex items-center">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Approved Applications</h1>
                <p class="text-gray-600">Your approved market stall rental applications</p>
            </div>
        </div>

        <!-- Summary -->
        <?php if ($total_applications > 0): ?>
        <div class="flex items-center mb-6">
            <div class="w-16 h-16 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-check text-green-600 text-2xl"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-gray-900"><?php echo $total_applications; ?> Approved Application<?php echo $total_applications > 1 ? 's' : ''; ?></div>
                <div class="text-sm text-gray-600">Stall rental contracts</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Error Message -->
    <?php if (isset($error_message)): ?>
        <div class="mb-4 p-4 bg-red-50 text-red-700 rounded-lg">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_applications === 0): ?>
        <!-- Empty State -->
        <div class="card p-8 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-gray-400 text-3xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Approved Applications</h3>
            <p class="text-gray-600 mb-6">You don't have any approved applications yet.</p>
            <div class="space-x-3">
                <a href="pending.php" class="inline-flex items-center px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    <i class="fas fa-clock mr-2"></i> Check Pending Applications
                </a>
                <a href="paid.php" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-receipt mr-2"></i> Check Paid Applications
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Applications List -->
        <div class="space-y-8">
            <?php foreach ($applications as $app): ?>
                <?php
                    $full_name = trim($app['first_name'] . ' ' . 
                        (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . 
                        $app['last_name'] . 
                        (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));
                    
                    // Format dates
                    $birth_date = !empty($app['birth_date']) ? date('M j, Y', strtotime($app['birth_date'])) : 'N/A';
                    $payment_date = !empty($app['payment_date']) ? date('F j, Y g:i A', strtotime($app['payment_date'])) : 'N/A';
                    $start_date = !empty($app['start_date']) ? date('F j, Y', strtotime($app['start_date'])) : 'Not set';
                    $contract_start = !empty($app['contract_start']) ? date('F j, Y', strtotime($app['contract_start'])) : 'Not set';
                    $contract_end = !empty($app['contract_end']) ? date('F j, Y', strtotime($app['contract_end'])) : 'Not set';
                    
                    // Build full address
                    $address_parts = [];
                    if (!empty($app['house_number'])) $address_parts[] = $app['house_number'];
                    if (!empty($app['street'])) $address_parts[] = $app['street'];
                    if (!empty($app['barangay'])) $address_parts[] = 'Brgy. ' . $app['barangay'];
                    if (!empty($app['city'])) $address_parts[] = $app['city'];
                    if (!empty($app['province'])) $address_parts[] = $app['province'];
                    if (!empty($app['zip_code'])) $address_parts[] = $app['zip_code'];
                    $full_address = implode(', ', $address_parts);
                    
                    // Get documents
                    $documents = [
                        'Barangay Clearance' => $app['barangay_clearance'] ?? '',
                        '2x2 ID Photo' => $app['id_photo_2x2'] ?? '',
                        'Valid ID' => $app['valid_id'] ?? ''
                    ];
                    
                    // Calculate amounts
                    $stall_rights_amount = $app['stall_rights_amount'] ?? 0;
                    $security_bond = $app['security_bond'] ?? 0;
                    $monthly_rent = $app['monthly_rent'] ?? 0;
                    $total_amount_paid = $stall_rights_amount + $security_bond;
                    $contract_monthly_rent = $app['contract_monthly_rent'] ?? $monthly_rent;
                    
                    // Get monthly rent billing
                    $monthly_bills = [];
                    $total_penalty = 0;
                    $paid_count = 0;
                    $pending_count = 0;
                    $overdue_count = 0;
                    $total_amount_due = 0;
                    
                    try {
                        // First get rent_total_id
                        $rent_total_stmt = $pdo->prepare("
                            SELECT id FROM rent_totals 
                            WHERE registration_id = ? 
                            AND status = 'active' 
                            LIMIT 1
                        ");
                        $rent_total_stmt->execute([$app['id']]);
                        $rent_total = $rent_total_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($rent_total) {
                            $billing_stmt = $pdo->prepare("
                                SELECT 
                                    mb.*,
                                    m.name as month_name
                                FROM monthly_rent_billing mb
                                WHERE mb.rent_total_id = ?
                                ORDER BY mb.billing_year, mb.billing_month
                            ");
                            $billing_stmt->execute([$rent_total['id']]);
                            $monthly_bills = $billing_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($monthly_bills as $bill) {
                                $total_penalty += $bill['penalty_amount'] ?? 0;
                                $total_amount_due += $bill['total_amount_due'] ?? 0;
                                
                                if ($bill['payment_status'] == 'paid') $paid_count++;
                                elseif ($bill['payment_status'] == 'overdue') $overdue_count++;
                                else $pending_count++;
                            }
                        }
                    } catch(PDOException $e) {
                        // Continue without billing data
                    }
                    
                    // Stall status
                    $stall_status = $app['stall_status'] ?? 'pending';
                    $status_badge_class = 'status-active';
                    $status_badge_text = 'Active';
                    $status_icon = 'fa-store';
                    
                    if ($stall_status === 'inactive') {
                        $status_badge_class = 'status-inactive';
                        $status_badge_text = 'Inactive';
                        $status_icon = 'fa-store-slash';
                    } elseif ($stall_status === 'pending') {
                        $status_badge_class = 'status-pending';
                        $status_badge_text = 'Pending';
                        $status_icon = 'fa-clock';
                    } elseif ($stall_status === 'expired') {
                        $status_badge_class = 'status-inactive';
                        $status_badge_text = 'Expired';
                        $status_icon = 'fa-calendar-times';
                    } elseif ($stall_status === 'terminated') {
                        $status_badge_class = 'status-inactive';
                        $status_badge_text = 'Terminated';
                        $status_icon = 'fa-ban';
                    }
                ?>

                <!-- Application Card -->
                <div class="card overflow-hidden">
                    <!-- Header -->
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                            <div>
                                <div class="flex items-center mb-2">
                                    <span class="text-xl font-bold text-gray-900 mr-4">
                                        <?php echo $app['stall_rights_no']; ?>
                                    </span>
                                    <span class="status-badge status-approved">
                                        <i class="fas fa-check-circle mr-1"></i>Approved
                                    </span>
                                    <span class="status-badge <?php echo $status_badge_class; ?> ml-2">
                                        <i class="fas <?php echo $status_icon; ?> mr-1"></i>
                                        <?php echo $status_badge_text; ?>
                                    </span>
                                </div>
                                <div class="text-gray-600 flex items-center">
                                    <i class="fas fa-store mr-2"></i>
                                    <span><?php echo $app['stall_name']; ?> • <?php echo $app['class_name']; ?> Class</span>
                                </div>
                            </div>
                            <div class="mt-3 md:mt-0 text-right">
                                <div class="text-sm text-gray-500">Contract Period</div>
                                <div class="font-medium text-gray-900">
                                    <?php echo date('M Y', strtotime($contract_start)); ?> - 
                                    <?php echo date('M Y', strtotime($contract_end)); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Progress Bar -->
                        <div>
                            <div class="progress-container">
                                <div class="progress-fill" style="width: 100%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>Application</span>
                                <span>Interview</span>
                                <span>Payment</span>
                                <span class="font-bold text-green-600">Approved</span>
                            </div>
                        </div>
                    </div>

                    <!-- Applicant Info & Stall Info Sections -->
                    <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Applicant Info -->
                        <div>
                            <div class="info-card-header">
                                <div class="icon-circle bg-blue-100 text-blue-600">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">Applicant</h3>
                                    <p class="text-sm text-gray-500">Your registered information</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($full_name); ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Renter Code</div>
                                    <div class="info-value font-bold text-blue-600"><?php echo $app['renter_code']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Birth Date</div>
                                    <div class="info-value"><?php echo $birth_date; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Gender</div>
                                    <div class="info-value">
                                        <?php 
                                        if (!empty($app['gender'])) {
                                            echo ucfirst($app['gender']);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="info-label">Contact</div>
                                    <div class="info-value"><?php echo $app['mobile']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo $app['email']; ?></div>
                                </div>
                                <?php if (!empty($app['telephone'])): ?>
                                <div>
                                    <div class="info-label">Telephone</div>
                                    <div class="info-value"><?php echo $app['telephone']; ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($app['emergency_name'])): ?>
                                <div>
                                    <div class="info-label">Emergency Contact</div>
                                    <div class="info-value"><?php echo $app['emergency_name']; ?> (<?php echo $app['emergency_contact']; ?>)</div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-4">
                                <div class="info-label">Address</div>
                                <div class="info-value text-sm"><?php echo htmlspecialchars($full_address); ?></div>
                            </div>
                        </div>

                        <!-- Stall Info -->
                        <div>
                            <div class="info-card-header">
                                <div class="icon-circle bg-green-100 text-green-600">
                                    <i class="fas fa-store"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">Stall Details</h3>
                                    <p class="text-sm text-gray-500">Stall and business information</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="info-label">Stall Name</div>
                                    <div class="info-value"><?php echo $app['stall_name']; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Stall Class</div>
                                    <div class="info-value font-bold text-blue-600"><?php echo $app['class_name']; ?> Class</div>
                                </div>
                                <div>
                                    <div class="info-label">Business Name</div>
                                    <div class="info-value"><?php echo !empty($app['business_name']) ? htmlspecialchars($app['business_name']) : 'N/A'; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Business Type</div>
                                    <div class="info-value"><?php echo !empty($app['business_type']) ? htmlspecialchars($app['business_type']) : 'N/A'; ?></div>
                                </div>
                                <?php if (!empty($app['market_name'])): ?>
                                <div>
                                    <div class="info-label">Market</div>
                                    <div class="info-value"><?php echo $app['market_name']; ?></div>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <div class="info-label">Contract Start</div>
                                    <div class="info-value"><?php echo $contract_start; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Contract End</div>
                                    <div class="info-value"><?php echo $contract_end; ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Stall Rights No.</div>
                                    <div class="info-value font-mono"><?php echo $app['stall_rights_no']; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment & Financial Summary -->
                    <div class="p-6 border-t border-gray-200">
                        <h3 class="font-semibold text-gray-900 mb-6 flex items-center">
                            <i class="fas fa-file-invoice-dollar text-blue-600 mr-2"></i> Financial Summary
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <!-- Initial Payment -->
                            <div class="info-section">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-receipt text-green-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Initial Payment</div>
                                        <div class="text-sm text-gray-500">Paid upon approval</div>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Stall Rights Fee:</span>
                                        <span class="font-medium text-red-600">
                                            <?php echo formatCurrency($stall_rights_amount); ?>
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Security Bond:</span>
                                        <span class="font-medium text-orange-600">
                                            <?php echo formatCurrency($security_bond); ?>
                                        </span>
                                    </div>
                                    <div class="pt-2 mt-2 border-t border-gray-200">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <div class="font-bold text-gray-900">Total Paid</div>
                                                <div class="text-xs text-green-600">
                                                    <i class="fas fa-check-circle mr-1"></i>Payment completed
                                                </div>
                                            </div>
                                            <div class="text-lg font-bold text-green-700">
                                                <?php echo formatCurrency($total_amount_paid); ?>
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            Reference: <?php echo $app['reference_number']; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Monthly Rent -->
                            <div class="info-section">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-calendar-alt text-blue-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Monthly Rent</div>
                                        <div class="text-sm text-blue-600">Contract rental fee</div>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <div class="text-gray-700 font-medium">Monthly Amount</div>
                                            <div class="text-sm text-gray-500">Due every month</div>
                                        </div>
                                        <div class="text-2xl font-bold text-blue-700">
                                            <?php echo formatCurrency($contract_monthly_rent); ?>
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Next payment due: 
                                        <?php 
                                        if (!empty($monthly_bills)) {
                                            $next_bill = null;
                                            foreach ($monthly_bills as $bill) {
                                                if ($bill['payment_status'] == 'pending') {
                                                    $next_bill = $bill;
                                                    break;
                                                }
                                            }
                                            if ($next_bill) {
                                                echo date('F j, Y', strtotime($next_bill['due_date']));
                                            } else {
                                                echo 'All paid';
                                            }
                                        } else {
                                            echo 'Starting ' . date('F j, Y', strtotime($contract_start));
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Contract Summary -->
                            <div class="info-section bg-blue-50 border border-blue-100">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-blue-200 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-file-contract text-blue-700 text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Contract Summary</div>
                                        <div class="text-sm text-blue-600">Rental agreement details</div>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-700">Contract Duration:</span>
                                        <span class="font-medium">
                                            <?php 
                                            $start = new DateTime($contract_start);
                                            $end = new DateTime($contract_end);
                                            $interval = $start->diff($end);
                                            $years = $interval->y;
                                            $months = $interval->m;
                                            echo ($years > 0 ? $years . ' year' . ($years > 1 ? 's' : '') . ' ' : '') . 
                                                 ($months > 0 ? $months . ' month' . ($months > 1 ? 's' : '') : '');
                                            ?>
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-700">Contract Value:</span>
                                        <span class="font-medium">
                                            <?php echo formatCurrency($contract_monthly_rent * 12); ?>
                                            <span class="text-sm text-gray-500">/year</span>
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-700">Security Bond:</span>
                                        <span class="font-medium">
                                            <?php echo formatCurrency($security_bond); ?>
                                            <span class="text-xs text-gray-500">(Refundable)</span>
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-500 text-center mt-3 pt-3 border-t border-blue-200">
                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                        Late payments incur <?php 
                                        try {
                                            $penalty_stmt = $pdo->prepare("SELECT penalty_percent FROM market_penalty_config WHERE expiration_date IS NULL OR expiration_date >= CURDATE() LIMIT 1");
                                            $penalty_stmt->execute();
                                            $penalty = $penalty_stmt->fetch(PDO::FETCH_ASSOC);
                                            echo $penalty['penalty_percent'] ?? '5';
                                        } catch(PDOException $e) {
                                            echo '5';
                                        }
                                        ?>% penalty
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Monthly Rent Billing Table -->
                        <?php if (!empty($monthly_bills)): ?>
                            <div class="mt-8">
                                <h4 class="text-lg font-semibold text-gray-800 mb-4 section-header">
                                    <i class="fas fa-calendar-alt mr-2"></i>Monthly Rent Billing (<?php echo $monthly_bills[0]['billing_year'] ?? date('Y'); ?>)
                                </h4>
                                
                                <!-- Monthly Billing Table -->
                                <div class="overflow-x-auto mb-6">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Base Rent</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Penalty</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Due</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($monthly_bills as $bill): 
                                                $days_late = $bill['days_late'] ?? 0;
                                                $penalty_amount = $bill['penalty_amount'] ?? 0;
                                                $discount_amount = $bill['discount_amount'] ?? 0;
                                                $base_rent = $bill['base_rent'] ?? 0;
                                                $total_due = $bill['total_amount_due'] ?? 0;
                                                $due_date = date('M d, Y', strtotime($bill['due_date']));
                                                $month_names = [
                                                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                                ];
                                            ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="font-semibold"><?php echo $month_names[$bill['billing_month']] ?? $bill['billing_month']; ?></span>
                                                        <span class="text-gray-600"> <?php echo $bill['billing_year']; ?></span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php echo $due_date; ?>
                                                        <?php if ($days_late > 0): ?>
                                                            <div class="text-xs text-red-600 mt-1">
                                                                (<?php echo $days_late; ?> days late)
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php echo formatCurrency($base_rent); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($penalty_amount > 0): ?>
                                                            <span class="text-red-600 font-semibold">
                                                                <?php echo formatCurrency($penalty_amount); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-green-600">₱0.00</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($discount_amount > 0): ?>
                                                            <span class="text-green-600 font-semibold">
                                                                -<?php echo formatCurrency($discount_amount); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">₱0.00</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap font-bold text-lg">
                                                        <?php echo formatCurrency($total_due); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($bill['payment_status'] == 'paid'): ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                <i class="fas fa-check-circle mr-1"></i> Paid
                                                            </span>
                                                            <?php if ($bill['payment_date']): ?>
                                                            <div class="text-xs text-gray-500 mt-1">
                                                                <?php echo date('M d', strtotime($bill['payment_date'])); ?>
                                                            </div>
                                                            <?php endif; ?>
                                                        <?php elseif ($bill['payment_status'] == 'overdue'): ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                                <i class="fas fa-exclamation-circle mr-1"></i> Overdue
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                                <i class="fas fa-clock mr-1"></i> Pending
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="bg-gray-50">
                                            <tr>
                                                <td colspan="2" class="px-6 py-4 font-bold text-right text-gray-900">
                                                    Totals:
                                                </td>
                                                <td class="px-6 py-4 font-bold text-gray-900">
                                                    <?php echo formatCurrency(array_sum(array_column($monthly_bills, 'base_rent'))); ?>
                                                </td>
                                                <td class="px-6 py-4 font-bold <?php echo $total_penalty > 0 ? 'text-red-600' : 'text-gray-900'; ?>">
                                                    <?php echo formatCurrency($total_penalty); ?>
                                                </td>
                                                <td class="px-6 py-4 font-bold <?php echo array_sum(array_column($monthly_bills, 'discount_amount')) > 0 ? 'text-green-600' : 'text-gray-900'; ?>">
                                                    -<?php echo formatCurrency(array_sum(array_column($monthly_bills, 'discount_amount'))); ?>
                                                </td>
                                                <td class="px-6 py-4 font-bold text-xl text-blue-700">
                                                    <?php echo formatCurrency($total_amount_due); ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php if ($overdue_count > 0): ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            <i class="fas fa-exclamation-circle mr-1"></i> Overdue
                                                        </span>
                                                    <?php elseif ($paid_count == count($monthly_bills)): ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            <i class="fas fa-check-circle mr-1"></i> Paid
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            <i class="fas fa-clock mr-1"></i> Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <!-- Payment Summary -->
                                <div class="info-section">
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                        <div>
                                            <div class="text-sm text-gray-600">Total Base Rent</div>
                                            <div class="text-xl font-bold text-gray-900">
                                                <?php echo formatCurrency(array_sum(array_column($monthly_bills, 'base_rent'))); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">For <?php echo count($monthly_bills); ?> months</div>
                                        </div>
                                        
                                        <div>
                                            <div class="text-sm text-gray-600">Total Penalties</div>
                                            <div class="text-xl font-bold <?php echo $total_penalty > 0 ? 'text-red-600' : 'text-gray-900'; ?>">
                                                <?php echo formatCurrency($total_penalty); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php if ($total_penalty > 0): ?>
                                                    Penalty applied to overdue payments
                                                <?php else: ?>
                                                    No penalties
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <div class="text-sm text-gray-600">Payment Status</div>
                                            <div class="text-xl font-bold <?php echo $overdue_count > 0 ? 'text-red-600' : ($paid_count == count($monthly_bills) ? 'text-green-600' : 'text-yellow-600'); ?>">
                                                <?php echo $overdue_count > 0 ? 'Overdue' : ($paid_count == count($monthly_bills) ? 'Paid' : 'Pending'); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo $paid_count; ?> paid, 
                                                <?php echo $pending_count; ?> pending, 
                                                <?php echo $overdue_count; ?> overdue
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <div class="text-sm text-gray-600">Total Amount Due</div>
                                            <div class="text-2xl font-bold text-blue-700">
                                                <?php echo formatCurrency($total_amount_due); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Documents Section -->
                    <div class="p-6 border-t border-gray-200">
                        <h3 class="font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-file-alt text-purple-600 mr-2"></i> Submitted Documents
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($documents as $label => $doc_path): ?>
                                <?php if (!empty($doc_path)): ?>
                                    <?php
                                        $doc_url = getDocumentUrl($doc_path);
                                        $file_ext = strtolower(pathinfo($doc_path, PATHINFO_EXTENSION));
                                        $is_image = in_array($file_ext, ['jpg','jpeg','png','gif']);
                                        $file_name = basename($doc_path);
                                    ?>
                                    <div class="border border-gray-200 rounded-lg p-4 text-center hover:border-blue-300 transition cursor-pointer" 
                                         onclick="openModal('<?php echo $doc_url; ?>','<?php echo $label; ?>')">
                                        <div class="mb-3">
                                            <?php if ($is_image): ?>
                                                <i class="fas fa-image text-blue-500 text-3xl"></i>
                                            <?php else: ?>
                                                <i class="fas fa-file-pdf text-red-500 text-3xl"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm font-medium text-gray-900"><?php echo $label; ?></div>
                                        <div class="text-xs text-gray-500 mt-1 truncate" title="<?php echo htmlspecialchars($file_name); ?>">
                                            <?php echo htmlspecialchars($file_name); ?>
                                        </div>
                                        <div class="mt-2">
                                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">Click to view</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="border border-gray-200 rounded-lg p-4 text-center opacity-50">
                                        <div class="mb-3">
                                            <i class="fas fa-times-circle text-gray-300 text-3xl"></i>
                                        </div>
                                        <div class="text-sm font-medium text-gray-900"><?php echo $label; ?></div>
                                        <div class="text-xs text-gray-500 mt-1">Not uploaded</div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-6 bg-gray-50 border-t border-gray-200">
                        <div class="flex flex-col md:flex-row md:items-center justify-between">
                            <div class="mb-4 md:mb-0">
                                <div class="font-medium text-gray-900">Stall Rental Agreement</div>
                                <div class="text-sm text-gray-600">
                                    <?php if ($paid_count == count($monthly_bills)): ?>
                                        <span class="text-green-700">
                                            <i class="fas fa-check-circle mr-1"></i> All rental payments are up to date
                                        </span>
                                    <?php elseif ($overdue_count > 0): ?>
                                        <span class="text-red-700 font-medium">
                                            <i class="fas fa-exclamation-triangle mr-1"></i> 
                                            <?php echo $overdue_count; ?> overdue payment<?php echo $overdue_count > 1 ? 's' : ''; ?>
                                        </span>
                                    <?php else: ?>
                                        <i class="fas fa-info-circle mr-1"></i> 
                                        Your stall rental contract is active. Monthly payments are due as per schedule.
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">
                                    Stall Rights No: <span class="font-mono font-medium text-gray-700"><?php echo $app['stall_rights_no']; ?></span>
                                </div>
                                <div class="text-sm text-gray-500">
                                    Renter Code: <span class="font-mono font-medium text-gray-700"><?php echo $app['renter_code']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
const modal = document.getElementById("imageModal");
const modalImg = document.getElementById("modalImage");
const captionText = document.getElementById("modalCaption");

function openModal(imageSrc, caption) {
    if (imageSrc) {
        modal.style.display = "block";
        modalImg.src = imageSrc;
        captionText.innerHTML = caption;
    } else {
        alert("No document available to view.");
    }
}

document.getElementsByClassName("modal-close")[0].onclick = () => modal.style.display = "none";
window.onclick = (event) => { if(event.target==modal) modal.style.display="none"; }
document.addEventListener('keydown', (event) => { if(event.key==='Escape' && modal.style.display==='block') modal.style.display='none'; });
</script>
</body>
</html>