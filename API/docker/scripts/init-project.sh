#!/bin/bash
set -e

echo "============================================"
echo "Creating Symfony Project Structure"
echo "============================================"

# Create Symfony project
composer create-project symfony/skeleton:"7.1.*" temp_project --no-interaction

# Move files from temp_project to current directory
cp -r temp_project/* .
cp temp_project/.env .
cp temp_project/.gitignore .
rm -rf temp_project

echo "============================================"
echo "Installing Required Packages"
echo "============================================"

# Core Symfony packages
composer require symfony/orm-pack --no-interaction
composer require symfony/security-bundle --no-interaction
composer require symfony/validator --no-interaction
composer require symfony/serializer --no-interaction
composer require symfony/property-access --no-interaction
composer require symfony/mailer --no-interaction
composer require symfony/uid --no-interaction

# API specific packages
composer require nelmio/api-doc-bundle --no-interaction
composer require lexik/jwt-authentication-bundle --no-interaction

# Development packages
composer require --dev symfony/maker-bundle --no-interaction
composer require --dev symfony/debug-bundle --no-interaction
composer require --dev symfony/profiler-pack --no-interaction
composer require --dev phpunit/phpunit --no-interaction

echo "============================================"
echo "Generating JWT Keys"
echo "============================================"

mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:therapy_jwt_passphrase
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:therapy_jwt_passphrase

echo "============================================"
echo "Project Initialization Complete!"
echo "============================================"
