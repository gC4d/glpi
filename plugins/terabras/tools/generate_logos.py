#!/usr/bin/env python3
"""
Generate the Terabras app logo assets (header + login + favicon) from the
brand identity kit PNGs.

Source kit is NOT committed to git (see /.gitignore). Run this from the GLPI
root whenever the brand assets change:

    <venv>/bin/python plugins/terabras/tools/generate_logos.py

Outputs are written to plugins/terabras/public/img/ and are committed to git.
Requires Pillow.
"""
import glob
import os
import sys

from PIL import Image

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", ".."))
KIT = None
for cand in glob.glob(os.path.join(ROOT, "TERABRAS_IdentidadeVisual*", "1.Logotipo", "Digital", "PNG")):
    KIT = cand
    break
if KIT is None:
    sys.exit("Brand kit not found (TERABRAS_IdentidadeVisual*/1.Logotipo/Digital/PNG). "
             "Restore the kit folder to regenerate assets.")

OUT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "public", "img"))
os.makedirs(OUT, exist_ok=True)

ORANGE = (255, 120, 0)  # #FF7800 brand accent — preserved when recoloring


def find(name_contains):
    """Locate a source PNG by a substring (accent-insensitive-ish)."""
    for p in glob.glob(os.path.join(KIT, "*.png")):
        base = os.path.basename(p).lower()
        if all(tok in base for tok in name_contains):
            return p
    sys.exit(f"Source not found for tokens {name_contains} in {KIT}")


def trim(im):
    """Crop transparent padding to the logo's bounding box."""
    im = im.convert("RGBA")
    bbox = im.getbbox()
    return im.crop(bbox) if bbox else im


def resize_h(im, height):
    w, h = im.size
    return im.resize((max(1, round(w * height / h)), height), Image.LANCZOS)


def is_orange(r, g, b):
    # Accent pixels: warm (red high, blue low). Everything else = the wordmark body.
    return r > 90 and r > b + 40 and g < 200


def recolor_body_to_white(im):
    """Turn the blue wordmark body white, keep the orange accent, keep alpha."""
    im = im.convert("RGBA")
    px = list(im.getdata())
    out = []
    for r, g, b, a in px:
        if a == 0:
            out.append((0, 0, 0, 0))
        elif is_orange(r, g, b):
            out.append((r, g, b, a))
        else:
            out.append((255, 255, 255, a))
    im.putdata(out)
    return im


def save(im, name):
    path = os.path.join(OUT, name)
    im.save(path)
    print(f"  {name:28} {im.size[0]}x{im.size[1]}")


# --- Sources -------------------------------------------------------------
wordmark_blue_src = trim(Image.open(find(["logo", "escrita", "sem fundo"])))
symbol_blue_src = trim(Image.open(find(["simbolo", "sem fundo"])))
symbol_white_src = trim(Image.open(find(["branco", "sem fundo"])))

print(f"Kit: {KIT}")
print(f"Out: {OUT}")

# --- Header logos (crisp at ~40px tall, exported at 160 for retina) -------
wm_blue = resize_h(wordmark_blue_src, 160)
wm_white = recolor_body_to_white(wm_blue)
sym_blue = resize_h(symbol_blue_src, 160)
sym_white = resize_h(symbol_white_src, 160)

save(wm_blue, "wordmark-blue.png")     # --glpi-logo-dark (light backgrounds)
save(wm_white, "wordmark-white.png")   # --glpi-logo-light (dark sidebar / dark theme)
save(sym_blue, "symbol-blue.png")      # --glpi-logo-dark-reduced
save(sym_white, "symbol-white.png")    # --glpi-logo-light-reduced

# --- Login logos (larger) -------------------------------------------------
save(resize_h(wordmark_blue_src, 300), "wordmark-blue-login.png")
save(recolor_body_to_white(resize_h(wordmark_blue_src, 300)), "wordmark-white-login.png")

# --- Favicon (from the symbol) -------------------------------------------
fav = symbol_blue_src.copy()
w, h = fav.size
side = max(w, h)
canvas = Image.new("RGBA", (side, side), (0, 0, 0, 0))
canvas.paste(fav, ((side - w) // 2, (side - h) // 2), fav)
canvas.save(os.path.join(OUT, "favicon.ico"),
            sizes=[(16, 16), (32, 32), (48, 48), (64, 64)])
print("  favicon.ico                  16/32/48/64")
print("Done.")
