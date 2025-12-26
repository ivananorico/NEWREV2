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

  const API_BASE = "http://localhost/revenue2/backend/Market/MapCreator";

  console.log("MarketOutput mounted with ID:", id);

  useEffect(() => {
    async function fetchData() {
      try {
        console.log("Fetching data for map ID:", id);
        const res = await fetch(`${API_BASE}/map_display.php?map_id=${id}`);
        
        if (!res.ok) throw new Error(`Network error: ${res.status}`);
        
        const data = await res.json();
        console.log("API Response:", data);

        if (data.status === "success") {
          setMapName(data.map.name);
          const baseUrl = "http://localhost/revenue2";
          setMapImage(`${baseUrl}/${data.map.image_path}`);
          
          // Ensure stalls have pixel dimensions, use defaults if not present
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

  console.log("Current state:", { loading, error, mapName, mapImage, stalls });

  if (loading) return (
    <div className="loading-container">
      <h2>Loading market map ID: {id}...</h2>
    </div>
  );
  
  if (error) return (
    <div className="error-container">
      <h2>Error loading map</h2>
      <p>Error: {error}</p>
      <p>Map ID: {id}</p>
      <button 
        className="back-button"
        onClick={() => navigate("/Market/MapCreator")}
      >
        Back to Market Creator
      </button>
    </div>
  );

  return (
    <div className="market-output-container">
      <h1>Market Map: {mapName || "Unknown"}</h1>
      <p>Map ID: {id}</p>
      
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
      
      {mapImage ? (
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
                <div className="stall-class">Class: {stall.class_name}</div>
                <div className="stall-price">₱{stall.price?.toLocaleString()}</div>
                {stall.section_name && (
                  <div className="stall-section">Section: {stall.section_name}</div>
                )}
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="no-image-message">
          No map image available
        </div>
      )}

      <button 
        className="back-button"
        onClick={() => navigate("/Market/MapCreator")}
      >
        Back to Market Creator
      </button>
    </div>
  );
}