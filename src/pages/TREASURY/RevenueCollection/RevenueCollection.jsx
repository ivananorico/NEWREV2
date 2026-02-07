import React, { useState, useEffect, useMemo } from 'react';
import { 
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip,
  PieChart, Pie, Cell, ResponsiveContainer, AreaChart, Area 
} from 'recharts';

export default function RevenueCollection() {
  const [transactions, setTransactions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [dateRange, setDateRange] = useState({
    start: '2026-01-01',
    end: '2026-02-07'
  });
  const [selectedSystem, setSelectedSystem] = useState('all');

  // API URL - Make sure this is correct
  const API_URL = 'http://localhost/revenue2/backend/Digital/revenue/get_transactions.php';

  // Load data
  const loadData = async () => {
    try {
      setLoading(true);
      
      const params = new URLSearchParams({
        start_date: dateRange.start,
        end_date: dateRange.end,
        limit: 1000
      });
      
      if (selectedSystem !== 'all') params.append('client_system', selectedSystem);
      
      console.log('Fetching from:', `${API_URL}?${params.toString()}`);
      
      const response = await fetch(`${API_URL}?${params.toString()}`);
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const result = await response.json();
      console.log('API Response:', result);
      
      if (result.status === 'success') {
        setTransactions(result.data?.transactions || []);
      } else {
        console.error('API Error:', result.message);
      }
    } catch (error) {
      console.error('Error loading data:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [dateRange, selectedSystem]);

  // Calculate revenue statistics
  const revenueStats = useMemo(() => {
    // Filter only paid transactions (actual revenue collected)
    const paidTransactions = transactions.filter(t => t.payment_status === 'paid');
    
    // Calculate total revenue collected
    const totalRevenue = paidTransactions.reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
    
    // Calculate revenue by system
    const systemRevenue = {};
    paidTransactions.forEach(t => {
      const system = t.client_system || 'Unknown';
      if (!systemRevenue[system]) {
        systemRevenue[system] = {
          revenue: 0,
          count: 0
        };
      }
      systemRevenue[system].revenue += parseFloat(t.amount || 0);
      systemRevenue[system].count++;
    });
    
    // Convert to array for charts
    const systemRevenueArray = Object.entries(systemRevenue).map(([system, data]) => ({
      system,
      revenue: data.revenue,
      count: data.count,
      percentage: totalRevenue > 0 ? (data.revenue / totalRevenue * 100) : 0
    })).sort((a, b) => b.revenue - a.revenue);
    
    // Calculate daily revenue
    const dailyRevenue = {};
    paidTransactions.forEach(t => {
      const date = t.transaction_date || t.created_at?.split(' ')[0];
      if (date) {
        if (!dailyRevenue[date]) {
          dailyRevenue[date] = {
            revenue: 0,
            count: 0
          };
        }
        dailyRevenue[date].revenue += parseFloat(t.amount || 0);
        dailyRevenue[date].count++;
      }
    });
    
    const dailyRevenueArray = Object.entries(dailyRevenue).map(([date, data]) => ({
      date,
      revenue: data.revenue,
      count: data.count
    })).sort((a, b) => new Date(a.date) - new Date(b.date));
    
    // Payment method revenue
    const methodRevenue = {};
    paidTransactions.forEach(t => {
      const method = t.payment_method || 'Unknown';
      if (!methodRevenue[method]) {
        methodRevenue[method] = {
          revenue: 0,
          count: 0
        };
      }
      methodRevenue[method].revenue += parseFloat(t.amount || 0);
      methodRevenue[method].count++;
    });
    
    const methodRevenueArray = Object.entries(methodRevenue).map(([method, data]) => ({
      method,
      revenue: data.revenue,
      count: data.count
    }));
    
    return {
      totalRevenue,
      totalTransactions: paidTransactions.length,
      pendingTransactions: transactions.filter(t => t.payment_status === 'pending').length,
      systemRevenue: systemRevenueArray,
      dailyRevenue: dailyRevenueArray,
      methodRevenue: methodRevenueArray,
      avgTransaction: paidTransactions.length > 0 ? totalRevenue / paidTransactions.length : 0
    };
  }, [transactions]);

  // Format currency
  const formatCurrency = (amount) => {
    if (!amount && amount !== 0) return '₱0.00';
    return `₱${parseFloat(amount).toLocaleString('en-PH', { 
      minimumFractionDigits: 2, 
      maximumFractionDigits: 2 
    })}`;
  };

  // Get system display name
  const getSystemName = (system) => {
    const names = {
      'rpt': 'RPT',
      'business': 'Business Tax',
      'market': 'Market Stall',
      'market_rent': 'Market Rent',
      'sanitation': 'Sanitation',
      'wss': 'Water & Sanitation',
      'franchise': 'Franchise',
      'tmm': 'Traffic Fines',
      'zoning': 'Zoning',
      'cemetery': 'Cemetery'
    };
    return names[system] || system || 'Unknown';
  };

  // Get system color
  const getSystemColor = (system) => {
    const colors = {
      'rpt': '#4CAF50',
      'business': '#2196F3',
      'market': '#FF9800',
      'market_rent': '#FF5722',
      'sanitation': '#9C27B0',
      'wss': '#00BCD4',
      'franchise': '#795548',
      'tmm': '#F44336',
      'zoning': '#3F51B5',
      'cemetery': '#009688'
    };
    return colors[system] || '#607D8B';
  };

  // Custom tooltip
  const CustomTooltip = ({ active, payload, label }) => {
    if (active && payload && payload.length) {
      return (
        <div className="bg-white p-3 border border-gray-300 rounded shadow">
          <p className="font-medium">{label}</p>
          {payload.map((entry, index) => (
            <p key={index} style={{ color: entry.color }}>
              {entry.dataKey === 'revenue' || entry.dataKey === 'value' ? 
                `${entry.name}: ${formatCurrency(entry.value)}` : 
                `${entry.name}: ${entry.value}`
              }
            </p>
          ))}
        </div>
      );
    }
    return null;
  };

  if (loading) {
    return (
      <div className="p-8">
        <div className="text-center py-12">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading Revenue Data...</p>
          <button 
            onClick={loadData}
            className="mt-4 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
          >
            Retry Loading
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="p-4">
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-800">💰 Revenue Collection Summary</h1>
        <p className="text-gray-600">Total Revenue Collected: {formatCurrency(revenueStats.totalRevenue)}</p>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-lg shadow p-4 mb-6">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label className="block text-sm font-medium mb-1">Start Date</label>
            <input
              type="date"
              value={dateRange.start}
              onChange={(e) => setDateRange({...dateRange, start: e.target.value})}
              className="w-full p-2 border rounded"
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">End Date</label>
            <input
              type="date"
              value={dateRange.end}
              onChange={(e) => setDateRange({...dateRange, end: e.target.value})}
              className="w-full p-2 border rounded"
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Revenue Source</label>
            <select
              value={selectedSystem}
              onChange={(e) => setSelectedSystem(e.target.value)}
              className="w-full p-2 border rounded"
            >
              <option value="all">All Sources</option>
              {[...new Set(transactions.map(t => t.client_system).filter(Boolean))].map(system => (
                <option key={system} value={system}>{getSystemName(system)}</option>
              ))}
            </select>
          </div>
        </div>
        
        <div className="mt-4 flex justify-between items-center">
          <button
            onClick={() => {
              setDateRange({ start: '2026-01-01', end: '2026-02-07' });
              setSelectedSystem('all');
            }}
            className="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded"
          >
            Reset Filters
          </button>
          <button
            onClick={loadData}
            className="px-4 py-2 text-sm bg-blue-500 text-white rounded hover:bg-blue-600"
          >
            Refresh Data
          </button>
        </div>
      </div>

      {/* Revenue Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div className="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <div className="text-sm opacity-90">Total Revenue Collected</div>
              <div className="text-3xl font-bold mt-2">
                {formatCurrency(revenueStats.totalRevenue)}
              </div>
            </div>
            <div className="text-4xl">💰</div>
          </div>
          <div className="mt-4 text-sm opacity-80">
            {revenueStats.totalTransactions} successful payments
          </div>
        </div>
        
        <div className="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <div className="text-sm opacity-90">Average Payment</div>
              <div className="text-3xl font-bold mt-2">
                {formatCurrency(revenueStats.avgTransaction)}
              </div>
            </div>
            <div className="text-4xl">📊</div>
          </div>
          <div className="mt-4 text-sm opacity-80">
            Per transaction average
          </div>
        </div>
        
        <div className="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg shadow p-6">
          <div className="flex items-center justify-between">
            <div>
              <div className="text-sm opacity-90">Pending Collection</div>
              <div className="text-3xl font-bold mt-2">
                {revenueStats.pendingTransactions}
              </div>
            </div>
            <div className="text-4xl">⏳</div>
          </div>
          <div className="mt-4 text-sm opacity-80">
            Transactions awaiting payment
          </div>
        </div>
      </div>

      {/* Revenue Distribution */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {/* Revenue by System */}
        <div className="bg-white rounded-lg shadow p-4">
          <h3 className="font-semibold mb-4">Revenue Collected by System</h3>
          <div className="h-64">
            {revenueStats.systemRevenue.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={revenueStats.systemRevenue}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                  <XAxis 
                    dataKey="system" 
                    tickFormatter={getSystemName}
                    angle={-45}
                    textAnchor="end"
                    height={60}
                    tick={{ fontSize: 11 }}
                  />
                  <YAxis 
                    tickFormatter={(value) => `₱${(value / 1000).toFixed(0)}K`}
                    tick={{ fontSize: 12 }}
                  />
                  <Tooltip 
                    formatter={(value) => [formatCurrency(value), 'Revenue']}
                    labelFormatter={getSystemName}
                  />
                  <Bar 
                    dataKey="revenue" 
                    name="Revenue"
                    radius={[4, 4, 0, 0]}
                  >
                    {revenueStats.systemRevenue.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={getSystemColor(entry.system)} />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-full flex items-center justify-center text-gray-500">
                No revenue data available
              </div>
            )}
          </div>
          
          {/* System Revenue Table */}
          <div className="mt-4">
            <div className="text-sm font-medium mb-2">Revenue Breakdown</div>
            <div className="space-y-2 max-h-48 overflow-y-auto">
              {revenueStats.systemRevenue.map((item, index) => (
                <div key={index} className="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                  <div className="flex items-center">
                    <div 
                      className="w-3 h-3 rounded-full mr-2"
                      style={{ backgroundColor: getSystemColor(item.system) }}
                    ></div>
                    <span className="text-sm">{getSystemName(item.system)}</span>
                  </div>
                  <div className="text-right">
                    <div className="font-semibold">{formatCurrency(item.revenue)}</div>
                    <div className="text-xs text-gray-500">
                      {item.count} payments • {item.percentage.toFixed(1)}%
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Payment Methods Revenue */}
        <div className="bg-white rounded-lg shadow p-4">
          <h3 className="font-semibold mb-4">Revenue by Payment Method</h3>
          <div className="h-64">
            {revenueStats.methodRevenue.length > 0 ? (
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={revenueStats.methodRevenue}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ method, percentage }) => 
                      `${method}: ${formatCurrency(percentage)}`
                    }
                    outerRadius={80}
                    fill="#8884d8"
                    dataKey="revenue"
                  >
                    {revenueStats.methodRevenue.map((entry, index) => (
                      <Cell 
                        key={`cell-${index}`} 
                        fill={['#10B981', '#3B82F6', '#8B5CF6', '#F59E0B'][index % 4]} 
                      />
                    ))}
                  </Pie>
                  <Tooltip formatter={(value) => [formatCurrency(value), 'Revenue']} />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-full flex items-center justify-center text-gray-500">
                No payment method data
              </div>
            )}
          </div>
          
          {/* Method Revenue Details */}
          <div className="mt-4">
            <div className="text-sm font-medium mb-2">Payment Method Breakdown</div>
            <div className="space-y-2">
              {revenueStats.methodRevenue.map((item, index) => (
                <div key={index} className="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                  <span className="text-sm capitalize">{item.method || 'Unknown'}</span>
                  <div className="text-right">
                    <div className="font-semibold">{formatCurrency(item.revenue)}</div>
                    <div className="text-xs text-gray-500">{item.count} transactions</div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Daily Revenue Trend */}
      <div className="bg-white rounded-lg shadow p-4 mb-6">
        <h3 className="font-semibold mb-4">Daily Revenue Collection Trend</h3>
        <div className="h-64">
          {revenueStats.dailyRevenue.length > 0 ? (
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={revenueStats.dailyRevenue}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                <XAxis 
                  dataKey="date" 
                  tick={{ fontSize: 12 }}
                />
                <YAxis 
                  tickFormatter={(value) => `₱${(value / 1000).toFixed(0)}K`}
                  tick={{ fontSize: 12 }}
                />
                <Tooltip content={<CustomTooltip />} />
                <Area 
                  type="monotone" 
                  dataKey="revenue" 
                  name="Daily Revenue"
                  stroke="#3B82F6" 
                  fill="#3B82F6" 
                  fillOpacity={0.1}
                  strokeWidth={2}
                />
              </AreaChart>
            </ResponsiveContainer>
          ) : (
            <div className="h-full flex items-center justify-center text-gray-500">
              No daily revenue data available
            </div>
          )}
        </div>
        
        {/* Daily Stats */}
        {revenueStats.dailyRevenue.length > 0 && (
          <div className="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="text-center p-3 bg-gray-50 rounded">
              <div className="text-sm text-gray-600">Highest Day</div>
              <div className="font-semibold">
                {formatCurrency(Math.max(...revenueStats.dailyRevenue.map(d => d.revenue)))}
              </div>
              <div className="text-xs text-gray-500">
                {revenueStats.dailyRevenue.find(d => 
                  d.revenue === Math.max(...revenueStats.dailyRevenue.map(d => d.revenue))
                )?.date}
              </div>
            </div>
            <div className="text-center p-3 bg-gray-50 rounded">
              <div className="text-sm text-gray-600">Average Daily</div>
              <div className="font-semibold">
                {formatCurrency(
                  revenueStats.dailyRevenue.reduce((sum, d) => sum + d.revenue, 0) / 
                  revenueStats.dailyRevenue.length
                )}
              </div>
              <div className="text-xs text-gray-500">
                {revenueStats.dailyRevenue.length} days
              </div>
            </div>
            <div className="text-center p-3 bg-gray-50 rounded">
              <div className="text-sm text-gray-600">Total Days</div>
              <div className="font-semibold">{revenueStats.dailyRevenue.length}</div>
              <div className="text-xs text-gray-500">With revenue</div>
            </div>
            <div className="text-center p-3 bg-gray-50 rounded">
              <div className="text-sm text-gray-600">Daily Average Tx</div>
              <div className="font-semibold">
                {(revenueStats.dailyRevenue.reduce((sum, d) => sum + d.count, 0) / 
                 revenueStats.dailyRevenue.length).toFixed(1)}
              </div>
              <div className="text-xs text-gray-500">Transactions per day</div>
            </div>
          </div>
        )}
      </div>

      {/* Summary Stats */}
      <div className="bg-white rounded-lg shadow p-4">
        <h3 className="font-semibold mb-4">Collection Summary</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          {revenueStats.systemRevenue.slice(0, 4).map((item, index) => (
            <div key={index} className="p-4 border rounded-lg">
              <div className="flex items-center mb-2">
                <div 
                  className="w-4 h-4 rounded-full mr-2"
                  style={{ backgroundColor: getSystemColor(item.system) }}
                ></div>
                <span className="font-medium">{getSystemName(item.system)}</span>
              </div>
              <div className="text-2xl font-bold text-gray-800">
                {formatCurrency(item.revenue)}
              </div>
              <div className="text-sm text-gray-600 mt-1">
                {item.count} payments • {item.percentage.toFixed(1)}% of total
              </div>
              <div className="mt-2 text-xs text-gray-500">
                Avg: {formatCurrency(item.revenue / item.count)}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}