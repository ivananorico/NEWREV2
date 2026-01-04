import React, { useState, useRef, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import "./MapCreator.css";

export default function Market1() {
  const [mapId, setMapId] = useState(null);
  const [stallCount, setStallCount] = useState(0);
  const [stalls, setStalls] = useState([]);
  const [mapImage, setMapImage] = useState(null);
  const [mapFile, setMapFile] = useState(null);
  const [isFinished, setIsFinished] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const [modalOpen, setModalOpen] = useState(false);
  const [selectedStallIndex, setSelectedStallIndex] = useState(null);

  // Resize state
  const [resizing, setResizing] = useState(false);
  const [resizeDirection, setResizeDirection] = useState(null);
  const [activeStallIndex, setActiveStallIndex] = useState(null);

  const marketMapRef = useRef(null);
  const modalRef = useRef(null);
  const navigate = useNavigate();
  
  // Detect if running in production or local
  const isProduction = window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1';
  const API_BASE = isProduction 
    ? "/backend/Market/MapCreator"
    : "http://localhost/revenue2/backend/Market/MapCreator";

  const handleFileSelect = (e) => {
    const file = e.target.files[0];
    if (file) {
      setMapFile(file);
      setMapImage(URL.createObjectURL(file));
    }
  };

  // Add a stall with pixel dimensions
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
        class_id: 3,
        class_name: "C",
        price: 5000.00,
        height: 0,          // Physical height in meters
        length: 0,          // Physical length in meters
        width: 0           // Physical width in meters
      }
    ]);
  };

  const deleteStall = (index) => {
    if (window.confirm(`Delete ${stalls[index].name}?`)) {
      const updated = stalls.filter((_, i) => i !== index);
      setStalls(updated);
      setStallCount(updated.length);
    }
  };

  const saveStalls = async () => {
    if (!mapFile) return alert("Select a map image first.");
    const mapName = document.querySelector('input[name="mapName"]').value || "Unnamed Map";

    const formData = new FormData();
    formData.append("mapName", mapName);
    formData.append("mapImage", mapFile);
    formData.append("stalls", JSON.stringify(stalls));

    try {
      setLoading(true);
      setError(null);
      
      const res = await fetch(`${API_BASE}/save_stalls.php`, {
        method: "POST",
        body: formData
      });
      
      if (!res.ok) {
        throw new Error(`HTTP error! Status: ${res.status}`);
      }
      
      const data = await res.json();
      
      if (data.status === "success") {
        alert("Map and stalls saved!");
        setMapId(data.map_id);
        navigate(`/Market/MarketOutput/view/${data.map_id}`);
      } else {
        setError("Save failed: " + (data.message || "Unknown error"));
      }
    } catch (err) {
      console.error('Save error:', err);
      setError("Upload error: " + err.message);
    } finally {
      setLoading(false);
    }
  };

  // Drag stalls
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

  // Resize stalls
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

  const openPriceModal = (index, e) => {
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
    const updated = [...stalls];
    const classPrices = {
      1: 15000.00,
      2: 10000.00,
      3: 5000.00
    };
    
    updated[selectedStallIndex].class_id = parseInt(class_id);
    updated[selectedStallIndex].class_name = class_id == 1 ? "A" : class_id == 2 ? "B" : "C";
    updated[selectedStallIndex].price = classPrices[class_id] || 5000.00;
    setStalls(updated);
  };

  const updateStallPrice = (price) => {
    const updated = [...stalls];
    updated[selectedStallIndex].price = parseFloat(price) || 0;
    setStalls(updated);
  };

  return (
    <div className="market-container">
      <div className="market-header">
        <div className="header-content">
          <h1>{isFinished ? "Finished Market Map" : "Market Map Creator"}</h1>
          <p className="subtitle">Design and arrange stalls on your market map</p>
        </div>
      </div>

      {error && (
        <div className="error-banner">
          <div className="error-content">
            <span className="error-icon">⚠️</span>
            <div className="error-message">
              <strong>Error:</strong> {error}
            </div>
            <button onClick={() => setError(null)} className="retry-btn">
              Dismiss
            </button>
          </div>
        </div>
      )}

      <div className="market-content">
        {/* Left Panel - Controls */}
        <div className="control-panel">
          {!isFinished && (
            <div className="upload-section">
              <h3>Map Setup</h3>
              <div className="form-group">
                <label>Map Name</label>
                <input 
                  type="text" 
                  name="mapName" 
                  placeholder="Enter map name" 
                  required 
                  className="form-input"
                />
              </div>
              <div className="form-group">
                <label>Upload Map Image</label>
                <div className="file-upload">
                  <input 
                    type="file" 
                    name="mapImage" 
                    accept="image/*" 
                    onChange={handleFileSelect} 
                    required 
                    id="mapUpload"
                  />
                  <label htmlFor="mapUpload" className="file-upload-label">
                    Choose Image
                  </label>
                  {mapFile && (
                    <span className="file-name">{mapFile.name}</span>
                  )}
                </div>
              </div>
            </div>
          )}

          <div className="stats-section">
            <h3>Summary</h3>
            <div className="stats-grid">
              <div className="stat-card">
                <span className="stat-number">{stallCount}</span>
                <span className="stat-label">Total Stalls</span>
              </div>
              <div className="stat-card">
                <span className="stat-number">₱{(stalls.reduce((sum, stall) => sum + stall.price, 0)).toLocaleString()}</span>
                <span className="stat-label">Total Value</span>
              </div>
            </div>
          </div>

          <div className="actions-section">
            <h3>Actions</h3>
            <div className="action-buttons">
              <button 
                onClick={addStall} 
                disabled={!mapImage}
                className="btn btn-primary"
              >
                <span className="btn-icon">+</span> Add Stall
              </button>
              <button 
                onClick={saveStalls} 
                disabled={!mapFile || loading}
                className="btn btn-success"
              >
                {loading ? "Saving..." : "Save Map"}
              </button>
              <button 
                onClick={() => navigate("/Market/ViewAllMaps")}
                className="btn btn-secondary"
              >
                View All Maps
              </button>
              <button 
                onClick={() => navigate("/Market/Config")}
                className="btn btn-accent"
              >
                Configuration
              </button>
            </div>
          </div>
        </div>

        {/* Right Panel - Map Area */}
        <div className="map-panel">
          <div className="map-header">
            <h3>Market Map</h3>
            <div className="map-info">
              <span className="info-item">
                <span className="info-label">Size:</span> 800×600px
              </span>
              <span className="info-item">
                <span className="info-label">Stalls:</span> {stallCount}
              </span>
            </div>
          </div>
          
          <div
            id="marketMap"
            ref={marketMapRef}
            className="market-map"
            style={{ backgroundImage: mapImage ? `url('${mapImage}')` : "none" }}
          >
            {!mapImage && (
              <div className="map-placeholder">
                <div className="placeholder-icon">🗺️</div>
                <p>Upload a map image to begin</p>
              </div>
            )}
            
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
                onContextMenu={(e) => openPriceModal(index, e)}
              >
                <div className="stall-content">
                  <div className="stall-header">
                    <div className="stall-name">{stall.name}</div>
                    <div className="stall-class-badge">{stall.class_name}</div>
                  </div>
                  <div className="stall-details">
                    <div className="stall-price">₱{stall.price.toLocaleString()}</div>
                    <div className="stall-dimensions">
                      {Math.round(stall.pixel_width)}×{Math.round(stall.pixel_height)}px
                    </div>
                  </div>
                </div>

                {/* Resize handles */}
                {!isFinished && (
                  <>
                    <div className="resize-handle nw" onMouseDown={(e) => handleResizeStart(e, index, 'nw')}></div>
                    <div className="resize-handle ne" onMouseDown={(e) => handleResizeStart(e, index, 'ne')}></div>
                    <div className="resize-handle sw" onMouseDown={(e) => handleResizeStart(e, index, 'sw')}></div>
                    <div className="resize-handle se" onMouseDown={(e) => handleResizeStart(e, index, 'se')}></div>
                  </>
                )}

                {!isFinished && (
                  <button
                    className="delete-stall-btn"
                    onClick={(e) => { e.stopPropagation(); deleteStall(index); }}
                    title="Delete stall"
                  >
                    ×
                  </button>
                )}
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
                <div className="class-selector">
                  {[
                    { id: 1, name: "A", price: 15000.00 },
                    { id: 2, name: "B", price: 10000.00 },
                    { id: 3, name: "C", price: 5000.00 }
                  ].map((cls) => (
                    <button
                      key={cls.id}
                      className={`class-option ${stalls[selectedStallIndex]?.class_id === cls.id ? 'selected' : ''}`}
                      onClick={() => updateStallClass(cls.id)}
                    >
                      <span className="class-name">Class {cls.name}</span>
                      <span className="class-price">₱{cls.price.toLocaleString()}</span>
                    </button>
                  ))}
                </div>
              </div>

              <div className="form-section">
                <label>Custom Price (₱)</label>
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
      
      {loading && (
        <div className="loading-overlay">
          <div className="loading-spinner"></div>
          <p>Saving map and stalls...</p>
        </div>
      )}
    </div>
  );
}