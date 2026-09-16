// Screenshots pages that are not part of the Ferncourt seed (e.g. an imported kit).
// `NODE_PATH=$PWD/node_modules node demo/tools/kit-shots.cjs elementor <pages.json> <outdir>`
// shots each page as published; `... divi <converted.json> <outdir>` logs in and shots
// each Divi draft preview. See demo/README.md, "Trying another kit".
const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const [stage, listFile, outDir] = process.argv.slice(2);
const BASE = 'http://localhost:8040';
const entries = JSON.parse(fs.readFileSync(listFile, 'utf8'));
fs.mkdirSync(outDir, { recursive: true });

async function settle(page) {
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) {
      window.scrollTo(0, y);
      await new Promise((r) => setTimeout(r, 150));
    }
    window.scrollTo(0, 0);
  });
  await page.waitForTimeout(1500);
}

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  if (stage === 'divi') {
    await page.goto(`${BASE}/wp-login.php`);
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'ferncourt-demo');
    await page.click('#wp-submit');
    await page.waitForURL(/\/wp-admin\//);
  }

  for (const entry of entries) {
    const url = stage === 'divi' ? `${BASE}/?page_id=${entry.draft_id}&preview=true` : entry.url;
    const response = await page.goto(url, { waitUntil: 'load' });
    await settle(page);
    const file = path.join(outDir, `kit-${entry.slug}-${stage}.png`);
    await page.screenshot({ path: file, fullPage: true });
    console.log(`${stage} ${entry.slug}: HTTP ${response ? response.status() : '?'} -> ${path.basename(file)}`);
  }

  await browser.close();
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
