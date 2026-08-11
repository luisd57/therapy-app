---
paths:
  - landing/src/**/*.astro
  - landing/src/**/*.svelte
  - landing/src/**/*.ts
---
# Landing Page Conventions (Astro + Svelte)

## Tech Stack
- Astro 5.7 (Islands Architecture - static HTML with selective client hydration)
- Svelte 5 (interactive island components)
- Tailwind CSS 3.4

## Project Structure
```
src/
├── components/
│   ├── astro/         # Server-rendered (.astro) - layout, bio, services
│   └── svelte/        # Client-hydrated (.svelte) - interactive flows
├── content/site/      # Content Collections (markdown)
├── layouts/           # Astro layout templates
├── pages/             # Astro page routes
├── services/          # Typed API client functions
├── types/             # TypeScript interfaces
└── utils/             # Date/time helpers
```

## Conventions
- PascalCase filenames, one component per file
- `.astro` for static/server content; `.svelte` for interactive islands
- API calls centralized in `services/`
- Dates: display in user's local timezone, send in ISO-8601 UTC
- Client-side validation + always handle server error responses gracefully
