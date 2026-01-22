<?php
// revenue2/citizen_dashboard/rpt/rpt_application/assessed.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Include database connection
require_once '../../../db/RPT/rpt_db.php';
$pdo = getDatabaseConnection();

// Function to get proper document URL
function getDocumentUrl(string $dbPath): string
{
    // Remove ../ and ./
    $clean = str_replace(['../', './'], '', $dbPath);

    // Remove leading slash
    $clean = ltrim($clean, '/');

    // If DB path already has revenue2/, remove it
    if (strpos($clean, 'revenue2/') === 0) {
        $clean = substr($clean, strlen('revenue2/'));
    }

    // Final browser-safe URL
    return '/revenue2/' . $clean;
}

// Fetch user's applications under assessment
$applications = [];
$total_applications = 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            pr.id,
            pr.reference_number,
            pr.lot_location,
            pr.barangay,
            pr.district,
            pr.city,
            pr.province,
            pr.zip_code,
            pr.has_building,
            pr.status,
            DATE(pr.created_at) as application_date,
            DATE(pr.updated_at) as updated_date,
            
            po.first_name,
            po.last_name,
            po.middle_name,
            po.suffix,
            po.email,
            po.phone,
            po.tin_number,
            po.house_number,
            po.street,
            po.barangay as owner_barangay,
            po.district as owner_district,
            po.city as owner_city,
            po.province as owner_province,
            po.zip_code as owner_zip_code,
            po.birthdate,
            po.sex,
            po.marital_status
            
        FROM property_registrations pr
        JOIN property_owners po ON pr.owner_id = po.id
        WHERE po.user_id = ? 
          AND pr.status = 'assessed'
        ORDER BY pr.updated_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_applications = count($applications);
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Get base URL for background image
$base_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
$bg_image_path = $base_url . '/revenue2/Login/images/gsmbg.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Assessment - RPT Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #9aa5b1;
            --accent: #f59e0b;  /* Changed to amber for "in progress" */
            --background: #fbfbfb;
        }

        body {
            background: linear-gradient(135deg, rgba(240, 240, 240, 0.4) 0%, rgba(230, 230, 230, 0.4) 50%, rgba(220, 220, 220, 0.3) 100%);
            position: relative;
            overflow-x: hidden;
            min-height: 100vh;
            font-family: Inter, system-ui, sans-serif;
        }

        /* Background image with blur - ABSOLUTE PATH */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('<?php echo $bg_image_path; ?>') center/cover no-repeat;
            opacity: 0.08;
            pointer-events: none;
            z-index: -2;
            filter: blur(1px);
        }
        
        /* Animated background particles - same as login */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(245, 158, 11, 0.1) 0%, transparent 50%), /* Amber */
                radial-gradient(circle at 80% 20%, rgba(74, 144, 226, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(253, 168, 17, 0.05) 0%, transparent 50%);
            animation: backgroundFloat 20s ease-in-out infinite;
            z-index: -1;
        }
        
        @keyframes backgroundFloat {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(1deg); }
            66% { transform: translateY(10px) rotate(-1deg); }
        }

        /* Status Badge */
        .status-badge {
            padding: 0.375rem 0.875rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-assessment {
            background-color: #fef3c7;  /* Amber background */
            color: #92400e;  /* Dark amber text */
            border: 1px solid #f59e0b;  /* Amber border */
        }

        /* Info Box */
        .info-box {
            background: #f8fafc;
            border-left: 3px solid #3b82f6;
            padding: 1rem;
            margin-bottom: 0.5rem;
        }

        /* Progress Bar */
        .simple-progress {
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
        }

        .simple-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6 0%, #f59e0b 100%); /* Blue to amber gradient */
            border-radius: 3px;
        }

        /* Document Card */
        .simple-doc-card {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 1rem;
            background: white;
            transition: all 0.3s ease;
        }

        .simple-doc-card:hover {
            border-color: #f59e0b;
            cursor: pointer;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.1);
        }

        /* Modal */
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

        /* Header box styles - same as dashboard */
        .header-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border-radius: 20px;
            padding: 1.5rem;
        }

        .lgu-card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 0.75rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: all .25s ease;
        }

        /* Assessment Details Box */
        .assessment-box {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 1px solid #fbbf24;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .responsive-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Animation for cards */
        .notification-slide {
            animation: slideIn .3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Next Steps Box */
        .next-steps-box {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 1px solid #fbbf24;
            border-radius: 10px;
        }

        /* Pulsing animation for in-progress status */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .pulse-animation {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
<?php include '../../navbar.php'; ?>

<!-- Image Modal -->
<div id="imageModal" class="modal">
    <span class="modal-close">&times;</span>
    <img class="modal-content" id="modalImage">
    <div id="modalCaption" class="modal-caption"></div>
</div>

<main class="container mx-auto px-6 py-10 flex-grow max-w-6xl">
    <!-- Page Header - Similar to rpt_services.php style -->
    <div class="header-box mb-8">
        <div class="flex items-center">
            <a href="../rpt_services.php" class="inline-flex items-center text-gray-600 hover:text-[var(--primary)] mr-6">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-4xl font-bold text-gray-900 mb-2">
                    Under Assessment
                </h1>
                <p class="text-gray-600 text-lg">
                    Applications currently being evaluated by assessors
                </p>
            </div>
        </div>
        <div class="h-1 w-20 rounded-full mt-4" style="background-color: #f59e0b;"></div>
    </div>

    <!-- Error Message -->
    <?php if (isset($error_message)): ?>
        <div class="notification-slide mb-6 p-4 bg-red-50 text-red-700 rounded-lg border border-red-200 text-sm">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_applications === 0): ?>
        <!-- Empty State -->
        <div class="lgu-card p-12 text-center">
            <div class="text-gray-400 mb-4">
                <i class="fas fa-chart-line text-5xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Applications Under Assessment</h3>
            <p class="text-gray-600 mb-6">You don't have any applications being assessed at the moment.</p>
            <div class="space-x-4">
                <a href="pending.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm transition-colors">
                    <i class="fas fa-clock mr-2"></i>Pending Applications
                </a>
                <a href="for_inspection.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm transition-colors">
                    <i class="fas fa-search mr-2"></i>For Inspection
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Applications List -->
        <div class="space-y-6">
            <?php foreach ($applications as $app): ?>
                <?php
                    $full_name = trim($app['first_name'] . ' ' . (!empty($app['middle_name']) ? $app['middle_name'] . ' ' : '') . $app['last_name'] . (!empty($app['suffix']) ? ' ' . $app['suffix'] : ''));

                    $documents = [];
                    try {
                        $doc_stmt = $pdo->prepare("SELECT document_type, file_name, file_path FROM property_documents WHERE registration_id = ?");
                        $doc_stmt->execute([$app['id']]);
                        $documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch(PDOException $e) {}

                    $doc_labels = [
                        'barangay_certificate' => 'Barangay Certificate',
                        'ownership_proof' => 'Proof of Ownership',
                        'valid_id' => 'Valid ID',
                        'survey_plan' => 'Survey Plan'
                    ];

                    $docs_by_type = [];
                    foreach ($documents as $doc) $docs_by_type[$doc['document_type']] = $doc;
                ?>

                <!-- Application Card -->
                <div class="lgu-card overflow-hidden">
                    <!-- Header -->
                    <div class="p-6 border-b border-gray-100">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="font-semibold text-gray-900"><?php echo $app['reference_number']; ?></span>
                                    <span class="status-badge status-assessment pulse-animation">
                                        <i class="fas fa-chart-line mr-1"></i>Under Assessment
                                    </span>
                                </div>
                                <div class="text-gray-600 text-sm">
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                    <?php echo $app['lot_location']; ?>, Brgy. <?php echo $app['barangay']; ?>
                                </div>
                            </div>
                            <div class="text-gray-600 text-sm">
                                Updated: <?php echo date('M j, Y', strtotime($app['updated_date'])); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Assessment Status -->
                    <div class="assessment-box mx-6 mt-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="font-medium text-amber-800 flex items-center">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Assessment In Progress
                            </div>
                            <div class="text-sm text-amber-700 font-medium">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                Started <?php echo date('F j, Y', strtotime($app['updated_date'])); ?>
                            </div>
                        </div>
                        <div class="text-sm text-gray-600">
                            Your property is being evaluated by our assessors. This process typically takes 3-5 business days.
                        </div>
                    </div>

                    <!-- Progress -->
                    <div class="p-6 bg-gray-50 border-b border-gray-100">
                        <div class="mb-2 text-sm text-gray-700">
                            Application Progress: <span class="font-medium">Step 3 of 4</span>
                        </div>
                        <div class="simple-progress mb-2">
                            <div class="simple-progress-fill" style="width: 75%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-600">
                            <span>Pending</span>
                            <span>Inspection</span>
                            <span class="font-medium text-amber-600">Under Assessment</span>
                            <span>Approved</span>
                        </div>
                    </div>

                    <!-- Information Sections -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Owner Information -->
                            <div>
                                <h3 class="font-medium text-gray-900 mb-4 pb-2 border-b border-gray-200">
                                    <i class="fas fa-user text-blue-600 mr-2"></i>Owner Information
                                </h3>
                                
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Full Name</div>
                                            <div class="text-sm font-medium"><?php echo $full_name; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Contact</div>
                                            <div class="text-sm font-medium"><?php echo $app['phone']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Email</div>
                                            <div class="text-sm font-medium"><?php echo $app['email']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Birthdate</div>
                                            <div class="text-sm font-medium"><?php echo isset($app['birthdate']) ? date('M j, Y', strtotime($app['birthdate'])) : '-'; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Gender</div>
                                            <div class="text-sm font-medium"><?php echo ucfirst($app['sex'] ?? '-'); ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Marital Status</div>
                                            <div class="text-sm font-medium"><?php echo ucfirst($app['marital_status'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($app['tin_number'])): ?>
                                    <div class="info-box">
                                        <div class="text-xs text-gray-500 mb-1">Tax Identification No.</div>
                                        <div class="text-sm font-medium"><?php echo $app['tin_number']; ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="info-box">
                                        <div class="text-xs text-gray-500 mb-1">Address</div>
                                        <div class="text-sm">
                                            <?php 
                                            $address_parts = [];
                                            if (!empty($app['house_number'])) $address_parts[] = $app['house_number'];
                                            if (!empty($app['street'])) $address_parts[] = $app['street'];
                                            if (!empty($app['owner_barangay'])) $address_parts[] = 'Brgy. ' . $app['owner_barangay'];
                                            if (!empty($app['owner_district'])) $address_parts[] = 'Dist. ' . $app['owner_district'];
                                            echo implode(', ', $address_parts);
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Property Information -->
                            <div>
                                <h3 class="font-medium text-gray-900 mb-4 pb-2 border-b border-gray-200">
                                    <i class="fas fa-home text-green-600 mr-2"></i>Property Information
                                </h3>
                                
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Location</div>
                                            <div class="text-sm font-medium"><?php echo $app['lot_location']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Barangay</div>
                                            <div class="text-sm font-medium">Brgy. <?php echo $app['barangay']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">District</div>
                                            <div class="text-sm font-medium"><?php echo $app['district']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">City</div>
                                            <div class="text-sm font-medium"><?php echo $app['city']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Province</div>
                                            <div class="text-sm font-medium"><?php echo $app['province']; ?></div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500 mb-1">Zip Code</div>
                                            <div class="text-sm font-medium"><?php echo $app['zip_code']; ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-box">
                                        <div class="text-xs text-gray-500 mb-1">Property Type</div>
                                        <div class="text-sm font-medium"><?php echo $app['has_building'] == 'yes' ? 'With Building' : 'Vacant Land'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Documents Section -->
                        <?php if (!empty($documents)): ?>
                            <div class="mt-8 pt-8 border-t border-gray-200">
                                <h3 class="font-medium text-gray-900 mb-4">
                                    <i class="fas fa-file-alt text-gray-600 mr-2"></i>Uploaded Documents
                                </h3>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <?php foreach ($doc_labels as $type => $label): ?>
                                        <?php
                                            $doc_url = isset($docs_by_type[$type]) ? getDocumentUrl($docs_by_type[$type]['file_path']) : '';
                                        ?>
                                        <div class="simple-doc-card" onclick="openModal('<?php echo $doc_url; ?>','<?php echo $label; ?>')">
                                            <?php if ($doc_url): ?>
                                                <?php 
                                                $file_ext = strtolower(pathinfo($docs_by_type[$type]['file_name'], PATHINFO_EXTENSION));
                                                $is_image = in_array($file_ext, ['jpg','jpeg','png','gif']);
                                                ?>
                                                <div class="mb-2 text-gray-400">
                                                    <?php if ($is_image): ?>
                                                        <i class="fas fa-image text-lg"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-file-pdf text-lg"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-sm font-medium text-gray-900 mb-1"><?php echo $label; ?></div>
                                                <div class="text-xs text-gray-500 truncate" title="<?php echo htmlspecialchars($docs_by_type[$type]['file_name']); ?>">
                                                    <?php echo htmlspecialchars($docs_by_type[$type]['file_name']); ?>
                                                </div>
                                                <div class="mt-2 text-xs text-blue-600">
                                                    Click to view
                                                </div>
                                            <?php else: ?>
                                                <div class="mb-2 text-gray-300">
                                                    <i class="fas fa-question-circle text-lg"></i>
                                                </div>
                                                <div class="text-sm font-medium text-gray-900 mb-1"><?php echo $label; ?></div>
                                                <div class="text-xs text-gray-500">Not uploaded</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Next Steps -->
                        <div class="mt-6 p-4 next-steps-box rounded-lg">
                            <div class="flex">
                                <div class="mr-3 text-amber-600">
                                    <i class="fas fa-info-circle text-lg"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900 mb-1">What's Happening Now?</div>
                                    <div class="text-sm text-gray-700 mb-2">
                                        Your property is being assessed for tax valuation. Our assessors are analyzing the property details and inspection results.
                                    </div>
                                    <div class="flex items-center text-xs text-amber-700">
                                        <i class="fas fa-clock mr-1"></i>
                                        <span>Assessment typically takes 3-5 business days to complete</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Information Section -->
        <div class="mt-8 lgu-card p-6">
            <h3 class="font-medium text-gray-900 mb-3">About Property Assessment</h3>
            <div class="space-y-2 text-sm text-gray-600">
                <div class="flex items-start">
                    <i class="fas fa-calculator text-amber-500 mt-1 mr-2"></i>
                    <span>Assessors are calculating the fair market value of your property</span>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-chart-line text-amber-500 mt-1 mr-2"></i>
                    <span>Tax assessment is based on property size, location, improvements, and market rates</span>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-file-invoice-dollar text-amber-500 mt-1 mr-2"></i>
                    <span>Once assessment is complete, you'll receive the tax declaration and payment details</span>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-bell text-amber-500 mt-1 mr-2"></i>
                    <span>You'll be notified via email once the assessment is complete and ready for review</span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<footer class="bg-white border-t border-gray-200 mt-16">
    <div class="container mx-auto px-6 py-12 max-w-7xl">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
            <!-- Brand -->
            <div class="col-span-1">
                <div class="flex items-center mb-4 text-2xl font-bold">
                    <span style="color: #4a90e2;">Go</span><span style="color: #4caf50;">Serve</span><span style="color: #4a90e2;">PH</span>
                </div>
                <p class="text-gray-600 leading-relaxed">
                    The official digital gateway of your Local Government Unit, providing efficient and transparent government services.
                </p>
            </div>
            
            <!-- Portal Links -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Portal</h4>
                <ul class="space-y-3 text-gray-600">
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">Services</a></li>
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">My Applications</a></li>
                    <li><a href="#" class="hover:text-[#4a90e2] transition-colors">Settings</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Contact</h4>
                <ul class="space-y-3 text-gray-600">
                    <li><i class="fas fa-phone mr-2 text-gray-400"></i> (02) 8123 4567</li>
                    <li><i class="fas fa-envelope mr-2 text-gray-400"></i> support@goserveph.gov.ph</li>
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