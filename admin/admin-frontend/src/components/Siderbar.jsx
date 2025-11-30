import { NavLink } from "react-router-dom";

const linkClasses =
  "flex items-center gap-3 px-4 py-3 rounded-lg font-semibold text-sm";
const activeClasses = "bg-purple-100 text-purple-700";
const inactiveClasses = "hover:bg-purple-50 text-gray-700";

export default function Sidebar() {
  return (
    <aside className="hidden md:flex sticky top-0 h-screen z-40 w-64 bg-white border-r flex-shrink-0 flex-col justify-between">
      <div>
        <div className="flex items-center gap-2 px-6 py-6 border-b">
          <img src="/img/cr8-logo.png" alt="Logo" className="w-10 h-10 rounded-full" />
          <span className="font-bold text-xl text-purple-800">CR8 Cebu</span>
        </div>
        <nav className="flex flex-col gap-1 mt-6 px-2">
          <NavLink
            to="/dashboard"
            className={({ isActive }) =>
              `${linkClasses} ${isActive ? activeClasses : inactiveClasses}`
            }
          >
            Dashboard
          </NavLink>
          <NavLink
            to="/artist-applications"
            className={({ isActive }) =>
              `${linkClasses} ${isActive ? activeClasses : inactiveClasses}`
            }
          >
            Artist Applications
          </NavLink>
          <NavLink
            to="/inbox"
            className={({ isActive }) =>
              `${linkClasses} ${isActive ? activeClasses : inactiveClasses}`
            }
          >
            Inbox
          </NavLink>
          <NavLink
            to="/artists"
            className={({ isActive }) =>
              `${linkClasses} ${isActive ? activeClasses : inactiveClasses}`
            }
          >
            All Artists
          </NavLink>
          <NavLink
            to="/customers"
            className={({ isActive }) =>
              `${linkClasses} ${isActive ? activeClasses : inactiveClasses}`
            }
          >
            All Customers
          </NavLink>
          <NavLink
            to="/orders"
            className={({ isActive }) =>
              `${linkClasses} ${isActive ? activeClasses : inactiveClasses}`
            }
          >
            Orders
          </NavLink>
          <NavLink
            to="/sales"
            className={({ isActive }) =>
              `${linkClasses} ${isActive ? activeClasses : inactiveClasses}`
            }
          >
            Sales
          </NavLink>
          <NavLink
            to="/reports"
            className={({ isActive }) =>
              `${linkClasses} ${isActive ? activeClasses : inactiveClasses}`
            }
          >
            Reports
          </NavLink>
          <NavLink
            to="/inventory"
            className={({ isActive }) =>
              `${linkClasses} ${isActive ? activeClasses : inactiveClasses}`
            }
          >
            Inventory
          </NavLink>
          {/* later you can add Admin Management conditionally */}
        </nav>
      </div>

      <div className="mb-6 px-2">
        <a
          href="/admin/dashboard.php?logout=1"
          className="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold bg-red-50 text-red-700 hover:bg-red-100 text-sm"
        >
          Logout
        </a>
      </div>
    </aside>
  );
}
