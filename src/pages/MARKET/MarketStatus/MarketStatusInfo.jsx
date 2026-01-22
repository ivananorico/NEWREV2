import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  ArrowLeft,
  User,
  Mail,
  Phone,
  MapPin,
  Home,
  Building,
  DollarSign,
  Calendar,
  FileText,
  ShieldCheck,
  Store,
  BarChart3,
  Clock,
  CheckCircle,
  AlertCircle,
  Printer,
  Edit,
  Receipt,
  CreditCard,
  Users,
  Briefcase,
  Package,
  Layers,
  Tag,
  ClipboardCheck,
  Building2,
  Banknote,
  Wallet,
  TrendingUp,
  AlertTriangle
} from 'lucide-react';

export default function MarketStatusInfo() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [loading, setLoading] = useState(true);
  const [citizen, setCitizen] = useState(null);
  const [billingHistory, setBillingHistory] = useState([]);
  const [paymentLogs, setPaymentLogs] = useState([]);

  const API_BASE = window.location.hostname.includes('goserveph.com') 
    ? "/backend/Market/MarketStatus"
    : "http://localhost/revenue2/backend/Market/MarketStatus";

  useEffect(() => {
    if (id) {
      loadCitizenDetails();
    }
  }, [id]);

  const loadCitizenDetails = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${API_BASE}/citizen_details.php?renter_code=${id}`);
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();
      
      if (data.status === 'success') {
        setCitizen(data.citizen);
        setBillingHistory(data.billing_history || []);
        setPaymentLogs(data.payment_logs || []);
      }
    } catch (error) {
      console.error('Error:', error);
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (amount) => {
    const num = parseFloat(amount) || 0;
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2
    }).format(num);
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    } catch (e) {
      return dateString;
    }
  };

  const getStatusBadge = (status) => {
    switch(status?.toLowerCase()) {
      case 'active':
        return { text: 'Active', color: 'text-green-600', bg: 'bg-green-50', icon: CheckCircle };
      case 'pending':
        return { text: 'Pending', color: 'text-yellow-600', bg: 'bg-yellow-50', icon: Clock };
      case 'approved':
        return { text: 'Approved', color: 'text-blue-600', bg: 'bg-blue-50', icon: ShieldCheck };
      case 'inactive':
        return { text: 'Inactive', color: 'text-gray-600', bg: 'bg-gray-50', icon: AlertCircle };
      default:
        return { text: status || 'N/A', color: 'text-gray-600', bg: 'bg-gray-50', icon: AlertCircle };
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

  const calculatePaymentSummary = () => {
    if (!billingHistory.length) return { paid: 0, pending: 0, overdue: 0, total: 0 };
    const paid = billingHistory.filter(bill => bill.payment_status === 'paid').length;
    const pending = billingHistory.filter(bill => bill.payment_status === 'pending').length;
    const overdue = billingHistory.filter(bill => bill.payment_status === 'overdue').length;
    return { paid, pending, overdue, total: billingHistory.length };
  };

  const handlePrint = () => {
    window.print();
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-3 text-gray-600">Loading citizen details...</p>
        </div>
      </div>
    );
  }

  if (!citizen) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-lg p-6 text-center">
          <AlertCircle className="w-12 h-12 text-red-400 mx-auto mb-3" />
          <h2 className="text-lg font-semibold text-gray-900 mb-2">Citizen not found</h2>
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

  const statusBadge = getStatusBadge(citizen.status);
  const StatusIcon = statusBadge.icon;
  const paymentSummary = calculatePaymentSummary();
  const totalPaid = paymentLogs.reduce((sum, log) => sum + parseFloat(log.amount_paid || 0), 0);
  const collectionRate = citizen.monthly_totals > 0 ? Math.round((totalPaid / citizen.monthly_totals) * 100) : 0;
  const contractMonths = citizen.contract_months || 0;
  const remainingBalance = parseFloat(citizen.monthly_totals || 0) - totalPaid;

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
                <h1 className="text-xl font-bold text-gray-900">{citizen.full_name || 'Unknown Citizen'}</h1>
                <div className="flex items-center space-x-3 mt-1">
                  <p className="text-sm text-gray-600">Renter Code: {citizen.renter_code}</p>
                  <span className="text-gray-300">•</span>
                  <div className={`inline-flex items-center px-3 py-1 rounded-full ${statusBadge.bg} ${statusBadge.color}`}>
                    <StatusIcon className="w-3 h-3 mr-1" />
                    <span className="text-sm font-medium">{statusBadge.text}</span>
                  </div>
                </div>
              </div>
            </div>
            <div className="flex items-center space-x-2">
              <button
                onClick={handlePrint}
                className="flex items-center px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded"
              >
                <Printer className="w-4 h-4 mr-1" />
                Print Record
              </button>
              <button className="flex items-center px-3 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">
                <Edit className="w-4 h-4 mr-1" />
                Edit Info
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
            <p className="text-sm text-gray-500">Monthly Rent</p>
            <div className="flex items-center mt-1">
              <DollarSign className="w-4 h-4 text-blue-500 mr-2" />
              <p className="text-lg font-bold text-blue-600">{formatCurrency(citizen.monthly_rent)}</p>
            </div>
            <p className="text-xs text-gray-500 mt-1">per month</p>
          </div>
          
          <div className="bg-white p-4 border rounded">
            <p className="text-sm text-gray-500">Contract Value</p>
            <div className="flex items-center mt-1">
              <BarChart3 className="w-4 h-4 text-green-500 mr-2" />
              <p className="text-lg font-bold text-green-600">{formatCurrency(citizen.monthly_totals)}</p>
            </div>
            <p className="text-xs text-gray-500 mt-1">{contractMonths} months</p>
          </div>
          
          <div className="bg-white p-4 border rounded">
            <p className="text-sm text-gray-500">Total Collected</p>
            <div className="flex items-center mt-1">
              <Wallet className="w-4 h-4 text-purple-500 mr-2" />
              <p className="text-lg font-bold text-purple-600">{formatCurrency(totalPaid)}</p>
            </div>
            <p className="text-xs text-gray-500 mt-1">{paymentSummary.paid} months paid</p>
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
            <p className="text-xs text-gray-500 mt-1">Payment completion</p>
          </div>
        </div>

        {/* Personal Information */}
        <div className="bg-white border rounded">
          <div className="px-6 py-4 border-b">
            <div className="flex items-center">
              <User className="w-5 h-5 text-gray-600 mr-2" />
              <h2 className="text-lg font-semibold">Personal Information</h2>
            </div>
          </div>
          
          <div className="p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="space-y-4">
                <div>
                  <p className="text-sm text-gray-500 mb-1">Full Name</p>
                  <p className="font-medium text-lg">{citizen.full_name}</p>
                </div>
                
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Gender</p>
                    <p className="font-medium">{getGenderText(citizen.gender)}</p>
                  </div>
                  
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Birth Date</p>
                    <p className="font-medium">{formatDate(citizen.birth_date)}</p>
                  </div>
                </div>
                
                <div>
                  <p className="text-sm text-gray-500 mb-1">Contact Information</p>
                  <div className="space-y-2">
                    {citizen.email && (
                      <div className="flex items-center">
                        <Mail className="w-4 h-4 text-gray-400 mr-2" />
                        <span>{citizen.email}</span>
                      </div>
                    )}
                    {citizen.mobile && (
                      <div className="flex items-center">
                        <Phone className="w-4 h-4 text-gray-400 mr-2" />
                        <span>{citizen.mobile}</span>
                      </div>
                    )}
                    {citizen.telephone && (
                      <div className="flex items-center">
                        <Phone className="w-4 h-4 text-gray-400 mr-2" />
                        <span>{citizen.telephone}</span>
                      </div>
                    )}
                  </div>
                </div>
              </div>
              
              <div className="space-y-4">
                <div>
                  <p className="text-sm text-gray-500 mb-1">Residential Address</p>
                  <div className="space-y-1">
                    {citizen.house_number && (
                      <p className="font-medium">{citizen.house_number}</p>
                    )}
                    {citizen.street && (
                      <p className="font-medium">{citizen.street}</p>
                    )}
                    <p className="text-gray-600">
                      Brgy. {citizen.barangay || 'N/A'}, {citizen.city || 'N/A'}, {citizen.province || 'N/A'}
                    </p>
                    {citizen.zip_code && (
                      <p className="text-gray-500 text-sm">ZIP: {citizen.zip_code}</p>
                    )}
                  </div>
                </div>
                
                {citizen.emergency_name && (
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Emergency Contact</p>
                    <div className="space-y-1">
                      <p className="font-medium">{citizen.emergency_name}</p>
                      {citizen.emergency_contact && (
                        <p className="text-gray-600">{citizen.emergency_contact}</p>
                      )}
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Business & Contract Information */}
        <div className="bg-white border rounded">
          <div className="px-6 py-4 border-b">
            <div className="flex items-center">
              <Building2 className="w-5 h-5 text-gray-600 mr-2" />
              <h2 className="text-lg font-semibold">Business & Contract Information</h2>
            </div>
          </div>
          
          <div className="p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="space-y-4">
                <div>
                  <p className="text-sm text-gray-500 mb-1">Business Name</p>
                  <p className="font-medium text-lg">{citizen.business_name || 'Not specified'}</p>
                </div>
                
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Business Type</p>
                    <div className="flex items-center">
                      <Package className="w-4 h-4 text-gray-400 mr-1" />
                      <span className="font-medium">{citizen.business_type || 'N/A'}</span>
                    </div>
                  </div>
                  
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Stall Class</p>
                    <div className="flex items-center">
                      <Layers className="w-4 h-4 text-gray-400 mr-1" />
                      <span className="font-medium">{citizen.class_name || 'N/A'}</span>
                    </div>
                  </div>
                </div>
                
                <div>
                  <p className="text-sm text-gray-500 mb-1">Stall Details</p>
                  <div className="space-y-2">
                    <div className="flex items-center">
                      <Tag className="w-4 h-4 text-gray-400 mr-2" />
                      <span className="font-medium">Stall: {citizen.stall_name || citizen.stall_rights_no || 'N/A'}</span>
                    </div>
                    <div className="flex items-center">
                      <ClipboardCheck className="w-4 h-4 text-gray-400 mr-2" />
                      <span>Rights No: {citizen.stall_rights_no || 'N/A'}</span>
                    </div>
                  </div>
                </div>
              </div>
              
              <div className="space-y-4">
                <div>
                  <p className="text-sm text-gray-500 mb-1">Contract Timeline</p>
                  <div className="space-y-3">
                    <div className="flex justify-between items-center">
                      <span className="text-gray-600">Contract Duration:</span>
                      <span className="font-medium">{contractMonths} months</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <span className="text-gray-600">Start Date:</span>
                      <span className="font-medium">{formatDate(citizen.contract_start)}</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <span className="text-gray-600">End Date:</span>
                      <span className="font-medium">{formatDate(citizen.contract_end)}</span>
                    </div>
                    <div className="pt-2 border-t">
                      <div className="flex justify-between items-center">
                        <span className="text-gray-600">Registration Date:</span>
                        <span className="font-medium">{formatDate(citizen.registration_date)}</span>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div className="p-3 bg-gray-50 rounded-lg">
                  <p className="text-sm text-gray-500 mb-2">Contract Summary</p>
                  <div className="space-y-2">
                    <div className="flex justify-between items-center">
                      <span>Total Contract Value:</span>
                      <span className="font-bold text-green-600">{formatCurrency(citizen.monthly_totals)}</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <span>Paid to Date:</span>
                      <span className="font-bold text-blue-600">{formatCurrency(totalPaid)}</span>
                    </div>
                    <div className="flex justify-between items-center border-t pt-2">
                      <span>Remaining Balance:</span>
                      <span className={`font-bold ${remainingBalance > 0 ? 'text-red-600' : 'text-green-600'}`}>
                        {formatCurrency(remainingBalance)}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Payment Summary */}
        <div className="bg-white border rounded">
          <div className="px-6 py-4 border-b">
            <div className="flex items-center">
              <CreditCard className="w-5 h-5 text-gray-600 mr-2" />
              <h2 className="text-lg font-semibold">Payment Summary</h2>
            </div>
          </div>
          
          <div className="p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <div className="mb-6">
                  <p className="text-sm text-gray-500 mb-3">Payment Status Overview</p>
                  <div className="grid grid-cols-2 gap-4">
                    <div className="text-center p-4 bg-green-50 rounded-lg">
                      <div className="text-2xl font-bold text-green-600">{paymentSummary.paid}</div>
                      <div className="text-xs text-green-700">Paid Months</div>
                    </div>
                    <div className="text-center p-4 bg-yellow-50 rounded-lg">
                      <div className="text-2xl font-bold text-yellow-600">{paymentSummary.pending}</div>
                      <div className="text-xs text-yellow-700">Pending</div>
                    </div>
                    <div className="text-center p-4 bg-red-50 rounded-lg">
                      <div className="text-2xl font-bold text-red-600">{paymentSummary.overdue}</div>
                      <div className="text-xs text-red-700">Overdue</div>
                    </div>
                    <div className="text-center p-4 bg-blue-50 rounded-lg">
                      <div className="text-2xl font-bold text-blue-600">{paymentSummary.total}</div>
                      <div className="text-xs text-blue-700">Total Months</div>
                    </div>
                  </div>
                </div>
                
                <div>
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
              </div>
              
              <div>
                <div className="mb-6">
                  <p className="text-sm text-gray-500 mb-3">Recent Payments</p>
                  {paymentLogs.length === 0 ? (
                    <div className="text-center py-4 text-gray-500">
                      <CreditCard className="w-8 h-8 mx-auto mb-2 text-gray-400" />
                      <p className="text-sm">No payment records found</p>
                    </div>
                  ) : (
                    <div className="space-y-2">
                      {paymentLogs.slice(0, 3).map((payment) => (
                        <div key={payment.id} className="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50">
                          <div>
                            <div className="flex items-center">
                              <Receipt className="w-4 h-4 text-green-500 mr-2" />
                              <span className="font-medium text-sm">{payment.receipt_number}</span>
                            </div>
                            <p className="text-xs text-gray-500 mt-1">{formatDate(payment.payment_date)}</p>
                          </div>
                          <div className="text-right">
                            <p className="font-bold text-green-600">{formatCurrency(payment.amount_paid)}</p>
                            <p className="text-xs text-gray-500">{payment.payment_method}</p>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
                
                <div>
                  <p className="text-sm text-gray-500 mb-3">Financial Summary</p>
                  <div className="space-y-3">
                    <div className="flex justify-between items-center">
                      <div className="flex items-center">
                        <DollarSign className="w-4 h-4 text-blue-500 mr-2" />
                        <span>Monthly Rent:</span>
                      </div>
                      <span className="font-medium text-blue-600">{formatCurrency(citizen.monthly_rent)}</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <div className="flex items-center">
                        <BarChart3 className="w-4 h-4 text-green-500 mr-2" />
                        <span>Total Contract:</span>
                      </div>
                      <span className="font-medium text-green-600">{formatCurrency(citizen.monthly_totals)}</span>
                    </div>
                    <div className="pt-3 border-t">
                      <div className="flex justify-between items-center font-semibold text-lg">
                        <div className="flex items-center">
                          <Wallet className="w-5 h-5 text-purple-500 mr-2" />
                          <span>Total Collected:</span>
                        </div>
                        <span className="text-purple-600">{formatCurrency(totalPaid)}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Billing History */}
        <div className="bg-white border rounded">
          <div className="px-6 py-4 border-b">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <Calendar className="w-5 h-5 text-gray-600 mr-2" />
                <h2 className="text-lg font-semibold">Billing History</h2>
              </div>
              <span className="text-sm text-gray-500">{billingHistory.length} records</span>
            </div>
          </div>
          
          <div className="p-6">
            {billingHistory.length === 0 ? (
              <div className="text-center py-8 text-gray-500">
                <Calendar className="w-8 h-8 mx-auto mb-2 text-gray-400" />
                <p>No billing records found</p>
                <p className="text-sm text-gray-400 mt-1">Monthly bills will appear here once generated</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-4 py-3 text-left font-medium text-gray-600">Month/Year</th>
                      <th className="px-4 py-3 text-left font-medium text-gray-600">Due Date</th>
                      <th className="px-4 py-3 text-left font-medium text-gray-600">Base Rent</th>
                      <th className="px-4 py-3 text-left font-medium text-gray-600">Penalty</th>
                      <th className="px-4 py-3 text-left font-medium text-gray-600">Discount</th>
                      <th className="px-4 py-3 text-left font-medium text-gray-600">Total Due</th>
                      <th className="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {billingHistory.map((bill) => {
                      const statusClass = bill.payment_status === 'paid' ? 'bg-green-100 text-green-800' :
                                        bill.payment_status === 'overdue' ? 'bg-red-100 text-red-800' :
                                        'bg-yellow-100 text-yellow-800';
                      return (
                        <tr key={bill.id} className="hover:bg-gray-50">
                          <td className="px-4 py-3">
                            {bill.billing_month && bill.billing_year 
                              ? `${new Date(bill.billing_year, bill.billing_month - 1).toLocaleDateString('en-US', { month: 'short' })} ${bill.billing_year}`
                              : 'N/A'}
                          </td>
                          <td className="px-4 py-3">{formatDate(bill.due_date)}</td>
                          <td className="px-4 py-3">{formatCurrency(bill.base_rent)}</td>
                          <td className="px-4 py-3">
                            {bill.penalty_amount > 0 ? (
                              <span className="text-red-600">{formatCurrency(bill.penalty_amount)}</span>
                            ) : (
                              <span className="text-gray-400">-</span>
                            )}
                          </td>
                          <td className="px-4 py-3">
                            {bill.discount_amount > 0 ? (
                              <span className="text-green-600">{formatCurrency(bill.discount_amount)}</span>
                            ) : (
                              <span className="text-gray-400">-</span>
                            )}
                          </td>
                          <td className="px-4 py-3 font-medium">{formatCurrency(bill.total_amount_due)}</td>
                          <td className="px-4 py-3">
                            <span className={`inline-block px-3 py-1 text-xs rounded-full ${statusClass}`}>
                              {bill.payment_status || 'pending'}
                            </span>
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
              <p className="text-gray-600">Created: {formatDate(citizen.renter_created_at)}</p>
              <p className="text-gray-600">Last Updated: {formatDate(citizen.renter_updated_at)}</p>
            </div>
            <div>
              <p className="text-gray-500 font-medium mb-1">Application Status</p>
              <p className="text-gray-600">Status: {citizen.application_status || 'N/A'}</p>
              <p className="text-gray-600">Interviewer: {citizen.interviewer || 'N/A'}</p>
            </div>
            <div>
              <p className="text-gray-500 font-medium mb-1">System Notes</p>
              <p className="text-gray-600 text-xs">
                Monthly rent is due on the 15th of each month. Late payments incur penalties.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}