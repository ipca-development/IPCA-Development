import React, { useState } from "react"
import ComplianceDashboard from "./pages/ComplianceDashboard"
import ComplianceAudits from "./pages/ComplianceAudits"

export default function App() {
  const [tab, setTab] = useState("dashboard") // "dashboard" | "audits"

  return (
    <div className="min-h-screen flex flex-col">
      <header className="bg-ipcaBlue text-white px-6 py-4 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">IPCA – Compliance Center</h1>
          <p className="text-xs text-slate-200">
            AI-Managed Compliance, Manuals & Audit RCA/CAP
          </p>
        </div>
        <nav className="space-x-2 text-sm">
          <button
            onClick={() => setTab("dashboard")}
            className={`px-3 py-1 rounded ${
              tab === "dashboard"
                ? "bg-white text-ipcaBlue"
                : "border border-white"
            }`}
          >
            Compliance Dashboard
          </button>
          <button
            onClick={() => setTab("audits")}
            className={`px-3 py-1 rounded ${
              tab === "audits"
                ? "bg-white text-ipcaBlue"
                : "border border-white"
            }`}
          >
            Audits
          </button>
        </nav>
      </header>

      <main className="flex-1 p-6 bg-slate-100">
        {tab === "dashboard" && <ComplianceDashboard />}
        {tab === "audits" && <ComplianceAudits />}
      </main>
    </div>
  )
}