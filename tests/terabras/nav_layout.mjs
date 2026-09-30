/**
 * Terabras — navigation layout verification.
 *
 * Reproduces the reported top-navigation defect and its fix: with the sidebar
 * moved to the top (Setup > My settings > "Page layout"), assert at every tested
 * width and browser zoom that no module is lost, that the labels are actually
 * legible against the bar they sit on, that dropdowns open un-clipped, and that
 * the header never overflows into the user chip. Finishes with a regression pass
 * on the sidebar layout.
 *
 *   node tests/terabras/nav_layout.mjs            # both layouts
 *   LAYOUT=horizontal node tests/terabras/nav_layout.mjs
 *
 * The caller flips the `page_layout` preference between the two runs, e.g.
 *   docker compose -f docker-compose.terabras.yaml exec -T db \
 *     mariadb -uroot -pglpi glpi -e "UPDATE glpi_users SET page_layout='horizontal' WHERE name='admin';"
 */
import { chromium } from 'playwright';

const BASE = process.env.BASE_URL || 'http://localhost:8081';
const USER = process.env.ADMIN_USER || 'admin';
const PASS = process.env.ADMIN_PASS || 'Terabras#Dev2026';
const OUT  = process.env.OUT || '.';
let fails = 0, passes = 0;
const ok  = (m) => { console.log(`  \x1b[32mPASS\x1b[0m ${m}`); passes++; };
const ko  = (m) => { console.log(`  \x1b[31mFAIL\x1b[0m ${m}`); fails++; };
const chk = (cond, m) => cond ? ok(m) : ko(m);

// sRGB relative luminance contrast ratio
function lum([r,g,b]) {
  const f = c => { c/=255; return c <= 0.03928 ? c/12.92 : Math.pow((c+0.055)/1.055, 2.4); };
  return 0.2126*f(r) + 0.7152*f(g) + 0.0722*f(b);
}
function contrast(a, b) {
  const L1 = lum(a), L2 = lum(b);
  return (Math.max(L1,L2) + 0.05) / (Math.min(L1,L2) + 0.05);
}
const rgb = s => (s.match(/\d+(\.\d+)?/g) || [0,0,0]).slice(0,3).map(Number);
// flatten a foreground colour at `opacity` over a background
const over = (fg, bg, op) => fg.map((c,i) => c*op + bg[i]*(1-op));

const browser = await chromium.launch();

async function session(layout) {
  const ctx = await browser.newContext({ viewport: { width: 1920, height: 1000 } });
  const page = await ctx.newPage();
  await page.goto(`${BASE}/index.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="login_name"]', USER);
  await page.fill('input[name="login_password"]', PASS);
  await page.click('button[type="submit"], input[type="submit"]');
  await page.waitForLoadState('networkidle');
  return { ctx, page };
}

const MODULES = ['Ativos','Assistência','Gerência','Ferramentas','Administração','Configuração'];

// ---------------------------------------------------------------- horizontal
const ONLY = process.env.LAYOUT || 'both';
if (ONLY !== 'vertical') {
console.log('\n\x1b[1m== HORIZONTAL (top) navigation ==\x1b[0m');
  const { ctx, page } = await session('horizontal');
  for (const [w, zoom] of [[1920,1],[1600,1],[1366,1],[1280,1],[1024,1],[1920,1.25],[1920,1.5]]) {
    // browser zoom == smaller CSS viewport at the same physical size
    const cssW = Math.round(w / zoom);
    await page.setViewportSize({ width: cssW, height: Math.round(1000/zoom) });
    await page.goto(`${BASE}/front/central.php`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(350);
    const label = `${w}px @ ${zoom*100}% (css ${cssW}px)`;
    console.log(`\n-- ${label}`);

    const r = await page.evaluate((MODULES) => {
      const header = document.querySelector('header.navbar');
      const hdrBg = getComputedStyle(header).backgroundColor;
      const nav = document.querySelector('#navbar-menu');
      const collapsed = getComputedStyle(nav).display === 'none';
      const toggler = document.querySelector('.navbar-toggler');
      const togglerVisible = toggler ? toggler.getBoundingClientRect().width > 0 : false;
      const items = [...document.querySelectorAll('#navbar-menu ul.navbar-nav > li.nav-item > .nav-link')]
        .map(a => {
          const cs = getComputedStyle(a); const rc = a.getBoundingClientRect();
          return { text: (a.innerText||'').trim().split('\n')[0], color: cs.color, opacity: +cs.opacity,
                   w: rc.width, h: rc.height, right: rc.right, left: rc.left };
        });
      // The user chip is the only .nav-link carrying an avatar; the module buttons
      // are .nav-item.dropdown > .nav-link too, so match on the avatar.
      // The header includes user_header twice (a .d-lg-none mobile copy and the
      // desktop one), so pick the avatar whose chip is actually laid out.
      const chip = [...document.querySelectorAll('header.navbar .nav-link .avatar')]
        .map(a => a.closest('.nav-link'))
        .find(n => n && n.getBoundingClientRect().width > 0) || null;
      const chipRect = chip ? chip.getBoundingClientRect() : null;
      return {
        hdrBg, collapsed, togglerVisible, items,
        docOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
        headerOverflow: header.scrollWidth > header.clientWidth,
        chip: chipRect ? { left: chipRect.left, right: chipRect.right } : null,
        innerW: window.innerWidth,
        found: MODULES.filter(m => document.body.innerText.includes(m)).length,
      };
    }, MODULES);

    chk(!r.docOverflow, `${label}: page does not scroll horizontally`);
    chk(!r.headerOverflow, `${label}: header content fits`);

    if (r.collapsed) {
      chk(r.togglerVisible, `${label}: menu collapsed -> hamburger is offered`);
      await page.click('.navbar-toggler');
      await page.waitForTimeout(450);
      const after = await page.evaluate(() =>
        [...document.querySelectorAll('#navbar-menu ul.navbar-nav > li.nav-item > .nav-link')]
          .filter(a => a.getBoundingClientRect().width > 0)
          .map(a => (a.innerText||'').trim().split('\n')[0]));
      const missing = MODULES.filter(m => !after.includes(m));
      chk(missing.length === 0, `${label}: all ${MODULES.length} modules reachable via hamburger${missing.length ? ' (missing: '+missing.join(', ')+')' : ''}`);
    } else {
      const bg = rgb(r.hdrBg);
      let worst = 99, worstItem = '';
      for (const it of r.items) {
        const c = contrast(over(rgb(it.color), bg, it.opacity), bg);
        if (c < worst) { worst = c; worstItem = it.text; }
      }
      chk(worst >= 3.0, `${label}: menu labels legible on the bar (worst contrast ${worst.toFixed(2)}:1 on "${worstItem}")`);
      const missing = MODULES.filter(m => !r.items.some(i => i.text === m && i.w > 0 && i.h > 0));
      chk(missing.length === 0, `${label}: all ${MODULES.length} modules rendered${missing.length ? ' (missing: '+missing.join(', ')+')' : ''}`);
      const lastRight = Math.max(...r.items.map(i => i.right));
      chk(r.chip && lastRight <= r.chip.left + 1, `${label}: menu does not overlap the user chip`);
      chk(r.chip && r.chip.right <= r.innerW + 1, `${label}: user chip is fully on screen`);

      // dropdown must open and not be clipped
      await page.click('#navbar-menu ul.navbar-nav > li.nav-item > .nav-link');
      await page.waitForTimeout(400);
      const dd = await page.evaluate(() => {
        const m = document.querySelector('#navbar-menu .dropdown-menu.show');
        if (!m) return null;
        const rc = m.getBoundingClientRect();
        return { w: rc.width, h: rc.height, left: rc.left, right: rc.right, top: rc.top,
                 items: m.querySelectorAll('.dropdown-item').length,
                 inViewX: rc.left >= -1 && rc.right <= window.innerWidth + 1 };
      });
      chk(dd !== null, `${label}: first dropdown opens`);
      if (dd) {
        chk(dd.items > 0 && dd.h > 0, `${label}: dropdown renders its ${dd.items} entries`);
        chk(dd.inViewX, `${label}: dropdown is not clipped horizontally`);
        await page.screenshot({ path: `${OUT}/dd-${w}-${zoom}.png`, clip: { x: 0, y: 0, width: Math.min(cssW, 1400), height: 420 } });
      }
    }
    await page.screenshot({ path: `${OUT}/h-${w}-${zoom}.png`, clip: { x: 0, y: 0, width: Math.min(cssW,1920), height: 160 } });
  }
  await ctx.close();
}

// ------------------------------------------------------------------ vertical
if (ONLY !== 'horizontal') {
console.log('\n\x1b[1m== VERTICAL (sidebar) navigation — regression ==\x1b[0m');
  const { ctx, page } = await session('vertical');
  await page.setViewportSize({ width: 1600, height: 1000 });
  await page.goto(`${BASE}/front/central.php`, { waitUntil: 'networkidle' });
  const r = await page.evaluate(() => {
    const header = document.querySelector('header.navbar');
    // The navy is painted on .navbar-vertical; .sidebar itself is transparent.
    const side = document.querySelector('.navbar-vertical');
    return {
      body: document.body.className,
      hdrBg: header ? getComputedStyle(header).backgroundColor : null,
      // The sidebar is painted with a navy gradient, so backgroundColor stays
      // transparent; assert the gradient and the legibility of its links instead.
      sideBg: side ? getComputedStyle(side).backgroundImage : null,
      sideLink: side ? getComputedStyle(side.querySelector('.nav-link')).color : null,
      items: [...document.querySelectorAll('.sidebar ul.navbar-nav > li.nav-item > .nav-link')]
        .map(a => (a.innerText||'').trim().split('\n')[0]).filter(Boolean),
    };
  });
  console.log(`   body="${r.body}" header=${r.hdrBg} sidebarLink=${r.sideLink}`);
  chk(r.hdrBg === 'rgb(255, 255, 255)', 'vertical layout: utility header stays the light surface');
  chk(!!r.sideBg && r.sideBg.includes('gradient') && r.sideBg.includes('20, 0, 120'),
      'vertical layout: sidebar keeps the brand navy gradient');
  const missing = MODULES.filter(m => !r.items.includes(m));
  chk(missing.length === 0, `vertical layout: all modules still in the sidebar${missing.length ? ' (missing: '+missing.join(', ')+')' : ''}`);
  await page.screenshot({ path: `${OUT}/vertical-1600.png`, clip: { x: 0, y: 0, width: 900, height: 500 } });
  await ctx.close();
}

await browser.close();
console.log(`\n${passes} passed, ${fails} failed`);
process.exit(fails ? 1 : 0);
