# Brand: the plugin's look and its WordPress.org images

The plugin follows the theblue.email site's design system, "Ultramaryna" (the marketing
theme's `src/styles/settings/_tokens.scss` in `marziolek/theblue.email`):

- **The stage:** one deep ultramarine `#0f1f96`, in tones of itself (`#0a1568`), with
  white as the accent and periwinkle `#c6cfff` for quieter text.
- **Paper:** white `#ffffff` where people read. Ink `#0a1238`, slate `#55607f`,
  rules `#dde3f3`, a pale wash `#f4f6fd`.
- **Type:** Instrument Sans for everything. Display sizes are condensed through the
  width axis (`font-stretch: 80%`), semibold, with tracking around -0.025em to -0.03em.
- **Shapes:** pill buttons, 10px fields, 16px panels. Flat: no shadows, no gradients.
- **The wordmark** in `assets/images/` is the site's outlined logo, unchanged:
  "theblue" white with ".email" periwinkle on the stage, and "theblue" ultramarine with
  ".email" ink on paper.

Where it is used:

| Surface | File | The one bold thing |
|---|---|---|
| The theblue.email page | `assets/admin/settings.css` | The connection set on the stage like the site's stats band; when not connected, the whole job (title, two steps, key field) sits on the stage |
| Block preview in the editor | `blocks/form/editor.css` | The form's name in the condensed display face on a paper card |
| Block icon | `blocks/form/editor.js` | An outlined field and a pill button: at 24px a signup form reads where the corgi would not |
| WordPress.org banner | `docs/brand/banner.html` | The site's hero headline treatment, with a placed form running off the bottom edge |
| WordPress.org icon | `.wordpress-org/icon.svg` | The theblue.email corgi on the stage, cropped to head and chest and running off the bottom edge like the banner's form |

The corgi is the platform's character (the marketing theme's `assets/img/blue-corgi.svg`,
used on its 404 page). `icon.svg` embeds it unchanged inside a 256x256 ultramarine square,
cropped with a nested `viewBox="-4 -3 44 44"`; tighter crops pinch the ears against the top
edge, looser ones shrink the face below what reads at 32px. If the character is redrawn,
paste its new inner markup into that nested `<svg>` and re-render the PNGs.

Instrument Sans ships in `assets/fonts/` under the SIL Open Font License (`OFL.txt` there).
It is loaded from the plugin, never from a font CDN, and only on the plugin's settings page
and in the block editor.

## Re-rendering the WordPress.org images

The banner is one page at 772x250 CSS pixels, captured at 2x and at 1x. The icons are
`icon.svg` captured at 256 and 128. From the repository root, with Chrome installed:

```
CHROME="/c/Program Files/Google/Chrome/Application/chrome.exe"   # or google-chrome
SHOT='--headless --disable-gpu --hide-scrollbars --allow-file-access-from-files --virtual-time-budget=3000'
ROOT="file:///$(pwd -W 2>/dev/null || pwd)"

"$CHROME" $SHOT --force-device-scale-factor=2 --window-size=772,250 --screenshot=.wordpress-org/banner-1544x500.png "$ROOT/docs/brand/banner.html"
"$CHROME" $SHOT --force-device-scale-factor=1 --window-size=772,250 --screenshot=.wordpress-org/banner-772x250.png  "$ROOT/docs/brand/banner.html"
"$CHROME" $SHOT --force-device-scale-factor=1 --window-size=256,256 --screenshot=.wordpress-org/icon-256x256.png    "$ROOT/.wordpress-org/icon.svg"
"$CHROME" $SHOT --force-device-scale-factor=0.5 --window-size=256,256 --screenshot=.wordpress-org/icon-128x128.png "$ROOT/.wordpress-org/icon.svg"
```

Check every image's dimensions afterwards (`file .wordpress-org/*.png`).
`docs/brand/fonts/` holds Source Code Pro (OFL) for the shortcode in the banner; it is not
part of the plugin.
