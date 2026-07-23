#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
out_dir="${repo_root}/dist"
theme_dir="${out_dir}/poivre-sens"
out_zip="${out_dir}/poivre-sens.zip"

rm -rf "${theme_dir}"
mkdir -p "${theme_dir}"
rm -f "${out_zip}"

git -C "${repo_root}" archive \
  --format=tar \
  HEAD:wordpress-theme/poivre-sens \
  | tar -x -C "${theme_dir}"

(
  cd "${out_dir}"
  zip -qr "${out_zip}" poivre-sens
)

printf 'Archive WordPress créée : %s\n' "${out_zip}"
printf 'Dossier artefact GitHub prêt : %s\n' "${theme_dir}"
