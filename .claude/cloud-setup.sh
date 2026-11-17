#!/usr/bin/env bash
# Setup script for a Claude Code cloud environment. Paste into the environment's setup field.
# Mirrors the "test" job in .github/workflows/ci.yml, with services on localhost instead of docker-compose.
set -euo pipefail

cd "${CLAUDE_PROJECT_DIR:-$(pwd)}"

# PHP 8.5 is not in the Ubuntu archive. The PPA host needs to be on the environment's network allowlist.
sudo apt-get update -q
sudo apt-get install -y -q software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt-get update -q
sudo apt-get install -y -q \
  php8.5-cli php8.5-pgsql php8.5-intl php8.5-xml php8.5-mbstring php8.5-curl php8.5-zip php8.5-redis php8.5-pcov \
  postgresql-16 redis-server unzip

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
php bin/console doctrine:database:create --env=test --if-not-exists --no-interaction
php bin/console doctrine:migrations:migrate --env=test --no-interaction --all-or-nothing
cd ..

(cd dashboard && npm ci --no-audit --no-fund)
(cd landing && npm ci --no-audit --no-fund)
