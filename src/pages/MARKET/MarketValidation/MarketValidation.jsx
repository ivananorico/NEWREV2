import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';

const MarketValidation = () => {
  const [applications, setApplications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage, setItemsPerPage] = useState(10);
  const [filterStatus, setFilterStatus] = useState('all');

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

  // Handle page change
  const handlePageChange = (page) => {
    setCurrentPage(page);
  };

  // Get status color
  const getStatusColor = (status) => {
    if (!status) return 'bg-gray-100 text-gray-800 border border-gray-200';
    
    const statusLower = status.toLowerCase();
    switch (statusLower) {
      case 'approved':
        return 'bg-green-100 text-green-800 border border-green-200';
      case 'interviewed':
        return 'bg-blue-100 text-blue-800 border border-blue-200';
      case 'paying':
        return 'bg-purple-100 text-purple-800 border border-purple-200';
      case 'paid':
        return 'bg-indigo-100 text-indigo-800 border border-indigo-200';
      case 'pending':
        return 'bg-yellow-100 text-yellow-800 border border-yellow-200';
      case 'need_correction':
        return 'bg-red-100 text-red-800 border border-red-200';
      case 'resubmitted':
        return 'bg-orange-100 text-orange-800 border border-orange-200';
      case 'rejected':
        return 'bg-gray-100 text-gray-800 border border-gray-200';
      default:
        return 'bg-gray-100 text-gray-800 border border-gray-200';
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
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2
    }).format(num);
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

  return (
    <div className='mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg'>
      <div className="mb-8">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
          <div>
            <h1 className="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Market Validation</h1>
            <p className="text-gray-600 dark:text-gray-300 mt-1">Review market stall applications (Excluding Approved)</p>
          </div>
          <div className="flex gap-2">
            <button
              onClick={fetchApplications}
              disabled={loading}
              className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clipRule="evenodd" />
              </svg>
              Refresh
            </button>
            
            <select
              value={filterStatus}
              onChange={(e) => {
                setFilterStatus(e.target.value);
                setCurrentPage(1);
              }}
              className="border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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

        {/* Stats Grid */}
        <div className="grid grid-cols-2 md:grid-cols-7 gap-4 mb-6">
          <div className="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm border-l-4 border-l-yellow-500">
            <div className="text-sm text-gray-600 dark:text-gray-300">Pending</div>
            <div className="text-2xl font-bold text-yellow-600 dark:text-yellow-500">{stats.pending}</div>
            <div className="text-xs text-gray-500 dark:text-gray-400">Awaiting Interview</div>
          </div>
          
          <div className="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm border-l-4 border-l-blue-500">
            <div className="text-sm text-gray-600 dark:text-gray-300">Interviewed</div>
            <div className="text-2xl font-bold text-blue-600 dark:text-blue-500">{stats.interviewed}</div>
            <div className="text-xs text-gray-500 dark:text-gray-400">Interview Done</div>
          </div>
          
          <div className="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm border-l-4 border-l-purple-500">
            <div className="text-sm text-gray-600 dark:text-gray-300">Paying</div>
            <div className="text-2xl font-bold text-purple-600 dark:text-purple-500">{stats.paying}</div>
            <div className="text-xs text-gray-500 dark:text-gray-400">Payment Required</div>
          </div>
          
          <div className="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm border-l-4 border-l-indigo-500">
            <div className="text-sm text-gray-600 dark:text-gray-300">Paid</div>
            <div className="text-2xl font-bold text-indigo-600 dark:text-indigo-500">{stats.paid}</div>
            <div className="text-xs text-gray-500 dark:text-gray-400">Payment Completed</div>
          </div>
          
          <div className="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm border-l-4 border-l-red-500">
            <div className="text-sm text-gray-600 dark:text-gray-300">Need Correction</div>
            <div className="text-2xl font-bold text-red-600 dark:text-red-500">{stats.need_correction}</div>
            <div className="text-xs text-gray-500 dark:text-gray-400">Needs Correction</div>
          </div>
          
          <div className="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm border-l-4 border-l-orange-500">
            <div className="text-sm text-gray-600 dark:text-gray-300">Resubmitted</div>
            <div className="text-2xl font-bold text-orange-600 dark:text-orange-500">{stats.resubmitted}</div>
            <div className="text-xs text-gray-500 dark:text-gray-400">Resubmitted</div>
          </div>
          
          <div className="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm border-l-4 border-l-gray-500">
            <div className="text-sm text-gray-600 dark:text-gray-300">Rejected</div>
            <div className="text-2xl font-bold text-gray-600 dark:text-gray-500">{stats.rejected}</div>
            <div className="text-xs text-gray-500 dark:text-gray-400">Rejected</div>
          </div>
        </div>
      </div>

      {/* Controls */}
      <div className="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm mb-6">
        <div className="flex flex-col lg:flex-row gap-4">
          {/* Search */}
          <div className="flex-1">
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clipRule="evenodd" />
                </svg>
              </div>
              <input
                type="text"
                placeholder="Search applications by stall name, applicant name, business name..."
                value={searchTerm}
                onChange={(e) => {
                  setSearchTerm(e.target.value);
                  setCurrentPage(1);
                }}
                className="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>

          {/* Items per page */}
          <div>
            <select
              value={itemsPerPage}
              onChange={(e) => {
                setItemsPerPage(parseInt(e.target.value));
                setCurrentPage(1);
              }}
              className="border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="10">10 per page</option>
              <option value="25">25 per page</option>
              <option value="50">50 per page</option>
            </select>
          </div>
        </div>
      </div>

      {/* Error Alert */}
      {error && (
        <div className="mb-6 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg">
          <div className="flex items-start">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-red-400 mr-3 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
            </svg>
            <div>
              <p className="text-red-800 dark:text-red-200 font-medium">Error Loading Data</p>
              <p className="text-red-700 dark:text-red-300 text-sm">{error}</p>
              <button 
                onClick={fetchApplications}
                className="mt-2 px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700"
              >
                Retry Loading
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Loading State */}
      {loading ? (
        <div className="flex flex-col items-center justify-center py-12">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-600"></div>
          <p className="mt-4 text-gray-600 dark:text-gray-300">Loading market applications...</p>
        </div>
      ) : (
        <>
          {/* Results Summary */}
          <div className="mb-4 text-sm text-gray-600 dark:text-gray-300">
            {filteredApplications.length > 0 ? (
              `Showing ${startIndex + 1}-${Math.min(endIndex, filteredApplications.length)} of ${filteredApplications.length} applications`
            ) : (
              'No applications to display'
            )}
            <span className="text-blue-600 dark:text-blue-400 ml-2">(Approved applications are hidden)</span>
          </div>

          {/* Applications Table */}
          {paginatedApplications.length > 0 ? (
            <div className="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                  <thead className="bg-gray-50 dark:bg-gray-900">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Application Details
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Applicant Info
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Business & Stall
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Financial Details
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Status & Dates
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    {paginatedApplications.map((app) => (
                      <tr key={app.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td className="px-6 py-4">
                          <div>
                            <div className="font-mono text-blue-600 dark:text-blue-400 font-bold">{app.stall_rights_no}</div>
                            <div className="text-sm text-gray-500 dark:text-gray-400">Application ID</div>
                            <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                              Applied: {formatDate(app.created_at)}
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div>
                            <div className="font-medium text-gray-900 dark:text-white">
                              {app.first_name} {app.last_name}
                            </div>
                            <div className="text-sm text-gray-500 dark:text-gray-400">{app.email}</div>
                            <div className="text-sm text-gray-600 dark:text-gray-300 mt-1">{app.mobile}</div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div>
                            <div className="font-medium text-gray-900 dark:text-white">{app.business_name}</div>
                            <div className="text-sm text-gray-500 dark:text-gray-400">{app.business_type}</div>
                            <div className="mt-2">
                              <div className="text-gray-600 dark:text-gray-300">
                                <span className="font-medium">Stall:</span> {app.stall_name}
                              </div>
                              <div className="text-xs text-gray-500 dark:text-gray-400">
                                Class: {app.stall_class}
                              </div>
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="space-y-1">
                            <div className="flex justify-between text-sm">
                              <span className="text-gray-600 dark:text-gray-300">Monthly Rent:</span>
                              <span className="font-semibold">{formatCurrency(app.monthly_rent)}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                              <span className="text-gray-600 dark:text-gray-300">Total Due:</span>
                              <span className="font-bold text-blue-700 dark:text-blue-400">{formatCurrency(app.total_amount_due)}</span>
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div>
                            <span className={`inline-flex px-3 py-1 text-xs font-semibold rounded-full ${getStatusColor(app.application_status)}`}>
                              {getStatusText(app.application_status)}
                            </span>
                            <div className="mt-2 text-xs space-y-1">
                              {app.interview_date && (
                                <div className="text-gray-600 dark:text-gray-300">
                                  Interview: {formatDate(app.interview_date)}
                                </div>
                              )}
                              {app.payment_date && (
                                <div className="text-gray-600 dark:text-gray-300">
                                  Payment: {formatDate(app.payment_date)}
                                </div>
                              )}
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex flex-col gap-2">
                            <Link
                              to={`/market/marketvalidationinfo/${app.id}`}
                              className="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path fillRule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clipRule="evenodd" />
                              </svg>
                              View Details
                            </Link>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            <div className="bg-white dark:bg-gray-800 p-8 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm text-center">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-16 w-16 text-yellow-400 mx-auto mb-4" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 000 2h6a1 1 0 100-2H7z" clipRule="evenodd" />
              </svg>
              <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-2">
                {searchTerm || filterStatus !== 'all' ? 'No matching applications found' : 'No applications'}
              </h3>
              <p className="text-gray-600 dark:text-gray-300 max-w-md mx-auto">
                {searchTerm 
                  ? 'Try adjusting your search criteria.'
                  : filterStatus === 'all'
                    ? 'There are currently no market applications (approved applications are hidden).'
                    : `No applications with status "${filterStatus}" found.`}
              </p>
              <p className="text-blue-600 dark:text-blue-400 mt-2">Note: Approved applications are not shown in this list.</p>
              <div className="mt-4 flex gap-2 justify-center">
                <button
                  onClick={fetchApplications}
                  className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                >
                  Refresh Data
                </button>
                {filterStatus !== 'all' && (
                  <button
                    onClick={() => setFilterStatus('all')}
                    className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
                  >
                    Show All Statuses
                  </button>
                )}
              </div>
            </div>
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="mt-6 flex flex-col sm:flex-row justify-between items-center space-y-4 sm:space-y-0">
              <div className="text-sm text-gray-600 dark:text-gray-300">
                Page {currentPage} of {totalPages}
              </div>
              <div className="flex items-center space-x-2">
                <button
                  onClick={() => handlePageChange(currentPage - 1)}
                  disabled={currentPage === 1}
                  className="px-4 py-2 border border-gray-300 dark:border-gray-600 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Previous
                </button>
                
                <div className="flex items-center space-x-1">
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
                            ? 'bg-blue-600 text-white'
                            : 'border border-gray-300 dark:border-gray-600 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-700'
                        }`}
                      >
                        {pageNumber}
                      </button>
                    );
                  })}
                </div>
                
                <button
                  onClick={() => handlePageChange(currentPage + 1)}
                  disabled={currentPage === totalPages}
                  className="px-4 py-2 border border-gray-300 dark:border-gray-600 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
};

export default MarketValidation;