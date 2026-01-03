import React, { useState } from "react";
import apiService from "./apiService";

export default function Resubmitted({ registration, documents, fetchData, formatDate, getDocumentTypeName, navigate }) {
  const [showInspectionForm, setShowInspectionForm] = useState(false);
  const [showRejectForm, setShowRejectForm] = useState(false);
  const [inspectionDate, setInspectionDate] = useState("");
  const [assessorName, setAssessorName] = useState("");
  const [rejectionNotes, setRejectionNotes] = useState("");
  const [loading, setLoading] = useState(false);

  // Get document URL
  const getDocumentUrl = (filePath) => {
    const isLocalhost = window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1";
    let cleanPath = filePath.replace(/^(http:\/\/|https:\/\/)[^\/]+\//, "").replace(/^\/+/, "");
    const baseUrl = isLocalhost ? "http://localhost/revenue2" : "https://revenuetreasury.goserveph.com";
    return `${baseUrl}/${cleanPath}`;
  };

  // File icon based on extension
  const fileIcon = (fileName) => {
    const ext = fileName.split('.').pop().toLowerCase();
    if (['jpg','jpeg','png','gif'].includes(ext)) return '🖼️';
    if (['pdf'].includes(ext)) return '📄';
    return '📁';
  };

  const handleScheduleInspection = async () => {
    if (!inspectionDate || !assessorName) { alert("Please fill all fields"); return; }
    setLoading(true);
    try {
      await apiService.scheduleInspection(registration.id, { scheduled_date: inspectionDate, assessor_name: assessorName });
      alert("✅ Inspection scheduled!");
      setShowInspectionForm(false); setInspectionDate(""); setAssessorName(""); await fetchData();
    } catch (err) { alert(`❌ ${err.message}`); } finally { setLoading(false); }
  };

  const handleReject = async () => {
    if (!rejectionNotes.trim()) { alert("Enter rejection notes"); return; }
    setLoading(true);
    try {
      await apiService.rejectApplication(registration.id, rejectionNotes);
      alert("✅ Marked as Needs Correction");
      setShowRejectForm(false); setRejectionNotes(""); await fetchData();
    } catch (err) { alert(`❌ ${err.message}`); } finally { setLoading(false); }
  };

  return (
    <div className="min-h-screen bg-gray-50 py-6">
      <div className="max-w-7xl mx-auto px-4">

        {/* Header / Status Card */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-6">
          <div className="flex items-center justify-between mb-4">
            <div>
              <button onClick={() => navigate(-1)} className="text-gray-600 hover:text-blue-600 mb-1 flex items-center">← Back</button>
              <h1 className="text-2xl font-bold text-gray-900">Resubmitted Application</h1>
              <p className="text-gray-600 mt-1">Reference: <span className="font-medium">{registration.reference_number}</span></p>
            </div>
            <span className="bg-orange-100 text-orange-800 px-3 py-1 rounded-full font-semibold">RESUBMITTED</span>
          </div>

          {/* Progress Bar - stays at Pending */}
          <div className="mt-4">
            <div className="flex justify-between text-xs text-gray-500 mb-1">
              <span>Pending</span>
              <span>For Inspection</span>
              <span>Assessed</span>
              <span>Approved</span>
            </div>
            <div className="w-full h-3 bg-gray-200 rounded-full overflow-hidden">
              <div className="h-3 bg-yellow-400 rounded-full" style={{ width: '25%' }}></div>
            </div>
          </div>
        </div>

        {/* Previous Correction Notes */}
        {registration.correction_notes && (
          <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <h3 className="font-semibold text-yellow-800 mb-2">Previous Correction Notes:</h3>
            <p className="text-yellow-700">{registration.correction_notes}</p>
          </div>
        )}

        {/* Documents + Admin Actions */}
        <div className="flex flex-col lg:flex-row gap-6 mb-6">

          {/* Documents */}
          <div className="flex-1 bg-white rounded-xl shadow-lg p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-4">Uploaded Documents ({documents.length})</h2>
            <div className="grid grid-cols-2 gap-4">
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
                    onClick={() => window.open(getDocumentUrl(doc.file_path), '_blank')}
                    className="mt-auto w-full bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 rounded text-sm transition"
                  >
                    View
                  </button>
                </div>
              ))}
            </div>
          </div>

          {/* Admin Actions */}
          <div className="w-full lg:w-64 flex flex-col gap-4 p-6 bg-blue-50 rounded-xl shadow-lg">
            <h2 className="text-lg font-bold text-gray-900 mb-2">Admin Actions</h2>
            <button
              onClick={() => setShowInspectionForm(true)}
              disabled={loading}
              className="bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white px-4 py-3 rounded-lg flex items-center justify-center shadow hover:shadow-md transition"
            >
              📅 Schedule Inspection
            </button>
            <button
              onClick={() => setShowRejectForm(true)}
              disabled={loading}
              className="bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white px-4 py-3 rounded-lg flex items-center justify-center shadow hover:shadow-md transition"
            >
              ❌ Mark Needs Correction
            </button>
          </div>
        </div>

        {/* Registration Details */}
        <div className="bg-white rounded-xl shadow-lg p-6 grid grid-cols-1 md:grid-cols-2 gap-6">

          {/* Property Info - same as Pending.jsx */}
          <div className="bg-gray-50 p-4 rounded-lg space-y-2">
            <h3 className="font-semibold text-gray-700 mb-2">Property Info</h3>
            <p><span className="font-medium">Location:</span> {registration.location_address}</p>
            <p><span className="font-medium">Barangay:</span> {registration.barangay}</p>
            <p><span className="font-medium">District:</span> {registration.district}</p>
            <p><span className="font-medium">City/Municipality:</span> {registration.municipality_city}</p>
            <p><span className="font-medium">Province:</span> {registration.province}</p>
            <p><span className="font-medium">Zip Code:</span> {registration.zip_code}</p>
            <p><span className="font-medium">Has Building:</span> {registration.has_building === 'yes' ? 'Yes' : 'No'}</p>
          </div>

          {/* Owner Info - same as Pending.jsx */}
          <div className="bg-gray-50 p-4 rounded-lg flex flex-col justify-between">
            <div className="space-y-2">
              <h3 className="font-semibold text-gray-700 mb-2">Owner Info</h3>
              <p><span className="font-medium">Name:</span> {registration.owner_name}</p>
              <p><span className="font-medium">Sex:</span> {registration.sex || 'N/A'}</p>
              <p><span className="font-medium">Marital Status:</span> {registration.marital_status || 'N/A'}</p>
              <p><span className="font-medium">Birthdate:</span> {registration.birthdate ? new Date(registration.birthdate).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
              <p><span className="font-medium">Address:</span> {registration.owner_address}</p>
              <p><span className="font-medium">Contact:</span> {registration.contact_number}</p>
              <p><span className="font-medium">Email:</span> {registration.email_address}</p>
            </div>
            <div className="mt-4 text-sm text-gray-500 border-t border-gray-300 pt-2">
              Date Registered: {formatDate(registration.date_registered, 'MMMM d, yyyy at hh:mm a')}
            </div>
          </div>

        </div>

        {/* Modals */}
        {showInspectionForm && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div className="bg-white rounded-xl shadow-2xl max-w-md w-full">
              <div className="p-6">
                <div className="flex justify-between items-center mb-4">
                  <h3 className="text-lg font-semibold text-gray-900">Schedule Inspection</h3>
                  <button onClick={() => setShowInspectionForm(false)} className="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Inspection Date *</label>
                    <input type="date" value={inspectionDate} onChange={(e) => setInspectionDate(e.target.value)} min={new Date().toISOString().split('T')[0]} className="w-full px-3 py-2 border border-gray-300 rounded-md" required />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Assessor Name *</label>
                    <input type="text" value={assessorName} onChange={(e) => setAssessorName(e.target.value)} className="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Enter assessor's name" required />
                  </div>
                  <div className="flex gap-3 pt-4">
                    <button onClick={handleScheduleInspection} disabled={loading} className="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white py-2 rounded">{loading ? 'Scheduling...' : 'Schedule'}</button>
                    <button onClick={() => setShowInspectionForm(false)} disabled={loading} className="flex-1 bg-gray-300 hover:bg-gray-400 disabled:bg-gray-200 py-2 rounded">Cancel</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {showRejectForm && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div className="bg-white rounded-xl shadow-2xl max-w-md w-full">
              <div className="p-6">
                <div className="flex justify-between items-center mb-4">
                  <h3 className="text-lg font-semibold text-gray-900">Mark as Needs Correction</h3>
                  <button onClick={() => setShowRejectForm(false)} className="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Correction Notes *</label>
                    <textarea value={rejectionNotes} onChange={(e) => setRejectionNotes(e.target.value)} className="w-full px-3 py-2 border border-gray-300 rounded-md h-32" placeholder="Explain what needs to be corrected..." required />
                  </div>
                  <div className="flex gap-3 pt-4">
                    <button onClick={handleReject} disabled={loading} className="flex-1 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white py-2 rounded">{loading ? 'Submitting...' : 'Mark Needs Correction'}</button>
                    <button onClick={() => setShowRejectForm(false)} disabled={loading} className="flex-1 bg-gray-300 hover:bg-gray-400 disabled:bg-gray-200 py-2 rounded">Cancel</button>
                  </div>
                  <p className="text-sm text-red-600">This will change status to "needs_correction" and notify the citizen.</p>
                </div>
              </div>
            </div>
          </div>
        )}

      </div>
    </div>
  );
}
