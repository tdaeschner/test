#!/usr/bin/env python3
"""VISCA-over-IP-Proxy für sanftere Joystick-Fahrten (z. B. OBSBOT Tail 2).

    Joystick ──UDP──> dieses Skript (Laptop / Raspberry Pi) ──UDP──> Kamera

- Schwenk/Neigung: Geschwindigkeit wird über eine Kurve gedämpft, gedeckelt
  und mit einer Rampe weich angefahren und abgebremst.
- Zoom: Geschwindigkeit wird gedeckelt.
- Alles andere (Presets, Fokus, Abfragen ...) geht unverändert durch.

Im Joystick als Kamera-IP die IP des Rechners eintragen, auf dem das Skript läuft.

Beispiele:
    # Erst prüfen, wie langsam die Kamera überhaupt kann:
    python3 visca_smoother.py --camera 192.168.1.50 --test

    # Proxy starten:
    python3 visca_smoother.py --camera 192.168.1.50 --pan-max 6 --zoom-max 1 -v
"""

import argparse
import socket
import struct
import threading
import time
from collections import deque

# VISCA-over-IP Payload-Typen (Sony-Header)
CMD = 0x0100
INQ = 0x0110
REPLY = 0x0111
CTRL = 0x0200
CTRL_REPLY = 0x0201

ACK = bytes([0x90, 0x41, 0xFF])
DONE = bytes([0x90, 0x51, 0xFF])

IN_MAX = 0x18  # höchste Geschwindigkeit, die ein Joystick üblicherweise sendet
TICK = 0.05    # Rampen-Takt in Sekunden


def hexs(b):
    return " ".join(f"{x:02X}" for x in b)


def unpack(data):
    """Liefert (framed, payload_type, seq, payload)."""
    if len(data) >= 8 and data[0] in (0x01, 0x02):
        ptype, length, seq = struct.unpack(">HHI", data[:8])
        return True, ptype, seq, data[8:8 + length]
    ptype = INQ if len(data) > 1 and data[1] == 0x09 else CMD
    return False, ptype, 0, data


def is_pan_tilt_drive(p):
    return len(p) == 9 and p[1:4] == b"\x01\x06\x01"


def is_zoom(p):
    return len(p) == 6 and p[1:4] == b"\x01\x04\x07"


def drive_cmd(pan, tilt, pan_max, tilt_max):
    def axis(v, cap, neg_dir, pos_dir):
        s = round(abs(v))
        if s < 1:
            return 1, 0x03  # Achse steht
        return min(s, cap), (pos_dir if v > 0 else neg_dir)

    pv, pd = axis(pan, pan_max, 0x01, 0x02)    # 01 links, 02 rechts
    tv, td = axis(tilt, tilt_max, 0x02, 0x01)  # 01 hoch, 02 runter
    return bytes([0x81, 0x01, 0x06, 0x01, pv, tv, pd, td, 0xFF])


class Smoother:
    def __init__(self, args):
        self.a = args
        self.cam_addr = (args.camera, args.camera_port)
        self.cam = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        self.joy = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        self.lock = threading.RLock()
        self.seq = 0
        self.pending_inq = deque(maxlen=16)
        self.target = [0.0, 0.0]   # Ziel-Geschwindigkeit pan/tilt (mit Vorzeichen)
        self.current = [0.0, 0.0]  # aktuelle Geschwindigkeit pan/tilt
        self.sent = self.stop_cmd()

    def log(self, *msg):
        if self.a.verbose:
            print(time.strftime("%H:%M:%S"), *msg, flush=True)

    def stop_cmd(self):
        return drive_cmd(0, 0, self.a.pan_max, self.a.tilt_max)

    # ---- Kamera ---------------------------------------------------------
    def send_cam(self, payload, ptype=CMD):
        with self.lock:
            if self.a.camera_framing == "sony":
                self.seq = (self.seq + 1) & 0xFFFFFFFF
                data = struct.pack(">HHI", ptype, len(payload), self.seq) + payload
            else:
                data = payload
            self.cam.sendto(data, self.cam_addr)
        self.log("-> Kamera ", hexs(payload))

    def camera_loop(self):
        while True:
            data, _ = self.cam.recvfrom(2048)
            _, ptype, _, p = unpack(data)
            self.log("<- Kamera ", hexs(p))
            if ptype == CTRL_REPLY or len(p) < 3:
                continue
            # Nur Antworten auf Abfragen (90 50 ...Daten... FF) an den Joystick
            # zurückgeben. ACK/Completion bekommt der Joystick schon vom Proxy.
            if p[1] == 0x50 and len(p) > 3:
                with self.lock:
                    target = self.pending_inq.popleft() if self.pending_inq else None
                if target:
                    self.reply_joy(*target, p)

    # ---- Joystick -------------------------------------------------------
    def reply_joy(self, addr, seq, framed, payload, ptype=REPLY):
        if framed:
            data = struct.pack(">HHI", ptype, len(payload), seq) + payload
        else:
            data = payload
        self.joy.sendto(data, addr)

    def joystick_loop(self):
        self.joy.bind(("0.0.0.0", self.a.listen_port))
        print(f"Warte auf Joystick an UDP-Port {self.a.listen_port}, "
              f"Kamera {self.cam_addr[0]}:{self.cam_addr[1]}", flush=True)
        while True:
            data, addr = self.joy.recvfrom(2048)
            self.on_joystick(data, addr)

    def on_joystick(self, data, addr):
        framed, ptype, seq, p = unpack(data)
        self.log("<- Joystick", hexs(p))

        if ptype == CTRL:  # z. B. Sequenz-Reset: nur bestätigen
            self.reply_joy(addr, seq, framed, b"\x01", CTRL_REPLY)
            return
        if ptype == INQ or (len(p) > 1 and p[1] == 0x09):
            with self.lock:
                self.pending_inq.append((addr, seq, framed))
            self.send_cam(p, INQ)
            return

        self.reply_joy(addr, seq, framed, ACK)
        self.reply_joy(addr, seq, framed, DONE)

        if is_pan_tilt_drive(p):
            self.set_target(p)
        elif is_zoom(p):
            self.send_cam(self.slow_zoom(p))
        else:
            if len(p) > 3 and (p[2] == 0x06 or (p[2] == 0x04 and p[3] == 0x3F)):
                self.reset_motion()  # Home/Preset/Absolutfahrt übernimmt
            self.send_cam(p)

    # ---- Schwenk / Neigung ----------------------------------------------
    def curve(self, v, cap):
        x = min(v, IN_MAX) / IN_MAX
        return max(1.0, cap * x ** self.a.expo)

    def set_target(self, p):
        vv, ww, pd, td = p[4], p[5], p[6], p[7]
        pan = {0x01: -1, 0x02: 1}.get(pd, 0) * self.curve(vv, self.a.pan_max)
        tilt = {0x01: 1, 0x02: -1}.get(td, 0) * self.curve(ww, self.a.tilt_max)
        with self.lock:
            self.target = [pan, tilt]

    def reset_motion(self):
        with self.lock:
            self.target = [0.0, 0.0]
            self.current = [0.0, 0.0]
            self.sent = self.stop_cmd()

    def tick(self, dt):
        with self.lock:
            for i, cap in enumerate((self.a.pan_max, self.a.tilt_max)):
                step = cap / self.a.ramp * dt if self.a.ramp > 0 else float("inf")
                diff = self.target[i] - self.current[i]
                self.current[i] += max(-step, min(step, diff))
            cmd = drive_cmd(*self.current, self.a.pan_max, self.a.tilt_max)
            if cmd != self.sent:
                self.send_cam(cmd)
                self.sent = cmd

    # ---- Zoom -----------------------------------------------------------
    def slow_zoom(self, p):
        z = p[4]
        if z in (0x02, 0x03):           # Standard-Zoom tele/weit
            direction, speed = z, 7
        elif z >> 4 in (0x2, 0x3):      # variabler Zoom 2p/3p
            direction, speed = z >> 4, z & 0x0F
        else:                           # Stopp
            return p
        x = (speed + 1) / 8
        out = min(self.a.zoom_max, round(self.a.zoom_max * x ** self.a.expo))
        return bytes([p[0], 0x01, 0x04, 0x07, (direction << 4) | out, 0xFF])

    # ---- Ablauf ---------------------------------------------------------
    def reset_camera_seq(self):
        if self.a.camera_framing == "sony":
            self.send_cam(b"\x01", CTRL)
            with self.lock:
                self.seq = 0

    def test(self):
        stop = self.stop_cmd()
        zoom_stop = bytes([0x81, 0x01, 0x04, 0x07, 0x00, 0xFF])
        steps = [
            ("Schwenk rechts, langsamste Stufe (1)", drive_cmd(1, 0, 1, 1), stop, 3),
            ("Schwenk links, langsamste Stufe (1)", drive_cmd(-1, 0, 1, 1), stop, 3),
            ("Zoom tele, langsamste Stufe (0)", bytes([0x81, 0x01, 0x04, 0x07, 0x20, 0xFF]), zoom_stop, 2),
            ("Zoom weit, langsamste Stufe (0)", bytes([0x81, 0x01, 0x04, 0x07, 0x30, 0xFF]), zoom_stop, 2),
        ]
        for name, start, end, secs in steps:
            print(f"{name} für {secs} s ...", flush=True)
            self.send_cam(start)
            time.sleep(secs)
            self.send_cam(end)
            time.sleep(1)
        print("Fertig. Ist das langsam genug? Wenn nicht, kann der Proxy die "
              "Kamera nicht weiter abbremsen.")

    def run(self):
        threading.Thread(target=self.camera_loop, daemon=True).start()
        self.reset_camera_seq()
        if self.a.test:
            self.test()
            return
        threading.Thread(target=self.joystick_loop, daemon=True).start()
        last = time.monotonic()
        while True:
            time.sleep(TICK)
            now = time.monotonic()
            self.tick(now - last)
            last = now


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--camera", required=True, help="IP-Adresse der Kamera")
    ap.add_argument("--camera-port", type=int, default=52381,
                    help="VISCA-Port der Kamera (Standard: 52381)")
    ap.add_argument("--camera-framing", choices=["sony", "raw"], default="sony",
                    help="sony = VISCA over IP mit Header, raw = reines VISCA")
    ap.add_argument("--listen-port", type=int, default=52381,
                    help="UDP-Port, an den der Joystick sendet (Standard: 52381)")
    ap.add_argument("--pan-max", type=int, default=6,
                    help="höchste Schwenkstufe an die Kamera (1-24, Standard: 6)")
    ap.add_argument("--tilt-max", type=int, default=5,
                    help="höchste Neigestufe an die Kamera (1-20, Standard: 5)")
    ap.add_argument("--zoom-max", type=int, default=1,
                    help="höchste Zoomstufe an die Kamera (0-7, Standard: 1)")
    ap.add_argument("--expo", type=float, default=2.0,
                    help="Kurve: 1 = linear, höher = feiner bei kleinen Ausschlägen")
    ap.add_argument("--ramp", type=float, default=0.8,
                    help="Sekunden von Stillstand bis Höchststufe (0 = keine Rampe)")
    ap.add_argument("--test", action="store_true",
                    help="nur langsamste Kamerafahrten testen, kein Proxy")
    ap.add_argument("-v", "--verbose", action="store_true",
                    help="alle VISCA-Befehle anzeigen")
    args = ap.parse_args()
    try:
        Smoother(args).run()
    except KeyboardInterrupt:
        pass


if __name__ == "__main__":
    main()
