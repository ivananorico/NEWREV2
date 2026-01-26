import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';

const BusinessValidation = () => {
  const [activeTab, setActiveTab] = useState('myData');
  const [myPermits, setMyPermits] = useState([]);
  const [externalPermits, setExternalPermits] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadingExternal, setLoadingExternal] = useState(false);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage, setItemsPerPage] = useState(10);
  const [filterStatus, setFilterStatus] = useState('PENDING');
  const [isImporting, setIsImporting] = useState(false);
  const [selectedPermits, setSelectedPermits] = useState([]);
  const [importResult, setImportResult] = useState(null);
  const [alreadyImportedIds, setAlreadyImportedIds] = useState([]);

  const isLocalhost = window.location.hostname === 'localhost' || 
                      window.location.hostname === '127.0.0.1';
  
  const API_BASE = isLocalhost
    ? "http://localhost/revenue2/backend/Business/BusinessValidation"
    : "/backend/Business/BusinessValidation";

  // Use proxy for external API
  const EXTERNAL_API_URL = `${API_BASE}/fetch_external_proxy.php`;

  // Fetch data from YOUR database
  const fetchMyPermits = async () => {
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
        const errorText = await response.text();
        console.error('Server response:', errorText);
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.status === 'success') {
        setMyPermits(data.permits || []);
        console.log(`Loaded ${data.permits?.length || 0} permits from database`);
        console.log('First permit:', data.permits?.[0]);
        
        // Extract already imported IDs for filtering external data
        const importedIds = data.permits?.map(p => p.applicant_id) || [];
        setAlreadyImportedIds(importedIds);
        console.log('Already imported IDs:', importedIds);
      } else {
        throw new Error(data.message || 'Failed to fetch permits');
      }
    } catch (err) {
      console.error('Error fetching permits:', err);
      setError('Failed to load business permits: ' + err.message);
      setMyPermits([]);
    } finally {
      setLoading(false);
    }
  };

  // Fetch data from CLASSMATE's system using proxy
  const fetchExternalPermits = async () => {
    try {
      setLoadingExternal(true);
      setError('');
      
      console.log('Fetching from proxy URL:', EXTERNAL_API_URL);
      
      const response = await fetch(EXTERNAL_API_URL);
      
      if (!response.ok) {
        const errorText = await response.text();
        console.error('Proxy error response:', errorText);
        throw new Error(`Proxy error! status: ${response.status}`);
      }
      
      const data = await response.json();
      console.log('External data received:', data);
      
      if (data.success) {
        // Filter out already imported permits BEFORE setting state
        const externalData = data.data || [];
        const filteredData = externalData.filter(permit => {
          const permitId = permit.applicant_id || permit.permit_id;
          return !alreadyImportedIds.includes(permitId);
        });
        
        setExternalPermits(filteredData);
        setSelectedPermits([]);
        console.log(`Fetched ${externalData.length} permits, filtered to ${filteredData.length} (removed ${externalData.length - filteredData.length} already imported)`);
      } else {
        throw new Error(data.message || 'Failed to fetch data from permit system');
      }
    } catch (err) {
      console.error('Error fetching external permits:', err);
      setError('Failed to load permits from external system: ' + err.message);
      setExternalPermits([]);
    } finally {
      setLoadingExternal(false);
    }
  };

  useEffect(() => {
    if (activeTab === 'myData') {
      fetchMyPermits();
    }
  }, [activeTab]);

  // Function to import selected permits
  const importSelectedPermits = async () => {
    if (selectedPermits.length === 0) {
      setError('Please select at least one permit to import');
      return;
    }
    
    setIsImporting(true);
    setImportResult(null);
    setError('');
    
    try {
      // Get the selected permits
      const permitsToImport = externalPermits.filter(permit => {
        const permitId = permit.applicant_id || permit.permit_id;
        return selectedPermits.includes(permitId);
      });
      
      console.log('Selected permits for import:', permitsToImport.length);
      console.log('First selected permit:', permitsToImport[0]);
      
      // Transform the data to match backend expectations
      const transformedPermits = permitsToImport.map(permit => ({
        applicant_id: permit.applicant_id || permit.permit_id || `EXT-${Date.now()}`,
        business_name: permit.business_name || '',
        owner_last_name: permit.last_name || permit.owner_last_name || '',
        owner_first_name: permit.first_name || permit.owner_first_name || '',
        owner_middle_name: permit.middle_name || permit.owner_middle_name || '',
        full_name: permit.full_name || `${permit.first_name || ''} ${permit.middle_name || ''} ${permit.last_name || ''}`.trim(),
        owner_type: permit.owner_type || 'Individual',
        business_nature: permit.business_nature || '',
        trade_name: permit.trade_name || null,
        barangay: permit.barangay || '',
        district: permit.district || '',
        city_municipality: permit.city_municipality || permit.city || '',
        province: permit.province || '',
        zip_code: permit.zip_code || '',
        contact_number: permit.contact_number || '',
        email_address: permit.email_address || '',
        capital_investment: parseFloat(permit.capital_investment) || 0,
        application_date: permit.application_date || new Date().toISOString().split('T')[0],
        business_area: permit.business_area || null,
        status: permit.status || 'PENDING'
      }));
      
      console.log('Transformed permits ready for import:', transformedPermits);
      
      const importResponse = await fetch(`${API_BASE}/import_external_permits.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          permits: transformedPermits,
          import_date: new Date().toISOString(),
          source: 'external_permit_system'
        })
      });
      
      const importData = await importResponse.json();
      console.log('Import response:', importData);
      
      if (importData.status === 'success') {
        setImportResult({
          success: true,
          message: `Successfully imported ${importData.imported_count || 0} business permits. ${importData.skipped_count || 0} were already in database.`,
          details: importData.details || '',
          imported_count: importData.imported_count || 0,
          skipped_count: importData.skipped_count || 0,
          error_count: importData.error_count || 0
        });
        
        // Update already imported IDs
        const newlyImportedIds = permitsToImport.map(p => p.applicant_id || p.permit_id);
        setAlreadyImportedIds(prev => [...prev, ...newlyImportedIds]);
        
        // Remove imported permits from external view
        setExternalPermits(prev => 
          prev.filter(permit => !selectedPermits.includes(permit.applicant_id || permit.permit_id))
        );
        
        setSelectedPermits([]);
        
        // Auto-switch to my data tab after 1.5 seconds
        setTimeout(() => {
          setActiveTab('myData');
          fetchMyPermits();
        }, 1500);
      } else {
        throw new Error(importData.message || 'Import failed');
      }
    } catch (err) {
      console.error('Import error:', err);
      setError('Import failed: ' + err.message);
      setImportResult({
        success: false,
        message: err.message || 'Failed to import permits',
        error_details: err.toString()
      });
    } finally {
      setIsImporting(false);
    }
  };

  // Helper functions
  const toggleSelectPermit = (permitId) => {
    setSelectedPermits(prev => {
      if (prev.includes(permitId)) {
        return prev.filter(id => id !== permitId);
      } else {
        return [...prev, permitId];
      }
    });
  };

  const selectAllPermits = () => {
    const allIds = externalPermits
      .map(permit => permit.applicant_id || permit.permit_id)
      .filter(id => id); // Remove undefined/null IDs
    setSelectedPermits(allIds);
  };

  const deselectAllPermits = () => {
    setSelectedPermits([]);
  };

  // Filter and pagination for MY DATA
  const filteredMyPermits = myPermits.filter(permit => {
    const searchLower = searchTerm.toLowerCase();
    const matchesSearch = 
      (permit.business_name || '').toLowerCase().includes(searchLower) ||
      (permit.owner_name || permit.owner_full_name || '').toLowerCase().includes(searchLower) ||
      (permit.applicant_id || '').toLowerCase().includes(searchLower) ||
      (permit.business_type || permit.business_nature || '').toLowerCase().includes(searchLower) ||
      (permit.barangay || permit.business_barangay || '').toLowerCase().includes(searchLower);
    
    const permitStatus = permit.status || permit.permit_status || 'PENDING';
    const matchesStatus = filterStatus === 'all' || permitStatus === filterStatus;
    
    return matchesSearch && matchesStatus;
  });

  const totalPages = Math.ceil(filteredMyPermits.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const paginatedPermits = filteredMyPermits.slice(startIndex, endIndex);

  const handlePageChange = (page) => {
    setCurrentPage(page);
  };

  // Fix: Use permit_status from database (your data has permit_status, not status)
  const getStatusColor = (status) => {
    const statusValue = status || 'PENDING';
    switch (statusValue.toUpperCase()) {
      case 'ACTIVE':
        return 'bg-green-100 text-green-800 border border-green-200';
      case 'APPROVED':
        return 'bg-blue-100 text-blue-800 border border-blue-200';
      case 'PENDING':
        return 'bg-yellow-100 text-yellow-800 border border-yellow-200';
      case 'EXPIRED':
        return 'bg-red-100 text-red-800 border border-red-200';
      case 'RENEWED':
        return 'bg-purple-100 text-purple-800 border border-purple-200';
      default:
        return 'bg-gray-100 text-gray-800 border border-gray-200';
    }
  };

  const formatCurrency = (amount) => {
    const num = parseFloat(amount) || 0;
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2
    }).format(num);
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
      return new Date(dateString).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      });
    } catch (e) {
      return dateString || 'N/A';
    }
  };

  const getTaxTypeDisplay = (permit) => {
    const taxType = permit.tax_calculation_type || 'capital_investment';
    const amount = parseFloat(permit.taxable_amount || permit.capital_investment) || 0;
    
    if (taxType === 'capital_investment') {
      return {
        label: 'Capital Investment',
        amount: amount,
        icon: '💼',
        color: 'text-purple-600'
      };
    } else {
      return {
        label: 'Gross Sales',
        amount: amount,
        icon: '📈',
        color: 'text-green-600'
      };
    }
  };

  const stats = {
    pending: myPermits.filter(p => (p.status === 'PENDING' || p.permit_status === 'PENDING')).length,
    approved: myPermits.filter(p => (p.status === 'APPROVED' || p.permit_status === 'APPROVED')).length,
    active: myPermits.filter(p => (p.status === 'ACTIVE' || p.permit_status === 'ACTIVE')).length,
    expired: myPermits.filter(p => (p.status === 'EXPIRED' || p.permit_status === 'EXPIRED')).length,
    total: myPermits.length
  };

  return (
    <div className="min-h-screen bg-gray-50 p-4 md:p-6">
      {/* Header with Tabs */}
      <div className="mb-8">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
          <div>
            <h1 className="text-2xl md:text-3xl font-bold text-gray-900">Business Permit Management</h1>
            <p className="text-gray-600 mt-1">Manage business permits and tax calculations</p>
          </div>
          
          <button
            onClick={activeTab === 'myData' ? fetchMyPermits : fetchExternalPermits}
            disabled={loading || (activeTab === 'fetchData' && loadingExternal)}
            className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
              <path fillRule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clipRule="evenodd" />
            </svg>
            Refresh {activeTab === 'myData' ? 'My Data' : 'External Data'}
          </button>
        </div>

        {/* Import Result Alert */}
        {importResult && (
          <div className={`mb-4 p-4 rounded-lg ${importResult.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}`}>
            <div className="flex items-start">
              {importResult.success ? (
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-green-400 mr-3 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                </svg>
              ) : (
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-red-400 mr-3 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                </svg>
              )}
              <div className="flex-1">
                <p className={`font-medium ${importResult.success ? 'text-green-800' : 'text-red-800'}`}>
                  {importResult.success ? 'Import Successful' : 'Import Failed'}
                </p>
                <p className={`text-sm ${importResult.success ? 'text-green-700' : 'text-red-700'}`}>
                  {importResult.message}
                </p>
                {importResult.imported_count >= 0 && (
                  <div className="mt-2 text-xs text-gray-600">
                    <span className="font-medium">Details:</span> Imported: {importResult.imported_count || 0}, 
                    Skipped: {importResult.skipped_count || 0}, 
                    Errors: {importResult.error_count || 0}
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Error Alert */}
        {error && (
          <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div className="flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-red-400 mr-3" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
              </svg>
              <div>
                <p className="text-red-800 font-medium">Error</p>
                <p className="text-red-700 text-sm">{error}</p>
              </div>
            </div>
          </div>
        )}

        {/* Tabs */}
        <div className="mb-6">
          <div className="flex space-x-2 border-b border-gray-200">
            <button
              onClick={() => {
                setActiveTab('myData');
                setSearchTerm('');
                setCurrentPage(1);
              }}
              className={`px-6 py-3 text-sm font-medium rounded-t-lg transition-colors ${
                activeTab === 'myData'
                  ? 'bg-white border border-gray-200 border-b-0 text-blue-600'
                  : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
              }`}
            >
              <div className="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clipRule="evenodd" />
                </svg>
                My Database ({stats.total})
              </div>
            </button>
            
            <button
              onClick={() => {
                setActiveTab('fetchData');
                setSearchTerm('');
                if (externalPermits.length === 0 && !loadingExternal) {
                  fetchExternalPermits();
                }
              }}
              className={`px-6 py-3 text-sm font-medium rounded-t-lg transition-colors ${
                activeTab === 'fetchData'
                  ? 'bg-white border border-gray-200 border-b-0 text-blue-600'
                  : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
              }`}
            >
              <div className="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clipRule="evenodd" />
                </svg>
                Fetch from Permit System ({externalPermits.length})
              </div>
            </button>
          </div>
        </div>

        {/* Stats Grid for My Data tab */}
        {activeTab === 'myData' && (
          <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div className="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
              <div className="text-sm text-gray-600">Pending Review</div>
              <div className="text-2xl font-bold text-yellow-600">{stats.pending}</div>
              <div className="text-xs text-gray-500">Awaiting Action</div>
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
        )}
      </div>

      {/* Tab Content */}
      {activeTab === 'myData' ? (
        /* MY DATA TAB CONTENT */
        <>
          {/* Search & Controls */}
          <div className="bg-white p-4 rounded-lg border border-gray-200 shadow-sm mb-6">
            <div className="flex flex-col lg:flex-row gap-4">
              <div className="flex-1">
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                      <path fillRule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clipRule="evenodd" />
                    </svg>
                  </div>
                  <input
                    type="text"
                    placeholder="Search by business name, owner, ID, or location..."
                    value={searchTerm}
                    onChange={(e) => {
                      setSearchTerm(e.target.value);
                      setCurrentPage(1);
                    }}
                    className="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>
              </div>
              
              <div className="flex gap-2">
                <select
                  value={filterStatus}
                  onChange={(e) => {
                    setFilterStatus(e.target.value);
                    setCurrentPage(1);
                  }}
                  className="border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                >
                  <option value="PENDING">Pending Review</option>
                  <option value="all">All Statuses</option>
                  <option value="APPROVED">Approved</option>
                  <option value="ACTIVE">Active</option>
                  <option value="EXPIRED">Expired</option>
                  <option value="RENEWED">Renewed</option>
                </select>
                
                <select
                  value={itemsPerPage}
                  onChange={(e) => {
                    setItemsPerPage(parseInt(e.target.value));
                    setCurrentPage(1);
                  }}
                  className="border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                >
                  <option value="10">10 per page</option>
                  <option value="25">25 per page</option>
                  <option value="50">50 per page</option>
                </select>
              </div>
            </div>
          </div>

          {/* Loading State */}
          {loading ? (
            <div className="flex flex-col items-center justify-center py-12">
              <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-600"></div>
              <p className="mt-4 text-gray-600">Loading business permits...</p>
            </div>
          ) : (
            <>
              {/* Results Summary */}
              <div className="mb-4 flex justify-between items-center">
                <div className="text-sm text-gray-600">
                  Showing {startIndex + 1}-{Math.min(endIndex, filteredMyPermits.length)} of {filteredMyPermits.length} applications
                </div>
                <div className="text-sm text-gray-500">
                  Filter: {filterStatus === 'all' ? 'All Statuses' : filterStatus}
                </div>
              </div>

              {/* Permits Table - FIXED DATA MAPPING */}
              {paginatedPermits.length > 0 ? (
                <div className="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Business Info
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Owner
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Location
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Tax Information
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Action
                          </th>
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {paginatedPermits.map((permit) => {
                          const taxInfo = getTaxTypeDisplay(permit);
                          // FIX: Use permit_status from database
                          const status = permit.permit_status || permit.status || 'PENDING';
                          const ownerName = permit.owner_name || permit.owner_full_name || 'Unknown';
                          const businessType = permit.business_type || permit.business_nature || 'Unknown';
                          const barangay = permit.barangay || permit.business_barangay || '';
                          const city = permit.city || permit.business_city || '';
                          const district = permit.district || permit.business_district || '';
                          
                          return (
                            <tr key={permit.id} className="hover:bg-gray-50">
                              <td className="px-6 py-4">
                                <div>
                                  <div className="font-medium text-gray-900">{permit.business_name || 'Unknown Business'}</div>
                                  <div className="text-sm text-gray-500">
                                    <span className="font-mono">{permit.applicant_id || 'No ID'}</span>
                                  </div>
                                  <div className="text-xs text-gray-400 mt-1">
                                    {businessType} • Created: {formatDate(permit.created_at)}
                                  </div>
                                </div>
                              </td>
                              
                              <td className="px-6 py-4">
                                <div>
                                  <div className="font-medium text-gray-900">{ownerName}</div>
                                  <div className="text-sm text-gray-600">{permit.contact_number || 'No contact'}</div>
                                  {permit.owner_email && (
                                    <div className="text-xs text-gray-500 truncate max-w-[180px]">
                                      {permit.owner_email}
                                    </div>
                                  )}
                                </div>
                              </td>
                              
                              <td className="px-6 py-4">
                                <div>
                                  <div className="text-sm text-gray-900">{barangay}</div>
                                  <div className="text-xs text-gray-600">{city}</div>
                                  {district && (
                                    <div className="text-xs text-gray-500">{district} District</div>
                                  )}
                                </div>
                              </td>
                              
                              <td className="px-6 py-4">
                                <div className="space-y-1">
                                  <div className="flex items-center gap-2">
                                    <span className="text-sm">{taxInfo.icon}</span>
                                    <span className={`text-sm font-medium ${taxInfo.color}`}>
                                      {taxInfo.label}
                                    </span>
                                  </div>
                                  
                                  <div className="text-sm text-gray-900">
                                    Capital: {formatCurrency(taxInfo.amount)}
                                  </div>
                                  
                                  {permit.tax_rate > 0 && (
                                    <div className="text-xs text-gray-500">
                                      Tax Rate: {permit.tax_rate}%
                                    </div>
                                  )}
                                  
                                  {permit.total_tax > 0 && (
                                    <div className="text-xs text-green-600 font-medium">
                                      Total Tax: {formatCurrency(permit.total_tax)}
                                    </div>
                                  )}
                                </div>
                              </td>
                              
                              <td className="px-6 py-4">
                                <div className="space-y-1">
                                  <span className={`inline-flex px-3 py-1 text-xs font-semibold rounded-full ${getStatusColor(status)}`}>
                                    {status}
                                  </span>
                                  <div className="text-xs text-gray-500 space-y-0.5">
                                    {permit.issue_date && (
                                      <div>Issued: {formatDate(permit.issue_date)}</div>
                                    )}
                                    {permit.expiry_date && (
                                      <div>Expires: {formatDate(permit.expiry_date)}</div>
                                    )}
                                  </div>
                                </div>
                              </td>
                              
                              <td className="px-6 py-4">
                                <Link
                                  to={`/business/businessvalidationinfo/${permit.id}`}
                                  className="inline-flex items-center justify-center gap-2 px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 transition-colors"
                                >
                                  <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fillRule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clipRule="evenodd" />
                                  </svg>
                                  View
                                </Link>
                              </td>
                            </tr>
                          );
                        })}
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
                    {searchTerm ? 'No matching applications found' : 'No business permits in your database'}
                  </h3>
                  <p className="text-gray-600 max-w-md mx-auto">
                    {searchTerm 
                      ? 'Try adjusting your search criteria.'
                      : 'Switch to the "Fetch from Permit System" tab to import permits.'}
                  </p>
                  <button
                    onClick={() => setActiveTab('fetchData')}
                    className="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                      <path fillRule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clipRule="evenodd" />
                    </svg>
                    Go to Fetch Tab
                  </button>
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
                      className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
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
                      className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
                    >
                      Next
                    </button>
                  </div>
                </div>
              )}
            </>
          )}
        </>
      ) : (
        /* FETCH DATA TAB CONTENT */
        <>
          {/* Action Bar for Fetch Tab */}
          <div className="bg-white p-4 rounded-lg border border-gray-200 shadow-sm mb-6">
            <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
              <div>
                <h3 className="text-lg font-semibold text-gray-900">Available Permits from External System</h3>
                <p className="text-gray-600 text-sm mt-1">
                  {alreadyImportedIds.length > 0 ? (
                    <span className="text-blue-600">
                      Already imported {alreadyImportedIds.length} permits. Only new permits are shown below.
                    </span>
                  ) : (
                    'Select permits to import into your database.'
                  )}
                </p>
              </div>
              
              <div className="flex flex-wrap gap-2">
                {selectedPermits.length > 0 && (
                  <>
                    <button
                      onClick={importSelectedPermits}
                      disabled={isImporting}
                      className="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
                    >
                      {isImporting ? (
                        <>
                          <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                          </svg>
                          Importing...
                        </>
                      ) : (
                        <>
                          <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clipRule="evenodd" />
                          </svg>
                          Import Selected ({selectedPermits.length})
                        </>
                      )}
                    </button>
                    
                    <button
                      onClick={deselectAllPermits}
                      className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                    >
                      Deselect All
                    </button>
                  </>
                )}
                
                {externalPermits.length > 0 && (
                  <button
                    onClick={selectAllPermits}
                    className="px-4 py-2 border border-blue-300 text-blue-600 rounded-lg hover:bg-blue-50"
                  >
                    Select All ({externalPermits.length})
                  </button>
                )}
                
                <button
                  onClick={fetchExternalPermits}
                  disabled={loadingExternal}
                  className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clipRule="evenodd" />
                  </svg>
                  Refresh External Data
                </button>
              </div>
            </div>
            
            {/* Selection Summary */}
            {selectedPermits.length > 0 && (
              <div className="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <div className="flex items-center justify-between">
                  <div className="text-sm text-blue-700">
                    <span className="font-medium">{selectedPermits.length}</span> permit(s) selected for import
                  </div>
                  <div className="text-xs text-blue-600">
                    Click "Import Selected" to add to your database
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Loading State for External Data */}
          {loadingExternal ? (
            <div className="flex flex-col items-center justify-center py-12">
              <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-600"></div>
              <p className="mt-4 text-gray-600">Fetching permits from external system...</p>
            </div>
          ) : externalPermits.length > 0 ? (
            <div className="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
                        <input
                          type="checkbox"
                          checked={selectedPermits.length === externalPermits.length && externalPermits.length > 0}
                          onChange={(e) => {
                            if (e.target.checked) {
                              selectAllPermits();
                            } else {
                              deselectAllPermits();
                            }
                          }}
                          className="h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                        />
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Business Info
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Owner
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Business Details
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Capital Investment
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {externalPermits.map((permit, index) => {
                      const permitId = permit.applicant_id || permit.permit_id || `ext-${index}`;
                      const isSelected = selectedPermits.includes(permitId);
                      
                      return (
                        <tr key={permitId} className={`hover:bg-gray-50 ${isSelected ? 'bg-blue-50' : ''}`}>
                          <td className="px-6 py-4">
                            <input
                              type="checkbox"
                              checked={isSelected}
                              onChange={() => toggleSelectPermit(permitId)}
                              className="h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                            />
                          </td>
                          
                          <td className="px-6 py-4">
                            <div>
                              <div className="font-medium text-gray-900">{permit.business_name}</div>
                              <div className="text-sm text-gray-500">
                                <span className="font-mono">{permit.applicant_id || permit.permit_id}</span>
                              </div>
                              {permit.trade_name && (
                                <div className="text-xs text-gray-400 mt-1">
                                  Trade: {permit.trade_name}
                                </div>
                              )}
                            </div>
                          </td>
                          
                          <td className="px-6 py-4">
                            <div>
                              <div className="font-medium text-gray-900">{permit.full_name || `${permit.first_name || ''} ${permit.last_name || ''}`}</div>
                              <div className="text-sm text-gray-600">{permit.contact_number}</div>
                              <div className="text-xs text-gray-500 truncate max-w-[180px]">
                                {permit.email_address}
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-6 py-4">
                            <div>
                              <div className="text-sm text-gray-900">{permit.business_nature}</div>
                              <div className="text-xs text-gray-500">
                                <span className="inline-block px-2 py-1 bg-gray-100 rounded">
                                  {permit.owner_type || 'Individual'}
                                </span>
                              </div>
                              <div className="text-xs text-gray-400 mt-1">
                                Applied: {formatDate(permit.application_date)}
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-6 py-4">
                            <div className="space-y-1">
                              <div className="text-lg font-bold text-gray-900">
                                {formatCurrency(permit.capital_investment)}
                              </div>
                              <div className="text-xs text-gray-500">
                                Area: {permit.business_area || 'N/A'} sqm
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-6 py-4">
                            <div className="space-y-1">
                              <span className={`inline-flex px-3 py-1 text-xs font-semibold rounded-full ${
                                (permit.status === 'APPROVED' || permit.status === 'Approved') ? 'bg-green-100 text-green-800 border border-green-200' :
                                'bg-yellow-100 text-yellow-800 border border-yellow-200'
                              }`}>
                                {permit.status || 'PENDING'}
                              </span>
                              <div className="text-xs text-gray-500 space-y-0.5">
                                <div>{permit.barangay}, {permit.city_municipality || permit.city}</div>
                                <div>{permit.province}</div>
                              </div>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            <div className="bg-white p-8 rounded-lg border border-gray-200 shadow-sm text-center">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-16 w-16 text-green-400 mx-auto mb-4" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
              </svg>
              <h3 className="text-lg font-medium text-gray-900 mb-2">All permits already imported!</h3>
              <p className="text-gray-600 max-w-md mx-auto mb-4">
                You have imported all available permits from the external system.
                Switch to "My Database" tab to view and manage them.
              </p>
              <div className="flex gap-2 justify-center">
                <button
                  onClick={() => setActiveTab('myData')}
                  className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clipRule="evenodd" />
                  </svg>
                  View My Database
                </button>
                <button
                  onClick={fetchExternalPermits}
                  className="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                >
                  Check for New Permits
                </button>
              </div>
            </div>
          )}
        </>
      )}

      {/* Footer */}
      <div className="mt-8 pt-6 border-t border-gray-200 text-center text-sm text-gray-500">
        <p>Business Permit Management System • {new Date().toLocaleDateString('en-PH')}</p>
        <p className="text-xs mt-1">
          {myPermits.length} permits in database • {alreadyImportedIds.length} imported from external system
        </p>
      </div>
    </div>
  );
};

export default BusinessValidation;