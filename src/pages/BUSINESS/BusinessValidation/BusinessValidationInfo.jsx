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
  const [quarterlyBreakdown, setQuarterlyBreakdown] = useState(null);
  const [error, setError] = useState('');

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
        setError('');
        
        if (!id) {
          alert('Error: No permit ID provided');
          navigate('/business/validation');
          return;
        }
        
        // Fetch permit details
        const permitUrl = `${API_BASE}/get_permit_details.php?id=${id}`;
        console.log('Fetching from:', permitUrl);
        
        const permitRes = await fetch(permitUrl, {
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          }
        });
        
        // Get response as text first
        const responseText = await permitRes.text();
        console.log('Raw response:', responseText.substring(0, 200));
        
        // Check if response is HTML error
        if (responseText.includes('<br />') || responseText.includes('<b>') || responseText.trim().startsWith('<')) {
          throw new Error('Server returned HTML error instead of JSON. Check PHP file.');
        }
        
        // Try to parse as JSON
        let permitData;
        try {
          permitData = JSON.parse(responseText);
        } catch (parseError) {
          console.error('JSON Parse Error:', parseError);
          console.error('Response was:', responseText);
          throw new Error('Invalid JSON response from server');
        }
        
        if (!permitRes.ok) {
          throw new Error(`HTTP error! status: ${permitRes.status}`);
        }
        
        if (permitData.status !== 'success') {
          throw new Error(permitData.message || 'Failed to load permit details');
        }
        
        if (!permitData.permit) {
          throw new Error('Permit data not found in response');
        }
        
        console.log('Permit loaded:', permitData.permit);
        setPermit(permitData.permit);
        
        // Calculate initial tax
        await calculateTax(permitData.permit);
        
      } catch (err) {
        console.error('Error in loadData:', err);
        setError('Error loading data: ' + err.message);
        alert('Error loading permit details: ' + err.message);
        navigate('/business/validation');
      } finally {
        setLoading(false);
      }
    };
    
    loadData();
  }, [id, navigate, API_BASE]);

  // Calculate quarterly breakdown
  const calculateQuarterlyBreakdown = (totalTax, issueDate) => {
    const quarterlyAmount = (totalTax / 4).toFixed(2);
    const issueDateObj = issueDate ? new Date(issueDate) : new Date();
    const currentYear = issueDateObj.getFullYear();
    
    // Define quarterly due dates
    const quarters = [
      { 
        quarter: 'Q1', 
        due_date: new Date(currentYear, 2, 31), // March 31
        label: '1st Quarter (Jan-Mar)'
      },
      { 
        quarter: 'Q2', 
        due_date: new Date(currentYear, 5, 30), // June 30
        label: '2nd Quarter (Apr-Jun)'
      },
      { 
        quarter: 'Q3', 
        due_date: new Date(currentYear, 8, 30), // September 30
        label: '3rd Quarter (Jul-Sep)'
      },
      { 
        quarter: 'Q4', 
        due_date: new Date(currentYear, 11, 31), // December 31
        label: '4th Quarter (Oct-Dec)'
      }
    ];
    
    return quarters.map(quarter => ({
      ...quarter,
      quarterly_tax_amount: parseFloat(quarterlyAmount),
      due_date: quarter.due_date.toISOString().split('T')[0],
      payment_status: 'pending',
      penalty_amount: 0,
      discount_amount: 0,
      paid_amount: 0,
      balance_amount: parseFloat(quarterlyAmount)
    }));
  };

  // Calculate tax function
  const calculateTax = async (permitData, rateId = null, customRateValue = null) => {
    try {
      if (!permitData || !permitData.id) {
        alert('Error: Permit data not available');
        return;
      }
      
      let url = `${API_BASE}/calculate_tax.php?`;
      url += `tax_type=${encodeURIComponent(permitData.tax_calculation_type || 'gross_sales')}`;
      url += `&taxable_amount=${encodeURIComponent(permitData.taxable_amount || 0)}`;
      url += `&business_type=${encodeURIComponent(permitData.business_type || 'Retail')}`;
      url += `&permit_id=${encodeURIComponent(permitData.id)}`;
      
      if (rateId) {
        url += `&selected_config_id=${rateId}`;
      } else if (customRateValue !== null) {
        url += `&override_tax_rate=${customRateValue}`;
      }
      
      console.log('Calculating tax from:', url);
      
      const response = await fetch(url);
      const text = await response.text();
      
      // Check if response is HTML
      if (text.includes('<br />') || text.includes('<b>') || text.trim().startsWith('<')) {
        console.warn('Tax calculation API returned HTML, using fallback');
        // Use fallback calculation
        const taxableAmount = permitData.taxable_amount || 0;
        const taxRate = permitData.tax_rate || 2.0;
        const taxAmount = taxableAmount * taxRate / 100;
        const regulatoryFees = 499.98 + 500 + 300; // Sum of regulatory fees
        const totalTax = taxAmount + regulatoryFees;
        
        const simpleTax = {
          status: 'success',
          calculation: {
            taxable_amount: taxableAmount,
            tax_rate: taxRate,
            tax_amount: taxAmount,
            regulatory_fees: regulatoryFees,
            total_tax: totalTax
          }
        };
        
        setCalculatedTax(simpleTax);
        const quarterlyData = calculateQuarterlyBreakdown(totalTax, permitData.issue_date);
        setQuarterlyBreakdown(quarterlyData);
        return;
      }
      
      // Parse JSON
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        console.error('Tax calculation JSON parse error:', parseError);
        throw new Error('Invalid response from tax calculation');
      }
      
      if (data.status === 'success') {
        setCalculatedTax(data);
        
        // Calculate quarterly breakdown
        const totalTax = data.calculation?.total_tax || 0;
        const quarterlyData = calculateQuarterlyBreakdown(totalTax, permitData.issue_date);
        setQuarterlyBreakdown(quarterlyData);
      } else {
        alert('Calculation failed: ' + (data.message || 'Unknown error'));
      }
    } catch (err) {
      console.error('Tax calculation error:', err);
      
      // Fallback calculation
      const taxableAmount = permitData.taxable_amount || 0;
      const taxRate = permitData.tax_rate || 2.0;
      const taxAmount = taxableAmount * taxRate / 100;
      const regulatoryFees = 499.98 + 500 + 300; // Sum of regulatory fees
      const totalTax = taxAmount + regulatoryFees;
      
      const simpleTax = {
        status: 'success',
        calculation: {
          taxable_amount: taxableAmount,
          tax_rate: taxRate,
          tax_amount: taxAmount,
          regulatory_fees: regulatoryFees,
          total_tax: totalTax
        }
      };
      
      setCalculatedTax(simpleTax);
      
      // Calculate quarterly breakdown
      const quarterlyData = calculateQuarterlyBreakdown(totalTax, permitData.issue_date);
      setQuarterlyBreakdown(quarterlyData);
    }
  };

  // Determine if tax is calculated based on data
  const isTaxCalculated = () => {
    if (!permit) return false;
    return (parseFloat(permit.taxable_amount) > 0 && parseFloat(permit.tax_amount) > 0);
  };

  // Determine if tax is approved based on status
  const isTaxApproved = () => {
    if (!permit) return false;
    return (permit.status === 'Approved' || permit.status === 'Active');
  };

  // Handle approve with quarterly generation
  const handleApprove = async () => {
    if (!window.confirm('Approve this business permit and generate quarterly taxes?')) return;
    
    if (!calculatedTax || !quarterlyBreakdown) {
      alert('Please wait for tax calculation to complete');
      return;
    }
    
    if (!permit) {
      alert('No permit data available');
      return;
    }
    
    try {
      // First, update the permit status
      const updateUrl = `${API_BASE}/update_permit_status.php`;
      console.log('Updating permit status at:', updateUrl);
      
      const updateResponse = await fetch(updateUrl, {
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
          regulatory_fees: calculatedTax.calculation?.regulatory_fees || 0,
          total_tax: calculatedTax.calculation?.total_tax || 0,
          approved_date: new Date().toISOString()
        })
      });
      
      // Check response
      const updateText = await updateResponse.text();
      console.log('Update response:', updateText);
      
      let updateData;
      try {
        updateData = JSON.parse(updateText);
      } catch (parseError) {
        console.error('Update response parse error:', parseError);
        throw new Error('Invalid response from update API');
      }
      
      if (updateData.status !== 'success') {
        throw new Error(updateData.message || 'Approval failed');
      }
      
      // Try to generate quarterly taxes
      try {
        const quarterlyUrl = `${API_BASE}/generate_quarterly_taxes.php`;
        const quarterlyResponse = await fetch(quarterlyUrl, {
          method: 'POST',
          headers: { 
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            permit_id: id,
            annual_tax_amount: calculatedTax.calculation?.total_tax || 0,
            tax_year: new Date().getFullYear(),
            quarterly_breakdown: quarterlyBreakdown,
            remarks: 'Quarterly taxes generated upon approval'
          })
        });
        
        const quarterlyText = await quarterlyResponse.text();
        console.log('Quarterly response:', quarterlyText);
        
        let quarterlyData;
        try {
          quarterlyData = JSON.parse(quarterlyText);
        } catch (parseError) {
          console.warn('Quarterly tax generation response parse error:', parseError);
          // Continue even if quarterly generation fails
        }
        
        if (quarterlyData && quarterlyData.status === 'success') {
          alert('✅ Permit approved successfully! Quarterly taxes have been generated.');
        } else {
          alert('✅ Permit approved successfully! (Quarterly tax generation may need manual setup)');
        }
      } catch (quarterlyError) {
        console.warn('Quarterly tax generation error:', quarterlyError);
        alert('✅ Permit approved successfully! (Note: Quarterly tax generation encountered an error)');
      }
      
      navigate('/business/validation');
      
    } catch (err) {
      console.error('Approve error:', err);
      alert('Error approving permit: ' + err.message);
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

  // Handle reject
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
          remarks: reason
        })
      });
      
      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        throw new Error('Invalid response from server');
      }
      
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

  // Format date
  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-PH', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <h2 className="text-lg font-semibold text-gray-700">Loading Business Permit...</h2>
          <p className="text-gray-500">Please wait</p>
          {error && <p className="text-red-500 text-sm mt-2">{error}</p>}
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
              {error && <p className="text-sm text-red-600 mt-1">{error}</p>}
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

      {/* Error Display */}
      {error && (
        <div className="mb-4 p-4 bg-yellow-50 border-l-4 border-yellow-400">
          <div className="flex">
            <div className="flex-shrink-0">
              <svg className="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
              </svg>
            </div>
            <div className="ml-3">
              <p className="text-sm text-yellow-700">{error}</p>
            </div>
          </div>
        </div>
      )}

      {/* Main Content Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left Column - Business Info & Tax Calculation */}
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
                {/* Tax Status Indicators */}
                <div>
                  <label className="block text-sm font-medium text-gray-500">Tax Calculation Status</label>
                  <span className={`mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                    isTaxCalculated() ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                  }`}>
                    {isTaxCalculated() ? 'Calculated' : 'Not Calculated'}
                  </span>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-500">Tax Approval Status</label>
                  <span className={`mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                    isTaxApproved() ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                  }`}>
                    {isTaxApproved() ? 'Approved' : 'Not Approved'}
                  </span>
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
            
            {/* Current Calculation */}
            {calculatedTax ? (
              <div className="p-6">
                {/* Total Tax Display */}
                <div className="mb-6 p-4 bg-green-50 rounded-lg border border-green-200">
                  <div className="flex justify-between items-center">
                    <div>
                      <h3 className="text-lg font-bold text-green-900">Total Annual Tax</h3>
                      <p className="text-sm text-green-700">Valid for one year from issue date</p>
                    </div>
                    <div className="text-right">
                      <p className="text-3xl font-bold text-green-600">{formatCurrency(calculatedTax.calculation?.total_tax || 0)}</p>
                      <p className="text-sm text-green-700">
                        = {formatCurrency(calculatedTax.calculation?.tax_amount || 0)} (Tax) + {formatCurrency(calculatedTax.calculation?.regulatory_fees || 0)} (Fees)
                      </p>
                    </div>
                  </div>
                </div>

                {/* Quarterly Breakdown */}
                {quarterlyBreakdown && (
                  <div className="mt-8">
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Quarterly Payment Breakdown</h3>
                    <div className="bg-gray-50 rounded-lg p-4">
                      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        {quarterlyBreakdown.map((quarter, index) => (
                          <div key={index} className="bg-white p-4 rounded-lg border border-gray-200">
                            <div className="flex justify-between items-start mb-2">
                              <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                quarter.quarter === 'Q1' ? 'bg-blue-100 text-blue-800' :
                                quarter.quarter === 'Q2' ? 'bg-green-100 text-green-800' :
                                quarter.quarter === 'Q3' ? 'bg-yellow-100 text-yellow-800' :
                                'bg-purple-100 text-purple-800'
                              }`}>
                                {quarter.quarter}
                              </span>
                            </div>
                            <p className="text-xs text-gray-500 mb-1">{quarter.label}</p>
                            <p className="text-lg font-bold text-gray-900 mb-1">{formatCurrency(quarter.quarterly_tax_amount)}</p>
                            <p className="text-xs text-gray-500">Due: {formatDate(quarter.due_date)}</p>
                          </div>
                        ))}
                      </div>
                      <div className="mt-4 pt-4 border-t border-gray-200 text-center">
                        <p className="text-sm text-gray-600">
                          Total Quarterly Payments: <span className="font-semibold">{formatCurrency(calculatedTax.calculation?.total_tax || 0)}</span>
                        </p>
                        <p className="text-xs text-gray-500 mt-1">
                          Each quarter: {formatCurrency((calculatedTax.calculation?.total_tax || 0) / 4)}
                        </p>
                      </div>
                    </div>
                  </div>
                )}

                {/* Tax Details */}
                <div className="space-y-4 mt-6">
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
                </div>

                {/* Rate Options - Moved to bottom */}
                {showRateOptions && (
                  <div className="mt-6 p-4 bg-gray-50 rounded-lg border">
                    <h3 className="text-sm font-medium text-gray-700 mb-2">Rate Adjustment Options:</h3>
                    <div className="flex gap-2">
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        value={customRate}
                        onChange={(e) => setCustomRate(e.target.value)}
                        placeholder="Enter custom rate (e.g., 2.5)"
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
                )}
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
                disabled={!calculatedTax || !quarterlyBreakdown}
                className="w-full inline-flex justify-center items-center px-4 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                </svg>
                Approve & Generate Quarterly
              </button>
              
              <button
                onClick={handleReject}
                className="w-full inline-flex justify-center items-center px-4 py-3 border border-gray-300 text-base font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 transition-colors"
              >
                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
                Reject Permit
              </button>
              
              <button
                onClick={() => window.print()}
                className="w-full inline-flex justify-center items-center px-4 py-3 border border-gray-300 text-base font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 transition-colors"
              >
                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Print Preview
              </button>
            </div>
          </div>

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
                  <span className="font-medium">{formatDate(permit.issue_date)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-500">Expiry Date:</span>
                  <span className="font-medium">{formatDate(permit.expiry_date)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-500">Created:</span>
                  <span className="font-medium">{formatDate(permit.created_at)}</span>
                </div>
                {permit.approved_date && (
                  <div className="flex justify-between">
                    <span className="text-gray-500">Approved Date:</span>
                    <span className="font-medium">{formatDate(permit.approved_date)}</span>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Payment Summary */}
          {quarterlyBreakdown && (
            <div className="bg-white rounded-lg shadow-sm border border-gray-200">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-semibold text-gray-900">Payment Summary</h2>
              </div>
              <div className="p-6">
                <div className="space-y-3">
                  {quarterlyBreakdown.map((quarter, index) => (
                    <div key={index} className="flex justify-between items-center">
                      <div className="flex items-center">
                        <span className={`w-8 h-8 flex items-center justify-center rounded-full text-xs font-medium mr-2 ${
                          quarter.quarter === 'Q1' ? 'bg-blue-100 text-blue-800' :
                          quarter.quarter === 'Q2' ? 'bg-green-100 text-green-800' :
                          quarter.quarter === 'Q3' ? 'bg-yellow-100 text-yellow-800' :
                          'bg-purple-100 text-purple-800'
                        }`}>
                          {quarter.quarter}
                        </span>
                        <span className="text-sm text-gray-700">{quarter.label}</span>
                      </div>
                      <span className="font-semibold text-gray-900">{formatCurrency(quarter.quarterly_tax_amount)}</span>
                    </div>
                  ))}
                </div>
                <div className="mt-4 pt-4 border-t border-gray-200">
                  <div className="flex justify-between items-center font-bold text-gray-900">
                    <span>Total Annual:</span>
                    <span>{formatCurrency(calculatedTax?.calculation?.total_tax || 0)}</span>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Footer Note */}
      <div className="mt-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
        <p className="text-sm text-blue-800 text-center">
          <strong>Note:</strong> Approving this permit will generate quarterly tax payments automatically. 
          Each quarter is due at the end of the quarter (March 31, June 30, September 30, December 31).
        </p>
        <p className="text-xs text-blue-600 text-center mt-1">
          Permits are valid for one year from the issue date. Late payments may incur penalties.
        </p>
      </div>
    </div>
  );
};

export default BusinessValidationInfo;