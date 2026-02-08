import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
  Building2, Search, Filter, RefreshCw, Database, Download,
  Eye, Calendar, User, MapPin, DollarSign, AlertCircle, 
  CheckCircle, FileText, Clock, TrendingUp, AlertTriangle,
  FileCheck, Home, Users, Shield, CheckSquare, CreditCard,
  Percent, Store, Receipt, Coins, FileSearch, Archive
} from 'lucide-react';

// Custom colors from the dashboard
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
        const importedIds = data.permits?.map(p => p.applicant_id) || [];
        setAlreadyImportedIds(importedIds);
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
      
      const response = await fetch(EXTERNAL_API_URL);
      
      if (!response.ok) {
        const errorText = await response.text();
        console.error('Proxy error response:', errorText);
        throw new Error(`Proxy error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.success) {
        const externalData = data.data || [];
        const filteredData = externalData.filter(permit => {
          const permitId = permit.applicant_id || permit.permit_id;
          return !alreadyImportedIds.includes(permitId);
        });
        
        setExternalPermits(filteredData);
        setSelectedPermits([]);
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
      const permitsToImport = externalPermits.filter(permit => {
        const permitId = permit.applicant_id || permit.permit_id;
        return selectedPermits.includes(permitId);
      });
      
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
      
      if (importData.status === 'success') {
        setImportResult({
          success: true,
          message: `Successfully imported ${importData.imported_count || 0} business permits. ${importData.skipped_count || 0} were already in database.`,
          imported_count: importData.imported_count || 0,
          skipped_count: importData.skipped_count || 0,
          error_count: importData.error_count || 0
        });
        
        const newlyImportedIds = permitsToImport.map(p => p.applicant_id || p.permit_id);
        setAlreadyImportedIds(prev => [...prev, ...newlyImportedIds]);
        
        setExternalPermits(prev => 
          prev.filter(permit => !selectedPermits.includes(permit.applicant_id || permit.permit_id))
        );
        
        setSelectedPermits([]);
        
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
        message: err.message || 'Failed to import permits'
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
      .filter(id => id);
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

  const getStatusInfo = (status) => {
    const statusValue = status || 'PENDING';
    switch (statusValue.toUpperCase()) {
      case 'ACTIVE':
        return {
          label: 'Active',
          color: 'text-green-700',
          bgColor: 'bg-green-50',
          borderColor: 'border-green-100',
          icon: CheckCircle,
          dotColor: COLORS.success
        };
      case 'APPROVED':
        return {
          label: 'Approved',
          color: 'text-blue-700',
          bgColor: 'bg-blue-50',
          borderColor: 'border-blue-100',
          icon: FileCheck,
          dotColor: COLORS.primary
        };
      case 'PENDING':
        return {
          label: 'Pending Review',
          color: 'text-yellow-700',
          bgColor: 'bg-yellow-50',
          borderColor: 'border-yellow-100',
          icon: Clock,
          dotColor: COLORS.warning
        };
      case 'EXPIRED':
        return {
          label: 'Expired',
          color: 'text-red-700',
          bgColor: 'bg-red-50',
          borderColor: 'border-red-100',
          icon: AlertTriangle,
          dotColor: COLORS.danger
        };
      case 'RENEWED':
        return {
          label: 'Renewed',
          color: 'text-purple-700',
          bgColor: 'bg-purple-50',
          borderColor: 'border-purple-100',
          icon: RefreshCw,
          dotColor: '#8b5cf6'
        };
      default:
        return {
          label: statusValue.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()),
          color: 'text-gray-700',
          bgColor: 'bg-gray-50',
          borderColor: 'border-gray-100',
          icon: FileText,
          dotColor: COLORS.secondary
        };
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
        color: COLORS.purple
      };
    } else {
      return {
        label: 'Gross Sales',
        amount: amount,
        color: COLORS.success
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

  // Loading state
  if (loading && activeTab === 'myData') {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
        <div className="flex flex-col justify-center items-center h-screen bg-white">
          <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 mb-4" style={{ borderColor: COLORS.primary }}></div>
          <p style={{ color: COLORS.dark }}>Loading Business Applications...</p>
          <p className="text-sm mt-2" style={{ color: COLORS.secondary }}>Fetching business permit data</p>
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
                Business Permit Validation
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
                onClick={activeTab === 'myData' ? fetchMyPermits : fetchExternalPermits}
                disabled={loading || (activeTab === 'fetchData' && loadingExternal)}
                className="flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all disabled:opacity-50"
                style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
              >
                <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
                Refresh
              </button>
              
              <button
                className="flex items-center gap-2 px-4 py-2 rounded-lg transition-all"
                style={{ backgroundColor: COLORS.primary, color: 'white' }}
              >
                <Database className="w-4 h-4" />
                Export Report
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Success/Error Messages */}
        {importResult && (
          <div className={`border rounded-xl p-4 ${importResult.success ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}`}>
            <div className="flex items-center gap-3">
              {importResult.success ? (
                <CheckCircle className="w-5 h-5 text-green-600" />
              ) : (
                <AlertCircle className="w-5 h-5 text-red-600" />
              )}
              <div className="flex-1">
                <p className={`font-medium ${importResult.success ? 'text-green-800' : 'text-red-800'}`}>
                  {importResult.success ? 'Import Successful' : 'Import Failed'}
                </p>
                <p className={`text-sm ${importResult.success ? 'text-green-700' : 'text-red-700'}`}>
                  {importResult.message}
                </p>
              </div>
            </div>
          </div>
        )}

        {error && (
          <div className="border rounded-xl p-4" style={{ backgroundColor: `${COLORS.danger}10`, borderColor: `${COLORS.danger}20` }}>
            <div className="flex items-center gap-3">
              <AlertCircle className="w-5 h-5" style={{ color: COLORS.danger }} />
              <div>
                <p className="font-medium" style={{ color: COLORS.danger }}>Error</p>
                <p className="text-sm" style={{ color: COLORS.dark }}>{error}</p>
              </div>
            </div>
          </div>
        )}

        {/* Tabs */}
        <div className="bg-white border rounded-xl shadow-sm" style={{ borderColor: COLORS.secondary }}>
          <div className="p-6 border-b" style={{ borderColor: COLORS.secondary }}>
            <div className="flex space-x-1">
              <button
                onClick={() => {
                  setActiveTab('myData');
                  setSearchTerm('');
                  setCurrentPage(1);
                }}
                className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-all whitespace-nowrap ${
                  activeTab === 'myData' 
                    ? 'text-white shadow-sm' 
                    : 'hover:bg-gray-50'
                }`}
                style={{
                  backgroundColor: activeTab === 'myData' ? COLORS.primary : 'transparent',
                  color: activeTab === 'myData' ? 'white' : COLORS.dark
                }}
              >
                <Database className="w-5 h-5" />
                My Database
                <span className="text-xs px-1.5 py-0.5 rounded-full bg-white/20">
                  {stats.total}
                </span>
              </button>
              
              <button
                onClick={() => {
                  setActiveTab('fetchData');
                  setSearchTerm('');
                  if (externalPermits.length === 0 && !loadingExternal) {
                    fetchExternalPermits();
                  }
                }}
                className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-all whitespace-nowrap ${
                  activeTab === 'fetchData'
                    ? 'text-white shadow-sm'
                    : 'hover:bg-gray-50'
                }`}
                style={{
                  backgroundColor: activeTab === 'fetchData' ? COLORS.info : 'transparent',
                  color: activeTab === 'fetchData' ? 'white' : COLORS.dark
                }}
              >
                <Download className="w-5 h-5" />
                Fetch from Permit System
                <span className="text-xs px-1.5 py-0.5 rounded-full bg-white/20">
                  {externalPermits.length}
                </span>
              </button>
            </div>
          </div>

          {/* Tab Content */}
          <div className="p-6">
            {activeTab === 'myData' ? (
              /* MY DATA TAB CONTENT */
              <>
                {/* Statistics Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
                  {/* Total Applications */}
                  <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
                       style={{ borderColor: COLORS.secondary }}>
                    <div className="flex items-center justify-between mb-4">
                      <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                        <Building2 className="w-6 h-6" style={{ color: COLORS.primary }} />
                      </div>
                      <span className="text-sm px-3 py-1 rounded-full" 
                            style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                        Total
                      </span>
                    </div>
                    <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
                      Business Permits
                    </h3>
                    <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.total}</p>
                    <div className="text-sm" style={{ color: COLORS.secondary }}>
                      <div className="flex justify-between">
                        <span>In system</span>
                      </div>
                      <div className="w-full bg-gray-200 rounded-full h-2 mt-1">
                        <div 
                          className="h-2 rounded-full transition-all duration-500"
                          style={{ 
                            width: `${stats.total > 0 ? 100 : 0}%`,
                            backgroundColor: COLORS.primary
                          }}
                        ></div>
                      </div>
                    </div>
                  </div>

                  {/* Pending Review */}
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
                      Pending Review
                    </h3>
                    <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.pending}</p>
                    <div className="text-sm" style={{ color: COLORS.secondary }}>
                      <div className="flex justify-between">
                        <span>Awaiting review</span>
                        <span className="font-medium">{Math.round((stats.pending / Math.max(stats.total, 1)) * 100)}%</span>
                      </div>
                      <div className="w-full bg-gray-200 rounded-full h-2 mt-1">
                        <div 
                          className="h-2 rounded-full transition-all duration-500"
                          style={{ 
                            width: `${stats.total > 0 ? (stats.pending / stats.total) * 100 : 0}%`,
                            backgroundColor: COLORS.warning
                          }}
                        ></div>
                      </div>
                    </div>
                  </div>

                  {/* Approved */}
                  <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
                       style={{ borderColor: COLORS.secondary }}>
                    <div className="flex items-center justify-between mb-4">
                      <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.success}15` }}>
                        <CheckCircle className="w-6 h-6" style={{ color: COLORS.success }} />
                      </div>
                      <span className="text-sm px-3 py-1 rounded-full bg-green-100 text-green-800">
                        {stats.approved}
                      </span>
                    </div>
                    <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
                      Approved
                    </h3>
                    <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.approved}</p>
                    <div className="text-sm" style={{ color: COLORS.secondary }}>
                      <div className="flex justify-between">
                        <span>Recently approved</span>
                        <span className="font-medium">{Math.round((stats.approved / Math.max(stats.total, 1)) * 100)}%</span>
                      </div>
                      <div className="w-full bg-gray-200 rounded-full h-2 mt-1">
                        <div 
                          className="h-2 rounded-full transition-all duration-500"
                          style={{ 
                            width: `${stats.total > 0 ? (stats.approved / stats.total) * 100 : 0}%`,
                            backgroundColor: COLORS.success
                          }}
                        ></div>
                      </div>
                    </div>
                  </div>

                  {/* Active */}
                  <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
                       style={{ borderColor: COLORS.secondary }}>
                    <div className="flex items-center justify-between mb-4">
                      <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.info}15` }}>
                        <Store className="w-6 h-6" style={{ color: COLORS.info }} />
                      </div>
                      <span className="text-sm px-3 py-1 rounded-full bg-blue-100 text-blue-800">
                        {stats.active}
                      </span>
                    </div>
                    <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
                      Active
                    </h3>
                    <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.active}</p>
                    <div className="text-sm" style={{ color: COLORS.secondary }}>
                      <div className="flex justify-between">
                        <span>Current businesses</span>
                        <span className="font-medium">{Math.round((stats.active / Math.max(stats.total, 1)) * 100)}%</span>
                      </div>
                      <div className="w-full bg-gray-200 rounded-full h-2 mt-1">
                        <div 
                          className="h-2 rounded-full transition-all duration-500"
                          style={{ 
                            width: `${stats.total > 0 ? (stats.active / stats.total) * 100 : 0}%`,
                            backgroundColor: COLORS.info
                          }}
                        ></div>
                      </div>
                    </div>
                  </div>

                  {/* Expired */}
                  <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
                       style={{ borderColor: COLORS.secondary }}>
                    <div className="flex items-center justify-between mb-4">
                      <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.danger}15` }}>
                        <AlertTriangle className="w-6 h-6" style={{ color: COLORS.danger }} />
                      </div>
                      <span className="text-sm px-3 py-1 rounded-full bg-red-100 text-red-800">
                        {stats.expired}
                      </span>
                    </div>
                    <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
                      Expired
                    </h3>
                    <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.expired}</p>
                    <div className="text-sm" style={{ color: COLORS.secondary }}>
                      <div className="flex justify-between">
                        <span>Needs renewal</span>
                        <span className="font-medium">{Math.round((stats.expired / Math.max(stats.total, 1)) * 100)}%</span>
                      </div>
                      <div className="w-full bg-gray-200 rounded-full h-2 mt-1">
                        <div 
                          className="h-2 rounded-full transition-all duration-500"
                          style={{ 
                            width: `${stats.total > 0 ? (stats.expired / stats.total) * 100 : 0}%`,
                            backgroundColor: COLORS.danger
                          }}
                        ></div>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Filter Section */}
                <div className="bg-white border rounded-xl p-6 shadow-sm mb-6" style={{ borderColor: COLORS.secondary }}>
                  <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                    <div className="flex-1">
                      <div className="relative">
                        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" style={{ color: COLORS.secondary }} />
                        <input
                          type="text"
                          placeholder="Search by business name, owner, ID, or location..."
                          value={searchTerm}
                          onChange={(e) => {
                            setSearchTerm(e.target.value);
                            setCurrentPage(1);
                          }}
                          className="w-full pl-10 pr-4 py-2 border rounded-lg"
                          style={{ borderColor: COLORS.secondary }}
                        />
                      </div>
                    </div>
                    
                    <div className="flex gap-2">
                      <div className="relative">
                        <Filter className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" style={{ color: COLORS.secondary }} />
                        <select
                          value={filterStatus}
                          onChange={(e) => {
                            setFilterStatus(e.target.value);
                            setCurrentPage(1);
                          }}
                          className="pl-10 pr-8 py-2 border rounded-lg appearance-none bg-white"
                          style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                        >
                          <option value="PENDING">Pending Review</option>
                          <option value="all">All Statuses</option>
                          <option value="APPROVED">Approved</option>
                          <option value="ACTIVE">Active</option>
                          <option value="EXPIRED">Expired</option>
                          <option value="RENEWED">Renewed</option>
                        </select>
                      </div>
                      
                      <select
                        value={itemsPerPage}
                        onChange={(e) => {
                          setItemsPerPage(parseInt(e.target.value));
                          setCurrentPage(1);
                        }}
                        className="px-3 py-2 border rounded-lg bg-white"
                        style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                      >
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                      </select>
                    </div>
                  </div>
                  
                  {/* Search Stats */}
                  <div className="mt-4 flex items-center justify-between text-sm">
                    <div style={{ color: COLORS.secondary }}>
                      {searchTerm ? (
                        <span>
                          Searching for: <span className="font-medium" style={{ color: COLORS.dark }}>"{searchTerm}"</span>
                        </span>
                      ) : (
                        <span>Showing all business applications</span>
                      )}
                    </div>
                    <div className="font-medium" style={{ color: COLORS.dark }}>
                      Showing {startIndex + 1}-{Math.min(endIndex, filteredMyPermits.length)} of {filteredMyPermits.length}
                    </div>
                  </div>
                </div>

                {/* Permits Table */}
                <div className="bg-white border rounded-xl shadow-sm" style={{ borderColor: COLORS.secondary }}>
                  <div className="p-6 border-b" style={{ borderColor: COLORS.secondary }}>
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                      <div>
                        <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                          <FileText className="w-5 h-5" style={{ color: COLORS.primary }} />
                          Business Applications ({filteredMyPermits.length})
                        </h3>
                        <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                          Filter: {filterStatus === 'all' ? 'All Statuses' : filterStatus}
                        </p>
                      </div>
                      
                      <div className="inline-flex items-center gap-2 px-3 py-1.5 border rounded-lg text-sm"
                          style={{ borderColor: COLORS.secondary, color: COLORS.secondary }}>
                        <Archive className="w-4 h-4" />
                        <span>{stats.total} total applications</span>
                      </div>
                    </div>
                  </div>
                  
                  {paginatedPermits.length === 0 ? (
                    <div className="text-center py-12" style={{ color: COLORS.secondary }}>
                      <FileSearch className="w-12 h-12 mx-auto mb-2" />
                      <p className="text-sm font-medium" style={{ color: COLORS.dark }}>
                        {searchTerm || filterStatus !== 'PENDING' 
                          ? "No matching applications found" 
                          : "No business applications found"}
                      </p>
                      <p className="text-sm mt-1 max-w-xs mx-auto">
                        {searchTerm 
                          ? "Try adjusting your search terms or clear filters"
                          : "Switch to the Fetch tab to import applications"}
                      </p>
                      {(searchTerm || filterStatus !== 'PENDING') && (
                        <button
                          onClick={() => {
                            setSearchTerm("");
                            setFilterStatus("PENDING");
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
                                Application ID
                              </th>
                              <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                                Business Information
                              </th>
                              <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                                Owner
                              </th>
                              <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                                Tax Information
                              </th>
                              <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                                Status
                              </th>
                              <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                                Actions
                              </th>
                            </tr>
                          </thead>
                          <tbody>
                            {paginatedPermits.map((permit) => {
                              const statusInfo = getStatusInfo(permit.permit_status || permit.status);
                              const StatusIcon = statusInfo.icon;
                              const taxInfo = getTaxTypeDisplay(permit);
                              const ownerName = permit.owner_name || permit.owner_full_name || 'Unknown';
                              const businessType = permit.business_type || permit.business_nature || 'Unknown';
                              const barangay = permit.barangay || permit.business_barangay || '';
                              const city = permit.city || permit.business_city || '';
                              
                              return (
                                <tr key={permit.id} className="hover:bg-gray-50 transition-colors" 
                                    style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}>
                                  <td className="p-4">
                                    <div className="font-mono font-medium" style={{ color: COLORS.dark }}>
                                      {permit.applicant_id || 'No ID'}
                                    </div>
                                    <div className="text-xs mt-1" style={{ color: COLORS.secondary }}>ID: {permit.id}</div>
                                  </td>
                                  <td className="p-4">
                                    <div className="font-medium" style={{ color: COLORS.dark }}>{permit.business_name || 'Unknown Business'}</div>
                                    <div className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                                      {businessType}
                                    </div>
                                    <div className="text-xs mt-1" style={{ color: COLORS.secondary }}>
                                      {barangay}, {city}
                                    </div>
                                  </td>
                                  <td className="p-4">
                                    <div className="font-medium" style={{ color: COLORS.dark }}>{ownerName}</div>
                                    <div className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                                      {permit.contact_number || 'No contact'}
                                    </div>
                                    {permit.owner_email && (
                                      <div className="text-xs mt-0.5" style={{ color: COLORS.secondary }}>
                                        {permit.owner_email}
                                      </div>
                                    )}
                                  </td>
                                  <td className="p-4">
                                    <div className="font-medium" style={{ color: taxInfo.color }}>
                                      {formatCurrency(taxInfo.amount)}
                                    </div>
                                    <div className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                                      {taxInfo.label}
                                    </div>
                                    {permit.tax_rate > 0 && (
                                      <div className="text-xs mt-1" style={{ color: COLORS.success }}>
                                        Tax Rate: {permit.tax_rate}%
                                      </div>
                                    )}
                                  </td>
                                  <td className="p-4">
                                    <div className="flex items-center gap-3">
                                      <div className={`p-2 rounded-lg ${statusInfo.bgColor}`}>
                                        <StatusIcon className={`w-4 h-4 ${statusInfo.color}`} />
                                      </div>
                                      <div>
                                        <span className={`text-xs font-medium px-3 py-1.5 rounded-full ${statusInfo.bgColor} ${statusInfo.color} border ${statusInfo.borderColor}`}>
                                          {statusInfo.label}
                                        </span>
                                      </div>
                                    </div>
                                  </td>
                                  <td className="p-4">
                                    <Link
                                      to={`/business/businessvalidationinfo/${permit.id}`}
                                      className="px-4 py-2 rounded-lg flex items-center gap-2 transition-all"
                                      style={{ backgroundColor: COLORS.primary, color: 'white' }}
                                    >
                                      <Eye className="w-4 h-4" />
                                      Review
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
                            Showing {startIndex + 1}-{Math.min(endIndex, filteredMyPermits.length)} of {filteredMyPermits.length} applications
                          </div>
                          <div className="text-sm">
                            <div className="flex flex-wrap items-center gap-2">
                              <span className="font-medium" style={{ color: COLORS.dark }}>Status Summary:</span>
                              {stats.pending > 0 && (
                                <span className="px-2 py-1 rounded text-xs bg-yellow-100 text-yellow-800">
                                  {stats.pending} pending
                                </span>
                              )}
                              {stats.approved > 0 && (
                                <span className="px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">
                                  {stats.approved} approved
                                </span>
                              )}
                              {stats.active > 0 && (
                                <span className="px-2 py-1 rounded text-xs bg-green-100 text-green-800">
                                  {stats.active} active
                                </span>
                              )}
                              {stats.expired > 0 && (
                                <span className="px-2 py-1 rounded text-xs bg-red-100 text-red-800">
                                  {stats.expired} expired
                                </span>
                              )}
                            </div>
                          </div>
                        </div>
                      </div>
                    </>
                  )}
                </div>

                {/* Pagination */}
                {totalPages > 1 && (
                  <div className="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div className="text-sm" style={{ color: COLORS.secondary }}>
                      Page {currentPage} of {totalPages}
                    </div>
                    <div className="flex items-center space-x-2">
                      <button
                        onClick={() => handlePageChange(currentPage - 1)}
                        disabled={currentPage === 1}
                        className="px-4 py-2 border rounded-lg hover:bg-gray-50 disabled:opacity-50"
                        style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
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
                              className={`px-3 py-1 text-sm rounded transition-colors ${
                                currentPage === pageNumber
                                  ? 'text-white'
                                  : 'border hover:bg-gray-50'
                              }`}
                              style={{
                                backgroundColor: currentPage === pageNumber ? COLORS.primary : 'transparent',
                                borderColor: currentPage === pageNumber ? COLORS.primary : COLORS.secondary,
                                color: currentPage === pageNumber ? 'white' : COLORS.dark
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
                        className="px-4 py-2 border rounded-lg hover:bg-gray-50 disabled:opacity-50"
                        style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                      >
                        Next
                      </button>
                    </div>
                  </div>
                )}
              </>
            ) : (
              /* FETCH DATA TAB CONTENT */
              <>
                {/* Action Bar */}
                <div className="bg-white border rounded-xl p-6 shadow-sm mb-6" style={{ borderColor: COLORS.secondary }}>
                  <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                    <div>
                      <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                        <Download className="w-5 h-5" style={{ color: COLORS.info }} />
                        Available Permits from External System
                      </h3>
                      <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                        {alreadyImportedIds.length > 0 ? (
                          <span style={{ color: COLORS.primary }}>
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
                            className="flex items-center gap-2 px-4 py-2 rounded-lg transition-all hover:shadow-sm disabled:opacity-50"
                            style={{ 
                              backgroundColor: COLORS.success, 
                              color: 'white',
                              boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
                            }}
                          >
                            {isImporting ? (
                              <>
                                <RefreshCw className="w-4 h-4 animate-spin" />
                                Importing...
                              </>
                            ) : (
                              <>
                                <Download className="w-4 h-4" />
                                Import Selected ({selectedPermits.length})
                              </>
                            )}
                          </button>
                          
                          <button
                            onClick={deselectAllPermits}
                            className="px-4 py-2 border rounded-lg hover:bg-gray-50 transition-colors"
                            style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                          >
                            Deselect All
                          </button>
                        </>
                      )}
                      
                      {externalPermits.length > 0 && (
                        <button
                          onClick={selectAllPermits}
                          className="px-4 py-2 border rounded-lg hover:bg-blue-50 transition-colors"
                          style={{ borderColor: COLORS.primary, color: COLORS.primary }}
                        >
                          Select All ({externalPermits.length})
                        </button>
                      )}
                      
                      <button
                        onClick={fetchExternalPermits}
                        disabled={loadingExternal}
                        className="flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50"
                        style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                      >
                        <RefreshCw className={`w-4 h-4 ${loadingExternal ? 'animate-spin' : ''}`} />
                        Refresh External Data
                      </button>
                    </div>
                  </div>
                  
                  {/* Selection Summary */}
                  {selectedPermits.length > 0 && (
                    <div className="mt-4 p-3 rounded-lg" style={{ backgroundColor: `${COLORS.primary}10`, border: `1px solid ${COLORS.primary}20` }}>
                      <div className="flex items-center justify-between">
                        <div className="text-sm flex items-center gap-2" style={{ color: COLORS.primary }}>
                          <CheckSquare className="w-4 h-4" />
                          <span className="font-medium">{selectedPermits.length}</span> permit(s) selected for import
                        </div>
                        <div className="text-xs" style={{ color: COLORS.secondary }}>
                          Click "Import Selected" to add to your database
                        </div>
                      </div>
                    </div>
                  )}
                </div>

                {/* Loading State for External Data */}
                {loadingExternal ? (
                  <div className="flex flex-col items-center justify-center py-12">
                    <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2" style={{ borderColor: COLORS.info }}></div>
                    <p className="mt-4" style={{ color: COLORS.secondary }}>Fetching permits from external system...</p>
                  </div>
                ) : externalPermits.length > 0 ? (
                  <div className="bg-white border rounded-xl shadow-sm overflow-hidden" style={{ borderColor: COLORS.secondary }}>
                    <div className="overflow-x-auto">
                      <table className="min-w-full">
                        <thead style={{ backgroundColor: '#f9fafb', borderColor: COLORS.secondary, borderBottomWidth: '2px' }}>
                          <tr>
                            <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                              <div className="flex items-center">
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
                                  className="h-4 w-4 rounded border-gray-300 focus:ring-2 focus:ring-blue-500"
                                  style={{ borderColor: COLORS.secondary }}
                                />
                              </div>
                            </th>
                            <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>Business Info</th>
                            <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>Owner</th>
                            <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>Business Details</th>
                            <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>Capital Investment</th>
                            <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>Status</th>
                          </tr>
                        </thead>
                        <tbody>
                          {externalPermits.map((permit, index) => {
                            const permitId = permit.applicant_id || permit.permit_id || `ext-${index}`;
                            const isSelected = selectedPermits.includes(permitId);
                            
                            return (
                              <tr 
                                key={permitId} 
                                className={`transition-colors hover:bg-gray-50 ${isSelected ? 'bg-blue-50' : ''}`}
                                style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}
                              >
                                <td className="p-4">
                                  <div className="flex items-center">
                                    <input
                                      type="checkbox"
                                      checked={isSelected}
                                      onChange={() => toggleSelectPermit(permitId)}
                                      className="h-4 w-4 rounded border-gray-300 focus:ring-2 focus:ring-blue-500"
                                      style={{ borderColor: COLORS.secondary }}
                                    />
                                  </div>
                                </td>
                                
                                <td className="p-4">
                                  <div>
                                    <div className="font-medium flex items-center gap-2" style={{ color: COLORS.dark }}>
                                      <Building2 className="w-4 h-4" style={{ color: COLORS.primary }} />
                                      {permit.business_name}
                                    </div>
                                    <div className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                                      <span className="font-mono">{permit.applicant_id || permit.permit_id}</span>
                                    </div>
                                    {permit.trade_name && (
                                      <div className="text-xs mt-1" style={{ color: COLORS.secondary }}>
                                        Trade: {permit.trade_name}
                                      </div>
                                    )}
                                  </div>
                                </td>
                                
                                <td className="p-4">
                                  <div>
                                    <div className="font-medium flex items-center gap-2" style={{ color: COLORS.dark }}>
                                      <User className="w-4 h-4" style={{ color: COLORS.info }} />
                                      {permit.full_name || `${permit.first_name || ''} ${permit.last_name || ''}`}
                                    </div>
                                    <div className="text-sm mt-1" style={{ color: COLORS.secondary }}>{permit.contact_number}</div>
                                    <div className="text-xs mt-1 truncate max-w-[180px]" style={{ color: COLORS.secondary }}>
                                      {permit.email_address}
                                    </div>
                                  </div>
                                </td>
                                
                                <td className="p-4">
                                  <div>
                                    <div className="text-sm" style={{ color: COLORS.dark }}>{permit.business_nature}</div>
                                    <div className="text-xs mt-1">
                                      <span className="inline-block px-2 py-1 rounded" style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                                        {permit.owner_type || 'Individual'}
                                      </span>
                                    </div>
                                    <div className="text-xs mt-1 flex items-center gap-1" style={{ color: COLORS.secondary }}>
                                      <Calendar className="w-3 h-3" />
                                      Applied: {formatDate(permit.application_date)}
                                    </div>
                                  </div>
                                </td>
                                
                                <td className="p-4">
                                  <div className="space-y-1">
                                    <div className="text-lg font-bold flex items-center gap-2" style={{ color: COLORS.dark }}>
                                      <DollarSign className="w-4 h-4" />
                                      {formatCurrency(permit.capital_investment)}
                                    </div>
                                    <div className="text-xs" style={{ color: COLORS.secondary }}>
                                      Area: {permit.business_area || 'N/A'} sqm
                                    </div>
                                  </div>
                                </td>
                                
                                <td className="p-4">
                                  <div className="space-y-2">
                                    <span className={`inline-flex px-3 py-1 text-xs font-semibold rounded-full ${
                                      (permit.status === 'APPROVED' || permit.status === 'Approved') ? 'bg-green-100 text-green-800 border border-green-200' :
                                      'bg-yellow-100 text-yellow-800 border border-yellow-200'
                                    }`}>
                                      {permit.status || 'PENDING'}
                                    </span>
                                    <div className="text-xs space-y-0.5" style={{ color: COLORS.secondary }}>
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
                  <div className="bg-white p-8 border rounded-xl shadow-sm text-center" style={{ borderColor: COLORS.secondary }}>
                    <div className="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style={{ backgroundColor: `${COLORS.success}15` }}>
                      <CheckCircle className="w-8 h-8" style={{ color: COLORS.success }} />
                    </div>
                    <h3 className="text-lg font-medium mb-2" style={{ color: COLORS.dark }}>All permits already imported!</h3>
                    <p className="max-w-md mx-auto mb-6" style={{ color: COLORS.secondary }}>
                      You have imported all available permits from the external system.
                      Switch to "My Database" tab to view and manage them.
                    </p>
                    <div className="flex gap-2 justify-center">
                      <button
                        onClick={() => setActiveTab('myData')}
                        className="inline-flex items-center gap-2 px-4 py-2 rounded-lg transition-all hover:shadow-sm"
                        style={{ 
                          backgroundColor: COLORS.primary, 
                          color: 'white',
                          boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
                        }}
                      >
                        <Database className="w-4 h-4" />
                        View My Database
                      </button>
                      <button
                        onClick={fetchExternalPermits}
                        className="inline-flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50 transition-colors"
                        style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                      >
                        Check for New Permits
                      </button>
                    </div>
                  </div>
                )}
              </>
            )}
          </div>
        </div>

        {/* Footer Summary */}
        <div className="text-center text-sm pt-6 border-t" style={{ color: COLORS.secondary, borderColor: COLORS.secondary }}>
          <p>Business Permit Validation Portal • {new Date().toLocaleDateString('en-PH')}</p>
          <p className="text-xs mt-1">
            Local Government Unit - Business Tax Management System
          </p>
        </div>
      </div>
    </div>
  );
};

export default BusinessValidation;