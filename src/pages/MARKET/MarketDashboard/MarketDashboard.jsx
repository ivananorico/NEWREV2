import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
  PieChart, Pie, Cell, LineChart, Line, AreaChart, Area
} from 'recharts';
import {
  Users, Store, DollarSign, TrendingUp, Calendar, AlertCircle,
  CheckCircle, Clock, PieChart as PieChartIcon, BarChart3, RefreshCw,
  ArrowUpRight, ArrowDownRight, ShoppingBag, FileText, Activity,
  UserCheck, Receipt, Home, ShoppingCart, CreditCard, TrendingDown,
  Target, Percent, Package, Layers, Shield, User, Wallet, History,
  ChevronRight, Download, Filter, Search
} from 'lucide-react';

export default function MarketDashboard() {
  const [dashboardData, setDashboardData] = useState({
    totals: {
      total_citizens: 0,
      active_stalls: 0,
      monthly_revenue: 0,
      total_contract_value: 0,
      pending_applications: 0,
      overdue_payments: 0,
      total_paid: 0,
      active_renters: 0,
      available_stalls: 0,
      today_payments: 0,
      this_month_payments: 0,
      last_month_payments: 0
    },
    recentActivities: [],
    topStalls: [],
    revenueTrend: [],
    businessTypes: [],
    statusDistribution: [],
    recentPayments: [],
    topPayers: [],
    paymentMethods: [],
    loading: true
  });
  
  const navigate = useNavigate();
  const [timeRange, setTimeRange] = useState('month');
  const [showAllPayments, setShowAllPayments] = useState(false);

  // Detect environment
  const isLocalhost = window.location.hostname === 'localhost' || 
                      window.location.hostname === '127.0.0.1';
  const API_BASE = isLocalhost
    ? "http://localhost/revenue2/backend/Market/MarketDashboard"
    : "/backend/Market/MarketDashboard";

  useEffect(() => {
    fetchDashboardData();
    // Refresh every 3 minutes
    const interval = setInterval(fetchDashboardData, 180000);
    return () => clearInterval(interval);
  }, []);

  const fetchDashboardData = async () => {
    try {
      setDashboardData(prev => ({ ...prev, loading: true }));
      
      const response = await fetch(`${API_BASE}/dashboard_data.php?range=${timeRange}`, {
        headers: { 
          'Cache-Control': 'no-cache',
          'Pragma': 'no-cache'
        }
      });
      
      if (!response.ok) throw new Error('Failed to fetch data');
      
      const data = await response.json();
      
      if (data.status === 'success') {
        setDashboardData({
          totals: data.totals || {},
          recentActivities: data.recent_activities || [],
          topStalls: data.top_stalls || [],
          revenueTrend: data.revenue_trend || [],
          businessTypes: data.business_types || [],
          statusDistribution: data.status_distribution || [],
          recentPayments: data.recent_payments || [],
          topPayers: data.top_payers || [],
          paymentMethods: data.payment_methods || [],
          loading: false
        });
      }
    } catch (error) {
      console.error('Error:', error);
      setDashboardData(prev => ({ ...prev, loading: false }));
    }
  };

  // Format currency
  const formatCurrency = (amount) => {
    const num = parseFloat(amount) || 0;
    return `₱${num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  };

  // Format number
  const formatNumber = (num) => num.toLocaleString('en-PH');

  // Format date
  const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    });
  };

  // Format time
  const formatTime = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleTimeString('en-PH', {
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  // Calculate percentages for charts
  const calculateStallOccupancy = () => {
    const total = dashboardData.totals.active_stalls + dashboardData.totals.available_stalls;
    return total > 0 ? {
      occupied: Math.round((dashboardData.totals.active_stalls / total) * 100),
      available: Math.round((dashboardData.totals.available_stalls / total) * 100)
    } : { occupied: 0, available: 0 };
  };

  // Calculate payment growth
  const calculatePaymentGrowth = () => {
    const { this_month_payments, last_month_payments } = dashboardData.totals;
    if (last_month_payments === 0) return 100;
    return Math.round(((this_month_payments - last_month_payments) / last_month_payments) * 100);
  };

  // Prepare data for revenue trend chart
  const prepareRevenueData = () => {
    return dashboardData.revenueTrend.map(item => ({
      month: item.month,
      revenue: parseFloat(item.revenue) || 0
    })).reverse();
  };

  // Prepare data for business types chart
  const prepareBusinessTypeData = () => {
    return dashboardData.businessTypes.map((type, index) => ({
      name: type.name,
      value: type.count,
      color: type.color || `#${Math.floor(Math.random()*16777215).toString(16)}`
    }));
  };

  // Prepare data for status distribution
  const prepareStatusData = () => {
    const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8'];
    return dashboardData.statusDistribution.map((status, index) => ({
      name: status.status || 'Unknown',
      value: status.count,
      color: COLORS[index % COLORS.length]
    }));
  };

  // Prepare data for payment methods
  const preparePaymentMethodData = () => {
    const COLORS = ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b'];
    return dashboardData.paymentMethods.map((method, index) => ({
      name: method.method || 'Unknown',
      value: method.count,
      amount: parseFloat(method.total_amount) || 0,
      color: COLORS[index % COLORS.length]
    }));
  };

  // Prepare top payers data
  const prepareTopPayersData = () => {
    return dashboardData.topPayers.map(payer => ({
      name: payer.full_name,
      amount: parseFloat(payer.total_paid) || 0,
      last_payment: payer.last_payment_date,
      payments: payer.payment_count
    }));
  };

  // Stat Card Component
  const StatCard = ({ title, value, icon: Icon, change, color = 'blue', subtitle, onClick }) => (
    <div 
      onClick={onClick}
      className={`bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 cursor-pointer hover:shadow-md transition-all ${
        onClick ? 'hover:scale-[1.02]' : ''
      }`}
    >
      <div className="flex justify-between items-start">
        <div>
          <p className="text-sm text-gray-600 dark:text-gray-400">{title}</p>
          <p className={`text-2xl font-bold mt-2 ${
            color === 'blue' ? 'text-blue-700 dark:text-blue-400' :
            color === 'green' ? 'text-green-700 dark:text-green-400' :
            color === 'purple' ? 'text-purple-700 dark:text-purple-400' :
            color === 'orange' ? 'text-orange-700 dark:text-orange-400' :
            color === 'red' ? 'text-red-700 dark:text-red-400' :
            'text-gray-700 dark:text-gray-300'
          }`}>
            {value}
          </p>
          {subtitle && (
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{subtitle}</p>
          )}
        </div>
        <div className={`p-3 rounded-xl ${
          color === 'blue' ? 'bg-blue-100 text-blue-600 dark:bg-blue-900/30' :
          color === 'green' ? 'bg-green-100 text-green-600 dark:bg-green-900/30' :
          color === 'purple' ? 'bg-purple-100 text-purple-600 dark:bg-purple-900/30' :
          color === 'orange' ? 'bg-orange-100 text-orange-600 dark:bg-orange-900/30' :
          color === 'red' ? 'bg-red-100 text-red-600 dark:bg-red-900/30' :
          'bg-gray-100 text-gray-600 dark:bg-gray-900/30'
        }`}>
          <Icon className="w-6 h-6" />
        </div>
      </div>
      {change !== undefined && (
        <div className="flex items-center mt-4 text-sm">
          {change > 0 ? (
            <ArrowUpRight className="w-4 h-4 text-green-500 mr-1" />
          ) : change < 0 ? (
            <ArrowDownRight className="w-4 h-4 text-red-500 mr-1" />
          ) : (
            <span className="w-4 h-4 mr-1">─</span>
          )}
          <span className={change > 0 ? 'text-green-600' : change < 0 ? 'text-red-600' : 'text-gray-600'}>
            {change > 0 ? '+' : ''}{change}%
          </span>
          <span className="text-gray-500 dark:text-gray-400 ml-2">vs last month</span>
        </div>
      )}
    </div>
  );

  // Payment Card Component
  const PaymentCard = ({ payment }) => (
    <div className="flex items-center justify-between p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:shadow-sm transition-shadow">
      <div className="flex items-center gap-3">
        <div className="w-10 h-10 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center">
          <User className="w-5 h-5 text-green-600" />
        </div>
        <div>
          <p className="font-medium text-gray-900 dark:text-white">{payment.renter_name}</p>
          <p className="text-sm text-gray-600 dark:text-gray-400">{payment.business_name}</p>
          <div className="flex items-center gap-2 mt-1">
            <span className="text-xs px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded">
              {payment.stall_rights_no}
            </span>
            <span className="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded">
              {payment.payment_method}
            </span>
          </div>
        </div>
      </div>
      <div className="text-right">
        <p className="text-lg font-bold text-green-700 dark:text-green-400">{formatCurrency(payment.amount_paid)}</p>
        <div className="flex items-center gap-2 text-sm text-gray-500">
          <Calendar className="w-3 h-3" />
          {formatDate(payment.payment_date)} {formatTime(payment.payment_date)}
        </div>
        <p className="text-xs text-gray-500 mt-1">{payment.receipt_number}</p>
      </div>
    </div>
  );

  if (dashboardData.loading) {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-gray-900 p-6">
        <div className="flex items-center justify-center h-96">
          <div className="text-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
            <p className="mt-4 text-gray-600 dark:text-gray-400">Loading dashboard...</p>
          </div>
        </div>
      </div>
    );
  }

  const occupancy = calculateStallOccupancy();
  const paymentGrowth = calculatePaymentGrowth();
  const revenueData = prepareRevenueData();
  const businessTypeData = prepareBusinessTypeData();
  const statusData = prepareStatusData();
  const paymentMethodData = preparePaymentMethodData();
  const topPayersData = prepareTopPayersData();

  const displayedPayments = showAllPayments ? 
    dashboardData.recentPayments : 
    dashboardData.recentPayments.slice(0, 5);

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 p-4 md:p-6">
      {/* Header */}
      <div className="mb-6">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Market Analytics Dashboard</h1>
            <p className="text-gray-600 dark:text-gray-400 mt-1">
              Comprehensive overview with payment tracking
            </p>
          </div>
          <div className="flex items-center gap-3">
            <div className="text-sm text-gray-600 dark:text-gray-400">
              {new Date().toLocaleDateString('en-PH', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
              })}
            </div>
            <button
              onClick={fetchDashboardData}
              className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-2 transition-colors"
            >
              <RefreshCw className="w-4 h-4" />
              Refresh
            </button>
          </div>
        </div>
      </div>

      {/* Time Range Selector */}
      <div className="flex gap-2 mb-6">
        {['day', 'week', 'month', 'year'].map(range => (
          <button
            key={range}
            onClick={() => setTimeRange(range)}
            className={`px-4 py-2 rounded-lg text-sm font-medium ${
              timeRange === range 
                ? 'bg-blue-600 text-white' 
                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700'
            }`}
          >
            {range.charAt(0).toUpperCase() + range.slice(1)}
          </button>
        ))}
      </div>

      {/* Key Metrics Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard 
          title="Total Revenue" 
          value={formatCurrency(dashboardData.totals.monthly_revenue)} 
          icon={DollarSign}
          change={12.5}
          color="green"
          subtitle="Monthly collection"
          onClick={() => navigate('/market/reports')}
        />
        <StatCard 
          title="Total Paid" 
          value={formatCurrency(dashboardData.totals.total_paid)} 
          icon={Wallet}
          change={paymentGrowth}
          color="purple"
          subtitle="All-time payments"
          onClick={() => navigate('/market/payments')}
        />
        <StatCard 
          title="Today's Collection" 
          value={formatCurrency(dashboardData.totals.today_payments)} 
          icon={CreditCard}
          color="blue"
          subtitle="Daily revenue"
        />
        <StatCard 
          title="Active Renters" 
          value={formatNumber(dashboardData.totals.active_renters)} 
          icon={Users}
          change={5.7}
          color="orange"
          subtitle="Paying citizens"
        />
      </div>

      {/* Second Row Metrics */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard 
          title="Active Stalls" 
          value={formatNumber(dashboardData.totals.active_stalls)} 
          icon={Store}
          change={8.2}
          color="blue"
          subtitle={`${occupancy.occupied}% occupancy`}
        />
        <StatCard 
          title="Pending Applications" 
          value={formatNumber(dashboardData.totals.pending_applications)} 
          icon={Clock}
          change={-2.1}
          color="yellow"
          subtitle="Awaiting approval"
        />
        <StatCard 
          title="Overdue Payments" 
          value={formatNumber(dashboardData.totals.overdue_payments)} 
          icon={AlertCircle}
          change={0}
          color="red"
          subtitle="Require attention"
        />
        <StatCard 
          title="Available Stalls" 
          value={formatNumber(dashboardData.totals.available_stalls)} 
          icon={Home}
          change={-3.4}
          color="green"
          subtitle={`${occupancy.available}% available`}
        />
      </div>

      {/* Charts Section */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {/* Revenue Trend Chart */}
        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
          <div className="flex items-center justify-between mb-4">
            <div>
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Revenue Trend</h2>
              <p className="text-sm text-gray-600 dark:text-gray-400">Payment history over time</p>
            </div>
            <TrendingUp className="w-5 h-5 text-blue-500" />
          </div>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={revenueData} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                <XAxis 
                  dataKey="month" 
                  stroke="#666"
                  tick={{ fill: '#666', fontSize: 12 }}
                />
                <YAxis 
                  stroke="#666"
                  tickFormatter={(value) => `₱${(value/1000).toFixed(0)}K`}
                  tick={{ fill: '#666', fontSize: 12 }}
                />
                <Tooltip 
                  formatter={(value) => [`₱${value.toLocaleString()}`, 'Revenue']}
                  labelFormatter={(label) => `Month: ${label}`}
                />
                <Area 
                  type="monotone" 
                  dataKey="revenue" 
                  stroke="#3b82f6" 
                  fill="#3b82f6" 
                  fillOpacity={0.3}
                  strokeWidth={2}
                />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Payment Methods */}
        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
          <div className="flex items-center justify-between mb-4">
            <div>
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Payment Methods</h2>
              <p className="text-sm text-gray-600 dark:text-gray-400">How renters are paying</p>
            </div>
            <CreditCard className="w-5 h-5 text-green-500" />
          </div>
          <div className="h-64">
            {paymentMethodData.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={paymentMethodData}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ name, percent }) => percent > 0.05 ? `${name}` : ''}
                    outerRadius={80}
                    fill="#8884d8"
                    dataKey="value"
                  >
                    {paymentMethodData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.color} />
                    ))}
                  </Pie>
                  <Tooltip 
                    formatter={(value, name, props) => [
                      `₱${props.payload.amount.toLocaleString()}`,
                      `${props.payload.name} (${value} payments)`
                    ]}
                  />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <div className="flex items-center justify-center h-full">
                <p className="text-gray-500">No payment data available</p>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Recent Payments Section */}
      <div className="mb-6">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Recent Rent Payments</h2>
            <p className="text-sm text-gray-600 dark:text-gray-400">Who paid and when</p>
          </div>
          <div className="flex items-center gap-3">
            <button
              onClick={() => setShowAllPayments(!showAllPayments)}
              className="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg flex items-center gap-2"
            >
              {showAllPayments ? 'Show Less' : 'Show All'}
              <ChevronRight className="w-4 h-4" />
            </button>
            <button
              onClick={() => navigate('/market/payments')}
              className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-2"
            >
              <History className="w-4 h-4" />
              View All Payments
            </button>
          </div>
        </div>
        
        <div className="space-y-3">
          {displayedPayments.length > 0 ? (
            displayedPayments.map((payment, index) => (
              <PaymentCard key={index} payment={payment} />
            ))
          ) : (
            <div className="text-center py-8 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
              <CreditCard className="w-12 h-12 text-gray-300 mx-auto mb-3" />
              <p className="text-gray-500">No recent payments found</p>
            </div>
          )}
        </div>
      </div>

      {/* Top Payers & Business Types */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {/* Top Payers */}
        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
          <div className="flex items-center justify-between mb-4">
            <div>
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Top Payers</h2>
              <p className="text-sm text-gray-600 dark:text-gray-400">Highest contributing renters</p>
            </div>
            <UserCheck className="w-5 h-5 text-green-500" />
          </div>
          <div className="space-y-4">
            {topPayersData.length > 0 ? (
              topPayersData.map((payer, index) => (
                <div key={index} className="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-lg">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center">
                      <span className="font-bold text-blue-600">{index + 1}</span>
                    </div>
                    <div>
                      <p className="font-medium text-gray-900 dark:text-white">{payer.name}</p>
                      <p className="text-sm text-gray-500 dark:text-gray-400">
                        {payer.payments} payments • Last: {formatDate(payer.last_payment)}
                      </p>
                    </div>
                  </div>
                  <div className="text-right">
                    <p className="font-bold text-green-700 dark:text-green-400">{formatCurrency(payer.amount)}</p>
                    <p className="text-xs text-gray-500">Total paid</p>
                  </div>
                </div>
              ))
            ) : (
              <div className="text-center py-8">
                <p className="text-gray-500">No payer data available</p>
              </div>
            )}
          </div>
        </div>

        {/* Business Types */}
        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
          <div className="flex items-center justify-between mb-4">
            <div>
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Business Types</h2>
              <p className="text-sm text-gray-600 dark:text-gray-400">Stall category distribution</p>
            </div>
            <PieChartIcon className="w-5 h-5 text-purple-500" />
          </div>
          <div className="h-64">
            {businessTypeData.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={businessTypeData}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ name, percent }) => percent > 0.05 ? `${name}: ${(percent * 100).toFixed(0)}%` : ''}
                    outerRadius={80}
                    fill="#8884d8"
                    dataKey="value"
                  >
                    {businessTypeData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.color} />
                    ))}
                  </Pie>
                  <Tooltip formatter={(value) => [value, 'Stalls']} />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <div className="flex items-center justify-center h-full">
                <p className="text-gray-500">No business type data available</p>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Performance Metrics */}
      <div className="mb-6">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Performance Metrics</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <div className="flex items-center gap-3">
              <Target className="w-5 h-5 text-blue-600" />
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-400">Occupancy Rate</p>
                <p className="text-xl font-bold text-gray-900 dark:text-white">{occupancy.occupied}%</p>
              </div>
            </div>
          </div>
          
          <div className="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <div className="flex items-center gap-3">
              <Percent className="w-5 h-5 text-green-600" />
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-400">Collection Rate</p>
                <p className="text-xl font-bold text-gray-900 dark:text-white">
                  {dashboardData.totals.monthly_revenue > 0 
                    ? Math.round((dashboardData.totals.total_paid / dashboardData.totals.monthly_revenue) * 100) 
                    : 0}%
                </p>
              </div>
            </div>
          </div>
          
          <div className="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <div className="flex items-center gap-3">
              <TrendingUp className="w-5 h-5 text-purple-600" />
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-400">Avg Stall Revenue</p>
                <p className="text-xl font-bold text-gray-900 dark:text-white">
                  {formatCurrency(
                    dashboardData.totals.active_stalls > 0 
                      ? dashboardData.totals.monthly_revenue / dashboardData.totals.active_stalls 
                      : 0
                  )}
                </p>
              </div>
            </div>
          </div>
          
          <div className="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <div className="flex items-center gap-3">
              <Package className="w-5 h-5 text-orange-600" />
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-400">Avg Payment</p>
                <p className="text-xl font-bold text-gray-900 dark:text-white">
                  {formatCurrency(
                    dashboardData.totals.total_paid / Math.max(dashboardData.recentPayments.length, 1)
                  )}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Quick Actions */}
      <div className="mb-6">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h2>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <button
            onClick={() => navigate('/market/payments')}
            className="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-left hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors"
          >
            <CreditCard className="w-6 h-6 text-green-600 mb-2" />
            <h3 className="font-medium text-gray-900 dark:text-white">View All Payments</h3>
            <p className="text-sm text-gray-600 dark:text-gray-400">Complete payment history</p>
          </button>
          
          <button
            onClick={() => navigate('/market/reports')}
            className="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg text-left hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors"
          >
            <FileText className="w-6 h-6 text-blue-600 mb-2" />
            <h3 className="font-medium text-gray-900 dark:text-white">Generate Reports</h3>
            <p className="text-sm text-gray-600 dark:text-gray-400">Export financial reports</p>
          </button>
          
          <button
            onClick={() => navigate('/market/citizens')}
            className="p-4 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg text-left hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors"
          >
            <Users className="w-6 h-6 text-purple-600 mb-2" />
            <h3 className="font-medium text-gray-900 dark:text-white">Manage Renters</h3>
            <p className="text-sm text-gray-600 dark:text-gray-400">View all market citizens</p>
          </button>
        </div>
      </div>

      {/* Footer */}
      <div className="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
        <div className="flex flex-col md:flex-row justify-between items-center text-sm text-gray-600 dark:text-gray-400">
          <div>
            Market Analytics Dashboard • 
            Last updated: {new Date().toLocaleTimeString([], { 
              hour: '2-digit', 
              minute: '2-digit',
              second: '2-digit'
            })}
          </div>
          <div className="flex items-center gap-4 mt-2 md:mt-0">
            <div className="flex items-center gap-2">
              <div className="w-2 h-2 rounded-full bg-green-500"></div>
              <span>Paid</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-2 h-2 rounded-full bg-blue-500"></div>
              <span>Active</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-2 h-2 rounded-full bg-red-500"></div>
              <span>Overdue</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}