import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import type { Page } from '@playwright/test';

// Specs run from the repository root (demo/verify.sh does this).
const DEMO = join(process.cwd(), 'demo');

export const PAGES = [
  { slug: 'home', path: '/' },
  { slug: 'spaces', path: '/spaces/' },
  { slug: 'memberships', path: '/memberships/' },
  { slug: 'about', path: '/about/' },
  { slug: 'events', path: '/events/' },
  { slug: 'blog', path: '/blog/' },
  { slug: 'contact', path: '/contact/' },
];

/** Runs WP-CLI against the demo site and returns its output. */
export function wp(...args: string[]): string {
  return execFileSync(join(DEMO, 'wp'), args, { encoding: 'utf8' }).trim();
}

/** demo/versions.env as key/value pairs. */
export function demoEnv(): Record<string, string> {
  return Object.fromEntries(
    readFileSync(join(DEMO, 'versions.env'), 'utf8')
      .split('\n')
      .filter((line) => /^[A-Z_]+=/.test(line))
      .map((line) => [line.slice(0, line.indexOf('=')), line.slice(line.indexOf('=') + 1)]),
  );
}

export async function login(page: Page): Promise<void> {
  const env = demoEnv();
  await page.goto('/wp-login.php');
  await page.fill('#user_login', env.ADMIN_USER);
  await page.fill('#user_pass', env.ADMIN_PASSWORD);
  await page.click('#wp-submit');
  await page.waitForURL(/\/wp-admin\//);
}
