import React, { useState, useRef, useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";
import "./MapEditor.css";

export default function MapEditor() {
  const { id } = useParams();
  const navigate = useNavigate();
  
  const [mapData, setMapData] = useState(null);
  const [stalls, setStalls] = useState([]);
  const [stallClasses, setStallClasses] = useState([]);
  const [sections, setSections] = useState([]);
  const [stallCount, setStallCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [modalOpen, setModalOpen] = useState(false);
  const [selectedStallIndex, setSelectedStallIndex] = useState(null);
  const [modalPos, setModalPos] = useState({ x: 0, y: 0 });

  // Resize state
  const [resizing, setResizing] = useState(false);
  const [resizeDirection, setResizeDirection] = useState(null);
  const [activeStallIndex, setActiveStallIndex] = useState(null);

  // Market section filter states
  const [selectedSection, setSelectedSection] = useState("all");

  const marketMapRef = useRef(null);
  const modalRef = useRef(null);
  
  const API_BASE = "http://localhost/revenue2/backend/Market/MapCreator";

  // Fetch map, stalls, stall classes, and sections data
  useEffect(() => {
    fetchMapData();
    fetchStallClasses();
    fetchSections();
  }, [id]);

  const fetchMapData = async () => {
    try {
      console.log('Fetching map data from:', `${API_BASE}/map_display.php?map_id=${id}`);
      const res = await fetch(`${API_BASE}/map_display.php?map_id=${id}`);
      if (!res.ok) throw new Error(`Network error: ${res.status}`);
      
      const data = await res.json();
      console.log('Map data response:', data);
      
      if (data.status === "success") {
        setMapData(data.map);
        // Ensure stalls have pixel dimensions, use defaults if not present
        const stallsWithPixelDims = (data.stalls || []).map(stall => ({
          ...stall,
          pixel_width: stall.pixel_width || 80,
          pixel_height: stall.pixel_height || 60
        }));
        setStalls(stallsWithPixelDims);
        setStallCount(stallsWithPixelDims.length);
      } else {
        throw new Error(data.message || "Failed to fetch map data");
      }
    } catch (err) {
      console.error('Error fetching map data:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const fetchStallClasses = async () => {
    try {
      console.log('Fetching stall classes from:', `${API_BASE}/get_stall_rights.php`);
      const res = await fetch(`${API_BASE}/get_stall_rights.php`);
      const data = await res.json();
      console.log('Stall classes response:', data);
      
      if (data.status === "success") {
        setStallClasses(data.classes);
      } else {
        console.error("Failed to fetch stall classes from database");
        setStallClasses([]);
      }
    } catch (err) {
      console.error("Error fetching stall classes:", err);
      setStallClasses([]);
    }
  };

  const fetchSections = async () => {
    try {
      console.log('Fetching sections from:', `${API_BASE}/get_sections.php`);
      const res = await fetch(`${API_BASE}/get_sections.php`);
      const data = await res.json();
      console.log('Sections response:', data);
      
      if (data.status === "success") {
        setSections(data.sections);
      } else {
        console.error("Failed to fetch sections from database");
        setSections([]);
      }
    } catch (err) {
      console.error("Error fetching sections:", err);
      setSections([]);
    }
  };

  // Add a new stall with pixel dimensions - SAME AS CREATOR
  const addStall = () => {
    const newCount = stallCount + 1;
    
    setStallCount(newCount);
    setStalls([
      ...stalls,
      { 
        name: `Stall ${newCount}`, 
        pos_x: 50, 
        pos_y: 50, 
        pixel_width: 80,    // Visual width in pixels
        pixel_height: 60,   // Visual height in pixels
        status: "available", 
        class_id: 3, // Default to Class C - SAME AS CREATOR
        class_name: "C",
        price: 5000.00, // Default price for Class C - SAME AS CREATOR
        height: 0, 
        length: 0, 
        width: 0,
        section_id: sections.length > 0 ? sections[0].id : null,
        isNew: true
      }
    ]);
  };

  // Delete a stall
  const deleteStall = async (index) => {
    const stall = stalls[index];
    if (window.confirm(`Delete ${stall.name}?`)) {
      if (stall.id && !stall.isNew) {
        try {
          const formData = new FormData();
          formData.append("stall_id", stall.id);

          const res = await fetch(`${API_BASE}/delete_stall.php`, {
            method: "POST",
            body: formData
          });
          
          const data = await res.json();
          if (data.status !== "success") {
            throw new Error(data.message || "Failed to delete stall");
          }
        } catch (err) {
          alert("Delete failed: " + err.message);
          return;
        }
      }
      
      const updated = stalls.filter((_, i) => i !== index);
      setStalls(updated);
      setStallCount(updated.length);
    }
  };

  // Toggle maintenance status
  const toggleMaintenance = (index) => {
    const updated = [...stalls];
    const stall = updated[index];
    
    if (stall.status === "maintenance") {
      stall.status = "available";
    } else {
      stall.status = "maintenance";
    }
    
    setStalls(updated);
  };

  // Save updates to backend
  const saveUpdates = async () => {
    try {
      console.log('Saving updates to:', `${API_BASE}/update_map.php`);
      const res = await fetch(`${API_BASE}/update_map.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          map_id: id,
          stalls: stalls
        })
      });
      
      const data = await res.json();
      if (data.status === "success") {
        alert("Map updated successfully!");
        fetchMapData();
      } else {
        throw new Error(data.message || "Failed to update map");
      }
    } catch (err) {
      alert("Update failed: " + err.message);
    }
  };

  // Drag stalls - FIXED POSITION CALCULATION
  const handleDrag = (e, index) => {
    if (resizing) return;
    
    const containerRect = marketMapRef.current.getBoundingClientRect();
    const x = e.clientX - containerRect.left - (stalls[index].pixel_width / 2);
    const y = e.clientY - containerRect.top - (stalls[index].pixel_height / 2);

    const updated = [...stalls];
    updated[index].pos_x = Math.max(0, Math.min(containerRect.width - updated[index].pixel_width, x));
    updated[index].pos_y = Math.max(0, Math.min(containerRect.height - updated[index].pixel_height, y));
    setStalls(updated);
  };

  // Resize stalls - FIXED POSITION CALCULATION
  const handleResize = (e, index, direction) => {
    const containerRect = marketMapRef.current.getBoundingClientRect();
    const mouseX = e.clientX - containerRect.left;
    const mouseY = e.clientY - containerRect.top;
    
    const updated = [...stalls];
    const stall = updated[index];
    const minSize = 40;

    let newWidth = stall.pixel_width;
    let newHeight = stall.pixel_height;
    let newX = stall.pos_x;
    let newY = stall.pos_y;

    switch (direction) {
      case 'nw':
        newWidth = Math.max(minSize, stall.pos_x + stall.pixel_width - mouseX);
        newHeight = Math.max(minSize, stall.pos_y + stall.pixel_height - mouseY);
        newX = mouseX;
        newY = mouseY;
        break;
      case 'ne':
        newWidth = Math.max(minSize, mouseX - stall.pos_x);
        newHeight = Math.max(minSize, stall.pos_y + stall.pixel_height - mouseY);
        newY = mouseY;
        break;
      case 'sw':
        newWidth = Math.max(minSize, stall.pos_x + stall.pixel_width - mouseX);
        newHeight = Math.max(minSize, mouseY - stall.pos_y);
        newX = mouseX;
        break;
      case 'se':
        newWidth = Math.max(minSize, mouseX - stall.pos_x);
        newHeight = Math.max(minSize, mouseY - stall.pos_y);
        break;
      case 'n':
        newHeight = Math.max(minSize, stall.pos_y + stall.pixel_height - mouseY);
        newY = mouseY;
        break;
      case 's':
        newHeight = Math.max(minSize, mouseY - stall.pos_y);
        break;
      case 'w':
        newWidth = Math.max(minSize, stall.pos_x + stall.pixel_width - mouseX);
        newX = mouseX;
        break;
      case 'e':
        newWidth = Math.max(minSize, mouseX - stall.pos_x);
        break;
    }

    // Boundary checks
    if (newX < 0) {
      newWidth += newX;
      newX = 0;
    }
    if (newY < 0) {
      newHeight += newY;
      newY = 0;
    }
    if (newX + newWidth > containerRect.width) {
      newWidth = containerRect.width - newX;
    }
    if (newY + newHeight > containerRect.height) {
      newHeight = containerRect.height - newY;
    }

    // Ensure minimum size
    newWidth = Math.max(minSize, newWidth);
    newHeight = Math.max(minSize, newHeight);

    updated[index] = {
      ...stall,
      pos_x: newX,
      pos_y: newY,
      pixel_width: newWidth,
      pixel_height: newHeight
    };
    
    setStalls(updated);
  };

  const handleDragStart = (e, index) => {
    if (resizing) return;
    
    e.preventDefault();
    setActiveStallIndex(index);
    
    const onMouseMove = (ev) => handleDrag(ev, index);
    const onMouseUp = () => {
      document.removeEventListener("mousemove", onMouseMove);
      document.removeEventListener("mouseup", onMouseUp);
      setActiveStallIndex(null);
    };
    
    document.addEventListener("mousemove", onMouseMove);
    document.addEventListener("mouseup", onMouseUp);
  };

  const handleResizeStart = (e, index, direction) => {
    e.preventDefault();
    e.stopPropagation();
    
    setResizing(true);
    setResizeDirection(direction);
    setActiveStallIndex(index);
    
    const onMouseMove = (ev) => handleResize(ev, index, direction);
    const onMouseUp = () => {
      document.removeEventListener("mousemove", onMouseMove);
      document.removeEventListener("mouseup", onMouseUp);
      setResizing(false);
      setResizeDirection(null);
      setActiveStallIndex(null);
    };
    
    document.addEventListener("mousemove", onMouseMove);
    document.addEventListener("mouseup", onMouseUp);
  };

  // Open stall modal for editing
  const openEditModal = (index, e) => {
    e.preventDefault();
    setSelectedStallIndex(index);
    const viewportX = e.clientX;
    const viewportY = e.clientY;
    const modalWidth = 350;
    const modalHeight = 550;

    let x = viewportX;
    let y = viewportY;
    if (x + modalWidth > window.innerWidth) x = window.innerWidth - modalWidth - 10;
    if (y + modalHeight > window.innerHeight) y = window.innerHeight - modalHeight - 10;
    x = Math.max(10, x);
    y = Math.max(10, y);
    setModalPos({ x, y });
    setModalOpen(true);
  };

  const handleBackdropClick = (e) => {
    if (modalRef.current && !modalRef.current.contains(e.target)) {
      setModalOpen(false);
    }
  };

  // Update stall class - SAME AS CREATOR
  const updateStallClass = (class_id) => {
    const updated = [...stalls];
    const classPrices = {
      1: 15000.00, // Class A
      2: 10000.00, // Class B
      3: 5000.00   // Class C
    };
    
    updated[selectedStallIndex].class_id = parseInt(class_id);
    updated[selectedStallIndex].class_name = class_id == 1 ? "A" : class_id == 2 ? "B" : "C";
    updated[selectedStallIndex].price = classPrices[class_id] || 5000.00;
    setStalls(updated);
  };

  // Update price separately - SAME AS CREATOR
  const updateStallPrice = (price) => {
    const updated = [...stalls];
    updated[selectedStallIndex].price = parseFloat(price) || 0;
    setStalls(updated);
  };

  // Update stall section - SAME AS CREATOR
  const updateStallSection = (section_id) => {
    const updated = [...stalls];
    updated[selectedStallIndex].section_id = section_id ? parseInt(section_id) : null;
    setStalls(updated);
  };

  // Filter stalls by section - SAME AS CREATOR
  const filteredStalls = selectedSection === "all" 
    ? stalls 
    : stalls.filter(stall => stall.section_id == selectedSection);

  // Get section name by ID - SAME AS CREATOR
  const getSectionName = (section_id) => {
    const section = sections.find(s => s.id == section_id);
    return section ? section.name : "No Section";
  };

  // Get stall rights price based on class - SAME AS CREATOR
  const getStallRightsPrice = (class_id) => {
    const prices = {
      1: 15000.00, // Class A
      2: 10000.00, // Class B
      3: 5000.00   // Class C
    };
    return prices[class_id] || 5000.00;
  };

  if (loading) return (
    <div className="market-container">
      <div className="loading">Loading map data...</div>
    </div>
  );

  if (error) return (
    <div className="market-container">
      <h1>Error</h1>
      <div className="error-banner">
        <div className="error-content">
          <span className="error-icon">⚠️</span>
          <div className="error-message">
            <strong>Error:</strong> {error}
          </div>
          <button 
            onClick={() => navigate("/Market/ViewAllMaps")}
            className="retry-btn"
          >
            Back to Maps
          </button>
        </div>
      </div>
    </div>
  );

  return (
    <div className="market-container">
      <h1>Edit Market Map: {mapData?.name}</h1>

      <div className="instructions">
        <p>
          <strong>Instructions:</strong> Drag stalls to reposition. Right-click to edit details. 
          Click the × button to delete stalls. Add new stalls with the button below.
          Click the wrench button to toggle maintenance mode (turns stall gray).
          Drag resize handles to change stall size.
        </p>
      </div>

      {/* Market Section Filter */}
      {stalls.length > 0 && sections.length > 0 && (
        <div className="section-filter">
          <label htmlFor="sectionFilter">Filter by Market Section:</label>
          <select 
            id="sectionFilter"
            value={selectedSection} 
            onChange={(e) => setSelectedSection(e.target.value)}
            className="filter-select"
          >
            <option value="all">All Sections</option>
            {sections.map((section) => (
              <option key={section.id} value={section.id}>
                {section.name}
              </option>
            ))}
          </select>
          <div className="filter-info">
            Showing {filteredStalls.length} of {stalls.length} stalls
            {selectedSection !== "all" && ` in "${getSectionName(selectedSection)}"`}
          </div>
        </div>
      )}

      {/* Debug info for sections */}
      {sections.length === 0 && !loading && (
        <div className="debug-info">
          No sections loaded. Check if the PHP file exists and returns valid JSON.
        </div>
      )}

      <div
        id="marketMap"
        ref={marketMapRef}
        className="market-map"
        style={{ backgroundImage: mapData ? `url(http://localhost/revenue/${mapData.image_path})` : "none" }}
      >
        {filteredStalls.map((stall, index) => {
          const originalIndex = stalls.findIndex(s => s.name === stall.name);
          return (
            <div
              key={originalIndex}
              className={`stall ${stall.status} ${selectedSection !== "all" ? "filtered" : ""} ${activeStallIndex === originalIndex ? 'active' : ''}`}
              style={{ 
                left: `${stall.pos_x}px`, 
                top: `${stall.pos_y}px`,
                width: `${stall.pixel_width}px`,
                height: `${stall.pixel_height}px`
              }}
              onMouseDown={(e) => handleDragStart(e, originalIndex)}
              onContextMenu={(e) => openEditModal(originalIndex, e)}
            >
              <div className="stall-content">
                <div className="stall-name">{stall.name}</div>
                <div className="stall-class">Class: {stall.class_name}</div>
                <div className="stall-price">₱{stall.price.toLocaleString()}</div>
                <div className="stall-dimensions">
                  {Math.round(stall.pixel_width)}×{Math.round(stall.pixel_height)}px
                </div>
                <div className="stall-size">{stall.length}m × {stall.width}m × {stall.height}m</div>
                {stall.section_id && (
                  <div className="stall-section">Section: {getSectionName(stall.section_id)}</div>
                )}
              </div>

              {/* Resize handles */}
              <>
                <div className="resize-handle nw" onMouseDown={(e) => handleResizeStart(e, originalIndex, 'nw')}></div>
                <div className="resize-handle ne" onMouseDown={(e) => handleResizeStart(e, originalIndex, 'ne')}></div>
                <div className="resize-handle sw" onMouseDown={(e) => handleResizeStart(e, originalIndex, 'sw')}></div>
                <div className="resize-handle se" onMouseDown={(e) => handleResizeStart(e, originalIndex, 'se')}></div>
                <div className="resize-handle n" onMouseDown={(e) => handleResizeStart(e, originalIndex, 'n')}></div>
                <div className="resize-handle s" onMouseDown={(e) => handleResizeStart(e, originalIndex, 's')}></div>
                <div className="resize-handle w" onMouseDown={(e) => handleResizeStart(e, originalIndex, 'w')}></div>
                <div className="resize-handle e" onMouseDown={(e) => handleResizeStart(e, originalIndex, 'e')}></div>
              </>

              {/* Delete button */}
              <button
                className="delete-stall-btn"
                onClick={(e) => { e.stopPropagation(); deleteStall(originalIndex); }}
                title="Delete stall"
              >
                ×
              </button>

              {/* Maintenance button - EDITOR ONLY */}
              <button
                className={`maintenance-stall-btn ${stall.status === "maintenance" ? "active" : ""}`}
                onClick={(e) => { e.stopPropagation(); toggleMaintenance(originalIndex); }}
                title={stall.status === "maintenance" ? "Remove from maintenance" : "Put under maintenance"}
              >
                ⚙️
              </button>
            </div>
          );
        })}
      </div>

      {modalOpen && (
        <div className="modal-backdrop" onClick={handleBackdropClick}>
          <div
            ref={modalRef}
            className="price-modal"
            style={{ left: `${modalPos.x}px`, top: `${modalPos.y}px`, position: 'fixed' }}
            onClick={(e) => e.stopPropagation()}
          >
            <h4>Set Stall Details - {stalls[selectedStallIndex]?.name}</h4>

            {/* Section Selection Field */}
            <label>Market Section</label>
            <select
              value={stalls[selectedStallIndex]?.section_id || ""}
              onChange={(e) => updateStallSection(e.target.value)}
            >
              <option value="">No Section</option>
              {sections.map((section) => (
                <option key={section.id} value={section.id}>
                  {section.name}
                </option>
              ))}
            </select>

            <label>Stall Class</label>
            <select
              value={stalls[selectedStallIndex]?.class_id || ""}
              onChange={(e) => updateStallClass(e.target.value)}
            >
              <option value="1">Class A - ₱15,000.00 (Premium Location)</option>
              <option value="2">Class B - ₱10,000.00 (Standard Location)</option>
              <option value="3">Class C - ₱5,000.00 (Economy Location)</option>
            </select>

            <div className="current-class-info">
              <strong>Selected: Class {stalls[selectedStallIndex]?.class_name}</strong>
              <br />
              <span>Stall Rights: ₱{getStallRightsPrice(stalls[selectedStallIndex]?.class_id).toLocaleString()}</span>
            </div>

            <label>Custom Price (₱)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.price || 0}
              onChange={(e) => updateStallPrice(e.target.value)}
              step="0.01"
              min="0"
            />

            <label>Height (m)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.height || 0}
              onChange={(e) => {
                const updated = [...stalls];
                updated[selectedStallIndex].height = parseFloat(e.target.value) || 0;
                setStalls(updated);
              }}
              step="0.01"
            />

            <label>Length (m)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.length || 0}
              onChange={(e) => {
                const updated = [...stalls];
                updated[selectedStallIndex].length = parseFloat(e.target.value) || 0;
                setStalls(updated);
              }}
              step="0.01"
            />

            <label>Width (m)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.width || 0}
              onChange={(e) => {
                const updated = [...stalls];
                updated[selectedStallIndex].width = parseFloat(e.target.value) || 0;
                setStalls(updated);
              }}
              step="0.01"
            />

            <div className="modal-buttons">
              <button onClick={() => setModalOpen(false)}>Save</button>
              <button onClick={() => setModalOpen(false)}>Cancel</button>
            </div>
          </div>
        </div>
      )}

      <div className="controls">
        <button onClick={addStall}>Add Stall</button>
        <button onClick={saveUpdates}>Save Changes</button>
        <button onClick={() => navigate("/Market/ViewAllMaps")}>View All Maps</button>
        <button onClick={fetchMapData}>Refresh Data</button>
        <button 
          onClick={() => navigate(`/Market/MarketOutput/view/${id}`)}
          className="view-customer-btn"
        >
          View as Customer
        </button>
      </div>
    </div>
  );
}