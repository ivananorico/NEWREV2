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
  
  const [taxRates, setTaxRates] = useState([]);
  const [selectedRate, setSelectedRate] = useState(null);
  const [customRate, setCustomRate] = useState('');
  const [showRateOptions, setShowRateOptions] = useState(false);
  const [regulatoryFees, setRegulatoryFees] = useState([]);

  const API_BASE = window.location.hostname === "localhost"
    ? "http://localhost/revenue2/backend"
    : "https://revenuetreasury.goserveph.com/backend";

  useEffect(() => {
    const loadData = async () => {
      try {
        setLoading(true);
        setError('');
        
        if (!id) {
          navigate('/business/validation');
          return;
        }
        
        // Use the updated API_BASE
        const permitUrl = `${API_BASE}/Business/BusinessValidation/get_permit_details.php?id=${id}`;
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
        
        await loadTaxRates(permitData.permit);
        
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
          
          const quarterlyData = calculateQuarterlyBreakdown(
            permitData.permit.total_tax, 
            permitData.permit.issue_date
          );
          setQuarterlyBreakdown(quarterlyData);
          
          if (permitData.permit.tax_calculation_type === 'capital_investment') {
            const matchingRate = taxRates.find(rate => 
              permitData.permit.taxable_amount >= parseFloat(rate.min_amount) && 
              permitData.permit.taxable_amount <= parseFloat(rate.max_amount)
            );
            if (matchingRate) {
              setSelectedRate(matchingRate.id);
            }
          } else {
            const matchingRate = taxRates.find(rate => 
              rate.business_type === permitData.permit.business_type
            );
            if (matchingRate) {
              setSelectedRate(matchingRate.id);
            }
          }
        } else {
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

  const loadTaxRates = async (permitData) => {
    try {
      let ratesUrl;
      if (permitData.tax_calculation_type === 'capital_investment') {
        ratesUrl = `${API_BASE}/Business/BusinessValidation/get_capital_config.php`;
      } else {
        ratesUrl = `${API_BASE}/Business/BusinessValidation/get_gross_sale_confog.php`;
      }
      
      const response = await fetch(ratesUrl);
      const data = await response.json();
      
      if (data.status === 'success') {
        setTaxRates(data.data || []);
      }
    } catch (err) {
      console.error('Error loading tax rates:', err);
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

  const calculateQuarterlyBreakdown = (totalTax, issueDate) => {
    const quarterlyAmount = (totalTax / 4).toFixed(2);
    const issueDateObj = issueDate ? new Date(issueDate) : new Date();
    const currentYear = issueDateObj.getFullYear();
    
    const quarters = [
      { 
        quarter: 'Q1', 
        due_date: new Date(currentYear, 2, 31),
        label: 'January - March',
        color: 'border-l-4 border-blue-500'
      },
      { 
        quarter: 'Q2', 
        due_date: new Date(currentYear, 5, 30),
        label: 'April - June',
        color: 'border-l-4 border-green-500'
      },
      { 
        quarter: 'Q3', 
        due_date: new Date(currentYear, 8, 30),
        label: 'July - September',
        color: 'border-l-4 border-yellow-500'
      },
      { 
        quarter: 'Q4', 
        due_date: new Date(currentYear, 11, 31),
        label: 'October - December',
        color: 'border-l-4 border-purple-500'
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

  const calculateTax = async (permitData, rateId = null, customRateValue = null) => {
    try {
      setIsCalculating(true);
      
      if (!permitData || !permitData.id) {
        alert('Permit data not available');
        return;
      }
      
      let url = `${API_BASE}/Business/BusinessValidation/calculate_tax.php?`;
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
        
        const totalTax = data.calculation?.total_tax || 0;
        const quarterlyData = calculateQuarterlyBreakdown(totalTax, permitData.issue_date);
        setQuarterlyBreakdown(quarterlyData);
        
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

  const handleApprove = async () => {
    if (!window.confirm('Approve this business permit with the calculated tax?')) return;
    
    if (!calculatedTax || !permit) {
      alert('Tax calculation is not complete');
      return;
    }
    
    setIsApproving(true);
    
    try {
      const updateUrl = `${API_BASE}/Business/BusinessValidation/update_permit_status.php`;
      
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
      
      try {
        const quarterlyUrl = `${API_BASE}/Business/BusinessValidation/generate_quarterly_taxes.php`;
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

  const formatCurrency = (amount) => {
    if (!amount && amount !== 0) return '₱0.00';
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);
  };

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

  const getCurrentRateInfo = () => {
    if (!permit || !calculatedTax) return null;
    
    if (permit.tax_calculation_type === 'capital_investment') {
      const rate = taxRates.find(r => r.id === selectedRate);
      if (rate) {
        return {
          type: 'Capital Investment Bracket',
          range: `₱${parseFloat(rate.min_amount).toLocaleString()} - ₱${parseFloat(rate.max_amount).toLocaleString()}`,
          rate: `${rate.tax_percent}%`,
          description: `Applicable for capital investments within this range`
        };
      }
    } else {
      const rate = taxRates.find(r => r.id === selectedRate);
      if (rate) {
        return {
          type: 'Business Type Rate',
          range: rate.business_type,
          rate: `${rate.tax_percent}%`,
          description: `Standard rate for ${rate.business_type.toLowerCase()} businesses`
        };
      }
    }
    
    if (selectedRate === 'custom') {
      return {
        type: 'Custom Rate',
        range: 'Manual adjustment',
        rate: `${calculatedTax.calculation?.tax_rate}%`,
        description: 'Manually adjusted tax rate'
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
      <div className="container mx-auto px-4 py-8">
        
        {/* Header */}
        <div className="mb-8">
          <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Business Permit Validation</h1>
              <p className="text-gray-600 mt-1">Review and validate business permit application</p>
            </div>
            <div className="flex items-center space-x-3">
              <div className="text-sm text-gray-500 bg-white border border-gray-300 px-4 py-2 rounded-lg">
                Permit ID: <span className="font-semibold text-blue-600 ml-1">{permit.business_permit_id}</span>
              </div>
              <Link 
                to="/business/validation" 
                className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50"
              >
                ← Back to List
              </Link>
            </div>
          </div>

          {/* Status Badge */}
          <div className="flex items-center justify-between mb-8">
            <div>
              <span className={`inline-flex items-center px-4 py-2 rounded-full text-sm font-medium ${
                permit.status === 'Pending' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
                permit.status === 'Approved' ? 'bg-green-100 text-green-800 border border-green-200' :
                permit.status === 'Active' ? 'bg-blue-100 text-blue-800 border border-blue-200' :
                'bg-gray-100 text-gray-800 border border-gray-200'
              }`}>
                Status: {permit.status}
              </span>
            </div>
            
            <div className="text-right">
              <div className="text-sm text-gray-600">Application Date</div>
              <div className="font-medium text-gray-900">{formatDate(permit.created_at)}</div>
            </div>
          </div>
        </div>

        {/* Main Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          
          {/* Left Column - Application Details */}
          <div className="lg:col-span-2 space-y-8">
            
            {/* Business Information */}
            <div className="bg-white rounded-lg border border-gray-200">
              <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 className="text-lg font-semibold text-gray-900">Business Information</h2>
              </div>
              <div className="p-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                  
                  {/* Business Details */}
                  <div className="space-y-6">
                    <div>
                      <h3 className="text-sm font-medium text-gray-700 mb-4">Business Details</h3>
                      <div className="space-y-4">
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Business Name</label>
                          <p className="text-base font-semibold text-gray-900">{permit.business_name}</p>
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Business Type</label>
                          <div className="flex flex-wrap gap-2 mt-2">
                            <span className="px-3 py-1 text-xs font-medium rounded bg-gray-100 text-gray-800">
                              {permit.business_type}
                            </span>
                            <span className="px-3 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800">
                              {permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment' : 'Gross Sales'}
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div>
                      <h3 className="text-sm font-medium text-gray-700 mb-4">Tax Information</h3>
                      <div className="space-y-3">
                        <div className="flex justify-between items-center">
                          <span className="text-sm text-gray-600">Tax Base Amount</span>
                          <span className="text-lg font-bold text-gray-900">
                            {formatCurrency(permit.taxable_amount)}
                          </span>
                        </div>
                        <div className="flex justify-between items-center">
                          <span className="text-sm text-gray-600">Tax Type</span>
                          <span className="text-sm font-medium text-gray-900">
                            {permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment' : 'Gross Sales'}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Business Location & Owner */}
                  <div className="space-y-6">
                    <div>
                      <h3 className="text-sm font-medium text-gray-700 mb-4">Business Location</h3>
                      <div className="space-y-2">
                        <p className="text-sm text-gray-900">
                          {permit.business_street && <>{permit.business_street}, </>}
                          {permit.business_barangay}
                        </p>
                        <p className="text-sm text-gray-900">
                          {permit.business_city}, {permit.business_province}
                        </p>
                        {permit.business_zipcode && (
                          <p className="text-sm text-gray-900">ZIP: {permit.business_zipcode}</p>
                        )}
                      </div>
                    </div>

                    <div>
                      <h3 className="text-sm font-medium text-gray-700 mb-4">Owner Information</h3>
                      <div className="space-y-2">
                        <p className="text-sm font-medium text-gray-900">{permit.full_name}</p>
                        {permit.personal_contact && (
                          <p className="text-sm text-gray-700">
                            <span className="text-gray-500">Contact: </span>
                            {permit.personal_contact}
                          </p>
                        )}
                        {permit.personal_email && (
                          <p className="text-sm text-gray-700 truncate">
                            <span className="text-gray-500">Email: </span>
                            {permit.personal_email}
                          </p>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Tax Calculation Section */}
            <div className="bg-white rounded-lg border border-gray-200">
              <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div className="flex items-center justify-between">
                  <h2 className="text-lg font-semibold text-gray-900">Tax Calculation</h2>
                  {calculatedTax && (
                    <button
                      onClick={() => setShowRateOptions(!showRateOptions)}
                      className="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-800"
                    >
                      {showRateOptions ? 'Cancel Adjustment' : 'Adjust Tax Rate'}
                    </button>
                  )}
                </div>
              </div>
              
              <div className="p-6">
                {/* Tax Rate Selection Panel */}
                {showRateOptions && (
                  <div className="mb-8 p-5 bg-blue-50 rounded-lg border border-blue-200">
                    <h3 className="font-medium text-gray-900 mb-4">Select Tax Rate</h3>
                    
                    {/* Available Tax Rates */}
                    <div className="mb-6">
                      <h4 className="text-sm font-medium text-gray-700 mb-3">
                        Available {permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment Brackets' : 'Business Type Rates'}
                      </h4>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        {taxRates.map((rate) => (
                          <button
                            key={rate.id}
                            onClick={() => calculateTax(permit, rate.id)}
                            className={`p-4 rounded border text-left transition-all ${
                              selectedRate === rate.id
                                ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-100'
                                : 'border-gray-200 bg-white hover:bg-gray-50 hover:border-gray-300'
                            }`}
                          >
                            <div className="flex justify-between items-start">
                              <div>
                                {permit.tax_calculation_type === 'capital_investment' ? (
                                  <div>
                                    <div className="font-medium text-gray-900">Bracket {rate.id}</div>
                                    <div className="text-xs text-gray-500 mt-1">
                                      ₱{parseFloat(rate.min_amount).toLocaleString()} - ₱{parseFloat(rate.max_amount).toLocaleString()}
                                    </div>
                                  </div>
                                ) : (
                                  <div>
                                    <div className="font-medium text-gray-900">{rate.business_type}</div>
                                    <div className="text-xs text-gray-500 mt-1">Standard rate</div>
                                  </div>
                                )}
                              </div>
                              <div className="text-right">
                                <div className="text-lg font-bold text-blue-600">{rate.tax_percent}%</div>
                              </div>
                            </div>
                          </button>
                        ))}
                      </div>
                    </div>
                    
                    {/* Custom Rate Option */}
                    <div className="pt-5 border-t border-blue-200">
                      <h4 className="text-sm font-medium text-gray-700 mb-3">Custom Tax Rate</h4>
                      <div className="flex gap-3">
                        <div className="flex-1">
                          <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            value={customRate}
                            onChange={(e) => setCustomRate(e.target.value)}
                            placeholder="Enter custom rate %"
                            className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          />
                        </div>
                        <button
                          onClick={handleCustomRate}
                          className="px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700"
                        >
                          Apply Custom Rate
                        </button>
                      </div>
                    </div>
                  </div>
                )}

                {isCalculating ? (
                  <div className="py-12 text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p className="text-gray-700">Calculating tax...</p>
                  </div>
                ) : calculatedTax ? (
                  <div className="space-y-8">
                    
                    {/* Current Rate Information */}
                    {currentRateInfo && (
                      <div className="bg-blue-50 p-5 rounded-lg border border-blue-200">
                        <div className="flex items-center justify-between">
                          <div>
                            <h3 className="font-medium text-blue-900">Applied Tax Rate</h3>
                            <p className="text-blue-700 text-sm mt-1">{currentRateInfo.description}</p>
                          </div>
                          <div className="text-right">
                            <div className="text-2xl font-bold text-blue-600">{currentRateInfo.rate}</div>
                            <div className="text-sm text-blue-700">{currentRateInfo.type}</div>
                          </div>
                        </div>
                      </div>
                    )}

                    {/* Tax Calculation Steps */}
                    <div className="space-y-6">
                      {/* Base Calculation */}
                      <div>
                        <h3 className="text-sm font-medium text-gray-700 mb-4">Tax Calculation</h3>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                          <div className="bg-gray-50 p-4 rounded border border-gray-200">
                            <div className="text-xs font-medium text-gray-500 mb-1">Taxable Base</div>
                            <div className="text-xl font-bold text-gray-900">
                              {formatCurrency(calculatedTax.calculation?.taxable_amount || 0)}
                            </div>
                            <div className="text-xs text-gray-500 mt-1">
                              {permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment' : 'Gross Sales'}
                            </div>
                          </div>
                          
                          <div className="bg-blue-50 p-4 rounded border border-blue-200">
                            <div className="text-xs font-medium text-blue-500 mb-1">Tax Rate</div>
                            <div className="text-xl font-bold text-blue-600">
                              {calculatedTax.calculation?.tax_rate || 0}%
                            </div>
                            <div className="text-xs text-blue-500 mt-1">Applied Rate</div>
                          </div>
                          
                          <div className="bg-green-50 p-4 rounded border border-green-200">
                            <div className="text-xs font-medium text-green-500 mb-1">Tax Amount</div>
                            <div className="text-xl font-bold text-green-600">
                              {formatCurrency(calculatedTax.calculation?.tax_amount || 0)}
                            </div>
                            <div className="text-xs text-green-500 mt-1">Base × Rate</div>
                          </div>
                        </div>
                      </div>

                      {/* Regulatory Fees */}
                      <div className="border-t border-gray-200 pt-6">
                        <h3 className="text-sm font-medium text-gray-700 mb-4">Regulatory Fees</h3>
                        <div className="bg-gray-50 p-4 rounded border border-gray-200">
                          <div className="space-y-3">
                            {regulatoryFees.map((fee, index) => (
                              <div key={index} className="flex justify-between items-center py-2">
                                <div>
                                  <span className="text-sm text-gray-700">{fee.fee_name}</span>
                                  {fee.remarks && (
                                    <div className="text-xs text-gray-500 mt-1">{fee.remarks}</div>
                                  )}
                                </div>
                                <span className="text-sm font-medium text-gray-900">
                                  {formatCurrency(fee.amount)}
                                </span>
                              </div>
                            ))}
                            <div className="pt-3 border-t border-gray-300">
                              <div className="flex justify-between font-medium">
                                <span className="text-gray-700">Total Regulatory Fees</span>
                                <span className="text-blue-600">
                                  {formatCurrency(calculatedTax.calculation?.regulatory_fees || 0)}
                                </span>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      {/* Total Annual Tax */}
                      <div className="bg-gradient-to-r from-green-50 to-green-100 p-6 rounded-lg border border-green-200">
                        <div className="flex flex-col md:flex-row md:items-center justify-between">
                          <div>
                            <h3 className="text-lg font-bold text-green-900">Total Annual Business Tax</h3>
                            <p className="text-green-700 mt-1">Payable quarterly as per schedule</p>
                          </div>
                          <div className="mt-4 md:mt-0 text-right">
                            <div className="text-4xl font-bold text-green-600">
                              {formatCurrency(calculatedTax.calculation?.total_tax || 0)}
                            </div>
                            <div className="text-sm text-green-700 mt-2">
                              Tax: {formatCurrency(calculatedTax.calculation?.tax_amount || 0)} + 
                              Fees: {formatCurrency(calculatedTax.calculation?.regulatory_fees || 0)}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    {/* Quarterly Payment Schedule */}
                    {quarterlyBreakdown && (
                      <div className="border-t border-gray-200 pt-8">
                        <h3 className="text-lg font-semibold text-gray-900 mb-6">Quarterly Payment Schedule</h3>
                        
                        <div className="mb-6">
                          <div className="bg-gray-50 p-4 rounded border border-gray-200">
                            <div className="flex items-center justify-between">
                              <div>
                                <h4 className="font-medium text-gray-900">Annual Tax Breakdown</h4>
                                <p className="text-sm text-gray-600 mt-1">Total divided into four equal installments</p>
                              </div>
                              <div className="text-right">
                                <div className="text-2xl font-bold text-gray-900">
                                  {formatCurrency(calculatedTax?.calculation?.total_tax || 0)}
                                </div>
                                <div className="text-sm text-gray-600">Total Annual Tax</div>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div className="space-y-4">
                          {quarterlyBreakdown.map((quarter, index) => (
                            <div key={index} className={`p-5 rounded-lg bg-white border border-gray-200 ${quarter.color}`}>
                              <div className="flex flex-col md:flex-row md:items-center justify-between">
                                <div className="mb-4 md:mb-0">
                                  <div className="flex items-center space-x-4">
                                    <div className="flex items-center justify-center w-12 h-12 rounded-lg bg-gray-100">
                                      <span className="text-lg font-bold text-gray-700">{quarter.quarter}</span>
                                    </div>
                                    <div>
                                      <h4 className="font-medium text-gray-900">{quarter.label}</h4>
                                      <p className="text-sm text-gray-600">Due: {quarter.due_date_formatted}</p>
                                    </div>
                                  </div>
                                </div>
                                <div className="text-right">
                                  <div className="text-2xl font-bold text-gray-900">
                                    {formatCurrency(quarter.quarterly_tax_amount)}
                                  </div>
                                  <p className="text-sm text-gray-600">Quarterly installment</p>
                                </div>
                              </div>
                            </div>
                          ))}
                        </div>

                        <div className="mt-6 pt-6 border-t border-gray-200">
                          <div className="bg-blue-50 p-4 rounded border border-blue-200">
                            <div className="text-center">
                              <p className="text-sm text-blue-700">
                                Each quarter's payment is due on or before the specified due date. Late payments may incur penalties.
                              </p>
                            </div>
                          </div>
                        </div>
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="py-12 text-center">
                    <p className="text-gray-600">Tax calculation will begin automatically...</p>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Right Column - Action Panel & Summary */}
          <div className="space-y-6">
            {/* Action Panel */}
            <div className="bg-white rounded-lg border border-gray-200">
              <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 className="text-lg font-semibold text-gray-900">Actions</h2>
              </div>
              <div className="p-6">
                {isCalculating ? (
                  <div className="text-center py-4">
                    <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p className="text-gray-700">Calculating tax...</p>
                  </div>
                ) : calculatedTax ? (
                  <div className="space-y-4">
                    <button
                      onClick={handleApprove}
                      disabled={isApproving}
                      className="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow-sm transition-all duration-200 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {isApproving ? (
                        <>
                          <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-3"></div>
                          Approving...
                        </>
                      ) : (
                        <>
                          <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                          Approve Permit
                        </>
                      )}
                    </button>
                    
                    <div className="bg-blue-50 p-4 rounded border border-blue-200">
                      <div className="flex items-start">
                        <svg className="w-5 h-5 text-blue-600 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div className="text-sm text-blue-700">
                          Approving will save the tax calculation and generate quarterly payment records.
                        </div>
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="text-center py-4">
                    <p className="text-gray-600">Please wait for tax calculation...</p>
                  </div>
                )}
              </div>
            </div>

            {/* Summary Card */}
            <div className="bg-white rounded-lg border border-gray-200">
              <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 className="text-lg font-semibold text-gray-900">Quick Summary</h2>
              </div>
              <div className="p-6">
                <div className="space-y-4">
                  <div>
                    <div className="flex justify-between mb-2">
                      <span className="text-sm text-gray-600">Business Type</span>
                      <span className="text-sm font-medium text-gray-900">{permit.business_type}</span>
                    </div>
                    <div className="flex justify-between mb-2">
                      <span className="text-sm text-gray-600">Tax Type</span>
                      <span className="text-sm font-medium text-gray-900">
                        {permit.tax_calculation_type === 'capital_investment' ? 'Capital' : 'Gross Sales'}
                      </span>
                    </div>
                    <div className="flex justify-between mb-2">
                      <span className="text-sm text-gray-600">Tax Base</span>
                      <span className="text-sm font-bold text-gray-900">
                        {formatCurrency(permit.taxable_amount)}
                      </span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-sm text-gray-600">Application Date</span>
                      <span className="text-sm text-gray-900">{formatDate(permit.created_at)}</span>
                    </div>
                  </div>

                  {currentRateInfo && (
                    <div className="pt-4 border-t border-gray-200">
                      <h3 className="text-sm font-medium text-gray-700 mb-3">Applied Tax Rate</h3>
                      <div className="space-y-2">
                        <div className="flex justify-between">
                          <span className="text-sm text-gray-600">Type</span>
                          <span className="text-sm font-medium text-gray-900">{currentRateInfo.type}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-sm text-gray-600">Rate</span>
                          <span className="text-sm font-bold text-blue-600">{currentRateInfo.rate}</span>
                        </div>
                      </div>
                    </div>
                  )}

                  {calculatedTax && (
                    <div className="pt-4 border-t border-gray-200">
                      <div className="bg-gray-50 p-4 rounded">
                        <div className="text-center">
                          <div className="text-xs font-medium text-gray-500 mb-1">Total Annual Tax</div>
                          <div className="text-2xl font-bold text-green-600">
                            {formatCurrency(calculatedTax.calculation?.total_tax || 0)}
                          </div>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Information Card */}
            <div className="bg-white rounded-lg border border-gray-200">
              <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 className="text-lg font-semibold text-gray-900">System Information</h2>
              </div>
              <div className="p-6">
                <div className="space-y-4">
                  <div className="flex items-start">
                    <svg className="w-5 h-5 text-gray-600 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div className="text-sm text-gray-600">
                      <strong>Tax rates are loaded from the database.</strong> You can select from available rates or enter a custom rate.
                    </div>
                  </div>
                  <div className="flex items-start">
                    <svg className="w-5 h-5 text-gray-600 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div className="text-sm text-gray-600">
                      Approving will automatically generate quarterly payment schedules.
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default BusinessValidationInfo;