<?php
// apply_rental.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['map_id'])) {
    header('Location: market_portal_services.php');
    exit();
}

$map_id = intval($_GET['map_id']);

// Database connection
$host = 'localhost:3307';
$dbname = 'market_rent';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

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
    SELECT s.*, sr.class_name, sr.price as class_price, sr.description as class_desc,
           sec.name as section_name
    FROM stalls s
    LEFT JOIN stall_rights sr ON s.class_id = sr.class_id
    LEFT JOIN sections sec ON s.section_id = sec.id
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

$user_name = $_SESSION['user_name'] ?? 'Citizen';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($map['name']); ?> - Market Stall Rental</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* LGU Professional Theme */
        :root {
            --lgu-blue: #1e3a8a;
            --lgu-green: #059669;
            --lgu-red: #dc2626;
            --lgu-orange: #ea580c;
            --lgu-gray: #6b7280;
            --lgu-light: #f8fafc;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Map Title Section - More Visible */
        .map-title-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 5px solid var(--lgu-blue);
        }

        .map-title {
            color: var(--lgu-blue);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .map-subtitle {
            color: #4b5563;
            font-size: 1rem;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .map-id-badge {
            background: var(--lgu-blue);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Map Display Area - Fixed 800x600 */
        .map-display-area {
            background: #f8fafc;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
        }

        .market-map {
            width: 800px;
            height: 600px;
            background-color: white;
            border: 2px solid #d1d5db;
            border-radius: 8px;
            margin: 0 auto;
            position: relative;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Stall Markers - Professional Style */
        .stall-marker {
            position: absolute;
            cursor: pointer;
            user-select: none;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            border: 2px solid white;
            text-align: center;
            font-family: 'Segoe UI', system-ui;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .stall-marker:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 100;
        }

        .stall-content {
            padding: 8px;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            pointer-events: none;
        }

        .stall-name {
            font-weight: 700;
            font-size: 11px;
            color: white;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            margin-bottom: 3px;
        }

        .stall-price {
            font-weight: 600;
            font-size: 10px;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 6px;
            border-radius: 10px;
            margin-bottom: 3px;
        }

        .stall-details {
            font-size: 9px;
            opacity: 0.9;
        }

        /* Status Colors */
        .status-available {
            background: linear-gradient(135deg, var(--lgu-green) 0%, #10b981 100%);
        }

        .status-occupied {
            background: linear-gradient(135deg, var(--lgu-red) 0%, #ef4444 100%);
        }

        .status-reserved {
            background: linear-gradient(135deg, var(--lgu-orange) 0%, #f97316 100%);
        }

        .status-maintenance {
            background: linear-gradient(135deg, var(--lgu-gray) 0%, #9ca3af 100%);
        }

        /* Selected Stall */
        .stall-marker.selected {
            box-shadow: 0 0 0 4px var(--lgu-blue), 0 6px 20px rgba(30, 58, 138, 0.3);
            border: 3px solid var(--lgu-blue);
            animation: pulse-border 2s infinite;
        }

        @keyframes pulse-border {
            0% { border-color: var(--lgu-blue); }
            50% { border-color: rgba(30, 58, 138, 0.5); }
            100% { border-color: var(--lgu-blue); }
        }

        /* Side Panel - LGU Style */
        .side-panel {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 25px;
            border: 1px solid #e5e7eb;
            height: fit-content;
        }

        .panel-title {
            color: var(--lgu-blue);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            border: 1px solid #e5e7eb;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--lgu-blue);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 500;
        }

        /* Legend */
        .legend-container {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            padding: 8px 12px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 2px solid white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .legend-text {
            font-size: 0.9rem;
            color: #374151;
            font-weight: 500;
        }

        /* Selected Stall Info */
        .selected-info {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-radius: 10px;
            padding: 20px;
            border: 2px solid #dbeafe;
            margin-bottom: 25px;
        }

        .selected-title {
            color: var(--lgu-blue);
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .detail-item {
            background: white;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 1rem;
            color: #1f2937;
            font-weight: 600;
        }

        /* LGU Buttons */
        .lgu-btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.25s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            border: none;
        }

        .lgu-btn-primary {
            background: linear-gradient(135deg, var(--lgu-blue) 0%, #1e40af 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.3);
        }

        .lgu-btn-primary:hover {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.4);
        }

        .lgu-btn-secondary {
            background: white;
            color: var(--lgu-blue);
            border: 2px solid var(--lgu-blue);
        }

        .lgu-btn-secondary:hover {
            background: #f8fafc;
            transform: translateY(-2px);
        }

        .lgu-btn-success {
            background: linear-gradient(135deg, var(--lgu-green) 0%, #10b981 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        }

        .lgu-btn-success:hover {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.4);
        }

        /* Breadcrumb */
        .breadcrumb {
            background: white;
            padding: 15px 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #e5e7eb;
        }

        .breadcrumb a {
            color: var(--lgu-blue);
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .breadcrumb-separator {
            color: #9ca3af;
            margin: 0 10px;
        }

        /* Action Bar */
        .action-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e5e7eb;
            margin-bottom: 25px;
        }

        /* Instructions */
        .instructions-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px solid #dbeafe;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .instructions-title {
            color: var(--lgu-blue);
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .instruction-step {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 10px;
        }

        .step-number {
            background: var(--lgu-blue);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            flex-shrink: 0;
        }

        .step-text {
            color: #374151;
            font-size: 0.95rem;
        }

        /* Map Dimensions Info */
        .map-dimensions {
            background: #f8fafc;
            padding: 10px 15px;
            border-radius: 6px;
            margin-top: 15px;
            text-align: center;
            border: 1px solid #e5e7eb;
        }

        .dimensions-text {
            color: #6b7280;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .market-map {
                width: 100%;
                height: 500px;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 15px;
            }
            
            .market-map {
                height: 400px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .action-bar {
                flex-direction: column;
                gap: 15px;
            }
            
            .lgu-btn {
                width: 100%;
            }
            
            .map-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Keep Original Navbar -->
    <?php include '../../navbar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="../../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span class="breadcrumb-separator">›</span>
            <a href="../market_services.php">Market Services</a>
            <span class="breadcrumb-separator">›</span>
            <a href="market_portal_services.php">Market Maps</a>
            <span class="breadcrumb-separator">›</span>
            <span class="text-gray-600 font-medium"><?php echo htmlspecialchars($map['name']); ?></span>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Market Stall Selection</h2>
                <p class="text-sm text-gray-600">Select an available stall to proceed with rental application</p>
            </div>
            <div id="applyButton" style="display: none;">
                <button onclick="proceedToApplication()" class="lgu-btn lgu-btn-success">
                    <i class="fas fa-file-contract"></i>
                    Proceed to Application
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column - Map -->
            <div class="lg:col-span-2">
                <!-- Map Title Section - More Visible -->
                <div class="map-title-section">
                    <h1 class="map-title">
                        <i class="fas fa-map-marked-alt"></i>
                        <?php echo htmlspecialchars($map['name']); ?>
                    </h1>
                    <div class="flex flex-wrap items-center gap-4">
                        <p class="map-subtitle">
                            <i class="fas fa-calendar-alt"></i>
                            Uploaded: <?php echo date('F d, Y', strtotime($map['created_at'])); ?>
                        </p>
                        <span class="map-id-badge">
                            <i class="fas fa-hashtag"></i>
                            Map ID: <?php echo $map['id']; ?>
                        </span>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="instructions-box mb-6">
                    <h3 class="instructions-title">
                        <i class="fas fa-graduation-cap"></i>
                        How to Select a Stall
                    </h3>
                    <div class="instruction-step">
                        <span class="step-number">1</span>
                        <span class="step-text">Locate available stalls marked with green color on the map</span>
                    </div>
                    <div class="instruction-step">
                        <span class="step-number">2</span>
                        <span class="step-text">Click on your preferred stall to select it</span>
                    </div>
                    <div class="instruction-step">
                        <span class="step-number">3</span>
                        <span class="step-text">Review stall details in the right panel</span>
                    </div>
                    <div class="instruction-step">
                        <span class="step-number">4</span>
                        <span class="step-text">Click "Proceed to Application" to continue</span>
                    </div>
                </div>

                <!-- Map Container -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <!-- Map Display -->
                    <div class="map-display-area">
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
                                     data-stall-section="<?php echo htmlspecialchars($stall['section_name'] ?? 'N/A'); ?>"
                                     data-stall-status="<?php echo $status; ?>"
                                     data-stall-available="<?php echo $is_available ? 'true' : 'false'; ?>"
                                     onclick="selectStall(this)"
                                     title="<?php echo htmlspecialchars($stall['name'] . ' - ₱' . number_format($price, 2) . ' - ' . $status); ?>">
                                    <div class="stall-content">
                                        <div class="stall-name"><?php echo htmlspecialchars($stall['name']); ?></div>
                                        <div class="stall-price">₱<?php echo number_format($price, 2); ?></div>
                                        <?php if ($stall['section_name']): ?>
                                            <div class="stall-details"><?php echo htmlspecialchars($stall['section_name']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12 bg-gray-50 rounded-lg">
                                <i class="fas fa-map-marked-alt text-5xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 text-lg">Map image not available</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Map Dimensions Info -->
                    <div class="map-dimensions">
                        <p class="dimensions-text">
                            <i class="fas fa-expand-alt"></i>
                            Map Dimensions: 800×600 pixels • Stall dimensions shown to scale
                        </p>
                    </div>
                </div>
            </div>

            <!-- Right Column - Side Panel -->
            <div>
                <!-- Statistics -->
                <div class="side-panel mb-6">
                    <h3 class="panel-title">
                        <i class="fas fa-chart-bar"></i>
                        Market Statistics
                    </h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $total_stalls; ?></div>
                            <div class="stat-label">Total Stalls</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" style="color: var(--lgu-green);"><?php echo $available_stalls; ?></div>
                            <div class="stat-label">Available</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" style="color: var(--lgu-red);"><?php echo $occupied_stalls; ?></div>
                            <div class="stat-label">Occupied</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" style="color: var(--lgu-orange);"><?php echo $reserved_stalls; ?></div>
                            <div class="stat-label">Reserved</div>
                        </div>
                    </div>
                </div>

                <!-- Legend -->
                <div class="side-panel mb-6">
                    <h3 class="panel-title">
                        <i class="fas fa-key"></i>
                        Status Legend
                    </h3>
                    <div class="legend-container">
                        <div class="legend-item">
                            <div class="legend-color" style="background: var(--lgu-green);"></div>
                            <span class="legend-text">Available - Ready for rental</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: var(--lgu-red);"></div>
                            <span class="legend-text">Occupied - Currently rented</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: var(--lgu-orange);"></div>
                            <span class="legend-text">Reserved - Under reservation</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: var(--lgu-gray);"></div>
                            <span class="legend-text">Maintenance - Under repair</span>
                        </div>
                    </div>
                </div>

                <!-- Selected Stall Info -->
                <div id="selectedStallInfo" class="selected-info" style="display: none;">
                    <h3 class="selected-title">
                        <i class="fas fa-store"></i>
                        Selected Stall Information
                    </h3>
                    <div class="details-grid">
                        <div class="detail-item">
                            <div class="detail-label">Stall Name</div>
                            <div class="detail-value" id="selectedStallName">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Price (Monthly)</div>
                            <div class="detail-value" id="selectedStallPrice">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Class</div>
                            <div class="detail-value" id="selectedStallClass">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Section</div>
                            <div class="detail-value" id="selectedStallSection">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Status</div>
                            <div class="detail-value" id="selectedStallStatus">-</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Availability</div>
                            <div class="detail-value text-green-600" id="selectedStallAvailability">-</div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="side-panel">
                    <h3 class="panel-title">
                        <i class="fas fa-cogs"></i>
                        Actions
                    </h3>
                    <div class="space-y-3">
                        <button onclick="goBack()" class="lgu-btn lgu-btn-secondary w-full">
                            <i class="fas fa-arrow-left"></i>
                            Back to Market Maps
                        </button>
                        <button onclick="refreshPage()" class="lgu-btn w-full" style="background: #f8fafc; color: #374151; border: 1px solid #e5e7eb;">
                            <i class="fas fa-sync-alt"></i>
                            Refresh Page
                        </button>
                        <button onclick="showHelp()" class="lgu-btn w-full" style="background: #fef3c7; color: #92400e; border: 1px solid #fbbf24;">
                            <i class="fas fa-question-circle"></i>
                            Need Help?
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Note -->
        <div class="mt-8 text-center text-sm text-gray-500">
            <p><i class="fas fa-shield-alt mr-1"></i> This is an official LGU system. All transactions are recorded and monitored.</p>
            <p class="mt-1">For assistance, contact Market Administration Office at (02) 1234-5678 or email market-admin@lgu.gov.ph</p>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        let selectedStall = null;

        function selectStall(stallElement) {
            const isAvailable = stallElement.dataset.stallAvailable === 'true';
            
            if (!isAvailable) {
                showLGUNotification('This stall is not available for rental. Please select an available stall (green color).', 'warning');
                return;
            }
            
            // Remove previous selection
            document.querySelectorAll('.stall-marker').forEach(marker => {
                marker.classList.remove('selected');
            });
            
            // Add selected class to current stall
            stallElement.classList.add('selected');
            
            // Store selected stall data
            selectedStall = {
                id: stallElement.dataset.stallId,
                name: stallElement.dataset.stallName,
                price: stallElement.dataset.stallPrice,
                class: stallElement.dataset.stallClass,
                section: stallElement.dataset.stallSection,
                status: stallElement.dataset.stallStatus
            };
            
            // Update selected stall info
            document.getElementById('selectedStallName').textContent = selectedStall.name;
            document.getElementById('selectedStallPrice').textContent = '₱' + selectedStall.price + '/month';
            document.getElementById('selectedStallClass').textContent = selectedStall.class;
            document.getElementById('selectedStallSection').textContent = selectedStall.section;
            document.getElementById('selectedStallStatus').textContent = selectedStall.status.charAt(0).toUpperCase() + selectedStall.status.slice(1);
            document.getElementById('selectedStallAvailability').textContent = 'Available for Rent';
            
            // Show selected stall info
            document.getElementById('selectedStallInfo').style.display = 'block';
            
            // Show apply button
            document.getElementById('applyButton').style.display = 'block';
            
            showLGUNotification('Stall selected successfully! You may now proceed with the application.', 'success');
            
            // Scroll to top for mobile
            if (window.innerWidth < 768) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        function proceedToApplication() {
            if (!selectedStall) {
                showLGUNotification('Please select a stall first.', 'error');
                return;
            }
            
            // Redirect to rental application form
            window.location.href = `rental_application_form.php?map_id=<?php echo $map_id; ?>&stall_id=${selectedStall.id}`;
        }

        function goBack() {
            window.location.href = 'market_portal_services.php';
        }

        function refreshPage() {
            window.location.reload();
        }

        function showHelp() {
            alert('Need Assistance?\n\n1. For technical issues: IT Support - (02) 1234-5678\n2. For rental inquiries: Market Office - (02) 1234-5679\n3. For payment concerns: Treasury Office - (02) 1234-5680\n\nOffice Hours: Monday-Friday, 8:00 AM - 5:00 PM');
        }

        function showLGUNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-4 rounded-lg shadow-lg text-white font-medium z-50 transform transition-all duration-300 ${
                type === 'success' ? 'bg-green-600' :
                type === 'error' ? 'bg-red-600' :
                type === 'warning' ? 'bg-orange-500' : 'bg-blue-600'
            }`;
            notification.innerHTML = `
                <div class="flex items-center gap-3">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Remove after 4 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100px)';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 4000);
        }

        // Handle image loading errors
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
                            <div class="absolute inset-0 flex flex-col items-center justify-center bg-gray-50">
                                <i class="fas fa-exclamation-triangle text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 font-medium">Map image could not be loaded</p>
                                <p class="text-sm text-gray-400 mt-2">Please contact market administration</p>
                            </div>
                        `;
                    };
                }
            }
        });
    </script>
</body>
</html>