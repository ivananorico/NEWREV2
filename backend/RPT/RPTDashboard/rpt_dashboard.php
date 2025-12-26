<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Include RPT database configuration
require_once '../../../db/RPT/rpt_db.php';

class RPTDashboard {
    private $rpt_pdo;
    private $digital_pdo;
    
    public function __construct() {
        $this->connectToRPTDB();
        $this->connectToDigitalDB();
    }
    
    private function connectToRPTDB() {
        try {
            $this->rpt_pdo = getDatabaseConnection();
            
            // Check if connection returned an error array
            if (is_array($this->rpt_pdo) && isset($this->rpt_pdo['error'])) {
                $this->rpt_pdo = null;
                error_log("RPT DB Connection failed: " . $this->rpt_pdo['message']);
            }
        } catch (Exception $e) {
            $this->rpt_pdo = null;
            error_log("RPT DB Connection failed: " . $e->getMessage());
        }
    }
    
    private function connectToDigitalDB() {
        // Include Digital database configuration
        require_once '../../../db/Digital/digital_db.php';
        
        // The digital_db.php already creates $pdo variable
        // We'll use that connection if it exists
        if (isset($pdo) && $pdo instanceof PDO) {
            $this->digital_pdo = $pdo;
        } else {
            $this->digital_pdo = null;
            error_log("Digital DB Connection not available");
        }
    }
    
    public function getDashboardStats() {
        try {
            if (!$this->rpt_pdo) {
                throw new Exception("RPT database connection failed");
            }
            
            $response = [
                'success' => true,
                'property_stats' => $this->getPropertyStats(),
                'tax_stats' => $this->getTaxStats(),
                'registration_audit' => $this->getRegistrationAudit(),
                'payment_audit' => $this->getPaymentAudit(),
                'quarterly_overview' => $this->getQuarterlyOverview(),
                'overdue_analysis' => $this->getOverdueAnalysis(),
                'property_distribution' => $this->getPropertyDistribution(),
                'recent_activities' => $this->getRecentActivities(),
                'collection_performance' => $this->getCollectionPerformance(),
                'current_quarter' => $this->getCurrentQuarter(),
                'current_year' => date('Y')
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
            $stats = [];
            
            // Total Property Owners
            $stmt = $this->rpt_pdo->query("SELECT 
                COUNT(*) as total_owners,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_owners,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_owners,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_owners
                FROM property_owners");
            $owners = $stmt->fetch();
            $stats['owners'] = $owners;
            
            // Property Registrations
            $stmt = $this->rpt_pdo->query("SELECT 
                COUNT(*) as total_properties,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_properties,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_properties,
                SUM(CASE WHEN status = 'for_inspection' THEN 1 ELSE 0 END) as inspection_properties,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_properties,
                SUM(CASE WHEN status = 'needs_correction' THEN 1 ELSE 0 END) as correction_properties
                FROM property_registrations");
            $properties = $stmt->fetch();
            $stats['properties'] = $properties;
            
            // Building vs Land Only
            $stmt = $this->rpt_pdo->query("SELECT 
                has_building,
                COUNT(*) as count
                FROM property_registrations 
                WHERE status = 'approved'
                GROUP BY has_building");
            $building_stats = $stmt->fetchAll();
            $stats['building_stats'] = $building_stats;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Property Stats Error: " . $e->getMessage());
            return [];
        }
    }
    
    private function getTaxStats() {
        try {
            $stats = [];
            $currentYear = date('Y');
            
            // Total Annual Tax Assessment
            $stmt = $this->rpt_pdo->query("SELECT 
                COALESCE(SUM(total_annual_tax), 0) as total_annual_tax,
                COUNT(*) as total_assessments
                FROM property_totals 
                WHERE status = 'active'");
            $annual = $stmt->fetch();
            $stats['annual_tax'] = $annual;
            
            // Quarterly Tax Breakdown (ALL statuses for statistics)
            $stmt = $this->rpt_pdo->prepare("SELECT 
                quarter,
                COUNT(*) as tax_count,
                COALESCE(SUM(total_quarterly_tax), 0) as total_amount,
                SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END) as paid_amount,
                SUM(CASE WHEN payment_status = 'pending' THEN total_quarterly_tax ELSE 0 END) as pending_amount,
                SUM(CASE WHEN payment_status = 'overdue' THEN total_quarterly_tax ELSE 0 END) as overdue_amount,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue_count
                FROM quarterly_taxes 
                WHERE year = ?
                GROUP BY quarter
                ORDER BY quarter");
            $stmt->execute([$currentYear]);
            $quarterly = $stmt->fetchAll();
            $stats['quarterly_tax'] = $quarterly;
            
            // Yearly Tax Summary
            $stmt = $this->rpt_pdo->query("SELECT 
                year,
                COUNT(*) as tax_count,
                COALESCE(SUM(total_quarterly_tax), 0) as total_amount,
                SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END) as paid_amount,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count
                FROM quarterly_taxes 
                GROUP BY year
                ORDER BY year DESC
                LIMIT 5");
            $yearly = $stmt->fetchAll();
            $stats['yearly_summary'] = $yearly;
            
            // Total Outstanding Balance (pending + overdue)
            $stmt = $this->rpt_pdo->query("SELECT 
                COALESCE(SUM(CASE WHEN payment_status IN ('pending', 'overdue') THEN total_quarterly_tax ELSE 0 END), 0) as total_outstanding
                FROM quarterly_taxes");
            $outstanding = $stmt->fetch();
            $stats['outstanding_balance'] = $outstanding['total_outstanding'] ?? 0;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Tax Stats Error: " . $e->getMessage());
            return [];
        }
    }
    
    private function getRegistrationAudit() {
        try {
            // Get recent registration activities with owner details
            $stmt = $this->rpt_pdo->query("SELECT 
                pr.reference_number,
                po.full_name,
                po.phone,
                pr.lot_location,
                pr.barangay,
                pr.district,
                pr.has_building,
                pr.status,
                pr.created_at,
                pr.correction_notes,
                (SELECT GROUP_CONCAT(pi.scheduled_date, ' - ', pi.assessor_name, ' (', pi.status, ')') 
                 FROM property_inspections pi 
                 WHERE pi.registration_id = pr.id) as inspections
                FROM property_registrations pr
                JOIN property_owners po ON pr.owner_id = po.id
                ORDER BY pr.created_at DESC
                LIMIT 20");
            $registrations = $stmt->fetchAll();
            
            // Get registration status counts by date
            $stmt = $this->rpt_pdo->query("SELECT 
                DATE(created_at) as date,
                status,
                COUNT(*) as count
                FROM property_registrations 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at), status
                ORDER BY date DESC, status");
            $daily_stats = $stmt->fetchAll();
            
            return [
                'recent_registrations' => $registrations,
                'daily_stats' => $daily_stats
            ];
            
        } catch (Exception $e) {
            error_log("Registration Audit Error: " . $e->getMessage());
            return ['recent_registrations' => [], 'daily_stats' => []];
        }
    }
    
    private function getPaymentAudit() {
        try {
            $audit = [];
            
            // Get RPT payment audit - ONLY COMPLETED PAYMENTS (status = 'paid')
            $stmt = $this->rpt_pdo->query("SELECT 
                qt.id,
                qt.quarter,
                qt.year,
                qt.total_quarterly_tax,
                qt.payment_status,
                qt.receipt_number,
                qt.payment_date,
                qt.due_date,
                pr.reference_number as property_ref,
                po.full_name,
                po.phone,
                po.email,
                pt.total_annual_tax
                FROM quarterly_taxes qt
                JOIN property_totals pt ON qt.property_total_id = pt.id
                JOIN property_registrations pr ON pt.registration_id = pr.id
                JOIN property_owners po ON pr.owner_id = po.id
                WHERE qt.payment_status = 'paid'  -- ONLY SHOW PAID TAXES
                AND qt.receipt_number IS NOT NULL  -- MUST HAVE RECEIPT
                ORDER BY qt.payment_date DESC
                LIMIT 15");
            $rpt_payments = $stmt->fetchAll();
            $audit['rpt_payments'] = $rpt_payments;
            
            // Get digital payments if available - ONLY COMPLETED PAYMENTS
            if ($this->digital_pdo) {
                $stmt = $this->digital_pdo->prepare("SELECT 
                    payment_id,
                    client_reference,
                    purpose,
                    amount,
                    phone,
                    payment_method,
                    receipt_number,
                    payment_status,
                    created_at,
                    paid_at
                    FROM payment_transactions 
                    WHERE client_system = 'rpt'
                    AND payment_status = 'paid'  -- ONLY COMPLETED PAYMENTS
                    AND receipt_number IS NOT NULL  -- MUST HAVE RECEIPT
                    ORDER BY paid_at DESC
                    LIMIT 10");
                $stmt->execute();
                $digital_payments = $stmt->fetchAll();
                $audit['digital_payments'] = $digital_payments;
            } else {
                $audit['digital_payments'] = [];
            }
            
            // Payment summary by status (for statistics only)
            $stmt = $this->rpt_pdo->query("SELECT 
                payment_status,
                COUNT(*) as count,
                COALESCE(SUM(total_quarterly_tax), 0) as total_amount
                FROM quarterly_taxes 
                GROUP BY payment_status");
            $payment_summary = $stmt->fetchAll();
            $audit['payment_summary'] = $payment_summary;
            
            return $audit;
            
        } catch (Exception $e) {
            error_log("Payment Audit Error: " . $e->getMessage());
            return [
                'rpt_payments' => [],
                'digital_payments' => [],
                'payment_summary' => []
            ];
        }
    }
    
    private function getQuarterlyOverview() {
        try {
            $currentYear = date('Y');
            $currentQuarter = $this->getCurrentQuarter();
            
            $stmt = $this->rpt_pdo->prepare("SELECT 
                quarter,
                COUNT(*) as total_taxes,
                COALESCE(SUM(total_quarterly_tax), 0) as total_amount,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN total_quarterly_tax ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN payment_status = 'overdue' THEN total_quarterly_tax ELSE 0 END), 0) as overdue_amount
                FROM quarterly_taxes 
                WHERE year = ?
                GROUP BY quarter
                ORDER BY quarter");
            $stmt->execute([$currentYear]);
            $quarters = $stmt->fetchAll();
            
            return [
                'current_quarter' => $currentQuarter,
                'current_year' => $currentYear,
                'quarters' => $quarters
            ];
            
        } catch (Exception $e) {
            error_log("Quarterly Overview Error: " . $e->getMessage());
            return [
                'current_quarter' => $this->getCurrentQuarter(),
                'current_year' => date('Y'),
                'quarters' => []
            ];
        }
    }
    
    private function getOverdueAnalysis() {
        try {
            // Overdue properties with details (pending and overdue status)
            $stmt = $this->rpt_pdo->query("SELECT 
                qt.id,
                qt.quarter,
                qt.year,
                qt.total_quarterly_tax,
                qt.due_date,
                qt.payment_status,
                DATEDIFF(CURDATE(), qt.due_date) as days_overdue,
                pr.reference_number,
                po.full_name,
                po.phone,
                po.email
                FROM quarterly_taxes qt
                JOIN property_totals pt ON qt.property_total_id = pt.id
                JOIN property_registrations pr ON pt.registration_id = pr.id
                JOIN property_owners po ON pr.owner_id = po.id
                WHERE qt.payment_status IN ('pending', 'overdue')
                AND qt.due_date < CURDATE()
                ORDER BY qt.due_date ASC
                LIMIT 15");
            $overdue_list = $stmt->fetchAll();
            
            // Overdue summary
            $stmt = $this->rpt_pdo->query("SELECT 
                COUNT(*) as total_overdue,
                COALESCE(SUM(total_quarterly_tax), 0) as total_overdue_amount,
                AVG(DATEDIFF(CURDATE(), due_date)) as avg_days_overdue,
                MIN(due_date) as oldest_overdue,
                MAX(due_date) as newest_overdue
                FROM quarterly_taxes 
                WHERE payment_status IN ('pending', 'overdue')
                AND due_date < CURDATE()");
            $overdue_summary = $stmt->fetch();
            
            return [
                'overdue_list' => $overdue_list,
                'overdue_summary' => $overdue_summary
            ];
            
        } catch (Exception $e) {
            error_log("Overdue Analysis Error: " . $e->getMessage());
            return [
                'overdue_list' => [],
                'overdue_summary' => []
            ];
        }
    }
    
    private function getPropertyDistribution() {
        try {
            // Enhanced Property types distribution with combined values
            $stmt = $this->rpt_pdo->query("SELECT 
                lp.property_type,
                COUNT(*) as property_count,
                COUNT(DISTINCT lp.registration_id) as unique_properties,
                COALESCE(SUM(lp.land_assessed_value), 0) as total_land_assessed_value,
                COALESCE(SUM(lp.land_market_value), 0) as total_land_market_value,
                COALESCE(SUM(lp.land_annual_tax), 0) as total_annual_tax,
                COALESCE(SUM(lp.land_area_sqm), 0) as total_area_sqm,
                COALESCE(AVG(lp.land_area_sqm), 0) as avg_area_sqm,
                COALESCE(AVG(lp.assessment_level), 0) as avg_assessment_level,
                COUNT(bp.id) as building_count,
                COALESCE(SUM(bp.building_assessed_value), 0) as total_building_assessed_value,
                COALESCE(SUM(bp.floor_area_sqm), 0) as total_building_area_sqm
                FROM land_properties lp
                LEFT JOIN building_properties bp ON lp.id = bp.land_id AND bp.status = 'active'
                WHERE lp.status = 'active'
                GROUP BY lp.property_type
                ORDER BY property_count DESC");
            $property_types = $stmt->fetchAll();
            
            // Enhanced - Property with buildings vs land only
            $stmt = $this->rpt_pdo->query("SELECT 
                pr.has_building,
                COUNT(*) as property_count,
                COALESCE(SUM(pt.total_annual_tax), 0) as total_annual_tax,
                COALESCE(SUM(lp.land_assessed_value), 0) as total_land_value,
                COALESCE(SUM(bp.building_assessed_value), 0) as total_building_value,
                COALESCE(AVG(lp.land_area_sqm), 0) as avg_land_area,
                COALESCE(AVG(bp.floor_area_sqm), 0) as avg_building_area
                FROM property_registrations pr
                JOIN land_properties lp ON pr.id = lp.registration_id AND lp.status = 'active'
                LEFT JOIN building_properties bp ON lp.id = bp.land_id AND bp.status = 'active'
                LEFT JOIN property_totals pt ON pr.id = pt.registration_id AND pt.status = 'active'
                WHERE pr.status = 'approved'
                GROUP BY pr.has_building");
            $building_comparison = $stmt->fetchAll();
            
            // Location distribution (by barangay)
            $stmt = $this->rpt_pdo->query("SELECT 
                pr.barangay,
                pr.district,
                COUNT(*) as property_count,
                COUNT(DISTINCT pr.owner_id) as unique_owners,
                COALESCE(SUM(pt.total_annual_tax), 0) as total_annual_tax,
                COALESCE(SUM(lp.land_assessed_value), 0) as total_land_value,
                COALESCE(SUM(lp.land_area_sqm), 0) as total_land_area
                FROM property_registrations pr
                LEFT JOIN property_totals pt ON pr.id = pt.registration_id AND pt.status = 'active'
                LEFT JOIN land_properties lp ON pr.id = lp.registration_id AND lp.status = 'active'
                WHERE pr.status = 'approved'
                GROUP BY pr.barangay, pr.district
                ORDER BY property_count DESC
                LIMIT 10");
            $locations = $stmt->fetchAll();
            
            // Enhanced Building types distribution
            $stmt = $this->rpt_pdo->query("SELECT 
                bp.construction_type,
                pc.classification as building_classification,
                COUNT(*) as building_count,
                COALESCE(SUM(bp.building_assessed_value), 0) as total_assessed_value,
                COALESCE(SUM(bp.building_market_value), 0) as total_market_value,
                COALESCE(SUM(bp.floor_area_sqm), 0) as total_area_sqm,
                COALESCE(AVG(bp.floor_area_sqm), 0) as avg_area_sqm,
                COALESCE(AVG(bp.year_built), 0) as avg_year_built,
                COALESCE(SUM(bp.annual_tax), 0) as total_annual_tax
                FROM building_properties bp
                LEFT JOIN property_configurations pc ON bp.property_config_id = pc.id
                WHERE bp.status = 'active'
                GROUP BY bp.construction_type, pc.classification
                ORDER BY building_count DESC");
            $building_types = $stmt->fetchAll();
            
            // Get total combined values
            $stmt = $this->rpt_pdo->query("SELECT 
                COUNT(DISTINCT lp.id) as total_land_parcels,
                COUNT(DISTINCT bp.id) as total_buildings,
                COALESCE(SUM(lp.land_assessed_value), 0) as grand_total_land_value,
                COALESCE(SUM(bp.building_assessed_value), 0) as grand_total_building_value,
                COALESCE(SUM(lp.land_annual_tax), 0) as grand_total_land_tax,
                COALESCE(SUM(bp.annual_tax), 0) as grand_total_building_tax,
                COALESCE(SUM(lp.land_area_sqm), 0) as grand_total_land_area,
                COALESCE(SUM(bp.floor_area_sqm), 0) as grand_total_building_area
                FROM land_properties lp
                LEFT JOIN building_properties bp ON lp.id = bp.land_id AND bp.status = 'active'
                WHERE lp.status = 'active'");
            $grand_totals = $stmt->fetch();
            
            return [
                'property_types' => $property_types,
                'building_comparison' => $building_comparison,
                'locations' => $locations,
                'building_types' => $building_types,
                'grand_totals' => $grand_totals
            ];
            
        } catch (Exception $e) {
            error_log("Property Distribution Error: " . $e->getMessage());
            return [
                'property_types' => [],
                'building_comparison' => [],
                'locations' => [],
                'building_types' => [],
                'grand_totals' => []
            ];
        }
    }
    
    private function getRecentActivities() {
        try {
            $activities = [];
            
            // Recent property registrations
            $stmt = $this->rpt_pdo->query("SELECT 
                'registration' as type,
                pr.reference_number,
                po.full_name,
                pr.status,
                pr.created_at,
                NULL as amount,
                NULL as payment_method
                FROM property_registrations pr
                JOIN property_owners po ON pr.owner_id = po.id
                ORDER BY pr.created_at DESC
                LIMIT 5");
            $registrations = $stmt->fetchAll();
            
            // Recent tax payments - ONLY PAID
            $stmt = $this->rpt_pdo->query("SELECT 
                'payment' as type,
                qt.receipt_number,
                po.full_name,
                qt.payment_status,
                qt.payment_date as created_at,
                qt.total_quarterly_tax as amount,
                'rpt' as payment_method
                FROM quarterly_taxes qt
                JOIN property_totals pt ON qt.property_total_id = pt.id
                JOIN property_registrations pr ON pt.registration_id = pr.id
                JOIN property_owners po ON pr.owner_id = po.id
                WHERE qt.payment_status = 'paid'
                ORDER BY qt.payment_date DESC
                LIMIT 5");
            $payments = $stmt->fetchAll();
            
            // Recent inspections
            $stmt = $this->rpt_pdo->query("SELECT 
                'inspection' as type,
                pr.reference_number,
                po.full_name,
                pi.status,
                pi.scheduled_date as created_at,
                NULL as amount,
                pi.assessor_name as payment_method
                FROM property_inspections pi
                JOIN property_registrations pr ON pi.registration_id = pr.id
                JOIN property_owners po ON pr.owner_id = po.id
                WHERE pi.status = 'completed'
                ORDER BY pi.scheduled_date DESC
                LIMIT 5");
            $inspections = $stmt->fetchAll();
            
            // Merge all activities and sort by date
            $all_activities = array_merge($registrations, $payments, $inspections);
            usort($all_activities, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            return array_slice($all_activities, 0, 10);
            
        } catch (Exception $e) {
            error_log("Recent Activities Error: " . $e->getMessage());
            return [];
        }
    }
    
    private function getCollectionPerformance() {
        try {
            $currentYear = date('Y');
            
            $stmt = $this->rpt_pdo->prepare("SELECT 
                quarter,
                COALESCE(SUM(total_quarterly_tax), 0) as total_assigned,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END), 0) as total_collected,
                COUNT(*) as total_taxes,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_taxes,
                CASE 
                    WHEN COALESCE(SUM(total_quarterly_tax), 0) > 0 
                    THEN ROUND(COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END), 0) / COALESCE(SUM(total_quarterly_tax), 0) * 100, 1)
                    ELSE 0 
                END as collection_rate
                FROM quarterly_taxes 
                WHERE year = ?
                GROUP BY quarter
                ORDER BY quarter");
            $stmt->execute([$currentYear]);
            $quarterly_performance = $stmt->fetchAll();
            
            // Overall performance
            $stmt = $this->rpt_pdo->prepare("SELECT 
                COALESCE(SUM(total_quarterly_tax), 0) as total_assigned,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END), 0) as total_collected,
                COUNT(*) as total_taxes,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_taxes,
                CASE 
                    WHEN COALESCE(SUM(total_quarterly_tax), 0) > 0 
                    THEN ROUND(COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_quarterly_tax ELSE 0 END), 0) / COALESCE(SUM(total_quarterly_tax), 0) * 100, 1)
                    ELSE 0 
                END as collection_rate
                FROM quarterly_taxes 
                WHERE year = ?");
            $stmt->execute([$currentYear]);
            $overall_performance = $stmt->fetch();
            
            // Year-to-date collection
            $stmt = $this->rpt_pdo->prepare("SELECT 
                MONTH(payment_date) as month,
                COUNT(*) as payment_count,
                COALESCE(SUM(total_quarterly_tax), 0) as amount_collected
                FROM quarterly_taxes 
                WHERE year = ?
                AND payment_status = 'paid'
                AND payment_date IS NOT NULL
                GROUP BY MONTH(payment_date)
                ORDER BY month");
            $stmt->execute([$currentYear]);
            $monthly_collection = $stmt->fetchAll();
            
            return [
                'quarterly_performance' => $quarterly_performance,
                'overall_performance' => $overall_performance,
                'monthly_collection' => $monthly_collection
            ];
            
        } catch (Exception $e) {
            error_log("Collection Performance Error: " . $e->getMessage());
            return [
                'quarterly_performance' => [],
                'overall_performance' => [],
                'monthly_collection' => []
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
    $dashboard->getDashboardStats();
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}
?>