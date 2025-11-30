import { useEffect, useState } from "react";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Legend,
} from "chart.js";
import { Line } from "react-chartjs-2";

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend);

export default function Dashboard() {
  const [data, setData] = useState(null);
  const [artistId, setArtistId] = useState("all");
  const [period, setPeriod] = useState("month");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  async function loadDashboard(selectedArtistId = artistId, selectedPeriod = period) {
    setLoading(true);
    setError("");

    try {
      const params = new URLSearchParams({
        artist_id: selectedArtistId,
        period: selectedPeriod,
      });

      // adjust URL if your PHP lives somewhere else
const API_BASE = "https://cr8admin.dcism.org";

const res = await fetch(
  `${API_BASE}/admin/api/dashboard.php?${params.toString()}`,
  {
    credentials: "include"
  }
);

      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body.error || `Request failed with status ${res.status}`);
      }

      const json = await res.json();
      setData(json);
    } catch (err) {
      console.error(err);
      setError(err.message || "Failed to load dashboard data");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadDashboard();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleFilterSubmit = (e) => {
    e.preventDefault();
    loadDashboard(artistId, period);
  };

  if (loading && !data) {
    return <p className="text-gray-600">Loading dashboard...</p>;
  }

  if (error && !data) {
    return <p className="text-red-600">Error: {error}</p>;
  }

  const cards = data?.cards || {};
  const chart = data?.chart || { labels: [], data: [] };
  const artists = data?.filters?.artists || [];
  const latestSales = data?.latest_sales || [];

  const chartData = {
    labels: chart.labels,
    datasets: [
      {
        label: "Revenue",
        data: chart.data,
        borderColor: "#7c3aed",
        backgroundColor: "rgba(124, 58, 237, 0.2)",
        pointBackgroundColor: "#7c3aed",
        borderWidth: 2,
        tension: 0.3,
        fill: true,
      },
    ],
  };

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: true,
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          callback: (value) => "₱" + value.toLocaleString(),
        },
      },
    },
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: (context) =>
            `Revenue: ₱${context.parsed.y.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            })}`,
        },
      },
    },
  };

  return (
    <div className="space-y-8">
      {/* Top stat cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div className="bg-white rounded-xl shadow p-6 flex flex-col justify-between">
          <h3 className="text-gray-500 font-semibold">Total Revenue</h3>
          <p className="text-3xl font-bold text-green-600 mt-2">
            ₱{Number(cards.sales_total || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}
          </p>
        </div>

        <div className="bg-white rounded-xl shadow p-6 flex flex-col justify-between">
          <h3 className="text-gray-500 font-semibold">Top Selling Artist</h3>
          <p className="text-2xl font-bold text-purple-700 mt-2 truncate">
            {cards.top_seller?.artist_name || "N/A"}
          </p>
          <p className="text-sm text-gray-400">
            ₱{Number(cards.top_seller?.total_revenue || 0).toLocaleString(undefined, {
              minimumFractionDigits: 2,
            })}{" "}
            in sales
          </p>
        </div>

        <div className="bg-white rounded-xl shadow p-6 flex flex-col justify-between">
          <h3 className="text-gray-500 font-semibold">Top Selling Product</h3>
          <p className="text-2xl font-bold text-blue-600 mt-2 truncate">
            {cards.top_product?.product_name || "N/A"}
          </p>
          <p className="text-sm text-gray-400">
            {Number(cards.top_product?.total_sold || 0).toLocaleString()} units sold
          </p>
        </div>

        <div className="bg-white rounded-xl shadow p-6 flex flex-col justify-between">
          <h3 className="text-gray-500 font-semibold">Artist Applications</h3>
          <p className="text-3xl font-bold text-yellow-600 mt-2">
            {cards.app_count ?? 0}
          </p>
          <p className="text-sm text-gray-400">
            Unread: <span className="font-bold">{cards.unread_count ?? 0}</span>
          </p>
        </div>
      </div>

      {/* Chart + Latest sales */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Chart */}
        <div className="lg:col-span-2 bg-white rounded-xl shadow p-6">
          <div className="flex flex-col sm:flex-row justify-between items-center mb-4">
            <h2 className="text-xl font-bold text-gray-800">Revenue Overview</h2>

            <form
              onSubmit={handleFilterSubmit}
              className="flex items-center gap-2 mt-4 sm:mt-0"
            >
              <select
                value={artistId}
                onChange={(e) => setArtistId(e.target.value)}
                className="px-3 py-2 bg-gray-100 border border-gray-200 rounded-md text-sm focus:outline-purple-400"
              >
                <option value="all">All Artists</option>
                {artists.map((artist) => (
                  <option key={artist.id} value={artist.id}>
                    {artist.artist_name}
                  </option>
                ))}
              </select>

              <select
                value={period}
                onChange={(e) => setPeriod(e.target.value)}
                className="px-3 py-2 bg-gray-100 border border-gray-200 rounded-md text-sm focus:outline-purple-400"
              >
                <option value="week">This Week</option>
                <option value="month">This Month</option>
                <option value="year">This Year</option>
              </select>

              <button
                type="submit"
                className="px-4 py-2 bg-purple-600 text-white font-semibold rounded-md text-sm hover:bg-purple-700"
              >
                Apply
              </button>
            </form>
          </div>

          <Line data={chartData} options={chartOptions} />
        </div>

        {/* Latest Sales */}
        <div className="bg-white rounded-xl shadow p-6 overflow-x-auto">
          <h2 className="text-xl font-bold text-gray-800 mb-4">Latest Items Sold</h2>
          <ul className="space-y-3">
            {latestSales.length === 0 && (
              <li className="text-gray-400 text-sm">No recent sales to display.</li>
            )}

            {latestSales.map((sale, idx) => (
              <li
                key={idx}
                className="flex items-center justify-between py-2 border-b last:border-b-0 min-w-0"
              >
                <div className="min-w-0">
                  <p
                    className="font-semibold text-gray-700 text-sm truncate"
                    title={sale.product_name}
                  >
                    {sale.product_name}
                  </p>
                  <p className="text-xs text-gray-500 truncate">
                    by {sale.artist_name}
                  </p>
                </div>
                <div className="text-right flex-shrink-0">
                  <p className="font-bold text-green-600 text-sm">
                    ₱{Number(sale.sale_total).toLocaleString(undefined, {
                      minimumFractionDigits: 2,
                    })}
                  </p>
                  <p className="text-xs text-gray-400">
                    {new Date(sale.created_at).toLocaleDateString(undefined, {
                      month: "short",
                      day: "2-digit",
                    })}
                  </p>
                </div>
              </li>
            ))}
          </ul>
        </div>
      </div>

      {error && (
        <p className="text-xs text-red-500">
          (Warning: {error})
        </p>
      )}
    </div>
  );
}
