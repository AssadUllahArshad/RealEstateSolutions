#!/usr/bin/env bash
set -Eeuo pipefail

deploy_root="${1:?deployment root is required}"
release_id="${2:?release id is required}"
php_bin="${3:-/usr/bin/php84}"
web_root="${4:-}"
release_dir="${deploy_root}/releases/${release_id}"
shared_dir="${deploy_root}/shared"
current_link="${deploy_root}/current"
backup_dir="${deploy_root}/backups/$(date +%Y%m%d-%H%M%S)"
previous_target=""

[[ -f "${deploy_root}/.estateflow-production" ]] || { echo "Missing EstateFlow production marker." >&2; exit 2; }
[[ -x "${php_bin}" ]] || { echo "Configured PHP binary is not executable." >&2; exit 2; }
[[ "$("${php_bin}" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" == "8.4" ]] || { echo "EstateFlow requires PHP 8.4." >&2; exit 2; }
[[ -d "${release_dir}" ]] || { echo "Uploaded release does not exist." >&2; exit 2; }
[[ -f "${shared_dir}/.env" ]] || { echo "Shared production .env is missing." >&2; exit 2; }

mkdir -p "${shared_dir}/storage/app/customer-documents" "${shared_dir}/storage/framework/cache" \
  "${shared_dir}/storage/framework/sessions" "${shared_dir}/storage/framework/views" \
  "${shared_dir}/storage/logs" "${deploy_root}/backups"

ln -sfn "${shared_dir}/.env" "${release_dir}/.env"
rm -rf "${release_dir}/storage"
ln -s "${shared_dir}/storage" "${release_dir}/storage"

if [[ -L "${current_link}" ]]; then
  previous_target="$(readlink "${current_link}")"
  "${php_bin}" "${current_link}/artisan" down --retry=60 || true
fi

recover_previous() {
  if [[ -n "${previous_target}" ]]; then
    ln -sfn "${previous_target}" "${current_link}"
    "${php_bin}" "${current_link}/artisan" up || true
  fi
}
trap recover_previous ERR

mkdir -p "${backup_dir}"
chmod 700 "${backup_dir}"
cp "${shared_dir}/.env" "${backup_dir}/environment.env"
chmod 600 "${backup_dir}/environment.env"
"${php_bin}" "${release_dir}/deploy/backup-database.php" "${backup_dir}"

if [[ -d "${shared_dir}/storage/app/customer-documents" ]]; then
  tar -C "${shared_dir}/storage/app" -czf "${backup_dir}/customer-documents.tar.gz" customer-documents
  sha256sum "${backup_dir}/customer-documents.tar.gz" > "${backup_dir}/customer-documents.tar.gz.sha256"
fi

cd "${release_dir}"
"${php_bin}" artisan migrate --force
"${php_bin}" artisan optimize
"${php_bin}" artisan storage:link || true

ln -sfn "${release_dir}" "${current_link}"

if [[ -n "${web_root}" ]]; then
  [[ "${web_root}" =~ ^[a-zA-Z0-9_./-]+$ ]] || { echo "Unsafe public web-root path." >&2; exit 2; }
  [[ -f "${web_root}/.estateflow-public" ]] || { echo "Missing EstateFlow public-root marker." >&2; exit 2; }
  rsync -a --delete --exclude='.estateflow-public' "${release_dir}/public/" "${web_root}/"
  sed \
    -e "s#__DIR__.'/../storage#'${deploy_root}/current/storage#g" \
    -e "s#__DIR__.'/../vendor#'${deploy_root}/current/vendor#g" \
    -e "s#__DIR__.'/../bootstrap#'${deploy_root}/current/bootstrap#g" \
    "${release_dir}/public/index.php" > "${web_root}/index.php"
fi

"${php_bin}" "${current_link}/artisan" up
trap - ERR

find "${deploy_root}/releases" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
  | sort -nr | tail -n +6 | cut -d' ' -f2- | xargs -r rm -rf

echo "Activated EstateFlow release ${release_id}."
