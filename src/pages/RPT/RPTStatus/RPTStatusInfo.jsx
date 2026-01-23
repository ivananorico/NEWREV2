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
          color: "bg-green-50 text-green-700 border border-green-200",
          icon: <CheckCircle className="w-3 h-3 mr-1" />
        };
      case 'overdue': 
        return { 
          text: "DELINQUENT", 
          color: "bg-red-50 text-red-700 border border-red-200",
          icon: <AlertCircle className="w-3 h-3 mr-1" />
        };
      default: 
        return { 
          text: "PENDING", 
          color: "bg-yellow-50 text-yellow-700 border border-yellow-200",
          icon: <Clock className="w-3 h-3 mr-1" />
        };
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600 font-medium">Loading property details...</p>
        </div>
      </div>
    );
  }

  if (!property) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-lg p-8 text-center max-w-md shadow">
          <AlertCircle className="w-16 h-16 text-red-400 mx-auto mb-4" />
          <h2 className="text-xl font-bold text-gray-900 mb-2">Property Not Found</h2>
          <p className="text-gray-600 mb-6">The requested property details could not be loaded.</p>
          <button
            onClick={() => navigate(-1)}
            className="w-full px-6 py-3 bg-blue-600 text-white font-medium rounded hover:bg-blue-700"
          >
            Return to Property Registry
          </button>
        </div>
      </div>
    );
  }

  // Calculate totals
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
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b shadow">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between py-6">
            <div className="flex items-center space-x-4 mb-4 sm:mb-0">
              <button
                onClick={() => navigate(-1)}
                className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
              >
                <ArrowLeft className="w-5 h-5" />
              </button>
              <div>
                <div className="flex items-center gap-2">
                  <FileSignature className="w-5 h-5 text-blue-600" />
                  <h1 className="text-2xl font-bold text-gray-900">Property Tax Record</h1>
                </div>
                <div className="flex items-center gap-2 mt-1">
                  <span className="font-mono bg-gray-100 px-3 py-1 rounded text-sm">{property.reference_number}</span>
                  <span className="text-gray-600 text-sm">• {property.owner_name}</span>
                </div>
              </div>
            </div>
            <div className="flex items-center space-x-3">
              <button
                onClick={() => window.print()}
                className="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors"
              >
                <Printer className="w-4 h-4" />
                <span className="text-sm font-medium">Print</span>
              </button>
              <button
                onClick={fetchPropertyDetails}
                className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
              >
                <FileText className="w-4 h-4" />
                <span className="text-sm font-medium">Refresh</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
          <div className="bg-white border rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
            <div className="flex items-start justify-between mb-4">
              <div className="p-3 bg-blue-50 rounded-xl">
                <DollarSign className="w-6 h-6 text-blue-600" />
              </div>
              <div className="text-right">
                <p className="text-sm font-medium text-gray-500">Annual Tax</p>
                <p className="text-2xl font-bold text-gray-900 mt-1">{formatCurrency(totalAnnualTax)}</p>
              </div>
            </div>
            <div className="text-xs text-gray-500 font-medium">Current fiscal year assessment</div>
          </div>

          <div className="bg-white border rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
            <div className="flex items-start justify-between mb-4">
              <div className="p-3 bg-green-50 rounded-xl">
                <TrendingUp className="w-6 h-6 text-green-600" />
              </div>
              <div className="text-right">
                <p className="text-sm font-medium text-gray-500">Collection Rate</p>
                <p className="text-2xl font-bold text-gray-900 mt-1">{collectionRate}%</p>
              </div>
            </div>
            <div className="text-xs text-gray-500 font-medium">{formatCurrency(totalPaid)} collected</div>
          </div>

          <div className="bg-white border rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
            <div className="flex items-start justify-between mb-4">
              <div className="p-3 bg-yellow-50 rounded-xl">
                <Calendar className="w-6 h-6 text-yellow-600" />
              </div>
              <div className="text-right">
                <p className="text-sm font-medium text-gray-500">Quarterly Tax</p>
                <p className="text-2xl font-bold text-gray-900 mt-1">{formatCurrency(quarterlyAmount)}</p>
              </div>
            </div>
            <div className="text-xs text-gray-500 font-medium">Per quarter installment</div>
          </div>
        </div>

        {/* Combined Property & Owner Information */}
        <div className="bg-white border rounded-xl shadow-sm mb-8">
          <div className="px-6 py-5 border-b">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="p-2 bg-blue-50 rounded-lg">
                  <MapPin className="w-5 h-5 text-blue-600" />
                </div>
                <div>
                  <h2 className="text-lg font-semibold text-gray-900">Property & Owner Information</h2>
                  <p className="text-sm text-gray-500 mt-1">Complete property details and owner information</p>
                </div>
              </div>
              <span className={`px-3 py-1.5 text-sm font-medium rounded-full ${property.has_building === 'yes' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                {property.has_building === 'yes' ? 'With Building' : 'Vacant Lot'}
              </span>
            </div>
          </div>
          <div className="p-6">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
              {/* Property Information */}
              <div className="space-y-6">
                <div>
                  <h3 className="text-sm font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <div className="p-2 bg-gray-100 rounded">
                      <Home className="w-4 h-4" />
                    </div>
                    Property Location
                  </h3>
                  <div className="space-y-4">
                    <div>
                      <p className="text-sm font-medium text-gray-500 mb-1">Complete Address</p>
                      <p className="text-gray-900 font-medium">{property.lot_location}</p>
                      <p className="text-sm text-gray-600 mt-1">
                        {property.barangay}, District {property.district}, {property.city}, {property.province}
                      </p>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <p className="text-sm font-medium text-gray-500 mb-1">Property Type</p>
                        <p className="text-gray-900 font-medium">{property.property_type || "Residential"}</p>
                      </div>
                      <div>
                        <p className="text-sm font-medium text-gray-500 mb-1">Land Area</p>
                        <p className="text-gray-900 font-medium">{property.land_area_sqm} sqm</p>
                      </div>
                      <div>
                        <p className="text-sm font-medium text-gray-500 mb-1">Zip Code</p>
                        <p className="text-gray-900 font-medium">{property.zip_code || 'N/A'}</p>
                      </div>
                      <div>
                        <p className="text-sm font-medium text-gray-500 mb-1">Registered</p>
                        <p className="text-gray-900 font-medium">{formatDate(property.created_at)}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Owner Information */}
              <div className="space-y-6">
                <div>
                  <h3 className="text-sm font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <div className="p-2 bg-gray-100 rounded">
                      <User className="w-4 h-4" />
                    </div>
                    Property Owner
                  </h3>
                  <div className="space-y-4">
                    <div>
                      <p className="text-sm font-medium text-gray-500 mb-1">Full Name</p>
                      <p className="text-xl font-bold text-gray-900">{property.owner_name}</p>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <p className="text-sm font-medium text-gray-500 mb-1">Sex</p>
                        <p className="text-gray-900 font-medium">{property.sex ? property.sex.charAt(0).toUpperCase() + property.sex.slice(1) : 'N/A'}</p>
                      </div>
                      <div>
                        <p className="text-sm font-medium text-gray-500 mb-1">Marital Status</p>
                        <p className="text-gray-900 font-medium">{property.marital_status ? property.marital_status.charAt(0).toUpperCase() + property.marital_status.slice(1) : 'N/A'}</p>
                      </div>
                      <div>
                        <p className="text-sm font-medium text-gray-500 mb-1">Birthdate</p>
                        <p className="text-gray-900 font-medium">{formatDate(property.birthdate)}</p>
                      </div>
                      <div>
                        <p className="text-sm font-medium text-gray-500 mb-1">Status</p>
                        <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                          ACTIVE
                        </span>
                      </div>
                    </div>
                    <div className="space-y-3">
                      <div>
                        <p className="text-sm font-medium text-gray-500 mb-1">Contact Information</p>
                        <div className="flex flex-col gap-2">
                          <div className="flex items-center text-sm">
                            <Phone className="w-4 h-4 text-gray-400 mr-2" />
                            <span className="font-medium text-gray-900">{property.phone || 'N/A'}</span>
                          </div>
                          <div className="flex items-center text-sm">
                            <Mail className="w-4 h-4 text-gray-400 mr-2" />
                            <span className="font-medium text-gray-900">{property.email || 'N/A'}</span>
                          </div>
                        </div>
                      </div>
                      <div>
                        <p className="text-sm font-medium text-gray-500 mb-1">Registered Address</p>
                        <p className="text-gray-900 font-medium">{property.owner_address || 'N/A'}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Perfectly Aligned Land & Building Assessment */}
        <div className="bg-white border rounded-xl shadow-sm mb-8">
          <div className="px-6 py-5 border-b">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="p-2 bg-purple-50 rounded-lg">
                  <Landmark className="w-5 h-5 text-purple-600" />
                </div>
                <div>
                  <h2 className="text-lg font-semibold text-gray-900">Property Assessment Details</h2>
                  <p className="text-sm text-gray-500 mt-1">Detailed land and building valuation assessment</p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-sm font-medium text-gray-600">Land TDN: {property.land_tdn || 'N/A'}</span>
                {buildings.length > 0 && (
                  <span className="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">
                    {buildings.length} building{buildings.length !== 1 ? 's' : ''}
                  </span>
                )}
              </div>
            </div>
          </div>
          <div className="p-6">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
              
              {/* Land Assessment */}
              <div className="space-y-6">
                <div>
                  <div className="flex items-center justify-between mb-6">
                    <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
                      <div className="p-2 bg-blue-50 rounded">
                        <Home className="w-4 h-4 text-blue-600" />
                      </div>
                      Land Assessment
                    </h3>
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-medium text-blue-600">ID: {property.land_tdn || 'N/A'}</span>
                      {buildingYearBuilt && (
                        <span className="text-xs font-medium text-blue-600">• Built: {buildingYearBuilt}</span>
                      )}
                    </div>
                  </div>
                  
                  {/* Property Details */}
                  <div className="mb-6">
                    <h4 className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-3">Property Details</h4>
                    <div className="grid grid-cols-2 gap-4">
                      <div className="bg-gray-50 p-4 rounded-lg">
                        <div className="flex items-center gap-2 mb-2">
                          <Ruler className="w-4 h-4 text-gray-400" />
                          <p className="text-xs font-medium text-gray-500">Total Area</p>
                        </div>
                        <p className="text-lg font-bold text-gray-900">{property.land_area_sqm} sqm</p>
                      </div>
                      <div className="bg-gray-50 p-4 rounded-lg">
                        <div className="flex items-center gap-2 mb-2">
                          <Hash className="w-4 h-4 text-gray-400" />
                          <p className="text-xs font-medium text-gray-500">Property Type</p>
                        </div>
                        <p className="text-lg font-bold text-gray-900">{property.property_type || "Residential"}</p>
                      </div>
                    </div>
                  </div>

                  {/* Valuation Details */}
                  <div className="mb-6">
                    <h4 className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-3">Valuation Details</h4>
                    <div className="grid grid-cols-2 gap-4">
                      <div className="bg-blue-50 p-4 rounded-lg">
                        <div className="flex items-center gap-2 mb-2">
                          <DollarSign className="w-4 h-4 text-blue-400" />
                          <p className="text-xs font-medium text-gray-500">Market Value</p>
                        </div>
                        <p className="text-lg font-bold text-blue-600">{formatCurrency(property.land_market_value)}</p>
                      </div>
                      <div className="bg-green-50 p-4 rounded-lg">
                        <div className="flex items-center gap-2 mb-2">
                          <BarChart className="w-4 h-4 text-green-400" />
                          <p className="text-xs font-medium text-gray-500">Assessed Value</p>
                        </div>
                        <p className="text-lg font-bold text-green-600">{formatCurrency(property.land_assessed_value)}</p>
                      </div>
                      <div className="bg-purple-50 p-4 rounded-lg">
                        <div className="flex items-center gap-2 mb-2">
                          <Percent className="w-4 h-4 text-purple-400" />
                          <p className="text-xs font-medium text-gray-500">Assessment Level</p>
                        </div>
                        <p className="text-lg font-bold text-purple-600">{property.assessment_level}%</p>
                      </div>
                      <div className="bg-gray-50 p-4 rounded-lg">
                        <div className="flex items-center gap-2 mb-2">
                          <Maximize2 className="w-4 h-4 text-gray-400" />
                          <p className="text-xs font-medium text-gray-500">Total Assessment</p>
                        </div>
                        <p className="text-lg font-bold text-gray-900">{formatCurrency(property.total_assessed_value)}</p>
                      </div>
                    </div>
                  </div>

                  {/* Tax Breakdown */}
                  <div className="border-t pt-6">
                    <h4 className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Tax Breakdown</h4>
                    <div className="grid grid-cols-3 gap-4">
                      <div className="bg-gray-50 p-4 rounded-lg">
                        <p className="text-xs font-medium text-gray-500">Basic Tax</p>
                        <p className="text-lg font-bold text-gray-900 mt-1">
                          {formatCurrency(property.land_basic_tax || (property.land_assessed_value * 0.01))}
                        </p>
                      </div>
                      <div className="bg-gray-50 p-4 rounded-lg">
                        <p className="text-xs font-medium text-gray-500">SEF Tax</p>
                        <p className="text-lg font-bold text-gray-900 mt-1">
                          {formatCurrency(property.land_sef_tax || (property.land_assessed_value * 0.01))}
                        </p>
                      </div>
                      <div className="bg-blue-100 p-4 rounded-lg border border-blue-200">
                        <p className="text-xs font-medium text-blue-600">Annual Land Tax</p>
                        <p className="text-xl font-bold text-blue-600 mt-1">{formatCurrency(totalLandTax)}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Building Assessment */}
              <div className="space-y-6">
                <div>
                  <div className="flex items-center justify-between mb-6">
                    <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
                      <div className="p-2 bg-green-50 rounded">
                        <Building className="w-4 h-4 text-green-600" />
                      </div>
                      Building Assessment
                    </h3>
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-medium text-green-600">ID: {buildingTdn || 'N/A'}</span>
                      {buildingYearBuilt && (
                        <span className="text-xs font-medium text-green-600">• Built: {buildingYearBuilt}</span>
                      )}
                    </div>
                  </div>
                  
                  {buildings.length > 0 ? (
                    <>
                      {/* Property Details */}
                      <div className="mb-6">
                        <h4 className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-3">Property Details</h4>
                        <div className="grid grid-cols-2 gap-4">
                          <div className="bg-gray-50 p-4 rounded-lg">
                            <div className="flex items-center gap-2 mb-2">
                              <Ruler className="w-4 h-4 text-gray-400" />
                              <p className="text-xs font-medium text-gray-500">Total Floor Area</p>
                            </div>
                            <p className="text-lg font-bold text-gray-900">{totalFloorArea} sqm</p>
                          </div>
                          <div className="bg-gray-50 p-4 rounded-lg">
                            <div className="flex items-center gap-2 mb-2">
                              <Hash className="w-4 h-4 text-gray-400" />
                              <p className="text-xs font-medium text-gray-500">Construction Type</p>
                            </div>
                            <p className="text-lg font-bold text-gray-900">
                              {[...new Set(buildings.map(b => b.construction_type))].join(', ')}
                            </p>
                          </div>
                        </div>
                      </div>

                      {/* Valuation Details */}
                      <div className="mb-6">
                        <h4 className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-3">Valuation Details</h4>
                        <div className="grid grid-cols-2 gap-4">
                          <div className="bg-blue-50 p-4 rounded-lg">
                            <div className="flex items-center gap-2 mb-2">
                              <DollarSign className="w-4 h-4 text-blue-400" />
                              <p className="text-xs font-medium text-gray-500">Total Market Value</p>
                            </div>
                            <p className="text-lg font-bold text-blue-600">{formatCurrency(totalBuildingMarketValue)}</p>
                          </div>
                          <div className="bg-green-50 p-4 rounded-lg">
                            <div className="flex items-center gap-2 mb-2">
                              <BarChart className="w-4 h-4 text-green-400" />
                              <p className="text-xs font-medium text-gray-500">Total Assessed Value</p>
                            </div>
                            <p className="text-lg font-bold text-green-600">{formatCurrency(totalBuildingAssessedValue)}</p>
                          </div>
                          <div className="bg-purple-50 p-4 rounded-lg">
                            <div className="flex items-center gap-2 mb-2">
                              <Percent className="w-4 h-4 text-purple-400" />
                              <p className="text-xs font-medium text-gray-500">Avg. Assessment Level</p>
                            </div>
                            <p className="text-lg font-bold text-purple-600">{avgAssessmentLevel}%</p>
                          </div>
                          <div className="bg-gray-50 p-4 rounded-lg">
                            <div className="flex items-center gap-2 mb-2">
                              <Maximize2 className="w-4 h-4 text-gray-400" />
                              <p className="text-xs font-medium text-gray-500">Total Assessment</p>
                            </div>
                            <p className="text-lg font-bold text-gray-900">{formatCurrency(totalBuildingAssessedValue)}</p>
                          </div>
                        </div>
                      </div>

                      {/* Tax Breakdown */}
                      <div className="border-t pt-6">
                        <h4 className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Tax Breakdown</h4>
                        <div className="grid grid-cols-3 gap-4">
                          <div className="bg-gray-50 p-4 rounded-lg">
                            <p className="text-xs font-medium text-gray-500">Total Basic Tax</p>
                            <p className="text-lg font-bold text-gray-900 mt-1">
                              {formatCurrency(buildings.reduce((sum, b) => sum + (parseFloat(b.basic_tax_amount) || 0), 0))}
                            </p>
                          </div>
                          <div className="bg-gray-50 p-4 rounded-lg">
                            <p className="text-xs font-medium text-gray-500">Total SEF Tax</p>
                            <p className="text-lg font-bold text-gray-900 mt-1">
                              {formatCurrency(buildings.reduce((sum, b) => sum + (parseFloat(b.sef_tax_amount) || 0), 0))}
                            </p>
                          </div>
                          <div className="bg-green-100 p-4 rounded-lg border border-green-200">
                            <p className="text-xs font-medium text-green-600">Total Building Tax</p>
                            <p className="text-xl font-bold text-green-600 mt-1">{formatCurrency(totalBuildingTax)}</p>
                          </div>
                        </div>
                      </div>
                    </>
                  ) : (
                    <div className="text-center py-8">
                      <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <Building className="w-8 h-8 text-gray-400" />
                      </div>
                      <h3 className="text-lg font-medium text-gray-900 mb-2">No Buildings Registered</h3>
                      <p className="text-gray-600">This property has no building structures</p>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Compact Tax Summary */}
        <div className="bg-white border rounded-xl shadow-sm mb-8">
          <div className="px-6 py-4 border-b">
            <div className="flex items-center gap-2">
              <DollarSign className="w-4 h-4 text-yellow-600" />
              <h2 className="text-base font-semibold text-gray-900">Tax Summary</h2>
            </div>
          </div>
          <div className="p-4">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="bg-blue-50 p-4 rounded-lg border border-blue-100">
                <div className="flex items-center justify-between mb-2">
                  <p className="text-sm font-medium text-gray-700">Land Tax</p>
                  <Home className="w-4 h-4 text-blue-500" />
                </div>
                <p className="text-xl font-bold text-blue-600">{formatCurrency(totalLandTax)}</p>
                <div className="h-1 bg-blue-100 rounded-full overflow-hidden mt-2">
                  <div 
                    className="h-1 bg-blue-500 rounded-full" 
                    style={{ width: `${(totalLandTax / totalAnnualTax) * 100}%` }}
                  ></div>
                </div>
                <p className="text-xs text-gray-500 mt-1">
                  {Math.round((totalLandTax / totalAnnualTax) * 100)}% of total
                </p>
              </div>

              <div className="bg-green-50 p-4 rounded-lg border border-green-100">
                <div className="flex items-center justify-between mb-2">
                  <p className="text-sm font-medium text-gray-700">Building Tax</p>
                  <Building className="w-4 h-4 text-green-500" />
                </div>
                <p className="text-xl font-bold text-green-600">{formatCurrency(totalBuildingTax)}</p>
                <div className="h-1 bg-green-100 rounded-full overflow-hidden mt-2">
                  <div 
                    className="h-1 bg-green-500 rounded-full" 
                    style={{ width: `${(totalBuildingTax / totalAnnualTax) * 100}%` }}
                  ></div>
                </div>
                <p className="text-xs text-gray-500 mt-1">
                  {Math.round((totalBuildingTax / totalAnnualTax) * 100)}% of total
                </p>
              </div>

              <div className="bg-purple-50 p-4 rounded-lg border border-purple-100">
                <div className="flex items-center justify-between mb-2">
                  <p className="text-sm font-medium text-gray-700">Total Annual Tax</p>
                  <DollarSign className="w-4 h-4 text-purple-500" />
                </div>
                <p className="text-2xl font-bold text-purple-600">{formatCurrency(totalAnnualTax)}</p>
                <div className="mt-2 space-y-1">
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-gray-600">Quarterly:</span>
                    <span className="font-semibold text-gray-900">{formatCurrency(quarterlyAmount)}</span>
                  </div>
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-gray-600">Paid Quarters:</span>
                    <span className="font-semibold text-green-600">
                      {quarterlyTaxes.filter(t => t.payment_status === 'paid').length}/4
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Quarterly Taxes Table */}
        <div className="bg-white border rounded-xl shadow-sm mb-8">
          <div className="px-6 py-4 border-b">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-2 mb-2 sm:mb-0">
                <Calendar className="w-4 h-4 text-purple-600" />
                <div>
                  <h2 className="text-base font-semibold text-gray-900">Quarterly Tax Payments</h2>
                  <p className="text-xs text-gray-500">Payment history and current status</p>
                </div>
              </div>
              <div className="flex flex-wrap gap-1">
                <span className="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full inline-flex items-center gap-1">
                  <CheckCheck className="w-2.5 h-2.5" />
                  Paid: {quarterlyTaxes.filter(t => t.payment_status === 'paid').length}
                </span>
                <span className="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-full inline-flex items-center gap-1">
                  <Clock className="w-2.5 h-2.5" />
                  Pending: {quarterlyTaxes.filter(t => t.payment_status === 'pending').length}
                </span>
                <span className="px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full inline-flex items-center gap-1">
                  <AlertCircle className="w-2.5 h-2.5" />
                  Overdue: {quarterlyTaxes.filter(t => t.payment_status === 'overdue').length}
                </span>
              </div>
            </div>
          </div>
          
          <div className="p-4">
            {quarterlyTaxesWithTotals.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Quarter</th>
                      <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Due Date</th>
                      <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Base Amount</th>
                      <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Penalty</th>
                      <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                      <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                      <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment Date</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200">
                    {quarterlyTaxesWithTotals.map((tax) => {
                      const status = getPaymentStatus(tax.payment_status);
                      const hasPenalty = tax.penalty_amount > 0;
                      const isCurrentQuarter = 
                        tax.year == new Date().getFullYear() && 
                        parseInt(tax.quarter.replace('Q', '')) === Math.floor(new Date().getMonth() / 3) + 1;
                      
                      return (
                        <tr key={tax.id} className={`hover:bg-gray-50 ${isCurrentQuarter ? 'bg-blue-50' : ''}`}>
                          <td className="px-3 py-3 whitespace-nowrap">
                            <div className="font-medium text-gray-900">{tax.quarter} {tax.year}</div>
                            {isCurrentQuarter && (
                              <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mt-1">
                                Current
                              </span>
                            )}
                          </td>
                          <td className="px-3 py-3 whitespace-nowrap">
                            <div className="text-gray-900">{formatDate(tax.due_date)}</div>
                            {tax.days_late > 0 && (
                              <div className="text-xs text-red-600 font-medium mt-0.5">{tax.days_late} days late</div>
                            )}
                          </td>
                          <td className="px-3 py-3 whitespace-nowrap">
                            <div className="font-medium text-gray-900">{formatCurrency(tax.total_quarterly_tax)}</div>
                          </td>
                          <td className="px-3 py-3 whitespace-nowrap">
                            {hasPenalty ? (
                              <div className="flex items-center">
                                <AlertCircle className="w-3 h-3 text-red-500 mr-1" />
                                <span className="font-medium text-red-600">{formatCurrency(tax.penalty_amount)}</span>
                              </div>
                            ) : (
                              <span className="text-gray-400">-</span>
                            )}
                          </td>
                          <td className="px-3 py-3 whitespace-nowrap">
                            <div className="font-bold text-gray-900">
                              {formatCurrency(tax.totalWithPenalty)}
                            </div>
                          </td>
                          <td className="px-3 py-3 whitespace-nowrap">
                            <div className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold ${status.color}`}>
                              {status.icon}
                              <span>{status.text}</span>
                            </div>
                          </td>
                          <td className="px-3 py-3 whitespace-nowrap">
                            <div className="text-gray-900">
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
              <div className="text-center py-8">
                <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                  <CreditCard className="w-8 h-8 text-gray-400" />
                </div>
                <h3 className="text-base font-semibold text-gray-900 mb-2">No Tax Records</h3>
                <p className="text-gray-600 text-sm">No quarterly tax payments have been recorded for this property.</p>
              </div>
            )}
          </div>
        </div>

        {/* Compact Footer Summary */}
        <div className="bg-white border rounded-xl shadow-sm">
          <div className="px-6 py-4 border-b">
            <h3 className="text-base font-semibold text-gray-900">Record Information</h3>
          </div>
          <div className="p-4">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="bg-gray-50 p-4 rounded-lg">
                <div className="flex items-center gap-2 mb-2">
                  <div className="p-1.5 bg-gray-100 rounded">
                    <Tag className="w-3.5 h-3.5 text-gray-600" />
                  </div>
                  <div>
                    <p className="text-xs font-medium text-gray-500">Registration Date</p>
                    <p className="text-base font-bold text-gray-900 mt-0.5">{formatDate(property.created_at)}</p>
                  </div>
                </div>
                <p className="text-xs text-gray-500">Date when property was registered</p>
              </div>
              
              <div className="bg-gray-50 p-4 rounded-lg">
                <div className="flex items-center gap-2 mb-2">
                  <div className="p-1.5 bg-gray-100 rounded">
                    <FileText className="w-3.5 h-3.5 text-gray-600" />
                  </div>
                  <div>
                    <p className="text-xs font-medium text-gray-500">Last Updated</p>
                    <p className="text-base font-bold text-gray-900 mt-0.5">{formatDate(property.updated_at || property.created_at)}</p>
                  </div>
                </div>
                <p className="text-xs text-gray-500">Date of last update</p>
              </div>
              
              <div className="bg-green-50 p-4 rounded-lg border border-green-200">
                <div className="flex items-center gap-2 mb-2">
                  <div className="p-1.5 bg-green-100 rounded">
                    <CheckCircle className="w-3.5 h-3.5 text-green-600" />
                  </div>
                  <div>
                    <p className="text-xs font-medium text-green-600">Status</p>
                    <p className="text-base font-bold text-green-700 mt-0.5">{property.status?.toUpperCase() || 'APPROVED'}</p>
                  </div>
                </div>
                <p className="text-xs text-green-600">Current registration status</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}