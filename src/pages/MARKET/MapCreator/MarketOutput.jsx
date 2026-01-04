import React, { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import "./MarketOutput.css";

export default function MarketOutput() {
  const { id } = useParams();
  const [mapName, setMapName] = useState("");
  const [mapImage, setMapImage] = useState(null);
  const [stalls, setStalls] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const navigate = useNavigate();

  // Detect environment and set base URLs
  const isProduction = window.location.hostname !== 'localhost' && 
                       window.location.hostname !== '127.0.0.1';
  
  const API_BASE = isProduction 
    ? "/backend/Market/MapCreator"
    : "http://localhost/revenue2/backend/Market/MapCreator";

  // For images, handle differently based on environment
  const getImageUrl = (filePath) => {
    if (!filePath) return null;
    
    if (isProduction) {
      return filePath.startsWith('/') ? filePath : `/${filePath}`;
    } else {
      const baseUrl = "http://localhost/revenue2";
      return filePath.startsWith('http') ? filePath : `${baseUrl}/${filePath}`;
    }
  };

  useEffect(() => {
    async function fetchData() {
      try {
        const res = await fetch(`${API_BASE}/map_display.php?map_id=${id}`);
        
        if (!res.ok) throw new Error(`Network error: ${res.status}`);
        
        const data = await res.json();

        if (data.status === "success") {
          setMapName(data.map.name);
          
          const imageUrl = getImageUrl(data.map.file_path || data.map.image_path);
          setMapImage(imageUrl);
          
          const stallsWithPixelDims = (data.stalls || []).map(stall => ({
            ...stall,
            pixel_width: stall.pixel_width || 80,
            pixel_height: stall.pixel_height || 60
          }));
          setStalls(stallsWithPixelDims);
        } else {
          throw new Error(data.message || "Unknown error from API");
        }
      } catch (err) {
        console.error("Fetch error:", err);
        setError(err.message);
      } finally {
        setLoading(false);
      }
    }

    if (id) {
      fetchData();
    } else {
      setError("No map ID provided");
      setLoading(false);
    }
  }, [id]);

  if (loading) return (
    <div className="loading-container">
      <h2>Loading market map...</h2>
      <div className="loading-spinner"></div>
    </div>
  );
  
  if (error) return (
    <div className="error-container">
      <h2>Error loading map</h2>
      <p>Error: {error}</p>
      <p>Map ID: {id}</p>
      <button 
        className="back-button"
        onClick={() => navigate("/Market/ViewAllMaps")}
      >
        Back to Maps
      </button>
    </div>
  );

  return (
    <div className="market-output-container">
      <div className="header-section">
        <h1>Market Map: {mapName || "Unnamed Map"}</h1>
        <p className="map-info">Map ID: {id} • {stalls.length} Stalls</p>
      </div>
      
      {/* Status Legend */}
      <div className="status-legend">
        <div className="legend-item">
          <div className="legend-color legend-available"></div>
          <span>Available</span>
        </div>
        <div className="legend-item">
          <div className="legend-color legend-occupied"></div>
          <span>Occupied</span>
        </div>
        <div className="legend-item">
          <div className="legend-color legend-maintenance"></div>
          <span>Maintenance</span>
        </div>
        <div className="legend-item">
          <div className="legend-color legend-reserved"></div>
          <span>Reserved</span>
        </div>
      </div>
      
      <div className="map-section">
        {mapImage ? (
          <div className="map-container">
            <div
              className="market-map-display"
              style={{
                backgroundImage: `url('${mapImage}')`
              }}
            >
              {stalls.map((stall) => (
                <div
                  key={stall.id}
                  className={`stall-marker ${stall.status || 'available'}`}
                  style={{
                    left: `${stall.pos_x}px`,
                    top: `${stall.pos_y}px`,
                    width: `${stall.pixel_width}px`,
                    height: `${stall.pixel_height}px`
                  }}
                  title={`${stall.name} - ₱${stall.price?.toLocaleString()} - ${stall.status || 'available'}`}
                >
                  <div className="stall-content">
                    <div className="stall-name">{stall.name}</div>
                    <div className="stall-details">
                      <span className="stall-class">{stall.class_name}</span>
                      <span className="stall-price">₱{stall.price?.toLocaleString()}</span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        ) : (
          <div className="no-image-message">
            <p>No map image available</p>
            <p className="image-info">Trying to load: {mapName}</p>
          </div>
        )}
      </div>

      <div className="controls">
        <button 
          className="btn btn-secondary"
          onClick={() => navigate("/Market/ViewAllMaps")}
        >
          Back to All Maps
        </button>
        <button 
          className="btn btn-primary"
          onClick={() => navigate(`/Market/MapEditor/${id}`)}
        >
          Edit This Map
        </button>
        <button 
          className="btn btn-success"
          onClick={() => navigate("/Market/MapCreator")}
        >
          Create New Map
        </button>
      </div>
    </div>
  );
}