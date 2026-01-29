import React, { useState, useEffect } from "react";
import { 
  Search, Filter, Download, RefreshCw, AlertCircle, 
  Calendar, Building, MapPin, User, DollarSign,
  TrendingUp, AlertTriangle, CreditCard, Store,
  FileWarning, Briefcase, Phone, Archive,
  ChevronRight, Clock
} from "lucide-react";

export default function BusinessDelinquent() {
  const [delinquents, setDelinquents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [quarterFilter, setQuarterFilter] = useState("all");
  const [yearFilter, setYearFilter] = useState(new Date().getFullYear().toString());
  const [businessTypeFilter, setBusinessTypeFilter] = useState("all");

  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

  const fetchDelinquentTaxes = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(`${API_BASE}/Business/BusinessDelinquent/get_delinquent_taxes.php`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      });
      
      if (!response.ok) {
        throw new Error(`Server error: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.success && Array.isArray(data.data)) {
        setDelinquents(data.data);
      } else {
        // If no overdue taxes found, show empty state
        setDelinquents([]);
      }
      
    } catch (err) {
      console.error("Fetch error:", err);
      setError(`Failed to load delinquent business taxes: ${err.message}`);
      // For demo purposes, show empty state instead of mock data
      setDelinquents([]);
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

  // Get unique business types
  const businessTypes = [...new Set(delinquents.map(d => d.business_nature).filter(Boolean))];

  // Filter delinquents (already filtered to only overdue by API)
  const filteredDelinquents = delinquents.filter(delinquent => {
    const matchesSearch = 
      (delinquent.business_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (delinquent.owner_full_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (delinquent.trade_name?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (delinquent.business_barangay?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (delinquent.applicant_id?.toLowerCase() || '').includes(searchTerm.toLowerCase());
    
    const matchesQuarter = quarterFilter === "all" || delinquent.quarter === quarterFilter;
    const matchesYear = yearFilter === "all" || delinquent.year?.toString() === yearFilter;
    const matchesBusinessType = businessTypeFilter === "all" || delinquent.business_nature === businessTypeFilter;
    
    return matchesSearch && matchesQuarter && matchesYear && matchesBusinessType;
  });

  // Calculate statistics for OVERDUE taxes only
  const calculateStats = () => {
    const stats = {
      totalAmountDue: 0,
      totalPenalties: 0,
      totalBusinesses: filteredDelinquents.length,
      totalDaysLate: 0,
      byQuarter: { Q1: 0, Q2: 0, Q3: 0, Q4: 0 },
      byBusinessType: {}
    };

    filteredDelinquents.forEach(d => {
      const amount = parseFloat(d.total_quarterly_tax || d.total_tax || 0);
      const penalty = parseFloat(d.penalty_amount || 0);
      const daysLate = parseInt(d.days_late || 0);
      
      stats.totalAmountDue += amount;
      stats.totalPenalties += penalty;
      stats.totalDaysLate += daysLate;
      
      if (d.quarter) stats.byQuarter[d.quarter] = (stats.byQuarter[d.quarter] || 0) + 1;
      
      // Count by business type
      if (d.business_nature) {
        stats.byBusinessType[d.business_nature] = (stats.byBusinessType[d.business_nature] || 0) + 1;
      }
    });

    // Calculate average days late
    stats.averageDaysLate = stats.totalBusinesses > 0 ? 
      Math.round(stats.totalDaysLate / stats.totalBusinesses) : 0;

    return stats;
  };

  const stats = calculateStats();

  const getOverdueSeverity = (daysLate) => {
    if (daysLate > 90) {
      return {
        label: "Critical Overdue",
        color: "text-red-800",
        bgColor: "bg-red-50",
        borderColor: "border-red-200",
        daysLabel: `${daysLate}+ days`
      };
    } else if (daysLate > 60) {
      return {
        label: "Severe Overdue",
        color: "text-orange-800",
        bgColor: "bg-orange-50",
        borderColor: "border-orange-200",
        daysLabel: `${daysLate} days`
      };
    } else if (daysLate > 30) {
      return {
        label: "High Overdue",
        color: "text-yellow-800",
        bgColor: "bg-yellow-50",
        borderColor: "border-yellow-200",
        daysLabel: `${daysLate} days`
      };
    } else {
      return {
        label: "Overdue",
        color: "text-gray-800",
        bgColor: "bg-gray-50",
        borderColor: "border-gray-200",
        daysLabel: `${daysLate} days`
      };
    }
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

  const calculateTotalDue = (tax, penalty) => {
    return (parseFloat(tax || 0) + parseFloat(penalty || 0));
  };

  const handleExportReport = () => {
    const csvContent = [
      ['Business ID', 'Business Name', 'Owner Name', 'Business Type', 'Location', 'Quarter', 'Year', 'Amount Due', 'Penalty', 'Total Due', 'Days Late', 'Due Date'],
      ...filteredDelinquents.map(d => [
        d.applicant_id || d.business_id || 'N/A',
        d.business_name || 'N/A',
        d.owner_full_name || 'N/A',
        d.business_nature || 'N/A',
        `${d.business_barangay || ''}, ${d.business_city || ''}`,
        d.quarter || 'N/A',
        d.year || 'N/A',
        d.total_quarterly_tax || d.total_tax || '0',
        d.penalty_amount || '0',
        calculateTotalDue(d.total_quarterly_tax, d.penalty_amount).toFixed(2),
        d.days_late || '0',
        formatDate(d.due_date)
      ])
    ].map(row => row.join(',')).join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `overdue_business_taxes_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
  };

  const handleSendNotice = (id, businessName, ownerName, email, phone) => {
    const message = `Send delinquency notice to:\nBusiness: ${businessName}\nOwner: ${ownerName}\nEmail: ${email}\nPhone: ${phone}`;
    console.log(`Sending notice for business tax ID: ${id}`);
    alert(message);
  };

  const handleRecordPayment = (id, businessName, totalDue) => {
    const message = `Record payment for:\nBusiness: ${businessName}\nTotal Amount: ${formatCurrency(totalDue)}`;
    console.log(`Recording payment for business tax ID: ${id}`);
    alert(message);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-white">
        <div className="flex items-center justify-center p-4 h-screen">
          <div className="text-center">
            <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-gray-800"></div>
            <p className="mt-4 font-medium text-gray-600">Loading overdue business taxes...</p>
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
                Overdue Business Taxes
              </h1>
              <p className="text-sm text-gray-600">
                Manage business taxes with overdue payments
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
        {/* Stats Cards - ONLY OVERDUE */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Overdue Businesses */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Overdue Businesses</p>
                <p className="text-2xl font-bold text-red-600 mt-1">{stats.totalBusinesses}</p>
              </div>
              <div className="p-3 bg-red-50 rounded-lg">
                <AlertTriangle className="w-5 h-5 text-red-600" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Average {stats.averageDaysLate} days late
            </div>
          </div>

          {/* Total Amount Due */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Taxes Due</p>
                <p className="text-2xl font-bold text-gray-900 mt-1">{formatCurrency(stats.totalAmountDue)}</p>
              </div>
              <div className="p-3 bg-gray-100 rounded-lg">
                <DollarSign className="w-5 h-5 text-gray-700" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Base taxes only
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
              Additional charges
            </div>
          </div>

          {/* Total Outstanding */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Outstanding</p>
                <p className="text-2xl font-bold text-gray-900 mt-1">
                  {formatCurrency(stats.totalAmountDue + stats.totalPenalties)}
                </p>
              </div>
              <div className="p-3 bg-red-100 rounded-lg">
                <FileWarning className="w-5 h-5 text-red-700" />
              </div>
            </div>
            <div className="text-xs text-gray-500">
              Including penalties
            </div>
          </div>
        </div>

        {/* Filters Section */}
        <div className="bg-white border border-gray-200 rounded-xl p-5">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            {/* Search */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Search Overdue</label>
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <input
                  type="text"
                  placeholder="Search overdue businesses..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition duration-200"
                />
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
                  className="w-full pl-10 pr-10 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent appearance-none bg-white transition duration-200"
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
                  className="w-full pl-10 pr-10 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent appearance-none bg-white transition duration-200"
                >
                  <option value="all">All Years</option>
                  {years.map(year => (
                    <option key={year} value={year}>{year}</option>
                  ))}
                </select>
              </div>
            </div>

            {/* Business Type Filter */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Business Type</label>
              <div className="relative">
                <Building className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <select
                  value={businessTypeFilter}
                  onChange={(e) => setBusinessTypeFilter(e.target.value)}
                  className="w-full pl-10 pr-10 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent appearance-none bg-white transition duration-200"
                >
                  <option value="all">All Business Types</option>
                  {businessTypes.map(type => (
                    <option key={type} value={type}>{type}</option>
                  ))}
                </select>
              </div>
            </div>

            {/* Clear Filters Button */}
            <div className="flex items-end">
              <button
                onClick={() => {
                  setSearchTerm("");
                  setQuarterFilter("all");
                  setYearFilter("all");
                  setBusinessTypeFilter("all");
                }}
                className="w-full py-2.5 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition duration-200"
              >
                Clear Filters
              </button>
            </div>
          </div>
          
          {/* Search Stats */}
          <div className="mt-4 flex items-center justify-between text-sm">
            <div className="text-gray-600">
              Showing <span className="font-semibold">{filteredDelinquents.length}</span> overdue business{filteredDelinquents.length === 1 ? '' : 'es'}
            </div>
            <div className="text-gray-700 font-medium">
              Total outstanding: {formatCurrency(stats.totalAmountDue + stats.totalPenalties)}
            </div>
          </div>
        </div>

        {/* Overdue Taxes Table */}
        <div className="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
          <div className="px-5 py-4 border-b border-gray-200 bg-red-50">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-3">
                <AlertTriangle className="w-5 h-5 text-red-600" />
                <div>
                  <h2 className="text-sm font-semibold text-red-900 uppercase tracking-wider">Overdue Business Taxes</h2>
                  <p className="text-sm text-red-700 mt-1">
                    {filteredDelinquents.length} business{filteredDelinquents.length === 1 ? '' : 'es'} with overdue payments
                  </p>
                </div>
              </div>
              <div className="mt-2 sm:mt-0">
                <div className="inline-flex items-center gap-2 text-xs bg-red-100 text-red-800 px-3 py-1.5 rounded-lg">
                  <Clock className="w-3 h-3" />
                  <span>Only showing overdue payments</span>
                </div>
              </div>
            </div>
          </div>
          
          {filteredDelinquents.length === 0 ? (
            <div className="px-4 py-12 text-center">
              <div className="mx-auto w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mb-3">
                <AlertTriangle className="w-6 h-6 text-green-600" />
              </div>
              <h3 className="text-sm font-medium text-gray-900 mb-1">
                No Overdue Business Taxes
              </h3>
              <p className="text-sm text-gray-500 max-w-xs mx-auto">
                All business taxes are currently paid and up to date
              </p>
              {searchTerm || quarterFilter !== "all" || yearFilter !== "all" || businessTypeFilter !== "all" && (
                <button
                  onClick={() => {
                    setSearchTerm("");
                    setQuarterFilter("all");
                    setYearFilter("all");
                    setBusinessTypeFilter("all");
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
                        Business Details
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Owner & Contact
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Tax Period
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Amount Details
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Overdue Status
                      </th>
                      <th className="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {filteredDelinquents.map((delinquent) => {
                      const severity = getOverdueSeverity(delinquent.days_late || 0);
                      const totalDue = calculateTotalDue(delinquent.total_quarterly_tax, delinquent.penalty_amount);
                      
                      return (
                        <tr key={delinquent.id} className="hover:bg-red-50 transition-colors">
                          <td className="px-5 py-4">
                            <div className="flex items-center gap-3">
                              <div className="p-2 rounded-lg bg-red-100">
                                <Store className="w-4 h-4 text-red-600" />
                              </div>
                              <div>
                                <div className="font-medium text-sm text-gray-900">
                                  {delinquent.business_name || "Business Name N/A"}
                                </div>
                                <div className="text-xs text-gray-500 mt-0.5">
                                  ID: {delinquent.applicant_id || "N/A"}
                                </div>
                                <div className="text-xs text-gray-600 mt-1">
                                  <span className="font-medium">{delinquent.business_nature || "N/A"}</span>
                                </div>
                                <div className="text-xs text-gray-500">
                                  <MapPin className="w-3 h-3 inline mr-1" />
                                  {delinquent.business_barangay || "N/A"}
                                </div>
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="font-medium text-sm text-gray-900">
                              {delinquent.owner_full_name || "N/A"}
                            </div>
                            <div className="text-xs text-gray-500 mt-1">
                              <Building className="w-3 h-3 inline mr-1" />
                              {delinquent.owner_type || "Individual"}
                            </div>
                            <div className="text-xs text-gray-500 mt-2">
                              <Phone className="w-3 h-3 inline mr-1" />
                              {delinquent.contact_number || "No phone"}
                            </div>
                            <div className="text-xs text-gray-500 truncate max-w-[180px]">
                              <span className="font-medium">Email:</span> {delinquent.email_address || "No email"}
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="text-center">
                              <div className="font-bold text-lg text-gray-900">
                                {delinquent.quarter || "N/A"}
                              </div>
                              <div className="text-sm text-gray-600">
                                {delinquent.year || "N/A"}
                              </div>
                              <div className="text-xs text-gray-500 mt-2">
                                Due: {formatDate(delinquent.due_date)}
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
                                  {formatCurrency(delinquent.penalty_amount)}
                                </span>
                              </div>
                              <div className="flex justify-between text-sm font-bold border-t pt-1">
                                <span>Total Due:</span>
                                <span className="text-gray-900">
                                  {formatCurrency(totalDue)}
                                </span>
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="flex flex-col gap-2">
                              <span className={`text-xs font-medium px-3 py-1.5 rounded-full ${severity.bgColor} ${severity.color} border ${severity.borderColor}`}>
                                {severity.label}
                              </span>
                              <div className="text-sm text-gray-700">
                                {severity.daysLabel} late
                              </div>
                              <div className="text-xs text-gray-500">
                                Due: {formatDate(delinquent.due_date)}
                              </div>
                            </div>
                          </td>
                          
                          <td className="px-5 py-4">
                            <div className="flex flex-col gap-2">
                              <button
                                onClick={() => handleSendNotice(
                                  delinquent.id,
                                  delinquent.business_name,
                                  delinquent.owner_full_name,
                                  delinquent.email_address,
                                  delinquent.contact_number
                                )}
                                className="text-sm font-medium px-4 py-2 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 transition duration-200 flex items-center justify-center gap-2"
                              >
                                <AlertTriangle className="w-4 h-4" />
                                Send Notice
                              </button>
                              <button
                                onClick={() => handleRecordPayment(
                                  delinquent.id,
                                  delinquent.business_name,
                                  totalDue
                                )}
                                className="text-sm font-medium px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 transition duration-200 flex items-center justify-center gap-2"
                              >
                                <CreditCard className="w-4 h-4" />
                                Record Payment
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
              <div className="px-5 py-4 border-t border-gray-200 bg-red-50">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div className="text-sm text-gray-700">
                    Showing <span className="font-semibold">{filteredDelinquents.length}</span> overdue business{filteredDelinquents.length === 1 ? '' : 'es'}
                  </div>
                  <div className="text-sm text-gray-700">
                    <div className="font-medium">
                      Total outstanding: {formatCurrency(stats.totalAmountDue + stats.totalPenalties)}
                    </div>
                    <div className="text-xs text-gray-600 mt-1">
                      Average overdue: {stats.averageDaysLate} days
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
            <p className="font-medium">Local Government Unit - Overdue Business Tax Management</p>
            <p className="mt-1">Business Tax Collection System v2.0 | Showing overdue taxes only</p>
          </div>
        </div>
      </div>
    </div>
  );
}