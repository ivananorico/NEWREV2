import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  Search,
  Filter,
  Eye,
  Download,
  RefreshCw,
  CheckCircle,
  Building,
  User,
  Calendar,
  DollarSign,
  Clock,
  AlertCircle,
  TrendingUp,
  Wallet,
  Percent,
  Phone,
  Mail,
  FileText,
  CreditCard,
  CalendarDays
} from "lucide-react";

// API configuration
const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

// Helper function for currency formatting (moved outside components)
const formatCurrency = (amount) => {
  const num = parseFloat(amount) || 0;
  if (num >= 1000000) {
    return `₱${(num / 1000000).toFixed(1)}M`;
  }
  if (num >= 1000) {
    return `₱${(num / 1000).toFixed(1)}K`;
  }
  return `₱${num.toFixed(2)}`;
};

// Helper function for date formatting
const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    });
  } catch (e) {
    return dateString;
  }
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
          text: status?.toUpperCase() || "Active",
          color: "bg-green-50 text-green-700 border border-green-200",
          icon: <CheckCircle className="w-3 h-3 mr-1" />
        };
      case 'pending':
        return {
          text: "Pending",
          color: "bg-yellow-50 text-yellow-700 border border-yellow-200",
          icon: <Clock className="w-3 h-3 mr-1" />
        };
      case 'expired':
        return {
          text: "Expired",
          color: "bg-red-50 text-red-700 border border-red-200",
          icon: <AlertCircle className="w-3 h-3 mr-1" />
        };
      default:
        return {
          text: status || "N/A",
          color: "bg-gray-50 text-gray-700 border border-gray-200",
          icon: null
        };
    }
  };

  const statusInfo = getStatusInfo();
  
  return (
    <span className={`inline-flex items-center px-2 py-1 rounded text-xs font-medium ${statusInfo.color}`}>
      {statusInfo.icon}
      {statusInfo.text}
    </span>
  );
};

// Tax Type Badge Component
const TaxTypeBadge = ({ type }) => {
  const getTypeInfo = () => {
    switch(type?.toLowerCase()) {
      case 'capital_investment':
        return {
          text: "Capital",
          color: "bg-purple-50 text-purple-700 border border-purple-200"
        };
      case 'gross_sales':
        return {
          text: "Gross Sales",
          color: "bg-indigo-50 text-indigo-700 border border-indigo-200"
        };
      default:
        return {
          text: type || "N/A",
          color: "bg-gray-50 text-gray-700 border border-gray-200"
        };
    }
  };

  const typeInfo = getTypeInfo();
  
  return (
    <span className={`inline-flex items-center px-2 py-1 rounded text-xs font-medium ${typeInfo.color}`}>
      {typeInfo.text}
    </span>
  );
};

// Payment Status Badge Component
const PaymentStatusBadge = ({ status, amount }) => {
  const getPaymentStatusInfo = () => {
    switch(status?.toLowerCase()) {
      case 'fully_paid':
        return {
          text: "Paid",
          color: "bg-green-50 text-green-700 border border-green-200",
          icon: <CheckCircle className="w-3 h-3 mr-1" />
        };
      case 'pending':
        return {
          text: "Pending",
          color: "bg-yellow-50 text-yellow-700 border border-yellow-200",
          icon: <Clock className="w-3 h-3 mr-1" />
        };
      case 'overdue':
        return {
          text: "Overdue",
          color: "bg-red-50 text-red-700 border border-red-200",
          icon: <AlertCircle className="w-3 h-3 mr-1" />
        };
      default:
        return {
          text: "No Tax",
          color: "bg-gray-50 text-gray-700 border border-gray-200",
          icon: null
        };
    }
  };

  const statusInfo = getPaymentStatusInfo();
  
  return (
    <div className="flex flex-col gap-1">
      <span className={`inline-flex items-center px-2 py-1 rounded text-xs font-medium ${statusInfo.color}`}>
        {statusInfo.icon}
        {statusInfo.text}
      </span>
      {amount > 0 && (
        <span className={`text-xs font-medium ${
          status === 'fully_paid' ? 'text-green-600' :
          status === 'overdue' ? 'text-red-600' :
          'text-yellow-600'
        }`}>
          {formatCurrency(amount)}
        </span>
      )}
    </div>
  );
};

export default function BusinessStatus() {
  const [permits, setPermits] = useState([]);
  const [summary, setSummary] = useState({
    total_businesses: 0,
    total_annual_revenue: 0,
    total_collected: 0,
    total_pending: 0,
    total_overdue: 0,
    collection_rate: 0,
    fully_paid_businesses: 0,
    pending_businesses: 0,
    overdue_businesses: 0
  });
  const [filteredPermits, setFilteredPermits] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState("");
  const [businessType, setBusinessType] = useState("all");
  const [statusFilter, setStatusFilter] = useState("all");
  const [paymentFilter, setPaymentFilter] = useState("all");
  const navigate = useNavigate();

  useEffect(() => {
    loadData();
  }, []);

  useEffect(() => {
    filterPermits();
  }, [permits, searchTerm, businessType, statusFilter, paymentFilter]);

  const loadData = async () => {
    try {
      setLoading(true);
      await fetchPermits();
    } catch (error) {
      console.error("Error loading data:", error);
    } finally {
      setLoading(false);
    }
  };

  const fetchPermits = async () => {
    try {
      const res = await fetch(
        `${API_BASE}/Business/BusinessStatus/get_permits.php`,
        { 
          headers: {
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
          }
        }
      );
      
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      
      const data = await res.json();
      
      if (data.status === "success") {
        const permitsData = data.permits || [];
        console.log("Fetched permits data:", permitsData);
        
        // Check data structure
        if (permitsData.length > 0) {
          console.log("First permit structure:", permitsData[0]);
          console.log("Available fields:", Object.keys(permitsData[0]));
        }
        
        setPermits(permitsData);
        
        // Use summary from backend or calculate
        if (data.summary) {
          setSummary(data.summary);
        } else {
          // Fallback calculation
          const totalBusinesses = permitsData.length;
          const totalRevenue = permitsData.reduce((sum, p) => sum + (parseFloat(p.total_tax) || 0), 0);
          const totalCollected = permitsData.reduce((sum, p) => sum + (parseFloat(p.total_paid_tax) || 0), 0);
          const totalPending = permitsData.reduce((sum, p) => sum + (parseFloat(p.total_pending_tax) || 0), 0);
          const totalOverdue = permitsData.reduce((sum, p) => sum + (parseFloat(p.overdue_tax_amount) || 0), 0);
          
          setSummary({
            total_businesses: totalBusinesses,
            total_annual_revenue: totalRevenue,
            total_collected: totalCollected,
            total_pending: totalPending,
            total_overdue: totalOverdue,
            collection_rate: totalRevenue > 0 ? Math.round((totalCollected / totalRevenue) * 100) : 0,
            fully_paid_businesses: permitsData.filter(p => p.payment_status === 'fully_paid').length,
            pending_businesses: permitsData.filter(p => p.payment_status === 'pending').length,
            overdue_businesses: permitsData.filter(p => p.payment_status === 'overdue').length
          });
        }
      } else {
        console.error("API returned error:", data);
      }
    } catch (err) {
      console.error("Error fetching permits:", err);
    }
  };

  const filterPermits = () => {
    let result = [...permits];

    // Search filter
    if (searchTerm) {
      const term = searchTerm.toLowerCase();
      result = result.filter(permit =>
        (permit.business_name?.toLowerCase().includes(term)) ||
        (permit.owner_name?.toLowerCase().includes(term)) ||
        (permit.owner_full_name?.toLowerCase().includes(term)) ||
        (permit.business_permit_id?.toLowerCase().includes(term)) ||
        (permit.applicant_id?.toLowerCase().includes(term))
      );
    }

    // Business type filter
    if (businessType !== "all") {
      result = result.filter(permit => permit.business_type === businessType);
    }

    // Status filter
    if (statusFilter !== "all") {
      result = result.filter(permit => permit.status === statusFilter);
    }

    // Payment status filter
    if (paymentFilter !== "all") {
      result = result.filter(permit => permit.payment_status === paymentFilter);
    }

    setFilteredPermits(result);
  };

  const getBusinessTypes = () => {
    const types = [...new Set(permits.map(p => p.business_type).filter(Boolean))];
    return types.sort();
  };

  const getStatusTypes = () => {
    const types = [...new Set(permits.map(p => p.status).filter(Boolean))];
    return types.sort();
  };

  const getPaymentStatusTypes = () => {
    const types = [...new Set(permits.map(p => p.payment_status).filter(Boolean))];
    return types.sort();
  };

  const exportToCSV = () => {
    const headers = [
      "Permit ID", "Business Name", "Owner", "Contact", "Email", "Business Type", 
      "Tax Type", "Status", "Annual Tax", "Paid Amount", "Pending Amount", "Overdue Amount",
      "Payment Status", "Issue Date", "Expiry Date"
    ];
    
    const csvData = [
      headers.join(","),
      ...filteredPermits.map(p => [
        `"${p.business_permit_id || p.applicant_id || 'N/A'}"`,
        `"${p.business_name || ''}"`,
        `"${p.owner_name || p.owner_full_name || ''}"`,
        `"${p.contact_number || 'N/A'}"`,
        `"${p.owner_email || p.email_address || 'N/A'}"`,
        `"${p.business_type || ''}"`,
        `"${p.tax_calculation_type || 'N/A'}"`,
        `"${p.status || ''}"`,
        p.total_tax || 0,
        p.total_paid_tax || 0,
        p.total_pending_tax || 0,
        p.overdue_tax_amount || 0,
        `"${p.payment_status || ''}"`,
        `"${p.issue_date_formatted || p.issue_date || ''}"`,
        `"${p.expiry_date_formatted || p.expiry_date || ''}"`
      ].join(","))
    ].join("\n");

    const blob = new Blob([csvData], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `business-permits-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-white flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-800 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading business permits...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white">
      {/* Header */}
      <div className="border-b border-gray-200 bg-white px-4 py-4">
        <div className="max-w-7xl mx-auto">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <h1 className="text-xl font-bold text-gray-900">Business Tax Management</h1>
              <p className="text-sm text-gray-600">Monitor business permits and tax collection</p>
            </div>
            
            <div className="flex gap-2">
              <button
                onClick={loadData}
                disabled={loading}
                className="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
              >
                <RefreshCw className="w-4 h-4" />
              </button>
              <button
                onClick={exportToCSV}
                className="px-3 py-2 bg-gray-900 text-white rounded-lg flex items-center gap-1"
              >
                <Download className="w-4 h-4" />
                Export
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 py-4">
        {/* Revenue Summary Boxes */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
          {/* Total Annual Revenue */}
          <div className="bg-white border border-blue-200 rounded-lg p-4 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Annual Revenue</p>
                <p className="text-xl font-bold text-blue-700 mt-1">{formatCurrency(summary.total_annual_revenue)}</p>
              </div>
              <div className="p-2 bg-blue-100 rounded">
                <TrendingUp className="w-5 h-5 text-blue-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500 mt-2">
              Total expected revenue from all businesses
            </div>
          </div>
          
          {/* Total Collected */}
          <div className="bg-white border border-green-200 rounded-lg p-4 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Collected</p>
                <p className="text-xl font-bold text-green-700 mt-1">{formatCurrency(summary.total_collected)}</p>
              </div>
              <div className="p-2 bg-green-100 rounded">
                <Wallet className="w-5 h-5 text-green-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500 mt-2">
              Actual payments received
            </div>
          </div>
          
          {/* Collection Rate */}
          <div className="bg-white border border-purple-200 rounded-lg p-4 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Collection Rate</p>
                <p className="text-xl font-bold text-purple-700 mt-1">{summary.collection_rate}%</p>
              </div>
              <div className="p-2 bg-purple-100 rounded">
                <Percent className="w-5 h-5 text-purple-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500 mt-2">
              {formatCurrency(summary.total_pending)} pending • {formatCurrency(summary.total_overdue)} overdue
            </div>
          </div>
        </div>

        {/* Business Summary */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
          <div className="bg-white border border-gray-200 rounded-lg p-4">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-blue-100 rounded">
                <Building className="w-5 h-5 text-blue-600" />
              </div>
              <div>
                <p className="text-lg font-bold text-gray-900">{summary.total_businesses}</p>
                <p className="text-xs text-gray-600">Total Businesses</p>
              </div>
            </div>
          </div>
          
          <div className="bg-white border border-gray-200 rounded-lg p-4">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-green-100 rounded">
                <CheckCircle className="w-5 h-5 text-green-600" />
              </div>
              <div>
                <p className="text-lg font-bold text-gray-900">{summary.fully_paid_businesses}</p>
                <p className="text-xs text-gray-600">Fully Paid</p>
              </div>
            </div>
          </div>
          
          <div className="bg-white border border-gray-200 rounded-lg p-4">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-yellow-100 rounded">
                <Clock className="w-5 h-5 text-yellow-600" />
              </div>
              <div>
                <p className="text-lg font-bold text-gray-900">{summary.pending_businesses}</p>
                <p className="text-xs text-gray-600">Pending Payment</p>
              </div>
            </div>
          </div>
          
          <div className="bg-white border border-gray-200 rounded-lg p-4">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-red-100 rounded">
                <AlertCircle className="w-5 h-5 text-red-600" />
              </div>
              <div>
                <p className="text-lg font-bold text-gray-900">{summary.overdue_businesses}</p>
                <p className="text-xs text-gray-600">Overdue</p>
              </div>
            </div>
          </div>
        </div>

        {/* Filters */}
        <div className="bg-white border border-gray-200 rounded-lg p-4 mb-4">
          <div className="flex flex-col lg:flex-row gap-3">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <input
                  type="text"
                  placeholder="Search business, owner, or permit ID..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </div>
            
            <div className="flex gap-3">
              <div className="relative">
                <Filter className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <select
                  value={businessType}
                  onChange={(e) => setBusinessType(e.target.value)}
                  className="pl-10 pr-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="all">All Business Types</option>
                  {getBusinessTypes().map(type => (
                    <option key={type} value={type}>{type}</option>
                  ))}
                </select>
              </div>
              
              <div className="relative">
                <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <select
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className="pl-10 pr-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="all">All Status</option>
                  {getStatusTypes().map(status => (
                    <option key={status} value={status}>{status}</option>
                  ))}
                </select>
              </div>

              <div className="relative">
                <CreditCard className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <select
                  value={paymentFilter}
                  onChange={(e) => setPaymentFilter(e.target.value)}
                  className="pl-10 pr-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="all">All Payments</option>
                  {getPaymentStatusTypes().map(status => (
                    <option key={status} value={status}>
                      {status === 'fully_paid' ? 'Fully Paid' :
                       status === 'pending' ? 'Pending' :
                       status === 'overdue' ? 'Overdue' :
                       'No Tax'}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </div>
          
          <div className="mt-3 text-sm text-gray-600">
            Showing {filteredPermits.length} of {permits.length} businesses
          </div>
        </div>

        {/* Business List Table */}
        <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-48">
                    Permit Details
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-48">
                    Business Info
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">
                    Tax Information
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                    Payment Status
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {filteredPermits.map((permit) => {
                  const permitId = permit.business_permit_id || permit.applicant_id;
                  const ownerName = permit.owner_name || permit.owner_full_name;
                  const contactNumber = permit.contact_number;
                  const ownerEmail = permit.owner_email || permit.email_address;
                  const businessType = permit.business_type || permit.business_nature;
                  
                  return (
                    <tr key={permit.id} className="hover:bg-gray-50">
                      {/* Permit Details */}
                      <td className="px-4 py-3">
                        <div className="font-mono text-sm font-semibold text-blue-600">
                          {permitId}
                        </div>
                        <div className="flex items-center gap-1 text-xs text-gray-500 mt-1">
                          <CalendarDays className="w-3 h-3" />
                          Issued: {formatDate(permit.issue_date)}
                        </div>
                        {permit.expiry_date && (
                          <div className="text-xs text-gray-500 mt-0.5">
                            Expires: {formatDate(permit.expiry_date)}
                          </div>
                        )}
                        <div className="mt-2">
                          <BusinessStatusBadge status={permit.status} />
                        </div>
                      </td>
                      
                      {/* Business Info */}
                      <td className="px-4 py-3">
                        <div className="font-medium text-gray-900">
                          {permit.business_name}
                        </div>
                        <div className="text-sm text-gray-600 mt-1">
                          {ownerName}
                        </div>
                        
                        <div className="mt-2 space-y-1">
                          {contactNumber && (
                            <div className="flex items-center gap-1 text-xs text-gray-600">
                              <Phone className="w-3 h-3" />
                              {contactNumber}
                            </div>
                          )}
                          
                          {ownerEmail && (
                            <div className="flex items-center gap-1 text-xs text-gray-600">
                              <Mail className="w-3 h-3" />
                              {ownerEmail}
                            </div>
                          )}
                        </div>
                        
                        <div className="mt-2">
                          <span className="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800">
                            {businessType}
                          </span>
                        </div>
                      </td>
                      
                      {/* Tax Information */}
                      <td className="px-4 py-3">
                        <div className="space-y-2">
                          <div>
                            <div className="text-xs text-gray-500">Annual Tax</div>
                            <div className="text-sm font-bold text-gray-900">
                              {formatCurrency(permit.total_tax)}
                            </div>
                          </div>
                          
                          <div>
                            <div className="text-xs text-gray-500">Paid Amount</div>
                            <div className="text-sm font-bold text-green-600">
                              {formatCurrency(permit.total_paid_tax)}
                            </div>
                          </div>
                          
                          <div className="flex items-center gap-2">
                            <TaxTypeBadge type={permit.tax_calculation_type} />
                            {permit.progress_info && (
                              <div className="text-xs text-gray-500">
                                {permit.progress_info.completion_rate}%
                              </div>
                            )}
                          </div>
                        </div>
                      </td>
                      
                      {/* Payment Status */}
                      <td className="px-4 py-3">
                        <PaymentStatusBadge 
                          status={permit.payment_status} 
                          amount={permit.total_pending_tax}
                        />
                        
                        {permit.overdue_tax_amount > 0 && (
                          <div className="text-xs text-red-600 mt-1">
                            Overdue: {formatCurrency(permit.overdue_tax_amount)}
                          </div>
                        )}
                        
                        {permit.next_due_date && permit.payment_status !== 'fully_paid' && (
                          <div className="text-xs text-gray-500 mt-1">
                            Next due: {formatDate(permit.next_due_date)}
                          </div>
                        )}
                        
                        {permit.progress_info && (
                          <div className="text-xs text-gray-500 mt-1">
                            {permit.progress_info.paid_quarters}/{permit.progress_info.total_quarters} quarters paid
                          </div>
                        )}
                      </td>
                      
                      {/* Actions - Only View button */}
                      <td className="px-4 py-3">
                        <button
                          onClick={() => navigate(`/business/businessstatusinfo/${permit.id}`)}
                          className="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 flex items-center gap-1 justify-center"
                        >
                          <Eye className="w-3.5 h-3.5" />
                          View
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
            
            {filteredPermits.length === 0 && (
              <div className="text-center py-8">
                <Building className="w-12 h-12 text-gray-300 mx-auto mb-2" />
                <p className="text-gray-500">No businesses found</p>
                <p className="text-gray-400 text-sm mt-1">
                  {searchTerm || businessType !== 'all' || statusFilter !== 'all' || paymentFilter !== 'all'
                    ? "Try adjusting your filters"
                    : "No approved businesses available"}
                </p>
              </div>
            )}
          </div>
        </div>

        {/* Financial Summary Footer */}
        <div className="mt-4 bg-white border border-gray-200 rounded-lg p-4">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <p className="text-sm text-gray-600">
                Summary: <span className="font-semibold">{summary.total_businesses}</span> businesses
              </p>
              <p className="text-xs text-gray-500">
                Annual Revenue: {formatCurrency(summary.total_annual_revenue)} • 
                Collected: {formatCurrency(summary.total_collected)} • 
                Pending: {formatCurrency(summary.total_pending)}
              </p>
            </div>
            
            <div className="flex gap-4 text-sm text-gray-600">
              <div>
                Fully Paid: <span className="font-semibold">{summary.fully_paid_businesses}</span>
              </div>
              <div>
                Pending: <span className="font-semibold">{summary.pending_businesses}</span>
              </div>
              <div>
                Overdue: <span className="font-semibold">{summary.overdue_businesses}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}