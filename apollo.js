/* Apollo Lyre - all logic.
   Spotify login uses Authorization Code with PKCE (no client secret needed). */

const CONFIG = {
  // 1. Paste your Client ID from the Spotify developer dashboard here.
  clientId: "0765fc3ceb4347e1984cb415e3e3ad5e",
  // 2. Register this exact URI in the dashboard (it is also shown on index.html).
  //    Open the site as http://127.0.0.1:PORT/index.html (localhost is not allowed).
  redirectUri: new URL("index.html", window.location.href).href,
  scopes: [
    "user-read-private",
    "user-top-read",
    "user-read-recently-played",
    "user-library-read",
    "user-library-modify",
    "playlist-read-private",
    "playlist-modify-public",
    "playlist-modify-private",
    "ugc-image-upload",
  ],
};

const API_BASE = "https://api.spotify.com/v1";

/* ---------- helpers ---------- */
const $ = (id) => document.getElementById(id);
const val = (id) => ($(id) ? $(id).value.trim() : "");
const store = {
  get: (k) => localStorage.getItem("apollo_" + k),
  set: (k, v) => localStorage.setItem("apollo_" + k, v),
  del: (k) => localStorage.removeItem("apollo_" + k),
};

function out(label, data) {
  const el = $("output");
  if (!el) return;
  const body = typeof data === "string" ? data : JSON.stringify(data, null, 2);
  el.textContent = label + "\n\n" + body;
  const v = $("visual");
  if (v) v.innerHTML = `<p class="note"><b>${esc(label)}</b>${typeof data === "string" && data ? "<br>" + esc(data) : ""}</p>`;
}

/* ---------- Spotify auth (PKCE) ---------- */
function randomString(length = 64) {
  const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~";
  const bytes = crypto.getRandomValues(new Uint8Array(length));
  return Array.from(bytes, (b) => chars[b % chars.length]).join("");
}

async function challengeFrom(verifier) {
  const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(verifier));
  return btoa(String.fromCharCode(...new Uint8Array(digest)))
    .replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}

async function connectSpotify() {
  if (CONFIG.clientId.startsWith("YOUR_")) { //this for admin reminder, not a security check
    out("Setup needed", "Paste your Spotify Client ID into CONFIG.clientId in apollo.js first.");
    return;
  }
  const verifier = randomString();
  const state = randomString(16);
  store.set("verifier", verifier);
  store.set("state", state);
  const params = new URLSearchParams({
    client_id: CONFIG.clientId,
    response_type: "code",
    redirect_uri: CONFIG.redirectUri,
    scope: CONFIG.scopes.join(" "),
    state,
    code_challenge_method: "S256",
    code_challenge: await challengeFrom(verifier),
  });
  window.location.href = "https://accounts.spotify.com/authorize?" + params;
}

async function tokenRequest(params) {
  const res = await fetch("https://accounts.spotify.com/api/token", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ client_id: CONFIG.clientId, ...params }),
  });
  const data = await res.json();
  if (!res.ok) {
    out("Token error", data);
    return false;
  }
  store.set("access_token", data.access_token);
  store.set("expires_at", String(Date.now() + data.expires_in * 1000 - 30000));
  if (data.refresh_token) store.set("refresh_token", data.refresh_token);
  return true;
}

async function handleCallback() {
  const q = new URLSearchParams(window.location.search);
  if (q.get("error")) {
    out("Spotify login error", { error: q.get("error") });
    history.replaceState({}, "", window.location.pathname);
    return;
  }
  const code = q.get("code");
  if (!code) return;
  if (q.get("state") !== store.get("state")) {
    out("Login error", "State mismatch. Try connecting again.");
    history.replaceState({}, "", window.location.pathname);
    return;
  }
  const ok = await tokenRequest({
    grant_type: "authorization_code",
    code,
    redirect_uri: CONFIG.redirectUri,
    code_verifier: store.get("verifier"),
  });
  history.replaceState({}, "", window.location.pathname);
  if (ok) out("Connected", "Spotify login succeeded. Try 'Test: get my profile'.");
}

async function getToken() {
  if (!store.get("access_token")) return null;
  if (Date.now() > Number(store.get("expires_at"))) {
    const refresh = store.get("refresh_token");
    if (!refresh) return null;
    const ok = await tokenRequest({ grant_type: "refresh_token", refresh_token: refresh });
    if (!ok) return null;
  }
  return store.get("access_token");
}

function disconnectSpotify() {
  ["access_token", "refresh_token", "expires_at", "verifier", "state"].forEach(store.del);
  updateStatus();
  out("Disconnected", "Spotify tokens removed from this browser.");
}

async function updateStatus() {
  const el = $("connection-status");
  if (el) {
    const on = !!(await getToken());
    el.textContent = on ? "Connected" : "Not connected";
    el.dataset.state = on ? "on" : "off";
  }
  const uri = $("redirect-uri");
  if (uri) uri.textContent = CONFIG.redirectUri;
}

/* ---------- Spotify API request ---------- */
async function spotify(method, path, { query, body } = {}) {
  const token = await getToken();
  if (!token) {
    out("Not connected", "Connect to Spotify first (button on index.html).");
    return;
  }
  const url = new URL(API_BASE + path);
  Object.entries(query || {}).forEach(([k, v]) => {
    if (v !== "" && v != null) url.searchParams.set(k, v);
  });
  try {
    const res = await fetch(url, {
      method,
      headers: {
        Authorization: "Bearer " + token,
        ...(body ? { "Content-Type": "application/json" } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
    });
    const text = await res.text();
    let data = text;
    try { data = text ? JSON.parse(text) : null; } catch (e) { /* keep raw text */ }
    const result = { status: res.status, ok: res.ok, data };
    out(`${method} ${url.pathname}${url.search}`, result);
    renderVisual(`${method} ${url.pathname}${url.search}`, result);
  } catch (err) {
    out("Request failed", String(err));
  }
}

/* ---------- API test actions (api.html and index.html) ---------- */
const actions = {
  connect: connectSpotify,
  disconnect: disconnectSpotify,
  clearOutput: () => out("Output cleared", ""),

  profile: () => spotify("GET", "/me"),

  topArtists: () => spotify("GET", "/me/top/artists", { query: { time_range: val("time_range"), limit: val("limit") } }),
  topTracks: () => spotify("GET", "/me/top/tracks", { query: { time_range: val("time_range"), limit: val("limit") } }),
  recent: () => spotify("GET", "/me/player/recently-played", { query: { limit: val("limit") } }),
  savedTracks: () => spotify("GET", "/me/tracks", { query: { limit: val("limit") } }),

  search: () => spotify("GET", "/search", { query: { q: val("search-q"), type: "track", limit: 10, offset: val("search-offset") } }),
  getArtist: () => spotify("GET", "/artists/" + val("artist-id")),
  getTrack: () => spotify("GET", "/tracks/" + val("track-id")),

  saveLibrary: () => spotify("PUT", "/me/library", { query: { uris: val("track-uri") } }),
  removeLibrary: () => spotify("DELETE", "/me/library", { query: { uris: val("track-uri") } }),
  checkLibrary: () => spotify("GET", "/me/library/contains", { query: { uris: val("track-uri") } }),

  myPlaylists: () => spotify("GET", "/me/playlists", { query: { limit: val("limit") } }),
  createPlaylist: () => spotify("POST", "/me/playlists", {
    body: { name: val("playlist-name"), description: val("playlist-desc"), public: $("playlist-public").checked },
  }),
  getPlaylistItems: () => spotify("GET", `/playlists/${val("playlist-id")}/items`),
  updatePlaylist: () => spotify("PUT", "/playlists/" + val("playlist-id"), {
    body: { name: val("playlist-name"), description: val("playlist-desc"), public: $("playlist-public").checked },
  }),
  addItem: () => spotify("POST", `/playlists/${val("playlist-id")}/items`, { body: { uris: [val("track-uri")] } }),
  // Body shape for removal is a best guess; check the output and adjust if Spotify rejects it.
  removeItem: () => spotify("DELETE", `/playlists/${val("playlist-id")}/items`, { body: { items: [{ uri: val("track-uri") }] } }),
  // Deleting a playlist means removing it from the user's library (unfollow).
  deletePlaylist: () => spotify("DELETE", "/me/library", { query: { uris: "spotify:playlist:" + val("playlist-id") } }),
};

/* ---------- Apollo account forms (login-signup.html) ---------- */
// No backend yet. Later these forms will post to PHP scripts that use MySQL
// (password_hash, sessions, user/admin roles).
function handleSignup(e) {
  e.preventDefault();
  const password = val("signup-password");
  if (password !== val("signup-confirm")) {
    out("Sign up", "Passwords do not match.");
    return;
  }
  out("Sign up (not connected to a backend yet)", {
    username: val("signup-username"),
    email: val("signup-email"),
    note: "PHP + MySQL registration will be added later.",
  });
}

function handleLogin(e) {
  e.preventDefault();
  out("Login (not connected to a backend yet)", {
    email: val("login-email"),
    note: "PHP + MySQL login will be added later.",
  });
}

function handleCustom(e) {
  e.preventDefault();
  const [path, search] = val("custom-path").split("?");
  const query = Object.fromEntries(new URLSearchParams(search || ""));
  let body;
  if (val("custom-body")) {
    try { body = JSON.parse(val("custom-body")); } catch (err) { out("Invalid JSON body", String(err)); return; }
  }
  spotify($("custom-method").value, path, { query, body });
}

/* ---------- visual output (api.html) ---------- */
const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const imgUrl = (o) => { const i = (o && o.images) || []; return (i[1] || i[0] || {}).url || ""; };
const mmss = (ms) => `${Math.floor(ms / 60000)}:${String(Math.floor(ms / 1000) % 60).padStart(2, "0")}`;

function bars(title, counts) {
  const rows = Object.entries(counts).sort((a, b) => b[1] - a[1]).slice(0, 8);
  if (!rows.length) return "";
  const max = rows[0][1];
  return `<div class="panel"><h3>${esc(title)}</h3>${rows.map(([k, v]) =>
    `<div class="bar"><span>${esc(k)}</span><div class="fill"><i style="width:${(v / max) * 100}%"></i></div><b>${v}</b></div>`).join("")}</div>`;
}

function card(o) {
  const t = o.type;
  const pic = imgUrl(t === "track" ? o.album : o);
  let sub = "", extra = "";
  if (t === "track") {
    sub = `<small>${esc((o.artists || []).map((a) => a.name).join(", "))}</small><small>${esc(o.album && o.album.name)}</small>`;
    extra = `<time>${mmss(o.duration_ms || 0)}</time>`;
  } else if (t === "artist") {
    sub = (o.genres || []).slice(0, 4).map((g) => `<em>${esc(g)}</em>`).join("") || "<small>No genres listed</small>";
  } else if (t === "playlist") {
    const n = (o.items && o.items.total) ?? (o.tracks && o.tracks.total);
    sub = `<small>${n ?? "?"} songs</small>`;
  } else {
    sub = `<small>${esc(o.id)}</small>`;
  }
  const a = (o.artists && o.artists[0]) || {};
  const act = t === "track" ? '<button type="button" data-card="save">Save to library</button><button type="button" data-card="add">Add to playlist</button>' : "";
  return `<div class="card" data-type="${esc(t)}" data-id="${esc(o.id)}" data-uri="${esc(o.uri)}" data-name="${esc(o.name)}" data-artist="${esc(a.id)}" data-artistname="${esc(a.name)}">` +
    (pic ? `<img src="${esc(pic)}" alt="" loading="lazy">` : '<div class="ph"></div>') +
    `<div><strong>${esc(o.name || o.display_name)}</strong>${sub}<div class="genres"></div><div class="actions">${act}</div>${t === "user" ? "" : tagsHtml(o.uri)}</div>${extra}</div>`;
}

function renderVisual(label, res) {
  const box = $("visual");
  if (!box) return;
  const d = res.data;
  let html = `<div class="status ${res.ok ? "" : "bad"}"><b>${res.status}</b><span>${esc(label)}</span></div>`;
  if (!res.ok) {
    const msg = d && d.error && (d.error.message || d.error.description || d.error);
    const hint = {
      0: "Not connected, or the request was blocked. Connect on index.html and open this page from the same address.",
      401: "Your login expired or is invalid. Click Disconnect on index.html, then connect to Spotify again.",
      403: "Spotify refused the request. This Spotify account may not be on the app's user list, or the app owner's Premium may have lapsed.",
      429: "Too many requests. Wait a minute and try again.",
    }[res.status] || "";
    box.innerHTML = html + `<p class="note">${esc(msg || "Request failed. See the raw response below.")}</p>` + (hint ? `<p class="note">${esc(hint)}</p>` : "");
    return;
  }
  const raw = (d && ((d.tracks && d.tracks.items) || (d.artists && d.artists.items) ||
    (Array.isArray(d.items) && d.items.map((x) => x.track || x.item || x)))) || (d && d.type ? [d] : []);
  const list = raw.filter((x) => x && ["track", "artist", "playlist", "user"].includes(x.type));
  if (!list.length) {
    box.innerHTML = html + '<p class="note">Request succeeded. Nothing to draw, see the raw response below.</p>';
    return;
  }
  const tracks = list.filter((x) => x.type === "track");
  const artists = list.filter((x) => x.type === "artist");
  if (tracks.length) {
    const avg = tracks.reduce((s, x) => s + (x.duration_ms || 0), 0) / tracks.length;
    const explicit = tracks.filter((x) => x.explicit).length;
    html += `<div class="stats"><div><b>${tracks.length}</b><span>songs</span></div><div><b>${mmss(avg)}</b><span>average length</span></div><div><b>${explicit}</b><span>explicit</span></div></div>`;
    const byArtist = {};
    tracks.forEach((t) => (t.artists || []).forEach((a) => { byArtist[a.name] = (byArtist[a.name] || 0) + 1; }));
    html += bars("Artists in this result", byArtist);
  }
  if (artists.length) {
    const byGenre = {};
    artists.forEach((a) => (a.genres || []).forEach((g) => { byGenre[g] = (byGenre[g] || 0) + 1; }));
    html += bars("Genres in this result", byGenre) || '<p class="note">No genre data returned for these artists.</p>';
  }
  box.innerHTML = html + `<div class="cards">${list.map(card).join("")}</div>`;
  fillGenres(box);
}

// Click a card to fill the matching form fields on api.html
document.addEventListener("click", (e) => {
  const c = e.target.closest && e.target.closest(".card");
  if (!c) return;
  const { type, id, uri } = c.dataset;
  const set = (k, v) => { if ($(k) && v) $(k).value = v; };
  if (type === "track") { set("track-id", id); set("track-uri", uri); }
  if (type === "artist") set("artist-id", id);
  if (type === "playlist") set("playlist-id", id);
  document.querySelectorAll(".card.picked").forEach((x) => x.classList.remove("picked"));
  c.classList.add("picked");
});

/* ---------- workspace (api.html) ---------- */
const artistCache = new Map();
const errMsg = (r) => (r.data && r.data.error && (r.data.error.message || r.data.error)) || "status " + r.status;
const toast = (msg, bad) => { const t = $("toast"); if (t) { t.textContent = msg; t.className = "toast " + (bad ? "bad" : "ok"); } };
const rawOut = (r) => { const el = $("output"); if (el) el.textContent = (r.label || "") + "\n\n" + JSON.stringify(r, null, 2); };
const show = (r) => { out(r.label || "Result", r); renderVisual(r.label || "Result", r); };

// Quiet API call: returns {ok, status, data, label} and prints nothing.
async function call(method, path, { query, body, raw } = {}) {
  const token = await getToken();
  if (!token) return { ok: false, status: 0, data: { error: { message: "Connect to Spotify first (button on index.html)." } } };
  const url = new URL(API_BASE + path);
  Object.entries(query || {}).forEach(([k, v]) => { if (v !== "" && v != null) url.searchParams.set(k, v); });
  const headers = { Authorization: "Bearer " + token };
  if (body) headers["Content-Type"] = raw ? "image/jpeg" : "application/json";
  try {
    const res = await fetch(url, { method, headers, body: body ? (raw ? body : JSON.stringify(body)) : undefined });
    const text = await res.text();
    let data = text;
    try { data = text ? JSON.parse(text) : null; } catch (e) { /* keep text */ }
    return { ok: res.ok, status: res.status, data, label: `${method} ${url.pathname}${url.search}` };
  } catch (err) {
    return { ok: false, status: 0, data: { error: { message: String(err) } } };
  }
}

/* Tags: Spotify has no tag feature, so tags are kept in this browser for now (MySQL later). */
const tagStore = { all: () => JSON.parse(store.get("tags") || "{}"), save: (t) => store.set("tags", JSON.stringify(t)) };
const tagsInner = (list) => list.map((x) => `<em title="Click to remove">${esc(x)}</em>`).join("") + '<input type="text" class="tag-input" placeholder="+ tag" maxlength="20">';
const tagsHtml = (key) => `<div class="tags" data-key="${esc(key)}">${tagsInner(tagStore.all()[key] || [])}</div>`;

document.addEventListener("keydown", (e) => {
  if (e.key !== "Enter" || !e.target.classList || !e.target.classList.contains("tag-input")) return;
  e.preventDefault();
  const tag = e.target.value.trim().toLowerCase();
  if (!tag) return;
  const box = e.target.closest(".tags"), all = tagStore.all(), key = box.dataset.key;
  all[key] = [...new Set([...(all[key] || []), tag])];
  tagStore.save(all);
  box.innerHTML = tagsInner(all[key]);
  box.querySelector("input").focus();
});

document.addEventListener("click", (e) => {
  if (!(e.target.matches && e.target.matches(".tags em"))) return;
  const box = e.target.closest(".tags"), all = tagStore.all(), key = box.dataset.key;
  all[key] = (all[key] || []).filter((x) => x !== e.target.textContent);
  if (!all[key].length) delete all[key];
  tagStore.save(all);
  box.innerHTML = tagsInner(all[key] || []);
});

/* Genres: Spotify gives genres on artists only, so a song's genres are its artist's genres. */
async function artistGenres(id) {
  if (!artistCache.has(id)) {
    const r = await call("GET", "/artists/" + id);
    artistCache.set(id, r.ok && r.data ? r.data.genres || [] : []);
  }
  return artistCache.get(id);
}

async function fillGenres(root) {
  for (const c of root.querySelectorAll('.card[data-type="track"]')) {
    if (!c.dataset.artist) continue;
    const g = await artistGenres(c.dataset.artist);
    const box = c.querySelector(".genres");
    if (box) box.innerHTML = g.slice(0, 3).map((x) => `<em>${esc(x)}</em>`).join("") || "<small>No genres listed</small>";
  }
}

/* Recommendations: Spotify's recommendation endpoint is gone, so suggestions come from search. */
async function recommend(c) {
  const box = $("recs");
  if (!box) return;
  box.innerHTML = '<p class="note">Finding songs you might like...</p>';
  const genres = c.dataset.artist ? await artistGenres(c.dataset.artist) : [];
  const queries = [`artist:"${c.dataset.artistname}"`];
  if (genres[0]) queries.push(`genre:"${genres[0]}"`);
  const found = await Promise.all(queries.map((q) => call("GET", "/search", { query: { q, type: "track", limit: 8 } })));
  const seen = new Set([c.dataset.uri]), picks = [];
  found.forEach((r) => ((r.ok && r.data && r.data.tracks && r.data.tracks.items) || []).forEach((t) => {
    if (t && !seen.has(t.uri)) { seen.add(t.uri); picks.push(t); }
  }));
  box.innerHTML = picks.length
    ? `<div class="panel"><h3>You might also like</h3><p class="hint">Based on the artist${genres[0] ? " and genre " + esc(genres[0]) : ""} of the song you just saved.</p><div class="cards">${picks.slice(0, 8).map(card).join("")}</div></div>`
    : '<p class="note">No suggestions found.</p>';
  fillGenres(box);
}

/* Buttons on song cards */
document.addEventListener("click", async (e) => {
  const b = e.target.closest && e.target.closest("[data-card]");
  if (!b) return;
  const c = b.closest(".card");
  if (b.dataset.card === "add") {
    const pid = val("playlist-select");
    if (!pid) return toast("Choose or create a playlist first.", true);
    const r = await call("POST", `/playlists/${pid}/items`, { body: { uris: [c.dataset.uri] } });
    toast(r.ok ? `Added "${c.dataset.name}" to the playlist.` : "Could not add the song: " + errMsg(r), !r.ok);
  }
  if (b.dataset.card === "save") {
    const r = await call("PUT", "/me/library", { query: { uris: c.dataset.uri } });
    toast(r.ok ? `Saved "${c.dataset.name}" to your library.` : "Could not save the song: " + errMsg(r), !r.ok);
    if (r.ok) recommend(c);
  }
});

/* Playlists */
function showPlaylistTags() {
  const box = $("playlist-tags"), pid = val("playlist-select");
  if (box) box.innerHTML = pid ? tagsHtml("spotify:playlist:" + pid) : "";
}

async function loadPlaylists(selectId) {
  const sel = $("playlist-select");
  if (!sel) return;
  const r = await call("GET", "/me/playlists", { query: { limit: 50 } });
  if (!r.ok) return toast("Could not load playlists: " + errMsg(r), true);
  sel.innerHTML = '<option value="">Choose a playlist</option>' +
    (r.data.items || []).filter(Boolean).map((p) => `<option value="${esc(p.id)}">${esc(p.name)}</option>`).join("");
  if (selectId) sel.value = selectId;
  showPlaylistTags();
}

function resizeToJpeg(file) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => {
      const c = document.createElement("canvas");
      c.width = c.height = 300;
      const s = Math.min(img.width, img.height);
      c.getContext("2d").drawImage(img, (img.width - s) / 2, (img.height - s) / 2, s, s, 0, 0, 300, 300);
      resolve(c.toDataURL("image/jpeg", 0.85).split(",")[1]);
    };
    img.onerror = () => reject(new Error("Could not read the image."));
    img.src = URL.createObjectURL(file);
  });
}

Object.assign(actions, {
  search: async () => {
    $("visual").innerHTML = '<p class="note">Searching...</p>';
    show(await call("GET", "/search", { query: { q: val("search-q"), type: val("search-type"), limit: 10 } }));
  },

  loadPlaylists: () => loadPlaylists(),

  createPlaylist: async () => {
    const name = val("playlist-name");
    if (!name) return toast("Enter a playlist name.", true);
    const r = await call("POST", "/me/playlists", { body: { name, description: val("playlist-desc"), public: $("playlist-public").checked } });
    rawOut(r);
    if (!r.ok) return toast("Could not create the playlist: " + errMsg(r), true);
    toast(`Created "${name}". Search for songs and use Add to playlist.`);
    await loadPlaylists(r.data.id);
    if ($("cover-file").files[0]) await actions.uploadCover();
  },

  viewPlaylist: async () => {
    const pid = val("playlist-select");
    if (!pid) return toast("Choose a playlist first.", true);
    show(await call("GET", `/playlists/${pid}/items`));
  },

  uploadCover: async () => {
    const pid = val("playlist-select"), file = $("cover-file").files[0];
    if (!pid || !file) return toast("Choose a playlist and a picture first.", true);
    try {
      const r = await call("PUT", `/playlists/${pid}/images`, { body: await resizeToJpeg(file), raw: true });
      rawOut(r);
      toast(r.ok ? "Cover picture uploaded. It can take a moment to appear in Spotify." : "Upload failed: " + errMsg(r) + " (reconnect to Spotify to grant image upload access)", !r.ok);
    } catch (err) { toast(String(err.message || err), true); }
  },

  genreTracks: async () => {
    const r = await call("GET", "/me/top/tracks", { query: { time_range: val("time_range"), limit: 20 } });
    if (!r.ok) return show(r);
    const ids = [...new Set((r.data.items || []).flatMap((t) => t.artists.map((a) => a.id)))].slice(0, 25);
    const counts = {};
    for (const id of ids) (await artistGenres(id)).forEach((g) => { counts[g] = (counts[g] || 0) + 1; });
    rawOut(r);
    $("visual").innerHTML = `<div class="status"><b>${r.status}</b><span>Genres across the artists of your top songs (${ids.length} artists)</span></div>` +
      (bars("Artists per genre", counts) || '<p class="note">No genres returned.</p>');
  },

  myTags: () => {
    const counts = {};
    Object.values(tagStore.all()).forEach((list) => list.forEach((t) => { counts[t] = (counts[t] || 0) + 1; }));
    $("visual").innerHTML = bars("Your tags (items per tag)", counts) || '<p class="note">No tags yet. Add tags on song, artist or playlist cards.</p>';
  },
});

document.addEventListener("DOMContentLoaded", async () => {
  if (document.body.dataset.page !== "api") return;
  if ($("search-form")) $("search-form").addEventListener("submit", (e) => { e.preventDefault(); actions.search(); });
  if ($("playlist-select")) $("playlist-select").addEventListener("change", showPlaylistTags);
  if (await getToken()) loadPlaylists();
});

/* ---------- page setup ---------- */
document.addEventListener("click", (e) => {
  const action = e.target.dataset && e.target.dataset.action;
  if (action && actions[action]) actions[action]();
});

document.addEventListener("DOMContentLoaded", async () => {
  if ($("signup-form")) $("signup-form").addEventListener("submit", handleSignup);
  if ($("login-form")) $("login-form").addEventListener("submit", handleLogin);
  if ($("custom-form")) $("custom-form").addEventListener("submit", handleCustom);
  if (document.body.dataset.page === "index") await handleCallback();
  updateStatus();
});
