// Format date to readable string
export const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
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

// Format currency in Philippine Peso
export const formatCurrency = (amount) => {
  if (!amount || isNaN(amount)) return '₱0.00';
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount);
};

// Get document type display name
export const getDocumentTypeName = (type) => {
  const typeMap = {
    'barangay_certificate': 'Barangay Certificate',
    'ownership_proof': 'Proof of Ownership',
    'valid_id': 'Valid ID',
    'survey_plan': 'Survey Plan',
    'tax_declaration': 'Tax Declaration',
    'deed_of_sale': 'Deed of Sale',
    'transfer_certificate': 'Transfer Certificate',
    'other': 'Other Document'
  };
  return typeMap[type] || type.replace('_', ' ').toUpperCase();
};

// Calculate age from year built
export const calculateAge = (yearBuilt) => {
  if (!yearBuilt) return 0;
  const currentYear = new Date().getFullYear();
  return Math.max(0, currentYear - parseInt(yearBuilt));
};

// Calculate depreciation
export const calculateDepreciation = (age, rate) => {
  return Math.min(100, age * (rate || 0));
};

// Calculate assessed value
export const calculateAssessedValue = (marketValue, assessmentLevel) => {
  const market = parseFloat(marketValue) || 0;
  const level = parseFloat(assessmentLevel) || 0;
  return market * (level / 100);
};