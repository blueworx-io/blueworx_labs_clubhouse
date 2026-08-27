const { test, expect } = require('@playwright/test');

// @wordpress only, and only where a shop is installed: the sign-in form on the
// club's login page is one of SureCart's, and without SureCart this plugin
// serves no login page at all — which is the point of issue #261.
//
// CI has no shop, so these skip there. Run them locally with:
//   npm run wp:up && npm run wp:shop
// The member account is created by the first test that needs it.

const MEMBER = { login: 'clubmember', pass: 'member-test-pw' };

async function shopIsInstalled(page) {
  const res = await page.goto('/login/', { waitUntil: 'domcontentloaded' });
  return res !== null && res.status() === 200 && (await page.locator('sc-login-form').count()) > 0;
}

test('a member signs in through the shop form on the club page @wordpress', async ({ page }) => {
  test.skip(!(await shopIsInstalled(page)), 'no shop installed — see the note at the top of this file');

  // The club's own card around the shop's form, not the shop's own page.
  await expect(page.locator('.ch-auth__title')).toBeVisible();
  await expect(page.locator('sc-login-form')).toBeVisible();

  // The form is a web component: it is only usable once the shop's script has
  // upgraded it, which is the thing this page had to be taught to enqueue.
  await expect(page.locator('sc-login-form input')).toBeVisible({ timeout: 15000 });

  await page.locator('sc-login-form input').first().fill(MEMBER.login);
  await page.getByRole('button', { name: /next/i }).first().click();

  const password = page.locator('sc-login-form input[type="password"]');
  await expect(password).toBeVisible({ timeout: 15000 });
  await password.fill(MEMBER.pass);
  // "Login", exactly: the form also offers "Send a login code", which is the
  // passwordless path and needs a connected store.
  await page.locator('sc-login-form').getByText('Login', { exact: true }).first().click();

  // Signed in, and sent where the club's setting says — the member area by
  // default, which is what this plugin has always meant by a blank setting.
  await page.waitForURL(/member-dashboard/, { timeout: 20000 });
  await expect(page.locator('.clubhouse-member')).toBeVisible();
});

test('the club heading titles the shop form, not the shop wording @wordpress', async ({ page }) => {
  test.skip(!(await shopIsInstalled(page)), 'no shop installed');

  const heading = await page.locator('.ch-auth__title').innerText();
  await expect(page.locator('sc-login-form [slot="title"]')).toHaveText(heading);
});

// The other half of #261, and the half CI actually runs: with no shop there is
// no membership to sign in to, so the pages and the link go.
test('a club with no shop offers no way in @wordpress', async ({ page }) => {
  test.skip(await shopIsInstalled(page), 'a shop is installed — this is the no-shop case');

  // Nothing in the header points at a login page that is not there.
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.ch-nav__cta')).not.toContainText('Log in');
  await expect(page.locator('a[href$="/login/"]')).toHaveCount(0);

  // And the member area is not served. WordPress sends /login/ itself to
  // wp-login.php once nothing answers for it, which is core's own behaviour and
  // leaves staff a way in.
  const members = await page.goto('/member-dashboard/', { waitUntil: 'domcontentloaded' });
  expect(members.status()).toBe(404);
});
