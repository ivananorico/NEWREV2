import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import "./MarketConfig.css";

export default function MarketConfig() {
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState("stall-rights");

  return (
    <div className="market-config-container">
      <div className="config-header">
        <h1>Market Configuration</h1>
        <p>Manage stall rights classes and market sections</p>
      </div>

      <div className="config-tabs">
        <button 
          className={`tab-button ${activeTab === "stall-rights" ? "active" : ""}`}
          onClick={() => setActiveTab("stall-rights")}
        >
          🏪 Stall Rights Classes
        </button>
        <button 
          className={`tab-button ${activeTab === "sections" ? "active" : ""}`}
          onClick={() => setActiveTab("sections")}
        >
          📍 Market Sections
        </button>
      </div>

      <div className="tab-content">
        {activeTab === "stall-rights" && (
          <div className="tab-panel">
            <StallRightsConfig />
          </div>
        )}
        {activeTab === "sections" && (
          <div className="tab-panel">
            <SectionsConfig />
          </div>
        )}
      </div>

      <div className="config-footer">
        <button 
          className="back-button"
          onClick={() => navigate("/Market/MapCreator")}
        >
          ← Back to Map Creator
        </button>
      </div>
    </div>
  );
}

// Stall Rights Configuration Component
function StallRightsConfig() {
  const [stallClasses, setStallClasses] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [editingClass, setEditingClass] = useState(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [formData, setFormData] = useState({
    class_name: "",
    price: "",
    description: ""
  });

  const API_BASE = "http://localhost/revenue2/backend/Market/MapCreator";

  useEffect(() => {
    fetchStallClasses();
  }, []);

  const fetchStallClasses = async () => {
    try {
      setLoading(true);
      const res = await fetch(`${API_BASE}/get_stall_rights.php`);
      const data = await res.json();
      
      if (data.status === "success") {
        setStallClasses(data.classes);
      } else {
        throw new Error(data.message || "Failed to fetch stall classes");
      }
    } catch (err) {
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
        fetchStallClasses();
      } else {
        throw new Error(data.message || "Operation failed");
      }
    } catch (err) {
      alert("Error: " + err.message);
    }
  };

  const handleDelete = async (classId) => {
    if (!window.confirm("Are you sure you want to delete this stall class? This may affect existing stalls.")) {
      return;
    }

    try {
      const formData = new FormData();
      formData.append("class_id", classId);

      const res = await fetch(`${API_BASE}/delete_stall_rights.php`, {
        method: "POST",
        body: formData
      });

      const data = await res.json();
      
      if (data.status === "success") {
        alert("Stall class deleted successfully!");
        fetchStallClasses();
      } else {
        throw new Error(data.message || "Delete failed");
      }
    } catch (err) {
      alert("Delete error: " + err.message);
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
    return <div className="loading">Loading stall classes...</div>;
  }

  return (
    <div className="stall-rights-config">
      <div className="config-section-header">
        <h2>Stall Rights Classes Configuration</h2>
        <p>Manage different stall classes with their pricing and descriptions</p>
      </div>

      {error && (
        <div className="error-banner">
          {error}
          <button onClick={fetchStallClasses}>Retry</button>
        </div>
      )}

      <div className="action-bar">
        <button 
          className="btn-primary"
          onClick={() => setShowAddForm(!showAddForm)}
        >
          {showAddForm ? "Cancel" : "+ Add New Class"}
        </button>
      </div>

      {showAddForm && (
        <div className="form-card">
          <h3>{editingClass ? "Edit Stall Class" : "Add New Stall Class"}</h3>
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label>Class Name *</label>
              <input
                type="text"
                value={formData.class_name}
                onChange={(e) => setFormData({...formData, class_name: e.target.value})}
                placeholder="e.g., A, B, C, PREMIUM, STANDARD"
                maxLength="10"
                required
              />
            </div>

            <div className="form-group">
              <label>Price (₱) *</label>
              <input
                type="number"
                value={formData.price}
                onChange={(e) => setFormData({...formData, price: e.target.value})}
                placeholder="e.g., 15000.00"
                step="0.01"
                min="0"
                required
              />
            </div>

            <div className="form-group">
              <label>Description</label>
              <textarea
                value={formData.description}
                onChange={(e) => setFormData({...formData, description: e.target.value})}
                placeholder="e.g., Premium Location - High traffic area"
                rows="3"
              />
            </div>

            <div className="form-buttons">
              <button type="submit" className="btn-save">
                {editingClass ? "Update Class" : "Add Class"}
              </button>
              <button type="button" onClick={resetForm} className="btn-cancel">
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      <div className="classes-grid">
        {stallClasses.length === 0 ? (
          <div className="empty-state">
            <p>No stall classes found. Add your first stall class above.</p>
          </div>
        ) : (
          stallClasses.map((stallClass) => (
            <div key={stallClass.class_id} className="class-card">
              <div className="class-header">
                <h3>Class {stallClass.class_name}</h3>
                <span className="class-price">₱{parseFloat(stallClass.price).toLocaleString()}</span>
              </div>
              
              <div className="class-description">
                {stallClass.description || "No description provided"}
              </div>

              <div className="class-actions">
                <button 
                  className="btn-edit"
                  onClick={() => handleEdit(stallClass)}
                >
                  Edit
                </button>
                <button 
                  className="btn-delete"
                  onClick={() => handleDelete(stallClass.class_id)}
                >
                  Delete
                </button>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}

// Sections Configuration Component
function SectionsConfig() {
  const [sections, setSections] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [editingSection, setEditingSection] = useState(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [formData, setFormData] = useState({
    name: ""
  });

  const API_BASE = "http://localhost/revenue/backend/Market/MapCreator";

  useEffect(() => {
    fetchSections();
  }, []);

  const fetchSections = async () => {
    try {
      setLoading(true);
      const res = await fetch(`${API_BASE}/get_sections.php`);
      const data = await res.json();
      
      if (data.status === "success") {
        setSections(data.sections);
      } else {
        throw new Error(data.message || "Failed to fetch sections");
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!formData.name.trim()) {
      alert("Please enter a section name");
      return;
    }

    try {
      const url = editingSection 
        ? `${API_BASE}/update_section.php`
        : `${API_BASE}/add_section.php`;

      const formDataToSend = new FormData();
      if (editingSection) {
        formDataToSend.append("section_id", editingSection.id);
      }
      formDataToSend.append("name", formData.name.trim());

      const res = await fetch(url, {
        method: "POST",
        body: formDataToSend
      });

      const data = await res.json();
      
      if (data.status === "success") {
        alert(editingSection ? "Section updated successfully!" : "Section added successfully!");
        resetForm();
        fetchSections();
      } else {
        throw new Error(data.message || "Operation failed");
      }
    } catch (err) {
      alert("Error: " + err.message);
    }
  };

  const handleDelete = async (sectionId) => {
    if (!window.confirm("Are you sure you want to delete this section? This may affect existing stalls.")) {
      return;
    }

    try {
      const formData = new FormData();
      formData.append("section_id", sectionId);

      const res = await fetch(`${API_BASE}/delete_section.php`, {
        method: "POST",
        body: formData
      });

      const data = await res.json();
      
      if (data.status === "success") {
        alert("Section deleted successfully!");
        fetchSections();
      } else {
        throw new Error(data.message || "Delete failed");
      }
    } catch (err) {
      alert("Delete error: " + err.message);
    }
  };

  const handleEdit = (section) => {
    setEditingSection(section);
    setFormData({
      name: section.name
    });
    setShowAddForm(true);
  };

  const resetForm = () => {
    setEditingSection(null);
    setShowAddForm(false);
    setFormData({
      name: ""
    });
  };

  if (loading) {
    return <div className="loading">Loading sections...</div>;
  }

  return (
    <div className="sections-config">
      <div className="config-section-header">
        <h2>Market Sections Configuration</h2>
        <p>Manage different market sections for organizing stalls</p>
      </div>

      {error && (
        <div className="error-banner">
          {error}
          <button onClick={fetchSections}>Retry</button>
        </div>
      )}

      <div className="action-bar">
        <button 
          className="btn-primary"
          onClick={() => setShowAddForm(!showAddForm)}
        >
          {showAddForm ? "Cancel" : "+ Add New Section"}
        </button>
      </div>

      {showAddForm && (
        <div className="form-card">
          <h3>{editingSection ? "Edit Section" : "Add New Section"}</h3>
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label>Section Name *</label>
              <input
                type="text"
                value={formData.name}
                onChange={(e) => setFormData({...formData, name: e.target.value})}
                placeholder="e.g., Fruits Section, Meat Section, Electronics"
                required
              />
            </div>

            <div className="form-buttons">
              <button type="submit" className="btn-save">
                {editingSection ? "Update Section" : "Add Section"}
              </button>
              <button type="button" onClick={resetForm} className="btn-cancel">
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      <div className="sections-list">
        {sections.length === 0 ? (
          <div className="empty-state">
            <p>No sections found. Add your first section above.</p>
          </div>
        ) : (
          sections.map((section) => (
            <div key={section.id} className="section-card">
              <div className="section-info">
                <h3>{section.name}</h3>
                <span className="section-id">ID: {section.id}</span>
              </div>
              
              <div className="section-actions">
                <button 
                  className="btn-edit"
                  onClick={() => handleEdit(section)}
                >
                  Edit
                </button>
                <button 
                  className="btn-delete"
                  onClick={() => handleDelete(section.id)}
                >
                  Delete
                </button>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}