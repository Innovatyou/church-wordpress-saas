#!/usr/bin/env bash
# Build WordPress-installable zips into dist/:
#   dist/kingdom-theme.zip          Appearance → Themes → Add New → Upload Theme
#   dist/kingdom-church-suite.zip   Plugins → Add New → Upload Plugin
#   dist/kingdom-saas.zip           Network Admin → Plugins → Add New → Upload Plugin (Multisite only)
set -euo pipefail
cd "$(dirname "$0")/.."
rm -rf dist build && mkdir -p dist build

pack() { # folder-name source-dir zip-name
	cp -r "$2" "build/$1"
	# Developer-only files stay out of the install packages.
	rm -rf "build/$1/tests" "build/$1/node_modules"
	find "build/$1" -name '.DS_Store' -delete
	(cd build && zip -qr -X "../dist/$3" "$1")
	echo "dist/$3  ($(du -h "dist/$3" | cut -f1))"
}

pack kingdom              themes/kingdom                        kingdom-theme.zip
pack kingdom-church-suite plugins/kingdom-church-suite          kingdom-church-suite.zip
pack kingdom-saas         plugins/kingdom-saas                  kingdom-saas.zip
rm -rf build
