import React, { useState, useEffect } from 'react';
import { 
  BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, 
  Tooltip, Legend, ResponsiveContainer, LineChart, Line
} from 'recharts';
import { 
  CreditCard, DollarSign, Users, Calendar, AlertCircle, 
  TrendingUp, TrendingDown, RefreshCw, Download, Filter,
  CheckCircle, Clock, XCircle, FileText, 
  ArrowUpRight, ArrowDownRight, 
  BarChart as BarChartIcon, PieChart as PieChartIcon,
  LineChart as LineChartIcon, Database, Filter as FilterIcon,
  ChevronDown, ChevronUp, TrendingUp as TrendingUpIcon,
  Activity, Receipt, Smartphone,
  Globe, Landmark, Building, MapPin, Eye, Banknote, Wallet,
  Search, Percent, Target, Award, Star, Trophy,
  AlertTriangle, Info, CalendarDays,
  FileSpreadsheet, History, Zap, Shield,
  Phone, Mail, MessageSquare,
  Cpu, HardDrive, Cloud,
  Navigation, Compass, Target as Target2Icon
} from 'lucide-react';
import * as XLSX from 'xlsx';

// Auto-detect environment
const getApiBase = () => {
  const currentHost = window.location.hostname;
  const currentProtocol = window.location.protocol;
  
  // Check for localhost or 127.0.0.1
  if (currentHost === 'localhost' || currentHost === '127.0.0.1') {
    return 'http://localhost/revenue2/backend/Digital';
  }
  
  // Check for local development domains
  if (currentHost.includes('.local') || currentHost.includes('.test')) {
    return `${currentProtocol}//${currentHost}/revenue2/backend/Digital`;
  }
  
  // Production domain
  if (currentHost.includes('goserveph.com')) {
    return `${currentProtocol}//revenuetreasury.goserveph.com/backend/Digital`;
  }
  
  // Default fallback
  const pathParts = window.location.pathname.split('/');
  const revenueIndex = pathParts.indexOf('revenue2');
  
  if (revenueIndex !== -1) {
    const basePath = pathParts.slice(0, revenueIndex + 1).join('/');
    return `${currentProtocol}//${currentHost}${basePath}/backend/Digital`;
  }
  
  return `${currentProtocol}//${currentHost}/backend/Digital`;
};

const API_BASE = getApiBase();

export default function DigiDashboard() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [payments, setPayments] = useState([]);
  const [stats, setStats] = useState(null);
  const [exportLoading, setExportLoading] = useState(false);
  const [dateRange, setDateRange] = useState({
    startDate: new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0],
    endDate: new Date().toISOString().split('T')[0]
  });
  const [filters, setFilters] = useState({
    payment_method: 'all',
    payment_status: 'all',
    client_system: 'all',
    search: ''
  });
  const [showFilters, setShowFilters] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage] = useState(15);
  const [chartType, setChartType] = useState('bar');

  useEffect(() => {
    fetchAllData();
  }, [dateRange, currentPage]);

  const fetchAllData = async () => {
    setLoading(true);
    try {
      await Promise.all([
        fetchPayments(),
        fetchStats()
      ]);
    } catch (err) {
      console.error('Error fetching data:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const fetchPayments = async () => {
    try {
      let url = `${API_BASE}/payments.php?action=get_payments&start_date=${dateRange.startDate}&end_date=${dateRange.endDate}&page=${currentPage}&limit=${itemsPerPage}`;
      
      // Add filters to URL
      if (filters.payment_method !== 'all') {
        url += `&payment_method=${filters.payment_method}`;
      }
      if (filters.payment_status !== 'all') {
        url += `&payment_status=${filters.payment_status}`;
      }
      if (filters.client_system !== 'all') {
        url += `&client_system=${filters.client_system}`;
      }
      if (filters.search) {
        url += `&search=${encodeURIComponent(filters.search)}`;
      }
      
      const response = await fetch(url, {
        headers: { 'Accept': 'application/json' }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (!data.success) {
        throw new Error(data.error || 'Failed to load payments');
      }
      
      setPayments(data.data || []);
      
    } catch (err) {
      console.error('Error fetching payments:', err);
      throw err;
    }
  };

  const fetchStats = async () => {
    try {
      const response = await fetch(`${API_BASE}/payments.php?action=get_stats&start_date=${dateRange.startDate}&end_date=${dateRange.endDate}`);
      const data = await response.json();
      
      if (data.success) {
        setStats(data.data);
      } else {
        throw new Error(data.error || 'Failed to load statistics');
      }
    } catch (err) {
      console.error('Error fetching stats:', err);
      throw err;
    }
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

  const formatNumber = (num) => {
    if (num === null || num === undefined || isNaN(num)) return '0';
    return new Intl.NumberFormat('en-PH').format(num);
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const formatShortDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', {
      month: 'short',
      day: 'numeric'
    });
  };

  const getStatusBadge = (status) => {
    switch(status) {
      case 'paid':
        return <span className="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800 flex items-center gap-1"><CheckCircle className="w-3 h-3" /> Paid</span>;
      case 'pending':
        return <span className="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800 flex items-center gap-1"><Clock className="w-3 h-3" /> Pending</span>;
      case 'failed':
        return <span className="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800 flex items-center gap-1"><XCircle className="w-3 h-3" /> Failed</span>;
      default:
        return <span className="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">Unknown</span>;
    }
  };

  const getMethodBadge = (method) => {
    switch(method) {
      case 'gcash':
        return <span className="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800 flex items-center gap-1"><Smartphone className="w-3 h-3" /> GCash</span>;
      case 'maya':
        return <span className="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800 flex items-center gap-1"><Wallet className="w-3 h-3" /> Maya</span>;
      case 'card':
        return <span className="px-2 py-1 text-xs rounded-full bg-orange-100 text-orange-800 flex items-center gap-1"><CreditCard className="w-3 h-3" /> Card</span>;
      default:
        return <span className="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">{method || 'Unknown'}</span>;
    }
  };

  const getSystemBadge = (system) => {
    const systemColors = {
      'rpt': { bg: 'bg-indigo-100', text: 'text-indigo-800', label: 'RPT' },
      'business': { bg: 'bg-emerald-100', text: 'text-emerald-800', label: 'Business' },
      'market': { bg: 'bg-amber-100', text: 'text-amber-800', label: 'Market' },
      'market_rent': { bg: 'bg-amber-100', text: 'text-amber-800', label: 'Market Rent' }
    };
    
    const config = systemColors[system] || { bg: 'bg-gray-100', text: 'text-gray-800', label: system || 'Unknown' };
    
    return (
      <span className={`px-2 py-1 text-xs rounded-full ${config.bg} ${config.text} flex items-center gap-1`}>
        {system === 'rpt' && <Landmark className="w-3 h-3" />}
        {system === 'business' && <Building className="w-3 h-3" />}
        {(system === 'market' || system === 'market_rent') && <MapPin className="w-3 h-3" />}
        {config.label}
      </span>
    );
  };

  const exportToExcel = () => {
    setExportLoading(true);
    try {
      const wb = XLSX.utils.book_new();
      
      // Payments sheet
      const wsPayments = XLSX.utils.json_to_sheet(payments.map(p => ({
        'Payment ID': p.payment_id,
        'Client System': p.client_system,
        'Reference': p.client_reference,
        'Purpose': p.purpose,
        'Amount': parseFloat(p.amount),
        'Phone': p.phone,
        'Payment Method': p.payment_method,
        'Status': p.payment_status,
        'OTP Verified': p.otp_verified ? 'Yes' : 'No',
        'Receipt No.': p.receipt_number,
        'Created At': p.created_at,
        'Paid At': p.paid_at,
        'Callback Sent': p.callback_sent ? 'Yes' : 'No'
      })));
      XLSX.utils.book_append_sheet(wb, wsPayments, 'Payments');
      
      // Summary sheet
      if (stats) {
        const wsStats = XLSX.utils.json_to_sheet([
          {
            'Total Transactions': stats.total_transactions,
            'Total Amount': stats.total_amount,
            'Paid Count': stats.paid_count,
            'Pending Count': stats.pending_count,
            'Failed Count': stats.failed_count,
            'Success Rate': `${stats.success_rate || 0}%`,
            'Average Amount': stats.average_amount
          }
        ]);
        XLSX.utils.book_append_sheet(wb, wsStats, 'Summary');
      }
      
      const filename = `Digital_Payments_${dateRange.startDate}_to_${dateRange.endDate}.xlsx`;
      XLSX.writeFile(wb, filename);
      
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting to Excel');
    } finally {
      setExportLoading(false);
    }
  };

  const handleFilterChange = (filterType, value) => {
    setFilters(prev => ({
      ...prev,
      [filterType]: value
    }));
    setCurrentPage(1);
  };

  const handleDateChange = (type, value) => {
    setDateRange(prev => ({
      ...prev,
      [type]: value
    }));
    setCurrentPage(1);
  };

  // Chart data preparation
  const getDailyTrendData = () => {
    if (!stats || !stats.daily_trend) return [];
    return stats.daily_trend.map(day => ({
      date: formatShortDate(day.date),
      amount: parseFloat(day.amount || 0),
      count: parseInt(day.count || 0)
    }));
  };

  const getMethodChartData = () => {
    if (!stats) return [];
    
    return [
      { name: 'GCash', value: stats.gcash_count || 0, color: '#0088FE' },
      { name: 'Maya', value: stats.maya_count || 0, color: '#00C49F' },
      { name: 'Card', value: stats.card_count || 0, color: '#FFBB28' }
    ].filter(item => item.value > 0);
  };

  if (loading && payments.length === 0) {
    return (
      <div className="flex flex-col justify-center items-center h-screen bg-white">
        <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-gray-800 mb-4"></div>
        <p className="text-gray-600">Loading Digital Payment Dashboard...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="max-w-4xl mx-auto p-6 bg-white">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6">
          <div className="flex items-center space-x-3 mb-4">
            <AlertCircle className="w-8 h-8 text-red-600" />
            <div>
              <h3 className="text-lg font-semibold text-red-600">Error Loading Dashboard</h3>
              <p className="text-red-600">{error}</p>
              <p className="text-sm text-red-500 mt-1">API: {API_BASE}</p>
            </div>
          </div>
          <button 
            onClick={fetchAllData}
            className="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 flex items-center gap-2"
          >
            <RefreshCw className="w-4 h-4" />
            Try Again
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white">
      {/* Header */}
      <div className="border-b border-gray-200 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
              <h1 className="text-2xl font-bold text-gray-900 mb-1">
                Digital Payment Dashboard
              </h1>
              <div className="flex items-center gap-3 text-sm text-gray-500">
                <div className="flex items-center gap-1">
                  <Calendar className="w-4 h-4" />
                  <span>{dateRange.startDate} to {dateRange.endDate}</span>
                </div>
              </div>
            </div>
            
            <div className="flex flex-wrap gap-3 items-center">
              {/* Date Range */}
              <div className="flex gap-2">
                <input
                  type="date"
                  value={dateRange.startDate}
                  onChange={(e) => handleDateChange('startDate', e.target.value)}
                  className="px-3 py-2 border border-gray-300 rounded-lg text-sm"
                />
                <span className="self-center text-gray-500">to</span>
                <input
                  type="date"
                  value={dateRange.endDate}
                  onChange={(e) => handleDateChange('endDate', e.target.value)}
                  className="px-3 py-2 border border-gray-300 rounded-lg text-sm"
                />
              </div>
              
              {/* Export Button */}
              <button
                onClick={exportToExcel}
                disabled={exportLoading || payments.length === 0}
                className="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
              >
                {exportLoading ? (
                  <>
                    <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div>
                    <span>Exporting...</span>
                  </>
                ) : (
                  <>
                    <Download className="w-4 h-4" />
                    <span>Export Excel</span>
                  </>
                )}
              </button>
              
              {/* Refresh Button */}
              <button
                onClick={fetchAllData}
                className="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700"
              >
                <RefreshCw className="w-4 h-4" />
                Refresh
              </button>
            </div>
          </div>
          
          {/* Search Bar */}
          <div className="mt-4">
            <div className="relative">
              <input
                type="text"
                placeholder="Search payments by ID, reference, phone, purpose..."
                value={filters.search}
                onChange={(e) => handleFilterChange('search', e.target.value)}
                className="w-full px-4 py-3 pl-11 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
              <div className="absolute left-3 top-1/2 transform -translate-y-1/2">
                <Search className="w-5 h-5 text-gray-400" />
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Stats Overview */}
        {stats && (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
              <div className="flex items-center justify-between mb-4">
                <div className="p-3 bg-blue-50 rounded-lg">
                  <DollarSign className="w-6 h-6 text-blue-600" />
                </div>
                <span className="text-sm text-gray-500">Total Amount</span>
              </div>
              <h3 className="text-2xl font-bold text-gray-900 mb-2">
                {formatCurrency(stats.total_amount)}
              </h3>
              <p className="text-sm text-gray-600">
                {formatNumber(stats.total_transactions)} transactions
              </p>
            </div>
            
            <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
              <div className="flex items-center justify-between mb-4">
                <div className="p-3 bg-green-50 rounded-lg">
                  <CheckCircle className="w-6 h-6 text-green-600" />
                </div>
                <span className="text-sm text-gray-500">Success Rate</span>
              </div>
              <h3 className="text-2xl font-bold text-gray-900 mb-2">
                {stats.success_rate || 0}%
              </h3>
              <p className="text-sm text-gray-600">
                {formatNumber(stats.paid_count)} paid • {formatNumber(stats.failed_count)} failed
              </p>
            </div>
            
            <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
              <div className="flex items-center justify-between mb-4">
                <div className="p-3 bg-purple-50 rounded-lg">
                  <Smartphone className="w-6 h-6 text-purple-600" />
                </div>
                <span className="text-sm text-gray-500">GCash</span>
              </div>
              <h3 className="text-2xl font-bold text-gray-900 mb-2">
                {formatNumber(stats.gcash_count)}
              </h3>
              <p className="text-sm text-gray-600">
                Most used payment method
              </p>
            </div>
            
            <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
              <div className="flex items-center justify-between mb-4">
                <div className="p-3 bg-orange-50 rounded-lg">
                  <Target className="w-6 h-6 text-orange-600" />
                </div>
                <span className="text-sm text-gray-500">Average</span>
              </div>
              <h3 className="text-2xl font-bold text-gray-900 mb-2">
                {formatCurrency(stats.average_amount)}
              </h3>
              <p className="text-sm text-gray-600">
                Per transaction
              </p>
            </div>
          </div>
        )}

        {/* Charts Section */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Daily Trend Chart */}
          <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            <div className="flex justify-between items-center mb-6">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                <Activity className="w-5 h-5 text-gray-600" />
                Daily Transaction Trend
              </h3>
              <div className="flex gap-2">
                <button
                  onClick={() => setChartType('bar')}
                  className={`px-3 py-1 text-sm rounded-lg ${chartType === 'bar' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-50'}`}
                >
                  Bar
                </button>
                <button
                  onClick={() => setChartType('line')}
                  className={`px-3 py-1 text-sm rounded-lg ${chartType === 'line' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-50'}`}
                >
                  Line
                </button>
              </div>
            </div>
            <div className="h-72">
              {getDailyTrendData().length > 0 ? (
                <ResponsiveContainer width="100%" height="100%">
                  {chartType === 'bar' ? (
                    <BarChart data={getDailyTrendData()}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                      <XAxis dataKey="date" />
                      <YAxis 
                        tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
                      />
                      <Tooltip 
                        formatter={(value, name) => {
                          if (name === 'amount') return [formatCurrency(value), 'Amount'];
                          return [value, 'Count'];
                        }}
                      />
                      <Legend />
                      <Bar dataKey="amount" fill="#3B82F6" name="Amount" radius={[4, 4, 0, 0]} />
                    </BarChart>
                  ) : (
                    <LineChart data={getDailyTrendData()}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                      <XAxis dataKey="date" />
                      <YAxis 
                        tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
                      />
                      <Tooltip 
                        formatter={(value, name) => {
                          if (name === 'amount') return [formatCurrency(value), 'Amount'];
                          return [value, 'Count'];
                        }}
                      />
                      <Legend />
                      <Line type="monotone" dataKey="amount" stroke="#3B82F6" name="Amount" strokeWidth={2} dot={{ r: 4 }} />
                      <Line type="monotone" dataKey="count" stroke="#10B981" name="Count" strokeWidth={2} dot={{ r: 4 }} />
                    </LineChart>
                  )}
                </ResponsiveContainer>
              ) : (
                <div className="flex flex-col items-center justify-center h-full text-gray-400">
                  <LineChartIcon className="w-12 h-12 mb-2" />
                  <p>No transaction data available</p>
                </div>
              )}
            </div>
          </div>

          {/* Payment Methods Distribution */}
          <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            <div className="flex justify-between items-center mb-6">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                <PieChartIcon className="w-5 h-5 text-gray-600" />
                Payment Methods Distribution
              </h3>
            </div>
            <div className="h-72">
              {getMethodChartData().length > 0 ? (
                <ResponsiveContainer width="100%" height="100%">
                  <PieChart>
                    <Pie
                      data={getMethodChartData()}
                      cx="50%"
                      cy="50%"
                      labelLine={false}
                      label={({ name, percent }) => `${name}: ${(percent * 100).toFixed(1)}%`}
                      outerRadius={80}
                      fill="#8884d8"
                      dataKey="value"
                    >
                      {getMethodChartData().map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={entry.color} />
                      ))}
                    </Pie>
                    <Tooltip 
                      formatter={(value, name) => [`${value} transactions`, name]}
                    />
                    <Legend />
                  </PieChart>
                </ResponsiveContainer>
              ) : (
                <div className="flex flex-col items-center justify-center h-full text-gray-400">
                  <PieChartIcon className="w-12 h-12 mb-2" />
                  <p>No payment method data available</p>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Transactions Table */}
        <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
          <div className="p-6 border-b border-gray-200">
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
              <div>
                <h3 className="font-semibold text-gray-900">Payment Transactions</h3>
                <p className="text-sm text-gray-500 mt-1">
                  Showing {payments.length} transactions
                </p>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => setShowFilters(!showFilters)}
                  className="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700"
                >
                  <Filter className="w-4 h-4" />
                  Filters
                  {showFilters ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                </button>
              </div>
            </div>
          </div>
          
          {/* Filters Panel */}
          {showFilters && (
            <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Payment Method
                  </label>
                  <select
                    value={filters.payment_method}
                    onChange={(e) => handleFilterChange('payment_method', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                  >
                    <option value="all">All Methods</option>
                    <option value="gcash">GCash</option>
                    <option value="maya">Maya</option>
                    <option value="card">Card</option>
                  </select>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Payment Status
                  </label>
                  <select
                    value={filters.payment_status}
                    onChange={(e) => handleFilterChange('payment_status', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                  >
                    <option value="all">All Statuses</option>
                    <option value="paid">Paid</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                  </select>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Client System
                  </label>
                  <select
                    value={filters.client_system}
                    onChange={(e) => handleFilterChange('client_system', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                  >
                    <option value="all">All Systems</option>
                    <option value="rpt">RPT</option>
                    <option value="business">Business</option>
                    <option value="market">Market</option>
                    <option value="market_rent">Market Rent</option>
                  </select>
                </div>
              </div>
            </div>
          )}
          
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Payment Details
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Amount
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Method & Status
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Date & Time
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {payments.length > 0 ? (
                  payments.map((payment) => (
                    <tr key={payment.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4">
                        <div className="space-y-1">
                          <div className="flex items-center gap-2">
                            {getSystemBadge(payment.client_system)}
                            <span className="text-sm font-medium text-gray-900">
                              {payment.payment_id}
                            </span>
                          </div>
                          <p className="text-sm text-gray-600">{payment.purpose}</p>
                          <div className="flex items-center gap-2 text-xs text-gray-500">
                            <Receipt className="w-3 h-3" />
                            {payment.receipt_number || 'No receipt'}
                            <span className="mx-1">•</span>
                            <Smartphone className="w-3 h-3" />
                            {payment.phone}
                          </div>
                          <p className="text-xs text-gray-400">Ref: {payment.client_reference}</p>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="text-lg font-bold text-gray-900">
                          {formatCurrency(payment.amount)}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="space-y-2">
                          <div>{getMethodBadge(payment.payment_method)}</div>
                          <div>{getStatusBadge(payment.payment_status)}</div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="space-y-1">
                          <div className="text-sm text-gray-900">
                            {formatDate(payment.created_at)}
                          </div>
                          {payment.paid_at && (
                            <div className="text-xs text-green-600 flex items-center gap-1">
                              <CheckCircle className="w-3 h-3" />
                              Paid: {payment.paid_at.split(' ')[0]}
                            </div>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="4" className="px-6 py-12 text-center text-gray-500">
                      <div className="flex flex-col items-center">
                        <Database className="w-12 h-12 text-gray-300 mb-2" />
                        <p>No payment transactions found</p>
                        <p className="text-sm mt-1">Try adjusting your filters or date range</p>
                      </div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Footer */}
        <div className="text-center text-sm text-gray-500 pt-6 border-t border-gray-200">
          <p>Digital Payment Dashboard • Date Range: {dateRange.startDate} to {dateRange.endDate}</p>
          <p className="text-xs text-gray-400 mt-1">
            API: {API_BASE} • Last updated: {new Date().toLocaleTimeString()}
          </p>
        </div>
      </div>
    </div>
  );
}