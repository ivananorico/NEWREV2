import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';

export default function MarketDelinquent() {
  const [delinquents, setDelinquents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedStatus, setSelectedStatus] = useState('all');
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 10;

  // FIXED: Dynamic API base URL
  const getApiBaseUrl = () => {
    const { hostname, protocol, pathname } = window.location;
    
    console.log("Current location info:", { hostname, protocol, pathname });
    
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
      
      console.log("Fetching from URL:", url);
      
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
        },
        mode: 'cors'
      });
      
      console.log("Response status:", response.status);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      console.log("Response data:", data);
      
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
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2
    }).format(num);
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

  // Render loading state
  if (loading) {
    return (
      <div className='mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg min-h-screen'>
        <div className="flex items-center justify-center h-64">
          <div className="text-center">
            <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-600 mx-auto"></div>
            <p className="mt-4 text-gray-600">Loading delinquent data...</p>
          </div>
        </div>
      </div>
    );
  }

  // Render error state
  if (error) {
    return (
      <div className='mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg min-h-screen'>
        <div className="bg-red-50 border border-red-200 rounded-xl p-6 max-w-md mx-auto mt-8">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <i className="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
            </div>
            <div className="ml-3">
              <h3 className="text-lg font-medium text-red-800">Error Loading Data</h3>
              <div className="mt-2 text-sm text-red-700">
                <p>{error}</p>
              </div>
            </div>
          </div>
          <div className="mt-4">
            <button
              onClick={fetchDelinquents}
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
            >
              <i className="fas fa-sync-alt mr-2"></i> Try Again
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className='mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg'>
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col md:flex-row md:items-center justify-between mb-6">
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Market Delinquent Management</h1>
            <p className="text-gray-600 dark:text-gray-400 mt-2">
              Monitor and manage overdue stall rental payments
            </p>
          </div>
          
          <div className="mt-4 md:mt-0 flex gap-3">
            <button
              onClick={fetchDelinquents}
              disabled={loading}
              className="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50 transition-colors flex items-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-sync-alt"></i> Refresh
            </button>
            <button className="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2 shadow-sm hover:shadow">
              <i className="fas fa-file-export"></i> Export
            </button>
          </div>
        </div>

        {/* Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div className="bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-xl p-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-blue-800">Total Delinquents</p>
                <p className="text-2xl font-bold text-blue-900 mt-1">{summary.totalDelinquents}</p>
              </div>
              <div className="p-3 bg-blue-200 rounded-lg">
                <i className="fas fa-users text-blue-600 text-xl"></i>
              </div>
            </div>
          </div>
          
          <div className="bg-gradient-to-r from-red-50 to-red-100 border border-red-200 rounded-xl p-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-red-800">Total Overdue Amount</p>
                <p className="text-2xl font-bold text-red-900 mt-1">{formatCurrency(summary.totalOverdueAmount)}</p>
              </div>
              <div className="p-3 bg-red-200 rounded-lg">
                <i className="fas fa-money-bill-wave text-red-600 text-xl"></i>
              </div>
            </div>
          </div>
          
          <div className="bg-gradient-to-r from-amber-50 to-amber-100 border border-amber-200 rounded-xl p-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-amber-800">Average Days Overdue</p>
                <p className="text-2xl font-bold text-amber-900 mt-1">{summary.averageDaysOverdue} days</p>
              </div>
              <div className="p-3 bg-amber-200 rounded-lg">
                <i className="fas fa-calendar-day text-amber-600 text-xl"></i>
              </div>
            </div>
          </div>
          
          <div className="bg-gradient-to-r from-purple-50 to-purple-100 border border-purple-200 rounded-xl p-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-purple-800">Severe Cases</p>
                <p className="text-2xl font-bold text-purple-900 mt-1">{summary.severityCounts.severe}</p>
              </div>
              <div className="p-3 bg-purple-200 rounded-lg">
                <i className="fas fa-exclamation-triangle text-purple-600 text-xl"></i>
              </div>
            </div>
          </div>
        </div>

        {/* Filters and Search */}
        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 mb-6">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex-1">
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i className="fas fa-search text-gray-400"></i>
                </div>
                <input
                  type="text"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="block w-full pl-10 pr-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Search by business name, stall rights no, renter code..."
                />
              </div>
            </div>
            
            <div className="flex flex-wrap gap-3">
              <select
                value={selectedStatus}
                onChange={(e) => setSelectedStatus(e.target.value)}
                className="px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="all">All Status</option>
                <option value="current">Current (0 days)</option>
                <option value="mild">Mild (1-7 days)</option>
                <option value="moderate">Moderate (8-30 days)</option>
                <option value="severe">Severe (30+ days)</option>
              </select>
              
              <button className="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium transition-colors flex items-center gap-2">
                <i className="fas fa-filter"></i> Filter
              </button>
            </div>
          </div>
          
          {/* Quick Stats */}
          <div className="flex flex-wrap gap-4 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button 
              onClick={() => setSelectedStatus('current')}
              className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${selectedStatus === 'current' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'}`}
            >
              Current: {summary.severityCounts.current}
            </button>
            <button 
              onClick={() => setSelectedStatus('mild')}
              className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${selectedStatus === 'mild' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'}`}
            >
              Mild: {summary.severityCounts.mild}
            </button>
            <button 
              onClick={() => setSelectedStatus('moderate')}
              className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${selectedStatus === 'moderate' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'}`}
            >
              Moderate: {summary.severityCounts.moderate}
            </button>
            <button 
              onClick={() => setSelectedStatus('severe')}
              className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${selectedStatus === 'severe' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'}`}
            >
              Severe: {summary.severityCounts.severe}
            </button>
          </div>
        </div>
      </div>

      {/* Delinquent Table */}
      <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Renter Details
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Stall Information
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Payment Status
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              {currentDelinquents.length === 0 ? (
                <tr>
                  <td colSpan="4" className="px-6 py-12 text-center">
                    <div className="flex flex-col items-center justify-center">
                      <div className="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-3">
                        <i className="fas fa-check-circle text-gray-400 text-2xl"></i>
                      </div>
                      <p className="text-gray-500 dark:text-gray-400 text-lg font-medium">No delinquent records found</p>
                      <p className="text-gray-400 dark:text-gray-500 text-sm mt-1">
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
                    <tr key={delinquent.id} className="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <div className="flex-shrink-0 h-10 w-10 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <i className="fas fa-user text-blue-600 dark:text-blue-300"></i>
                          </div>
                          <div className="ml-4">
                            <div className="text-sm font-medium text-gray-900 dark:text-white">
                              {delinquent.full_name || 'N/A'}
                            </div>
                            <div className="text-sm text-gray-500 dark:text-gray-400">
                              {delinquent.renter_code || 'N/A'}
                            </div>
                            <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                              <i className="fas fa-phone mr-1"></i> {delinquent.mobile || 'N/A'}
                            </div>
                          </div>
                        </div>
                      </td>
                      
                      <td className="px-6 py-4">
                        <div className="text-sm text-gray-900 dark:text-white font-medium">
                          {delinquent.business_name || 'N/A'}
                        </div>
                        <div className="text-sm text-gray-500 dark:text-gray-400">
                          {delinquent.stall_rights_no || 'N/A'} • {delinquent.stall_name || 'N/A'}
                        </div>
                        <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                          <i className="fas fa-store mr-1"></i> {delinquent.stall_class || 'N/A'} Class
                        </div>
                      </td>
                      
                      <td className="px-6 py-4">
                        <div className="space-y-2">
                          <div className="flex items-center justify-between">
                            <span className="text-sm text-gray-600 dark:text-gray-400">Amount Due:</span>
                            <span className="text-sm font-semibold text-red-600 dark:text-red-400">
                              {formatCurrency(delinquent.overdue_amount || 0)}
                            </span>
                          </div>
                          <div className="flex items-center justify-between">
                            <span className="text-sm text-gray-600 dark:text-gray-400">Due Date:</span>
                            <span className="text-sm text-gray-900 dark:text-white">
                              {formatDate(delinquent.due_date)}
                            </span>
                          </div>
                          <div className="flex items-center justify-between">
                            <span className="text-sm text-gray-600 dark:text-gray-400">Days Overdue:</span>
                            <span className={`px-2 py-1 rounded-full text-xs font-medium ${statusColor}`}>
                              {daysOverdue} days ({statusText})
                            </span>
                          </div>
                          {delinquent.last_payment_date && (
                            <div className="flex items-center justify-between">
                              <span className="text-sm text-gray-600 dark:text-gray-400">Last Payment:</span>
                              <span className="text-sm text-green-600 dark:text-green-400">
                                {formatDate(delinquent.last_payment_date)}
                              </span>
                            </div>
                          )}
                        </div>
                      </td>
                      
                      <td className="px-6 py-4">
                        <div className="flex space-x-2">
                          <button
                            onClick={() => {
                              // View details action
                              console.log('View details for:', delinquent.id);
                            }}
                            className="px-3 py-1.5 bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/50 text-blue-700 dark:text-blue-300 rounded-lg text-sm font-medium transition-colors flex items-center gap-1"
                          >
                            <i className="fas fa-eye text-xs"></i> View
                          </button>
                          <button
                            onClick={() => {
                              // Send reminder action
                              console.log('Send reminder to:', delinquent.id);
                            }}
                            className="px-3 py-1.5 bg-amber-50 dark:bg-amber-900/30 hover:bg-amber-100 dark:hover:bg-amber-900/50 text-amber-700 dark:text-amber-300 rounded-lg text-sm font-medium transition-colors flex items-center gap-1"
                          >
                            <i className="fas fa-bell text-xs"></i> Remind
                          </button>
                          <Link
                            to={`/admin/market/validation/${delinquent.application_id}`}
                            className="px-3 py-1.5 bg-green-50 dark:bg-green-900/30 hover:bg-green-100 dark:hover:bg-green-900/50 text-green-700 dark:text-green-300 rounded-lg text-sm font-medium transition-colors flex items-center gap-1"
                          >
                            <i className="fas fa-file-invoice-dollar text-xs"></i> Payment
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
          <div className="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
            <div className="flex items-center justify-between">
              <div className="text-sm text-gray-700 dark:text-gray-400">
                Showing <span className="font-medium">{indexOfFirstItem + 1}</span> to{' '}
                <span className="font-medium">
                  {Math.min(indexOfLastItem, filteredDelinquents.length)}
                </span>{' '}
                of <span className="font-medium">{filteredDelinquents.length}</span> results
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
                  disabled={currentPage === 1}
                  className="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                >
                  <i className="fas fa-chevron-left"></i>
                </button>
                
                {Array.from({ length: totalPages }, (_, i) => i + 1)
                  .filter(page => {
                    // Show first, last, and pages around current
                    return page === 1 || 
                           page === totalPages || 
                           (page >= currentPage - 1 && page <= currentPage + 1);
                  })
                  .map((page, index, array) => {
                    // Add ellipsis if needed
                    const prevPage = array[index - 1];
                    if (prevPage && page - prevPage > 1) {
                      return (
                        <React.Fragment key={`ellipsis-${page}`}>
                          <span className="px-3 py-2 text-gray-500">...</span>
                          <button
                            onClick={() => setCurrentPage(page)}
                            className={`px-3 py-2 rounded-lg transition-colors ${currentPage === page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600'}`}
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
                        className={`px-3 py-2 rounded-lg transition-colors ${currentPage === page ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600'}`}
                      >
                        {page}
                      </button>
                    );
                  })}
                
                <button
                  onClick={() => setCurrentPage(prev => Math.min(prev + 1, totalPages))}
                  disabled={currentPage === totalPages}
                  className="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                >
                  <i className="fas fa-chevron-right"></i>
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
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Severity Breakdown</h3>
            <div className="space-y-4">
              {[
                { label: 'Current (0 days)', count: summary.severityCounts.current, color: 'bg-green-500', textColor: 'text-green-700 dark:text-green-300' },
                { label: 'Mild (1-7 days)', count: summary.severityCounts.mild, color: 'bg-yellow-500', textColor: 'text-yellow-700 dark:text-yellow-300' },
                { label: 'Moderate (8-30 days)', count: summary.severityCounts.moderate, color: 'bg-orange-500', textColor: 'text-orange-700 dark:text-orange-300' },
                { label: 'Severe (30+ days)', count: summary.severityCounts.severe, color: 'bg-red-500', textColor: 'text-red-700 dark:text-red-300' }
              ].map((item) => (
                <div key={item.label} className="space-y-2">
                  <div className="flex justify-between text-sm">
                    <span className={item.textColor}>{item.label}</span>
                    <span className="font-medium text-gray-900 dark:text-white">{item.count} cases</span>
                  </div>
                  <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div
                      className={`${item.color} h-2 rounded-full transition-all duration-500`}
                      style={{ width: `${(item.count / summary.totalDelinquents) * 100}%` }}
                    ></div>
                  </div>
                </div>
              ))}
            </div>
          </div>
          
          {/* Quick Actions */}
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
            <div className="grid grid-cols-2 gap-4">
              <button className="p-4 bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/30 border border-blue-200 dark:border-blue-700 rounded-xl hover:from-blue-100 hover:to-blue-200 dark:hover:from-blue-800/50 dark:hover:to-blue-700/50 transition-all duration-200 flex flex-col items-center justify-center">
                <div className="p-3 bg-blue-200 dark:bg-blue-700 rounded-lg mb-2">
                  <i className="fas fa-envelope text-blue-600 dark:text-blue-300 text-xl"></i>
                </div>
                <span className="font-medium text-gray-900 dark:text-white">Send Bulk Reminders</span>
              </button>
              
              <button className="p-4 bg-gradient-to-r from-green-50 to-green-100 dark:from-green-900/30 dark:to-green-800/30 border border-green-200 dark:border-green-700 rounded-xl hover:from-green-100 hover:to-green-200 dark:hover:from-green-800/50 dark:hover:to-green-700/50 transition-all duration-200 flex flex-col items-center justify-center">
                <div className="p-3 bg-green-200 dark:bg-green-700 rounded-lg mb-2">
                  <i className="fas fa-file-export text-green-600 dark:text-green-300 text-xl"></i>
                </div>
                <span className="font-medium text-gray-900 dark:text-white">Export Report</span>
              </button>
              
              <button className="p-4 bg-gradient-to-r from-amber-50 to-amber-100 dark:from-amber-900/30 dark:to-amber-800/30 border border-amber-200 dark:border-amber-700 rounded-xl hover:from-amber-100 hover:to-amber-200 dark:hover:from-amber-800/50 dark:hover:to-amber-700/50 transition-all duration-200 flex flex-col items-center justify-center">
                <div className="p-3 bg-amber-200 dark:bg-amber-700 rounded-lg mb-2">
                  <i className="fas fa-chart-bar text-amber-600 dark:text-amber-300 text-xl"></i>
                </div>
                <span className="font-medium text-gray-900 dark:text-white">Generate Analytics</span>
              </button>
              
              <button className="p-4 bg-gradient-to-r from-purple-50 to-purple-100 dark:from-purple-900/30 dark:to-purple-800/30 border border-purple-200 dark:border-purple-700 rounded-xl hover:from-purple-100 hover:to-purple-200 dark:hover:from-purple-800/50 dark:hover:to-purple-700/50 transition-all duration-200 flex flex-col items-center justify-center">
                <div className="p-3 bg-purple-200 dark:bg-purple-700 rounded-lg mb-2">
                  <i className="fas fa-cog text-purple-600 dark:text-purple-300 text-xl"></i>
                </div>
                <span className="font-medium text-gray-900 dark:text-white">Settings</span>
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}