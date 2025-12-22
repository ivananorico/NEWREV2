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

  // Determine environment
  const isLocalhost = window.location.hostname === 'localhost' || 
                      window.location.hostname === '127.0.0.1' ||
                      window.location.hostname === '';
  const API_BASE = isLocalhost
    ? "http://localhost/revenue2/backend/Business/BusinessValidation"
    : "/backend/Business/BusinessValidation";

  // Load permit and calculate tax
  useEffect(() => {
    const loadData = async () => {
      try {
        setLoading(true);
        
        // Check if ID exists
        if (!id) {
          console.error('No ID provided');
          alert('Error: No permit ID provided');
          navigate('/business/validation');
          return;
        }
        
        // Fetch permit details
        const permitUrl = `${API_BASE}/get_permit_details.php?id=${id}`;
        console.log(`Fetching from: ${permitUrl}`);
        const permitRes = await fetch(permitUrl);
        
        if (!permitRes.ok) {
          throw new Error(`HTTP error! status: ${permitRes.status}`);
        }
        
        const contentType = permitRes.headers.get("content-type");
        let permitData;
        
        if (contentType && contentType.includes("application/json")) {
          permitData = await permitRes.json();
        } else {
          const text = await permitRes.text();
          try {
            permitData = JSON.parse(text);
          } catch (parseError) {
            console.error('Failed to parse JSON:', text);
            throw new Error('Invalid JSON response from server');
          }
        }
        
        console.log('Permit API Response:', permitData);
        
        if (permitData.status !== 'success') {
          alert('Error loading permit: ' + (permitData.message || 'Unknown error'));
          navigate('/business/validation');
          return;
        }
        
        if (!permitData.permit) {
          throw new Error('Permit data not found in response');
        }
        
        setPermit(permitData.permit);
        
        // Calculate initial tax
        await calculateTax(permitData.permit);
        
      } catch (err) {
        console.error('Error in loadData:', err);
        alert('Error loading data: ' + err.message);
        navigate('/business/validation');
      } finally {
        setLoading(false);
      }
    };
    
    loadData();
  }, [id, navigate, API_BASE]);

  // Fixed calculateTax function
  const calculateTax = async (permitData, rateId = null, customRateValue = null) => {
    try {
      console.log('Calculating tax for permit:', permitData);
      
      // DEFENSIVE CHECK: Ensure permitData exists
      if (!permitData) {
        console.error('No permit data provided to calculateTax');
        alert('Error: No permit data available for calculation');
        return;
      }
      
      // DEFENSIVE CHECK: Ensure required properties exist
      if (!permitData.id) {
        console.error('Permit ID is missing', permitData);
        alert('Error: Permit ID is missing');
        return;
      }
      
      if (!permitData.taxable_amount && permitData.taxable_amount !== 0) {
        console.error('Taxable amount is missing', permitData);
        alert('Error: Taxable amount is not available');
        return;
      }
      
      // Try to use the tax breakdown from get_permit_details.php first
      try {
        const detailsUrl = `${API_BASE}/get_permit_details.php?id=${permitData.id}`;
        console.log('Fetching tax details from:', detailsUrl);
        const detailsRes = await fetch(detailsUrl);
        
        if (detailsRes.ok) {
          const detailsData = await detailsRes.json();
          console.log('Tax details response:', detailsData);
          
          if (detailsData.status === 'success' && detailsData.tax_breakdown) {
            // Use the tax breakdown from the details API
            setCalculatedTax({
              status: 'success',
              calculation: {
                taxable_amount: permitData.taxable_amount || 0,
                tax_rate: permitData.tax_rate || detailsData.tax_breakdown.tax_rate_used || 0,
                tax_amount: permitData.tax_amount || 0,
                regulatory_fees: permitData.regulatory_fees || 0,
                total_tax: permitData.total_tax || 0
              },
              tax_breakdown: detailsData.tax_breakdown,
              config_used: detailsData.config_used || {},
              fee_breakdown: [
                {
                  name: 'Tax Amount',
                  amount: permitData.tax_amount || 0,
                  remarks: `Based on ${permitData.tax_rate || 0}% rate`
                },
                {
                  name: 'Regulatory Fees',
                  amount: permitData.regulatory_fees || 0,
                  remarks: 'Standard fees'
                }
              ]
            });
            return;
          }
        }
      } catch (apiError) {
        console.warn('Failed to fetch tax details, using fallback:', apiError);
      }
      
      // Fallback to separate calculation if needed
      let url = `${API_BASE}/calculate_tax.php?`;
      url += `tax_type=${encodeURIComponent(permitData.tax_calculation_type || 'gross_sales')}`;
      url += `&taxable_amount=${encodeURIComponent(permitData.taxable_amount || 0)}`;
      url += `&business_type=${encodeURIComponent(permitData.business_type || 'Retail')}`;
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
      
      console.log('Calculating tax via:', url);
      const response = await fetch(url);
      const data = await response.json();
      console.log('Tax calculation response:', data);
      
      if (data.status === 'success') {
        setCalculatedTax(data);
      } else {
        alert('Calculation failed: ' + (data.message || 'Unknown error'));
      }
    } catch (err) {
      console.error('Tax calculation error:', err);
      
      // Create a safe calculation if API fails
      const taxableAmount = permitData.taxable_amount || 0;
      const taxRate = permitData.tax_rate || 2.0;
      const taxAmount = permitData.tax_amount || (taxableAmount * taxRate / 100);
      const regulatoryFees = permitData.regulatory_fees || 500;
      
      const simpleTax = {
        status: 'success',
        calculation: {
          taxable_amount: taxableAmount,
          tax_rate: taxRate,
          tax_amount: taxAmount,
          regulatory_fees: regulatoryFees,
          total_tax: taxAmount + regulatoryFees
        },
        tax_breakdown: {
          base_amount: taxableAmount,
          tax_rate_used: taxRate,
          tax_amount: taxAmount,
          regulatory_fees: regulatoryFees,
          total_tax: taxAmount + regulatoryFees,
          calculation_steps: [
            {
              step: 1,
              description: 'Tax Calculation',
              formula: 'Taxable Amount × Tax Rate',
              calculation: `${taxableAmount} × ${taxRate}%`,
              result: taxAmount
            },
            {
              step: 2,
              description: 'Regulatory Fees',
              formula: 'Fixed Fees',
              calculation: 'Standard regulatory charges',
              result: regulatoryFees
            }
          ]
        },
        config_used: {
          tax_config: null,
          regulatory_fees: [],
          calculation_date: new Date().toISOString().split('T')[0]
        },
        fee_breakdown: [
          {
            name: 'Tax Amount',
            amount: taxAmount,
            remarks: `Based on ${taxRate}% rate`
          },
          {
            name: 'Regulatory Fees',
            amount: regulatoryFees,
            remarks: 'Standard fees'
          }
        ]
      };
      setCalculatedTax(simpleTax);
    }
  };

  // Handle rate selection
  const handleRateSelect = (rateId, taxPercent) => {
    if (!permit) {
      alert('No permit data available');
      return;
    }
    
    setSelectedRate(rateId);
    setCustomRate('');
    setShowRateOptions(false);
    calculateTax(permit, rateId, null);
  };

  // Handle custom rate
  const handleCustomRate = () => {
    if (!permit) {
      alert('No permit data available');
      return;
    }
    
    if (!customRate || isNaN(customRate) || customRate <= 0) {
      alert('Please enter a valid tax rate (greater than 0)');
      return;
    }
    
    setSelectedRate('custom');
    setShowRateOptions(false);
    calculateTax(permit, null, parseFloat(customRate));
  };

  // Approve permit
  const handleApprove = async () => {
    if (!window.confirm('Approve this business permit?')) return;
    
    if (!calculatedTax) {
      alert('Please wait for tax calculation to complete');
      return;
    }
    
    try {
      const url = `${API_BASE}/update_permit_status.php`;
      console.log('Approving permit via:', url);
      
      const response = await fetch(url, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          id: id,
          status: 'Approved',
          action_by: 'admin',
          remarks: 'Permit approved via system',
          tax_amount: calculatedTax.calculation?.tax_amount || 0,
          total_tax: calculatedTax.calculation?.total_tax || 0,
          tax_calculated: 1,
          tax_approved: 1,
          approved_date: new Date().toISOString()
        })
      });
      
      const data = await response.json();
      console.log('Approve response:', data);
      
      if (data.status === 'success') {
        alert('✅ Permit approved successfully!');
        navigate('/business/validation');
      } else {
        throw new Error(data.message || 'Approval failed');
      }
    } catch (err) {
      console.error('Approve error:', err);
      alert('Error approving permit: ' + err.message);
    }
  };

  // Reject permit
  const handleReject = async () => {
    const reason = prompt('Enter reason for rejection:');
    if (!reason) return;
    
    if (!permit) {
      alert('No permit data available');
      return;
    }
    
    try {
      const url = `${API_BASE}/update_permit_status.php`;
      
      const response = await fetch(url, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          id: id,
          status: 'Rejected',
          action_by: 'admin',
          remarks: reason,
          tax_calculated: permit.tax_calculated || 0,
          tax_approved: 0
        })
      });
      
      const data = await response.json();
      
      if (data.status === 'success') {
        alert('❌ Permit rejected');
        navigate('/business/validation');
      } else {
        throw new Error(data.message || 'Rejection failed');
      }
    } catch (err) {
      alert('Error rejecting permit: ' + err.message);
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
          <p className="text-xs text-gray-400 mt-2">Loading ID: {id}</p>
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
              <p className="text-xs text-red-600 mt-1">Permit ID: {id} could not be loaded</p>
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
          Permit ID: {permit.business_permit_id || id}
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
                  <p className="mt-1 text-sm text-gray-900 font-medium">{permit.business_name || 'N/A'}</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-500">Owner Name</label>
                  <p className="mt-1 text-sm text-gray-900 font-medium">{permit.owner_name || 'N/A'}</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-500">Business Type</label>
                  <span className="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                    {permit.business_type || 'Not specified'}
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
                  <p className="mt-1 text-lg font-bold text-gray-900">{formatCurrency(permit.taxable_amount || 0)}</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-500">Address</label>
                  <p className="mt-1 text-sm text-gray-900">{permit.address || 'Not specified'}</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-500">Contact Number</label>
                  <p className="mt-1 text-sm text-gray-900">{permit.contact_number || 'N/A'}</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-500">Email</label>
                  <p className="mt-1 text-sm text-gray-900">{permit.email || 'N/A'}</p>
                </div>
              </div>
            </div>
          </div>

          {/* Tax Calculation Card */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200">
            <div className="px-6 py-4 border-b border-gray-200">
              <div className="flex justify-between items-center">
                <h2 className="text-lg font-semibold text-gray-900">Tax Calculation</h2>
                {calculatedTax && (
                  <button
                    onClick={() => setShowRateOptions(!showRateOptions)}
                    className="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                  >
                    {showRateOptions ? 'Cancel' : 'Adjust Tax Rate'}
                  </button>
                )}
              </div>
            </div>
            
            {/* Rate Options */}
            {showRateOptions && calculatedTax && calculatedTax.available_configs && (
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
                            ? `₱${parseFloat(config.min_amount || 0).toLocaleString()} - ₱${parseFloat(config.max_amount || 0).toLocaleString()}`
                            : config.business_type || 'Standard Rate'
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
            {calculatedTax ? (
              <div className="p-6">
                {/* Current Rate Info */}
                <div className="mb-6 p-4 bg-blue-50 rounded-lg">
                  <div className="flex justify-between items-center">
                    <div>
                      <h3 className="font-medium text-blue-900">Current Tax Rate</h3>
                      <p className="text-2xl font-bold text-blue-900">{calculatedTax.calculation?.tax_rate || 0}%</p>
                      {calculatedTax.config_used?.tax_config?.remarks && (
                        <p className="text-sm text-blue-700 mt-1">{calculatedTax.config_used.tax_config.remarks}</p>
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
                    <span className="font-semibold">{formatCurrency(calculatedTax.calculation?.taxable_amount || 0)}</span>
                  </div>
                  <div className="flex justify-between items-center py-2 border-b">
                    <span className="text-gray-600">Tax Rate:</span>
                    <span className="font-semibold">{calculatedTax.calculation?.tax_rate || 0}%</span>
                  </div>
                  <div className="flex justify-between items-center py-2 border-b">
                    <span className="text-gray-600">Tax Amount:</span>
                    <span className="font-semibold">{formatCurrency(calculatedTax.calculation?.tax_amount || 0)}</span>
                  </div>
                  <div className="flex justify-between items-center py-2 border-b">
                    <span className="text-gray-600">Regulatory Fees:</span>
                    <span className="font-semibold">{formatCurrency(calculatedTax.calculation?.regulatory_fees || 0)}</span>
                  </div>
                  
                  {/* Total Tax */}
                  <div className="pt-4 mt-4 border-t">
                    <div className="flex justify-between items-center">
                      <div>
                        <h3 className="text-lg font-bold text-gray-900">Total Annual Tax</h3>
                        <p className="text-sm text-gray-500">Valid for one year from issue date</p>
                      </div>
                      <div className="text-right">
                        <p className="text-3xl font-bold text-green-600">{formatCurrency(calculatedTax.calculation?.total_tax || 0)}</p>
                        <p className="text-sm text-gray-500">
                          = {formatCurrency(calculatedTax.calculation?.tax_amount || 0)} + {formatCurrency(calculatedTax.calculation?.regulatory_fees || 0)}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            ) : (
              <div className="p-6 text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p className="text-gray-600">Calculating tax...</p>
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
          {calculatedTax && calculatedTax.fee_breakdown && (
            <div className="bg-white rounded-lg shadow-sm border border-gray-200">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-semibold text-gray-900">Fee Breakdown</h2>
              </div>
              <div className="p-6">
                <ul className="space-y-3">
                  {calculatedTax.fee_breakdown.map((fee, index) => (
                    <li key={index} className="flex justify-between items-center">
                      <div>
                        <span className="text-sm font-medium text-gray-900">{fee.name || 'Fee'}</span>
                        {fee.remarks && (
                          <p className="text-xs text-gray-500">{fee.remarks}</p>
                        )}
                      </div>
                      <span className="text-sm font-semibold text-gray-900">{formatCurrency(fee.amount || 0)}</span>
                    </li>
                  ))}
                </ul>
                <div className="mt-4 pt-4 border-t">
                  <div className="flex justify-between items-center font-semibold">
                    <span>Total Fees:</span>
                    <span>{formatCurrency(calculatedTax.calculation?.regulatory_fees || 0)}</span>
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
                  permit.status === 'Active' ? 'bg-blue-100 text-blue-800' :
                  'bg-red-100 text-red-800'
                }`}>
                  {permit.status || 'Unknown'}
                </span>
              </div>
              <div className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <span className="text-gray-500">Issue Date:</span>
                  <span className="font-medium">{permit.issue_date ? new Date(permit.issue_date).toLocaleDateString() : 'N/A'}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-500">Expiry Date:</span>
                  <span className="font-medium">{permit.expiry_date ? new Date(permit.expiry_date).toLocaleDateString() : 'N/A'}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-500">Created:</span>
                  <span className="font-medium">{permit.created_at ? new Date(permit.created_at).toLocaleDateString() : 'N/A'}</span>
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
        <p className="mt-1 text-xs text-gray-400">Permit ID: {id}</p>
      </div>
    </div>
  );
};

export default BusinessValidationInfo;