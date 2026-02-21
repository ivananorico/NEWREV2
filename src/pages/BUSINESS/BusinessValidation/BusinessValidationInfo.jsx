import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';

const BusinessValidationInfo = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const [permit, setPermit] = useState(null);
  const [users, setUsers] = useState([]);
  const [selectedUserId, setSelectedUserId] = useState('');
  const [showUserSelector, setShowUserSelector] = useState(false);
  const [autoMatchedUser, setAutoMatchedUser] = useState(null);
  const [isAutoMatching, setIsAutoMatching] = useState(false);
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
  const [autoCalculatedRate, setAutoCalculatedRate] = useState(null);

  const API_BASE = window.location.hostname === "localhost"
    ? "http://localhost/revenue2/backend"
    : "https://revenuetreasury.goserveph.com/backend";

  // Fetch users from the database
  const fetchUsers = async () => {
    try {
      const response = await fetch(`${API_BASE}/Business/BusinessValidation/get_users.php`);
      const responseText = await response.text();
      
      if (responseText.includes('<br />') || responseText.includes('<b>') || responseText.trim().startsWith('<')) {
        console.error('Server error in fetchUsers');
        return;
      }
      
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (parseError) {
        console.error('JSON Parse Error in fetchUsers:', parseError);
        return;
      }
      
      if (data.status === 'success') {
        setUsers(data.users || []);
      }
    } catch (err) {
      console.error('Error fetching users:', err);
    }
  };

  // Auto-match user by email
  const autoMatchUserByEmail = async (email) => {
    if (!email) return null;
    
    setIsAutoMatching(true);
    try {
      const response = await fetch(`${API_BASE}/Business/BusinessValidation/find_user_by_email.php`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ email: email })
      });
      
      const responseText = await response.text();
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (parseError) {
        console.error('JSON Parse Error in autoMatchUserByEmail:', parseError);
        return null;
      }
      
      if (data.status === 'success' && data.found) {
        setAutoMatchedUser(data.user);
        setSelectedUserId(data.user.id.toString());
        return data.user;
      } else {
        setAutoMatchedUser(null);
        return null;
      }
    } catch (err) {
      console.error('Error auto-matching user:', err);
      return null;
    } finally {
      setIsAutoMatching(false);
    }
  };

  useEffect(() => {
    const loadData = async () => {
      try {
        setLoading(true);
        setError('');
        
        // Fetch users first
        await fetchUsers();
        
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
        
        // Try to auto-match user by email if permit has email
        if (permitData.permit.email_address) {
          await autoMatchUserByEmail(permitData.permit.email_address);
        }
        
        // If permit already has user_id, use that
        if (permitData.permit.user_id) {
          setSelectedUserId(permitData.permit.user_id.toString());
        }
        
        setRegulatoryFees(permitData.regulatory_fees || []);
        setTaxConfig(permitData.tax_config || null);
        setDiscountConfig(permitData.discount_config || null);
        setPenaltyConfig(permitData.penalty_config || null);
        
        // Load tax rates
        await loadTaxRates(permitData.permit);
        
        // Check if there's already calculated tax in the permit
        const permitTaxAmount = parseFloat(permitData.permit.tax_amount) || 0;
        const permitTotalTax = parseFloat(permitData.permit.total_tax) || 0;
        
        if (permitTaxAmount > 0 || permitTotalTax > 0) {
          // Use existing tax calculation
          const existingTax = {
            status: 'success',
            calculation: {
              taxable_amount: permitData.permit.taxable_amount || permitData.permit.capital_investment || 0,
              tax_rate: parseFloat(permitData.permit.tax_rate) || 0,
              tax_amount: permitTaxAmount,
              regulatory_fees: permitData.total_regulatory_fees || 0,
              total_tax: permitTotalTax
            }
          };
          setCalculatedTax(existingTax);
          
          const quarterlyData = calculateQuarterlyBreakdown(
            permitTotalTax, 
            permitData.permit.issue_date || permitData.permit.application_date
          );
          setQuarterlyBreakdown(quarterlyData);
          
          // Set selected rate from existing data
          if (permitData.permit.tax_rate) {
            setSelectedRate(parseFloat(permitData.permit.tax_rate));
            setAutoCalculatedRate(parseFloat(permitData.permit.tax_rate));
          }
        } else {
          // Auto-calculate tax based on permit data
          await autoCalculateTax(permitData.permit);
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
        ratesUrl = `${API_BASE}/Business/BusinessValidation/get_gross_sales_config.php`;
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
    } catch (err) {
      console.error('Error loading tax rates, using defaults:', err);
    }
  };

  const autoCalculateTax = async (permitData) => {
    try {
      setIsCalculating(true);
      
      if (!permitData) {
        alert('Permit data not available');
        return;
      }
      
      const taxableAmount = parseFloat(permitData.taxable_amount || permitData.capital_investment || 0);
      let taxRate = 0;
      let rateInfo = {};
      
      // Determine tax rate based on calculation type
      if (permitData.tax_calculation_type === 'capital_investment') {
        // Find the appropriate bracket for capital investment
        const bracket = taxRates.find(rate => 
          taxableAmount >= parseFloat(rate.min_amount) && 
          taxableAmount <= parseFloat(rate.max_amount)
        );
        
        if (bracket) {
          taxRate = parseFloat(bracket.tax_percent);
          rateInfo = {
            type: 'Auto-selected',
            bracket: bracket,
            isCustom: false
          };
        } else {
          // Default rate if no bracket matches
          taxRate = 25.00;
          rateInfo = {
            type: 'Default',
            isCustom: false
          };
        }
      } else {
        // For gross sales, use business_nature to determine rate
        const businessNature = permitData.business_nature || '';
        let businessType = '';
        
        if (businessNature.toLowerCase().includes('retail')) businessType = 'Retailer';
        else if (businessNature.toLowerCase().includes('whole')) businessType = 'Wholesaler';
        else if (businessNature.toLowerCase().includes('manufact') || businessNature.toLowerCase().includes('factory')) 
          businessType = 'Manufacturer';
        else if (businessNature.toLowerCase().includes('service') || businessNature.toLowerCase().includes('consult')) 
          businessType = 'Service';
        else businessType = 'Retailer'; // Default
        
        const rate = taxRates.find(r => r.business_type === businessType);
        taxRate = rate ? parseFloat(rate.tax_percent) : 2.00; // Default 2%
        rateInfo = {
          type: 'Auto-selected',
          businessType: businessType,
          isCustom: false
        };
      }
      
      // Calculate tax amount
      const taxAmount = (taxableAmount * taxRate) / 100;
      
      // Calculate regulatory fees
      const totalRegulatoryFees = regulatoryFees.reduce((sum, fee) => sum + parseFloat(fee.amount || 0), 0);
      const totalTax = taxAmount + totalRegulatoryFees;
      
      const calculatedTaxData = {
        status: 'success',
        calculation: {
          taxable_amount: taxableAmount,
          tax_rate: taxRate,
          tax_amount: taxAmount,
          regulatory_fees: totalRegulatoryFees,
          total_tax: totalTax,
          rate_info: rateInfo
        }
      };
      
      setCalculatedTax(calculatedTaxData);
      setAutoCalculatedRate(taxRate);
      setSelectedRate(taxRate);
      
      const quarterlyData = calculateQuarterlyBreakdown(totalTax, permitData.issue_date);
      setQuarterlyBreakdown(quarterlyData);
      
      // Update permit with auto-calculated values
      setPermit(prev => ({
        ...prev,
        tax_amount: taxAmount,
        total_tax: totalTax,
        tax_rate: taxRate
      }));
      
    } catch (err) {
      console.error('Auto tax calculation error:', err);
      alert('Auto tax calculation failed: ' + err.message);
    } finally {
      setIsCalculating(false);
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

  const handleCustomRate = () => {
    if (!permit) {
      alert('No permit data available');
      return;
    }
    
    if (!customRate || isNaN(customRate) || parseFloat(customRate) <= 0) {
      alert('Please enter a valid tax rate (greater than 0)');
      return;
    }
    
    const customRateValue = parseFloat(customRate);
    updateTaxWithNewRate(customRateValue, true);
    setShowRateOptions(false);
  };

  const updateTaxWithNewRate = (newRate, isCustom = false) => {
    if (!permit || !calculatedTax) return;
    
    const taxableAmount = parseFloat(calculatedTax.calculation?.taxable_amount || 0);
    const taxAmount = (taxableAmount * newRate) / 100;
    const totalRegulatoryFees = calculatedTax.calculation?.regulatory_fees || 0;
    const totalTax = taxAmount + totalRegulatoryFees;
    
    const updatedTax = {
      ...calculatedTax,
      calculation: {
        ...calculatedTax.calculation,
        tax_rate: newRate,
        tax_amount: taxAmount,
        total_tax: totalTax,
        rate_info: {
          ...calculatedTax.calculation?.rate_info,
          isCustom: isCustom
        }
      }
    };
    
    setCalculatedTax(updatedTax);
    setSelectedRate(newRate);
    
    const quarterlyData = calculateQuarterlyBreakdown(totalTax, permit.issue_date);
    setQuarterlyBreakdown(quarterlyData);
    
    // Update permit with new values
    setPermit(prev => ({
      ...prev,
      tax_amount: taxAmount,
      total_tax: totalTax,
      tax_rate: newRate
    }));
  };

  const handleSelectBracket = (bracketId) => {
    const bracket = taxRates.find(rate => rate.id === bracketId);
    if (bracket) {
      const taxRate = parseFloat(bracket.tax_percent);
      updateTaxWithNewRate(taxRate, false);
    }
  };

  const handleSelectBusinessType = (rateId) => {
    const rate = taxRates.find(r => r.id === rateId);
    if (rate) {
      const taxRate = parseFloat(rate.tax_percent);
      updateTaxWithNewRate(taxRate, false);
    }
  };

  const handleResetToAuto = () => {
    if (autoCalculatedRate !== null) {
      updateTaxWithNewRate(autoCalculatedRate, false);
      setCustomRate('');
      setShowRateOptions(false);
    }
  };

  const handleAssignUser = async () => {
    if (!selectedUserId) {
      alert('Please select a user');
      return;
    }

    // Validate that selectedUserId is a valid number
    const userIdNum = parseInt(selectedUserId);
    if (isNaN(userIdNum)) {
      alert('Invalid user ID selected');
      return;
    }

    console.log('Assigning user - Permit ID:', id, 'User ID:', userIdNum);

    try {
      const assignUrl = `${API_BASE}/Business/BusinessValidation/assign_user_to_permit.php`;
      const response = await fetch(assignUrl, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          permit_id: id,
          user_id: userIdNum
        })
      });

      const responseText = await response.text();
      console.log('Raw server response:', responseText);
      
      let result;
      try {
        result = JSON.parse(responseText);
      } catch (parseError) {
        console.error('JSON Parse Error:', parseError);
        console.error('Response that failed to parse:', responseText);
        throw new Error('Invalid server response: ' + responseText.substring(0, 100));
      }

      console.log('Parsed response:', result);

      if (result.status === 'success') {
        alert('User assigned successfully!');
        setShowUserSelector(false);
        setAutoMatchedUser(null);
        
        // Update the permit with the new user_id
        setPermit(prev => {
          const updated = {
            ...prev,
            user_id: userIdNum
          };
          console.log('Updated permit state:', updated);
          return updated;
        });
        
        // Keep selectedUserId in sync (as string for radio buttons)
        setSelectedUserId(selectedUserId);
        
      } else {
        alert('Error: ' + (result.message || 'Failed to assign user'));
      }
    } catch (err) {
      console.error('Error assigning user:', err);
      alert('Error assigning user: ' + err.message);
    }
  };

  const handleRetryAutoMatch = async () => {
    if (permit && permit.email_address) {
      await autoMatchUserByEmail(permit.email_address);
    }
  };

  const handleApprove = async () => {
    if (!window.confirm('Are you sure you want to approve this business permit with the calculated tax?')) return;
    
    if (!calculatedTax || !permit) {
      alert('Tax calculation is not complete');
      return;
    }

    // Check both permit.user_id and selectedUserId
    const finalUserId = permit.user_id || (selectedUserId ? parseInt(selectedUserId) : null);
    
    if (!finalUserId) {
      alert('Please assign a user to this permit before approving');
      setShowUserSelector(true);
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
        permit_status: 'APPROVED',
        user_id: finalUserId
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
    
    const rateInfo = calculatedTax.calculation?.rate_info || {};
    
    if (permit.tax_calculation_type === 'capital_investment') {
      if (rateInfo.isCustom) {
        return {
          type: 'Custom Rate',
          rate: `${calculatedTax.calculation?.tax_rate}%`,
          description: 'Manually adjusted tax rate',
          isCustom: true
        };
      } else if (rateInfo.bracket) {
        return {
          type: 'Auto-selected Bracket',
          bracket: rateInfo.bracket,
          rate: `${calculatedTax.calculation?.tax_rate}%`,
          description: `Auto-applied for capital investment: ₱${(calculatedTax.calculation?.taxable_amount || 0).toLocaleString()}`,
          isCustom: false
        };
      }
    } else {
      if (rateInfo.isCustom) {
        return {
          type: 'Custom Rate',
          rate: `${calculatedTax.calculation?.tax_rate}%`,
          description: 'Manually adjusted tax rate',
          isCustom: true
        };
      } else {
        return {
          type: 'Auto-selected Rate',
          businessType: rateInfo.businessType || 'Standard',
          rate: `${calculatedTax.calculation?.tax_rate}%`,
          description: `Auto-applied for ${rateInfo.businessType || 'Standard'} business type`,
          isCustom: false
        };
      }
    }
    
    return {
      type: 'Standard Rate',
      rate: `${calculatedTax.calculation?.tax_rate}%`,
      description: 'Applied tax rate',
      isCustom: false
    };
  };

  const getBusinessTypeDisplay = () => {
    if (!permit) return '';
    
    const businessNature = permit.business_nature || '';
    if (businessNature.toLowerCase().includes('retail')) return 'Retailer';
    if (businessNature.toLowerCase().includes('whole')) return 'Wholesaler';
    if (businessNature.toLowerCase().includes('manufact') || businessNature.toLowerCase().includes('factory')) return 'Manufacturer';
    if (businessNature.toLowerCase().includes('service') || businessNature.toLowerCase().includes('consult')) return 'Service';
    
    return businessNature || 'General Business';
  };

  const getUserFullName = (user) => {
    if (!user) return '';
    const parts = [
      user.first_name,
      user.middle_name,
      user.last_name,
      user.suffix
    ].filter(Boolean);
    return parts.join(' ');
  };

  // Check if permit is already approved
  const isPermitApproved = permit?.permit_status === 'APPROVED' || permit?.permit_status === 'ACTIVE';

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
  const businessTypeDisplay = getBusinessTypeDisplay();
  const isCustomRate = currentRateInfo?.isCustom;

  // Find selected user details
  const selectedUser = users.find(u => u.id.toString() === selectedUserId) || autoMatchedUser;
  
  // Check if user is already assigned (but permit is not approved)
  const isUserAssigned = (permit.user_id || selectedUserId) && !isPermitApproved;

  return (
    <div className="min-h-screen bg-gray-50 py-6">
      <div className="max-w-7xl mx-auto px-4">

        {/* Header */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-6">
          <div className="flex items-center justify-between mb-6">
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

          {/* User Assignment Section */}
          <div className="border-t border-gray-200 pt-4 mt-2">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <svg className="w-5 h-5 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <h3 className="font-medium text-gray-900">Citizen Assignment</h3>
              </div>
              <div className="flex gap-2">
                {isAutoMatching && (
                  <div className="flex items-center text-sm text-blue-600">
                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 mr-2"></div>
                    Auto-matching...
                  </div>
                )}
                {/* Show Assign Citizen button if permit is NOT approved */}
                {!isPermitApproved && (
                  <button
                    onClick={() => setShowUserSelector(!showUserSelector)}
                    className="inline-flex items-center px-3 py-1 text-sm font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg"
                  >
                    <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                    {permit.user_id || selectedUserId ? 'Change Assignment' : 'Assign Citizen'}
                  </button>
                )}
                {!permit.user_id && !selectedUserId && !isPermitApproved && permit.email_address && (
                  <button
                    onClick={handleRetryAutoMatch}
                    className="inline-flex items-center px-3 py-1 text-sm font-medium text-green-600 hover:text-green-800 hover:bg-green-50 rounded-lg"
                    disabled={isAutoMatching}
                  >
                    <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Retry Auto-match
                  </button>
                )}
              </div>
            </div>

            {/* Auto-match status */}
            {autoMatchedUser && !permit.user_id && !isPermitApproved && (
              <div className="mt-3 p-4 bg-green-50 rounded-lg border border-green-200">
                <div className="flex items-start">
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <span className="text-xs px-2 py-1 bg-green-100 text-green-800 rounded-full">AUTO-MATCHED</span>
                      <span className="text-sm text-gray-600">Based on email: {permit.email_address}</span>
                    </div>
                    <div className="font-medium text-gray-900 mt-2">
                      {getUserFullName(autoMatchedUser)}
                    </div>
                    <div className="text-xs text-gray-500 mt-1">
                      Email: {autoMatchedUser.email} | Mobile: {autoMatchedUser.mobile || 'N/A'}
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* Current Assignment - Always show if user is assigned, even if approved */}
            {(permit.user_id || selectedUserId) && !showUserSelector && (
              <div className="mt-3 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <div className="flex items-start">
                  <div className="flex-1">
                    <div className="text-sm text-gray-600">Assigned Citizen:</div>
                    <div className="font-medium text-gray-900">
                      {selectedUser ? getUserFullName(selectedUser) : 'Citizen #' + (permit.user_id || selectedUserId)}
                    </div>
                    {selectedUser && (
                      <div className="text-xs text-gray-500 mt-1">
                        Email: {selectedUser.email} | Mobile: {selectedUser.mobile || 'N/A'}
                      </div>
                    )}
                  </div>
                  <div className="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full">
                    {permit.user_id ? 'Database Assigned' : 'Manually Assigned'}
                  </div>
                </div>
              </div>
            )}

            {/* User Selector - Only show if permit is NOT approved */}
            {!isPermitApproved && showUserSelector && (
              <div className="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                <h4 className="text-sm font-medium text-gray-700 mb-3">Select Citizen</h4>
                
                <div className="max-h-64 overflow-y-auto mb-3">
                  <div className="space-y-2">
                    {users.length > 0 ? (
                      users.map(user => (
                        <label
                          key={user.id}
                          className={`flex items-start p-3 border rounded-lg cursor-pointer transition-colors ${
                            selectedUserId === user.id.toString()
                              ? 'border-blue-500 bg-blue-50'
                              : 'border-gray-200 bg-white hover:bg-gray-50'
                          }`}
                        >
                          <input
                            type="radio"
                            name="userSelection"
                            value={user.id}
                            checked={selectedUserId === user.id.toString()}
                            onChange={(e) => setSelectedUserId(e.target.value)}
                            className="mt-1 mr-3"
                          />
                          <div className="flex-1">
                            <div className="font-medium text-gray-900">{getUserFullName(user)}</div>
                            <div className="text-xs text-gray-600 mt-1">
                              Email: {user.email} | Mobile: {user.mobile || 'N/A'}
                            </div>
                            <div className="text-xs text-gray-500 mt-1">
                              ID: {user.id} • Role: {user.role} • Status: {user.status}
                            </div>
                          </div>
                        </label>
                      ))
                    ) : (
                      <div className="text-center py-8 text-gray-500">
                        <p>No citizens found in the database</p>
                        <p className="text-xs mt-1">Please add users to the system first</p>
                      </div>
                    )}
                  </div>
                </div>

                <div className="flex justify-end gap-2">
                  <button
                    onClick={() => setShowUserSelector(false)}
                    className="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
                  >
                    Back
                  </button>
                </div>
              </div>
            )}

            {/* No match message */}
            {!autoMatchedUser && !permit.user_id && !selectedUserId && !showUserSelector && !isPermitApproved && permit.email_address && (
              <div className="mt-3 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                <div className="flex items-center">
                  <svg className="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                  <div className="flex-1">
                    <p className="text-sm font-medium text-yellow-800">
                      No citizen found with email: {permit.email_address}
                    </p>
                    <p className="text-xs text-yellow-700 mt-1">
                      Please manually assign a citizen from the list.
                    </p>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Main Content Grid - The rest of your code remains exactly the same */}
        <div className="flex flex-col lg:flex-row gap-6">
          
          {/* Left Column */}
          <div className="flex-1 space-y-6">
            
            {/* Business Information Card */}
            <div className="bg-white rounded-xl shadow p-6">
              <h2 className="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <svg className="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                Business Information
              </h2>
              
              <div className="space-y-4">
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
                
                {/* Edit Rate Button in Tax Calculation Card */}
                <button
                  onClick={() => setShowRateOptions(!showRateOptions)}
                  className="inline-flex items-center px-3 py-1 text-sm font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg"
                >
                  <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                  {showRateOptions ? 'Hide Rate Options' : 'Edit Tax Rate'}
                </button>
              </div>
              
              {/* Tax Rate Selection Panel */}
              {showRateOptions && (
                <div className="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                  <h3 className="font-medium text-gray-900 mb-3">Adjust Tax Rate</h3>
                  
                  {permit.tax_calculation_type === 'capital_investment' ? (
                    <div className="mb-4">
                      <h4 className="text-sm font-medium text-gray-700 mb-2">Capital Investment Brackets</h4>
                      <div className="space-y-2">
                        {taxRates.map((rate) => (
                          <button
                            key={rate.id}
                            onClick={() => handleSelectBracket(rate.id)}
                            className={`w-full p-3 rounded-lg border text-left ${
                              selectedRate === parseFloat(rate.tax_percent) && !isCustomRate
                                ? 'border-blue-500 bg-blue-50'
                                : 'border-gray-200 bg-white hover:bg-gray-50'
                            }`}
                          >
                            <div className="flex justify-between items-center">
                              <div>
                                <div className="text-sm font-medium text-gray-900">
                                  ₱{parseFloat(rate.min_amount).toLocaleString()} - ₱{parseFloat(rate.max_amount).toLocaleString()}
                                </div>
                                <div className="text-xs text-gray-500 mt-1">
                                  Auto-applied when capital is within this range
                                </div>
                              </div>
                              <div className="text-sm font-bold text-blue-600">{rate.tax_percent}%</div>
                            </div>
                          </button>
                        ))}
                      </div>
                    </div>
                  ) : (
                    <div className="mb-4">
                      <h4 className="text-sm font-medium text-gray-700 mb-2">Business Type Rates</h4>
                      <div className="space-y-2">
                        {taxRates.map((rate) => (
                          <button
                            key={rate.id}
                            onClick={() => handleSelectBusinessType(rate.id)}
                            className={`w-full p-3 rounded-lg border text-left ${
                              selectedRate === parseFloat(rate.tax_percent) && !isCustomRate
                                ? 'border-blue-500 bg-blue-50'
                                : 'border-gray-200 bg-white hover:bg-gray-50'
                            }`}
                          >
                            <div className="flex justify-between items-center">
                              <div>
                                <div className="text-sm font-medium text-gray-900">{rate.business_type}</div>
                                <div className="text-xs text-gray-500 mt-1">
                                  Standard rate for {rate.business_type.toLowerCase()} businesses
                                </div>
                              </div>
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
                        Apply Custom
                      </button>
                    </div>
                    <div className="text-xs text-gray-500 mt-2">
                      Enter a custom percentage rate (e.g., 2.5 for 2.5%)
                    </div>
                    {isCustomRate && autoCalculatedRate !== null && (
                      <button
                        onClick={handleResetToAuto}
                        className="mt-2 inline-flex items-center px-3 py-1 text-xs text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg"
                      >
                        <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                        </svg>
                        Reset to Auto-calculated Rate ({autoCalculatedRate}%)
                      </button>
                    )}
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
                    <div className={`p-4 rounded-lg border ${
                      isCustomRate 
                        ? 'bg-purple-50 border-purple-200' 
                        : 'bg-blue-50 border-blue-200'
                    }`}>
                      <div className="flex items-center justify-between">
                        <div>
                          <div className="text-sm font-medium text-gray-900">
                            {isCustomRate ? 'Custom Tax Rate Applied' : 'Auto-calculated Tax Rate'}
                          </div>
                          <div className="flex items-center mt-1">
                            <span className="text-2xl font-bold text-blue-600 mr-2">
                              {calculatedTax.calculation?.tax_rate}%
                            </span>
                            <span className="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-800">
                              {currentRateInfo.type}
                            </span>
                          </div>
                          <div className="text-xs text-gray-600 mt-2">
                            {currentRateInfo.description}
                          </div>
                          {currentRateInfo.bracket && (
                            <div className="text-xs text-gray-600 mt-1">
                              Range: ₱{parseFloat(currentRateInfo.bracket.min_amount).toLocaleString()} - ₱{parseFloat(currentRateInfo.bracket.max_amount).toLocaleString()}
                            </div>
                          )}
                        </div>
                        {isCustomRate && (
                          <div className="text-xs px-3 py-1 rounded-full bg-purple-100 text-purple-800 font-medium">
                            MANUAL
                          </div>
                        )}
                      </div>
                    </div>
                  )}

                  {/* Tax Calculation Steps */}
                  <div className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                      <div className="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <div className="text-xs text-gray-500 mb-1">Taxable Base</div>
                        <div className="text-lg font-bold text-gray-900">
                          {formatCurrency(calculatedTax.calculation?.taxable_amount || 0)}
                        </div>
                      </div>
                      
                      <div className="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <div className="text-xs text-blue-500 mb-1">Tax Rate</div>
                        <div className="text-lg font-bold text-blue-600">
                          {calculatedTax.calculation?.tax_rate || 0}%
                        </div>
                      </div>
                      
                      <div className="bg-green-50 p-4 rounded-lg border border-green-200">
                        <div className="text-xs text-green-500 mb-1">Tax Amount</div>
                        <div className="text-lg font-bold text-green-600">
                          {formatCurrency(calculatedTax.calculation?.tax_amount || 0)}
                        </div>
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

                  {/* Quarterly Payment Schedule */}
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

          {/* Right Column - Action Panel */}
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
                  <button
                    onClick={handleApprove}
                    disabled={isApproving || isPermitApproved || !(permit.user_id || selectedUserId)}
                    className={`w-full py-3 font-semibold rounded-lg transition-all flex items-center justify-center ${
                      isPermitApproved
                        ? 'bg-green-100 text-green-700 cursor-not-allowed'
                        : !(permit.user_id || selectedUserId)
                        ? 'bg-gray-300 text-gray-600 cursor-not-allowed'
                        : 'bg-green-600 hover:bg-green-700 text-white'
                    } ${isApproving ? 'opacity-50' : ''}`}
                    title={!(permit.user_id || selectedUserId) ? 'Please assign a citizen first' : ''}
                  >
                    {isApproving ? (
                      <>
                        <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                        Approving...
                      </>
                    ) : isPermitApproved ? (
                      <>
                        <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                        Already Approved
                      </>
                    ) : !(permit.user_id || selectedUserId) ? (
                      <>
                        <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Assign Citizen First
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
                  {calculatedTax && (
                    <>
                      <div className="flex justify-between items-center">
                        <span className="text-sm text-gray-600">Tax Rate</span>
                        <span className={`text-sm font-bold ${isCustomRate ? 'text-purple-600' : 'text-blue-600'}`}>
                          {calculatedTax.calculation?.tax_rate}%
                        </span>
                      </div>
                      <div className="pt-2 border-t border-gray-200">
                        <div className="flex justify-between items-center">
                          <span className="text-sm font-medium text-gray-700">Annual Tax</span>
                          <span className="text-lg font-bold text-green-600">
                            {formatCurrency(calculatedTax.calculation?.total_tax || 0)}
                          </span>
                        </div>
                      </div>
                    </>
                  )}
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