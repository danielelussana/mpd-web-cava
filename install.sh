#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────
#  mpd-web-cava — installer
#
#  Installa cava, distribuisce il bridge HTTP mpd-spectrum e il client web
#  mpd-client.php, e verifica/propone le righe mancanti in mpd.conf.
#
#  Pensato per Debian / Raspberry Pi OS (apt). Va eseguito come root:
#      sudo bash install.sh
#
#  Variabili sovrascrivibili (env):
#    WEBROOT        Dove copiare public/mpd-client.php   (default: /var/www/html)
#    APP_DIR         Dove copiare mpd-spectrum.py          (default: /opt/mpd-web)
#    MPD_CONF        File di configurazione MPD            (default: /etc/mpd.conf)
#    FIFO_PATH       FIFO condivisa tra MPD e cava          (default: /tmp/mpd-cava.fifo)
#    SPECTRUM_PORT   Porta HTTP del bridge cava             (default: 44883)
#    SERVICE_USER    Utente con cui gira il servizio systemd (default: utente che ha lanciato sudo, o "mpd")
# ─────────────────────────────────────────────────────────────────────────
set -Eeuo pipefail

WEBROOT="${WEBROOT:-/var/www/html}"
APP_DIR="${APP_DIR:-/opt/mpd-web}"
MPD_CONF="${MPD_CONF:-/etc/mpd.conf}"
FIFO_PATH="${FIFO_PATH:-/tmp/mpd-cava.fifo}"
SPECTRUM_PORT="${SPECTRUM_PORT:-44883}"
SERVICE_USER="${SERVICE_USER:-${SUDO_USER:-mpd}}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

log()  { printf '\033[1;36m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m!!\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31mERRORE:\033[0m %s\n' "$*" >&2; exit 1; }

confirm() {
    # confirm "domanda" -> 0 se l'utente conferma, 1 altrimenti
    local reply
    read -r -p "$1 [y/N] " reply </dev/tty || return 1
    [[ "$reply" =~ ^[Yy]$ ]]
}

[[ $EUID -eq 0 ]] || die "esegui questo script come root (sudo bash install.sh)"
command -v apt-get >/dev/null 2>&1 || die "questo installer richiede una distro basata su apt (Debian / Raspberry Pi OS)"

# ── 1. Pacchetti di sistema ─────────────────────────────────────────────
log "Controllo dipendenze di sistema (cava, mpc, python3)…"

if command -v cava >/dev/null 2>&1; then
    installed_via_apt="no"
    dpkg -s cava >/dev/null 2>&1 && installed_via_apt="yes"
    ver="$(cava --version 2>&1 | head -n1 || true)"
    if [[ "$installed_via_apt" == "yes" ]]; then
        log "cava è già installato via apt ($ver) — nessuna azione necessaria."
    else
        warn "cava è già presente ma NON risulta installato via apt ($ver)."
        warn "Probabilmente è stato compilato manualmente: questo script non lo tocca per non rompere la tua build."
    fi
else
    log "cava non trovato: lo installo via apt (pacchetto Debian, stessa modalità della macchina di riferimento)…"
    apt-get update -qq
    apt-get install -y cava
fi

for pkg_bin in "mpc:mpc" "python3:python3"; do
    bin="${pkg_bin%%:*}"; pkg="${pkg_bin##*:}"
    if command -v "$bin" >/dev/null 2>&1; then
        log "$bin già presente."
    else
        log "$bin non trovato: installo il pacchetto $pkg…"
        apt-get install -y "$pkg"
    fi
done

# ── 2. File applicativi ─────────────────────────────────────────────────
log "Distribuzione dei file applicativi…"

mkdir -p "$APP_DIR" "$WEBROOT"
install -m 644 "$SCRIPT_DIR/public/mpd-client.php" "$WEBROOT/mpd-client.php"
install -m 755 "$SCRIPT_DIR/mpd-spectrum.py" "$APP_DIR/mpd-spectrum.py"
log "Copiato mpd-client.php in $WEBROOT/"
log "Copiato mpd-spectrum.py in $APP_DIR/"

if ! id -u "$SERVICE_USER" >/dev/null 2>&1; then
    warn "L'utente '$SERVICE_USER' non esiste, il servizio girerà come 'mpd'."
    SERVICE_USER="mpd"
fi

unit_src="$SCRIPT_DIR/systemd/mpd-spectrum.service"
unit_dst="/etc/systemd/system/mpd-spectrum.service"
sed \
    -e "s|^User=.*|User=$SERVICE_USER|" \
    -e "s|^Group=.*|Group=$SERVICE_USER|" \
    -e "s|^WorkingDirectory=.*|WorkingDirectory=$APP_DIR|" \
    -e "s|^Environment=MPD_SPECTRUM_PORT=.*|Environment=MPD_SPECTRUM_PORT=$SPECTRUM_PORT|" \
    -e "s|^Environment=MPD_SPECTRUM_SOURCE=.*|Environment=MPD_SPECTRUM_SOURCE=$FIFO_PATH|" \
    -e "s|^ExecStart=.*|ExecStart=/usr/bin/python3 $APP_DIR/mpd-spectrum.py|" \
    "$unit_src" > "$unit_dst"
log "Installata unit systemd in $unit_dst (utente: $SERVICE_USER, porta: $SPECTRUM_PORT, fifo: $FIFO_PATH)"

systemctl daemon-reload
systemctl enable --now mpd-spectrum.service
log "Servizio mpd-spectrum avviato. Stato:"
systemctl --no-pager --lines=5 status mpd-spectrum.service || true

# ── 3. Verifica audio_output in mpd.conf ────────────────────────────────
log "Verifica configurazione MPD ($MPD_CONF)…"

if [[ ! -f "$MPD_CONF" ]]; then
    warn "$MPD_CONF non trovato: salto il controllo automatico."
    warn "Assicurati manualmente che MPD abbia un output 'fifo' verso $FIFO_PATH."
else
    has_fifo_output="no"
    if grep -Pzoq "(?s)audio_output\s*\{[^}]*type\s+\"fifo\"[^}]*path\s+\"$(printf '%s' "$FIFO_PATH" | sed 's/[.[\*^$/]/\\&/g')\"" "$MPD_CONF" 2>/dev/null; then
        has_fifo_output="yes"
    fi

    if [[ "$has_fifo_output" == "yes" ]]; then
        log "Output fifo verso $FIFO_PATH già presente in mpd.conf."
    else
        cat <<EOF

Nello spettrogramma cava legge l'audio da una FIFO che MPD deve scrivere.
Non ho trovato in $MPD_CONF un output di questo tipo. Ti propongo di aggiungere:

    audio_output {
        type        "fifo"
        name        "cava fifo"
        path        "$FIFO_PATH"
        format      "44100:16:2"
    }

EOF
        if confirm "Vuoi che aggiunga questo blocco a $MPD_CONF ora (verrà creato un backup)?"; then
            backup="${MPD_CONF}.bak.$(date +%Y%m%d-%H%M%S)"
            cp -a "$MPD_CONF" "$backup"
            {
                echo ""
                echo "audio_output {"
                echo "    type        \"fifo\""
                echo "    name        \"cava fifo\""
                echo "    path        \"$FIFO_PATH\""
                echo "    format      \"44100:16:2\""
                echo "}"
            } >> "$MPD_CONF"
            log "Aggiunto. Backup salvato in $backup."
            if confirm "Riavvio MPD ora per applicare la modifica? (interrompe la riproduzione in corso)"; then
                systemctl restart mpd
                log "MPD riavviato."
            else
                warn "Ricorda di eseguire 'sudo systemctl restart mpd' per applicare la modifica."
            fi
        else
            warn "Nessuna modifica applicata. Aggiungi manualmente il blocco sopra a $MPD_CONF e riavvia MPD."
        fi
    fi

    audio_output_count="$(grep -c '^\s*audio_output\s*{' "$MPD_CONF" || true)"
    if [[ "$audio_output_count" -lt 2 ]]; then
        cat <<EOF

Nota sui pulsanti USB/HDMI di mpd-client.php:
il codice (funzione switchAudioOutput in mpd-client.php) assume che il primo
audio_output nel tuo mpd.conf sia l'uscita "USB" e il secondo sia l'uscita
"HDMI" (id 1 e 2, nell'ordine in cui compaiono nel file). Al momento hai solo
$audio_output_count output configurati: se vuoi usare quei due pulsanti,
aggiungi due blocchi audio_output di tipo "alsa" — uno per ciascuna uscita —
PRIMA di quello fifo qui sopra, per esempio:

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

Usa 'aplay -L' per trovare i nomi corretti delle tue schede audio.
Questo script NON li aggiunge automaticamente perché dipendono dal tuo
hardware: modifica $MPD_CONF a mano se vuoi usare questa funzione, poi
riavvia MPD. Se l'ordine dei tuoi output differisce, aggiorna anche l'array
\$outputs dentro switchAudioOutput() in mpd-client.php.

EOF
    fi
fi

# ── 4. Riepilogo ─────────────────────────────────────────────────────────
cat <<EOF

──────────────────────────────────────────────────────────────
 Fatto.
   • Client web:     $WEBROOT/mpd-client.php
   • Bridge cava:     $APP_DIR/mpd-spectrum.py  (servizio: mpd-spectrum.service)
   • Bridge in ascolto su: http://127.0.0.1:$SPECTRUM_PORT/bars

 Serve ancora un web server con PHP (lighttpd+php-fpm, apache+mod_php,
 nginx+php-fpm, ecc.) che pubblichi $WEBROOT — questo script non lo installa.

 Variabili d'ambiente lette da mpd-client.php (se diverse dai default):
   MPD_HOST, MPD_PORT, MPD_MPC_BIN, MPD_SPECTRUM_URL, MPD_SPECTRUM_SCRIPT, MPD_PYTHON_BIN
──────────────────────────────────────────────────────────────
EOF
