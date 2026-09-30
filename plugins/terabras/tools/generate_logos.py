#!/usr/bin/env python3
"""
Generate every Terabras logo asset from the brand identity kit.

This is the SINGLE SOURCE for the product's marks. The kit itself is not
committed (see /.gitignore); the generated files are. Re-run from the GLPI root
whenever the brand assets change:

    <venv>/bin/python plugins/terabras/tools/generate_logos.py

It writes two sets of files:

1. plugins/terabras/public/img/ — consumed through the `--glpi-logo-*` custom
   properties that plugins/terabras/public/css/branding.css redefines. These
   cover every page where the branding plugin is loaded.

2. public/pics/ — replacements for the upstream GLPI marks. These are the ONLY
   marks available on pages that render before the plugin can load: the setup
   wizard, the "database must be configured" screen and the error pages shown
   when the database is unreachable. They cannot be replaced by CSS, so the
   files themselves are swapped (tracked in TERABRAS_DIVERGENCE.md).

Requires Pillow.
"""
import glob
import os
import sys
import unicodedata

from PIL import Image

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", ".."))

KIT = None
for cand in glob.glob(os.path.join(ROOT, "TERABRAS_IdentidadeVisual*", "1.Logotipo", "Digital", "PNG")):
    KIT = cand
    break
if KIT is None:
    sys.exit("Brand kit not found (TERABRAS_IdentidadeVisual*/1.Logotipo/Digital/PNG). "
             "Restore the kit folder to regenerate assets.")

PLUGIN_OUT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "public", "img"))
CORE_OUT = os.path.join(ROOT, "public", "pics")
os.makedirs(PLUGIN_OUT, exist_ok=True)


def _norm(s):
    """Fold accents and case so 'Símbolo' and 'Simbolo' compare equal."""
    s = unicodedata.normalize("NFKD", s)
    return "".join(c for c in s if not unicodedata.combining(c)).lower()


def source(filename):
    """Resolve one kit file by its EXACT (accent-folded) name.

    Exact matching matters: the kit ships both 'Simbolo sem fundo.png' (the
    official two-colour mark) and 'Simbolo azul sem fundo.png' (a flat one-colour
    variant). Substring matching picked whichever the filesystem returned first,
    which is how the product ended up shipping the flat purple "T" instead of the
    official mark with its orange segment.
    """
    want = _norm(filename)
    matches = [p for p in glob.glob(os.path.join(KIT, "*.png")) if _norm(os.path.basename(p)) == want]
    if len(matches) != 1:
        sys.exit(f"Expected exactly one kit file named {filename!r}, found {len(matches)} in {KIT}")
    return matches[0]


def trim(im):
    """Crop transparent padding to the artwork's bounding box."""
    im = im.convert("RGBA")
    bbox = im.getbbox()
    return im.crop(bbox) if bbox else im


def resize_h(im, height):
    w, h = im.size
    return im.resize((max(1, round(w * height / h)), height), Image.LANCZOS)


def is_accent(r, g, b):
    """Brand orange (#FF7800): warm, red-dominant. Everything else is the body."""
    return r > 90 and r > b + 40 and g < 200


def to_white_body(im):
    """Turn the navy body white and KEEP the orange segment.

    Used for the marks shown on the navy chrome. The kit's own all-white symbol
    drops the orange segment entirely, which loses half the identity.
    """
    im = im.convert("RGBA")
    out = []
    for r, g, b, a in im.getdata():
        if a == 0:
            out.append((0, 0, 0, 0))
        elif is_accent(r, g, b):
            out.append((r, g, b, a))
        else:
            out.append((255, 255, 255, a))
    im.putdata(out)
    return im


def letterbox(im, ratio_w, ratio_h):
    """Centre the artwork on a transparent canvas of the given aspect ratio.

    GLPI's anonymous pages render the login mark through `content:` with a fixed
    width AND height, which stretches the image (object-fit defaults to `fill`).
    Baking the target aspect ratio into the file makes that stretch a no-op, so
    the mark stays undistorted even where no Terabras CSS is loaded.
    """
    w, h = im.size
    target_w, target_h = (w, round(w * ratio_h / ratio_w))
    if target_h < h:
        target_w, target_h = (round(h * ratio_w / ratio_h), h)
    canvas = Image.new("RGBA", (target_w, target_h), (0, 0, 0, 0))
    canvas.paste(im, ((target_w - w) // 2, (target_h - h) // 2), im)
    return canvas


def save(im, directory, name):
    path = os.path.join(directory, name)
    im.save(path)
    print(f"  {os.path.relpath(path, ROOT):46} {im.size[0]}x{im.size[1]}")


# --- Sources --------------------------------------------------------------
# 'Logo escrita sem fundo'  = the wordmark  (orange segment + navy lettering)
# 'Simbolo sem fundo'       = the symbol    (orange segment + navy "T")
wordmark_src = trim(Image.open(source("Logo escrita sem fundo.png")))
symbol_src = trim(Image.open(source("Simbolo sem fundo.png")))

print(f"Kit: {KIT}")

# --- Plugin assets --------------------------------------------------------
print("\nplugins/terabras/public/img/")
wordmark = resize_h(wordmark_src, 160)
wordmark_white = to_white_body(wordmark)
wordmark_login = resize_h(wordmark_src, 300)
wordmark_login_white = to_white_body(wordmark_login)
symbol = resize_h(symbol_src, 160)
symbol_white = to_white_body(symbol)

save(wordmark, PLUGIN_OUT, "wordmark-blue.png")              # --glpi-logo-dark
save(wordmark_white, PLUGIN_OUT, "wordmark-white.png")       # --glpi-logo-light
save(wordmark_login, PLUGIN_OUT, "wordmark-blue-login.png")  # --glpi-logo-dark-login
save(wordmark_login_white, PLUGIN_OUT, "wordmark-white-login.png")
save(symbol, PLUGIN_OUT, "symbol-blue.png")                  # --glpi-logo-dark-reduced
save(symbol_white, PLUGIN_OUT, "symbol-white.png")           # --glpi-logo-light-reduced


def write_favicon(src, directory, name="favicon.ico"):
    square = letterbox(src, 1, 1)
    square.save(os.path.join(directory, name), sizes=[(16, 16), (32, 32), (48, 48), (64, 64)])
    print(f"  {os.path.relpath(os.path.join(directory, name), ROOT):46} 16/32/48/64")


write_favicon(symbol, PLUGIN_OUT)

# --- Core mark replacements ----------------------------------------------
# GLPI's own naming: `logo-GLPI-*` is the WORDMARK, `logo-G-*` the reduced
# symbol. Mapping the symbol onto the wordmark slots is what put a bare "T" on
# the "database must be configured" screen, where the wordmark belongs.
print("\npublic/pics/ (upstream mark replacements)")
logos_dir = os.path.join(CORE_OUT, "logos")

# 100 = inline marks, consumed as <img>/background inside the app.
save(wordmark, logos_dir, "logo-GLPI-100-black.png")
save(wordmark, logos_dir, "logo-GLPI-100-grey.png")
save(wordmark_white, logos_dir, "logo-GLPI-100-white.png")

# 250 = the large login/anonymous mark, rendered through `content:` at a fixed
# 200x110 box — hence the letterboxing.
save(letterbox(wordmark_login, 200, 110), logos_dir, "logo-GLPI-250-black.png")
save(letterbox(wordmark_login, 200, 110), logos_dir, "logo-GLPI-250-grey.png")
save(letterbox(wordmark_login_white, 200, 110), logos_dir, "logo-GLPI-250-white.png")

# Reduced marks (collapsed menu rail).
save(letterbox(symbol, 1, 1), logos_dir, "logo-G-100-black.png")
save(letterbox(symbol, 1, 1), logos_dir, "logo-G-100-grey.png")
save(letterbox(symbol_white, 1, 1), logos_dir, "logo-G-100-white.png")

write_favicon(symbol, CORE_OUT)

print("\nDone.")
