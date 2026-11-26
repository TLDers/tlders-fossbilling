#!/bin/sh
# Builds the three packages into dist/:
#  - fossbilling (manual install: unzip into modules/, old manifest, works on 0.8.9 and main)
#  - clientfence (manual install)
#  - fossbilling-directory (extensions.fossbilling.org: files at the zip root, manifest v1)
set -e
php build.php
version=$(php -r 'echo json_decode(file_get_contents("template/manifest.json"))->version;')
mkdir -p dist
(cd fossbilling && zip -qrX -FS "../dist/tlders-price-monitor-fossbilling-$version.zip" Tldersprices -x ".DS_Store")
(cd clientfence && zip -qrX -FS "../dist/tlders-price-monitor-clientfence-$version.zip" Tldersprices -x ".DS_Store")
tmp=$(mktemp -d)
cp -R fossbilling/Tldersprices/. "$tmp/"
cp template/manifest.directory.json "$tmp/manifest.json"
(cd "$tmp" && zip -qrX -D "$OLDPWD/dist/tlders-price-monitor-fossbilling-directory-$version.zip" . -x ".DS_Store")
rm -rf "${tmp:?}"
ls -la dist | awk 'NR>3{print $5, $9}'
