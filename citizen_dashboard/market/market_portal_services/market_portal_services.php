<?php
// revenue2/citizen_dashboard/market/market_portal_services/market_portal_services.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Include the database connection from market_db.php
include_once '../../../db/Market/market_db.php';

// Fetch all maps from database
$stmt = $pdo->prepare("SELECT * FROM maps ORDER BY created_at DESC");
$stmt->execute();
$maps = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Market Maps - GoServePH</title>
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

        .map-card {
            border-radius: 12px;
            overflow: hidden;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .map-image {
            height: 200px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .map-card:hover .map-image {
            transform: scale(1.05);
        }

        .availability-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.75rem;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
        }

        .stats-item {
            background: rgba(154, 165, 177, 0.08);
            border-radius: 8px;
            padding: 12px;
            border-left: 3px solid var(--primary);
        }

        .rent-btn {
            background: linear-gradient(135deg, var(--primary), #3a7bd5);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .rent-btn:hover {
            background: linear-gradient(135deg, #3a7bd5, #2a6bc4);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(74, 144, 226, 0.3);
        }

        .process-step {
            padding: 20px;
            border-radius: 10px;
            background: white;
            border: 1px solid rgba(229, 231, 235, 0.8);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }

        .step-number {
            width: 36px;
            height: 36px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 12px;
            box-shadow: 0 4px 10px rgba(74, 144, 226, 0.2);
        }

        .empty-state {
            padding: 60px 20px;
            border-radius: 12px;
            background: white;
            border: 1px solid rgba(229, 231, 235, 0.8);
        }

        .header-breadcrumb {
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .header-breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .header-breadcrumb a:hover {
            text-decoration: underline;
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

        .section-header {
            border-left: 5px solid var(--primary);
            padding-left: 1rem;
            margin-bottom: 1.5rem;
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
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Navbar -->
    <?php include '../../navbar.php'; ?>
    
    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Enhanced Header with box -->
        <div class="header-box mb-12">
            <!-- Breadcrumb -->
            <div class="header-breadcrumb mb-4">
                <a href="../market_services.php" class="inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Market Services
                </a>
            </div>
            
            <!-- Title Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
                <div>
                    <h1 class="text-4xl font-bold text-gray-900 mb-2">Market Maps & Stall Selection</h1>
                    <p class="text-gray-600 text-lg">Browse available market maps and select a stall to rent</p>
                </div>
                <div class="stat-card px-5 py-3">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></div>
                        <span class="font-semibold text-gray-700">
                            <?php echo count($maps); ?> Market Map<?php echo count($maps) !== 1 ? 's' : ''; ?> Available
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Divider -->
            <div class="h-1 w-24 rounded-full mt-6" style="background-color: #4a90e2;"></div>
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
                    
                    // Calculate occupancy rate
                    $occupancy_rate = $stallCount > 0 ? round(($availableCount / $stallCount) * 100) : 0;
                ?>
                <!-- Map Card -->
                <a href="apply_rental.php?map_id=<?php echo $map['id']; ?>" 
                   class="card-hover map-card block">
                    <!-- Map Image -->
                    <div class="relative overflow-hidden">
                        <img src="<?php echo htmlspecialchars($image_path); ?>" 
                             alt="<?php echo htmlspecialchars($map['name']); ?>" 
                             class="w-full map-image"
                             onerror="this.src='https://images.unsplash.com/photo-1589829545856-d10d557cf95f?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'">
                        
                        <!-- Availability Badge -->
                        <div class="absolute top-3 right-3">
                            <div class="availability-badge">
                                <i class="fas fa-check-circle mr-1"></i>
                                <?php echo $availableCount; ?> Available
                            </div>
                        </div>
                        
                        <!-- Occupancy Indicator -->
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-3">
                            <div class="flex items-center justify-between">
                                <span class="text-white text-sm font-medium">Occupancy</span>
                                <div class="w-24 bg-gray-700 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full" 
                                         style="width: <?php echo $occupancy_rate; ?>%"></div>
                                </div>
                                <span class="text-white text-sm font-bold"><?php echo $occupancy_rate; ?>%</span>
                            </div>
                        </div>
                        
                        <!-- Overlay Gradient -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent opacity-0 hover:opacity-100 transition-opacity duration-300"></div>
                    </div>
                    
                    <!-- Map Details -->
                    <div class="p-5">
                        <!-- Map Title -->
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="text-lg font-bold text-gray-800 truncate pr-2">
                                <?php echo htmlspecialchars($map['name']); ?>
                            </h3>
                            <span class="text-xs font-semibold px-2 py-1 rounded flex-shrink-0 ml-2" 
                                  style="background-color: rgba(74, 144, 226, 0.1); color: #4a90e2;">
                                Map #<?php echo $map['id']; ?>
                            </span>
                        </div>
                        
                        <!-- Map Description -->
                        <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                            <i class="fas fa-map-marker-alt mr-2" style="color: #4caf50;"></i>
                            Select from <?php echo $stallCount; ?> stall locations in this market area
                        </p>
                        
                        <!-- Stats -->
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div class="stat-card">
                                <div class="text-xs text-gray-500 mb-1">Total Stalls</div>
                                <div class="font-bold text-gray-800 text-lg"><?php echo $stallCount; ?></div>
                            </div>
                            <div class="stat-card" style="border-left-color: #4caf50;">
                                <div class="text-xs text-gray-500 mb-1" style="color: #4caf50;">Available Now</div>
                                <div class="font-bold text-lg" style="color: #4caf50;"><?php echo $availableCount; ?></div>
                            </div>
                        </div>
                        
                        <!-- Footer Info -->
                        <div class="flex justify-between items-center text-xs text-gray-500 mb-4">
                            <div class="flex items-center">
                                <i class="far fa-calendar mr-1"></i>
                                <?php echo date('M d, Y', strtotime($map['created_at'])); ?>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-clock mr-1"></i>
                                Updated recently
                            </div>
                        </div>
                        
                        <!-- Rent Button -->
                        <div class="rent-btn">
                            <i class="fas fa-store mr-2"></i>
                            Select & Rent Stall
                            <i class="fas fa-arrow-right ml-2"></i>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- No Maps Available -->
            <div class="empty-state text-center">
                <div class="w-20 h-20 mx-auto mb-6 rounded-full flex items-center justify-center" 
                     style="background-color: rgba(154, 165, 177, 0.1);">
                    <i class="fas fa-map text-3xl" style="color: #9aa5b1;"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-700 mb-3">No Market Maps Available</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    Currently there are no market maps uploaded. Please check back later or contact market administration for assistance.
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-3">
                    <a href="../market_services.php" 
                       class="inline-flex items-center justify-center px-5 py-3 rounded-lg font-medium transition-colors"
                       style="background-color: rgba(154, 165, 177, 0.1); color: #64748b;">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Services
                    </a>
                    <a href="javascript:location.reload()" 
                       class="inline-flex items-center justify-center px-5 py-3 rounded-lg font-medium transition-colors"
                       style="background-color: rgba(74, 144, 226, 0.1); color: #4a90e2;">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Refresh Page
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Information Section -->
        <div class="info-card p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-info-circle mr-2" style="color: #4a90e2;"></i>
                How to Rent a Market Stall
            </h3>
            
            <!-- Process Steps -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="process-step card-hover">
                    <div class="step-number">1</div>
                    <h4 class="font-semibold text-gray-800 mb-2">Select a Market Map</h4>
                    <p class="text-gray-600 text-sm">
                        Browse through available market maps and choose the location that suits your business needs.
                    </p>
                </div>
                
                <div class="process-step card-hover">
                    <div class="step-number">2</div>
                    <h4 class="font-semibold text-gray-800 mb-2">Choose Your Stall</h4>
                    <p class="text-gray-600 text-sm">
                        Select your preferred stall from the available options on the chosen map.
                    </p>
                </div>
                
                <div class="process-step card-hover">
                    <div class="step-number">3</div>
                    <h4 class="font-semibold text-gray-800 mb-2">Complete Application</h4>
                    <p class="text-gray-600 text-sm">
                        Submit required documents and complete the online application form.
                    </p>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="pt-6 border-t border-gray-100">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h4 class="font-semibold text-gray-800 mb-2">Need Assistance?</h4>
                        <p class="text-gray-600 text-sm">
                            Our market services team is here to help you with the rental process.
                        </p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="flex items-center">
                            <i class="fas fa-envelope mr-2" style="color: #4a90e2;"></i>
                            <span class="text-sm font-medium">market-support@goserveph.gov.ph</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-phone mr-2" style="color: #4caf50;"></i>
                            <span class="text-sm font-medium">(02) 1234-5680</span>
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
                        <li><a href="../../../citizen_dashboard/citizen_dashboard.php" class="hover:text-[#4a90e2] transition-colors">Dashboard</a></li>
                        <li><a href="../market_services.php" class="hover:text-[#4a90e2] transition-colors">Market Services</a></li>
                        <li><a href="#" class="hover:text-[#4a90e2] transition-colors">My Rentals</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h4 class="font-bold text-gray-800 mb-4 uppercase text-sm tracking-wider">Contact</h4>
                    <ul class="space-y-3 text-gray-600">
                        <li><i class="fas fa-phone mr-2 text-gray-400"></i> (02) 1234-5680</li>
                        <li><i class="fas fa-envelope mr-2 text-gray-400"></i> market@goserveph.gov.ph</li>
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

    <!-- JavaScript for interactive features -->
    <script>
        // Add loading state to cards
        document.addEventListener('DOMContentLoaded', function() {
            const mapCards = document.querySelectorAll('a[href*="apply_rental.php"]');
            mapCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    // Add subtle loading effect
                    const originalHTML = this.innerHTML;
                    const rentBtn = this.querySelector('.rent-btn');
                    
                    if (rentBtn) {
                        rentBtn.innerHTML = `
                            <div class="flex items-center justify-center">
                                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                                Loading...
                            </div>
                        `;
                        rentBtn.style.opacity = '0.9';
                    }
                });
            });

            // Handle broken images with fallback
            document.querySelectorAll('img').forEach(img => {
                img.addEventListener('error', function() {
                    this.src = 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80';
                    this.classList.add('object-cover');
                });
            });

            // Add hover effect to process steps
            const processSteps = document.querySelectorAll('.process-step');
            processSteps.forEach(step => {
                step.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                    this.style.boxShadow = '0 8px 20px rgba(0, 0, 0, 0.08)';
                });
                
                step.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.03)';
                });
            });

            // Add smooth scroll animation
            const smoothScroll = (target) => {
                const element = document.querySelector(target);
                if (element) {
                    window.scrollTo({
                        top: element.offsetTop - 100,
                        behavior: 'smooth'
                    });
                }
            };
        });
    </script>
</body>
</html>