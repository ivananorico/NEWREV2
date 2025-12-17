import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';

const BusinessValidationInfo = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const [permit, setPermit] = useState(null);
  const [loading, setLoading] = useState(true);
  const [calculatedTax, setCalculatedTax] = useState(null);
  const [selectedRate, setSelectedRate] = useState(null);
  const [customRate, setCustomRate] = useState('');
  const [showRateOptions, setShowRateOptions] = useState(false);

  // Load permit and calculate tax
  useEffect(() => {
    const loadData = async () => {
      try {
        setLoading(true);
        
        // Fetch permit details
        const permitUrl = `http://localhost/revenue/backend/Business/BusinessValidation/get_permit_details.php?id=${id}`;
        const permitRes = await fetch(permitUrl);
        const permitData = await permitRes.json();
        
        if (permitData.status !== 'success') {
          alert('Error loading permit');
          navigate('/business/validation');
          return;
        }
        
        setPermit(permitData.permit);
        
        // Calculate initial tax
        await calculateTax(permitData.permit);
        
      } catch (err) {
        console.error('Error:', err);
        alert('Error loading data');
        navigate('/business/validation');
      } finally {
        setLoading(false);
      }
    };
    
    if (id) loadData();
  }, [id, navigate]);

  // Calculate tax
  const calculateTax = async (permitData, rateId = null, customRateValue = null) => {
    try {
      let url = `http://localhost/revenue/backend/Business/BusinessValidation/calculate_tax.php?`;
      url += `tax_type=${encodeURIComponent(permitData.tax_calculation_type)}`;
      url += `&taxable_amount=${encodeURIComponent(permitData.taxable_amount)}`;
      url += `&business_type=${encodeURIComponent(permitData.business_type)}`;
      url += `&permit_id=${encodeURIComponent(permitData.id)}`;
      
      if (rateId) {
        url += `&selected_config_id=${rateId}`;
        setSelectedRate(rateId);
        setCustomRate('');
      } else if (customRateValue !== null) {
        url += `&override_tax_rate=${customRateValue}`;
        setSelectedRate('custom');
        setCustomRate(customRateValue);
      }
      
      const response = await fetch(url);
      const data = await response.json();
      
      if (data.status === 'success') {
        setCalculatedTax(data);
      } else {
        alert('Calculation failed: ' + data.message);
      }
    } catch (err) {
      alert('Error calculating tax');
    }
  };

  // Handle rate selection
  const handleRateSelect = (rateId, taxPercent) => {
    setSelectedRate(rateId);
    setCustomRate('');
    setShowRateOptions(false);
    if (permit) calculateTax(permit, rateId, null);
  };

  // Handle custom rate
  const handleCustomRate = () => {
    if (!customRate || isNaN(customRate) || customRate <= 0) {
      alert('Please enter a valid tax rate');
      return;
    }
    setSelectedRate('custom');
    setShowRateOptions(false);
    if (permit) calculateTax(permit, null, parseFloat(customRate));
  };

  // Approve permit
  const handleApprove = async () => {
    if (!window.confirm('Approve this business permit?')) return;
    
    try {
      const response = await fetch('http://localhost/revenue/backend/Business/BusinessValidation/update_permit_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id: id,
          status: 'Approved',
          action_by: 'admin',
          remarks: 'Permit approved',
          tax_amount: calculatedTax?.calculation.tax_amount || 0,
          total_tax: calculatedTax?.calculation.total_tax || 0
        })
      });
      
      const data = await response.json();
      if (data.status === 'success') {
        alert('✅ Permit approved successfully!');
        navigate('/business/validation');
      } else {
        throw new Error(data.message);
      }
    } catch (err) {
      alert('Error: ' + err.message);
    }
  };

  // Reject permit
  const handleReject = async () => {
    const reason = prompt('Enter reason for rejection:');
    if (!reason) return;
    
    try {
      const response = await fetch('http://localhost/revenue/backend/Business/BusinessValidation/update_permit_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id: id,
          status: 'Rejected',
          action_by: 'admin',
          remarks: reason
        })
      });
      
      const data = await response.json();
      if (data.status === 'success') {
        alert('❌ Permit rejected');
        navigate('/business/validation');
      } else {
        throw new Error(data.message);
      }
    } catch (err) {
      alert('Error: ' + err.message);
    }
  };

  // Format currency
  const formatCurrency = (amount) => {
    if (!amount && amount !== 0) return '₱0.00';
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP'
    }).format(amount);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <h2 className="text-lg font-semibold text-gray-700">Loading Business Permit...</h2>
          <p className="text-gray-500">Please wait</p>
        </div>
      </div>
    );
  }

  if (!permit) {
    return (
      <div className="mx-auto max-w-4xl p-6">
        <div className="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
          <div className="flex">
            <div className="flex-shrink-0">
              <svg className="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
              </svg>
            </div>
            <div className="ml-3">
              <p className="text-sm text-red-700">Permit not found</p>
            </div>
          </div>
        </div>
        <Link to="/business/validation" className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
          ← Back to Permits List
        </Link>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Business Permit Validation</h1>
            <p className="text-gray-600 mt-1">Review and approve business permit applications</p>
          </div>
          <Link 
            to="/business/validation" 
            className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
          >
            ← Back to List
          </Link>
        </div>
        
        {/* Permit ID Badge */}
        <div className="mt-4 inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
          Permit ID: {permit.business_permit_id}
        </div>
      </div>

      {/* Main Content Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left Column - Business Info */}
        <div className="lg:col-span-2 space-y-6">
          {/* Business Information Card */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200">
            <div className="px-6 py-4 border-b border-gray-200">
              <h2 className="text-lg font-semibold text-gray-900">Business Information</h2>
            </div>
            <div className="p-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-500">Business Name</label>
                  <p className="mt-1 text-sm text-gray-900 font-medium">{permit.business_name}</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-500">Owner Name</label>
                  <p className="mt-1 text-sm text-gray-900 font-medium">{permit.owner_name}</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-500">Business Type</label>
                  <span className="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                    {permit.business_type}
                  </span>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-500">Tax Type</label>
                  <span className="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment' : 'Gross Sales'}
                  </span>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-500">Capital/Sales Amount</label>
                  <p className="mt-1 text-lg font-bold text-gray-900">{formatCurrency(permit.taxable_amount)}</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-500">Address</label>
                  <p className="mt-1 text-sm text-gray-900">{permit.address || 'Not specified'}</p>
                </div>
              </div>
            </div>
          </div>

          {/* Tax Calculation Card */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200">
            <div className="px-6 py-4 border-b border-gray-200">
              <div className="flex justify-between items-center">
                <h2 className="text-lg font-semibold text-gray-900">Tax Calculation</h2>
                <button
                  onClick={() => setShowRateOptions(!showRateOptions)}
                  className="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                >
                  {showRateOptions ? 'Cancel' : 'Adjust Tax Rate'}
                </button>
              </div>
            </div>
            
            {/* Rate Options */}
            {showRateOptions && calculatedTax && (
              <div className="p-6 bg-gray-50 border-b">
                <div className="mb-4">
                  <h3 className="text-sm font-medium text-gray-700 mb-2">Select Tax Rate:</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                    {calculatedTax.available_configs.map((config) => (
                      <button
                        key={config.id}
                        onClick={() => handleRateSelect(config.id, config.tax_percent)}
                        className={`p-3 border rounded-lg text-left transition-colors ${
                          selectedRate === config.id
                            ? 'border-blue-500 bg-blue-50'
                            : 'border-gray-300 hover:bg-gray-50'
                        }`}
                      >
                        <div className="font-medium">
                          {permit.tax_calculation_type === 'capital_investment'
                            ? `₱${parseFloat(config.min_amount).toLocaleString()} - ₱${parseFloat(config.max_amount).toLocaleString()}`
                            : config.business_type
                          }
                        </div>
                        <div className="text-sm text-gray-600">
                          Rate: <span className="font-bold">{config.tax_percent}%</span>
                        </div>
                      </button>
                    ))}
                  </div>
                </div>
                
                {/* Custom Rate */}
                <div>
                  <h3 className="text-sm font-medium text-gray-700 mb-2">Or Enter Custom Rate:</h3>
                  <div className="flex gap-2">
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      value={customRate}
                      onChange={(e) => setCustomRate(e.target.value)}
                      placeholder="e.g., 2.5"
                      className="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    />
                    <button
                      onClick={handleCustomRate}
                      className="px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700"
                    >
                      Apply Custom Rate
                    </button>
                  </div>
                </div>
              </div>
            )}

            {/* Current Calculation */}
            {calculatedTax && (
              <div className="p-6">
                {/* Current Rate Info */}
                <div className="mb-6 p-4 bg-blue-50 rounded-lg">
                  <div className="flex justify-between items-center">
                    <div>
                      <h3 className="font-medium text-blue-900">Current Tax Rate</h3>
                      <p className="text-2xl font-bold text-blue-900">{calculatedTax.calculation.tax_rate}%</p>
                      {calculatedTax.config_used?.remarks && (
                        <p className="text-sm text-blue-700 mt-1">{calculatedTax.config_used.remarks}</p>
                      )}
                    </div>
                    <div className="text-right">
                      <div className="text-sm text-blue-700">Applied</div>
                      <div className="text-lg font-semibold text-blue-900">
                        {selectedRate === 'custom' ? 'Custom Rate' : 'Standard Rate'}
                      </div>
                    </div>
                  </div>
                </div>

                {/* Calculation Summary */}
                <div className="space-y-4">
                  <div className="flex justify-between items-center py-2 border-b">
                    <span className="text-gray-600">Taxable Amount:</span>
                    <span className="font-semibold">{formatCurrency(calculatedTax.calculation.taxable_amount)}</span>
                  </div>
                  <div className="flex justify-between items-center py-2 border-b">
                    <span className="text-gray-600">Tax Rate:</span>
                    <span className="font-semibold">{calculatedTax.calculation.tax_rate}%</span>
                  </div>
                  <div className="flex justify-between items-center py-2 border-b">
                    <span className="text-gray-600">Tax Amount:</span>
                    <span className="font-semibold">{formatCurrency(calculatedTax.calculation.tax_amount)}</span>
                  </div>
                  <div className="flex justify-between items-center py-2 border-b">
                    <span className="text-gray-600">Regulatory Fees:</span>
                    <span className="font-semibold">{formatCurrency(calculatedTax.calculation.regulatory_fees)}</span>
                  </div>
                  
                  {/* Total Tax */}
                  <div className="pt-4 mt-4 border-t">
                    <div className="flex justify-between items-center">
                      <div>
                        <h3 className="text-lg font-bold text-gray-900">Total Annual Tax</h3>
                        <p className="text-sm text-gray-500">Valid for one year from issue date</p>
                      </div>
                      <div className="text-right">
                        <p className="text-3xl font-bold text-green-600">{formatCurrency(calculatedTax.calculation.total_tax)}</p>
                        <p className="text-sm text-gray-500">
                          = {formatCurrency(calculatedTax.calculation.tax_amount)} + {formatCurrency(calculatedTax.calculation.regulatory_fees)}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Right Column - Actions & Summary */}
        <div className="space-y-6">
          {/* Action Panel */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200">
            <div className="px-6 py-4 border-b border-gray-200">
              <h2 className="text-lg font-semibold text-gray-900">Actions</h2>
            </div>
            <div className="p-6 space-y-4">
              <button
                onClick={handleApprove}
                disabled={!calculatedTax}
                className="w-full inline-flex justify-center items-center px-4 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                </svg>
                Approve Permit
              </button>
              
              <button
                onClick={handleReject}
                className="w-full inline-flex justify-center items-center px-4 py-3 border border-gray-300 text-base font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50"
              >
                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
                Reject Permit
              </button>
              
              <button
                onClick={() => window.print()}
                className="w-full inline-flex justify-center items-center px-4 py-3 border border-gray-300 text-base font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50"
              >
                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Print Preview
              </button>
            </div>
          </div>

          {/* Fee Breakdown */}
          {calculatedTax && (
            <div className="bg-white rounded-lg shadow-sm border border-gray-200">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-semibold text-gray-900">Fee Breakdown</h2>
              </div>
              <div className="p-6">
                <ul className="space-y-3">
                  {calculatedTax.fee_breakdown.map((fee, index) => (
                    <li key={index} className="flex justify-between items-center">
                      <div>
                        <span className="text-sm font-medium text-gray-900">{fee.name}</span>
                        {fee.remarks && (
                          <p className="text-xs text-gray-500">{fee.remarks}</p>
                        )}
                      </div>
                      <span className="text-sm font-semibold text-gray-900">{formatCurrency(fee.amount)}</span>
                    </li>
                  ))}
                </ul>
                <div className="mt-4 pt-4 border-t">
                  <div className="flex justify-between items-center font-semibold">
                    <span>Total Fees:</span>
                    <span>{formatCurrency(calculatedTax.calculation.regulatory_fees)}</span>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Status & Info */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200">
            <div className="px-6 py-4 border-b border-gray-200">
              <h2 className="text-lg font-semibold text-gray-900">Permit Status</h2>
            </div>
            <div className="p-6">
              <div className="flex items-center justify-between mb-4">
                <span className="text-sm font-medium text-gray-500">Current Status</span>
                <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${
                  permit.status === 'Pending' ? 'bg-yellow-100 text-yellow-800' :
                  permit.status === 'Approved' ? 'bg-green-100 text-green-800' :
                  'bg-red-100 text-red-800'
                }`}>
                  {permit.status}
                </span>
              </div>
              <div className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <span className="text-gray-500">Issue Date:</span>
                  <span className="font-medium">{new Date(permit.issue_date).toLocaleDateString()}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-500">Expiry Date:</span>
                  <span className="font-medium">{new Date(permit.expiry_date).toLocaleDateString()}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-500">Contact Number:</span>
                  <span className="font-medium">{permit.contact_number || 'N/A'}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Footer Note */}
      <div className="mt-8 text-center text-sm text-gray-500">
        <p>All tax calculations are based on current LGU tax ordinances and regulations.</p>
        <p className="mt-1">For assistance, contact the Business Permits and Licensing Office.</p>
      </div>
    </div>
  );
};

export default BusinessValidationInfo;