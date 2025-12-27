import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { 
  Search, 
  Filter, 
  Eye, 
  Download, 
  RefreshCw, 
  AlertCircle, 
  CheckCircle, 
  Clock, 
  Building,
  FileText,
  Home,
  MapPin,
  User,
  Calendar,
  Hash
} from "lucide-react";

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
      
      // Filter out approved registrations - EXCLUDE APPROVED STATUS
      const filteredRegistrations = registrationsData.filter(r => 
        r.status !== "approved" && r.status !== "Approved"
      );
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
      (reg.owner_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (reg.reference_number?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (reg.lot_location?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (reg.barangay?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (reg.first_name && reg.last_name ? 
        `${reg.first_name} ${reg.last_name}`.toLowerCase().includes(searchTerm.toLowerCase()) : false);
    
    const matchesStatus = statusFilter === "all" || reg.status === statusFilter;
    
    return matchesSearch && matchesStatus;
  });

  const getStatusInfo = (status) => {
    const statusMap = {
      pending: { 
        label: "Pending Review", 
        color: "bg-yellow-50 text-yellow-800 border-yellow-200",
        icon: Clock,
        bgColor: "bg-yellow-100"
      },
      for_inspection: { 
        label: "For Inspection", 
        color: "bg-blue-50 text-blue-800 border-blue-200",
        icon: Eye,
        bgColor: "bg-blue-100"
      },
      needs_correction: { 
        label: "Needs Correction", 
        color: "bg-orange-50 text-orange-800 border-orange-200",
        icon: AlertCircle,
        bgColor: "bg-orange-100"
      },
      assessed: { 
        label: "Assessed", 
        color: "bg-purple-50 text-purple-800 border-purple-200",
        icon: CheckCircle,
        bgColor: "bg-purple-100"
      },
      resubmitted: { 
        label: "Resubmitted", 
        color: "bg-indigo-50 text-indigo-800 border-indigo-200",
        icon: RefreshCw,
        bgColor: "bg-indigo-100"
      }
    };
    
    return statusMap[status] || { 
      label: status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()), 
      color: "bg-gray-50 text-gray-800 border-gray-200",
      icon: FileText,
      bgColor: "bg-gray-100"
    };
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

  const formatTimeAgo = (dateString) => {
    if (!dateString) return "";
    try {
      const date = new Date(dateString);
      const now = new Date();
      const diffMs = now - date;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMs / 3600000);
      const diffDays = Math.floor(diffMs / 86400000);
      
      if (diffMins < 60) {
        return `${diffMins} min${diffMins !== 1 ? 's' : ''} ago`;
      } else if (diffHours < 24) {
        return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
      } else if (diffDays < 7) {
        return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
      } else {
        return formatDate(dateString);
      }
    } catch (err) {
      return "";
    }
  };

  const handleViewDetails = (id) => {
    navigate(`/rpt/rptvalidationinfo/${id}`);
  };

  // Count registrations by status (excluding approved)
  const statusCounts = {
    pending: registrations.filter(r => r.status === 'pending').length,
    for_inspection: registrations.filter(r => r.status === 'for_inspection').length,
    needs_correction: registrations.filter(r => r.status === 'needs_correction').length,
    assessed: registrations.filter(r => r.status === 'assessed').length,
    resubmitted: registrations.filter(r => r.status === 'resubmitted').length,
    total: registrations.length
  };

  // Status options for filter dropdown
  const statusOptions = [
    { value: "all", label: "All Status", count: registrations.length },
    { value: "pending", label: "Pending Review", count: statusCounts.pending },
    { value: "for_inspection", label: "For Inspection", count: statusCounts.for_inspection },
    { value: "needs_correction", label: "Needs Correction", count: statusCounts.needs_correction },
    { value: "assessed", label: "Assessed", count: statusCounts.assessed },
    { value: "resubmitted", label: "Resubmitted", count: statusCounts.resubmitted }
  ];

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center p-4">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600 font-medium">Loading property applications...</p>
          <p className="text-sm text-gray-400 mt-2">Fetching data from server</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center p-4">
        <div className="bg-white rounded-xl shadow-lg p-8 max-w-md w-full border border-gray-200">
          <div className="text-center">
            <div className="bg-red-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
              <AlertCircle className="w-8 h-8 text-red-500" />
            </div>
            <h2 className="text-xl font-bold text-gray-800 mb-2">Connection Error</h2>
            <p className="text-gray-600 mb-4">{error}</p>
            <div className="space-y-3">
              <button
                onClick={fetchRegistrations}
                className="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg font-medium transition duration-200"
              >
                <RefreshCw className="w-4 h-4 inline-block mr-2" />
                Retry Connection
              </button>
              <button
                onClick={() => window.location.reload()}
                className="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-3 rounded-lg font-medium transition duration-200"
              >
                Reload Page
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
      {/* Header */}
      <div className="bg-white border-b border-gray-200 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="text-2xl md:text-3xl font-bold text-gray-900">Property Tax Applications</h1>
              <p className="text-gray-600 mt-2">Review and process property registration applications</p>
              <div className="flex items-center gap-2 mt-3">
                <div className="flex items-center gap-1 text-sm text-gray-500">
                  <Building className="w-4 h-4" />
                  <span>Excluding approved applications</span>
                </div>
                <div className="h-4 w-px bg-gray-300"></div>
                <div className="text-sm text-gray-500">
                  Last updated: {new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                </div>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <button
                onClick={fetchRegistrations}
                className="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2.5 rounded-lg font-medium transition duration-200"
              >
                <RefreshCw className="w-4 h-4" />
                <span className="hidden sm:inline">Refresh</span>
              </button>
              <button className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg font-medium transition duration-200">
                <Download className="w-4 h-4" />
                <span className="hidden sm:inline">Export</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Status Summary Cards */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
          {/* Total Pending Card */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:shadow-md transition duration-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 font-medium">Total Applications</p>
                <p className="text-3xl font-bold text-gray-900 mt-2">{statusCounts.total}</p>
                <p className="text-xs text-gray-500 mt-2">Excluding approved status</p>
              </div>
              <div className="bg-blue-50 p-3 rounded-lg">
                <Building className="w-6 h-6 text-blue-600" />
              </div>
            </div>
          </div>

          {/* Pending Review Card */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:shadow-md transition duration-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 font-medium">Pending Review</p>
                <p className="text-3xl font-bold text-yellow-600 mt-2">{statusCounts.pending}</p>
                <div className="flex items-center gap-1 text-xs text-gray-500 mt-2">
                  <Clock className="w-3 h-3" />
                  <span>Awaiting initial review</span>
                </div>
              </div>
              <div className="bg-yellow-50 p-3 rounded-lg">
                <Clock className="w-6 h-6 text-yellow-600" />
              </div>
            </div>
          </div>

          {/* For Inspection Card */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:shadow-md transition duration-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 font-medium">For Inspection</p>
                <p className="text-3xl font-bold text-blue-600 mt-2">{statusCounts.for_inspection}</p>
                <div className="flex items-center gap-1 text-xs text-gray-500 mt-2">
                  <Eye className="w-3 h-3" />
                  <span>Scheduled for site visit</span>
                </div>
              </div>
              <div className="bg-blue-50 p-3 rounded-lg">
                <Eye className="w-6 h-6 text-blue-600" />
              </div>
            </div>
          </div>
        </div>

        {/* Additional Status Cards - Second Row */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
          {/* Needs Correction Card */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:shadow-md transition duration-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 font-medium">Needs Correction</p>
                <p className="text-3xl font-bold text-orange-600 mt-2">{statusCounts.needs_correction}</p>
                <div className="flex items-center gap-1 text-xs text-gray-500 mt-2">
                  <AlertCircle className="w-3 h-3" />
                  <span>Requires owner action</span>
                </div>
              </div>
              <div className="bg-orange-50 p-3 rounded-lg">
                <AlertCircle className="w-6 h-6 text-orange-600" />
              </div>
            </div>
          </div>

          {/* Assessed Card */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:shadow-md transition duration-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 font-medium">Assessed</p>
                <p className="text-3xl font-bold text-purple-600 mt-2">{statusCounts.assessed}</p>
                <div className="flex items-center gap-1 text-xs text-gray-500 mt-2">
                  <CheckCircle className="w-3 h-3" />
                  <span>Ready for final approval</span>
                </div>
              </div>
              <div className="bg-purple-50 p-3 rounded-lg">
                <CheckCircle className="w-6 h-6 text-purple-600" />
              </div>
            </div>
          </div>

          {/* Resubmitted Card */}
          <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:shadow-md transition duration-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 font-medium">Resubmitted</p>
                <p className="text-3xl font-bold text-indigo-600 mt-2">{statusCounts.resubmitted || 0}</p>
                <div className="flex items-center gap-1 text-xs text-gray-500 mt-2">
                  <RefreshCw className="w-3 h-3" />
                  <span>Updated after correction</span>
                </div>
              </div>
              <div className="bg-indigo-50 p-3 rounded-lg">
                <RefreshCw className="w-6 h-6 text-indigo-600" />
              </div>
            </div>
          </div>
        </div>

        {/* Filters Section */}
        <div className="bg-white rounded-xl border border-gray-200 p-5 mb-6 shadow-sm">
          <div className="flex flex-col lg:flex-row lg:items-center gap-4">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                <input
                  type="text"
                  placeholder="Search by owner name, reference number, location, or barangay..."
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
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className="w-full pl-12 pr-10 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none bg-white transition duration-200"
                >
                  {statusOptions.map(option => (
                    <option key={option.value} value={option.value}>
                      {option.label} ({option.count})
                    </option>
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
                <span>Showing all non-approved applications</span>
              )}
            </div>
            <div className="text-gray-500">
              {filteredRegistrations.length} of {registrations.length} records
            </div>
          </div>
        </div>

        {/* Applications Table */}
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
          <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 className="text-lg font-semibold text-gray-900">Application List</h2>
                <p className="text-sm text-gray-600 mt-1">
                  {filteredRegistrations.length} application{filteredRegistrations.length !== 1 ? 's' : ''} requiring action
                </p>
              </div>
              <div className="mt-2 sm:mt-0">
                <div className="inline-flex items-center gap-2 text-sm bg-blue-50 text-blue-700 px-3 py-1.5 rounded-full">
                  <AlertCircle className="w-4 h-4" />
                  <span>Approved applications are filtered out</span>
                </div>
              </div>
            </div>
          </div>
          
          {filteredRegistrations.length === 0 ? (
            <div className="px-6 py-16 text-center">
              <div className="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                <FileText className="w-8 h-8 text-gray-400" />
              </div>
              <h3 className="text-lg font-medium text-gray-900 mb-2">
                {searchTerm || statusFilter !== "all" 
                  ? "No matching applications found" 
                  : "No pending applications"}
              </h3>
              <p className="text-gray-500 max-w-md mx-auto">
                {searchTerm 
                  ? "Try adjusting your search terms or clear the filter"
                  : "All property applications have been approved or processed"}
              </p>
              {(searchTerm || statusFilter !== "all") && (
                <button
                  onClick={() => {
                    setSearchTerm("");
                    setStatusFilter("all");
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
                          <Hash className="w-4 h-4" />
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
                          <FileText className="w-4 h-4" />
                          Status
                        </div>
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <div className="flex items-center gap-2">
                          <Calendar className="w-4 h-4" />
                          Date Submitted
                        </div>
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {filteredRegistrations.map((registration) => {
                      const statusInfo = getStatusInfo(registration.status);
                      const StatusIcon = statusInfo.icon;
                      const ownerName = registration.owner_name || 
                        (registration.first_name && registration.last_name ? 
                          `${registration.first_name} ${registration.last_name}` : 
                          "N/A");
                      
                      return (
                        <tr key={registration.id} className="hover:bg-gray-50 transition duration-150">
                          <td className="px-6 py-4">
                            <div className="font-mono font-bold text-gray-900">{registration.reference_number}</div>
                            <div className="flex items-center gap-1 text-xs text-gray-500 mt-1">
                              <span className="bg-gray-100 px-2 py-0.5 rounded">ID: {registration.id}</span>
                              {registration.has_building === 'yes' && (
                                <span className="bg-green-100 text-green-800 px-2 py-0.5 rounded">With Building</span>
                              )}
                            </div>
                          </td>
                          <td className="px-6 py-4">
                            <div className="font-medium text-gray-900">{ownerName}</div>
                            <div className="text-sm text-gray-500 truncate max-w-[200px]">
                              {registration.email || "No email"}
                            </div>
                            <div className="text-sm text-gray-500">{registration.phone || "No phone"}</div>
                          </td>
                          <td className="px-6 py-4">
                            <div className="flex items-start gap-2">
                              <Home className="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" />
                              <div>
                                <div className="font-medium text-gray-900">{registration.lot_location || "No location specified"}</div>
                                <div className="text-sm text-gray-500">
                                  Brgy. {registration.barangay || "N/A"}, Dist. {registration.district || "N/A"}
                                </div>
                              </div>
                            </div>
                          </td>
                          <td className="px-6 py-4">
                            <div className="flex items-center gap-3">
                              <div className={`p-2 rounded-lg ${statusInfo.bgColor}`}>
                                <StatusIcon className="w-5 h-5" />
                              </div>
                              <div>
                                <span className={`inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium ${statusInfo.color} border`}>
                                  {statusInfo.label}
                                </span>
                                {registration.correction_notes && (
                                  <div className="text-xs text-orange-600 mt-1 max-w-xs truncate">
                                    Note: {registration.correction_notes}
                                  </div>
                                )}
                                <div className="text-xs text-gray-500 mt-1">
                                  {formatTimeAgo(registration.updated_at || registration.created_at)}
                                </div>
                              </div>
                            </div>
                          </td>
                          <td className="px-6 py-4">
                            <div className="text-sm font-medium text-gray-900">{formatDate(registration.created_at)}</div>
                            <div className="text-xs text-gray-500 mt-1">
                              Submitted {formatTimeAgo(registration.created_at)}
                            </div>
                          </td>
                          <td className="px-6 py-4">
                            <button
                              onClick={() => handleViewDetails(registration.id)}
                              className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg font-medium flex items-center gap-2 transition duration-200"
                            >
                              <Eye className="w-4 h-4" />
                              <span>Review</span>
                            </button>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
              
              {/* Table Footer */}
              <div className="px-6 py-4 border-t border-gray-200 bg-gray-50">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                  <div className="text-sm text-gray-600">
                    Showing <span className="font-semibold">{filteredRegistrations.length}</span> of{" "}
                    <span className="font-semibold">{registrations.length}</span> pending applications
                  </div>
                  <div className="text-sm text-gray-600">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-medium">Status Summary:</span>
                      <div className="flex items-center gap-1">
                        <span className="inline-block w-2 h-2 bg-yellow-500 rounded-full"></span>
                        <span className="text-yellow-600">{statusCounts.pending} pending</span>
                      </div>
                      <div className="flex items-center gap-1">
                        <span className="inline-block w-2 h-2 bg-blue-500 rounded-full"></span>
                        <span className="text-blue-600">{statusCounts.for_inspection} inspection</span>
                      </div>
                      <div className="flex items-center gap-1">
                        <span className="inline-block w-2 h-2 bg-orange-500 rounded-full"></span>
                        <span className="text-orange-600">{statusCounts.needs_correction} correction</span>
                      </div>
                      <div className="flex items-center gap-1">
                        <span className="inline-block w-2 h-2 bg-purple-500 rounded-full"></span>
                        <span className="text-purple-600">{statusCounts.assessed} assessed</span>
                      </div>
                      {statusCounts.resubmitted > 0 && (
                        <div className="flex items-center gap-1">
                          <span className="inline-block w-2 h-2 bg-indigo-500 rounded-full"></span>
                          <span className="text-indigo-600">{statusCounts.resubmitted} resubmitted</span>
                        </div>
                      )}
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
            <p className="font-medium">Local Government Unit - Property Tax Management System</p>
            <p className="mt-1">Real Property Tax Application Portal v2.0</p>
            <div className="flex items-center justify-center gap-4 mt-2 text-xs text-gray-400">
              <span>Data refreshed: {new Date().toLocaleTimeString()}</span>
              <span>•</span>
              <span>Total records: {registrations.length}</span>
              <span>•</span>
              <span>API: {API_BASE.includes('localhost') ? 'Development' : 'Production'}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}