import React, { useState, useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";

const MarketValidationInfo = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  
  const [application, setApplication] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  
  // Modal states
  const [showInterviewModal, setShowInterviewModal] = useState(false);
  const [showRejectModal, setShowRejectModal] = useState(false);
  const [showCorrectionModal, setShowCorrectionModal] = useState(false);
  const [showPaymentModal, setShowPaymentModal] = useState(false);
  const [showApproveModal, setShowApproveModal] = useState(false);
  const [showInterviewCompleteModal, setShowInterviewCompleteModal] = useState(false); // NEW
  
  // Form states
  const [interviewer, setInterviewer] = useState("");
  const [interviewDate, setInterviewDate] = useState("");
  const [interviewNotes, setInterviewNotes] = useState("");
  const [rejectionNotes, setRejectionNotes] = useState("");
  const [correctionNotes, setCorrectionNotes] = useState("");
  const [paymentReference, setPaymentReference] = useState("");
  const [paymentNotes, setPaymentNotes] = useState("");
  const [approvalNotes, setApprovalNotes] = useState("");
  const [interviewResultNotes, setInterviewResultNotes] = useState(""); // NEW
  
  const [actionLoading, setActionLoading] = useState(false);

  // Dynamic API base URL
  const getApiBaseUrl = () => {
    const isLocalhost = window.location.hostname === 'localhost' || 
                        window.location.hostname === '127.0.0.1';
    
    if (isLocalhost) {
      return 'http://localhost/revenue2/backend';
    } else {
      return 'https://revenuetreasury.goserveph.com/backend';
    }
  };

  // Fetch application data
  const fetchData = async () => {
    if (!id || id === "undefined") {
      setError("Invalid application ID");
      setLoading(false);
      return;
    }

    try {
      setLoading(true);
      setError(null);
      
      const API_BASE = getApiBaseUrl();
      const API_PATH = "/Market/MarketValidation";

      const response = await fetch(
        `${API_BASE}${API_PATH}/get_application_details.php?id=${id}`,
        {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
          }
        }
      );
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.status === 'success') {
        setApplication(data.data);
      } else {
        throw new Error(data.message || "Failed to fetch application");
      }
    } catch (err) {
      setError(err.message || "Failed to load data.");
    } finally {
      setLoading(false);
    }
  };

  // Generic API call function
  const callApi = async (endpoint, payload) => {
    setActionLoading(true);
    try {
      const API_BASE = getApiBaseUrl();
      const API_PATH = "/Market/MarketValidation";
      
      const response = await fetch(
        `${API_BASE}${API_PATH}/${endpoint}`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(payload)
        }
      );
      
      const result = await response.json();
      
      if (result.status === 'success') {
        return result;
      } else {
        throw new Error(result.message || "Failed to update");
      }
    } catch (error) {
      throw error;
    } finally {
      setActionLoading(false);
    }
  };

  // Handle Schedule Interview
  const handleScheduleInterview = async () => {
    if (!interviewer.trim()) {
      alert("⚠️ Please enter interviewer name");
      return;
    }
    
    try {
      await callApi("set_interview.php", {
        application_id: parseInt(application.id),
        interviewer: interviewer,
        interview_date: interviewDate || new Date().toISOString().slice(0, 16).replace('T', ' '),
        interview_notes: interviewNotes || ""
      });
      
      alert("✅ Interview scheduled!");
      setShowInterviewModal(false);
      setInterviewer("");
      setInterviewDate("");
      setInterviewNotes("");
      fetchData();
    } catch (error) {
      alert("❌ Failed to schedule interview: " + error.message);
    }
  };

  // Handle Mark Interview as Completed (Simple - just update status)
  const handleInterviewCompleted = async () => {
    try {
      await callApi("mark_interview_completed.php", { // NEW endpoint
        application_id: parseInt(application.id)
      });
      
      alert("✅ Interview marked as completed!");
      setShowInterviewCompleteModal(false);
      setInterviewer("");
      setInterviewResultNotes("");
      fetchData();
    } catch (error) {
      alert("❌ Failed to mark interview as completed: " + error.message);
    }
  };

  // Handle Need Correction
  const handleNeedCorrection = async () => {
    if (!correctionNotes.trim()) {
      alert("⚠️ Please enter correction notes");
      return;
    }
    
    try {
      await callApi("need_correction.php", {
        application_id: parseInt(application.id),
        correction_notes: correctionNotes
      });
      
      alert("✅ Application marked as needs correction!");
      setShowCorrectionModal(false);
      setCorrectionNotes("");
      fetchData();
    } catch (error) {
      alert("❌ Failed to mark for correction: " + error.message);
    }
  };

  // Handle Reject
  const handleReject = async () => {
    if (!rejectionNotes.trim()) {
      alert("⚠️ Please enter rejection reason");
      return;
    }
    
    try {
      await callApi("reject_application.php", {
        application_id: parseInt(application.id),
        remarks: rejectionNotes
      });
      
      alert("✅ Application rejected!");
      setShowRejectModal(false);
      setRejectionNotes("");
      fetchData();
    } catch (error) {
      alert("❌ Failed to reject application: " + error.message);
    }
  };

  // Handle Mark as Paying (from interviewed status)
  const handleMarkAsPaying = async () => {
    try {
      await callApi("proceed_to_payment.php", { // CHANGED endpoint name
        application_id: parseInt(application.id)
      });
      
      alert("✅ Application marked as ready for payment!");
      fetchData();
    } catch (error) {
      alert("❌ Failed to update status: " + error.message);
    }
  };

  // Handle Mark as Paid
  const handleMarkAsPaid = async () => {
    if (!paymentReference.trim()) {
      alert("⚠️ Please enter payment reference");
      return;
    }
    
    try {
      await callApi("mark_as_paid.php", {
        application_id: parseInt(application.id),
        reference_number: paymentReference,
        payment_notes: paymentNotes || ""
      });
      
      alert("✅ Payment recorded!");
      setShowPaymentModal(false);
      setPaymentReference("");
      setPaymentNotes("");
      fetchData();
    } catch (error) {
      alert("❌ Failed to record payment: " + error.message);
    }
  };

  // Handle Approve
  const handleApprove = async () => {
    try {
      await callApi("approve_application.php", {
        application_id: parseInt(application.id),
        approval_notes: approvalNotes || ""
      });
      
      alert("✅ Application approved!");
      setShowApproveModal(false);
      setApprovalNotes("");
      fetchData();
    } catch (error) {
      alert("❌ Failed to approve application: " + error.message);
    }
  };

  // Handle Resubmitted
  const handleMarkAsResubmitted = async () => {
    try {
      await callApi("mark_as_resubmitted.php", {
        application_id: parseInt(application.id)
      });
      
      alert("✅ Application marked as resubmitted!");
      fetchData();
    } catch (error) {
      alert("❌ Failed to update status: " + error.message);
    }
  };

  useEffect(() => {
    fetchData();
  }, [id]);

  // Format currency
  const formatCurrency = (amount) => {
    const num = parseFloat(amount) || 0;
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2
    }).format(num);
  };

  // Format date
  const formatDate = (dateString) => {
    if (!dateString || dateString === '0000-00-00 00:00:00' || dateString === '0000-00-00') {
      return 'N/A';
    }
    
    try {
      const date = new Date(dateString);
      
      if (isNaN(date.getTime())) {
        return 'Invalid Date';
      }
      
      return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
      });
    } catch (e) {
      return 'Date Error';
    }
  };

  // Get status color
  const getStatusColor = () => {
    const status = application?.application_status?.toLowerCase();
    switch (status) {
      case 'pending': return 'bg-yellow-50 border-yellow-200 text-yellow-800';
      case 'interview_scheduled': return 'bg-blue-50 border-blue-200 text-blue-800';
      case 'interviewed': return 'bg-green-50 border-green-200 text-green-800';
      case 'paying': return 'bg-purple-50 border-purple-200 text-purple-800';
      case 'paid': return 'bg-indigo-50 border-indigo-200 text-indigo-800';
      case 'need_correction': return 'bg-red-50 border-red-200 text-red-800';
      case 'resubmitted': return 'bg-orange-50 border-orange-200 text-orange-800';
      case 'approved': return 'bg-emerald-50 border-emerald-200 text-emerald-800';
      case 'rejected': return 'bg-gray-50 border-gray-200 text-gray-800';
      default: return 'bg-gray-50 border-gray-200 text-gray-800';
    }
  };

  // Get status icon
  const getStatusIcon = () => {
    const status = application?.application_status?.toLowerCase();
    switch (status) {
      case 'pending': return '⏳';
      case 'interview_scheduled': return '📅';
      case 'interviewed': return '✅';
      case 'paying': return '💰';
      case 'paid': return '💳';
      case 'need_correction': return '⚠️';
      case 'resubmitted': return '📄';
      case 'approved': return '🎉';
      case 'rejected': return '❌';
      default: return '📋';
    }
  };

  // Get status display text
  const getStatusText = () => {
    const status = application?.application_status?.toLowerCase();
    const statusMap = {
      'pending': 'Pending',
      'interview_scheduled': 'Interview Scheduled',
      'interviewed': 'Interview Completed',
      'paying': 'Payment Required',
      'paid': 'Payment Completed',
      'need_correction': 'Needs Correction',
      'resubmitted': 'Resubmitted',
      'approved': 'Approved',
      'rejected': 'Rejected'
    };
    return statusMap[status] || status || 'Unknown';
  };

  // Get document URL
  const getDocumentUrl = (filePath) => {
    if (!filePath) return "#";
    if (filePath.startsWith('http')) return filePath;
    
    const isLocalhost = window.location.hostname === 'localhost' || 
                        window.location.hostname === '127.0.0.1';
    
    if (isLocalhost) {
      return `http://localhost/revenue2/${filePath}`;
    } else {
      return `https://revenuetreasury.goserveph.com/${filePath}`;
    }
  };

  // Render action buttons based on status - UPDATED FOR interview_scheduled
  const renderActionButtons = () => {
    const status = application?.application_status?.toLowerCase();
    
    switch (status) {
      case 'pending':
        return (
          <div className="space-y-4">
            <button
              onClick={() => setShowInterviewModal(true)}
              disabled={actionLoading}
              className="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              📅 Schedule Interview
            </button>
            <button
              onClick={() => setShowCorrectionModal(true)}
              disabled={actionLoading}
              className="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              ⚠️ Needs Correction
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              ❌ Reject
            </button>
          </div>
        );
      
      case 'interview_scheduled': // NEW: Mark interview as completed
        return (
          <div className="space-y-4">
            <button
              onClick={handleInterviewCompleted}
              disabled={actionLoading}
              className="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              ✅ Mark Interview Completed
            </button>
            <button
              onClick={() => setShowCorrectionModal(true)}
              disabled={actionLoading}
              className="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              ⚠️ Needs Correction
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              ❌ Reject
            </button>
          </div>
        );
      
      case 'interviewed': // CHANGED: Show "Proceed to Payment" instead of "Mark as Paying"
        return (
          <div className="space-y-4">
            <button
              onClick={handleMarkAsPaying}
              disabled={actionLoading}
              className="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              💰 Proceed to Payment
            </button>
            <button
              onClick={() => setShowCorrectionModal(true)}
              disabled={actionLoading}
              className="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              ⚠️ Needs Correction
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              ❌ Reject
            </button>
          </div>
        );
      
      case 'paying': // CHANGED: Don't show "Mark as Paid" button - citizen will update to paid
        return (
          <div className="text-center py-4">
            <p className="text-gray-600 mb-4">Waiting for citizen to pay stall rights and security bond</p>
            <div className="bg-yellow-50 p-4 rounded-md mb-4">
              <p className="font-semibold">Total Amount Due:</p>
              <p className="text-xl font-bold text-blue-700">{formatCurrency(application.total_amount_due)}</p>
            </div>
            <button
              onClick={() => setShowCorrectionModal(true)}
              disabled={actionLoading}
              className="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-3 rounded-lg disabled:opacity-50 mb-3"
            >
              ⚠️ Needs Correction
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              ❌ Reject
            </button>
          </div>
        );
      
      case 'paid': // Show "Approve" button when citizen has paid
        return (
          <div className="space-y-4">
            <button
              onClick={() => setShowApproveModal(true)}
              disabled={actionLoading}
              className="w-full bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              🎉 Approve Application
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              ❌ Reject
            </button>
          </div>
        );
      
      case 'need_correction':
        return (
          <div className="space-y-4">
            <button
              onClick={handleMarkAsResubmitted}
              disabled={actionLoading}
              className="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              📄 Mark as Resubmitted
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              ❌ Reject
            </button>
          </div>
        );
      
      case 'resubmitted':
        return (
          <div className="space-y-4">
            <button
              onClick={() => setShowInterviewModal(true)}
              disabled={actionLoading}
              className="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              📅 Schedule Interview (Again)
            </button>
            <button
              onClick={() => setShowCorrectionModal(true)}
              disabled={actionLoading}
              className="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              ⚠️ Needs More Correction
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg disabled:opacity-50"
            >
              ❌ Reject
            </button>
          </div>
        );
      
      default:
        return (
          <div className="text-center py-4">
            <p className="text-gray-600">No actions available</p>
          </div>
        );
    }
  };

  // Render loading state
  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading application data...</p>
        </div>
      </div>
    );
  }

  // Render error state
  if (error || !application) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-lg shadow-md p-8 max-w-md w-full">
          <div className="text-red-500 text-4xl mb-4 text-center">⚠️</div>
          <h2 className="text-xl font-bold text-gray-900 mb-2 text-center">Error Loading Data</h2>
          <p className="text-gray-600 mb-6 text-center">{error || "Application not found"}</p>
          
          <div className="space-y-3">
            <button 
              onClick={() => navigate(-1)}
              className="w-full bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded"
            >
              Go Back
            </button>
            <button 
              onClick={fetchData}
              className="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded"
            >
              Try Again
            </button>
          </div>
        </div>
      </div>
    );
  }

  const status = application.application_status?.toLowerCase() || 'pending';
  const statusColor = getStatusColor();
  const statusIcon = getStatusIcon();
  const statusText = getStatusText();

  return (
    <div className='mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg'>
      {/* Header */}
      <div className="mb-8">
        <div className="flex justify-between items-center mb-4">
          <div>
            <button 
              onClick={() => navigate(-1)} 
              className="text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 mb-2"
            >
              ← Back to Applications
            </button>
            <h1 className="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">
              Application Review
            </h1>
            <p className="text-gray-600 dark:text-gray-300 mt-1">
              ID: <span className="font-medium text-blue-600 dark:text-blue-400">
                {application.stall_rights_no || application.id}
              </span>
            </p>
          </div>
          <button
            onClick={fetchData}
            disabled={loading || actionLoading}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            🔄 Refresh
          </button>
        </div>

        {/* Status Card */}
        <div className={`rounded-lg p-4 mb-6 border ${statusColor}`}>
          <div className="flex items-center">
            <div className={`p-3 rounded-lg mr-4 ${statusColor.replace('bg-', 'bg-').replace('border-', 'border-')}`}>
              <span className="text-2xl">{statusIcon}</span>
            </div>
            <div>
              <h3 className="font-bold text-lg">Application Status: {statusText.toUpperCase()}</h3>
              <p className="mt-1">
                {status === 'pending' && "This application is waiting for interview."}
                {status === 'interview_scheduled' && `Interview is scheduled for ${formatDate(application.interview_date)}.`}
                {status === 'interviewed' && "Interview completed. Ready for payment."}
                {status === 'paying' && "Waiting for citizen to pay stall rights and security bond."}
                {status === 'paid' && "Payment completed. Ready for final approval."}
                {status === 'need_correction' && "Application needs correction."}
                {status === 'resubmitted' && "Application has been resubmitted."}
                {status === 'approved' && "Application has been approved."}
                {status === 'rejected' && "Application has been rejected."}
              </p>
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Content */}
        <div className="lg:col-span-2 space-y-6">
          
          {/* Applicant Info */}
          <div className="bg-white rounded-lg border border-gray-200 p-6">
            <h2 className="text-xl font-bold mb-4">Applicant Information</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-gray-500">Full Name</p>
                <p className="font-semibold">
                  {application.first_name} {application.middle_name ? application.middle_name + ' ' : ''}{application.last_name}
                </p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Email</p>
                <p>{application.email}</p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Mobile</p>
                <p>{application.mobile}</p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Renter Code</p>
                <p className="font-mono text-blue-600">{application.renter_code}</p>
              </div>
              <div className="md:col-span-2">
                <p className="text-sm text-gray-500">Address</p>
                <p>
                  {application.house_number} {application.street}, {application.barangay}, {application.city}, {application.province} {application.zip_code}
                </p>
              </div>
            </div>
          </div>

          {/* Business Info */}
          <div className="bg-white rounded-lg border border-gray-200 p-6">
            <h2 className="text-xl font-bold mb-4">Business Information</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-gray-500">Business Name</p>
                <p className="font-semibold">{application.business_name || 'N/A'}</p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Business Type</p>
                <p>{application.business_type || 'N/A'}</p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Stall Name</p>
                <p className="font-semibold">{application.stall_name || 'N/A'}</p>
              </div>
              <div>
                <p className="text-sm text-gray-500">Stall Class</p>
                <p className="font-semibold text-blue-600">{application.stall_class || 'N/A'}</p>
              </div>
            </div>
          </div>

          {/* Financial Info */}
          <div className="bg-white rounded-lg border border-gray-200 p-6">
            <h2 className="text-xl font-bold mb-4">Financial Information</h2>
            <div className="space-y-3">
              <div className="flex justify-between">
                <span>Monthly Rent:</span>
                <span className="font-semibold">{formatCurrency(application.monthly_rent)}</span>
              </div>
              <div className="flex justify-between">
                <span>Stall Rights Fee:</span>
                <span className="font-semibold">{formatCurrency(application.stall_rights_amount)}</span>
              </div>
              <div className="flex justify-between">
                <span>Security Bond:</span>
                <span className="font-semibold">{formatCurrency(application.security_bond)}</span>
              </div>
              <div className="border-t border-gray-300 pt-3 mt-3">
                <div className="flex justify-between">
                  <span className="font-bold">Total Amount Due:</span>
                  <span className="font-bold text-blue-700 text-lg">{formatCurrency(application.total_amount_due)}</span>
                </div>
              </div>
              {application.payment_date && (
                <div className="border-t border-gray-300 pt-3 mt-3">
                  <div className="flex justify-between">
                    <span className="font-semibold">Payment Date:</span>
                    <span>{formatDate(application.payment_date)}</span>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Documents */}
          <div className="bg-white rounded-lg border border-gray-200 p-6">
            <h2 className="text-xl font-bold mb-4">Uploaded Documents</h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {application.barangay_clearance && (
                <div className="border border-gray-200 rounded-lg p-4">
                  <div className="flex items-center mb-3">
                    <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                      📄
                    </div>
                    <div>
                      <h3 className="font-semibold">Barangay Clearance</h3>
                    </div>
                  </div>
                  <button
                    onClick={() => window.open(getDocumentUrl(application.barangay_clearance), '_blank')}
                    className="w-full bg-gray-100 hover:bg-gray-200 py-2 rounded"
                  >
                    View Document
                  </button>
                </div>
              )}
              
              {application.id_photo_2x2 && (
                <div className="border border-gray-200 rounded-lg p-4">
                  <div className="flex items-center mb-3">
                    <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                      📸
                    </div>
                    <div>
                      <h3 className="font-semibold">2x2 ID Photo</h3>
                    </div>
                  </div>
                  <button
                    onClick={() => window.open(getDocumentUrl(application.id_photo_2x2), '_blank')}
                    className="w-full bg-gray-100 hover:bg-gray-200 py-2 rounded"
                  >
                    View Photo
                  </button>
                </div>
              )}
              
              {application.valid_id && (
                <div className="border border-gray-200 rounded-lg p-4">
                  <div className="flex items-center mb-3">
                    <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                      🪪
                    </div>
                    <div>
                      <h3 className="font-semibold">Valid ID</h3>
                    </div>
                  </div>
                  <button
                    onClick={() => window.open(getDocumentUrl(application.valid_id), '_blank')}
                    className="w-full bg-gray-100 hover:bg-gray-200 py-2 rounded"
                  >
                    View ID
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Action Panel */}
        <div className="lg:col-span-1">
          <div className="bg-blue-50 rounded-lg border border-blue-200 p-6 sticky top-6">
            <h2 className="text-xl font-bold mb-4">Admin Actions</h2>
            
            {renderActionButtons()}

            <div className="mt-8 pt-6 border-t border-gray-200">
              <h3 className="font-semibold mb-3">Application Details</h3>
              <div className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <span>Application ID:</span>
                  <span className="font-mono font-semibold">{application.stall_rights_no || application.id}</span>
                </div>
                <div className="flex justify-between">
                  <span>Renter Code:</span>
                  <span className="font-semibold">{application.renter_code}</span>
                </div>
                <div className="flex justify-between">
                  <span>Stall Class:</span>
                  <span className="font-semibold">{application.stall_class}</span>
                </div>
                <div className="flex justify-between">
                  <span>Submitted:</span>
                  <span>{formatDate(application.created_at)}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Schedule Interview Modal */}
      {showInterviewModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg font-semibold">Schedule Interview</h3>
                <button 
                  onClick={() => setShowInterviewModal(false)} 
                  className="text-gray-400 hover:text-gray-600"
                  disabled={actionLoading}
                >
                  ✕
                </button>
              </div>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-1">
                    Interviewer Name *
                  </label>
                  <input 
                    type="text" 
                    value={interviewer}
                    onChange={(e) => setInterviewer(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    placeholder="Enter your name"
                    disabled={actionLoading}
                    required
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-1">
                    Interview Date & Time *
                  </label>
                  <input 
                    type="datetime-local" 
                    value={interviewDate}
                    onChange={(e) => setInterviewDate(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    disabled={actionLoading}
                    required
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-1">
                    Interview Notes (Optional)
                  </label>
                  <textarea 
                    value={interviewNotes}
                    onChange={(e) => setInterviewNotes(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md h-24"
                    placeholder="Enter any notes..."
                    disabled={actionLoading}
                  />
                </div>
                
                <div className="flex gap-3 pt-4">
                  <button 
                    onClick={handleScheduleInterview} 
                    disabled={actionLoading || !interviewer.trim() || !interviewDate}
                    className="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded disabled:opacity-50"
                  >
                    {actionLoading ? 'Processing...' : 'Schedule Interview'}
                  </button>
                  <button 
                    onClick={() => setShowInterviewModal(false)} 
                    disabled={actionLoading}
                    className="flex-1 bg-gray-300 hover:bg-gray-400 py-2 rounded"
                  >
                    Cancel
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Correction Modal */}
      {showCorrectionModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg font-semibold">Mark as Needs Correction</h3>
                <button 
                  onClick={() => setShowCorrectionModal(false)} 
                  className="text-gray-400 hover:text-gray-600"
                  disabled={actionLoading}
                >
                  ✕
                </button>
              </div>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-1">
                    Correction Notes *
                  </label>
                  <textarea 
                    value={correctionNotes}
                    onChange={(e) => setCorrectionNotes(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md h-32"
                    placeholder="Explain what needs to be corrected..."
                    disabled={actionLoading}
                    required
                  />
                </div>
                <div className="flex gap-3 pt-4">
                  <button 
                    onClick={handleNeedCorrection} 
                    disabled={actionLoading || !correctionNotes.trim()}
                    className="flex-1 bg-yellow-600 hover:bg-yellow-700 text-white py-2 rounded disabled:opacity-50"
                  >
                    {actionLoading ? 'Processing...' : 'Mark Needs Correction'}
                  </button>
                  <button 
                    onClick={() => setShowCorrectionModal(false)} 
                    disabled={actionLoading}
                    className="flex-1 bg-gray-300 hover:bg-gray-400 py-2 rounded"
                  >
                    Cancel
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Approve Modal */}
      {showApproveModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg font-semibold">Approve Application</h3>
                <button 
                  onClick={() => setShowApproveModal(false)} 
                  className="text-gray-400 hover:text-gray-600"
                  disabled={actionLoading}
                >
                  ✕
                </button>
              </div>
              <div className="space-y-4">
                <div className="bg-green-50 p-4 rounded-md">
                  <p className="font-semibold">Application Summary:</p>
                  <p className="mt-1">• Applicant: {application.first_name} {application.last_name}</p>
                  <p>• Stall: {application.stall_name}</p>
                  <p>• Amount Paid: {formatCurrency(application.total_amount_due)}</p>
                </div>
                <div className="flex gap-3 pt-4">
                  <button 
                    onClick={handleApprove} 
                    disabled={actionLoading}
                    className="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded disabled:opacity-50"
                  >
                    {actionLoading ? 'Processing...' : 'Approve Application'}
                  </button>
                  <button 
                    onClick={() => setShowApproveModal(false)} 
                    disabled={actionLoading}
                    className="flex-1 bg-gray-300 hover:bg-gray-400 py-2 rounded"
                  >
                    Cancel
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Reject Modal */}
      {showRejectModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg font-semibold">Reject Application</h3>
                <button 
                  onClick={() => setShowRejectModal(false)} 
                  className="text-gray-400 hover:text-gray-600"
                  disabled={actionLoading}
                >
                  ✕
                </button>
              </div>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-1">
                    Rejection Reason *
                  </label>
                  <textarea 
                    value={rejectionNotes}
                    onChange={(e) => setRejectionNotes(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md h-32"
                    placeholder="Explain why this application is being rejected..."
                    disabled={actionLoading}
                    required
                  />
                </div>
                <div className="flex gap-3 pt-4">
                  <button 
                    onClick={handleReject} 
                    disabled={actionLoading || !rejectionNotes.trim()}
                    className="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 rounded disabled:opacity-50"
                  >
                    {actionLoading ? 'Processing...' : 'Reject Application'}
                  </button>
                  <button 
                    onClick={() => setShowRejectModal(false)} 
                    disabled={actionLoading}
                    className="flex-1 bg-gray-300 hover:bg-gray-400 py-2 rounded"
                  >
                    Cancel
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default MarketValidationInfo;