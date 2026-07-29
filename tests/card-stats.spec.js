const { test, expect } = require('@playwright/test');

// A stat card's value/label pair is not always a number. Team cards put the
// match day and the league there, and a real club's values are prose-length:
// Crewe Vagrants runs "Thursday evening" against "North West Counties". The
// flex row had no min-width:0, so a flex item refused to shrink below its
// content and the league was clipped at the card edge, reading "North West
// Cour".
//
// The bundled demo teams use short values, so the long case is written in here
// rather than waited for — the rule under test is how the row copes with text
// wider than its column, not what the demo content happens to say.
const LONG = { day: 'Thursday evening', league: 'North West Counties' };

test('stat card values longer than their column stay inside the card', async ({ page }) => {
  await page.goto('?clubhouse_page=teams');

  const cards = page.locator('.ch-scard');
  await expect(cards.first()).toBeVisible();

  const overflows = await cards.evaluateAll((els, long) => {
    els.forEach((card) => {
      const values = card.querySelectorAll('.ch-scard__stat-v');
      if (values[0]) values[0].textContent = long.day;
      if (values[1]) values[1].textContent = long.league;
    });
    return els.flatMap((card) => {
      const bounds = card.getBoundingClientRect();
      return [...card.querySelectorAll('.ch-scard__stat')]
        .map((stat) => {
          const r = stat.getBoundingClientRect();
          return { text: stat.innerText.trim(), over: Math.round(r.right - bounds.right) };
        })
        .filter((s) => s.over > 1);
    });
  }, LONG);

  expect(overflows).toEqual([]);
});
