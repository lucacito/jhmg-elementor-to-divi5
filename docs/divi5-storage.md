# Divi 5 Storage and Builder Integration (research notes)

This document summarizes how Divi 5 stores builder layouts and page builder state, based on the local `references/Divi` source snapshot included in this workspace.

**Key files inspected**
- [references/Divi/et-pagebuilder/et-pagebuilder.php](references/Divi/et-pagebuilder/et-pagebuilder.php#L1)
- [references/Divi/includes/builder/shortcode-core.php](references/Divi/includes/builder/shortcode-core.php#L720-L820)
- [references/Divi/includes/builder/core.php](references/Divi/includes/builder/core.php#L4210-L4277)
- [references/Divi/includes/builder/functions.php](references/Divi/includes/builder/functions.php#L2400-L2470)
- [references/Divi/includes/builder/feature/Library.php](references/Divi/includes/builder/feature/Library.php#L1489-L1530)
- [references/Divi/includes/builder/post/type/Layout.php](references/Divi/includes/builder/post/type/Layout.php#L1-L40)

## Post type and layout storage
- Divi declares a dedicated post type for library/layout items: `ET_BUILDER_LAYOUT_POST_TYPE` (value: `et_pb_layout`). See [references/Divi/et-pagebuilder/et-pagebuilder.php](references/Divi/et-pagebuilder/et-pagebuilder.php#L1).
- Layouts (library items) are stored as posts of type `et_pb_layout`. They contain builder content in `post_content` (shortcodes / builder markup). New layout posts are created via `et_pb_create_layout()` which calls `wp_insert_post()` and adds meta. See [includes/builder/shortcode-core.php](references/Divi/includes/builder/shortcode-core.php#L738-L780).

## Primary functions/classes for creating/saving layouts
- `et_pb_create_layout( $name, $content, $meta = array(), $tax_input = array(), ... )` — creates a `et_pb_layout` post and writes meta via `add_post_meta()`; used by the Library and REST endpoints. [shortcode-core.php#738](references/Divi/includes/builder/shortcode-core.php#L738-L780)
- `et_pb_submit_layout( $args )` — higher-level entry that prepares `$meta` and calls `et_pb_create_layout`. [shortcode-core.php#654-L720](references/Divi/includes/builder/shortcode-core.php#L654-L720)
- AJAX handlers: `et_pb_save_layout()` and `et_pb_update_layout()` wrap the above for admin calls. [shortcode-core.php#823-L900](references/Divi/includes/builder/shortcode-core.php#L823-L900)

## Meta keys Divi expects / commonly uses
(Non-exhaustive; pulled from Divi sources under `includes/builder`)
- `_et_pb_use_builder` — `on` / `off` flag marking a post as Divi-built. Used heavily by `et_pb_is_pagebuilder_used()`. See [includes/builder/functions.php#L5624-L5640](references/Divi/includes/builder/functions.php#L5624-L5640).
- `_et_builder_version` — builder version string tracked on posts (example usage at [functions.php#L2578-L2580](references/Divi/includes/builder/functions.php#L2578-L2580)).
- `_et_pb_old_content` — Divi stores raw prior post content or builder content in this meta in several flows (e.g. WooCommerce product integration). See [includes/builder/feature/woocommerce-modules.php#L739](references/Divi/includes/builder/feature/woocommerce-modules.php#L739).
- `_et_pb_excluded_global_options` — list of excluded global options for library items. [functions.php#L2512-L2516](references/Divi/includes/builder/functions.php#L2512-L2516)
- `_et_pb_page_layout` — layout option for fullwidth / boxed layouts; used by theme templates. [includes/builder/functions.php#L1615](references/Divi/includes/builder/functions.php#L1615)

## What Divi Builder checks when opening a page
- The canonical check is `et_pb_is_pagebuilder_used( $post_id )` defined in [includes/builder/core.php#L4210-L4277](references/Divi/includes/builder/core.php#L4210-L4277).
- The function returns true if any of:
  - `_et_pb_use_builder` meta equals `on`.
  - the post type is `et_pb_layout` (library layout post type).
  - the post type is `layout` (extra compatibility).
- Therefore marking a post with `_et_pb_use_builder = 'on'` is sufficient for Divi to treat it as a builder page for many purposes (visual builder link, front-end rendering fallbacks).

## Creation workflow (typical)
1. Prepare builder content as Divi shortcodes / markup (string) suitable for `post_content`.
2. Call `et_pb_create_layout( $name, $content, $meta, $tax_input )` to create a library layout (optional when creating library items).
3. For pages, update post meta:
   - `update_post_meta( $post_id, '_et_pb_use_builder', 'on' )`
   - `update_post_meta( $post_id, '_et_builder_version', <version string> )`
- `update_post_meta( $post_id, '_et_pb_old_content', <saved content> )`  (Divi often stores an "old content" snapshot; here this should be the generated Divi shortcodes for builder and frontend rendering)
- `update_post_meta( $post_id, '_edc_divi_data', json_encode( $divi_data ) )`  (debug snapshot of the converted Divi structure)
## Runtime validation requirements
- A page must be marked as a Divi builder page via `_et_pb_use_builder = 'on'` for Divi 5 to surface the visual builder links and treat the page as builder content.
- `post_content` remains the canonical source for module rendering; `_et_pb_old_content` should contain native Divi shortcode markup when exporting converted pages. A JSON-only `_et_pb_old_content` is not sufficient by itself for full frontend rendering.
- A practical Divi 5 runtime validation suite should confirm:
  - the page is recognized by Divi builder,
  - Divi builder UI or layout markers are present,
  - the expected module output is visible on the frontend,
  - visible frontend content is rendered, and
  - there are no JavaScript console or page errors during builder load.
- Screenshots should be captured for both the builder workspace and frontend output to preserve proof of runtime behavior.
- Confirmed limitation: Divi 5 does not render actual page output from JSON-only `_et_pb_old_content` meta alone. Real frontend rendering requires builder content in `post_content` or a proper `et_pb_layout` library post.

## Example structure (minimal) — creating a page recognized by Divi
- Post (type `page`) — saved normally via `wp_insert_post()`.
- Required post meta to make Divi consider it a builder page (our exporter currently writes these):
  - `_et_pb_use_builder` => `on`
  - `_et_builder_version` => `VB|Divi|x.y.z` (example; match the active builder version)
- `_et_pb_old_content` => (string) serialized Divi shortcodes / builder markup
- `_edc_divi_data` => (string) JSON-encoded converted Divi structure for diagnostics
Example meta payload (JSON):

{
  "_et_pb_use_builder": "on",
  "_et_builder_version": "VB|Divi|5.7.4",
  "_et_pb_old_content": "<Divi shortcodes or JSON snapshot>",
  "_et_pb_use_divi_5": "1"
}

## Notes and recommendations
- Divi's runtime uses `post_content` (shortcodes/markup) and library posts (`et_pb_layout`) as the canonical source for module data. Storing a JSON snapshot in `_et_pb_old_content` preserves the converted structure but Divi builder will not render modules from JSON without an additional serializer that emits Divi shortcodes or library posts.
- For testable, repeatable integration in this repository we implemented a pragmatic approach:
  - Exporter writes required meta keys (see above).
  - Tests can verify Divi recognition by checking `_et_pb_use_builder` or, when a full Divi runtime is available in the environment, calling `et_pb_is_pagebuilder_used()`.
- Full end-to-end compatibility requires one of:
  - Implementing a JSON → Divi shortcode serializer and populate `post_content` (or create a `et_pb_layout` post with `post_content`), or
  - Emulating the complete Divi runtime inside tests (complex and fragile).

## References (selected)
- `et_pb_create_layout()` / `et_pb_submit_layout()` — [references/Divi/includes/builder/shortcode-core.php](references/Divi/includes/builder/shortcode-core.php#L738-L780)
- `ET_BUILDER_LAYOUT_POST_TYPE` — [references/Divi/includes/builder/post/type/Layout.php](references/Divi/includes/builder/post/type/Layout.php#L1-L30)
- `et_pb_is_pagebuilder_used()` — [references/Divi/includes/builder/core.php](references/Divi/includes/builder/core.php#L4210-L4277)
- Many meta usages and saves: [references/Divi/includes/builder/functions.php](references/Divi/includes/builder/functions.php#L2400-L2470)

---
Generated: June 12, 2026
