import React, { useState, useEffect } from "react";
import { Search, Filter, Eye, Download, RefreshCw, CheckCircle, Building, FileText, Home, User, Calendar, DollarSign, MapPin, ChevronRight } from "lucide-react";
import { useNavigate } from "react-router-dom";

export default function RPTStatus() {
  const [approvedProperties, setApprovedProperties] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [propertyTypeFilter, setPropertyTypeFilter] = useState("all");
  const navigate = useNavigate();

  // API Configuration
  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

  const API_PATH = "/RPT/RPTStatus";
  const isDevelopment = window.location.hostname === "localhost";

  const fetchApprovedProperties = async () => {
    try {
      setLoading(true);
      setError(null);
      
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
      console.log("📄 Raw response:", text.substring(0, 500));
      
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        console.error("❌ JSON parse error:", parseError);
        throw new Error("Invalid JSON response from server");
      }
      
      console.log("📊 Parsed data structure:", data);
      
      // Check for success - multiple formats
      const isSuccess = (
        data.success === true || 
        data.success === "true" || 
        data.status === "success" || 
        data.status === "Success" ||
        (Array.isArray(data) && data.length >= 0)
      );
      
      if (isSuccess) {
        // Extract data from response
        let propertiesData = [];
        
        if (Array.isArray(data)) {
          propertiesData = data;
        } else if (data.success === true || data.success === "true") {
          propertiesData = data.data || [];
        } else if (data.status === "success") {
          propertiesData = data.data || [];
        } else if (Array.isArray(data.data)) {
          propertiesData = data.data;
        } else {
          propertiesData = [];
        }
        
        console.log("✅ Loaded properties:", propertiesData.length);
        setApprovedProperties(propertiesData);
      } else {
        throw new Error(data.error || data.message || "Failed to load approved properties");
      }
    } catch (err) {
      console.error("❌ Error fetching approved properties:", err);
      setError(`Failed to load approved properties: ${err.message}`);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchApprovedProperties();
  }, []);

  // Filter properties based on search and type
  const filteredProperties = approvedProperties.filter(property => {
    const matchesSearch = 
      (property.owner_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.reference_number?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.lot_location?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.barangay?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.tdn?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.first_name && property.last_name ? 
        `${property.first_name} ${property.last_name}`.toLowerCase().includes(searchTerm.toLowerCase()) : false);
    
    const matchesType = propertyTypeFilter === "all" || 
      (property.property_type && property.property_type === propertyTypeFilter);
    
    return matchesSearch && matchesType;
  });

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
    if (!amount || isNaN(amount)) return '₱0.00';
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);
  };

  const handleViewDetails = (id) => {
    navigate(`/rpt/rptstatusinfo/${id}`);
  };

  const handleExport = () => {
    // Simple CSV export
    if (filteredProperties.length === 0) {
      alert("No data to export");
      return;
    }

    const headers = [
      "Reference Number",
      "Owner Name",
      "Location",
      "Barangay",
      "District",
      "Land Area (sqm)",
      "Land Market Value",
      "Land Assessed Value",
      "Annual Tax",
      "Property Type",
      "Date Approved"
    ];

    const csvData = filteredProperties.map(property => [
      property.reference_number || "",
      property.owner_name || "",
      property.lot_location || "",
      property.barangay || "",
      property.district || "",
      property.land_area_sqm || "0",
      property.land_market_value || "0",
      property.land_assessed_value || "0",
      property.total_annual_tax || "0",
      property.property_type || "Residential",
      property.created_at ? new Date(property.created_at).toLocaleDateString() : ""
    ]);

    const csvContent = [
      headers.join(","),
      ...csvData.map(row => row.map(cell => `"${cell}"`).join(","))
    ].join("\n");

    const blob = new Blob([csvContent], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `approved-properties-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600 font-medium">Loading approved properties...</p>
          <p className="text-sm text-gray-500 mt-2">Fetching data from server</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-lg shadow-lg p-8 max-w-md w-full">
          <div className="text-center">
            <div className="text-red-500 text-4xl mb-4">⚠️</div>
            <h2 className="text-xl font-bold text-gray-900 mb-2">Error Loading Data</h2>
            <p className="text-gray-600 mb-4">{error}</p>
            <div className="space-y-3">
              <button
                onClick={fetchApprovedProperties}
                className="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg font-medium"
              >
                <RefreshCw className="w-4 h-4 inline-block mr-2" />
                Try Again
              </button>
              <button
                onClick={() => navigate(-1)}
                className="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-3 rounded-lg font-medium"
              >
                Go Back
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Get unique property types for filter
  const propertyTypes = [...new Set(approvedProperties.map(p => p.property_type).filter(Boolean))];

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="text-2xl md:text-3xl font-bold text-gray-900">Approved Properties</h1>
              <p className="text-gray-600 mt-2">Manage and view all approved real property tax registrations</p>
              <div className="flex items-center gap-2 mt-3">
                <div className="flex items-center gap-1 text-sm text-gray-500">
                  <CheckCircle className="w-4 h-4 text-green-500" />
                  <span>Showing approved properties only</span>
                </div>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <button
                onClick={fetchApprovedProperties}
                className="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2.5 rounded-lg font-medium transition duration-200"
              >
                <RefreshCw className="w-4 h-4" />
                <span className="hidden sm:inline">Refresh</span>
              </button>
              <button
                onClick={handleExport}
                className="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2.5 rounded-lg font-medium transition duration-200"
              >
                <Download className="w-4 h-4" />
                <span className="hidden sm:inline">Export CSV</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Stats Summary */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 font-medium">Total Approved</p>
                <p className="text-3xl font-bold text-gray-900 mt-2">{approvedProperties.length}</p>
              </div>
              <div className="bg-green-50 p-3 rounded-lg">
                <CheckCircle className="w-6 h-6 text-green-600" />
              </div>
            </div>
          </div>
          
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 font-medium">With Buildings</p>
                <p className="text-3xl font-bold text-blue-600 mt-2">
                  {approvedProperties.filter(p => p.has_building === 'yes').length}
                </p>
              </div>
              <div className="bg-blue-50 p-3 rounded-lg">
                <Building className="w-6 h-6 text-blue-600" />
              </div>
            </div>
          </div>
          
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 font-medium">Land Only</p>
                <p className="text-3xl font-bold text-purple-600 mt-2">
                  {approvedProperties.filter(p => p.has_building === 'no').length}
                </p>
              </div>
              <div className="bg-purple-50 p-3 rounded-lg">
                <Home className="w-6 h-6 text-purple-600" />
              </div>
            </div>
          </div>
          
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 font-medium">Total Annual Tax</p>
                <p className="text-2xl font-bold text-green-600 mt-2">
                  {formatCurrency(approvedProperties.reduce((sum, p) => sum + (parseFloat(p.total_annual_tax) || 0), 0))}
                </p>
              </div>
              <div className="bg-green-50 p-3 rounded-lg">
                <DollarSign className="w-6 h-6 text-green-600" />
              </div>
            </div>
          </div>
        </div>

        {/* Filters */}
        <div className="bg-white rounded-xl border border-gray-200 p-5 mb-6 shadow-sm">
          <div className="flex flex-col lg:flex-row lg:items-center gap-4">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                <input
                  type="text"
                  placeholder="Search by owner name, reference number, location, or TDN..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                />
              </div>
            </div>
            
            <div className="flex items-center gap-4">
              <div className="relative flex-1">
                <Filter className="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                <select
                  value={propertyTypeFilter}
                  onChange={(e) => setPropertyTypeFilter(e.target.value)}
                  className="w-full pl-12 pr-10 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none bg-white transition duration-200"
                >
                  <option value="all">All Property Types</option>
                  {propertyTypes.map(type => (
                    <option key={type} value={type}>{type}</option>
                  ))}
                </select>
              </div>
            </div>
          </div>
          
          {/* Search Stats */}
          <div className="mt-4 flex items-center justify-between text-sm">
            <div className="text-gray-600">
              {searchTerm ? (
                <span>
                  Searching for: <span className="font-semibold text-gray-900">"{searchTerm}"</span>
                </span>
              ) : (
                <span>Showing all approved properties</span>
              )}
            </div>
            <div className="text-gray-500">
              {filteredProperties.length} of {approvedProperties.length} records
            </div>
          </div>
        </div>

        {/* Properties Table */}
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
          <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 className="text-lg font-semibold text-gray-900">Approved Property List</h2>
                <p className="text-sm text-gray-600 mt-1">
                  {filteredProperties.length} property{filteredProperties.length !== 1 ? 's' : ''} found
                </p>
              </div>
              <div className="mt-2 sm:mt-0">
                <div className="inline-flex items-center gap-2 text-sm bg-green-50 text-green-700 px-3 py-1.5 rounded-full">
                  <CheckCircle className="w-4 h-4" />
                  <span>All properties are approved and active</span>
                </div>
              </div>
            </div>
          </div>
          
          {filteredProperties.length === 0 ? (
            <div className="px-6 py-16 text-center">
              <div className="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                <Building className="w-8 h-8 text-gray-400" />
              </div>
              <h3 className="text-lg font-medium text-gray-900 mb-2">
                {searchTerm || propertyTypeFilter !== "all" 
                  ? "No matching properties found" 
                  : "No approved properties yet"}
              </h3>
              <p className="text-gray-500 max-w-md mx-auto">
                {searchTerm 
                  ? "Try adjusting your search terms or clear the filter"
                  : "Properties will appear here once they are approved by the assessor"}
              </p>
              {(searchTerm || propertyTypeFilter !== "all") && (
                <button
                  onClick={() => {
                    setSearchTerm("");
                    setPropertyTypeFilter("all");
                  }}
                  className="mt-4 text-blue-600 hover:text-blue-700 font-medium"
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
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <div className="flex items-center gap-2">
                          <FileText className="w-4 h-4" />
                          Reference No.
                        </div>
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <div className="flex items-center gap-2">
                          <User className="w-4 h-4" />
                          Property Owner
                        </div>
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <div className="flex items-center gap-2">
                          <MapPin className="w-4 h-4" />
                          Property Location
                        </div>
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <div className="flex items-center gap-2">
                          <Home className="w-4 h-4" />
                          Property Details
                        </div>
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <div className="flex items-center gap-2">
                          <Calendar className="w-4 h-4" />
                          Date Approved
                        </div>
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {filteredProperties.map((property) => (
                      <tr key={property.id} className="hover:bg-gray-50 transition duration-150">
                        <td className="px-6 py-4">
                          <div className="font-mono font-bold text-gray-900">{property.reference_number}</div>
                          <div className="flex items-center gap-1 text-xs text-gray-500 mt-1">
                            <span className="bg-gray-100 px-2 py-0.5 rounded">ID: {property.id}</span>
                            {property.land_tdn && (
                              <span className="bg-blue-100 text-blue-800 px-2 py-0.5 rounded">TDN: {property.land_tdn}</span>
                            )}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="font-medium text-gray-900">{property.owner_name || `${property.first_name || ''} ${property.last_name || ''}`.trim()}</div>
                          <div className="text-sm text-gray-500 truncate max-w-[200px]">
                            {property.email || "No email"}
                          </div>
                          <div className="text-sm text-gray-500">{property.phone || "No phone"}</div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="font-medium text-gray-900">{property.lot_location || "No location specified"}</div>
                          <div className="text-sm text-gray-500">
                            Brgy. {property.barangay || "N/A"}, Dist. {property.district || "N/A"}
                          </div>
                          {property.property_type && (
                            <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mt-1 ${
                              property.property_type === 'Residential' ? 'bg-green-100 text-green-800' :
                              property.property_type === 'Commercial' ? 'bg-blue-100 text-blue-800' :
                              property.property_type === 'Industrial' ? 'bg-purple-100 text-purple-800' :
                              'bg-yellow-100 text-yellow-800'
                            }`}>
                              {property.property_type}
                            </span>
                          )}
                        </td>
                        <td className="px-6 py-4">
                          <div className="space-y-2">
                            <div className="flex justify-between text-sm">
                              <span className="text-gray-600">Land Area:</span>
                              <span className="font-medium text-gray-900">{property.land_area_sqm || "0"} sqm</span>
                            </div>
                            <div className="flex justify-between text-sm">
                              <span className="text-gray-600">Market Value:</span>
                              <span className="font-medium text-gray-900">{formatCurrency(property.land_market_value)}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                              <span className="text-gray-600">Assessed Value:</span>
                              <span className="font-medium text-gray-900">{formatCurrency(property.land_assessed_value)}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                              <span className="text-gray-600">Annual Tax:</span>
                              <span className="font-medium text-green-600">{formatCurrency(property.total_annual_tax)}</span>
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="text-sm font-medium text-gray-900">{formatDate(property.created_at)}</div>
                          <div className="text-xs text-gray-500 mt-1">
                            {property.updated_at && property.updated_at !== property.created_at ? (
                              <>Updated {formatDate(property.updated_at)}</>
                            ) : (
                              <>Approved {formatDate(property.created_at)}</>
                            )}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <button
                            onClick={() => handleViewDetails(property.id)}
                            className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg font-medium transition duration-200"
                          >
                            <Eye className="w-4 h-4" />
                            <span>View Details</span>
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              
              {/* Table Footer */}
              <div className="px-6 py-4 border-t border-gray-200 bg-gray-50">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                  <div className="text-sm text-gray-600">
                    Showing <span className="font-semibold">{filteredProperties.length}</span> of{" "}
                    <span className="font-semibold">{approvedProperties.length}</span> approved properties
                  </div>
                  <div className="text-sm text-gray-600">
                    <div className="flex items-center gap-3">
                      <div className="flex items-center gap-1">
                        <span className="inline-block w-2 h-2 bg-green-500 rounded-full"></span>
                        <span className="text-green-600">Approved: {approvedProperties.length}</span>
                      </div>
                      <div className="flex items-center gap-1">
                        <span className="inline-block w-2 h-2 bg-blue-500 rounded-full"></span>
                        <span className="text-blue-600">With Buildings: {approvedProperties.filter(p => p.has_building === 'yes').length}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </>
          )}
        </div>

        {/* Footer */}
        <div className="mt-8 pt-6 border-t border-gray-200">
          <div className="text-center text-gray-500 text-sm">
            <p className="font-medium">Local Government Unit - Approved Property Tax Registry</p>
            <p className="mt-1">Real Property Tax Management System v2.0</p>
            <div className="flex items-center justify-center gap-4 mt-2 text-xs text-gray-400">
              <span>Data refreshed: {new Date().toLocaleTimeString()}</span>
              <span>•</span>
              <span>Total approved: {approvedProperties.length}</span>
              <span>•</span>
              <span>API: {isDevelopment ? 'Development' : 'Production'}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}