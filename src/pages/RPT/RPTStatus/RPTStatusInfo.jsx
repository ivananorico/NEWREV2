import React, { useState, useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";

export default function RPTStatusInfo() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [property, setProperty] = useState(null);
  const [buildings, setBuildings] = useState([]);
  const [quarterlyTaxes, setQuarterlyTaxes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState("overview");

  const API_BASE = "http://localhost/revenue/backend/RPT/RPTStatus";

  useEffect(() => {
    fetchPropertyDetails();
  }, [id]);

  const fetchPropertyDetails = async () => {
    try {
      const response = await fetch(`${API_BASE}/get_property_details.php?id=${id}`);
      const data = await response.json();

      if (data.status === "success") {
        setProperty(data.property);
        setBuildings(data.buildings || []);
        setQuarterlyTaxes(data.quarterly_taxes || []);
      } else {
        throw new Error(data.message || "Failed to fetch property details");
      }
    } catch (err) {
      setError(err.message);
      console.error("Error fetching property details:", err);
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2
    }).format(amount || 0);
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  const getPaymentBadge = (status) => {
    const statusConfig = {
      paid: { color: "bg-green-100 text-green-800 border-green-200", label: "Paid" },
      pending: { color: "bg-yellow-100 text-yellow-800 border-yellow-200", label: "Pending" },
      overdue: { color: "bg-red-100 text-red-800 border-red-200", label: "Overdue" }
    };
    
    const config = statusConfig[status] || { color: "bg-gray-100 text-gray-800 border-gray-200", label: status };
    return (
      <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border ${config.color}`}>
        {config.label}
      </span>
    );
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-8 max-w-md w-full mx-4">
          <div className="flex flex-col items-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
            <h3 className="text-lg font-semibold text-gray-900 mb-2">Loading Property Details</h3>
            <p className="text-gray-600 text-center">Please wait while we fetch the property information...</p>
          </div>
        </div>
      </div>
    );
  }

  if (error || !property) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-8 max-w-md w-full mx-4">
          <div className="text-center">
            <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
            </div>
            <h3 className="text-xl font-bold text-gray-900 mb-2">Error Loading Property</h3>
            <p className="text-gray-600 mb-6">{error || "Property not found"}</p>
            <button
              onClick={() => navigate('/rpt/rptstatus')}
              className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg text-sm font-medium transition-colors w-full"
            >
              Back to Properties List
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Header Section */}
        <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 mb-6">
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div className="flex-1">
              <div className="flex items-center space-x-4 mb-4">
                <button
                  onClick={() => navigate('/rpt/rptstatus')}
                  className="flex items-center text-gray-600 hover:text-gray-900 transition-colors"
                >
                  <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                  </svg>
                  Back to Properties
                </button>
              </div>
              
              <div className="flex items-start justify-between">
                <div>
                  <h1 className="text-3xl font-bold text-gray-900 mb-2">{property.reference_number}</h1>
                  <p className="text-lg text-gray-600 mb-4">{property.lot_location}</p>
                  
                  <div className="flex flex-wrap gap-2 mb-4">
                    <span className="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                      Approved Property
                    </span>
                    <span className="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                      {property.land_classification}
                    </span>
                    <span className={`px-3 py-1 rounded-full text-sm font-medium ${
                      property.has_building === 'yes' 
                        ? 'bg-purple-100 text-purple-800' 
                        : 'bg-gray-100 text-gray-800'
                    }`}>
                      {property.has_building === 'yes' ? 'With Building' : 'Vacant Land'}
                    </span>
                  </div>
                </div>
                
                <div className="text-right">
                  <div className="text-2xl font-bold text-blue-600 mb-1">
                    {formatCurrency(property.total_annual_tax)}
                  </div>
                  <div className="text-sm text-gray-500">Annual Tax</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Navigation Tabs */}
        <div className="bg-white rounded-2xl shadow-lg border border-gray-200 mb-6">
          <div className="border-b border-gray-200">
            <nav className="flex -mb-px">
              {['overview', 'land-building', 'taxes'].map((tab) => (
                <button
                  key={tab}
                  onClick={() => setActiveTab(tab)}
                  className={`flex-1 py-4 px-6 text-center font-medium text-sm border-b-2 transition-colors ${
                    activeTab === tab
                      ? 'border-blue-500 text-blue-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  {tab === 'overview' && 'Property Overview'}
                  {tab === 'land-building' && `Land & Building (${buildings.length})`}
                  {tab === 'taxes' && `Tax Records (${quarterlyTaxes.length})`}
                </button>
              ))}
            </nav>
          </div>

          {/* Tab Content */}
          <div className="p-6">
            {/* Overview Tab */}
            {activeTab === 'overview' && (
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Owner Information Card */}
                <div className="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-100">
                  <div className="flex items-center mb-4">
                    <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                      <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </div>
                    <h3 className="text-lg font-semibold text-gray-900">Property Owner</h3>
                  </div>
                  <div className="space-y-3">
                    <div>
                      <label className="text-sm font-medium text-gray-500">Full Name</label>
                      <p className="text-gray-900 font-medium">{property.owner_name}</p>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="text-sm font-medium text-gray-500">Email</label>
                        <p className="text-gray-900">{property.email}</p>
                      </div>
                      <div>
                        <label className="text-sm font-medium text-gray-500">Phone</label>
                        <p className="text-gray-900">{property.phone}</p>
                      </div>
                    </div>
                    <div>
                      <label className="text-sm font-medium text-gray-500">Address</label>
                      <p className="text-gray-900">{property.owner_address}</p>
                    </div>
                    {property.tin_number && (
                      <div>
                        <label className="text-sm font-medium text-gray-500">TIN Number</label>
                        <p className="text-gray-900 font-mono">{property.tin_number}</p>
                      </div>
                    )}
                  </div>
                </div>

                {/* Property Details Card */}
                <div className="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-6 border border-green-100">
                  <div className="flex items-center mb-4">
                    <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                      <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                      </svg>
                    </div>
                    <h3 className="text-lg font-semibold text-gray-900">Property Details</h3>
                  </div>
                  <div className="space-y-3">
                    <div>
                      <label className="text-sm font-medium text-gray-500">Location</label>
                      <p className="text-gray-900 font-medium">{property.lot_location}</p>
                      <p className="text-gray-600 text-sm">{property.barangay}, {property.district}</p>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="text-sm font-medium text-gray-500">Land Area</label>
                        <p className="text-gray-900 font-medium">{property.land_area_sqm} sqm</p>
                      </div>
                      <div>
                        <label className="text-sm font-medium text-gray-500">Land TDN</label>
                        <p className="text-gray-900 font-mono text-sm">{property.land_tdn}</p>
                      </div>
                    </div>
                    <div>
                      <label className="text-sm font-medium text-gray-500">Approval Date</label>
                      <p className="text-gray-900">{formatDate(property.approval_date)}</p>
                    </div>
                  </div>
                </div>

                {/* Tax Assessment Card */}
                <div className="bg-gradient-to-br from-orange-50 to-amber-50 rounded-xl p-6 border border-orange-100 lg:col-span-2">
                  <div className="flex items-center mb-4">
                    <div className="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                      <svg className="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                      </svg>
                    </div>
                    <h3 className="text-lg font-semibold text-gray-900">Tax Assessment</h3>
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="text-center">
                      <div className="text-2xl font-bold text-gray-900 mb-1">{formatCurrency(property.land_market_value)}</div>
                      <div className="text-sm text-gray-500">Land Market Value</div>
                    </div>
                    <div className="text-center">
                      <div className="text-2xl font-bold text-gray-900 mb-1">{formatCurrency(property.land_assessed_value)}</div>
                      <div className="text-sm text-gray-500">Land Assessed Value</div>
                    </div>
                    <div className="text-center">
                      <div className="text-3xl font-bold text-blue-600 mb-1">{formatCurrency(property.total_annual_tax)}</div>
                      <div className="text-sm text-gray-500">Total Annual Tax</div>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* Land & Building Tab */}
            {activeTab === 'land-building' && (
              <div className="space-y-6">
                {/* Land Information Card */}
                <div className="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-6 border border-green-200">
                  <div className="flex items-center mb-4">
                    <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                      <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                      </svg>
                    </div>
                    <h3 className="text-lg font-semibold text-gray-900">Land Information</h3>
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-500 mb-1">Land TDN</label>
                      <p className="text-gray-900 font-mono text-sm">{property.land_tdn}</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-500 mb-1">Classification</label>
                      <p className="text-gray-900">{property.land_classification}</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-500 mb-1">Land Area</label>
                      <p className="text-gray-900 font-medium">{property.land_area_sqm} sqm</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-500 mb-1">Market Value</label>
                      <p className="text-gray-900 font-medium">{formatCurrency(property.land_market_value)}</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-500 mb-1">Assessed Value</label>
                      <p className="text-gray-900 font-medium">{formatCurrency(property.land_assessed_value)}</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-500 mb-1">Assessment Level</label>
                      <p className="text-gray-900">{property.assessment_level}%</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-500 mb-1">Annual Tax</label>
                      <p className="text-lg font-bold text-green-600">{formatCurrency(property.land_annual_tax)}</p>
                    </div>
                  </div>
                </div>

                {/* Building Information */}
                {buildings.length > 0 ? (
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Building Information</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                      {buildings.map((building, index) => (
                        <div key={building.id} className="bg-white border border-gray-200 rounded-xl p-6 hover:shadow-lg transition-shadow">
                          <div className="flex items-center justify-between mb-4">
                            <h4 className="text-lg font-semibold text-gray-900">Building {index + 1}</h4>
                            <span className="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs font-medium">
                              {building.tdn}
                            </span>
                          </div>
                          
                          <div className="space-y-3">
                            <div className="grid grid-cols-2 gap-2 text-sm">
                              <div>
                                <span className="text-gray-500">Floor Area:</span>
                                <p className="font-medium text-gray-900">{building.floor_area_sqm} sqm</p>
                              </div>
                              <div>
                                <span className="text-gray-500">Year Built:</span>
                                <p className="font-medium text-gray-900">{building.year_built}</p>
                              </div>
                            </div>
                            
                            <div className="grid grid-cols-2 gap-2 text-sm">
                              <div>
                                <span className="text-gray-500">Construction:</span>
                                <p className="font-medium text-gray-900">{building.construction_type}</p>
                              </div>
                              <div>
                                <span className="text-gray-500">Material:</span>
                                <p className="font-medium text-gray-900">{building.material_type}</p>
                              </div>
                            </div>
                            
                            <div className="pt-3 border-t border-gray-200">
                              <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                  <span className="text-gray-500">Market Value:</span>
                                  <p className="font-medium text-gray-900">{formatCurrency(building.building_market_value)}</p>
                                </div>
                                <div>
                                  <span className="text-gray-500">Assessed Value:</span>
                                  <p className="font-medium text-gray-900">{formatCurrency(building.building_assessed_value)}</p>
                                </div>
                              </div>
                            </div>
                            
                            <div className="bg-blue-50 rounded-lg p-3 mt-3">
                              <div className="text-center">
                                <div className="text-lg font-bold text-blue-600">{formatCurrency(building.annual_tax)}</div>
                                <div className="text-xs text-blue-600">Annual Building Tax</div>
                              </div>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                ) : (
                  <div className="bg-gray-50 rounded-xl p-6 border border-gray-200 text-center">
                    <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                      <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                      </svg>
                    </div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-2">No Buildings</h3>
                    <p className="text-gray-500">This property is a vacant land with no registered buildings.</p>
                  </div>
                )}
              </div>
            )}

            {/* Taxes Tab */}
            {activeTab === 'taxes' && (
              <div>
                {quarterlyTaxes.length > 0 ? (
                  <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Quarter</th>
                          <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Year</th>
                          <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Due Date</th>
                          <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                          <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                          <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment Date</th>
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {quarterlyTaxes.map((tax) => (
                          <tr key={tax.id} className="hover:bg-gray-50 transition-colors">
                            <td className="px-6 py-4 whitespace-nowrap">
                              <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                {tax.quarter}
                              </span>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                              {tax.year}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                              {formatDate(tax.due_date)}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                              {formatCurrency(tax.total_quarterly_tax)}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              {getPaymentBadge(tax.payment_status)}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                              {tax.payment_date ? formatDate(tax.payment_date) : '-'}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <div className="text-center py-12">
                    <div className="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                      <svg className="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                      </svg>
                    </div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-2">No Tax Records</h3>
                    <p className="text-gray-500">No quarterly tax records found for this property.</p>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}