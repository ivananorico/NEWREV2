import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { 
  Search, Filter, Eye, Download, RefreshCw, AlertCircle, CheckCircle, 
  Clock, Building, FileText, Home, MapPin, User, Calendar, Hash, Users, FileCheck,
  Map, AlertTriangle, FileSearch, Landmark, Archive, ChevronRight, TrendingUp,
  BarChart3, Database, Shield, CheckCircle2, Clock3, FileWarning, Eye as EyeIcon
} from "lucide-react";

// Custom colors from the dashboard
const COLORS = {
  primary: '#4a90e2',
  secondary: '#9aa5b1',
  success: '#4caf50',
  background: '#fbfbfb',
  warning: '#ff9800',
  danger: '#f44336',
  info: '#2196f3',
  dark: '#374151'
};

export default function RPTValidationTable() {
  const [registrations, setRegistrations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [stats, setStats] = useState({
    pending: 0,
    for_inspection: 0,
    needs_correction: 0,
    assessed: 0,
    resubmitted: 0,
    total: 0
  });
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
      
      // Update statistics
      const newStats = {
        pending: filteredRegistrations.filter(r => r.status === 'pending').length,
        for_inspection: filteredRegistrations.filter(r => r.status === 'for_inspection').length,
        needs_correction: filteredRegistrations.filter(r => r.status === 'needs_correction').length,
        assessed: filteredRegistrations.filter(r => r.status === 'assessed').length,
        resubmitted: filteredRegistrations.filter(r => r.status === 'resubmitted').length,
        total: filteredRegistrations.length
      };
      setStats(newStats);
      
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
        color: "text-yellow-700",
        bgColor: "bg-yellow-50",
        borderColor: "border-yellow-100",
        icon: Clock,
        dotColor: COLORS.warning
      },
      for_inspection: { 
        label: "For Inspection", 
        color: "text-blue-700",
        bgColor: "bg-blue-50",
        borderColor: "border-blue-100",
        icon: EyeIcon,
        dotColor: COLORS.info
      },
      needs_correction: { 
        label: "Needs Correction", 
        color: "text-orange-700",
        bgColor: "bg-orange-50",
        borderColor: "border-orange-100",
        icon: AlertTriangle,
        dotColor: COLORS.warning
      },
      assessed: { 
        label: "Assessed", 
        color: "text-purple-700",
        bgColor: "bg-purple-50",
        borderColor: "border-purple-100",
        icon: FileCheck,
        dotColor: '#6b46c1'
      },
      resubmitted: { 
        label: "Resubmitted", 
        color: "text-indigo-700",
        bgColor: "bg-indigo-50",
        borderColor: "border-indigo-100",
        icon: RefreshCw,
        dotColor: '#4f46e5'
      }
    };
    
    return statusMap[status] || { 
      label: status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()), 
      color: "text-gray-700",
      bgColor: "bg-gray-50",
      borderColor: "border-gray-100",
      icon: FileText,
      dotColor: COLORS.secondary
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

  const statusOptions = [
    { value: "all", label: "All Status", count: registrations.length },
    { value: "pending", label: "Pending Review", count: stats.pending },
    { value: "for_inspection", label: "For Inspection", count: stats.for_inspection },
    { value: "needs_correction", label: "Needs Correction", count: stats.needs_correction },
    { value: "assessed", label: "Assessed", count: stats.assessed },
    { value: "resubmitted", label: "Resubmitted", count: stats.resubmitted }
  ];

  if (loading) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
        <div className="flex flex-col justify-center items-center h-screen bg-white">
          <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 mb-4" style={{ borderColor: COLORS.primary }}></div>
          <p style={{ color: COLORS.dark }}>Loading Property Applications...</p>
          <p className="text-sm mt-2" style={{ color: COLORS.secondary }}>Fetching application data</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
        <div className="max-w-4xl mx-auto p-6 bg-white">
          <div className="bg-red-50 border border-red-200 rounded-xl p-6">
            <div className="flex items-center space-x-3 mb-4">
              <AlertCircle className="w-8 h-8" style={{ color: COLORS.danger }} />
              <div>
                <h3 className="text-lg font-semibold" style={{ color: COLORS.danger }}>Error Loading Applications</h3>
                <p style={{ color: COLORS.danger }}>{error}</p>
              </div>
            </div>
            <button 
              onClick={fetchRegistrations}
              className="px-4 py-2 rounded-lg flex items-center gap-2 transition-all"
              style={{ backgroundColor: COLORS.primary, color: 'white' }}
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
    <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
      {/* Header */}
      <div className="border-b" style={{ backgroundColor: 'white', borderColor: '#e5e7eb' }}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
              <h1 className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>
                Property Tax Applications Validation
              </h1>
              <div className="flex items-center gap-3 text-sm" style={{ color: COLORS.secondary }}>
                <div className="flex items-center gap-1">
                  <Calendar className="w-4 h-4" />
                  <span>Active Applications • {new Date().toLocaleDateString('en-PH')}</span>
                </div>
              </div>
            </div>
            
            <div className="flex flex-wrap gap-3 items-center">
              <button
                onClick={fetchRegistrations}
                className="flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all"
                style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
              >
                <RefreshCw className="w-4 h-4" />
                Refresh
              </button>
              
              <button
                className="flex items-center gap-2 px-4 py-2 rounded-lg transition-all"
                style={{ backgroundColor: COLORS.primary, color: 'white' }}
              >
                <Database className="w-4 h-4" />
                Export Report
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Statistics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
          {/* Total Applications */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                <Landmark className="w-6 h-6" style={{ color: COLORS.primary }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full" 
                    style={{ backgroundColor: `${COLORS.secondary}15`, color: COLORS.dark }}>
                Total
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Applications
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.total}</p>
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Pending approval</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2 mt-1">
                <div 
                  className="h-2 rounded-full transition-all duration-500"
                  style={{ 
                    width: `${stats.total > 0 ? 100 : 0}%`,
                    backgroundColor: COLORS.primary
                  }}
                ></div>
              </div>
            </div>
          </div>

          {/* Pending Review */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.warning}15` }}>
                <Clock className="w-6 h-6" style={{ color: COLORS.warning }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full bg-yellow-100 text-yellow-800">
                {stats.pending}
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Pending Review
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.pending}</p>
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Awaiting review</span>
                <span className="font-medium">{Math.round((stats.pending / Math.max(stats.total, 1)) * 100)}%</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2 mt-1">
                <div 
                  className="h-2 rounded-full transition-all duration-500"
                  style={{ 
                    width: `${stats.total > 0 ? (stats.pending / stats.total) * 100 : 0}%`,
                    backgroundColor: COLORS.warning
                  }}
                ></div>
              </div>
            </div>
          </div>

          {/* For Inspection */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.info}15` }}>
                <EyeIcon className="w-6 h-6" style={{ color: COLORS.info }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full bg-blue-100 text-blue-800">
                {stats.for_inspection}
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              For Inspection
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.for_inspection}</p>
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Site visit needed</span>
                <span className="font-medium">{Math.round((stats.for_inspection / Math.max(stats.total, 1)) * 100)}%</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2 mt-1">
                <div 
                  className="h-2 rounded-full transition-all duration-500"
                  style={{ 
                    width: `${stats.total > 0 ? (stats.for_inspection / stats.total) * 100 : 0}%`,
                    backgroundColor: COLORS.info
                  }}
                ></div>
              </div>
            </div>
          </div>

          {/* Needs Correction */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.danger}15` }}>
                <AlertTriangle className="w-6 h-6" style={{ color: COLORS.danger }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full bg-red-100 text-red-800">
                {stats.needs_correction}
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Needs Correction
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.needs_correction}</p>
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Requires updates</span>
                <span className="font-medium">{Math.round((stats.needs_correction / Math.max(stats.total, 1)) * 100)}%</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2 mt-1">
                <div 
                  className="h-2 rounded-full transition-all duration-500"
                  style={{ 
                    width: `${stats.total > 0 ? (stats.needs_correction / stats.total) * 100 : 0}%`,
                    backgroundColor: COLORS.danger
                  }}
                ></div>
              </div>
            </div>
          </div>

          {/* Assessed */}
          <div className="bg-white border rounded-xl p-6 shadow-sm transition-all hover:shadow-md" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between mb-4">
              <div className="p-3 rounded-lg" style={{ backgroundColor: '#6b46c115' }}>
                <FileCheck className="w-6 h-6" style={{ color: '#6b46c1' }} />
              </div>
              <span className="text-sm px-3 py-1 rounded-full bg-purple-100 text-purple-800">
                {stats.assessed}
              </span>
            </div>
            <h3 className="text-sm font-semibold uppercase tracking-wider mb-2" style={{ color: COLORS.secondary }}>
              Assessed
            </h3>
            <p className="text-2xl font-bold mb-4" style={{ color: COLORS.dark }}>{stats.assessed}</p>
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              <div className="flex justify-between">
                <span>Ready for approval</span>
                <span className="font-medium">{Math.round((stats.assessed / Math.max(stats.total, 1)) * 100)}%</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2 mt-1">
                <div 
                  className="h-2 rounded-full transition-all duration-500"
                  style={{ 
                    width: `${stats.total > 0 ? (stats.assessed / stats.total) * 100 : 0}%`,
                    backgroundColor: '#6b46c1'
                  }}
                ></div>
              </div>
            </div>
          </div>
        </div>

        {/* Filter Section */}
        <div className="bg-white border rounded-xl p-6 shadow-sm" style={{ borderColor: COLORS.secondary }}>
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2" style={{ color: COLORS.secondary }} />
                <input
                  type="text"
                  placeholder="Search by owner name, reference number, location..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2 border rounded-lg"
                  style={{ borderColor: COLORS.secondary }}
                />
              </div>
            </div>
            
            <div className="flex-1">
              <div className="relative">
                <Filter className="absolute left-3 top-1/2 transform -translate-y-1/2" style={{ color: COLORS.secondary }} />
                <select
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className="w-full pl-10 pr-10 py-2 border rounded-lg appearance-none bg-white"
                  style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
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
            <div style={{ color: COLORS.secondary }}>
              {searchTerm ? (
                <span>
                  Searching for: <span className="font-medium" style={{ color: COLORS.dark }}>"{searchTerm}"</span>
                </span>
              ) : (
                <span>Showing all pending applications</span>
              )}
            </div>
            <div className="font-medium" style={{ color: COLORS.dark }}>
              {filteredRegistrations.length} of {registrations.length} applications
            </div>
          </div>
        </div>

        {/* Applications Table */}
        <div className="bg-white border rounded-xl shadow-sm" style={{ borderColor: COLORS.secondary }}>
          <div className="p-6 border-b" style={{ borderColor: COLORS.secondary }}>
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
              <div>
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <FileText className="w-5 h-5" style={{ color: COLORS.primary }} />
                  Property Applications ({filteredRegistrations.length})
                </h3>
                <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                  Excluding approved applications
                </p>
              </div>
              
              <div className="inline-flex items-center gap-2 px-3 py-1.5 border rounded-lg text-sm"
                   style={{ borderColor: COLORS.secondary, color: COLORS.secondary }}>
                <Archive className="w-4 h-4" />
                <span>Approved applications excluded</span>
              </div>
            </div>
          </div>
          
          {filteredRegistrations.length === 0 ? (
            <div className="text-center py-12" style={{ color: COLORS.secondary }}>
              <FileSearch className="w-12 h-12 mx-auto mb-2" />
              <p className="text-sm font-medium" style={{ color: COLORS.dark }}>
                {searchTerm || statusFilter !== "all" 
                  ? "No matching applications found" 
                  : "All applications have been processed"}
              </p>
              <p className="text-sm mt-1 max-w-xs mx-auto">
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
                  className="mt-4 text-sm font-medium px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all"
                  style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                >
                  Clear filters
                </button>
              )}
            </div>
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead>
                    <tr style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Reference No.
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Property Owner
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Property Location
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Status
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Date Submitted
                      </th>
                      <th className="p-4 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {filteredRegistrations.map((registration) => {
                      const statusInfo = getStatusInfo(registration.status);
                      const StatusIcon = statusInfo.icon;
                      const ownerName = registration.owner_name || 
                        (registration.first_name && registration.last_name ? 
                          `${registration.first_name} ${registration.last_name}` : 
                          "N/A");
                      
                      return (
                        <tr key={registration.id} className="hover:bg-gray-50 transition-colors" 
                            style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}>
                          <td className="p-4">
                            <div className="font-mono font-medium" style={{ color: COLORS.dark }}>
                              {registration.reference_number}
                            </div>
                            <div className="text-xs mt-1" style={{ color: COLORS.secondary }}>ID: {registration.id}</div>
                          </td>
                          <td className="p-4">
                            <div className="font-medium" style={{ color: COLORS.dark }}>{ownerName}</div>
                            <div className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                              {registration.email || "No email provided"}
                            </div>
                            <div className="text-xs mt-0.5" style={{ color: COLORS.secondary }}>
                              {registration.phone || "No phone"}
                            </div>
                          </td>
                          <td className="p-4">
                            <div className="font-medium" style={{ color: COLORS.dark }}>
                              {registration.lot_location || "Not specified"}
                            </div>
                            <div className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                              Brgy. {registration.barangay || "N/A"}, Dist. {registration.district || "N/A"}
                            </div>
                          </td>
                          <td className="p-4">
                            <div className="flex items-center gap-3">
                              <div className={`p-2 rounded-lg ${statusInfo.bgColor}`}>
                                <StatusIcon className={`w-4 h-4 ${statusInfo.color}`} />
                              </div>
                              <div>
                                <span className={`text-xs font-medium px-3 py-1.5 rounded-full ${statusInfo.bgColor} ${statusInfo.color} border ${statusInfo.borderColor}`}>
                                  {statusInfo.label}
                                </span>
                                {registration.correction_notes && (
                                  <div className="text-xs mt-1" style={{ color: COLORS.warning }} title={registration.correction_notes}>
                                    Note: {registration.correction_notes.substring(0, 30)}...
                                  </div>
                                )}
                              </div>
                            </div>
                          </td>
                          <td className="p-4">
                            <div className="font-medium" style={{ color: COLORS.dark }}>
                              {formatDate(registration.created_at)}
                            </div>
                            <div className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                              {formatTimeAgo(registration.created_at)}
                            </div>
                          </td>
                          <td className="p-4">
                            <button
                              onClick={() => handleViewDetails(registration.id)}
                              className="px-4 py-2 rounded-lg flex items-center gap-2 transition-all"
                              style={{ backgroundColor: COLORS.primary, color: 'white' }}
                            >
                              <Eye className="w-4 h-4" />
                              Review
                            </button>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
              
              {/* Table Footer */}
              <div className="p-4 border-t" style={{ borderColor: COLORS.secondary, backgroundColor: `${COLORS.background}` }}>
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div className="text-sm" style={{ color: COLORS.secondary }}>
                    Showing <span className="font-semibold" style={{ color: COLORS.dark }}>{filteredRegistrations.length}</span> of{" "}
                    <span className="font-semibold" style={{ color: COLORS.dark }}>{registrations.length}</span> pending applications
                  </div>
                  <div className="text-sm">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-medium" style={{ color: COLORS.dark }}>Status Summary:</span>
                      {stats.pending > 0 && (
                        <span className="px-2 py-1 rounded text-xs bg-yellow-100 text-yellow-800">
                          {stats.pending} pending
                        </span>
                      )}
                      {stats.for_inspection > 0 && (
                        <span className="px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">
                          {stats.for_inspection} inspection
                        </span>
                      )}
                      {stats.needs_correction > 0 && (
                        <span className="px-2 py-1 rounded text-xs bg-orange-100 text-orange-800">
                          {stats.needs_correction} correction
                        </span>
                      )}
                      {stats.assessed > 0 && (
                        <span className="px-2 py-1 rounded text-xs bg-purple-100 text-purple-800">
                          {stats.assessed} assessed
                        </span>
                      )}
                      {stats.resubmitted > 0 && (
                        <span className="px-2 py-1 rounded text-xs bg-indigo-100 text-indigo-800">
                          {stats.resubmitted} resubmitted
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            </>
          )}
        </div>

        {/* Footer Summary */}
        <div className="text-center text-sm pt-6 border-t" style={{ color: COLORS.secondary, borderColor: COLORS.secondary }}>
          <p>Property Tax Application Validation Portal • {new Date().toLocaleDateString('en-PH')}</p>
          <p className="text-xs mt-1">
            Local Government Unit - Real Property Tax Management System
          </p>
        </div>
      </div>
    </div>
  );
}