import { useState, useEffect } from 'react';

// Icons import
import {
  Store, Building, TrendingUp, Percent, Shield,
  DollarSign, Calendar, CheckCircle, Edit2,
  Trash2, Plus, Search, RefreshCw, AlertTriangle,
  FileText, ChevronRight, Layers, Calculator,
  CreditCard, Receipt, Coins, Clock
} from 'lucide-react';

// Custom colors from your RPTConfig
const COLORS = {
  primary: '#4a90e2',
  secondary: '#9aa5b1',
  success: '#4caf50',
  background: '#fbfbfb',
  warning: '#ff9800',
  danger: '#f44336',
  info: '#2196f3',
  dark: '#374151',
  purple: '#8b5cf6',
  indigo: '#6366f1'
};

export default function BusinessTaxConfig() {
  const [activeTab, setActiveTab] = useState('business');
  const [currentDate, setCurrentDate] = useState(new Date().toISOString().split('T')[0]);
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const [successMessage, setSuccessMessage] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [showForm, setShowForm] = useState(false);

  // API Base URL - Keeping your exact logic
  const isLocalhost = window.location.hostname === 'localhost' || 
                      window.location.hostname === '127.0.0.1' ||
                      window.location.hostname === '';
  const API_BASE = isLocalhost
    ? "http://localhost/revenue2/backend/Business/BusinessTaxConfig"
    : "/backend/Business/BusinessTaxConfig";

  // Initialize all states - Keeping your exact state structure
  const [businessConfigs, setBusinessConfigs] = useState([]);
  const [businessForm, setBusinessForm] = useState({
    business_type: '',
    tax_percent: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    remarks: ''
  });

  const [capitalConfigs, setCapitalConfigs] = useState([]);
  const [capitalForm, setCapitalForm] = useState({
    min_amount: '',
    max_amount: '',
    tax_percent: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    remarks: ''
  });

  const [regulatoryConfigs, setRegulatoryConfigs] = useState([]);
  const [regulatoryForm, setRegulatoryForm] = useState({
    fee_name: '',
    amount: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    remarks: ''
  });

  const [penaltyConfigs, setPenaltyConfigs] = useState([]);
  const [penaltyForm, setPenaltyForm] = useState({
    penalty_percent: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    remarks: ''
  });

  const [discountConfigs, setDiscountConfigs] = useState([]);
  const [discountForm, setDiscountForm] = useState({
    discount_percent: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    remarks: ''
  });

  const [editingId, setEditingId] = useState(null);
  const [editingType, setEditingType] = useState(null);

  // Safe array variables
  const businessConfigsSafe = Array.isArray(businessConfigs) ? businessConfigs : [];
  const capitalConfigsSafe = Array.isArray(capitalConfigs) ? capitalConfigs : [];
  const regulatoryConfigsSafe = Array.isArray(regulatoryConfigs) ? regulatoryConfigs : [];
  const penaltyConfigsSafe = Array.isArray(penaltyConfigs) ? penaltyConfigs : [];
  const discountConfigsSafe = Array.isArray(discountConfigs) ? discountConfigs : [];

  // Helper to normalize database dates - Keeping your exact function
  const normalizeDate = (dateStr) => {
    if (!dateStr || dateStr === '0000-00-00' || dateStr === '0000-00-00 00:00:00') {
      return null;
    }
    return dateStr;
  };

  // Enhanced fetch helper - Keeping your exact function
  const fetchData = async (endpoint, setData) => {
    try {
      const response = await fetch(`${API_BASE}/${endpoint}?current_date=${currentDate}&_t=${Date.now()}`);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        const text = await response.text();
        try {
          const parsed = JSON.parse(text);
          return handleJSONResponse(parsed, endpoint, setData);
        } catch (parseError) {
          throw new Error(`Expected JSON but got: ${contentType}`);
        }
      }
      
      const result = await response.json();
      return handleJSONResponse(result, endpoint, setData);
    } catch (error) {
      console.error(`Error fetching ${endpoint}:`, error);
      setData([]);
      setError(`Failed to load ${endpoint}: ${error.message}`);
      return [];
    }
  };

  // Handle JSON response parsing - Keeping your exact function
  const handleJSONResponse = (result, endpoint, setData) => {
    console.log(`API Response from ${endpoint}:`, result);
    
    let dataArray = [];
    
    // Handle different response structures
    if (Array.isArray(result)) {
      dataArray = result;
    } else if (result && typeof result === 'object') {
      if (result.data !== undefined && result.data !== null) {
        if (Array.isArray(result.data)) {
          dataArray = result.data;
        } else if (typeof result.data === 'object') {
          dataArray = [result.data];
        }
      } else if (result.success !== undefined && result.data !== undefined) {
        if (Array.isArray(result.data)) {
          dataArray = result.data;
        } else if (typeof result.data === 'object') {
          dataArray = [result.data];
        }
      } else if (result.status === 'success' && result.data !== undefined) {
        if (Array.isArray(result.data)) {
          dataArray = result.data;
        } else if (typeof result.data === 'object') {
          dataArray = [result.data];
        }
      } else {
        const arrayKeys = Object.keys(result).filter(key => Array.isArray(result[key]));
        if (arrayKeys.length > 0) {
          dataArray = result[arrayKeys[0]];
        } else if (result.id !== undefined) {
          dataArray = [result];
        }
      }
    }
    
    if (dataArray.length === 0 && result && typeof result === 'object') {
      const entries = Object.entries(result);
      if (entries.length > 0) {
        if (Array.isArray(entries[0][1])) {
          dataArray = entries[0][1];
        } else if (typeof entries[0][1] === 'object') {
          const nestedEntries = Object.entries(entries[0][1]);
          if (nestedEntries.length > 0 && Array.isArray(nestedEntries[0][1])) {
            dataArray = nestedEntries[0][1];
          }
        }
      }
    }
    
    console.log(`Parsed JSON data for ${endpoint}:`, dataArray);
    
    // Normalize dates in the data
    if (Array.isArray(dataArray)) {
      dataArray = dataArray.map(item => ({
        ...item,
        expiration_date: normalizeDate(item.expiration_date)
      }));
      
      setData(dataArray);
      return dataArray;
    } else {
      console.error(`Expected array but got:`, typeof dataArray, dataArray);
      setData([]);
      return [];
    }
  };

  // Fetch functions - Keeping your exact functions
  const fetchBusinessConfigs = async () => {
    return await fetchData('business-configurations.php', setBusinessConfigs);
  };

  const fetchCapitalConfigs = async () => {
    return await fetchData('capital-configurations.php', setCapitalConfigs);
  };

  const fetchRegulatoryConfigs = async () => {
    return await fetchData('regulatory-configurations.php', setRegulatoryConfigs);
  };

  const fetchPenaltyConfigs = async () => {
    return await fetchData('penalty-configurations.php', setPenaltyConfigs);
  };

  const fetchDiscountConfigs = async () => {
    return await fetchData('discount-configurations.php', setDiscountConfigs);
  };

  const fetchAllConfigs = async () => {
    const timeout = new Promise((_, reject) => 
      setTimeout(() => reject(new Error('Request timeout')), 15000)
    );

    try {
      const result = await Promise.race([
        Promise.all([
          fetchBusinessConfigs(),
          fetchCapitalConfigs(),
          fetchRegulatoryConfigs(),
          fetchPenaltyConfigs(),
          fetchDiscountConfigs()
        ]),
        timeout
      ]);
      return result;
    } catch (error) {
      console.error('Error fetching all configurations:', error);
      throw error;
    }
  };

  useEffect(() => {
    const loadData = async () => {
      setLoading(true);
      setError(null);
      try {
        await fetchAllConfigs();
      } catch (error) {
        setError('Failed to load configurations: ' + error.message);
      } finally {
        setLoading(false);
      }
    };
    
    loadData();
  }, [currentDate]);

  // Generic API call handler - Keeping your exact function
  const makeApiCall = async (endpoint, method, data = null) => {
    let url = `${API_BASE}/${endpoint}`;
    
    if (data?.id && (method === 'PUT' || method === 'PATCH' || method === 'DELETE')) {
      url += `?id=${data.id}`;
    }

    const options = {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Cache-Control': 'no-cache'
      },
      body: method !== 'GET' && data ? JSON.stringify(data) : null
    };

    try {
      const response = await fetch(url, options);
      
      const contentType = response.headers.get("content-type");
      let result;
      
      if (contentType && contentType.includes("application/json")) {
        result = await response.json();
      } else {
        const text = await response.text();
        try {
          result = JSON.parse(text);
        } catch {
          throw new Error(`Non-JSON response: ${text.substring(0, 100)}`);
        }
      }
      
      if (!response.ok) {
        throw new Error(result.message || result.error || 'Unknown error');
      }
      
      return result;
    } catch (error) {
      console.error(`API call failed (${method} ${endpoint}):`, error);
      throw error;
    }
  };

  // Form Handlers - Keeping your exact functions
  const handleBusinessSubmit = async (e) => {
    e.preventDefault();
    const endpoint = 'business-configurations.php';
    const method = editingId ? 'PUT' : 'POST';
    
    try {
      setSubmitting(true);
      const result = await makeApiCall(endpoint, method, editingId ? { ...businessForm, id: editingId } : businessForm);
      
      await fetchBusinessConfigs();
      resetBusinessForm();
      setSuccessMessage(editingId ? 'Business tax updated successfully!' : 'Business tax created successfully!');
      setTimeout(() => setSuccessMessage(null), 3000);
    } catch (error) {
      console.error('Error saving business configuration:', error);
      alert('Error saving business configuration: ' + error.message);
    } finally {
      setSubmitting(false);
    }
  };

  const handleCapitalSubmit = async (e) => {
    e.preventDefault();
    if (parseFloat(capitalForm.min_amount) >= parseFloat(capitalForm.max_amount)) {
      alert('Minimum capital must be less than maximum capital');
      return;
    }

    const endpoint = 'capital-configurations.php';
    const method = editingId ? 'PUT' : 'POST';
    
    try {
      setSubmitting(true);
      const result = await makeApiCall(endpoint, method, editingId ? { ...capitalForm, id: editingId } : capitalForm);
      
      await fetchCapitalConfigs();
      resetCapitalForm();
      setSuccessMessage(editingId ? 'Capital investment tax updated successfully!' : 'Capital investment tax created successfully!');
      setTimeout(() => setSuccessMessage(null), 3000);
    } catch (error) {
      console.error('Error saving capital configuration:', error);
      alert('Error saving capital configuration: ' + error.message);
    } finally {
      setSubmitting(false);
    }
  };

  const handleRegulatorySubmit = async (e) => {
    e.preventDefault();
    const endpoint = 'regulatory-configurations.php';
    const method = editingId ? 'PUT' : 'POST';
    
    try {
      setSubmitting(true);
      const result = await makeApiCall(endpoint, method, editingId ? { ...regulatoryForm, id: editingId } : regulatoryForm);
      
      await fetchRegulatoryConfigs();
      resetRegulatoryForm();
      setSuccessMessage(editingId ? 'Regulatory configuration updated successfully!' : 'Regulatory configuration created successfully!');
      setTimeout(() => setSuccessMessage(null), 3000);
    } catch (error) {
      console.error('Error saving regulatory configuration:', error);
      alert('Error saving regulatory configuration: ' + error.message);
    } finally {
      setSubmitting(false);
    }
  };

  const handlePenaltySubmit = async (e) => {
    e.preventDefault();
    const endpoint = 'penalty-configurations.php';
    const method = editingId ? 'PUT' : 'POST';
    
    try {
      setSubmitting(true);
      const result = await makeApiCall(endpoint, method, editingId ? { ...penaltyForm, id: editingId } : penaltyForm);
      
      await fetchPenaltyConfigs();
      resetPenaltyForm();
      setSuccessMessage(editingId ? 'Penalty configuration updated successfully!' : 'Penalty configuration created successfully!');
      setTimeout(() => setSuccessMessage(null), 3000);
    } catch (error) {
      console.error('Error saving penalty configuration:', error);
      alert('Error saving penalty configuration: ' + error.message);
    } finally {
      setSubmitting(false);
    }
  };

  const handleDiscountSubmit = async (e) => {
    e.preventDefault();
    const endpoint = 'discount-configurations.php';
    const method = editingId ? 'PUT' : 'POST';
    
    try {
      setSubmitting(true);
      const result = await makeApiCall(endpoint, method, editingId ? { ...discountForm, id: editingId } : discountForm);
      
      await fetchDiscountConfigs();
      resetDiscountForm();
      setSuccessMessage(editingId ? 'Discount configuration updated successfully!' : 'Discount configuration created successfully!');
      setTimeout(() => setSuccessMessage(null), 3000);
    } catch (error) {
      console.error('Error saving discount configuration:', error);
      alert('Error saving discount configuration: ' + error.message);
    } finally {
      setSubmitting(false);
    }
  };

  // Edit Handlers - Keeping your exact functions
  const handleBusinessEdit = (config) => {
    setBusinessForm({
      business_type: config.business_type || '',
      tax_percent: config.tax_percent || '',
      effective_date: config.effective_date || new Date().toISOString().split('T')[0],
      expiration_date: config.expiration_date || '',
      remarks: config.remarks || ''
    });
    setEditingId(config.id);
    setEditingType('business');
  };

  const handleCapitalEdit = (config) => {
    setCapitalForm({
      min_amount: config.min_amount || '',
      max_amount: config.max_amount || '',
      tax_percent: config.tax_percent || '',
      effective_date: config.effective_date || new Date().toISOString().split('T')[0],
      expiration_date: config.expiration_date || '',
      remarks: config.remarks || ''
    });
    setEditingId(config.id);
    setEditingType('capital');
  };

  const handleRegulatoryEdit = (config) => {
    setRegulatoryForm({
      fee_name: config.fee_name || '',
      amount: config.amount || '',
      effective_date: config.effective_date || new Date().toISOString().split('T')[0],
      expiration_date: config.expiration_date || '',
      remarks: config.remarks || ''
    });
    setEditingId(config.id);
    setEditingType('regulatory');
  };

  const handlePenaltyEdit = (config) => {
    setPenaltyForm({
      penalty_percent: config.penalty_percent || '',
      effective_date: config.effective_date || new Date().toISOString().split('T')[0],
      expiration_date: config.expiration_date || '',
      remarks: config.remarks || ''
    });
    setEditingId(config.id);
    setEditingType('penalty');
  };

  const handleDiscountEdit = (config) => {
    setDiscountForm({
      discount_percent: config.discount_percent || '',
      effective_date: config.effective_date || new Date().toISOString().split('T')[0],
      expiration_date: config.expiration_date || '',
      remarks: config.remarks || ''
    });
    setEditingId(config.id);
    setEditingType('discount');
  };

  // Delete Handler - Keeping your exact function
  const handleDelete = async (id, type) => {
    const typeName = type === 'business' ? 'business tax' : 
                    type === 'capital' ? 'capital investment tax' :
                    type === 'regulatory' ? 'regulatory configuration' :
                    type === 'penalty' ? 'penalty configuration' : 'discount configuration';
    
    if (window.confirm(`Are you sure you want to delete this ${typeName}?`)) {
      try {
        setSubmitting(true);
        const endpoint = `${type}-configurations.php`;
        await makeApiCall(endpoint, 'DELETE', { id });
        
        switch (type) {
          case 'business':
            await fetchBusinessConfigs();
            break;
          case 'capital':
            await fetchCapitalConfigs();
            break;
          case 'regulatory':
            await fetchRegulatoryConfigs();
            break;
          case 'penalty':
            await fetchPenaltyConfigs();
            break;
          case 'discount':
            await fetchDiscountConfigs();
            break;
        }
        
        setSuccessMessage(`${typeName} deleted successfully!`);
        setTimeout(() => setSuccessMessage(null), 3000);
      } catch (error) {
        console.error(`Error deleting ${type}:`, error);
        alert('Error deleting configuration: ' + error.message);
      } finally {
        setSubmitting(false);
      }
    }
  };

  // Form Resets - Keeping your exact functions
  const resetBusinessForm = () => {
    setBusinessForm({
      business_type: '',
      tax_percent: '',
      effective_date: new Date().toISOString().split('T')[0],
      expiration_date: '',
      remarks: ''
    });
    setEditingId(null);
    setEditingType(null);
    setShowForm(false);
  };

  const resetCapitalForm = () => {
    setCapitalForm({
      min_amount: '',
      max_amount: '',
      tax_percent: '',
      effective_date: new Date().toISOString().split('T')[0],
      expiration_date: '',
      remarks: ''
    });
    setEditingId(null);
    setEditingType(null);
    setShowForm(false);
  };

  const resetRegulatoryForm = () => {
    setRegulatoryForm({
      fee_name: '',
      amount: '',
      effective_date: new Date().toISOString().split('T')[0],
      expiration_date: '',
      remarks: ''
    });
    setEditingId(null);
    setEditingType(null);
    setShowForm(false);
  };

  const resetPenaltyForm = () => {
    setPenaltyForm({
      penalty_percent: '',
      effective_date: new Date().toISOString().split('T')[0],
      expiration_date: '',
      remarks: ''
    });
    setEditingId(null);
    setEditingType(null);
    setShowForm(false);
  };

  const resetDiscountForm = () => {
    setDiscountForm({
      discount_percent: '',
      effective_date: new Date().toISOString().split('T')[0],
      expiration_date: '',
      remarks: ''
    });
    setEditingId(null);
    setEditingType(null);
    setShowForm(false);
  };

  // Statistics
  const calculateStats = (configs) => {
    if (!Array.isArray(configs)) return { active: 0, expired: 0 };
    
    const today = new Date();
    let active = 0;
    let expired = 0;
    
    configs.forEach(config => {
      if (!config.expiration_date) {
        active++;
      } else {
        try {
          const expDate = new Date(config.expiration_date);
          if (expDate > today) {
            active++;
          } else {
            expired++;
          }
        } catch (e) {
          active++;
        }
      }
    });
    
    return { active, expired };
  };

  const businessStats = calculateStats(businessConfigsSafe);
  const capitalStats = calculateStats(capitalConfigsSafe);
  const regulatoryStats = calculateStats(regulatoryConfigsSafe);
  const penaltyStats = calculateStats(penaltyConfigsSafe);
  const discountStats = calculateStats(discountConfigsSafe);

  // Refresh function
  const refreshCurrentTab = async () => {
    setLoading(true);
    try {
      switch (activeTab) {
        case 'business':
          await fetchBusinessConfigs();
          break;
        case 'capital':
          await fetchCapitalConfigs();
          break;
        case 'regulatory':
          await fetchRegulatoryConfigs();
          break;
        case 'penalty':
          await fetchPenaltyConfigs();
          break;
        case 'discount':
          await fetchDiscountConfigs();
          break;
        default:
          await fetchAllConfigs();
      }
    } catch (error) {
      console.error('Error refreshing data:', error);
      setError('Failed to refresh data: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  // Get tab icon
  const getTabIcon = (tab) => {
    switch(tab) {
      case 'business': return <Store className="w-5 h-5" />;
      case 'capital': return <CreditCard className="w-5 h-5" />;
      case 'regulatory': return <Receipt className="w-5 h-5" />;
      case 'penalty': return <Shield className="w-5 h-5" />;
      case 'discount': return <Percent className="w-5 h-5" />;
      default: return <Settings className="w-5 h-5" />;
    }
  };

  // Get tab title
  const getTabTitle = (tab) => {
    return tab === 'business' ? 'Gross Sales Tax' : 
           tab === 'capital' ? 'Capital Investment Tax' :
           tab === 'regulatory' ? 'Regulatory Fees' :
           tab === 'penalty' ? 'Penalties' : 'Discounts';
  };

  return (
    <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
      {/* Header */}
      <div className="border-b" style={{ backgroundColor: 'white', borderColor: '#e5e7eb' }}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
              <h1 className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>
                Business Tax Configuration
              </h1>
              <div className="flex items-center gap-3 text-sm" style={{ color: COLORS.secondary }}>
                <div className="flex items-center gap-1">
                  <Calendar className="w-4 h-4" />
                  <span>Last Updated: {new Date().toLocaleDateString('en-PH')}</span>
                </div>
                <div className="flex items-center gap-1">
                  <CheckCircle className="w-4 h-4" />
                  <span>Total Configurations: {
                    businessConfigsSafe.length + capitalConfigsSafe.length + 
                    regulatoryConfigsSafe.length + penaltyConfigsSafe.length + 
                    discountConfigsSafe.length
                  }</span>
                </div>
              </div>
            </div>
            <div className="flex items-center gap-4">
              <div className="flex items-center gap-2">
                <Calendar className="w-4 h-4" style={{ color: COLORS.secondary }} />
                <input
                  type="date"
                  value={currentDate}
                  onChange={(e) => setCurrentDate(e.target.value)}
                  className="px-3 py-1 border rounded-lg text-sm"
                  style={{ borderColor: COLORS.secondary }}
                />
              </div>
              <button
                onClick={() => setCurrentDate(new Date().toISOString().split('T')[0])}
                className="px-3 py-1 text-sm border rounded-lg hover:bg-gray-50"
                style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
              >
                Today
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Success Message */}
        {successMessage && (
          <div className="bg-green-50 border border-green-200 rounded-xl p-4 animate-fadeIn">
            <div className="flex items-center gap-3">
              <CheckCircle className="w-5 h-5 text-green-600" />
              <div className="flex-1">
                <p className="font-medium text-green-600">Success</p>
                <p className="text-sm text-green-700">{successMessage}</p>
              </div>
              <button 
                onClick={() => setSuccessMessage(null)}
                className="text-green-600 hover:text-green-800"
              >
                ×
              </button>
            </div>
          </div>
        )}

        {/* Error Display */}
        {error && (
          <div className="bg-red-50 border border-red-200 rounded-xl p-4 animate-fadeIn">
            <div className="flex items-center gap-3">
              <AlertTriangle className="w-5 h-5 text-red-600" />
              <div className="flex-1">
                <p className="font-medium text-red-600">Error</p>
                <p className="text-sm text-red-700">{error}</p>
              </div>
              <button 
                onClick={() => setError(null)}
                className="text-red-600 hover:text-red-800"
              >
                ×
              </button>
            </div>
          </div>
        )}

        {/* Statistics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
          {/* Business Card */}
          <div 
            className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all cursor-pointer transform hover:-translate-y-1"
            style={{ borderColor: COLORS.primary, borderLeft: `4px solid ${COLORS.primary}` }}
            onClick={() => setActiveTab('business')}
          >
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                <Store className="w-5 h-5" style={{ color: COLORS.primary }} />
              </div>
              <div className="flex-1">
                <h3 className="text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary }}>
                  Gross Sales
                </h3>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.dark }}>{businessConfigsSafe.length}</p>
                <div className="flex items-center justify-between mt-2">
                  <span className="text-xs px-2 py-1 rounded-full" style={{ backgroundColor: `${COLORS.primary}15`, color: COLORS.primary }}>
                    {businessStats.active} active
                  </span>
                  {businessStats.expired > 0 && (
                    <span className="text-xs text-gray-500">{businessStats.expired} expired</span>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Capital Card */}
          <div 
            className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all cursor-pointer transform hover:-translate-y-1"
            style={{ borderColor: COLORS.indigo, borderLeft: `4px solid ${COLORS.indigo}` }}
            onClick={() => setActiveTab('capital')}
          >
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.indigo}15` }}>
                <CreditCard className="w-5 h-5" style={{ color: COLORS.indigo }} />
              </div>
              <div className="flex-1">
                <h3 className="text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary }}>
                  Capital Tax
                </h3>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.dark }}>{capitalConfigsSafe.length}</p>
                <div className="flex items-center justify-between mt-2">
                  <span className="text-xs px-2 py-1 rounded-full" style={{ backgroundColor: `${COLORS.indigo}15`, color: COLORS.indigo }}>
                    {capitalStats.active} active
                  </span>
                  {capitalStats.expired > 0 && (
                    <span className="text-xs text-gray-500">{capitalStats.expired} expired</span>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Regulatory Card */}
          <div 
            className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all cursor-pointer transform hover:-translate-y-1"
            style={{ borderColor: COLORS.success, borderLeft: `4px solid ${COLORS.success}` }}
            onClick={() => setActiveTab('regulatory')}
          >
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.success}15` }}>
                <Receipt className="w-5 h-5" style={{ color: COLORS.success }} />
              </div>
              <div className="flex-1">
                <h3 className="text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary }}>
                  Regulatory
                </h3>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.dark }}>{regulatoryConfigsSafe.length}</p>
                <div className="flex items-center justify-between mt-2">
                  <span className="text-xs px-2 py-1 rounded-full" style={{ backgroundColor: `${COLORS.success}15`, color: COLORS.success }}>
                    {regulatoryStats.active} active
                  </span>
                  {regulatoryStats.expired > 0 && (
                    <span className="text-xs text-gray-500">{regulatoryStats.expired} expired</span>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Penalty Card */}
          <div 
            className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all cursor-pointer transform hover:-translate-y-1"
            style={{ borderColor: COLORS.danger, borderLeft: `4px solid ${COLORS.danger}` }}
            onClick={() => setActiveTab('penalty')}
          >
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.danger}15` }}>
                <Shield className="w-5 h-5" style={{ color: COLORS.danger }} />
              </div>
              <div className="flex-1">
                <h3 className="text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary }}>
                  Penalties
                </h3>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.dark }}>{penaltyConfigsSafe.length}</p>
                <div className="flex items-center justify-between mt-2">
                  <span className="text-xs px-2 py-1 rounded-full" style={{ backgroundColor: `${COLORS.danger}15`, color: COLORS.danger }}>
                    {penaltyStats.active} active
                  </span>
                  {penaltyStats.expired > 0 && (
                    <span className="text-xs text-gray-500">{penaltyStats.expired} expired</span>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Discount Card */}
          <div 
            className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all cursor-pointer transform hover:-translate-y-1"
            style={{ borderColor: COLORS.warning, borderLeft: `4px solid ${COLORS.warning}` }}
            onClick={() => setActiveTab('discount')}
          >
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.warning}15` }}>
                <Percent className="w-5 h-5" style={{ color: COLORS.warning }} />
              </div>
              <div className="flex-1">
                <h3 className="text-xs font-semibold uppercase tracking-wider" style={{ color: COLORS.secondary }}>
                  Discounts
                </h3>
                <p className="text-2xl font-bold mt-1" style={{ color: COLORS.dark }}>{discountConfigsSafe.length}</p>
                <div className="flex items-center justify-between mt-2">
                  <span className="text-xs px-2 py-1 rounded-full" style={{ backgroundColor: `${COLORS.warning}15`, color: COLORS.warning }}>
                    {discountStats.active} active
                  </span>
                  {discountStats.expired > 0 && (
                    <span className="text-xs text-gray-500">{discountStats.expired} expired</span>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Main Configuration Area */}
        <div className="bg-white border rounded-xl shadow-sm" style={{ borderColor: COLORS.secondary }}>
          {/* Tab Navigation */}
          <div className="p-6 border-b" style={{ borderColor: COLORS.secondary }}>
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
              <div className="flex space-x-1 overflow-x-auto pb-2 md:pb-0">
                {['business', 'capital', 'regulatory', 'penalty', 'discount'].map(tab => (
                  <button
                    key={tab}
                    onClick={() => setActiveTab(tab)}
                    className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-all whitespace-nowrap ${
                      activeTab === tab 
                        ? 'text-white shadow-sm' 
                        : 'hover:bg-gray-50'
                    }`}
                    style={{
                      backgroundColor: activeTab === tab ? 
                        (tab === 'business' ? COLORS.primary :
                         tab === 'capital' ? COLORS.indigo :
                         tab === 'regulatory' ? COLORS.success :
                         tab === 'penalty' ? COLORS.danger : COLORS.warning) : 'transparent',
                      color: activeTab === tab ? 'white' : COLORS.dark
                    }}
                  >
                    {getTabIcon(tab)}
                    {getTabTitle(tab)}
                    <span className="text-xs px-1.5 py-0.5 rounded-full bg-white/20">
                      {tab === 'business' ? businessConfigsSafe.length :
                       tab === 'capital' ? capitalConfigsSafe.length :
                       tab === 'regulatory' ? regulatoryConfigsSafe.length :
                       tab === 'penalty' ? penaltyConfigsSafe.length :
                       discountConfigsSafe.length}
                    </span>
                  </button>
                ))}
              </div>
              
              <div className="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                <div className="relative flex-1 sm:flex-none">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" style={{ color: COLORS.secondary }} />
                  <input
                    type="text"
                    placeholder={`Search ${getTabTitle(activeTab).toLowerCase()}...`}
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="pl-10 pr-4 py-2 border rounded-lg w-full sm:w-64 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    style={{ borderColor: COLORS.secondary }}
                  />
                </div>
                
                <div className="flex gap-2">
                  <button
                    onClick={() => {
                      setShowForm(!showForm);
                      if (editingId) {
                        switch(activeTab) {
                          case 'business': resetBusinessForm(); break;
                          case 'capital': resetCapitalForm(); break;
                          case 'regulatory': resetRegulatoryForm(); break;
                          case 'penalty': resetPenaltyForm(); break;
                          case 'discount': resetDiscountForm(); break;
                        }
                      }
                    }}
                    className="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 py-2 rounded-lg transition-all hover:shadow-sm"
                    style={{ 
                      backgroundColor: COLORS.primary, 
                      color: 'white',
                      boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
                    }}
                  >
                    <Plus className="w-4 h-4" />
                    <span className="truncate">{showForm ? 'Cancel' : 'Add New'}</span>
                  </button>
                  
                  <button
                    onClick={refreshCurrentTab}
                    disabled={loading || submitting}
                    className="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all disabled:opacity-50"
                    style={{ 
                      borderColor: COLORS.secondary, 
                      color: COLORS.dark,
                      boxShadow: '0 1px 3px rgba(0,0,0,0.05)'
                    }}
                  >
                    <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
                    <span className="truncate">{loading ? 'Refreshing...' : 'Refresh'}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Loading State */}
          {loading && (
            <div className="p-8 text-center">
              <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2" style={{ borderColor: COLORS.primary }}></div>
              <p className="mt-2 text-sm" style={{ color: COLORS.secondary }}>Loading configurations...</p>
            </div>
          )}

          {/* Configuration Form */}
          {!loading && showForm && (
            <div className="p-6 border-b" style={{ borderColor: COLORS.secondary, backgroundColor: `${COLORS.background}` }}>
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <Edit2 className="w-5 h-5" style={{ color: COLORS.primary }} />
                  {editingType ? `Edit ${getTabTitle(activeTab)}` : `New ${getTabTitle(activeTab)}`}
                </h3>
                <span className="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-800">
                  {editingType ? 'Editing Mode' : 'Create Mode'}
                </span>
              </div>
              
              <form onSubmit={(e) => {
                e.preventDefault();
                switch(activeTab) {
                  case 'business': handleBusinessSubmit(e); break;
                  case 'capital': handleCapitalSubmit(e); break;
                  case 'regulatory': handleRegulatorySubmit(e); break;
                  case 'penalty': handlePenaltySubmit(e); break;
                  case 'discount': handleDiscountSubmit(e); break;
                }
              }} className="space-y-4">
                {/* Business Configuration Form */}
                {activeTab === 'business' && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Business Type *
                      </label>
                      <select
                        value={businessForm.business_type}
                        onChange={(e) => setBusinessForm({...businessForm, business_type: e.target.value})}
                        className="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        style={{ borderColor: COLORS.secondary }}
                        required
                        disabled={submitting}
                      >
                        <option value="">Select Business Type</option>
                        <option value="Retailer">Retailer</option>
                        <option value="Wholesaler">Wholesaler</option>
                        <option value="Service Provider">Service Provider</option>
                        <option value="Manufacturer">Manufacturer</option>
                        <option value="Contractor">Contractor</option>
                        <option value="Other">Other</option>
                      </select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Tax Rate (%) *
                      </label>
                      <div className="relative">
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          max="100"
                          value={businessForm.tax_percent}
                          onChange={(e) => setBusinessForm({...businessForm, tax_percent: e.target.value})}
                          className="w-full p-2 border rounded-lg pl-8 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                          style={{ borderColor: COLORS.secondary }}
                          placeholder="2.00 for 2%"
                          required
                          disabled={submitting}
                        />
                        <Percent className="absolute left-2 top-1/2 transform -translate-y-1/2 w-4 h-4" style={{ color: COLORS.secondary }} />
                      </div>
                    </div>
                  </div>
                )}

                {/* Capital Investment Tax Form */}
                {activeTab === 'capital' && (
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Minimum Capital (₱) *
                      </label>
                      <div className="relative">
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          value={capitalForm.min_amount}
                          onChange={(e) => setCapitalForm({...capitalForm, min_amount: e.target.value})}
                          className="w-full p-2 border rounded-lg pl-8 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                          style={{ borderColor: COLORS.secondary }}
                          placeholder="0.00"
                          required
                          disabled={submitting}
                        />
                        <DollarSign className="absolute left-2 top-1/2 transform -translate-y-1/2 w-4 h-4" style={{ color: COLORS.secondary }} />
                      </div>
                    </div>

                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Maximum Capital (₱) *
                      </label>
                      <div className="relative">
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          value={capitalForm.max_amount}
                          onChange={(e) => setCapitalForm({...capitalForm, max_amount: e.target.value})}
                          className="w-full p-2 border rounded-lg pl-8 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                          style={{ borderColor: COLORS.secondary }}
                          placeholder="5000.00"
                          required
                          disabled={submitting}
                        />
                        <DollarSign className="absolute left-2 top-1/2 transform -translate-y-1/2 w-4 h-4" style={{ color: COLORS.secondary }} />
                      </div>
                    </div>

                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Tax Percentage (%) *
                      </label>
                      <div className="relative">
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          max="100"
                          value={capitalForm.tax_percent}
                          onChange={(e) => setCapitalForm({...capitalForm, tax_percent: e.target.value})}
                          className="w-full p-2 border rounded-lg pl-8 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                          style={{ borderColor: COLORS.secondary }}
                          placeholder="0.25 for 0.25%"
                          required
                          disabled={submitting}
                        />
                        <Percent className="absolute left-2 top-1/2 transform -translate-y-1/2 w-4 h-4" style={{ color: COLORS.secondary }} />
                      </div>
                    </div>
                  </div>
                )}

                {/* Regulatory Configuration Form */}
                {activeTab === 'regulatory' && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Fee Name *
                      </label>
                      <select
                        value={regulatoryForm.fee_name}
                        onChange={(e) => setRegulatoryForm({...regulatoryForm, fee_name: e.target.value})}
                        className="w-full p-2 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                        style={{ borderColor: COLORS.secondary }}
                        required
                        disabled={submitting}
                      >
                        <option value="">Select Fee Type</option>
                        <option value="Mayor's Permit Fee">Mayor's Permit Fee</option>
                        <option value="Sanitary Fee">Sanitary Fee</option>
                        <option value="Registration Fee">Registration Fee</option>
                        <option value="Signage Fee">Signage Fee</option>
                        <option value="Other">Other</option>
                      </select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Amount (₱) *
                      </label>
                      <div className="relative">
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          value={regulatoryForm.amount}
                          onChange={(e) => setRegulatoryForm({...regulatoryForm, amount: e.target.value})}
                          className="w-full p-2 border rounded-lg pl-8 focus:ring-2 focus:ring-green-500 focus:border-transparent"
                          style={{ borderColor: COLORS.secondary }}
                          placeholder="0.00"
                          required
                          disabled={submitting}
                        />
                        <DollarSign className="absolute left-2 top-1/2 transform -translate-y-1/2 w-4 h-4" style={{ color: COLORS.secondary }} />
                      </div>
                    </div>
                  </div>
                )}

                {/* Penalty Configuration Form */}
                {activeTab === 'penalty' && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Penalty Percentage (%) *
                      </label>
                      <div className="relative">
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          max="100"
                          value={penaltyForm.penalty_percent}
                          onChange={(e) => setPenaltyForm({...penaltyForm, penalty_percent: e.target.value})}
                          className="w-full p-2 border rounded-lg pl-8 focus:ring-2 focus:ring-red-500 focus:border-transparent"
                          style={{ borderColor: COLORS.secondary }}
                          placeholder="0.00"
                          required
                          disabled={submitting}
                        />
                        <Percent className="absolute left-2 top-1/2 transform -translate-y-1/2 w-4 h-4" style={{ color: COLORS.secondary }} />
                      </div>
                    </div>
                  </div>
                )}

                {/* Discount Configuration Form */}
                {activeTab === 'discount' && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Discount Percentage (%) *
                      </label>
                      <div className="relative">
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          max="100"
                          value={discountForm.discount_percent}
                          onChange={(e) => setDiscountForm({...discountForm, discount_percent: e.target.value})}
                          className="w-full p-2 border rounded-lg pl-8 focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                          style={{ borderColor: COLORS.secondary }}
                          placeholder="0.00"
                          required
                          disabled={submitting}
                        />
                        <Percent className="absolute left-2 top-1/2 transform -translate-y-1/2 w-4 h-4" style={{ color: COLORS.secondary }} />
                      </div>
                    </div>
                  </div>
                )}

                {/* Common Date Fields */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                      Effective Date *
                    </label>
                    <div className="relative">
                      <input
                        type="date"
                        value={(() => {
                          switch(activeTab) {
                            case 'business': return businessForm.effective_date;
                            case 'capital': return capitalForm.effective_date;
                            case 'regulatory': return regulatoryForm.effective_date;
                            case 'penalty': return penaltyForm.effective_date;
                            case 'discount': return discountForm.effective_date;
                            default: return '';
                          }
                        })()}
                        onChange={(e) => {
                          switch(activeTab) {
                            case 'business': setBusinessForm({...businessForm, effective_date: e.target.value}); break;
                            case 'capital': setCapitalForm({...capitalForm, effective_date: e.target.value}); break;
                            case 'regulatory': setRegulatoryForm({...regulatoryForm, effective_date: e.target.value}); break;
                            case 'penalty': setPenaltyForm({...penaltyForm, effective_date: e.target.value}); break;
                            case 'discount': setDiscountForm({...discountForm, effective_date: e.target.value}); break;
                          }
                        }}
                        className="w-full p-2 border rounded-lg pl-8 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        style={{ borderColor: COLORS.secondary }}
                        required
                        disabled={submitting}
                      />
                      <Calendar className="absolute left-2 top-1/2 transform -translate-y-1/2 w-4 h-4" style={{ color: COLORS.secondary }} />
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                      Expiration Date
                    </label>
                    <div className="relative">
                      <input
                        type="date"
                        value={(() => {
                          switch(activeTab) {
                            case 'business': return businessForm.expiration_date;
                            case 'capital': return capitalForm.expiration_date;
                            case 'regulatory': return regulatoryForm.expiration_date;
                            case 'penalty': return penaltyForm.expiration_date;
                            case 'discount': return discountForm.expiration_date;
                            default: return '';
                          }
                        })()}
                        onChange={(e) => {
                          switch(activeTab) {
                            case 'business': setBusinessForm({...businessForm, expiration_date: e.target.value}); break;
                            case 'capital': setCapitalForm({...capitalForm, expiration_date: e.target.value}); break;
                            case 'regulatory': setRegulatoryForm({...regulatoryForm, expiration_date: e.target.value}); break;
                            case 'penalty': setPenaltyForm({...penaltyForm, expiration_date: e.target.value}); break;
                            case 'discount': setDiscountForm({...discountForm, expiration_date: e.target.value}); break;
                          }
                        }}
                        className="w-full p-2 border rounded-lg pl-8 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        style={{ borderColor: COLORS.secondary }}
                        disabled={submitting}
                      />
                      <Calendar className="absolute left-2 top-1/2 transform -translate-y-1/2 w-4 h-4" style={{ color: COLORS.secondary }} />
                      <div className="text-xs text-gray-500 mt-1">Leave empty if no expiration</div>
                    </div>
                  </div>
                </div>

                {/* Remarks Field */}
                <div>
                  <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                    Remarks
                  </label>
                  <textarea
                    value={(() => {
                      switch(activeTab) {
                        case 'business': return businessForm.remarks;
                        case 'capital': return capitalForm.remarks;
                        case 'regulatory': return regulatoryForm.remarks;
                        case 'penalty': return penaltyForm.remarks;
                        case 'discount': return discountForm.remarks;
                        default: return '';
                      }
                    })()}
                    onChange={(e) => {
                      switch(activeTab) {
                        case 'business': setBusinessForm({...businessForm, remarks: e.target.value}); break;
                        case 'capital': setCapitalForm({...capitalForm, remarks: e.target.value}); break;
                        case 'regulatory': setRegulatoryForm({...regulatoryForm, remarks: e.target.value}); break;
                        case 'penalty': setPenaltyForm({...penaltyForm, remarks: e.target.value}); break;
                        case 'discount': setDiscountForm({...discountForm, remarks: e.target.value}); break;
                      }
                    }}
                    rows="2"
                    className="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    style={{ borderColor: COLORS.secondary }}
                    placeholder="Additional notes or description..."
                    disabled={submitting}
                  />
                </div>

                {/* Preview Section */}
                {activeTab === 'business' && businessForm.tax_percent && (
                  <div className="p-4 rounded-lg" style={{ backgroundColor: `${COLORS.primary}05`, border: `1px solid ${COLORS.primary}20` }}>
                    <h4 className="font-medium mb-2 flex items-center gap-2" style={{ color: COLORS.primary }}>
                      <Calculator className="w-4 h-4" />
                      Tax Calculation Preview
                    </h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                      <div>
                        <span className="font-medium" style={{ color: COLORS.secondary }}>Business Type:</span>
                        <div className="font-medium" style={{ color: COLORS.dark }}>{businessForm.business_type || 'Not specified'}</div>
                      </div>
                      <div>
                        <span className="font-medium" style={{ color: COLORS.secondary }}>Tax Rate:</span>
                        <div className="font-medium" style={{ color: COLORS.dark }}>{businessForm.tax_percent}%</div>
                      </div>
                    </div>
                    <p className="text-sm mt-2" style={{ color: COLORS.secondary }}>
                      Example: ₱100,000 gross sales = ₱{(100000 * (parseFloat(businessForm.tax_percent || 0) / 100)).toFixed(2)}
                    </p>
                  </div>
                )}

                {activeTab === 'capital' && capitalForm.min_amount && capitalForm.max_amount && capitalForm.tax_percent && (
                  <div className="p-4 rounded-lg" style={{ backgroundColor: `${COLORS.indigo}05`, border: `1px solid ${COLORS.indigo}20` }}>
                    <h4 className="font-medium mb-2 flex items-center gap-2" style={{ color: COLORS.indigo }}>
                      <Calculator className="w-4 h-4" />
                      Tax Calculation Preview
                    </h4>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                      <div>
                        <span className="font-medium" style={{ color: COLORS.secondary }}>Capital Range:</span>
                        <div className="font-medium" style={{ color: COLORS.dark }}>
                          ₱{parseFloat(capitalForm.min_amount).toLocaleString()} - ₱{parseFloat(capitalForm.max_amount).toLocaleString()}
                        </div>
                      </div>
                      <div>
                        <span className="font-medium" style={{ color: COLORS.secondary }}>Tax Rate:</span>
                        <div className="font-medium" style={{ color: COLORS.dark }}>{capitalForm.tax_percent}%</div>
                      </div>
                      <div>
                        <span className="font-medium" style={{ color: COLORS.secondary }}>Max Tax:</span>
                        <div className="font-medium" style={{ color: COLORS.dark }}>
                          ₱{parseFloat(capitalForm.max_amount).toLocaleString()} × {capitalForm.tax_percent}% = ₱{(parseFloat(capitalForm.max_amount) * (parseFloat(capitalForm.tax_percent) / 100)).toFixed(2)}
                        </div>
                      </div>
                    </div>
                  </div>
                )}

                {/* Form Actions */}
                <div className="flex gap-3 pt-4">
                  <button
                    type="submit"
                    disabled={submitting}
                    className="px-6 py-2 rounded-lg flex items-center gap-2 transition-all hover:shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    style={{ 
                      backgroundColor: COLORS.primary, 
                      color: 'white',
                      boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
                    }}
                  >
                    <CheckCircle className="w-4 h-4" />
                    {submitting ? 'Saving...' : (editingType ? 'Update Configuration' : 'Create Configuration')}
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      switch(activeTab) {
                        case 'business': resetBusinessForm(); break;
                        case 'capital': resetCapitalForm(); break;
                        case 'regulatory': resetRegulatoryForm(); break;
                        case 'penalty': resetPenaltyForm(); break;
                        case 'discount': resetDiscountForm(); break;
                      }
                    }}
                    disabled={submitting}
                    className="px-6 py-2 border rounded-lg hover:bg-gray-50 transition-all disabled:opacity-50"
                    style={{ 
                      borderColor: COLORS.secondary, 
                      color: COLORS.dark,
                      boxShadow: '0 1px 3px rgba(0,0,0,0.05)'
                    }}
                  >
                    Cancel
                  </button>
                </div>
              </form>
            </div>
          )}

          {/* Configurations List */}
          {!loading && !showForm && (
            <div className="p-6">
              <div className="flex justify-between items-center mb-6">
                <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                  <FileText className="w-5 h-5" style={{ color: COLORS.primary }} />
                  {getTabTitle(activeTab)} Configurations
                  <span className="text-sm px-2 py-1 rounded-full bg-gray-100">
                    {activeTab === 'business' ? businessConfigsSafe.length :
                     activeTab === 'capital' ? capitalConfigsSafe.length :
                     activeTab === 'regulatory' ? regulatoryConfigsSafe.length :
                     activeTab === 'penalty' ? penaltyConfigsSafe.length :
                     discountConfigsSafe.length} total
                  </span>
                </h3>
              </div>

              {(() => {
                const configs = activeTab === 'business' ? businessConfigsSafe :
                               activeTab === 'capital' ? capitalConfigsSafe :
                               activeTab === 'regulatory' ? regulatoryConfigsSafe :
                               activeTab === 'penalty' ? penaltyConfigsSafe :
                               discountConfigsSafe;

                if (configs.length === 0) {
                  const iconColor = activeTab === 'business' ? COLORS.primary :
                                  activeTab === 'capital' ? COLORS.indigo :
                                  activeTab === 'regulatory' ? COLORS.success :
                                  activeTab === 'penalty' ? COLORS.danger : COLORS.warning;

                  return (
                    <div className="text-center py-12">
                      <div className="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style={{ backgroundColor: `${iconColor}15` }}>
                        {getTabIcon(activeTab)}
                      </div>
                      <h3 className="text-lg font-medium mb-2" style={{ color: COLORS.dark }}>
                        No {getTabTitle(activeTab).toLowerCase()} found
                      </h3>
                      <p className="text-sm mb-6" style={{ color: COLORS.secondary }}>
                        Get started by creating your first {getTabTitle(activeTab).toLowerCase()} configuration
                      </p>
                      <button 
                        onClick={() => {
                          switch(activeTab) {
                            case 'business': resetBusinessForm(); break;
                            case 'capital': resetCapitalForm(); break;
                            case 'regulatory': resetRegulatoryForm(); break;
                            case 'penalty': resetPenaltyForm(); break;
                            case 'discount': resetDiscountForm(); break;
                          }
                          setShowForm(true);
                        }}
                        className="px-6 py-2 rounded-lg flex items-center gap-2 mx-auto transition-all hover:shadow-sm"
                        style={{ 
                          backgroundColor: iconColor, 
                          color: 'white',
                          boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
                        }}
                      >
                        <Plus className="w-4 h-4" />
                        Add {getTabTitle(activeTab)} Configuration
                      </button>
                    </div>
                  );
                }

                return (
                  <div className="overflow-x-auto">
                    <table className="w-full">
                      <thead>
                        <tr style={{ borderBottomWidth: '2px', borderColor: COLORS.secondary }}>
                          <th className="p-3 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>ID</th>
                          <th className="p-3 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>Details</th>
                          <th className="p-3 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>Values</th>
                          <th className="p-3 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>Dates</th>
                          <th className="p-3 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>Status</th>
                          <th className="p-3 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        {configs.map((config) => {
                          const isExpired = config.expiration_date && new Date(config.expiration_date) <= new Date();
                          const rowColor = isExpired ? 'bg-gray-50/50' : 'hover:bg-gray-50';
                          
                          return (
                            <tr 
                              key={config.id} 
                              className={`transition-colors ${rowColor}`}
                              style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}
                            >
                              <td className="p-3">
                                <div className="font-mono text-sm" style={{ color: COLORS.dark }}>#{config.id}</div>
                              </td>
                              <td className="p-3">
                                <div className="font-medium" style={{ color: COLORS.dark }}>
                                  {config.business_type || config.fee_name || getTabTitle(activeTab)}
                                </div>
                                {config.remarks && (
                                  <div className="text-xs mt-1" style={{ color: COLORS.secondary }}>
                                    {config.remarks}
                                  </div>
                                )}
                              </td>
                              <td className="p-3">
                                <div className="space-y-1">
                                  {activeTab === 'business' && (
                                    <>
                                      <div className="text-sm">
                                        <span style={{ color: COLORS.secondary }}>Rate: </span>
                                        <span className="font-medium" style={{ color: COLORS.dark }}>{config.tax_percent}%</span>
                                      </div>
                                      <div className="text-xs" style={{ color: COLORS.secondary }}>
                                        ₱100,000 × {config.tax_percent}% = ₱{(100000 * (parseFloat(config.tax_percent || 0) / 100)).toFixed(2)}
                                      </div>
                                    </>
                                  )}
                                  {activeTab === 'capital' && (
                                    <>
                                      <div className="text-sm">
                                        <span style={{ color: COLORS.secondary }}>Range: </span>
                                        <span className="font-medium" style={{ color: COLORS.dark }}>
                                          ₱{parseFloat(config.min_amount || 0).toLocaleString()} - ₱{parseFloat(config.max_amount || 0).toLocaleString()}
                                        </span>
                                      </div>
                                      <div className="text-sm">
                                        <span style={{ color: COLORS.secondary }}>Rate: </span>
                                        <span className="font-medium" style={{ color: COLORS.dark }}>{config.tax_percent}%</span>
                                      </div>
                                    </>
                                  )}
                                  {(activeTab === 'regulatory' || activeTab === 'penalty' || activeTab === 'discount') && (
                                    <div className="text-sm">
                                      <span style={{ color: COLORS.secondary }}>
                                        {activeTab === 'regulatory' ? 'Amount: ' : 'Rate: '}
                                      </span>
                                      <span className="font-medium" style={{ color: COLORS.dark }}>
                                        {activeTab === 'regulatory' ? '₱' + parseFloat(config.amount || 0).toLocaleString() : config.penalty_percent || config.discount_percent}%
                                      </span>
                                    </div>
                                  )}
                                </div>
                              </td>
                              <td className="p-3">
                                <div className="space-y-1">
                                  <div className="text-sm">
                                    <span style={{ color: COLORS.secondary }}>Effective: </span>
                                    <span style={{ color: COLORS.dark }}>{config.effective_date}</span>
                                  </div>
                                  <div className="text-sm">
                                    <span style={{ color: COLORS.secondary }}>Expires: </span>
                                    <span style={{ color: config.expiration_date ? COLORS.dark : COLORS.secondary }}>
                                      {config.expiration_date || 'Never'}
                                    </span>
                                  </div>
                                </div>
                              </td>
                              <td className="p-3">
                                <span className={`px-3 py-1 rounded-full text-xs font-medium ${
                                  !isExpired 
                                    ? 'bg-green-100 text-green-800' 
                                    : 'bg-gray-100 text-gray-800'
                                }`}>
                                  {isExpired ? 'Expired' : 'Active'}
                                </span>
                              </td>
                              <td className="p-3">
                                <div className="flex gap-2">
                                  <button
                                    onClick={() => {
                                      switch(activeTab) {
                                        case 'business': handleBusinessEdit(config); break;
                                        case 'capital': handleCapitalEdit(config); break;
                                        case 'regulatory': handleRegulatoryEdit(config); break;
                                        case 'penalty': handlePenaltyEdit(config); break;
                                        case 'discount': handleDiscountEdit(config); break;
                                      }
                                    }}
                                    className="p-2 rounded-lg hover:bg-yellow-50 transition-colors"
                                    style={{ color: COLORS.warning }}
                                    disabled={isExpired || submitting}
                                    title="Edit"
                                  >
                                    <Edit2 className="w-4 h-4" />
                                  </button>
                                  <button
                                    onClick={() => handleDelete(config.id, activeTab)}
                                    className="p-2 rounded-lg hover:bg-red-50 transition-colors"
                                    style={{ color: COLORS.danger }}
                                    disabled={submitting}
                                    title="Delete"
                                  >
                                    <Trash2 className="w-4 h-4" />
                                  </button>
                                </div>
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                );
              })()}
            </div>
          )}
        </div>

        {/* Footer Summary */}
        <div className="text-center text-sm pt-6 border-t" style={{ color: COLORS.secondary, borderColor: COLORS.secondary }}>
          <div className="flex flex-col md:flex-row justify-between items-center">
            <div className="text-left mb-2 md:mb-0">
              <p className="font-medium" style={{ color: COLORS.dark }}>Business Tax Configuration Management</p>
              <p className="text-xs" style={{ color: COLORS.secondary }}>Last Updated: {new Date().toLocaleDateString('en-PH')}</p>
            </div>
            <div className="text-right">
              <p className="text-xs">
                Total Configurations: {
                  businessConfigsSafe.length + capitalConfigsSafe.length + 
                  regulatoryConfigsSafe.length + penaltyConfigsSafe.length + 
                  discountConfigsSafe.length
                }
              </p>
              <p className="text-xs">
                Active: {
                  businessStats.active + capitalStats.active + 
                  regulatoryStats.active + penaltyStats.active + 
                  discountStats.active
                } • Expired: {
                  businessStats.expired + capitalStats.expired + 
                  regulatoryStats.expired + penaltyStats.expired + 
                  discountStats.expired
                }
              </p>
            </div>
          </div>
        </div>
      </div>

      <style jsx>{`
        @keyframes fadeIn {
          from { opacity: 0; transform: translateY(-10px); }
          to { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeIn {
          animation: fadeIn 0.3s ease-out;
        }
      `}</style>
    </div>
  );
}