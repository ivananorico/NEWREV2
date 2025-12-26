import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  Search,
  Filter,
  Download,
  Eye,
  CheckCircle,
  AlertCircle,
  Building,
  User,
  Calendar,
  FileText,
  DollarSign,
  TrendingUp,
  Clock,
  CreditCard,
  AlertTriangle
} from "lucide-react";

export default function BusinessStatus() {
  const [permits, setPermits] = useState([]);
  const [taxSummary, setTaxSummary] = useState({
    total_revenue: 0,
    total_pending: 0,
    total_overdue: 0,
    total_next_pending: 0,
    pending_business_count: 0,
    total_businesses: 0,
    collection_rate: 0
  });
  const [filteredPermits, setFilteredPermits] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState("");
  const [filter, setFilter] = useState("all");
  const [sortBy, setSortBy] = useState("date");
  const [paymentFilter, setPaymentFilter] = useState("all");
  const navigate = useNavigate();

  useEffect(() => {
    fetchPermits();
  }, []);

  useEffect(() => {
    filterAndSortPermits();
  }, [permits, searchTerm, filter, sortBy, paymentFilter]);

  const fetchPermits = async () => {
    try {
      const res = await fetch(
        "http://localhost/revenue2/backend/Business/BusinessStatus/get_permits.php",
        { credentials: "include" }
      );
      const data = await res.json();
      if (data.status === "success") {
        setPermits(data.permits || []);
        setTaxSummary(data.tax_summary || {
          total_revenue: 0,
          total_pending: 0,
          total_overdue: 0,
          total_next_pending: 0,
          pending_business_count: 0,
          total_businesses: 0,
          collection_rate: 0
        });
      }
    } catch (err) {
      console.error("Error fetching permits:", err);
    } finally {
      setLoading(false);
    }
  };

  const filterAndSortPermits = () => {
    let result = [...permits];

    // Apply search filter
    if (searchTerm) {
      const term = searchTerm.toLowerCase();
      result = result.filter(permit =>
        permit.business_name?.toLowerCase().includes(term) ||
        permit.owner_name?.toLowerCase().includes(term) ||
        permit.business_type?.toLowerCase().includes(term) ||
        permit.business_permit_id?.toLowerCase().includes(term)
      );
    }

    // Apply business type filter
    if (filter !== "all") {
      result = result.filter(permit => permit.business_type === filter);
    }

    // Apply payment status filter
    if (paymentFilter !== "all") {
      switch (paymentFilter) {
        case "fully_paid":
          result = result.filter(permit => permit.payment_status === 'fully_paid');
          break;
        case "pending":
          result = result.filter(permit => permit.payment_status === 'pending');
          break;
        case "overdue":
          result = result.filter(permit => permit.payment_status === 'overdue');
          break;
        default:
          break;
      }
    }

    // Apply sorting
    result.sort((a, b) => {
      switch (sortBy) {
        case "name":
          return a.business_name.localeCompare(b.business_name);
        case "date":
          return new Date(b.issue_date || b.created_at) - new Date(a.issue_date || a.created_at);
        case "type":
          return a.business_type.localeCompare(b.business_type);
        case "revenue2":
          return (b.total_paid_tax || 0) - (a.total_paid_tax || 0);
        case "pending":
          return (b.total_pending_tax || 0) - (a.total_pending_tax || 0);
        case "overdue":
          return (b.overdue_tax_amount || 0) - (a.overdue_tax_amount || 0);
        default:
          return 0;
      }
    });

    setFilteredPermits(result);
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2
    }).format(amount || 0);
  };

  const getPaymentStatus = (permit) => {
    switch (permit.payment_status) {
      case 'fully_paid':
        return {
          text: "Fully Paid",
          color: "bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300",
          icon: CheckCircle,
          description: "All taxes paid"
        };
      case 'overdue':
        return {
          text: "Overdue",
          color: "bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300",
          icon: AlertTriangle,
          description: "Payment overdue"
        };
      case 'pending':
        return {
          text: "Pending",
          color: "bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300",
          icon: Clock,
          description: "Payment pending"
        };
      default:
        return {
          text: "Unknown",
          color: "bg-gray-100 dark:bg-gray-900/30 text-gray-800 dark:text-gray-300",
          icon: AlertCircle,
          description: "Payment status unknown"
        };
    }
  };

  const getBusinessTypes = () => {
    const types = [...new Set(permits.map(p => p.business_type).filter(Boolean))];
    return types.sort();
  };

  const exportToCSV = () => {
    const headers = [
      "Business Name", 
      "Owner", 
      "Type", 
      "Permit ID", 
      "Total Tax", 
      "Paid Tax", 
      "Pending Tax", 
      "Overdue Tax", 
      "Next Payment Amount", 
      "Next Due Date", 
      "Payment Status",
      "Tax Paid %"
    ];
    const csvData = [
      headers.join(","),
      ...filteredPermits.map(p => [
        `"${p.business_name}"`,
        `"${p.owner_name}"`,
        `"${p.business_type}"`,
        `"${p.business_permit_id || 'N/A'}"`,
        p.total_tax,
        p.total_paid_tax,
        p.total_pending_tax,
        p.overdue_tax_amount,
        p.next_pending_tax_amount,
        `"${p.next_pending_due_date || 'N/A'}"`,
        `"${getPaymentStatus(p).text}"`,
        p.tax_paid_percentage || 0
      ].join(","))
    ].join("\n");

    const blob = new Blob([csvData], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `business-tax-report-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600 dark:text-gray-300">Loading tax information...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto p-4 lg:p-6">
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
          <div>
            <h1 className="text-2xl lg:text-3xl font-bold text-gray-800 dark:text-white">
              Business Tax Management
            </h1>
            <p className="text-gray-600 dark:text-gray-300 mt-2">
              Track tax payments and pending balances for all approved businesses
            </p>
          </div>
          
          <div className="flex items-center gap-3">
            <button
              onClick={exportToCSV}
              className="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors shadow-sm hover:shadow"
            >
              <Download size={18} />
              Export Report
            </button>
          </div>
        </div>

        {/* Tax Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div className="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-5 text-white shadow-lg">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm opacity-90">Total revenue2</p>
                <p className="text-xl font-bold mt-1">{formatCurrency(taxSummary.total_revenue)}</p>
                <p className="text-xs opacity-80 mt-1">
                  {taxSummary.total_businesses} Businesses
                </p>
              </div>
              <DollarSign className="w-10 h-10 opacity-80" />
            </div>
          </div>
          
          <div className="bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-xl p-5 text-white shadow-lg">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm opacity-90">Collection Rate</p>
                <p className="text-2xl font-bold mt-1">{taxSummary.collection_rate}%</p>
                <p className="text-xs opacity-80 mt-1">
                  {formatCurrency(taxSummary.total_pending)} pending
                </p>
              </div>
              <TrendingUp className="w-10 h-10 opacity-80" />
            </div>
          </div>
          
          <div className="bg-gradient-to-r from-amber-500 to-amber-600 rounded-xl p-5 text-white shadow-lg">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm opacity-90">Pending Taxes</p>
                <p className="text-xl font-bold mt-1">{formatCurrency(taxSummary.total_pending)}</p>
                <p className="text-xs opacity-80 mt-1">
                  {taxSummary.pending_business_count} Businesses
                </p>
              </div>
              <Clock className="w-10 h-10 opacity-80" />
            </div>
          </div>
          
          <div className="bg-gradient-to-r from-red-500 to-red-600 rounded-xl p-5 text-white shadow-lg">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm opacity-90">Overdue Taxes</p>
                <p className="text-xl font-bold mt-1">{formatCurrency(taxSummary.total_overdue)}</p>
                <p className="text-xs opacity-80 mt-1">
                  {formatCurrency(taxSummary.total_next_pending)} upcoming
                </p>
              </div>
              <AlertTriangle className="w-10 h-10 opacity-80" />
            </div>
          </div>
        </div>

        {/* Filters and Search */}
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 p-5 mb-6">
          <div className="flex flex-col lg:flex-row gap-4">
            {/* Search */}
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={20} />
                <input
                  type="text"
                  placeholder="Search business, owner, or permit ID..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
            </div>

            {/* Filters */}
            <div className="flex flex-wrap gap-3">
              <div className="relative">
                <Filter className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={18} />
                <select
                  value={filter}
                  onChange={(e) => setFilter(e.target.value)}
                  className="pl-10 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none"
                >
                  <option value="all">All Business Types</option>
                  {getBusinessTypes().map(type => (
                    <option key={type} value={type}>{type}</option>
                  ))}
                </select>
              </div>

              <div className="relative">
                <CreditCard className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={18} />
                <select
                  value={paymentFilter}
                  onChange={(e) => setPaymentFilter(e.target.value)}
                  className="pl-10 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none"
                >
                  <option value="all">All Payments</option>
                  <option value="fully_paid">Fully Paid</option>
                  <option value="pending">Pending Payment</option>
                  <option value="overdue">Overdue Payment</option>
                </select>
              </div>

              <div className="relative">
                <FileText className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={18} />
                <select
                  value={sortBy}
                  onChange={(e) => setSortBy(e.target.value)}
                  className="pl-10 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none"
                >
                  <option value="date">Sort by Date</option>
                  <option value="name">Sort by Name</option>
                  <option value="type">Sort by Type</option>
                  <option value="revenue2">Sort by revenue2</option>
                  <option value="pending">Sort by Pending</option>
                  <option value="overdue">Sort by Overdue</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        {/* Results Info */}
        <div className="flex justify-between items-center mb-4">
          <p className="text-gray-600 dark:text-gray-300">
            Showing <span className="font-semibold">{filteredPermits.length}</span> of{" "}
            <span className="font-semibold">{permits.length}</span> approved businesses
          </p>
          {searchTerm && (
            <button
              onClick={() => setSearchTerm("")}
              className="text-sm text-blue-600 dark:text-blue-400 hover:underline"
            >
              Clear search
            </button>
          )}
        </div>

        {/* Business Tax Table */}
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-900/20">
                <tr>
                  <th className="p-4 text-left text-gray-700 dark:text-gray-300 font-semibold">Business Information</th>
                  <th className="p-4 text-left text-gray-700 dark:text-gray-300 font-semibold">Tax Summary</th>
                  <th className="p-4 text-left text-gray-700 dark:text-gray-300 font-semibold">Payment Status</th>
                  <th className="p-4 text-left text-gray-700 dark:text-gray-300 font-semibold">Next Payment</th>
                  <th className="p-4 text-left text-gray-700 dark:text-gray-300 font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                {filteredPermits.map((permit) => {
                  const paymentStatus = getPaymentStatus(permit);
                  const StatusIcon = paymentStatus.icon;
                  
                  return (
                    <tr 
                      key={permit.id} 
                      className="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors"
                    >
                      <td className="p-4">
                        <div>
                          <p className="font-medium text-gray-800 dark:text-white">{permit.business_name}</p>
                          <div className="mt-2 space-y-1 text-sm">
                            <div className="flex justify-between">
                              <span className="text-gray-500 dark:text-gray-400">Owner:</span>
                              <span className="text-gray-800 dark:text-white">{permit.owner_name}</span>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-gray-500 dark:text-gray-400">Type:</span>
                              <span className="text-gray-800 dark:text-white">{permit.business_type}</span>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-gray-500 dark:text-gray-400">Permit ID:</span>
                              <span className="text-gray-800 dark:text-white">{permit.business_permit_id}</span>
                            </div>
                          </div>
                        </div>
                      </td>
                      
                      <td className="p-4">
                        <div className="space-y-3">
                          <div>
                            <div className="flex justify-between mb-1">
                              <span className="text-sm text-gray-600 dark:text-gray-400">Total Tax:</span>
                              <span className="font-medium text-gray-800 dark:text-white">
                                {formatCurrency(permit.total_tax)}
                              </span>
                            </div>
                            <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                              <div 
                                className="bg-green-500 h-2 rounded-full" 
                                style={{ width: `${permit.tax_paid_percentage || 0}%` }}
                              ></div>
                            </div>
                            <div className="text-right text-xs text-gray-500 mt-1">
                              {permit.tax_paid_percentage || 0}% paid
                            </div>
                          </div>
                          
                          <div className="grid grid-cols-2 gap-2">
                            <div className="bg-green-50 dark:bg-green-900/20 p-2 rounded">
                              <p className="text-xs text-green-700 dark:text-green-300">Paid</p>
                              <p className="font-medium text-green-800 dark:text-green-200">
                                {formatCurrency(permit.total_paid_tax)}
                              </p>
                            </div>
                            <div className="bg-amber-50 dark:bg-amber-900/20 p-2 rounded">
                              <p className="text-xs text-amber-700 dark:text-amber-300">Pending</p>
                              <p className="font-medium text-amber-800 dark:text-amber-200">
                                {formatCurrency(permit.total_pending_tax)}
                              </p>
                            </div>
                          </div>
                        </div>
                      </td>
                      
                      <td className="p-4">
                        <div className="flex flex-col gap-2">
                          <span className={`inline-flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-medium ${paymentStatus.color}`}>
                            <StatusIcon size={14} />
                            {paymentStatus.text}
                          </span>
                          <p className="text-xs text-gray-500 dark:text-gray-400">
                            {paymentStatus.description}
                          </p>
                          {permit.pending_quarters_count > 0 && (
                            <p className="text-xs text-gray-600 dark:text-gray-300">
                              {permit.pending_quarters_count} quarter(s) pending
                            </p>
                          )}
                          {permit.overdue_tax_amount > 0 && (
                            <div className="mt-1">
                              <p className="text-xs text-red-600 dark:text-red-400 font-medium">
                                Overdue: {formatCurrency(permit.overdue_tax_amount)}
                              </p>
                            </div>
                          )}
                        </div>
                      </td>
                      
                      <td className="p-4">
                        {permit.next_pending_tax_amount > 0 ? (
                          <div>
                            <div className="mb-3">
                              <p className="text-sm font-medium text-gray-800 dark:text-white mb-1">
                                {formatCurrency(permit.next_pending_tax_amount)}
                              </p>
                              <p className="text-xs text-gray-500 dark:text-gray-400">
                                Due: {new Date(permit.next_pending_due_date).toLocaleDateString()}
                              </p>
                            </div>
                            {permit.pending_quarterly_taxes?.length > 0 && (
                              <div className="border-t pt-2">
                                <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">
                                  Upcoming quarters:
                                </p>
                                <div className="space-y-1">
                                  {permit.pending_quarterly_taxes.slice(0, 2).map((q, idx) => (
                                    <div key={idx} className="flex justify-between text-xs">
                                      <span className="text-gray-600 dark:text-gray-300">
                                        {q.quarter} {q.year}
                                      </span>
                                      <span className={`font-medium ${
                                        q.payment_status === 'overdue' 
                                          ? 'text-red-600 dark:text-red-400' 
                                          : 'text-gray-800 dark:text-white'
                                      }`}>
                                        {formatCurrency(q.total_quarterly_tax)}
                                      </span>
                                    </div>
                                  ))}
                                  {permit.pending_quarterly_taxes.length > 2 && (
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                      +{permit.pending_quarterly_taxes.length - 2} more
                                    </p>
                                  )}
                                </div>
                              </div>
                            )}
                          </div>
                        ) : (
                          <div className="text-center py-2">
                            <CheckCircle className="w-8 h-8 text-green-400 mx-auto mb-2" />
                            <p className="text-sm text-gray-500 dark:text-gray-400">All taxes paid</p>
                          </div>
                        )}
                      </td>
                      
                      <td className="p-4">
                        <div className="flex flex-col gap-2">
                          <button
                            onClick={() => navigate(`/business/businessstatusinfo/${permit.id}`)}
                            className="flex items-center justify-center gap-2 px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-lg transition-all shadow-sm hover:shadow-md"
                          >
                            <Eye size={16} />
                            View Details
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {filteredPermits.length === 0 && (
            <div className="text-center py-10">
              <AlertCircle className="w-12 h-12 text-gray-400 mx-auto mb-3" />
              <p className="text-gray-500 dark:text-gray-400">No businesses match your search criteria</p>
            </div>
          )}
        </div>

        {/* Summary Footer */}
        <div className="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
          <p>Data as of {new Date().toLocaleDateString()} • All amounts in Philippine Peso (₱)</p>
          <p className="mt-1">
            {taxSummary.collection_rate}% collection rate • {taxSummary.pending_business_count} businesses with pending payments
          </p>
        </div>
      </div>
    </div>
  );
}