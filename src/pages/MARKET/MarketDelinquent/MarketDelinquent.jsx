import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
  Search, Filter, Download, RefreshCw, AlertCircle, 
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
  Grid, Landmark, Target
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
  white: '#ffffff',
  light: '#e5e7eb'
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
        <div className="border-b bg-white shadow-sm">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: COLORS.primary }}>
                <Store className="w-5 h-5 text-white" />
              </div>
              <div>
                <h1 className="text-lg font-bold" style={{ color: COLORS.dark }}>Market Revenue System</h1>
              </div>
            </div>
          </div>
        </div>
        
        <div className="flex flex-col justify-center items-center h-[80vh]">
          <div className="relative">
            <div className="animate-spin rounded-full h-16 w-16 border-4 border-gray-200 border-t-4" style={{ borderTopColor: COLORS.primary }}></div>
            <div className="absolute inset-0 flex items-center justify-center">
              <Store className="w-6 h-6" style={{ color: COLORS.primary }} />
            </div>
          </div>
          <p className="mt-4 font-medium" style={{ color: COLORS.dark }}>Loading Delinquent Records...</p>
          <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>Fetching overdue payment data</p>
        </div>
      </div>
    );
  }

  // Render error state
  if (error) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
        {/* Header */}
        <div className="border-b bg-white shadow-sm">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: COLORS.primary }}>
                <Store className="w-5 h-5 text-white" />
              </div>
              <div>
                <h1 className="text-lg font-bold" style={{ color: COLORS.dark }}>Market Revenue System</h1>
              </div>
            </div>
          </div>
        </div>
        
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
          <div className="bg-white border rounded-2xl shadow-sm p-8 text-center max-w-2xl mx-auto" style={{ borderColor: COLORS.light }}>
            <div className="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <AlertCircle className="w-10 h-10" style={{ color: COLORS.danger }} />
            </div>
            <h2 className="text-2xl font-bold mb-3" style={{ color: COLORS.dark }}>Unable to Load Data</h2>
            <p className="text-gray-600 mb-8 max-w-md mx-auto">{error}</p>
            <button 
              onClick={fetchDelinquents}
              className="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-white font-medium transition-all hover:shadow-lg"
              style={{ backgroundColor: COLORS.primary }}
            >
              <RefreshCw className="w-5 h-5" />
              Try Again
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
      {/* Header - Clean, no duplicate breadcrumb */}
      <div className="border-b bg-white shadow-sm sticky top-0 z-10">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-16">
            <div className="flex items-center gap-4">
              <div className="p-2 rounded-lg" style={{ backgroundColor: COLORS.primary }}>
                <Store className="w-5 h-5 text-white" />
              </div>
              <div>
                <h1 className="text-lg font-bold" style={{ color: COLORS.dark }}>Market Revenue System</h1>
              </div>
              {/* Breadcrumb - Single instance */}
              <div className="hidden md:flex items-center gap-2 text-sm ml-6">
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
            
            <div className="flex items-center gap-2">
              <button className="p-2 hover:bg-gray-100 rounded-lg transition-colors relative">
                <Bell className="w-5 h-5" style={{ color: COLORS.secondary }} />
                <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full ring-2 ring-white"></span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Page Title */}
        <div className="mb-8">
          <h1 className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>
            Delinquent Management
          </h1>
          <p className="text-sm" style={{ color: COLORS.secondary }}>
            Monitor and manage overdue stall rental payments
          </p>
        </div>

        {/* Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
          <div className="bg-white rounded-xl p-5 border shadow-sm hover:shadow-md transition-all" style={{ borderColor: COLORS.light }}>
            <div className="flex items-center justify-between mb-3">
              <div className="p-2.5 rounded-lg" style={{ backgroundColor: `${COLORS.primary}10` }}>
                <Users className="w-5 h-5" style={{ color: COLORS.primary }} />
              </div>
              <span className="text-xs px-2.5 py-1 rounded-full font-medium" 
                    style={{ backgroundColor: `${COLORS.secondary}10`, color: COLORS.secondary }}>
                Total
              </span>
            </div>
            <p className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>{summary.totalDelinquents}</p>
            <p className="text-xs" style={{ color: COLORS.secondary }}>Total Delinquents</p>
            <div className="mt-3 pt-3 border-t text-xs" style={{ borderColor: COLORS.light, color: COLORS.secondary }}>
              Overdue accounts requiring attention
            </div>
          </div>
          
          <div className="bg-white rounded-xl p-5 border shadow-sm hover:shadow-md transition-all" style={{ borderColor: COLORS.light }}>
            <div className="flex items-center justify-between mb-3">
              <div className="p-2.5 rounded-lg" style={{ backgroundColor: `${COLORS.danger}10` }}>
                <DollarSign className="w-5 h-5" style={{ color: COLORS.danger }} />
              </div>
              <span className="text-xs px-2.5 py-1 rounded-full bg-red-100 text-red-800 font-medium">
                Outstanding
              </span>
            </div>
            <p className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>{formatCurrency(summary.totalOverdueAmount)}</p>
            <p className="text-xs" style={{ color: COLORS.secondary }}>Total Overdue Amount</p>
            <div className="mt-3 pt-3 border-t text-xs" style={{ borderColor: COLORS.light, color: COLORS.secondary }}>
              Uncollected rental fees
            </div>
          </div>
          
          <div className="bg-white rounded-xl p-5 border shadow-sm hover:shadow-md transition-all" style={{ borderColor: COLORS.light }}>
            <div className="flex items-center justify-between mb-3">
              <div className="p-2.5 rounded-lg" style={{ backgroundColor: `${COLORS.warning}10` }}>
                <CalendarDays className="w-5 h-5" style={{ color: COLORS.warning }} />
              </div>
              <span className="text-xs px-2.5 py-1 rounded-full font-medium" 
                    style={{ backgroundColor: `${COLORS.secondary}10`, color: COLORS.secondary }}>
                Average
              </span>
            </div>
            <p className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>{summary.averageDaysOverdue} days</p>
            <p className="text-xs" style={{ color: COLORS.secondary }}>Average Days Overdue</p>
            <div className="mt-3 pt-3 border-t text-xs" style={{ borderColor: COLORS.light, color: COLORS.secondary }}>
              Mean delinquency period
            </div>
          </div>
          
          <div className="bg-white rounded-xl p-5 border shadow-sm hover:shadow-md transition-all" style={{ borderColor: COLORS.light }}>
            <div className="flex items-center justify-between mb-3">
              <div className="p-2.5 rounded-lg" style={{ backgroundColor: `${COLORS.danger}10` }}>
                <AlertTriangle className="w-5 h-5" style={{ color: COLORS.danger }} />
              </div>
              <span className="text-xs px-2.5 py-1 rounded-full bg-red-100 text-red-800 font-medium">
                Critical
              </span>
            </div>
            <p className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>{summary.severityCounts.severe}</p>
            <p className="text-xs" style={{ color: COLORS.secondary }}>Severe Cases</p>
            <div className="mt-3 pt-3 border-t text-xs" style={{ borderColor: COLORS.light, color: COLORS.secondary }}>
              30+ days overdue
            </div>
          </div>
        </div>

        {/* Filters Section */}
        <div className="bg-white rounded-xl border shadow-sm mb-8" style={{ borderColor: COLORS.light }}>
          <div className="p-5">
            <div className="flex flex-col lg:flex-row lg:items-center gap-4">
              <div className="flex-1">
                <div className="relative">
                  <Search className="absolute left-3.5 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                         style={{ color: COLORS.secondary }} />
                  <input
                    type="text"
                    value={searchTerm}
                    onChange={(e) => {
                      setSearchTerm(e.target.value);
                      setCurrentPage(1);
                    }}
                    className="block w-full pl-10 pr-4 py-2.5 border rounded-lg bg-white focus:outline-none focus:ring-2 transition-all text-sm"
                    style={{ 
                      borderColor: showFilters ? COLORS.primary : COLORS.light,
                      color: COLORS.dark,
                      boxShadow: showFilters ? `0 0 0 3px ${COLORS.primary}15` : 'none'
                    }}
                    placeholder="Search by business name, stall rights no, renter code..."
                  />
                </div>
              </div>
              
              <div className="flex flex-wrap items-center gap-3">
                <button
                  onClick={() => setShowFilters(!showFilters)}
                  className="inline-flex items-center gap-2 px-4 py-2.5 border rounded-lg hover:bg-gray-50 transition-all text-sm font-medium"
                  style={{ 
                    borderColor: showFilters ? COLORS.primary : COLORS.light,
                    color: showFilters ? COLORS.primary : COLORS.dark,
                    backgroundColor: showFilters ? `${COLORS.primary}5` : 'transparent'
                  }}
                >
                  <Filter className="w-4 h-4" />
                  {showFilters ? 'Hide Filters' : 'Filters'}
                </button>
                
                <button
                  onClick={fetchDelinquents}
                  disabled={loading}
                  className="inline-flex items-center gap-2 px-4 py-2.5 border rounded-lg hover:bg-gray-50 transition-all disabled:opacity-50 text-sm font-medium"
                  style={{ borderColor: COLORS.light, color: COLORS.dark }}
                >
                  <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
                  Refresh
                </button>
                
                <button 
                  onClick={exportToExcel}
                  disabled={filteredDelinquents.length === 0}
                  className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-white text-sm font-medium transition-all hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                  style={{ backgroundColor: COLORS.primary }}
                >
                  <Download className="w-4 h-4" />
                  Export
                </button>
              </div>
            </div>
            
            {/* Filter Options */}
            {showFilters && (
              <div className="mt-5 pt-5 border-t" style={{ borderColor: COLORS.light }}>
                <div className="flex flex-wrap items-center gap-3">
                  <span className="text-xs font-medium" style={{ color: COLORS.secondary }}>Filter by status:</span>
                  <div className="flex flex-wrap gap-2">
                    <button
                      onClick={() => {
                        setSelectedStatus('all');
                        setCurrentPage(1);
                      }}
                      className={`px-4 py-1.5 rounded-lg text-xs font-medium transition-all ${
                        selectedStatus === 'all' 
                          ? 'text-white shadow-sm' 
                          : 'border hover:bg-gray-50'
                      }`}
                      style={{ 
                        backgroundColor: selectedStatus === 'all' ? COLORS.secondary : 'transparent',
                        borderColor: selectedStatus === 'all' ? 'transparent' : COLORS.light,
                        color: selectedStatus === 'all' ? 'white' : COLORS.dark
                      }}
                    >
                      All ({delinquents.length})
                    </button>
                    <button
                      onClick={() => {
                        setSelectedStatus('current');
                        setCurrentPage(1);
                      }}
                      className={`px-4 py-1.5 rounded-lg text-xs font-medium transition-all ${
                        selectedStatus === 'current' 
                          ? 'text-white shadow-sm' 
                          : 'border hover:bg-gray-50'
                      }`}
                      style={{ 
                        backgroundColor: selectedStatus === 'current' ? COLORS.success : 'transparent',
                        borderColor: selectedStatus === 'current' ? 'transparent' : COLORS.light,
                        color: selectedStatus === 'current' ? 'white' : COLORS.dark
                      }}
                    >
                      Current ({summary.severityCounts.current})
                    </button>
                    <button
                      onClick={() => {
                        setSelectedStatus('mild');
                        setCurrentPage(1);
                      }}
                      className={`px-4 py-1.5 rounded-lg text-xs font-medium transition-all ${
                        selectedStatus === 'mild' 
                          ? 'text-white shadow-sm' 
                          : 'border hover:bg-gray-50'
                      }`}
                      style={{ 
                        backgroundColor: selectedStatus === 'mild' ? '#f59e0b' : 'transparent',
                        borderColor: selectedStatus === 'mild' ? 'transparent' : COLORS.light,
                        color: selectedStatus === 'mild' ? 'white' : COLORS.dark
                      }}
                    >
                      Mild ({summary.severityCounts.mild})
                    </button>
                    <button
                      onClick={() => {
                        setSelectedStatus('moderate');
                        setCurrentPage(1);
                      }}
                      className={`px-4 py-1.5 rounded-lg text-xs font-medium transition-all ${
                        selectedStatus === 'moderate' 
                          ? 'text-white shadow-sm' 
                          : 'border hover:bg-gray-50'
                      }`}
                      style={{ 
                        backgroundColor: selectedStatus === 'moderate' ? COLORS.warning : 'transparent',
                        borderColor: selectedStatus === 'moderate' ? 'transparent' : COLORS.light,
                        color: selectedStatus === 'moderate' ? 'white' : COLORS.dark
                      }}
                    >
                      Moderate ({summary.severityCounts.moderate})
                    </button>
                    <button
                      onClick={() => {
                        setSelectedStatus('severe');
                        setCurrentPage(1);
                      }}
                      className={`px-4 py-1.5 rounded-lg text-xs font-medium transition-all ${
                        selectedStatus === 'severe' 
                          ? 'text-white shadow-sm' 
                          : 'border hover:bg-gray-50'
                      }`}
                      style={{ 
                        backgroundColor: selectedStatus === 'severe' ? COLORS.danger : 'transparent',
                        borderColor: selectedStatus === 'severe' ? 'transparent' : COLORS.light,
                        color: selectedStatus === 'severe' ? 'white' : COLORS.dark
                      }}
                    >
                      Severe ({summary.severityCounts.severe})
                    </button>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Severity Stats */}
        {delinquents.length > 0 && (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            {[
              { label: 'Current', count: summary.severityCounts.current, color: COLORS.success, icon: CheckCircle },
              { label: 'Mild', count: summary.severityCounts.mild, color: '#f59e0b', icon: Clock },
              { label: 'Moderate', count: summary.severityCounts.moderate, color: COLORS.warning, icon: AlertTriangle },
              { label: 'Severe', count: summary.severityCounts.severe, color: COLORS.danger, icon: AlertCircle }
            ].map((item) => {
              const Icon = item.icon;
              const percentage = summary.totalDelinquents > 0 
                ? ((item.count / summary.totalDelinquents) * 100).toFixed(1) 
                : 0;
              
              return (
                <div key={item.label} className="bg-white rounded-lg p-4 border" style={{ borderColor: COLORS.light }}>
                  <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center gap-2">
                      <div className="p-1.5 rounded-md" style={{ backgroundColor: `${item.color}15` }}>
                        <Icon className="w-4 h-4" style={{ color: item.color }} />
                      </div>
                      <span className="text-sm font-medium" style={{ color: COLORS.dark }}>{item.label}</span>
                    </div>
                    <span className="text-lg font-bold" style={{ color: item.color }}>{item.count}</span>
                  </div>
                  <div className="w-full bg-gray-100 rounded-full h-1.5">
                    <div
                      className="h-1.5 rounded-full transition-all duration-500"
                      style={{ 
                        width: `${percentage}%`,
                        backgroundColor: item.color
                      }}
                    ></div>
                  </div>
                  <p className="text-xs mt-2" style={{ color: COLORS.secondary }}>{percentage}% of total</p>
                </div>
              );
            })}
          </div>
        )}

        {/* Delinquent Table */}
        <div className="bg-white rounded-xl border shadow-sm overflow-hidden" style={{ borderColor: COLORS.light }}>
          {/* Table Header with Results Count */}
          <div className="px-6 py-4 border-b bg-gray-50/50 flex items-center justify-between" style={{ borderColor: COLORS.light }}>
            <div className="flex items-center gap-3">
              <FileText className="w-4 h-4" style={{ color: COLORS.secondary }} />
              <span className="text-sm font-medium" style={{ color: COLORS.dark }}>
                Delinquent Records
              </span>
              <span className="px-2 py-0.5 text-xs font-medium rounded-full" 
                    style={{ backgroundColor: `${COLORS.primary}10`, color: COLORS.primary }}>
                {filteredDelinquents.length} {filteredDelinquents.length === 1 ? 'result' : 'results'}
              </span>
            </div>
            {searchTerm && (
              <button 
                onClick={() => setSearchTerm('')}
                className="text-xs hover:text-blue-600 transition-colors"
                style={{ color: COLORS.secondary }}
              >
                Clear search
              </button>
            )}
          </div>

          <div className="overflow-x-auto">
            <table className="min-w-full divide-y" style={{ borderColor: COLORS.light }}>
              <thead>
                <tr>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider bg-gray-50/50"
                      style={{ color: COLORS.secondary }}>
                    Renter Details
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider bg-gray-50/50"
                      style={{ color: COLORS.secondary }}>
                    Stall Information
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider bg-gray-50/50"
                      style={{ color: COLORS.secondary }}>
                    Payment Status
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider bg-gray-50/50"
                      style={{ color: COLORS.secondary }}>
                    {/* Actions column removed */}
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y" style={{ borderColor: COLORS.light }}>
                {currentDelinquents.length === 0 ? (
                  <tr>
                    <td colSpan="3" className="px-6 py-12 text-center">
                      <div className="flex flex-col items-center justify-center">
                        <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                          <CheckCircle className="w-8 h-8" style={{ color: COLORS.secondary }} />
                        </div>
                        <p className="text-base font-medium mb-1" style={{ color: COLORS.dark }}>No delinquent records found</p>
                        <p className="text-sm" style={{ color: COLORS.secondary }}>
                          {searchTerm || selectedStatus !== 'all' 
                            ? 'Try adjusting your search filters' 
                            : 'All payments are up to date'}
                        </p>
                      </div>
                    </td>
                  </tr>
                ) : (
                  currentDelinquents.map((delinquent, index) => {
                    const daysOverdue = calculateDaysOverdue(delinquent.due_date);
                    const statusColor = getOverdueStatusColor(daysOverdue);
                    const statusText = getOverdueStatusText(daysOverdue);
                    
                    return (
                      <tr key={delinquent.id || index} className="hover:bg-gray-50/50 transition-colors">
                        <td className="px-6 py-4">
                          <div className="flex items-center">
                            <div className="flex-shrink-0 h-9 w-9 rounded-full flex items-center justify-center"
                                 style={{ backgroundColor: `${COLORS.primary}10` }}>
                              <User className="w-4 h-4" style={{ color: COLORS.primary }} />
                            </div>
                            <div className="ml-3">
                              <div className="text-sm font-medium" style={{ color: COLORS.dark }}>
                                {delinquent.full_name || 'N/A'}
                              </div>
                              <div className="text-xs mt-0.5 font-mono" style={{ color: COLORS.secondary }}>
                                {delinquent.renter_code || 'N/A'}
                              </div>
                              {delinquent.mobile && (
                                <div className="text-xs mt-1 flex items-center gap-1" style={{ color: COLORS.secondary }}>
                                  <Phone className="w-3 h-3" />
                                  {delinquent.mobile}
                                </div>
                              )}
                            </div>
                          </div>
                        </td>
                        
                        <td className="px-6 py-4">
                          <div className="text-sm font-medium" style={{ color: COLORS.dark }}>
                            {delinquent.business_name || 'N/A'}
                          </div>
                          <div className="text-xs mt-1 flex flex-wrap items-center gap-1">
                            <span className="font-mono" style={{ color: COLORS.secondary }}>
                              {delinquent.stall_rights_no || 'N/A'}
                            </span>
                            <span className="w-1 h-1 rounded-full bg-gray-300"></span>
                            <span style={{ color: COLORS.secondary }}>
                              {delinquent.stall_name || 'N/A'}
                            </span>
                          </div>
                          <div className="text-xs mt-2">
                            <span className="px-2 py-0.5 rounded-md" 
                                  style={{ backgroundColor: `${COLORS.info}10`, color: COLORS.info }}>
                              Class {delinquent.stall_class || 'N/A'}
                            </span>
                          </div>
                        </td>
                        
                        <td className="px-6 py-4">
                          <div className="space-y-2">
                            <div className="flex items-center justify-between">
                              <span className="text-xs" style={{ color: COLORS.secondary }}>Amount Due:</span>
                              <span className="text-sm font-semibold" style={{ color: COLORS.danger }}>
                                {formatCurrency(delinquent.overdue_amount || 0)}
                              </span>
                            </div>
                            <div className="flex items-center justify-between">
                              <span className="text-xs" style={{ color: COLORS.secondary }}>Due Date:</span>
                              <span className="text-xs" style={{ color: COLORS.dark }}>
                                {formatDate(delinquent.due_date)}
                              </span>
                            </div>
                            <div className="flex items-center justify-between">
                              <span className="text-xs" style={{ color: COLORS.secondary }}>Overdue:</span>
                              <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${statusColor}`}>
                                {daysOverdue} days • {statusText}
                              </span>
                            </div>
                            {delinquent.last_payment_date && (
                              <div className="flex items-center justify-between pt-1 border-t" style={{ borderColor: COLORS.light }}>
                                <span className="text-xs" style={{ color: COLORS.secondary }}>Last Payment:</span>
                                <span className="text-xs" style={{ color: COLORS.success }}>
                                  {formatDate(delinquent.last_payment_date)}
                                </span>
                              </div>
                            )}
                          </div>
                        </td>
                        
                        {/* Actions column completely removed */}
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
          
          {/* Pagination */}
          {totalPages > 1 && (
            <div className="px-6 py-4 border-t bg-gray-50/50 flex items-center justify-between" style={{ borderColor: COLORS.light }}>
              <div className="text-xs" style={{ color: COLORS.secondary }}>
                Showing {indexOfFirstItem + 1} to {Math.min(indexOfLastItem, filteredDelinquents.length)} of {filteredDelinquents.length} results
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
                  disabled={currentPage === 1}
                  className="p-2 border rounded-lg bg-white disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition-colors"
                  style={{ borderColor: COLORS.light }}
                >
                  <ChevronLeft className="w-4 h-4" style={{ color: COLORS.dark }} />
                </button>
                
                <div className="flex items-center gap-1">
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
                            <span className="px-3 py-2 text-xs" style={{ color: COLORS.secondary }}>...</span>
                            <button
                              onClick={() => setCurrentPage(page)}
                              className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all border ${
                                currentPage === page 
                                  ? 'text-white shadow-sm' 
                                  : 'bg-white hover:bg-gray-50'
                              }`}
                              style={{ 
                                backgroundColor: currentPage === page ? COLORS.primary : 'transparent',
                                borderColor: currentPage === page ? 'transparent' : COLORS.light,
                                color: currentPage === page ? 'white' : COLORS.dark
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
                          className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all border ${
                            currentPage === page 
                              ? 'text-white shadow-sm' 
                              : 'bg-white hover:bg-gray-50'
                          }`}
                          style={{ 
                            backgroundColor: currentPage === page ? COLORS.primary : 'transparent',
                            borderColor: currentPage === page ? 'transparent' : COLORS.light,
                            color: currentPage === page ? 'white' : COLORS.dark
                          }}
                        >
                          {page}
                        </button>
                      );
                    })}
                </div>
                
                <button
                  onClick={() => setCurrentPage(prev => Math.min(prev + 1, totalPages))}
                  disabled={currentPage === totalPages}
                  className="p-2 border rounded-lg bg-white disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition-colors"
                  style={{ borderColor: COLORS.light }}
                >
                  <ChevronRight className="w-4 h-4" style={{ color: COLORS.dark }} />
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="mt-8 pt-6 border-t text-center" style={{ borderColor: COLORS.light }}>
          <p className="text-xs" style={{ color: COLORS.secondary }}>
            Market Delinquent Management System • Last updated: {new Date().toLocaleDateString('en-PH', {
              year: 'numeric',
              month: 'long',
              day: 'numeric',
              hour: '2-digit',
              minute: '2-digit'
            })}
          </p>
        </div>
      </div>

      {/* Delinquent Detail Modal */}
      {selectedDelinquent && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50 backdrop-blur-sm">
          <div className="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-xl">
            <div className="sticky top-0 bg-white border-b rounded-t-xl px-6 py-4 flex items-center justify-between" style={{ borderColor: COLORS.light }}>
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.primary}10` }}>
                  <Store className="w-5 h-5" style={{ color: COLORS.primary }} />
                </div>
                <div>
                  <h2 className="text-lg font-bold" style={{ color: COLORS.dark }}>Delinquent Details</h2>
                  <p className="text-xs" style={{ color: COLORS.secondary }}>{selectedDelinquent.stall_rights_no}</p>
                </div>
              </div>
              <button 
                onClick={() => setSelectedDelinquent(null)}
                className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
              >
                <XCircle className="w-5 h-5" style={{ color: COLORS.secondary }} />
              </button>
            </div>
            
            <div className="p-6 space-y-6">
              {/* Status Badge */}
              {(() => {
                const daysOverdue = calculateDaysOverdue(selectedDelinquent.due_date);
                const statusColor = getOverdueStatusColor(daysOverdue);
                const statusText = getOverdueStatusText(daysOverdue);
                
                return (
                  <div className={`${statusColor} px-4 py-3 rounded-lg flex items-center gap-3`}>
                    <AlertCircle className="w-5 h-5" />
                    <div>
                      <p className="text-sm font-medium">{statusText} Delinquency • {daysOverdue} days overdue</p>
                      <p className="text-xs opacity-90">This account has been overdue for {daysOverdue} days</p>
                    </div>
                  </div>
                );
              })()}
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Renter Info */}
                <div className="space-y-3">
                  <h3 className="text-sm font-semibold flex items-center gap-2 pb-2 border-b" 
                      style={{ color: COLORS.dark, borderColor: COLORS.light }}>
                    <User className="w-4 h-4" style={{ color: COLORS.primary }} />
                    Renter Information
                  </h3>
                  <div className="space-y-3">
                    <div className="flex items-start gap-2">
                      <div className="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                           style={{ backgroundColor: `${COLORS.primary}10` }}>
                        <User className="w-4 h-4" style={{ color: COLORS.primary }} />
                      </div>
                      <div>
                        <p className="text-sm font-medium" style={{ color: COLORS.dark }}>{selectedDelinquent.full_name}</p>
                        <p className="text-xs mt-0.5 font-mono" style={{ color: COLORS.secondary }}>{selectedDelinquent.renter_code}</p>
                      </div>
                    </div>
                    
                    <div className="grid grid-cols-2 gap-3 pl-2">
                      <div>
                        <p className="text-xs" style={{ color: COLORS.secondary }}>Mobile</p>
                        <p className="text-sm font-medium mt-1" style={{ color: COLORS.dark }}>
                          {selectedDelinquent.mobile || 'N/A'}
                        </p>
                      </div>
                      <div>
                        <p className="text-xs" style={{ color: COLORS.secondary }}>Email</p>
                        <p className="text-sm font-medium mt-1 truncate" style={{ color: COLORS.dark }}>
                          {selectedDelinquent.email || 'N/A'}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
                
                {/* Stall Info */}
                <div className="space-y-3">
                  <h3 className="text-sm font-semibold flex items-center gap-2 pb-2 border-b" 
                      style={{ color: COLORS.dark, borderColor: COLORS.light }}>
                    <Store className="w-4 h-4" style={{ color: COLORS.primary }} />
                    Stall Information
                  </h3>
                  <div className="space-y-3">
                    <div>
                      <p className="text-xs" style={{ color: COLORS.secondary }}>Business Name</p>
                      <p className="text-sm font-medium mt-1" style={{ color: COLORS.dark }}>
                        {selectedDelinquent.business_name || 'N/A'}
                      </p>
                    </div>
                    
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <p className="text-xs" style={{ color: COLORS.secondary }}>Stall Name</p>
                        <p className="text-sm mt-1" style={{ color: COLORS.dark }}>
                          {selectedDelinquent.stall_name || 'N/A'}
                        </p>
                      </div>
                      <div>
                        <p className="text-xs" style={{ color: COLORS.secondary }}>Class</p>
                        <p className="text-sm mt-1">
                          <span className="px-2 py-0.5 rounded-md text-xs"
                                style={{ backgroundColor: `${COLORS.info}10`, color: COLORS.info }}>
                            Class {selectedDelinquent.stall_class || 'N/A'}
                          </span>
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              {/* Payment Info */}
              <div className="space-y-3">
                <h3 className="text-sm font-semibold flex items-center gap-2 pb-2 border-b" 
                    style={{ color: COLORS.dark, borderColor: COLORS.light }}>
                  <DollarSign className="w-4 h-4" style={{ color: COLORS.primary }} />
                  Payment Information
                </h3>
                
                <div className="bg-gray-50 rounded-lg p-4">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-3">
                      <div>
                        <p className="text-xs" style={{ color: COLORS.secondary }}>Monthly Rent</p>
                        <p className="text-lg font-bold mt-1" style={{ color: COLORS.dark }}>
                          {formatCurrency(selectedDelinquent.monthly_rent || 0)}
                        </p>
                      </div>
                      <div className="flex items-center justify-between p-2 bg-white rounded-lg border" 
                           style={{ borderColor: COLORS.light }}>
                        <span className="text-xs" style={{ color: COLORS.secondary }}>Overdue Amount</span>
                        <span className="text-base font-bold" style={{ color: COLORS.danger }}>
                          {formatCurrency(selectedDelinquent.overdue_amount || 0)}
                        </span>
                      </div>
                    </div>
                    
                    <div className="space-y-3">
                      <div>
                        <p className="text-xs" style={{ color: COLORS.secondary }}>Due Date</p>
                        <div className="flex items-center gap-2 mt-1">
                          <Calendar className="w-4 h-4" style={{ color: COLORS.warning }} />
                          <p className="text-sm font-medium" style={{ color: COLORS.dark }}>
                            {formatDate(selectedDelinquent.due_date)}
                          </p>
                        </div>
                      </div>
                      
                      {selectedDelinquent.last_payment_date && (
                        <div>
                          <p className="text-xs" style={{ color: COLORS.secondary }}>Last Payment</p>
                          <div className="flex items-center gap-2 mt-1">
                            <CheckCircle className="w-4 h-4" style={{ color: COLORS.success }} />
                            <p className="text-sm font-medium" style={{ color: COLORS.success }}>
                              {formatDate(selectedDelinquent.last_payment_date)}
                            </p>
                          </div>
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            {/* Modal Footer */}
            <div className="sticky bottom-0 bg-gray-50 border-t rounded-b-xl px-6 py-4 flex justify-end gap-3" style={{ borderColor: COLORS.light }}>
              <button
                onClick={() => setSelectedDelinquent(null)}
                className="px-4 py-2 border rounded-lg hover:bg-white transition-colors text-sm font-medium"
                style={{ borderColor: COLORS.light, color: COLORS.dark }}
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// Phone icon component
const Phone = (props) => (
  <svg {...props} xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
  </svg>
);