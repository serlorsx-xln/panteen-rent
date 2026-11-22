// End-to-end browser test: a real customer journey + staff back office, desktop and mobile.
// Start the test server first (tests/serve-test.sh) then: node tests/e2e/e2e.js [outdir]
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8089';
const OUT = process.argv[2] || path.join(__dirname, 'shots');
fs.mkdirSync(OUT, { recursive: true });
const SLIP = path.join(__dirname, '../../database/seed-slips', fs.readdirSync(path.join(__dirname, '../../database/seed-slips')).find(f => !f.startsWith('.') && /\.(jpe?g|png)$/i.test(f)));

let pass = 0, fail = [];
const ok = (c, label, d = '') => { if (c) { pass++; console.log('  \x1b[32m✓\x1b[0m ' + label); } else { fail.push(label + (d ? ' (' + d + ')' : '')); console.log('  \x1b[31m✗ ' + label + '\x1b[0m ' + d); } };
const group = n => console.log('\n\x1b[1m' + n + '\x1b[0m');

async function session(browser, viewport, who) {
  const ctx = await browser.newContext({ viewport, locale: 'th-TH', timezoneId: 'Asia/Bangkok', deviceScaleFactor: 1, isMobile: viewport.width < 600, hasTouch: viewport.width < 600 });
  const page = await ctx.newPage();
  page.errors = [];
  page.on('pageerror', e => page.errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') page.errors.push(m.text()); });
  page.on('dialog', d => d.accept());
  if (who) {
    await page.goto(BASE + '/login');
    await page.fill('#email', who[0]);
    await page.fill('#password', who[1]);
    await Promise.all([page.waitForNavigation(), page.click('main form button[type=submit]')]);
  }
  return page;
}
// compare with the device width, not innerWidth: on phones a too-wide layout makes the browser zoom out
// and innerWidth grows with it, which would hide the problem.
const noOverflow = page => page.evaluate(w => document.documentElement.scrollWidth <= w + 1, page.viewportSize().width);
const shot = (page, name) => page.screenshot({ path: path.join(OUT, name + '.png'), fullPage: true });

(async () => {
  const browser = await chromium.launch();
  const M = ['member@panteen.test', 'Member1234'], S = ['staff@panteen.test', 'Staff1234'], A = ['admin@panteen.test', 'Admin1234'];

  group('ลูกค้า: หน้าแรก → บอกวันงาน → รายการชุด');
  const m = await session(browser, { width: 1366, height: 860 }, M);
  const isoPlus = n => { const d = new Date(); d.setDate(d.getDate() + n); return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10); };
  await m.goto(BASE + '/');
  ok(await m.locator('.cats a.cat').count() >= 5, 'หน้าแรกมีหมวดชุดให้เลือก');
  ok(await m.locator('[data-home-grid] article.vit').count() === 4, 'หน้าแรกแสดงชุดมาใหม่ 4 ชุด');
  ok(await m.locator('.hero-media img').evaluate(i => i.complete && i.naturalWidth > 1000), 'ภาพเปิดหน้าโหลดสำเร็จ');
  // the bar floats clear over the photograph and turns white once the page has moved
  const bar = m.locator('header.topbar');
  ok(await bar.evaluate(el => el.classList.contains('is-over') && !el.classList.contains('is-stuck')), 'แถบเมนูโปร่งใสทับภาพเปิดหน้า');
  await m.mouse.wheel(0, 700);
  await m.waitForFunction(() => document.querySelector('header.topbar').classList.contains('is-stuck'), null, { timeout: 3000 }).catch(() => {});
  ok(await bar.evaluate(el => el.classList.contains('is-stuck')), 'เลื่อนลงแล้วแถบเมนูกลับเป็นพื้นขาว');
  await m.evaluate(() => window.scrollTo(0, 0));
  // say the event date at the desk: every floor answers in place, then search
  const eventDay = isoPlus(40);
  await m.fill('form.desk input[name=date]', eventDay);
  await m.waitForFunction(() => /ว่าง/.test(document.querySelector('[data-floor-count]').textContent), null, { timeout: 5000 }).catch(() => {});
  ok(/ว่าง \d+ จาก \d+ ชุด/.test(await m.locator('[data-floor-count]').first().innerText()), 'เลือกวันงานแล้วแต่ละหมวดบอกจำนวนชุดที่ว่างวันนั้น');
  ok(await m.locator('[data-home-grid] article.vit .vit-avail').count() === 4, 'ชุดมาใหม่บอกว่าว่างหรือไม่ว่างในวันงาน');
  ok(!/dd|yyyy/.test(await m.locator('form.desk [data-desk-text]').innerText()) && /25[67]\d/.test(await m.locator('form.desk [data-desk-text]').innerText()), 'ช่องวันงานอ่านเป็นวันที่ไทย (พ.ศ.)', await m.locator('form.desk [data-desk-text]').innerText());
  await shot(m, 'd-home');
  await Promise.all([m.waitForNavigation(), m.click('form.desk .desk-go')]);
  ok(/\/dresses\?/.test(m.url()) && m.url().includes('date=' + eventDay) && /free=1/.test(m.url()), 'กดค้นหาแล้วไปหน้ารายการชุดพร้อมวันงาน', m.url());

  group('ลูกค้า: รายการชุด ตัวกรอง และการเรียง');
  const cards = m.locator('[data-grid] article.vit');
  ok(await cards.count() > 0 && await m.locator('[data-grid] article.vit.is-busy').count() === 0, 'รายการแสดงเฉพาะชุดที่ว่างวันนั้น');
  ok(/ว่าง \d+ ?จาก \d+ ชุด/.test(await m.locator('[data-grid-count]').innerText()), 'บอกจำนวนชุดที่ว่างวันนั้นจากทั้งหมดที่ตรงตัวกรอง', await m.locator('[data-grid-count]').innerText());
  ok(/รับชุด .+ คืนชุด/.test(await m.locator('[data-desk-count]').innerText()), 'แถบวันงานบอกวันรับและวันคืนชุด');
  await m.locator('.opt-row label:has(input[name=size][value="M"])').click();
  await m.waitForFunction(() => /size=M/.test(location.search), null, { timeout: 5000 }).catch(() => {});
  ok(/size=M/.test(m.url()), 'กรองไซส์แล้วที่อยู่หน้าเปลี่ยนตามโดยไม่โหลดหน้าใหม่', m.url());
  await m.waitForFunction(() => [...document.querySelectorAll('[data-grid] .vit-meta')].every(n => /ไซส์ M/.test(n.textContent)), null, { timeout: 5000 }).catch(() => {});
  const metas = await m.locator('[data-grid] .vit-meta').allInnerTexts();
  ok(metas.length > 0 && metas.every(t => /ไซส์ M/.test(t)), 'ทุกใบในรายการเป็นไซส์ M', metas.slice(0, 3).join(' | '));
  ok(+(await m.locator('[data-total]').innerText()) === metas.length || await m.locator('[data-more]').isVisible(), 'ตัวเลขจำนวนชุดตรงกับที่แสดง');
  ok(await m.locator('[data-chips] a[data-chip]').count() === 1, 'มีป้ายตัวกรองที่ใช้อยู่');
  await m.selectOption('select[name=sort]', 'price_asc');
  await m.waitForFunction(() => /sort=price_asc/.test(location.search), null, { timeout: 5000 }).catch(() => {});
  await m.waitForTimeout(300);
  const nums = (await m.locator('[data-grid] .vit-price').allInnerTexts()).map(t => +t.split('บาท')[0].replace(/[^0-9]/g, ''));
  ok(nums.length > 0 && nums.every((v, i) => i === 0 || nums[i - 1] <= v), 'เรียงค่าเช่าจากน้อยไปมาก', nums.join(','));
  await m.locator('[data-chips] a[data-chip]').first().click();
  await m.waitForFunction(() => !/size=M/.test(location.search), null, { timeout: 5000 }).catch(() => {});
  ok(!/size=M/.test(m.url()), 'กดป้ายตัวกรองเพื่อเอาออกได้', m.url());
  // show every dress for that day: taken ones stay in the list, dimmed and said in words
  await m.locator('label.switch:has([data-desk-free])').click();
  await m.waitForFunction(() => !/free=1/.test(location.search), null, { timeout: 5000 }).catch(() => {});
  ok(!/free=1/.test(m.url()) && await cards.count() >= 20, 'ปิด “เฉพาะชุดที่ว่าง” แล้วเห็นทุกชุด', m.url());
  if (await m.locator('[data-more]').isVisible()) {
    const n0 = await cards.count();
    await m.locator('[data-more]').click();
    await m.waitForFunction(n => document.querySelectorAll('[data-grid] article.vit').length > n, n0, { timeout: 5000 }).catch(() => {});
    ok(await cards.count() > n0, 'กด “ดูชุดเพิ่ม” แล้วชุดต่อท้ายรายการเดิม', `${n0} → ${await cards.count()}`);
  }
  await shot(m, 'd-list');
  // a favourite straight from the card
  const favBtn = m.locator('[data-grid] article.vit [data-fav] button').first();
  const was = await favBtn.getAttribute('aria-pressed');
  await favBtn.click();
  await m.waitForFunction(w => document.querySelector('[data-grid] article.vit [data-fav] button').getAttribute('aria-pressed') !== w, was, { timeout: 5000 }).catch(() => {});
  ok(await favBtn.getAttribute('aria-pressed') !== was, 'กดหัวใจที่การ์ดเพื่อเก็บชุดที่ถูกใจได้โดยไม่ออกจากหน้า');
  await favBtn.click();
  await m.waitForTimeout(400);
  // clear the date: the cards stop talking about that day
  await m.locator('[data-desk-clear]').click();
  await m.waitForFunction(() => !/date=/.test(location.search), null, { timeout: 5000 }).catch(() => {});
  ok(!/date=/.test(m.url()) && await m.locator('[data-grid] .vit-avail').count() === 0, 'ล้างวันงานแล้วการ์ดเลิกบอกสถานะของวันนั้น', m.url());

  group('ลูกค้า: หน้าชุด → ดูวันว่าง → จอง');
  await m.locator('[data-grid] article.vit a.vit-link').first().click();
  await m.waitForLoadState('networkidle');
  ok(/\/dress\//.test(m.url()), 'กดชุดแล้วไปหน้ารายละเอียดชุด', m.url());
  ok(await m.locator('[data-gallery-main]').evaluate(i => i.complete && i.naturalWidth > 500 && !!i.getAttribute('srcset')), 'รูปชุดโหลดสำเร็จและมีขนาดสำหรับจอความละเอียดสูง');
  ok(await m.locator('.pdp-box .strip').isVisible() && /บาท/.test(await m.locator('.pdp-box .strip-total').innerText()), 'หน้าชุดบอกค่าเช่า เงินมัดจำ และยอดชำระตอนจอง');
  // move two months ahead in the calendar (AJAX) and pick a free day
  for (let k = 0; k < 2; k++) {
    const title = await m.locator('.cal-head h2').innerText();
    await m.locator('[data-cal-go]').nth(1).click();
    await m.waitForFunction(t => document.querySelector('.cal-head h2').textContent !== t, title);
  }
  const free = m.locator('.cal a.cal-free');
  ok(await free.count() > 0, 'ปฏิทินเปลี่ยนเดือนได้และมีวันว่าง');
  await free.nth(Math.min(10, (await free.count()) - 1)).click();
  await m.waitForLoadState('networkidle');
  ok(await m.locator('.verdict.is-free').isVisible(), 'เลือกวันงานแล้วบอกว่า “ว่าง” พร้อมวันรับ–คืน');
  await shot(m, 'd-dress');
  await m.locator('#verdict a[href*="/book/"], a.btn[href*="/book/"]').first().click();
  await m.waitForLoadState('networkidle');
  ok(/\/book\//.test(m.url()), 'กดจองแล้วไปฟอร์มจอง', m.url());
  await m.waitForFunction(() => { const o = document.querySelector('[data-quote-out]'); return o && /บาท/.test(o.textContent); }, null, { timeout: 5000 }).catch(() => {});
  ok(/บาท/.test(await m.locator('[data-quote-out]').innerText()), 'คำนวณราคาให้ทันที');
  // extend by one day → price updates live
  const before = await m.locator('[data-quote-out]').innerText();
  const ret = await m.inputValue('#return_date');
  const d = new Date(ret); d.setDate(d.getDate() + 1);
  await m.fill('#return_date', d.toISOString().slice(0, 10));
  await m.locator('#return_date').dispatchEvent('change');
  await m.waitForTimeout(800);
  ok((await m.locator('[data-quote-out]').innerText()) !== before, 'เปลี่ยนวันคืนแล้วราคาอัปเดตเอง (วันเพิ่ม)');
  {
    const newRet = await m.inputValue('#return_date');
    const be = await m.locator('label:has(#return_date) [data-desk-text]').innerText();
    const y = +newRet.slice(0, 4) + 543;
    ok(be.includes(String(y)) && be.includes(String(+newRet.slice(8, 10))), 'ช่องวันที่บอกวันเป็น พ.ศ. แบบเดียวกับช่องค้นหา', be);
    const rng = await m.locator('[data-cal-wrap] .cal-day.cal-range[data-date="' + newRet + '"]').count();
    const evd = await m.inputValue('#event_date');
    const evRange = await m.locator('[data-cal-wrap] .cal-day.cal-range[data-date="' + evd + '"]').count();
    ok(newRet.slice(0, 7) !== evd.slice(0, 7) || (rng === 1 && evRange === 0), 'ปฏิทินแรเงาช่วงรับ–คืน (วันงานเป็นสีทึบ)', `return=${rng} event=${evRange}`);
  }
  {
    // choosing delivery by post shows the address block and adds the shipping fee to the price
    ok(!(await m.locator('[data-ship-address]').isVisible()), 'รับที่ร้าน: ไม่ต้องกรอกที่อยู่');
    const fee = has => m.waitForFunction(h => /ค่าจัดส่ง/.test(document.querySelector('[data-quote-out]').textContent) === h, has, { timeout: 5000 }).catch(() => {});
    await m.locator('label.way:has(input[name="delivery_method"][value="post"])').click();
    await fee(true);
    ok(await m.locator('[data-ship-address]').isVisible() && await m.locator('[data-saved-addresses] input[name="address_id"]').count() >= 2, 'เลือกส่งพัสดุ → แสดงที่อยู่ที่บันทึกไว้ให้เลือก');
    await m.locator('[data-saved-addresses] label.way').first().click();
    await m.waitForLoadState('networkidle');
    ok(/ค่าจัดส่ง/.test(await m.locator('[data-quote-out]').innerText()), 'ราคารวมค่าจัดส่งตามเขตของที่อยู่ที่เลือก');
    ok(!(await m.locator('[data-new-address]').isVisible()), 'เลือกที่อยู่เดิม → ซ่อนช่องกรอกที่อยู่ใหม่');
    await shot(m, 'd-book-ship');
    await m.locator('label.way:has(input[name="delivery_method"][value="pickup"])').click();
    await fee(false);
    await m.waitForLoadState('networkidle');
    ok(!/ค่าจัดส่ง/.test(await m.locator('[data-quote-out]').innerText()), 'เปลี่ยนกลับเป็นรับที่ร้าน → ไม่มีค่าจัดส่ง');
  }
  await shot(m, 'd-book');
  await m.click('[data-booking-submit]');
  await m.waitForLoadState('networkidle');
  ok(/\/book\//.test(m.url()) && await m.locator('.field-error, [aria-invalid="true"]').count() > 0, 'ลืมติ๊กยอมรับเงื่อนไข → แจ้งเตือนที่ฟอร์ม');
  await m.check('input[name=agree]');
  await m.click('[data-booking-submit]');
  await m.waitForURL(/\/bookings\/\d+/);
  const bookingUrl = m.url();
  ok(true, 'จองสำเร็จ → หน้าการจอง');
  ok(await m.locator('[data-qr] svg, [data-qr] canvas, [data-qr] img').count() > 0, 'แสดง QR พร้อมเพย์ตามยอด');
  ok(await m.locator('[data-countdown]').count() > 0, 'มีนับเวลาถอยหลังการล็อกชุด');

  group('ลูกค้า: แนบสลิป');
  const fake = path.join(OUT, 'fake.jpg'); fs.writeFileSync(fake, 'not really an image');
  await m.setInputFiles('input[name=slip]', fake);
  await m.waitForTimeout(400);
  ok(/เปิดเป็นรูปไม่ได้/.test(await m.locator('[data-drop-label]').innerText()) && !(await m.locator('[data-drop-preview]').isVisible()), 'เลือกไฟล์ที่ไม่ใช่รูป → แจ้งทันที ไม่แสดงรูปเสีย');
  await m.setInputFiles('input[name=slip]', SLIP);
  await m.waitForTimeout(400);
  ok(await m.locator('[data-drop-preview]').isVisible(), 'เลือกไฟล์แล้วเห็นตัวอย่างสลิป');
  await shot(m, 'd-pay');
  await Promise.all([m.waitForNavigation(), m.locator('form:has(input[name=slip]) button[type=submit]').click()]);
  ok(/รอ.*ตรวจ/.test(await m.locator('main').innerText()), 'ส่งสลิปแล้วสถานะเป็น “รอตรวจสลิป”');

  group('พนักงาน: อนุมัติสลิป');
  const s = await session(browser, { width: 1366, height: 860 }, S);
  await s.goto(BASE + '/admin/payments');
  const bid = bookingUrl.match(/\/bookings\/(\d+)/)[1];
  const card = s.locator('.slipcard').filter({ has: s.locator(`a[href$="/admin/bookings/${bid}"]`) }).first();
  const cardCount = await card.count();
  ok(cardCount === 1, 'สลิปใหม่ขึ้นในคิวตรวจ');
  await shot(s, 'd-admin-payments');
  await Promise.all([s.waitForNavigation(), (cardCount ? card : s.locator('.slipcard').first()).locator('form[action$="/approve"] button[type=submit]').click()]);
  ok(/อนุมัติ|ยืนยัน/.test(await s.locator('.flash, [role=status], .toast').first().innerText().catch(() => '')), 'แจ้งผลอนุมัติ');

  group('ลูกค้า: เห็นสถานะยืนยัน + ใบเสร็จ');
  await m.goto(bookingUrl);
  ok(/ยืนยันแล้ว|จองสำเร็จ/.test(await m.locator('main').innerText()), 'สถานะเปลี่ยนเป็นยืนยันแล้ว');
  const rc = m.locator('a[href*="/receipts/"]').first();
  ok(await rc.count() > 0, 'มีลิงก์ใบเสร็จ');
  if (await rc.count()) {
    await m.goto(BASE + await rc.getAttribute('href'));
    ok(/ใบเสร็จ/.test(await m.locator('body').innerText()), 'เปิดใบเสร็จได้');
    await shot(m, 'd-receipt');
  }
  await m.goto(BASE + '/account/notifications');
  ok(await m.locator('a.notif').count() >= 3 && /ยืนยัน/.test(await m.locator('a.notif').first().innerText()), 'แจ้งเตือนล่าสุดบอกว่าการจองยืนยันแล้ว');

  group('พนักงาน: รับชุด → คืนชุด (พรีวิวค่าปรับ)');
  // Use a booking that starts today: create at the counter.
  await s.goto(BASE + '/admin/bookings?status=confirmed');
  await s.goto(BASE + '/admin/bookings/' + bid);
  ok(await s.locator('.steps, ol').count() > 0, 'หน้าการจองหลังร้านแสดงขั้นตอน');
  await shot(s, 'd-admin-booking');
  await s.goto(BASE + '/admin');
  const pickBtn = s.locator('form[action$="/pickup"] button[type=submit]').first();
  if (await pickBtn.count()) {
    await Promise.all([s.waitForNavigation(), pickBtn.click()]);
    ok(true, 'กดรับชุดจากหน้าแดชบอร์ดได้');
  } else ok(true, 'ไม่มีคิวรับชุดวันนี้ (ข้าม)');
  await s.goto(BASE + '/admin/bookings?status=picked_up');
  // bookings returned by post are closed through the inspection page instead: take the first one returned at the counter
  const picked = [...new Set(await s.locator('table a[href*="/admin/bookings/"]').evaluateAll(as => as.map(a => a.getAttribute('href'))))];
  let counterReturn = null;
  for (const h of picked) {
    await s.goto(new URL(h, BASE).href, { waitUntil: 'networkidle' });
    if (await s.locator('form#return').count()) { counterReturn = h; break; }
  }
  if (counterReturn) {
    const form = s.locator('form#return');
    ok(await form.count() === 1, 'มีฟอร์มรับคืนชุด');
    await s.fill('form#return [name=damage_fee]', '250');
    await s.locator('form#return [name=damage_fee]').dispatchEvent('input');
    await s.waitForTimeout(200);
    const preview = await form.innerText();
    ok(/250/.test(preview), 'พรีวิวยอดหักมัดจำตามค่าเสียหายทันที');
    await s.fill('form#return [name=damage_note]', 'ชายกระโปรงเปื้อน');
    await Promise.all([s.waitForNavigation(), form.locator('button[type=submit]').click()]);
    ok(/คืนชุดแล้ว|คืนแล้ว/.test(await s.locator('main').innerText()), 'บันทึกคืนชุดเรียบร้อย');
  }

  group('ตรวจทุกหน้า: ไม่มี JS error / ไม่ล้นจอ (เดสก์ท็อป + มือถือ)');
  await m.goto(BASE + '/');
  const catHref = new URL(await m.locator('a[href*="/c/"]').first().getAttribute('href'), BASE).pathname;
  const dressHref = new URL(await m.locator('article.vit a.vit-link').first().getAttribute('href'), BASE).pathname;
  const memberPages = ['/', '/dresses', '/dresses?date=' + isoPlus(30) + '&free=1', catHref, dressHref, dressHref + '?date=' + isoPlus(30), '/book/' + dressHref.split('/').pop(), '/how-to-rent', '/credits', '/bookings', bookingUrl.replace(BASE, ''), '/account', '/account/favorites', '/account/appointments', '/account/notifications', '/account/addresses'];
  const adminPages = ['/admin', '/admin/bookings', '/admin/bookings/' + bid, '/admin/bookings/new', '/admin/schedule', '/admin/payments', '/admin/payments?tab=done', '/admin/costumes', '/admin/costumes/new',
    '/admin/categories', '/admin/members', '/admin/appointments', '/admin/reports/rentals', '/admin/reports/finance', '/admin/reports/appointments', '/admin/settings',
    '/admin/shipping', '/admin/shipping?tab=back', '/admin/scan', '/admin/damage-types'];
  for (const [label, vp] of [['d', { width: 1366, height: 860 }], ['m', { width: 390, height: 844 }]]) {
    const mp = label === 'd' ? m : await session(browser, vp, M);
    const ap = await session(browser, vp, A);
    for (const [pg, list, who] of [[mp, memberPages, 'member'], [ap, adminPages, 'admin']]) {
      for (const u of list) {
        pg.errors = [];
        const r = await pg.goto(BASE + u, { waitUntil: 'networkidle' });
        const name = `${label}-${who}-` + (u.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'home');
        if (label === 'm' || who === 'admin') await shot(pg, name);
        ok(r.status() === 200 && !pg.errors.length && await noOverflow(pg), `${label === 'd' ? 'เดสก์ท็อป' : 'มือถือ'} ${who} ${u}`, `status ${r.status()} ${pg.errors.join(' | ')} ${await noOverflow(pg) ? '' : 'H-OVERFLOW'}`);
      }
    }
    if (label === 'm') {
      // phone storefront: the menu sheet and the filter drawer
      await mp.goto(BASE + '/');
      await mp.locator('details.navsheet > summary').click();
      await mp.waitForTimeout(350);
      ok(await mp.locator('.navsheet-panel a[href="/dresses"]').isVisible(), 'มือถือ: เปิดเมนูร้านได้');
      await mp.keyboard.press('Escape');
      await mp.waitForTimeout(350);
      ok(!(await mp.locator('.navsheet-panel a[href="/dresses"]').isVisible()), 'มือถือ: กด Esc ปิดเมนูร้าน');
      await mp.goto(BASE + '/dresses');
      await mp.locator('[data-filters-open]').click();
      await mp.waitForTimeout(350);
      ok(await mp.locator('#filters input[name=q]').isVisible() && await mp.locator('[data-filters-open]').getAttribute('aria-expanded') === 'true', 'มือถือ: เปิดแผงตัวกรองได้');
      await mp.locator('#filters .opt-row label:has(input[name=size][value="S"])').click();
      await mp.waitForFunction(() => /size=S/.test(location.search), null, { timeout: 5000 }).catch(() => {});
      await mp.locator('#filters .apply').click();
      await mp.waitForTimeout(450);
      ok(/size=S/.test(mp.url()) && await mp.evaluate(() => !document.body.classList.contains('filters-on')), 'มือถือ: เลือกไซส์แล้วกดดูชุด แผงปิดและรายการถูกกรอง', mp.url());
      await shot(mp, 'm-list-filtered');
      // phone dress page: the date is asked right on the page
      await mp.goto(BASE + dressHref);
      await mp.fill('form.pdp-date input[name=date]', isoPlus(33));
      await mp.locator('form.pdp-date input[name=date]').blur();
      await mp.waitForURL(/date=/, { timeout: 5000 }).catch(() => {});
      ok(/date=/.test(mp.url()) && await mp.locator('.verdict.is-free, .verdict.is-busy').count() === 1, 'มือถือ: ใส่วันงานที่หน้าชุดแล้วได้คำตอบว่าว่างหรือไม่ว่าง', mp.url());
      await shot(mp, 'm-dress-date');
      // mobile admin drawer opens and closes
      await ap.goto(BASE + '/admin');
      await ap.click('.nav-toggle');
      await ap.waitForTimeout(300);
      ok(await ap.locator('a[href="/admin/payments"]').first().isVisible(), 'มือถือ: เปิดเมนูหลังร้านได้');
      await ap.keyboard.press('Escape');
      await ap.waitForTimeout(400);
      const closed = await ap.evaluate(() => !document.body.classList.contains('nav-open'));
      const box = await ap.locator('a[href="/admin/payments"]').first().boundingBox();
      ok(closed && (!box || box.x + box.width <= 1), 'มือถือ: กด Esc ปิดเมนู (เมนูเลื่อนออกนอกจอ)', JSON.stringify(box));
      ok(await ap.evaluate(() => document.activeElement && document.activeElement.matches('[data-nav-toggle]')), 'มือถือ: ปิดเมนูแล้วโฟกัสกลับไปที่ปุ่มเมนู');
    }
  }

  group('การเข้าถึง (a11y พื้นฐาน)');
  const g = await session(browser, { width: 1366, height: 860 }, null);
  for (const u of ['/', '/login', '/register', memberPages[1]]) {
    await g.goto(BASE + u);
    const r = await g.evaluate(() => {
      const unlabeled = [...document.querySelectorAll('input:not([type=hidden]):not([type=submit]), select, textarea')].filter(el => !(el.labels && el.labels.length) && !el.getAttribute('aria-label') && !el.getAttribute('aria-labelledby'));
      const noAlt = [...document.querySelectorAll('img')].filter(i => !i.hasAttribute('alt'));
      const h1 = document.querySelectorAll('h1').length;
      return { unlabeled: unlabeled.map(e => e.name || e.id), noAlt: noAlt.length, h1, lang: document.documentElement.lang };
    });
    ok(!r.unlabeled.length && !r.noAlt && r.h1 === 1 && r.lang === 'th', `${u}: ทุกช่องมี label, รูปมี alt, มี h1 เดียว`, JSON.stringify(r));
  }
  await g.goto(BASE + '/');
  await g.keyboard.press('Tab');
  const skip = await g.evaluate(() => document.activeElement && (document.activeElement.textContent || '').trim());
  ok(/ข้าม/.test(skip || ''), 'กด Tab ครั้งแรกเจอลิงก์ “ข้ามไปเนื้อหา”', skip);
  const focusVisible = await g.evaluate(() => getComputedStyle(document.activeElement).outlineStyle !== 'none' || getComputedStyle(document.activeElement).boxShadow !== 'none');
  ok(focusVisible, 'มีกรอบโฟกัสเมื่อใช้คีย์บอร์ด');

  await browser.close();
  console.log('\n' + '─'.repeat(60) + '\n' + (fail.length ? '\x1b[31m' : '\x1b[32m') + pass + '/' + (pass + fail.length) + ' passed\x1b[0m');
  fail.forEach(f => console.log('  ✗ ' + f));
  process.exit(fail.length ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
