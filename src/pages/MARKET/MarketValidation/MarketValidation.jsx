import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
  Search, Filter, Eye, Download, RefreshCw, AlertCircle, CheckCircle, 
  Clock, Building, FileText, User, Calendar, Users, FileCheck, AlertTriangle,
  Archive, ChevronRight, BarChart3, Database, Shield, Clock as ClockIcon,
  FileWarning, Eye as EyeIcon, TrendingUp, DollarSign, Store, CreditCard,
  Home, MapPin, Hash, ChevronLeft, ChevronDown, MoreVertical, Send,
  Phone, Mail, CheckSquare, XCircle, CalendarDays, Timer, Percent,
  Target, TrendingUp as TrendingUpIcon, Users as UsersIcon
} from "lucide-react";

// Custom colors matching the dashboard
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

const MarketValidation = () => {
  const [applications, setApplications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage, setItemsPerPage] = useState(10);
  const [filterStatus, setFilterStatus] = useState('all');
  const [showFilters, setShowFilters] = useState(false);

  // Dynamic API base URL
  const getApiBaseUrl = () => {
    const isLocalhost = window.location.hostname === 'localhost' || 
                        window.location.hostname === '127.0.0.1';
    
    if (isLocalhost) {
      return 'http://localhost/revenue2/backend';
    } else {
      return 'https://revenuetreasury.goserveph.com/backend';
    }
  };

  // Fetch market applications data (excluding approved)
  const fetchApplications = async () => {
    try {
      setLoading(true);
      setError('');
      
      const API_BASE = getApiBaseUrl();
      const API_PATH = "/Market/MarketValidation/get_applications.php";
      
      const response = await fetch(`${API_BASE}${API_PATH}`, {
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.status === 'success') {
        // Filter out approved applications on frontend as well
        const filteredApps = (data.data || []).filter(app => 
          app.application_status && app.application_status.toLowerCase() !== 'approved'
        );
        
        setApplications(filteredApps);
      } else {
        throw new Error(data.message || 'Failed to fetch market applications');
      }
    } catch (err) {
      setError(err.message || 'Failed to load market applications. Please try again.');
      setApplications([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchApplications();
  }, []);

  // Filter applications based on search and status filter
  const filteredApplications = applications.filter(app => {
    const searchLower = searchTerm.toLowerCase();
    const matchesSearch = 
      (app.stall_name || '').toLowerCase().includes(searchLower) ||
      (app.first_name || '').toLowerCase().includes(searchLower) ||
      (app.last_name || '').toLowerCase().includes(searchLower) ||
      (app.stall_rights_no || '').toLowerCase().includes(searchLower) ||
      (app.business_name || '').toLowerCase().includes(searchLower) ||
      (app.renter_code || '').toLowerCase().includes(searchLower);
    
    // Show all applications except approved when filter is 'all'
    const matchesStatus = filterStatus === 'all' || 
                         (app.application_status && app.application_status.toLowerCase() === filterStatus.toLowerCase());
    
    return matchesSearch && matchesStatus;
  });

  // Calculate statistics - EXCLUDING approved applications
  const stats = {
    pending: applications.filter(a => a.application_status && a.application_status.toLowerCase() === 'pending').length,
    interviewed: applications.filter(a => a.application_status && a.application_status.toLowerCase() === 'interviewed').length,
    paying: applications.filter(a => a.application_status && a.application_status.toLowerCase() === 'paying').length,
    paid: applications.filter(a => a.application_status && a.application_status.toLowerCase() === 'paid').length,
    need_correction: applications.filter(a => a.application_status && a.application_status.toLowerCase() === 'need_correction').length,
    resubmitted: applications.filter(a => a.application_status && a.application_status.toLowerCase() === 'resubmitted').length,
    rejected: applications.filter(a => a.application_status && a.application_status.toLowerCase() === 'rejected').length,
    total: applications.length // This already excludes approved
  };

  // Pagination calculations
  const totalPages = Math.ceil(filteredApplications.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const paginatedApplications = filteredApplications.slice(startIndex, endIndex);

  // Get status color
  const getStatusColor = (status) => {
    if (!status) return { bg: 'bg-gray-100', text: 'text-gray-800', border: 'border-gray-200', icon: FileText };
    
    const statusLower = status.toLowerCase();
    switch (statusLower) {
      case 'approved':
        return { bg: 'bg-green-100', text: 'text-green-800', border: 'border-green-200', icon: CheckCircle };
      case 'interviewed':
        return { bg: 'bg-blue-100', text: 'text-blue-800', border: 'border-blue-200', icon: Users };
      case 'paying':
        return { bg: 'bg-purple-100', text: 'text-purple-800', border: 'border-purple-200', icon: CreditCard };
      case 'paid':
        return { bg: 'bg-indigo-100', text: 'text-indigo-800', border: 'border-indigo-200', icon: DollarSign };
      case 'pending':
        return { bg: 'bg-yellow-100', text: 'text-yellow-800', border: 'border-yellow-200', icon: Clock };
      case 'need_correction':
        return { bg: 'bg-red-100', text: 'text-red-800', border: 'border-red-200', icon: AlertTriangle };
      case 'resubmitted':
        return { bg: 'bg-orange-100', text: 'text-orange-800', border: 'border-orange-200', icon: RefreshCw };
      case 'rejected':
        return { bg: 'bg-gray-100', text: 'text-gray-800', border: 'border-gray-200', icon: XCircle };
      default:
        return { bg: 'bg-gray-100', text: 'text-gray-800', border: 'border-gray-200', icon: FileText };
    }
  };

  // Get status text
  const getStatusText = (status) => {
    if (!status) return 'Unknown';
    
    const statusLower = status.toLowerCase();
    const statusMap = {
      'pending': 'Pending Interview',
      'interviewed': 'Interview Completed',
      'paying': 'Payment Required',
      'paid': 'Payment Completed',
      'need_correction': 'Needs Correction',
      'resubmitted': 'Resubmitted',
      'approved': 'Approved',
      'rejected': 'Rejected'
    };
    return statusMap[statusLower] || status;
  };

  // Format currency
  const formatCurrency = (amount) => {
    const num = parseFloat(amount) || 0;
    if (num >= 1000000) return `₱${(num / 1000000).toFixed(2)}M`;
    if (num >= 1000) return `₱${(num / 1000).toFixed(2)}K`;
    return `₱${num.toFixed(2)}`;
  };

  // Format date
  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
      return new Date(dateString).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    } catch (e) {
      return 'Invalid Date';
    }
  };

  // Handle page change
  const handlePageChange = (page) => {
    setCurrentPage(page);
  };

  if (loading) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
        <div className="flex flex-col justify-center items-center h-screen bg-white">
          <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-gray-800 mb-4"></div>
          <p className="text-gray-600">Loading Market Applications...</p>
          <p className="text-sm text-gray-400 mt-2">Fetching validation data</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
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
              onClick={fetchApplications}
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
      <div className="border-b" style={{ backgroundColor: 'white', borderColor: '#e5e7eb' }}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
              <h1 className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>
                Market Applications Validation
              </h1>
              <div className="flex items-center gap-3 text-sm" style={{ color: COLORS.secondary }}>
                <div className="flex items-center gap-1">
                  <Calendar className="w-4 h-4" />
                  <span>Active Applications • {new Date().toLocaleDateString('en-PH')}</span>
                </div>
              </div>
            </div>
            
            <div className="flex flex-wrap gap-3 items-center">
              <button
                onClick={() => setShowFilters(!showFilters)}
                className="flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all"
                style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
              >
                <Filter className="w-4 h-4" />
                {showFilters ? 'Hide Filters' : 'Show Filters'}
              </button>
              <button
                onClick={fetchApplications}
                disabled={loading}
                className="flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all disabled:opacity-50"
                style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
              >
                <RefreshCw className="w-4 h-4" />
                Refresh
              </button>
              <button className="flex items-center gap-2 px-4 py-2 rounded-lg transition-all"
                style={{ backgroundColor: COLORS.primary, color: 'white' }}>
                <Database className="w-4 h-4" />
                Export Report
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Stats Cards - Single Row */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-4">
          {/* Pending */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.warning}15` }}>
                <Clock className="w-6 h-6" style={{ color: COLORS.warning }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full bg-yellow-100 text-yellow-800">
                {stats.pending}
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Pending
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.pending}</p>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Awaiting Interview
            </div>
          </div>
          
          {/* Interviewed */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.info}15` }}>
                <Users className="w-6 h-6" style={{ color: COLORS.info }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full bg-blue-100 text-blue-800">
                {stats.interviewed}
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Interviewed
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.interviewed}</p>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Interview Done
            </div>
          </div>
          
          {/* Paying */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: '#6b46c115' }}>
                <CreditCard className="w-6 h-6" style={{ color: '#6b46c1' }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full bg-purple-100 text-purple-800">
                {stats.paying}
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Paying
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.paying}</p>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Payment Required
            </div>
          </div>
          
          {/* Paid */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: '#4f46e515' }}>
                <DollarSign className="w-6 h-6" style={{ color: '#4f46e5' }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full bg-indigo-100 text-indigo-800">
                {stats.paid}
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Paid
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.paid}</p>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Payment Completed
            </div>
          </div>
          
          {/* Need Correction */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.danger}15` }}>
                <AlertTriangle className="w-6 h-6" style={{ color: COLORS.danger }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full bg-red-100 text-red-800">
                {stats.need_correction}
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Need Correction
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.need_correction}</p>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Needs Correction
            </div>
          </div>
          
          {/* Resubmitted */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.warning}15` }}>
                <RefreshCw className="w-6 h-6" style={{ color: COLORS.warning }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full bg-orange-100 text-orange-800">
                {stats.resubmitted}
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Resubmitted
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.resubmitted}</p>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Resubmitted
            </div>
          </div>
          
          {/* Rejected */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.secondary}15` }}>
                <XCircle className="w-6 h-6" style={{ color: COLORS.secondary }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full bg-gray-100 text-gray-800">
                {stats.rejected}
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Rejected
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.rejected}</p>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Rejected
            </div>
          </div>
        </div>

        {/* Filters Section */}
        <div className="bg-white border rounded-xl p-6" style={{ borderColor: COLORS.secondary }}>
          <div className="flex justify-between items-center mb-4">
            <h3 className="font-semibold" style={{ color: COLORS.dark }}>Filter Applications</h3>
            <div className="flex gap-3">
              <button
                onClick={() => setShowFilters(!showFilters)}
                className="flex items-center gap-2 text-sm"
                style={{ color: COLORS.primary }}
              >
                <Filter className="w-4 h-4" />
                {showFilters ? "Hide Filters" : "Show Filters"}
              </button>
              
              <select
                value={filterStatus}
                onChange={(e) => {
                  setFilterStatus(e.target.value);
                  setCurrentPage(1);
                }}
                className="px-4 py-2 border rounded-lg bg-white"
                style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
              >
                <option value="all">All Statuses (Excl. Approved)</option>
                <option value="pending">Pending Interview</option>
                <option value="interviewed">Interview Completed</option>
                <option value="paying">Payment Required</option>
                <option value="paid">Payment Completed</option>
                <option value="need_correction">Needs Correction</option>
                <option value="resubmitted">Resubmitted</option>
                <option value="rejected">Rejected</option>
              </select>
            </div>
          </div>
          
          {showFilters && (
            <div className="mt-4 pt-4 border-t" style={{ borderColor: COLORS.secondary }}>
              <div className="flex flex-col md:flex-row gap-4">
                {/* Search */}
                <div className="flex-1">
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                           style={{ color: COLORS.secondary }} />
                    <input
                      type="text"
                      placeholder="Search applications by stall name, applicant name, business name..."
                      value={searchTerm}
                      onChange={(e) => {
                        setSearchTerm(e.target.value);
                        setCurrentPage(1);
                      }}
                      className="block w-full pl-10 pr-3 py-2.5 border rounded-lg bg-white"
                      style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                    />
                  </div>
                </div>

                {/* Items per page */}
                <div>
                  <div className="relative">
                    <FileText className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                             style={{ color: COLORS.secondary }} />
                    <select
                      value={itemsPerPage}
                      onChange={(e) => {
                        setItemsPerPage(parseInt(e.target.value));
                        setCurrentPage(1);
                      }}
                      className="px-10 py-2.5 border rounded-lg bg-white appearance-none"
                      style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                    >
                      <option value="10">10 per page</option>
                      <option value="25">25 per page</option>
                      <option value="50">50 per page</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          )}
          
          {/* Search Stats */}
          <div className="mt-4 flex items-center justify-between text-sm">
            <div style={{ color: COLORS.secondary }}>
              {searchTerm ? (
                <span>
                  Searching for: <span className="font-medium" style={{ color: COLORS.dark }}>"{searchTerm}"</span>
                </span>
              ) : (
                <span>Showing all pending applications</span>
              )}
            </div>
            <div className="font-medium" style={{ color: COLORS.dark }}>
              {filteredApplications.length} of {applications.length} applications
            </div>
          </div>
        </div>

        {/* Applications Table */}
        <div className="bg-white border rounded-xl shadow-sm" style={{ borderColor: COLORS.secondary }}>
          <div className="p-6 border-b" style={{ borderColor: COLORS.secondary }}>
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
              <div>
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <Store className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Market Applications ({filteredApplications.length})
                </h3>
                <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                  Excluding approved applications
                </p>
              </div>
              
              <div className="inline-flex items-center gap-2 px-3 py-1.5 border rounded-lg text-sm"
                   style={{ borderColor: COLORS.secondary, color: COLORS.secondary }}>
                <Archive className="w-4 h-4" />
                <span>Approved applications excluded</span>
              </div>
            </div>
          </div>
          
          {paginatedApplications.length === 0 ? (
            <div className="text-center py-12" style={{ color: COLORS.secondary }}>
              <FileText className="w-12 h-12 mx-auto mb-2" />
              <p className="text-sm font-medium" style={{ color: COLORS.dark }}>
                {searchTerm || filterStatus !== 'all' 
                  ? "No matching applications found" 
                  : "All applications have been processed"}
              </p>
              <p className="text-sm mt-1 max-w-xs mx-auto">
                {searchTerm 
                  ? "Try adjusting your search terms or clear filters"
                  : "No pending applications at this time"}
              </p>
              {(searchTerm || filterStatus !== "all") && (
                <button
                  onClick={() => {
                    setSearchTerm("");
                    setFilterStatus("all");
                  }}
                  className="mt-4 text-sm font-medium px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all"
                  style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                >
                  Clear filters
                </button>
              )}
            </div>
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead>
                    <tr style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Application Details
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Applicant Info
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Business & Stall
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Financial Details
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Status & Dates
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {paginatedApplications.map((app) => {
                      const statusInfo = getStatusColor(app.application_status);
                      const StatusIcon = statusInfo.icon;
                      
                      return (
                        <tr key={app.id} className="hover:bg-gray-50 transition-colors" 
                            style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}>
                          <td className="p-4">
                            <div>
                              <div className="font-mono text-blue-600 font-bold">{app.stall_rights_no}</div>
                              <div className="text-sm" style={{ color: COLORS.secondary }}>Application ID</div>
                              <div className="text-xs mt-1" style={{ color: COLORS.secondary }}>
                                Applied: {formatDate(app.created_at)}
                              </div>
                            </div>
                          </td>
                          
                          <td className="p-4">
                            <div>
                              <div className="font-medium" style={{ color: COLORS.dark }}>
                                {app.first_name} {app.last_name}
                              </div>
                              <div className="text-sm mt-1" style={{ color: COLORS.secondary }}>{app.email}</div>
                              <div className="text-sm" style={{ color: COLORS.dark }}>{app.mobile}</div>
                            </div>
                          </td>
                          
                          <td className="p-4">
                            <div>
                              <div className="font-medium" style={{ color: COLORS.dark }}>{app.business_name}</div>
                              <div className="text-sm" style={{ color: COLORS.secondary }}>{app.business_type}</div>
                              <div className="mt-2">
                                <div style={{ color: COLORS.dark }}>
                                  <span className="font-medium">Stall:</span> {app.stall_name}
                                </div>
                                <div className="text-xs" style={{ color: COLORS.secondary }}>
                                  Class: {app.stall_class}
                                </div>
                              </div>
                            </div>
                          </td>
                          
                          <td className="p-4">
                            <div className="space-y-1">
                              <div className="flex justify-between text-sm">
                                <span style={{ color: COLORS.secondary }}>Monthly Rent:</span>
                                <span className="font-semibold" style={{ color: COLORS.dark }}>
                                  {formatCurrency(app.monthly_rent)}
                                </span>
                              </div>
                              <div className="flex justify-between text-sm">
                                <span style={{ color: COLORS.secondary }}>Total Due:</span>
                                <span className="font-bold" style={{ color: COLORS.primary }}>
                                  {formatCurrency(app.total_amount_due)}
                                </span>
                              </div>
                            </div>
                          </td>
                          
                          <td className="p-4">
                            <div className="flex items-center gap-3">
                              <div className={`p-2 rounded-lg ${statusInfo.bg}`}>
                                <StatusIcon className={`w-4 h-4 ${statusInfo.text}`} />
                              </div>
                              <div>
                                <span className={`text-xs font-medium px-3 py-1.5 rounded-full ${statusInfo.bg} ${statusInfo.text} border ${statusInfo.border}`}>
                                  {getStatusText(app.application_status)}
                                </span>
                                <div className="mt-2 text-xs space-y-1">
                                  {app.interview_date && (
                                    <div style={{ color: COLORS.secondary }}>
                                      Interview: {formatDate(app.interview_date)}
                                    </div>
                                  )}
                                  {app.payment_date && (
                                    <div style={{ color: COLORS.secondary }}>
                                      Payment: {formatDate(app.payment_date)}
                                    </div>
                                  )}
                                </div>
                              </div>
                            </div>
                          </td>
                          
                          <td className="p-4">
                            <Link
                              to={`/market/marketvalidationinfo/${app.id}`}
                              className="px-4 py-2 rounded-lg flex items-center gap-2 transition-all"
                              style={{ backgroundColor: COLORS.primary, color: 'white' }}
                            >
                              <Eye className="w-4 h-4" />
                              View Details
                            </Link>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
              
              {/* Table Footer */}
              <div className="p-4 border-t" style={{ borderColor: COLORS.secondary, backgroundColor: `${COLORS.background}` }}>
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div className="text-sm" style={{ color: COLORS.secondary }}>
                    Showing <span className="font-semibold" style={{ color: COLORS.dark }}>{startIndex + 1}</span> to{" "}
                    <span className="font-semibold" style={{ color: COLORS.dark }}>{Math.min(endIndex, filteredApplications.length)}</span> of{" "}
                    <span className="font-semibold" style={{ color: COLORS.dark }}>{filteredApplications.length}</span> applications
                  </div>
                  
                  {/* Pagination */}
                  {totalPages > 1 && (
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => handlePageChange(currentPage - 1)}
                        disabled={currentPage === 1}
                        className="p-2 border rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition-colors"
                        style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                      >
                        <ChevronLeft className="w-4 h-4" />
                      </button>
                      
                      <div className="flex items-center gap-1">
                        {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                          let pageNumber;
                          if (totalPages <= 5) {
                            pageNumber = i + 1;
                          } else if (currentPage <= 3) {
                            pageNumber = i + 1;
                          } else if (currentPage >= totalPages - 2) {
                            pageNumber = totalPages - 4 + i;
                          } else {
                            pageNumber = currentPage - 2 + i;
                          }

                          if (pageNumber < 1 || pageNumber > totalPages) return null;

                          return (
                            <button
                              key={pageNumber}
                              onClick={() => handlePageChange(pageNumber)}
                              className={`px-3 py-1 text-sm rounded ${
                                currentPage === pageNumber
                                  ? 'text-white'
                                  : 'border hover:bg-gray-50'
                              }`}
                              style={{ 
                                backgroundColor: currentPage === pageNumber ? COLORS.primary : 'transparent',
                                color: currentPage === pageNumber ? 'white' : COLORS.dark,
                                borderColor: currentPage === pageNumber ? COLORS.primary : COLORS.secondary
                              }}
                            >
                              {pageNumber}
                            </button>
                          );
                        })}
                      </div>
                      
                      <button
                        onClick={() => handlePageChange(currentPage + 1)}
                        disabled={currentPage === totalPages}
                        className="p-2 border rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition-colors"
                        style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                      >
                        <ChevronRight className="w-4 h-4" />
                      </button>
                    </div>
                  )}
                </div>
              </div>
            </>
          )}
        </div>

        {/* Footer Summary */}
        <div className="text-center text-sm pt-6 border-t" style={{ color: COLORS.secondary, borderColor: COLORS.secondary }}>
          <p>Market Application Validation Portal • {new Date().toLocaleDateString('en-PH')}</p>
          <p className="text-xs mt-1">
            Local Government Unit - Market Stall Management System
          </p>
        </div>
      </div>
    </div>
  );
};

export default MarketValidation;