import React, { useState, useEffect } from "react";
import { 
  Search, Filter, Eye, Download, RefreshCw, AlertCircle, 
  Calendar, Store, Building, MapPin, User, DollarSign,
  Clock, TrendingUp, AlertTriangle, CreditCard, Phone,
  FileWarning, Send, Bell, Mail, ChevronRight, Archive
} from "lucide-react";

// Custom colors - Same as RPTDelinquent
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

export default function BusinessDelinquent() {
  const [delinquents, setDelinquents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [quarterFilter, setQuarterFilter] = useState("all");
  const [yearFilter, setYearFilter] = useState(new Date().getFullYear().toString());
  const [businessTypeFilter, setBusinessTypeFilter] = useState("all");
  const [showFilters, setShowFilters] = useState(false);

  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

  const fetchDelinquentTaxes = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(`${API_BASE}/Business/BusinessDelinquent/get_delinquent_taxes.php`, {
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
      
      if (data.success && Array.isArray(data.data)) {
        setDelinquents(data.data);
      } else {
        setDelinquents([]);
      }
      
    } catch (err) {
      console.error("Fetch error:", err);
      setError(`Failed to load delinquent business taxes: ${err.message}`);
      setDelinquents([]);
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
  const uniqueQuarters = ["Q1", "Q2", "Q3", "Q4"];
  const businessTypes = [...new Set(delinquents.map(d => d.business_nature).filter(Boolean))];

  // Filter delinquents
  const filteredDelinquents = delinquents.filter(delinquent => {
    const matchesSearch = 
      (delinquent.business_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (delinquent.owner_full_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (delinquent.trade_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (delinquent.business_barangay?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (delinquent.applicant_id?.toLowerCase() || '').includes(searchTerm.toLowerCase());
    
    const matchesQuarter = quarterFilter === "all" || delinquent.quarter === quarterFilter;
    const matchesYear = yearFilter === "all" || delinquent.year?.toString() === yearFilter;
    const matchesBusinessType = businessTypeFilter === "all" || delinquent.business_nature === businessTypeFilter;
    
    return matchesSearch && matchesQuarter && matchesYear && matchesBusinessType;
  });

  // Calculate statistics
  const calculateStats = () => {
    const stats = {
      totalAmountDue: 0,
      totalPenalties: 0,
      totalBusinesses: filteredDelinquents.length,
      totalDaysLate: 0,
      byQuarter: { Q1: 0, Q2: 0, Q3: 0, Q4: 0 },
      byBusinessType: {}
    };

    filteredDelinquents.forEach(d => {
      const amount = parseFloat(d.total_quarterly_tax || d.total_tax || 0);
      const penalty = parseFloat(d.penalty_amount || 0);
      const daysLate = parseInt(d.days_late || 0);
      
      stats.totalAmountDue += amount;
      stats.totalPenalties += penalty;
      stats.totalDaysLate += daysLate;
      
      if (d.quarter) stats.byQuarter[d.quarter] = (stats.byQuarter[d.quarter] || 0) + 1;
      if (d.business_nature) {
        stats.byBusinessType[d.business_nature] = (stats.byBusinessType[d.business_nature] || 0) + 1;
      }
    });

    stats.averageDaysLate = stats.totalBusinesses > 0 ? 
      Math.round(stats.totalDaysLate / stats.totalBusinesses) : 0;

    return stats;
  };

  const stats = calculateStats();

  // Status Info Function - Similar to RPTDelinquent
  const getStatusInfo = (daysLate, paymentStatus) => {
    if (paymentStatus === 'overdue' || daysLate > 0) {
      const severity = daysLate > 90 ? "critical" : 
                       daysLate > 60 ? "high" : 
                       daysLate > 30 ? "medium" : "low";
      
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
        label: `Overdue (${daysLate} days)`,
        severity,
        ...info,
        daysLate
      };
    }
    
    return {
      label: paymentStatus?.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) || "Unknown",
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

  const calculateTotalDue = (tax, penalty) => {
    return (parseFloat(tax || 0) + parseFloat(penalty || 0));
  };

  const handleExportReport = () => {
    const csvContent = [
      ['Business ID', 'Business Name', 'Owner Name', 'Business Type', 'Location', 'Quarter', 'Year', 'Amount Due', 'Penalty', 'Total Due', 'Days Late', 'Due Date'],
      ...filteredDelinquents.map(d => [
        d.applicant_id || d.business_id || 'N/A',
        d.business_name || 'N/A',
        d.owner_full_name || 'N/A',
        d.business_nature || 'N/A',
        `${d.business_barangay || ''}, ${d.business_city || ''}`,
        d.quarter || 'N/A',
        d.year || 'N/A',
        d.total_quarterly_tax || d.total_tax || '0',
        d.penalty_amount || '0',
        calculateTotalDue(d.total_quarterly_tax, d.penalty_amount).toFixed(2),
        d.days_late || '0',
        formatDate(d.due_date)
      ])
    ].map(row => row.join(',')).join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `overdue_business_taxes_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
  };

  const handleSendNotice = (id, businessName, ownerName, email, phone) => {
    const message = `Send delinquency notice to:\nBusiness: ${businessName}\nOwner: ${ownerName}\nEmail: ${email}\nPhone: ${phone}`;
    console.log(`Sending notice for business tax ID: ${id}`);
    alert(message);
  };

  const handleRecordPayment = (id, businessName, totalDue) => {
    const message = `Record payment for:\nBusiness: ${businessName}\nTotal Amount: ${formatCurrency(totalDue)}`;
    console.log(`Recording payment for business tax ID: ${id}`);
    alert(message);
  };

  if (loading) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
        <div className="flex items-center justify-center p-4 h-screen">
          <div className="text-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 mb-4"
                 style={{ borderColor: COLORS.primary }}></div>
            <p className="font-medium" style={{ color: COLORS.dark }}>Loading delinquent business taxes...</p>
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
      {/* Header - Same style as RPTDelinquent */}
      <div className="border-b bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>
                Delinquent Business Taxes
              </h1>
              <p className="text-sm" style={{ color: COLORS.secondary }}>
                Track and manage overdue business tax payments
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

      {/* Main Content - Same layout as RPTDelinquent */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Stats Cards - Same style */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Total Delinquent Businesses */}
          <div className="bg-white border rounded-xl p-5 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium" style={{ color: COLORS.secondary }}>Delinquent Businesses</p>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.danger }}>{stats.totalBusinesses}</p>
              </div>
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.danger}15` }}>
                <AlertTriangle className="w-5 h-5" style={{ color: COLORS.danger }} />
              </div>
            </div>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Average {stats.averageDaysLate} days late
            </div>
          </div>

          {/* Total Amount Due */}
          <div className="bg-white border rounded-xl p-5 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium" style={{ color: COLORS.secondary }}>Taxes Due</p>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.dark }}>{formatCurrency(stats.totalAmountDue)}</p>
              </div>
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.dark}08` }}>
                <DollarSign className="w-5 h-5" style={{ color: COLORS.dark }} />
              </div>
            </div>
            <div className="text-xs" style={{ color: COLORS.secondary }}>
              Base taxes only
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
              Additional charges
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
              Across all delinquent businesses
            </div>
          </div>
        </div>

        {/* Filters Section - Same collapsible design */}
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
                    placeholder="Search businesses, owners, or location..."
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
                    {uniqueQuarters.map(quarter => (
                      <option key={quarter} value={quarter}>{quarter}</option>
                    ))}
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

              {/* Business Type Filter */}
              <div>
                <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>Business Type</label>
                <div className="relative">
                  <Building className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
                           style={{ color: COLORS.secondary }} />
                  <select
                    value={businessTypeFilter}
                    onChange={(e) => setBusinessTypeFilter(e.target.value)}
                    className="w-full pl-10 pr-10 py-2.5 text-sm border rounded-lg focus:ring-2 focus:border-transparent appearance-none transition duration-200"
                    style={{ 
                      borderColor: COLORS.secondary,
                      backgroundColor: 'white'
                    }}
                  >
                    <option value="all">All Business Types</option>
                    {businessTypes.map(type => (
                      <option key={type} value={type}>{type}</option>
                    ))}
                  </select>
                </div>
              </div>
            </div>
          )}
          
          {/* Search Stats */}
          <div className="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              {filteredDelinquents.length} delinquent business{filteredDelinquents.length === 1 ? '' : 'es'} found
            </div>
            <div className="text-sm font-medium" style={{ color: COLORS.dark }}>
              Total outstanding: {formatCurrency(stats.totalAmountDue + stats.totalPenalties)}
            </div>
          </div>
        </div>

        {/* Delinquent Taxes Table - Same structure */}
        <div className="bg-white border rounded-xl overflow-hidden shadow-sm transition-all" 
             style={{ borderColor: COLORS.secondary }}>
          <div className="px-5 py-4 border-b" style={{ borderColor: COLORS.secondary, backgroundColor: `${COLORS.background}` }}>
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 className="text-sm font-semibold uppercase tracking-wider" style={{ color: COLORS.dark }}>
                  Delinquent Businesses
                </h2>
                <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                  {filteredDelinquents.length} business{filteredDelinquents.length === 1 ? '' : 'es'} with overdue taxes
                </p>
              </div>
            </div>
          </div>
          
          {filteredDelinquents.length === 0 ? (
            <div className="px-4 py-12 text-center">
              <div className="mx-auto w-12 h-12 rounded-full flex items-center justify-center mb-3" 
                   style={{ backgroundColor: `${COLORS.success}15` }}>
                <AlertTriangle className="w-6 h-6" style={{ color: COLORS.success }} />
              </div>
              <h3 className="text-sm font-medium mb-1" style={{ color: COLORS.dark }}>
                {searchTerm || quarterFilter !== "all" || yearFilter !== "all" || businessTypeFilter !== "all"
                  ? "No delinquent businesses found" 
                  : "No delinquent businesses at this time"}
              </h3>
              <p className="text-sm max-w-xs mx-auto" style={{ color: COLORS.secondary }}>
                {searchTerm || quarterFilter !== "all" || yearFilter !== "all" || businessTypeFilter !== "all"
                  ? "Try adjusting your search filters"
                  : "All business taxes are currently up to date"}
              </p>
              {(searchTerm || quarterFilter !== "all" || yearFilter !== "all" || businessTypeFilter !== "all") && (
                <button
                  onClick={() => {
                    setSearchTerm("");
                    setQuarterFilter("all");
                    setYearFilter("all");
                    setBusinessTypeFilter("all");
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
                        Business Details
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider" 
                          style={{ color: COLORS.secondary }}>
                        Owner & Contact
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
                      const statusInfo = getStatusInfo(delinquent.days_late || 0, delinquent.payment_status);
                      const StatusIcon = statusInfo.icon;
                      const totalDue = calculateTotalDue(delinquent.total_quarterly_tax, delinquent.penalty_amount);
                      
                      return (
                        <tr key={delinquent.id} className="hover:bg-gray-50 transition-colors">
                          <td className="px-5 py-4">
                            <div className="flex items-center gap-3">
                              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                                <Store className="w-4 h-4" style={{ color: COLORS.primary }} />
                              </div>
                              <div>
                                <div className="font-mono text-xs font-semibold" style={{ color: COLORS.dark }}>
                                  ID: {delinquent.applicant_id || "N/A"}
                                </div>
                                <div className="text-sm font-medium mt-0.5" style={{ color: COLORS.dark }}>
                                  {delinquent.business_name || "Business Name N/A"}
                                </div>
                                <div className="text-xs" style={{ color: COLORS.secondary }}>
                                  {delinquent.business_nature || "N/A"}
                                </div>
                                <div className="text-xs" style={{ color: COLORS.secondary }}>
                                  <MapPin className="w-3 h-3 inline mr-1" />
                                  {delinquent.business_barangay || "N/A"}
                                </div>
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="font-medium text-sm" style={{ color: COLORS.dark }}>
                              {delinquent.owner_full_name || "N/A"}
                            </div>
                            <div className="text-xs truncate max-w-[180px]" style={{ color: COLORS.secondary }}>
                              <Building className="w-3 h-3 inline mr-1" />
                              {delinquent.owner_type || "Individual"}
                            </div>
                            <div className="text-xs" style={{ color: COLORS.secondary }}>
                              <Phone className="w-3 h-3 inline mr-1" />
                              {delinquent.contact_number || "No phone"}
                            </div>
                            <div className="text-xs truncate max-w-[180px]" style={{ color: COLORS.secondary }}>
                              <Mail className="w-3 h-3 inline mr-1" />
                              {delinquent.email_address || "No email"}
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
                                  {formatCurrency(delinquent.penalty_amount)}
                                </span>
                              </div>
                              <div className="flex justify-between text-sm font-bold border-t pt-1" 
                                   style={{ borderColor: COLORS.secondary }}>
                                <span style={{ color: COLORS.dark }}>Total Due:</span>
                                <span className="font-bold" style={{ color: COLORS.dark }}>
                                  {formatCurrency(totalDue)}
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
                                onClick={() => handleSendNotice(
                                  delinquent.id,
                                  delinquent.business_name,
                                  delinquent.owner_full_name,
                                  delinquent.email_address,
                                  delinquent.contact_number
                                )}
                                className="text-sm font-medium px-3 py-1.5 rounded-lg flex items-center justify-center gap-1 transition-all"
                                style={{ 
                                  backgroundColor: COLORS.danger, 
                                  color: 'white'
                                }}
                              >
                                <Bell className="w-3 h-3" />
                                Send Notice
                              </button>
                              <button
                                onClick={() => handleRecordPayment(
                                  delinquent.id,
                                  delinquent.business_name,
                                  totalDue
                                )}
                                className="text-sm font-medium px-3 py-1.5 rounded-lg border flex items-center justify-center gap-1 transition-all"
                                style={{ 
                                  borderColor: COLORS.primary,
                                  color: COLORS.primary,
                                  backgroundColor: 'white'
                                }}
                              >
                                <CreditCard className="w-3 h-3" />
                                Record Payment
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
                    Showing <span className="font-semibold" style={{ color: COLORS.dark }}>{filteredDelinquents.length}</span> delinquent business{filteredDelinquents.length === 1 ? '' : 'es'}
                  </div>
                  <div className="text-sm font-medium" style={{ color: COLORS.dark }}>
                    Total Outstanding: {formatCurrency(stats.totalAmountDue + stats.totalPenalties)}
                  </div>
                </div>
              </div>
            </>
          )}
        </div>

        {/* Footer - Same style */}
        <div className="mt-8 pt-6 border-t text-center text-sm" 
             style={{ borderColor: COLORS.secondary, color: COLORS.secondary }}>
          <p className="font-medium" style={{ color: COLORS.dark }}>Local Government Unit - Business Tax Management</p>
          <p className="mt-1">Business Tax Collection System v2.0</p>
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