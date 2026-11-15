#!/usr/bin/env bash
set -Eeuo pipefail

deploy_root="${1:?deployment root is required}"
php_bin="${2:-/usr/bin/php84}"
retention_days="${3:-30}"
current_link="${deploy_root}/current"
shared_dir="${deploy_root}/shared"
backup_dir="${deploy_root}/backups/daily/$(date +%Y%m%d-%H%M%S)"

[[ -f "${deploy_root}/.estateflow-production" ]] || { echo "Missing EstateFlow production marker." >&2; exit 2; }
[[ -x "${php_bin}" ]] || { echo "Configured PHP binary is not executable." >&2; exit 2; }
[[ "${retention_days}" =~ ^[1-9][0-9]*$ ]] || { echo "Retention must be a positive number of days." >&2; exit 2; }
[[ -f "${current_link}/artisan" ]] || { echo "Active EstateFlow release is unavailable." >&2; exit 2; }

mkdir -p "${backup_dir}"
chmod 700 "${deploy_root}/backups" "${deploy_root}/backups/daily" "${backup_dir}"

cleanup_failed_backup() {
  rm -rf "${backup_dir}"
}
trap cleanup_failed_backup ERR

"${php_bin}" "${current_link}/deploy/backup-database.php" "${backup_dir}"

if [[ -d "${shared_dir}/storage/app/customer-documents" ]]; then
  tar -C "${shared_dir}/storage/app" -czf "${backup_dir}/customer-documents.tar.gz" customer-documents
  sha256sum "${backup_dir}/customer-documents.tar.gz" > "${backup_dir}/customer-documents.tar.gz.sha256"
  chmod 600 "${backup_dir}/customer-documents.tar.gz" "${backup_dir}/customer-documents.tar.gz.sha256"
fi

trap - ERR
find "${deploy_root}/backups/daily" -mindepth 1 -maxdepth 1 -type d -mtime "+${retention_days}" -exec rm -rf -- {} +
echo "EstateFlow daily backup completed: ${backup_dir}"
