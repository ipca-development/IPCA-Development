const BASE_URL = "http://localhost:8888"

export async function apiGet(path) {
  const res = await fetch(BASE_URL + path)
  if (!res.ok) {
    throw new Error(`GET ${path} failed with ${res.status}`)
  }
  return res.json()
}

export async function apiPost(path, body) {
  const res = await fetch(BASE_URL + path, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body || {}),
  })

  const txt = await res.text()

  if (!res.ok) {
    console.error("POST failed", path, "HTTP", res.status, txt)
    throw new Error(txt || `POST ${path} failed with ${res.status}`)
  }

  // Some endpoints may return empty body; handle safely
  if (!txt) return {}

  try {
    return JSON.parse(txt)
  } catch (e) {
    console.error("POST non-JSON response", path, txt)
    throw new Error("Server returned non-JSON response: " + txt.slice(0, 200))
  }
}

export async function apiPatch(path, body) {
  const res = await fetch(BASE_URL + path, {
    method: "PATCH",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body || {}),
  })
  if (!res.ok) {
    const txt = await res.text()
    console.error("PATCH error", txt)
    throw new Error(`PATCH ${path} failed with ${res.status}`)
  }
  return res.json()
}