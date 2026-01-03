<?php
// business_application_status/business_billing.php
session_start();
require_once '../../../db/Business/business_db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Citizen';

// Function to get discount percentage from config
function getDiscountPercentage($pdo) {
    try {
        $discount_stmt = $pdo->prepare("
            SELECT discount_percent 
            FROM business_discount_config 
            WHERE (expiration_date IS NULL OR expiration_date = '0000-00-00' OR expiration_date >= CURDATE())
            AND effective_date <= CURDATE()
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $discount_stmt->execute();
        $discount = $discount_stmt->fetch(PDO::FETCH_ASSOC);
        
        return $discount['discount_percent'] ?? 0.00;
    } catch(PDOException $e) {
        return 0.00; // Default no discount
    }
}

// Function to check if eligible for annual discount (Jan 1-31 only)
function isEligibleForAnnualDiscount($quarterly_taxes) {
    $current_date = date('Y-m-d');
    $current_month = date('n'); // 1-12
    $current_day = date('j'); // 1-31
    
    // Check if current date is January 1-31
    if ($current_month != 1 || $current_day > 31) {
        return false;
    }
    
    // Check if all quarters are unpaid
    foreach ($quarterly_taxes as $quarter) {
        if ($quarter['payment_status'] == 'paid') {
            return false;
        }
    }
    
    return true;
}

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Fetch all business permits for this user
try {
    $sql = "SELECT * FROM business_permits WHERE user_id = ? ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $permits = $stmt->fetchAll();
    
    // Get discount percentage from config
    $discount_percent = getDiscountPercentage($pdo);
    
    // Fetch quarterly taxes for each permit
    $quarterly_taxes = [];
    foreach ($permits as &$permit) {
        $tax_sql = "SELECT * FROM business_quarterly_taxes 
                   WHERE business_permit_id = ? 
                   ORDER BY year DESC, 
                   FIELD(quarter, 'Q4', 'Q3', 'Q2', 'Q1')";
        $tax_stmt = $pdo->prepare($tax_sql);
        $tax_stmt->execute([$permit['id']]);
        $permit_taxes = $tax_stmt->fetchAll();
        $quarterly_taxes[$permit['id']] = $permit_taxes;
        
        // Calculate if eligible for annual discount
        $eligible_for_discount = isEligibleForAnnualDiscount($permit_taxes);
        $permit['eligible_for_annual_discount'] = $eligible_for_discount;
        
        // Calculate discount amount if eligible
        if ($eligible_for_discount && $discount_percent > 0) {
            $annual_tax = $permit['total_tax'] ?? 0;
            $discount_amount = ($annual_tax * $discount_percent) / 100;
            $discounted_total = $annual_tax - $discount_amount;
            
            $permit['discount_percent'] = $discount_percent;
            $permit['discount_amount'] = $discount_amount;
            $permit['discounted_total'] = $discounted_total;
        }
        
        $tax_stmt = null;
    }
    $stmt = null;
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Business Billing & Status | GoServePH</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #9aa5b1;
            --accent: #4caf50;
            --warning: #f59e0b;
            --danger: #ef4444;
            --background: #fbfbfb;
        }

        body {
            background-color: var(--background);
            font-family: Inter, system-ui, sans-serif;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fbbf24;
        }

        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .status-active {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
        }

        .status-expired {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .info-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(74, 144, 226, 0.1);
        }

        .section-header {
            border-left: 5px solid var(--primary);
            padding-left: 1rem;
            margin-bottom: 1.5rem;
        }

        .tab-button {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.2s ease;
            border: 2px solid transparent;
        }

        .tab-button.active {
            background-color: var(--primary);
            color: white;
        }

        .tab-button:not(.active):hover {
            background-color: #f1f5f9;
        }

        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
            background-color: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 0.75rem;
        }
        
        .discount-banner {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
    </style>
</head>

<body class="flex flex-col min-h-screen">
<?php include '../../../citizen_dashboard/navbar.php'; ?>

<main class="container mx-auto px-4 py-8 flex-grow max-w-7xl">
    <!-- Simplified Header - Only arrow for back -->
    <div class="mb-8">
        <div class="flex items-center">
            <a href="../business_services.php" 
               class="inline-flex items-center text-gray-600 hover:text-[var(--primary)] mr-4 p-2 rounded-lg hover:bg-gray-100">
                <i class="fas fa-arrow-left text-lg"></i>
            </a>
            <div class="flex-1">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    Business Billing & Application Status
                </h1>
                <p class="text-gray-600">
                    View your business permit applications, status, and tax information
                </p>
            </div>
        </div>
        <div class="h-1 w-24 rounded-full mt-4" style="background-color: #4a90e2;"></div>
    </div>

    <?php if (empty($permits)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-folder-open text-5xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">No Business Applications Found</h3>
            <p class="text-gray-600 mb-6 max-w-md mx-auto">
                You haven't applied for any business permits yet. Start your application to manage your business taxes online.
            </p>
            <a href="../business_application.php" 
               class="inline-flex items-center px-6 py-3 rounded-lg font-semibold text-white"
               style="background-color: #4a90e2;">
                <i class="fas fa-plus mr-2"></i>
                Apply for Business Permit
            </a>
        </div>
    <?php else: ?>
        <!-- Applications List - All information displayed immediately -->
        <div class="space-y-6">
            <?php foreach ($permits as $permit): 
                $status_class = 'status-' . strtolower($permit['status']);
                $permit_taxes = $quarterly_taxes[$permit['id']] ?? [];
                
                // Count paid quarters
                $paid_count = 0;
                $pending_count = 0;
                $overdue_count = 0;
                $total_penalty = 0;
                $quarter_totals = [
                    'tax_amount' => 0,
                    'penalty' => 0,
                    'total' => 0
                ];
                
                foreach ($permit_taxes as $tax) {
                    $tax_amount = $tax['total_quarterly_tax'] ?? 0;
                    $penalty_amount = $tax['penalty_amount'] ?? 0;
                    $total_amount = $tax_amount + $penalty_amount;
                    
                    $quarter_totals['tax_amount'] += $tax_amount;
                    $quarter_totals['penalty'] += $penalty_amount;
                    $quarter_totals['total'] += $total_amount;
                    
                    if ($tax['payment_status'] == 'paid') $paid_count++;
                    elseif ($tax['payment_status'] == 'overdue') $overdue_count++;
                    else $pending_count++;
                    
                    $total_penalty += $penalty_amount;
                }
                
                $grand_total = ($permit['total_tax'] ?? 0) + $total_penalty;
            ?>
                <div class="info-card p-6">
                    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6">
                        <div>
                            <div class="flex items-center mb-2">
                                <h3 class="text-xl font-bold text-gray-900 mr-3">
                                    <?php echo htmlspecialchars($permit['business_name']); ?>
                                </h3>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $permit['status']; ?>
                                </span>
                            </div>
                            <div class="text-gray-600">
                                Permit ID: <span class="font-mono font-semibold"><?php echo htmlspecialchars($permit['business_permit_id']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Info -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-sm text-gray-500 mb-1">Business Type</div>
                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($permit['business_type']); ?></div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-sm text-gray-500 mb-1">Issue Date</div>
                            <div class="font-semibold text-gray-800">
                                <?php echo date('M d, Y', strtotime($permit['issue_date'])); ?>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-sm text-gray-500 mb-1">Expiry Date</div>
                            <div class="font-semibold text-gray-800">
                                <?php echo $permit['expiry_date'] ? date('M d, Y', strtotime($permit['expiry_date'])) : 'N/A'; ?>
                            </div>
                        </div>
                    </div>

                    <!-- PERSONAL INFORMATION SECTION - ALWAYS SHOW -->
                    <div class="mb-8">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 section-header">
                            <i class="fas fa-user-circle mr-2"></i>Personal Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h5 class="font-medium text-gray-700 mb-3">Applicant Details</h5>
                                <div class="space-y-2">
                                    <div><span class="text-gray-600">Full Name:</span> 
                                        <span class="font-semibold"><?php echo htmlspecialchars($permit['full_name']); ?></span>
                                    </div>
                                    <div><span class="text-gray-600">Date of Birth:</span> 
                                        <span class="font-semibold"><?php echo date('M d, Y', strtotime($permit['date_of_birth'])); ?></span>
                                    </div>
                                    <div><span class="text-gray-600">Sex:</span> 
                                        <span class="font-semibold"><?php echo htmlspecialchars($permit['sex'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div><span class="text-gray-600">Marital Status:</span> 
                                        <span class="font-semibold"><?php echo htmlspecialchars($permit['marital_status'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div><span class="text-gray-600">Contact:</span> 
                                        <span class="font-semibold"><?php echo htmlspecialchars($permit['personal_contact']); ?></span>
                                    </div>
                                    <div><span class="text-gray-600">Email:</span> 
                                        <span class="font-semibold"><?php echo htmlspecialchars($permit['personal_email']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <h5 class="font-medium text-gray-700 mb-3">Business Address</h5>
                                <div class="space-y-2">
                                    <div class="font-semibold"><?php echo htmlspecialchars($permit['business_street'] ?? 'N/A'); ?></div>
                                    <div><?php echo htmlspecialchars($permit['business_barangay']); ?>, 
                                         <?php echo htmlspecialchars($permit['business_district']); ?></div>
                                    <div><?php echo htmlspecialchars($permit['business_city']); ?>, 
                                         <?php echo htmlspecialchars($permit['business_province']); ?></div>
                                    <div>ZIP: <?php echo htmlspecialchars($permit['business_zipcode'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($permit['status'] == 'Pending'): ?>
                        <!-- Pending Application Message -->
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <i class="fas fa-clock text-yellow-500 mt-1 mr-3"></i>
                                <div>
                                    <h5 class="font-semibold text-yellow-800 mb-1">Application Under Review</h5>
                                    <p class="text-yellow-700 text-sm">
                                        Your business permit application is currently being processed. 
                                        You will be notified once it's approved. Estimated processing time: 3-5 business days.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Annual Discount Banner - Made Smaller -->
                        <?php if ($permit['eligible_for_annual_discount'] && $discount_percent > 0 && $total_penalty == 0 && $paid_count == 0): ?>
                            <div class="discount-banner mb-4">
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div class="flex items-center mb-2 md:mb-0">
                                        <div class="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-gift text-white"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-white mb-1">Annual Payment Discount Available</h4>
                                            <p class="text-white text-opacity-90 text-xs">
                                                Save <?php echo $permit['discount_percent']; ?>% on annual tax. Valid until Jan 31.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-bold text-white"><?php echo formatCurrency($permit['discounted_total'] ?? 0); ?></div>
                                        <div class="text-xs text-white text-opacity-90">
                                            Save: <?php echo formatCurrency($permit['discount_amount'] ?? 0); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- BUSINESS TAX INFORMATION SECTION - ALWAYS SHOW -->
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4 section-header">
                                <i class="fas fa-file-invoice-dollar mr-2"></i>Tax Information
                            </h4>
                            
                            <!-- Tax Summary -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <div class="text-sm text-blue-600 mb-1">Annual Tax</div>
                                    <div class="text-2xl font-bold text-blue-900">
                                        <?php echo formatCurrency($permit['total_tax'] ?? 0); ?>
                                    </div>
                                    <?php if ($permit['eligible_for_annual_discount'] && $discount_percent > 0): ?>
                                        <div class="text-xs text-green-600 mt-1">
                                            <?php echo $permit['discount_percent']; ?>% discount available
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <div class="text-sm text-green-600 mb-1">Tax Type</div>
                                    <div class="font-semibold text-green-900 text-lg">
                                        <?php echo str_replace('_', ' ', ucfirst($permit['tax_calculation_type'] ?? 'capital_investment')); ?>
                                    </div>
                                </div>
                                <div class="bg-purple-50 p-4 rounded-lg">
                                    <div class="text-sm text-purple-600 mb-1">Tax Rate</div>
                                    <div class="text-2xl font-bold text-purple-900">
                                        <?php echo number_format($permit['tax_rate'], 2); ?>%
                                    </div>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="text-sm text-gray-600 mb-1">Taxable Amount</div>
                                    <div class="text-xl font-bold text-gray-900">
                                        <?php echo formatCurrency($permit['taxable_amount'] ?? 0); ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($permit_taxes)): ?>
                                <!-- Quarterly Taxes Table -->
                                <div class="overflow-x-auto mb-6">
                                    <h5 class="font-medium text-gray-700 mb-3">Quarterly Tax Payments</h5>
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quarter</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tax Amount</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Penalty</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($permit_taxes as $tax): 
                                                $status = $tax['payment_status'];
                                                $tax_amount = $tax['total_quarterly_tax'] ?? 0;
                                                $penalty_amount = $tax['penalty_amount'] ?? 0;
                                                $total_amount = $tax_amount + $penalty_amount;
                                                $days_late = $tax['days_late'] ?? 0;
                                                $dueDate = new DateTime($tax['due_date']);
                                            ?>
                                                <tr>
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <span class="font-semibold"><?php echo $tax['quarter']; ?></span>
                                                        <span class="text-gray-600"> <?php echo $tax['year']; ?></span>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <?php echo $dueDate->format('M d, Y'); ?>
                                                        <?php if ($days_late > 0): ?>
                                                            <div class="text-xs text-red-600 mt-1">
                                                                (<?php echo $days_late; ?> days late)
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <?php echo formatCurrency($tax_amount); ?>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <?php if ($penalty_amount > 0): ?>
                                                            <span class="font-semibold text-red-600">
                                                                <?php echo formatCurrency($penalty_amount); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-gray-500"><?php echo formatCurrency(0); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap font-bold text-lg">
                                                        <?php echo formatCurrency($total_amount); ?>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <?php if ($status == 'paid'): ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                <i class="fas fa-check mr-1"></i> Paid
                                                            </span>
                                                        <?php elseif ($status == 'overdue'): ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                                <i class="fas fa-exclamation-triangle mr-1"></i> Overdue
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
                                                <td colspan="2" class="px-4 py-3 text-right font-bold text-gray-700">
                                                    Totals:
                                                </td>
                                                <td class="px-4 py-3 font-bold text-gray-900">
                                                    <?php echo formatCurrency($quarter_totals['tax_amount']); ?>
                                                </td>
                                                <td class="px-4 py-3 font-bold <?php echo $quarter_totals['penalty'] > 0 ? 'text-red-600' : 'text-gray-900'; ?>">
                                                    <?php echo formatCurrency($quarter_totals['penalty']); ?>
                                                </td>
                                                <td class="px-4 py-3 font-bold text-xl text-blue-700">
                                                    <?php echo formatCurrency($quarter_totals['total']); ?>
                                                </td>
                                                <td class="px-4 py-3"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="bg-gray-50 rounded-lg p-6 text-center">
                                    <i class="fas fa-file-invoice text-3xl text-gray-300 mb-3"></i>
                                    <p class="text-gray-600">No quarterly tax records found for this permit.</p>
                                </div>
                            <?php endif; ?>

                            <!-- Payment Summary -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="text-sm text-gray-600 mb-1">Total Annual Tax</div>
                                    <div class="text-xl font-bold text-gray-900">
                                        <?php echo formatCurrency($permit['total_tax'] ?? 0); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">Total for the year</div>
                                </div>
                                
                                <div class="bg-red-50 p-4 rounded-lg">
                                    <div class="text-sm text-red-600 mb-1">Total Penalties</div>
                                    <div class="text-xl font-bold <?php echo $total_penalty > 0 ? 'text-red-600' : 'text-gray-900'; ?>">
                                        <?php echo formatCurrency($total_penalty); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo $total_penalty > 0 ? 'Late payment penalties' : 'No penalties'; ?>
                                    </div>
                                </div>
                                
                                <div class="bg-<?php echo $overdue_count > 0 ? 'red' : ($paid_count == 4 ? 'green' : 'blue'); ?>-50 p-4 rounded-lg">
                                    <div class="text-sm text-<?php echo $overdue_count > 0 ? 'red' : ($paid_count == 4 ? 'green' : 'blue'); ?>-600 mb-1">Payment Status</div>
                                    <div class="text-xl font-bold text-<?php echo $overdue_count > 0 ? 'red' : ($paid_count == 4 ? 'green' : 'blue'); ?>-900">
                                        <?php echo $overdue_count > 0 ? 'Overdue' : ($paid_count == 4 ? 'Fully Paid' : 'Partially Paid'); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo $paid_count; ?> paid • <?php echo $pending_count; ?> pending • <?php echo $overdue_count; ?> overdue
                                    </div>
                                </div>
                                
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <div class="text-sm text-blue-600 mb-1">Total Amount Due</div>
                                    <div class="text-2xl font-bold text-blue-900">
                                        <?php echo formatCurrency($grand_total); ?>
                                    </div>
                                    <?php if ($permit['eligible_for_annual_discount'] && $discount_percent > 0): ?>
                                        <div class="text-xs text-green-600">
                                            Discount: <?php echo formatCurrency($permit['discount_amount'] ?? 0); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Additional Tax Info -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h5 class="font-medium text-gray-700 mb-3">Regulatory Fees</h5>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Mayor's Permit Fee:</span>
                                            <span class="font-semibold"><?php echo formatCurrency(499.98); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Sanitary Fee:</span>
                                            <span class="font-semibold"><?php echo formatCurrency(500.00); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Registration Fee:</span>
                                            <span class="font-semibold"><?php echo formatCurrency(300.00); ?></span>
                                        </div>
                                        <div class="border-t pt-2 mt-2">
                                            <div class="flex justify-between font-bold text-gray-800">
                                                <span>Total Regulatory Fees:</span>
                                                <span><?php echo formatCurrency(1299.98); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h5 class="font-medium text-gray-700 mb-3">Tax Calculation Details</h5>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Taxable Amount:</span>
                                            <span class="font-semibold"><?php echo formatCurrency($permit['taxable_amount'] ?? 0); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Tax Rate Applied:</span>
                                            <span class="font-semibold"><?php echo number_format($permit['tax_rate'], 2); ?>%</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Annual Tax:</span>
                                            <span class="font-semibold"><?php echo formatCurrency($permit['tax_amount'] ?? 0); ?></span>
                                        </div>
                                        <div class="border-t pt-2 mt-2">
                                            <div class="flex justify-between font-bold text-gray-800">
                                                <span>Total Annual Payment:</span>
                                                <span><?php echo formatCurrency($permit['total_tax'] ?? 0); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Footer (Direct HTML instead of include) -->
<footer class="bg-white border-t border-gray-200 mt-16">
    <div class="container mx-auto px-6 py-12 max-w-7xl">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
            <!-- Brand -->
            <div class="col-span-1">
                <div class="flex items-center space-x-2 mb-4 text-2xl font-bold">
                    <span style="color: #4a90e2;">Go</span>
                    <span style="color: #4caf50;">Serve</span>
                    <span style="color: #4a90e2;">PH</span>
                </div>
                <p class="text-gray-600 leading-relaxed">
                    The official digital gateway of your Local Government Unit, providing efficient and transparent government services.
                </p>
            </div>
            
            <!-- Portal Links -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Portal</h4>
                <ul class="space-y-3 text-gray-600">
                    <li><a href="../../citizen_dashboard.php" class="hover:text-[#4a90e2] transition-colors">Dashboard</a></li>
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">My Applications</a></li>
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">Settings</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Contact</h4>
                <ul class="space-y-3 text-gray-600">
                    <li><i class="fas fa-phone mr-2 text-gray-400"></i> (02) 8123 4567</li>
                    <li><i class="fas fa-envelope mr-2 text-gray-400"></i> business@goserveph.gov.ph</li>
                    <li><i class="fas fa-clock mr-2 text-gray-400"></i> Mon-Fri: 8AM - 5PM</li>
                </ul>
            </div>

            <!-- Social -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Connect</h4>
                <div class="flex space-x-4 text-2xl">
                    <a href="#" class="text-gray-400 hover:text-blue-600 transition-colors">
                        <i class="fab fa-facebook"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-blue-400 transition-colors">
                        <i class="fab fa-twitter"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Copyright -->
        <div class="border-t border-gray-200 mt-10 pt-8">
            <p class="text-sm text-gray-500 text-center">
                &copy; 2026 GoServePH Local Government Unit. Republic of the Philippines.
            </p>
        </div>
    </div>
</footer>

</body>
</html>