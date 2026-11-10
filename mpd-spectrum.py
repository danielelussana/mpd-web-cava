#!/usr/bin/env python3
"""
Tiny HTTP bridge between cava and the web UI.

By default this script reuses the user's ~/.config/cava if present,
overriding only the output section so cava writes raw ascii bars to stdout.
That means if plain `cava` already works on the Raspberry, this script should
inherit the same FIFO/input settings automatically.

Useful environment variables:
    MPD_SPECTRUM_HOST=127.0.0.1
    MPD_SPECTRUM_PORT=44883
    MPD_SPECTRUM_BARS=30
    MPD_SPECTRUM_FRAMERATE=60
    MPD_SPECTRUM_INPUT_METHOD=fifo
    MPD_SPECTRUM_SOURCE=/tmp/mpd-cava.fifo
    MPD_SPECTRUM_SAMPLE_RATE=44100
    MPD_SPECTRUM_SAMPLE_BITS=16
    MPD_SPECTRUM_CHANNELS=2
    MPD_SPECTRUM_USE_USER_CONFIG=1
    MPD_SPECTRUM_USER_CONFIG=~/.config/cava
    MPD_SPECTRUM_STALL_TIMEOUT=15
"""

from __future__ import annotations

import json
import os
import re
import signal
import subprocess
import tempfile
import threading
import time
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path


HOST = os.getenv("MPD_SPECTRUM_HOST", "127.0.0.1")
PORT = int(os.getenv("MPD_SPECTRUM_PORT", "44883"))
BARS = max(12, int(os.getenv("MPD_SPECTRUM_BARS", "30")))
FRAMERATE = max(15, int(os.getenv("MPD_SPECTRUM_FRAMERATE", "60")))
INPUT_METHOD = os.getenv("MPD_SPECTRUM_INPUT_METHOD", "fifo")
INPUT_SOURCE = os.getenv("MPD_SPECTRUM_SOURCE", "/tmp/mpd-cava.fifo")
INPUT_SAMPLE_RATE = int(os.getenv("MPD_SPECTRUM_SAMPLE_RATE", "44100"))
INPUT_SAMPLE_BITS = int(os.getenv("MPD_SPECTRUM_SAMPLE_BITS", "16"))
INPUT_CHANNELS = int(os.getenv("MPD_SPECTRUM_CHANNELS", "2"))
CAVA_BIN = os.getenv("MPD_SPECTRUM_CAVA_BIN", "cava")
USE_USER_CONFIG = os.getenv("MPD_SPECTRUM_USE_USER_CONFIG", "1") != "0"
USER_CONFIG_PATH = Path(os.path.expanduser(os.getenv("MPD_SPECTRUM_USER_CONFIG", "~/.config/cava")))
# Se cava resta "muto" (bar tutte a zero) più a lungo di questa soglia,
# viene considerato bloccato (es. dopo una disconnessione/riconnessione
# della fifo) e viene riavviato forzatamente, anche se il processo non e'
# mai andato in crash e non ha scritto errori.
STALL_TIMEOUT = float(os.getenv("MPD_SPECTRUM_STALL_TIMEOUT", "15"))


def resolve_user_config_path(path: Path) -> Path | None:
    if not path.exists():
        return None
    if path.is_file():
        return path
    if path.is_dir():
        candidate = path / "config"
        if candidate.exists() and candidate.is_file():
            return candidate
    return None


def replace_or_append_section(text: str, section: str, values: dict[str, str], merge: bool = True) -> str:
    pattern = re.compile(rf"(?ms)^\[{re.escape(section)}\]\s*\n.*?(?=^\[|\Z)")
    existing: dict[str, str] = {}

    match = pattern.search(text)
    if match and merge:
        for raw_line in match.group(0).splitlines()[1:]:
            stripped = raw_line.strip()
            if not stripped or stripped.startswith(";") or stripped.startswith("#") or "=" not in stripped:
                continue
            key, value = stripped.split("=", 1)
            existing[key.strip()] = value.strip()

    existing.update(values)
    block = "[" + section + "]\n" + "\n".join(f"{key} = {value}" for key, value in existing.items())

    if match:
        return pattern.sub(block + "\n\n", text, count=1)

    base = text.rstrip()
    if base:
        return base + "\n\n" + block + "\n"
    return block + "\n"


def build_cava_config() -> str:
    resolved_user_config = resolve_user_config_path(USER_CONFIG_PATH)
    if USE_USER_CONFIG and resolved_user_config is not None:
        base = resolved_user_config.read_text(encoding="utf-8")
        base = replace_or_append_section(
            base,
            "general",
            {
                "framerate": str(FRAMERATE),
                "bars": str(BARS),
            },
            merge=True,
        )
        base = replace_or_append_section(
            base,
            "output",
            {
                "method": "raw",
                "raw_target": "/dev/stdout",
                "data_format": "ascii",
                "ascii_max_range": "100",
                "bar_delimiter": "59",
                "frame_delimiter": "10",
                "channels": "stereo",
            },
            merge=False,
        )
        return base.strip()

    return f"""
[general]
framerate = {FRAMERATE}
bars = {BARS}
lower_cutoff_freq = 50
higher_cutoff_freq = 12000

[input]
method = {INPUT_METHOD}
source = {INPUT_SOURCE}
sample_rate = {INPUT_SAMPLE_RATE}
sample_bits = {INPUT_SAMPLE_BITS}
channels = {INPUT_CHANNELS}

[output]
method = raw
raw_target = /dev/stdout
data_format = ascii
ascii_max_range = 100
bar_delimiter = 59
frame_delimiter = 10
channels = stereo

[smoothing]
noise_reduction = 68
monstercat = 1
waves = 0
""".strip()


class SpectrumState:
    def __init__(self) -> None:
        self._bars = [0] * BARS
        self._lock = threading.Lock()
        self._last_update = 0.0
        self._last_error = "cava non ancora avviato"
        self._zero_since: float | None = None
        self._stop = threading.Event()
        self._config_path = self._write_config()
        self._worker = threading.Thread(target=self._run_forever, daemon=True)

    def start(self) -> None:
        self._worker.start()

    def stop(self) -> None:
        self._stop.set()

    def snapshot(self) -> dict:
        with self._lock:
            age_ms = int((time.time() - self._last_update) * 1000) if self._last_update else None
            return {
                "bars": self._bars[:],
                "source": f"cava {INPUT_METHOD}:{INPUT_SOURCE}",
                "updated_at": self._last_update,
                "age_ms": age_ms,
                "error": self._last_error if age_ms is None or age_ms > 1500 else None,
                "stalled": self._is_stalled_locked(),
            }

    def _write_config(self) -> str:
        config_text = build_cava_config()
        path = Path(tempfile.gettempdir()) / "mpd-spectrum-cava.conf"
        path.write_text(config_text, encoding="utf-8")
        return str(path)

    def _is_stalled_locked(self) -> bool:
        # Deve essere chiamato con self._lock già acquisito.
        return self._zero_since is not None and (time.time() - self._zero_since) > STALL_TIMEOUT

    def is_stalled(self) -> bool:
        with self._lock:
            return self._is_stalled_locked()

    def _run_forever(self) -> None:
        while not self._stop.is_set():
            process = None
            with self._lock:
                self._zero_since = None
            try:
                process = subprocess.Popen(
                    [CAVA_BIN, "-p", self._config_path],
                    stdout=subprocess.PIPE,
                    stderr=subprocess.PIPE,
                    stdin=subprocess.DEVNULL,
                    text=True,
                    encoding="utf-8",
                    errors="ignore",
                    bufsize=1,
                )

                stderr_thread = threading.Thread(target=self._read_stderr, args=(process,), daemon=True)
                stderr_thread.start()

                assert process.stdout is not None
                for line in process.stdout:
                    if self._stop.is_set():
                        break
                    self._consume_line(line)
                    if self.is_stalled():
                        self._set_error(
                            f"cava bloccato: nessun segnale reale da oltre {STALL_TIMEOUT:.0f}s, riavvio"
                        )
                        break

                if process.poll() is None:
                    # Uscito dal for perché "stalled" o stop, ma il processo
                    # è ancora vivo: lo terminiamo qui sotto, nel finally.
                    pass
                else:
                    code = process.wait(timeout=1)
                    self._set_error(f"cava terminato con codice {code}")
            except Exception as exc:  # pragma: no cover
                self._set_error(str(exc))
            finally:
                if process and process.poll() is None:
                    process.terminate()
                    try:
                        process.wait(timeout=1)
                    except subprocess.TimeoutExpired:
                        process.kill()

            if not self._stop.is_set():
                time.sleep(1.2)

    def _read_stderr(self, process: subprocess.Popen[str]) -> None:
        assert process.stderr is not None
        for line in process.stderr:
            cleaned = line.strip()
            if cleaned:
                self._set_error(cleaned)

    def _consume_line(self, line: str) -> None:
        values = []
        for raw in line.strip().split(";"):
            raw = raw.strip()
            if not raw:
                continue
            try:
                values.append(max(0, min(100, int(raw))))
            except ValueError:
                return

        if not values:
            return

        with self._lock:
            if len(values) < BARS:
                values.extend([0] * (BARS - len(values)))
            self._bars = values[:BARS]
            self._last_update = time.time()
            self._last_error = ""
            if any(self._bars):
                self._zero_since = None
            elif self._zero_since is None:
                self._zero_since = self._last_update

    def _set_error(self, message: str) -> None:
        with self._lock:
            self._last_error = message


STATE = SpectrumState()


class Handler(BaseHTTPRequestHandler):
    def do_GET(self) -> None:  # noqa: N802
        if self.path in ("/", "/health"):
            self._send_json(
                {
                    "ok": True,
                    "service": "mpd-spectrum",
                    "bars": BARS,
                    "input_method": INPUT_METHOD,
                    "input_source": INPUT_SOURCE,
                    "user_config": str(resolve_user_config_path(USER_CONFIG_PATH) or USER_CONFIG_PATH),
                    "using_user_config": USE_USER_CONFIG and resolve_user_config_path(USER_CONFIG_PATH) is not None,
                }
            )
            return

        if self.path.startswith("/bars"):
            self._send_json(STATE.snapshot())
            return

        self.send_error(HTTPStatus.NOT_FOUND, "Not found")

    def log_message(self, fmt: str, *args: object) -> None:
        return

    def _send_json(self, payload: dict) -> None:
        body = json.dumps(payload).encode("utf-8")
        self.send_response(HTTPStatus.OK)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Cache-Control", "no-store, no-cache, must-revalidate")
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)


def main() -> None:
    STATE.start()
    server = ThreadingHTTPServer((HOST, PORT), Handler)

    def shutdown(*_: object) -> None:
        STATE.stop()
        # shutdown() blocca se chiamato dal thread che esegue serve_forever()
        threading.Thread(target=server.shutdown, daemon=True).start()

    signal.signal(signal.SIGINT, shutdown)
    signal.signal(signal.SIGTERM, shutdown)
    print(f"mpd-spectrum listening on http://{HOST}:{PORT}/bars")
    server.serve_forever()


if __name__ == "__main__":
    main()
