#!/usr/bin/env bash
# Setup for the Claude Code cloud environment. The environment runs it through .claude/cloud-bootstrap.sh.
# Mirrors the "test" job in .github/workflows/ci.yml, with services on localhost instead of docker-compose.
set -euo pipefail

# The setup phase runs from /tmp without CLAUDE_PROJECT_DIR, so find the clone (/home/user/therapy-app so far).
repo="${CLAUDE_PROJECT_DIR:-$PWD}"
if [ ! -f "$repo/API/composer.json" ]; then
  repo="$(find /home /root /workspace -maxdepth 4 -path '*/API/composer.json' 2>/dev/null | head -1 || true)"
  repo="${repo%/API/composer.json}"
fi
if [ -z "$repo" ]; then
  echo "Could not find the repository clone with API/composer.json." >&2
  exit 1
fi
cd "$repo"
echo "Using the clone at $repo"

# Same as the CI job env. Without APP_ENV=test, composer's cache:clear boots dev and crashes on missing vars.
export APP_ENV=test TZ=UTC PGTZ=UTC
# Setup runs as root, and composer disables plugins as root. Symfony Flex is a plugin, and auto-scripts need it.
export COMPOSER_ALLOW_SUPERUSER=1

if [ "$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)" -lt 20 ]; then
  echo "Node 20+ is required, the image has $(node -v 2>/dev/null || echo none)." >&2
  exit 1
fi

# Launchpad (the PPA and its API) often returns 503, sometimes for minutes, even after apt's own retries.
# Downloaded packages stay cached, so each install pass only fetches what is still missing.
echo 'Acquire::Retries "5";' | sudo tee /etc/apt/apt.conf.d/80-retries
retry() {
  for attempt in 1 2 3 4 5 6 7 8; do
    "$@" && return 0
    echo "Failed (attempt $attempt of 8), retrying in 30s: $*" >&2
    sleep 30
  done
  return 1
}
apt_install() { retry sudo apt-get install -y -q "$@"; }

# Ubuntu archive packages first, before the PPA is added, so a PPA outage cannot block them.
sudo apt-get update -q
apt_install software-properties-common redis-server unzip
# postgresql-16 is only in the 24.04 archive. Older images get their default version.
if apt-cache show postgresql-16 >/dev/null 2>&1; then apt_install postgresql-16; else apt_install postgresql; fi

# PHP 8.5 is not in the Ubuntu archive. Add ppa.launchpadcontent.net and getcomposer.org to the network allowlist.
retry sudo add-apt-repository -y ppa:ondrej/php
retry sudo apt-get update -q
apt_install php8.5-cli php8.5-pgsql php8.5-intl php8.5-xml php8.5-mbstring php8.5-curl php8.5-zip php8.5-redis php8.5-pcov
# The image pins php to 8.3 in manual alternatives mode, so installing 8.5 alone does not switch it.
sudo update-alternatives --set php /usr/bin/php8.5

if ! command -v composer >/dev/null; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

sudo service postgresql start
sudo service redis-server start

sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='therapy_user'" | grep -q 1 \
  || sudo -u postgres psql -c "CREATE USER therapy_user WITH PASSWORD 'therapy_pass' CREATEDB;"

# Test-only values from ci.yml. .env.test.local overrides the docker hostnames in .env.test.
cd API
touch .env
cat > .env.test.local <<'EOF'
DATABASE_URL="postgresql://therapy_user:therapy_pass@localhost:5432/therapy_db?serverVersion=16&charset=utf8"
REDIS_URL="redis://localhost:6379"
MAILER_DSN="null://null"
JWT_PASSPHRASE=therapy_jwt_passphrase
APPOINTMENT_DURATION_MINUTES=50
SLOT_LOCK_TTL=600
PRACTICE_TIMEZONE=America/Caracas
SLOT_START_INCREMENT_MINUTES=30
DEFAULT_URI=http://localhost
JWT_COOKIE_SECURE=false
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
EOF

composer install --no-interaction --prefer-dist --no-progress
php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
php bin/console doctrine:database:create --if-not-exists --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
cd ..

(cd dashboard && npm ci --no-audit --no-fund)
(cd landing && npm ci --no-audit --no-fund)

# skill-gate.mjs needs the mattpocock-skills plugin, and enabledPlugins in project settings
# does not install it in a cloud session. A failure here only warns: the rest of setup is done.
if command -v claude >/dev/null \
  && claude plugin marketplace add mattpocock/skills \
  && claude plugin install mattpocock-skills@mattpocock; then
  echo "Installed the mattpocock-skills plugin"
else
  echo "Warning: could not install the mattpocock-skills plugin, so skill-gate will block API/src edits." >&2
fi

# The user's global config (CLAUDE.md, output style, scrub and authorship hooks) is the private
# luisd57/claude-config repo. A session clones only the repo picked for it, so setup clones this one
# with CLAUDE_CONFIG_TOKEN (read-only, set in the environment). The token goes in a header, never a URL.
installer="$(find /home /root /workspace -maxdepth 3 -path '*/claude-config/cloud-install.sh' 2>/dev/null | head -1 || true)"
if [ -z "$installer" ] && [ -n "${CLAUDE_CONFIG_TOKEN:-}" ]; then
  config_dir="$(mktemp -d)/claude-config"
  auth="$(printf 'x-access-token:%s' "$CLAUDE_CONFIG_TOKEN" | base64 | tr -d '\n')"
  if git -c "http.extraHeader=Authorization: Basic $auth" clone -q --depth 1 \
    https://github.com/luisd57/claude-config.git "$config_dir"; then
    installer="$config_dir/cloud-install.sh"
  fi
fi
if [ -n "$installer" ] && bash "$installer"; then
  :
else
  echo "Warning: luisd57/claude-config was not installed, so cloud sessions run without the global CLAUDE.md and hooks." >&2
fi
