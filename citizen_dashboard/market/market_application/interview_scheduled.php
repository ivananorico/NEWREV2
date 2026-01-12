<?php
// revenue2/citizen_dashboard/market/market_application/interview_scheduled.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Include database connection
include_once '../../../db/Market/market_db.php';

// Fetch user's scheduled interview applications
$applications = [];
$total_applications = 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            rr.*,
            ro.*,
            s.name as stall_name,
            sr.class_name,
            sr.price as class_price,
            m.name as market_name
        FROM rental_registration rr
        JOIN renter_owner ro ON rr.id = ro.registration_id
        LEFT JOIN stalls s ON rr.stall_id = s.id
        LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
        LEFT JOIN maps m ON rr.map_id = m.id
        WHERE ro.user_id = ? 
          AND rr.application_status = 'interview_scheduled'
        ORDER BY rr.interview_date ASC
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
<title>Interview Scheduled - Market Rent Services</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
.status-badge { 
    padding: 0.25rem 0.75rem; 
    border-radius: 9999px; 
    font-size: 0.75rem; 
    font-weight: 600; 
}
.status-scheduled { 
    background-color: #dbeafe; 
    color: #1e40af; 
    border: 1px solid #93c5fd;
}
.card {
    background: white;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}
.info-label {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
}
.info-value {
    font-size: 0.875rem;
    color: #111827;
    font-weight: 500;
}
.interview-card {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 2px solid #0ea5e9;
    border-radius: 0.5rem;
}
</style>
</head>
<body class="bg-gray-50 min-h-screen">
<?php include '../../navbar.php'; ?>

<main class="max-w-6xl mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="bg-white rounded border border-gray-200 p-4">
            <div class="flex items-center mb-3">
                <a href="../market_services.php" class="text-blue-600 hover:text-blue-800 mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Scheduled Interviews</h1>
                    <p class="text-gray-600 text-sm">Your market stall application interviews</p>
                </div>
            </div>

            <?php if ($total_applications > 0): ?>
                <div class="flex items-center mt-3">
                    <div class="mr-3">
                        <div class="text-xl font-bold text-gray-900"><?php echo $total_applications; ?></div>
                        <div class="text-sm text-gray-500">Interview<?php echo $total_applications > 1 ? 's' : ''; ?> Scheduled</div>
                    </div>
                    <div class="h-6 w-px bg-gray-300"></div>
                    <div class="ml-3">
                        <div class="status-badge status-scheduled">
                            <i class="fas fa-calendar-check mr-1"></i>Interview Scheduled
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Error Message -->
    <?php if (isset($error_message)): ?>
        <div class="mb-4 p-3 bg-red-50 text-red-700 rounded border border-red-200 text-sm">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_applications === 0): ?>
        <!-- Empty State -->
        <div class="bg-white rounded border border-gray-200 p-6 text-center">
            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-calendar-times text-gray-400 text-xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">No Scheduled Interviews</h3>
            <p class="text-gray-600 mb-4">You don't have any scheduled interviews.</p>
            <a href="pending.php" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                <i class="fas fa-clock mr-2"></i>Check Pending Applications
            </a>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($applications as $app): ?>
                <?php
                    // Prepare full name
                    $full_name = trim($app['first_name'] . ' ' . 
                        (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . 
                        $app['last_name'] . 
                        (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));
                    
                    // Build full address
                    $address_parts = [];
                    if (!empty($app['house_number'])) $address_parts[] = $app['house_number'];
                    if (!empty($app['street'])) $address_parts[] = $app['street'];
                    if (!empty($app['barangay'])) $address_parts[] = 'Brgy. ' . $app['barangay'];
                    if (!empty($app['city'])) $address_parts[] = $app['city'];
                    if (!empty($app['province'])) $address_parts[] = $app['province'];
                    if (!empty($app['zip_code'])) $address_parts[] = $app['zip_code'];
                    $full_address = implode(', ', $address_parts);
                    
                    // Format birth date
                    $birth_date = !empty($app['birth_date']) ? date('F j, Y', strtotime($app['birth_date'])) : 'N/A';
                    
                    // Format interview date
                    $interview_date = !empty($app['interview_date']) ? date('F j, Y g:i A', strtotime($app['interview_date'])) : 'To be scheduled';
                    $interview_date_short = !empty($app['interview_date']) ? date('M j, Y', strtotime($app['interview_date'])) : 'TBD';
                    
                    // Calculate amounts
                    $stall_rights_amount = $app['stall_rights_amount'] ?? 0;
                    $security_bond = $app['security_bond'] ?? 0;
                    $monthly_rent = $app['monthly_rent'] ?? 0;
                    
                    $total_amount_due = $stall_rights_amount + $security_bond;
                    
                    // Calculate days until interview
                    $days_until = '';
                    if (!empty($app['interview_date'])) {
                        $interview_day = new DateTime($app['interview_date']);
                        $today = new DateTime();
                        $interval = $today->diff($interview_day);
                        $days_until = $interval->days;
                    }
                ?>

                <div class="card">
                    <!-- Header -->
                    <div class="p-4 border-b border-gray-100">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="flex items-center mb-1">
                                    <span class="text-lg font-bold text-gray-900 mr-3">
                                        #<?php echo $app['stall_rights_no']; ?>
                                    </span>
                                    <span class="status-badge status-scheduled">
                                        <i class="fas fa-calendar-check mr-1"></i>Interview Scheduled
                                    </span>
                                </div>
                                <div class="text-gray-600 text-sm">
                                    <i class="fas fa-store mr-1"></i>
                                    <?php echo $app['stall_name']; ?> • <?php echo $app['class_name']; ?> Class
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-gray-500">Interview Date</div>
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo $interview_date_short; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Interview Notice -->
                    <div class="px-4 py-3 bg-blue-50 border-y border-blue-100">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                            <div class="text-sm text-blue-700">
                                Interview scheduled! Please arrive 15 minutes early.
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
                        <div class="mb-1">
                            <div class="flex justify-between text-xs text-gray-600 mb-1">
                                <span>Application</span>
                                <span>Interview</span>
                                <span>Payment</span>
                                <span>Approval</span>
                            </div>
                            <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-500" style="width: 50%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-4">
                        <div class="space-y-4">
                            <!-- Interview Details -->
                            <div class="interview-card p-4 mb-4">
                                <h3 class="font-semibold text-gray-800 mb-3 border-b pb-2">Interview Details</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-3">
                                        <div>
                                            <div class="info-label">Date & Time</div>
                                            <div class="info-value text-lg font-bold text-blue-700">
                                                <i class="fas fa-clock mr-2"></i><?php echo $interview_date; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="info-label">Location</div>
                                            <div class="info-value">
                                                <i class="fas fa-map-marker-alt mr-2"></i>
                                                Market Administration Office, 2nd Floor
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-3">
                                        <div>
                                            <div class="info-label">Interviewer</div>
                                            <div class="info-value">
                                                <i class="fas fa-user-tie mr-2"></i>
                                                <?php echo !empty($app['interviewer']) ? htmlspecialchars($app['interviewer']) : 'Market Administrator'; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="info-label">Contact</div>
                                            <div class="info-value">
                                                <i class="fas fa-phone-alt mr-2"></i>
                                                (02) 1234-5680
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($days_until !== ''): ?>
                                <div class="mt-4 pt-4 border-t border-blue-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="info-label">Days Until Interview</div>
                                            <div class="info-value">
                                                <?php if ($days_until == 0): ?>
                                                    <span class="font-bold text-green-600">Interview is TODAY</span>
                                                <?php elseif ($days_until == 1): ?>
                                                    <span class="font-bold text-yellow-600">Interview is TOMORROW</span>
                                                <?php else: ?>
                                                    <span class="font-bold text-blue-600"><?php echo $days_until; ?> day<?php echo $days_until > 1 ? 's' : ''; ?> from now</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <button onclick="printInterviewSlip('<?php echo $app['stall_rights_no']; ?>', '<?php echo $interview_date; ?>', '<?php echo htmlspecialchars($full_name); ?>')" 
                                                class="text-sm bg-white text-blue-600 border border-blue-600 hover:bg-blue-50 px-3 py-1 rounded">
                                            <i class="fas fa-print mr-1"></i>Print Slip
                                        </button>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Applicant Information Section -->
                            <div>
                                <h3 class="font-semibold text-gray-800 mb-3 border-b pb-2">Applicant Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-3">
                                        <!-- Personal Information -->
                                        <div>
                                            <h4 class="font-medium text-gray-700 mb-2 text-sm">Personal Information</h4>
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="info-label">Full Name</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($full_name); ?></div>
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
                                                    <div class="info-label">Birth Date</div>
                                                    <div class="info-value"><?php echo $birth_date; ?></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Contact Information -->
                                        <div>
                                            <h4 class="font-medium text-gray-700 mb-2 text-sm">Contact Information</h4>
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="info-label">Email Address</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($app['email']); ?></div>
                                                </div>
                                                <div>
                                                    <div class="info-label">Mobile Number</div>
                                                    <div class="info-value"><?php echo $app['mobile']; ?></div>
                                                </div>
                                                <?php if (!empty($app['telephone'])): ?>
                                                <div>
                                                    <div class="info-label">Telephone</div>
                                                    <div class="info-value"><?php echo $app['telephone']; ?></div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <!-- Address Information -->
                                        <div>
                                            <h4 class="font-medium text-gray-700 mb-2 text-sm">Address</h4>
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="info-label">Complete Address</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($full_address); ?></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Emergency Contact -->
                                        <?php if (!empty($app['emergency_name'])): ?>
                                        <div>
                                            <h4 class="font-medium text-gray-700 mb-2 text-sm">Emergency Contact</h4>
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="info-label">Contact Person</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($app['emergency_name']); ?></div>
                                                </div>
                                                <div>
                                                    <div class="info-label">Contact Number</div>
                                                    <div class="info-value"><?php echo $app['emergency_contact']; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Application Information -->
                                        <div>
                                            <h4 class="font-medium text-gray-700 mb-2 text-sm">Application Details</h4>
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="info-label">Renter Code</div>
                                                    <div class="info-value font-bold text-blue-600"><?php echo $app['renter_code']; ?></div>
                                                </div>
                                                <div>
                                                    <div class="info-label">Stall Rights No.</div>
                                                    <div class="info-value font-bold"><?php echo $app['stall_rights_no']; ?></div>
                                                </div>
                                                <div>
                                                    <div class="info-label">Date Applied</div>
                                                    <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($app['created_at'])); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stall and Financial Information Section -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Stall Information -->
                                <div>
                                    <h3 class="font-semibold text-gray-800 mb-3 border-b pb-2">Stall Information</h3>
                                    <div class="space-y-3">
                                        <div>
                                            <div class="info-label">Stall Name</div>
                                            <div class="info-value"><?php echo htmlspecialchars($app['stall_name']); ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Business Name</div>
                                            <div class="info-value"><?php echo !empty($app['business_name']) ? htmlspecialchars($app['business_name']) : 'N/A'; ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Business Type</div>
                                            <div class="info-value"><?php echo !empty($app['business_type']) ? htmlspecialchars($app['business_type']) : 'N/A'; ?></div>
                                        </div>
                                        <div>
                                            <div class="info-label">Stall Class</div>
                                            <div class="info-value font-bold text-blue-600"><?php echo $app['class_name']; ?> Class</div>
                                        </div>
                                        <?php if (!empty($app['market_name'])): ?>
                                        <div>
                                            <div class="info-label">Market Location</div>
                                            <div class="info-value"><?php echo htmlspecialchars($app['market_name']); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Financial Information -->
                                <div>
                                    <h3 class="font-semibold text-gray-800 mb-3 border-b pb-2">Financial Information</h3>
                                    <div class="space-y-4">
                                        <!-- Monthly Rent -->
                                        <div class="p-3 bg-blue-50 rounded border border-blue-100">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <div class="info-label">Monthly Rent</div>
                                                    <div class="text-lg font-bold text-blue-700">
                                                        ₱<?php echo number_format($monthly_rent, 2); ?>
                                                    </div>
                                                    <div class="text-xs text-blue-600 mt-1">
                                                        Payable monthly after stall approval
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- One-time Payments -->
                                        <div class="p-3 bg-purple-50 rounded border border-purple-100">
                                            <h4 class="font-medium text-gray-800 mb-2">One-time Payments</h4>
                                            <div class="space-y-2">
                                                <?php if ($stall_rights_amount > 0): ?>
                                                <div class="flex justify-between">
                                                    <div>
                                                        <div class="text-sm text-gray-800">Stall Rights Fee</div>
                                                        <div class="text-xs text-gray-600">Non-refundable</div>
                                                    </div>
                                                    <div class="text-sm font-bold text-gray-900">
                                                        ₱<?php echo number_format($stall_rights_amount, 2); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($security_bond > 0): ?>
                                                <div class="flex justify-between">
                                                    <div>
                                                        <div class="text-sm text-gray-800">Security Bond</div>
                                                        <div class="text-xs text-gray-600">Refundable</div>
                                                    </div>
                                                    <div class="text-sm font-bold text-gray-900">
                                                        ₱<?php echo number_format($security_bond, 2); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div class="pt-2 mt-2 border-t border-purple-200">
                                                    <div class="flex justify-between items-center">
                                                        <div>
                                                            <div class="font-bold text-gray-900">Total Amount Due</div>
                                                            <div class="text-xs text-gray-600">Payable after interview approval</div>
                                                        </div>
                                                        <div class="text-lg font-bold text-purple-600">
                                                            ₱<?php echo number_format($total_amount_due, 2); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Interview Status -->
                                        <div class="p-3 bg-blue-50 rounded border border-blue-100">
                                            <h4 class="font-medium text-gray-800 mb-1">Interview Status</h4>
                                            <div class="flex items-center">
                                                <div class="status-badge status-scheduled mr-2">
                                                    <i class="fas fa-calendar-check mr-1"></i>Scheduled
                                                </div>
                                                <div class="text-xs text-blue-700">
                                                    Waiting for your interview
                                                </div>
                                            </div>
                                            <?php if (!empty($app['interviewer'])): ?>
                                            <div class="mt-2 text-xs text-gray-600">
                                                Interviewer: <?php echo htmlspecialchars($app['interviewer']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Interview Requirements -->
                            <div class="p-3 bg-yellow-50 rounded border border-yellow-100">
                                <h4 class="font-medium text-gray-800 mb-2">What to Bring to Interview</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                    <div class="flex items-start">
                                        <i class="fas fa-file-alt text-green-500 mt-1 mr-2"></i>
                                        <div>
                                            <div class="font-medium text-gray-800">Original Documents</div>
                                            <div class="text-gray-600 text-xs">All uploaded documents in original form</div>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <i class="fas fa-id-card text-blue-500 mt-1 mr-2"></i>
                                        <div>
                                            <div class="font-medium text-gray-800">Valid ID</div>
                                            <div class="text-gray-600 text-xs">Government-issued photo ID</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-3 bg-blue-50 border-t border-blue-100">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between">
                            <div class="mb-2 sm:mb-0">
                                <div class="font-medium text-gray-800">Interview Reminders</div>
                                <div class="text-gray-600 text-sm">
                                    • Arrive 15 minutes early • Bring original documents • Dress appropriately
                                </div>
                            </div>
                            <div class="text-blue-700 text-xs flex items-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                <span>Need help? Call (02) 1234-5680</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Help Section -->
        <div class="mt-6 bg-white rounded border border-gray-200 p-4">
            <h3 class="font-semibold text-gray-800 mb-3 text-sm">Need Help?</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <div>
                        <div class="font-medium text-gray-700 text-sm">Contact Market Office</div>
                        <div class="text-gray-600 text-sm">(02) 1234-5680</div>
                    </div>
                    <div>
                        <div class="font-medium text-gray-700 text-sm">Email</div>
                        <div class="text-gray-600 text-sm">market@lgu.gov.ph</div>
                    </div>
                </div>
                <div class="space-y-2">
                    <div>
                        <div class="font-medium text-gray-700 text-sm">Office Hours</div>
                        <div class="text-gray-600 text-sm">Mon-Fri: 8:00 AM - 5:00 PM</div>
                    </div>
                    <div>
                        <div class="font-medium text-gray-700 text-sm">Location</div>
                        <div class="text-gray-600 text-sm">Market Administration Office, 2nd Floor</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
function printInterviewSlip(stallRightsNo, interviewDatetime, applicantName) {
    const slipContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Interview Slip - ${stallRightsNo}</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    padding: 20px; 
                    max-width: 600px; 
                    margin: 0 auto;
                    line-height: 1.6;
                }
                .header { 
                    text-align: center; 
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #1e40af;
                }
                .details { 
                    margin: 25px 0;
                }
                .detail-row { 
                    margin-bottom: 12px;
                    padding-bottom: 12px;
                    border-bottom: 1px solid #e5e7eb;
                }
                .label { 
                    font-weight: bold; 
                    color: #1e40af;
                    display: block;
                    margin-bottom: 4px;
                }
                .footer { 
                    margin-top: 30px; 
                    font-size: 11px; 
                    color: #6b7280; 
                    text-align: center; 
                    padding-top: 15px; 
                    border-top: 1px solid #e5e7eb;
                }
                .instructions { 
                    background: #f3f4f6; 
                    padding: 15px; 
                    border-radius: 6px; 
                    margin: 20px 0; 
                    font-size: 13px;
                }
                .qr-section {
                    text-align: center;
                    margin: 20px 0;
                    padding: 15px;
                    border: 1px dashed #9ca3af;
                    border-radius: 6px;
                }
                @media print {
                    body { padding: 15px; }
                    button { display: none !important; }
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2 style="color: #1e40af; margin-bottom: 5px;">Market Stall Rental Interview</h2>
                <h3 style="color: #374151; font-weight: normal;">GoServePH Local Government Unit</h3>
            </div>
            
            <div class="details">
                <div class="detail-row">
                    <span class="label">Application Reference:</span>
                    <div style="font-size: 16px; font-weight: bold;">${stallRightsNo}</div>
                </div>
                <div class="detail-row">
                    <span class="label">Applicant Name:</span>
                    ${applicantName}
                </div>
                <div class="detail-row">
                    <span class="label">Interview Schedule:</span>
                    ${interviewDatetime}
                </div>
                <div class="detail-row">
                    <span class="label">Interview Location:</span>
                    Market Administration Office<br>
                    2nd Floor, Public Market Building
                </div>
                <div class="detail-row">
                    <span class="label">Office Contact:</span>
                    (02) 1234-5680
                </div>
            </div>
            
            <div class="instructions">
                <h4 style="color: #374151; margin-bottom: 10px;">Important Instructions:</h4>
                <ul style="margin: 0; padding-left: 20px;">
                    <li style="margin-bottom: 5px;">Bring this slip to your interview</li>
                    <li style="margin-bottom: 5px;">Arrive 15 minutes before scheduled time</li>
                    <li style="margin-bottom: 5px;">Bring all original documents for verification</li>
                    <li style="margin-bottom: 5px;">Dress in business casual attire</li>
                    <li>Parking available at the market parking area</li>
                </ul>
            </div>
            
            <div class="qr-section">
                <p style="margin-bottom: 10px; color: #6b7280;">Scan this code at the reception:</p>
                <div style="background: #f3f4f6; padding: 20px; display: inline-block; border-radius: 8px;">
                    <div style="width: 100px; height: 100px; background: #9ca3af; margin: 0 auto; border-radius: 4px;"></div>
                </div>
                <p style="margin-top: 10px; font-size: 10px; color: #9ca3af;">QR Code for quick check-in</p>
            </div>
            
            <div class="footer">
                <p><strong>Generated on:</strong> ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                <p>Market Administration Office • Public Market Building</p>
                <p>This slip is required for entry to the interview</p>
            </div>
            
            <div class="no-print" style="text-align: center; margin-top: 25px;">
                <button onclick="window.print()" style="background: #1e40af; color: white; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold;">
                    <i class="fas fa-print" style="margin-right: 8px;"></i>Print Interview Slip
                </button>
                <p style="margin-top: 10px; font-size: 12px; color: #6b7280;">Print this slip and bring it to your interview</p>
            </div>
        </body>
        </html>
    `;
    
    const printWindow = window.open('', '_blank', 'width=700,height=800');
    printWindow.document.write(slipContent);
    printWindow.document.close();
    
    // Auto-print after a short delay
    setTimeout(() => {
        printWindow.print();
    }, 500);
}
</script>
</body>
</html>