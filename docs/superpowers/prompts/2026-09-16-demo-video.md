# Retake the demo project in a fresh session

Paste this into a new Claude Code session started in `/Users/Lucas/Documents/JHMG-Local/jhmg-elementor-to-divi5-demo-site` (or say "Read docs/superpowers/prompts/2026-09-16-demo-video.md and do it").

---

You are continuing work on the JHMG "Converter for Elementor to Divi 5" demo site (Ferncourt Coworking), built to record the product demo video. Read this whole prompt, then `demo/README.md`, `docs/known-issues.md` and `docs/superpowers/specs/2026-09-15-demo-site-design.md` before touching anything.

## Where things stand (2026-09-16)

- **Released.** Free plugin 3.0.2 is live on wordpress.org (SVN r3698436, tag `3.0.2`); Pro 1.2.1 is on divi5lab.com. Both came from branch `fix/divi-rendering-2026-09`, merged into `fix/correctness-pass-2026-08` and `main` (same commit). `main` now contains `demo/`.
- **Checkouts.** `jhmg-elementor-to-divi5` (main checkout, on `fix/correctness-pass-2026-08`, with Lucas's own uncommitted files: a licence-client docblock, e2e screenshots, `references/…/.svn` changes — leave them alone). `jhmg-elementor-to-divi5-demo-site` (git worktree, on `fix/divi-rendering-2026-09`, identical content; the demo stack's bind mounts point here). The `demo-site` branch is behind and can be deleted.
- **The converter now renders correctly in Divi 5.7.4.** Every item in `docs/known-issues.md` "Fixed in free 3.0.2 and Pro 1.2.1" has a schema test (`tests/support/DiviModuleSchema.php`, run on every fixture, add-on probe, both Kit Library kits and the demo pages) and, where the demo shows it, a render assertion (`demo/tests/render.spec.ts`, 23 tests). `demo/verify.sh` runs six checks and passes: versions, pages, conversions, converted (screenshots), render, reset.
- **The demo stack** (`demo/build.sh`, port 8040, admin / ferncourt-demo) was rebuilt on 2026-09-16 with the current seeds: About uses the EAEL progress bar again, Home's CTA has body text, Events' post grid filters by the events category, Memberships' pricing tables have subtitles. The snapshot in `demo/snapshots/` is from that build. `demo/reset.sh` restores it in about ten seconds.
- **Kit comparison.** `demo/tools/kit-compare.sh references/kits/ceramic-studio.zip` imports a Kit Library kit, screenshots the originals and the Divi drafts into `demo/output/kit-screenshots/` (gitignored) and resets. The Ceramic Studio pages match their originals now; one carousel image wraps to a second row (a column-gap difference, not a rendering bug).

## Things to know before recording

1. The demo container's WordPress core is not pinned; it was 7.1 at the last build. If a Playwright login ever hangs after a reset, the restored database is behind the core: `demo/reset.sh && demo/wp core update-db && demo/snapshot.sh` once.
2. The Pro licence key still has to be entered by hand before recording (Tools → Elementor → Divi 5 → License), then `demo/snapshot.sh`, then `demo/verify.sh` must end with `All checks passed`.
3. Home's "240+" members counter converts to `240`: Divi's number counter has no suffix other than `%`, and the conversion report says so. Change the copy if the video should not show a dropped "+".
4. Under Divi, Header Footer Elementor is kept from loading by `demo/mu-plugins/ferncourt-demo.php`; on a real site the plugin now shows an admin notice telling the user to deactivate HFE. The Theme Builder header and footer are built by `demo/lib/theme-builder.php` (no manual repair any more).
5. The conversion report's "not carried over" list now names counter affixes, gallery filter buttons and captions, unsupported social networks and post-grid filters. Worth showing in the video: it is what "honest conversion" looks like.
6. WP-CLI `eval-file` takes positional arguments only (`commit-conversions.php probes`); `--flags` are rejected.

## Suggested next steps for the video session

- Run `demo/reset.sh && demo/verify.sh` first; read `demo/output/screenshots/<page>-elementor.png` beside `<page>-divi.png` and pick the pages that tell the story best (Home, Spaces, Memberships).
- Follow `demo/README.md` → "Recording flow". The Pro kit ZIP for the on-camera upload is `demo/output/ferncourt-kit.zip` (rebuilt by `demo/build.sh`).
- If anything renders wrong that the render check does not cover, add an assertion to `demo/tests/render.spec.ts` (and a probe widget to `demo/content/probes/core-widgets.php` if the site does not use that widget) before fixing the converter, then follow `docs/superpowers/plans/2026-09-15-divi-rendering-fixes.md`'s task shape: failing test, smallest change, proof by render.
- Do not run `wp plugin delete` / `wp theme delete` against the bind-mounted plugins or Divi in the demo stack, and keep port 8040 as the only demo stack.
