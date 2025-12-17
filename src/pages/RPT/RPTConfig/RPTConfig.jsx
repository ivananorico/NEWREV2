import { useState, useEffect } from 'react';

export default function RPTConfig() {
  const [activeTab, setActiveTab] = useState('land');
  const [landConfigurations, setLandConfigurations] = useState([]);
  const [propertyConfigurations, setPropertyConfigurations] = useState([]);
  const [buildingAssessmentLevels, setBuildingAssessmentLevels] = useState([]);
  const [taxConfigurations, setTaxConfigurations] = useState([]);
  const [discountConfigurations, setDiscountConfigurations] = useState([]);
  const [penaltyConfigurations, setPenaltyConfigurations] = useState([]);
  const [currentDate, setCurrentDate] = useState(new Date().toISOString().split('T')[0]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  // Land Configuration Form
  const [landFormData, setLandFormData] = useState({
    classification: '',
    market_value: '',
    assessment_level: '',
    description: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    status: 'active'
  });

  // Property Configuration Form
  const [propertyFormData, setPropertyFormData] = useState({
    classification: '',
    material_type: '',
    unit_cost: '',
    depreciation_rate: '',
    min_value: '',
    max_value: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    status: 'active'
  });

  // Building Assessment Level Form
  const [buildingAssessmentFormData, setBuildingAssessmentFormData] = useState({
    classification: '',
    min_assessed_value: '',
    max_assessed_value: '',
    level_percent: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    status: 'active'
  });

  // Tax Configuration Form
  const [taxFormData, setTaxFormData] = useState({
    tax_name: '',
    tax_percent: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    status: 'active'
  });

  // Discount Configuration Form
  const [discountFormData, setDiscountFormData] = useState({
    discount_percent: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    status: 'active'
  });

  // Penalty Configuration Form
  const [penaltyFormData, setPenaltyFormData] = useState({
    penalty_percent: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    status: 'active'
  });

  const [editingId, setEditingId] = useState(null);
  const [editingType, setEditingType] = useState(null);

  // Safe array variables to prevent errors
  const landConfigurationsSafe = Array.isArray(landConfigurations) ? landConfigurations : [];
  const propertyConfigurationsSafe = Array.isArray(propertyConfigurations) ? propertyConfigurations : [];
  const buildingAssessmentLevelsSafe = Array.isArray(buildingAssessmentLevels) ? buildingAssessmentLevels : [];
  const taxConfigurationsSafe = Array.isArray(taxConfigurations) ? taxConfigurations : [];
  const discountConfigurationsSafe = Array.isArray(discountConfigurations) ? discountConfigurations : [];
  const penaltyConfigurationsSafe = Array.isArray(penaltyConfigurations) ? penaltyConfigurations : [];

  // API Base URL
  const isProduction = window.location.hostname.includes('goserveph.com');
  const API_BASE = isProduction 
    ? "/backend/RPT/RPTConfig"
    : "http://localhost/revenue/backend/RPT/RPTConfig";

  // Fetch all data
  const fetchLandConfigurations = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(`${API_BASE}/land-configurations.php`);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      // Handle both array and object responses
      if (Array.isArray(data)) {
        setLandConfigurations(data);
      } else if (data && data.error) {
        throw new Error(data.error);
      } else {
        setLandConfigurations([data]);
      }
    } catch (error) {
      console.error('Error fetching land configurations:', error);
      setError('Failed to load land configurations: ' + error.message);
      setLandConfigurations([]);
    } finally {
      setLoading(false);
    }
  };

  const fetchPropertyConfigurations = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await fetch(`${API_BASE}/property-configurations.php`);
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();
      setPropertyConfigurations(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching property configurations:', error);
      setError('Failed to load property configurations: ' + error.message);
      setPropertyConfigurations([]);
    } finally {
      setLoading(false);
    }
  };

  const fetchBuildingAssessmentLevels = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await fetch(`${API_BASE}/building-assessment-levels.php`);
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();
      setBuildingAssessmentLevels(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching building assessment levels:', error);
      setError('Failed to load building assessment levels: ' + error.message);
      setBuildingAssessmentLevels([]);
    } finally {
      setLoading(false);
    }
  };

  const fetchTaxConfigurations = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await fetch(`${API_BASE}/tax-configurations.php`);
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();
      setTaxConfigurations(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching tax configurations:', error);
      setError('Failed to load tax configurations: ' + error.message);
      setTaxConfigurations([]);
    } finally {
      setLoading(false);
    }
  };

  const fetchDiscountConfigurations = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await fetch(`${API_BASE}/discount-configurations.php`);
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();
      setDiscountConfigurations(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching discount configurations:', error);
      setError('Failed to load discount configurations: ' + error.message);
      setDiscountConfigurations([]);
    } finally {
      setLoading(false);
    }
  };

  const fetchPenaltyConfigurations = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await fetch(`${API_BASE}/penalty-configurations.php`);
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();
      setPenaltyConfigurations(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching penalty configurations:', error);
      setError('Failed to load penalty configurations: ' + error.message);
      setPenaltyConfigurations([]);
    } finally {
      setLoading(false);
    }
  };

  // Fetch all data on component mount
  useEffect(() => {
    fetchLandConfigurations();
    fetchPropertyConfigurations();
    fetchBuildingAssessmentLevels();
    fetchTaxConfigurations();
    fetchDiscountConfigurations();
    fetchPenaltyConfigurations();
  }, []);

  // Refresh data when tab changes
  useEffect(() => {
    switch(activeTab) {
      case 'land':
        fetchLandConfigurations();
        break;
      case 'property':
        fetchPropertyConfigurations();
        break;
      case 'building-assessment':
        fetchBuildingAssessmentLevels();
        break;
      case 'tax':
        fetchTaxConfigurations();
        break;
      case 'discount-penalty':
        fetchDiscountConfigurations();
        fetchPenaltyConfigurations();
        break;
    }
  }, [activeTab]);

  // Form Handlers
  const handleLandSubmit = async (e) => {
    e.preventDefault();
    const url = editingId ? `${API_BASE}/land-configurations.php?id=${editingId}` : `${API_BASE}/land-configurations.php`;
    const method = editingId ? 'PUT' : 'POST';

    try {
      const response = await fetch(url, {
        method: method,
        headers: { 
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(landFormData)
      });
      
      const result = await response.json();
      
      if (response.ok || result.success) {
        fetchLandConfigurations();
        resetLandForm();
        alert(editingId ? 'Land configuration updated!' : 'Land configuration created!');
      } else {
        alert('Error: ' + (result.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error saving land configuration:', error);
      alert('Error saving land configuration: ' + error.message);
    }
  };

  const handlePropertySubmit = async (e) => {
    e.preventDefault();
    const url = editingId ? `${API_BASE}/property-configurations.php?id=${editingId}` : `${API_BASE}/property-configurations.php`;
    const method = editingId ? 'PUT' : 'POST';

    try {
      const response = await fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(propertyFormData)
      });
      const result = await response.json();
      if (response.ok || result.success) {
        fetchPropertyConfigurations();
        resetPropertyForm();
        alert(editingId ? 'Property configuration updated!' : 'Property configuration created!');
      } else {
        alert('Error: ' + (result.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error saving property configuration:', error);
      alert('Error saving property configuration: ' + error.message);
    }
  };

  const handleBuildingAssessmentSubmit = async (e) => {
    e.preventDefault();
    const url = editingId ? `${API_BASE}/building-assessment-levels.php?id=${editingId}` : `${API_BASE}/building-assessment-levels.php`;
    const method = editingId ? 'PUT' : 'POST';

    try {
      const response = await fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(buildingAssessmentFormData)
      });
      const result = await response.json();
      if (response.ok || result.success) {
        fetchBuildingAssessmentLevels();
        resetBuildingAssessmentForm();
        alert(editingId ? 'Building assessment level updated!' : 'Building assessment level created!');
      } else {
        alert('Error: ' + (result.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error saving building assessment level:', error);
      alert('Error saving building assessment level: ' + error.message);
    }
  };

  const handleTaxSubmit = async (e) => {
    e.preventDefault();
    
    if (!['Basic Tax', 'SEF Tax'].includes(taxFormData.tax_name)) {
      alert('Tax name must be either "Basic Tax" or "SEF Tax"');
      return;
    }

    const url = editingId ? `${API_BASE}/tax-configurations.php?id=${editingId}` : `${API_BASE}/tax-configurations.php`;
    const method = editingId ? 'PUT' : 'POST';

    try {
      const response = await fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(taxFormData)
      });
      const result = await response.json();
      if (response.ok || result.success) {
        fetchTaxConfigurations();
        resetTaxForm();
        alert(editingId ? 'Tax configuration updated!' : 'Tax configuration created!');
      } else {
        alert('Error: ' + (result.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error saving tax configuration:', error);
      alert('Error saving tax configuration: ' + error.message);
    }
  };

  const handleDiscountSubmit = async (e) => {
    e.preventDefault();
    const url = editingId ? `${API_BASE}/discount-configurations.php?id=${editingId}` : `${API_BASE}/discount-configurations.php`;
    const method = editingId ? 'PUT' : 'POST';

    try {
      const response = await fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(discountFormData)
      });
      const result = await response.json();
      if (response.ok || result.success) {
        fetchDiscountConfigurations();
        resetDiscountForm();
        alert(editingId ? 'Discount configuration updated!' : 'Discount configuration created!');
      } else {
        alert('Error: ' + (result.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error saving discount configuration:', error);
      alert('Error saving discount configuration: ' + error.message);
    }
  };

  const handlePenaltySubmit = async (e) => {
    e.preventDefault();
    const url = editingId ? `${API_BASE}/penalty-configurations.php?id=${editingId}` : `${API_BASE}/penalty-configurations.php`;
    const method = editingId ? 'PUT' : 'POST';

    try {
      const response = await fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(penaltyFormData)
      });
      const result = await response.json();
      if (response.ok || result.success) {
        fetchPenaltyConfigurations();
        resetPenaltyForm();
        alert(editingId ? 'Penalty configuration updated!' : 'Penalty configuration created!');
      } else {
        alert('Error: ' + (result.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error saving penalty configuration:', error);
      alert('Error saving penalty configuration: ' + error.message);
    }
  };

  // Edit Handlers
  const handleLandEdit = (config) => {
    setLandFormData({
      classification: config.classification || '',
      market_value: config.market_value || '',
      assessment_level: config.assessment_level || '',
      description: config.description || '',
      effective_date: config.effective_date || new Date().toISOString().split('T')[0],
      expiration_date: config.expiration_date || '',
      status: config.status || 'active'
    });
    setEditingId(config.id);
    setEditingType('land');
  };

  const handlePropertyEdit = (config) => {
    setPropertyFormData({
      classification: config.classification || '',
      material_type: config.material_type || '',
      unit_cost: config.unit_cost || '',
      depreciation_rate: config.depreciation_rate || '',
      min_value: config.min_value || '',
      max_value: config.max_value || '',
      effective_date: config.effective_date || new Date().toISOString().split('T')[0],
      expiration_date: config.expiration_date || '',
      status: config.status || 'active'
    });
    setEditingId(config.id);
    setEditingType('property');
  };

  const handleBuildingAssessmentEdit = (config) => {
    setBuildingAssessmentFormData({
      classification: config.classification || '',
      min_assessed_value: config.min_assessed_value || '',
      max_assessed_value: config.max_assessed_value || '',
      level_percent: config.level_percent || '',
      effective_date: config.effective_date || new Date().toISOString().split('T')[0],
      expiration_date: config.expiration_date || '',
      status: config.status || 'active'
    });
    setEditingId(config.id);
    setEditingType('building-assessment');
  };

  const handleTaxEdit = (config) => {
    setTaxFormData({
      tax_name: config.tax_name || '',
      tax_percent: config.tax_percent || '',
      effective_date: config.effective_date || new Date().toISOString().split('T')[0],
      expiration_date: config.expiration_date || '',
      status: config.status || 'active'
    });
    setEditingId(config.id);
    setEditingType('tax');
  };

  const handleDiscountEdit = (config) => {
    setDiscountFormData({
      discount_percent: config.discount_percent || '',
      effective_date: config.effective_date || new Date().toISOString().split('T')[0],
      expiration_date: config.expiration_date || '',
      status: config.status || 'active'
    });
    setEditingId(config.id);
    setEditingType('discount');
  };

  const handlePenaltyEdit = (config) => {
    setPenaltyFormData({
      penalty_percent: config.penalty_percent || '',
      effective_date: config.effective_date || new Date().toISOString().split('T')[0],
      expiration_date: config.expiration_date || '',
      status: config.status || 'active'
    });
    setEditingId(config.id);
    setEditingType('penalty');
  };

  // Delete and Expire Handlers
  const handleDelete = async (id, type) => {
    const typeName = type.replace('-configurations', '').replace('-', ' ').replace('-levels', ' levels');
    if (window.confirm(`Are you sure you want to delete this ${typeName} configuration?`)) {
      try {
        const response = await fetch(`${API_BASE}/${type}.php?id=${id}`, { 
          method: 'DELETE' 
        });
        
        const result = await response.json();
        
        if (response.ok || result.success) {
          // Refresh the current tab's data
          switch(type) {
            case 'land-configurations':
              fetchLandConfigurations();
              break;
            case 'property-configurations':
              fetchPropertyConfigurations();
              break;
            case 'building-assessment-levels':
              fetchBuildingAssessmentLevels();
              break;
            case 'tax-configurations':
              fetchTaxConfigurations();
              break;
            case 'discount-configurations':
              fetchDiscountConfigurations();
              break;
            case 'penalty-configurations':
              fetchPenaltyConfigurations();
              break;
          }
          alert(`${typeName} configuration deleted successfully!`);
        } else {
          alert('Error: ' + (result.error || 'Failed to delete'));
        }
      } catch (error) {
        console.error(`Error deleting ${type}:`, error);
        alert('Error deleting configuration: ' + error.message);
      }
    }
  };

  const handleExpire = async (id, type) => {
    const typeName = type.replace('-configurations', '').replace('-', ' ').replace('-levels', ' levels');
    if (window.confirm(`Are you sure you want to expire this ${typeName}?`)) {
      try {
        const response = await fetch(`${API_BASE}/${type}.php?id=${id}`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ 
            status: 'expired',
            expiration_date: new Date().toISOString().split('T')[0]
          })
        });
        const result = await response.json();
        if (response.ok || result.success) {
          switch(type) {
            case 'land-configurations':
              fetchLandConfigurations();
              break;
            case 'property-configurations':
              fetchPropertyConfigurations();
              break;
            case 'building-assessment-levels':
              fetchBuildingAssessmentLevels();
              break;
            case 'tax-configurations':
              fetchTaxConfigurations();
              break;
            case 'discount-configurations':
              fetchDiscountConfigurations();
              break;
            case 'penalty-configurations':
              fetchPenaltyConfigurations();
              break;
          }
          alert(`${typeName} configuration expired successfully!`);
        } else {
          alert('Error: ' + result.error);
        }
      } catch (error) {
        console.error(`Error expiring ${type}:`, error);
        alert('Error expiring configuration');
      }
    }
  };

  // Reset Form Functions
  const resetLandForm = () => {
    setLandFormData({
      classification: '',
      market_value: '',
      assessment_level: '',
      description: '',
      effective_date: new Date().toISOString().split('T')[0],
      expiration_date: '',
      status: 'active'
    });
    setEditingId(null);
    setEditingType(null);
  };

  const resetPropertyForm = () => {
    setPropertyFormData({
      classification: '',
      material_type: '',
      unit_cost: '',
      depreciation_rate: '',
      min_value: '',
      max_value: '',
      effective_date: new Date().toISOString().split('T')[0],
      expiration_date: '',
      status: 'active'
    });
    setEditingId(null);
    setEditingType(null);
  };

  const resetBuildingAssessmentForm = () => {
    setBuildingAssessmentFormData({
      classification: '',
      min_assessed_value: '',
      max_assessed_value: '',
      level_percent: '',
      effective_date: new Date().toISOString().split('T')[0],
      expiration_date: '',
      status: 'active'
    });
    setEditingId(null);
    setEditingType(null);
  };

  const resetTaxForm = () => {
    setTaxFormData({
      tax_name: '',
      tax_percent: '',
      effective_date: new Date().toISOString().split('T')[0],
      expiration_date: '',
      status: 'active'
    });
    setEditingId(null);
    setEditingType(null);
  };

  const resetDiscountForm = () => {
    setDiscountFormData({
      discount_percent: '',
      effective_date: new Date().toISOString().split('T')[0],
      expiration_date: '',
      status: 'active'
    });
    setEditingId(null);
    setEditingType(null);
  };

  const resetPenaltyForm = () => {
    setPenaltyFormData({
      penalty_percent: '',
      effective_date: new Date().toISOString().split('T')[0],
      expiration_date: '',
      status: 'active'
    });
    setEditingId(null);
    setEditingType(null);
  };

  // Statistics
  const activeLandConfigs = landConfigurationsSafe.filter(config => config.status === 'active').length;
  const activePropertyConfigs = propertyConfigurationsSafe.filter(config => config.status === 'active').length;
  const activeBuildingAssessmentConfigs = buildingAssessmentLevelsSafe.filter(config => config.status === 'active').length;
  const activeTaxConfigs = taxConfigurationsSafe.filter(config => config.status === 'active').length;
  const activeDiscountConfigs = discountConfigurationsSafe.filter(config => config.status === 'active').length;
  const activePenaltyConfigs = penaltyConfigurationsSafe.filter(config => config.status === 'active').length;

  // Check if Basic Tax and SEF Tax already exist
  const basicTaxExists = taxConfigurationsSafe.some(tax => tax.tax_name === 'Basic Tax' && tax.status === 'active');
  const sefTaxExists = taxConfigurationsSafe.some(tax => tax.tax_name === 'SEF Tax' && tax.status === 'active');

  return (
    <div className='mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg'>
      <h1 className="text-2xl font-bold mb-6">Real Property Tax Configuration</h1>
      
      {error && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
          <div className="flex items-center">
            <div className="text-red-600 font-medium">Error:</div>
            <div className="ml-2 text-red-700">{error}</div>
            <button onClick={() => setError(null)} className="ml-auto text-red-600 hover:text-red-800">×</button>
          </div>
        </div>
      )}

      {/* Tab Navigation */}
      <div className="mb-6 border-b border-gray-200 dark:border-slate-700">
        <nav className="-mb-px flex space-x-8">
          {['land', 'property', 'building-assessment', 'tax', 'discount-penalty'].map(tab => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`py-2 px-1 border-b-2 font-medium text-sm ${
                activeTab === tab
                  ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                  : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'
              }`}
            >
              {tab === 'discount-penalty' ? 'Discount & Penalty' : 
               tab === 'building-assessment' ? 'Building Assessment' :
               tab.charAt(0).toUpperCase() + tab.slice(1)} Configurations
            </button>
          ))}
        </nav>
      </div>

      {/* Statistics */}
      <div className="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
        <div className="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
          <h3 className="font-semibold text-blue-800 dark:text-blue-300">Land Configs</h3>
          <p className="text-2xl font-bold">{landConfigurationsSafe.length}</p>
          <p className="text-sm">Active: {activeLandConfigs}</p>
        </div>
        <div className="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
          <h3 className="font-semibold text-green-800 dark:text-green-300">Property Configs</h3>
          <p className="text-2xl font-bold">{propertyConfigurationsSafe.length}</p>
          <p className="text-sm">Active: {activePropertyConfigs}</p>
        </div>
        <div className="bg-teal-50 dark:bg-teal-900/20 p-4 rounded-lg">
          <h3 className="font-semibold text-teal-800 dark:text-teal-300">Building Assessment</h3>
          <p className="text-2xl font-bold">{buildingAssessmentLevelsSafe.length}</p>
          <p className="text-sm">Active: {activeBuildingAssessmentConfigs}</p>
        </div>
        <div className="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
          <h3 className="font-semibold text-purple-800 dark:text-purple-300">Tax Configs</h3>
          <p className="text-2xl font-bold">{taxConfigurationsSafe.length}</p>
          <p className="text-sm">Active: {activeTaxConfigs}</p>
        </div>
        <div className="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg">
          <h3 className="font-semibold text-orange-800 dark:text-orange-300">Discount/Penalty</h3>
          <p className="text-2xl font-bold">{discountConfigurationsSafe.length + penaltyConfigurationsSafe.length}</p>
          <p className="text-sm">Active: {activeDiscountConfigs + activePenaltyConfigs}</p>
        </div>
        <div className="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-lg">
          <h3 className="font-semibold text-indigo-800 dark:text-indigo-300">Total Active</h3>
          <p className="text-2xl font-bold">{activeLandConfigs + activePropertyConfigs + activeBuildingAssessmentConfigs + activeTaxConfigs + activeDiscountConfigs + activePenaltyConfigs}</p>
        </div>
      </div>

      {loading && (
        <div className="text-center py-8">
          <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <p className="mt-2 text-gray-600">Loading configurations...</p>
        </div>
      )}

      {/* Land Configuration Tab */}
      {activeTab === 'land' && !loading && (
        <>
          <div className="mb-8">
            <h2 className="text-xl font-semibold mb-4">
              {editingType === 'land' ? 'Edit Land Configuration' : 'Add New Land Configuration'}
            </h2>
            <form onSubmit={handleLandSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-2">Classification *</label>
                <input
                  type="text"
                  value={landFormData.classification}
                  onChange={(e) => setLandFormData({...landFormData, classification: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="e.g., Residential, Commercial, Agricultural"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Market Value (per sqm) *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={landFormData.market_value}
                  onChange={(e) => setLandFormData({...landFormData, market_value: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.00"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Assessment Level (%) *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  value={landFormData.assessment_level}
                  onChange={(e) => setLandFormData({...landFormData, assessment_level: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.00"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Status</label>
                <select
                  value={landFormData.status}
                  onChange={(e) => setLandFormData({...landFormData, status: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                >
                  <option value="active">Active</option>
                  <option value="expired">Expired</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Effective Date *</label>
                <input
                  type="date"
                  value={landFormData.effective_date}
                  onChange={(e) => setLandFormData({...landFormData, effective_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Expiration Date</label>
                <input
                  type="date"
                  value={landFormData.expiration_date}
                  onChange={(e) => setLandFormData({...landFormData, expiration_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                />
              </div>
              <div className="md:col-span-2">
                <label className="block text-sm font-medium mb-2">Description</label>
                <textarea
                  value={landFormData.description}
                  onChange={(e) => setLandFormData({...landFormData, description: e.target.value})}
                  rows="3"
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="Additional details..."
                />
              </div>
              <div className="md:col-span-2 flex gap-4 mt-4">
                <button type="submit" className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition-colors">
                  {editingType === 'land' ? 'Update Land Configuration' : 'Create Land Configuration'}
                </button>
                <button type="button" onClick={resetLandForm} className="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition-colors">
                  Cancel
                </button>
                <button 
                  type="button" 
                  onClick={fetchLandConfigurations}
                  className="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600 transition-colors"
                >
                  Refresh List
                </button>
              </div>
            </form>
          </div>

          <div>
            <h2 className="text-xl font-semibold mb-4">Land Configurations ({landConfigurationsSafe.length})</h2>
            {landConfigurationsSafe.length === 0 ? (
              <div className="text-center py-8 text-gray-500">
                <p>No land configurations found.</p>
                <button 
                  onClick={fetchLandConfigurations} 
                  className="mt-2 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                >
                  Try Loading Again
                </button>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full border-collapse border border-gray-300 dark:border-slate-700">
                  <thead>
                    <tr className="bg-gray-100 dark:bg-slate-800">
                      <th className="border p-2 text-left">ID</th>
                      <th className="border p-2 text-left">Classification</th>
                      <th className="border p-2 text-left">Market Value</th>
                      <th className="border p-2 text-left">Assessment Level</th>
                      <th className="border p-2 text-left">Assessed Value</th>
                      <th className="border p-2 text-left">Effective Date</th>
                      <th className="border p-2 text-left">Status</th>
                      <th className="border p-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {landConfigurationsSafe.map((config) => (
                      <tr key={config.id} className={`hover:bg-gray-50 dark:hover:bg-slate-800 ${config.status === 'expired' ? 'bg-gray-50 dark:bg-slate-800/50 text-gray-500' : ''}`}>
                        <td className="border p-2">#{config.id}</td>
                        <td className="border p-2">
                          <div className="font-medium">{config.classification}</div>
                          {config.description && <div className="text-xs text-gray-600 dark:text-gray-400 mt-1">{config.description}</div>}
                        </td>
                        <td className="border p-2">₱{parseFloat(config.market_value || 0).toLocaleString()}</td>
                        <td className="border p-2">{config.assessment_level}%</td>
                        <td className="border p-2">
                          ₱{
                            (parseFloat(config.market_value || 0) * 
                             (parseFloat(config.assessment_level || 0) / 100)).toFixed(2)
                          }
                        </td>
                        <td className="border p-2">{config.effective_date}</td>
                        <td className="border p-2">
                          <span className={`px-2 py-1 rounded-full text-xs ${config.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'}`}>
                            {config.status}
                          </span>
                        </td>
                        <td className="border p-2">
                          <div className="flex gap-2">
                            <button onClick={() => handleLandEdit(config)} className="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600 transition-colors" disabled={config.status === 'expired'}>
                              Edit
                            </button>
                            {config.status === 'active' && (
                              <button onClick={() => handleExpire(config.id, 'land-configurations')} className="bg-orange-500 text-white px-3 py-1 rounded text-sm hover:bg-orange-600 transition-colors">
                                Expire
                              </button>
                            )}
                            <button onClick={() => handleDelete(config.id, 'land-configurations')} className="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition-colors">
                              Delete
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      )}

      {/* Property Configuration Tab */}
      {activeTab === 'property' && !loading && (
        <>
          <div className="mb-8">
            <h2 className="text-xl font-semibold mb-4">
              {editingType === 'property' ? 'Edit Property Configuration' : 'Add New Property Configuration'}
            </h2>
            <form onSubmit={handlePropertySubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-2">Classification *</label>
                <input
                  type="text"
                  value={propertyFormData.classification}
                  onChange={(e) => setPropertyFormData({...propertyFormData, classification: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="e.g., Residential, Commercial"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Material Type *</label>
                <input
                  type="text"
                  value={propertyFormData.material_type}
                  onChange={(e) => setPropertyFormData({...propertyFormData, material_type: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="e.g., Concrete, Wooden"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Unit Cost (per sqm) *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={propertyFormData.unit_cost}
                  onChange={(e) => setPropertyFormData({...propertyFormData, unit_cost: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.00"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Depreciation Rate (%) *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  value={propertyFormData.depreciation_rate}
                  onChange={(e) => setPropertyFormData({...propertyFormData, depreciation_rate: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.00"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Min Value *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={propertyFormData.min_value}
                  onChange={(e) => setPropertyFormData({...propertyFormData, min_value: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.00"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Max Value *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={propertyFormData.max_value}
                  onChange={(e) => setPropertyFormData({...propertyFormData, max_value: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.00"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Status</label>
                <select
                  value={propertyFormData.status}
                  onChange={(e) => setPropertyFormData({...propertyFormData, status: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                >
                  <option value="active">Active</option>
                  <option value="expired">Expired</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Effective Date *</label>
                <input
                  type="date"
                  value={propertyFormData.effective_date}
                  onChange={(e) => setPropertyFormData({...propertyFormData, effective_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Expiration Date</label>
                <input
                  type="date"
                  value={propertyFormData.expiration_date}
                  onChange={(e) => setPropertyFormData({...propertyFormData, expiration_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                />
              </div>
              <div className="md:col-span-2 flex gap-4 mt-4">
                <button type="submit" className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition-colors">
                  {editingType === 'property' ? 'Update Property Configuration' : 'Create Property Configuration'}
                </button>
                <button type="button" onClick={resetPropertyForm} className="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition-colors">
                  Cancel
                </button>
                <button 
                  type="button" 
                  onClick={fetchPropertyConfigurations}
                  className="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600 transition-colors"
                >
                  Refresh List
                </button>
              </div>
            </form>
          </div>

          <div>
            <h2 className="text-xl font-semibold mb-4">Property Configurations ({propertyConfigurationsSafe.length})</h2>
            {propertyConfigurationsSafe.length === 0 ? (
              <div className="text-center py-8 text-gray-500">No property configurations found.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full border-collapse border border-gray-300 dark:border-slate-700">
                  <thead>
                    <tr className="bg-gray-100 dark:bg-slate-800">
                      <th className="border p-2 text-left">ID</th>
                      <th className="border p-2 text-left">Classification</th>
                      <th className="border p-2 text-left">Material Type</th>
                      <th className="border p-2 text-left">Unit Cost</th>
                      <th className="border p-2 text-left">Depreciation</th>
                      <th className="border p-2 text-left">Value Range</th>
                      <th className="border p-2 text-left">Effective Date</th>
                      <th className="border p-2 text-left">Status</th>
                      <th className="border p-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {propertyConfigurationsSafe.map((config) => (
                      <tr key={config.id} className={`hover:bg-gray-50 dark:hover:bg-slate-800 ${config.status === 'expired' ? 'bg-gray-50 dark:bg-slate-800/50 text-gray-500' : ''}`}>
                        <td className="border p-2">#{config.id}</td>
                        <td className="border p-2">{config.classification}</td>
                        <td className="border p-2">{config.material_type}</td>
                        <td className="border p-2">₱{parseFloat(config.unit_cost || 0).toLocaleString()}</td>
                        <td className="border p-2">{config.depreciation_rate}%</td>
                        <td className="border p-2">₱{parseFloat(config.min_value || 0).toLocaleString()} - ₱{parseFloat(config.max_value || 0).toLocaleString()}</td>
                        <td className="border p-2">{config.effective_date}</td>
                        <td className="border p-2">
                          <span className={`px-2 py-1 rounded-full text-xs ${config.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'}`}>
                            {config.status}
                          </span>
                        </td>
                        <td className="border p-2">
                          <div className="flex gap-2">
                            <button onClick={() => handlePropertyEdit(config)} className="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600 transition-colors" disabled={config.status === 'expired'}>
                              Edit
                            </button>
                            {config.status === 'active' && (
                              <button onClick={() => handleExpire(config.id, 'property-configurations')} className="bg-orange-500 text-white px-3 py-1 rounded text-sm hover:bg-orange-600 transition-colors">
                                Expire
                              </button>
                            )}
                            <button onClick={() => handleDelete(config.id, 'property-configurations')} className="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition-colors">
                              Delete
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      )}

      {/* Building Assessment Level Tab */}
      {activeTab === 'building-assessment' && !loading && (
        <>
          <div className="mb-8">
            <h2 className="text-xl font-semibold mb-4">
              {editingType === 'building-assessment' ? 'Edit Building Assessment Level' : 'Add New Building Assessment Level'}
            </h2>
            <form onSubmit={handleBuildingAssessmentSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-2">Classification *</label>
                <select
                  value={buildingAssessmentFormData.classification}
                  onChange={(e) => setBuildingAssessmentFormData({...buildingAssessmentFormData, classification: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  required
                >
                  <option value="">Select Classification</option>
                  <option value="Commercial">Commercial</option>
                  <option value="Residential">Residential</option>
                  <option value="Industrial">Industrial</option>
                  <option value="Agricultural">Agricultural</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Minimum Assessed Value *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={buildingAssessmentFormData.min_assessed_value}
                  onChange={(e) => setBuildingAssessmentFormData({...buildingAssessmentFormData, min_assessed_value: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.00"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Maximum Assessed Value *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={buildingAssessmentFormData.max_assessed_value}
                  onChange={(e) => setBuildingAssessmentFormData({...buildingAssessmentFormData, max_assessed_value: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.00"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Assessment Level (%) *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  value={buildingAssessmentFormData.level_percent}
                  onChange={(e) => setBuildingAssessmentFormData({...buildingAssessmentFormData, level_percent: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.00"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Status</label>
                <select
                  value={buildingAssessmentFormData.status}
                  onChange={(e) => setBuildingAssessmentFormData({...buildingAssessmentFormData, status: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                >
                  <option value="active">Active</option>
                  <option value="expired">Expired</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Effective Date *</label>
                <input
                  type="date"
                  value={buildingAssessmentFormData.effective_date}
                  onChange={(e) => setBuildingAssessmentFormData({...buildingAssessmentFormData, effective_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Expiration Date</label>
                <input
                  type="date"
                  value={buildingAssessmentFormData.expiration_date}
                  onChange={(e) => setBuildingAssessmentFormData({...buildingAssessmentFormData, expiration_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                />
              </div>
              <div className="md:col-span-2 flex gap-4 mt-4">
                <button type="submit" className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition-colors">
                  {editingType === 'building-assessment' ? 'Update Building Assessment Level' : 'Create Building Assessment Level'}
                </button>
                <button type="button" onClick={resetBuildingAssessmentForm} className="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition-colors">
                  Cancel
                </button>
                <button 
                  type="button" 
                  onClick={fetchBuildingAssessmentLevels}
                  className="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600 transition-colors"
                >
                  Refresh List
                </button>
              </div>
            </form>
          </div>

          <div>
            <h2 className="text-xl font-semibold mb-4">Building Assessment Levels ({buildingAssessmentLevelsSafe.length})</h2>
            {buildingAssessmentLevelsSafe.length === 0 ? (
              <div className="text-center py-8 text-gray-500">No building assessment levels found.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full border-collapse border border-gray-300 dark:border-slate-700">
                  <thead>
                    <tr className="bg-gray-100 dark:bg-slate-800">
                      <th className="border p-2 text-left">ID</th>
                      <th className="border p-2 text-left">Classification</th>
                      <th className="border p-2 text-left">Value Range</th>
                      <th className="border p-2 text-left">Assessment Level</th>
                      <th className="border p-2 text-left">Effective Date</th>
                      <th className="border p-2 text-left">Status</th>
                      <th className="border p-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {buildingAssessmentLevelsSafe.map((config) => (
                      <tr key={config.id} className={`hover:bg-gray-50 dark:hover:bg-slate-800 ${config.status === 'expired' ? 'bg-gray-50 dark:bg-slate-800/50 text-gray-500' : ''}`}>
                        <td className="border p-2">#{config.id}</td>
                        <td className="border p-2">
                          <span className={`font-medium ${
                            config.classification === 'Commercial' ? 'text-blue-600' :
                            config.classification === 'Residential' ? 'text-green-600' :
                            config.classification === 'Industrial' ? 'text-orange-600' : 'text-purple-600'
                          }`}>
                            {config.classification}
                          </span>
                        </td>
                        <td className="border p-2">
                          ₱{parseFloat(config.min_assessed_value || 0).toLocaleString()} - ₱{parseFloat(config.max_assessed_value || 0).toLocaleString()}
                        </td>
                        <td className="border p-2">{config.level_percent}%</td>
                        <td className="border p-2">{config.effective_date}</td>
                        <td className="border p-2">
                          <span className={`px-2 py-1 rounded-full text-xs ${config.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'}`}>
                            {config.status}
                          </span>
                        </td>
                        <td className="border p-2">
                          <div className="flex gap-2">
                            <button onClick={() => handleBuildingAssessmentEdit(config)} className="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600 transition-colors" disabled={config.status === 'expired'}>
                              Edit
                            </button>
                            {config.status === 'active' && (
                              <button onClick={() => handleExpire(config.id, 'building-assessment-levels')} className="bg-orange-500 text-white px-3 py-1 rounded text-sm hover:bg-orange-600 transition-colors">
                                Expire
                              </button>
                            )}
                            <button onClick={() => handleDelete(config.id, 'building-assessment-levels')} className="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition-colors">
                              Delete
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      )}

      {/* Tax Configuration Tab */}
      {activeTab === 'tax' && !loading && (
        <>
          <div className="mb-8">
            <h2 className="text-xl font-semibold mb-4">
              {editingType === 'tax' ? 'Edit Tax Configuration' : 'Add New Tax Configuration'}
            </h2>
            
            {/* Tax Status Info */}
            <div className="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className={`p-4 rounded-lg border ${basicTaxExists ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200'}`}>
                <h3 className="font-semibold mb-2">Basic Tax Status</h3>
                <p className={basicTaxExists ? 'text-green-700' : 'text-yellow-700'}>
                  {basicTaxExists ? '✅ Active configuration exists' : '⚠️ No active configuration'}
                </p>
              </div>
              <div className={`p-4 rounded-lg border ${sefTaxExists ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200'}`}>
                <h3 className="font-semibold mb-2">SEF Tax Status</h3>
                <p className={sefTaxExists ? 'text-green-700' : 'text-yellow-700'}>
                  {sefTaxExists ? '✅ Active configuration exists' : '⚠️ No active configuration'}
                </p>
              </div>
            </div>

            <form onSubmit={handleTaxSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-2">Tax Name *</label>
                <select
                  value={taxFormData.tax_name}
                  onChange={(e) => setTaxFormData({...taxFormData, tax_name: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  required
                >
                  <option value="">Select Tax Type</option>
                  <option value="Basic Tax">Basic Tax</option>
                  <option value="SEF Tax">SEF Tax</option>
                </select>
                <p className="text-xs text-gray-500 mt-1">Only Basic Tax and SEF Tax are allowed</p>
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Tax Percentage (%) *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  value={taxFormData.tax_percent}
                  onChange={(e) => setTaxFormData({...taxFormData, tax_percent: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.00"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Status</label>
                <select
                  value={taxFormData.status}
                  onChange={(e) => setTaxFormData({...taxFormData, status: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                >
                  <option value="active">Active</option>
                  <option value="expired">Expired</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Effective Date *</label>
                <input
                  type="date"
                  value={taxFormData.effective_date}
                  onChange={(e) => setTaxFormData({...taxFormData, effective_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Expiration Date</label>
                <input
                  type="date"
                  value={taxFormData.expiration_date}
                  onChange={(e) => setTaxFormData({...taxFormData, expiration_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                />
              </div>
              <div className="md:col-span-2 flex gap-4 mt-4">
                <button type="submit" className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition-colors">
                  {editingType === 'tax' ? 'Update Tax Configuration' : 'Create Tax Configuration'}
                </button>
                <button type="button" onClick={resetTaxForm} className="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition-colors">
                  Cancel
                </button>
                <button 
                  type="button" 
                  onClick={fetchTaxConfigurations}
                  className="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600 transition-colors"
                >
                  Refresh List
                </button>
              </div>
            </form>
          </div>

          <div>
            <h2 className="text-xl font-semibold mb-4">Tax Configurations ({taxConfigurationsSafe.length})</h2>
            {taxConfigurationsSafe.length === 0 ? (
              <div className="text-center py-8 text-gray-500">No tax configurations found.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full border-collapse border border-gray-300 dark:border-slate-700">
                  <thead>
                    <tr className="bg-gray-100 dark:bg-slate-800">
                      <th className="border p-2 text-left">ID</th>
                      <th className="border p-2 text-left">Tax Name</th>
                      <th className="border p-2 text-left">Tax Percentage</th>
                      <th className="border p-2 text-left">Effective Date</th>
                      <th className="border p-2 text-left">Expiration Date</th>
                      <th className="border p-2 text-left">Status</th>
                      <th className="border p-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {taxConfigurationsSafe.map((config) => (
                      <tr key={config.id} className={`hover:bg-gray-50 dark:hover:bg-slate-800 ${config.status === 'expired' ? 'bg-gray-50 dark:bg-slate-800/50 text-gray-500' : ''}`}>
                        <td className="border p-2">#{config.id}</td>
                        <td className="border p-2">
                          <span className={`font-medium ${config.tax_name === 'Basic Tax' ? 'text-blue-600' : 'text-green-600'}`}>
                            {config.tax_name}
                          </span>
                        </td>
                        <td className="border p-2">{config.tax_percent}%</td>
                        <td className="border p-2">{config.effective_date}</td>
                        <td className="border p-2">{config.expiration_date || '-'}</td>
                        <td className="border p-2">
                          <span className={`px-2 py-1 rounded-full text-xs ${config.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'}`}>
                            {config.status}
                          </span>
                        </td>
                        <td className="border p-2">
                          <div className="flex gap-2">
                            <button onClick={() => handleTaxEdit(config)} className="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600 transition-colors" disabled={config.status === 'expired'}>
                              Edit
                            </button>
                            {config.status === 'active' && (
                              <button onClick={() => handleExpire(config.id, 'tax-configurations')} className="bg-orange-500 text-white px-3 py-1 rounded text-sm hover:bg-orange-600 transition-colors">
                                Expire
                              </button>
                            )}
                            <button onClick={() => handleDelete(config.id, 'tax-configurations')} className="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition-colors">
                              Delete
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      )}

      {/* Discount & Penalty Configuration Tab */}
      {activeTab === 'discount-penalty' && !loading && (
        <>
          {/* Discount Section */}
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
                  value={discountFormData.discount_percent}
                  onChange={(e) => setDiscountFormData({...discountFormData, discount_percent: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.00"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Status</label>
                <select
                  value={discountFormData.status}
                  onChange={(e) => setDiscountFormData({...discountFormData, status: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                >
                  <option value="active">Active</option>
                  <option value="expired">Expired</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Effective Date *</label>
                <input
                  type="date"
                  value={discountFormData.effective_date}
                  onChange={(e) => setDiscountFormData({...discountFormData, effective_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Expiration Date</label>
                <input
                  type="date"
                  value={discountFormData.expiration_date}
                  onChange={(e) => setDiscountFormData({...discountFormData, expiration_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                />
              </div>
              <div className="md:col-span-2 flex gap-4 mt-4">
                <button type="submit" className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition-colors">
                  {editingType === 'discount' ? 'Update Discount Configuration' : 'Create Discount Configuration'}
                </button>
                <button type="button" onClick={resetDiscountForm} className="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition-colors">
                  Cancel
                </button>
                <button 
                  type="button" 
                  onClick={fetchDiscountConfigurations}
                  className="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600 transition-colors"
                >
                  Refresh List
                </button>
              </div>
            </form>
          </div>

          {/* Discount Configurations List */}
          <div className="mb-8">
            <h2 className="text-xl font-semibold mb-4">Discount Configurations ({discountConfigurationsSafe.length})</h2>
            {discountConfigurationsSafe.length === 0 ? (
              <div className="text-center py-8 text-gray-500">No discount configurations found.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full border-collapse border border-gray-300 dark:border-slate-700">
                  <thead>
                    <tr className="bg-gray-100 dark:bg-slate-800">
                      <th className="border p-2 text-left">ID</th>
                      <th className="border p-2 text-left">Discount Percentage</th>
                      <th className="border p-2 text-left">Effective Date</th>
                      <th className="border p-2 text-left">Expiration Date</th>
                      <th className="border p-2 text-left">Status</th>
                      <th className="border p-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {discountConfigurationsSafe.map((config) => (
                      <tr key={config.id} className={`hover:bg-gray-50 dark:hover:bg-slate-800 ${config.status === 'expired' ? 'bg-gray-50 dark:bg-slate-800/50 text-gray-500' : ''}`}>
                        <td className="border p-2">#{config.id}</td>
                        <td className="border p-2">{config.discount_percent}%</td>
                        <td className="border p-2">{config.effective_date}</td>
                        <td className="border p-2">{config.expiration_date || '-'}</td>
                        <td className="border p-2">
                          <span className={`px-2 py-1 rounded-full text-xs ${config.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'}`}>
                            {config.status}
                          </span>
                        </td>
                        <td className="border p-2">
                          <div className="flex gap-2">
                            <button onClick={() => handleDiscountEdit(config)} className="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600 transition-colors" disabled={config.status === 'expired'}>
                              Edit
                            </button>
                            {config.status === 'active' && (
                              <button onClick={() => handleExpire(config.id, 'discount-configurations')} className="bg-orange-500 text-white px-3 py-1 rounded text-sm hover:bg-orange-600 transition-colors">
                                Expire
                              </button>
                            )}
                            <button onClick={() => handleDelete(config.id, 'discount-configurations')} className="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition-colors">
                              Delete
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          {/* Penalty Section */}
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
                  value={penaltyFormData.penalty_percent}
                  onChange={(e) => setPenaltyFormData({...penaltyFormData, penalty_percent: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  placeholder="0.00"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Status</label>
                <select
                  value={penaltyFormData.status}
                  onChange={(e) => setPenaltyFormData({...penaltyFormData, status: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                >
                  <option value="active">Active</option>
                  <option value="expired">Expired</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Effective Date *</label>
                <input
                  type="date"
                  value={penaltyFormData.effective_date}
                  onChange={(e) => setPenaltyFormData({...penaltyFormData, effective_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Expiration Date</label>
                <input
                  type="date"
                  value={penaltyFormData.expiration_date}
                  onChange={(e) => setPenaltyFormData({...penaltyFormData, expiration_date: e.target.value})}
                  className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                />
              </div>
              <div className="md:col-span-2 flex gap-4 mt-4">
                <button type="submit" className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition-colors">
                  {editingType === 'penalty' ? 'Update Penalty Configuration' : 'Create Penalty Configuration'}
                </button>
                <button type="button" onClick={resetPenaltyForm} className="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition-colors">
                  Cancel
                </button>
                <button 
                  type="button" 
                  onClick={fetchPenaltyConfigurations}
                  className="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600 transition-colors"
                >
                  Refresh List
                </button>
              </div>
            </form>
          </div>

          {/* Penalty Configurations List */}
          <div>
            <h2 className="text-xl font-semibold mb-4">Penalty Configurations ({penaltyConfigurationsSafe.length})</h2>
            {penaltyConfigurationsSafe.length === 0 ? (
              <div className="text-center py-8 text-gray-500">No penalty configurations found.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full border-collapse border border-gray-300 dark:border-slate-700">
                  <thead>
                    <tr className="bg-gray-100 dark:bg-slate-800">
                      <th className="border p-2 text-left">ID</th>
                      <th className="border p-2 text-left">Penalty Percentage</th>
                      <th className="border p-2 text-left">Effective Date</th>
                      <th className="border p-2 text-left">Expiration Date</th>
                      <th className="border p-2 text-left">Status</th>
                      <th className="border p-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {penaltyConfigurationsSafe.map((config) => (
                      <tr key={config.id} className={`hover:bg-gray-50 dark:hover:bg-slate-800 ${config.status === 'expired' ? 'bg-gray-50 dark:bg-slate-800/50 text-gray-500' : ''}`}>
                        <td className="border p-2">#{config.id}</td>
                        <td className="border p-2">{config.penalty_percent}%</td>
                        <td className="border p-2">{config.effective_date}</td>
                        <td className="border p-2">{config.expiration_date || '-'}</td>
                        <td className="border p-2">
                          <span className={`px-2 py-1 rounded-full text-xs ${config.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'}`}>
                            {config.status}
                          </span>
                        </td>
                        <td className="border p-2">
                          <div className="flex gap-2">
                            <button onClick={() => handlePenaltyEdit(config)} className="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600 transition-colors" disabled={config.status === 'expired'}>
                              Edit
                            </button>
                            {config.status === 'active' && (
                              <button onClick={() => handleExpire(config.id, 'penalty-configurations')} className="bg-orange-500 text-white px-3 py-1 rounded text-sm hover:bg-orange-600 transition-colors">
                                Expire
                              </button>
                            )}
                            <button onClick={() => handleDelete(config.id, 'penalty-configurations')} className="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition-colors">
                              Delete
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
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