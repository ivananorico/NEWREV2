import React, { useState, useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";
import {
  ArrowLeft,
  RefreshCw,
  Printer,
  Home,
  Building,
  DollarSign,
  Calendar,
  MapPin,
  User,
  CheckCircle,
  AlertCircle,
  FileText,
  Mail,
  Phone,
  CalendarDays
} from "lucide-react";

export default function RPTStatusInfo() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [property, setProperty] = useState(null);
  const [buildings, setBuildings] = useState([]);
  const [quarterlyTaxes, setQuarterlyTaxes] = useState([]);
  const [loading, setLoading] = useState(true);

  const API_BASE = window.location.hostname === "localhost" 
    ? "http://localhost/revenue2/backend" 
    : "https://revenuetreasury.goserveph.com/backend";

  useEffect(() => {
    fetchPropertyDetails();
  }, [id]);

  const fetchPropertyDetails = async () => {
    try {
      setLoading(true);
      const res = await fetch(
        `${API_BASE}/RPT/RPTStatus/get_property_details.php?id=${id}`,
        { 
          method: 'GET',
          headers: { 'Accept': 'application/json' }
        }
      );
      
      const data = await res.json();
      if (data.status === "success") {
        setProperty(data.data.property);
        setBuildings(data.data.buildings || []);
        setQuarterlyTaxes(data.data.quarterly_taxes || []);
      }
    } catch (err) {
      console.error("Error:", err);
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (amount) => {
    if (!amount || isNaN(amount)) return '₱0.00';
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2
    }).format(amount);
  };

  const formatDate = (dateString, options = {}) => {
    if (!dateString || dateString === "0000-00-00" || dateString === "0000-00-00 00:00:00") return "N/A";
    
    const date = new Date(dateString);
    
    if (options.format === 'full') {
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    }
    
    return date.toLocaleDateString('en-PH', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    });
  };

  const getPaymentStatus = (status) => {
    switch(status?.toLowerCase()) {
      case 'paid': return { text: "Paid", color: "bg-green-100 text-green-800" };
      case 'overdue': return { text: "Overdue", color: "bg-red-100 text-red-800" };
      default: return { text: "Pending", color: "bg-yellow-100 text-yellow-800" };
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-3 text-gray-600">Loading...</p>
        </div>
      </div>
    );
  }

  if (!property) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-lg p-6 text-center max-w-md">
          <AlertCircle className="w-12 h-12 text-red-400 mx-auto mb-3" />
          <h2 className="text-lg font-semibold text-gray-900 mb-2">Property not found</h2>
          <button
            onClick={() => navigate(-1)}
            className="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            Go Back
          </button>
        </div>
      </div>
    );
  }

  const totalLandTax = parseFloat(property.land_annual_tax) || 0;
  const totalBuildingTax = buildings.reduce((sum, b) => sum + (parseFloat(b.building_annual_tax) || 0), 0);
  const totalAnnualTax = parseFloat(property.total_annual_tax) || (totalLandTax + totalBuildingTax);
  const totalPaid = quarterlyTaxes
    .filter(tax => tax.payment_status === 'paid')
    .reduce((sum, tax) => sum + (parseFloat(tax.total_quarterly_tax) || 0), 0);
  const collectionRate = totalAnnualTax > 0 ? Math.round((totalPaid / totalAnnualTax) * 100) : 0;

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b shadow-sm">
        <div className="max-w-6xl mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-3">
              <button
                onClick={() => navigate(-1)}
                className="p-2 hover:bg-gray-100 rounded"
              >
                <ArrowLeft className="w-5 h-5" />
              </button>
              <div>
                <h1 className="text-xl font-bold text-gray-900">{property.reference_number}</h1>
                <p className="text-sm text-gray-600">{property.owner_name}</p>
              </div>
            </div>
            <div className="flex items-center space-x-2">
              <button
                onClick={() => window.print()}
                className="p-2 text-gray-600 hover:bg-gray-100 rounded"
              >
                <Printer className="w-5 h-5" />
              </button>
              <button
                onClick={fetchPropertyDetails}
                className="p-2 text-gray-600 hover:bg-gray-100 rounded"
              >
                <RefreshCw className="w-5 h-5" />
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-6xl mx-auto px-4 py-6">
        {/* Summary Stats */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div className="bg-white p-4 border rounded shadow-sm">
            <p className="text-sm text-gray-500">Annual Tax</p>
            <p className="text-xl font-bold text-green-600">{formatCurrency(totalAnnualTax)}</p>
          </div>
          <div className="bg-white p-4 border rounded shadow-sm">
            <p className="text-sm text-gray-500">Collection</p>
            <p className="text-xl font-bold text-blue-600">{collectionRate}%</p>
          </div>
          <div className="bg-white p-4 border rounded shadow-sm">
            <p className="text-sm text-gray-500">Land Tax</p>
            <p className="text-xl font-bold text-purple-600">{formatCurrency(totalLandTax)}</p>
          </div>
          <div className="bg-white p-4 border rounded shadow-sm">
            <p className="text-sm text-gray-500">Building Tax</p>
            <p className="text-xl font-bold text-orange-600">{formatCurrency(totalBuildingTax)}</p>
          </div>
        </div>

        {/* Property and Owner Information Grid - REVISED */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
          
          {/* Property Information */}
          <div className="bg-gray-50 p-4 rounded-lg space-y-2">
            <div className="flex items-center mb-3">
              <MapPin className="w-5 h-5 text-blue-600 mr-2" />
              <h3 className="font-semibold text-gray-700 text-lg">Property Information</h3>
            </div>
            <div className="space-y-3">
              <div className="flex items-start">
                <span className="font-medium min-w-28">Location:</span>
                <span className="text-gray-700">{property.lot_location}</span>
              </div>
              <div className="flex items-start">
                <span className="font-medium min-w-28">Barangay:</span>
                <span className="text-gray-700">{property.barangay}</span>
              </div>
              <div className="flex items-start">
                <span className="font-medium min-w-28">District:</span>
                <span className="text-gray-700">{property.district}</span>
              </div>
              <div className="flex items-start">
                <span className="font-medium min-w-28">City:</span>
                <span className="text-gray-700">{property.city || 'N/A'}</span>
              </div>
              <div className="flex items-start">
                <span className="font-medium min-w-28">Province:</span>
                <span className="text-gray-700">{property.province || 'N/A'}</span>
              </div>
              <div className="flex items-start">
                <span className="font-medium min-w-28">Zip Code:</span>
                <span className="text-gray-700">{property.zip_code || 'N/A'}</span>
              </div>
              <div className="flex items-start">
                <span className="font-medium min-w-28">Has Building:</span>
                <span className={`px-2 py-1 text-xs rounded-full ${property.has_building === 'yes' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                  {property.has_building === 'yes' ? 'Yes' : 'No'}
                </span>
              </div>
            </div>
          </div>

          {/* Owner Information - Updated from Pending.js */}
          <div className="bg-gray-50 p-4 rounded-lg flex flex-col justify-between">
            <div className="space-y-3">
              <div className="flex items-center mb-3">
                <User className="w-5 h-5 text-blue-600 mr-2" />
                <h3 className="font-semibold text-gray-700 text-lg">Owner Information</h3>
              </div>
              
              <div className="space-y-3">
                <div className="flex items-start">
                  <span className="font-medium min-w-28">Name:</span>
                  <span className="text-gray-700">{property.owner_name}</span>
                </div>
                <div className="flex items-start">
                  <span className="font-medium min-w-28">Sex:</span>
                  <span className="text-gray-700">{property.sex ? property.sex.charAt(0).toUpperCase() + property.sex.slice(1) : 'N/A'}</span>
                </div>
                <div className="flex items-start">
                  <span className="font-medium min-w-28">Marital Status:</span>
                  <span className="text-gray-700">{property.marital_status ? property.marital_status.charAt(0).toUpperCase() + property.marital_status.slice(1) : 'N/A'}</span>
                </div>
                <div className="flex items-start">
                  <span className="font-medium min-w-28">Birthdate:</span>
                  <div className="flex items-center text-gray-700">
                    <CalendarDays className="w-4 h-4 mr-1 text-gray-500" />
                    {property.birthdate ? formatDate(property.birthdate, { format: 'full' }).split(' at ')[0] : 'N/A'}
                  </div>
                </div>
                <div className="flex items-start">
                  <span className="font-medium min-w-28">Address:</span>
                  <span className="text-gray-700">{property.owner_address || 'N/A'}</span>
                </div>
                <div className="flex items-start">
                  <span className="font-medium min-w-28">Contact:</span>
                  <div className="flex items-center text-gray-700">
                    <Phone className="w-4 h-4 mr-1 text-gray-500" />
                    {property.contact_number || 'N/A'}
                  </div>
                </div>
                <div className="flex items-start">
                  <span className="font-medium min-w-28">Email:</span>
                  <div className="flex items-center text-gray-700">
                    <Mail className="w-4 h-4 mr-1 text-gray-500" />
                    {property.email_address || 'N/A'}
                  </div>
                </div>
              </div>
            </div>

            {/* Date Registered at bottom */}
            <div className="mt-6 pt-4 border-t border-gray-300">
              <div className="text-sm text-gray-500 flex items-center">
                <FileText className="w-4 h-4 mr-2" />
                Date Registered: {formatDate(property.created_at, { format: 'full' })}
              </div>
            </div>
          </div>

        </div>

        {/* Property Assessment - Side by Side */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          {/* Land Property */}
          <div className="bg-white border rounded shadow-sm">
            <div className="px-6 py-4 border-b bg-gray-50">
              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <Home className="w-5 h-5 text-blue-600 mr-2" />
                  <h2 className="font-semibold">Land Property</h2>
                </div>
                <span className="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">
                  {property.property_type || "Residential"}
                </span>
              </div>
            </div>
            <div className="p-6">
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm text-gray-500">Area</p>
                    <p className="font-medium">{property.land_area_sqm} sqm</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-500">Market Value</p>
                    <p className="font-medium">{formatCurrency(property.land_market_value)}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-500">Assessed Value</p>
                    <p className="font-medium">{formatCurrency(property.land_assessed_value)}</p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-500">Assessment Level</p>
                    <p className="font-medium">{property.assessment_level || "30"}%</p>
                  </div>
                </div>
                
                <div className="border-t pt-4">
                  <div className="flex justify-between items-center">
                    <div>
                      <p className="text-sm text-gray-500">Annual Land Tax</p>
                      <p className="text-xs text-gray-400">Basic + SEF Taxes</p>
                    </div>
                    <p className="text-xl font-bold text-green-600">{formatCurrency(totalLandTax)}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Building Property */}
          <div className="bg-white border rounded shadow-sm">
            <div className="px-6 py-4 border-b bg-gray-50">
              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <Building className="w-5 h-5 text-green-600 mr-2" />
                  <h2 className="font-semibold">Building Property</h2>
                </div>
                <span className="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">
                  {buildings.length} building{buildings.length !== 1 ? 's' : ''}
                </span>
              </div>
            </div>
            <div className="p-6">
              {buildings.length > 0 ? (
                <div className="space-y-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <p className="text-sm text-gray-500">Total Area</p>
                      <p className="font-medium">
                        {buildings.reduce((sum, b) => sum + (parseFloat(b.floor_area_sqm) || 0), 0)} sqm
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-500">Total Market Value</p>
                      <p className="font-medium">
                        {formatCurrency(buildings.reduce((sum, b) => sum + (parseFloat(b.building_market_value) || 0), 0))}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-500">Construction</p>
                      <p className="font-medium">
                        {[...new Set(buildings.map(b => b.construction_type))].join(', ')}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm text-gray-500">Avg. Assessment</p>
                      <p className="font-medium">
                        {buildings.length > 0 ? 
                          (buildings.reduce((sum, b) => sum + (parseFloat(b.assessment_level) || 0), 0) / buildings.length).toFixed(1) + '%' : 
                          'N/A'}
                      </p>
                    </div>
                  </div>
                  
                  <div className="border-t pt-4">
                    <div className="flex justify-between items-center">
                      <div>
                        <p className="text-sm text-gray-500">Annual Building Tax</p>
                        <p className="text-xs text-gray-400">All buildings combined</p>
                      </div>
                      <p className="text-xl font-bold text-green-600">{formatCurrency(totalBuildingTax)}</p>
                    </div>
                  </div>
                </div>
              ) : (
                <div className="text-center py-8">
                  <Building className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                  <p className="text-gray-500">No buildings registered</p>
                  <p className="text-sm text-gray-400">Land only property</p>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Tax Summary */}
        <div className="bg-white border rounded shadow-sm mb-6">
          <div className="px-6 py-4 border-b">
            <div className="flex items-center">
              <DollarSign className="w-5 h-5 text-yellow-600 mr-2" />
              <h2 className="text-lg font-semibold">Tax Summary</h2>
            </div>
          </div>
          <div className="p-6">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div className="text-center">
                <p className="text-sm text-gray-500">Land Tax</p>
                <p className="text-2xl font-bold text-blue-600">{formatCurrency(totalLandTax)}</p>
                <div className="mt-2">
                  <div className="h-2 bg-gray-200 rounded">
                    <div 
                      className="h-2 bg-blue-500 rounded" 
                      style={{ width: `${(totalLandTax / totalAnnualTax) * 100}%` }}
                    ></div>
                  </div>
                </div>
              </div>
              <div className="text-center">
                <p className="text-sm text-gray-500">Building Tax</p>
                <p className="text-2xl font-bold text-green-600">{formatCurrency(totalBuildingTax)}</p>
                <div className="mt-2">
                  <div className="h-2 bg-gray-200 rounded">
                    <div 
                      className="h-2 bg-green-500 rounded" 
                      style={{ width: `${(totalBuildingTax / totalAnnualTax) * 100}%` }}
                    ></div>
                  </div>
                </div>
              </div>
              <div className="text-center">
                <p className="text-sm text-gray-500">Total Annual Tax</p>
                <p className="text-2xl font-bold text-purple-600">{formatCurrency(totalAnnualTax)}</p>
                <p className="text-sm text-gray-500 mt-1">Quarterly: {formatCurrency(totalAnnualTax / 4)}</p>
              </div>
            </div>
          </div>
        </div>

        {/* Quarterly Taxes */}
        <div className="bg-white border rounded shadow-sm">
          <div className="px-6 py-4 border-b">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <Calendar className="w-5 h-5 text-purple-600 mr-2" />
                <h2 className="text-lg font-semibold">Quarterly Taxes</h2>
              </div>
              <div className="flex space-x-2">
                <span className="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">
                  {quarterlyTaxes.filter(t => t.payment_status === 'paid').length} paid
                </span>
                <span className="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded">
                  {quarterlyTaxes.filter(t => t.payment_status === 'pending').length} pending
                </span>
              </div>
            </div>
          </div>
          
          <div className="p-6">
            {quarterlyTaxes.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-4 py-2 text-left text-sm font-medium text-gray-600">Quarter</th>
                      <th className="px-4 py-2 text-left text-sm font-medium text-gray-600">Due Date</th>
                      <th className="px-4 py-2 text-left text-sm font-medium text-gray-600">Amount</th>
                      <th className="px-4 py-2 text-left text-sm font-medium text-gray-600">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {quarterlyTaxes.map((tax) => {
                      const status = getPaymentStatus(tax.payment_status);
                      return (
                        <tr key={tax.id} className="hover:bg-gray-50">
                          <td className="px-4 py-3">
                            <div className="font-medium">{tax.quarter} {tax.year}</div>
                          </td>
                          <td className="px-4 py-3 text-gray-600">{formatDate(tax.due_date)}</td>
                          <td className="px-4 py-3 font-medium">{formatCurrency(tax.total_quarterly_tax)}</td>
                          <td className="px-4 py-3">
                            <span className={`px-3 py-1 text-xs rounded-full ${status.color}`}>
                              {status.text}
                            </span>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="text-center py-8 text-gray-500">
                <Calendar className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                <p>No quarterly taxes recorded</p>
              </div>
            )}
          </div>
        </div>

        {/* Footer */}
        <div className="mt-6 text-sm text-gray-500 flex items-center">
          <FileText className="w-4 h-4 mr-2" />
          <p>Property last updated: {formatDate(property.updated_at, { format: 'full' })}</p>
        </div>
      </div>
    </div>
  );
}