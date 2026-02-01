import React, { useState } from "react";
import apiService from "./apiService";

export default function ForInspection({ registration, documents, fetchData, formatDate, getDocumentTypeName, navigate }) {
    const [showRejectForm, setShowRejectForm] = useState(false);
    const [showDocumentModal, setShowDocumentModal] = useState(false);
    const [currentDocument, setCurrentDocument] = useState(null);
    const [rejectionNotes, setRejectionNotes] = useState("");
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

    // ACTION: Mark as Assessed
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

    // ACTION: Reject Application (Needs Correction)
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
        } catch (err) { 
            alert(`❌ Error: ${err.message}`); 
        } finally { 
            setLoading(false); 
        }
    };

    const handleViewDocument = (doc) => {
        setCurrentDocument(doc);
        setShowDocumentModal(true);
    };

    return (
        <div className="min-h-screen bg-gradient-to-b from-gray-50 to-blue-50 py-6">
            <div className="max-w-7xl mx-auto px-4">

                {/* Header / Status Card - Glass Effect */}
                <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-xl border border-white/30 p-6 mb-8">
                    <div className="flex items-center justify-between mb-6">
                        <div>
                            <button 
                                onClick={() => navigate(-1)} 
                                className="text-gray-600 hover:text-blue-600 mb-3 flex items-center transition-colors"
                            >
                                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back to Dashboard
                            </button>
                            <h1 className="text-3xl font-bold text-gray-900">Scheduled for Inspection</h1>
                            <p className="text-gray-600 mt-2">Reference: <span className="font-semibold text-blue-700">{registration.reference_number}</span></p>
                        </div>
                        <span className="bg-gradient-to-r from-blue-500 to-cyan-500 text-white px-4 py-2 rounded-full font-semibold shadow-lg flex items-center">
                            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                            FOR INSPECTION
                        </span>
                    </div>

                    {/* Progress Bar with Steps */}
                    <div className="mt-8">
                        <div className="flex justify-between text-sm font-medium text-gray-600 mb-2">
                            <span className="text-blue-600">Submitted</span>
                            <span className="text-blue-600 font-bold">Inspection</span>
                            <span>Assessment</span>
                            <span>Approved</span>
                        </div>
                        <div className="relative">
                            <div className="w-full h-3 bg-gray-200 rounded-full overflow-hidden shadow-inner">
                                <div className="h-3 bg-gradient-to-r from-blue-400 to-cyan-400 rounded-full transition-all duration-500" style={{ width: '50%' }}></div>
                            </div>
                            <div className="flex justify-between mt-2">
                                {[1, 2, 3, 4].map((step) => (
                                    <div key={step} className={`w-8 h-8 rounded-full flex items-center justify-center ${step <= 2 ? 'bg-blue-500 text-white shadow-lg' : 'bg-gray-300 text-gray-600'}`}>
                                        {step}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Inspection Details Card - Premium Design */}
                <div className="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-2xl shadow-lg p-6 mb-8 border border-blue-100">
                    <div className="flex items-center mb-6">
                        <div className="w-12 h-12 bg-gradient-to-br from-blue-100 to-blue-200 rounded-xl flex items-center justify-center mr-4 shadow">
                            <svg className="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <h2 className="text-xl font-bold text-gray-900">Inspection Scheduled</h2>
                            <p className="text-gray-600">Property inspection has been scheduled</p>
                        </div>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="bg-white rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow duration-300 border border-blue-100">
                            <div className="flex items-center mb-3">
                                <div className="w-10 h-10 bg-gradient-to-br from-blue-50 to-cyan-50 rounded-lg flex items-center justify-center mr-3">
                                    <svg className="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <div className="text-sm text-gray-500">Inspection Date</div>
                                    <div className="font-bold text-gray-900 text-xl">
                                        {registration.inspection_date ? 
                                            formatDate(registration.inspection_date, 'MMMM d, yyyy') : 
                                            'Not scheduled yet'}
                                    </div>
                                </div>
                            </div>
                            {registration.inspection_date && (
                                <div className="text-sm text-blue-600 mt-2 flex items-center">
                                    <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    {formatDate(registration.inspection_date, 'EEEE')}
                                </div>
                            )}
                        </div>
                        <div className="bg-white rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow duration-300 border border-blue-100">
                            <div className="flex items-center mb-3">
                                <div className="w-10 h-10 bg-gradient-to-br from-blue-50 to-cyan-50 rounded-lg flex items-center justify-center mr-3">
                                    <svg className="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <div className="text-sm text-gray-500">Assigned Assessor</div>
                                    <div className="font-bold text-gray-900 text-xl">
                                        {registration.inspector_name || 'To be assigned'}
                                    </div>
                                </div>
                            </div>
                            <div className="text-sm text-gray-600 mt-2">
                                {registration.inspector_name ? 
                                    'Will conduct the property inspection' : 
                                    'Awaiting assessor assignment'}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Main Content Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    {/* Left Column - Documents & Info */}
                    <div className="lg:col-span-2 space-y-8">
                        
                        {/* Documents Card */}
                        <div className="bg-white rounded-2xl shadow-lg p-6">
                            <div className="flex items-center justify-between mb-6">
                                <h2 className="text-xl font-bold text-gray-900 flex items-center">
                                    <svg className="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Uploaded Documents
                                </h2>
                                <span className="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-semibold">
                                    {documents.length} files
                                </span>
                            </div>
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {documents.map((doc, i) => (
                                    <div key={i} className="border border-gray-200 hover:border-blue-300 rounded-xl p-4 hover:shadow-lg transition-all duration-300 bg-white group">
                                        <div className="flex items-start mb-4">
                                            <div className="w-12 h-12 bg-gradient-to-br from-blue-100 to-blue-50 rounded-lg flex items-center justify-center mr-4 text-2xl group-hover:scale-110 transition-transform">
                                                {fileIcon(doc.file_name)}
                                            </div>
                                            <div className="flex-1">
                                                <h3 className="font-semibold text-gray-900 group-hover:text-blue-700 transition-colors">
                                                    {getDocumentTypeName(doc.document_type)}
                                                </h3>
                                                <p className="text-sm text-gray-500 truncate" title={doc.file_name}>{doc.file_name}</p>
                                                <div className="mt-2">
                                                    <span className="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">
                                                        {doc.file_name.split('.').pop().toUpperCase()}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <button
                                            onClick={() => handleViewDocument(doc)}
                                            className="w-full bg-gradient-to-r from-gray-50 to-gray-100 hover:from-blue-50 hover:to-blue-100 text-gray-700 hover:text-blue-700 py-2.5 rounded-lg text-sm font-medium transition-all duration-300 flex items-center justify-center group"
                                        >
                                            <svg className="w-4 h-4 mr-2 group-hover:animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            View Document
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Application Details Card */}
                        <div className="bg-white rounded-2xl shadow-lg p-6">
                            <h2 className="text-xl font-bold text-gray-900 mb-6 flex items-center">
                                <svg className="w-6 h-6 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Application Details
                            </h2>
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* Owner Information */}
                                <div className="bg-gradient-to-br from-blue-50 to-indigo-50 p-5 rounded-xl space-y-4">
                                    <div className="flex items-center">
                                        <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                            <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                        </div>
                                        <h3 className="font-semibold text-gray-800 text-lg">Owner Information</h3>
                                    </div>
                                    
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Full Name</label>
                                            <p className="text-gray-900 font-medium">{registration.owner_name}</p>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Sex</label>
                                            <p className="text-gray-900 font-medium">{registration.sex}</p>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Marital Status</label>
                                            <p className="text-gray-900 font-medium">{registration.marital_status}</p>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Birthdate</label>
                                            <p className="text-gray-900 font-medium">
                                                {registration.birthdate ? formatDate(registration.birthdate, 'MMMM d, yyyy') : 'N/A'}
                                            </p>
                                        </div>
                                        <div className="col-span-2">
                                            <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Address</label>
                                            <p className="text-gray-900 font-medium">{registration.owner_address}</p>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Contact</label>
                                            <p className="text-gray-900 font-medium">{registration.contact_number}</p>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Email</label>
                                            <p className="text-gray-900 font-medium truncate">{registration.email_address}</p>
                                        </div>
                                    </div>
                                </div>

                                {/* Property Information */}
                                <div className="bg-gradient-to-br from-green-50 to-emerald-50 p-5 rounded-xl space-y-4">
                                    <div className="flex items-center">
                                        <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                            <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                            </svg>
                                        </div>
                                        <h3 className="font-semibold text-gray-800 text-lg">Property Information</h3>
                                    </div>
                                    
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Location Address</label>
                                            <p className="text-gray-900 font-medium">{registration.location_address}</p>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Barangay</label>
                                            <p className="text-gray-900 font-medium">{registration.barangay}</p>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">District</label>
                                            <p className="text-gray-900 font-medium">{registration.district}</p>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">City/Municipality</label>
                                            <p className="text-gray-900 font-medium">{registration.municipality_city}</p>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Province</label>
                                            <p className="text-gray-900 font-medium">{registration.province}</p>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Zip Code</label>
                                            <p className="text-gray-900 font-medium">{registration.zip_code}</p>
                                        </div>
                                        <div className="col-span-2">
                                            <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Has Building</label>
                                            <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${registration.has_building === 'yes' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                                                {registration.has_building === 'yes' ? '✅ Yes' : '❌ No'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            {/* Registration Date */}
                            <div className="mt-6 pt-6 border-t border-gray-200">
                                <div className="flex items-center text-sm text-gray-600">
                                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    Date Registered: <span className="font-semibold ml-1">{formatDate(registration.date_registered, 'MMMM d, yyyy at hh:mm a')}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right Column - Admin Actions */}
                    <div className="space-y-8">
                        {/* Admin Actions Card */}
                        <div className="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl shadow-lg p-6 border border-blue-100">
                            <div className="flex items-center mb-6">
                                <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                    <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </div>
                                <h2 className="text-lg font-bold text-gray-900">Admin Actions</h2>
                            </div>
                            
                            <div className="space-y-4">
                                <button
                                    onClick={handleMarkAsAssessed}
                                    disabled={loading}
                                    className="w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 disabled:from-green-400 disabled:to-emerald-400 text-white px-4 py-3.5 rounded-xl flex items-center justify-center shadow-lg hover:shadow-xl transition-all duration-300 group"
                                >
                                    <svg className="w-5 h-5 mr-3 group-hover:animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Mark as Assessed
                                </button>
                                
                                <button
                                    onClick={() => setShowRejectForm(true)}
                                    disabled={loading}
                                    className="w-full bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 disabled:from-red-400 disabled:to-pink-400 text-white px-4 py-3.5 rounded-xl flex items-center justify-center shadow-lg hover:shadow-xl transition-all duration-300 group"
                                >
                                    <svg className="w-5 h-5 mr-3 group-hover:animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.106 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                    </svg>
                                    Mark for Correction
                                </button>
                            </div>

                            {/* Quick Info */}
                            <div className="mt-8 pt-6 border-t border-blue-200">
                                <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center">
                                    <svg className="w-4 h-4 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Application Info
                                </h3>
                                
                                <div className="space-y-3 bg-white/50 p-4 rounded-lg">
                                    {[
                                        { label: 'Reference No.', value: registration.reference_number },
                                        { label: 'Status', value: 'For Inspection', color: 'text-blue-700' },
                                        { label: 'Submitted Date', value: formatDate(registration.date_registered, 'MMM d, yyyy') },
                                        { label: 'Inspection Date', value: registration.inspection_date ? formatDate(registration.inspection_date, 'MMM d, yyyy') : 'Not set' },
                                        { label: 'Assessor', value: registration.inspector_name || 'Not assigned' },
                                        { label: 'Documents', value: `${documents.length} files` },
                                        { label: 'Property Type', value: registration.has_building === 'yes' ? 'With Building' : 'Vacant Land' },
                                    ].map((item, idx) => (
                                        <div key={idx} className="flex justify-between items-center py-1">
                                            <span className="text-xs text-gray-600 font-medium">{item.label}</span>
                                            <span className={`text-xs font-semibold ${item.color || 'text-gray-900'}`}>{item.value}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* Important Notes Card */}
                        <div className="bg-gradient-to-br from-yellow-50 to-amber-50 rounded-2xl shadow-lg p-6 border border-yellow-100">
                            <div className="flex items-center mb-4">
                                <div className="w-10 h-10 bg-gradient-to-br from-yellow-100 to-yellow-200 rounded-lg flex items-center justify-center mr-3 shadow-sm">
                                    <svg className="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.106 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                    </svg>
                                </div>
                                <h3 className="font-semibold text-gray-800">Inspection Preparation</h3>
                            </div>
                            
                            <div className="space-y-3">
                                <div className="bg-white/70 p-4 rounded-lg">
                                    <div className="text-sm text-gray-600 mb-2">Next Step:</div>
                                    <div className="font-semibold text-yellow-700">Property Inspection</div>
                                </div>
                                
                                <div className="text-sm text-gray-600">
                                    <p className="mb-2">Important Notes:</p>
                                    <ul className="space-y-2">
                                        <li className="flex items-start">
                                            <div className="w-2 h-2 bg-yellow-500 rounded-full mr-2 mt-1.5 flex-shrink-0"></div>
                                            <span>Ensure property is accessible on scheduled date</span>
                                        </li>
                                        <li className="flex items-start">
                                            <div className="w-2 h-2 bg-yellow-500 rounded-full mr-2 mt-1.5 flex-shrink-0"></div>
                                            <span>Have all property documents ready for verification</span>
                                        </li>
                                        <li className="flex items-start">
                                            <div className="w-2 h-2 bg-yellow-500 rounded-full mr-2 mt-1.5 flex-shrink-0"></div>
                                            <span>Property owner or representative must be present</span>
                                        </li>
                                        <li className="flex items-start">
                                            <div className="w-2 h-2 bg-yellow-500 rounded-full mr-2 mt-1.5 flex-shrink-0"></div>
                                            <span>Assessor will take measurements and photos</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* View Document Modal */}
                {showDocumentModal && currentDocument && (
                    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4 z-50">
                        <div className="bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] flex flex-col animate-fadeIn">
                            <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gradient-to-r from-blue-50 to-indigo-50 rounded-t-2xl">
                                <div className="flex items-center">
                                    <div className="w-12 h-12 bg-gradient-to-br from-blue-100 to-blue-200 rounded-xl flex items-center justify-center mr-4 text-2xl shadow">
                                        {fileIcon(currentDocument.file_name)}
                                    </div>
                                    <div>
                                        <h3 className="text-xl font-bold text-gray-900">{getDocumentTypeName(currentDocument.document_type)}</h3>
                                        <p className="text-sm text-gray-600 truncate max-w-lg">{currentDocument.file_name}</p>
                                    </div>
                                </div>
                                <div className="flex items-center space-x-3">
                                    <button
                                        onClick={() => window.open(getDocumentUrl(currentDocument.file_path), '_blank')}
                                        className="text-sm font-medium text-blue-600 hover:text-blue-700 px-4 py-2 rounded-lg border border-blue-200 hover:border-blue-300 hover:bg-blue-50 transition-colors flex items-center shadow-sm"
                                    >
                                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        Download
                                    </button>
                                    <button 
                                        onClick={() => setShowDocumentModal(false)} 
                                        className="text-gray-500 hover:text-gray-700 hover:bg-gray-100 p-2 rounded-full transition-colors"
                                    >
                                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <div className="flex-1 overflow-auto p-4">
                                <div className="bg-gray-50 rounded-xl border border-gray-300 flex items-center justify-center min-h-[60vh] p-4">
                                    {currentDocument.file_name.toLowerCase().endsWith('.pdf') ? (
                                        <iframe 
                                            src={getDocumentUrl(currentDocument.file_path)}
                                            className="w-full h-[60vh] border-0 rounded-lg shadow"
                                            title={currentDocument.file_name}
                                        />
                                    ) : currentDocument.file_name.toLowerCase().match(/\.(jpg|jpeg|png|gif)$/) ? (
                                        <img 
                                            src={getDocumentUrl(currentDocument.file_path)} 
                                            alt={currentDocument.file_name}
                                            className="max-w-full max-h-[60vh] object-contain rounded-lg shadow"
                                        />
                                    ) : (
                                        <div className="text-center p-8">
                                            <div className="text-5xl mb-4">📄</div>
                                            <h4 className="text-xl font-semibold text-gray-700 mb-3">Document Preview Not Available</h4>
                                            <p className="text-gray-600 mb-6">This file type cannot be previewed in the browser.</p>
                                            <button
                                                onClick={() => window.open(getDocumentUrl(currentDocument.file_path), '_blank')}
                                                className="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-all duration-300 shadow-lg hover:shadow-xl"
                                            >
                                                Download File
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>
                            
                            <div className="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                                <div className="flex justify-between items-center">
                                    <div className="text-sm text-gray-600">
                                        <div className="flex items-center space-x-6">
                                            <div>
                                                <span className="font-medium">Document Type:</span> {getDocumentTypeName(currentDocument.document_type)}
                                            </div>
                                            <div>
                                                <span className="font-medium">Uploaded:</span> {formatDate(currentDocument.created_at || registration.date_registered, 'MMM d, yyyy • h:mm a')}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex space-x-3">
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

                {/* Reject Form Modal */}
                {showRejectForm && (
                    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50">
                        <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-slideUp">
                            <div className="p-6">
                                <div className="flex justify-between items-center mb-6">
                                    <h3 className="text-xl font-semibold text-gray-900">Mark for Correction</h3>
                                    <button onClick={() => setShowRejectForm(false)} className="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-1 rounded-full">
                                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <div className="space-y-5">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">Correction Notes *</label>
                                        <textarea 
                                            value={rejectionNotes} 
                                            onChange={(e) => setRejectionNotes(e.target.value)} 
                                            className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition h-32" 
                                            placeholder="Explain what needs to be corrected..." 
                                            required 
                                        />
                                    </div>
                                    <div className="flex gap-3 pt-2">
                                        <button 
                                            onClick={handleReject} 
                                            disabled={loading}
                                            className="flex-1 bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 disabled:from-red-400 disabled:to-pink-400 text-white py-3.5 rounded-xl font-medium transition-all flex items-center justify-center shadow-lg hover:shadow-xl"
                                        >
                                            {loading ? (
                                                <>
                                                    <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Processing...
                                                </>
                                            ) : 'Mark Needs Correction'}
                                        </button>
                                        <button 
                                            onClick={() => setShowRejectForm(false)} 
                                            disabled={loading}
                                            className="flex-1 bg-gradient-to-r from-gray-300 to-gray-400 hover:from-gray-400 hover:to-gray-500 disabled:from-gray-200 disabled:to-gray-300 text-gray-800 py-3.5 rounded-xl font-medium transition-all shadow"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                    <p className="text-sm text-red-600 bg-red-50 p-3 rounded-lg">
                                        ⚠️ This will change status to "needs_correction" and notify the citizen.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

            </div>
            
            {/* Add CSS animations */}
            <style jsx>{`
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                @keyframes slideUp {
                    from { transform: translateY(20px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
                .animate-fadeIn {
                    animation: fadeIn 0.3s ease-out;
                }
                .animate-slideUp {
                    animation: slideUp 0.3s ease-out;
                }
            `}</style>
        </div>
    );
}