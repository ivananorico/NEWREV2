import React, { useState, useEffect, useCallback } from 'react';
import { 
  BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, 
  Tooltip, Legend, ResponsiveContainer
} from 'recharts';
import { 
  Building, DollarSign, Calendar, AlertCircle, 
  RefreshCw, MapPin, Download, 
  CheckCircle, Percent, CalendarDays,
  Banknote, AlertTriangle, ChevronDown,
  FileSpreadsheet, Database, Map,
  Activity, Grid3x3, BarChart as BarChartIcon, PieChart as PieIcon,
  Archive
} from 'lucide-react';
import * as XLSX from 'xlsx';

// Auto-detect environment
const getApiBase = () => {
  const isLocalhost = window.location.hostname === 'localhost' || 
                     window.location.hostname === '127.0.0.1';
  
  if (isLocalhost) {
    return 'http://localhost/revenue2/backend/Business/BusinessTaxDashboard';
  } else {
    return 'https://revenuetreasury.goserveph.com/backend/Business/BusinessTaxDashboard';
  }
};

const API_BASE = getApiBase();

export default function BusinessTaxDashboard() {
  // State variables
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [dashboardData, setDashboardData] = useState(null);
  const [exportLoading, setExportLoading] = useState(false);
  const [activeTab, setActiveTab] = useState('payments');
  const [selectedYear, setSelectedYear] = useState(() => new Date().getFullYear());
  const [availableYears, setAvailableYears] = useState([]);
  const [yearDropdownOpen, setYearDropdownOpen] = useState(false);
  const [viewMode, setViewMode] = useState('cards');
  
  // Current quarter
  const currentQuarter = (() => {
    const month = new Date().getMonth() + 1;
    if (month >= 1 && month <= 3) return 'Q1';
    if (month >= 4 && month <= 6) return 'Q2';
    if (month >= 7 && month <= 9) return 'Q3';
    return 'Q4';
  })();

  // Fetch available years - NO dependencies
  const fetchAvailableYears = useCallback(async () => {
    console.log('Fetching available years...');
    
    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 10000);
      
      const response = await fetch(
        `${API_BASE}/BusinessTaxDashboard.php?action=get_years`,
        {
          signal: controller.signal,
          headers: { 'Accept': 'application/json' }
        }
      );
      
      clearTimeout(timeoutId);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const yearsData = await response.json();
      console.log('Years response:', yearsData);
      
      let years = [];
      if (yearsData.success && yearsData.years && yearsData.years.length > 0) {
        years = yearsData.years.sort((a, b) => b - a);
      } else {
        const currentYear = new Date().getFullYear();
        years = [currentYear, currentYear - 1, currentYear - 2];
      }
      
      setAvailableYears(years);
      
    } catch (err) {
      console.error('Error fetching years:', err);
      setAvailableYears([new Date().getFullYear(), new Date().getFullYear() - 1]);
    }
  }, []); // NO dependencies

  // Memoized fetch function with proper error handling
  const fetchDashboardData = useCallback(async (year) => {
    console.log('Fetching dashboard data for year:', year);
    
    try {
      setLoading(true);
      setError(null);
      
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 15000);
      
      const response = await fetch(
        `${API_BASE}/BusinessTaxDashboard.php?action=dashboard&year=${year}`,
        {
          signal: controller.signal,
          headers: { 
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          }
        }
      );
      
      clearTimeout(timeoutId);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      console.log('Dashboard response:', data);
      
      if (!data.success) {
        throw new Error(data.error || data.message || 'Failed to load dashboard data');
      }
      
      const parsedData = parseNumbersInData(data.data);
      setDashboardData(parsedData);
      
    } catch (err) {
      console.error('Error fetching dashboard data:', err);
      setError(`Failed to load data: ${err.message}`);
    } finally {
      setLoading(false);
    }
  }, []);

  // Initialize - fetch years on mount
  useEffect(() => {
    console.log('Component mounted, fetching years...');
    fetchAvailableYears();
  }, []); // Empty dependency array - runs once

  // Fetch dashboard when selectedYear changes
  useEffect(() => {
    if (selectedYear) {
      console.log('Selected year changed to:', selectedYear);
      fetchDashboardData(selectedYear);
    }
  }, [selectedYear]); // Only depends on selectedYear

  // Parse numbers in data
  const parseNumbersInData = (data) => {
    if (!data) return data;
    
    const parsed = JSON.parse(JSON.stringify(data));
    
    const parseNumericFields = (obj) => {
      if (!obj) return obj;
      
      Object.keys(obj).forEach(key => {
        if (typeof obj[key] === 'string' && !isNaN(obj[key]) && obj[key] !== '') {
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

  // Format currency
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

  // Safe parse float
  const safeParseFloat = (value, defaultValue = 0) => {
    if (value === null || value === undefined || value === '') return defaultValue;
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return isNaN(num) ? defaultValue : num;
  };

  // Format number
  const formatNumber = (num) => {
    const parsedNum = safeParseFloat(num);
    return new Intl.NumberFormat('en-PH').format(parsedNum);
  };

  // Format percent
  const formatPercent = (value) => {
    const parsedValue = safeParseFloat(value);
    return `${parsedValue.toFixed(1)}%`;
  };

  // Export to Excel
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

  // Export barangay report
  const exportBarangayReport = () => {
    if (!dashboardData?.barangay_collection) return;
    
    setExportLoading(true);
    try {
      const totalCollection = dashboardData.barangay_collection.reduce((total, curr) => total + safeParseFloat(curr.total_collection), 0);
      const barangayData = dashboardData.barangay_collection.map(b => ({
        'Barangay': b.barangay,
        'Total Collection (PHP)': safeParseFloat(b.total_collection),
        'Number of Businesses': safeParseFloat(b.business_count),
        'Average Tax per Business (PHP)': safeParseFloat(b.avg_tax_per_business),
        'Percentage of Total (%)': totalCollection > 0 ? (safeParseFloat(b.total_collection) / totalCollection) * 100 : 0
      }));

      exportToExcel(barangayData, `Business_Barangay_Report_${selectedYear}`, 'Barangay Collection');
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting barangay report');
    } finally {
      setExportLoading(false);
    }
  };

  // Export quarterly report
  const exportQuarterlyReport = () => {
    if (!dashboardData?.quarterly_analysis) return;
    
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

      exportToExcel(quarterlyData, `Business_Quarterly_Report_${selectedYear}`, 'Quarterly Analysis');
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting quarterly report');
    } finally {
      setExportLoading(false);
    }
  };

  // Export complete dashboard report
  const exportCompleteDashboardReport = () => {
    if (!dashboardData) return;
    
    setExportLoading(true);
    try {
      const wb = XLSX.utils.book_new();
      const dateStr = new Date().toISOString().split('T')[0];
      
      // Summary Sheet
      const overallCollection = dashboardData.quarterly_analysis.reduce((acc, q) => {
        return {
          total_due: acc.total_due + safeParseFloat(q.total_due),
          collected: acc.collected + safeParseFloat(q.collected)
        };
      }, { total_due: 0, collected: 0 });
      
      const effectiveCollectionRate = overallCollection.total_due > 0 
        ? (overallCollection.collected / overallCollection.total_due) * 100 
        : 0;

      const summaryData = [{
        'Year': selectedYear,
        'Total Businesses': formatNumber(dashboardData.business_stats?.total_businesses),
        'Active Businesses': formatNumber(dashboardData.business_stats?.active_businesses),
        'Total Annual Tax': formatCurrency(dashboardData.tax_stats?.annual?.total_annual_tax),
        'Collection Rate': formatPercent(effectiveCollectionRate),
        'Current Quarter': dashboardData.current_quarter,
        'Total Outstanding': formatCurrency(dashboardData.tax_stats?.outstanding?.total_outstanding),
        'Data Updated': dashboardData.timestamp
      }];
      
      const ws1 = XLSX.utils.json_to_sheet(summaryData);
      XLSX.utils.book_append_sheet(wb, ws1, 'Dashboard Summary');
      
      XLSX.writeFile(wb, `Business_Tax_Complete_Report_${selectedYear}_${dateStr}.xlsx`);
      
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting complete report');
    } finally {
      setExportLoading(false);
    }
  };

  // Handle year change
  const handleYearChange = (year) => {
    console.log('Changing year to:', year);
    setSelectedYear(year);
    setYearDropdownOpen(false);
  };

  // Loading state
  if (loading && !dashboardData) {
    return (
      <div className="flex flex-col justify-center items-center h-screen bg-white">
        <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-gray-800 mb-4"></div>
        <p className="text-gray-600">Loading Business Tax Dashboard...</p>
        <p className="text-sm text-gray-400 mt-2">Fetching data for {selectedYear}</p>
      </div>
    );
  }

  // Error state
  if (error && !dashboardData) {
    return (
      <div className="max-w-4xl mx-auto p-6 bg-white">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6">
          <div className="flex items-center space-x-3 mb-4">
            <AlertCircle className="w-8 h-8 text-red-600" />
            <div>
              <h3 className="text-lg font-semibold text-red-600">Error Loading Dashboard</h3>
              <p className="text-red-600">{error}</p>
            </div>
          </div>
          <button 
            onClick={() => fetchDashboardData(selectedYear)}
            className="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 flex items-center gap-2"
          >
            <RefreshCw className="w-4 h-4" />
            Try Again
          </button>
        </div>
      </div>
    );
  }

  // No data state
  if (!dashboardData) {
    return (
      <div className="text-center py-12 bg-white">
        <Building className="w-16 h-16 text-gray-400 mx-auto mb-4" />
        <p className="text-gray-500">No dashboard data available for {selectedYear}</p>
        <button 
          onClick={() => fetchDashboardData(selectedYear)}
          className="mt-4 px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 flex items-center gap-2 mx-auto"
        >
          <RefreshCw className="w-4 h-4" />
          Load Dashboard
        </button>
      </div>
    );
  }

  // Extract data with defaults
  const {
    business_stats = {},
    tax_stats = {},
    quarterly_analysis = [],
    business_types = [],
    top_taxpayers = [],
    barangay_collection = [],
    overdue_taxes = [],
    recent_payments = [],
    config = {},
    yearly_summary = {},
    current_quarter: dataCurrentQuarter,
    timestamp,
    available_years: dataAvailableYears = []
  } = dashboardData;

  // Calculate key metrics
  const overallCollection = quarterly_analysis.reduce((acc, q) => {
    return {
      total_due: acc.total_due + safeParseFloat(q.total_due),
      collected: acc.collected + safeParseFloat(q.collected)
    };
  }, { total_due: 0, collected: 0 });

  const effectiveCollectionRate = overallCollection.total_due > 0 
    ? (overallCollection.collected / overallCollection.total_due) * 100 
    : 0;

  const totalAnnualTax = safeParseFloat(tax_stats.annual?.total_annual_tax);
  const quarterlyTarget = safeParseFloat(tax_stats.annual?.total_annual_tax) / 4;
  const currentQuarterCollected = safeParseFloat(tax_stats.current_quarter?.current_quarter_paid);
  const totalOutstanding = safeParseFloat(tax_stats.outstanding?.total_outstanding);

  // Prepare chart data
  const quarterlyChartData = quarterly_analysis.map(q => ({
    quarter: q.quarter,
    total_due: safeParseFloat(q.total_due),
    collected: safeParseFloat(q.collected),
    overdue_amount: safeParseFloat(q.overdue_amount),
    pending_amount: safeParseFloat(q.pending_amount),
    collection_rate: safeParseFloat(q.collection_rate)
  }));

  const businessTypeData = business_types.map(b => ({
    name: b.business_type,
    value: safeParseFloat(b.count),
    percentage: safeParseFloat(b.percentage)
  }));

  const barangaysData = barangay_collection.map(b => ({
    name: b.barangay,
    revenue: safeParseFloat(b.total_collection),
    businesses: safeParseFloat(b.business_count),
    avg_tax: safeParseFloat(b.avg_tax_per_business)
  }));

  // Sort barangays by revenue (highest first)
  const sortedBarangays = [...barangaysData].sort((a, b) => b.revenue - a.revenue);
  const topBarangaysData = sortedBarangays.slice(0, 5);

  const formattedDate = timestamp 
    ? new Date(timestamp).toLocaleDateString('en-PH', { 
        month: 'long', 
        day: 'numeric', 
        year: 'numeric'
      })
    : 'Today';

  const formattedTime = timestamp
    ? new Date(timestamp).toLocaleTimeString('en-PH', {
        hour: '2-digit',
        minute: '2-digit'
      })
    : 'Now';

  const getActivitiesForTab = () => {
    switch(activeTab) {
      case 'payments':
        return recent_payments || [];
      case 'overdue':
        return overdue_taxes || [];
      default:
        return recent_payments || [];
    }
  };

  // Colors for charts
  const COLORS = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'];

  return (
    <div className="min-h-screen bg-white">
      {/* Header */}
      <div className="border-b border-gray-200 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
              <h1 className="text-2xl font-bold text-gray-900 mb-1">
                Business Tax Collection Dashboard
              </h1>
              <div className="flex items-center gap-3 text-sm text-gray-500">
                <div className="flex items-center gap-1">
                  <Calendar className="w-4 h-4" />
                  <span>{dataCurrentQuarter || currentQuarter} {selectedYear} • {formattedDate} at {formattedTime}</span>
                </div>
              </div>
            </div>
            
            <div className="flex flex-wrap gap-3 items-center">
              {/* Year Selection */}
              <div className="relative">
                <button
                  onClick={() => setYearDropdownOpen(!yearDropdownOpen)}
                  className="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700"
                >
                  <Calendar className="w-4 h-4" />
                  <span>Year: {selectedYear}</span>
                  <ChevronDown className="w-4 h-4" />
                </button>
                
                {yearDropdownOpen && (
                  <div className="absolute top-full right-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                    <div className="py-1 max-h-60 overflow-y-auto">
                      {(dataAvailableYears.length > 0 ? dataAvailableYears : availableYears).map(year => (
                        <button
                          key={year}
                          onClick={() => handleYearChange(year)}
                          className={`w-full text-left px-4 py-2 hover:bg-gray-50 transition-colors ${
                            selectedYear === year 
                              ? 'bg-gray-100 text-gray-900 font-medium' 
                              : 'text-gray-700'
                          }`}
                        >
                          <div className="flex items-center justify-between">
                            <span>{year}</span>
                            {selectedYear === year && (
                              <CheckCircle className="w-4 h-4 text-gray-600" />
                            )}
                          </div>
                        </button>
                      ))}
                    </div>
                  </div>
                )}
              </div>
              
              {/* Refresh Button */}
              <button
                onClick={() => fetchDashboardData(selectedYear)}
                className="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700"
              >
                <RefreshCw className="w-4 h-4" />
                Refresh
              </button>
              
              {/* Export Button */}
              <button
                onClick={exportCompleteDashboardReport}
                disabled={exportLoading}
                className="flex items-center gap-2 px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-black disabled:opacity-50"
              >
                {exportLoading ? (
                  <>
                    <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div>
                    <span>Exporting...</span>
                  </>
                ) : (
                  <>
                    <Download className="w-4 h-4" />
                    <span>Export All</span>
                  </>
                )}
              </button>
            </div>
          </div>
          
          {/* Available Years Quick Select */}
          <div className="mt-4 flex flex-wrap gap-2">
            {(dataAvailableYears.length > 0 ? dataAvailableYears : availableYears).map(year => (
              <button
                key={year}
                onClick={() => handleYearChange(year)}
                className={`px-3 py-1 text-sm rounded-lg transition-colors border ${
                  selectedYear === year
                    ? 'bg-gray-900 text-white border-gray-900'
                    : 'text-gray-700 border-gray-300 hover:bg-gray-50'
                }`}
              >
                {year}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Export Options Bar */}
        {exportLoading && (
          <div className="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <div className="flex items-center gap-2">
              <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-blue-600"></div>
              <span className="text-sm text-blue-600">Preparing Excel export for {selectedYear}...</span>
            </div>
          </div>
        )}

        <div className="bg-white border border-gray-200 rounded-lg p-4">
          <div className="flex flex-wrap gap-2">
            <button
              onClick={exportQuarterlyReport}
              disabled={exportLoading || quarterly_analysis.length === 0}
              className="flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm disabled:opacity-50"
            >
              <Calendar className="w-4 h-4" />
              Quarterly Report
            </button>
            <button
              onClick={exportBarangayReport}
              disabled={exportLoading || barangay_collection.length === 0}
              className="flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm disabled:opacity-50"
            >
              <MapPin className="w-4 h-4" />
              Barangay Report
            </button>
            <button
              onClick={exportCompleteDashboardReport}
              disabled={exportLoading}
              className="flex items-center gap-2 px-3 py-2 bg-gray-900 text-white rounded-lg hover:bg-black text-sm disabled:opacity-50"
            >
              <Database className="w-4 h-4" />
              Complete Report
            </button>
          </div>
        </div>

        {/* Key Metrics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {/* Collection Rate Card */}
          <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-blue-50 rounded-lg">
                <Percent className="w-6 h-6 text-blue-600" />
              </div>
              <span className={`text-sm px-3 py-1 rounded-full ${
                effectiveCollectionRate >= 90 ? 'bg-green-100 text-green-800' :
                effectiveCollectionRate >= 75 ? 'bg-yellow-100 text-yellow-800' :
                'bg-red-100 text-red-800'
              }`}>
                {formatPercent(effectiveCollectionRate)}
              </span>
            </div>
            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-2">
              Collection Rate
            </h3>
            <p className="text-2xl font-bold text-gray-900 mb-4">
              {formatCurrency(overallCollection.collected)}
            </p>
            <div className="text-sm text-gray-600">
              <div className="flex justify-between mb-1">
                <span>Target:</span>
                <span className="font-medium">{formatCurrency(overallCollection.total_due)}</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div 
                  className={`h-2 rounded-full ${
                    effectiveCollectionRate >= 90 ? 'bg-green-500' :
                    effectiveCollectionRate >= 75 ? 'bg-yellow-500' :
                    'bg-red-500'
                  }`}
                  style={{ width: `${Math.min(effectiveCollectionRate, 100)}%` }}
                ></div>
              </div>
            </div>
          </div>

          {/* Annual Tax Card */}
          <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-green-50 rounded-lg">
                <DollarSign className="w-6 h-6 text-green-600" />
              </div>
              <span className="text-sm px-3 py-1 bg-gray-100 text-gray-800 rounded-full">
                {selectedYear} Assessment
              </span>
            </div>
            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-2">
              Total Assessment
            </h3>
            <p className="text-2xl font-bold text-gray-900 mb-4">{formatCurrency(totalAnnualTax)}</p>
            <div className="text-sm text-gray-600 space-y-2">
              <div className="flex justify-between">
                <span className="flex items-center gap-2">
                  <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                  Tax Amount:
                </span>
                <span>{formatCurrency(tax_stats.annual?.total_tax_amount)}</span>
              </div>
              <div className="flex justify-between">
                <span className="flex items-center gap-2">
                  <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                  Regulatory Fees:
                </span>
                <span>{formatCurrency(tax_stats.annual?.total_fees)}</span>
              </div>
            </div>
          </div>

          {/* Current Quarter Card */}
          <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-yellow-50 rounded-lg">
                <CalendarDays className="w-6 h-6 text-yellow-600" />
              </div>
              <span className={`text-sm px-3 py-1 rounded-full ${
                (currentQuarterCollected / quarterlyTarget) >= 0.8 ? 'bg-green-100 text-green-800' :
                (currentQuarterCollected / quarterlyTarget) >= 0.6 ? 'bg-yellow-100 text-yellow-800' :
                'bg-red-100 text-red-800'
              }`}>
                {dataCurrentQuarter || currentQuarter} Progress
              </span>
            </div>
            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-2">
              Current Quarter
            </h3>
            <p className="text-2xl font-bold text-gray-900 mb-4">{formatCurrency(currentQuarterCollected)}</p>
            <div className="text-sm text-gray-600">
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

          {/* Outstanding Balance Card */}
          <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-red-50 rounded-lg">
                <AlertTriangle className="w-6 h-6 text-red-600" />
              </div>
              <span className="text-sm px-3 py-1 bg-red-100 text-red-800 rounded-full">
                Delinquent
              </span>
            </div>
            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-2">
              Outstanding Balance
            </h3>
            <p className="text-2xl font-bold text-gray-900 mb-4">{formatCurrency(totalOutstanding)}</p>
            <div className="text-sm text-gray-600 space-y-2">
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

        {/* View Mode Toggle */}
        <div className="flex justify-end">
          <div className="inline-flex rounded-lg border border-gray-300 p-1">
            <button
              onClick={() => setViewMode('cards')}
              className={`px-4 py-2 text-sm rounded-md transition-colors ${
                viewMode === 'cards' 
                  ? 'bg-gray-900 text-white' 
                  : 'text-gray-700 hover:bg-gray-50'
              }`}
            >
              <div className="flex items-center gap-2">
                <Grid3x3 className="w-4 h-4" />
                Cards
              </div>
            </button>
            <button
              onClick={() => setViewMode('charts')}
              className={`px-4 py-2 text-sm rounded-md transition-colors ${
                viewMode === 'charts' 
                  ? 'bg-gray-900 text-white' 
                  : 'text-gray-700 hover:bg-gray-50'
              }`}
            >
              <div className="flex items-center gap-2">
                <BarChartIcon className="w-4 h-4" />
                Charts
              </div>
            </button>
          </div>
        </div>

        {/* Charts Section */}
        {viewMode === 'charts' && (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Quarterly Collection Chart */}
            <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                  <Activity className="w-5 h-5 text-gray-600" />
                  Quarterly Collection {selectedYear}
                </h3>
                <button
                  onClick={exportQuarterlyReport}
                  disabled={exportLoading || quarterly_analysis.length === 0}
                  className="text-sm text-gray-600 hover:text-gray-700 disabled:opacity-50"
                >
                  Export
                </button>
              </div>
              <div className="h-72">
                {quarterlyChartData.length > 0 ? (
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={quarterlyChartData}>
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
                                       name === 'collected' ? 'Paid' :
                                       name === 'overdue_amount' ? 'Overdue' :
                                       name === 'pending_amount' ? 'Pending' : name;
                          return [name === 'collection_rate' ? `${safeParseFloat(value).toFixed(1)}%` : formattedValue, label];
                        }}
                        labelFormatter={(label) => `Quarter: ${label}`}
                      />
                      <Legend />
                      <Bar dataKey="collected" fill="#4F46E5" name="Paid" radius={[4, 4, 0, 0]} />
                      <Bar dataKey="overdue_amount" fill="#EF4444" name="Overdue" radius={[4, 4, 0, 0]} />
                      <Bar dataKey="pending_amount" fill="#F59E0B" name="Pending" radius={[4, 4, 0, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="flex flex-col items-center justify-center h-full text-gray-400">
                    <BarChartIcon className="w-12 h-12 mb-2" />
                    <p>No quarterly data available for {selectedYear}</p>
                  </div>
                )}
              </div>
            </div>

            {/* Business Types Chart */}
            <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                  <PieIcon className="w-5 h-5 text-gray-600" />
                  Business Types Distribution
                </h3>
                <button
                  onClick={exportCompleteDashboardReport}
                  disabled={exportLoading}
                  className="text-sm text-gray-600 hover:text-gray-700 disabled:opacity-50"
                >
                  Export
                </button>
              </div>
              <div className="h-72">
                {businessTypeData.length > 0 ? (
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie
                        data={businessTypeData}
                        cx="50%"
                        cy="50%"
                        labelLine={false}
                        label={({ name, percent }) => `${name}: ${(percent * 100).toFixed(0)}%`}
                        outerRadius={80}
                        fill="#8884d8"
                        dataKey="value"
                      >
                        {businessTypeData.map((entry, index) => (
                          <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                        ))}
                      </Pie>
                      <Tooltip 
                        formatter={(value, name, props) => {
                          const percent = (props.payload.percentage || 0).toFixed(1);
                          return [`${value} businesses (${percent}%)`, 'Count'];
                        }}
                      />
                      <Legend />
                    </PieChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="flex flex-col items-center justify-center h-full text-gray-400">
                    <PieIcon className="w-12 h-12 mb-2" />
                    <p>No business type data available</p>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Cards View */}
        {viewMode === 'cards' && (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Quarterly Analysis Cards */}
            <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                  <Calendar className="w-5 h-5 text-gray-600" />
                  Quarterly Analysis {selectedYear}
                </h3>
                <button
                  onClick={exportQuarterlyReport}
                  disabled={exportLoading || quarterly_analysis.length === 0}
                  className="text-sm text-gray-600 hover:text-gray-700 disabled:opacity-50"
                >
                  Export
                </button>
              </div>
              <div className="space-y-4">
                {quarterly_analysis.map((quarter, index) => (
                  <div key={index} className="p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div className="flex justify-between items-center mb-2">
                      <span className="font-medium text-gray-900">{quarter.quarter} {quarter.year}</span>
                      <span className={`text-sm px-3 py-1 rounded-full ${
                        safeParseFloat(quarter.collection_rate) >= 90 ? 'bg-green-100 text-green-800' :
                        safeParseFloat(quarter.collection_rate) >= 60 ? 'bg-yellow-100 text-yellow-800' :
                        'bg-red-100 text-red-800'
                      }`}>
                        {formatPercent(quarter.collection_rate)}
                      </span>
                    </div>
                    <div className="grid grid-cols-2 gap-4 text-sm text-gray-600">
                      <div>
                        <p className="font-medium text-gray-500">Collected</p>
                        <p className="text-lg font-semibold text-gray-900">
                          {formatCurrency(quarter.collected)}
                        </p>
                      </div>
                      <div>
                        <p className="font-medium text-gray-500">Due</p>
                        <p className="text-lg font-semibold text-gray-900">
                          {formatCurrency(quarter.total_due)}
                        </p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Barangay Collection Cards */}
            <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                  <MapPin className="w-5 h-5 text-gray-600" />
                  Top Barangays {selectedYear}
                </h3>
                <button
                  onClick={exportBarangayReport}
                  disabled={exportLoading || barangay_collection.length === 0}
                  className="text-sm text-gray-600 hover:text-gray-700 disabled:opacity-50"
                >
                  Export
                </button>
              </div>
              <div className="space-y-4">
                {topBarangaysData.map((barangay, index) => {
                  const totalCollection = barangay_collection.reduce((total, b) => total + safeParseFloat(b.total_collection), 0);
                  const percentage = totalCollection > 0 
                    ? (barangay.revenue / totalCollection) * 100 
                    : 0;
                  
                  return (
                    <div key={index} className="p-4 border border-gray-200 rounded-lg">
                      <div className="flex justify-between items-center mb-2">
                        <span className="font-medium text-gray-900">{barangay.name}</span>
                        <span className={`text-sm px-3 py-1 rounded-full ${
                          index === 0 ? 'bg-yellow-100 text-yellow-800' :
                          index === 1 ? 'bg-gray-200 text-gray-800' :
                          index === 2 ? 'bg-orange-100 text-orange-800' :
                          'bg-blue-100 text-blue-800'
                        }`}>
                          #{index + 1}
                        </span>
                      </div>
                      <p className="text-2xl font-bold text-gray-900 mb-2">
                        {formatCurrency(barangay.revenue)}
                      </p>
                      <div className="flex justify-between text-sm text-gray-600">
                        <span>{formatNumber(barangay.businesses)} businesses</span>
                        <span>{percentage.toFixed(1)}% of total</span>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>
        )}

        {/* Barangay Collection Section */}
        <div className="bg-white border border-gray-200 rounded-xl shadow-sm">
          <div className="p-6 border-b border-gray-200">
            <div className="flex justify-between items-center">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                <Map className="w-5 h-5 text-gray-600" />
                Barangay Tax Collection {selectedYear}
              </h3>
              <button
                onClick={exportBarangayReport}
                disabled={exportLoading || barangay_collection.length === 0}
                className="flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm disabled:opacity-50"
              >
                <Download className="w-4 h-4" />
                Export
              </button>
            </div>
          </div>
          
          <div className="p-6">
            {barangaysData.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Barangay
                      </th>
                      <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Businesses
                      </th>
                      <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Total Collection
                      </th>
                      <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Avg per Business
                      </th>
                      <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        % of Total
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {barangaysData.map((barangay, index) => {
                      const totalCollection = barangaysData.reduce((total, b) => total + b.revenue, 0);
                      const percentage = totalCollection > 0 
                        ? (barangay.revenue / totalCollection) * 100 
                        : 0;
                      
                      return (
                        <tr key={index} className="hover:bg-gray-50">
                          <td className="px-4 py-3 whitespace-nowrap">
                            <div className="flex items-center">
                              <div className="ml-2">
                                <div className="text-sm font-medium text-gray-900">{barangay.name}</div>
                              </div>
                            </div>
                          </td>
                          <td className="px-4 py-3 whitespace-nowrap">
                            <div className="text-sm text-gray-900">{formatNumber(barangay.businesses)}</div>
                          </td>
                          <td className="px-4 py-3 whitespace-nowrap">
                            <div className="text-sm font-semibold text-gray-900">{formatCurrency(barangay.revenue)}</div>
                          </td>
                          <td className="px-4 py-3 whitespace-nowrap">
                            <div className="text-sm text-gray-500">{formatCurrency(barangay.avg_tax)}</div>
                          </td>
                          <td className="px-4 py-3 whitespace-nowrap">
                            <div className="flex items-center gap-2">
                              <div className="w-full bg-gray-200 rounded-full h-2">
                                <div 
                                  className="h-2 rounded-full bg-green-500"
                                  style={{ width: `${Math.min(percentage, 100)}%` }}
                                ></div>
                              </div>
                              <span className="text-sm text-gray-600 w-12">{percentage.toFixed(1)}%</span>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="text-center py-8 text-gray-400">
                <Map className="w-12 h-12 mx-auto mb-2" />
                <p>No barangay collection data available for {selectedYear}</p>
              </div>
            )}
          </div>
        </div>

        {/* Recent Activities */}
        <div className="bg-white border border-gray-200 rounded-xl shadow-sm">
          <div className="p-6 border-b border-gray-200">
            <div className="flex justify-between items-center">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                <Activity className="w-5 h-5 text-gray-600" />
                Recent Activities {selectedYear}
              </h3>
              <div className="flex gap-2">
                <button
                  onClick={() => setActiveTab('payments')}
                  className={`px-4 py-2 text-sm rounded-lg transition-colors border ${
                    activeTab === 'payments' 
                      ? 'bg-gray-900 text-white border-gray-900' 
                      : 'text-gray-700 border-gray-300 hover:bg-gray-50'
                  }`}
                >
                  Payments
                </button>
                <button
                  onClick={() => setActiveTab('overdue')}
                  className={`px-4 py-2 text-sm rounded-lg transition-colors border ${
                    activeTab === 'overdue' 
                      ? 'bg-gray-900 text-white border-gray-900' 
                      : 'text-gray-700 border-gray-300 hover:bg-gray-50'
                  }`}
                >
                  Overdue
                </button>
              </div>
            </div>
          </div>
          <div className="p-6">
            <div className="space-y-4">
              {getActivitiesForTab().slice(0, 5).map((activity, index) => (
                <div key={index} className="p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className={`p-2 rounded-lg ${
                        activeTab === 'payments' ? 'bg-green-100' :
                        'bg-red-100'
                      }`}>
                        {activeTab === 'payments' ? (
                          <CheckCircle className="w-5 h-5 text-green-600" />
                        ) : (
                          <AlertCircle className="w-5 h-5 text-red-600" />
                        )}
                      </div>
                      <div>
                        <h4 className="font-medium text-gray-900">{activity.business_name || activity.owner_name}</h4>
                        <p className="text-sm text-gray-500">
                          {activeTab === 'payments' && `Payment #${activity.receipt_number} • ${activity.quarter} ${activity.year}`}
                          {activeTab === 'overdue' && `${activity.days_overdue} days overdue • ${activity.quarter} ${activity.year}`}
                        </p>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className={`font-bold text-lg ${
                        activeTab === 'payments' ? 'text-green-600' :
                        'text-red-600'
                      }`}>
                        {formatCurrency(activity.total_quarterly_tax || activity.amount)}
                      </p>
                      {activeTab === 'payments' && (
                        <div className="flex items-center gap-2 text-sm text-gray-500">
                          {safeParseFloat(activity.discount_amount) > 0 && (
                            <span className="text-blue-600">
                              -{formatCurrency(activity.discount_amount)}
                            </span>
                          )}
                        </div>
                      )}
                    </div>
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

        {/* Footer Summary */}
        <div className="text-center text-sm text-gray-500 pt-6 border-t border-gray-200">
          <p>Business Tax Collection Dashboard • Year: {selectedYear} • Updated {formattedDate} at {formattedTime}</p>
          <p className="text-xs text-gray-400 mt-1">
            Available years: {(dataAvailableYears.length > 0 ? dataAvailableYears : availableYears).join(', ')}
          </p>
        </div>
      </div>
    </div>
  );
}