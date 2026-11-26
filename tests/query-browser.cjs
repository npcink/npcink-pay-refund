// Run against a disposable local WordPress site seeded as described in docs/VISUAL-AUDIT-FOLLOWUP.zh-CN.md.
const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const origin = new URL(process.env.REFUND_TEST_URL);
assert.equal(origin.hostname, '127.0.0.1', 'Only a loopback fixture may be tested');
assert.ok(process.env.REFUND_TEST_LOGIN && process.env.REFUND_TEST_PASSWORD);

async function main() {
  const browser = await chromium.launch({ channel: 'chrome' });
  try {
    const page = await browser.newPage({ viewport: { width: 1360, height: 1000 } });
    page.setDefaultTimeout(6000);
    const errors = [];
    const pending = new Map();
    const seen = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      if (url.origin !== origin.origin) return route.abort();
      const body = new URLSearchParams(route.request().postData());
      const action = body.get('action');
      if (url.pathname !== '/wp-admin/admin-ajax.php' || !/^npcink_pay_refund_(wx|zfb)_/.test(action || '')) return route.continue();
      const order = body.get(action === 'npcink_pay_refund_zfb_order_query' ? 'param' : 'order_id');
      seen.push([action, order]);
      pending.set(`${action}:${order}`, route);
    });
    async function respond(action, order, data, status = 200) {
      const key = `${action}:${order}`;
      await waitForRequest(action, order);
      const route = pending.get(key);
      pending.delete(key);
      const response = page.waitForResponse(candidate => candidate.request() === route.request());
      await route.fulfill({ status, json: { success: status === 200, data } });
      await response;
      // Wait until jQuery has processed both callbacks, not just the network response.
      await page.waitForFunction(() => window.jQuery.active === 0);
    }
    async function waitForRequest(action, order) {
      const key = `${action}:${order}`;
      for (let i = 0; i < 600 && !pending.has(key); i++) await new Promise(resolve => setTimeout(resolve, 10));
      assert.ok(pending.has(key), `Missing ${key}; observed ${JSON.stringify(seen)}`);
    }

    await page.goto(new URL('/wp-login.php', origin).href);
    await page.locator('#user_login').fill(process.env.REFUND_TEST_LOGIN);
    await page.locator('#user_pass').fill(process.env.REFUND_TEST_PASSWORD);
    await Promise.all([page.waitForURL('**/wp-admin/**'), page.locator('#wp-submit').click()]);
    await page.goto(new URL('/wp-admin/index.php?page=npcink_pay_refund_query', origin).href);
    const row = '#npcink-pay-refund-records tbody tr';
    await page.locator(row).nth(19).waitFor();
    const disclosure = page.locator('#npcink-pay-refund-operational-summary');
    await disclosure.focus();
    await disclosure.press('Enter');
    const queue = page.locator('#npcink-pay-refund-operational-records tbody tr').first();
    await queue.getByRole('button', { name: '查询核对' }).waitFor();
    for (const width of [390, 782, 960, 1360]) {
      await page.setViewportSize({ width, height: 900 });
      assert.equal(await page.locator(row).count(), 20);
      for (const selector of ['#npcink-pay-refund-records', '#npcink-pay-refund-operational-records']) {
        const table = page.locator(selector);
        assert.equal(await table.locator('tbody .npcink-pay-refund-cell-label').first().isVisible(), width <= 782);
        assert.ok(await table.evaluate(el => el.scrollWidth <= el.clientWidth + 1), `${selector} overflows at ${width}px`);
      }
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth === innerWidth), `Page overflows at ${width}px`);
      if (process.env.REFUND_SCREENSHOTS && [390, 1360].includes(width)) {
        await queue.screenshot({ path: path.join(process.env.REFUND_SCREENSHOTS, `refund-queue-${width}.png`) });
        await page.locator(row).first().screenshot({ path: path.join(process.env.REFUND_SCREENSHOTS, `refund-record-${width}.png`) });
      }
    }
    await page.locator('#npcink-pay-refund-record-next').click();
    await page.getByText(/第 2 页/).waitFor();
    await page.locator('#npcink-pay-refund-record-prev').click();
    await page.locator(row).nth(19).waitFor();
    await page.locator('#npcink-pay-refund-record-keyword').fill('no-matching-fixture');
    await page.getByText('没有符合筛选条件的退款记录。').waitFor();
    console.log('PASS: four viewports, long rows, keyboard disclosure, live pagination and filtered empty state');

    const reconcileButton = queue.getByRole('button', { name: '查询核对' });
    const reconcileOrder = await reconcileButton.getAttribute('data-order');
    await page.setViewportSize({ width: 390, height: 900 });
    await reconcileButton.click();
    await respond('npcink_pay_refund_wx_order_query', reconcileOrder, { html: '<h3>本站记录已补记</h3>' });
    assert.equal(await page.locator('#npcink-pay-refund-wx-input').inputValue(), reconcileOrder);
    await page.locator('#npcink-pay-refund-wx-data').getByText('本站记录已补记').waitFor();
    await page.setViewportSize({ width: 1360, height: 900 });
    console.log('PASS: the narrow-screen reconciliation button queries the exact order in its channel');

    async function query(channel, value) {
      const input = page.locator(`#npcink-pay-refund-${channel}-input`);
      await input.fill(value);
      await input.press('Enter');
    }
    function paid(channel, order) {
      const id = channel === 'wx' ? 'wx-order-btn' : 'order-btn';
      const amount = channel === 'wx' ? '705' : '7.05';
      return { html: `<h3>支付成功 ${order}</h3><input id="npcink-pay-refund-${channel}-reason"><button id="${id}" data-order-id="${order}" data-order-amount="${amount}">全额退款</button>` };
    }
    for (const channel of ['zfb', 'wx']) {
      const action = `npcink_pay_refund_${channel}_order_query`;
      const button = page.locator(`#npcink-pay-refund-${channel}-button`);
      const refund = page.locator(channel === 'wx' ? '#wx-order-btn' : '#order-btn');
      await query(channel, '  slow-order-a  ');
      await waitForRequest(action, 'slow-order-a');
      assert.equal(await button.innerText(), '查询中…');
      assert.ok(await button.isDisabled());
      await page.locator(`#npcink-pay-refund-${channel}-input`).press('Enter');
      await query(channel, 'fast-order-b');
      await waitForRequest(action, 'fast-order-b');
      // Release the fast response first while the old request is still in flight.
      const fast = pending.get(`${action}:fast-order-b`);
      pending.delete(`${action}:fast-order-b`);
      await fast.fulfill({ json: { success: true, data: paid(channel, 'fast-order-b') } });
      await refund.waitFor();
      await respond(action, 'slow-order-a', paid(channel, 'slow-order-a'));
      assert.equal(seen.filter(([name, order]) => name === action && order === 'slow-order-a').length, 1);
      assert.equal(await refund.getAttribute('data-order-id'), 'fast-order-b');
      await page.locator(`#npcink-pay-refund-${channel}-input`).fill('changed-order-c');
      assert.equal(await refund.count(), 0);
      await query(channel, 'error-order');
      await respond(action, 'error-order', { message: '测试：查询失败' }, 400);
      await page.locator(`#npcink-pay-refund-${channel}-data`).getByText('测试：查询失败').waitFor();
      assert.ok(await button.isEnabled());
    }
    console.log('PASS: both channels send the exact trimmed order; stale results cannot restore old refund controls');

    const wxQuery = 'npcink_pay_refund_wx_order_query';
    const wxRefund = 'npcink_pay_refund_wx_order_refund';
    const wxPoll = 'npcink_pay_refund_wx_refund_status';
    await query('wx', 'poll-order-a');
    await respond(wxQuery, 'poll-order-a', paid('wx', 'poll-order-a'));
    await page.locator('#npcink-pay-refund-wx-reason').fill('fixture');
    page.once('dialog', async dialog => {
      assert.match(dialog.message(), /poll-order-a.*7\.05 元/s);
      await dialog.accept();
    });
    await page.locator('#wx-order-btn').click();
    await waitForRequest(wxRefund, 'poll-order-a');
    assert.ok(await page.locator('#npcink-pay-refund-wx-input').isDisabled());
    assert.ok(await page.locator('#npcink-pay-refund-zfb-input').isEnabled());
    await respond(wxRefund, 'poll-order-a', { html: '<h3>退款处理中</h3>', poll: true, order_id: 'poll-order-a' });
    await waitForRequest(wxPoll, 'poll-order-a');
    await query('wx', 'new-order-b');
    await waitForRequest(wxQuery, 'new-order-b');
    const fresh = pending.get(`${wxQuery}:new-order-b`);
    pending.delete(`${wxQuery}:new-order-b`);
    await fresh.fulfill({ json: { success: true, data: paid('wx', 'new-order-b') } });
    await page.locator('#wx-order-btn[data-order-id="new-order-b"]').waitFor();
    await respond(wxPoll, 'poll-order-a', { html: '<h3>旧退款结果</h3>', refund_status: 'SUCCESS' });
    assert.equal(await page.locator('#wx-order-btn').getAttribute('data-order-id'), 'new-order-b');
    assert.deepEqual(errors, []);
    console.log('PASS: submitted refund locks only its channel; in-flight WeChat polling cannot overwrite a newer order');
  } finally {
    await browser.close();
  }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
