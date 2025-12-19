import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";

export default function RPTStatus() {
  const [properties, setProperties] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [filterStatus, setFilterStatus] = useState("all");
  const [debugInfo, setDebugInfo] = useState({});
  const navigate = useNavigate();

  // ✅ DYNAMIC API URL - Works for both localhost and production
  const isDevelopment = window.location.hostname === "localhost";
  const API_BASE = isDevelopment
    ? "http://localhost/revenue/backend"
    : "https://revenuetreasury.goserveph.com/backend";
  
  const API_PATH = "/RPT/RPTStatus";
  const API_URL = `${API_BASE}${API_PATH}/get_approved_properties.php`;

  useEffect(() => {
    fetchApprovedProperties();
  }, []);

  const fetchApprovedProperties = async () => {
    try {
      setLoading(true);
      setError(null);
      
      console.log(`🔧 Environment: ${isDevelopment ? "Development" : "Production"}`);
      console.log(`🌐 API Base: ${API_BASE}`);
      console.log(`📁 Fetching from: ${API_URL}`);
      
      // ✅ SIMPLIFIED FETCH - Remove problematic headers
      const response = await fetch(API_URL, {
        method: 'GET',
        headers: {
          'Accept': 'application/json'
          // Removed: 'Cache-Control' - it triggers CORS preflight
        }
      });
      
      console.log("Response status:", response.status);
      
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status} - ${response.statusText}`);
      }
      
      const text = await response.text();
      console.log("📄 Raw response (first 500 chars):", text.substring(0, 500) + (text.length > 500 ? '...' : ''));
      
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        console.error("❌ JSON parse error:", parseError);
        setDebugInfo({
          rawResponse: text.substring(0, 500),
          parseError: parseError.message,
          url: API_URL,
          environment: isDevelopment ? "Development" : "Production"
        });
        throw new Error("Invalid JSON response from server");
      }
      
      console.log("✅ Parsed data:", data);
      
      // Handle response formats
      let propertiesData = [];
      
      if (data.success === true || data.success === "true") {
        console.log("✅ Using format: success + data");
        propertiesData = data.data || [];
      }
      else if (data.status === "success") {
        console.log("✅ Using format: status + properties");
        propertiesData = data.properties || [];
      }
      else if (Array.isArray(data)) {
        console.log("✅ Using format: direct array");
        propertiesData = data;
      }
      else if (data.error) {
        throw new Error(data.error);
      }
      else {
        console.error("❌ Unknown data format:", data);
        setDebugInfo({
          dataStructure: data,
          availableKeys: Object.keys(data),
          url: API_URL
        });
        throw new Error(data.message || "Failed to fetch approved properties");
      }
      
      console.log(`✅ Loaded ${propertiesData.length} approved properties`);
      setProperties(propertiesData);
      
    } catch (err) {
      console.error("❌ Error fetching approved properties:", err);
      setError(`Failed to fetch approved properties: ${err.message}`);
      
      // Show more debug info
      setDebugInfo(prev => ({
        ...prev,
        lastError: err.message,
        timestamp: new Date().toISOString(),
        apiUrl: API_URL,
        environment: isDevelopment ? "Development" : "Production"
      }));
    } finally {
      setLoading(false);
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

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
      return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    } catch (err) {
      return dateString;
    }
  };

  const handleViewDetails = (propertyId) => {
    navigate(`/rpt/rptstatusinfo/${propertyId}`);
  };

  const handleRetry = () => {
    setLoading(true);
    setError(null);
    fetchApprovedProperties();
  };

  const filteredProperties = properties.filter(property => {
    const matchesSearch = 
      property.reference_number?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      property.owner_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      property.lot_location?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      property.barangay?.toLowerCase().includes(searchTerm.toLowerCase());
    
    if (filterStatus === "with_building") {
      return matchesSearch && property.has_building === 'yes';
    } else if (filterStatus === "vacant") {
      return matchesSearch && property.has_building === 'no';
    }
    return matchesSearch;
  });

  const totalProperties = properties.length;
  const propertiesWithBuildings = properties.filter(p => p.has_building === 'yes').length;
  const vacantLands = properties.filter(p => p.has_building === 'no').length;
  const totalAnnualRevenue = properties.reduce((sum, prop) => sum + parseFloat(prop.total_annual_tax || 0), 0);

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-blue-50 to-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full border border-gray-100">
          <div className="flex flex-col items-center space-y-6">
            <div className="relative">
              <div className="animate-spin rounded-full h-16 w-16 border-4 border-blue-100"></div>
              <div className="absolute inset-0 flex items-center justify-center">
                <div className="animate-ping rounded-full h-8 w-8 bg-blue-500 opacity-75"></div>
              </div>
            </div>
            <div className="text-center">
              <p className="text-gray-800 font-semibold text-lg mb-2">Loading Approved Properties</p>
              <p className="text-gray-500 text-sm">Please wait while we fetch property data...</p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-blue-50 to-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-2xl shadow-xl p-8 max-w-lg w-full border border-red-100">
          <div className="text-center">
            <div className="inline-flex items-center justify-center w-20 h-20 bg-red-100 rounded-full mb-6">
              <span className="text-3xl text-red-600">⚠️</span>
            </div>
            <h2 className="text-2xl font-bold text-gray-900 mb-2">Connection Error</h2>
            <p className="text-gray-600 mb-4">{error}</p>
            
            {/* Debug Information */}
            <div className="bg-gray-50 p-4 rounded-lg mb-6 text-left border border-gray-200">
              <p className="text-sm font-semibold text-gray-700 mb-2">Troubleshooting Steps:</p>
              <ol className="text-sm text-gray-600 space-y-2 list-decimal pl-5">
                <li>
                  <strong>Test the API directly:</strong>{" "}
                  <a 
                    href={API_URL} 
                    target="_blank" 
                    rel="noopener noreferrer" 
                    className="text-blue-600 underline"
                  >
                    Click here to open {API_URL}
                  </a>
                </li>
                <li><strong>Check if the PHP file exists</strong> at the backend location</li>
                <li><strong>Verify CORS headers</strong> in the PHP file allow 'localhost:5173'</li>
                <li><strong>Clear browser cache</strong> and try again</li>
              </ol>
            </div>
            
            <div className="space-y-3">
              <button
                onClick={handleRetry}
                className="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-200 shadow-md hover:shadow-lg"
              >
                🔄 Retry Connection
              </button>
              <button
                onClick={() => navigate(-1)}
                className="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-6 rounded-xl transition-colors"
              >
                ← Go Back
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-gray-50">
      {/* Header */}
      <div className="bg-gradient-to-r from-blue-700 to-blue-800 text-white shadow-lg">
        <div className="max-w-7xl mx-auto px-4 py-8">
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h1 className="text-3xl font-bold mb-2">Approved Properties</h1>
              <p className="text-blue-100 opacity-90">
                LGU Revenue Collection System • Property Registry
              </p>
            </div>
            <div className="mt-6 lg:mt-0 flex flex-wrap gap-3">
              <button
                onClick={fetchApprovedProperties}
                className="bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white px-5 py-2.5 rounded-xl font-medium transition-all duration-200 border border-white/30 flex items-center space-x-2"
              >
                <span>🔄</span>
                <span>Refresh</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="max-w-7xl mx-auto px-4 -mt-6">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          {/* Total Properties Card */}
          <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 transform hover:-translate-y-1 transition-transform duration-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-500 text-sm font-medium mb-1">Total Properties</p>
                <p className="text-3xl font-bold text-gray-900">{totalProperties}</p>
              </div>
              <div className="bg-blue-100 p-3 rounded-xl">
                <span className="text-2xl text-blue-600">🏢</span>
              </div>
            </div>
            <div className="mt-4 pt-4 border-t border-gray-100">
              <p className="text-xs text-gray-500">Approved property registrations</p>
            </div>
          </div>

          {/* Properties with Buildings */}
          <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 transform hover:-translate-y-1 transition-transform duration-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-500 text-sm font-medium mb-1">With Buildings</p>
                <p className="text-3xl font-bold text-green-900">{propertiesWithBuildings}</p>
              </div>
              <div className="bg-green-100 p-3 rounded-xl">
                <span className="text-2xl text-green-600">🏠</span>
              </div>
            </div>
            <div className="mt-4 pt-4 border-t border-gray-100">
              <p className="text-xs text-gray-500">Properties with building structures</p>
            </div>
          </div>

          {/* Vacant Lands */}
          <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 transform hover:-translate-y-1 transition-transform duration-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-500 text-sm font-medium mb-1">Vacant Lands</p>
                <p className="text-3xl font-bold text-amber-900">{vacantLands}</p>
              </div>
              <div className="bg-amber-100 p-3 rounded-xl">
                <span className="text-2xl text-amber-600">🌱</span>
              </div>
            </div>
            <div className="mt-4 pt-4 border-t border-gray-100">
              <p className="text-xs text-gray-500">Properties without structures</p>
            </div>
          </div>

          {/* Annual Revenue */}
          <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 transform hover:-translate-y-1 transition-transform duration-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-500 text-sm font-medium mb-1">Annual Revenue</p>
                <p className="text-2xl font-bold text-purple-900">{formatCurrency(totalAnnualRevenue)}</p>
              </div>
              <div className="bg-purple-100 p-3 rounded-xl">
                <span className="text-2xl text-purple-600">💰</span>
              </div>
            </div>
            <div className="mt-4 pt-4 border-t border-gray-100">
              <p className="text-xs text-gray-500">Total annual tax collection</p>
            </div>
          </div>
        </div>

        {/* Filters and Search */}
        <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 mb-8">
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div className="flex-1">
              <div className="relative">
                <input
                  type="text"
                  placeholder="Search by reference number, owner name, or location..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full px-4 py-3 pl-12 bg-gray-50 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                />
                <div className="absolute left-4 top-3.5 text-gray-400">
                  🔍
                </div>
              </div>
            </div>
            <div className="flex space-x-3">
              <select
                value={filterStatus}
                onChange={(e) => setFilterStatus(e.target.value)}
                className="bg-gray-50 border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
              >
                <option value="all">All Properties</option>
                <option value="with_building">With Buildings</option>
                <option value="vacant">Vacant Lands</option>
              </select>
              <button 
                onClick={() => {
                  setSearchTerm("");
                  setFilterStatus("all");
                }}
                className="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-3 rounded-xl font-medium transition-colors"
              >
                Clear Filters
              </button>
            </div>
          </div>
        </div>

        {/* Properties Table */}
        <div className="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden mb-8">
          <div className="p-6 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white">
            <div className="flex flex-col md:flex-row md:items-center md:justify-between">
              <div>
                <h2 className="text-xl font-bold text-gray-900">Approved Properties</h2>
                <p className="text-gray-600 text-sm mt-1">
                  {filteredProperties.length} propert{filteredProperties.length === 1 ? 'y' : 'ies'} found
                </p>
              </div>
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                    Property Details
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                    Owner Information
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                    Classification
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                    Annual Tax
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                    Status
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {filteredProperties.length === 0 ? (
                  <tr>
                    <td colSpan="6" className="px-6 py-16 text-center">
                      <div className="text-gray-400 mb-4">
                        <svg className="mx-auto h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                      </div>
                      <p className="text-gray-500 text-lg font-medium mb-2">No properties found</p>
                      <p className="text-sm text-gray-400">
                        {searchTerm || filterStatus !== "all" 
                          ? "Try adjusting your search or filters"
                          : "No approved properties in the system yet"}
                      </p>
                    </td>
                  </tr>
                ) : (
                  filteredProperties.map((property) => (
                    <tr key={property.id} className="hover:bg-blue-50/50 transition-colors">
                      <td className="px-6 py-4">
                        <div className="font-mono font-bold text-blue-700 text-sm">
                          {property.reference_number}
                        </div>
                        <div className="font-medium text-gray-900 mt-1">{property.lot_location}</div>
                        <div className="text-sm text-gray-500">
                          {property.barangay}, Dist. {property.district}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="font-semibold text-gray-900">{property.owner_name}</div>
                        <div className="text-sm text-gray-500 mt-1">{property.email}</div>
                        <div className="text-sm text-gray-500">{property.phone}</div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="space-y-2">
                          <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ${
                            property.has_building === 'yes' 
                              ? 'bg-green-100 text-green-800 border border-green-200' 
                              : 'bg-amber-100 text-amber-800 border border-amber-200'
                          }`}>
                            {property.has_building === 'yes' ? '🏠 With Building' : '🌱 Vacant Land'}
                          </span>
                          {property.land_classification && (
                            <span className="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold border border-blue-200">
                              📍 {property.land_classification}
                            </span>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="text-lg font-bold text-gray-900">
                          {formatCurrency(parseFloat(property.total_annual_tax))}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <div className="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                          <span className="text-sm font-medium text-gray-700">Active</span>
                        </div>
                        <div className="text-xs text-gray-500 mt-1">
                          Approved: {formatDate(property.approval_date)}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <button
                          onClick={() => handleViewDetails(property.id)}
                          className="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md flex items-center justify-center space-x-2"
                        >
                          <span>👁️</span>
                          <span>View Details</span>
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {/* Table Footer */}
          {filteredProperties.length > 0 && (
            <div className="bg-gray-50 px-6 py-4 border-t border-gray-200">
              <div className="flex flex-col md:flex-row md:items-center md:justify-between">
                <div className="text-sm text-gray-600">
                  Showing <span className="font-semibold">{filteredProperties.length}</span> of <span className="font-semibold">{properties.length}</span> properties
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Footer Info */}
        <div className="text-center text-gray-500 text-sm mb-8">
          <p>© {new Date().getFullYear()} Local Government Unit • Property Registry System</p>
        </div>
      </div>
    </div>
  );
}