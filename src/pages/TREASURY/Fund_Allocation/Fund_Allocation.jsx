import React, { useState, useEffect } from 'react';

export default function Fund_Allocation() {
  const [bankAccount, setBankAccount] = useState(null);
  const [funds, setFunds] = useState([]);
  const [allocations, setAllocations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showFundModal, setShowFundModal] = useState(false);
  const [showAllocationModal, setShowAllocationModal] = useState(false);
  const [showEditFundModal, setShowEditFundModal] = useState(false);
  const [showEditAllocationModal, setShowEditAllocationModal] = useState(false);
  const [errors, setErrors] = useState([]);
  const [selectedItem, setSelectedItem] = useState(null);
  
  // Form states
  const [newFund, setNewFund] = useState({
    fund_name: '',
    description: '',
    initial_balance: '',
    fiscal_year: new Date().getFullYear()
  });
  
  const [newAllocation, setNewAllocation] = useState({
    fund_id: '',
    department: '',
    purpose: '',
    allocated_amount: '',
    allocated_date: new Date().toISOString().split('T')[0]
  });

  // Edit states
  const [editFund, setEditFund] = useState({
    id: '',
    fund_name: '',
    description: '',
    initial_balance: '',
    fiscal_year: ''
  });
  
  const [editAllocation, setEditAllocation] = useState({
    id: '',
    fund_id: '',
    department: '',
    purpose: '',
    allocated_amount: '',
    allocated_date: ''
  });

  // Color palette
  const colors = {
    primary: '#4a90e2',    // Blue
    secondary: '#9aa5b1',  // Gray
    success: '#4caf50',    // Green
    background: '#fbfbfb',  // Off-white
    textDark: '#1f2937',    // Dark gray
    textLight: '#6b7280',   // Light gray
    warning: '#f59e0b',     // Amber
    danger: '#ef4444',      // Red
    purple: '#8b5cf6',      // Purple for allocations
  };

  // API Base
  const API_BASE = 'http://localhost/revenue2/backend/Treasury/Fund_Allocation';

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      setErrors([]);
      
      // Load bank account
      try {
        const response = await fetch(`${API_BASE}/get_bank_account.php`);
        const data = await response.json();
        
        if (data.status === 'success') {
          setBankAccount(data.data || data.account);
        } else {
          setErrors(prev => [...prev, `Bank: ${data.message}`]);
        }
      } catch (bankError) {
        console.error('Bank API error:', bankError);
        setErrors(prev => [...prev, 'Failed to load bank account']);
      }
      
      // Load funds
      try {
        const response = await fetch(`${API_BASE}/get_funds.php`);
        const data = await response.json();
        
        if (data.status === 'success') {
          setFunds(data.data || []);
        } else {
          setErrors(prev => [...prev, `Funds: ${data.message}`]);
        }
      } catch (fundsError) {
        console.error('Funds API error:', fundsError);
        setErrors(prev => [...prev, 'Failed to load funds']);
      }
      
      // Load allocations
      try {
        const response = await fetch(`${API_BASE}/get_allocations.php`);
        const data = await response.json();
        
        if (data.status === 'success') {
          setAllocations(data.data || []);
        } else {
          setErrors(prev => [...prev, `Allocations: ${data.message}`]);
        }
      } catch (allocError) {
        console.error('Allocations API error:', allocError);
        setErrors(prev => [...prev, 'Failed to load allocations']);
      }
      
    } catch (error) {
      console.error('Error loading data:', error);
      setErrors(prev => [...prev, `System: ${error.message}`]);
    } finally {
      setLoading(false);
    }
  };

  // Calculate real-time balances
  const bankBalance = bankAccount ? parseFloat(bankAccount.current_balance || 0) : 0;
  const totalFunds = funds.reduce((sum, fund) => sum + parseFloat(fund.current_balance || 0), 0);
  const totalAllocated = allocations.reduce((sum, alloc) => sum + parseFloat(alloc.allocated_amount || 0), 0);
  const availableInBank = bankBalance - totalFunds;
  const availableInFunds = totalFunds - totalAllocated;

  // Calculate real-time balance changes as user types
  const newFundAmount = parseFloat(newFund.initial_balance) || 0;
  const projectedBankBalance = availableInBank - newFundAmount;
  const newAllocationAmount = parseFloat(newAllocation.allocated_amount) || 0;
  
  // Get selected fund's available balance
  const selectedFund = funds.find(f => f.id == newAllocation.fund_id);
  const selectedFundAllocations = allocations.filter(a => a.fund_id == newAllocation.fund_id);
  const allocatedFromSelectedFund = selectedFundAllocations.reduce((sum, a) => sum + parseFloat(a.allocated_amount || 0), 0);
  const availableInSelectedFund = selectedFund ? parseFloat(selectedFund.current_balance || 0) - allocatedFromSelectedFund : 0;
  const projectedFundBalance = availableInSelectedFund - newAllocationAmount;

  const createFund = async () => {
    if (!newFund.fund_name.trim() || !newFund.initial_balance) {
      alert('Please fill all required fields');
      return;
    }

    if (parseFloat(newFund.initial_balance) > availableInBank) {
      alert(`Insufficient bank balance! Available: ${formatCurrency(availableInBank)}`);
      return;
    }

    try {
      const fundData = {
        fund_name: newFund.fund_name,
        description: newFund.description,
        initial_balance: parseFloat(newFund.initial_balance),
        fiscal_year: parseInt(newFund.fiscal_year)
      };
      
      const response = await fetch(`${API_BASE}/create_fund.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(fundData)
      });

      const data = await response.json();
      
      if (data.status === 'success') {
        alert('Fund created successfully!');
        setShowFundModal(false);
        setNewFund({
          fund_name: '',
          description: '',
          initial_balance: '',
          fiscal_year: new Date().getFullYear()
        });
        loadData();
      } else {
        alert('Error: ' + data.message);
      }
    } catch (error) {
      console.error('Error creating fund:', error);
      alert('Failed to create fund: ' + error.message);
    }
  };

  const createAllocation = async () => {
    if (!newAllocation.fund_id || !newAllocation.department.trim() || 
        !newAllocation.purpose.trim() || !newAllocation.allocated_amount) {
      alert('Please fill all required fields');
      return;
    }

    if (newAllocationAmount > availableInSelectedFund) {
      alert(`Insufficient fund balance! Available: ${formatCurrency(availableInSelectedFund)}`);
      return;
    }

    try {
      const allocationData = {
        fund_id: newAllocation.fund_id,
        department: newAllocation.department,
        purpose: newAllocation.purpose,
        allocated_amount: newAllocationAmount,
        allocated_date: newAllocation.allocated_date
      };
      
      const response = await fetch(`${API_BASE}/create_allocation.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(allocationData)
      });

      const data = await response.json();
      
      if (data.status === 'success') {
        alert('Allocation created successfully!');
        setShowAllocationModal(false);
        setNewAllocation({
          fund_id: '',
          department: '',
          purpose: '',
          allocated_amount: '',
          allocated_date: new Date().toISOString().split('T')[0]
        });
        loadData();
      } else {
        alert('Error: ' + data.message);
      }
    } catch (error) {
      console.error('Error creating allocation:', error);
      alert('Failed to create allocation: ' + error.message);
    }
  };

  // Edit Fund Functions
  const openEditFundModal = (fund) => {
    setSelectedItem(fund);
    setEditFund({
      id: fund.id,
      fund_name: fund.fund_name,
      description: fund.description || '',
      initial_balance: fund.initial_balance,
      fiscal_year: fund.fiscal_year
    });
    setShowEditFundModal(true);
  };

  const updateFund = async () => {
    if (!editFund.fund_name.trim() || !editFund.initial_balance) {
      alert('Please fill all required fields');
      return;
    }

    const originalFund = funds.find(f => f.id == editFund.id);
    const balanceChange = parseFloat(editFund.initial_balance) - parseFloat(originalFund.initial_balance);
    
    if (balanceChange > 0 && balanceChange > availableInBank) {
      alert(`Insufficient bank balance to increase fund! Additional needed: ${formatCurrency(balanceChange)}`);
      return;
    }

    try {
      const fundData = {
        id: editFund.id,
        fund_name: editFund.fund_name,
        description: editFund.description,
        initial_balance: parseFloat(editFund.initial_balance),
        fiscal_year: parseInt(editFund.fiscal_year)
      };
      
      const response = await fetch(`${API_BASE}/update_fund.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(fundData)
      });

      const data = await response.json();
      
      if (data.status === 'success') {
        alert('Fund updated successfully!');
        setShowEditFundModal(false);
        loadData();
      } else {
        alert('Error: ' + data.message);
      }
    } catch (error) {
      console.error('Error updating fund:', error);
      alert('Failed to update fund: ' + error.message);
    }
  };

  const deleteFund = async (fundId) => {
    if (!confirm('Are you sure you want to delete this fund? This action cannot be undone.')) {
      return;
    }

    // Check if fund has allocations
    const fundAllocations = allocations.filter(a => a.fund_id == fundId);
    if (fundAllocations.length > 0) {
      if (!confirm(`This fund has ${fundAllocations.length} allocation(s). Deleting will also remove these allocations. Proceed?`)) {
        return;
      }
    }

    try {
      const response = await fetch(`${API_BASE}/delete_fund.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: fundId })
      });

      const data = await response.json();
      
      if (data.status === 'success') {
        alert('Fund deleted successfully!');
        loadData();
      } else {
        alert('Error: ' + data.message);
      }
    } catch (error) {
      console.error('Error deleting fund:', error);
      alert('Failed to delete fund: ' + error.message);
    }
  };

  // Edit Allocation Functions
  const openEditAllocationModal = (allocation) => {
    setSelectedItem(allocation);
    setEditAllocation({
      id: allocation.id,
      fund_id: allocation.fund_id,
      department: allocation.department,
      purpose: allocation.purpose,
      allocated_amount: allocation.allocated_amount,
      allocated_date: allocation.allocated_date
    });
    setShowEditAllocationModal(true);
  };

  const updateAllocation = async () => {
    if (!editAllocation.fund_id || !editAllocation.department.trim() || 
        !editAllocation.purpose.trim() || !editAllocation.allocated_amount) {
      alert('Please fill all required fields');
      return;
    }

    const originalAllocation = allocations.find(a => a.id == editAllocation.id);
    const amountChange = parseFloat(editAllocation.allocated_amount) - parseFloat(originalAllocation.allocated_amount);
    
    if (amountChange > 0) {
      const selectedFund = funds.find(f => f.id == editAllocation.fund_id);
      const fundAllocations = allocations.filter(a => a.fund_id == selectedFund.id && a.id != editAllocation.id);
      const allocatedFromFund = fundAllocations.reduce((sum, a) => sum + parseFloat(a.allocated_amount || 0), 0);
      const availableInSelectedFund = parseFloat(selectedFund.current_balance || 0) - allocatedFromFund;
      
      if (amountChange > availableInSelectedFund) {
        alert(`Insufficient fund balance! Available: ${formatCurrency(availableInSelectedFund)}`);
        return;
      }
    }

    try {
      const allocationData = {
        id: editAllocation.id,
        fund_id: editAllocation.fund_id,
        department: editAllocation.department,
        purpose: editAllocation.purpose,
        allocated_amount: parseFloat(editAllocation.allocated_amount),
        allocated_date: editAllocation.allocated_date
      };
      
      const response = await fetch(`${API_BASE}/update_allocation.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(allocationData)
      });

      const data = await response.json();
      
      if (data.status === 'success') {
        alert('Allocation updated successfully!');
        setShowEditAllocationModal(false);
        loadData();
      } else {
        alert('Error: ' + data.message);
      }
    } catch (error) {
      console.error('Error updating allocation:', error);
      alert('Failed to update allocation: ' + error.message);
    }
  };

  const deleteAllocation = async (allocationId) => {
    if (!confirm('Are you sure you want to delete this allocation?')) {
      return;
    }

    try {
      const response = await fetch(`${API_BASE}/delete_allocation.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: allocationId })
      });

      const data = await response.json();
      
      if (data.status === 'success') {
        alert('Allocation deleted successfully!');
        loadData();
      } else {
        alert('Error: ' + data.message);
      }
    } catch (error) {
      console.error('Error deleting allocation:', error);
      alert('Failed to delete allocation: ' + error.message);
    }
  };

  const formatCurrency = (amount) => {
    return `₱${parseFloat(amount || 0).toLocaleString('en-PH', { 
      minimumFractionDigits: 2, 
      maximumFractionDigits: 2 
    })}`;
  };

  const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  // Get fund color based on usage percentage
  const getFundColor = (usagePercentage) => {
    if (usagePercentage > 90) return colors.danger;
    if (usagePercentage > 70) return colors.warning;
    return colors.primary;
  };

  if (loading) {
    return (
      <div className="p-6" style={{ backgroundColor: colors.background }}>
        <div className="text-center py-12">
          <div className="animate-spin rounded-full h-16 w-16 border-b-2 mx-auto mb-4" style={{ borderColor: colors.primary }}></div>
          <p className="font-medium" style={{ color: colors.textDark }}>Loading Treasury System</p>
        </div>
      </div>
    );
  }

  return (
    <div className="p-6 min-h-screen" style={{ backgroundColor: colors.background }}>
      <h1 className="text-2xl font-bold mb-6" style={{ color: colors.textDark }}>Treasury Fund Management</h1>
      
      {/* BANK ACCOUNT */}
      <div className="rounded-xl p-6 mb-6 shadow-lg" style={{ 
        background: `linear-gradient(135deg, ${colors.primary}, #357abd)`,
        color: 'white'
      }}>
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-lg font-bold mb-1">Government Funds</h2>
            <p className="opacity-90 text-sm mb-2">
              {bankAccount ? bankAccount.account_name : 'GovServ Bank'}
            </p>
            <p className="text-3xl font-bold">{formatCurrency(bankBalance)}</p>
            <p className="opacity-75 text-xs mt-2">
              Updated: {bankAccount ? formatDate(bankAccount.updated_at) : 'Never'}
            </p>
          </div>
        </div>
      </div>
      
      {/* REAL-TIME BALANCE INDICATOR */}
      <div className="mb-8 grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Bank Balance Section */}
        <div className="rounded-xl p-6 shadow" style={{ backgroundColor: 'white', border: `1px solid ${colors.secondary}20` }}>
          <div className="flex items-center justify-between mb-4">
            <div>
              <h3 className="font-bold" style={{ color: colors.textDark }}>Bank Balance</h3>
              <p className="text-sm" style={{ color: colors.textLight }}>Available for new funds</p>
            </div>
            <div className="text-2xl font-bold" style={{ color: colors.primary }}>
              {formatCurrency(availableInBank)}
            </div>
          </div>
          
          {showFundModal && newFundAmount > 0 && (
            <div className="mt-4 p-4 rounded-lg border" style={{ backgroundColor: '#ebf5ff', borderColor: colors.primary }}>
              <div className="flex items-center mb-2">
                <div className="w-3 h-3 rounded-full mr-2" style={{ backgroundColor: colors.primary }}></div>
                <span className="text-sm font-medium" style={{ color: colors.primary }}>New Fund Impact</span>
              </div>
              <div className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <span style={{ color: colors.textLight }}>Current Available:</span>
                  <span className="font-medium">{formatCurrency(availableInBank)}</span>
                </div>
                <div className="flex justify-between">
                  <span style={{ color: colors.textLight }}>New Fund Amount:</span>
                  <span className="font-medium" style={{ color: colors.danger }}>-{formatCurrency(newFundAmount)}</span>
                </div>
                <div className="border-t pt-2 flex justify-between" style={{ borderColor: `${colors.primary}40` }}>
                  <span className="font-medium" style={{ color: colors.textDark }}>After Creation:</span>
                  <span className={`font-bold ${projectedBankBalance >= 0 ? '' : ''}`} style={{ 
                    color: projectedBankBalance >= 0 ? colors.success : colors.danger 
                  }}>
                    {formatCurrency(projectedBankBalance)}
                  </span>
                </div>
                {projectedBankBalance < 0 && (
                  <div className="text-xs p-2 rounded mt-2" style={{ color: colors.danger, backgroundColor: '#fee2e2' }}>
                    ⚠️ Warning: This will overdraw the bank account
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
        
        {/* Funds Balance Section */}
        <div className="rounded-xl p-6 shadow" style={{ backgroundColor: 'white', border: `1px solid ${colors.secondary}20` }}>
          <div className="flex items-center justify-between mb-4">
            <div>
              <h3 className="font-bold" style={{ color: colors.textDark }}>Funds Balance</h3>
              <p className="text-sm" style={{ color: colors.textLight }}>Available for allocations</p>
            </div>
            <div className="text-2xl font-bold" style={{ color: colors.success }}>
              {formatCurrency(availableInFunds)}
            </div>
          </div>
          
          {showAllocationModal && selectedFund && newAllocationAmount > 0 && (
            <div className="mt-4 p-4 rounded-lg border" style={{ backgroundColor: '#f0fdf4', borderColor: colors.success }}>
              <div className="flex items-center mb-2">
                <div className="w-3 h-3 rounded-full mr-2" style={{ backgroundColor: colors.success }}></div>
                <span className="text-sm font-medium" style={{ color: colors.success }}>Allocation Impact</span>
              </div>
              <div className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <span style={{ color: colors.textLight }}>Selected Fund:</span>
                  <span className="font-medium">{selectedFund.fund_name}</span>
                </div>
                <div className="flex justify-between">
                  <span style={{ color: colors.textLight }}>Fund Available:</span>
                  <span className="font-medium">{formatCurrency(availableInSelectedFund)}</span>
                </div>
                <div className="flex justify-between">
                  <span style={{ color: colors.textLight }}>Allocation Amount:</span>
                  <span className="font-medium" style={{ color: colors.danger }}>-{formatCurrency(newAllocationAmount)}</span>
                </div>
                <div className="border-t pt-2 flex justify-between" style={{ borderColor: `${colors.success}40` }}>
                  <span className="font-medium" style={{ color: colors.textDark }}>After Allocation:</span>
                  <span className={`font-bold ${projectedFundBalance >= 0 ? '' : ''}`} style={{ 
                    color: projectedFundBalance >= 0 ? colors.success : colors.danger 
                  }}>
                    {formatCurrency(projectedFundBalance)}
                  </span>
                </div>
                {projectedFundBalance < 0 && (
                  <div className="text-xs p-2 rounded mt-2" style={{ color: colors.danger, backgroundColor: '#fee2e2' }}>
                    ⚠️ Warning: This will exceed available fund balance
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
      
      {/* FINANCIAL SUMMARY */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div className="rounded-xl p-4 shadow hover:shadow-md transition-shadow" style={{ 
          backgroundColor: 'white', 
          border: `1px solid ${colors.secondary}20`
        }}>
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm" style={{ color: colors.textLight }}>Total in Funds</p>
              <p className="text-2xl font-bold" style={{ color: colors.success }}>{formatCurrency(totalFunds)}</p>
            </div>
            <div className="p-2 rounded-full" style={{ backgroundColor: `${colors.success}15` }}>
              <span style={{ color: colors.success, fontWeight: 'bold' }}>{funds.length}</span>
            </div>
          </div>
          <p className="text-xs mt-2" style={{ color: colors.secondary }}>From {funds.length} funds</p>
        </div>
        
        <div className="rounded-xl p-4 shadow hover:shadow-md transition-shadow" style={{ 
          backgroundColor: 'white', 
          border: `1px solid ${colors.secondary}20`
        }}>
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm" style={{ color: colors.textLight }}>Total Allocated</p>
              <p className="text-2xl font-bold" style={{ color: colors.purple }}>{formatCurrency(totalAllocated)}</p>
            </div>
            <div className="p-2 rounded-full" style={{ backgroundColor: `${colors.purple}15` }}>
              <span style={{ color: colors.purple, fontWeight: 'bold' }}>{allocations.length}</span>
            </div>
          </div>
          <p className="text-xs mt-2" style={{ color: colors.secondary }}>To departments</p>
        </div>
        
        <div className="rounded-xl p-4 shadow hover:shadow-md transition-shadow" style={{ 
          backgroundColor: 'white', 
          border: `1px solid ${colors.secondary}20`
        }}>
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm" style={{ color: colors.textLight }}>Estimated Available in Bank</p>
              <p className={`text-2xl font-bold ${availableInBank >= 0 ? '' : ''}`} style={{ 
                color: availableInBank >= 0 ? colors.primary : colors.danger 
              }}>
                {formatCurrency(availableInBank)}
              </p>
            </div>
            <div className={`p-2 rounded-full ${availableInBank >= 0 ? '' : ''}`} style={{ 
              backgroundColor: availableInBank >= 0 ? `${colors.primary}15` : `${colors.danger}15` 
            }}>
              <svg className="w-5 h-5" fill={availableInBank >= 0 ? colors.primary : colors.danger} viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clipRule="evenodd" />
              </svg>
            </div>
          </div>
          <p className="text-xs mt-2" style={{ color: colors.secondary }}>For new funds</p>
        </div>
        
        <div className="rounded-xl p-4 shadow hover:shadow-md transition-shadow" style={{ 
          backgroundColor: 'white', 
          border: `1px solid ${colors.secondary}20`
        }}>
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm" style={{ color: colors.textLight }}>Available in Funds</p>
              <p className={`text-2xl font-bold ${availableInFunds >= 0 ? '' : ''}`} style={{ 
                color: availableInFunds >= 0 ? colors.success : colors.danger 
              }}>
                {formatCurrency(availableInFunds)}
              </p>
            </div>
            <div className={`p-2 rounded-full ${availableInFunds >= 0 ? '' : ''}`} style={{ 
              backgroundColor: availableInFunds >= 0 ? `${colors.success}15` : `${colors.danger}15` 
            }}>
              <svg className="w-5 h-5" fill={availableInFunds >= 0 ? colors.success : colors.danger} viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clipRule="evenodd" />
              </svg>
            </div>
          </div>
          <p className="text-xs mt-2" style={{ color: colors.secondary }}>For new allocations</p>
        </div>
      </div>
      
      {/* FUNDS SECTION */}
      <div className="mb-8">
        <div className="flex justify-between items-center mb-4">
          <div>
            <h2 className="text-xl font-bold" style={{ color: colors.textDark }}>Funds</h2>
            <p className="text-sm" style={{ color: colors.textLight }}>Manage financial funds for allocation</p>
          </div>
          <button 
            onClick={() => setShowFundModal(true)}
            className="px-4 py-2 rounded-lg flex items-center shadow hover:shadow-md transition-all"
            style={{ backgroundColor: colors.success, color: 'white' }}
          >
            <span className="mr-2">+</span> Create Fund
          </button>
        </div>
        
        {funds.length === 0 ? (
          <div className="text-center py-12 rounded-xl border-2 border-dashed hover:border-green-400 transition-colors" 
               style={{ backgroundColor: colors.background, borderColor: colors.secondary }}>
            <div className="mb-3" style={{ color: colors.secondary }}>
              <svg className="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <p style={{ color: colors.textDark }}>No funds created yet</p>
            <p className="text-sm mt-1" style={{ color: colors.textLight }}>Create your first fund to start allocating resources</p>
            <button 
              onClick={() => setShowFundModal(true)}
              className="mt-4 px-4 py-2 rounded-lg shadow"
              style={{ backgroundColor: colors.success, color: 'white' }}
            >
              Create First Fund
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {funds.map(fund => {
              const fundAllocations = allocations.filter(a => a.fund_id == fund.id);
              const allocatedFromFund = fundAllocations.reduce((sum, a) => sum + parseFloat(a.allocated_amount || 0), 0);
              const availableInThisFund = parseFloat(fund.current_balance || 0) - allocatedFromFund;
              const usagePercentage = fund.initial_balance > 0 ? (allocatedFromFund / fund.initial_balance) * 100 : 0;
              const fundColor = getFundColor(usagePercentage);
              
              return (
                <div key={fund.id} className="rounded-xl p-5 shadow-sm hover:shadow-md transition-all group" 
                     style={{ 
                       backgroundColor: 'white', 
                       border: `1px solid ${colors.secondary}20`,
                       borderLeft: `4px solid ${fundColor}`
                     }}>
                  <div className="flex justify-between items-start mb-3">
                    <div className="flex-1">
                      <h3 className="font-bold text-lg" style={{ color: colors.textDark }}>{fund.fund_name}</h3>
                      <p className="text-xs mt-1" style={{ color: colors.textLight }}>{fund.description || 'No description'}</p>
                    </div>
                    <div className="flex space-x-2">
                      <span className="text-xs px-2 py-1 rounded-full whitespace-nowrap" style={{ 
                        backgroundColor: `${colors.primary}15`, 
                        color: colors.primary 
                      }}>
                        FY {fund.fiscal_year}
                      </span>
                      <div className="opacity-0 group-hover:opacity-100 transition-opacity flex space-x-1">
                        <button
                          onClick={() => openEditFundModal(fund)}
                          className="p-1 hover:bg-gray-100 rounded"
                          title="Edit Fund"
                          style={{ color: colors.primary }}
                        >
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                          </svg>
                        </button>
                        <button
                          onClick={() => deleteFund(fund.id)}
                          className="p-1 hover:bg-gray-100 rounded"
                          title="Delete Fund"
                          style={{ color: colors.danger }}
                        >
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                      </div>
                    </div>
                  </div>
                  
                  {/* Progress bar */}
                  <div className="mb-4">
                    <div className="flex justify-between text-xs mb-1" style={{ color: colors.textLight }}>
                      <span>Usage: {usagePercentage.toFixed(1)}%</span>
                      <span>{formatCurrency(allocatedFromFund)} / {formatCurrency(fund.initial_balance)}</span>
                    </div>
                    <div className="w-full rounded-full h-2" style={{ backgroundColor: `${colors.secondary}20` }}>
                      <div 
                        className="h-2 rounded-full"
                        style={{ 
                          width: `${Math.min(usagePercentage, 100)}%`,
                          backgroundColor: fundColor
                        }}
                      ></div>
                    </div>
                  </div>
                  
                  <div className="space-y-3">
                    <div className="flex justify-between items-center">
                      <span style={{ color: colors.textLight }}>Initial:</span>
                      <span className="font-bold" style={{ color: colors.textDark }}>{formatCurrency(fund.initial_balance)}</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <span style={{ color: colors.textLight }}>Allocated:</span>
                      <span className="font-bold" style={{ color: colors.purple }}>{formatCurrency(allocatedFromFund)}</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <span style={{ color: colors.textLight }}>Available:</span>
                      <span className={`font-bold ${availableInThisFund >= 0 ? '' : ''}`} style={{ 
                        color: availableInThisFund >= 0 ? colors.success : colors.danger 
                      }}>
                        {formatCurrency(availableInThisFund)}
                      </span>
                    </div>
                  </div>
                  
                  <div className="mt-5 pt-4" style={{ borderTop: `1px solid ${colors.secondary}20` }}>
                    <div className="flex justify-between text-xs" style={{ color: colors.textLight }}>
                      <span>{fundAllocations.length} allocations</span>
                      <span>Created: {formatDate(fund.created_at)}</span>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
      
      {/* ALLOCATIONS SECTION */}
      <div>
        <div className="flex justify-between items-center mb-4">
          <div>
            <h2 className="text-xl font-bold" style={{ color: colors.textDark }}>Allocations</h2>
            <p className="text-sm" style={{ color: colors.textLight }}>Fund allocations to departments</p>
          </div>
          <button 
            onClick={() => setShowAllocationModal(true)}
            disabled={funds.length === 0}
            className={`px-4 py-2 rounded-lg flex items-center shadow transition-all ${
              funds.length === 0 
                ? 'cursor-not-allowed shadow-none' 
                : 'hover:shadow-md'
            }`}
            style={{ 
              backgroundColor: funds.length === 0 ? colors.secondary : colors.primary,
              color: 'white'
            }}
          >
            <span className="mr-2">+</span> New Allocation
          </button>
        </div>
        
        {allocations.length === 0 ? (
          <div className="text-center py-12 rounded-xl border-2 border-dashed transition-colors" 
               style={{ 
                 backgroundColor: colors.background, 
                 borderColor: colors.secondary,
                 borderStyle: 'dashed'
               }}>
            <div className="mb-3" style={{ color: colors.secondary }}>
              <svg className="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
            <p style={{ color: colors.textDark }}>No allocations yet</p>
            <p className="text-sm mt-1" style={{ color: colors.textLight }}>
              {funds.length === 0 
                ? 'Create funds first, then allocate to departments' 
                : 'Start allocating funds to departments'}
            </p>
          </div>
        ) : (
          <div className="rounded-xl overflow-hidden shadow" style={{ backgroundColor: 'white', border: `1px solid ${colors.secondary}20` }}>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr style={{ backgroundColor: `${colors.background}` }}>
                    <th className="p-4 text-left text-sm font-medium" style={{ color: colors.textDark }}>Department</th>
                    <th className="p-4 text-left text-sm font-medium" style={{ color: colors.textDark }}>Purpose</th>
                    <th className="p-4 text-left text-sm font-medium" style={{ color: colors.textDark }}>Amount</th>
                    <th className="p-4 text-left text-sm font-medium" style={{ color: colors.textDark }}>Date</th>
                    <th className="p-4 text-left text-sm font-medium" style={{ color: colors.textDark }}>Fund</th>
                    <th className="p-4 text-left text-sm font-medium" style={{ color: colors.textDark }}>Status</th>
                    <th className="p-4 text-left text-sm font-medium" style={{ color: colors.textDark }}>Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y" style={{ divideColor: `${colors.secondary}20` }}>
                  {allocations.map(alloc => {
                    const fund = funds.find(f => f.id == alloc.fund_id);
                    const fundAllocations = allocations.filter(a => a.fund_id == alloc.fund_id);
                    const allocatedFromFund = fundAllocations.reduce((sum, a) => sum + parseFloat(a.allocated_amount || 0), 0);
                    const availableInFund = fund ? parseFloat(fund.current_balance || 0) - allocatedFromFund : 0;
                    const isOverallocated = availableInFund < 0;
                    
                    return (
                      <tr key={alloc.id} className="hover:bg-gray-50 transition-colors">
                        <td className="p-4">
                          <div className="font-medium" style={{ color: colors.textDark }}>{alloc.department}</div>
                        </td>
                        <td className="p-4">
                          <div className="text-sm max-w-xs" style={{ color: colors.textLight }}>{alloc.purpose}</div>
                        </td>
                        <td className="p-4">
                          <div className="font-bold" style={{ color: colors.success }}>{formatCurrency(alloc.allocated_amount)}</div>
                        </td>
                        <td className="p-4">
                          <div className="text-sm" style={{ color: colors.textLight }}>{alloc.allocated_date}</div>
                        </td>
                        <td className="p-4">
                          <span className="text-xs px-3 py-1 rounded-full" style={{ 
                            backgroundColor: `${colors.primary}15`, 
                            color: colors.primary 
                          }}>
                            {fund?.fund_name || 'Unknown Fund'}
                          </span>
                        </td>
                        <td className="p-4">
                          <span className={`text-xs px-3 py-1 rounded-full ${
                            isOverallocated ? '' : ''
                          }`} style={{ 
                            backgroundColor: isOverallocated ? `${colors.danger}15` : `${colors.success}15`,
                            color: isOverallocated ? colors.danger : colors.success
                          }}>
                            {isOverallocated ? 'Over Budget' : 'Active'}
                          </span>
                        </td>
                        <td className="p-4">
                          <div className="flex space-x-2">
                            <button
                              onClick={() => openEditAllocationModal(alloc)}
                              className="p-1 hover:bg-gray-100 rounded"
                              title="Edit Allocation"
                              style={{ color: colors.primary }}
                            >
                              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                              </svg>
                            </button>
                            <button
                              onClick={() => deleteAllocation(alloc.id)}
                              className="p-1 hover:bg-gray-100 rounded"
                              title="Delete Allocation"
                              style={{ color: colors.danger }}
                            >
                              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                              </svg>
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>
      
      {/* MODAL FOR CREATING FUND */}
      {showFundModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="rounded-xl max-w-lg w-full max-h-[90vh] overflow-y-auto" style={{ backgroundColor: 'white' }}>
            <div className="p-6">
              <div className="flex justify-between items-center mb-6">
                <h3 className="text-xl font-bold" style={{ color: colors.textDark }}>Create New Fund</h3>
                <button 
                  onClick={() => setShowFundModal(false)}
                  style={{ color: colors.textLight }}
                >
                  ✕
                </button>
              </div>
              
              {/* Balance Preview Card */}
              <div className="mb-6 p-4 rounded-lg border" style={{ backgroundColor: '#ebf5ff', borderColor: colors.primary }}>
                <div className="flex items-center mb-2">
                  <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" style={{ color: colors.primary }}>
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span className="font-medium" style={{ color: colors.primary }}>Balance Impact Preview</span>
                </div>
                <div className="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <p style={{ color: colors.textLight }}>Current Bank Available:</p>
                    <p className="font-bold" style={{ color: colors.primary }}>{formatCurrency(availableInBank)}</p>
                  </div>
                  <div>
                    <p style={{ color: colors.textLight }}>New Fund Amount:</p>
                    <p className="font-bold" style={{ color: colors.textDark }}>{newFundAmount > 0 ? formatCurrency(newFundAmount) : '₱0.00'}</p>
                  </div>
                  <div className="col-span-2 pt-2 border-t" style={{ borderColor: `${colors.primary}40` }}>
                    <div className="flex justify-between items-center">
                      <p className="font-medium" style={{ color: colors.textDark }}>After Creation:</p>
                      <p className={`text-xl font-bold ${
                        projectedBankBalance >= 0 ? '' : ''
                      }`} style={{ 
                        color: projectedBankBalance >= 0 ? colors.success : colors.danger 
                      }}>
                        {formatCurrency(projectedBankBalance)}
                      </p>
                    </div>
                    {projectedBankBalance < 0 && (
                      <div className="mt-2 text-xs p-2 rounded" style={{ color: colors.danger, backgroundColor: '#fee2e2' }}>
                        ⚠️ Warning: This will overdraw the bank account
                      </div>
                    )}
                  </div>
                </div>
              </div>
              
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Fund Name *
                  </label>
                  <input
                    type="text"
                    className="w-full p-3 border rounded-lg focus:ring-2 focus:border-blue-500"
                    style={{ 
                      borderColor: colors.secondary,
                      outline: 'none'
                    }}
                    placeholder="e.g., General Fund, Special Education Fund"
                    value={newFund.fund_name}
                    onChange={(e) => setNewFund({...newFund, fund_name: e.target.value})}
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Description
                  </label>
                  <textarea
                    className="w-full p-3 border rounded-lg focus:ring-2 focus:border-blue-500"
                    style={{ 
                      borderColor: colors.secondary,
                      outline: 'none'
                    }}
                    rows="3"
                    placeholder="Purpose of this fund..."
                    value={newFund.description}
                    onChange={(e) => setNewFund({...newFund, description: e.target.value})}
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Initial Balance *
                  </label>
                  <div className="relative">
                    <span className="absolute left-3 top-3" style={{ color: colors.textLight }}>₱</span>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      className="w-full p-3 pl-8 border rounded-lg focus:ring-2 focus:border-blue-500"
                      style={{ 
                        borderColor: colors.secondary,
                        outline: 'none'
                      }}
                      placeholder="0.00"
                      value={newFund.initial_balance}
                      onChange={(e) => setNewFund({...newFund, initial_balance: e.target.value})}
                    />
                  </div>
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Fiscal Year
                  </label>
                  <input
                    type="number"
                    className="w-full p-3 border rounded-lg focus:ring-2 focus:border-blue-500"
                    style={{ 
                      borderColor: colors.secondary,
                      outline: 'none'
                    }}
                    value={newFund.fiscal_year}
                    onChange={(e) => setNewFund({...newFund, fiscal_year: e.target.value})}
                  />
                </div>
              </div>
              
              <div className="flex justify-end space-x-3 mt-8 pt-6" style={{ borderTop: `1px solid ${colors.secondary}20` }}>
                <button
                  onClick={() => setShowFundModal(false)}
                  className="px-4 py-2 border rounded-lg transition-colors"
                  style={{ 
                    borderColor: colors.secondary,
                    color: colors.textDark
                  }}
                >
                  Cancel
                </button>
                <button
                  onClick={createFund}
                  disabled={!newFund.fund_name.trim() || !newFund.initial_balance}
                  className={`px-4 py-2 rounded-lg transition-colors ${
                    !newFund.fund_name.trim() || !newFund.initial_balance
                      ? '' : ''
                  }`}
                  style={{ 
                    backgroundColor: !newFund.fund_name.trim() || !newFund.initial_balance 
                      ? colors.secondary 
                      : colors.success,
                    color: 'white'
                  }}
                >
                  Create Fund
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
      
      {/* MODAL FOR EDITING FUND */}
      {showEditFundModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="rounded-xl max-w-lg w-full max-h-[90vh] overflow-y-auto" style={{ backgroundColor: 'white' }}>
            <div className="p-6">
              <div className="flex justify-between items-center mb-6">
                <h3 className="text-xl font-bold" style={{ color: colors.textDark }}>Edit Fund</h3>
                <button 
                  onClick={() => setShowEditFundModal(false)}
                  style={{ color: colors.textLight }}
                >
                  ✕
                </button>
              </div>
              
              {/* Balance Preview Card */}
              {selectedItem && (
                <div className="mb-6 p-4 rounded-lg border" style={{ backgroundColor: '#fffbeb', borderColor: colors.warning }}>
                  <div className="flex items-center mb-2">
                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" style={{ color: colors.warning }}>
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span className="font-medium" style={{ color: colors.warning }}>Balance Change Preview</span>
                  </div>
                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <p style={{ color: colors.textLight }}>Original Amount:</p>
                      <p className="font-bold" style={{ color: colors.textDark }}>{formatCurrency(selectedItem.initial_balance)}</p>
                    </div>
                    <div>
                      <p style={{ color: colors.textLight }}>New Amount:</p>
                      <p className="font-bold" style={{ color: colors.textDark }}>{editFund.initial_balance ? formatCurrency(editFund.initial_balance) : '₱0.00'}</p>
                    </div>
                    <div className="col-span-2 pt-2 border-t" style={{ borderColor: `${colors.warning}40` }}>
                      <div className="flex justify-between items-center">
                        <p className="font-medium" style={{ color: colors.textDark }}>Change:</p>
                        <p className={`text-xl font-bold ${
                          parseFloat(editFund.initial_balance || 0) > parseFloat(selectedItem.initial_balance) 
                            ? '' 
                            : ''
                        }`} style={{ 
                          color: parseFloat(editFund.initial_balance || 0) > parseFloat(selectedItem.initial_balance) 
                            ? colors.danger 
                            : colors.success
                        }}>
                          {formatCurrency(parseFloat(editFund.initial_balance || 0) - parseFloat(selectedItem.initial_balance))}
                        </p>
                      </div>
                      {parseFloat(editFund.initial_balance || 0) > parseFloat(selectedItem.initial_balance) && 
                       (parseFloat(editFund.initial_balance) - parseFloat(selectedItem.initial_balance)) > availableInBank && (
                        <div className="mt-2 text-xs p-2 rounded" style={{ color: colors.danger, backgroundColor: '#fee2e2' }}>
                          ⚠️ Warning: Insufficient bank balance for this increase
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              )}
              
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Fund Name *
                  </label>
                  <input
                    type="text"
                    className="w-full p-3 border rounded-lg focus:ring-2 focus:border-blue-500"
                    style={{ 
                      borderColor: colors.secondary,
                      outline: 'none'
                    }}
                    placeholder="e.g., General Fund, Special Education Fund"
                    value={editFund.fund_name}
                    onChange={(e) => setEditFund({...editFund, fund_name: e.target.value})}
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Description
                  </label>
                  <textarea
                    className="w-full p-3 border rounded-lg focus:ring-2 focus:border-blue-500"
                    style={{ 
                      borderColor: colors.secondary,
                      outline: 'none'
                    }}
                    rows="3"
                    placeholder="Purpose of this fund..."
                    value={editFund.description}
                    onChange={(e) => setEditFund({...editFund, description: e.target.value})}
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Initial Balance *
                  </label>
                  <div className="relative">
                    <span className="absolute left-3 top-3" style={{ color: colors.textLight }}>₱</span>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      className="w-full p-3 pl-8 border rounded-lg focus:ring-2 focus:border-blue-500"
                      style={{ 
                        borderColor: colors.secondary,
                        outline: 'none'
                      }}
                      placeholder="0.00"
                      value={editFund.initial_balance}
                      onChange={(e) => setEditFund({...editFund, initial_balance: e.target.value})}
                    />
                  </div>
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Fiscal Year
                  </label>
                  <input
                    type="number"
                    className="w-full p-3 border rounded-lg focus:ring-2 focus:border-blue-500"
                    style={{ 
                      borderColor: colors.secondary,
                      outline: 'none'
                    }}
                    value={editFund.fiscal_year}
                    onChange={(e) => setEditFund({...editFund, fiscal_year: e.target.value})}
                  />
                </div>
              </div>
              
              <div className="flex justify-between mt-8 pt-6" style={{ borderTop: `1px solid ${colors.secondary}20` }}>
                <button
                  onClick={() => deleteFund(editFund.id)}
                  className="px-4 py-2 border rounded-lg transition-colors"
                  style={{ 
                    borderColor: colors.danger,
                    color: colors.danger
                  }}
                >
                  Delete
                </button>
                <div className="flex space-x-3">
                  <button
                    onClick={() => setShowEditFundModal(false)}
                    className="px-4 py-2 border rounded-lg transition-colors"
                    style={{ 
                      borderColor: colors.secondary,
                      color: colors.textDark
                    }}
                  >
                    Cancel
                  </button>
                  <button
                    onClick={updateFund}
                    disabled={!editFund.fund_name.trim() || !editFund.initial_balance}
                    className={`px-4 py-2 rounded-lg transition-colors ${
                      !editFund.fund_name.trim() || !editFund.initial_balance
                        ? '' : ''
                    }`}
                    style={{ 
                      backgroundColor: !editFund.fund_name.trim() || !editFund.initial_balance 
                        ? colors.secondary 
                        : colors.primary,
                      color: 'white'
                    }}
                  >
                    Update Fund
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
      
      {/* MODAL FOR CREATING ALLOCATION */}
      {showAllocationModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="rounded-xl max-w-lg w-full max-h-[90vh] overflow-y-auto" style={{ backgroundColor: 'white' }}>
            <div className="p-6">
              <div className="flex justify-between items-center mb-6">
                <h3 className="text-xl font-bold" style={{ color: colors.textDark }}>Create Allocation</h3>
                <button 
                  onClick={() => setShowAllocationModal(false)}
                  style={{ color: colors.textLight }}
                >
                  ✕
                </button>
              </div>
              
              {/* Selected Fund Balance Preview */}
              {selectedFund && (
                <div className="mb-6 p-4 rounded-lg border" style={{ backgroundColor: '#f0fdf4', borderColor: colors.success }}>
                  <div className="flex items-center mb-2">
                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" style={{ color: colors.success }}>
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span className="font-medium" style={{ color: colors.success }}>Selected Fund: {selectedFund.fund_name}</span>
                  </div>
                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <p style={{ color: colors.textLight }}>Fund Balance:</p>
                      <p className="font-bold" style={{ color: colors.success }}>{formatCurrency(availableInSelectedFund)}</p>
                    </div>
                    <div>
                      <p style={{ color: colors.textLight }}>Allocation Amount:</p>
                      <p className="font-bold" style={{ color: colors.textDark }}>{newAllocationAmount > 0 ? formatCurrency(newAllocationAmount) : '₱0.00'}</p>
                    </div>
                    <div className="col-span-2 pt-2 border-t" style={{ borderColor: `${colors.success}40` }}>
                      <div className="flex justify-between items-center">
                        <p className="font-medium" style={{ color: colors.textDark }}>After Allocation:</p>
                        <p className={`text-xl font-bold ${
                          projectedFundBalance >= 0 ? '' : ''
                        }`} style={{ 
                          color: projectedFundBalance >= 0 ? colors.success : colors.danger 
                        }}>
                          {formatCurrency(projectedFundBalance)}
                        </p>
                      </div>
                      {projectedFundBalance < 0 && (
                        <div className="mt-2 text-xs p-2 rounded" style={{ color: colors.danger, backgroundColor: '#fee2e2' }}>
                          ⚠️ Warning: This will exceed available fund balance
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              )}
              
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Select Fund *
                  </label>
                  <select
                    className="w-full p-3 border rounded-lg focus:ring-2 focus:border-blue-500"
                    style={{ 
                      borderColor: colors.secondary,
                      outline: 'none'
                    }}
                    value={newAllocation.fund_id}
                    onChange={(e) => setNewAllocation({...newAllocation, fund_id: e.target.value})}
                  >
                    <option value="">Select a fund</option>
                    {funds.map(fund => {
                      const fundAllocations = allocations.filter(a => a.fund_id == fund.id);
                      const allocatedFromFund = fundAllocations.reduce((sum, a) => sum + parseFloat(a.allocated_amount || 0), 0);
                      const availableInSelectedFund = parseFloat(fund.current_balance || 0) - allocatedFromFund;
                      
                      return (
                        <option key={fund.id} value={fund.id}>
                          {fund.fund_name} (Available: {formatCurrency(availableInSelectedFund)})
                        </option>
                      );
                    })}
                  </select>
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Department *
                  </label>
                  <input
                    type="text"
                    className="w-full p-3 border rounded-lg focus:ring-2 focus:border-blue-500"
                    style={{ 
                      borderColor: colors.secondary,
                      outline: 'none'
                    }}
                    placeholder="e.g., Health Department, Engineering Office"
                    value={newAllocation.department}
                    onChange={(e) => setNewAllocation({...newAllocation, department: e.target.value})}
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Purpose *
                  </label>
                  <textarea
                    className="w-full p-3 border rounded-lg focus:ring-2 focus:border-blue-500"
                    style={{ 
                      borderColor: colors.secondary,
                      outline: 'none'
                    }}
                    rows="3"
                    placeholder="Purpose of this allocation..."
                    value={newAllocation.purpose}
                    onChange={(e) => setNewAllocation({...newAllocation, purpose: e.target.value})}
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Amount *
                  </label>
                  <div className="relative">
                    <span className="absolute left-3 top-3" style={{ color: colors.textLight }}>₱</span>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      className="w-full p-3 pl-8 border rounded-lg focus:ring-2 focus:border-blue-500"
                      style={{ 
                        borderColor: colors.secondary,
                        outline: 'none'
                      }}
                      placeholder="0.00"
                      value={newAllocation.allocated_amount}
                      onChange={(e) => setNewAllocation({...newAllocation, allocated_amount: e.target.value})}
                    />
                  </div>
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Allocation Date
                  </label>
                  <input
                    type="date"
                    className="w-full p-3 border rounded-lg focus:ring-2 focus:border-blue-500"
                    style={{ 
                      borderColor: colors.secondary,
                      outline: 'none'
                    }}
                    value={newAllocation.allocated_date}
                    onChange={(e) => setNewAllocation({...newAllocation, allocated_date: e.target.value})}
                  />
                </div>
              </div>
              
              <div className="flex justify-end space-x-3 mt-8 pt-6" style={{ borderTop: `1px solid ${colors.secondary}20` }}>
                <button
                  onClick={() => setShowAllocationModal(false)}
                  className="px-4 py-2 border rounded-lg transition-colors"
                  style={{ 
                    borderColor: colors.secondary,
                    color: colors.textDark
                  }}
                >
                  Cancel
                </button>
                <button
                  onClick={createAllocation}
                  disabled={!newAllocation.fund_id || !newAllocation.department.trim() || 
                           !newAllocation.purpose.trim() || !newAllocation.allocated_amount}
                  className={`px-4 py-2 rounded-lg transition-colors ${
                    !newAllocation.fund_id || !newAllocation.department.trim() || 
                    !newAllocation.purpose.trim() || !newAllocation.allocated_amount
                      ? '' : ''
                  }`}
                  style={{ 
                    backgroundColor: !newAllocation.fund_id || !newAllocation.department.trim() || 
                    !newAllocation.purpose.trim() || !newAllocation.allocated_amount
                      ? colors.secondary 
                      : colors.primary,
                    color: 'white'
                  }}
                >
                  Create Allocation
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
      
      {/* MODAL FOR EDITING ALLOCATION */}
      {showEditAllocationModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="rounded-xl max-w-lg w-full max-h-[90vh] overflow-y-auto" style={{ backgroundColor: 'white' }}>
            <div className="p-6">
              <div className="flex justify-between items-center mb-6">
                <h3 className="text-xl font-bold" style={{ color: colors.textDark }}>Edit Allocation</h3>
                <button 
                  onClick={() => setShowEditAllocationModal(false)}
                  style={{ color: colors.textLight }}
                >
                  ✕
                </button>
              </div>
              
              {/* Selected Fund Balance Preview */}
              {selectedItem && (
                <div className="mb-6 p-4 rounded-lg border" style={{ backgroundColor: '#fffbeb', borderColor: colors.warning }}>
                  <div className="flex items-center mb-2">
                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" style={{ color: colors.warning }}>
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span className="font-medium" style={{ color: colors.warning }}>Allocation Change Preview</span>
                  </div>
                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <p style={{ color: colors.textLight }}>Original Amount:</p>
                      <p className="font-bold" style={{ color: colors.textDark }}>{formatCurrency(selectedItem.allocated_amount)}</p>
                    </div>
                    <div>
                      <p style={{ color: colors.textLight }}>New Amount:</p>
                      <p className="font-bold" style={{ color: colors.textDark }}>{editAllocation.allocated_amount ? formatCurrency(editAllocation.allocated_amount) : '₱0.00'}</p>
                    </div>
                    <div className="col-span-2 pt-2 border-t" style={{ borderColor: `${colors.warning}40` }}>
                      <div className="flex justify-between items-center">
                        <p className="font-medium" style={{ color: colors.textDark }}>Change:</p>
                        <p className={`text-xl font-bold ${
                          parseFloat(editAllocation.allocated_amount || 0) > parseFloat(selectedItem.allocated_amount) 
                            ? '' 
                            : ''
                        }`} style={{ 
                          color: parseFloat(editAllocation.allocated_amount || 0) > parseFloat(selectedItem.allocated_amount) 
                            ? colors.danger 
                            : colors.success
                        }}>
                          {formatCurrency(parseFloat(editAllocation.allocated_amount || 0) - parseFloat(selectedItem.allocated_amount))}
                        </p>
                      </div>
                      {parseFloat(editAllocation.allocated_amount || 0) > parseFloat(selectedItem.allocated_amount) && (
                        <div className="mt-2 text-xs p-2 rounded" style={{ color: colors.primary, backgroundColor: '#ebf5ff' }}>
                          ⓘ Additional funds will be deducted from the selected fund
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              )}
              
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Select Fund *
                  </label>
                  <select
                    className="w-full p-3 border rounded-lg focus:ring-2 focus:border-blue-500"
                    style={{ 
                      borderColor: colors.secondary,
                      outline: 'none'
                    }}
                    value={editAllocation.fund_id}
                    onChange={(e) => setEditAllocation({...editAllocation, fund_id: e.target.value})}
                  >
                    <option value="">Select a fund</option>
                    {funds.map(fund => {
                      const fundAllocations = allocations.filter(a => a.fund_id == fund.id && a.id != editAllocation.id);
                      const allocatedFromFund = fundAllocations.reduce((sum, a) => sum + parseFloat(a.allocated_amount || 0), 0);
                      const availableInSelectedFund = parseFloat(fund.current_balance || 0) - allocatedFromFund;
                      
                      return (
                        <option key={fund.id} value={fund.id}>
                          {fund.fund_name} (Available: {formatCurrency(availableInSelectedFund)})
                        </option>
                      );
                    })}
                  </select>
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Department *
                  </label>
                  <input
                    type="text"
                    className="w-full p-3 border rounded-lg focus:ring-2 focus:border-blue-500"
                    style={{ 
                      borderColor: colors.secondary,
                      outline: 'none'
                    }}
                    placeholder="e.g., Health Department, Engineering Office"
                    value={editAllocation.department}
                    onChange={(e) => setEditAllocation({...editAllocation, department: e.target.value})}
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Purpose *
                  </label>
                  <textarea
                    className="w-full p-3 border rounded-lg focus:ring-2 focus:border-blue-500"
                    style={{ 
                      borderColor: colors.secondary,
                      outline: 'none'
                    }}
                    rows="3"
                    placeholder="Purpose of this allocation..."
                    value={editAllocation.purpose}
                    onChange={(e) => setEditAllocation({...editAllocation, purpose: e.target.value})}
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Amount *
                  </label>
                  <div className="relative">
                    <span className="absolute left-3 top-3" style={{ color: colors.textLight }}>₱</span>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      className="w-full p-3 pl-8 border rounded-lg focus:ring-2 focus:border-blue-500"
                      style={{ 
                        borderColor: colors.secondary,
                        outline: 'none'
                      }}
                      placeholder="0.00"
                      value={editAllocation.allocated_amount}
                      onChange={(e) => setEditAllocation({...editAllocation, allocated_amount: e.target.value})}
                    />
                  </div>
                </div>
                
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: colors.textDark }}>
                    Allocation Date
                  </label>
                  <input
                    type="date"
                    className="w-full p-3 border rounded-lg focus:ring-2 focus:border-blue-500"
                    style={{ 
                      borderColor: colors.secondary,
                      outline: 'none'
                    }}
                    value={editAllocation.allocated_date}
                    onChange={(e) => setEditAllocation({...editAllocation, allocated_date: e.target.value})}
                  />
                </div>
              </div>
              
              <div className="flex justify-between mt-8 pt-6" style={{ borderTop: `1px solid ${colors.secondary}20` }}>
                <button
                  onClick={() => deleteAllocation(editAllocation.id)}
                  className="px-4 py-2 border rounded-lg transition-colors"
                  style={{ 
                    borderColor: colors.danger,
                    color: colors.danger
                  }}
                >
                  Delete
                </button>
                <div className="flex space-x-3">
                  <button
                    onClick={() => setShowEditAllocationModal(false)}
                    className="px-4 py-2 border rounded-lg transition-colors"
                    style={{ 
                      borderColor: colors.secondary,
                      color: colors.textDark
                    }}
                  >
                    Cancel
                  </button>
                  <button
                    onClick={updateAllocation}
                    disabled={!editAllocation.fund_id || !editAllocation.department.trim() || 
                             !editAllocation.purpose.trim() || !editAllocation.allocated_amount}
                    className={`px-4 py-2 rounded-lg transition-colors ${
                      !editAllocation.fund_id || !editAllocation.department.trim() || 
                      !editAllocation.purpose.trim() || !editAllocation.allocated_amount
                        ? '' : ''
                    }`}
                    style={{ 
                      backgroundColor: !editAllocation.fund_id || !editAllocation.department.trim() || 
                      !editAllocation.purpose.trim() || !editAllocation.allocated_amount
                        ? colors.secondary 
                        : colors.primary,
                      color: 'white'
                    }}
                  >
                    Update Allocation
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}