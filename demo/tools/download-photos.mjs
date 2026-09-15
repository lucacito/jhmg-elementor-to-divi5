// Downloads the photos named in picks.json, cropped per shots.json, and writes
// manifest.json and CREDITS.md. Refuses any photo not under the free Unsplash License.
// Run: node demo/tools/download-photos.mjs
import { readFile, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const imagesDir = join(dirname(fileURLToPath(import.meta.url)), '../content/images');
const shots = JSON.parse(await readFile(join(imagesDir, 'shots.json'), 'utf8'));
const picks = JSON.parse(await readFile(join(imagesDir, 'picks.json'), 'utf8'));

const manifest = [];
let totalBytes = 0;

for (const shot of shots) {
  const id = picks[shot.file];
  if (!id) throw new Error(`picks.json has no photo for ${shot.file}`);

  const response = await fetch(`https://unsplash.com/napi/photos/${id}`);
  if (!response.ok) throw new Error(`${shot.file}: photo ${id} returned ${response.status}`);
  const photo = await response.json();
  if (photo.premium || photo.plus) throw new Error(`${shot.file}: ${id} is Unsplash+, not the free Unsplash License`);

  const crop = shot.crop === 'square' ? 'w=800&h=800&fit=crop&crop=faces' : 'w=1600&h=1067&fit=crop';
  const image = await fetch(`${photo.urls.raw}&${crop}&q=75&fm=jpg`);
  if (!image.ok) throw new Error(`${shot.file}: download returned ${image.status}`);
  const bytes = Buffer.from(await image.arrayBuffer());
  await writeFile(join(imagesDir, shot.file), bytes);
  totalBytes += bytes.length;

  manifest.push({
    file: shot.file,
    unsplash_id: id,
    page_url: photo.links.html,
    photographer: photo.user.name,
    photographer_url: `https://unsplash.com/@${photo.user.username}`,
    license: 'Unsplash License',
    license_url: 'https://unsplash.com/license',
    alt: shot.alt,
  });
  console.log(`${shot.file}  ${(bytes.length / 1e3).toFixed(0)} KB  ${photo.user.name}`);
}

await writeFile(join(imagesDir, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);

const rows = manifest
  .map((m) => `| ${m.file} | [${m.unsplash_id}](${m.page_url}) | [${m.photographer}](${m.photographer_url}) |`)
  .join('\n');
await writeFile(
  join(imagesDir, 'CREDITS.md'),
  `# Photo credits\n\nEvery photo here is from Unsplash under the [Unsplash License](https://unsplash.com/license): free for commercial use, no attribution required. Credited anyway. The tour video and its poster in \`../video/\` are made from these photos.\n\n| File | Photo | Photographer |\n|---|---|---|\n${rows}\n`,
);

console.log(`${manifest.length} photos, ${(totalBytes / 1e6).toFixed(1)} MB`);
