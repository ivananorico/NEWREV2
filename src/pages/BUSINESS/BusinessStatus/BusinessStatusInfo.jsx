import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";

export default function BusinessStatusInfo() {
  const { id } = useParams();
  const [permit, setPermit] = useState(null);
  const [quarterlyTaxes, setQuarterlyTaxes] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchPermit();
  }, [id]);

  const fetchPermit = async () => {
    try {
      const res = await fetch(
        `http://localhost/revenue2/backend/Business/BusinessStatus/get_permit_by_id.php?id=${id}`,
        { credentials: "include" }
      );
      const data = await res.json();
      if (data.status === "success") {
        setPermit(data.permit);
        setQuarterlyTaxes(data.quarterlyTaxes || []);
      }
    } catch (err) {
      console.error("Error:", err);
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <p className="text-center mt-10 text-gray-500">Loading...</p>;
  if (!permit) return <p className="text-center mt-10 text-red-500">No record found</p>;

  return (
    <div className="max-w-5xl mx-auto mt-8 p-6 bg-white dark:bg-slate-900 rounded-lg shadow-lg">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:justify-between md:items-center mb-6">
        <h2 className="text-3xl font-bold text-blue-700 dark:text-blue-400">Business Permit Details</h2>
        <span className={`mt-2 md:mt-0 px-4 py-2 rounded-full text-white font-semibold
          ${permit.status === "Approved" ? "bg-green-600" : permit.status === "Pending" ? "bg-yellow-500" : "bg-red-600"}`}>
          {permit.status}
        </span>
      </div>

      {/* Permit Info */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <p><strong>Business Name:</strong> {permit.business_name}</p>
        <p><strong>Owner:</strong> {permit.owner_name}</p>
        <p><strong>Business Type:</strong> {permit.business_type}</p>
        <p><strong>Tax Type:</strong> {permit.tax_calculation_type}</p>
        <p><strong>Tax Amount:</strong> ₱{permit.tax_amount}</p>
        <p><strong>Total Tax:</strong> ₱{permit.total_tax}</p>
        <p><strong>Issued Date:</strong> {permit.issue_date}</p>
        <p><strong>Expiry Date:</strong> {permit.expiry_date}</p>
        <p><strong>Address:</strong> {permit.address}</p>
        <p><strong>Contact:</strong> {permit.contact_number}</p>
        {permit.additional_info && <p><strong>Additional Info:</strong> {permit.additional_info}</p>}
      </div>

      {/* Quarterly Taxes */}
      <div className="mb-6">
        <h3 className="text-2xl font-semibold mb-3 text-gray-700 dark:text-gray-300">Quarterly Taxes</h3>
        {quarterlyTaxes.length === 0 ? (
          <p className="text-gray-500">No quarterly taxes found.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border border-gray-200 dark:border-gray-700 rounded-lg">
              <thead className="bg-blue-100 dark:bg-blue-900 text-gray-700 dark:text-gray-200">
                <tr>
                  <th className="px-4 py-2">Quarter</th>
                  <th className="px-4 py-2">Amount</th>
                  <th className="px-4 py-2">Due Date</th>
                  <th className="px-4 py-2">Paid</th>
                </tr>
              </thead>
              <tbody>
                {quarterlyTaxes.map((tax) => (
                  <tr key={tax.id} className="border-t border-gray-200 dark:border-gray-700">
                    <td className="px-4 py-2">{tax.quarter}</td>
                    <td className="px-4 py-2">₱{tax.amount}</td>
                    <td className="px-4 py-2">{tax.due_date}</td>
                    <td className={`px-4 py-2 font-semibold ${tax.paid ? "text-green-600" : "text-red-600"}`}>
                      {tax.paid ? "Paid" : "Unpaid"}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Footer / Notes */}
      <div className="text-gray-500 dark:text-gray-400 text-sm mt-4">
        * All amounts are in Philippine Pesos. Please verify your quarterly tax payments with the LGU.
      </div>
    </div>
  );
}
