import { useState, useEffect } from 'react';

export default function BusinessTaxConfig() {
  const [activeTab, setActiveTab] = useState('business');
  const [currentDate, setCurrentDate] = useState(new Date().toISOString().split('T')[0]);
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const [successMessage, setSuccessMessage] = useState(null);

  // Initialize all states as empty arrays
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

  const API_BASE = "http://localhost/revenue/backend/Business/BusinessTaxConfig";

  // Helper to normalize database dates
  const normalizeDate = (dateStr) => {
    if (!dateStr || dateStr === '0000-00-00' || dateStr === '0000-00-00 00:00:00') {
      return null;
    }
    return dateStr;
  };

  // PROPER JSON FETCH HELPER
  const fetchData = async (endpoint, setData) => {
    try {
      console.log(`Fetching from: ${API_BASE}/${endpoint}.php?current_date=${currentDate}`);
      const response = await fetch(`${API_BASE}/${endpoint}.php?current_date=${currentDate}`);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      // First check if response is valid JSON
      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        const text = await response.text();
        console.warn(`Response from ${endpoint} is not JSON:`, text.substring(0, 200));
        throw new Error(`Expected JSON but got: ${contentType}`);
      }
      
      // Parse JSON response
      const result = await response.json();
      console.log(`API Response from ${endpoint}:`, result);
      
      let dataArray = [];
      
      // Handle JSON response based on structure
      if (Array.isArray(result)) {
        // Case 1: Direct JSON array
        dataArray = result;
      } else if (result && typeof result === 'object') {
        // Case 2: JSON object with common structures
        if (result.data !== undefined) {
          // If data is an array
          if (Array.isArray(result.data)) {
            dataArray = result.data;
          } 
          // If data is a single object, wrap in array
          else if (typeof result.data === 'object' && result.data !== null) {
            dataArray = [result.data];
          }
          // If data is null or undefined
          else if (result.data === null || result.data === undefined) {
            dataArray = [];
          }
        } 
        // Case 3: Check for success/data pattern
        else if (result.success !== undefined && result.data !== undefined) {
          if (Array.isArray(result.data)) {
            dataArray = result.data;
          } else if (typeof result.data === 'object' && result.data !== null) {
            dataArray = [result.data];
          }
        }
        // Case 4: Object with other array properties
        else {
          // Look for any array property
          const arrayKeys = Object.keys(result).filter(key => Array.isArray(result[key]));
          if (arrayKeys.length > 0) {
            dataArray = result[arrayKeys[0]];
          }
          // If no array found but has id property (single object)
          else if (result.id !== undefined) {
            dataArray = [result];
          }
        }
      }
      
      // If still no data, try to extract from root properties
      if (dataArray.length === 0 && result && typeof result === 'object') {
        const entries = Object.entries(result);
        if (entries.length > 0 && Array.isArray(entries[0][1])) {
          dataArray = entries[0][1];
        }
      }
      
      console.log(`Parsed JSON data for ${endpoint}:`, dataArray);
      
      // Normalize dates in the data
      if (Array.isArray(dataArray)) {
        dataArray = dataArray.map(item => ({
          ...item,
          expiration_date: normalizeDate(item.expiration_date)
        }));
      } else {
        console.error(`Expected array but got:`, typeof dataArray, dataArray);
        dataArray = [];
      }
      
      setData(dataArray);
      return dataArray;
    } catch (error) {
      console.error(`Error fetching ${endpoint}:`, error);
      setData([]);
      setError(`Failed to load ${endpoint}: ${error.message}`);
      return [];
    }
  };

  // Fetch all configurations
  const fetchBusinessConfigs = async () => {
    return await fetchData('business-configurations', setBusinessConfigs);
  };

  const fetchCapitalConfigs = async () => {
    return await fetchData('capital-configurations', setCapitalConfigs);
  };

  const fetchRegulatoryConfigs = async () => {
    return await fetchData('regulatory-configurations', setRegulatoryConfigs);
  };

  const fetchPenaltyConfigs = async () => {
    return await fetchData('penalty-configurations', setPenaltyConfigs);
  };

  const fetchDiscountConfigs = async () => {
    return await fetchData('discount-configurations', setDiscountConfigs);
  };

  // Fetch all configurations
  const fetchAllConfigs = async () => {
    try {
      await Promise.all([
        fetchBusinessConfigs(),
        fetchCapitalConfigs(),
        fetchRegulatoryConfigs(),
        fetchPenaltyConfigs(),
        fetchDiscountConfigs()
      ]);
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

  // Business Configuration Handlers
  const handleBusinessSubmit = async (e) => {
    e.preventDefault();
    const url = editingId 
      ? `${API_BASE}/business-configurations.php?id=${editingId}`
      : `${API_BASE}/business-configurations.php`;
    
    const method = editingId ? 'PUT' : 'POST';

    try {
      setSubmitting(true);
      const response = await fetch(url, {
        method: method,
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(businessForm)
      });

      // Check if response is JSON
      const contentType = response.headers.get("content-type");
      let result;
      if (contentType && contentType.includes("application/json")) {
        result = await response.json();
      } else {
        const text = await response.text();
        throw new Error(`Expected JSON but got: ${text.substring(0, 100)}`);
      }
      
      console.log('Business API Response:', result);
      
      if (response.ok) {
        // Refresh from server
        await fetchBusinessConfigs();
        resetBusinessForm();
        setSuccessMessage(editingId ? 'Business tax updated successfully!' : 'Business tax created successfully!');
        setTimeout(() => setSuccessMessage(null), 3000);
      } else {
        alert('Error: ' + (result.message || result.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error saving business configuration:', error);
      alert('Error saving business configuration: ' + error.message);
    } finally {
      setSubmitting(false);
    }
  };

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

  // Capital Investment Configuration Handlers
  const handleCapitalSubmit = async (e) => {
    e.preventDefault();
    const url = editingId 
      ? `${API_BASE}/capital-configurations.php?id=${editingId}`
      : `${API_BASE}/capital-configurations.php`;
    
    const method = editingId ? 'PUT' : 'POST';

    // Validate that min capital is less than max capital
    if (parseFloat(capitalForm.min_amount) >= parseFloat(capitalForm.max_amount)) {
      alert('Minimum capital must be less than maximum capital');
      return;
    }

    try {
      setSubmitting(true);
      const response = await fetch(url, {
        method: method,
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(capitalForm)
      });

      // Check if response is JSON
      const contentType = response.headers.get("content-type");
      let result;
      if (contentType && contentType.includes("application/json")) {
        result = await response.json();
      } else {
        const text = await response.text();
        throw new Error(`Expected JSON but got: ${text.substring(0, 100)}`);
      }
      
      console.log('Capital API Response:', result);
      
      if (response.ok) {
        await fetchCapitalConfigs();
        resetCapitalForm();
        setSuccessMessage(editingId ? 'Capital investment tax updated successfully!' : 'Capital investment tax created successfully!');
        setTimeout(() => setSuccessMessage(null), 3000);
      } else {
        alert('Error: ' + (result.message || result.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error saving capital configuration:', error);
      alert('Error saving capital configuration: ' + error.message);
    } finally {
      setSubmitting(false);
    }
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

  // Regulatory Configuration Handlers
  const handleRegulatorySubmit = async (e) => {
    e.preventDefault();
    const url = editingId 
      ? `${API_BASE}/regulatory-configurations.php?id=${editingId}`
      : `${API_BASE}/regulatory-configurations.php`;
    
    const method = editingId ? 'PUT' : 'POST';

    try {
      setSubmitting(true);
      const response = await fetch(url, {
        method: method,
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(regulatoryForm)
      });

      // Check if response is JSON
      const contentType = response.headers.get("content-type");
      let result;
      if (contentType && contentType.includes("application/json")) {
        result = await response.json();
      } else {
        const text = await response.text();
        throw new Error(`Expected JSON but got: ${text.substring(0, 100)}`);
      }
      
      console.log('Regulatory API Response:', result);
      
      if (response.ok) {
        await fetchRegulatoryConfigs();
        resetRegulatoryForm();
        setSuccessMessage(editingId ? 'Regulatory configuration updated successfully!' : 'Regulatory configuration created successfully!');
        setTimeout(() => setSuccessMessage(null), 3000);
      } else {
        alert('Error: ' + (result.message || result.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error saving regulatory configuration:', error);
      alert('Error saving regulatory configuration: ' + error.message);
    } finally {
      setSubmitting(false);
    }
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

  // Penalty Configuration Handlers
  const handlePenaltySubmit = async (e) => {
    e.preventDefault();
    const url = editingId 
      ? `${API_BASE}/penalty-configurations.php?id=${editingId}`
      : `${API_BASE}/penalty-configurations.php`;
    
    const method = editingId ? 'PUT' : 'POST';

    try {
      setSubmitting(true);
      const response = await fetch(url, {
        method: method,
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(penaltyForm)
      });

      // Check if response is JSON
      const contentType = response.headers.get("content-type");
      let result;
      if (contentType && contentType.includes("application/json")) {
        result = await response.json();
      } else {
        const text = await response.text();
        throw new Error(`Expected JSON but got: ${text.substring(0, 100)}`);
      }
      
      console.log('Penalty API Response:', result);
      
      if (response.ok) {
        await fetchPenaltyConfigs();
        resetPenaltyForm();
        setSuccessMessage(editingId ? 'Penalty configuration updated successfully!' : 'Penalty configuration created successfully!');
        setTimeout(() => setSuccessMessage(null), 3000);
      } else {
        alert('Error: ' + (result.message || result.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error saving penalty configuration:', error);
      alert('Error saving penalty configuration: ' + error.message);
    } finally {
      setSubmitting(false);
    }
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

  // Discount Configuration Handlers
  const handleDiscountSubmit = async (e) => {
    e.preventDefault();
    const url = editingId 
      ? `${API_BASE}/discount-configurations.php?id=${editingId}`
      : `${API_BASE}/discount-configurations.php`;
    
    const method = editingId ? 'PUT' : 'POST';

    try {
      setSubmitting(true);
      const response = await fetch(url, {
        method: method,
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(discountForm)
      });

      // Check if response is JSON
      const contentType = response.headers.get("content-type");
      let result;
      if (contentType && contentType.includes("application/json")) {
        result = await response.json();
      } else {
        const text = await response.text();
        throw new Error(`Expected JSON but got: ${text.substring(0, 100)}`);
      }
      
      console.log('Discount API Response:', result);
      
      if (response.ok) {
        await fetchDiscountConfigs();
        resetDiscountForm();
        setSuccessMessage(editingId ? 'Discount configuration updated successfully!' : 'Discount configuration created successfully!');
        setTimeout(() => setSuccessMessage(null), 3000);
      } else {
        alert('Error: ' + (result.message || result.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error saving discount configuration:', error);
      alert('Error saving discount configuration: ' + error.message);
    } finally {
      setSubmitting(false);
    }
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

  // Common Handlers
  const handleDelete = async (id, type) => {
    const typeName = type === 'business' ? 'business tax' : 
                    type === 'capital' ? 'capital investment tax' :
                    type === 'regulatory' ? 'regulatory configuration' :
                    type === 'penalty' ? 'penalty configuration' : 'discount configuration';
    
    if (window.confirm(`Are you sure you want to delete this ${typeName}?`)) {
      try {
        setSubmitting(true);
        const endpoint = `${type}-configurations`;
        const response = await fetch(`${API_BASE}/${endpoint}.php?id=${id}`, {
          method: 'DELETE'
        });

        // Check if response is JSON
        const contentType = response.headers.get("content-type");
        let result;
        if (contentType && contentType.includes("application/json")) {
          result = await response.json();
        } else {
          const text = await response.text();
          throw new Error(`Expected JSON but got: ${text.substring(0, 100)}`);
        }

        if (response.ok) {
          // Refresh data
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
        } else {
          alert('Error: ' + (result.message || result.error || 'Failed to delete'));
        }
      } catch (error) {
        console.error(`Error deleting ${type}:`, error);
        alert('Error deleting configuration: ' + error.message);
      } finally {
        setSubmitting(false);
      }
    }
  };

  const handleExpire = async (id, type) => {
    const typeName = type === 'business' ? 'business tax' : 
                    type === 'capital' ? 'capital investment tax' :
                    type === 'regulatory' ? 'regulatory configuration' :
                    type === 'penalty' ? 'penalty configuration' : 'discount configuration';
    
    const today = new Date().toISOString().split('T')[0];
    
    if (window.confirm(`Are you sure you want to expire this ${typeName}?`)) {
      try {
        setSubmitting(true);
        const endpoint = `${type}-configurations`;
        const response = await fetch(`${API_BASE}/${endpoint}.php?id=${id}`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ 
            expiration_date: today
          })
        });

        // Check if response is JSON
        const contentType = response.headers.get("content-type");
        let result;
        if (contentType && contentType.includes("application/json")) {
          result = await response.json();
        } else {
          const text = await response.text();
          throw new Error(`Expected JSON but got: ${text.substring(0, 100)}`);
        }

        if (response.ok) {
          // Refresh data
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
          
          setSuccessMessage(`${typeName} expired successfully!`);
          setTimeout(() => setSuccessMessage(null), 3000);
        } else {
          alert('Error: ' + (result.message || result.error || 'Failed to expire'));
        }
      } catch (error) {
        console.error(`Error expiring ${type}:`, error);
        alert('Error expiring configuration: ' + error.message);
      } finally {
        setSubmitting(false);
      }
    }
  };

  // Form Resets
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
  };

  // Statistics with safe array handling
  const getSafeArray = (data) => Array.isArray(data) ? data : [];
  
  const businessConfigsSafe = getSafeArray(businessConfigs);
  const capitalConfigsSafe = getSafeArray(capitalConfigs);
  const regulatoryConfigsSafe = getSafeArray(regulatoryConfigs);
  const penaltyConfigsSafe = getSafeArray(penaltyConfigs);
  const discountConfigsSafe = getSafeArray(discountConfigs);

  // Calculate statistics - handle empty or invalid dates
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
          // If date parsing fails, treat as active
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

  // Function to refresh current tab data
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

  // Debug: Check what data we have
  useEffect(() => {
    console.log('Business Configs (JSON):', businessConfigsSafe);
    console.log('Capital Configs (JSON):', capitalConfigsSafe);
    console.log('Regulatory Configs (JSON):', regulatoryConfigsSafe);
    console.log('Penalty Configs (JSON):', penaltyConfigsSafe);
    console.log('Discount Configs (JSON):', discountConfigsSafe);
  }, [businessConfigs, capitalConfigs, regulatoryConfigs, penaltyConfigs, discountConfigs]);

  return (
    <div className='mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg'>
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Business Tax Configuration</h1>
        <button
          onClick={refreshCurrentTab}
          disabled={loading || submitting}
          className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {loading ? 'Refreshing...' : 'Refresh Data'}
        </button>
      </div>
      
      {/* Success Message */}
      {successMessage && (
        <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
          <div className="flex items-center">
            <div className="text-green-600 font-medium">Success:</div>
            <div className="ml-2 text-green-700">{successMessage}</div>
            <button 
              onClick={() => setSuccessMessage(null)}
              className="ml-auto text-green-600 hover:text-green-800"
            >
              ×
            </button>
          </div>
        </div>
      )}

      {/* Error Display */}
      {error && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <div className="flex items-center">
            <div className="text-red-600 font-medium">Error:</div>
            <div className="ml-2 text-red-700">{error}</div>
            <button 
              onClick={() => setError(null)}
              className="ml-auto text-red-600 hover:text-red-800"
            >
              ×
            </button>
          </div>
        </div>
      )}

      {/* Tab Navigation */}
      <div className="mb-6 border-b border-gray-200 dark:border-slate-700">
        <nav className="-mb-px flex space-x-8">
          {['business', 'capital', 'regulatory', 'penalty', 'discount'].map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`py-2 px-1 border-b-2 font-medium text-sm ${
                activeTab === tab
                  ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                  : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'
              }`}
            >
              {tab === 'business' ? 'Gross Sales Tax' : 
               tab === 'capital' ? 'Capital Investment Tax' :
               tab === 'regulatory' ? 'Regulatory Fees' :
               tab === 'penalty' ? 'Penalties' : 'Discounts'}
            </button>
          ))}
        </nav>
      </div>

      {/* Date Filter */}
      <div className="mb-6 p-4 border rounded-lg dark:border-slate-700">
        <label className="block text-sm font-medium mb-2">View Configurations Effective On:</label>
        <div className="flex items-center gap-4">
          <input
            type="date"
            value={currentDate}
            onChange={(e) => setCurrentDate(e.target.value)}
            className="p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
          />
          <button
            onClick={() => setCurrentDate(new Date().toISOString().split('T')[0])}
            className="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition-colors"
          >
            Today
          </button>
        </div>
        <p className="text-sm text-gray-600 dark:text-gray-400 mt-2">
          Showing configurations effective on or before {currentDate}
        </p>
      </div>

      {/* Statistics */}
      <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div className="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
          <h3 className="font-semibold text-blue-800 dark:text-blue-300">Gross Sales Tax</h3>
          <p className="text-2xl font-bold">{businessConfigsSafe.length}</p>
          <p className="text-sm">Active: {businessStats.active} | Expired: {businessStats.expired}</p>
        </div>
        <div className="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-lg">
          <h3 className="font-semibold text-indigo-800 dark:text-indigo-300">Capital Investment Tax</h3>
          <p className="text-2xl font-bold">{capitalConfigsSafe.length}</p>
          <p className="text-sm">Active: {capitalStats.active} | Expired: {capitalStats.expired}</p>
        </div>
        <div className="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
          <h3 className="font-semibold text-green-800 dark:text-green-300">Regulatory Fees</h3>
          <p className="text-2xl font-bold">{regulatoryConfigsSafe.length}</p>
          <p className="text-sm">Active: {regulatoryStats.active} | Expired: {regulatoryStats.expired}</p>
        </div>
        <div className="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg">
          <h3 className="font-semibold text-red-800 dark:text-red-300">Penalties</h3>
          <p className="text-2xl font-bold">{penaltyConfigsSafe.length}</p>
          <p className="text-sm">Active: {penaltyStats.active} | Expired: {penaltyStats.expired}</p>
        </div>
        <div className="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
          <h3 className="font-semibold text-purple-800 dark:text-purple-300">Discounts</h3>
          <p className="text-2xl font-bold">{discountConfigsSafe.length}</p>
          <p className="text-sm">Active: {discountStats.active} | Expired: {discountStats.expired}</p>
        </div>
      </div>

      {/* Loading State */}
      {loading && (
        <div className="text-center py-8">
          <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <p className="mt-2 text-gray-600">Loading configurations...</p>
        </div>
      )}

      {/* Business Configuration Tab (Gross Sales Tax) */}
      {activeTab === 'business' && !loading && (
        <>
          {/* Business Configuration Form */}
          <div className="mb-8">
            <h2 className="text-xl font-semibold mb-4">
              {editingType === 'business' ? 'Edit Gross Sales Tax' : 'Add New Gross Sales Tax'}
            </h2>
            <form onSubmit={handleBusinessSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-2">Business Type *</label>
                <input
                  type="text"
                  value={businessForm.business_type}
                  onChange={(e) => setBusinessForm({...businessForm, business_type: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="e.g., Retailer, Wholesaler, Service Provider"
                  required
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Tax Rate (%) *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  value={businessForm.tax_percent}
                  onChange={(e) => setBusinessForm({...businessForm, tax_percent: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="e.g., 2.00 for 2%"
                  required
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Effective Date *</label>
                <input
                  type="date"
                  value={businessForm.effective_date}
                  onChange={(e) => setBusinessForm({...businessForm, effective_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  required
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Expiration Date</label>
                <input
                  type="date"
                  value={businessForm.expiration_date}
                  onChange={(e) => setBusinessForm({...businessForm, expiration_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  disabled={submitting}
                />
                <p className="text-xs text-gray-500 mt-1">Leave empty if no expiration</p>
              </div>

              <div className="md:col-span-2">
                <label className="block text-sm font-medium mb-2">Remarks</label>
                <textarea
                  value={businessForm.remarks}
                  onChange={(e) => setBusinessForm({...businessForm, remarks: e.target.value})}
                  rows="2"
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="Additional notes about this business tax configuration..."
                  disabled={submitting}
                />
              </div>

              {/* Tax Preview */}
              {businessForm.tax_percent && (
                <div className="md:col-span-2 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                  <h4 className="font-medium mb-2">Tax Calculation Preview</h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                      <span className="font-medium">Business Type:</span>
                      <div className="text-lg">{businessForm.business_type || 'Not specified'}</div>
                    </div>
                    <div>
                      <span className="font-medium">Tax Rate:</span>
                      <div className="text-lg">{businessForm.tax_percent}%</div>
                    </div>
                  </div>
                  <p className="text-sm mt-2">
                    Example: For ₱100,000 gross sales = ₱{(100000 * (parseFloat(businessForm.tax_percent || 0) / 100)).toFixed(2)}
                  </p>
                </div>
              )}

              {/* Form Actions */}
              <div className="md:col-span-2 flex gap-4 mt-4">
                <button
                  type="submit"
                  disabled={submitting}
                  className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {submitting ? 'Saving...' : editingType === 'business' ? 'Update Gross Sales Tax' : 'Create Gross Sales Tax'}
                </button>
                <button
                  type="button"
                  onClick={resetBusinessForm}
                  disabled={submitting}
                  className="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition-colors disabled:opacity-50"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>

          {/* Business Configurations List */}
          <div>
            <h2 className="text-xl font-semibold mb-4">
              Gross Sales Tax Rates ({businessConfigsSafe.length})
            </h2>
            
            {businessConfigsSafe.length === 0 ? (
              <div className="text-center py-8 text-gray-500">
                No gross sales tax rates found for the selected date.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full border-collapse border border-gray-300 dark:border-slate-700">
                  <thead>
                    <tr className="bg-gray-100 dark:bg-slate-800">
                      <th className="border p-2 text-left">Business Type</th>
                      <th className="border p-2 text-left">Tax Rate</th>
                      <th className="border p-2 text-left">Effective Date</th>
                      <th className="border p-2 text-left">Expiration Date</th>
                      <th className="border p-2 text-left">Status</th>
                      <th className="border p-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {businessConfigsSafe.map((config) => {
                      const isExpired = config.expiration_date && new Date(config.expiration_date) <= new Date();
                      return (
                        <tr 
                          key={config.id} 
                          className={`hover:bg-gray-50 dark:hover:bg-slate-800 ${
                            isExpired ? 'bg-gray-50 dark:bg-slate-800/50 text-gray-500' : ''
                          }`}
                        >
                          <td className="border p-2">
                            <div className="font-medium">{config.business_type}</div>
                            {config.remarks && (
                              <div className="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                {config.remarks}
                              </div>
                            )}
                          </td>
                          <td className="border p-2">{config.tax_percent}%</td>
                          <td className="border p-2">{config.effective_date}</td>
                          <td className="border p-2">{config.expiration_date || '-'}</td>
                          <td className="border p-2">
                            <span className={`px-2 py-1 rounded-full text-xs ${
                              !isExpired 
                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' 
                                : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                            }`}>
                              {isExpired ? 'Expired' : 'Active'}
                            </span>
                          </td>
                          <td className="border p-2">
                            <div className="flex gap-2">
                              <button
                                onClick={() => handleBusinessEdit(config)}
                                className="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600 transition-colors"
                                disabled={isExpired || submitting}
                              >
                                Edit
                              </button>
                              {!isExpired && (
                                <button
                                  onClick={() => handleExpire(config.id, 'business')}
                                  className="bg-orange-500 text-white px-3 py-1 rounded text-sm hover:bg-orange-600 transition-colors"
                                  disabled={submitting}
                                >
                                  Expire
                                </button>
                              )}
                              <button
                                onClick={() => handleDelete(config.id, 'business')}
                                className="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition-colors"
                                disabled={submitting}
                              >
                                Delete
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      )}

      {/* Capital Investment Tax Tab */}
      {activeTab === 'capital' && !loading && (
        <>
          {/* Capital Investment Tax Form */}
          <div className="mb-8">
            <h2 className="text-xl font-semibold mb-4">
              {editingType === 'capital' ? 'Edit Capital Investment Tax' : 'Add New Capital Investment Tax'}
            </h2>
            <form onSubmit={handleCapitalSubmit} className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label className="block text-sm font-medium mb-2">Minimum Capital (₱) *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={capitalForm.min_amount}
                  onChange={(e) => setCapitalForm({...capitalForm, min_amount: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="e.g., 0.00"
                  required
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Maximum Capital (₱) *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={capitalForm.max_amount}
                  onChange={(e) => setCapitalForm({...capitalForm, max_amount: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="e.g., 5000.00"
                  required
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Tax Percentage (%) *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  value={capitalForm.tax_percent}
                  onChange={(e) => setCapitalForm({...capitalForm, tax_percent: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="e.g., 0.25 for 0.25%"
                  required
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Effective Date *</label>
                <input
                  type="date"
                  value={capitalForm.effective_date}
                  onChange={(e) => setCapitalForm({...capitalForm, effective_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  required
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Expiration Date</label>
                <input
                  type="date"
                  value={capitalForm.expiration_date}
                  onChange={(e) => setCapitalForm({...capitalForm, expiration_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  disabled={submitting}
                />
                <p className="text-xs text-gray-500 mt-1">Leave empty if no expiration</p>
              </div>

              <div className="md:col-span-3">
                <label className="block text-sm font-medium mb-2">Remarks</label>
                <textarea
                  value={capitalForm.remarks}
                  onChange={(e) => setCapitalForm({...capitalForm, remarks: e.target.value})}
                  rows="2"
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="Additional notes about this capital investment tax..."
                  disabled={submitting}
                />
              </div>

              {/* Tax Calculation Preview */}
              {capitalForm.min_amount && capitalForm.max_amount && capitalForm.tax_percent && (
                <div className="md:col-span-3 p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg">
                  <h4 className="font-medium mb-2">Tax Calculation Preview</h4>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                      <span className="font-medium">Capital Range:</span>
                      <div className="text-lg">₱{parseFloat(capitalForm.min_amount).toLocaleString()} - ₱{parseFloat(capitalForm.max_amount).toLocaleString()}</div>
                    </div>
                    <div>
                      <span className="font-medium">Tax Rate:</span>
                      <div className="text-lg">{capitalForm.tax_percent}%</div>
                    </div>
                    <div>
                      <span className="font-medium">Example Tax:</span>
                      <div className="text-lg">
                        ₱{parseFloat(capitalForm.max_amount).toLocaleString()} capital × {capitalForm.tax_percent}% = ₱{(parseFloat(capitalForm.max_amount) * (parseFloat(capitalForm.tax_percent) / 100)).toFixed(2)}
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {/* Form Actions */}
              <div className="md:col-span-3 flex gap-4 mt-4">
                <button
                  type="submit"
                  disabled={submitting}
                  className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {submitting ? 'Saving...' : editingType === 'capital' ? 'Update Capital Tax' : 'Create Capital Tax'}
                </button>
                <button
                  type="button"
                  onClick={resetCapitalForm}
                  disabled={submitting}
                  className="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition-colors disabled:opacity-50"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>

          {/* Capital Investment Tax List */}
          <div>
            <h2 className="text-xl font-semibold mb-4">
              Capital Investment Tax Brackets ({capitalConfigsSafe.length})
            </h2>
            
            {capitalConfigsSafe.length === 0 ? (
              <div className="text-center py-8 text-gray-500">
                No capital investment tax brackets found for the selected date.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full border-collapse border border-gray-300 dark:border-slate-700">
                  <thead>
                    <tr className="bg-gray-100 dark:bg-slate-800">
                      <th className="border p-2 text-left">Capital Range</th>
                      <th className="border p-2 text-left">Tax Rate</th>
                      <th className="border p-2 text-left">Effective Date</th>
                      <th className="border p-2 text-left">Expiration Date</th>
                      <th className="border p-2 text-left">Status</th>
                      <th className="border p-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {capitalConfigsSafe.map((config) => {
                      const isExpired = config.expiration_date && new Date(config.expiration_date) <= new Date();
                      return (
                        <tr 
                          key={config.id} 
                          className={`hover:bg-gray-50 dark:hover:bg-slate-800 ${
                            isExpired ? 'bg-gray-50 dark:bg-slate-800/50 text-gray-500' : ''
                          }`}
                        >
                          <td className="border p-2">
                            <div className="font-medium">
                              ₱{parseFloat(config.min_amount || 0).toLocaleString()} - ₱{parseFloat(config.max_amount || 0).toLocaleString()}
                            </div>
                            {config.remarks && (
                              <div className="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                {config.remarks}
                              </div>
                            )}
                          </td>
                          <td className="border p-2">{config.tax_percent}%</td>
                          <td className="border p-2">{config.effective_date}</td>
                          <td className="border p-2">{config.expiration_date || '-'}</td>
                          <td className="border p-2">
                            <span className={`px-2 py-1 rounded-full text-xs ${
                              !isExpired 
                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' 
                                : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                            }`}>
                              {isExpired ? 'Expired' : 'Active'}
                            </span>
                          </td>
                          <td className="border p-2">
                            <div className="flex gap-2">
                              <button
                                onClick={() => handleCapitalEdit(config)}
                                className="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600 transition-colors"
                                disabled={isExpired || submitting}
                              >
                                Edit
                              </button>
                              {!isExpired && (
                                <button
                                  onClick={() => handleExpire(config.id, 'capital')}
                                  className="bg-orange-500 text-white px-3 py-1 rounded text-sm hover:bg-orange-600 transition-colors"
                                  disabled={submitting}
                                >
                                  Expire
                                </button>
                              )}
                              <button
                                onClick={() => handleDelete(config.id, 'capital')}
                                className="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition-colors"
                                disabled={submitting}
                              >
                                Delete
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      )}

      {/* Regulatory Configuration Tab */}
      {activeTab === 'regulatory' && !loading && (
        <>
          {/* Regulatory Configuration Form */}
          <div className="mb-8">
            <h2 className="text-xl font-semibold mb-4">
              {editingType === 'regulatory' ? 'Edit Regulatory Fee Configuration' : 'Add New Regulatory Fee Configuration'}
            </h2>
            <form onSubmit={handleRegulatorySubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-2">Fee Name *</label>
                <input
                  type="text"
                  value={regulatoryForm.fee_name}
                  onChange={(e) => setRegulatoryForm({...regulatoryForm, fee_name: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="e.g., Mayor Permit Fee, Sanitary Fee, Registration Fee"
                  required
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Amount (₱) *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={regulatoryForm.amount}
                  onChange={(e) => setRegulatoryForm({...regulatoryForm, amount: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.00"
                  required
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Effective Date *</label>
                <input
                  type="date"
                  value={regulatoryForm.effective_date}
                  onChange={(e) => setRegulatoryForm({...regulatoryForm, effective_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  required
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Expiration Date</label>
                <input
                  type="date"
                  value={regulatoryForm.expiration_date}
                  onChange={(e) => setRegulatoryForm({...regulatoryForm, expiration_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  disabled={submitting}
                />
                <p className="text-xs text-gray-500 mt-1">Leave empty if no expiration</p>
              </div>

              <div className="md:col-span-2">
                <label className="block text-sm font-medium mb-2">Remarks</label>
                <textarea
                  value={regulatoryForm.remarks}
                  onChange={(e) => setRegulatoryForm({...regulatoryForm, remarks: e.target.value})}
                  rows="2"
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="Additional details about this regulatory fee..."
                  disabled={submitting}
                />
              </div>

              {/* Form Actions */}
              <div className="md:col-span-2 flex gap-4 mt-4">
                <button
                  type="submit"
                  disabled={submitting}
                  className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {submitting ? 'Saving...' : editingType === 'regulatory' ? 'Update Regulatory Fee' : 'Create Regulatory Fee'}
                </button>
                <button
                  type="button"
                  onClick={resetRegulatoryForm}
                  disabled={submitting}
                  className="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition-colors disabled:opacity-50"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>

          {/* Regulatory Configurations List */}
          <div>
            <h2 className="text-xl font-semibold mb-4">
              Regulatory Fee Configurations ({regulatoryConfigsSafe.length})
            </h2>
            
            {regulatoryConfigsSafe.length === 0 ? (
              <div className="text-center py-8 text-gray-500">
                No regulatory fee configurations found for the selected date.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full border-collapse border border-gray-300 dark:border-slate-700">
                  <thead>
                    <tr className="bg-gray-100 dark:bg-slate-800">
                      <th className="border p-2 text-left">Fee Name</th>
                      <th className="border p-2 text-left">Amount</th>
                      <th className="border p-2 text-left">Effective Date</th>
                      <th className="border p-2 text-left">Expiration Date</th>
                      <th className="border p-2 text-left">Status</th>
                      <th className="border p-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {regulatoryConfigsSafe.map((config) => {
                      const isExpired = config.expiration_date && new Date(config.expiration_date) <= new Date();
                      
                      return (
                        <tr 
                          key={config.id} 
                          className={`hover:bg-gray-50 dark:hover:bg-slate-800 ${
                            isExpired ? 'bg-gray-50 dark:bg-slate-800/50 text-gray-500' : ''
                          }`}
                        >
                          <td className="border p-2">
                            <div className="font-medium">{config.fee_name}</div>
                            {config.remarks && (
                              <div className="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                {config.remarks}
                              </div>
                            )}
                          </td>
                          <td className="border p-2">₱{parseFloat(config.amount || 0).toLocaleString()}</td>
                          <td className="border p-2">{config.effective_date}</td>
                          <td className="border p-2">{config.expiration_date || '-'}</td>
                          <td className="border p-2">
                            <span className={`px-2 py-1 rounded-full text-xs ${
                              !isExpired 
                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' 
                                : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                            }`}>
                              {isExpired ? 'Expired' : 'Active'}
                            </span>
                          </td>
                          <td className="border p-2">
                            <div className="flex gap-2">
                              <button
                                onClick={() => handleRegulatoryEdit(config)}
                                className="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600 transition-colors"
                                disabled={isExpired || submitting}
                              >
                                Edit
                              </button>
                              {!isExpired && (
                                <button
                                  onClick={() => handleExpire(config.id, 'regulatory')}
                                  className="bg-orange-500 text-white px-3 py-1 rounded text-sm hover:bg-orange-600 transition-colors"
                                  disabled={submitting}
                                >
                                  Expire
                                </button>
                              )}
                              <button
                                onClick={() => handleDelete(config.id, 'regulatory')}
                                className="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition-colors"
                                disabled={submitting}
                              >
                                Delete
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      )}

      {/* Penalty Configuration Tab */}
      {activeTab === 'penalty' && !loading && (
        <>
          {/* Penalty Configuration Form */}
          <div className="mb-8">
            <h2 className="text-xl font-semibold mb-4">
              {editingType === 'penalty' ? 'Edit Penalty Configuration' : 'Add New Penalty Configuration'}
            </h2>
            <form onSubmit={handlePenaltySubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-2">Penalty Percentage (%) *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  value={penaltyForm.penalty_percent}
                  onChange={(e) => setPenaltyForm({...penaltyForm, penalty_percent: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.00"
                  required
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Effective Date *</label>
                <input
                  type="date"
                  value={penaltyForm.effective_date}
                  onChange={(e) => setPenaltyForm({...penaltyForm, effective_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  required
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Expiration Date</label>
                <input
                  type="date"
                  value={penaltyForm.expiration_date}
                  onChange={(e) => setPenaltyForm({...penaltyForm, expiration_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  disabled={submitting}
                />
                <p className="text-xs text-gray-500 mt-1">Leave empty if no expiration</p>
              </div>

              <div className="md:col-span-2">
                <label className="block text-sm font-medium mb-2">Remarks</label>
                <textarea
                  value={penaltyForm.remarks}
                  onChange={(e) => setPenaltyForm({...penaltyForm, remarks: e.target.value})}
                  rows="2"
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="Additional details about this penalty..."
                  disabled={submitting}
                />
              </div>

              {/* Form Actions */}
              <div className="md:col-span-2 flex gap-4 mt-4">
                <button
                  type="submit"
                  disabled={submitting}
                  className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {submitting ? 'Saving...' : editingType === 'penalty' ? 'Update Penalty' : 'Create Penalty'}
                </button>
                <button
                  type="button"
                  onClick={resetPenaltyForm}
                  disabled={submitting}
                  className="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition-colors disabled:opacity-50"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>

          {/* Penalty Configurations List */}
          <div>
            <h2 className="text-xl font-semibold mb-4">
              Penalty Configurations ({penaltyConfigsSafe.length})
            </h2>
            
            {penaltyConfigsSafe.length === 0 ? (
              <div className="text-center py-8 text-gray-500">
                No penalty configurations found for the selected date.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full border-collapse border border-gray-300 dark:border-slate-700">
                  <thead>
                    <tr className="bg-gray-100 dark:bg-slate-800">
                      <th className="border p-2 text-left">Penalty Rate</th>
                      <th className="border p-2 text-left">Effective Date</th>
                      <th className="border p-2 text-left">Expiration Date</th>
                      <th className="border p-2 text-left">Status</th>
                      <th className="border p-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {penaltyConfigsSafe.map((config) => {
                      const isExpired = config.expiration_date && new Date(config.expiration_date) <= new Date();
                      return (
                        <tr 
                          key={config.id} 
                          className={`hover:bg-gray-50 dark:hover:bg-slate-800 ${
                            isExpired ? 'bg-gray-50 dark:bg-slate-800/50 text-gray-500' : ''
                          }`}
                        >
                          <td className="border p-2">
                            <div className="font-medium">{config.penalty_percent}%</div>
                            {config.remarks && (
                              <div className="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                {config.remarks}
                              </div>
                            )}
                          </td>
                          <td className="border p-2">{config.effective_date}</td>
                          <td className="border p-2">{config.expiration_date || '-'}</td>
                          <td className="border p-2">
                            <span className={`px-2 py-1 rounded-full text-xs ${
                              !isExpired 
                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' 
                                : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                            }`}>
                              {isExpired ? 'Expired' : 'Active'}
                            </span>
                          </td>
                          <td className="border p-2">
                            <div className="flex gap-2">
                              <button
                                onClick={() => handlePenaltyEdit(config)}
                                className="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600 transition-colors"
                                disabled={isExpired || submitting}
                              >
                                Edit
                              </button>
                              {!isExpired && (
                                <button
                                  onClick={() => handleExpire(config.id, 'penalty')}
                                  className="bg-orange-500 text-white px-3 py-1 rounded text-sm hover:bg-orange-600 transition-colors"
                                  disabled={submitting}
                                >
                                  Expire
                                </button>
                              )}
                              <button
                                onClick={() => handleDelete(config.id, 'penalty')}
                                className="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition-colors"
                                disabled={submitting}
                              >
                                Delete
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      )}

      {/* Discount Configuration Tab */}
      {activeTab === 'discount' && !loading && (
        <>
          {/* Discount Configuration Form */}
          <div className="mb-8">
            <h2 className="text-xl font-semibold mb-4">
              {editingType === 'discount' ? 'Edit Discount Configuration' : 'Add New Discount Configuration'}
            </h2>
            <form onSubmit={handleDiscountSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-2">Discount Percentage (%) *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  value={discountForm.discount_percent}
                  onChange={(e) => setDiscountForm({...discountForm, discount_percent: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.00"
                  required
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Effective Date *</label>
                <input
                  type="date"
                  value={discountForm.effective_date}
                  onChange={(e) => setDiscountForm({...discountForm, effective_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  required
                  disabled={submitting}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Expiration Date</label>
                <input
                  type="date"
                  value={discountForm.expiration_date}
                  onChange={(e) => setDiscountForm({...discountForm, expiration_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  disabled={submitting}
                />
                <p className="text-xs text-gray-500 mt-1">Leave empty if no expiration</p>
              </div>

              <div className="md:col-span-2">
                <label className="block text-sm font-medium mb-2">Remarks</label>
                <textarea
                  value={discountForm.remarks}
                  onChange={(e) => setDiscountForm({...discountForm, remarks: e.target.value})}
                  rows="2"
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="Additional details about this discount..."
                  disabled={submitting}
                />
              </div>

              {/* Form Actions */}
              <div className="md:col-span-2 flex gap-4 mt-4">
                <button
                  type="submit"
                  disabled={submitting}
                  className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {submitting ? 'Saving...' : editingType === 'discount' ? 'Update Discount' : 'Create Discount'}
                </button>
                <button
                  type="button"
                  onClick={resetDiscountForm}
                  disabled={submitting}
                  className="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition-colors disabled:opacity-50"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>

          {/* Discount Configurations List */}
          <div>
            <h2 className="text-xl font-semibold mb-4">
              Discount Configurations ({discountConfigsSafe.length})
            </h2>
            
            {discountConfigsSafe.length === 0 ? (
              <div className="text-center py-8 text-gray-500">
                No discount configurations found for the selected date.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full border-collapse border border-gray-300 dark:border-slate-700">
                  <thead>
                    <tr className="bg-gray-100 dark:bg-slate-800">
                      <th className="border p-2 text-left">Discount Rate</th>
                      <th className="border p-2 text-left">Effective Date</th>
                      <th className="border p-2 text-left">Expiration Date</th>
                      <th className="border p-2 text-left">Status</th>
                      <th className="border p-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {discountConfigsSafe.map((config) => {
                      const isExpired = config.expiration_date && new Date(config.expiration_date) <= new Date();
                      return (
                        <tr 
                          key={config.id} 
                          className={`hover:bg-gray-50 dark:hover:bg-slate-800 ${
                            isExpired ? 'bg-gray-50 dark:bg-slate-800/50 text-gray-500' : ''
                          }`}
                        >
                          <td className="border p-2">
                            <div className="font-medium">{config.discount_percent}%</div>
                            {config.remarks && (
                              <div className="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                {config.remarks}
                              </div>
                            )}
                          </td>
                          <td className="border p-2">{config.effective_date}</td>
                          <td className="border p-2">{config.expiration_date || '-'}</td>
                          <td className="border p-2">
                            <span className={`px-2 py-1 rounded-full text-xs ${
                              !isExpired 
                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' 
                                : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                            }`}>
                              {isExpired ? 'Expired' : 'Active'}
                            </span>
                          </td>
                          <td className="border p-2">
                            <div className="flex gap-2">
                              <button
                                onClick={() => handleDiscountEdit(config)}
                                className="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600 transition-colors"
                                disabled={isExpired || submitting}
                              >
                                Edit
                              </button>
                              {!isExpired && (
                                <button
                                  onClick={() => handleExpire(config.id, 'discount')}
                                  className="bg-orange-500 text-white px-3 py-1 rounded text-sm hover:bg-orange-600 transition-colors"
                                  disabled={submitting}
                                >
                                  Expire
                                </button>
                              )}
                              <button
                                onClick={() => handleDelete(config.id, 'discount')}
                                className="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition-colors"
                                disabled={submitting}
                              >
                                Delete
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      )}
    </div>
  );
}