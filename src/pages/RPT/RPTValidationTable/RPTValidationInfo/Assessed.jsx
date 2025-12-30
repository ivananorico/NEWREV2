import React, { useState, useEffect } from "react";

export default function Assessed({ registration, documents, fetchData, formatDate, formatCurrency, getDocumentTypeName, navigate }) {
  // State declarations
  const [loading, setLoading] = useState(false);
  const [showAssessmentForm, setShowAssessmentForm] = useState(false);
  const [showRejectForm, setShowRejectForm] = useState(false);
  const [showDocumentModal, setShowDocumentModal] = useState(false);
  const [selectedDocument, setSelectedDocument] = useState(null);
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

  // Get API Base URL - FIXED for both localhost and domain
  const getApiBaseUrl = () => {
    // Use environment variable if available
    const envApiUrl = import.meta.env.VITE_API_URL;
    if (envApiUrl) {
      return `${envApiUrl}/RPT/RPTValidationTable`;
    }
    
    // Fallback to automatic detection
    const isLocalhost = window.location.hostname === "localhost" || 
                        window.location.hostname === "127.0.0.1";
    
    if (isLocalhost) {
      return "http://localhost/revenue2/backend/RPT/RPTValidationTable";
    }
    return "https://revenuetreasury.goserveph.com/backend/RPT/RPTValidationTable";
  };

  // Get Document Base URL - FIXED for both localhost and domain
  const getDocumentBaseUrl = () => {
    // Use environment variable if available
    const envApiUrl = import.meta.env.VITE_API_URL;
    if (envApiUrl) {
      return envApiUrl.replace('/backend', '');
    }
    
    // Fallback to automatic detection
    const isLocalhost = window.location.hostname === "localhost" || 
                        window.location.hostname === "127.0.0.1";
    
    if (isLocalhost) {
      return "http://localhost/revenue2";
    }
    return "https://revenuetreasury.goserveph.com";
  };

  // Function to get document URL - FIXED
  const getDocumentUrl = (filePath) => {
    const baseUrl = getDocumentBaseUrl();
    
    // Clean up file path (remove any existing domain or double slashes)
    let cleanPath = filePath.trim();
    
    // Remove any http:// or https:// prefixes
    cleanPath = cleanPath.replace(/^(http:\/\/|https:\/\/)[^\/]+\//, '');
    
    // Remove leading slashes
    cleanPath = cleanPath.replace(/^\/+/, '');
    
    // For localhost, make sure path starts correctly
    if (cleanPath.startsWith('revenue2/')) {
      cleanPath = cleanPath.replace('revenue2/', '');
    }
    
    return `${baseUrl}/${cleanPath}`;
  };

  // Function to view document in modal
  const viewDocument = (document) => {
    setSelectedDocument(document);
    setShowDocumentModal(true);
  };

  // Function to download document
  const downloadDocument = (document) => {
    const fullUrl = getDocumentUrl(document.file_path);
    window.open(fullUrl, '_blank');
  };

  // Get file extension
  const getFileExtension = (filename) => {
    return filename.split('.').pop().toLowerCase();
  };

  // Check if file is image
  const isImageFile = (filename) => {
    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    const ext = getFileExtension(filename);
    return imageExtensions.includes(ext);
  };

  // Check if file is PDF
  const isPdfFile = (filename) => {
    return getFileExtension(filename) === 'pdf';
  };

  // Function to get document icon
  const getDocumentIcon = (filename) => {
    if (isImageFile(filename)) return '🖼️';
    if (isPdfFile(filename)) return '📕';
    return '📄';
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
        console.log(`Fetching ${key} config from: ${url}`);
        
        const response = await fetch(url, {
          headers: { 'Accept': 'application/json' }
        });
        
        if (!response.ok) {
          console.warn(`Failed to fetch ${key} config: ${response.status}`);
          return [];
        }
        
        const data = await response.json();
        console.log(`${key} config response:`, data);
        
        let configData = extractDataFromResponse(data);
        
        if (dataKey && configData && configData[dataKey]) {
          configData = configData[dataKey];
        }
        
        if (Array.isArray(configData)) {
          const activeItems = configData.filter(item => item.status === 'active');
          setter(activeItems);
          console.log(`✅ ${key} config loaded:`, activeItems.length, "items");
          return activeItems;
        } else {
          console.warn(`${key} config data is not an array:`, configData);
          return [];
        }
      } catch (err) {
        console.error(`Error fetching ${key} config:`, err);
        return [];
      }
    });

    await Promise.all(promises);
    console.log("✅ All configurations loaded");
  };

  // Fetch assessment data
  const fetchAssessmentData = async () => {
    const API_BASE = getApiBaseUrl();
    
    try {
      const url = `${API_BASE}/get_assessment_data.php?id=${registration.id}`;
      console.log(`Fetching assessment data from: ${url}`);
      
      const response = await fetch(url, {
        headers: { 'Accept': 'application/json' }
      });
      
      if (!response.ok) {
        console.log("No assessment data found or server error:", response.status);
        return;
      }
      
      const data = await response.json();
      console.log("Assessment data response:", data);
      
      let assessmentData = extractDataFromResponse(data);
      
      if (assessmentData) {
        // Based on your database, land assessment is in land_properties table
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
        
        console.log("✅ Assessment data loaded");
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
  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-6">
      <div className="max-w-6xl mx-auto px-4">
        {/* Header */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-6">
          <div className="flex items-center justify-between">
            <div>
              <button
                onClick={() => navigate(-1)}
                className="text-gray-600 hover:text-blue-600 mb-4 flex items-center"
              >
                ← Back
              </button>
              <h1 className="text-2xl font-bold text-gray-900">Assessed Property</h1>
              <p className="text-gray-600">Reference: {registration.reference_number}</p>
            </div>
            <span className="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-semibold">
              ASSESSED
            </span>
          </div>
        </div>

        {/* Documents Section */}
        {documents.length > 0 && (
          <div className="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 className="text-xl font-bold text-gray-900 mb-4">Uploaded Documents ({documents.length})</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {documents.map((doc, index) => (
                <div key={index} className="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition">
                  <div className="flex items-start mb-2">
                    <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                      <span className="text-2xl">{getDocumentIcon(doc.file_name)}</span>
                    </div>
                    <div className="flex-1">
                      <h3 className="font-semibold text-gray-900">{getDocumentTypeName(doc.document_type)}</h3>
                      <p className="text-sm text-gray-600 truncate">{doc.file_name}</p>
                      <p className="text-xs text-gray-500 mt-1">
                        {formatDate(doc.created_at)}
                      </p>
                    </div>
                  </div>
                  <div className="flex space-x-2 mt-2">
                    <button
                      onClick={() => viewDocument(doc)}
                      className="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-sm py-2 rounded transition"
                    >
                      View
                    </button>
                    <button
                      onClick={() => downloadDocument(doc)}
                      className="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm py-2 rounded transition"
                    >
                      Download
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Registration Details */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h2 className="text-xl font-bold text-gray-900 mb-4">Registration Details</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 className="font-semibold text-gray-700 mb-2">Property Information</h3>
              <div className="space-y-2">
                <p><span className="font-medium">Type:</span> {registration.property_type}</p>
                <p><span className="font-medium">Address:</span> {registration.lot_location || registration.location_address}</p>
                <p><span className="font-medium">Barangay:</span> {registration.barangay}</p>
                <p><span className="font-medium">City:</span> {registration.city}</p>
              </div>
            </div>
            <div>
              <h3 className="font-semibold text-gray-700 mb-2">Owner Information</h3>
              <div className="space-y-2">
                <p><span className="font-medium">Name:</span> {registration.owner_name}</p>
                <p><span className="font-medium">Address:</span> {registration.owner_address}</p>
                <p><span className="font-medium">Contact:</span> {registration.contact_number || registration.phone}</p>
                <p><span className="font-medium">Email:</span> {registration.email_address || registration.email}</p>
              </div>
            </div>
          </div>
          <div className="mt-4 pt-4 border-t border-gray-200">
            <p><span className="font-medium">Date Registered:</span> {formatDate(registration.created_at)}</p>
            <p><span className="font-medium">Has Building:</span> {registration.has_building === 'yes' ? 'Yes' : 'No'}</p>
          </div>
        </div>

        {/* Current Assessment Display */}
        {(landAssessment || buildingAssessment) && (
          <div className="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 className="text-xl font-bold text-gray-900 mb-4">Current Assessment</h2>
            
            {landAssessment && (
              <div className="mb-6">
                <h3 className="text-lg font-semibold text-gray-800 mb-3">Land Assessment</h3>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div className="bg-gray-50 p-3 rounded">
                    <p className="text-sm text-gray-600">Property Type</p>
                    <p className="font-semibold">{landAssessment.property_type}</p>
                  </div>
                  <div className="bg-gray-50 p-3 rounded">
                    <p className="text-sm text-gray-600">Area (sqm)</p>
                    <p className="font-semibold">{landAssessment.land_area_sqm}</p>
                  </div>
                  <div className="bg-gray-50 p-3 rounded">
                    <p className="text-sm text-gray-600">Market Value</p>
                    <p className="font-semibold">{formatCurrency(parseFloat(landAssessment.land_market_value || 0))}</p>
                  </div>
                  <div className="bg-gray-50 p-3 rounded">
                    <p className="text-sm text-gray-600">Assessed Value</p>
                    <p className="font-semibold">{formatCurrency(parseFloat(landAssessment.land_assessed_value || 0))}</p>
                  </div>
                </div>
              </div>
            )}
            
            {buildingAssessment && (
              <div>
                <h3 className="text-lg font-semibold text-gray-800 mb-3">Building Assessment</h3>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div className="bg-gray-50 p-3 rounded">
                    <p className="text-sm text-gray-600">Construction Type</p>
                    <p className="font-semibold">{buildingAssessment.construction_type}</p>
                  </div>
                  <div className="bg-gray-50 p-3 rounded">
                    <p className="text-sm text-gray-600">Floor Area (sqm)</p>
                    <p className="font-semibold">{buildingAssessment.floor_area_sqm}</p>
                  </div>
                  <div className="bg-gray-50 p-3 rounded">
                    <p className="text-sm text-gray-600">Year Built</p>
                    <p className="font-semibold">{buildingAssessment.year_built}</p>
                  </div>
                  <div className="bg-gray-50 p-3 rounded">
                    <p className="text-sm text-gray-600">Assessed Value</p>
                    <p className="font-semibold">{formatCurrency(parseFloat(buildingAssessment.building_assessed_value || 0))}</p>
                  </div>
                </div>
              </div>
            )}
          </div>
        )}

        {/* Action Buttons */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h2 className="text-lg font-bold text-gray-900 mb-4">Admin Actions</h2>
          <div className="flex flex-wrap gap-4">
            <button
              onClick={() => setShowAssessmentForm(true)}
              disabled={loading}
              className="bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white px-6 py-3 rounded-lg flex items-center"
            >
              <span className="mr-2">📊</span>
              {landAssessment ? 'Edit Assessment' : 'Input Assessment Data'}
            </button>
            
            <button
              onClick={handleApprove}
              disabled={loading || !canApproveProperty()}
              className="bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white px-6 py-3 rounded-lg flex items-center"
            >
              <span className="mr-2">✅</span>
              Approve Property
            </button>
            
            <button
              onClick={() => setShowRejectForm(true)}
              disabled={loading}
              className="bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white px-6 py-3 rounded-lg flex items-center"
            >
              <span className="mr-2">❌</span>
              Mark Needs Correction
            </button>
          </div>
          
          {!canApproveProperty() && (
            <div className="mt-4 p-3 bg-red-50 border border-red-200 rounded">
              <p className="text-sm text-red-700">
                ⚠️ Complete all required assessments before approving.
                {!landAssessment && " Land assessment missing."}
                {registration.has_building === 'yes' && !buildingAssessment && " Building assessment missing."}
              </p>
            </div>
          )}
        </div>

        {/* Document Viewer Modal */}
        {showDocumentModal && selectedDocument && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div className="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
              <div className="flex justify-between items-center border-b border-gray-200 p-6">
                <div>
                  <h3 className="text-xl font-bold text-gray-900">{getDocumentTypeName(selectedDocument.document_type)}</h3>
                  <p className="text-sm text-gray-600 mt-1">{selectedDocument.file_name}</p>
                  <div className="flex items-center mt-2 text-sm text-gray-500">
                    <span className="mr-4">Type: {selectedDocument.file_type}</span>
                    <span>Size: {(selectedDocument.file_size / 1024).toFixed(2)} KB</span>
                  </div>
                </div>
                <div className="flex space-x-2">
                  <button
                    onClick={() => downloadDocument(selectedDocument)}
                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2"
                  >
                    <span>📥</span>
                    <span>Download</span>
                  </button>
                  <button
                    onClick={() => setShowDocumentModal(false)}
                    className="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg"
                  >
                    Close
                  </button>
                </div>
              </div>
              
              <div className="p-6 overflow-auto" style={{ maxHeight: 'calc(90vh - 120px)' }}>
                {isImageFile(selectedDocument.file_name) ? (
                  <div className="text-center">
                    <img 
                      src={getDocumentUrl(selectedDocument.file_path)}
                      alt={selectedDocument.file_name}
                      className="max-w-full h-auto mx-auto rounded-lg shadow-lg"
                      onError={(e) => {
                        e.target.onerror = null;
                        e.target.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300"><rect width="400" height="300" fill="%23f5f5f5"/><text x="200" y="150" text-anchor="middle" font-family="Arial" font-size="16" fill="%23666">Image not available</text></svg>';
                      }}
                    />
                    <p className="text-sm text-gray-500 mt-4">
                      Image preview. Click Download for original quality.
                    </p>
                  </div>
                ) : isPdfFile(selectedDocument.file_name) ? (
                  <div className="text-center">
                    <div className="mb-6">
                      <div className="text-6xl text-red-500 mb-4">📕</div>
                      <p className="text-gray-600">PDF files cannot be previewed in the browser.</p>
                      <p className="text-sm text-gray-500 mt-2">
                        Please download the file to view it.
                      </p>
                    </div>
                    <button
                      onClick={() => downloadDocument(selectedDocument)}
                      className="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg flex items-center space-x-2 mx-auto"
                    >
                      <span>📥</span>
                      <span>Download PDF</span>
                    </button>
                  </div>
                ) : (
                  <div className="text-center">
                    <div className="mb-6">
                      <div className="text-6xl text-gray-400 mb-4">📄</div>
                      <p className="text-gray-600">This file type cannot be previewed.</p>
                      <p className="text-sm text-gray-500 mt-2">
                        File type: {selectedDocument.file_type}
                      </p>
                    </div>
                    <button
                      onClick={() => downloadDocument(selectedDocument)}
                      className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg flex items-center space-x-2 mx-auto"
                    >
                      <span>📥</span>
                      <span>Download File</span>
                    </button>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Assessment Form Modal with Automated Calculations */}
        {showAssessmentForm && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div className="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
              <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                  <h3 className="text-lg font-semibold text-gray-900">Property Assessment with Automated Calculations</h3>
                  <button
                    onClick={() => setShowAssessmentForm(false)}
                    className="text-gray-400 hover:text-gray-600"
                  >
                    ✕
                  </button>
                </div>
                
                {buildingWarning && (
                  <div className="mb-4 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div className="flex items-start">
                      <span className="text-yellow-500 mr-2">⚠️</span>
                      <p className="text-yellow-700 text-sm">{buildingWarning}</p>
                    </div>
                  </div>
                )}

                <form onSubmit={handleAssessmentSubmit} className="space-y-6">
                  {/* Land Assessment Section */}
                  <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="font-semibold text-gray-800 mb-4">Land Assessment</h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Property Type *</label>
                        <select
                          value={assessmentForm.land_property_type}
                          onChange={(e) => setAssessmentForm({...assessmentForm, land_property_type: e.target.value})}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                          required
                        >
                          <option value="">Select Property Type</option>
                          {landConfigs.map((config) => (
                            <option key={config.id} value={config.classification}>
                              {config.classification}
                            </option>
                          ))}
                        </select>
                        <div className="text-xs text-gray-500 mt-1">
                          Available: {landConfigs.length} configurations
                        </div>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Land Area (sqm) *</label>
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          value={assessmentForm.land_area_sqm}
                          onChange={(e) => setAssessmentForm({...assessmentForm, land_area_sqm: e.target.value})}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                          placeholder="Enter land area"
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Market Value per sqm</label>
                        <input
                          type="text"
                          value={formatCurrency(landCalculations.market_value_per_sqm)}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                          readOnly
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Total Market Value</label>
                        <input
                          type="text"
                          value={formatCurrency(landCalculations.market_value)}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                          readOnly
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Assessment Level</label>
                        <input
                          type="text"
                          value={`${landCalculations.assessment_level}%`}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                          readOnly
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Assessed Value</label>
                        <input
                          type="text"
                          value={formatCurrency(landCalculations.assessed_value)}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                          readOnly
                        />
                      </div>
                    </div>
                  </div>

                  {/* Building Assessment Section */}
                  {registration.has_building === 'yes' && (
                    <div className="bg-gray-50 p-4 rounded-lg">
                      <h4 className="font-semibold text-gray-800 mb-4">Building Assessment</h4>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">Construction Type *</label>
                          <select
                            value={assessmentForm.construction_type}
                            onChange={(e) => setAssessmentForm({...assessmentForm, construction_type: e.target.value})}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
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
                          {!assessmentForm.land_property_type && (
                            <p className="text-xs text-red-500 mt-1">Select Land Property Type first</p>
                          )}
                          <div className="text-xs text-gray-500 mt-1">
                            Available: {getFilteredConstructionTypes().length} types for {assessmentForm.land_property_type}
                          </div>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">Floor Area (sqm) *</label>
                          <input
                            type="number"
                            step="0.01"
                            min="0"
                            value={assessmentForm.floor_area_sqm}
                            onChange={(e) => setAssessmentForm({...assessmentForm, floor_area_sqm: e.target.value})}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter floor area"
                            required
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">Year Built</label>
                          <input
                            type="number"
                            min="1900"
                            max={new Date().getFullYear()}
                            value={assessmentForm.year_built}
                            onChange={(e) => setAssessmentForm({...assessmentForm, year_built: e.target.value})}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">Market Value per sqm</label>
                          <input
                            type="text"
                            value={formatCurrency(buildingCalculations.market_value_per_sqm)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                            readOnly
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">Total Market Value</label>
                          <input
                            type="text"
                            value={formatCurrency(buildingCalculations.market_value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                            readOnly
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">Building Age</label>
                          <input
                            type="text"
                            value={`${buildingCalculations.building_age} years`}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                            readOnly
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">Depreciation</label>
                          <input
                            type="text"
                            value={`${buildingCalculations.depreciation_percent.toFixed(2)}%`}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                            readOnly
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">Depreciated Value</label>
                          <input
                            type="text"
                            value={formatCurrency(buildingCalculations.depreciated_value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                            readOnly
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">Assessment Level</label>
                          <input
                            type="text"
                            value={buildingCalculations.assessment_level ? `${buildingCalculations.assessment_level}%` : 'N/A'}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                            readOnly
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">Assessed Value</label>
                          <input
                            type="text"
                            value={buildingCalculations.assessed_value ? formatCurrency(buildingCalculations.assessed_value) : 'N/A'}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                            readOnly
                          />
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Tax Calculation Summary */}
                  <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="font-semibold text-gray-800 mb-4">Tax Calculation Summary</h4>
                    
                    <div className="mb-6">
                      <h5 className="text-sm font-semibold text-gray-700 mb-2">Tax Rates Used:</h5>
                      <div className="grid grid-cols-3 gap-4">
                        <div className="bg-blue-50 p-3 rounded border border-blue-100">
                          <p className="text-sm text-blue-600">Basic Tax</p>
                          <p className="text-lg font-semibold text-blue-700">{taxCalculations.basic_tax_percent}%</p>
                        </div>
                        <div className="bg-blue-50 p-3 rounded border border-blue-100">
                          <p className="text-sm text-blue-600">SEF Tax</p>
                          <p className="text-lg font-semibold text-blue-700">{taxCalculations.sef_tax_percent}%</p>
                        </div>
                        <div className="bg-green-50 p-3 rounded border border-green-100">
                          <p className="text-sm text-green-600">Total Tax Rate</p>
                          <p className="text-lg font-semibold text-green-700">{taxCalculations.total_tax_rate}%</p>
                        </div>
                      </div>
                    </div>

                    {/* Land Tax Breakdown */}
                    <div className="mb-4">
                      <h5 className="text-sm font-semibold text-gray-700 mb-2">Land Tax Breakdown:</h5>
                      <div className="grid grid-cols-3 gap-4">
                        <div className="bg-gray-100 p-3 rounded">
                          <p className="text-sm text-gray-600">Basic Tax</p>
                          <p className="text-lg font-semibold text-gray-900">{formatCurrency(taxCalculations.land_basic_tax)}</p>
                        </div>
                        <div className="bg-gray-100 p-3 rounded">
                          <p className="text-sm text-gray-600">SEF Tax</p>
                          <p className="text-lg font-semibold text-gray-900">{formatCurrency(taxCalculations.land_sef_tax)}</p>
                        </div>
                        <div className="bg-green-50 p-3 rounded border border-green-100">
                          <p className="text-sm text-green-600">Land Annual Tax</p>
                          <p className="text-lg font-semibold text-green-700">{formatCurrency(taxCalculations.land_annual_tax)}</p>
                        </div>
                      </div>
                    </div>

                    {/* Building Tax Breakdown */}
                    {registration.has_building === 'yes' && buildingCalculations.assessed_value > 0 && (
                      <div className="mb-4">
                        <h5 className="text-sm font-semibold text-gray-700 mb-2">Building Tax Breakdown:</h5>
                        <div className="grid grid-cols-3 gap-4">
                          <div className="bg-gray-100 p-3 rounded">
                            <p className="text-sm text-gray-600">Basic Tax</p>
                            <p className="text-lg font-semibold text-gray-900">{formatCurrency(taxCalculations.building_basic_tax)}</p>
                          </div>
                          <div className="bg-gray-100 p-3 rounded">
                            <p className="text-sm text-gray-600">SEF Tax</p>
                            <p className="text-lg font-semibold text-gray-900">{formatCurrency(taxCalculations.building_sef_tax)}</p>
                          </div>
                          <div className="bg-green-50 p-3 rounded border border-green-100">
                            <p className="text-sm text-green-600">Building Annual Tax</p>
                            <p className="text-lg font-semibold text-green-700">{formatCurrency(taxCalculations.building_annual_tax)}</p>
                          </div>
                        </div>
                      </div>
                    )}

                    {/* Total Tax Summary */}
                    <div className="bg-blue-50 p-4 rounded-lg border border-blue-100">
                      <h5 className="text-sm font-semibold text-blue-700 mb-2">Total Property Tax Summary:</h5>
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
                          <p className="text-sm text-green-600">Total Annual Tax</p>
                          <p className="text-2xl font-bold text-green-700">{formatCurrency(taxCalculations.total_annual_tax)}</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="flex gap-3 pt-4">
                    <button
                      type="submit"
                      disabled={loading}
                      className="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white py-3 rounded-lg"
                    >
                      {loading ? 'Saving...' : 'Save Assessment'}
                    </button>
                    <button
                      type="button"
                      onClick={() => setShowAssessmentForm(false)}
                      disabled={loading}
                      className="flex-1 bg-gray-300 hover:bg-gray-400 disabled:bg-gray-200 py-3 rounded-lg"
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
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div className="bg-white rounded-xl shadow-2xl max-w-md w-full">
              <div className="p-6">
                <div className="flex justify-between items-center mb-4">
                  <h3 className="text-lg font-semibold text-gray-900">Mark as Needs Correction</h3>
                  <button
                    onClick={() => setShowRejectForm(false)}
                    className="text-gray-400 hover:text-gray-600"
                  >
                    ✕
                  </button>
                </div>
                
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Correction Notes *</label>
                    <textarea
                      value={rejectionNotes}
                      onChange={(e) => setRejectionNotes(e.target.value)}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md h-32"
                      placeholder="Explain what needs to be corrected..."
                      required
                    />
                  </div>
                  
                  <div className="flex gap-3 pt-4">
                    <button
                      onClick={handleReject}
                      disabled={loading}
                      className="flex-1 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white py-2 rounded"
                    >
                      {loading ? 'Submitting...' : 'Mark Needs Correction'}
                    </button>
                    <button
                      onClick={() => setShowRejectForm(false)}
                      disabled={loading}
                      className="flex-1 bg-gray-300 hover:bg-gray-400 disabled:bg-gray-200 py-2 rounded"
                    >
                      Cancel
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </div> 
  );
}