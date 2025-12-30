import React, { useState } from "react";
import apiService from "./apiService";

export default function ForInspection({ registration, documents, fetchData, formatDate, getDocumentTypeName, navigate }) {
  const [loading, setLoading] = useState(false);

  const handleMarkAsAssessed = async () => {
    if (window.confirm("Mark this property as assessed?\n\nThis will move it to the assessment phase.")) {
      setLoading(true);
      try {
        await apiService.markAsAssessed(registration.id);
        alert("✅ Property marked as assessed!");
        await fetchData();
      } catch (error) {
        alert(`❌ Error: ${error.message}`);
      } finally {
        setLoading(false);
      }
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
              <h1 className="text-2xl font-bold text-gray-900">Scheduled for Inspection</h1>
              <p className="text-gray-600">Reference: {registration.reference_number}</p>
            </div>
            <span className="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-semibold">
              FOR INSPECTION
            </span>
          </div>
        </div>

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

        {/* Action Button */}
        <div className="bg-white rounded-xl shadow-lg p-6">
          <h2 className="text-lg font-bold text-gray-900 mb-4">Admin Actions</h2>
          <div className="flex flex-wrap gap-4">
            <button
              onClick={handleMarkAsAssessed}
              disabled={loading}
              className="bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white px-6 py-3 rounded-lg flex items-center"
            >
              <span className="mr-2">📊</span>
              {loading ? 'Processing...' : 'Mark as Assessed'}
            </button>
            
            <p className="text-sm text-gray-600">
              Click this button after the property inspection is complete to move it to assessment phase.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}