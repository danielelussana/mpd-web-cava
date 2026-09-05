<?php
declare(strict_types=1);

/**
 * Lightweight MPD web client with playlist management.
 *
 * Environment variables:
 * - MPD_HOST (default 127.0.0.1)
 * - MPD_PORT (default 6600)
 * - MPD_MPC_BIN (default /usr/bin/mpc)
 * - MPD_SPECTRUM_URL (default http://127.0.0.1:44883/bars)
 * - MPD_SPECTRUM_SCRIPT (default /var/www/html/mpd-spectrum.py)
 * - MPD_PYTHON_BIN (default /usr/bin/python3)
 */

$config = [
    'mpd_host' => getenv('MPD_HOST') ?: '127.0.0.1',
    'mpd_port' => (int) (getenv('MPD_PORT') ?: 6600),
    'mpc_bin' => getenv('MPD_MPC_BIN') ?: '/usr/bin/mpc',
    'spectrum_url' => getenv('MPD_SPECTRUM_URL') ?: 'http://127.0.0.1:44883/bars',
    'spectrum_script' => getenv('MPD_SPECTRUM_SCRIPT') ?: '/opt/mpd-web/mpd-spectrum.py',
    'python_bin' => getenv('MPD_PYTHON_BIN') ?: '/usr/bin/python3',
];

if (isset($_GET['api'])) {
    handleApi((string) $_GET['api'], $config);
    exit;
}

ensureSpectrumService($config);
renderRedesignedPage();

function renderRedesignedPage(): void
{
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>mpd remote</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Mono:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
<style>
:root {
  --bg:      #0f1114;
  --surface: #111418;
  --border:  #1e2228;
  --accent:  #e8c547;
  --accent2: #4ecdc4;
  --text:    #e8eaed;
  --sub:     #5a6472;
  --muted:   #3d4450;
  --danger:  #e05c5c;
  --radius:  4px;
  --mono:    'DM Mono', monospace;
  --sidebar-width: 310px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--mono); font-size: 13px; overflow: hidden; }

body::after {
  content: '';
  position: fixed; inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='300' height='300' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
  background-size: 300px 300px;
  pointer-events: none; z-index: 9999;
}
.blob {
  position: fixed; border-radius: 50%; pointer-events: none; z-index: 0;
  width: 55vw; height: 55vw; max-width: 620px; max-height: 620px;
  background: radial-gradient(circle, rgba(232,197,71,.07) 0%, transparent 70%);
  top: -18%; right: -8%;
  animation: blobPulse 10s ease-in-out infinite;
}
.blob2 {
  position: fixed; border-radius: 50%; pointer-events: none; z-index: 0;
  width: 35vw; height: 35vw; max-width: 340px; max-height: 340px;
  background: radial-gradient(circle, rgba(78,205,196,.05) 0%, transparent 70%);
  bottom: 5%; left: 3%;
  animation: blobPulse 14s ease-in-out infinite reverse;
}
@keyframes blobPulse { 0%,100%{transform:scale(1);opacity:.5} 50%{transform:scale(1.1);opacity:.9} }

/* ── Shell ────────────────────────────────────────── */
.shell {
  position: relative; z-index: 1;
  display: grid;
  grid-template-columns: var(--sidebar-width) 7px 1fr;
  grid-template-rows: auto 1fr;
  grid-template-areas: "hdr hdr hdr" "side grip main";
  height: 100vh;
  overflow: hidden;
}

/* ── Header ───────────────────────────────────────── */
header {
  grid-area: hdr;
  background: rgba(17,20,24,.92);
  border-bottom: 1px solid var(--border);
  backdrop-filter: blur(12px);
  padding: 12px 22px;
  display: flex; align-items: center; gap: 12px;
  flex-wrap: wrap;
}
.logo { font-family: 'DM Serif Display', serif; font-size: 1.3rem; line-height: 1; color: #fff; flex-shrink: 0; }
.logo em { font-style: italic; color: var(--accent); }
.status-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--muted); flex-shrink: 0; transition: background .3s; }
.status-dot.play  { background: var(--accent2); box-shadow: 0 0 8px var(--accent2); animation: dotPulse 2s infinite; }
.status-dot.pause { background: var(--accent); }
.status-dot.stop  { background: var(--accent); }
.status-dot.error { background: var(--danger); box-shadow: 0 0 8px var(--danger); }
@keyframes dotPulse { 0%,100%{opacity:1} 50%{opacity:.4} }
.conn-text { font-size: 10px; color: var(--sub); flex-shrink: 0; white-space: nowrap; }
.output-group { display: flex; gap: 4px; flex-shrink: 0; }
.out-btn { background: none; border: 1px solid var(--border); border-radius: var(--radius); color: var(--muted); font-family: var(--mono); font-size: 10px; padding: 5px 9px; cursor: pointer; transition: border-color .15s, color .15s; }
.out-btn:hover { border-color: var(--sub); color: var(--text); }
.out-btn.on { border-color: var(--accent2); color: var(--accent2); }
.vol-wrap { display: flex; align-items: center; gap: 6px; color: var(--muted); flex-shrink: 0; }
.vol-wrap svg { width: 13px; height: 13px; fill: currentColor; }
input[type=range] { -webkit-appearance: none; width: 84px; height: 2px; background: var(--border); border-radius: 99px; cursor: pointer; outline: none; }
input[type=range]::-webkit-slider-thumb { -webkit-appearance: none; width: 11px; height: 11px; border-radius: 50%; background: var(--accent); cursor: pointer; }
#volLabel { font-size: 10px; color: var(--muted); width: 30px; flex-shrink: 0; }

.progress-wrap { flex: 1; min-width: 150px; display: flex; flex-direction: column; gap: 4px; }
.progress-bar { height: 2px; background: var(--border); border-radius: 99px; cursor: pointer; transition: height .15s; }
.progress-bar:hover { height: 4px; }
.progress-fill { height: 100%; background: var(--accent); border-radius: 99px; transition: width .5s linear; width: 0%; }
.progress-times { display: grid; grid-template-columns: 1fr auto 1fr; gap: 6px; font-size: 9px; color: var(--muted); }
.progress-times span:first-child { text-align: left; }
.progress-times span:last-child { text-align: right; }
.queue-meta { text-align: center; white-space: nowrap; }

.controls { display: flex; align-items: center; gap: 2px; flex-shrink: 0; }
.btn { background: none; border: none; cursor: pointer; padding: 6px; border-radius: var(--radius); color: var(--sub); transition: background .15s, color .15s; line-height: 0; }
.btn:hover { background: rgba(255,255,255,.06); color: var(--text); }
.btn.active { color: var(--accent); }
.btn svg { width: 17px; height: 17px; fill: currentColor; }
.btn.big svg { width: 22px; height: 22px; }
.btn.teal { color: var(--accent2); }
.btn.teal:hover { color: #fff; }
.btn.mode-txt { font-family: var(--mono); font-size: 10px; font-weight: 600; line-height: 1; padding: 7px 9px; }

/* ── Sidebar ──────────────────────────────────────── */
aside { grid-area: side; background: rgba(17,20,24,.75); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
.sidebar-resizer { grid-area: grip; position: relative; z-index: 5; cursor: col-resize; background: #16191d; touch-action: none; user-select: none; }
.sidebar-resizer::after { content: ''; position: absolute; width: 1px; height: 34px; left: 3px; top: 50%; transform: translateY(-50%); background: var(--border); transition: background .15s; }
.sidebar-resizer:hover::after, .sidebar-resizer.dragging::after { background: var(--sub); }
body.resizing-sidebar { cursor: col-resize; user-select: none; }
body.resizing-sidebar aside, body.resizing-sidebar main { pointer-events: none; }

.sidebar-tabs { display: flex; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.tab-btn { flex: 1; background: none; border: none; cursor: pointer; color: var(--muted); font-family: var(--mono); font-size: 9px; letter-spacing: .15em; text-transform: uppercase; padding: 11px 4px; border-bottom: 2px solid transparent; transition: color .15s, border-color .15s; }
.tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
.tab-panel { display: none; flex: 1; overflow-y: auto; flex-direction: column; }
.tab-panel.active { display: flex; }

/* Queue */
.queue-actions { padding: 8px 10px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.act-btn { width: 100%; background: none; border: 1px solid var(--border); border-radius: var(--radius); color: var(--muted); font-family: var(--mono); font-size: 10px; padding: 5px 8px; cursor: pointer; transition: color .15s, border-color .15s; }
.act-btn:hover { color: var(--danger); border-color: var(--danger); }
.pl-item { display: flex; align-items: center; padding: 8px 10px; gap: 8px; cursor: pointer; transition: background .1s; }
.pl-item:hover { background: rgba(255,255,255,.03); }
.pl-item.current { background: rgba(232,197,71,.07); }
.pl-item.current .pi-title { color: var(--accent); }
.pi-num { color: var(--muted); font-size: 10px; width: 18px; text-align: right; flex-shrink: 0; }
.pi-info { flex: 1; min-width: 0; }
.pi-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 12px; }
.pi-sub   { font-size: 10px; color: var(--sub); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pi-actions { display: flex; gap: 1px; opacity: 0; transition: opacity .15s; flex-shrink: 0; }
.pl-item:hover .pi-actions { opacity: 1; }
.pi-btn { background: none; border: none; cursor: pointer; color: var(--muted); padding: 3px 4px; border-radius: 3px; font-size: 10px; transition: color .15s; }
.pi-btn:hover { color: var(--text); }
.pi-btn.del:hover { color: var(--danger); }

/* Search */
.search-box { padding: 10px; border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; gap: 6px; }
.search-box input { flex: 1; min-width: 0; background: rgba(0,0,0,.3); border: 1px solid var(--border); color: var(--text); font-family: var(--mono); font-size: 12px; padding: 7px 10px; outline: none; border-radius: var(--radius); transition: border-color .2s; }
.search-box input:focus { border-color: var(--sub); }
.search-go { background: var(--accent); border: none; border-radius: var(--radius); color: var(--bg); font-family: var(--mono); font-size: 10px; font-weight: 500; letter-spacing: .1em; padding: 0 14px; cursor: pointer; flex-shrink: 0; transition: opacity .15s; }
.search-go:hover { opacity: .85; }
.sr-item { display: flex; align-items: center; padding: 8px 10px; gap: 8px; transition: background .1s; }
.sr-item:hover { background: rgba(255,255,255,.03); }
.sr-info { flex: 1; min-width: 0; }
.sr-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 12px; }
.sr-sub   { font-size: 10px; color: var(--sub); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sr-add { background: none; border: 1px solid var(--border); border-radius: var(--radius); color: var(--muted); font-size: 13px; line-height: 1; padding: 3px 8px; cursor: pointer; flex-shrink: 0; transition: background .15s, color .15s, border-color .15s; }
.sr-add:hover { background: var(--accent); color: var(--bg); border-color: var(--accent); }

/* Library */
.lib-subtabs { display: flex; gap: 6px; padding: 8px 10px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.lib-subtab { flex: 1; background: none; border: 1px solid var(--border); border-radius: var(--radius); color: var(--muted); font-family: var(--mono); font-size: 9px; letter-spacing: .08em; text-transform: uppercase; padding: 6px 4px; cursor: pointer; transition: color .15s, border-color .15s; }
.lib-subtab.active { color: var(--accent); border-color: var(--accent); }
.libsub { display: none; flex: 1; flex-direction: column; overflow-y: auto; min-height: 0; }
.libsub.active { display: flex; }
.save-box { padding: 10px; border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; gap: 6px; }
.save-box input { flex: 1; min-width: 0; background: rgba(0,0,0,.3); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 12px; padding: 7px 10px; outline: none; transition: border-color .2s; }
.save-box input:focus { border-color: var(--sub); }
.save-box button { background: none; border: 1px solid var(--border); border-radius: var(--radius); color: var(--accent); font-family: var(--mono); font-size: 10px; padding: 6px 10px; cursor: pointer; white-space: nowrap; transition: background .15s, border-color .15s; }
.save-box button:hover { background: rgba(232,197,71,.1); border-color: var(--accent); }
.saved-pl { border-bottom: 1px solid var(--border); }
.saved-pl-hdr { display: flex; align-items: center; padding: 10px 10px; gap: 4px; cursor: pointer; transition: background .1s; }
.saved-pl-hdr:hover { background: rgba(255,255,255,.03); }
.spl-name { flex: 1; font-size: 12px; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.spl-arrow { color: var(--muted); font-size: 11px; transition: transform .2s; flex-shrink: 0; }
.saved-pl.open .spl-arrow { transform: rotate(90deg); }
.spl-btn { background: none; border: none; cursor: pointer; color: var(--muted); font-size: 11px; padding: 3px 5px; border-radius: 3px; transition: color .15s; flex-shrink: 0; }
.spl-btn.load:hover   { color: var(--accent2); }
.spl-btn.append:hover { color: var(--accent2); }
.spl-btn.update:hover { color: var(--accent); }
.spl-btn.del:hover    { color: var(--danger); }
.saved-pl-tracks { display: none; }
.saved-pl.open .saved-pl-tracks { display: block; }
.spt-item { display: flex; align-items: center; padding: 6px 10px 6px 22px; gap: 8px; border-top: 1px solid rgba(255,255,255,.04); transition: background .1s; }
.spt-item:hover { background: rgba(255,255,255,.02); }
.spt-info { flex: 1; min-width: 0; }
.spt-title { font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.spt-sub   { font-size: 9px; color: var(--sub); }
.spt-play { background: none; border: none; cursor: pointer; color: var(--muted); padding: 2px 4px; border-radius: 3px; font-size: 10px; flex-shrink: 0; transition: color .15s; }
.spt-play:hover { color: var(--accent2); }
.spt-del { background: none; border: none; cursor: pointer; color: var(--muted); padding: 2px 4px; border-radius: 3px; font-size: 11px; opacity: 0; transition: opacity .15s, color .15s; }
.spt-item:hover .spt-del { opacity: 1; }
.spt-del:hover { color: var(--danger); }

/* Browse */
.breadcrumb-row { display: flex; align-items: center; gap: 8px; padding: 10px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.crumb-btn { background: none; border: 1px solid var(--border); border-radius: var(--radius); color: var(--muted); cursor: pointer; padding: 4px 8px; font-size: 11px; transition: border-color .15s, color .15s; }
.crumb-btn:hover { border-color: var(--sub); color: var(--text); }
.crumb-btn:disabled { opacity: .35; cursor: default; }
.crumb-path { flex: 1; min-width: 0; font-size: 10px; color: var(--sub); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lib-item { display: flex; align-items: center; padding: 8px 10px; gap: 8px; transition: background .1s; }
.lib-item:hover { background: rgba(255,255,255,.03); }
.lib-icon { color: var(--muted); font-size: 11px; width: 14px; text-align: center; flex-shrink: 0; }
.lib-info { flex: 1; min-width: 0; }
.lib-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 12px; }
.lib-sub   { font-size: 10px; color: var(--sub); }
.lib-actions { display: flex; gap: 4px; flex-shrink: 0; align-items: center; }
.lib-open { background: none; border: 1px solid var(--border); border-radius: var(--radius); color: var(--muted); font-size: 10px; padding: 3px 8px; cursor: pointer; transition: border-color .15s, color .15s; }
.lib-open:hover { border-color: var(--sub); color: var(--text); }

/* Main */
main { grid-area: main; overflow-y: auto; padding: 22px; display: flex; flex-direction: column; gap: 14px; }
.card { background: rgba(17,20,24,.6); border: 1px solid var(--border); border-radius: 6px; padding: 16px; }
.card-title { font-size: 9px; letter-spacing: .2em; text-transform: uppercase; color: var(--muted); margin-bottom: 14px; }
.now-row { display: flex; gap: 16px; margin-bottom: 14px; }
.cover-thumb-wrap { position: relative; width: 64px; height: 64px; border-radius: 6px; overflow: hidden; background: var(--bg); border: 1px solid var(--border); flex-shrink: 0; }
.cover-thumb { width: 100%; height: 100%; object-fit: cover; display: block; }
.cover-thumb.hidden { display: none; }
.cover-thumb-empty { position: absolute; inset: 0; display: grid; place-items: center; color: var(--muted); font-size: 20px; }
.cover-thumb-empty.hidden { display: none; }
.now-info { flex: 1; min-width: 0; align-self: center; }
.now-title  { font-size: 17px; font-weight: 500; color: #fff; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.now-artist { font-size: 12px; color: var(--accent); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.now-album  { font-size: 11px; color: var(--sub); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.now-file   { font-size: 10px; color: var(--muted); margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; }
.stat { background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 10px; }
.stat-lbl { font-size: 8px; color: var(--muted); text-transform: uppercase; letter-spacing: .1em; margin-bottom: 5px; }
.stat-val { font-size: 14px; font-weight: 500; color: #fff; }
.stat-val.ac { color: var(--accent); }

/* toast */
#toast { position: fixed; bottom: 22px; left: 50%; transform: translateX(-50%) translateY(60px); background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 8px 18px; font-size: 11px; color: var(--text); z-index: 9000; transition: transform .25s ease, opacity .25s ease; opacity: 0; pointer-events: none; max-width: 90vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
#toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.65); backdrop-filter: blur(6px); z-index: 8000; display: flex; align-items: center; justify-content: center; }
.modal { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 20px; width: min(300px, 90vw); max-height: 80vh; overflow-y: auto; }
.modal-title { font-size: 9px; letter-spacing: .2em; text-transform: uppercase; color: var(--muted); margin-bottom: 14px; }
.modal-opt { padding: 10px 12px; border-radius: var(--radius); cursor: pointer; font-size: 12px; transition: background .15s; }
.modal-opt:hover { background: rgba(255,255,255,.06); }
.modal-opt.queue-opt { color: var(--accent); }
.modal-sep { border: none; border-top: 1px solid var(--border); margin: 8px 0; }
.modal-cancel { margin-top: 12px; width: 100%; background: none; border: 1px solid var(--border); border-radius: var(--radius); color: var(--muted); font-family: var(--mono); font-size: 10px; padding: 8px; cursor: pointer; transition: border-color .15s; }
.modal-cancel:hover { border-color: var(--sub); }

.empty { padding: 22px; text-align: center; color: var(--muted); font-size: 11px; }

/* Visualizzatore */
.cava-card { padding: 0; overflow: hidden; }
.cava-card .card-title { padding: 12px 16px 8px; margin-bottom: 0; }
canvas.cava-cv { display: block; width: 100%; height: 80px; }
#cavaDesk { height: 240px; }
.cava-status { padding: 4px 16px 10px; font-size: 10px; color: var(--muted); display: flex; align-items: center; gap: 7px; }
.cava-led { width: 5px; height: 5px; border-radius: 50%; background: var(--muted); flex-shrink: 0; transition: background .3s; }
.cava-led.live { background: var(--accent2); box-shadow: 0 0 5px var(--accent2); animation: dotPulse 1.5s infinite; }

/* Mobile track panel */
#mob-track {
  display: none; flex-direction: column; gap: 2px;
  padding: 10px 14px 8px; flex-shrink: 0;
  background: rgba(17,20,24,.95); border-bottom: 1px solid var(--border);
}
#mob-track .m-title  { font-size: 14px; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#mob-track .m-artist { font-size: 11px; color: var(--accent); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#mob-track .m-album  { font-size: 10px; color: var(--sub); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mob-prog-wrap { margin-top: 6px; display: flex; flex-direction: column; gap: 3px; }
.mob-prog-bar  { height: 2px; background: var(--border); border-radius: 99px; cursor: pointer; }
.mob-prog-fill { height: 100%; background: var(--accent); border-radius: 99px; transition: width .5s linear; width: 0%; }
.mob-prog-times { display: grid; grid-template-columns: 1fr auto 1fr; gap: 6px; font-size: 9px; color: var(--muted); }
.mob-prog-times span:first-child { text-align: left; }
.mob-prog-times span:last-child { text-align: right; }

#mob-cava { display: none; flex-shrink: 0; border-bottom: 1px solid var(--border); }

/* ── Responsive: mobile portrait ─────────────────── */
@media (max-width: 600px) {
  .shell { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
  main  { display: none !important; }
  aside { border-right: none; flex: 1; min-height: 0; overflow: hidden; }
  .sidebar-resizer { display: none; }

  header { flex-direction: column; align-items: stretch; padding: 7px 12px 5px; gap: 0; flex-shrink: 0; }

  .hdr-r1 { display: flex; align-items: center; gap: 8px; padding-bottom: 5px; border-bottom: 1px solid var(--border); }
  .hdr-r1 .logo { flex: 1; font-size: 1.05rem; }
  .conn-text { display: none; }

  .hdr-r3 { display: none; }

  .hdr-r4 { display: flex; justify-content: center; padding-top: 4px; }
  .hdr-r4 .btn { padding: 7px 5px; }
  .hdr-r4 .btn svg { width: 20px; height: 20px; }
  .hdr-r4 .btn.big svg { width: 26px; height: 26px; }

  #mob-track { display: flex; }
  #mob-cava  { display: block; }
}

/* Desktop: row wrappers flow inline in the header bar */
@media (min-width: 601px) {
  .hdr-r1, .hdr-r3, .hdr-r4 { display: contents; }
}
::-webkit-scrollbar { width: 3px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }
</style>
</head>
<body>
<div class="blob"></div>
<div class="blob2"></div>
<div class="shell">

<!-- ══ HEADER ══════════════════════════════════════════════ -->
<header>
  <div class="hdr-r1">
    <div class="logo">mpd <em>remote</em></div>
    <div class="status-dot" id="statusDot"></div>
    <div class="conn-text" id="connText">Connessione…</div>
    <div class="output-group">
      <button class="out-btn" data-output="usb" title="Riproduci sulla scheda audio USB">USB</button>
      <button class="out-btn" data-output="hdmi" title="Riproduci sull'uscita HDMI">HDMI</button>
    </div>
    <div class="vol-wrap">
      <svg viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z"/></svg>
      <input type="range" id="volSlider" min="0" max="100" value="0">
      <span id="volLabel">0%</span>
    </div>
  </div>
  <div class="hdr-r3">
    <div class="progress-wrap">
      <div class="progress-bar" id="progressBar">
        <div class="progress-fill" id="progressFill"></div>
      </div>
      <div class="progress-times">
        <span id="timeElapsed">0:00</span>
        <span id="queueMeta" class="queue-meta">—</span>
        <span id="timeDuration">0:00</span>
      </div>
    </div>
  </div>
  <div class="hdr-r4">
    <div class="controls">
      <button class="btn" id="btnRandom" data-command="random" title="Casuale">
        <svg viewBox="0 0 24 24"><path d="M18 4l3 3-3 3v-2h-2.2l-2.4 3 2.4 3H18v-2l3 3-3 3v-2h-2.2l-3.3-4.1L9.2 13H3v-2h5.8l3.3-4.1L15.8 6H18V4z"/></svg>
      </button>
      <button class="btn" data-command="prev" title="Precedente">
        <svg viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6 8.5 6V6z"/></svg>
      </button>
      <button class="btn big" id="btnPlay" data-command="toggle" title="Play/Pausa">
        <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
      </button>
      <button class="btn" data-command="next" title="Successivo">
        <svg viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
      </button>
      <button class="btn" id="btnRepeat" data-command="repeat" title="Ripeti">
        <svg viewBox="0 0 24 24"><path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"/></svg>
      </button>
      <button class="btn mode-txt" id="btnSingle" data-command="single" title="Riproduzione singola">1×</button>
      <button class="btn mode-txt" id="btnConsume" data-command="consume" title="Consuma dopo la riproduzione">C</button>
      <button class="btn teal" id="btnRescan" title="Aggiorna database MPD">
        <svg viewBox="0 0 24 24"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
      </button>
    </div>
  </div>
</header>

<!-- Mobile-only: track info ────────────────────────────────── -->
<section id="mob-track">
  <div class="m-title"  id="mobTitle">—</div>
  <div class="m-artist" id="mobArtist"></div>
  <div class="m-album"  id="mobAlbum"></div>
  <div class="mob-prog-wrap">
    <div class="mob-prog-bar" id="progressBarMob">
      <div class="mob-prog-fill" id="progressFillMob"></div>
    </div>
    <div class="mob-prog-times">
      <span id="timeElapsedMob">0:00</span>
      <span id="queueMetaMob" class="queue-meta">—</span>
      <span id="timeDurationMob">0:00</span>
    </div>
  </div>
</section>

<!-- Mobile-only: visualizzatore ────────────────────────────── -->
<section id="mob-cava">
  <canvas class="cava-cv" id="cavaMob" height="70"></canvas>
  <div class="cava-status">
    <span class="cava-led" id="ledMob"></span>
    <span id="infoMob">—</span>
  </div>
</section>

<!-- ══ SIDEBAR ═════════════════════════════════════════════ -->
<aside>
  <div class="sidebar-tabs">
    <button class="tab-btn active" data-tab="queue">Coda</button>
    <button class="tab-btn" data-tab="search">Cerca</button>
    <button class="tab-btn" data-tab="library">Libreria</button>
  </div>

  <!-- CODA -->
  <div class="tab-panel active" id="tab-queue">
    <div class="queue-actions">
      <button class="act-btn" id="clearQueueBtn">✕ Svuota coda</button>
    </div>
    <div id="queueContainer"></div>
  </div>

  <!-- CERCA -->
  <div class="tab-panel" id="tab-search">
    <div class="search-box">
      <input type="text" id="searchInput" placeholder="Titolo, artista, album, file…" autocomplete="off">
      <button class="search-go" id="searchGoBtn">Cerca</button>
    </div>
    <div id="searchResults"></div>
  </div>

  <!-- LIBRERIA -->
  <div class="tab-panel" id="tab-library">
    <div class="lib-subtabs">
      <button class="lib-subtab active" data-libsub="playlists">Playlist salvate</button>
      <button class="lib-subtab" data-libsub="browse">Sfoglia cartelle</button>
    </div>
    <div class="libsub active" id="libsub-playlists">
      <div class="save-box">
        <input type="text" id="savePlaylistName" placeholder="Nome playlist…" autocomplete="off">
        <button id="saveQueueBtn">Salva coda</button>
      </div>
      <div id="libraryContainer"></div>
    </div>
    <div class="libsub" id="libsub-browse">
      <div class="breadcrumb-row">
        <button class="crumb-btn" id="libUpBtn" title="Cartella superiore">←</button>
        <span class="crumb-path" id="libPath">Tutta la libreria</span>
      </div>
      <div id="browseContainer"></div>
    </div>
  </div>
</aside>

<div class="sidebar-resizer" id="sidebarResizer" role="separator" aria-label="Ridimensiona pannello laterale" aria-orientation="vertical" tabindex="0"></div>

<!-- ══ MAIN ═════════════════════════════════════════════════ -->
<main>
  <div class="card">
    <div class="card-title">Brano corrente</div>
    <div class="now-row">
      <div class="cover-thumb-wrap">
        <img id="coverImg" class="cover-thumb hidden" alt="Copertina album">
        <div id="coverEmpty" class="cover-thumb-empty">♪</div>
      </div>
      <div class="now-info">
        <div class="now-title" id="siTitle">—</div>
        <div class="now-artist" id="siArtist">—</div>
        <div class="now-album" id="siAlbum">—</div>
        <div class="now-file" id="siFile">—</div>
      </div>
    </div>
    <div class="stats-grid">
      <div class="stat"><div class="stat-lbl">Stato</div><div class="stat-val ac" id="sState">—</div></div>
      <div class="stat"><div class="stat-lbl">Volume</div><div class="stat-val" id="sVol">—</div></div>
      <div class="stat"><div class="stat-lbl">Traccia</div><div class="stat-val" id="sTrack">—</div></div>
    </div>
  </div>
  <div class="card cava-card">
    <div class="card-title">Visualizzatore</div>
    <canvas class="cava-cv" id="cavaDesk" height="240"></canvas>
    <div class="cava-status">
      <span class="cava-led" id="ledDesk"></span>
      <span id="infoDesk">In attesa dello spettro…</span>
    </div>
  </div>
</main>
</div><!-- /.shell -->

<!-- ══ MODAL ════════════════════════════════════════════════ -->
<div id="addModal" class="modal-overlay" style="display:none">
  <div class="modal">
    <div class="modal-title">Aggiungi a…</div>
    <div id="modalList"></div>
    <button class="modal-cancel" id="modalCancelBtn">Annulla</button>
  </div>
</div>

<div id="toast"></div>

<script>
// ── helpers ───────────────────────────────────────────────
function clampNum(v, min, max) { return Math.min(max, Math.max(min, v)); }
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmt(s) {
  s = Math.max(0, Math.floor(s || 0));
  const h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60), x = s % 60;
  return h ? `${h}:${String(m).padStart(2,'0')}:${String(x).padStart(2,'0')}` : `${m}:${String(x).padStart(2,'0')}`;
}
function translateQueueLabel(label) {
  if (!label) return '—';
  const m = /^Track (\d+) of (\d+)$/.exec(label);
  if (m) return `Traccia ${m[1]} di ${m[2]}`;
  if (label === 'Queue unavailable') return 'Coda non disponibile';
  return label;
}
async function api(action, params = {}, method = 'GET') {
  const qs = new URLSearchParams({ api: action, ...params });
  try {
    const r = await fetch('?' + qs, method === 'POST' ? { method: 'POST' } : {});
    return await r.json();
  } catch (e) {
    return { ok: false, error: 'Errore di rete' };
  }
}

let toastT;
function toast(msg) {
  const el = document.getElementById('toast');
  el.textContent = msg; el.classList.add('show');
  clearTimeout(toastT);
  toastT = setTimeout(() => el.classList.remove('show'), 2400);
}

const state = {
  status: null, playlists: [], queue: [],
  seekDrag: false, volumeDrag: false, coverKey: null,
  lastQueueLabel: null, libraryPath: '', libraryEntries: []
};

// ── Transport ────────────────────────────────────────────
document.querySelectorAll('[data-command]').forEach(b => b.onclick = async () => {
  await api('command', { command: b.dataset.command }, 'POST');
  refreshStatus();
});

document.querySelectorAll('[data-output]').forEach(b => b.onclick = async () => {
  const d = await api('output', { target: b.dataset.output }, 'POST');
  if (d.ok) {
    document.querySelectorAll('[data-output]').forEach(x => x.classList.toggle('on', x === b));
    toast(d.message || 'Uscita cambiata');
  } else {
    toast('Errore: ' + (d.error || 'cambio uscita non riuscito'));
  }
});

document.getElementById('btnRescan').onclick = async () => {
  toast('Aggiornamento database MPD…');
  const d = await api('database-rescan', {}, 'POST');
  toast(d.ok ? (d.message || 'Database aggiornato') : ('Errore: ' + (d.error || 'aggiornamento non riuscito')));
};

let volT;
document.getElementById('volSlider').oninput = function() {
  state.volumeDrag = true;
  document.getElementById('volLabel').textContent = this.value + '%';
  clearTimeout(volT);
  const v = this.value;
  volT = setTimeout(async () => { await api('volume', { volume: v }, 'POST'); state.volumeDrag = false; }, 200);
};

function seekHandler() {
  return async e => {
    const rect = e.currentTarget.getBoundingClientRect();
    const pct = (e.clientX - rect.left) / rect.width;
    const dur = state.status?.duration || 0;
    if (dur > 0) { await api('seek', { position: Math.floor(pct * dur) }, 'POST'); refreshStatus(); }
  };
}
document.getElementById('progressBar').addEventListener('click', seekHandler());
document.getElementById('progressBarMob').addEventListener('click', seekHandler());

// ── Status polling ───────────────────────────────────────
const stateLabels = { playing: 'In riproduzione', paused: 'In pausa', stopped: 'Fermo' };

async function refreshStatus() {
  const d = await api('status');
  const dot = document.getElementById('statusDot');
  const connText = document.getElementById('connText');

  if (!d.ok) {
    dot.className = 'status-dot error';
    connText.textContent = 'MPD non raggiungibile';
    return;
  }
  connText.textContent = 'Connesso a MPD';

  const s = state.status = d.status;
  const title = s.title || (s.file ? s.file.split('/').pop() : 'Nessuna traccia');

  document.getElementById('siTitle').textContent = title;
  document.getElementById('siArtist').textContent = s.artist || '—';
  document.getElementById('siAlbum').textContent = s.album || '—';
  document.getElementById('siFile').textContent = s.file || '—';

  document.getElementById('mobTitle').textContent = title;
  document.getElementById('mobArtist').textContent = s.artist || '';
  document.getElementById('mobAlbum').textContent = s.album || '';

  const pct = s.duration > 0 ? (s.elapsed / s.duration * 100) : 0;
  document.getElementById('progressFill').style.width = pct + '%';
  document.getElementById('progressFillMob').style.width = pct + '%';
  document.getElementById('timeElapsed').textContent = fmt(s.elapsed);
  document.getElementById('timeElapsedMob').textContent = fmt(s.elapsed);
  document.getElementById('timeDuration').textContent = fmt(s.duration);
  document.getElementById('timeDurationMob').textContent = fmt(s.duration);
  document.getElementById('queueMeta').textContent = translateQueueLabel(s.queueLabel);
  document.getElementById('queueMetaMob').textContent = translateQueueLabel(s.queueLabel);

  dot.className = 'status-dot ' + (s.state === 'playing' ? 'play' : (s.state === 'paused' ? 'pause' : 'stop'));

  document.getElementById('btnPlay').innerHTML = s.state === 'playing'
    ? '<svg viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>'
    : '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>';

  document.getElementById('btnRandom').classList.toggle('active', !!s.random);
  document.getElementById('btnRepeat').classList.toggle('active', !!s.repeat);
  document.getElementById('btnSingle').classList.toggle('active', !!s.single);
  document.getElementById('btnConsume').classList.toggle('active', !!s.consume);

  if (!state.volumeDrag) {
    document.getElementById('volSlider').value = s.volume;
    document.getElementById('volLabel').textContent = s.volume + '%';
  }

  document.getElementById('sState').textContent = stateLabels[s.state] || s.stateLabel || '—';
  document.getElementById('sVol').textContent = s.volume + '%';
  document.getElementById('sTrack').textContent = translateQueueLabel(s.queueLabel);

  const key = `${s.file}|${s.album}|${s.artist}`;
  if (key !== state.coverKey) {
    state.coverKey = key;
    const cover = document.getElementById('coverImg'), empty = document.getElementById('coverEmpty');
    cover.classList.add('hidden'); empty.classList.remove('hidden');
    if (s.file) {
      cover.onload = () => { cover.classList.remove('hidden'); empty.classList.add('hidden'); };
      cover.onerror = () => { cover.classList.add('hidden'); empty.classList.remove('hidden'); };
      cover.src = `?api=cover&t=${encodeURIComponent(key)}`;
    } else {
      cover.removeAttribute('src');
    }
  }

  if (s.queueLabel !== state.lastQueueLabel) {
    state.lastQueueLabel = s.queueLabel;
    refreshQueue();
  }
}

// ── Queue ────────────────────────────────────────────────
document.getElementById('clearQueueBtn').onclick = async () => {
  await api('playlist-action', { action: 'clear-queue' }, 'POST');
  refreshQueue(); refreshStatus();
};

async function refreshQueue() {
  const d = await api('playlists');
  if (!d.ok) return;
  state.playlists = d.playlists || [];
  state.queue = d.queue || [];
  renderQueue();
  if (document.getElementById('libsub-playlists').classList.contains('active')) renderSavedPlaylists();
}

function currentQueuePosition() {
  // mpc's queue "position" (and the #N/M shown here) are 1-indexed.
  const m = /^Track (\d+) of \d+$/.exec(state.status?.queueLabel || '');
  return m ? parseInt(m[1], 10) : null;
}

function renderQueue() {
  const c = document.getElementById('queueContainer');
  const pl = state.queue;
  if (!pl.length) { c.innerHTML = '<div class="empty">La coda è vuota</div>'; return; }

  const curPos = currentQueuePosition();
  c.innerHTML = pl.map((s, i) => {
    const isCur = curPos !== null ? s.position === curPos : (!!state.status?.file && state.status.file === s.file);
    return `<div class="pl-item${isCur ? ' current' : ''}" data-pos="${s.position}">
      <span class="pi-num">${s.position}</span>
      <div class="pi-info">
        <div class="pi-title">${esc(s.title || (s.file ? s.file.split('/').pop() : '?'))}</div>
        ${s.artist ? `<div class="pi-sub">${esc(s.artist)}</div>` : ''}
      </div>
      <div class="pi-actions">
        ${i > 0           ? `<button class="pi-btn" data-up="${s.position}" title="Sposta su">↑</button>` : ''}
        ${i < pl.length-1  ? `<button class="pi-btn" data-down="${s.position}" title="Sposta giù">↓</button>` : ''}
        <button class="pi-btn del" data-remove="${s.position}" title="Rimuovi">✕</button>
      </div>
    </div>`;
  }).join('');

  c.querySelectorAll('.pl-item').forEach(row => row.addEventListener('click', async e => {
    if (e.target.closest('.pi-actions')) return;
    await api('queue-play', { position: row.dataset.pos }, 'POST');
    refreshStatus();
  }));
  c.querySelectorAll('[data-up]').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    await api('playlist-action', { action: 'queue-move-up', position: b.dataset.up }, 'POST');
    refreshQueue();
  });
  c.querySelectorAll('[data-down]').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    await api('playlist-action', { action: 'queue-move-down', position: b.dataset.down }, 'POST');
    refreshQueue();
  });
  c.querySelectorAll('[data-remove]').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    await api('playlist-action', { action: 'queue-remove', position: b.dataset.remove }, 'POST');
    refreshQueue(); refreshStatus();
  });
}

// ── Search ───────────────────────────────────────────────
document.getElementById('searchGoBtn').onclick = doSearch;
document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') doSearch(); });

async function doSearch() {
  const q = document.getElementById('searchInput').value.trim();
  const c = document.getElementById('searchResults');
  if (q.length < 2) { c.innerHTML = '<div class="empty">Inserisci almeno 2 caratteri</div>'; return; }
  c.innerHTML = '<div class="empty">Ricerca in corso…</div>';

  const d = await api('library-search', { q });
  if (!d.ok) { c.innerHTML = `<div class="empty">${esc(d.error || 'Ricerca non riuscita')}</div>`; return; }

  const res = d.tracks || [];
  if (!res.length) { c.innerHTML = '<div class="empty">Nessun risultato per «' + esc(q) + '»</div>'; return; }

  c.innerHTML = res.map(s => `
    <div class="sr-item">
      <div class="sr-info">
        <div class="sr-title">${esc(s.title || s.file.split('/').pop())}</div>
        ${s.artist ? `<div class="sr-sub">${esc(s.artist)}</div>` : ''}
      </div>
      <button class="sr-add" data-file="${esc(s.file)}" title="Aggiungi">+</button>
    </div>`).join('');

  c.querySelectorAll('.sr-add').forEach(btn => btn.addEventListener('click', () => openAddModal(btn.dataset.file, false)));
}

// ── Add-to modal ─────────────────────────────────────────
function closeModal() { document.getElementById('addModal').style.display = 'none'; }
document.getElementById('modalCancelBtn').onclick = closeModal;
document.getElementById('addModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });

async function openAddModal(uri, isDir) {
  const listEl = document.getElementById('modalList');
  listEl.innerHTML = '<div class="empty">Caricamento…</div>';
  document.getElementById('addModal').style.display = 'flex';

  const d = await api('playlists');
  const playlists = d.playlists || [];
  state.playlists = playlists;

  listEl.innerHTML = '';
  const qOpt = document.createElement('div');
  qOpt.className = 'modal-opt queue-opt';
  qOpt.textContent = isDir ? '▶  Aggiungi cartella alla coda' : '▶  Aggiungi alla coda';
  qOpt.onclick = async () => {
    closeModal();
    const r = await api('playlist-action', { action: isDir ? 'queue-add-directory' : 'queue-add-track', uri }, 'POST');
    if (r.ok) { toast(r.message || 'Aggiunto alla coda'); refreshQueue(); refreshStatus(); }
    else toast('Errore: ' + (r.error || 'impossibile aggiungere'));
  };
  listEl.appendChild(qOpt);

  const sep = document.createElement('hr');
  sep.className = 'modal-sep';
  listEl.appendChild(sep);

  if (playlists.length) {
    playlists.forEach(p => {
      const item = document.createElement('div');
      item.className = 'modal-opt';
      item.textContent = '♪  ' + p.name;
      item.onclick = async () => {
        closeModal();
        const r = await api('playlist-action', { action: isDir ? 'playlist-add-directory' : 'playlist-add-track', name: p.name, uri }, 'POST');
        if (r.ok) toast(r.message || ('Aggiunto a «' + p.name + '»'));
        else toast('Errore: ' + (r.error || 'impossibile aggiungere'));
      };
      listEl.appendChild(item);
    });
  } else {
    const em = document.createElement('div');
    em.style.cssText = 'color:var(--muted);padding:8px 12px;font-size:11px';
    em.textContent = 'Nessuna playlist salvata';
    listEl.appendChild(em);
  }
}

// ── Library: saved playlists ────────────────────────────
document.getElementById('savePlaylistName').addEventListener('keydown', e => { if (e.key === 'Enter') saveCurrentQueue(); });
document.getElementById('saveQueueBtn').onclick = saveCurrentQueue;

async function saveCurrentQueue() {
  const input = document.getElementById('savePlaylistName');
  const name = input.value.trim();
  if (!name) { toast('Inserisci un nome per la playlist'); return; }
  const d = await api('playlist-action', { action: 'save', name }, 'POST');
  if (d.ok) {
    toast(d.message || ('Playlist «' + name + '» salvata'));
    input.value = '';
    await refreshQueue();
  } else {
    toast('Errore: ' + (d.error || 'playlist già esistente?'));
  }
}

function renderSavedPlaylists() {
  const c = document.getElementById('libraryContainer');
  const pl = state.playlists;
  const openNames = new Set([...c.querySelectorAll('.saved-pl.open')].map(el => el.dataset.name));

  if (!pl.length) { c.innerHTML = '<div class="empty">Nessuna playlist salvata</div>'; return; }

  c.innerHTML = pl.map((p, idx) => `
    <div class="saved-pl" data-name="${esc(p.name)}" data-idx="${idx}">
      <div class="saved-pl-hdr">
        <span class="spl-name spl-toggle">${esc(p.name)}</span>
        <button class="spl-btn load" data-load title="Sostituisci la coda e riproduci">▶</button>
        <button class="spl-btn append" data-append title="Aggiungi alla coda">+</button>
        <button class="spl-btn update" data-update title="Sostituisci con la coda corrente">⟳</button>
        <button class="spl-btn del" data-del title="Elimina">✕</button>
        <span class="spl-arrow spl-toggle">›</span>
      </div>
      <div class="saved-pl-tracks" id="spt-${idx}"></div>
    </div>`).join('');

  c.querySelectorAll('.saved-pl').forEach(el => {
    if (openNames.has(el.dataset.name)) togglePlaylist(el, el.dataset.name, el.dataset.idx, true);
  });
}

document.getElementById('libraryContainer').addEventListener('click', async e => {
  const plEl = e.target.closest('[data-name]');
  if (!plEl) return;
  const name = plEl.dataset.name, idx = plEl.dataset.idx;

  if (e.target.closest('[data-load]')) {
    const d = await api('playlist-action', { action: 'load-replace', name }, 'POST');
    toast(d.ok ? ('Playlist «' + name + '» caricata') : ('Errore: ' + (d.error || '')));
    await refreshQueue(); refreshStatus();
    return;
  }
  if (e.target.closest('[data-append]')) {
    const d = await api('playlist-action', { action: 'load-append', name }, 'POST');
    toast(d.ok ? ('Playlist «' + name + '» aggiunta alla coda') : ('Errore: ' + (d.error || '')));
    await refreshQueue();
    return;
  }
  if (e.target.closest('[data-update]')) {
    if (!confirm('Sostituire «' + name + '» con la coda corrente?')) return;
    const d = await api('playlist-action', { action: 'update', name }, 'POST');
    toast(d.ok ? ('Playlist «' + name + '» aggiornata') : ('Errore: ' + (d.error || '')));
    await refreshQueue();
    return;
  }
  if (e.target.closest('[data-del]')) {
    if (!confirm('Eliminare la playlist «' + name + '»?')) return;
    await api('playlist-action', { action: 'delete-playlist', name }, 'POST');
    toast('Playlist «' + name + '» eliminata');
    await refreshQueue();
    return;
  }
  if (e.target.closest('.spl-toggle') || e.target.closest('.saved-pl-hdr')) {
    await togglePlaylist(plEl, name, idx);
    return;
  }
});

document.getElementById('libraryContainer').addEventListener('click', async e => {
  const btn = e.target.closest('.spt-del');
  if (!btn) return;
  const plEl = btn.closest('[data-name]');
  if (!plEl) return;
  const name = plEl.dataset.name, idx = plEl.dataset.idx, pos = btn.dataset.pos;
  await api('playlist-action', { action: 'playlist-remove-track', name, position: pos }, 'POST');
  toast('Brano rimosso da «' + name + '»');
  await refreshQueue();
  const refreshedEl = document.querySelector(`.saved-pl[data-idx="${idx}"]`);
  if (refreshedEl) togglePlaylist(refreshedEl, name, idx, true);
}, true);

async function togglePlaylist(plEl, name, idx, forceOpen) {
  const opening = forceOpen || !plEl.classList.contains('open');
  plEl.classList.toggle('open', opening);
  if (!opening) return;

  const tracksEl = document.getElementById('spt-' + idx);
  if (!tracksEl) return;
  tracksEl.innerHTML = '<div class="empty">Caricamento…</div>';

  const d = await api('playlist-contents', { name });
  const tracks = d.tracks || [];
  if (!tracks.length) { tracksEl.innerHTML = '<div class="empty">Playlist vuota</div>'; return; }

  tracksEl.innerHTML = tracks.map(t => `
    <div class="spt-item">
      <div class="spt-info">
        <div class="spt-title">${esc(t.title || t.file.split('/').pop())}</div>
        ${t.artist ? `<div class="spt-sub">${esc(t.artist)}</div>` : ''}
      </div>
      <button class="spt-play" data-file="${esc(t.file)}" title="Riproduci subito">▶</button>
      <button class="spt-del" data-pos="${t.position}" title="Rimuovi">✕</button>
    </div>`).join('');
}

document.getElementById('libraryContainer').addEventListener('click', async e => {
  const btn = e.target.closest('.spt-play');
  if (!btn) return;
  const d = await api('playlist-action', { action: 'queue-play-track', uri: btn.dataset.file }, 'POST');
  if (d.ok) { toast(d.message || 'Riproduzione avviata'); refreshQueue(); refreshStatus(); }
  else toast('Errore: ' + (d.error || 'impossibile riprodurre'));
});

// ── Library: browse folders ─────────────────────────────
document.getElementById('libUpBtn').onclick = () => {
  if (!state.libraryPath) return;
  const parts = state.libraryPath.split('/'); parts.pop();
  browseLibrary(parts.join('/'));
};

async function browseLibrary(path) {
  const c = document.getElementById('browseContainer');
  c.innerHTML = '<div class="empty">Caricamento…</div>';
  const d = await api('library-browse', { path });
  if (!d.ok) { c.innerHTML = `<div class="empty">${esc(d.error || 'Impossibile sfogliare la libreria')}</div>`; return; }
  state.libraryPath = d.path || '';
  state.libraryEntries = d.entries || [];
  document.getElementById('libPath').textContent = state.libraryPath || 'Tutta la libreria';
  document.getElementById('libUpBtn').disabled = !state.libraryPath;
  renderBrowse();
}

function renderBrowse() {
  const c = document.getElementById('browseContainer');
  const entries = state.libraryEntries;
  if (!entries.length) { c.innerHTML = '<div class="empty">Nessun elemento trovato</div>'; return; }

  c.innerHTML = entries.map(item => {
    const folder = item.type === 'directory';
    return `<div class="lib-item">
      <span class="lib-icon">${folder ? '▸' : '♪'}</span>
      <div class="lib-info">
        <div class="lib-title">${esc(item.name)}</div>
        ${!folder && item.artist ? `<div class="lib-sub">${esc(item.artist)}</div>` : ''}
      </div>
      <div class="lib-actions">
        ${folder
          ? `<button class="lib-open" data-open="${esc(item.path)}">Apri</button><button class="sr-add" data-add="${esc(item.path)}" data-dir="1" title="Aggiungi cartella">+</button>`
          : `<button class="sr-add" data-add="${esc(item.file)}" title="Aggiungi">+</button>`}
      </div>
    </div>`;
  }).join('');

  c.querySelectorAll('[data-open]').forEach(b => b.onclick = () => browseLibrary(b.dataset.open));
  c.querySelectorAll('[data-add]').forEach(b => b.onclick = () => openAddModal(b.dataset.add, b.dataset.dir === '1'));
}

// ── Sidebar tabs ─────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(b => b.onclick = () => switchTab(b.dataset.tab));
function switchTab(name) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  if (name === 'queue' || name === 'library') refreshQueue();
  if (name === 'library' && document.getElementById('libsub-browse').classList.contains('active') && !state.libraryEntries.length) {
    browseLibrary(state.libraryPath);
  }
}

document.querySelectorAll('.lib-subtab').forEach(b => b.onclick = () => {
  document.querySelectorAll('.lib-subtab').forEach(x => x.classList.toggle('active', x === b));
  document.querySelectorAll('.libsub').forEach(x => x.classList.remove('active'));
  document.getElementById('libsub-' + b.dataset.libsub).classList.add('active');
  if (b.dataset.libsub === 'browse' && !state.libraryEntries.length) browseLibrary(state.libraryPath);
  if (b.dataset.libsub === 'playlists') renderSavedPlaylists();
});

// ── Sidebar resize ───────────────────────────────────────
(function() {
  const root = document.documentElement;
  const handle = document.getElementById('sidebarResizer');

  const saved = Number(localStorage.getItem('mpd-sidebar-width'));
  if (saved) root.style.setProperty('--sidebar-width', clampNum(saved, 220, 520) + 'px');

  handle.addEventListener('pointerdown', e => {
    if (matchMedia('(max-width:600px)').matches) return;
    e.preventDefault();
    handle.setPointerCapture(e.pointerId);
    handle.classList.add('dragging');
    document.body.classList.add('resizing-sidebar');

    const move = ev => {
      const px = clampNum(ev.clientX, 220, 520);
      root.style.setProperty('--sidebar-width', px + 'px');
      localStorage.setItem('mpd-sidebar-width', String(px));
    };
    const stop = () => {
      handle.classList.remove('dragging');
      document.body.classList.remove('resizing-sidebar');
      handle.removeEventListener('pointermove', move);
      handle.removeEventListener('pointerup', stop);
      handle.removeEventListener('pointercancel', stop);
    };
    handle.addEventListener('pointermove', move);
    handle.addEventListener('pointerup', stop);
    handle.addEventListener('pointercancel', stop);
  });

  handle.addEventListener('keydown', e => {
    const delta = e.key === 'ArrowRight' ? 12 : e.key === 'ArrowLeft' ? -12 : 0;
    if (!delta) return;
    e.preventDefault();
    const current = parseFloat(getComputedStyle(root).getPropertyValue('--sidebar-width')) || 310;
    const changed = clampNum(current + delta, 220, 520);
    root.style.setProperty('--sidebar-width', changed + 'px');
    localStorage.setItem('mpd-sidebar-width', String(changed));
  });
})();

// ── Init ─────────────────────────────────────────────────
refreshStatus();
refreshQueue();
setInterval(refreshStatus, 2000);

// ── Visualizzatore (spettro) ─────────────────────────────
(function() {
  const cvs  = ['cavaDesk', 'cavaMob'].map(id => document.getElementById(id)).filter(Boolean);
  const leds  = ['ledDesk', 'ledMob'].map(id => document.getElementById(id)).filter(Boolean);
  const infos = ['infoDesk', 'infoMob'].map(id => document.getElementById(id)).filter(Boolean);

  const BARS = 30;
  let raw      = new Float32Array(BARS);
  let smoothed = new Float32Array(BARS);
  let peaks    = new Float32Array(BARS);
  let peakAge  = new Uint16Array(BARS);

  function resize(cv) { cv.width = cv.offsetWidth || cv.clientWidth || 300; cv.height = cv.offsetHeight || cv.clientHeight || 80; }
  cvs.forEach(resize);
  window.addEventListener('resize', () => cvs.forEach(resize));

  function drawOn(cv) {
    const ctx = cv.getContext('2d');
    const W = cv.width, H = cv.height;
    ctx.clearRect(0, 0, W, H);

    const gap = 2;
    const bw = Math.max(1, (W - gap * (BARS - 1)) / BARS);

    for (let i = 0; i < BARS; i++) {
      smoothed[i] += (raw[i] - smoothed[i]) * 0.22;
      if (smoothed[i] >= peaks[i]) { peaks[i] = smoothed[i]; peakAge[i] = 0; }
      else { peakAge[i]++; if (peakAge[i] > 18) peaks[i] = Math.max(0, peaks[i] - 0.012); }

      const v = smoothed[i];
      const bh = Math.max(1, v * H);
      const x = i * (bw + gap);

      const alpha = 0.1 + v * 0.75;
      ctx.fillStyle = `rgba(255,255,255,${alpha.toFixed(2)})`;
      ctx.fillRect(x, H - bh, bw, bh);

      if (peaks[i] > 0.04) {
        const py = H - peaks[i] * H - 1;
        ctx.fillStyle = `rgba(255,255,255,${Math.min(1, 0.4 + peaks[i] * 0.6).toFixed(2)})`;
        ctx.fillRect(x, py, bw, 1);
      }
    }
  }
  function draw() { requestAnimationFrame(draw); cvs.forEach(drawOn); }

  function setLed(live) { leds.forEach(l => l.className = 'cava-led' + (live ? ' live' : '')); }
  function setInfo(txt) { infos.forEach(el => el.textContent = txt); }

  async function refreshSpectrum() {
    try {
      const r = await fetch('?api=spectrum', { cache: 'no-store' });
      const d = await r.json();
      const incoming = Array.isArray(d.bars) ? d.bars.map(v => Number(v) || 0) : [];
      const playing = state.status?.state === 'playing';

      if (incoming.length && Math.max(...incoming) > 0) {
        for (let i = 0; i < BARS; i++) raw[i] = clampNum((incoming[i] || 0) / 100, 0, 1);
        setLed(true); setInfo('Attivo');
      } else if (playing) {
        setLed(true); setInfo('Attivo');
      } else {
        for (let i = 0; i < BARS; i++) raw[i] = 0;
        setLed(false); setInfo('Fermo');
      }
    } catch (e) {
      for (let i = 0; i < BARS; i++) raw[i] = 0;
      setLed(false); setInfo('Non disponibile');
    }
  }

  draw();
  refreshSpectrum();
  setInterval(() => { if (!document.hidden) refreshSpectrum(); }, 120);
})();
</script>
</body>
</html>
<?php
}

function handleApi(string $api, array $config): void
{
    try {
        switch ($api) {
            case 'status':
                jsonResponse(getStatus($config));
                return;
            case 'command':
                $command = (string) ($_POST['command'] ?? $_GET['command'] ?? '');
                jsonResponse(runPlayerCommand($command, $config));
                return;
            case 'output':
                $target = (string) ($_POST['target'] ?? $_GET['target'] ?? '');
                jsonResponse(switchAudioOutput($target, $config));
                return;
            case 'seek':
                $position = (int) ($_POST['position'] ?? $_GET['position'] ?? 0);
                jsonResponse(seekTo($position, $config));
                return;
            case 'volume':
                $volume = (int) ($_POST['volume'] ?? $_GET['volume'] ?? -1);
                jsonResponse(setVolume($volume, $config));
                return;
            case 'spectrum':
                proxySpectrum($config);
                return;
            case 'cover':
                serveCover($config);
                return;
            case 'playlists':
                jsonResponse(getPlaylistsData($config));
                return;
            case 'playlist-contents':
                $name = trim((string) ($_GET['name'] ?? ''));
                jsonResponse(getPlaylistContents($name, $config));
                return;
            case 'library-search':
                $query = trim((string) ($_GET['q'] ?? ''));
                jsonResponse(searchLibrary($query, $config));
                return;
            case 'library-browse':
                $path = trim((string) ($_GET['path'] ?? ''), "/");
                jsonResponse(browseLibrary($path, $config));
                return;
            case 'playlist-action':
                $action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');
                jsonResponse(runPlaylistAction($action, $config));
                return;
            case 'database-rescan':
                jsonResponse(rescanDatabase($config));
                return;
            case 'queue-play':
                $position = (int) ($_POST['position'] ?? $_GET['position'] ?? -1);
                jsonResponse(playQueueTrack($position, $config));
                return;
            default:
                jsonResponse(['ok' => false, 'error' => 'Unknown API endpoint'], 404);
                return;
        }
    } catch (Throwable $exception) {
        if ($api === 'cover') {
            http_response_code(404);
            return;
        }

        jsonResponse([
            'ok' => false,
            'error' => $exception->getMessage(),
        ], 500);
    }
}

function renderPage(): void
{
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>MPD Remote</title>
  <style>
    :root {
      --bg-0: #0f1114;
      --bg-1: #12161b;
      --bg-2: #151a20;
      --panel: rgba(17, 20, 24, 0.84);
      --panel-strong: rgba(17, 20, 24, 0.96);
      --text: #e8eaed;
      --muted: #5a6472;
      --line: rgba(255, 255, 255, 0.06);
      --led-green: #4ecdc4;
      --led-green-soft: rgba(78, 205, 196, 0.18);
      --led-amber: #ffd24d;
      --led-red: #e05c5c;
      --accent: #e8c547;
      --accent-2: #4ecdc4;
      --shadow: 0 28px 60px rgba(0, 0, 0, 0.42);
      --radius: 22px;
    }

    * {
      box-sizing: border-box;
    }

    html, body {
      margin: 0;
      min-height: 100%;
      font-family: "DM Mono", monospace;
      color: var(--text);
      background:
        radial-gradient(circle at top right, rgba(232, 197, 71, 0.08), transparent 24%),
        radial-gradient(circle at bottom left, rgba(78, 205, 196, 0.06), transparent 24%),
        linear-gradient(180deg, var(--bg-0) 0%, var(--bg-1) 50%, #0d1014 100%);
    }

    body {
      padding: 10px;
    }

    body::after {
      content: "";
      position: fixed;
      inset: 0;
      pointer-events: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='300' height='300' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
      background-size: 300px 300px;
      z-index: 9999;
    }

    .shell {
      max-width: 1680px;
      margin: 0 auto;
      display: grid;
      gap: 12px;
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .brand {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .brand h1 {
      margin: 0;
      font-size: clamp(1.2rem, 1.4vw, 1.8rem);
      letter-spacing: -0.02em;
      color: #ffffff;
      font-family: "DM Serif Display", serif;
      font-weight: 400;
    }

    .brand p,
    .meta-line,
    .mini {
      margin: 0;
      color: var(--muted);
    }

    .brand em {
      color: var(--accent);
      font-style: italic;
    }

    .topbar-right {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .status-pill,
    .nav-pill {
      padding: 7px 11px;
      border-radius: 999px;
      background: rgba(17, 20, 24, 0.92);
      border: 1px solid rgba(255, 255, 255, 0.06);
      box-shadow: 0 12px 26px rgba(0, 0, 0, 0.24);
      font-size: 0.74rem;
      white-space: nowrap;
    }

    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 10px;
    }

    .nav-pills {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .nav-pill {
      color: var(--muted);
      text-decoration: none;
      transition: border-color 120ms ease, color 120ms ease, box-shadow 120ms ease;
    }

    .nav-pill.active,
    .nav-pill:hover,
    .nav-pill:focus-visible {
      color: var(--accent);
      border-color: rgba(232, 197, 71, 0.28);
      box-shadow: 0 0 0 1px rgba(232, 197, 71, 0.08), 0 0 18px rgba(232, 197, 71, 0.1);
      outline: none;
    }

    .led {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--led-red);
      box-shadow: 0 0 8px rgba(224, 92, 92, 0.6);
      flex: 0 0 auto;
      transition: background 160ms ease, box-shadow 160ms ease;
    }

    .led.ok {
      background: var(--led-green);
      box-shadow: 0 0 10px rgba(78, 205, 196, 0.75);
      animation: ledPulse 2s infinite;
    }

    .led.pause {
      background: var(--led-amber);
      box-shadow: 0 0 8px rgba(232, 197, 71, 0.6);
    }

    @keyframes ledPulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.45; }
    }

    .layout {
      display: grid;
      grid-template-columns: minmax(220px, 320px) minmax(0, 1fr);
      gap: 12px;
      align-items: start;
    }

    .panel {
      background: linear-gradient(180deg, rgba(17, 20, 24, 0.96), rgba(17, 20, 24, 0.92));
      border: 1px solid rgba(255, 255, 255, 0.06);
      box-shadow: var(--shadow);
      backdrop-filter: blur(14px);
      border-radius: var(--radius);
      overflow: hidden;
    }

    .cover-panel {
      position: sticky;
      top: 10px;
    }

    .cover-wrap {
      position: relative;
      aspect-ratio: 1 / 0.92;
      background:
        radial-gradient(circle at center, rgba(232, 197, 71, 0.08), transparent 54%),
        linear-gradient(135deg, rgba(17, 20, 24, 0.95), rgba(23, 28, 34, 0.98));
      overflow: hidden;
    }

    .cover-wrap::after {
      content: "";
      position: absolute;
      inset: auto 8% 6% 8%;
      height: 26%;
      border-radius: 999px;
      background: rgba(0, 0, 0, 0.45);
      filter: blur(28px);
      z-index: 0;
    }

    .cover-art {
      width: 100%;
      height: 100%;
      object-fit: cover;
      position: relative;
      z-index: 1;
      display: block;
    }

    .cover-spectrum {
      position: absolute;
      left: 10px;
      right: 10px;
      bottom: 10px;
      z-index: 3;
      padding: 10px 10px 8px;
      border-radius: 14px;
      background: linear-gradient(180deg, rgba(7, 8, 10, 0.18), rgba(7, 8, 10, 0.62));
      border: 1px solid rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(6px);
      transition: opacity 180ms ease;
    }

    .cover-spectrum.is-silent {
      opacity: 0;
    }

    .cover-spectrum-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      margin-bottom: 6px;
      font-size: 0.66rem;
      color: #a0aab8;
      text-transform: uppercase;
      letter-spacing: 0.14em;
    }

    .cover-fallback {
      position: absolute;
      inset: 0;
      display: grid;
      place-items: center;
      text-align: center;
      padding: 24px;
      color: rgba(232, 234, 237, 0.62);
      font-size: 0.95rem;
      z-index: 2;
      background: linear-gradient(180deg, rgba(9, 21, 15, 0.18), rgba(9, 21, 15, 0.52));
      transition: opacity 160ms ease, visibility 160ms ease;
    }

    .is-hidden {
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
    }

    .cover-meta {
      padding: 12px 12px 14px;
      display: grid;
      gap: 6px;
    }

    .eyebrow {
      text-transform: uppercase;
      letter-spacing: 0.16em;
      font-size: 0.72rem;
      color: var(--muted);
    }

    .track-title {
      margin: 0;
      font-size: clamp(1rem, 1.4vw, 1.35rem);
      line-height: 1.05;
      letter-spacing: -0.04em;
      color: #ffffff;
    }

    .track-artist {
      font-size: 0.84rem;
      font-weight: 600;
    }

    .content {
      display: grid;
      gap: 12px;
      align-content: start;
    }

    .section {
      padding: 12px;
      display: grid;
      gap: 10px;
    }

    .section-head {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 10px;
      flex-wrap: wrap;
    }

    .section-title {
      margin: 0;
      font-size: 1rem;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      color: var(--muted);
    }

    .led-inline {
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    #spectrum {
      width: 100%;
      height: 74px;
      display: block;
      border-radius: 12px;
      background: transparent;
      border: 0;
      box-shadow: none;
    }

    .transport {
      display: grid;
      gap: 10px;
    }

    .transport-row,
    .toggles,
    .toolbar-row {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .transport-row {
      justify-content: center;
    }

    .btn,
    .text-input {
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 18px;
      padding: 8px 11px;
      background: rgba(0, 0, 0, 0.22);
      color: var(--text);
      font: inherit;
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.24);
      transition: transform 120ms ease, border-color 120ms ease, box-shadow 120ms ease, background 120ms ease;
    }

    .btn {
      cursor: pointer;
      min-width: 44px;
    }

    .btn:hover,
    .btn:focus-visible,
    .text-input:focus-visible {
      transform: translateY(-1px);
      border-color: rgba(232, 197, 71, 0.32);
      box-shadow: 0 0 0 1px rgba(232, 197, 71, 0.08), 0 0 18px rgba(232, 197, 71, 0.1);
      outline: none;
    }

    .btn.primary {
      background: linear-gradient(135deg, rgba(232, 197, 71, 0.26), rgba(232, 197, 71, 0.08));
      color: #ffffff;
      min-width: 60px;
    }

    .btn.warn {
      border-color: rgba(255, 210, 77, 0.2);
      color: #ffe6a0;
    }

    .btn.danger {
      border-color: rgba(255, 109, 109, 0.2);
      color: #ffc1c1;
    }

    .btn.toggle-on {
      background: rgba(232, 197, 71, 0.12);
      color: var(--accent);
      border-color: rgba(232, 197, 71, 0.34);
      box-shadow: 0 0 14px rgba(232, 197, 71, 0.14);
    }

    .slider-group {
      display: grid;
      gap: 6px;
    }

    .slider-labels {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      font-size: 0.95rem;
    }

    input[type="range"] {
      width: 100%;
      accent-color: var(--accent);
    }

    .grid-two {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }

    .info-strip {
      margin-top: 2px;
    }

    .stat,
    .playlist-card,
    .queue-row {
      padding: 10px;
      border-radius: 16px;
      background: rgba(0, 0, 0, 0.16);
      border: 1px solid var(--line);
      display: grid;
      gap: 6px;
    }

    .stat strong {
      font-size: 0.96rem;
      letter-spacing: -0.04em;
    }

    .footer-note {
      padding: 0 4px;
      font-size: 0.9rem;
      color: var(--muted);
    }

    .view {
      display: none;
      gap: 20px;
    }

    .view.active {
      display: grid;
    }

    .playlist-layout {
      grid-template-columns: minmax(0, 1.1fr) minmax(300px, 0.9fr);
      align-items: start;
    }

    .stack {
      display: grid;
      gap: 16px;
    }

    .playlist-list,
    .queue-list {
      display: grid;
      gap: 10px;
    }

    .playlist-card {
      grid-template-columns: 1fr auto;
      align-items: center;
      gap: 12px;
    }

    .playlist-card h4,
    .queue-main strong {
      margin: 0;
    }

    .playlist-card p,
    .queue-main span {
      margin: 0;
      color: var(--muted);
    }

    .queue-row {
      grid-template-columns: auto 1fr auto;
      gap: 12px;
      align-items: center;
    }

    .queue-index {
      width: 34px;
      height: 34px;
      border-radius: 999px;
      display: grid;
      place-items: center;
      background: rgba(232, 197, 71, 0.08);
      color: var(--accent);
      border: 1px solid rgba(232, 197, 71, 0.16);
      font-size: 0.9rem;
    }

    .queue-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .queue-actions .btn,
    .playlist-actions .btn {
      min-width: auto;
      padding: 10px 12px;
    }

    .playlist-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .empty-state {
      padding: 12px;
      border-radius: 16px;
      border: 1px dashed rgba(255, 255, 255, 0.1);
      color: var(--muted);
      background: rgba(0, 0, 0, 0.12);
    }

    .flash {
      min-height: 1.2em;
      color: #dffff0;
    }

    .flash.error {
      color: #ffbebc;
    }

    .page-hidden {
      display: none !important;
    }

    @media (max-width: 980px) {
      body {
        padding: 8px;
      }

      .layout,
      .playlist-layout,
      .grid-two {
        grid-template-columns: 1fr;
      }

      .cover-panel {
        position: static;
      }

      .section {
        padding: 10px;
      }
    }

    @media (max-width: 640px) {
      .layout {
        gap: 10px;
      }

      .topbar {
        align-items: flex-start;
      }

      .topbar-right {
        width: 100%;
      }

      .nav-pills {
        width: 100%;
      }

      .nav-pill {
        flex: 1 1 0;
        text-align: center;
      }

      .transport-row,
      .toggles,
      .toolbar-row,
      .queue-row,
      .playlist-card {
        justify-content: stretch;
      }

      .transport-row .btn,
      .toggles .btn,
      .toolbar-row .btn,
      .toolbar-row .text-input {
        flex: 1 1 0;
      }

      .btn.primary {
        flex-basis: 100%;
      }

      .playlist-card,
      .queue-row {
        grid-template-columns: 1fr;
      }

      .playlist-actions,
      .queue-actions {
        justify-content: stretch;
      }

      .playlist-actions .btn,
      .queue-actions .btn {
        flex: 1 1 0;
      }

      .cover-wrap {
        aspect-ratio: 1 / 0.76;
      }

      .cover-spectrum {
        left: 8px;
        right: 8px;
        bottom: 8px;
        padding: 8px 8px 6px;
      }

      #spectrum {
        height: 56px;
      }
    }

    /* Cantata-inspired desktop skin: flat Qt surfaces, violet focus and compact rows. */
    :root {
      --bg-0: #202126;
      --bg-1: #24252b;
      --bg-2: #2b2c33;
      --panel: #292a30;
      --panel-strong: #2d2e35;
      --text: #e6e4ea;
      --muted: #9996a2;
      --line: #3a3a43;
      --accent: #9b59d0;
      --accent-2: #c68cff;
      --led-green: #74c69d;
      --shadow: 0 8px 24px rgba(0, 0, 0, .22);
      --radius: 5px;
    }

    html, body {
      font-family: "Segoe UI", "Noto Sans", sans-serif;
      background: #1f2025;
      color: var(--text);
    }

    body { padding: 0; }
    body::after { display: none; }
    .shell { max-width: none; gap: 0; min-height: 100vh; }

    .topbar {
      min-height: 54px;
      padding: 0 18px;
      background: #292a30;
      border-bottom: 1px solid #3a3a43;
      box-shadow: 0 3px 12px rgba(0, 0, 0, .28);
    }

    .brand h1 {
      font-family: inherit;
      font-size: 1rem;
      font-weight: 600;
      letter-spacing: .02em;
    }

    .brand h1::before { content: "♫"; color: var(--accent-2); margin-right: 9px; }
    .brand em { font-family: inherit; font-style: normal; color: #c6c3cb; }

    .nav-pill, .status-pill {
      border-radius: 3px;
      background: transparent;
      box-shadow: none;
      border: 1px solid transparent;
      padding: 8px 12px;
    }

    .nav-pill.active, .nav-pill:hover, .nav-pill:focus-visible {
      color: #fff;
      background: #3a3045;
      border-color: #73508f;
      box-shadow: inset 0 -2px 0 var(--accent-2);
    }

    .status-pill { background: #23242a; border-color: #3a3a43; }
    .layout { grid-template-columns: minmax(0, 1fr) minmax(250px, 330px); gap: 1px; background: #3a3a43; }
    .cover-panel { grid-column: 2; grid-row: 1; position: static; }
    .content { grid-column: 1; grid-row: 1; }

    .panel {
      border: 0;
      border-radius: 0;
      background: #292a30;
      box-shadow: none;
      backdrop-filter: none;
    }

    .section { min-height: calc(100vh - 55px); padding: 20px 24px; }
    .cover-wrap { aspect-ratio: 1; background: #23242a; }
    .cover-meta { border-top: 1px solid #3a3a43; padding: 16px; }
    .track-title { font-family: inherit; font-size: 1.2rem; font-weight: 600; }
    .track-artist { color: var(--accent-2); }

    .section-title { font-family: inherit; font-size: .9rem; text-transform: uppercase; letter-spacing: .08em; }
    .section-head { border-bottom: 1px solid #3a3a43; padding-bottom: 12px; margin-bottom: 20px; }
    .transport { max-width: 900px; margin: 8vh auto 0; }

    .btn {
      border-radius: 3px;
      border: 1px solid #484852;
      background: #34353c;
      color: #dddbe2;
      box-shadow: none;
      min-height: 34px;
    }
    .btn:hover, .btn:focus-visible { background: #41424a; border-color: #686873; transform: none; }
    .btn.primary, .btn.toggle-on {
      color: #fff;
      border-color: #8e55ba;
      background: linear-gradient(#a565d3, #8145ad);
      box-shadow: inset 0 1px rgba(255,255,255,.16);
    }

    input[type="range"] { accent-color: var(--accent); }
    .stat, .playlist-card, .queue-row, .text-input, .empty-state {
      border-radius: 3px;
      border-color: #40414a;
      background: #25262c;
      box-shadow: none;
    }
    .playlist-layout { gap: 1px; background: #3a3a43; }
    .playlist-card, .queue-row { padding: 9px 11px; }
    .playlist-card:hover, .queue-row:hover { background: #30313a; }
    .queue-index { color: var(--accent-2); }
    .cover-spectrum { border-radius: 3px; background: rgba(31,32,37,.84); }

    @media (max-width: 980px) {
      .layout { grid-template-columns: minmax(0, 1fr) minmax(210px, 280px); }
      .section { min-height: auto; }
      .transport { margin-top: 24px; }
    }

    @media (max-width: 700px) {
      .layout { grid-template-columns: 1fr; }
      .cover-panel, .content { grid-column: 1; }
      .cover-panel { grid-row: 1; }
      .content { grid-row: 2; }
      .cover-wrap { aspect-ratio: 16 / 10; }
      .topbar { padding: 8px 10px; }
    }

    /* Compact desktop layout: neutral Cantata-style palette, no violet accents. */
    :root {
      --accent: #aeb8c2;
      --accent-2: #d0d4d8;
      --led-green: #78b99a;
    }

    .nav-pill.active, .nav-pill:hover, .nav-pill:focus-visible {
      color: #fff;
      background: #35383e;
      border-color: #59616a;
      box-shadow: inset 0 -2px 0 #aeb8c2;
    }

    .layout {
      grid-template-columns: clamp(300px, 28vw, 430px) minmax(0, 1fr);
      align-items: stretch;
    }

    .cover-panel {
      grid-column: 1;
      grid-row: 1;
    }

    .content {
      grid-column: 2;
      grid-row: 1;
    }

    .section {
      min-height: 0;
      padding: 18px 22px;
    }

    .section-head {
      margin-bottom: 8px;
    }

    .transport {
      width: 100%;
      max-width: none;
      margin: 0;
      grid-template-columns: minmax(220px, .72fr) minmax(320px, 1.28fr);
      align-items: center;
      column-gap: 28px;
    }

    .transport-row,
    .toggles {
      justify-content: flex-start;
    }

    .transport-row { grid-column: 1; grid-row: 1; }
    .transport .slider-group:nth-of-type(2) { grid-column: 2; grid-row: 1; }
    .transport .slider-group:nth-of-type(3) { grid-column: 2; grid-row: 2; }
    .toggles { grid-column: 1; grid-row: 2; }
    .info-strip { grid-column: 1 / -1; grid-row: 3; }

    .btn.primary, .btn.toggle-on {
      color: #fff;
      border-color: #626a73;
      background: linear-gradient(#555c64, #434950);
    }

    input[type="range"] { accent-color: #aeb8c2; }
    .track-artist, .queue-index { color: #c7ccd1; }
    .queue-index { background: #30343a; border-color: #4b525a; }

    .cover-spectrum {
      padding: 8px 9px 7px;
      background: rgba(21, 25, 30, .88);
      border-color: rgba(210, 214, 218, .14);
    }

    .cover-spectrum.is-silent { opacity: 1; }
    .cover-spectrum-meta { display: none; }
    #spectrum { height: 82px; }

    .playlist-layout {
      grid-template-columns: minmax(280px, .72fr) minmax(420px, 1.28fr);
      min-height: calc(100vh - 55px);
    }

    @media (max-width: 980px) {
      .layout { grid-template-columns: minmax(240px, 34vw) minmax(0, 1fr); }
      .transport { grid-template-columns: 1fr; }
      .transport-row, .transport .slider-group:nth-of-type(2),
      .transport .slider-group:nth-of-type(3), .toggles, .info-strip {
        grid-column: 1;
        grid-row: auto;
      }
    }

    @media (max-width: 700px) {
      .layout, .playlist-layout { grid-template-columns: 1fr; }
      .cover-panel, .content { grid-column: 1; }
      .cover-panel { grid-row: 1; }
      .content { grid-row: 2; }
    }
  </style>
</head>
<body>
  <div class="shell">
    <div class="topbar">
      <div class="brand">
        <h1>MPD <em>Remote</em></h1>
      </div>

      <div class="topbar-right">
        <nav class="nav-pills" aria-label="Sezioni">
          <a href="#player" class="nav-pill active" data-view-link="player">Player</a>
          <a href="#playlists" class="nav-pill" data-view-link="playlists">Playlist</a>
        </nav>
        <div class="status-pill" id="connectionState">
          <span class="led" id="connectionLed"></span>
          <span>Connecting...</span>
        </div>
      </div>
    </div>

    <div id="playerView" class="view active">
      <div class="layout">
        <aside class="panel cover-panel">
          <div class="cover-wrap">
            <img id="coverArt" class="cover-art page-hidden" alt="Album cover">
            <div id="coverFallback" class="cover-fallback">No cover art available</div>
            <div id="spectrumShell" class="cover-spectrum is-silent">
              <div class="cover-spectrum-meta">
                <span>Spectrum</span>
                <span id="spectrumState">Waiting for cava</span>
              </div>
              <canvas id="spectrum" width="900" height="74"></canvas>
            </div>
          </div>
          <div class="cover-meta">
            <div class="eyebrow">Now Playing</div>
            <h2 id="trackTitle" class="track-title">No track</h2>
            <div id="trackArtist" class="track-artist">Waiting for MPD</div>
            <p id="trackAlbum" class="meta-line">Unknown album</p>
          </div>
        </aside>

        <main class="content">
          <section class="panel section">
            <div class="section-head">
              <h3 class="section-title">Control</h3>
              <p class="mini" id="queueMeta">-</p>
            </div>

            <div class="transport">
              <div class="transport-row">
                <button class="btn" data-command="prev" aria-label="Previous track">◀◀</button>
                <button class="btn primary" id="playPause" data-command="toggle" aria-label="Play or pause">▶</button>
                <button class="btn" data-command="next" aria-label="Next track">▶▶</button>
              </div>

              <div class="slider-group">
                <div class="slider-labels">
                  <span id="elapsedTime">0:00</span>
                  <span id="trackLength">0:00</span>
                </div>
                <input id="seekBar" type="range" min="0" max="0" step="1" value="0">
              </div>

              <div class="slider-group">
                <div class="slider-labels">
                  <span>Volume</span>
                  <span id="volumeValue">0%</span>
                </div>
                <input id="volumeBar" type="range" min="0" max="100" step="1" value="0">
              </div>

              <div class="toggles">
                <button class="btn" data-command="repeat" id="repeatBtn">Repeat</button>
                <button class="btn" data-command="random" id="randomBtn">Random</button>
                <button class="btn" data-command="single" id="singleBtn">Single</button>
                <button class="btn" data-command="consume" id="consumeBtn">Consume</button>
              </div>

              <div class="grid-two info-strip">
                <div class="stat">
                  <span class="eyebrow">File</span>
                  <strong id="trackFile">-</strong>
                  <span class="mini">Relative path reported by MPD</span>
                </div>
                <div class="stat">
                  <span class="eyebrow">Status</span>
                  <strong id="trackState">Stopped</strong>
                </div>
              </div>
            </div>
          </section>
        </main>
      </div>
    </div>

    <div id="playlistsView" class="view">
      <div class="playlist-layout">
        <section class="panel section stack">
          <div class="section-head">
            <h3 class="section-title">Saved Playlists</h3>
            <p class="mini">Create, load, clear or delete MPD playlists</p>
          </div>

          <div class="toolbar-row">
            <input id="playlistNameInput" class="text-input" type="text" placeholder="New playlist">
            <button id="savePlaylistBtn" class="btn primary">Save queue</button>
            <button id="refreshPlaylistsBtn" class="btn">Refresh</button>
          </div>

          <div id="playlistFlash" class="flash"></div>
          <div id="playlistList" class="playlist-list"></div>
        </section>

        <section class="panel section stack">
          <div class="section-head">
            <h3 class="section-title">Current Queue</h3>
            <p class="mini">Reorder, remove tracks or save the current state</p>
          </div>

          <div class="toolbar-row">
            <button id="clearQueueBtn" class="btn warn">Clear queue</button>
            <button id="refreshQueueBtn" class="btn">Reload queue</button>
          </div>

          <div id="queueList" class="queue-list"></div>
        </section>
      </div>
    </div>
  </div>

  <script>
    const state = {
      status: null,
      spectrumBars: Array.from({ length: 30 }, () => 0),
      activeCoverKey: null,
      seekDrag: false,
      volumeDrag: false,
      playlists: [],
      queue: [],
      lastVisibleSpectrum: Array.from({ length: 30 }, () => 12)
    };

    const nodes = {
      connectionState: document.getElementById('connectionState'),
      connectionLed: document.getElementById('connectionLed'),
      spectrumShell: document.getElementById('spectrumShell'),
      spectrumState: document.getElementById('spectrumState'),
      queueMeta: document.getElementById('queueMeta'),
      coverArt: document.getElementById('coverArt'),
      coverFallback: document.getElementById('coverFallback'),
      trackTitle: document.getElementById('trackTitle'),
      trackArtist: document.getElementById('trackArtist'),
      trackAlbum: document.getElementById('trackAlbum'),
      trackFile: document.getElementById('trackFile'),
      trackState: document.getElementById('trackState'),
      playPause: document.getElementById('playPause'),
      elapsedTime: document.getElementById('elapsedTime'),
      trackLength: document.getElementById('trackLength'),
      seekBar: document.getElementById('seekBar'),
      volumeBar: document.getElementById('volumeBar'),
      volumeValue: document.getElementById('volumeValue'),
      repeatBtn: document.getElementById('repeatBtn'),
      randomBtn: document.getElementById('randomBtn'),
      singleBtn: document.getElementById('singleBtn'),
      consumeBtn: document.getElementById('consumeBtn'),
      canvas: document.getElementById('spectrum'),
      playlistNameInput: document.getElementById('playlistNameInput'),
      playlistList: document.getElementById('playlistList'),
      queueList: document.getElementById('queueList'),
      playlistFlash: document.getElementById('playlistFlash'),
      playerView: document.getElementById('playerView'),
      playlistsView: document.getElementById('playlistsView')
    };

    const ctx = nodes.canvas.getContext('2d');

    document.querySelectorAll('[data-command]').forEach((button) => {
      button.addEventListener('click', async () => {
        await sendCommand(button.dataset.command);
        refreshStatus();
      });
    });

    document.querySelectorAll('[data-view-link]').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        setView(link.dataset.viewLink || 'player');
      });
    });

    document.getElementById('savePlaylistBtn').addEventListener('click', async () => {
      const name = nodes.playlistNameInput.value.trim();
      if (!name) {
        flash('Enter a playlist name.', true);
        return;
      }

      await playlistAction('save', { name });
      nodes.playlistNameInput.value = '';
      await refreshPlaylists();
    });

    document.getElementById('refreshPlaylistsBtn').addEventListener('click', refreshPlaylists);
    document.getElementById('refreshQueueBtn').addEventListener('click', refreshPlaylists);
    document.getElementById('clearQueueBtn').addEventListener('click', async () => {
      if (!confirm('Clear the current queue?')) {
        return;
      }

      await playlistAction('clear-queue');
      await refreshPlaylists();
      await refreshStatus();
    });

    nodes.seekBar.addEventListener('pointerdown', () => {
      state.seekDrag = true;
    });

    nodes.seekBar.addEventListener('pointerup', async () => {
      state.seekDrag = false;
      await fetch(`?api=seek&position=${encodeURIComponent(nodes.seekBar.value)}`, { method: 'POST' });
      refreshStatus();
    });

    nodes.volumeBar.addEventListener('input', () => {
      state.volumeDrag = true;
      nodes.volumeValue.textContent = `${nodes.volumeBar.value}%`;
    });

    nodes.volumeBar.addEventListener('change', async () => {
      state.volumeDrag = false;
      await fetch(`?api=volume&volume=${encodeURIComponent(nodes.volumeBar.value)}`, { method: 'POST' });
      refreshStatus();
    });

    async function sendCommand(command) {
      await fetch(`?api=command&command=${encodeURIComponent(command)}`, { method: 'POST' });
    }

    async function refreshStatus() {
      try {
        const response = await fetch('?api=status', { cache: 'no-store' });
        const data = await response.json();
        if (!data.ok) {
          throw new Error(data.error || 'MPD error');
        }

        state.status = data.status;
        renderStatus();
        updateConnection(true, 'Connected to MPD');
      } catch (error) {
        updateConnection(false, 'MPD unreachable');
      }
    }

    async function refreshPlaylists() {
      try {
        const response = await fetch('?api=playlists', { cache: 'no-store' });
        const data = await response.json();
        if (!data.ok) {
          throw new Error(data.error || 'Playlist error');
        }

        state.playlists = data.playlists || [];
        state.queue = data.queue || [];
        renderPlaylists();
      } catch (error) {
        flash(error.message || 'Unable to load playlists', true);
      }
    }

    async function playlistAction(action, payload = {}) {
      const params = new URLSearchParams({ action, ...payload });
      const response = await fetch(`?api=playlist-action&${params.toString()}`, { method: 'POST' });
      const data = await response.json();
      if (!data.ok) {
        flash(data.error || 'Playlist action failed', true);
        return false;
      }

      flash(data.message || 'Action completed');
      return true;
    }

    async function refreshSpectrum() {
      try {
        const response = await fetch('?api=spectrum', { cache: 'no-store' });
        const data = await response.json();
        if (Array.isArray(data.bars) && data.bars.length) {
          const incoming = data.bars.map((value) => Number(value) || 0);
          if (Math.max(...incoming, 0) > 0) {
            state.spectrumBars = incoming;
            state.lastVisibleSpectrum = incoming.slice();
          } else if (state.status && state.status.state === 'playing') {
            state.spectrumBars = state.lastVisibleSpectrum.map((value, index) =>
              Math.max(5, Math.round(value * 0.94 + ((index * 7) % 5)))
            );
          } else {
            state.spectrumBars = incoming;
          }
          nodes.spectrumState.textContent = data.source || 'cava active';
          nodes.spectrumShell.classList.toggle('is-silent',
            (!state.status || state.status.state !== 'playing') && Math.max(...incoming, 0) <= 0
          );
        } else {
          nodes.spectrumState.textContent = data.error || 'No data';
          state.spectrumBars = state.status && state.status.state === 'playing'
            ? state.lastVisibleSpectrum.slice()
            : state.spectrumBars.map(() => 0);
        }
      } catch (error) {
        nodes.spectrumState.textContent = 'Spectrum unavailable';
        state.spectrumBars = state.status && state.status.state === 'playing'
          ? state.lastVisibleSpectrum.slice()
          : state.spectrumBars.map(() => 0);
      }
    }

    function renderStatus() {
      const status = state.status;
      if (!status) {
        return;
      }

      nodes.trackTitle.textContent = status.title || 'No track';
      nodes.trackArtist.textContent = status.artist || 'Unknown artist';
      nodes.trackAlbum.textContent = status.album || 'Unknown album';
      nodes.trackFile.textContent = status.file || '-';
      nodes.trackState.textContent = status.stateLabel;
      nodes.queueMeta.textContent = status.queueLabel;
      nodes.playPause.textContent = status.state === 'playing' ? 'Ⅱ' : '▶';
      nodes.elapsedTime.textContent = formatTime(status.elapsed);
      nodes.trackLength.textContent = formatTime(status.duration);

      if (!state.seekDrag) {
        nodes.seekBar.max = String(Math.max(status.duration, 0));
        nodes.seekBar.value = String(Math.min(status.elapsed, status.duration));
      }

      if (!state.volumeDrag) {
        nodes.volumeBar.value = String(status.volume);
        nodes.volumeValue.textContent = `${status.volume}%`;
      }

      setToggle(nodes.repeatBtn, status.repeat);
      setToggle(nodes.randomBtn, status.random);
      setToggle(nodes.singleBtn, status.single);
      setToggle(nodes.consumeBtn, status.consume);

      const coverKey = `${status.file || ''}|${status.album || ''}|${status.artist || ''}`;
      if (coverKey !== state.activeCoverKey) {
        state.activeCoverKey = coverKey;
        const nextSrc = `?api=cover&t=${encodeURIComponent(coverKey)}`;
        nodes.coverFallback.classList.remove('is-hidden');
        nodes.coverArt.classList.add('page-hidden');
        nodes.coverArt.onload = () => {
          nodes.coverArt.classList.remove('page-hidden');
          nodes.coverFallback.classList.add('is-hidden');
        };
        nodes.coverArt.onerror = () => {
          nodes.coverArt.classList.add('page-hidden');
          nodes.coverFallback.classList.remove('is-hidden');
        };
        nodes.coverArt.src = nextSrc;
      }
    }

    function renderPlaylists() {
      nodes.playlistList.innerHTML = '';
      nodes.queueList.innerHTML = '';

      if (!state.playlists.length) {
        nodes.playlistList.innerHTML = '<div class="empty-state">No saved MPD playlists.</div>';
      } else {
        state.playlists.forEach((playlist) => {
          const card = document.createElement('article');
          card.className = 'playlist-card';
          card.innerHTML = `
            <div>
              <h4>${escapeHtml(playlist.name)}</h4>
              <p>${playlist.count} saved tracks</p>
            </div>
            <div class="playlist-actions">
              <button class="btn primary" data-playlist-load="${escapeAttr(playlist.name)}">Load</button>
              <button class="btn" data-playlist-append="${escapeAttr(playlist.name)}">Append</button>
              <button class="btn danger" data-playlist-delete="${escapeAttr(playlist.name)}">Delete</button>
            </div>
          `;
          nodes.playlistList.appendChild(card);
        });
      }

      if (!state.queue.length) {
        nodes.queueList.innerHTML = '<div class="empty-state">The current queue is empty.</div>';
      } else {
        state.queue.forEach((item) => {
          const row = document.createElement('article');
          row.className = 'queue-row';
          row.innerHTML = `
            <div class="queue-index">${item.position}</div>
            <div class="queue-main">
              <strong>${escapeHtml(item.title || item.file || 'Untitled track')}</strong>
              <span>${escapeHtml(item.artist || 'Unknown artist')}</span>
            </div>
            <div class="queue-actions">
              <button class="btn" data-queue-up="${item.position}">Up</button>
              <button class="btn" data-queue-down="${item.position}">Down</button>
              <button class="btn danger" data-queue-remove="${item.position}">Remove</button>
            </div>
          `;
          nodes.queueList.appendChild(row);
        });
      }

      bindPlaylistButtons();
    }

    function bindPlaylistButtons() {
      document.querySelectorAll('[data-playlist-load]').forEach((button) => {
        button.onclick = async () => {
          await playlistAction('load-replace', { name: button.dataset.playlistLoad });
          await refreshPlaylists();
          await refreshStatus();
        };
      });

      document.querySelectorAll('[data-playlist-append]').forEach((button) => {
        button.onclick = async () => {
          await playlistAction('load-append', { name: button.dataset.playlistAppend });
          await refreshPlaylists();
        };
      });

      document.querySelectorAll('[data-playlist-delete]').forEach((button) => {
        button.onclick = async () => {
          if (!confirm(`Delete playlist "${button.dataset.playlistDelete}"?`)) {
            return;
          }
          await playlistAction('delete-playlist', { name: button.dataset.playlistDelete });
          await refreshPlaylists();
        };
      });

      document.querySelectorAll('[data-queue-remove]').forEach((button) => {
        button.onclick = async () => {
          await playlistAction('queue-remove', { position: button.dataset.queueRemove });
          await refreshPlaylists();
          await refreshStatus();
        };
      });

      document.querySelectorAll('[data-queue-up]').forEach((button) => {
        button.onclick = async () => {
          await playlistAction('queue-move-up', { position: button.dataset.queueUp });
          await refreshPlaylists();
        };
      });

      document.querySelectorAll('[data-queue-down]').forEach((button) => {
        button.onclick = async () => {
          await playlistAction('queue-move-down', { position: button.dataset.queueDown });
          await refreshPlaylists();
        };
      });
    }

    function flash(message, isError = false) {
      nodes.playlistFlash.textContent = message;
      nodes.playlistFlash.classList.toggle('error', Boolean(isError));
    }

    function updateConnection(ok, label) {
      nodes.connectionState.lastElementChild.textContent = label;
      nodes.connectionLed.classList.remove('ok', 'pause');
      if (!ok) {
        return;
      }

      if (state.status && state.status.state === 'paused') {
        nodes.connectionLed.classList.add('pause');
        return;
      }

      if (state.status && state.status.state === 'playing') {
        nodes.connectionLed.classList.add('ok');
      }
    }

    function setToggle(node, enabled) {
      node.classList.toggle('toggle-on', Boolean(enabled));
    }

    function setView(view) {
      const isPlaylistView = view === 'playlists';
      nodes.playerView.classList.toggle('active', !isPlaylistView);
      nodes.playlistsView.classList.toggle('active', isPlaylistView);
      document.querySelectorAll('[data-view-link]').forEach((link) => {
        link.classList.toggle('active', link.dataset.viewLink === view);
      });
      window.location.hash = isPlaylistView ? 'playlists' : 'player';

      if (isPlaylistView) {
        refreshPlaylists();
      }
    }

    function formatTime(totalSeconds) {
      const safe = Math.max(0, Number(totalSeconds) || 0);
      const hours = Math.floor(safe / 3600);
      const minutes = Math.floor((safe % 3600) / 60);
      const seconds = safe % 60;
      if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
      }
      return `${minutes}:${String(seconds).padStart(2, '0')}`;
    }

    function drawSpectrum() {
      const width = nodes.canvas.clientWidth;
      const height = nodes.canvas.clientHeight;
      if (nodes.canvas.width !== width || nodes.canvas.height !== height) {
        nodes.canvas.width = width;
        nodes.canvas.height = height;
      }

      ctx.clearRect(0, 0, width, height);

      const bars = state.spectrumBars;
      const maxBar = Math.max(...bars, 0);
      if (maxBar <= 0) {
        requestAnimationFrame(drawSpectrum);
        return;
      }

      const barCount = bars.length || 1;
      const gap = width < 500 ? 4 : 6;
      const barWidth = Math.max(3, (width - gap * (barCount - 1)) / barCount);
      ctx.fillStyle = '#c7c7c5';

      bars.forEach((value, index) => {
        const normalized = Math.max(0, Math.min(1, value / 100));
        const barHeight = normalized * (height - 5);
        if (barHeight < 1) {
          return;
        }
        const x = index * (barWidth + gap);
        const y = height - barHeight - 2;
        ctx.fillRect(Math.round(x), Math.round(y), Math.max(2, Math.floor(barWidth)), Math.round(barHeight));
      });

      requestAnimationFrame(drawSpectrum);
    }

    function escapeHtml(value) {
      return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    function escapeAttr(value) {
      return escapeHtml(value);
    }

    if (window.location.hash === '#playlists') {
      setView('playlists');
    }

    refreshStatus();
    refreshSpectrum();
    setInterval(refreshStatus, 2000);
    setInterval(() => {
      if (!document.hidden) {
        refreshSpectrum();
      }
    }, 120);
    requestAnimationFrame(drawSpectrum);
  </script>
</body>
</html>
<?php
}

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function getStatus(array $config): array
{
    $currentRaw = trim(runMpc(['--format', '%artist%\t%title%\t%album%\t%file%\t%time%', 'current'], $config));
    $statusRaw = runMpc(['status'], $config);
    $song = parseCurrent($currentRaw);
    $status = parseStatus($statusRaw);

    return [
        'ok' => true,
        'status' => [
            'artist' => $song['artist'],
            'title' => $song['title'],
            'album' => $song['album'],
            'file' => $song['file'],
            'duration' => $status['duration'] > 0 ? $status['duration'] : $song['duration'],
            'elapsed' => $status['elapsed'],
            'volume' => $status['volume'],
            'state' => $status['state'],
            'stateLabel' => ucfirst($status['state']),
            'repeat' => $status['repeat'],
            'random' => $status['random'],
            'single' => $status['single'],
            'consume' => $status['consume'],
            'queueLabel' => $status['queueLabel'],
        ],
    ];
}

function getPlaylistsData(array $config): array
{
    $playlistsRaw = trim(runMpc(['lsplaylists'], $config));
    $playlistNames = [];
    if ($playlistsRaw !== '') {
        foreach (preg_split('/\r?\n/', $playlistsRaw) ?: [] as $line) {
            $name = trim($line);
            if ($name !== '') {
                $playlistNames[] = $name;
            }
        }
    }

    $playlists = [];
    foreach ($playlistNames as $name) {
        $entries = trim(runMpc(['--format', '%file%', 'playlist', $name], $config));
        $count = $entries === '' ? 0 : count(preg_split('/\r?\n/', $entries) ?: []);
        $playlists[] = [
            'name' => $name,
            'count' => $count,
        ];
    }

    $queueRaw = trim(runMpc(['--format', '%position%\t%artist%\t%title%\t%file%', 'playlist'], $config));
    $queue = [];
    if ($queueRaw !== '') {
        foreach (preg_split('/\r?\n/', $queueRaw) ?: [] as $line) {
            $parts = array_pad(explode("\t", $line), 4, '');
            $queue[] = [
                'position' => (int) $parts[0],
                'artist' => trim($parts[1]),
                'title' => trim($parts[2]),
                'file' => trim($parts[3]),
            ];
        }
    }

    return [
        'ok' => true,
        'playlists' => $playlists,
        'queue' => $queue,
    ];
}

function getPlaylistContents(string $name, array $config): array
{
    if ($name === '' || !in_array($name, listPlaylistNames($config), true)) {
        return ['ok' => false, 'error' => 'Playlist non trovata'];
    }

    $raw = trim(runMpc(['--format', '%artist%\t%title%\t%album%\t%file%', 'playlist', $name], $config));
    $tracks = [];
    foreach ($raw === '' ? [] : (preg_split('/\r?\n/', $raw) ?: []) as $index => $line) {
        $parts = array_pad(explode("\t", $line), 4, '');
        $tracks[] = [
            'position' => $index,
            'displayPosition' => $index + 1,
            'artist' => trim($parts[0]),
            'title' => trim($parts[1]),
            'album' => trim($parts[2]),
            'file' => trim($parts[3]),
        ];
    }

    return ['ok' => true, 'name' => $name, 'tracks' => $tracks];
}

function rescanDatabase(array $config): array
{
    set_time_limit(0);
    runMpc(['rescan', '--wait'], $config);
    return ['ok' => true, 'message' => 'Aggiornamento del database completato.'];
}

function searchLibrary(string $query, array $config): array
{
    if (strlen($query) < 2) {
        return ['ok' => false, 'error' => 'Inserisci almeno 2 caratteri'];
    }

    $raw = trim(runMpc([
        '--format',
        '%artist%\t%title%\t%album%\t%file%',
        'search',
        'any',
        $query,
    ], $config));

    $tracks = [];
    $lines = $raw === '' ? [] : (preg_split('/\r?\n/', $raw) ?: []);
    foreach (array_slice($lines, 0, 100) as $line) {
        $parts = array_pad(explode("\t", $line), 4, '');
        $file = trim($parts[3]);
        if ($file === '') {
            continue;
        }
        $tracks[] = [
            'artist' => trim($parts[0]),
            'title' => trim($parts[1]),
            'album' => trim($parts[2]),
            'file' => $file,
        ];
    }

    return [
        'ok' => true,
        'tracks' => $tracks,
        'truncated' => count($lines) > 100,
    ];
}

function browseLibrary(string $path, array $config): array
{
    if ($path !== '' && (str_contains($path, "\0") || preg_match('~(^|/)\.\.(/|$)~', $path))) {
        return ['ok' => false, 'error' => 'Percorso della libreria non valido'];
    }

    $args = ['ls'];
    if ($path !== '') {
        $args[] = $path;
    }
    $raw = trim(runMpc($args, $config));
    $entries = [];
    $audioExtensions = ['mp3', 'flac', 'ogg', 'opus', 'm4a', 'aac', 'wav', 'wma', 'ape', 'mpc', 'alac', 'aiff', 'aif'];
    // `mpc ls` may include stored playlists together with music database
    // entries.  They are not directories and must only be shown in the
    // dedicated "Playlist salvate" pane; otherwise clicking one causes MPD
    // to answer "No such directory".
    $storedPlaylists = array_fill_keys(listPlaylistNames($config), true);

    foreach ($raw === '' ? [] : (preg_split('/\r?\n/', $raw) ?: []) as $uri) {
        $uri = trim($uri);
        if ($uri === '') {
            continue;
        }
        if (isset($storedPlaylists[$uri])) {
            continue;
        }
        $extension = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
        $isTrack = in_array($extension, $audioExtensions, true);
        $entry = [
            'type' => $isTrack ? 'track' : 'directory',
            'path' => $uri,
            'name' => basename($uri),
        ];
        if ($isTrack) {
            $entry['file'] = $uri;
            $entry['title'] = pathinfo($uri, PATHINFO_FILENAME);
            $entry['artist'] = '';
            $entry['album'] = '';
        }
        $entries[] = $entry;
    }

    usort($entries, static function (array $a, array $b): int {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'directory' ? -1 : 1;
        }
        return strnatcasecmp($a['name'], $b['name']);
    });

    return ['ok' => true, 'path' => $path, 'entries' => $entries];
}

function parseCurrent(string $currentRaw): array
{
    $parts = array_pad(explode("\t", $currentRaw), 5, '');

    return [
        'artist' => trim($parts[0]) ?: '',
        'title' => trim($parts[1]) ?: '',
        'album' => trim($parts[2]) ?: '',
        'file' => trim($parts[3]) ?: '',
        'duration' => durationToSeconds(trim($parts[4])),
    ];
}

function parseStatus(string $statusRaw): array
{
    $lines = preg_split('/\r?\n/', trim($statusRaw)) ?: [];
    $state = 'stopped';
    $elapsed = 0;
    $duration = 0;
    $volume = 0;
    $repeat = false;
    $random = false;
    $single = false;
    $consume = false;
    $queueLabel = 'Queue unavailable';

    foreach ($lines as $line) {
        if (preg_match('/\[(playing|paused|stopped)\]/', $line, $matches)) {
            $state = $matches[1];
        }

        if (preg_match('/#(\d+)\/(\d+)/', $line, $matches)) {
            $queueLabel = 'Track ' . $matches[1] . ' of ' . $matches[2];
        }

        if (preg_match('/(\d+:\d+(?::\d+)?)\/(\d+:\d+(?::\d+)?)/', $line, $matches)) {
            $elapsed = durationToSeconds($matches[1]);
            $duration = durationToSeconds($matches[2]);
        }

        if (preg_match('/volume:\s*(-?\d+)%/i', $line, $matches)) {
            $volume = max(0, min(100, (int) $matches[1]));
        }

        if (preg_match('/repeat:\s*(on|off)/i', $line, $matches)) {
            $repeat = $matches[1] === 'on';
        }

        if (preg_match('/random:\s*(on|off)/i', $line, $matches)) {
            $random = $matches[1] === 'on';
        }

        if (preg_match('/single:\s*(on|off|oneshot)/i', $line, $matches)) {
            $single = $matches[1] !== 'off';
        }

        if (preg_match('/consume:\s*(on|off|oneshot)/i', $line, $matches)) {
            $consume = $matches[1] !== 'off';
        }
    }

    return [
        'state' => $state,
        'elapsed' => $elapsed,
        'duration' => $duration,
        'volume' => $volume,
        'repeat' => $repeat,
        'random' => $random,
        'single' => $single,
        'consume' => $consume,
        'queueLabel' => $queueLabel,
    ];
}

function durationToSeconds(string $value): int
{
    if ($value === '') {
        return 0;
    }

    $parts = array_map('intval', explode(':', $value));
    if (count($parts) === 2) {
        return ($parts[0] * 60) + $parts[1];
    }

    if (count($parts) === 3) {
        return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
    }

    return 0;
}

function runPlayerCommand(string $command, array $config): array
{
    $map = [
        'toggle' => ['toggle'],
        'next' => ['next'],
        'prev' => ['prev'],
        'repeat' => ['repeat'],
        'random' => ['random'],
        'single' => ['single'],
        'consume' => ['consume'],
    ];

    if (!isset($map[$command])) {
        return ['ok' => false, 'error' => 'Unsupported command'];
    }

    runMpc($map[$command], $config);
    return ['ok' => true];
}

function switchAudioOutput(string $target, array $config): array
{
    // Output IDs come from the audio_output blocks in /etc/mpd.conf (in file order).
    $outputs = [
        'usb' => ['id' => 1, 'label' => 'scheda USB'],
        'hdmi' => ['id' => 2, 'label' => 'HDMI'],
    ];

    if (!isset($outputs[$target])) {
        return ['ok' => false, 'error' => 'Uscita audio sconosciuta'];
    }

    foreach ($outputs as $key => $output) {
        runMpc([$key === $target ? 'enable' : 'disable', (string) $output['id']], $config);
    }

    return ['ok' => true, 'message' => 'Uscita audio: ' . $outputs[$target]['label']];
}

function playQueueTrack(int $position, array $config): array
{
    if ($position < 0) {
        return ['ok' => false, 'error' => 'Posizione non valida'];
    }
    runMpc(['play', (string) $position], $config);
    return ['ok' => true];
}

function runPlaylistAction(string $action, array $config): array
{
    $name = trim((string) ($_POST['name'] ?? $_GET['name'] ?? ''));
    $uri = trim((string) ($_POST['uri'] ?? $_GET['uri'] ?? ''));
    $position = max(0, (int) ($_POST['position'] ?? $_GET['position'] ?? 0));

    switch ($action) {
        case 'create-empty':
            if ($name === '') {
                return ['ok' => false, 'error' => 'Inserisci il nome della playlist'];
            }
            if (in_array($name, listPlaylistNames($config), true)) {
                return ['ok' => false, 'error' => 'Esiste già una playlist con questo nome'];
            }
            $all = trim(runMpc(['--format', '%file%', 'listall'], $config));
            $firstTrack = $all === '' ? '' : trim((preg_split('/\r?\n/', $all) ?: [''])[0]);
            if ($firstTrack === '') {
                return ['ok' => false, 'error' => 'Non è possibile creare una playlist vuota: il database musicale non contiene brani'];
            }
            runMpdCommand('playlistadd', [$name, $firstTrack], $config);
            runMpdCommand('playlistdelete', [$name, '0'], $config);
            return ['ok' => true, 'message' => 'Playlist vuota creata'];

        case 'save':
            if ($name === '') {
                return ['ok' => false, 'error' => 'Missing playlist name'];
            }

            $existing = listPlaylistNames($config);
            if (in_array($name, $existing, true)) {
                return ['ok' => false, 'error' => 'Esiste già una playlist con questo nome'];
            }
            runMpc(['save', $name], $config);
            return ['ok' => true, 'message' => 'Nuova playlist creata dalla coda corrente'];

        case 'update':
            if ($name === '') {
                return ['ok' => false, 'error' => 'Missing playlist name'];
            }
            if (!in_array($name, listPlaylistNames($config), true)) {
                return ['ok' => false, 'error' => 'Playlist non trovata'];
            }
            runMpc(['rm', $name], $config);
            runMpc(['save', $name], $config);
            return ['ok' => true, 'message' => 'Playlist aggiornata con la coda corrente'];

        case 'load-replace':
            if ($name === '') {
                return ['ok' => false, 'error' => 'Missing playlist name'];
            }
            runMpc(['clear'], $config);
            runMpc(['load', $name], $config);
            return ['ok' => true, 'message' => 'Playlist loaded'];

        case 'load-append':
            if ($name === '') {
                return ['ok' => false, 'error' => 'Missing playlist name'];
            }
            runMpc(['load', $name], $config);
            return ['ok' => true, 'message' => 'Playlist appended'];

        case 'delete-playlist':
            if ($name === '') {
                return ['ok' => false, 'error' => 'Missing playlist name'];
            }
            runMpc(['rm', $name], $config);
            return ['ok' => true, 'message' => 'Playlist deleted'];

        case 'playlist-add-track':
            if ($name === '' || $uri === '') {
                return ['ok' => false, 'error' => 'Playlist o brano mancante'];
            }
            if (!in_array($name, listPlaylistNames($config), true)) {
                return ['ok' => false, 'error' => 'Playlist non trovata'];
            }
            runMpdCommand('playlistadd', [$name, $uri], $config);
            return ['ok' => true, 'message' => 'Brano aggiunto alla playlist'];

        case 'playlist-add-directory':
            if ($name === '' || $uri === '') {
                return ['ok' => false, 'error' => 'Playlist o cartella mancante'];
            }
            if (!in_array($name, listPlaylistNames($config), true)) {
                return ['ok' => false, 'error' => 'Playlist non trovata'];
            }
            $raw = trim(runMpc(['--format', '%file%', 'listall', $uri], $config));
            $tracks = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw) ?: [])));
            if ($tracks === []) {
                return ['ok' => false, 'error' => 'La cartella non contiene brani indicizzati'];
            }
            foreach ($tracks as $track) {
                runMpdCommand('playlistadd', [$name, $track], $config);
            }
            return ['ok' => true, 'message' => count($tracks) . ' brani aggiunti alla playlist'];

        case 'queue-add-track':
            if ($uri === '') {
                return ['ok' => false, 'error' => 'Brano mancante'];
            }
            runMpc(['add', $uri], $config);
            return ['ok' => true, 'message' => 'Brano aggiunto alla coda'];

        case 'queue-play-track':
            if ($uri === '') {
                return ['ok' => false, 'error' => 'Brano mancante'];
            }
            $response = runMpdCommand('addid', [$uri], $config);
            $id = null;
            foreach ($response as $line) {
                if (preg_match('/^Id:\s*(\d+)$/', $line, $matches)) {
                    $id = (int) $matches[1];
                    break;
                }
            }
            if ($id === null) {
                return ['ok' => false, 'error' => 'Impossibile aggiungere il brano'];
            }
            runMpdCommand('playid', [(string) $id], $config);
            return ['ok' => true, 'message' => 'Riproduzione avviata'];

        case 'queue-add-directory':
            if ($uri === '') {
                return ['ok' => false, 'error' => 'Cartella mancante'];
            }
            $raw = trim(runMpc(['--format', '%file%', 'listall', $uri], $config));
            $tracks = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw) ?: [])));
            if ($tracks === []) {
                return ['ok' => false, 'error' => 'La cartella non contiene brani indicizzati'];
            }
            foreach ($tracks as $track) {
                runMpc(['add', $track], $config);
            }
            return ['ok' => true, 'message' => count($tracks) . ' brani aggiunti alla coda'];

        case 'playlist-remove-track':
            if ($name === '') {
                return ['ok' => false, 'error' => 'Playlist mancante'];
            }
            if (!in_array($name, listPlaylistNames($config), true)) {
                return ['ok' => false, 'error' => 'Playlist non trovata'];
            }
            runMpdCommand('playlistdelete', [$name, (string) $position], $config);
            return ['ok' => true, 'message' => 'Brano rimosso dalla playlist'];

        case 'clear-queue':
            runMpc(['clear'], $config);
            return ['ok' => true, 'message' => 'Queue cleared'];

        case 'queue-remove':
            runMpc(['del', (string) $position], $config);
            return ['ok' => true, 'message' => 'Track removed from queue'];

        case 'queue-move-up':
            if ($position <= 1) {
                return ['ok' => true, 'message' => 'Track is already at the top'];
            }
            runMpc(['move', (string) $position, (string) ($position - 1)], $config);
            return ['ok' => true, 'message' => 'Track moved up'];

        case 'queue-move-down':
            $queue = getPlaylistsData($config)['queue'];
            $maxPosition = count($queue);
            if ($position >= $maxPosition) {
                return ['ok' => true, 'message' => 'Track is already at the bottom'];
            }
            runMpc(['move', (string) $position, (string) ($position + 1)], $config);
            return ['ok' => true, 'message' => 'Track moved down'];

        default:
            return ['ok' => false, 'error' => 'Unsupported playlist action'];
    }
}

function listPlaylistNames(array $config): array
{
    $raw = trim(runMpc(['lsplaylists'], $config));
    if ($raw === '') {
        return [];
    }

    $names = [];
    foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
        $name = trim($line);
        if ($name !== '') {
            $names[] = $name;
        }
    }

    return $names;
}

function seekTo(int $position, array $config): array
{
    runMpc(['seek', (string) max(0, $position)], $config);
    return ['ok' => true];
}

function setVolume(int $volume, array $config): array
{
    runMpc(['volume', (string) max(0, min(100, $volume))], $config);
    return ['ok' => true];
}

function ensureSpectrumService(array $config): bool
{
    if (spectrumIsReachable($config['spectrum_url'])) {
        return true;
    }

    if (!is_file($config['spectrum_script']) || !is_readable($config['spectrum_script'])) {
        return false;
    }

    $command = [
        $config['python_bin'],
        $config['spectrum_script'],
        '--daemon',
    ];

    try {
        runProcess($command);
    } catch (Throwable $exception) {
        return false;
    }

    // Give the detached HTTP listener a brief moment to bind its socket.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        usleep(50000);
        if (spectrumIsReachable($config['spectrum_url'])) {
            return true;
        }
    }

    return false;
}

function spectrumIsReachable(string $url): bool
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 0.12,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return false;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) && isset($decoded['bars']) && is_array($decoded['bars']);
}

function proxySpectrum(array $config): void
{
    if (!spectrumIsReachable($config['spectrum_url'])) {
        ensureSpectrumService($config);
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 0.35,
        ],
    ]);

    $response = @file_get_contents($config['spectrum_url'], false, $context);
    if ($response === false) {
        jsonResponse([
            'bars' => [],
            'error' => 'mpd-spectrum.py unreachable',
        ], 502);
        return;
    }

    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $response;
}

function serveCover(array $config): void
{
    $currentFile = trim(runMpc(['--format', '%file%', 'current'], $config));
    if ($currentFile === '') {
        http_response_code(404);
        return;
    }

    $binary = runMpcBinary(['readpicture', $currentFile], $config);
    if ($binary === '' || !looksLikeImage($binary)) {
        $binary = runMpcBinary(['albumart', $currentFile], $config);
    }

    if ($binary === '' || !looksLikeImage($binary)) {
        http_response_code(404);
        return;
    }

    $mime = detectMime($binary);
    header('Content-Type: ' . $mime);
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Content-Length: ' . strlen($binary));
    echo $binary;
}

function looksLikeImage(string $binary): bool
{
    return str_starts_with($binary, "\xFF\xD8\xFF")
        || str_starts_with($binary, "\x89PNG\r\n\x1A\n")
        || str_starts_with($binary, "RIFF")
        || str_starts_with($binary, "GIF8");
}

function detectMime(string $binary): string
{
    if (function_exists('finfo_buffer')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_buffer($finfo, $binary);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }
    }

    return 'image/jpeg';
}

function runMpc(array $args, array $config): string
{
    $output = runProcess(buildMpcCommand($args, $config));
    return trim($output['stdout']);
}

function runMpcBinary(array $args, array $config): string
{
    $output = runProcess(buildMpcCommand($args, $config));
    return $output['stdout'];
}

/**
 * Runs a command directly through MPD's text protocol.
 *
 * Some MPD protocol commands (including playlistadd) are not exposed by
 * every mpc release. Talking to MPD directly also avoids changing the
 * current queue merely to edit a stored playlist.
 */
function runMpdCommand(string $command, array $arguments, array $config): array
{
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $command)) {
        throw new InvalidArgumentException('Comando MPD non valido');
    }

    $socket = @stream_socket_client(
        'tcp://' . $config['mpd_host'] . ':' . $config['mpd_port'],
        $errorNumber,
        $errorMessage,
        3.0
    );
    if (!is_resource($socket)) {
        throw new RuntimeException('MPD non raggiungibile: ' . ($errorMessage ?: (string) $errorNumber));
    }

    stream_set_timeout($socket, 5);
    try {
        $greeting = fgets($socket);
        if (!is_string($greeting) || !str_starts_with($greeting, 'OK MPD ')) {
            throw new RuntimeException('Risposta iniziale MPD non valida');
        }

        $encoded = array_map('quoteMpdArgument', $arguments);
        $request = $command . ($encoded === [] ? '' : ' ' . implode(' ', $encoded)) . "\n";
        if (fwrite($socket, $request) === false) {
            throw new RuntimeException('Invio del comando a MPD non riuscito');
        }

        $response = [];
        while (($line = fgets($socket)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === 'OK') {
                return $response;
            }
            if (str_starts_with($line, 'ACK ')) {
                throw new RuntimeException('MPD: ' . $line);
            }
            $response[] = $line;
        }

        $meta = stream_get_meta_data($socket);
        throw new RuntimeException(!empty($meta['timed_out']) ? 'Timeout nella risposta di MPD' : 'Connessione MPD interrotta');
    } finally {
        fclose($socket);
    }
}

function quoteMpdArgument(mixed $value): string
{
    $value = (string) $value;
    if (str_contains($value, "\n") || str_contains($value, "\r")) {
        throw new InvalidArgumentException('Parametro MPD non valido');
    }

    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
}

function buildMpcCommand(array $args, array $config): array
{
    return array_merge(
        [$config['mpc_bin'], '--host', $config['mpd_host'], '--port', (string) $config['mpd_port']],
        $args
    );
}

function runProcess(array $command): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start process');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException(trim($stderr) ?: 'mpc command failed');
    }

    return [
        'stdout' => $stdout === false ? '' : $stdout,
        'stderr' => $stderr === false ? '' : $stderr,
    ];
}
