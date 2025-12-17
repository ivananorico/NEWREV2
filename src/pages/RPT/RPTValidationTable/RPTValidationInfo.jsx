import React, { useState, useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";

export default function RPTValidationInfo() {
  const { id } = useParams();
  const navigate = useNavigate();
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
    
    // Building Properties (only if has_building === 'yes')
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

  const API_BASE = "http://localhost/revenue/backend/RPT/RPTValidationTable";

  // DEBUG: Log the ID when component mounts
  useEffect(() => {
    console.log("🔍 RPTValidationInfo Component Mounted");
    console.log("📌 URL Parameter ID:", id);
    console.log("📌 Type of ID:", typeof id);
    
    if (!id || id === "undefined") {
      console.error("❌ ERROR: No valid ID found in URL");
      setError("No registration ID provided in URL");
      setLoading(false);
      return;
    }
  }, [id]);

  useEffect(() => {
    if (id && id !== "undefined") {
      console.log("🚀 Starting data fetch for ID:", id);
      fetchRegistrationDetails();
      fetchConfigurations();
      fetchAssessmentData();
    }
  }, [id]);

  useEffect(() => {
    calculateAnnualTaxes();
  }, [landCalculations.assessed_value, buildingCalculations.assessed_value, taxConfigs]);

  // Calculate annual taxes for both Basic Tax and SEF Tax
  const calculateAnnualTaxes = () => {
    // Find Basic Tax and SEF Tax from taxConfigs
    const basicTax = taxConfigs.find(tax => tax.tax_name === 'Basic Tax');
    const sefTax = taxConfigs.find(tax => tax.tax_name === 'SEF Tax');

    const basicTaxPercent = basicTax ? parseFloat(basicTax.tax_percent) : 0;
    const sefTaxPercent = sefTax ? parseFloat(sefTax.tax_percent) : 0;
    
    // Total tax rate = Basic Tax % + SEF Tax %
    const totalTaxRate = (basicTaxPercent + sefTaxPercent) / 100;

    // LAND TAXES = Land Assessed Value × Total Tax Rate
    const land_annual_tax = landCalculations.assessed_value * totalTaxRate;
    const land_basic_tax = land_annual_tax * (basicTaxPercent / (basicTaxPercent + sefTaxPercent));
    const land_sef_tax = land_annual_tax * (sefTaxPercent / (basicTaxPercent + sefTaxPercent));

    // BUILDING TAXES = Building Assessed Value × Total Tax Rate
    const building_annual_tax = buildingCalculations.assessed_value * totalTaxRate;
    const building_basic_tax = building_annual_tax * (basicTaxPercent / (basicTaxPercent + sefTaxPercent));
    const building_sef_tax = building_annual_tax * (sefTaxPercent / (basicTaxPercent + sefTaxPercent));

    // TOTAL TAXES
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

    // Update individual calculations
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

  // Fetch existing assessment data
  const fetchAssessmentData = async () => {
    try {
      console.log("📊 Fetching assessment data for ID:", id);
      
      const response = await fetch(`${API_BASE}/get_assessment_data.php?id=${id}`);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const text = await response.text();
      console.log("📄 Raw assessment response:", text);
      
      const data = JSON.parse(text);
      console.log("📊 Parsed assessment data:", data);

      if (data.status === "success") {
        setLandAssessment(data.land_assessment || null);
        setBuildingAssessment(data.building_assessment || null);
        
        console.log("🏠 Land assessment:", data.land_assessment);
        console.log("🏢 Building assessment:", data.building_assessment);
        
        // If assessment data exists, pre-fill the form
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
      } else {
        console.warn("⚠️ No assessment data found:", data.message);
      }
    } catch (err) {
      console.error("❌ Error fetching assessment data:", err);
    }
  };

  // Check if assessment data exists for approval
  const canApproveProperty = () => {
    if (!registration || registration.status !== 'assessed') {
      return false;
    }

    // Check if land assessment exists
    if (!landAssessment) {
      return false;
    }

    // If property has building, check if building assessment exists
    if (registration.has_building === 'yes' && !buildingAssessment) {
      return false;
    }

    return true;
  };

 const fetchRegistrationDetails = async () => {
  try {
    console.log("📋 Fetching registration details for ID:", id);
    console.log("🔗 Full URL:", `${API_BASE}/get_registration_details.php?id=${id}`);
    
    // Add timeout and better error handling
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
    
    const response = await fetch(`${API_BASE}/get_registration_details.php?id=${id}`, {
      signal: controller.signal
    });
    
    clearTimeout(timeoutId);
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const text = await response.text();
    console.log("📄 Raw registration response:", text);
    
    // Try to parse JSON
    let data;
    try {
      data = JSON.parse(text);
    } catch (parseError) {
      console.error("❌ JSON parse error:", parseError);
      throw new Error("Invalid JSON response from server");
    }
    
    console.log("📋 Registration API Response:", data);

    if (data.status === "success") {
      setRegistration(data.registration);
      console.log("✅ Registration data loaded:", data.registration);
    } else {
      throw new Error(data.message || "Failed to fetch registration details");
    }
  } catch (err) {
    console.error("❌ Error fetching registration details:", err);
    setError(err.message);
  } finally {
    setLoading(false);
  }
};

  const fetchConfigurations = async () => {
    try {
      console.log("⚙️ Fetching configurations...");
      
      // Fetch land configurations
      const landResponse = await fetch(`${API_BASE}/get_land_configurations.php`);
      const landData = await landResponse.json();
      console.log("🌍 Land configs:", landData);
      
      if (landData.status === "success") {
        const activeLandConfigs = landData.configurations.filter(config => config.status === 'active') || [];
        setLandConfigs(activeLandConfigs);
        
        if (activeLandConfigs.length > 0 && !assessmentForm.land_property_type) {
          setAssessmentForm(prev => ({
            ...prev,
            land_property_type: activeLandConfigs[0].classification
          }));
        }
      }

      // Fetch property configurations (for unit_cost and depreciation_rate only)
      const propertyResponse = await fetch(`${API_BASE}/get_property_configurations.php`);
      const propertyData = await propertyResponse.json();
      console.log("🏗️ Property configs:", propertyData);
      
      if (propertyData.status === "success") {
        const activePropertyConfigs = propertyData.configurations.filter(config => config.status === 'active') || [];
        setPropertyConfigs(activePropertyConfigs);
        
        if (activePropertyConfigs.length > 0 && !assessmentForm.construction_type) {
          setAssessmentForm(prev => ({
            ...prev,
            construction_type: activePropertyConfigs[0].material_type
          }));
        }
      }

      // Fetch building assessment levels (NEW TABLE - for assessment levels based on classification and depreciated value ranges)
      const assessmentResponse = await fetch(`${API_BASE}/get_building_assessment_levels.php`);
      const assessmentData = await assessmentResponse.json();
      console.log("📊 Building assessment levels:", assessmentData);
      
      if (assessmentData.status === "success") {
        setBuildingAssessmentLevels(assessmentData.assessment_levels || []);
      }

      // Fetch tax configurations
      const taxResponse = await fetch(`${API_BASE}/get_tax_configurations.php`);
      const taxData = await taxResponse.json();
      console.log("💰 Tax configs:", taxData);
      
      if (taxData.status === "success") {
        setTaxConfigs(taxData.tax_configurations || []);
      }

    } catch (err) {
      console.error("❌ Error fetching configurations:", err);
    }
  };

  // Calculate land values
  useEffect(() => {
    calculateLandValues();
  }, [assessmentForm.land_property_type, assessmentForm.land_area_sqm, landConfigs]);

  // Calculate building values - NOW using building assessment levels table
  useEffect(() => {
    calculateBuildingValues();
  }, [assessmentForm.construction_type, assessmentForm.floor_area_sqm, assessmentForm.year_built, 
      propertyConfigs, buildingAssessmentLevels, assessmentForm.land_property_type]);

  // Get filtered construction types based on land classification
  const getFilteredConstructionTypes = () => {
    if (!assessmentForm.land_property_type) return [];
    
    // Get all unique material types from property configs that have the same classification
    const classificationPropertyConfigs = propertyConfigs.filter(config => 
      config.classification === assessmentForm.land_property_type && 
      config.status === 'active'
    );
    
    // Extract unique material types
    const uniqueMaterialTypes = [...new Set(classificationPropertyConfigs.map(config => config.material_type))];
    
    return uniqueMaterialTypes;
  };

  const calculateLandValues = () => {
    const { land_property_type, land_area_sqm } = assessmentForm;
    
    if (!land_area_sqm || land_area_sqm <= 0 || !land_property_type) {
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
      const market_value_per_sqm = parseFloat(landConfig.market_value);
      const market_value = parseFloat(land_area_sqm) * market_value_per_sqm;
      const assessed_value = market_value * (parseFloat(landConfig.assessment_level) / 100);
      
      setLandCalculations({
        market_value: market_value,
        assessed_value: assessed_value,
        assessment_level: parseFloat(landConfig.assessment_level),
        classification: landConfig.classification,
        market_value_per_sqm: market_value_per_sqm,
        basic_tax: 0, // Will be calculated in calculateAnnualTaxes
        sef_tax: 0,   // Will be calculated in calculateAnnualTaxes
        annual_tax: 0 // Will be calculated in calculateAnnualTaxes
      });

      setAssessmentForm(prev => ({
        ...prev,
        land_market_value: market_value.toFixed(2),
        land_assessed_value: assessed_value.toFixed(2),
        land_assessment_level: landConfig.assessment_level
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
    
    if (!floor_area_sqm || floor_area_sqm <= 0 || !construction_type || !land_property_type) {
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

    // Find property config for unit_cost and depreciation_rate
    const propertyConfig = propertyConfigs.find(config => 
      config.material_type === construction_type && 
      config.classification === land_property_type &&
      config.status === 'active'
    );

    if (!propertyConfig) {
      setBuildingWarning(`No configuration found for construction type: ${construction_type} in ${land_property_type} classification`);
      
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
    const buildingAge = currentYear - parseInt(year_built);
    const market_value_per_sqm = parseFloat(propertyConfig.unit_cost);
    const market_value = parseFloat(floor_area_sqm) * market_value_per_sqm;
    
    // Calculate depreciation
    const depreciationPercent = Math.min(100, buildingAge * parseFloat(propertyConfig.depreciation_rate));
    const depreciated_value = market_value * ((100 - depreciationPercent) / 100);
    
    // NOW: Find assessment level from building_assessment_levels table based on:
    // 1. Classification (Commercial, Residential, etc.)
    // 2. Depreciated value falls within min_assessed_value and max_assessed_value ranges
    const matchingAssessmentLevel = buildingAssessmentLevels.find(level => 
      level.classification === land_property_type &&
      depreciated_value >= parseFloat(level.min_assessed_value) && 
      depreciated_value <= parseFloat(level.max_assessed_value) &&
      level.status === 'active'
    );

    if (matchingAssessmentLevel) {
      // Calculate assessed value: Depreciated Value × Assessment Level %
      const assessed_value = depreciated_value * (parseFloat(matchingAssessmentLevel.level_percent) / 100);
      
      setBuildingCalculations({
        market_value: market_value,
        depreciated_value: depreciated_value,
        assessed_value: assessed_value,
        assessment_level: parseFloat(matchingAssessmentLevel.level_percent),
        depreciation_percent: depreciationPercent,
        material_type: construction_type,
        building_age: buildingAge,
        range_matched: true,
        matched_assessment_level: matchingAssessmentLevel,
        market_value_per_sqm: market_value_per_sqm,
        basic_tax: 0, // Will be calculated in calculateAnnualTaxes
        sef_tax: 0,   // Will be calculated in calculateAnnualTaxes
        annual_tax: 0 // Will be calculated in calculateAnnualTaxes
      });

      setAssessmentForm(prev => ({
        ...prev,
        building_market_value: market_value.toFixed(2),
        building_depreciated_value: depreciated_value.toFixed(2),
        building_assessed_value: assessed_value.toFixed(2),
        building_assessment_level: matchingAssessmentLevel.level_percent,
        depreciation_percent: depreciationPercent.toFixed(2)
      }));

      setBuildingWarning("");
    } else {
      // No matching assessment level found for this depreciated value range
      setBuildingCalculations({
        market_value: market_value,
        depreciated_value: depreciated_value,
        assessed_value: 0,
        assessment_level: 0,
        depreciation_percent: depreciationPercent,
        material_type: construction_type,
        building_age: buildingAge,
        range_matched: false,
        matched_assessment_level: null,
        market_value_per_sqm: market_value_per_sqm,
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

      // Get all assessment levels for this classification
      const classificationLevels = buildingAssessmentLevels.filter(
        level => level.classification === land_property_type && level.status === 'active'
      );
      
      if (classificationLevels.length > 0) {
        const minRange = Math.min(...classificationLevels.map(level => parseFloat(level.min_assessed_value)));
        const maxRange = Math.max(...classificationLevels.map(level => parseFloat(level.max_assessed_value)));
        
        setBuildingWarning(
          `❌ Depreciated value ${formatCurrency(depreciated_value)} is outside configured assessed value ranges for ${land_property_type} classification ` +
          `(${formatCurrency(minRange)} - ${formatCurrency(maxRange)}). ` +
          `Assessment level and assessed value cannot be calculated.`
        );
      } else {
        setBuildingWarning(
          `❌ No assessment levels configured for ${land_property_type} classification. ` +
          `Please add assessment levels in the building_assessment_levels table.`
        );
      }
    }
  };

  // Handle inspection scheduling
  const handleInspectionSubmit = async (e) => {
    e.preventDefault();
    try {
      console.log("📅 Submitting inspection form:", inspectionForm);
      
      const response = await fetch(`${API_BASE}/schedule_inspection.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          registration_id: id,
          ...inspectionForm
        })
      });

      const data = await response.json();
      console.log("📅 Inspection response:", data);

      if (data.status === "success") {
        alert("Inspection scheduled successfully!");
        setShowInspectionForm(false);
        setInspectionForm({
          scheduled_date: "",
          assessor_name: ""
        });
        await updateRegistrationStatus('for_inspection');
      } else {
        throw new Error(data.message || "Failed to schedule inspection");
      }
    } catch (err) {
      alert(`Error: ${err.message}`);
    }
  };

  // Handle assessment submission (save assessment data without TDN)
  const handleAssessmentSubmit = async (e) => {
    e.preventDefault();
    setIsSubmitting(true);
    
    console.log("💾 Submitting assessment data:", assessmentForm);
    
    // Show warning if building range doesn't match
    if (buildingWarning && registration.has_building === 'yes') {
      const proceed = window.confirm(
        "Building depreciated value is outside configured assessed value ranges. Assessment level and assessed value cannot be calculated. Do you want to proceed with the assessment anyway?"
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
        // Only include building data if property has building
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

      console.log("📤 Sending assessment data to backend:", submissionData);

      const response = await fetch(`${API_BASE}/assess_property.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(submissionData)
      });

      const data = await response.json();
      console.log("📥 Assessment response:", data);

      if (data.status === "success") {
        alert(`Property assessment ${data.action || 'saved'} successfully!\n\n📊 Assessment Summary:\nLand Annual Tax: ${formatCurrency(taxCalculations.land_annual_tax)}\nBuilding Annual Tax: ${formatCurrency(taxCalculations.building_annual_tax)}\nTotal Annual Tax: ${formatCurrency(taxCalculations.total_annual_tax)}\n\n💡 Note: Quarterly taxes will be generated when you approve the property.`);
        
        // Refresh assessment data but keep form open
        await fetchAssessmentData();
        
        // Ask user if they want to close the form
        const shouldClose = window.confirm("Assessment saved successfully! Do you want to close the form? Click OK to close, Cancel to continue editing.");
        
        if (shouldClose) {
          setShowAssessmentForm(false);
        }
        
      } else {
        throw new Error(data.message || "Failed to assess property");
      }
    } catch (err) {
      alert(`Error: ${err.message}`);
    } finally {
      setIsSubmitting(false);
    }
  };

  // Handle approval - Generate TDN and create property_totals + quarterly taxes
  const handleApprove = async () => {
    if (window.confirm("Are you sure you want to approve this property assessment?\n\nThis will:\n• Generate permanent TDNs\n• Create property tax totals\n• Generate quarterly tax bills\n• Change status to 'approved'")) {
      try {
        console.log("✅ Approving property ID:", id);
        
        const response = await fetch(`${API_BASE}/approve_property.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
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
          alert(`Property approved successfully!\n\n📋 Generated TDNs:\nLand TDN: ${data.tdns.land_tdn}${data.tdns.building_tdn ? `\nBuilding TDN: ${data.tdns.building_tdn}` : ''}\n\n💰 Tax Summary:\nLand Annual Tax: ${formatCurrency(data.totals.land_annual_tax)}\nBuilding Annual Tax: ${formatCurrency(data.totals.building_annual_tax)}\nTotal Annual Tax: ${formatCurrency(data.totals.total_annual_tax)}\n\n📅 Quarterly taxes generated for 2025`);
          await fetchRegistrationDetails();
          await fetchAssessmentData(); // Refresh to get the new TDNs
        } else {
          throw new Error(data.message || "Failed to approve property");
        }
      } catch (err) {
        alert(`Error: ${err.message}`);
      }
    }
  };

  // Update registration status to 'assessed'
  const handleUpdateToAssessed = async () => {
    if (window.confirm("Are you sure you want to mark this property as assessed? This will show the assessment form.")) {
      try {
        console.log("📊 Updating status to assessed for ID:", id);
        
        const response = await fetch(`${API_BASE}/update_registration_status.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            registration_id: id,
            status: 'assessed'
          })
        });

        const data = await response.json();
        console.log("📊 Status update response:", data);

        if (data.status === "success") {
          alert("Property marked as assessed!");
          await fetchRegistrationDetails();
          setShowAssessmentForm(true); // Automatically show assessment form
        } else {
          throw new Error(data.message || "Failed to update status");
        }
      } catch (err) {
        alert(`Error: ${err.message}`);
      }
    }
  };

  // Update registration status
  const updateRegistrationStatus = async (status) => {
    try {
      const response = await fetch(`${API_BASE}/update_registration_status.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          registration_id: id,
          status: status
        })
      });
      await response.json();
      await fetchRegistrationDetails();
    } catch (err) {
      console.error("Error updating status:", err);
    }
  };

  // Format date
  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  };

  // Format currency
  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP'
    }).format(amount);
  };

  // Reset construction type when land property type changes
  useEffect(() => {
    if (assessmentForm.land_property_type) {
      const filteredTypes = getFilteredConstructionTypes();
      if (filteredTypes.length > 0) {
        // If current construction type is not in filtered types, reset it
        if (!filteredTypes.includes(assessmentForm.construction_type)) {
          setAssessmentForm(prev => ({
            ...prev,
            construction_type: filteredTypes[0]
          }));
        }
      } else {
        // No construction types available for this classification
        setAssessmentForm(prev => ({
          ...prev,
          construction_type: ""
        }));
      }
    }
  }, [assessmentForm.land_property_type]);

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white rounded-lg shadow-lg p-8 max-w-md w-full mx-4">
          <div className="flex items-center justify-center space-x-3">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <span className="text-gray-700 font-medium">Loading registration details...</span>
          </div>
        </div>
      </div>
    );
  }

  if (error || !registration) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white rounded-lg shadow-lg p-8 max-w-md w-full mx-4 text-center">
          <div className="text-red-500 text-xl mb-4">⚠️</div>
          <h2 className="text-xl font-bold text-gray-900 mb-2">
            {error ? "Error Loading Data" : "Registration Not Found"}
          </h2>
          <p className="text-gray-600 mb-6">{error || "The requested registration could not be found."}</p>
          <div className="space-x-4">
            <button
              onClick={() => navigate(-1)}
              className="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition-colors"
            >
              Go Back
            </button>
            <button
              onClick={fetchRegistrationDetails}
              className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors"
            >
              Try Again
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-6xl mx-auto px-4">
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
                  <p className="text-gray-600">Reference: <span className="font-mono font-semibold">{registration.reference_number}</span></p>
                </div>
              </div>
            </div>

            {/* Status Badge */}
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
          <div className="flex flex-col sm:flex-row gap-4">
            {/* Schedule Inspection Button - Show when pending */}
            {registration.status === 'pending' && (
              <button
                onClick={() => setShowInspectionForm(true)}
                className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors flex items-center justify-center space-x-2"
              >
                <span>📅</span>
                <span>Schedule Inspection</span>
              </button>
            )}

            {/* Assess Button - Show when for_inspection (update status to assessed) */}
            {registration.status === 'for_inspection' && (
              <button
                onClick={handleUpdateToAssessed}
                className="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition-colors flex items-center justify-center space-x-2"
              >
                <span>📊</span>
                <span>Mark as Assessed</span>
              </button>
            )}

            {/* Assess Property Button - Show when assessed (to input assessment data) */}
            {registration.status === 'assessed' && (
              <button
                onClick={async () => {
                  // Refresh assessment data before showing the form
                  await fetchAssessmentData();
                  setShowAssessmentForm(true);
                }}
                className="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition-colors flex items-center justify-center space-x-2"
              >
                <span>📊</span>
                <span>Input Assessment Data</span>
              </button>
            )}

            {/* Approve Button - Show when assessed AND has assessment data */}
            {registration.status === 'assessed' && canApproveProperty() && (
              <button
                onClick={handleApprove}
                className="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg transition-colors flex items-center justify-center space-x-2"
              >
                <span>✅</span>
                <span>Approve Property</span>
              </button>
            )}
          </div>

          {/* Assessment Status Info */}
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
                className="text-blue-600 hover:text-blue-800"
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

        {/* Assessment Form - Show when status is assessed */}
        {showAssessmentForm && registration.status === 'assessed' && (
          <div className="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-semibold text-green-900">Property Assessment</h3>
              <button
                onClick={() => setShowAssessmentForm(false)}
                className="text-green-600 hover:text-green-800"
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
                      {landConfigs.filter(config => config.status === 'active').map((config) => (
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

              {/* Building Assessment Section - Only show if property has building */}
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
                  
                  {/* Assessment Level Info */}
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
                
                {/* Tax Rates */}
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
                      <p className="text-xs text-gray-500">({taxCalculations.basic_tax_percent}% of {formatCurrency(landCalculations.assessed_value)})</p>
                    </div>
                    <div className="bg-gray-50 p-3 rounded-lg">
                      <p className="text-sm text-gray-600">SEF Tax</p>
                      <p className="text-lg font-semibold text-gray-900">{formatCurrency(taxCalculations.land_sef_tax)}</p>
                      <p className="text-xs text-gray-500">({taxCalculations.sef_tax_percent}% of {formatCurrency(landCalculations.assessed_value)})</p>
                    </div>
                    <div className="bg-green-50 p-3 rounded-lg">
                      <p className="text-sm text-green-600">Land Annual Tax</p>
                      <p className="text-lg font-semibold text-green-900">{formatCurrency(taxCalculations.land_annual_tax)}</p>
                      <p className="text-xs text-green-500">({taxCalculations.total_tax_rate}% of {formatCurrency(landCalculations.assessed_value)})</p>
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
                        <p className="text-xs text-gray-500">({taxCalculations.basic_tax_percent}% of {formatCurrency(buildingCalculations.assessed_value)})</p>
                      </div>
                      <div className="bg-gray-50 p-3 rounded-lg">
                        <p className="text-sm text-gray-600">SEF Tax</p>
                        <p className="text-lg font-semibold text-gray-900">{formatCurrency(taxCalculations.building_sef_tax)}</p>
                        <p className="text-xs text-gray-500">({taxCalculations.sef_tax_percent}% of {formatCurrency(buildingCalculations.assessed_value)})</p>
                      </div>
                      <div className="bg-green-50 p-3 rounded-lg">
                        <p className="text-sm text-green-600">Building Annual Tax</p>
                        <p className="text-lg font-semibold text-green-900">{formatCurrency(taxCalculations.building_annual_tax)}</p>
                        <p className="text-xs text-green-500">({taxCalculations.total_tax_rate}% of {formatCurrency(buildingCalculations.assessed_value)})</p>
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
                <p className="mt-1 text-sm text-gray-900">{registration.property_type || 'N/A'}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Location Address</label>
                <p className="mt-1 text-sm text-gray-900">{registration.location_address || 'N/A'}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Barangay</label>
                <p className="mt-1 text-sm text-gray-900">{registration.barangay || 'N/A'}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Municipality/City</label>
                <p className="mt-1 text-sm text-gray-900">{registration.municipality_city || 'N/A'}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Province</label>
                <p className="mt-1 text-sm text-gray-900">{registration.province || 'N/A'}</p>
              </div>
            </div>

            {/* Owner Information */}
            <div className="space-y-4">
              <h3 className="text-lg font-semibold text-gray-900 border-b pb-2">Owner Information</h3>
              <div>
                <label className="block text-sm font-medium text-gray-600">Owner Name</label>
                <p className="mt-1 text-sm text-gray-900">{registration.owner_name || 'N/A'}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Owner Address</label>
                <p className="mt-1 text-sm text-gray-900">{registration.owner_address || 'N/A'}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Contact Number</label>
                <p className="mt-1 text-sm text-gray-900">{registration.contact_number || 'N/A'}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">Email Address</label>
                <p className="mt-1 text-sm text-gray-900">{registration.email_address || 'N/A'}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-600">TIN</label>
                <p className="mt-1 text-sm text-gray-900">{registration.tin || 'N/A'}</p>
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
              {registration.remarks && (
                <div>
                  <label className="block text-sm font-medium text-gray-600">Remarks</label>
                  <p className="mt-1 text-sm text-gray-900">{registration.remarks}</p>
                </div>
              )}
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
                    {landAssessment.annual_tax && (
                      <div className="col-span-2">
                        <label className="block text-sm font-medium text-gray-600">Annual Tax</label>
                        <p className="mt-1 text-sm font-semibold text-green-900">{formatCurrency(parseFloat(landAssessment.annual_tax))}</p>
                      </div>
                    )}
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
                    {buildingAssessment.annual_tax && (
                      <div className="col-span-2">
                        <label className="block text-sm font-medium text-gray-600">Annual Tax</label>
                        <p className="mt-1 text-sm font-semibold text-green-900">{formatCurrency(parseFloat(buildingAssessment.annual_tax))}</p>
                      </div>
                    )}
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