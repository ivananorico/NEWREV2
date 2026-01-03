import React, { useState } from "react";
import apiService from "./apiService";

export default function ForInspection({ registration, documents, fetchData, formatDate, getDocumentTypeName, navigate }) {
  const [loading, setLoading] = useState(false);

  // Get Document Base URL
  const getDocumentBaseUrl = () => {
    const envApiUrl = import.meta.env.VITE_API_URL;
    if (envApiUrl) {
      return envApiUrl.replace('/backend', '');
    }
    
    const isLocalhost = window.location.hostname === "localhost" || 
                        window.location.hostname === "127.0.0.1";
    
    if (isLocalhost) {
      return "http://localhost/revenue2";
    }
    return "https://revenuetreasury.goserveph.com";
  };

  // Function to get document URL
  const getDocumentUrl = (filePath) => {
    const baseUrl = getDocumentBaseUrl();
    
    let cleanPath = filePath.trim();
    cleanPath = cleanPath.replace(/^(http:\/\/|https:\/\/)[^\/]+\//, '');
    cleanPath = cleanPath.replace(/^\/+/, '');
    
    if (cleanPath.startsWith('revenue2/')) {
      cleanPath = cleanPath.replace('revenue2/', '');
    }
    
    return `${baseUrl}/${cleanPath}`;
  };

  const fileIcon = (fileName) => {
    const ext = fileName.split('.').pop().toLowerCase();
    if (['jpg','jpeg','png','gif'].includes(ext)) return '🖼️';
    if (['pdf'].includes(ext)) return '📄';
    return '📁';
  };

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
      <div className="max-w-7xl mx-auto px-4">

        {/* Header / Status Card */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-6">
          <div className="flex items-center justify-between mb-4">
            <div>
              <button onClick={() => navigate(-1)} className="text-gray-600 hover:text-blue-600 mb-1 flex items-center">← Back</button>
              <h1 className="text-2xl font-bold text-gray-900">Scheduled for Inspection</h1>
              <p className="text-gray-600 mt-1">Reference: <span className="font-medium">{registration.reference_number}</span></p>
            </div>
            <span className="bg-blue-100 text-blue-800 px-3 py-1 rounded-full font-semibold">FOR INSPECTION</span>
          </div>

          {/* Progress Bar */}
          <div className="mt-4">
            <div className="flex justify-between text-xs text-gray-500 mb-1">
              <span>Pending</span>
              <span className="font-bold">For Inspection</span>
              <span>Assessed</span>
              <span>Approved</span>
            </div>
            <div className="w-full h-3 bg-gray-200 rounded-full overflow-hidden">
              <div className="h-3 bg-blue-500 rounded-full" style={{ width: '50%' }}></div>
            </div>
          </div>
        </div>

        {/* Inspection Details Card - USING CORRECT FIELD NAMES */}
        {registration.inspection_date && registration.inspector_name && (
          <div className="bg-blue-50 border border-blue-200 rounded-xl shadow-lg p-6 mb-6">
            <div className="flex items-center mb-4">
              <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                <span className="text-blue-600 text-xl">📅</span>
              </div>
              <div>
                <h2 className="text-xl font-bold text-gray-900">Inspection Scheduled</h2>
                <p className="text-gray-600">Property inspection has been scheduled</p>
              </div>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="bg-white rounded-lg p-4">
                <div className="text-sm text-gray-500 mb-1">Inspection Date</div>
                <div className="font-semibold text-gray-900 text-lg">
                  {formatDate(registration.inspection_date, 'MMMM d, yyyy')}
                </div>
                <div className="text-sm text-gray-600 mt-1">
                  {formatDate(registration.inspection_date, 'EEEE')}
                </div>
              </div>
              <div className="bg-white rounded-lg p-4">
                <div className="text-sm text-gray-500 mb-1">Assigned Assessor</div>
                <div className="font-semibold text-gray-900 text-lg">{registration.inspector_name}</div>
                <div className="text-sm text-gray-600 mt-1">Will conduct the inspection</div>
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
          <div className="w-full lg:w-64 flex flex-col gap-4 p-6 bg-green-50 rounded-xl shadow-lg">
            <h2 className="text-lg font-bold text-gray-900 mb-2">Admin Actions</h2>
            <button
              onClick={handleMarkAsAssessed}
              disabled={loading}
              className="bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white px-4 py-3 rounded-lg flex items-center justify-center shadow hover:shadow-md transition"
            >
              📊 Mark as Assessed
            </button>
            <p className="text-xs text-gray-600 mt-2">
              Click after inspection is complete to move to assessment phase
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
            <p><span className="font-medium">Has Building:</span> {registration.has_building === 'yes' ? 'Yes' : 'No'}</p>
          </div>

          {/* Owner Info */}
          <div className="bg-gray-50 p-4 rounded-lg flex flex-col justify-between">
            <div className="space-y-2">
              <h3 className="font-semibold text-gray-700 mb-2">Owner Info</h3>
              <p><span className="font-medium">Name:</span> {registration.owner_name}</p>
              <p><span className="font-medium">Sex:</span> {registration.sex || 'N/A'}</p>
              <p><span className="font-medium">Marital Status:</span> {registration.marital_status || 'N/A'}</p>
              <p><span className="font-medium">Birthdate:</span> {registration.birthdate ? formatDate(registration.birthdate, 'MMMM d, yyyy') : 'N/A'}</p>
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

        {/* Important Notes Section */}
        <div className="mt-6 bg-yellow-50 border border-yellow-200 rounded-xl shadow-lg p-6">
          <div className="flex items-start">
            <div className="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-4 flex-shrink-0">
              <span className="text-yellow-600">ℹ️</span>
            </div>
            <div>
              <h3 className="font-semibold text-gray-900 mb-2">Inspection Preparation Notes</h3>
              <ul className="text-gray-700 space-y-2">
                <li className="flex items-start">
                  <span className="text-yellow-500 mr-2">•</span>
                  <span>Ensure the property is accessible on the scheduled date</span>
                </li>
                <li className="flex items-start">
                  <span className="text-yellow-500 mr-2">•</span>
                  <span>Have all property documents ready for verification</span>
                </li>
                <li className="flex items-start">
                  <span className="text-yellow-500 mr-2">•</span>
                  <span>Property owner or authorized representative should be present</span>
                </li>
                <li className="flex items-start">
                  <span className="text-yellow-500 mr-2">•</span>
                  <span>Assessor will take measurements and photos during inspection</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

      </div>
    </div>
  );
}