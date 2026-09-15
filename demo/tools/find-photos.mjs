// For each slot in shots.json, finds free (non-premium) Unsplash photos and renders
// a labelled contact sheet to demo/tools/.candidates/<slot>.png.
// Run: node demo/tools/find-photos.mjs [file.jpg ...]   (no arguments = every slot)
import { chromium } from '@playwright/test';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const shots = JSON.parse(await readFile(join(here, '../content/images/shots.json'), 'utf8'));
const only = process.argv.slice(2);
const outDir = join(here, '.candidates');
await mkdir(outDir, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1200, height: 900 } });

for (const shot of shots) {
  if (only.length && !only.includes(shot.file)) continue;

  const orientation = shot.crop === 'square' ? 'squarish' : 'landscape';
  const url = `https://unsplash.com/napi/search/photos?query=${encodeURIComponent(shot.query)}&per_page=30&orientation=${orientation}`;
  const response = await fetch(url);
  if (!response.ok) throw new Error(`${shot.file}: search returned ${response.status}`);
  const { results } = await response.json();

  const free = results.filter((photo) => !photo.premium && !photo.plus).slice(0, 9);
  if (free.length === 0) throw new Error(`${shot.file}: no free results for "${shot.query}"`);

  const cells = free
    .map((photo) => `<figure><img src="${photo.urls.small}"><figcaption>${photo.id}</figcaption></figure>`)
    .join('');
  await page.setContent(
    `<style>
      body { margin: 0; padding: 8px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; font: 18px monospace; }
      figure { margin: 0; }
      img { width: 100%; height: 260px; object-fit: cover; display: block; }
      figcaption { padding: 4px 8px; background: #000; color: #fff; }
    </style>${cells}`,
    { waitUntil: 'networkidle' },
  );

  const name = shot.file.replace(/\.jpg$/, '');
  await page.screenshot({ path: join(outDir, `${name}.png`), fullPage: true });
  await writeFile(
    join(outDir, `${name}.json`),
    JSON.stringify(free.map((photo) => ({ id: photo.id, alt: photo.alt_description, page: photo.links.html })), null, 2),
  );
  console.log(`${shot.file}: ${free.length} candidates`);
}

await browser.close();
