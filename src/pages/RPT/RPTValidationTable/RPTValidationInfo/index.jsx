import React, { useState, useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";  // ← ADD THIS LINE
import Pending from "./Pending.jsx";
import ForInspection from "./ForInspection.jsx";
import Assessed from "./Assessed.jsx";
import NeedsCorrection from "./NeedsCorrection.jsx";
import Resubmitted from "./Resubmitted.jsx";
import apiService from "./apiService.js";
import { formatDate, formatCurrency, getDocumentTypeName } from "./utils.js";

export default function RPTValidationInfo() {
  const { id } = useParams();
  const navigate = useNavigate();
  
  const [registration, setRegistration] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [documents, setDocuments] = useState([]);

  const fetchData = async () => {
    if (!id || id === "undefined") {
      setError("Invalid registration ID");
      setLoading(false);
      return;
    }

    try {
      setLoading(true);
      setError(null);

      // Fetch registration
      const response = await fetch(
        `http://localhost/revenue2/backend/RPT/RPTValidationTable/get_registration_details.php?id=${id}`
      );
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.success) {
        const regData = data.data?.registration || data.data || data;
        setRegistration(regData);
        
        // Fetch documents
        try {
          const docsResponse = await fetch(
            `http://localhost/revenue2/backend/RPT/RPTValidationTable/get_documents.php?registration_id=${id}`
          );
          const docsData = await docsResponse.json();
          
          if (docsData.success) {
            setDocuments(docsData.data?.documents || docsData.data || []);
          } else {
            setDocuments([]);
          }
        } catch (docsError) {
          console.error("Error fetching documents:", docsError);
          setDocuments([]);
        }
      } else {
        throw new Error(data.message || "Failed to fetch registration");
      }
    } catch (err) {
      console.error("Fetch error:", err);
      setError(err.message || "Failed to load data");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [id]);

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading registration data...</p>
          <p className="text-sm text-gray-500">ID: {id}</p>
        </div>
      </div>
    );
  }

  if (error || !registration) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-lg shadow-md p-8 max-w-md w-full">
          <div className="text-red-500 text-4xl mb-4 text-center">⚠️</div>
          <h2 className="text-xl font-bold text-gray-900 mb-2 text-center">Error Loading Data</h2>
          <p className="text-gray-600 mb-6 text-center">{error || "Registration not found"}</p>
          
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
          
          <div className="mt-6 text-sm text-gray-500">
            <p>ID: {id}</p>
            <p>URL: {window.location.href}</p>
          </div>
        </div>
      </div>
    );
  }

  // Render based on status
  const status = registration.status?.toLowerCase() || 'pending';
  
  const commonProps = {
    registration,
    documents,
    fetchData,
    formatDate,
    formatCurrency,
    getDocumentTypeName,
    navigate
  };

  switch (status) {
    case 'pending':
      return <Pending {...commonProps} />;
    case 'for_inspection':
      return <ForInspection {...commonProps} />;
    case 'assessed':
      return <Assessed {...commonProps} />;
    case 'needs_correction':
      return <NeedsCorrection {...commonProps} />;
    case 'resubmitted':
      return <Resubmitted {...commonProps} />;
    default:
      return <Assessed {...commonProps} />;
  }
}