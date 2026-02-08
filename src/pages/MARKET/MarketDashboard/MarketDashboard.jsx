import React, { useState, useEffect } from 'react';
import { 
  BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, 
  Tooltip, Legend, ResponsiveContainer, LineChart, Line, Area,
  RadarChart, PolarGrid, PolarAngleAxis, PolarRadiusAxis, Radar
} from 'recharts';
import { 
  Store, DollarSign, Users, Calendar, AlertCircle, 
  TrendingUp, TrendingDown, RefreshCw, MapPin, Tag, Filter,
  Download, FileText, PieChart as PieChartIcon,
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
  Building as Building2Icon, ShoppingBag, ShoppingCart,
  Package, Home, DoorOpen, Key, Shield, Receipt,
  Zap, Crown, BadgeCheck, Navigation, ChartPie,
  ChartLine, ChartBarBig, ChartArea, Store as StoreIcon,
  CreditCard as CreditCardIcon, UserCheck, FileCheck,
  FileX, Clock as ClockIcon, CheckSquare, AlertOctagon,
  Wallet as WalletIcon, Calculator as CalculatorIcon,
  Grid, Landmark as LandmarkIcon, BarChart as BarChartIcon,
  ChartBar, Building as BuildingIcon3
} from 'lucide-react';
import * as XLSX from 'xlsx';

// Auto-detect environment
const isLocalhost = window.location.hostname === 'localhost' || 
                    window.location.hostname === '127.0.0.1' ||
                    window.location.hostname === '';
const API_BASE = isLocalhost
  ? "http://localhost/revenue2/backend/Market/MarketDashboard"
  : "/backend/Market/MarketDashboard";

// Custom colors
const COLORS = {
  primary: '#3b82f6',
  secondary: '#6b7280',
  success: '#10b981',
  background: '#f9fafb',
  warning: '#f59e0b',
  danger: '#ef4444',
  info: '#06b6d4',
  dark: '#1f2937'
};

const CHART_COLORS = ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899'];

export default function MarketRPTDashboard() {
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
      const response = await fetch(`${API_BASE}/dashboard_data.php?action=get_years`);
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
      
      const response = await fetch(`${API_BASE}/dashboard_data.php?action=dashboard&year=${selectedYear}`, {
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

  const exportStallReport = () => {
    if (!dashboardData?.top_stalls) return;
    
    setExportLoading(true);
    try {
      const stallData = dashboardData.top_stalls.map(s => ({
        'Business Name': s.business_name,
        'Renter Name': s.renter_name,
        'Business Type': s.business_type,
        'Stall Number': s.stall_number,
        'Stall Class': s.stall_class,
        'Monthly Rent': safeParseFloat(s.monthly_rent),
        'Annual Revenue': safeParseFloat(s.annual_revenue),
        'Start Date': s.start_date,
        'Status': s.status
      }));

      exportToExcel(stallData, `Market_Stalls_Report_${selectedYear}`, 'Top Stalls');
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting stall report');
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
          quarterly_target: acc.quarterly_target + safeParseFloat(q.quarterly_target),
          collected_revenue: acc.collected_revenue + safeParseFloat(q.collected_revenue)
        };
      }, { quarterly_target: 0, collected_revenue: 0 }) || { quarterly_target: 0, collected_revenue: 0 };
      
      const effectiveCollectionRate = overallCollection.quarterly_target > 0 
        ? (overallCollection.collected_revenue / overallCollection.quarterly_target) * 100 
        : 0;

      const summaryData = [{
        'Year': selectedYear,
        'Total Stalls': formatNumber(dashboardData.market_stats?.total_stalls || 0),
        'Active Stalls': formatNumber(dashboardData.market_stats?.active_stalls || 0),
        'Available Stalls': formatNumber(dashboardData.market_stats?.available_stalls || 0),
        'Active Renters': formatNumber(dashboardData.market_stats?.active_renters || 0),
        'Total Annual Revenue': formatCurrency(dashboardData.revenue_stats?.annual?.total_annual_revenue || 0),
        'Monthly Revenue': formatCurrency(dashboardData.revenue_stats?.annual?.total_monthly_revenue || 0),
        'Collection Rate': formatPercent(effectiveCollectionRate),
        'Current Quarter': dashboardData.current_quarter,
        'Total Outstanding': formatCurrency(dashboardData.revenue_stats?.outstanding?.total_outstanding || 0),
        'Pending Applications': formatNumber(dashboardData.market_stats?.pending_applications || 0),
        'Data Updated': dashboardData.timestamp
      }];
      
      const ws1 = XLSX.utils.json_to_sheet(summaryData);
      XLSX.utils.book_append_sheet(wb, ws1, 'Dashboard Summary');
      
      // Stalls Data
      if (dashboardData.top_stalls.length > 0) {
        const stallData = dashboardData.top_stalls.map(s => ({
          'Business Name': s.business_name,
          'Renter': s.renter_name,
          'Business Type': s.business_type,
          'Stall Number': s.stall_number,
          'Class': s.stall_class,
          'Monthly Rent': safeParseFloat(s.monthly_rent),
          'Annual Revenue': safeParseFloat(s.annual_revenue),
          'Start Date': s.start_date,
          'Status': s.status
        }));
        const ws2 = XLSX.utils.json_to_sheet(stallData);
        XLSX.utils.book_append_sheet(wb, ws2, 'Top Stalls');
      }
      
      // Quarterly Analysis
      if (dashboardData.quarterly_analysis.length > 0) {
        const quarterlyData = dashboardData.quarterly_analysis.map(q => ({
          'Quarter': q.quarter,
          'Year': q.year,
          'Target': formatCurrency(q.quarterly_target),
          'Collected': formatCurrency(q.collected_revenue),
          'Collection Rate': formatPercent(q.collection_rate),
          'Total Payments': q.total_payments,
          'Early Payments': q.early_payments,
          'On Time': q.ontime_payments,
          'Late Payments': q.late_payments
        }));
        const ws3 = XLSX.utils.json_to_sheet(quarterlyData);
        XLSX.utils.book_append_sheet(wb, ws3, 'Quarterly Analysis');
      }
      
      XLSX.writeFile(wb, `Market_Dashboard_Report_${selectedYear}_${dateStr}.xlsx`);
      
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
        <p className="text-gray-600">Loading Market Rent & Revenue Dashboard...</p>
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
        <StoreIcon className="w-16 h-16 mx-auto mb-4" style={{ color: COLORS.primary }} />
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
    market_stats = {},
    revenue_stats = {},
    quarterly_analysis = [],
    top_stalls = [],
    payment_analysis = {},
    recent_activities = {},
    business_distribution = {},
    current_quarter: dataCurrentQuarter,
    timestamp
  } = dashboardData;

  // Calculate key metrics
  const overallCollection = quarterly_analysis.reduce((acc, q) => ({
    quarterly_target: acc.quarterly_target + safeParseFloat(q.quarterly_target),
    collected_revenue: acc.collected_revenue + safeParseFloat(q.collected_revenue)
  }), { quarterly_target: 0, collected_revenue: 0 });

  const effectiveCollectionRate = overallCollection.quarterly_target > 0 
    ? (overallCollection.collected_revenue / overallCollection.quarterly_target) * 100 
    : 0;

  const totalAnnualRevenue = safeParseFloat(revenue_stats.annual?.total_annual_revenue);
  const monthlyRevenue = safeParseFloat(revenue_stats.annual?.total_monthly_revenue);
  const quarterlyTarget = safeParseFloat(revenue_stats.current_quarter?.current_quarter_target);
  const currentQuarterCollected = safeParseFloat(revenue_stats.current_quarter?.current_quarter_collected);
  const totalOutstanding = safeParseFloat(revenue_stats.outstanding?.total_outstanding);

  const quarterlyData = revenue_stats.quarterly || [];
  const paymentTimingData = payment_analysis.payment_timing || [];
  const businessTypesData = business_distribution.business_types || [];

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
                Market Rent & Revenue Dashboard
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
              onClick={exportStallReport}
              disabled={exportLoading || !top_stalls.length}
              className="flex items-center gap-2 px-3 py-2 border rounded-lg text-sm disabled:opacity-50 transition-all"
              style={{ 
                borderColor: COLORS.secondary, 
                color: COLORS.dark,
                backgroundColor: 'white'
              }}
            >
              <StoreIcon className="w-4 h-4" />
              Stall Report
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
              {formatCurrency(overallCollection.collected_revenue)}
            </p>
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between mb-1">
                <span>Target:</span>
                <span className="font-medium">{formatCurrency(overallCollection.quarterly_target)}</span>
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

          {/* Annual Revenue Card */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.success}15` }}>
                <DollarSignIcon className="w-6 h-6" style={{ color: COLORS.success }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full" 
                    style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                {selectedYear} Revenue
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Total Annual Revenue
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{formatCurrency(totalAnnualRevenue)}</p>
            <div className="space-y-2 text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full" style={{ backgroundColor: COLORS.primary }}></div>
                  Food:
                </span>
                <span>{formatCurrency(revenue_stats.annual?.food_revenue)}</span>
              </div>
              <div className="flex justify-between">
                <span className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full" style={{ backgroundColor: COLORS.success }}></div>
                  Retail:
                </span>
                <span>{formatCurrency(revenue_stats.annual?.retail_revenue)}</span>
              </div>
              <div className="flex justify-between">
                <span className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full" style={{ backgroundColor: COLORS.warning }}></div>
                  Service:
                </span>
                <span>{formatCurrency(revenue_stats.annual?.service_revenue)}</span>
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
                <span>{formatCurrency(revenue_stats.outstanding?.pending_balance)}</span>
              </div>
              <div className="flex justify-between">
                <span>Overdue:</span>
                <span>{formatCurrency(revenue_stats.outstanding?.overdue_balance)}</span>
              </div>
              <div className="flex justify-between font-medium" style={{ color: COLORS.dark }}>
                <span>Total Bills:</span>
                <span>{formatNumber(revenue_stats.outstanding?.outstanding_bills)}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Market Statistics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {/* Total Stalls Card */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                <StoreIcon className="w-6 h-6" style={{ color: COLORS.primary }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full" 
                    style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                Total
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Total Stalls
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{formatNumber(market_stats.total_stalls)}</p>
            <div className="space-y-2 text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Occupied:</span>
                <span className="font-medium">{formatNumber(market_stats.active_stalls)}</span>
              </div>
              <div className="flex justify-between">
                <span>Available:</span>
                <span className="font-medium">{formatNumber(market_stats.available_stalls)}</span>
              </div>
            </div>
          </div>

          {/* Active Renters Card */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.success}15` }}>
                <UsersIcon className="w-6 h-6" style={{ color: COLORS.success }} />
              </div>
              <span className={`text-sm px-3 py-1 rounded-full ${
                market_stats.active_renters > 50 ? 'bg-green-100 text-green-800' :
                market_stats.active_renters > 30 ? 'bg-yellow-100 text-yellow-800' :
                'bg-blue-100 text-blue-800'
              }`}>
                Active
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Active Renters
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{formatNumber(market_stats.active_renters)}</p>
            <div className="space-y-2 text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Occupancy Rate:</span>
                <span className="font-medium">{formatPercent(market_stats.total_stalls > 0 ? (market_stats.active_stalls / market_stats.total_stalls) * 100 : 0)}</span>
              </div>
              <div className="flex justify-between">
                <span>Pending Applications:</span>
                <span className="font-medium">{formatNumber(market_stats.pending_applications)}</span>
              </div>
            </div>
          </div>

          {/* Monthly Revenue Card */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.warning}15` }}>
                <CreditCardIcon className="w-6 h-6" style={{ color: COLORS.warning }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full" 
                    style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                Monthly
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Monthly Revenue
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{formatCurrency(monthlyRevenue)}</p>
            <div className="space-y-2 text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Per Stall (Avg):</span>
                <span className="font-medium">
                  {formatCurrency(market_stats.active_stalls > 0 ? monthlyRevenue / market_stats.active_stalls : 0)}
                </span>
              </div>
              <div className="flex justify-between">
                <span>Active Contracts:</span>
                <span className="font-medium">{formatNumber(revenue_stats.annual?.active_contracts || 0)}</span>
              </div>
            </div>
          </div>

          {/* Stalls by Class Card */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.info}15` }}>
                <Building2Icon className="w-6 h-6" style={{ color: COLORS.info }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full" 
                    style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                Classes
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Stalls by Class
            </h3>
            <div className="space-y-3">
              {market_stats.stalls_by_class?.map((stallClass, index) => (
                <div key={index} className="flex justify-between items-center">
                  <span className="text-sm" style={{ color: COLORS.dark }}>Class {stallClass.class_name}:</span>
                  <div className="flex items-center gap-2">
                    <span className="font-medium">{formatNumber(stallClass.stall_count)}</span>
                    <span className="text-xs" style={{ color: COLORS.secondary }}>
                      {formatCurrency(stallClass.total_value)}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Top Performing Stalls Section */}
        <div className="bg-white border rounded-xl shadow-sm" style={{ borderColor: COLORS.secondary }}>
          <div className="p-6 border-b" style={{ borderColor: COLORS.secondary }}>
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
              <div>
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <Trophy className="w-5 h-5" style={{ color: COLORS.warning }} />
                  Top Performing Stalls {selectedYear}
                </h3>
                <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                  {top_stalls.length} stalls with highest revenue generation
                </p>
              </div>
              <div className="flex gap-2">
                <button
                  onClick={exportStallReport}
                  disabled={exportLoading || top_stalls.length === 0}
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
            {top_stalls.length > 0 ? (
              <div className="space-y-6">
                {/* Top Stalls List */}
                <div className="space-y-4">
                  {top_stalls.slice(0, 5).map((stall, index) => {
                    const monthlyRent = safeParseFloat(stall.monthly_rent);
                    const percentage = totalAnnualRevenue > 0 
                      ? ((monthlyRent * 12) / totalAnnualRevenue) * 100 
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
                            <h5 className="font-medium" style={{ color: COLORS.dark }}>{stall.business_name}</h5>
                            <p className="text-sm" style={{ color: COLORS.secondary }}>
                              {stall.renter_name} • {stall.stall_number} • {stall.business_type}
                            </p>
                          </div>
                        </div>
                        <div className="text-right">
                          <p className="font-bold text-lg" style={{ color: COLORS.primary }}>
                            {formatCurrency(stall.monthly_rent)}/mo
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
            ) : (
              <div className="text-center py-8" style={{ color: COLORS.secondary }}>
                <StoreIcon className="w-12 h-12 mx-auto mb-2" />
                <p>No stall revenue data available for {selectedYear}</p>
                <p className="text-sm mt-1">Stall revenue data will appear here once collected</p>
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
            {/* Quarterly Revenue Chart */}
            <div className="bg-white border rounded-xl p-6 shadow-sm" style={{ borderColor: COLORS.secondary }}>
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <Activity className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Quarterly Revenue {selectedYear}
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
                      quarterly_target: safeParseFloat(q.quarterly_target),
                      collected_revenue: safeParseFloat(q.collected_revenue),
                      collection_rate: safeParseFloat(q.collection_rate)
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
                                       name === 'quarterly_target' ? 'Target' :
                                       name === 'collected_revenue' ? 'Collected' : name;
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
                      <Bar dataKey="collected_revenue" fill={COLORS.primary} name="Collected" radius={[4, 4, 0, 0]} />
                      <Bar dataKey="quarterly_target" fill={COLORS.secondary} name="Target" radius={[4, 4, 0, 0]} />
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

            {/* Business Types Chart */}
            <div className="bg-white border rounded-xl p-6 shadow-sm" style={{ borderColor: COLORS.secondary }}>
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <ShoppingBag className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Business Types Distribution
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
                {businessTypesData.length > 0 ? (
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie
                        data={businessTypesData.map((p, index) => ({
                          name: p.business_type,
                          value: safeParseFloat(p.total_monthly_revenue),
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
                        {businessTypesData.map((entry, index) => (
                          <Cell key={`cell-${index}`} fill={CHART_COLORS[index % CHART_COLORS.length]} />
                        ))}
                      </Pie>
                      <Tooltip 
                        formatter={(value, name, props) => {
                          return [`${formatCurrency(value)}`, 'Monthly Revenue'];
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
                          {formatCurrency(quarter.collected_revenue)}
                        </p>
                      </div>
                      <div>
                        <p className="font-medium">Target</p>
                        <p className="text-lg font-semibold" style={{ color: COLORS.dark }}>
                          {formatCurrency(quarter.quarterly_target)}
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
                        item.payment_timing.includes('Early') ? 'text-green-700' :
                        item.payment_timing.includes('On Time') ? 'text-blue-700' :
                        'text-red-700'
                      }`}>
                        {item.payment_timing}
                      </span>
                      <span className="text-sm" style={{ color: COLORS.secondary }}>
                        {safeParseFloat(item.count)} payments
                      </span>
                    </div>
                    <p className={`text-2xl font-bold ${
                      item.payment_timing.includes('Early') ? 'text-green-600' :
                      item.payment_timing.includes('On Time') ? 'text-blue-600' :
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
                        <h4 className="font-medium" style={{ color: COLORS.dark }}>{activity.renter_name}</h4>
                        <p className="text-sm" style={{ color: COLORS.secondary }}>
                          {activeTab === 'payments' && `${activity.business_name} • ${activity.stall_number}`}
                          {activeTab === 'registrations' && `Stall: ${activity.stall_number} • ${activity.application_status}`}
                          {activeTab === 'overdue' && `${activity.business_name} • ${activity.stall_number} • ${activity.current_days_late} days late`}
                        </p>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className={`font-bold text-lg ${
                        activeTab === 'payments' ? 'text-green-600' :
                        activeTab === 'overdue' ? 'text-red-600' :
                        'text-blue-600'
                      }`}>
                        {formatCurrency(activity.amount_paid || activity.total_amount_due || activity.monthly_rent || 0)}
                      </p>
                      {activeTab === 'payments' && (
                        <p className="text-sm" style={{ color: COLORS.secondary }}>
                          {activity.payment_date ? new Date(activity.payment_date).toLocaleDateString() : ''}
                        </p>
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
          <p>Market Rent & Revenue Dashboard • Year: {selectedYear} • Updated {formattedDate} at {formattedTime}</p>
          <p className="text-xs mt-1">
            Available years: {dashboardData.available_years?.join(', ') || selectedYear}
          </p>
        </div>
      </div>
    </div>
  );
}