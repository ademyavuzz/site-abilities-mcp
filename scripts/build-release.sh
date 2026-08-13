#!/usr/bin/env bash
set -euo pipefail

version="${1:-0.1.0-alpha}"
root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
dist_dir="${root_dir}/dist"
temporary_parent="$(mktemp -d "${TMPDIR:-/tmp}/site-abilities-mcp-build.XXXXXX")"
build_dir="${temporary_parent}/site-abilities-mcp"
plugin_version="$(sed -n 's/^ \* Version:[[:space:]]*//p' "${root_dir}/site-abilities-mcp.php" | head -n 1)"

if [[ "${version}" != "${plugin_version}" ]]; then
	echo "Requested release ${version} does not match plugin header ${plugin_version}." >&2
	exit 1
fi

mkdir -p "${build_dir}/includes" "${dist_dir}"

cp "${root_dir}/site-abilities-mcp.php" "${build_dir}/"
cp "${root_dir}/index.php" "${build_dir}/"
cp "${root_dir}/readme.txt" "${build_dir}/"
cp "${root_dir}/LICENSE" "${build_dir}/"
cp "${root_dir}/includes/"*.php "${build_dir}/includes/"

cd "${temporary_parent}"
zip -qr "${dist_dir}/site-abilities-mcp-${version}.zip" site-abilities-mcp
cd "${dist_dir}"
checksum="$(shasum -a 256 "site-abilities-mcp-${version}.zip" | awk '{print $1}')"
printf '%s  %s\n' "${checksum}" "site-abilities-mcp-${version}.zip" > "site-abilities-mcp-${version}.zip.sha256"

echo "Built ${dist_dir}/site-abilities-mcp-${version}.zip"
