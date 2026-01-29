import React, { useState, useEffect } from "react";
import { 
  Search, Filter, Eye, Download, RefreshCw, AlertCircle, 
  Calendar, FileText, Home, MapPin, User, Hash, DollarSign,
  Clock, TrendingUp, AlertTriangle, Percent, CreditCard,
  FileWarning, Building, Landmark, CheckCircle, Ban, Archive
} from "lucide-react";

export default function RPTDelinquent() {
  const [delinquents, setDelinquents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [quarterFilter, setQuarterFilter] = useState("all");
  const [yearFilter, setYearFilter] = useState(new Date().getFullYear().toString());

  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

  const fetchDelinquentTaxes = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(`${API_BASE}/RPT/RPTDelinquent/get_delinquent_taxes.php`, {
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
      console.log("API Response:", data); // Debug log
      
      let delinquentsData = [];
      
      if (data.success && Array.isArray(data.data)) {
        delinquentsData = data.data;
      } else if (data.status === "success" && Array.isArray(data.delinquents)) {
        delinquentsData = data.delinquents;
      } else if (Array.isArray(data)) {
        delinquentsData = data;
      } else {
        throw new Error("Unexpected response format");
      }
      
      setDelinquents(delinquentsData);
      
    } catch (err) {
      console.error("Fetch error:", err);
      setError(`Failed to load delinquent taxes: ${err.message}`);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDelinquentTaxes();
  }, []);

  // Generate years for filter (current year - 5 years back)
  const currentYear = new Date().getFullYear();
  const years = Array.from({ length: 6 }, (_, i) => (currentYear - i).toString());

  // Get unique quarters from data
  const uniqueQuarters = ["Q1", "Q2", "Q3", "Q4"];

  // Filter delinquents
  const filteredDelinquents = delinquents.filter(delinquent => {
    const matchesSearch = 
      (delinquent.owner_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (delinquent.reference_number?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (delinquent.tdn?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (delinquent.lot_location?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (delinquent.first_name && delinquent.last_name ? 
        `${delinquent.first_name} ${delinquent.last_name}`.toLowerCase().includes(searchTerm.toLowerCase()) : false);
    
    const matchesStatus = statusFilter === "all" || delinquent.payment_status === statusFilter;
    const matchesQuarter = quarterFilter === "all" || delinquent.quarter === quarterFilter;
    const matchesYear = yearFilter === "all" || delinquent.year?.toString() === yearFilter;
    
    return matchesSearch && matchesStatus && matchesQuarter && matchesYear;
  });

  // Calculate statistics
  const calculateStats = () => {
    const stats = {
      totalAmountDue: 0,
      totalPenalties: 0,
      totalProperties: filteredDelinquents.length,
      byQuarter: { Q1: 0, Q2: 0, Q3: 0, Q4: 0 },
      byStatus: { pending: 0, overdue: 0 }
    };

    filteredDelinquents.forEach(d => {
      const amount = parseFloat(d.total_quarterly_tax || d.total_amount || 0);
      const penalty = parseFloat(d.penalty_amount || 0);
      
      stats.totalAmountDue += amount;
      stats.totalPenalties += penalty;
      
      if (d.quarter) stats.byQuarter[d.quarter] = (stats.byQuarter[d.quarter] || 0) + 1;
      
      if (d.payment_status === 'pending') {
        stats.byStatus.pending++;
      } else if (d.payment_status === 'overdue') {
        stats.byStatus.overdue++;
      }
    });

    return stats;
  };

  const stats = calculateStats();

  const getStatusInfo = (status, dueDate) => {
    const now = new Date();
    const due = new Date(dueDate);
    const daysLate = Math.max(0, Math.floor((now - due) / (1000 * 60 * 60 * 24)));
    
    if (status === 'overdue' || daysLate > 0) {
      const colorClass = daysLate > 90 ? "text-red-800" : 
                         daysLate > 60 ? "text-orange-800" : "text-yellow-800";
      const bgClass = daysLate > 90 ? "bg-red-50" : 
                      daysLate > 60 ? "bg-orange-50" : "bg-yellow-50";
      
      return {
        label: `Overdue (${daysLate} days)`,
        color: colorClass,
        bgColor: bgClass,
        borderColor: "border-red-200",
        icon: AlertTriangle,
        daysLate
      };
    }
    
    const statusMap = {
      pending: { 
        label: "Pending Payment", 
        color: "text-blue-800",
        bgColor: "bg-blue-50",
        borderColor: "border-blue-200",
        icon: Clock
      },
      paid: { 
        label: "Paid", 
        color: "text-green-800",
        bgColor: "bg-green-50",
        borderColor: "border-green-200",
        icon: CheckCircle
      }
    };
    
    return statusMap[status] || { 
      label: status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()), 
      color: "text-gray-800",
      bgColor: "bg-gray-50",
      borderColor: "border-gray-200",
      icon: FileWarning
    };
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

  const calculatePenalty = (amount, dueDate, penaltyRate = 0.02) => {
    if (!dueDate) return 0;
    
    const now = new Date();
    const due = new Date(dueDate);
    const daysLate = Math.max(0, Math.floor((now - due) / (1000 * 60 * 60 * 24)));
    
    if (daysLate <= 0) return 0;
    
    // Calculate monthly penalty (2% per month or fraction thereof)
    const monthsLate = Math.ceil(daysLate / 30);
    return parseFloat(amount) * penaltyRate * monthsLate;
  };

  const handleExportReport = () => {
    const csvContent = [
      ['Reference No', 'TDN', 'Owner Name', 'Property Location', 'Quarter', 'Year', 'Amount Due', 'Penalty', 'Total Due', 'Status', 'Due Date'],
      ...filteredDelinquents.map(d => [
        d.reference_number || 'N/A',
        d.tdn || 'N/A',
        d.owner_name || `${d.first_name} ${d.last_name}` || 'N/A',
        d.lot_location || 'N/A',
        d.quarter || 'N/A',
        d.year || 'N/A',
        d.total_quarterly_tax || d.total_amount || '0',
        d.penalty_amount || calculatePenalty(d.total_quarterly_tax, d.due_date) || '0',
        (parseFloat(d.total_quarterly_tax || 0) + parseFloat(d.penalty_amount || 0)).toFixed(2),
        d.payment_status || 'pending',
        formatDate(d.due_date)
      ])
    ].map(row => row.join(',')).join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `delinquent_taxes_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-white">
        <div className="flex items-center justify-center p-4 h-screen">
          <div className="text-center">
            <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-gray-800"></div>
            <p className="mt-4 font-medium text-gray-600">Loading delinquent taxes...</p>
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
                onClick={fetchDelinquentTaxes}
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
      {/* Header */}
      <div className="border-b border-gray-200 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="text-2xl font-bold text-gray-900 mb-1">
                Delinquent Property Taxes
              </h1>
              <p className="text-sm text-gray-600">
                Track and manage overdue property tax payments
              </p>
            </div>
            
            <div className="flex flex-wrap gap-3 items-center">
              <button
                onClick={handleExportReport}
                className="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700"
              >
                <Download className="w-4 h-4" />
                Export Report
              </button>
              <button
                onClick={fetchDelinquentTaxes}
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
        {/* Stats Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Total Delinquent Properties */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Delinquent Properties</p>
                <p className="text-2xl font-bold text-red-600 mt-1">{stats.totalProperties}</p>
              </div>
              <div className="p-3 bg-red-50 rounded-lg">
                <FileWarning className="w-5 h-5 text-red-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              {stats.byStatus.overdue} overdue, {stats.byStatus.pending} pending
            </div>
          </div>

          {/* Total Amount Due */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Amount Due</p>
                <p className="text-2xl font-bold text-gray-900 mt-1">{formatCurrency(stats.totalAmountDue)}</p>
              </div>
              <div className="p-3 bg-gray-100 rounded-lg">
                <DollarSign className="w-5 h-5 text-gray-700" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Excluding penalties and interest
            </div>
          </div>

          {/* Total Penalties */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Accrued Penalties</p>
                <p className="text-2xl font-bold text-orange-600 mt-1">{formatCurrency(stats.totalPenalties)}</p>
              </div>
              <div className="p-3 bg-orange-50 rounded-lg">
                <TrendingUp className="w-5 h-5 text-orange-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              2% monthly penalty rate
            </div>
          </div>

          {/* Distribution */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Quarter Distribution</p>
                <div className="flex gap-2 mt-2">
                  {Object.entries(stats.byQuarter).map(([quarter, count]) => (
                    count > 0 && (
                      <div key={quarter} className="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded">
                        {quarter}: {count}
                      </div>
                    )
                  ))}
                </div>
              </div>
              <div className="p-3 bg-blue-50 rounded-lg">
                <Calendar className="w-5 h-5 text-blue-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              By tax quarter
            </div>
          </div>
        </div>

        {/* Filters Section */}
        <div className="bg-white border border-gray-200 rounded-xl p-5">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {/* Search */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Search</label>
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <input
                  type="text"
                  placeholder="Search by owner, TDN, or location..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent transition duration-200"
                />
              </div>
            </div>

            {/* Status Filter */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Payment Status</label>
              <div className="relative">
                <Filter className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <select
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className="w-full pl-10 pr-10 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent appearance-none bg-white transition duration-200"
                >
                  <option value="all">All Status</option>
                  <option value="pending">Pending Payment</option>
                  <option value="overdue">Overdue</option>
                  <option value="paid">Paid</option>
                </select>
              </div>
            </div>

            {/* Quarter Filter */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Quarter</label>
              <div className="relative">
                <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <select
                  value={quarterFilter}
                  onChange={(e) => setQuarterFilter(e.target.value)}
                  className="w-full pl-10 pr-10 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent appearance-none bg-white transition duration-200"
                >
                  <option value="all">All Quarters</option>
                  {uniqueQuarters.map(quarter => (
                    <option key={quarter} value={quarter}>{quarter}</option>
                  ))}
                </select>
              </div>
            </div>

            {/* Year Filter */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Year</label>
              <div className="relative">
                <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <select
                  value={yearFilter}
                  onChange={(e) => setYearFilter(e.target.value)}
                  className="w-full pl-10 pr-10 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent appearance-none bg-white transition duration-200"
                >
                  <option value="all">All Years</option>
                  {years.map(year => (
                    <option key={year} value={year}>{year}</option>
                  ))}
                </select>
              </div>
            </div>
          </div>
          
          {/* Search Stats */}
          <div className="mt-4 flex items-center justify-between text-sm">
            <div className="text-gray-600">
              {filteredDelinquents.length} delinquent propert{filteredDelinquents.length === 1 ? 'y' : 'ies'} found
            </div>
            <div className="text-gray-700 font-medium">
              Total due: {formatCurrency(stats.totalAmountDue + stats.totalPenalties)}
            </div>
          </div>
        </div>

        {/* Delinquent Taxes Table */}
        <div className="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
          <div className="px-5 py-4 border-b border-gray-200 bg-gray-50">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wider">Delinquent Properties</h2>
                <p className="text-sm text-gray-600 mt-1">
                  {filteredDelinquents.length} propert{filteredDelinquents.length === 1 ? 'y' : 'ies'} with overdue taxes
                </p>
              </div>
            </div>
          </div>
          
          {filteredDelinquents.length === 0 ? (
            <div className="px-4 py-12 text-center">
              <div className="mx-auto w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                <CheckCircle className="w-6 h-6 text-green-400" />
              </div>
              <h3 className="text-sm font-medium text-gray-900 mb-1">
                {searchTerm || statusFilter !== "all" || quarterFilter !== "all" || yearFilter !== "all"
                  ? "No delinquent properties found" 
                  : "No delinquent properties at this time"}
              </h3>
              <p className="text-sm text-gray-500 max-w-xs mx-auto">
                {searchTerm || statusFilter !== "all" || quarterFilter !== "all" || yearFilter !== "all"
                  ? "Try adjusting your search filters"
                  : "All taxes are currently up to date"}
              </p>
              {(searchTerm || statusFilter !== "all" || quarterFilter !== "all" || yearFilter !== "all") && (
                <button
                  onClick={() => {
                    setSearchTerm("");
                    setStatusFilter("all");
                    setQuarterFilter("all");
                    setYearFilter("all");
                  }}
                  className="mt-4 text-sm font-medium text-gray-900 hover:text-black"
                >
                  Clear all filters
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
                        Property Details
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Owner
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Tax Period
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Amount Details
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status & Due Date
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {filteredDelinquents.map((delinquent) => {
                      const statusInfo = getStatusInfo(delinquent.payment_status, delinquent.due_date);
                      const StatusIcon = statusInfo.icon;
                      const ownerName = delinquent.owner_name || 
                        (delinquent.first_name && delinquent.last_name ? 
                          `${delinquent.first_name} ${delinquent.last_name}` : 
                          "N/A");
                      
                      const penaltyAmount = parseFloat(delinquent.penalty_amount || 0) > 0 
                        ? parseFloat(delinquent.penalty_amount) 
                        : calculatePenalty(delinquent.total_quarterly_tax, delinquent.due_date);
                      
                      const totalAmount = parseFloat(delinquent.total_quarterly_tax || 0) + penaltyAmount;
                      
                      return (
                        <tr key={delinquent.id} className="hover:bg-gray-50 transition-colors">
                          <td className="px-5 py-4">
                            <div className="flex items-center gap-3">
                              <div className="p-2 rounded-lg bg-gray-100">
                                <Building className="w-4 h-4 text-gray-600" />
                              </div>
                              <div>
                                <div className="font-mono text-xs font-semibold text-gray-900">
                                  TDN: {delinquent.tdn || "N/A"}
                                </div>
                                <div className="text-sm font-medium text-gray-900 mt-0.5">
                                  {delinquent.lot_location || "Location not specified"}
                                </div>
                                <div className="text-xs text-gray-500">
                                  Brgy. {delinquent.barangay || "N/A"}
                                </div>
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="font-medium text-sm text-gray-900">{ownerName}</div>
                            <div className="text-xs text-gray-500 truncate max-w-[180px]">
                              {delinquent.email || "No email provided"}
                            </div>
                            <div className="text-xs text-gray-500">{delinquent.phone || "No phone"}</div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="text-center">
                              <div className="font-bold text-lg text-gray-900">
                                {delinquent.quarter || "N/A"}
                              </div>
                              <div className="text-sm text-gray-600">
                                {delinquent.year || "N/A"}
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="space-y-1">
                              <div className="flex justify-between text-sm">
                                <span className="text-gray-600">Base Tax:</span>
                                <span className="font-medium text-gray-900">
                                  {formatCurrency(delinquent.total_quarterly_tax)}
                                </span>
                              </div>
                              <div className="flex justify-between text-sm">
                                <span className="text-red-600">Penalty:</span>
                                <span className="font-medium text-red-600">
                                  {formatCurrency(penaltyAmount)}
                                </span>
                              </div>
                              <div className="flex justify-between text-sm font-bold border-t pt-1">
                                <span>Total Due:</span>
                                <span className="text-gray-900">
                                  {formatCurrency(totalAmount)}
                                </span>
                              </div>
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
                                <div className="text-xs text-gray-600 mt-1">
                                  Due: {formatDate(delinquent.due_date)}
                                </div>
                                {statusInfo.daysLate > 0 && (
                                  <div className="text-xs text-red-600 mt-0.5">
                                    {statusInfo.daysLate} days late
                                  </div>
                                )}
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="flex flex-col gap-2">
                              <button
                                onClick={() => console.log("View details", delinquent.id)}
                                className="text-sm font-medium px-3 py-1.5 rounded-lg bg-gray-900 text-white hover:bg-black transition duration-200 flex items-center justify-center gap-1"
                              >
                                <Eye className="w-3 h-3" />
                                Details
                              </button>
                              <button
                                onClick={() => console.log("Send notice", delinquent.id)}
                                className="text-sm font-medium px-3 py-1.5 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 transition duration-200 flex items-center justify-center gap-1"
                              >
                                <AlertTriangle className="w-3 h-3" />
                                Send Notice
                              </button>
                            </div>
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
                    Showing <span className="font-semibold">{filteredDelinquents.length}</span> delinquent propert{filteredDelinquents.length === 1 ? 'y' : 'ies'}
                  </div>
                  <div className="text-sm text-gray-700">
                    <div className="font-medium">
                      Total Outstanding: {formatCurrency(stats.totalAmountDue + stats.totalPenalties)}
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
            <p className="font-medium">Local Government Unit - Delinquent Tax Management</p>
            <p className="mt-1">Real Property Tax Collection System v2.0</p>
          </div>
        </div>
      </div>
    </div>
  );
}