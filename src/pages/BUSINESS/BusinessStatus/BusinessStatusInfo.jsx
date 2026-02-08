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
  Briefcase,
  CreditCard,
  CalendarDays,
  Hash,
  Wallet,
  FileCheck,
  TrendingUp,
  ChevronRight,
  Download,
  Info,
  Home,
  Building,
  Users
} from "lucide-react";

// Custom color palette
const COLORS = {
  primary: '#4a90e2',      // Blue
  secondary: '#9aa5b1',    // Gray
  success: '#4caf50',      // Green
  warning: '#ff9800',      // Orange
  danger: '#f44336',       // Red
  info: '#2196f3',         // Light Blue
  purple: '#9c27b0',       // Purple
  indigo: '#3f51b5',       // Indigo
  background: '#fbfbfb',   // Light Background
  dark: '#374151',         // Dark Gray
  lightGray: '#f3f4f6'     // Very Light Gray
};

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
    if (!amount || isNaN(amount)) return '₱0';
    const num = parseFloat(amount);
    
    if (num >= 1000000) return `₱${(num / 1000000).toFixed(1)}M`;
    if (num >= 1000) return `₱${(num / 1000).toFixed(1)}K`;
    return `₱${num.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
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
        return { 
          text: "Paid", 
          color: COLORS.success, 
          bg: `${COLORS.success}15`, 
          border: `${COLORS.success}30`,
          icon: CheckCircle 
        };
      case 'overdue':
        return { 
          text: "Overdue", 
          color: COLORS.danger, 
          bg: `${COLORS.danger}15`, 
          border: `${COLORS.danger}30`,
          icon: AlertCircle 
        };
      default:
        return { 
          text: "Pending", 
          color: COLORS.warning, 
          bg: `${COLORS.warning}15`, 
          border: `${COLORS.warning}30`,
          icon: Clock 
        };
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

  const getBusinessStatusColor = (status) => {
    const statusLower = status?.toLowerCase();
    switch(statusLower) {
      case 'active':
      case 'approved':
      case 'renewed':
        return COLORS.success;
      case 'pending':
      case 'for_approval':
        return COLORS.warning;
      case 'expired':
      case 'cancelled':
      case 'suspended':
        return COLORS.danger;
      default:
        return COLORS.secondary;
    }
  };

  const handlePrint = () => {
    window.print();
  };

  const handleExportDetails = () => {
    if (!permit) return;
    
    const headers = [
      "Field", "Value"
    ];

    const data = [
      ["Permit ID", permit.business_permit_id || permit.applicant_id],
      ["Business Name", permit.business_name],
      ["Trade Name", permit.trade_name || "N/A"],
      ["Owner Name", permit.owner_name || permit.owner_full_name],
      ["Owner Type", getOwnerTypeText(permit.owner_type)],
      ["Business Type", permit.business_type || permit.business_nature],
      ["Tax Calculation Type", permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment' : 'Gross Sales'],
      ["Capital Investment/Tax Base", formatCurrency(permit.capital_investment || permit.taxable_amount)],
      ["Tax Rate", `${permit.tax_rate || 0}%`],
      ["Tax Amount", formatCurrency(permit.tax_amount)],
      ["Regulatory Fees", formatCurrency(permit.regulatory_fees)],
      ["Total Annual Tax", formatCurrency(permit.total_tax)],
      ["Business Status", permit.business_status || permit.permit_status],
      ["Contact Number", permit.contact_number || "N/A"],
      ["Email Address", permit.email_address || "N/A"],
      ["Address", `${permit.business_barangay || ''}, ${permit.business_city || ''}, ${permit.business_province || ''}`],
      ["Issue Date", formatDate(permit.issue_date)],
      ["Approved Date", formatDate(permit.approved_date)],
      ["Expiry Date", formatDate(permit.expiry_date)],
      ["Application Date", formatDate(permit.application_date) || "N/A"],
      ["Created At", formatDate(permit.created_at)],
      ["Updated At", formatDate(permit.updated_at)]
    ];

    const csvContent = [
      headers.join(","),
      ...data.map(row => `"${row[0]}","${row[1]}"`)
    ].join("\n");

    const blob = new Blob([csvContent], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `business-details-${permit.business_permit_id}-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center" style={{ backgroundColor: COLORS.background }}>
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 mx-auto mb-4" style={{ borderColor: COLORS.primary }}></div>
          <p className="text-gray-700">Loading Business Details...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center" style={{ backgroundColor: COLORS.background }}>
        <div className="max-w-md w-full bg-white rounded-xl border p-8" style={{ borderColor: COLORS.secondary }}>
          <div className="flex items-center mb-6">
            <AlertCircle className="h-10 w-10 mr-4" style={{ color: COLORS.danger }} />
            <div>
              <h2 className="text-xl font-bold" style={{ color: COLORS.danger }}>Error</h2>
              <p style={{ color: COLORS.secondary }}>{error}</p>
            </div>
          </div>
          <button
            onClick={() => navigate('/business/businessstatus')}
            className="w-full px-6 py-3 rounded-lg flex items-center justify-center gap-2 transition-all"
            style={{ backgroundColor: COLORS.primary, color: 'white' }}
          >
            <ArrowLeft className="w-4 h-4" />
            Back to Business List
          </button>
        </div>
      </div>
    );
  }

  if (!permit) {
    return (
      <div className="min-h-screen flex items-center justify-center" style={{ backgroundColor: COLORS.background }}>
        <div className="max-w-md w-full bg-white rounded-xl border p-8" style={{ borderColor: COLORS.secondary }}>
          <div className="flex items-center mb-6">
            <AlertCircle className="h-10 w-10 mr-4" style={{ color: COLORS.danger }} />
            <div>
              <h2 className="text-xl font-bold" style={{ color: COLORS.danger }}>Business Permit Not Found</h2>
              <p style={{ color: COLORS.secondary }}>The requested business permit could not be found.</p>
            </div>
          </div>
          <button
            onClick={() => navigate('/business/businessstatus')}
            className="w-full px-6 py-3 rounded-lg flex items-center justify-center gap-2 transition-all"
            style={{ backgroundColor: COLORS.primary, color: 'white' }}
          >
            <ArrowLeft className="w-4 h-4" />
            Back to Business List
          </button>
        </div>
      </div>
    );
  }

  // Calculate stats
  const paidTaxes = quarterlyTaxes.filter(tax => tax.payment_status === 'paid');
  const totalPaid = paidTaxes.reduce((sum, tax) => sum + (parseFloat(tax.total_quarterly_tax) || 0), 0);
  const collectionRate = permit.total_tax > 0 ? Math.round((totalPaid / permit.total_tax) * 100) : 0;
  const totalPending = parseFloat(permit.total_pending_tax) || 0;
  const totalPenalty = parseFloat(permit.total_penalty) || 0;

  // Calculate quarter stats
  const paidQuarters = quarterlyTaxes.filter(t => t.payment_status === 'paid').length;
  const pendingQuarters = quarterlyTaxes.filter(t => t.payment_status === 'pending').length;
  const overdueQuarters = quarterlyTaxes.filter(t => t.payment_status === 'overdue').length;
  const totalQuarters = quarterlyTaxes.length;

  return (
    <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        
        {/* Header Section */}
        <div className="bg-white rounded-xl border p-6 mb-6" style={{ borderColor: COLORS.secondary }}>
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-4">
                <button 
                  onClick={() => navigate('/business/businessstatus')} 
                  className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-all"
                  style={{ color: COLORS.primary, borderColor: COLORS.secondary, borderWidth: '1px' }}
                >
                  <ArrowLeft className="w-4 h-4" />
                  Back to List
                </button>
              </div>
              
              <div className="flex items-start gap-4">
                <div className="p-3 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                  <Briefcase className="w-6 h-6" style={{ color: COLORS.primary }} />
                </div>
                <div className="flex-1">
                  <div className="flex flex-wrap items-center gap-3 mb-2">
                    <h1 className="text-2xl font-bold" style={{ color: COLORS.dark }}>{permit.business_name}</h1>
                    <div className="px-2 py-1 rounded text-xs font-medium border" 
                         style={{ 
                           backgroundColor: `${getBusinessStatusColor(permit.business_status || permit.permit_status)}15`,
                           color: getBusinessStatusColor(permit.business_status || permit.permit_status),
                           borderColor: `${getBusinessStatusColor(permit.business_status || permit.permit_status)}30`
                         }}>
                      {permit.business_status || permit.permit_status || 'N/A'}
                    </div>
                  </div>
                  
                  <div className="flex flex-wrap items-center gap-4">
                    <div className="flex items-center gap-2">
                      <Hash className="w-4 h-4" style={{ color: COLORS.secondary }} />
                      <span className="font-mono font-medium" style={{ color: COLORS.primary }}>
                        {permit.business_permit_id || permit.applicant_id}
                      </span>
                      <span className="text-sm" style={{ color: COLORS.secondary }}>Permit ID</span>
                    </div>
                    
                    <div className="flex items-center gap-2">
                      <CalendarDays className="w-4 h-4" style={{ color: COLORS.secondary }} />
                      <span className="text-sm" style={{ color: COLORS.secondary }}>
                        Issued: {formatDate(permit.issue_date) || 'Not issued'}
                      </span>
                    </div>
                    
                    <div className="flex items-center gap-2">
                      <User className="w-4 h-4" style={{ color: COLORS.secondary }} />
                      <span className="text-sm" style={{ color: COLORS.dark }}>
                        {permit.owner_name || permit.owner_full_name}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div className="flex flex-col items-end gap-3">
              <div className="text-right">
                <div className="text-lg font-bold" style={{ color: COLORS.success }}>
                  {formatCurrency(permit.total_tax)}
                </div>
                <div className="text-sm" style={{ color: COLORS.secondary }}>Annual Tax</div>
              </div>
              
              <div className="flex gap-2">
                <button
                  onClick={handlePrint}
                  className="px-4 py-2 rounded-lg flex items-center gap-2 transition-all border"
                  style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                >
                  <Printer className="w-4 h-4" />
                  Print
                </button>
                
                <button
                  onClick={handleExportDetails}
                  className="px-4 py-2 rounded-lg flex items-center gap-2 transition-all"
                  style={{ backgroundColor: COLORS.primary, color: 'white' }}
                >
                  <Download className="w-4 h-4" />
                  Export
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Main Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          {/* Left Column - Owner & Business Info */}
          <div className="lg:col-span-2 space-y-6">
            
            {/* Owner Information Card */}
            <div className="bg-white rounded-xl border p-6" style={{ borderColor: COLORS.secondary }}>
              <div className="flex items-center gap-2 mb-4">
                <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.info}15` }}>
                  <UserCircle className="w-5 h-5" style={{ color: COLORS.info }} />
                </div>
                <h2 className="font-semibold" style={{ color: COLORS.dark }}>Owner Information</h2>
              </div>
              
              <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-medium mb-1" style={{ color: COLORS.secondary }}>OWNER NAME</label>
                    <div className="flex items-center gap-2 p-2.5 rounded-lg border" style={{ borderColor: COLORS.secondary }}>
                      <User className="w-4 h-4" style={{ color: COLORS.secondary }} />
                      <span className="font-medium" style={{ color: COLORS.dark }}>
                        {permit.owner_name || permit.owner_full_name || 'Not specified'}
                      </span>
                    </div>
                  </div>
                  
                  <div>
                    <label className="block text-xs font-medium mb-1" style={{ color: COLORS.secondary }}>OWNER TYPE</label>
                    <div className="p-2.5 rounded-lg border text-center"
                         style={{ 
                           borderColor: permit.owner_type?.toLowerCase() === 'corporation' ? COLORS.info : COLORS.primary,
                           backgroundColor: permit.owner_type?.toLowerCase() === 'corporation' ? `${COLORS.info}10` : `${COLORS.primary}10`,
                           color: permit.owner_type?.toLowerCase() === 'corporation' ? COLORS.info : COLORS.primary
                         }}>
                      <span className="font-medium">{getOwnerTypeText(permit.owner_type)}</span>
                    </div>
                  </div>
                </div>

                <div className="border-t pt-4" style={{ borderColor: `${COLORS.secondary}30` }}>
                  <h3 className="text-sm font-medium mb-3" style={{ color: COLORS.secondary }}>CONTACT INFORMATION</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {permit.contact_number && (
                      <div>
                        <label className="block text-xs font-medium mb-1" style={{ color: COLORS.secondary }}>CONTACT NUMBER</label>
                        <div className="flex items-center gap-2 p-2.5 rounded-lg border" style={{ borderColor: COLORS.secondary }}>
                          <Phone className="w-4 h-4" style={{ color: COLORS.secondary }} />
                          <span className="font-medium" style={{ color: COLORS.dark }}>{permit.contact_number}</span>
                        </div>
                      </div>
                    )}
                    
                    {permit.email_address && (
                      <div>
                        <label className="block text-xs font-medium mb-1" style={{ color: COLORS.secondary }}>EMAIL ADDRESS</label>
                        <div className="flex items-center gap-2 p-2.5 rounded-lg border" style={{ borderColor: COLORS.secondary }}>
                          <Mail className="w-4 h-4" style={{ color: COLORS.secondary }} />
                          <span className="font-medium" style={{ color: COLORS.dark }}>{permit.email_address}</span>
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </div>

            {/* Business Information Card */}
            <div className="bg-white rounded-xl border p-6" style={{ borderColor: COLORS.secondary }}>
              <div className="flex items-center gap-2 mb-4">
                <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.success}15` }}>
                  <Building2 className="w-5 h-5" style={{ color: COLORS.success }} />
                </div>
                <h2 className="font-semibold" style={{ color: COLORS.dark }}>Business Information</h2>
              </div>
              
              <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-medium mb-1" style={{ color: COLORS.secondary }}>BUSINESS NAME</label>
                    <div className="p-2.5 rounded-lg border" style={{ borderColor: COLORS.secondary }}>
                      <span className="font-medium" style={{ color: COLORS.dark }}>{permit.business_name}</span>
                    </div>
                  </div>
                  
                  <div>
                    <label className="block text-xs font-medium mb-1" style={{ color: COLORS.secondary }}>BUSINESS TYPE</label>
                    <div className="flex items-center gap-2 p-2.5 rounded-lg border" style={{ borderColor: COLORS.secondary }}>
                      <Tag className="w-4 h-4" style={{ color: COLORS.secondary }} />
                      <span className="font-medium" style={{ color: COLORS.dark }}>
                        {permit.business_type || permit.business_nature || 'N/A'}
                      </span>
                    </div>
                  </div>
                </div>

                <div className="border-t pt-4" style={{ borderColor: `${COLORS.secondary}30` }}>
                  <h3 className="text-sm font-medium mb-3" style={{ color: COLORS.secondary }}>TAX INFORMATION</h3>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                      <label className="block text-xs font-medium mb-1" style={{ color: COLORS.secondary }}>
                        {permit.tax_calculation_type === 'capital_investment' ? 'CAPITAL INVESTMENT' : 'TAX BASE'}
                      </label>
                      <div className="p-2.5 rounded-lg border text-center" style={{ borderColor: COLORS.secondary }}>
                        <span className="font-bold" style={{ color: COLORS.dark }}>
                          {formatCurrency(permit.capital_investment || permit.taxable_amount || 0)}
                        </span>
                      </div>
                    </div>
                    
                    <div>
                      <label className="block text-xs font-medium mb-1" style={{ color: COLORS.secondary }}>TAX RATE</label>
                      <div className="p-2.5 rounded-lg border text-center" 
                           style={{ 
                             borderColor: COLORS.info, 
                             backgroundColor: `${COLORS.info}10`,
                             color: COLORS.info
                           }}>
                        <div className="flex items-center justify-center gap-1">
                          <Percent className="w-3.5 h-3.5" />
                          <span className="font-bold">{permit.tax_rate || 0}%</span>
                        </div>
                      </div>
                    </div>
                    
                    <div>
                      <label className="block text-xs font-medium mb-1" style={{ color: COLORS.secondary }}>TAX AMOUNT</label>
                      <div className="p-2.5 rounded-lg border text-center" 
                           style={{ 
                             borderColor: COLORS.success, 
                             backgroundColor: `${COLORS.success}10`,
                             color: COLORS.success
                           }}>
                        <span className="font-bold">{formatCurrency(permit.tax_amount || 0)}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="border-t pt-4" style={{ borderColor: `${COLORS.secondary}30` }}>
                  <h3 className="text-sm font-medium mb-3" style={{ color: COLORS.secondary }}>BUSINESS ADDRESS</h3>
                  <div className="p-3 rounded-lg border" style={{ borderColor: COLORS.secondary, backgroundColor: `${COLORS.secondary}10` }}>
                    <div className="flex items-start gap-2">
                      <MapPin className="w-4 h-4 mt-0.5" style={{ color: COLORS.secondary }} />
                      <div>
                        <p className="font-medium" style={{ color: COLORS.dark }}>
                          Brgy. {permit.business_barangay || 'N/A'}, {permit.business_city || 'N/A'}
                          {permit.business_province && `, ${permit.business_province}`}
                          {permit.business_zipcode && ` ${permit.business_zipcode}`}
                        </p>
                        {permit.business_district && permit.business_district !== 'Unknown' && (
                          <p className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                            District: {permit.business_district}
                          </p>
                        )}
                      </div>
                    </div>
                  </div>
                </div>

                <div className="border-t pt-4" style={{ borderColor: `${COLORS.secondary}30` }}>
                  <h3 className="text-sm font-medium mb-3" style={{ color: COLORS.secondary }}>PERMIT DATES</h3>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                      <label className="block text-xs font-medium mb-1" style={{ color: COLORS.secondary }}>ISSUE DATE</label>
                      <div className="flex items-center gap-2 p-2.5 rounded-lg border" style={{ borderColor: COLORS.secondary }}>
                        <Calendar className="w-4 h-4" style={{ color: COLORS.secondary }} />
                        <span className="font-medium" style={{ color: COLORS.dark }}>{formatDate(permit.issue_date) || 'Not issued'}</span>
                      </div>
                    </div>
                    
                    <div>
                      <label className="block text-xs font-medium mb-1" style={{ color: COLORS.secondary }}>APPROVED DATE</label>
                      <div className="flex items-center gap-2 p-2.5 rounded-lg border" style={{ borderColor: COLORS.secondary }}>
                        <CheckCircle className="w-4 h-4" style={{ color: COLORS.success }} />
                        <span className="font-medium" style={{ color: COLORS.dark }}>{formatDate(permit.approved_date) || 'Not approved'}</span>
                      </div>
                    </div>
                    
                    <div>
                      <label className="block text-xs font-medium mb-1" style={{ color: COLORS.secondary }}>EXPIRY DATE</label>
                      <div className="flex items-center gap-2 p-2.5 rounded-lg border" style={{ borderColor: COLORS.secondary }}>
                        <AlertCircle className="w-4 h-4" style={{ color: COLORS.warning }} />
                        <span className="font-medium" style={{ color: COLORS.dark }}>{formatDate(permit.expiry_date) || 'Not set'}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Right Column - Summary & Stats */}
          <div className="space-y-6">
            
            {/* Summary Stats Card */}
            <div className="bg-white rounded-xl border p-6" style={{ borderColor: COLORS.secondary }}>
              <h2 className="font-semibold mb-4" style={{ color: COLORS.dark }}>Summary Stats</h2>
              
              <div className="space-y-4">
                <div>
                  <h3 className="text-sm font-medium mb-3" style={{ color: COLORS.secondary }}>QUARTERLY PAYMENTS</h3>
                  <div className="grid grid-cols-2 gap-3">
                    <div className="p-3 rounded-lg text-center" 
                         style={{ 
                           backgroundColor: `${COLORS.success}15`,
                           border: `1px solid ${COLORS.success}30`
                         }}>
                      <div className="text-lg font-bold" style={{ color: COLORS.success }}>{paidQuarters}</div>
                      <div className="text-xs mt-1" style={{ color: COLORS.success }}>Paid</div>
                    </div>
                    
                    <div className="p-3 rounded-lg text-center" 
                         style={{ 
                           backgroundColor: `${COLORS.warning}15`,
                           border: `1px solid ${COLORS.warning}30`
                         }}>
                      <div className="text-lg font-bold" style={{ color: COLORS.warning }}>{pendingQuarters}</div>
                      <div className="text-xs mt-1" style={{ color: COLORS.warning }}>Pending</div>
                    </div>
                    
                    <div className="p-3 rounded-lg text-center" 
                         style={{ 
                           backgroundColor: `${COLORS.danger}15`,
                           border: `1px solid ${COLORS.danger}30`
                         }}>
                      <div className="text-lg font-bold" style={{ color: COLORS.danger }}>{overdueQuarters}</div>
                      <div className="text-xs mt-1" style={{ color: COLORS.danger }}>Overdue</div>
                    </div>
                    
                    <div className="p-3 rounded-lg text-center" 
                         style={{ 
                           backgroundColor: `${COLORS.primary}15`,
                           border: `1px solid ${COLORS.primary}30`
                         }}>
                      <div className="text-lg font-bold" style={{ color: COLORS.primary }}>{totalQuarters}</div>
                      <div className="text-xs mt-1" style={{ color: COLORS.primary }}>Total</div>
                    </div>
                  </div>
                </div>

                <div className="pt-4 border-t" style={{ borderColor: `${COLORS.secondary}30` }}>
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-sm font-medium" style={{ color: COLORS.secondary }}>Collection Rate</span>
                    <span className="font-bold" style={{ color: COLORS.primary }}>{collectionRate}%</span>
                  </div>
                  <div className="w-full h-2 rounded-full" style={{ backgroundColor: `${COLORS.secondary}30` }}>
                    <div 
                      className="h-full rounded-full"
                      style={{ 
                        width: `${collectionRate}%`,
                        backgroundColor: collectionRate >= 80 ? COLORS.success : 
                                       collectionRate >= 60 ? COLORS.warning : COLORS.danger
                      }}
                    ></div>
                  </div>
                </div>

                <div className="pt-4 border-t" style={{ borderColor: `${COLORS.secondary}30` }}>
                  <h3 className="text-sm font-medium mb-2" style={{ color: COLORS.secondary }}>RECORD INFO</h3>
                  <div className="space-y-2">
                    {permit.application_date && (
                      <div className="flex justify-between items-center">
                        <span className="text-xs" style={{ color: COLORS.secondary }}>Application</span>
                        <span className="text-xs font-medium" style={{ color: COLORS.dark }}>{formatDate(permit.application_date)}</span>
                      </div>
                    )}
                    <div className="flex justify-between items-center">
                      <span className="text-xs" style={{ color: COLORS.secondary }}>Created</span>
                      <span className="text-xs font-medium" style={{ color: COLORS.dark }}>{formatDate(permit.created_at)}</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <span className="text-xs" style={{ color: COLORS.secondary }}>Last Updated</span>
                      <span className="text-xs font-medium" style={{ color: COLORS.dark }}>{formatDate(permit.updated_at)}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Tax Summary Card */}
            <div className="bg-white rounded-xl border p-6" style={{ borderColor: COLORS.secondary }}>
              <div className="flex items-center gap-2 mb-4">
                <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.purple}15` }}>
                  <PieChart className="w-5 h-5" style={{ color: COLORS.purple }} />
                </div>
                <h2 className="font-semibold" style={{ color: COLORS.dark }}>Tax Summary</h2>
              </div>
              
              <div className="space-y-4">
                <div className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-sm" style={{ color: COLORS.secondary }}>Basic Tax</span>
                    <span className="font-bold" style={{ color: COLORS.dark }}>{formatCurrency(permit.tax_amount)}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm" style={{ color: COLORS.secondary }}>Regulatory Fees</span>
                    <span className="font-bold" style={{ color: COLORS.primary }}>{formatCurrency(permit.regulatory_fees)}</span>
                  </div>
                  <div className="pt-3 border-t" style={{ borderColor: `${COLORS.secondary}30` }}>
                    <div className="flex justify-between items-center font-bold">
                      <span style={{ color: COLORS.success }}>Total Annual Tax</span>
                      <span className="text-lg" style={{ color: COLORS.success }}>{formatCurrency(permit.total_tax)}</span>
                    </div>
                  </div>
                </div>

                <div className="pt-4 border-t" style={{ borderColor: `${COLORS.secondary}30` }}>
                  <div className="space-y-3">
                    <div className="flex justify-between items-center">
                      <div className="flex items-center gap-2">
                        <CheckCircle className="w-4 h-4" style={{ color: COLORS.success }} />
                        <span className="text-sm" style={{ color: COLORS.success }}>Total Paid</span>
                      </div>
                      <span className="font-bold" style={{ color: COLORS.success }}>{formatCurrency(totalPaid)}</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <div className="flex items-center gap-2">
                        <Clock className="w-4 h-4" style={{ color: COLORS.warning }} />
                        <span className="text-sm" style={{ color: COLORS.warning }}>Pending Balance</span>
                      </div>
                      <span className="font-bold" style={{ color: COLORS.warning }}>{formatCurrency(totalPending)}</span>
                    </div>
                    {totalPenalty > 0 && (
                      <div className="flex justify-between items-center">
                        <div className="flex items-center gap-2">
                          <AlertTriangle className="w-4 h-4" style={{ color: COLORS.danger }} />
                          <span className="text-sm" style={{ color: COLORS.danger }}>Total Penalty</span>
                        </div>
                        <span className="font-bold" style={{ color: COLORS.danger }}>{formatCurrency(totalPenalty)}</span>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Quarterly Taxes Table */}
        <div className="mt-6 bg-white rounded-xl border overflow-hidden" style={{ borderColor: COLORS.secondary }}>
          <div className="p-4 border-b" style={{ borderColor: COLORS.secondary, backgroundColor: `${COLORS.secondary}10` }}>
            <div className="flex items-center gap-2">
              <Receipt className="w-5 h-5" style={{ color: COLORS.primary }} />
              <h2 className="font-semibold" style={{ color: COLORS.dark }}>Quarterly Tax Payments</h2>
            </div>
          </div>
          
          <div className="p-6">
            {quarterlyTaxes.length === 0 ? (
              <div className="text-center py-8">
                <div className="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" 
                     style={{ backgroundColor: `${COLORS.secondary}15` }}>
                  <Receipt className="w-8 h-8" style={{ color: COLORS.secondary }} />
                </div>
                <h3 className="font-medium mb-2" style={{ color: COLORS.dark }}>No Quarterly Tax Records</h3>
                <p className="max-w-md mx-auto" style={{ color: COLORS.secondary }}>
                  No quarterly tax payment records found for this business permit.
                </p>
              </div>
            ) : (
              <div className="overflow-hidden rounded-lg border" style={{ borderColor: COLORS.secondary }}>
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead>
                      <tr style={{ borderBottom: `1px solid ${COLORS.secondary}`, backgroundColor: `${COLORS.secondary}10` }}>
                        <th className="p-3 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary }}>Quarter</th>
                        <th className="p-3 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary }}>Year</th>
                        <th className="p-3 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary }}>Due Date</th>
                        <th className="p-3 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary }}>Amount</th>
                        <th className="p-3 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary }}>Penalty</th>
                        <th className="p-3 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary }}>Total</th>
                        <th className="p-3 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary }}>Status</th>
                        <th className="p-3 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary }}>Payment Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      {quarterlyTaxes.map((tax) => {
                        const status = getPaymentStatus(tax.payment_status);
                        const StatusIcon = status.icon;
                        const totalAmount = (parseFloat(tax.total_quarterly_tax) || 0) + (parseFloat(tax.penalty_amount) || 0);
                        
                        return (
                          <tr key={tax.id} className="hover:bg-gray-50 transition-colors" style={{ borderBottom: `1px solid ${COLORS.secondary}30` }}>
                            <td className="p-3">
                              <span className="font-medium" style={{ color: COLORS.dark }}>{tax.quarter}</span>
                            </td>
                            <td className="p-3" style={{ color: COLORS.dark }}>{tax.year}</td>
                            <td className="p-3" style={{ color: COLORS.dark }}>{formatDate(tax.due_date)}</td>
                            <td className="p-3">
                              <span className="font-bold" style={{ color: COLORS.dark }}>{formatCurrency(tax.total_quarterly_tax)}</span>
                            </td>
                            <td className="p-3">
                              {tax.penalty_amount > 0 ? (
                                <span className="font-medium" style={{ color: COLORS.danger }}>{formatCurrency(tax.penalty_amount)}</span>
                              ) : (
                                <span style={{ color: COLORS.secondary }}>-</span>
                              )}
                            </td>
                            <td className="p-3">
                              <span className="font-bold" style={{ color: COLORS.success }}>{formatCurrency(totalAmount)}</span>
                            </td>
                            <td className="p-3">
                              <div className="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium border"
                                   style={{ 
                                     backgroundColor: status.bg,
                                     color: status.color,
                                     borderColor: status.border
                                   }}>
                                <StatusIcon className="w-3 h-3 mr-1" />
                                {status.text}
                              </div>
                            </td>
                            <td className="p-3" style={{ color: COLORS.dark }}>
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

        {/* Footer Actions */}
        <div className="mt-6 pt-6 border-t" style={{ borderColor: COLORS.secondary }}>
          <div className="flex flex-col md:flex-row justify-between items-center gap-4">
            <div className="text-sm" style={{ color: COLORS.secondary }}>
              Business Permit ID: <span className="font-mono font-medium" style={{ color: COLORS.dark }}>
                {permit.business_permit_id || permit.applicant_id}
              </span>
            </div>
            <div className="flex gap-3">
              <button
                onClick={() => navigate('/business/businessstatus')}
                className="px-4 py-2 rounded-lg flex items-center gap-2 transition-all border"
                style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
              >
                <ArrowLeft className="w-4 h-4" />
                Back to List
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}