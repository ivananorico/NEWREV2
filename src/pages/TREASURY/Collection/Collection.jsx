import React, { useState, useEffect } from 'react';
import { 
  DollarSign, Calendar, TrendingUp, TrendingDown, RefreshCw, Download,
  CreditCard, Wallet, Building2, Store, Landmark, BarChart3, PieChartIcon,
  Table, Filter, CheckCircle, AlertCircle, Users, Percent,
  ChevronDown, ArrowUpRight, ArrowDownRight, FileText
} from 'lucide-react';
import * as XLSX from 'xlsx';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
  PieChart, Pie, Cell, ResponsiveContainer, LineChart, Line
} from 'recharts';

const API_BASE = window.location.hostname === "localhost"
  ? "http://localhost/revenue2/backend/Treasury"
  : "https://revenuetreasury.goserveph.com/backend/Treasury";

export default function Collection() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [collectionData, setCollectionData] = useState(null);
  const [exportLoading, setExportLoading] = useState(false);
  const [viewMode, setViewMode] = useState('charts');
  const [systemFilter, setSystemFilter] = useState('all');
  const [yearFilter, setYearFilter] = useState(new Date().getFullYear());
  const [availableYears, setAvailableYears] = useState([]);
  const [yearDropdownOpen, setYearDropdownOpen] = useState(false);
  const [timeRange, setTimeRange] = useState('year');

  useEffect(() => {
    fetchAvailableYears();
  }, []);

  useEffect(() => {
    if (availableYears.length > 0) {
      if (!availableYears.includes(yearFilter)) {
        setYearFilter(availableYears[0]);
      } else {
        fetchCollectionData();
      }
    }
  }, [availableYears]);

  useEffect(() => {
    if (yearFilter && availableYears.includes(yearFilter)) {
      fetchCollectionData();
    }
  }, [yearFilter, systemFilter, timeRange]);

  const fetchAvailableYears = async () => {
    try {
      const response = await fetch(
        `${API_BASE}/collection_api.php?action=get_available_years`
      );
      const data = await response.json();
      
      if (data.success && data.years && data.years.length > 0) {
        setAvailableYears(data.years.sort((a, b) => b - a));
      } else {
        const currentYear = new Date().getFullYear();
        setAvailableYears([currentYear]);
      }
    } catch (err) {
      console.error('Error fetching years:', err);
      const currentYear = new Date().getFullYear();
      setAvailableYears([currentYear]);
    }
  };

  const fetchCollectionData = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const params = new URLSearchParams({
        action: 'get_collection_data',
        year: yearFilter,
        system: systemFilter,
        range: timeRange
      });
      
      const response = await fetch(
        `${API_BASE}/collection_api.php?${params.toString()}`,
        {
          headers: { 'Accept': 'application/json' }
        }
      );
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (!data.success) {
        throw new Error(data.error || 'Failed to load collection data');
      }
      
      setCollectionData(data);
      
    } catch (err) {
      console.error('Error:', err);
      setError(err.message || 'Error fetching data');
    } finally {
      setLoading(false);
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

  const getSystemName = (system) => {
    switch(system) {
      case 'all': return 'All Systems';
      case 'rpt': return 'Real Property Tax';
      case 'business': return 'Business Tax';
      case 'market': return 'Market Rent';
      default: return system;
    }
  };

  const exportToExcel = () => {
    if (!collectionData) return;
    
    setExportLoading(true);
    try {
      const wb = XLSX.utils.book_new();
      const dateStr = new Date().toISOString().split('T')[0];
      
      const summaryData = [{
        'Year': yearFilter,
        'Time Range': timeRange === 'year' ? 'Annual' : timeRange === 'quarter' ? 'Quarterly' : 'Monthly',
        'System': getSystemName(systemFilter),
        'Total Collected': safeParseFloat(collectionData.total?.collected_amount),
        'Total Target': safeParseFloat(collectionData.total?.target_amount),
        'Collection Rate': safeParseFloat(collectionData.total?.collection_rate),
        'Transactions': safeParseFloat(collectionData.total?.transaction_count),
        'Data Updated': collectionData.timestamp
      }];
      
      const ws1 = XLSX.utils.json_to_sheet(summaryData);
      XLSX.utils.book_append_sheet(wb, ws1, 'Summary');
      
      if (collectionData.monthly && collectionData.monthly.length > 0) {
        const monthlyData = collectionData.monthly.map(m => ({
          'Month': m.month_name || `Month ${m.month}`,
          'Collected Amount': safeParseFloat(m.collected_amount),
          'Target Amount': safeParseFloat(m.target_amount),
          'Collection Rate': safeParseFloat(m.collection_rate),
          'Transactions': safeParseFloat(m.transaction_count)
        }));
        
        const ws2 = XLSX.utils.json_to_sheet(monthlyData);
        XLSX.utils.book_append_sheet(wb, ws2, 'Monthly Data');
      }
      
      XLSX.writeFile(wb, `Collection_Report_${yearFilter}_${dateStr}.xlsx`);
      
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting report');
    } finally {
      setExportLoading(false);
    }
  };

  // System colors
  const systemColors = {
    'RPT': '#4F46E5',
    'Business Tax': '#10B981',
    'Market Rent': '#F59E0B'
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 p-6">
        <div className="flex flex-col justify-center items-center h-64">
          <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-gray-800 mb-4"></div>
          <p className="text-gray-600">Loading Collection Dashboard...</p>
          <p className="text-sm text-gray-400 mt-2">Year: {yearFilter}</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 p-6">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6 max-w-2xl mx-auto">
          <div className="flex items-center space-x-3 mb-4">
            <AlertCircle className="w-8 h-8 text-red-600" />
            <div>
              <h3 className="text-lg font-semibold text-red-600">Error Loading Collection Data</h3>
              <p className="text-red-600">{error}</p>
              <p className="text-sm text-red-500 mt-1">
                Please check if the backend API is running and tables exist.
              </p>
            </div>
          </div>
          <div className="flex gap-3">
            <button 
              onClick={fetchCollectionData}
              className="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 flex items-center gap-2"
            >
              <RefreshCw className="w-4 h-4" />
              Try Again
            </button>
            <button 
              onClick={() => setError(null)}
              className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
            >
              Go Back
            </button>
          </div>
        </div>
      </div>
    );
  }

  const totalCollected = safeParseFloat(collectionData?.total?.collected_amount || 0);
  const totalTarget = safeParseFloat(collectionData?.total?.target_amount || 0);
  const collectionRate = safeParseFloat(collectionData?.total?.collection_rate || 0);
  const totalTransactions = safeParseFloat(collectionData?.total?.transaction_count || 0);

  // Prepare chart data
  const monthlyChartData = collectionData?.monthly?.map(m => ({
    name: m.month_name?.substring(0, 3) || `M${m.month}`,
    collected: safeParseFloat(m.collected_amount),
    target: safeParseFloat(m.target_amount),
    rate: safeParseFloat(m.collection_rate)
  })) || [];

  const systemChartData = collectionData?.system_breakdown?.map(system => ({
    name: system.system,
    value: safeParseFloat(system.collected_amount),
    color: systemColors[system.system] || '#6B7280'
  })) || [];

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Collection Dashboard</h1>
              <p className="text-gray-600 mt-1">Track revenue collection performance across all systems</p>
            </div>
            
            <div className="flex flex-wrap gap-3">
              {/* System Filter */}
              <div className="flex gap-2">
                {['all', 'rpt', 'business', 'market'].map((system) => (
                  <button
                    key={system}
                    onClick={() => setSystemFilter(system)}
                    className={`px-4 py-2 rounded-lg flex items-center gap-2 transition-colors ${
                      systemFilter === system
                        ? 'bg-gray-900 text-white'
                        : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'
                    }`}
                  >
                    {system === 'all' && <CreditCard className="w-4 h-4" />}
                    {system === 'rpt' && <Landmark className="w-4 h-4" />}
                    {system === 'business' && <Building2 className="w-4 h-4" />}
                    {system === 'market' && <Store className="w-4 h-4" />}
                    <span className="text-sm">
                      {system === 'all' ? 'All' : 
                       system === 'rpt' ? 'RPT' : 
                       system === 'business' ? 'Business' : 'Market'}
                    </span>
                  </button>
                ))}
              </div>
              
              {/* Year Selection */}
              <div className="relative">
                <button
                  onClick={() => setYearDropdownOpen(!yearDropdownOpen)}
                  className="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 bg-white"
                >
                  <Calendar className="w-4 h-4" />
                  <span>{yearFilter}</span>
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
                              setYearFilter(year);
                              setYearDropdownOpen(false);
                            }}
                            className={`w-full text-left px-4 py-2 hover:bg-gray-50 transition-colors ${
                              yearFilter === year 
                                ? 'bg-gray-100 text-gray-900 font-medium' 
                                : 'text-gray-700'
                            }`}
                          >
                            <div className="flex items-center justify-between">
                              <span>{year}</span>
                              {yearFilter === year && (
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
              
              {/* Time Range */}
              <div className="flex gap-2">
                {['year', 'quarter', 'month'].map((range) => (
                  <button
                    key={range}
                    onClick={() => setTimeRange(range)}
                    className={`px-3 py-2 rounded-lg text-sm transition-colors ${
                      timeRange === range
                        ? 'bg-gray-900 text-white'
                        : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'
                    }`}
                  >
                    {range === 'year' ? 'Year' : range === 'quarter' ? 'Quarter' : 'Month'}
                  </button>
                ))}
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
              
              {/* Export Button */}
              <button
                onClick={exportToExcel}
                disabled={exportLoading}
                className="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-black disabled:opacity-50 flex items-center gap-2"
              >
                {exportLoading ? (
                  <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div>
                ) : (
                  <Download className="w-4 h-4" />
                )}
                <span>Export</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Key Metrics */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          {/* Total Collected */}
          <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-gray-100 rounded-lg">
                <DollarSign className="w-6 h-6 text-gray-700" />
              </div>
              <div className="text-right">
                <div className={`flex items-center gap-1 ${
                  collectionRate >= 90 ? 'text-green-600' :
                  collectionRate >= 75 ? 'text-yellow-600' :
                  'text-red-600'
                }`}>
                  {collectionRate >= 90 ? (
                    <ArrowUpRight className="w-4 h-4" />
                  ) : collectionRate >= 75 ? (
                    <ArrowDownRight className="w-4 h-4" />
                  ) : (
                    <AlertCircle className="w-4 h-4" />
                  )}
                  <span className="text-sm font-semibold">{formatPercent(collectionRate)}</span>
                </div>
                <span className="text-xs text-gray-500">Rate</span>
              </div>
            </div>
            <h3 className="text-sm font-medium text-gray-600 uppercase tracking-wider mb-2">
              Total Collected
            </h3>
            <p className="text-2xl font-bold text-gray-900 mb-3">{formatCurrency(totalCollected)}</p>
            <div className="text-sm text-gray-600">
              <div className="flex justify-between">
                <span>Target:</span>
                <span className="font-medium">{formatCurrency(totalTarget)}</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2 mt-2">
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

          {/* Transactions */}
          <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-blue-50 rounded-lg">
                <CreditCard className="w-6 h-6 text-blue-600" />
              </div>
              <div className="text-right">
                <div className="flex items-center gap-1 text-blue-600">
                  <Users className="w-4 h-4" />
                  <span className="text-sm font-semibold">{formatNumber(totalTransactions)}</span>
                </div>
                <span className="text-xs text-gray-500">Count</span>
              </div>
            </div>
            <h3 className="text-sm font-medium text-gray-600 uppercase tracking-wider mb-2">
              Total Transactions
            </h3>
            <p className="text-2xl font-bold text-gray-900 mb-3">{formatNumber(totalTransactions)}</p>
            <div className="text-sm text-gray-600 space-y-1">
              <div className="flex justify-between">
                <span>Average Value:</span>
                <span className="font-medium">
                  {formatCurrency(totalTransactions > 0 ? totalCollected / totalTransactions : 0)}
                </span>
              </div>
            </div>
          </div>

          {/* RPT Collection */}
          <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-indigo-50 rounded-lg">
                <Landmark className="w-6 h-6 text-indigo-600" />
              </div>
              <span className="text-xs px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full">
                RPT
              </span>
            </div>
            <h3 className="text-sm font-medium text-gray-600 uppercase tracking-wider mb-2">
              RPT Collection
            </h3>
            <p className="text-2xl font-bold text-gray-900 mb-3">
              {formatCurrency(safeParseFloat(collectionData?.rpt?.collected_amount || 0))}
            </p>
            <div className="text-sm text-gray-600">
              <div className="flex justify-between">
                <span>Target:</span>
                <span>{formatCurrency(safeParseFloat(collectionData?.rpt?.target_amount || 0))}</span>
              </div>
              <div className="flex justify-between mt-1">
                <span>Rate:</span>
                <span className={`font-medium ${
                  safeParseFloat(collectionData?.rpt?.collection_rate) >= 90 ? 'text-green-600' :
                  safeParseFloat(collectionData?.rpt?.collection_rate) >= 75 ? 'text-yellow-600' :
                  'text-red-600'
                }`}>
                  {formatPercent(collectionData?.rpt?.collection_rate || 0)}
                </span>
              </div>
            </div>
          </div>

          {/* Business Tax Collection */}
          <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-emerald-50 rounded-lg">
                <Building2 className="w-6 h-6 text-emerald-600" />
              </div>
              <span className="text-xs px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full">
                Business
              </span>
            </div>
            <h3 className="text-sm font-medium text-gray-600 uppercase tracking-wider mb-2">
              Business Tax Collection
            </h3>
            <p className="text-2xl font-bold text-gray-900 mb-3">
              {formatCurrency(safeParseFloat(collectionData?.business?.collected_amount || 0))}
            </p>
            <div className="text-sm text-gray-600">
              <div className="flex justify-between">
                <span>Target:</span>
                <span>{formatCurrency(safeParseFloat(collectionData?.business?.target_amount || 0))}</span>
              </div>
              <div className="flex justify-between mt-1">
                <span>Rate:</span>
                <span className={`font-medium ${
                  safeParseFloat(collectionData?.business?.collection_rate) >= 90 ? 'text-green-600' :
                  safeParseFloat(collectionData?.business?.collection_rate) >= 75 ? 'text-yellow-600' :
                  'text-red-600'
                }`}>
                  {formatPercent(collectionData?.business?.collection_rate || 0)}
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* Charts View */}
        {viewMode === 'charts' && (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            {/* Monthly Collection Chart */}
            <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                  <BarChart3 className="w-5 h-5 text-gray-600" />
                  Monthly Collection {yearFilter}
                </h3>
                <span className="text-sm text-gray-500">{getSystemName(systemFilter)}</span>
              </div>
              <div style={{ height: '300px', width: '100%' }}>
                {monthlyChartData.length > 0 ? (
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={monthlyChartData}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                      <XAxis dataKey="name" />
                      <YAxis tickFormatter={(value) => formatCurrency(value).replace('₱', '')} />
                      <Tooltip 
                        formatter={(value, name) => {
                          if (name === 'collected' || name === 'target') {
                            return [formatCurrency(value), name === 'collected' ? 'Collected' : 'Target'];
                          }
                          return [value, name];
                        }}
                      />
                      <Legend />
                      <Bar 
                        dataKey="collected" 
                        fill={
                          systemFilter === 'all' ? '#6B7280' :
                          systemFilter === 'rpt' ? '#4F46E5' : 
                          systemFilter === 'business' ? '#10B981' : 
                          '#F59E0B'
                        } 
                        radius={[4, 4, 0, 0]} 
                        name="Collected"
                      />
                      <Bar 
                        dataKey="target" 
                        fill="#D1D5DB" 
                        radius={[4, 4, 0, 0]} 
                        name="Target"
                        opacity={0.6}
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

            {/* System Breakdown Pie Chart */}
            <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                  <PieChartIcon className="w-5 h-5 text-gray-600" />
                  Collection Breakdown by System
                </h3>
                <span className="text-sm text-gray-500">{yearFilter}</span>
              </div>
              <div style={{ height: '300px', width: '100%' }}>
                {systemChartData.length > 0 ? (
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie
                        data={systemChartData}
                        cx="50%"
                        cy="50%"
                        labelLine={false}
                        label={({ name, percent }) => `${name}: ${(percent * 100).toFixed(0)}%`}
                        outerRadius={80}
                        fill="#8884d8"
                        dataKey="value"
                      >
                        {systemChartData.map((entry, index) => (
                          <Cell key={`cell-${index}`} fill={entry.color} />
                        ))}
                      </Pie>
                      <Tooltip formatter={(value) => [formatCurrency(value), 'Collected']} />
                      <Legend />
                    </PieChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="flex flex-col items-center justify-center h-full text-gray-400">
                    <PieChartIcon className="w-12 h-12 mb-2" />
                    <p>No system breakdown available</p>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Detailed View */}
        {viewMode === 'detailed' && (
          <div className="space-y-6">
            {/* Collection Table */}
            <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
              <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 className="font-semibold text-gray-900">Detailed Collection Data</h3>
              </div>
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Period
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Collected Amount
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Target Amount
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Collection Rate
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Transactions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {timeRange === 'month' && collectionData?.monthly && collectionData.monthly.length > 0 ? (
                      collectionData.monthly.map((month, index) => (
                        <tr key={index} className="hover:bg-gray-50">
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="flex items-center">
                              <span className="font-medium text-gray-900">{month.month_name || `Month ${month.month}`}</span>
                            </div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className="font-semibold">{formatCurrency(month.collected_amount)}</span>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className="text-gray-600">{formatCurrency(month.target_amount)}</span>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className={`font-medium ${
                              safeParseFloat(month.collection_rate) >= 90 ? 'text-green-600' :
                              safeParseFloat(month.collection_rate) >= 75 ? 'text-yellow-600' :
                              'text-red-600'
                            }`}>
                              {formatPercent(month.collection_rate)}
                            </span>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className="text-gray-700">{formatNumber(month.transaction_count)}</span>
                          </td>
                        </tr>
                      ))
                    ) : timeRange === 'quarter' && collectionData?.quarterly && collectionData.quarterly.length > 0 ? (
                      collectionData.quarterly.map((quarter, index) => (
                        <tr key={index} className="hover:bg-gray-50">
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="flex items-center">
                              <span className="font-medium text-gray-900">Quarter {quarter.quarter}</span>
                            </div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className="font-semibold">{formatCurrency(quarter.collected_amount)}</span>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className="text-gray-600">{formatCurrency(quarter.target_amount)}</span>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className={`font-medium ${
                              safeParseFloat(quarter.collection_rate) >= 90 ? 'text-green-600' :
                              safeParseFloat(quarter.collection_rate) >= 75 ? 'text-yellow-600' :
                              'text-red-600'
                            }`}>
                              {formatPercent(quarter.collection_rate)}
                            </span>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className="text-gray-700">{formatNumber(quarter.transaction_count)}</span>
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan="5" className="px-6 py-12 text-center text-gray-500">
                          No collection data available for the selected time range
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}

        {/* Summary Info */}
        <div className="mt-8 p-6 bg-white border border-gray-200 rounded-xl shadow-sm">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="space-y-3">
              <h4 className="font-semibold text-gray-900 mb-3">Collection Summary</h4>
              <div className="space-y-2">
                <div className="flex justify-between">
                  <span className="text-gray-600">Total Collected:</span>
                  <span className="font-bold text-gray-900">{formatCurrency(totalCollected)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Total Target:</span>
                  <span className="font-bold text-gray-900">{formatCurrency(totalTarget)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Collection Rate:</span>
                  <span className={`font-bold ${
                    collectionRate >= 90 ? 'text-green-600' :
                    collectionRate >= 75 ? 'text-yellow-600' :
                    'text-red-600'
                  }`}>
                    {formatPercent(collectionRate)}
                  </span>
                </div>
              </div>
            </div>

            <div className="space-y-3">
              <h4 className="font-semibold text-gray-900 mb-3">System Performance</h4>
              <div className="space-y-2">
                {collectionData?.system_breakdown && collectionData.system_breakdown.map((system, index) => (
                  <div key={index} className="flex justify-between items-center">
                    <span className="text-gray-600">{system.system}:</span>
                    <div className="flex items-center gap-2">
                      <span className="font-medium">{formatCurrency(system.collected_amount)}</span>
                      <span className={`text-xs px-2 py-1 rounded-full ${
                        safeParseFloat(system.collection_rate) >= 90 ? 'bg-green-100 text-green-800' :
                        safeParseFloat(system.collection_rate) >= 75 ? 'bg-yellow-100 text-yellow-800' :
                        'bg-red-100 text-red-800'
                      }`}>
                        {formatPercent(system.collection_rate)}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            <div className="space-y-3">
              <h4 className="font-semibold text-gray-900 mb-3">Report Information</h4>
              <div className="space-y-2 text-sm text-gray-600">
                <div className="flex justify-between">
                  <span>Selected System:</span>
                  <span className="font-medium">{getSystemName(systemFilter)}</span>
                </div>
                <div className="flex justify-between">
                  <span>Selected Year:</span>
                  <span className="font-medium">{yearFilter}</span>
                </div>
                <div className="flex justify-between">
                  <span>Time Range:</span>
                  <span className="font-medium">
                    {timeRange === 'year' ? 'Annual' : timeRange === 'quarter' ? 'Quarterly' : 'Monthly'}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span>View Mode:</span>
                  <span className="font-medium">{viewMode === 'charts' ? 'Charts' : 'Detailed Table'}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}