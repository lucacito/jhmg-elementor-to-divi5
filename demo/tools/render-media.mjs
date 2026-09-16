// Renders logo.svg to logo.png and records tour.html to video/tour.webm plus a poster,
// using Playwright's built-in video capture (no ffmpeg install needed).
// Run: node demo/tools/render-media.mjs   (after download-photos.mjs)
import { chromium } from '@playwright/test';
import { mkdir, mkdtemp, readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const content = join(here, '../content');
const browser = await chromium.launch();

// Logo: inline SVG so the Google Fonts load, captured at 2x on a transparent background.
const svg = await readFile(join(content, 'logo.svg'), 'utf8');
const logoPage = await browser.newPage({ deviceScaleFactor: 2 });
await logoPage.setContent(
  `<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600&family=Inter:wght@400&display=block">
   <style>html, body { margin: 0; background: transparent; } svg { display: block; }</style>${svg}`,
  { waitUntil: 'networkidle' },
);
await logoPage.evaluate(() => document.fonts.ready);
await logoPage.locator('svg').screenshot({ path: join(content, 'logo.png'), omitBackground: true });
await logoPage.close();
console.log('logo.png');

// Tour video: record 20 seconds of the slideshow, grabbing a poster frame early on.
const videoDir = join(content, 'video');
await mkdir(videoDir, { recursive: true });
const context = await browser.newContext({
  viewport: { width: 1280, height: 720 },
  recordVideo: { dir: await mkdtemp(join(tmpdir(), 'ferncourt-tour-')), size: { width: 1280, height: 720 } },
});
const page = await context.newPage();
await page.goto(pathToFileURL(join(here, 'tour.html')).href, { waitUntil: 'networkidle' });
await page.evaluate(async () => {
  await document.fonts.ready;
  await Promise.all([...document.images].map((image) => image.decode()));
  document.body.classList.add('play');
});
await page.waitForTimeout(2500);
await page.screenshot({ path: join(videoDir, 'tour-poster.jpg'), type: 'jpeg', quality: 80 });
await page.waitForTimeout(18500);
const video = page.video();
await context.close();
await video.saveAs(join(videoDir, 'tour.webm'));
console.log('video/tour.webm, video/tour-poster.jpg');

await browser.close();
