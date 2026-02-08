import React, { useState, useEffect } from 'react';
import { 
  BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, 
  Tooltip, Legend, ResponsiveContainer, LineChart, Line, Area,
  RadarChart, PolarGrid, PolarAngleAxis, PolarRadiusAxis, Radar
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
  Archive, BarChart4, PieChart as PieIcon, TrendingDown as TrendingDownIcon,
  Map, Award, Trophy, Star, Building as BuildingIcon, 
  Percent as PercentIcon, Target as TargetIcon, Users as UsersIcon,
  TrendingUp as TrendingUpIcon2, DollarSign as DollarSignIcon,
  Building as Building2Icon
} from 'lucide-react';
import * as XLSX from 'xlsx';

const API_BASE = window.location.hostname === "localhost"
  ? "http://localhost/revenue2/backend"
  : "https://revenuetreasury.goserveph.com/backend";

// Custom colors
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
        setAvailableYears(data.years.sort((a, b) => b - a));
      } else {
        const currentYear = new Date().getFullYear();
        setAvailableYears([currentYear, currentYear - 1, currentYear - 2]);
      }
    } catch (err) {
      console.error('Error fetching years:', err);
      const currentYear = new Date().getFullYear();
      setAvailableYears([currentYear, currentYear - 1]);
    }
  };

  const fetchDashboardData = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(`${API_BASE}/RPT/RPTDashboard/rpt_dashboard.php?action=dashboard&year=${selectedYear}`, {
        headers: { 'Accept': 'application/json' }
      });
      
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      
      const data = await response.json();
      
      if (!data.success) throw new Error(data.error || data.message || 'Failed to load dashboard data');
      
      setDashboardData(parseNumbersInData(data));
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
    if (amount === null || amount === undefined || amount === '' || isNaN(amount)) return '₱0';
    
    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
    
    if (numAmount >= 1000000000) return `₱${(numAmount / 1000000000).toFixed(2)}B`;
    if (numAmount >= 1000000) return `₱${(numAmount / 1000000).toFixed(2)}M`;
    if (numAmount >= 1000) return `₱${(numAmount / 1000).toFixed(2)}K`;
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

  const exportBarangayReport = () => {
    if (!dashboardData?.top_barangays) return;
    
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

      exportToExcel(barangayData, `RPT_Barangay_Report_${selectedYear}`, 'Top Barangays');
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting barangay report');
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
      
      // Summary Sheet
      const overallCollection = dashboardData.quarterly_analysis?.reduce((acc, q) => {
        return {
          total_due: acc.total_due + safeParseFloat(q.total_due),
          collected: acc.collected + safeParseFloat(q.collected)
        };
      }, { total_due: 0, collected: 0 }) || { total_due: 0, collected: 0 };
      
      const effectiveCollectionRate = overallCollection.total_due > 0 
        ? (overallCollection.collected / overallCollection.total_due) * 100 
        : 0;

      const summaryData = [{
        'Year': selectedYear,
        'Total Properties': formatNumber(dashboardData.property_stats?.total_registrations || 0),
        'Active Owners': formatNumber(dashboardData.property_stats?.active_owners || 0),
        'Total Annual Tax': formatCurrency(dashboardData.tax_stats?.annual?.total_annual_tax || 0),
        'Collection Rate': formatPercent(effectiveCollectionRate),
        'Current Quarter': dashboardData.current_quarter,
        'Total Outstanding': formatCurrency(dashboardData.tax_stats?.outstanding?.total_outstanding || 0),
        'Data Updated': dashboardData.timestamp
      }];
      
      const ws1 = XLSX.utils.json_to_sheet(summaryData);
      XLSX.utils.book_append_sheet(wb, ws1, 'Dashboard Summary');
      
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
      <div className="flex flex-col justify-center items-center h-screen bg-white">
        <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-gray-800 mb-4"></div>
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
            <AlertCircle className="w-8 h-8 text-red-600" />
            <div>
              <h3 className="text-lg font-semibold text-red-600">Error Loading Dashboard</h3>
              <p className="text-red-600">{error}</p>
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
      <div className="text-center py-12 bg-white">
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
    quarterly_analysis = [],
    top_barangays = [],
    payment_analysis = {},
    recent_activities = {},
    property_distribution = {},
    current_quarter: dataCurrentQuarter,
    timestamp
  } = dashboardData;

  // Calculate key metrics
  const overallCollection = quarterly_analysis.reduce((acc, q) => ({
    total_due: acc.total_due + safeParseFloat(q.total_due),
    collected: acc.collected + safeParseFloat(q.collected)
  }), { total_due: 0, collected: 0 });

  const effectiveCollectionRate = overallCollection.total_due > 0 
    ? (overallCollection.collected / overallCollection.total_due) * 100 
    : 0;

  const totalAnnualTax = safeParseFloat(tax_stats.annual?.total_annual_tax);
  const quarterlyTarget = safeParseFloat(tax_stats.annual?.quarterly_target);
  const currentQuarterCollected = safeParseFloat(tax_stats.current_quarter?.current_quarter_paid);
  const totalOutstanding = safeParseFloat(tax_stats.outstanding?.total_outstanding);

  const quarterlyData = tax_stats.quarterly || [];
  const paymentTimingData = payment_analysis.payment_timing || [];
  const propertyTypesData = property_distribution.property_types || [];

  const getActivitiesForTab = () => {
    switch(activeTab) {
      case 'payments': return recent_activities.payments || [];
      case 'registrations': return recent_activities.registrations || [];
      case 'overdue': return recent_activities.overdue || [];
      default: return [];
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
                  <div className="absolute top-full right-0 mt-1 w-48 bg-white border rounded-lg shadow-lg z-50">
                    <div className="py-1 max-h-60 overflow-y-auto">
                      {dashboardData.available_years?.map(year => (
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
            {dashboardData.available_years?.map(year => (
              <button
                key={year}
                onClick={() => setSelectedYear(year)}
                className={`px-3 py-1 text-sm rounded-lg transition-colors border ${
                  selectedYear === year
                    ? 'text-white border-gray-900'
                    : 'border-gray-300 hover:bg-gray-50'
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
        {/* Export Options */}
        <div className="bg-white border rounded-xl p-4" style={{ borderColor: COLORS.secondary }}>
          <div className="flex flex-wrap gap-2">
            <button
              onClick={exportBarangayReport}
              disabled={exportLoading || !top_barangays.length}
              className="flex items-center gap-2 px-3 py-2 border rounded-lg text-sm disabled:opacity-50 transition-all"
              style={{ 
                borderColor: COLORS.secondary, 
                color: COLORS.dark,
                backgroundColor: 'white'
              }}
            >
              <MapPin className="w-4 h-4" />
              Barangay Report
            </button>
            <button
              onClick={exportCompleteDashboardReport}
              disabled={exportLoading}
              className="flex items-center gap-2 px-3 py-2 border rounded-lg text-sm disabled:opacity-50 transition-all"
              style={{ 
                borderColor: COLORS.secondary, 
                color: COLORS.dark,
                backgroundColor: 'white'
              }}
            >
              <Database className="w-4 h-4" />
              Complete Report
            </button>
          </div>
        </div>

        {/* Key Metrics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {/* Collection Rate Card */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                <PercentIcon className="w-6 h-6" style={{ color: COLORS.primary }} />
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
                                   effectiveCollectionRate >= 75 ? '#ff9800' : COLORS.danger
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
                <DollarSignIcon className="w-6 h-6" style={{ color: COLORS.success }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full" 
                    style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                {selectedYear} Tax
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Total Assessment
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

        {/* Top Performing Barangays Section */}
        <div className="bg-white border rounded-xl shadow-sm" style={{ borderColor: COLORS.secondary }}>
          <div className="p-6 border-b" style={{ borderColor: COLORS.secondary }}>
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
              <div>
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <Map className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Top Performing Barangays {selectedYear}
                </h3>
                <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                  {top_barangays.length} barangays with property tax revenue
                </p>
              </div>
              <div className="flex gap-2">
                <button
                  onClick={exportBarangayReport}
                  disabled={exportLoading || top_barangays.length === 0}
                  className="flex items-center gap-2 px-3 py-2 border rounded-lg text-sm disabled:opacity-50 transition-all"
                  style={{ 
                    borderColor: COLORS.secondary, 
                    color: COLORS.dark,
                    backgroundColor: 'white'
                  }}
                >
                  <Download className="w-4 h-4" />
                  Export
                </button>
              </div>
            </div>
          </div>
          
          <div className="p-6">
            {top_barangays.length > 0 ? (
              <div className="space-y-6">
                {/* Summary Stats */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div className="p-4 rounded-lg border transition-all hover:shadow-sm" 
                       style={{ backgroundColor: `${COLORS.primary}05`, borderColor: COLORS.secondary }}>
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-sm font-medium" style={{ color: COLORS.dark }}>Total Barangays</span>
                      <MapPin className="w-5 h-5" style={{ color: COLORS.primary }} />
                    </div>
                    <p className="text-2xl font-bold" style={{ color: COLORS.primary }}>{top_barangays.length}</p>
                    <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>With Property Tax Revenue</p>
                  </div>
                  
                  <div className="p-4 rounded-lg border transition-all hover:shadow-sm" 
                       style={{ backgroundColor: `${COLORS.success}05`, borderColor: COLORS.secondary }}>
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-sm font-medium" style={{ color: COLORS.dark }}>Total Collection</span>
                      <DollarSign className="w-5 h-5" style={{ color: COLORS.success }} />
                    </div>
                    <p className="text-2xl font-bold" style={{ color: COLORS.success }}>
                      {formatCurrency(top_barangays.reduce((total, b) => total + safeParseFloat(b.total_annual_tax), 0))}
                    </p>
                    <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>From All Barangays</p>
                  </div>
                  
                  <div className="p-4 rounded-lg border transition-all hover:shadow-sm" 
                       style={{ backgroundColor: `${COLORS.info}05`, borderColor: COLORS.secondary }}>
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-sm font-medium" style={{ color: COLORS.dark }}>Total Properties</span>
                      <Building2Icon className="w-5 h-5" style={{ color: COLORS.info }} />
                    </div>
                    <p className="text-2xl font-bold" style={{ color: COLORS.info }}>
                      {formatNumber(top_barangays.reduce((total, b) => total + safeParseFloat(b.property_count), 0))}
                    </p>
                    <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>Across All Barangays</p>
                  </div>
                </div>
                
                {/* Top Barangays List */}
                <div>
                  <h4 className="font-semibold mb-4 flex items-center gap-2" style={{ color: COLORS.dark }}>
                    <Trophy className="w-5 h-5" style={{ color: COLORS.warning }} />
                    Top Performing Barangays
                  </h4>
                  <div className="space-y-3">
                    {top_barangays.slice(0, 5).map((barangay, index) => {
                      const totalCollection = top_barangays.reduce((total, b) => total + safeParseFloat(b.total_annual_tax), 0);
                      const percentage = totalCollection > 0 
                        ? (safeParseFloat(barangay.total_annual_tax) / totalCollection) * 100 
                        : 0;
                      
                      return (
                        <div key={index} 
                             className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition-all"
                             style={{ borderColor: COLORS.secondary }}>
                          <div className="flex items-center gap-3">
                            <div className={`w-8 h-8 rounded-full flex items-center justify-center ${
                              index === 0 ? 'bg-yellow-100 text-yellow-800' :
                              index === 1 ? 'bg-gray-200 text-gray-800' :
                              index === 2 ? 'bg-orange-100 text-orange-800' :
                              'bg-blue-100 text-blue-800'
                            }`}>
                              {index === 0 && <Trophy className="w-4 h-4" />}
                              {index === 1 && <Star className="w-4 h-4" />}
                              {index === 2 && <Award className="w-4 h-4" />}
                              {index > 2 && <span className="text-sm font-bold">{index + 1}</span>}
                            </div>
                            <div>
                              <h5 className="font-medium" style={{ color: COLORS.dark }}>{barangay.barangay}</h5>
                              <p className="text-sm" style={{ color: COLORS.secondary }}>
                                {formatNumber(barangay.property_count)} properties • {formatNumber(barangay.unique_owners)} owners
                              </p>
                            </div>
                          </div>
                          <div className="text-right">
                            <p className="font-bold text-lg" style={{ color: COLORS.primary }}>
                              {formatCurrency(barangay.total_annual_tax)}
                            </p>
                            <div className="flex items-center justify-end gap-2 text-sm">
                              <span style={{ color: COLORS.secondary }}>{percentage.toFixed(1)}%</span>
                              <div className="w-24 bg-gray-200 rounded-full h-2">
                                <div 
                                  className="h-2 rounded-full transition-all duration-500"
                                  style={{ 
                                    width: `${Math.min(percentage, 100)}%`,
                                    backgroundColor: percentage > 20 ? COLORS.success :
                                                   percentage > 10 ? COLORS.warning : COLORS.primary
                                  }}
                                ></div>
                              </div>
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              </div>
            ) : (
              <div className="text-center py-8" style={{ color: COLORS.secondary }}>
                <Map className="w-12 h-12 mx-auto mb-2" />
                <p>No barangay collection data available for {selectedYear}</p>
                <p className="text-sm mt-1">Property tax collection data by barangay will appear here</p>
              </div>
            )}
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
                  onClick={exportCompleteDashboardReport}
                  disabled={exportLoading || quarterlyData.length === 0}
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

            {/* Property Types Chart */}
            <div className="bg-white border rounded-xl p-6 shadow-sm" style={{ borderColor: COLORS.secondary }}>
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <Building2 className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Property Types Distribution
                </h3>
                <button
                  onClick={exportCompleteDashboardReport}
                  disabled={exportLoading}
                  className="text-sm hover:text-gray-700 disabled:opacity-50 transition-all"
                  style={{ color: COLORS.secondary }}
                >
                  Export
                </button>
              </div>
              <div className="h-72">
                {propertyTypesData.length > 0 ? (
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie
                        data={propertyTypesData.map((p, index) => ({
                          name: p.property_type,
                          value: safeParseFloat(p.total_tax),
                          color: CHART_COLORS[index % CHART_COLORS.length]
                        }))}
                        cx="50%"
                        cy="50%"
                        labelLine={false}
                        label={({ name, percent }) => `${name}: ${(percent * 100).toFixed(0)}%`}
                        outerRadius={80}
                        fill="#8884d8"
                        dataKey="value"
                      >
                        {propertyTypesData.map((entry, index) => (
                          <Cell key={`cell-${index}`} fill={CHART_COLORS[index % CHART_COLORS.length]} />
                        ))}
                      </Pie>
                      <Tooltip 
                        formatter={(value, name, props) => {
                          return [`${formatCurrency(value)}`, 'Annual Tax'];
                        }}
                        contentStyle={{ 
                          backgroundColor: 'white',
                          borderColor: COLORS.secondary,
                          borderRadius: '8px'
                        }}
                      />
                      <Legend />
                    </PieChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="flex flex-col items-center justify-center h-full" style={{ color: COLORS.secondary }}>
                    <PieChartIcon className="w-12 h-12 mb-2" />
                    <p>No property type data available</p>
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
                  onClick={exportCompleteDashboardReport}
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
              </div>
            </div>

            {/* Payment Analysis Cards */}
            <div className="bg-white border rounded-xl p-6 shadow-sm" style={{ borderColor: COLORS.secondary }}>
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <CreditCard className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Payment Analysis {selectedYear}
                </h3>
                <button
                  onClick={exportCompleteDashboardReport}
                  disabled={exportLoading || !payment_analysis}
                  className="text-sm hover:text-gray-700 disabled:opacity-50 transition-all"
                  style={{ color: COLORS.secondary }}
                >
                  Export
                </button>
              </div>
              <div className="space-y-4">
                {paymentTimingData.map((item, index) => (
                  <div key={index} 
                       className="p-4 border rounded-lg transition-all hover:shadow-sm"
                       style={{ borderColor: COLORS.secondary }}>
                    <div className="flex justify-between items-center mb-2">
                      <span className={`font-medium ${
                        item.payment_timing === 'On Time' ? 'text-green-700' :
                        item.payment_timing === 'Late Payment' ? 'text-yellow-700' :
                        'text-red-700'
                      }`}>
                        {item.payment_timing}
                      </span>
                      <span className="text-sm" style={{ color: COLORS.secondary }}>
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
                          <CheckCircle className="w-5 h-5 text-green-600" />
                        ) : activeTab === 'registrations' ? (
                          <FileText className="w-5 h-5 text-blue-600" />
                        ) : (
                          <AlertCircle className="w-5 h-5 text-red-600" />
                        )}
                      </div>
                      <div>
                        <h4 className="font-medium" style={{ color: COLORS.dark }}>{activity.owner_name}</h4>
                        <p className="text-sm" style={{ color: COLORS.secondary }}>
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
                        <div className="flex items-center gap-2 text-sm" style={{ color: COLORS.secondary }}>
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
                <div className="text-center py-8" style={{ color: COLORS.secondary }}>
                  <Activity className="w-12 h-12 mx-auto mb-2" />
                  <p>No {activeTab} activities available for {selectedYear}</p>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Footer Summary */}
        <div className="text-center text-sm pt-6 border-t" style={{ color: COLORS.secondary, borderColor: COLORS.secondary }}>
          <p>Real Property Tax Collection Dashboard • Year: {selectedYear} • Updated {formattedDate} at {formattedTime}</p>
          <p className="text-xs mt-1">
            Available years: {dashboardData.available_years?.join(', ') || selectedYear}
          </p>
        </div>
      </div>
    </div>
  );
}