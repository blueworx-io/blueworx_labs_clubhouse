const { test, expect } = require('@playwright/test');
const { hasShop } = require('./helpers/shop');
const { normalise } = require('./helpers/store-markup');
const { ADMIN_PASS } = require('./helpers/credentials');

// Needs a shop: the member area is only served beside one.
test.beforeEach(async ({ page }) => {
  test.skip(!(await hasShop(page)), 'no shop installed — run npm run wp:shop');
});

async function signInAsMember(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'member');
  await page.fill('#user_pass', 'wptest-member-pw');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

const PAGES = [
  ['member-overview', '/member-dashboard/', true],
  ['member-orders', '/member-dashboard/?view=orders', true],
  ['member-profile', '/member-dashboard/?view=profile', true],
  ['checkout', '/checkout-fixture/', false],
];

for (const [name, path, member] of PAGES) {
  test(`${name} markup is unchanged by the switch to Labs`, async ({ page }) => {
    if (member) await signInAsMember(page);
    await page.goto(path);
    const html = await page.locator('.bw-admin').first().evaluate((el) => el.outerHTML);
    expect(normalise(html)).toMatchSnapshot(`${name}.html`);
  });
}
