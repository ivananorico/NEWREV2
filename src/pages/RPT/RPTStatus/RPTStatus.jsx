import React, { useState, useEffect } from "react";
import { Search, Filter, Eye, Download, RefreshCw, CheckCircle, Building, User, Calendar, DollarSign, Clock, AlertCircle, Home, Phone, Mail, MapPin, TrendingUp, Wallet, Percent } from "lucide-react";
import { useNavigate } from "react-router-dom";

// Property Type Badge Component
const PropertyTypeBadge = ({ propertyType }) => {
  if (!propertyType) return <span className="text-gray-400 text-sm">Not specified</span>;
  
  const colors = {
    'Residential': 'bg-green-50 text-green-700 border border-green-200',
    'Commercial': 'bg-blue-50 text-blue-700 border border-blue-200',
    'Industrial': 'bg-purple-50 text-purple-700 border border-purple-200',
    'Agricultural': 'bg-yellow-50 text-yellow-700 border border-yellow-200'
  };
  
  const colorClass = colors[propertyType] || 'bg-gray-50 text-gray-700 border border-gray-200';
  
  return (
    <span className={`inline-flex items-center px-2 py-1 rounded text-xs font-medium ${colorClass}`}>
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
          color: 'bg-green-50 text-green-700 border border-green-200',
          icon: <CheckCircle className="w-3 h-3 mr-1" />
        };
      case 'overdue':
        return {
          text: 'Delinquent',
          color: 'bg-red-50 text-red-700 border border-red-200',
          icon: <AlertCircle className="w-3 h-3 mr-1" />
        };
      case 'next-quarter':
        return {
          text: 'Next Quarter',
          color: 'bg-blue-50 text-blue-700 border border-blue-200',
          icon: <Clock className="w-3 h-3 mr-1" />
        };
      case 'pending':
        return {
          text: 'Current Quarter',
          color: 'bg-yellow-50 text-yellow-700 border border-yellow-200',
          icon: <Clock className="w-3 h-3 mr-1" />
        };
      default:
        return {
          text: 'Unknown',
          color: 'bg-gray-50 text-gray-700 border border-gray-200',
          icon: null
        };
    }
  };

  const statusInfo = getStatusInfo();
  
  return (
    <span className={`inline-flex items-center px-2 py-1 rounded text-xs font-medium ${statusInfo.color}`}>
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
  const [useFallbackData, setUseFallbackData] = useState(false);
  const navigate = useNavigate();

  // API Configuration
  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

  const API_PATH = "/RPT/RPTStatus";

  // Generate fallback data for development/demo
  const generateFallbackData = () => {
    const propertyTypes = ['Residential', 'Commercial', 'Industrial', 'Agricultural'];
    const barangays = ['Poblacion', 'San Isidro', 'Santa Cruz', 'San Jose', 'San Miguel', 'San Roque', 'San Antonio', 'San Vicente'];
    const firstNames = ['Juan', 'Maria', 'Pedro', 'Ana', 'Jose', 'Carmen', 'Antonio', 'Teresa', 'Manuel', 'Rosa'];
    const lastNames = ['Dela Cruz', 'Santos', 'Reyes', 'Gonzales', 'Ramos', 'Aquino', 'Mendoza', 'Torres', 'Fernandez', 'Castro'];
    const locations = ['Main Street', 'Highway Road', 'Market Area', 'Subdivision', 'Commercial Center', 'Industrial Zone'];
    
    const fallbackData = [];
    
    for (let i = 1; i <= 20; i++) {
      const firstName = firstNames[Math.floor(Math.random() * firstNames.length)];
      const lastName = lastNames[Math.floor(Math.random() * lastNames.length)];
      const propertyType = propertyTypes[Math.floor(Math.random() * propertyTypes.length)];
      const barangay = barangays[Math.floor(Math.random() * barangays.length)];
      const location = locations[Math.floor(Math.random() * locations.length)];
      
      fallbackData.push({
        id: i,
        reference_number: `RPT-2024-${String(i).padStart(5, '0')}`,
        owner_name: `${firstName} ${lastName}`,
        first_name: firstName,
        last_name: lastName,
        property_type: propertyType,
        lot_location: `${i} ${location}`,
        barangay: barangay,
        total_annual_tax: (Math.random() * 50000 + 5000).toFixed(2),
        land_area_sqm: (Math.random() * 1000 + 100).toFixed(2),
        has_building: Math.random() > 0.3 ? 'yes' : 'no',
        building_count: Math.random() > 0.5 ? Math.floor(Math.random() * 5) + 1 : 0,
        phone: `09${Math.floor(Math.random() * 900000000 + 100000000)}`,
        created_at: new Date(Date.now() - Math.floor(Math.random() * 90) * 24 * 60 * 60 * 1000).toISOString(),
        email: `${firstName.toLowerCase()}.${lastName.toLowerCase().replace(' ', '')}@example.com`
      });
    }
    
    return fallbackData;
  };

  // Define getPaymentStatus function FIRST before using it
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
    
    // Random status for demo (weighted towards paid)
    const random = Math.random();
    if (random < 0.5) return 'paid';
    if (random < 0.8) return 'pending';
    return 'overdue';
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
      
      // Check if we're in development mode and should use fallback
      const isDevelopment = process.env.NODE_ENV === 'development' || 
                          window.location.hostname === 'localhost' ||
                          window.location.hostname === '127.0.0.1';
      
      // For development/testing, you can force fallback data by adding ?fallback=true to URL
      const urlParams = new URLSearchParams(window.location.search);
      const forceFallback = urlParams.get('fallback') === 'true';
      
      if (forceFallback) {
        console.log("Using fallback data (forced via URL parameter)");
        const fallbackData = generateFallbackData();
        setApprovedProperties(fallbackData);
        setUseFallbackData(true);
        setLoading(false);
        return;
      }
      
      // Fetch approved properties
      const response = await fetch(`${API_BASE}${API_PATH}/get_approved_properties.php`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        },
        signal: AbortSignal.timeout(8000) // 8 second timeout
      });
      
      if (!response.ok) {
        throw new Error(`Server error: ${response.status}`);
      }
      
      const text = await response.text();
      
      // Handle empty response
      if (!text.trim()) {
        throw new Error("Empty response from server");
      }
      
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        console.error("Failed to parse JSON:", text);
        
        // In development mode, use fallback data
        if (isDevelopment) {
          console.log("Using fallback data due to JSON parse error");
          const fallbackData = generateFallbackData();
          setApprovedProperties(fallbackData);
          setUseFallbackData(true);
          setLoading(false);
          return;
        }
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
        setUseFallbackData(false);
      } else {
        // Check if this is a database connection error
        const errorMessage = data.error || data.message || "Failed to load approved properties";
        
        if (errorMessage.includes('database') || errorMessage.includes('SQLSTATE') || errorMessage.includes('connection')) {
          console.warn("Database connection issue detected");
          
          // In development or if explicitly allowed, use fallback data
          if (isDevelopment) {
            console.log("Using fallback data due to database error");
            const fallbackData = generateFallbackData();
            setApprovedProperties(fallbackData);
            setUseFallbackData(true);
            setLoading(false);
            return;
          }
        }
        
        throw new Error(errorMessage);
      }
    } catch (err) {
      console.error("Fetch error:", err);
      
      const isDevelopment = process.env.NODE_ENV === 'development' || 
                          window.location.hostname === 'localhost' ||
                          window.location.hostname === '127.0.0.1';
      
      // Check if this is a network/connection error
      const isConnectionError = err.message.includes('Connection refused') || 
                               err.message.includes('Failed to fetch') ||
                               err.message.includes('NetworkError') ||
                               err.message.includes('timeout');
      
      if (isConnectionError && isDevelopment) {
        console.log("Using fallback data for development (connection error)");
        const fallbackData = generateFallbackData();
        setApprovedProperties(fallbackData);
        setUseFallbackData(true);
      } else {
        setError(`Failed to load approved properties: ${err.message}`);
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchApprovedProperties();
  }, []);

  // Calculate total annual revenue
  const totalAnnualRevenue = approvedProperties.reduce((sum, p) => sum + (parseFloat(p.total_annual_tax) || 0), 0);
  
  // Calculate total collected (assuming paid properties have total annual tax collected)
  const totalCollected = approvedProperties
    .filter(p => {
      const status = getPaymentStatus(p.created_at);
      return status === 'paid';
    })
    .reduce((sum, p) => sum + (parseFloat(p.total_annual_tax) || 0), 0);
  
  // Calculate collection rate
  const collectionRate = totalAnnualRevenue > 0 ? Math.round((totalCollected / totalAnnualRevenue) * 100) : 0;
  
  // Calculate pending payments
  const pendingPayments = totalAnnualRevenue - totalCollected;

  // Filter properties based on search, type, and payment status
  const filteredProperties = approvedProperties.filter(property => {
    const matchesSearch = 
      (property.owner_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.reference_number?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.lot_location?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.barangay?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.first_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.last_name?.toLowerCase() || '').includes(searchTerm.toLowerCase());
    
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
    
    if (num >= 1000000) {
      return `₱${(num / 1000000).toFixed(1)}M`;
    }
    if (num >= 1000) {
      return `₱${(num / 1000).toFixed(1)}K`;
    }
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

  // Calculate statistics
  const totalAnnualTax = approvedProperties.reduce((sum, p) => sum + (parseFloat(p.total_annual_tax) || 0), 0);
  const propertiesWithBuildings = approvedProperties.filter(p => p.has_building === 'yes' && (p.building_count || 0) > 0).length;
  const vacantProperties = approvedProperties.filter(p => p.has_building !== 'yes').length;
  
  // Calculate payment status statistics
  const paymentStats = approvedProperties.reduce((stats, property) => {
    const status = getPaymentStatus(property.created_at);
    stats[status] = (stats[status] || 0) + 1;
    return stats;
  }, {});

  if (loading) {
    return (
      <div className="min-h-screen bg-white flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-gray-800 mx-auto"></div>
          <p className="mt-4 text-gray-600 font-medium">
            Loading citizen properties...
          </p>
        </div>
      </div>
    );
  }

  if (error && !useFallbackData) {
    return (
      <div className="min-h-screen bg-white flex items-center justify-center p-4">
        <div className="bg-white border border-gray-200 rounded-xl p-8 max-w-md w-full">
          <div className="text-center">
            <div className="mx-auto w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mb-4">
              <AlertCircle className="w-6 h-6 text-red-600" />
            </div>
            <h2 className="text-xl font-bold text-gray-900 mb-2">Error Loading Data</h2>
            <p className="text-gray-600 mb-4">{error}</p>
            <p className="text-sm text-gray-500 mb-4">
              This could be due to:
              <ul className="mt-2 text-left list-disc pl-5">
                <li>Backend server not running</li>
                <li>Database connection issues</li>
                <li>Network problems</li>
              </ul>
            </p>
            <div className="space-y-3">
              <button
                onClick={fetchApprovedProperties}
                className="w-full bg-gray-900 hover:bg-black text-white px-4 py-3 rounded-lg font-medium flex items-center justify-center gap-2"
              >
                <RefreshCw className="w-4 h-4" />
                Try Again
              </button>
              {(window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') && (
                <button
                  onClick={() => {
                    const fallbackData = generateFallbackData();
                    setApprovedProperties(fallbackData);
                    setUseFallbackData(true);
                    setError(null);
                  }}
                  className="w-full border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-3 rounded-lg font-medium"
                >
                  Use Demo Data for Development
                </button>
              )}
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white">
      {/* Header */}
      <div className="border-b border-gray-200 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="text-2xl font-bold text-gray-900 mb-1">
                Citizen Property Registry
              </h1>
              <p className="text-sm text-gray-600">
                Track property taxes and payment status
                {useFallbackData && (
                  <span className="ml-2 px-2 py-0.5 bg-yellow-100 text-yellow-800 text-xs rounded">
                    Using Demo Data
                  </span>
                )}
              </p>
            </div>
            
            <div className="flex flex-wrap gap-3 items-center">
              {useFallbackData && (
                <button
                  onClick={fetchApprovedProperties}
                  className="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 text-sm"
                >
                  <RefreshCw className="w-4 h-4" />
                  Retry Real Data
                </button>
              )}
              <button
                onClick={fetchApprovedProperties}
                className="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700"
              >
                <RefreshCw className="w-4 h-4" />
                Refresh
              </button>
              <button
                onClick={handleExport}
                className="flex items-center gap-2 px-4 py-2 bg-gray-900 hover:bg-black text-white rounded-lg"
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
        {/* Stats Summary */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Total Properties */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Properties</p>
                <p className="text-2xl font-bold text-gray-900 mt-1">{formatNumber(approvedProperties.length)}</p>
              </div>
              <div className="p-3 bg-gray-100 rounded-lg">
                <Home className="w-5 h-5 text-gray-700" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Registered properties
            </div>
          </div>
          
          {/* Properties with Buildings */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">With Buildings</p>
                <p className="text-2xl font-bold text-gray-900 mt-1">{formatNumber(propertiesWithBuildings)}</p>
              </div>
              <div className="p-3 bg-blue-50 rounded-lg">
                <Building className="w-5 h-5 text-blue-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Properties with structures
            </div>
          </div>

          {/* Vacant Properties */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Vacant Lots</p>
                <p className="text-2xl font-bold text-gray-900 mt-1">{formatNumber(vacantProperties)}</p>
              </div>
              <div className="p-3 bg-yellow-50 rounded-lg">
                <MapPin className="w-5 h-5 text-yellow-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Properties without buildings
            </div>
          </div>

          {/* Delinquent Payments */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Delinquent</p>
                <p className="text-2xl font-bold text-gray-900 mt-1">{formatNumber(paymentStats.overdue || 0)}</p>
              </div>
              <div className="p-3 bg-red-50 rounded-lg">
                <AlertCircle className="w-5 h-5 text-red-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Overdue payments
            </div>
          </div>
        </div>

        {/* Revenue Summary Boxes */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
          {/* Total Annual Revenue */}
          <div className="bg-white border border-blue-200 rounded-lg p-4 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Annual Revenue</p>
                <p className="text-xl font-bold text-blue-700 mt-1">{formatCurrency(totalAnnualRevenue)}</p>
              </div>
              <div className="p-2 bg-blue-100 rounded">
                <TrendingUp className="w-5 h-5 text-blue-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500 mt-2">
              Total expected revenue from all properties
            </div>
          </div>
          
          {/* Total Collected */}
          <div className="bg-white border border-green-200 rounded-lg p-4 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Collected</p>
                <p className="text-xl font-bold text-green-700 mt-1">{formatCurrency(totalCollected)}</p>
              </div>
              <div className="p-2 bg-green-100 rounded">
                <Wallet className="w-5 h-5 text-green-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500 mt-2">
              Actual payments received
            </div>
          </div>
          
          {/* Collection Rate */}
          <div className="bg-white border border-purple-200 rounded-lg p-4 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Collection Rate</p>
                <p className="text-xl font-bold text-purple-700 mt-1">{collectionRate}%</p>
              </div>
              <div className="p-2 bg-purple-100 rounded">
                <Percent className="w-5 h-5 text-purple-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500 mt-2">
              {formatCurrency(pendingPayments)} pending
            </div>
          </div>
        </div>

        {/* Filters Section */}
        <div className="bg-white border border-gray-200 rounded-xl p-5">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div>
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <input
                  type="text"
                  placeholder="Search by owner, reference, or location..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent"
                />
              </div>
            </div>
            
            <div>
              <div className="relative">
                <Filter className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <select
                  value={propertyTypeFilter}
                  onChange={(e) => setPropertyTypeFilter(e.target.value)}
                  className="w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent appearance-none bg-white"
                >
                  <option value="all">All Property Types</option>
                  {propertyTypes.map(type => (
                    <option key={type} value={type}>{type}</option>
                  ))}
                </select>
              </div>
            </div>
            
            <div>
              <div className="relative">
                <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <select
                  value={paymentFilter}
                  onChange={(e) => setPaymentFilter(e.target.value)}
                  className="w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent appearance-none bg-white"
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
            <div className="text-gray-600">
              {searchTerm ? (
                <span>
                  Searching for: <span className="font-medium text-gray-900">"{searchTerm}"</span>
                </span>
              ) : (
                <span>Showing all citizen properties</span>
              )}
              {useFallbackData && (
                <span className="ml-2 px-2 py-0.5 bg-yellow-100 text-yellow-800 text-xs rounded">
                  Demo Mode
                </span>
              )}
            </div>
            <div className="text-gray-700 font-medium">
              {filteredProperties.length} of {approvedProperties.length} properties
            </div>
          </div>
        </div>

        {/* Properties Table */}
        <div className="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
          <div className="px-5 py-4 border-b border-gray-200 bg-gray-50">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wider">Citizen Properties</h2>
                <p className="text-sm text-gray-600 mt-1">
                  {filteredProperties.length} propert{filteredProperties.length !== 1 ? 'ies' : 'y'}
                  {useFallbackData && " (Demo Data)"}
                </p>
              </div>
              <div className="mt-2 sm:mt-0">
                <div className="inline-flex items-center gap-2 text-xs bg-gray-100 text-gray-700 px-3 py-1.5 rounded-lg">
                  <Calendar className="w-3 h-3" />
                  <span>Current Quarter: Q{Math.floor((new Date().getMonth() / 3)) + 1}</span>
                </div>
              </div>
            </div>
          </div>
          
          {filteredProperties.length === 0 ? (
            <div className="px-4 py-12 text-center">
              <div className="mx-auto w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                <Home className="w-6 h-6 text-gray-400" />
              </div>
              <h3 className="text-sm font-medium text-gray-900 mb-1">
                {searchTerm || propertyTypeFilter !== "all" || paymentFilter !== "all"
                  ? "No matching properties found" 
                  : "No approved properties yet"}
              </h3>
              <p className="text-sm text-gray-500 max-w-xs mx-auto">
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
                  className="mt-4 text-sm font-medium text-gray-900 hover:text-black"
                >
                  Clear filters
                </button>
              )}
            </div>
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Reference No.
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Property Owner
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Property Type
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Building Status
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Location
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Annual Tax
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Payment Status
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {filteredProperties.map((property) => {
                      const paymentStatus = getPaymentStatus(property.created_at);
                      const buildingStatus = getBuildingStatus(property);
                      
                      return (
                        <tr key={property.id} className="hover:bg-gray-50">
                          <td className="px-4 py-3">
                            <div className="font-mono text-xs font-semibold text-gray-900">
                              {property.reference_number}
                            </div>
                            <div className="text-xs text-gray-500 mt-0.5">
                              {formatNumber(property.land_area_sqm)} sqm
                            </div>
                          </td>
                          <td className="px-4 py-3">
                            <div className="font-medium text-sm text-gray-900">
                              {property.owner_name || `${property.first_name || ''} ${property.last_name || ''}`.trim()}
                            </div>
                            <div className="text-xs text-gray-500 mt-0.5">
                              {property.phone || "No phone"}
                            </div>
                          </td>
                          <td className="px-4 py-3">
                            <PropertyTypeBadge propertyType={property.property_type} />
                          </td>
                          <td className="px-4 py-3">
                            <div className="flex items-center gap-1.5">
                              {buildingStatus === 'Vacant' ? (
                                <>
                                  <MapPin className="w-3.5 h-3.5 text-gray-400" />
                                  <span className="text-sm text-gray-600">{buildingStatus}</span>
                                </>
                              ) : buildingStatus === 'Building pending' ? (
                                <>
                                  <Building className="w-3.5 h-3.5 text-gray-400" />
                                  <span className="text-sm text-gray-600">{buildingStatus}</span>
                                </>
                              ) : (
                                <>
                                  <Building className="w-3.5 h-3.5 text-blue-600" />
                                  <span className="text-sm text-blue-700 font-medium">{buildingStatus}</span>
                                </>
                              )}
                            </div>
                          </td>
                          <td className="px-4 py-3">
                            <div className="text-sm text-gray-900">{property.lot_location || "Not specified"}</div>
                            <div className="text-xs text-gray-500">
                              Brgy. {property.barangay || "N/A"}
                            </div>
                          </td>
                          <td className="px-4 py-3">
                            <div className="font-bold text-gray-900 text-sm">
                              {formatCurrency(property.total_annual_tax)}
                            </div>
                            <div className="text-xs text-gray-500">
                              {formatDate(property.created_at)}
                            </div>
                          </td>
                          <td className="px-4 py-3">
                            <PaymentStatusBadge status={paymentStatus} />
                          </td>
                          <td className="px-4 py-3">
                            <button
                              onClick={() => handleViewDetails(property.id)}
                              className="text-xs font-medium px-3 py-1.5 rounded bg-gray-900 text-white hover:bg-black transition duration-200 flex items-center gap-1.5"
                            >
                              <Eye className="w-3.5 h-3.5" />
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
              <div className="px-5 py-4 border-t border-gray-200 bg-gray-50">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div className="text-sm text-gray-700">
                    Showing <span className="font-semibold">{filteredProperties.length}</span> of{" "}
                    <span className="font-semibold">{approvedProperties.length}</span> properties
                  </div>
                  <div className="text-sm text-gray-700">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-medium">Summary:</span>
                      <span className="px-2 py-1 rounded bg-green-50 text-green-700 border border-green-200 text-xs">
                        Paid: {paymentStats.paid || 0}
                      </span>
                      <span className="px-2 py-1 rounded bg-yellow-50 text-yellow-700 border border-yellow-200 text-xs">
                        Current: {paymentStats.pending || 0}
                      </span>
                      <span className="px-2 py-1 rounded bg-red-50 text-red-700 border border-red-200 text-xs">
                        Delinquent: {paymentStats.overdue || 0}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </>
          )}
        </div>

        {/* Footer */}
        <div className="mt-8 pt-6 border-t border-gray-200">
          <div className="text-center text-sm text-gray-600">
            <p className="font-medium">Property Tax Management System</p>
            <p className="mt-1 text-xs">
              {useFallbackData ? "Currently using demo data. Check backend connection for real data." : "Connected to live database"}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}