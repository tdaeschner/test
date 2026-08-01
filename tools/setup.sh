#!/usr/bin/env bash
#
# Richtet die lokale Testumgebung ein: WordPress installieren, Plugin und Theme
# aktivieren, Demo-Reisetage anlegen.
#
#   docker compose up -d && ./tools/setup.sh
#
# Mehrfach ausführbar - vorhandene Demo-Tage werden dabei ersetzt.

set -euo pipefail

SITE_URL="${SITE_URL:-http://localhost:8080}"
ADMIN_USER="${ADMIN_USER:-norwegen}"
ADMIN_PASS="${ADMIN_PASS:-norwegen}"
ADMIN_MAIL="${ADMIN_MAIL:-mail@example.com}"

if docker compose version >/dev/null 2>&1; then
	COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
	COMPOSE="docker-compose"
else
	echo "Docker Compose wurde nicht gefunden. Bitte Docker Desktop installieren." >&2
	exit 1
fi

wp() {
	$COMPOSE run --rm wpcli "$@"
}

echo "→ Warte auf WordPress …"
for _ in $(seq 1 60); do
	if wp core is-installed --quiet 2>/dev/null || wp core version >/dev/null 2>&1; then
		break
	fi
	sleep 2
done

if wp core is-installed --quiet 2>/dev/null; then
	echo "→ WordPress ist bereits installiert."
else
	echo "→ Installiere WordPress …"
	wp core install \
		--url="$SITE_URL" \
		--title="Norwegen" \
		--admin_user="$ADMIN_USER" \
		--admin_password="$ADMIN_PASS" \
		--admin_email="$ADMIN_MAIL" \
		--skip-email
fi

echo "→ Sprache auf Deutsch stellen …"
wp language core install de_DE --activate || true
wp option update timezone_string "Europe/Berlin"
wp option update date_format "j. F Y"
wp option update blogdescription "Siebzehn Tage Norwegen"

echo "→ Plugin und Theme aktivieren …"
wp plugin activate norwegen-reise
wp theme activate nordlys

echo "→ Permalinks setzen …"
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

echo "→ Demo-Reisetage anlegen …"
wp eval-file tools/demo-data.php

echo
echo "Fertig."
echo "  Seite:    $SITE_URL"
echo "  Backend:  $SITE_URL/wp-admin  ($ADMIN_USER / $ADMIN_PASS)"
echo
echo "Demo-Daten wieder entfernen:"
echo "  $COMPOSE run --rm wpcli eval-file tools/demo-data.php -- --delete"
