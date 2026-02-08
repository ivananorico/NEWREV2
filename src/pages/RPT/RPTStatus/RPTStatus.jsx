import React, { useState, useEffect } from "react";
import { 
  Search, Filter, Eye, Download, RefreshCw, CheckCircle, Building, 
  User, Calendar, DollarSign, Clock, AlertCircle, Home, Phone, Mail, 
  MapPin, TrendingUp, Wallet, Percent, ChevronRight, BarChart3, 
  Database, Shield, CheckCircle2, Clock3, FileWarning, Archive, 
  Landmark, Users, TrendingDown, ArrowUpRight, ArrowDownRight
} from "lucide-react";
import { useNavigate } from "react-router-dom";

// Custom colors from the dashboard
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

// Property Type Badge Component
const PropertyTypeBadge = ({ propertyType }) => {
  if (!propertyType) return <span className="text-sm" style={{ color: COLORS.secondary }}>Not specified</span>;
  
  const colors = {
    'Residential': { bg: `${COLORS.success}15`, text: COLORS.success, border: `${COLORS.success}30` },
    'Commercial': { bg: `${COLORS.primary}15`, text: COLORS.primary, border: `${COLORS.primary}30` },
    'Industrial': { bg: `${COLORS.warning}15`, text: COLORS.warning, border: `${COLORS.warning}30` },
    'Agricultural': { bg: `${COLORS.info}15`, text: COLORS.info, border: `${COLORS.info}30` }
  };
  
  const colorStyle = colors[propertyType] || { 
    bg: `${COLORS.secondary}15`, 
    text: COLORS.secondary, 
    border: `${COLORS.secondary}30` 
  };
  
  return (
    <span 
      className="inline-flex items-center px-2 py-1 rounded text-xs font-medium border"
      style={{ 
        backgroundColor: colorStyle.bg,
        color: colorStyle.text,
        borderColor: colorStyle.border
      }}
    >
      {propertyType}
    </span>
  );
};

// Payment Status Badge Component
const PaymentStatusBadge = ({ status }) => {
  const getStatusInfo = () => {
    switch(status) {
      case 'paid':
        return {
          text: 'Paid',
          bgColor: `${COLORS.success}15`,
          textColor: COLORS.success,
          borderColor: `${COLORS.success}30`,
          icon: <CheckCircle className="w-3 h-3 mr-1" />
        };
      case 'overdue':
        return {
          text: 'Delinquent',
          bgColor: `${COLORS.danger}15`,
          textColor: COLORS.danger,
          borderColor: `${COLORS.danger}30`,
          icon: <AlertCircle className="w-3 h-3 mr-1" />
        };
      case 'next-quarter':
        return {
          text: 'Next Quarter',
          bgColor: `${COLORS.info}15`,
          textColor: COLORS.info,
          borderColor: `${COLORS.info}30`,
          icon: <Clock className="w-3 h-3 mr-1" />
        };
      case 'pending':
        return {
          text: 'Current Quarter',
          bgColor: `${COLORS.warning}15`,
          textColor: COLORS.warning,
          borderColor: `${COLORS.warning}30`,
          icon: <Clock className="w-3 h-3 mr-1" />
        };
      default:
        return {
          text: 'Unknown',
          bgColor: `${COLORS.secondary}15`,
          textColor: COLORS.secondary,
          borderColor: `${COLORS.secondary}30`,
          icon: null
        };
    }
  };

  const statusInfo = getStatusInfo();
  
  return (
    <span 
      className="inline-flex items-center px-2 py-1 rounded text-xs font-medium border"
      style={{ 
        backgroundColor: statusInfo.bgColor,
        color: statusInfo.textColor,
        borderColor: statusInfo.borderColor
      }}
    >
      {statusInfo.icon}
      {statusInfo.text}
    </span>
  );
};

export default function RPTStatus() {
  const [approvedProperties, setApprovedProperties] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [propertyTypeFilter, setPropertyTypeFilter] = useState("all");
  const [paymentFilter, setPaymentFilter] = useState("all");
  const navigate = useNavigate();

  // API Configuration
  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

  const API_PATH = "/RPT/RPTStatus";

  // Define getPaymentStatus function
  const getPaymentStatus = (createdDate) => {
    if (!createdDate) return 'pending';
    
    const now = new Date();
    const created = new Date(createdDate);
    const currentMonth = now.getMonth();
    const currentQuarter = Math.floor(currentMonth / 3) + 1;
    
    // Check if property was created this quarter
    const createdMonth = created.getMonth();
    const createdQuarter = Math.floor(createdMonth / 3) + 1;
    const currentYear = now.getFullYear();
    const createdYear = created.getFullYear();
    
    // If created this quarter, show "Next Quarter"
    if (createdYear === currentYear && createdQuarter === currentQuarter) {
      return 'next-quarter';
    }
    
    // Simple logic: Random status for demo
    const statuses = ['paid', 'pending', 'overdue'];
    const randomStatus = statuses[Math.floor(Math.random() * statuses.length)];
    
    return randomStatus;
  };

  // Get building status text
  const getBuildingStatus = (property) => {
    if (property.has_building === 'yes' && property.building_count > 0) {
      return `${property.building_count} building${property.building_count !== 1 ? 's' : ''}`;
    } else if (property.has_building === 'yes') {
      return 'Building pending';
    } else {
      return 'Vacant';
    }
  };

  const fetchApprovedProperties = async () => {
    try {
      setLoading(true);
      setError(null);
      
      // Fetch approved properties
      const response = await fetch(`${API_BASE}${API_PATH}/get_approved_properties.php`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      });
      
      if (!response.ok) {
        throw new Error(`Server error: ${response.status}`);
      }
      
      const text = await response.text();
      
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        throw new Error("Invalid JSON response from server");
      }
      
      const isSuccess = (
        data.success === true || 
        data.success === "true" || 
        data.status === "success"
      );
      
      if (isSuccess) {
        let propertiesData = [];
        
        if (Array.isArray(data)) {
          propertiesData = data;
        } else if (data.success === true || data.success === "true") {
          propertiesData = data.data || [];
        } else if (data.status === "success") {
          propertiesData = data.data || [];
        } else if (Array.isArray(data.data)) {
          propertiesData = data.data;
        }
        
        setApprovedProperties(propertiesData);
      } else {
        throw new Error(data.error || data.message || "Failed to load approved properties");
      }
    } catch (err) {
      setError(`Failed to load approved properties: ${err.message}`);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchApprovedProperties();
  }, []);

  // Calculate totals
  const totalAnnualRevenue = approvedProperties.reduce((sum, p) => sum + (parseFloat(p.total_annual_tax) || 0), 0);
  const totalCollected = approvedProperties
    .filter(p => getPaymentStatus(p.created_at) === 'paid')
    .reduce((sum, p) => sum + (parseFloat(p.total_annual_tax) || 0), 0);
  const collectionRate = totalAnnualRevenue > 0 ? Math.round((totalCollected / totalAnnualRevenue) * 100) : 0;
  const pendingPayments = totalAnnualRevenue - totalCollected;

  // Statistics
  const totalAnnualTax = approvedProperties.reduce((sum, p) => sum + (parseFloat(p.total_annual_tax) || 0), 0);
  const propertiesWithBuildings = approvedProperties.filter(p => p.has_building === 'yes' && (p.building_count || 0) > 0).length;
  const vacantProperties = approvedProperties.filter(p => p.has_building !== 'yes').length;
  
  // Payment status statistics
  const paymentStats = approvedProperties.reduce((stats, property) => {
    const status = getPaymentStatus(property.created_at);
    stats[status] = (stats[status] || 0) + 1;
    return stats;
  }, {});

  // Filter properties
  const filteredProperties = approvedProperties.filter(property => {
    const matchesSearch = 
      (property.owner_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.reference_number?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.lot_location?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.barangay?.toLowerCase() || '').includes(searchTerm.toLowerCase());
    
    const propertyType = property.property_type || '';
    const matchesType = propertyTypeFilter === "all" || 
      propertyType.toLowerCase() === propertyTypeFilter.toLowerCase();
    
    const paymentStatus = getPaymentStatus(property.created_at);
    const matchesPayment = paymentFilter === "all" || 
      paymentStatus === paymentFilter;
    
    return matchesSearch && matchesType && matchesPayment;
  });

  // Get unique property types for filter dropdown
  const propertyTypes = [...new Set(
    approvedProperties.map(p => p.property_type).filter(Boolean)
  )].sort();

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

  const formatCurrency = (amount) => {
    if (!amount || isNaN(amount)) return '₱0';
    const num = parseFloat(amount);
    
    if (num >= 1000000000) return `₱${(num / 1000000000).toFixed(2)}B`;
    if (num >= 1000000) return `₱${(num / 1000000).toFixed(2)}M`;
    if (num >= 1000) return `₱${(num / 1000).toFixed(2)}K`;
    return `₱${num.toFixed(2)}`;
  };

  const formatNumber = (num) => {
    if (!num || isNaN(num)) return '0';
    return new Intl.NumberFormat('en-PH').format(parseFloat(num));
  };

  const handleViewDetails = (id) => {
    navigate(`/rpt/rptstatusinfo/${id}`);
  };

  const handleExport = () => {
    if (filteredProperties.length === 0) {
      alert("No data to export");
      return;
    }

    const headers = [
      "Reference Number",
      "Owner Name",
      "Property Type",
      "Location",
      "Barangay",
      "Total Annual Tax",
      "Payment Status",
      "Building Status",
      "Date Approved"
    ];

    const csvData = filteredProperties.map(property => {
      const paymentStatus = getPaymentStatus(property.created_at);
      const buildingStatus = getBuildingStatus(property);
      
      return [
        property.reference_number || "",
        property.owner_name || "",
        property.property_type || "",
        property.lot_location || "",
        property.barangay || "",
        property.total_annual_tax || "0",
        paymentStatus,
        buildingStatus,
        property.created_at ? new Date(property.created_at).toLocaleDateString() : ""
      ];
    });

    const csvContent = [
      headers.join(","),
      ...csvData.map(row => row.map(cell => `"${cell}"`).join(","))
    ].join("\n");

    const blob = new Blob([csvContent], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `citizen-properties-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
  };

  if (loading) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
        <div className="flex flex-col justify-center items-center h-screen bg-white">
          <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 mb-4" style={{ borderColor: COLORS.primary }}></div>
          <p style={{ color: COLORS.dark }}>Loading Property Status...</p>
          <p className="text-sm mt-2" style={{ color: COLORS.secondary }}>Fetching approved properties data</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
        <div className="max-w-4xl mx-auto p-6 bg-white">
          <div className="bg-red-50 border border-red-200 rounded-xl p-6">
            <div className="flex items-center space-x-3 mb-4">
              <AlertCircle className="w-8 h-8" style={{ color: COLORS.danger }} />
              <div>
                <h3 className="text-lg font-semibold" style={{ color: COLORS.danger }}>Error Loading Properties</h3>
                <p style={{ color: COLORS.danger }}>{error}</p>
              </div>
            </div>
            <button 
              onClick={fetchApprovedProperties}
              className="px-4 py-2 rounded-lg flex items-center gap-2 transition-all"
              style={{ backgroundColor: COLORS.primary, color: 'white' }}
            >
              <RefreshCw className="w-4 h-4" />
              Try Again
            </button>
          </div>
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
                Property Tax Status Registry
              </h1>
              <div className="flex items-center gap-3 text-sm" style={{ color: COLORS.secondary }}>
                <div className="flex items-center gap-1">
                  <Calendar className="w-4 h-4" />
                  <span>Approved Properties • {new Date().toLocaleDateString('en-PH')}</span>
                </div>
              </div>
            </div>
            
            <div className="flex flex-wrap gap-3 items-center">
              <button
                onClick={fetchApprovedProperties}
                className="flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all"
                style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
              >
                <RefreshCw className="w-4 h-4" />
                Refresh
              </button>
              
              <button
                onClick={handleExport}
                className="flex items-center gap-2 px-4 py-2 rounded-lg transition-all"
                style={{ backgroundColor: COLORS.primary, color: 'white' }}
              >
                <Download className="w-4 h-4" />
                Export
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Compact Statistics Cards - Horizontal Layout */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
          {/* Total Properties */}
          <div className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                <Home className="w-5 h-5" style={{ color: COLORS.primary }} />
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-xs font-semibold uppercase tracking-wider truncate" style={{ color: COLORS.secondary }}>
                  Properties
                </h3>
                <p className="text-xl font-bold truncate" style={{ color: COLORS.dark }}>{formatNumber(approvedProperties.length)}</p>
                <div className="flex items-center gap-2 mt-1">
                  <div className="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full rounded-full" 
                      style={{ 
                        width: `${approvedProperties.length > 0 ? 100 : 0}%`,
                        backgroundColor: COLORS.primary 
                      }}
                    />
                  </div>
                  <span className="text-xs font-medium whitespace-nowrap" style={{ color: COLORS.primary }}>
                    Total
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Properties with Buildings */}
          <div className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.info}15` }}>
                <Building className="w-5 h-5" style={{ color: COLORS.info }} />
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-xs font-semibold uppercase tracking-wider truncate" style={{ color: COLORS.secondary }}>
                  With Buildings
                </h3>
                <p className="text-xl font-bold truncate" style={{ color: COLORS.dark }}>{formatNumber(propertiesWithBuildings)}</p>
                <div className="flex items-center gap-2 mt-1">
                  <div className="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full rounded-full" 
                      style={{ 
                        width: `${approvedProperties.length > 0 ? (propertiesWithBuildings / approvedProperties.length) * 100 : 0}%`,
                        backgroundColor: COLORS.info 
                      }}
                    />
                  </div>
                  <span className="text-xs font-medium whitespace-nowrap" style={{ color: COLORS.info }}>
                    {approvedProperties.length > 0 ? Math.round((propertiesWithBuildings / approvedProperties.length) * 100) : 0}%
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Vacant Properties */}
          <div className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.warning}15` }}>
                <MapPin className="w-5 h-5" style={{ color: COLORS.warning }} />
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-xs font-semibold uppercase tracking-wider truncate" style={{ color: COLORS.secondary }}>
                  Vacant Lots
                </h3>
                <p className="text-xl font-bold truncate" style={{ color: COLORS.dark }}>{formatNumber(vacantProperties)}</p>
                <div className="flex items-center gap-2 mt-1">
                  <div className="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full rounded-full" 
                      style={{ 
                        width: `${approvedProperties.length > 0 ? (vacantProperties / approvedProperties.length) * 100 : 0}%`,
                        backgroundColor: COLORS.warning 
                      }}
                    />
                  </div>
                  <span className="text-xs font-medium whitespace-nowrap" style={{ color: COLORS.warning }}>
                    {approvedProperties.length > 0 ? Math.round((vacantProperties / approvedProperties.length) * 100) : 0}%
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Delinquent Properties */}
          <div className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.danger}15` }}>
                <AlertCircle className="w-5 h-5" style={{ color: COLORS.danger }} />
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-xs font-semibold uppercase tracking-wider truncate" style={{ color: COLORS.secondary }}>
                  Delinquent
                </h3>
                <p className="text-xl font-bold truncate" style={{ color: COLORS.dark }}>{formatNumber(paymentStats.overdue || 0)}</p>
                <div className="flex items-center gap-2 mt-1">
                  <div className="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full rounded-full" 
                      style={{ 
                        width: `${approvedProperties.length > 0 ? ((paymentStats.overdue || 0) / approvedProperties.length) * 100 : 0}%`,
                        backgroundColor: COLORS.danger 
                      }}
                    />
                  </div>
                  <span className="text-xs font-medium whitespace-nowrap" style={{ color: COLORS.danger }}>
                    {approvedProperties.length > 0 ? Math.round(((paymentStats.overdue || 0) / approvedProperties.length) * 100) : 0}%
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Paid Properties */}
          <div className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.success}15` }}>
                <CheckCircle className="w-5 h-5" style={{ color: COLORS.success }} />
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-xs font-semibold uppercase tracking-wider truncate" style={{ color: COLORS.secondary }}>
                  Paid
                </h3>
                <p className="text-xl font-bold truncate" style={{ color: COLORS.dark }}>{formatNumber(paymentStats.paid || 0)}</p>
                <div className="flex items-center gap-2 mt-1">
                  <div className="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full rounded-full" 
                      style={{ 
                        width: `${approvedProperties.length > 0 ? ((paymentStats.paid || 0) / approvedProperties.length) * 100 : 0}%`,
                        backgroundColor: COLORS.success 
                      }}
                    />
                  </div>
                  <span className="text-xs font-medium whitespace-nowrap" style={{ color: COLORS.success }}>
                    {approvedProperties.length > 0 ? Math.round(((paymentStats.paid || 0) / approvedProperties.length) * 100) : 0}%
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Compact Revenue Cards - Horizontal Layout */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {/* Total Annual Revenue */}
          <div className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                <TrendingUp className="w-5 h-5" style={{ color: COLORS.primary }} />
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-xs font-semibold uppercase tracking-wider truncate" style={{ color: COLORS.secondary }}>
                  Annual Revenue
                </h3>
                <p className="text-lg font-bold truncate" style={{ color: COLORS.dark }}>{formatCurrency(totalAnnualRevenue)}</p>
                <div className="flex items-center gap-2 mt-1">
                  <div className="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full rounded-full" 
                      style={{ 
                        width: `${totalAnnualRevenue > 0 ? 100 : 0}%`,
                        backgroundColor: COLORS.primary 
                      }}
                    />
                  </div>
                  <span className="text-xs font-medium whitespace-nowrap" style={{ color: COLORS.primary }}>
                    Total
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Total Collected */}
          <div className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.success}15` }}>
                <Wallet className="w-5 h-5" style={{ color: COLORS.success }} />
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-xs font-semibold uppercase tracking-wider truncate" style={{ color: COLORS.secondary }}>
                  Collected Revenue
                </h3>
                <p className="text-lg font-bold truncate" style={{ color: COLORS.dark }}>{formatCurrency(totalCollected)}</p>
                <div className="flex items-center gap-2 mt-1">
                  <div className="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full rounded-full" 
                      style={{ 
                        width: `${totalAnnualRevenue > 0 ? (totalCollected / totalAnnualRevenue) * 100 : 0}%`,
                        backgroundColor: COLORS.success 
                      }}
                    />
                  </div>
                  <span className="text-xs font-medium whitespace-nowrap" style={{ color: COLORS.success }}>
                    {totalAnnualRevenue > 0 ? Math.round((totalCollected / totalAnnualRevenue) * 100) : 0}%
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Collection Rate */}
          <div className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.info}15` }}>
                <Percent className="w-5 h-5" style={{ color: COLORS.info }} />
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-xs font-semibold uppercase tracking-wider truncate" style={{ color: COLORS.secondary }}>
                  Collection Rate
                </h3>
                <p className="text-lg font-bold truncate" style={{ color: COLORS.dark }}>{collectionRate}%</p>
                <div className="flex items-center gap-2 mt-1">
                  <div className="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full rounded-full" 
                      style={{ 
                        width: `${collectionRate}%`,
                        backgroundColor: collectionRate >= 80 ? COLORS.success : collectionRate >= 60 ? COLORS.warning : COLORS.danger
                      }}
                    />
                  </div>
                  <span className="text-xs font-medium whitespace-nowrap" style={{ color: COLORS.secondary }}>
                    {formatCurrency(pendingPayments)} pending
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Filter Section */}
        <div className="bg-white border rounded-xl p-6 shadow-sm" style={{ borderColor: COLORS.secondary }}>
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2" style={{ color: COLORS.secondary }} />
                <input
                  type="text"
                  placeholder="Search by owner name, reference number, location..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2 border rounded-lg"
                  style={{ borderColor: COLORS.secondary }}
                />
              </div>
            </div>
            
            <div className="flex-1">
              <div className="relative">
                <Filter className="absolute left-3 top-1/2 transform -translate-y-1/2" style={{ color: COLORS.secondary }} />
                <select
                  value={propertyTypeFilter}
                  onChange={(e) => setPropertyTypeFilter(e.target.value)}
                  className="w-full pl-10 pr-10 py-2 border rounded-lg appearance-none bg-white"
                  style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                >
                  <option value="all">All Property Types</option>
                  {propertyTypes.map(type => (
                    <option key={type} value={type}>{type}</option>
                  ))}
                </select>
              </div>
            </div>
            
            <div className="flex-1">
              <div className="relative">
                <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2" style={{ color: COLORS.secondary }} />
                <select
                  value={paymentFilter}
                  onChange={(e) => setPaymentFilter(e.target.value)}
                  className="w-full pl-10 pr-10 py-2 border rounded-lg appearance-none bg-white"
                  style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                >
                  <option value="all">All Payment Status</option>
                  <option value="paid">Paid</option>
                  <option value="pending">Current Quarter</option>
                  <option value="next-quarter">Next Quarter</option>
                  <option value="overdue">Delinquent</option>
                </select>
              </div>
            </div>
          </div>
          
          {/* Search Stats */}
          <div className="mt-4 flex items-center justify-between text-sm">
            <div style={{ color: COLORS.secondary }}>
              {searchTerm ? (
                <span>
                  Searching for: <span className="font-medium" style={{ color: COLORS.dark }}>"{searchTerm}"</span>
                </span>
              ) : (
                <span>Showing all approved properties</span>
              )}
            </div>
            <div className="font-medium" style={{ color: COLORS.dark }}>
              {filteredProperties.length} of {approvedProperties.length} properties
            </div>
          </div>
        </div>

        {/* Properties Table */}
        <div className="bg-white border rounded-xl shadow-sm" style={{ borderColor: COLORS.secondary }}>
          <div className="p-6 border-b" style={{ borderColor: COLORS.secondary }}>
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
              <div>
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <Home className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Property Registry ({filteredProperties.length})
                </h3>
                <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                  Approved citizen properties
                </p>
              </div>
              
              <div className="inline-flex items-center gap-2 px-3 py-1.5 border rounded-lg text-sm"
                   style={{ borderColor: COLORS.secondary, color: COLORS.secondary }}>
                <Calendar className="w-4 h-4" />
                <span>Current Quarter: Q{Math.floor((new Date().getMonth() / 3)) + 1}</span>
              </div>
            </div>
          </div>
          
          {filteredProperties.length === 0 ? (
            <div className="text-center py-12" style={{ color: COLORS.secondary }}>
              <Home className="w-12 h-12 mx-auto mb-2" />
              <p className="text-sm font-medium" style={{ color: COLORS.dark }}>
                {searchTerm || propertyTypeFilter !== "all" || paymentFilter !== "all"
                  ? "No matching properties found" 
                  : "No approved properties yet"}
              </p>
              <p className="text-sm mt-1 max-w-xs mx-auto">
                {searchTerm || propertyTypeFilter !== "all" || paymentFilter !== "all"
                  ? "Try adjusting your search terms or clear filters"
                  : "Check back later for approved properties"}
              </p>
              {(searchTerm || propertyTypeFilter !== "all" || paymentFilter !== "all") && (
                <button
                  onClick={() => {
                    setSearchTerm("");
                    setPropertyTypeFilter("all");
                    setPaymentFilter("all");
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
                        Reference No.
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Owner
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Type
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Building Status
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Location
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Annual Tax
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Payment Status
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {filteredProperties.map((property) => {
                      const paymentStatus = getPaymentStatus(property.created_at);
                      const buildingStatus = getBuildingStatus(property);
                      
                      return (
                        <tr key={property.id} className="hover:bg-gray-50 transition-colors" 
                            style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}>
                          <td className="p-4">
                            <div className="font-mono font-medium" style={{ color: COLORS.dark }}>
                              {property.reference_number}
                            </div>
                            <div className="text-xs mt-1" style={{ color: COLORS.secondary }}>
                              {formatNumber(property.land_area_sqm)} sqm
                            </div>
                          </td>
                          <td className="p-4">
                            <div className="font-medium" style={{ color: COLORS.dark }}>
                              {property.owner_name || `${property.first_name || ''} ${property.last_name || ''}`.trim()}
                            </div>
                            <div className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                              {property.phone || "No phone"}
                            </div>
                          </td>
                          <td className="p-4">
                            <PropertyTypeBadge propertyType={property.property_type} />
                          </td>
                          <td className="p-4">
                            <div className="flex items-center gap-1.5">
                              {buildingStatus === 'Vacant' ? (
                                <>
                                  <MapPin className="w-3.5 h-3.5" style={{ color: COLORS.secondary }} />
                                  <span className="text-sm" style={{ color: COLORS.secondary }}>{buildingStatus}</span>
                                </>
                              ) : buildingStatus === 'Building pending' ? (
                                <>
                                  <Building className="w-3.5 h-3.5" style={{ color: COLORS.secondary }} />
                                  <span className="text-sm" style={{ color: COLORS.secondary }}>{buildingStatus}</span>
                                </>
                              ) : (
                                <>
                                  <Building className="w-3.5 h-3.5" style={{ color: COLORS.primary }} />
                                  <span className="text-sm font-medium" style={{ color: COLORS.primary }}>{buildingStatus}</span>
                                </>
                              )}
                            </div>
                          </td>
                          <td className="p-4">
                            <div className="text-sm" style={{ color: COLORS.dark }}>{property.lot_location || "Not specified"}</div>
                            <div className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                              Brgy. {property.barangay || "N/A"}
                            </div>
                          </td>
                          <td className="p-4">
                            <div className="font-bold text-sm" style={{ color: COLORS.dark }}>
                              {formatCurrency(property.total_annual_tax)}
                            </div>
                            <div className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                              {formatDate(property.created_at)}
                            </div>
                          </td>
                          <td className="p-4">
                            <PaymentStatusBadge status={paymentStatus} />
                          </td>
                          <td className="p-4">
                            <button
                              onClick={() => handleViewDetails(property.id)}
                              className="px-4 py-2 rounded-lg flex items-center gap-2 transition-all"
                              style={{ backgroundColor: COLORS.primary, color: 'white' }}
                            >
                              <Eye className="w-4 h-4" />
                              View
                            </button>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
              
              {/* Table Footer */}
              <div className="p-4 border-t" style={{ borderColor: COLORS.secondary, backgroundColor: `${COLORS.background}` }}>
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div className="text-sm" style={{ color: COLORS.secondary }}>
                    Showing <span className="font-semibold" style={{ color: COLORS.dark }}>{filteredProperties.length}</span> of{" "}
                    <span className="font-semibold" style={{ color: COLORS.dark }}>{approvedProperties.length}</span> properties
                  </div>
                  <div className="text-sm">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-medium" style={{ color: COLORS.dark }}>Summary:</span>
                      <span className="px-2 py-1 rounded text-xs border" 
                            style={{ backgroundColor: `${COLORS.success}15`, color: COLORS.success, borderColor: `${COLORS.success}30` }}>
                        Paid: {paymentStats.paid || 0}
                      </span>
                      <span className="px-2 py-1 rounded text-xs border" 
                            style={{ backgroundColor: `${COLORS.warning}15`, color: COLORS.warning, borderColor: `${COLORS.warning}30` }}>
                        Current: {paymentStats.pending || 0}
                      </span>
                      <span className="px-2 py-1 rounded text-xs border" 
                            style={{ backgroundColor: `${COLORS.danger}15`, color: COLORS.danger, borderColor: `${COLORS.danger}30` }}>
                        Delinquent: {paymentStats.overdue || 0}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </>
          )}
        </div>

        {/* Footer Summary */}
        <div className="text-center text-sm pt-6 border-t" style={{ color: COLORS.secondary, borderColor: COLORS.secondary }}>
          <p>Property Tax Status Registry • {new Date().toLocaleDateString('en-PH')}</p>
          <p className="text-xs mt-1">
            Local Government Unit - Real Property Tax Management System
          </p>
        </div>
      </div>
    </div>
  );
}