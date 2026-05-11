#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

usage() {
  cat <<'EOF'
Usage: scripts/init-project.sh --project-name <name> [options]

Options:
  --project-name <name>   Project slug, for example: my-app
  --back-repo <name>      Backend repository name, default: <project>-back
  --front-repo <name>     Frontend repository name, default: <project>-front
  --infra-repo <name>     Infra repository name, default: <project>-infra
  --github-owner <name>   Optional GitHub owner, kept for interface consistency
  --help                  Show this help
EOF
}

require_value() {
  local option="$1"
  local value="${2:-}"

  if [ -z "$value" ]; then
    echo "Missing value for $option" >&2
    exit 1
  fi
}

title_case() {
  printf '%s' "$1" | tr '_-' '  ' | awk '{
    for (i = 1; i <= NF; i++) {
      $i = toupper(substr($i, 1, 1)) tolower(substr($i, 2));
    }
    print;
  }'
}

pascal_case() {
  title_case "$1" | tr -d ' '
}

replace_literal() {
  local file="$1"
  local old="$2"
  local new="$3"

  OLD_VALUE="$old" NEW_VALUE="$new" perl -0pi -e 's/\Q$ENV{OLD_VALUE}\E/$ENV{NEW_VALUE}/g' "$file"
}

assert_file() {
  local file="$1"

  if [ ! -f "${ROOT_DIR}/${file}" ]; then
    echo "Expected file not found: ${ROOT_DIR}/${file}" >&2
    exit 1
  fi
}

PROJECT_NAME=""
BACK_REPO=""
FRONT_REPO=""
INFRA_REPO=""

while [ "$#" -gt 0 ]; do
  case "$1" in
    --project-name)
      require_value "$1" "${2:-}"
      PROJECT_NAME="$2"
      shift 2
      ;;
    --back-repo)
      require_value "$1" "${2:-}"
      BACK_REPO="$2"
      shift 2
      ;;
    --front-repo)
      require_value "$1" "${2:-}"
      FRONT_REPO="$2"
      shift 2
      ;;
    --infra-repo)
      require_value "$1" "${2:-}"
      INFRA_REPO="$2"
      shift 2
      ;;
    --github-owner)
      require_value "$1" "${2:-}"
      shift 2
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

if [ -z "$PROJECT_NAME" ]; then
  usage >&2
  exit 1
fi

BACK_REPO="${BACK_REPO:-${PROJECT_NAME}-back}"
FRONT_REPO="${FRONT_REPO:-${PROJECT_NAME}-front}"
INFRA_REPO="${INFRA_REPO:-${PROJECT_NAME}-infra}"

PROJECT_TITLE="$(title_case "$PROJECT_NAME")"
PROJECT_HEADER_PREFIX="$(pascal_case "$PROJECT_NAME")"
PROJECT_DB_IDENTIFIER="${PROJECT_NAME//-/_}"
BACK_REPO_UNDERSCORE="${BACK_REPO//-/_}"
SECRETS_DIR="/run/${PROJECT_NAME}-secrets/jwt"

FILES=(
  "AGENTS.md"
  "README.md"
  "Dockerfile"
  "composer.json"
  ".env.example"
  ".env.test"
  "phpunit.dist.xml"
  "docker-compose.yml"
  "docker-compose.test.yml"
  "docker/entrypoint.sh"
  "scripts/init.sh"
  "config/packages/nelmio_api_doc.yaml"
  "src/Admin/Presentation/Controller/AdminDashboardController.php"
  "templates/admin/dashboard.html.twig"
  "templates/admin/security/login.html.twig"
  "src/Shared/Presentation/Controller/HealthController.php"
  "src/Shared/Infrastructure/Outbox/DispatchOutboxMessageHandler.php"
  "tests/Functional/HealthControllerTest.php"
)

for file in "${FILES[@]}"; do
  assert_file "$file"
done

for file in "${FILES[@]}"; do
  target="${ROOT_DIR}/${file}"
  replace_literal "$target" "starter/starter-back" "${PROJECT_NAME}/back"
  replace_literal "$target" "starter-back" "$BACK_REPO"
  replace_literal "$target" "starter-front" "$FRONT_REPO"
  replace_literal "$target" "starter-infra" "$INFRA_REPO"
  replace_literal "$target" "starter_back" "$BACK_REPO"
  replace_literal "$target" "starter_front" "$FRONT_REPO"
  replace_literal "$target" "starter_infra" "$INFRA_REPO"
  replace_literal "$target" "App Back API" "${PROJECT_TITLE} Back API"
  replace_literal "$target" "Backend Symfony DDD centre sur User avec JWT, outbox, Mercure et Stripe." "${PROJECT_TITLE} Back API Symfony DDD centree sur User avec JWT, outbox, Mercure et Stripe."
  replace_literal "$target" "Starter back" "${PROJECT_TITLE} back"
  replace_literal "$target" "Starter Back Admin" "${PROJECT_TITLE} Admin"
  replace_literal "$target" "Starter backend Symfony DDD centré sur \`User\`, sans notion de \`Profile\`." "${PROJECT_TITLE} Back API Symfony DDD centree sur \`User\`, sans notion de \`Profile\`."
  replace_literal "$target" "Starter backend Symfony DDD centré sur User avec JWT, outbox, Mercure et Stripe." "${PROJECT_TITLE} Back API Symfony DDD centree sur User avec JWT, outbox, Mercure et Stripe."
  replace_literal "$target" "<%s@starter.local>" "<%s@${PROJECT_NAME}.local>"
  replace_literal "$target" "X-Starter-Outbox-Id" "X-${PROJECT_HEADER_PREFIX}-Outbox-Id"
  replace_literal "$target" "/run/starter-secrets/jwt" "$SECRETS_DIR"
done

replace_literal "${ROOT_DIR}/.env.example" "mysql://app:app@db:3306/starter?serverVersion=8.0.32&charset=utf8mb4" "mysql://${PROJECT_DB_IDENTIFIER}:${PROJECT_DB_IDENTIFIER}@db:3306/${PROJECT_DB_IDENTIFIER}?serverVersion=8.0.32&charset=utf8mb4"
replace_literal "${ROOT_DIR}/.env.test" "mysql://app:app@db:3306/starter_test?serverVersion=8.0.32&charset=utf8mb4" "mysql://${PROJECT_DB_IDENTIFIER}:${PROJECT_DB_IDENTIFIER}@db:3306/${PROJECT_DB_IDENTIFIER}_test?serverVersion=8.0.32&charset=utf8mb4"
replace_literal "${ROOT_DIR}/docker-compose.yml" "MYSQL_DATABASE: starter" "MYSQL_DATABASE: ${PROJECT_DB_IDENTIFIER}"
replace_literal "${ROOT_DIR}/docker-compose.yml" "MYSQL_USER: app" "MYSQL_USER: ${PROJECT_DB_IDENTIFIER}"
replace_literal "${ROOT_DIR}/docker-compose.yml" "MYSQL_PASSWORD: app" "MYSQL_PASSWORD: ${PROJECT_DB_IDENTIFIER}"
replace_literal "${ROOT_DIR}/docker-compose.test.yml" "MYSQL_DATABASE: starter_test" "MYSQL_DATABASE: ${PROJECT_DB_IDENTIFIER}_test"
replace_literal "${ROOT_DIR}/docker-compose.test.yml" "MYSQL_USER: app" "MYSQL_USER: ${PROJECT_DB_IDENTIFIER}"
replace_literal "${ROOT_DIR}/docker-compose.test.yml" "MYSQL_PASSWORD: app" "MYSQL_PASSWORD: ${PROJECT_DB_IDENTIFIER}"
replace_literal "${ROOT_DIR}/phpunit.dist.xml" "mysql://app:app@db:3306/starter_test?serverVersion=8.0.32&amp;charset=utf8mb4" "mysql://${PROJECT_DB_IDENTIFIER}:${PROJECT_DB_IDENTIFIER}@db:3306/${PROJECT_DB_IDENTIFIER}_test?serverVersion=8.0.32&amp;charset=utf8mb4"
replace_literal "${ROOT_DIR}/src/Shared/Presentation/Controller/HealthController.php" "service' => '${BACK_REPO}'" "service' => '${BACK_REPO_UNDERSCORE}'"
replace_literal "${ROOT_DIR}/tests/Functional/HealthControllerTest.php" "service' => '${BACK_REPO}'" "service' => '${BACK_REPO_UNDERSCORE}'"

cat <<EOF
Project templating applied in ${BACK_REPO}.

Applied values:
- project: ${PROJECT_NAME}
- back repo: ${BACK_REPO}
- front repo: ${FRONT_REPO}
- infra repo: ${INFRA_REPO}
EOF
