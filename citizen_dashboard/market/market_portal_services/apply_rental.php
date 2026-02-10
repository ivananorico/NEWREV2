<?php
// revenue2/citizen_dashboard/market/market_portal_services/apply_rental.php

// Remove session_start() and replace with navbar include
// The navbar will handle session checking and logout functionality
require_once '../../../citizen_dashboard/navbar.php';

// Check if user is logged in (already handled in navbar.php, but double-check)
if (!isset($_SESSION['user_id'])) {
    // This should not happen if navbar.php is included properly
    exit('Session error. Please try logging in again.');
}

if (!isset($_GET['map_id'])) {
    header('Location: market_portal_services.php');
    exit();
}

$map_id = intval($_GET['map_id']);

// Include database connection
include_once '../../../db/Market/market_db.php';

// Fetch map details
$stmt = $pdo->prepare("SELECT * FROM maps WHERE id = ?");
$stmt->execute([$map_id]);
$map = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$map) {
    header('Location: market_portal_services.php');
    exit();
}

// Fix image path
$image_path = str_replace('uploads/market/maps/', '../../../uploads/market/maps/', $map['file_path']);

// Fetch stalls for this map
$stmt = $pdo->prepare("
    SELECT s.*, sr.class_name, sr.price as class_price, sr.description as class_desc
    FROM stalls s
    LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
    WHERE s.map_id = ?
    ORDER BY s.name
");
$stmt->execute([$map_id]);
$stalls = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count statistics
$total_stalls = count($stalls);
$available_stalls = count(array_filter($stalls, fn($s) => $s['status'] === 'available'));
$occupied_stalls = count(array_filter($stalls, fn($s) => $s['status'] === 'occupied'));
$reserved_stalls = count(array_filter($stalls, fn($s) => $s['status'] === 'reserved'));
$maintenance_stalls = count(array_filter($stalls, fn($s) => $s['status'] === 'maintenance'));

// Get price range
$prices = array_column($stalls, 'price');
$min_price = count($prices) > 0 ? min($prices) : 0;
$max_price = count($prices) > 0 ? max($prices) : 0;

$user_name = $_SESSION['user_name'] ?? 'Citizen';

// Get the base URL for the background image
// Get base URL for background image - FIXED: Using reliable method
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $protocol = 'https://';
} else {
    $protocol = 'http://';
}
$base_url = $protocol . $_SERVER['HTTP_HOST'];
$bg_image_path = $base_url . '/revenue2/Login/images/gsmbg.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($map['name']); ?> - Market Stall Rental | GoServePH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../images/GSM_logo.png">
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
            background: linear-gradient(135deg, rgba(240, 240, 240, 0.4) 0%, rgba(230, 230, 230, 0.4) 50%, rgba(220, 220, 220, 0.3) 100%);
            position: relative;
            overflow-x: hidden;
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        /* Background image with blur */
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
        
        /* Animated background particles */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(76, 175, 80, 0.1) 0%, transparent 50%),
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

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(74, 144, 226, 0.15);
        }

        .header-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border-radius: 20px;
            padding: 1.5rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            border-radius: 1rem;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(74, 144, 226, 0.15);
        }

        /* Map Container - Original size preserved */
        .market-map {
            width: 800px;
            height: 600px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin: 0 auto;
            position: relative;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            overflow: hidden;
        }

        /* Stall Markers */
        .stall-marker {
            position: absolute;
            cursor: pointer;
            border-radius: 4px;
            border: 2px solid white;
            text-align: center;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        
        .stall-marker:hover {
            transform: scale(1.05);
            z-index: 10;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .stall-content {
            padding: 3px;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: rgba(0, 0, 0, 0.25);
        }
        
        .stall-name {
            font-size: 10px;
            color: white;
            font-weight: 600;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            margin-bottom: 2px;
        }
        
        .stall-price {
            font-size: 8px;
            background: rgba(255, 255, 255, 0.25);
            padding: 2px 4px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
        }
        
        /* Status Colors */
        .status-available {
            background: #4caf50;
        }
        
        .status-occupied {
            background: #f44336;
        }
        
        .status-reserved {
            background: #ff9800;
        }
        
        .status-maintenance {
            background: #808080; /* Gray for Maintenance */
        }
        
        .stall-marker.selected {
            border: 3px solid var(--primary);
            box-shadow: 0 0 0 2px rgba(74, 144, 226, 0.2), 0 4px 8px rgba(0,0,0,0.2);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 2px rgba(74, 144, 226, 0.2), 0 4px 8px rgba(0,0,0,0.2); }
            50% { box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.1), 0 4px 8px rgba(0,0,0,0.2); }
            100% { box-shadow: 0 0 0 2px rgba(74, 144, 226, 0.2), 0 4px 8px rgba(0,0,0,0.2); }
        }

        /* Panel Styling */
        .simple-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .simple-panel:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(74, 144, 226, 0.12);
        }

        .panel-title {
            color: #1a1a1a;
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .panel-title i {
            color: var(--primary);
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }
        
        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            padding: 2rem;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.2);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Steps */
        .steps-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        }

        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 1.25rem;
        }
        
        .step-item:last-child {
            margin-bottom: 0;
        }
        
        .step-number {
            background: var(--primary);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .step-text {
            font-size: 0.95rem;
            color: #4b5563;
            line-height: 1.5;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .stat-box {
            background: rgba(249, 250, 251, 0.8);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            border: 1px solid rgba(229, 231, 235, 0.8);
            transition: all 0.3s ease;
        }
        
        .stat-box:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateY(-2px);
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Legend */
        .legend-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 8px;
            background: rgba(249, 250, 251, 0.8);
            transition: all 0.2s ease;
        }
        
        .legend-item:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateX(4px);
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Status Tags */
        .status-tag {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .tag-available { 
            background: #e8f5e9; 
            color: #2e7d32; 
        }
        .tag-occupied { 
            background: #ffebee; 
            color: #c62828; 
        }
        .tag-reserved { 
            background: #fff3e0; 
            color: #ef6c00; 
        }
        .tag-maintenance { 
            background: #e5e7eb; 
            color: #6b7280; 
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            width: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #3a7bd5);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #3a7bd5, #2a6bc4);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(74, 144, 226, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--accent), #43a047);
            color: white;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #43a047, #388e3c);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(76, 175, 80, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary), #8a949f);
            color: white;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #8a949f, #7a8694);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(154, 165, 177, 0.3);
        }

        /* Back Button */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s;
            background: rgba(74, 144, 226, 0.1);
        }

        .back-button:hover {
            background: rgba(74, 144, 226, 0.2);
            transform: translateX(-2px);
        }

        /* Improved Title */
        .market-title {
            font-size: 2.25rem;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 8px;
            position: relative;
            display: inline-block;
        }
        
        .market-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 2px;
        }
        
        .market-subtitle {
            color: var(--secondary);
            font-size: 1.1rem;
            margin-top: 16px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .market-map {
                width: 700px;
                height: 525px;
            }
        }
        
        @media (max-width: 992px) {
            .market-map {
                width: 100%;
                height: 500px;
            }
        }
        
        @media (max-width: 768px) {
            .market-map {
                height: 400px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                padding: 1.5rem;
                margin: 1rem;
            }
            
            .market-title {
                font-size: 1.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .market-map {
                height: 300px;
            }
            
            .market-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- No need to include navbar.php again since it's already included at the top -->

    <!-- Modal -->
    <div id="stallModal" class="modal-overlay">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-900" id="modalStallName">Stall Details</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="modalStallDetails" class="mb-6"></div>
            <div class="flex gap-3">
                <button onclick="closeModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Close
                </button>
                <button onclick="applyForStall()" id="modalApplyBtn" class="btn btn-success">
                    <i class="fas fa-file-alt"></i> Apply for Rental
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Improved Header -->
        <div class="header-box mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <a href="market_portal_services.php" class="back-button self-start mb-4 md:mb-0">
                    <i class="fas fa-arrow-left"></i> Back to Maps
                </a>
                
                <div class="text-center md:flex-1">
                    <h1 class="market-title"><?php echo htmlspecialchars($map['name']); ?> Market</h1>
                    <p class="market-subtitle">Browse and select a stall to rent</p>
                </div>
                
                <!-- Empty div for spacing -->
                <div class="hidden md:block w-32"></div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="steps-container mb-8">
            <h3 class="panel-title">
                <i class="fas fa-info-circle"></i> How to Rent a Stall
            </h3>
            <div class="step-item">
                <div class="step-number">1</div>
                <div class="step-text">Click on an available stall (green color) on the market map</div>
            </div>
            <div class="step-item">
                <div class="step-number">2</div>
                <div class="step-text">Review stall details in the popup window</div>
            </div>
            <div class="step-item">
                <div class="step-number">3</div>
                <div class="step-text">Click "Apply" to start the rental application process</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Map Section -->
            <div class="lg:col-span-2">
                <div class="simple-panel">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="panel-title">
                            <i class="fas fa-map"></i> Market Layout
                        </h3>
                        <span class="text-sm text-gray-600">
                            <span class="font-semibold"><?php echo $available_stalls; ?></span> of <span class="font-semibold"><?php echo $total_stalls; ?></span> stalls available
                        </span>
                    </div>
                    
                    <?php if ($image_path): ?>
                        <div class="market-map"
                             style="background-image: url('<?php echo htmlspecialchars($image_path); ?>')"
                             id="marketMap">
                            <?php foreach ($stalls as $stall): 
                                $pixel_width = $stall['pixel_width'] ?? 80;
                                $pixel_height = $stall['pixel_height'] ?? 60;
                                $status = $stall['status'] ?? 'available';
                                $price = $stall['price'] ?? $stall['class_price'] ?? 0;
                                $is_available = ($status === 'available');
                            ?>
                            <div class="stall-marker status-<?php echo $status; ?>"
                                 style="left: <?php echo $stall['pos_x']; ?>px;
                                        top: <?php echo $stall['pos_y']; ?>px;
                                        width: <?php echo $pixel_width; ?>px;
                                        height: <?php echo $pixel_height; ?>px;"
                                 data-stall-id="<?php echo $stall['id']; ?>"
                                 data-stall-name="<?php echo htmlspecialchars($stall['name']); ?>"
                                 data-stall-price="<?php echo number_format($price, 2); ?>"
                                 data-stall-class="<?php echo htmlspecialchars($stall['class_name'] ?? 'N/A'); ?>"
                                 data-stall-status="<?php echo $status; ?>"
                                 data-stall-length="<?php echo $stall['length']; ?>"
                                 data-stall-width="<?php echo $stall['width']; ?>"
                                 data-stall-height="<?php echo $stall['height']; ?>"
                                 data-stall-available="<?php echo $is_available ? 'true' : 'false'; ?>"
                                 onclick="showStallModal(this)"
                                 title="<?php echo htmlspecialchars($stall['name']); ?> - <?php echo ucfirst($status); ?>">
                                <div class="stall-content">
                                    <div class="stall-name"><?php echo htmlspecialchars($stall['name']); ?></div>
                                    <div class="stall-price">₱<?php echo number_format($price, 2); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="market-map flex flex-col items-center justify-center bg-gray-100 rounded-lg">
                            <i class="fas fa-exclamation-triangle text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-600 font-medium">Map image not available</p>
                            <p class="text-sm text-gray-500 mt-2">Please contact market administration</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Statistics -->
                <div class="simple-panel">
                    <h3 class="panel-title">
                        <i class="fas fa-chart-bar"></i> Statistics
                    </h3>
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $total_stalls; ?></div>
                            <div class="stat-label">Total Stalls</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number" style="color: #4caf50;"><?php echo $available_stalls; ?></div>
                            <div class="stat-label">Available</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number" style="color: #f44336;"><?php echo $occupied_stalls; ?></div>
                            <div class="stat-label">Occupied</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number" style="color: #808080;"><?php echo $maintenance_stalls; ?></div>
                            <div class="stat-label">Maintenance</div>
                        </div>
                    </div>
                </div>

                <!-- Color Legend -->
                <div class="simple-panel">
                    <h3 class="panel-title">
                        <i class="fas fa-palette"></i> Status Legend
                    </h3>
                    <div class="space-y-2">
                        <div class="legend-item">
                            <div class="legend-color" style="background: #4caf50;"></div>
                            <div class="flex-1">
                                <span class="font-medium text-gray-900">Available</span>
                                <div class="text-xs text-gray-500">Ready for rent</div>
                            </div>
                            <span class="text-sm font-semibold"><?php echo $available_stalls; ?></span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #f44336;"></div>
                            <div class="flex-1">
                                <span class="font-medium text-gray-900">Occupied</span>
                                <div class="text-xs text-gray-500">Already rented</div>
                            </div>
                            <span class="text-sm font-semibold"><?php echo $occupied_stalls; ?></span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #ff9800;"></div>
                            <div class="flex-1">
                                <span class="font-medium text-gray-900">Reserved</span>
                                <div class="text-xs text-gray-500">Temporarily held</div>
                            </div>
                            <span class="text-sm font-semibold"><?php echo $reserved_stalls; ?></span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #808080;"></div>
                            <div class="flex-1">
                                <span class="font-medium text-gray-900">Maintenance</span>
                                <div class="text-xs text-gray-500">Under repair</div>
                            </div>
                            <span class="text-sm font-semibold"><?php echo $maintenance_stalls; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Note -->
        <div class="mt-8 text-center text-gray-500 text-sm">
            <p>© <?php echo date('Y'); ?> GoServePH Market Services. All stall rentals are subject to availability and approval.</p>
        </div>
    </main>

    <!-- Consistent Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
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
                        <li><a href="../../../citizen_dashboard/citizen_dashboard.php" class="hover:text-[#4a90e2] transition-colors">Dashboard</a></li>
                        <li><a href="../market_services.php" class="hover:text-[#4a90e2] transition-colors">Market Services</a></li>
                        <li><a href="market_portal_services.php" class="hover:text-[#4a90e2] transition-colors">Market Maps</a></li>
                    </ul>
                </div>

                <!-- Contact / Need Help Section -->
                <div class="md:col-span-2">
                    <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Need Assistance?</h4>
                    <p class="text-gray-600 mb-4 text-sm">
                        Our market services team is here to help you with the rental process.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-phone mr-3 text-gray-400"></i>
                                <div>
                                    <div class="text-sm font-medium">Market Office</div>
                                    <div class="text-sm">(02) 1234-5678</div>
                                </div>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-envelope mr-3 text-gray-400"></i>
                                <div>
                                    <div class="text-sm font-medium">Email Support</div>
                                    <div class="text-sm">market@goserveph.gov.ph</div>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-clock mr-3 text-gray-400"></i>
                                <div>
                                    <div class="text-sm font-medium">Office Hours</div>
                                    <div class="text-sm">Mon-Fri: 8AM - 5PM</div>
                                </div>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-map-marker-alt mr-3 text-gray-400"></i>
                                <div>
                                    <div class="text-sm font-medium">Location</div>
                                    <div class="text-sm">Municipal Market Office</div>
                                </div>
                            </div>
                        </div>
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

    <!-- JavaScript -->
    <script>
        let selectedStall = null;
        const modal = document.getElementById('stallModal');

        function showStallModal(stallElement) {
            const isAvailable = stallElement.dataset.stallAvailable === 'true';
            const status = stallElement.dataset.stallStatus;
            
            // Remove previous selection
            document.querySelectorAll('.stall-marker').forEach(marker => {
                marker.classList.remove('selected');
            });
            
            // Add selected class
            stallElement.classList.add('selected');
            
            // Store selected stall data
            selectedStall = {
                id: stallElement.dataset.stallId,
                name: stallElement.dataset.stallName,
                price: stallElement.dataset.stallPrice,
                class: stallElement.dataset.stallClass,
                status: status,
                length: stallElement.dataset.stallLength,
                width: stallElement.dataset.stallWidth,
                height: stallElement.dataset.stallHeight,
                isAvailable: isAvailable
            };
            
            // Update modal title
            document.getElementById('modalStallName').textContent = selectedStall.name;
            
            // Status tag
            let statusTag = '';
            if (status === 'available') {
                statusTag = '<span class="status-tag tag-available"><i class="fas fa-check-circle mr-2"></i>Available</span>';
            } else if (status === 'occupied') {
                statusTag = '<span class="status-tag tag-occupied"><i class="fas fa-times-circle mr-2"></i>Occupied</span>';
            } else if (status === 'reserved') {
                statusTag = '<span class="status-tag tag-reserved"><i class="fas fa-clock mr-2"></i>Reserved</span>';
            } else if (status === 'maintenance') {
                statusTag = '<span class="status-tag tag-maintenance"><i class="fas fa-tools mr-2"></i>Maintenance</span>';
            }
            
            // Calculate dimensions
            const floorArea = (parseFloat(selectedStall.length) * parseFloat(selectedStall.width)).toFixed(2);
            const volume = (parseFloat(selectedStall.length) * parseFloat(selectedStall.width) * parseFloat(selectedStall.height)).toFixed(2);
            
            document.getElementById('modalStallDetails').innerHTML = `
                <div class="space-y-6">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-3 rounded-lg bg-gray-50">
                            <div class="text-xs text-gray-500 mb-1">Stall Class</div>
                            <div class="font-semibold text-gray-900">${selectedStall.class}</div>
                        </div>
                        <div class="p-3 rounded-lg bg-gray-50">
                            <div class="text-xs text-gray-500 mb-1">Status</div>
                            <div>${statusTag}</div>
                        </div>
                    </div>
                    
                    <!-- Price -->
                    <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05));">
                        <div class="text-sm text-gray-600 mb-2">Monthly Rental Fee</div>
                        <div class="flex items-baseline">
                            <div class="text-3xl font-bold text-orange-600">₱${selectedStall.price}</div>
                            <div class="ml-2 text-sm text-gray-500">/ month</div>
                        </div>
                    </div>
                    
                    <!-- Dimensions -->
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-ruler-combined mr-2 text-primary"></i>
                            Stall Dimensions
                        </h4>
                        <div class="grid grid-cols-3 gap-3 mb-3">
                            <div class="text-center p-3 rounded-lg border border-gray-200">
                                <div class="text-xs text-gray-500 mb-1">Length</div>
                                <div class="font-semibold text-gray-900">${selectedStall.length}m</div>
                            </div>
                            <div class="text-center p-3 rounded-lg border border-gray-200">
                                <div class="text-xs text-gray-500 mb-1">Width</div>
                                <div class="font-semibold text-gray-900">${selectedStall.width}m</div>
                            </div>
                            <div class="text-center p-3 rounded-lg border border-gray-200">
                                <div class="text-xs text-gray-500 mb-1">Height</div>
                                <div class="font-semibold text-gray-900">${selectedStall.height}m</div>
                            </div>
                        </div>
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-calculator mr-2"></i>
                            Floor Area: <strong>${floorArea} m²</strong> • Volume: <strong>${volume} m³</strong>
                        </div>
                    </div>
                    
                    ${!isAvailable ? `
                    <div class="p-4 rounded-lg bg-yellow-50 border border-yellow-200">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-yellow-500 mt-0.5 mr-3"></i>
                            <div>
                                <div class="font-medium text-yellow-800 mb-1">Not Available for Rent</div>
                                <div class="text-sm text-yellow-700">This stall is currently ${selectedStall.status}. Please select an available stall (green color).</div>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
            
            // Update apply button
            const applyBtn = document.getElementById('modalApplyBtn');
            if (isAvailable && status === 'available') {
                applyBtn.disabled = false;
                applyBtn.innerHTML = '<i class="fas fa-file-alt mr-2"></i> Apply for Rental';
                applyBtn.className = 'btn btn-success';
                applyBtn.onclick = applyForStall;
            } else {
                applyBtn.disabled = true;
                let reason = status === 'occupied' ? 'Already Rented' :
                            status === 'reserved' ? 'Reserved' :
                            status === 'maintenance' ? 'Under Maintenance' : 'Not Available';
                applyBtn.innerHTML = `<i class="fas fa-ban mr-2"></i> ${reason}`;
                applyBtn.className = 'btn btn-secondary';
                applyBtn.onclick = null;
            }
            
            // Show modal with animation
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
            document.querySelectorAll('.stall-marker').forEach(marker => {
                marker.classList.remove('selected');
            });
            selectedStall = null;
        }

        function applyForStall() {
            if (!selectedStall) return;
            
            if (selectedStall.isAvailable && selectedStall.status === 'available') {
                window.location.href = `rental_application_form.php?map_id=<?php echo $map_id; ?>&stall_id=${selectedStall.id}`;
            } else {
                alert('This stall is not available for rental.');
            }
        }

        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
        
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') closeModal();
        });

        // Handle image errors
        document.addEventListener('DOMContentLoaded', function() {
            const marketMap = document.getElementById('marketMap');
            if (marketMap) {
                const bgImage = marketMap.style.backgroundImage;
                if (bgImage) {
                    const img = new Image();
                    img.src = bgImage.replace(/url\(['"]?(.*?)['"]?\)/i, '$1');
                    img.onerror = function() {
                        marketMap.style.backgroundImage = 'none';
                        marketMap.innerHTML += `
                            <div class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200">
                                <i class="fas fa-exclamation-triangle text-4xl text-gray-400 mb-4"></i>
                                <p class="text-gray-600 font-medium mb-2">Map Image Could Not Be Loaded</p>
                                <p class="text-sm text-gray-500">Please contact market administration for assistance</p>
                            </div>
                        `;
                    };
                }
            }
        });
    </script> 
</body>
</html>