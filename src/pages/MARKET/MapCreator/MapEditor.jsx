import React, { useState, useRef, useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";
import "./MapEditor.css";

export default function MapEditor() {
  const { id } = useParams();
  const navigate = useNavigate();
  
  const [mapData, setMapData] = useState(null);
  const [stalls, setStalls] = useState([]);
  const [stallClasses, setStallClasses] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [modalOpen, setModalOpen] = useState(false);
  const [selectedStallIndex, setSelectedStallIndex] = useState(null);
  const [activeStallIndex, setActiveStallIndex] = useState(null);

  const marketMapRef = useRef(null);
  const modalRef = useRef(null);
  
  const isProduction = window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1';
  const API_BASE = isProduction 
    ? "/backend/Market/MapCreator"
    : "http://localhost/revenue2/backend/Market/MapCreator";

  useEffect(() => {
    fetchMapData();
    fetchStallClasses();
  }, [id]);

  const fetchMapData = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const res = await fetch(`${API_BASE}/map_display.php?map_id=${id}`);
      if (!res.ok) throw new Error(`Network error: ${res.status}`);
      
      const data = await res.json();
      
      if (data.status === "success") {
        setMapData(data.map);
        const stallsWithPixelDims = (data.stalls || []).map(stall => ({
          ...stall,
          pixel_width: stall.pixel_width || 80,
          pixel_height: stall.pixel_height || 60,
          length: stall.length || 0,
          width: stall.width || 0,
          height: stall.height || 0,
          price: parseFloat(stall.price) || 0 // Ensure price is a number
        }));
        setStalls(stallsWithPixelDims);
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
      const res = await fetch(`${API_BASE}/get_stall_rights.php`);
      if (!res.ok) {
        console.log("Failed to fetch stall classes");
        return;
      }
      
      const data = await res.json();
      
      if (data.status === "success") {
        // Make sure price is parsed as float
        const classesWithParsedPrices = (data.classes || []).map(cls => ({
          ...cls,
          price: parseFloat(cls.price) || 0
        }));
        setStallClasses(classesWithParsedPrices);
      } else {
        setStallClasses([]);
      }
    } catch (err) {
      console.error("Error fetching stall classes:", err);
      setStallClasses([]);
    }
  };

  const getImageUrl = (filePath) => {
    if (!filePath) return null;
    
    if (isProduction) {
      return filePath.startsWith('/') ? filePath : `/${filePath}`;
    } else {
      const baseUrl = "http://localhost/revenue2";
      return filePath.startsWith('http') ? filePath : `${baseUrl}/${filePath}`;
    }
  };

  const addStall = () => {
    if (!stallClasses || stallClasses.length === 0) {
      alert("No stall classes available. Please fetch stall classes first.");
      return;
    }
    
    // Use first stall class from database
    const defaultClass = stallClasses[0];
    
    setStalls([
      ...stalls,
      { 
        name: `Stall ${stalls.length + 1}`, 
        pos_x: 50, 
        pos_y: 50, 
        pixel_width: 80,
        pixel_height: 60,
        status: "available", 
        class_id: parseInt(defaultClass.class_id),
        class_name: defaultClass.class_name,
        price: parseFloat(defaultClass.price) || 5000, // Use DB price
        height: 0, 
        length: 0, 
        width: 0,
        isNew: true
      }
    ]);
  };

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
      setStalls(stalls.filter((_, i) => i !== index));
    }
  };

  const toggleMaintenance = (index) => {
    const updated = [...stalls];
    updated[index].status = updated[index].status === "maintenance" ? "available" : "maintenance";
    setStalls(updated);
  };

  const saveUpdates = async () => {
    try {
      const payload = {
        map_id: id,
        stalls: stalls.map(stall => ({
          ...stall,
          class_id: parseInt(stall.class_id),
          price: parseFloat(stall.price) || 0,
          height: parseFloat(stall.height) || 0,
          length: parseFloat(stall.length) || 0,
          width: parseFloat(stall.width) || 0
        }))
      };
      
      const res = await fetch(`${API_BASE}/update_map.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
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

  const handleDrag = (e, index) => {
    const containerRect = marketMapRef.current.getBoundingClientRect();
    const x = e.clientX - containerRect.left - (stalls[index].pixel_width / 2);
    const y = e.clientY - containerRect.top - (stalls[index].pixel_height / 2);

    const updated = [...stalls];
    updated[index].pos_x = Math.max(0, Math.min(containerRect.width - updated[index].pixel_width, x));
    updated[index].pos_y = Math.max(0, Math.min(containerRect.height - updated[index].pixel_height, y));
    setStalls(updated);
  };

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
    }

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
    
    setActiveStallIndex(index);
    
    const onMouseMove = (ev) => handleResize(ev, index, direction);
    const onMouseUp = () => {
      document.removeEventListener("mousemove", onMouseMove);
      document.removeEventListener("mouseup", onMouseUp);
      setActiveStallIndex(null);
    };
    
    document.addEventListener("mousemove", onMouseMove);
    document.addEventListener("mouseup", onMouseUp);
  };

  const openEditModal = (index, e) => {
    e.preventDefault();
    setSelectedStallIndex(index);
    setModalOpen(true);
  };

  const handleBackdropClick = (e) => {
    if (modalRef.current && !modalRef.current.contains(e.target)) {
      setModalOpen(false);
    }
  };

  const updateStallClass = (class_id) => {
    const class_id_int = parseInt(class_id);
    const selectedClass = stallClasses.find(cls => cls.class_id === class_id_int);
    
    if (!selectedClass) return;
    
    const updated = [...stalls];
    updated[selectedStallIndex] = {
      ...updated[selectedStallIndex],
      class_id: class_id_int,
      class_name: selectedClass.class_name,
      price: parseFloat(selectedClass.price) || 0
    };
    
    setStalls(updated);
  };

  const updateStallPrice = (price) => {
    const updated = [...stalls];
    updated[selectedStallIndex].price = parseFloat(price) || 0;
    setStalls(updated);
  };

  if (loading) return (
    <div className="loading-overlay">
      <div className="loading-spinner"></div>
      <p>Loading map data...</p>
    </div>
  );

  if (error) return (
    <div className="error-banner">
      <div className="error-content">
        <span className="error-icon">⚠️</span>
        <div className="error-message">
          <strong>Error:</strong> {error}
        </div>
        <div className="error-actions">
          <button onClick={() => navigate("/Market/ViewAllMaps")} className="btn btn-secondary">Back to Maps</button>
          <button onClick={fetchMapData} className="btn btn-primary">Try Again</button>
        </div>
      </div>
    </div>
  );

  return (
    <div className="market-container">
      <div className="market-header">
        <div className="header-content">
          <h1>Edit Market Map</h1>
          <p className="subtitle">{mapData?.name} • {stalls.length} Stalls</p>
        </div>
      </div>

      <div className="market-content">
        {/* Left Panel - Controls */}
        <div className="control-panel">
          <div className="stats-section">
            <h3>Summary</h3>
            <div className="stats-grid">
              <div className="stat-card">
                <span className="stat-number">{stalls.length}</span>
                <span className="stat-label">Total Stalls</span>
              </div>
              <div className="stat-card">
                <span className="stat-number">₱{(stalls.reduce((sum, stall) => sum + (parseFloat(stall.price) || 0), 0)).toLocaleString()}</span>
                <span className="stat-label">Total Value</span>
              </div>
            </div>
          </div>

          <div className="actions-section">
            <h3>Actions</h3>
            <div className="action-buttons">
              <button 
                onClick={addStall} 
                disabled={!stallClasses || stallClasses.length === 0}
                className="btn btn-primary"
              >
                <span className="btn-icon">+</span> Add Stall
              </button>
              <button 
                onClick={saveUpdates}
                className="btn btn-success"
              >
                Save Changes
              </button>
              <button 
                onClick={() => navigate(`/Market/MarketOutput/view/${id}`)}
                className="btn btn-secondary"
              >
                View as Customer
              </button>
              <button 
                onClick={() => navigate("/Market/ViewAllMaps")}
                className="btn btn-secondary"
              >
                Back to Maps
              </button>
            </div>
          </div>

          <div className="status-section">
            <h3>Status</h3>
            <div className="status-list">
              <div className="status-item">
                <div className="status-dot available"></div>
                <span>Available: {stalls.filter(s => s.status === "available").length}</span>
              </div>
              <div className="status-item">
                <div className="status-dot occupied"></div>
                <span>Occupied: {stalls.filter(s => s.status === "occupied").length}</span>
              </div>
              <div className="status-item">
                <div className="status-dot reserved"></div>
                <span>Reserved: {stalls.filter(s => s.status === "reserved").length}</span>
              </div>
              <div className="status-item">
                <div className="status-dot maintenance"></div>
                <span>Maintenance: {stalls.filter(s => s.status === "maintenance").length}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Right Panel - Map Area */}
        <div className="map-panel">
          <div className="map-header">
            <h3>Market Map Editor</h3>
            <div className="map-info">
              <span className="info-item">
                <span className="info-label">Size:</span> 800×600px
              </span>
              <span className="info-item">
                <span className="info-label">Map:</span> {mapData?.name}
              </span>
            </div>
          </div>
          
          <div
            id="marketMap"
            ref={marketMapRef}
            className="market-map"
            style={{ backgroundImage: mapData ? `url('${getImageUrl(mapData.file_path)}')` : "none" }}
          >
            {stalls.map((stall, index) => (
              <div
                key={index}
                className={`stall ${stall.status} ${activeStallIndex === index ? 'active' : ''}`}
                style={{ 
                  left: `${stall.pos_x}px`, 
                  top: `${stall.pos_y}px`,
                  width: `${stall.pixel_width}px`,
                  height: `${stall.pixel_height}px`
                }}
                onMouseDown={(e) => handleDragStart(e, index)}
                onContextMenu={(e) => openEditModal(index, e)}
              >
                <div className="stall-content">
                  <div className="stall-header">
                    <div className="stall-name">{stall.name}</div>
                    <div className="stall-class-badge">{stall.class_name}</div>
                  </div>
                  <div className="stall-details">
                    <div className="stall-price">₱{(parseFloat(stall.price) || 0).toLocaleString()}</div>
                    <div className="stall-dimensions">
                      {Math.round(stall.pixel_width)}×{Math.round(stall.pixel_height)}px
                    </div>
                  </div>
                </div>

                <div className="resize-handle nw" onMouseDown={(e) => handleResizeStart(e, index, 'nw')}></div>
                <div className="resize-handle ne" onMouseDown={(e) => handleResizeStart(e, index, 'ne')}></div>
                <div className="resize-handle sw" onMouseDown={(e) => handleResizeStart(e, index, 'sw')}></div>
                <div className="resize-handle se" onMouseDown={(e) => handleResizeStart(e, index, 'se')}></div>

                <button
                  className="delete-stall-btn"
                  onClick={(e) => { e.stopPropagation(); deleteStall(index); }}
                  title="Delete stall"
                >
                  ×
                </button>

                <button
                  className={`maintenance-stall-btn ${stall.status === "maintenance" ? "active" : ""}`}
                  onClick={(e) => { e.stopPropagation(); toggleMaintenance(index); }}
                  title={stall.status === "maintenance" ? "Remove from maintenance" : "Put under maintenance"}
                >
                  ⚙️
                </button>
              </div>
            ))}
          </div>
          
          <div className="map-legend">
            <div className="legend-item">
              <div className="legend-color available"></div>
              <span>Available</span>
            </div>
            <div className="legend-item">
              <div className="legend-color reserved"></div>
              <span>Reserved</span>
            </div>
            <div className="legend-item">
              <div className="legend-color occupied"></div>
              <span>Occupied</span>
            </div>
            <div className="legend-item">
              <div className="legend-color maintenance"></div>
              <span>Maintenance</span>
            </div>
          </div>
        </div>
      </div>

      {modalOpen && (
        <div className="modal-backdrop" onClick={handleBackdropClick}>
          <div
            ref={modalRef}
            className="price-modal"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="modal-header">
              <h4>Edit Stall Details</h4>
              <button className="modal-close" onClick={() => setModalOpen(false)}>×</button>
            </div>
            
            <div className="modal-content">
              <div className="stall-summary">
                <h5>{stalls[selectedStallIndex]?.name}</h5>
                <div className="summary-grid">
                  <div className="summary-item">
                    <span>Position:</span>
                    <span>{Math.round(stalls[selectedStallIndex]?.pos_x)}px, {Math.round(stalls[selectedStallIndex]?.pos_y)}px</span>
                  </div>
                  <div className="summary-item">
                    <span>Size:</span>
                    <span>{Math.round(stalls[selectedStallIndex]?.pixel_width)}×{Math.round(stalls[selectedStallIndex]?.pixel_height)}px</span>
                  </div>
                </div>
              </div>

              <div className="form-section">
                <label>Stall Class</label>
                <select
                  value={stalls[selectedStallIndex]?.class_id || ""}
                  onChange={(e) => updateStallClass(e.target.value)}
                  className="form-select"
                >
                  {stallClasses && stallClasses.length > 0 ? (
                    stallClasses.map((cls) => (
                      <option key={cls.class_id} value={cls.class_id}>
                        Class {cls.class_name} - ₱{parseFloat(cls.price).toLocaleString()}
                      </option>
                    ))
                  ) : (
                    <option value="">No classes available</option>
                  )}
                </select>
              </div>

              <div className="form-section">
                <label>Monthly Rent (₱)</label>
                <input
                  type="number"
                  value={stalls[selectedStallIndex]?.price || 0}
                  onChange={(e) => updateStallPrice(e.target.value)}
                  step="0.01"
                  min="0"
                  className="form-input"
                />
              </div>

              <div className="dimensions-section">
                <h6>Physical Dimensions (meters)</h6>
                <div className="dimensions-grid">
                  <div className="dimension-input">
                    <label>Length</label>
                    <input
                      type="number"
                      value={stalls[selectedStallIndex]?.length || 0}
                      onChange={(e) => {
                        const updated = [...stalls];
                        updated[selectedStallIndex].length = parseFloat(e.target.value) || 0;
                        setStalls(updated);
                      }}
                      step="0.01"
                      className="form-input"
                    />
                  </div>
                  <div className="dimension-input">
                    <label>Width</label>
                    <input
                      type="number"
                      value={stalls[selectedStallIndex]?.width || 0}
                      onChange={(e) => {
                        const updated = [...stalls];
                        updated[selectedStallIndex].width = parseFloat(e.target.value) || 0;
                        setStalls(updated);
                      }}
                      step="0.01"
                      className="form-input"
                    />
                  </div>
                  <div className="dimension-input">
                    <label>Height</label>
                    <input
                      type="number"
                      value={stalls[selectedStallIndex]?.height || 0}
                      onChange={(e) => {
                        const updated = [...stalls];
                        updated[selectedStallIndex].height = parseFloat(e.target.value) || 0;
                        setStalls(updated);
                      }}
                      step="0.01"
                      className="form-input"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div className="modal-actions">
              <button 
                onClick={() => setModalOpen(false)} 
                className="btn btn-success"
              >
                Save Changes
              </button>
              <button 
                onClick={() => setModalOpen(false)} 
                className="btn btn-secondary"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}