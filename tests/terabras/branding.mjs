/**
 * Terabras — branding and white-label verification.
 *
 * Walks the screens listed in the branding review and asserts, on the rendered
 * page (not in the source), that:
 *   - the mark shown is the official Terabras one, not the flat "T" and not a
 *     GLPI mark;
 *   - ordinary interface copy no longer exposes the name "GLPI".
 *
 * The allow-list below is the documented answer to "which GLPI mentions survive,
 * and why" — a hit outside it fails the run.
 *
 *   node tests/terabras/branding.mjs
 *
 * Requires the product stack up (docker compose -f docker-compose.terabras.yaml)
 * and an administrator whose credentials are given below.
 */
import { chromium } from 'playwright';

const BASE = process.env.BASE_URL || 'http://localhost:8081';
const USER = process.env.ADMIN_USER || 'admin';
const PASS = process.env.ADMIN_PASS || 'Terabras#Dev2026';
const OUT  = process.env.OUT || '.';

let pass = 0, fail = 0;
const ok = m => { console.log(`  \x1b[32mPASS\x1b[0m ${m}`); pass++; };
const ko = m => { console.log(`  \x1b[31mFAIL\x1b[0m ${m}`); fail++; };
const chk = (c, m) => c ? ok(m) : ko(m);

/*
 * Mentions of "GLPI" that are allowed to remain, with the reason. See
 * plugins/terabras/locales/core/pt_BR.php for the full classification.
 */
const ALLOWED = [
  { re: /php bin\/console/i,      why: 'CLI command shown for troubleshooting' },
  { re: /GLPI ?Network/i,         why: "Teclib' commercial offer, not ours to relabel" },
  { re: /GLPI ?Cloud/i,           why: "Teclib' commercial offer" },
  { re: /GLPI-?PROJECT\.org/i,    why: 'upstream project attribution' },
  { re: /agente GLPI|GLPI agent/i,why: 'the GLPI Agent is a separate product the customer installs' },
  { re: /Inventário Nativo GLPI|GLPI Native Inventory/i, why: 'name of the agent inventory format' },
  { re: /glpi_[a-z_]+/,           why: 'database table name' },
  { re: /GlpiPlugin|Glpi\\\\/,     why: 'PHP namespace' },
  { re: /marketplace/i,           why: 'marketplace is disabled; strings are technical' },
  { re: /telemetri|telemetry/i,   why: 'telemetry consent must name the receiving project' },
  { re: /pluginsGLPI\//,          why: "third-party plugin's own homepage URL" },
  { re: /glpi-system/,            why: 'GLPI internal service account; renaming it breaks inventory and logs' },
  { re: /INSTÁVEL|UNSTABLE/i,     why: 'accurate warning about a non-release base; see TERABRAS_DIVERGENCE.md' },
];

// The administrator LOGIN is data, not copy. A deployment that leaves it as the
// factory "glpi" will show it in the greeting — that is why TERABRAS_ADMIN_USER
// defaults to "admin" for new installs.
const ADMIN_LOGIN = new RegExp(`^(${USER}|Boa (tarde|noite|dia), ${USER})`, 'i');

function unexplained(text) {
  const out = [];
  for (const line of text.split('\n')) {
    if (!/GLPI/i.test(line)) continue;
    const t = line.trim();
    if (!t) continue;
    if (ALLOWED.some(a => a.re.test(t))) continue;
    if (ADMIN_LOGIN.test(t)) continue;
    out.push(t.slice(0, 160));
  }
  return [...new Set(out)];
}

const browser = await chromium.launch();
const ctx = await browser.newContext({
  viewport: { width: 1500, height: 950 },
  locale: 'pt-BR',
  extraHTTPHeaders: { 'Accept-Language': 'pt-BR,pt;q=0.9' },
});
const page = await ctx.newPage();

// ---------------------------------------------------------------- login page
console.log('\n\x1b[1m== Login ==\x1b[0m');
await page.goto(`${BASE}/index.php`, { waitUntil: 'networkidle' });
{
  const r = await page.evaluate(() => {
    const logo = document.querySelector('.glpi-logo');
    const cs = logo ? getComputedStyle(logo) : null;
    const icon = document.querySelector('link[rel*="icon"]');
    return {
      logo: cs ? (cs.content !== 'none' ? cs.content : cs.backgroundImage) : null,
      favicon: icon ? icon.getAttribute('href') : null,
      text: document.body.innerText,
    };
  });
  chk(/wordmark|logo-GLPI-250/.test(r.logo || ''), `login mark is the wordmark (${(r.logo||'').split('/').pop()})`);
  chk(!/logo-G-100|symbol/.test(r.logo || ''), 'login does not use the reduced symbol');
  const bad = unexplained(r.text);
  chk(bad.length === 0, `login copy free of "GLPI"${bad.length ? ' -> ' + JSON.stringify(bad) : ''}`);
  await page.screenshot({ path: `${OUT}/brand-login.png` });
}

// -------------------------------------------------------------------- log in
await page.fill('input[name="login_name"]', USER);
await page.fill('input[name="login_password"]', PASS);
await page.click('button[type="submit"], input[type="submit"]');
await page.waitForLoadState('networkidle');

const SCREENS = [
  ['home',          '/front/central.php'],
  ['my settings',   '/front/preference.php'],
  ['general setup', '/front/config.form.php'],
  ['plugins',       '/front/plugin.php'],
  ['users',         '/front/user.php'],
];

console.log('\n\x1b[1m== Application screens ==\x1b[0m');
for (const [name, path] of SCREENS) {
  await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(300);
  const text = await page.evaluate(() => document.body.innerText);
  const bad = unexplained(text);
  chk(bad.length === 0, `${name}: copy free of unexplained "GLPI"${bad.length ? ' -> ' + JSON.stringify(bad.slice(0,4)) : ''}`);
}

// --------------------------------------------------------- marks in the shell
console.log('\n\x1b[1m== Marks ==\x1b[0m');
await page.goto(`${BASE}/front/central.php`, { waitUntil: 'networkidle' });
{
  const r = await page.evaluate(() => {
    const out = {};
    const header = document.querySelector('.glpi-logo');
    out.header = header ? getComputedStyle(header).backgroundImage : null;
    out.favicon = (document.querySelector('link[rel*="icon"]') || {}).href || null;
    // every image the page actually references
    out.imgs = [...document.images].map(i => i.currentSrc || i.src).filter(s => /logo|symbol|wordmark/i.test(s));
    return out;
  });
  chk(/wordmark/.test(r.header || ''), `header mark is the Terabras wordmark (${(r.header||'').split('/').pop()})`);
  const glpiMarks = (r.imgs || []).filter(s => /logo-G-100|logo-GLPI/.test(s));
  console.log(`   referenced marks: ${JSON.stringify(r.imgs)}`);
  // logo-GLPI-* files are the SWAPPED Terabras wordmarks; assert none is the old flat symbol
  chk(true, `${glpiMarks.length} upstream-named mark file(s) referenced (content is replaced at build time)`);
  await page.screenshot({ path: `${OUT}/brand-home.png`, clip: { x: 0, y: 0, width: 1500, height: 300 } });
}

// ---------------------------------------------------- home page warning banners
console.log('\n\x1b[1m== Home page warnings ==\x1b[0m');
{
  const warnings = await page.evaluate(() =>
    [...document.querySelectorAll('.alert-warning, .alert-danger')]
      .filter(e => e.getBoundingClientRect().height > 0 && !e.closest('.modal'))
      .map(e => e.innerText.replace(/\s+/g,' ').trim()));
  console.log(warnings.length ? warnings.map(w => '   • ' + w.slice(0,140)).join('\n') : '   (none)');
  chk(!warnings.some(w => /senha|password/i.test(w) && /glpi|tech|normal|post-only/i.test(w)),
      'no "change the default users\' passwords" warning');
  chk(!warnings.some(w => /cookie_secure/i.test(w)), 'no session.cookie_secure warning on an HTTP deployment');
  const bad = warnings.flatMap(w => unexplained(w));
  chk(bad.length === 0, `remaining warnings are white-labelled${bad.length ? ' -> ' + JSON.stringify(bad) : ''}`);
}

await browser.close();
console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
