import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';

const BusinessValidation = () => {
  const [permits, setPermits] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage, setItemsPerPage] = useState(10);
  // Set default filter to 'Pending'
  const [filterStatus, setFilterStatus] = useState('Pending');

  // Determine environment
  const isLocalhost = window.location.hostname === 'localhost' || 
                      window.location.hostname === '127.0.0.1' ||
                      window.location.hostname === '';
  const API_BASE = isLocalhost
    ? "http://localhost/revenue2/backend/Business/BusinessValidation"
    : "/backend/Business/BusinessValidation";

  // Fetch permits data
  const fetchPermits = async () => {
    try {
      setLoading(true);
      setError('');
      
      const response = await fetch(`${API_BASE}/get_permits.php`, {
        headers: {
          'Cache-Control': 'no-cache',
          'Pragma': 'no-cache'
        }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.status === 'success') {
        setPermits(data.permits);
      } else {
        throw new Error(data.message || 'Failed to fetch permits');
      }
    } catch (err) {
      console.error('Error fetching permits:', err);
      setError(err.message || 'Failed to load business permits. Please try again.');
      setPermits([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPermits();
  }, []);

  // Filter permits - only show pending by default
  const filteredPermits = permits.filter(permit => {
    const searchLower = searchTerm.toLowerCase();
    const matchesSearch = 
      (permit.business_name || '').toLowerCase().includes(searchLower) ||
      (permit.owner_name || '').toLowerCase().includes(searchLower) ||
      (permit.business_permit_id || '').toLowerCase().includes(searchLower) ||
      (permit.business_type || '').toLowerCase().includes(searchLower) ||
      (permit.barangay || '').toLowerCase().includes(searchLower) ||
      (permit.city || '').toLowerCase().includes(searchLower);
    
    // Only show pending permits by default
    const matchesStatus = filterStatus === 'all' || permit.status === filterStatus;
    
    return matchesSearch && matchesStatus;
  });

  // Pagination calculations
  const totalPages = Math.ceil(filteredPermits.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const paginatedPermits = filteredPermits.slice(startIndex, endIndex);

  // Handle page change
  const handlePageChange = (page) => {
    setCurrentPage(page);
  };

  // Get status color
  const getStatusColor = (status) => {
    switch (status) {
      case 'Active':
        return 'bg-green-100 text-green-800 border border-green-200';
      case 'Approved':
        return 'bg-blue-100 text-blue-800 border border-blue-200';
      case 'Pending':
        return 'bg-yellow-100 text-yellow-800 border border-yellow-200';
      case 'Expired':
        return 'bg-red-100 text-red-800 border border-red-200';
      default:
        return 'bg-gray-100 text-gray-800 border border-gray-200';
    }
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

  // Format date with time
  const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    try {
      return new Date(dateString).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    } catch (e) {
      return 'Invalid Date';
    }
  };

  // Calculate statistics - ONLY FOR PENDING
  const stats = {
    pending: permits.filter(p => p.status === 'Pending').length,
    approved: permits.filter(p => p.status === 'Approved').length,
    active: permits.filter(p => p.status === 'Active').length,
    expired: permits.filter(p => p.status === 'Expired').length,
    total: permits.length
  };

  // Handle approval action
  const handleApprove = async (permitId) => {
    if (window.confirm('Are you sure you want to approve this business permit?')) {
      try {
        const response = await fetch(`${API_BASE}/approve_permit.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ id: permitId })
        });

        const data = await response.json();
        
        if (data.status === 'success') {
          alert('Business permit approved successfully!');
          fetchPermits(); // Refresh the list
        } else {
          alert('Failed to approve: ' + data.message);
        }
      } catch (err) {
        console.error('Error approving permit:', err);
        alert('Error approving business permit');
      }
    }
  };

  // Handle rejection action
  const handleReject = async (permitId) => {
    const reason = window.prompt('Please enter reason for rejection:');
    if (reason) {
      try {
        const response = await fetch(`${API_BASE}/reject_permit.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ 
            id: permitId,
            reason: reason 
          })
        });

        const data = await response.json();
        
        if (data.status === 'success') {
          alert('Business permit rejected successfully!');
          fetchPermits(); // Refresh the list
        } else {
          alert('Failed to reject: ' + data.message);
        }
      } catch (err) {
        console.error('Error rejecting permit:', err);
        alert('Error rejecting business permit');
      }
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 p-4 md:p-6">
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
          <div>
            <h1 className="text-2xl md:text-3xl font-bold text-gray-900">Pending Business Permit Applications</h1>
            <p className="text-gray-600 mt-1">Review and validate new business applications</p>
          </div>
          <div className="flex gap-2">
            <button
              onClick={fetchPermits}
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
              className="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="Pending">Pending Review</option>
              <option value="all">All Applications</option>
              <option value="Approved">Approved</option>
              <option value="Active">Active</option>
              <option value="Expired">Expired</option>
            </select>
          </div>
        </div>

        {/* Stats Grid - Focus on pending */}
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
          <div className="bg-white p-4 rounded-lg border border-gray-200 shadow-sm border-l-4 border-l-yellow-500">
            <div className="text-sm text-gray-600">Pending Review</div>
            <div className="text-2xl font-bold text-yellow-600">{stats.pending}</div>
            <div className="text-xs text-gray-500">Awaiting Validation</div>
          </div>
          
          <div className="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
            <div className="text-sm text-gray-600">Approved</div>
            <div className="text-2xl font-bold text-blue-600">{stats.approved}</div>
            <div className="text-xs text-gray-500">Recently Approved</div>
          </div>
          
          <div className="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
            <div className="text-sm text-gray-600">Active</div>
            <div className="text-2xl font-bold text-green-600">{stats.active}</div>
            <div className="text-xs text-gray-500">Current Businesses</div>
          </div>
          
          <div className="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
            <div className="text-sm text-gray-600">Expired</div>
            <div className="text-2xl font-bold text-red-600">{stats.expired}</div>
            <div className="text-xs text-gray-500">Needs Renewal</div>
          </div>
          
          <div className="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
            <div className="text-sm text-gray-600">Total</div>
            <div className="text-2xl font-bold text-gray-900">{stats.total}</div>
            <div className="text-xs text-gray-500">All Applications</div>
          </div>
        </div>
      </div>

      {/* Controls */}
      <div className="bg-white p-4 rounded-lg border border-gray-200 shadow-sm mb-6">
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
                placeholder="Search pending applications by business name, owner, permit ID..."
                value={searchTerm}
                onChange={(e) => {
                  setSearchTerm(e.target.value);
                  setCurrentPage(1);
                }}
                className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
              className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <div className="flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-red-400 mr-3" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
            </svg>
            <div>
              <p className="text-red-800 font-medium">Error Loading Data</p>
              <p className="text-red-700 text-sm">{error}</p>
            </div>
          </div>
        </div>
      )}

      {/* Loading State */}
      {loading ? (
        <div className="flex flex-col items-center justify-center py-12">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-600"></div>
          <p className="mt-4 text-gray-600">Loading business permits...</p>
        </div>
      ) : (
        <>
          {/* Results Summary */}
          <div className="mb-4 text-sm text-gray-600">
            Showing {startIndex + 1}-{Math.min(endIndex, filteredPermits.length)} of {filteredPermits.length} pending applications
          </div>

          {/* Permits Table */}
          {paginatedPermits.length > 0 ? (
            <div className="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Application Details
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Business Info
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Owner Details
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Tax Calculation
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status & Dates
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {paginatedPermits.map((permit) => (
                      <tr key={permit.id} className="hover:bg-gray-50">
                        <td className="px-6 py-4">
                          <div>
                            <div className="font-mono text-blue-600 font-bold">{permit.business_permit_id}</div>
                            <div className="text-sm text-gray-500">Application ID</div>
                            <div className="mt-2 text-xs text-gray-500">
                              Created: {formatDateTime(permit.created_at)}
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div>
                            <div className="font-medium text-gray-900">{permit.business_name}</div>
                            <div className="text-sm text-gray-500">{permit.business_type}</div>
                            <div className="text-sm mt-1">
                              <div className="text-gray-600">
                                {permit.barangay}, {permit.city}
                              </div>
                              {permit.street && (
                                <div className="text-xs text-gray-500">{permit.street}</div>
                              )}
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div>
                            <div className="font-medium text-gray-900">{permit.owner_name}</div>
                            {permit.contact_number && (
                              <div className="text-sm text-gray-600">{permit.contact_number}</div>
                            )}
                            {permit.owner_email && (
                              <div className="text-sm text-gray-500">{permit.owner_email}</div>
                            )}
                            <div className="mt-1 text-xs text-gray-500">
                              {permit.district} District
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div>
                            <div className="text-sm">
                              <span className="font-medium">Type:</span> {permit.tax_calculation_type}
                            </div>
                            {permit.taxable_amount > 0 && (
                              <div className="text-sm">
                                <span className="font-medium">Amount:</span> {formatCurrency(permit.taxable_amount)}
                              </div>
                            )}
                            {permit.tax_rate > 0 && (
                              <div className="text-sm">
                                <span className="font-medium">Rate:</span> {permit.tax_rate}%
                              </div>
                            )}
                            {permit.total_tax > 0 && (
                              <div className="mt-1">
                                <div className="font-bold text-blue-600">{formatCurrency(permit.total_tax)}</div>
                                <div className="text-xs text-gray-500">Total Tax & Fees</div>
                              </div>
                            )}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <span className={`inline-flex px-3 py-1 text-xs font-semibold rounded-full ${getStatusColor(permit.status)}`}>
                            {permit.status}
                          </span>
                          <div className="mt-2 text-xs">
                            {permit.issue_date && (
                              <div className="text-gray-600">
                                <span className="font-medium">Issued:</span> {formatDate(permit.issue_date)}
                              </div>
                            )}
                            {permit.expiry_date && (
                              <div className="text-gray-600">
                                <span className="font-medium">Expires:</span> {formatDate(permit.expiry_date)}
                              </div>
                            )}
                            <div className="text-gray-500 mt-1">
                              Last updated: {formatDateTime(permit.updated_at)}
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex flex-col gap-2">
                            <Link
                              to={`/business/businessvalidationinfo/${permit.id}`}
                              className="inline-flex items-center justify-center px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path fillRule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clipRule="evenodd" />
                              </svg>
                              Review Details
                            </Link>
                            
                            {permit.status === 'Pending' && (
                              <div className="flex gap-2 mt-2">
                                <button
                                  onClick={() => handleApprove(permit.id)}
                                  className="flex-1 inline-flex items-center justify-center px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700"
                                >
                                  <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                  </svg>
                                  Approve
                                </button>
                                
                                <button
                                  onClick={() => handleReject(permit.id)}
                                  className="flex-1 inline-flex items-center justify-center px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700"
                                >
                                  <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                                  </svg>
                                  Reject
                                </button>
                              </div>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            <div className="bg-white p-8 rounded-lg border border-gray-200 shadow-sm text-center">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-16 w-16 text-yellow-400 mx-auto mb-4" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 000 2h6a1 1 0 100-2H7z" clipRule="evenodd" />
              </svg>
              <h3 className="text-lg font-medium text-gray-900 mb-2">
                {searchTerm || filterStatus !== 'all' ? 'No matching applications found' : 'No pending applications'}
              </h3>
              <p className="text-gray-600 max-w-md mx-auto">
                {searchTerm 
                  ? 'Try adjusting your search criteria.'
                  : filterStatus === 'Pending' 
                    ? 'There are currently no pending business permit applications.'
                    : 'No applications found with the selected status.'}
              </p>
            </div>
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="mt-6 flex flex-col sm:flex-row justify-between items-center space-y-4 sm:space-y-0">
              <div className="text-sm text-gray-600">
                Page {currentPage} of {totalPages}
              </div>
              <div className="flex items-center space-x-2">
                <button
                  onClick={() => handlePageChange(currentPage - 1)}
                  disabled={currentPage === 1}
                  className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
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
                            : 'border border-gray-300 hover:bg-gray-50'
                        }`}
                      >
                        {pageNumber}
                      </button>
                    );
                  })}
                  
                  {totalPages > 5 && currentPage < totalPages - 2 && (
                    <>
                      <span className="px-2 text-gray-500">...</span>
                      <button
                        onClick={() => handlePageChange(totalPages)}
                        className="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50"
                      >
                        {totalPages}
                      </button>
                    </>
                  )}
                </div>
                
                <button
                  onClick={() => handlePageChange(currentPage + 1)}
                  disabled={currentPage === totalPages}
                  className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </>
      )}

      {/* Footer Info */}
      <div className="mt-8 pt-6 border-t border-gray-200 text-center text-sm text-gray-500">
        <p>Business Permit Validation System • {new Date().toLocaleDateString('en-PH', { 
          weekday: 'long', 
          year: 'numeric', 
          month: 'long', 
          day: 'numeric' 
        })}</p>
      </div>
    </div>
  );
};

export default BusinessValidation;