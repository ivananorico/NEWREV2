import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { Search, Filter, Eye, Building, Home, LandPlot, DollarSign, Calendar, RefreshCw } from "lucide-react";

export default function RPTStatus() {
  const [properties, setProperties] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [filterStatus, setFilterStatus] = useState("all");
  const navigate = useNavigate();

  // API Configuration
  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";
  
  const API_URL = `${API_BASE}/RPT/RPTStatus/get_approved_properties.php`;

  useEffect(() => {
    fetchApprovedProperties();
  }, []);

  const fetchApprovedProperties = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(API_URL, {
        method: 'GET',
        headers: {
          'Accept': 'application/json'
        }
      });
      
      if (!response.ok) {
        throw new Error(`Server error: ${response.status}`);
      }
      
      const data = await response.json();
      
      // Handle different response formats
      let propertiesData = [];
      
      if (data.success === true || data.success === "true") {
        propertiesData = data.data || [];
      } else if (data.status === "success") {
        propertiesData = data.properties || [];
      } else if (Array.isArray(data)) {
        propertiesData = data;
      } else if (data.error) {
        throw new Error(data.error);
      } else {
        throw new Error(data.message || "Failed to fetch properties");
      }
      
      setProperties(propertiesData);
      
    } catch (err) {
      console.error("Error fetching approved properties:", err);
      setError(`Failed to load properties: ${err.message}`);
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

  // Filter properties based on search and status
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

  // Calculate summary statistics
  const totalProperties = properties.length;
  const propertiesWithBuildings = properties.filter(p => p.has_building === 'yes').length;
  const vacantLands = properties.filter(p => p.has_building === 'no').length;
  const totalAnnualRevenue = properties.reduce((sum, prop) => sum + parseFloat(prop.total_annual_tax || 0), 0);

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading approved properties...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-lg shadow-md p-6 max-w-md w-full border border-gray-200">
          <div className="text-center">
            <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <span className="text-red-600 text-2xl">⚠️</span>
            </div>
            <h2 className="text-xl font-semibold text-gray-800 mb-2">Error Loading Data</h2>
            <p className="text-gray-600 mb-4">{error}</p>
            <button
              onClick={fetchApprovedProperties}
              className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium"
            >
              Try Again
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Approved Properties</h1>
              <p className="text-gray-600 mt-1">Property tax registry and management</p>
            </div>
            <div className="mt-4 sm:mt-0 flex items-center space-x-3">
              <button
                onClick={fetchApprovedProperties}
                className="flex items-center space-x-2 bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
              >
                <RefreshCw className="w-4 h-4" />
                <span>Refresh</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Summary Statistics */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div className="bg-white rounded-lg border border-gray-200 p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Total Properties</p>
                <p className="text-2xl font-bold text-gray-900">{totalProperties}</p>
              </div>
              <div className="bg-blue-100 p-2 rounded">
                <Building className="w-6 h-6 text-blue-600" />
              </div>
            </div>
          </div>
          
          <div className="bg-white rounded-lg border border-gray-200 p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">With Buildings</p>
                <p className="text-2xl font-bold text-green-600">{propertiesWithBuildings}</p>
              </div>
              <div className="bg-green-100 p-2 rounded">
                <Home className="w-6 h-6 text-green-600" />
              </div>
            </div>
          </div>
          
          <div className="bg-white rounded-lg border border-gray-200 p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Vacant Lands</p>
                <p className="text-2xl font-bold text-amber-600">{vacantLands}</p>
              </div>
              <div className="bg-amber-100 p-2 rounded">
                <LandPlot className="w-6 h-6 text-amber-600" />
              </div>
            </div>
          </div>
          
          <div className="bg-white rounded-lg border border-gray-200 p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Annual Revenue</p>
                <p className="text-xl font-bold text-purple-600">{formatCurrency(totalAnnualRevenue)}</p>
              </div>
              <div className="bg-purple-100 p-2 rounded">
                <DollarSign className="w-6 h-6 text-purple-600" />
              </div>
            </div>
          </div>
        </div>

        {/* Filters */}
        <div className="bg-white rounded-lg border border-gray-200 p-4 mb-6">
          <div className="flex flex-col md:flex-row md:items-center space-y-4 md:space-y-0 md:space-x-4">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                <input
                  type="text"
                  placeholder="Search by reference, owner, or location..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
            </div>
            
            <div className="flex items-center space-x-4">
              <div className="relative">
                <Filter className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                <select
                  value={filterStatus}
                  onChange={(e) => setFilterStatus(e.target.value)}
                  className="pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none bg-white"
                >
                  <option value="all">All Properties</option>
                  <option value="with_building">With Buildings</option>
                  <option value="vacant">Vacant Lands</option>
                </select>
              </div>
              
              {(searchTerm || filterStatus !== "all") && (
                <button
                  onClick={() => {
                    setSearchTerm("");
                    setFilterStatus("all");
                  }}
                  className="text-sm text-gray-600 hover:text-gray-800"
                >
                  Clear filters
                </button>
              )}
            </div>
          </div>
        </div>

        {/* Properties Table */}
        <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <h2 className="text-lg font-semibold text-gray-900">Property List</h2>
              <p className="text-sm text-gray-600 mt-1 sm:mt-0">
                {filteredProperties.length} propert{filteredProperties.length !== 1 ? 'ies' : 'y'} found
              </p>
            </div>
          </div>
          
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Property Details
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Owner
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Type
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Annual Tax
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {filteredProperties.length === 0 ? (
                  <tr>
                    <td colSpan="6" className="px-6 py-12 text-center">
                      <div className="text-gray-400 mb-4">
                        <Building className="w-12 h-12 mx-auto" />
                      </div>
                      <p className="text-gray-500 font-medium mb-2">No properties found</p>
                      <p className="text-sm text-gray-400">
                        {searchTerm || filterStatus !== "all" 
                          ? "Try adjusting your search or filter" 
                          : "No approved properties available"}
                      </p>
                    </td>
                  </tr>
                ) : (
                  filteredProperties.map((property) => (
                    <tr key={property.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4">
                        <div className="font-medium text-gray-900">{property.reference_number}</div>
                        <div className="text-sm text-gray-900 mt-1">{property.lot_location}</div>
                        <div className="text-sm text-gray-500">
                          {property.barangay}, Dist. {property.district}
                        </div>
                      </td>
                      
                      <td className="px-6 py-4">
                        <div className="font-medium text-gray-900">{property.owner_name}</div>
                        <div className="text-sm text-gray-500">{property.phone}</div>
                      </td>
                      
                      <td className="px-6 py-4">
                        <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${
                          property.has_building === 'yes' 
                            ? 'bg-green-100 text-green-800 border border-green-200' 
                            : 'bg-amber-100 text-amber-800 border border-amber-200'
                        }`}>
                          {property.has_building === 'yes' ? '🏠 With Building' : '🌱 Vacant Land'}
                        </span>
                      </td>
                      
                      <td className="px-6 py-4">
                        <div className="font-bold text-gray-900">
                          {formatCurrency(parseFloat(property.total_annual_tax))}
                        </div>
                      </td>
                      
                      <td className="px-6 py-4">
                        <div className="flex items-center">
                          <div className="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                          <span className="text-sm text-gray-700">Active</span>
                        </div>
                        <div className="text-xs text-gray-500 mt-1">
                          <Calendar className="w-3 h-3 inline mr-1" />
                          {formatDate(property.approval_date)}
                        </div>
                      </td>
                      
                      <td className="px-6 py-4">
                        <button
                          onClick={() => handleViewDetails(property.id)}
                          className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center space-x-2"
                        >
                          <Eye className="w-4 h-4" />
                          <span>View</span>
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
            <div className="px-6 py-4 border-t border-gray-200 bg-gray-50">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div className="text-sm text-gray-600">
                  Showing {filteredProperties.length} of {properties.length} properties
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="mt-8 text-center text-gray-500 text-sm">
          <p>Local Government Unit - Property Tax System</p>
          <p className="mt-1">Updated: {new Date().toLocaleDateString()}</p>
        </div>
      </div>
    </div>
  );
}