# Brand assets

`hrx-logo-source.png` is the master artwork. Everything else is generated
from it — regenerate rather than editing the outputs by hand:

| Output | How it is made |
|---|---|
| `public/assets/img/logo.png` | source resized to 500px wide |
| `public/assets/img/logo-white.png` | source's alpha channel painted white, for dark backgrounds |
| `public/assets/img/logo-mark.png` | the asterisk mark alone, squared — used by the collapsed sidebar |
| `public/favicon.ico` | the same mark at 16–256px |

The previous logo set is kept in `public/assets/img/_logo_backup_2026-07-22/`.
