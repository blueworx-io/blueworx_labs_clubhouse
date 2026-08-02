const { test, expect } = require('@playwright/test');

// The member account journey. The rendering half runs against either harness;
// the WordPress-only half needs a real auth stack, so it is tagged @wordpress
// and is dropped when the run targets the DB-free preview — against the preview
// it would pass while proving nothing.

test('login page offers a real sign-in form', async ({ page }) => {
  await page.goto('?clubhouse_page=login');

  const form = page.locator('.ch-auth__form');
  await expect(form).toHaveAttribute('method', 'post');
  // WordPress's own credential field names — the form posts to the auth stack,
  // not to a decorative handler.
  await expect(form.locator('input[name="user_login"]')).toBeVisible();
  await expect(form.locator('input[name="user_password"]')).toBeVisible();
  await expect(form.locator('input[name="remember"]')).toBeAttached();
});

test('forgotten-password screen is reachable and stays in the club look', async ({ page }) => {
  await page.goto('?clubhouse_page=login&clubhouse_auth=forgot');

  await expect(page.locator('[data-auth-view="forgot"]')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Forgotten your password?' })).toBeVisible();
  // Asking for a reset link never asks for the password being reset.
  await expect(page.locator('input[name="user_password"]')).toHaveCount(0);
  // Still the clubhouse chrome, not wp-login.php.
  await expect(page.locator('header.ch-nav')).toBeVisible();
});

test('set-a-new-password screen asks for the password twice', async ({ page }) => {
  await page.goto('?clubhouse_page=login&clubhouse_auth=reset');

  await expect(page.locator('input[name="pass1"]')).toBeVisible();
  await expect(page.locator('input[name="pass2"]')).toBeVisible();
});

test('the login page has exactly one h1 and it is the card heading', async ({ page }) => {
  await page.goto('?clubhouse_page=login');
  await expect(page.locator('h1')).toHaveCount(1);
  await expect(page.locator('h1')).toHaveClass(/ch-auth__title/);
});

test('@wordpress bad credentials are refused with WordPress\'s own message', async ({ page }) => {
  await page.goto('/login/');
  await page.fill('input[name="user_login"]', 'nobody-here');
  await page.fill('input[name="user_password"]', 'wrong-password');
  await page.click('.ch-auth__submit');

  await expect(page.locator('.ch-auth__msg--error')).toBeVisible();
  // Still signed out, and still on the clubhouse login page.
  await expect(page.locator('input[name="user_password"]')).toBeVisible();
});

test('@wordpress an off-site redirect_to is ignored', async ({ page }) => {
  await page.goto('/login/?redirect_to=https://evil.example/steal');
  // The value is carried so a legitimate one survives the round trip, but the
  // handler is what decides whether to honour it — see AuthViewTest.
  await expect(page.locator('input[name="redirect_to"]')).toHaveValue('https://evil.example/steal');
  await page.fill('input[name="user_login"]', 'nobody-here');
  await page.fill('input[name="user_password"]', 'wrong-password');
  await page.click('.ch-auth__submit');
  expect(page.url()).not.toContain('evil.example');
});
