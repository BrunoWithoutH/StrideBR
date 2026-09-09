# Social previews

The versioned default Open Graph asset is
`public/assets/img/branding/stridebr-og-20260909.png` (1200×630). Its SVG source
is kept in `stridebr-og.svg`.

After deployment, verify that the home page exposes the versioned asset:

```sh
curl -s https://stridebr.com.br/ \
  | grep -E 'og:image|twitter:image'
```

Both tags must point to
`https://stridebr.com.br/assets/img/branding/stridebr-og-20260909.png`. Also
open that URL directly to confirm the image is publicly available.

GitHub Social Preview is configured in repository settings, not in HTML. Upload
`stridebr-github-social-preview.png` (1280×640, rendered from the SVG master) in
**GitHub → repository → Settings → Social preview**.
