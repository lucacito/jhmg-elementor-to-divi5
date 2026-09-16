// Checks every media file the seed needs is present, licensed and within budget.
// Run: node demo/tools/check-media.mjs
import { existsSync, readFileSync, statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const content = join(dirname(fileURLToPath(import.meta.url)), '../content');
const problems = [];
const read = (path) => JSON.parse(readFileSync(join(content, path), 'utf8'));

const shots = read('images/shots.json');
if (shots.length !== 30) problems.push(`shots.json has ${shots.length} slots, expected 30`);
if (new Set(shots.map((s) => s.file)).size !== shots.length) problems.push('shots.json repeats a file name');

const manifest = existsSync(join(content, 'images/manifest.json')) ? read('images/manifest.json') : [];
if (manifest.length === 0) problems.push('images/manifest.json is missing or empty');

let photoBytes = 0;
for (const shot of shots) {
  const path = join(content, 'images', shot.file);
  if (!existsSync(path)) {
    problems.push(`missing photo ${shot.file}`);
    continue;
  }
  photoBytes += statSync(path).size;
  const entry = manifest.find((m) => m.file === shot.file);
  if (!entry) problems.push(`manifest has no entry for ${shot.file}`);
  else {
    if (entry.license !== 'Unsplash License') problems.push(`${shot.file}: license is ${entry.license}`);
    if (!String(entry.page_url).startsWith('https://unsplash.com/photos/')) problems.push(`${shot.file}: no Unsplash page URL`);
    if (entry.alt !== shot.alt) problems.push(`${shot.file}: manifest alt differs from shots.json`);
  }
}
if (photoBytes > 8_000_000) problems.push(`photos total ${(photoBytes / 1e6).toFixed(1)} MB, budget is 8 MB`);

for (const [file, minBytes] of [
  ['images/CREDITS.md', 500],
  ['logo.svg', 200],
  ['logo.png', 2_000],
  ['video/tour-poster.jpg', 20_000],
  ['video/tour.webm', 200_000],
]) {
  const path = join(content, file);
  if (!existsSync(path)) problems.push(`missing ${file}`);
  else if (statSync(path).size < minBytes) problems.push(`${file} is under ${minBytes} bytes`);
}

if (problems.length) {
  console.error(problems.map((p) => `FAIL: ${p}`).join('\n'));
  process.exit(1);
}
console.log(`Media OK: ${shots.length} photos, ${(photoBytes / 1e6).toFixed(1)} MB, logo, tour video.`);
