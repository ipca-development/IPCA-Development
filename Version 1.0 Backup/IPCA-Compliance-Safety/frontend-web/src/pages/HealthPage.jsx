import React, { useEffect, useState } from "react"
import { apiGet } from "../api"

export default function HealthPage() {
  const [status, setStatus] = useState(null)

  useEffect(() => {
    apiGet("/health").then((data) => setStatus(data.status))
  }, [])

  return (
    <div className="p-4 bg-white rounded shadow max-w-xl mx-auto">
      <h2 className="text-xl font-semibold mb-2">Backend Health</h2>
      <p>Backend says: <strong>{status ?? "Loading..."}</strong></p>
    </div>
  )
}
