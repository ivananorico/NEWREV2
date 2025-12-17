<?php
// market_portal_services.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

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

// Fetch all maps from database
$stmt = $pdo->prepare("SELECT * FROM maps ORDER BY created_at DESC");
$stmt->execute();
$maps = $stmt->fetchAll(PDO::FETCH_ASSOC);

$user_name = $_SESSION['user_name'] ?? 'Citizen';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Maps - GoServePH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Include Navbar -->
    <?php include '../../navbar.php'; ?>
    
    <!-- Main Content -->
    <main class="container mx-auto px-4 sm:px-6 py-8">
        <!-- Page Header -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                <div class="flex items-center mb-4 md:mb-0">
                    <a href="../market_services.php" class="text-blue-600 hover:text-blue-800 mr-4">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-2">Market Maps & Stall Selection</h1>
                        <p class="text-gray-600 text-sm sm:text-base">Browse available market maps and select a stall to rent</p>
                    </div>
                </div>
                <div class="bg-blue-50 text-blue-700 px-3 sm:px-4 py-2 rounded-lg text-sm sm:text-base">
                    <i class="fas fa-info-circle mr-2"></i>
                    <?php echo count($maps); ?> market map(s) available
                </div>
            </div>
        </div>

        <!-- Market Maps Grid -->
        <?php if (count($maps) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
                <?php foreach ($maps as $map): 
                    // Count stalls for this map
                    $stmt = $pdo->prepare("SELECT COUNT(*) as stall_count FROM stalls WHERE map_id = ?");
                    $stmt->execute([$map['id']]);
                    $stallCount = $stmt->fetch(PDO::FETCH_ASSOC)['stall_count'];
                    
                    // Count available stalls
                    $stmt = $pdo->prepare("SELECT COUNT(*) as available_count FROM stalls WHERE map_id = ? AND status = 'available'");
                    $stmt->execute([$map['id']]);
                    $availableCount = $stmt->fetch(PDO::FETCH_ASSOC)['available_count'];
                    
                    // Fix the image path
                    $image_path = str_replace('uploads/market/maps/', '../../../uploads/market/maps/', $map['file_path']);
                ?>
                <!-- Entire card is clickable -->
                <a href="apply_rental.php?map_id=<?php echo $map['id']; ?>" 
                   class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden block hover:scale-[1.02]">
                    <!-- Map Preview -->
                    <div class="h-48 bg-gray-100 overflow-hidden relative">
                        <img src="<?php echo htmlspecialchars($image_path); ?>" 
                             alt="<?php echo htmlspecialchars($map['name']); ?>" 
                             class="w-full h-full object-cover"
                             onerror="this.src='https://via.placeholder.com/400x300/cccccc/666666?text=Map+Image+Not+Found'">
                        <div class="absolute top-2 right-2 bg-black/70 text-white text-xs px-2 py-1 rounded">
                            <?php echo $availableCount; ?> stalls available
                        </div>
                        <!-- Rent Now Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent flex items-end p-4">
                            <div class="text-white">
                                <div class="flex items-center">
                                    <span class="bg-green-500 text-white text-xs px-2 py-1 rounded mr-2">
                                        <i class="fas fa-store mr-1"></i>
                                        Rent Now
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Map Details -->
                    <div class="p-4 sm:p-6">
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="text-lg sm:text-xl font-bold text-gray-800 truncate">
                                <?php echo htmlspecialchars($map['name']); ?>
                            </h3>
                            <span class="bg-indigo-100 text-indigo-600 text-xs font-semibold px-2 py-1 rounded flex-shrink-0 ml-2">
                                Map #<?php echo $map['id']; ?>
                            </span>
                        </div>
                        
                        <p class="text-gray-600 text-xs sm:text-sm mb-4">
                            <i class="far fa-calendar-alt mr-1"></i>
                            Uploaded: <?php echo date('M d, Y', strtotime($map['created_at'])); ?>
                        </p>
                        
                        <!-- Stats -->
                        <div class="grid grid-cols-2 gap-3 sm:gap-4 mb-6">
                            <div class="bg-gray-50 p-2 sm:p-3 rounded-lg">
                                <div class="text-xs sm:text-sm text-gray-500">Total Stalls</div>
                                <div class="text-base sm:text-lg font-bold text-gray-800"><?php echo $stallCount; ?></div>
                            </div>
                            <div class="bg-green-50 p-2 sm:p-3 rounded-lg">
                                <div class="text-xs sm:text-sm text-green-500">Available</div>
                                <div class="text-base sm:text-lg font-bold text-green-600"><?php echo $availableCount; ?></div>
                            </div>
                        </div>
                        
                        <!-- Action Button -->
                        <div class="mt-4">
                            <div class="w-full inline-flex items-center justify-center px-4 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors duration-200 text-sm sm:text-base">
                                <i class="fas fa-shopping-cart mr-2"></i>
                                Rent a Stall
                                <i class="fas fa-arrow-right ml-2"></i>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- No Maps Available -->
            <div class="bg-white rounded-xl shadow-lg p-8 sm:p-12 text-center">
                <div class="w-20 h-20 sm:w-24 sm:h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-map text-gray-400 text-2xl sm:text-3xl"></i>
                </div>
                <h3 class="text-xl sm:text-2xl font-bold text-gray-700 mb-4">No Market Maps Available</h3>
                <p class="text-gray-600 mb-8 max-w-md mx-auto text-sm sm:text-base">
                    Currently there are no market maps uploaded. Please check back later or contact market administration for assistance.
                </p>
                <div class="flex flex-col sm:flex-row justify-center space-y-3 sm:space-y-0 sm:space-x-4">
                    <a href="../market_services.php" class="inline-flex items-center justify-center px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-colors duration-200 text-sm sm:text-base">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Services
                    </a>
                    <a href="javascript:location.reload()" class="inline-flex items-center justify-center px-6 py-3 bg-indigo-100 text-indigo-600 font-medium rounded-lg hover:bg-indigo-200 transition-colors duration-200 text-sm sm:text-base">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Refresh
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Information Section -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">How to Rent a Market Stall</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="flex items-start">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3 sm:mr-4 flex-shrink-0">
                        <span class="text-blue-600 font-bold text-sm sm:text-base">1</span>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-700 mb-1 text-sm sm:text-base">Select a Map</h4>
                        <p class="text-gray-600 text-xs sm:text-sm">Click on any market map below to start the rental process.</p>
                    </div>
                </div>
                <div class="flex items-start">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3 sm:mr-4 flex-shrink-0">
                        <span class="text-blue-600 font-bold text-sm sm:text-base">2</span>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-700 mb-1 text-sm sm:text-base">Choose Stall</h4>
                        <p class="text-gray-600 text-xs sm:text-sm">Select your preferred stall location from the available options.</p>
                    </div>
                </div>
                <div class="flex items-start">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3 sm:mr-4 flex-shrink-0">
                        <span class="text-blue-600 font-bold text-sm sm:text-base">3</span>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-700 mb-1 text-sm sm:text-base">Apply Online</h4>
                        <p class="text-gray-600 text-xs sm:text-sm">Submit your rental application with required documents.</p>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 sm:mt-8 pt-4 sm:pt-6 border-t border-gray-200">
                <h4 class="font-semibold text-gray-700 mb-2 sm:mb-3 text-sm sm:text-base">Need Assistance?</h4>
                <p class="text-gray-600 text-xs sm:text-sm">
                    Contact our market services team at <span class="text-indigo-600 font-medium">market-support@goserveph.gov.ph</span> 
                    or visit the Market Administration Office.
                </p>
            </div>
        </div>
    </main>

    <!-- JavaScript for interactive features -->
    <script>
        // Add loading state to cards
        document.addEventListener('DOMContentLoaded', function() {
            const mapCards = document.querySelectorAll('a[href*="apply_rental.php"]');
            mapCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    // Add loading effect
                    const originalHTML = this.innerHTML;
                    
                    // Create loading overlay
                    const loadingOverlay = document.createElement('div');
                    loadingOverlay.className = 'absolute inset-0 bg-indigo-600/90 flex items-center justify-center rounded-xl z-10';
                    loadingOverlay.innerHTML = `
                        <div class="text-center text-white">
                            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-white mx-auto mb-3"></div>
                            <p class="font-medium">Loading rental application...</p>
                        </div>
                    `;
                    
                    this.style.position = 'relative';
                    this.appendChild(loadingOverlay);
                    
                    // Disable pointer events on children
                    const children = this.children;
                    for (let child of children) {
                        if (child !== loadingOverlay) {
                            child.style.pointerEvents = 'none';
                            child.style.opacity = '0.7';
                        }
                    }
                });
            });

            // Handle broken images
            document.querySelectorAll('img').forEach(img => {
                img.addEventListener('error', function() {
                    this.src = 'https://via.placeholder.com/400x300/cccccc/666666?text=Map+Image+Not+Found';
                    this.classList.add('object-contain');
                });
            });
        });
    </script>
</body>
</html>