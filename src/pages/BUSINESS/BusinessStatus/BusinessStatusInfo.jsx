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
  Eye,
  ChevronRight
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
        `${API_BASE}/Business/BusinessStatus/get_permit_by_id.php?id=${id}`,
        { 
          method: 'GET',
          credentials: "include",
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          }
        }
      );
      
      const data = await res.json();
      
      if (data.status === "success") {
        setPermit(data.data.permit);
        setQuarterlyTaxes(data.data.quarterlyTaxes || []);
      }
    } catch (err) {
      console.error("Error:", err);
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
    if (!dateString) return "Not set";
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
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

  const getOverallStatus = () => {
    if (!permit) return { text: "Loading", color: "text-gray-600", bg: "bg-gray-100" };
    
    const paidCount = quarterlyTaxes.filter(tax => tax.payment_status === 'paid').length;
    const totalCount = quarterlyTaxes.length;
    
    if (paidCount === totalCount && totalCount > 0) {
      return { text: "Fully Paid", color: "text-green-600", bg: "bg-green-100" };
    } else if (quarterlyTaxes.some(tax => tax.payment_status === 'overdue')) {
      return { text: "Has Overdue", color: "text-red-600", bg: "bg-red-100" };
    } else if (paidCount > 0) {
      return { text: "Partially Paid", color: "text-blue-600", bg: "bg-blue-100" };
    } else {
      return { text: "No Payments", color: "text-gray-600", bg: "bg-gray-100" };
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

  const overallStatus = getOverallStatus();
  const paidTaxes = quarterlyTaxes.filter(tax => tax.payment_status === 'paid');
  const totalPaid = paidTaxes.reduce((sum, tax) => sum + (parseFloat(tax.total_quarterly_tax) || 0), 0);
  const collectionRate = permit.total_tax > 0 ? Math.round((totalPaid / permit.total_tax) * 100) : 0;

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Simple Header */}
      <div className="bg-white border-b">
        <div className="max-w-6xl mx-auto px-4 py-4">
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
                <p className="text-sm text-gray-600">Business Permit Details</p>
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
      <div className="max-w-6xl mx-auto px-4 py-6">
        {/* Quick Stats */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div className="bg-white p-4 border rounded">
            <p className="text-sm text-gray-500">Permit ID</p>
            <p className="font-semibold text-gray-900">{permit.business_permit_id}</p>
          </div>
          
          <div className="bg-white p-4 border rounded">
            <p className="text-sm text-gray-500">Annual Tax</p>
            <p className="font-semibold text-green-600">{formatCurrency(permit.total_tax)}</p>
          </div>
          
          <div className="bg-white p-4 border rounded">
            <p className="text-sm text-gray-500">Collection Rate</p>
            <p className="font-semibold text-blue-600">{collectionRate}%</p>
          </div>
          
          <div className="bg-white p-4 border rounded">
            <p className="text-sm text-gray-500">Status</p>
            <span className={`inline-block px-3 py-1 text-sm rounded-full ${overallStatus.bg} ${overallStatus.color}`}>
              {overallStatus.text}
            </span>
          </div>
        </div>

        {/* Business Information */}
        <div className="bg-white border rounded mb-6">
          <div className="px-6 py-4 border-b">
            <h2 className="text-lg font-semibold">Business Information</h2>
          </div>
          
          <div className="p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="space-y-4">
                <div>
                  <p className="text-sm text-gray-500 mb-1">Owner Name</p>
                  <p className="font-medium">{permit.owner_name}</p>
                </div>
                
                <div>
                  <p className="text-sm text-gray-500 mb-1">Business Type</p>
                  <div className="flex space-x-2">
                    <span className="px-3 py-1 bg-gray-100 text-gray-800 text-sm rounded">
                      {permit.business_type}
                    </span>
                    <span className="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded">
                      {permit.tax_calculation_type === 'capital_investment' ? 'Capital' : 'Gross Sales'}
                    </span>
                  </div>
                </div>
                
                <div>
                  <p className="text-sm text-gray-500 mb-1">Contact</p>
                  <div className="space-y-1">
                    {permit.contact_number && (
                      <div className="flex items-center">
                        <Phone className="w-4 h-4 text-gray-400 mr-2" />
                        <span>{permit.contact_number}</span>
                      </div>
                    )}
                    {permit.owner_email && (
                      <div className="flex items-center">
                        <Mail className="w-4 h-4 text-gray-400 mr-2" />
                        <span>{permit.owner_email}</span>
                      </div>
                    )}
                  </div>
                </div>
              </div>
              
              <div className="space-y-4">
                <div>
                  <p className="text-sm text-gray-500 mb-1">Location</p>
                  <div className="space-y-1">
                    <div className="flex items-center">
                      <MapPin className="w-4 h-4 text-gray-400 mr-2" />
                      <span>{permit.street}</span>
                    </div>
                    <p className="text-gray-600 text-sm ml-6">
                      Brgy. {permit.barangay}, {permit.city}, {permit.province}
                    </p>
                  </div>
                </div>
                
                <div>
                  <p className="text-sm text-gray-500 mb-1">Permit Dates</p>
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

        {/* Tax Summary */}
        <div className="bg-white border rounded mb-6">
          <div className="px-6 py-4 border-b">
            <h2 className="text-lg font-semibold">Tax Summary</h2>
          </div>
          
          <div className="p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <div className="mb-4">
                  <p className="text-sm text-gray-500 mb-1">Tax Calculation</p>
                  <div className="space-y-2">
                    <div className="flex justify-between">
                      <span>Basic Tax:</span>
                      <span className="font-medium">{formatCurrency(permit.tax_amount)}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Regulatory Fees:</span>
                      <span className="font-medium">{formatCurrency(permit.regulatory_fees)}</span>
                    </div>
                    <div className="pt-2 border-t">
                      <div className="flex justify-between font-semibold">
                        <span>Total Annual Tax:</span>
                        <span className="text-green-600">{formatCurrency(permit.total_tax)}</span>
                      </div>
                    </div>
                  </div>
                </div>
                
                {permit.taxable_amount > 0 && (
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Tax Basis</p>
                    <p>
                      {permit.tax_calculation_type === 'capital_investment' 
                        ? `Capital Investment: ${formatCurrency(permit.taxable_amount)}`
                        : `Gross Sales Tax Rate: ${permit.tax_rate || 0}%`
                      }
                    </p>
                  </div>
                )}
              </div>
              
              <div>
                <div className="mb-4">
                  <p className="text-sm text-gray-500 mb-1">Payment Summary</p>
                  <div className="space-y-2">
                    <div className="flex justify-between">
                      <span>Paid Amount:</span>
                      <span className="text-green-600 font-medium">{formatCurrency(totalPaid)}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Pending Amount:</span>
                      <span className="text-yellow-600 font-medium">{formatCurrency(permit.total_tax - totalPaid)}</span>
                    </div>
                    {permit.total_penalty > 0 && (
                      <div className="flex justify-between">
                        <span>Penalties:</span>
                        <span className="text-red-600 font-medium">{formatCurrency(permit.total_penalty)}</span>
                      </div>
                    )}
                  </div>
                </div>
                
                <div>
                  <p className="text-sm text-gray-500 mb-1">Collection Progress</p>
                  <div className="flex items-center space-x-3">
                    <div className="flex-1 bg-gray-200 rounded-full h-2">
                      <div 
                        className="bg-green-500 h-2 rounded-full" 
                        style={{ width: `${collectionRate}%` }}
                      ></div>
                    </div>
                    <span className="text-sm font-medium">{collectionRate}%</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Quarterly Taxes */}
        <div className="bg-white border rounded">
          <div className="px-6 py-4 border-b">
            <div className="flex justify-between items-center">
              <h2 className="text-lg font-semibold">Quarterly Taxes</h2>
              <span className="text-sm text-gray-500">
                {quarterlyTaxes.filter(t => t.payment_status === 'paid').length} of {quarterlyTaxes.length} paid
              </span>
            </div>
          </div>
          
          <div className="p-6">
            {quarterlyTaxes.length === 0 ? (
              <div className="text-center py-8 text-gray-500">
                <Calendar className="w-8 h-8 mx-auto mb-2 text-gray-400" />
                <p>No quarterly taxes recorded</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-4 py-3 text-left text-sm font-medium text-gray-600">Quarter</th>
                      <th className="px-4 py-3 text-left text-sm font-medium text-gray-600">Due Date</th>
                      <th className="px-4 py-3 text-left text-sm font-medium text-gray-600">Amount</th>
                      <th className="px-4 py-3 text-left text-sm font-medium text-gray-600">Penalty</th>
                      <th className="px-4 py-3 text-left text-sm font-medium text-gray-600">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {quarterlyTaxes.map((tax) => {
                      const status = getPaymentStatus(tax.payment_status);
                      const StatusIcon = status.icon;
                      return (
                        <tr key={tax.id} className="hover:bg-gray-50">
                          <td className="px-4 py-3">
                            <div className="font-medium">{tax.quarter} {tax.year}</div>
                          </td>
                          <td className="px-4 py-3 text-gray-600">{formatDate(tax.due_date)}</td>
                          <td className="px-4 py-3 font-medium">{formatCurrency(tax.total_quarterly_tax)}</td>
                          <td className="px-4 py-3">
                            {tax.penalty_amount > 0 ? (
                              <span className="text-red-600">{formatCurrency(tax.penalty_amount)}</span>
                            ) : (
                              <span className="text-gray-400">-</span>
                            )}
                          </td>
                          <td className="px-4 py-3">
                            <div className={`inline-flex items-center px-3 py-1 rounded-full text-sm ${status.bg}`}>
                              <StatusIcon className={`w-3 h-3 mr-1 ${status.color}`} />
                              <span className={status.color}>{status.text}</span>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
            
            {/* Quick Summary */}
            {quarterlyTaxes.length > 0 && (
              <div className="mt-6 pt-6 border-t">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div className="text-center">
                    <div className="text-2xl font-bold text-green-600">
                      {quarterlyTaxes.filter(t => t.payment_status === 'paid').length}
                    </div>
                    <div className="text-sm text-gray-600">Paid Quarters</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-yellow-600">
                      {quarterlyTaxes.filter(t => t.payment_status === 'pending').length}
                    </div>
                    <div className="text-sm text-gray-600">Pending</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-red-600">
                      {quarterlyTaxes.filter(t => t.payment_status === 'overdue').length}
                    </div>
                    <div className="text-sm text-gray-600">Overdue</div>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Footer Notes */}
        <div className="mt-6 text-sm text-gray-500">
          <p><strong>LGU Notes:</strong> Quarterly taxes are due on the last day of each quarter. Late payments incur 2% monthly penalty.</p>
          <p className="mt-1">Record updated: {formatDate(permit.updated_at)}</p>
        </div>
      </div>
    </div>
  );
}