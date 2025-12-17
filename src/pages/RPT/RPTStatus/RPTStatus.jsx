import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";

export default function RPTStatus() {
  const [properties, setProperties] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [debugInfo, setDebugInfo] = useState({});
  const navigate = useNavigate();

  // 🔥 SAME PATTERN AS RPTValidationTable
  const API_BASE =
    window.location.hostname === "localhost"
      ? "http://localhost/revenue/backend"
      : "https://revenuetreasury.goserveph.com/backend";

  const API_PATH = "/RPT/RPTStatus";

  useEffect(() => {
    fetchApprovedProperties();
  }, []);

  const fetchApprovedProperties = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const url = `${API_BASE}${API_PATH}/get_approved_properties.php`;
      console.log(`🌐 Fetching approved properties from: ${url}`);
      
      const response = await fetch(url, {
        credentials: 'include',
        headers: {
          'Accept': 'application/json'
        }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const text = await response.text();
      console.log("📄 Raw response:", text.substring(0, 500) + (text.length > 500 ? '...' : ''));
      
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        console.error("❌ JSON parse error:", parseError);
        setDebugInfo({
          rawResponse: text.substring(0, 500),
          parseError: parseError.message
        });
        throw new Error("Invalid JSON response from server");
      }
      
      console.log("✅ Parsed data:", data);
      
      // Handle both response formats
      let propertiesData = [];
      
      // Format 1: {success: true, data: [...]} - NEW format
      if (data.success === true || data.success === "true") {
        console.log("✅ Using format: success + data");
        propertiesData = data.data || [];
      }
      // Format 2: {status: "success", properties: [...]} - OLD format
      else if (data.status === "success") {
        console.log("✅ Using format: status + properties");
        propertiesData = data.properties || [];
      }
      // Format 3: Direct array
      else if (Array.isArray(data)) {
        console.log("✅ Using format: direct array");
        propertiesData = data;
      }
      else {
        console.error("❌ Unknown data format:", data);
        setDebugInfo({
          dataStructure: data,
          availableKeys: Object.keys(data)
        });
        throw new Error(data.message || data.error || "Failed to fetch approved properties");
      }
      
      console.log(`✅ Loaded ${propertiesData.length} approved properties`);
      setProperties(propertiesData);
      
    } catch (err) {
      console.error("❌ Error fetching approved properties:", err);
      setError(`Failed to fetch approved properties: ${err.message}`);
    } finally {
      setLoading(false);
    }
  };

  // Format currency
  const formatCurrency = (amount) => {
    if (!amount || isNaN(amount)) return '₱0.00';
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);
  };

  // Format date
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

  // Handle view property details
  const handleViewDetails = (propertyId) => {
    navigate(`/rpt/rptstatusinfo/${propertyId}`);
  };

  const handleRetry = () => {
    setLoading(true);
    setError(null);
    fetchApprovedProperties();
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white rounded-lg shadow-lg p-8 max-w-md w-full mx-4">
          <div className="flex flex-col items-center space-y-4">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            <div className="text-center">
              <p className="text-gray-700 font-medium mb-2">Loading approved properties...</p>
              {window.location.hostname === "localhost" && (
                <p className="text-xs text-blue-500 mt-2">API: {API_BASE}{API_PATH}</p>
              )}
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white rounded-lg shadow-lg p-8 max-w-md w-full mx-4">
          <div className="text-center">
            <div className="text-red-500 text-4xl mb-4">⚠️</div>
            <h2 className="text-xl font-bold text-gray-900 mb-2">Error Loading Data</h2>
            <p className="text-gray-600 mb-4">{error}</p>
            
            {/* Debug Info */}
            <div className="bg-gray-100 p-3 rounded mb-4 text-left">
              <p className="text-sm font-semibold mb-1">Debug Info:</p>
              <p className="text-xs">API Base: {API_BASE}</p>
              <p className="text-xs">API Path: {API_PATH}/get_approved_properties.php</p>
              <p className="text-xs">Environment: {window.location.hostname === "localhost" ? "Development" : "Production"}</p>
              
              {debugInfo.rawResponse && (
                <>
                  <p className="text-xs font-semibold mt-2">Raw Response (first 500 chars):</p>
                  <pre className="text-xs bg-gray-800 text-white p-2 rounded mt-1 overflow-auto max-h-40">
                    {debugInfo.rawResponse}
                  </pre>
                </>
              )}
              
              {debugInfo.dataStructure && (
                <>
                  <p className="text-xs font-semibold mt-2">Data Structure:</p>
                  <pre className="text-xs bg-gray-800 text-white p-2 rounded mt-1 overflow-auto max-h-40">
                    {JSON.stringify(debugInfo.dataStructure, null, 2)}
                  </pre>
                </>
              )}
            </div>
            
            <div className="space-x-4">
              <button
                onClick={() => navigate(-1)}
                className="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition-colors"
              >
                Go Back
              </button>
              <button
                onClick={handleRetry}
                className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors"
              >
                Try Again
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-7xl mx-auto px-4">
        {/* Header */}
        <div className="bg-white rounded-xl shadow-lg border border-gray-200 p-6 mb-6">
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Approved Properties</h1>
              <p className="text-gray-600 mt-1">View and manage approved property registrations</p>
            </div>
            <div className="mt-4 lg:mt-0">
              <div className="flex items-center space-x-2 text-gray-600">
                <div className="w-3 h-3 bg-green-500 rounded-full"></div>
                <span className="text-sm font-medium">Total Approved: {properties.length}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Development Banner */}
        {window.location.hostname === "localhost" && (
          <div className="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div className="flex items-center">
              <span className="text-yellow-600 mr-2">🔧</span>
              <div>
                <p className="text-sm font-medium text-yellow-800">Development Mode</p>
                <p className="text-xs text-yellow-700">
                  API: {API_BASE}{API_PATH}/get_approved_properties.php
                </p>
              </div>
            </div>
          </div>
        )}

        {/* Table Card */}
        <div className="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
          <div className="p-6 border-b border-gray-200">
            <div className="flex flex-col md:flex-row md:items-center md:justify-between">
              <div>
                <h2 className="text-lg font-bold text-gray-900">Approved Properties List</h2>
                <p className="text-gray-600 text-sm mt-1">
                  Showing {properties.length} approved propert{properties.length === 1 ? 'y' : 'ies'}
                </p>
              </div>
              <div className="mt-4 md:mt-0 flex space-x-3">
                <button
                  onClick={fetchApprovedProperties}
                  className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition-colors flex items-center space-x-2"
                >
                  <span>🔄</span>
                  <span>Refresh</span>
                </button>
              </div>
            </div>
          </div>

          {/* Table */}
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Reference No.
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Property Owner
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Property Location
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Building Info
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Land Area
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Annual Tax
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Approval Date
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {properties.length === 0 ? (
                  <tr>
                    <td colSpan="8" className="px-6 py-12 text-center">
                      <div className="text-gray-400 mb-2">
                        <svg className="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                      </div>
                      <p className="text-gray-500 text-lg font-medium mb-1">No approved properties found</p>
                      <p className="text-sm text-gray-400">Properties will appear here after they are approved.</p>
                    </td>
                  </tr>
                ) : (
                  properties.map((property) => (
                    <tr key={property.id} className="hover:bg-gray-50 transition-colors">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="font-mono font-semibold text-blue-600">
                          {property.reference_number}
                        </div>
                        <div className="text-xs text-gray-500 mt-1">
                          ID: {property.id}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="font-medium text-gray-900">{property.owner_name}</div>
                        <div className="text-sm text-gray-500">{property.email}</div>
                        <div className="text-sm text-gray-500">{property.phone}</div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="font-medium text-gray-900">{property.lot_location}</div>
                        <div className="text-sm text-gray-500">
                          {property.barangay}, {property.district}
                        </div>
                        {property.land_classification && (
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 mt-1">
                            {property.land_classification}
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex flex-col space-y-1">
                          <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                            property.has_building === 'yes' 
                              ? 'bg-green-100 text-green-800 border border-green-200' 
                              : 'bg-gray-100 text-gray-800 border border-gray-200'
                          }`}>
                            {property.has_building === 'yes' ? 'With Building' : 'Vacant Land'}
                          </span>
                          {property.has_building === 'yes' && property.building_count > 0 && (
                            <span className="text-xs text-gray-500">
                              {property.building_count} building{property.building_count !== 1 ? 's' : ''}
                            </span>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {property.land_area_sqm ? `${property.land_area_sqm} sqm` : 'N/A'}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm font-semibold text-gray-900">
                          {formatCurrency(parseFloat(property.total_annual_tax))}
                        </div>
                        {property.land_annual_tax && parseFloat(property.land_annual_tax) > 0 && (
                          <div className="text-xs text-gray-500">
                            Land: {formatCurrency(parseFloat(property.land_annual_tax))}
                          </div>
                        )}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {formatDate(property.approval_date)}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button
                          onClick={() => handleViewDetails(property.id)}
                          className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition-colors inline-flex items-center space-x-2"
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

          {/* Footer */}
          {properties.length > 0 && (
            <div className="bg-gray-50 px-6 py-3 border-t border-gray-200">
              <div className="flex flex-col md:flex-row md:items-center md:justify-between text-sm text-gray-500">
                <div>
                  Showing <span className="font-medium">{properties.length}</span> approved propert{properties.length === 1 ? 'y' : 'ies'}
                </div>
                <div className="mt-2 md:mt-0">
                  <span className="font-medium">Total Annual Tax: </span>
                  {formatCurrency(properties.reduce((sum, prop) => sum + parseFloat(prop.total_annual_tax || 0), 0))}
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Summary Stats */}
        {properties.length > 0 && (
          <div className="mt-6 bg-white rounded-xl shadow-lg border border-gray-200 p-6">
            <h3 className="text-md font-semibold text-gray-900 mb-4">Property Summary</h3>
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div className="text-sm text-blue-700 mb-1">Total Properties</div>
                <div className="text-2xl font-bold text-blue-900">{properties.length}</div>
              </div>
              <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                <div className="text-sm text-green-700 mb-1">Properties with Buildings</div>
                <div className="text-2xl font-bold text-green-900">
                  {properties.filter(p => p.has_building === 'yes').length}
                </div>
              </div>
              <div className="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <div className="text-sm text-purple-700 mb-1">Total Land Area</div>
                <div className="text-2xl font-bold text-purple-900">
                  {properties.reduce((sum, prop) => sum + parseFloat(prop.land_area_sqm || 0), 0).toLocaleString()} sqm
                </div>
              </div>
              <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div className="text-sm text-yellow-700 mb-1">Total Annual Revenue</div>
                <div className="text-2xl font-bold text-yellow-900">
                  {formatCurrency(properties.reduce((sum, prop) => sum + parseFloat(prop.total_annual_tax || 0), 0))}
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}