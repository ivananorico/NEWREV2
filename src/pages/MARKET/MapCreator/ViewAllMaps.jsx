import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";

export default function ViewAllMaps() {
  const [maps, setMaps] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const navigate = useNavigate();

  // Correct folder name
  const API_BASE = "http://localhost/revenue/backend/Market/MapCreator";

  // Fetch all maps
  useEffect(() => {
    fetchAllMaps();
  }, []);

  const fetchAllMaps = async () => {
    try {
      const res = await fetch(`${API_BASE}/get_all_maps.php`);
      const data = await res.json();

      if (data.status === "success") {
        setMaps(data.maps);
      } else {
        throw new Error(data.message || "Failed to fetch maps");
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  // Delete map
  const deleteMap = async (mapId, mapName) => {
    if (!window.confirm(`Are you sure you want to delete "${mapName}"? This will also delete all associated stalls.`)) {
      return;
    }

    try {
      const res = await fetch(`${API_BASE}/delete_map.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ map_id: mapId }),
      });

      const data = await res.json();

      if (data.status === "success") {
        alert("Map deleted successfully!");
        fetchAllMaps(); // Refresh the list
      } else {
        throw new Error(data.message || "Failed to delete map");
      }
    } catch (err) {
      alert("Delete failed: " + err.message);
    }
  };

  // View map in editor
  const viewMap = (mapId) => {
    navigate(`/MARKET/MapEditor/${mapId}`);
  };

  if (loading)
    return (
      <div className="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
        <h1 className="text-2xl font-bold mb-4">Market Dashboard</h1>
        <p>Loading maps...</p>
      </div>
    );

  if (error)
    return (
      <div className="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
        <h1 className="text-2xl font-bold mb-4">Market Dashboard</h1>
        <p className="text-red-500">Error: {error}</p>
      </div>
    );

  return (
    <div className="mx-1 mt-1 p-6 dark:bg-slate-900 bg-white dark:text-slate-300 rounded-lg">
      <h1 className="text-2xl font-bold mb-6">Market Dashboard</h1>

      <div className="mb-6">
        <button
          onClick={() => navigate("/MARKET/MapCreator")}
          className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors"
        >
          Create New Map
        </button>
      </div>

      {maps.length === 0 ? (
        <div className="text-center py-8">
          <p className="text-gray-500">No maps found. Create your first market map!</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {maps.map((map) => (
            <div
              key={map.id}
              className="bg-gray-50 dark:bg-slate-800 rounded-lg shadow-md overflow-hidden"
            >
              <div className="p-4">
                <h3 className="text-lg font-semibold mb-2">{map.name}</h3>
                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                  Created: {new Date(map.created_at).toLocaleDateString()}
                </p>
                <p className="text-sm text-gray-600 dark:text-gray-400">
                  Stalls: {map.stall_count || 0}
                </p>
              </div>

              <div className="bg-gray-100 dark:bg-slate-700 px-4 py-3 flex justify-between">
                <button
                  onClick={() => viewMap(map.id)}
                  className="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm transition-colors"
                >
                  View/Edit
                </button>
                <button
                  onClick={() => deleteMap(map.id, map.name)}
                  className="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm transition-colors"
                >
                  Delete
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
