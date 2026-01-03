import React, { useState, useEffect } from "react";
import { Search, Filter, Eye, Download, RefreshCw, CheckCircle, Building, FileText, User, Calendar, DollarSign, MapPin, Tag, Landmark, ArrowRight, CheckCheck } from "lucide-react";
import { useNavigate } from "react-router-dom";

// Property Type Badge Component - Clean Design
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
    <span className={`inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium ${colorClass}`}>
      {propertyType}
    </span>
  );
};

export default function RPTStatus() {
  const [approvedProperties, setApprovedProperties] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [propertyTypeFilter, setPropertyTypeFilter] = useState("all");
  const [calculatingPenalties, setCalculatingPenalties] = useState(false);
  const navigate = useNavigate();

  // API Configuration
  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

  const API_PATH = "/RPT/RPTStatus";

  // Function to automatically calculate penalties
  const calculatePenaltiesAutomatically = async () => {
    try {
      setCalculatingPenalties(true);
      
      const response = await fetch(`${API_BASE}${API_PATH}/calculate_rpt_penalties.php`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      });
      
      if (!response.ok) {
        throw new Error(`Penalty calculation failed: ${response.status}`);
      }
      
      const text = await response.text();
      let data;
      
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        throw new Error("Invalid JSON response from penalty calculation");
      }
      
      const isSuccess = (
        data.success === true || 
        data.success === "true" || 
        data.status === "success"
      );
      
      if (!isSuccess) {
        console.warn("Penalty calculation warning:", data.message || "Penalties not updated");
      }
      
    } catch (err) {
      console.warn("Penalty calculation error:", err.message);
      // Don't show error to user - continue loading properties
    } finally {
      setCalculatingPenalties(false);
    }
  };

  const fetchApprovedProperties = async () => {
    try {
      setLoading(true);
      setError(null);
      
      // First, calculate penalties automatically (silently in background)
      calculatePenaltiesAutomatically();
      
      // Then fetch approved properties
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

  // Filter properties based on search and type
  const filteredProperties = approvedProperties.filter(property => {
    const matchesSearch = 
      (property.owner_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.reference_number?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.lot_location?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (property.barangay?.toLowerCase() || '').includes(searchTerm.toLowerCase());
    
    const propertyType = property.property_type || '';
    const matchesType = propertyTypeFilter === "all" || 
      propertyType.toLowerCase() === propertyTypeFilter.toLowerCase();
    
    return matchesSearch && matchesType;
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
    return `₱${num.toFixed(0)}`;
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
      "Date Approved"
    ];

    const csvData = filteredProperties.map(property => [
      property.reference_number || "",
      property.owner_name || "",
      property.property_type || "",
      property.lot_location || "",
      property.barangay || "",
      property.total_annual_tax || "0",
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

  // Calculate totals
  const totalAnnualTax = approvedProperties.reduce((sum, p) => sum + (parseFloat(p.total_annual_tax) || 0), 0);
  const propertiesWithBuildings = approvedProperties.filter(p => p.has_building === 'yes').length;

  if (loading) {
    return (
      <div className="min-h-screen bg-white flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-gray-800 mx-auto"></div>
          <p className="mt-4 text-gray-600 font-medium">
            {calculatingPenalties ? "Updating penalties..." : "Loading approved properties..."}
          </p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-white flex items-center justify-center p-4">
        <div className="bg-white border border-gray-200 rounded-xl p-8 max-w-md w-full">
          <div className="text-center">
            <h2 className="text-xl font-bold text-gray-900 mb-2">Error Loading Data</h2>
            <p className="text-gray-600 mb-4">{error}</p>
            <button
              onClick={fetchApprovedProperties}
              className="w-full bg-gray-900 hover:bg-black text-white px-4 py-3 rounded-lg font-medium flex items-center justify-center gap-2"
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
    <div className="min-h-screen bg-white">
      {/* Header */}
      <div className="border-b border-gray-200 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="text-2xl font-bold text-gray-900 mb-1">
                Approved Properties
              </h1>
              <p className="text-sm text-gray-600">
                All approved real property tax registrations
              </p>
            </div>
            
            <div className="flex flex-wrap gap-3 items-center">
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
          {/* Total Approved */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Approved</p>
                <p className="text-2xl font-bold text-gray-900 mt-1">{formatNumber(approvedProperties.length)}</p>
              </div>
              <div className="p-3 bg-gray-100 rounded-lg">
                <CheckCircle className="w-5 h-5 text-gray-700" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Approved properties
            </div>
          </div>
          
          {/* Total Annual Tax */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Annual Tax</p>
                <p className="text-2xl font-bold text-gray-900 mt-1">{formatCurrency(totalAnnualTax)}</p>
              </div>
              <div className="p-3 bg-green-50 rounded-lg">
                <DollarSign className="w-5 h-5 text-green-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Annual tax assessment
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

          {/* Last Updated */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Last Updated</p>
                <p className="text-2xl font-bold text-gray-900 mt-1">
                  {new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                </p>
              </div>
              <div className="p-3 bg-gray-100 rounded-lg">
                <Calendar className="w-5 h-5 text-gray-700" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Penalties auto-calculated
            </div>
          </div>
        </div>

        {/* Filters Section */}
        <div className="bg-white border border-gray-200 rounded-xl p-5">
          <div className="flex flex-col lg:flex-row lg:items-center gap-4">
            <div className="flex-1">
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
            
            <div className="flex-1">
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
          </div>
          
          {/* Search Stats */}
          <div className="mt-4 flex items-center justify-between text-sm">
            <div className="text-gray-600">
              {searchTerm ? (
                <span>
                  Searching for: <span className="font-medium text-gray-900">"{searchTerm}"</span>
                </span>
              ) : (
                <span>Showing all approved properties</span>
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
                <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wider">Properties</h2>
                <p className="text-sm text-gray-600 mt-1">
                  {filteredProperties.length} approved property{filteredProperties.length !== 1 ? 's' : ''}
                </p>
              </div>
              <div className="mt-2 sm:mt-0">
                <div className="inline-flex items-center gap-2 text-xs bg-gray-100 text-gray-700 px-3 py-1.5 rounded-lg">
                  <CheckCheck className="w-3 h-3" />
                  <span>Penalties automatically calculated</span>
                </div>
              </div>
            </div>
          </div>
          
          {filteredProperties.length === 0 ? (
            <div className="px-4 py-12 text-center">
              <div className="mx-auto w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                <Building className="w-6 h-6 text-gray-400" />
              </div>
              <h3 className="text-sm font-medium text-gray-900 mb-1">
                {searchTerm || propertyTypeFilter !== "all" 
                  ? "No matching properties found" 
                  : "No approved properties yet"}
              </h3>
              <p className="text-sm text-gray-500 max-w-xs mx-auto">
                {searchTerm 
                  ? "Try adjusting your search terms or clear filters"
                  : "Check back later for approved properties"}
              </p>
              {(searchTerm || propertyTypeFilter !== "all") && (
                <button
                  onClick={() => {
                    setSearchTerm("");
                    setPropertyTypeFilter("all");
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
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Reference No.
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Property Owner
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Property Location
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Type
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Annual Tax
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Date Approved
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {filteredProperties.map((property) => (
                      <tr key={property.id} className="hover:bg-gray-50">
                        <td className="px-5 py-4">
                          <div className="font-mono text-sm font-semibold text-gray-900">{property.reference_number}</div>
                          <div className="text-xs text-gray-500 mt-0.5">
                            Area: {formatNumber(property.land_area_sqm)} sqm
                          </div>
                        </td>
                        <td className="px-5 py-4">
                          <div className="font-medium text-sm text-gray-900">
                            {property.owner_name || `${property.first_name || ''} ${property.last_name || ''}`.trim()}
                          </div>
                        </td>
                        <td className="px-5 py-4">
                          <div className="font-medium text-sm text-gray-900">{property.lot_location || "Not specified"}</div>
                          <div className="text-xs text-gray-500">
                            Brgy. {property.barangay || "N/A"}, Dist. {property.district || "N/A"}
                          </div>
                        </td>
                        <td className="px-5 py-4">
                          <PropertyTypeBadge propertyType={property.property_type} />
                        </td>
                        <td className="px-5 py-4">
                          <div className="font-bold text-gray-900 text-sm">
                            {formatCurrency(property.total_annual_tax)}
                          </div>
                        </td>
                        <td className="px-5 py-4">
                          <div className="text-sm text-gray-900">{formatDate(property.created_at)}</div>
                        </td>
                        <td className="px-5 py-4">
                          <button
                            onClick={() => handleViewDetails(property.id)}
                            className="text-sm font-medium px-4 py-2 rounded-lg bg-gray-900 text-white hover:bg-black transition duration-200 flex items-center gap-2"
                          >
                            <Eye className="w-4 h-4" />
                            View Details
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              
              {/* Table Footer */}
              <div className="px-5 py-4 border-t border-gray-200 bg-gray-50">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div className="text-sm text-gray-700">
                    Showing <span className="font-semibold">{filteredProperties.length}</span> of{" "}
                    <span className="font-semibold">{approvedProperties.length}</span> approved properties
                  </div>
                  <div className="text-sm text-gray-700">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-medium">Tax Summary:</span>
                      <span className="px-2 py-1 rounded bg-green-50 text-green-700 border border-green-200 text-xs">
                        Total: {formatCurrency(totalAnnualTax)}
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
            <p className="font-medium">Real Property Tax Registry</p>
            <p className="mt-1">Data as of {new Date().toLocaleDateString('en-PH')} • Penalties automatically calculated on page load</p>
          </div>
        </div>
      </div>
    </div>
  );
}