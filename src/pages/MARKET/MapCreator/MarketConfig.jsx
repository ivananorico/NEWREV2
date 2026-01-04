import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";

export default function MarketConfig() {
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState("stall-rights");
  const [currentDate, setCurrentDate] = useState(new Date().toISOString().split('T')[0]);
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const [successMessage, setSuccessMessage] = useState(null);
  const [stats, setStats] = useState({
    stallClasses: 0,
    penalties: { total: 0, active: 0, expired: 0 },
    discounts: { total: 0, active: 0, expired: 0 }
  });

  const API_BASE = getApiBase();

  // Fetch statistics
  const fetchStats = async () => {
    try {
      setLoading(true);
      
      // Fetch stall classes count
      const stallRes = await fetch(`${API_BASE}/get_stall_rights.php`);
      const stallData = await stallRes.json();
      const stallCount = stallData.status === 'success' ? (stallData.classes || []).length : 0;

      // Fetch penalties
      const penaltyRes = await fetch(`${API_BASE}/penalty_config.php`);
      const penaltyData = await penaltyRes.json();
      const penalties = penaltyData.status === 'success' ? (penaltyData.configs || []) : [];
      
      // Calculate penalty stats
      const today = new Date();
      const penaltyStats = penalties.reduce((acc, penalty) => {
        const isExpired = penalty.expiration_date && new Date(penalty.expiration_date) < today;
        return {
          total: acc.total + 1,
          active: acc.active + (isExpired ? 0 : 1),
          expired: acc.expired + (isExpired ? 1 : 0)
        };
      }, { total: 0, active: 0, expired: 0 });

      // Fetch discounts
      const discountRes = await fetch(`${API_BASE}/discount_config.php`);
      const discountData = await discountRes.json();
      const discounts = discountData.status === 'success' ? (discountData.configs || []) : [];
      
      // Calculate discount stats
      const discountStats = discounts.reduce((acc, discount) => {
        const isExpired = discount.expiration_date && new Date(discount.expiration_date) < today;
        return {
          total: acc.total + 1,
          active: acc.active + (isExpired ? 0 : 1),
          expired: acc.expired + (isExpired ? 1 : 0)
        };
      }, { total: 0, active: 0, expired: 0 });

      setStats({
        stallClasses: stallCount,
        penalties: penaltyStats,
        discounts: discountStats
      });

    } catch (err) {
      console.error('Error fetching statistics:', err);
      // Set default stats on error
      setStats({
        stallClasses: 0,
        penalties: { total: 0, active: 0, expired: 0 },
        discounts: { total: 0, active: 0, expired: 0 }
      });
      setError('Failed to fetch data: ' + err.message);
    } finally {
      setLoading(false);
    }
  };

  // Refresh current tab
  const refreshCurrentTab = async () => {
    setLoading(true);
    try {
      await fetchStats();
      setSuccessMessage("Data refreshed successfully!");
      setTimeout(() => setSuccessMessage(null), 3000);
    } catch (error) {
      console.error('Error refreshing data:', error);
      setError('Failed to refresh data: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchStats();
  }, []);

  return (
    <div className='mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg'>
      <div className="flex justify-between items-center mb-6">
        <div>
          <h1 className="text-2xl font-bold">Market Configuration</h1>
          <p className="text-gray-600 dark:text-gray-400 mt-1">Manage all market settings in one place</p>
        </div>
        <button
          onClick={() => navigate("/Market/MapCreator")}
          className="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors flex items-center gap-2"
        >
          ← Back to Map Creator
        </button>
      </div>

      {/* Success Message */}
      {successMessage && (
        <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:border-green-800">
          <div className="flex items-center">
            <div className="text-green-600 font-medium">Success:</div>
            <div className="ml-2 text-green-700 dark:text-green-300">{successMessage}</div>
            <button 
              onClick={() => setSuccessMessage(null)}
              className="ml-auto text-green-600 hover:text-green-800 dark:text-green-400"
            >
              ×
            </button>
          </div>
        </div>
      )}

      {/* Error Display */}
      {error && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/20 dark:border-red-800">
          <div className="flex items-center">
            <div className="text-red-600 font-medium">Error:</div>
            <div className="ml-2 text-red-700 dark:text-red-300">{error}</div>
            <button 
              onClick={() => setError(null)}
              className="ml-auto text-red-600 hover:text-red-800 dark:text-red-400"
            >
              ×
            </button>
          </div>
        </div>
      )}

      {/* Statistics Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div className="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
          <div className="flex items-center gap-3">
            <div className="bg-blue-100 dark:bg-blue-800 p-2 rounded-lg">
              <span className="text-blue-600 dark:text-blue-300 text-xl">🏪</span>
            </div>
            <div>
              <h3 className="font-semibold text-blue-800 dark:text-blue-300">Stall Classes</h3>
              <p className="text-2xl font-bold">{stats.stallClasses}</p>
              <p className="text-sm text-blue-600 dark:text-blue-400">A, B, C Classes</p>
            </div>
          </div>
        </div>
        <div className="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg">
          <div className="flex items-center gap-3">
            <div className="bg-red-100 dark:bg-red-800 p-2 rounded-lg">
              <span className="text-red-600 dark:text-red-300 text-xl">⚠️</span>
            </div>
            <div>
              <h3 className="font-semibold text-red-800 dark:text-red-300">Penalties</h3>
              <p className="text-2xl font-bold">{stats.penalties.total}</p>
              <p className="text-sm text-red-600 dark:text-red-400">
                Active: {stats.penalties.active} | Expired: {stats.penalties.expired}
              </p>
            </div>
          </div>
        </div>
        <div className="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
          <div className="flex items-center gap-3">
            <div className="bg-green-100 dark:bg-green-800 p-2 rounded-lg">
              <span className="text-green-600 dark:text-green-300 text-xl">💸</span>
            </div>
            <div>
              <h3 className="font-semibold text-green-800 dark:text-green-300">Discounts</h3>
              <p className="text-2xl font-bold">{stats.discounts.total}</p>
              <p className="text-sm text-green-600 dark:text-green-400">
                Active: {stats.discounts.active} | Expired: {stats.discounts.expired}
              </p>
            </div>
          </div>
        </div>
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

      {/* Tab Navigation */}
      <div className="mb-6 border-b border-gray-200 dark:border-slate-700">
        <nav className="-mb-px flex space-x-8">
          {['stall-rights', 'penalty', 'discount'].map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`py-2 px-1 border-b-2 font-medium text-sm flex items-center gap-2 ${
                activeTab === tab
                  ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                  : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'
              }`}
            >
              {tab === 'stall-rights' && '🏪 Stall Rights'}
              {tab === 'penalty' && '⚠️ Penalty Config'}
              {tab === 'discount' && '💸 Discount Config'}
            </button>
          ))}
        </nav>
      </div>

      {/* Tab Content */}
      <div className="tab-content">
        {activeTab === "stall-rights" && <StallRightsConfig refreshStats={fetchStats} />}
        {activeTab === "penalty" && <PenaltyConfig refreshStats={fetchStats} />}
        {activeTab === "discount" && <DiscountConfig refreshStats={fetchStats} />}
      </div>
    </div>
  );
}

// Stall Rights Configuration Component
function StallRightsConfig({ refreshStats }) {
  const [stallClasses, setStallClasses] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [editingClass, setEditingClass] = useState(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [formData, setFormData] = useState({
    class_name: "",
    price: "",
    description: ""
  });
  const [submitting, setSubmitting] = useState(false);

  const API_BASE = getApiBase();

  useEffect(() => {
    fetchStallClasses();
  }, []);

  const fetchStallClasses = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const res = await fetch(`${API_BASE}/get_stall_rights.php`);
      
      if (!res.ok) {
        throw new Error(`HTTP error! Status: ${res.status}`);
      }
      
      const data = await res.json();
      
      if (data.status === "success") {
        const sortedClasses = data.classes.sort((a, b) => parseFloat(b.price) - parseFloat(a.price));
        setStallClasses(sortedClasses || []);
      } else {
        throw new Error(data.message || "Failed to fetch stall classes");
      }
    } catch (err) {
      console.error('Error fetching stall classes:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!formData.class_name || !formData.price) {
      alert("Please fill in all required fields");
      return;
    }

    try {
      setSubmitting(true);
      
      const url = editingClass 
        ? `${API_BASE}/update_stall_rights.php`
        : `${API_BASE}/add_stall_rights.php`;

      const formDataToSend = new FormData();
      if (editingClass) {
        formDataToSend.append("class_id", editingClass.class_id);
      }
      formDataToSend.append("class_name", formData.class_name.toUpperCase());
      formDataToSend.append("price", formData.price);
      formDataToSend.append("description", formData.description);
      
      const res = await fetch(url, {
        method: "POST",
        body: formDataToSend
      });

      const data = await res.json();
      
      if (data.status === "success") {
        alert(editingClass ? "Stall class updated successfully!" : "Stall class added successfully!");
        resetForm();
        await fetchStallClasses();
        await refreshStats();
      } else {
        throw new Error(data.message || "Operation failed");
      }
    } catch (err) {
      console.error('Save error:', err);
      alert("Error: " + err.message);
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (classId) => {
    if (!window.confirm("Are you sure you want to delete this stall class? This may affect existing stalls.")) {
      return;
    }

    try {
      setSubmitting(true);
      const formData = new FormData();
      formData.append("class_id", classId);
      
      const res = await fetch(`${API_BASE}/delete_stall_rights.php`, {
        method: "POST",
        body: formData
      });

      const data = await res.json();
      
      if (data.status === "success") {
        alert("Stall class deleted successfully!");
        await fetchStallClasses();
        await refreshStats();
      } else {
        throw new Error(data.message || "Delete failed");
      }
    } catch (err) {
      console.error('Delete error:', err);
      alert("Delete error: " + err.message);
    } finally {
      setSubmitting(false);
    }
  };

  const handleEdit = (stallClass) => {
    setEditingClass(stallClass);
    setFormData({
      class_name: stallClass.class_name,
      price: stallClass.price,
      description: stallClass.description || ""
    });
    setShowAddForm(true);
  };

  const resetForm = () => {
    setEditingClass(null);
    setShowAddForm(false);
    setFormData({
      class_name: "",
      price: "",
      description: ""
    });
  };

  if (loading) {
    return (
      <div className="text-center py-8">
        <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <p className="mt-2 text-gray-600">Loading stall classes...</p>
      </div>
    );
  }

  return (
    <div className="config-section">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h2 className="text-xl font-semibold">🏪 Stall Rights Classes</h2>
          <p className="text-gray-600 dark:text-gray-400 mt-1">Manage different stall classes with their pricing and descriptions</p>
        </div>
        <div className="flex gap-2">
          <button 
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors flex items-center gap-2"
            onClick={() => setShowAddForm(!showAddForm)}
            disabled={submitting}
          >
            {showAddForm ? "✕ Cancel" : "+ Add New Class"}
          </button>
          <button 
            onClick={fetchStallClasses}
            className="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition-colors flex items-center gap-2"
            disabled={loading || submitting}
          >
            ↻ Refresh
          </button>
        </div>
      </div>

      {showAddForm && (
        <div className="mb-8 p-6 border rounded-lg dark:border-slate-700">
          <h3 className="text-lg font-semibold mb-4">
            {editingClass ? "✏️ Edit Stall Class" : "➕ Add New Stall Class"}
          </h3>
          <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium mb-2">Class Name *</label>
              <input
                type="text"
                value={formData.class_name}
                onChange={(e) => setFormData({...formData, class_name: e.target.value})}
                className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                placeholder="e.g., A, B, C"
                maxLength="10"
                required
                disabled={submitting}
              />
            </div>

            <div>
              <label className="block text-sm font-medium mb-2">Price (₱) *</label>
              <input
                type="number"
                value={formData.price}
                onChange={(e) => setFormData({...formData, price: e.target.value})}
                className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                placeholder="e.g., 15000.00"
                step="0.01"
                min="0"
                required
                disabled={submitting}
              />
            </div>

            <div className="md:col-span-2">
              <label className="block text-sm font-medium mb-2">Description</label>
              <textarea
                value={formData.description}
                onChange={(e) => setFormData({...formData, description: e.target.value})}
                rows="2"
                className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                placeholder="e.g., Premium Location - High traffic area"
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
                {submitting ? 'Saving...' : editingClass ? 'Update Class' : 'Add Class'}
              </button>
              <button
                type="button"
                onClick={resetForm}
                disabled={submitting}
                className="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition-colors disabled:opacity-50"
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Stall Classes Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {stallClasses.length === 0 ? (
          <div className="col-span-3 text-center py-8 text-gray-500">
            No stall classes found. Add your first stall class above.
          </div>
        ) : (
          stallClasses.map((stallClass) => (
            <div key={stallClass.class_id} className="border rounded-lg p-6 hover:shadow-lg transition-shadow dark:border-slate-700">
              <div className="flex justify-between items-start mb-4">
                <div>
                  <div className="text-sm text-gray-500 dark:text-gray-400 mb-1">Class</div>
                  <h3 className="text-xl font-bold">Class {stallClass.class_name}</h3>
                </div>
                <div className="bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300 font-bold text-lg px-3 py-1 rounded">
                  ₱{parseFloat(stallClass.price || 0).toLocaleString()}
                </div>
              </div>
              
              <div className="mb-4">
                <div className="text-sm text-gray-500 dark:text-gray-400 mb-1">Description</div>
                <p className="text-gray-700 dark:text-gray-300">{stallClass.description || "No description provided"}</p>
              </div>

              <div className="flex justify-between items-center mb-4">
                <div className="text-sm text-gray-500 dark:text-gray-400">
                  ID: {stallClass.class_id}
                </div>
                <span className="px-2 py-1 rounded-full text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                  Active
                </span>
              </div>

              <div className="flex gap-2">
                <button 
                  className="flex-1 bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 transition-colors flex items-center justify-center gap-2"
                  onClick={() => handleEdit(stallClass)}
                  disabled={submitting}
                >
                  ✏️ Edit
                </button>
                <button 
                  className="flex-1 bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition-colors flex items-center justify-center gap-2"
                  onClick={() => handleDelete(stallClass.class_id)}
                  disabled={submitting}
                >
                  🗑️ Delete
                </button>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}

// Penalty Configuration Component
function PenaltyConfig({ refreshStats }) {
  const [penalties, setPenalties] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [editingPenalty, setEditingPenalty] = useState(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [formData, setFormData] = useState({
    penalty_percent: "",
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: "",
    remarks: ""
  });
  const [submitting, setSubmitting] = useState(false);

  const API_BASE = getApiBase();

  useEffect(() => {
    fetchPenalties();
  }, []);

  const fetchPenalties = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const res = await fetch(`${API_BASE}/penalty_config.php`);
      
      if (!res.ok) {
        throw new Error(`HTTP error! Status: ${res.status}`);
      }
      
      const data = await res.json();
      
      if (data.status === "success") {
        const sortedPenalties = data.configs.sort((a, b) => 
          new Date(b.effective_date) - new Date(a.effective_date)
        );
        setPenalties(sortedPenalties || []);
      } else {
        throw new Error(data.message || "Failed to fetch penalty config");
      }
    } catch (err) {
      console.error('Error fetching penalties:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!formData.penalty_percent || !formData.effective_date) {
      alert("Please fill in all required fields");
      return;
    }

    try {
      setSubmitting(true);
      const action = editingPenalty ? 'update' : 'add';
      const payload = {
        action: action,
        penalty_percent: formData.penalty_percent,
        effective_date: formData.effective_date,
        expiration_date: formData.expiration_date || null,
        remarks: formData.remarks
      };

      if (editingPenalty) {
        payload.id = editingPenalty.id;
      }
      
      const res = await fetch(`${API_BASE}/penalty_config.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      
      if (data.status === "success") {
        alert(editingPenalty ? "Penalty updated successfully!" : "Penalty added successfully!");
        resetForm();
        await fetchPenalties();
        await refreshStats();
      } else {
        throw new Error(data.message || "Operation failed");
      }
    } catch (err) {
      console.error('Save error:', err);
      alert("Error: " + err.message);
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm("Are you sure you want to delete this penalty configuration?")) {
      return;
    }

    try {
      setSubmitting(true);
      const payload = {
        action: 'delete',
        id: id
      };
      
      const res = await fetch(`${API_BASE}/penalty_config.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      
      if (data.status === "success") {
        alert("Penalty deleted successfully!");
        await fetchPenalties();
        await refreshStats();
      } else {
        throw new Error(data.message || "Delete failed");
      }
    } catch (err) {
      console.error('Delete error:', err);
      alert("Delete error: " + err.message);
    } finally {
      setSubmitting(false);
    }
  };

  const handleExpire = async (id) => {
    if (!window.confirm("Are you sure you want to expire this penalty configuration?")) {
      return;
    }

    try {
      setSubmitting(true);
      const today = new Date().toISOString().split('T')[0];
      const payload = {
        action: 'update',
        id: id,
        expiration_date: today
      };
      
      const res = await fetch(`${API_BASE}/penalty_config.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      
      if (data.status === "success") {
        alert("Penalty expired successfully!");
        await fetchPenalties();
        await refreshStats();
      } else {
        throw new Error(data.message || "Expire failed");
      }
    } catch (err) {
      console.error('Expire error:', err);
      alert("Expire error: " + err.message);
    } finally {
      setSubmitting(false);
    }
  };

  const handleEdit = (penalty) => {
    setEditingPenalty(penalty);
    setFormData({
      penalty_percent: penalty.penalty_percent,
      effective_date: penalty.effective_date,
      expiration_date: penalty.expiration_date || "",
      remarks: penalty.remarks || ""
    });
    setShowAddForm(true);
  };

  const resetForm = () => {
    setEditingPenalty(null);
    setShowAddForm(false);
    setFormData({
      penalty_percent: "",
      effective_date: new Date().toISOString().split('T')[0],
      expiration_date: "",
      remarks: ""
    });
  };

  const isActive = (penalty) => {
    const today = new Date().toISOString().split('T')[0];
    return (
      penalty.effective_date <= today && 
      (!penalty.expiration_date || penalty.expiration_date >= today)
    );
  };

  if (loading) {
    return (
      <div className="text-center py-8">
        <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <p className="mt-2 text-gray-600">Loading penalty configuration...</p>
      </div>
    );
  }

  return (
    <div className="config-section">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h2 className="text-xl font-semibold">⚠️ Penalty Configuration</h2>
          <p className="text-gray-600 dark:text-gray-400 mt-1">Manage penalty percentages and their effective dates</p>
        </div>
        <div className="flex gap-2">
          <button 
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors flex items-center gap-2"
            onClick={() => setShowAddForm(!showAddForm)}
            disabled={submitting}
          >
            {showAddForm ? "✕ Cancel" : "+ Add New Penalty"}
          </button>
          <button 
            onClick={fetchPenalties}
            className="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition-colors flex items-center gap-2"
            disabled={loading || submitting}
          >
            ↻ Refresh
          </button>
        </div>
      </div>

      {showAddForm && (
        <div className="mb-8 p-6 border rounded-lg dark:border-slate-700">
          <h3 className="text-lg font-semibold mb-4">
            {editingPenalty ? "✏️ Edit Penalty" : "➕ Add New Penalty"}
          </h3>
          <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium mb-2">Penalty Percentage (%) *</label>
              <input
                type="number"
                step="0.01"
                min="0"
                max="100"
                value={formData.penalty_percent}
                onChange={(e) => setFormData({...formData, penalty_percent: e.target.value})}
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
                value={formData.effective_date}
                onChange={(e) => setFormData({...formData, effective_date: e.target.value})}
                className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                required
                disabled={submitting}
              />
            </div>

            <div>
              <label className="block text-sm font-medium mb-2">Expiration Date</label>
              <input
                type="date"
                value={formData.expiration_date}
                onChange={(e) => setFormData({...formData, expiration_date: e.target.value})}
                className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                disabled={submitting}
              />
              <p className="text-xs text-gray-500 mt-1">Leave empty if no expiration</p>
            </div>

            <div className="md:col-span-2">
              <label className="block text-sm font-medium mb-2">Remarks</label>
              <textarea
                value={formData.remarks}
                onChange={(e) => setFormData({...formData, remarks: e.target.value})}
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
                {submitting ? 'Saving...' : editingPenalty ? 'Update Penalty' : 'Add Penalty'}
              </button>
              <button
                type="button"
                onClick={resetForm}
                disabled={submitting}
                className="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition-colors disabled:opacity-50"
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Penalties Table */}
      <div>
        <h3 className="text-lg font-semibold mb-4">
          Penalty Configurations ({penalties.length})
        </h3>
        
        {penalties.length === 0 ? (
          <div className="text-center py-8 text-gray-500">
            No penalty configurations found.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full border-collapse border border-gray-300 dark:border-slate-700">
              <thead>
                <tr className="bg-gray-100 dark:bg-slate-800">
                  <th className="border p-3 text-left">Penalty Rate</th>
                  <th className="border p-3 text-left">Effective Date</th>
                  <th className="border p-3 text-left">Expiration Date</th>
                  <th className="border p-3 text-left">Status</th>
                  <th className="border p-3 text-left">Actions</th>
                </tr>
              </thead>
              <tbody>
                {penalties.map((penalty) => {
                  const isExpired = isActive(penalty) ? false : true;
                  return (
                    <tr 
                      key={penalty.id} 
                      className={`hover:bg-gray-50 dark:hover:bg-slate-800 ${
                        isExpired ? 'bg-gray-50 dark:bg-slate-800/50 text-gray-500' : ''
                      }`}
                    >
                      <td className="border p-3">
                        <div className="font-medium text-lg">{penalty.penalty_percent}%</div>
                        {penalty.remarks && (
                          <div className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {penalty.remarks}
                          </div>
                        )}
                      </td>
                      <td className="border p-3">{penalty.effective_date}</td>
                      <td className="border p-3">{penalty.expiration_date || '-'}</td>
                      <td className="border p-3">
                        <span className={`px-3 py-1 rounded-full text-sm ${
                          !isExpired 
                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' 
                            : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                        }`}>
                          {isExpired ? 'Expired' : 'Active'}
                        </span>
                      </td>
                      <td className="border p-3">
                        <div className="flex gap-2">
                          <button
                            onClick={() => handleEdit(penalty)}
                            className="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 transition-colors flex items-center gap-2"
                            disabled={isExpired || submitting}
                          >
                            ✏️ Edit
                          </button>
                          {!isExpired && (
                            <button
                              onClick={() => handleExpire(penalty.id)}
                              className="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600 transition-colors"
                              disabled={submitting}
                            >
                              Expire
                            </button>
                          )}
                          <button
                            onClick={() => handleDelete(penalty.id)}
                            className="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition-colors flex items-center gap-2"
                            disabled={submitting}
                          >
                            🗑️ Delete
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
    </div>
  );
}

// Discount Configuration Component
function DiscountConfig({ refreshStats }) {
  const [discounts, setDiscounts] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [editingDiscount, setEditingDiscount] = useState(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [formData, setFormData] = useState({
    discount_percent: "",
    effective_date: new Date().toISOString().split('T')[0],
    expiration_date: "",
    remarks: ""
  });
  const [submitting, setSubmitting] = useState(false);

  const API_BASE = getApiBase();

  useEffect(() => {
    fetchDiscounts();
  }, []);

  const fetchDiscounts = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const res = await fetch(`${API_BASE}/discount_config.php`);
      
      if (!res.ok) {
        throw new Error(`HTTP error! Status: ${res.status}`);
      }
      
      const data = await res.json();
      
      if (data.status === "success") {
        const sortedDiscounts = data.configs.sort((a, b) => 
          new Date(b.effective_date) - new Date(a.effective_date)
        );
        setDiscounts(sortedDiscounts || []);
      } else {
        throw new Error(data.message || "Failed to fetch discount config");
      }
    } catch (err) {
      console.error('Error fetching discounts:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!formData.discount_percent || !formData.effective_date) {
      alert("Please fill in all required fields");
      return;
    }

    try {
      setSubmitting(true);
      const action = editingDiscount ? 'update' : 'add';
      const payload = {
        action: action,
        discount_percent: formData.discount_percent,
        effective_date: formData.effective_date,
        expiration_date: formData.expiration_date || null,
        remarks: formData.remarks
      };

      if (editingDiscount) {
        payload.id = editingDiscount.id;
      }
      
      const res = await fetch(`${API_BASE}/discount_config.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      
      if (data.status === "success") {
        alert(editingDiscount ? "Discount updated successfully!" : "Discount added successfully!");
        resetForm();
        await fetchDiscounts();
        await refreshStats();
      } else {
        throw new Error(data.message || "Operation failed");
      }
    } catch (err) {
      console.error('Save error:', err);
      alert("Error: " + err.message);
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm("Are you sure you want to delete this discount configuration?")) {
      return;
    }

    try {
      setSubmitting(true);
      const payload = {
        action: 'delete',
        id: id
      };
      
      const res = await fetch(`${API_BASE}/discount_config.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      
      if (data.status === "success") {
        alert("Discount deleted successfully!");
        await fetchDiscounts();
        await refreshStats();
      } else {
        throw new Error(data.message || "Delete failed");
      }
    } catch (err) {
      console.error('Delete error:', err);
      alert("Delete error: " + err.message);
    } finally {
      setSubmitting(false);
    }
  };

  const handleExpire = async (id) => {
    if (!window.confirm("Are you sure you want to expire this discount configuration?")) {
      return;
    }

    try {
      setSubmitting(true);
      const today = new Date().toISOString().split('T')[0];
      const payload = {
        action: 'update',
        id: id,
        expiration_date: today
      };
      
      const res = await fetch(`${API_BASE}/discount_config.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      
      if (data.status === "success") {
        alert("Discount expired successfully!");
        await fetchDiscounts();
        await refreshStats();
      } else {
        throw new Error(data.message || "Expire failed");
      }
    } catch (err) {
      console.error('Expire error:', err);
      alert("Expire error: " + err.message);
    } finally {
      setSubmitting(false);
    }
  };

  const handleEdit = (discount) => {
    setEditingDiscount(discount);
    setFormData({
      discount_percent: discount.discount_percent,
      effective_date: discount.effective_date,
      expiration_date: discount.expiration_date || "",
      remarks: discount.remarks || ""
    });
    setShowAddForm(true);
  };

  const resetForm = () => {
    setEditingDiscount(null);
    setShowAddForm(false);
    setFormData({
      discount_percent: "",
      effective_date: new Date().toISOString().split('T')[0],
      expiration_date: "",
      remarks: ""
    });
  };

  const isActive = (discount) => {
    const today = new Date().toISOString().split('T')[0];
    return (
      discount.effective_date <= today && 
      (!discount.expiration_date || discount.expiration_date >= today)
    );
  };

  if (loading) {
    return (
      <div className="text-center py-8">
        <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <p className="mt-2 text-gray-600">Loading discount configuration...</p>
      </div>
    );
  }

  return (
    <div className="config-section">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h2 className="text-xl font-semibold">💸 Discount Configuration</h2>
          <p className="text-gray-600 dark:text-gray-400 mt-1">Manage discount percentages and their effective dates</p>
        </div>
        <div className="flex gap-2">
          <button 
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors flex items-center gap-2"
            onClick={() => setShowAddForm(!showAddForm)}
            disabled={submitting}
          >
            {showAddForm ? "✕ Cancel" : "+ Add New Discount"}
          </button>
          <button 
            onClick={fetchDiscounts}
            className="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition-colors flex items-center gap-2"
            disabled={loading || submitting}
          >
            ↻ Refresh
          </button>
        </div>
      </div>

      {showAddForm && (
        <div className="mb-8 p-6 border rounded-lg dark:border-slate-700">
          <h3 className="text-lg font-semibold mb-4">
            {editingDiscount ? "✏️ Edit Discount" : "➕ Add New Discount"}
          </h3>
          <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium mb-2">Discount Percentage (%) *</label>
              <input
                type="number"
                step="0.01"
                min="0"
                max="100"
                value={formData.discount_percent}
                onChange={(e) => setFormData({...formData, discount_percent: e.target.value})}
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
                value={formData.effective_date}
                onChange={(e) => setFormData({...formData, effective_date: e.target.value})}
                className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                required
                disabled={submitting}
              />
            </div>

            <div>
              <label className="block text-sm font-medium mb-2">Expiration Date</label>
              <input
                type="date"
                value={formData.expiration_date}
                onChange={(e) => setFormData({...formData, expiration_date: e.target.value})}
                className="w-full p-2 border border-gray-300 rounded dark:bg-slate-800 dark:border-slate-600"
                disabled={submitting}
              />
              <p className="text-xs text-gray-500 mt-1">Leave empty if no expiration</p>
            </div>

            <div className="md:col-span-2">
              <label className="block text-sm font-medium mb-2">Remarks</label>
              <textarea
                value={formData.remarks}
                onChange={(e) => setFormData({...formData, remarks: e.target.value})}
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
                {submitting ? 'Saving...' : editingDiscount ? 'Update Discount' : 'Add Discount'}
              </button>
              <button
                type="button"
                onClick={resetForm}
                disabled={submitting}
                className="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition-colors disabled:opacity-50"
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Discounts Table */}
      <div>
        <h3 className="text-lg font-semibold mb-4">
          Discount Configurations ({discounts.length})
        </h3>
        
        {discounts.length === 0 ? (
          <div className="text-center py-8 text-gray-500">
            No discount configurations found.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full border-collapse border border-gray-300 dark:border-slate-700">
              <thead>
                <tr className="bg-gray-100 dark:bg-slate-800">
                  <th className="border p-3 text-left">Discount Rate</th>
                  <th className="border p-3 text-left">Effective Date</th>
                  <th className="border p-3 text-left">Expiration Date</th>
                  <th className="border p-3 text-left">Status</th>
                  <th className="border p-3 text-left">Actions</th>
                </tr>
              </thead>
              <tbody>
                {discounts.map((discount) => {
                  const isExpired = isActive(discount) ? false : true;
                  return (
                    <tr 
                      key={discount.id} 
                      className={`hover:bg-gray-50 dark:hover:bg-slate-800 ${
                        isExpired ? 'bg-gray-50 dark:bg-slate-800/50 text-gray-500' : ''
                      }`}
                    >
                      <td className="border p-3">
                        <div className="font-medium text-lg">{discount.discount_percent}%</div>
                        {discount.remarks && (
                          <div className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {discount.remarks}
                          </div>
                        )}
                      </td>
                      <td className="border p-3">{discount.effective_date}</td>
                      <td className="border p-3">{discount.expiration_date || '-'}</td>
                      <td className="border p-3">
                        <span className={`px-3 py-1 rounded-full text-sm ${
                          !isExpired 
                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' 
                            : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                        }`}>
                          {isExpired ? 'Expired' : 'Active'}
                        </span>
                      </td>
                      <td className="border p-3">
                        <div className="flex gap-2">
                          <button
                            onClick={() => handleEdit(discount)}
                            className="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 transition-colors flex items-center gap-2"
                            disabled={isExpired || submitting}
                          >
                            ✏️ Edit
                          </button>
                          {!isExpired && (
                            <button
                              onClick={() => handleExpire(discount.id)}
                              className="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600 transition-colors"
                              disabled={submitting}
                            >
                              Expire
                            </button>
                          )}
                          <button
                            onClick={() => handleDelete(discount.id)}
                            className="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition-colors flex items-center gap-2"
                            disabled={submitting}
                          >
                            🗑️ Delete
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
    </div>
  );
}

// Helper function for API base URL - UPDATED with your pattern
function getApiBase() {
  const isProduction = window.location.hostname.includes('goserveph.com'); // Your production domain
  
  if (isProduction) {
    return "/backend/Market/MapCreator";
  } else {
    // Local/development
    return "http://localhost/revenue2/backend/Market/MapCreator";
  }
}