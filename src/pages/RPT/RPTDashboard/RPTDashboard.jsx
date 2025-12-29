import React, { useState, useEffect } from 'react';
import { 
  BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, 
  Tooltip, Legend, ResponsiveContainer, LineChart, Line, Area
} from 'recharts';
import { 
  Building, Home, DollarSign, Users, Calendar, AlertCircle, 
  TrendingUp, TrendingDown, RefreshCw, MapPin, Tag, Filter,
  Download, FileText, BarChart3, PieChart as PieChartIcon,
  CheckCircle, Clock, XCircle, Landmark, Percent, Eye, Target,
  ArrowUpRight, ArrowDownRight, CircleDollarSign, ShieldAlert,
  CreditCard, Wallet, Timer, CalendarDays, TrendingUp as TrendingUpIcon,
  Banknote, AlertTriangle, CheckCheck, ArrowRightLeft, ChevronRight,
  Building2, Layers, Grid3x3, Compass, LandPlot, FileBarChart,
  Activity, LineChart as LineChartIcon, Calculator,
  FileSpreadsheet, Database, Table, ChevronDown, ChevronUp
} from 'lucide-react';
import * as XLSX from 'xlsx';

const API_BASE = window.location.hostname === "localhost"
  ? "http://localhost/revenue2/backend"
  : "https://revenuetreasury.goserveph.com/backend";

export default function RPTDashboardImproved() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [dashboardData, setDashboardData] = useState(null);
  const [exportLoading, setExportLoading] = useState(false);
  const [activeTab, setActiveTab] = useState('payments'); // For recent activities tabs
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
  const [availableYears, setAvailableYears] = useState([]);
  const [yearDropdownOpen, setYearDropdownOpen] = useState(false);
  const [currentQuarter] = useState(() => {
    const month = new Date().getMonth() + 1;
    if (month >= 1 && month <= 3) return 'Q1';
    if (month >= 4 && month <= 6) return 'Q2';
    if (month >= 7 && month <= 9) return 'Q3';
    return 'Q4';
  });

  useEffect(() => {
    fetchAvailableYears();
  }, []);

  useEffect(() => {
    if (availableYears.length > 0) {
      // Set default year to latest available
      const latestYear = Math.max(...availableYears);
      if (!availableYears.includes(selectedYear)) {
        setSelectedYear(latestYear);
      }
    }
  }, [availableYears]);

  useEffect(() => {
    fetchDashboardData();
  }, [selectedYear]);

  const fetchAvailableYears = async () => {
    try {
      const response = await fetch(`${API_BASE}/RPT/RPTDashboard/rpt_dashboard.php?action=get_years`);
      const data = await response.json();
      
      if (data.success && data.years && data.years.length > 0) {
        setAvailableYears(data.years);
        
        // Set initial year to latest available
        const latestYear = Math.max(...data.years);
        setSelectedYear(latestYear);
      } else {
        // Fallback to current year and previous year
        const currentYear = new Date().getFullYear();
        setAvailableYears([currentYear - 1, currentYear]);
        setSelectedYear(currentYear);
      }
    } catch (err) {
      console.error('Error fetching years:', err);
      const currentYear = new Date().getFullYear();
      setAvailableYears([currentYear - 1, currentYear]);
      setSelectedYear(currentYear);
    }
  };

  const fetchDashboardData = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(`${API_BASE}/RPT/RPTDashboard/rpt_dashboard.php?action=dashboard&year=${selectedYear}`, {
        headers: { 'Accept': 'application/json' }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (!data.success) {
        throw new Error(data.error || data.message || 'Failed to load dashboard data');
      }
      
      // Parse string numbers to floats
      const parsedData = parseNumbersInData(data);
      setDashboardData(parsedData);
      
    } catch (err) {
      console.error('Error:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  // Helper function to parse string numbers to floats
  const parseNumbersInData = (data) => {
    if (!data) return data;
    
    const parsed = JSON.parse(JSON.stringify(data));
    
    // Helper function to parse numeric strings
    const parseNumericFields = (obj) => {
      if (!obj) return obj;
      
      Object.keys(obj).forEach(key => {
        if (typeof obj[key] === 'string' && !isNaN(obj[key]) && obj[key] !== '') {
          // Check if it's a number string (including decimals)
          if (/^-?\d*\.?\d+$/.test(obj[key])) {
            obj[key] = parseFloat(obj[key]);
          }
        } else if (typeof obj[key] === 'object' && obj[key] !== null) {
          parseNumericFields(obj[key]);
        }
      });
    };
    
    parseNumericFields(parsed);
    return parsed;
  };

  // Fixed formatCurrency function
  const formatCurrency = (amount) => {
    if (amount === null || amount === undefined || amount === '' || isNaN(amount)) {
      return '₱0';
    }
    
    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
    
    if (numAmount >= 1000000000) {
      return `₱${(numAmount / 1000000000).toFixed(2)}B`;
    }
    if (numAmount >= 1000000) {
      return `₱${(numAmount / 1000000).toFixed(2)}M`;
    }
    if (numAmount >= 1000) {
      return `₱${(numAmount / 1000).toFixed(2)}K`;
    }
    return `₱${numAmount.toFixed(2)}`;
  };

  // Safe parse float helper
  const safeParseFloat = (value, defaultValue = 0) => {
    if (value === null || value === undefined || value === '') return defaultValue;
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return isNaN(num) ? defaultValue : num;
  };

  const formatNumber = (num) => {
    const parsedNum = safeParseFloat(num);
    return new Intl.NumberFormat('en-PH').format(parsedNum);
  };

  const formatPercent = (value) => {
    const parsedValue = safeParseFloat(value);
    return `${parsedValue.toFixed(1)}%`;
  };

  const getProgressColor = (value) => {
    const numValue = safeParseFloat(value);
    if (numValue >= 90) return 'bg-green-500';
    if (numValue >= 75) return 'bg-yellow-500';
    return 'bg-red-500';
  };

  const getStatusColor = (status) => {
    switch(status) {
      case 'paid': return 'bg-green-100 text-green-800';
      case 'pending': return 'bg-yellow-100 text-yellow-800';
      case 'overdue': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  // ==================== EXPORT TO EXCEL FUNCTIONS ====================
  // (Keep all your existing export functions - they remain the same)
  const exportToExcel = (data, fileName, sheetName = 'Sheet1') => {
    try {
      if (!data || data.length === 0) {
        alert('No data available to export');
        return;
      }

      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.json_to_sheet(data);
      XLSX.utils.book_append_sheet(wb, ws, sheetName);
      XLSX.writeFile(wb, `${fileName}_${selectedYear}_${new Date().toISOString().split('T')[0]}.xlsx`);
      
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting to Excel');
    }
  };

  const exportQuarterlyReport = () => {
    if (!dashboardData) return;
    
    setExportLoading(true);
    try {
      const quarterlyData = dashboardData.quarterly_analysis.map(q => ({
        'Year': q.year,
        'Quarter': q.quarter,
        'Total Due (PHP)': safeParseFloat(q.total_due),
        'Collected (PHP)': safeParseFloat(q.collected),
        'Collection Rate (%)': safeParseFloat(q.collection_rate),
        'Paid Count': safeParseFloat(q.paid_count),
        'Overdue Amount (PHP)': safeParseFloat(q.overdue_amount),
        'Overdue Count': safeParseFloat(q.overdue_count),
        'Pending Amount (PHP)': safeParseFloat(q.pending_amount),
        'Pending Count': safeParseFloat(q.pending_count),
        'Average Days Late': safeParseFloat(q.avg_days_late),
        'Total Discounts (PHP)': safeParseFloat(q.total_discounts),
        'Total Penalties (PHP)': safeParseFloat(q.total_penalties)
      }));

      exportToExcel(quarterlyData, `RPT_Quarterly_Report_${selectedYear}`, 'Quarterly Analysis');
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting quarterly report');
    } finally {
      setExportLoading(false);
    }
  };

  const exportTopBarangaysReport = () => {
    if (!dashboardData) return;
    
    setExportLoading(true);
    try {
      const barangayData = dashboardData.top_barangays.map(b => ({
        'Barangay': b.barangay,
        'District': b.district,
        'Property Count': safeParseFloat(b.property_count),
        'Unique Owners': safeParseFloat(b.unique_owners),
        'Total Annual Tax (PHP)': safeParseFloat(b.total_annual_tax),
        'Total Land Value (PHP)': safeParseFloat(b.total_land_value),
        'Total Building Value (PHP)': safeParseFloat(b.total_building_value),
        'Average Tax per Property (PHP)': safeParseFloat(b.avg_tax_per_property)
      }));

      exportToExcel(barangayData, `RPT_Top_Barangays_${selectedYear}`, 'Top Barangays');
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting barangay report');
    } finally {
      setExportLoading(false);
    }
  };

  const exportPaymentAnalysisReport = () => {
    if (!dashboardData) return;
    
    setExportLoading(true);
    try {
      const paymentAnalysis = [];
      
      if (dashboardData.payment_analysis?.payment_timing) {
        dashboardData.payment_analysis.payment_timing.forEach(item => {
          paymentAnalysis.push({
            'Category': 'Payment Timing',
            'Type': item.payment_timing,
            'Bill Count': safeParseFloat(item.count),
            'Total Amount (PHP)': safeParseFloat(item.amount)
          });
        });
      }
      
      if (dashboardData.payment_analysis?.discount_penalty) {
        const dp = dashboardData.payment_analysis.discount_penalty;
        paymentAnalysis.push({
          'Category': 'Financial',
          'Type': 'Discounts Given',
          'Bill Count': safeParseFloat(dp.discount_count),
          'Total Amount (PHP)': safeParseFloat(dp.total_discounts_given)
        });
        paymentAnalysis.push({
          'Category': 'Financial',
          'Type': 'Penalties Collected',
          'Bill Count': safeParseFloat(dp.penalty_count),
          'Total Amount (PHP)': safeParseFloat(dp.total_penalties_collected)
        });
      }
      
      if (dashboardData.payment_analysis?.delinquency) {
        dashboardData.payment_analysis.delinquency.forEach(item => {
          paymentAnalysis.push({
            'Category': 'Delinquency',
            'Type': item.delinquency_range,
            'Bill Count': safeParseFloat(item.count),
            'Total Amount (PHP)': safeParseFloat(item.amount)
          });
        });
      }

      exportToExcel(paymentAnalysis, `RPT_Payment_Analysis_${selectedYear}`, 'Payment Analysis');
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting payment analysis');
    } finally {
      setExportLoading(false);
    }
  };

  const exportRecentActivitiesReport = () => {
    if (!dashboardData) return;
    
    setExportLoading(true);
    try {
      const allActivities = [];
      
      if (dashboardData.recent_activities?.payments) {
        dashboardData.recent_activities.payments.forEach(payment => {
          allActivities.push({
            'Activity Type': 'Payment',
            'Date': payment.payment_date,
            'Owner Name': payment.owner_name,
            'Receipt Number': payment.receipt_number,
            'Quarter': payment.quarter,
            'Year': payment.year,
            'Amount (PHP)': safeParseFloat(payment.amount),
            'Discount (PHP)': safeParseFloat(payment.discount_amount),
            'Penalty (PHP)': safeParseFloat(payment.penalty_amount),
            'Days Late': safeParseFloat(payment.days_late),
            'Reference Number': payment.reference_number,
            'Status': payment.payment_status
          });
        });
      }
      
      if (dashboardData.recent_activities?.registrations) {
        dashboardData.recent_activities.registrations.forEach(reg => {
          allActivities.push({
            'Activity Type': 'Registration',
            'Date': reg.created_at,
            'Owner Name': reg.owner_name,
            'Reference Number': reg.reference_number,
            'Lot Location': reg.lot_location,
            'Barangay': reg.barangay,
            'Land Count': safeParseFloat(reg.land_count),
            'Building Count': safeParseFloat(reg.building_count),
            'Status': reg.status
          });
        });
      }
      
      if (dashboardData.recent_activities?.overdue) {
        dashboardData.recent_activities.overdue.forEach(overdue => {
          allActivities.push({
            'Activity Type': 'Overdue',
            'Due Date': overdue.due_date,
            'Owner Name': overdue.owner_name,
            'Quarter': overdue.quarter,
            'Year': overdue.year,
            'Amount (PHP)': safeParseFloat(overdue.amount),
            'Days Late': safeParseFloat(overdue.days_late),
            'Reference Number': overdue.reference_number,
            'Barangay': overdue.barangay
          });
        });
      }

      exportToExcel(allActivities, `RPT_Recent_Activities_${selectedYear}`, 'Recent Activities');
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting recent activities');
    } finally {
      setExportLoading(false);
    }
  };

  const exportCompleteDashboardReport = () => {
    if (!dashboardData) return;
    
    setExportLoading(true);
    try {
      const wb = XLSX.utils.book_new();
      const dateStr = new Date().toISOString().split('T')[0];
      
      // 1. Summary Sheet
      const summaryData = [{
        'Year': selectedYear,
        'Total Properties': formatNumber(dashboardData.property_stats.total_registrations),
        'Active Owners': formatNumber(dashboardData.property_stats.active_owners),
        'Total Annual Tax': formatCurrency(dashboardData.tax_stats.annual?.total_annual_tax),
        'Collection Rate': formatPercent(effectiveCollectionRate),
        'Current Quarter': dashboardData.current_quarter,
        'Total Outstanding': formatCurrency(totalOutstanding),
        'Data Updated': dashboardData.timestamp
      }];
      
      const ws1 = XLSX.utils.json_to_sheet(summaryData);
      XLSX.utils.book_append_sheet(wb, ws1, 'Dashboard Summary');
      
      // 2. Quarterly Analysis Sheet
      const quarterlyData = dashboardData.quarterly_analysis.map(q => ({
        'Year': q.year,
        'Quarter': q.quarter,
        'Total Due (PHP)': safeParseFloat(q.total_due),
        'Collected (PHP)': safeParseFloat(q.collected),
        'Collection Rate (%)': safeParseFloat(q.collection_rate),
        'Paid Count': safeParseFloat(q.paid_count),
        'Overdue Amount (PHP)': safeParseFloat(q.overdue_amount),
        'Overdue Count': safeParseFloat(q.overdue_count),
        'Pending Amount (PHP)': safeParseFloat(q.pending_amount),
        'Pending Count': safeParseFloat(q.pending_count),
        'Average Days Late': safeParseFloat(q.avg_days_late),
        'Total Discounts (PHP)': safeParseFloat(q.total_discounts),
        'Total Penalties (PHP)': safeParseFloat(q.total_penalties)
      }));
      
      const ws2 = XLSX.utils.json_to_sheet(quarterlyData);
      XLSX.utils.book_append_sheet(wb, ws2, 'Quarterly Analysis');
      
      // 3. Top Barangays Sheet
      const barangayData = dashboardData.top_barangays.map(b => ({
        'Barangay': b.barangay,
        'District': b.district,
        'Property Count': safeParseFloat(b.property_count),
        'Unique Owners': safeParseFloat(b.unique_owners),
        'Total Annual Tax (PHP)': safeParseFloat(b.total_annual_tax),
        'Total Land Value (PHP)': safeParseFloat(b.total_land_value),
        'Total Building Value (PHP)': safeParseFloat(b.total_building_value),
        'Average Tax per Property (PHP)': safeParseFloat(b.avg_tax_per_property)
      }));
      
      const ws3 = XLSX.utils.json_to_sheet(barangayData);
      XLSX.utils.book_append_sheet(wb, ws3, 'Top Barangays');
      
      // 4. Payment Analysis Sheet
      const paymentAnalysis = [];
      
      if (dashboardData.payment_analysis?.payment_timing) {
        dashboardData.payment_analysis.payment_timing.forEach(item => {
          paymentAnalysis.push({
            'Category': 'Payment Timing',
            'Type': item.payment_timing,
            'Bill Count': safeParseFloat(item.count),
            'Total Amount (PHP)': safeParseFloat(item.amount)
          });
        });
      }
      
      if (dashboardData.payment_analysis?.discount_penalty) {
        const dp = dashboardData.payment_analysis.discount_penalty;
        paymentAnalysis.push({
          'Category': 'Financial',
          'Type': 'Discounts Given',
          'Bill Count': safeParseFloat(dp.discount_count),
          'Total Amount (PHP)': safeParseFloat(dp.total_discounts_given)
        });
        paymentAnalysis.push({
          'Category': 'Financial',
          'Type': 'Penalties Collected',
          'Bill Count': safeParseFloat(dp.penalty_count),
          'Total Amount (PHP)': safeParseFloat(dp.total_penalties_collected)
        });
      }
      
      if (dashboardData.payment_analysis?.delinquency) {
        dashboardData.payment_analysis.delinquency.forEach(item => {
          paymentAnalysis.push({
            'Category': 'Delinquency',
            'Type': item.delinquency_range,
            'Bill Count': safeParseFloat(item.count),
            'Total Amount (PHP)': safeParseFloat(item.amount)
          });
        });
      }
      
      const ws4 = XLSX.utils.json_to_sheet(paymentAnalysis);
      XLSX.utils.book_append_sheet(wb, ws4, 'Payment Analysis');
      
      // 5. Recent Activities Sheet
      const allActivities = [];
      
      if (dashboardData.recent_activities?.payments) {
        dashboardData.recent_activities.payments.forEach(payment => {
          allActivities.push({
            'Activity Type': 'Payment',
            'Date': payment.payment_date,
            'Owner Name': payment.owner_name,
            'Receipt Number': payment.receipt_number,
            'Quarter': payment.quarter,
            'Year': payment.year,
            'Amount (PHP)': safeParseFloat(payment.amount),
            'Discount (PHP)': safeParseFloat(payment.discount_amount),
            'Penalty (PHP)': safeParseFloat(payment.penalty_amount),
            'Days Late': safeParseFloat(payment.days_late),
            'Reference Number': payment.reference_number,
            'Status': payment.payment_status
          });
        });
      }
      
      if (dashboardData.recent_activities?.registrations) {
        dashboardData.recent_activities.registrations.forEach(reg => {
          allActivities.push({
            'Activity Type': 'Registration',
            'Date': reg.created_at,
            'Owner Name': reg.owner_name,
            'Reference Number': reg.reference_number,
            'Lot Location': reg.lot_location,
            'Barangay': reg.barangay,
            'Land Count': safeParseFloat(reg.land_count),
            'Building Count': safeParseFloat(reg.building_count),
            'Status': reg.status
          });
        });
      }
      
      if (dashboardData.recent_activities?.overdue) {
        dashboardData.recent_activities.overdue.forEach(overdue => {
          allActivities.push({
            'Activity Type': 'Overdue',
            'Due Date': overdue.due_date,
            'Owner Name': overdue.owner_name,
            'Quarter': overdue.quarter,
            'Year': overdue.year,
            'Amount (PHP)': safeParseFloat(overdue.amount),
            'Days Late': safeParseFloat(overdue.days_late),
            'Reference Number': overdue.reference_number,
            'Barangay': overdue.barangay
          });
        });
      }
      
      const ws5 = XLSX.utils.json_to_sheet(allActivities);
      XLSX.utils.book_append_sheet(wb, ws5, 'Recent Activities');
      
      XLSX.writeFile(wb, `RPT_Complete_Report_${selectedYear}_${dateStr}.xlsx`);
      
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting complete report');
    } finally {
      setExportLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="flex flex-col justify-center items-center h-screen">
        <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-blue-600 mb-4"></div>
        <p className="text-gray-600">Loading Real Property Tax Dashboard...</p>
        <p className="text-sm text-gray-400 mt-2">Fetching data for {selectedYear}</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="max-w-4xl mx-auto p-6">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6">
          <div className="flex items-center space-x-3 mb-4">
            <AlertCircle className="w-8 h-8 text-red-600" />
            <div>
              <h3 className="text-lg font-semibold text-red-600">Error Loading Dashboard</h3>
              <p className="text-red-600">{error}</p>
            </div>
          </div>
          <button 
            onClick={fetchDashboardData}
            className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center gap-2"
          >
            <RefreshCw className="w-4 h-4" />
            Try Again
          </button>
        </div>
      </div>
    );
  }

  if (!dashboardData) {
    return (
      <div className="text-center py-12">
        <Landmark className="w-16 h-16 text-gray-400 mx-auto mb-4" />
        <p className="text-gray-500">No dashboard data available for {selectedYear}</p>
        <button 
          onClick={fetchDashboardData}
          className="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2 mx-auto"
        >
          <RefreshCw className="w-4 h-4" />
          Load Dashboard
        </button>
      </div>
    );
  }

  // Extract data with defaults
  const {
    property_stats = {},
    tax_stats = {},
    collection_performance = {},
    property_distribution = {},
    quarterly_analysis = [],
    top_barangays = [],
    payment_analysis = {},
    recent_activities = {},
    current_quarter: dataCurrentQuarter,
    timestamp
  } = dashboardData;

  // Calculate key metrics with safe parsing
  const collectionRate = safeParseFloat(collection_performance.overall?.collection_rate);
  const totalAnnualTax = safeParseFloat(tax_stats.annual?.total_annual_tax);
  const quarterlyTarget = safeParseFloat(tax_stats.annual?.quarterly_target);
  const currentQuarterCollected = safeParseFloat(tax_stats.current_quarter?.current_quarter_paid);
  const totalOutstanding = safeParseFloat(tax_stats.outstanding?.total_outstanding);
  
  // Prepare chart data with safe parsing
  const monthlyCollectionData = collection_performance.monthly || [];
  const quarterlyData = tax_stats.quarterly || [];
  const propertyTypeData = property_distribution.property_types || [];
  const topBarangaysData = top_barangays.slice(0, 8);
  const paymentTimingData = payment_analysis.payment_timing || [];

  // Calculate overall collection rate from quarterly analysis
  const overallCollection = quarterly_analysis.reduce((acc, q) => {
    return {
      total_due: acc.total_due + safeParseFloat(q.total_due),
      collected: acc.collected + safeParseFloat(q.collected)
    };
  }, { total_due: 0, collected: 0 });

  const effectiveCollectionRate = overallCollection.total_due > 0 
    ? (overallCollection.collected / overallCollection.total_due) * 100 
    : 0;

  // Get activities for active tab
  const getActivitiesForTab = () => {
    switch(activeTab) {
      case 'payments':
        return recent_activities.payments || [];
      case 'registrations':
        return recent_activities.registrations || [];
      case 'overdue':
        return recent_activities.overdue || [];
      default:
        return recent_activities.payments || [];
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-6">
        <div className="max-w-7xl mx-auto">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
              <div className="flex items-center gap-3 mb-2">
                <Landmark className="w-8 h-8" />
                <h1 className="text-2xl sm:text-3xl font-bold">Real Property Tax Collection Dashboard</h1>
              </div>
              <p className="text-blue-100 flex items-center gap-2">
                <CalendarDays className="w-4 h-4" />
                {dataCurrentQuarter || currentQuarter} {selectedYear} • 
                {timestamp ? new Date(timestamp).toLocaleDateString('en-PH', { 
                  month: 'long', 
                  day: 'numeric', 
                  year: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit'
                }) : 'Live'}
              </p>
            </div>
            
            <div className="flex flex-wrap gap-2 items-center">
              {/* Year Selection Dropdown */}
              <div className="relative">
                <button
                  onClick={() => setYearDropdownOpen(!yearDropdownOpen)}
                  className="flex items-center gap-2 px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg transition-colors"
                >
                  <Calendar className="w-4 h-4" />
                  <span>Year: {selectedYear}</span>
                  {yearDropdownOpen ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                </button>
                
                {yearDropdownOpen && (
                  <div className="absolute top-full right-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                    <div className="py-1">
                      {availableYears.map(year => (
                        <button
                          key={year}
                          onClick={() => {
                            setSelectedYear(year);
                            setYearDropdownOpen(false);
                          }}
                          className={`w-full text-left px-4 py-2 hover:bg-blue-50 transition-colors ${
                            selectedYear === year 
                              ? 'bg-blue-100 text-blue-700 font-medium' 
                              : 'text-gray-700'
                          }`}
                        >
                          <div className="flex items-center justify-between">
                            <span>{year}</span>
                            {selectedYear === year && (
                              <CheckCircle className="w-4 h-4 text-blue-600" />
                            )}
                          </div>
                        </button>
                      ))}
                    </div>
                  </div>
                )}
              </div>
              
              <button
                onClick={fetchDashboardData}
                className="flex items-center gap-2 px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg transition-colors"
              >
                <RefreshCw className="w-4 h-4" />
                Refresh
              </button>
              <button
                onClick={exportCompleteDashboardReport}
                disabled={exportLoading}
                className="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors disabled:opacity-50"
              >
                {exportLoading ? (
                  <>
                    <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div>
                    <span>Exporting...</span>
                  </>
                ) : (
                  <>
                    <FileSpreadsheet className="w-4 h-4" />
                    <span>Export All</span>
                  </>
                )}
              </button>
            </div>
          </div>
          
          {/* Available Years Quick Select */}
          <div className="mt-4 flex flex-wrap gap-2">
            {availableYears.map(year => (
              <button
                key={year}
                onClick={() => setSelectedYear(year)}
                className={`px-3 py-1 text-sm rounded-full transition-colors ${
                  selectedYear === year
                    ? 'bg-white text-blue-700 font-medium'
                    : 'bg-white/20 text-white hover:bg-white/30'
                }`}
              >
                {year}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
        {/* Year Info Banner */}
        <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-blue-100 rounded-lg">
                <Calendar className="w-5 h-5 text-blue-600" />
              </div>
              <div>
                <h3 className="font-semibold text-blue-800">Viewing Data for {selectedYear}</h3>
                <p className="text-sm text-blue-600">
                  {quarterly_analysis.length > 0 
                    ? `Showing ${quarterly_analysis.length} quarters of tax collection data` 
                    : 'No quarterly data available for this year'}
                </p>
              </div>
            </div>
            <div className="text-sm text-blue-700">
              {availableYears.length > 1 && (
                <p>Available years: {availableYears.join(', ')}</p>
              )}
            </div>
          </div>
        </div>

        {/* Export Options Bar */}
        {exportLoading && (
          <div className="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <div className="flex items-center gap-2">
              <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-blue-600"></div>
              <span className="text-sm text-blue-600">Preparing Excel export for {selectedYear}...</span>
            </div>
          </div>
        )}

        <div className="bg-white rounded-xl border border-gray-200 p-4">
          <div className="flex flex-wrap gap-2">
            <button
              onClick={exportQuarterlyReport}
              disabled={exportLoading || quarterly_analysis.length === 0}
              className="flex items-center gap-2 px-3 py-2 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 text-sm disabled:opacity-50"
            >
              <Calendar className="w-4 h-4" />
              Quarterly Report
            </button>
            <button
              onClick={exportTopBarangaysReport}
              disabled={exportLoading || top_barangays.length === 0}
              className="flex items-center gap-2 px-3 py-2 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 text-sm disabled:opacity-50"
            >
              <MapPin className="w-4 h-4" />
              Barangays Report
            </button>
            <button
              onClick={exportPaymentAnalysisReport}
              disabled={exportLoading || !payment_analysis}
              className="flex items-center gap-2 px-3 py-2 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 text-sm disabled:opacity-50"
            >
              <CreditCard className="w-4 h-4" />
              Payment Analysis
            </button>
            <button
              onClick={exportRecentActivitiesReport}
              disabled={exportLoading || !recent_activities}
              className="flex items-center gap-2 px-3 py-2 bg-yellow-50 text-yellow-700 rounded-lg hover:bg-yellow-100 text-sm disabled:opacity-50"
            >
              <Activity className="w-4 h-4" />
              Recent Activities
            </button>
            <button
              onClick={exportCompleteDashboardReport}
              disabled={exportLoading}
              className="flex items-center gap-2 px-3 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 text-sm disabled:opacity-50"
            >
              <Database className="w-4 h-4" />
              Complete Report
            </button>
          </div>
        </div>

        {/* Top Key Metrics */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Collection Performance */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-blue-50 rounded-lg">
                <Target className="w-6 h-6 text-blue-600" />
              </div>
              <span className={`text-sm px-2 py-1 rounded-full ${getProgressColor(effectiveCollectionRate)} text-white`}>
                {formatPercent(effectiveCollectionRate)}
              </span>
            </div>
            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-1">Collection Rate</h3>
            <p className="text-2xl font-bold text-gray-900 mb-2">
              {formatCurrency(overallCollection.collected)}
            </p>
            <div className="text-xs text-gray-500">
              <div className="flex justify-between mb-1">
                <span>Target:</span>
                <span className="font-medium">{formatCurrency(overallCollection.total_due)}</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div 
                  className={`h-2 rounded-full ${getProgressColor(effectiveCollectionRate)}`}
                  style={{ width: `${Math.min(effectiveCollectionRate, 100)}%` }}
                ></div>
              </div>
            </div>
          </div>

          {/* Annual Tax Assessment */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-green-50 rounded-lg">
                <Calculator className="w-6 h-6 text-green-600" />
              </div>
              <span className="text-sm px-2 py-1 bg-green-100 text-green-800 rounded-full">
                {selectedYear} Tax
              </span>
            </div>
            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-1">Total Assessment</h3>
            <p className="text-2xl font-bold text-gray-900 mb-2">{formatCurrency(totalAnnualTax)}</p>
            <div className="text-xs text-gray-500 space-y-1">
              <div className="flex justify-between">
                <span className="flex items-center gap-1">
                  <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                  Residential:
                </span>
                <span>{formatCurrency(tax_stats.annual?.residential_tax)}</span>
              </div>
              <div className="flex justify-between">
                <span className="flex items-center gap-1">
                  <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                  Commercial:
                </span>
                <span>{formatCurrency(tax_stats.annual?.commercial_tax)}</span>
              </div>
            </div>
          </div>

          {/* Current Quarter Performance */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-yellow-50 rounded-lg">
                <Calendar className="w-6 h-6 text-yellow-600" />
              </div>
              <span className={`text-sm px-2 py-1 rounded-full ${
                (currentQuarterCollected / quarterlyTarget) >= 0.8 ? 'bg-green-100 text-green-800' :
                (currentQuarterCollected / quarterlyTarget) >= 0.6 ? 'bg-yellow-100 text-yellow-800' :
                'bg-red-100 text-red-800'
              }`}>
                {dataCurrentQuarter || currentQuarter} Progress
              </span>
            </div>
            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-1">Current Quarter</h3>
            <p className="text-2xl font-bold text-gray-900 mb-2">{formatCurrency(currentQuarterCollected)}</p>
            <div className="text-xs text-gray-500">
              <div className="flex justify-between mb-1">
                <span>Target:</span>
                <span className="font-medium">{formatCurrency(quarterlyTarget)}</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div 
                  className={`h-2 rounded-full ${
                    (currentQuarterCollected / quarterlyTarget) >= 0.8 ? 'bg-green-500' :
                    (currentQuarterCollected / quarterlyTarget) >= 0.6 ? 'bg-yellow-500' :
                    'bg-red-500'
                  }`}
                  style={{ width: `${Math.min((currentQuarterCollected / quarterlyTarget) * 100, 100)}%` }}
                ></div>
              </div>
            </div>
          </div>

          {/* Outstanding Balance */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-red-50 rounded-lg">
                <AlertTriangle className="w-6 h-6 text-red-600" />
              </div>
              <span className="text-sm px-2 py-1 bg-red-100 text-red-800 rounded-full">
                Delinquent
              </span>
            </div>
            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-1">Outstanding Balance</h3>
            <p className="text-2xl font-bold text-gray-900 mb-2">{formatCurrency(totalOutstanding)}</p>
            <div className="text-xs text-gray-500 space-y-1">
              <div className="flex justify-between">
                <span>Pending:</span>
                <span>{formatCurrency(tax_stats.outstanding?.pending_balance)}</span>
              </div>
              <div className="flex justify-between">
                <span>Overdue:</span>
                <span>{formatCurrency(tax_stats.outstanding?.overdue_balance)}</span>
              </div>
              <div className="flex justify-between font-medium">
                <span>Total Bills:</span>
                <span>{formatNumber(tax_stats.outstanding?.outstanding_bills)}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Charts Section */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Quarterly Collection Performance */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex justify-between items-center mb-6">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                <Activity className="w-5 h-5 text-blue-600" />
                Quarterly Collection Performance {selectedYear}
              </h3>
              <button
                onClick={exportQuarterlyReport}
                disabled={exportLoading || quarterly_analysis.length === 0}
                className="flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700 disabled:opacity-50"
              >
                <Download className="w-4 h-4" />
                Export
              </button>
            </div>
            <div className="h-72">
              {quarterlyData.length > 0 ? (
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={quarterlyData.map(q => ({
                    quarter: q.quarter,
                    total_due: safeParseFloat(q.total_due),
                    total_paid: safeParseFloat(q.total_paid),
                    total_overdue: safeParseFloat(q.total_overdue),
                    total_pending: safeParseFloat(q.total_pending),
                    collection_rate: safeParseFloat(q.total_due) > 0 
                      ? (safeParseFloat(q.total_paid) / safeParseFloat(q.total_due)) * 100 
                      : 0
                  }))}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                    <XAxis dataKey="quarter" />
                    <YAxis 
                      tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
                    />
                    <Tooltip 
                      formatter={(value, name) => {
                        const formattedValue = formatCurrency(value);
                        const label = name === 'collection_rate' ? 'Collection Rate' : 
                                     name === 'total_due' ? 'Total Due' :
                                     name === 'total_paid' ? 'Paid' :
                                     name === 'total_overdue' ? 'Overdue' :
                                     name === 'total_pending' ? 'Pending' : name;
                        return [name === 'collection_rate' ? `${value.toFixed(1)}%` : formattedValue, label];
                      }}
                      labelFormatter={(label) => `Quarter: ${label}`}
                    />
                    <Legend />
                    <Bar dataKey="total_paid" fill="#10B981" name="Paid" radius={[4, 4, 0, 0]} />
                    <Bar dataKey="total_overdue" fill="#EF4444" name="Overdue" radius={[4, 4, 0, 0]} />
                    <Bar dataKey="total_pending" fill="#F59E0B" name="Pending" radius={[4, 4, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              ) : (
                <div className="flex flex-col items-center justify-center h-full text-gray-400">
                  <BarChart3 className="w-12 h-12 mb-2" />
                  <p>No quarterly data available for {selectedYear}</p>
                </div>
              )}
            </div>
          </div>

          {/* Top Barangays Revenue */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex justify-between items-center mb-6">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                <MapPin className="w-5 h-5 text-purple-600" />
                Top Barangays by Tax Revenue {selectedYear}
              </h3>
              <button
                onClick={exportTopBarangaysReport}
                disabled={exportLoading || top_barangays.length === 0}
                className="flex items-center gap-1 text-sm text-purple-600 hover:text-purple-700 disabled:opacity-50"
              >
                <Download className="w-4 h-4" />
                Export
              </button>
            </div>
            <div className="h-72">
              {topBarangaysData.length > 0 ? (
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart 
                    data={topBarangaysData.map(b => ({
                      name: b.barangay,
                      revenue: safeParseFloat(b.total_annual_tax),
                      properties: safeParseFloat(b.property_count),
                      avg_tax: safeParseFloat(b.avg_tax_per_property)
                    }))}
                    layout="vertical"
                  >
                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" horizontal={false} />
                    <XAxis 
                      type="number" 
                      tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
                    />
                    <YAxis 
                      type="category" 
                      dataKey="name" 
                      width={100}
                      tick={{ fontSize: 12 }}
                    />
                    <Tooltip 
                      formatter={(value, name) => {
                        if (name === 'revenue') return [formatCurrency(value), 'Annual Tax'];
                        if (name === 'avg_tax') return [formatCurrency(value), 'Avg Tax per Property'];
                        return [value, name];
                      }}
                      labelFormatter={(label) => `Barangay: ${label}`}
                    />
                    <Legend />
                    <Bar dataKey="revenue" fill="#8B5CF6" name="Annual Tax" radius={[0, 4, 4, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              ) : (
                <div className="flex flex-col items-center justify-center h-full text-gray-400">
                  <MapPin className="w-12 h-12 mb-2" />
                  <p>No barangay data available for {selectedYear}</p>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Payment Analysis Section */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Payment Timing */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex justify-between items-center mb-6">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                <Timer className="w-5 h-5 text-blue-600" />
                Payment Timing Analysis {selectedYear}
              </h3>
              <button
                onClick={exportPaymentAnalysisReport}
                disabled={exportLoading || !payment_analysis}
                className="flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700 disabled:opacity-50"
              >
                <Download className="w-4 h-4" />
                Export
              </button>
            </div>
            <div className="space-y-4">
              {paymentTimingData.map((item, index) => (
                <div key={index} className={`p-4 rounded-lg ${
                  item.payment_timing === 'On Time' ? 'bg-green-50' :
                  item.payment_timing === 'Late Payment' ? 'bg-yellow-50' :
                  'bg-red-50'
                }`}>
                  <div className="flex items-center justify-between mb-2">
                    <span className={`font-medium ${
                      item.payment_timing === 'On Time' ? 'text-green-700' :
                      item.payment_timing === 'Late Payment' ? 'text-yellow-700' :
                      'text-red-700'
                    }`}>
                      {item.payment_timing}
                    </span>
                    <span className={`text-sm px-2 py-1 rounded-full ${
                      item.payment_timing === 'On Time' ? 'bg-green-100 text-green-800' :
                      item.payment_timing === 'Late Payment' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-red-100 text-red-800'
                    }`}>
                      {safeParseFloat(item.count)} bills
                    </span>
                  </div>
                  <p className={`text-2xl font-bold ${
                    item.payment_timing === 'On Time' ? 'text-green-600' :
                    item.payment_timing === 'Late Payment' ? 'text-yellow-600' :
                    'text-red-600'
                  }`}>
                    {formatCurrency(item.amount)}
                  </p>
                </div>
              ))}
            </div>
          </div>

          {/* Discounts vs Penalties */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex justify-between items-center mb-6">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                <ArrowRightLeft className="w-5 h-5 text-purple-600" />
                Discounts vs Penalties {selectedYear}
              </h3>
              <button
                onClick={exportPaymentAnalysisReport}
                disabled={exportLoading || !payment_analysis}
                className="flex items-center gap-1 text-sm text-purple-600 hover:text-purple-700 disabled:opacity-50"
              >
                <Download className="w-4 h-4" />
                Export
              </button>
            </div>
            <div className="space-y-6">
              <div>
                <div className="flex items-center justify-between mb-2">
                  <span className="font-medium text-green-700">Discounts Given</span>
                  <span className="text-sm bg-green-100 text-green-800 px-2 py-1 rounded-full">
                    {safeParseFloat(payment_analysis.discount_penalty?.discount_count)} bills
                  </span>
                </div>
                <p className="text-3xl font-bold text-green-600 mb-4">
                  {formatCurrency(payment_analysis.discount_penalty?.total_discounts_given)}
                </p>
                <div className="w-full bg-gray-200 rounded-full h-2">
                  <div 
                    className="h-2 rounded-full bg-green-500"
                    style={{ width: '100%' }}
                  ></div>
                </div>
              </div>
              
              <div>
                <div className="flex items-center justify-between mb-2">
                  <span className="font-medium text-red-700">Penalties Collected</span>
                  <span className="text-sm bg-red-100 text-red-800 px-2 py-1 rounded-full">
                    {safeParseFloat(payment_analysis.discount_penalty?.penalty_count)} bills
                  </span>
                </div>
                <p className="text-3xl font-bold text-red-600 mb-4">
                  {formatCurrency(payment_analysis.discount_penalty?.total_penalties_collected)}
                </p>
                <div className="w-full bg-gray-200 rounded-full h-2">
                  <div 
                    className="h-2 rounded-full bg-red-500"
                    style={{ width: '100%' }}
                  ></div>
                </div>
              </div>
            </div>
          </div>

          {/* Quarterly Analysis Summary */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex justify-between items-center mb-6">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                <Calendar className="w-5 h-5 text-blue-600" />
                {selectedYear} Quarterly Summary
              </h3>
              <button
                onClick={exportQuarterlyReport}
                disabled={exportLoading || quarterly_analysis.length === 0}
                className="flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700 disabled:opacity-50"
              >
                <Download className="w-4 h-4" />
                Export
              </button>
            </div>
            <div className="space-y-4">
              {quarterly_analysis.slice().reverse().map((quarter, index) => (
                <div key={index} className="p-3 bg-gray-50 rounded-lg">
                  <div className="flex justify-between items-center mb-1">
                    <span className="font-medium">{quarter.quarter} {quarter.year}</span>
                    <span className={`text-sm px-2 py-1 rounded-full ${
                      safeParseFloat(quarter.collection_rate) >= 90 ? 'bg-green-100 text-green-800' :
                      safeParseFloat(quarter.collection_rate) >= 60 ? 'bg-yellow-100 text-yellow-800' :
                      'bg-red-100 text-red-800'
                    }`}>
                      {formatPercent(quarter.collection_rate)}
                    </span>
                  </div>
                  <div className="flex justify-between text-sm text-gray-600">
                    <span>Collected: {formatCurrency(quarter.collected)}</span>
                    <span>Due: {formatCurrency(quarter.total_due)}</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                    <div 
                      className={`h-1.5 rounded-full ${
                        safeParseFloat(quarter.collection_rate) >= 90 ? 'bg-green-500' :
                        safeParseFloat(quarter.collection_rate) >= 60 ? 'bg-yellow-500' :
                        'bg-red-500'
                      }`}
                      style={{ width: `${Math.min(safeParseFloat(quarter.collection_rate), 100)}%` }}
                    ></div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Recent Activities */}
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm">
          <div className="p-5 border-b border-gray-200">
            <div className="flex justify-between items-center">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                <Activity className="w-5 h-5 text-gray-600" />
                Recent Activities {selectedYear}
              </h3>
              <div className="flex gap-2">
                <button
                  onClick={() => setActiveTab('payments')}
                  className={`text-sm px-3 py-1 rounded transition-colors ${
                    activeTab === 'payments' 
                      ? 'bg-blue-100 text-blue-700' 
                      : 'text-gray-600 hover:text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  Payments
                </button>
                <button
                  onClick={() => setActiveTab('registrations')}
                  className={`text-sm px-3 py-1 rounded transition-colors ${
                    activeTab === 'registrations' 
                      ? 'bg-blue-100 text-blue-700' 
                      : 'text-gray-600 hover:text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  Registrations
                </button>
                <button
                  onClick={() => setActiveTab('overdue')}
                  className={`text-sm px-3 py-1 rounded transition-colors ${
                    activeTab === 'overdue' 
                      ? 'bg-blue-100 text-blue-700' 
                      : 'text-gray-600 hover:text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  Overdue
                </button>
                <button
                  onClick={exportRecentActivitiesReport}
                  disabled={exportLoading || !recent_activities}
                  className="flex items-center gap-1 text-sm text-gray-600 hover:text-gray-700 px-3 py-1 rounded hover:bg-gray-50 disabled:opacity-50"
                >
                  <Download className="w-4 h-4" />
                  Export
                </button>
              </div>
            </div>
          </div>
          <div className="p-5">
            <div className="space-y-4">
              {getActivitiesForTab().slice(0, 5).map((activity, index) => (
                <div key={index} className={`flex items-center justify-between p-4 rounded-lg hover:bg-gray-50 transition-colors ${
                  activeTab === 'overdue' ? 'bg-red-50' : 'bg-gray-50'
                }`}>
                  <div className="flex items-center gap-3">
                    <div className={`p-2 rounded-lg ${
                      activeTab === 'payments' ? 'bg-green-100' :
                      activeTab === 'registrations' ? 'bg-blue-100' :
                      'bg-red-100'
                    }`}>
                      {activeTab === 'payments' ? (
                        <CheckCircle className="w-5 h-5 text-green-600" />
                      ) : activeTab === 'registrations' ? (
                        <FileText className="w-5 h-5 text-blue-600" />
                      ) : (
                        <AlertCircle className="w-5 h-5 text-red-600" />
                      )}
                    </div>
                    <div>
                      <h4 className="font-medium">{activity.owner_name}</h4>
                      <p className="text-sm text-gray-500">
                        {activeTab === 'payments' && `Payment #${activity.receipt_number} • ${activity.quarter} ${activity.year}`}
                        {activeTab === 'registrations' && `Registration #${activity.reference_number} • ${activity.barangay}`}
                        {activeTab === 'overdue' && `${activity.days_late} days overdue • ${activity.quarter} ${activity.year}`}
                      </p>
                    </div>
                  </div>
                  <div className="text-right">
                    <p className={`font-bold text-lg ${
                      activeTab === 'payments' ? 'text-green-600' :
                      activeTab === 'overdue' ? 'text-red-600' :
                      'text-blue-600'
                    }`}>
                      {formatCurrency(activity.amount)}
                    </p>
                    {activeTab === 'payments' && (
                      <div className="flex items-center gap-2 text-sm text-gray-500">
                        {safeParseFloat(activity.discount_amount) > 0 && (
                          <span className="text-blue-600">
                            -{formatCurrency(activity.discount_amount)}
                          </span>
                        )}
                        {safeParseFloat(activity.penalty_amount) > 0 && (
                          <span className="text-red-600">
                            +{formatCurrency(activity.penalty_amount)}
                          </span>
                        )}
                      </div>
                    )}
                    {activeTab === 'overdue' && (
                      <p className="text-sm text-red-500">
                        Due: {new Date(activity.due_date).toLocaleDateString()}
                      </p>
                    )}
                  </div>
                </div>
              ))}
              
              {getActivitiesForTab().length === 0 && (
                <div className="text-center py-8 text-gray-400">
                  <Activity className="w-12 h-12 mx-auto mb-2" />
                  <p>No {activeTab} activities available for {selectedYear}</p>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Property Statistics Footer */}
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <h3 className="font-semibold text-gray-900 mb-4">Property Portfolio Summary</h3>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="text-center p-4 bg-blue-50 rounded-lg">
              <p className="text-sm text-gray-600 mb-1">Total Properties</p>
              <p className="text-2xl font-bold text-gray-900">
                {formatNumber(property_stats.total_registrations)}
              </p>
              <p className="text-xs text-gray-500 mt-1">
                {formatNumber(property_stats.approved_properties)} approved
              </p>
            </div>
            <div className="text-center p-4 bg-green-50 rounded-lg">
              <p className="text-sm text-gray-600 mb-1">Property Owners</p>
              <p className="text-2xl font-bold text-gray-900">
                {formatNumber(property_stats.total_owners)}
              </p>
              <p className="text-xs text-gray-500 mt-1">
                {formatNumber(property_stats.active_owners)} active
              </p>
            </div>
            <div className="text-center p-4 bg-purple-50 rounded-lg">
              <p className="text-sm text-gray-600 mb-1">Buildings</p>
              <p className="text-2xl font-bold text-gray-900">
                {formatNumber(property_stats.active_buildings)}
              </p>
              <p className="text-xs text-gray-500 mt-1">
                on {formatNumber(property_stats.active_land_properties)} land parcels
              </p>
            </div>
            <div className="text-center p-4 bg-yellow-50 rounded-lg">
              <p className="text-sm text-gray-600 mb-1">Barangays Covered</p>
              <p className="text-2xl font-bold text-gray-900">
                {formatNumber(property_stats.barangays_covered)}
              </p>
              <p className="text-xs text-gray-500 mt-1">
                across all districts
              </p>
            </div>
          </div>
        </div>

        {/* Footer Note */}
        <div className="text-center text-sm text-gray-500 pt-4 border-t border-gray-200">
          <p>Real Property Tax Collection Dashboard • Year: {selectedYear} • Updated {timestamp ? new Date(timestamp).toLocaleTimeString() : 'Just now'}</p>
          <p className="text-xs text-gray-400 mt-1">
            Available years: {availableYears.join(', ')} • Switch between years using the year selector
          </p>
        </div>
      </div>
    </div>
  );
}