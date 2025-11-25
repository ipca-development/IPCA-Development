import React, { useState } from "react"
import HealthPage from "./pages/HealthPage"
import AuditsPage from "./pages/AuditsPage"

export default function App() {
  const [page, setPage] = useState("audits")

  return (
    <div className="min-h-screen flex flex-col">
      <header className="bg-ipcaBlue text-white px-6 py-4 flex items-center justify-between shadow">
        <div>
          <h1 className="text-2xl font-semibold">IPCA Safety & Compliance</h1>
        </div>

        <nav className="space-x-3 text-sm">
          <button
            onClick={() => setPage("audits")}
            className={`px-3 py-1 rounded ${
              page === "audits" ? "bg-white text-ipcaBlue" : "border border-white"
            }`}
          >
            Audits
          </button>
          <button
            onClick={() => setPage("health")}
            className={`px-3 py-1 rounded ${
              page === "health" ? "bg-white text-ipcaBlue" : "border border-white"
            }`}
          >
            Health
          </button>
        </nav>
      </header>

      <main className="flex-1 p-6">
        {page === "health" && <HealthPage />}
        {page === "audits" && <AuditsPage />}
      </main>
    </div>
  )
}
