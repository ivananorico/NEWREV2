import React, { useState, useEffect } from 'react';
import { 
  BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, 
  Tooltip, Legend, ResponsiveContainer, LineChart, Line
} from 'recharts';
import { 
  Store, DollarSign, Users, Calendar, AlertCircle, 
  TrendingUp, TrendingDown, RefreshCw, MapPin, Tag, Filter,
  Download, FileText, BarChart3, PieChart as PieChartIcon,
  CheckCircle, Clock, XCircle, Percent, Eye, Target,
  ArrowUpRight, ArrowDownRight, CircleDollarSign,
  CreditCard, Wallet, Timer, CalendarDays, TrendingUp as TrendingUpIcon,
  Banknote, AlertTriangle, CheckCheck, ArrowRightLeft, ChevronRight,
  Building2, Layers, Grid3x3, Compass, LandPlot, FileBarChart,
  Activity, LineChart as LineChartIcon, Calculator,
  FileSpreadsheet, Database, Table, ChevronDown, ChevronUp,
  Map, Award, Trophy, Star, TrendingDown as TrendingDownIcon,
  BarChart as BarChartIcon, LineChart as LineChartIcon2,
  ChartBar, Grid3x3 as GridIcon, BarChart4,
  Archive, Target as TargetIcon, Percent as PercentIcon,
  Calculator as CalculatorIcon, Calendar as CalendarIcon,
  AlertTriangle as AlertTriangleIcon, ShoppingBag, Building,
  UserCheck, Receipt, Package, ShoppingCart, Key,
  DoorOpen, Shield, Home, Landmark, Building as BuildingIcon,
  Grid, PieChart as PieChartLucide
} from 'lucide-react';
import * as XLSX from 'xlsx';

// Auto-detect environment
const isLocalhost = window.location.hostname === 'localhost' || 
                    window.location.hostname === '127.0.0.1' ||
                    window.location.hostname === '';
const API_BASE = isLocalhost
  ? "http://localhost/revenue2/backend/Market/MarketDashboard"
  : "/backend/Market/MarketDashboard";

const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
];

const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8', '#82ca9d', '#ffc658'];

export default function MarketRentDashboard() {
  const [loading, setLoading] = useState(true);
  const [loadingYears, setLoadingYears] = useState(true);
  const [error, setError] = useState(null);
  const [dashboardData, setDashboardData] = useState(null);
  const [exportLoading, setExportLoading] = useState(false);
  const [activeTab, setActiveTab] = useState('payments');
  const [selectedYear, setSelectedYear] = useState(null);
  const [selectedMonth, setSelectedMonth] = useState(null);
  const [availableYears, setAvailableYears] = useState([]);
  const [availableMonths, setAvailableMonths] = useState([]);
  const [yearDropdownOpen, setYearDropdownOpen] = useState(false);
  const [monthDropdownOpen, setMonthDropdownOpen] = useState(false);
  const [viewMode, setViewMode] = useState('cards');
  const [currentDate] = useState(new Date());

  // Fetch available years from database
  const fetchAvailableYears = async () => {
    try {
      setLoadingYears(true);
      const response = await fetch(`${API_BASE}/dashboard_data.php?action=get_years`);
      
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      
      const data = await response.json();
      
      if (data.status === 'success' && data.years && data.years.length > 0) {
        const sortedYears = [...data.years].sort((a, b) => b - a);
        setAvailableYears(sortedYears);
        
        // Set initial selected year to the most recent year with data
        if (sortedYears.length > 0 && !selectedYear) {
          setSelectedYear(sortedYears[0]);
        }
      } else {
        // Fallback: use current year if no data
        const currentYear = currentDate.getFullYear();
        setAvailableYears([currentYear]);
        if (!selectedYear) setSelectedYear(currentYear);
      }
    } catch (err) {
      console.error('Error fetching years:', err);
      // Fallback: use current year
      const currentYear = currentDate.getFullYear();
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
      const response = await fetch(`${API_BASE}/dashboard_data.php?action=get_months&year=${year}`);
      
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      
      const data = await response.json();
      
      if (data.status === 'success' && data.months && data.months.length > 0) {
        const sortedMonths = [...data.months].sort((a, b) => b - a);
        setAvailableMonths(sortedMonths);
        
        // Set initial selected month to the most recent month with data
        if (sortedMonths.length > 0 && !selectedMonth) {
          setSelectedMonth(sortedMonths[0]);
        }
      } else {
        // Fallback: use current month
        const currentMonth = currentDate.getMonth() + 1;
        setAvailableMonths([currentMonth]);
        if (!selectedMonth) setSelectedMonth(currentMonth);
      }
    } catch (err) {
      console.error('Error fetching months:', err);
      // Fallback: use current month
      const currentMonth = currentDate.getMonth() + 1;
      setAvailableMonths([currentMonth]);
      if (!selectedMonth) setSelectedMonth(currentMonth);
    }
  };

  // Fetch dashboard data
  const fetchDashboardData = async () => {
    if (!selectedYear || !selectedMonth) return;
    
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(`${API_BASE}/dashboard_data.php?year=${selectedYear}&month=${selectedMonth}`);
      
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      
      const data = await response.json();
      
      if (data.status === 'success') {
        setDashboardData(data);
      } else {
        throw new Error(data.message || 'Failed to load dashboard data');
      }
    } catch (err) {
      console.error('Error fetching dashboard data:', err);
      setError(err.message);
      setDashboardData(null);
    } finally {
      setLoading(false);
    }
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
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    });
  };

  const formatTime = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleTimeString('en-PH', {
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  // Calculate occupancy rate
  const calculateOccupancyRate = () => {
    if (!dashboardData?.totals) return 0;
    const { active_stalls, available_stalls } = dashboardData.totals;
    const total = safeParseFloat(active_stalls) + safeParseFloat(available_stalls);
    return total > 0 ? (safeParseFloat(active_stalls) / total) * 100 : 0;
  };

  // Calculate collection rate
  const calculateCollectionRate = () => {
    if (!dashboardData?.totals) return 0;
    const { monthly_revenue, this_month_payments } = dashboardData.totals;
    const revenue = safeParseFloat(monthly_revenue);
    const collected = safeParseFloat(this_month_payments);
    return revenue > 0 ? (collected / revenue) * 100 : 0;
  };

  // Export functions
  const exportToExcel = (data, fileName, sheetName = 'Sheet1') => {
    try {
      if (!data || data.length === 0) {
        alert('No data available to export');
        return;
      }

      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.json_to_sheet(data);
      XLSX.utils.book_append_sheet(wb, ws, sheetName);
      XLSX.writeFile(wb, `${fileName}_${selectedYear}_${selectedMonth}.xlsx`);
      
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting to Excel');
    }
  };

  const exportSummaryReport = () => {
    if (!dashboardData?.totals) return;
    
    setExportLoading(true);
    try {
      const occupancyRate = calculateOccupancyRate();
      const collectionRate = calculateCollectionRate();
      
      const summaryData = [{
        'Year': selectedYear,
        'Month': MONTHS[selectedMonth - 1],
        'Total Stalls': dashboardData.totals.total_citizens || 0,
        'Active Stalls': dashboardData.totals.active_stalls,
        'Available Stalls': dashboardData.totals.available_stalls,
        'Occupancy Rate (%)': occupancyRate.toFixed(1),
        'Total Renters': dashboardData.totals.active_renters,
        'Monthly Revenue (PHP)': dashboardData.totals.monthly_revenue,
        'Total Contract Value (PHP)': dashboardData.totals.total_contract_value,
        'Collection Rate (%)': collectionRate.toFixed(1),
        'Overdue Payments (PHP)': dashboardData.totals.overdue_payments,
        'Pending Applications': dashboardData.totals.pending_applications,
        "Today's Collection (PHP)": dashboardData.totals.today_payments
      }];

      exportToExcel(summaryData, `Market_Summary_${selectedYear}_${selectedMonth}`, 'Summary');
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting summary report');
    } finally {
      setExportLoading(false);
    }
  };

  const exportRevenueReport = () => {
    if (!dashboardData?.revenue_trend) return;
    
    setExportLoading(true);
    try {
      const revenueData = dashboardData.revenue_trend.map(item => ({
        'Month': item.month,
        'Revenue (PHP)': item.revenue,
        'Year': selectedYear
      }));

      exportToExcel(revenueData, `Market_Revenue_${selectedYear}`, 'Revenue Trend');
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting revenue report');
    } finally {
      setExportLoading(false);
    }
  };

  const exportCompleteDashboardReport = () => {
    if (!dashboardData) return;
    
    setExportLoading(true);
    try {
      const occupancyRate = calculateOccupancyRate();
      const collectionRate = calculateCollectionRate();
      
      const wb = XLSX.utils.book_new();
      const dateStr = new Date().toISOString().split('T')[0];
      
      // Summary Sheet
      const summaryData = [{
        'Year': selectedYear,
        'Month': MONTHS[selectedMonth - 1],
        'Total Stalls': dashboardData.totals.total_citizens || 0,
        'Active Stalls': dashboardData.totals.active_stalls,
        'Available Stalls': dashboardData.totals.available_stalls,
        'Occupancy Rate (%)': occupancyRate.toFixed(1),
        'Active Renters': dashboardData.totals.active_renters,
        'Monthly Revenue (PHP)': formatCurrency(dashboardData.totals.monthly_revenue),
        'Collection Rate (%)': collectionRate.toFixed(1),
        'Today\'s Collection (PHP)': formatCurrency(dashboardData.totals.today_payments),
        'Overdue Payments (PHP)': formatCurrency(dashboardData.totals.overdue_payments),
        'Data Updated': new Date().toLocaleString()
      }];
      
      const ws1 = XLSX.utils.json_to_sheet(summaryData);
      XLSX.utils.book_append_sheet(wb, ws1, 'Dashboard Summary');
      
      XLSX.writeFile(wb, `Market_Dashboard_Complete_Report_${selectedYear}_${selectedMonth}_${dateStr}.xlsx`);
      
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting complete report');
    } finally {
      setExportLoading(false);
    }
  };

  // Loading state
  if (loadingYears) {
    return (
      <div className="flex flex-col justify-center items-center h-screen bg-white">
        <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-gray-800 mb-4"></div>
        <p className="text-gray-600">Loading Market Dashboard...</p>
        <p className="text-sm text-gray-400 mt-2">Fetching available data years</p>
      </div>
    );
  }

  // Get data for display
  const occupancyRate = calculateOccupancyRate();
  const collectionRate = calculateCollectionRate();
  const revenueData = dashboardData?.revenue_trend || [];
  const businessTypeData = dashboardData?.business_types || [];
  const recentPayments = dashboardData?.recent_payments || [];
  const pendingApplications = [];

  return (
    <div className="min-h-screen bg-white">
      {/* Header - Clean White Design */}
      <div className="border-b border-gray-200 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
              <h1 className="text-2xl font-bold text-gray-900 mb-1">
                Market Rent & Revenue Dashboard
              </h1>
              <div className="flex items-center gap-3 text-sm text-gray-500">
                <div className="flex items-center gap-1">
                  <CalendarIcon className="w-4 h-4" />
                  <span>
                    {selectedMonth ? MONTHS[selectedMonth - 1] : ''} {selectedYear} • 
                    {currentDate.toLocaleDateString('en-PH', { 
                      month: 'long', 
                      day: 'numeric', 
                      year: 'numeric'
                    })}
                  </span>
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
                  <CalendarIcon className="w-4 h-4" />
                  <span>Year: {selectedYear}</span>
                  <ChevronDown className="w-4 h-4" />
                </button>
                
                {yearDropdownOpen && (
                  <div className="absolute top-full right-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
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

              {/* Month Selection */}
              <div className="relative">
                <button
                  onClick={() => setMonthDropdownOpen(!monthDropdownOpen)}
                  className="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700"
                >
                  <CalendarDays className="w-4 h-4" />
                  <span>Month: {selectedMonth ? MONTHS[selectedMonth - 1] : 'Select'}</span>
                  <ChevronDown className="w-4 h-4" />
                </button>
                
                {monthDropdownOpen && (
                  <div className="absolute top-full right-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                    <div className="py-1 max-h-60 overflow-y-auto">
                      {availableMonths.map(month => (
                        <button
                          key={month}
                          onClick={() => {
                            setSelectedMonth(month);
                            setMonthDropdownOpen(false);
                          }}
                          className={`w-full text-left px-4 py-2 hover:bg-gray-50 transition-colors ${
                            selectedMonth === month 
                              ? 'bg-gray-100 text-gray-900 font-medium' 
                              : 'text-gray-700'
                          }`}
                        >
                          <div className="flex items-center justify-between">
                            <span>{MONTHS[month - 1]}</span>
                            {selectedMonth === month && (
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
                onClick={fetchDashboardData}
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
            {availableYears.map(year => (
              <button
                key={year}
                onClick={() => setSelectedYear(year)}
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
          <div className="p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <div className="flex items-center gap-2">
              <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-blue-600"></div>
              <span className="text-sm text-blue-600">Preparing Excel export for {selectedYear} {MONTHS[selectedMonth - 1]}...</span>
            </div>
          </div>
        )}

        {/* Export Buttons */}
        <div className="bg-white border border-gray-200 rounded-xl p-4">
          <div className="flex flex-wrap gap-2">
            <button
              onClick={exportSummaryReport}
              disabled={exportLoading || !dashboardData}
              className="flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm disabled:opacity-50"
            >
              <CalendarIcon className="w-4 h-4" />
              Summary Report
            </button>
            <button
              onClick={exportRevenueReport}
              disabled={exportLoading || !dashboardData?.revenue_trend}
              className="flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm disabled:opacity-50"
            >
              <TrendingUp className="w-4 h-4" />
              Revenue Report
            </button>
            <button
              onClick={exportCompleteDashboardReport}
              disabled={exportLoading}
              className="flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm disabled:opacity-50"
            >
              <Database className="w-4 h-4" />
              Complete Report
            </button>
          </div>
        </div>

        {/* Error Message */}
        {error && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-4">
            <div className="flex items-center">
              <AlertCircle className="w-5 h-5 text-red-500 mr-2" />
              <p className="text-red-700">{error}</p>
            </div>
          </div>
        )}

        {/* Loading Indicator */}
        {loading && (
          <div className="flex justify-center items-center py-12">
            <div className="text-center">
              <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-gray-800 mx-auto mb-4"></div>
              <p className="text-gray-600">Loading dashboard data...</p>
              <p className="text-sm text-gray-400">
                {selectedYear} - {MONTHS[selectedMonth - 1]}
              </p>
            </div>
          </div>
        )}

        {/* Dashboard Content */}
        {!loading && dashboardData && (
          <>
            {/* Key Metrics Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              {/* Collection Rate Card */}
              <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                <div className="flex items-center justify-between mb-4">
                  <div className="p-3 bg-blue-50 rounded-lg">
                    <PercentIcon className="w-6 h-6 text-blue-600" />
                  </div>
                  <span className={`text-sm px-3 py-1 rounded-full ${
                    collectionRate >= 90 ? 'bg-green-100 text-green-800' :
                    collectionRate >= 75 ? 'bg-yellow-100 text-yellow-800' :
                    'bg-red-100 text-red-800'
                  }`}>
                    {formatPercent(collectionRate)}
                  </span>
                </div>
                <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-2">
                  Collection Rate
                </h3>
                <p className="text-2xl font-bold text-gray-900 mb-4">
                  {formatCurrency(dashboardData.totals?.this_month_payments)}
                </p>
                <div className="text-sm text-gray-600">
                  <div className="flex justify-between mb-1">
                    <span>Target:</span>
                    <span className="font-medium">{formatCurrency(dashboardData.totals?.monthly_revenue)}</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2">
                    <div 
                      className={`h-2 rounded-full ${
                        collectionRate >= 90 ? 'bg-green-500' :
                        collectionRate >= 75 ? 'bg-yellow-500' :
                        'bg-red-500'
                      }`}
                      style={{ width: `${Math.min(collectionRate, 100)}%` }}
                    ></div>
                  </div>
                </div>
              </div>

              {/* Monthly Revenue Card */}
              <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                <div className="flex items-center justify-between mb-4">
                  <div className="p-3 bg-green-50 rounded-lg">
                    <CalculatorIcon className="w-6 h-6 text-green-600" />
                  </div>
                  <span className="text-sm px-3 py-1 bg-gray-100 text-gray-800 rounded-full">
                    {MONTHS[selectedMonth - 1]} Assessment
                  </span>
                </div>
                <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-2">
                  Monthly Revenue
                </h3>
                <p className="text-2xl font-bold text-gray-900 mb-4">
                  {formatCurrency(dashboardData.totals?.monthly_revenue)}
                </p>
                <div className="space-y-2 text-sm text-gray-600">
                  <div className="flex justify-between">
                    <span className="flex items-center gap-2">
                      <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                      Today's Collection:
                    </span>
                    <span>{formatCurrency(dashboardData.totals?.today_payments)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="flex items-center gap-2">
                      <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                      Total Contracts:
                    </span>
                    <span>{formatCurrency(dashboardData.totals?.total_contract_value)}</span>
                  </div>
                </div>
              </div>

              {/* Occupancy Rate Card */}
              <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                <div className="flex items-center justify-between mb-4">
                  <div className="p-3 bg-yellow-50 rounded-lg">
                    <Building className="w-6 h-6 text-yellow-600" />
                  </div>
                  <span className="text-sm px-3 py-1 bg-gray-100 text-gray-800 rounded-full">
                    Stall Utilization
                  </span>
                </div>
                <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-2">
                  Occupancy Rate
                </h3>
                <p className="text-2xl font-bold text-gray-900 mb-4">{formatPercent(occupancyRate)}</p>
                <div className="text-sm text-gray-600">
                  <div className="flex justify-between mb-1">
                    <span>Active:</span>
                    <span className="font-medium">{formatNumber(dashboardData.totals?.active_stalls)}</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2">
                    <div 
                      className={`h-2 rounded-full ${
                        occupancyRate >= 85 ? 'bg-green-500' :
                        occupancyRate >= 70 ? 'bg-yellow-500' :
                        'bg-blue-500'
                      }`}
                      style={{ width: `${Math.min(occupancyRate, 100)}%` }}
                    ></div>
                  </div>
                </div>
              </div>

              {/* Outstanding Balance Card */}
              <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                <div className="flex items-center justify-between mb-4">
                  <div className="p-3 bg-red-50 rounded-lg">
                    <AlertTriangleIcon className="w-6 h-6 text-red-600" />
                  </div>
                  <span className="text-sm px-3 py-1 bg-red-100 text-red-800 rounded-full">
                    Delinquent
                  </span>
                </div>
                <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-2">
                  Overdue Payments
                </h3>
                <p className="text-2xl font-bold text-gray-900 mb-4">
                  {formatCurrency(dashboardData.totals?.overdue_payments)}
                </p>
                <div className="space-y-2 text-sm text-gray-600">
                  <div className="flex justify-between">
                    <span>Active Renters:</span>
                    <span>{formatNumber(dashboardData.totals?.active_renters)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Total Stalls:</span>
                    <span>{formatNumber(dashboardData.totals?.total_citizens || 0)}</span>
                  </div>
                  <div className="flex justify-between font-medium">
                    <span>Available Stalls:</span>
                    <span>{formatNumber(dashboardData.totals?.available_stalls)}</span>
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
                    <GridIcon className="w-4 h-4" />
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
                    <BarChart4 className="w-4 h-4" />
                    Charts
                  </div>
                </button>
              </div>
            </div>

            {/* Charts Section */}
            {viewMode === 'charts' && (
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Revenue Trend Chart */}
                <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                  <div className="flex justify-between items-center mb-6">
                    <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                      <Activity className="w-5 h-5 text-gray-600" />
                      Monthly Revenue Trend {selectedYear}
                    </h3>
                    <button
                      onClick={exportRevenueReport}
                      disabled={exportLoading || revenueData.length === 0}
                      className="text-sm text-gray-600 hover:text-gray-700 disabled:opacity-50"
                    >
                      Export
                    </button>
                  </div>
                  <div className="h-72">
                    {revenueData.length > 0 ? (
                      <ResponsiveContainer width="100%" height="100%">
                        <LineChart data={revenueData}>
                          <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                          <XAxis dataKey="month" />
                          <YAxis 
                            tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
                          />
                          <Tooltip 
                            formatter={(value) => [formatCurrency(value), 'Revenue']}
                            labelFormatter={(label) => `Month: ${label}`}
                          />
                          <Legend />
                          <Line 
                            type="monotone" 
                            dataKey="revenue" 
                            stroke="#3b82f6" 
                            strokeWidth={3}
                            dot={{ r: 4 }}
                            activeDot={{ r: 6 }}
                          />
                        </LineChart>
                      </ResponsiveContainer>
                    ) : (
                      <div className="flex flex-col items-center justify-center h-full text-gray-400">
                        <TrendingUp className="w-12 h-12 mb-2" />
                        <p>No revenue data available for {selectedYear}</p>
                      </div>
                    )}
                  </div>
                </div>

                {/* Business Types Chart */}
                <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                  <div className="flex justify-between items-center mb-6">
                    <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                      <PieChartLucide className="w-5 h-5 text-gray-600" />
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
                            dataKey="count"
                            nameKey="business_type"
                          >
                            {businessTypeData.map((entry, index) => (
                              <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                            ))}
                          </Pie>
                          <Tooltip 
                            formatter={(value, name, props) => {
                              const percentage = (props.payload.percentage || 0).toFixed(1);
                              return [`${value} stalls (${percentage}%)`, 'Count'];
                            }}
                          />
                          <Legend />
                        </PieChart>
                      </ResponsiveContainer>
                    ) : (
                      <div className="flex flex-col items-center justify-center h-full text-gray-400">
                        <PieChartLucide className="w-12 h-12 mb-2" />
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
                {/* Revenue Trend Cards */}
                <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                  <div className="flex justify-between items-center mb-6">
                    <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                      <TrendingUp className="w-5 h-5 text-gray-600" />
                      Revenue Trend {selectedYear}
                    </h3>
                    <button
                      onClick={exportRevenueReport}
                      disabled={exportLoading || revenueData.length === 0}
                      className="text-sm text-gray-600 hover:text-gray-700 disabled:opacity-50"
                    >
                      Export
                    </button>
                  </div>
                  <div className="space-y-4">
                    {revenueData.slice(-6).map((item, index) => (
                      <div key={index} className="p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div className="flex justify-between items-center mb-2">
                          <span className="font-medium text-gray-900">{item.month}</span>
                          <span className={`text-sm px-3 py-1 rounded-full ${
                            safeParseFloat(item.revenue) > (index > 0 ? safeParseFloat(revenueData[index - 1]?.revenue || 0) : 0)
                              ? 'bg-green-100 text-green-800'
                              : 'bg-red-100 text-red-800'
                          }`}>
                            {formatCurrency(item.revenue)}
                          </span>
                        </div>
                        <div className="flex items-center gap-3">
                          <div className="w-full bg-gray-200 rounded-full h-2">
                            <div 
                              className="h-2 rounded-full bg-blue-500"
                              style={{ 
                                width: `${Math.min(
                                  (safeParseFloat(item.revenue) / Math.max(...revenueData.map(r => safeParseFloat(r.revenue)))) * 100, 
                                  100
                                )}%` 
                              }}
                            ></div>
                          </div>
                          <span className="text-sm font-medium text-gray-700">
                            {index > 0 ? (
                              <span className={`flex items-center gap-1 ${
                                safeParseFloat(item.revenue) > safeParseFloat(revenueData[index - 1]?.revenue || 0)
                                  ? 'text-green-600'
                                  : 'text-red-600'
                              }`}>
                                {safeParseFloat(item.revenue) > safeParseFloat(revenueData[index - 1]?.revenue || 0) ? (
                                  <ArrowUpRight className="w-3 h-3" />
                                ) : (
                                  <ArrowDownRight className="w-3 h-3" />
                                )}
                                {Math.abs(
                                  ((safeParseFloat(item.revenue) - safeParseFloat(revenueData[index - 1]?.revenue || 0)) / 
                                  safeParseFloat(revenueData[index - 1]?.revenue || 1)) * 100
                                ).toFixed(1)}%
                              </span>
                            ) : 'New'}
                          </span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Business Types Cards */}
                <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                  <div className="flex justify-between items-center mb-6">
                    <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                      <Store className="w-5 h-5 text-gray-600" />
                      Business Types {selectedYear}
                    </h3>
                    <button
                      onClick={exportCompleteDashboardReport}
                      disabled={exportLoading || businessTypeData.length === 0}
                      className="text-sm text-gray-600 hover:text-gray-700 disabled:opacity-50"
                    >
                      Export
                    </button>
                  </div>
                  <div className="space-y-4">
                    {businessTypeData.slice(0, 5).map((business, index) => (
                      <div key={index} className="p-4 border border-gray-200 rounded-lg">
                        <div className="flex justify-between items-center mb-2">
                          <span className="font-medium text-gray-900">{business.business_type}</span>
                          <span className="text-sm text-gray-600">
                            {formatNumber(business.count)} stalls
                          </span>
                        </div>
                        <div className="flex items-center gap-3">
                          <div className="w-full bg-gray-200 rounded-full h-2">
                            <div 
                              className="h-2 rounded-full bg-blue-500"
                              style={{ 
                                width: `${Math.min(
                                  (safeParseFloat(business.count) / 
                                  businessTypeData.reduce((total, b) => total + safeParseFloat(b.count), 0)) * 100, 
                                  100
                                )}%` 
                              }}
                            ></div>
                          </div>
                          <span className="text-sm font-medium text-gray-700">
                            {formatPercent(
                              (safeParseFloat(business.count) / 
                              businessTypeData.reduce((total, b) => total + safeParseFloat(b.count), 0)) * 100
                            )}
                          </span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            )}

            {/* Recent Payments Section */}
            <div className="bg-white border border-gray-200 rounded-xl shadow-sm">
              <div className="p-6 border-b border-gray-200">
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                  <div>
                    <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                      <Activity className="w-5 h-5 text-gray-600" />
                      Recent Payments {selectedYear}
                    </h3>
                    <p className="text-sm text-gray-500 mt-1">
                      Latest payment transactions for {MONTHS[selectedMonth - 1]}
                    </p>
                  </div>
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
                  {recentPayments.slice(0, 5).map((payment, index) => (
                    <div key={index} className="p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                          <div className={`p-2 rounded-lg ${
                            activeTab === 'payments' ? 'bg-green-100' : 'bg-red-100'
                          }`}>
                            {activeTab === 'payments' ? (
                              <CheckCircle className="w-5 h-5 text-green-600" />
                            ) : (
                              <AlertCircle className="w-5 h-5 text-red-600" />
                            )}
                          </div>
                          <div>
                            <h4 className="font-medium text-gray-900">{payment.renter_name}</h4>
                            <p className="text-sm text-gray-500">
                              {payment.business_name || payment.rental_stall_name} • 
                              {payment.stall_rights_no || 'No Stall'} • 
                              {payment.payment_method || 'Not Specified'}
                            </p>
                          </div>
                        </div>
                        <div className="text-right">
                          <p className={`font-bold text-lg ${
                            activeTab === 'payments' ? 'text-green-600' : 'text-red-600'
                          }`}>
                            {formatCurrency(payment.amount_paid)}
                          </p>
                          <div className="flex items-center gap-2 text-sm text-gray-500">
                            <Calendar className="w-3 h-3" />
                            {formatDate(payment.payment_date)} {formatTime(payment.payment_date)}
                          </div>
                          {payment.receipt_number && (
                            <p className="text-xs text-gray-500 mt-1">Receipt: {payment.receipt_number}</p>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                  
                  {recentPayments.length === 0 && (
                    <div className="text-center py-8 text-gray-400">
                      <CreditCard className="w-12 h-12 mx-auto mb-2" />
                      <p>No {activeTab} activities available for {MONTHS[selectedMonth - 1]} {selectedYear}</p>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Performance Indicators */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div className="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-6">
                <div className="flex items-center gap-3 mb-4">
                  <div className="p-3 bg-blue-500 rounded-lg">
                    <Target className="w-6 h-6 text-white" />
                  </div>
                  <div>
                    <h4 className="font-semibold text-gray-900">Target Achievement</h4>
                    <p className="text-sm text-gray-600">Monthly collection target</p>
                  </div>
                </div>
                <div className="mb-4">
                  <div className="flex justify-between text-sm mb-1">
                    <span className="text-gray-600">Progress</span>
                    <span className="font-semibold text-blue-600">
                      {formatPercent(collectionRate)}
                    </span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2">
                    <div 
                      className="bg-blue-600 h-2 rounded-full"
                      style={{ width: `${Math.min(collectionRate, 100)}%` }}
                    ></div>
                  </div>
                </div>
              </div>

              <div className="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-6">
                <div className="flex items-center gap-3 mb-4">
                  <div className="p-3 bg-green-500 rounded-lg">
                    <CheckCheck className="w-6 h-6 text-white" />
                  </div>
                  <div>
                    <h4 className="font-semibold text-gray-900">Stall Utilization</h4>
                    <p className="text-sm text-gray-600">Optimal space usage</p>
                  </div>
                </div>
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-2xl font-bold text-gray-900">
                      {formatNumber(dashboardData.totals?.active_stalls)}
                      <span className="text-sm font-normal text-gray-600"> / {formatNumber(safeParseFloat(dashboardData.totals?.active_stalls) + safeParseFloat(dashboardData.totals?.available_stalls))}</span>
                    </p>
                    <p className="text-sm text-gray-600">Stalls occupied</p>
                  </div>
                  <div className={`p-2 rounded-full ${occupancyRate >= 85 ? 'bg-green-100 text-green-600' : 'bg-yellow-100 text-yellow-600'}`}>
                    {occupancyRate >= 85 ? (
                      <TrendingUp className="w-5 h-5" />
                    ) : (
                      <TrendingDown className="w-5 h-5" />
                    )}
                  </div>
                </div>
              </div>

              <div className="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-6">
                <div className="flex items-center gap-3 mb-4">
                  <div className="p-3 bg-purple-500 rounded-lg">
                    <Shield className="w-6 h-6 text-white" />
                  </div>
                  <div>
                    <h4 className="font-semibold text-gray-900">Risk Level</h4>
                    <p className="text-sm text-gray-600">Overdue payments risk</p>
                  </div>
                </div>
                <div className="flex items-center justify-between">
                  <div>
                    <p className={`text-2xl font-bold ${
                      safeParseFloat(dashboardData.totals?.overdue_payments) > 0 ? 'text-red-600' : 'text-green-600'
                    }`}>
                      {safeParseFloat(dashboardData.totals?.overdue_payments) > 0 ? 'High' : 'Low'}
                    </p>
                    <p className="text-sm text-gray-600">
                      {safeParseFloat(dashboardData.totals?.overdue_payments) > 0 
                        ? 'Attention needed' 
                        : 'All payments on track'}
                    </p>
                  </div>
                  <div className={`p-2 rounded-full ${
                    safeParseFloat(dashboardData.totals?.overdue_payments) > 0 
                      ? 'bg-red-100 text-red-600' 
                      : 'bg-green-100 text-green-600'
                  }`}>
                    {safeParseFloat(dashboardData.totals?.overdue_payments) > 0 ? (
                      <AlertTriangle className="w-5 h-5" />
                    ) : (
                      <CheckCircle className="w-5 h-5" />
                    )}
                  </div>
                </div>
              </div>
            </div>
          </>
        )}

        {/* Empty State */}
        {!loading && !dashboardData && !error && (
          <div className="text-center py-16">
            <Store className="w-16 h-16 text-gray-300 mx-auto mb-4" />
            <h3 className="text-lg font-semibold text-gray-700 mb-2">No Data Available</h3>
            <p className="text-gray-500 mb-6">
              No dashboard data found for {selectedYear} - {MONTHS[selectedMonth - 1]}
            </p>
            <button
              onClick={fetchDashboardData}
              className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
              Try Again
            </button>
          </div>
        )}

        {/* Footer Summary */}
        <div className="text-center text-sm text-gray-500 pt-6 border-t border-gray-200">
          <p>Market Rent & Revenue Dashboard • {MONTHS[selectedMonth - 1]} {selectedYear} • 
            Updated {currentDate.toLocaleDateString('en-PH', { 
              month: 'long', 
              day: 'numeric', 
              year: 'numeric'
            })} at {currentDate.toLocaleTimeString('en-PH', {
              hour: '2-digit',
              minute: '2-digit'
            })}
          </p>
          <p className="text-xs text-gray-400 mt-1">
            Available years: {availableYears.join(', ')}
          </p>
        </div>
      </div>
    </div>
  );
}