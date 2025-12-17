import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";

export default function RPTValidationTable() {
  const [registrations, setRegistrations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const navigate = useNavigate();

  // 🔥 WORKS BOTH LOCAL & DOMAIN
  const API_BASE =
    window.location.hostname === "localhost"
      ? "http://localhost/revenue/backend"
      : "https://revenuetreasury.goserveph.com/backend";

  const fetchRegistrations = async () => {
    try {
      const response = await fetch(
        `${API_BASE}/RPT/RPTValidationTable/get_registrations.php`
      );
      const data = await response.json();

      if (data.status === "success") {
        setRegistrations(
          data.registrations.filter(r => r.status !== "approved")
        );
      } else {
        throw new Error(data.message || "Failed to fetch registrations");
      }
    } catch (err) {
      setError(err.message);
      console.error("Fetch error:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRegistrations();
  }, []);

  const getStatusBadge = (status) => {
    const map = {
      pending: "bg-yellow-100 text-yellow-800",
      for_inspection: "bg-blue-100 text-blue-800",
      needs_correction: "bg-orange-100 text-orange-800",
      assessed: "bg-purple-100 text-purple-800",
      approved: "bg-green-100 text-green-800",
    };
    return (
      <span className={`px-2 py-1 rounded-full text-xs ${map[status] || "bg-gray-100"}`}>
        {status.replace("_", " ").toUpperCase()}
      </span>
    );
  };

  const formatDate = (d) =>
    new Date(d).toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });

  const handleViewDetails = (id) => {
    navigate(`/rpt/rptvalidationinfo/${id}`);
  };

  if (loading) return <div className="p-6">Loading…</div>;
  if (error) return <div className="p-6 text-red-600">{error}</div>;

  return (
    <div className="bg-white rounded shadow border">
      <div className="p-4 border-b">
        <h2 className="text-lg font-semibold">Property Registrations</h2>
      </div>

      <table className="min-w-full divide-y divide-gray-200">
        <thead className="bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th className="px-4 py-3 text-left">Ref No</th>
            <th className="px-4 py-3 text-left">Owner</th>
            <th className="px-4 py-3 text-left">Location</th>
            <th className="px-4 py-3 text-left">Status</th>
            <th className="px-4 py-3 text-left">Submitted</th>
            <th className="px-4 py-3 text-left">Action</th>
          </tr>
        </thead>
        <tbody className="divide-y">
          {registrations.length === 0 && (
            <tr>
              <td colSpan="6" className="p-6 text-center text-gray-500">
                No pending applications
              </td>
            </tr>
          )}

          {registrations.map(r => (
            <tr key={r.id} className="hover:bg-gray-50">
              <td className="px-4 py-3">{r.reference_number}</td>
              <td className="px-4 py-3">
                <div>{r.owner_name}</div>
                <div className="text-sm text-gray-500">{r.email}</div>
              </td>
              <td className="px-4 py-3">{r.lot_location}</td>
              <td className="px-4 py-3">{getStatusBadge(r.status)}</td>
              <td className="px-4 py-3">{formatDate(r.created_at)}</td>
              <td className="px-4 py-3">
                <button
                  onClick={() => handleViewDetails(r.id)}
                  className="bg-blue-600 text-white px-3 py-1 rounded text-sm"
                >
                  View
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
