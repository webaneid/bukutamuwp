#!/usr/bin/env bash
#
# Build & publish rilis plugin ke GitHub Releases, sebagai asset ZIP yang bersih & benar
# (folder "bukutamu/" berisi HANYA file runtime — bukan tooling dev seperti node_modules,
# src/, package*.json, atau file repo seperti .git/README.md/CLAUDE.md).
#
# Tanpa script ini, `gh release create` tanpa asset akan membuat GitHub memakai zip source
# otomatisnya sendiri — folder di dalamnya bernama "bukutamuwp-{versi}", BUKAN "bukutamu", yang
# bikin sistem auto-update (includes/class-updater.php) berisiko salah kenali sebagai plugin
# terpisah setelah update berikutnya. Lihat CLAUDE.md > Sistem Update Plugin.
#
# Pemakaian: jalankan dari root plugin (atau dari mana saja, script ini cd sendiri).
#   bin/release.sh
#
# Prasyarat: BUKUTAMU_VERSION di bukutamu.php SUDAH dinaikkan & di-push ke main dulu.

set -euo pipefail

cd "$(dirname "$0")/.."

VERSION=$(grep -m1 "define( 'BUKUTAMU_VERSION'" bukutamu.php | sed -E "s/.*'([0-9]+\.[0-9]+\.[0-9]+)'.*/\1/")
TAG="v${VERSION}"

if [ -z "$VERSION" ]; then
	echo "Gagal membaca BUKUTAMU_VERSION dari bukutamu.php" >&2
	exit 1
fi

echo "Membangun rilis Buku Tamu versi ${VERSION} (tag ${TAG})..."

npm run build

BUILD_DIR=$(mktemp -d)
TARGET="${BUILD_DIR}/bukutamu"
mkdir -p "$TARGET"

# Hanya file runtime yang dibutuhkan plugin untuk jalan di WordPress.
cp bukutamu.php uninstall.php "$TARGET/"
cp -R acf-json assets build includes templates "$TARGET/"

ZIP_PATH="${BUILD_DIR}/bukutamu.zip"
( cd "$BUILD_DIR" && zip -rq "$ZIP_PATH" bukutamu -x '*.DS_Store' )

echo ""
echo "Isi zip (harus semua di dalam folder bukutamu/, tanpa node_modules/src/package.json):"
unzip -l "$ZIP_PATH"

if gh release view "$TAG" >/dev/null 2>&1; then
	echo ""
	echo "Release ${TAG} sudah ada — upload/ganti asset zip-nya..."
	gh release upload "$TAG" "$ZIP_PATH" --clobber
else
	echo ""
	echo "Membuat release baru ${TAG}..."
	gh release create "$TAG" "$ZIP_PATH" --title "$TAG" --generate-notes
fi

echo ""
echo "Selesai: https://github.com/webaneid/bukutamuwp/releases/tag/${TAG}"
