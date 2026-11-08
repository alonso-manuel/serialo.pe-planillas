const { chromium } = require('playwright');
const path = require('path');
void (async () => {
    const b = await chromium.launch();
    const p = await b.newPage({ viewport: { width: 1080, height: 1920 } });
    await p.goto(
        'file:///' + path.resolve('miniatura.html').split(path.sep).join('/'),
    );
    await p.waitForTimeout(500);
    await p.screenshot({ path: process.argv[2] || 'miniatura.png' });
    await b.close();
})();
