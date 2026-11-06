#!/usr/bin/env bash
# Builds dist/theblue-email-forms.zip: the plugin as WordPress installs it,
# without anything listed in .distignore. Works with or without rsync and zip
# (Git Bash on Windows has neither), and with .distignore checked out with
# Windows line endings.
set -euo pipefail

cd "$(dirname "$0")/.."
slug=theblue-email-forms
rm -rf build dist
mkdir -p "build/$slug" dist

# .distignore without carriage returns, blank lines or comments.
patterns=()
while IFS= read -r entry || [ -n "$entry" ]; do
	entry="${entry%$'\r'}"
	[ -z "$entry" ] && continue
	[ "${entry:0:1}" = "#" ] && continue
	patterns+=("$entry")
done < .distignore

if command -v rsync > /dev/null; then
	rsync -a --exclude-from=<(printf '%s\n' "${patterns[@]}") ./ "build/$slug/"
else
	# Leave the ignored paths out of the copy instead of deleting them after.
	excludes=()
	for entry in "${patterns[@]}"; do
		if [ "${entry:0:1}" = "/" ]; then
			excludes+=("--exclude=.${entry}")
		else
			excludes+=("--exclude=${entry}")
		fi
	done
	tar "${excludes[@]}" -cf - . | (cd "build/$slug" && tar -xf -)
fi

# Never ship development files, whatever the copy above did.
for path in .git vendor node_modules tests bin docs build dist; do
	if [ -e "build/$slug/$path" ]; then
		echo "build/$slug/$path should not be in the build; check .distignore" >&2
		exit 1
	fi
done

if command -v zip > /dev/null; then
	(cd build && zip -qr "../dist/$slug.zip" "$slug")
else
	php -r '
		$zip = new ZipArchive();
		if ( true !== $zip->open( $argv[2], ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			fwrite( STDERR, "Cannot create {$argv[2]}\n" );
			exit( 1 );
		}
		$base  = realpath( $argv[1] );
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			$local = str_replace( "\\", "/", substr( $file->getPathname(), strlen( dirname( $base ) ) + 1 ) );
			$zip->addFile( $file->getPathname(), $local );
		}
		$zip->close();
	' "build/$slug" "dist/$slug.zip"
fi

echo "dist/$slug.zip ($(du -h "dist/$slug.zip" | cut -f1))"
