import React, { useState, useEffect } from 'react';
import { 
  Home, Building, DollarSign, Calendar, Clock, 
  AlertCircle, CheckCircle, FileText, MapPin, RefreshCw,
  CreditCard, Users, TrendingUp, Eye, FileWarning,
  ChevronRight, ArrowUpRight, ArrowDownRight
} from 'lucide-react';

// Environment detection
const isLocalhost = window.location.hostname === "localhost" || 
                    window.location.hostname === "127.0.0.1";
const API_BASE = isLocalhost
  ? "http://localhost/revenue2/backend"
  : "https://revenuetreasury.goserveph.com/backend";

const DASHBOARD_API = `${API_BASE}/RPT/RPTDashboard/dashboard_api.php`;

const RPTDashboard = () => {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [data, setData] = useState({});
  const [lastRefresh, setLastRefresh] = useState(new Date());

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const fetchDashboardData = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const url = `${DASHBOARD_API}`;
      
      const response = await fetch(url);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const result = await response.json();
      
      if (!result.success) {
        throw new Error(result.error || 'Failed to fetch dashboard data');
      }

      setData(result.data);
      setLastRefresh(new Date());

    } catch (err) {
      console.error('Dashboard Error:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (amount) => {
    if (amount === null || amount === undefined || isNaN(amount)) return '₱0';
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(amount);
  };

  const formatNumber = (num) => {
    if (num === null || num === undefined || isNaN(num)) return '0';
    return new Intl.NumberFormat('en-PH').format(num);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p className="text-gray-600">Loading RPT Dashboard...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-6">
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <div className="flex items-center gap-2 mb-2">
            <AlertCircle className="text-red-600" size={20} />
            <h3 className="font-semibold text-red-800">Error Loading Dashboard</h3>
          </div>
          <p className="text-red-700 mb-3">{error}</p>
          <button
            onClick={fetchDashboardData}
            className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 flex items-center gap-2"
          >
            <RefreshCw size={16} />
            Try Again
          </button>
        </div>
      </div>
    );
  }

  // Based on your database, you have:
  // 1 property registration, 1 land property, 1 building property
  // 4 quarterly taxes (2 paid, 2 pending)
  
  const stats = data.stats || {};
  const pending = data.pending_items || {};
  const recentPayments = data.recent_payments || [];
  const recentRegistrations = data.recent_registrations || [];

  // Calculate collection rate from actual data
  const paidCount = stats.payments?.paid_count || 0;
  const pendingCount = stats.payments?.pending_count || 0;
  const overdueCount = stats.payments?.overdue_count || 0;
  const totalPayments = paidCount + pendingCount + overdueCount;
  const collectionRate = totalPayments > 0 ? Math.round((paidCount / totalPayments) * 100) : 0;

  return (
    <div className="p-4">
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-800">Real Property Tax Dashboard</h1>
        <div className="flex items-center justify-between mt-2">
          <p className="text-gray-600">Overview of property tax assessment and collection</p>
          <div className="flex items-center gap-2">
            <span className="text-sm text-gray-500">
              Updated: {lastRefresh.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
            </span>
            <button
              onClick={fetchDashboardData}
              className="p-1 hover:bg-gray-100 rounded"
              title="Refresh"
            >
              <RefreshCw size={16} className="text-gray-600" />
            </button>
          </div>
        </div>
      </div>

      {/* Main Stats - Based on your actual data */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {/* Total Properties */}
        <div className="bg-white p-4 rounded-lg border border-gray-200">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-blue-50 rounded">
              <Home className="text-blue-600" size={20} />
            </div>
            <div>
              <p className="text-sm text-gray-600">Total Properties</p>
              <p className="text-xl font-bold text-gray-800">
                {formatNumber(stats.properties?.total_properties || 0)}
              </p>
            </div>
          </div>
          <div className="mt-3 text-sm">
            <div className="flex justify-between">
              <span className="text-gray-600">Lands:</span>
              <span className="font-medium">{formatNumber(stats.properties?.total_lands || 0)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">Buildings:</span>
              <span className="font-medium">{formatNumber(stats.properties?.total_buildings || 0)}</span>
            </div>
          </div>
        </div>

        {/* Total Revenue */}
        <div className="bg-white p-4 rounded-lg border border-gray-200">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-green-50 rounded">
              <DollarSign className="text-green-600" size={20} />
            </div>
            <div>
              <p className="text-sm text-gray-600">Total Annual Tax</p>
              <p className="text-xl font-bold text-gray-800">
                {formatCurrency(stats.revenue?.total_annual_tax || 0)}
              </p>
            </div>
          </div>
          <div className="mt-3 text-sm">
            <div className="flex justify-between">
              <span className="text-gray-600">Collected:</span>
              <span className="font-medium text-green-600">
                {formatCurrency(stats.revenue?.collected_this_year || 0)}
              </span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">Pending:</span>
              <span className="font-medium text-yellow-600">
                {formatCurrency(stats.revenue?.pending_this_year || 0)}
              </span>
            </div>
          </div>
        </div>

        {/* Collection Rate */}
        <div className="bg-white p-4 rounded-lg border border-gray-200">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-purple-50 rounded">
              <TrendingUp className="text-purple-600" size={20} />
            </div>
            <div>
              <p className="text-sm text-gray-600">Collection Rate</p>
              <p className="text-xl font-bold text-gray-800">{collectionRate}%</p>
            </div>
          </div>
          <div className="mt-3">
            <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
              <div 
                className="h-full bg-green-500 rounded-full"
                style={{ width: `${collectionRate}%` }}
              ></div>
            </div>
            <div className="flex justify-between text-xs text-gray-500 mt-1">
              <span>Paid: {paidCount}</span>
              <span>Pending: {pendingCount}</span>
              <span>Overdue: {overdueCount}</span>
            </div>
          </div>
        </div>

        {/* Active Owners */}
        <div className="bg-white p-4 rounded-lg border border-gray-200">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-yellow-50 rounded">
              <Users className="text-yellow-600" size={20} />
            </div>
            <div>
              <p className="text-sm text-gray-600">Property Owners</p>
              <p className="text-xl font-bold text-gray-800">
                {formatNumber(stats.owners?.total_owners || 0)}
              </p>
            </div>
          </div>
          <div className="mt-3 text-sm">
            <div className="flex justify-between">
              <span className="text-gray-600">Active:</span>
              <span className="font-medium text-green-600">{formatNumber(stats.owners?.active_owners || 0)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">Pending:</span>
              <span className="font-medium text-yellow-600">{formatNumber(stats.owners?.pending_owners || 0)}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Alerts Section */}
      <div className="mb-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-3">Alerts & Notifications</h2>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {/* Overdue Payments */}
          {pending.overdue_payments && pending.overdue_payments.length > 0 ? (
            <div className="bg-red-50 border border-red-200 rounded-lg p-4">
              <div className="flex items-center gap-2 mb-2">
                <AlertCircle className="text-red-600" size={18} />
                <h3 className="font-medium text-red-800">Overdue Payments</h3>
              </div>
              <p className="text-sm text-red-700 mb-2">
                {pending.overdue_payments.length} payments are overdue
              </p>
              <div className="text-center">
                <p className="text-lg font-bold text-red-800">
                  {formatCurrency(
                    pending.overdue_payments.reduce((sum, item) => sum + (parseFloat(item.total_quarterly_tax) || 0), 0)
                  )}
                </p>
                <p className="text-xs text-red-600">Total overdue amount</p>
              </div>
            </div>
          ) : (
            <div className="bg-green-50 border border-green-200 rounded-lg p-4">
              <div className="flex items-center gap-2">
                <CheckCircle className="text-green-600" size={18} />
                <h3 className="font-medium text-green-800">No Overdue Payments</h3>
              </div>
              <p className="text-sm text-green-700 mt-1">All payments are up to date</p>
            </div>
          )}

          {/* Pending Registrations */}
          {pending.registrations && pending.registrations.length > 0 ? (
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <div className="flex items-center gap-2 mb-2">
                <FileWarning className="text-yellow-600" size={18} />
                <h3 className="font-medium text-yellow-800">Pending Registrations</h3>
              </div>
              <p className="text-sm text-yellow-700 mb-2">
                {pending.registrations.length} registrations awaiting approval
              </p>
              <div className="space-y-1">
                {pending.registrations.slice(0, 2).map((item, index) => (
                  <div key={index} className="flex justify-between text-sm">
                    <span className="text-yellow-800">{item.reference_number}</span>
                    <span className="font-medium">{item.days_pending} days</span>
                  </div>
                ))}
              </div>
            </div>
          ) : (
            <div className="bg-green-50 border border-green-200 rounded-lg p-4">
              <div className="flex items-center gap-2">
                <CheckCircle className="text-green-600" size={18} />
                <h3 className="font-medium text-green-800">No Pending Registrations</h3>
              </div>
              <p className="text-sm text-green-700 mt-1">All registrations are processed</p>
            </div>
          )}

          {/* Upcoming Inspections */}
          {pending.inspections && pending.inspections.length > 0 ? (
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <div className="flex items-center gap-2 mb-2">
                <Calendar className="text-blue-600" size={18} />
                <h3 className="font-medium text-blue-800">Upcoming Inspections</h3>
              </div>
              <p className="text-sm text-blue-700 mb-2">
                {pending.inspections.length} inspections scheduled
              </p>
              <div className="space-y-1">
                {pending.inspections.slice(0, 2).map((item, index) => (
                  <div key={index} className="flex justify-between text-sm">
                    <span className="text-blue-800">{item.reference_number}</span>
                    <span className="font-medium">{item.scheduled_date}</span>
                  </div>
                ))}
              </div>
            </div>
          ) : (
            <div className="bg-green-50 border border-green-200 rounded-lg p-4">
              <div className="flex items-center gap-2">
                <CheckCircle className="text-green-600" size={18} />
                <h3 className="font-medium text-green-800">No Upcoming Inspections</h3>
              </div>
              <p className="text-sm text-green-700 mt-1">No inspections scheduled</p>
            </div>
          )}
        </div>
      </div>

      {/* Recent Data Section */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Payments */}
        <div className="bg-white rounded-lg border border-gray-200">
          <div className="p-4 border-b border-gray-200">
            <h3 className="font-semibold text-gray-800 flex items-center gap-2">
              <CreditCard size={18} className="text-blue-600" />
              Recent Tax Payments
            </h3>
          </div>
          <div className="p-4">
            {recentPayments.length > 0 ? (
              <div className="space-y-3">
                {recentPayments.map((payment, index) => (
                  <div key={index} className="flex items-center justify-between p-3 hover:bg-gray-50 rounded">
                    <div>
                      <div className="font-medium text-gray-900">{payment.reference_number}</div>
                      <div className="text-sm text-gray-600">{payment.owner}</div>
                    </div>
                    <div className="text-right">
                      <div className="font-bold text-green-700">{formatCurrency(payment.total_quarterly_tax)}</div>
                      <div className="text-xs text-gray-500">
                        {payment.quarter} {payment.year} • {payment.payment_date}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center py-6 text-gray-500">
                <CreditCard size={24} className="mx-auto mb-2 text-gray-400" />
                <p>No recent payments found</p>
              </div>
            )}
          </div>
        </div>

        {/* Recent Registrations */}
        <div className="bg-white rounded-lg border border-gray-200">
          <div className="p-4 border-b border-gray-200">
            <h3 className="font-semibold text-gray-800 flex items-center gap-2">
              <FileText size={18} className="text-green-600" />
              Recent Property Registrations
            </h3>
          </div>
          <div className="p-4">
            {recentRegistrations.length > 0 ? (
              <div className="space-y-3">
                {recentRegistrations.map((registration, index) => (
                  <div key={index} className="p-3 hover:bg-gray-50 rounded">
                    <div className="flex justify-between items-start mb-2">
                      <div>
                        <div className="font-medium text-gray-900">{registration.reference_number}</div>
                        <div className="text-sm text-gray-600">{registration.owner}</div>
                      </div>
                      <span className={`px-2 py-1 text-xs rounded ${
                        registration.status === 'approved' ? 'bg-green-100 text-green-800' :
                        registration.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-gray-100 text-gray-800'
                      }`}>
                        {registration.status}
                      </span>
                    </div>
                    <div className="flex items-center gap-4 text-sm text-gray-600">
                      <div className="flex items-center gap-1">
                        <MapPin size={14} />
                        {registration.barangay}
                      </div>
                      <div className="flex items-center gap-1">
                        <Home size={14} />
                        {registration.land_count} land
                      </div>
                      <div className="flex items-center gap-1">
                        <Building size={14} />
                        {registration.building_count} building
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center py-6 text-gray-500">
                <FileText size={24} className="mx-auto mb-2 text-gray-400" />
                <p>No recent registrations found</p>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Summary Section */}
      <div className="mt-6 bg-gray-50 rounded-lg p-4 border border-gray-200">
        <h3 className="font-semibold text-gray-800 mb-3">System Summary</h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <p className="text-gray-600">Total Barangays</p>
            <p className="font-bold text-gray-800">{stats.barangay?.total_barangays || 0}</p>
          </div>
          <div>
            <p className="text-gray-600">Completed Inspections</p>
            <p className="font-bold text-gray-800">{formatNumber(stats.inspections?.completed || 0)}</p>
          </div>
          <div>
            <p className="text-gray-600">Scheduled Inspections</p>
            <p className="font-bold text-gray-800">{formatNumber(stats.inspections?.scheduled || 0)}</p>
          </div>
          <div>
            <p className="text-gray-600">Environment</p>
            <p className={`font-bold ${isLocalhost ? 'text-yellow-600' : 'text-green-600'}`}>
              {isLocalhost ? 'Development' : 'Production'}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default RPTDashboard;