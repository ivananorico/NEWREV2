import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';

const BusinessValidationInfo = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const [permit, setPermit] = useState(null);
  const [loading, setLoading] = useState(true);
  const [calculatedTax, setCalculatedTax] = useState(null);
  const [quarterlyBreakdown, setQuarterlyBreakdown] = useState(null);
  const [error, setError] = useState('');
  const [isApproving, setIsApproving] = useState(false);
  const [isCalculating, setIsCalculating] = useState(false);
  
  // Tax rate adjustment states
  const [taxRates, setTaxRates] = useState([]);
  const [selectedRate, setSelectedRate] = useState(null);
  const [customRate, setCustomRate] = useState('');
  const [showRateOptions, setShowRateOptions] = useState(false);
  const [regulatoryFees, setRegulatoryFees] = useState([]);

  const API_BASE = window.location.hostname === 'localhost' || 
                   window.location.hostname === '127.0.0.1'
    ? "http://localhost/revenue2/backend/Business/BusinessValidation"
    : "/backend/Business/BusinessValidation";

  // Load permit and tax data
  useEffect(() => {
    const loadData = async () => {
      try {
        setLoading(true);
        setError('');
        
        if (!id) {
          navigate('/business/validation');
          return;
        }
        
        // Fetch permit details
        const permitUrl = `${API_BASE}/get_permit_details.php?id=${id}`;
        const permitRes = await fetch(permitUrl);
        const responseText = await permitRes.text();
        
        if (responseText.includes('<br />') || responseText.includes('<b>') || responseText.trim().startsWith('<')) {
          throw new Error('Server error. Please check PHP configuration.');
        }
        
        let permitData;
        try {
          permitData = JSON.parse(responseText);
        } catch (parseError) {
          throw new Error('Invalid server response');
        }
        
        if (permitData.status !== 'success') {
          throw new Error(permitData.message || 'Failed to load permit');
        }
        
        if (!permitData.permit) {
          throw new Error('Permit data not found');
        }
        
        setPermit(permitData.permit);
        setRegulatoryFees(permitData.regulatory_fees || []);
        
        // Load tax rates from database
        await loadTaxRates(permitData.permit);
        
        // If tax is already calculated, use it
        if (permitData.permit.tax_amount > 0 && permitData.permit.total_tax > 0) {
          const existingTax = {
            status: 'success',
            calculation: {
              taxable_amount: permitData.permit.taxable_amount,
              tax_rate: permitData.permit.tax_rate,
              tax_amount: permitData.permit.tax_amount,
              regulatory_fees: permitData.permit.regulatory_fees,
              total_tax: permitData.permit.total_tax
            }
          };
          setCalculatedTax(existingTax);
          
          // Calculate quarterly breakdown
          const quarterlyData = calculateQuarterlyBreakdown(
            permitData.permit.total_tax, 
            permitData.permit.issue_date
          );
          setQuarterlyBreakdown(quarterlyData);
          
          // Set selected rate based on current tax rate
          if (permitData.permit.tax_calculation_type === 'capital_investment') {
            // Find matching bracket
            const matchingRate = taxRates.find(rate => 
              permitData.permit.taxable_amount >= parseFloat(rate.min_amount) && 
              permitData.permit.taxable_amount <= parseFloat(rate.max_amount)
            );
            if (matchingRate) {
              setSelectedRate(matchingRate.id);
            }
          } else {
            // For gross sales, find matching business type
            const matchingRate = taxRates.find(rate => 
              rate.business_type === permitData.permit.business_type
            );
            if (matchingRate) {
              setSelectedRate(matchingRate.id);
            }
          }
        } else {
          // Automatically calculate tax if not calculated
          await calculateTax(permitData.permit);
        }
        
      } catch (err) {
        console.error('Error in loadData:', err);
        setError('Error: ' + err.message);
      } finally {
        setLoading(false);
      }
    };
    
    loadData();
  }, [id, navigate, API_BASE]);

  // Load tax rates from database
  const loadTaxRates = async (permitData) => {
    try {
      let ratesUrl;
      if (permitData.tax_calculation_type === 'capital_investment') {
        ratesUrl = `${API_BASE}/get_capital_config.php`;
      } else {
        ratesUrl = `${API_BASE}/get_gross_sale_confog.php`;
      }
      
      const response = await fetch(ratesUrl);
      const data = await response.json();
      
      if (data.status === 'success') {
        setTaxRates(data.data || []);
      }
    } catch (err) {
      console.error('Error loading tax rates:', err);
      // Set default rates if API fails
      if (permitData.tax_calculation_type === 'capital_investment') {
        setTaxRates([
          { id: 1, min_amount: '1.00', max_amount: '5000.00', tax_percent: '20.00' },
          { id: 2, min_amount: '5000.00', max_amount: '10000.00', tax_percent: '25.00' },
          { id: 3, min_amount: '10000.00', max_amount: '15000.00', tax_percent: '25.00' },
          { id: 4, min_amount: '15000.01', max_amount: '20000.00', tax_percent: '25.00' }
        ]);
      } else {
        setTaxRates([
          { id: 1, business_type: 'Retailer', tax_percent: '2.00' },
          { id: 2, business_type: 'Wholesaler', tax_percent: '1.50' },
          { id: 3, business_type: 'Manufacturer', tax_percent: '1.75' },
          { id: 4, business_type: 'Service', tax_percent: '1.25' }
        ]);
      }
    }
  };

  // Calculate quarterly breakdown
  const calculateQuarterlyBreakdown = (totalTax, issueDate) => {
    const quarterlyAmount = (totalTax / 4).toFixed(2);
    const issueDateObj = issueDate ? new Date(issueDate) : new Date();
    const currentYear = issueDateObj.getFullYear();
    
    const quarters = [
      { 
        quarter: 'Q1', 
        due_date: new Date(currentYear, 2, 31),
        label: 'Jan-Mar',
        color: 'bg-blue-50 text-blue-700 border border-blue-200'
      },
      { 
        quarter: 'Q2', 
        due_date: new Date(currentYear, 5, 30),
        label: 'Apr-Jun',
        color: 'bg-green-50 text-green-700 border border-green-200'
      },
      { 
        quarter: 'Q3', 
        due_date: new Date(currentYear, 8, 30),
        label: 'Jul-Sep',
        color: 'bg-yellow-50 text-yellow-700 border border-yellow-200'
      },
      { 
        quarter: 'Q4', 
        due_date: new Date(currentYear, 11, 31),
        label: 'Oct-Dec',
        color: 'bg-purple-50 text-purple-700 border border-purple-200'
      }
    ];
    
    return quarters.map(quarter => ({
      ...quarter,
      quarterly_tax_amount: parseFloat(quarterlyAmount),
      due_date_formatted: quarter.due_date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    }));
  };

  // Calculate tax with selected rate
  const calculateTax = async (permitData, rateId = null, customRateValue = null) => {
    try {
      setIsCalculating(true);
      
      if (!permitData || !permitData.id) {
        alert('Permit data not available');
        return;
      }
      
      let url = `${API_BASE}/calculate_tax.php?`;
      url += `tax_type=${encodeURIComponent(permitData.tax_calculation_type || 'gross_sales')}`;
      url += `&taxable_amount=${encodeURIComponent(permitData.taxable_amount || 0)}`;
      url += `&business_type=${encodeURIComponent(permitData.business_type || 'Retailer')}`;
      url += `&permit_id=${encodeURIComponent(permitData.id)}`;
      
      if (rateId) {
        url += `&selected_config_id=${rateId}`;
      } else if (customRateValue !== null) {
        url += `&override_tax_rate=${customRateValue}`;
      }
      
      const response = await fetch(url);
      const text = await response.text();
      
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        throw new Error('Invalid response from server');
      }
      
      if (data.status === 'success') {
        setCalculatedTax(data);
        
        // Calculate quarterly breakdown
        const totalTax = data.calculation?.total_tax || 0;
        const quarterlyData = calculateQuarterlyBreakdown(totalTax, permitData.issue_date);
        setQuarterlyBreakdown(quarterlyData);
        
        // Update selected rate
        if (rateId) {
          setSelectedRate(rateId);
        } else if (customRateValue !== null) {
          setSelectedRate('custom');
        }
      } else {
        throw new Error(data.message || 'Calculation failed');
      }
    } catch (err) {
      console.error('Tax calculation error:', err);
      
      // Fallback calculation
      const taxableAmount = permitData.taxable_amount || 0;
      const taxRate = permitData.tax_rate || (permitData.tax_calculation_type === 'capital_investment' ? 25 : 2);
      const taxAmount = taxableAmount * taxRate / 100;
      const regulatoryFees = 499.98 + 500 + 300;
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
    } finally {
      setIsCalculating(false);
    }
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

  // Handle approve
  const handleApprove = async () => {
    if (!window.confirm('Approve this business permit with the calculated tax?')) return;
    
    if (!calculatedTax || !permit) {
      alert('Tax calculation is not complete');
      return;
    }
    
    setIsApproving(true);
    
    try {
      // Update permit status and save tax calculation
      const updateUrl = `${API_BASE}/update_permit_status.php`;
      
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
          tax_rate: calculatedTax.calculation?.tax_rate || 0,
          regulatory_fees: calculatedTax.calculation?.regulatory_fees || 0,
          total_tax: calculatedTax.calculation?.total_tax || 0,
          approved_date: new Date().toISOString()
        })
      });
      
      const updateText = await updateResponse.text();
      let updateData;
      try {
        updateData = JSON.parse(updateText);
      } catch (parseError) {
        throw new Error('Invalid server response');
      }
      
      if (updateData.status !== 'success') {
        throw new Error(updateData.message || 'Approval failed');
      }
      
      // Generate quarterly taxes
      try {
        const quarterlyUrl = `${API_BASE}/generate_quarterly_taxes.php`;
        await fetch(quarterlyUrl, {
          method: 'POST',
          headers: { 
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            permit_id: id,
            annual_tax_amount: calculatedTax.calculation?.total_tax || 0,
            tax_year: new Date().getFullYear(),
            remarks: 'Quarterly taxes generated'
          })
        });
      } catch (quarterlyError) {
        console.warn('Quarterly generation skipped:', quarterlyError);
      }
      
      alert('Permit approved successfully!');
      navigate('/business/validation');
      
    } catch (err) {
      console.error('Approve error:', err);
      alert('Error: ' + err.message);
      setIsApproving(false);
    }
  };

  // Format currency
  const formatCurrency = (amount) => {
    if (!amount && amount !== 0) return '₱0.00';
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);
  };

  // Format date
  const formatDate = (dateString) => {
    if (!dateString) return 'Not set';
    try {
      return new Date(dateString).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    } catch (e) {
      return dateString;
    }
  };

  // Get current tax rate info
  const getCurrentRateInfo = () => {
    if (!permit || !calculatedTax) return null;
    
    if (permit.tax_calculation_type === 'capital_investment') {
      const rate = taxRates.find(r => r.id === selectedRate);
      if (rate) {
        return {
          type: 'Bracket',
          range: `₱${parseFloat(rate.min_amount).toLocaleString()} - ₱${parseFloat(rate.max_amount).toLocaleString()}`,
          rate: `${rate.tax_percent}%`
        };
      }
    } else {
      const rate = taxRates.find(r => r.id === selectedRate);
      if (rate) {
        return {
          type: 'Business Type',
          range: rate.business_type,
          rate: `${rate.tax_percent}%`
        };
      }
    }
    
    if (selectedRate === 'custom') {
      return {
        type: 'Custom',
        range: 'Manual adjustment',
        rate: `${calculatedTax.calculation?.tax_rate}%`
      };
    }
    
    return null;
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50">
        <div className="container mx-auto px-4 py-8">
          <div className="flex flex-col items-center justify-center min-h-[60vh]">
            <div className="animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600 mb-6"></div>
            <h2 className="text-lg font-semibold text-gray-900">Loading Business Permit</h2>
            <p className="text-gray-600 mt-2">Please wait...</p>
          </div>
        </div>
      </div>
    );
  }

  if (error || !permit) {
    return (
      <div className="min-h-screen bg-gray-50">
        <div className="container mx-auto px-4 py-8">
          <div className="bg-red-50 border border-red-200 rounded-xl p-8 max-w-2xl mx-auto">
            <div className="flex items-center mb-6">
              <svg className="h-8 w-8 text-red-600 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div>
                <h2 className="text-xl font-bold text-red-900">Error</h2>
                <p className="text-red-700">{error || 'Permit not found'}</p>
              </div>
            </div>
            <Link 
              to="/business/validation" 
              className="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700"
            >
              ← Back to Permits
            </Link>
          </div>
        </div>
      </div>
    );
  }

  const currentRateInfo = getCurrentRateInfo();

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="container mx-auto px-4 py-6">
        
        {/* Header */}
        <div className="mb-6">
          <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
            <div>
              <h1 className="text-xl font-bold text-gray-900">Business Permit Validation</h1>
              <p className="text-gray-600 text-sm">Review and approve business tax calculation</p>
            </div>
            <div className="flex items-center space-x-3">
              <div className="text-sm text-gray-500 bg-white border border-gray-300 px-3 py-1.5 rounded-lg">
                ID: <span className="font-semibold text-blue-600">{permit.business_permit_id}</span>
              </div>
              <button
                onClick={() => window.location.reload()}
                className="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50"
              >
                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh
              </button>
              <Link 
                to="/business/validation" 
                className="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50"
              >
                ← Back
              </Link>
            </div>
          </div>
        </div>

        {/* Main Content */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          {/* Application Details & Tax Calculation */}
          <div className="lg:col-span-2 space-y-6">
            
            {/* Application Information */}
            <div className="bg-white rounded-lg border border-gray-200">
              <div className="px-5 py-3 border-b border-gray-200 bg-gray-50">
                <h2 className="font-semibold text-gray-900">Application Information</h2>
              </div>
              <div className="p-5">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                  
                  <div className="space-y-4">
                    <div>
                      <label className="block text-xs font-medium text-gray-500 mb-1">Business Name</label>
                      <p className="text-sm font-semibold text-gray-900">{permit.business_name}</p>
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-500 mb-1">Owner</label>
                      <p className="text-sm text-gray-900">{permit.owner_name}</p>
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-500 mb-1">Contact</label>
                      <p className="text-sm text-gray-700">
                        {permit.contact_number || permit.phone || 'Not provided'}
                      </p>
                    </div>
                  </div>

                  <div className="space-y-4">
                    <div>
                      <label className="block text-xs font-medium text-gray-500 mb-1">Business Type</label>
                      <div className="flex items-center space-x-2">
                        <span className="px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-800">
                          {permit.business_type}
                        </span>
                        <span className="px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800">
                          {permit.tax_calculation_type === 'capital_investment' ? 'Capital' : 'Gross Sales'}
                        </span>
                      </div>
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-500 mb-1">Location</label>
                      <p className="text-sm text-gray-700">
                        {permit.barangay}, {permit.city}
                      </p>
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-500 mb-1">Application Date</label>
                      <p className="text-sm text-gray-700">{formatDate(permit.created_at)}</p>
                    </div>
                  </div>

                </div>
              </div>
            </div>

            {/* Tax Calculation Section */}
            <div className="bg-white rounded-lg border border-gray-200">
              <div className="px-5 py-3 border-b border-gray-200 bg-gray-50">
                <div className="flex items-center justify-between">
                  <h2 className="font-semibold text-gray-900">Tax Calculation</h2>
                  {calculatedTax && (
                    <button
                      onClick={() => setShowRateOptions(!showRateOptions)}
                      className="text-sm text-blue-600 hover:text-blue-800"
                    >
                      {showRateOptions ? 'Cancel Adjustment' : 'Adjust Tax Rate'}
                    </button>
                  )}
                </div>
              </div>
              
              <div className="p-5">
                {/* Tax Rate Selection */}
                {showRateOptions && (
                  <div className="mb-6 p-4 bg-blue-50 rounded border border-blue-200">
                    <h3 className="font-medium text-gray-900 mb-3">Select Tax Rate</h3>
                    
                    {/* Available Tax Rates from Database */}
                    <div className="mb-4">
                      <h4 className="text-sm font-medium text-gray-700 mb-2">
                        Available {permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment Tax Brackets' : 'Business Type Rates'}
                      </h4>
                      <div className="space-y-2">
                        {taxRates.map((rate) => (
                          <button
                            key={rate.id}
                            onClick={() => calculateTax(permit, rate.id)}
                            className={`w-full text-left p-3 rounded border transition-colors ${
                              selectedRate === rate.id
                                ? 'bg-blue-100 border-blue-300'
                                : 'bg-white border-gray-200 hover:bg-gray-50'
                            }`}
                          >
                            <div className="flex justify-between items-center">
                              <div>
                                {permit.tax_calculation_type === 'capital_investment' ? (
                                  <>
                                    <span className="font-medium text-gray-900">
                                      ₱{parseFloat(rate.min_amount).toLocaleString()} - ₱{parseFloat(rate.max_amount).toLocaleString()}
                                    </span>
                                    <div className="text-xs text-gray-500">Capital range</div>
                                  </>
                                ) : (
                                  <>
                                    <span className="font-medium text-gray-900">{rate.business_type}</span>
                                    <div className="text-xs text-gray-500">Business type</div>
                                  </>
                                )}
                              </div>
                              <div className="text-right">
                                <span className="font-bold text-blue-600">{rate.tax_percent}%</span>
                                <div className="text-xs text-gray-500">Tax rate</div>
                              </div>
                            </div>
                          </button>
                        ))}
                      </div>
                    </div>
                    
                    {/* Custom Rate Option */}
                    <div>
                      <h4 className="text-sm font-medium text-gray-700 mb-2">Custom Tax Rate</h4>
                      <div className="flex gap-2">
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          max="100"
                          value={customRate}
                          onChange={(e) => setCustomRate(e.target.value)}
                          placeholder="Enter custom rate %"
                          className="flex-1 px-3 py-2 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        />
                        <button
                          onClick={handleCustomRate}
                          className="px-4 py-2 bg-blue-600 text-white font-medium rounded hover:bg-blue-700"
                        >
                          Apply Custom
                        </button>
                      </div>
                    </div>
                  </div>
                )}

                {isCalculating ? (
                  <div className="py-8 text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p className="text-gray-700">Calculating tax...</p>
                  </div>
                ) : calculatedTax ? (
                  <div className="space-y-6">
                    
                    {/* Current Rate Information */}
                    {currentRateInfo && (
                      <div className="bg-blue-50 p-4 rounded border border-blue-200">
                        <div className="flex justify-between items-center">
                          <div>
                            <h3 className="font-medium text-blue-900">Applied Tax Rate</h3>
                            <p className="text-sm text-blue-700">
                              {currentRateInfo.type}: {currentRateInfo.range}
                            </p>
                          </div>
                          <div className="text-right">
                            <div className="text-2xl font-bold text-blue-600">{currentRateInfo.rate}</div>
                          </div>
                        </div>
                      </div>
                    )}

                    {/* Tax Calculation Steps */}
                    <div className="space-y-4">
                      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div className="bg-gray-50 p-4 rounded border border-gray-200">
                          <label className="block text-xs font-medium text-gray-500 mb-1">Base Amount</label>
                          <p className="text-lg font-bold text-gray-900">
                            {formatCurrency(calculatedTax.calculation?.taxable_amount || 0)}
                          </p>
                          <p className="text-xs text-gray-500 mt-1">
                            {permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment' : 'Gross Sales'}
                          </p>
                        </div>
                        <div className="bg-gray-50 p-4 rounded border border-gray-200">
                          <label className="block text-xs font-medium text-gray-500 mb-1">Tax Rate</label>
                          <p className="text-lg font-bold text-blue-600">
                            {calculatedTax.calculation?.tax_rate || 0}%
                          </p>
                          <p className="text-xs text-gray-500 mt-1">Applied rate</p>
                        </div>
                        <div className="bg-gray-50 p-4 rounded border border-gray-200">
                          <label className="block text-xs font-medium text-gray-500 mb-1">Tax Amount</label>
                          <p className="text-lg font-bold text-green-600">
                            {formatCurrency(calculatedTax.calculation?.tax_amount || 0)}
                          </p>
                          <p className="text-xs text-gray-500 mt-1">Base × Rate</p>
                        </div>
                      </div>

                      {/* Regulatory Fees */}
                      <div className="border-t border-gray-200 pt-4">
                        <h3 className="text-sm font-medium text-gray-700 mb-3">Regulatory Fees</h3>
                        <div className="space-y-2">
                          {regulatoryFees.map((fee, index) => (
                            <div key={index} className="flex justify-between">
                              <span className="text-sm text-gray-600">{fee.fee_name}</span>
                              <span className="text-sm font-medium text-gray-900">
                                {formatCurrency(fee.amount)}
                              </span>
                            </div>
                          ))}
                          <div className="pt-2 border-t border-gray-200">
                            <div className="flex justify-between font-medium">
                              <span className="text-gray-700">Total Fees</span>
                              <span className="text-blue-600">
                                {formatCurrency(calculatedTax.calculation?.regulatory_fees || 0)}
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    {/* Annual Business Tax */}
                    <div className="bg-gradient-to-r from-green-50 to-green-100 p-5 rounded-lg border border-green-200">
                      <div className="flex flex-col md:flex-row md:items-center justify-between">
                        <div>
                          <h3 className="text-lg font-bold text-green-900">Annual Business Tax</h3>
                          <p className="text-green-700 text-sm">Payable in quarterly installments</p>
                        </div>
                        <div className="mt-3 md:mt-0 text-right">
                          <div className="text-3xl font-bold text-green-600">
                            {formatCurrency(calculatedTax.calculation?.total_tax || 0)}
                          </div>
                          <div className="text-green-700 text-sm mt-1">
                            Tax: {formatCurrency(calculatedTax.calculation?.tax_amount || 0)} + 
                            Fees: {formatCurrency(calculatedTax.calculation?.regulatory_fees || 0)}
                          </div>
                        </div>
                      </div>
                    </div>

                    {/* Quarterly Payment Schedule */}
                    {quarterlyBreakdown && (
                      <div>
                        <h3 className="font-medium text-gray-900 mb-4">Quarterly Payment Schedule</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                          {quarterlyBreakdown.map((quarter, index) => (
                            <div key={index} className={`p-4 rounded-lg border ${quarter.color}`}>
                              <div className="flex justify-between items-start mb-2">
                                <span className="font-semibold text-gray-900">{quarter.quarter}</span>
                              </div>
                              <div className="space-y-1">
                                <p className="text-xs text-gray-600">{quarter.label}</p>
                                <p className="text-lg font-bold text-gray-900">
                                  {formatCurrency(quarter.quarterly_tax_amount)}
                                </p>
                                <p className="text-xs text-gray-500">
                                  Due: {quarter.due_date_formatted}
                                </p>
                              </div>
                            </div>
                          ))}
                        </div>
                        <div className="mt-4 pt-4 border-t border-gray-200 text-center">
                          <p className="text-sm text-gray-600">
                            Total Annual: <span className="font-bold text-gray-900">
                              {formatCurrency(calculatedTax.calculation?.total_tax || 0)}
                            </span>
                          </p>
                          <p className="text-xs text-gray-500 mt-1">
                            Each quarter: {formatCurrency((calculatedTax.calculation?.total_tax || 0) / 4)}
                          </p>
                        </div>
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="py-8 text-center">
                    <p className="text-gray-600">Tax calculation will begin automatically...</p>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Action Panel */}
          <div className="space-y-6">
            
            {/* Approval Panel */}
            <div className="bg-white rounded-lg border border-gray-200">
              <div className="px-5 py-3 border-b border-gray-200 bg-gray-50">
                <h2 className="font-semibold text-gray-900">Actions</h2>
              </div>
              
              <div className="p-5">
                {/* Approve Button */}
                {!isApproving ? (
                  <button
                    onClick={handleApprove}
                    disabled={!calculatedTax || isCalculating}
                    className="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow-sm hover:shadow transition-all duration-200 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Approve Permit
                  </button>
                ) : (
                  <div className="py-3 text-center">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600 mx-auto mb-3"></div>
                    <p className="text-sm text-gray-700">Approving...</p>
                  </div>
                )}

                {/* Status Information */}
                <div className="mt-4 p-3 bg-blue-50 rounded border border-blue-200">
                  <div className="flex items-start">
                    <svg className="w-4 h-4 text-blue-600 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div className="text-xs text-blue-700">
                      Approving will save the tax calculation and generate quarterly payment records.
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Application Summary */}
            <div className="bg-white rounded-lg border border-gray-200">
              <div className="px-5 py-3 border-b border-gray-200 bg-gray-50">
                <h2 className="font-semibold text-gray-900">Summary</h2>
              </div>
              <div className="p-5">
                <div className="space-y-3">
                  <div className="flex justify-between">
                    <span className="text-sm text-gray-600">Status</span>
                    <span className={`text-sm font-medium px-2 py-1 rounded ${
                      permit.status === 'Pending' ? 'bg-yellow-100 text-yellow-800' :
                      permit.status === 'Approved' ? 'bg-green-100 text-green-800' :
                      permit.status === 'Active' ? 'bg-blue-100 text-blue-800' :
                      'bg-gray-100 text-gray-800'
                    }`}>
                      {permit.status}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-sm text-gray-600">Tax Type</span>
                    <span className="text-sm font-medium text-gray-900">
                      {permit.tax_calculation_type === 'capital_investment' ? 'Capital' : 'Gross Sales'}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-sm text-gray-600">Base Amount</span>
                    <span className="text-sm font-semibold text-gray-900">
                      {formatCurrency(permit.taxable_amount)}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-sm text-gray-600">Issue Date</span>
                    <span className="text-sm text-gray-900">{formatDate(permit.issue_date)}</span>
                  </div>
                  {calculatedTax && (
                    <>
                      <div className="pt-3 border-t border-gray-200">
                        <div className="flex justify-between">
                          <span className="text-sm font-medium text-gray-700">Calculated Tax</span>
                          <span className="text-sm font-bold text-green-600">
                            {formatCurrency(calculatedTax.calculation?.total_tax || 0)}
                          </span>
                        </div>
                      </div>
                    </>
                  )}
                </div>
              </div>
            </div>

            {/* Tax Rate Info */}
            {currentRateInfo && (
              <div className="bg-white rounded-lg border border-gray-200">
                <div className="px-5 py-3 border-b border-gray-200 bg-gray-50">
                  <h2 className="font-semibold text-gray-900">Tax Rate Info</h2>
                </div>
                <div className="p-5">
                  <div className="space-y-3">
                    <div className="flex justify-between">
                      <span className="text-sm text-gray-600">Rate Type</span>
                      <span className="text-sm font-medium text-gray-900">{currentRateInfo.type}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-sm text-gray-600">Applied To</span>
                      <span className="text-sm font-medium text-gray-900">{currentRateInfo.range}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-sm text-gray-600">Tax Rate</span>
                      <span className="text-sm font-bold text-blue-600">{currentRateInfo.rate}</span>
                    </div>
                    {permit.tax_calculation_type === 'capital_investment' && (
                      <div className="pt-2 border-t border-gray-200">
                        <div className="text-xs text-gray-500">
                          Capital amount: {formatCurrency(permit.taxable_amount)}
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Footer Information */}
        <div className="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
          <div className="flex items-start">
            <svg className="w-5 h-5 text-gray-600 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div className="text-sm text-gray-600">
              <strong>LGU Business Tax System</strong> - Tax rates are loaded from the database. 
              You can select from available rates or enter a custom rate if needed.
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default BusinessValidationInfo;