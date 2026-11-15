#!/usr/bin/env python3
"""
Builds assets/css/themes/themes_bundle.css and includes/themes.php from the
palettes below, and refuses to write anything if a text/background pair falls
under WCAG AA contrast (4.5:1 for text, 3:1 for control borders).

Run:  python3 tools/build_themes.py          (build)
      python3 tools/build_themes.py --check  (verify only, for CI)
"""
import sys, os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

LIGHT_STATUS = dict(success="#146c43", danger="#b02a37", warning="#8a5a00", info="#087990")
DARK_STATUS = dict(success="#5fd39a", danger="#ff8f8a", warning="#f2c261", info="#6fd3ea")

def theme(name, mode, **k):
    base = dict(name=name, mode=mode, surface="#ffffff" if mode == "light" else None)
    base.update(DARK_STATUS if mode == "dark" else LIGHT_STATUS)
    base.update(k)
    base.setdefault("topbar", base["surface"])
    base.setdefault("on_primary", "#ffffff")
    return base

THEMES = {
    "default": theme("Classic Blue", "light", bg="#f4f6fb", surface2="#eef1f7", text="#162033", muted="#56627a",
                     border="#d5dbe6", control="#7c879a", primary="#1f5fd0", primary_hover="#184fb0",
                     sb_bg="#ffffff", sb_text="#26324a", sb_muted="#5d6a82", sb_active_bg="#e3ecfd", sb_active_text="#174ea6", sb_border="#d5dbe6"),
    "dark": theme("Dark Midnight", "dark", bg="#0f141c", surface="#18202d", surface2="#202b3b", text="#e8edf5", muted="#a7b2c5",
                  border="#2c394d", control="#6f7f99", primary="#7aa7ff", primary_hover="#9bbcff", on_primary="#0b1220",
                  sb_bg="#131a25", sb_text="#d5dcea", sb_muted="#9aa6ba", sb_active_bg="#22324d", sb_active_text="#cfe0ff", sb_border="#2c394d"),
    "ocean": theme("Ocean Breeze", "light", bg="#eef6f9", surface2="#e3eff4", text="#10283a", muted="#4c6577",
                   border="#cfdfe7", control="#738997", primary="#0a6c9c", primary_hover="#085a83",
                   sb_bg="#0b4f72", sb_text="#eaf6fb", sb_muted="#b4d6e6", sb_active_bg="#0e6a97", sb_active_text="#ffffff", sb_border="#0b4f72"),
    "forest": theme("Forest Green", "light", bg="#f1f6f1", surface2="#e6efe7", text="#17291c", muted="#4f6455",
                    border="#d2e0d4", control="#75897a", primary="#1f7a3d", primary_hover="#186431",
                    sb_bg="#173d25", sb_text="#eaf5ec", sb_muted="#b3d2ba", sb_active_bg="#22583a", sb_active_text="#ffffff", sb_border="#173d25"),
    "sunset": theme("Sunset Orange", "light", bg="#fdf5ef", surface2="#f8eadf", text="#33201a", muted="#6d5248",
                    border="#ecd9cc", control="#a08878", primary="#b8431a", primary_hover="#9c3814",
                    sb_bg="#5a2413", sb_text="#fdeee6", sb_muted="#e3bba9", sb_active_bg="#7c341c", sb_active_text="#ffffff", sb_border="#5a2413"),
    "royal": theme("Royal Purple", "light", bg="#f5f3fb", surface2="#ebe7f7", text="#221a3a", muted="#5b5278",
                   border="#dcd6ee", control="#8d85a8", primary="#5b3cc4", primary_hover="#4b30a6",
                   sb_bg="#2c1d66", sb_text="#efeafd", sb_muted="#c2b6ea", sb_active_bg="#432e94", sb_active_text="#ffffff", sb_border="#2c1d66"),
    "slate": theme("Slate Gray", "light", bg="#f1f3f5", surface2="#e6e9ed", text="#1c232b", muted="#55606c",
                   border="#d3d9df", control="#7a8591", primary="#3d5166", primary_hover="#314253",
                   sb_bg="#232c36", sb_text="#e9edf1", sb_muted="#aeb8c2", sb_active_bg="#36434f", sb_active_text="#ffffff", sb_border="#232c36"),
    "rose": theme("Rose Pink", "light", bg="#fdf3f6", surface2="#f9e6ec", text="#36161f", muted="#71505a",
                  border="#f0d5dd", control="#9a7c88", primary="#b3214f", primary_hover="#981a42",
                  sb_bg="#ffffff", sb_text="#3a1a24", sb_muted="#71505a", sb_active_bg="#fbe1e9", sb_active_text="#8f1a40", sb_border="#f0d5dd"),
    "coffee": theme("Coffee Brown", "light", bg="#f8f3ee", surface2="#f0e7de", text="#2e2118", muted="#6a584b",
                    border="#e3d6c9", control="#998777", primary="#7a4a22", primary_hover="#663d1b",
                    sb_bg="#3b2717", sb_text="#f6ece3", sb_muted="#d4bfae", sb_active_bg="#56391f", sb_active_text="#ffffff", sb_border="#3b2717"),
    "cyber": theme("Cyber Neon", "dark", bg="#0a0e17", surface="#121a2a", surface2="#1a2438", text="#e6f1ff", muted="#9fb3cc",
                   border="#24344f", control="#62799c", primary="#2ee6c8", primary_hover="#62f0d9", on_primary="#04211c",
                   sb_bg="#0d1422", sb_text="#d6e4f7", sb_muted="#93a8c4", sb_active_bg="#123b3f", sb_active_text="#7ff5e1", sb_border="#24344f"),
}

def rgb(h):
    h = h.lstrip("#")
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))

def lum(h):
    def ch(v):
        v /= 255
        return v / 12.92 if v <= 0.03928 else ((v + 0.055) / 1.055) ** 2.4
    r, g, b = (ch(v) for v in rgb(h))
    return 0.2126 * r + 0.7152 * g + 0.0722 * b

def contrast(a, b):
    la, lb = sorted((lum(a), lum(b)), reverse=True)
    return (la + 0.05) / (lb + 0.05)

# (foreground key, background key, minimum ratio, what it is)
PAIRS = [
    ("text", "bg", 4.5, "body text on page"), ("text", "surface", 4.5, "body text on cards"), ("text", "surface2", 4.5, "text on subtle panels"),
    ("muted", "bg", 4.5, "muted text on page"), ("muted", "surface", 4.5, "muted text on cards"), ("muted", "surface2", 4.5, "muted text on subtle panels"),
    ("on_primary", "primary", 4.5, "primary button label"), ("on_primary", "primary_hover", 4.5, "primary button label (hover)"),
    ("primary", "surface", 4.5, "links on cards"), ("primary", "bg", 4.5, "links on page"),
    ("sb_text", "sb_bg", 4.5, "sidebar text"), ("sb_muted", "sb_bg", 4.5, "sidebar section labels"),
    ("sb_active_text", "sb_active_bg", 4.5, "active sidebar item"), ("sb_text", "sb_active_bg", 4.5, "hovered sidebar item"),
    ("text", "topbar", 4.5, "top bar text"),
    ("control", "surface", 3.0, "input borders"), ("control", "bg", 3.0, "input borders on page"),
    ("success", "surface", 4.5, "success text"), ("danger", "surface", 4.5, "danger text"),
    ("warning", "surface", 4.5, "warning text"), ("info", "surface", 4.5, "info text"),
    ("success", "bg", 4.5, "success text on page"), ("danger", "bg", 4.5, "danger text on page"),
]

def check():
    failures = []
    for tid, t in THEMES.items():
        for fg, bg, need, what in PAIRS:
            c = contrast(t[fg], t[bg])
            if c < need:
                failures.append(f"{tid}: {what} ({t[fg]} on {t[bg]}) is {c:.2f}:1, needs {need}:1")
    return failures

def css_for(tid, t):
    r = lambda k: ", ".join(str(v) for v in rgb(t[k]))
    sel = ":root, [data-theme=\"default\"]" if tid == "default" else f"[data-theme=\"{tid}\"]"
    return f"""/* {t['name']} ({t['mode']}) */
{sel} {{
  color-scheme: {t['mode']};
  --bm-bg: {t['bg']}; --bm-surface: {t['surface']}; --bm-surface-2: {t['surface2']};
  --bm-text: {t['text']}; --bm-muted: {t['muted']};
  --bm-border: {t['border']}; --bm-control-border: {t['control']};
  --bm-primary: {t['primary']}; --bm-primary-hover: {t['primary_hover']}; --bm-on-primary: {t['on_primary']};
  --bm-primary-rgb: {r('primary')};
  --bm-success: {t['success']}; --bm-danger: {t['danger']}; --bm-warning: {t['warning']}; --bm-info: {t['info']};
  --bm-topbar-bg: {t['topbar']};
  --bm-sb-bg: {t['sb_bg']}; --bm-sb-text: {t['sb_text']}; --bm-sb-muted: {t['sb_muted']};
  --bm-sb-active-bg: {t['sb_active_bg']}; --bm-sb-active-text: {t['sb_active_text']}; --bm-sb-border: {t['sb_border']};

  /* Bootstrap reads these */
  --bs-body-bg: {t['bg']}; --bs-body-bg-rgb: {r('bg')};
  --bs-body-color: {t['text']}; --bs-body-color-rgb: {r('text')};
  --bs-emphasis-color: {t['text']}; --bs-emphasis-color-rgb: {r('text')};
  --bs-heading-color: {t['text']};
  --bs-secondary-color: {t['muted']}; --bs-secondary-color-rgb: {r('muted')};
  --bs-tertiary-color: {t['muted']};
  --bs-secondary-bg: {t['surface2']}; --bs-secondary-bg-rgb: {r('surface2')};
  --bs-tertiary-bg: {t['surface2']}; --bs-tertiary-bg-rgb: {r('surface2')};
  --bs-border-color: {t['border']}; --bs-border-color-translucent: {t['border']};
  --bs-primary: {t['primary']}; --bs-primary-rgb: {r('primary')};
  --bs-link-color: {t['primary']}; --bs-link-color-rgb: {r('primary')};
  --bs-link-hover-color: {t['primary_hover']}; --bs-link-hover-color-rgb: {r('primary_hover')};
  --bs-success-text-emphasis: {t['success']}; --bs-danger-text-emphasis: {t['danger']};
  --bs-warning-text-emphasis: {t['warning']}; --bs-info-text-emphasis: {t['info']};

  /* Names older pages still use */
  --primary-color: {t['primary']}; --primary-hover: {t['primary_hover']};
  --app-bg: {t['bg']}; --card-bg: {t['surface']}; --topbar-bg: {t['topbar']};
  --text-color: {t['text']}; --surface-text: {t['text']}; --muted-color: {t['muted']}; --border-color: {t['border']};
  --sidebar-bg: {t['sb_bg']}; --sidebar-color: {t['sb_text']}; --sidebar-icon-color: {t['sb_text']};
  --sidebar-active-bg: {t['sb_active_bg']}; --sidebar-active-color: {t['sb_active_text']};
  --success-color: {t['success']}; --danger-color: {t['danger']}; --warning-color: {t['warning']}; --info-color: {t['info']};
}}
"""

def main():
    failures = check()
    if failures:
        print("Contrast check FAILED:")
        for f in failures:
            print("  -", f)
        sys.exit(1)
    print(f"Contrast check passed: {len(THEMES)} themes x {len(PAIRS)} colour pairs.")
    if "--check" in sys.argv:
        return
    out = ["/* Generated by tools/build_themes.py. Edit the palettes there, not this file. */\n"]
    out += [css_for(tid, t) for tid, t in THEMES.items()]
    with open(os.path.join(ROOT, "assets/css/themes/themes_bundle.css"), "w") as f:
        f.write("\n".join(out))
    rows = ",\n".join(
        f"    '{tid}' => ['name' => '{t['name']}', 'mode' => '{t['mode']}', 'color' => '{t['primary']}', 'bg' => '{t['bg']}', 'sidebar' => '{t['sb_bg']}']"
        for tid, t in THEMES.items())
    with open(os.path.join(ROOT, "includes/themes.php"), "w") as f:
        f.write("<?php\n// Generated by tools/build_themes.py. Do not edit by hand.\nreturn [\n" + rows + ",\n];\n")
    print("Wrote themes_bundle.css and includes/themes.php")

if __name__ == "__main__":
    main()
