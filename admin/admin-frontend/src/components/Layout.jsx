import { useState } from "react";
import Sidebar from "./Sidebar.jsx";

export default function Layout({ children }) {
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <div className="min-h-screen bg-gray-100 flex flex-col md:flex-row">
      {/* Mobile header */}
      <header className="flex items-center justify-between bg-white border-b px-4 py-3 md:hidden">
        <div className="flex items-center gap-2">
          <img src="/img/cr8-logo.png" alt="Logo" className="w-8 h-8 rounded-full" />
          <span className="font-bold text-lg text-purple-800">CR8 Cebu</span>
        </div>
        <button
          className="text-purple-700 focus:outline-none"
          onClick={() => setMobileOpen(!mobileOpen)}
        >
          <svg
            className="w-7 h-7"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            viewBox="0 0 24 24"
          >
            <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </header>

      {/* Mobile sidebar */}
      {mobileOpen && (
        <div
          className="fixed inset-0 z-40 flex md:hidden"
          onClick={() => setMobileOpen(false)}
        >
          <div
            className="bg-black bg-opacity-30 flex-1"
            aria-hidden="true"
          />
          <div
            className="w-64 bg-white border-l flex-shrink-0"
            onClick={(e) => e.stopPropagation()}
          >
            <Sidebar />
          </div>
        </div>
      )}

      {/* Desktop sidebar */}
      <Sidebar />

      {/* Main content */}
      <main className="flex-1 flex flex-col min-h-screen pt-16 md:pt-0">
        <section className="p-4 md:p-8 flex-1">{children}</section>
      </main>
    </div>
  );
}
