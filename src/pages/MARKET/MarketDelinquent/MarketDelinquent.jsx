import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
  Search, Filter, Eye, Download, RefreshCw, AlertCircle, 
  Calendar, FileText, Home, MapPin, User, DollarSign,
  Clock, TrendingUp, AlertTriangle, Percent, CreditCard,
  Building, CheckCircle, Send, Bell, MoreVertical, 
  ChevronDown, ExternalLink, Mail, Users, Store,
  FileSpreadsheet, Database, FileWarning, BellRing,
  CircleDollarSign, CalendarDays, Timer, CheckSquare,
  XCircle, ChevronRight, ChevronLeft, Plus, Minus,
  FileCheck, UserCheck, Receipt, Key, Shield, Archive,
  ChartPie, ChartLine, ChartBar, Activity, BarChart,
  PieChart, LineChart, Grid3x3, Compass, Navigation,
  Trophy, Star, Award, Crown, Zap, Package, ShoppingBag,
  ShoppingCart, DoorOpen, Calculator, Table, Layers,
  Grid, Landmark, Target, Percent as PercentIcon,
  Target as TargetIcon, Users as UsersIcon, TrendingUp as TrendingUpIcon,
  DollarSign as DollarSignIcon, CreditCard as CreditCardIcon,
  Calendar as CalendarIcon, Clock as ClockIcon, Filter as FilterIcon
} from "lucide-react";

// Custom colors matching the dashboard
const COLORS = {
  primary: '#3b82f6',
  secondary: '#6b7280',
  success: '#10b981',
  background: '#f9fafb',
  warning: '#f59e0b',
  danger: '#ef4444',
  info: '#06b6d4',
  dark: '#1f2937',
  white: '#ffffff'
};

export default function MarketDelinquent() {
  const [delinquents, setDelinquents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedStatus, setSelectedStatus] = useState('all');
  const [currentPage, setCurrentPage] = useState(1);
  const [selectedDelinquent, setSelectedDelinquent] = useState(null);
  const [showFilters, setShowFilters] = useState(false);
  const itemsPerPage = 10;

  // FIXED: Dynamic API base URL
  const getApiBaseUrl = () => {
    const { hostname, protocol, pathname } = window.location;
    
    // Production domain - NO /revenue2 in path
    if (hostname === 'revenuetreasury.goserveph.com') {
      return `${protocol}//${hostname}/backend`;
    }
    
    // Localhost - WITH /revenue2 in path
    if (hostname === 'localhost' || hostname === '127.0.0.1') {
      return 'http://localhost/revenue2/backend';
    }
    
    // Default: Try to detect from current path
    if (pathname.includes('/revenue2')) {
      return '/revenue2/backend';
    } else {
      return '/backend';
    }
  };

  // Fetch delinquent data
  const fetchDelinquents = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const API_BASE = getApiBaseUrl();
      const url = `${API_BASE}/Market/Delinquent/get_delinquents.php`;
      
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
        },
        mode: 'cors'
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.status === 'success') {
        setDelinquents(data.data || []);
      } else {
        throw new Error(data.message || "Failed to fetch delinquent data");
      }
    } catch (err) {
      console.error("Fetch error:", err);
      setError(err.message || "Failed to load delinquent data.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDelinquents();
  }, []);

  // Format currency
  const formatCurrency = (amount) => {
    const num = parseFloat(amount) || 0;
    if (num >= 1000000) return `₱${(num / 1000000).toFixed(2)}M`;
    if (num >= 1000) return `₱${(num / 1000).toFixed(2)}K`;
    return `₱${num.toFixed(2)}`;
  };

  // Format date
  const formatDate = (dateString) => {
    if (!dateString || dateString === '0000-00-00 00:00:00' || dateString === '0000-00-00') {
      return 'N/A';
    }
    
    try {
      const date = new Date(dateString);
      
      if (isNaN(date.getTime())) {
        return 'Invalid Date';
      }
      
      return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    } catch (e) {
      return 'Date Error';
    }
  };

  // Calculate days overdue
  const calculateDaysOverdue = (dueDate) => {
    if (!dueDate || dueDate === '0000-00-00' || dueDate === '0000-00-00 00:00:00') {
      return 0;
    }
    
    const due = new Date(dueDate);
    const today = new Date();
    const diffTime = today - due;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    return diffDays > 0 ? diffDays : 0;
  };

  // Get overdue status color
  const getOverdueStatusColor = (daysOverdue) => {
    if (daysOverdue === 0) return 'bg-green-100 text-green-800';
    if (daysOverdue <= 7) return 'bg-yellow-100 text-yellow-800';
    if (daysOverdue <= 30) return 'bg-orange-100 text-orange-800';
    return 'bg-red-100 text-red-800';
  };

  // Get overdue status text
  const getOverdueStatusText = (daysOverdue) => {
    if (daysOverdue === 0) return 'Current';
    if (daysOverdue <= 7) return 'Mild';
    if (daysOverdue <= 30) return 'Moderate';
    return 'Severe';
  };

  // Filter delinquents based on search and status
  const filteredDelinquents = delinquents.filter(delinquent => {
    // Search filter
    const searchLower = searchTerm.toLowerCase();
    const matchesSearch = 
      !searchTerm ||
      (delinquent.business_name && delinquent.business_name.toLowerCase().includes(searchLower)) ||
      (delinquent.stall_rights_no && delinquent.stall_rights_no.toLowerCase().includes(searchLower)) ||
      (delinquent.renter_code && delinquent.renter_code.toLowerCase().includes(searchLower)) ||
      (delinquent.full_name && delinquent.full_name.toLowerCase().includes(searchLower));
    
    // Status filter
    const daysOverdue = calculateDaysOverdue(delinquent.due_date);
    let matchesStatus = true;
    
    switch (selectedStatus) {
      case 'current':
        matchesStatus = daysOverdue === 0;
        break;
      case 'mild':
        matchesStatus = daysOverdue > 0 && daysOverdue <= 7;
        break;
      case 'moderate':
        matchesStatus = daysOverdue > 7 && daysOverdue <= 30;
        break;
      case 'severe':
        matchesStatus = daysOverdue > 30;
        break;
      default:
        matchesStatus = true;
    }
    
    return matchesSearch && matchesStatus;
  });

  // Calculate pagination
  const totalPages = Math.ceil(filteredDelinquents.length / itemsPerPage);
  const indexOfLastItem = currentPage * itemsPerPage;
  const indexOfFirstItem = indexOfLastItem - itemsPerPage;
  const currentDelinquents = filteredDelinquents.slice(indexOfFirstItem, indexOfLastItem);

  // Calculate summary statistics
  const calculateSummary = () => {
    const totalDelinquents = delinquents.length;
    const totalOverdueAmount = delinquents.reduce((sum, d) => sum + (parseFloat(d.overdue_amount) || 0), 0);
    const totalDaysOverdue = delinquents.reduce((sum, d) => sum + calculateDaysOverdue(d.due_date), 0);
    const averageDaysOverdue = totalDelinquents > 0 ? Math.round(totalDaysOverdue / totalDelinquents) : 0;
    
    // Count by severity
    const severityCounts = {
      current: 0,
      mild: 0,
      moderate: 0,
      severe: 0
    };
    
    delinquents.forEach(d => {
      const daysOverdue = calculateDaysOverdue(d.due_date);
      if (daysOverdue === 0) severityCounts.current++;
      else if (daysOverdue <= 7) severityCounts.mild++;
      else if (daysOverdue <= 30) severityCounts.moderate++;
      else severityCounts.severe++;
    });
    
    return {
      totalDelinquents,
      totalOverdueAmount,
      averageDaysOverdue,
      severityCounts
    };
  };

  const summary = calculateSummary();

  // Export to CSV function
  const exportToExcel = () => {
    const exportData = filteredDelinquents.map(d => ({
      'Renter Name': d.full_name || 'N/A',
      'Renter Code': d.renter_code || 'N/A',
      'Business Name': d.business_name || 'N/A',
      'Stall Rights No': d.stall_rights_no || 'N/A',
      'Stall Class': d.stall_class || 'N/A',
      'Overdue Amount': formatCurrency(d.overdue_amount || 0),
      'Due Date': formatDate(d.due_date),
      'Days Overdue': calculateDaysOverdue(d.due_date),
      'Status': getOverdueStatusText(calculateDaysOverdue(d.due_date)),
      'Mobile': d.mobile || 'N/A',
      'Last Payment': formatDate(d.last_payment_date)
    }));
    
    const csvContent = [
      Object.keys(exportData[0] || {}).join(','),
      ...exportData.map(row => Object.values(row).join(','))
    ].join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `delinquents_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
  };

  // Render loading state
  if (loading) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
        {/* Header */}
        <div className="border-b bg-white">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: COLORS.primary }}>
                <Store className="w-6 h-6 text-white" />
              </div>
              <div>
                <h1 className="text-xl font-bold" style={{ color: COLORS.dark }}>Market Revenue System</h1>
                <p className="text-xs" style={{ color: COLORS.secondary }}>Delinquent Management</p>
              </div>
            </div>
          </div>
        </div>
        
        <div className="flex flex-col justify-center items-center h-screen">
          <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-gray-800 mb-4"></div>
          <p className="text-gray-600">Loading Delinquent Records...</p>
          <p className="text-sm text-gray-400 mt-2">Fetching overdue payment data</p>
        </div>
      </div>
    );
  }

  // Render error state
  if (error) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
        {/* Header */}
        <div className="border-b bg-white">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: COLORS.primary }}>
                <Store className="w-6 h-6 text-white" />
              </div>
              <div>
                <h1 className="text-xl font-bold" style={{ color: COLORS.dark }}>Market Revenue System</h1>
                <p className="text-xs" style={{ color: COLORS.secondary }}>Delinquent Management</p>
              </div>
            </div>
          </div>
        </div>
        
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div className="bg-red-50 border border-red-200 rounded-xl p-6">
            <div className="flex items-center space-x-3 mb-4">
              <div className="p-3 rounded-lg bg-red-100">
                <AlertCircle className="w-6 h-6 text-red-600" />
              </div>
              <div>
                <h3 className="text-lg font-semibold text-red-600">Error Loading Data</h3>
                <p className="text-red-600">{error}</p>
              </div>
            </div>
            <button 
              onClick={fetchDelinquents}
              className="px-4 py-2 rounded-lg flex items-center gap-2 transition-all"
              style={{ backgroundColor: COLORS.primary, color: 'white' }}
            >
              <RefreshCw className="w-4 h-4" />
              Try Again
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
      {/* Header */}
      <div className="border-b bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: COLORS.primary }}>
                <Store className="w-6 h-6 text-white" />
              </div>
              <div>
                <h1 className="text-xl font-bold" style={{ color: COLORS.dark }}>Market Revenue System</h1>
                <p className="text-xs" style={{ color: COLORS.secondary }}>Delinquent Management</p>
              </div>
              
              {/* Breadcrumb */}
              <div className="hidden lg:flex items-center gap-2 text-sm ml-6">
                <Link to="/admin/dashboard" className="hover:text-blue-600 transition-colors" style={{ color: COLORS.secondary }}>
                  Dashboard
                </Link>
                <ChevronRight className="w-3 h-3" style={{ color: COLORS.secondary }} />
                <Link to="/admin/market" className="hover:text-blue-600 transition-colors" style={{ color: COLORS.secondary }}>
                  Market
                </Link>
                <ChevronRight className="w-3 h-3" style={{ color: COLORS.secondary }} />
                <span style={{ color: COLORS.primary, fontWeight: '500' }}>Delinquents</span>
              </div>
            </div>
            
            <div className="flex items-center gap-3">
              <button className="p-2 hover:bg-gray-100 rounded-lg transition-colors" title="Help">
                <AlertCircle className="w-5 h-5" style={{ color: COLORS.secondary }} />
              </button>
              <button className="p-2 hover:bg-gray-100 rounded-lg transition-colors relative" title="Notifications">
                <Bell className="w-5 h-5" style={{ color: COLORS.secondary }} />
                <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
              </button>
              <div className="flex items-center gap-2 p-2">
                <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                  <User className="w-4 h-4 text-blue-600" />
                </div>
                <div className="hidden md:block">
                  <p className="text-sm font-medium" style={{ color: COLORS.dark }}>Admin User</p>
                  <p className="text-xs" style={{ color: COLORS.secondary }}>Administrator</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Page Header */}
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>
              Market Delinquent Management
            </h1>
            <p className="text-sm" style={{ color: COLORS.secondary }}>
              Monitor and manage overdue stall rental payments
            </p>
          </div>
          
          <div className="flex flex-wrap gap-3">
            <button
              onClick={() => setShowFilters(!showFilters)}
              className="flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all"
              style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
            >
              <Filter className="w-4 h-4" />
              {showFilters ? 'Hide Filters' : 'Show Filters'}
            </button>
            <button
              onClick={fetchDelinquents}
              disabled={loading}
              className="flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all disabled:opacity-50"
              style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
            >
              <RefreshCw className="w-4 h-4" />
              Refresh
            </button>
            <button 
              onClick={exportToExcel}
              className="flex items-center gap-2 px-4 py-2 rounded-lg transition-all"
              style={{ backgroundColor: COLORS.primary, color: 'white' }}
            >
              <Download className="w-4 h-4" />
              Export
            </button>
          </div>
        </div>

        {/* Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                <Users className="w-6 h-6" style={{ color: COLORS.primary }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full" 
                    style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                Total
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Total Delinquents
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{summary.totalDelinquents}</p>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Overdue accounts requiring attention
            </div>
          </div>
          
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.danger}15` }}>
                <DollarSign className="w-6 h-6" style={{ color: COLORS.danger }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full bg-red-100 text-red-800">
                Outstanding
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Total Overdue Amount
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{formatCurrency(summary.totalOverdueAmount)}</p>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Uncollected rental fees
            </div>
          </div>
          
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.warning}15` }}>
                <CalendarDays className="w-6 h-6" style={{ color: COLORS.warning }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full" 
                    style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                Average
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Average Days Overdue
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{summary.averageDaysOverdue} days</p>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Mean delinquency period
            </div>
          </div>
          
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.info}15` }}>
                <AlertTriangle className="w-6 h-6" style={{ color: COLORS.info }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full bg-red-100 text-red-800">
                Critical
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Severe Cases
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{summary.severityCounts.severe}</p>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              30+ days overdue
            </div>
          </div>
        </div>

        {/* Filters Section */}
        {showFilters && (
          <div className="bg-white border rounded-xl p-6" style={{ borderColor: COLORS.secondary }}>
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div className="flex-1">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                         style={{ color: COLORS.secondary }} />
                  <input
                    type="text"
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="block w-full pl-10 pr-3 py-2.5 border rounded-lg bg-white"
                    style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                    placeholder="Search by business name, stall rights no, renter code..."
                  />
                </div>
              </div>
              
              <div className="flex flex-wrap gap-3">
                <select
                  value={selectedStatus}
                  onChange={(e) => setSelectedStatus(e.target.value)}
                  className="px-4 py-2.5 border rounded-lg bg-white"
                  style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                >
                  <option value="all">All Status</option>
                  <option value="current">Current (0 days)</option>
                  <option value="mild">Mild (1-7 days)</option>
                  <option value="moderate">Moderate (8-30 days)</option>
                  <option value="severe">Severe (30+ days)</option>
                </select>
              </div>
            </div>
            
            {/* Quick Stats */}
            <div className="flex flex-wrap gap-3 mt-4 pt-4" style={{ borderTop: `1px solid ${COLORS.secondary}` }}>
              <button 
                onClick={() => setSelectedStatus('current')}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${selectedStatus === 'current' ? 'text-white' : 'border hover:bg-gray-50'}`}
                style={{ 
                  backgroundColor: selectedStatus === 'current' ? COLORS.success : 'transparent',
                  borderColor: selectedStatus !== 'current' ? COLORS.secondary : 'transparent',
                  color: selectedStatus === 'current' ? 'white' : COLORS.dark
                }}
              >
                Current: {summary.severityCounts.current}
              </button>
              <button 
                onClick={() => setSelectedStatus('mild')}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${selectedStatus === 'mild' ? 'text-white' : 'border hover:bg-gray-50'}`}
                style={{ 
                  backgroundColor: selectedStatus === 'mild' ? '#ff9800' : 'transparent',
                  borderColor: selectedStatus !== 'mild' ? COLORS.secondary : 'transparent',
                  color: selectedStatus === 'mild' ? 'white' : COLORS.dark
                }}
              >
                Mild: {summary.severityCounts.mild}
              </button>
              <button 
                onClick={() => setSelectedStatus('moderate')}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${selectedStatus === 'moderate' ? 'text-white' : 'border hover:bg-gray-50'}`}
                style={{ 
                  backgroundColor: selectedStatus === 'moderate' ? COLORS.warning : 'transparent',
                  borderColor: selectedStatus !== 'moderate' ? COLORS.secondary : 'transparent',
                  color: selectedStatus === 'moderate' ? 'white' : COLORS.dark
                }}
              >
                Moderate: {summary.severityCounts.moderate}
              </button>
              <button 
                onClick={() => setSelectedStatus('severe')}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${selectedStatus === 'severe' ? 'text-white' : 'border hover:bg-gray-50'}`}
                style={{ 
                  backgroundColor: selectedStatus === 'severe' ? COLORS.danger : 'transparent',
                  borderColor: selectedStatus !== 'severe' ? COLORS.secondary : 'transparent',
                  color: selectedStatus === 'severe' ? 'white' : COLORS.dark
                }}
              >
                Severe: {summary.severityCounts.severe}
              </button>
            </div>
          </div>
        )}

        {/* Delinquent Table */}
        <div className="bg-white border rounded-xl overflow-hidden" style={{ borderColor: COLORS.secondary }}>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y" style={{ borderColor: COLORS.secondary }}>
              <thead className="bg-gray-50">
                <tr>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                      style={{ color: COLORS.secondary }}>
                    Renter Details
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                      style={{ color: COLORS.secondary }}>
                    Stall Information
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                      style={{ color: COLORS.secondary }}>
                    Payment Status
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                      style={{ color: COLORS.secondary }}>
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y" style={{ borderColor: COLORS.secondary }}>
                {currentDelinquents.length === 0 ? (
                  <tr>
                    <td colSpan="4" className="px-6 py-12 text-center">
                      <div className="flex flex-col items-center justify-center">
                        <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                          <CheckCircle className="w-8 h-8" style={{ color: COLORS.secondary }} />
                        </div>
                        <p className="text-lg font-medium mb-1" style={{ color: COLORS.dark }}>No delinquent records found</p>
                        <p style={{ color: COLORS.secondary }}>
                          {searchTerm || selectedStatus !== 'all' ? 'Try adjusting your filters' : 'All payments are up to date'}
                        </p>
                      </div>
                    </td>
                  </tr>
                ) : (
                  currentDelinquents.map((delinquent) => {
                    const daysOverdue = calculateDaysOverdue(delinquent.due_date);
                    const statusColor = getOverdueStatusColor(daysOverdue);
                    const statusText = getOverdueStatusText(daysOverdue);
                    
                    return (
                      <tr key={delinquent.id} className="hover:bg-gray-50 transition-colors">
                        <td className="px-6 py-4">
                          <div className="flex items-center">
                            <div className="flex-shrink-0 h-10 w-10 rounded-full flex items-center justify-center"
                                 style={{ backgroundColor: `${COLORS.primary}15` }}>
                              <User className="w-5 h-5" style={{ color: COLORS.primary }} />
                            </div>
                            <div className="ml-4">
                              <div className="text-sm font-medium" style={{ color: COLORS.dark }}>
                                {delinquent.full_name || 'N/A'}
                              </div>
                              <div className="text-sm" style={{ color: COLORS.secondary }}>
                                {delinquent.renter_code || 'N/A'}
                              </div>
                              <div className="text-xs mt-1" style={{ color: COLORS.secondary }}>
                                <Phone className="w-3 h-3 inline-block mr-1" /> {delinquent.mobile || 'N/A'}
                              </div>
                            </div>
                          </div>
                        </td>
                        
                        <td className="px-6 py-4">
                          <div className="text-sm font-medium" style={{ color: COLORS.dark }}>
                            {delinquent.business_name || 'N/A'}
                          </div>
                          <div className="text-sm" style={{ color: COLORS.secondary }}>
                            {delinquent.stall_rights_no || 'N/A'} • {delinquent.stall_name || 'N/A'}
                          </div>
                          <div className="text-xs mt-1" style={{ color: COLORS.secondary }}>
                            <Store className="w-3 h-3 inline-block mr-1" /> {delinquent.stall_class || 'N/A'} Class
                          </div>
                        </td>
                        
                        <td className="px-6 py-4">
                          <div className="space-y-2">
                            <div className="flex items-center justify-between">
                              <span className="text-sm" style={{ color: COLORS.secondary }}>Amount Due:</span>
                              <span className="text-sm font-semibold" style={{ color: COLORS.danger }}>
                                {formatCurrency(delinquent.overdue_amount || 0)}
                              </span>
                            </div>
                            <div className="flex items-center justify-between">
                              <span className="text-sm" style={{ color: COLORS.secondary }}>Due Date:</span>
                              <span className="text-sm" style={{ color: COLORS.dark }}>
                                {formatDate(delinquent.due_date)}
                              </span>
                            </div>
                            <div className="flex items-center justify-between">
                              <span className="text-sm" style={{ color: COLORS.secondary }}>Days Overdue:</span>
                              <span className={`px-2 py-1 rounded-full text-xs font-medium ${statusColor}`}>
                                {daysOverdue} days ({statusText})
                              </span>
                            </div>
                            {delinquent.last_payment_date && (
                              <div className="flex items-center justify-between">
                                <span className="text-sm" style={{ color: COLORS.secondary }}>Last Payment:</span>
                                <span className="text-sm" style={{ color: COLORS.success }}>
                                  {formatDate(delinquent.last_payment_date)}
                                </span>
                              </div>
                            )}
                          </div>
                        </td>
                        
                        <td className="px-6 py-4">
                          <div className="flex space-x-2">
                            <button
                              onClick={() => setSelectedDelinquent(delinquent)}
                              className="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors flex items-center gap-1"
                              style={{ 
                                backgroundColor: `${COLORS.primary}15`, 
                                color: COLORS.primary,
                                border: `1px solid ${COLORS.primary}30`
                              }}
                            >
                              <Eye className="w-3 h-3" /> View
                            </button>
                            <button
                              onClick={() => {
                                console.log('Send reminder to:', delinquent.id);
                                alert(`Reminder sent to ${delinquent.full_name || "renter"}`);
                              }}
                              className="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors flex items-center gap-1"
                              style={{ 
                                backgroundColor: `${COLORS.warning}15`, 
                                color: COLORS.warning,
                                border: `1px solid ${COLORS.warning}30`
                              }}
                            >
                              <Bell className="w-3 h-3" /> Remind
                            </button>
                            <Link
                              to={`/admin/market/validation/${delinquent.application_id}`}
                              className="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors flex items-center gap-1"
                              style={{ 
                                backgroundColor: `${COLORS.success}15`, 
                                color: COLORS.success,
                                border: `1px solid ${COLORS.success}30`
                              }}
                            >
                              <CreditCard className="w-3 h-3" /> Payment
                            </Link>
                          </div>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
          
          {/* Pagination */}
          {totalPages > 1 && (
            <div className="px-6 py-4 bg-gray-50" style={{ borderTop: `1px solid ${COLORS.secondary}` }}>
              <div className="flex items-center justify-between">
                <div className="text-sm" style={{ color: COLORS.secondary }}>
                  Showing <span className="font-medium" style={{ color: COLORS.dark }}>{indexOfFirstItem + 1}</span> to{' '}
                  <span className="font-medium" style={{ color: COLORS.dark }}>
                    {Math.min(indexOfLastItem, filteredDelinquents.length)}
                  </span>{' '}
                  of <span className="font-medium" style={{ color: COLORS.dark }}>{filteredDelinquents.length}</span> results
                </div>
                <div className="flex gap-2">
                  <button
                    onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
                    disabled={currentPage === 1}
                    className="px-3 py-2 border rounded-lg bg-white disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition-colors"
                    style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                  >
                    <ChevronLeft className="w-4 h-4" />
                  </button>
                  
                  {Array.from({ length: totalPages }, (_, i) => i + 1)
                    .filter(page => {
                      return page === 1 || 
                             page === totalPages || 
                             (page >= currentPage - 1 && page <= currentPage + 1);
                    })
                    .map((page, index, array) => {
                      const prevPage = array[index - 1];
                      if (prevPage && page - prevPage > 1) {
                        return (
                          <React.Fragment key={`ellipsis-${page}`}>
                            <span className="px-3 py-2" style={{ color: COLORS.secondary }}>...</span>
                            <button
                              onClick={() => setCurrentPage(page)}
                              className={`px-3 py-2 rounded-lg transition-colors border ${currentPage === page ? 'text-white' : 'bg-white hover:bg-gray-50'}`}
                              style={{ 
                                backgroundColor: currentPage === page ? COLORS.primary : 'transparent',
                                color: currentPage === page ? 'white' : COLORS.dark,
                                borderColor: currentPage === page ? COLORS.primary : COLORS.secondary
                              }}
                            >
                              {page}
                            </button>
                          </React.Fragment>
                        );
                      }
                      
                      return (
                        <button
                          key={page}
                          onClick={() => setCurrentPage(page)}
                          className={`px-3 py-2 rounded-lg transition-colors border ${currentPage === page ? 'text-white' : 'bg-white hover:bg-gray-50'}`}
                          style={{ 
                            backgroundColor: currentPage === page ? COLORS.primary : 'transparent',
                            color: currentPage === page ? 'white' : COLORS.dark,
                            borderColor: currentPage === page ? COLORS.primary : COLORS.secondary
                          }}
                        >
                          {page}
                        </button>
                      );
                    })}
                  
                  <button
                    onClick={() => setCurrentPage(prev => Math.min(prev + 1, totalPages))}
                    disabled={currentPage === totalPages}
                    className="px-3 py-2 border rounded-lg bg-white disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition-colors"
                    style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                  >
                    <ChevronRight className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Summary Section */}
        {delinquents.length > 0 && (
          <div className="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Severity Breakdown */}
            <div className="bg-white border rounded-xl p-6" style={{ borderColor: COLORS.secondary }}>
              <h3 className="text-lg font-semibold mb-4" style={{ color: COLORS.dark }}>Severity Breakdown</h3>
              <div className="space-y-4">
                {[
                  { label: 'Current (0 days)', count: summary.severityCounts.current, color: COLORS.success },
                  { label: 'Mild (1-7 days)', count: summary.severityCounts.mild, color: '#ff9800' },
                  { label: 'Moderate (8-30 days)', count: summary.severityCounts.moderate, color: COLORS.warning },
                  { label: 'Severe (30+ days)', count: summary.severityCounts.severe, color: COLORS.danger }
                ].map((item) => (
                  <div key={item.label} className="space-y-2">
                    <div className="flex justify-between text-sm">
                      <span style={{ color: COLORS.dark }}>{item.label}</span>
                      <span className="font-medium" style={{ color: COLORS.dark }}>{item.count} cases</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className="h-2 rounded-full transition-all duration-500"
                        style={{ 
                          width: `${(item.count / summary.totalDelinquents) * 100}%`,
                          backgroundColor: item.color
                        }}
                      ></div>
                    </div>
                    <div className="text-xs" style={{ color: COLORS.secondary }}>
                      {((item.count / summary.totalDelinquents) * 100).toFixed(1)}% of total delinquents
                    </div>
                  </div>
                ))}
              </div>
            </div>
            
            {/* Quick Actions */}
            <div className="bg-white border rounded-xl p-6" style={{ borderColor: COLORS.secondary }}>
              <h3 className="text-lg font-semibold mb-4" style={{ color: COLORS.dark }}>Quick Actions</h3>
              <div className="grid grid-cols-2 gap-4">
                <button className="p-4 border rounded-xl hover:shadow-md transition-all duration-200 flex flex-col items-center justify-center"
                        style={{ borderColor: COLORS.secondary }}>
                  <div className="p-3 rounded-lg mb-2" style={{ backgroundColor: `${COLORS.primary}15` }}>
                    <Send className="w-5 h-5" style={{ color: COLORS.primary }} />
                  </div>
                  <span className="font-medium text-center" style={{ color: COLORS.dark }}>Send Bulk Reminders</span>
                </button>
                
                <button 
                  onClick={exportToExcel}
                  className="p-4 border rounded-xl hover:shadow-md transition-all duration-200 flex flex-col items-center justify-center"
                  style={{ borderColor: COLORS.secondary }}>
                  <div className="p-3 rounded-lg mb-2" style={{ backgroundColor: `${COLORS.success}15` }}>
                    <FileSpreadsheet className="w-5 h-5" style={{ color: COLORS.success }} />
                  </div>
                  <span className="font-medium text-center" style={{ color: COLORS.dark }}>Export Report</span>
                </button>
                
                <button className="p-4 border rounded-xl hover:shadow-md transition-all duration-200 flex flex-col items-center justify-center"
                        style={{ borderColor: COLORS.secondary }}>
                  <div className="p-3 rounded-lg mb-2" style={{ backgroundColor: `${COLORS.warning}15` }}>
                    <BarChart className="w-5 h-5" style={{ color: COLORS.warning }} />
                  </div>
                  <span className="font-medium text-center" style={{ color: COLORS.dark }}>Generate Analytics</span>
                </button>
                
                <button className="p-4 border rounded-xl hover:shadow-md transition-all duration-200 flex flex-col items-center justify-center"
                        style={{ borderColor: COLORS.secondary }}>
                  <div className="p-3 rounded-lg mb-2" style={{ backgroundColor: `${COLORS.info}15` }}>
                    <Settings className="w-5 h-5" style={{ color: COLORS.info }} />
                  </div>
                  <span className="font-medium text-center" style={{ color: COLORS.dark }}>Settings</span>
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Footer */}
        <div className="text-center py-4" style={{ color: COLORS.secondary }}>
          <div className="text-sm">
            Market Delinquent Management System • Data last updated: {new Date().toLocaleDateString('en-PH', {
              year: 'numeric',
              month: 'long',
              day: 'numeric'
            })}
          </div>
          <div className="text-xs mt-1">
            This system helps track and manage overdue stall rental payments efficiently.
          </div>
        </div>
      </div>

      {/* Delinquent Detail Modal */}
      {selectedDelinquent && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div className="p-6">
              <div className="flex justify-between items-center mb-6">
                <h3 className="text-xl font-bold" style={{ color: COLORS.dark }}>Delinquent Details</h3>
                <button 
                  onClick={() => setSelectedDelinquent(null)}
                  className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                  style={{ color: COLORS.secondary }}
                >
                  <XCircle className="w-5 h-5" />
                </button>
              </div>
              
              <div className="space-y-6">
                {/* Renter Info */}
                <div>
                  <h4 className="font-semibold mb-3 flex items-center" style={{ color: COLORS.dark, borderBottom: `2px solid ${COLORS.primary}30`, paddingBottom: '0.5rem' }}>
                    <User className="w-4 h-4 mr-2" /> Renter Information
                  </h4>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <p className="text-sm" style={{ color: COLORS.secondary }}>Full Name</p>
                      <p className="font-medium" style={{ color: COLORS.dark }}>{selectedDelinquent.full_name}</p>
                    </div>
                    <div>
                      <p className="text-sm" style={{ color: COLORS.secondary }}>Renter Code</p>
                      <p className="font-medium" style={{ color: COLORS.dark }}>{selectedDelinquent.renter_code}</p>
                    </div>
                    <div>
                      <p className="text-sm" style={{ color: COLORS.secondary }}>Mobile Number</p>
                      <p className="font-medium" style={{ color: COLORS.dark }}>{selectedDelinquent.mobile || 'N/A'}</p>
                    </div>
                    <div>
                      <p className="text-sm" style={{ color: COLORS.secondary }}>Email</p>
                      <p className="font-medium" style={{ color: COLORS.dark }}>{selectedDelinquent.email || 'N/A'}</p>
                    </div>
                  </div>
                </div>
                
                {/* Stall Info */}
                <div>
                  <h4 className="font-semibold mb-3 flex items-center" style={{ color: COLORS.dark, borderBottom: `2px solid ${COLORS.primary}30`, paddingBottom: '0.5rem' }}>
                    <Store className="w-4 h-4 mr-2" /> Stall Information
                  </h4>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <p className="text-sm" style={{ color: COLORS.secondary }}>Business Name</p>
                      <p className="font-medium" style={{ color: COLORS.dark }}>{selectedDelinquent.business_name}</p>
                    </div>
                    <div>
                      <p className="text-sm" style={{ color: COLORS.secondary }}>Stall Rights No.</p>
                      <p className="font-medium" style={{ color: COLORS.dark }}>{selectedDelinquent.stall_rights_no}</p>
                    </div>
                    <div>
                      <p className="text-sm" style={{ color: COLORS.secondary }}>Stall Class</p>
                      <p className="font-medium" style={{ color: COLORS.dark }}>{selectedDelinquent.stall_class}</p>
                    </div>
                    <div>
                      <p className="text-sm" style={{ color: COLORS.secondary }}>Stall Name</p>
                      <p className="font-medium" style={{ color: COLORS.dark }}>{selectedDelinquent.stall_name}</p>
                    </div>
                  </div>
                </div>
                
                {/* Payment Info */}
                <div>
                  <h4 className="font-semibold mb-3 flex items-center" style={{ color: COLORS.dark, borderBottom: `2px solid ${COLORS.primary}30`, paddingBottom: '0.5rem' }}>
                    <DollarSign className="w-4 h-4 mr-2" /> Payment Information
                  </h4>
                  <div className="space-y-3">
                    <div className="flex justify-between items-center p-3 rounded-lg" style={{ backgroundColor: `${COLORS.danger}10` }}>
                      <span style={{ color: COLORS.dark }}>Overdue Amount</span>
                      <span className="font-bold text-lg" style={{ color: COLORS.danger }}>
                        {formatCurrency(selectedDelinquent.overdue_amount)}
                      </span>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <p className="text-sm" style={{ color: COLORS.secondary }}>Due Date</p>
                        <p className="font-medium" style={{ color: COLORS.dark }}>{formatDate(selectedDelinquent.due_date)}</p>
                      </div>
                      <div>
                        <p className="text-sm" style={{ color: COLORS.secondary }}>Days Overdue</p>
                        <p className="font-medium" style={{ color: COLORS.danger }}>
                          {calculateDaysOverdue(selectedDelinquent.due_date)} days
                        </p>
                      </div>
                      {selectedDelinquent.last_payment_date && (
                        <>
                          <div>
                            <p className="text-sm" style={{ color: COLORS.secondary }}>Last Payment Date</p>
                            <p className="font-medium" style={{ color: COLORS.success }}>
                              {formatDate(selectedDelinquent.last_payment_date)}
                            </p>
                          </div>
                          <div>
                            <p className="text-sm" style={{ color: COLORS.secondary }}>Last Payment Amount</p>
                            <p className="font-medium" style={{ color: COLORS.dark }}>
                              {formatCurrency(selectedDelinquent.last_payment_amount || 0)}
                            </p>
                          </div>
                        </>
                      )}
                    </div>
                  </div>
                </div>
                
                {/* Actions */}
                <div className="flex justify-end gap-3 pt-4 border-t" style={{ borderColor: COLORS.secondary }}>
                  <button
                    onClick={() => setSelectedDelinquent(null)}
                    className="px-4 py-2 border rounded-lg hover:bg-gray-50 transition-colors"
                    style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                  >
                    Close
                  </button>
                  <Link
                    to={`/admin/market/validation/${selectedDelinquent.application_id}`}
                    className="px-4 py-2 rounded-lg transition-all flex items-center gap-2"
                    style={{ backgroundColor: COLORS.primary, color: 'white' }}
                  >
                    <CreditCard className="w-4 h-4" />
                    Process Payment
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// Missing Phone and Settings icons - add them to imports or use alternatives
const Phone = (props) => (
  <svg {...props} xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
  </svg>
);

const Settings = (props) => (
  <svg {...props} xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
    <circle cx="12" cy="12" r="3" />
  </svg>
);