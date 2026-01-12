<?php
// revenue2/citizen_dashboard/market/market_portal_services/apply_rental.php
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
        :root {
            --primary: #4a90e2;
            --secondary: #9aa5b1;
            --accent: #4caf50;
            --background: #fbfbfb;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--background);
            color: #333;
        }
        
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Simple back button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            transition: all 0.2s;
        }
        
        .back-btn:hover {
            border-color: var(--primary);
            background: #f8f9fa;
        }
        
        /* Map Container - Original size preserved */
        .market-map {
            width: 800px;
            height: 600px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin: 0 auto;
            position: relative;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            overflow: hidden;
        }
        
        /* Stall Markers - Simple */
        .stall-marker {
            position: absolute;
            cursor: pointer;
            border-radius: 2px;
            border: 1px solid white;
            text-align: center;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-weight: 500;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .stall-marker:hover {
            border-color: var(--primary);
            z-index: 10;
        }
        
        .stall-content {
            padding: 2px;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .stall-name {
            font-size: 10px;
            color: white;
            margin-bottom: 1px;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.3);
        }
        
        .stall-price {
            font-size: 8px;
            background: rgba(255, 255, 255, 0.3);
            padding: 1px 3px;
            border-radius: 2px;
            backdrop-filter: blur(2px);
        }
        
        /* Status Colors - FIXED to match legend */
        .status-available {
            background: #4caf50; /* Green for Available */
        }
        
        .status-occupied {
            background: #f44336; /* Red for Occupied */
        }
        
        .status-reserved {
            background: #ff9800; /* Orange for Reserved */
        }
        
        .status-maintenance {
            background: #9c27b0; /* Purple for Maintenance */
        }
        
        .stall-marker.selected {
            border: 2px solid var(--primary);
            box-shadow: 0 0 0 1px var(--primary);
        }
        
        /* Simple Panel */
        .simple-panel {
            background: white;
            border-radius: 4px;
            padding: 16px;
            border: 1px solid #e0e0e0;
            margin-bottom: 16px;
        }
        
        .panel-title {
            color: #2c3e50;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 8px;
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
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 4px;
            padding: 20px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .modal-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }
        
        .stat-box {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 12px;
            text-align: center;
            border: 1px solid #e0e0e0;
        }
        
        .stat-number {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--secondary);
            text-transform: uppercase;
        }
        
        /* Buttons */
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
            border: 1px solid transparent;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background: #357ae8;
            border-color: #357ae8;
        }
        
        .btn-success {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        
        .btn-success:hover {
            background: #43a047;
            border-color: #43a047;
        }
        
        .btn-secondary {
            background: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }
        
        .btn-secondary:hover {
            background: #8a949f;
            border-color: #8a949f;
        }
        
        .btn-outline {
            background: white;
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-outline:hover {
            background: #f8f9fa;
        }
        
        /* Status Tags - FIXED to match colors */
        .status-tag {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .tag-available { background: #e8f5e9; color: #2e7d32; }
        .tag-occupied { background: #ffebee; color: #c62828; }
        .tag-reserved { background: #fff3e0; color: #ef6c00; }
        .tag-maintenance { background: #f3e5f5; color: #7b1fa2; }
        
        /* Info Cards */
        .info-card {
            background: white;
            border-radius: 4px;
            padding: 16px;
            border: 1px solid #e0e0e0;
            margin-bottom: 16px;
        }
        
        .info-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        /* Steps */
        .steps-container {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 16px;
            border: 1px solid #e0e0e0;
            margin-bottom: 16px;
        }
        
        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        .step-number {
            background: var(--primary);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 500;
            flex-shrink: 0;
        }
        
        .step-text {
            font-size: 0.9rem;
            color: #555;
            line-height: 1.4;
        }
        
        /* Contact Info */
        .contact-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .contact-item:last-child {
            border-bottom: none;
        }
        
        .contact-icon {
            color: var(--primary);
            width: 16px;
        }
        
        /* Legend Items - FIXED to match actual colors */
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 0;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        /* Color classes for legend */
        .legend-available {
            background: #4caf50; /* Same as .status-available */
        }
        
        .legend-occupied {
            background: #f44336; /* Same as .status-occupied */
        }
        
        .legend-reserved {
            background: #ff9800; /* Same as .status-reserved */
        }
        
        .legend-maintenance {
            background: #9c27b0; /* Same as .status-maintenance */
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
            .main-container {
                padding: 16px;
            }
            
            .market-map {
                height: 400px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .market-map {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include '../../navbar.php'; ?>
    
    <!-- Modal -->
    <div id="stallModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalStallName">Stall Details</h3>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="modalStallDetails"></div>
            <div class="flex gap-2 mt-4">
                <button onclick="closeModal()" class="btn btn-secondary flex-1">
                    <i class="fas fa-times"></i> Close
                </button>
                <button onclick="applyForStall()" id="modalApplyBtn" class="btn btn-success flex-1">
                    <i class="fas fa-file-alt"></i> Apply
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Back Button -->
        <a href="market_portal_services.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Maps
        </a>

        <!-- Market Info -->
        <div class="info-card">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($map['name']); ?> Market</h1>
                    <p class="text-gray-600 text-sm">Welcome, <?php echo htmlspecialchars($user_name); ?></p>
                </div>
                <div class="flex flex-col items-end">
                    <div class="text-gray-600 text-sm">Price Range</div>
                    <div class="font-semibold text-primary">₱<?php echo number_format($min_price, 2); ?> - ₱<?php echo number_format($max_price, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="steps-container">
            <div class="info-title">How to Rent a Stall</div>
            <div class="step-item">
                <div class="step-number">1</div>
                <div class="step-text">Click on an available stall (green color) on the map</div>
            </div>
            <div class="step-item">
                <div class="step-number">2</div>
                <div class="step-text">Review stall details in the popup window</div>
            </div>
            <div class="step-item">
                <div class="step-number">3</div>
                <div class="step-text">Click "Apply" to start the rental application</div>
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
                        <span class="text-sm text-gray-500">Available: <?php echo $available_stalls; ?> of <?php echo $total_stalls; ?></span>
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
                                
                                // Debug: Check stall status
                                // echo "<!-- Stall {$stall['name']}: Status = $status, Color = status-$status -->";
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
                        <div class="text-center py-12 border border-gray-300 rounded">
                            <i class="fas fa-exclamation-circle text-3xl text-gray-400 mb-3"></i>
                            <p class="text-gray-500">Map image not available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
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
                            <div class="stat-number" style="color: #9c27b0;"><?php echo $maintenance_stalls; ?></div>
                            <div class="stat-label">Maintenance</div>
                        </div>
                    </div>
                </div>

                <!-- Color Legend - FIXED to match actual stall colors -->
                <div class="simple-panel">
                    <h3 class="panel-title">
                        <i class="fas fa-info-circle"></i> Status Legend
                    </h3>
                    <div class="space-y-1">
                        <div class="legend-item">
                            <div class="legend-color legend-available"></div>
                            <div>
                                <span class="text-sm font-medium">Available</span>
                                <div class="text-xs text-gray-500">Ready for rent</div>
                            </div>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color legend-occupied"></div>
                            <div>
                                <span class="text-sm font-medium">Occupied</span>
                                <div class="text-xs text-gray-500">Already rented</div>
                            </div>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color legend-reserved"></div>
                            <div>
                                <span class="text-sm font-medium">Reserved</span>
                                <div class="text-xs text-gray-500">Temporarily held</div>
                            </div>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color legend-maintenance"></div>
                            <div>
                                <span class="text-sm font-medium">Maintenance</span>
                                <div class="text-xs text-gray-500">Under repair</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="simple-panel">
                    <h3 class="panel-title">
                        <i class="fas fa-phone"></i> Contact Information
                    </h3>
                    <div class="space-y-2">
                        <div class="contact-item">
                            <i class="fas fa-phone-alt contact-icon"></i>
                            <div>
                                <div class="text-sm font-medium">Market Office</div>
                                <div class="text-sm text-gray-600">(02) 1234-5678</div>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope contact-icon"></i>
                            <div>
                                <div class="text-sm font-medium">Email</div>
                                <div class="text-sm text-gray-600">market@lgu.gov.ph</div>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-clock contact-icon"></i>
                            <div>
                                <div class="text-sm font-medium">Office Hours</div>
                                <div class="text-sm text-gray-600">Mon-Fri: 8AM-5PM</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 pt-4 border-t border-gray-300 text-center text-sm text-gray-500">
            <p>Municipal Market Management System • For assistance, contact Market Office</p>
        </div>
    </div>

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
            
            // Status tag - matching the CSS classes
            let statusTag = '';
            if (status === 'available') {
                statusTag = '<span class="status-tag tag-available">Available</span>';
            } else if (status === 'occupied') {
                statusTag = '<span class="status-tag tag-occupied">Occupied</span>';
            } else if (status === 'reserved') {
                statusTag = '<span class="status-tag tag-reserved">Reserved</span>';
            } else if (status === 'maintenance') {
                statusTag = '<span class="status-tag tag-maintenance">Maintenance</span>';
            }
            
            // Calculate dimensions
            const floorArea = (parseFloat(selectedStall.length) * parseFloat(selectedStall.width)).toFixed(2);
            const volume = (parseFloat(selectedStall.length) * parseFloat(selectedStall.width) * parseFloat(selectedStall.height)).toFixed(2);
            
            document.getElementById('modalStallDetails').innerHTML = `
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">Stall Class</div>
                        <div class="font-medium">${selectedStall.class}</div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">Status</div>
                        <div>${statusTag}</div>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-3">
                        <div class="text-sm text-gray-600 mb-2">Monthly Rental</div>
                        <div class="text-xl font-semibold text-primary">₱${selectedStall.price}</div>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-3">
                        <div class="text-sm text-gray-600 mb-2">Dimensions</div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="text-center p-2 border border-gray-200 rounded">
                                <div class="text-xs text-gray-500">Length</div>
                                <div class="font-medium">${selectedStall.length}m</div>
                            </div>
                            <div class="text-center p-2 border border-gray-200 rounded">
                                <div class="text-xs text-gray-500">Width</div>
                                <div class="font-medium">${selectedStall.width}m</div>
                            </div>
                            <div class="text-center p-2 border border-gray-200 rounded">
                                <div class="text-xs text-gray-500">Height</div>
                                <div class="font-medium">${selectedStall.height}m</div>
                            </div>
                        </div>
                        <div class="mt-2 text-sm text-gray-600">
                            Floor Area: ${floorArea} m² • Volume: ${volume} m³
                        </div>
                    </div>
                    
                    ${!isAvailable ? `
                    <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 text-sm p-3 rounded">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        This stall is not available for rental (Status: ${selectedStall.status})
                    </div>
                    ` : ''}
                </div>
            `;
            
            // Update apply button
            const applyBtn = document.getElementById('modalApplyBtn');
            if (isAvailable && status === 'available') {
                applyBtn.disabled = false;
                applyBtn.innerHTML = '<i class="fas fa-file-alt"></i> Apply for Rental';
                applyBtn.className = 'btn btn-success flex-1';
            } else {
                applyBtn.disabled = true;
                let reason = status === 'occupied' ? 'Already Rented' :
                            status === 'reserved' ? 'Reserved' :
                            status === 'maintenance' ? 'Under Maintenance' : 'Not Available';
                applyBtn.innerHTML = `<i class="fas fa-ban"></i> ${reason}`;
                applyBtn.className = 'btn btn-secondary flex-1';
            }
            
            // Show modal
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
                            <div class="absolute inset-0 flex flex-col items-center justify-center bg-gray-100">
                                <i class="fas fa-exclamation-triangle text-2xl text-gray-400 mb-2"></i>
                                <p class="text-gray-500">Map could not be loaded</p>
                            </div>
                        `;
                    };
                }
            }
        });
    </script>
</body>
</html>