# Project guidance

- Supporting documents live in `docs/`. Read `docs/DESIGN.md` and
  `docs/UX-CONTRACT.md` for UI work, and `docs/ARCHITECTURE.md` for project scope.

## Known deployment surprises

- Prism's native show-invisibles hook crashes on optional grammar entries, including
  Pascal's. `prism-rows.js` marks rendered whitespace instead; keep grammar imports
  driven by the `prism.config.json` registry and Prism's dependency resolver.

- Leave routes uncached for subdirectory mounts with the current Laravel version.
  The compiled matcher trims `/websvn/` before resolving the base URL and returns
  405 with "Supported methods: HEAD" for the homepage. Use `route:clear`, retain
  config/view caching, and use `optimize --except=routes` if needed. See DEPLOYMENT.md.
