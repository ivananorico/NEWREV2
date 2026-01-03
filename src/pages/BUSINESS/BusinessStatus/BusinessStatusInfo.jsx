import { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import {
  ArrowLeft,
  Building,
  User,
  Calendar,
  DollarSign,
  MapPin,
  Phone,
  Mail,
  FileText,
  CheckCircle,
  AlertCircle,
  Clock,
  Printer,
  Download,
  Home,
  CreditCard,
  Percent,
  Shield,
  Briefcase,
  Navigation,
  UserCircle,
  Building2,
  Tag,
  AlertTriangle,
  TrendingUp,
  CreditCard as Card
} from "lucide-react";

export default function BusinessStatusInfo() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [permit, setPermit] = useState(null);
  const [quarterlyTaxes, setQuarterlyTaxes] = useState([]);
  const [loading, setLoading] = useState(true);

  // API Configuration
  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

  useEffect(() => {
    fetchPermitDetails();
  }, [id]);

  const fetchPermitDetails = async () => {
    try {
      setLoading(true);
      const res = await fetch(
        `${API_BASE}/Business/BusinessStatus/get_permit_by_id.php?id=${id}`
      );
      
      const data = await res.json();
      console.log("API Response:", data); // Debug log
      
      if (data.status === "success") {
        setPermit(data.data.permit);
        setQuarterlyTaxes(data.data.quarterlyTaxes || []);
      } else {
        console.error("API Error:", data.message);
      }
    } catch (err) {
      console.error("Fetch Error:", err);
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
    if (!dateString || dateString === '0000-00-00') return "Not set";
    try {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    } catch (e) {
      return dateString; // Return as-is if formatting fails
    }
  };

  const getPaymentStatus = (status) => {
    switch(status) {
      case 'paid':
        return { text: "Paid", color: "text-green-600", bg: "bg-green-50", icon: CheckCircle };
      case 'overdue':
        return { text: "Overdue", color: "text-red-600", bg: "bg-red-50", icon: AlertCircle };
      default:
        return { text: "Pending", color: "text-yellow-600", bg: "bg-yellow-50", icon: Clock };
    }
  };

  const getGenderText = (gender) => {
    switch(gender) {
      case 'male': return 'Male';
      case 'female': return 'Female';
      case 'other': return 'Other';
      default: return 'Not specified';
    }
  };

  const getMaritalStatusText = (status) => {
    switch(status) {
      case 'single': return 'Single';
      case 'married': return 'Married';
      case 'divorced': return 'Divorced';
      case 'widowed': return 'Widowed';
      default: return 'Not specified';
    }
  };

  const handlePrint = () => {
    window.print();
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-3 text-gray-600">Loading business details...</p>
        </div>
      </div>
    );
  }

  if (!permit) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-lg p-6 text-center">
          <AlertCircle className="w-12 h-12 text-red-400 mx-auto mb-3" />
          <h2 className="text-lg font-semibold text-gray-900 mb-2">Business not found</h2>
          <button
            onClick={() => navigate(-1)}
            className="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            Back to List
          </button>
        </div>
      </div>
    );
  }

  const paidTaxes = quarterlyTaxes.filter(tax => tax.payment_status === 'paid');
  const totalPaid = paidTaxes.reduce((sum, tax) => sum + (parseFloat(tax.total_quarterly_tax) || 0), 0);
  const collectionRate = permit.total_tax > 0 ? Math.round((totalPaid / permit.total_tax) * 100) : 0;
  const totalPending = parseFloat(permit.total_pending_tax) || 0;
  const totalPenalty = parseFloat(permit.total_penalty) || 0;

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b">
        <div className="max-w-7xl mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <button
                onClick={() => navigate(-1)}
                className="p-2 hover:bg-gray-100 rounded"
              >
                <ArrowLeft className="w-5 h-5" />
              </button>
              <div>
                <h1 className="text-xl font-bold text-gray-900">{permit.business_name}</h1>
                <p className="text-sm text-gray-600">Business Permit Details - ID: {permit.business_permit_id}</p>
              </div>
            </div>
            <div className="flex items-center space-x-2">
              <button
                onClick={handlePrint}
                className="flex items-center px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded"
              >
                <Printer className="w-4 h-4 mr-1" />
                Print
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 py-6 space-y-6">
        {/* Quick Stats */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div className="bg-white p-4 border rounded">
            <p className="text-sm text-gray-500">Business Status</p>
            <div className="flex items-center mt-1">
              <span className={`inline-block px-3 py-1 text-sm rounded-full ${
                permit.business_status === 'Active' ? 'bg-green-100 text-green-800' :
                permit.business_status === 'Approved' ? 'bg-blue-100 text-blue-800' :
                'bg-gray-100 text-gray-800'
              }`}>
                {permit.business_status || 'N/A'}
              </span>
            </div>
          </div>
          
          <div className="bg-white p-4 border rounded">
            <p className="text-sm text-gray-500">Annual Tax</p>
            <p className="text-lg font-bold text-green-600 mt-1">{formatCurrency(permit.total_tax)}</p>
          </div>
          
          <div className="bg-white p-4 border rounded">
            <p className="text-sm text-gray-500">Collection Rate</p>
            <div className="flex items-center space-x-2 mt-1">
              <div className="flex-1 bg-gray-200 rounded-full h-2">
                <div 
                  className="bg-green-500 h-2 rounded-full" 
                  style={{ width: `${collectionRate}%` }}
                ></div>
              </div>
              <span className="text-sm font-bold text-blue-600">{collectionRate}%</span>
            </div>
          </div>
          
          <div className="bg-white p-4 border rounded">
            <p className="text-sm text-gray-500">Payment Status</p>
            <span className={`inline-block px-3 py-1 text-sm rounded-full ${
              permit.overall_status_color === 'green' ? 'bg-green-100 text-green-800' :
              permit.overall_status_color === 'red' ? 'bg-red-100 text-red-800' :
              permit.overall_status_color === 'yellow' ? 'bg-yellow-100 text-yellow-800' :
              'bg-gray-100 text-gray-800'
            }`}>
              {permit.overall_status_text || 'N/A'}
            </span>
          </div>
        </div>

        {/* Owner Information */}
        <div className="bg-white border rounded">
          <div className="px-6 py-4 border-b">
            <div className="flex items-center">
              <UserCircle className="w-5 h-5 text-gray-600 mr-2" />
              <h2 className="text-lg font-semibold">Owner Information</h2>
            </div>
          </div>
          
          <div className="p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="space-y-4">
                <div>
                  <p className="text-sm text-gray-500 mb-1">Full Name</p>
                  <p className="font-medium text-lg">{permit.owner_name || 'Not specified'}</p>
                </div>
                
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Gender</p>
                    <p className="font-medium">{getGenderText(permit.sex)}</p>
                  </div>
                  
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Marital Status</p>
                    <p className="font-medium">{getMaritalStatusText(permit.marital_status)}</p>
                  </div>
                </div>
                
                {permit.date_of_birth && permit.date_of_birth !== '0000-00-00' && (
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Date of Birth</p>
                    <p className="font-medium">{formatDate(permit.date_of_birth)}</p>
                  </div>
                )}
              </div>
              
              <div className="space-y-4">
                <div>
                  <p className="text-sm text-gray-500 mb-1">Personal Address</p>
                  <div className="space-y-1">
                    {permit.personal_street && (
                      <p className="font-medium">{permit.personal_street}</p>
                    )}
                    <p className="text-gray-600">
                      Brgy. {permit.personal_barangay || 'N/A'}, {permit.personal_city || 'N/A'}, {permit.personal_province || 'N/A'}
                    </p>
                    {permit.personal_zipcode && (
                      <p className="text-gray-500 text-sm">ZIP: {permit.personal_zipcode}</p>
                    )}
                  </div>
                </div>
                
                <div className="space-y-2">
                  <p className="text-sm text-gray-500 mb-1">Contact Information</p>
                  <div className="space-y-1">
                    {permit.personal_contact && (
                      <div className="flex items-center">
                        <Phone className="w-4 h-4 text-gray-400 mr-2" />
                        <span>{permit.personal_contact}</span>
                      </div>
                    )}
                    {permit.personal_email && (
                      <div className="flex items-center">
                        <Mail className="w-4 h-4 text-gray-400 mr-2" />
                        <span>{permit.personal_email}</span>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Business Information */}
        <div className="bg-white border rounded">
          <div className="px-6 py-4 border-b">
            <div className="flex items-center">
              <Building2 className="w-5 h-5 text-gray-600 mr-2" />
              <h2 className="text-lg font-semibold">Business Information</h2>
            </div>
          </div>
          
          <div className="p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="space-y-4">
                <div>
                  <p className="text-sm text-gray-500 mb-1">Business Name</p>
                  <p className="font-medium text-lg">{permit.business_name}</p>
                </div>
                
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Business Type</p>
                    <div className="flex items-center">
                      <Tag className="w-4 h-4 text-gray-400 mr-1" />
                      <span className="font-medium">{permit.business_type || 'N/A'}</span>
                    </div>
                  </div>
                  
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Tax Type</p>
                    <div className="flex items-center">
                      <CreditCard className="w-4 h-4 text-gray-400 mr-1" />
                      <span className="font-medium">
                        {permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment' : 'Gross Sales'}
                      </span>
                    </div>
                  </div>
                </div>
                
                {permit.taxable_amount > 0 && (
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Tax Basis</p>
                    <p className="font-medium">
                      {permit.tax_calculation_type === 'capital_investment' 
                        ? `Capital: ${formatCurrency(permit.taxable_amount)}`
                        : `Tax Rate: ${permit.tax_rate || 0}%`
                      }
                    </p>
                  </div>
                )}
              </div>
              
              <div className="space-y-4">
                <div>
                  <p className="text-sm text-gray-500 mb-1">Business Address</p>
                  <div className="space-y-1">
                    {permit.business_street && (
                      <p className="font-medium">{permit.business_street}</p>
                    )}
                    <p className="text-gray-600">
                      Brgy. {permit.business_barangay || 'N/A'}, {permit.business_city || 'N/A'}, {permit.business_province || 'N/A'}
                    </p>
                    {permit.business_zipcode && (
                      <p className="text-gray-500 text-sm">ZIP: {permit.business_zipcode}</p>
                    )}
                    {permit.business_district && permit.business_district !== 'Unknown' && (
                      <p className="text-gray-500 text-sm">District: {permit.business_district}</p>
                    )}
                  </div>
                </div>
                
                <div>
                  <p className="text-sm text-gray-500 mb-1">Permit Details</p>
                  <div className="space-y-1 text-sm">
                    <div className="flex justify-between">
                      <span className="text-gray-600">Issued:</span>
                      <span>{formatDate(permit.issue_date)}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-gray-600">Approved:</span>
                      <span>{formatDate(permit.approved_date)}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-gray-600">Expires:</span>
                      <span>{formatDate(permit.expiry_date)}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Tax Breakdown */}
        <div className="bg-white border rounded">
          <div className="px-6 py-4 border-b">
            <div className="flex items-center">
              <CreditCard className="w-5 h-5 text-gray-600 mr-2" />
              <h2 className="text-lg font-semibold">Tax Breakdown</h2>
            </div>
          </div>
          
          <div className="p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <div className="mb-6">
                  <p className="text-sm text-gray-500 mb-3">Annual Tax Composition</p>
                  <div className="space-y-3">
                    <div className="flex justify-between items-center">
                      <span>Basic Tax:</span>
                      <span className="font-medium">{formatCurrency(permit.tax_amount)}</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <span>Regulatory Fees:</span>
                      <span className="font-medium">{formatCurrency(permit.regulatory_fees)}</span>
                    </div>
                    <div className="pt-3 border-t">
                      <div className="flex justify-between items-center font-semibold text-lg">
                        <span>Total Annual Tax:</span>
                        <span className="text-green-600">{formatCurrency(permit.total_tax)}</span>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div>
                  <p className="text-sm text-gray-500 mb-3">Payment Summary</p>
                  <div className="space-y-3">
                    <div className="flex justify-between items-center">
                      <div className="flex items-center">
                        <CheckCircle className="w-4 h-4 text-green-500 mr-2" />
                        <span>Total Paid:</span>
                      </div>
                      <span className="font-medium text-green-600">{formatCurrency(totalPaid)}</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <div className="flex items-center">
                        <Clock className="w-4 h-4 text-yellow-500 mr-2" />
                        <span>Pending Balance:</span>
                      </div>
                      <span className="font-medium text-yellow-600">{formatCurrency(totalPending)}</span>
                    </div>
                    {totalPenalty > 0 && (
                      <div className="flex justify-between items-center">
                        <div className="flex items-center">
                          <AlertTriangle className="w-4 h-4 text-red-500 mr-2" />
                          <span>Total Penalty:</span>
                        </div>
                        <span className="font-medium text-red-600">{formatCurrency(totalPenalty)}</span>
                      </div>
                    )}
                  </div>
                </div>
              </div>
              
              <div>
                <div className="mb-6">
                  <p className="text-sm text-gray-500 mb-3">Collection Progress</p>
                  <div className="space-y-4">
                    <div className="flex items-center justify-between">
                      <span className="text-sm">Collection Rate</span>
                      <span className="text-lg font-bold text-blue-600">{collectionRate}%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-4">
                      <div 
                        className="bg-green-500 h-4 rounded-full transition-all duration-500"
                        style={{ width: `${collectionRate}%` }}
                      ></div>
                    </div>
                    <div className="text-center">
                      <p className="text-sm text-gray-600">
                        {totalPaid > 0 ? `${formatCurrency(totalPaid)} collected` : 'No payments yet'}
                      </p>
                    </div>
                  </div>
                </div>
                
                <div>
                  <p className="text-sm text-gray-500 mb-3">Quarterly Breakdown</p>
                  <div className="grid grid-cols-2 gap-4">
                    <div className="text-center p-3 bg-green-50 rounded-lg">
                      <div className="text-xl font-bold text-green-600">{quarterlyTaxes.filter(t => t.payment_status === 'paid').length}</div>
                      <div className="text-xs text-green-700">Paid Quarters</div>
                    </div>
                    <div className="text-center p-3 bg-yellow-50 rounded-lg">
                      <div className="text-xl font-bold text-yellow-600">{quarterlyTaxes.filter(t => t.payment_status === 'pending').length}</div>
                      <div className="text-xs text-yellow-700">Pending</div>
                    </div>
                    <div className="text-center p-3 bg-red-50 rounded-lg">
                      <div className="text-xl font-bold text-red-600">{quarterlyTaxes.filter(t => t.payment_status === 'overdue').length}</div>
                      <div className="text-xs text-red-700">Overdue</div>
                    </div>
                    <div className="text-center p-3 bg-blue-50 rounded-lg">
                      <div className="text-xl font-bold text-blue-600">{quarterlyTaxes.length}</div>
                      <div className="text-xs text-blue-700">Total Quarters</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Quarterly Taxes Table */}
        <div className="bg-white border rounded">
          <div className="px-6 py-4 border-b">
            <div className="flex items-center">
              <Calendar className="w-5 h-5 text-gray-600 mr-2" />
              <h2 className="text-lg font-semibold">Quarterly Tax Payments</h2>
            </div>
          </div>
          
          <div className="p-6">
            {quarterlyTaxes.length === 0 ? (
              <div className="text-center py-8 text-gray-500">
                <Calendar className="w-8 h-8 mx-auto mb-2 text-gray-400" />
                <p>No quarterly tax records found</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-4 py-3 text-left font-medium text-gray-600">Quarter</th>
                      <th className="px-4 py-3 text-left font-medium text-gray-600">Year</th>
                      <th className="px-4 py-3 text-left font-medium text-gray-600">Due Date</th>
                      <th className="px-4 py-3 text-left font-medium text-gray-600">Amount</th>
                      <th className="px-4 py-3 text-left font-medium text-gray-600">Penalty</th>
                      <th className="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                      <th className="px-4 py-3 text-left font-medium text-gray-600">Payment Date</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {quarterlyTaxes.map((tax) => {
                      const status = getPaymentStatus(tax.payment_status);
                      const StatusIcon = status.icon;
                      return (
                        <tr key={tax.id} className="hover:bg-gray-50">
                          <td className="px-4 py-3">
                            <span className="font-medium">{tax.quarter}</span>
                          </td>
                          <td className="px-4 py-3">{tax.year}</td>
                          <td className="px-4 py-3">{formatDate(tax.due_date)}</td>
                          <td className="px-4 py-3 font-medium">{formatCurrency(tax.total_quarterly_tax)}</td>
                          <td className="px-4 py-3">
                            {tax.penalty_amount > 0 ? (
                              <span className="text-red-600">{formatCurrency(tax.penalty_amount)}</span>
                            ) : (
                              <span className="text-gray-400">-</span>
                            )}
                          </td>
                          <td className="px-4 py-3">
                            <div className={`inline-flex items-center px-3 py-1 rounded-full ${status.bg}`}>
                              <StatusIcon className={`w-3 h-3 mr-1 ${status.color}`} />
                              <span className={`text-xs font-medium ${status.color}`}>{status.text}</span>
                            </div>
                          </td>
                          <td className="px-4 py-3">
                            {tax.payment_date ? formatDate(tax.payment_date) : '-'}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>

        {/* Footer Information */}
        <div className="bg-gray-50 border rounded p-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div>
              <p className="text-gray-500 font-medium mb-1">Record Information</p>
              <p className="text-gray-600">Created: {formatDate(permit.created_at)}</p>
              <p className="text-gray-600">Last Updated: {formatDate(permit.updated_at)}</p>
            </div>
            <div>
              <p className="text-gray-500 font-medium mb-1">Tax Information</p>
              <p className="text-gray-600">Pending Quarters: {permit.pending_quarters_count || 0}</p>
              <p className="text-gray-600">Total Quarters: {permit.total_quarters_count || 0}</p>
            </div>
            <div>
              <p className="text-gray-500 font-medium mb-1">System Notes</p>
              <p className="text-gray-600 text-xs">
                Quarterly taxes are due on the last day of each quarter. Late payments incur penalties.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}