<?php
// rpt_tax_payment.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Citizen';

// Include database connection
include_once '../../../db/RPT/rpt_db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPT Tax Payment - GoServePH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Include Navbar -->
    <?php include '../../navbar.php'; ?>
    
    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8">
        <!-- Page Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center mb-4">
                <a href="../rpt_services.php" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">RPT Tax Payment</h1>
                    <p class="text-gray-600">View and pay your quarterly property taxes</p>
                </div>
            </div>
        </div>

        <!-- Tax Payment Content -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <?php
            try {
                // Get approved properties for this user with their quarterly taxes
                $query = "
                    SELECT 
                        pr.id,
                        pr.reference_number,
                        pr.lot_location,
                        pr.barangay,
                        pr.district,
                        po.full_name,
                        pt.total_annual_tax,
                        pt.id as property_total_id,
                        (SELECT COUNT(*) FROM quarterly_taxes qt 
                         WHERE qt.property_total_id = pt.id AND qt.payment_status = 'paid') as paid_quarters,
                        (SELECT COUNT(*) FROM quarterly_taxes qt 
                         WHERE qt.property_total_id = pt.id AND qt.payment_status = 'overdue') as overdue_quarters,
                        (SELECT SUM(qt.total_quarterly_tax + qt.penalty_amount) FROM quarterly_taxes qt 
                         WHERE qt.property_total_id = pt.id AND qt.payment_status = 'paid') as total_paid,
                        (SELECT SUM(qt.total_quarterly_tax + qt.penalty_amount) FROM quarterly_taxes qt 
                         WHERE qt.property_total_id = pt.id AND qt.payment_status IN ('pending', 'overdue')) as total_pending
                    FROM property_registrations pr
                    INNER JOIN property_owners po ON pr.owner_id = po.id
                    INNER JOIN property_totals pt ON pr.id = pt.registration_id
                    WHERE po.user_id = :user_id AND pr.status = 'approved'
                    ORDER BY pr.created_at DESC
                ";
                
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->execute();
                $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($properties)) {
                    echo '
                    <div class="lg:col-span-4">
                        <div class="bg-white rounded-xl shadow-lg p-8 text-center">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-home text-gray-400 text-2xl"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-800 mb-2">No Properties Found</h3>
                            <p class="text-gray-600 mb-4">You don\'t have any approved properties yet.</p>
                            <a href="rpt_registration/rpt_registration.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                                Register Property
                            </a>
                        </div>
                    </div>';
                } else {
                    // Summary Statistics
                    $totalAnnualTax = 0;
                    $totalPaid = 0;
                    $totalPending = 0;
                    $totalProperties = count($properties);
                    
                    foreach ($properties as $property) {
                        $totalAnnualTax += $property['total_annual_tax'];
                        $totalPaid += $property['total_paid'] ?? 0;
                        $totalPending += $property['total_pending'] ?? 0;
                    }
                    
                    echo '
                    <!-- Summary Stats -->
                    <div class="lg:col-span-4 mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-white rounded-xl shadow p-5">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                        <i class="fas fa-home text-blue-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold text-gray-800">' . $totalProperties . '</div>
                                        <div class="text-gray-500 text-sm">Properties</div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white rounded-xl shadow p-5">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold text-gray-800">₱' . number_format($totalPaid, 2) . '</div>
                                        <div class="text-gray-500 text-sm">Total Paid</div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white rounded-xl shadow p-5">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold text-gray-800">₱' . number_format($totalPending, 2) . '</div>
                                        <div class="text-gray-500 text-sm">Pending Payment</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
                    
                    foreach ($properties as $property) {
                        // Get quarterly taxes for this property
                        $taxQuery = "
                            SELECT 
                                qt.*,
                                CASE 
                                    WHEN qt.payment_status = 'paid' THEN 'paid'
                                    WHEN qt.due_date < CURDATE() AND qt.payment_status = 'pending' THEN 'overdue'
                                    ELSE 'pending'
                                END as actual_status
                            FROM quarterly_taxes qt
                            WHERE qt.property_total_id = :property_total_id
                            ORDER BY qt.year DESC, 
                                CASE qt.quarter 
                                    WHEN 'Q1' THEN 1
                                    WHEN 'Q2' THEN 2
                                    WHEN 'Q3' THEN 3
                                    WHEN 'Q4' THEN 4
                                END DESC
                        ";
                        
                        $taxStmt = $pdo->prepare($taxQuery);
                        $taxStmt->bindParam(':property_total_id', $property['property_total_id'], PDO::PARAM_INT);
                        $taxStmt->execute();
                        $quarterlyTaxes = $taxStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Calculate property summary
                        $propertyPaid = $property['total_paid'] ?? 0;
                        $propertyPending = $property['total_pending'] ?? 0;
                        $paymentProgress = $property['total_annual_tax'] > 0 ? ($propertyPaid / $property['total_annual_tax']) * 100 : 0;
                        
                        echo '
                        <div class="lg:col-span-4">
                            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-6">
                                    <div class="mb-4 lg:mb-0">
                                        <h3 class="text-xl font-semibold text-gray-800">' . htmlspecialchars($property['reference_number']) . '</h3>
                                        <p class="text-gray-600">' . htmlspecialchars($property['lot_location']) . ', ' . htmlspecialchars($property['barangay']) . ', ' . htmlspecialchars($property['district']) . '</p>
                                        <p class="text-gray-500 text-sm mt-1">Owner: ' . htmlspecialchars($property['full_name']) . '</p>
                                    </div>
                                    <div class="flex flex-col md:flex-row items-center space-y-2 md:space-y-0 md:space-x-6">
                                        <div class="text-center">
                                            <div class="text-2xl font-bold text-blue-600">₱' . number_format($property['total_annual_tax'], 2) . '</div>
                                            <div class="text-sm text-gray-500">Annual Tax</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-lg font-bold ' . ($paymentProgress >= 100 ? 'text-green-600' : 'text-blue-600') . '">
                                                ' . round($paymentProgress, 1) . '%
                                            </div>
                                            <div class="text-sm text-gray-500">Paid</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Payment Progress Bar -->
                                <div class="mb-6">
                                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                                        <span>Payment Progress</span>
                                        <span>₱' . number_format($propertyPaid, 2) . ' / ₱' . number_format($property['total_annual_tax'], 2) . '</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="' . ($paymentProgress >= 100 ? 'bg-green-600' : 'bg-blue-600') . ' h-2.5 rounded-full" 
                                             style="width: ' . min($paymentProgress, 100) . '%"></div>
                                    </div>
                                </div>
                                
                                <h4 class="font-semibold text-gray-800 mb-4">Quarterly Taxes</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">';
                        
                        if (empty($quarterlyTaxes)) {
                            echo '
                                    <div class="md:col-span-2 lg:col-span-4">
                                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 text-center">
                                            <i class="fas fa-info-circle text-gray-400 text-3xl mb-3"></i>
                                            <p class="text-gray-600">No quarterly taxes generated yet for this property.</p>
                                        </div>
                                    </div>';
                        } else {
                            foreach ($quarterlyTaxes as $tax) {
                                $status = $tax['actual_status'];
                                $statusConfig = [
                                    'paid' => [
                                        'color' => 'bg-green-100 text-green-800 border-green-200', 
                                        'label' => 'Paid', 
                                        'icon' => 'fa-check-circle',
                                        'bg' => 'bg-green-50'
                                    ],
                                    'overdue' => [
                                        'color' => 'bg-red-100 text-red-800 border-red-200', 
                                        'label' => 'Overdue', 
                                        'icon' => 'fa-exclamation-triangle',
                                        'bg' => 'bg-red-50'
                                    ],
                                    'pending' => [
                                        'color' => 'bg-yellow-100 text-yellow-800 border-yellow-200', 
                                        'label' => 'Pending', 
                                        'icon' => 'fa-clock',
                                        'bg' => 'bg-white'
                                    ]
                                ];
                                $config = $statusConfig[$status] ?? $statusConfig['pending'];
                                
                                $dueDate = new DateTime($tax['due_date']);
                                $currentDate = new DateTime();
                                $isOverdue = $dueDate < $currentDate && $status !== 'paid';
                                $isCurrentQuarter = false;
                                
                                // Check if this is the current quarter
                                $currentMonth = date('n');
                                $quarterMonth = substr($tax['quarter'], 1);
                                $currentQuarter = ceil($currentMonth / 3);
                                $taxQuarter = (int) substr($tax['quarter'], 1);
                                
                                if ($taxQuarter == $currentQuarter && $tax['year'] == date('Y')) {
                                    $isCurrentQuarter = true;
                                }
                                
                                $totalAmount = $tax['total_quarterly_tax'] + ($tax['penalty_amount'] ?? 0);
                                
                                echo '
                                    <div class="border-2 rounded-xl p-4 ' . $config['bg'] . ' ' . ($isOverdue ? 'border-red-300' : ($isCurrentQuarter ? 'border-blue-300' : 'border-gray-200')) . '">
                                        <div class="flex items-center justify-between mb-3">
                                            <div>
                                                <span class="text-lg font-semibold text-gray-800">' . htmlspecialchars($tax['quarter']) . ' ' . htmlspecialchars($tax['year']) . '</span>';
                                                
                                                if ($isCurrentQuarter) {
                                                    echo '<span class="ml-2 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Current</span>';
                                                }
                                                
                                                echo '
                                            </div>
                                            <span class="' . $config['color'] . ' px-2 py-1 rounded-full text-xs font-medium border">
                                                <i class="fas ' . $config['icon'] . ' mr-1"></i>' . $config['label'] . '
                                            </span>
                                        </div>
                                        
                                        <div class="space-y-2 mb-4">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-500">Due Date:</span>
                                                <span class="font-medium ' . ($isOverdue ? 'text-red-600' : 'text-gray-700') . '">' . $dueDate->format('M d, Y') . '</span>
                                            </div>
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-500">Base Amount:</span>
                                                <span class="font-medium">₱' . number_format($tax['total_quarterly_tax'], 2) . '</span>
                                            </div>';
                                            
                                            if (($tax['penalty_amount'] ?? 0) > 0) {
                                                echo '
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-500">Penalty:</span>
                                                <span class="font-semibold text-red-600">+₱' . number_format($tax['penalty_amount'], 2) . '</span>
                                            </div>';
                                            }
                                            
                                            echo '
                                            <div class="pt-2 border-t border-gray-200">
                                                <div class="flex justify-between text-base">
                                                    <span class="text-gray-700 font-medium">Total:</span>
                                                    <span class="font-bold text-gray-800">₱' . number_format($totalAmount, 2) . '</span>
                                                </div>
                                            </div>
                                        </div>';
                                        
                                        if ($status === 'paid') {
                                            echo '
                                        <div class="space-y-2">
                                            <button class="w-full bg-green-600 text-white py-2 rounded-lg font-medium cursor-not-allowed" disabled>
                                                <i class="fas fa-check mr-2"></i>Paid on ' . (!empty($tax['payment_date']) ? date('M d, Y', strtotime($tax['payment_date'])) : '') . '
                                            </button>';
                                            
                                            if (!empty($tax['receipt_number'])) {
                                                echo '
                                            <button onclick="viewReceipt(\'' . htmlspecialchars($tax['receipt_number']) . '\')" 
                                                    class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 rounded-lg font-medium transition-colors text-sm">
                                                <i class="fas fa-receipt mr-2"></i>View Receipt
                                            </button>';
                                            }
                                            echo '
                                        </div>';
                                        } else {
                                            echo '
                                        <button onclick="initiatePayment(' . $tax['id'] . ', ' . $totalAmount . ', \'RPT Tax: ' . $tax['quarter'] . ' ' . $tax['year'] . ' - ' . htmlspecialchars($property['reference_number']) . '\')" 
                                                class="w-full ' . ($isOverdue ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700') . ' text-white py-2.5 rounded-lg font-medium transition-colors flex items-center justify-center">
                                            <i class="fas fa-credit-card mr-2"></i>Pay ₱' . number_format($totalAmount, 2) . '
                                        </button>';
                                        }
                                        
                                        echo '
                                    </div>';
                            }
                        }
                        
                        echo '
                                </div>
                            </div>
                        </div>';
                    }
                }
            } catch (PDOException $e) {
                error_log("RPT Tax Payment Error: " . $e->getMessage());
                echo '
                <div class="lg:col-span-4">
                    <div class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-red-800 mb-2">Error Loading Taxes</h3>
                        <p class="text-red-600">Unable to load your tax information. Please try again later.</p>
                    </div>
                </div>';
            }
            ?>
        </div>
    </main>
<script>
function initiatePayment(taxId, amount, purpose) {
    // Prepare data for digital payment - FIXED CLIENT REFERENCE FORMAT
    const paymentData = {
        client_system: 'rpt',
        client_reference: 'RPT-' + taxId, // Keep simple format "RPT-{id}"
        purpose: purpose,
        amount: parseFloat(amount).toFixed(2)
    };
    
    // Log for debugging
    console.log('Initiating payment:', paymentData);
    
    // Send to payment method selection
    const encodedData = btoa(JSON.stringify(paymentData));
    window.location.href = '/revenue2/citizen_dashboard/digital/payment_method.php?data=' + encodedData;
}

// Rest of your JavaScript remains the same...
</script>
</body>
</html>