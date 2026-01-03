import React, { useState } from "react";
import apiService from "./apiService";

export default function NeedsCorrection({ registration, documents, fetchData, formatDate, getDocumentTypeName, navigate }) {
  const [loading, setLoading] = useState(false);
  const [showResubmitForm, setShowResubmitForm] = useState(false);

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

  const handleMarkAsResubmitted = async () => {
    if (!window.confirm("Mark this application as resubmitted?\n\nThis will change status to 'pending' for review.")) return;
    
    setLoading(true);
    try {
      await apiService.markAsResubmitted(registration.id);
      alert("✅ Application marked as resubmitted!");
      await fetchData();
    } catch (error) {
      alert(`❌ Error: ${error.message}`);
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
              <button onClick={() => navigate(-1)} className="text-gray-600 hover:text-blue-600 mb-1 flex items-center">← Back</button>
              <h1 className="text-2xl font-bold text-gray-900">Needs Correction Application</h1>
              <p className="text-gray-600 mt-1">Reference: <span className="font-medium">{registration.reference_number}</span></p>
            </div>
            <span className="bg-red-100 text-red-800 px-3 py-1 rounded-full font-semibold">NEEDS CORRECTION</span>
          </div>

          {/* Progress Bar - Still at Pending stage */}
          <div className="mt-4">
            <div className="flex justify-between text-xs text-gray-500 mb-1">
              <span>Pending</span>
              <span>For Inspection</span>
              <span>Assessed</span>
              <span>Approved</span>
            </div>
            <div className="w-full h-3 bg-gray-200 rounded-full overflow-hidden">
              <div className="h-3 bg-red-400 rounded-full" style={{ width: '25%' }}></div>
            </div>
          </div>
        </div>

        {/* Correction Notes */}
        {registration.correction_notes && (
          <div className="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-lg">
            <div className="flex items-start">
              <div className="flex-shrink-0">
                <svg className="h-5 w-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd"/>
                </svg>
              </div>
              <div className="ml-3">
                <h3 className="text-sm font-semibold text-red-800 mb-1">Correction Required</h3>
                <p className="text-red-700">{registration.correction_notes}</p>
              </div>
            </div>
          </div>
        )}

        {/* Documents + Admin Actions */}
        <div className="flex flex-col lg:flex-row gap-6">

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
          <div className="w-full lg:w-64 flex flex-col gap-4 p-6 bg-red-50 rounded-xl shadow-lg">
            <h2 className="text-lg font-bold text-gray-900 mb-2">Admin Actions</h2>
            <button
              onClick={handleMarkAsResubmitted}
              disabled={loading}
              className="bg-gray-800 hover:bg-gray-900 disabled:bg-gray-400 text-white px-4 py-3 rounded-lg flex items-center justify-center shadow hover:shadow-md transition"
            >
              📝 Mark as Resubmitted
            </button>
            <p className="text-xs text-gray-600 mt-2">
              This will change status back to "pending" and allow the application to proceed.
            </p>
          </div>
        </div>

        {/* Registration Details */}
        <div className="bg-white rounded-xl shadow-lg p-6 mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">

          {/* Property Info */}
          <div className="bg-gray-50 p-4 rounded-lg space-y-2">
            <h3 className="font-semibold text-gray-700 mb-2">Property Info</h3>
            <p><span className="font-medium">Location:</span> {registration.location_address}</p>
            <p><span className="font-medium">Barangay:</span> {registration.barangay}</p>
            <p><span className="font-medium">District:</span> {registration.district}</p>
            <p><span className="font-medium">City/Municipality:</span> {registration.municipality_city}</p>
            <p><span className="font-medium">Province:</span> {registration.province}</p>
            <p><span className="font-medium">Zip Code:</span> {registration.zip_code}</p>
            <p><span className="font-medium">Property Type:</span> {registration.property_type}</p>
            <p><span className="font-medium">Has Building:</span> {registration.has_building === 'yes' ? 'Yes' : 'No'}</p>
          </div>

          {/* Owner Info */}
          <div className="bg-gray-50 p-4 rounded-lg flex flex-col justify-between">
            <div className="space-y-2">
              <h3 className="font-semibold text-gray-700 mb-2">Owner Info</h3>
              <p><span className="font-medium">Name:</span> {registration.owner_name}</p>
              <p><span className="font-medium">Address:</span> {registration.owner_address}</p>
              <p><span className="font-medium">Contact:</span> {registration.contact_number}</p>
              <p><span className="font-medium">Email:</span> {registration.email_address}</p>
            </div>

            {/* Date Registered at bottom */}
            <div className="mt-4 text-sm text-gray-500 border-t border-gray-300 pt-2">
              Date Registered: {formatDate(registration.date_registered, 'MMMM d, yyyy at hh:mm a')}
            </div>
          </div>

        </div>

      </div>
    </div>
  );
}