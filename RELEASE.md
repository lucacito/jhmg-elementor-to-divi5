# Release checklist

Two plugins ship from this repo on separate channels:

| Plugin | Channel | Version source |
|---|---|---|
| `jhmg-converter-for-elementor-to-divi` (free) | WordPress.org SVN | plugin header + `EDC_PLUGIN_VERSION` + readme `Stable tag` |
| `jhmg-converter-for-elementor-to-divi-pro` (Pro) | divi5lab.com | plugin header + `EDCP_PLUGIN_VERSION` |

`wporg-svn/` is a working copy, not part of this repo — it is gitignored and
recreated on demand with `svn co https://plugins.svn.wordpress.org/jhmg-converter-for-elementor-to-divi/ wporg-svn`.

---

## 1. Before you start

- [ ] Working tree clean for `plugin/`, `tests/` and `fixtures/`.
- [ ] Decide the version number. The free plugin carries it in **three** places
      that must agree, and nothing checks them for you:
      - `plugin/jhmg-converter-for-elementor-to-divi/jhmg-converter-for-elementor-to-divi.php` — `Version:` header
      - the same file — `EDC_PLUGIN_VERSION`
      - `plugin/jhmg-converter-for-elementor-to-divi/readme.txt` — `Stable tag:`

## 2. Tests

```bash
npm test            # phpunit + playwright
```

- [ ] PHPUnit green.
- [ ] Playwright green. It drives the Docker WordPress at `localhost:8000`; if it
      is not up, `scripts/docker/setup_wp.sh` builds and configures it. The specs
      log in as `admin`/`admin` and the setup script re-asserts that password on
      every run, because a long-lived container drifts and the failure then looks
      like an unrelated selector timeout.

## 3. Translations

```bash
npm run i18n        # regenerates both .pot files
```

- [ ] Re-run whenever user-facing strings changed. Free's template is delivered
      through translate.wordpress.org; Pro's ships inside the plugin and is
      loaded by its own `load_plugin_textdomain()` call.

## 4. readme.txt

- [ ] `Stable tag` matches the version being released.
- [ ] `Tested up to` matches the WordPress version the Playwright suite actually
      ran against — check it, do not carry the old value forward:
      `docker exec -i $(docker compose ps -q wordpress) wp core version --allow-root`
- [ ] Changelog entry written for this version.
- [ ] Upgrade Notice entry written — this is what existing users see in wp-admin.
- [ ] Any counted claim in the description re-counted rather than reused:
      ```bash
      php scripts/widget-coverage.php /path/to/elementor /path/to/elementor-pro
      ```
      It prints how many widget types map to a real Divi module and how many
      only emit a labelled placeholder — state **both**, never the sum. The
      sum is the number that produced the old "140+ widget mappings" claim,
      which also counted the `e-*` legacy fixture aliases Elementor never
      emits. Given an Elementor tree it also lists that release's widgets the
      converter does not handle.

      Elementor Pro is a licensed download and is not in this repo; without a
      path to it the Pro figure prints UNKNOWN rather than a guess. Get that
      number before quoting coverage on the pricing page.

## 5. Sync the free plugin into the SVN working copy

```bash
rsync -a --delete --exclude='.DS_Store' --exclude='.svn' \
  plugin/jhmg-converter-for-elementor-to-divi/ wporg-svn/trunk/
```

- [ ] `cd wporg-svn && svn status` — review every line.
- [ ] `svn add` anything marked `?`:
      ```bash
      cd wporg-svn && svn add --force trunk
      ```
- [ ] `svn rm` anything marked `!` (deleted upstream by the rsync).
- [ ] Confirm no development files rode along: no `tests/`, `fixtures/`,
      `node_modules/`, `vendor/`, `.git*`, `.DS_Store`.

## 6. Tag and commit to SVN

```bash
cd wporg-svn
svn cp trunk tags/<version>
svn ci -m "Release <version>"
```

- [ ] Tag directory created **from trunk after** the sync, not before.
- [ ] Assets (`wporg-svn/assets/`) updated only if the banner, icon or
      screenshots changed.

## 7. Pro

- [ ] `EDCP_PLUGIN_VERSION` and the `Version:` header agree.
- [ ] Build the ZIP from `plugin/jhmg-converter-for-elementor-to-divi-pro/`
      excluding `.DS_Store`.
- [ ] Upload to divi5lab.com and confirm `/api/plugin/update-check` serves the
      new `version` and a `package` URL. An update with no `package` is how the
      licence paywall withholds it, so a missing URL looks identical to an
      expired licence from the customer's side.
- [ ] Install the ZIP on a clean site with a valid licence and confirm the
      one-click update path works.

## 8. After release

- [ ] `PriceDropNotice::CAMPAIGN_END` — the notice is hard-expired at
      `2026-10-27` and quotes `$49/yr` as the old price. If the price changed,
      `AdminPage::PRO_PRICE`, this notice and the divi5lab.com listing must all
      agree; nothing enforces that.
- [ ] Watch the wordpress.org support forum for the first few days.

---

## Things that have bitten this release process before

- **The version lives in three places.** Two agreeing and one stale ships a
  plugin whose update never offers itself.
- **`Tested up to` gets carried forward untested.** Read it off the container
  the suite actually ran against.
- **Counted claims go stale.** The description said "140+ widget mappings" while
  the registry recognised 129 widget types, 12 of which only ever emit a
  placeholder.
- **`wporg-svn/` is gitignored.** Nothing in a `git status` will remind you the
  sync has not happened.
