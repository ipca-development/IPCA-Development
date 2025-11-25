const API_BASE = "http://localhost:8888"

export async function apiGet(path) {
  const res = await fetch(`${API_BASE}${path}`)
  if (!res.ok) throw new Error("GET failed")
  return res.json()
}

export async function apiPost(path, data) {
  const res = await fetch(`${API_BASE}${path}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
  if (!res.ok) throw new Error("POST failed")
  return res.json()
}
