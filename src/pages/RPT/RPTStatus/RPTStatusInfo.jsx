import React, { useState, useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";
import {
  ArrowLeft,
  Printer,
  FileText,
  Building,
  Home,
  User,
  MapPin,
  DollarSign,
  Calendar,
  Phone,
  Mail,
  CheckCircle,
  AlertCircle,
  Clock,
  TrendingUp,
  Receipt,
  Layers,
  CreditCard,
  CheckCheck,
  FileSignature,
  Tag,
  Square,
  Landmark,
  Ruler,
  BarChart,
  Percent,
  Maximize2,
  Hash,
  CalendarDays,
  Barcode
} from "lucide-react";

// Enhanced Color Palette - Modern & Professional
const COLORS = {
  // Primary Colors
  primary: {
    main: '#3b82f6',      // Bright Blue
    light: '#60a5fa',
    dark: '#2563eb',
    bg: '#eff6ff',
    border: '#bfdbfe'
  },
  // Secondary Colors
  secondary: {
    main: '#64748b',      // Slate Gray
    light: '#94a3b8',
    dark: '#475569',
    bg: '#f8fafc',
    border: '#e2e8f0'
  },
  // Status Colors
  success: {
    main: '#10b981',      // Emerald
    light: '#34d399',
    dark: '#059669',
    bg: '#d1fae5',
    border: '#a7f3d0'
  },
  warning: {
    main: '#f59e0b',      // Amber
    light: '#fbbf24',
    dark: '#d97706',
    bg: '#fef3c7',
    border: '#fde68a'
  },
  danger: {
    main: '#ef4444',      // Red
    light: '#f87171',
    dark: '#dc2626',
    bg: '#fee2e2',
    border: '#fecaca'
  },
  info: {
    main: '#06b6d4',      // Cyan
    light: '#22d3ee',
    dark: '#0891b2',
    bg: '#cffafe',
    border: '#a5f3fc'
  },
  // Special Colors
  purple: {
    main: '#8b5cf6',      // Violet
    light: '#a78bfa',
    dark: '#7c3aed',
    bg: '#ede9fe',
    border: '#ddd6fe'
  },
  // Background Colors
  background: {
    main: '#f8fafc',      // Light Gray
    card: '#ffffff',
    hover: '#f1f5f9'
  },
  // Text Colors
  text: {
    primary: '#1e293b',   // Slate 800
    secondary: '#64748b', // Slate 500
    tertiary: '#94a3b8',  // Slate 400
    white: '#ffffff'
  },
  // Border Colors
  border: {
    light: '#e2e8f0',
    medium: '#cbd5e1',
    dark: '#94a3b8'
  }
};

// Status Badge Component
const StatusBadge = ({ status, type = 'payment' }) => {
  const getStatusConfig = () => {
    switch(status?.toLowerCase()) {
      case 'paid':
        return {
          text: 'PAID',
          color: COLORS.success.main,
          bgColor: COLORS.success.bg,
          borderColor: COLORS.success.border,
          icon: <CheckCircle className="w-3.5 h-3.5 mr-1.5" />
        };
      case 'overdue':
        return {
          text: 'DELINQUENT',
          color: COLORS.danger.main,
          bgColor: COLORS.danger.bg,
          borderColor: COLORS.danger.border,
          icon: <AlertCircle className="w-3.5 h-3.5 mr-1.5" />
        };
      case 'pending':
        return {
          text: 'PENDING',
          color: COLORS.warning.main,
          bgColor: COLORS.warning.bg,
          borderColor: COLORS.warning.border,
          icon: <Clock className="w-3.5 h-3.5 mr-1.5" />
        };
      default:
        return {
          text: status?.toUpperCase() || 'UNKNOWN',
          color: COLORS.secondary.main,
          bgColor: COLORS.secondary.bg,
          borderColor: COLORS.secondary.border,
          icon: null
        };
    }
  };

  const config = getStatusConfig();
  
  return (
    <span 
      className="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold border transition-all duration-200 hover:scale-105"
      style={{ 
        backgroundColor: config.bgColor,
        color: config.color,
        borderColor: config.borderColor,
        boxShadow: '0 1px 2px rgba(0, 0, 0, 0.05)'
      }}
    >
      {config.icon}
      {config.text}
    </span>
  );
};

// Property Type Badge
const PropertyTypeBadge = ({ propertyType }) => {
  const getPropertyTypeConfig = () => {
    switch(propertyType?.toLowerCase()) {
      case 'residential':
        return {
          color: COLORS.success.main,
          bgColor: COLORS.success.bg,
          borderColor: COLORS.success.border
        };
      case 'commercial':
        return {
          color: COLORS.primary.main,
          bgColor: COLORS.primary.bg,
          borderColor: COLORS.primary.border
        };
      case 'industrial':
        return {
          color: COLORS.warning.main,
          bgColor: COLORS.warning.bg,
          borderColor: COLORS.warning.border
        };
      case 'agricultural':
        return {
          color: COLORS.info.main,
          bgColor: COLORS.info.bg,
          borderColor: COLORS.info.border
        };
      default:
        return {
          color: COLORS.secondary.main,
          bgColor: COLORS.secondary.bg,
          borderColor: COLORS.secondary.border
        };
    }
  };

  const config = getPropertyTypeConfig();
  
  return (
    <span 
      className="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold border transition-all duration-200 hover:scale-105"
      style={{ 
        backgroundColor: config.bgColor,
        color: config.color,
        borderColor: config.borderColor,
        boxShadow: '0 1px 2px rgba(0, 0, 0, 0.05)'
      }}
    >
      {propertyType || 'Not specified'}
    </span>
  );
};

export default function RPTStatusInfo() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [property, setProperty] = useState(null);
  const [buildings, setBuildings] = useState([]);
  const [quarterlyTaxes, setQuarterlyTaxes] = useState([]);
  const [loading, setLoading] = useState(true);

  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

  useEffect(() => {
    fetchPropertyDetails();
  }, [id]);

  const fetchPropertyDetails = async () => {
    try {
      setLoading(true);
      const res = await fetch(
        `${API_BASE}/RPT/RPTStatus/get_property_details.php?id=${id}`,
        { 
          method: 'GET',
          headers: { 'Accept': 'application/json' }
        }
      );
      
      const data = await res.json();
      
      if (data.status === "success") {
        setProperty(data.data.property);
        setBuildings(data.data.buildings || []);
        setQuarterlyTaxes(data.data.quarterly_taxes || []);
      }
    } catch (err) {
      console.error("Error:", err);
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (amount) => {
    if (!amount || isNaN(amount)) return '₱0';
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(amount);
  };

  const formatDate = (dateString) => {
    if (!dateString || dateString === "0000-00-00" || dateString === "0000-00-00 00:00:00") return "N/A";
    try {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    } catch (e) {
      return "N/A";
    }
  };

  const getPaymentStatus = (status) => {
    switch(status?.toLowerCase()) {
      case 'paid': 
        return { 
          text: "PAID", 
          color: `${COLORS.success.bg} ${COLORS.text.success}`,
          border: COLORS.success.border,
          icon: <CheckCircle className="w-3.5 h-3.5 mr-1.5" style={{ color: COLORS.success.main }} />
        };
      case 'overdue': 
        return { 
          text: "DELINQUENT", 
          color: `${COLORS.danger.bg} ${COLORS.text.danger}`,
          border: COLORS.danger.border,
          icon: <AlertCircle className="w-3.5 h-3.5 mr-1.5" style={{ color: COLORS.danger.main }} />
        };
      default: 
        return { 
          text: "PENDING", 
          color: `${COLORS.warning.bg} ${COLORS.text.warning}`,
          border: COLORS.warning.border,
          icon: <Clock className="w-3.5 h-3.5 mr-1.5" style={{ color: COLORS.warning.main }} />
        };
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background.main }}>
        <div className="flex flex-col items-center justify-center h-screen">
          <div className="relative">
            <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2" 
                 style={{ borderColor: COLORS.primary.main }}></div>
            <div className="absolute inset-0 flex items-center justify-center">
              <div className="w-8 h-8 rounded-full" 
                   style={{ backgroundColor: COLORS.primary.main }}></div>
            </div>
          </div>
          <p className="mt-4 text-lg font-medium" style={{ color: COLORS.text.primary }}>Loading property details...</p>
          <p className="text-sm mt-1" style={{ color: COLORS.text.secondary }}>Please wait while we fetch the information</p>
        </div>
      </div>
    );
  }

  if (!property) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background.main }}>
        <div className="max-w-md mx-auto p-6">
          <div className="rounded-2xl p-8 text-center shadow-lg" 
               style={{ backgroundColor: COLORS.background.card, border: `1px solid ${COLORS.border.light}` }}>
            <div className="w-20 h-20 mx-auto mb-6 rounded-full flex items-center justify-center"
                 style={{ backgroundColor: COLORS.danger.bg }}>
              <AlertCircle className="w-10 h-10" style={{ color: COLORS.danger.main }} />
            </div>
            <h2 className="text-2xl font-bold mb-3" style={{ color: COLORS.text.primary }}>Property Not Found</h2>
            <p className="mb-8" style={{ color: COLORS.text.secondary }}>The requested property details could not be loaded.</p>
            <button
              onClick={() => navigate(-1)}
              className="px-8 py-3 rounded-xl font-medium transition-all duration-200 hover:shadow-lg"
              style={{ 
                backgroundColor: COLORS.primary.main,
                color: COLORS.text.white
              }}
            >
              Return to Property Registry
            </button>
          </div>
        </div>
      </div>
    );
  }

  // Calculate totals (KEEPING SAME DATA LOGIC)
  const totalLandTax = parseFloat(property.land_annual_tax) || 0;
  const totalBuildingTax = buildings.reduce((sum, b) => sum + (parseFloat(b.building_annual_tax) || 0), 0);
  const totalAnnualTax = parseFloat(property.total_annual_tax) || (totalLandTax + totalBuildingTax);
  const quarterlyAmount = totalAnnualTax / 4;
  
  // Calculate payment statistics
  const totalPaid = quarterlyTaxes
    .filter(tax => tax.payment_status === 'paid')
    .reduce((sum, tax) => sum + (parseFloat(tax.total_quarterly_tax) || 0), 0);
    
  const collectionRate = totalAnnualTax > 0 ? Math.round((totalPaid / totalAnnualTax) * 100) : 0;
  
  // Calculate totals with penalties for quarterly taxes
  const quarterlyTaxesWithTotals = quarterlyTaxes.map(tax => ({
    ...tax,
    totalWithPenalty: (parseFloat(tax.total_quarterly_tax) || 0) + (parseFloat(tax.penalty_amount) || 0)
  }));

  // Calculate building totals
  const totalFloorArea = buildings.reduce((sum, b) => sum + (parseFloat(b.floor_area_sqm) || 0), 0);
  const totalBuildingMarketValue = buildings.reduce((sum, b) => sum + (parseFloat(b.building_market_value) || 0), 0);
  const totalBuildingAssessedValue = buildings.reduce((sum, b) => sum + (parseFloat(b.building_assessed_value) || 0), 0);
  const avgAssessmentLevel = buildings.length > 0 
    ? (buildings.reduce((sum, b) => sum + (parseFloat(b.assessment_level) || 0), 0) / buildings.length).toFixed(1)
    : 0;

  // Get building details for display
  const buildingTdn = buildings.length > 0 ? buildings[0]?.tdn : null;
  const buildingYearBuilt = buildings.length > 0 ? buildings[0]?.year_built : null;

  return (
    <div className="min-h-screen" style={{ backgroundColor: COLORS.background.main }}>
      {/* Enhanced Header */}
      <div className="shadow-md" style={{ 
        backgroundColor: COLORS.background.card,
        borderBottom: `1px solid ${COLORS.border.light}`
      }}>
        <div className="max-w-7xl mx-auto px-6 py-5">
          <div className="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
            <div className="flex items-center space-x-4">
              <button
                onClick={() => navigate(-1)}
                className="p-2.5 rounded-xl transition-all duration-200 hover:shadow-md"
                style={{ 
                  backgroundColor: COLORS.background.hover,
                  color: COLORS.primary.main
                }}
              >
                <ArrowLeft className="w-5 h-5" />
              </button>
              <div>
                <div className="flex items-center gap-3">
                  <div className="p-2.5 rounded-xl"
                       style={{ backgroundColor: COLORS.primary.bg }}>
                    <FileSignature className="w-6 h-6" style={{ color: COLORS.primary.main }} />
                  </div>
                  <div>
                    <h1 className="text-2xl font-bold" style={{ color: COLORS.text.primary }}>Property Tax Record</h1>
                    <div className="flex items-center gap-3 mt-2">
                      <span className="font-mono px-3 py-1.5 rounded-lg text-sm font-medium"
                            style={{ 
                              backgroundColor: COLORS.primary.bg,
                              color: COLORS.primary.main,
                              border: `1px solid ${COLORS.primary.border}`
                            }}>
                        {property.reference_number}
                      </span>
                      <span className="text-sm" style={{ color: COLORS.text.secondary }}>• {property.owner_name}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div className="flex items-center space-x-3">
              <button
                onClick={() => window.print()}
                className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium transition-all duration-200 hover:shadow-md"
                style={{ 
                  backgroundColor: COLORS.background.hover,
                  color: COLORS.text.primary,
                  border: `1px solid ${COLORS.border.light}`
                }}
              >
                <Printer className="w-4 h-4" />
                <span className="text-sm font-medium">Print</span>
              </button>
              <button
                onClick={fetchPropertyDetails}
                className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium transition-all duration-200 hover:shadow-lg"
                style={{ 
                  backgroundColor: COLORS.primary.main,
                  color: COLORS.text.white
                }}
              >
                <FileText className="w-4 h-4" />
                <span className="text-sm font-medium">Refresh</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-6 py-8 space-y-8">
        {/* Enhanced Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="rounded-2xl p-6 shadow-sm transition-all duration-300 hover:shadow-md"
               style={{ 
                 backgroundColor: COLORS.background.card,
                 border: `1px solid ${COLORS.border.light}`
               }}>
            <div className="flex items-start justify-between mb-5">
              <div className="p-3 rounded-xl"
                   style={{ backgroundColor: COLORS.primary.bg }}>
                <DollarSign className="w-6 h-6" style={{ color: COLORS.primary.main }} />
              </div>
              <div className="text-right">
                <p className="text-sm font-medium" style={{ color: COLORS.text.secondary }}>Annual Tax</p>
                <p className="text-2xl font-bold mt-2" style={{ color: COLORS.text.primary }}>{formatCurrency(totalAnnualTax)}</p>
              </div>
            </div>
            <div className="text-xs font-medium" style={{ color: COLORS.text.secondary }}>Current fiscal year assessment</div>
          </div>

          <div className="rounded-2xl p-6 shadow-sm transition-all duration-300 hover:shadow-md"
               style={{ 
                 backgroundColor: COLORS.background.card,
                 border: `1px solid ${COLORS.border.light}`
               }}>
            <div className="flex items-start justify-between mb-5">
              <div className="p-3 rounded-xl"
                   style={{ backgroundColor: COLORS.success.bg }}>
                <TrendingUp className="w-6 h-6" style={{ color: COLORS.success.main }} />
              </div>
              <div className="text-right">
                <p className="text-sm font-medium" style={{ color: COLORS.text.secondary }}>Collection Rate</p>
                <p className="text-2xl font-bold mt-2" style={{ color: COLORS.text.primary }}>{collectionRate}%</p>
              </div>
            </div>
            <div className="text-xs font-medium" style={{ color: COLORS.text.secondary }}>{formatCurrency(totalPaid)} collected</div>
          </div>

          <div className="rounded-2xl p-6 shadow-sm transition-all duration-300 hover:shadow-md"
               style={{ 
                 backgroundColor: COLORS.background.card,
                 border: `1px solid ${COLORS.border.light}`
               }}>
            <div className="flex items-start justify-between mb-5">
              <div className="p-3 rounded-xl"
                   style={{ backgroundColor: COLORS.warning.bg }}>
                <Calendar className="w-6 h-6" style={{ color: COLORS.warning.main }} />
              </div>
              <div className="text-right">
                <p className="text-sm font-medium" style={{ color: COLORS.text.secondary }}>Quarterly Tax</p>
                <p className="text-2xl font-bold mt-2" style={{ color: COLORS.text.primary }}>{formatCurrency(quarterlyAmount)}</p>
              </div>
            </div>
            <div className="text-xs font-medium" style={{ color: COLORS.text.secondary }}>Per quarter installment</div>
          </div>
        </div>

        {/* Enhanced Property & Owner Information */}
        <div className="rounded-2xl shadow-sm overflow-hidden"
             style={{ 
               backgroundColor: COLORS.background.card,
               border: `1px solid ${COLORS.border.light}`
             }}>
          <div className="px-8 py-6 border-b"
               style={{ borderColor: COLORS.border.light }}>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-4">
                <div className="p-3 rounded-xl"
                     style={{ backgroundColor: COLORS.primary.bg }}>
                  <MapPin className="w-5 h-5" style={{ color: COLORS.primary.main }} />
                </div>
                <div>
                  <h2 className="text-lg font-semibold" style={{ color: COLORS.text.primary }}>Property & Owner Information</h2>
                  <p className="text-sm mt-1" style={{ color: COLORS.text.secondary }}>Complete property details and owner information</p>
                </div>
              </div>
              <span className={`px-4 py-2 text-sm font-medium rounded-full transition-all duration-200 hover:scale-105 ${
                property.has_building === 'yes' 
                  ? 'bg-green-100 text-green-800 border border-green-200' 
                  : 'bg-gray-100 text-gray-800 border border-gray-200'
              }`}>
                {property.has_building === 'yes' ? 'With Building' : 'Vacant Lot'}
              </span>
            </div>
          </div>
          <div className="p-8">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
              {/* Property Owner */}
              <div className="space-y-8">
                <div>
                  <h3 className="text-sm font-semibold mb-6 flex items-center gap-3">
                    <div className="p-2.5 rounded-lg"
                         style={{ backgroundColor: COLORS.background.hover }}>
                      <User className="w-4 h-4" style={{ color: COLORS.text.secondary }} />
                    </div>
                    <span style={{ color: COLORS.text.primary }}>Property Owner</span>
                  </h3>
                  <div className="space-y-6">
                    <div>
                      <p className="text-sm font-medium mb-2" style={{ color: COLORS.text.secondary }}>Full Name</p>
                      <p className="text-xl font-bold" style={{ color: COLORS.text.primary }}>{property.owner_name}</p>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <p className="text-sm font-medium mb-2" style={{ color: COLORS.text.secondary }}>Sex</p>
                        <p className="font-medium" style={{ color: COLORS.text.primary }}>
                          {property.sex ? property.sex.charAt(0).toUpperCase() + property.sex.slice(1) : 'N/A'}
                        </p>
                      </div>
                      <div>
                        <p className="text-sm font-medium mb-2" style={{ color: COLORS.text.secondary }}>Marital Status</p>
                        <p className="font-medium" style={{ color: COLORS.text.primary }}>
                          {property.marital_status ? property.marital_status.charAt(0).toUpperCase() + property.marital_status.slice(1) : 'N/A'}
                        </p>
                      </div>
                      <div>
                        <p className="text-sm font-medium mb-2" style={{ color: COLORS.text.secondary }}>Birthdate</p>
                        <p className="font-medium" style={{ color: COLORS.text.primary }}>{formatDate(property.birthdate)}</p>
                      </div>
                      <div>
                        <p className="text-sm font-medium mb-2" style={{ color: COLORS.text.secondary }}>Status</p>
                        <span className="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium"
                              style={{ 
                                backgroundColor: COLORS.success.bg,
                                color: COLORS.success.main,
                                border: `1px solid ${COLORS.success.border}`
                              }}>
                          ACTIVE
                        </span>
                      </div>
                    </div>
                    <div className="space-y-4">
                      <div>
                        <p className="text-sm font-medium mb-3" style={{ color: COLORS.text.secondary }}>Contact Information</p>
                        <div className="flex flex-col gap-3">
                          <div className="flex items-center text-sm">
                            <Phone className="w-4 h-4 mr-3" style={{ color: COLORS.text.secondary }} />
                            <span className="font-medium" style={{ color: COLORS.text.primary }}>{property.phone || 'N/A'}</span>
                          </div>
                          <div className="flex items-center text-sm">
                            <Mail className="w-4 h-4 mr-3" style={{ color: COLORS.text.secondary }} />
                            <span className="font-medium" style={{ color: COLORS.text.primary }}>{property.email || 'N/A'}</span>
                          </div>
                        </div>
                      </div>
                      <div>
                        <p className="text-sm font-medium mb-2" style={{ color: COLORS.text.secondary }}>Registered Address</p>
                        <p className="font-medium" style={{ color: COLORS.text.primary }}>{property.owner_address || 'N/A'}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Property Location */}
              <div className="space-y-8">
                <div>
                  <h3 className="text-sm font-semibold mb-6 flex items-center gap-3">
                    <div className="p-2.5 rounded-lg"
                         style={{ backgroundColor: COLORS.background.hover }}>
                      <Home className="w-4 h-4" style={{ color: COLORS.text.secondary }} />
                    </div>
                    <span style={{ color: COLORS.text.primary }}>Property Location</span>
                  </h3>
                  <div className="space-y-6">
                    <div>
                      <p className="text-sm font-medium mb-2" style={{ color: COLORS.text.secondary }}>Complete Address</p>
                      <p className="font-medium" style={{ color: COLORS.text.primary }}>{property.lot_location}</p>
                      <p className="text-sm mt-2" style={{ color: COLORS.text.secondary }}>
                        {property.barangay}, District {property.district}, {property.city}, {property.province}
                      </p>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <p className="text-sm font-medium mb-2" style={{ color: COLORS.text.secondary }}>Property Type</p>
                        <PropertyTypeBadge propertyType={property.property_type || "Residential"} />
                      </div>
                      <div>
                        <p className="text-sm font-medium mb-2" style={{ color: COLORS.text.secondary }}>Land Area</p>
                        <p className="font-medium" style={{ color: COLORS.text.primary }}>{property.land_area_sqm} sqm</p>
                      </div>
                      <div>
                        <p className="text-sm font-medium mb-2" style={{ color: COLORS.text.secondary }}>Zip Code</p>
                        <p className="font-medium" style={{ color: COLORS.text.primary }}>{property.zip_code || 'N/A'}</p>
                      </div>
                      <div>
                        <p className="text-sm font-medium mb-2" style={{ color: COLORS.text.secondary }}>Registered</p>
                        <p className="font-medium" style={{ color: COLORS.text.primary }}>{formatDate(property.created_at)}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Enhanced Property Assessment */}
        <div className="rounded-2xl shadow-sm overflow-hidden"
             style={{ 
               backgroundColor: COLORS.background.card,
               border: `1px solid ${COLORS.border.light}`
             }}>
          <div className="px-8 py-6 border-b"
               style={{ borderColor: COLORS.border.light }}>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-4">
                <div className="p-3 rounded-xl"
                     style={{ backgroundColor: COLORS.purple.bg }}>
                  <Landmark className="w-5 h-5" style={{ color: COLORS.purple.main }} />
                </div>
                <div>
                  <h2 className="text-lg font-semibold" style={{ color: COLORS.text.primary }}>Property Assessment Details</h2>
                  <p className="text-sm mt-1" style={{ color: COLORS.text.secondary }}>Detailed land and building valuation assessment</p>
                </div>
              </div>
              <div className="flex items-center gap-3">
                <span className="text-sm font-medium" style={{ color: COLORS.text.secondary }}>Land TDN: {property.land_tdn || 'N/A'}</span>
                {buildings.length > 0 && (
                  <span className="px-4 py-1.5 rounded-full text-sm font-medium transition-all duration-200 hover:scale-105"
                        style={{ 
                          backgroundColor: COLORS.success.bg,
                          color: COLORS.success.main,
                          border: `1px solid ${COLORS.success.border}`
                        }}>
                    {buildings.length} building{buildings.length !== 1 ? 's' : ''}
                  </span>
                )}
              </div>
            </div>
          </div>
          <div className="p-8">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
              
              {/* Land Assessment */}
              <div className="space-y-8">
                <div>
                  <div className="flex items-center justify-between mb-8">
                    <h3 className="text-sm font-semibold flex items-center gap-3">
                      <div className="p-2.5 rounded-lg"
                           style={{ backgroundColor: COLORS.primary.bg }}>
                        <Home className="w-4 h-4" style={{ color: COLORS.primary.main }} />
                      </div>
                      <span style={{ color: COLORS.text.primary }}>Land Assessment</span>
                    </h3>
                    <div className="flex items-center gap-3">
                      <span className="text-xs font-medium" style={{ color: COLORS.primary.main }}>ID: {property.land_tdn || 'N/A'}</span>
                      {buildingYearBuilt && (
                        <span className="text-xs font-medium" style={{ color: COLORS.primary.main }}>• Built: {buildingYearBuilt}</span>
                      )}
                    </div>
                  </div>
                  
                  {/* Property Details */}
                  <div className="mb-8">
                    <h4 className="text-xs font-semibold uppercase tracking-wide mb-4" style={{ color: COLORS.text.secondary }}>Property Details</h4>
                    <div className="grid grid-cols-2 gap-4">
                      <div className="p-4 rounded-xl"
                           style={{ backgroundColor: COLORS.background.hover, border: `1px solid ${COLORS.border.light}` }}>
                        <div className="flex items-center gap-2 mb-3">
                          <Ruler className="w-4 h-4" style={{ color: COLORS.text.secondary }} />
                          <p className="text-xs font-medium" style={{ color: COLORS.text.secondary }}>Total Area</p>
                        </div>
                        <p className="text-lg font-bold" style={{ color: COLORS.text.primary }}>{property.land_area_sqm} sqm</p>
                      </div>
                      <div className="p-4 rounded-xl"
                           style={{ backgroundColor: COLORS.background.hover, border: `1px solid ${COLORS.border.light}` }}>
                        <div className="flex items-center gap-2 mb-3">
                          <Hash className="w-4 h-4" style={{ color: COLORS.text.secondary }} />
                          <p className="text-xs font-medium" style={{ color: COLORS.text.secondary }}>Property Type</p>
                        </div>
                        <p className="text-lg font-bold" style={{ color: COLORS.text.primary }}>{property.property_type || "Residential"}</p>
                      </div>
                    </div>
                  </div>

                  {/* Valuation Details */}
                  <div className="mb-8">
                    <h4 className="text-xs font-semibold uppercase tracking-wide mb-4" style={{ color: COLORS.text.secondary }}>Valuation Details</h4>
                    <div className="grid grid-cols-2 gap-4">
                      <div className="p-4 rounded-xl"
                           style={{ backgroundColor: COLORS.primary.bg, border: `1px solid ${COLORS.primary.border}` }}>
                        <div className="flex items-center gap-2 mb-3">
                          <DollarSign className="w-4 h-4" style={{ color: COLORS.primary.main }} />
                          <p className="text-xs font-medium" style={{ color: COLORS.primary.main }}>Market Value</p>
                        </div>
                        <p className="text-lg font-bold" style={{ color: COLORS.primary.main }}>{formatCurrency(property.land_market_value)}</p>
                      </div>
                      <div className="p-4 rounded-xl"
                           style={{ backgroundColor: COLORS.success.bg, border: `1px solid ${COLORS.success.border}` }}>
                        <div className="flex items-center gap-2 mb-3">
                          <BarChart className="w-4 h-4" style={{ color: COLORS.success.main }} />
                          <p className="text-xs font-medium" style={{ color: COLORS.success.main }}>Assessed Value</p>
                        </div>
                        <p className="text-lg font-bold" style={{ color: COLORS.success.main }}>{formatCurrency(property.land_assessed_value)}</p>
                      </div>
                      <div className="p-4 rounded-xl"
                           style={{ backgroundColor: COLORS.purple.bg, border: `1px solid ${COLORS.purple.border}` }}>
                        <div className="flex items-center gap-2 mb-3">
                          <Percent className="w-4 h-4" style={{ color: COLORS.purple.main }} />
                          <p className="text-xs font-medium" style={{ color: COLORS.purple.main }}>Assessment Level</p>
                        </div>
                        <p className="text-lg font-bold" style={{ color: COLORS.purple.main }}>{property.assessment_level}%</p>
                      </div>
                      <div className="p-4 rounded-xl"
                           style={{ backgroundColor: COLORS.background.hover, border: `1px solid ${COLORS.border.light}` }}>
                        <div className="flex items-center gap-2 mb-3">
                          <Maximize2 className="w-4 h-4" style={{ color: COLORS.text.secondary }} />
                          <p className="text-xs font-medium" style={{ color: COLORS.text.secondary }}>Total Assessment</p>
                        </div>
                        <p className="text-lg font-bold" style={{ color: COLORS.text.primary }}>{formatCurrency(property.total_assessed_value)}</p>
                      </div>
                    </div>
                  </div>

                  {/* Tax Breakdown */}
                  <div className="border-t pt-8" style={{ borderColor: COLORS.border.light }}>
                    <h4 className="text-xs font-semibold uppercase tracking-wide mb-6" style={{ color: COLORS.text.secondary }}>Tax Breakdown</h4>
                    <div className="grid grid-cols-3 gap-4">
                      <div className="p-4 rounded-xl"
                           style={{ backgroundColor: COLORS.background.hover, border: `1px solid ${COLORS.border.light}` }}>
                        <p className="text-xs font-medium" style={{ color: COLORS.text.secondary }}>Basic Tax</p>
                        <p className="text-lg font-bold mt-2" style={{ color: COLORS.text.primary }}>
                          {formatCurrency(property.land_basic_tax || (property.land_assessed_value * 0.01))}
                        </p>
                      </div>
                      <div className="p-4 rounded-xl"
                           style={{ backgroundColor: COLORS.background.hover, border: `1px solid ${COLORS.border.light}` }}>
                        <p className="text-xs font-medium" style={{ color: COLORS.text.secondary }}>SEF Tax</p>
                        <p className="text-lg font-bold mt-2" style={{ color: COLORS.text.primary }}>
                          {formatCurrency(property.land_sef_tax || (property.land_assessed_value * 0.01))}
                        </p>
                      </div>
                      <div className="p-4 rounded-xl border-2"
                           style={{ 
                             backgroundColor: COLORS.primary.bg,
                             borderColor: COLORS.primary.border
                           }}>
                        <p className="text-xs font-medium" style={{ color: COLORS.primary.main }}>Annual Land Tax</p>
                        <p className="text-xl font-bold mt-2" style={{ color: COLORS.primary.main }}>{formatCurrency(totalLandTax)}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Building Assessment */}
              <div className="space-y-8">
                <div>
                  <div className="flex items-center justify-between mb-8">
                    <h3 className="text-sm font-semibold flex items-center gap-3">
                      <div className="p-2.5 rounded-lg"
                           style={{ backgroundColor: COLORS.success.bg }}>
                        <Building className="w-4 h-4" style={{ color: COLORS.success.main }} />
                      </div>
                      <span style={{ color: COLORS.text.primary }}>Building Assessment</span>
                    </h3>
                    <div className="flex items-center gap-3">
                      <span className="text-xs font-medium" style={{ color: COLORS.success.main }}>ID: {buildingTdn || 'N/A'}</span>
                      {buildingYearBuilt && (
                        <span className="text-xs font-medium" style={{ color: COLORS.success.main }}>• Built: {buildingYearBuilt}</span>
                      )}
                    </div>
                  </div>
                  
                  {buildings.length > 0 ? (
                    <>
                      {/* Property Details */}
                      <div className="mb-8">
                        <h4 className="text-xs font-semibold uppercase tracking-wide mb-4" style={{ color: COLORS.text.secondary }}>Property Details</h4>
                        <div className="grid grid-cols-2 gap-4">
                          <div className="p-4 rounded-xl"
                               style={{ backgroundColor: COLORS.background.hover, border: `1px solid ${COLORS.border.light}` }}>
                            <div className="flex items-center gap-2 mb-3">
                              <Ruler className="w-4 h-4" style={{ color: COLORS.text.secondary }} />
                              <p className="text-xs font-medium" style={{ color: COLORS.text.secondary }}>Total Floor Area</p>
                            </div>
                            <p className="text-lg font-bold" style={{ color: COLORS.text.primary }}>{totalFloorArea} sqm</p>
                          </div>
                          <div className="p-4 rounded-xl"
                               style={{ backgroundColor: COLORS.background.hover, border: `1px solid ${COLORS.border.light}` }}>
                            <div className="flex items-center gap-2 mb-3">
                              <Hash className="w-4 h-4" style={{ color: COLORS.text.secondary }} />
                              <p className="text-xs font-medium" style={{ color: COLORS.text.secondary }}>Construction Type</p>
                            </div>
                            <p className="text-lg font-bold" style={{ color: COLORS.text.primary }}>
                              {[...new Set(buildings.map(b => b.construction_type))].join(', ')}
                            </p>
                          </div>
                        </div>
                      </div>

                      {/* Valuation Details */}
                      <div className="mb-8">
                        <h4 className="text-xs font-semibold uppercase tracking-wide mb-4" style={{ color: COLORS.text.secondary }}>Valuation Details</h4>
                        <div className="grid grid-cols-2 gap-4">
                          <div className="p-4 rounded-xl"
                               style={{ backgroundColor: COLORS.primary.bg, border: `1px solid ${COLORS.primary.border}` }}>
                            <div className="flex items-center gap-2 mb-3">
                              <DollarSign className="w-4 h-4" style={{ color: COLORS.primary.main }} />
                              <p className="text-xs font-medium" style={{ color: COLORS.primary.main }}>Total Market Value</p>
                            </div>
                            <p className="text-lg font-bold" style={{ color: COLORS.primary.main }}>{formatCurrency(totalBuildingMarketValue)}</p>
                          </div>
                          <div className="p-4 rounded-xl"
                               style={{ backgroundColor: COLORS.success.bg, border: `1px solid ${COLORS.success.border}` }}>
                            <div className="flex items-center gap-2 mb-3">
                              <BarChart className="w-4 h-4" style={{ color: COLORS.success.main }} />
                              <p className="text-xs font-medium" style={{ color: COLORS.success.main }}>Total Assessed Value</p>
                            </div>
                            <p className="text-lg font-bold" style={{ color: COLORS.success.main }}>{formatCurrency(totalBuildingAssessedValue)}</p>
                          </div>
                          <div className="p-4 rounded-xl"
                               style={{ backgroundColor: COLORS.purple.bg, border: `1px solid ${COLORS.purple.border}` }}>
                            <div className="flex items-center gap-2 mb-3">
                              <Percent className="w-4 h-4" style={{ color: COLORS.purple.main }} />
                              <p className="text-xs font-medium" style={{ color: COLORS.purple.main }}>Avg. Assessment Level</p>
                            </div>
                            <p className="text-lg font-bold" style={{ color: COLORS.purple.main }}>{avgAssessmentLevel}%</p>
                          </div>
                          <div className="p-4 rounded-xl"
                               style={{ backgroundColor: COLORS.background.hover, border: `1px solid ${COLORS.border.light}` }}>
                            <div className="flex items-center gap-2 mb-3">
                              <Maximize2 className="w-4 h-4" style={{ color: COLORS.text.secondary }} />
                              <p className="text-xs font-medium" style={{ color: COLORS.text.secondary }}>Total Assessment</p>
                            </div>
                            <p className="text-lg font-bold" style={{ color: COLORS.text.primary }}>{formatCurrency(totalBuildingAssessedValue)}</p>
                          </div>
                        </div>
                      </div>

                      {/* Tax Breakdown */}
                      <div className="border-t pt-8" style={{ borderColor: COLORS.border.light }}>
                        <h4 className="text-xs font-semibold uppercase tracking-wide mb-6" style={{ color: COLORS.text.secondary }}>Tax Breakdown</h4>
                        <div className="grid grid-cols-3 gap-4">
                          <div className="p-4 rounded-xl"
                               style={{ backgroundColor: COLORS.background.hover, border: `1px solid ${COLORS.border.light}` }}>
                            <p className="text-xs font-medium" style={{ color: COLORS.text.secondary }}>Total Basic Tax</p>
                            <p className="text-lg font-bold mt-2" style={{ color: COLORS.text.primary }}>
                              {formatCurrency(buildings.reduce((sum, b) => sum + (parseFloat(b.basic_tax_amount) || 0), 0))}
                            </p>
                          </div>
                          <div className="p-4 rounded-xl"
                               style={{ backgroundColor: COLORS.background.hover, border: `1px solid ${COLORS.border.light}` }}>
                            <p className="text-xs font-medium" style={{ color: COLORS.text.secondary }}>Total SEF Tax</p>
                            <p className="text-lg font-bold mt-2" style={{ color: COLORS.text.primary }}>
                              {formatCurrency(buildings.reduce((sum, b) => sum + (parseFloat(b.sef_tax_amount) || 0), 0))}
                            </p>
                          </div>
                          <div className="p-4 rounded-xl border-2"
                               style={{ 
                                 backgroundColor: COLORS.success.bg,
                                 borderColor: COLORS.success.border
                               }}>
                            <p className="text-xs font-medium" style={{ color: COLORS.success.main }}>Total Building Tax</p>
                            <p className="text-xl font-bold mt-2" style={{ color: COLORS.success.main }}>{formatCurrency(totalBuildingTax)}</p>
                          </div>
                        </div>
                      </div>
                    </>
                  ) : (
                    <div className="text-center py-12">
                      <div className="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6"
                           style={{ backgroundColor: COLORS.background.hover }}>
                        <Building className="w-10 h-10" style={{ color: COLORS.text.secondary }} />
                      </div>
                      <h3 className="text-lg font-medium mb-3" style={{ color: COLORS.text.primary }}>No Buildings Registered</h3>
                      <p className="text-sm" style={{ color: COLORS.text.secondary }}>This property has no building structures</p>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Enhanced Tax Summary */}
        <div className="rounded-2xl shadow-sm overflow-hidden"
             style={{ 
               backgroundColor: COLORS.background.card,
               border: `1px solid ${COLORS.border.light}`
             }}>
          <div className="px-8 py-6 border-b"
               style={{ borderColor: COLORS.border.light }}>
            <div className="flex items-center gap-4">
              <div className="p-2.5 rounded-lg"
                   style={{ backgroundColor: COLORS.warning.bg }}>
                <DollarSign className="w-5 h-5" style={{ color: COLORS.warning.main }} />
              </div>
              <div>
                <h2 className="text-base font-semibold" style={{ color: COLORS.text.primary }}>Tax Summary</h2>
                <p className="text-sm mt-1" style={{ color: COLORS.text.secondary }}>Overview of tax contributions</p>
              </div>
            </div>
          </div>
          <div className="p-6">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div className="p-5 rounded-xl border-2 transition-all duration-300 hover:shadow-md"
                   style={{ 
                     backgroundColor: COLORS.primary.bg,
                     borderColor: COLORS.primary.border
                   }}>
                <div className="flex items-center justify-between mb-4">
                  <p className="text-sm font-medium" style={{ color: COLORS.primary.main }}>Land Tax</p>
                  <Home className="w-5 h-5" style={{ color: COLORS.primary.main }} />
                </div>
                <p className="text-2xl font-bold" style={{ color: COLORS.primary.main }}>{formatCurrency(totalLandTax)}</p>
                <div className="h-2 bg-blue-100 rounded-full overflow-hidden mt-4">
                  <div 
                    className="h-2 rounded-full" 
                    style={{ 
                      width: `${(totalLandTax / totalAnnualTax) * 100}%`,
                      backgroundColor: COLORS.primary.main 
                    }}
                  ></div>
                </div>
                <p className="text-xs mt-2" style={{ color: COLORS.primary.main }}>
                  {Math.round((totalLandTax / totalAnnualTax) * 100)}% of total
                </p>
              </div>

              <div className="p-5 rounded-xl border-2 transition-all duration-300 hover:shadow-md"
                   style={{ 
                     backgroundColor: COLORS.success.bg,
                     borderColor: COLORS.success.border
                   }}>
                <div className="flex items-center justify-between mb-4">
                  <p className="text-sm font-medium" style={{ color: COLORS.success.main }}>Building Tax</p>
                  <Building className="w-5 h-5" style={{ color: COLORS.success.main }} />
                </div>
                <p className="text-2xl font-bold" style={{ color: COLORS.success.main }}>{formatCurrency(totalBuildingTax)}</p>
                <div className="h-2 bg-green-100 rounded-full overflow-hidden mt-4">
                  <div 
                    className="h-2 rounded-full" 
                    style={{ 
                      width: `${(totalBuildingTax / totalAnnualTax) * 100}%`,
                      backgroundColor: COLORS.success.main 
                    }}
                  ></div>
                </div>
                <p className="text-xs mt-2" style={{ color: COLORS.success.main }}>
                  {Math.round((totalBuildingTax / totalAnnualTax) * 100)}% of total
                </p>
              </div>

              <div className="p-5 rounded-xl border-2 transition-all duration-300 hover:shadow-md"
                   style={{ 
                     backgroundColor: COLORS.purple.bg,
                     borderColor: COLORS.purple.border
                   }}>
                <div className="flex items-center justify-between mb-4">
                  <p className="text-sm font-medium" style={{ color: COLORS.purple.main }}>Total Annual Tax</p>
                  <DollarSign className="w-5 h-5" style={{ color: COLORS.purple.main }} />
                </div>
                <p className="text-3xl font-bold" style={{ color: COLORS.purple.main }}>{formatCurrency(totalAnnualTax)}</p>
                <div className="mt-6 space-y-3">
                  <div className="flex items-center justify-between text-sm">
                    <span style={{ color: COLORS.text.secondary }}>Quarterly:</span>
                    <span className="font-semibold" style={{ color: COLORS.text.primary }}>{formatCurrency(quarterlyAmount)}</span>
                  </div>
                  <div className="flex items-center justify-between text-sm">
                    <span style={{ color: COLORS.text.secondary }}>Paid Quarters:</span>
                    <span className="font-semibold" style={{ color: COLORS.success.main }}>
                      {quarterlyTaxes.filter(t => t.payment_status === 'paid').length}/4
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Enhanced Quarterly Taxes Table */}
        <div className="rounded-2xl shadow-sm overflow-hidden"
             style={{ 
               backgroundColor: COLORS.background.card,
               border: `1px solid ${COLORS.border.light}`
             }}>
          <div className="px-8 py-6 border-b"
               style={{ borderColor: COLORS.border.light }}>
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-4 mb-4 sm:mb-0">
                <div className="p-2.5 rounded-lg"
                     style={{ backgroundColor: COLORS.purple.bg }}>
                  <Calendar className="w-5 h-5" style={{ color: COLORS.purple.main }} />
                </div>
                <div>
                  <h2 className="text-base font-semibold" style={{ color: COLORS.text.primary }}>Quarterly Tax Payments</h2>
                  <p className="text-sm mt-1" style={{ color: COLORS.text.secondary }}>Payment history and current status</p>
                </div>
              </div>
              <div className="flex flex-wrap gap-2">
                <span className="px-3 py-1.5 rounded-full text-xs font-medium inline-flex items-center gap-1.5 transition-all duration-200 hover:scale-105"
                      style={{ 
                        backgroundColor: COLORS.success.bg,
                        color: COLORS.success.main,
                        border: `1px solid ${COLORS.success.border}`
                      }}>
                  <CheckCheck className="w-3 h-3" />
                  Paid: {quarterlyTaxes.filter(t => t.payment_status === 'paid').length}
                </span>
                <span className="px-3 py-1.5 rounded-full text-xs font-medium inline-flex items-center gap-1.5 transition-all duration-200 hover:scale-105"
                      style={{ 
                        backgroundColor: COLORS.warning.bg,
                        color: COLORS.warning.main,
                        border: `1px solid ${COLORS.warning.border}`
                      }}>
                  <Clock className="w-3 h-3" />
                  Pending: {quarterlyTaxes.filter(t => t.payment_status === 'pending').length}
                </span>
                <span className="px-3 py-1.5 rounded-full text-xs font-medium inline-flex items-center gap-1.5 transition-all duration-200 hover:scale-105"
                      style={{ 
                        backgroundColor: COLORS.danger.bg,
                        color: COLORS.danger.main,
                        border: `1px solid ${COLORS.danger.border}`
                      }}>
                  <AlertCircle className="w-3 h-3" />
                  Overdue: {quarterlyTaxes.filter(t => t.payment_status === 'overdue').length}
                </span>
              </div>
            </div>
          </div>
          
          <div className="p-6">
            {quarterlyTaxesWithTotals.length > 0 ? (
              <div className="overflow-x-auto rounded-lg border"
                   style={{ borderColor: COLORS.border.light }}>
                <table className="w-full text-sm">
                  <thead>
                    <tr style={{ backgroundColor: COLORS.background.hover }}>
                      <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider" 
                          style={{ color: COLORS.text.secondary, borderBottom: `1px solid ${COLORS.border.light}` }}>
                        Quarter
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider" 
                          style={{ color: COLORS.text.secondary, borderBottom: `1px solid ${COLORS.border.light}` }}>
                        Due Date
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider" 
                          style={{ color: COLORS.text.secondary, borderBottom: `1px solid ${COLORS.border.light}` }}>
                        Base Amount
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider" 
                          style={{ color: COLORS.text.secondary, borderBottom: `1px solid ${COLORS.border.light}` }}>
                        Penalty
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider" 
                          style={{ color: COLORS.text.secondary, borderBottom: `1px solid ${COLORS.border.light}` }}>
                        Total
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider" 
                          style={{ color: COLORS.text.secondary, borderBottom: `1px solid ${COLORS.border.light}` }}>
                        Status
                      </th>
                      <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider" 
                          style={{ color: COLORS.text.secondary, borderBottom: `1px solid ${COLORS.border.light}` }}>
                        Payment Date
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y" style={{ borderColor: COLORS.border.light }}>
                    {quarterlyTaxesWithTotals.map((tax) => {
                      const status = getPaymentStatus(tax.payment_status);
                      const hasPenalty = tax.penalty_amount > 0;
                      const isCurrentQuarter = 
                        tax.year == new Date().getFullYear() && 
                        parseInt(tax.quarter.replace('Q', '')) === Math.floor(new Date().getMonth() / 3) + 1;
                      
                      return (
                        <tr key={tax.id} className="transition-all duration-200 hover:bg-gray-50"
                            style={{ 
                              backgroundColor: isCurrentQuarter ? COLORS.primary.bg : 'transparent',
                              borderBottom: `1px solid ${COLORS.border.light}`
                            }}>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="font-medium" style={{ color: COLORS.text.primary }}>{tax.quarter} {tax.year}</div>
                            {isCurrentQuarter && (
                              <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium mt-2"
                                    style={{ 
                                      backgroundColor: COLORS.primary.main,
                                      color: COLORS.text.white
                                    }}>
                                Current Quarter
                              </span>
                            )}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div style={{ color: COLORS.text.primary }}>{formatDate(tax.due_date)}</div>
                            {tax.days_late > 0 && (
                              <div className="text-xs font-medium mt-1.5" style={{ color: COLORS.danger.main }}>
                                {tax.days_late} days late
                              </div>
                            )}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="font-medium" style={{ color: COLORS.text.primary }}>{formatCurrency(tax.total_quarterly_tax)}</div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            {hasPenalty ? (
                              <div className="flex items-center">
                                <AlertCircle className="w-4 h-4 mr-2" style={{ color: COLORS.danger.main }} />
                                <span className="font-medium" style={{ color: COLORS.danger.main }}>{formatCurrency(tax.penalty_amount)}</span>
                              </div>
                            ) : (
                              <span className="text-gray-400">-</span>
                            )}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="font-bold" style={{ color: COLORS.text.primary }}>
                              {formatCurrency(tax.totalWithPenalty)}
                            </div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className={`inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold border transition-all duration-200 hover:scale-105 ${status.color}`}
                                 style={{ borderColor: status.border }}>
                              {status.icon}
                              <span>{status.text}</span>
                            </div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div style={{ color: COLORS.text.primary }}>
                              {tax.payment_date ? formatDate(tax.payment_date) : '-'}
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="text-center py-12">
                <div className="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6"
                     style={{ backgroundColor: COLORS.background.hover }}>
                  <CreditCard className="w-10 h-10" style={{ color: COLORS.text.secondary }} />
                </div>
                <h3 className="text-base font-semibold mb-3" style={{ color: COLORS.text.primary }}>No Tax Records</h3>
                <p className="text-sm" style={{ color: COLORS.text.secondary }}>No quarterly tax payments have been recorded for this property.</p>
              </div>
            )}
          </div>
        </div>

        {/* Enhanced Footer Summary */}
        <div className="rounded-2xl shadow-sm overflow-hidden"
             style={{ 
               backgroundColor: COLORS.background.card,
               border: `1px solid ${COLORS.border.light}`
             }}>
          <div className="px-8 py-6 border-b"
               style={{ borderColor: COLORS.border.light }}>
            <h3 className="text-base font-semibold" style={{ color: COLORS.text.primary }}>Record Information</h3>
          </div>
          <div className="p-6">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div className="p-5 rounded-xl transition-all duration-300 hover:shadow-md"
                   style={{ 
                     backgroundColor: COLORS.background.hover,
                     border: `1px solid ${COLORS.border.light}`
                   }}>
                <div className="flex items-center gap-3 mb-3">
                  <div className="p-2.5 rounded-lg"
                       style={{ backgroundColor: COLORS.background.card }}>
                    <Tag className="w-4 h-4" style={{ color: COLORS.text.secondary }} />
                  </div>
                  <div>
                    <p className="text-xs font-medium" style={{ color: COLORS.text.secondary }}>Registration Date</p>
                    <p className="text-base font-bold mt-1" style={{ color: COLORS.text.primary }}>{formatDate(property.created_at)}</p>
                  </div>
                </div>
                <p className="text-xs" style={{ color: COLORS.text.secondary }}>Date when property was registered</p>
              </div>
              
              <div className="p-5 rounded-xl transition-all duration-300 hover:shadow-md"
                   style={{ 
                     backgroundColor: COLORS.background.hover,
                     border: `1px solid ${COLORS.border.light}`
                   }}>
                <div className="flex items-center gap-3 mb-3">
                  <div className="p-2.5 rounded-lg"
                       style={{ backgroundColor: COLORS.background.card }}>
                    <FileText className="w-4 h-4" style={{ color: COLORS.text.secondary }} />
                  </div>
                  <div>
                    <p className="text-xs font-medium" style={{ color: COLORS.text.secondary }}>Last Updated</p>
                    <p className="text-base font-bold mt-1" style={{ color: COLORS.text.primary }}>{formatDate(property.updated_at || property.created_at)}</p>
                  </div>
                </div>
                <p className="text-xs" style={{ color: COLORS.text.secondary }}>Date of last update</p>
              </div>
              
              <div className="p-5 rounded-xl border-2 transition-all duration-300 hover:shadow-md"
                   style={{ 
                     backgroundColor: COLORS.success.bg,
                     borderColor: COLORS.success.border
                   }}>
                <div className="flex items-center gap-3 mb-3">
                  <div className="p-2.5 rounded-lg"
                       style={{ backgroundColor: COLORS.success.main }}>
                    <CheckCircle className="w-4 h-4" style={{ color: COLORS.text.white }} />
                  </div>
                  <div>
                    <p className="text-xs font-medium" style={{ color: COLORS.success.main }}>Status</p>
                    <p className="text-base font-bold mt-1" style={{ color: COLORS.success.main }}>{property.status?.toUpperCase() || 'APPROVED'}</p>
                  </div>
                </div>
                <p className="text-xs" style={{ color: COLORS.success.main }}>Current registration status</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
} 