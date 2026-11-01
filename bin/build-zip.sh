#!/usr/bin/env bash
# Builds dist/theblue-email-forms.zip: the plugin as WordPress installs it,
# without anything listed in .distignore.
set -euo pipefail

cd "$(dirname "$0")/.."
slug=theblue-email-forms
rm -rf build dist
mkdir -p "build/$slug" dist

if command -v rsync > /dev/null; then
	rsync -a --exclude-from=.distignore ./ "build/$slug/"
else
	tar --exclude=./build --exclude=./dist -cf - . | (cd "build/$slug" && tar -xf -)
	while IFS= read -r entry; do
		[ -z "$entry" ] && continue
		rm -rf "build/$slug/${entry#/}"
	done < .distignore
fi

(cd build && zip -qr "../dist/$slug.zip" "$slug")

echo "dist/$slug.zip"
