#!/usr/bin/env node
'use strict';

/**
 * visual-diff.js
 *
 * Converts an Elementor JSON export to Divi 5 via the running Docker WordPress,
 * screenshots the Divi output, optionally screenshots a reference image/HTML page,
 * and produces a pixel-diff image.
 *
 * Usage:
 *   node scripts/visual-diff.js --json <elementor.json> [options]
 *
 * Options:
 *   --json <path>     Elementor export JSON (array or {content} export format)
 *   --before <path>   Reference image (.jpg/.png) or HTML file to compare against
 *   --width <px>      Viewport width in pixels (default: 1440)
 *   --label <name>    Slug for the output directory (default: json filename stem)
 *   --url <base>      WordPress base URL (default: http://localhost:8000)
 *   --threshold <n>   Pixelmatch threshold 0–1 (default: 0.1)
 *
 * Output: visual-diffs/<timestamp>-<label>/
 *   before.png   reference screenshot (if --before provided)
 *   after.png    Divi converted output
 *   diff.png     pixel diff (if both before and after present)
 *   report.json  metadata + diff stats
 */

const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const http = require('http');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');

// ── Argument parsing ─────────────────────────────────────────────────────────

function parseArgs(argv) {
  const args = {};
  for (let i = 2; i < argv.length; i++) {
    if (argv[i].startsWith('--')) {
      const key = argv[i].slice(2);
      args[key] = argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[++i] : true;
    }
  }
  return args;
}

const args = parseArgs(process.argv);

if (!args.json) {
  console.error('Usage: node scripts/visual-diff.js --json <elementor.json> [--before <ref>] [--width 1440] [--label <name>]');
  process.exit(1);
}

const jsonPath     = path.resolve(args.json);
const beforePath   = args.before ? path.resolve(args.before) : null;
const viewportWidth  = parseInt(args.width || '1440', 10);
const baseUrl      = args.url || process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
const diffThreshold = parseFloat(args.threshold || '0.1');
const label        = (args.label || path.basename(jsonPath, path.extname(jsonPath)))
  .replace(/[^a-z0-9_-]/gi, '-').toLowerCase();

const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
const outputDir = path.join(ROOT, 'visual-diffs', `${timestamp}-${label}`);

// ── Docker helpers ───────────────────────────────────────────────────────────

function getContainerId() {
  const id = execFileSync(
    'docker-compose',
    ['-f', path.join(ROOT, 'docker-compose.yml'), 'ps', '-q', 'wordpress'],
    { cwd: ROOT, encoding: 'utf8' }
  ).trim();
  if (!id) throw new Error('WordPress Docker container is not running. Start it with: docker-compose up -d');
  return id;
}

function dockerCp(localSrc, containerDst) {
  const cid = getContainerId();
  execFileSync('docker', ['cp', localSrc, `${cid}:${containerDst}`], { cwd: ROOT, stdio: 'inherit' });
}

function dockerExec(command) {
  const cid = getContainerId();
  return execFileSync('docker', ['exec', '-i', cid, 'bash', '-lc', command], {
    cwd: ROOT,
    encoding: 'utf8',
  });
}

function shellEscape(value) {
  return `'${String(value).replace(/'/g, "'\\''")}'`;
}

// ── Elementor JSON normalisation ─────────────────────────────────────────────
// Fixtures are raw arrays. Full exports are {content, page_settings, ...}.

function normaliseElementorJson(filePath) {
  const raw = fs.readFileSync(filePath, 'utf8');
  const parsed = JSON.parse(raw);
  if (Array.isArray(parsed)) return JSON.stringify(parsed);
  if (parsed && Array.isArray(parsed.content)) return JSON.stringify(parsed.content);
  throw new Error(`Unrecognised Elementor JSON format in ${filePath}`);
}

// ── Local HTML server ────────────────────────────────────────────────────────

function serveHtmlFile(htmlFilePath) {
  const baseDir = path.dirname(htmlFilePath);
  const html    = fs.readFileSync(htmlFilePath, 'utf8');

  const MIME = {
    '.css': 'text/css', '.js': 'application/javascript',
    '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
    '.gif': 'image/gif', '.svg': 'image/svg+xml',
    '.woff': 'font/woff', '.woff2': 'font/woff2',
  };

  return new Promise((resolve) => {
    const server = http.createServer((req, res) => {
      if (req.url === '/' || req.url === '/index.html') {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        return res.end(html);
      }
      const assetPath = path.join(baseDir, decodeURIComponent(req.url.replace(/\?.*$/, '')));
      if (fs.existsSync(assetPath) && fs.statSync(assetPath).isFile()) {
        const mime = MIME[path.extname(assetPath)] || 'application/octet-stream';
        res.writeHead(200, { 'Content-Type': mime });
        return res.end(fs.readFileSync(assetPath));
      }
      res.writeHead(404);
      res.end();
    });
    server.listen(0, '127.0.0.1', () => {
      const { port } = server.address();
      resolve({ port, close: () => server.close() });
    });
  });
}

// ── Pixel diff ───────────────────────────────────────────────────────────────

function diffImages(beforeBuf, afterBuf, diffPath) {
  // Lazy-require so missing deps give a clear error only when diffing is attempted
  const { PNG }      = require('pngjs');
  const pixelmatch   = require('pixelmatch');

  const img1 = PNG.sync.read(beforeBuf);
  const img2 = PNG.sync.read(afterBuf);

  // Diff at the narrower/shorter of the two images
  const width  = Math.min(img1.width,  img2.width);
  const height = Math.min(img1.height, img2.height);

  // Crop data to matching dimensions if needed
  function cropData(img) {
    if (img.width === width && img.height === height) return img.data;
    const out = Buffer.alloc(width * height * 4);
    for (let y = 0; y < height; y++) {
      img.data.copy(out, y * width * 4, y * img.width * 4, y * img.width * 4 + width * 4);
    }
    return out;
  }

  const diff      = new PNG({ width, height });
  const mismatch  = pixelmatch(cropData(img1), cropData(img2), diff.data, width, height, {
    threshold:  diffThreshold,
    includeAA:  false,
  });

  fs.writeFileSync(diffPath, PNG.sync.write(diff));

  const total = width * height;
  return { mismatch, total, pct: parseFloat(((mismatch / total) * 100).toFixed(2)), width, height };
}

// ── Main ─────────────────────────────────────────────────────────────────────

async function run() {
  fs.mkdirSync(outputDir, { recursive: true });

  console.log(`\nVisual diff: ${label}`);
  console.log(`  JSON   : ${jsonPath}`);
  if (beforePath) console.log(`  Before : ${beforePath}`);
  console.log(`  Width  : ${viewportWidth}px`);
  console.log(`  Output : ${outputDir}\n`);

  const browser = await chromium.launch();
  const beforePngPath = path.join(outputDir, 'before.png');
  const afterPngPath  = path.join(outputDir, 'after.png');
  let server = null;

  try {
    // ── Step 1: "before" screenshot ──────────────────────────────────────────
    if (beforePath) {
      const ext = path.extname(beforePath).toLowerCase();
      const page = await browser.newPage({ viewport: { width: viewportWidth, height: 900 } });

      if (['.jpg', '.jpeg', '.png', '.webp'].includes(ext)) {
        // Wrap image in a simple HTML page so Playwright can screenshot it at full width
        const dataUrl = 'data:image/' +
          (ext === '.jpg' || ext === '.jpeg' ? 'jpeg' : ext.slice(1)) +
          ';base64,' + fs.readFileSync(beforePath).toString('base64');

        await page.setContent(
          `<html><body style="margin:0;padding:0;background:#fff">` +
          `<img src="${dataUrl}" style="width:100%;display:block"></body></html>`
        );
        await page.waitForLoadState('load');
      } else if (['.html', '.htm'].includes(ext)) {
        server = await serveHtmlFile(beforePath);
        await page.goto(`http://127.0.0.1:${server.port}/`, { waitUntil: 'networkidle', timeout: 20000 });
      } else {
        throw new Error(`Unsupported --before file type: ${ext}`);
      }

      await page.screenshot({ path: beforePngPath, fullPage: true });
      await page.close();
      console.log(`Before screenshot saved (${path.basename(beforePath)})`);
    } else {
      console.log('No --before provided; skipping reference screenshot');
    }

    // ── Step 2: Convert Elementor JSON → Divi in Docker ─────────────────────
    console.log('\nSetting up conversion in Docker...');

    const normalisedJson = normaliseElementorJson(jsonPath);
    const tmpJsonPath    = path.join(outputDir, '_elementor_data.json');
    fs.writeFileSync(tmpJsonPath, normalisedJson);

    dockerCp(tmpJsonPath, '/tmp/visual-diff-input.json');
    dockerCp(path.join(ROOT, 'scripts', 'docker', 'set-elementor-from-path.php'), '/tmp/set-elementor-from-path.php');
    dockerCp(path.join(ROOT, 'scripts', 'docker', 'convert-to-new-page.php'), '/tmp/convert-to-new-page.php');

    const sourcePageId = dockerExec(
      `wp post create --post_type=page --post_status=publish --post_title=${shellEscape('[visual-diff] ' + label)} --porcelain --allow-root`
    ).trim();
    if (!sourcePageId) throw new Error('Failed to create source page in WordPress');
    console.log(`  Source page created  (ID ${sourcePageId})`);

    dockerExec(
      `JSON_PATH=/tmp/visual-diff-input.json PAGE_ID=${shellEscape(sourcePageId)} wp eval-file /tmp/set-elementor-from-path.php --allow-root`
    );
    console.log('  Elementor data attached');

    const convertedPageId = dockerExec(
      `SOURCE_PAGE_ID=${shellEscape(sourcePageId)} wp eval-file /tmp/convert-to-new-page.php --allow-root`
    ).trim();
    if (!convertedPageId) throw new Error('Conversion produced no page ID');
    console.log(`  Divi page created    (ID ${convertedPageId})`);

    // ── Step 3: Screenshot Divi output ───────────────────────────────────────
    console.log('\nScreenshotting Divi output...');

    const page = await browser.newPage({ viewport: { width: viewportWidth, height: 900 } });
    const diviUrl = `${baseUrl}/?page_id=${convertedPageId}`;
    await page.goto(diviUrl, { waitUntil: 'networkidle', timeout: 30000 });
    // Wait for at least one Divi section, but don't hard-fail if none appear
    await page.waitForSelector('.et_pb_section', { timeout: 15000 }).catch(() => {
      console.warn('  Warning: no .et_pb_section found — page may be empty or conversion failed');
    });
    await page.screenshot({ path: afterPngPath, fullPage: true });
    await page.close();
    console.log(`  After screenshot saved (${diviUrl})`);

    // ── Step 4: Pixel diff ───────────────────────────────────────────────────
    const report = {
      label,
      timestamp,
      jsonFile:        path.relative(ROOT, jsonPath),
      beforeFile:      beforePath ? path.relative(ROOT, beforePath) : null,
      convertedPageId,
      sourcePageId,
      viewportWidth,
      diviUrl,
      diff:            null,
    };

    if (beforePath && fs.existsSync(beforePngPath)) {
      console.log('\nGenerating diff image...');
      const diffPngPath = path.join(outputDir, 'diff.png');
      try {
        const result = diffImages(
          fs.readFileSync(beforePngPath),
          fs.readFileSync(afterPngPath),
          diffPngPath
        );
        report.diff = result;
        const bar = '█'.repeat(Math.round(result.pct / 2)) + '░'.repeat(50 - Math.round(result.pct / 2));
        console.log(`  Diff: ${result.pct}% [${bar}]  (${result.mismatch.toLocaleString()} / ${result.total.toLocaleString()} px)`);
      } catch (e) {
        console.warn(`  Diff skipped: ${e.message}`);
        console.warn('  Run: npm install pixelmatch pngjs  to enable diffing');
      }
    }

    // ── Step 5: Write report ─────────────────────────────────────────────────
    fs.writeFileSync(path.join(outputDir, 'report.json'), JSON.stringify(report, null, 2));

    console.log(`\nDone! ${outputDir}`);
    console.log(`  after.png  — Divi converted page`);
    if (beforePath) {
      console.log(`  before.png — original reference`);
      if (report.diff) console.log(`  diff.png   — pixel diff (${report.diff.pct}% different)`);
    }
    console.log(`  report.json`);

  } finally {
    if (server) server.close();
    await browser.close();
  }
}

run().catch((err) => {
  console.error('\nError:', err.message);
  process.exit(1);
});
