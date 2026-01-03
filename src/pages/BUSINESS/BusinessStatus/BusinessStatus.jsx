import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  Search,
  Download,
  Eye,
  CheckCircle,
  Building,
  DollarSign,
  TrendingUp,
  Clock,
  AlertTriangle,
  Home,
  Calendar,
  ShieldCheck,
  Activity,
  FileText,
  Calculator
} from "lucide-react";

// API configuration
const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

export default function BusinessStatus() {
  const [permits, setPermits] = useState([]);
  const [summary, setSummary] = useState({
    total_businesses: 0,
    total_revenue: 0,
    pending_payments: 0,
    collection_rate: 0,
    fully_paid_count: 0,
    overdue_count: 0
  });
  const [filteredPermits, setFilteredPermits] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState("");
  const [businessType, setBusinessType] = useState("all");
  const [paymentStatus, setPaymentStatus] = useState("all");
  const navigate = useNavigate();

  useEffect(() => {
    loadData();
  }, []);

  useEffect(() => {
    filterPermits();
  }, [permits, searchTerm, businessType, paymentStatus]);

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
        setPermits(permitsData);
        
        // Calculate summary statistics
        const totalBusinesses = permitsData.length;
        const totalRevenue = permitsData.reduce((sum, p) => sum + (parseFloat(p.total_paid_tax) || 0), 0);
        const pendingPayments = permitsData.reduce((sum, p) => sum + (parseFloat(p.total_pending_tax) || 0), 0);
        const fullyPaid = permitsData.filter(p => p.payment_status === 'fully_paid').length;
        const overdueCount = permitsData.filter(p => p.payment_status === 'overdue').length;
        
        setSummary({
          total_businesses: totalBusinesses,
          total_revenue: totalRevenue,
          pending_payments: pendingPayments,
          collection_rate: totalBusinesses > 0 ? Math.round((fullyPaid / totalBusinesses) * 100) : 0,
          fully_paid_count: fullyPaid,
          overdue_count: overdueCount
        });
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
        (permit.business_permit_id?.toLowerCase().includes(term))
      );
    }

    // Business type filter
    if (businessType !== "all") {
      result = result.filter(permit => permit.business_type === businessType);
    }

    // Payment status filter
    if (paymentStatus !== "all") {
      result = result.filter(permit => permit.payment_status === paymentStatus);
    }

    setFilteredPermits(result);
  };

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
      return "N/A";
    }
  };

  const getBusinessStatusBadge = (status) => {
    switch(status) {
      case 'Active':
        return {
          text: "Active",
          bgColor: "bg-green-50",
          textColor: "text-green-700",
          icon: Activity
        };
      case 'Approved':
        return {
          text: "Approved",
          bgColor: "bg-blue-50",
          textColor: "text-blue-700",
          icon: ShieldCheck
        };
      case 'Renewed':
        return {
          text: "Renewed",
          bgColor: "bg-purple-50",
          textColor: "text-purple-700",
          icon: FileText
        };
      case 'Pending':
        return {
          text: "Pending",
          bgColor: "bg-yellow-50",
          textColor: "text-yellow-700",
          icon: Clock
        };
      case 'Expired':
        return {
          text: "Expired",
          bgColor: "bg-red-50",
          textColor: "text-red-700",
          icon: AlertTriangle
        };
      default:
        return {
          text: status || "N/A",
          bgColor: "bg-gray-50",
          textColor: "text-gray-700",
          icon: ShieldCheck
        };
    }
  };

  const getTaxCalculationType = (type) => {
    switch(type?.toLowerCase()) {
      case 'percentage':
        return {
          text: "Percentage",
          bgColor: "bg-blue-50",
          textColor: "text-blue-700",
          icon: Calculator
        };
      case 'fixed':
        return {
          text: "Fixed Amount",
          bgColor: "bg-green-50",
          textColor: "text-green-700",
          icon: Calculator
        };
      case 'graduated':
        return {
          text: "Graduated",
          bgColor: "bg-purple-50",
          textColor: "text-purple-700",
          icon: Calculator
        };
      default:
        return {
          text: type || "N/A",
          bgColor: "bg-gray-50",
          textColor: "text-gray-700",
          icon: Calculator
        };
    }
  };

  const getBusinessTypes = () => {
    const types = [...new Set(permits.map(p => p.business_type).filter(Boolean))];
    return types.sort();
  };

  const exportToCSV = () => {
    const headers = [
      "Business Name", "Owner", "Permit ID", "Business Type", "Status",
      "Location", "Approved Date", "Annual Tax", "Tax Calculation Type"
    ];
    
    const csvData = [
      headers.join(","),
      ...filteredPermits.map(p => [
        `"${p.business_name || ''}"`,
        `"${p.owner_name || ''}"`,
        `"${p.business_permit_id || 'N/A'}"`,
        `"${p.business_type || ''}"`,
        `"${p.status || ''}"`,
        `"${p.barangay || ''}, ${p.city || ''}"`,
        `"${formatDate(p.approved_date)}"`,
        p.total_tax || 0,
        `"${p.tax_calculation_type || 'N/A'}"`
      ].join(","))
    ].join("\n");

    const blob = new Blob([csvData], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `approved-businesses-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading approved businesses...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 p-4 lg:p-6">
      {/* Header */}
      <div className="mb-6">
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-800">Approved Business Dashboard</h1>
            <p className="text-gray-600 mt-1">Monitor business permits and annual taxes</p>
          </div>
          <button
            onClick={loadData}
            disabled={loading}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-2 disabled:opacity-50 text-sm"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Refresh
          </button>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
          <div className="flex items-center">
            <div className="p-2 bg-blue-100 rounded-lg">
              <Building className="w-5 h-5 text-blue-600" />
            </div>
            <div className="ml-3">
              <p className="text-xs text-gray-500">Total</p>
              <p className="text-lg font-bold text-gray-800">{summary.total_businesses}</p>
              <p className="text-xs text-gray-500">Approved Businesses</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
          <div className="flex items-center">
            <div className="p-2 bg-green-100 rounded-lg">
              <DollarSign className="w-5 h-5 text-green-600" />
            </div>
            <div className="ml-3">
              <p className="text-xs text-gray-500">Annual Tax</p>
              <p className="text-lg font-bold text-gray-800">{formatCurrency(summary.total_revenue)}</p>
              <p className="text-xs text-gray-500">Total Generated</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
          <div className="flex items-center">
            <div className="p-2 bg-yellow-100 rounded-lg">
              <TrendingUp className="w-5 h-5 text-yellow-600" />
            </div>
            <div className="ml-3">
              <p className="text-xs text-gray-500">Rate</p>
              <p className="text-lg font-bold text-gray-800">{summary.collection_rate}%</p>
              <p className="text-xs text-gray-500">Payment Rate</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
          <div className="flex items-center">
            <div className="p-2 bg-red-100 rounded-lg">
              <AlertTriangle className="w-5 h-5 text-red-600" />
            </div>
            <div className="ml-3">
              <p className="text-xs text-gray-500">Balance</p>
              <p className="text-lg font-bold text-gray-800">{formatCurrency(summary.pending_payments)}</p>
              <p className="text-xs text-gray-500">Pending Payments</p>
            </div>
          </div>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-lg shadow p-4 mb-4 border border-gray-200">
        <div className="flex flex-col lg:flex-row gap-3">
          <div className="flex-1">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={18} />
              <input
                type="text"
                placeholder="Search businesses, owners, permit ID..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <select
              value={businessType}
              onChange={(e) => setBusinessType(e.target.value)}
              className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
            >
              <option value="all">All Types</option>
              {getBusinessTypes().map(type => (
                <option key={type} value={type}>{type}</option>
              ))}
            </select>

            <select
              value={paymentStatus}
              onChange={(e) => setPaymentStatus(e.target.value)}
              className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
            >
              <option value="all">All Payments</option>
              <option value="fully_paid">Paid</option>
              <option value="pending">Unpaid</option>
              <option value="overdue">Overdue</option>
            </select>
          </div>

          <button
            onClick={exportToCSV}
            className="flex items-center justify-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors text-sm"
          >
            <Download size={16} />
            Export
          </button>
        </div>
      </div>

      {/* Results Info */}
      <div className="mb-3">
        <p className="text-sm text-gray-600">
          Showing <span className="font-semibold">{filteredPermits.length}</span> of{" "}
          <span className="font-semibold">{permits.length}</span> businesses
        </p>
      </div>

      {/* Business List */}
      <div className="bg-white rounded-lg shadow overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Business Info
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Location
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Annual Tax
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Tax Calculation
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {filteredPermits.map((permit) => {
                const businessStatusBadge = getBusinessStatusBadge(permit.status);
                const BusinessStatusIcon = businessStatusBadge.icon;
                const taxCalculationBadge = getTaxCalculationType(permit.tax_calculation_type);
                const TaxCalculationIcon = taxCalculationBadge.icon;
                
                return (
                  <tr key={permit.id} className="hover:bg-gray-50">
                    {/* Business Info */}
                    <td className="px-4 py-4">
                      <div className="space-y-1">
                        <p className="font-semibold text-gray-900">{permit.business_name || "No Name"}</p>
                        <p className="text-gray-600 text-xs">{permit.owner_name || "No Owner"}</p>
                        <div className="flex items-center gap-2 pt-1">
                          <span className="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded">
                            {permit.business_type || "N/A"}
                          </span>
                          <span className="text-xs text-blue-600 font-mono">{permit.business_permit_id || "N/A"}</span>
                        </div>
                      </div>
                    </td>

                    {/* Location */}
                    <td className="px-4 py-4">
                      <div className="space-y-1">
                        <div className="flex items-start gap-2">
                          <Home className="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" />
                          <div>
                            <p className="text-gray-900">{permit.barangay || "N/A"}</p>
                            <p className="text-gray-500 text-xs">{permit.city || "N/A"}</p>
                          </div>
                        </div>
                        <div className="flex items-center text-gray-400 text-xs pt-1">
                          <Calendar className="w-3 h-3 mr-1 flex-shrink-0" />
                          <span>Approved: {formatDate(permit.approved_date)}</span>
                        </div>
                      </div>
                    </td>

                    {/* Business Status */}
                    <td className="px-4 py-4">
                      <div className="flex items-center justify-center">
                        <div className={`inline-flex items-center gap-2 px-3 py-2 rounded-lg ${businessStatusBadge.bgColor} ${businessStatusBadge.textColor}`}>
                          <BusinessStatusIcon className="w-4 h-4 flex-shrink-0" />
                          <span className="text-sm font-semibold whitespace-nowrap">{businessStatusBadge.text}</span>
                        </div>
                      </div>
                    </td>

                    {/* Annual Tax */}
                    <td className="px-4 py-4">
                      <div className="flex items-center justify-center">
                        <div className="text-center">
                          <p className="text-lg font-bold text-blue-700">
                            {formatCurrency(permit.total_tax)}
                          </p>
                          <p className="text-gray-500 text-xs mt-1">Annual Tax</p>
                        </div>
                      </div>
                    </td>

                    {/* Tax Calculation Type */}
                    <td className="px-4 py-4">
                      <div className="flex items-center justify-center">
                        <div className={`inline-flex items-center gap-2 px-3 py-2 rounded-lg ${taxCalculationBadge.bgColor} ${taxCalculationBadge.textColor}`}>
                          <TaxCalculationIcon className="w-4 h-4 flex-shrink-0" />
                          <span className="text-sm font-semibold whitespace-nowrap">{taxCalculationBadge.text}</span>
                        </div>
                      </div>
                    </td>

                    {/* Actions */}
                    <td className="px-4 py-4">
                      <div className="flex items-center justify-center">
                        <button
                          onClick={() => navigate(`/business/businessstatusinfo/${permit.id}`)}
                          className="inline-flex items-center justify-center gap-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-xs"
                        >
                          <Eye size={12} />
                          Details
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>

          {filteredPermits.length === 0 && (
            <div className="text-center py-8">
              <Building className="w-12 h-12 text-gray-300 mx-auto mb-3" />
              <p className="text-gray-500">No businesses found</p>
              <p className="text-gray-400 text-sm mt-1">
                {searchTerm || businessType !== 'all' || paymentStatus !== 'all'
                  ? "Try adjusting your filters"
                  : "No approved businesses available"}
              </p>
            </div>
          )}
        </div>
      </div>

      {/* Footer Summary */}
      <div className="mt-4 p-3 bg-white rounded-lg shadow border border-gray-200">
        <div className="flex flex-col sm:flex-row justify-between items-center gap-3">
          <div>
            <p className="text-sm text-gray-600">
              {new Date().toLocaleDateString('en-PH', { 
                weekday: 'short',
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
              })}
            </p>
            <p className="text-gray-500 text-xs">
              {summary.total_businesses} businesses • Total Annual Tax: {formatCurrency(summary.total_revenue)}
            </p>
          </div>
          
          <div className="flex items-center gap-4">
            <div className="text-center">
              <p className="text-xs text-gray-500">Approved</p>
              <p className="text-sm font-bold text-blue-600">{summary.total_businesses}</p>
            </div>
            
            <div className="text-center">
              <p className="text-xs text-gray-500">Average Tax</p>
              <p className="text-sm font-bold text-green-600">
                {summary.total_businesses > 0 
                  ? formatCurrency(summary.total_revenue / summary.total_businesses) 
                  : "₱0"}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}