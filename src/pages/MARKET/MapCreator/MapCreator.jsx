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
  const [sections, setSections] = useState([]);
  const [selectedSection, setSelectedSection] = useState("all");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [modalOpen, setModalOpen] = useState(false);
  const [selectedStallIndex, setSelectedStallIndex] = useState(null);
  const [modalPos, setModalPos] = useState({ x: 0, y: 0 });

  // Resize state
  const [resizing, setResizing] = useState(false);
  const [resizeDirection, setResizeDirection] = useState(null);
  const [activeStallIndex, setActiveStallIndex] = useState(null);

  const marketMapRef = useRef(null);
  const modalRef = useRef(null);
  const navigate = useNavigate();
  
  const API_BASE = "http://localhost/revenue/backend/Market/MapCreator";

  useEffect(() => {
    fetchSections();
  }, []);

  const fetchSections = async () => {
    try {
      setLoading(true);
      setError(null);
      console.log('Fetching sections from:', `${API_BASE}/get_sections.php`);
      
      const res = await fetch(`${API_BASE}/get_sections.php`);
      const data = await res.json();
      
      console.log('Sections response:', data);
      
      if (data.status === "success") {
        setSections(data.sections);
        console.log('Sections loaded successfully:', data.sections);
      } else {
        console.error("Failed to fetch sections from database:", data.message);
        setSections([]);
        setError("Failed to load sections: " + data.message);
      }
    } catch (err) {
      console.error("Error fetching sections:", err);
      setSections([]);
      setError("Network error: " + err.message);
    } finally {
      setLoading(false);
    }
  };

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
        width: 0,           // Physical width in meters
        section_id: sections.length > 0 ? sections[0].id : null
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
      const res = await fetch(`${API_BASE}/save_stalls.php`, {
        method: "POST",
        body: formData
      });
      const data = await res.json();
      if (data.status === "success") {
        alert("Map and stalls saved!");
        setMapId(data.map_id);
        navigate(`/Market/MarketOutput/view/${data.map_id}`);
      } else {
        alert("Save failed: " + (data.message || "Unknown"));
      }
    } catch (err) {
      alert("Upload error: " + err.message);
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

  const updateStallSection = (section_id) => {
    const updated = [...stalls];
    updated[selectedStallIndex].section_id = section_id ? parseInt(section_id) : null;
    setStalls(updated);
  };

  const filteredStalls = selectedSection === "all" 
    ? stalls 
    : stalls.filter(stall => stall.section_id == selectedSection);

  const getSectionName = (section_id) => {
    const section = sections.find(s => s.id == section_id);
    return section ? section.name : "No Section";
  };

  const getStallRightsPrice = (class_id) => {
    const prices = {
      1: 15000.00,
      2: 10000.00,
      3: 5000.00
    };
    return prices[class_id] || 5000.00;
  };

  if (loading) {
    return (
      <div className="market-container">
        <div className="loading">Loading sections...</div>
      </div>
    );
  }

  return (
    <div className="market-container">
      <h1>{isFinished ? "Finished Market Map" : "Market Map Creator"}</h1>

      {error && (
        <div className="error-banner">
          <div className="error-content">
            <span className="error-icon">⚠️</span>
            <div className="error-message">
              <strong>Error:</strong> {error}
            </div>
            <button onClick={fetchSections} className="retry-btn">
              Retry
            </button>
          </div>
        </div>
      )}

      {!isFinished && (
        <div className="upload-form">
          <input type="text" name="mapName" placeholder="Map Name" required />
          <input type="file" name="mapImage" accept="image/*" onChange={handleFileSelect} required />
        </div>
      )}

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

      {sections.length === 0 && !loading && (
        <div className="debug-info">
          No sections loaded. Check if the PHP file exists and returns valid JSON.
        </div>
      )}

      <div
        id="marketMap"
        ref={marketMapRef}
        className="market-map"
        style={{ backgroundImage: mapImage ? `url('${mapImage}')` : "none" }}
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
              onContextMenu={(e) => openPriceModal(originalIndex, e)}
            >
              <div className="stall-content">
                <div className="stall-name">{stall.name}</div>
                <div className="stall-class">Class: {stall.class_name}</div>
                <div className="stall-price">₱{stall.price.toLocaleString()}</div>
                <div className="stall-dimensions">
                  {Math.round(stall.pixel_width)}×{Math.round(stall.pixel_height)}px
                </div>
                <div className="stall-size">
                  {stall.length}m × {stall.width}m × {stall.height}m
                </div>
                {stall.section_id && (
                  <div className="stall-section">Section: {getSectionName(stall.section_id)}</div>
                )}
              </div>

              {/* Resize handles */}
              {!isFinished && (
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
              )}

              {!isFinished && (
                <button
                  className="delete-stall-btn"
                  onClick={(e) => { e.stopPropagation(); deleteStall(originalIndex); }}
                  title="Delete stall"
                >
                  ×
                </button>
              )}
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
              <option value="1">Class A - ₱15,000.00</option>
              <option value="2">Class B - ₱10,000.00</option>
              <option value="3">Class C - ₱5,000.00</option>
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

            <div className="modal-buttons">
              <button onClick={() => setModalOpen(false)}>Save</button>
              <button onClick={() => setModalOpen(false)}>Cancel</button>
            </div>
          </div>
        </div>
      )}

      {!isFinished && (
        <div className="controls">
          <button onClick={addStall} disabled={!mapImage}>Add Stall</button>
          <button onClick={saveStalls} disabled={!mapFile}>Save Stalls</button>
          <button onClick={() => navigate("/Market/ViewAllMaps")}>View All Maps</button>
          <button onClick={fetchSections}>Refresh Sections</button>
          <button 
            onClick={() => navigate("/Market/Config")}
            className="config-btn"
            title="Configure stall rights and sections"
          >
            ⚙️ Configuration
          </button>
        </div>
      )}
    </div>
  );
}