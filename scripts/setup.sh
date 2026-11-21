#!/usr/bin/env bash
# One-command local setup: .env -> docker compose up -> wait -> demo data -> smoke checks -> summary.
# Needs only Docker (with Compose) and curl. Works on macOS (bash 3.2) and Linux.
#
#   scripts/setup.sh            build + start everything, seed demo data, run the checks
#   scripts/setup.sh --check    only run the checks against the running stack (no build, no seeding)
#   scripts/setup.sh --reset    docker compose down -v first (DELETES the database; asks y/N)
#   scripts/setup.sh --reset --yes   same, without the prompt
#
# Exits non-zero if any check fails. SETUP_TIMEOUT (seconds, default 600) bounds the waits.
set -u

cd "$(dirname "$0")/.." || exit 1

MODE=setup
RESET=false
ASSUME_YES=false
for arg in "$@"; do
    case "$arg" in
        --check) MODE=check ;;
        --reset) RESET=true ;;
        --yes | -y) ASSUME_YES=true ;;
        -h | --help) sed -n '2,10p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unknown option: $arg (see --help)" >&2; exit 2 ;;
    esac
done
if [ "$MODE" = check ] && [ "$RESET" = true ]; then
    echo "--check and --reset can't be combined." >&2
    exit 2
fi

TIMEOUT="${SETUP_TIMEOUT:-600}"
OK="✔"
FAIL="✘"
FAILED=0
SUMMARY=""
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

say() { printf '\n==> %s\n' "$*"; }
pass() { SUMMARY="${SUMMARY}  ${OK} $1\n"; printf '  %s %s\n' "$OK" "$1"; }
fail() { SUMMARY="${SUMMARY}  ${FAIL} $1\n"; printf '  %s %s\n' "$FAIL" "$1"; FAILED=$((FAILED + 1)); }

# Value of KEY from .env (or .env.example), surrounding quotes removed; $2 when unset or empty.
env_value() {
    local file value=""
    for file in .env .env.example; do
        if [ -f "$file" ]; then
            value="$(sed -n "s/^$1=//p" "$file" | tail -n 1)"
            break
        fi
    done
    value="${value%\"}"; value="${value#\"}"; value="${value%\'}"; value="${value#\'}"
    printf '%s' "${value:-$2}"
}

# The XSRF-TOKEN cookie from a curl cookie jar, URL-decoded (it is sent back as the X-XSRF-TOKEN header).
xsrf_token() {
    local raw
    raw="$(awk '$6 == "XSRF-TOKEN" { v = $7 } END { print v }' "$1")"
    printf '%b' "${raw//%/\\x}"
}

# Runs "$@" until it succeeds or TIMEOUT passes, printing progress. Returns 1 on timeout.
wait_for() {
    local label="$1" start=$SECONDS
    shift
    printf '  waiting for %s ' "$label"
    until "$@" >/dev/null 2>&1; do
        if [ $((SECONDS - start)) -ge "$TIMEOUT" ]; then
            printf ' timed out after %ss\n' "$TIMEOUT"
            return 1
        fi
        printf '.'
        sleep 3
    done
    printf ' ready (%ss)\n' $((SECONDS - start))
}

http_ok() { curl -fsS -o /dev/null --max-time 5 "$1"; }
db_healthy() { [ "$(docker inspect -f '{{.State.Health.Status}}' "$(docker compose ps -q db)" 2>/dev/null)" = healthy ]; }
categories_ok() { curl -fsS --max-time 5 -H 'Accept: application/json' "$API/api/v1/categories" | grep -q '"slug"'; }
artisan() { docker compose exec -T api sh -c 'APP_KEY="${APP_KEY:-$(cat storage/app/.app-key 2>/dev/null)}" php artisan "$@"' artisan "$@"; }

command -v docker >/dev/null 2>&1 || { echo "Docker is required (https://docs.docker.com/get-docker/)." >&2; exit 1; }
command -v curl >/dev/null 2>&1 || { echo "curl is required." >&2; exit 1; }
docker compose version >/dev/null 2>&1 || { echo "Docker Compose v2 is required (docker compose ...)." >&2; exit 1; }
docker info >/dev/null 2>&1 || { echo "The Docker daemon is not running; start Docker and retry." >&2; exit 1; }

# ---------------------------------------------------------------------------------------------
if [ "$MODE" = setup ]; then
    if [ "$RESET" = true ]; then
        say "Reset: docker compose down -v"
        if [ "$ASSUME_YES" != true ]; then
            if [ ! -t 0 ]; then
                echo "--reset deletes the database volume (all enquiries, users, currencies). Re-run with --yes to confirm." >&2
                exit 1
            fi
            printf 'This deletes the local database volume (all enquiries, users and currency edits). Continue? [y/N] '
            read -r answer
            case "$answer" in y | Y | yes | YES) ;; *) echo "Aborted."; exit 1 ;; esac
        fi
        docker compose down -v --remove-orphans || exit 1
    fi

    say "Environment"
    if [ -f .env ]; then
        echo "  .env exists; leaving it unchanged."
    else
        cp .env.example .env && echo "  Created .env from .env.example (local-only defaults)."
    fi

    say "Build and start (docker compose up -d --build)"
    docker compose up -d --build || { echo "docker compose up failed." >&2; exit 1; }
fi

API="$(env_value APP_URL http://localhost:8000)"; API="${API%/}"
WEB="$(env_value CORS_ALLOWED_ORIGIN http://localhost:5173)"; WEB="${WEB%/}"
MAILPIT="http://localhost:8025"
ADMIN_ENABLED="$(env_value ADMIN_ENABLED true)"
ADMIN_EMAIL="$(env_value ADMIN_EMAIL admin@example.com)"
ADMIN_PASSWORD="$(env_value ADMIN_PASSWORD local-admin-password)"
CUSTOMER_EMAIL="$(env_value DEMO_CUSTOMER_EMAIL customer@example.com)"
CUSTOMER_PASSWORD="$(env_value DEMO_CUSTOMER_PASSWORD local-customer-password)"

say "Waiting for the services (timeout ${TIMEOUT}s; the first start builds the admin panel)"
if wait_for "db (healthy)" db_healthy; then pass "Postgres is healthy"; else fail "Postgres is healthy"; fi
if wait_for "api $API/up" http_ok "$API/up"; then pass "API answers GET /up"; else fail "API answers GET /up (see: docker compose logs api)"; fi
if wait_for "api /api/v1/categories" categories_ok; then pass "API returns categories (catalogue imported)"; else fail "API returns categories (catalogue imported)"; fi
if wait_for "web $WEB" http_ok "$WEB/"; then pass "Web app answers on $WEB"; else fail "Web app answers on $WEB (see: docker compose logs web)"; fi
if wait_for "Mailpit $MAILPIT" http_ok "$MAILPIT/"; then pass "Mailpit answers on $MAILPIT"; else fail "Mailpit answers on $MAILPIT"; fi

if [ "$MODE" = setup ]; then
    say "Demo data (DemoSeeder; idempotent, sends no email)"
    if artisan db:seed --class=DemoSeeder --force --no-interaction; then
        pass "Demo data seeded (admin, demo customer, [demo] enquiries)"
    else
        fail "Demo data seeded (see the output above)"
    fi
fi

say "Login checks"
if [ "$ADMIN_ENABLED" != true ]; then
    fail "Admin panel enabled (ADMIN_ENABLED is not true in .env)"
else
    # Admin: GET the login page for the session + XSRF-TOKEN cookies, POST the credentials with the token.
    JAR="$TMP/admin.jar"
    curl -sS -o /dev/null --max-time 10 -c "$JAR" "$API/admin/login"
    status="$(curl -sS -o /dev/null --max-time 10 -b "$JAR" -c "$JAR" -w '%{http_code} %{redirect_url}' \
        -H "X-XSRF-TOKEN: $(xsrf_token "$JAR")" -H 'Accept: text/html' \
        --data-urlencode "email=$ADMIN_EMAIL" --data-urlencode "password=$ADMIN_PASSWORD" "$API/admin/login")"
    if [ "$status" = "302 $API/admin" ]; then
        pass "Admin login ($ADMIN_EMAIL) redirects to /admin"
    else
        fail "Admin login ($ADMIN_EMAIL) redirects to /admin (got: $status)"
    fi

    page="$(curl -sS --max-time 10 -b "$JAR" -w '\n%{http_code}' "$API/admin")"
    if [ "${page##*$'\n'}" = 200 ] && printf '%s' "$page" | grep -q '"component":"dashboard"'; then
        pass "GET /admin shows the dashboard"
    else
        fail "GET /admin shows the dashboard (HTTP ${page##*$'\n'})"
    fi

    if curl -sS --max-time 10 -b "$JAR" "$API/admin/enquiries" | grep -q '\[demo\]'; then
        pass "Admin enquiries list shows the demo enquiries"
    else
        fail "Admin enquiries list shows the demo enquiries (run: scripts/setup.sh, or SEED_DEMO_DATA=true)"
    fi

    # A wrong password must not log in: back to the login form, and /admin still redirects to it.
    BAD="$TMP/bad.jar"
    curl -sS -o /dev/null --max-time 10 -c "$BAD" "$API/admin/login"
    status="$(curl -sS -o /dev/null --max-time 10 -b "$BAD" -c "$BAD" -w '%{http_code} %{redirect_url}' \
        -H "X-XSRF-TOKEN: $(xsrf_token "$BAD")" -H 'Accept: text/html' \
        --data-urlencode "email=$ADMIN_EMAIL" --data-urlencode "password=not-the-password" "$API/admin/login")"
    after="$(curl -sS -o /dev/null --max-time 10 -b "$BAD" -w '%{http_code} %{redirect_url}' "$API/admin")"
    if [ "$status" = "302 $API/admin/login" ] && [ "$after" = "302 $API/admin/login" ]; then
        pass "Wrong admin password is refused"
    else
        fail "Wrong admin password is refused (login: $status, /admin: $after)"
    fi
fi

# Demo customer: Sanctum SPA login from the shop origin, then /admin must be a 403.
SHOP="$TMP/shop.jar"
curl -sS -o /dev/null --max-time 10 -c "$SHOP" -H "Origin: $WEB" -H "Referer: $WEB/" "$API/sanctum/csrf-cookie"
status="$(curl -sS -o "$TMP/shop.json" --max-time 10 -b "$SHOP" -c "$SHOP" -w '%{http_code}' \
    -H "Origin: $WEB" -H "Referer: $WEB/" -H 'Accept: application/json' \
    -H "X-XSRF-TOKEN: $(xsrf_token "$SHOP")" \
    --data-urlencode "email=$CUSTOMER_EMAIL" --data-urlencode "password=$CUSTOMER_PASSWORD" "$API/api/v1/auth/login")"
if [ "$status" = 200 ]; then
    pass "Demo customer ($CUSTOMER_EMAIL) can log in to the shop"
else
    fail "Demo customer ($CUSTOMER_EMAIL) can log in to the shop (HTTP $status)"
fi
if [ "$ADMIN_ENABLED" = true ]; then
    status="$(curl -sS -o /dev/null --max-time 10 -b "$SHOP" -w '%{http_code}' "$API/admin")"
    if [ "$status" = 403 ]; then
        pass "Demo customer is refused by /admin (403)"
    else
        fail "Demo customer is refused by /admin (got HTTP $status)"
    fi
fi

# ---------------------------------------------------------------------------------------------
say "Summary"
printf '%b' "$SUMMARY"
cat <<EOF

  Shop      $WEB
  Admin     $API/admin
  API       $API/api/v1   (e.g. $API/api/v1/products/featured)
  Mailpit   $MAILPIT   (the "sales inbox")

  Local demo logins (local-only defaults from .env; change them for anything but your machine):
    Admin     $ADMIN_EMAIL / $ADMIN_PASSWORD
    Customer  $CUSTOMER_EMAIL / $CUSTOMER_PASSWORD   (shop login at $WEB/login)

EOF
if [ "$FAILED" -gt 0 ]; then
    echo "  $FAILED check(s) failed. Logs: docker compose logs api web  ·  README.md -> Troubleshooting"
    exit 1
fi
echo "  All checks passed."
