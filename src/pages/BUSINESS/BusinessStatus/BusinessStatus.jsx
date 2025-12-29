import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  Search,
  Filter,
  Download,
  Eye,
  CheckCircle,
  Building,
  DollarSign,
  TrendingUp,
  Clock,
  AlertTriangle,
  Home,
  Percent,
  CheckSquare,
  XCircle
} from "lucide-react";

export default function BusinessStatus() {
  const [permits, setPermits] = useState([]);
  const [summary, setSummary] = useState({
    total_businesses: 0,
    total_revenue: 0,
    pending_payments: 0,
    overdue_payments: 0,
    collection_rate: 0,
    fully_paid_count: 0
  });
  const [filteredPermits, setFilteredPermits] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState("");
  const [businessType, setBusinessType] = useState("all");
  const [paymentStatus, setPaymentStatus] = useState("all");
  const [barangayFilter, setBarangayFilter] = useState("all");
  const navigate = useNavigate();

  useEffect(() => {
    // Load data on component mount
    const loadData = async () => {
      try {
        setLoading(true);
        
        // First, try to calculate penalties (silently, don't wait for it)
        calculatePenaltiesSilently();
        
        // Then fetch permits data
        await fetchPermits();
      } catch (error) {
        console.error("Error loading data:", error);
      } finally {
        setLoading(false);
      }
    };
    
    loadData();
  }, []);

  useEffect(() => {
    filterPermits();
  }, [permits, searchTerm, businessType, paymentStatus, barangayFilter]);

  // Silent penalty calculation - doesn't block UI
  const calculatePenaltiesSilently = async () => {
    try {
      await fetch(
        "http://localhost/revenue2/backend/Business/BusinessStatus/calculate_penalties.php",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          credentials: "include"
        }
      );
      // Don't wait for response or handle errors - just fire and forget
    } catch (error) {
      // Silently fail - penalties will be calculated next time
      console.log("Penalty calculation failed silently");
    }
  };

  const fetchPermits = async () => {
    try {
      const res = await fetch(
        "http://localhost/revenue2/backend/Business/BusinessStatus/get_permits.php",
        { credentials: "include" }
      );
      const data = await res.json();
      
      if (data.status === "success") {
        const permitsData = data.permits || [];
        setPermits(permitsData);
        
        // Calculate summary statistics
        const totalBusinesses = permitsData.length;
        const totalRevenue = permitsData.reduce((sum, p) => sum + (p.total_paid_tax || 0), 0);
        const pendingPayments = permitsData.reduce((sum, p) => sum + (p.total_pending_tax || 0), 0);
        const overduePayments = permitsData.reduce((sum, p) => sum + (p.overdue_tax_amount || 0), 0);
        const fullyPaid = permitsData.filter(p => p.payment_status === 'fully_paid').length;
        
        setSummary({
          total_businesses: totalBusinesses,
          total_revenue: totalRevenue,
          pending_payments: pendingPayments,
          overdue_payments: overduePayments,
          collection_rate: totalBusinesses > 0 ? Math.round((fullyPaid / totalBusinesses) * 100) : 0,
          fully_paid_count: fullyPaid
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

    // Barangay filter
    if (barangayFilter !== "all") {
      result = result.filter(permit => permit.barangay === barangayFilter);
    }

    setFilteredPermits(result);
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2
    }).format(amount || 0);
  };

  const formatDate = (dateString) => {
    if (!dateString) return "N/A";
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  const getPaymentStatusBadge = (status) => {
    switch(status) {
      case 'fully_paid':
        return {
          text: "Fully Paid",
          bgColor: "bg-green-100",
          textColor: "text-green-800",
          icon: CheckCircle,
          iconColor: "text-green-600"
        };
      case 'overdue':
        return {
          text: "Overdue",
          bgColor: "bg-red-100",
          textColor: "text-red-800",
          icon: AlertTriangle,
          iconColor: "text-red-600"
        };
      case 'pending':
        return {
          text: "Pending",
          bgColor: "bg-yellow-100",
          textColor: "text-yellow-800",
          icon: Clock,
          iconColor: "text-yellow-600"
        };
      default:
        return {
          text: "Unknown",
          bgColor: "bg-gray-100",
          textColor: "text-gray-800",
          icon: Clock,
          iconColor: "text-gray-600"
        };
    }
  };

  const getBusinessTypes = () => {
    const types = [...new Set(permits.map(p => p.business_type).filter(Boolean))];
    return types.sort();
  };

  const getBarangays = () => {
    const barangays = [...new Set(permits.map(p => p.barangay).filter(Boolean))];
    return barangays.sort();
  };

  const exportToCSV = () => {
    const headers = [
      "Business Name", "Owner", "Permit ID", "Type", "Location",
      "Approved Date", "Total Tax", "Paid Amount", "Pending Amount", 
      "Payment Status", "Collection Rate"
    ];
    
    const csvData = [
      headers.join(","),
      ...filteredPermits.map(p => [
        `"${p.business_name}"`,
        `"${p.owner_name}"`,
        `"${p.business_permit_id || 'N/A'}"`,
        `"${p.business_type}"`,
        `"${p.barangay}, ${p.city}"`,
        `"${formatDate(p.approved_date)}"`,
        p.total_tax,
        p.total_paid_tax,
        p.total_pending_tax,
        `"${getPaymentStatusBadge(p.payment_status).text}"`,
        p.tax_paid_percentage || 0
      ].join(","))
    ].join("\n");

    const blob = new Blob([csvData], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `business-monitoring-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading business data...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 p-4 lg:p-6">
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-800">Business Monitoring Dashboard</h1>
        <p className="text-gray-600 mt-1">Monitor approved businesses and tax collection status</p>
        <p className="text-sm text-gray-500 mt-2">
          Penalties are automatically updated. Last updated: {new Date().toLocaleTimeString('en-PH')}
        </p>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
          <div className="flex items-center">
            <div className="p-2 bg-blue-100 rounded-lg">
              <Building className="w-6 h-6 text-blue-600" />
            </div>
            <div className="ml-3">
              <p className="text-sm text-gray-500">Total Businesses</p>
              <p className="text-xl font-bold text-gray-800">{summary.total_businesses}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
          <div className="flex items-center">
            <div className="p-2 bg-green-100 rounded-lg">
              <DollarSign className="w-6 h-6 text-green-600" />
            </div>
            <div className="ml-3">
              <p className="text-sm text-gray-500">Total Collection</p>
              <p className="text-xl font-bold text-gray-800">{formatCurrency(summary.total_revenue)}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
          <div className="flex items-center">
            <div className="p-2 bg-yellow-100 rounded-lg">
              <TrendingUp className="w-6 h-6 text-yellow-600" />
            </div>
            <div className="ml-3">
              <p className="text-sm text-gray-500">Collection Rate</p>
              <p className="text-xl font-bold text-gray-800">{summary.collection_rate}%</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
          <div className="flex items-center">
            <div className="p-2 bg-orange-100 rounded-lg">
              <Clock className="w-6 h-6 text-orange-600" />
            </div>
            <div className="ml-3">
              <p className="text-sm text-gray-500">Pending Payments</p>
              <p className="text-xl font-bold text-gray-800">{formatCurrency(summary.pending_payments)}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
          <div className="flex items-center">
            <div className="p-2 bg-red-100 rounded-lg">
              <AlertTriangle className="w-6 h-6 text-red-600" />
            </div>
            <div className="ml-3">
              <p className="text-sm text-gray-500">Overdue</p>
              <p className="text-xl font-bold text-gray-800">{formatCurrency(summary.overdue_payments)}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-lg shadow p-4 mb-6 border border-gray-200">
        <div className="flex flex-col lg:flex-row gap-4">
          <div className="flex-1">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={20} />
              <input
                type="text"
                placeholder="Search business, owner, or permit ID..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <select
              value={businessType}
              onChange={(e) => setBusinessType(e.target.value)}
              className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="all">All Business Types</option>
              {getBusinessTypes().map(type => (
                <option key={type} value={type}>{type}</option>
              ))}
            </select>

            <select
              value={paymentStatus}
              onChange={(e) => setPaymentStatus(e.target.value)}
              className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="all">All Payment Status</option>
              <option value="fully_paid">Fully Paid</option>
              <option value="pending">Pending</option>
              <option value="overdue">Overdue</option>
            </select>

            <select
              value={barangayFilter}
              onChange={(e) => setBarangayFilter(e.target.value)}
              className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="all">All Barangays</option>
              {getBarangays().map(brgy => (
                <option key={brgy} value={brgy}>{brgy}</option>
              ))}
            </select>
          </div>

          <button
            onClick={exportToCSV}
            className="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors"
          >
            <Download size={18} />
            Export
          </button>
        </div>
      </div>

      {/* Results Info */}
      <div className="flex justify-between items-center mb-4">
        <p className="text-gray-600">
          Showing <span className="font-semibold">{filteredPermits.length}</span> of{" "}
          <span className="font-semibold">{permits.length}</span> approved businesses
        </p>
        <div className="flex items-center gap-2 text-sm">
          <div className="flex items-center gap-1">
            <CheckSquare className="w-4 h-4 text-green-600" />
            <span>Paid</span>
          </div>
          <div className="flex items-center gap-1">
            <Clock className="w-4 h-4 text-yellow-600" />
            <span>Pending</span>
          </div>
          <div className="flex items-center gap-1">
            <XCircle className="w-4 h-4 text-red-600" />
            <span>Overdue</span>
          </div>
        </div>
      </div>

      {/* Business List */}
      <div className="bg-white rounded-lg shadow overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Business Information
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Location
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Tax Summary
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {filteredPermits.map((permit) => {
                const statusBadge = getPaymentStatusBadge(permit.payment_status);
                const StatusIcon = statusBadge.icon;
                
                return (
                  <tr key={permit.id} className="hover:bg-gray-50">
                    {/* Business Info */}
                    <td className="px-6 py-4">
                      <div>
                        <p className="font-medium text-gray-900">{permit.business_name}</p>
                        <p className="text-sm text-gray-500 mt-1">{permit.owner_name}</p>
                        <div className="flex items-center gap-2 mt-2">
                          <span className="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                            {permit.business_type}
                          </span>
                          <span className="text-xs text-gray-500">
                            Permit: {permit.business_permit_id}
                          </span>
                        </div>
                        <p className="text-xs text-gray-500 mt-1">
                          Approved: {formatDate(permit.approved_date)}
                        </p>
                      </div>
                    </td>

                    {/* Location */}
                    <td className="px-6 py-4">
                      <div className="flex items-start gap-2">
                        <Home className="w-4 h-4 text-gray-400 mt-0.5" />
                        <div>
                          <p className="text-gray-900">{permit.barangay}</p>
                          <p className="text-sm text-gray-500">{permit.city}</p>
                        </div>
                      </div>
                    </td>

                    {/* Tax Summary */}
                    <td className="px-6 py-4">
                      <div className="space-y-2">
                        <div>
                          <p className="text-sm text-gray-500">Total Tax</p>
                          <p className="font-medium text-gray-900">{formatCurrency(permit.total_tax)}</p>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                          <div>
                            <p className="text-xs text-green-600">Paid</p>
                            <p className="text-sm font-medium">{formatCurrency(permit.total_paid_tax)}</p>
                          </div>
                          <div>
                            <p className="text-xs text-yellow-600">Pending</p>
                            <p className="text-sm font-medium">{formatCurrency(permit.total_pending_tax)}</p>
                          </div>
                        </div>
                        <div className="flex items-center gap-2">
                          <Percent className="w-4 h-4 text-gray-400" />
                          <span className="text-sm text-gray-600">
                            {permit.tax_paid_percentage || 0}% collected
                          </span>
                        </div>
                      </div>
                    </td>

                    {/* Status */}
                    <td className="px-6 py-4">
                      <div className={`inline-flex items-center gap-2 px-3 py-2 rounded-full ${statusBadge.bgColor} ${statusBadge.textColor}`}>
                        <StatusIcon className={`w-4 h-4 ${statusBadge.iconColor}`} />
                        <span className="text-sm font-medium">{statusBadge.text}</span>
                      </div>
                      {permit.pending_quarters_count > 0 && (
                        <p className="text-xs text-gray-500 mt-2">
                          {permit.pending_quarters_count} quarter(s) pending
                        </p>
                      )}
                      {permit.overdue_tax_amount > 0 && (
                        <p className="text-xs text-red-600 font-medium mt-1">
                          Overdue: {formatCurrency(permit.overdue_tax_amount)}
                        </p>
                      )}
                    </td>

                    {/* Actions */}
                    <td className="px-6 py-4">
                      <button
                        onClick={() => navigate(`/business/businessstatusinfo/${permit.id}`)}
                        className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm"
                      >
                        <Eye size={14} />
                        View Details
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>

          {filteredPermits.length === 0 && (
            <div className="text-center py-10">
              <Building className="w-12 h-12 text-gray-400 mx-auto mb-3" />
              <p className="text-gray-500">No businesses found matching your criteria</p>
            </div>
          )}
        </div>
      </div>

      {/* Footer Summary */}
      <div className="mt-6 p-4 bg-white rounded-lg shadow border border-gray-200">
        <div className="flex flex-col sm:flex-row justify-between items-center">
          <div>
            <p className="text-sm text-gray-600">
              As of {new Date().toLocaleDateString('en-PH')}
            </p>
            <p className="text-xs text-gray-500">
              Total approved businesses: {summary.total_businesses} | Collection rate: {summary.collection_rate}%
            </p>
          </div>
          <div className="flex items-center gap-4 mt-2 sm:mt-0">
            <div className="text-center">
              <p className="text-xs text-gray-500">Fully Paid</p>
              <p className="font-medium text-green-600">{summary.fully_paid_count || 0}</p>
            </div>
            <div className="text-center">
              <p className="text-xs text-gray-500">With Balance</p>
              <p className="font-medium text-yellow-600">
                {summary.total_businesses - (summary.fully_paid_count || 0)}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}