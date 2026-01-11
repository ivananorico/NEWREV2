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
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f8fafc;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Map Container */
        .market-map {
            width: 800px;
            height: 600px;
            background-color: #f1f5f9;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            margin: 0 auto;
            position: relative;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Stall Markers */
        .stall-marker {
            position: absolute;
            cursor: pointer;
            border-radius: 4px;
            border: 2px solid white;
            text-align: center;
            transition: all 0.2s;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-weight: 600;
        }

        .stall-marker:hover {
            transform: scale(1.05);
            box-shadow: 0 0 0 3px #3b82f6;
            z-index: 100;
        }

        .stall-content {
            padding: 4px;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stall-name {
            font-size: 11px;
            color: white;
            margin-bottom: 2px;
        }

        .stall-price {
            font-size: 9px;
            background: rgba(255, 255, 255, 0.3);
            padding: 1px 4px;
            border-radius: 8px;
        }

        /* Status Colors */
        .status-available {
            background: #10b981;
        }

        .status-occupied {
            background: #ef4444;
        }

        .status-reserved {
            background: #f97316;
        }

        .status-maintenance {
            background: #8b5cf6;
        }

        .stall-marker.selected {
            box-shadow: 0 0 0 3px #3b82f6;
            border: 2px solid white;
        }

        /* Panel */
        .simple-panel {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .panel-title {
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }

        /* Legend */
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            padding: 8px;
            background: #f8fafc;
            border-radius: 6px;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
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
            border-radius: 8px;
            padding: 20px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1e293b;
        }

        /* Stats */
        .simple-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: #f1f5f9;
            border-radius: 6px;
            padding: 12px;
            text-align: center;
        }

        .stat-number {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Buttons */
        .simple-btn {
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .simple-btn-primary {
            background: #3b82f6;
            color: white;
        }

        .simple-btn-primary:hover {
            background: #2563eb;
        }

        .simple-btn-success {
            background: #10b981;
            color: white;
        }

        .simple-btn-success:hover {
            background: #059669;
        }

        .simple-btn-secondary {
            background: #64748b;
            color: white;
        }

        .simple-btn-secondary:hover {
            background: #475569;
        }

        .simple-btn-outline {
            background: white;
            color: #3b82f6;
            border: 2px solid #3b82f6;
        }

        .simple-btn-outline:hover {
            background: #eff6ff;
        }

        /* Status Badges */
        .status-tag {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .tag-available { background: #d1fae5; color: #065f46; }
        .tag-occupied { background: #fee2e2; color: #991b1b; }
        .tag-reserved { background: #ffedd5; color: #9a3412; }
        .tag-maintenance { background: #f3e8ff; color: #5b21b6; }

        /* Two Column Layout for Header */
        .header-two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .header-box {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }

        .header-box-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Dimension Display */
        .dimension-display {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 5px;
        }

        .dimension-item {
            background: #f1f5f9;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 70px;
        }

        .dimension-label {
            font-size: 0.7rem;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .dimension-value {
            font-weight: 600;
            color: #1e293b;
        }

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
            
            .simple-stats {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                padding: 15px;
            }
            
            .header-two-column {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .dimension-display {
                flex-direction: column;
                gap: 8px;
            }
            
            .dimension-item {
                width: 100%;
                flex-direction: row;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navbar -->
    <?php include '../../navbar.php'; ?>
    
    <!-- Modal -->
    <div id="stallModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalStallName">Stall Details</h3>
                <button onclick="closeModal()" class="bg-gray-100 hover:bg-gray-200 rounded-full w-8 h-8 flex items-center justify-center">
                    <i class="fas fa-times text-gray-600"></i>
                </button>
            </div>
            <div id="modalStallDetails">
                <!-- Stall details will be populated here -->
            </div>
            <div class="flex gap-2 mt-4">
                <button onclick="closeModal()" class="simple-btn simple-btn-secondary flex-1">
                    <i class="fas fa-times"></i> Close
                </button>
                <button onclick="applyForStall()" id="modalApplyBtn" class="simple-btn simple-btn-success flex-1">
                    <i class="fas fa-file-contract"></i> Apply Now
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Simple Breadcrumb -->
        <div class="flex items-center text-sm text-gray-600 mb-6">
            <a href="../../dashboard.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <span class="mx-2">›</span>
            <a href="market_portal_services.php" class="text-blue-600 hover:text-blue-800">Market Maps</a>
            <span class="mx-2">›</span>
            <span class="font-medium"><?php echo htmlspecialchars($map['name']); ?></span>
        </div>

        <!-- Two Column Header -->
        <div class="header-two-column">
            <!-- Market Section -->
            <div class="header-box">
                <h2 class="header-box-title">
                    <i class="fas fa-store text-blue-600"></i>
                    <?php echo htmlspecialchars($map['name']); ?> Market
                </h2>
                <div class="text-gray-600 text-sm space-y-2">
                    <p><span class="font-medium text-green-600">✓ Available stalls:</span> <?php echo $available_stalls; ?> out of <?php echo $total_stalls; ?></p>
                    <p><span class="font-medium text-blue-600">ℹ️ Price range:</span> ₱<?php echo number_format($min_price, 2); ?> - ₱<?php echo number_format($max_price, 2); ?>/month</p>
                    <p><span class="font-medium text-gray-600">📍 Typical stall size:</span> 20.00m × 20.00m × 20.00m</p>
                </div>
            </div>

            <!-- How to Rent -->
            <div class="header-box">
                <h2 class="header-box-title">
                    <i class="fas fa-clipboard-list text-green-600"></i>
                    How to Rent a Stall
                </h2>
                <div class="space-y-3">
                    <div class="flex items-start gap-2">
                        <div class="bg-blue-100 text-blue-800 rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold">1</div>
                        <span class="text-sm text-gray-600">Click on a green stall</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <div class="bg-blue-100 text-blue-800 rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold">2</div>
                        <span class="text-sm text-gray-600">Check the stall details</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <div class="bg-blue-100 text-blue-800 rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold">3</div>
                        <span class="text-sm text-gray-600">Click "Apply Now" to start</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Map Section -->
            <div class="lg:col-span-2">
                <!-- Map Container -->
                <div class="simple-panel">
                    <h3 class="panel-title">
                        <i class="fas fa-map"></i> Market Layout
                    </h3>
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
                                 title="<?php echo htmlspecialchars($stall['name']); ?> - Click for details">
                                <div class="stall-content">
                                    <div class="stall-name"><?php echo htmlspecialchars($stall['name']); ?></div>
                                    <div class="stall-price">₱<?php echo number_format($price, 2); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12 bg-gray-100 rounded-lg">
                            <i class="fas fa-exclamation-triangle text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500">Map image not available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Side Panel -->
            <div>
                <!-- Statistics -->
                <div class="simple-panel mb-6">
                    <h3 class="panel-title">
                        <i class="fas fa-chart-bar"></i> Stall Summary
                    </h3>
                    <div class="simple-stats">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $total_stalls; ?></div>
                            <div class="stat-label">Total Stalls</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number" style="color: #10b981;"><?php echo $available_stalls; ?></div>
                            <div class="stat-label">Available</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number" style="color: #ef4444;"><?php echo $occupied_stalls; ?></div>
                            <div class="stat-label">Occupied</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number" style="color: #8b5cf6;"><?php echo $maintenance_stalls; ?></div>
                            <div class="stat-label">Maintenance</div>
                        </div>
                    </div>
                </div>

                <!-- Important Notes -->
                <div class="simple-panel mb-6">
                    <h3 class="panel-title">
                        <i class="fas fa-info-circle"></i> Important Notes
                    </h3>
                    <div class="space-y-3">
                        <div class="flex items-start gap-2">
                            <div class="mt-1">
                                <div class="w-4 h-4 rounded bg-green-500"></div>
                            </div>
                            <div>
                                <p class="font-medium text-sm">Available (Green)</p>
                                <p class="text-xs text-gray-500">Ready for rent</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <div class="mt-1">
                                <div class="w-4 h-4 rounded bg-red-500"></div>
                            </div>
                            <div>
                                <p class="font-medium text-sm">Occupied (Red)</p>
                                <p class="text-xs text-gray-500">Already rented</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <div class="mt-1">
                                <div class="w-4 h-4 rounded bg-orange-500"></div>
                            </div>
                            <div>
                                <p class="font-medium text-sm">Reserved (Orange)</p>
                                <p class="text-xs text-gray-500">Temporarily held</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <div class="mt-1">
                                <div class="w-4 h-4 rounded bg-purple-500"></div>
                            </div>
                            <div>
                                <p class="font-medium text-sm">Maintenance (Purple)</p>
                                <p class="text-xs text-gray-500">Being repaired</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="simple-panel">
                    <h3 class="panel-title">
                        <i class="fas fa-cog"></i> Quick Actions
                    </h3>
                    <div class="space-y-3">
                        <a href="market_portal_services.php" class="simple-btn simple-btn-outline w-full">
                            <i class="fas fa-arrow-left"></i> Back to Maps
                        </a>
                        <button onclick="refreshPage()" class="simple-btn w-full" style="background: #f1f5f9; color: #475569;">
                            <i class="fas fa-sync-alt"></i> Refresh Map
                        </button>
                        <button onclick="showHelp()" class="simple-btn w-full" style="background: #fef3c7; color: #92400e;">
                            <i class="fas fa-question-circle"></i> Need Help?
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Simple Footer -->
        <div class="mt-8 pt-4 border-t border-gray-200 text-center text-sm text-gray-500">
            <p>Need assistance? Contact Market Office: (02) 1234-5678 | Email: market@lgu.gov.ph</p>
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
            
            // Update modal details
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
            
            // Calculate floor area
            const floorArea = (parseFloat(selectedStall.length) * parseFloat(selectedStall.width)).toFixed(2);
            const volume = (parseFloat(selectedStall.length) * parseFloat(selectedStall.width) * parseFloat(selectedStall.height)).toFixed(2);
            
            document.getElementById('modalStallDetails').innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-xs text-gray-500">Stall Name</div>
                            <div class="font-semibold text-lg">${selectedStall.name}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Status</div>
                            <div class="mt-1">${statusTag}</div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="text-xs text-gray-500 mb-2">Monthly Rental</div>
                        <div class="font-bold text-blue-600 text-xl">₱${selectedStall.price}</div>
                        <div class="text-xs text-gray-500 mt-1">Stall Class: ${selectedStall.class}</div>
                    </div>
                    
                    <div>
                        <div class="text-xs text-gray-500 mb-2">Stall Dimensions</div>
                        <div class="dimension-display">
                            <div class="dimension-item">
                                <div class="dimension-label">Length</div>
                                <div class="dimension-value">${selectedStall.length}m</div>
                            </div>
                            <div class="dimension-item">
                                <div class="dimension-label">Width</div>
                                <div class="dimension-value">${selectedStall.width}m</div>
                            </div>
                            <div class="dimension-item">
                                <div class="dimension-label">Height</div>
                                <div class="dimension-value">${selectedStall.height}m</div>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 mt-3">
                            <div class="flex justify-between">
                                <span>Floor Area:</span>
                                <span class="font-medium">${floorArea} m²</span>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span>Total Volume:</span>
                                <span class="font-medium">${volume} m³</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Update apply button
            const applyBtn = document.getElementById('modalApplyBtn');
            if (isAvailable && status === 'available') {
                applyBtn.disabled = false;
                applyBtn.innerHTML = '<i class="fas fa-file-contract"></i> Apply Now';
                applyBtn.className = 'simple-btn simple-btn-success flex-1';
            } else {
                applyBtn.disabled = true;
                let reason = status === 'occupied' ? 'Already Rented' :
                            status === 'reserved' ? 'Reserved' :
                            status === 'maintenance' ? 'Under Maintenance' : 'Not Available';
                applyBtn.innerHTML = `<i class="fas fa-ban"></i> ${reason}`;
                applyBtn.className = 'simple-btn simple-btn-secondary flex-1';
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
            if (!selectedStall) {
                alert('Please select a stall first.');
                return;
            }
            
            if (selectedStall.isAvailable && selectedStall.status === 'available') {
                window.location.href = `rental_application_form.php?map_id=<?php echo $map_id; ?>&stall_id=${selectedStall.id}`;
            } else {
                let message = 'This stall is not available for rental.';
                if (selectedStall.status === 'occupied') message = 'This stall is already rented.';
                else if (selectedStall.status === 'reserved') message = 'This stall is reserved.';
                else if (selectedStall.status === 'maintenance') message = 'This stall is under maintenance.';
                alert(message);
            }
        }

        function refreshPage() {
            window.location.reload();
        }

        function showHelp() {
            alert('For assistance:\n\nMarket Office: (02) 1234-5679\nHours: Mon-Fri, 8AM-5PM\nEmail: market@lgu.gov.ph\n\nStall dimensions show length × width × height in meters.');
        }

        // Close modal when clicking outside or pressing Escape
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') closeModal();
        });

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
                            <div class="absolute inset-0 flex flex-col items-center justify-center bg-gray-100">
                                <i class="fas fa-exclamation-triangle text-3xl text-gray-400 mb-2"></i>
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