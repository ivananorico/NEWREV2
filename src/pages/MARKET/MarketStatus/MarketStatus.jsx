import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Search, Download, Eye, User, Mail, Phone, Building, DollarSign, TrendingUp,
  Home, Calendar, ShieldCheck, FileText, MapPin, Award, Clock, Users, Filter,
  RefreshCw, CheckCircle, AlertCircle, Store, BarChart3, Target, TrendingDown,
  Layers, Briefcase, Hash, ChevronRight, ChevronLeft, Database, Settings,
  ArrowUpRight, PieChart, Grid3x3, Landmark, CreditCard, Timer, Percent,
  Building as BuildingIcon, Store as StoreIcon, Target as TargetIcon,
  TrendingUp as TrendingUpIcon, Users as UsersIcon, CheckCircle as CheckCircleIcon
} from 'lucide-react';

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

export default function MarketStatus() {
  const [citizens, setCitizens] = useState([]);
  const [totals, setTotals] = useState({
    total_citizens: 0,
    total_monthly_rent: 0,
    total_contract_value: 0,
    active_citizens: 0,
    average_monthly_rent: 0,
    average_contract_value: 0,
    total_business_types: 0,
    total_stall_classes: 0
  });
  const [filteredCitizens, setFilteredCitizens] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [businessTypeFilter, setBusinessTypeFilter] = useState('all');
  const [statusFilter, setStatusFilter] = useState('all');
  const [showFilters, setShowFilters] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 10;
  const navigate = useNavigate();

  // API Base URL
  const isProduction = window.location.hostname.includes('goserveph.com');
  const API_BASE = isProduction 
    ? "/backend/Market/MarketStatus"
    : "http://localhost/revenue2/backend/Market/MarketStatus";

  useEffect(() => {
    loadData();
  }, []);

  useEffect(() => {
    filterCitizens();
  }, [citizens, searchTerm, businessTypeFilter, statusFilter]);

  const loadData = async () => {
    try {
      setLoading(true);
      await fetchApprovedCitizens();
    } catch (error) {
      console.error('Error loading data:', error);
    } finally {
      setLoading(false);
    }
  };

  const fetchApprovedCitizens = async () => {
    try {
      const apiUrl = `${API_BASE}/approved_citizens.php`;
      console.log('Fetching from:', apiUrl);
      
      const response = await fetch(apiUrl, {
        headers: {
          'Cache-Control': 'no-cache',
          'Pragma': 'no-cache'
        }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      console.log('API Response:', data);
      
      if (data.status === 'success') {
        const citizensData = data.data || [];
        setCitizens(citizensData);
        setTotals(data.totals || {
          total_citizens: 0,
          total_monthly_rent: 0,
          total_contract_value: 0,
          active_citizens: 0,
          average_monthly_rent: 0,
          average_contract_value: 0,
          total_business_types: 0,
          total_stall_classes: 0
        });
      } else {
        console.error('API Error:', data.message);
      }
    } catch (err) {
      console.error('Error fetching citizens:', err);
    }
  };

  const filterCitizens = () => {
    let result = [...citizens];

    // Search filter
    if (searchTerm) {
      const term = searchTerm.toLowerCase();
      result = result.filter(citizen =>
        (citizen.full_name?.toLowerCase().includes(term)) ||
        (citizen.renter_code?.toLowerCase().includes(term)) ||
        (citizen.business_name?.toLowerCase().includes(term)) ||
        (citizen.email?.toLowerCase().includes(term))
      );
    }

    // Business type filter
    if (businessTypeFilter !== 'all') {
      result = result.filter(citizen => citizen.business_type === businessTypeFilter);
    }

    // Status filter
    if (statusFilter !== 'all') {
      result = result.filter(citizen => citizen.status === statusFilter);
    }

    setFilteredCitizens(result);
  };

  // Calculate pagination
  const totalPages = Math.ceil(filteredCitizens.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const paginatedCitizens = filteredCitizens.slice(startIndex, endIndex);

  const formatCurrency = (amount) => {
    const num = parseFloat(amount) || 0;
    if (num >= 1000000) return `₱${(num / 1000000).toFixed(2)}M`;
    if (num >= 1000) return `₱${(num / 1000).toFixed(2)}K`;
    return `₱${num.toFixed(2)}`;
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      });
    } catch (e) {
      return 'N/A';
    }
  };

  const getStatusBadge = (status) => {
    switch(status?.toLowerCase()) {
      case 'active':
        return {
          text: 'Active',
          bgColor: 'bg-green-50',
          textColor: 'text-green-800',
          borderColor: 'border-green-200',
          icon: CheckCircle,
          dotColor: COLORS.success
        };
      case 'pending':
        return {
          text: 'Pending',
          bgColor: 'bg-yellow-50',
          textColor: 'text-yellow-800',
          borderColor: 'border-yellow-200',
          icon: Clock,
          dotColor: COLORS.warning
        };
      case 'approved':
        return {
          text: 'Approved',
          bgColor: 'bg-blue-50',
          textColor: 'text-blue-800',
          borderColor: 'border-blue-200',
          icon: ShieldCheck,
          dotColor: COLORS.primary
        };
      case 'inactive':
        return {
          text: 'Inactive',
          bgColor: 'bg-gray-50',
          textColor: 'text-gray-800',
          borderColor: 'border-gray-200',
          icon: AlertCircle,
          dotColor: COLORS.secondary
        };
      default:
        return {
          text: status || 'N/A',
          bgColor: 'bg-gray-50',
          textColor: 'text-gray-800',
          borderColor: 'border-gray-200',
          icon: AlertCircle,
          dotColor: COLORS.secondary
        };
    }
  };

  const getBusinessTypes = () => {
    const types = [...new Set(citizens.map(c => c.business_type).filter(Boolean))];
    return types;
  };

  const exportToCSV = () => {
    const headers = [
      'Renter Code', 'Full Name', 'Business Name', 'Business Type', 'Status',
      'Stall Rights No', 'Stall Name', 'Monthly Rent', 'Contract Total', 
      'Contract Months', 'Email', 'Mobile', 'Registration Date', 'Contract Start', 'Contract End'
    ];
    
    const csvData = [
      headers.join(','),
      ...filteredCitizens.map(c => [
        `"${c.renter_code || ''}"`,
        `"${c.full_name || ''}"`,
        `"${c.business_name || ''}"`,
        `"${c.business_type || ''}"`,
        `"${c.status || ''}"`,
        `"${c.stall_rights_no || ''}"`,
        `"${c.stall_name || ''}"`,
        c.monthly_rent || 0,
        c.monthly_totals || 0,
        c.contract_months || 0,
        `"${c.email || ''}"`,
        `"${c.mobile || ''}"`,
        `"${formatDate(c.registration_date)}"`,
        `"${formatDate(c.contract_start)}"`,
        `"${formatDate(c.contract_end)}"`
      ].join(','))
    ].join('\n');

    const blob = new Blob([csvData], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `market-citizens-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
  };

  // Calculate filtered totals
  const calculateFilteredTotals = () => {
    if (filteredCitizens.length === 0) return {
      total_monthly_rent: 0,
      total_contract_value: 0,
      active_citizens: 0,
      count: 0
    };

    const total_monthly_rent = filteredCitizens.reduce((sum, c) => 
      sum + (parseFloat(c.monthly_rent) || 0), 0);
    const total_contract_value = filteredCitizens.reduce((sum, c) => 
      sum + (parseFloat(c.monthly_totals) || 0), 0);
    const active_citizens = filteredCitizens.filter(c => c.status === 'active').length;
    
    return {
      total_monthly_rent,
      total_contract_value,
      active_citizens,
      count: filteredCitizens.length
    };
  };

  const filteredTotals = calculateFilteredTotals();

  if (loading) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
        <div className="flex flex-col justify-center items-center h-screen bg-white">
          <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-gray-800 mb-4"></div>
          <p className="text-gray-600">Loading Market Citizens...</p>
          <p className="text-sm text-gray-400 mt-2">Fetching market citizen data</p>
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
                Market Citizens Dashboard
              </h1>
              <div className="flex items-center gap-3 text-sm" style={{ color: COLORS.secondary }}>
                <div className="flex items-center gap-1">
                  <Calendar className="w-4 h-4" />
                  <span>Approved Renters • {new Date().toLocaleDateString('en-PH')}</span>
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
                onClick={loadData}
                disabled={loading}
                className="flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all disabled:opacity-50"
                style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
              >
                <RefreshCw className="w-4 h-4" />
                Refresh
              </button>
              <button 
                onClick={exportToCSV}
                className="flex items-center gap-2 px-4 py-2 rounded-lg transition-all"
                style={{ backgroundColor: COLORS.primary, color: 'white' }}
              >
                <Download className="w-4 h-4" />
                Export CSV
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
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
              Total Citizens
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{totals.total_citizens}</p>
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between mb-1">
                <span>Active:</span>
                <span className="font-medium">{totals.active_citizens}</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div 
                  className="h-2 rounded-full transition-all duration-500"
                  style={{ 
                    width: `${totals.total_citizens > 0 ? (totals.active_citizens / totals.total_citizens) * 100 : 0}%`,
                    backgroundColor: (totals.active_citizens / totals.total_citizens) >= 0.7 ? COLORS.success :
                                   (totals.active_citizens / totals.total_citizens) >= 0.4 ? COLORS.warning : COLORS.danger
                  }}
                ></div>
              </div>
            </div>
          </div>

          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.success}15` }}>
                <DollarSign className="w-6 h-6" style={{ color: COLORS.success }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full" 
                    style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                Monthly
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Monthly Rent Total
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{formatCurrency(totals.total_monthly_rent)}</p>
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Average:</span>
                <span className="font-medium">{formatCurrency(totals.average_monthly_rent)}</span>
              </div>
            </div>
          </div>

          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: '#6b46c115' }}>
                <BarChart3 className="w-6 h-6" style={{ color: '#6b46c1' }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full bg-purple-100 text-purple-800">
                Total
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Contract Value
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{formatCurrency(totals.total_contract_value)}</p>
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Average:</span>
                <span className="font-medium">{formatCurrency(totals.average_contract_value)}</span>
              </div>
            </div>
          </div>

          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.info}15` }}>
                <Briefcase className="w-6 h-6" style={{ color: COLORS.info }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full" 
                    style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                Types
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Business Types
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{totals.total_business_types}</p>
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Stall Classes:</span>
                <span className="font-medium">{totals.total_stall_classes}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Filters Section */}
        <div className="bg-white border rounded-xl p-6" style={{ borderColor: COLORS.secondary }}>
          <div className="flex justify-between items-center mb-4">
            <h3 className="font-semibold" style={{ color: COLORS.dark }}>Filter Citizens</h3>
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
            <div className="mt-4 pt-4 border-t" style={{ borderColor: COLORS.secondary }}>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {/* Search */}
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Search</label>
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                           style={{ color: COLORS.secondary }} />
                    <input
                      type="text"
                      placeholder="Search citizens, business, renter code..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      className="block w-full pl-10 pr-3 py-2.5 border rounded-lg bg-white"
                      style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                    />
                  </div>
                </div>

                {/* Business Type Filter */}
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Business Type</label>
                  <div className="relative">
                    <Building className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                             style={{ color: COLORS.secondary }} />
                    <select
                      value={businessTypeFilter}
                      onChange={(e) => setBusinessTypeFilter(e.target.value)}
                      className="block w-full pl-10 pr-10 py-2.5 border rounded-lg bg-white appearance-none"
                      style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                    >
                      <option value="all">All Business Types</option>
                      {getBusinessTypes().map(type => (
                        <option key={type} value={type}>{type}</option>
                      ))}
                    </select>
                  </div>
                </div>

                {/* Status Filter */}
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Status</label>
                  <div className="relative">
                    <ShieldCheck className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                                style={{ color: COLORS.secondary }} />
                    <select
                      value={statusFilter}
                      onChange={(e) => setStatusFilter(e.target.value)}
                      className="block w-full pl-10 pr-10 py-2.5 border rounded-lg bg-white appearance-none"
                      style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                    >
                      <option value="all">All Status</option>
                      <option value="active">Active</option>
                      <option value="pending">Pending</option>
                      <option value="approved">Approved</option>
                      <option value="inactive">Inactive</option>
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
                <span>Showing all approved citizens</span>
              )}
            </div>
            <div className="font-medium" style={{ color: COLORS.dark }}>
              {filteredCitizens.length} of {citizens.length} citizens
            </div>
          </div>
        </div>

        {/* Filtered Summary Banner */}
        {searchTerm || businessTypeFilter !== 'all' || statusFilter !== 'all' ? (
          <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div className="text-center">
                <p className="text-sm" style={{ color: COLORS.dark }}>Filtered Citizens</p>
                <p className="text-xl font-bold" style={{ color: COLORS.primary }}>
                  {filteredTotals.count} / {totals.total_citizens}
                </p>
                <p className="text-xs" style={{ color: COLORS.secondary }}>{filteredTotals.active_citizens} active</p>
              </div>
              <div className="text-center">
                <p className="text-sm" style={{ color: COLORS.dark }}>Filtered Monthly Rent</p>
                <p className="text-xl font-bold" style={{ color: COLORS.success }}>
                  {formatCurrency(filteredTotals.total_monthly_rent)}
                </p>
                <p className="text-xs" style={{ color: COLORS.secondary }}>
                  Avg: {formatCurrency(filteredTotals.count > 0 ? filteredTotals.total_monthly_rent / filteredTotals.count : 0)}
                </p>
              </div>
              <div className="text-center">
                <p className="text-sm" style={{ color: COLORS.dark }}>Filtered Contract Value</p>
                <p className="text-xl font-bold" style={{ color: '#6b46c1' }}>
                  {formatCurrency(filteredTotals.total_contract_value)}
                </p>
              </div>
              <div className="text-center">
                <p className="text-sm" style={{ color: COLORS.dark }}>Completion</p>
                <p className="text-xl font-bold" style={{ color: COLORS.warning }}>
                  {citizens.length > 0 ? ((filteredTotals.count / citizens.length) * 100).toFixed(1) : 0}%
                </p>
                <p className="text-xs" style={{ color: COLORS.secondary }}>of total</p>
              </div>
            </div>
          </div>
        ) : null}

        {/* Citizens Table */}
        <div className="bg-white border rounded-xl shadow-sm" style={{ borderColor: COLORS.secondary }}>
          <div className="p-6 border-b" style={{ borderColor: COLORS.secondary }}>
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
              <div>
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <Users className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Market Citizens ({filteredCitizens.length})
                </h3>
                <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                  Approved market stall renters with contracts
                </p>
              </div>
              
              <div className="inline-flex items-center gap-2 px-3 py-1.5 border rounded-lg text-sm"
                   style={{ borderColor: COLORS.secondary, color: COLORS.secondary }}>
                <CheckCircle className="w-4 h-4" />
                <span>Approved citizens only</span>
              </div>
            </div>
          </div>
          
          {paginatedCitizens.length === 0 ? (
            <div className="text-center py-12" style={{ color: COLORS.secondary }}>
              <Users className="w-12 h-12 mx-auto mb-2" />
              <p className="text-sm font-medium" style={{ color: COLORS.dark }}>
                {searchTerm || businessTypeFilter !== 'all' || statusFilter !== 'all' 
                  ? "No matching citizens found" 
                  : "No approved citizens available"}
              </p>
              <p className="text-sm mt-1 max-w-xs mx-auto">
                {searchTerm 
                  ? "Try adjusting your search terms or clear filters"
                  : "No approved market citizens at this time"}
              </p>
              {(searchTerm || businessTypeFilter !== "all" || statusFilter !== "all") && (
                <button
                  onClick={() => {
                    setSearchTerm("");
                    setBusinessTypeFilter("all");
                    setStatusFilter("all");
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
                        Citizen Info
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Business Details
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Status
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Financials
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Contract
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {paginatedCitizens.map((citizen) => {
                      const statusBadge = getStatusBadge(citizen.status);
                      const StatusIcon = statusBadge.icon;
                      const contractMonths = citizen.contract_months || 0;
                      const monthlyTotals = parseFloat(citizen.monthly_totals) || 0;
                      
                      return (
                        <tr key={citizen.id} className="hover:bg-gray-50 transition-colors" 
                            style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}>
                          {/* Citizen Info */}
                          <td className="p-4">
                            <div className="space-y-2">
                              <div>
                                <p className="font-semibold" style={{ color: COLORS.dark }}>
                                  {citizen.full_name || 'No Name'}
                                </p>
                                <p className="text-sm" style={{ color: COLORS.secondary }}>
                                  {citizen.renter_code || 'No Code'}
                                </p>
                              </div>
                              <div className="space-y-1 text-xs">
                                <div className="flex items-center gap-2">
                                  <Mail className="w-3 h-3" style={{ color: COLORS.secondary }} />
                                  <span style={{ color: COLORS.dark }} className="truncate">
                                    {citizen.email || 'No email'}
                                  </span>
                                </div>
                                <div className="flex items-center gap-2">
                                  <Phone className="w-3 h-3" style={{ color: COLORS.secondary }} />
                                  <span style={{ color: COLORS.dark }}>{citizen.mobile || 'No phone'}</span>
                                </div>
                              </div>
                            </div>
                          </td>

                          {/* Business Details */}
                          <td className="p-4">
                            <div className="space-y-2">
                              <div>
                                <p className="font-medium" style={{ color: COLORS.dark }}>
                                  {citizen.business_name || 'No Business'}
                                </p>
                                <div className="flex flex-wrap gap-1 mt-1">
                                  {citizen.business_type && (
                                    <span className="text-xs px-2 py-1 rounded" 
                                          style={{ backgroundColor: `${COLORS.info}15`, color: COLORS.dark }}>
                                      {citizen.business_type}
                                    </span>
                                  )}
                                  {citizen.class_name && (
                                    <span className="text-xs px-2 py-1 rounded" 
                                          style={{ backgroundColor: `${COLORS.primary}15`, color: COLORS.dark }}>
                                      {citizen.class_name}
                                    </span>
                                  )}
                                </div>
                              </div>
                              <div className="flex items-center text-xs">
                                <MapPin className="w-3 h-3 mr-1" style={{ color: COLORS.secondary }} />
                                <span style={{ color: COLORS.dark }}>
                                  Stall: {citizen.stall_name || citizen.stall_rights_no || 'N/A'}
                                </span>
                              </div>
                            </div>
                          </td>

                          {/* Status */}
                          <td className="p-4">
                            <div className="flex items-center gap-3">
                              <div className={`p-2 rounded-lg ${statusBadge.bgColor}`}>
                                <StatusIcon className={`w-4 h-4 ${statusBadge.textColor}`} />
                              </div>
                              <div>
                                <span className={`text-xs font-medium px-3 py-1.5 rounded-full ${statusBadge.bgColor} ${statusBadge.textColor} border ${statusBadge.borderColor}`}>
                                  {statusBadge.text}
                                </span>
                                <div className="text-xs mt-1" style={{ color: COLORS.secondary }}>
                                  {formatDate(citizen.registration_date)}
                                </div>
                              </div>
                            </div>
                          </td>

                          {/* Financials */}
                          <td className="p-4">
                            <div className="space-y-3">
                              <div>
                                <p className="text-sm font-semibold" style={{ color: COLORS.dark }}>
                                  {formatCurrency(citizen.monthly_rent)}
                                </p>
                                <p className="text-xs" style={{ color: COLORS.secondary }}>Monthly Rent</p>
                              </div>
                              {monthlyTotals > 0 && (
                                <div className="pt-2 border-t" style={{ borderColor: COLORS.secondary }}>
                                  <p className="text-sm font-bold" style={{ color: COLORS.success }}>
                                    {formatCurrency(monthlyTotals)}
                                  </p>
                                  <p className="text-xs" style={{ color: COLORS.secondary }}>Contract Total</p>
                                </div>
                              )}
                            </div>
                          </td>

                          {/* Contract */}
                          <td className="p-4">
                            <div className="space-y-2">
                              {contractMonths > 0 ? (
                                <>
                                  <div className="flex items-center text-sm">
                                    <Calendar className="w-4 h-4 mr-2" style={{ color: COLORS.secondary }} />
                                    <span style={{ color: COLORS.dark }}>{contractMonths} months</span>
                                  </div>
                                  <div className="text-xs space-y-1" style={{ color: COLORS.secondary }}>
                                    <div>From: {formatDate(citizen.contract_start)}</div>
                                    <div>To: {formatDate(citizen.contract_end)}</div>
                                  </div>
                                </>
                              ) : (
                                <div className="text-sm" style={{ color: COLORS.secondary }}>No contract details</div>
                              )}
                            </div>
                          </td>

                          {/* Actions */}
                          <td className="p-4">
                            <button
                              onClick={() => navigate(`/market/marketstatusinfo/${citizen.renter_code || citizen.id}`)}
                              className="px-4 py-2 rounded-lg flex items-center gap-2 transition-all"
                              style={{ backgroundColor: COLORS.primary, color: 'white' }}
                            >
                              <Eye className="w-4 h-4" />
                              View Details
                            </button>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
              
              {/* Table Footer & Pagination */}
              <div className="p-4 border-t" style={{ borderColor: COLORS.secondary, backgroundColor: `${COLORS.background}` }}>
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div className="text-sm" style={{ color: COLORS.secondary }}>
                    Showing <span className="font-semibold" style={{ color: COLORS.dark }}>{startIndex + 1}</span> to{" "}
                    <span className="font-semibold" style={{ color: COLORS.dark }}>{Math.min(endIndex, filteredCitizens.length)}</span> of{" "}
                    <span className="font-semibold" style={{ color: COLORS.dark }}>{filteredCitizens.length}</span> citizens
                  </div>
                  
                  {/* Pagination */}
                  {totalPages > 1 && (
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
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
                              onClick={() => setCurrentPage(pageNumber)}
                              className={`px-3 py-1 text-sm rounded ${
                                currentPage === pageNumber ? 'text-white' : 'border hover:bg-gray-50'
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
                        onClick={() => setCurrentPage(prev => Math.min(prev + 1, totalPages))}
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
          <p>Market Citizens Management System • {new Date().toLocaleDateString('en-PH')}</p>
          <p className="text-xs mt-1">
            Local Government Unit - Market Stall Revenue Management
          </p>
        </div>
      </div>
    </div>
  );
}