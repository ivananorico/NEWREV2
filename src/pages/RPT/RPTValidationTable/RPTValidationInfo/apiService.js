// API configuration that works for both localhost and production
const API_BASE = window.location.hostname === "localhost"
  ? "http://localhost/revenue2/backend"
  : "https://revenuetreasury.goserveph.com/backend";

const API_PATH = "/RPT/RPTValidationTable";

// Helper function to check if API response is successful
const checkSuccess = (data) => {
  if (!data) return false;
  if (data.success === true || data.success === "true") return true;
  if (data.status === "success" || data.status === "Success") return true;
  if (data.message && data.message.toLowerCase().includes("success")) return true;
  if (data.id || data.tdn || data.reference_number) return true;
  return false;
};

// REGISTRATION FUNCTIONS
export const scheduleInspection = async (registrationId, data) => {
  const response = await fetch(`${API_BASE}${API_PATH}/schedule_inspection.php`, {
    method: 'POST',
    headers: { 
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      registration_id: registrationId,
      ...data
    })
  });
  
  const result = await response.json();
  console.log('Inspection response:', result);
  
  if (!checkSuccess(result)) {
    throw new Error(result.message || result.error || "Failed to schedule inspection");
  }
  
  return result;
};

export const rejectApplication = async (registrationId, notes) => {
  const response = await fetch(`${API_BASE}${API_PATH}/reject_registration.php`, {
    method: 'POST',
    headers: { 
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      registration_id: registrationId,
      status: 'needs_correction',
      correction_notes: notes
    })
  });
  
  const result = await response.json();
  console.log('Rejection response:', result);
  
  if (!checkSuccess(result)) {
    throw new Error(result.message || result.error || "Failed to reject application");
  }
  
  return result;
};

export const markAsAssessed = async (registrationId) => {
  const response = await fetch(`${API_BASE}${API_PATH}/update_registration_status.php`, {
    method: 'POST',
    headers: { 
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      registration_id: registrationId,
      status: 'assessed'
    })
  });
  
  const result = await response.json();
  console.log('Mark as assessed response:', result);
  
  if (!checkSuccess(result)) {
    throw new Error(result.message || result.error || "Failed to update status");
  }
  
  return result;
};

export const markAsResubmitted = async (registrationId) => {
  const response = await fetch(`${API_BASE}${API_PATH}/update_registration_status.php`, {
    method: 'POST',
    headers: { 
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      registration_id: registrationId,
      status: 'resubmitted'
    })
  });
  
  const result = await response.json();
  console.log('Mark as resubmitted response:', result);
  
  if (!checkSuccess(result)) {
    throw new Error(result.message || result.error || "Failed to update status");
  }
  
  return result;
};

export const approveProperty = async (registrationId, taxData) => {
  const response = await fetch(`${API_BASE}${API_PATH}/approve_property.php`, {
    method: 'POST',
    headers: { 
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      registration_id: registrationId,
      ...taxData
    })
  });
  
  const result = await response.json();
  console.log('Approve property response:', result);
  
  if (!checkSuccess(result)) {
    throw new Error(result.message || result.error || "Failed to approve property");
  }
  
  return result;
};

// ASSESSMENT FUNCTIONS
export const saveAssessment = async (registrationId, assessmentData) => {
  const response = await fetch(`${API_BASE}${API_PATH}/assess_property.php`, {
    method: 'POST',
    headers: { 
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      registration_id: registrationId,
      ...assessmentData
    })
  });
  
  const result = await response.json();
  console.log('Assessment response:', result);
  
  if (!checkSuccess(result)) {
    throw new Error(result.message || result.error || "Failed to save assessment");
  }
  
  return result;
};

export const getAssessmentData = async (registrationId) => {
  const response = await fetch(`${API_BASE}${API_PATH}/get_assessment_data.php?id=${registrationId}`, {
    headers: { 'Accept': 'application/json' }
  });
  
  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }
  
  const result = await response.json();
  console.log('Assessment data response:', result);
  
  if (!checkSuccess(result)) {
    throw new Error(result.message || result.error || "Failed to get assessment data");
  }
  
  return result.data || result;
};

// CONFIGURATION FUNCTIONS
export const getLandConfigs = async () => {
  const response = await fetch(`${API_BASE}${API_PATH}/get_land_configurations.php`, {
    headers: { 'Accept': 'application/json' }
  });
  
  const result = await response.json();
  return checkSuccess(result) ? (result.data || result) : [];
};

export const getPropertyConfigs = async () => {
  const response = await fetch(`${API_BASE}${API_PATH}/get_property_configurations.php`, {
    headers: { 'Accept': 'application/json' }
  });
  
  const result = await response.json();
  return checkSuccess(result) ? (result.data || result) : [];
};

export const getTaxConfigs = async () => {
  const response = await fetch(`${API_BASE}${API_PATH}/get_tax_configurations.php`, {
    headers: { 'Accept': 'application/json' }
  });
  
  const result = await response.json();
  return checkSuccess(result) ? (result.data || result) : [];
};

export const getBuildingAssessmentLevels = async () => {
  const response = await fetch(`${API_BASE}${API_PATH}/get_building_assessment_levels.php`, {
    headers: { 'Accept': 'application/json' }
  });
  
  const result = await response.json();
  return checkSuccess(result) ? (result.data || result) : [];
};

// Default export
export default {
  scheduleInspection,
  rejectApplication,
  markAsAssessed,
  markAsResubmitted,
  approveProperty,
  saveAssessment,
  getAssessmentData,
  getLandConfigs,
  getPropertyConfigs,
  getTaxConfigs,
  getBuildingAssessmentLevels
};