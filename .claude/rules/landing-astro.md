---
paths:
  - landing/src/**/*.astro
  - landing/src/**/*.svelte
  - landing/src/**/*.ts
---
# Landing Page Conventions (Astro + Svelte)

## Conventions
- PascalCase filenames, one component per file
- `.astro` for static/server content; `.svelte` for interactive islands
- API calls centralized in `services/`
- Dates: display in user's local timezone, send in ISO-8601 UTC
- Client-side validation + always handle server error responses gracefully
- `config.ts` holds values the app assumes before the API reports them
