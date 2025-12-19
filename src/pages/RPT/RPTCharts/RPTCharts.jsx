import React, { useState, useEffect } from 'react';
import {
  BarChart, Bar, PieChart, Pie, Cell, LineChart, Line,
  XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer
} from 'recharts';
import {
  RefreshCw, DollarSign, Home, TrendingUp, Building,
  AlertCircle, CheckCircle, CreditCard, Calendar,
  MapPin, Users, BarChart as BarChartIcon, PieChart as PieChartIcon
} from 'lucide-react';

// Environment detection
const isLocalhost = window.location.hostname === "localhost" || 
                    window.location.hostname === "127.0.0.1";
const API_BASE = isLocalhost
  ? "http://localhost/revenue2/backend"
  : "https://revenuetreasury.goserveph.com/backend";

const CHARTS_API = `${API_BASE}/RPT/RPTCharts/RPTCharts.php`;

const RPTCharts = () => {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [data, setData] = useState({});
  const [lastUpdate, setLastUpdate] = useState(new Date());

  const COLORS = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6'];

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(CHARTS_API);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const result = await response.json();
      
      if (!result.success) {
        throw new Error(result.error || 'Failed to fetch chart data');
      }

      setData(result.data);
      setLastUpdate(new Date());
    } catch (err) {
      console.error('Chart Error:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (amount) => {
    if (!amount && amount !== 0) return '₱0';
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(amount);
  };

  const formatNumber = (num) => {
    if (!num && num !== 0) return '0';
    return new Intl.NumberFormat('en-PH').format(num);
  };

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center h-screen bg-gray-50">
        <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-600 mb-4"></div>
        <h2 className="text-lg font-semibold text-gray-700">Loading Revenue Analytics</h2>
        <p className="text-gray-500 text-sm mt-1">Preparing charts and data...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-6 bg-gray-50 min-h-screen">
        <div className="max-w-4xl mx-auto">
          <div className="bg-red-50 border border-red-200 rounded-xl p-6">
            <div className="flex items-center gap-3 mb-4">
              <AlertCircle className="text-red-600" size={24} />
              <h3 className="text-lg font-semibold text-red-800">Chart Data Error</h3>
            </div>
            <p className="text-red-700 mb-4">Unable to load chart data:</p>
            <p className="text-red-600 bg-red-100 p-3 rounded mb-6 font-mono text-sm">{error}</p>
            <div className="flex gap-3">
              <button
                onClick={fetchData}
                className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center gap-2"
              >
                <RefreshCw size={16} />
                Retry Connection
              </button>
              <button
                onClick={() => window.location.reload()}
                className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
              >
                Reload Page
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  const summary = data.summary || {};
  const revenueByBarangay = data.revenue_by_barangay || [];
  const revenueByPropertyType = data.revenue_by_property_type || [];
  const quarterlyCollection = data.quarterly_collection || [];
  const topProperties = data.top_properties || [];
  const annualTrend = data.annual_revenue_trend || [];
  const paymentStatus = data.payment_status || [];

  // Calculate percentage for collection
  const totalTaxDue = quarterlyCollection.reduce((sum, q) => sum + (parseFloat(q.total_tax) || 0), 0);
  const totalCollected = quarterlyCollection.reduce((sum, q) => sum + (parseFloat(q.collected) || 0), 0);
  const collectionPercentage = totalTaxDue > 0 ? (totalCollected / totalTaxDue * 100).toFixed(1) : 0;

  return (
    <div className="bg-gray-50 min-h-screen p-4 md:p-6">
      {/* Header */}
      <div className="mb-6">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">RPT Revenue Analytics Dashboard</h1>
            <p className="text-gray-600">Visual insights into property tax revenue and collections</p>
          </div>
          <div className="flex items-center gap-3">
            <div className="text-sm text-gray-500">
              Last updated: {lastUpdate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
            </div>
            <button
              onClick={fetchData}
              disabled={loading}
              className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
            >
              <RefreshCw size={16} className={loading ? 'animate-spin' : ''} />
              {loading ? 'Refreshing...' : 'Refresh Data'}
            </button>
          </div>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-3">
            <div className="p-2 bg-blue-50 rounded">
              <DollarSign className="text-blue-600" size={20} />
            </div>
            <span className="text-xs font-medium px-2 py-1 bg-blue-100 text-blue-800 rounded-full">
              Revenue
            </span>
          </div>
          <h3 className="text-2xl font-bold text-gray-900 mb-1">
            {formatCurrency(summary.total_annual_revenue || 0)}
          </h3>
          <p className="text-sm text-gray-600">Total Annual Tax Assessment</p>
        </div>
        
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-3">
            <div className="p-2 bg-green-50 rounded">
              <Home className="text-green-600" size={20} />
            </div>
            <span className="text-xs font-medium px-2 py-1 bg-green-100 text-green-800 rounded-full">
              Properties
            </span>
          </div>
          <h3 className="text-2xl font-bold text-gray-900 mb-1">
            {formatNumber(summary.total_properties || 0)}
          </h3>
          <p className="text-sm text-gray-600">Registered Properties</p>
        </div>
        
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-3">
            <div className="p-2 bg-purple-50 rounded">
              <TrendingUp className="text-purple-600" size={20} />
            </div>
            <span className="text-xs font-medium px-2 py-1 bg-purple-100 text-purple-800 rounded-full">
              Collection
            </span>
          </div>
          <h3 className="text-2xl font-bold text-blue-600 mb-1">
            {summary.collection_rate || 0}%
          </h3>
          <p className="text-sm text-gray-600">Collection Rate</p>
        </div>
        
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-3">
            <div className="p-2 bg-yellow-50 rounded">
              <Building className="text-yellow-600" size={20} />
            </div>
            <span className="text-xs font-medium px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full">
              Structures
            </span>
          </div>
          <h3 className="text-2xl font-bold text-gray-900 mb-1">
            {formatNumber(summary.active_buildings || 0)}
          </h3>
          <p className="text-sm text-gray-600">Active Buildings</p>
        </div>
      </div>

      {/* Main Charts Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {/* Revenue by Barangay */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-5">
            <h3 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
              <MapPin size={20} className="text-blue-600" />
              Revenue by Barangay
            </h3>
            <span className="text-sm text-gray-500">
              Top {Math.min(10, revenueByBarangay.length)} barangays
            </span>
          </div>
          <div className="h-72">
            {revenueByBarangay.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={revenueByBarangay}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                  <XAxis 
                    dataKey="barangay" 
                    angle={-45}
                    textAnchor="end"
                    height={60}
                    tick={{ fontSize: 12 }}
                  />
                  <YAxis 
                    tickFormatter={(value) => `₱${(value/1000).toFixed(0)}K`}
                  />
                  <Tooltip 
                    formatter={(value, name) => {
                      if (name === 'total_revenue') return [formatCurrency(value), 'Total Revenue'];
                      if (name === 'avg_revenue') return [formatCurrency(value), 'Avg Revenue'];
                      return [formatNumber(value), 'Properties'];
                    }}
                    contentStyle={{ 
                      backgroundColor: 'white', 
                      border: '1px solid #e5e7eb',
                      borderRadius: '8px'
                    }}
                  />
                  <Legend />
                  <Bar 
                    dataKey="total_revenue" 
                    name="Total Revenue" 
                    fill="#3B82F6" 
                    radius={[4, 4, 0, 0]}
                  />
                  <Bar 
                    dataKey="property_count" 
                    name="Property Count" 
                    fill="#10B981" 
                    radius={[4, 4, 0, 0]}
                  />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-full flex items-center justify-center text-gray-500">
                <div className="text-center">
                  <BarChartIcon size={48} className="mx-auto mb-3 text-gray-300" />
                  <p>No barangay revenue data available</p>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Revenue by Property Type */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-5">
            <h3 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
              <PieChartIcon size={20} className="text-green-600" />
              Revenue by Property Type
            </h3>
            <span className="text-sm text-gray-500">
              {revenueByPropertyType.length} categories
            </span>
          </div>
          <div className="h-72">
            {revenueByPropertyType.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={revenueByPropertyType}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ property_type, total_revenue }) => {
                      const total = revenueByPropertyType.reduce((sum, item) => sum + (parseFloat(item.total_revenue) || 0), 0);
                      const percentage = total > 0 ? ((total_revenue / total) * 100).toFixed(0) : 0;
                      return `${property_type}: ${percentage}%`;
                    }}
                    outerRadius={80}
                    dataKey="total_revenue"
                    nameKey="property_type"
                  >
                    {revenueByPropertyType.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip 
                    formatter={(value) => [formatCurrency(value), 'Revenue']}
                    contentStyle={{ 
                      backgroundColor: 'white', 
                      border: '1px solid #e5e5e5',
                      borderRadius: '8px'
                    }}
                  />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-full flex items-center justify-center text-gray-500">
                <div className="text-center">
                  <PieChartIcon size={48} className="mx-auto mb-3 text-gray-300" />
                  <p>No property type data available</p>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Quarterly Collection & Annual Trend */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {/* Quarterly Collection */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-5">
            <h3 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
              <Calendar size={20} className="text-orange-600" />
              Quarterly Collection ({new Date().getFullYear()})
            </h3>
            <div className="text-right">
              <div className="text-sm font-medium text-blue-600">{collectionPercentage}% collected</div>
              <div className="text-xs text-gray-500">
                {formatCurrency(totalCollected)} of {formatCurrency(totalTaxDue)}
              </div>
            </div>
          </div>
          <div className="h-72">
            {quarterlyCollection.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={quarterlyCollection}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                  <XAxis dataKey="quarter" />
                  <YAxis tickFormatter={(value) => `₱${(value/1000).toFixed(0)}K`} />
                  <Tooltip 
                    formatter={(value, name) => {
                      if (name === 'total_tax' || name === 'collected') 
                        return [formatCurrency(value), name === 'total_tax' ? 'Total Tax' : 'Collected'];
                      return [formatNumber(value), name];
                    }}
                    contentStyle={{ 
                      backgroundColor: 'white', 
                      border: '1px solid #e5e7eb',
                      borderRadius: '8px'
                    }}
                  />
                  <Legend />
                  <Bar 
                    dataKey="total_tax" 
                    name="Total Tax Due" 
                    fill="#F59E0B" 
                    radius={[4, 4, 0, 0]}
                  />
                  <Bar 
                    dataKey="collected" 
                    name="Collected" 
                    fill="#10B981" 
                    radius={[4, 4, 0, 0]}
                  />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-full flex items-center justify-center text-gray-500">
                <div className="text-center">
                  <Calendar size={48} className="mx-auto mb-3 text-gray-300" />
                  <p>No quarterly collection data available</p>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Annual Revenue Trend */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-5">
            <h3 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
              <TrendingUp size={20} className="text-purple-600" />
              Annual Revenue Trend
            </h3>
            <span className="text-sm text-gray-500">
              Last {annualTrend.length} years
            </span>
          </div>
          <div className="h-72">
            {annualTrend.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={annualTrend}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                  <XAxis dataKey="year" />
                  <YAxis tickFormatter={(value) => `₱${(value/1000).toFixed(0)}K`} />
                  <Tooltip 
                    formatter={(value) => [formatCurrency(value), 'Revenue']}
                    contentStyle={{ 
                      backgroundColor: 'white', 
                      border: '1px solid #e5e7eb',
                      borderRadius: '8px'
                    }}
                  />
                  <Legend />
                  <Line 
                    type="monotone" 
                    dataKey="total_revenue" 
                    name="Total Revenue" 
                    stroke="#3B82F6" 
                    strokeWidth={2}
                    dot={{ r: 4 }}
                    activeDot={{ r: 6 }}
                  />
                  <Line 
                    type="monotone" 
                    dataKey="collected" 
                    name="Collected" 
                    stroke="#10B981" 
                    strokeWidth={2}
                    dot={{ r: 4 }}
                    activeDot={{ r: 6 }}
                  />
                </LineChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-full flex items-center justify-center text-gray-500">
                <div className="text-center">
                  <TrendingUp size={48} className="mx-auto mb-3 text-gray-300" />
                  <p>No annual trend data available</p>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Top Properties & Revenue Stats */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {/* Top Properties Table */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
          <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <CreditCard size={20} className="text-blue-600" />
            Top Properties by Annual Tax
          </h3>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Annual Tax</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {topProperties.length > 0 ? (
                  topProperties.map((property, index) => (
                    <tr key={index} className="hover:bg-gray-50">
                      <td className="px-4 py-3">
                        <div className="font-medium text-gray-900">{property.reference_number}</div>
                        <div className="text-xs text-gray-500">{property.barangay}</div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="text-sm">{property.lot_location}</div>
                        <div className="text-xs text-gray-500">{property.building_count || 0} buildings</div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="text-sm">{property.owner_name}</div>
                        <div className="text-xs text-gray-500">{formatNumber(property.land_area_sqm || 0)} sqm</div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="font-bold text-blue-600">
                          {formatCurrency(property.total_annual_tax || 0)}
                        </div>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="4" className="px-4 py-8 text-center text-gray-500">
                      No property data available
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Revenue Stats */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
          <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <DollarSign size={20} className="text-green-600" />
            Revenue Statistics
          </h3>
          <div className="space-y-4">
            <div className="p-4 bg-blue-50 rounded-lg">
              <h4 className="font-medium text-blue-800 mb-2">This Year Collection</h4>
              <div className="space-y-2">
                <div className="flex justify-between">
                  <span className="text-sm text-gray-700">Collected:</span>
                  <span className="font-bold text-green-600">
                    {formatCurrency(summary.collected_this_year || 0)}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-gray-700">Pending:</span>
                  <span className="font-bold text-yellow-600">
                    {formatCurrency(summary.pending_this_year || 0)}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-gray-700">Overdue Payments:</span>
                  <span className="font-bold text-red-600">
                    {summary.overdue_count || 0}
                  </span>
                </div>
              </div>
            </div>
            
            <div className="p-4 bg-green-50 rounded-lg">
              <h4 className="font-medium text-green-800 mb-2">Property Distribution</h4>
              <div className="space-y-2">
                <div className="flex justify-between">
                  <span className="text-sm text-gray-700">Total Properties:</span>
                  <span className="font-bold">{formatNumber(summary.total_properties || 0)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-gray-700">Active Buildings:</span>
                  <span className="font-bold">{formatNumber(summary.active_buildings || 0)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-gray-700">Avg Property Tax:</span>
                  <span className="font-bold">{formatCurrency(summary.avg_property_tax || 0)}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Footer */}
      <div className="mt-8 pt-6 border-t border-gray-200">
        <div className="flex flex-col md:flex-row md:items-center justify-between text-sm text-gray-500">
          <div>
            <span className="font-medium">RPT Revenue Analytics v1.0</span>
            <span className="mx-2">•</span>
            <span>Data as of: {lastUpdate.toLocaleDateString()} {lastUpdate.toLocaleTimeString()}</span>
          </div>
          <div className="mt-2 md:mt-0">
            <span>Environment: </span>
            <span className={`font-medium ${isLocalhost ? 'text-yellow-600' : 'text-green-600'}`}>
              {isLocalhost ? 'Development' : 'Production'}
            </span>
          </div>
        </div>
      </div>
    </div>
  );
};

export default RPTCharts;