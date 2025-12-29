<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Include RPT database configuration
require_once '../../../db/RPT/rpt_db.php';

class RPTDashboard {
    private $rpt_pdo;
    
    public function __construct() {
        $this->connectToRPTDB();
    }
    
    private function connectToRPTDB() {
        try {
            $this->rpt_pdo = getDatabaseConnection();
            
            if (is_array($this->rpt_pdo) && isset($this->rpt_pdo['error'])) {
                $this->rpt_pdo = null;
                error_log("RPT DB Connection failed: " . $this->rpt_pdo['message']);
                throw new Exception("Database connection failed");
            }
        } catch (Exception $e) {
            $this->rpt_pdo = null;
            error_log("RPT DB Connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function handleRequest() {
        $action = $_GET['action'] ?? 'dashboard';
        $year = isset($_GET['year']) ? intval($_GET['year']) : null;
        
        switch ($action) {
            case 'get_years':
                $this->getAvailableYears();
                break;
            case 'dashboard':
            default:
                $this->getDashboardStats($year);
                break;
        }
    }
    
    private function getAvailableYears() {
        try {
            if (!$this->rpt_pdo) {
                throw new Exception("RPT database connection failed");
            }
            
            // Get distinct years from quarterly_taxes table
            $stmt = $this->rpt_pdo->query("SELECT DISTINCT year FROM quarterly_taxes ORDER BY year DESC");
            $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Also check property_registrations for additional years
            $stmt = $this->rpt_pdo->query("SELECT DISTINCT YEAR(created_at) as year FROM property_registrations WHERE created_at IS NOT NULL");
            $regYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Merge and get unique years
            $allYears = array_unique(array_merge($years, $regYears));
            
            // Filter out null/empty values and convert to integers
            $allYears = array_filter($allYears, function($year) {
                return $year !== null && $year !== '' && $year > 1900;
            });
            
            // Convert to integers and sort descending
            $allYears = array_map('intval', $allYears);
            rsort($allYears);
            
            // If no years found, add current year
            if (empty($allYears)) {
                $allYears = [date('Y')];
            }
            
            echo json_encode([
                'success' => true,
                'years' => $allYears
            ]);
            
        } catch (Exception $e) {
            error_log("Get Years Error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Failed to get available years',
                'message' => $e->getMessage(),
                'years' => [date('Y')]
            ]);
        }
    }
    
    private function getAvailableYearsForDashboard() {
        try {
            if (!$this->rpt_pdo) {
                return [date('Y')];
            }
            
            // Get distinct years from quarterly_taxes table
            $stmt = $this->rpt_pdo->query("SELECT DISTINCT year FROM quarterly_taxes ORDER BY year DESC");
            $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Also check property_registrations for additional years
            $stmt = $this->rpt_pdo->query("SELECT DISTINCT YEAR(created_at) as year FROM property_registrations WHERE created_at IS NOT NULL");
            $regYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Merge and get unique years
            $allYears = array_unique(array_merge($years, $regYears));
            
            // Filter out null/empty values and convert to integers
            $allYears = array_filter($allYears, function($year) {
                return $year !== null && $year !== '' && $year > 1900;
            });
            
            // Convert to integers and sort descending
            $allYears = array_map('intval', $allYears);
            rsort($allYears);
            
            // If no years found, add current year
            if (empty($allYears)) {
                $allYears = [date('Y')];
            }
            
            return $allYears;
            
        } catch (Exception $e) {
            error_log("Get Available Years Error: " . $e->getMessage());
            return [date('Y')];
        }
    }
    
    public function getDashboardStats($year) {
        try {
            if (!$this->rpt_pdo) {
                throw new Exception("RPT database connection failed");
            }
        
            // Get available years
            $availableYears = $this->getAvailableYearsForDashboard();
            
            // If year is null or not available, use the latest year
            if ($year === null || !in_array($year, $availableYears)) {
                if (!empty($availableYears)) {
                    $year = max($availableYears); // Use latest available year
                } else {
                    $year = date('Y'); // Fallback to current year
                }
            }
            
            // Validate year
            if ($year < 1900 || $year > 2100) {
                $year = !empty($availableYears) ? max($availableYears) : date('Y');
            }
            
            $response = [
                'success' => true,
                'selected_year' => $year,
                'available_years' => $availableYears,
                'property_stats' => $this->getPropertyStats(),
                'tax_stats' => $this->getTaxStats($year),
                'collection_performance' => $this->getCollectionPerformance($year),
                'property_distribution' => $this->getPropertyDistribution(),
                'quarterly_analysis' => $this->getQuarterlyAnalysis($year),
                'top_barangays' => $this->getTopBarangays($year),
                'payment_analysis' => $this->getPaymentAnalysis($year),
                'recent_activities' => $this->getRecentActivities($year),
                'current_quarter' => $this->getCurrentQuarter(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            echo json_encode($response);
            
        } catch (Exception $e) {
            error_log("Dashboard Error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Failed to load dashboard data',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    private function getPropertyStats() {
        try {
            // Get total property stats with value aggregation
            $stmt = $this->rpt_pdo->query("SELECT 
                (SELECT COUNT(*) FROM property_owners) as total_owners,
                (SELECT COUNT(*) FROM property_owners WHERE status = 'active') as active_owners,
                (SELECT COUNT(*) FROM property_owners WHERE status = 'pending') as pending_owners,
                (SELECT COUNT(*) FROM property_registrations) as total_registrations,
                (SELECT COUNT(*) FROM property_registrations WHERE status = 'approved') as approved_properties,
                (SELECT COUNT(*) FROM property_registrations WHERE status = 'pending') as pending_properties,
                (SELECT COUNT(*) FROM land_properties WHERE status = 'active') as active_land_properties,
                (SELECT COUNT(*) FROM building_properties WHERE status = 'active') as active_buildings,
                (SELECT COUNT(DISTINCT pr.barangay) FROM property_registrations pr WHERE pr.barangay IS NOT NULL) as barangays_covered");
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            
        } catch (Exception $e) {
            error_log("Property Stats Error: " . $e->getMessage());
            return [];
        }
    }
    
    private function getTaxStats($year) {
        try {
            $currentQuarter = $this->getCurrentQuarter();
            
            // Total tax assessment values (all years)
            $stmt = $this->rpt_pdo->query("SELECT 
                COALESCE(SUM(pt.total_annual_tax), 0) as total_annual_tax,
                COALESCE(SUM(pt.total_annual_tax) / 4, 0) as quarterly_target,
                COUNT(pt.id) as total_assessments,
                COALESCE(SUM(CASE 
                    WHEN lp.property_type = 'Residential' THEN pt.total_annual_tax 
                    ELSE 0 
                END), 0) as residential_tax,
                COALESCE(SUM(CASE 
                    WHEN lp.property_type = 'Commercial' THEN pt.total_annual_tax 
                    ELSE 0 
                END), 0) as commercial_tax,
                COALESCE(SUM(CASE 
                    WHEN lp.property_type = 'Industrial' THEN pt.total_annual_tax 
                    ELSE 0 
                END), 0) as industrial_tax,
                COALESCE(SUM(CASE 
                    WHEN lp.property_type = 'Agricultural' THEN pt.total_annual_tax 
                    ELSE 0 
                END), 0) as agricultural_tax
                FROM property_totals pt
                LEFT JOIN land_properties lp ON pt.land_id = lp.id
                WHERE pt.status = 'active'");
            
            $annual = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get quarterly breakdown for selected year
            $stmt = $this->rpt_pdo->prepare("SELECT 
                quarter,
                SUM(total_quarterly_tax) as total_due,
                SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END) as total_paid,
                SUM(CASE WHEN payment_status = 'overdue' THEN total_quarterly_tax ELSE 0 END) as total_overdue,
                SUM(CASE WHEN payment_status = 'pending' THEN total_quarterly_tax ELSE 0 END) as total_pending,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count
                FROM quarterly_taxes 
                WHERE year = ?
                GROUP BY quarter
                ORDER BY 
                    CASE quarter
                        WHEN 'Q1' THEN 1
                        WHEN 'Q2' THEN 2
                        WHEN 'Q3' THEN 3
                        WHEN 'Q4' THEN 4
                    END");
            
            $stmt->execute([$year]);
            $quarterly = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Current quarter performance for selected year
            $stmt = $this->rpt_pdo->prepare("SELECT 
                COALESCE(SUM(total_quarterly_tax), 0) as current_quarter_due,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END), 0) as current_quarter_paid,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' AND discount_applied = 1 THEN discount_amount ELSE 0 END), 0) as discounts_given,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' AND penalty_amount > 0 THEN penalty_amount ELSE 0 END), 0) as penalties_collected,
                COUNT(*) as total_bills,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_bills
                FROM quarterly_taxes 
                WHERE year = ? AND quarter = ?");
            
            $stmt->execute([$year, $currentQuarter]);
            $current_quarter = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Outstanding balance summary for selected year
            $stmt = $this->rpt_pdo->prepare("SELECT 
                COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN total_quarterly_tax ELSE 0 END), 0) as pending_balance,
                COALESCE(SUM(CASE WHEN payment_status = 'overdue' THEN total_quarterly_tax ELSE 0 END), 0) as overdue_balance,
                COALESCE(SUM(CASE WHEN payment_status IN ('pending', 'overdue') THEN total_quarterly_tax ELSE 0 END), 0) as total_outstanding,
                COUNT(CASE WHEN payment_status IN ('pending', 'overdue') THEN 1 END) as outstanding_bills
                FROM quarterly_taxes
                WHERE year = ?");
            
            $stmt->execute([$year]);
            $outstanding = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'annual' => $annual ?: [],
                'quarterly' => $quarterly,
                'current_quarter' => $current_quarter ?: [],
                'outstanding' => $outstanding ?: []
            ];
            
        } catch (Exception $e) {
            error_log("Tax Stats Error: " . $e->getMessage());
            return [
                'annual' => [],
                'quarterly' => [],
                'current_quarter' => [],
                'outstanding' => []
            ];
        }
    }
    
    private function getCollectionPerformance($year) {
        try {
            $currentQuarter = $this->getCurrentQuarter();
            
            // Overall collection rate for selected year
            $stmt = $this->rpt_pdo->prepare("SELECT 
                COALESCE(SUM(total_quarterly_tax), 0) as total_assigned,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END), 0) as total_collected,
                CASE 
                    WHEN COALESCE(SUM(total_quarterly_tax), 0) > 0 
                    THEN ROUND((SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END) / SUM(total_quarterly_tax)) * 100, 1)
                    ELSE 0 
                END as collection_rate,
                COUNT(*) as total_bills,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_bills
                FROM quarterly_taxes 
                WHERE year = ?");
            
            $stmt->execute([$year]);
            $overall = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Compare with previous year
            $previousYear = $year - 1;
            $stmt = $this->rpt_pdo->prepare("SELECT 
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END), 0) as previous_year_collected
                FROM quarterly_taxes 
                WHERE year = ?");
            
            $stmt->execute([$previousYear]);
            $previous_year = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Month-by-month collection for selected year
            $stmt = $this->rpt_pdo->prepare("SELECT 
                DATE_FORMAT(payment_date, '%b') as month,
                MONTH(payment_date) as month_num,
                COUNT(*) as payment_count,
                COALESCE(SUM(total_quarterly_tax), 0) as amount_collected,
                COALESCE(AVG(days_late), 0) as avg_days_late
                FROM quarterly_taxes 
                WHERE year = ?
                AND payment_status = 'paid'
                AND payment_date IS NOT NULL
                GROUP BY MONTH(payment_date), DATE_FORMAT(payment_date, '%b')
                ORDER BY month_num");
            
            $stmt->execute([$year]);
            $monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Payment method distribution for selected year
            $stmt = $this->rpt_pdo->prepare("SELECT 
                CASE 
                    WHEN payment_method IS NULL OR payment_method = '' THEN 'Cash'
                    ELSE payment_method 
                END as payment_method,
                COUNT(*) as count,
                SUM(total_quarterly_tax) as amount
                FROM quarterly_taxes 
                WHERE payment_status = 'paid'
                AND year = ?
                GROUP BY CASE 
                    WHEN payment_method IS NULL OR payment_method = '' THEN 'Cash'
                    ELSE payment_method 
                END");
            
            $stmt->execute([$year]);
            $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'overall' => $overall ?: ['total_assigned' => 0, 'total_collected' => 0, 'collection_rate' => 0, 'total_bills' => 0, 'paid_bills' => 0],
                'previous_year' => $previous_year ?: ['previous_year_collected' => 0],
                'monthly' => $monthly,
                'payment_methods' => $payment_methods
            ];
            
        } catch (Exception $e) {
            error_log("Collection Performance Error: " . $e->getMessage());
            return [
                'overall' => ['total_assigned' => 0, 'total_collected' => 0, 'collection_rate' => 0],
                'previous_year' => ['previous_year_collected' => 0],
                'monthly' => [],
                'payment_methods' => []
            ];
        }
    }
    
    private function getPropertyDistribution() {
        try {
            // Property types with detailed breakdown
            $stmt = $this->rpt_pdo->query("SELECT 
                COALESCE(lp.property_type, 'Unknown') as property_type,
                COUNT(DISTINCT lp.id) as property_count,
                COUNT(DISTINCT bp.id) as building_count,
                COALESCE(SUM(lp.land_assessed_value), 0) as total_land_value,
                COALESCE(SUM(lp.land_annual_tax), 0) as total_land_tax,
                COALESCE(SUM(bp.building_assessed_value), 0) as total_building_value,
                COALESCE(SUM(bp.annual_tax), 0) as total_building_tax,
                COALESCE(SUM(lp.land_annual_tax) + COALESCE(SUM(bp.annual_tax), 0), 0) as total_tax,
                COALESCE(SUM(lp.land_area_sqm), 0) as total_area_sqm,
                COALESCE(AVG(lp.land_annual_tax), 0) as avg_tax_per_property,
                COALESCE(AVG(lp.land_assessed_value), 0) as avg_land_value
                FROM land_properties lp
                LEFT JOIN building_properties bp ON lp.id = bp.land_id AND bp.status = 'active'
                WHERE lp.status = 'active'
                GROUP BY lp.property_type
                ORDER BY total_tax DESC");
            
            $property_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Building types distribution
            $stmt = $this->rpt_pdo->query("SELECT 
                COALESCE(bp.construction_type, 'Unknown') as construction_type,
                COUNT(*) as count,
                COALESCE(SUM(bp.building_assessed_value), 0) as total_value,
                COALESCE(SUM(bp.annual_tax), 0) as total_tax,
                COALESCE(AVG(bp.building_assessed_value), 0) as avg_value,
                COALESCE(SUM(bp.floor_area_sqm), 0) as total_area_sqm
                FROM building_properties bp
                WHERE bp.status = 'active'
                GROUP BY bp.construction_type
                ORDER BY total_value DESC");
            
            $building_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'property_types' => $property_types,
                'building_types' => $building_types
            ];
            
        } catch (Exception $e) {
            error_log("Property Distribution Error: " . $e->getMessage());
            return [
                'property_types' => [],
                'building_types' => []
            ];
        }
    }
    
    private function getQuarterlyAnalysis($year) {
        try {
            $stmt = $this->rpt_pdo->prepare("SELECT 
                quarter,
                year,
                SUM(total_quarterly_tax) as total_due,
                SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END) as collected,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN payment_status = 'overdue' THEN total_quarterly_tax ELSE 0 END) as overdue_amount,
                SUM(CASE WHEN payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                SUM(CASE WHEN payment_status = 'pending' THEN total_quarterly_tax ELSE 0 END) as pending_amount,
                SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                ROUND(
                    CASE 
                        WHEN SUM(total_quarterly_tax) > 0 
                        THEN (SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END) / SUM(total_quarterly_tax)) * 100
                        ELSE 0 
                    END, 1
                ) as collection_rate,
                AVG(CASE WHEN payment_status = 'paid' AND days_late > 0 THEN days_late ELSE NULL END) as avg_days_late,
                SUM(CASE WHEN payment_status = 'paid' AND discount_applied = 1 THEN discount_amount ELSE 0 END) as total_discounts,
                SUM(CASE WHEN payment_status = 'paid' AND penalty_amount > 0 THEN penalty_amount ELSE 0 END) as total_penalties
                FROM quarterly_taxes 
                WHERE year = ?
                GROUP BY quarter, year
                ORDER BY year DESC, 
                    CASE quarter
                        WHEN 'Q1' THEN 1
                        WHEN 'Q2' THEN 2
                        WHEN 'Q3' THEN 3
                        WHEN 'Q4' THEN 4
                    END");
            
            $stmt->execute([$year]);
            $analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $analysis;
            
        } catch (Exception $e) {
            error_log("Quarterly Analysis Error: " . $e->getMessage());
            return [];
        }
    }
    
    private function getTopBarangays($year) {
        try {
            // Top 10 barangays by tax revenue for selected year
            $stmt = $this->rpt_pdo->prepare("SELECT 
                COALESCE(pr.barangay, 'Unknown') as barangay,
                COALESCE(pr.district, 'Unknown') as district,
                COUNT(DISTINCT pr.id) as property_count,
                COUNT(DISTINCT pr.owner_id) as unique_owners,
                COALESCE(SUM(pt.total_annual_tax), 0) as total_annual_tax,
                COALESCE(SUM(lp.land_assessed_value), 0) as total_land_value,
                COALESCE(SUM(bp.building_assessed_value), 0) as total_building_value,
                COALESCE(SUM(pt.total_annual_tax) / COUNT(DISTINCT pr.id), 0) as avg_tax_per_property,
                ROUND(
                    CASE 
                        WHEN COUNT(DISTINCT pr.id) > 0 
                        THEN (COALESCE(SUM(pt.total_annual_tax), 0) / COUNT(DISTINCT pr.id))
                        ELSE 0 
                    END, 2
                ) as avg_tax_per_property
                FROM property_registrations pr
                LEFT JOIN property_totals pt ON pr.id = pt.registration_id AND pt.status = 'active'
                LEFT JOIN land_properties lp ON pr.id = lp.registration_id AND lp.status = 'active'
                LEFT JOIN building_properties bp ON lp.id = bp.land_id AND bp.status = 'active'
                WHERE pr.status = 'approved'
                AND pr.barangay IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM quarterly_taxes qt
                    JOIN property_totals pt2 ON qt.property_total_id = pt2.id
                    WHERE pt2.registration_id = pr.id
                    AND qt.year = ?
                )
                GROUP BY pr.barangay, pr.district
                ORDER BY total_annual_tax DESC
                LIMIT 10");
            
            $stmt->execute([$year]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Top Barangays Error: " . $e->getMessage());
            return [];
        }
    }
    
    private function getPaymentAnalysis($year) {
        try {
            // Early vs late payments for selected year
            $stmt = $this->rpt_pdo->prepare("SELECT 
                'On Time' as payment_timing,
                COUNT(*) as count,
                SUM(total_quarterly_tax) as amount
                FROM quarterly_taxes 
                WHERE year = ?
                AND payment_status = 'paid'
                AND days_late = 0
                AND discount_applied = 1
                
                UNION ALL
                
                SELECT 
                'Late Payment' as payment_timing,
                COUNT(*) as count,
                SUM(total_quarterly_tax) as amount
                FROM quarterly_taxes 
                WHERE year = ?
                AND payment_status = 'paid'
                AND days_late > 0
                
                UNION ALL
                
                SELECT 
                'With Penalty' as payment_timing,
                COUNT(*) as count,
                SUM(total_quarterly_tax) as amount
                FROM quarterly_taxes 
                WHERE year = ?
                AND payment_status = 'paid'
                AND penalty_amount > 0");
            
            $stmt->execute([$year, $year, $year]);
            $payment_timing = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Discount vs penalty analysis for selected year
            $stmt = $this->rpt_pdo->prepare("SELECT 
                COALESCE(SUM(CASE WHEN discount_applied = 1 THEN discount_amount ELSE 0 END), 0) as total_discounts_given,
                COALESCE(SUM(CASE WHEN penalty_amount > 0 THEN penalty_amount ELSE 0 END), 0) as total_penalties_collected,
                COUNT(CASE WHEN discount_applied = 1 THEN 1 END) as discount_count,
                COUNT(CASE WHEN penalty_amount > 0 THEN 1 END) as penalty_count
                FROM quarterly_taxes 
                WHERE year = ? AND payment_status = 'paid'");
            
            $stmt->execute([$year]);
            $discount_penalty = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delinquency analysis for selected year
            $stmt = $this->rpt_pdo->prepare("SELECT 
                CASE 
                    WHEN days_late BETWEEN 1 AND 30 THEN '1-30 days'
                    WHEN days_late BETWEEN 31 AND 60 THEN '31-60 days'
                    WHEN days_late BETWEEN 61 AND 90 THEN '61-90 days'
                    WHEN days_late > 90 THEN '90+ days'
                    ELSE 'On Time'
                END as delinquency_range,
                COUNT(*) as count,
                SUM(total_quarterly_tax) as amount
                FROM quarterly_taxes 
                WHERE year = ? 
                AND payment_status IN ('paid', 'overdue')
                GROUP BY CASE 
                    WHEN days_late BETWEEN 1 AND 30 THEN '1-30 days'
                    WHEN days_late BETWEEN 31 AND 60 THEN '31-60 days'
                    WHEN days_late BETWEEN 61 AND 90 THEN '61-90 days'
                    WHEN days_late > 90 THEN '90+ days'
                    ELSE 'On Time'
                END");
            
            $stmt->execute([$year]);
            $delinquency = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'payment_timing' => $payment_timing,
                'discount_penalty' => $discount_penalty ?: [],
                'delinquency' => $delinquency
            ];
            
        } catch (Exception $e) {
            error_log("Payment Analysis Error: " . $e->getMessage());
            return [
                'payment_timing' => [],
                'discount_penalty' => [],
                'delinquency' => []
            ];
        }
    }
    
    private function getRecentActivities($year) {
        try {
            // Recent payments with details for selected year
            $stmt = $this->rpt_pdo->prepare("SELECT 
                'payment' as type,
                qt.receipt_number,
                CONCAT(po.first_name, ' ', po.last_name) as owner_name,
                qt.payment_status,
                qt.payment_date,
                qt.total_quarterly_tax as amount,
                qt.quarter,
                qt.year,
                qt.discount_amount,
                qt.penalty_amount,
                qt.days_late,
                pr.reference_number
                FROM quarterly_taxes qt
                JOIN property_totals pt ON qt.property_total_id = pt.id
                JOIN property_registrations pr ON pt.registration_id = pr.id
                JOIN property_owners po ON pr.owner_id = po.id
                WHERE qt.payment_status = 'paid'
                AND qt.year = ?
                ORDER BY qt.payment_date DESC
                LIMIT 10");
            
            $stmt->execute([$year]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Recent registrations for selected year
            $stmt = $this->rpt_pdo->prepare("SELECT 
                'registration' as type,
                pr.reference_number,
                CONCAT(po.first_name, ' ', po.last_name) as owner_name,
                pr.status,
                pr.created_at,
                pr.lot_location,
                pr.barangay,
                (SELECT COUNT(*) FROM land_properties lp WHERE lp.registration_id = pr.id) as land_count,
                (SELECT COUNT(*) FROM building_properties bp 
                 JOIN land_properties lp2 ON bp.land_id = lp2.id 
                 WHERE lp2.registration_id = pr.id) as building_count
                FROM property_registrations pr
                JOIN property_owners po ON pr.owner_id = po.id
                WHERE YEAR(pr.created_at) = ?
                ORDER BY pr.created_at DESC
                LIMIT 10");
            
            $stmt->execute([$year]);
            $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Overdue notices for selected year
            $stmt = $this->rpt_pdo->prepare("SELECT 
                'overdue' as type,
                qt.id,
                CONCAT(po.first_name, ' ', po.last_name) as owner_name,
                qt.quarter,
                qt.year,
                qt.total_quarterly_tax as amount,
                qt.due_date,
                qt.days_late,
                pr.reference_number,
                pr.barangay
                FROM quarterly_taxes qt
                JOIN property_totals pt ON qt.property_total_id = pt.id
                JOIN property_registrations pr ON pt.registration_id = pr.id
                JOIN property_owners po ON pr.owner_id = po.id
                WHERE qt.payment_status = 'overdue'
                AND qt.year = ?
                ORDER BY qt.days_late DESC
                LIMIT 10");
            
            $stmt->execute([$year]);
            $overdue = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'payments' => $payments,
                'registrations' => $registrations,
                'overdue' => $overdue
            ];
            
        } catch (Exception $e) {
            error_log("Recent Activities Error: " . $e->getMessage());
            return [
                'payments' => [],
                'registrations' => [],
                'overdue' => []
            ];
        }
    }
    
    private function getCurrentQuarter() {
        $month = date('n');
        if ($month >= 1 && $month <= 3) return 'Q1';
        if ($month >= 4 && $month <= 6) return 'Q2';
        if ($month >= 7 && $month <= 9) return 'Q3';
        return 'Q4';
    }
}

// Handle request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $dashboard = new RPTDashboard();
    $dashboard->handleRequest();
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}
?>