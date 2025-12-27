import React, { useState, useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";
import {
  ArrowLeft,
  RefreshCw,
  User,
  DollarSign,
  Calendar,
  Building,
  FileText,
  MapPin,
  Home,
  CheckCircle,
  Landmark,
  Hash,
  Percent,
  Ruler,
  Tag,
  Award,
  AlertCircle
} from "lucide-react";

export default function RPTStatusInfo() {
  const { id } = useParams();
  const navigate = useNavigate();

  const API_BASE =
    window.location.hostname === "localhost"
      ? "http://localhost/revenue2/backend"
      : "https://revenuetreasury.goserveph.com/backend";

  const [property, setProperty] = useState(null);
  const [buildings, setBuildings] = useState([]);
  const [quarterlyTaxes, setQuarterlyTaxes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState("overview");
  const [retryCount, setRetryCount] = useState(0);

  useEffect(() => {
    if (id) {
      fetchPropertyDetails();
    }
  }, [id, retryCount]);

  const fetchPropertyDetails = async () => {
    if (!id || id === "undefined" || id === "null") {
      setError("Invalid property ID provided");
      setLoading(false);
      return;
    }

    try {
      setLoading(true);
      setError(null);

      const response = await fetch(
        `${API_BASE}/RPT/RPTStatus/get_property_details.php?id=${id}`,
        { 
          method: "GET", 
          headers: { 
            "Accept": "application/json",
            // Remove Content-Type for GET requests to avoid CORS issues
          } 
        }
      );

      if (!response.ok) {
        const errorText = await response.text();
        console.error("Server error response:", errorText);
        throw new Error(`Server error: ${response.status}`);
      }

      const data = await response.json();

      let propertyData = null,
        buildingsData = [],
        taxesData = [];

      if (data.success === true || data.success === "true" || data.status === "success") {
        const responseData = data.data || {};
        propertyData = responseData.property || responseData;
        buildingsData = responseData.buildings || [];
        taxesData = responseData.quarterly_taxes || [];
      } else if (Array.isArray(data)) {
        // Handle array response format
        propertyData = data[0] || null;
      } else {
        throw new Error(data.message || data.error || "Failed to fetch data");
      }

      if (!propertyData) throw new Error("Property data not found");

      // Log the received data for debugging
      console.log("Property data received:", propertyData);
      console.log("Buildings data received:", buildingsData);
      console.log("Taxes data received:", taxesData);

      setProperty(propertyData);
      setBuildings(buildingsData);
      setQuarterlyTaxes(taxesData);
    } catch (err) {
      console.error("Error:", err);
      setError(`Failed to load property: ${err.message}`);
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (amount) => {
    if (amount === null || amount === undefined || amount === "" || isNaN(amount)) return "₱0.00";
    return new Intl.NumberFormat("en-PH", {
      style: "currency",
      currency: "PHP",
      minimumFractionDigits: 2,
    }).format(parseFloat(amount));
  };

  const formatDate = (dateString) => {
    if (!dateString || dateString === "0000-00-00") return "N/A";
    try {
      return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
      });
    } catch {
      return dateString;
    }
  };

  const formatDateTime = (dateString) => {
    if (!dateString || dateString === "0000-00-00 00:00:00") return "N/A";
    try {
      return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    } catch {
      return dateString;
    }
  };

  const getPaymentStatusColor = (status) => {
    switch (status?.toLowerCase()) {
      case "paid":
        return "bg-green-100 text-green-800 border border-green-200";
      case "overdue":
        return "bg-red-100 text-red-800 border border-red-200";
      case "pending":
        return "bg-yellow-100 text-yellow-800 border border-yellow-200";
      default:
        return "bg-gray-100 text-gray-800 border border-gray-200";
    }
  };

  const handleRetry = () => {
    setRetryCount(prev => prev + 1);
  };

  // Calculate totals
  const buildingTotals = {
    marketValue: buildings.reduce((sum, b) => sum + (parseFloat(b.building_market_value) || 0), 0),
    depreciatedValue: buildings.reduce((sum, b) => sum + (parseFloat(b.building_depreciated_value) || 0), 0),
    assessedValue: buildings.reduce((sum, b) => sum + (parseFloat(b.building_assessed_value) || 0), 0),
    annualTax: buildings.reduce((sum, b) => sum + (parseFloat(b.annual_tax) || 0), 0),
    basicTax: buildings.reduce((sum, b) => sum + (parseFloat(b.basic_tax_amount) || 0), 0),
    sefTax: buildings.reduce((sum, b) => sum + (parseFloat(b.sef_tax_amount) || 0), 0),
  };

  // Get land totals
  const landTotals = {
    marketValue: parseFloat(property?.land_market_value) || 0,
    assessedValue: parseFloat(property?.land_assessed_value) || 0,
    annualTax: parseFloat(property?.annual_tax) || 0,
    basicTax: parseFloat(property?.basic_tax_amount) || 0,
    sefTax: parseFloat(property?.sef_tax_amount) || 0,
  };

  // Calculate totals
  const totalPropertyValue = {
    marketValue: landTotals.marketValue + buildingTotals.marketValue,
    assessedValue: landTotals.assessedValue + buildingTotals.assessedValue,
    annualTax: landTotals.annualTax + buildingTotals.annualTax,
    basicTax: landTotals.basicTax + buildingTotals.basicTax,
    sefTax: landTotals.sefTax + buildingTotals.sefTax,
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading property details...</p>
          <p className="text-sm text-gray-500 mt-1">ID: {id}</p>
        </div>
      </div>
    );
  }

  if (error || !property) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="bg-white p-6 rounded-lg shadow-md border border-gray-200 max-w-md w-full text-center">
          <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <AlertCircle className="w-8 h-8 text-red-500" />
          </div>
          <h2 className="text-xl font-semibold mb-2 text-gray-800">
            Error Loading Property
          </h2>
          <p className="text-gray-600 mb-4">{error || "Property not found"}</p>
          <div className="space-y-3">
            <button
              onClick={handleRetry}
              className="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium"
            >
              <RefreshCw className="w-4 h-4 inline-block mr-2" />
              Try Again
            </button>
            <button
              onClick={() => navigate("/rpt/rptstatus")}
              className="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md font-medium"
            >
              Back to Properties
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div className="flex items-center space-x-4">
              <button
                onClick={() => navigate("/rpt/rptstatus")}
                className="flex items-center text-gray-600 hover:text-gray-900 transition-colors"
              >
                <ArrowLeft className="w-5 h-5 mr-2" /> 
                <span className="font-medium">Back to Properties</span>
              </button>
              <div className="hidden sm:block w-px h-6 bg-gray-300"></div>
              <div>
                <h1 className="text-xl font-bold text-gray-900">
                  {property.reference_number}
                </h1>
                <p className="text-sm text-gray-600 flex items-center gap-1">
                  <MapPin className="w-4 h-4" />
                  {property.lot_location}
                </p>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${
                property.status === 'approved' ? 'bg-green-100 text-green-800 border border-green-200' :
                property.status === 'pending' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
                'bg-gray-100 text-gray-800 border border-gray-200'
              }`}>
                {property.status?.toUpperCase() || 'ACTIVE'}
              </span>
              <button
                onClick={fetchPropertyDetails}
                className="flex items-center space-x-2 bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-md text-sm transition-colors"
              >
                <RefreshCw className="w-4 h-4" />
                <span>Refresh</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {/* Summary Banner */}
        <div className="mb-6 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-6">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h2 className="text-lg font-bold text-gray-900 mb-2">Property Tax Summary</h2>
              <div className="flex items-center gap-4 text-sm">
                <div className="flex items-center gap-2">
                  <Landmark className="w-4 h-4 text-blue-600" />
                  <span className="text-gray-700">{property.land_classification || 'Residential'}</span>
                </div>
                <div className="flex items-center gap-2">
                  <Ruler className="w-4 h-4 text-blue-600" />
                  <span className="text-gray-700">{property.land_area_sqm} sqm</span>
                </div>
                <div className="flex items-center gap-2">
                  <Home className="w-4 h-4 text-blue-600" />
                  <span className="text-gray-700">{property.has_building === 'yes' ? 'With Building' : 'Land Only'}</span>
                </div>
              </div>
            </div>
            <div className="text-right">
              <p className="text-sm text-gray-600">Total Annual Tax</p>
              <p className="text-3xl font-bold text-green-600">
                {formatCurrency(totalPropertyValue.annualTax)}
              </p>
            </div>
          </div>
        </div>

        {/* Tabs */}
        <div className="bg-white rounded-lg border border-gray-200 mb-6">
          <div className="border-b border-gray-200">
            <div className="flex overflow-x-auto">
              {["overview", "details", "taxes", "owner"].map((tab) => (
                <button
                  key={tab}
                  onClick={() => setActiveTab(tab)}
                  className={`flex items-center gap-2 px-6 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors ${
                    activeTab === tab
                      ? "border-blue-500 text-blue-600 bg-blue-50"
                      : "border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50"
                  }`}
                >
                  {tab === "overview" && <>
                    <Home className="w-4 h-4" />
                    Overview
                  </>}
                  {tab === "details" && <>
                    <Building className="w-4 h-4" />
                    Property Details
                    {buildings.length > 0 && (
                      <span className="bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded-full">
                        {buildings.length}
                      </span>
                    )}
                  </>}
                  {tab === "taxes" && <>
                    <DollarSign className="w-4 h-4" />
                    Tax Records
                    {quarterlyTaxes.length > 0 && (
                      <span className="bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded-full">
                        {quarterlyTaxes.length}
                      </span>
                    )}
                  </>}
                  {tab === "owner" && <>
                    <User className="w-4 h-4" />
                    Owner Info
                  </>}
                </button>
              ))}
            </div>
          </div>

          {/* Tab Content */}
          <div className="p-6">
            {/* Overview Tab */}
            {activeTab === "overview" && (
              <div className="space-y-6">
                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                  <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div className="flex items-center">
                      <Landmark className="w-5 h-5 text-blue-600 mr-3" />
                      <div>
                        <p className="text-sm text-gray-600">Land Assessed Value</p>
                        <p className="text-xl font-bold text-gray-900">
                          {formatCurrency(landTotals.assessedValue)}
                        </p>
                      </div>
                    </div>
                  </div>

                  <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div className="flex items-center">
                      <Building className="w-5 h-5 text-green-600 mr-3" />
                      <div>
                        <p className="text-sm text-gray-600">Building Assessed Value</p>
                        <p className="text-xl font-bold text-gray-900">
                          {formatCurrency(buildingTotals.assessedValue)}
                        </p>
                      </div>
                    </div>
                  </div>

                  <div className="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <div className="flex items-center">
                      <Percent className="w-5 h-5 text-purple-600 mr-3" />
                      <div>
                        <p className="text-sm text-gray-600">Total Assessed Value</p>
                        <p className="text-xl font-bold text-gray-900">
                          {formatCurrency(totalPropertyValue.assessedValue)}
                        </p>
                      </div>
                    </div>
                  </div>

                  <div className="bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <div className="flex items-center">
                      <Calendar className="w-5 h-5 text-orange-600 mr-3" />
                      <div>
                        <p className="text-sm text-gray-600">Quarterly Tax</p>
                        <p className="text-xl font-bold text-gray-900">
                          {formatCurrency(totalPropertyValue.annualTax / 4)}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Tax Breakdown */}
                <div className="bg-white border border-gray-200 rounded-lg p-6">
                  <h3 className="text-lg font-semibold mb-4 text-gray-900">Tax Breakdown</h3>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="border border-gray-200 rounded-lg p-4">
                      <p className="text-sm text-gray-600">Basic Tax (5%)</p>
                      <p className="text-lg font-bold text-gray-900">
                        {formatCurrency(totalPropertyValue.basicTax)}
                      </p>
                    </div>
                    <div className="border border-gray-200 rounded-lg p-4">
                      <p className="text-sm text-gray-600">SEF Tax (3%)</p>
                      <p className="text-lg font-bold text-gray-900">
                        {formatCurrency(totalPropertyValue.sefTax)}
                      </p>
                    </div>
                    <div className="border border-green-200 bg-green-50 rounded-lg p-4">
                      <p className="text-sm text-green-700">Total Annual Tax</p>
                      <p className="text-lg font-bold text-green-900">
                        {formatCurrency(totalPropertyValue.annualTax)}
                      </p>
                    </div>
                  </div>
                </div>

                {/* Property Quick Info */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {/* Location Info */}
                  <div className="bg-white border border-gray-200 rounded-lg p-6">
                    <div className="flex items-center mb-4">
                      <MapPin className="w-5 h-5 text-gray-700 mr-2" />
                      <h3 className="text-lg font-semibold text-gray-900">
                        Property Location
                      </h3>
                    </div>
                    <div className="space-y-4">
                      <div>
                        <p className="font-medium text-gray-900">
                          {property.lot_location}
                        </p>
                        <p className="text-sm text-gray-600">
                          {property.barangay}, Dist. {property.district}
                        </p>
                      </div>
                      <div className="grid grid-cols-2 gap-4">
                        <div>
                          <p className="text-sm text-gray-600">City</p>
                          <p className="font-medium text-gray-900">
                            {property.city || "Quezon City"}
                          </p>
                        </div>
                        <div>
                          <p className="text-sm text-gray-600">Province</p>
                          <p className="font-medium text-gray-900">
                            {property.province || "Metro Manila"}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Property Details */}
                  <div className="bg-white border border-gray-200 rounded-lg p-6">
                    <div className="flex items-center mb-4">
                      <Tag className="w-5 h-5 text-gray-700 mr-2" />
                      <h3 className="text-lg font-semibold text-gray-900">
                        Property Details
                      </h3>
                    </div>
                    <div className="space-y-3">
                      <div className="flex justify-between">
                        <span className="text-gray-600">Land Area:</span>
                        <span className="font-medium">{property.land_area_sqm} sqm</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-600">Property Type:</span>
                        <span className="font-medium">{property.property_type || property.land_classification}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-600">Has Building:</span>
                        <span className={`font-medium ${property.has_building === 'yes' ? 'text-green-600' : 'text-gray-600'}`}>
                          {property.has_building === 'yes' ? 'Yes' : 'No'}
                        </span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-600">Date Registered:</span>
                        <span className="font-medium">{formatDate(property.created_at)}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* Property Details Tab */}
            {activeTab === "details" && (
              <div className="space-y-6">
                {/* Land Details - Enhanced with missing fields */}
                <div className="bg-white border border-gray-200 rounded-lg p-6">
                  <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center">
                      <Landmark className="w-5 h-5 text-blue-600 mr-2" />
                      <h3 className="text-lg font-semibold text-gray-900">
                        Land Information
                      </h3>
                    </div>
                    {property.land_tdn && (
                      <span className="bg-blue-100 text-blue-800 text-sm px-3 py-1 rounded-full flex items-center">
                        <Hash className="w-4 h-4 mr-1" />
                        {property.land_tdn}
                      </span>
                    )}
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                      <p className="text-sm text-gray-600">TDN</p>
                      <p className="font-medium text-gray-900">
                        {property.land_tdn || "N/A"}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Property Type</p>
                      <p className="font-medium text-gray-900">
                        {property.property_type || "N/A"}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Land Area</p>
                      <p className="font-medium text-gray-900">
                        {property.land_area_sqm} sqm
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Land Market Value</p>
                      <p className="font-medium text-gray-900">
                        {formatCurrency(property.land_market_value)}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Land Assessed Value</p>
                      <p className="font-medium text-gray-900">
                        {formatCurrency(property.land_assessed_value)}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Assessment Level</p>
                      <p className="font-medium text-gray-900">
                        {property.assessment_level || "N/A"}%
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Total Assessed Value</p>
                      <p className="font-medium text-gray-900">
                        {formatCurrency(property.total_assessed_value || property.land_assessed_value)}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Annual Tax</p>
                      <p className="font-medium text-green-600">
                        {formatCurrency(property.annual_tax)}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Status</p>
                      <p className="font-medium text-gray-900">
                        {property.status || "Active"}
                      </p>
                    </div>
                  </div>
                </div>

                {/* Buildings - Enhanced with missing fields */}
                {buildings.length > 0 ? (
                  <div>
                    <div className="flex items-center justify-between mb-4">
                      <div className="flex items-center">
                        <Building className="w-5 h-5 text-green-600 mr-2" />
                        <h3 className="text-lg font-semibold text-gray-900">
                          Buildings ({buildings.length})
                        </h3>
                      </div>
                      <div className="text-sm text-gray-600">
                        Total Building Value: {formatCurrency(buildingTotals.assessedValue)}
                      </div>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      {buildings.map((b, i) => (
                        <div
                          key={b.id || i}
                          className="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors"
                        >
                          <div className="flex justify-between items-start mb-3">
                            <div>
                              <h4 className="font-semibold text-gray-900">
                                Building {i + 1}
                              </h4>
                              <p className="text-sm text-gray-600">{b.construction_type}</p>
                            </div>
                            <div className="text-right">
                              {b.tdn && (
                                <span className="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded block mb-1">
                                  TDN: {b.tdn}
                                </span>
                              )}
                              <span className={`text-xs px-2 py-1 rounded ${
                                b.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                              }`}>
                                {b.status || 'active'}
                              </span>
                            </div>
                          </div>
                          <div className="grid grid-cols-2 gap-2 mb-3">
                            <div>
                              <p className="text-xs text-gray-600">Floor Area</p>
                              <p className="text-sm font-medium">{b.floor_area_sqm} sqm</p>
                            </div>
                            <div>
                              <p className="text-xs text-gray-600">Year Built</p>
                              <p className="text-sm font-medium">{b.year_built || "N/A"}</p>
                            </div>
                            <div>
                              <p className="text-xs text-gray-600">Construction Type</p>
                              <p className="text-sm font-medium">{b.construction_type}</p>
                            </div>
                            <div>
                              <p className="text-xs text-gray-600">Property Config ID</p>
                              <p className="text-sm font-medium">{b.property_config_id || "N/A"}</p>
                            </div>
                          </div>
                          <div className="border-t pt-3">
                            <div className="grid grid-cols-2 gap-2 mb-2">
                              <div>
                                <p className="text-xs text-gray-600">Market Value</p>
                                <p className="text-sm font-medium">{formatCurrency(b.building_market_value)}</p>
                              </div>
                              <div>
                                <p className="text-xs text-gray-600">Depreciated Value</p>
                                <p className="text-sm font-medium">{formatCurrency(b.building_depreciated_value)}</p>
                              </div>
                              <div>
                                <p className="text-xs text-gray-600">Depreciation</p>
                                <p className="text-sm font-medium">{b.depreciation_percent || "0"}%</p>
                              </div>
                              <div>
                                <p className="text-xs text-gray-600">Assessed Value</p>
                                <p className="text-sm font-medium">{formatCurrency(b.building_assessed_value)}</p>
                              </div>
                            </div>
                            <div className="bg-gray-50 p-2 rounded">
                              <div className="flex justify-between">
                                <div>
                                  <p className="text-xs text-gray-600">Basic Tax</p>
                                  <p className="text-sm font-medium">{formatCurrency(b.basic_tax_amount)}</p>
                                </div>
                                <div>
                                  <p className="text-xs text-gray-600">SEF Tax</p>
                                  <p className="text-sm font-medium">{formatCurrency(b.sef_tax_amount)}</p>
                                </div>
                                <div>
                                  <p className="text-xs text-gray-600">Annual Tax</p>
                                  <p className="text-sm font-medium text-green-600">{formatCurrency(b.annual_tax)}</p>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                ) : (
                  <div className="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
                    <Building className="w-12 h-12 text-gray-400 mx-auto mb-3" />
                    <p className="text-gray-600">No buildings registered for this property</p>
                    <p className="text-sm text-gray-500 mt-1">This property has land only</p>
                  </div>
                )}
              </div>
            )}

            {/* Tax Records Tab */}
            {activeTab === "taxes" && (
              <div className="space-y-6">
                {quarterlyTaxes.length > 0 ? (
                  <>
                    {/* Tax Summary */}
                    <div className="bg-white border border-gray-200 rounded-lg p-6">
                      <h3 className="font-semibold text-gray-900 mb-4">Tax Payment Summary</h3>
                      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div className="bg-green-50 p-4 rounded-lg">
                          <p className="text-sm text-green-700">Paid Quarters</p>
                          <p className="text-2xl font-bold text-gray-900">
                            {quarterlyTaxes.filter(t => t.payment_status === "paid").length}
                          </p>
                        </div>
                        <div className="bg-yellow-50 p-4 rounded-lg">
                          <p className="text-sm text-yellow-700">Pending Quarters</p>
                          <p className="text-2xl font-bold text-gray-900">
                            {quarterlyTaxes.filter(t => t.payment_status === "pending").length}
                          </p>
                        </div>
                        <div className="bg-red-50 p-4 rounded-lg">
                          <p className="text-sm text-red-700">Overdue Quarters</p>
                          <p className="text-2xl font-bold text-gray-900">
                            {quarterlyTaxes.filter(t => t.payment_status === "overdue").length}
                          </p>
                        </div>
                        <div className="bg-blue-50 p-4 rounded-lg">
                          <p className="text-sm text-blue-700">Total Tax Due</p>
                          <p className="text-2xl font-bold text-gray-900">
                            {formatCurrency(quarterlyTaxes.reduce((sum, t) => sum + (parseFloat(t.total_quarterly_tax) || 0), 0))}
                          </p>
                        </div>
                      </div>
                    </div>

                    {/* Tax Table */}
                    <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
                      <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                          <thead className="bg-gray-50">
                            <tr>
                              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Quarter
                              </th>
                              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Year
                              </th>
                              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Due Date
                              </th>
                              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Amount
                              </th>
                              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Status
                              </th>
                              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Penalty
                              </th>
                              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Payment Date
                              </th>
                              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                Receipt #
                              </th>
                            </tr>
                          </thead>
                          <tbody className="bg-white divide-y divide-gray-200">
                            {quarterlyTaxes.map((tax) => (
                              <tr key={tax.id} className="hover:bg-gray-50">
                                <td className="px-4 py-3">{tax.quarter}</td>
                                <td className="px-4 py-3">{tax.year}</td>
                                <td className="px-4 py-3">{formatDate(tax.due_date)}</td>
                                <td className="px-4 py-3 font-medium">{formatCurrency(tax.total_quarterly_tax)}</td>
                                <td className="px-4 py-3">
                                  <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${getPaymentStatusColor(tax.payment_status)}`}>
                                    {tax.payment_status}
                                  </span>
                                </td>
                                <td className="px-4 py-3">
                                  {parseFloat(tax.penalty_amount) > 0 ? (
                                    <span className="text-red-600 font-medium">
                                      {formatCurrency(tax.penalty_amount)}
                                    </span>
                                  ) : (
                                    <span className="text-gray-500">None</span>
                                  )}
                                </td>
                                <td className="px-4 py-3">
                                  {tax.payment_date ? formatDate(tax.payment_date) : "—"}
                                </td>
                                <td className="px-4 py-3">
                                  {tax.receipt_number ? (
                                    <span className="font-mono text-sm bg-gray-100 px-2 py-1 rounded">
                                      {tax.receipt_number}
                                    </span>
                                  ) : (
                                    "—"
                                  )}
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </>
                ) : (
                  <div className="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
                    <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                      <FileText className="w-8 h-8 text-gray-400" />
                    </div>
                    <h4 className="text-lg font-medium text-gray-700 mb-2">No Tax Records</h4>
                    <p className="text-gray-600 max-w-md mx-auto">
                      Quarterly tax bills for this property will be generated soon.
                    </p>
                  </div>
                )}
              </div>
            )}

            {/* Owner Info Tab */}
            {activeTab === "owner" && (
              <div className="space-y-6">
                <div className="bg-white border border-gray-200 rounded-lg p-6">
                  <div className="flex items-center mb-6">
                    <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                      <User className="w-6 h-6 text-blue-600" />
                    </div>
                    <div>
                      <h3 className="font-semibold text-gray-900">Owner Information</h3>
                      <p className="text-sm text-gray-600">Complete details of the property owner</p>
                    </div>
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-4">
                      <div>
                        <p className="text-sm text-gray-600">Owner Code</p>
                        <p className="text-lg font-semibold text-gray-900">{property.owner_code || "N/A"}</p>
                      </div>
                      <div>
                        <p className="text-sm text-gray-600">Full Name</p>
                        <p className="text-lg font-semibold text-gray-900">{property.owner_name}</p>
                      </div>
                      <div>
                        <p className="text-sm text-gray-600">Contact Information</p>
                        <div className="mt-2 space-y-2">
                          <div className="flex items-center">
                            <span className="text-gray-400 mr-2">📱</span>
                            <span className="font-medium">{property.phone || "Not provided"}</span>
                          </div>
                          <div className="flex items-center">
                            <span className="text-gray-400 mr-2">✉️</span>
                            <span className="font-medium truncate">{property.email || "Not provided"}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div className="space-y-4">
                      <div>
                        <p className="text-sm text-gray-600">Owner Address</p>
                        <div className="mt-2 p-3 bg-gray-50 rounded-lg">
                          <p className="font-medium text-gray-900">{property.owner_address || "Not provided"}</p>
                          {property.house_number && property.street && (
                            <p className="text-sm text-gray-600 mt-1">
                              {property.house_number} {property.street}, {property.barangay}
                            </p>
                          )}
                          <p className="text-sm text-gray-600">
                            {property.city || "Quezon City"}, {property.province || "Metro Manila"}
                            {property.zip_code && `, ${property.zip_code}`}
                          </p>
                        </div>
                      </div>
                      <div>
                        <p className="text-sm text-gray-600">TIN Number</p>
                        <p className="font-medium text-gray-900">{property.tin_number || "Not provided"}</p>
                      </div>
                      <div>
                        <p className="text-sm text-gray-600">Owner Status</p>
                        <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${
                          property.owner_status === 'active' ? 'bg-green-100 text-green-800' :
                          property.owner_status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                          'bg-gray-100 text-gray-800'
                        }`}>
                          {property.owner_status || 'active'}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Footer */}
        <div className="mt-6 border-t border-gray-200 pt-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between text-sm text-gray-500">
            <div>
              <p>
                Property ID: <span className="font-medium text-gray-700">{id}</span> • 
                Reference: <span className="font-medium text-gray-700">{property.reference_number}</span>
              </p>
              <p className="mt-1">
                Last updated: {formatDateTime(property.updated_at || property.created_at)} • 
                Created: {formatDate(property.created_at)}
              </p>
            </div>
            <div className="mt-2 sm:mt-0">
              <p className="flex items-center gap-2">
                <Award className="w-4 h-4" />
                Approved Property • System: RPT Management
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}