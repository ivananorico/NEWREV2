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
  FileSpreadsheet, Database, Table, ChevronDown, ChevronUp,
  Archive, BarChart4, PieChart as PieIcon, TrendingDown as TrendingDownIcon
} from 'lucide-react';
import * as XLSX from 'xlsx';

// Auto-detect environment
const getApiBase = () => {
  const isLocalhost = window.location.hostname === 'localhost' || 
                     window.location.hostname === '127.0.0.1';
  
  if (isLocalhost) {
    return 'http://localhost/revenue2/backend';
  } else {
    return 'https://revenuetreasury.goserveph.com/backend';
  }
};

const API_BASE = getApiBase();

// Color palette from BusinessTaxDashboard
const COLORS = {
  primary: '#4a90e2',
  secondary: '#9aa5b1',
  success: '#4caf50',
  background: '#fbfbfb',
  warning: '#ff9800',
  danger: '#f44336',
  info: '#2196f3',
  dark: '#374151'
};

const CHART_COLORS = ['#4a90e2', '#9aa5b1', '#4caf50', '#ff9800', '#2196f3', '#f44336', '#673ab7'];

export default function RPTDashboardImproved() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [dashboardData, setDashboardData] = useState(null);
  const [exportLoading, setExportLoading] = useState(false);
  const [activeTab, setActiveTab] = useState('payments');
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
  const [availableYears, setAvailableYears] = useState([]);
  const [yearDropdownOpen, setYearDropdownOpen] = useState(false);
  const [viewMode, setViewMode] = useState('cards');
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
        const sortedYears = [...data.years].sort((a, b) => b - a);
        setAvailableYears(sortedYears);
      } else {
        const currentYear = new Date().getFullYear();
        const years = [currentYear, currentYear - 1, currentYear - 2];
        setAvailableYears(years);
        setSelectedYear(currentYear);
      }
    } catch (err) {
      console.error('Error fetching years:', err);
      const currentYear = new Date().getFullYear();
      setAvailableYears([currentYear, currentYear - 1]);
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
      
      const parsedData = parseNumbersInData(data);
      setDashboardData(parsedData);
      
    } catch (err) {
      console.error('Error:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

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

  const exportBarangayCollectionReport = () => {
    if (!dashboardData || !dashboardData.top_barangays) return;
    
    setExportLoading(true);
    try {
      const barangayData = dashboardData.top_barangays.map(b => ({
        'Barangay': b.barangay,
        'District': b.district || 'Unknown',
        'Property Count': formatNumber(b.property_count),
        'Unique Owners': formatNumber(b.unique_owners),
        'Total Annual Tax (PHP)': safeParseFloat(b.total_annual_tax),
        'Total Land Value (PHP)': safeParseFloat(b.total_land_value),
        'Total Building Value (PHP)': safeParseFloat(b.total_building_value),
        'Average Tax per Property (PHP)': safeParseFloat(b.avg_tax_per_property)
      }));

      exportToExcel(barangayData, `RPT_Barangay_Collections_${selectedYear}`, 'Barangay Collections');
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting barangay collection report');
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
        'Total Properties': formatNumber(dashboardData.property_stats.total_registrations),
        'Active Owners': formatNumber(dashboardData.property_stats.active_owners),
        'Total Annual Tax': formatCurrency(dashboardData.tax_stats.annual?.total_annual_tax),
        'Collection Rate': formatPercent(effectiveCollectionRate),
        'Current Quarter': dashboardData.current_quarter,
        'Total Outstanding': formatCurrency(dashboardData.tax_stats.outstanding?.total_outstanding),
        'Data Updated': dashboardData.timestamp
      }];
      
      const ws1 = XLSX.utils.json_to_sheet(summaryData);
      XLSX.utils.book_append_sheet(wb, ws1, 'Dashboard Summary');
      
      if (dashboardData.top_barangays && dashboardData.top_barangays.length > 0) {
        const barangayData = dashboardData.top_barangays.map(b => ({
          'Barangay': b.barangay,
          'District': b.district || 'Unknown',
          'Property Count': safeParseFloat(b.property_count),
          'Unique Owners': safeParseFloat(b.unique_owners),
          'Total Annual Tax': safeParseFloat(b.total_annual_tax),
          'Total Land Value': safeParseFloat(b.total_land_value),
          'Total Building Value': safeParseFloat(b.total_building_value),
          'Avg Tax per Property': safeParseFloat(b.avg_tax_per_property)
        }));
        
        const ws2 = XLSX.utils.json_to_sheet(barangayData);
        XLSX.utils.book_append_sheet(wb, ws2, 'Barangay Collections');
      }
      
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
      <div className="flex flex-col justify-center items-center h-screen" style={{ backgroundColor: COLORS.background }}>
        <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 mb-4"
             style={{ borderColor: COLORS.primary }}></div>
        <p className="text-gray-600">Loading Real Property Tax Dashboard...</p>
        <p className="text-sm text-gray-400 mt-2">Fetching data for {selectedYear}</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="max-w-4xl mx-auto p-6 bg-white">
        <div className="bg-red-50 border border-red-200 rounded-xl p-6" style={{ backgroundColor: COLORS.background }}>
          <div className="flex items-center space-x-3 mb-4">
            <AlertCircle className="w-8 h-8" style={{ color: COLORS.danger }} />
            <div>
              <h3 className="text-lg font-semibold" style={{ color: COLORS.danger }}>Error Loading Dashboard</h3>
              <p style={{ color: COLORS.danger }}>{error}</p>
            </div>
          </div>
          <button 
            onClick={fetchDashboardData}
            className="px-4 py-2 rounded-lg flex items-center gap-2 transition-all"
            style={{ backgroundColor: COLORS.primary, color: 'white' }}
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
      <div className="text-center py-12" style={{ backgroundColor: COLORS.background }}>
        <Landmark className="w-16 h-16 mx-auto mb-4" style={{ color: COLORS.primary }} />
        <p className="text-gray-500">No dashboard data available for {selectedYear}</p>
        <button 
          onClick={fetchDashboardData}
          className="mt-4 px-4 py-2 rounded-lg flex items-center gap-2 mx-auto transition-all"
          style={{ backgroundColor: COLORS.primary, color: 'white' }}
        >
          <RefreshCw className="w-4 h-4" />
          Load Dashboard
        </button>
      </div>
    );
  }

  const {
    property_stats = {},
    tax_stats = {},
    collection_performance = {},
    property_distribution = {},
    quarterly_analysis = [],
    top_barangays = [],
    recent_activities = {},
    current_quarter: dataCurrentQuarter,
    timestamp
  } = dashboardData;

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
  const quarterlyTarget = safeParseFloat(tax_stats.annual?.quarterly_target);
  const currentQuarterCollected = safeParseFloat(tax_stats.current_quarter?.current_quarter_paid);
  const totalOutstanding = safeParseFloat(tax_stats.outstanding?.total_outstanding);

  const quarterlyData = tax_stats.quarterly || [];
  const topBarangaysData = top_barangays.slice(0, 10);

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

  return (
    <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
      {/* Header */}
      <div className="border-b" style={{ backgroundColor: 'white', borderColor: '#e5e7eb' }}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
              <h1 className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>
                Real Property Tax Collection Dashboard
              </h1>
              <div className="flex items-center gap-3 text-sm" style={{ color: COLORS.secondary }}>
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
                  className="flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all"
                  style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                >
                  <Calendar className="w-4 h-4" />
                  <span>Year: {selectedYear}</span>
                  <ChevronDown className="w-4 h-4" />
                </button>
                
                {yearDropdownOpen && (
                  <div className="absolute top-full right-0 mt-1 w-48 bg-white border rounded-lg shadow-lg z-50"
                       style={{ borderColor: COLORS.secondary }}>
                    <div className="py-1 max-h-60 overflow-y-auto">
                      {availableYears.map(year => (
                        <button
                          key={year}
                          onClick={() => {
                            setSelectedYear(year);
                            setYearDropdownOpen(false);
                          }}
                          className={`w-full text-left px-4 py-2 hover:bg-gray-50 transition-colors ${
                            selectedYear === year 
                              ? 'bg-gray-100 font-medium' 
                              : 'text-gray-700'
                          }`}
                        >
                          <div className="flex items-center justify-between">
                            <span style={{ color: selectedYear === year ? COLORS.primary : COLORS.dark }}>
                              {year}
                            </span>
                            {selectedYear === year && (
                              <CheckCircle className="w-4 h-4" style={{ color: COLORS.primary }} />
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
                className="flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all"
                style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
              >
                <RefreshCw className="w-4 h-4" />
                Refresh
              </button>
              
              <button
                onClick={exportCompleteDashboardReport}
                disabled={exportLoading}
                className="flex items-center gap-2 px-4 py-2 rounded-lg transition-all disabled:opacity-50"
                style={{ backgroundColor: COLORS.primary, color: 'white' }}
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
            {availableYears.map(year => (
              <button
                key={year}
                onClick={() => setSelectedYear(year)}
                className={`px-3 py-1 text-sm rounded-lg transition-colors border ${
                  selectedYear === year
                    ? 'text-white'
                    : 'hover:bg-gray-50'
                }`}
                style={{
                  backgroundColor: selectedYear === year ? COLORS.primary : 'transparent',
                  color: selectedYear === year ? 'white' : COLORS.dark,
                  borderColor: selectedYear === year ? COLORS.primary : COLORS.secondary
                }}
              >
                {year}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Key Metrics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {/* Collection Rate Card */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md"
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                <Percent className="w-6 h-6" style={{ color: COLORS.primary }} />
              </div>
              <span className={`text-sm px-3 py-1 rounded-full ${
                effectiveCollectionRate >= 90 ? 'bg-green-100 text-green-800' :
                effectiveCollectionRate >= 75 ? 'bg-yellow-100 text-yellow-800' :
                'bg-red-100 text-red-800'
              }`}>
                {formatPercent(effectiveCollectionRate)}
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Collection Rate
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>
              {formatCurrency(overallCollection.collected)}
            </p>
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between mb-1">
                <span>Target:</span>
                <span className="font-medium">{formatCurrency(overallCollection.total_due)}</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div 
                  className="h-2 rounded-full transition-all duration-500"
                  style={{ 
                    width: `${Math.min(effectiveCollectionRate, 100)}%`,
                    backgroundColor: effectiveCollectionRate >= 90 ? COLORS.success :
                                   effectiveCollectionRate >= 75 ? COLORS.warning : COLORS.danger
                  }}
                ></div>
              </div>
            </div>
          </div>

          {/* Annual Tax Card */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md"
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.success}15` }}>
                <DollarSign className="w-6 h-6" style={{ color: COLORS.success }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full" 
                    style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                {selectedYear} Tax
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Total Tax
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{formatCurrency(totalAnnualTax)}</p>
            <div className="space-y-2 text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full" style={{ backgroundColor: COLORS.primary }}></div>
                  Residential:
                </span>
                <span>{formatCurrency(tax_stats.annual?.residential_tax)}</span>
              </div>
              <div className="flex justify-between">
                <span className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full" style={{ backgroundColor: COLORS.success }}></div>
                  Commercial:
                </span>
                <span>{formatCurrency(tax_stats.annual?.commercial_tax)}</span>
              </div>
            </div>
          </div>

          {/* Current Quarter Card */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md"
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.warning}15` }}>
                <CalendarDays className="w-6 h-6" style={{ color: COLORS.warning }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full" 
                    style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                {dataCurrentQuarter || currentQuarter}
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Current Quarter
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{formatCurrency(currentQuarterCollected)}</p>
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between mb-1">
                <span>Target:</span>
                <span className="font-medium">{formatCurrency(quarterlyTarget)}</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div 
                  className="h-2 rounded-full transition-all duration-500"
                  style={{ 
                    width: `${Math.min((currentQuarterCollected / quarterlyTarget) * 100, 100)}%`,
                    backgroundColor: (currentQuarterCollected / quarterlyTarget) >= 0.8 ? COLORS.success :
                                   (currentQuarterCollected / quarterlyTarget) >= 0.6 ? COLORS.warning : COLORS.danger
                  }}
                ></div>
              </div>
            </div>
          </div>

          {/* Outstanding Balance Card */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md"
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.danger}15` }}>
                <AlertTriangle className="w-6 h-6" style={{ color: COLORS.danger }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full bg-red-100 text-red-800">
                Delinquent
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Outstanding Balance
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{formatCurrency(totalOutstanding)}</p>
            <div className="space-y-2 text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Pending:</span>
                <span>{formatCurrency(tax_stats.outstanding?.pending_balance)}</span>
              </div>
              <div className="flex justify-between">
                <span>Overdue:</span>
                <span>{formatCurrency(tax_stats.outstanding?.overdue_balance)}</span>
              </div>
              <div className="flex justify-between font-medium" style={{ color: COLORS.dark }}>
                <span>Total Bills:</span>
                <span>{formatNumber(tax_stats.outstanding?.outstanding_bills)}</span>
              </div>
            </div>
          </div>
        </div>

        {/* View Mode Toggle */}
        <div className="flex justify-end">
          <div className="inline-flex rounded-lg border p-1" style={{ borderColor: COLORS.secondary }}>
            <button
              onClick={() => setViewMode('cards')}
              className={`px-4 py-2 text-sm rounded-md transition-all ${
                viewMode === 'cards' 
                  ? 'text-white' 
                  : 'hover:bg-gray-50'
              }`}
              style={{
                backgroundColor: viewMode === 'cards' ? COLORS.primary : 'transparent',
                color: viewMode === 'cards' ? 'white' : COLORS.dark
              }}
            >
              <div className="flex items-center gap-2">
                <Grid3x3 className="w-4 h-4" />
                Cards
              </div>
            </button>
            <button
              onClick={() => setViewMode('charts')}
              className={`px-4 py-2 text-sm rounded-md transition-all ${
                viewMode === 'charts' 
                  ? 'text-white' 
                  : 'hover:bg-gray-50'
              }`}
              style={{
                backgroundColor: viewMode === 'charts' ? COLORS.primary : 'transparent',
                color: viewMode === 'charts' ? 'white' : COLORS.dark
              }}
            >
              <div className="flex items-center gap-2">
                <BarChart4 className="w-4 h-4" />
                Charts
              </div>
            </button>
          </div>
        </div>

        {/* Charts Section */}
        {viewMode === 'charts' && (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Quarterly Collection Chart */}
            <div className="bg-white border rounded-xl p-6 shadow-sm" style={{ borderColor: COLORS.secondary }}>
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <Activity className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Quarterly Collection {selectedYear}
                </h3>
                <button
                  onClick={exportQuarterlyReport}
                  disabled={exportLoading || quarterly_analysis.length === 0}
                  className="text-sm hover:text-gray-700 disabled:opacity-50 transition-all"
                  style={{ color: COLORS.secondary }}
                >
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
                                       name === 'total_paid' ? 'Paid' : name;
                          return [name === 'collection_rate' ? `${value.toFixed(1)}%` : formattedValue, label];
                        }}
                        labelFormatter={(label) => `Quarter: ${label}`}
                        contentStyle={{ 
                          backgroundColor: 'white',
                          borderColor: COLORS.secondary,
                          borderRadius: '8px'
                        }}
                      />
                      <Legend />
                      <Bar dataKey="total_paid" fill={COLORS.primary} name="Paid" radius={[4, 4, 0, 0]} />
                      <Bar dataKey="total_due" fill={COLORS.secondary} name="Total Due" radius={[4, 4, 0, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="flex flex-col items-center justify-center h-full" style={{ color: COLORS.secondary }}>
                    <BarChart3 className="w-12 h-12 mb-2" />
                    <p>No quarterly data available for {selectedYear}</p>
                  </div>
                )}
              </div>
            </div>

            {/* Top Barangays Collection Chart */}
            <div className="bg-white border rounded-xl p-6 shadow-sm" style={{ borderColor: COLORS.secondary }}>
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <MapPin className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Top Barangays by Collection {selectedYear}
                </h3>
                <button
                  onClick={exportBarangayCollectionReport}
                  disabled={exportLoading || top_barangays.length === 0}
                  className="text-sm hover:text-gray-700 disabled:opacity-50 transition-all flex items-center gap-1"
                  style={{ color: COLORS.secondary }}
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
                        name: b.barangay.length > 15 ? b.barangay.substring(0, 12) + '...' : b.barangay,
                        collection: safeParseFloat(b.total_annual_tax),
                        fullName: b.barangay,
                        propertyCount: safeParseFloat(b.property_count),
                        avgTax: safeParseFloat(b.avg_tax_per_property)
                      }))}
                      layout="vertical"
                      margin={{ left: 100, right: 30, top: 20, bottom: 20 }}
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
                          if (name === 'collection') return [formatCurrency(value), 'Annual Tax'];
                          if (name === 'propertyCount') return [formatNumber(value), 'Properties'];
                          if (name === 'avgTax') return [formatCurrency(value), 'Avg Tax/Property'];
                          return [value, name];
                        }}
                        labelFormatter={(label, payload) => {
                          const fullName = payload[0]?.payload.fullName;
                          return `Barangay: ${fullName || label}`;
                        }}
                        contentStyle={{ 
                          backgroundColor: 'white',
                          borderColor: COLORS.secondary,
                          borderRadius: '8px'
                        }}
                      />
                      <Legend />
                      <Bar 
                        dataKey="collection" 
                        fill={COLORS.primary} 
                        name="Annual Tax Collection" 
                        radius={[0, 4, 4, 0]}
                      />
                    </BarChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="flex flex-col items-center justify-center h-full" style={{ color: COLORS.secondary }}>
                    <MapPin className="w-12 h-12 mb-2" />
                    <p>No barangay collection data available for {selectedYear}</p>
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
            <div className="bg-white border rounded-xl p-6 shadow-sm" style={{ borderColor: COLORS.secondary }}>
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <Calendar className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Quarterly Analysis {selectedYear}
                </h3>
                <button
                  onClick={exportQuarterlyReport}
                  disabled={exportLoading || quarterly_analysis.length === 0}
                  className="text-sm hover:text-gray-700 disabled:opacity-50 transition-all"
                  style={{ color: COLORS.secondary }}
                >
                  Export
                </button>
              </div>
              <div className="space-y-4">
                {quarterly_analysis.map((quarter, index) => (
                  <div key={index} 
                       className="p-4 border rounded-lg hover:bg-gray-50 transition-all"
                       style={{ borderColor: COLORS.secondary }}>
                    <div className="flex justify-between items-center mb-2">
                      <span className="font-medium" style={{ color: COLORS.dark }}>{quarter.quarter} {quarter.year}</span>
                      <span className={`text-sm px-3 py-1 rounded-full ${
                        safeParseFloat(quarter.collection_rate) >= 90 ? 'bg-green-100 text-green-800' :
                        safeParseFloat(quarter.collection_rate) >= 60 ? 'bg-yellow-100 text-yellow-800' :
                        'bg-red-100 text-red-800'
                      }`}>
                        {formatPercent(quarter.collection_rate)}
                      </span>
                    </div>
                    <div className="grid grid-cols-2 gap-4 text-sm" style={{ color: COLORS.secondary }}>
                      <div>
                        <p className="font-medium">Collected</p>
                        <p className="text-lg font-semibold" style={{ color: COLORS.dark }}>
                          {formatCurrency(quarter.collected)}
                        </p>
                      </div>
                      <div>
                        <p className="font-medium">Due</p>
                        <p className="text-lg font-semibold" style={{ color: COLORS.dark }}>
                          {formatCurrency(quarter.total_due)}
                        </p>
                      </div>
                    </div>
                  </div>
                ))}
                
                {quarterly_analysis.length === 0 && (
                  <div className="text-center py-8" style={{ color: COLORS.secondary }}>
                    <Calendar className="w-12 h-12 mx-auto mb-2" />
                    <p>No quarterly data available for {selectedYear}</p>
                  </div>
                )}
              </div>
            </div>

            {/* Collections per Barangay Cards */}
            <div className="bg-white border rounded-xl p-6 shadow-sm" style={{ borderColor: COLORS.secondary }}>
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <MapPin className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Collections per Barangay {selectedYear}
                </h3>
                <button
                  onClick={exportBarangayCollectionReport}
                  disabled={exportLoading || top_barangays.length === 0}
                  className="text-sm hover:text-gray-700 disabled:opacity-50 transition-all flex items-center gap-1"
                  style={{ color: COLORS.secondary }}
                >
                  <Download className="w-4 h-4" />
                  Export
                </button>
              </div>
              <div className="space-y-4 max-h-96 overflow-y-auto pr-2">
                {topBarangaysData.map((barangay, index) => (
                  <div key={index} 
                       className="p-4 border rounded-lg hover:bg-gray-50 transition-all"
                       style={{ borderColor: COLORS.secondary }}>
                    <div className="flex justify-between items-start mb-2">
                      <div>
                        <span className="font-medium" style={{ color: COLORS.dark }}>{barangay.barangay}</span>
                        {barangay.district && barangay.district !== 'Unknown' && (
                          <span className="ml-2 text-xs px-2 py-1 rounded-full"
                                style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                            {barangay.district}
                          </span>
                        )}
                      </div>
                      <span className="text-sm font-medium" style={{ color: COLORS.secondary }}>
                        {formatNumber(barangay.property_count)} properties
                      </span>
                    </div>
                    <div className="flex justify-between items-end">
                      <div>
                        <p className="text-xs" style={{ color: COLORS.secondary }}>Annual Tax Collection</p>
                        <p className="text-xl font-bold" style={{ color: COLORS.dark }}>
                          {formatCurrency(barangay.total_annual_tax)}
                        </p>
                      </div>
                      <div className="text-right">
                        <p className="text-xs" style={{ color: COLORS.secondary }}>Avg per Property</p>
                        <p className="text-sm font-semibold" style={{ color: COLORS.primary }}>
                          {formatCurrency(barangay.avg_tax_per_property)}
                        </p>
                      </div>
                    </div>
                    <div className="mt-2 flex justify-between text-xs" style={{ color: COLORS.secondary }}>
                      <span>Land Value: {formatCurrency(barangay.total_land_value)}</span>
                      <span>Building Value: {formatCurrency(barangay.total_building_value)}</span>
                    </div>
                  </div>
                ))}
                
                {topBarangaysData.length === 0 && (
                  <div className="text-center py-8" style={{ color: COLORS.secondary }}>
                    <MapPin className="w-12 h-12 mx-auto mb-2" />
                    <p>No barangay collection data available for {selectedYear}</p>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Recent Activities */}
        <div className="bg-white border rounded-xl shadow-sm" style={{ borderColor: COLORS.secondary }}>
          <div className="p-6 border-b" style={{ borderColor: COLORS.secondary }}>
            <div className="flex justify-between items-center">
              <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                <Activity className="w-5 h-5" style={{ color: COLORS.primary }} />
                Recent Activities {selectedYear}
              </h3>
              <div className="flex gap-2">
                <button
                  onClick={() => setActiveTab('payments')}
                  className={`px-4 py-2 text-sm rounded-lg transition-all border ${
                    activeTab === 'payments' 
                      ? 'text-white' 
                      : 'hover:bg-gray-50'
                  }`}
                  style={{
                    backgroundColor: activeTab === 'payments' ? COLORS.primary : 'transparent',
                    color: activeTab === 'payments' ? 'white' : COLORS.dark,
                    borderColor: activeTab === 'payments' ? COLORS.primary : COLORS.secondary
                  }}
                >
                  Payments
                </button>
                <button
                  onClick={() => setActiveTab('registrations')}
                  className={`px-4 py-2 text-sm rounded-lg transition-all border ${
                    activeTab === 'registrations' 
                      ? 'text-white' 
                      : 'hover:bg-gray-50'
                  }`}
                  style={{
                    backgroundColor: activeTab === 'registrations' ? COLORS.primary : 'transparent',
                    color: activeTab === 'registrations' ? 'white' : COLORS.dark,
                    borderColor: activeTab === 'registrations' ? COLORS.primary : COLORS.secondary
                  }}
                >
                  Registrations
                </button>
                <button
                  onClick={() => setActiveTab('overdue')}
                  className={`px-4 py-2 text-sm rounded-lg transition-all border ${
                    activeTab === 'overdue' 
                      ? 'text-white' 
                      : 'hover:bg-gray-50'
                  }`}
                  style={{
                    backgroundColor: activeTab === 'overdue' ? COLORS.primary : 'transparent',
                    color: activeTab === 'overdue' ? 'white' : COLORS.dark,
                    borderColor: activeTab === 'overdue' ? COLORS.primary : COLORS.secondary
                  }}
                >
                  Overdue
                </button>
              </div>
            </div>
          </div>
          <div className="p-6">
            <div className="space-y-4">
              {getActivitiesForTab().slice(0, 5).map((activity, index) => (
                <div key={index} 
                     className="p-4 border rounded-lg hover:bg-gray-50 transition-all"
                     style={{ borderColor: COLORS.secondary }}>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className={`p-2 rounded-lg ${
                        activeTab === 'payments' ? 'bg-green-100' :
                        activeTab === 'registrations' ? 'bg-blue-100' :
                        'bg-red-100'
                      }`}>
                        {activeTab === 'payments' ? (
                          <CheckCircle className="w-5 h-5" style={{ color: COLORS.success }} />
                        ) : activeTab === 'registrations' ? (
                          <FileText className="w-5 h-5" style={{ color: COLORS.info }} />
                        ) : (
                          <AlertCircle className="w-5 h-5" style={{ color: COLORS.danger }} />
                        )}
                      </div>
                      <div>
                        <h4 className="font-medium" style={{ color: COLORS.dark }}>{activity.owner_name}</h4>
                        <p className="text-sm" style={{ color: COLORS.secondary }}>
                          {activeTab === 'payments' && `Payment #${activity.receipt_number} • ${activity.quarter} ${activity.year}`}
                          {activeTab === 'registrations' && `Registration #${activity.reference_number} • ${activity.barangay}`}
                          {activeTab === 'overdue' && `${activity.days_late} days overdue • ${activity.quarter} ${activity.year} • ${activity.barangay}`}
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
                        <div className="flex items-center gap-2 text-sm" style={{ color: COLORS.secondary }}>
                          {safeParseFloat(activity.discount_amount) > 0 && (
                            <span style={{ color: COLORS.primary }}>
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
                <div className="text-center py-8" style={{ color: COLORS.secondary }}>
                  <Activity className="w-12 h-12 mx-auto mb-2" />
                  <p>No {activeTab} activities available for {selectedYear}</p>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Footer Summary */}
        <div className="text-center text-sm pt-6 border-t" 
             style={{ color: COLORS.secondary, borderColor: COLORS.secondary }}>
          <p>Real Property Tax Collection Dashboard • Year: {selectedYear} • Updated {formattedDate} at {formattedTime}</p>
          <p className="text-xs mt-1">
            Available years: {availableYears.join(', ')}
          </p>
        </div>
      </div>
    </div>
  );
}