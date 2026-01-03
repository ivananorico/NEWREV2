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
        /* Clean Citizen-Friendly Theme */
        :root {
            --primary-blue: #2563eb;
            --primary-green: #10b981;
            --primary-red: #ef4444;
            --primary-orange: #f97316;
            --primary-gray: #6b7280;
            --light-bg: #f9fafb;
            --card-bg: #ffffff;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Map Title Section */
        .map-title-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary-blue);
        }

        .map-title {
            color: #1f2937;
            font-size: 1.6rem;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .map-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
        }

        /* Map Display Area */
        .map-display-area {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .market-map {
            width: 800px;
            height: 600px;
            background-color: #f8fafc;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            margin: 0 auto;
            position: relative;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        /* Stall Markers - Clean Style */
        .stall-marker {
            position: absolute;
            cursor: pointer;
            user-select: none;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 2px solid white;
            text-align: center;
            transition: all 0.2s ease;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .stall-marker:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            z-index: 100;
        }

        .stall-content {
            padding: 6px;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            pointer-events: none;
        }

        .stall-name {
            font-weight: 600;
            font-size: 11px;
            color: white;
            margin-bottom: 3px;
        }

        .stall-price {
            font-weight: 500;
            font-size: 10px;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 6px;
            border-radius: 10px;
            margin-bottom: 3px;
        }

        /* Status Colors - Softer */
        .status-available {
            background: linear-gradient(135deg, var(--primary-green) 0%, #34d399 100%);
        }

        .status-occupied {
            background: linear-gradient(135deg, var(--primary-red) 0%, #f87171 100%);
        }

        .status-reserved {
            background: linear-gradient(135deg, var(--primary-orange) 0%, #fb923c 100%);
        }

        .status-maintenance {
            background: linear-gradient(135deg, var(--primary-gray) 0%, #9ca3af 100%);
        }

        /* Selected Stall */
        .stall-marker.selected {
            box-shadow: 0 0 0 3px var(--primary-blue), 0 4px 12px rgba(37, 99, 235, 0.2);
            border: 2px solid white;
        }

        /* Side Panel */
        .side-panel {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            padding: 25px;
            border: 1px solid #e5e7eb;
            height: fit-content;
        }

        .panel-title {
            color: #1f2937;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            border: 1px solid #e5e7eb;
        }

        .stat-value {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6b7280;
        }

        /* Legend */
        .legend-container {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 8px;
            background: white;
            border-radius: 6px;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }

        /* Modal Styles */
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
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #1f2937;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6b7280;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .modal-close:hover {
            background: #f3f4f6;
        }

        .modal-body {
            margin-bottom: 25px;
        }

        /* Stall Details in Modal */
        .stall-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .detail-item {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-blue);
        }

        .detail-label {
            font-size: 0.8rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .detail-value {
            font-size: 1.1rem;
            color: #1f2937;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .badge-available {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-occupied {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-reserved {
            background: #ffedd5;
            color: #9a3412;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .btn-success {
            background: var(--primary-green);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-outline {
            background: white;
            color: var(--primary-blue);
            border: 2px solid var(--primary-blue);
        }

        .btn-outline:hover {
            background: #f0f9ff;
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
            
            .stall-details-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                padding: 20px;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Keep Original Navbar -->
    <?php include '../../navbar.php'; ?>
    
    <!-- Modal -->
    <div id="stallModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalStallName">Stall Information</h3>
                <button onclick="closeModal()" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="stall-details-grid" id="modalStallDetails">
                    <!-- Stall details will be populated here by JavaScript -->
                </div>
            </div>
            <div class="flex gap-3">
                <button onclick="closeModal()" class="btn btn-secondary flex-1">
                    <i class="fas fa-times"></i> Close
                </button>
                <button onclick="applyForStall()" id="modalApplyBtn" class="btn btn-success flex-1">
                    <i class="fas fa-file-contract"></i> Apply for this Stall
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Breadcrumb -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
            <div class="flex items-center text-sm text-gray-600">
                <a href="../../dashboard.php" class="text-blue-600 hover:text-blue-800 flex items-center gap-1">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <span class="mx-2">›</span>
                <a href="../market_services.php" class="text-blue-600 hover:text-blue-800">Market Services</a>
                <span class="mx-2">›</span>
                <a href="market_portal_services.php" class="text-blue-600 hover:text-blue-800">Market Maps</a>
                <span class="mx-2">›</span>
                <span class="text-gray-800 font-medium"><?php echo htmlspecialchars($map['name']); ?></span>
            </div>
        </div>

        <!-- Page Title -->
        <div class="map-title-section">
            <h1 class="map-title">
                <i class="fas fa-map-marked-alt text-blue-600"></i>
                <?php echo htmlspecialchars($map['name']); ?> - Stall Selection
            </h1>
            <p class="map-subtitle">
                Click on any <span class="text-green-600 font-medium">green stall</span> to view details and apply for rental
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column - Map -->
            <div class="lg:col-span-2">
                <!-- Instructions -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 mb-6">
                    <h3 class="font-semibold text-blue-800 mb-3 flex items-center gap-2">
                        <i class="fas fa-info-circle"></i> How to Apply for a Stall
                    </h3>
                    <div class="space-y-2 text-blue-700">
                        <p>1. Look for <span class="font-medium">green-colored stalls</span> on the map (these are available)</p>
                        <p>2. Click on any available stall to see its details</p>
                        <p>3. Review the stall information in the popup window</p>
                        <p>4. Click "Apply for this Stall" to start your application</p>
                    </div>
                </div>

                <!-- Map Container -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
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
                                     data-stall-length="<?php echo $stall['length']; ?>"
                                     data-stall-width="<?php echo $stall['width']; ?>"
                                     data-stall-height="<?php echo $stall['height']; ?>"
                                     data-stall-available="<?php echo $is_available ? 'true' : 'false'; ?>"
                                     onclick="showStallModal(this)"
                                     title="Click to view details">
                                    <div class="stall-content">
                                        <div class="stall-name"><?php echo htmlspecialchars($stall['name']); ?></div>
                                        <div class="stall-price">₱<?php echo number_format($price, 2); ?></div>
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

                    <!-- Map Info -->
                    <div class="mt-4 text-center text-sm text-gray-500">
                        <p>Map shows all stalls in <?php echo htmlspecialchars($map['name']); ?>. Click on stalls for details.</p>
                    </div>
                </div>
            </div>

            <!-- Right Column - Side Panel -->
            <div>
                <!-- Statistics -->
                <div class="side-panel mb-6">
                    <h3 class="panel-title">
                        <i class="fas fa-chart-pie text-blue-600"></i>
                        Market Statistics
                    </h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $total_stalls; ?></div>
                            <div class="stat-label">Total Stalls</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" style="color: var(--primary-green);"><?php echo $available_stalls; ?></div>
                            <div class="stat-label">Available Now</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" style="color: var(--primary-red);"><?php echo $occupied_stalls; ?></div>
                            <div class="stat-label">Currently Rented</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" style="color: var(--primary-orange);"><?php echo $reserved_stalls; ?></div>
                            <div class="stat-label">Reserved</div>
                        </div>
                    </div>
                </div>

                <!-- Legend -->
                <div class="side-panel mb-6">
                    <h3 class="panel-title">
                        <i class="fas fa-palette text-blue-600"></i>
                        Color Guide
                    </h3>
                    <div class="legend-container">
                        <div class="legend-item">
                            <div class="legend-color" style="background: var(--primary-green);"></div>
                            <span class="text-sm text-gray-700">Available for rent</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: var(--primary-red);"></div>
                            <span class="text-sm text-gray-700">Already rented</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: var(--primary-orange);"></div>
                            <span class="text-sm text-gray-700">Under reservation</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: var(--primary-gray);"></div>
                            <span class="text-sm text-gray-700">Under maintenance</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="side-panel">
                    <h3 class="panel-title">
                        <i class="fas fa-bolt text-blue-600"></i>
                        Quick Actions
                    </h3>
                    <div class="space-y-3">
                        <a href="market_portal_services.php" class="btn btn-outline w-full">
                            <i class="fas fa-arrow-left"></i>
                            Back to All Maps
                        </a>
                        <button onclick="refreshPage()" class="btn w-full" style="background: #f3f4f6; color: #374151;">
                            <i class="fas fa-redo"></i>
                            Refresh View
                        </button>
                        <button onclick="showHelp()" class="btn w-full" style="background: #fef3c7; color: #92400e;">
                            <i class="fas fa-life-ring"></i>
                            Need Help?
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-500">
            <p>© <?php echo date('Y'); ?> LGU Market Administration. For assistance, contact (02) 1234-5678</p>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        let selectedStall = null;
        const modal = document.getElementById('stallModal');
        const modalStallName = document.getElementById('modalStallName');
        const modalStallDetails = document.getElementById('modalStallDetails');
        const modalApplyBtn = document.getElementById('modalApplyBtn');

        function showStallModal(stallElement) {
            const isAvailable = stallElement.dataset.stallAvailable === 'true';
            const status = stallElement.dataset.stallStatus;
            
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
                status: status,
                length: stallElement.dataset.stallLength,
                width: stallElement.dataset.stallWidth,
                height: stallElement.dataset.stallHeight,
                isAvailable: isAvailable
            };
            
            // Update modal title
            modalStallName.textContent = selectedStall.name;
            
            // Update modal details
            let statusBadge = '';
            if (status === 'available') {
                statusBadge = '<span class="status-badge badge-available">Available</span>';
            } else if (status === 'occupied') {
                statusBadge = '<span class="status-badge badge-occupied">Occupied</span>';
            } else if (status === 'reserved') {
                statusBadge = '<span class="status-badge badge-reserved">Reserved</span>';
            }
            
            modalStallDetails.innerHTML = `
                <div class="detail-item">
                    <div class="detail-label">Stall Name</div>
                    <div class="detail-value">${selectedStall.name}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Monthly Price</div>
                    <div class="detail-value">₱${selectedStall.price}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Stall Class</div>
                    <div class="detail-value">${selectedStall.class}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Section</div>
                    <div class="detail-value">${selectedStall.section}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Dimensions</div>
                    <div class="detail-value">${selectedStall.length}m × ${selectedStall.width}m</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">${statusBadge}</div>
                </div>
            `;
            
            // Enable/disable apply button based on availability
            if (isAvailable) {
                modalApplyBtn.disabled = false;
                modalApplyBtn.innerHTML = '<i class="fas fa-file-contract"></i> Apply for this Stall';
                modalApplyBtn.className = 'btn btn-success flex-1';
            } else {
                modalApplyBtn.disabled = true;
                modalApplyBtn.innerHTML = '<i class="fas fa-ban"></i> Not Available';
                modalApplyBtn.className = 'btn btn-secondary flex-1';
            }
            
            // Show modal
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Remove selection
            document.querySelectorAll('.stall-marker').forEach(marker => {
                marker.classList.remove('selected');
            });
            
            selectedStall = null;
        }

        function applyForStall() {
            if (!selectedStall) {
                showNotification('Please select a stall first.', 'error');
                return;
            }
            
            if (selectedStall.isAvailable) {
                // Redirect to rental application form
                window.location.href = `rental_application_form.php?map_id=<?php echo $map_id; ?>&stall_id=${selectedStall.id}`;
            } else {
                showNotification('This stall is not available for rental.', 'warning');
            }
        }

        function goBack() {
            window.location.href = 'market_portal_services.php';
        }

        function refreshPage() {
            window.location.reload();
        }

        function showHelp() {
            alert('Market Stall Rental Assistance\n\nAvailable Hours: Monday-Friday, 8:00 AM - 5:00 PM\n\nContact Information:\n• Market Office: (02) 1234-5679\n• Treasury Office: (02) 1234-5680\n• IT Support: (02) 1234-5681\n\nEmail: market@lgu.gov.ph');
        }

        function showNotification(message, type) {
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

        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                closeModal();
            }
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
                            <div class="absolute inset-0 flex flex-col items-center justify-center bg-gray-50">
                                <i class="fas fa-exclamation-triangle text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 font-medium">Map image could not be loaded</p>
                            </div>
                        `;
                    };
                }
            }
        });
    </script>
</body>
</html>