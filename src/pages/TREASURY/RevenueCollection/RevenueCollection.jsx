import React, { useState, useEffect, useMemo } from 'react';
import { 
  BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, 
  Tooltip, Legend, ResponsiveContainer, LineChart, Line, Area,
  RadarChart, PolarGrid, PolarAngleAxis, PolarRadiusAxis, Radar,
  AreaChart
} from 'recharts';
import { 
  DollarSign, TrendingUp, TrendingDown, RefreshCw, Filter,
  Download, FileText, BarChart3, PieChart as PieChartIcon,
  Calendar, AlertCircle, CheckCircle, Clock, XCircle,
  ArrowUpRight, ArrowDownRight, CircleDollarSign, CreditCard,
  Wallet, Timer, CalendarDays, TrendingUp as TrendingUpIcon,
  Banknote, AlertTriangle, CheckCheck, ArrowRightLeft, 
  Building2, Layers, Grid3x3, Compass, LandPlot, FileBarChart,
  Activity, LineChart as LineChartIcon, Calculator,
  FileSpreadsheet, Database, Table, ChevronDown, ChevronUp,
  Archive, BarChart4, TrendingDown as TrendingDownIcon,
  Map, Award, Trophy, Star, Percent as PercentIcon, 
  Target as TargetIcon, Users as UsersIcon, TrendingUp as TrendingUpIcon2,
  Building, Home, Users, Tag, Eye, Target, Percent,
  Building as BuildingIcon, DollarSign as DollarSignIcon,
  Building as Building2Icon, PieChart as PieIcon,
  Landmark, ShieldAlert, ArrowUpRight as ArrowUpRightIcon,
  ArrowDownRight as ArrowDownRightIcon, Filter as FilterIcon,
  ChevronRight, ChevronLeft, Loader2, ExternalLink,
  BarChart2, LineChart as LineChartIcon2, PieChart as PieChartIcon2,
  Zap, TrendingUp as TrendingUpIcon3, TrendingDown as TrendingDownIcon2,
  Search, CheckSquare, Clock as ClockIcon, AlertOctagon
} from 'lucide-react';
import * as XLSX from 'xlsx';

// Custom colors matching the RPT dashboard
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

// System-specific colors
const SYSTEM_COLORS = {
  'rpt': '#4CAF50',
  'business': '#2196F3',
  'market': '#FF9800',
  'market_rent': '#FF5722',
  'sanitation': '#9C27B0',
  'wss': '#00BCD4',
  'franchise': '#795548',
  'tmm': '#F44336',
  'zoning': '#3F51B5',
  'cemetery': '#009688',
  'Unknown': '#607D8B'
};

const CHART_COLORS = ['#4a90e2', '#9aa5b1', '#4caf50', '#ff9800', '#2196f3', '#f44336', '#673ab7'];

export default function RevenueCollectionImproved() {
  const [transactions, setTransactions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [dateRange, setDateRange] = useState({
    start: '2026-01-01',
    end: '2026-02-07'
  });
  const [selectedSystem, setSelectedSystem] = useState('all');
  const [activeTab, setActiveTab] = useState('overview');
  const [viewMode, setViewMode] = useState('charts');
  const [exportLoading, setExportLoading] = useState(false);
  const [availableSystems, setAvailableSystems] = useState([]);
  const [revenueStats, setRevenueStats] = useState(null);

  const API_URL = window.location.hostname === "localhost"
    ? "http://localhost/revenue2/backend/Digital/revenue/get_transactions.php"
    : "https://revenuetreasury.goserveph.com/backend/Digital/revenue/get_transactions.php";

  const getSystemName = (system) => {
    const names = {
      'rpt': 'Real Property Tax',
      'business': 'Business Tax',
      'market': 'Market Stall',
      'market_rent': 'Market Rent',
      'sanitation': 'Sanitation Fees',
      'wss': 'Water & Sanitation',
      'franchise': 'Franchise Fees',
      'tmm': 'Traffic Fines',
      'zoning': 'Zoning Fees',
      'cemetery': 'Cemetery Fees'
    };
    return names[system] || system || 'Unknown';
  };

  const getSystemIcon = (system) => {
    const icons = {
      'rpt': <Landmark className="w-4 h-4" />,
      'business': <Building2 className="w-4 h-4" />,
      'market': <Home className="w-4 h-4" />,
      'market_rent': <BuildingIcon className="w-4 h-4" />,
      'sanitation': <AlertCircle className="w-4 h-4" />,
      'wss': <Droplets className="w-4 h-4" />,
      'franchise': <FileText className="w-4 h-4" />,
      'tmm': <Car className="w-4 h-4" />,
      'zoning': <Map className="w-4 h-4" />,
      'cemetery': <Cross className="w-4 h-4" />
    };
    return icons[system] || <DollarSign className="w-4 h-4" />;
  };

  // Load data
  const loadData = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const params = new URLSearchParams({
        start_date: dateRange.start,
        end_date: dateRange.end,
        limit: 5000
      });
      
      if (selectedSystem !== 'all') params.append('client_system', selectedSystem);
      
      console.log('Fetching from:', `${API_URL}?${params.toString()}`);
      
      const response = await fetch(`${API_URL}?${params.toString()}`);
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const result = await response.json();
      console.log('API Response:', result);
      
      if (result.status === 'success') {
        const data = result.data?.transactions || [];
        setTransactions(data);
        
        // Extract available systems
        const systems = [...new Set(data.map(t => t.client_system).filter(Boolean))];
        setAvailableSystems(systems);
        
        // Calculate comprehensive stats
        const stats = calculateRevenueStats(data);
        setRevenueStats(stats);
      } else {
        throw new Error(result.message || 'Failed to load data');
      }
    } catch (error) {
      console.error('Error loading data:', error);
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  const calculateRevenueStats = (transactions) => {
    // Filter only paid transactions (actual revenue collected)
    const paidTransactions = transactions.filter(t => t.payment_status === 'paid');
    const pendingTransactions = transactions.filter(t => t.payment_status === 'pending');
    const failedTransactions = transactions.filter(t => t.payment_status === 'failed' || t.payment_status === 'cancelled');

    // Calculate total revenue collected
    const totalRevenue = paidTransactions.reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
    
    // Calculate revenue by system
    const systemRevenue = {};
    paidTransactions.forEach(t => {
      const system = t.client_system || 'Unknown';
      if (!systemRevenue[system]) {
        systemRevenue[system] = {
          revenue: 0,
          count: 0,
          avgAmount: 0
        };
      }
      systemRevenue[system].revenue += parseFloat(t.amount || 0);
      systemRevenue[system].count++;
    });

    // Calculate averages
    Object.keys(systemRevenue).forEach(system => {
      systemRevenue[system].avgAmount = systemRevenue[system].revenue / systemRevenue[system].count;
    });

    // Convert to array for charts
    const systemRevenueArray = Object.entries(systemRevenue).map(([system, data]) => ({
      system,
      name: getSystemName(system),
      revenue: data.revenue,
      count: data.count,
      avgAmount: data.avgAmount,
      percentage: totalRevenue > 0 ? (data.revenue / totalRevenue * 100) : 0
    })).sort((a, b) => b.revenue - a.revenue);

    // Calculate daily revenue
    const dailyRevenue = {};
    paidTransactions.forEach(t => {
      const date = t.transaction_date || t.created_at?.split(' ')[0];
      if (date) {
        if (!dailyRevenue[date]) {
          dailyRevenue[date] = {
            revenue: 0,
            count: 0
          };
        }
        dailyRevenue[date].revenue += parseFloat(t.amount || 0);
        dailyRevenue[date].count++;
      }
    });

    const dailyRevenueArray = Object.entries(dailyRevenue).map(([date, data]) => ({
      date,
      revenue: data.revenue,
      count: data.count
    })).sort((a, b) => new Date(a.date) - new Date(b.date));

    // Payment method revenue
    const methodRevenue = {};
    paidTransactions.forEach(t => {
      const method = t.payment_method || 'Unknown';
      if (!methodRevenue[method]) {
        methodRevenue[method] = {
          revenue: 0,
          count: 0
        };
      }
      methodRevenue[method].revenue += parseFloat(t.amount || 0);
      methodRevenue[method].count++;
    });

    const methodRevenueArray = Object.entries(methodRevenue).map(([method, data]) => ({
      method,
      revenue: data.revenue,
      count: data.count,
      percentage: totalRevenue > 0 ? (data.revenue / totalRevenue * 100) : 0
    })).sort((a, b) => b.revenue - a.revenue);

    // Monthly revenue
    const monthlyRevenue = {};
    paidTransactions.forEach(t => {
      const date = t.transaction_date || t.created_at?.split(' ')[0];
      if (date) {
        const month = date.substring(0, 7); // YYYY-MM format
        if (!monthlyRevenue[month]) {
          monthlyRevenue[month] = {
            revenue: 0,
            count: 0
          };
        }
        monthlyRevenue[month].revenue += parseFloat(t.amount || 0);
        monthlyRevenue[month].count++;
      }
    });

    const monthlyRevenueArray = Object.entries(monthlyRevenue).map(([month, data]) => ({
      month,
      revenue: data.revenue,
      count: data.count
    })).sort((a, b) => a.month.localeCompare(b.month));

    return {
      totalRevenue,
      totalTransactions: paidTransactions.length,
      pendingTransactions: pendingTransactions.length,
      failedTransactions: failedTransactions.length,
      systemRevenue: systemRevenueArray,
      dailyRevenue: dailyRevenueArray,
      monthlyRevenue: monthlyRevenueArray,
      methodRevenue: methodRevenueArray,
      avgTransaction: paidTransactions.length > 0 ? totalRevenue / paidTransactions.length : 0,
      maxDailyRevenue: dailyRevenueArray.length > 0 ? Math.max(...dailyRevenueArray.map(d => d.revenue)) : 0,
      minDailyRevenue: dailyRevenueArray.length > 0 ? Math.min(...dailyRevenueArray.map(d => d.revenue)) : 0,
      totalDays: dailyRevenueArray.length,
      peakDay: dailyRevenueArray.length > 0 ? 
        dailyRevenueArray.find(d => d.revenue === Math.max(...dailyRevenueArray.map(d => d.revenue)))?.date : null
    };
  };

  useEffect(() => {
    loadData();
  }, [dateRange, selectedSystem]);

  const formatCurrency = (amount) => {
    if (amount === null || amount === undefined || amount === '' || isNaN(amount)) return '₱0.00';
    
    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
    
    if (numAmount >= 1000000000) return `₱${(numAmount / 1000000000).toFixed(2)}B`;
    if (numAmount >= 1000000) return `₱${(numAmount / 1000000).toFixed(2)}M`;
    if (numAmount >= 1000) return `₱${(numAmount / 1000).toFixed(2)}K`;
    return `₱${numAmount.toFixed(2)}`;
  };

  const formatNumber = (num) => {
    if (num === null || num === undefined || num === '' || isNaN(num)) return '0';
    const parsedNum = typeof num === 'string' ? parseFloat(num) : num;
    return new Intl.NumberFormat('en-PH').format(parsedNum);
  };

  const formatPercent = (value) => {
    const parsedValue = parseFloat(value);
    return `${parsedValue.toFixed(1)}%`;
  };

  const exportToExcel = (data, fileName, sheetName = 'Sheet1') => {
    try {
      if (!data || data.length === 0) {
        alert('No data available to export');
        return;
      }

      setExportLoading(true);
      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.json_to_sheet(data);
      XLSX.utils.book_append_sheet(wb, ws, sheetName);
      XLSX.writeFile(wb, `${fileName}_${dateRange.start}_to_${dateRange.end}.xlsx`);
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting to Excel');
    } finally {
      setExportLoading(false);
    }
  };

  const exportRevenueReport = () => {
    if (!revenueStats) return;
    
    try {
      const exportData = [
        {
          'Period': `${dateRange.start} to ${dateRange.end}`,
          'Total Revenue': revenueStats.totalRevenue,
          'Total Transactions': revenueStats.totalTransactions,
          'Pending Transactions': revenueStats.pendingTransactions,
          'Average Transaction': revenueStats.avgTransaction,
          'Peak Day': revenueStats.peakDay,
          'Peak Day Revenue': revenueStats.maxDailyRevenue
        }
      ];

      exportToExcel(exportData, `Revenue_Report`, 'Summary');
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting revenue report');
    }
  };

  const exportSystemRevenue = () => {
    if (!revenueStats?.systemRevenue) return;
    
    const systemData = revenueStats.systemRevenue.map(s => ({
      'System': getSystemName(s.system),
      'Revenue': s.revenue,
      'Transactions': s.count,
      'Average Amount': s.avgAmount,
      'Percentage of Total': `${s.percentage.toFixed(1)}%`
    }));

    exportToExcel(systemData, `System_Revenue_Report`, 'System Revenue');
  };

  if (loading) {
    return (
      <div className="flex flex-col justify-center items-center h-screen" style={{ backgroundColor: COLORS.background }}>
        <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-gray-800 mb-4"></div>
        <p className="text-gray-600">Loading Revenue Collection Dashboard...</p>
        <p className="text-sm text-gray-400 mt-2">Fetching data from {dateRange.start} to {dateRange.end}</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="max-w-4xl mx-auto p-6" style={{ backgroundColor: COLORS.background }}>
        <div className="bg-red-50 border border-red-200 rounded-xl p-6">
          <div className="flex items-center space-x-3 mb-4">
            <AlertCircle className="w-8 h-8 text-red-600" />
            <div>
              <h3 className="text-lg font-semibold text-red-600">Error Loading Revenue Data</h3>
              <p className="text-red-600">{error}</p>
            </div>
          </div>
          <button 
            onClick={loadData}
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

  const CustomTooltip = ({ active, payload, label }) => {
    if (active && payload && payload.length) {
      return (
        <div className="bg-white p-3 border rounded-lg shadow-lg" style={{ borderColor: COLORS.secondary }}>
          <p className="font-medium mb-2" style={{ color: COLORS.dark }}>{label}</p>
          {payload.map((entry, index) => (
            <div key={index} className="flex items-center justify-between gap-4">
              <div className="flex items-center gap-2">
                <div className="w-3 h-3 rounded-full" style={{ backgroundColor: entry.color }}></div>
                <span style={{ color: COLORS.dark }}>{entry.name}:</span>
              </div>
              <span className="font-semibold" style={{ color: entry.color }}>
                {entry.dataKey === 'revenue' || entry.dataKey === 'value' ? 
                  formatCurrency(entry.value) : 
                  formatNumber(entry.value)
                }
              </span>
            </div>
          ))}
        </div>
      );
    }
    return null;
  };

  return (
    <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
      {/* Header */}
      <div className="border-b" style={{ backgroundColor: 'white', borderColor: '#e5e7eb' }}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
              <h1 className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>
                Revenue Collection Dashboard
              </h1>
              <div className="flex items-center gap-3 text-sm" style={{ color: COLORS.secondary }}>
                <div className="flex items-center gap-1">
                  <Calendar className="w-4 h-4" />
                  <span>{dateRange.start} to {dateRange.end}</span>
                </div>
                <div className="flex items-center gap-1">
                  <DollarSign className="w-4 h-4" />
                  <span>Total Revenue: {formatCurrency(revenueStats?.totalRevenue || 0)}</span>
                </div>
              </div>
            </div>
            
            <div className="flex flex-wrap gap-3 items-center">
              <button
                onClick={loadData}
                className="flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all"
                style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
              >
                <RefreshCw className="w-4 h-4" />
                Refresh
              </button>
              
              <button
                onClick={exportRevenueReport}
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
          
          {/* Filters */}
          <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="block text-sm font-medium mb-1" style={{ color: COLORS.dark }}>Start Date</label>
              <input
                type="date"
                value={dateRange.start}
                onChange={(e) => setDateRange({...dateRange, start: e.target.value})}
                className="w-full p-2 border rounded-lg transition-all focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                style={{ borderColor: COLORS.secondary }}
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1" style={{ color: COLORS.dark }}>End Date</label>
              <input
                type="date"
                value={dateRange.end}
                onChange={(e) => setDateRange({...dateRange, end: e.target.value})}
                className="w-full p-2 border rounded-lg transition-all focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                style={{ borderColor: COLORS.secondary }}
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1" style={{ color: COLORS.dark }}>Revenue Source</label>
              <select
                value={selectedSystem}
                onChange={(e) => setSelectedSystem(e.target.value)}
                className="w-full p-2 border rounded-lg transition-all focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                style={{ borderColor: COLORS.secondary }}
              >
                <option value="all">All Revenue Sources</option>
                {availableSystems.map(system => (
                  <option key={system} value={system}>{getSystemName(system)}</option>
                ))}
              </select>
            </div>
          </div>
          
          {/* Quick Date Presets */}
          <div className="mt-4 flex flex-wrap gap-2">
            {[
              { label: 'Today', start: new Date().toISOString().split('T')[0], end: new Date().toISOString().split('T')[0] },
              { label: 'Yesterday', start: new Date(Date.now() - 86400000).toISOString().split('T')[0], end: new Date(Date.now() - 86400000).toISOString().split('T')[0] },
              { label: 'Last 7 Days', start: new Date(Date.now() - 604800000).toISOString().split('T')[0], end: new Date().toISOString().split('T')[0] },
              { label: 'Last 30 Days', start: new Date(Date.now() - 2592000000).toISOString().split('T')[0], end: new Date().toISOString().split('T')[0] },
              { label: 'This Month', start: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0], end: new Date().toISOString().split('T')[0] }
            ].map((preset, index) => (
              <button
                key={index}
                onClick={() => setDateRange({ start: preset.start, end: preset.end })}
                className={`px-3 py-1 text-sm rounded-lg transition-colors border ${
                  dateRange.start === preset.start ? 'text-white' : 'border-gray-300 hover:bg-gray-50'
                }`}
                style={{
                  backgroundColor: dateRange.start === preset.start ? COLORS.primary : 'transparent',
                  color: dateRange.start === preset.start ? 'white' : COLORS.dark,
                  borderColor: dateRange.start === preset.start ? COLORS.primary : COLORS.secondary
                }}
              >
                {preset.label}
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
              onClick={exportRevenueReport}
              disabled={exportLoading}
              className="flex items-center gap-2 px-3 py-2 border rounded-lg text-sm disabled:opacity-50 transition-all"
              style={{ 
                borderColor: COLORS.secondary, 
                color: COLORS.dark,
                backgroundColor: 'white'
              }}
            >
              <FileSpreadsheet className="w-4 h-4" />
              Summary Report
            </button>
            <button
              onClick={exportSystemRevenue}
              disabled={exportLoading}
              className="flex items-center gap-2 px-3 py-2 border rounded-lg text-sm disabled:opacity-50 transition-all"
              style={{ 
                borderColor: COLORS.secondary, 
                color: COLORS.dark,
                backgroundColor: 'white'
              }}
            >
              <Database className="w-4 h-4" />
              System Revenue
            </button>
          </div>
        </div>

        {/* Key Metrics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {/* Total Revenue Card */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.success}15` }}>
                <DollarSignIcon className="w-6 h-6" style={{ color: COLORS.success }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full" 
                    style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                Total Collected
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Total Revenue
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>
              {formatCurrency(revenueStats?.totalRevenue || 0)}
            </p>
            <div className="space-y-2 text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Transactions:</span>
                <span className="font-medium">{formatNumber(revenueStats?.totalTransactions || 0)}</span>
              </div>
              <div className="flex justify-between">
                <span>Average:</span>
                <span>{formatCurrency(revenueStats?.avgTransaction || 0)}</span>
              </div>
            </div>
          </div>

          {/* Successful Payments Card */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                <CheckCircle className="w-6 h-6" style={{ color: COLORS.primary }} />
              </div>
              <span className={`text-sm px-3 py-1 rounded-full ${
                (revenueStats?.totalTransactions || 0) > 100 ? 'bg-green-100 text-green-800' :
                (revenueStats?.totalTransactions || 0) > 50 ? 'bg-yellow-100 text-yellow-800' :
                'bg-blue-100 text-blue-800'
              }`}>
                {formatNumber(revenueStats?.totalTransactions || 0)}
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Successful Payments
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>
              {formatNumber(revenueStats?.totalTransactions || 0)}
            </p>
            <div className="space-y-2 text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Success Rate:</span>
                <span className="font-medium">
                  {revenueStats?.totalTransactions ? 
                    `${((revenueStats.totalTransactions / (revenueStats.totalTransactions + revenueStats.pendingTransactions + revenueStats.failedTransactions)) * 100).toFixed(1)}%` : 
                    '0%'
                  }
                </span>
              </div>
            </div>
          </div>

          {/* Pending Payments Card */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.warning}15` }}>
                <Clock className="w-6 h-6" style={{ color: COLORS.warning }} />
              </div>
              <span className={`text-sm px-3 py-1 rounded-full ${
                (revenueStats?.pendingTransactions || 0) > 20 ? 'bg-red-100 text-red-800' :
                (revenueStats?.pendingTransactions || 0) > 10 ? 'bg-yellow-100 text-yellow-800' :
                'bg-gray-100 text-gray-800'
              }`}>
                {formatNumber(revenueStats?.pendingTransactions || 0)}
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Pending Collection
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>
              {formatNumber(revenueStats?.pendingTransactions || 0)}
            </p>
            <div className="space-y-2 text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Pending Rate:</span>
                <span className="font-medium">
                  {revenueStats?.pendingTransactions ? 
                    `${((revenueStats.pendingTransactions / (revenueStats.totalTransactions + revenueStats.pendingTransactions + revenueStats.failedTransactions)) * 100).toFixed(1)}%` : 
                    '0%'
                  }
                </span>
              </div>
            </div>
          </div>

          {/* Peak Performance Card */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.info}15` }}>
                <Trophy className="w-6 h-6" style={{ color: COLORS.info }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full" 
                    style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                Peak Day
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Best Performing Day
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>
              {formatCurrency(revenueStats?.maxDailyRevenue || 0)}
            </p>
            <div className="space-y-2 text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Date:</span>
                <span className="font-medium">{revenueStats?.peakDay || 'N/A'}</span>
              </div>
              <div className="flex justify-between">
                <span>Daily Avg:</span>
                <span>{formatCurrency(revenueStats?.totalRevenue / revenueStats?.totalDays || 0)}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Tabs for different views */}
        <div className="flex justify-between items-center">
          <div className="flex space-x-2">
            <button
              onClick={() => setActiveTab('overview')}
              className={`px-4 py-2 text-sm rounded-lg transition-all ${
                activeTab === 'overview' 
                  ? 'text-white' 
                  : 'hover:bg-gray-50 border'
              }`}
              style={{
                backgroundColor: activeTab === 'overview' ? COLORS.primary : 'transparent',
                color: activeTab === 'overview' ? 'white' : COLORS.dark,
                borderColor: activeTab === 'overview' ? COLORS.primary : COLORS.secondary
              }}
            >
              Overview
            </button>
            <button
              onClick={() => setActiveTab('systems')}
              className={`px-4 py-2 text-sm rounded-lg transition-all ${
                activeTab === 'systems' 
                  ? 'text-white' 
                  : 'hover:bg-gray-50 border'
              }`}
              style={{
                backgroundColor: activeTab === 'systems' ? COLORS.primary : 'transparent',
                color: activeTab === 'systems' ? 'white' : COLORS.dark,
                borderColor: activeTab === 'systems' ? COLORS.primary : COLORS.secondary
              }}
            >
              By System
            </button>
            <button
              onClick={() => setActiveTab('trends')}
              className={`px-4 py-2 text-sm rounded-lg transition-all ${
                activeTab === 'trends' 
                  ? 'text-white' 
                  : 'hover:bg-gray-50 border'
              }`}
              style={{
                backgroundColor: activeTab === 'trends' ? COLORS.primary : 'transparent',
                color: activeTab === 'trends' ? 'white' : COLORS.dark,
                borderColor: activeTab === 'trends' ? COLORS.primary : COLORS.secondary
              }}
            >
              Trends
            </button>
          </div>

          {/* View Mode Toggle */}
          <div className="inline-flex rounded-lg border p-1" style={{ borderColor: COLORS.secondary }}>
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
          </div>
        </div>

        {/* Overview Tab Content */}
        {activeTab === 'overview' && (
          <div className="space-y-6">
            {/* Revenue by System Chart */}
            <div className="bg-white border rounded-xl p-6 shadow-sm" style={{ borderColor: COLORS.secondary }}>
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <PieChartIcon className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Revenue Distribution by System
                </h3>
                <button
                  onClick={exportSystemRevenue}
                  disabled={exportLoading}
                  className="text-sm hover:text-gray-700 disabled:opacity-50 transition-all"
                  style={{ color: COLORS.secondary }}
                >
                  Export Data
                </button>
              </div>
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div className="h-80">
                  {revenueStats?.systemRevenue && revenueStats.systemRevenue.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                      <PieChart>
                        <Pie
                          data={revenueStats.systemRevenue}
                          cx="50%"
                          cy="50%"
                          labelLine={false}
                          label={({ name, percentage }) => `${name}: ${percentage.toFixed(1)}%`}
                          outerRadius={100}
                          innerRadius={40}
                          paddingAngle={2}
                          dataKey="revenue"
                        >
                          {revenueStats.systemRevenue.map((entry, index) => (
                            <Cell 
                              key={`cell-${index}`} 
                              fill={SYSTEM_COLORS[entry.system] || CHART_COLORS[index % CHART_COLORS.length]} 
                            />
                          ))}
                        </Pie>
                        <Tooltip 
                          formatter={(value) => [formatCurrency(value), 'Revenue']}
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
                      <p>No revenue distribution data available</p>
                    </div>
                  )}
                </div>
                
                {/* System Revenue Details */}
                <div className="space-y-4">
                  <h4 className="font-semibold mb-4" style={{ color: COLORS.dark }}>Revenue Breakdown</h4>
                  <div className="space-y-3 max-h-64 overflow-y-auto pr-2">
                    {revenueStats?.systemRevenue.map((item, index) => (
                      <div key={index} 
                           className="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 transition-all"
                           style={{ borderColor: COLORS.secondary }}>
                        <div className="flex items-center gap-3">
                          <div className="w-3 h-3 rounded-full" 
                               style={{ backgroundColor: SYSTEM_COLORS[item.system] || CHART_COLORS[index % CHART_COLORS.length] }}>
                          </div>
                          <div>
                            <p className="font-medium" style={{ color: COLORS.dark }}>{item.name}</p>
                            <p className="text-sm" style={{ color: COLORS.secondary }}>
                              {item.count} transactions
                            </p>
                          </div>
                        </div>
                        <div className="text-right">
                          <p className="font-bold text-lg" style={{ color: COLORS.primary }}>
                            {formatCurrency(item.revenue)}
                          </p>
                          <div className="flex items-center justify-end gap-2 text-sm">
                            <span style={{ color: COLORS.secondary }}>{item.percentage.toFixed(1)}%</span>
                            <div className="w-24 bg-gray-200 rounded-full h-2">
                              <div 
                                className="h-2 rounded-full transition-all duration-500"
                                style={{ 
                                  width: `${Math.min(item.percentage, 100)}%`,
                                  backgroundColor: SYSTEM_COLORS[item.system] || COLORS.primary
                                }}
                              ></div>
                            </div>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>

            {/* Daily Revenue Trend */}
            <div className="bg-white border rounded-xl p-6 shadow-sm" style={{ borderColor: COLORS.secondary }}>
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <TrendingUpIcon className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Daily Revenue Collection Trend
                </h3>
              </div>
              <div className="h-80">
                {revenueStats?.dailyRevenue && revenueStats.dailyRevenue.length > 0 ? (
                  <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={revenueStats.dailyRevenue}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                      <XAxis 
                        dataKey="date" 
                        tick={{ fontSize: 12 }}
                      />
                      <YAxis 
                        tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
                        tick={{ fontSize: 12 }}
                      />
                      <Tooltip content={<CustomTooltip />} />
                      <Area 
                        type="monotone" 
                        dataKey="revenue" 
                        name="Daily Revenue"
                        stroke={COLORS.primary} 
                        fill={COLORS.primary} 
                        fillOpacity={0.1}
                        strokeWidth={2}
                      />
                    </AreaChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="flex flex-col items-center justify-center h-full" style={{ color: COLORS.secondary }}>
                    <LineChartIcon className="w-12 h-12 mb-2" />
                    <p>No daily revenue data available</p>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Systems Tab Content */}
        {activeTab === 'systems' && (
          <div className="space-y-6">
            {/* System Performance Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {revenueStats?.systemRevenue.map((system, index) => (
                <div key={index} 
                     className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md"
                     style={{ borderColor: COLORS.secondary }}>
                  <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center gap-3">
                      <div className="p-2 rounded-lg" 
                           style={{ backgroundColor: `${SYSTEM_COLORS[system.system] || COLORS.primary}15` }}>
                        <div style={{ color: SYSTEM_COLORS[system.system] || COLORS.primary }}>
                          {getSystemIcon(system.system)}
                        </div>
                      </div>
                      <div>
                        <h4 className="font-semibold" style={{ color: COLORS.dark }}>{system.name}</h4>
                        <p className="text-sm" style={{ color: COLORS.secondary }}>
                          {system.count} transactions
                        </p>
                      </div>
                    </div>
                    <span className="text-sm px-3 py-1 rounded-full" 
                          style={{ 
                            backgroundColor: `${COLORS.secondary}15`, 
                            color: COLORS.dark 
                          }}>
                      #{index + 1}
                    </span>
                  </div>
                  
                  <div className="mb-4">
                    <p className="text-2xl font-bold mb-2" style={{ color: COLORS.primary }}>
                      {formatCurrency(system.revenue)}
                    </p>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div 
                        className="h-2 rounded-full transition-all duration-500"
                        style={{ 
                          width: `${Math.min(system.percentage, 100)}%`,
                          backgroundColor: SYSTEM_COLORS[system.system] || COLORS.primary
                        }}
                      ></div>
                    </div>
                    <div className="flex justify-between text-sm mt-1" style={{ color: COLORS.secondary }}>
                      <span>{system.percentage.toFixed(1)}% of total</span>
                      <span>Avg: {formatCurrency(system.avgAmount)}</span>
                    </div>
                  </div>
                  
                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div className="text-center p-3 rounded-lg" 
                         style={{ backgroundColor: `${COLORS.secondary}05` }}>
                      <div className="font-medium" style={{ color: COLORS.dark }}>Transactions</div>
                      <div className="text-lg font-bold" style={{ color: COLORS.primary }}>
                        {formatNumber(system.count)}
                      </div>
                    </div>
                    <div className="text-center p-3 rounded-lg" 
                         style={{ backgroundColor: `${COLORS.success}05` }}>
                      <div className="font-medium" style={{ color: COLORS.dark }}>Average</div>
                      <div className="text-lg font-bold" style={{ color: COLORS.success }}>
                        {formatCurrency(system.avgAmount)}
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Trends Tab Content */}
        {activeTab === 'trends' && (
          <div className="space-y-6">
            {/* Payment Methods Analysis */}
            <div className="bg-white border rounded-xl p-6 shadow-sm" style={{ borderColor: COLORS.secondary }}>
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <CreditCard className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Payment Methods Analysis
                </h3>
              </div>
              
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div className="h-80">
                  {revenueStats?.methodRevenue && revenueStats.methodRevenue.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                      <BarChart data={revenueStats.methodRevenue}>
                        <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                        <XAxis 
                          dataKey="method" 
                          tickFormatter={(value) => value.charAt(0).toUpperCase() + value.slice(1)}
                          tick={{ fontSize: 12 }}
                        />
                        <YAxis 
                          tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
                          tick={{ fontSize: 12 }}
                        />
                        <Tooltip 
                          formatter={(value) => [formatCurrency(value), 'Revenue']}
                          contentStyle={{ 
                            backgroundColor: 'white',
                            borderColor: COLORS.secondary,
                            borderRadius: '8px'
                          }}
                        />
                        <Bar 
                          dataKey="revenue" 
                          name="Revenue"
                          radius={[4, 4, 0, 0]}
                          fill={COLORS.primary}
                        />
                      </BarChart>
                    </ResponsiveContainer>
                  ) : (
                    <div className="flex flex-col items-center justify-center h-full" style={{ color: COLORS.secondary }}>
                      <CreditCard className="w-12 h-12 mb-2" />
                      <p>No payment method data available</p>
                    </div>
                  )}
                </div>
                
                {/* Payment Method Details */}
                <div className="space-y-4">
                  <h4 className="font-semibold mb-4" style={{ color: COLORS.dark }}>Payment Method Breakdown</h4>
                  <div className="space-y-3 max-h-64 overflow-y-auto pr-2">
                    {revenueStats?.methodRevenue.map((method, index) => (
                      <div key={index} 
                           className="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 transition-all"
                           style={{ borderColor: COLORS.secondary }}>
                        <div className="flex items-center gap-3">
                          <div className={`p-2 rounded-lg ${
                            method.method.toLowerCase().includes('cash') ? 'bg-green-100' :
                            method.method.toLowerCase().includes('card') ? 'bg-blue-100' :
                            method.method.toLowerCase().includes('online') ? 'bg-purple-100' :
                            'bg-gray-100'
                          }`}>
                            <CreditCard className={`w-4 h-4 ${
                              method.method.toLowerCase().includes('cash') ? 'text-green-600' :
                              method.method.toLowerCase().includes('card') ? 'text-blue-600' :
                              method.method.toLowerCase().includes('online') ? 'text-purple-600' :
                              'text-gray-600'
                            }`} />
                          </div>
                          <div>
                            <p className="font-medium capitalize" style={{ color: COLORS.dark }}>
                              {method.method || 'Unknown'}
                            </p>
                            <p className="text-sm" style={{ color: COLORS.secondary }}>
                              {method.count} transactions
                            </p>
                          </div>
                        </div>
                        <div className="text-right">
                          <p className="font-bold text-lg" style={{ color: COLORS.primary }}>
                            {formatCurrency(method.revenue)}
                          </p>
                          <p className="text-sm" style={{ color: COLORS.secondary }}>
                            {method.percentage.toFixed(1)}%
                          </p>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Summary Stats */}
        <div className="bg-white border rounded-xl p-6 shadow-sm" style={{ borderColor: COLORS.secondary }}>
          <h3 className="font-semibold mb-4" style={{ color: COLORS.dark }}>Collection Performance Summary</h3>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="text-center p-4 rounded-lg" 
                 style={{ backgroundColor: `${COLORS.success}05` }}>
              <div className="text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Total Revenue</div>
              <div className="text-2xl font-bold" style={{ color: COLORS.success }}>
                {formatCurrency(revenueStats?.totalRevenue || 0)}
              </div>
            </div>
            
            <div className="text-center p-4 rounded-lg" 
                 style={{ backgroundColor: `${COLORS.primary}05` }}>
              <div className="text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Successful Payments</div>
              <div className="text-2xl font-bold" style={{ color: COLORS.primary }}>
                {formatNumber(revenueStats?.totalTransactions || 0)}
              </div>
            </div>
            
            <div className="text-center p-4 rounded-lg" 
                 style={{ backgroundColor: `${COLORS.warning}05` }}>
              <div className="text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Daily Average</div>
              <div className="text-2xl font-bold" style={{ color: COLORS.warning }}>
                {formatCurrency((revenueStats?.totalRevenue || 0) / (revenueStats?.totalDays || 1))}
              </div>
            </div>
            
            <div className="text-center p-4 rounded-lg" 
                 style={{ backgroundColor: `${COLORS.info}05` }}>
              <div className="text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Peak Day</div>
              <div className="text-2xl font-bold" style={{ color: COLORS.info }}>
                {formatCurrency(revenueStats?.maxDailyRevenue || 0)}
              </div>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="text-center text-sm pt-6 border-t" style={{ color: COLORS.secondary, borderColor: COLORS.secondary }}>
          <p>Revenue Collection Dashboard • Period: {dateRange.start} to {dateRange.end}</p>
          <p className="text-xs mt-1">
            Showing data for {selectedSystem === 'all' ? 'all systems' : getSystemName(selectedSystem)} • 
            {revenueStats?.systemRevenue.length || 0} revenue sources • 
            Last updated: {new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })}
          </p>
        </div>
      </div>
    </div>
  );
}

// Missing icon components
const Droplets = ({ className }) => (
  <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
  </svg>
);

const Car = ({ className }) => (
  <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 6v6a4 4 0 01-4 4H9m8 0a4 4 0 01-4 4m4-4v2a4 4 0 01-4 4m0 0H5a4 4 0 01-4-4v-2a4 4 0 014-4h14a4 4 0 014 4v2a4 4 0 01-4 4h-4z" />
  </svg>
);

const Cross = ({ className }) => (
  <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
  </svg>
);