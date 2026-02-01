import React, { useState, useEffect } from "react";

export default function Assessed({ registration, documents, fetchData, formatDate, formatCurrency, getDocumentTypeName, navigate }) {
  // State declarations
  const [loading, setLoading] = useState(false);
  const [showAssessmentForm, setShowAssessmentForm] = useState(false);
  const [showRejectForm, setShowRejectForm] = useState(false);
  const [showDocumentModal, setShowDocumentModal] = useState(false);
  const [currentDocument, setCurrentDocument] = useState(null);
  const [rejectionNotes, setRejectionNotes] = useState("");
  const [buildingWarning, setBuildingWarning] = useState("");
  
  // Configurations from database
  const [landConfigs, setLandConfigs] = useState([]);
  const [propertyConfigs, setPropertyConfigs] = useState([]);
  const [buildingAssessmentLevels, setBuildingAssessmentLevels] = useState([]);
  const [taxConfigs, setTaxConfigs] = useState([]);
  
  // Assessment data states
  const [landAssessment, setLandAssessment] = useState(null);
  const [buildingAssessment, setBuildingAssessment] = useState(null);

  // Form states
  const [assessmentForm, setAssessmentForm] = useState({
    land_property_type: "",
    land_area_sqm: "",
    land_market_value: "",
    land_assessed_value: "",
    land_assessment_level: "",
    construction_type: "",
    floor_area_sqm: "",
    year_built: new Date().getFullYear(),
    building_market_value: "",
    building_depreciated_value: "",
    depreciation_percent: "",
    building_assessed_value: "",
    building_assessment_level: ""
  });

  // Calculation states
  const [landCalculations, setLandCalculations] = useState({
    market_value: 0,
    assessed_value: 0,
    assessment_level: 0,
    classification: "",
    market_value_per_sqm: 0,
    basic_tax: 0,
    sef_tax: 0,
    annual_tax: 0
  });

  const [buildingCalculations, setBuildingCalculations] = useState({
    market_value: 0,
    depreciated_value: 0,
    assessed_value: 0,
    assessment_level: 0,
    depreciation_percent: 0,
    material_type: "",
    building_age: 0,
    range_matched: false,
    matched_assessment_level: null,
    market_value_per_sqm: 0,
    basic_tax: 0,
    sef_tax: 0,
    annual_tax: 0
  });

  const [taxCalculations, setTaxCalculations] = useState({
    basic_tax_percent: 0,
    sef_tax_percent: 0,
    total_tax_rate: 0,
    land_basic_tax: 0,
    land_sef_tax: 0,
    land_annual_tax: 0,
    building_basic_tax: 0,
    building_sef_tax: 0,
    building_annual_tax: 0,
    total_basic_tax: 0,
    total_sef_tax: 0,
    total_annual_tax: 0
  });

  // File icon function
  const fileIcon = (fileName) => {
    const ext = fileName.split('.').pop().toLowerCase();
    if (['jpg','jpeg','png','gif'].includes(ext)) return '🖼️';
    if (['pdf'].includes(ext)) return '📄';
    return '📁';
  };

  // Get Document Base URL
  const getDocumentBaseUrl = () => {
    const envApiUrl = import.meta.env.VITE_API_URL;
    if (envApiUrl) {
      return envApiUrl.replace('/backend', '');
    }
    
    const isLocalhost = window.location.hostname === "localhost" || 
                        window.location.hostname === "127.0.0.1";
    
    if (isLocalhost) {
      return "http://localhost/revenue2";
    }
    return "https://revenuetreasury.goserveph.com";
  };

  // Function to get document URL
  const getDocumentUrl = (filePath) => {
    const baseUrl = getDocumentBaseUrl();
    
    let cleanPath = filePath.trim();
    cleanPath = cleanPath.replace(/^(http:\/\/|https:\/\/)[^\/]+\//, '');
    cleanPath = cleanPath.replace(/^\/+/, '');
    
    if (cleanPath.startsWith('revenue2/')) {
      cleanPath = cleanPath.replace('revenue2/', '');
    }
    
    return `${baseUrl}/${cleanPath}`;
  };

  // Get API Base URL
  const getApiBaseUrl = () => {
    const envApiUrl = import.meta.env.VITE_API_URL;
    if (envApiUrl) {
      return `${envApiUrl}/RPT/RPTValidationTable`;
    }
    
    const isLocalhost = window.location.hostname === "localhost" || 
                        window.location.hostname === "127.0.0.1";
    
    if (isLocalhost) {
      return "http://localhost/revenue2/backend/RPT/RPTValidationTable";
    }
    return "https://revenuetreasury.goserveph.com/backend/RPT/RPTValidationTable";
  };

  const handleViewDocument = (doc) => {
    setCurrentDocument(doc);
    setShowDocumentModal(true);
  };

  // Helper function to extract data from API response
  const extractDataFromResponse = (data) => {
    if (!data) return null;
    
    if (data.success === true || data.success === "true") {
      return data.data || data;
    }
    if (data.status === "success" || data.status === "Success") {
      return data.data || data;
    }
    if (data.message && data.message.toLowerCase().includes("success")) {
      return data;
    }
    return data;
  };

  // Fetch configurations from database
  const fetchConfigurations = async () => {
    const API_BASE = getApiBaseUrl();
    
    const endpoints = [
      { 
        key: 'land', 
        url: `${API_BASE}/get_land_configurations.php`,
        setter: setLandConfigs,
        dataKey: 'configurations'
      },
      { 
        key: 'property', 
        url: `${API_BASE}/get_property_configurations.php`,
        setter: setPropertyConfigs,
        dataKey: 'configurations'
      },
      { 
        key: 'assessment', 
        url: `${API_BASE}/get_building_assessment_levels.php`,
        setter: setBuildingAssessmentLevels,
        dataKey: 'assessment_levels'
      },
      { 
        key: 'tax', 
        url: `${API_BASE}/get_tax_configurations.php`,
        setter: setTaxConfigs,
        dataKey: 'tax_configurations'
      }
    ];

    const promises = endpoints.map(async ({ key, url, setter, dataKey }) => {
      try {
        const response = await fetch(url, {
          headers: { 'Accept': 'application/json' }
        });
        
        if (!response.ok) {
          console.warn(`Failed to fetch ${key} config: ${response.status}`);
          return [];
        }
        
        const data = await response.json();
        let configData = extractDataFromResponse(data);
        
        if (dataKey && configData && configData[dataKey]) {
          configData = configData[dataKey];
        }
        
        if (Array.isArray(configData)) {
          const activeItems = configData.filter(item => item.status === 'active');
          setter(activeItems);
          return activeItems;
        } else {
          return [];
        }
      } catch (err) {
        console.error(`Error fetching ${key} config:`, err);
        return [];
      }
    });

    await Promise.all(promises);
  };

  // Fetch assessment data
  const fetchAssessmentData = async () => {
    const API_BASE = getApiBaseUrl();
    
    try {
      const url = `${API_BASE}/get_assessment_data.php?id=${registration.id}`;
      const response = await fetch(url, {
        headers: { 'Accept': 'application/json' }
      });
      
      if (!response.ok) {
        console.log("No assessment data found");
        return;
      }
      
      const data = await response.json();
      let assessmentData = extractDataFromResponse(data);
      
      if (assessmentData) {
        const landData = assessmentData.land_assessment || assessmentData.land || null;
        const buildingData = assessmentData.building_assessment || assessmentData.building || null;
        
        setLandAssessment(landData);
        setBuildingAssessment(buildingData);
        
        if (landData) {
          setAssessmentForm(prev => ({
            ...prev,
            land_property_type: landData.property_type || "",
            land_area_sqm: landData.land_area_sqm || "",
            land_market_value: landData.land_market_value || "",
            land_assessed_value: landData.land_assessed_value || "",
            land_assessment_level: landData.assessment_level || ""
          }));
        }
        
        if (buildingData) {
          setAssessmentForm(prev => ({
            ...prev,
            construction_type: buildingData.construction_type || "",
            floor_area_sqm: buildingData.floor_area_sqm || "",
            year_built: buildingData.year_built || new Date().getFullYear(),
            building_market_value: buildingData.building_market_value || "",
            building_depreciated_value: buildingData.building_depreciated_value || "",
            depreciation_percent: buildingData.depreciation_percent || "",
            building_assessed_value: buildingData.building_assessed_value || "",
            building_assessment_level: buildingData.assessment_level || ""
          }));
        }
      }
    } catch (err) {
      console.error("Error fetching assessment data:", err);
    }
  };

  // Load all data on component mount
  useEffect(() => {
    const loadData = async () => {
      setLoading(true);
      try {
        await Promise.all([
          fetchConfigurations(),
          fetchAssessmentData()
        ]);
      } catch (error) {
        console.error("Error loading data:", error);
      } finally {
        setLoading(false);
      }
    };

    if (registration.id) {
      loadData();
    }
  }, [registration.id]);

  // Land calculation effect
  useEffect(() => {
    calculateLandValues();
  }, [assessmentForm.land_property_type, assessmentForm.land_area_sqm, landConfigs]);

  // Building calculation effect
  useEffect(() => {
    calculateBuildingValues();
  }, [
    assessmentForm.construction_type, 
    assessmentForm.floor_area_sqm, 
    assessmentForm.year_built, 
    propertyConfigs, 
    buildingAssessmentLevels, 
    assessmentForm.land_property_type
  ]);

  // Tax calculation effect
  useEffect(() => {
    if (landCalculations.assessed_value > 0 || buildingCalculations.assessed_value > 0) {
      calculateAnnualTaxes();
    }
  }, [landCalculations.assessed_value, buildingCalculations.assessed_value, taxConfigs]);

  // Construction type filtering effect
  useEffect(() => {
    if (assessmentForm.land_property_type) {
      const filteredTypes = getFilteredConstructionTypes();
      if (filteredTypes.length > 0) {
        if (!filteredTypes.includes(assessmentForm.construction_type)) {
          setAssessmentForm(prev => ({
            ...prev,
            construction_type: filteredTypes[0]
          }));
        }
      } else {
        setAssessmentForm(prev => ({
          ...prev,
          construction_type: ""
        }));
      }
    }
  }, [assessmentForm.land_property_type]);

  // Helper functions
  const getFilteredConstructionTypes = () => {
    if (!assessmentForm.land_property_type) return [];
    
    const classificationPropertyConfigs = propertyConfigs.filter(config => 
      config.classification === assessmentForm.land_property_type && 
      config.status === 'active'
    );
    
    const uniqueMaterialTypes = [...new Set(classificationPropertyConfigs.map(config => config.material_type))];
    return uniqueMaterialTypes;
  };

  const canApproveProperty = () => {
    if (!registration || registration.status !== 'assessed') {
      return false;
    }

    if (!landAssessment) {
      return false;
    }

    if (registration.has_building === 'yes' && !buildingAssessment) {
      return false;
    }

    return true;
  };

  // Calculation functions
  const calculateAnnualTaxes = () => {
    const basicTax = taxConfigs.find(tax => tax.tax_name === 'Basic Tax');
    const sefTax = taxConfigs.find(tax => tax.tax_name === 'SEF Tax');

    const basicTaxPercent = basicTax ? parseFloat(basicTax.tax_percent) || 0 : 0;
    const sefTaxPercent = sefTax ? parseFloat(sefTax.tax_percent) || 0 : 0;
    
    const totalTaxRate = (basicTaxPercent + sefTaxPercent) / 100;

    // Land taxes
    const land_annual_tax = (landCalculations.assessed_value || 0) * totalTaxRate;
    const land_basic_tax = basicTaxPercent > 0 ? land_annual_tax * (basicTaxPercent / (basicTaxPercent + sefTaxPercent)) : 0;
    const land_sef_tax = sefTaxPercent > 0 ? land_annual_tax * (sefTaxPercent / (basicTaxPercent + sefTaxPercent)) : 0;

    // Building taxes
    const building_annual_tax = (buildingCalculations.assessed_value || 0) * totalTaxRate;
    const building_basic_tax = basicTaxPercent > 0 ? building_annual_tax * (basicTaxPercent / (basicTaxPercent + sefTaxPercent)) : 0;
    const building_sef_tax = sefTaxPercent > 0 ? building_annual_tax * (sefTaxPercent / (basicTaxPercent + sefTaxPercent)) : 0;

    // Total taxes
    const total_annual_tax = land_annual_tax + building_annual_tax;
    const total_basic_tax = land_basic_tax + building_basic_tax;
    const total_sef_tax = land_sef_tax + building_sef_tax;

    setTaxCalculations({
      basic_tax_percent: basicTaxPercent,
      sef_tax_percent: sefTaxPercent,
      total_tax_rate: basicTaxPercent + sefTaxPercent,
      land_basic_tax,
      land_sef_tax,
      land_annual_tax,
      building_basic_tax,
      building_sef_tax,
      building_annual_tax,
      total_basic_tax,
      total_sef_tax,
      total_annual_tax
    });

    setLandCalculations(prev => ({
      ...prev,
      basic_tax: land_basic_tax,
      sef_tax: land_sef_tax,
      annual_tax: land_annual_tax
    }));

    setBuildingCalculations(prev => ({
      ...prev,
      basic_tax: building_basic_tax,
      sef_tax: building_sef_tax,
      annual_tax: building_annual_tax
    }));
  };

  const calculateLandValues = () => {
    const { land_property_type, land_area_sqm } = assessmentForm;
    
    if (!land_area_sqm || parseFloat(land_area_sqm) <= 0 || !land_property_type) {
      setLandCalculations({ 
        market_value: 0, 
        assessed_value: 0, 
        assessment_level: 0,
        classification: "",
        market_value_per_sqm: 0,
        basic_tax: 0,
        sef_tax: 0,
        annual_tax: 0
      });
      setAssessmentForm(prev => ({
        ...prev,
        land_market_value: "",
        land_assessed_value: "",
        land_assessment_level: ""
      }));
      return;
    }

    const landConfig = landConfigs.find(config => 
      config.classification === land_property_type && config.status === 'active'
    );

    if (landConfig) {
      const market_value_per_sqm = parseFloat(landConfig.market_value) || 0;
      const area = parseFloat(land_area_sqm) || 0;
      const market_value = area * market_value_per_sqm;
      const assessment_level = parseFloat(landConfig.assessment_level) || 0;
      const assessed_value = market_value * (assessment_level / 100);
      
      setLandCalculations({
        market_value,
        assessed_value,
        assessment_level,
        classification: landConfig.classification,
        market_value_per_sqm,
        basic_tax: landCalculations.basic_tax,
        sef_tax: landCalculations.sef_tax,
        annual_tax: landCalculations.annual_tax
      });

      setAssessmentForm(prev => ({
        ...prev,
        land_market_value: market_value.toFixed(2),
        land_assessed_value: assessed_value.toFixed(2),
        land_assessment_level: assessment_level.toFixed(2)
      }));
    } else {
      setLandCalculations({ 
        market_value: 0, 
        assessed_value: 0, 
        assessment_level: 0,
        classification: "",
        market_value_per_sqm: 0,
        basic_tax: 0,
        sef_tax: 0,
        annual_tax: 0
      });
      setAssessmentForm(prev => ({
        ...prev,
        land_market_value: "",
        land_assessed_value: "",
        land_assessment_level: ""
      }));
    }
  };

  const calculateBuildingValues = () => {
    const { construction_type, floor_area_sqm, year_built, land_property_type } = assessmentForm;
    
    if (!floor_area_sqm || parseFloat(floor_area_sqm) <= 0 || !construction_type || !land_property_type) {
      setBuildingCalculations({ 
        market_value: 0, 
        depreciated_value: 0, 
        assessed_value: 0, 
        assessment_level: 0,
        depreciation_percent: 0,
        material_type: "",
        building_age: 0,
        range_matched: false,
        matched_assessment_level: null,
        market_value_per_sqm: 0,
        basic_tax: 0,
        sef_tax: 0,
        annual_tax: 0
      });
      setAssessmentForm(prev => ({
        ...prev,
        building_market_value: "",
        building_depreciated_value: "",
        building_assessed_value: "",
        building_assessment_level: "",
        depreciation_percent: ""
      }));
      setBuildingWarning("");
      return;
    }

    const propertyConfig = propertyConfigs.find(config => 
      config.material_type === construction_type && 
      config.classification === land_property_type &&
      config.status === 'active'
    );

    if (!propertyConfig) {
      setBuildingWarning(`No configuration found for "${construction_type}" in "${land_property_type}" classification`);
      setBuildingCalculations({ 
        market_value: 0, 
        depreciated_value: 0, 
        assessed_value: 0, 
        assessment_level: 0,
        depreciation_percent: 0,
        material_type: "",
        building_age: 0,
        range_matched: false,
        matched_assessment_level: null,
        market_value_per_sqm: 0,
        basic_tax: 0,
        sef_tax: 0,
        annual_tax: 0
      });
      setAssessmentForm(prev => ({
        ...prev,
        building_market_value: "",
        building_depreciated_value: "",
        building_assessed_value: "",
        building_assessment_level: "",
        depreciation_percent: ""
      }));
      return;
    }

    const currentYear = new Date().getFullYear();
    const buildingAge = Math.max(0, currentYear - parseInt(year_built));
    const market_value_per_sqm = parseFloat(propertyConfig.unit_cost) || 0;
    const area = parseFloat(floor_area_sqm) || 0;
    const market_value = area * market_value_per_sqm;
    const depreciationRate = parseFloat(propertyConfig.depreciation_rate) || 0;
    const depreciationPercent = Math.min(100, buildingAge * depreciationRate);
    const depreciated_value = market_value * ((100 - depreciationPercent) / 100);
    
    const matchingAssessmentLevel = buildingAssessmentLevels.find(level => 
      level.classification === land_property_type &&
      depreciated_value >= parseFloat(level.min_assessed_value || 0) && 
      depreciated_value <= parseFloat(level.max_assessed_value || Infinity) &&
      level.status === 'active'
    );

    if (matchingAssessmentLevel) {
      const assessmentLevelPercent = parseFloat(matchingAssessmentLevel.level_percent) || 0;
      const assessed_value = depreciated_value * (assessmentLevelPercent / 100);
      
      setBuildingCalculations({
        market_value,
        depreciated_value,
        assessed_value,
        assessment_level: assessmentLevelPercent,
        depreciation_percent: depreciationPercent,
        material_type: construction_type,
        building_age: buildingAge,
        range_matched: true,
        matched_assessment_level: matchingAssessmentLevel,
        market_value_per_sqm,
        basic_tax: buildingCalculations.basic_tax,
        sef_tax: buildingCalculations.sef_tax,
        annual_tax: buildingCalculations.annual_tax
      });

      setAssessmentForm(prev => ({
        ...prev,
        building_market_value: market_value.toFixed(2),
        building_depreciated_value: depreciated_value.toFixed(2),
        building_assessed_value: assessed_value.toFixed(2),
        building_assessment_level: assessmentLevelPercent.toFixed(2),
        depreciation_percent: depreciationPercent.toFixed(2)
      }));

      setBuildingWarning("");
    } else {
      const classificationLevels = buildingAssessmentLevels.filter(
        level => level.classification === land_property_type && level.status === 'active'
      );
      
      setBuildingCalculations({
        market_value,
        depreciated_value,
        assessed_value: 0,
        assessment_level: 0,
        depreciation_percent: depreciationPercent,
        material_type: construction_type,
        building_age: buildingAge,
        range_matched: false,
        matched_assessment_level: null,
        market_value_per_sqm,
        basic_tax: 0,
        sef_tax: 0,
        annual_tax: 0
      });

      setAssessmentForm(prev => ({
        ...prev,
        building_market_value: market_value.toFixed(2),
        building_depreciated_value: depreciated_value.toFixed(2),
        building_assessed_value: "",
        building_assessment_level: "",
        depreciation_percent: depreciationPercent.toFixed(2)
      }));

      if (classificationLevels.length > 0) {
        const minRange = Math.min(...classificationLevels.map(level => parseFloat(level.min_assessed_value || 0)));
        const maxRange = Math.max(...classificationLevels.map(level => parseFloat(level.max_assessed_value || Infinity)));
        setBuildingWarning(
          `Depreciated value ${formatCurrency(depreciated_value)} is outside configured ranges (${formatCurrency(minRange)} - ${formatCurrency(maxRange)})`
        );
      } else {
        setBuildingWarning(
          `No assessment levels configured for "${land_property_type}" classification`
        );
      }
    }
  };

  const handleReject = async () => {
    if (!rejectionNotes.trim()) {
      alert("Please enter rejection notes");
      return;
    }

    setLoading(true);
    try {
      const API_BASE = getApiBaseUrl();
      const response = await fetch(`${API_BASE}/reject_registration.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          registration_id: registration.id,
          status: 'needs_correction',
          correction_notes: rejectionNotes
        })
      });

      const data = await response.json();
      
      if (data.success === true || data.success === "true") {
        alert("✅ Application marked as 'Needs Correction'");
        setShowRejectForm(false);
        setRejectionNotes("");
        await fetchData();
      } else {
        throw new Error(data.message || data.error || "Failed to reject application");
      }
    } catch (error) {
      alert(`❌ Error: ${error.message}`);
    } finally {
      setLoading(false);
    }
  };

  const handleApprove = async () => {
    if (window.confirm("Approve this property assessment?\n\nThis will:\n• Generate TDNs\n• Create tax records\n• Generate quarterly bills")) {
      setLoading(true);
      try {
        const API_BASE = getApiBaseUrl();
        const response = await fetch(`${API_BASE}/approve_property.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            registration_id: registration.id,
            land_annual_tax: taxCalculations.land_annual_tax,
            building_annual_tax: taxCalculations.building_annual_tax,
            total_annual_tax: taxCalculations.total_annual_tax
          })
        });

        const data = await response.json();
        
        if (data.success === true || data.success === "true") {
          const tdns = data.tdns || data.data?.tdns || {};
          const message = data.message || "Property approved successfully!";
          alert(`✅ ${message}\n\nLand TDN: ${tdns.land_tdn || 'N/A'}\nBuilding TDN: ${tdns.building_tdn || 'N/A'}`);
          await fetchData();
        } else {
          throw new Error(data.message || data.error || "Failed to approve property");
        }
      } catch (error) {
        alert(`❌ Error: ${error.message}`);
      } finally {
        setLoading(false);
      }
    }
  };

  const handleAssessmentSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    
    if (buildingWarning && registration.has_building === 'yes') {
      const proceed = window.confirm(
        "Building value is outside configured ranges. Proceed anyway?"
      );
      if (!proceed) {
        setLoading(false);
        return;
      }
    }

    try {
      const API_BASE = getApiBaseUrl();
      const submissionData = {
        registration_id: registration.id,
        land_property_type: assessmentForm.land_property_type,
        land_area_sqm: assessmentForm.land_area_sqm,
        land_market_value: assessmentForm.land_market_value,
        land_assessed_value: assessmentForm.land_assessed_value,
        land_assessment_level: assessmentForm.land_assessment_level,
        ...(registration.has_building === 'yes' && {
          construction_type: assessmentForm.construction_type,
          floor_area_sqm: assessmentForm.floor_area_sqm,
          year_built: assessmentForm.year_built,
          useful_life_years: 50,
          building_market_value: assessmentForm.building_market_value,
          building_depreciated_value: assessmentForm.building_depreciated_value,
          depreciation_percent: assessmentForm.depreciation_percent,
          building_assessed_value: assessmentForm.building_assessed_value,
          building_assessment_level: assessmentForm.building_assessment_level
        })
      };

      const response = await fetch(`${API_BASE}/assess_property.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(submissionData)
      });

      const data = await response.json();
      
      if (data.success === true || data.success === "true") {
        const message = data.message || "Assessment saved successfully!";
        alert(`✅ ${message}`);
        setShowAssessmentForm(false);
        await fetchAssessmentData();
      } else {
        throw new Error(data.message || data.error || "Failed to assess property");
      }
    } catch (error) {
      alert(`❌ Error: ${error.message}`);
    } finally {
      setLoading(false);
    }
  };

  // Loading state
  if (loading && !showAssessmentForm && !showRejectForm) {
    return (
      <div className="min-h-screen bg-gradient-to-b from-gray-50 to-blue-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-purple-600 mx-auto mb-4"></div>
          <p className="text-gray-700 font-medium">Loading assessment data...</p>
          <p className="text-gray-500 text-sm mt-1">Please wait while we prepare everything</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-b from-gray-50 to-blue-50 py-6">
      <div className="max-w-7xl mx-auto px-4">

        {/* Header / Status Card - Glass Effect */}
        <div className="bg-white/95 backdrop-blur-sm rounded-2xl shadow-xl border border-white/30 p-6 mb-8">
          <div className="flex items-center justify-between mb-6">
            <div>
              <button 
                onClick={() => navigate(-1)} 
                className="text-gray-600 hover:text-purple-600 mb-3 flex items-center transition-colors"
              >
                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Dashboard
              </button>
              <h1 className="text-3xl font-bold text-gray-900">Property Assessment</h1>
              <p className="text-gray-600 mt-2">Reference: <span className="font-semibold text-purple-700">{registration.reference_number}</span></p>
            </div>
            <span className="bg-gradient-to-r from-purple-500 to-indigo-600 text-white px-4 py-2 rounded-full font-semibold shadow-lg flex items-center">
              <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
              ASSESSED
            </span>
          </div>

          {/* Progress Bar with Steps */}
          <div className="mt-8">
            <div className="flex justify-between text-sm font-medium text-gray-600 mb-2">
              <span>Submitted</span>
              <span>Inspection</span>
              <span className="text-purple-600">Assessment</span>
              <span>Approved</span>
            </div>
            <div className="relative">
              <div className="w-full h-3 bg-gray-200 rounded-full overflow-hidden shadow-inner">
                <div className="h-3 bg-gradient-to-r from-purple-400 to-indigo-500 rounded-full transition-all duration-500" style={{ width: '75%' }}></div>
              </div>
              <div className="flex justify-between mt-2">
                {[1, 2, 3, 4].map((step) => (
                  <div 
                    key={step} 
                    className={`w-8 h-8 rounded-full flex items-center justify-center ${
                      step === 3 
                        ? 'bg-gradient-to-r from-purple-500 to-indigo-600 text-white shadow-lg scale-110' 
                        : step < 3 
                          ? 'bg-green-500 text-white shadow-md' 
                          : 'bg-gray-300 text-gray-600'
                    } transition-all duration-300`}
                  >
                    {step}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Main Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          
          {/* Left Column - Documents & Info */}
          <div className="lg:col-span-2 space-y-8">
            
            {/* Documents Card */}
            <div className="bg-white rounded-2xl shadow-lg p-6">
              <div className="flex items-center justify-between mb-6">
                <h2 className="text-xl font-bold text-gray-900 flex items-center">
                  <svg className="w-6 h-6 mr-3 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  Uploaded Documents
                </h2>
                <span className="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-sm font-semibold">
                  {documents.length} files
                </span>
              </div>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {documents.map((doc, i) => (
                  <div key={i} className="border border-gray-200 hover:border-purple-300 rounded-xl p-4 hover:shadow-lg transition-all duration-300 bg-white group">
                    <div className="flex items-start mb-4">
                      <div className="w-12 h-12 bg-gradient-to-br from-purple-100 to-purple-50 rounded-lg flex items-center justify-center mr-4 text-2xl group-hover:scale-110 transition-transform">
                        {fileIcon(doc.file_name)}
                      </div>
                      <div className="flex-1">
                        <h3 className="font-semibold text-gray-900 group-hover:text-purple-700 transition-colors">
                          {getDocumentTypeName(doc.document_type)}
                        </h3>
                        <p className="text-sm text-gray-500 truncate" title={doc.file_name}>{doc.file_name}</p>
                        <div className="mt-2">
                          <span className="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">
                            {doc.file_name.split('.').pop().toUpperCase()}
                          </span>
                        </div>
                      </div>
                    </div>
                    <button
                      onClick={() => handleViewDocument(doc)}
                      className="w-full bg-gradient-to-r from-gray-50 to-gray-100 hover:from-purple-50 hover:to-purple-100 text-gray-700 hover:text-purple-700 py-2.5 rounded-lg text-sm font-medium transition-all duration-300 flex items-center justify-center group"
                    >
                      <svg className="w-4 h-4 mr-2 group-hover:animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                      View Document
                    </button>
                  </div>
                ))}
              </div>
            </div>

            {/* Current Assessment Summary - Enhanced */}
            {(landAssessment || buildingAssessment) && (
              <div className="bg-white rounded-2xl shadow-lg p-6">
                <h2 className="text-xl font-bold text-gray-900 mb-6 flex items-center">
                  <svg className="w-6 h-6 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                  Current Assessment Summary
                </h2>
                
                {/* Land Assessment */}
                {landAssessment && (
                  <div className="mb-8 p-5 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl border border-blue-100">
                    <div className="flex items-center justify-between mb-4">
                      <h3 className="text-lg font-semibold text-blue-800 flex items-center">
                        <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Land Assessment
                      </h3>
                      <span className="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">✓ Completed</span>
                    </div>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                      <div className="bg-white/80 p-4 rounded-lg border border-blue-200">
                        <p className="text-xs text-blue-600 font-medium mb-1">Property Type</p>
                        <p className="font-semibold text-gray-900">{landAssessment.property_type}</p>
                      </div>
                      <div className="bg-white/80 p-4 rounded-lg border border-blue-200">
                        <p className="text-xs text-blue-600 font-medium mb-1">Area (sqm)</p>
                        <p className="font-semibold text-gray-900">{landAssessment.land_area_sqm}</p>
                      </div>
                      <div className="bg-white/80 p-4 rounded-lg border border-blue-200">
                        <p className="text-xs text-blue-600 font-medium mb-1">Market Value</p>
                        <p className="font-semibold text-blue-700">{formatCurrency(parseFloat(landAssessment.land_market_value || 0))}</p>
                      </div>
                      <div className="bg-white/80 p-4 rounded-lg border border-green-200">
                        <p className="text-xs text-green-600 font-medium mb-1">Assessed Value</p>
                        <p className="font-semibold text-green-700">{formatCurrency(parseFloat(landAssessment.land_assessed_value || 0))}</p>
                      </div>
                    </div>
                  </div>
                )}
                
                {/* Building Assessment */}
                {buildingAssessment && (
                  <div className="p-5 bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl border border-green-100">
                    <div className="flex items-center justify-between mb-4">
                      <h3 className="text-lg font-semibold text-green-800 flex items-center">
                        <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        Building Assessment
                      </h3>
                      <span className="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">✓ Completed</span>
                    </div>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                      <div className="bg-white/80 p-4 rounded-lg border border-green-200">
                        <p className="text-xs text-green-600 font-medium mb-1">Construction Type</p>
                        <p className="font-semibold text-gray-900">{buildingAssessment.construction_type}</p>
                      </div>
                      <div className="bg-white/80 p-4 rounded-lg border border-green-200">
                        <p className="text-xs text-green-600 font-medium mb-1">Floor Area (sqm)</p>
                        <p className="font-semibold text-gray-900">{buildingAssessment.floor_area_sqm}</p>
                      </div>
                      <div className="bg-white/80 p-4 rounded-lg border border-green-200">
                        <p className="text-xs text-green-600 font-medium mb-1">Year Built</p>
                        <p className="font-semibold text-gray-900">{buildingAssessment.year_built}</p>
                      </div>
                      <div className="bg-white/80 p-4 rounded-lg border border-emerald-200">
                        <p className="text-xs text-emerald-600 font-medium mb-1">Assessed Value</p>
                        <p className="font-semibold text-emerald-700">{formatCurrency(parseFloat(buildingAssessment.building_assessed_value || 0))}</p>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* Tax Calculation Summary - Enhanced */}
            {(landCalculations.assessed_value > 0 || buildingCalculations.assessed_value > 0) && (
              <div className="bg-white rounded-2xl shadow-lg p-6">
                <h2 className="text-xl font-bold text-gray-900 mb-6 flex items-center">
                  <svg className="w-6 h-6 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Tax Calculation Summary
                </h2>
                
                {/* Tax Rates */}
                <div className="mb-8 p-5 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl border border-blue-100">
                  <h3 className="font-semibold text-blue-800 mb-4">Tax Rates</h3>
                  <div className="grid grid-cols-3 gap-4">
                    <div className="bg-white/80 p-4 rounded-lg border border-blue-200 text-center">
                      <p className="text-sm text-blue-600 mb-1">Basic Tax</p>
                      <p className="text-xl font-bold text-blue-700">{taxCalculations.basic_tax_percent}%</p>
                    </div>
                    <div className="bg-white/80 p-4 rounded-lg border border-blue-200 text-center">
                      <p className="text-sm text-blue-600 mb-1">SEF Tax</p>
                      <p className="text-xl font-bold text-blue-700">{taxCalculations.sef_tax_percent}%</p>
                    </div>
                    <div className="bg-gradient-to-r from-green-100 to-emerald-100 p-4 rounded-lg border border-green-200 text-center">
                      <p className="text-sm text-green-600 mb-1">Total Tax Rate</p>
                      <p className="text-xl font-bold text-green-700">{taxCalculations.total_tax_rate}%</p>
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                  {/* Land Tax */}
                  <div className="bg-gradient-to-br from-blue-50 to-indigo-50 p-5 rounded-xl border border-blue-100">
                    <h3 className="font-semibold text-blue-800 mb-4 flex items-center">
                      <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                      </svg>
                      Land Tax
                    </h3>
                    <div className="space-y-3">
                      <div className="flex justify-between items-center py-2 border-b border-blue-100">
                        <span className="text-gray-600">Basic Tax:</span>
                        <span className="font-semibold text-blue-700">{formatCurrency(taxCalculations.land_basic_tax)}</span>
                      </div>
                      <div className="flex justify-between items-center py-2 border-b border-blue-100">
                        <span className="text-gray-600">SEF Tax:</span>
                        <span className="font-semibold text-blue-700">{formatCurrency(taxCalculations.land_sef_tax)}</span>
                      </div>
                      <div className="pt-3 mt-3 border-t border-blue-200">
                        <div className="flex justify-between items-center">
                          <span className="font-semibold text-green-700">Annual Tax:</span>
                          <span className="text-lg font-bold text-green-700">{formatCurrency(taxCalculations.land_annual_tax)}</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Building Tax */}
                  {buildingCalculations.assessed_value > 0 && (
                    <div className="bg-gradient-to-br from-green-50 to-emerald-50 p-5 rounded-xl border border-green-100">
                      <h3 className="font-semibold text-green-800 mb-4 flex items-center">
                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        Building Tax
                      </h3>
                      <div className="space-y-3">
                        <div className="flex justify-between items-center py-2 border-b border-green-100">
                          <span className="text-gray-600">Basic Tax:</span>
                          <span className="font-semibold text-blue-700">{formatCurrency(taxCalculations.building_basic_tax)}</span>
                        </div>
                        <div className="flex justify-between items-center py-2 border-b border-green-100">
                          <span className="text-gray-600">SEF Tax:</span>
                          <span className="font-semibold text-blue-700">{formatCurrency(taxCalculations.building_sef_tax)}</span>
                        </div>
                        <div className="pt-3 mt-3 border-t border-green-200">
                          <div className="flex justify-between items-center">
                            <span className="font-semibold text-green-700">Annual Tax:</span>
                            <span className="text-lg font-bold text-green-700">{formatCurrency(taxCalculations.building_annual_tax)}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Total Tax */}
                  <div className="bg-gradient-to-br from-emerald-50 to-green-50 p-5 rounded-xl border border-emerald-200 shadow-sm">
                    <h3 className="font-semibold text-emerald-800 mb-4 flex items-center">
                      <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                      </svg>
                      Total Property Tax
                    </h3>
                    <div className="space-y-3">
                      <div className="flex justify-between items-center py-2 border-b border-emerald-100">
                        <span className="text-gray-600">Total Basic Tax:</span>
                        <span className="font-semibold text-blue-700">{formatCurrency(taxCalculations.total_basic_tax)}</span>
                      </div>
                      <div className="flex justify-between items-center py-2 border-b border-emerald-100">
                        <span className="text-gray-600">Total SEF Tax:</span>
                        <span className="font-semibold text-blue-700">{formatCurrency(taxCalculations.total_sef_tax)}</span>
                      </div>
                      <div className="pt-3 mt-3 border-t border-emerald-300">
                        <div className="flex justify-between items-center">
                          <span className="text-lg font-bold text-emerald-700">Total Annual Tax:</span>
                          <span className="text-2xl font-bold text-emerald-700">{formatCurrency(taxCalculations.total_annual_tax)}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* Application Details Card */}
            <div className="bg-white rounded-2xl shadow-lg p-6">
              <h2 className="text-xl font-bold text-gray-900 mb-6 flex items-center">
                <svg className="w-6 h-6 mr-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Application Details
              </h2>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Owner Information */}
                <div className="bg-gradient-to-br from-blue-50 to-indigo-50 p-5 rounded-xl space-y-4">
                  <div className="flex items-center">
                    <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                      <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </div>
                    <h3 className="font-semibold text-gray-800 text-lg">Owner Information</h3>
                  </div>
                  
                  <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Full Name</label>
                        <p className="text-gray-900 font-medium">
                          {registration.owner_name || 
                           `${registration.first_name || ''} ${registration.last_name || ''}`.trim() || 
                           'N/A'}
                        </p>
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Sex</label>
                        <p className="text-gray-900 font-medium">{registration.sex || 'N/A'}</p>
                      </div>
                    </div>
                    
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Marital Status</label>
                        <p className="text-gray-900 font-medium">{registration.marital_status || 'N/A'}</p>
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Birthdate</label>
                        <p className="text-gray-900 font-medium">
                          {registration.birthdate ? formatDate(registration.birthdate, 'MMMM d, yyyy') : 'N/A'}
                        </p>
                      </div>
                    </div>
                    
                    <div>
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Address</label>
                      <p className="text-gray-900 font-medium">
                        {registration.owner_address || registration.address || 'N/A'}
                      </p>
                    </div>
                    
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Contact</label>
                        <p className="text-gray-900 font-medium">{registration.contact_number || registration.phone || 'N/A'}</p>
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Email</label>
                        <p className="text-gray-900 font-medium truncate">{registration.email_address || registration.email || 'N/A'}</p>
                      </div>
                    </div>
                    
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">TIN</label>
                        <p className="text-gray-900 font-medium">{registration.tin || 'N/A'}</p>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Property Information */}
                <div className="bg-gradient-to-br from-green-50 to-emerald-50 p-5 rounded-xl space-y-4">
                  <div className="flex items-center">
                    <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                      <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                      </svg>
                    </div>
                    <h3 className="font-semibold text-gray-800 text-lg">Property Information</h3>
                  </div>
                  
                  <div className="space-y-3">
                    <div>
                      <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Lot Location</label>
                      <p className="text-gray-900 font-medium">{registration.lot_location || registration.location_address || 'N/A'}</p>
                    </div>
                    
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Barangay</label>
                        <p className="text-gray-900 font-medium">{registration.barangay || 'N/A'}</p>
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">District</label>
                        <p className="text-gray-900 font-medium">{registration.district || 'N/A'}</p>
                      </div>
                    </div>
                    
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">City</label>
                        <p className="text-gray-900 font-medium">{registration.city || registration.municipality_city || 'N/A'}</p>
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Province</label>
                        <p className="text-gray-900 font-medium">{registration.province || 'N/A'}</p>
                      </div>
                    </div>
                    
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">ZIP Code</label>
                        <p className="text-gray-900 font-medium">{registration.zip_code || 'N/A'}</p>
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Has Building</label>
                        <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${registration.has_building === 'yes' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                          {registration.has_building === 'yes' ? '✅ Yes' : '❌ No'}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              {/* Registration Date */}
              <div className="mt-6 pt-6 border-t border-gray-200">
                <div className="flex items-center text-sm text-gray-600">
                  <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  Date Registered: <span className="font-semibold ml-1">{formatDate(registration.date_registered || registration.created_at, 'MMMM d, yyyy at hh:mm a')}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Right Column - Admin Actions */}
          <div className="space-y-8">
            {/* Admin Actions Card */}
            <div className="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-2xl shadow-lg p-6 border border-purple-100">
              <div className="flex items-center mb-6">
                <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                  <svg className="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </div>
                <h2 className="text-lg font-bold text-gray-900">Admin Actions</h2>
              </div>
              
              <div className="space-y-4">
                <button
                  onClick={() => setShowAssessmentForm(true)}
                  disabled={loading}
                  className="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 disabled:from-blue-400 disabled:to-blue-400 text-white px-4 py-3.5 rounded-xl flex items-center justify-center shadow-lg hover:shadow-xl transition-all duration-300 group"
                >
                  <svg className="w-5 h-5 mr-3 group-hover:animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  {landAssessment ? 'Edit Assessment Data' : 'Input Assessment Data'}
                </button>
                
                <button
                  onClick={handleApprove}
                  disabled={loading || !canApproveProperty()}
                  className="w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 disabled:from-green-400 disabled:to-emerald-400 text-white px-4 py-3.5 rounded-xl flex items-center justify-center shadow-lg hover:shadow-xl transition-all duration-300 group"
                >
                  <svg className="w-5 h-5 mr-3 group-hover:animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  Approve Property
                </button>
                
                <button
                  onClick={() => setShowRejectForm(true)}
                  disabled={loading}
                  className="w-full bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 disabled:from-red-400 disabled:to-pink-400 text-white px-4 py-3.5 rounded-xl flex items-center justify-center shadow-lg hover:shadow-xl transition-all duration-300 group"
                >
                  <svg className="w-5 h-5 mr-3 group-hover:animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.106 16.5c-.77.833.192 2.5 1.732 2.5z" />
                  </svg>
                  Mark for Correction
                </button>
              </div>

              {/* Quick Info */}
              <div className="mt-8 pt-6 border-t border-purple-200">
                <h3 className="text-sm font-semibold text-gray-700 mb-4 flex items-center">
                  <svg className="w-4 h-4 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Application Info
                </h3>
                
                <div className="space-y-3 bg-white/50 p-4 rounded-lg">
                  {[
                    { label: 'Reference No.', value: registration.reference_number },
                    { label: 'Status', value: 'Assessed', color: 'text-purple-700' },
                    { label: 'Submitted Date', value: formatDate(registration.date_registered || registration.created_at, 'MMM d, yyyy') },
                    { label: 'Documents', value: `${documents.length} files` },
                    { label: 'Property Type', value: registration.has_building === 'yes' ? 'With Building' : 'Vacant Land' },
                    { label: 'Land Assessed', value: landAssessment ? '✓ Completed' : '❌ Pending', color: landAssessment ? 'text-green-700' : 'text-yellow-700' },
                    ...(registration.has_building === 'yes' ? [
                      { label: 'Building Assessed', value: buildingAssessment ? '✓ Completed' : '❌ Pending', color: buildingAssessment ? 'text-green-700' : 'text-yellow-700' }
                    ] : []),
                  ].map((item, idx) => (
                    <div key={idx} className="flex justify-between items-center py-1">
                      <span className="text-xs text-gray-600 font-medium">{item.label}</span>
                      <span className={`text-xs font-semibold ${item.color || 'text-gray-900'}`}>{item.value}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* Status Info Card */}
            <div className="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-2xl shadow-lg p-6 border border-purple-100">
              <div className="flex items-center mb-4">
                <svg className="w-6 h-6 mr-3 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <h3 className="font-semibold text-gray-800">Current Status</h3>
              </div>
              
              <div className="space-y-3">
                <div className="bg-white/70 p-4 rounded-lg">
                  <div className="text-sm text-gray-600 mb-2">Next Step:</div>
                  <div className="font-semibold text-purple-700">Final Approval & TDN Generation</div>
                </div>
                
                <div className="text-sm text-gray-600">
                  <p className="mb-2">Requirements:</p>
                  <ul className="space-y-1">
                    <li className="flex items-center">
                      <div className={`w-2 h-2 rounded-full mr-2 ${landAssessment ? 'bg-green-500' : 'bg-yellow-500'}`}></div>
                      <span>Land assessment {landAssessment ? 'completed' : 'pending'}</span>
                    </li>
                    {registration.has_building === 'yes' && (
                      <li className="flex items-center">
                        <div className={`w-2 h-2 rounded-full mr-2 ${buildingAssessment ? 'bg-green-500' : 'bg-yellow-500'}`}></div>
                        <span>Building assessment {buildingAssessment ? 'completed' : 'pending'}</span>
                      </li>
                    )}
                    <li className="flex items-center">
                      <div className={`w-2 h-2 rounded-full mr-2 ${canApproveProperty() ? 'bg-green-500' : 'bg-blue-400'}`}></div>
                      <span>Ready for approval: {canApproveProperty() ? 'Yes' : 'No'}</span>
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            {/* Assessment Required Alert */}
            {!landAssessment && (
              <div className="bg-gradient-to-br from-yellow-50 to-amber-50 rounded-2xl shadow-lg p-6 border border-yellow-100">
                <div className="flex items-start">
                  <div className="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-4 flex-shrink-0">
                    <span className="text-yellow-600">⚠️</span>
                  </div>
                  <div>
                    <h3 className="font-semibold text-gray-900 mb-2">Assessment Required</h3>
                    <ul className="text-gray-700 space-y-2 text-sm">
                      <li className="flex items-start">
                        <span className="text-yellow-500 mr-2">•</span>
                        <span>Click "Input Assessment Data" to begin</span>
                      </li>
                      <li className="flex items-start">
                        <span className="text-yellow-500 mr-2">•</span>
                        <span>Land assessment is required for all properties</span>
                      </li>
                      <li className="flex items-start">
                        <span className="text-yellow-500 mr-2">•</span>
                        <span>Building assessment required if property has building</span>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* View Document Modal */}
        {showDocumentModal && currentDocument && (
          <div className="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4 z-50">
            <div className="bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] flex flex-col animate-fadeIn">
              <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gradient-to-r from-purple-50 to-indigo-50 rounded-t-2xl">
                <div className="flex items-center">
                  <div className="w-12 h-12 bg-gradient-to-br from-purple-100 to-purple-200 rounded-xl flex items-center justify-center mr-4 text-2xl shadow">
                    {fileIcon(currentDocument.file_name)}
                  </div>
                  <div>
                    <h3 className="text-xl font-bold text-gray-900">{getDocumentTypeName(currentDocument.document_type)}</h3>
                    <p className="text-sm text-gray-600 truncate max-w-lg">{currentDocument.file_name}</p>
                  </div>
                </div>
                <div className="flex items-center space-x-3">
                  <button
                    onClick={() => window.open(getDocumentUrl(currentDocument.file_path), '_blank')}
                    className="text-sm font-medium text-purple-600 hover:text-purple-700 px-4 py-2 rounded-lg border border-purple-200 hover:border-purple-300 hover:bg-purple-50 transition-colors flex items-center shadow-sm"
                  >
                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Download
                  </button>
                  <button 
                    onClick={() => setShowDocumentModal(false)} 
                    className="text-gray-500 hover:text-gray-700 hover:bg-gray-100 p-2 rounded-full transition-colors"
                  >
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>
              
              <div className="flex-1 overflow-auto p-4">
                <div className="bg-gray-50 rounded-xl border border-gray-300 flex items-center justify-center min-h-[60vh] p-4">
                  {currentDocument.file_name.toLowerCase().endsWith('.pdf') ? (
                    <iframe 
                      src={getDocumentUrl(currentDocument.file_path)}
                      className="w-full h-[60vh] border-0 rounded-lg shadow"
                      title={currentDocument.file_name}
                    />
                  ) : currentDocument.file_name.toLowerCase().match(/\.(jpg|jpeg|png|gif)$/) ? (
                    <img 
                      src={getDocumentUrl(currentDocument.file_path)} 
                      alt={currentDocument.file_name}
                      className="max-w-full max-h-[60vh] object-contain rounded-lg shadow"
                    />
                  ) : (
                    <div className="text-center p-8">
                      <div className="text-5xl mb-4">📄</div>
                      <h4 className="text-xl font-semibold text-gray-700 mb-3">Document Preview Not Available</h4>
                      <p className="text-gray-600 mb-6">This file type cannot be previewed in the browser.</p>
                      <button
                        onClick={() => window.open(getDocumentUrl(currentDocument.file_path), '_blank')}
                        className="bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-all duration-300 shadow-lg hover:shadow-xl"
                      >
                        Download File
                      </button>
                    </div>
                  )}
                </div>
              </div>
              
              <div className="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                <div className="flex justify-between items-center">
                  <div className="text-sm text-gray-600">
                    <div className="flex items-center space-x-6">
                      <div>
                        <span className="font-medium">Document Type:</span> {getDocumentTypeName(currentDocument.document_type)}
                      </div>
                      <div>
                        <span className="font-medium">Uploaded:</span> {formatDate(currentDocument.uploaded_at || registration.date_registered || registration.created_at, 'MMM d, yyyy • h:mm a')}
                      </div>
                    </div>
                  </div>
                  <div className="flex space-x-3">
                    <button
                      onClick={() => {
                        const url = getDocumentUrl(currentDocument.file_path);
                        window.open(url, '_blank');
                      }}
                      className="text-sm font-medium text-purple-600 hover:text-purple-700 px-4 py-2 rounded-lg border border-purple-200 hover:border-purple-300 hover:bg-purple-50 transition-colors"
                    >
                      Open in New Tab
                    </button>
                    <button
                      onClick={() => setShowDocumentModal(false)}
                      className="text-sm font-medium text-gray-700 hover:text-gray-900 px-4 py-2 rounded-lg border border-gray-300 hover:border-gray-400 hover:bg-gray-100 transition-colors"
                    >
                      Close
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Assessment Form Modal */}
        {showAssessmentForm && (
          <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50">
            <div className="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto animate-slideUp">
              <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                  <h3 className="text-xl font-semibold text-gray-900">Property Assessment Form</h3>
                  <button onClick={() => setShowAssessmentForm(false)} className="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-1 rounded-full">
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
                
                {buildingWarning && (
                  <div className="mb-4 bg-gradient-to-r from-yellow-50 to-amber-50 border border-yellow-200 rounded-xl p-4">
                    <div className="flex items-start">
                      <span className="text-yellow-500 mr-2 mt-0.5">⚠️</span>
                      <p className="text-yellow-700 text-sm">{buildingWarning}</p>
                    </div>
                  </div>
                )}

                <form onSubmit={handleAssessmentSubmit} className="space-y-8">
                  {/* Land Assessment Section */}
                  <div className="bg-gradient-to-br from-blue-50 to-indigo-50 p-5 rounded-xl border border-blue-100">
                    <h4 className="font-semibold text-blue-800 mb-4 text-lg flex items-center">
                      <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                      </svg>
                      Land Assessment
                    </h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Property Type *</label>
                        <select
                          value={assessmentForm.land_property_type}
                          onChange={(e) => setAssessmentForm({...assessmentForm, land_property_type: e.target.value})}
                          className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                          required
                        >
                          <option value="">Select Property Type</option>
                          {landConfigs.map((config) => (
                            <option key={config.id} value={config.classification}>
                              {config.classification}
                            </option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Land Area (sqm) *</label>
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          value={assessmentForm.land_area_sqm}
                          onChange={(e) => setAssessmentForm({...assessmentForm, land_area_sqm: e.target.value})}
                          className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                          placeholder="Enter land area"
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Market Value per sqm</label>
                        <input
                          type="text"
                          value={formatCurrency(landCalculations.market_value_per_sqm)}
                          className="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100"
                          readOnly
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Total Market Value</label>
                        <input
                          type="text"
                          value={formatCurrency(landCalculations.market_value)}
                          className="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100"
                          readOnly
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Assessment Level</label>
                        <input
                          type="text"
                          value={`${landCalculations.assessment_level}%`}
                          className="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100"
                          readOnly
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Assessed Value</label>
                        <input
                          type="text"
                          value={formatCurrency(landCalculations.assessed_value)}
                          className="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100"
                          readOnly
                        />
                      </div>
                    </div>
                  </div>

                  {/* Building Assessment Section */}
                  {registration.has_building === 'yes' && (
                    <div className="bg-gradient-to-br from-green-50 to-emerald-50 p-5 rounded-xl border border-green-100">
                      <h4 className="font-semibold text-green-800 mb-4 text-lg flex items-center">
                        <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        Building Assessment
                      </h4>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">Construction Type *</label>
                          <select
                            value={assessmentForm.construction_type}
                            onChange={(e) => setAssessmentForm({...assessmentForm, construction_type: e.target.value})}
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition"
                            required
                            disabled={!assessmentForm.land_property_type}
                          >
                            <option value="">Select Construction Type</option>
                            {getFilteredConstructionTypes().map((materialType, index) => (
                              <option key={index} value={materialType}>
                                {materialType}
                              </option>
                            ))}
                          </select>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">Floor Area (sqm) *</label>
                          <input
                            type="number"
                            step="0.01"
                            min="0"
                            value={assessmentForm.floor_area_sqm}
                            onChange={(e) => setAssessmentForm({...assessmentForm, floor_area_sqm: e.target.value})}
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition"
                            placeholder="Enter floor area"
                            required
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">Year Built</label>
                          <input
                            type="number"
                            min="1900"
                            max={new Date().getFullYear()}
                            value={assessmentForm.year_built}
                            onChange={(e) => setAssessmentForm({...assessmentForm, year_built: e.target.value})}
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">Market Value per sqm</label>
                          <input
                            type="text"
                            value={formatCurrency(buildingCalculations.market_value_per_sqm)}
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100"
                            readOnly
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">Total Market Value</label>
                          <input
                            type="text"
                            value={formatCurrency(buildingCalculations.market_value)}
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100"
                            readOnly
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">Building Age</label>
                          <input
                            type="text"
                            value={`${buildingCalculations.building_age} years`}
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100"
                            readOnly
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">Depreciation</label>
                          <input
                            type="text"
                            value={`${buildingCalculations.depreciation_percent.toFixed(2)}%`}
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100"
                            readOnly
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">Depreciated Value</label>
                          <input
                            type="text"
                            value={formatCurrency(buildingCalculations.depreciated_value)}
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100"
                            readOnly
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">Assessment Level</label>
                          <input
                            type="text"
                            value={buildingCalculations.assessment_level ? `${buildingCalculations.assessment_level}%` : 'N/A'}
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100"
                            readOnly
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">Assessed Value</label>
                          <input
                            type="text"
                            value={buildingCalculations.assessed_value ? formatCurrency(buildingCalculations.assessed_value) : 'N/A'}
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100"
                            readOnly
                          />
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Tax Calculation Summary */}
                  {(landCalculations.assessed_value > 0 || buildingCalculations.assessed_value > 0) && (
                    <div className="bg-gradient-to-br from-blue-50 to-indigo-50 p-5 rounded-xl border border-blue-100">
                      <h4 className="font-semibold text-blue-800 mb-4 text-lg">Tax Calculation Summary</h4>
                      
                      <div className="mb-6">
                        <h5 className="text-sm font-semibold text-gray-700 mb-3">Tax Rates Used:</h5>
                        <div className="grid grid-cols-3 gap-4">
                          <div className="bg-white/80 p-4 rounded-lg border border-blue-200 text-center">
                            <p className="text-sm text-blue-600 mb-1">Basic Tax</p>
                            <p className="text-lg font-bold text-blue-700">{taxCalculations.basic_tax_percent}%</p>
                          </div>
                          <div className="bg-white/80 p-4 rounded-lg border border-blue-200 text-center">
                            <p className="text-sm text-blue-600 mb-1">SEF Tax</p>
                            <p className="text-lg font-bold text-blue-700">{taxCalculations.sef_tax_percent}%</p>
                          </div>
                          <div className="bg-gradient-to-r from-green-50 to-emerald-50 p-4 rounded-lg border border-green-200 text-center">
                            <p className="text-sm text-green-600 mb-1">Total Tax Rate</p>
                            <p className="text-lg font-bold text-green-700">{taxCalculations.total_tax_rate}%</p>
                          </div>
                        </div>
                      </div>

                      {/* Total Tax Summary */}
                      <div className="bg-gradient-to-r from-emerald-50 to-green-50 p-5 rounded-lg border border-emerald-200">
                        <h5 className="text-sm font-semibold text-emerald-800 mb-3 text-center">Total Property Tax Summary</h5>
                        <div className="grid grid-cols-3 gap-4">
                          <div className="text-center">
                            <p className="text-sm text-blue-600">Total Basic Tax</p>
                            <p className="text-xl font-bold text-blue-700">{formatCurrency(taxCalculations.total_basic_tax)}</p>
                          </div>
                          <div className="text-center">
                            <p className="text-sm text-blue-600">Total SEF Tax</p>
                            <p className="text-xl font-bold text-blue-700">{formatCurrency(taxCalculations.total_sef_tax)}</p>
                          </div>
                          <div className="text-center">
                            <p className="text-sm text-emerald-600">Total Annual Tax</p>
                            <p className="text-2xl font-bold text-emerald-700">{formatCurrency(taxCalculations.total_annual_tax)}</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  )}

                  <div className="flex gap-4 pt-4">
                    <button
                      type="submit"
                      disabled={loading}
                      className="flex-1 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 disabled:from-green-400 disabled:to-emerald-400 text-white py-3.5 rounded-xl font-medium transition-all duration-300 shadow-lg hover:shadow-xl flex items-center justify-center"
                    >
                      {loading ? (
                        <>
                          <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                          Saving Assessment...
                        </>
                      ) : 'Save Assessment'}
                    </button>
                    <button
                      type="button"
                      onClick={() => setShowAssessmentForm(false)}
                      disabled={loading}
                      className="flex-1 bg-gradient-to-r from-gray-300 to-gray-400 hover:from-gray-400 hover:to-gray-500 disabled:from-gray-200 disabled:to-gray-300 text-gray-800 py-3.5 rounded-xl font-medium transition-all shadow"
                    >
                      Cancel
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        )}

        {/* Rejection Form Modal */}
        {showRejectForm && (
          <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50">
            <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-slideUp">
              <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                  <h3 className="text-xl font-semibold text-gray-900">Mark for Correction</h3>
                  <button onClick={() => setShowRejectForm(false)} className="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-1 rounded-full">
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
                <div className="space-y-5">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Correction Notes *</label>
                    <textarea 
                      value={rejectionNotes} 
                      onChange={(e) => setRejectionNotes(e.target.value)} 
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition h-32" 
                      placeholder="Explain what needs to be corrected..." 
                      required 
                    />
                  </div>
                  <div className="flex gap-3 pt-2">
                    <button 
                      onClick={handleReject} 
                      disabled={loading}
                      className="flex-1 bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 disabled:from-red-400 disabled:to-pink-400 text-white py-3.5 rounded-xl font-medium transition-all flex items-center justify-center shadow-lg hover:shadow-xl"
                    >
                      {loading ? (
                        <>
                          <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                          Processing...
                        </>
                      ) : 'Mark Needs Correction'}
                    </button>
                    <button 
                      onClick={() => setShowRejectForm(false)} 
                      disabled={loading}
                      className="flex-1 bg-gradient-to-r from-gray-300 to-gray-400 hover:from-gray-400 hover:to-gray-500 disabled:from-gray-200 disabled:to-gray-300 text-gray-800 py-3.5 rounded-xl font-medium transition-all shadow"
                    >
                      Cancel
                    </button>
                  </div>
                  <p className="text-sm text-red-600 bg-red-50 p-3 rounded-lg">
                    ⚠️ This will change status to "needs_correction" and notify the citizen.
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}

      </div>
      
      {/* Add CSS animations */}
      <style jsx>{`
        @keyframes fadeIn {
          from { opacity: 0; }
          to { opacity: 1; }
        }
        @keyframes slideUp {
          from { transform: translateY(20px); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
        }
        .animate-fadeIn {
          animation: fadeIn 0.3s ease-out;
        }
        .animate-slideUp {
          animation: slideUp 0.3s ease-out;
        }
      `}</style>
    </div>
  );
}