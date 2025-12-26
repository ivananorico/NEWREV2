import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { Search, Filter, Eye, Download, RefreshCw, AlertCircle, CheckCircle, Clock, Building } from "lucide-react";

export default function RPTValidationTable() {
  const [registrations, setRegistrations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const navigate = useNavigate();

  // API Configuration
  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

  const fetchRegistrations = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(`${API_BASE}/RPT/RPTValidationTable/get_registrations.php`, {
        method: 'GET',
        credentials: 'omit',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      });
      
      if (!response.ok) {
        throw new Error(`Server error: ${response.status}`);
      }
      
      const data = await response.json();
      
      // Extract registrations from different possible formats
      let registrationsData = [];
      
      if (data.success && Array.isArray(data.data)) {
        registrationsData = data.data;
      } else if (data.status === "success" && Array.isArray(data.registrations)) {
        registrationsData = data.registrations;
      } else if (Array.isArray(data)) {
        registrationsData = data;
      } else {
        throw new Error("Unexpected response format");
      }
      
      // Filter out approved registrations
      const filteredRegistrations = registrationsData.filter(r => r.status !== "approved");
      setRegistrations(filteredRegistrations);
      
    } catch (err) {
      console.error("Fetch error:", err);
      setError(`Failed to load applications: ${err.message}`);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRegistrations();
  }, []);

  // Filter registrations based on search and status
  const filteredRegistrations = registrations.filter(reg => {
    const matchesSearch = 
      reg.owner_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      reg.reference_number?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      reg.lot_location?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      reg.barangay?.toLowerCase().includes(searchTerm.toLowerCase());
    
    const matchesStatus = statusFilter === "all" || reg.status === statusFilter;
    
    return matchesSearch && matchesStatus;
  });

  const getStatusInfo = (status) => {
    const statusMap = {
      pending: { 
        label: "Pending", 
        color: "bg-yellow-100 text-yellow-800 border-yellow-200",
        icon: Clock 
      },
      for_inspection: { 
        label: "For Inspection", 
        color: "bg-blue-100 text-blue-800 border-blue-200",
        icon: Eye 
      },
      needs_correction: { 
        label: "Needs Correction", 
        color: "bg-orange-100 text-orange-800 border-orange-200",
        icon: AlertCircle 
      },
      assessed: { 
        label: "Assessed", 
        color: "bg-purple-100 text-purple-800 border-purple-200",
        icon: CheckCircle 
      },
      approved: { 
        label: "Approved", 
        color: "bg-green-100 text-green-800 border-green-200",
        icon: CheckCircle 
      }
    };
    
    return statusMap[status] || { label: status, color: "bg-gray-100 text-gray-800", icon: Clock };
  };

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

  const handleViewDetails = (id) => {
    navigate(`/rpt/rptvalidationinfo/${id}`);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading property applications...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-lg shadow-md p-6 max-w-md w-full border border-gray-200">
          <div className="text-center">
            <AlertCircle className="w-12 h-12 text-red-500 mx-auto mb-4" />
            <h2 className="text-xl font-semibold text-gray-800 mb-2">Connection Error</h2>
            <p className="text-gray-600 mb-4">{error}</p>
            <button
              onClick={fetchRegistrations}
              className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium"
            >
              Retry Connection
            </button>
          </div>
        </div>
      </div>
    );
  }

  // Count registrations by status
  const statusCounts = {
    pending: registrations.filter(r => r.status === 'pending').length,
    for_inspection: registrations.filter(r => r.status === 'for_inspection').length,
    needs_correction: registrations.filter(r => r.status === 'needs_correction').length,
    assessed: registrations.filter(r => r.status === 'assessed').length,
    total: registrations.length
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Property Tax Applications</h1>
              <p className="text-gray-600 mt-1">Review and process property registration applications</p>
            </div>
            <div className="mt-4 sm:mt-0 flex items-center space-x-3">
              <button
                onClick={fetchRegistrations}
                className="flex items-center space-x-2 bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
              >
                <RefreshCw className="w-4 h-4" />
                <span>Refresh</span>
              </button>
              <button className="flex items-center space-x-2 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-md text-sm font-medium">
                <Download className="w-4 h-4" />
                <span>Export</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Status Summary */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
          <div className="bg-white rounded-lg border border-gray-200 p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Total Pending</p>
                <p className="text-2xl font-bold text-gray-900">{statusCounts.total}</p>
              </div>
              <div className="bg-gray-100 p-2 rounded">
                <Building className="w-6 h-6 text-gray-600" />
              </div>
            </div>
          </div>
          
          <div className="bg-white rounded-lg border border-gray-200 p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Pending Review</p>
                <p className="text-2xl font-bold text-yellow-600">{statusCounts.pending}</p>
              </div>
              <div className="bg-yellow-100 p-2 rounded">
                <Clock className="w-6 h-6 text-yellow-600" />
              </div>
            </div>
          </div>
          
          <div className="bg-white rounded-lg border border-gray-200 p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">For Inspection</p>
                <p className="text-2xl font-bold text-blue-600">{statusCounts.for_inspection}</p>
              </div>
              <div className="bg-blue-100 p-2 rounded">
                <Eye className="w-6 h-6 text-blue-600" />
              </div>
            </div>
          </div>
          
          <div className="bg-white rounded-lg border border-gray-200 p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Needs Correction</p>
                <p className="text-2xl font-bold text-orange-600">{statusCounts.needs_correction}</p>
              </div>
              <div className="bg-orange-100 p-2 rounded">
                <AlertCircle className="w-6 h-6 text-orange-600" />
              </div>
            </div>
          </div>
          
          <div className="bg-white rounded-lg border border-gray-200 p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Assessed</p>
                <p className="text-2xl font-bold text-purple-600">{statusCounts.assessed}</p>
              </div>
              <div className="bg-purple-100 p-2 rounded">
                <CheckCircle className="w-6 h-6 text-purple-600" />
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
                  placeholder="Search by owner name, reference number, or location..."
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
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className="pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none bg-white"
                >
                  <option value="all">All Status</option>
                  <option value="pending">Pending</option>
                  <option value="for_inspection">For Inspection</option>
                  <option value="needs_correction">Needs Correction</option>
                  <option value="assessed">Assessed</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        {/* Applications Table */}
        <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 className="text-lg font-semibold text-gray-900">Applications</h2>
            <p className="text-sm text-gray-600 mt-1">
              {filteredRegistrations.length} application{filteredRegistrations.length !== 1 ? 's' : ''} found
            </p>
          </div>
          
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference No.</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Property Owner</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Property Location</th>
                  <th className="px-6 py-3 text-left text-xs font-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Submitted</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {filteredRegistrations.length === 0 ? (
                  <tr>
                    <td colSpan="6" className="px-6 py-12 text-center">
                      <div className="text-gray-400 mb-4">
                        <Building className="w-12 h-12 mx-auto" />
                      </div>
                      <p className="text-gray-500 font-medium mb-2">No applications found</p>
                      <p className="text-sm text-gray-400">
                        {searchTerm || statusFilter !== "all" 
                          ? "Try adjusting your search or filter" 
                          : "No pending applications available"}
                      </p>
                    </td>
                  </tr>
                ) : (
                  filteredRegistrations.map((registration) => {
                    const statusInfo = getStatusInfo(registration.status);
                    const StatusIcon = statusInfo.icon;
                    
                    return (
                      <tr key={registration.id} className="hover:bg-gray-50">
                        <td className="px-6 py-4">
                          <div className="font-medium text-gray-900">{registration.reference_number}</div>
                          <div className="text-sm text-gray-500">ID: {registration.id}</div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="font-medium text-gray-900">{registration.owner_name}</div>
                          <div className="text-sm text-gray-500">{registration.email}</div>
                          <div className="text-sm text-gray-500">{registration.phone}</div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="font-medium text-gray-900">{registration.lot_location}</div>
                          <div className="text-sm text-gray-500">
                            {registration.barangay}, Dist. {registration.district}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex items-center space-x-2">
                            <StatusIcon className="w-4 h-4" />
                            <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${statusInfo.color} border`}>
                              {statusInfo.label}
                            </span>
                          </div>
                          {registration.correction_notes && (
                            <div className="text-xs text-orange-600 mt-1 max-w-xs truncate">
                              {registration.correction_notes}
                            </div>
                          )}
                        </td>
                        <td className="px-6 py-4">
                          <div className="text-sm text-gray-900">{formatDate(registration.created_at)}</div>
                        </td>
                        <td className="px-6 py-4">
                          <button
                            onClick={() => handleViewDetails(registration.id)}
                            className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center space-x-2"
                          >
                            <Eye className="w-4 h-4" />
                            <span>Review</span>
                          </button>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
          
          {/* Table Footer */}
          {filteredRegistrations.length > 0 && (
            <div className="px-6 py-4 border-t border-gray-200 bg-gray-50">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div className="text-sm text-gray-600">
                  Showing {filteredRegistrations.length} of {registrations.length} pending applications
                </div>
                <div className="mt-2 sm:mt-0 text-sm text-gray-600">
                  <span className="font-medium">Status: </span>
                  <span className="text-yellow-600">{statusCounts.pending} pending</span>
                  {", "}
                  <span className="text-blue-600">{statusCounts.for_inspection} inspection</span>
                  {", "}
                  <span className="text-orange-600">{statusCounts.needs_correction} correction</span>
                  {", "}
                  <span className="text-purple-600">{statusCounts.assessed} assessed</span>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="mt-8 text-center text-gray-500 text-sm">
          <p>Local Government Unit - Property Tax System</p>
          <p className="mt-1">Last updated: {new Date().toLocaleTimeString()}</p>
        </div>
      </div>
    </div>
  );
}