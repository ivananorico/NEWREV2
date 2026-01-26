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
  const [taxConfig, setTaxConfig] = useState(null);
  const [discountConfig, setDiscountConfig] = useState(null);
  const [penaltyConfig, setPenaltyConfig] = useState(null);

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
        
        const permitUrl = `${API_BASE}/Business/BusinessValidation/get_permit_details.php?id=${id}`;
        console.log('Fetching permit from:', permitUrl);
        const permitRes = await fetch(permitUrl);
        const responseText = await permitRes.text();
        console.log('Raw response:', responseText.substring(0, 500));
        
        if (responseText.includes('<br />') || responseText.includes('<b>') || responseText.trim().startsWith('<')) {
          throw new Error('Server error. Please check PHP configuration.');
        }
        
        let permitData;
        try {
          permitData = JSON.parse(responseText);
        } catch (parseError) {
          console.error('JSON Parse Error:', parseError);
          throw new Error('Invalid server response: ' + responseText.substring(0, 200));
        }
        
        if (permitData.status !== 'success') {
          throw new Error(permitData.message || 'Failed to load permit');
        }
        
        if (!permitData.permit) {
          throw new Error('Permit data not found');
        }
        
        console.log('Permit data loaded:', permitData.permit);
        setPermit(permitData.permit);
        setRegulatoryFees(permitData.regulatory_fees || []);
        setTaxConfig(permitData.tax_config || null);
        setDiscountConfig(permitData.discount_config || null);
        setPenaltyConfig(permitData.penalty_config || null);
        
        await loadTaxRates(permitData.permit);
        
        // Initialize calculated tax from permit data
        const permitTaxAmount = parseFloat(permitData.permit.tax_amount) || 0;
        const permitTotalTax = parseFloat(permitData.permit.total_tax) || 0;
        
        if (permitTaxAmount > 0 || permitTotalTax > 0 || permitData.calculated_tax_amount > 0) {
          const taxAmount = permitTaxAmount || permitData.calculated_tax_amount || 0;
          const totalTax = permitTotalTax || permitData.calculated_total_tax || 0;
          const taxRate = parseFloat(permitData.permit.tax_rate) || 
                         (permitData.tax_config ? parseFloat(permitData.tax_config.tax_percent) : 
                          (permitData.permit.tax_calculation_type === 'capital_investment' ? 25 : 2));
          
          const existingTax = {
            status: 'success',
            calculation: {
              taxable_amount: permitData.permit.taxable_amount || permitData.permit.capital_investment || 0,
              tax_rate: taxRate,
              tax_amount: taxAmount,
              regulatory_fees: permitData.total_regulatory_fees || 0,
              total_tax: totalTax
            }
          };
          setCalculatedTax(existingTax);
          
          const quarterlyData = calculateQuarterlyBreakdown(
            totalTax, 
            permitData.permit.issue_date || permitData.permit.application_date
          );
          setQuarterlyBreakdown(quarterlyData);
          
          // Set selected rate based on tax config
          if (permitData.tax_config) {
            if (permitData.permit.tax_calculation_type === 'capital_investment') {
              const matchingRate = taxRates.find(rate => 
                permitData.permit.taxable_amount >= parseFloat(rate.min_amount) && 
                permitData.permit.taxable_amount <= parseFloat(rate.max_amount)
              );
              if (matchingRate) {
                setSelectedRate(matchingRate.id);
              }
            } else {
              // For gross sales, we need to find matching business type
              // Since business_permits has business_nature, not business_type
              const matchingRate = taxRates.find(rate => 
                rate.tax_percent === permitData.tax_config.tax_percent.toString()
              );
              if (matchingRate) {
                setSelectedRate(matchingRate.id);
              }
            }
          }
        } else {
          // No tax calculated yet, calculate now
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
        ratesUrl = `${API_BASE}/Business/BusinessValidation/get_gross_sales_config.php`; // Fixed typo
      }
      
      console.log('Loading tax rates from:', ratesUrl);
      const response = await fetch(ratesUrl);
      const responseText = await response.text();
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (parseError) {
        console.error('Failed to parse tax rates:', responseText);
        throw new Error('Invalid tax rates response');
      }
      
      if (data.status === 'success') {
        setTaxRates(data.data || []);
      } else {
        console.warn('Tax rates API returned:', data);
        // Fallback to default rates
        throw new Error(data.message || 'Failed to load tax rates');
      }
    } catch (err) {
      console.error('Error loading tax rates, using defaults:', err);
      // Provide default tax rates based on database structure
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
    if (!totalTax || totalTax <= 0) {
      totalTax = 1000; // Default for preview
    }
    
    const quarterlyAmount = (totalTax / 4).toFixed(2);
    const issueDateObj = issueDate ? new Date(issueDate) : new Date();
    const currentYear = issueDateObj.getFullYear();
    
    const quarters = [
      { 
        quarter: 'Q1', 
        due_date: new Date(currentYear, 2, 31), // March 31
        label: 'January - March',
        color: 'bg-blue-100 text-blue-800'
      },
      { 
        quarter: 'Q2', 
        due_date: new Date(currentYear, 5, 30), // June 30
        label: 'April - June',
        color: 'bg-green-100 text-green-800'
      },
      { 
        quarter: 'Q3', 
        due_date: new Date(currentYear, 8, 30), // September 30
        label: 'July - September',
        color: 'bg-yellow-100 text-yellow-800'
      },
      { 
        quarter: 'Q4', 
        due_date: new Date(currentYear, 11, 31), // December 31
        label: 'October - December',
        color: 'bg-purple-100 text-purple-800'
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
      url += `tax_type=${encodeURIComponent(permitData.tax_calculation_type || 'capital_investment')}`;
      url += `&taxable_amount=${encodeURIComponent(permitData.taxable_amount || permitData.capital_investment || 0)}`;
      url += `&business_nature=${encodeURIComponent(permitData.business_nature || 'Retailer')}`; // Using business_nature
      url += `&permit_id=${encodeURIComponent(permitData.id)}`;
      
      if (rateId) {
        url += `&selected_config_id=${rateId}`;
      } else if (customRateValue !== null) {
        url += `&override_tax_rate=${customRateValue}`;
      }
      
      console.log('Calculating tax with URL:', url);
      const response = await fetch(url);
      const text = await response.text();
      console.log('Tax calculation response:', text.substring(0, 500));
      
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        console.error('Failed to parse tax calculation:', text);
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
        
        // Update permit with new tax values
        if (data.calculation) {
          setPermit(prev => ({
            ...prev,
            tax_amount: data.calculation.tax_amount,
            total_tax: data.calculation.total_tax,
            tax_rate: data.calculation.tax_rate
          }));
        }
      } else {
        throw new Error(data.message || 'Calculation failed');
      }
    } catch (err) {
      console.error('Tax calculation error:', err);
      alert('Tax calculation failed: ' + err.message + '. Using fallback calculation.');
      
      // Fallback calculation
      const taxableAmount = parseFloat(permitData.taxable_amount || permitData.capital_investment || 0);
      const taxRate = customRateValue || (permitData.tax_calculation_type === 'capital_investment' ? 25 : 2);
      const taxAmount = (taxableAmount * parseFloat(taxRate)) / 100;
      const regulatoryFees = 499.98 + 500 + 300; // Default fees
      const totalTax = taxAmount + regulatoryFees;
      
      const simpleTax = {
        status: 'success',
        calculation: {
          taxable_amount: taxableAmount,
          tax_rate: parseFloat(taxRate),
          tax_amount: taxAmount,
          regulatory_fees: regulatoryFees,
          total_tax: totalTax
        }
      };
      
      setCalculatedTax(simpleTax);
      const quarterlyData = calculateQuarterlyBreakdown(totalTax, permitData.issue_date);
      setQuarterlyBreakdown(quarterlyData);
      
      // Update permit with fallback values
      setPermit(prev => ({
        ...prev,
        tax_amount: taxAmount,
        total_tax: totalTax,
        tax_rate: parseFloat(taxRate)
      }));
    } finally {
      setIsCalculating(false);
    }
  };

  const handleCustomRate = () => {
    if (!permit) {
      alert('No permit data available');
      return;
    }
    
    if (!customRate || isNaN(customRate) || parseFloat(customRate) <= 0) {
      alert('Please enter a valid tax rate (greater than 0)');
      return;
    }
    
    setSelectedRate('custom');
    setShowRateOptions(false);
    calculateTax(permit, null, parseFloat(customRate));
  };

  const handleApprove = async () => {
    if (!window.confirm('Are you sure you want to approve this business permit with the calculated tax?')) return;
    
    if (!calculatedTax || !permit) {
      alert('Tax calculation is not complete');
      return;
    }
    
    setIsApproving(true);
    
    try {
      const updateUrl = `${API_BASE}/Business/BusinessValidation/update_permit_status.php`;
      
      const updateData = {
        id: id,
        status: 'APPROVED',
        action_by: 'admin',
        remarks: 'Permit approved via system validation',
        tax_amount: calculatedTax.calculation?.tax_amount || 0,
        tax_rate: calculatedTax.calculation?.tax_rate || 0,
        regulatory_fees: calculatedTax.calculation?.regulatory_fees || 0,
        total_tax: calculatedTax.calculation?.total_tax || 0,
        approved_date: new Date().toISOString().split('T')[0],
        tax_status: 'Approved',
        permit_status: 'APPROVED'
      };
      
      console.log('Sending approval data:', updateData);
      const updateResponse = await fetch(updateUrl, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(updateData)
      });
      
      const updateText = await updateResponse.text();
      console.log('Approval response:', updateText);
      
      let updateResult;
      try {
        updateResult = JSON.parse(updateText);
      } catch (parseError) {
        throw new Error('Invalid server response: ' + updateText.substring(0, 200));
      }
      
      if (updateResult.status !== 'success') {
        throw new Error(updateResult.message || 'Approval failed');
      }
      
      // Try to generate quarterly taxes
      try {
        const quarterlyUrl = `${API_BASE}/Business/BusinessValidation/generate_quarterly_taxes.php`;
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
            remarks: 'Quarterly taxes generated on approval'
          })
        });
        
        const quarterlyText = await quarterlyResponse.text();
        console.log('Quarterly generation response:', quarterlyText);
      } catch (quarterlyError) {
        console.warn('Quarterly generation skipped or failed:', quarterlyError);
        // Don't fail the whole approval if quarterly generation fails
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
    if (amount === null || amount === undefined || isNaN(amount)) return '₱0.00';
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
    
    // If no specific rate selected, show from tax config
    if (taxConfig) {
      return {
        type: permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment Bracket' : 'Business Type Rate',
        range: permit.tax_calculation_type === 'capital_investment' 
          ? `₱${parseFloat(taxConfig.min_amount || 0).toLocaleString()} - ₱${parseFloat(taxConfig.max_amount || 0).toLocaleString()}`
          : (taxConfig.business_type || 'Standard'),
        rate: `${taxConfig.tax_percent}%`,
        description: 'Auto-selected based on configuration'
      };
    }
    
    return null;
  };

  // Function to get selected bracket details
  const getSelectedBracket = () => {
    if (permit?.tax_calculation_type === 'capital_investment' && selectedRate) {
      return taxRates.find(rate => rate.id === selectedRate);
    }
    return null;
  };

  // Get business type for display (since database has business_nature, not business_type)
  const getBusinessTypeDisplay = () => {
    if (!permit) return '';
    
    // Try to map business_nature to a business type
    const businessNature = permit.business_nature || '';
    if (businessNature.toLowerCase().includes('retail')) return 'Retailer';
    if (businessNature.toLowerCase().includes('whole')) return 'Wholesaler';
    if (businessNature.toLowerCase().includes('manufact') || businessNature.toLowerCase().includes('factory')) return 'Manufacturer';
    if (businessNature.toLowerCase().includes('service') || businessNature.toLowerCase().includes('consult')) return 'Service';
    
    return businessNature || 'General Business';
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading Business Permit...</p>
        </div>
      </div>
    );
  }

  if (error || !permit) {
    return (
      <div className="min-h-screen bg-gray-50 py-6">
        <div className="max-w-7xl mx-auto px-4">
          <div className="bg-red-50 border border-red-200 rounded-xl p-8 max-w-2xl mx-auto">
            <div className="flex items-center mb-6">
              <svg className="h-10 w-10 text-red-600 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
  const selectedBracket = getSelectedBracket();
  const businessTypeDisplay = getBusinessTypeDisplay();

  return (
    <div className="min-h-screen bg-gray-50 py-6">
      <div className="max-w-7xl mx-auto px-4">

        {/* Header / Status Card */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-6">
          <div className="flex items-center justify-between mb-4">
            <div>
              <button 
                onClick={() => navigate(-1)} 
                className="text-gray-600 hover:text-blue-600 mb-1 flex items-center"
              >
                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to List
              </button>
              <h1 className="text-2xl font-bold text-gray-900">Business Permit Validation</h1>
              <p className="text-gray-600 mt-1">Review and validate business permit application</p>
            </div>
            <div className="flex flex-col items-end">
              <span className={`inline-flex items-center px-4 py-2 rounded-full font-semibold mb-2 ${
                permit.permit_status === 'PENDING' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
                permit.permit_status === 'APPROVED' || permit.permit_status === 'ACTIVE' ? 'bg-green-100 text-green-800 border border-green-200' :
                'bg-gray-100 text-gray-800 border border-gray-200'
              }`}>
                {permit.permit_status || 'PENDING'}
              </span>
              <div className="text-xs text-gray-500">
                Permit ID: <span className="font-mono font-medium text-blue-600">{permit.applicant_id}</span>
              </div>
            </div>
          </div>

          {/* Progress Bar */}
          <div className="mt-4">
            <div className="flex justify-between text-xs text-gray-500 mb-1">
              <span className="font-bold text-blue-600">Submitted</span>
              <span className={`font-bold ${permit.permit_status !== 'PENDING' ? 'text-blue-600' : 'text-gray-400'}`}>
                Assessed
              </span>
              <span className={`${permit.permit_status === 'APPROVED' || permit.permit_status === 'ACTIVE' ? 'font-bold text-blue-600' : 'text-gray-400'}`}>
                Approved
              </span>
              <span className={`${permit.permit_status === 'ACTIVE' ? 'font-bold text-blue-600' : 'text-gray-400'}`}>
                Active
              </span>
            </div>
            <div className="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
              <div className={`h-2 bg-blue-500 rounded-full ${
                permit.permit_status === 'PENDING' ? 'w-1/4' :
                permit.permit_status === 'APPROVED' ? 'w-3/4' :
                permit.permit_status === 'ACTIVE' ? 'w-full' : 'w-1/4'
              }`}></div>
            </div>
          </div>
        </div>

        {/* Main Content Grid */}
        <div className="flex flex-col lg:flex-row gap-6">
          
          {/* Left Column - Business Details */}
          <div className="flex-1 space-y-6">
            
            {/* Business Information Card - Compact Form-like Layout */}
            <div className="bg-white rounded-xl shadow p-6">
              <h2 className="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <svg className="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                Business Information
              </h2>
              
              <div className="space-y-4">
                {/* Business Basic Info */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-1">
                    <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Business Name</label>
                    <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">{permit.business_name}</div>
                  </div>
                  
                  <div className="space-y-1">
                    <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Trade Name</label>
                    <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">
                      {permit.trade_name || 'Same as Business Name'}
                    </div>
                  </div>
                </div>

                {/* Owner Information */}
                <div className="border-t border-gray-200 pt-4">
                  <h3 className="text-sm font-medium text-gray-700 mb-3">Owner Information</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Owner Full Name</label>
                      <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">{permit.owner_full_name}</div>
                    </div>
                    
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Owner Type</label>
                      <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">{permit.owner_type || 'Individual'}</div>
                    </div>
                    
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Number</label>
                      <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">{permit.contact_number || 'N/A'}</div>
                    </div>
                    
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Email Address</label>
                      <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">{permit.email_address || 'N/A'}</div>
                    </div>
                  </div>
                </div>

                {/* Business Details */}
                <div className="border-t border-gray-200 pt-4">
                  <h3 className="text-sm font-medium text-gray-700 mb-3">Business Details</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Business Nature</label>
                      <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">{permit.business_nature}</div>
                    </div>
                    
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Tax Calculation Type</label>
                      <div className="flex gap-2">
                        <span className="px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800 border border-blue-200">
                          {permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment' : 'Gross Sales'}
                        </span>
                        <span className="px-2 py-1 text-xs font-medium rounded bg-purple-100 text-purple-800 border border-purple-200">
                          {businessTypeDisplay}
                        </span>
                      </div>
                    </div>
                    
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment' : 'Gross Sales Amount'}
                      </label>
                      <div className="text-sm font-bold text-gray-900 bg-gray-50 p-2 rounded border">
                        {formatCurrency(permit.capital_investment || permit.taxable_amount || 0)}
                      </div>
                    </div>
                    
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Taxable Amount</label>
                      <div className="text-sm font-bold text-gray-900 bg-gray-50 p-2 rounded border">
                        {formatCurrency(permit.taxable_amount || permit.capital_investment || 0)}
                      </div>
                    </div>
                  </div>
                </div>

                {/* Business Address */}
                <div className="border-t border-gray-200 pt-4">
                  <h3 className="text-sm font-medium text-gray-700 mb-3">Business Address</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Barangay</label>
                      <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">{permit.business_barangay}</div>
                    </div>
                    
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">District</label>
                      <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">{permit.business_district}</div>
                    </div>
                    
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">City</label>
                      <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">{permit.business_city}</div>
                    </div>
                    
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Province</label>
                      <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">{permit.business_province}</div>
                    </div>
                    
                    {permit.business_zipcode && (
                      <div className="space-y-1">
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">ZIP Code</label>
                        <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">{permit.business_zipcode}</div>
                      </div>
                    )}
                  </div>
                </div>

                {/* Dates */}
                <div className="border-t border-gray-200 pt-4">
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="space-y-1">
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Application Date</label>
                      <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">{formatDate(permit.application_date || permit.created_at)}</div>
                    </div>
                    
                    {permit.issue_date && (
                      <div className="space-y-1">
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Issue Date</label>
                        <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">{formatDate(permit.issue_date)}</div>
                      </div>
                    )}
                    
                    {permit.expiry_date && (
                      <div className="space-y-1">
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry Date</label>
                        <div className="text-sm font-medium text-gray-900 bg-gray-50 p-2 rounded border">{formatDate(permit.expiry_date)}</div>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </div>

            {/* Tax Calculation Card */}
            <div className="bg-white rounded-xl shadow p-6">
              <div className="flex items-center justify-between mb-6">
                <h2 className="text-lg font-bold text-gray-900 flex items-center">
                  <svg className="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Tax Calculation
                </h2>
                {calculatedTax && (
                  <button
                    onClick={() => setShowRateOptions(!showRateOptions)}
                    className="inline-flex items-center px-3 py-1 text-sm font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg"
                  >
                    <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    {showRateOptions ? 'Cancel' : 'Adjust Rate'}
                  </button>
                )}
              </div>
              
              {/* Tax Rate Selection Panel */}
              {showRateOptions && (
                <div className="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                  <h3 className="font-medium text-gray-900 mb-3">Select Tax Rate</h3>
                  
                  {permit.tax_calculation_type === 'capital_investment' ? (
                    <div className="mb-4">
                      <h4 className="text-sm font-medium text-gray-700 mb-2">Capital Investment Brackets</h4>
                      <select
                        value={selectedRate || ''}
                        onChange={(e) => {
                          const rateId = e.target.value;
                          if (rateId) {
                            calculateTax(permit, parseInt(rateId));
                          }
                        }}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white"
                      >
                        <option value="">Select Capital Investment Bracket</option>
                        {taxRates.map((rate) => (
                          <option key={rate.id} value={rate.id}>
                            ₱{parseFloat(rate.min_amount).toLocaleString()} - ₱{parseFloat(rate.max_amount).toLocaleString()} : {rate.tax_percent}%
                          </option>
                        ))}
                      </select>
                      {selectedBracket && (
                        <div className="mt-2 text-sm text-gray-600">
                          <div>Selected bracket: ₱{parseFloat(selectedBracket.min_amount).toLocaleString()} - ₱{parseFloat(selectedBracket.max_amount).toLocaleString()}</div>
                          <div>Tax rate: {selectedBracket.tax_percent}%</div>
                        </div>
                      )}
                    </div>
                  ) : (
                    <div className="mb-4">
                      <h4 className="text-sm font-medium text-gray-700 mb-2">Business Type Rates</h4>
                      <div className="space-y-2">
                        {taxRates.map((rate) => (
                          <button
                            key={rate.id}
                            onClick={() => calculateTax(permit, rate.id)}
                            className={`w-full p-3 rounded-lg border text-left ${
                              selectedRate === rate.id
                                ? 'border-blue-500 bg-blue-50'
                                : 'border-gray-200 bg-white hover:bg-gray-50'
                            }`}
                          >
                            <div className="flex justify-between items-center">
                              <div className="text-sm font-medium text-gray-900">{rate.business_type}</div>
                              <div className="text-sm font-bold text-blue-600">{rate.tax_percent}%</div>
                            </div>
                          </button>
                        ))}
                      </div>
                    </div>
                  )}
                  
                  {/* Custom Rate Option */}
                  <div className="pt-3 border-t border-blue-200">
                    <h4 className="text-sm font-medium text-gray-700 mb-2">Custom Tax Rate</h4>
                    <div className="flex gap-2">
                      <div className="flex-1">
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          max="100"
                          value={customRate}
                          onChange={(e) => setCustomRate(e.target.value)}
                          placeholder="Enter custom rate %"
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                        />
                      </div>
                      <button
                        onClick={handleCustomRate}
                        className="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 text-sm"
                      >
                        Apply
                      </button>
                    </div>
                  </div>
                </div>
              )}

              {isCalculating ? (
                <div className="py-8 text-center">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
                  <p className="text-gray-700 text-sm">Calculating tax...</p>
                </div>
              ) : calculatedTax ? (
                <div className="space-y-6">
                  
                  {/* Current Rate Information */}
                  {currentRateInfo && (
                    <div className="bg-blue-50 p-4 rounded-lg border border-blue-200">
                      <div className="flex items-center justify-between">
                        <div>
                          <div className="text-sm font-medium text-blue-900">Applied Rate: {currentRateInfo.rate}</div>
                          <div className="text-xs text-blue-700 mt-1">{currentRateInfo.description}</div>
                          {permit.tax_calculation_type === 'capital_investment' && selectedBracket && (
                            <div className="text-xs text-blue-700 mt-1">
                              Range: ₱{parseFloat(selectedBracket.min_amount).toLocaleString()} - ₱{parseFloat(selectedBracket.max_amount).toLocaleString()}
                            </div>
                          )}
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Tax Calculation Steps */}
                  <div className="space-y-4">
                    {/* Base Calculation */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                      <div className="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <div className="text-xs text-gray-500 mb-1">
                          {permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment' : 'Taxable Base'}
                        </div>
                        <div className="text-lg font-bold text-gray-900">
                          {formatCurrency(calculatedTax.calculation?.taxable_amount || 0)}
                        </div>
                        <div className="text-xs text-gray-500 mt-1">Taxable Amount</div>
                      </div>
                      
                      <div className="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <div className="text-xs text-blue-500 mb-1">Tax Rate</div>
                        <div className="text-lg font-bold text-blue-600">
                          {calculatedTax.calculation?.tax_rate || 0}%
                        </div>
                        <div className="text-xs text-blue-500 mt-1">Applied Rate</div>
                      </div>
                      
                      <div className="bg-green-50 p-4 rounded-lg border border-green-200">
                        <div className="text-xs text-green-500 mb-1">Tax Amount</div>
                        <div className="text-lg font-bold text-green-600">
                          {formatCurrency(calculatedTax.calculation?.tax_amount || 0)}
                        </div>
                        <div className="text-xs text-green-500 mt-1">Base × Rate</div>
                      </div>
                    </div>

                    {/* Regulatory Fees */}
                    <div>
                      <h3 className="text-sm font-medium text-gray-700 mb-3">Regulatory Fees</h3>
                      <div className="space-y-2">
                        {regulatoryFees.length > 0 ? (
                          <>
                            {regulatoryFees.map((fee, index) => (
                              <div key={index} className="flex justify-between items-center py-2 px-3 bg-gray-50 rounded">
                                <span className="text-sm text-gray-700">{fee.fee_name}</span>
                                <span className="text-sm font-medium text-gray-900">
                                  {formatCurrency(fee.amount)}
                                </span>
                              </div>
                            ))}
                            <div className="pt-2 border-t border-gray-300">
                              <div className="flex justify-between font-medium">
                                <span className="text-gray-700">Total Regulatory Fees</span>
                                <span className="text-blue-600">
                                  {formatCurrency(calculatedTax.calculation?.regulatory_fees || 0)}
                                </span>
                              </div>
                            </div>
                          </>
                        ) : (
                          <div className="text-center py-4 text-gray-500 text-sm">
                            No regulatory fees configured
                          </div>
                        )}
                      </div>
                    </div>

                    {/* Total Annual Tax */}
                    <div className="bg-green-50 p-5 rounded-lg border border-green-200">
                      <div className="text-center">
                        <div className="text-sm text-green-700 mb-1">Total Annual Business Tax</div>
                        <div className="text-3xl font-bold text-green-600">
                          {formatCurrency(calculatedTax.calculation?.total_tax || 0)}
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Simple Quarterly Payment Schedule */}
                  {quarterlyBreakdown && (
                    <div className="border-t border-gray-200 pt-6">
                      <h3 className="text-sm font-medium text-gray-700 mb-3">Quarterly Payment Schedule</h3>
                      
                      <div className="mb-4 p-3 bg-gray-50 rounded-lg border">
                        <div className="flex justify-between items-center">
                          <div className="text-sm text-gray-600">Annual Tax Total</div>
                          <div className="text-lg font-bold text-gray-900">
                            {formatCurrency(calculatedTax?.calculation?.total_tax || 0)}
                          </div>
                        </div>
                        <div className="text-xs text-gray-500 mt-1">Divided into 4 equal payments</div>
                      </div>

                      <div className="space-y-2">
                        {quarterlyBreakdown.map((quarter, index) => (
                          <div key={index} className="flex items-center justify-between p-3 bg-white border rounded-lg">
                            <div className="flex items-center">
                              <div className={`w-8 h-8 flex items-center justify-center rounded-full mr-3 ${quarter.color}`}>
                                <span className="text-sm font-bold">{quarter.quarter}</span>
                              </div>
                              <div>
                                <div className="text-sm font-medium text-gray-900">{quarter.label}</div>
                                <div className="text-xs text-gray-500">Due: {quarter.due_date_formatted}</div>
                              </div>
                            </div>
                            <div className="text-right">
                              <div className="text-lg font-bold text-gray-900">
                                {formatCurrency(quarter.quarterly_tax_amount)}
                              </div>
                              <div className="text-xs text-gray-500">per quarter</div>
                            </div>
                          </div>
                        ))}
                      </div>

                      <div className="mt-4 pt-3 border-t border-gray-200">
                        <div className="text-center text-sm text-gray-600">
                          Payments are due quarterly. Late payments may incur penalties.
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              ) : (
                <div className="py-8 text-center">
                  <p className="text-gray-600 text-sm">Tax calculation will begin automatically...</p>
                </div>
              )}
            </div>
          </div>

          {/* Right Column - Action Panel & Summary */}
          <div className="w-full lg:w-80 space-y-6">
            {/* Action Panel */}
            <div className="bg-white rounded-xl shadow p-6">
              <h2 className="text-lg font-bold text-gray-900 mb-4">Actions</h2>
              
              <div className="space-y-3">
                {isCalculating ? (
                  <div className="text-center py-4">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
                    <p className="text-gray-700 text-sm">Calculating tax...</p>
                  </div>
                ) : calculatedTax ? (
                  <>
                    <button
                      onClick={handleApprove}
                      disabled={isApproving || permit.permit_status === 'APPROVED' || permit.permit_status === 'ACTIVE'}
                      className={`w-full py-3 font-semibold rounded-lg transition-all flex items-center justify-center ${
                        permit.permit_status === 'APPROVED' || permit.permit_status === 'ACTIVE'
                          ? 'bg-green-100 text-green-700 cursor-not-allowed'
                          : 'bg-green-600 hover:bg-green-700 text-white'
                      } ${isApproving ? 'opacity-50' : ''}`}
                    >
                      {isApproving ? (
                        <>
                          <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                          Approving...
                        </>
                      ) : permit.permit_status === 'APPROVED' || permit.permit_status === 'ACTIVE' ? (
                        <>
                          <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                          </svg>
                          Already Approved
                        </>
                      ) : (
                        <>
                          <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                          </svg>
                          Approve Permit
                        </>
                      )}
                    </button>
                    
                    {permit.permit_status !== 'APPROVED' && permit.permit_status !== 'ACTIVE' && (
                      <>
                        <button
                          onClick={() => setShowRateOptions(!showRateOptions)}
                          className="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-all flex items-center justify-center"
                        >
                          <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                          </svg>
                          Adjust Tax Rate
                        </button>
                        
                        <div className="bg-blue-50 p-3 rounded border border-blue-200 mt-4">
                          <div className="text-sm text-blue-700">
                            Approving will save the tax calculation and generate quarterly payment records.
                          </div>
                        </div>
                      </>
                    )}
                  </>
                ) : (
                  <div className="text-center py-4">
                    <p className="text-gray-600 text-sm">Please wait for tax calculation...</p>
                  </div>
                )}
              </div>
            </div>

            {/* Summary Card */}
            <div className="bg-white rounded-xl shadow p-6">
              <h2 className="text-lg font-bold text-gray-900 mb-4">Quick Summary</h2>
              <div className="space-y-3">
                <div className="space-y-2">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Business Nature</span>
                    <span className="text-sm font-medium text-gray-900">{permit.business_nature}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Tax Type</span>
                    <span className="text-sm font-medium text-gray-900">
                      {permit.tax_calculation_type === 'capital_investment' ? 'Capital Investment' : 'Gross Sales'}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Tax Base</span>
                    <span className="text-sm font-bold text-gray-900">
                      {formatCurrency(permit.taxable_amount || permit.capital_investment || 0)}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Application Date</span>
                    <span className="text-sm text-gray-900">{formatDate(permit.application_date || permit.created_at)}</span>
                  </div>
                </div>

                {currentRateInfo && (
                  <div className="pt-3 border-t border-gray-200">
                    <h3 className="text-xs font-medium text-gray-700 mb-2">Applied Tax Rate</h3>
                    <div className="space-y-1">
                      <div className="flex justify-between">
                        <span className="text-xs text-gray-600">Type</span>
                        <span className="text-xs font-medium text-gray-900">{currentRateInfo.type}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-xs text-gray-600">Rate</span>
                        <span className="text-xs font-bold text-blue-600">{currentRateInfo.rate}</span>
                      </div>
                      {permit.tax_calculation_type === 'capital_investment' && selectedBracket && (
                        <div className="flex justify-between">
                          <span className="text-xs text-gray-600">Range</span>
                          <span className="text-xs font-medium text-gray-900">
                            ₱{parseFloat(selectedBracket.min_amount).toLocaleString()} - ₱{parseFloat(selectedBracket.max_amount).toLocaleString()}
                          </span>
                        </div>
                      )}
                    </div>
                  </div>
                )}

                {calculatedTax && (
                  <div className="pt-3 border-t border-gray-200">
                    <div className="bg-gray-50 p-3 rounded-lg">
                      <div className="text-center">
                        <div className="text-xs text-gray-500 mb-1">Total Annual Tax</div>
                        <div className="text-xl font-bold text-green-600">
                          {formatCurrency(calculatedTax.calculation?.total_tax || 0)}
                        </div>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            </div>

            {/* Information Card */}
            <div className="bg-white rounded-xl shadow p-6">
              <h2 className="text-lg font-bold text-gray-900 mb-4">System Information</h2>
              <div className="space-y-3">
                <div className="text-sm text-gray-600">
                  Tax rates are loaded from the database. You can select from available rates or enter a custom rate.
                </div>
                <div className="text-sm text-gray-600">
                  Approving will automatically generate quarterly payment schedules.
                </div>
                {discountConfig && (
                  <div className="text-sm text-blue-600">
                    Discount available: {discountConfig.discount_percent}% for early payments
                  </div>
                )}
                {penaltyConfig && (
                  <div className="text-sm text-red-600">
                    Penalty rate: {penaltyConfig.penalty_percent}% for late payments
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Important Notes Section */}
        {!calculatedTax && (
          <div className="mt-6 bg-yellow-50 border border-yellow-200 rounded-xl p-4">
            <div className="flex items-start">
              <div className="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                <span className="text-yellow-600">⚠️</span>
              </div>
              <div>
                <h3 className="font-semibold text-gray-900 mb-1">Tax Calculation Required</h3>
                <ul className="text-sm text-gray-700">
                  <li>• Tax calculation is in progress or not yet started</li>
                  <li>• Review and adjust tax rates if necessary</li>
                  <li>• Complete tax calculation before approving permit</li>
                </ul>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default BusinessValidationInfo;