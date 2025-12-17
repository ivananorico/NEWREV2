import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";

export default function RPTValidationTable() {
  const [registrations, setRegistrations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [debugInfo, setDebugInfo] = useState({});
  const navigate = useNavigate();

  // 🔥 WORKS BOTH LOCAL & DOMAIN
  const API_BASE =
    window.location.hostname === "localhost"
      ? "http://localhost/revenue/backend"
      : "https://revenuetreasury.goserveph.com/backend";

  const fetchRegistrations = async () => {
    try {
      console.log(`🌐 Fetching from: ${API_BASE}/RPT/RPTValidationTable/get_registrations.php`);
      
      const response = await fetch(
        `${API_BASE}/RPT/RPTValidationTable/get_registrations.php`,
        {
          credentials: 'include',
          headers: {
            'Accept': 'application/json'
          }
        }
      );
      
      console.log("Response status:", response.status);
      
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      
      const text = await response.text();
      console.log("Raw response:", text);
      
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        console.error("JSON parse error:", parseError);
        setDebugInfo({
          rawResponse: text.substring(0, 500),
          parseError: parseError.message
        });
        throw new Error("Invalid JSON response from server");
      }
      
      console.log("Parsed data structure:", data);
      
      // DEBUG: Log the exact structure
      console.log("Data keys:", Object.keys(data));
      if (data.success !== undefined) console.log("data.success:", data.success);
      if (data.status !== undefined) console.log("data.status:", data.status);
      if (data.data !== undefined) console.log("data.data type:", typeof data.data, "length:", Array.isArray(data.data) ? data.data.length : "N/A");
      if (data.registrations !== undefined) console.log("data.registrations type:", typeof data.registrations, "length:", Array.isArray(data.registrations) ? data.registrations.length : "N/A");
      
      // Try to extract registrations from various possible formats
      let registrationsData = [];
      
      // Format 1: {success: true, data: [...]} - NEW format
      if (data.success !== undefined && data.data !== undefined && Array.isArray(data.data)) {
        console.log("✅ Using format: success + data");
        registrationsData = data.data;
      }
      // Format 2: {status: "success", registrations: [...]} - OLD format
      else if (data.status === "success" && data.registrations !== undefined && Array.isArray(data.registrations)) {
        console.log("✅ Using format: status + registrations");
        registrationsData = data.registrations;
      }
      // Format 3: Direct array [...]
      else if (Array.isArray(data)) {
        console.log("✅ Using format: direct array");
        registrationsData = data;
      }
      // Format 4: {success: true, registrations: [...]} - Hybrid
      else if (data.success !== undefined && data.registrations !== undefined && Array.isArray(data.registrations)) {
        console.log("✅ Using format: success + registrations");
        registrationsData = data.registrations;
      }
      else {
        console.error("❌ Unknown data format:", data);
        setDebugInfo({
          dataStructure: data,
          availableKeys: Object.keys(data)
        });
        throw new Error(`Unexpected response format. Available keys: ${Object.keys(data).join(', ')}`);
      }
      
      // Filter out approved registrations
      const filteredRegistrations = registrationsData.filter(r => r.status !== "approved");
      
      console.log(`✅ Loaded ${registrationsData.length} total, ${filteredRegistrations.length} pending`);
      setRegistrations(filteredRegistrations);
      
    } catch (err) {
      console.error("Fetch error:", err);
      setError(`Failed to fetch registrations: ${err.message}`);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRegistrations();
  }, []);

  const getStatusBadge = (status) => {
    const map = {
      pending: "bg-yellow-100 text-yellow-800 border border-yellow-200",
      for_inspection: "bg-blue-100 text-blue-800 border border-blue-200",
      needs_correction: "bg-orange-100 text-orange-800 border border-orange-200",
      assessed: "bg-purple-100 text-purple-800 border border-purple-200",
      approved: "bg-green-100 text-green-800 border border-green-200",
    };
    return (
      <span className={`px-3 py-1 rounded-full text-xs font-medium ${map[status] || "bg-gray-100 text-gray-800 border"}`}>
        {status.replace("_", " ").toUpperCase()}
      </span>
    );
  };

  const formatDate = (dateString) => {
    if (!dateString) return "N/A";
    try {
      return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
      });
    } catch (err) {
      return dateString;
    }
  };

  const handleViewDetails = (id) => {
    navigate(`/rpt/rptvalidationinfo/${id}`);
  };

  const handleRetry = () => {
    setLoading(true);
    setError(null);
    fetchRegistrations();
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p className="text-gray-600">Loading registrations...</p>
          <p className="text-sm text-gray-500 mt-2">API: {API_BASE}</p>
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
              <p className="text-xs">Path: /RPT/RPTValidationTable/get_registrations.php</p>
              <p className="text-xs">Environment: Development</p>
              
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
                onClick={() => window.location.reload()}
                className="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition-colors"
              >
                Refresh Page
              </button>
              <button
                onClick={handleRetry}
                className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors"
              >
                Try Again
              </button>
              <button
                onClick={() => {
                  const url = `${API_BASE}/RPT/RPTValidationTable/get_registrations.php`;
                  window.open(url, '_blank');
                }}
                className="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition-colors"
              >
                Test API Directly
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
              <h1 className="text-2xl font-bold text-gray-900">Property Registration Applications</h1>
              <p className="text-gray-600 mt-1">Manage and validate property registration requests</p>
            </div>
            <div className="mt-4 lg:mt-0">
              <div className="flex items-center space-x-2 text-gray-600">
                <div className="w-3 h-3 bg-green-500 rounded-full"></div>
                <span className="text-sm">Approved: {registrations.filter(r => r.status === 'approved').length}</span>
                <div className="w-3 h-3 bg-blue-500 rounded-full"></div>
                <span className="text-sm">Pending: {registrations.filter(r => r.status !== 'approved').length}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Debug info in development */}
        {window.location.hostname === "localhost" && (
          <div className="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div className="flex items-center">
              <span className="text-yellow-600 mr-2">🔧</span>
              <div>
                <p className="text-sm font-medium text-yellow-800">Development Mode</p>
                <p className="text-xs text-yellow-700">
                  API: {API_BASE}/RPT/RPTValidationTable/get_registrations.php
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
                <h2 className="text-lg font-bold text-gray-900">Registration List</h2>
                <p className="text-gray-600 text-sm mt-1">
                  Showing {registrations.length} {registrations.length === 1 ? 'registration' : 'registrations'}
                </p>
              </div>
              <div className="mt-4 md:mt-0 flex space-x-3">
                <button
                  onClick={fetchRegistrations}
                  className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition-colors flex items-center space-x-2"
                >
                  <span>🔄</span>
                  <span>Refresh</span>
                </button>
                <button
                  onClick={() => {
                    const url = `${API_BASE}/RPT/RPTValidationTable/get_registrations.php`;
                    window.open(url, '_blank');
                    console.log("API Response structure:", fetchRegistrations);
                  }}
                  className="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm transition-colors flex items-center space-x-2"
                >
                  <span>📡</span>
                  <span>View API</span>
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
                    Owner Information
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Property Location
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Date Submitted
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {registrations.length === 0 ? (
                  <tr>
                    <td colSpan="6" className="px-6 py-12 text-center">
                      <div className="text-gray-400 mb-2">
                        <svg className="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                      </div>
                      <p className="text-gray-500">No pending applications found</p>
                      <p className="text-sm text-gray-400 mt-1">All applications have been approved or processed</p>
                    </td>
                  </tr>
                ) : (
                  registrations.map((registration) => (
                    <tr key={registration.id} className="hover:bg-gray-50 transition-colors">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="font-mono font-semibold text-blue-600">
                          {registration.reference_number}
                        </div>
                        <div className="text-xs text-gray-500 mt-1">
                          ID: {registration.id}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="font-medium text-gray-900">{registration.owner_name}</div>
                        <div className="text-sm text-gray-500">{registration.email}</div>
                        <div className="text-sm text-gray-500">{registration.phone}</div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="font-medium text-gray-900">{registration.lot_location}</div>
                        <div className="text-sm text-gray-500">
                          {registration.barangay}, {registration.district}
                        </div>
                        {registration.has_building === 'yes' && (
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mt-1">
                            Has Building
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {getStatusBadge(registration.status)}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {formatDate(registration.created_at)}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button
                          onClick={() => handleViewDetails(registration.id)}
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
          {registrations.length > 0 && (
            <div className="bg-gray-50 px-6 py-3 border-t border-gray-200">
              <div className="flex flex-col md:flex-row md:items-center md:justify-between text-sm text-gray-500">
                <div>
                  Showing <span className="font-medium">{registrations.length}</span> of{" "}
                  <span className="font-medium">{registrations.length}</span> applications
                </div>
                <div className="mt-2 md:mt-0">
                  <span className="font-medium">Last updated: </span>
                  {new Date().toLocaleTimeString()}
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}