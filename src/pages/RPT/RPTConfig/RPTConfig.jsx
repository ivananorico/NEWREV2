import { useState, useEffect } from 'react';
import {
  Landmark, Settings, DollarSign, Percent, AlertTriangle,
  Home, Building2, Calculator, Clock, CheckCircle,
  Edit2, Trash2, RefreshCw, Plus, Search, Filter,
  Calendar, TrendingUp, Shield, Zap, Layers,
  ChevronRight, Download, FileText, Info
} from 'lucide-react';

// Custom colors from dashboard
const COLORS = {
  primary: '#4a90e2',
  secondary: '#9aa5b1',
  success: '#4caf50',
  background: '#fbfbfb',
  warning: '#ff9800',
  danger: '#f44336',
  info: '#2196f3',
  dark: '#374151'
};

export default function RPTConfig() {
  const [activeTab, setActiveTab] = useState('land');
  const [landConfigurations, setLandConfigurations] = useState([]);
  const [propertyConfigurations, setPropertyConfigurations] = useState([]);
  const [buildingAssessmentLevels, setBuildingAssessmentLevels] = useState([]);
  const [taxConfigurations, setTaxConfigurations] = useState([]);
  const [discountConfigurations, setDiscountConfigurations] = useState([]);
  const [penaltyConfigurations, setPenaltyConfigurations] = useState([]);
  const [currentDate] = useState(new Date().toISOString().split('T')[0]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [showForm, setShowForm] = useState(false);

  // Form states (keep the same as before)
  const [landFormData, setLandFormData] = useState({
    classification: '',
    market_value: '',
    assessment_level: '',
    description: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    status: 'active'
  });

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

  const [buildingAssessmentFormData, setBuildingAssessmentFormData] = useState({
    classification: '',
    min_assessed_value: '',
    max_assessed_value: '',
    level_percent: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    status: 'active'
  });

  const [taxFormData, setTaxFormData] = useState({
    tax_name: '',
    tax_percent: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    status: 'active'
  });

  const [discountFormData, setDiscountFormData] = useState({
    discount_percent: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    status: 'active'
  });

  const [penaltyFormData, setPenaltyFormData] = useState({
    penalty_percent: '',
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: '',
    status: 'active'
  });

  const [editingId, setEditingId] = useState(null);
  const [editingType, setEditingType] = useState(null);

  // Safe array variables
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
    : "http://localhost/revenue2/backend/RPT/RPTConfig";

  // Fetch all data functions (keep the same as before)
  const fetchLandConfigurations = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${API_BASE}/land-configurations.php`);
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();
      setLandConfigurations(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching land configurations:', error);
      setError('Failed to load land configurations');
    } finally {
      setLoading(false);
    }
  };

  const fetchPropertyConfigurations = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${API_BASE}/property-configurations.php`);
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();
      setPropertyConfigurations(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching property configurations:', error);
      setError('Failed to load property configurations');
    } finally {
      setLoading(false);
    }
  };

  const fetchBuildingAssessmentLevels = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${API_BASE}/building-assessment-levels.php`);
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();
      setBuildingAssessmentLevels(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching building assessment levels:', error);
      setError('Failed to load building assessment levels');
    } finally {
      setLoading(false);
    }
  };

  const fetchTaxConfigurations = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${API_BASE}/tax-configurations.php`);
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();
      setTaxConfigurations(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching tax configurations:', error);
      setError('Failed to load tax configurations');
    } finally {
      setLoading(false);
    }
  };

  const fetchDiscountConfigurations = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${API_BASE}/discount-configurations.php`);
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();
      setDiscountConfigurations(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching discount configurations:', error);
      setError('Failed to load discount configurations');
    } finally {
      setLoading(false);
    }
  };

  const fetchPenaltyConfigurations = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${API_BASE}/penalty-configurations.php`);
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();
      setPenaltyConfigurations(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching penalty configurations:', error);
      setError('Failed to load penalty configurations');
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
    setShowForm(false);
    setEditingId(null);
    setEditingType(null);
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

  // Form Handlers (keep the same as before, but here are shortened versions)
  const handleLandSubmit = async (e) => {
    e.preventDefault();
    const url = editingId ? `${API_BASE}/land-configurations.php?id=${editingId}` : `${API_BASE}/land-configurations.php`;
    const method = editingId ? 'PUT' : 'POST';
    try {
      const response = await fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
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

  // Other form handlers would be similar...
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

  // Edit Handlers (keep the same as before)
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
    setShowForm(true);
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
    setShowForm(true);
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
    setShowForm(true);
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
    setShowForm(true);
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
    setShowForm(true);
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
    setShowForm(true);
  };

  // Delete Handler
  const handleDelete = async (id, type) => {
    const typeName = type.replace('-configurations', '').replace('-', ' ').replace('-levels', ' levels');
    if (window.confirm(`Are you sure you want to delete this ${typeName} configuration?`)) {
      try {
        const response = await fetch(`${API_BASE}/${type}.php?id=${id}`, { method: 'DELETE' });
        const result = await response.json();
        if (response.ok || result.success) {
          switch(type) {
            case 'land-configurations': fetchLandConfigurations(); break;
            case 'property-configurations': fetchPropertyConfigurations(); break;
            case 'building-assessment-levels': fetchBuildingAssessmentLevels(); break;
            case 'tax-configurations': fetchTaxConfigurations(); break;
            case 'discount-configurations': fetchDiscountConfigurations(); break;
            case 'penalty-configurations': fetchPenaltyConfigurations(); break;
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
    setShowForm(false);
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
    setShowForm(false);
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
    setShowForm(false);
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
    setShowForm(false);
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
    setShowForm(false);
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
    setShowForm(false);
  };

  // Statistics
  const activeLandConfigs = landConfigurationsSafe.filter(config => config.status === 'active').length;
  const activePropertyConfigs = propertyConfigurationsSafe.filter(config => config.status === 'active').length;
  const activeBuildingAssessmentConfigs = buildingAssessmentLevelsSafe.filter(config => config.status === 'active').length;
  const activeTaxConfigs = taxConfigurationsSafe.filter(config => config.status === 'active').length;
  const activeDiscountConfigs = discountConfigurationsSafe.filter(config => config.status === 'active').length;
  const activePenaltyConfigs = penaltyConfigurationsSafe.filter(config => config.status === 'active').length;

  // Filter configurations based on search
  const filteredLandConfigs = landConfigurationsSafe.filter(config => 
    config.classification?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    config.description?.toLowerCase().includes(searchTerm.toLowerCase())
  );

  // Get current form data
  const getCurrentFormData = () => {
    switch(activeTab) {
      case 'land': return landFormData;
      case 'property': return propertyFormData;
      case 'building-assessment': return buildingAssessmentFormData;
      case 'tax': return taxFormData;
      case 'discount-penalty': 
        return editingType === 'discount' ? discountFormData : penaltyFormData;
      default: return {};
    }
  };

  const currentFormData = getCurrentFormData();

  // Get tab icon
  const getTabIcon = (tab) => {
    switch(tab) {
      case 'land': return <Home className="w-5 h-5" />;
      case 'property': return <Building2 className="w-5 h-5" />;
      case 'building-assessment': return <Layers className="w-5 h-5" />;
      case 'tax': return <Calculator className="w-5 h-5" />;
      case 'discount-penalty': return <Percent className="w-5 h-5" />;
      default: return <Settings className="w-5 h-5" />;
    }
  };

  // Get tab title
  const getTabTitle = (tab) => {
    return tab === 'discount-penalty' ? 'Discount & Penalty' : 
           tab === 'building-assessment' ? 'Building Assessment' :
           tab.charAt(0).toUpperCase() + tab.slice(1);
  };

  return (
    <div className="min-h-screen" style={{ backgroundColor: COLORS.background }}>
      {/* Header */}
      <div className="border-b" style={{ backgroundColor: 'white', borderColor: '#e5e7eb' }}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
              <h1 className="text-2xl font-bold mb-1" style={{ color: COLORS.dark }}>
                Real Property Tax Configuration
              </h1>
              <div className="flex items-center gap-3 text-sm" style={{ color: COLORS.secondary }}>
                <div className="flex items-center gap-1">
                  <Calendar className="w-4 h-4" />
                  <span>Last Updated: {new Date().toLocaleDateString('en-PH')}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* Error Display */}
        {error && (
          <div className="bg-red-50 border border-red-200 rounded-xl p-4">
            <div className="flex items-center gap-3">
              <AlertTriangle className="w-5 h-5 text-red-600" />
              <div className="flex-1">
                <p className="font-medium text-red-600">Error</p>
                <p className="text-sm text-red-700">{error}</p>
              </div>
              <button onClick={() => setError(null)} className="text-red-600 hover:text-red-800">
                <span className="sr-only">Dismiss</span>
                ×
              </button>
            </div>
          </div>
        )}

        {/* Statistics Cards - Horizontal Layout */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
          {/* Land Config Card */}
          <div className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                <Home className="w-5 h-5" style={{ color: COLORS.primary }} />
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-xs font-semibold uppercase tracking-wider truncate" style={{ color: COLORS.secondary }}>
                  Land
                </h3>
                <p className="text-xl font-bold truncate" style={{ color: COLORS.dark }}>{landConfigurationsSafe.length}</p>
                <div className="flex items-center gap-2 mt-1">
                  <div className="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full rounded-full" 
                      style={{ 
                        width: `${landConfigurationsSafe.length > 0 ? (activeLandConfigs / landConfigurationsSafe.length) * 100 : 0}%`,
                        backgroundColor: COLORS.primary 
                      }}
                    />
                  </div>
                  <span className="text-xs font-medium whitespace-nowrap" style={{ color: COLORS.primary }}>
                    {activeLandConfigs} active
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Property Config Card */}
          <div className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.success}15` }}>
                <Building2 className="w-5 h-5" style={{ color: COLORS.success }} />
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-xs font-semibold uppercase tracking-wider truncate" style={{ color: COLORS.secondary }}>
                  Property
                </h3>
                <p className="text-xl font-bold truncate" style={{ color: COLORS.dark }}>{propertyConfigurationsSafe.length}</p>
                <div className="flex items-center gap-2 mt-1">
                  <div className="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full rounded-full" 
                      style={{ 
                        width: `${propertyConfigurationsSafe.length > 0 ? (activePropertyConfigs / propertyConfigurationsSafe.length) * 100 : 0}%`,
                        backgroundColor: COLORS.success 
                      }}
                    />
                  </div>
                  <span className="text-xs font-medium whitespace-nowrap" style={{ color: COLORS.success }}>
                    {activePropertyConfigs} active
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Building Assessment Card */}
          <div className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.info}15` }}>
                <Layers className="w-5 h-5" style={{ color: COLORS.info }} />
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-xs font-semibold uppercase tracking-wider truncate" style={{ color: COLORS.secondary }}>
                  Building
                </h3>
                <p className="text-xl font-bold truncate" style={{ color: COLORS.dark }}>{buildingAssessmentLevelsSafe.length}</p>
                <div className="flex items-center gap-2 mt-1">
                  <div className="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full rounded-full" 
                      style={{ 
                        width: `${buildingAssessmentLevelsSafe.length > 0 ? (activeBuildingAssessmentConfigs / buildingAssessmentLevelsSafe.length) * 100 : 0}%`,
                        backgroundColor: COLORS.info 
                      }}
                    />
                  </div>
                  <span className="text-xs font-medium whitespace-nowrap" style={{ color: COLORS.info }}>
                    {activeBuildingAssessmentConfigs} active
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Tax Config Card */}
          <div className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.warning}15` }}>
                <Calculator className="w-5 h-5" style={{ color: COLORS.warning }} />
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-xs font-semibold uppercase tracking-wider truncate" style={{ color: COLORS.secondary }}>
                  Tax Rates
                </h3>
                <p className="text-xl font-bold truncate" style={{ color: COLORS.dark }}>{taxConfigurationsSafe.length}</p>
                <div className="flex items-center gap-2 mt-1">
                  <div className="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full rounded-full" 
                      style={{ 
                        width: `${taxConfigurationsSafe.length > 0 ? (activeTaxConfigs / taxConfigurationsSafe.length) * 100 : 0}%`,
                        backgroundColor: COLORS.warning 
                      }}
                    />
                  </div>
                  <span className="text-xs font-medium whitespace-nowrap" style={{ color: COLORS.warning }}>
                    {activeTaxConfigs} active
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Discount Card */}
          <div className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.primary}15` }}>
                <Percent className="w-5 h-5" style={{ color: COLORS.primary }} />
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-xs font-semibold uppercase tracking-wider truncate" style={{ color: COLORS.secondary }}>
                  Discounts
                </h3>
                <p className="text-xl font-bold truncate" style={{ color: COLORS.dark }}>{discountConfigurationsSafe.length}</p>
                <div className="flex items-center gap-2 mt-1">
                  <div className="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full rounded-full" 
                      style={{ 
                        width: `${discountConfigurationsSafe.length > 0 ? (activeDiscountConfigs / discountConfigurationsSafe.length) * 100 : 0}%`,
                        backgroundColor: COLORS.primary 
                      }}
                    />
                  </div>
                  <span className="text-xs font-medium whitespace-nowrap" style={{ color: COLORS.primary }}>
                    {activeDiscountConfigs} active
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Penalty Card */}
          <div className="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" 
               style={{ borderColor: COLORS.secondary }}>
            <div className="flex items-start gap-3">
              <div className="p-2 rounded-lg" style={{ backgroundColor: `${COLORS.danger}15` }}>
                <Shield className="w-5 h-5" style={{ color: COLORS.danger }} />
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="text-xs font-semibold uppercase tracking-wider truncate" style={{ color: COLORS.secondary }}>
                  Penalties
                </h3>
                <p className="text-xl font-bold truncate" style={{ color: COLORS.dark }}>{penaltyConfigurationsSafe.length}</p>
                <div className="flex items-center gap-2 mt-1">
                  <div className="flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full rounded-full" 
                      style={{ 
                        width: `${penaltyConfigurationsSafe.length > 0 ? (activePenaltyConfigs / penaltyConfigurationsSafe.length) * 100 : 0}%`,
                        backgroundColor: COLORS.danger 
                      }}
                    />
                  </div>
                  <span className="text-xs font-medium whitespace-nowrap" style={{ color: COLORS.danger }}>
                    {activePenaltyConfigs} active
                  </span>
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
                {['land', 'property', 'building-assessment', 'tax', 'discount-penalty'].map(tab => (
                  <button
                    key={tab}
                    onClick={() => setActiveTab(tab)}
                    className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-all whitespace-nowrap ${
                      activeTab === tab 
                        ? 'text-white' 
                        : 'hover:bg-gray-50'
                    }`}
                    style={{
                      backgroundColor: activeTab === tab ? COLORS.primary : 'transparent',
                      color: activeTab === tab ? 'white' : COLORS.dark
                    }}
                  >
                    {getTabIcon(tab)}
                    {getTabTitle(tab)}
                  </button>
                ))}
              </div>
              
              <div className="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                <div className="relative flex-1 sm:flex-none">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" style={{ color: COLORS.secondary }} />
                  <input
                    type="text"
                    placeholder="Search configurations..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="pl-10 pr-4 py-2 border rounded-lg w-full sm:w-64"
                    style={{ borderColor: COLORS.secondary }}
                  />
                </div>
                
                <div className="flex gap-2">
                  <button
                    onClick={() => {
                      setShowForm(!showForm);
                      if (editingId) {
                        switch(activeTab) {
                          case 'land': resetLandForm(); break;
                          case 'property': resetPropertyForm(); break;
                          case 'building-assessment': resetBuildingAssessmentForm(); break;
                          case 'tax': resetTaxForm(); break;
                          case 'discount-penalty': 
                            editingType === 'discount' ? resetDiscountForm() : resetPenaltyForm();
                            break;
                        }
                      }
                    }}
                    className="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 py-2 rounded-lg transition-all"
                    style={{ backgroundColor: COLORS.primary, color: 'white' }}
                  >
                    <Plus className="w-4 h-4" />
                    <span className="truncate">{showForm ? 'Cancel' : 'Add New'}</span>
                  </button>
                  
                  <button
                    onClick={() => {
                      switch(activeTab) {
                        case 'land': fetchLandConfigurations(); break;
                        case 'property': fetchPropertyConfigurations(); break;
                        case 'building-assessment': fetchBuildingAssessmentLevels(); break;
                        case 'tax': fetchTaxConfigurations(); break;
                        case 'discount-penalty': 
                          fetchDiscountConfigurations();
                          fetchPenaltyConfigurations();
                          break;
                      }
                    }}
                    className="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50 transition-all"
                    style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                  >
                    <RefreshCw className="w-4 h-4" />
                    <span className="truncate">Refresh</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Configuration Form */}
          {showForm && (
            <div className="p-6 border-b" style={{ borderColor: COLORS.secondary, backgroundColor: `${COLORS.background}` }}>
              <h3 className="font-semibold mb-4 flex items-center gap-2" style={{ color: COLORS.dark }}>
                <Edit2 className="w-5 h-5" style={{ color: COLORS.primary }} />
                {editingType ? `Edit ${getTabTitle(activeTab)} Configuration` : `Add New ${getTabTitle(activeTab)} Configuration`}
              </h3>
              
              <form onSubmit={(e) => {
                switch(activeTab) {
                  case 'land': handleLandSubmit(e); break;
                  case 'property': handlePropertySubmit(e); break;
                  case 'building-assessment': handleBuildingAssessmentSubmit(e); break;
                  case 'tax': handleTaxSubmit(e); break;
                  case 'discount-penalty': 
                    editingType === 'discount' ? handleDiscountSubmit(e) : handlePenaltySubmit(e);
                    break;
                  default: 
                    if (editingType === 'discount') handleDiscountSubmit(e);
                    else if (editingType === 'penalty') handlePenaltySubmit(e);
                    break;
                }
              }} className="space-y-4">
                {activeTab === 'land' && (
                  <>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                          Classification *
                        </label>
                        <input
                          type="text"
                          value={landFormData.classification}
                          onChange={(e) => setLandFormData({...landFormData, classification: e.target.value})}
                          className="w-full p-2 border rounded-lg"
                          style={{ borderColor: COLORS.secondary }}
                          placeholder="e.g., Residential, Commercial"
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                          Market Value (per sqm) *
                        </label>
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          value={landFormData.market_value}
                          onChange={(e) => setLandFormData({...landFormData, market_value: e.target.value})}
                          className="w-full p-2 border rounded-lg"
                          style={{ borderColor: COLORS.secondary }}
                          placeholder="0.00"
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                          Assessment Level (%) *
                        </label>
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          max="100"
                          value={landFormData.assessment_level}
                          onChange={(e) => setLandFormData({...landFormData, assessment_level: e.target.value})}
                          className="w-full p-2 border rounded-lg"
                          style={{ borderColor: COLORS.secondary }}
                          placeholder="0.00"
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                          Status
                        </label>
                        <select
                          value={landFormData.status}
                          onChange={(e) => setLandFormData({...landFormData, status: e.target.value})}
                          className="w-full p-2 border rounded-lg"
                          style={{ borderColor: COLORS.secondary }}
                        >
                          <option value="active">Active</option>
                          <option value="expired">Expired</option>
                        </select>
                      </div>
                      <div>
                        <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                          Effective Date *
                        </label>
                        <input
                          type="date"
                          value={landFormData.effective_date}
                          onChange={(e) => setLandFormData({...landFormData, effective_date: e.target.value})}
                          className="w-full p-2 border rounded-lg"
                          style={{ borderColor: COLORS.secondary }}
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                          Expiration Date
                        </label>
                        <input
                          type="date"
                          value={landFormData.expiration_date}
                          onChange={(e) => setLandFormData({...landFormData, expiration_date: e.target.value})}
                          className="w-full p-2 border rounded-lg"
                          style={{ borderColor: COLORS.secondary }}
                        />
                      </div>
                    </div>
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Description
                      </label>
                      <textarea
                        value={landFormData.description}
                        onChange={(e) => setLandFormData({...landFormData, description: e.target.value})}
                        rows="2"
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                        placeholder="Additional details..."
                      />
                    </div>
                  </>
                )}

                {activeTab === 'property' && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Classification *
                      </label>
                      <input
                        type="text"
                        value={propertyFormData.classification}
                        onChange={(e) => setPropertyFormData({...propertyFormData, classification: e.target.value})}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                        placeholder="e.g., Residential, Commercial"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Material Type *
                      </label>
                      <input
                        type="text"
                        value={propertyFormData.material_type}
                        onChange={(e) => setPropertyFormData({...propertyFormData, material_type: e.target.value})}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                        placeholder="e.g., Concrete, Wooden"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Unit Cost (per sqm) *
                      </label>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={propertyFormData.unit_cost}
                        onChange={(e) => setPropertyFormData({...propertyFormData, unit_cost: e.target.value})}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                        placeholder="0.00"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Depreciation Rate (%) *
                      </label>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        value={propertyFormData.depreciation_rate}
                        onChange={(e) => setPropertyFormData({...propertyFormData, depreciation_rate: e.target.value})}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                        placeholder="0.00"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Min Value *
                      </label>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={propertyFormData.min_value}
                        onChange={(e) => setPropertyFormData({...propertyFormData, min_value: e.target.value})}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                        placeholder="0.00"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Max Value *
                      </label>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={propertyFormData.max_value}
                        onChange={(e) => setPropertyFormData({...propertyFormData, max_value: e.target.value})}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                        placeholder="0.00"
                        required
                      />
                    </div>
                  </div>
                )}

                {activeTab === 'building-assessment' && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Classification *
                      </label>
                      <select
                        value={buildingAssessmentFormData.classification}
                        onChange={(e) => setBuildingAssessmentFormData({...buildingAssessmentFormData, classification: e.target.value})}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
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
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Minimum Assessed Value *
                      </label>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={buildingAssessmentFormData.min_assessed_value}
                        onChange={(e) => setBuildingAssessmentFormData({...buildingAssessmentFormData, min_assessed_value: e.target.value})}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                        placeholder="0.00"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Maximum Assessed Value *
                      </label>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={buildingAssessmentFormData.max_assessed_value}
                        onChange={(e) => setBuildingAssessmentFormData({...buildingAssessmentFormData, max_assessed_value: e.target.value})}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                        placeholder="0.00"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Assessment Level (%) *
                      </label>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        value={buildingAssessmentFormData.level_percent}
                        onChange={(e) => setBuildingAssessmentFormData({...buildingAssessmentFormData, level_percent: e.target.value})}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                        placeholder="0.00"
                        required
                      />
                    </div>
                  </div>
                )}

                {activeTab === 'tax' && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Tax Name *
                      </label>
                      <select
                        value={taxFormData.tax_name}
                        onChange={(e) => setTaxFormData({...taxFormData, tax_name: e.target.value})}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                        required
                      >
                        <option value="">Select Tax Type</option>
                        <option value="Basic Tax">Basic Tax</option>
                        <option value="SEF Tax">SEF Tax</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Tax Percentage (%) *
                      </label>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        value={taxFormData.tax_percent}
                        onChange={(e) => setTaxFormData({...taxFormData, tax_percent: e.target.value})}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                        placeholder="0.00"
                        required
                      />
                    </div>
                  </div>
                )}

                {(activeTab === 'discount-penalty' && editingType === 'discount') && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Discount Percentage (%) *
                      </label>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        value={discountFormData.discount_percent}
                        onChange={(e) => setDiscountFormData({...discountFormData, discount_percent: e.target.value})}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                        placeholder="0.00"
                        required
                      />
                    </div>
                  </div>
                )}

                {(activeTab === 'discount-penalty' && editingType === 'penalty') && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Penalty Percentage (%) *
                      </label>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        value={penaltyFormData.penalty_percent}
                        onChange={(e) => setPenaltyFormData({...penaltyFormData, penalty_percent: e.target.value})}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                        placeholder="0.00"
                        required
                      />
                    </div>
                  </div>
                )}

                {/* Common fields for all forms */}
                {(activeTab !== 'discount-penalty' || (activeTab === 'discount-penalty' && editingType)) && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Effective Date *
                      </label>
                      <input
                        type="date"
                        value={currentFormData.effective_date}
                        onChange={(e) => {
                          switch(activeTab) {
                            case 'land': setLandFormData({...landFormData, effective_date: e.target.value}); break;
                            case 'property': setPropertyFormData({...propertyFormData, effective_date: e.target.value}); break;
                            case 'building-assessment': setBuildingAssessmentFormData({...buildingAssessmentFormData, effective_date: e.target.value}); break;
                            case 'tax': setTaxFormData({...taxFormData, effective_date: e.target.value}); break;
                            case 'discount-penalty': 
                              if (editingType === 'discount') setDiscountFormData({...discountFormData, effective_date: e.target.value});
                              else setPenaltyFormData({...penaltyFormData, effective_date: e.target.value});
                              break;
                          }
                        }}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Expiration Date
                      </label>
                      <input
                        type="date"
                        value={currentFormData.expiration_date}
                        onChange={(e) => {
                          switch(activeTab) {
                            case 'land': setLandFormData({...landFormData, expiration_date: e.target.value}); break;
                            case 'property': setPropertyFormData({...propertyFormData, expiration_date: e.target.value}); break;
                            case 'building-assessment': setBuildingAssessmentFormData({...buildingAssessmentFormData, expiration_date: e.target.value}); break;
                            case 'tax': setTaxFormData({...taxFormData, expiration_date: e.target.value}); break;
                            case 'discount-penalty': 
                              if (editingType === 'discount') setDiscountFormData({...discountFormData, expiration_date: e.target.value});
                              else setPenaltyFormData({...penaltyFormData, expiration_date: e.target.value});
                              break;
                          }
                        }}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium mb-2" style={{ color: COLORS.dark }}>
                        Status
                      </label>
                      <select
                        value={currentFormData.status}
                        onChange={(e) => {
                          switch(activeTab) {
                            case 'land': setLandFormData({...landFormData, status: e.target.value}); break;
                            case 'property': setPropertyFormData({...propertyFormData, status: e.target.value}); break;
                            case 'building-assessment': setBuildingAssessmentFormData({...buildingAssessmentFormData, status: e.target.value}); break;
                            case 'tax': setTaxFormData({...taxFormData, status: e.target.value}); break;
                            case 'discount-penalty': 
                              if (editingType === 'discount') setDiscountFormData({...discountFormData, status: e.target.value});
                              else setPenaltyFormData({...penaltyFormData, status: e.target.value});
                              break;
                          }
                        }}
                        className="w-full p-2 border rounded-lg"
                        style={{ borderColor: COLORS.secondary }}
                      >
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                      </select>
                    </div>
                  </div>
                )}

                <div className="flex gap-3 pt-4">
                  <button
                    type="submit"
                    className="px-6 py-2 rounded-lg flex items-center gap-2 transition-all"
                    style={{ backgroundColor: COLORS.primary, color: 'white' }}
                  >
                    <CheckCircle className="w-4 h-4" />
                    {editingType ? 'Update Configuration' : 'Create Configuration'}
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      switch(activeTab) {
                        case 'land': resetLandForm(); break;
                        case 'property': resetPropertyForm(); break;
                        case 'building-assessment': resetBuildingAssessmentForm(); break;
                        case 'tax': resetTaxForm(); break;
                        case 'discount-penalty': 
                          editingType === 'discount' ? resetDiscountForm() : resetPenaltyForm();
                          break;
                      }
                    }}
                    className="px-6 py-2 border rounded-lg hover:bg-gray-50 transition-all"
                    style={{ borderColor: COLORS.secondary, color: COLORS.dark }}
                  >
                    Cancel
                  </button>
                </div>
              </form>
            </div>
          )}

          {/* Configurations List */}
          <div className="p-6">
            <div className="flex justify-between items-center mb-6">
              <h3 className="font-semibold flex items-center gap-2" style={{ color: COLORS.dark }}>
                <FileText className="w-5 h-5" style={{ color: COLORS.primary }} />
                {getTabTitle(activeTab)} Configurations ({activeTab === 'land' ? filteredLandConfigs.length : 
                activeTab === 'property' ? propertyConfigurationsSafe.length :
                activeTab === 'building-assessment' ? buildingAssessmentLevelsSafe.length :
                activeTab === 'tax' ? taxConfigurationsSafe.length :
                discountConfigurationsSafe.length + penaltyConfigurationsSafe.length})
              </h3>
              
              {activeTab === 'discount-penalty' && (
                <div className="flex gap-2">
                  <button
                    onClick={() => {
                      setEditingType('discount');
                      resetDiscountForm();
                      setShowForm(true);
                    }}
                    className="flex items-center gap-2 px-3 py-2 border rounded-lg text-sm transition-all"
                    style={{ 
                      borderColor: COLORS.secondary, 
                      color: COLORS.dark,
                      backgroundColor: 'white'
                    }}
                  >
                    <Percent className="w-4 h-4" />
                    Add Discount
                  </button>
                  <button
                    onClick={() => {
                      setEditingType('penalty');
                      resetPenaltyForm();
                      setShowForm(true);
                    }}
                    className="flex items-center gap-2 px-3 py-2 border rounded-lg text-sm transition-all"
                    style={{ 
                      borderColor: COLORS.secondary, 
                      color: COLORS.dark,
                      backgroundColor: 'white'
                    }}
                  >
                    <Shield className="w-4 h-4" />
                    Add Penalty
                  </button>
                </div>
              )}
            </div>

            {loading ? (
              <div className="text-center py-12">
                <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2" style={{ borderColor: COLORS.primary }}></div>
                <p className="mt-2" style={{ color: COLORS.secondary }}>Loading configurations...</p>
              </div>
            ) : (
              <>
                {activeTab === 'land' && filteredLandConfigs.length === 0 ? (
                  <div className="text-center py-12" style={{ color: COLORS.secondary }}>
                    <Home className="w-12 h-12 mx-auto mb-2" />
                    <p>No land configurations found</p>
                    <button 
                      onClick={() => {
                        resetLandForm();
                        setShowForm(true);
                      }}
                      className="mt-4 px-4 py-2 rounded-lg flex items-center gap-2 mx-auto transition-all"
                      style={{ backgroundColor: COLORS.primary, color: 'white' }}
                    >
                      <Plus className="w-4 h-4" />
                      Add Land Configuration
                    </button>
                  </div>
                ) : activeTab === 'property' && propertyConfigurationsSafe.length === 0 ? (
                  <div className="text-center py-12" style={{ color: COLORS.secondary }}>
                    <Building2 className="w-12 h-12 mx-auto mb-2" />
                    <p>No property configurations found</p>
                  </div>
                ) : activeTab === 'building-assessment' && buildingAssessmentLevelsSafe.length === 0 ? (
                  <div className="text-center py-12" style={{ color: COLORS.secondary }}>
                    <Layers className="w-12 h-12 mx-auto mb-2" />
                    <p>No building assessment levels found</p>
                  </div>
                ) : activeTab === 'tax' && taxConfigurationsSafe.length === 0 ? (
                  <div className="text-center py-12" style={{ color: COLORS.secondary }}>
                    <Calculator className="w-12 h-12 mx-auto mb-2" />
                    <p>No tax configurations found</p>
                  </div>
                ) : activeTab === 'discount-penalty' && discountConfigurationsSafe.length === 0 && penaltyConfigurationsSafe.length === 0 ? (
                  <div className="text-center py-12" style={{ color: COLORS.secondary }}>
                    <Percent className="w-12 h-12 mx-auto mb-2" />
                    <p>No discount or penalty configurations found</p>
                  </div>
                ) : (
                  <div className="overflow-x-auto">
                    <table className="w-full">
                      <thead>
                        <tr style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}>
                          <th className="p-3 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>ID</th>
                          <th className="p-3 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>Details</th>
                          <th className="p-3 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>Values</th>
                          <th className="p-3 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>Dates</th>
                          <th className="p-3 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>Status</th>
                          <th className="p-3 text-left text-sm font-semibold" style={{ color: COLORS.secondary }}>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        {activeTab === 'land' && filteredLandConfigs.map((config) => (
                          <tr key={config.id} className="hover:bg-gray-50 transition-colors" style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}>
                            <td className="p-3">
                              <div className="font-medium" style={{ color: COLORS.dark }}>#{config.id}</div>
                            </td>
                            <td className="p-3">
                              <div className="font-medium" style={{ color: COLORS.dark }}>{config.classification}</div>
                              {config.description && (
                                <div className="text-sm mt-1" style={{ color: COLORS.secondary }}>
                                  {config.description}
                                </div>
                              )}
                            </td>
                            <td className="p-3">
                              <div className="space-y-1">
                                <div className="text-sm">
                                  <span style={{ color: COLORS.secondary }}>Market Value: </span>
                                  <span className="font-medium" style={{ color: COLORS.dark }}>₱{parseFloat(config.market_value || 0).toLocaleString()}/sqm</span>
                                </div>
                                <div className="text-sm">
                                  <span style={{ color: COLORS.secondary }}>Assessment: </span>
                                  <span className="font-medium" style={{ color: COLORS.dark }}>{config.assessment_level}%</span>
                                </div>
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
                                  <span style={{ color: COLORS.dark }}>{config.expiration_date || 'Never'}</span>
                                </div>
                              </div>
                            </td>
                            <td className="p-3">
                              <span className={`px-3 py-1 rounded-full text-sm ${
                                config.status === 'active' 
                                  ? 'bg-green-100 text-green-800' 
                                  : 'bg-gray-100 text-gray-800'
                              }`}>
                                {config.status}
                              </span>
                            </td>
                            <td className="p-3">
                              <div className="flex gap-2">
                                <button
                                  onClick={() => handleLandEdit(config)}
                                  className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                  style={{ color: COLORS.warning }}
                                  disabled={config.status === 'expired'}
                                >
                                  <Edit2 className="w-4 h-4" />
                                </button>
                                <button
                                  onClick={() => handleDelete(config.id, 'land-configurations')}
                                  className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                  style={{ color: COLORS.danger }}
                                >
                                  <Trash2 className="w-4 h-4" />
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))}
                        
                        {activeTab === 'property' && propertyConfigurationsSafe.map((config) => (
                          <tr key={config.id} className="hover:bg-gray-50 transition-colors" style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}>
                            <td className="p-3">
                              <div className="font-medium" style={{ color: COLORS.dark }}>#{config.id}</div>
                            </td>
                            <td className="p-3">
                              <div className="font-medium" style={{ color: COLORS.dark }}>{config.classification}</div>
                              <div className="text-sm mt-1" style={{ color: COLORS.secondary }}>{config.material_type}</div>
                            </td>
                            <td className="p-3">
                              <div className="space-y-1">
                                <div className="text-sm">
                                  <span style={{ color: COLORS.secondary }}>Unit Cost: </span>
                                  <span className="font-medium" style={{ color: COLORS.dark }}>₱{parseFloat(config.unit_cost || 0).toLocaleString()}</span>
                                </div>
                                <div className="text-sm">
                                  <span style={{ color: COLORS.secondary }}>Depreciation: </span>
                                  <span className="font-medium" style={{ color: COLORS.dark }}>{config.depreciation_rate}%</span>
                                </div>
                                <div className="text-sm">
                                  <span style={{ color: COLORS.secondary }}>Range: </span>
                                  <span className="font-medium" style={{ color: COLORS.dark }}>
                                    ₱{parseFloat(config.min_value || 0).toLocaleString()} - ₱{parseFloat(config.max_value || 0).toLocaleString()}
                                  </span>
                                </div>
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
                                  <span style={{ color: COLORS.dark }}>{config.expiration_date || 'Never'}</span>
                                </div>
                              </div>
                            </td>
                            <td className="p-3">
                              <span className={`px-3 py-1 rounded-full text-sm ${
                                config.status === 'active' 
                                  ? 'bg-green-100 text-green-800' 
                                  : 'bg-gray-100 text-gray-800'
                              }`}>
                                {config.status}
                              </span>
                            </td>
                            <td className="p-3">
                              <div className="flex gap-2">
                                <button
                                  onClick={() => handlePropertyEdit(config)}
                                  className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                  style={{ color: COLORS.warning }}
                                  disabled={config.status === 'expired'}
                                >
                                  <Edit2 className="w-4 h-4" />
                                </button>
                                <button
                                  onClick={() => handleDelete(config.id, 'property-configurations')}
                                  className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                  style={{ color: COLORS.danger }}
                                >
                                  <Trash2 className="w-4 h-4" />
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))}
                        
                        {activeTab === 'building-assessment' && buildingAssessmentLevelsSafe.map((config) => (
                          <tr key={config.id} className="hover:bg-gray-50 transition-colors" style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}>
                            <td className="p-3">
                              <div className="font-medium" style={{ color: COLORS.dark }}>#{config.id}</div>
                            </td>
                            <td className="p-3">
                              <div className={`font-medium ${
                                config.classification === 'Commercial' ? 'text-blue-600' :
                                config.classification === 'Residential' ? 'text-green-600' :
                                config.classification === 'Industrial' ? 'text-orange-600' : 'text-purple-600'
                              }`}>
                                {config.classification}
                              </div>
                            </td>
                            <td className="p-3">
                              <div className="space-y-1">
                                <div className="text-sm">
                                  <span style={{ color: COLORS.secondary }}>Value Range: </span>
                                  <span className="font-medium" style={{ color: COLORS.dark }}>
                                    ₱{parseFloat(config.min_assessed_value || 0).toLocaleString()} - ₱{parseFloat(config.max_assessed_value || 0).toLocaleString()}
                                  </span>
                                </div>
                                <div className="text-sm">
                                  <span style={{ color: COLORS.secondary }}>Level: </span>
                                  <span className="font-medium" style={{ color: COLORS.dark }}>{config.level_percent}%</span>
                                </div>
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
                                  <span style={{ color: COLORS.dark }}>{config.expiration_date || 'Never'}</span>
                                </div>
                              </div>
                            </td>
                            <td className="p-3">
                              <span className={`px-3 py-1 rounded-full text-sm ${
                                config.status === 'active' 
                                  ? 'bg-green-100 text-green-800' 
                                  : 'bg-gray-100 text-gray-800'
                              }`}>
                                {config.status}
                              </span>
                            </td>
                            <td className="p-3">
                              <div className="flex gap-2">
                                <button
                                  onClick={() => handleBuildingAssessmentEdit(config)}
                                  className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                  style={{ color: COLORS.warning }}
                                  disabled={config.status === 'expired'}
                                >
                                  <Edit2 className="w-4 h-4" />
                                </button>
                                <button
                                  onClick={() => handleDelete(config.id, 'building-assessment-levels')}
                                  className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                  style={{ color: COLORS.danger }}
                                >
                                  <Trash2 className="w-4 h-4" />
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))}
                        
                        {activeTab === 'tax' && taxConfigurationsSafe.map((config) => (
                          <tr key={config.id} className="hover:bg-gray-50 transition-colors" style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}>
                            <td className="p-3">
                              <div className="font-medium" style={{ color: COLORS.dark }}>#{config.id}</div>
                            </td>
                            <td className="p-3">
                              <div className={`font-medium ${
                                config.tax_name === 'Basic Tax' ? 'text-blue-600' : 'text-green-600'
                              }`}>
                                {config.tax_name}
                              </div>
                            </td>
                            <td className="p-3">
                              <div className="space-y-1">
                                <div className="text-sm">
                                  <span style={{ color: COLORS.secondary }}>Rate: </span>
                                  <span className="font-medium" style={{ color: COLORS.dark }}>{config.tax_percent}%</span>
                                </div>
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
                                  <span style={{ color: COLORS.dark }}>{config.expiration_date || 'Never'}</span>
                                </div>
                              </div>
                            </td>
                            <td className="p-3">
                              <span className={`px-3 py-1 rounded-full text-sm ${
                                config.status === 'active' 
                                  ? 'bg-green-100 text-green-800' 
                                  : 'bg-gray-100 text-gray-800'
                              }`}>
                                {config.status}
                              </span>
                            </td>
                            <td className="p-3">
                              <div className="flex gap-2">
                                <button
                                  onClick={() => handleTaxEdit(config)}
                                  className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                  style={{ color: COLORS.warning }}
                                  disabled={config.status === 'expired'}
                                >
                                  <Edit2 className="w-4 h-4" />
                                </button>
                                <button
                                  onClick={() => handleDelete(config.id, 'tax-configurations')}
                                  className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                  style={{ color: COLORS.danger }}
                                >
                                  <Trash2 className="w-4 h-4" />
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))}
                        
                        {activeTab === 'discount-penalty' && (
                          <>
                            {discountConfigurationsSafe.map((config) => (
                              <tr key={config.id} className="hover:bg-gray-50 transition-colors" style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}>
                                <td className="p-3">
                                  <div className="font-medium" style={{ color: COLORS.dark }}>#{config.id}</div>
                                </td>
                                <td className="p-3">
                                  <div className="font-medium flex items-center gap-2" style={{ color: COLORS.primary }}>
                                    <Percent className="w-4 h-4" />
                                    Discount Configuration
                                  </div>
                                </td>
                                <td className="p-3">
                                  <div className="space-y-1">
                                    <div className="text-sm">
                                      <span style={{ color: COLORS.secondary }}>Discount: </span>
                                      <span className="font-medium" style={{ color: COLORS.dark }}>{config.discount_percent}%</span>
                                    </div>
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
                                      <span style={{ color: COLORS.dark }}>{config.expiration_date || 'Never'}</span>
                                    </div>
                                  </div>
                                </td>
                                <td className="p-3">
                                  <span className={`px-3 py-1 rounded-full text-sm ${
                                    config.status === 'active' 
                                      ? 'bg-green-100 text-green-800' 
                                      : 'bg-gray-100 text-gray-800'
                                  }`}>
                                    {config.status}
                                  </span>
                                </td>
                                <td className="p-3">
                                  <div className="flex gap-2">
                                    <button
                                      onClick={() => handleDiscountEdit(config)}
                                      className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                      style={{ color: COLORS.warning }}
                                      disabled={config.status === 'expired'}
                                    >
                                      <Edit2 className="w-4 h-4" />
                                    </button>
                                    <button
                                      onClick={() => handleDelete(config.id, 'discount-configurations')}
                                      className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                      style={{ color: COLORS.danger }}
                                    >
                                      <Trash2 className="w-4 h-4" />
                                    </button>
                                  </div>
                                </td>
                              </tr>
                            ))}
                            
                            {penaltyConfigurationsSafe.map((config) => (
                              <tr key={config.id} className="hover:bg-gray-50 transition-colors" style={{ borderColor: COLORS.secondary, borderBottomWidth: '1px' }}>
                                <td className="p-3">
                                  <div className="font-medium" style={{ color: COLORS.dark }}>#{config.id}</div>
                                </td>
                                <td className="p-3">
                                  <div className="font-medium flex items-center gap-2" style={{ color: COLORS.danger }}>
                                    <Shield className="w-4 h-4" />
                                    Penalty Configuration
                                  </div>
                                </td>
                                <td className="p-3">
                                  <div className="space-y-1">
                                    <div className="text-sm">
                                      <span style={{ color: COLORS.secondary }}>Penalty: </span>
                                      <span className="font-medium" style={{ color: COLORS.dark }}>{config.penalty_percent}%</span>
                                    </div>
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
                                      <span style={{ color: COLORS.dark }}>{config.expiration_date || 'Never'}</span>
                                    </div>
                                  </div>
                                </td>
                                <td className="p-3">
                                  <span className={`px-3 py-1 rounded-full text-sm ${
                                    config.status === 'active' 
                                      ? 'bg-green-100 text-green-800' 
                                      : 'bg-gray-100 text-gray-800'
                                  }`}>
                                    {config.status}
                                  </span>
                                </td>
                                <td className="p-3">
                                  <div className="flex gap-2">
                                    <button
                                      onClick={() => handlePenaltyEdit(config)}
                                      className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                      style={{ color: COLORS.warning }}
                                      disabled={config.status === 'expired'}
                                    >
                                      <Edit2 className="w-4 h-4" />
                                    </button>
                                    <button
                                      onClick={() => handleDelete(config.id, 'penalty-configurations')}
                                      className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                      style={{ color: COLORS.danger }}
                                    >
                                      <Trash2 className="w-4 h-4" />
                                    </button>
                                  </div>
                                </td>
                              </tr>
                            ))}
                          </>
                        )}
                      </tbody>
                    </table>
                  </div>
                )}
              </>
            )}
          </div>
        </div>

        {/* Footer Summary */}
        <div className="text-center text-sm pt-6 border-t" style={{ color: COLORS.secondary, borderColor: COLORS.secondary }}>
          <p>Real Property Tax Configuration Management • Last Updated: {new Date().toLocaleDateString('en-PH')}</p>
          <p className="text-xs mt-1">
            Total Active Configurations: {activeLandConfigs + activePropertyConfigs + activeBuildingAssessmentConfigs + activeTaxConfigs + activeDiscountConfigs + activePenaltyConfigs}
          </p>
        </div>
      </div>
    </div>
  );
}