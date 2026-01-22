import React, { useState } from "react";
import apiService from "./apiService";

export default function NeedsCorrection({ registration, documents, fetchData, formatDate, getDocumentTypeName, navigate }) {
  const [showRejectForm, setShowRejectForm] = useState(false);
  const [showDocumentModal, setShowDocumentModal] = useState(false);
  const [currentDocument, setCurrentDocument] = useState(null);
  const [rejectionNotes, setRejectionNotes] = useState("");
  const [loading, setLoading] = useState(false);
  const [correctionNotes, setCorrectionNotes] = useState(registration.correction_notes || "");

  const getDocumentUrl = (filePath) => {
    const cleanPath = filePath.replace(/^(http:\/\/localhost\/revenue2\/|https:\/\/revenuetreasury.goserveph.com\/)/, '');
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
      return `http://localhost/revenue2/${cleanPath}`;
    }
    return `https://revenuetreasury.goserveph.com/${cleanPath}`;
  };

  const fileIcon = (fileName) => {
    const ext = fileName.split('.').pop().toLowerCase();
    if (['jpg','jpeg','png','gif'].includes(ext)) return '🖼️';
    if (['pdf'].includes(ext)) return '📄';
    return '📁';
  };

  const handleViewDocument = (doc) => {
    setCurrentDocument(doc);
    setShowDocumentModal(true);
  };

  const handleMarkAsResubmitted = async () => {
    if (!window.confirm("Mark this application as resubmitted? This will move it back to pending status.")) return;
    
    setLoading(true);
    try {
      await apiService.updateStatus(registration.id, 'resubmitted');
      alert("✅ Marked as Resubmitted!");
      await fetchData();
    } catch (err) { 
      alert(`❌ ${err.message}`); 
    } finally { 
      setLoading(false); 
    }
  };

  const handleReject = async () => {
    if (!rejectionNotes.trim()) { 
      alert("Enter rejection notes"); 
      return; 
    }
    setLoading(true);
    try {
      await apiService.rejectApplication(registration.id, rejectionNotes);
      alert("✅ Application marked as needs correction!");
      setShowRejectForm(false); 
      setRejectionNotes(""); 
      await fetchData();
    } catch (err) { 
      alert(`❌ ${err.message}`); 
    } finally { 
      setLoading(false); 
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 py-6">
      <div className="max-w-7xl mx-auto px-4">

        {/* Header / Status Card */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-6">
          <div className="flex items-center justify-between mb-4">
            <div>
              <button onClick={() => navigate(-1)} className="text-gray-600 hover:text-blue-600 mb-1 flex items-center">
                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back
              </button>
              <h1 className="text-2xl font-bold text-gray-900">Application Needs Correction</h1>
              <p className="text-gray-600 mt-1">Reference: <span className="font-medium">{registration.reference_number}</span></p>
            </div>
            <span className="bg-orange-100 text-orange-800 px-3 py-1 rounded-full font-semibold">NEEDS CORRECTION</span>
          </div>

          {/* Progress Bar */}
          <div className="mt-4">
            <div className="flex justify-between text-xs text-gray-500 mb-1">
              <span>Submitted</span>
              <span>For Inspection</span>
              <span>Assessed</span>
              <span>Approved</span>
            </div>
            <div className="w-full h-3 bg-gray-200 rounded-full overflow-hidden">
              <div className="h-3 bg-orange-400 rounded-full" style={{ width: '25%' }}></div>
            </div>
          </div>

          {/* Correction Notes Box */}
          <div className="mt-6 p-4 bg-orange-50 border border-orange-200 rounded-lg">
            <div className="flex items-start">
              <svg className="w-5 h-5 text-orange-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.106 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
              <div>
                <h3 className="font-semibold text-orange-800 mb-1">Correction Required</h3>
                <p className="text-orange-700 whitespace-pre-line">{correctionNotes}</p>
                <div className="text-xs text-orange-600 mt-2">
                  Status updated on: {formatDate(registration.updated_at || registration.created_at, 'MMM d, yyyy • h:mm a')}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Documents + Admin Actions */}
        <div className="flex flex-col lg:flex-row gap-6">

          {/* Documents */}
          <div className="flex-1 bg-white rounded-xl shadow-lg p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-4 flex items-center">
              <svg className="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Uploaded Documents ({documents.length})
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {documents.map((doc, i) => (
                <div key={i} className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition flex flex-col">
                  <div className="flex items-center mb-2">
                    <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-3 text-2xl">
                      {fileIcon(doc.file_name)}
                    </div>
                    <div className="flex-1">
                      <h3 className="font-semibold text-gray-900">{getDocumentTypeName(doc.document_type)}</h3>
                      <p className="text-sm text-gray-500 truncate" title={doc.file_name}>{doc.file_name}</p>
                    </div>
                  </div>
                  <button
                    onClick={() => handleViewDocument(doc)}
                    className="mt-auto w-full bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 rounded text-sm transition flex items-center justify-center"
                  >
                    <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    View Document
                  </button>
                </div>
              ))}
            </div>
          </div>

          {/* Admin Actions - ONLY TWO BUTTONS */}
          <div className="w-full lg:w-80 flex flex-col gap-4 p-6 bg-blue-50 rounded-xl shadow-lg border border-blue-100">
            <h2 className="text-lg font-bold text-gray-900 mb-3 flex items-center">
              <svg className="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              Admin Actions
            </h2>
            
            {/* ONLY TWO BUTTONS */}
            <button
              onClick={handleMarkAsResubmitted}
              disabled={loading}
              className="bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white px-4 py-3 rounded-lg flex items-center justify-center shadow hover:shadow-md transition"
            >
              <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              Mark as Resubmitted
            </button>
            <button
              onClick={() => setShowRejectForm(true)}
              disabled={loading}
              className="bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white px-4 py-3 rounded-lg flex items-center justify-center shadow hover:shadow-md transition"
            >
              <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.106 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
              Reject Application
            </button>
            
            {/* Application Info */}
            <div className="mt-6 pt-4 border-t border-blue-200">
              <h3 className="text-sm font-semibold text-gray-700 mb-3">Application Info</h3>
              <div className="space-y-3">
                <div className="flex justify-between items-center">
                  <span className="text-xs text-gray-600 min-w-[80px]">Status</span>
                  <span className="text-xs font-medium text-orange-700 bg-orange-100 px-2 py-1 rounded">Needs Correction</span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-xs text-gray-600 min-w-[80px]">Documents</span>
                  <span className="text-xs font-medium text-blue-700">{documents.length} files</span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-xs text-gray-600 min-w-[80px]">Submitted</span>
                  <span className="text-xs text-gray-700">{formatDate(registration.date_registered, 'MMM d, yyyy')}</span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-xs text-gray-600 min-w-[80px]">Reference</span>
                  <span className="text-xs font-mono text-gray-700" title={registration.reference_number}>
                    {registration.reference_number}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Registration Details - OWNER INFO FIRST, PROPERTY INFO SECOND */}
        <div className="bg-white rounded-xl shadow-lg p-6 mt-6">
          <h2 className="text-xl font-bold text-gray-900 mb-4 flex items-center">
            <svg className="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Application Details
          </h2>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* OWNER INFO - FIRST */}
            <div className="bg-gray-50 p-5 rounded-lg space-y-3">
              <h3 className="font-semibold text-gray-700 mb-3 flex items-center">
                <svg className="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Owner Information
              </h3>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Full Name</label>
                  <p className="text-gray-900 font-medium text-sm">{registration.owner_name}</p>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Sex</label>
                  <p className="text-gray-900 font-medium text-sm">{registration.sex || 'N/A'}</p>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Marital Status</label>
                  <p className="text-gray-900 font-medium text-sm">{registration.marital_status || 'N/A'}</p>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Birthdate</label>
                  <p className="text-gray-900 font-medium text-sm">
                    {registration.birthdate ? new Date(registration.birthdate).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A'}
                  </p>
                </div>
                <div className="col-span-2">
                  <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Address</label>
                  <p className="text-gray-900 font-medium text-sm">{registration.owner_address}</p>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Contact</label>
                  <p className="text-gray-900 font-medium text-sm">{registration.contact_number}</p>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Email</label>
                  <p className="text-gray-900 font-medium text-sm truncate">{registration.email_address}</p>
                </div>
                <div className="col-span-2">
                  <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">TIN</label>
                  <p className="text-gray-900 font-medium text-sm">{registration.tin || 'N/A'}</p>
                </div>
              </div>

              {/* Date Registered */}
              <div className="mt-4 pt-3 border-t border-gray-300">
                <div className="text-xs text-gray-500 flex items-center">
                  <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  Date Registered: {formatDate(registration.date_registered, 'MMMM d, yyyy at hh:mm a')}
                </div>
              </div>
            </div>

            {/* PROPERTY INFO - SECOND */}
            <div className="bg-gray-50 p-5 rounded-lg space-y-3">
              <h3 className="font-semibold text-gray-700 mb-3 flex items-center">
                <svg className="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Property Information
              </h3>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Location Address</label>
                  <p className="text-gray-900 font-medium text-sm">{registration.location_address}</p>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Barangay</label>
                  <p className="text-gray-900 font-medium text-sm">{registration.barangay}</p>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">District</label>
                  <p className="text-gray-900 font-medium text-sm">{registration.district}</p>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">City/Municipality</label>
                  <p className="text-gray-900 font-medium text-sm">{registration.municipality_city}</p>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Province</label>
                  <p className="text-gray-900 font-medium text-sm">{registration.province}</p>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Zip Code</label>
                  <p className="text-gray-900 font-medium text-sm">{registration.zip_code}</p>
                </div>
                <div className="col-span-2">
                  <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Has Building</label>
                  <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${registration.has_building === 'yes' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                    {registration.has_building === 'yes' ? 'Yes' : 'No'}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* View Document Modal - Same as Pending.jsx */}
        {showDocumentModal && currentDocument && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 backdrop-blur-sm">
            <div className="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col transform transition-all duration-300 scale-100">
              <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gradient-to-r from-blue-50 to-indigo-50">
                <div className="flex items-center">
                  <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3 text-lg">
                    {fileIcon(currentDocument.file_name)}
                  </div>
                  <div>
                    <h3 className="text-lg font-bold text-gray-900">{getDocumentTypeName(currentDocument.document_type)}</h3>
                    <p className="text-sm text-gray-600 truncate max-w-md">{currentDocument.file_name}</p>
                  </div>
                </div>
                <div className="flex items-center space-x-2">
                  <button
                    onClick={() => window.open(getDocumentUrl(currentDocument.file_path), '_blank')}
                    className="text-sm font-medium text-blue-600 hover:text-blue-700 px-3 py-1.5 rounded-lg border border-blue-200 hover:border-blue-300 hover:bg-blue-50 transition-colors flex items-center"
                  >
                    <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Download
                  </button>
                  <button 
                    onClick={() => setShowDocumentModal(false)} 
                    className="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-1.5 rounded-full transition-colors"
                  >
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>
              
              <div className="flex-1 overflow-auto p-4">
                <div className="bg-gray-100 rounded-lg border border-gray-300 flex items-center justify-center min-h-[60vh]">
                  {currentDocument.file_name.toLowerCase().endsWith('.pdf') ? (
                    <iframe 
                      src={getDocumentUrl(currentDocument.file_path)}
                      className="w-full h-[60vh] border-0 rounded-lg"
                      title={currentDocument.file_name}
                    />
                  ) : currentDocument.file_name.toLowerCase().match(/\.(jpg|jpeg|png|gif)$/) ? (
                    <img 
                      src={getDocumentUrl(currentDocument.file_path)} 
                      alt={currentDocument.file_name}
                      className="max-w-full max-h-[60vh] object-contain rounded-lg"
                    />
                  ) : (
                    <div className="text-center p-8">
                      <div className="text-4xl mb-4">📄</div>
                      <h4 className="text-lg font-semibold text-gray-700 mb-2">Document Preview Not Available</h4>
                      <p className="text-gray-600 mb-4">This file type cannot be previewed in the browser.</p>
                      <button
                        onClick={() => window.open(getDocumentUrl(currentDocument.file_path), '_blank')}
                        className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors"
                      >
                        Download File
                      </button>
                    </div>
                  )}
                </div>
              </div>
              
              <div className="px-6 py-4 border-t border-gray-200 bg-gray-50">
                <div className="flex justify-between items-center">
                  <div className="text-sm text-gray-600">
                    <div className="flex items-center space-x-4">
                      <div>
                        <span className="font-medium">Document Type:</span> {getDocumentTypeName(currentDocument.document_type)}
                      </div>
                      <div>
                        <span className="font-medium">Uploaded:</span> {formatDate(currentDocument.uploaded_at || registration.date_registered, 'MMM d, yyyy • h:mm a')}
                      </div>
                    </div>
                  </div>
                  <div className="flex space-x-2">
                    <button
                      onClick={() => {
                        const url = getDocumentUrl(currentDocument.file_path);
                        window.open(url, '_blank');
                      }}
                      className="text-sm font-medium text-blue-600 hover:text-blue-700 px-4 py-2 rounded-lg border border-blue-200 hover:border-blue-300 hover:bg-blue-50 transition-colors"
                    >
                      Open in New Tab
                    </button>
                    <button
                      onClick={() => setShowDocumentModal(false)}
                      className="text-sm font-medium text-gray-700 hover:text-gray-900 px-4 py-2 rounded-lg border border-gray-300 hover:border-gray-400 hover:bg-gray-100 transition-colors"
                    >
                      Close
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Reject Modal */}
        {showRejectForm && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div className="bg-white rounded-xl shadow-2xl max-w-md w-full">
              <div className="p-6">
                <div className="flex justify-between items-center mb-4">
                  <h3 className="text-lg font-semibold text-gray-900">Reject Application</h3>
                  <button onClick={() => setShowRejectForm(false)} className="text-gray-400 hover:text-gray-600">
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Rejection Notes *</label>
                    <textarea 
                      value={rejectionNotes} 
                      onChange={(e) => setRejectionNotes(e.target.value)} 
                      className="w-full px-3 py-2 border border-gray-300 rounded-md h-32" 
                      placeholder="Explain why the application is being rejected..." 
                      required 
                    />
                  </div>
                  <div className="flex gap-3 pt-4">
                    <button 
                      onClick={handleReject} 
                      disabled={loading}
                      className="flex-1 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white py-2 rounded flex items-center justify-center"
                    >
                      {loading ? (
                        <>
                          <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                          Processing...
                        </>
                      ) : 'Reject Application'}
                    </button>
                    <button 
                      onClick={() => setShowRejectForm(false)} 
                      disabled={loading}
                      className="flex-1 bg-gray-300 hover:bg-gray-400 disabled:bg-gray-200 py-2 rounded"
                    >
                      Cancel
                    </button>
                  </div>
                  <p className="text-sm text-red-600">
                    This will reject the application and notify the citizen.
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}

      </div>
    </div>
  );
}