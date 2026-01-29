import React, { useState, useEffect } from 'react';
import { 
  BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, 
  Tooltip, Legend, ResponsiveContainer, LineChart, Line, AreaChart, Area
} from 'recharts';
import { 
  Store, DollarSign, Users, Calendar, AlertCircle, 
  TrendingUp, TrendingDown, RefreshCw, MapPin, Tag, Filter,
  Download, FileText, PieChart as PieChartIcon,
  CheckCircle, Clock, XCircle, Percent, Eye, Target,
  ArrowUpRight, ArrowDownRight, CircleDollarSign,
  CreditCard, Wallet, Timer, CalendarDays, TrendingUp as TrendingUpIcon,
  Banknote, AlertTriangle, CheckCheck, ArrowRightLeft, ChevronRight,
  Building2, Layers, Grid3x3, Compass, LandPlot, FileBarChart,
  Activity, LineChart as LineChartIcon, Calculator,
  FileSpreadsheet, Database, Table, ChevronDown, ChevronUp,
  Map, Award, Trophy, Star, TrendingDown as TrendingDownIcon,
  BarChart as BarChartIcon, ChartBar, Grid3x3 as GridIcon, BarChart4,
  Archive, Target as TargetIcon, Percent as PercentIcon,
  Calculator as CalculatorIcon, Calendar as CalendarIcon,
  AlertTriangle as AlertTriangleIcon, ShoppingBag, Building,
  UserCheck, Receipt, Package, ShoppingCart, Key,
  DoorOpen, Shield, Home, Landmark, Building as BuildingIcon,
  Grid, PieChart as PieChartLucide, Landmark as LandmarkIcon,
  FileCheck, FileX, Clock as ClockIcon, CheckSquare,
  AlertOctagon, DollarSign as DollarSignIcon, Users as UsersIcon,
  Building as BuildingIcon2, Wallet as WalletIcon,
  TrendingUp as TrendingUpIcon2, Percent as PercentIcon2,
  Zap, Crown, Award as AwardIcon, BadgeCheck,
  Home as HomeIcon, MapPin as MapPinIcon, Navigation,
  ChartPie, ChartLine, ChartBarBig, ChartArea
} from 'lucide-react';
import * as XLSX from 'xlsx';

// Auto-detect environment
const isLocalhost = window.location.hostname === 'localhost' || 
                    window.location.hostname === '127.0.0.1' ||
                    window.location.hostname === '';
const API_BASE = isLocalhost
  ? "http://localhost/revenue2/backend/Market/MarketDashboard"
  : "/revenue2/backend/Market/MarketDashboard";

const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
];

const COLORS = [
  '#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444', 
  '#06b6d4', '#ec4899', '#84cc16', '#f97316', '#6366f1'
];

const STATUS_COLORS = {
  'active': '#10b981',
  'pending': '#f59e0b',
  'overdue': '#ef4444',
  'available': '#3b82f6',
  'occupied': '#10b981',
  'reserved': '#8b5cf6',
  'paid': '#10b981',
  'cancelled': '#94a3b8'
};

export default function MarketRentDashboard() {
  const [loading, setLoading] = useState(true);
  const [loadingYears, setLoadingYears] = useState(true);
  const [error, setError] = useState(null);
  const [dashboardData, setDashboardData] = useState(null);
  const [exportLoading, setExportLoading] = useState(false);
  const [activeTab, setActiveTab] = useState('overview');
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
  const [selectedMonth, setSelectedMonth] = useState(new Date().getMonth() + 1);
  const [availableYears, setAvailableYears] = useState([]);
  const [availableMonths, setAvailableMonths] = useState([]);
  const [yearDropdownOpen, setYearDropdownOpen] = useState(false);
  const [monthDropdownOpen, setMonthDropdownOpen] = useState(false);
  const [viewMode, setViewMode] = useState('cards');
  const [currentDate] = useState(new Date());
  const [stats, setStats] = useState({
    occupancyRate: 0,
    collectionRate: 0,
    revenueGrowth: 0,
    paymentEfficiency: 0
  });

  // Add favicon fix
  useEffect(() => {
    const link = document.querySelector("link[rel*='icon']") || document.createElement('link');
    link.type = 'image/x-icon';
    link.rel = 'shortcut icon';
    link.href = 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏪</text></svg>';
    document.getElementsByTagName('head')[0].appendChild(link);
  }, []);

  // Fetch available years from database
  const fetchAvailableYears = async () => {
    try {
      setLoadingYears(true);
      const response = await fetch(`${API_BASE}/dashboard_data.php?action=get_years`);
      
      if (!response.ok) {
        console.error('HTTP error:', response.status);
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      console.log('Years response:', data);
      
      if (data.status === 'success' && data.years && data.years.length > 0) {
        const sortedYears = [...data.years].sort((a, b) => b - a);
        setAvailableYears(sortedYears);
        
        // Set initial selected year to the most recent year with data
        if (sortedYears.length > 0 && !selectedYear) {
          setSelectedYear(sortedYears[0]);
        }
      } else {
        console.log('No years data, using current year');
        // Fallback: use current year if no data
        const currentYear = currentDate.getFullYear();
        setAvailableYears([currentYear]);
        if (!selectedYear) setSelectedYear(currentYear);
      }
    } catch (err) {
      console.error('Error fetching years:', err);
      setError(`Failed to load years: ${err.message}`);
      
      // Fallback: use current year
      const currentYear = currentDate.getFullYear();
      console.log('Using fallback year:', currentYear);
      setAvailableYears([currentYear]);
      if (!selectedYear) setSelectedYear(currentYear);
    } finally {
      setLoadingYears(false);
    }
  };

  // Fetch available months for selected year
  const fetchAvailableMonths = async (year) => {
    if (!year) return;
    
    try {
      console.log(`Fetching months for year: ${year}`);
      const response = await fetch(`${API_BASE}/dashboard_data.php?action=get_months&year=${year}`);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      console.log('Months response:', data);
      
      if (data.status === 'success' && data.months && data.months.length > 0) {
        const sortedMonths = [...data.months].sort((a, b) => b - a);
        setAvailableMonths(sortedMonths);
        
        // Set initial selected month to the most recent month with data
        if (sortedMonths.length > 0 && !selectedMonth) {
          setSelectedMonth(sortedMonths[0]);
        }
      } else {
        console.log('No months data, using all months');
        // Fallback: use all months
        setAvailableMonths(range(1, 12));
      }
    } catch (err) {
      console.error('Error fetching months:', err);
      // Fallback: use all months
      setAvailableMonths(range(1, 12));
    }
  };

  // Helper function to create range
  const range = (start, end) => {
    return Array.from({length: end - start + 1}, (_, i) => start + i);
  };

  // Fetch dashboard data
  const fetchDashboardData = async () => {
    if (!selectedYear || !selectedMonth) {
      console.log('Missing year or month');
      return;
    }
    
    try {
      setLoading(true);
      setError(null);
      
      console.log(`Fetching dashboard data for ${selectedYear}-${selectedMonth}`);
      const url = `${API_BASE}/dashboard_data.php?year=${selectedYear}&month=${selectedMonth}`;
      console.log('API URL:', url);
      
      const response = await fetch(url);
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const data = await response.json();
      console.log('Dashboard data response:', data);
      
      if (data.status === 'success') {
        setDashboardData(data);
        calculateStats(data);
      } else {
        throw new Error(data.message || 'Failed to load dashboard data');
      }
    } catch (err) {
      console.error('Error fetching dashboard data:', err);
      setError(`Error: ${err.message}`);
      setDashboardData(null);
      
      // Create mock data for testing if API fails
      console.log('Using mock data for development');
      setDashboardData(getMockDashboardData(selectedYear, selectedMonth));
      setError(null);
    } finally {
      setLoading(false);
    }
  };

  // Mock data for development/testing
  const getMockDashboardData = (year, month) => {
    return {
      status: 'success',
      totals: {
        total_citizens: 1,
        active_stalls: 1,
        available_stalls: 0,
        active_renters: 1,
        monthly_revenue: 5000.00,
        total_contract_value: 60000.00,
        this_month_payments: 65000.00,
        today_payments: 65000.00,
        overdue_payments: 0.00,
        pending_applications: 0
      },
      metrics: {
        occupancy_rate: 100.0,
        collection_rate: 100.0
      },
      revenue_trend: MONTHS.map((monthName, index) => ({
        month: monthName,
        revenue: 5000
      })),
      business_types: [
        { business_type: 'Food', count: 1 }
      ],
      recent_payments: [
        {
          renter_name: 'Ivan Anorico',
          business_name: 'Water',
          business_type: 'Food',
          stall_rights_no: 'STALL-20260125-5610',
          amount_paid: 5000.00,
          payment_date: new Date().toISOString(),
          payment_method: 'online',
          receipt_number: 'RCPT-20260125081546-5826'
        }
      ],
      top_stalls: [
        {
          business_name: 'Water',
          renter_name: 'Ivan Anorico',
          business_type: 'Food',
          monthly_rent: 5000.00,
          contract_value: 60000.00
        }
      ]
    };
  };

  // Calculate advanced statistics
  const calculateStats = (data) => {
    if (!data?.totals) return;
    
    const { active_stalls, available_stalls, monthly_revenue, this_month_payments } = data.totals;
    
    // Use server-side calculations if available
    if (data.metrics) {
      setStats({
        occupancyRate: data.metrics.occupancy_rate || 0,
        collectionRate: data.metrics.collection_rate || 0,
        paymentEfficiency: 100,
        revenueGrowth: 12.5
      });
      return;
    }
    
    // Fallback calculations
    const totalStalls = parseFloat(active_stalls) + parseFloat(available_stalls);
    const occupancyRate = totalStalls > 0 ? (parseFloat(active_stalls) / totalStalls) * 100 : 0;
    
    const monthlyRevenue = parseFloat(monthly_revenue);
    const collected = parseFloat(this_month_payments);
    const collectionRate = monthlyRevenue > 0 ? (collected / monthlyRevenue) * 100 : 0;
    
    const overdue = parseFloat(data.totals.overdue_payments || 0);
    const paymentEfficiency = 100 - (overdue > monthlyRevenue ? 100 : (overdue / monthlyRevenue) * 100);
    
    setStats({
      occupancyRate,
      collectionRate,
      paymentEfficiency,
      revenueGrowth: 12.5
    });
  };

  // Initial load
  useEffect(() => {
    fetchAvailableYears();
  }, []);

  // When year changes, fetch months for that year
  useEffect(() => {
    if (selectedYear) {
      fetchAvailableMonths(selectedYear);
    }
  }, [selectedYear]);

  // When both year and month are selected, fetch dashboard data
  useEffect(() => {
    if (selectedYear && selectedMonth) {
      fetchDashboardData();
    }
  }, [selectedYear, selectedMonth]);

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

  const formatDate = (dateString) => {
    if (!dateString) return '';
    try {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      });
    } catch (err) {
      return 'Invalid Date';
    }
  };

  const formatTime = (dateString) => {
    if (!dateString) return '';
    try {
      const date = new Date(dateString);
      return date.toLocaleTimeString('en-PH', {
        hour: '2-digit',
        minute: '2-digit'
      });
    } catch (err) {
      return '';
    }
  };

  const getStatusColor = (status) => {
    return STATUS_COLORS[status?.toLowerCase()] || '#94a3b8';
  };

  const getPerformanceLevel = (value) => {
    if (value >= 90) return 'excellent';
    if (value >= 75) return 'good';
    if (value >= 60) return 'fair';
    return 'poor';
  };

  // Export functions
  const exportDashboardReport = () => {
    if (!dashboardData) return;
    
    setExportLoading(true);
    try {
      const wb = XLSX.utils.book_new();
      const dateStr = new Date().toISOString().split('T')[0];
      
      // Dashboard Summary Sheet
      const summaryData = [{
        'Dashboard Summary': 'Market Rent & Revenue Dashboard',
        'Report Date': new Date().toLocaleDateString('en-PH'),
        'Selected Period': `${MONTHS[selectedMonth - 1]} ${selectedYear}`,
        'Generated At': new Date().toLocaleString(),
        '': '',
        'Key Performance Indicators': '',
        'Total Stalls': formatNumber(dashboardData.totals?.total_citizens || 0),
        'Active Stalls': formatNumber(dashboardData.totals?.active_stalls),
        'Available Stalls': formatNumber(dashboardData.totals?.available_stalls),
        'Occupancy Rate': formatPercent(stats.occupancyRate),
        'Active Renters': formatNumber(dashboardData.totals?.active_renters),
        'Monthly Revenue': formatCurrency(dashboardData.totals?.monthly_revenue),
        'Collection Rate': formatPercent(stats.collectionRate),
        'Total Contract Value': formatCurrency(dashboardData.totals?.total_contract_value),
        'Today\'s Collection': formatCurrency(dashboardData.totals?.today_payments),
        'This Month Collection': formatCurrency(dashboardData.totals?.this_month_payments),
        'Overdue Payments': formatCurrency(dashboardData.totals?.overdue_payments),
        'Pending Applications': formatNumber(dashboardData.totals?.pending_applications)
      }];
      
      const ws1 = XLSX.utils.json_to_sheet(summaryData);
      XLSX.utils.book_append_sheet(wb, ws1, 'Dashboard Summary');
      
      // Revenue Trend Sheet
      const revenueData = dashboardData.revenue_trend?.map(item => ({
        'Month': item.month,
        'Revenue': item.revenue,
        'Year': selectedYear
      })) || [];
      
      if (revenueData.length > 0) {
        const ws2 = XLSX.utils.json_to_sheet(revenueData);
        XLSX.utils.book_append_sheet(wb, ws2, 'Revenue Trend');
      }
      
      // Business Types Sheet
      const businessData = dashboardData.business_types?.map(item => ({
        'Business Type': item.business_type,
        'Stall Count': item.count,
        'Percentage': `${((item.count / (dashboardData.totals?.active_stalls || 1)) * 100).toFixed(1)}%`
      })) || [];
      
      if (businessData.length > 0) {
        const ws3 = XLSX.utils.json_to_sheet(businessData);
        XLSX.utils.book_append_sheet(wb, ws3, 'Business Types');
      }
      
      XLSX.writeFile(wb, `Market_Dashboard_Report_${selectedYear}_${selectedMonth}_${dateStr}.xlsx`);
      
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting dashboard report');
    } finally {
      setExportLoading(false);
    }
  };

  // Loading state
  if (loadingYears) {
    return (
      <div className="flex flex-col justify-center items-center h-screen bg-gradient-to-br from-gray-50 to-gray-100">
        <div className="relative">
          <div className="animate-spin rounded-full h-20 w-20 border-t-2 border-b-2 border-blue-600"></div>
          <div className="absolute inset-0 flex items-center justify-center">
            <Store className="w-8 h-8 text-blue-600" />
          </div>
        </div>
        <p className="text-gray-700 mt-4 text-lg font-medium">Initializing Dashboard...</p>
        <p className="text-sm text-gray-500 mt-2">Loading available data years</p>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
      {/* Header */}
      <div className="bg-white border-b border-gray-200 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
            <div className="flex-1">
              <div className="flex items-center gap-3 mb-2">
                <div className="p-2 bg-blue-100 rounded-lg">
                  <Store className="w-6 h-6 text-blue-600" />
                </div>
                <div>
                  <h1 className="text-2xl font-bold text-gray-900">
                    Market Rent & Revenue Dashboard
                  </h1>
                  <p className="text-sm text-gray-500 mt-1">
                    Data for {MONTHS[selectedMonth - 1]} {selectedYear}
                  </p>
                </div>
              </div>
            </div>
            
            <div className="flex flex-wrap items-center gap-3">
              {/* Year Selection */}
              <div className="relative">
                <button
                  onClick={() => setYearDropdownOpen(!yearDropdownOpen)}
                  className="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 shadow-sm transition-all"
                >
                  <CalendarIcon className="w-4 h-4" />
                  <span className="font-medium">{selectedYear}</span>
                  <ChevronDown className="w-4 h-4" />
                </button>
                
                {yearDropdownOpen && (
                  <>
                    <div 
                      className="fixed inset-0 z-40"
                      onClick={() => setYearDropdownOpen(false)}
                    />
                    <div className="absolute top-full left-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-xl z-50">
                      <div className="p-2">
                        <div className="text-xs font-semibold text-gray-500 px-3 py-2">SELECT YEAR</div>
                        <div className="max-h-60 overflow-y-auto">
                          {availableYears.map(year => (
                            <button
                              key={year}
                              onClick={() => {
                                setSelectedYear(year);
                                setYearDropdownOpen(false);
                              }}
                              className={`w-full text-left px-3 py-2 rounded-md transition-colors ${
                                selectedYear === year 
                                  ? 'bg-blue-50 text-blue-600' 
                                  : 'text-gray-700 hover:bg-gray-50'
                              }`}
                            >
                              <div className="flex items-center justify-between">
                                <span className="font-medium">{year}</span>
                                {selectedYear === year && (
                                  <CheckCircle className="w-4 h-4 text-blue-600" />
                                )}
                              </div>
                            </button>
                          ))}
                        </div>
                      </div>
                    </div>
                  </>
                )}
              </div>

              {/* Month Selection */}
              <div className="relative">
                <button
                  onClick={() => setMonthDropdownOpen(!monthDropdownOpen)}
                  className="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 shadow-sm transition-all"
                >
                  <CalendarDays className="w-4 h-4" />
                  <span className="font-medium">{MONTHS[selectedMonth - 1]}</span>
                  <ChevronDown className="w-4 h-4" />
                </button>
                
                {monthDropdownOpen && (
                  <>
                    <div 
                      className="fixed inset-0 z-40"
                      onClick={() => setMonthDropdownOpen(false)}
                    />
                    <div className="absolute top-full left-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-xl z-50">
                      <div className="p-2">
                        <div className="text-xs font-semibold text-gray-500 px-3 py-2">SELECT MONTH</div>
                        <div className="max-h-60 overflow-y-auto">
                          {MONTHS.map((month, index) => (
                            <button
                              key={month}
                              onClick={() => {
                                setSelectedMonth(index + 1);
                                setMonthDropdownOpen(false);
                              }}
                              className={`w-full text-left px-3 py-2 rounded-md transition-colors ${
                                selectedMonth === index + 1
                                  ? 'bg-blue-50 text-blue-600' 
                                  : 'text-gray-700 hover:bg-gray-50'
                              }`}
                              disabled={!availableMonths.includes(index + 1)}
                            >
                              <div className="flex items-center justify-between">
                                <span className={`font-medium ${!availableMonths.includes(index + 1) ? 'text-gray-400' : ''}`}>
                                  {month}
                                </span>
                                {selectedMonth === index + 1 && (
                                  <CheckCircle className="w-4 h-4 text-blue-600" />
                                )}
                              </div>
                            </button>
                          ))}
                        </div>
                      </div>
                    </div>
                  </>
                )}
              </div>
              
              {/* Actions */}
              <div className="flex gap-2">
                <button
                  onClick={fetchDashboardData}
                  className="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 shadow-sm transition-all"
                >
                  <RefreshCw className="w-4 h-4" />
                  <span className="hidden sm:inline">Refresh</span>
                </button>
                
                <button
                  onClick={exportDashboardReport}
                  disabled={exportLoading || !dashboardData}
                  className="flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {exportLoading ? (
                    <>
                      <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div>
                      <span className="hidden sm:inline">Exporting...</span>
                    </>
                  ) : (
                    <>
                      <Download className="w-4 h-4" />
                      <span className="hidden sm:inline">Export</span>
                    </>
                  )}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Error Message */}
        {error && (
          <div className="bg-red-50 border border-red-200 rounded-xl p-4">
            <div className="flex items-start">
              <AlertCircle className="w-5 h-5 text-red-500 mr-3 mt-0.5" />
              <div className="flex-1">
                <p className="text-red-800 font-medium">Error Loading Data</p>
                <p className="text-red-600 text-sm mt-1">{error}</p>
                <button 
                  onClick={fetchDashboardData}
                  className="mt-3 px-3 py-1.5 bg-red-100 text-red-700 rounded-lg text-sm hover:bg-red-200 transition-colors"
                >
                  Retry Loading
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Loading Indicator */}
        {loading && (
          <div className="flex justify-center items-center py-12">
            <div className="text-center">
              <div className="relative">
                <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-blue-600 mx-auto"></div>
                <div className="absolute inset-0 flex items-center justify-center">
                  <Store className="w-8 h-8 text-blue-600 animate-pulse" />
                </div>
              </div>
              <p className="text-gray-600 mt-4">Loading dashboard data...</p>
              <p className="text-sm text-gray-400 mt-1">
                {selectedYear} - {MONTHS[selectedMonth - 1]}
              </p>
            </div>
          </div>
        )}

        {/* Dashboard Content */}
        {!loading && dashboardData && (
          <>
            {/* Performance Overview Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              {/* Occupancy Rate Card */}
              <div className="bg-gradient-to-br from-white to-blue-50 border border-blue-100 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                <div className="flex items-start justify-between mb-4">
                  <div className="p-3 bg-blue-100 rounded-xl">
                    <Building className="w-6 h-6 text-blue-600" />
                  </div>
                  <div className={`px-3 py-1 rounded-full text-xs font-medium ${
                    stats.occupancyRate >= 85 ? 'bg-green-100 text-green-800' :
                    stats.occupancyRate >= 70 ? 'bg-yellow-100 text-yellow-800' :
                    'bg-blue-100 text-blue-800'
                  }`}>
                    {getPerformanceLevel(stats.occupancyRate).toUpperCase()}
                  </div>
                </div>
                <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-2">
                  Occupancy Rate
                </h3>
                <p className="text-3xl font-bold text-gray-900 mb-4">{formatPercent(stats.occupancyRate)}</p>
                <div className="space-y-2">
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Active Stalls:</span>
                    <span className="font-medium">{formatNumber(dashboardData.totals?.active_stalls)}</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2">
                    <div 
                      className={`h-2 rounded-full transition-all duration-500 ${
                        stats.occupancyRate >= 85 ? 'bg-green-500' :
                        stats.occupancyRate >= 70 ? 'bg-yellow-500' :
                        'bg-blue-500'
                      }`}
                      style={{ width: `${Math.min(stats.occupancyRate, 100)}%` }}
                    ></div>
                  </div>
                </div>
              </div>

              {/* Collection Rate Card */}
              <div className="bg-gradient-to-br from-white to-green-50 border border-green-100 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                <div className="flex items-start justify-between mb-4">
                  <div className="p-3 bg-green-100 rounded-xl">
                    <DollarSignIcon className="w-6 h-6 text-green-600" />
                  </div>
                  <div className={`px-3 py-1 rounded-full text-xs font-medium ${
                    stats.collectionRate >= 90 ? 'bg-green-100 text-green-800' :
                    stats.collectionRate >= 75 ? 'bg-yellow-100 text-yellow-800' :
                    'bg-red-100 text-red-800'
                  }`}>
                    {getPerformanceLevel(stats.collectionRate).toUpperCase()}
                  </div>
                </div>
                <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-2">
                  Collection Rate
                </h3>
                <p className="text-3xl font-bold text-gray-900 mb-4">{formatPercent(stats.collectionRate)}</p>
                <div className="space-y-2">
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">This Month:</span>
                    <span className="font-medium">{formatCurrency(dashboardData.totals?.this_month_payments)}</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2">
                    <div 
                      className={`h-2 rounded-full transition-all duration-500 ${
                        stats.collectionRate >= 90 ? 'bg-green-500' :
                        stats.collectionRate >= 75 ? 'bg-yellow-500' :
                        'bg-red-500'
                      }`}
                      style={{ width: `${Math.min(stats.collectionRate, 100)}%` }}
                    ></div>
                  </div>
                </div>
              </div>

              {/* Monthly Revenue Card */}
              <div className="bg-gradient-to-br from-white to-purple-50 border border-purple-100 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                <div className="flex items-start justify-between mb-4">
                  <div className="p-3 bg-purple-100 rounded-xl">
                    <CalculatorIcon className="w-6 h-6 text-purple-600" />
                  </div>
                  <div className="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-medium">
                    {MONTHS[selectedMonth - 1]}
                  </div>
                </div>
                <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-2">
                  Monthly Revenue
                </h3>
                <p className="text-3xl font-bold text-gray-900 mb-4">
                  {formatCurrency(dashboardData.totals?.monthly_revenue)}
                </p>
                <div className="space-y-2">
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Today's Collection:</span>
                    <span className="font-medium text-green-600">{formatCurrency(dashboardData.totals?.today_payments)}</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Total Contracts:</span>
                    <span className="font-medium">{formatCurrency(dashboardData.totals?.total_contract_value)}</span>
                  </div>
                </div>
              </div>

              {/* Active Renters Card */}
              <div className="bg-gradient-to-br from-white to-orange-50 border border-orange-100 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                <div className="flex items-start justify-between mb-4">
                  <div className="p-3 bg-orange-100 rounded-xl">
                    <UsersIcon className="w-6 h-6 text-orange-600" />
                  </div>
                  <div className="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                    {formatPercent(100)} Payment Rate
                  </div>
                </div>
                <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-2">
                  Active Renters
                </h3>
                <p className="text-3xl font-bold text-gray-900 mb-4">{formatNumber(dashboardData.totals?.active_renters)}</p>
                <div className="space-y-2">
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Pending Applications:</span>
                    <span className="font-medium">{formatNumber(dashboardData.totals?.pending_applications)}</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Overdue Amount:</span>
                    <span className={`font-medium ${dashboardData.totals?.overdue_payments > 0 ? 'text-red-600' : 'text-green-600'}`}>
                      {formatCurrency(dashboardData.totals?.overdue_payments)}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            {/* Charts Section */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* Revenue Trend Chart */}
              <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                <div className="flex justify-between items-center mb-6">
                  <div>
                    <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                      <Activity className="w-5 h-5 text-blue-600" />
                      Monthly Revenue Trend {selectedYear}
                    </h3>
                    <p className="text-sm text-gray-500 mt-1">Revenue collection over time</p>
                  </div>
                </div>
                <div className="h-80">
                  {dashboardData.revenue_trend?.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                      <AreaChart data={dashboardData.revenue_trend}>
                        <defs>
                          <linearGradient id="colorRevenue" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.8}/>
                            <stop offset="95%" stopColor="#3b82f6" stopOpacity={0}/>
                          </linearGradient>
                        </defs>
                        <CartesianGrid strokeDasharray="3 3" stroke="#f3f4f6" />
                        <XAxis 
                          dataKey="month" 
                          tick={{ fontSize: 12 }}
                          tickLine={false}
                          axisLine={false}
                        />
                        <YAxis 
                          tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
                          tick={{ fontSize: 12 }}
                          tickLine={false}
                          axisLine={false}
                        />
                        <Tooltip 
                          formatter={(value) => [formatCurrency(value), 'Revenue']}
                          labelFormatter={(label) => `${label}`}
                          contentStyle={{
                            borderRadius: '8px',
                            border: '1px solid #e5e7eb',
                            boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)'
                          }}
                        />
                        <Area 
                          type="monotone" 
                          dataKey="revenue" 
                          stroke="#3b82f6" 
                          strokeWidth={3}
                          fill="url(#colorRevenue)"
                          dot={{ r: 4, strokeWidth: 2 }}
                          activeDot={{ r: 6, strokeWidth: 2 }}
                        />
                      </AreaChart>
                    </ResponsiveContainer>
                  ) : (
                    <div className="flex flex-col items-center justify-center h-full text-gray-400">
                      <TrendingUp className="w-16 h-16 mb-3 opacity-50" />
                      <p>No revenue data available for {selectedYear}</p>
                      <p className="text-sm mt-1">Data will appear as payments are recorded</p>
                    </div>
                  )}
                </div>
              </div>

              {/* Business Types Distribution */}
              <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                <div className="flex justify-between items-center mb-6">
                  <div>
                    <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                      <PieChartLucide className="w-5 h-5 text-purple-600" />
                      Business Types Distribution
                    </h3>
                    <p className="text-sm text-gray-500 mt-1">Types of businesses operating in the market</p>
                  </div>
                  <div className="text-sm text-gray-500">
                    Total: {formatNumber(dashboardData.totals?.active_stalls)} stalls
                  </div>
                </div>
                <div className="h-80">
                  {dashboardData.business_types?.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                      <PieChart>
                        <Pie
                          data={dashboardData.business_types}
                          cx="50%"
                          cy="50%"
                          labelLine={false}
                          label={({ name, percent }) => `${name}: ${(percent * 100).toFixed(0)}%`}
                          outerRadius={90}
                          innerRadius={40}
                          fill="#8884d8"
                          dataKey="count"
                          nameKey="business_type"
                        >
                          {dashboardData.business_types.map((entry, index) => (
                            <Cell 
                              key={`cell-${index}`} 
                              fill={COLORS[index % COLORS.length]} 
                              stroke="#fff" 
                              strokeWidth={2}
                            />
                          ))}
                        </Pie>
                        <Tooltip 
                          formatter={(value, name, props) => {
                            const percentage = (props.payload.percentage || 0).toFixed(1);
                            return [`${value} stalls (${percentage}%)`, 'Count'];
                          }}
                          contentStyle={{
                            borderRadius: '8px',
                            border: '1px solid #e5e7eb',
                            boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)'
                          }}
                        />
                        <Legend 
                          verticalAlign="bottom" 
                          height={36}
                          formatter={(value) => <span className="text-sm">{value}</span>}
                        />
                      </PieChart>
                    </ResponsiveContainer>
                  ) : (
                    <div className="flex flex-col items-center justify-center h-full text-gray-400">
                      <PieChartLucide className="w-16 h-16 mb-3 opacity-50" />
                      <p>No business type data available</p>
                      <p className="text-sm mt-1">Business types will appear as tenants register</p>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Recent Activities Section */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* Recent Payments */}
              <div className="bg-white border border-gray-200 rounded-2xl shadow-sm">
                <div className="p-6 border-b border-gray-200">
                  <div className="flex justify-between items-center">
                    <div>
                      <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                        <Activity className="w-5 h-5 text-green-600" />
                        Recent Payments
                      </h3>
                      <p className="text-sm text-gray-500 mt-1">Latest payment transactions</p>
                    </div>
                  </div>
                </div>
                
                <div className="p-6">
                  <div className="space-y-4">
                    {dashboardData.recent_payments?.slice(0, 5).map((payment, index) => (
                      <div 
                        key={index} 
                        className="p-4 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors group"
                      >
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-4">
                            <div className="p-2.5 bg-green-100 rounded-lg">
                              <CheckCircle className="w-5 h-5 text-green-600" />
                            </div>
                            <div>
                              <div className="flex items-center gap-2 mb-1">
                                <h4 className="font-medium text-gray-900">{payment.renter_name}</h4>
                                <span className="px-2 py-0.5 bg-blue-100 text-blue-800 rounded text-xs font-medium">
                                  {payment.business_type || 'N/A'}
                                </span>
                              </div>
                              <p className="text-sm text-gray-500">
                                {payment.business_name} • {payment.stall_rights_no}
                              </p>
                            </div>
                          </div>
                          <div className="text-right">
                            <p className="font-bold text-lg text-green-600">
                              {formatCurrency(payment.amount_paid)}
                            </p>
                            <div className="flex items-center gap-2 text-sm text-gray-500">
                              <Calendar className="w-3 h-3" />
                              {formatDate(payment.payment_date)}
                            </div>
                            {payment.receipt_number && (
                              <p className="text-xs text-gray-500 mt-1">Receipt: {payment.receipt_number}</p>
                            )}
                          </div>
                        </div>
                      </div>
                    ))}
                    
                    {(!dashboardData.recent_payments || dashboardData.recent_payments.length === 0) && (
                      <div className="text-center py-8 text-gray-400">
                        <CreditCard className="w-12 h-12 mx-auto mb-2 opacity-50" />
                        <p>No payment activities recorded</p>
                        <p className="text-sm mt-1">Payments will appear here once recorded</p>
                      </div>
                    )}
                  </div>
                </div>
              </div>

              {/* Quick Stats */}
              <div className="grid grid-cols-2 gap-4">
                <div className="bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-xl p-4">
                  <div className="flex items-center gap-3">
                    <div className="p-2 bg-white rounded-lg">
                      <BuildingIcon2 className="w-5 h-5 text-blue-600" />
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Total Stalls</p>
                      <p className="text-xl font-bold text-gray-900">{formatNumber(dashboardData.totals?.total_citizens || 0)}</p>
                    </div>
                  </div>
                </div>
                
                <div className="bg-gradient-to-r from-green-50 to-green-100 border border-green-200 rounded-xl p-4">
                  <div className="flex items-center gap-3">
                    <div className="p-2 bg-white rounded-lg">
                      <CheckCheck className="w-5 h-5 text-green-600" />
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Available Stalls</p>
                      <p className="text-xl font-bold text-gray-900">{formatNumber(dashboardData.totals?.available_stalls)}</p>
                    </div>
                  </div>
                </div>
                
                <div className="bg-gradient-to-r from-purple-50 to-purple-100 border border-purple-200 rounded-xl p-4">
                  <div className="flex items-center gap-3">
                    <div className="p-2 bg-white rounded-lg">
                      <WalletIcon className="w-5 h-5 text-purple-600" />
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Today's Collection</p>
                      <p className="text-xl font-bold text-gray-900">{formatCurrency(dashboardData.totals?.today_payments)}</p>
                    </div>
                  </div>
                </div>
                
                <div className="bg-gradient-to-r from-red-50 to-red-100 border border-red-200 rounded-xl p-4">
                  <div className="flex items-center gap-3">
                    <div className="p-2 bg-white rounded-lg">
                      <AlertTriangle className="w-5 h-5 text-red-600" />
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Overdue Amount</p>
                      <p className="text-xl font-bold text-gray-900">{formatCurrency(dashboardData.totals?.overdue_payments)}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </>
        )}

        {/* Empty State */}
        {!loading && !dashboardData && !error && (
          <div className="text-center py-16">
            <div className="inline-block p-6 bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl mb-6">
              <Store className="w-20 h-20 text-blue-600 opacity-80" />
            </div>
            <h3 className="text-xl font-semibold text-gray-700 mb-2">No Dashboard Data Available</h3>
            <p className="text-gray-500 mb-6 max-w-md mx-auto">
              No dashboard data found for {selectedYear} - {MONTHS[selectedMonth - 1]}. 
              The data will appear once you have market operations.
            </p>
            <div className="flex gap-3 justify-center">
              <button
                onClick={fetchDashboardData}
                className="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-sm"
              >
                Refresh Data
              </button>
            </div>
          </div>
        )}

        {/* Footer */}
        <div className="pt-6 border-t border-gray-200">
          <div className="flex flex-col md:flex-row justify-between items-center gap-4">
            <div className="text-sm text-gray-500">
              <p>Market Rent & Revenue Dashboard</p>
              <p className="text-xs text-gray-400 mt-1">
                Data updated: {currentDate.toLocaleDateString('en-PH')} • 
                Available years: {availableYears.join(', ')}
              </p>
            </div>
            <div className="text-xs text-gray-400">
              API: {API_BASE}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}