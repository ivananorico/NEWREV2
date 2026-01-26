import { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import {
  ArrowLeft,
  Phone,
  Mail,
  CheckCircle,
  AlertCircle,
  Clock,
  Printer,
  UserCircle,
  Building2,
  Tag,
  AlertTriangle,
  PieChart,
  Receipt,
  Calendar,
  MapPin,
  DollarSign,
  Percent,
  FileText,
  ShieldCheck,
  User,
} from "lucide-react";

export default function BusinessStatusInfo() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [permit, setPermit] = useState(null);
  const [quarterlyTaxes, setQuarterlyTaxes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // API Configuration
  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

  useEffect(() => {
    console.log("Current ID from URL params:", id);
    
    if (!id || id === "undefined") {
      console.error("Invalid ID received:", id);
      setError("Invalid business permit ID. Please go back and try again.");
      setLoading(false);
      return;
    }
    
    fetchPermitDetails();
  }, [id]);

  const fetchPermitDetails = async () => {
    try {
      setLoading(true);
      setError(null);
      
      console.log("Fetching permit details for ID:", id);
      
      const res = await fetch(
        `${API_BASE}/Business/BusinessStatus/get_permit_by_id.php?id=${id}`,
        {
          headers: {
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
          }
        }
      );
      
      const data = await res.json();
      console.log("API Response:", data);
      
      if (data.status === "success") {
        setPermit(data.data.permit);
        setQuarterlyTaxes(data.data.quarterlyTaxes || []);
      } else {
        setError(data.message || "Failed to load business permit details");
      }
    } catch (err) {
      setError("Network error: " + err.message);
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (amount) => {
    if (!amount || isNaN(amount)) return '₱0.00';
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2
    }).format(amount);
  };

  const formatDate = (dateString) => {
    if (!dateString || dateString === '0000-00-00' || dateString === '0000-00-00 00:00:00') {
      return "Not set";
    }
    try {
      const date = new Date(dateString);
      if (isNaN(date.getTime())) return dateString;
      return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    } catch (e) {
      return dateString;
    }
  };

  const getPaymentStatus = (status) => {
    switch(status) {
      case 'paid':
        return { text: "Paid", color: "text-green-700", bg: "bg-green-50 border border-green-200", icon: CheckCircle };
      case 'overdue':
        return { text: "Overdue", color: "text-red-700", bg: "bg-red-50 border border-red-200", icon: AlertCircle };
      default:
        return { text: "Pending", color: "text-yellow-700", bg: "bg-yellow-50 border border-yellow-200", icon: Clock };
    }
  };

  const getOwnerTypeText = (type) => {
    switch(type?.toLowerCase()) {
      case 'corporation': return 'Corporation';
      case 'individual': return 'Individual';
      case 'partnership': return 'Partnership';
      default: return type || 'Not specified';
    }
  };

  const handlePrint = () => {
    window.print();
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading Business Details...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 py-6">
        <div className="max-w-7xl mx-auto px-4">
          <div className="bg-red-50 border border-red-200 rounded-xl p-8 max-w-2xl mx-auto">
            <div className="flex items-center mb-6">
              <AlertCircle className="h-10 w-10 text-red-600 mr-4" />
              <div>
                <h2 className="text-xl font-bold text-red-900">Error</h2>
                <p className="text-red-700">{error}</p>
              </div>
            </div>
            <button
              onClick={() => navigate('/business/businessstatus')}
              className="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700"
            >
              ← Back to Business List
            </button>
          </div>
        </div>
      </div>
    );
  }

  if (!permit) {
    return (
      <div className="min-h-screen bg-gray-50 py-6">
        <div className="max-w-7xl mx-auto px-4">
          <div className="bg-red-50 border border-red-200 rounded-xl p-8 max-w-2xl mx-auto">
            <div className="flex items-center mb-6">
              <AlertCircle className="h-10 w-10 text-red-600 mr-4" />
              <div>
                <h2 className="text-xl font-bold text-red-900">Business Permit Not Found</h2>
                <p className="text-red-700">The requested business permit could not be found.</p>
              </div>
            </div>
            <button
              onClick={() => navigate('/business/businessstatus')}
              className="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700"
            >
              ← Back to Business List
            </button>
          </div>
        </div>
      </div>
    );
  }

  const paidTaxes = quarterlyTaxes.filter(tax => tax.payment_status === 'paid');
  const totalPaid = paidTaxes.reduce((sum, tax) => sum + (parseFloat(tax.total_quarterly_tax) || 0), 0);
  const collectionRate = permit.total_tax > 0 ? Math.round((totalPaid / permit.total_tax) * 100) : 0;
  const totalPending = parseFloat(permit.total_pending_tax) || 0;
  const totalPenalty = parseFloat(permit.total_penalty) || 0;

  // Calculate stats for summary
  const paidQuarters = quarterlyTaxes.filter(t => t.payment_status === 'paid').length;
  const pendingQuarters = quarterlyTaxes.filter(t => t.payment_status === 'pending').length;
  const overdueQuarters = quarterlyTaxes.filter(t => t.payment_status === 'overdue').length;
  const totalQuarters = quarterlyTaxes.length;

  return (
    <div className="min-h-screen bg-gray-50 py-6">
      <div className="max-w-7xl mx-auto px-4">
        
        {/* Header Card with Business Permit Number */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-6">
          <div className="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-4">
            <div className="flex-1">
              <button 
                onClick={() => navigate('/business/businessstatus')} 
                className="text-gray-600 hover:text-blue-600 mb-4 flex items-center"
              >
                <ArrowLeft className="w-4 h-4 mr-1" />
                Back to List
              </button>
              <div className="flex items-center gap-3">
                <div className="p-3 bg-blue-100 rounded-lg">
                  <FileText className="w-6 h-6 text-blue-600" />
                </div>
                <div>
                  <h1 className="text-2xl font-bold text-gray-900">Business Permit Details</h1>
                  <div className="flex flex-wrap items-center gap-4 mt-2">
                    <div className="flex items-center gap-2">
                      <div className="font-mono text-lg font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-lg">
                        {permit.business_permit_id || permit.applicant_id}
                      </div>
                      <span className="text-sm text-gray-600">Permit Number</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <ShieldCheck className="w-4 h-4 text-gray-400" />
                      <span className="text-sm text-gray-600">ID: {permit.id}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div className="flex flex-col items-start md:items-end gap-3">
              <span className={`inline-flex items-center px-4 py-2 rounded-full font-semibold ${
                permit.business_status === 'ACTIVE' ? 'bg-green-100 text-green-800 border border-green-200' :
                permit.business_status === 'APPROVED' ? 'bg-blue-100 text-blue-800 border border-blue-200' :
                permit.business_status === 'PENDING' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
                permit.business_status === 'RENEWED' ? 'bg-purple-100 text-purple-800 border border-purple-200' :
                permit.business_status === 'EXPIRED' ? 'bg-red-100 text-red-800 border border-red-200' :
                'bg-gray-100 text-gray-800 border border-gray-200'
              }`}>
                {permit.business_status || 'N/A'}
              </span>
              <div className="text-sm text-gray-600">
                <div className="flex items-center gap-2">
                  <Calendar className="w-4 h-4" />
                  {permit.issue_date ? `Issued: ${formatDate(permit.issue_date)}` : 'No issue date'}
                </div>
              </div>
            </div>
          </div>

          {/* Business Name Highlight */}
          <div className="mt-4 pt-4 border-t border-gray-200">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <Building2 className="w-6 h-6 text-gray-600" />
                <div>
                  <h2 className="text-xl font-bold text-gray-900">{permit.business_name}</h2>
                  {permit.trade_name && (
                    <p className="text-gray-600">Trade Name: {permit.trade_name}</p>
                  )}
                  <p className="text-sm text-gray-600 mt-1">{permit.owner_name || permit.owner_full_name || 'Owner not specified'}</p>
                </div>
              </div>
              <div className="text-right">
                <div className="text-lg font-bold text-green-600">{formatCurrency(permit.total_tax)}</div>
                <div className="text-sm text-gray-600">Annual Tax</div>
              </div>
            </div>
          </div>
        </div>

        {/* Main Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          {/* Left Column - Main Information */}
          <div className="lg:col-span-2 space-y-6">
            
            {/* Owner Information Card */}
            <div className="bg-white rounded-xl shadow p-6">
              <h2 className="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <UserCircle className="w-5 h-5 mr-2 text-blue-600" />
                Owner Information
              </h2>
              
              <div className="space-y-4">
                {/* Basic Info */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-1">
                    <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Owner Full Name</label>
                    <div className="text-sm font-bold text-gray-900 bg-gray-50 p-2 rounded border flex items-center">
                      <User className="w-4 h-4 text-gray-400 mr-2" />
                      {permit.owner_name || permit.owner_full_name || 'Not specified'}
                    </div>
                  </div>
                  
                  <div className="space-y-1">
                    <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Owner Type</label>
                    <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">
                      {getOwnerTypeText(permit.owner_type)}
                    </div>
                  </div>
                </div>

                {/* Contact Information */}
                <div className="border-t border-gray-200 pt-4">
                  <h3 className="text-sm font-medium text-gray-700 mb-3">Contact Information</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {permit.contact_number && (
                      <div className="space-y-1">
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Number</label>
                        <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border flex items-center">
                          <Phone className="w-4 h-4 text-gray-400 mr-2" />
                          {permit.contact_number}
                        </div>
                      </div>
                    )}
                    
                    {permit.email_address && (
                      <div className="space-y-1">
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Email Address</label>
                        <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border flex items-center">
                          <Mail className="w-4 h-4 text-gray-400 mr-2" />
                          {permit.email_address}
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </div>

            {/* Business Information Card */}
            <div className="bg-white rounded-xl shadow p-6">
              <h2 className="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <Building2 className="w-5 h-5 mr-2 text-green-600" />
                Business Information
              </h2>
              
              <div className="space-y-4">
                {/* Basic Business Info */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-1">
                    <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Business Name</label>
                    <div className="text-sm font-bold text-gray-900 bg-gray-50 p-2 rounded border">{permit.business_name}</div>
                  </div>
                  
                  <div className="space-y-1">
                    <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Business Type & Tax</label>
                    <div className="flex flex-wrap gap-2">
                      <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border flex items-center">
                        <Tag className="w-4 h-4 text-gray-400 mr-2" />
                        {permit.business_type || permit.business_nature || 'N/A'}
                      </div>
                      <div className={`text-sm font-medium p-2 rounded border flex items-center ${
                        permit.tax_calculation_type === 'capital_investment' 
                          ? 'bg-purple-50 text-purple-700 border-purple-200' 
                          : 'bg-indigo-50 text-indigo-700 border-indigo-200'
                      }`}>
                        <DollarSign className="w-4 h-4 mr-2" />
                        {permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment' : 'Gross Sales'}
                      </div>
                    </div>
                  </div>
                </div>

                {/* Tax Information */}
                <div className="border-t border-gray-200 pt-4">
                  <h3 className="text-sm font-medium text-gray-700 mb-3">Tax Information</h3>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment' : 'Tax Base'}
                      </label>
                      <div className="text-sm font-bold text-gray-900 bg-gray-50 p-2 rounded border">
                        {formatCurrency(permit.capital_investment || permit.taxable_amount || 0)}
                      </div>
                    </div>
                    
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Tax Rate</label>
                      <div className="text-sm font-bold text-blue-600 bg-blue-50 p-2 rounded border border-blue-200 flex items-center">
                        <Percent className="w-4 h-4 mr-1" />
                        {permit.tax_rate || 0}%
                      </div>
                    </div>
                    
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Tax Amount</label>
                      <div className="text-sm font-bold text-green-600 bg-green-50 p-2 rounded border border-green-200">
                        {formatCurrency(permit.tax_amount || 0)}
                      </div>
                    </div>
                  </div>
                </div>

                {/* Business Address */}
                <div className="border-t border-gray-200 pt-4">
                  <h3 className="text-sm font-medium text-gray-700 mb-3">Business Address</h3>
                  <div className="space-y-2 p-3 bg-gray-50 rounded border">
                    <p className="text-sm text-gray-900 flex items-start gap-2">
                      <MapPin className="w-4 h-4 text-gray-400 mt-0.5" />
                      {permit.business_address || (
                        <>
                          Brgy. {permit.business_barangay || 'N/A'}, {permit.business_city || 'N/A'}
                          {permit.business_province && `, ${permit.business_province}`}
                          {permit.business_zipcode && ` ${permit.business_zipcode}`}
                        </>
                      )}
                    </p>
                    {permit.business_district && permit.business_district !== 'Unknown' && (
                      <p className="text-sm text-gray-900 ml-6">
                        District: {permit.business_district}
                      </p>
                    )}
                  </div>
                </div>

                {/* Dates */}
                <div className="border-t border-gray-200 pt-4">
                  <h3 className="text-sm font-medium text-gray-700 mb-3">Permit Dates</h3>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Issue Date</label>
                      <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border flex items-center">
                        <Calendar className="w-4 h-4 text-gray-400 mr-2" />
                        {formatDate(permit.issue_date) || 'Not issued'}
                      </div>
                    </div>
                    
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Approved Date</label>
                      <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border flex items-center">
                        <CheckCircle className="w-4 h-4 text-green-400 mr-2" />
                        {formatDate(permit.approved_date) || 'Not approved'}
                      </div>
                    </div>
                    
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry Date</label>
                      <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border flex items-center">
                        <AlertCircle className="w-4 h-4 text-orange-400 mr-2" />
                        {formatDate(permit.expiry_date) || 'Not set'}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Right Column - Combined Summary & Tax Information */}
          <div className="space-y-6">
            
            {/* Summary Stats Card */}
            <div className="bg-white rounded-xl shadow p-6">
              <h2 className="text-lg font-bold text-gray-900 mb-4">Summary Stats</h2>
              
              <div className="space-y-4">
                {/* Quarter Stats */}
                <div>
                  <h3 className="text-sm font-medium text-gray-700 mb-3">Quarterly Payments</h3>
                  <div className="grid grid-cols-2 gap-3">
                    <div className="text-center p-3 bg-green-50 rounded-lg border border-green-200">
                      <div className="text-xl font-bold text-green-600">{paidQuarters}</div>
                      <div className="text-xs text-green-700 mt-1">Paid</div>
                    </div>
                    
                    <div className="text-center p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                      <div className="text-xl font-bold text-yellow-600">{pendingQuarters}</div>
                      <div className="text-xs text-yellow-700 mt-1">Pending</div>
                    </div>
                    
                    <div className="text-center p-3 bg-red-50 rounded-lg border border-red-200">
                      <div className="text-xl font-bold text-red-600">{overdueQuarters}</div>
                      <div className="text-xs text-red-700 mt-1">Overdue</div>
                    </div>
                    
                    <div className="text-center p-3 bg-blue-50 rounded-lg border border-blue-200">
                      <div className="text-xl font-bold text-blue-600">{totalQuarters}</div>
                      <div className="text-xs text-blue-700 mt-1">Total</div>
                    </div>
                  </div>
                </div>

                {/* Collection Rate */}
                <div className="pt-4 border-t border-gray-200">
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-sm font-medium text-gray-700">Collection Rate</span>
                    <span className="text-lg font-bold text-blue-600">{collectionRate}%</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2">
                    <div 
                      className="bg-green-500 h-2 rounded-full"
                      style={{ width: `${collectionRate}%` }}
                    ></div>
                  </div>
                </div>

                {/* Timestamps */}
                <div className="pt-4 border-t border-gray-200">
                  <h3 className="text-sm font-medium text-gray-700 mb-2">Record Info</h3>
                  <div className="space-y-2">
                    {permit.application_date && (
                      <div className="flex justify-between items-center">
                        <span className="text-xs text-gray-600">Application</span>
                        <span className="text-xs font-medium text-gray-900">{formatDate(permit.application_date)}</span>
                      </div>
                    )}
                    <div className="flex justify-between items-center">
                      <span className="text-xs text-gray-600">Created</span>
                      <span className="text-xs font-medium text-gray-900">{formatDate(permit.created_at)}</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <span className="text-xs text-gray-600">Last Updated</span>
                      <span className="text-xs font-medium text-gray-900">{formatDate(permit.updated_at)}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Tax Summary Card */}
            <div className="bg-white rounded-xl shadow p-6">
              <h2 className="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <PieChart className="w-5 h-5 mr-2 text-purple-600" />
                Tax Summary
              </h2>
              
              <div className="space-y-4">
                <div className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Basic Tax</span>
                    <span className="font-bold text-gray-900">{formatCurrency(permit.tax_amount)}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Regulatory Fees</span>
                    <span className="font-bold text-blue-600">{formatCurrency(permit.regulatory_fees)}</span>
                  </div>
                  <div className="pt-3 border-t border-gray-300">
                    <div className="flex justify-between items-center font-bold">
                      <span className="text-green-700">Total Annual Tax</span>
                      <span className="text-lg font-bold text-green-600">{formatCurrency(permit.total_tax)}</span>
                    </div>
                  </div>
                </div>

                <div className="pt-4 border-t border-gray-200">
                  <div className="space-y-3">
                    <div className="flex justify-between items-center">
                      <div className="flex items-center">
                        <CheckCircle className="w-4 h-4 text-green-600 mr-2" />
                        <span className="text-sm text-green-700">Total Paid</span>
                      </div>
                      <span className="font-bold text-green-600">{formatCurrency(totalPaid)}</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <div className="flex items-center">
                        <Clock className="w-4 h-4 text-yellow-600 mr-2" />
                        <span className="text-sm text-yellow-700">Pending Balance</span>
                      </div>
                      <span className="font-bold text-yellow-600">{formatCurrency(totalPending)}</span>
                    </div>
                    {totalPenalty > 0 && (
                      <div className="flex justify-between items-center">
                        <div className="flex items-center">
                          <AlertTriangle className="w-4 h-4 text-red-600 mr-2" />
                          <span className="text-sm text-red-700">Total Penalty</span>
                        </div>
                        <span className="font-bold text-red-600">{formatCurrency(totalPenalty)}</span>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Quarterly Taxes Table */}
        <div className="mt-6">
          <div className="bg-white rounded-xl shadow">
            <div className="px-6 py-4 border-b border-gray-200">
              <h2 className="text-lg font-bold text-gray-900 flex items-center">
                <Receipt className="w-5 h-5 mr-2 text-blue-600" />
                Quarterly Tax Payments
              </h2>
            </div>
            
            <div className="p-6">
              {quarterlyTaxes.length === 0 ? (
                <div className="text-center py-8">
                  <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Receipt className="w-8 h-8 text-gray-400" />
                  </div>
                  <h3 className="text-lg font-medium text-gray-900 mb-2">No Quarterly Tax Records</h3>
                  <p className="text-gray-600 max-w-md mx-auto">
                    No quarterly tax payment records found for this business permit.
                  </p>
                </div>
              ) : (
                <div className="overflow-hidden rounded-lg border border-gray-200">
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quarter</th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year</th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Penalty</th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Date</th>
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {quarterlyTaxes.map((tax) => {
                          const status = getPaymentStatus(tax.payment_status);
                          const StatusIcon = status.icon;
                          const totalAmount = (parseFloat(tax.total_quarterly_tax) || 0) + (parseFloat(tax.penalty_amount) || 0);
                          
                          return (
                            <tr key={tax.id} className="hover:bg-gray-50 transition-colors">
                              <td className="px-6 py-4 whitespace-nowrap">
                                <span className="font-medium text-gray-900">{tax.quarter}</span>
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-gray-900">{tax.year}</td>
                              <td className="px-6 py-4 whitespace-nowrap text-gray-900">{formatDate(tax.due_date)}</td>
                              <td className="px-6 py-4 whitespace-nowrap">
                                <span className="font-bold text-gray-900">{formatCurrency(tax.total_quarterly_tax)}</span>
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap">
                                {tax.penalty_amount > 0 ? (
                                  <span className="font-medium text-red-600">{formatCurrency(tax.penalty_amount)}</span>
                                ) : (
                                  <span className="text-gray-400">-</span>
                                )}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap">
                                <span className="font-bold text-green-600">{formatCurrency(totalAmount)}</span>
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap">
                                <div className={`inline-flex items-center px-3 py-1.5 rounded-full ${status.bg}`}>
                                  <StatusIcon className={`w-3.5 h-3.5 mr-1.5 ${status.color}`} />
                                  <span className={`text-xs font-medium ${status.color}`}>{status.text}</span>
                                </div>
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-gray-900">
                                {tax.payment_date ? formatDate(tax.payment_date) : '-'}
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Footer Actions */}
        <div className="mt-6 flex justify-between items-center">
          <div className="text-sm text-gray-500">
            Business Permit: <span className="font-mono font-medium">{permit.business_permit_id || permit.applicant_id}</span>
          </div>
          <div className="flex space-x-3">
            <button
              onClick={handlePrint}
              className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50"
            >
              <Printer className="w-4 h-4 mr-2" />
              Print
            </button>
            <button
              onClick={() => navigate('/business/businessstatus')}
              className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50"
            >
              <ArrowLeft className="w-4 h-4 mr-2" />
              Back to List
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}