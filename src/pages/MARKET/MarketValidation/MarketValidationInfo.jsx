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
  
  // Form states
  const [interviewer, setInterviewer] = useState("");
  const [interviewDate, setInterviewDate] = useState("");
  const [interviewNotes, setInterviewNotes] = useState("");
  const [rejectionNotes, setRejectionNotes] = useState("");
  const [correctionNotes, setCorrectionNotes] = useState("");
  const [paymentReference, setPaymentReference] = useState("");
  const [paymentNotes, setPaymentNotes] = useState("");
  const [approvalNotes, setApprovalNotes] = useState("");
  
  const [actionLoading, setActionLoading] = useState(false);
  const [showDocumentPreview, setShowDocumentPreview] = useState(null);
  const [previewUrl, setPreviewUrl] = useState("");

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
      alert("Please enter interviewer name");
      return;
    }
    
    try {
      await callApi("set_interview.php", {
        application_id: parseInt(application.id),
        interviewer: interviewer,
        interview_date: interviewDate || new Date().toISOString().slice(0, 16).replace('T', ' '),
        interview_notes: interviewNotes || ""
      });
      
      alert("Interview scheduled!");
      setShowInterviewModal(false);
      setInterviewer("");
      setInterviewDate("");
      setInterviewNotes("");
      fetchData();
    } catch (error) {
      alert("Failed to schedule interview: " + error.message);
    }
  };

  // Handle Mark Interview as Completed
  const handleInterviewCompleted = async () => {
    try {
      await callApi("mark_interview_completed.php", {
        application_id: parseInt(application.id)
      });
      
      alert("Interview marked as completed!");
      fetchData();
    } catch (error) {
      alert("Failed to mark interview as completed: " + error.message);
    }
  };

  // Handle Need Correction
  const handleNeedCorrection = async () => {
    if (!correctionNotes.trim()) {
      alert("Please enter correction notes");
      return;
    }
    
    try {
      await callApi("need_correction.php", {
        application_id: parseInt(application.id),
        correction_notes: correctionNotes
      });
      
      alert("Application marked as needs correction!");
      setShowCorrectionModal(false);
      setCorrectionNotes("");
      fetchData();
    } catch (error) {
      alert("Failed to mark for correction: " + error.message);
    }
  };

  // Handle Reject
  const handleReject = async () => {
    if (!rejectionNotes.trim()) {
      alert("Please enter rejection reason");
      return;
    }
    
    try {
      await callApi("reject_application.php", {
        application_id: parseInt(application.id),
        remarks: rejectionNotes
      });
      
      alert("Application rejected!");
      setShowRejectModal(false);
      setRejectionNotes("");
      fetchData();
    } catch (error) {
      alert("Failed to reject application: " + error.message);
    }
  };

  // Handle Mark as Paying (from interviewed status)
  const handleMarkAsPaying = async () => {
    try {
      await callApi("proceed_to_payment.php", {
        application_id: parseInt(application.id)
      });
      
      alert("Application marked as ready for payment!");
      fetchData();
    } catch (error) {
      alert("Failed to update status: " + error.message);
    }
  };

  // Handle Mark as Paid
  const handleMarkAsPaid = async () => {
    if (!paymentReference.trim()) {
      alert("Please enter payment reference");
      return;
    }
    
    try {
      await callApi("mark_as_paid.php", {
        application_id: parseInt(application.id),
        reference_number: paymentReference,
        payment_notes: paymentNotes || ""
      });
      
      alert("Payment recorded!");
      setShowPaymentModal(false);
      setPaymentReference("");
      setPaymentNotes("");
      fetchData();
    } catch (error) {
      alert("Failed to record payment: " + error.message);
    }
  };

  // Handle Approve
  const handleApprove = async () => {
    try {
      await callApi("approve_application.php", {
        application_id: parseInt(application.id),
        approval_notes: approvalNotes || ""
      });
      
      alert("Application approved!");
      setShowApproveModal(false);
      setApprovalNotes("");
      fetchData();
    } catch (error) {
      alert("Failed to approve application: " + error.message);
    }
  };

  // Handle Resubmitted
  const handleMarkAsResubmitted = async () => {
    try {
      await callApi("mark_as_resubmitted.php", {
        application_id: parseInt(application.id)
      });
      
      alert("Application marked as resubmitted!");
      fetchData();
    } catch (error) {
      alert("Failed to update status: " + error.message);
    }
  };

  // Handle document preview
  const handleDocumentPreview = (docType, filePath) => {
    if (!filePath) {
      alert("No document available");
      return;
    }
    
    const url = getDocumentUrl(filePath);
    setPreviewUrl(url);
    setShowDocumentPreview(docType);
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
      case 'pending': return 'bg-yellow-100 text-yellow-800';
      case 'interview_scheduled': return 'bg-blue-100 text-blue-800';
      case 'interviewed': return 'bg-green-100 text-green-800';
      case 'paying': return 'bg-purple-100 text-purple-800';
      case 'paid': return 'bg-indigo-100 text-indigo-800';
      case 'need_correction': return 'bg-red-100 text-red-800';
      case 'resubmitted': return 'bg-orange-100 text-orange-800';
      case 'approved': return 'bg-emerald-100 text-emerald-800';
      case 'rejected': return 'bg-gray-100 text-gray-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  // Get status display text
  const getStatusText = () => {
    const status = application?.application_status?.toLowerCase();
    const statusMap = {
      'pending': 'Pending Interview',
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

  // Get progress bar status based on current status
  const getProgressBarStatus = () => {
    const status = application?.application_status?.toLowerCase();
    
    // If status is need_correction or resubmitted, keep bar at pending
    if (status === 'need_correction' || status === 'resubmitted') {
      return 'pending';
    }
    
    return status;
  };

  // Render progress bar steps
  const renderProgressBar = () => {
    const currentStatus = getProgressBarStatus();
    const steps = [
      { key: 'pending', label: 'Pending' },
      { key: 'interview_scheduled', label: 'Sched Interview' },
      { key: 'interviewed', label: 'Interviewed' },
      { key: 'paying', label: 'Proceed Payment' },
      { key: 'paid', label: 'Paid' },
      { key: 'approved', label: 'Approve' }
    ];

    // Determine which step is active
    const stepIndex = steps.findIndex(step => step.key === currentStatus);
    const activeIndex = stepIndex === -1 ? 0 : stepIndex;

    return (
      <div className="mb-6">
        <div className="flex items-center justify-between mb-2">
          {steps.map((step, index) => (
            <div key={step.key} className="flex flex-col items-center w-full">
              <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm ${
                index <= activeIndex 
                  ? 'bg-blue-600 text-white' 
                  : 'bg-gray-200 text-gray-400'
              }`}>
                {index + 1}
              </div>
              <span className={`text-xs mt-1 text-center ${
                index <= activeIndex ? 'font-semibold text-blue-600' : 'text-gray-500'
              }`}>
                {step.label}
              </span>
            </div>
          ))}
        </div>
        <div className="relative h-1 bg-gray-200 rounded-full">
          <div 
            className="absolute h-full bg-blue-600 rounded-full transition-all duration-500"
            style={{ width: `${(activeIndex / (steps.length - 1)) * 100}%` }}
          ></div>
        </div>
      </div>
    );
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

  // Render action buttons based on status - CORRECTED VERSION
  const renderActionButtons = () => {
    const status = application?.application_status?.toLowerCase();
    
    switch (status) {
      // PENDING: Can schedule interview, mark as need correction, or reject
      case 'pending':
        return (
          <div className="space-y-2">
            <button
              onClick={() => setShowInterviewModal(true)}
              disabled={actionLoading}
              className="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Schedule Interview
            </button>
            <button
              onClick={() => setShowCorrectionModal(true)}
              disabled={actionLoading}
              className="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Needs Correction
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Reject
            </button>
          </div>
        );
      
      // INTERVIEW SCHEDULED: Can mark as interviewed or cancel interview (back to pending)
      case 'interview_scheduled':
        return (
          <div className="space-y-2">
            <button
              onClick={handleInterviewCompleted}
              disabled={actionLoading}
              className="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Mark as Interviewed
            </button>
            <button
              onClick={() => {
                if (window.confirm("Reset back to pending status?")) {
                  // Call API to reset to pending
                  handleResetToPending();
                }
              }}
              disabled={actionLoading}
              className="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Reset to Pending
            </button>
          </div>
        );
      
      // INTERVIEWED: Can proceed to payment, mark as need correction, or reject
      case 'interviewed':
        return (
          <div className="space-y-2">
            <button
              onClick={handleMarkAsPaying}
              disabled={actionLoading}
              className="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Proceed to Payment
            </button>
            <button
              onClick={() => setShowCorrectionModal(true)}
              disabled={actionLoading}
              className="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Needs Correction
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Reject
            </button>
          </div>
        );
      
      // PAYING: Can mark as paid, needs correction, or reject
      case 'paying':
        return (
          <div className="space-y-2">
            <div className="bg-yellow-50 p-3 rounded border border-yellow-200 mb-2">
              <p className="text-sm text-yellow-700 font-medium">Waiting for payment</p>
              <p className="text-lg font-bold text-blue-700">{formatCurrency(application.total_amount_due)}</p>
            </div>
            <button
              onClick={() => setShowPaymentModal(true)}
              disabled={actionLoading}
              className="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Mark as Paid
            </button>
            <button
              onClick={() => setShowCorrectionModal(true)}
              disabled={actionLoading}
              className="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Needs Correction
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Reject
            </button>
          </div>
        );
      
      // PAID: Can approve, needs correction, or reject
      case 'paid':
        return (
          <div className="space-y-2">
            <button
              onClick={() => setShowApproveModal(true)}
              disabled={actionLoading}
              className="w-full bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Approve Application
            </button>
            <button
              onClick={() => setShowCorrectionModal(true)}
              disabled={actionLoading}
              className="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Needs Correction
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Reject
            </button>
          </div>
        );
      
      // NEED CORRECTION: Can mark as resubmitted or reject
      case 'need_correction':
        return (
          <div className="space-y-2">
            <button
              onClick={handleMarkAsResubmitted}
              disabled={actionLoading}
              className="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Mark as Resubmitted
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Reject
            </button>
          </div>
        );
      
      // RESUBMITTED: Can schedule interview, needs correction, or reject
      case 'resubmitted':
        return (
          <div className="space-y-2">
            <button
              onClick={() => setShowInterviewModal(true)}
              disabled={actionLoading}
              className="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Schedule Interview
            </button>
            <button
              onClick={() => setShowCorrectionModal(true)}
              disabled={actionLoading}
              className="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Needs Correction
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded disabled:opacity-50"
            >
              Reject
            </button>
          </div>
        );
      
      // DEFAULT: No actions available
      default:
        return (
          <div className="text-center py-2">
            <p className="text-gray-500">No actions available</p>
          </div>
        );
    }
  };

  // Handle reset to pending (for interview_scheduled status)
  const handleResetToPending = async () => {
    try {
      await callApi("reset_to_pending.php", {
        application_id: parseInt(application.id)
      });
      
      alert("Application reset to pending!");
      fetchData();
    } catch (error) {
      alert("Failed to reset to pending: " + error.message);
    }
  };

  // Render loading state
  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading application data...</p>
        </div>
      </div>
    );
  }

  // Render error state
  if (error || !application) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded shadow p-6 max-w-md w-full">
          <div className="text-red-500 text-4xl mb-3 text-center">
            <i className="fas fa-exclamation-triangle"></i>
          </div>
          <h2 className="text-lg font-bold text-gray-900 mb-2 text-center">Error Loading Data</h2>
          <p className="text-gray-600 mb-4 text-center">{error || "Application not found"}</p>
          
          <div className="space-y-2">
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
  const statusText = getStatusText();

  return (
    <div className='mx-auto p-4 max-w-7xl'>
      {/* Header */}
      <div className="mb-6">
        <div className="flex flex-col md:flex-row md:items-center justify-between mb-4">
          <div>
            <button 
              onClick={() => navigate(-1)} 
              className="text-blue-600 hover:text-blue-800 mb-3 inline-flex items-center"
            >
              <i className="fas fa-arrow-left mr-1"></i> Back
            </button>
            <h1 className="text-xl md:text-2xl font-bold text-gray-900">
              Market Application Review
            </h1>
            <p className="text-gray-600 mt-1">
              ID: <span className="font-medium text-blue-600">
                {application.stall_rights_no || application.id}
              </span>
            </p>
          </div>
          
          <div className="mt-3 md:mt-0">
            <button
              onClick={fetchData}
              disabled={loading || actionLoading}
              className="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
            >
              Refresh
            </button>
          </div>
        </div>

        {/* Progress Bar */}
        {renderProgressBar()}

        {/* Status Display */}
        <div className={`rounded p-3 mb-4 ${statusColor}`}>
          <div className="flex items-center justify-between">
            <div>
              <p className="font-bold">Status: {statusText}</p>
              <p className="text-sm text-gray-700">
                {status === 'pending' && "Waiting for interview scheduling"}
                {status === 'interview_scheduled' && `Interview scheduled for ${formatDate(application.interview_date)}`}
                {status === 'interviewed' && "Interview completed"}
                {status === 'paying' && "Waiting for payment"}
                {status === 'paid' && "Payment completed"}
                {status === 'need_correction' && "Needs correction"}
                {status === 'resubmitted' && "Resubmitted"}
                {status === 'approved' && "Approved"}
                {status === 'rejected' && "Rejected"}
              </p>
            </div>
            <div className="text-right">
              <p className="text-sm text-gray-600">Submitted</p>
              <p className="text-sm font-medium">{formatDate(application.created_at)}</p>
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Main Content */}
        <div className="lg:col-span-2 space-y-4">
          
          {/* Applicant Information */}
          <div className="bg-white rounded border border-gray-200 p-4">
            <h2 className="text-lg font-bold mb-3">Applicant Information</h2>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <div>
                  <p className="text-sm text-gray-600">Full Name</p>
                  <p className="font-semibold">
                    {application.first_name} {application.middle_name ? application.middle_name + ' ' : ''}{application.last_name}
                  </p>
                </div>
                
                <div>
                  <p className="text-sm text-gray-600">Email Address</p>
                  <p>{application.email}</p>
                </div>
                
                <div>
                  <p className="text-sm text-gray-600">Mobile Number</p>
                  <p>{application.mobile}</p>
                </div>
                
                <div>
                  <p className="text-sm text-gray-600">Renter Code</p>
                  <p className="font-semibold text-blue-600">{application.renter_code}</p>
                </div>
              </div>
              
              <div className="space-y-2">
                <div>
                  <p className="text-sm text-gray-600">Gender</p>
                  <p>{application.gender ? application.gender.charAt(0).toUpperCase() + application.gender.slice(1) : 'N/A'}</p>
                </div>
                
                <div>
                  <p className="text-sm text-gray-600">Birth Date</p>
                  <p>{application.birth_date ? new Date(application.birth_date).toLocaleDateString() : 'N/A'}</p>
                </div>
                
                {application.emergency_name && (
                  <div>
                    <p className="text-sm text-gray-600">Emergency Contact</p>
                    <p>{application.emergency_name} ({application.emergency_contact})</p>
                  </div>
                )}
              </div>
            </div>
            
            <div className="mt-4 pt-4 border-t border-gray-100">
              <p className="text-sm text-gray-600">Complete Address</p>
              <p>{application.full_address || 'N/A'}</p>
            </div>
          </div>

          {/* Stall Information */}
          <div className="bg-white rounded border border-gray-200 p-4">
            <h2 className="text-lg font-bold mb-3">Stall Information</h2>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <div>
                  <p className="text-sm text-gray-600">Stall Name</p>
                  <p className="font-semibold">{application.stall_name || 'N/A'}</p>
                </div>
                
                <div>
                  <p className="text-sm text-gray-600">Stall Class</p>
                  <p className="font-semibold text-blue-600">{application.stall_class || 'N/A'}</p>
                </div>
              </div>
              
              <div className="space-y-2">
                <div>
                  <p className="text-sm text-gray-600">Business Name</p>
                  <p>{application.business_name || 'N/A'}</p>
                </div>
                
                <div>
                  <p className="text-sm text-gray-600">Business Type</p>
                  <p>{application.business_type || 'N/A'}</p>
                </div>
              </div>
            </div>
            
            {application.interview_date && (
              <div className="mt-4 pt-4 border-t border-gray-100">
                <h3 className="font-medium text-gray-800 mb-2">Interview Details</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div>
                    <p className="text-sm text-gray-600">Interview Date</p>
                    <p className="font-medium">{formatDate(application.interview_date)}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Interviewer</p>
                    <p className="font-medium">{application.interviewer || 'N/A'}</p>
                  </div>
                  {application.interview_notes && (
                    <div className="md:col-span-2">
                      <p className="text-sm text-gray-600">Interview Notes</p>
                      <p className="mt-1 p-2 bg-gray-50 rounded text-sm">{application.interview_notes}</p>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* Financial Information */}
          <div className="bg-white rounded border border-gray-200 p-4">
            <h2 className="text-lg font-bold mb-3">Financial Information</h2>
            
            <div className="space-y-4">
              {/* Monthly Rent */}
              <div className="p-3 bg-blue-50 rounded border border-blue-100">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-bold text-gray-900">Monthly Rent</p>
                    <p className="text-sm text-gray-600">Payable monthly</p>
                  </div>
                  <p className="text-xl font-bold text-blue-700">
                    {formatCurrency(application.monthly_rent)}
                  </p>
                </div>
              </div>

              {/* One-time Payments */}
              <div className="p-3 bg-purple-50 rounded border border-purple-100">
                <p className="font-bold text-gray-900 mb-2">One-time Payments</p>
                
                <div className="space-y-2">
                  <div className="flex justify-between">
                    <div>
                      <p className="font-medium text-gray-800">Stall Rights Fee</p>
                      <p className="text-sm text-gray-600">Non-refundable</p>
                    </div>
                    <p className="font-semibold">
                      {formatCurrency(application.stall_rights_amount)}
                    </p>
                  </div>
                  
                  <div className="flex justify-between">
                    <div>
                      <p className="font-medium text-gray-800">Security Bond</p>
                      <p className="text-sm text-gray-600">Refundable</p>
                    </div>
                    <p className="font-semibold">
                      {formatCurrency(application.security_bond)}
                    </p>
                  </div>
                  
                  <div className="pt-2 border-t border-purple-200 mt-2">
                    <div className="flex justify-between items-center">
                      <div>
                        <p className="font-bold text-gray-900">Total Amount Due</p>
                        <p className="text-sm text-gray-600">Payable after interview</p>
                      </div>
                      <p className="text-xl font-bold text-purple-600">
                        {formatCurrency(application.total_amount_due)}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              {/* Payment Information */}
              {application.payment_date && (
                <div className="p-3 bg-green-50 rounded border border-green-100">
                  <p className="font-bold text-gray-900 mb-2">Payment Information</p>
                  <div className="space-y-1">
                    <div className="flex justify-between">
                      <span className="text-gray-600">Payment Date:</span>
                      <span className="font-medium">{formatDate(application.payment_date)}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-gray-600">Reference Number:</span>
                      <span className="font-medium text-blue-600">{application.reference_number || 'N/A'}</span>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Documents */}
          <div className="bg-white rounded border border-gray-200 p-4">
            <h2 className="text-lg font-bold mb-3">Uploaded Documents</h2>
            
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
              {/* Barangay Clearance */}
              {application.barangay_clearance && (
                <div 
                  className="border border-gray-200 rounded p-3 hover:border-blue-300 hover:shadow cursor-pointer"
                  onClick={() => handleDocumentPreview('barangay_clearance', application.barangay_clearance)}
                >
                  <div className="flex items-center mb-2">
                    <div className="w-10 h-10 bg-blue-50 rounded flex items-center justify-center mr-2">
                      <i className="fas fa-file-contract text-blue-500"></i>
                    </div>
                    <div>
                      <p className="font-semibold text-gray-900">Barangay Clearance</p>
                      <p className="text-xs text-gray-500">Click to view</p>
                    </div>
                  </div>
                </div>
              )}
              
              {/* 2x2 ID Photo */}
              {application.id_photo_2x2 && (
                <div 
                  className="border border-gray-200 rounded p-3 hover:border-blue-300 hover:shadow cursor-pointer"
                  onClick={() => handleDocumentPreview('id_photo', application.id_photo_2x2)}
                >
                  <div className="flex items-center mb-2">
                    <div className="w-10 h-10 bg-green-50 rounded flex items-center justify-center mr-2">
                      <i className="fas fa-user-circle text-green-500"></i>
                    </div>
                    <div>
                      <p className="font-semibold text-gray-900">2x2 ID Photo</p>
                      <p className="text-xs text-gray-500">Click to view</p>
                    </div>
                  </div>
                </div>
              )}
              
              {/* Valid ID */}
              {application.valid_id && (
                <div 
                  className="border border-gray-200 rounded p-3 hover:border-blue-300 hover:shadow cursor-pointer"
                  onClick={() => handleDocumentPreview('valid_id', application.valid_id)}
                >
                  <div className="flex items-center mb-2">
                    <div className="w-10 h-10 bg-purple-50 rounded flex items-center justify-center mr-2">
                      <i className="fas fa-id-card text-purple-500"></i>
                    </div>
                    <div>
                      <p className="font-semibold text-gray-900">Valid ID</p>
                      <p className="text-xs text-gray-500">Click to view</p>
                    </div>
                  </div>
                </div>
              )}
            </div>
            
            {!application.barangay_clearance && !application.id_photo_2x2 && !application.valid_id && (
              <div className="text-center py-4">
                <p className="text-gray-500">No documents uploaded</p>
              </div>
            )}
          </div>
        </div>

        {/* Action Panel */}
        <div className="lg:col-span-1">
          <div className="bg-white rounded border border-gray-200 p-4 sticky top-4">
            <h2 className="text-lg font-bold mb-3">Admin Actions</h2>
            
            {renderActionButtons()}

            <div className="mt-6 pt-4 border-t border-gray-200">
              <h3 className="font-semibold mb-2">Application Summary</h3>
              <div className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <span className="text-gray-600">Application ID:</span>
                  <span className="font-semibold">{application.stall_rights_no || application.id}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Renter Code:</span>
                  <span className="font-semibold">{application.renter_code}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Stall Class:</span>
                  <span className="font-semibold">{application.stall_class}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Status:</span>
                  <span className={`px-2 py-1 rounded text-xs font-medium ${statusColor}`}>
                    {statusText}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Submitted:</span>
                  <span>{formatDate(application.created_at)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Last Updated:</span>
                  <span>{formatDate(application.updated_at)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600">Total Amount Due:</span>
                  <span className="font-semibold text-blue-600">{formatCurrency(application.total_amount_due)}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Document Preview Modal */}
      {showDocumentPreview && (
        <div className="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded shadow max-w-4xl w-full">
            <div className="p-3 border-b border-gray-200 flex justify-between items-center">
              <h3 className="font-semibold">Document Preview</h3>
              <button 
                onClick={() => setShowDocumentPreview(null)} 
                className="text-gray-400 hover:text-gray-600"
              >
                ✕
              </button>
            </div>
            <div className="p-4">
              <div className="h-80 flex items-center justify-center bg-gray-100 rounded">
                {previewUrl.match(/\.(jpg|jpeg|png|gif)$/i) ? (
                  <img 
                    src={previewUrl} 
                    alt="Document" 
                    className="max-h-full max-w-full object-contain"
                  />
                ) : (
                  <div className="text-center">
                    <i className="fas fa-file-pdf text-4xl text-red-500 mb-2"></i>
                    <p className="text-gray-600 mb-3">PDF document preview not available</p>
                    <a 
                      href={previewUrl} 
                      target="_blank" 
                      rel="noopener noreferrer"
                      className="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700"
                    >
                      Download PDF
                    </a>
                  </div>
                )}
              </div>
            </div>
            <div className="p-3 border-t border-gray-200 flex justify-end">
              <a 
                href={previewUrl} 
                target="_blank" 
                rel="noopener noreferrer"
                className="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700"
              >
                Open in New Tab
              </a>
            </div>
          </div>
        </div>
      )}

      {/* Schedule Interview Modal */}
      {showInterviewModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded shadow max-w-md w-full">
            <div className="p-4">
              <div className="flex justify-between items-center mb-3">
                <h3 className="font-semibold">Schedule Interview</h3>
                <button 
                  onClick={() => setShowInterviewModal(false)} 
                  className="text-gray-400 hover:text-gray-600"
                  disabled={actionLoading}
                >
                  ✕
                </button>
              </div>
              <div className="space-y-3">
                <div>
                  <label className="block text-sm font-medium mb-1">
                    Interviewer Name *
                  </label>
                  <input 
                    type="text" 
                    value={interviewer}
                    onChange={(e) => setInterviewer(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Enter interviewer name"
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
                    className="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                    className="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-20"
                    placeholder="Enter any notes for the interview..."
                  />
                </div>
                
                <div className="flex gap-2 pt-3">
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

      {/* Payment Modal */}
      {showPaymentModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded shadow max-w-md w-full">
            <div className="p-4">
              <div className="flex justify-between items-center mb-3">
                <h3 className="font-semibold">Record Payment</h3>
                <button 
                  onClick={() => setShowPaymentModal(false)} 
                  className="text-gray-400 hover:text-gray-600"
                  disabled={actionLoading}
                >
                  ✕
                </button>
              </div>
              <div className="space-y-3">
                <div className="bg-green-50 p-3 rounded border border-green-100">
                  <p className="font-semibold text-green-800">Payment Amount</p>
                  <p className="text-2xl font-bold text-green-700">{formatCurrency(application.total_amount_due)}</p>
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">
                    Payment Reference Number *
                  </label>
                  <input 
                    type="text" 
                    value={paymentReference}
                    onChange={(e) => setPaymentReference(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Enter payment reference number"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">
                    Payment Notes (Optional)
                  </label>
                  <textarea 
                    value={paymentNotes}
                    onChange={(e) => setPaymentNotes(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-20"
                    placeholder="Enter any payment notes..."
                  />
                </div>
                <div className="flex gap-2 pt-3">
                  <button 
                    onClick={handleMarkAsPaid} 
                    disabled={actionLoading || !paymentReference.trim()}
                    className="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded disabled:opacity-50"
                  >
                    {actionLoading ? 'Processing...' : 'Mark as Paid'}
                  </button>
                  <button 
                    onClick={() => setShowPaymentModal(false)} 
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
          <div className="bg-white rounded shadow max-w-md w-full">
            <div className="p-4">
              <div className="flex justify-between items-center mb-3">
                <h3 className="font-semibold">Mark as Needs Correction</h3>
                <button 
                  onClick={() => setShowCorrectionModal(false)} 
                  className="text-gray-400 hover:text-gray-600"
                  disabled={actionLoading}
                >
                  ✕
                </button>
              </div>
              <div className="space-y-3">
                <div>
                  <label className="block text-sm font-medium mb-1">
                    Correction Notes *
                  </label>
                  <textarea 
                    value={correctionNotes}
                    onChange={(e) => setCorrectionNotes(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-28"
                    placeholder="Explain what needs to be corrected..."
                    required
                  />
                </div>
                <div className="flex gap-2 pt-3">
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
          <div className="bg-white rounded shadow max-w-md w-full">
            <div className="p-4">
              <div className="flex justify-between items-center mb-3">
                <h3 className="font-semibold">Approve Application</h3>
                <button 
                  onClick={() => setShowApproveModal(false)} 
                  className="text-gray-400 hover:text-gray-600"
                  disabled={actionLoading}
                >
                  ✕
                </button>
              </div>
              <div className="space-y-3">
                <div className="bg-emerald-50 p-3 rounded border border-emerald-100">
                  <p className="font-semibold text-emerald-800">Ready for Approval</p>
                  <div className="mt-1 space-y-1 text-sm">
                    <p>• Applicant: {application.first_name} {application.last_name}</p>
                    <p>• Stall: {application.stall_name}</p>
                    <p>• Payment: {formatCurrency(application.total_amount_due)} (Paid)</p>
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">
                    Approval Notes (Optional)
                  </label>
                  <textarea 
                    value={approvalNotes}
                    onChange={(e) => setApprovalNotes(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-20"
                    placeholder="Enter any approval notes..."
                  />
                </div>
                <div className="flex gap-2 pt-3">
                  <button 
                    onClick={handleApprove} 
                    disabled={actionLoading}
                    className="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white py-2 rounded disabled:opacity-50"
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
          <div className="bg-white rounded shadow max-w-md w-full">
            <div className="p-4">
              <div className="flex justify-between items-center mb-3">
                <h3 className="font-semibold">Reject Application</h3>
                <button 
                  onClick={() => setShowRejectModal(false)} 
                  className="text-gray-400 hover:text-gray-600"
                  disabled={actionLoading}
                >
                  ✕
                </button>
              </div>
              <div className="space-y-3">
                <div>
                  <label className="block text-sm font-medium mb-1">
                    Rejection Reason *
                  </label>
                  <textarea 
                    value={rejectionNotes}
                    onChange={(e) => setRejectionNotes(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-28"
                    placeholder="Explain why this application is being rejected..."
                    required
                  />
                </div>
                <div className="flex gap-2 pt-3">
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