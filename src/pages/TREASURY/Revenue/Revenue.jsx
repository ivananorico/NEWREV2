import React, { useState, useEffect } from 'react';
import { 
  BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, 
  Tooltip, Legend, ResponsiveContainer, LineChart, Line
} from 'recharts';
import { 
  DollarSign, Calendar, AlertCircle, RefreshCw, Download, 
  Landmark, Building2, Store, TrendingUp, TrendingDown,
  CheckCircle, ChevronDown, BarChart3, PieChart as PieChartIcon,
  LineChart as LineChartIcon, Table, Wallet, CreditCard,
  Activity, Percent, AlertTriangle, Users, HomeIcon,
  CheckSquare, XSquare, FileBarChart, Grid3x3
} from 'lucide-react';
import * as XLSX from 'xlsx';

const API_BASE = window.location.hostname === "localhost"
  ? "http://localhost/revenue2/backend/Treasury"
  : "https://revenuetreasury.goserveph.com/backend/Treasury";

export default function RevenueDashboard() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [revenueData, setRevenueData] = useState(null);
  const [exportLoading, setExportLoading] = useState(false);
  const [activeTab, setActiveTab] = useState('overview');
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
  const [availableYears, setAvailableYears] = useState([]);
  const [systemFilter, setSystemFilter] = useState('all');
  const [yearDropdownOpen, setYearDropdownOpen] = useState(false);
  const [viewMode, setViewMode] = useState('charts');

  useEffect(() => {
    fetchAvailableYears();
  }, [systemFilter]); // Refetch years when system filter changes

  useEffect(() => {
    if (availableYears.length > 0) {
      // Check if selected year is in available years, if not use the first available
      if (!availableYears.includes(selectedYear)) {
        setSelectedYear(availableYears[0]);
      }
    }
  }, [availableYears]);

  useEffect(() => {
    fetchRevenueData();
  }, [selectedYear, systemFilter]);

  const fetchAvailableYears = async () => {
    try {
      const response = await fetch(`${API_BASE}/revenue_dashboard_api.php?action=get_years&system=${systemFilter}`);
      const data = await response.json();
      
      if (data.success && data.years && data.years.length > 0) {
        setAvailableYears(data.years);
        // If current selected year is not in new list, update it
        if (!data.years.includes(selectedYear)) {
          setSelectedYear(data.years[0]);
        }
      } else {
        const currentYear = new Date().getFullYear();
        const years = [currentYear, currentYear - 1, currentYear - 2];
        setAvailableYears(years);
      }
    } catch (err) {
      console.error('Error fetching years:', err);
      const currentYear = new Date().getFullYear();
      setAvailableYears([currentYear, currentYear - 1]);
    }
  };

  const fetchRevenueData = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(
        `${API_BASE}/revenue_dashboard_api.php?action=get_revenue_data&year=${selectedYear}&system=${systemFilter}`, 
        {
          headers: { 'Accept': 'application/json' }
        }
      );
      
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      
      const data = await response.json();
      
      if (!data.success) throw new Error(data.error || 'Failed to load revenue data');
      
      // Parse numbers
      const parsedData = parseNumbersInData(data);
      setRevenueData(parsedData);
      
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

  const exportToExcel = () => {
    if (!revenueData) return;
    
    setExportLoading(true);
    try {
      const wb = XLSX.utils.book_new();
      const dateStr = new Date().toISOString().split('T')[0];
      
      // Summary sheet
      const summaryData = [{
        'Year': selectedYear,
        'System Filter': systemFilter === 'all' ? 'All Systems' : 
                        systemFilter === 'rpt' ? 'RPT' : 
                        systemFilter === 'business' ? 'Business Tax' : 'Market Rent',
        'Total Revenue': formatCurrency(revenueData.total?.total_revenue),
        'Total Target': formatCurrency(revenueData.total?.total_target),
        'Collection Rate': formatPercent(revenueData.total?.collection_rate),
        'Data Updated': revenueData.timestamp
      }];
      
      const ws1 = XLSX.utils.json_to_sheet(summaryData);
      XLSX.utils.book_append_sheet(wb, ws1, 'Summary');
      
      // Quarterly data
      if (revenueData.quarterly_data && revenueData.quarterly_data.length > 0) {
        const quarterlyData = revenueData.quarterly_data.map(q => ({
          'Quarter': q.quarter,
          'Total Revenue': safeParseFloat(q.total_revenue),
          'RPT Revenue': safeParseFloat(q.rpt_revenue),
          'Business Tax Revenue': safeParseFloat(q.business_revenue),
          'Market Rent Revenue': safeParseFloat(q.market_revenue),
          'Transactions': safeParseFloat(q.transactions)
        }));
        
        const ws2 = XLSX.utils.json_to_sheet(quarterlyData);
        XLSX.utils.book_append_sheet(wb, ws2, 'Quarterly Data');
      }
      
      XLSX.writeFile(wb, `Revenue_${systemFilter}_${selectedYear}_${dateStr}.xlsx`);
      
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting report');
    } finally {
      setExportLoading(false);
    }
  };

  const getSystemName = (system) => {
    switch(system) {
      case 'all': return 'All Systems';
      case 'rpt': return 'Real Property Tax';
      case 'business': return 'Business Tax';
      case 'market': return 'Market Rent';
      default: return system;
    }
  };

  const systemColors = {
    'RPT': '#4F46E5',
    'Business Tax': '#10B981',
    'Market Rent': '#F59E0B'
  };

  const systemIcons = {
    'rpt': Landmark,
    'business': Building2,
    'market': Store
  };

  if (loading) {
    return (
      <div className="flex flex-col justify-center items-center h-screen bg-gray-50">
        <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-gray-800 mb-4"></div>
        <p className="text-gray-600">Loading {getSystemName(systemFilter)} Revenue Dashboard...</p>
        <p className="text-sm text-gray-400 mt-2">Year: {selectedYear}</p>
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
            onClick={fetchRevenueData}
            className="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 flex items-center gap-2"
          >
            <RefreshCw className="w-4 h-4" />
            Try Again
          </button>
        </div>
      </div>
    );
  }

  if (!revenueData) {
    return (
      <div className="text-center py-12">
        <FileBarChart className="w-16 h-16 text-gray-400 mx-auto mb-4" />
        <p className="text-gray-500">No revenue data available for {selectedYear}</p>
        <button 
          onClick={fetchRevenueData}
          className="mt-4 px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 flex items-center gap-2 mx-auto"
        >
          <RefreshCw className="w-4 h-4" />
          Load Dashboard
        </button>
      </div>
    );
  }

  // Calculate metrics
  const totalRevenue = safeParseFloat(revenueData.total?.total_revenue || 0);
  const totalTarget = safeParseFloat(revenueData.total?.total_target || 0);
  const collectionRate = safeParseFloat(revenueData.total?.collection_rate || 0);
  
  const rptRevenue = safeParseFloat(revenueData.rpt?.collected_revenue || 0);
  const businessRevenue = safeParseFloat(revenueData.business?.collected_revenue || 0);
  const marketRevenue = safeParseFloat(revenueData.market?.collected_revenue || 0);

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
              <h1 className="text-xl font-bold text-gray-900 mb-1">
                Treasury Revenue Dashboard
              </h1>
              <div className="flex items-center gap-3 text-sm text-gray-500">
                <div className="flex items-center gap-1">
                  <Calendar className="w-4 h-4" />
                  <span>{selectedYear} • {getSystemName(systemFilter)}</span>
                </div>
                {revenueData.timestamp && (
                  <div className="flex items-center gap-1">
                    <span>Updated: {new Date(revenueData.timestamp).toLocaleDateString('en-PH', { 
                      month: 'short', 
                      day: 'numeric',
                      hour: '2-digit',
                      minute: '2-digit'
                    })}</span>
                  </div>
                )}
              </div>
            </div>
            
            <div className="flex flex-wrap gap-3 items-center">
              {/* System Filter */}
              <div className="flex gap-2">
                {['all', 'rpt', 'business', 'market'].map((system) => {
                  const Icon = systemIcons[system] || DollarSign;
                  return (
                    <button
                      key={system}
                      onClick={() => setSystemFilter(system)}
                      className={`px-4 py-2 rounded-lg flex items-center gap-2 transition-colors ${
                        systemFilter === system
                          ? 'bg-gray-900 text-white'
                          : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'
                      }`}
                    >
                      <Icon className="w-4 h-4" />
                      <span className="text-sm">
                        {system === 'all' ? 'All' : 
                         system === 'rpt' ? 'RPT' : 
                         system === 'business' ? 'Business' : 'Market'}
                      </span>
                    </button>
                  );
                })}
              </div>
              
              {/* Year Selection */}
              <div className="relative">
                <button
                  onClick={() => setYearDropdownOpen(!yearDropdownOpen)}
                  className="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 text-sm bg-white"
                >
                  <Calendar className="w-4 h-4" />
                  <span>{selectedYear}</span>
                  <ChevronDown className="w-4 h-4" />
                </button>
                
                {yearDropdownOpen && (
                  <>
                    <div 
                      className="fixed inset-0 z-40" 
                      onClick={() => setYearDropdownOpen(false)}
                    ></div>
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
                  </>
                )}
              </div>
              
              {/* View Mode Toggle */}
              <div className="inline-flex rounded-lg border border-gray-300 p-1 bg-white">
                <button
                  onClick={() => setViewMode('charts')}
                  className={`px-3 py-1 text-sm rounded-md transition-colors ${
                    viewMode === 'charts' 
                      ? 'bg-gray-900 text-white' 
                      : 'text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  <BarChart3 className="w-4 h-4" />
                </button>
                <button
                  onClick={() => setViewMode('detailed')}
                  className={`px-3 py-1 text-sm rounded-md transition-colors ${
                    viewMode === 'detailed' 
                      ? 'bg-gray-900 text-white' 
                      : 'text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  <Table className="w-4 h-4" />
                </button>
              </div>
              
              {/* Refresh Button */}
              <button
                onClick={fetchRevenueData}
                className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 text-sm"
              >
                <RefreshCw className="w-4 h-4" />
              </button>
              
              {/* Export Button */}
              <button
                onClick={exportToExcel}
                disabled={exportLoading}
                className="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-black disabled:opacity-50 text-sm"
              >
                {exportLoading ? (
                  <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white mx-2"></div>
                ) : (
                  <Download className="w-4 h-4" />
                )}
              </button>
            </div>
          </div>
          
          {/* Available Years */}
          <div className="mt-4 flex flex-wrap gap-2">
            <span className="text-xs text-gray-500 mr-2">Available years:</span>
            {availableYears.slice(0, 10).map(year => (
              <button
                key={year}
                onClick={() => setSelectedYear(year)}
                className={`px-2 py-1 text-xs rounded transition-colors border ${
                  selectedYear === year
                    ? 'bg-gray-900 text-white border-gray-900'
                    : 'text-gray-700 border-gray-300 hover:bg-gray-50'
                }`}
              >
                {year}
              </button>
            ))}
            {availableYears.length > 10 && (
              <span className="px-2 py-1 text-xs text-gray-500">
                +{availableYears.length - 10} more
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Key Metrics */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Total Revenue */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-2.5 bg-gray-900 rounded-lg">
                <DollarSign className="w-5 h-5 text-white" />
              </div>
              <span className="text-xs px-2.5 py-1 bg-gray-100 text-gray-800 rounded-full">
                {systemFilter === 'all' ? 'Total' : getSystemName(systemFilter)}
              </span>
            </div>
            <h3 className="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">
              Revenue
            </h3>
            <p className="text-xl font-bold text-gray-900 mb-3">{formatCurrency(totalRevenue)}</p>
            <div className="text-xs text-gray-600">
              <div className="flex justify-between mb-1">
                <span>Target:</span>
                <span className="font-medium">{formatCurrency(totalTarget)}</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-1.5">
                <div 
                  className={`h-1.5 rounded-full ${
                    collectionRate >= 90 ? 'bg-green-500' :
                    collectionRate >= 75 ? 'bg-yellow-500' :
                    'bg-red-500'
                  }`}
                  style={{ width: `${Math.min(collectionRate, 100)}%` }}
                ></div>
              </div>
              <div className="flex justify-between mt-1">
                <span>Rate:</span>
                <span className={`font-medium ${
                  collectionRate >= 90 ? 'text-green-600' :
                  collectionRate >= 75 ? 'text-yellow-600' :
                  'text-red-600'
                }`}>
                  {formatPercent(collectionRate)}
                </span>
              </div>
            </div>
          </div>

          {/* RPT Revenue (only show if all or rpt filter) */}
          {(systemFilter === 'all' || systemFilter === 'rpt') && (
            <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
              <div className="flex items-center justify-between mb-4">
                <div className="p-2.5 bg-blue-50 rounded-lg">
                  <Landmark className="w-5 h-5 text-blue-600" />
                </div>
                <span className={`text-xs px-2.5 py-1 rounded-full ${
                  safeParseFloat(revenueData.rpt?.collection_rate) >= 90 ? 'bg-green-100 text-green-800' :
                  safeParseFloat(revenueData.rpt?.collection_rate) >= 75 ? 'bg-yellow-100 text-yellow-800' :
                  'bg-red-100 text-red-800'
                }`}>
                  {formatPercent(revenueData.rpt?.collection_rate || 0)}
                </span>
              </div>
              <h3 className="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">
                RPT Revenue
              </h3>
              <p className="text-xl font-bold text-gray-900 mb-3">{formatCurrency(rptRevenue)}</p>
              <div className="text-xs text-gray-600 space-y-1">
                <div className="flex justify-between">
                  <span>Target:</span>
                  <span>{formatCurrency(revenueData.rpt?.annual_target || 0)}</span>
                </div>
                <div className="flex justify-between">
                  <span>Outstanding:</span>
                  <span className="text-red-600">{formatCurrency(revenueData.rpt?.outstanding_balance || 0)}</span>
                </div>
              </div>
            </div>
          )}

          {/* Business Tax Revenue (only show if all or business filter) */}
          {(systemFilter === 'all' || systemFilter === 'business') && (
            <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
              <div className="flex items-center justify-between mb-4">
                <div className="p-2.5 bg-green-50 rounded-lg">
                  <Building2 className="w-5 h-5 text-green-600" />
                </div>
                <span className={`text-xs px-2.5 py-1 rounded-full ${
                  safeParseFloat(revenueData.business?.collection_rate) >= 90 ? 'bg-green-100 text-green-800' :
                  safeParseFloat(revenueData.business?.collection_rate) >= 75 ? 'bg-yellow-100 text-yellow-800' :
                  'bg-red-100 text-red-800'
                }`}>
                  {formatPercent(revenueData.business?.collection_rate || 0)}
                </span>
              </div>
              <h3 className="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">
                Business Tax
              </h3>
              <p className="text-xl font-bold text-gray-900 mb-3">{formatCurrency(businessRevenue)}</p>
              <div className="text-xs text-gray-600 space-y-1">
                <div className="flex justify-between">
                  <span>Target:</span>
                  <span>{formatCurrency(revenueData.business?.annual_target || 0)}</span>
                </div>
                <div className="flex justify-between">
                  <span>Outstanding:</span>
                  <span className="text-red-600">{formatCurrency(revenueData.business?.outstanding_balance || 0)}</span>
                </div>
              </div>
            </div>
          )}

          {/* Market Rent Revenue (only show if all or market filter) */}
          {(systemFilter === 'all' || systemFilter === 'market') && (
            <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
              <div className="flex items-center justify-between mb-4">
                <div className="p-2.5 bg-yellow-50 rounded-lg">
                  <Store className="w-5 h-5 text-yellow-600" />
                </div>
                <span className={`text-xs px-2.5 py-1 rounded-full ${
                  safeParseFloat(revenueData.market?.collection_rate) >= 90 ? 'bg-green-100 text-green-800' :
                  safeParseFloat(revenueData.market?.collection_rate) >= 75 ? 'bg-yellow-100 text-yellow-800' :
                  'bg-red-100 text-red-800'
                }`}>
                  {formatPercent(revenueData.market?.collection_rate || 0)}
                </span>
              </div>
              <h3 className="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">
                Market Rent
              </h3>
              <p className="text-xl font-bold text-gray-900 mb-3">{formatCurrency(marketRevenue)}</p>
              <div className="text-xs text-gray-600 space-y-1">
                <div className="flex justify-between">
                  <span>Target:</span>
                  <span>{formatCurrency(revenueData.market?.annual_target || 0)}</span>
                </div>
                <div className="flex justify-between">
                  <span>Outstanding:</span>
                  <span className="text-red-600">{formatCurrency(revenueData.market?.outstanding_balance || 0)}</span>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Charts View */}
        {viewMode === 'charts' && (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Revenue Breakdown Chart */}
            <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                  {systemFilter === 'all' ? (
                    <>
                      <PieChartIcon className="w-5 h-5 text-gray-600" />
                      Revenue Breakdown by System
                    </>
                  ) : (
                    <>
                      <BarChart3 className="w-5 h-5 text-gray-600" />
                      Quarterly Revenue Trend
                    </>
                  )}
                </h3>
                <span className="text-sm text-gray-500">{selectedYear}</span>
              </div>
              <div className="h-72">
                {systemFilter === 'all' ? (
                  // Pie chart for all systems
                  revenueData.system_breakdown && revenueData.system_breakdown.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                      <PieChart>
                        <Pie
                          data={revenueData.system_breakdown}
                          cx="50%"
                          cy="50%"
                          labelLine={false}
                          label={({ name, percent }) => `${name}: ${(percent * 100).toFixed(0)}%`}
                          outerRadius={80}
                          fill="#8884d8"
                          dataKey="revenue"
                        >
                          {revenueData.system_breakdown.map((entry, index) => (
                            <Cell key={`cell-${index}`} fill={systemColors[entry.system] || '#6B7280'} />
                          ))}
                        </Pie>
                        <Tooltip formatter={(value) => [formatCurrency(value), 'Revenue']} />
                        <Legend />
                      </PieChart>
                    </ResponsiveContainer>
                  ) : (
                    <div className="flex flex-col items-center justify-center h-full text-gray-400">
                      <PieChartIcon className="w-12 h-12 mb-2" />
                      <p>No system breakdown available</p>
                    </div>
                  )
                ) : (
                  // Line chart for single system
                  revenueData.quarterly_data && revenueData.quarterly_data.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                      <LineChart data={revenueData.quarterly_data}>
                        <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                        <XAxis dataKey="quarter" />
                        <YAxis tickFormatter={(value) => formatCurrency(value).replace('₱', '')} />
                        <Tooltip formatter={(value) => [formatCurrency(value), 'Revenue']} />
                        <Legend />
                        <Line 
                          type="monotone" 
                          dataKey="total_revenue" 
                          stroke={
                            systemFilter === 'rpt' ? '#4F46E5' : 
                            systemFilter === 'business' ? '#10B981' : 
                            '#F59E0B'
                          } 
                          strokeWidth={2}
                          dot={{ r: 4 }}
                          activeDot={{ r: 6 }}
                        />
                      </LineChart>
                    </ResponsiveContainer>
                  ) : (
                    <div className="flex flex-col items-center justify-center h-full text-gray-400">
                      <LineChartIcon className="w-12 h-12 mb-2" />
                      <p>No quarterly data available</p>
                    </div>
                  )
                )}
              </div>
            </div>

            {/* Monthly Revenue */}
            <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                  <BarChart3 className="w-5 h-5 text-gray-600" />
                  Monthly Revenue {selectedYear}
                </h3>
                <span className="text-sm text-gray-500">{getSystemName(systemFilter)}</span>
              </div>
              <div className="h-72">
                {revenueData.monthly_data && revenueData.monthly_data.length > 0 ? (
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={revenueData.monthly_data.map(m => ({
                      month: new Date(selectedYear, m.month - 1).toLocaleString('default', { month: 'short' }),
                      revenue: safeParseFloat(m.revenue),
                      transactions: safeParseFloat(m.transactions)
                    }))}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                      <XAxis dataKey="month" />
                      <YAxis tickFormatter={(value) => formatCurrency(value).replace('₱', '')} />
                      <Tooltip 
                        formatter={(value, name) => {
                          if (name === 'revenue') return [formatCurrency(value), 'Revenue'];
                          if (name === 'transactions') return [value, 'Transactions'];
                          return [value, name];
                        }}
                      />
                      <Legend />
                      <Bar 
                        dataKey="revenue" 
                        fill={
                          systemFilter === 'all' ? '#6B7280' :
                          systemFilter === 'rpt' ? '#4F46E5' : 
                          systemFilter === 'business' ? '#10B981' : 
                          '#F59E0B'
                        } 
                        radius={[4, 4, 0, 0]} 
                      />
                    </BarChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="flex flex-col items-center justify-center h-full text-gray-400">
                    <BarChart3 className="w-12 h-12 mb-2" />
                    <p>No monthly data available</p>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Detailed View */}
        {viewMode === 'detailed' && (
          <div className="space-y-6">
            {/* Quarterly Table */}
            <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
              <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 className="font-semibold text-gray-900">Quarterly Performance {selectedYear}</h3>
              </div>
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quarter</th>
                      {systemFilter === 'all' && <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">RPT</th>}
                      {systemFilter === 'all' && <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business Tax</th>}
                      {systemFilter === 'all' && <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Market Rent</th>}
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Revenue</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transactions</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {revenueData.quarterly_data && revenueData.quarterly_data.length > 0 ? (
                      revenueData.quarterly_data.map((quarter, index) => (
                        <tr key={index} className="hover:bg-gray-50">
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="flex items-center">
                              <div className={`w-3 h-3 rounded-full mr-2 ${
                                quarter.quarter === 'Q1' ? 'bg-blue-500' :
                                quarter.quarter === 'Q2' ? 'bg-green-500' :
                                quarter.quarter === 'Q3' ? 'bg-yellow-500' : 'bg-red-500'
                              }`}></div>
                              <span className="font-medium">{quarter.quarter}</span>
                            </div>
                          </td>
                          {systemFilter === 'all' && (
                            <td className="px-6 py-4 whitespace-nowrap text-sm">
                              {formatCurrency(quarter.rpt_revenue)}
                            </td>
                          )}
                          {systemFilter === 'all' && (
                            <td className="px-6 py-4 whitespace-nowrap text-sm">
                              {formatCurrency(quarter.business_revenue)}
                            </td>
                          )}
                          {systemFilter === 'all' && (
                            <td className="px-6 py-4 whitespace-nowrap text-sm">
                              {formatCurrency(quarter.market_revenue)}
                            </td>
                          )}
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className="font-semibold">{formatCurrency(quarter.total_revenue)}</span>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm">
                            {formatNumber(quarter.transactions)}
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan="6" className="px-6 py-8 text-center text-gray-500">
                          No quarterly data available
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Additional Metrics */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                <h4 className="font-semibold text-gray-900 mb-4">Transaction Summary</h4>
                <div className="space-y-3">
                  <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span className="text-gray-700">Total Transactions</span>
                    <span className="font-bold text-gray-900">
                      {formatNumber(revenueData.treasury?.transaction_count || 0)}
                    </span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span className="text-gray-700">Average per Transaction</span>
                    <span className="font-bold text-gray-900">
                      {formatCurrency(
                        revenueData.treasury?.transaction_count > 0 
                          ? totalRevenue / revenueData.treasury.transaction_count 
                          : 0
                      )}
                    </span>
                  </div>
                </div>
              </div>

              <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                <h4 className="font-semibold text-gray-900 mb-4">Performance Indicators</h4>
                <div className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-gray-700">Collection Rate</span>
                    <span className={`font-bold ${
                      collectionRate >= 90 ? 'text-green-600' :
                      collectionRate >= 75 ? 'text-yellow-600' :
                      'text-red-600'
                    }`}>
                      {formatPercent(collectionRate)}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-gray-700">Revenue vs Target</span>
                    <span className={`font-bold ${
                      totalRevenue >= totalTarget ? 'text-green-600' : 'text-red-600'
                    }`}>
                      {formatCurrency(totalRevenue)} / {formatCurrency(totalTarget)}
                    </span>
                  </div>
                </div>
              </div>

              <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                <h4 className="font-semibold text-gray-900 mb-4">Data Information</h4>
                <div className="space-y-2 text-sm text-gray-600">
                  <div className="flex justify-between">
                    <span>Selected System:</span>
                    <span className="font-medium">{getSystemName(systemFilter)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Selected Year:</span>
                    <span className="font-medium">{selectedYear}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Available Years:</span>
                    <span className="font-medium">{availableYears.length}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Last Updated:</span>
                    <span className="font-medium">
                      {new Date(revenueData.timestamp).toLocaleTimeString('en-PH')}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Footer */}
        <div className="text-center text-sm text-gray-500 pt-6 border-t border-gray-200">
          <p>Treasury Revenue Dashboard • {getSystemName(systemFilter)} • Year: {selectedYear}</p>
          <p className="text-xs text-gray-400 mt-1">
            Total Revenue: {formatCurrency(totalRevenue)} • 
            Collection Rate: {formatPercent(collectionRate)} • 
            {systemFilter === 'all' && ` Systems: ${revenueData.system_breakdown?.length || 0}`}
          </p>
        </div>
      </div>
    </div>
  );
}