import React, { useState, useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";

export default function RPTValidationInfo() {
  const { id } = useParams();
  const navigate = useNavigate();
  
  // Dynamic API configuration
  const isDevelopment = process.env.NODE_ENV === 'development';
  const API_BASE = process.env.REACT_APP_API_BASE 
    ? `${process.env.REACT_APP_API_BASE}/RPT/RPTValidationTable`
    : isDevelopment 
      ? "http://localhost/revenue/backend/RPT/RPTValidationTable"
      : "/backend/RPT/RPTValidationTable";

  // State declarations
  const [registration, setRegistration] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showInspectionForm, setShowInspectionForm] = useState(false);
  const [showAssessmentForm, setShowAssessmentForm] = useState(false);
  const [landConfigs, setLandConfigs] = useState([]);
  const [propertyConfigs, setPropertyConfigs] = useState([]);
  const [buildingAssessmentLevels, setBuildingAssessmentLevels] = useState([]);
  const [taxConfigs, setTaxConfigs] = useState([]);
  const [buildingWarning, setBuildingWarning] = useState("");

  // Assessment data states
  const [landAssessment, setLandAssessment] = useState(null);
  const [buildingAssessment, setBuildingAssessment] = useState(null);

  // Form states
  const [inspectionForm, setInspectionForm] = useState({
    scheduled_date: "",
    assessor_name: ""
  });

  const [assessmentForm, setAssessmentForm] = useState({
    // Land Properties
    land_tdn: "",
    land_property_type: "",
    land_area_sqm: "",
    land_market_value: "",
    land_assessed_value: "",
    land_assessment_level: "",
    
    // Building Properties
    building_tdn: "",
    construction_type: "",
    floor_area_sqm: "",
    year_built: new Date().getFullYear(),
    useful_life_years: 50,
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

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [debugInfo, setDebugInfo] = useState({});

  // Debug logging on mount
  useEffect(() => {
    console.log("🔧 Environment:", isDevelopment ? "Development" : "Production");
    console.log("🌐 API Base URL:", API_BASE);
    console.log("📌 Registration ID from URL:", id);
    console.log("🔗 Full path:", window.location.href);
    
    setDebugInfo({
      environment: isDevelopment ? "Development" : "Production",
      apiBase: API_BASE,
      id: id,
      timestamp: new Date().toISOString()
    });
  }, []);

  // Main data fetching effect
  useEffect(() => {
    const fetchAllData = async () => {
      if (!id || id === "undefined" || id === "null") {
        console.error("❌ Invalid ID provided:", id);
        setError("Invalid registration ID. Please go back and select a valid registration.");
        setLoading(false);
        return;
      }

      try {
        setLoading(true);
        setError(null);
        
        console.log("🚀 Starting data fetch for ID:", id);
        
        // Fetch data in parallel for better performance
        await Promise.all([
          fetchRegistrationDetails(),
          fetchConfigurations(),
          fetchAssessmentData()
        ]);
        
        console.log("✅ All data fetched successfully");
      } catch (err) {
        console.error("❌ Error in fetchAllData:", err);
        setError(`Failed to load data: ${err.message}`);
      } finally {
        setLoading(false);
      }
    };

    fetchAllData();
  }, [id]);

  // Tax calculation effect
  useEffect(() => {
    if (landCalculations.assessed_value > 0 || buildingCalculations.assessed_value > 0) {
      calculateAnnualTaxes();
    }
  }, [landCalculations.assessed_value, buildingCalculations.assessed_value, taxConfigs]);

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

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
      return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    } catch (err) {
      return dateString;
    }
  };

  const formatCurrency = (amount) => {
    if (!amount || isNaN(amount)) return '₱0.00';
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);
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

  // Data fetching functions
  const fetchRegistrationDetails = async () => {
    try {
      console.log(`📋 Fetching registration details from: ${API_BASE}/get_registration_details.php?id=${id}`);
      
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 10000);
      
      const response = await fetch(`${API_BASE}/get_registration_details.php?id=${id}`, {
        signal: controller.signal,
        credentials: isDevelopment ? 'omit' : 'include',
        headers: {
          'Accept': 'application/json',
          'Cache-Control': 'no-cache',
          'Pragma': 'no-cache'
        }
      });
      
      clearTimeout(timeoutId);
      
      if (!response.ok) {
        const errorText = await response.text();
        console.error("❌ Server response error:", response.status, errorText);
        throw new Error(`Server error: ${response.status} ${response.statusText}`);
      }
      
      const text = await response.text();
      console.log("📄 Raw response:", text.substring(0, 500) + (text.length > 500 ? '...' : ''));
      
      let data;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        console.error("❌ JSON parse error:", parseError);
        console.error("Failed text:", text);
        throw new Error("Invalid response from server. Please check backend configuration.");
      }
      
      console.log("✅ Parsed data status:", data.status);

      if (data.status === "success") {
        const completeRegistration = {
          ...data.registration,
          province: data.registration.province || 'N/A',
          property_type: data.registration.property_type || 'Residential',
          last_updated: data.registration.last_updated || data.registration.date_registered,
          remarks: data.registration.remarks || 'No remarks'
        };
        
        setRegistration(completeRegistration);
        console.log("✅ Registration loaded successfully");
      } else {
        throw new Error(data.message || "Failed to fetch registration details");
      }
    } catch (err) {
      console.error("❌ Error in fetchRegistrationDetails:", err);
      throw err;
    }
  };

  const fetchConfigurations = async () => {
    const endpoints = [
      { key: 'land', url: 'get_land_configurations.php', setter: setLandConfigs, dataKey: 'configurations' },
      { key: 'property', url: 'get_property_configurations.php', setter: setPropertyConfigs, dataKey: 'configurations' },
      { key: 'assessment', url: 'get_building_assessment_levels.php', setter: setBuildingAssessmentLevels, dataKey: 'assessment_levels' },
      { key: 'tax', url: 'get_tax_configurations.php', setter: setTaxConfigs, dataKey: 'tax_configurations' }
    ];

    const promises = endpoints.map(async ({ key, url, setter, dataKey }) => {
      try {
        console.log(`⚙️ Fetching ${key} config from: ${API_BASE}/${url}`);
        
        const response = await fetch(`${API_BASE}/${url}`, {
          credentials: isDevelopment ? 'omit' : 'include',
          headers: { 'Accept': 'application/json' }
        });
        
        if (!response.ok) {
          console.warn(`⚠️ Failed to fetch ${key} config: ${response.status}`);
          return null;
        }
        
        const data = await response.json();
        
        if (data.status === "success") {
          const activeItems = (data[dataKey] || []).filter(item => item.status === 'active');
          setter(activeItems);
          console.log(`✅ ${key} config loaded:`, activeItems.length, "items");
          return activeItems;
        }
        return null;
      } catch (err) {
        console.error(`❌ Error fetching ${key} config:`, err);
        return null;
      }
    });

    await Promise.all(promises);
    console.log("✅ All configurations loaded");
  };

  const fetchAssessmentData = async () => {
    try {
      console.log(`📊 Fetching assessment data from: ${API_BASE}/get_assessment_data.php?id=${id}`);
      
      const response = await fetch(`${API_BASE}/get_assessment_data.php?id=${id}`, {
        credentials: isDevelopment ? 'omit' : 'include',
        headers: { 'Accept': 'application/json' }
      });
      
      if (!response.ok) {
        console.log("ℹ️ No assessment data found or server error:", response.status);
        return;
      }
      
      const data = await response.json();
      
      if (data.status === "success") {
        setLandAssessment(data.land_assessment || null);
        setBuildingAssessment(data.building_assessment || null);
        
        if (data.land_assessment) {
          setAssessmentForm(prev => ({
            ...prev,
            land_tdn: data.land_assessment.tdn || "",
            land_property_type: data.land_assessment.property_type || "",
            land_area_sqm: data.land_assessment.land_area_sqm || "",
            land_market_value: data.land_assessment.land_market_value || "",
            land_assessed_value: data.land_assessment.land_assessed_value || "",
            land_assessment_level: data.land_assessment.assessment_level || ""
          }));
        }
        
        if (data.building_assessment) {
          setAssessmentForm(prev => ({
            ...prev,
            building_tdn: data.building_assessment.tdn || "",
            construction_type: data.building_assessment.construction_type || "",
            floor_area_sqm: data.building_assessment.floor_area_sqm || "",
            year_built: data.building_assessment.year_built || new Date().getFullYear(),
            building_market_value: data.building_assessment.building_market_value || "",
            building_depreciated_value: data.building_assessment.building_depreciated_value || "",
            depreciation_percent: data.building_assessment.depreciation_percent || "",
            building_assessed_value: data.building_assessment.building_assessed_value || "",
            building_assessment_level: data.building_assessment.assessment_level || ""
          }));
        }
        
        console.log("✅ Assessment data loaded");
      }
    } catch (err) {
      console.error("❌ Error fetching assessment data:", err);
    }
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

  // Action handlers
  const handleInspectionSubmit = async (e) => {
    e.preventDefault();
    try {
      console.log("📅 Submitting inspection form");
      
      const response = await fetch(`${API_BASE}/schedule_inspection.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: isDevelopment ? 'omit' : 'include',
        body: JSON.stringify({
          registration_id: id,
          ...inspectionForm
        })
      });

      const data = await response.json();
      console.log("📅 Inspection response:", data);

      if (data.status === "success") {
        alert("✅ Inspection scheduled successfully!");
        setShowInspectionForm(false);
        setInspectionForm({ scheduled_date: "", assessor_name: "" });
        await updateRegistrationStatus('for_inspection');
      } else {
        throw new Error(data.message || "Failed to schedule inspection");
      }
    } catch (err) {
      alert(`❌ Error: ${err.message}`);
      console.error("Inspection submission error:", err);
    }
  };

  const handleAssessmentSubmit = async (e) => {
    e.preventDefault();
    setIsSubmitting(true);
    
    console.log("💾 Submitting assessment data");
    
    if (buildingWarning && registration.has_building === 'yes') {
      const proceed = window.confirm(
        "Building value is outside configured ranges. Proceed anyway?"
      );
      if (!proceed) {
        setIsSubmitting(false);
        return;
      }
    }

    try {
      const submissionData = {
        registration_id: id,
        land_property_type: assessmentForm.land_property_type,
        land_area_sqm: assessmentForm.land_area_sqm,
        land_market_value: assessmentForm.land_market_value,
        land_assessed_value: assessmentForm.land_assessed_value,
        land_assessment_level: assessmentForm.land_assessment_level,
        ...(registration.has_building === 'yes' && {
          construction_type: assessmentForm.construction_type,
          floor_area_sqm: assessmentForm.floor_area_sqm,
          year_built: assessmentForm.year_built,
          useful_life_years: assessmentForm.useful_life_years,
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
        credentials: isDevelopment ? 'omit' : 'include',
        body: JSON.stringify(submissionData)
      });

      const data = await response.json();
      console.log("📥 Assessment response:", data);

      if (data.status === "success") {
        alert(`✅ Assessment ${data.action || 'saved'} successfully!`);
        await fetchAssessmentData();
        
        const shouldClose = window.confirm("Close assessment form?");
        if (shouldClose) {
          setShowAssessmentForm(false);
        }
      } else {
        throw new Error(data.message || "Failed to assess property");
      }
    } catch (err) {
      alert(`❌ Error: ${err.message}`);
      console.error("Assessment submission error:", err);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleApprove = async () => {
    if (window.confirm("Approve this property assessment?\n\nThis will:\n• Generate TDNs\n• Create tax records\n• Generate quarterly bills")) {
      try {
        console.log("✅ Approving property");
        
        const response = await fetch(`${API_BASE}/approve_property.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          credentials: isDevelopment ? 'omit' : 'include',
          body: JSON.stringify({
            registration_id: id,
            land_annual_tax: taxCalculations.land_annual_tax,
            building_annual_tax: taxCalculations.building_annual_tax,
            total_annual_tax: taxCalculations.total_annual_tax
          })
        });

        const data = await response.json();
        console.log("✅ Approval response:", data);

        if (data.status === "success") {
          alert(`✅ Property approved!\n\nLand TDN: ${data.tdns?.land_tdn || 'N/A'}\nBuilding TDN: ${data.tdns?.building_tdn || 'N/A'}`);
          await fetchRegistrationDetails();
          await fetchAssessmentData();
        } else {
          throw new Error(data.message || "Failed to approve property");
        }
      } catch (err) {
        alert(`❌ Error: ${err.message}`);
        console.error("Approval error:", err);
      }
    }
  };

  const handleUpdateToAssessed = async () => {
    if (window.confirm("Mark this property as assessed?")) {
      try {
        const response = await fetch(`${API_BASE}/update_registration_status.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          credentials: isDevelopment ? 'omit' : 'include',
          body: JSON.stringify({
            registration_id: id,
            status: 'assessed'
          })
        });

        const data = await response.json();

        if (data.status === "success") {
          alert("✅ Property marked as assessed!");
          await fetchRegistrationDetails();
          setShowAssessmentForm(true);
        } else {
          throw new Error(data.message || "Failed to update status");
        }
      } catch (err) {
        alert(`❌ Error: ${err.message}`);
      }
    }
  };

  const updateRegistrationStatus = async (status) => {
    try {
      const response = await fetch(`${API_BASE}/update_registration_status.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: isDevelopment ? 'omit' : 'include',
        body: JSON.stringify({
          registration_id: id,
          status: status
        })
      });
      
      await response.json();
      await fetchRegistrationDetails();
    } catch (err) {
      console.error("Status update error:", err);
    }
  };

  const retryFetch = () => {
    setLoading(true);
    setError(null);
    fetchRegistrationDetails();
  };

  // Loading state
  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white rounded-lg shadow-lg p-8 max-w-md w-full mx-4">
          <div className="flex flex-col items-center space-y-4">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            <div className="text-center">
              <p className="text-gray-700 font-medium mb-2">Loading registration details...</p>
              <p className="text-sm text-gray-500">ID: {id}</p>
              {isDevelopment && (
                <p className="text-xs text-blue-500 mt-2">API: {API_BASE}</p>
              )}
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Error state
  if (error || !registration) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white rounded-lg shadow-lg p-8 max-w-md w-full mx-4">
          <div className="text-center">
            <div className="text-red-500 text-4xl mb-4">⚠️</div>
            <h2 className="text-xl font-bold text-gray-900 mb-2">
              {error ? "Error Loading Data" : "Registration Not Found"}
            </h2>
            <p className="text-gray-600 mb-4">
              {error || "The requested registration could not be found."}
            </p>
            
            {isDevelopment && (
              <div className="bg-gray-100 p-3 rounded mb-4 text-left">
                <p className="text-sm font-semibold mb-1">Debug Info:</p>
                <p className="text-xs">ID: {id}</p>
                <p className="text-xs">API: {API_BASE}</p>
                <p className="text-xs">Env: {isDevelopment ? "Development" : "Production"}</p>
              </div>
            )}
            
            <div className="space-x-4">
              <button
                onClick={() => navigate(-1)}
                className="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition-colors"
              >
                Go Back
              </button>
              <button
                onClick={retryFetch}
                className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors"
              >
                Try Again
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Main render
  return (
    <div className="min-h-screen bg-gray-50 py-8">
      {/* Environment Banner */}
      {isDevelopment && (
        <div className="fixed top-0 left-0 right-0 bg-yellow-500 text-black text-center py-2 text-sm font-bold z-50">
          🚧 DEVELOPMENT MODE | API: {API_BASE} | ID: {id}
        </div>
      )}

      <div className={`max-w-7xl mx-auto px-4 ${isDevelopment ? 'pt-10' : ''}`}>
        {/* Header Card */}
        <div className="bg-white rounded-xl shadow-lg border border-gray-200 p-6 mb-6">
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div className="flex-1">
              <div className="flex items-center space-x-4 mb-4">
                <button
                  onClick={() => navigate(-1)}
                  className="flex items-center space-x-2 text-gray-600 hover:text-gray-800 transition-colors"
                >
                  <span className="text-xl">←</span>
                  <span className="font-medium">Back to List</span>
                </button>
              </div>
              
              <div className="flex items-center space-x-4">
                <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                  <span className="text-blue-600 text-xl">🏠</span>
                </div>
                <div>
                  <h1 className="text-2xl font-bold text-gray-900">Property Registration Application</h1>
                  <p className="text-gray-600">
                    Reference: <span className="font-mono font-semibold">{registration.reference_number}</span>
                  </p>
                </div>
              </div>
            </div>

            <div className="mt-4 lg:mt-0">
              <span className={`inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold ${
                registration.status === 'pending' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
                registration.status === 'for_inspection' ? 'bg-blue-100 text-blue-800 border border-blue-200' :
                registration.status === 'assessed' ? 'bg-purple-100 text-purple-800 border border-purple-200' :
                'bg-green-100 text-green-800 border border-green-200'
              }`}>
                {registration.status.replace('_', ' ').toUpperCase()}
              </span>
            </div>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="bg-white rounded-xl shadow-lg border border-gray-200 p-6 mb-6">
          <h2 className="text-lg font-bold text-gray-900 mb-4">Admin Actions</h2>
          <div className="flex flex-wrap gap-4">
            {registration.status === 'pending' && (
              <button
                onClick={() => setShowInspectionForm(true)}
                className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors flex items-center space-x-2"
              >
                <span>📅</span>
                <span>Schedule Inspection</span>
              </button>
            )}

            {registration.status === 'for_inspection' && (
              <button
                onClick={handleUpdateToAssessed}
                className="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition-colors flex items-center space-x-2"
              >
                <span>📊</span>
                <span>Mark as Assessed</span>
              </button>
            )}

            {registration.status === 'assessed' && (
              <button
                onClick={async () => {
                  await fetchAssessmentData();
                  setShowAssessmentForm(true);
                }}
                className="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition-colors flex items-center space-x-2"
              >
                <span>📊</span>
                <span>Input Assessment Data</span>
              </button>
            )}

            {registration.status === 'assessed' && canApproveProperty() && (
              <button
                onClick={handleApprove}
                className="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg transition-colors flex items-center space-x-2"
              >
                <span>✅</span>
                <span>Approve Property</span>
              </button>
            )}
          </div>

          {registration.status === 'assessed' && (
            <div className="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
              <h3 className="text-sm font-semibold text-blue-900 mb-2">Assessment Status:</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div className={`p-3 rounded-lg ${
                  landAssessment ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'
                }`}>
                  <span className="font-semibold">Land Assessment:</span> {landAssessment ? '✓ Completed' : '✗ Missing'}
                </div>
                <div className={`p-3 rounded-lg ${
                  registration.has_building === 'yes' 
                    ? (buildingAssessment ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800')
                    : 'bg-gray-50 border border-gray-200 text-gray-600'
                }`}>
                  <span className="font-semibold">Building Assessment:</span> 
                  {registration.has_building === 'yes' 
                    ? (buildingAssessment ? '✓ Completed' : '✗ Missing')
                    : ' Not Applicable'
                  }
                </div>
              </div>
              {!canApproveProperty() && (
                <p className="text-red-600 text-sm mt-2">
                  ⚠️ Complete all required assessments before approving.
                </p>
              )}
            </div>
          )}
        </div>

        {/* Inspection Form */}
        {showInspectionForm && (
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-semibold text-blue-900">Schedule Property Inspection</h3>
              <button
                onClick={() => setShowInspectionForm(false)}
                className="text-blue-600 hover:text-blue-800 text-xl"
              >
                ✕
              </button>
            </div>
            <form onSubmit={handleInspectionSubmit} className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Scheduled Date *</label>
                  <input
                    type="date"
                    required
                    value={inspectionForm.scheduled_date}
                    onChange={(e) => setInspectionForm({...inspectionForm, scheduled_date: e.target.value})}
                    min={new Date().toISOString().split('T')[0]}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Assessor Name *</label>
                  <input
                    type="text"
                    required
                    value={inspectionForm.assessor_name}
                    onChange={(e) => setInspectionForm({...inspectionForm, assessor_name: e.target.value})}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Enter assessor's name"
                  />
                </div>
              </div>
              <div className="flex space-x-3">
                <button
                  type="submit"
                  className="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition-colors"
                >
                  Schedule Inspection
                </button>
                <button
                  type="button"
                  onClick={() => setShowInspectionForm(false)}
                  className="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition-colors"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        )}

        {/* Assessment Form */}
        {showAssessmentForm && registration.status === 'assessed' && (
          <div className="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-semibold text-green-900">Property Assessment</h3>
              <button
                onClick={() => setShowAssessmentForm(false)}
                className="text-green-600 hover:text-green-800 text-xl"
              >
                ✕
              </button>
            </div>
            
            {buildingWarning && (
              <div className="mb-4 bg-red-50 border border-red-200 rounded-lg p-4">
                <div className="flex items-start">
                  <span className="text-red-500 mr-2">⚠️</span>
                  <p className="text-red-700 text-sm">{buildingWarning}</p>
                </div>
              </div>
            )}

            <form onSubmit={handleAssessmentSubmit} className="space-y-6">
              {/* Land Assessment Section */}
              <div className="bg-white rounded-lg p-4 border border-gray-200">
                <h4 className="text-md font-semibold text-gray-900 mb-4">Land Assessment</h4>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Property Type *</label>
                    <select
                      value={assessmentForm.land_property_type}
                      onChange={(e) => setAssessmentForm({...assessmentForm, land_property_type: e.target.value})}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
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
                    <label className="block text-sm font-medium text-gray-700 mb-1">Land Area (sqm) *</label>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      value={assessmentForm.land_area_sqm}
                      onChange={(e) => setAssessmentForm({...assessmentForm, land_area_sqm: e.target.value})}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                      placeholder="Enter land area"
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Market Value per sqm</label>
                    <input
                      type="text"
                      value={formatCurrency(landCalculations.market_value_per_sqm)}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50"
                      readOnly
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Total Market Value</label>
                    <input
                      type="text"
                      value={formatCurrency(landCalculations.market_value)}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50"
                      readOnly
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Assessment Level</label>
                    <input
                      type="text"
                      value={`${landCalculations.assessment_level}%`}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50"
                      readOnly
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Assessed Value</label>
                    <input
                      type="text"
                      value={formatCurrency(landCalculations.assessed_value)}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50"
                      readOnly
                    />
                  </div>
                </div>
              </div>

              {/* Building Assessment Section */}
              {registration.has_building === 'yes' && (
                <div className="bg-white rounded-lg p-4 border border-gray-200">
                  <h4 className="text-md font-semibold text-gray-900 mb-4">Building Assessment</h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Construction Type *</label>
                      <select
                        value={assessmentForm.construction_type}
                        onChange={(e) => setAssessmentForm({...assessmentForm, construction_type: e.target.value})}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
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
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Floor Area (sqm) *</label>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={assessmentForm.floor_area_sqm}
                        onChange={(e) => setAssessmentForm({...assessmentForm, floor_area_sqm: e.target.value})}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
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
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Market Value per sqm</label>
                      <input
                        type="text"
                        value={formatCurrency(buildingCalculations.market_value_per_sqm)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50"
                        readOnly
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Total Market Value</label>
                      <input
                        type="text"
                        value={formatCurrency(buildingCalculations.market_value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50"
                        readOnly
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Building Age</label>
                      <input
                        type="text"
                        value={`${buildingCalculations.building_age} years`}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50"
                        readOnly
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Depreciation</label>
                      <input
                        type="text"
                        value={`${buildingCalculations.depreciation_percent.toFixed(2)}%`}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50"
                        readOnly
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Depreciated Value</label>
                      <input
                        type="text"
                        value={formatCurrency(buildingCalculations.depreciated_value)}
                        className={`w-full px-3 py-2 border rounded-md ${
                          buildingCalculations.range_matched ? 'border-gray-300 bg-gray-50' : 'border-yellow-300 bg-yellow-50'
                        }`}
                        readOnly
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Assessment Level</label>
                      <input
                        type="text"
                        value={buildingCalculations.assessment_level ? `${buildingCalculations.assessment_level}%` : 'N/A'}
                        className={`w-full px-3 py-2 border rounded-md ${
                          buildingCalculations.range_matched ? 'border-gray-300 bg-gray-50' : 'border-red-300 bg-red-50'
                        }`}
                        readOnly
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Assessed Value</label>
                      <input
                        type="text"
                        value={buildingCalculations.assessed_value ? formatCurrency(buildingCalculations.assessed_value) : 'N/A'}
                        className={`w-full px-3 py-2 border rounded-md ${
                          buildingCalculations.range_matched ? 'border-gray-300 bg-gray-50' : 'border-red-300 bg-red-50'
                        }`}
                        readOnly
                      />
                    </div>
                  </div>
                  
                  {buildingCalculations.matched_assessment_level && (
                    <div className="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                      <p className="text-sm text-blue-800">
                        <span className="font-semibold">Assessment Level Applied:</span> {buildingCalculations.assessment_level}%
                        <br />
                        <span className="text-xs">Based on depreciated value range: {formatCurrency(buildingCalculations.matched_assessment_level.min_assessed_value)} - {formatCurrency(buildingCalculations.matched_assessment_level.max_assessed_value)}</span>
                      </p>
                    </div>
                  )}
                </div>
              )}

              {/* Tax Calculation Summary */}
              <div className="bg-white rounded-lg p-4 border border-gray-200">
                <h4 className="text-md font-semibold text-gray-900 mb-4">Tax Calculation Summary</h4>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                  <div className="bg-blue-50 p-4 rounded-lg">
                    <h5 className="text-sm font-semibold text-blue-900 mb-2">Tax Rates Used:</h5>
                    <div className="space-y-1">
                      <div className="flex justify-between">
                        <span className="text-sm text-blue-700">Basic Tax:</span>
                        <span className="text-sm font-semibold text-blue-900">{taxCalculations.basic_tax_percent}%</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-blue-700">SEF Tax:</span>
                        <span className="text-sm font-semibold text-blue-900">{taxCalculations.sef_tax_percent}%</span>
                      </div>
                      <div className="flex justify-between border-t pt-1">
                        <span className="text-sm font-semibold text-blue-900">Total Tax Rate:</span>
                        <span className="text-sm font-semibold text-blue-900">{taxCalculations.total_tax_rate}%</span>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Land Tax Breakdown */}
                <div className="mb-4">
                  <h5 className="text-sm font-semibold text-gray-700 mb-2">Land Tax Breakdown:</h5>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="bg-gray-50 p-3 rounded-lg">
                      <p className="text-sm text-gray-600">Basic Tax</p>
                      <p className="text-lg font-semibold text-gray-900">{formatCurrency(taxCalculations.land_basic_tax)}</p>
                    </div>
                    <div className="bg-gray-50 p-3 rounded-lg">
                      <p className="text-sm text-gray-600">SEF Tax</p>
                      <p className="text-lg font-semibold text-gray-900">{formatCurrency(taxCalculations.land_sef_tax)}</p>
                    </div>
                    <div className="bg-green-50 p-3 rounded-lg">
                      <p className="text-sm text-green-600">Land Annual Tax</p>
                      <p className="text-lg font-semibold text-green-900">{formatCurrency(taxCalculations.land_annual_tax)}</p>
                    </div>
                  </div>
                </div>

                {/* Building Tax Breakdown */}
                {registration.has_building === 'yes' && buildingCalculations.assessed_value > 0 && (
                  <div className="mb-4">
                    <h5 className="text-sm font-semibold text-gray-700 mb-2">Building Tax Breakdown:</h5>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                      <div className="bg-gray-50 p-3 rounded-lg">
                        <p className="text-sm text-gray-600">Basic Tax</p>
                        <p className="text-lg font-semibold text-gray-900">{formatCurrency(taxCalculations.building_basic_tax)}</p>
                      </div>
                      <div className="bg-gray-50 p-3 rounded-lg">
                        <p className="text-sm text-gray-600">SEF Tax</p>
                        <p className="text-lg font-semibold text-gray-900">{formatCurrency(taxCalculations.building_sef_tax)}</p>
                      </div>
                      <div className="bg-green-50 p-3 rounded-lg">
                        <p className="text-sm text-green-600">Building Annual Tax</p>
                        <p className="text-lg font-semibold text-green-900">{formatCurrency(taxCalculations.building_annual_tax)}</p>
                      </div>
                    </div>
                  </div>
                )}

                {/* Total Tax Summary */}
                <div className="bg-blue-50 p-4 rounded-lg">
                  <h5 className="text-sm font-semibold text-blue-900 mb-2">Total Property Tax Summary:</h5>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="text-center">
                      <p className="text-sm text-blue-700">Total Basic Tax</p>
                      <p className="text-xl font-bold text-blue-900">{formatCurrency(taxCalculations.total_basic_tax)}</p>
                    </div>
                    <div className="text-center">
                      <p className="text-sm text-blue-700">Total SEF Tax</p>
                      <p className="text-xl font-bold text-blue-900">{formatCurrency(taxCalculations.total_sef_tax)}</p>
                    </div>
                    <div className="text-center">
                      <p className="text-sm text-green-700">Total Annual Tax</p>
                      <p className="text-2xl font-bold text-green-900">{formatCurrency(taxCalculations.total_annual_tax)}</p>
                    </div>
                  </div>
                </div>
              </div>

              <div className="flex space-x-3">
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white px-6 py-3 rounded-lg transition-colors flex items-center space-x-2"
                >
                  {isSubmitting ? (
                    <>
                      <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                      <span>Saving...</span>
                    </>
                  ) : (
                    <>
                      <span>💾</span>
                      <span>Save Assessment</span>
                    </>
                  )}
                </button>
                <button
                  type="button"
                  onClick={() => setShowAssessmentForm(false)}
                  className="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg transition-colors"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        )}

        {/* Registration Details */}
        <div className="bg-white rounded-xl shadow-lg border border-gray-200 p-6 mb-6">
          <h2 className="text-xl font-bold text-gray-900 mb-6">Registration Details</h2>
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {/* Property Information */}
            <div className="space-y-4">
              <h3 className="text-lg font-semibold text-gray-900 border-b pb-2">Property Information</h3>
              <div>
                <label className="block text-sm font-medium text-gray-600">Property Type</label>
                <p className="mt-1 text-sm text-gray-900">{registration.property_type}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Location Address</label>
                <p className="mt-1 text-sm text-gray-900">{registration.location_address}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Barangay</label>
                <p className="mt-1 text-sm text-gray-900">{registration.barangay}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Municipality/City</label>
                <p className="mt-1 text-sm text-gray-900">{registration.municipality_city}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Province</label>
                <p className="mt-1 text-sm text-gray-900">{registration.province}</p>
              </div>
            </div>

            {/* Owner Information */}
            <div className="space-y-4">
              <h3 className="text-lg font-semibold text-gray-900 border-b pb-2">Owner Information</h3>
              <div>
                <label className="block text-sm font-medium text-gray-600">Owner Name</label>
                <p className="mt-1 text-sm text-gray-900">{registration.owner_name}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Owner Address</label>
                <p className="mt-1 text-sm text-gray-900">{registration.owner_address}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Contact Number</label>
                <p className="mt-1 text-sm text-gray-900">{registration.contact_number}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Email Address</label>
                <p className="mt-1 text-sm text-gray-900">{registration.email_address}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">TIN</label>
                <p className="mt-1 text-sm text-gray-900">{registration.tin}</p>
              </div>
            </div>

            {/* Additional Information */}
            <div className="space-y-4">
              <h3 className="text-lg font-semibold text-gray-900 border-b pb-2">Additional Information</h3>
              <div>
                <label className="block text-sm font-medium text-gray-600">Has Building</label>
                <p className="mt-1 text-sm text-gray-900">
                  <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                    registration.has_building === 'yes' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                  }`}>
                    {registration.has_building === 'yes' ? 'Yes' : 'No'}
                  </span>
                </p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Date Registered</label>
                <p className="mt-1 text-sm text-gray-900">{formatDate(registration.date_registered)}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Last Updated</label>
                <p className="mt-1 text-sm text-gray-900">{formatDate(registration.last_updated)}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Remarks</label>
                <p className="mt-1 text-sm text-gray-900">{registration.remarks}</p>
              </div>
            </div>
          </div>
        </div>

        {/* Assessment Data Display */}
        {(landAssessment || buildingAssessment) && (
          <div className="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-6">Current Assessment Data</h2>
            
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* Land Assessment */}
              {landAssessment && (
                <div className="space-y-4">
                  <h3 className="text-lg font-semibold text-gray-900 border-b pb-2">Land Assessment</h3>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-600">TDN</label>
                      <p className="mt-1 text-sm font-mono text-gray-900">{landAssessment.tdn || 'Not Generated'}</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600">Property Type</label>
                      <p className="mt-1 text-sm text-gray-900">{landAssessment.property_type}</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600">Land Area (sqm)</label>
                      <p className="mt-1 text-sm text-gray-900">{landAssessment.land_area_sqm}</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600">Market Value</label>
                      <p className="mt-1 text-sm text-gray-900">{formatCurrency(parseFloat(landAssessment.land_market_value))}</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600">Assessment Level</label>
                      <p className="mt-1 text-sm text-gray-900">{landAssessment.assessment_level}%</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600">Assessed Value</label>
                      <p className="mt-1 text-sm text-gray-900">{formatCurrency(parseFloat(landAssessment.land_assessed_value))}</p>
                    </div>
                  </div>
                </div>
              )}

              {/* Building Assessment */}
              {buildingAssessment && (
                <div className="space-y-4">
                  <h3 className="text-lg font-semibold text-gray-900 border-b pb-2">Building Assessment</h3>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-600">TDN</label>
                      <p className="mt-1 text-sm font-mono text-gray-900">{buildingAssessment.tdn || 'Not Generated'}</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600">Construction Type</label>
                      <p className="mt-1 text-sm text-gray-900">{buildingAssessment.construction_type}</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600">Floor Area (sqm)</label>
                      <p className="mt-1 text-sm text-gray-900">{buildingAssessment.floor_area_sqm}</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600">Year Built</label>
                      <p className="mt-1 text-sm text-gray-900">{buildingAssessment.year_built}</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600">Market Value</label>
                      <p className="mt-1 text-sm text-gray-900">{formatCurrency(parseFloat(buildingAssessment.building_market_value))}</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600">Depreciated Value</label>
                      <p className="mt-1 text-sm text-gray-900">{formatCurrency(parseFloat(buildingAssessment.building_depreciated_value))}</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600">Depreciation</label>
                      <p className="mt-1 text-sm text-gray-900">{buildingAssessment.depreciation_percent}%</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600">Assessment Level</label>
                      <p className="mt-1 text-sm text-gray-900">{buildingAssessment.assessment_level}%</p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600">Assessed Value</label>
                      <p className="mt-1 text-sm text-gray-900">{formatCurrency(parseFloat(buildingAssessment.building_assessed_value))}</p>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}