import React, { useState } from "react";
import apiService from "./apiService";

export default function Resubmitted({ registration, documents, fetchData, formatDate, getDocumentTypeName, navigate }) {
  const [showInspectionForm, setShowInspectionForm] = useState(false);
  const [showRejectForm, setShowRejectForm] = useState(false);
  const [inspectionDate, setInspectionDate] = useState("");
  const [assessorName, setAssessorName] = useState("");
  const [rejectionNotes, setRejectionNotes] = useState("");
  const [loading, setLoading] = useState(false);

  const handleScheduleInspection = async () => {
    if (!inspectionDate || !assessorName) {
      alert("Please fill all required fields");
      return;
    }

    setLoading(true);
    try {
      await apiService.scheduleInspection(registration.id, {
        scheduled_date: inspectionDate,
        assessor_name: assessorName
      });
      alert("✅ Inspection scheduled successfully!");
      setShowInspectionForm(false);
      setInspectionDate("");
      setAssessorName("");
      await fetchData();
    } catch (error) {
      alert(`❌ Error: ${error.message}`);
    } finally {
      setLoading(false);
    }
  };

  const handleReject = async () => {
    if (!rejectionNotes.trim()) {
      alert("Please enter rejection notes");
      return;
    }

    setLoading(true);
    try {
      await apiService.rejectApplication(registration.id, rejectionNotes);
      alert("✅ Application marked as 'Needs Correction'");
      setShowRejectForm(false);
      setRejectionNotes("");
      await fetchData();
    } catch (error) {
      alert(`❌ Error: ${error.message}`);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 py-6">
      <div className="max-w-6xl mx-auto px-4">
        {/* Header */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-6">
          <div className="flex items-center justify-between">
            <div>
              <button
                onClick={() => navigate(-1)}
                className="text-gray-600 hover:text-blue-600 mb-4 flex items-center"
              >
                ← Back
              </button>
              <h1 className="text-2xl font-bold text-gray-900">Resubmitted Application</h1>
              <p className="text-gray-600">Reference: {registration.reference_number}</p>
            </div>
            <span className="bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-sm font-semibold">
              RESUBMITTED
            </span>
          </div>
        </div>

        {/* Previous Correction Notes */}
        {registration.correction_notes && (
          <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <h3 className="font-semibold text-yellow-800 mb-2">Previous Correction Notes:</h3>
            <p className="text-yellow-700">{registration.correction_notes}</p>
          </div>
        )}

        {/* Documents Section */}
        {documents.length > 0 && (
          <div className="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 className="text-xl font-bold text-gray-900 mb-4">Uploaded Documents ({documents.length})</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {documents.map((doc, index) => (
                <div key={index} className="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition">
                  <div className="flex items-start mb-2">
                    <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                      <span className="text-blue-600">📄</span>
                    </div>
                    <div>
                      <h3 className="font-semibold text-gray-900">{getDocumentTypeName(doc.document_type)}</h3>
                      <p className="text-sm text-gray-600 truncate">{doc.file_name}</p>
                    </div>
                  </div>
                  <button
                    onClick={() => window.open(`http://localhost/revenue2/${doc.file_path}`, '_blank')}
                    className="w-full mt-2 bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 rounded text-sm"
                  >
                    View Document
                  </button>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Registration Details */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h2 className="text-xl font-bold text-gray-900 mb-4">Registration Details</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 className="font-semibold text-gray-700 mb-2">Property Information</h3>
              <div className="space-y-2">
                <p><span className="font-medium">Type:</span> {registration.property_type}</p>
                <p><span className="font-medium">Address:</span> {registration.location_address}</p>
                <p><span className="font-medium">Barangay:</span> {registration.barangay}</p>
                <p><span className="font-medium">City:</span> {registration.municipality_city}</p>
              </div>
            </div>
            <div>
              <h3 className="font-semibold text-gray-700 mb-2">Owner Information</h3>
              <div className="space-y-2">
                <p><span className="font-medium">Name:</span> {registration.owner_name}</p>
                <p><span className="font-medium">Address:</span> {registration.owner_address}</p>
                <p><span className="font-medium">Contact:</span> {registration.contact_number}</p>
                <p><span className="font-medium">Email:</span> {registration.email_address}</p>
              </div>
            </div>
          </div>
          <div className="mt-4 pt-4 border-t border-gray-200">
            <p><span className="font-medium">Date Registered:</span> {formatDate(registration.date_registered)}</p>
            <p><span className="font-medium">Has Building:</span> {registration.has_building === 'yes' ? 'Yes' : 'No'}</p>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h2 className="text-lg font-bold text-gray-900 mb-4">Admin Actions</h2>
          <div className="flex flex-wrap gap-4">
            <button
              onClick={() => setShowInspectionForm(true)}
              disabled={loading}
              className="bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white px-6 py-3 rounded-lg flex items-center"
            >
              <span className="mr-2">📅</span>
              Schedule Inspection
            </button>
            
            <button
              onClick={() => setShowRejectForm(true)}
              disabled={loading}
              className="bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white px-6 py-3 rounded-lg flex items-center"
            >
              <span className="mr-2">❌</span>
              Mark Needs Correction
            </button>
          </div>
        </div>

        {/* Inspection Form Modal */}
        {showInspectionForm && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div className="bg-white rounded-xl shadow-2xl max-w-md w-full">
              <div className="p-6">
                <div className="flex justify-between items-center mb-4">
                  <h3 className="text-lg font-semibold text-gray-900">Schedule Inspection</h3>
                  <button
                    onClick={() => setShowInspectionForm(false)}
                    className="text-gray-400 hover:text-gray-600"
                  >
                    ✕
                  </button>
                </div>
                
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Inspection Date *</label>
                    <input
                      type="date"
                      value={inspectionDate}
                      onChange={(e) => setInspectionDate(e.target.value)}
                      min={new Date().toISOString().split('T')[0]}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md"
                      required
                    />
                  </div>
                  
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Assessor Name *</label>
                    <input
                      type="text"
                      value={assessorName}
                      onChange={(e) => setAssessorName(e.target.value)}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md"
                      placeholder="Enter assessor's name"
                      required
                    />
                  </div>
                  
                  <div className="flex gap-3 pt-4">
                    <button
                      onClick={handleScheduleInspection}
                      disabled={loading}
                      className="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white py-2 rounded"
                    >
                      {loading ? 'Scheduling...' : 'Schedule'}
                    </button>
                    <button
                      onClick={() => setShowInspectionForm(false)}
                      disabled={loading}
                      className="flex-1 bg-gray-300 hover:bg-gray-400 disabled:bg-gray-200 py-2 rounded"
                    >
                      Cancel
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Rejection Form Modal */}
        {showRejectForm && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div className="bg-white rounded-xl shadow-2xl max-w-md w-full">
              <div className="p-6">
                <div className="flex justify-between items-center mb-4">
                  <h3 className="text-lg font-semibold text-gray-900">Mark as Needs Correction</h3>
                  <button
                    onClick={() => setShowRejectForm(false)}
                    className="text-gray-400 hover:text-gray-600"
                  >
                    ✕
                  </button>
                </div>
                
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Correction Notes *</label>
                    <textarea
                      value={rejectionNotes}
                      onChange={(e) => setRejectionNotes(e.target.value)}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md h-32"
                      placeholder="Explain what needs to be corrected..."
                      required
                    />
                  </div>
                  
                  <div className="flex gap-3 pt-4">
                    <button
                      onClick={handleReject}
                      disabled={loading}
                      className="flex-1 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white py-2 rounded"
                    >
                      {loading ? 'Submitting...' : 'Mark Needs Correction'}
                    </button>
                    <button
                      onClick={() => setShowRejectForm(false)}
                      disabled={loading}
                      className="flex-1 bg-gray-300 hover:bg-gray-400 disabled:bg-gray-200 py-2 rounded"
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
    </div>
  );
}