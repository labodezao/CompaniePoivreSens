#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
out_dir="${repo_root}/dist"
out_zip="${out_dir}/poivre-sens.zip"

mkdir -p "${out_dir}"
rm -f "${out_zip}"

git -C "${repo_root}" archive \
  --format=zip \
  --prefix=poivre-sens/ \
  --output="${out_zip}" \
  HEAD:wordpress-theme/poivre-sens

printf 'Archive créée : %s\n' "${out_zip}"
