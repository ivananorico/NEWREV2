import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { 
  Search, Filter, Eye, Download, RefreshCw, AlertCircle, CheckCircle, 
  Clock, Building,FileText,Home,MapPin,User,Calendar,Hash,Users,FileCheck,
  Map,AlertTriangle,FileSearch,Landmark,Archive
} from "lucide-react";

export default function RPTValidationTable() {
  const [registrations, setRegistrations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const navigate = useNavigate();

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

  // Filter registrations
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
        color: "text-yellow-800",
        bgColor: "bg-yellow-50",
        borderColor: "border-yellow-200",
        icon: Clock
      },
      for_inspection: { 
        label: "For Inspection", 
        color: "text-blue-800",
        bgColor: "bg-blue-50",
        borderColor: "border-blue-200",
        icon: Eye
      },
      needs_correction: { 
        label: "Needs Correction", 
        color: "text-orange-800",
        bgColor: "bg-orange-50",
        borderColor: "border-orange-200",
        icon: AlertTriangle
      },
      assessed: { 
        label: "Assessed", 
        color: "text-purple-800",
        bgColor: "bg-purple-50",
        borderColor: "border-purple-200",
        icon: FileCheck
      },
      resubmitted: { 
        label: "Resubmitted", 
        color: "text-indigo-800",
        bgColor: "bg-indigo-50",
        borderColor: "border-indigo-200",
        icon: RefreshCw
      }
    };
    
    return statusMap[status] || { 
      label: status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()), 
      color: "text-gray-800",
      bgColor: "bg-gray-50",
      borderColor: "border-gray-200",
      icon: FileText
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

  // Status counts
  const statusCounts = {
    pending: registrations.filter(r => r.status === 'pending').length,
    for_inspection: registrations.filter(r => r.status === 'for_inspection').length,
    needs_correction: registrations.filter(r => r.status === 'needs_correction').length,
    assessed: registrations.filter(r => r.status === 'assessed').length,
    resubmitted: registrations.filter(r => r.status === 'resubmitted').length,
    total: registrations.length
  };

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
      <div className="min-h-screen bg-white">
        <div className="flex items-center justify-center p-4 h-screen">
          <div className="text-center">
            <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-gray-800"></div>
            <p className="mt-4 font-medium text-gray-600">Loading property applications...</p>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-white">
        <div className="flex items-center justify-center p-4 h-screen">
          <div className="bg-white rounded-lg border border-gray-200 p-6 max-w-md w-full">
            <div className="text-center">
              <div className="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4 bg-red-50">
                <AlertCircle className="w-6 h-6 text-red-500" />
              </div>
              <h2 className="text-lg font-semibold text-gray-800 mb-2">Connection Error</h2>
              <p className="text-gray-600 text-sm mb-4">{error}</p>
              <button
                onClick={fetchRegistrations}
                className="w-full px-4 py-2.5 rounded-md font-medium text-white bg-gray-900 hover:bg-black transition duration-200"
              >
                <RefreshCw className="w-4 h-4 inline-block mr-2" />
                Retry Connection
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white">
      {/* Header - Clean White Design */}
      <div className="border-b border-gray-200 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="text-2xl font-bold text-gray-900 mb-1">
                Property Tax Applications
              </h1>
              <p className="text-sm text-gray-600">
                Review and validate property registration applications
              </p>
            </div>
            
            <div className="flex flex-wrap gap-3 items-center">
              <button
                onClick={fetchRegistrations}
                className="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700"
              >
                <RefreshCw className="w-4 h-4" />
                Refresh
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Stats Cards - Clean Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          {/* Total Applications */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Applications</p>
                <p className="text-2xl font-bold text-gray-900 mt-1">{statusCounts.total}</p>
              </div>
              <div className="p-3 bg-gray-100 rounded-lg">
                <Landmark className="w-5 h-5 text-gray-700" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Excluding approved applications
            </div>
          </div>

          {/* Pending Review */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Pending Review</p>
                <p className="text-2xl font-bold text-yellow-600 mt-1">{statusCounts.pending}</p>
              </div>
              <div className="p-3 bg-yellow-50 rounded-lg">
                <Clock className="w-5 h-5 text-yellow-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Awaiting initial review
            </div>
          </div>

          {/* For Inspection */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">For Inspection</p>
                <p className="text-2xl font-bold text-blue-600 mt-1">{statusCounts.for_inspection}</p>
              </div>
              <div className="p-3 bg-blue-50 rounded-lg">
                <Eye className="w-5 h-5 text-blue-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Scheduled for site visit
            </div>
          </div>

          {/* Needs Correction */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Needs Correction</p>
                <p className="text-2xl font-bold text-orange-600 mt-1">{statusCounts.needs_correction}</p>
              </div>
              <div className="p-3 bg-orange-50 rounded-lg">
                <AlertTriangle className="w-5 h-5 text-orange-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Requires owner action
            </div>
          </div>

          {/* Assessed */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Assessed</p>
                <p className="text-2xl font-bold text-purple-600 mt-1">{statusCounts.assessed}</p>
              </div>
              <div className="p-3 bg-purple-50 rounded-lg">
                <FileCheck className="w-5 h-5 text-purple-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Ready for final approval
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
                  placeholder="Search applications by owner, reference, or location..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent transition duration-200"
                />
              </div>
            </div>
            
            <div className="flex-1">
              <div className="relative">
                <Filter className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <select
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className="w-full pl-10 pr-10 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent appearance-none bg-white transition duration-200"
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
                  Searching for: <span className="font-medium text-gray-900">"{searchTerm}"</span>
                </span>
              ) : (
                <span>Showing all pending applications</span>
              )}
            </div>
            <div className="text-gray-700 font-medium">
              {filteredRegistrations.length} of {registrations.length} applications
            </div>
          </div>
        </div>

        {/* Applications Table */}
        <div className="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
          <div className="px-5 py-4 border-b border-gray-200 bg-gray-50">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wider">Applications</h2>
                <p className="text-sm text-gray-600 mt-1">
                  {filteredRegistrations.length} pending application{filteredRegistrations.length !== 1 ? 's' : ''}
                </p>
              </div>
              <div className="mt-2 sm:mt-0">
                <div className="inline-flex items-center gap-2 text-xs bg-gray-100 text-gray-700 px-3 py-1.5 rounded-lg">
                  <Archive className="w-3 h-3" />
                  <span>Approved applications excluded</span>
                </div>
              </div>
            </div>
          </div>
          
          {filteredRegistrations.length === 0 ? (
            <div className="px-4 py-12 text-center">
              <div className="mx-auto w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                <FileSearch className="w-6 h-6 text-gray-400" />
              </div>
              <h3 className="text-sm font-medium text-gray-900 mb-1">
                {searchTerm || statusFilter !== "all" 
                  ? "No matching applications found" 
                  : "All applications have been processed"}
              </h3>
              <p className="text-sm text-gray-500 max-w-xs mx-auto">
                {searchTerm 
                  ? "Try adjusting your search terms or clear filters"
                  : "No pending applications at this time"}
              </p>
              {(searchTerm || statusFilter !== "all") && (
                <button
                  onClick={() => {
                    setSearchTerm("");
                    setStatusFilter("all");
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
                        Status
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Date Submitted
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                      </th>
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
                        <tr key={registration.id} className="hover:bg-gray-50 transition-colors">
                          <td className="px-5 py-4">
                            <div className="font-mono text-sm font-semibold text-gray-900">
                              {registration.reference_number}
                            </div>
                            <div className="text-xs text-gray-500 mt-0.5">ID: {registration.id}</div>
                          </td>
                          <td className="px-5 py-4">
                            <div className="font-medium text-sm text-gray-900">{ownerName}</div>
                            <div className="text-xs text-gray-500 truncate max-w-[180px]">
                              {registration.email || "No email provided"}
                            </div>
                            <div className="text-xs text-gray-500">{registration.phone || "No phone"}</div>
                          </td>
                          <td className="px-5 py-4">
                            <div className="font-medium text-sm text-gray-900">{registration.lot_location || "Not specified"}</div>
                            <div className="text-xs text-gray-500">
                              Brgy. {registration.barangay || "N/A"}, Dist. {registration.district || "N/A"}
                            </div>
                          </td>
                          <td className="px-5 py-4">
                            <div className="flex items-center gap-3">
                              <div className={`p-2 rounded-lg ${statusInfo.bgColor}`}>
                               <StatusIcon className={`w-4 h-4 ${statusInfo.color}`} />
                              </div>
                              <div>
                                <span className={`text-xs font-medium px-3 py-1.5 rounded-full ${statusInfo.bgColor} ${statusInfo.color} border ${statusInfo.borderColor}`}>
                                  {statusInfo.label}
                                </span>
                                {registration.correction_notes && (
                                  <div className="text-xs text-orange-600 mt-1 truncate max-w-[150px]" title={registration.correction_notes}>
                                    Note: {registration.correction_notes.substring(0, 30)}...
                                  </div>
                                )}
                              </div>
                            </div>
                          </td>
                          <td className="px-5 py-4">
                            <div className="text-sm font-medium text-gray-900">{formatDate(registration.created_at)}</div>
                            <div className="text-xs text-gray-500 mt-0.5">
                              {formatTimeAgo(registration.created_at)}
                            </div>
                          </td>
                          <td className="px-5 py-4">
                            <button
                              onClick={() => handleViewDetails(registration.id)}
                              className="text-sm font-medium px-4 py-2 rounded-lg bg-gray-900 text-white hover:bg-black transition duration-200 flex items-center gap-2"
                            >
                              <Eye className="w-4 h-4" />
                              Review Details
                            </button>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
              
              {/* Table Footer */}
              <div className="px-5 py-4 border-t border-gray-200 bg-gray-50">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div className="text-sm text-gray-700">
                    Showing <span className="font-semibold">{filteredRegistrations.length}</span> of{" "}
                    <span className="font-semibold">{registrations.length}</span> pending applications
                  </div>
                  <div className="text-sm text-gray-700">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-medium">Status Summary:</span>
                      {statusCounts.pending > 0 && (
                        <span className="px-2 py-1 rounded bg-yellow-100 text-yellow-800 text-xs">
                          {statusCounts.pending} pending
                        </span>
                      )}
                      {statusCounts.for_inspection > 0 && (
                        <span className="px-2 py-1 rounded bg-blue-100 text-blue-800 text-xs">
                          {statusCounts.for_inspection} inspection
                        </span>
                      )}
                      {statusCounts.needs_correction > 0 && (
                        <span className="px-2 py-1 rounded bg-orange-100 text-orange-800 text-xs">
                          {statusCounts.needs_correction} correction
                        </span>
                      )}
                      {statusCounts.assessed > 0 && (
                        <span className="px-2 py-1 rounded bg-purple-100 text-purple-800 text-xs">
                          {statusCounts.assessed} assessed
                        </span>
                      )}
                      {statusCounts.resubmitted > 0 && (
                        <span className="px-2 py-1 rounded bg-indigo-100 text-indigo-800 text-xs">
                          {statusCounts.resubmitted} resubmitted
                        </span>
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
          <div className="text-center text-sm text-gray-600">
            <p className="font-medium">Local Government Unit - Property Tax Management System</p>
            <p className="mt-1">Real Property Tax Application Portal v2.0</p>
          </div>
        </div>
      </div>
    </div>
  );
}