import React, { useState, useEffect, useMemo } from 'react';
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
  Grid, Landmark, Target, Eye
} from "lucide-react";

// Custom colors matching RPTDelinquent and BusinessDelinquent
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

export default function MarketDelinquent() {
  const [delinquents, setDelinquents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [monthFilter, setMonthFilter] = useState('all');
  const [yearFilter, setYearFilter] = useState(new Date().getFullYear().toString());
  const [showFilters, setShowFilters] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [selectedDelinquent, setSelectedDelinquent] = useState(null);
  
  // Email states
  const [sendingEmail, setSendingEmail] = useState(false);
  const [emailStatus, setEmailStatus] = useState(null);
  const [selectedDelinquents, setSelectedDelinquents] = useState([]);
  const [showEmailModal, setShowEmailModal] = useState(false);
  const [emailTemplate, setEmailTemplate] = useState('standard');
  const [customMessage, setCustomMessage] = useState('');
  const [selectAll, setSelectAll] = useState(false);
  
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
        // Reset to first page when new data loads
        setCurrentPage(1);
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

  // Generate filtered delinquents using useMemo
  const filteredDelinquents = useMemo(() => {
    return delinquents.filter(delinquent => {
      // Search filter
      const searchLower = searchTerm.toLowerCase();
      const matchesSearch = 
        !searchTerm ||
        (delinquent.business_name && delinquent.business_name.toLowerCase().includes(searchLower)) ||
        (delinquent.stall_rights_no && delinquent.stall_rights_no.toLowerCase().includes(searchLower)) ||
        (delinquent.renter_code && delinquent.renter_code.toLowerCase().includes(searchLower)) ||
        (delinquent.full_name && delinquent.full_name.toLowerCase().includes(searchLower));
      
      // Status filter
      const daysOverdue = delinquent.days_overdue || 0;
      let matchesStatus = true;
      
      switch (statusFilter) {
        case 'current':
          matchesStatus = daysOverdue === 0;
          break;
        case 'low':
          matchesStatus = daysOverdue > 0 && daysOverdue <= 30;
          break;
        case 'medium':
          matchesStatus = daysOverdue > 30 && daysOverdue <= 60;
          break;
        case 'high':
          matchesStatus = daysOverdue > 60 && daysOverdue <= 90;
          break;
        case 'critical':
          matchesStatus = daysOverdue > 90;
          break;
        default:
          matchesStatus = true;
      }
      
      // Month filter
      const billingMonth = delinquent.billing_month || '';
      const matchesMonth = monthFilter === 'all' || billingMonth === monthFilter;
      
      // Year filter
      const billingYear = delinquent.billing_year?.toString() || '';
      const matchesYear = yearFilter === 'all' || billingYear === yearFilter;
      
      return matchesSearch && matchesStatus && matchesMonth && matchesYear;
    });
  }, [delinquents, searchTerm, statusFilter, monthFilter, yearFilter]);

  // Handle select all
  useEffect(() => {
    if (selectAll) {
      setSelectedDelinquents(filteredDelinquents.map(d => d.id || d.billing_id));
    } else {
      setSelectedDelinquents([]);
    }
  }, [selectAll, filteredDelinquents]);

  // Reset to page 1 when filters change
  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm, statusFilter, monthFilter, yearFilter]);

  // Handle individual selection
  const handleSelectDelinquent = (id) => {
    setSelectedDelinquents(prev => {
      if (prev.includes(id)) {
        return prev.filter(item => item !== id);
      } else {
        return [...prev, id];
      }
    });
  };

  // Send email function
  const sendEmailNotices = async () => {
    if (selectedDelinquents.length === 0) {
      setEmailStatus({
        type: 'error',
        message: 'Please select at least one delinquent account'
      });
      return;
    }

    setSendingEmail(true);
    setEmailStatus(null);

    try {
      const selectedData = delinquents.filter(d => selectedDelinquents.includes(d.id || d.billing_id));
      
      const response = await fetch(`${getApiBaseUrl()}/Market/Delinquent/send_delinquent_notices.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          delinquents: selectedData,
          template: emailTemplate,
          custom_message: customMessage
        })
      });

      const result = await response.json();

      if (result.success) {
        setEmailStatus({
          type: 'success',
          message: `Successfully sent ${result.sent_count} email notices`
        });
        setShowEmailModal(false);
        setSelectedDelinquents([]);
        setSelectAll(false);
        setCustomMessage('');
        setEmailTemplate('standard');
      } else {
        setEmailStatus({
          type: 'error',
          message: result.error || 'Failed to send emails'
        });
      }
    } catch (err) {
      console.error('Email error:', err);
      setEmailStatus({
        type: 'error',
        message: 'Network error occurred'
      });
    } finally {
      setSendingEmail(false);
    }
  };

  // Generate years for filter
  const currentYear = new Date().getFullYear();
  const years = Array.from({ length: 6 }, (_, i) => (currentYear - i).toString());
  
  // Get unique months from data
  const months = [...new Set(delinquents.map(d => d.billing_month).filter(Boolean))];
  const monthNames = {
    '01': 'January', '02': 'February', '03': 'March', '04': 'April',
    '05': 'May', '06': 'June', '07': 'July', '08': 'August',
    '09': 'September', '10': 'October', '11': 'November', '12': 'December'
  };

  // Format currency
  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2
    }).format(amount || 0);
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

  // Get overdue status color and icon
  const getStatusInfo = (daysLate) => {
    if (daysLate > 0) {
      const severity = daysLate > 90 ? "critical" : 
                       daysLate > 60 ? "high" : 
                       daysLate > 30 ? "medium" : "low";
      
      const colorMap = {
        critical: { 
          text: "text-red-800", 
          bg: "bg-red-50", 
          border: "border-red-200",
          icon: AlertTriangle
        },
        high: { 
          text: "text-orange-800", 
          bg: "bg-orange-50", 
          border: "border-orange-200",
          icon: AlertTriangle
        },
        medium: { 
          text: "text-yellow-800", 
          bg: "bg-yellow-50", 
          border: "border-yellow-200",
          icon: Clock
        },
        low: { 
          text: "text-blue-800", 
          bg: "bg-blue-50", 
          border: "border-blue-200",
          icon: Clock
        }
      };
      
      const info = colorMap[severity] || colorMap.low;
      
      return {
        label: `Overdue (${daysLate} days)`,
        severity,
        ...info,
        daysLate
      };
    }
    
    return {
      label: "Current",
      text: "text-green-800",
      bg: "bg-green-50",
      border: "border-green-200",
      icon: CheckCircle,
      severity: "current"
    };
  };

  // Calculate pagination
  const totalPages = Math.ceil(filteredDelinquents.length / itemsPerPage);
  const indexOfLastItem = currentPage * itemsPerPage;
  const indexOfFirstItem = indexOfLastItem - itemsPerPage;
  const currentDelinquents = filteredDelinquents.slice(indexOfFirstItem, indexOfLastItem);

  // Calculate summary statistics
  const calculateSummary = () => {
    const totalDelinquents = delinquents.length;
    const totalOverdueAmount = delinquents.reduce((sum, d) => sum + (parseFloat(d.overdue_amount) || 0), 0);
    const totalDaysOverdue = delinquents.reduce((sum, d) => sum + (d.days_overdue || 0), 0);
    const averageDaysOverdue = totalDelinquents > 0 ? Math.round(totalDaysOverdue / totalDelinquents) : 0;
    
    // Count by severity
    const severityCounts = {
      current: 0,
      low: 0,
      medium: 0,
      high: 0,
      critical: 0
    };
    
    delinquents.forEach(d => {
      const daysOverdue = d.days_overdue || 0;
      if (daysOverdue === 0) severityCounts.current++;
      else if (daysOverdue <= 30) severityCounts.low++;
      else if (daysOverdue <= 60) severityCounts.medium++;
      else if (daysOverdue <= 90) severityCounts.high++;
      else severityCounts.critical++;
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
  const handleExportReport = () => {
    const csvContent = [
      ['Stall Rights No', 'Renter Name', 'Renter Code', 'Business Name', 'Stall Name', 'Stall Class', 'Month', 'Year', 'Amount Due', 'Days Overdue', 'Status', 'Due Date', 'Email', 'Phone'],
      ...filteredDelinquents.map(d => [
        d.stall_rights_no || 'N/A',
        d.full_name || 'N/A',
        d.renter_code || 'N/A',
        d.business_name || 'N/A',
        d.stall_name || 'N/A',
        d.stall_class || 'N/A',
        d.billing_month || 'N/A',
        d.billing_year || 'N/A',
        d.overdue_amount || '0',
        d.days_overdue || '0',
        getStatusInfo(d.days_overdue || 0).label,
        formatDate(d.due_date),
        d.email || 'N/A',
        d.mobile || 'N/A'
      ])
    ].map(row => row.join(',')).join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `market_delinquents_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
  };

  // Email Modal Component
  const EmailModal = () => {
    if (!showEmailModal) return null;

    const selectedData = delinquents.filter(d => selectedDelinquents.includes(d.id || d.billing_id));
    const totalAmount = selectedData.reduce((sum, d) => {
      return sum + (parseFloat(d.overdue_amount) || 0);
    }, 0);
    const avgDaysLate = selectedData.length > 0 
      ? Math.round(selectedData.reduce((sum, d) => sum + (d.days_overdue || 0), 0) / selectedData.length)
      : 0;

    // Handle backdrop click
    const handleBackdropClick = (e) => {
      if (e.target === e.currentTarget) {
        setShowEmailModal(false);
      }
    };

    // Prevent body scroll when modal is open
    useEffect(() => {
      document.body.style.overflow = 'hidden';
      return () => {
        document.body.style.overflow = 'unset';
      };
    }, []);

    return (
      <div 
        className="fixed inset-0 z-50 overflow-y-auto"
        onClick={handleBackdropClick}
        style={{ backgroundColor: 'rgba(0, 0, 0, 0.5)' }}
      >
        <div className="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
          <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">
            &#8203;
          </span>

          <div 
            className="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div className="sm:flex sm:items-start">
                <div className="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                  <Mail className="h-6 w-6 text-blue-600" />
                </div>
                <div className="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                  <h3 className="text-lg leading-6 font-medium text-gray-900">
                    Send Delinquent Notices
                  </h3>
                  <p className="text-sm text-gray-500 mt-2">
                    Sending to <span className="font-semibold">{selectedDelinquents.length}</span> selected delinquent account{selectedDelinquents.length === 1 ? '' : 's'}
                  </p>

                  <div className="mt-4 space-y-4">
                    {/* Email Template Selection */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Email Template
                      </label>
                      <select
                        value={emailTemplate}
                        onChange={(e) => setEmailTemplate(e.target.value)}
                        className="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                      >
                        <option value="standard">Standard Notice</option>
                        <option value="urgent">Urgent Notice</option>
                        <option value="final">Final Notice (Stall Reclamation)</option>
                      </select>
                    </div>

                    {/* Custom Message */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Custom Message (Optional)
                      </label>
                      <textarea
                        rows="4"
                        value={customMessage}
                        onChange={(e) => setCustomMessage(e.target.value)}
                        placeholder="Add any additional instructions or message..."
                        className="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                      />
                    </div>

                    {/* Summary */}
                    <div className="bg-gray-50 p-4 rounded-lg">
                      <h4 className="text-sm font-medium text-gray-900 mb-2">Summary</h4>
                      <div className="space-y-2">
                        <p className="text-sm text-gray-600 flex justify-between">
                          <span>Total Amount Due:</span>
                          <span className="font-semibold">{formatCurrency(totalAmount)}</span>
                        </p>
                        <p className="text-sm text-gray-600 flex justify-between">
                          <span>Average Days Late:</span>
                          <span className="font-semibold">{avgDaysLate} days</span>
                        </p>
                        <p className="text-sm text-gray-600 flex justify-between">
                          <span>Template:</span>
                          <span className="font-semibold capitalize">{emailTemplate}</span>
                        </p>
                      </div>
                    </div>

                    {/* Recipient List */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Recipients ({selectedDelinquents.length})
                      </label>
                      <div className="max-h-40 overflow-y-auto border rounded-md p-2">
                        {selectedData.map(d => (
                          <div key={d.id || d.billing_id} className="text-sm py-1 border-b last:border-0">
                            <span className="font-medium">{d.business_name || d.full_name}</span>
                            <span className="text-gray-500 ml-2">({d.email || 'No email'})</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div className="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
              <button
                type="button"
                onClick={sendEmailNotices}
                disabled={sendingEmail}
                className="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {sendingEmail ? (
                  <>
                    <RefreshCw className="w-4 h-4 mr-2 animate-spin" />
                    Sending...
                  </>
                ) : (
                  <>
                    <Send className="w-4 h-4 mr-2" />
                    Send Notices
                  </>
                )}
              </button>
              <button
                type="button"
                onClick={() => setShowEmailModal(false)}
                className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  };

  // Render loading state
  if (loading) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
        <div className="flex items-center justify-center p-4 h-screen">
          <div className="text-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 mb-4"
                 style={{ borderColor: COLORS.primary }}></div>
            <p className="font-medium" style={{ color: COLORS.dark }}>Loading delinquent accounts...</p>
            <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>Fetching data from server</p>
          </div>
        </div>
      </div>
    );
  }

  // Render error state
  if (error) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
        <div className="flex items-center justify-center p-4 h-screen">
          <div className="bg-white rounded-lg border p-6 max-w-md w-full" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="text-center">
              <div className="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4" 
                   style={{ backgroundColor: `${COLORS.danger}15` }}>
                <AlertCircle className="w-6 h-6" style={{ color: COLORS.danger }} />
              </div>
              <h2 className="text-lg font-semibold mb-2" style={{ color: COLORS.dark }}>Connection Error</h2>
              <p className="text-sm mb-4" style={{ color: COLORS.secondary }}>{error}</p>
              <button
                onClick={fetchDelinquents}
                className="w-full px-4 py-2.5 rounded-md font-medium text-white transition duration-200"
                style={{ backgroundColor: COLORS.primary }}
              >
                <RefreshCw className="w-4 h-4 inline-block mr-2" />
                Retry Connection
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
      {/* Email Status Toast */}
      {emailStatus && (
        <div className={`fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${
          emailStatus.type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'
        } border`}>
          <div className="flex items-center">
            {emailStatus.type === 'success' ? (
              <CheckCircle className="w-5 h-5 text-green-600 mr-3" />
            ) : (
              <AlertCircle className="w-5 h-5 text-red-600 mr-3" />
            )}
            <p className={`text-sm font-medium ${
              emailStatus.type === 'success' ? 'text-green-800' : 'text-red-800'
            }`}>
              {emailStatus.message}
            </p>
          </div>
        </div>
      )}

      {/* Email Modal */}
      <EmailModal />

      {/* Header */}
      <div className="border-b bg-white sticky top-0 z-10">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>
                Market Delinquent Accounts
              </h1>
              <p className="text-sm" style={{ color: COLORS.secondary }}>
                Track and manage overdue stall rental payments
              </p>
            </div>
            
            <div className="flex flex-wrap gap-3 items-center">
              {/* Email Button */}
              <button
                onClick={() => {
                  if (selectedDelinquents.length === 0) {
                    setEmailStatus({
                      type: 'error',
                      message: 'Please select accounts to send email notices'
                    });
                    setTimeout(() => setEmailStatus(null), 3000);
                  } else {
                    setShowEmailModal(true);
                  }
                }}
                className="flex items-center gap-2 px-4 py-2 rounded-lg text-white transition-all hover:shadow-lg"
                style={{ 
                  backgroundColor: COLORS.primary,
                  opacity: selectedDelinquents.length === 0 ? 0.5 : 1
                }}
                disabled={selectedDelinquents.length === 0}
              >
                <Mail className="w-4 h-4" />
                Send Email Notice ({selectedDelinquents.length})
              </button>

              <button
                onClick={handleExportReport}
                className="flex items-center gap-2 px-4 py-2 border rounded-lg text-gray-700 transition-all hover:bg-gray-50"
                style={{ 
                  borderColor: COLORS.secondary,
                  backgroundColor: 'white'
                }}
              >
                <Download className="w-4 h-4" />
                Export Report
              </button>
              <button
                onClick={fetchDelinquents}
                className="flex items-center gap-2 px-4 py-2 border rounded-lg text-gray-700 transition-all hover:bg-gray-50"
                style={{ 
                  borderColor: COLORS.secondary,
                  backgroundColor: 'white'
                }}
              >
                <RefreshCw className="w-4 h-4" />
                Refresh
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Stats Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Total Delinquent Accounts */}
          <div className="bg-white border rounded-xl p-5 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium" style={{ color: COLORS.secondary }}>Delinquent Accounts</p>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.danger }}>{summary.totalDelinquents}</p>
              </div>
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.danger}15` }}>
                <Store className="w-5 h-5" style={{ color: COLORS.danger }} />
              </div>
            </div>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              {summary.severityCounts.critical} critical, {summary.severityCounts.high} high risk
            </div>
          </div>

          {/* Total Amount Due */}
          <div className="bg-white border rounded-xl p-5 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium" style={{ color: COLORS.secondary }}>Total Overdue Amount</p>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.dark }}>{formatCurrency(summary.totalOverdueAmount)}</p>
              </div>
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.dark}08` }}>
                <DollarSign className="w-5 h-5" style={{ color: COLORS.dark }} />
              </div>
            </div>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Unpaid rental fees
            </div>
          </div>

          {/* Average Days Late */}
          <div className="bg-white border rounded-xl p-5 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium" style={{ color: COLORS.secondary }}>Average Days Late</p>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.warning }}>{summary.averageDaysOverdue} days</p>
              </div>
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.warning}15` }}>
                <Clock className="w-5 h-5" style={{ color: COLORS.warning }} />
              </div>
            </div>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Across all delinquent accounts
            </div>
          </div>

          {/* Critical Cases */}
          <div className="bg-white border rounded-xl p-5 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium" style={{ color: COLORS.secondary }}>Critical Cases</p>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.info }}>{summary.severityCounts.critical}</p>
              </div>
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.info}15` }}>
                <AlertTriangle className="w-5 h-5" style={{ color: COLORS.info }} />
              </div>
            </div>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              90+ days overdue
            </div>
          </div>
        </div>

        {/* Filters Section */}
        <div className="bg-white border rounded-xl p-5 transition-all" style={{ borderColor: COLORS.secondary }}>
          <div className="flex justify-between items-center mb-4">
            <h3 className="font-semibold" style={{ color: COLORS.dark }}>Filter Delinquent Accounts</h3>
            <button
              onClick={() => setShowFilters(!showFilters)}
              className="flex items-center gap-2 text-sm"
              style={{ color: COLORS.primary }}
            >
              <Filter className="w-4 h-4" />
              {showFilters ? "Hide Filters" : "Show Filters"}
            </button>
          </div>
          
          {showFilters && (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              {/* Search */}
              <div>
                <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Search</label>
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                         style={{ color: COLORS.secondary }} />
                  <input
                    type="text"
                    placeholder="Search by business, renter, or stall..."
                    value={searchTerm}
                    onChange={(e) => {
                      setSearchTerm(e.target.value);
                    }}
                    className="w-full pl-10 pr-4 py-2.5 text-sm border rounded-lg focus:ring-2 focus:border-transparent transition duration-200"
                    style={{ 
                      borderColor: COLORS.secondary,
                      backgroundColor: 'white'
                    }}
                  />
                </div>
              </div>

              {/* Status Filter */}
              <div>
                <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Overdue Status</label>
                <div className="relative">
                  <AlertCircle className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                         style={{ color: COLORS.secondary }} />
                  <select
                    value={statusFilter}
                    onChange={(e) => {
                      setStatusFilter(e.target.value);
                    }}
                    className="w-full pl-10 pr-10 py-2.5 text-sm border rounded-lg focus:ring-2 focus:border-transparent appearance-none transition duration-200"
                    style={{ 
                      borderColor: COLORS.secondary,
                      backgroundColor: 'white'
                    }}
                  >
                    <option value="all">All Status</option>
                    <option value="current">Current</option>
                    <option value="low">Low (1-30 days)</option>
                    <option value="medium">Medium (31-60 days)</option>
                    <option value="high">High (61-90 days)</option>
                    <option value="critical">Critical (90+ days)</option>
                  </select>
                </div>
              </div>

              {/* Month Filter */}
              <div>
                <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Month</label>
                <div className="relative">
                  <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                           style={{ color: COLORS.secondary }} />
                  <select
                    value={monthFilter}
                    onChange={(e) => {
                      setMonthFilter(e.target.value);
                    }}
                    className="w-full pl-10 pr-10 py-2.5 text-sm border rounded-lg focus:ring-2 focus:border-transparent appearance-none transition duration-200"
                    style={{ 
                      borderColor: COLORS.secondary,
                      backgroundColor: 'white'
                    }}
                  >
                    <option value="all">All Months</option>
                    {months.sort().map(month => (
                      <option key={month} value={month}>{monthNames[month] || month}</option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Year Filter */}
              <div>
                <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Year</label>
                <div className="relative">
                  <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                           style={{ color: COLORS.secondary }} />
                  <select
                    value={yearFilter}
                    onChange={(e) => {
                      setYearFilter(e.target.value);
                    }}
                    className="w-full pl-10 pr-10 py-2.5 text-sm border rounded-lg focus:ring-2 focus:border-transparent appearance-none transition duration-200"
                    style={{ 
                      borderColor: COLORS.secondary,
                      backgroundColor: 'white'
                    }}
                  >
                    <option value="all">All Years</option>
                    {years.map(year => (
                      <option key={year} value={year}>{year}</option>
                    ))}
                  </select>
                </div>
              </div>
            </div>
          )}
          
          {/* Search Stats */}
          <div className="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              {filteredDelinquents.length} delinquent account{filteredDelinquents.length === 1 ? '' : 's'} found
            </div>
            <div className="text-sm font-medium" style={{ color: COLORS.dark }}>
              Total overdue: {formatCurrency(
                filteredDelinquents.reduce((sum, d) => sum + (parseFloat(d.overdue_amount) || 0), 0)
              )}
            </div>
          </div>
        </div>

        {/* Delinquent Table */}
        <div className="bg-white border rounded-xl overflow-hidden shadow-sm transition-all" 
             style={{ borderColor: COLORS.secondary }}>
          <div className="px-5 py-4 border-b" style={{ borderColor: COLORS.secondary, backgroundColor: `${COLORS.background}` }}>
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 className="text-sm font-semibold uppercase tracking-wider" style={{ color: COLORS.dark }}>
                  Delinquent Accounts
                </h2>
                <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                  {filteredDelinquents.length} account{filteredDelinquents.length === 1 ? '' : 's'} with overdue payments
                </p>
              </div>
              
              {/* Selection Controls */}
              {filteredDelinquents.length > 0 && (
                <div className="flex items-center gap-4 mt-2 sm:mt-0">
                  <label className="flex items-center gap-2 text-sm">
                    <input
                      type="checkbox"
                      checked={selectAll}
                      onChange={(e) => setSelectAll(e.target.checked)}
                      className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span style={{ color: COLORS.dark }}>Select All</span>
                  </label>
                  {selectedDelinquents.length > 0 && (
                    <span className="text-sm font-medium" style={{ color: COLORS.primary }}>
                      {selectedDelinquents.length} selected
                    </span>
                  )}
                </div>
              )}
            </div>
          </div>
          
          {filteredDelinquents.length === 0 ? (
            <div className="px-4 py-12 text-center">
              <div className="mx-auto w-12 h-12 rounded-full flex items-center justify-center mb-3" 
                   style={{ backgroundColor: `${COLORS.success}15` }}>
                <CheckCircle className="w-6 h-6" style={{ color: COLORS.success }} />
              </div>
              <h3 className="text-sm font-medium mb-1" style={{ color: COLORS.dark }}>
                {searchTerm || statusFilter !== "all" || monthFilter !== "all" || yearFilter !== "all"
                  ? "No delinquent accounts found" 
                  : "No delinquent accounts at this time"}
              </h3>
              <p className="text-sm max-w-xs mx-auto" style={{ color: COLORS.secondary }}>
                {searchTerm || statusFilter !== "all" || monthFilter !== "all" || yearFilter !== "all"
                  ? "Try adjusting your search filters"
                  : "All stall rentals are currently up to date"}
              </p>
              {(searchTerm || statusFilter !== "all" || monthFilter !== "all" || yearFilter !== "all") && (
                <button
                  onClick={() => {
                    setSearchTerm("");
                    setStatusFilter("all");
                    setMonthFilter("all");
                    setYearFilter("all");
                  }}
                  className="mt-4 text-sm font-medium transition-all"
                  style={{ color: COLORS.primary }}
                >
                  Clear all filters
                </button>
              )}
            </div>
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y" style={{ borderColor: COLORS.secondary }}>
                  <thead style={{ backgroundColor: `${COLORS.background}` }}>
                    <tr>
                      <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider" 
                          style={{ color: COLORS.secondary, width: '40px' }}>
                        <span className="sr-only">Select</span>
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider" 
                          style={{ color: COLORS.secondary }}>
                        Stall Details
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider" 
                          style={{ color: COLORS.secondary }}>
                        Renter Information
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider" 
                          style={{ color: COLORS.secondary }}>
                        Period
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider" 
                          style={{ color: COLORS.secondary }}>
                        Amount Details
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider" 
                          style={{ color: COLORS.secondary }}>
                        Status & Due Date
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider" 
                          style={{ color: COLORS.secondary }}>
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y" style={{ borderColor: COLORS.secondary }}>
                    {currentDelinquents.map((delinquent) => {
                      const statusInfo = getStatusInfo(delinquent.days_overdue || 0);
                      const StatusIcon = statusInfo.icon;
                      const totalDue = parseFloat(delinquent.overdue_amount || 0);
                      const monthName = monthNames[delinquent.billing_month] || delinquent.billing_month;
                      
                      return (
                        <tr key={delinquent.id || delinquent.billing_id} className="hover:bg-gray-50 transition-colors">
                          <td className="px-5 py-4">
                            <input
                              type="checkbox"
                              checked={selectedDelinquents.includes(delinquent.id || delinquent.billing_id)}
                              onChange={() => handleSelectDelinquent(delinquent.id || delinquent.billing_id)}
                              className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                          </td>
                          <td className="px-5 py-4">
                            <div className="flex items-center gap-3">
                              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                                <Store className="w-4 h-4" style={{ color: COLORS.primary }} />
                              </div>
                              <div>
                                <div className="font-mono text-xs font-semibold" style={{ color: COLORS.dark }}>
                                  {delinquent.stall_rights_no || 'N/A'}
                                </div>
                                <div className="text-sm font-medium mt-0.5" style={{ color: COLORS.dark }}>
                                  {delinquent.stall_name || 'N/A'}
                                </div>
                                <div className="text-xs" style={{ color: COLORS.secondary }}>
                                  Class {delinquent.stall_class || 'N/A'}
                                </div>
                                <div className="text-xs" style={{ color: COLORS.secondary }}>
                                  {delinquent.business_name || 'No business name'}
                                </div>
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="font-medium text-sm" style={{ color: COLORS.dark }}>
                              {delinquent.full_name || 'N/A'}
                            </div>
                            <div className="text-xs truncate max-w-[180px]" style={{ color: COLORS.secondary }}>
                              Code: {delinquent.renter_code || 'N/A'}
                            </div>
                            <div className="text-xs" style={{ color: COLORS.secondary }}>
                              {delinquent.mobile || 'No phone'}
                            </div>
                            <div className="text-xs truncate max-w-[180px]" style={{ color: COLORS.secondary }}>
                              {delinquent.email || 'No email'}
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="text-center">
                              <div className="font-bold text-lg" style={{ color: COLORS.dark }}>
                                {monthName || 'N/A'}
                              </div>
                              <div className="text-sm" style={{ color: COLORS.secondary }}>
                                {delinquent.billing_year || 'N/A'}
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="space-y-1">
                              <div className="flex justify-between text-sm">
                                <span style={{ color: COLORS.secondary }}>Monthly Rent:</span>
                                <span className="font-medium" style={{ color: COLORS.dark }}>
                                  {formatCurrency(delinquent.monthly_rent || 0)}
                                </span>
                              </div>
                              <div className="flex justify-between text-sm">
                                <span style={{ color: COLORS.secondary }}>Base Amount:</span>
                                <span className="font-medium" style={{ color: COLORS.dark }}>
                                  {formatCurrency(delinquent.base_rent || 0)}
                                </span>
                              </div>
                              <div className="flex justify-between text-sm">
                                <span style={{ color: COLORS.danger }}>Penalty:</span>
                                <span className="font-medium" style={{ color: COLORS.danger }}>
                                  {formatCurrency(delinquent.penalty_amount || 0)}
                                </span>
                              </div>
                              <div className="flex justify-between text-sm font-bold border-t pt-1" 
                                   style={{ borderColor: COLORS.secondary }}>
                                <span style={{ color: COLORS.dark }}>Total Due:</span>
                                <span className="font-bold" style={{ color: COLORS.dark }}>
                                  {formatCurrency(totalDue)}
                                </span>
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="flex items-center gap-3">
                              <div className={`p-2 rounded-lg ${statusInfo.bg}`}>
                                <StatusIcon className={`w-4 h-4 ${statusInfo.text}`} />
                              </div>
                              <div>
                                <span className={`text-xs font-medium px-3 py-1.5 rounded-full ${statusInfo.bg} ${statusInfo.text} border ${statusInfo.border}`}>
                                  {statusInfo.label}
                                </span>
                                <div className="text-xs mt-1" style={{ color: COLORS.secondary }}>
                                  Due: {formatDate(delinquent.due_date)}
                                </div>
                                {delinquent.last_payment_date && (
                                  <div className="text-xs mt-0.5" style={{ color: COLORS.success }}>
                                    Last: {formatDate(delinquent.last_payment_date)}
                                  </div>
                                )}
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="flex flex-col gap-2">
                              <button
                                onClick={() => {
                                  setSelectedDelinquents([delinquent.id || delinquent.billing_id]);
                                  setShowEmailModal(true);
                                }}
                                className="text-sm font-medium px-3 py-1.5 rounded-lg flex items-center justify-center gap-1 transition-all"
                                style={{ 
                                  backgroundColor: COLORS.danger, 
                                  color: 'white'
                                }}
                              >
                                <Bell className="w-3 h-3" />
                                Send Notice
                              </button>
                              <button
                                onClick={() => setSelectedDelinquent(delinquent)}
                                className="text-sm font-medium px-3 py-1.5 rounded-lg border flex items-center justify-center gap-1 transition-all"
                                style={{ 
                                  borderColor: COLORS.primary,
                                  color: COLORS.primary,
                                  backgroundColor: 'white'
                                }}
                              >
                                <Eye className="w-3 h-3" />
                                View Details
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
              
              {/* Table Footer */}
              <div className="px-5 py-4 border-t" 
                   style={{ borderColor: COLORS.secondary, backgroundColor: `${COLORS.background}` }}>
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div className="text-sm" style={{ color: COLORS.secondary }}>
                    Showing <span className="font-semibold" style={{ color: COLORS.dark }}>{filteredDelinquents.length}</span> delinquent account{filteredDelinquents.length === 1 ? '' : 's'}
                    {selectedDelinquents.length > 0 && (
                      <span className="ml-2 font-medium" style={{ color: COLORS.primary }}>
                        ({selectedDelinquents.length} selected)
                      </span>
                    )}
                  </div>
                  <div className="text-sm font-medium" style={{ color: COLORS.dark }}>
                    Total Outstanding: {formatCurrency(
                      filteredDelinquents.reduce((sum, d) => sum + (parseFloat(d.overdue_amount) || 0), 0)
                    )}
                  </div>
                </div>
              </div>

              {/* Pagination */}
              {totalPages > 1 && (
                <div className="px-5 py-4 border-t" 
                     style={{ borderColor: COLORS.secondary, backgroundColor: `${COLORS.background}` }}>
                  <div className="flex items-center justify-between">
                    <div className="text-xs" style={{ color: COLORS.secondary }}>
                      Page {currentPage} of {totalPages}
                    </div>
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
                        disabled={currentPage === 1}
                        className="p-2 border rounded-lg bg-white disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition-colors"
                        style={{ borderColor: COLORS.secondary }}
                      >
                        <ChevronLeft className="w-4 h-4" style={{ color: COLORS.dark }} />
                      </button>
                      <span className="text-sm px-3 py-1" style={{ color: COLORS.dark }}>
                        {indexOfFirstItem + 1} - {Math.min(indexOfLastItem, filteredDelinquents.length)}
                      </span>
                      <button
                        onClick={() => setCurrentPage(prev => Math.min(prev + 1, totalPages))}
                        disabled={currentPage === totalPages}
                        className="p-2 border rounded-lg bg-white disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition-colors"
                        style={{ borderColor: COLORS.secondary }}
                      >
                        <ChevronRight className="w-4 h-4" style={{ color: COLORS.dark }} />
                      </button>
                    </div>
                  </div>
                </div>
              )}
            </>
          )}
        </div>

        {/* Footer */}
        <div className="mt-8 pt-6 border-t text-center text-sm" 
             style={{ borderColor: COLORS.secondary, color: COLORS.secondary }}>
          <p className="font-medium" style={{ color: COLORS.dark }}>Quezon City Public Market - Delinquent Management</p>
          <p className="mt-1">Market Revenue Collection System v2.0</p>
          <p className="mt-1 text-xs">
            Last updated: {new Date().toLocaleDateString('en-PH', { 
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
        <div className="fixed inset-0 z-50 overflow-y-auto" style={{ backgroundColor: 'rgba(0, 0, 0, 0.5)' }}>
          <div className="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">
              &#8203;
            </span>

            <div className="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
              <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div className="sm:flex sm:items-start">
                  <div className="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                    <Store className="h-6 w-6 text-blue-600" />
                  </div>
                  <div className="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                    <h3 className="text-lg leading-6 font-medium text-gray-900">
                      Delinquent Account Details
                    </h3>
                    <p className="text-sm text-gray-500 mt-1">
                      {selectedDelinquent.stall_rights_no} - {selectedDelinquent.business_name}
                    </p>

                    <div className="mt-4 grid grid-cols-2 gap-4">
                      <div className="col-span-2 sm:col-span-1">
                        <h4 className="text-xs font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
                          Renter Information
                        </h4>
                        <div className="space-y-2">
                          <p className="text-sm"><span className="font-medium">Name:</span> {selectedDelinquent.full_name}</p>
                          <p className="text-sm"><span className="font-medium">Code:</span> {selectedDelinquent.renter_code}</p>
                          <p className="text-sm"><span className="font-medium">Mobile:</span> {selectedDelinquent.mobile}</p>
                          <p className="text-sm"><span className="font-medium">Email:</span> {selectedDelinquent.email}</p>
                        </div>
                      </div>
                      
                      <div className="col-span-2 sm:col-span-1">
                        <h4 className="text-xs font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
                          Stall Information
                        </h4>
                        <div className="space-y-2">
                          <p className="text-sm"><span className="font-medium">Stall:</span> {selectedDelinquent.stall_name}</p>
                          <p className="text-sm"><span className="font-medium">Class:</span> {selectedDelinquent.stall_class}</p>
                          <p className="text-sm"><span className="font-medium">Business:</span> {selectedDelinquent.business_name}</p>
                          <p className="text-sm"><span className="font-medium">Monthly Rent:</span> {formatCurrency(selectedDelinquent.monthly_rent)}</p>
                        </div>
                      </div>
                      
                      <div className="col-span-2">
                        <h4 className="text-xs font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
                          Payment Details
                        </h4>
                        <div className="bg-gray-50 p-3 rounded-lg grid grid-cols-2 gap-3">
                          <div>
                            <p className="text-xs text-gray-500">Period</p>
                            <p className="text-sm font-medium">{monthNames[selectedDelinquent.billing_month]} {selectedDelinquent.billing_year}</p>
                          </div>
                          <div>
                            <p className="text-xs text-gray-500">Due Date</p>
                            <p className="text-sm font-medium">{formatDate(selectedDelinquent.due_date)}</p>
                          </div>
                          <div>
                            <p className="text-xs text-gray-500">Base Rent</p>
                            <p className="text-sm font-medium">{formatCurrency(selectedDelinquent.base_rent)}</p>
                          </div>
                          <div>
                            <p className="text-xs text-gray-500">Penalty</p>
                            <p className="text-sm font-medium" style={{ color: COLORS.danger }}>{formatCurrency(selectedDelinquent.penalty_amount)}</p>
                          </div>
                          <div className="col-span-2">
                            <p className="text-xs text-gray-500">Total Overdue</p>
                            <p className="text-lg font-bold" style={{ color: COLORS.danger }}>{formatCurrency(selectedDelinquent.overdue_amount)}</p>
                          </div>
                        </div>
                      </div>
                      
                      {selectedDelinquent.last_payment_date && (
                        <div className="col-span-2">
                          <h4 className="text-xs font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
                            Last Payment
                          </h4>
                          <div className="bg-green-50 p-3 rounded-lg">
                            <p className="text-sm">
                              <span className="font-medium">Date:</span> {formatDate(selectedDelinquent.last_payment_date)}
                            </p>
                            <p className="text-sm">
                              <span className="font-medium">Amount:</span> {formatCurrency(selectedDelinquent.last_payment_amount)}
                            </p>
                            {selectedDelinquent.last_receipt && (
                              <p className="text-sm">
                                <span className="font-medium">Receipt:</span> {selectedDelinquent.last_receipt}
                              </p>
                            )}
                          </div>
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              </div>
              <div className="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button
                  type="button"
                  onClick={() => setSelectedDelinquent(null)}
                  className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                >
                  Close
                </button>
              </div>
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