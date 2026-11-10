# mpd-web-cava

*[English](#english) | [Italiano](#italiano)*

---

## English

A web client for [MPD](https://www.musicpd.org/) in a single PHP file (no external PHP dependencies), with a live spectrum analyser fed by [cava](https://github.com/karlstav/cava) through a small Python HTTP bridge.

The interface is in Italian and designed for both phone and desktop: queue/search/library/saved playlists in a resizable sidebar, always-visible playback controls, audio output switching (e.g. USB / HDMI on a Raspberry Pi) and a real-time spectrum.

### Components

| File | What it does |
|---|---|
| `public/mpd-client.php` | The whole web app: UI + JSON API (`?api=...`) talking to MPD via `mpc`/TCP socket. Audio output switching (USB/HDMI) is the `switchAudioOutput()` function inside this same file, not a separate script. |
| `mpd-spectrum.py` | Standalone HTTP bridge: runs `cava` reading a FIFO written by MPD, and exposes the bar amplitudes as JSON at `http://127.0.0.1:44883/bars`. `mpd-client.php` proxies this endpoint (`?api=spectrum`) and starts it itself if it is not already listening. |
| `install.sh` | Installs `cava`/`mpc`/`python3`, copies the files into place, and checks/proposes missing lines in `mpd.conf`. |

### Architecture

```
 MPD ──(audio_output type=fifo)──> /tmp/mpd-cava.fifo ──> cava ──> mpd-spectrum.py ──HTTP JSON──> mpd-client.php ──> browser
  ^                                                                         ^
  └── mpc / TCP socket (state, queue, playlists, library) ──────────────────┘
```

`mpd-client.php` never talks to `cava` directly: it only reads the JSON exposed by `mpd-spectrum.py` and forwards it to the browser via polling.

### Requirements

- A working MPD with an indexed music library.
- A web server with PHP 8.x support (tested with lighttpd + php-fpm; apache+mod_php or nginx+php-fpm are fine too; it is not included in this repo and must be installed and configured separately).
- `mpc`, `python3`, `cava` (the installer sets them up).
- Debian / Raspberry Pi OS (the installer uses `apt`).

### Quick install

```bash
git clone https://github.com/<your-user>/mpd-web-cava.git
cd mpd-web-cava
sudo bash install.sh
```

Environment variables accepted by `install.sh` (all optional):

| Variable | Default | Meaning |
|---|---|---|
| `WEBROOT` | `/var/www/html` | Where `mpd-client.php` is copied |
| `APP_DIR` | `/opt/mpd-web` | Where `mpd-spectrum.py` is copied |
| `MPD_CONF` | `/etc/mpd.conf` | MPD configuration file to check |
| `FIFO_PATH` | `/tmp/mpd-cava.fifo` | FIFO shared between MPD and cava |

Example with custom paths:

```bash
sudo WEBROOT=/opt/mpd-web/public APP_DIR=/opt/mpd-web bash install.sh
```

The script is idempotent: re-running it duplicates nothing, skips cava if already installed via apt, and does not touch `mpd.conf` without asking (with an automatic backup before any change).

### Required MPD configuration

The spectrum needs MPD to write audio to a FIFO that `cava` reads in real time. `install.sh` checks whether this block is already in `mpd.conf` and, if missing, proposes it before writing it:

```
audio_output {
    type        "fifo"
    name        "cava fifo"
    path        "/tmp/mpd-cava.fifo"
    format      "44100:16:2"
}
```

#### USB / HDMI buttons (optional)

`switchAudioOutput()` in `mpd-client.php` assumes that in your `mpd.conf` the **first** `audio_output` is the "USB" output and the **second** is the "HDMI" output (ids 1 and 2, in the order they appear in the file): this depends on your audio hardware and cannot be generated automatically. To use those two buttons, add two `alsa` `audio_output` blocks before the fifo one, for example:

```
audio_output {
    type        "alsa"
    name        "USB PCM"
    device      "plughw:CARD=<your-card>,DEV=0"
    mixer_type  "software"
    format      "44100:16:2"
    enabled     "yes"
}

audio_output {
    type        "alsa"
    name        "HDMI"
    device      "hdmi:CARD=<your-hdmi-card>,DEV=0"
    mixer_type  "software"
    format      "44100:16:2"
    enabled     "no"
}
```

Use `aplay -L` to find the right names of your cards. If the order of your outputs is different, also update the `$outputs` array inside `switchAudioOutput()` in `mpd-client.php`.

After any change to `mpd.conf`: `sudo systemctl restart mpd`.

### Variables read by mpd-client.php

| Variable | Default |
|---|---|
| `MPD_HOST` | `127.0.0.1` |
| `MPD_PORT` | `6600` |
| `MPD_MPC_BIN` | `/usr/bin/mpc` |
| `MPD_SPECTRUM_URL` | `http://127.0.0.1:44883/bars` |
| `MPD_SPECTRUM_SCRIPT` | `/opt/mpd-web/mpd-spectrum.py` (only used to auto-start the bridge if it does not respond) |
| `MPD_PYTHON_BIN` | `/usr/bin/python3` |

### Troubleshooting

- **Spectrum frozen / at zero**: check that the bridge is running (`curl http://127.0.0.1:44883/health`; `mpd-client.php` starts it on demand) and that the fifo output in `mpd.conf` is `enabled` (fifo outputs are always active by default in MPD, but check `mpc outputs` anyway).
- **Album cover does not appear**: check that MPD sees the file (`mpc --format %file% current`); it depends on the library's tags/artwork, not on this project.
- **USB/HDMI buttons do nothing / pick the wrong output**: see the section above about the order of the `audio_output` blocks.

### License

GPLv3, see [`LICENSE`](LICENSE).

`cava` (external dependency, not included in this repo) is by Karl Stavestrand, MIT license.

---

## Italiano

Client web per [MPD](https://www.musicpd.org/) in un unico file PHP (senza
dipendenze esterne lato PHP), con uno spettrogramma live alimentato da
[cava](https://github.com/karlstav/cava) tramite un piccolo bridge HTTP in
Python.

Interfaccia in italiano, pensata per essere usata da telefono e da desktop:
coda/ricerca/libreria/playlist salvate in una sidebar ridimensionabile,
controlli di riproduzione sempre visibili, cambio uscita audio (es. USB /
HDMI su Raspberry Pi) e spettrogramma in tempo reale.

### Componenti

| File | Cosa fa |
|---|---|
| `public/mpd-client.php` | L'intera web app: UI + API JSON (`?api=...`) che parla con MPD via `mpc`/socket TCP. Il cambio uscita audio (USB/HDMI) è la funzione `switchAudioOutput()` dentro questo stesso file — non è uno script separato. |
| `mpd-spectrum.py` | Bridge HTTP standalone: lancia `cava` leggendo una FIFO scritta da MPD, ed espone le ampiezze delle barre come JSON su `http://127.0.0.1:44883/bars`. `mpd-client.php` fa da proxy verso questo endpoint (`?api=spectrum`) e lo avvia da solo se non lo trova già in ascolto. |
| `install.sh` | Installa `cava`/`mpc`/`python3`, copia i file al loro posto, e verifica/propone le righe mancanti in `mpd.conf`. |

### Architettura

```
 MPD ──(audio_output type=fifo)──> /tmp/mpd-cava.fifo ──> cava ──> mpd-spectrum.py ──HTTP JSON──> mpd-client.php ──> browser
  ^                                                                         ^
  └── mpc / socket TCP (stato, coda, playlist, libreria) ───────────────────┘
```

`mpd-client.php` non parla mai direttamente con `cava`: legge solo il JSON
esposto da `mpd-spectrum.py` e lo inoltra al browser via polling.

### Requisiti

- MPD già funzionante, con una libreria musicale indicizzata.
- Un web server con supporto PHP 8.x (testato con lighttpd + php-fpm; va
  bene anche apache+mod_php o nginx+php-fpm — non è incluso in questo repo,
  va installato e configurato a parte).
- `mpc`, `python3`, `cava` (l'installer li mette a posto lui).
- Debian / Raspberry Pi OS (l'installer usa `apt`).

### Installazione rapida

```bash
git clone https://github.com/<tuo-utente>/mpd-web-cava.git
cd mpd-web-cava
sudo bash install.sh
```

Variabili d'ambiente accettate da `install.sh` (tutte opzionali):

| Variabile | Default | Significato |
|---|---|---|
| `WEBROOT` | `/var/www/html` | Dove viene copiato `mpd-client.php` |
| `APP_DIR` | `/opt/mpd-web` | Dove viene copiato `mpd-spectrum.py` |
| `MPD_CONF` | `/etc/mpd.conf` | File di configurazione MPD da controllare |
| `FIFO_PATH` | `/tmp/mpd-cava.fifo` | FIFO condivisa tra MPD e cava |

Esempio con percorsi personalizzati:

```bash
sudo WEBROOT=/opt/mpd-web/public APP_DIR=/opt/mpd-web bash install.sh
```

Lo script è idempotente: rilanciarlo non duplica nulla, salta cava se già
installato via apt, e non tocca `mpd.conf` senza chiedere conferma (con
backup automatico prima di ogni modifica).

### Configurazione MPD necessaria

Lo spettrogramma richiede che MPD scriva l'audio su una FIFO che `cava`
legge in tempo reale. `install.sh` controlla se questo blocco è già presente
in `mpd.conf` e, se manca, te lo propone prima di scriverlo:

```
audio_output {
    type        "fifo"
    name        "cava fifo"
    path        "/tmp/mpd-cava.fifo"
    format      "44100:16:2"
}
```

#### Pulsanti USB / HDMI (opzionale)

`switchAudioOutput()` in `mpd-client.php` assume che nel tuo `mpd.conf` il
**primo** `audio_output` sia l'uscita "USB" e il **secondo** sia l'uscita
"HDMI" (gli id 1 e 2, nell'ordine in cui compaiono nel file) — questo perché
dipendono dal tuo hardware audio e non possono essere generati
automaticamente. Se vuoi usare quei due pulsanti, aggiungi due blocchi
`audio_output` di tipo `alsa` prima di quello fifo, per esempio:

```
audio_output {
    type        "alsa"
    name        "USB PCM"
    device      "plughw:CARD=<la-tua-scheda>,DEV=0"
    mixer_type  "software"
    format      "44100:16:2"
    enabled     "yes"
}

audio_output {
    type        "alsa"
    name        "HDMI"
    device      "hdmi:CARD=<la-tua-scheda-hdmi>,DEV=0"
    mixer_type  "software"
    format      "44100:16:2"
    enabled     "no"
}
```

Usa `aplay -L` per trovare i nomi corretti delle tue schede. Se l'ordine dei
tuoi output è diverso, aggiorna anche l'array `$outputs` dentro
`switchAudioOutput()` in `mpd-client.php`.

Dopo qualsiasi modifica a `mpd.conf`: `sudo systemctl restart mpd`.

### Variabili lette da mpd-client.php

| Variabile | Default |
|---|---|
| `MPD_HOST` | `127.0.0.1` |
| `MPD_PORT` | `6600` |
| `MPD_MPC_BIN` | `/usr/bin/mpc` |
| `MPD_SPECTRUM_URL` | `http://127.0.0.1:44883/bars` |
| `MPD_SPECTRUM_SCRIPT` | `/opt/mpd-web/mpd-spectrum.py` (usato solo per auto-avviare il bridge se non risponde) |
| `MPD_PYTHON_BIN` | `/usr/bin/python3` |

### Troubleshooting

- **Spettrogramma fermo / a zero**: verifica che il bridge giri
  (`curl http://127.0.0.1:44883/health`; lo avvia `mpd-client.php` quando serve) e che l'output fifo in `mpd.conf` sia
  `enabled` (di default gli output fifo sono sempre attivi in MPD, ma
  controlla comunque `mpc outputs`).
- **Copertina album non appare**: verifica che MPD veda il file
  (`mpc --format %file% current`) — dipende dai tag/artwork della libreria,
  non da questo progetto.
- **I pulsanti USB/HDMI non fanno nulla / sbagliano uscita**: vedi la
  sezione sopra sull'ordine degli `audio_output`.

### Licenza

GPLv3 — vedi [`LICENSE`](LICENSE).

`cava` (dipendenza esterna, non inclusa in questo repo) è di Karl Stavestrand,
licenza MIT.
