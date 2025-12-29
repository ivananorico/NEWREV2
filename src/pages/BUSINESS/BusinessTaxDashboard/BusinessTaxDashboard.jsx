import React, { useState, useEffect } from 'react';
import { 
  BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, 
  Tooltip, Legend, ResponsiveContainer, LineChart, Line, Area
} from 'recharts';
import { 
  Building, Home, DollarSign, Users, Calendar, AlertCircle, 
  TrendingUp, TrendingDown, RefreshCw, MapPin, Tag, Filter,
  Download, FileText, BarChart3, PieChart as PieChartIcon,
  CheckCircle, Clock, XCircle, Landmark, Percent, Eye, Target,
  ArrowUpRight, ArrowDownRight, CircleDollarSign, ShieldAlert,
  CreditCard, Wallet, Timer, CalendarDays, TrendingUp as TrendingUpIcon,
  Banknote, AlertTriangle, CheckCheck, ArrowRightLeft, ChevronRight,
  Building2, Layers, Grid3x3, Compass, LandPlot, FileBarChart,
  Activity, LineChart as LineChartIcon, Calculator,
  FileSpreadsheet, Database, Table, ChevronDown, ChevronUp,
  Map, Award, Trophy, Star, TrendingDown as TrendingDownIcon,
  BarChart as BarChartIcon, LineChart as LineChartIcon2,
  ChartBar
} from 'lucide-react';
import * as XLSX from 'xlsx';

// Auto-detect environment
const getApiBase = () => {
  const isLocalhost = window.location.hostname === 'localhost' || 
                     window.location.hostname === '127.0.0.1';
  
  if (isLocalhost) {
    return 'http://localhost/revenue2/backend/Business/BusinessTaxDashboard';
  } else {
    return 'https://revenuetreasury.goserveph.com/backend/Business/BusinessTaxDashboard';
  }
};

const API_BASE = getApiBase();

export default function BusinessTaxDashboard() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [dashboardData, setDashboardData] = useState(null);
  const [exportLoading, setExportLoading] = useState(false);
  const [activeTab, setActiveTab] = useState('payments');
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
  const [availableYears, setAvailableYears] = useState([]);
  const [yearDropdownOpen, setYearDropdownOpen] = useState(false);
  const [currentQuarter] = useState(() => {
    const month = new Date().getMonth() + 1;
    if (month >= 1 && month <= 3) return 'Q1';
    if (month >= 4 && month <= 6) return 'Q2';
    if (month >= 7 && month <= 9) return 'Q3';
    return 'Q4';
  });
  const [chartType, setChartType] = useState('bar'); // 'bar', 'pie', 'line'
  const [showTop, setShowTop] = useState(10); // Show top 10 barangays by default

  useEffect(() => {
    fetchAvailableYears();
  }, []);

  useEffect(() => {
    if (availableYears.length > 0) {
      const latestYear = Math.max(...availableYears);
      if (!availableYears.includes(selectedYear)) {
        setSelectedYear(latestYear);
      }
    }
  }, [availableYears]);

  useEffect(() => {
    fetchDashboardData();
  }, [selectedYear]);

  const fetchAvailableYears = async () => {
    try {
      const response = await fetch(`${API_BASE}/BusinessTaxDashboard.php?action=get_years`);
      const data = await response.json();
      
      if (data.success && data.years && data.years.length > 0) {
        setAvailableYears(data.years);
        const latestYear = Math.max(...data.years);
        setSelectedYear(latestYear);
      } else {
        const currentYear = new Date().getFullYear();
        setAvailableYears([currentYear - 1, currentYear]);
        setSelectedYear(currentYear);
      }
    } catch (err) {
      console.error('Error fetching years:', err);
      const currentYear = new Date().getFullYear();
      setAvailableYears([currentYear - 1, currentYear]);
      setSelectedYear(currentYear);
    }
  };

  const fetchDashboardData = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(`${API_BASE}/BusinessTaxDashboard.php?action=dashboard&year=${selectedYear}`, {
        headers: { 'Accept': 'application/json' }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (!data.success) {
        throw new Error(data.error || data.message || 'Failed to load dashboard data');
      }
      
      const parsedData = parseNumbersInData(data.data);
      setDashboardData(parsedData);
      
    } catch (err) {
      console.error('Error:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const parseNumbersInData = (data) => {
    if (!data) return data;
    
    const parsed = JSON.parse(JSON.stringify(data));
    
    const parseNumericFields = (obj) => {
      if (!obj) return obj;
      
      Object.keys(obj).forEach(key => {
        if (typeof obj[key] === 'string' && !isNaN(obj[key]) && obj[key] !== '') {
          if (/^-?\d*\.?\d+$/.test(obj[key])) {
            obj[key] = parseFloat(obj[key]);
          }
        } else if (typeof obj[key] === 'object' && obj[key] !== null) {
          parseNumericFields(obj[key]);
        }
      });
    };
    
    parseNumericFields(parsed);
    return parsed;
  };

  const formatCurrency = (amount) => {
    if (amount === null || amount === undefined || amount === '' || isNaN(amount)) {
      return '₱0';
    }
    
    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
    
    if (numAmount >= 1000000000) {
      return `₱${(numAmount / 1000000000).toFixed(2)}B`;
    }
    if (numAmount >= 1000000) {
      return `₱${(numAmount / 1000000).toFixed(2)}M`;
    }
    if (numAmount >= 1000) {
      return `₱${(numAmount / 1000).toFixed(2)}K`;
    }
    return `₱${numAmount.toFixed(2)}`;
  };

  const safeParseFloat = (value, defaultValue = 0) => {
    if (value === null || value === undefined || value === '') return defaultValue;
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return isNaN(num) ? defaultValue : num;
  };

  const formatNumber = (num) => {
    const parsedNum = safeParseFloat(num);
    return new Intl.NumberFormat('en-PH').format(parsedNum);
  };

  const formatPercent = (value) => {
    const parsedValue = safeParseFloat(value);
    return `${parsedValue.toFixed(1)}%`;
  };

  const getProgressColor = (value) => {
    const numValue = safeParseFloat(value);
    if (numValue >= 90) return 'bg-green-500';
    if (numValue >= 75) return 'bg-yellow-500';
    return 'bg-red-500';
  };

  const getStatusColor = (status) => {
    switch(status) {
      case 'paid': return 'bg-green-100 text-green-800';
      case 'pending': return 'bg-yellow-100 text-yellow-800';
      case 'overdue': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const exportToExcel = (data, fileName, sheetName = 'Sheet1') => {
    try {
      if (!data || data.length === 0) {
        alert('No data available to export');
        return;
      }

      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.json_to_sheet(data);
      XLSX.utils.book_append_sheet(wb, ws, sheetName);
      XLSX.writeFile(wb, `${fileName}_${selectedYear}_${new Date().toISOString().split('T')[0]}.xlsx`);
      
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting to Excel');
    }
  };

  const exportBarangayReport = () => {
    if (!dashboardData?.barangay_collection) return;
    
    setExportLoading(true);
    try {
      const totalCollection = dashboardData.barangay_collection.reduce((total, curr) => total + safeParseFloat(curr.total_collection), 0);
      const barangayData = dashboardData.barangay_collection.map(b => ({
        'Barangay': b.barangay,
        'Total Collection (PHP)': safeParseFloat(b.total_collection),
        'Number of Businesses': safeParseFloat(b.business_count),
        'Average Tax per Business (PHP)': safeParseFloat(b.avg_tax_per_business),
        'Percentage of Total (%)': totalCollection > 0 ? (safeParseFloat(b.total_collection) / totalCollection) * 100 : 0
      }));

      exportToExcel(barangayData, `Barangay_Tax_Report_${selectedYear}`, 'Barangay Collection');
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting barangay report');
    } finally {
      setExportLoading(false);
    }
  };

  const exportQuarterlyReport = () => {
    if (!dashboardData?.quarterly_analysis) return;
    
    setExportLoading(true);
    try {
      const quarterlyData = dashboardData.quarterly_analysis.map(q => ({
        'Year': q.year,
        'Quarter': q.quarter,
        'Total Due (PHP)': safeParseFloat(q.total_due),
        'Collected (PHP)': safeParseFloat(q.collected),
        'Collection Rate (%)': safeParseFloat(q.collection_rate),
        'Paid Count': safeParseFloat(q.paid_count),
        'Overdue Amount (PHP)': safeParseFloat(q.overdue_amount),
        'Overdue Count': safeParseFloat(q.overdue_count),
        'Pending Amount (PHP)': safeParseFloat(q.pending_amount),
        'Pending Count': safeParseFloat(q.pending_count),
        'Average Days Late': safeParseFloat(q.avg_days_late),
        'Total Discounts (PHP)': safeParseFloat(q.total_discounts),
        'Total Penalties (PHP)': safeParseFloat(q.total_penalties)
      }));

      exportToExcel(quarterlyData, `Business_Quarterly_Report_${selectedYear}`, 'Quarterly Analysis');
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting quarterly report');
    } finally {
      setExportLoading(false);
    }
  };

  const exportCompleteDashboardReport = () => {
    if (!dashboardData) return;
    
    setExportLoading(true);
    try {
      const wb = XLSX.utils.book_new();
      const dateStr = new Date().toISOString().split('T')[0];
      
      // 1. Summary Sheet
      const summaryData = [{
        'Year': selectedYear,
        'Total Businesses': formatNumber(dashboardData.business_stats?.total_businesses),
        'Active Businesses': formatNumber(dashboardData.business_stats?.active_businesses),
        'Total Annual Tax': formatCurrency(dashboardData.tax_stats?.annual?.total_annual_tax),
        'Collection Rate': formatPercent(dashboardData.yearly_summary?.collection_rate),
        'Current Quarter': dashboardData.current_quarter,
        'Total Outstanding': formatCurrency(dashboardData.tax_stats?.outstanding?.total_outstanding),
        'Data Updated': dashboardData.timestamp
      }];
      
      const ws1 = XLSX.utils.json_to_sheet(summaryData);
      XLSX.utils.book_append_sheet(wb, ws1, 'Dashboard Summary');
      
      // 2. Quarterly Analysis Sheet
      if (dashboardData.quarterly_analysis?.length > 0) {
        const quarterlyData = dashboardData.quarterly_analysis.map(q => ({
          'Year': q.year,
          'Quarter': q.quarter,
          'Total Due (PHP)': safeParseFloat(q.total_due),
          'Collected (PHP)': safeParseFloat(q.collected),
          'Collection Rate (%)': safeParseFloat(q.collection_rate),
          'Paid Count': safeParseFloat(q.paid_count),
          'Overdue Amount (PHP)': safeParseFloat(q.overdue_amount),
          'Overdue Count': safeParseFloat(q.overdue_count),
          'Pending Amount (PHP)': safeParseFloat(q.pending_amount),
          'Pending Count': safeParseFloat(q.pending_count),
          'Average Days Late': safeParseFloat(q.avg_days_late),
          'Total Discounts (PHP)': safeParseFloat(q.total_discounts),
          'Total Penalties (PHP)': safeParseFloat(q.total_penalties)
        }));
        
        const ws2 = XLSX.utils.json_to_sheet(quarterlyData);
        XLSX.utils.book_append_sheet(wb, ws2, 'Quarterly Analysis');
      }
      
      // 3. Business Types Sheet
      if (dashboardData.business_types?.length > 0) {
        const businessTypeData = dashboardData.business_types.map(b => ({
          'Business Type': b.business_type,
          'Count': safeParseFloat(b.count),
          'Percentage (%)': safeParseFloat(b.percentage)
        }));
        
        const ws3 = XLSX.utils.json_to_sheet(businessTypeData);
        XLSX.utils.book_append_sheet(wb, ws3, 'Business Types');
      }
      
      // 4. Barangay Collection Sheet
      if (dashboardData.barangay_collection?.length > 0) {
        const totalCollection = dashboardData.barangay_collection.reduce((total, curr) => total + safeParseFloat(curr.total_collection), 0);
        const barangayData = dashboardData.barangay_collection.map(b => ({
          'Barangay': b.barangay,
          'Total Collection (PHP)': safeParseFloat(b.total_collection),
          'Number of Businesses': safeParseFloat(b.business_count),
          'Average Tax per Business (PHP)': safeParseFloat(b.avg_tax_per_business),
          'Percentage of Total (%)': totalCollection > 0 ? (safeParseFloat(b.total_collection) / totalCollection) * 100 : 0
        }));
        
        const ws4 = XLSX.utils.json_to_sheet(barangayData);
        XLSX.utils.book_append_sheet(wb, ws4, 'Barangay Collection');
      }
      
      XLSX.writeFile(wb, `Business_Tax_Complete_Report_${selectedYear}_${dateStr}.xlsx`);
      
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting complete report');
    } finally {
      setExportLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="flex flex-col justify-center items-center h-screen">
        <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-blue-600 mb-4"></div>
        <p className="text-gray-600">Loading Business Tax Dashboard...</p>
        <p className="text-sm text-gray-400 mt-2">Fetching data for {selectedYear}</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="max-w-4xl mx-auto p-6">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6">
          <div className="flex items-center space-x-3 mb-4">
            <AlertCircle className="w-8 h-8 text-red-600" />
            <div>
              <h3 className="text-lg font-semibold text-red-600">Error Loading Dashboard</h3>
              <p className="text-red-600">{error}</p>
            </div>
          </div>
          <button 
            onClick={fetchDashboardData}
            className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center gap-2"
          >
            <RefreshCw className="w-4 h-4" />
            Try Again
          </button>
        </div>
      </div>
    );
  }

  if (!dashboardData) {
    return (
      <div className="text-center py-12">
        <Building className="w-16 h-16 text-gray-400 mx-auto mb-4" />
        <p className="text-gray-500">No dashboard data available for {selectedYear}</p>
        <button 
          onClick={fetchDashboardData}
          className="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2 mx-auto"
        >
          <RefreshCw className="w-4 h-4" />
          Load Dashboard
        </button>
      </div>
    );
  }

  // Extract data with defaults
  const {
    business_stats = {},
    tax_stats = {},
    quarterly_analysis = [],
    business_types = [],
    top_taxpayers = [],
    barangay_collection = [],
    overdue_taxes = [],
    recent_payments = [],
    config = {},
    yearly_summary = {},
    current_quarter: dataCurrentQuarter,
    timestamp,
    available_years = []
  } = dashboardData;

  // Calculate key metrics
  const collectionRate = safeParseFloat(yearly_summary.collection_rate);
  const totalAnnualTax = safeParseFloat(tax_stats.annual?.total_annual_tax);
  const quarterlyTarget = safeParseFloat(tax_stats.annual?.total_annual_tax) / 4;
  const currentQuarterCollected = safeParseFloat(tax_stats.current_quarter?.current_quarter_paid);
  const totalOutstanding = safeParseFloat(tax_stats.outstanding?.total_outstanding);
  
  // Prepare chart data
  const quarterlyChartData = quarterly_analysis.map(q => ({
    quarter: q.quarter,
    total_due: safeParseFloat(q.total_due),
    collected: safeParseFloat(q.collected),
    overdue_amount: safeParseFloat(q.overdue_amount),
    pending_amount: safeParseFloat(q.pending_amount),
    collection_rate: safeParseFloat(q.collection_rate)
  }));

  const businessTypeData = business_types.map(b => ({
    name: b.business_type,
    value: safeParseFloat(b.count),
    percentage: safeParseFloat(b.percentage)
  }));

  const barangaysData = barangay_collection.map(b => ({
    name: b.barangay,
    revenue: safeParseFloat(b.total_collection),
    businesses: safeParseFloat(b.business_count),
    avg_tax: safeParseFloat(b.avg_tax_per_business)
  }));

  // Sort barangays by revenue (highest first)
  const sortedBarangays = [...barangaysData].sort((a, b) => b.revenue - a.revenue);
  
  // Get top N barangays based on showTop state
  const topBarangays = sortedBarangays.slice(0, showTop);
  
  // Prepare data for charts
  const barangayChartData = topBarangays.map(barangay => ({
    name: barangay.name.length > 15 ? barangay.name.substring(0, 12) + '...' : barangay.name,
    fullName: barangay.name,
    revenue: barangay.revenue,
    businesses: barangay.businesses,
    avg_tax: barangay.avg_tax
  }));

  // Calculate total collection for percentages
  const totalBarangayCollection = barangaysData.reduce((total, barangay) => total + barangay.revenue, 0);

  // Calculate overall collection rate
  const overallCollection = quarterly_analysis.reduce((acc, q) => {
    return {
      total_due: acc.total_due + safeParseFloat(q.total_due),
      collected: acc.collected + safeParseFloat(q.collected)
    };
  }, { total_due: 0, collected: 0 });

  const effectiveCollectionRate = overallCollection.total_due > 0 
    ? (overallCollection.collected / overallCollection.total_due) * 100 
    : 0;

  // Get activities for active tab
  const getActivitiesForTab = () => {
    switch(activeTab) {
      case 'payments':
        return recent_payments || [];
      case 'overdue':
        return overdue_taxes || [];
      default:
        return recent_payments || [];
    }
  };

  // Render the appropriate chart based on chartType
  const renderBarangayChart = () => {
    if (barangayChartData.length === 0) {
      return (
        <div className="flex flex-col items-center justify-center h-72 text-gray-400">
          <BarChartIcon className="w-12 h-12 mb-2" />
          <p>No barangay data available for chart</p>
        </div>
      );
    }

    if (chartType === 'pie') {
      return (
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Pie
              data={barangayChartData}
              cx="50%"
              cy="50%"
              labelLine={false}
              label={({ name, percent }) => `${name}: ${(percent * 100).toFixed(0)}%`}
              outerRadius={80}
              fill="#8884d8"
              dataKey="revenue"
            >
              {barangayChartData.map((entry, index) => (
                <Cell key={`cell-${index}`} fill={BARANGAY_COLORS[index % BARANGAY_COLORS.length]} />
              ))}
            </Pie>
            <Tooltip 
              formatter={(value) => [formatCurrency(value), 'Revenue']}
              labelFormatter={(label) => {
                const barangay = barangayChartData.find(b => b.name === label) || {};
                return `Barangay: ${barangay.fullName || label}`;
              }}
            />
            <Legend />
          </PieChart>
        </ResponsiveContainer>
      );
    } else if (chartType === 'line') {
      return (
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={barangayChartData}>
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
            <XAxis 
              dataKey="name" 
              angle={-45}
              textAnchor="end"
              height={60}
            />
            <YAxis 
              tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
            />
            <Tooltip 
              formatter={(value) => [formatCurrency(value), 'Revenue']}
              labelFormatter={(label) => {
                const barangay = barangayChartData.find(b => b.name === label) || {};
                return `Barangay: ${barangay.fullName || label}`;
              }}
            />
            <Legend />
            <Line 
              type="monotone" 
              dataKey="revenue" 
              stroke="#8884d8" 
              activeDot={{ r: 8 }} 
              strokeWidth={2}
              name="Tax Revenue"
            />
            <Line 
              type="monotone" 
              dataKey="avg_tax" 
              stroke="#82ca9d" 
              strokeWidth={2}
              name="Avg per Business"
            />
          </LineChart>
        </ResponsiveContainer>
      );
    } else {
      // Default to bar chart
      return (
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={barangayChartData}>
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
            <XAxis 
              dataKey="name" 
              angle={-45}
              textAnchor="end"
              height={60}
            />
            <YAxis 
              tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
            />
            <Tooltip 
              formatter={(value, name) => {
                const formattedValue = formatCurrency(value);
                const label = name === 'revenue' ? 'Tax Revenue' :
                             name === 'businesses' ? 'Businesses' :
                             name === 'avg_tax' ? 'Avg per Business' : name;
                return [formattedValue, label];
              }}
              labelFormatter={(label) => {
                const barangay = barangayChartData.find(b => b.name === label) || {};
                return `Barangay: ${barangay.fullName || label}`;
              }}
            />
            <Legend />
            <Bar 
              dataKey="revenue" 
              fill="#8884d8" 
              name="Tax Revenue" 
              radius={[4, 4, 0, 0]}
            />
            <Bar 
              dataKey="avg_tax" 
              fill="#82ca9d" 
              name="Avg per Business" 
              radius={[4, 4, 0, 0]}
            />
          </BarChart>
        </ResponsiveContainer>
      );
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-6">
        <div className="max-w-7xl mx-auto">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
              <div className="flex items-center gap-3 mb-2">
                <Building className="w-8 h-8" />
                <h1 className="text-2xl sm:text-3xl font-bold">Business Tax Collection Dashboard</h1>
              </div>
              <p className="text-blue-100 flex items-center gap-2">
                <CalendarDays className="w-4 h-4" />
                {dataCurrentQuarter || currentQuarter} {selectedYear} • 
                {timestamp ? new Date(timestamp).toLocaleDateString('en-PH', { 
                  month: 'long', 
                  day: 'numeric', 
                  year: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit'
                }) : 'Live'}
              </p>
            </div>
            
            <div className="flex flex-wrap gap-2 items-center">
              {/* Year Selection Dropdown */}
              <div className="relative">
                <button
                  onClick={() => setYearDropdownOpen(!yearDropdownOpen)}
                  className="flex items-center gap-2 px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg transition-colors"
                >
                  <Calendar className="w-4 h-4" />
                  <span>Year: {selectedYear}</span>
                  {yearDropdownOpen ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                </button>
                
                {yearDropdownOpen && (
                  <div className="absolute top-full right-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                    <div className="py-1">
                      {(available_years.length > 0 ? available_years : availableYears).map(year => (
                        <button
                          key={year}
                          onClick={() => {
                            setSelectedYear(year);
                            setYearDropdownOpen(false);
                          }}
                          className={`w-full text-left px-4 py-2 hover:bg-blue-50 transition-colors ${
                            selectedYear === year 
                              ? 'bg-blue-100 text-blue-700 font-medium' 
                              : 'text-gray-700'
                          }`}
                        >
                          <div className="flex items-center justify-between">
                            <span>{year}</span>
                            {selectedYear === year && (
                              <CheckCircle className="w-4 h-4 text-blue-600" />
                            )}
                          </div>
                        </button>
                      ))}
                    </div>
                  </div>
                )}
              </div>
              
              <button
                onClick={fetchDashboardData}
                className="flex items-center gap-2 px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg transition-colors"
              >
                <RefreshCw className="w-4 h-4" />
                Refresh
              </button>
              <button
                onClick={exportCompleteDashboardReport}
                disabled={exportLoading}
                className="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors disabled:opacity-50"
              >
                {exportLoading ? (
                  <>
                    <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div>
                    <span>Exporting...</span>
                  </>
                ) : (
                  <>
                    <FileSpreadsheet className="w-4 h-4" />
                    <span>Export All</span>
                  </>
                )}
              </button>
            </div>
          </div>
          
          {/* Available Years Quick Select */}
          <div className="mt-4 flex flex-wrap gap-2">
            {(available_years.length > 0 ? available_years : availableYears).map(year => (
              <button
                key={year}
                onClick={() => setSelectedYear(year)}
                className={`px-3 py-1 text-sm rounded-full transition-colors ${
                  selectedYear === year
                    ? 'bg-white text-blue-700 font-medium'
                    : 'bg-white/20 text-white hover:bg-white/30'
                }`}
              >
                {year}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
        {/* Year Info Banner */}
        <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-blue-100 rounded-lg">
                <Calendar className="w-5 h-5 text-blue-600" />
              </div>
              <div>
                <h3 className="font-semibold text-blue-800">Viewing Data for {selectedYear}</h3>
                <p className="text-sm text-blue-600">
                  {quarterly_analysis.length > 0 
                    ? `Showing ${quarterly_analysis.length} quarters of business tax collection` 
                    : 'No quarterly data available for this year'}
                </p>
              </div>
            </div>
            <div className="text-sm text-blue-700">
              {available_years.length > 1 && (
                <p>Available years: {available_years.join(', ')}</p>
              )}
            </div>
          </div>
        </div>

        {/* Export Options Bar */}
        {exportLoading && (
          <div className="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <div className="flex items-center gap-2">
              <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-blue-600"></div>
              <span className="text-sm text-blue-600">Preparing Excel export for {selectedYear}...</span>
            </div>
          </div>
        )}

        <div className="bg-white rounded-xl border border-gray-200 p-4">
          <div className="flex flex-wrap gap-2">
            <button
              onClick={exportQuarterlyReport}
              disabled={exportLoading || quarterly_analysis.length === 0}
              className="flex items-center gap-2 px-3 py-2 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 text-sm disabled:opacity-50"
            >
              <Calendar className="w-4 h-4" />
              Quarterly Report
            </button>
            <button
              onClick={exportBarangayReport}
              disabled={exportLoading || barangay_collection.length === 0}
              className="flex items-center gap-2 px-3 py-2 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 text-sm disabled:opacity-50"
            >
              <MapPin className="w-4 h-4" />
              Barangay Report
            </button>
            <button
              onClick={exportCompleteDashboardReport}
              disabled={exportLoading}
              className="flex items-center gap-2 px-3 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 text-sm disabled:opacity-50"
            >
              <Database className="w-4 h-4" />
              Complete Report
            </button>
          </div>
        </div>

        {/* Top Key Metrics */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Collection Performance */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-blue-50 rounded-lg">
                <Target className="w-6 h-6 text-blue-600" />
              </div>
              <span className={`text-sm px-2 py-1 rounded-full ${getProgressColor(effectiveCollectionRate)} text-white`}>
                {formatPercent(effectiveCollectionRate)}
              </span>
            </div>
            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-1">Collection Rate</h3>
            <p className="text-2xl font-bold text-gray-900 mb-2">
              {formatCurrency(overallCollection.collected)}
            </p>
            <div className="text-xs text-gray-500">
              <div className="flex justify-between mb-1">
                <span>Target:</span>
                <span className="font-medium">{formatCurrency(overallCollection.total_due)}</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div 
                  className={`h-2 rounded-full ${getProgressColor(effectiveCollectionRate)}`}
                  style={{ width: `${Math.min(effectiveCollectionRate, 100)}%` }}
                ></div>
              </div>
            </div>
          </div>

          {/* Annual Tax Assessment */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-green-50 rounded-lg">
                <Calculator className="w-6 h-6 text-green-600" />
              </div>
              <span className="text-sm px-2 py-1 bg-green-100 text-green-800 rounded-full">
                {selectedYear} Assessment
              </span>
            </div>
            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-1">Total Assessment</h3>
            <p className="text-2xl font-bold text-gray-900 mb-2">{formatCurrency(totalAnnualTax)}</p>
            <div className="text-xs text-gray-500 space-y-1">
              <div className="flex justify-between">
                <span className="flex items-center gap-1">
                  <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                  Tax Amount:
                </span>
                <span>{formatCurrency(tax_stats.annual?.total_tax_amount)}</span>
              </div>
              <div className="flex justify-between">
                <span className="flex items-center gap-1">
                  <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                  Regulatory Fees:
                </span>
                <span>{formatCurrency(tax_stats.annual?.total_fees)}</span>
              </div>
            </div>
          </div>

          {/* Current Quarter Performance */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-yellow-50 rounded-lg">
                <Calendar className="w-6 h-6 text-yellow-600" />
              </div>
              <span className={`text-sm px-2 py-1 rounded-full ${
                (currentQuarterCollected / quarterlyTarget) >= 0.8 ? 'bg-green-100 text-green-800' :
                (currentQuarterCollected / quarterlyTarget) >= 0.6 ? 'bg-yellow-100 text-yellow-800' :
                'bg-red-100 text-red-800'
              }`}>
                {dataCurrentQuarter || currentQuarter} Progress
              </span>
            </div>
            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-1">Current Quarter</h3>
            <p className="text-2xl font-bold text-gray-900 mb-2">{formatCurrency(currentQuarterCollected)}</p>
            <div className="text-xs text-gray-500">
              <div className="flex justify-between mb-1">
                <span>Target:</span>
                <span className="font-medium">{formatCurrency(quarterlyTarget)}</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div 
                  className={`h-2 rounded-full ${
                    (currentQuarterCollected / quarterlyTarget) >= 0.8 ? 'bg-green-500' :
                    (currentQuarterCollected / quarterlyTarget) >= 0.6 ? 'bg-yellow-500' :
                    'bg-red-500'
                  }`}
                  style={{ width: `${Math.min((currentQuarterCollected / quarterlyTarget) * 100, 100)}%` }}
                ></div>
              </div>
            </div>
          </div>

          {/* Outstanding Balance */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 bg-red-50 rounded-lg">
                <AlertTriangle className="w-6 h-6 text-red-600" />
              </div>
              <span className="text-sm px-2 py-1 bg-red-100 text-red-800 rounded-full">
                Delinquent
              </span>
            </div>
            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-1">Outstanding Balance</h3>
            <p className="text-2xl font-bold text-gray-900 mb-2">{formatCurrency(totalOutstanding)}</p>
            <div className="text-xs text-gray-500 space-y-1">
              <div className="flex justify-between">
                <span>Pending:</span>
                <span>{formatCurrency(tax_stats.outstanding?.pending_balance)}</span>
              </div>
              <div className="flex justify-between">
                <span>Overdue:</span>
                <span>{formatCurrency(tax_stats.outstanding?.overdue_balance)}</span>
              </div>
              <div className="flex justify-between font-medium">
                <span>Total Bills:</span>
                <span>{formatNumber(tax_stats.outstanding?.outstanding_bills)}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Charts Section */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Quarterly Collection Performance */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex justify-between items-center mb-6">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                <Activity className="w-5 h-5 text-blue-600" />
                Quarterly Collection Performance {selectedYear}
              </h3>
              <button
                onClick={exportQuarterlyReport}
                disabled={exportLoading || quarterly_analysis.length === 0}
                className="flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700 disabled:opacity-50"
              >
                <Download className="w-4 h-4" />
                Export
              </button>
            </div>
            <div className="h-72">
              {quarterlyChartData.length > 0 ? (
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={quarterlyChartData}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                    <XAxis dataKey="quarter" />
                    <YAxis 
                      tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
                    />
                    <Tooltip 
                      formatter={(value, name) => {
                        const formattedValue = formatCurrency(value);
                        const label = name === 'collection_rate' ? 'Collection Rate' : 
                                     name === 'total_due' ? 'Total Due' :
                                     name === 'collected' ? 'Paid' :
                                     name === 'overdue_amount' ? 'Overdue' :
                                     name === 'pending_amount' ? 'Pending' : name;
                        return [name === 'collection_rate' ? `${safeParseFloat(value).toFixed(1)}%` : formattedValue, label];
                      }}
                      labelFormatter={(label) => `Quarter: ${label}`}
                    />
                    <Legend />
                    <Bar dataKey="collected" fill="#10B981" name="Paid" radius={[4, 4, 0, 0]} />
                    <Bar dataKey="overdue_amount" fill="#EF4444" name="Overdue" radius={[4, 4, 0, 0]} />
                    <Bar dataKey="pending_amount" fill="#F59E0B" name="Pending" radius={[4, 4, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              ) : (
                <div className="flex flex-col items-center justify-center h-full text-gray-400">
                  <BarChart3 className="w-12 h-12 mb-2" />
                  <p>No quarterly data available for {selectedYear}</p>
                </div>
              )}
            </div>
          </div>

          {/* Business Types Distribution */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex justify-between items-center mb-6">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                <PieChartIcon className="w-5 h-5 text-purple-600" />
                Business Types Distribution
              </h3>
              <button
                onClick={exportCompleteDashboardReport}
                disabled={exportLoading}
                className="flex items-center gap-1 text-sm text-purple-600 hover:text-purple-700 disabled:opacity-50"
              >
                <Download className="w-4 h-4" />
                Export
              </button>
            </div>
            <div className="h-72">
              {businessTypeData.length > 0 ? (
                <ResponsiveContainer width="100%" height="100%">
                  <PieChart>
                    <Pie
                      data={businessTypeData}
                      cx="50%"
                      cy="50%"
                      labelLine={false}
                      label={({ name, percent }) => `${name}: ${(percent * 100).toFixed(0)}%`}
                      outerRadius={80}
                      fill="#8884d8"
                      dataKey="value"
                    >
                      {businessTypeData.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                      ))}
                    </Pie>
                    <Tooltip 
                      formatter={(value, name, props) => {
                        const percent = (props.payload.percentage || 0).toFixed(1);
                        return [`${value} businesses (${percent}%)`, 'Count'];
                      }}
                    />
                    <Legend />
                  </PieChart>
                </ResponsiveContainer>
              ) : (
                <div className="flex flex-col items-center justify-center h-full text-gray-400">
                  <PieChartIcon className="w-12 h-12 mb-2" />
                  <p>No business type data available</p>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Barangay-wise Tax Collection Section with Chart */}
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm">
          <div className="p-5 border-b border-gray-200">
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
              <div>
                <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                  <Map className="w-5 h-5 text-red-600" />
                  Barangay-wise Tax Collection {selectedYear}
                </h3>
                <p className="text-sm text-gray-500 mt-1">
                  Showing top {showTop} barangays by tax revenue
                </p>
              </div>
              <div className="flex flex-wrap gap-2">
                {/* Chart Type Selector */}
                <div className="flex border border-gray-300 rounded-lg overflow-hidden">
                  <button
                    onClick={() => setChartType('bar')}
                    className={`px-3 py-1 text-sm transition-colors ${
                      chartType === 'bar' 
                        ? 'bg-blue-600 text-white' 
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    }`}
                  >
                    <BarChartIcon className="w-4 h-4 inline-block mr-1" />
                    Bar
                  </button>
                  <button
                    onClick={() => setChartType('pie')}
                    className={`px-3 py-1 text-sm transition-colors ${
                      chartType === 'pie' 
                        ? 'bg-blue-600 text-white' 
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    }`}
                  >
                    <PieChartIcon className="w-4 h-4 inline-block mr-1" />
                    Pie
                  </button>
                  <button
                    onClick={() => setChartType('line')}
                    className={`px-3 py-1 text-sm transition-colors ${
                      chartType === 'line' 
                        ? 'bg-blue-600 text-white' 
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    }`}
                  >
                    <LineChartIcon2 className="w-4 h-4 inline-block mr-1" />
                    Line
                  </button>
                </div>
                
                {/* Show Top Selector */}
                <div className="flex border border-gray-300 rounded-lg overflow-hidden">
                  <button
                    onClick={() => setShowTop(5)}
                    className={`px-3 py-1 text-sm transition-colors ${
                      showTop === 5 
                        ? 'bg-green-600 text-white' 
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    }`}
                  >
                    Top 5
                  </button>
                  <button
                    onClick={() => setShowTop(10)}
                    className={`px-3 py-1 text-sm transition-colors ${
                      showTop === 10 
                        ? 'bg-green-600 text-white' 
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    }`}
                  >
                    Top 10
                  </button>
                  <button
                    onClick={() => setShowTop(15)}
                    className={`px-3 py-1 text-sm transition-colors ${
                      showTop === 15 
                        ? 'bg-green-600 text-white' 
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    }`}
                  >
                    Top 15
                  </button>
                </div>
                
                <button
                  onClick={exportBarangayReport}
                  disabled={exportLoading || barangay_collection.length === 0}
                  className="flex items-center gap-2 px-3 py-2 bg-red-50 text-red-700 rounded-lg hover:bg-red-100 text-sm disabled:opacity-50"
                >
                  <Download className="w-4 h-4" />
                  Export
                </button>
              </div>
            </div>
          </div>
          
          <div className="p-5">
            {barangaysData.length > 0 ? (
              <div className="space-y-6">
                {/* Barangay Collection Chart */}
                <div className="bg-gray-50 rounded-xl p-4 border border-gray-200">
                  <div className="mb-4">
                    <h4 className="font-semibold text-gray-700 mb-2 flex items-center gap-2">
                      <ChartBar className="w-5 h-5 text-blue-600" />
                      Barangay Tax Revenue Visualization
                    </h4>
                    <div className="text-sm text-gray-500">
                      Visual representation of tax collection across barangays
                    </div>
                  </div>
                  <div className="h-80">
                    {renderBarangayChart()}
                  </div>
                </div>
                
                {/* Summary Stats */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div className="bg-blue-50 p-4 rounded-lg">
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-sm font-medium text-blue-700">Total Barangays</span>
                      <MapPin className="w-5 h-5 text-blue-600" />
                    </div>
                    <p className="text-2xl font-bold text-blue-800">{barangaysData.length}</p>
                    <p className="text-sm text-blue-600 mt-1">With Business Tax Revenue</p>
                  </div>
                  
                  <div className="bg-green-50 p-4 rounded-lg">
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-sm font-medium text-green-700">Total Collection</span>
                      <DollarSign className="w-5 h-5 text-green-600" />
                    </div>
                    <p className="text-2xl font-bold text-green-800">{formatCurrency(totalBarangayCollection)}</p>
                    <p className="text-sm text-green-600 mt-1">From All Barangays</p>
                  </div>
                  
                  <div className="bg-purple-50 p-4 rounded-lg">
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-sm font-medium text-purple-700">Total Businesses</span>
                      <Building className="w-5 h-5 text-purple-600" />
                    </div>
                    <p className="text-2xl font-bold text-purple-800">
                      {formatNumber(barangaysData.reduce((total, b) => total + b.businesses, 0))}
                    </p>
                    <p className="text-sm text-purple-600 mt-1">Across All Barangays</p>
                  </div>
                </div>
                
                {/* Top Performing Barangays */}
                <div>
                  <h4 className="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <Trophy className="w-5 h-5 text-yellow-600" />
                    Top Performing Barangays
                  </h4>
                  <div className="space-y-3">
                    {topBarangays.map((barangay, index) => {
                      const percentage = totalBarangayCollection > 0 
                        ? (barangay.revenue / totalBarangayCollection) * 100 
                        : 0;
                      
                      return (
                        <div key={index} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                          <div className="flex items-center gap-3">
                            <div className={`w-8 h-8 rounded-full flex items-center justify-center ${
                              index === 0 ? 'bg-yellow-100 text-yellow-800' :
                              index === 1 ? 'bg-gray-200 text-gray-800' :
                              index === 2 ? 'bg-orange-100 text-orange-800' :
                              'bg-blue-100 text-blue-800'
                            }`}>
                              {index === 0 && <Trophy className="w-4 h-4" />}
                              {index === 1 && <Star className="w-4 h-4" />}
                              {index === 2 && <Award className="w-4 h-4" />}
                              {index > 2 && <span className="text-sm font-bold">{index + 1}</span>}
                            </div>
                            <div>
                              <h5 className="font-medium">{barangay.name}</h5>
                              <p className="text-sm text-gray-500">{formatNumber(barangay.businesses)} businesses • Avg: {formatCurrency(barangay.avg_tax)}</p>
                            </div>
                          </div>
                          <div className="text-right">
                            <p className="font-bold text-lg text-gray-900">{formatCurrency(barangay.revenue)}</p>
                            <div className="flex items-center justify-end gap-2 text-sm">
                              <span className="text-gray-500">{percentage.toFixed(1)}% of total</span>
                              <div className="w-24 bg-gray-200 rounded-full h-2">
                                <div 
                                  className="h-2 rounded-full bg-blue-500"
                                  style={{ width: `${Math.min(percentage, 100)}%` }}
                                ></div>
                              </div>
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
                
                {/* Complete Barangay List */}
                {barangaysData.length > showTop && (
                  <div>
                    <h4 className="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                      <MapPin className="w-5 h-5 text-gray-600" />
                      All Barangays ({barangaysData.length})
                    </h4>
                    <div className="overflow-x-auto">
                      <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                          <tr>
                            <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                              Barangay
                            </th>
                            <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                              Businesses
                            </th>
                            <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                              Total Collection
                            </th>
                            <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                              Avg per Business
                            </th>
                            <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                              % of Total
                            </th>
                          </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                          {barangaysData.map((barangay, index) => {
                            const percentage = totalBarangayCollection > 0 
                              ? (barangay.revenue / totalBarangayCollection) * 100 
                              : 0;
                            
                            return (
                              <tr key={index} className="hover:bg-gray-50">
                                <td className="px-4 py-3 whitespace-nowrap">
                                  <div className="flex items-center">
                                    <div className="ml-2">
                                      <div className="text-sm font-medium text-gray-900">{barangay.name}</div>
                                    </div>
                                  </div>
                                </td>
                                <td className="px-4 py-3 whitespace-nowrap">
                                  <div className="text-sm text-gray-900">{formatNumber(barangay.businesses)}</div>
                                </td>
                                <td className="px-4 py-3 whitespace-nowrap">
                                  <div className="text-sm font-semibold text-gray-900">{formatCurrency(barangay.revenue)}</div>
                                </td>
                                <td className="px-4 py-3 whitespace-nowrap">
                                  <div className="text-sm text-gray-500">{formatCurrency(barangay.avg_tax)}</div>
                                </td>
                                <td className="px-4 py-3 whitespace-nowrap">
                                  <div className="flex items-center gap-2">
                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                      <div 
                                        className="h-2 rounded-full bg-green-500"
                                        style={{ width: `${Math.min(percentage, 100)}%` }}
                                      ></div>
                                    </div>
                                    <span className="text-sm text-gray-600 w-12">{percentage.toFixed(1)}%</span>
                                  </div>
                                </td>
                              </tr>
                            );
                          })}
                        </tbody>
                      </table>
                    </div>
                  </div>
                )}
              </div>
            ) : (
              <div className="text-center py-8 text-gray-400">
                <Map className="w-12 h-12 mx-auto mb-2" />
                <p>No barangay collection data available for {selectedYear}</p>
                <p className="text-sm mt-1">Business tax collection data by barangay will appear here</p>
              </div>
            )}
          </div>
        </div>

        {/* Recent Activities */}
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm">
          <div className="p-5 border-b border-gray-200">
            <div className="flex justify-between items-center">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                <Activity className="w-5 h-5 text-gray-600" />
                Recent Activities {selectedYear}
              </h3>
              <div className="flex gap-2">
                <button
                  onClick={() => setActiveTab('payments')}
                  className={`text-sm px-3 py-1 rounded transition-colors ${
                    activeTab === 'payments' 
                      ? 'bg-blue-100 text-blue-700' 
                      : 'text-gray-600 hover:text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  Payments
                </button>
                <button
                  onClick={() => setActiveTab('overdue')}
                  className={`text-sm px-3 py-1 rounded transition-colors ${
                    activeTab === 'overdue' 
                      ? 'bg-blue-100 text-blue-700' 
                      : 'text-gray-600 hover:text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  Overdue
                </button>
              </div>
            </div>
          </div>
          <div className="p-5">
            <div className="space-y-4">
              {getActivitiesForTab().slice(0, 5).map((activity, index) => (
                <div key={index} className={`flex items-center justify-between p-4 rounded-lg hover:bg-gray-50 transition-colors ${
                  activeTab === 'overdue' ? 'bg-red-50' : 'bg-gray-50'
                }`}>
                  <div className="flex items-center gap-3">
                    <div className={`p-2 rounded-lg ${
                      activeTab === 'payments' ? 'bg-green-100' :
                      'bg-red-100'
                    }`}>
                      {activeTab === 'payments' ? (
                        <CheckCircle className="w-5 h-5 text-green-600" />
                      ) : (
                        <AlertCircle className="w-5 h-5 text-red-600" />
                      )}
                    </div>
                    <div>
                      <h4 className="font-medium">{activity.business_name || activity.owner_name}</h4>
                      <p className="text-sm text-gray-500">
                        {activeTab === 'payments' && `Payment #${activity.receipt_number} • ${activity.quarter} ${activity.year}`}
                        {activeTab === 'overdue' && `${activity.days_overdue} days overdue • ${activity.quarter} ${activity.year}`}
                      </p>
                    </div>
                  </div>
                  <div className="text-right">
                    <p className={`font-bold text-lg ${
                      activeTab === 'payments' ? 'text-green-600' :
                      'text-red-600'
                    }`}>
                      {formatCurrency(activity.total_quarterly_tax || activity.amount)}
                    </p>
                    {activeTab === 'payments' && (
                      <div className="flex items-center gap-2 text-sm text-gray-500">
                        {safeParseFloat(activity.discount_amount) > 0 && (
                          <span className="text-blue-600">
                            -{formatCurrency(activity.discount_amount)}
                          </span>
                        )}
                        {safeParseFloat(activity.penalty_amount) > 0 && (
                          <span className="text-red-600">
                            +{formatCurrency(activity.penalty_amount)}
                          </span>
                        )}
                      </div>
                    )}
                    {activeTab === 'overdue' && (
                      <p className="text-sm text-red-500">
                        Due: {new Date(activity.due_date).toLocaleDateString()}
                      </p>
                    )}
                  </div>
                </div>
              ))}
              
              {getActivitiesForTab().length === 0 && (
                <div className="text-center py-8 text-gray-400">
                  <Activity className="w-12 h-12 mx-auto mb-2" />
                  <p>No {activeTab} activities available for {selectedYear}</p>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Footer Note */}
        <div className="text-center text-sm text-gray-500 pt-4 border-t border-gray-200">
          <p>Business Tax Collection Dashboard • Year: {selectedYear} • Updated {timestamp ? new Date(timestamp).toLocaleTimeString() : 'Just now'}</p>
          <p className="text-xs text-gray-400 mt-1">
            Environment: {window.location.hostname} • API: {API_BASE}
          </p>
        </div>
      </div>
    </div>
  );
}

// Colors for charts
const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8'];
const BARANGAY_COLORS = [
  '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
  '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9',
  '#F1948A', '#7DCEA0', '#F8C471', '#85C1E9', '#D2B4DE',
  '#A9CCE3', '#ABEBC6', '#F9E79F', '#D5DBDB', '#E8DAEF'
];