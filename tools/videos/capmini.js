const { chromium } = require('playwright');
const { cambiarEmpresa } = require('./lib');
void (async () => {
    const b = await chromium.launch();
    const p = await b.newPage({
        viewport: { width: 1280, height: 760 },
        deviceScaleFactor: 2,
    });
    await p.goto('http://127.0.0.1:8000/login');
    await p.fill('input[name=email]', 'demo@serialo.pe');
    await p.fill('input[name=password]', 'demo1234');
    await p.click('button[type=submit]');
    await p.waitForURL((u) => !u.pathname.startsWith('/login'));
    await p.goto('http://127.0.0.1:8000/periodos');
    await cambiarEmpresa(p, 'Distribuidora');
    await p.getByText('Noviembre 2026').first().click();
    await p.waitForURL((u) => /periodos.[0-9]+/.test(u.pathname));
    await p.waitForTimeout(800);
    await p.waitForLoadState('networkidle');
    await p.screenshot({ path: 'mini-grilla.png' });
    await b.close();
})();
