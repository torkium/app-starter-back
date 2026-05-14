#!/usr/bin/env bash
set -euo pipefail

cd /app

mkdir -p var/cache var/log var/runtime/jwt

if [ ! -f .env ]; then
  : > .env
fi

resolve_path() {
  local raw_path="$1"
  raw_path="${raw_path%\"}"
  raw_path="${raw_path#\"}"
  raw_path="${raw_path%\'}"
  raw_path="${raw_path#\'}"
  raw_path="${raw_path//%kernel.project_dir%/\/app}"
  printf '%s' "$raw_path"
}

private_key_path="$(resolve_path "${JWT_SECRET_KEY:-}")"
public_key_path="$(resolve_path "${JWT_PUBLIC_KEY:-}")"
private_key_source="/run/my_app-secrets/jwt/private.pem"
public_key_source="/run/my_app-secrets/jwt/public.pem"

write_pem_file() {
  local target_path="$1"
  local content="$2"
  local normalized_content
  normalized_content="${content//\\n/$'\n'}"

  mkdir -p "$(dirname "$target_path")"
  printf '%s\n' "$normalized_content" > "$target_path"
}

if [ -n "${JWT_PRIVATE_KEY_PEM:-}" ] || [ -n "${JWT_PUBLIC_KEY_PEM:-}" ]; then
  if [ -z "$private_key_path" ] || [ -z "$public_key_path" ]; then
    echo "JWT_SECRET_KEY and JWT_PUBLIC_KEY must be set when providing JWT PEM content." >&2
    exit 1
  fi

  if [ -z "${JWT_PRIVATE_KEY_PEM:-}" ] || [ -z "${JWT_PUBLIC_KEY_PEM:-}" ]; then
    echo "JWT_PRIVATE_KEY_PEM and JWT_PUBLIC_KEY_PEM must be provided together." >&2
    exit 1
  fi

  write_pem_file "$private_key_path" "${JWT_PRIVATE_KEY_PEM}"
  write_pem_file "$public_key_path" "${JWT_PUBLIC_KEY_PEM}"
fi

if { [ -z "$private_key_path" ] || [ ! -f "$private_key_path" ]; } && [ -f "$private_key_source" ]; then
  mkdir -p "$(dirname "$private_key_path")"
  cp "$private_key_source" "$private_key_path"
fi

if { [ -z "$public_key_path" ] || [ ! -f "$public_key_path" ]; } && [ -f "$public_key_source" ]; then
  mkdir -p "$(dirname "$public_key_path")"
  cp "$public_key_source" "$public_key_path"
fi

if [ "${APP_ENV:-}" = "test" ] && { [ ! -f "$private_key_path" ] || [ ! -f "$public_key_path" ]; }; then
  if [ -z "$private_key_path" ] || [ -z "$public_key_path" ] || [ -z "${JWT_PASSPHRASE:-}" ]; then
    echo "JWT_SECRET_KEY, JWT_PUBLIC_KEY and JWT_PASSPHRASE must be set to generate test JWT keys." >&2
    exit 1
  fi

  mkdir -p "$(dirname "$private_key_path")" "$(dirname "$public_key_path")"
  openssl genrsa -aes256 -passout "pass:${JWT_PASSPHRASE}" -out "$private_key_path" 2048 >/dev/null 2>&1
  openssl rsa -in "$private_key_path" -passin "pass:${JWT_PASSPHRASE}" -pubout -out "$public_key_path" >/dev/null 2>&1
fi

if [ -z "$private_key_path" ] || [ ! -f "$private_key_path" ]; then
  echo "JWT private key file is missing. Run make init locally or provide JWT_PRIVATE_KEY_PEM at runtime." >&2
  exit 1
fi

if [ -z "$public_key_path" ] || [ ! -f "$public_key_path" ]; then
  echo "JWT public key file is missing. Run make init locally or provide JWT_PUBLIC_KEY_PEM at runtime." >&2
  exit 1
fi

make_key_readable_by_php() {
  local key_path="$1"

  if [ ! -w "$key_path" ]; then
    return 0
  fi

  case "$key_path" in
    /app/config/jwt/*)
      chmod 644 "$key_path"
      ;;
    *)
      chown www-data:www-data "$key_path"
      chmod 600 "$key_path"
      ;;
  esac
}

make_key_readable_by_php "$private_key_path"
make_key_readable_by_php "$public_key_path"

exec "$@"
