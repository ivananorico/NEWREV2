import React, { useState, useEffect } from 'react';
import { 
  BarChart, Bar, LineChart, Line, PieChart, Pie, Cell,
  XAxis, YAxis, CartesianGrid, Tooltip, Legend, 
  ResponsiveContainer, AreaChart, Area, ComposedChart, RadarChart,
  Radar, PolarGrid, PolarAngleAxis, PolarRadiusAxis
} from 'recharts';

// ✅ DYNAMIC API URL
const isDevelopment = window.location.hostname === "localhost";
const API_BASE = isDevelopment
  ? "http://localhost/revenue2/backend"
  : "https://revenuetreasury.goserveph.com/backend";

export default function RPTDashboard() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [dashboardData, setDashboardData] = useState(null);
  const [activeTab, setActiveTab] = useState('overview');

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const fetchDashboardData = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(`${API_BASE}/RPT/RPTDashboard/rpt_dashboard.php`, {
        headers: { 'Accept': 'application/json' }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const text = await response.text();
      
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        console.error('JSON parse error:', parseError);
        throw new Error('Invalid JSON response');
      }
      
      if (!data.success) {
        throw new Error(data.error || data.message || 'Failed to load dashboard data');
      }
      
      setDashboardData(data);
    } catch (err) {
      console.error('Error fetching dashboard data:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (amount) => {
    if (amount === null || amount === undefined) return '₱0.00';
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(amount);
  };

  const formatNumber = (num) => {
    if (num === null || num === undefined) return '0';
    return new Intl.NumberFormat('en-PH').format(num);
  };

  const formatArea = (area) => {
    if (area === null || area === undefined) return '0 m²';
    return `${formatNumber(area)} m²`;
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
      return new Date(dateString).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    } catch (e) {
      return 'Invalid date';
    }
  };

  const getStatusColor = (status) => {
    switch(status?.toLowerCase()) {
      case 'approved':
      case 'paid':
      case 'active':
      case 'completed': return 'text-green-600 bg-green-100 dark:bg-green-900/20';
      case 'pending':
      case 'for_inspection':
      case 'scheduled': return 'text-yellow-600 bg-yellow-100 dark:bg-yellow-900/20';
      case 'rejected':
      case 'failed':
      case 'cancelled':
      case 'overdue': return 'text-red-600 bg-red-100 dark:bg-red-900/20';
      case 'needs_correction': return 'text-orange-600 bg-orange-100 dark:bg-orange-900/20';
      default: return 'text-gray-600 bg-gray-100 dark:bg-gray-900/20';
    }
  };

  const getStatusIcon = (status) => {
    switch(status?.toLowerCase()) {
      case 'approved':
      case 'paid':
      case 'active': return '✅';
      case 'pending':
      case 'for_inspection': return '⏳';
      case 'rejected':
      case 'failed': return '❌';
      case 'overdue': return '⚠️';
      case 'needs_correction': return '✏️';
      case 'scheduled': return '📅';
      case 'completed': return '✅';
      default: return '📄';
    }
  };

  const getActivityIcon = (type) => {
    switch(type) {
      case 'registration': return '📋';
      case 'payment': return '💰';
      case 'inspection': return '🔍';
      default: return '📝';
    }
  };

  const getPropertyTypeIcon = (type) => {
    switch(type?.toLowerCase()) {
      case 'residential': return '🏠';
      case 'commercial': return '🏢';
      case 'industrial': return '🏭';
      case 'agricultural': return '🌾';
      default: return '🏘️';
    }
  };

  const getBuildingTypeIcon = (type) => {
    switch(type?.toLowerCase()) {
      case 'concrete':
      case 'concreate': return '🏗️';
      case 'wooden': return '🪵';
      case 'mixed': return '🏠';
      case 'steel': return '⚙️';
      default: return '🏢';
    }
  };

  if (loading) {
    return (
      <div className="flex flex-col justify-center items-center h-96 space-y-4">
        <div className="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-600"></div>
        <p className="text-gray-600 dark:text-gray-400">Loading RPT Dashboard...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6">
        <div className="flex items-center space-x-3 mb-4">
          <span className="text-2xl">⚠️</span>
          <h3 className="text-lg font-semibold text-red-600 dark:text-red-400">Error Loading Dashboard</h3>
        </div>
        <p className="text-red-600 dark:text-red-400 mb-4">{error}</p>
        <button 
          onClick={fetchDashboardData}
          className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
        >
          Retry
        </button>
      </div>
    );
  }

  if (!dashboardData) {
    return (
      <div className="text-center py-12">
        <p className="text-gray-500 dark:text-gray-400">No data available</p>
        <button 
          onClick={fetchDashboardData}
          className="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          Load Data
        </button>
      </div>
    );
  }

  const { 
    property_stats = {},
    tax_stats = {},
    registration_audit = {},
    payment_audit = {},
    quarterly_overview = {},
    overdue_analysis = {},
    property_distribution = {},
    recent_activities = [],
    collection_performance = {},
    current_quarter = 'Q1',
    current_year = new Date().getFullYear()
  } = dashboardData;

  // Extract data
  const owners = property_stats.owners || {};
  const properties = property_stats.properties || {};
  const quarterlyTax = tax_stats.quarterly_tax || [];
  const recentRegistrations = registration_audit.recent_registrations || [];
  const rptPayments = payment_audit.rpt_payments || [];
  const overdueList = overdue_analysis.overdue_list || [];
  const propertyTypes = property_distribution.property_types || [];
  const buildingComparison = property_distribution.building_comparison || [];
  const buildingTypes = property_distribution.building_types || [];
  const locations = property_distribution.locations || [];
  const grandTotals = property_distribution.grand_totals || {};
  const quarterlyPerformance = collection_performance.quarterly_performance || [];
  const overallPerformance = collection_performance.overall_performance || {};
  const monthlyCollection = collection_performance.monthly_collection || [];

  // Prepare data for charts
  const propertyTypeData = propertyTypes.map(type => ({
    name: type.property_type || 'Unknown',
    value: type.property_count || 0,
    tax: type.total_annual_tax || 0,
    landValue: type.total_land_assessed_value || 0,
    buildingValue: type.total_building_assessed_value || 0,
    totalValue: (type.total_land_assessed_value || 0) + (type.total_building_assessed_value || 0),
    area: type.total_area_sqm || 0,
    buildingCount: type.building_count || 0,
    icon: getPropertyTypeIcon(type.property_type)
  }));

  const buildingComparisonData = buildingComparison.map(item => ({
    name: item.has_building === 'yes' ? 'With Building' : 'Land Only',
    propertyCount: item.property_count || 0,
    totalAnnualTax: item.total_annual_tax || 0,
    landValue: item.total_land_value || 0,
    buildingValue: item.total_building_value || 0,
    avgLandArea: item.avg_land_area || 0,
    avgBuildingArea: item.avg_building_area || 0
  }));

  const quarterlyChartData = quarterlyTax.map(q => ({
    name: q.quarter,
    collected: q.paid_amount || 0,
    pending: q.pending_amount || 0,
    overdue: q.overdue_amount || 0,
    total: q.total_amount || 0,
    paidCount: q.paid_count || 0,
    pendingCount: q.pending_count || 0,
    overdueCount: q.overdue_count || 0
  }));

  const performanceChartData = quarterlyPerformance.map(p => ({
    name: p.quarter,
    rate: p.collection_rate || 0,
    collected: p.total_collected || 0,
    assigned: p.total_assigned || 0,
    efficiency: p.collection_rate || 0
  }));

  const monthlyChartData = Array.from({ length: 12 }, (_, i) => {
    const monthData = monthlyCollection?.find(m => m.month === i + 1);
    return {
      name: new Date(2000, i, 1).toLocaleString('default', { month: 'short' }),
      amount: monthData?.amount_collected || 0,
      payments: monthData?.payment_count || 0
    };
  });

  const buildingTypeData = buildingTypes.map(type => ({
    name: type.construction_type || 'Unknown',
    classification: type.building_classification || 'Unknown',
    count: type.building_count || 0,
    assessedValue: type.total_assessed_value || 0,
    marketValue: type.total_market_value || 0,
    area: type.total_area_sqm || 0,
    avgYear: type.avg_year_built || 0,
    annualTax: type.total_annual_tax || 0,
    icon: getBuildingTypeIcon(type.construction_type)
  }));

  const locationData = locations.map(loc => ({
    name: loc.barangay,
    district: loc.district,
    propertyCount: loc.property_count || 0,
    uniqueOwners: loc.unique_owners || 0,
    totalAnnualTax: loc.total_annual_tax || 0,
    totalLandValue: loc.total_land_value || 0,
    totalLandArea: loc.total_land_area || 0
  }));

  // Colors for charts
  const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884d8', '#82ca9d'];

  // Tabs for property statistics
  const propertyTabs = [
    { id: 'overview', name: 'Overview', icon: '📊' },
    { id: 'types', name: 'Property Types', icon: '🏘️' },
    { id: 'buildings', name: 'Buildings', icon: '🏢' },
    { id: 'locations', name: 'Locations', icon: '📍' },
    { id: 'comparison', name: 'Comparison', icon: '⚖️' }
  ];

  const renderPropertyOverview = () => (
    <div className="space-y-6">
      {/* Grand Totals */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-3 mb-3">
            <div className="text-2xl bg-blue-500 text-white w-12 h-12 rounded-lg flex items-center justify-center">
              🏠
            </div>
            <div>
              <h3 className="text-sm text-gray-500 dark:text-gray-400">Total Land Parcels</h3>
              <p className="text-xl font-bold text-gray-900 dark:text-white">
                {formatNumber(grandTotals.total_land_parcels || 0)}
              </p>
            </div>
          </div>
          <div className="text-xs text-gray-500">
            {formatArea(grandTotals.grand_total_land_area || 0)}
          </div>
        </div>

        <div className="rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-3 mb-3">
            <div className="text-2xl bg-green-500 text-white w-12 h-12 rounded-lg flex items-center justify-center">
              🏢
            </div>
            <div>
              <h3 className="text-sm text-gray-500 dark:text-gray-400">Total Buildings</h3>
              <p className="text-xl font-bold text-gray-900 dark:text-white">
                {formatNumber(grandTotals.total_buildings || 0)}
              </p>
            </div>
          </div>
          <div className="text-xs text-gray-500">
            {formatArea(grandTotals.grand_total_building_area || 0)}
          </div>
        </div>

        <div className="rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-3 mb-3">
            <div className="text-2xl bg-purple-500 text-white w-12 h-12 rounded-lg flex items-center justify-center">
              💰
            </div>
            <div>
              <h3 className="text-sm text-gray-500 dark:text-gray-400">Total Land Value</h3>
              <p className="text-xl font-bold text-gray-900 dark:text-white">
                {formatCurrency(grandTotals.grand_total_land_value || 0)}
              </p>
            </div>
          </div>
          <div className="text-xs text-gray-500">
            Tax: {formatCurrency(grandTotals.grand_total_land_tax || 0)}
          </div>
        </div>

        <div className="rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-3 mb-3">
            <div className="text-2xl bg-yellow-500 text-white w-12 h-12 rounded-lg flex items-center justify-center">
              🏗️
            </div>
            <div>
              <h3 className="text-sm text-gray-500 dark:text-gray-400">Total Building Value</h3>
              <p className="text-xl font-bold text-gray-900 dark:text-white">
                {formatCurrency(grandTotals.grand_total_building_value || 0)}
              </p>
            </div>
          </div>
          <div className="text-xs text-gray-500">
            Tax: {formatCurrency(grandTotals.grand_total_building_tax || 0)}
          </div>
        </div>
      </div>

      {/* Combined Value Chart */}
      <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4 sm:p-6 border border-gray-200 dark:border-gray-700">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          Combined Property Values
        </h3>
        <div className="h-64">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={propertyTypeData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
              <XAxis dataKey="name" stroke="#9CA3AF" />
              <YAxis 
                stroke="#9CA3AF" 
                tickFormatter={(value) => `₱${(value/1000000).toFixed(1)}M`}
              />
              <Tooltip 
                formatter={(value, name) => {
                  if (name === 'landValue' || name === 'buildingValue' || name === 'totalValue') {
                    return [formatCurrency(value), name];
                  }
                  return [value, name];
                }}
              />
              <Legend />
              <Bar dataKey="landValue" fill="#3B82F6" name="Land Value" />
              <Bar dataKey="buildingValue" fill="#10B981" name="Building Value" />
            </BarChart>
          </ResponsiveContainer>
        </div>
        <div className="mt-4 text-center">
          <p className="text-sm text-gray-600 dark:text-gray-400">
            Total Combined Value: <span className="font-bold text-blue-600 dark:text-blue-400">
              {formatCurrency((grandTotals.grand_total_land_value || 0) + (grandTotals.grand_total_building_value || 0))}
            </span>
          </p>
        </div>
      </div>
    </div>
  );

  const renderPropertyTypes = () => (
    <div className="space-y-6">
      {/* Property Types Summary */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl shadow p-4 sm:p-6 border border-gray-200 dark:border-gray-700">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Property Types Distribution
          </h3>
          <div className="h-80">
            {propertyTypeData.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={propertyTypeData}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={(entry) => `${entry.name}: ${entry.value}`}
                    outerRadius={80}
                    innerRadius={40}
                    paddingAngle={5}
                    dataKey="value"
                  >
                    {propertyTypeData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip 
                    formatter={(value, name, props) => {
                      const data = props.payload;
                      return [
                        <div key="tooltip">
                          <p>{data.name}: {value} properties</p>
                          <p>Land Value: {formatCurrency(data.landValue)}</p>
                          <p>Building Value: {formatCurrency(data.buildingValue)}</p>
                          <p>Total Tax: {formatCurrency(data.tax)}</p>
                          <p>Total Area: {formatArea(data.area)}</p>
                        </div>,
                        name
                      ];
                    }}
                  />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <div className="flex items-center justify-center h-full text-gray-400">
                No property type data
              </div>
            )}
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4 sm:p-6 border border-gray-200 dark:border-gray-700">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Property Type Details
          </h3>
          <div className="space-y-3 max-h-80 overflow-y-auto">
            {propertyTypeData.map((type, index) => (
              <div key={index} className="p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                <div className="flex items-center justify-between mb-2">
                  <div className="flex items-center space-x-2">
                    <span className="text-xl">{type.icon}</span>
                    <span className="font-medium text-gray-900 dark:text-white">{type.name}</span>
                  </div>
                  <span className="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded text-sm">
                    {type.value} properties
                  </span>
                </div>
                <div className="grid grid-cols-2 gap-2 text-sm">
                  <div>
                    <p className="text-gray-500">Land Value</p>
                    <p className="font-semibold text-blue-600 dark:text-blue-400">
                      {formatCurrency(type.landValue)}
                    </p>
                  </div>
                  <div>
                    <p className="text-gray-500">Building Value</p>
                    <p className="font-semibold text-green-600 dark:text-green-400">
                      {formatCurrency(type.buildingValue)}
                    </p>
                  </div>
                  <div>
                    <p className="text-gray-500">Buildings</p>
                    <p className="font-semibold">{type.buildingCount}</p>
                  </div>
                  <div>
                    <p className="text-gray-500">Annual Tax</p>
                    <p className="font-semibold text-purple-600 dark:text-purple-400">
                      {formatCurrency(type.tax)}
                    </p>
                  </div>
                </div>
                <div className="mt-2 text-xs text-gray-500">
                  Total Area: {formatArea(type.area)}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Property Type Statistics Table */}
      <div className="bg-white dark:bg-slate-800 rounded-xl shadow border border-gray-200 dark:border-gray-700">
        <div className="p-4 sm:p-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Property Types Statistics
          </h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-gray-200 dark:border-gray-700">
                  <th className="py-3 px-2 text-left">Property Type</th>
                  <th className="py-3 px-2 text-left">Properties</th>
                  <th className="py-3 px-2 text-left">Buildings</th>
                  <th className="py-3 px-2 text-left">Land Value</th>
                  <th className="py-3 px-2 text-left">Building Value</th>
                  <th className="py-3 px-2 text-left">Total Area</th>
                  <th className="py-3 px-2 text-left">Annual Tax</th>
                </tr>
              </thead>
              <tbody>
                {propertyTypeData.map((type, index) => (
                  <tr key={index} className="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-slate-700/50">
                    <td className="py-3 px-2">
                      <div className="flex items-center space-x-2">
                        <span className="text-lg">{type.icon}</span>
                        <span className="font-medium">{type.name}</span>
                      </div>
                    </td>
                    <td className="py-3 px-2">
                      <span className="font-bold">{type.value}</span>
                    </td>
                    <td className="py-3 px-2">{type.buildingCount}</td>
                    <td className="py-3 px-2 font-semibold text-blue-600 dark:text-blue-400">
                      {formatCurrency(type.landValue)}
                    </td>
                    <td className="py-3 px-2 font-semibold text-green-600 dark:text-green-400">
                      {formatCurrency(type.buildingValue)}
                    </td>
                    <td className="py-3 px-2">{formatArea(type.area)}</td>
                    <td className="py-3 px-2 font-semibold text-purple-600 dark:text-purple-400">
                      {formatCurrency(type.tax)}
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="bg-gray-50 dark:bg-slate-700 font-bold">
                  <td className="py-3 px-2">TOTAL</td>
                  <td className="py-3 px-2">
                    {propertyTypeData.reduce((sum, type) => sum + (type.value || 0), 0)}
                  </td>
                  <td className="py-3 px-2">
                    {propertyTypeData.reduce((sum, type) => sum + (type.buildingCount || 0), 0)}
                  </td>
                  <td className="py-3 px-2 text-blue-600 dark:text-blue-400">
                    {formatCurrency(propertyTypeData.reduce((sum, type) => sum + (type.landValue || 0), 0))}
                  </td>
                  <td className="py-3 px-2 text-green-600 dark:text-green-400">
                    {formatCurrency(propertyTypeData.reduce((sum, type) => sum + (type.buildingValue || 0), 0))}
                  </td>
                  <td className="py-3 px-2">
                    {formatArea(propertyTypeData.reduce((sum, type) => sum + (type.area || 0), 0))}
                  </td>
                  <td className="py-3 px-2 text-purple-600 dark:text-purple-400">
                    {formatCurrency(propertyTypeData.reduce((sum, type) => sum + (type.tax || 0), 0))}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  );

  const renderBuildings = () => (
    <div className="space-y-6">
      {/* Building Statistics */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-3 mb-3">
            <div className="text-2xl bg-blue-500 text-white w-12 h-12 rounded-lg flex items-center justify-center">
              🏗️
            </div>
            <div>
              <h3 className="text-sm text-gray-500 dark:text-gray-400">Total Buildings</h3>
              <p className="text-xl font-bold text-gray-900 dark:text-white">
                {formatNumber(grandTotals.total_buildings || 0)}
              </p>
            </div>
          </div>
          <div className="text-xs text-gray-500">
            Total Area: {formatArea(grandTotals.grand_total_building_area || 0)}
          </div>
        </div>

        <div className="rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-3 mb-3">
            <div className="text-2xl bg-green-500 text-white w-12 h-12 rounded-lg flex items-center justify-center">
              💰
            </div>
            <div>
              <h3 className="text-sm text-gray-500 dark:text-gray-400">Total Building Value</h3>
              <p className="text-xl font-bold text-gray-900 dark:text-white">
                {formatCurrency(grandTotals.grand_total_building_value || 0)}
              </p>
            </div>
          </div>
          <div className="text-xs text-gray-500">
            Annual Tax: {formatCurrency(grandTotals.grand_total_building_tax || 0)}
          </div>
        </div>

        <div className="rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-3 mb-3">
            <div className="text-2xl bg-purple-500 text-white w-12 h-12 rounded-lg flex items-center justify-center">
              📊
            </div>
            <div>
              <h3 className="text-sm text-gray-500 dark:text-gray-400">Avg. Building Age</h3>
              <p className="text-xl font-bold text-gray-900 dark:text-white">
                {buildingTypeData.length > 0 ? 
                  current_year - Math.round(buildingTypeData.reduce((sum, type) => sum + (type.avgYear || 0), 0) / buildingTypeData.length) 
                  : 'N/A'} years
              </p>
            </div>
          </div>
          <div className="text-xs text-gray-500">
            {buildingTypeData.length} construction types
          </div>
        </div>
      </div>

      {/* Building Types Chart */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4 sm:p-6 border border-gray-200 dark:border-gray-700">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Building Construction Types
          </h3>
          <div className="h-64">
            {buildingTypeData.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={buildingTypeData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                  <XAxis dataKey="name" stroke="#9CA3AF" />
                  <YAxis 
                    yAxisId="left"
                    stroke="#9CA3AF" 
                  />
                  <YAxis 
                    yAxisId="right"
                    orientation="right"
                    stroke="#9CA3AF"
                    tickFormatter={(value) => `₱${(value/1000000).toFixed(1)}M`}
                  />
                  <Tooltip 
                    formatter={(value, name, props) => {
                      const data = props.payload;
                      if (name === 'assessedValue' || name === 'marketValue' || name === 'annualTax') {
                        return [formatCurrency(value), name];
                      }
                      if (name === 'area') {
                        return [formatArea(value), 'Total Area'];
                      }
                      return [value, name];
                    }}
                  />
                  <Legend />
                  <Bar yAxisId="left" dataKey="count" fill="#3B82F6" name="Building Count" />
                  <Bar yAxisId="right" dataKey="assessedValue" fill="#10B981" name="Assessed Value" />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="flex items-center justify-center h-full text-gray-400">
                No building data
              </div>
            )}
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4 sm:p-6 border border-gray-200 dark:border-gray-700">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Building Type Details
          </h3>
          <div className="space-y-3 max-h-64 overflow-y-auto">
            {buildingTypeData.map((type, index) => (
              <div key={index} className="p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                <div className="flex items-center justify-between mb-2">
                  <div className="flex items-center space-x-2">
                    <span className="text-xl">{type.icon}</span>
                    <div>
                      <p className="font-medium text-gray-900 dark:text-white">{type.name}</p>
                      <p className="text-xs text-gray-500">{type.classification}</p>
                    </div>
                  </div>
                  <span className="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded text-sm">
                    {type.count} buildings
                  </span>
                </div>
                <div className="grid grid-cols-2 gap-2 text-sm">
                  <div>
                    <p className="text-gray-500">Value</p>
                    <p className="font-semibold text-green-600 dark:text-green-400">
                      {formatCurrency(type.assessedValue)}
                    </p>
                  </div>
                  <div>
                    <p className="text-gray-500">Area</p>
                    <p className="font-semibold">{formatArea(type.area)}</p>
                  </div>
                  <div>
                    <p className="text-gray-500">Tax</p>
                    <p className="font-semibold text-purple-600 dark:text-purple-400">
                      {formatCurrency(type.annualTax)}
                    </p>
                  </div>
                  <div>
                    <p className="text-gray-500">Avg. Year</p>
                    <p className="font-semibold">{Math.round(type.avgYear)}</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );

  const renderLocations = () => (
    <div className="space-y-6">
      {/* Location Statistics */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-3 mb-3">
            <div className="text-2xl bg-blue-500 text-white w-12 h-12 rounded-lg flex items-center justify-center">
              📍
            </div>
            <div>
              <h3 className="text-sm text-gray-500 dark:text-gray-400">Top Locations</h3>
              <p className="text-xl font-bold text-gray-900 dark:text-white">
                {locationData.length}
              </p>
            </div>
          </div>
          <div className="text-xs text-gray-500">
            Barangays with highest properties
          </div>
        </div>

        <div className="rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-3 mb-3">
            <div className="text-2xl bg-green-500 text-white w-12 h-12 rounded-lg flex items-center justify-center">
              🏠
            </div>
            <div>
              <h3 className="text-sm text-gray-500 dark:text-gray-400">Total Properties</h3>
              <p className="text-xl font-bold text-gray-900 dark:text-white">
                {locationData.reduce((sum, loc) => sum + (loc.propertyCount || 0), 0)}
              </p>
            </div>
          </div>
          <div className="text-xs text-gray-500">
            In top {locationData.length} barangays
          </div>
        </div>

        <div className="rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-3 mb-3">
            <div className="text-2xl bg-purple-500 text-white w-12 h-12 rounded-lg flex items-center justify-center">
              👥
            </div>
            <div>
              <h3 className="text-sm text-gray-500 dark:text-gray-400">Unique Owners</h3>
              <p className="text-xl font-bold text-gray-900 dark:text-white">
                {locationData.reduce((sum, loc) => sum + (loc.uniqueOwners || 0), 0)}
              </p>
            </div>
          </div>
          <div className="text-xs text-gray-500">
            Across top barangays
          </div>
        </div>
      </div>

      {/* Location Distribution */}
      <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4 sm:p-6 border border-gray-200 dark:border-gray-700">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          Property Distribution by Barangay
        </h3>
        <div className="h-80">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={locationData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
              <XAxis dataKey="name" stroke="#9CA3AF" />
              <YAxis 
                yAxisId="left"
                stroke="#9CA3AF" 
              />
              <YAxis 
                yAxisId="right"
                orientation="right"
                stroke="#9CA3AF"
                tickFormatter={(value) => `₱${(value/1000000).toFixed(1)}M`}
              />
              <Tooltip 
                formatter={(value, name, props) => {
                  const data = props.payload;
                  if (name === 'totalAnnualTax' || name === 'totalLandValue') {
                    return [formatCurrency(value), name];
                  }
                  if (name === 'totalLandArea') {
                    return [formatArea(value), 'Land Area'];
                  }
                  return [value, name];
                }}
              />
              <Legend />
              <Bar yAxisId="left" dataKey="propertyCount" fill="#3B82F6" name="Properties" />
              <Bar yAxisId="right" dataKey="totalAnnualTax" fill="#10B981" name="Annual Tax" />
              <Bar yAxisId="left" dataKey="uniqueOwners" fill="#F59E0B" name="Owners" />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Location Table */}
      <div className="bg-white dark:bg-slate-800 rounded-xl shadow border border-gray-200 dark:border-gray-700">
        <div className="p-4 sm:p-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Location Details
          </h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-gray-200 dark:border-gray-700">
                  <th className="py-3 px-2 text-left">Barangay</th>
                  <th className="py-3 px-2 text-left">District</th>
                  <th className="py-3 px-2 text-left">Properties</th>
                  <th className="py-3 px-2 text-left">Owners</th>
                  <th className="py-3 px-2 text-left">Land Value</th>
                  <th className="py-3 px-2 text-left">Land Area</th>
                  <th className="py-3 px-2 text-left">Annual Tax</th>
                </tr>
              </thead>
              <tbody>
                {locationData.map((loc, index) => (
                  <tr key={index} className="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-slate-700/50">
                    <td className="py-3 px-2 font-medium">{loc.name}</td>
                    <td className="py-3 px-2 text-gray-500">{loc.district}</td>
                    <td className="py-3 px-2 font-bold">{loc.propertyCount}</td>
                    <td className="py-3 px-2">{loc.uniqueOwners}</td>
                    <td className="py-3 px-2 font-semibold text-blue-600 dark:text-blue-400">
                      {formatCurrency(loc.totalLandValue)}
                    </td>
                    <td className="py-3 px-2">{formatArea(loc.totalLandArea)}</td>
                    <td className="py-3 px-2 font-semibold text-green-600 dark:text-green-400">
                      {formatCurrency(loc.totalAnnualTax)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );

  const renderComparison = () => (
    <div className="space-y-6">
      {/* Building Comparison */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4 sm:p-6 border border-gray-200 dark:border-gray-700">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Properties With vs Without Buildings
          </h3>
          <div className="h-64">
            {buildingComparisonData.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={buildingComparisonData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                  <XAxis dataKey="name" stroke="#9CA3AF" />
                  <YAxis 
                    yAxisId="left"
                    stroke="#9CA3AF" 
                  />
                  <YAxis 
                    yAxisId="right"
                    orientation="right"
                    stroke="#9CA3AF"
                    tickFormatter={(value) => `₱${(value/1000000).toFixed(1)}M`}
                  />
                  <Tooltip 
                    formatter={(value, name, props) => {
                      const data = props.payload;
                      if (name === 'totalAnnualTax' || name === 'landValue' || name === 'buildingValue') {
                        return [formatCurrency(value), name];
                      }
                      if (name === 'avgLandArea' || name === 'avgBuildingArea') {
                        return [formatArea(value), name];
                      }
                      return [value, name];
                    }}
                  />
                  <Legend />
                  <Bar yAxisId="left" dataKey="propertyCount" fill="#3B82F6" name="Properties" />
                  <Bar yAxisId="right" dataKey="totalAnnualTax" fill="#10B981" name="Annual Tax" />
                  <Bar yAxisId="right" dataKey="landValue" fill="#8B5CF6" name="Land Value" />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="flex items-center justify-center h-full text-gray-400">
                No comparison data
              </div>
            )}
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4 sm:p-6 border border-gray-200 dark:border-gray-700">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Comparison Summary
          </h3>
          <div className="space-y-4">
            {buildingComparisonData.map((item, index) => (
              <div key={index} className="p-4 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                <div className="flex items-center justify-between mb-3">
                  <h4 className="font-semibold text-gray-900 dark:text-white">{item.name}</h4>
                  <span className="px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-sm">
                    {item.propertyCount} properties
                  </span>
                </div>
                <div className="grid grid-cols-2 gap-3 text-sm">
                  <div className="p-2 bg-white dark:bg-slate-600 rounded">
                    <p className="text-gray-500 text-xs">Land Value</p>
                    <p className="font-bold text-blue-600 dark:text-blue-400">
                      {formatCurrency(item.landValue)}
                    </p>
                  </div>
                  <div className="p-2 bg-white dark:bg-slate-600 rounded">
                    <p className="text-gray-500 text-xs">Building Value</p>
                    <p className="font-bold text-green-600 dark:text-green-400">
                      {formatCurrency(item.buildingValue)}
                    </p>
                  </div>
                  <div className="p-2 bg-white dark:bg-slate-600 rounded">
                    <p className="text-gray-500 text-xs">Annual Tax</p>
                    <p className="font-bold text-purple-600 dark:text-purple-400">
                      {formatCurrency(item.totalAnnualTax)}
                    </p>
                  </div>
                  <div className="p-2 bg-white dark:bg-slate-600 rounded">
                    <p className="text-gray-500 text-xs">Avg. Land Area</p>
                    <p className="font-bold">{formatArea(item.avgLandArea)}</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Radar Chart for Comparison */}
      <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4 sm:p-6 border border-gray-200 dark:border-gray-700">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          Property Comparison Analysis
        </h3>
        <div className="h-80">
          {propertyTypeData.length > 0 ? (
            <ResponsiveContainer width="100%" height="100%">
              <RadarChart data={propertyTypeData}>
                <PolarGrid />
                <PolarAngleAxis dataKey="name" />
                <PolarRadiusAxis 
                  angle={30}
                  domain={[0, Math.max(...propertyTypeData.map(d => d.value))]}
                />
                <Radar 
                  name="Properties" 
                  dataKey="value" 
                  stroke="#3B82F6" 
                  fill="#3B82F6" 
                  fillOpacity={0.6} 
                />
                <Radar 
                  name="Buildings" 
                  dataKey="buildingCount" 
                  stroke="#10B981" 
                  fill="#10B981" 
                  fillOpacity={0.6} 
                />
                <Tooltip 
                  formatter={(value, name) => {
                    if (name === 'Properties' || name === 'Buildings') {
                      return [value, name];
                    }
                    return [value, name];
                  }}
                />
                <Legend />
              </RadarChart>
            </ResponsiveContainer>
          ) : (
            <div className="flex items-center justify-center h-full text-gray-400">
              No data for radar chart
            </div>
          )}
        </div>
      </div>
    </div>
  );

  const renderTabContent = () => {
    switch(activeTab) {
      case 'overview':
        return renderPropertyOverview();
      case 'types':
        return renderPropertyTypes();
      case 'buildings':
        return renderBuildings();
      case 'locations':
        return renderLocations();
      case 'comparison':
        return renderComparison();
      default:
        return renderPropertyOverview();
    }
  };

  return (
    <div className="mx-auto p-4 sm:p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div>
            <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">
              Real Property Tax Administration Dashboard
            </h1>
            <p className="text-gray-600 dark:text-gray-400">
              Comprehensive property registration, tax assessment, and collection management
            </p>
          </div>
          <div className="flex items-center space-x-4">
            <div className="px-4 py-2 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-sm font-medium">
              {current_quarter} {current_year}
            </div>
            <button 
              onClick={fetchDashboardData}
              className="px-4 py-2 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors"
            >
              Refresh
            </button>
          </div>
        </div>
      </div>

      {/* Summary Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div className="rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-3 mb-3">
            <div className="text-2xl bg-blue-500 text-white w-12 h-12 rounded-lg flex items-center justify-center">
              👥
            </div>
            <div>
              <h3 className="text-sm text-gray-500 dark:text-gray-400">Property Owners</h3>
              <p className="text-xl font-bold text-gray-900 dark:text-white">
                {owners.total_owners || 0}
              </p>
            </div>
          </div>
          <div className="flex space-x-2 text-xs">
            <span className="text-green-600 dark:text-green-400">Active: {owners.active_owners || 0}</span>
            <span className="text-yellow-600 dark:text-yellow-400">Pending: {owners.pending_owners || 0}</span>
          </div>
        </div>

        <div className="rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-3 mb-3">
            <div className="text-2xl bg-green-500 text-white w-12 h-12 rounded-lg flex items-center justify-center">
              🏠
            </div>
            <div>
              <h3 className="text-sm text-gray-500 dark:text-gray-400">Properties</h3>
              <p className="text-xl font-bold text-gray-900 dark:text-white">
                {properties.total_properties || 0}
              </p>
            </div>
          </div>
          <div className="text-xs text-gray-500">
            Approved: {properties.approved_properties || 0}
          </div>
        </div>

        <div className="rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-3 mb-3">
            <div className="text-2xl bg-purple-500 text-white w-12 h-12 rounded-lg flex items-center justify-center">
              💰
            </div>
            <div>
              <h3 className="text-sm text-gray-500 dark:text-gray-400">Annual Tax Value</h3>
              <p className="text-xl font-bold text-gray-900 dark:text-white">
                {formatCurrency(tax_stats.annual_tax?.total_annual_tax || 0)}
              </p>
            </div>
          </div>
          <div className="text-xs text-gray-500">
            {tax_stats.annual_tax?.total_assessments || 0} assessments
          </div>
        </div>

        <div className="rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-3 mb-3">
            <div className="text-2xl bg-yellow-500 text-white w-12 h-12 rounded-lg flex items-center justify-center">
              📊
            </div>
            <div>
              <h3 className="text-sm text-gray-500 dark:text-gray-400">Collection Rate</h3>
              <p className="text-xl font-bold text-gray-900 dark:text-white">
                {overallPerformance.collection_rate || 0}%
              </p>
            </div>
          </div>
          <div className="text-xs text-gray-500">
            {overallPerformance.paid_taxes || 0} of {overallPerformance.total_taxes || 0} taxes paid
          </div>
        </div>
      </div>

      {/* Property Statistics Section */}
      <div className="mb-8">
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow border border-gray-200 dark:border-gray-700">
          {/* Tabs */}
          <div className="border-b border-gray-200 dark:border-gray-700">
            <div className="flex flex-wrap px-4 pt-4">
              {propertyTabs.map((tab) => (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={`flex items-center space-x-2 px-4 py-3 font-medium text-sm rounded-t-lg transition-colors ${
                    activeTab === tab.id
                      ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20'
                      : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                  }`}
                >
                  <span>{tab.icon}</span>
                  <span>{tab.name}</span>
                </button>
              ))}
            </div>
          </div>

          {/* Tab Content */}
          <div className="p-4 sm:p-6">
            {renderTabContent()}
          </div>
        </div>
      </div>

      {/* Recent Completed Payments */}
      <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4 sm:p-6 border border-gray-200 dark:border-gray-700 mb-8">
        <div className="flex justify-between items-center mb-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Recent Completed Tax Payments</h3>
          <span className="px-3 py-1 bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-300 rounded-full text-sm">
            {rptPayments.length} paid transactions
          </span>
        </div>
        
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-200 dark:border-gray-700">
                <th className="py-3 px-2 text-left">Taxpayer</th>
                <th className="py-3 px-2 text-left">Tax Period</th>
                <th className="py-3 px-2 text-left">Property</th>
                <th className="py-3 px-2 text-left">Amount</th>
                <th className="py-3 px-2 text-left">Receipt</th>
                <th className="py-3 px-2 text-left">Paid On</th>
              </tr>
            </thead>
            <tbody>
              {rptPayments.slice(0, 8).map((payment, index) => (
                <tr key={index} className="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-slate-700/50">
                  <td className="py-3 px-2">
                    <div>
                      <p className="font-medium text-gray-900 dark:text-white">{payment.full_name}</p>
                      <p className="text-xs text-gray-500">{payment.phone}</p>
                    </div>
                  </td>
                  <td className="py-3 px-2">
                    <p className="font-medium">{payment.quarter} {payment.year}</p>
                    <p className="text-xs text-gray-500">Due: {formatDate(payment.due_date)}</p>
                  </td>
                  <td className="py-3 px-2">
                    <p className="font-mono text-sm">{payment.property_ref}</p>
                  </td>
                  <td className="py-3 px-2">
                    <p className="font-bold text-green-600 dark:text-green-400">
                      {formatCurrency(payment.total_quarterly_tax)}
                    </p>
                  </td>
                  <td className="py-3 px-2">
                    <p className="font-mono text-xs bg-gray-100 dark:bg-slate-700 px-2 py-1 rounded">
                      {payment.receipt_number}
                    </p>
                  </td>
                  <td className="py-3 px-2 text-gray-500 text-sm">
                    {formatDate(payment.payment_date)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        
        {rptPayments.length === 0 && (
          <div className="text-center py-8 text-gray-500">
            No completed payment records found
          </div>
        )}
      </div>

      {/* Charts Section */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {/* Monthly Collection */}
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4 sm:p-6 border border-gray-200 dark:border-gray-700">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Monthly Collection ({current_year})
          </h3>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <ComposedChart data={monthlyChartData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                <XAxis dataKey="name" stroke="#9CA3AF" />
                <YAxis 
                  yAxisId="left"
                  stroke="#9CA3AF" 
                  tickFormatter={(value) => `₱${(value/1000).toFixed(0)}k`}
                />
                <Tooltip 
                  formatter={(value, name) => {
                    if (name === 'amount') return [formatCurrency(value), 'Amount Collected'];
                    if (name === 'payments') return [value, 'Number of Payments'];
                    return [value, name];
                  }}
                />
                <Legend />
                <Bar 
                  yAxisId="left"
                  dataKey="amount" 
                  fill="#3B82F6" 
                  name="Amount Collected"
                />
                <Line 
                  yAxisId="left"
                  type="monotone" 
                  dataKey="payments" 
                  stroke="#10B981" 
                  name="Number of Payments"
                  strokeWidth={2}
                />
              </ComposedChart>
            </ResponsiveContainer>
          </div>
          <div className="mt-4 text-center">
            <p className="text-sm text-gray-600 dark:text-gray-400">
              Total Collected: <span className="font-bold text-green-600 dark:text-green-400">
                {formatCurrency(overallPerformance.total_collected || 0)}
              </span>
            </p>
          </div>
        </div>

        {/* Quarterly Performance */}
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4 sm:p-6 border border-gray-200 dark:border-gray-700">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-6">
            Quarterly Tax Performance ({current_year})
          </h3>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={quarterlyChartData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                <XAxis dataKey="name" stroke="#9CA3AF" />
                <YAxis 
                  stroke="#9CA3AF" 
                  tickFormatter={(value) => `₱${(value/1000).toFixed(0)}k`}
                />
                <Tooltip 
                  formatter={(value, name) => {
                    if (name === 'collected' || name === 'pending' || name === 'overdue' || name === 'total') {
                      return [formatCurrency(value), name];
                    }
                    return [value, name];
                  }}
                />
                <Legend />
                <Bar dataKey="collected" fill="#10B981" name="Collected (Paid)" />
                <Bar dataKey="pending" fill="#F59E0B" name="Pending" />
                <Bar dataKey="overdue" fill="#EF4444" name="Overdue" />
              </BarChart>
            </ResponsiveContainer>
          </div>
          <div className="mt-6 grid grid-cols-4 gap-4">
            {quarterlyChartData.map((quarter, index) => (
              <div 
                key={index} 
                className={`p-3 rounded-lg ${quarter.name === current_quarter ? 'ring-2 ring-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'bg-gray-50 dark:bg-slate-700'}`}
              >
                <div className="flex justify-between items-center mb-2">
                  <span className={`font-bold ${quarter.name === current_quarter ? 'text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300'}`}>
                    {quarter.name}
                  </span>
                  {quarter.name === current_quarter && (
                    <span className="text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-1 rounded">
                      Current
                    </span>
                  )}
                </div>
                <p className="text-lg font-bold text-green-600 dark:text-green-400">
                  {formatCurrency(quarter.collected)}
                </p>
                <p className="text-sm text-gray-500">
                  {quarter.paidCount || 0} paid • {quarter.pendingCount || 0} pending
                </p>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Overdue Properties */}
      <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4 sm:p-6 border border-gray-200 dark:border-gray-700 mb-8">
        <div className="flex justify-between items-center mb-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Overdue Properties</h3>
          <span className="px-3 py-1 bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-300 rounded-full text-sm">
            {overdueList.length} overdue
          </span>
        </div>
        
        <div className="space-y-3">
          {overdueList.slice(0, 5).map((property, index) => (
            <div key={index} className="p-3 bg-red-50 dark:bg-red-900/10 rounded-lg border border-red-200 dark:border-red-800">
              <div className="flex justify-between items-start mb-2">
                <div>
                  <p className="font-medium text-gray-900 dark:text-white">{property.full_name}</p>
                  <p className="text-sm text-gray-500 dark:text-gray-400">
                    {property.reference_number} • {property.quarter} {property.year}
                  </p>
                </div>
                <span className="px-2 py-1 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300 rounded text-xs font-medium">
                  {property.days_overdue} days overdue
                </span>
              </div>
              <div className="flex justify-between items-center">
                <p className="font-bold text-red-600 dark:text-red-400">
                  {formatCurrency(property.total_quarterly_tax)}
                </p>
                <div className="flex items-center space-x-2">
                  <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(property.payment_status)}`}>
                    {getStatusIcon(property.payment_status)} {property.payment_status}
                  </span>
                  <p className="text-xs text-gray-500">
                    Due: {formatDate(property.due_date)}
                  </p>
                </div>
              </div>
            </div>
          ))}
        </div>
        
        {overdueList.length === 0 && (
          <div className="text-center py-8 text-gray-500">
            No overdue properties found
          </div>
        )}
      </div>

      {/* Recent Activities */}
      <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4 sm:p-6 border border-gray-200 dark:border-gray-700 mb-8">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-6">Recent System Activities</h3>
        <div className="space-y-3">
          {recent_activities.slice(0, 8).map((activity, index) => (
            <div key={index} className="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
              <div className="flex items-center space-x-3">
                <span className="text-xl">
                  {getActivityIcon(activity.type)}
                </span>
                <div>
                  <p className="font-medium text-gray-900 dark:text-white">
                    {activity.full_name}
                    {activity.reference_number && ` • ${activity.reference_number}`}
                    {activity.receipt_number && ` • Receipt: ${activity.receipt_number}`}
                  </p>
                  <p className="text-sm text-gray-500 dark:text-gray-400">
                    {activity.type === 'registration' && 'Property Registration'}
                    {activity.type === 'payment' && 'Tax Payment'}
                    {activity.type === 'inspection' && 'Property Inspection'}
                  </p>
                </div>
              </div>
              <div className="text-right">
                {activity.amount && (
                  <p className="font-bold text-green-600 dark:text-green-400">
                    {formatCurrency(activity.amount)}
                  </p>
                )}
                <div className="flex items-center space-x-2">
                  <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(activity.status)}`}>
                    {getStatusIcon(activity.status)} {activity.status}
                  </span>
                  <p className="text-xs text-gray-400">
                    {formatDate(activity.created_at)}
                  </p>
                </div>
              </div>
            </div>
          ))}
        </div>
        
        {recent_activities.length === 0 && (
          <div className="text-center py-8 text-gray-500">
            No recent activities
          </div>
        )}
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div className="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow p-6 text-white">
          <h4 className="text-lg font-semibold mb-3">Total Assigned Tax</h4>
          <p className="text-3xl font-bold mb-2">
            {formatCurrency(overallPerformance.total_assigned || 0)}
          </p>
          <p className="text-blue-100 text-sm">{current_year} Assessment</p>
        </div>

        <div className="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow p-6 text-white">
          <h4 className="text-lg font-semibold mb-3">Total Collected</h4>
          <p className="text-3xl font-bold mb-2">
            {formatCurrency(overallPerformance.total_collected || 0)}
          </p>
          <p className="text-emerald-100 text-sm">{overallPerformance.collection_rate || 0}% collection rate</p>
        </div>

        <div className="bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl shadow p-6 text-white">
          <h4 className="text-lg font-semibold mb-3">Outstanding Balance</h4>
          <p className="text-3xl font-bold mb-2">
            {formatCurrency((overallPerformance.total_assigned || 0) - (overallPerformance.total_collected || 0))}
          </p>
          <p className="text-amber-100 text-sm">{overdueList.length} properties overdue</p>
        </div>
      </div>

      {/* Footer */}
      <div className="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
        <div className="flex flex-col sm:flex-row justify-between items-center text-sm text-gray-500 dark:text-gray-400">
          <p>Last updated: {new Date().toLocaleString('en-PH')}</p>
          <div className="flex items-center space-x-4 mt-2 sm:mt-0">
            <span className="flex items-center">
              <span className="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
              Properties: {properties.approved_properties || 0}
            </span>
            <span className="flex items-center">
              <span className="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
              Owners: {owners.active_owners || 0}
            </span>
            <span className="flex items-center">
              <span className="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
              Overdue: {overdueList.length}
            </span>
            <span className="flex items-center">
              <span className="w-2 h-2 bg-purple-500 rounded-full mr-2"></span>
              Buildings: {grandTotals.total_buildings || 0}
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}