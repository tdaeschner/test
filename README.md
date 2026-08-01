# Norwegen — Travel Blog

Ein WordPress-Reiseblog für eine 17-tägige Norwegen-Reise. Kernstück ist eine
große, frei bewegliche Karte: Beim Scrollen zeichnet sich die Route Tag für Tag
weiter, an besonderen Stellen tauchen Fähnchen auf, und über jede Karte kommt
man direkt in den Artikel des jeweiligen Tages.

Das Repository enthält zwei Teile:

| Ordner | Was es ist |
| --- | --- |
| `wp-content/plugins/norwegen-reise/` | Die Daten: Reisetage, GPX-Tracks, Fähnchen, Einstellungen, REST-Schnittstelle |
| `wp-content/themes/nordlys/` | Das Aussehen: Startseite mit Scroll-Karte, Tagesartikel, Übersicht |

Die Trennung ist Absicht: Die Reisedaten bleiben erhalten, auch wenn das Theme
später gewechselt oder überarbeitet wird.

## Installation

1. Beide Ordner in die WordPress-Installation kopieren (Verzeichnisstruktur
   entspricht bereits `wp-content/`). Alternativ als ZIP hochladen.
2. Im Backend unter **Plugins** das Plugin *Norwegen Reise* aktivieren.
3. Unter **Design → Themes** das Theme *Nordlys* aktivieren.
4. **Einstellungen → Permalinks** einmal speichern, damit die Adressen der
   Reisetage (`/reisetag/…`) greifen.
5. **Einstellungen → Lesen**: „Deine Homepage zeigt“ auf *Eine statische Seite*
   stellen und eine leere Seite als Homepage wählen — oder auf „Deine letzten
   Beiträge“ stehen lassen. Die Startseiten-Vorlage mit der Karte greift in
   beiden Fällen.
6. Unter **Norwegen → Einstellungen** Titel, Untertitel, Abreisedatum und die
   Zahl der geplanten Tage (17) eintragen.

Es gibt keinen Build-Schritt. Kein npm, kein Webpack — die Dateien laufen so,
wie sie im Repository liegen. MapLibre GL (BSD-3) liegt fertig unter
`wp-content/plugins/norwegen-reise/assets/vendor/maplibre-gl/`; es wird nichts
von einem CDN nachgeladen.

## Lokal ausprobieren

Am schnellsten mit Docker — WordPress, Datenbank, Plugin, Theme und fünf
Beispiel-Reisetage in zwei Befehlen:

```bash
docker compose up -d
./tools/setup.sh
```

Danach läuft die Seite auf <http://localhost:8080>, das Backend unter
`/wp-admin` mit `norwegen` / `norwegen`.

Die Demo-Tage führen von Oslo über Geiranger und Trollstigen nach Bergen,
mit Tracks und zwölf Fähnchen — genug, damit sich die Route beim Scrollen
sichtbar füllt. Wieder entfernen:

```bash
docker compose run --rm wpcli eval-file tools/demo-data.php -- --delete
```

Die Demo-Daten lassen sich auch ohne WP-CLI erzeugen, etwa auf einer
Testinstallation beim Hoster: `tools/demo-data.php` nach dem Laden von
`wp-load.php` einbinden.

Worauf beim Testen zu achten ist:

* Beim Scrollen füllt sich die Linie, der Punkt an der Spitze wandert mit,
  und die Anzeige unten zählt die Kilometer hoch.
* Fähnchen tauchen erst auf, wenn die Route sie erreicht — und verschwinden
  wieder, wenn man zurückscrollt.
* Die Karte selbst anfassen: der Schalter springt auf „Freie Sicht“, die
  Kamera hört auf zu führen, bis man ihn wieder aktiviert.
* Ein Klick auf ein Fähnchen öffnet das Popup mit Link zum Tagesartikel.

## Der Abend-Workflow auf der Reise

Pro Tag ein Beitrag unter **Norwegen → Neuer Tag**:

1. **Titel** schreiben und den Artikel im Editor verfassen — hier kommen die
   Texte hin, die ihr abends mit KI-Hilfe erzeugt. Fotos und Videos einfach als
   Bild-, Galerie-, Video- oder Embed-Block einfügen; Galerien bekommen im
   Frontend automatisch eine Lightbox.
2. Rechts unter **Tages-Eckdaten**: Tagesnummer (bestimmt die Reihenfolge auf
   der Karte), Datum, Etappe („Geiranger → Trollstigen“) und optional Wetter.
   Die Distanz darf leer bleiben — sie wird aus dem Track berechnet.
3. Unter **Route des Tages** die GPX-Dateien hochladen (Komoot, Strava, Garmin,
   Handy-App, Apple Watch über HealthFit — GPX und GeoJSON werden gelesen).
   Jede Aufzeichnung wird beim Speichern eingelesen und automatisch von
   mehreren tausend auf ~1.200 Punkte reduziert, ohne dass sich die Linie
   sichtbar verändert. **Mehrere Dateien pro Tag sind möglich** — siehe unten.
4. Unter **Fähnchen & Highlights** die Momente des Tages setzen: Titel, ein bis
   zwei Sätze, Symbol (Aussicht, Wanderung, Wasserfall, Tiere, Essen,
   Übernachtung, Foto-Spot, Highlight) und optional ein Bild fürs Popup.
   Die Position lässt sich direkt in der Karte anklicken oder per Marker
   verschieben. Wegpunkte aus der GPX-Datei werden auf Wunsch gleich als
   Fähnchen übernommen.
5. **Beitragsbild** setzen (das große Bild auf der Karte und im Artikel) und
   veröffentlichen.

Sobald der Tag veröffentlicht ist, wächst die Route auf der Startseite
automatisch weiter. Entwürfe erscheinen nicht auf der Karte — ihr könnt also
vorschreiben und später freigeben.

### Mehrere Aufzeichnungen an einem Tag

Ein Reisetag besteht selten aus einer einzigen Datei: die Tagesstrecke kommt
vom Handy, die Wanderung von der Uhr, dazwischen war das Tracking vielleicht
kurz aus. Deshalb nimmt ein Tag beliebig viele Dateien.

* **Alle auf einmal auswählen** oder nacheinander hochladen — neue Dateien
  kommen zu den vorhandenen dazu, sie ersetzen sie nicht.
* **Sortiert wird nach Aufnahmezeit.** Der Zeitstempel steckt in jeder GPX aus
  einer Tracking-App. Es ist also egal, in welcher Reihenfolge du hochlädst;
  die Wanderung von 13:30 landet automatisch hinter der Fahrt von 08:00.
* **Dateien ohne Zeitstempel** — etwa in gpx.studio nachgezeichnete Strecken —
  landen hinten. Über die Nummern in der Tabelle lässt sich die Reihenfolge
  jederzeit von Hand korrigieren; sobald du eine Nummer änderst, gilt sie.
* **Jede Aufzeichnung hat in der Vorschaukarte ihre eigene Farbe**, passend zum
  Farbpunkt in der Tabelle. Einzelne Aufzeichnungen lassen sich per Häkchen
  wieder entfernen.
* **Lücken zwischen zwei Aufzeichnungen** werden auf der Karte mit einer
  geraden Linie überbrückt. Das sieht man kaum, wenn die Wanderung dort
  beginnt, wo das Auto stand. Die angezeigte **Distanz zählt diese Lücken
  nicht mit** — sie ist die Summe der tatsächlich aufgezeichneten Strecken.

Enthält eine einzelne GeoJSON-Datei mehrere Linien, wird jede davon zu einer
eigenen Aufzeichnung.

### Artikel per KI/App anlegen

Der Beitragstyp ist voll REST-fähig (`show_in_rest`), Basis-Route:

```
/wp-json/wp/v2/reisetage
```

Damit lassen sich Tage auch aus der WordPress-App, aus einem Automatisierungs-
Tool oder direkt aus einem KI-Workflow anlegen — inklusive der Meta-Felder
`_nb_day_number`, `_nb_date`, `_nb_place`, `_nb_distance_km` und `_nb_weather`.
Track und Fähnchen setzt man anschließend bequem im Backend, weil dort die
Karte zum Klicken ist.

## Die Karte

* **Frei beweglich**: ziehen, zoomen, drehen, neigen — dazu Vollbild und
  Maßstab. Auf dem Handy braucht das Verschieben zwei Finger, damit das
  Scrollen der Seite nicht blockiert wird.
* **Scroll-Fortschritt**: Die Linie füllt sich proportional zur tatsächlich
  gefahrenen Distanz. Ein Tag mit 400 km nimmt beim Scrollen entsprechend mehr
  Raum ein als einer mit 40 km.
* **Kamera**: folgt standardmäßig dem aktuellen Tag. Sobald man selbst die
  Karte anfasst, hält sie sich zurück — der Schalter unten rechts in der
  Anzeige holt sie zurück.
* **Geplante Route**: Unter **Norwegen → Einstellungen** lässt sich optional die
  komplette Rundreise als GPX hinterlegen. Sie liegt als feine gestrichelte
  Linie unter der gefahrenen Route — so sieht man von Anfang an, wohin es noch
  geht, und die gefüllte Linie wächst sichtbar darauf zu.

### Kartenstile

Vier Stile stehen ohne Schlüssel zur Verfügung: **Dunkel** (CARTO Dark Matter,
Standard und auf das Design abgestimmt), **Hell** (CARTO Positron),
**Satellit** (Esri World Imagery) und **Topografisch** (OpenTopoMap).

Optional lässt sich eine eigene Style-URL eintragen (z. B. ein Vektorstil von
MapTiler). Mit hinterlegtem MapTiler-Schlüssel kann zusätzlich **3D-Gelände**
aktiviert werden — für Norwegen durchaus einen Blick wert.

> **Datenschutz:** Die Kartenkacheln werden beim Betrachter direkt vom jeweiligen
> Anbieter geladen, dabei wird dessen IP-Adresse übertragen. Das gehört in die
> Datenschutzerklärung. Alles andere (Karten-Bibliothek, Schriften, Skripte)
> kommt vom eigenen Server; es werden keine Google Fonts oder CDNs eingebunden.

## Schnittstellen

| Route | Inhalt |
| --- | --- |
| `/wp-json/norwegen/v1/trip` | Komplette Reise: Tage, Tracks, Fähnchen, Fortschritts-Anteile, Kartenkonfiguration |
| `/wp-json/norwegen/v1/trip.geojson` | Route und Fähnchen als GeoJSON-FeatureCollection (Export, Weiterverwendung) |

Die Antwort wird in einem Transient zwischengespeichert und automatisch
erneuert, sobald ein Reisetag gespeichert, gelöscht oder wiederhergestellt wird.

## Struktur

```
wp-content/plugins/norwegen-reise/
├── norwegen-reise.php          Bootstrap, gemeinsame Assets
├── includes/
│   ├── class-nb-post-types.php Beitragstyp „Reisetag“ + Listenspalten
│   ├── class-nb-meta.php       Eckdaten, GPX-Upload, Fähnchen-Editor, Speichern
│   ├── class-nb-geo.php        Distanzen, Vereinfachung, Bounds, Position auf der Route
│   ├── class-nb-gpx.php        GPX- und GeoJSON-Import
│   ├── class-nb-trip.php       Reise-Datenpaket inkl. Fortschritts-Anteilen (gecacht)
│   ├── class-nb-settings.php   Einstellungsseite, Kartenstile, geplante Route
│   └── class-nb-rest.php       REST-Routen
└── assets/                     Admin-Editor, gemeinsamer Karten-Baustein, MapLibre

wp-content/themes/nordlys/
├── front-page.php              Hero + Scroll-Karte
├── single-nb_day.php           Ein Reisetag: Hero, Artikel, Etappenkarte, Fähnchen
├── archive-nb_day.php          Übersicht aller Tage
├── template-parts/day-card.php Eine Tageskarte in der Scroll-Spalte
├── inc/template-tags.php       Template-Helfer
├── assets/js/trip-map.js       Scroll-Fortschritt, Fähnchen, Kameraführung
├── assets/js/day-map.js        Karte eines einzelnen Tages
├── assets/js/lightbox.js       Bildergalerie
├── style.css                   Das komplette Design
└── theme.json                  Farben und Typografie für den Block-Editor
```

## Barrierefreiheit und Performance

* `prefers-reduced-motion` wird respektiert: keine Kamerafahrten, kein Pulsieren,
  keine Einblend-Animationen.
* Die Karte ist per Tastatur bedienbar, Fähnchen sind echte Buttons mit Label.
* Ohne JavaScript bleiben Artikel und Übersicht vollständig lesbar.
* Tracks werden serverseitig vereinfacht, Bilder lazy geladen, das
  Reise-Datenpaket wird gecacht.
