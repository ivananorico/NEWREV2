import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Search,
  Download,
  Eye,
  User,
  Mail,
  Phone,
  Building,
  DollarSign,
  TrendingUp,
  Home,
  Calendar,
  ShieldCheck,
  FileText,
  MapPin,
  Award,
  Clock,
  Users,
  Filter,
  RefreshCw,
  CheckCircle,
  AlertCircle,
  Store,
  BarChart3,
  Target,
  TrendingDown,
  Layers,
  Briefcase,
  Hash
} from 'lucide-react';

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
  const navigate = useNavigate();

  // API Base URL
  const isProduction = window.location.hostname.includes('goserveph.com');
  const API_BASE = isProduction 
    ? "/backend/Market/MarketStatus"
    : "http://localhost/revenue2/backend/Market/MarketStatus";

  useEffect(() => {
    loadData();
  }, []); // Empty dependency array to load once on mount

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

  const formatCurrency = (amount) => {
    const num = parseFloat(amount) || 0;
    if (num >= 1000000) {
      return `₱${(num / 1000000).toFixed(1)}M`;
    }
    if (num >= 1000) {
      return `₱${(num / 1000).toFixed(1)}K`;
    }
    return `₱${num.toFixed(2)}`;
  };

  const formatLargeNumber = (num) => {
    if (num >= 1000000) return `${(num / 1000000).toFixed(1)}M`;
    if (num >= 1000) return `${(num / 1000).toFixed(1)}K`;
    return num.toString();
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
          textColor: 'text-green-700',
          icon: CheckCircle
        };
      case 'pending':
        return {
          text: 'Pending',
          bgColor: 'bg-yellow-50',
          textColor: 'text-yellow-700',
          icon: Clock
        };
      case 'approved':
        return {
          text: 'Approved',
          bgColor: 'bg-blue-50',
          textColor: 'text-blue-700',
          icon: ShieldCheck
        };
      case 'inactive':
        return {
          text: 'Inactive',
          bgColor: 'bg-gray-50',
          textColor: 'text-gray-700',
          icon: AlertCircle
        };
      default:
        return {
          text: status || 'N/A',
          bgColor: 'bg-gray-50',
          textColor: 'text-gray-700',
          icon: AlertCircle
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
      <div className="flex items-center justify-center min-h-screen bg-gray-50 dark:bg-gray-900">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600 dark:text-gray-400">Loading market citizens...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 p-4 lg:p-6">
      {/* Header */}
      <div className="mb-6">
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-800 dark:text-white">Market Citizens Dashboard</h1>
            <p className="text-gray-600 dark:text-gray-400 mt-1">Manage and monitor market stall renters with financial totals</p>
          </div>
          <button
            onClick={loadData}
            disabled={loading}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-2 disabled:opacity-50 text-sm"
          >
            <RefreshCw className="w-4 h-4" />
            Refresh
          </button>
        </div>
      </div>

      {/* Summary Cards - Main Totals */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        {/* Total Citizens */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center">
            <div className="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
              <Users className="w-5 h-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div className="ml-3">
              <p className="text-xs text-gray-500 dark:text-gray-400">Total Citizens</p>
              <p className="text-lg font-bold text-gray-800 dark:text-white">
                {totals.total_citizens}
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                {totals.active_citizens} Active
              </p>
            </div>
          </div>
        </div>

        {/* Monthly Rent Total */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center">
            <div className="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
              <DollarSign className="w-5 h-5 text-green-600 dark:text-green-400" />
            </div>
            <div className="ml-3">
              <p className="text-xs text-gray-500 dark:text-gray-400">Monthly Rent</p>
              <p className="text-lg font-bold text-gray-800 dark:text-white">
                {formatCurrency(totals.total_monthly_rent)}
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Avg: {formatCurrency(totals.average_monthly_rent)}
              </p>
            </div>
          </div>
        </div>

        {/* Contract Value Total */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center">
            <div className="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg">
              <BarChart3 className="w-5 h-5 text-purple-600 dark:text-purple-400" />
            </div>
            <div className="ml-3">
              <p className="text-xs text-gray-500 dark:text-gray-400">Total Contract Value</p>
              <p className="text-lg font-bold text-gray-800 dark:text-white">
                {formatCurrency(totals.total_contract_value)}
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Per citizen: {formatCurrency(totals.average_contract_value)}
              </p>
            </div>
          </div>
        </div>

        {/* Business Types */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center">
            <div className="p-2 bg-orange-100 dark:bg-orange-900 rounded-lg">
              <Briefcase className="w-5 h-5 text-orange-600 dark:text-orange-400" />
            </div>
            <div className="ml-3">
              <p className="text-xs text-gray-500 dark:text-gray-400">Business Types</p>
              <p className="text-lg font-bold text-gray-800 dark:text-white">
                {totals.total_business_types}
              </p>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                {totals.total_stall_classes} Stall Classes
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Filtered Summary */}
      {searchTerm || businessTypeFilter !== 'all' || statusFilter !== 'all' ? (
        <div className="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
          <div className="flex flex-wrap gap-4">
            <div className="text-center">
              <p className="text-xs text-blue-600 dark:text-blue-400">Filtered Citizens</p>
              <p className="text-lg font-bold text-blue-700 dark:text-blue-300">
                {filteredTotals.count} / {totals.total_citizens}
              </p>
            </div>
            <div className="text-center">
              <p className="text-xs text-blue-600 dark:text-blue-400">Filtered Monthly Rent</p>
              <p className="text-lg font-bold text-blue-700 dark:text-blue-300">
                {formatCurrency(filteredTotals.total_monthly_rent)}
              </p>
            </div>
            <div className="text-center">
              <p className="text-xs text-blue-600 dark:text-blue-400">Filtered Contract Value</p>
              <p className="text-lg font-bold text-blue-700 dark:text-blue-300">
                {formatCurrency(filteredTotals.total_contract_value)}
              </p>
            </div>
          </div>
        </div>
      ) : null}

      {/* Filters */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-4 border border-gray-200 dark:border-gray-700">
        <div className="flex flex-col lg:flex-row gap-3">
          <div className="flex-1">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={18} />
              <input
                type="text"
                placeholder="Search citizens, business, renter code..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="relative">
              <Filter className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={16} />
              <select
                value={businessTypeFilter}
                onChange={(e) => setBusinessTypeFilter(e.target.value)}
                className="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white appearance-none"
              >
                <option value="all">All Business Types</option>
                {getBusinessTypes().map(type => (
                  <option key={type} value={type}>{type}</option>
                ))}
              </select>
            </div>

            <div className="relative">
              <ShieldCheck className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={16} />
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white appearance-none"
              >
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>

          <button
            onClick={exportToCSV}
            className="flex items-center justify-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors text-sm"
          >
            <Download size={16} />
            Export CSV
          </button>
        </div>
      </div>

      {/* Citizens List */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50 dark:bg-gray-900">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Citizen Info
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Business Details
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Financials
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Contract
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
              {filteredCitizens.map((citizen) => {
                const statusBadge = getStatusBadge(citizen.status);
                const StatusIcon = statusBadge.icon;
                const contractMonths = citizen.contract_months || 0;
                const monthlyTotals = parseFloat(citizen.monthly_totals) || 0;
                
                return (
                  <tr key={citizen.id} className="hover:bg-gray-50 dark:hover:bg-gray-900">
                    {/* Citizen Info */}
                    <td className="px-4 py-4">
                      <div className="space-y-1">
                        <p className="font-semibold text-gray-900 dark:text-white">{citizen.full_name || 'No Name'}</p>
                        <p className="text-gray-600 dark:text-gray-400 text-xs">{citizen.renter_code || 'No Code'}</p>
                        <div className="flex flex-col gap-1 pt-1">
                          <div className="flex items-center gap-2 text-xs">
                            <Mail className="w-3 h-3 text-gray-400" />
                            <span className="text-gray-500 dark:text-gray-400 truncate">{citizen.email || 'No email'}</span>
                          </div>
                          <div className="flex items-center gap-2 text-xs">
                            <Phone className="w-3 h-3 text-gray-400" />
                            <span className="text-gray-500 dark:text-gray-400">{citizen.mobile || 'No phone'}</span>
                          </div>
                        </div>
                      </div>
                    </td>

                    {/* Business Details */}
                    <td className="px-4 py-4">
                      <div className="space-y-1">
                        <div className="flex items-start gap-2">
                          <Building className="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" />
                          <div>
                            <p className="text-gray-900 dark:text-white">{citizen.business_name || 'No Business'}</p>
                            <div className="flex gap-1 mt-1">
                              {citizen.business_type && (
                                <span className="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded">
                                  {citizen.business_type}
                                </span>
                              )}
                              {citizen.class_name && (
                                <span className="text-xs px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 rounded">
                                  {citizen.class_name}
                                </span>
                              )}
                            </div>
                          </div>
                        </div>
                        <div className="flex items-center text-gray-500 dark:text-gray-400 text-xs pt-1">
                          <MapPin className="w-3 h-3 mr-1 flex-shrink-0" />
                          <span className="truncate">Stall: {citizen.stall_name || citizen.stall_rights_no || 'N/A'}</span>
                        </div>
                      </div>
                    </td>

                    {/* Status */}
                    <td className="px-4 py-4">
                      <div className="flex items-center">
                        <div className={`inline-flex items-center gap-2 px-3 py-2 rounded-lg ${statusBadge.bgColor} ${statusBadge.textColor}`}>
                          <StatusIcon className="w-4 h-4 flex-shrink-0" />
                          <span className="text-sm font-semibold whitespace-nowrap">{statusBadge.text}</span>
                        </div>
                      </div>
                    </td>

                    {/* Financials */}
                    <td className="px-4 py-4">
                      <div className="space-y-2">
                        <div>
                          <p className="text-sm font-semibold text-blue-700 dark:text-blue-400">
                            {formatCurrency(citizen.monthly_rent)}
                          </p>
                          <p className="text-gray-500 dark:text-gray-400 text-xs">Monthly Rent</p>
                        </div>
                        {monthlyTotals > 0 && (
                          <div className="border-t pt-2">
                            <p className="text-sm font-bold text-green-700 dark:text-green-400">
                              {formatCurrency(monthlyTotals)}
                            </p>
                            <p className="text-gray-500 dark:text-gray-400 text-xs">Contract Total</p>
                          </div>
                        )}
                      </div>
                    </td>

                    {/* Contract */}
                    <td className="px-4 py-4">
                      <div className="space-y-1">
                        {contractMonths > 0 ? (
                          <>
                            <div className="flex items-center text-gray-700 dark:text-gray-300 text-sm">
                              <Calendar className="w-4 h-4 mr-2 flex-shrink-0" />
                              <span>{contractMonths} months</span>
                            </div>
                            <div className="text-xs text-gray-500 space-y-0.5">
                              <div>From: {formatDate(citizen.contract_start)}</div>
                              <div>To: {formatDate(citizen.contract_end)}</div>
                            </div>
                          </>
                        ) : (
                          <div className="text-gray-400 text-sm">No contract details</div>
                        )}
                      </div>
                    </td>

                    {/* Actions */}
                    <td className="px-4 py-4">
                      <div className="flex items-center justify-center">
                        <button
                          onClick={() => navigate(`/market/marketstatusinfo/${citizen.renter_code || citizen.id}`)}
                          className="inline-flex items-center justify-center gap-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-xs"
                        >
                          <Eye size={12} />
                          View Details
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>

          {filteredCitizens.length === 0 && (
            <div className="text-center py-8">
              <Store className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
              <p className="text-gray-500 dark:text-gray-400">No market citizens found</p>
              <p className="text-gray-400 dark:text-gray-500 text-sm mt-1">
                {searchTerm || businessTypeFilter !== 'all' || statusFilter !== 'all'
                  ? 'Try adjusting your filters'
                  : 'No approved market citizens available'}
              </p>
            </div>
          )}
        </div>
      </div>

      {/* Footer Summary */}
      <div className="mt-4 p-4 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="text-center">
            <p className="text-xs text-gray-500 dark:text-gray-400">Total Citizens</p>
            <p className="text-lg font-bold text-gray-800 dark:text-white">{totals.total_citizens}</p>
          </div>
          
          <div className="text-center">
            <p className="text-xs text-gray-500 dark:text-gray-400">Monthly Rent Total</p>
            <p className="text-lg font-bold text-blue-600 dark:text-blue-400">
              {formatCurrency(totals.total_monthly_rent)}
            </p>
          </div>
          
          <div className="text-center">
            <p className="text-xs text-gray-500 dark:text-gray-400">Contract Value Total</p>
            <p className="text-lg font-bold text-green-600 dark:text-green-400">
              {formatCurrency(totals.total_contract_value)}
            </p>
          </div>
          
          <div className="text-center">
            <p className="text-xs text-gray-500 dark:text-gray-400">Average Per Citizen</p>
            <p className="text-lg font-bold text-purple-600 dark:text-purple-400">
              {formatCurrency(totals.average_contract_value)}
            </p>
          </div>
        </div>
        
        <div className="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 text-center">
          <p className="text-xs text-gray-500 dark:text-gray-400">
            Last updated: {new Date().toLocaleTimeString('en-PH', { 
              hour: '2-digit', 
              minute: '2-digit' 
            })}
          </p>
        </div>
      </div>
    </div>
  );
}