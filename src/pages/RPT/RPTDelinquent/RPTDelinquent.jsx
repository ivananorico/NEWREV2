import React, { useState, useEffect } from "react";
import { 
  Search, Filter, Eye, Download, RefreshCw, AlertCircle, 
  Calendar, FileText, Home, MapPin, User, Hash, DollarSign,
  Clock, TrendingUp, AlertTriangle, Percent, CreditCard,
  FileWarning, Building, Landmark, CheckCircle, Ban, Archive,
  Send, Bell, MoreVertical, ChevronDown, ExternalLink, Mail
} from "lucide-react";

// Custom colors
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

export default function RPTDelinquent() {
  const [delinquents, setDelinquents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [quarterFilter, setQuarterFilter] = useState("all");
  const [yearFilter, setYearFilter] = useState(new Date().getFullYear().toString());
  const [showFilters, setShowFilters] = useState(false);

  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

  const fetchDelinquentTaxes = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(`${API_BASE}/RPT/RPTDelinquent/get_delinquent_taxes.php`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      });
      
      if (!response.ok) {
        throw new Error(`Server error: ${response.status}`);
      }
      
      const data = await response.json();
      
      let delinquentsData = [];
      
      if (data.success && Array.isArray(data.data)) {
        delinquentsData = data.data;
      } else if (data.status === "success" && Array.isArray(data.delinquents)) {
        delinquentsData = data.delinquents;
      } else if (Array.isArray(data)) {
        delinquentsData = data;
      } else {
        throw new Error("Unexpected response format");
      }
      
      setDelinquents(delinquentsData);
      
    } catch (err) {
      console.error("Fetch error:", err);
      setError(`Failed to load delinquent taxes: ${err.message}`);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDelinquentTaxes();
  }, []);

  // Generate years for filter
  const currentYear = new Date().getFullYear();
  const years = Array.from({ length: 6 }, (_, i) => (currentYear - i).toString());

  // Filter delinquents
  const filteredDelinquents = delinquents.filter(delinquent => {
    const matchesSearch = 
      (delinquent.owner_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (delinquent.reference_number?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (delinquent.tdn?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (delinquent.lot_location?.toLowerCase() || '').includes(searchTerm.toLowerCase());
    
    const matchesStatus = statusFilter === "all" || delinquent.payment_status === statusFilter;
    const matchesQuarter = quarterFilter === "all" || delinquent.quarter === quarterFilter;
    const matchesYear = yearFilter === "all" || delinquent.year?.toString() === yearFilter;
    
    return matchesSearch && matchesStatus && matchesQuarter && matchesYear;
  });

  // Calculate statistics
  const calculateStats = () => {
    const stats = {
      totalAmountDue: 0,
      totalPenalties: 0,
      totalProperties: filteredDelinquents.length,
      byQuarter: { Q1: 0, Q2: 0, Q3: 0, Q4: 0 },
      byStatus: { overdue: 0, pending: 0 },
      totalDaysLate: 0
    };

    filteredDelinquents.forEach(d => {
      const amount = parseFloat(d.total_quarterly_tax || d.total_amount || 0);
      const penalty = parseFloat(d.penalty_amount || 0);
      const daysLate = parseInt(d.days_late || 0);
      
      stats.totalAmountDue += amount;
      stats.totalPenalties += penalty;
      stats.totalDaysLate += daysLate;
      
      if (d.quarter) stats.byQuarter[d.quarter] = (stats.byQuarter[d.quarter] || 0) + 1;
      
      if (d.payment_status === 'pending') {
        stats.byStatus.pending++;
      } else if (d.payment_status === 'overdue') {
        stats.byStatus.overdue++;
      }
    });

    stats.averageDaysLate = stats.totalProperties > 0 ? Math.round(stats.totalDaysLate / stats.totalProperties) : 0;

    return stats;
  };

  const stats = calculateStats();

  const getStatusInfo = (status, dueDate, daysLate) => {
    const lateDays = daysLate || 0;
    
    if (status === 'overdue' || lateDays > 0) {
      const severity = lateDays > 90 ? "critical" : 
                       lateDays > 60 ? "high" : 
                       lateDays > 30 ? "medium" : "low";
      
      const colorMap = {
        critical: { 
          text: "text-red-800", 
          bg: "bg-red-50", 
          border: "border-red-200",
          icon: AlertTriangle
        },
        high: { 
          text: "text-orange-800", 
          bg: "bg-orange-50", 
          border: "border-orange-200",
          icon: AlertTriangle
        },
        medium: { 
          text: "text-yellow-800", 
          bg: "bg-yellow-50", 
          border: "border-yellow-200",
          icon: Clock
        },
        low: { 
          text: "text-blue-800", 
          bg: "bg-blue-50", 
          border: "border-blue-200",
          icon: Clock
        }
      };
      
      const info = colorMap[severity] || colorMap.low;
      
      return {
        label: `Overdue (${lateDays} days)`,
        severity,
        ...info,
        daysLate: lateDays
      };
    }
    
    const statusMap = {
      pending: { 
        label: "Pending Payment", 
        text: "text-blue-800",
        bg: "bg-blue-50",
        border: "border-blue-200",
        icon: Clock,
        severity: "pending"
      },
      paid: { 
        label: "Paid", 
        text: "text-green-800",
        bg: "bg-green-50",
        border: "border-green-200",
        icon: CheckCircle,
        severity: "paid"
      }
    };
    
    return statusMap[status] || { 
      label: status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()), 
      text: "text-gray-800",
      bg: "bg-gray-50",
      border: "border-gray-200",
      icon: FileWarning,
      severity: "unknown"
    };
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2
    }).format(amount || 0);
  };

  const formatDate = (dateString) => {
    if (!dateString) return "N/A";
    try {
      return new Date(dateString).toLocaleDateString("en-PH", {
        year: "numeric",
        month: "short",
        day: "numeric",
      });
    } catch (err) {
      return dateString;
    }
  };

  const handleExportReport = () => {
    const csvContent = [
      ['Reference No', 'TDN', 'Owner Name', 'Property Location', 'Quarter', 'Year', 'Amount Due', 'Penalty', 'Total Due', 'Status', 'Due Date', 'Days Late'],
      ...filteredDelinquents.map(d => [
        d.reference_number || 'N/A',
        d.tdn || 'N/A',
        d.owner_name || 'N/A',
        d.lot_location || 'N/A',
        d.quarter || 'N/A',
        d.year || 'N/A',
        d.total_quarterly_tax || d.total_amount || '0',
        d.penalty_amount || '0',
        (parseFloat(d.total_quarterly_tax || 0) + parseFloat(d.penalty_amount || 0)).toFixed(2),
        d.payment_status || 'pending',
        formatDate(d.due_date),
        d.days_late || '0'
      ])
    ].map(row => row.join(',')).join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `delinquent_taxes_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
  };

  const handleSendNotice = (delinquent) => {
    // Implement send notice functionality
    console.log("Sending notice to:", delinquent.owner_name);
    // You can integrate with email API here
    alert(`Notice sent to ${delinquent.owner_name || "property owner"}`);
  };

  if (loading) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
        <div className="flex items-center justify-center p-4 h-screen">
          <div className="text-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 mb-4"
                 style={{ borderColor: COLORS.primary }}></div>
            <p className="font-medium" style={{ color: COLORS.dark }}>Loading delinquent taxes...</p>
            <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>Fetching data from server</p>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
        <div className="flex items-center justify-center p-4 h-screen">
          <div className="bg-white rounded-lg border p-6 max-w-md w-full" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="text-center">
              <div className="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4" 
                   style={{ backgroundColor: `${COLORS.danger}15` }}>
                <AlertCircle className="w-6 h-6" style={{ color: COLORS.danger }} />
              </div>
              <h2 className="text-lg font-semibold mb-2" style={{ color: COLORS.dark }}>Connection Error</h2>
              <p className="text-sm mb-4" style={{ color: COLORS.secondary }}>{error}</p>
              <button
                onClick={fetchDelinquentTaxes}
                className="w-full px-4 py-2.5 rounded-md font-medium text-white transition duration-200"
                style={{ backgroundColor: COLORS.primary }}
              >
                <RefreshCw className="w-4 h-4 inline-block mr-2" />
                Retry Connection
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
      {/* Header */}
      <div className="border-b bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>
                Delinquent Property Taxes
              </h1>
              <p className="text-sm" style={{ color: COLORS.secondary }}>
                Track and manage overdue property tax payments
              </p>
            </div>
            
            <div className="flex flex-wrap gap-3 items-center">
              <button
                onClick={handleExportReport}
                className="flex items-center gap-2 px-4 py-2 border rounded-lg text-gray-700 transition-all"
                style={{ 
                  borderColor: COLORS.secondary,
                  backgroundColor: 'white'
                }}
              >
                <Download className="w-4 h-4" />
                Export Report
              </button>
              <button
                onClick={fetchDelinquentTaxes}
                className="flex items-center gap-2 px-4 py-2 border rounded-lg text-gray-700 transition-all"
                style={{ 
                  borderColor: COLORS.secondary,
                  backgroundColor: 'white'
                }}
              >
                <RefreshCw className="w-4 h-4" />
                Refresh
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Stats Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Total Delinquent Properties */}
          <div className="bg-white border rounded-xl p-5 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium" style={{ color: COLORS.secondary }}>Delinquent Properties</p>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.danger }}>{stats.totalProperties}</p>
              </div>
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.danger}15` }}>
                <FileWarning className="w-5 h-5" style={{ color: COLORS.danger }} />
              </div>
            </div>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              {stats.byStatus.overdue} overdue, {stats.byStatus.pending} pending
            </div>
          </div>

          {/* Total Amount Due */}
          <div className="bg-white border rounded-xl p-5 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium" style={{ color: COLORS.secondary }}>Total Amount Due</p>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.dark }}>{formatCurrency(stats.totalAmountDue)}</p>
              </div>
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.dark}08` }}>
                <DollarSign className="w-5 h-5" style={{ color: COLORS.dark }} />
              </div>
            </div>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Excluding penalties and interest
            </div>
          </div>

          {/* Total Penalties */}
          <div className="bg-white border rounded-xl p-5 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium" style={{ color: COLORS.secondary }}>Accrued Penalties</p>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.warning }}>{formatCurrency(stats.totalPenalties)}</p>
              </div>
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.warning}15` }}>
                <TrendingUp className="w-5 h-5" style={{ color: COLORS.warning }} />
              </div>
            </div>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              2% monthly penalty rate
            </div>
          </div>

          {/* Average Days Late */}
          <div className="bg-white border rounded-xl p-5 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium" style={{ color: COLORS.secondary }}>Average Days Late</p>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.info }}>{stats.averageDaysLate} days</p>
              </div>
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.info}15` }}>
                <Calendar className="w-5 h-5" style={{ color: COLORS.info }} />
              </div>
            </div>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Across all delinquent properties
            </div>
          </div>
        </div>

        {/* Filters Section */}
        <div className="bg-white border rounded-xl p-5 transition-all" style={{ borderColor: COLORS.secondary }}>
          <div className="flex justify-between items-center mb-4">
            <h3 className="font-semibold" style={{ color: COLORS.dark }}>Filter Delinquent Taxes</h3>
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
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              {/* Search */}
              <div>
                <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Search</label>
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                         style={{ color: COLORS.secondary }} />
                  <input
                    type="text"
                    placeholder="Search by owner, TDN, or location..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="w-full pl-10 pr-4 py-2.5 text-sm border rounded-lg focus:ring-2 focus:border-transparent transition duration-200"
                    style={{ 
                      borderColor: COLORS.secondary,
                      backgroundColor: 'white'
                    }}
                  />
                </div>
              </div>

              {/* Status Filter */}
              <div>
                <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Payment Status</label>
                <div className="relative">
                  <Filter className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                         style={{ color: COLORS.secondary }} />
                  <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="w-full pl-10 pr-10 py-2.5 text-sm border rounded-lg focus:ring-2 focus:border-transparent appearance-none transition duration-200"
                    style={{ 
                      borderColor: COLORS.secondary,
                      backgroundColor: 'white'
                    }}
                  >
                    <option value="all">All Status</option>
                    <option value="pending">Pending Payment</option>
                    <option value="overdue">Overdue</option>
                  </select>
                </div>
              </div>

              {/* Quarter Filter */}
              <div>
                <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Quarter</label>
                <div className="relative">
                  <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                           style={{ color: COLORS.secondary }} />
                  <select
                    value={quarterFilter}
                    onChange={(e) => setQuarterFilter(e.target.value)}
                    className="w-full pl-10 pr-10 py-2.5 text-sm border rounded-lg focus:ring-2 focus:border-transparent appearance-none transition duration-200"
                    style={{ 
                      borderColor: COLORS.secondary,
                      backgroundColor: 'white'
                    }}
                  >
                    <option value="all">All Quarters</option>
                    <option value="Q1">Q1</option>
                    <option value="Q2">Q2</option>
                    <option value="Q3">Q3</option>
                    <option value="Q4">Q4</option>
                  </select>
                </div>
              </div>

              {/* Year Filter */}
              <div>
                <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Year</label>
                <div className="relative">
                  <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                           style={{ color: COLORS.secondary }} />
                  <select
                    value={yearFilter}
                    onChange={(e) => setYearFilter(e.target.value)}
                    className="w-full pl-10 pr-10 py-2.5 text-sm border rounded-lg focus:ring-2 focus:border-transparent appearance-none transition duration-200"
                    style={{ 
                      borderColor: COLORS.secondary,
                      backgroundColor: 'white'
                    }}
                  >
                    <option value="all">All Years</option>
                    {years.map(year => (
                      <option key={year} value={year}>{year}</option>
                    ))}
                  </select>
                </div>
              </div>
            </div>
          )}
          
          {/* Search Stats */}
          <div className="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              {filteredDelinquents.length} delinquent propert{filteredDelinquents.length === 1 ? 'y' : 'ies'} found
            </div>
            <div className="text-sm font-medium" style={{ color: COLORS.dark }}>
              Total due: {formatCurrency(stats.totalAmountDue + stats.totalPenalties)}
            </div>
          </div>
        </div>

        {/* Delinquent Taxes Table */}
        <div className="bg-white border rounded-xl overflow-hidden shadow-sm transition-all" 
             style={{ borderColor: COLORS.secondary }}>
          <div className="px-5 py-4 border-b" style={{ borderColor: COLORS.secondary, backgroundColor: `${COLORS.background}` }}>
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 className="text-sm font-semibold uppercase tracking-wider" style={{ color: COLORS.dark }}>
                  Delinquent Properties
                </h2>
                <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                  {filteredDelinquents.length} propert{filteredDelinquents.length === 1 ? 'y' : 'ies'} with overdue taxes
                </p>
              </div>
            </div>
          </div>
          
          {filteredDelinquents.length === 0 ? (
            <div className="px-4 py-12 text-center">
              <div className="mx-auto w-12 h-12 rounded-full flex items-center justify-center mb-3" 
                   style={{ backgroundColor: `${COLORS.success}15` }}>
                <CheckCircle className="w-6 h-6" style={{ color: COLORS.success }} />
              </div>
              <h3 className="text-sm font-medium mb-1" style={{ color: COLORS.dark }}>
                {searchTerm || statusFilter !== "all" || quarterFilter !== "all" || yearFilter !== "all"
                  ? "No delinquent properties found" 
                  : "No delinquent properties at this time"}
              </h3>
              <p className="text-sm max-w-xs mx-auto" style={{ color: COLORS.secondary }}>
                {searchTerm || statusFilter !== "all" || quarterFilter !== "all" || yearFilter !== "all"
                  ? "Try adjusting your search filters"
                  : "All taxes are currently up to date"}
              </p>
              {(searchTerm || statusFilter !== "all" || quarterFilter !== "all" || yearFilter !== "all") && (
                <button
                  onClick={() => {
                    setSearchTerm("");
                    setStatusFilter("all");
                    setQuarterFilter("all");
                    setYearFilter("all");
                  }}
                  className="mt-4 text-sm font-medium transition-all"
                  style={{ color: COLORS.primary }}
                >
                  Clear all filters
                </button>
              )}
            </div>
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y" style={{ borderColor: COLORS.secondary }}>
                  <thead style={{ backgroundColor: `${COLORS.background}` }}>
                    <tr>
                      <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider" 
                          style={{ color: COLORS.secondary }}>
                        Property Details
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider" 
                          style={{ color: COLORS.secondary }}>
                        Owner
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider" 
                          style={{ color: COLORS.secondary }}>
                        Tax Period
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider" 
                          style={{ color: COLORS.secondary }}>
                        Amount Details
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider" 
                          style={{ color: COLORS.secondary }}>
                        Status & Due Date
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider" 
                          style={{ color: COLORS.secondary }}>
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y" style={{ borderColor: COLORS.secondary }}>
                    {filteredDelinquents.map((delinquent) => {
                      const statusInfo = getStatusInfo(
                        delinquent.payment_status, 
                        delinquent.due_date, 
                        delinquent.days_late
                      );
                      const StatusIcon = statusInfo.icon;
                      
                      const penaltyAmount = parseFloat(delinquent.penalty_amount || 0);
                      const totalAmount = parseFloat(delinquent.total_quarterly_tax || 0) + penaltyAmount;
                      
                      return (
                        <tr key={delinquent.id} className="hover:bg-gray-50 transition-colors">
                          <td className="px-5 py-4">
                            <div className="flex items-center gap-3">
                              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                                <Building className="w-4 h-4" style={{ color: COLORS.primary }} />
                              </div>
                              <div>
                                <div className="font-mono text-xs font-semibold" style={{ color: COLORS.dark }}>
                                  TDN: {delinquent.tdn || "N/A"}
                                </div>
                                <div className="text-sm font-medium mt-0.5" style={{ color: COLORS.dark }}>
                                  {delinquent.lot_location || "Location not specified"}
                                </div>
                                <div className="text-xs" style={{ color: COLORS.secondary }}>
                                  Brgy. {delinquent.barangay || "N/A"}
                                </div>
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="font-medium text-sm" style={{ color: COLORS.dark }}>
                              {delinquent.owner_name || "N/A"}
                            </div>
                            <div className="text-xs truncate max-w-[180px]" style={{ color: COLORS.secondary }}>
                              {delinquent.email || "No email provided"}
                            </div>
                            <div className="text-xs" style={{ color: COLORS.secondary }}>
                              {delinquent.phone || "No phone"}
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="text-center">
                              <div className="font-bold text-lg" style={{ color: COLORS.dark }}>
                                {delinquent.quarter || "N/A"}
                              </div>
                              <div className="text-sm" style={{ color: COLORS.secondary }}>
                                {delinquent.year || "N/A"}
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="space-y-1">
                              <div className="flex justify-between text-sm">
                                <span style={{ color: COLORS.secondary }}>Base Tax:</span>
                                <span className="font-medium" style={{ color: COLORS.dark }}>
                                  {formatCurrency(delinquent.total_quarterly_tax)}
                                </span>
                              </div>
                              <div className="flex justify-between text-sm">
                                <span style={{ color: COLORS.danger }}>Penalty:</span>
                                <span className="font-medium" style={{ color: COLORS.danger }}>
                                  {formatCurrency(penaltyAmount)}
                                </span>
                              </div>
                              <div className="flex justify-between text-sm font-bold border-t pt-1" 
                                   style={{ borderColor: COLORS.secondary }}>
                                <span style={{ color: COLORS.dark }}>Total Due:</span>
                                <span className="font-bold" style={{ color: COLORS.dark }}>
                                  {formatCurrency(totalAmount)}
                                </span>
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="flex items-center gap-3">
                              <div className={`p-2 rounded-lg ${statusInfo.bg}`}>
                                <StatusIcon className={`w-4 h-4 ${statusInfo.text}`} />
                              </div>
                              <div>
                                <span className={`text-xs font-medium px-3 py-1.5 rounded-full ${statusInfo.bg} ${statusInfo.text} border ${statusInfo.border}`}>
                                  {statusInfo.label}
                                </span>
                                <div className="text-xs mt-1" style={{ color: COLORS.secondary }}>
                                  Due: {formatDate(delinquent.due_date)}
                                </div>
                                {statusInfo.daysLate > 0 && (
                                  <div className="text-xs mt-0.5" style={{ color: COLORS.danger }}>
                                    {statusInfo.daysLate} days late
                                  </div>
                                )}
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="flex flex-col gap-2">
                              <button
                                onClick={() => console.log("View details", delinquent.id)}
                                className="text-sm font-medium px-3 py-1.5 rounded-lg flex items-center justify-center gap-1 transition-all"
                                style={{ 
                                  backgroundColor: COLORS.primary, 
                                  color: 'white'
                                }}
                              >
                                <Eye className="w-3 h-3" />
                                Details
                              </button>
                              <button
                                onClick={() => handleSendNotice(delinquent)}
                                className="text-sm font-medium px-3 py-1.5 rounded-lg border flex items-center justify-center gap-1 transition-all"
                                style={{ 
                                  borderColor: COLORS.danger,
                                  color: COLORS.danger,
                                  backgroundColor: 'white'
                                }}
                              >
                                <Send className="w-3 h-3" />
                                Send Notice
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
              
              {/* Table Footer */}
              <div className="px-5 py-4 border-t" 
                   style={{ borderColor: COLORS.secondary, backgroundColor: `${COLORS.background}` }}>
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div className="text-sm" style={{ color: COLORS.secondary }}>
                    Showing <span className="font-semibold" style={{ color: COLORS.dark }}>{filteredDelinquents.length}</span> delinquent propert{filteredDelinquents.length === 1 ? 'y' : 'ies'}
                  </div>
                  <div className="text-sm font-medium" style={{ color: COLORS.dark }}>
                    Total Outstanding: {formatCurrency(stats.totalAmountDue + stats.totalPenalties)}
                  </div>
                </div>
              </div>
            </>
          )}
        </div>

        {/* Footer */}
        <div className="mt-8 pt-6 border-t text-center text-sm" 
             style={{ borderColor: COLORS.secondary, color: COLORS.secondary }}>
          <p className="font-medium" style={{ color: COLORS.dark }}>Local Government Unit - Delinquent Tax Management</p>
          <p className="mt-1">Real Property Tax Collection System v2.0</p>
          <p className="mt-1 text-xs">
            Last updated: {new Date().toLocaleDateString('en-PH', { 
              year: 'numeric', 
              month: 'long', 
              day: 'numeric',
              hour: '2-digit',
              minute: '2-digit'
            })}
          </p>
        </div>
      </div>
    </div>
  );
}