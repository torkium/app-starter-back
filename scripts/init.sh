#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"
JWT_DIR="${ROOT_DIR}/config/jwt"
PRIVATE_KEY="${JWT_DIR}/private.pem"
PUBLIC_KEY="${JWT_DIR}/public.pem"

if [ -f "${ENV_FILE}" ]; then
  echo "Keep existing ${ENV_FILE}"
else
  cp "${ROOT_DIR}/.env.example" "${ENV_FILE}"
  app_secret="$(openssl rand -hex 32)"
  jwt_passphrase="$(openssl rand -base64 36 | tr -d '\n')"
  mercure_secret="$(openssl rand -hex 32)"
  sed -i "s#^APP_SECRET=.*#APP_SECRET=${app_secret}#" "${ENV_FILE}"
  sed -i "s#^JWT_PASSPHRASE=.*#JWT_PASSPHRASE=${jwt_passphrase}#" "${ENV_FILE}"
  sed -i "s#^MERCURE_JWT_SECRET=.*#MERCURE_JWT_SECRET=${mercure_secret}#" "${ENV_FILE}"
  echo "Created ${ENV_FILE}"
fi

mkdir -p "${JWT_DIR}"

if [ ! -f "${PRIVATE_KEY}" ]; then
  openssl genrsa -out "${PRIVATE_KEY}" 4096 >/dev/null 2>&1
  echo "Created ${PRIVATE_KEY}"
fi
chmod 600 "${PRIVATE_KEY}"

if [ ! -f "${PUBLIC_KEY}" ]; then
  openssl rsa -pubout -in "${PRIVATE_KEY}" -out "${PUBLIC_KEY}" >/dev/null 2>&1
  echo "Created ${PUBLIC_KEY}"
fi
chmod 644 "${PUBLIC_KEY}"

cat <<'EOF'
Starter back initialized.

Next steps:
1. Review .env and replace any remaining placeholder values if needed
2. Run: make up
3. Run: make migrate
4. Validate: make test
EOF
