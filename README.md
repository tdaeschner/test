# Art-Net DMX Controller

Webbasierte Steuerung von DMX-Lampen über das Art-Net-Protokoll. Die Anwendung ist für lokale Netzwerke gedacht und bildet den Grundstein für zukünftige Funktionen wie Cues, Szenen und Spezialeffekte.

## Funktionsumfang

- Live-Steuerung einzelner DMX-Kanäle (0–255)
- WebSocket- und REST-Schnittstellen für externe Integrationen
- Sofort-Blackout aller Kanäle
- Art-Net-Ausgabe (UDP) mit konfigurierbarem Universum und Broadcast-IP
- Responsive Weboberfläche mit frei wählbarem Kanalbereich

## Voraussetzungen

- Node.js ≥ 18
- Zugriff auf das lokale Netzwerk, in dem die Art-Net-fähigen Fixtures erreichbar sind

## Installation

```bash
npm install
```

## Konfiguration

Optionale Umgebung variablen:

| Variable              | Standard          | Beschreibung                                      |
| --------------------- | ----------------- | ------------------------------------------------- |
| `PORT`                | `3000`            | HTTP-Port der Web-App                             |
| `ARTNET_TARGET_IP`    | `255.255.255.255` | Zieladresse für Art-Net-Pakete (z. B. Broadcast)  |
| `ARTNET_PORT`         | `6454`            | UDP-Port für Art-Net                              |
| `ARTNET_UNIVERSE`     | `0`               | Art-Net-Universum                                 |
| `ARTNET_CHANNELS`     | `512`             | Anzahl aktiver DMX-Kanäle                         |

Beispiel `.env` (optional):

```bash
PORT=4000
ARTNET_TARGET_IP=192.168.0.255
ARTNET_UNIVERSE=1
```

## Entwicklung & Start

```bash
# Entwicklung mit automatischen Neustarts
npm run dev

# Produktion / manueller Start
npm run start
```

Die Anwendung stellt die Weboberfläche unter `http://localhost:<PORT>` bereit und akzeptiert WebSocket-Verbindungen unter derselben URL.

## REST-API (Auszug)

- `GET /api/state` – Aktueller Status (Konfiguration & Kanalwerte)
- `PUT /api/channels/:channel` – Einzelnen Kanal setzen (`{ "value": 0-255 }`)
- `POST /api/channels/bulk` – Mehrere Kanäle in einem Request setzen (`{ "updates": [{ "channel": 1, "value": 255 }] }`)
- `POST /api/blackout` – Alle Kanäle auf 0 setzen

WebSocket-Nachrichten nutzen dasselbe Payload-Schema (`type: "setChannel"`, `"setChannels"`, `"blackout"`).

## Roadmap

- Verwaltung von Cues & Szenen mit Übergangszeiten
- Triggerbare Spezialeffekte (Strobe, Farbverläufe, Chaser)
- Mehrere Universen & Fixtures mit Alias-Namen
- Benutzerverwaltung und Zugriffssicherung

Beiträge und Ideen sind willkommen!
