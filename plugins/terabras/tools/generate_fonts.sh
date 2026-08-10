#!/usr/bin/env bash
#
# Regenerate the self-hosted Kanit web fonts (public/fonts/*.woff2) from the
# brand identity kit. Kanit is the official Terabras typeface (SIL OFL 1.1).
#
# Source: TERABRAS_IdentidadeVisual*/4.Fonte/Kanit.zip  (kept out of git)
# Needs : fonttools + brotli  ->  pip install fonttools brotli
#
# We ship only the 5 weights the theme uses (Light/Regular/Medium/SemiBold/Bold),
# subset to Latin + Latin-Ext + common punctuation so each file is ~28KB instead
# of ~170KB (Kanit's full glyph set includes Thai, which we never render).
#
# Usage: run from the repo root:
#   bash plugins/terabras/tools/generate_fonts.sh /path/to/Kanit.zip
set -euo pipefail

ZIP="${1:-}"
if [[ -z "$ZIP" ]]; then
    ZIP="$(find TERABRAS_IdentidadeVisual* -name Kanit.zip 2>/dev/null | head -1 || true)"
fi
[[ -f "$ZIP" ]] || { echo "Kanit.zip not found. Pass its path as arg 1."; exit 1; }

OUT="plugins/terabras/public/fonts"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$OUT"

unzip -oq "$ZIP" -d "$TMP"
cp "$TMP/OFL.txt" "$OUT/Kanit-OFL.txt"

# Latin + Latin-Ext (covers PT-BR accents) + punctuation/symbols we use.
UNI="U+0000-00FF,U+0100-017F,U+0180-024F,U+2000-206F,U+2070-209F,U+20A0-20BF,U+2122,U+2212,U+2013,U+2014,U+2018,U+2019,U+201C,U+201D,U+2026"

for name in Light Regular Medium SemiBold Bold; do
    pyftsubset "$TMP/Kanit-$name.ttf" \
        --unicodes="$UNI" --layout-features='*' --flavor=woff2 \
        --output-file="$OUT/kanit-$name.woff2"
    printf '  %-10s -> %s\n' "$name" "$(du -h "$OUT/kanit-$name.woff2" | cut -f1)"
done
echo "Done. Fonts written to $OUT/"
