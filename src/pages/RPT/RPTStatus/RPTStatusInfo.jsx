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

  useEffect(() => {
    fetchPropertyDetails();
  }, [id]);

  const fetchPropertyDetails = async () => {
    if (!id) {
      setError("Invalid property ID");
      setLoading(false);
      return;
    }

    try {
      setLoading(true);
      setError(null);

      const response = await fetch(
        `${API_BASE}/RPT/RPTStatus/get_property_details.php?id=${id}`,
        { method: "GET", headers: { Accept: "application/json" } }
      );

      if (!response.ok) throw new Error(`Server error: ${response.status}`);

      const data = await response.json();

      let propertyData = null,
        buildingsData = [],
        taxesData = [];

      if (data.success === true || data.success === "true") {
        const responseData = data.data || {};
        propertyData = responseData.property || responseData;
        buildingsData = responseData.buildings || [];
        taxesData = responseData.quarterly_taxes || [];
      } else {
        throw new Error(data.message || data.error || "Failed to fetch data");
      }

      if (!propertyData) throw new Error("Property data not found");

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
    if (!amount || isNaN(amount)) return "₱0.00";
    return new Intl.NumberFormat("en-PH", {
      style: "currency",
      currency: "PHP",
      minimumFractionDigits: 2,
    }).format(amount);
  };

  const formatDate = (dateString) => {
    if (!dateString) return "N/A";
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

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading property details...</p>
        </div>
      </div>
    );
  }

  if (error || !property) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="bg-white p-6 rounded-lg shadow-md border border-gray-200 max-w-md text-center">
          <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <span className="text-red-600 text-2xl">⚠️</span>
          </div>
          <h2 className="text-xl font-semibold mb-2 text-gray-800">
            Error Loading Property
          </h2>
          <p className="text-gray-600 mb-4">{error || "Property not found"}</p>
          <button
            onClick={() => navigate("/rpt/rptstatus")}
            className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium"
          >
            Back to Properties
          </button>
        </div>
      </div>
    );
  }

  // Calculate totals
  const buildingTotals = {
    marketValue: buildings.reduce(
      (sum, b) => sum + (parseFloat(b.building_market_value) || 0),
      0
    ),
    assessedValue: buildings.reduce(
      (sum, b) => sum + (parseFloat(b.building_assessed_value) || 0),
      0
    ),
    annualTax: buildings.reduce(
      (sum, b) => sum + (parseFloat(b.building_annual_tax) || 0),
      0
    ),
  };

  const totalPropertyValue = {
    marketValue:
      (parseFloat(property.land_market_value) || 0) + buildingTotals.marketValue,
    assessedValue:
      (parseFloat(property.land_assessed_value) || 0) +
      buildingTotals.assessedValue,
    annualTax: parseFloat(property.total_annual_tax) || 0,
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
          <div className="flex items-center space-x-4">
            <button
              onClick={() => navigate("/rpt/rptstatus")}
              className="flex items-center text-gray-600 hover:text-gray-900"
            >
              <ArrowLeft className="w-5 h-5 mr-2" /> Back
            </button>
            <div>
              <h1 className="text-xl font-bold text-gray-900">
                {property.reference_number}
              </h1>
              <p className="text-sm text-gray-600">{property.lot_location}</p>
            </div>
          </div>
          <button
            onClick={fetchPropertyDetails}
            className="flex items-center space-x-2 bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-md text-sm"
          >
            <RefreshCw className="w-4 h-4" />
            <span>Refresh</span>
          </button>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {/* Tabs */}
        <div className="bg-white rounded-lg border border-gray-200 mb-6">
          <div className="border-b border-gray-200">
            <div className="flex">
              {["overview", "details", "taxes"].map((tab) => (
                <button
                  key={tab}
                  onClick={() => setActiveTab(tab)}
                  className={`px-6 py-3 text-sm font-medium border-b-2 ${
                    activeTab === tab
                      ? "border-blue-500 text-blue-600"
                      : "border-transparent text-gray-500 hover:text-gray-700"
                  }`}
                >
                  {tab === "overview" && "Overview"}
                  {tab === "details" &&
                    `Property Details${
                      buildings.length > 0 ? ` (${buildings.length})` : ""
                    }`}
                  {tab === "taxes" &&
                    `Tax Records${
                      quarterlyTaxes.length > 0 ? ` (${quarterlyTaxes.length})` : ""
                    }`}
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
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div className="flex items-center">
                      <DollarSign className="w-5 h-5 text-blue-600 mr-3" />
                      <div>
                        <p className="text-sm text-gray-600">Annual Tax</p>
                        <p className="text-xl font-bold text-gray-900">
                          {formatCurrency(totalPropertyValue.annualTax)}
                        </p>
                      </div>
                    </div>
                  </div>

                  <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div className="flex items-center">
                      <Building className="w-5 h-5 text-green-600 mr-3" />
                      <div>
                        <p className="text-sm text-gray-600">Total Value</p>
                        <p className="text-xl font-bold text-gray-900">
                          {formatCurrency(totalPropertyValue.assessedValue)}
                        </p>
                      </div>
                    </div>
                  </div>

                  <div className="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <div className="flex items-center">
                      <Calendar className="w-5 h-5 text-purple-600 mr-3" />
                      <div>
                        <p className="text-sm text-gray-600">Status</p>
                        <div className="flex items-center">
                          <div className="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                          <span className="font-medium">
                            {property.status || "Active"}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Owner Information */}
                <div className="bg-white border border-gray-200 rounded-lg p-6">
                  <div className="flex items-center mb-4">
                    <User className="w-5 h-5 text-gray-700 mr-2" />
                    <h3 className="text-lg font-semibold text-gray-900">
                      Property Owner
                    </h3>
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <p className="text-sm text-gray-600">Name</p>
                      <p className="font-medium text-gray-900">
                        {property.owner_name}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Contact</p>
                      <p className="font-medium text-gray-900">
                        {property.phone || "N/A"}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Email</p>
                      <p className="font-medium text-gray-900">
                        {property.email || "N/A"}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Address</p>
                      <p className="font-medium text-gray-900">
                        {property.owner_address || "N/A"}
                      </p>
                    </div>
                  </div>
                </div>

                {/* Property Location */}
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
                        <p className="text-sm text-gray-600">Land Area</p>
                        <p className="font-medium text-gray-900">
                          {property.land_area_sqm} sqm
                        </p>
                      </div>
                      <div>
                        <p className="text-sm text-gray-600">Classification</p>
                        <p className="font-medium text-gray-900">
                          {property.land_classification || "N/A"}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* Property Details Tab */}
            {activeTab === "details" && (
              <div className="space-y-6">
                {/* Land Details */}
                <div className="bg-white border border-gray-200 rounded-lg p-6">
                  <h3 className="text-lg font-semibold mb-4 text-gray-900">
                    Land Information
                  </h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                      <p className="text-sm text-gray-600">TDN</p>
                      <p className="font-medium text-gray-900">
                        {property.land_tdn || "N/A"}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Market Value</p>
                      <p className="font-medium text-gray-900">
                        {formatCurrency(property.land_market_value)}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Assessed Value</p>
                      <p className="font-medium text-gray-900">
                        {formatCurrency(property.land_assessed_value)}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Assessment Level</p>
                      <p className="font-medium text-gray-900">
                        {property.land_assessment_level || "N/A"}%
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Property Type</p>
                      <p className="font-medium text-gray-900">
                        {property.land_classification || "N/A"}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-600">Tax Rate</p>
                      <p className="font-medium text-gray-900">
                        {property.tax_rate || "N/A"}%
                      </p>
                    </div>
                  </div>
                </div>

                {/* Buildings */}
                {buildings.length > 0 ? (
                  <div>
                    <h3 className="text-lg font-semibold mb-4 text-gray-900">
                      Buildings ({buildings.length})
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      {buildings.map((b, i) => (
                        <div
                          key={b.id || i}
                          className="border border-gray-200 rounded-lg p-4"
                        >
                          <div className="flex justify-between items-start mb-3">
                            <h4 className="font-semibold text-gray-900">
                              Building {i + 1}
                            </h4>
                            <span className="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">
                              {b.tdn || "No TDN"}
                            </span>
                          </div>
                          <div className="grid grid-cols-2 gap-2">
                            <div>
                              <p className="text-xs text-gray-600">Floor Area</p>
                              <p className="text-sm font-medium">{b.floor_area_sqm} sqm</p>
                            </div>
                            <div>
                              <p className="text-xs text-gray-600">Year Built</p>
                              <p className="text-sm font-medium">{b.year_built || "N/A"}</p>
                            </div>
                            <div>
                              <p className="text-xs text-gray-600">Market Value</p>
                              <p className="text-sm font-medium">{formatCurrency(b.building_market_value)}</p>
                            </div>
                            <div>
                              <p className="text-xs text-gray-600">Assessed Value</p>
                              <p className="text-sm font-medium">{formatCurrency(b.building_assessed_value)}</p>
                            </div>
                          </div>
                          {b.building_annual_tax && (
                            <div className="pt-2 border-t">
                              <p className="text-xs text-gray-600">Annual Tax</p>
                              <p className="text-sm font-medium text-green-600">{formatCurrency(b.building_annual_tax)}</p>
                            </div>
                          )}
                        </div>
                      ))}
                    </div>
                  </div>
                ) : (
                  <div className="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
                    <Building className="w-12 h-12 text-gray-400 mx-auto mb-3" />
                    <p className="text-gray-600">No buildings registered for this property</p>
                  </div>
                )}
              </div>
            )}

            {/* Tax Records Tab */}
            {activeTab === "taxes" && (
              <div>
                {quarterlyTaxes.length > 0 ? (
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          {[
                            "Quarter",
                            "Year",
                            "Due Date",
                            "Amount",
                            "Status",
                            "Penalty",
                            "Payment Date",
                            "Receipt #",
                          ].map((h) => (
                            <th
                              key={h}
                              className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                            >
                              {h}
                            </th>
                          ))}
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {quarterlyTaxes.map((tax) => (
                          <tr key={tax.id}>
                            <td className="px-4 py-2">{tax.quarter}</td>
                            <td className="px-4 py-2">{tax.year}</td>
                            <td className="px-4 py-2">{formatDate(tax.due_date)}</td>
                            <td className="px-4 py-2">{formatCurrency(tax.total_quarterly_tax)}</td>
                            <td className="px-4 py-2">
                              <span
                                className={`inline-flex items-center px-2 py-1 rounded text-xs font-medium ${
                                  tax.payment_status === "paid"
                                    ? "bg-green-100 text-green-800"
                                    : tax.payment_status === "overdue"
                                    ? "bg-red-100 text-red-800"
                                    : "bg-yellow-100 text-yellow-800"
                                }`}
                              >
                                {tax.payment_status}
                              </span>
                            </td>
                            <td className="px-4 py-2">{formatCurrency(tax.penalty_amount)}</td>
                            <td className="px-4 py-2">{tax.payment_date ? formatDate(tax.payment_date) : "N/A"}</td>
                            <td className="px-4 py-2">{tax.receipt_number || "N/A"}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <div className="text-center py-8">
                    <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                      <FileText className="w-8 h-8 text-gray-400" />
                    </div>
                    <p className="text-gray-600">No tax records available</p>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Footer */}
        <div className="mt-4 text-sm text-gray-500">
          <p>
            Property ID: {id} • Last updated: {new Date().toLocaleString()}
          </p>
        </div>
      </div>
    </div>
  );
}
