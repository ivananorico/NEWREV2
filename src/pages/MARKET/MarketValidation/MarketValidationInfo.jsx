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

  // FIXED: Dynamic API base URL
  const getApiBaseUrl = () => {
    const { hostname, protocol, pathname } = window.location;
    
    console.log("Current location info:", { hostname, protocol, pathname });
    
    // Production domain - NO /revenue2 in path
    if (hostname === 'revenuetreasury.goserveph.com') {
      return `${protocol}//${hostname}/backend`;
    }
    
    // Localhost - WITH /revenue2 in path
    if (hostname === 'localhost' || hostname === '127.0.0.1') {
      return 'http://localhost/revenue2/backend';
    }
    
    // Default: Try to detect from current path
    if (pathname.includes('/revenue2')) {
      return '/revenue2/backend';
    } else {
      return '/backend';
    }
  };

  // FIXED: Get document URL - Proper URL construction
  const getDocumentUrl = (filePath) => {
    if (!filePath || filePath === 'null' || filePath === 'undefined') {
      console.log('No file path provided');
      return null;
    }
    
    console.log('File path from DB:', filePath);
    
    // If it's already a full URL, return it
    if (filePath.startsWith('http://') || filePath.startsWith('https://')) {
      return filePath;
    }
    
    // Get the current protocol and hostname
    const protocol = window.location.protocol;
    const host = window.location.host;
    
    // Check if file path is relative or absolute
    if (filePath.startsWith('uploads/')) {
      // It's already relative from root
      return `${protocol}//${host}/${filePath}`;
    } else if (filePath.startsWith('/uploads/')) {
      // Absolute path
      return `${protocol}//${host}${filePath}`;
    } else if (filePath.includes('market/documents/')) {
      // Contains market documents path
      return `${protocol}//${host}/uploads/market/documents/${filePath.split('market/documents/')[1]}`;
    } else {
      // Assume it's a relative path, prepend with base URL
      const API_BASE = getApiBaseUrl();
      return `${API_BASE}/${filePath.replace(/^\//, '')}`;
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
      const url = `${API_BASE}/Market/MarketValidation/get_application_details.php?id=${id}`;
      
      console.log("Fetching from URL:", url);
      
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
        },
        mode: 'cors'
      });
      
      console.log("Response status:", response.status);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      console.log("Response data:", data);
      
      if (data.status === 'success') {
        setApplication(data.data);
      } else {
        throw new Error(data.message || "Failed to fetch application");
      }
    } catch (err) {
      console.error("Fetch error:", err);
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
      const url = `${API_BASE}/Market/MarketValidation/${endpoint}`;
      
      console.log(`Calling API: ${url}`, payload);
      
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });
      
      const result = await response.json();
      
      if (result.status === 'success') {
        return result;
      } else {
        throw new Error(result.message || "Failed to update");
      }
    } catch (error) {
      console.error("API call error:", error);
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

  // FIXED: Handle document preview
  const handleDocumentPreview = (docType, filePath) => {
    console.log('Document preview clicked:', { docType, filePath });
    
    if (!filePath || filePath === 'null' || filePath === 'undefined') {
      alert(`No ${docType.replace('_', ' ')} document available`);
      return;
    }
    
    const url = getDocumentUrl(filePath);
    
    if (!url) {
      alert(`Unable to load ${docType.replace('_', ' ')} document`);
      return;
    }
    
    console.log('Generated URL for preview:', url);
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
      case 'pending': return 'bg-yellow-50 text-yellow-800 border border-yellow-200';
      case 'interview_scheduled': return 'bg-blue-50 text-blue-800 border border-blue-200';
      case 'interviewed': return 'bg-green-50 text-green-800 border border-green-200';
      case 'paying': return 'bg-purple-50 text-purple-800 border border-purple-200';
      case 'paid': return 'bg-indigo-50 text-indigo-800 border border-indigo-200';
      case 'need_correction': return 'bg-red-50 text-red-800 border border-red-200';
      case 'resubmitted': return 'bg-orange-50 text-orange-800 border border-orange-200';
      case 'approved': return 'bg-emerald-50 text-emerald-800 border border-emerald-200';
      case 'rejected': return 'bg-gray-50 text-gray-800 border border-gray-200';
      default: return 'bg-gray-50 text-gray-800 border border-gray-200';
    }
  };

  // Get status display text
  const getStatusText = () => {
    const status = application?.application_status?.toLowerCase();
    const statusMap = {
      'pending': 'Pending Review',
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

  // Render progress bar steps
  const renderProgressBar = () => {
    const currentStatus = application?.application_status?.toLowerCase();
    const steps = [
      { key: 'pending', label: 'Pending', icon: 'fa-clock', color: 'yellow' },
      { key: 'interview_scheduled', label: 'Interview', icon: 'fa-calendar-alt', color: 'blue' },
      { key: 'interviewed', label: 'Completed', icon: 'fa-user-check', color: 'green' },
      { key: 'paying', label: 'Payment', icon: 'fa-credit-card', color: 'purple' },
      { key: 'paid', label: 'Paid', icon: 'fa-money-check', color: 'indigo' },
      { key: 'approved', label: 'Approved', icon: 'fa-check-circle', color: 'emerald' }
    ];

    const stepIndex = steps.findIndex(step => step.key === currentStatus);
    const activeIndex = stepIndex === -1 ? 0 : stepIndex;

    return (
      <div className="mb-8">
        <div className="flex items-center justify-between mb-4">
          {steps.map((step, index) => (
            <div key={step.key} className="flex flex-col items-center w-full relative">
              {/* Step connector line */}
              {index < steps.length - 1 && (
                <div className="absolute top-4 left-1/2 w-full h-1 bg-gray-200 z-0"></div>
              )}
              {/* Step circle */}
              <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold z-10 border-2 ${
                index <= activeIndex 
                  ? `bg-${step.color}-600 text-white border-${step.color}-600` 
                  : 'bg-white text-gray-400 border-gray-300'
              }`}>
                {index < activeIndex ? <i className="fas fa-check text-xs"></i> : index + 1}
              </div>
              {/* Step label */}
              <div className="text-center mt-3">
                <span className={`text-xs font-medium ${index <= activeIndex ? 'text-gray-900' : 'text-gray-500'}`}>
                  {step.label}
                </span>
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  };

  // Render action buttons based on status
  const renderActionButtons = () => {
    const status = application?.application_status?.toLowerCase();
    
    switch (status) {
      // PENDING: Can schedule interview, mark as need correction, or reject
      case 'pending':
        return (
          <div className="space-y-3">
            <button
              onClick={() => setShowInterviewModal(true)}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-calendar-alt"></i>
              Schedule Interview
            </button>
            <button
              onClick={() => setShowCorrectionModal(true)}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-edit"></i>
              Needs Correction
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-times-circle"></i>
              Reject Application
            </button>
          </div>
        );
      
      // INTERVIEW SCHEDULED: Can mark as interviewed or reset to pending
      case 'interview_scheduled':
        return (
          <div className="space-y-3">
            <button
              onClick={handleInterviewCompleted}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-check-circle"></i>
              Mark as Interviewed
            </button>
            <button
              onClick={handleResetToPending}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-undo"></i>
              Reset to Pending
            </button>
          </div>
        );
      
      // INTERVIEWED: Can proceed to payment, mark as need correction, or reject
      case 'interviewed':
        return (
          <div className="space-y-3">
            <button
              onClick={handleMarkAsPaying}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-credit-card"></i>
              Proceed to Payment
            </button>
            <button
              onClick={() => setShowCorrectionModal(true)}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-edit"></i>
              Needs Correction
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-times-circle"></i>
              Reject Application
            </button>
          </div>
        );
      
      // PAYING: Can mark as paid, needs correction, or reject
      case 'paying':
        return (
          <div className="space-y-3">
            <div className="bg-gradient-to-r from-amber-50 to-yellow-50 p-4 rounded-lg border border-amber-200">
              <p className="text-sm font-medium text-amber-800 mb-1">Waiting for Payment</p>
              <p className="text-xl font-bold text-blue-700">{formatCurrency(application.total_amount_due)}</p>
            </div>
            <button
              onClick={() => setShowPaymentModal(true)}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-money-bill-wave"></i>
              Mark as Paid
            </button>
            <button
              onClick={() => setShowCorrectionModal(true)}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-edit"></i>
              Needs Correction
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-times-circle"></i>
              Reject Application
            </button>
          </div>
        );
      
      // PAID: Can approve, needs correction, or reject
      case 'paid':
        return (
          <div className="space-y-3">
            <button
              onClick={() => setShowApproveModal(true)}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-check-circle"></i>
              Approve Application
            </button>
            <button
              onClick={() => setShowCorrectionModal(true)}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-edit"></i>
              Needs Correction
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-times-circle"></i>
              Reject Application
            </button>
          </div>
        );
      
      // NEED CORRECTION: Can mark as resubmitted or reject
      case 'need_correction':
        return (
          <div className="space-y-3">
            <button
              onClick={handleMarkAsResubmitted}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-redo"></i>
              Mark as Resubmitted
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-times-circle"></i>
              Reject Application
            </button>
          </div>
        );
      
      // RESUBMITTED: Can schedule interview, needs correction, or reject
      case 'resubmitted':
        return (
          <div className="space-y-3">
            <button
              onClick={() => setShowInterviewModal(true)}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-calendar-alt"></i>
              Schedule Interview
            </button>
            <button
              onClick={() => setShowCorrectionModal(true)}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-edit"></i>
              Needs Correction
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              disabled={actionLoading}
              className="w-full bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-4 py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-times-circle"></i>
              Reject Application
            </button>
          </div>
        );
      
      // DEFAULT: No actions available
      default:
        return (
          <div className="text-center py-4">
            <p className="text-gray-500 text-sm">No actions available</p>
          </div>
        );
    }
  };

  // Handle reset to pending (for interview_scheduled status)
  const handleResetToPending = async () => {
    if (!window.confirm("Are you sure you want to reset this application to pending status?")) {
      return;
    }
    
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
        <div className="bg-white rounded-xl shadow-lg p-6 max-w-md w-full">
          <div className="text-red-500 text-5xl mb-3 text-center">
            <i className="fas fa-exclamation-triangle"></i>
          </div>
          <h2 className="text-lg font-bold text-gray-900 mb-2 text-center">Error Loading Data</h2>
          <p className="text-gray-600 mb-4 text-center">{error || "Application not found"}</p>
          
          <div className="mb-4 p-3 bg-gray-100 rounded text-xs">
            <p><strong>Debug Info:</strong></p>
            <p>Application ID: {id}</p>
            <p>Current Host: {window.location.hostname}</p>
            <p>API Base URL: {getApiBaseUrl()}</p>
            <p>Full URL: {getApiBaseUrl()}/Market/MarketValidation/get_application_details.php?id={id}</p>
          </div>
          
          <div className="space-y-3">
            <button 
              onClick={() => navigate(-1)}
              className="w-full bg-gray-600 hover:bg-gray-700 text-white py-2.5 px-4 rounded-lg font-medium transition-colors"
            >
              <i className="fas fa-arrow-left mr-2"></i> Go Back
            </button>
            <button 
              onClick={fetchData}
              className="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 px-4 rounded-lg font-medium transition-colors"
            >
              <i className="fas fa-sync-alt mr-2"></i> Try Again
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
    <div className='mx-auto p-4 sm:p-6 max-w-7xl min-h-screen bg-gray-50'>
      {/* Header */}
      <div className="mb-6">
        <div className="flex flex-col md:flex-row md:items-center justify-between mb-4">
          <div>
            <button 
              onClick={() => navigate(-1)} 
              className="text-blue-600 hover:text-blue-800 mb-3 inline-flex items-center gap-2 font-medium"
            >
              <i className="fas fa-arrow-left"></i> Back to List
            </button>
            <div className="flex items-center gap-3">
              <div className="p-3 bg-white rounded-xl shadow-sm border border-gray-200">
                <i className="fas fa-file-alt text-2xl text-blue-600"></i>
              </div>
              <div>
                <h1 className="text-xl md:text-2xl font-bold text-gray-900">
                  Application Review Form
                </h1>
                <p className="text-gray-600 mt-1">
                  ID: <span className="font-medium text-blue-600">
                    {application.stall_rights_no || application.id}
                  </span>
                  <span className="mx-2">•</span>
                  Renter Code: <span className="font-medium text-green-600">{application.renter_code}</span>
                </p>
              </div>
            </div>
          </div>
          
          <div className="mt-3 md:mt-0">
            <button
              onClick={fetchData}
              disabled={loading || actionLoading}
              className="px-4 py-2.5 bg-white text-gray-700 rounded-lg border border-gray-300 hover:bg-gray-50 disabled:opacity-50 transition-colors font-medium flex items-center gap-2 shadow-sm hover:shadow"
            >
              <i className="fas fa-sync-alt"></i> Refresh
            </button>
          </div>
        </div>

        {/* Status Banner */}
        <div className={`rounded-xl p-4 mb-6 ${statusColor} shadow-sm`}>
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div className="flex items-center gap-4">
              <div className="p-3 bg-white/90 rounded-lg shadow-sm">
                <i className={`fas ${
                  status === 'pending' ? 'fa-clock text-yellow-500' :
                  status === 'interview_scheduled' ? 'fa-calendar-alt text-blue-500' :
                  status === 'interviewed' ? 'fa-user-check text-green-500' :
                  status === 'paying' ? 'fa-credit-card text-purple-500' :
                  status === 'paid' ? 'fa-money-check text-indigo-500' :
                  status === 'need_correction' ? 'fa-exclamation-triangle text-red-500' :
                  status === 'resubmitted' ? 'fa-redo text-orange-500' :
                  status === 'approved' ? 'fa-check-circle text-emerald-500' :
                  'fa-times-circle text-gray-500'
                } text-2xl`}></i>
              </div>
              <div>
                <p className="font-bold text-lg text-gray-900">{statusText}</p>
                <p className="text-sm text-gray-600 mt-1">
                  {status === 'pending' && "Application is pending initial review"}
                  {status === 'interview_scheduled' && `Interview scheduled: ${formatDate(application.interview_date)}`}
                  {status === 'interviewed' && "Interview completed - Ready for payment"}
                  {status === 'paying' && "Waiting for payment confirmation"}
                  {status === 'paid' && "Payment completed - Ready for approval"}
                  {status === 'need_correction' && "Application needs corrections"}
                  {status === 'resubmitted' && "Application has been resubmitted"}
                  {status === 'approved' && "Application has been approved"}
                  {status === 'rejected' && "Application has been rejected"}
                </p>
              </div>
            </div>
            
            <div className="text-right">
              <p className="text-sm text-gray-600">Submitted</p>
              <p className="text-sm font-medium text-gray-900">{formatDate(application.created_at)}</p>
            </div>
          </div>
        </div>

        {/* Progress Bar */}
        {renderProgressBar()}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Form Content */}
        <div className="lg:col-span-2 space-y-6">
          
          {/* Applicant Information Section */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div className="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
              <div className="p-2 bg-blue-50 rounded-lg">
                <i className="fas fa-user text-blue-500 text-lg"></i>
              </div>
              <h2 className="text-lg font-bold text-gray-900">Applicant Information</h2>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="space-y-5">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                  <div className="p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <p className="font-semibold text-gray-900">
                      {application.first_name} {application.middle_name ? application.middle_name + ' ' : ''}{application.last_name}
                    </p>
                  </div>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                  <div className="p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <p className="text-gray-900">{application.email}</p>
                  </div>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Mobile Number</label>
                  <div className="p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <p className="text-gray-900">{application.mobile}</p>
                  </div>
                </div>
              </div>
              
              <div className="space-y-5">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                  <div className="p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <p className="text-gray-900">{application.gender ? application.gender.charAt(0).toUpperCase() + application.gender.slice(1) : 'N/A'}</p>
                  </div>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Birth Date</label>
                  <div className="p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <p className="text-gray-900">{application.birth_date ? new Date(application.birth_date).toLocaleDateString() : 'N/A'}</p>
                  </div>
                </div>
                
                {application.emergency_name && (
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Emergency Contact</label>
                    <div className="p-3 bg-gray-50 rounded-lg border border-gray-200">
                      <p className="text-gray-900">{application.emergency_name} ({application.emergency_contact})</p>
                    </div>
                  </div>
                )}
              </div>
            </div>
            
            <div className="mt-6 pt-5 border-t border-gray-100">
              <label className="block text-sm font-medium text-gray-700 mb-2">Complete Address</label>
              <div className="p-3 bg-gray-50 rounded-lg border border-gray-200">
                <p className="text-gray-900">{application.full_address || 'N/A'}</p>
              </div>
            </div>
          </div>

          {/* Stall Information Section */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div className="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
              <div className="p-2 bg-green-50 rounded-lg">
                <i className="fas fa-store text-green-500 text-lg"></i>
              </div>
              <h2 className="text-lg font-bold text-gray-900">Stall Information</h2>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="space-y-5">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Stall Name</label>
                  <div className="p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <p className="font-semibold text-gray-900">{application.stall_name || 'N/A'}</p>
                  </div>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Stall Class</label>
                  <div className="p-3 bg-blue-50 rounded-lg border border-blue-200">
                    <p className="font-semibold text-blue-600">{application.stall_class || 'N/A'}</p>
                  </div>
                </div>
              </div>
              
              <div className="space-y-5">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Business Name</label>
                  <div className="p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <p className="text-gray-900">{application.business_name || 'N/A'}</p>
                  </div>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Business Type</label>
                  <div className="p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <p className="text-gray-900">{application.business_type || 'N/A'}</p>
                  </div>
                </div>
              </div>
            </div>
            
            {/* Interview Details Section */}
            {application.interview_date && (
              <div className="mt-6 pt-5 border-t border-gray-100">
                <h3 className="font-bold text-gray-800 mb-4">Interview Details</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Interview Date</label>
                    <div className="p-3 bg-gray-50 rounded-lg border border-gray-200">
                      <p className="font-medium text-gray-900">{formatDate(application.interview_date)}</p>
                    </div>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Interviewer</label>
                    <div className="p-3 bg-gray-50 rounded-lg border border-gray-200">
                      <p className="font-medium text-gray-900">{application.interviewer || 'N/A'}</p>
                    </div>
                  </div>
                  {application.interview_notes && (
                    <div className="md:col-span-2">
                      <label className="block text-sm font-medium text-gray-700 mb-2">Interview Notes</label>
                      <div className="mt-1 p-4 bg-gray-50 rounded-lg border border-gray-200 text-gray-700">
                        {application.interview_notes}
                      </div>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* Financial Information Section */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div className="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
              <div className="p-2 bg-purple-50 rounded-lg">
                <i className="fas fa-money-bill-wave text-purple-500 text-lg"></i>
              </div>
              <h2 className="text-lg font-bold text-gray-900">Financial Information</h2>
            </div>
            
            <div className="space-y-6">
              {/* Monthly Rent */}
              <div className="p-5 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-bold text-gray-900 text-lg">Monthly Rent</p>
                    <p className="text-sm text-blue-600">Payable monthly after approval</p>
                  </div>
                  <p className="text-2xl font-bold text-blue-700">
                    {formatCurrency(application.monthly_rent)}
                  </p>
                </div>
              </div>

              {/* One-time Payments */}
              <div className="p-5 bg-gradient-to-r from-purple-50 to-violet-50 rounded-xl border border-purple-200">
                <p className="font-bold text-gray-900 text-lg mb-4">One-time Payments</p>
                
                <div className="space-y-4">
                  <div className="flex justify-between items-center py-2">
                    <div>
                      <p className="font-medium text-gray-800">Stall Rights Fee</p>
                      <p className="text-xs text-gray-500">Non-refundable</p>
                    </div>
                    <p className="font-semibold text-gray-900 text-lg">
                      {formatCurrency(application.stall_rights_amount)}
                    </p>
                  </div>
                  
                  <div className="flex justify-between items-center py-2">
                    <div>
                      <p className="font-medium text-gray-800">Security Bond</p>
                      <p className="text-xs text-gray-500">Refundable</p>
                    </div>
                    <p className="font-semibold text-gray-900 text-lg">
                      {formatCurrency(application.security_bond)}
                    </p>
                  </div>
                  
                  <div className="pt-4 border-t border-purple-200">
                    <div className="flex justify-between items-center">
                      <div>
                        <p className="font-bold text-gray-900 text-lg">Total Amount Due</p>
                        <p className="text-sm text-purple-600">Payable after interview</p>
                      </div>
                      <p className="text-2xl font-bold text-purple-700">
                        {formatCurrency(application.total_amount_due)}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              {/* Payment Information */}
              {application.payment_date && (
                <div className="p-5 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl border border-green-200">
                  <p className="font-bold text-gray-900 text-lg mb-4">Payment Information</p>
                  <div className="space-y-3">
                    <div className="flex justify-between py-2">
                      <span className="text-gray-700">Payment Date:</span>
                      <span className="font-medium text-gray-900">{formatDate(application.payment_date)}</span>
                    </div>
                    <div className="flex justify-between py-2">
                      <span className="text-gray-700">Reference Number:</span>
                      <span className="font-medium text-blue-600">{application.reference_number || 'N/A'}</span>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Documents Section - FIXED */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div className="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
              <div className="p-2 bg-indigo-50 rounded-lg">
                <i className="fas fa-file-alt text-indigo-500 text-lg"></i>
              </div>
              <h2 className="text-lg font-bold text-gray-900">Uploaded Documents</h2>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {/* Barangay Clearance */}
              {application.barangay_clearance && application.barangay_clearance !== 'null' && application.barangay_clearance !== 'undefined' ? (
                <div 
                  className="border border-gray-200 rounded-xl p-4 hover:border-blue-300 hover:shadow-sm cursor-pointer transition-all duration-200 bg-gray-50 hover:bg-blue-50"
                  onClick={() => handleDocumentPreview('barangay_clearance', application.barangay_clearance)}
                >
                  <div className="flex items-center">
                    <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                      <i className="fas fa-file-contract text-blue-500 text-lg"></i>
                    </div>
                    <div>
                      <p className="font-semibold text-gray-900">Barangay Clearance</p>
                      <p className="text-xs text-gray-500 mt-1">Click to view</p>
                    </div>
                  </div>
                </div>
              ) : (
                <div className="border border-gray-200 rounded-xl p-4 bg-gray-50 opacity-60">
                  <div className="flex items-center">
                    <div className="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center mr-3">
                      <i className="fas fa-file-contract text-gray-400 text-lg"></i>
                    </div>
                    <div>
                      <p className="font-semibold text-gray-400">Barangay Clearance</p>
                      <p className="text-xs text-gray-400 mt-1">Not uploaded</p>
                    </div>
                  </div>
                </div>
              )}
              
              {/* 2x2 ID Photo */}
              {application.id_photo_2x2 && application.id_photo_2x2 !== 'null' && application.id_photo_2x2 !== 'undefined' ? (
                <div 
                  className="border border-gray-200 rounded-xl p-4 hover:border-green-300 hover:shadow-sm cursor-pointer transition-all duration-200 bg-gray-50 hover:bg-green-50"
                  onClick={() => handleDocumentPreview('id_photo', application.id_photo_2x2)}
                >
                  <div className="flex items-center">
                    <div className="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                      <i className="fas fa-user-circle text-green-500 text-lg"></i>
                    </div>
                    <div>
                      <p className="font-semibold text-gray-900">2x2 ID Photo</p>
                      <p className="text-xs text-gray-500 mt-1">Click to view</p>
                    </div>
                  </div>
                </div>
              ) : (
                <div className="border border-gray-200 rounded-xl p-4 bg-gray-50 opacity-60">
                  <div className="flex items-center">
                    <div className="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center mr-3">
                      <i className="fas fa-user-circle text-gray-400 text-lg"></i>
                    </div>
                    <div>
                      <p className="font-semibold text-gray-400">2x2 ID Photo</p>
                      <p className="text-xs text-gray-400 mt-1">Not uploaded</p>
                    </div>
                  </div>
                </div>
              )}
              
              {/* Valid ID */}
              {application.valid_id && application.valid_id !== 'null' && application.valid_id !== 'undefined' ? (
                <div 
                  className="border border-gray-200 rounded-xl p-4 hover:border-purple-300 hover:shadow-sm cursor-pointer transition-all duration-200 bg-gray-50 hover:bg-purple-50"
                  onClick={() => handleDocumentPreview('valid_id', application.valid_id)}
                >
                  <div className="flex items-center">
                    <div className="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                      <i className="fas fa-id-card text-purple-500 text-lg"></i>
                    </div>
                    <div>
                      <p className="font-semibold text-gray-900">Valid ID</p>
                      <p className="text-xs text-gray-500 mt-1">Click to view</p>
                    </div>
                  </div>
                </div>
              ) : (
                <div className="border border-gray-200 rounded-xl p-4 bg-gray-50 opacity-60">
                  <div className="flex items-center">
                    <div className="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center mr-3">
                      <i className="fas fa-id-card text-gray-400 text-lg"></i>
                    </div>
                    <div>
                      <p className="font-semibold text-gray-400">Valid ID</p>
                      <p className="text-xs text-gray-400 mt-1">Not uploaded</p>
                    </div>
                  </div>
                </div>
              )}
            </div>
            
            {!application.barangay_clearance && !application.id_photo_2x2 && !application.valid_id && (
              <div className="text-center py-8">
                <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                  <i className="fas fa-file text-gray-400 text-xl"></i>
                </div>
                <p className="text-gray-500">No documents uploaded</p>
              </div>
            )}
          </div>
        </div>

        {/* Action Panel */}
        <div className="lg:col-span-1">
          <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sticky top-6">
            <div className="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
              <div className="p-2 bg-red-50 rounded-lg">
                <i className="fas fa-cogs text-red-500 text-lg"></i>
              </div>
              <h2 className="text-lg font-bold text-gray-900">Admin Actions</h2>
            </div>
            
            {renderActionButtons()}

            {/* Application Summary */}
            <div className="mt-8 pt-6 border-t border-gray-200">
              <h3 className="font-semibold text-gray-800 mb-4">Application Summary</h3>
              <div className="space-y-3">
                <div className="flex justify-between py-2 border-b border-gray-100">
                  <span className="text-gray-600">Application ID:</span>
                  <span className="font-semibold text-gray-900">{application.stall_rights_no || application.id}</span>
                </div>
                <div className="flex justify-between py-2 border-b border-gray-100">
                  <span className="text-gray-600">Renter Code:</span>
                  <span className="font-semibold text-green-600">{application.renter_code}</span>
                </div>
                <div className="flex justify-between py-2 border-b border-gray-100">
                  <span className="text-gray-600">Stall Class:</span>
                  <span className="font-semibold text-blue-600">{application.stall_class}</span>
                </div>
                <div className="flex justify-between items-center py-2 border-b border-gray-100">
                  <span className="text-gray-600">Status:</span>
                  <span className={`px-3 py-1 rounded-full text-xs font-medium ${statusColor.replace('border', '')}`}>
                    {statusText}
                  </span>
                </div>
                <div className="flex justify-between py-2 border-b border-gray-100">
                  <span className="text-gray-600">Submitted:</span>
                  <span className="text-gray-900">{formatDate(application.created_at)}</span>
                </div>
                <div className="flex justify-between py-2 border-b border-gray-100">
                  <span className="text-gray-600">Last Updated:</span>
                  <span className="text-gray-900">{formatDate(application.updated_at)}</span>
                </div>
                <div className="flex justify-between py-2">
                  <span className="text-gray-600">Total Amount Due:</span>
                  <span className="font-semibold text-blue-600">{formatCurrency(application.total_amount_due)}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Document Preview Modal - FIXED */}
      {showDocumentPreview && (
        <div className="fixed inset-0 bg-black/80 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-2xl max-w-5xl w-full max-h-[90vh] flex flex-col">
            <div className="p-4 border-b border-gray-200 flex justify-between items-center">
              <h3 className="font-semibold text-lg text-gray-900">Document Preview</h3>
              <button 
                onClick={() => setShowDocumentPreview(null)} 
                className="text-gray-400 hover:text-gray-600 text-2xl"
              >
                ×
              </button>
            </div>
            <div className="p-4 flex-1 overflow-auto">
              <div className="h-[60vh] flex items-center justify-center bg-gray-100 rounded-lg">
                {previewUrl ? (
                  <>
                    {previewUrl.match(/\.(jpg|jpeg|png|gif|webp|bmp)$/i) ? (
                      <img 
                        src={previewUrl} 
                        alt="Document" 
                        className="max-h-full max-w-full object-contain rounded"
                        onError={(e) => {
                          console.error('Image failed to load:', previewUrl);
                          e.target.onerror = null;
                          e.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2YzZjNmMyIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIiBmaWxsPSIjOTk5Ij5JbWFnZSBub3QgZm91bmQ8L3RleHQ+PC9zdmc+';
                        }}
                      />
                    ) : (
                      <div className="text-center">
                        <i className="fas fa-file-pdf text-6xl text-red-500 mb-4"></i>
                        <p className="text-gray-600 mb-4 text-lg">Document preview</p>
                        <p className="text-sm text-gray-500 mb-2">URL: {previewUrl}</p>
                        <a 
                          href={previewUrl} 
                          target="_blank" 
                          rel="noopener noreferrer"
                          className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium inline-flex items-center gap-2"
                        >
                          <i className="fas fa-download"></i> View/Download Document
                        </a>
                      </div>
                    )}
                  </>
                ) : (
                  <div className="text-center">
                    <i className="fas fa-exclamation-triangle text-6xl text-yellow-500 mb-4"></i>
                    <p className="text-gray-600 mb-4 text-lg">Unable to load document</p>
                    <p className="text-sm text-gray-500">No URL generated</p>
                  </div>
                )}
              </div>
            </div>
            <div className="p-4 border-t border-gray-200 flex justify-between items-center">
              <span className="text-gray-600 capitalize">{showDocumentPreview.replace('_', ' ')}</span>
              <div className="flex gap-2">
                <button
                  onClick={() => setShowDocumentPreview(null)}
                  className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium"
                >
                  Close
                </button>
                {previewUrl && (
                  <a 
                    href={previewUrl} 
                    target="_blank" 
                    rel="noopener noreferrer"
                    className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium inline-flex items-center gap-2"
                  >
                    <i className="fas fa-external-link-alt"></i> Open in New Tab
                  </a>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Schedule Interview Modal */}
      {showInterviewModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-lg max-w-md w-full">
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h3 className="font-bold text-lg text-gray-900">Schedule Interview</h3>
                <button 
                  onClick={() => setShowInterviewModal(false)} 
                  className="text-gray-400 hover:text-gray-600 text-xl"
                  disabled={actionLoading}
                >
                  ×
                </button>
              </div>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-2 text-gray-700">
                    Interviewer Name *
                  </label>
                  <input 
                    type="text" 
                    value={interviewer}
                    onChange={(e) => setInterviewer(e.target.value)}
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Enter interviewer name"
                    required
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2 text-gray-700">
                    Interview Date & Time *
                  </label>
                  <input 
                    type="datetime-local" 
                    value={interviewDate}
                    onChange={(e) => setInterviewDate(e.target.value)}
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    required
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2 text-gray-700">
                    Interview Notes (Optional)
                  </label>
                  <textarea 
                    value={interviewNotes}
                    onChange={(e) => setInterviewNotes(e.target.value)}
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-28"
                    placeholder="Enter any notes for the interview..."
                  />
                </div>
                
                <div className="flex gap-3 pt-4">
                  <button 
                    onClick={() => setShowInterviewModal(false)} 
                    disabled={actionLoading}
                    className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-800 py-3 rounded-lg font-medium transition-colors"
                  >
                    Cancel
                  </button>
                  <button 
                    onClick={handleScheduleInterview} 
                    disabled={actionLoading || !interviewer.trim() || !interviewDate}
                    className="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200"
                  >
                    {actionLoading ? 'Processing...' : 'Schedule Interview'}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Payment Modal */}
      {showPaymentModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-lg max-w-md w-full">
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h3 className="font-bold text-lg text-gray-900">Record Payment</h3>
                <button 
                  onClick={() => setShowPaymentModal(false)} 
                  className="text-gray-400 hover:text-gray-600 text-xl"
                  disabled={actionLoading}
                >
                  ×
                </button>
              </div>
              <div className="space-y-4">
                <div className="bg-green-50 p-4 rounded-lg border border-green-200">
                  <p className="font-semibold text-green-800 mb-1">Payment Amount</p>
                  <p className="text-2xl font-bold text-green-700">{formatCurrency(application.total_amount_due)}</p>
                </div>
                <div>
                  <label className="block text-sm font-medium mb-2 text-gray-700">
                    Payment Reference Number *
                  </label>
                  <input 
                    type="text" 
                    value={paymentReference}
                    onChange={(e) => setPaymentReference(e.target.value)}
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Enter payment reference number"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-2 text-gray-700">
                    Payment Notes (Optional)
                  </label>
                  <textarea 
                    value={paymentNotes}
                    onChange={(e) => setPaymentNotes(e.target.value)}
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-24"
                    placeholder="Enter any payment notes..."
                  />
                </div>
                <div className="flex gap-3 pt-4">
                  <button 
                    onClick={() => setShowPaymentModal(false)} 
                    disabled={actionLoading}
                    className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-800 py-3 rounded-lg font-medium transition-colors"
                  >
                    Cancel
                  </button>
                  <button 
                    onClick={handleMarkAsPaid} 
                    disabled={actionLoading || !paymentReference.trim()}
                    className="flex-1 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200"
                  >
                    {actionLoading ? 'Processing...' : 'Mark as Paid'}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Correction Modal */}
      {showCorrectionModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-lg max-w-md w-full">
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h3 className="font-bold text-lg text-gray-900">Mark as Needs Correction</h3>
                <button 
                  onClick={() => setShowCorrectionModal(false)} 
                  className="text-gray-400 hover:text-gray-600 text-xl"
                  disabled={actionLoading}
                >
                  ×
                </button>
              </div>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-2 text-gray-700">
                    Correction Notes *
                  </label>
                  <textarea 
                    value={correctionNotes}
                    onChange={(e) => setCorrectionNotes(e.target.value)}
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-32"
                    placeholder="Explain what needs to be corrected..."
                    required
                  />
                </div>
                <div className="flex gap-3 pt-4">
                  <button 
                    onClick={() => setShowCorrectionModal(false)} 
                    disabled={actionLoading}
                    className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-800 py-3 rounded-lg font-medium transition-colors"
                  >
                    Cancel
                  </button>
                  <button 
                    onClick={handleNeedCorrection} 
                    disabled={actionLoading || !correctionNotes.trim()}
                    className="flex-1 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200"
                  >
                    {actionLoading ? 'Processing...' : 'Mark Needs Correction'}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Approve Modal */}
      {showApproveModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-lg max-w-md w-full">
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h3 className="font-bold text-lg text-gray-900">Approve Application</h3>
                <button 
                  onClick={() => setShowApproveModal(false)} 
                  className="text-gray-400 hover:text-gray-600 text-xl"
                  disabled={actionLoading}
                >
                  ×
                </button>
              </div>
              <div className="space-y-4">
                <div className="bg-emerald-50 p-4 rounded-lg border border-emerald-200">
                  <p className="font-semibold text-emerald-800 mb-2">Ready for Approval</p>
                  <div className="space-y-1 text-sm">
                    <p className="flex items-center gap-2">
                      <i className="fas fa-user text-emerald-600"></i>
                      <span>Applicant: <strong>{application.first_name} {application.last_name}</strong></span>
                    </p>
                    <p className="flex items-center gap-2">
                      <i className="fas fa-store text-emerald-600"></i>
                      <span>Stall: <strong>{application.stall_name}</strong></span>
                    </p>
                    <p className="flex items-center gap-2">
                      <i className="fas fa-money-check text-emerald-600"></i>
                      <span>Payment: <strong>{formatCurrency(application.total_amount_due)}</strong> (Paid)</span>
                    </p>
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium mb-2 text-gray-700">
                    Approval Notes (Optional)
                  </label>
                  <textarea 
                    value={approvalNotes}
                    onChange={(e) => setApprovalNotes(e.target.value)}
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-24"
                    placeholder="Enter any approval notes..."
                  />
                </div>
                <div className="flex gap-3 pt-4">
                  <button 
                    onClick={() => setShowApproveModal(false)} 
                    disabled={actionLoading}
                    className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-800 py-3 rounded-lg font-medium transition-colors"
                  >
                    Cancel
                  </button>
                  <button 
                    onClick={handleApprove} 
                    disabled={actionLoading}
                    className="flex-1 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200"
                  >
                    {actionLoading ? 'Processing...' : 'Approve Application'}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Reject Modal */}
      {showRejectModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-lg max-w-md w-full">
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h3 className="font-bold text-lg text-gray-900">Reject Application</h3>
                <button 
                  onClick={() => setShowRejectModal(false)} 
                  className="text-gray-400 hover:text-gray-600 text-xl"
                  disabled={actionLoading}
                >
                  ×
                </button>
              </div>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-2 text-gray-700">
                    Rejection Reason *
                  </label>
                  <textarea 
                    value={rejectionNotes}
                    onChange={(e) => setRejectionNotes(e.target.value)}
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 h-32"
                    placeholder="Explain why this application is being rejected..."
                    required
                  />
                </div>
                <div className="flex gap-3 pt-4">
                  <button 
                    onClick={() => setShowRejectModal(false)} 
                    disabled={actionLoading}
                    className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-800 py-3 rounded-lg font-medium transition-colors"
                  >
                    Cancel
                  </button>
                  <button 
                    onClick={handleReject} 
                    disabled={actionLoading || !rejectionNotes.trim()}
                    className="flex-1 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white py-3 rounded-lg font-medium disabled:opacity-50 transition-all duration-200"
                  >
                    {actionLoading ? 'Processing...' : 'Reject Application'}
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