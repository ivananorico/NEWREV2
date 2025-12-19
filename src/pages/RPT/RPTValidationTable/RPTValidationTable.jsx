import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";

export default function RPTValidationTable() {
  const [registrations, setRegistrations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [debugInfo, setDebugInfo] = useState({});
  const navigate = useNavigate();

  // Dynamic API configuration for both localhost and production
  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend"; // Relative path for production

  const API_PATH = "/RPT/RPTValidationTable";

  const fetchRegistrations = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const url = `${API_BASE}${API_PATH}/get_registrations.php`;
      console.log(`🌐 Fetching from: ${url}`);
      
      // FIXED: Remove credentials or use 'omit' mode
      const response = await fetch(url, {
        method: 'GET',
        mode: 'cors',
        credentials: 'omit', // Changed from 'include' to 'omit'
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      });
      
      console.log("Response status:", response.status);
      
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status} - ${response.statusText}`);
      }
      
      const text = await response.text();
      console.log("Raw response (first 500 chars):", text.substring(0, 500) + (text.length > 500 ? '...' : ''));
      
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        console.error("JSON parse error:", parseError);
        setDebugInfo({
          rawResponse: text.substring(0, 500),
          parseError: parseError.message,
          url: url,
          status: response.status
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
      // Format 5: {error: "message"} or similar
      else if (data.error) {
        throw new Error(data.error);
      }
      // Format 6: {message: "some message"}
      else if (data.message) {
        throw new Error(data.message);
      }
      else {
        console.error("❌ Unknown data format:", data);
        setDebugInfo({
          dataStructure: data,
          availableKeys: Object.keys(data),
          url: url
        });
        throw new Error(`Unexpected response format. Available keys: ${Object.keys(data).join(', ')}`);
      }
      
      // Filter out approved registrations to only show pending ones
      const filteredRegistrations = registrationsData.filter(r => r.status !== "approved");
      
      console.log(`✅ Loaded ${registrationsData.length} total, ${filteredRegistrations.length} pending`);
      setRegistrations(filteredRegistrations);
      
    } catch (err) {
      console.error("Fetch error:", err);
      setError(`Failed to fetch registrations: ${err.message}`);
      
      // Show debug info for localhost
      if (window.location.hostname === "localhost") {
        setDebugInfo(prev => ({
          ...prev,
          lastError: err.message,
          timestamp: new Date().toISOString()
        }));
      }
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
      rejected: "bg-red-100 text-red-800 border border-red-200",
      resubmitted: "bg-indigo-100 text-indigo-800 border border-indigo-200"
    };
    return (
      <span className={`px-3 py-1 rounded-full text-xs font-medium ${map[status] || "bg-gray-100 text-gray-800 border"}`}>
        {status ? status.replace("_", " ").toUpperCase() : "UNKNOWN"}
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
              <p className="text-gray-800 font-semibold text-lg mb-2">Loading Registrations</p>
              <p className="text-gray-500 text-sm">Please wait while we fetch the latest applications...</p>
              {window.location.hostname === "localhost" && (
                <p className="text-xs text-blue-500 mt-4">
                  API: {API_BASE}{API_PATH}/get_registrations.php
                </p>
              )}
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
            <p className="text-gray-600 mb-6">{error}</p>
            
            {/* Enhanced Debug Info for localhost */}
            {window.location.hostname === "localhost" && (
              <div className="bg-gray-50 p-4 rounded-lg mb-6 text-left border border-gray-200">
                <p className="text-sm font-semibold text-gray-700 mb-2">Debug Information:</p>
                <div className="space-y-1 text-sm text-gray-600">
                  <p><span className="font-medium">API URL:</span> {API_BASE}{API_PATH}/get_registrations.php</p>
                  <p><span className="font-medium">Frontend:</span> {window.location.origin}</p>
                  <p><span className="font-medium">Backend:</span> {API_BASE}</p>
                  <p><span className="font-medium">CORS Solution:</span> credentials set to 'omit'</p>
                </div>
                
                {debugInfo.rawResponse && (
                  <div className="mt-3">
                    <p className="text-xs font-semibold text-gray-700 mb-1">Server Response (first 500 chars):</p>
                    <pre className="text-xs bg-gray-800 text-white p-2 rounded overflow-auto max-h-32">
                      {debugInfo.rawResponse}
                    </pre>
                  </div>
                )}
                
                <div className="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                  <p className="text-sm font-medium text-yellow-800 mb-1">Troubleshooting:</p>
                  <p className="text-xs text-yellow-700">
                    1. Check if PHP file exists at: {API_BASE}{API_PATH}/get_registrations.php<br/>
                    2. Open the URL directly in browser to test<br/>
                    3. Check browser console for detailed errors
                  </p>
                </div>
              </div>
            )}
            
            <div className="space-y-3">
              <button
                onClick={handleRetry}
                className="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5"
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
              <h1 className="text-3xl font-bold mb-2">Property Registration Applications</h1>
              <p className="text-blue-100 opacity-90">
                LGU Registration System • Pending Applications
              </p>
            </div>
            <div className="mt-6 lg:mt-0 flex flex-wrap gap-3">
              <button
                onClick={fetchRegistrations}
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
          {/* Pending Applications */}
          <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 transform hover:-translate-y-1 transition-transform duration-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-500 text-sm font-medium mb-1">Pending Review</p>
                <p className="text-3xl font-bold text-yellow-900">
                  {registrations.filter(r => r.status === 'pending').length}
                </p>
              </div>
              <div className="bg-yellow-100 p-3 rounded-xl">
                <span className="text-2xl text-yellow-600">⏳</span>
              </div>
            </div>
            <div className="mt-4 pt-4 border-t border-gray-100">
              <p className="text-xs text-gray-500">Awaiting initial review</p>
            </div>
          </div>

          {/* For Inspection */}
          <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 transform hover:-translate-y-1 transition-transform duration-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-500 text-sm font-medium mb-1">For Inspection</p>
                <p className="text-3xl font-bold text-blue-900">
                  {registrations.filter(r => r.status === 'for_inspection').length}
                </p>
              </div>
              <div className="bg-blue-100 p-3 rounded-xl">
                <span className="text-2xl text-blue-600">🔍</span>
              </div>
            </div>
            <div className="mt-4 pt-4 border-t border-gray-100">
              <p className="text-xs text-gray-500">Scheduled for site inspection</p>
            </div>
          </div>

          {/* Needs Correction */}
          <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 transform hover:-translate-y-1 transition-transform duration-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-500 text-sm font-medium mb-1">Needs Correction</p>
                <p className="text-3xl font-bold text-orange-900">
                  {registrations.filter(r => r.status === 'needs_correction').length}
                </p>
              </div>
              <div className="bg-orange-100 p-3 rounded-xl">
                <span className="text-2xl text-orange-600">📝</span>
              </div>
            </div>
            <div className="mt-4 pt-4 border-t border-gray-100">
              <p className="text-xs text-gray-500">Requires applicant corrections</p>
            </div>
          </div>

          {/* Total Applications */}
          <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 transform hover:-translate-y-1 transition-transform duration-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-500 text-sm font-medium mb-1">Total Applications</p>
                <p className="text-3xl font-bold text-gray-900">
                  {registrations.length}
                </p>
              </div>
              <div className="bg-gray-100 p-3 rounded-xl">
                <span className="text-2xl text-gray-600">📋</span>
              </div>
            </div>
            <div className="mt-4 pt-4 border-t border-gray-100">
              <p className="text-xs text-gray-500">All pending applications</p>
            </div>
          </div>
        </div>

        {/* Applications Table */}
        <div className="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden mb-8">
          <div className="p-6 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white">
            <div className="flex flex-col md:flex-row md:items-center md:justify-between">
              <div>
                <h2 className="text-xl font-bold text-gray-900">Pending Applications</h2>
                <p className="text-gray-600 text-sm mt-1">
                  {registrations.length} application{registrations.length === 1 ? '' : 's'} requiring action
                </p>
              </div>
              <div className="mt-4 md:mt-0 flex items-center space-x-2">
                <span className="text-sm text-gray-500">Filter:</span>
                <select className="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm">
                  <option>All Status</option>
                  <option>Pending</option>
                  <option>For Inspection</option>
                  <option>Needs Correction</option>
                  <option>Assessed</option>
                </select>
              </div>
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                    Application Details
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                    Applicant Information
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                    Property Details
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                    Status
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                    Date Submitted
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {registrations.length === 0 ? (
                  <tr>
                    <td colSpan="6" className="px-6 py-16 text-center">
                      <div className="text-gray-400 mb-4">
                        <svg className="mx-auto h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                      </div>
                      <p className="text-gray-500 text-lg font-medium mb-2">No pending applications</p>
                      <p className="text-sm text-gray-400">
                        All applications have been processed. Great work! 🎉
                      </p>
                    </td>
                  </tr>
                ) : (
                  registrations.map((registration) => (
                    <tr key={registration.id} className="hover:bg-blue-50/50 transition-colors">
                      <td className="px-6 py-4">
                        <div className="font-mono font-bold text-blue-700 text-sm">
                          {registration.reference_number}
                        </div>
                        <div className="text-xs text-gray-500 mt-1">
                          ID: {registration.id}
                        </div>
                        {registration.correction_notes && (
                          <div className="text-xs text-red-600 mt-2">
                            <span className="font-medium">Notes:</span> {registration.correction_notes.substring(0, 50)}...
                          </div>
                        )}
                      </td>
                      <td className="px-6 py-4">
                        <div className="font-semibold text-gray-900">{registration.owner_name}</div>
                        <div className="text-sm text-gray-500 mt-1">{registration.email}</div>
                        <div className="text-sm text-gray-500">{registration.phone}</div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="font-medium text-gray-900">{registration.lot_location}</div>
                        <div className="text-sm text-gray-500">
                          {registration.barangay}, Dist. {registration.district}
                        </div>
                        <div className="flex items-center mt-2">
                          <span className={`inline-flex items-center px-2 py-1 rounded text-xs font-medium ${
                            registration.has_building === 'yes' 
                              ? 'bg-green-100 text-green-800 border border-green-200' 
                              : 'bg-gray-100 text-gray-800 border'
                          }`}>
                            {registration.has_building === 'yes' ? '🏠 Has Building' : '🌱 Vacant Land'}
                          </span>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex flex-col space-y-2">
                          {getStatusBadge(registration.status)}
                          {registration.status === 'needs_correction' && (
                            <span className="text-xs text-red-600 font-medium">
                              ⚠️ Requires action
                            </span>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="text-sm text-gray-900">
                          {formatDate(registration.created_at)}
                        </div>
                        <div className="text-xs text-gray-500">
                          Submitted
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex flex-col space-y-2">
                          <button
                            onClick={() => handleViewDetails(registration.id)}
                            className="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md flex items-center justify-center space-x-2"
                          >
                            <span>👁️</span>
                            <span>Review</span>
                          </button>
                          <button className="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            📧 Contact Applicant
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {/* Table Footer */}
          {registrations.length > 0 && (
            <div className="bg-gray-50 px-6 py-4 border-t border-gray-200">
              <div className="flex flex-col md:flex-row md:items-center md:justify-between">
                <div className="text-sm text-gray-600">
                  Showing <span className="font-semibold">{registrations.length}</span> pending application{registrations.length === 1 ? '' : 's'}
                </div>
                <div className="mt-2 md:mt-0 flex space-x-4">
                  <div className="text-sm text-gray-600">
                    <span className="font-semibold">Status Breakdown: </span>
                    <span className="text-yellow-600">{registrations.filter(r => r.status === 'pending').length} pending</span>
                    {', '}
                    <span className="text-blue-600">{registrations.filter(r => r.status === 'for_inspection').length} inspection</span>
                    {', '}
                    <span className="text-orange-600">{registrations.filter(r => r.status === 'needs_correction').length} correction</span>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Quick Actions Panel */}
        <div className="bg-gradient-to-r from-blue-600 to-blue-700 rounded-2xl shadow-lg p-8 mb-8">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between">
            <div className="text-white">
              <h3 className="text-xl font-bold mb-2">Need to manage applications?</h3>
              <p className="text-blue-100 opacity-90">Access additional tools and reports</p>
            </div>
            <div className="mt-6 md:mt-0 flex flex-wrap gap-3">
              <button className="bg-white text-blue-700 hover:bg-blue-50 font-semibold px-5 py-3 rounded-xl transition-colors">
                📊 Export to Excel
              </button>
              <button className="bg-blue-800 hover:bg-blue-900 text-white font-semibold px-5 py-3 rounded-xl border border-blue-600 transition-colors">
                🖨️ Print List
              </button>
              <button className="bg-transparent hover:bg-blue-800 text-white font-semibold px-5 py-3 rounded-xl border border-white transition-colors">
                📈 View Statistics
              </button>
            </div>
          </div>
        </div>

        {/* Footer Info */}
        <div className="text-center text-gray-500 text-sm mb-8">
          <p>© {new Date().getFullYear()} Local Government Unit • Property Registration System</p>
          <p className="mt-1">Last updated: {new Date().toLocaleString()}</p>
        </div>
      </div>
    </div>
  );
}