import React, { useState, useEffect } from "react";
import { 
  Search, Filter, Eye, Download, RefreshCw, CheckCircle, Building, 
  Calendar, DollarSign, Clock, AlertCircle, Briefcase, CreditCard, 
  CalendarDays, Hash, FileText, User, ChevronRight, Clock3, ArrowRight,
  CreditCard as CreditCardIcon, TrendingUp, Percent, Wallet, Phone, Mail
} from "lucide-react";
import { useNavigate } from "react-router-dom";

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

// Business Status Badge Component
const BusinessStatusBadge = ({ status }) => {
  const getStatusInfo = () => {
    const statusLower = status?.toLowerCase();
    switch(statusLower) {
      case 'active':
      case 'approved':
      case 'renewed':
        return {
          text: 'Active',
          bgColor: `${COLORS.success}15`,
          textColor: COLORS.success,
          borderColor: `${COLORS.success}30`,
          icon: <CheckCircle className="w-3 h-3 mr-1" />
        };
      case 'pending':
      case 'for_approval':
        return {
          text: 'Pending',
          bgColor: `${COLORS.warning}15`,
          textColor: COLORS.warning,
          borderColor: `${COLORS.warning}30`,
          icon: <Clock className="w-3 h-3 mr-1" />
        };
      case 'expired':
      case 'cancelled':
      case 'suspended':
        return {
          text: 'Inactive',
          bgColor: `${COLORS.danger}15`,
          textColor: COLORS.danger,
          borderColor: `${COLORS.danger}30`,
          icon: <AlertCircle className="w-3 h-3 mr-1" />
        };
      default:
        return {
          text: status || 'Unknown',
          bgColor: `${COLORS.secondary}15`,
          textColor: COLORS.secondary,
          borderColor: `${COLORS.secondary}30`,
          icon: null
        };
    }
  };

  const statusInfo = getStatusInfo();
  
  return (
    <span 
      className="inline-flex items-center px-2 py-1 rounded text-xs font-medium border"
      style={{ 
        backgroundColor: statusInfo.bgColor,
        color: statusInfo.textColor,
        borderColor: statusInfo.borderColor
      }}
    >
      {statusInfo.icon}
      {statusInfo.text}
    </span>
  );
};

// Business Type Badge Component
const BusinessTypeBadge = ({ type, nature }) => {
  const getTypeInfo = () => {
    const displayType = type || nature || '';
    
    if (!displayType) {
      return {
        text: 'Unknown',
        bgColor: `${COLORS.secondary}15`,
        textColor: COLORS.secondary,
        borderColor: `${COLORS.secondary}30`
      };
    }

    const colors = {
      'retail': { bgColor: `${COLORS.primary}15`, textColor: COLORS.primary },
      'wholesale': { bgColor: `${COLORS.info}15`, textColor: COLORS.info },
      'service': { bgColor: `${COLORS.success}15`, textColor: COLORS.success },
      'manufacturing': { bgColor: `${COLORS.warning}15`, textColor: COLORS.warning },
      'food': { bgColor: `${COLORS.danger}15`, textColor: COLORS.danger },
      'bakery': { bgColor: `${COLORS.danger}15`, textColor: COLORS.danger },
      'restaurant': { bgColor: `${COLORS.danger}15`, textColor: COLORS.danger },
      'professional': { bgColor: `${COLORS.info}15`, textColor: COLORS.info }
    };

    const displayTypeLower = displayType.toLowerCase();
    let matchedColor = colors[displayTypeLower];
    
    // Check for partial matches
    if (!matchedColor) {
      for (const [key, color] of Object.entries(colors)) {
        if (displayTypeLower.includes(key)) {
          matchedColor = color;
          break;
        }
      }
    }

    const colorStyle = matchedColor || { 
      bgColor: `${COLORS.secondary}15`, 
      textColor: COLORS.secondary 
    };
    
    // Shorten the display text if too long
    let displayText = displayType;
    if (displayText.length > 15) {
      displayText = displayText.split(' / ')[0] || displayText.substring(0, 15) + '...';
    }
    
    return {
      text: displayText,
      bgColor: colorStyle.bgColor,
      textColor: colorStyle.textColor,
      borderColor: `${colorStyle.textColor}30`
    };
  };

  const typeInfo = getTypeInfo();
  
  return (
    <span 
      className="inline-flex items-center px-2 py-1 rounded text-xs font-medium border truncate"
      style={{ 
        backgroundColor: typeInfo.bgColor,
        color: typeInfo.textColor,
        borderColor: typeInfo.borderColor
      }}
      title={type || nature}
    >
      {typeInfo.text}
    </span>
  );
};

// Next Quarter Component - UPDATED: Just shows quarter info, no status
const NextQuarterComponent = ({ nextDueDate, paidQuarters, totalQuarters }) => {
  // If all quarters are paid
  if (paidQuarters >= totalQuarters && totalQuarters > 0) {
    return (
      <div className="text-center">
        <div className="text-sm font-medium text-green-700">All Paid</div>
        <div className="text-xs text-gray-500">{paidQuarters}/{totalQuarters} quarters</div>
      </div>
    );
  }

  // If there's a next due date
  if (nextDueDate) {
    const dueDate = new Date(nextDueDate);
    const today = new Date();
    const daysUntilDue = Math.ceil((dueDate - today) / (1000 * 60 * 60 * 24));
    
    // Determine which quarter based on month
    const quarterMonth = dueDate.getMonth();
    const nextQuarter = Math.floor(quarterMonth / 3) + 1;
    const quarterYear = dueDate.getFullYear();
    
    let statusColor = daysUntilDue <= 7 ? COLORS.danger : daysUntilDue <= 30 ? COLORS.warning : COLORS.info;
    
    return (
      <div className="text-center">
        <div className="text-sm font-medium" style={{ color: statusColor }}>
          Q{nextQuarter} {quarterYear}
        </div>
        <div className="text-xs text-gray-500">
          {daysUntilDue > 0 ? `Due in ${daysUntilDue} days` : 'Past due'}
        </div>
      </div>
    );
  }

  // Default fallback
  return (
    <div className="text-center">
      <div className="text-sm font-medium text-gray-700">No schedule</div>
      <div className="text-xs text-gray-500">-</div>
    </div>
  );
};

// Helper functions
const formatCurrency = (amount) => {
  if (!amount || isNaN(amount)) return '₱0';
  const num = parseFloat(amount);
  return `₱${num.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
};

const formatNumber = (num) => {
  if (!num || isNaN(num)) return '0';
  return new Intl.NumberFormat('en-PH').format(parseFloat(num));
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    
    return date.toLocaleDateString("en-PH", {
      month: "short",
      day: "numeric",
      year: "numeric",
    });
  } catch (err) {
    return dateString;
  }
};

// Shorten owner name for display
const shortenOwnerName = (name) => {
  if (!name) return 'N/A';
  
  // Handle "Last, First" format
  if (name.includes(',')) {
    const parts = name.split(',').map(p => p.trim());
    if (parts.length >= 2) {
      return `${parts[0]}, ${parts[1].split(' ')[0]}`;
    }
  }
  
  // Handle regular names
  const words = name.split(' ');
  if (words.length <= 2) return name;
  
  return `${words[0]} ${words[1]}`;
};

export default function BusinessStatus() {
  const [permits, setPermits] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const navigate = useNavigate();

  // API Configuration
  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

  const API_PATH = "/Business/BusinessStatus";

  const fetchPermits = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(`${API_BASE}${API_PATH}/get_permits.php`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Cache-Control': 'no-cache'
        }
      });
      
      if (!response.ok) {
        throw new Error(`Server error: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.status === "success") {
        setPermits(data.permits || []);
      } else {
        throw new Error(data.error || data.message || "Failed to load business permits");
      }
    } catch (err) {
      setError(`Failed to load business permits: ${err.message}`);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPermits();
  }, []);

  // Filter permits
  const filteredPermits = permits.filter(permit => {
    const matchesSearch = 
      (permit.business_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (permit.owner_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (permit.business_permit_id?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (permit.trade_name?.toLowerCase() || '').includes(searchTerm.toLowerCase());
    
    const status = permit.status?.toLowerCase() || '';
    const matchesStatus = statusFilter === "all" || 
      status === statusFilter.toLowerCase();
    
    return matchesSearch && matchesStatus;
  });

  const handleViewDetails = (id) => {
    navigate(`/business/businessstatusinfo/${id}`);
  };

  const handleExport = () => {
    if (filteredPermits.length === 0) {
      alert("No data to export");
      return;
    }

    const headers = [
      "Permit ID", "Business Name", "Owner Name", "Owner Type", 
      "Business Type", "Business Status", "Next Quarter", "Annual Tax"
    ];

    const csvData = filteredPermits.map(permit => {
      // Determine next quarter
      let nextQuarter = "All Paid";
      if (permit.next_due_date) {
        const dueDate = new Date(permit.next_due_date);
        nextQuarter = `Q${Math.floor(dueDate.getMonth() / 3) + 1} ${dueDate.getFullYear()}`;
      }

      return [
        `"${permit.business_permit_id || ''}"`,
        `"${permit.business_name || ''}"`,
        `"${permit.owner_name || ''}"`,
        `"${permit.owner_type || 'Individual'}"`,
        `"${permit.business_type || ''}"`,
        `"${permit.status || ''}"`,
        `"${nextQuarter}"`,
        permit.total_tax || "0"
      ];
    });

    const csvContent = [
      headers.join(","),
      ...csvData.map(row => row.join(","))
    ].join("\n");

    const blob = new Blob([csvContent], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `business-permits-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center" style={{ backgroundColor: COLORS.background }}>
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 mx-auto mb-4" style={{ borderColor: COLORS.primary }}></div>
          <p style={{ color: COLORS.dark }}>Loading Business Permits...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center" style={{ backgroundColor: COLORS.background }}>
        <div className="max-w-md w-full bg-white rounded-xl shadow-sm border p-6" style={{ borderColor: COLORS.secondary }}>
          <div className="flex items-center space-x-3 mb-4">
            <AlertCircle className="w-8 h-8" style={{ color: COLORS.danger }} />
            <div>
              <h3 className="font-semibold" style={{ color: COLORS.danger }}>Error Loading Data</h3>
              <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>{error}</p>
            </div>
          </div>
          <button 
            onClick={fetchPermits}
            className="w-full px-4 py-2 rounded-lg flex items-center justify-center gap-2 transition-all"
            style={{ backgroundColor: COLORS.primary, color: 'white' }}
          >
            <RefreshCw className="w-4 h-4" />
            Try Again
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
      {/* Header */}
      <div className="border-b bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
              <h1 className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>
                Business Permit Registry
              </h1>
              <div className="flex items-center gap-2 text-sm" style={{ color: COLORS.secondary }}>
                <Briefcase className="w-4 h-4" />
                <span>{permits.length} Business Permits • {new Date().toLocaleDateString('en-PH')}</span>
              </div>
            </div>
            
            <div className="flex gap-3">
              <button
                onClick={fetchPermits}
                className="px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all flex items-center gap-2"
                style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
              >
                <RefreshCw className="w-4 h-4" />
                Refresh
              </button>
              
              <button
                onClick={handleExport}
                className="px-4 py-2 rounded-lg transition-all flex items-center gap-2"
                style={{ backgroundColor: COLORS.primary, color: 'white' }}
                disabled={filteredPermits.length === 0}
              >
                <Download className="w-4 h-4" />
                Export CSV
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Quick Stats */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="bg-white border rounded-xl p-4" style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium" style={{ color: COLORS.secondary }}>Total Permits</p>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.dark }}>{permits.length}</p>
              </div>
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                <Briefcase className="w-6 h-6" style={{ color: COLORS.primary }} />
              </div>
            </div>
          </div>
          
          <div className="bg-white border rounded-xl p-4" style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium" style={{ color: COLORS.secondary }}>Active Permits</p>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.dark }}>
                  {permits.filter(p => ['active', 'approved', 'renewed'].includes(p.status?.toLowerCase())).length}
                </p>
              </div>
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.success}15` }}>
                <CheckCircle className="w-6 h-6" style={{ color: COLORS.success }} />
              </div>
            </div>
          </div>
          
          <div className="bg-white border rounded-xl p-4" style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium" style={{ color: COLORS.secondary }}>Annual Revenue</p>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.dark }}>
                  {formatCurrency(permits.reduce((sum, p) => sum + (parseFloat(p.total_tax) || 0), 0))}
                </p>
              </div>
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.info}15` }}>
                <DollarSign className="w-6 h-6" style={{ color: COLORS.info }} />
              </div>
            </div>
          </div>
        </div>

        {/* Filters */}
        <div className="bg-white border rounded-xl p-4" style={{ borderColor: COLORS.secondary }}>
          <div className="flex flex-col lg:flex-row gap-4">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2" style={{ color: COLORS.secondary }} />
                <input
                  type="text"
                  placeholder="Search permit ID, business name, or owner..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500"
                  style={{ borderColor: COLORS.secondary }}
                />
              </div>
            </div>
            
            <div className="flex gap-4">
              <div className="relative min-w-[140px]">
                <Briefcase className="absolute left-3 top-1/2 transform -translate-y-1/2" style={{ color: COLORS.secondary }} />
                <select
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className="w-full pl-10 pr-8 py-2.5 border rounded-lg appearance-none bg-white"
                  style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                >
                  <option value="all">All Status</option>
                  <option value="active">Active</option>
                  <option value="pending">Pending</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
            </div>
          </div>
          
          <div className="mt-3 flex items-center justify-between text-sm">
            <div style={{ color: COLORS.secondary }}>
              {filteredPermits.length} of {permits.length} permits
            </div>
            {searchTerm && (
              <div style={{ color: COLORS.dark }}>
                Searching: <span className="font-medium">"{searchTerm}"</span>
              </div>
            )}
          </div>
        </div>

        {/* Business Permits Table - UPDATED: Removed status from Next Quarter column */}
        <div className="bg-white border rounded-xl overflow-hidden" style={{ borderColor: COLORS.secondary }}>
          <div className="p-4 border-b" style={{ borderColor: COLORS.secondary, backgroundColor: '#f9fafb' }}>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Hash className="w-5 h-5" style={{ color: COLORS.primary }} />
                <h3 className="font-semibold" style={{ color: COLORS.dark }}>Business Permits</h3>
                <span className="text-sm px-2 py-1 rounded-full bg-blue-100" style={{ color: COLORS.primary }}>
                  {filteredPermits.length}
                </span>
              </div>
              <div className="text-sm flex items-center gap-2" style={{ color: COLORS.secondary }}>
                <CalendarDays className="w-4 h-4" />
                <span>Current: Q{Math.floor((new Date().getMonth() / 3)) + 1} {new Date().getFullYear()}</span>
              </div>
            </div>
          </div>
          
          {filteredPermits.length === 0 ? (
            <div className="text-center py-12">
              <Briefcase className="w-12 h-12 mx-auto mb-3" style={{ color: COLORS.secondary }} />
              <p className="font-medium mb-1" style={{ color: COLORS.dark }}>No permits found</p>
              <p className="text-sm" style={{ color: COLORS.secondary }}>
                {searchTerm ? "Try adjusting your search" : "No business permits available"}
              </p>
            </div>
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead>
                    <tr className="border-b" style={{ borderColor: COLORS.secondary }}>
                      <th className="p-4 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary, width: '140px' }}>
                        Permit ID
                      </th>
                      <th className="p-4 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary, width: '220px' }}>
                        Business & Owner
                      </th>
                      <th className="p-4 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary, width: '120px' }}>
                        Owner Type
                      </th>
                      <th className="p-4 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary, width: '140px' }}>
                        Business Type
                      </th>
                      <th className="p-4 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary, width: '120px' }}>
                        Business Status
                      </th>
                      <th className="p-4 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary, width: '140px' }}>
                        Next Quarter
                      </th>
                      <th className="p-4 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary, width: '100px' }}>
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {filteredPermits.map((permit) => (
                      <tr key={permit.id} className="border-b hover:bg-gray-50 transition-colors" style={{ borderColor: '#e5e7eb' }}>
                        {/* Permit ID Column */}
                        <td className="p-4">
                          <div className="font-mono font-medium" style={{ color: COLORS.primary }}>
                            {permit.business_permit_id}
                          </div>
                          <div className="text-xs mt-1 flex items-center gap-1" style={{ color: COLORS.secondary }}>
                            <CalendarDays className="w-3 h-3" />
                            Issued: {formatDate(permit.issue_date)}
                          </div>
                        </td>
                        
                        {/* Business & Owner Column */}
                        <td className="p-4">
                          <div className="space-y-2">
                            <div>
                              <div className="font-semibold" style={{ color: COLORS.dark }}>
                                {permit.business_name || permit.trade_name || 'Unnamed Business'}
                              </div>
                              <div className="flex items-center gap-1.5 text-sm mt-1" style={{ color: COLORS.secondary }}>
                                <User className="w-3.5 h-3.5" />
                                <span>{shortenOwnerName(permit.owner_name)}</span>
                              </div>
                            </div>
                            
                            {permit.total_tax > 0 && (
                              <div className="text-xs px-2 py-1 rounded bg-gray-100 inline-block">
                                <span style={{ color: COLORS.dark }}>Annual Tax: {formatCurrency(permit.total_tax)}</span>
                              </div>
                            )}
                          </div>
                        </td>
                        
                        {/* Owner Type Column */}
                        <td className="p-4">
                          <div className="px-3 py-1.5 rounded-lg border text-center" 
                               style={{ 
                                 backgroundColor: permit.owner_type?.toLowerCase() === 'corporation' ? `${COLORS.info}15` : `${COLORS.primary}15`,
                                 borderColor: permit.owner_type?.toLowerCase() === 'corporation' ? `${COLORS.info}30` : `${COLORS.primary}30`,
                                 color: permit.owner_type?.toLowerCase() === 'corporation' ? COLORS.info : COLORS.primary
                               }}>
                            <span className="text-sm font-medium">
                              {permit.owner_type || 'Individual'}
                            </span>
                          </div>
                        </td>
                        
                        {/* Business Type Column */}
                        <td className="p-4">
                          <BusinessTypeBadge type={permit.business_type} nature={permit.business_nature} />
                        </td>
                        
                        {/* Business Status Column - ONLY BUSINESS PERMIT STATUS */}
                        <td className="p-4">
                          <BusinessStatusBadge status={permit.status} />
                          {permit.expiry_date && (
                            <div className="text-xs mt-2" style={{ color: COLORS.secondary }}>
                              Expires: {formatDate(permit.expiry_date)}
                            </div>
                          )}
                        </td>
                        
                        {/* Next Quarter Column - NO STATUS, JUST QUARTER INFO */}
                        <td className="p-4">
                          <NextQuarterComponent 
                            nextDueDate={permit.next_due_date}
                            paidQuarters={permit.paid_quarters || 0}
                            totalQuarters={permit.total_quarters || 0}
                          />
                        </td>
                        
                        {/* Actions Column */}
                        <td className="p-4">
                          <button
                            onClick={() => handleViewDetails(permit.id)}
                            className="w-full px-3 py-2 rounded-lg flex items-center justify-center gap-2 transition-all hover:opacity-90"
                            style={{ backgroundColor: COLORS.primary, color: 'white' }}
                          >
                            <Eye className="w-4 h-4" />
                            View
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              
              {/* Table Footer */}
              <div className="p-4 border-t" style={{ borderColor: COLORS.secondary, backgroundColor: '#f9fafb' }}>
                <div className="flex items-center justify-between">
                  <div className="text-sm" style={{ color: COLORS.secondary }}>
                    Showing <span className="font-semibold" style={{ color: COLORS.dark }}>{filteredPermits.length}</span> business permits
                  </div>
                  <div className="flex items-center gap-4 text-sm">
                    <div className="flex items-center gap-2">
                      <div className="w-2 h-2 rounded-full" style={{ backgroundColor: COLORS.success }}></div>
                      <span style={{ color: COLORS.dark }}>Active: {permits.filter(p => ['active', 'approved', 'renewed'].includes(p.status?.toLowerCase())).length}</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <div className="w-2 h-2 rounded-full" style={{ backgroundColor: COLORS.warning }}></div>
                      <span style={{ color: COLORS.dark }}>Pending: {permits.filter(p => ['pending', 'for_approval'].includes(p.status?.toLowerCase())).length}</span>
                    </div>
                  </div>
                </div>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}