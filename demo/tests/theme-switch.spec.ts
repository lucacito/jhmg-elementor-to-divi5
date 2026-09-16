import { expect, test } from '@playwright/test';
import { wp } from './support';

// Memberships has no "Book a tour" link of its own, so the count is the number of
// headers rendered: HFE's under Hello Elementor, only Divi's Theme Builder header under
// Divi (the must-use plugin keeps HFE from loading there). The heading proves the page
// body still renders.
test.describe.configure({ mode: 'serial' });

test.afterAll(() => {
  wp('theme', 'activate', 'hello-elementor');
});

test('Hello Elementor renders the HFE header and the page', async ({ page }) => {
  wp('theme', 'activate', 'hello-elementor');
  const response = await page.goto('/memberships/');
  expect(response?.status()).toBe(200);
  await expect(page.getByRole('link', { name: 'Book a tour' })).toHaveCount(1);
  await expect(page.getByRole('heading', { name: 'Pick the membership that fits' })).toBeVisible();
});

test('Divi renders only its Theme Builder header, and the page', async ({ page }) => {
  wp('theme', 'activate', 'Divi');
  const response = await page.goto('/memberships/');
  expect(response?.status()).toBe(200);
  await expect(page.getByRole('link', { name: 'Book a tour' })).toHaveCount(1);
  await expect(page.getByRole('heading', { name: 'Pick the membership that fits' })).toBeVisible();
});
