# Projektstand

Kurzfassung für alle, die hier später weitermachen — auch für eine neue
Claude-Session, die dieses Repository zum ersten Mal sieht. Die vollständige
Beschreibung steht in der [README](README.md); hier steht nur, was fertig ist,
was geprüft wurde und was noch offen ist.

Stand: Plugin und Theme in Version 1.1.0.

## Was fertig ist

* **Plugin „Norwegen Reise"** — Beitragstyp Reisetag mit Eckdaten, GPX- und
  GeoJSON-Import, mehreren Aufzeichnungen pro Tag (nach Aufnahmezeit sortiert),
  Fähnchen-Editor mit Karten-Picker, Einstellungsseite, REST-Schnittstelle.
* **Theme „Nordlys"** — Startseite mit scrollgesteuerter Karte, Tagesartikel
  mit Etappenkarte und Lightbox, Übersicht aller Tage, dunkles Aurora-Design.
* **Kartenstile** — Dunkel, Hell, Satellit, Topografisch, im Frontend
  umschaltbar; Auswahl im Backend konfigurierbar.
* **Testumgebung** — `docker compose up -d && ./tools/setup.sh`, inklusive fünf
  Demo-Reisetagen von Oslo bis Bergen.

## Was geprüft wurde

* Geo-Mathematik: Distanzen, Vereinfachung (verlustfrei), Position auf der
  Route, Fehlerfälle beim Import — als PHP-Einzeltests.
* Scroll-Fortschritt: gegen simuliertes Scrollen, Füllstand und Laufpunkt
  treffen die erwarteten Werte.
* Im echten Browser (Chromium): Fortschritt und Kilometerzähler über alle Tage,
  Ein- und Ausblenden der Fähnchen (logisch **und** anhand der tatsächlich
  gezeichneten Pixel), Popup, Kameraschalter, Einzeltag, Archiv, 404, mobile
  Ansicht, Kartenstil-Umschalter.
* Ein echter Upload zweier GPX-Dateien über das Backend-Formular, bewusst in
  falscher Reihenfolge abgeschickt und korrekt einsortiert.

## Was **nicht** geprüft ist

Ehrliche Liste — hier lauern die Überraschungen:

* **`docker-compose.yml`** wurde nie von mir ausgeführt (keine Docker-Umgebung
  zur Hand), nur die YAML-Syntax geprüft. Läuft aber beim Autor lokal.
* **Block-Editor und Mediathek**: Die Bildauswahl für Fähnchen (`wp.media`) und
  der Editor als Ganzes konnten nicht getestet werden, weil dem verwendeten
  WordPress-Build viele Core-Assets fehlten.
* **Echte Kartenkacheln** aller vier Anbieter — im Test durch farbige
  Platzhalter ersetzt.
* **Geplante Gesamtroute** (Upload in den Einstellungen): eingebaut, nie
  durchgetestet.
* **3D-Gelände** über MapTiler: eingebaut, nie durchgetestet.
* **Fotos und Videos im Artikel** samt Lightbox — die Demo-Tage haben keine
  Medien.
* Nur Chromium getestet, kein Safari oder Firefox, keine echten Touch-Gesten.

## Nächste Schritte

1. **Beispielbilder in die Demo-Tage** — ohne Bilder lässt sich das Design
   schlecht beurteilen, die Karten wirken kahl.
2. **Probetag mit echten Daten** vor der Reise: unterwegs eine GPX aufzeichnen,
   abends Artikel schreiben, echte Fotos einbinden, hochladen. Deckt fast alle
   offenen Punkte oben ab.
3. **Auf das echte Hosting bringen** — dort erst zeigen sich die echten Kacheln
   und die Upload-Grenzen des Anbieters.
4. Optional: privater Modus, solange die Reise läuft.

## Hinweise zur Umgebung

* Kein Build-Schritt, kein npm. Die Dateien laufen so, wie sie hier liegen.
* MapLibre GL (BSD-3) liegt lokal unter
  `wp-content/plugins/norwegen-reise/assets/vendor/maplibre-gl/`; es wird nichts
  von einem CDN geladen.
* Die Kartenkacheln kommen von Dritten (CARTO, Esri, OpenTopoMap) und gehören
  in die Datenschutzerklärung.
* Das Reise-Datenpaket liegt in einem Transient. Der Schlüssel enthält die
  Plugin-Version, damit ein Update den Zwischenspeicher automatisch verwirft.
