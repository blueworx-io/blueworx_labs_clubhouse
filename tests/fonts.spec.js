const { test, expect } = require('@playwright/test');

// Proves Phase 5: the rendered page makes zero third-party font requests and
// loads its fonts from the plugin's own /assets/fonts/ directory.
test('fonts are self-hosted, no Google CDN requests', async ({ page }) => {
  const thirdParty = [];
  const fontHits = [];
  page.on('request', (req) => {
    const url = req.url();
    if (url.includes('fonts.googleapis.com') || url.includes('fonts.gstatic.com')) {
      thirdParty.push(url);
    }
  });
  page.on('response', (res) => {
    const url = res.url();
    if (url.includes('/assets/fonts/') && url.endsWith('.woff2')) {
      fontHits.push({ url, status: res.status() });
    }
  });

  await page.goto('?clubhouse_page=home');
  await expect(page.locator('#ch-main')).toBeVisible();
  // Let font requests settle.
  await page.waitForLoadState('networkidle');

  expect(thirdParty, `unexpected third-party font requests: ${thirdParty.join(', ')}`).toHaveLength(0);
  expect(fontHits.length, 'expected at least one self-hosted woff2 request').toBeGreaterThan(0);
  for (const hit of fontHits) {
    expect(hit.status, `status for ${hit.url}`).toBe(200);
  }
});
