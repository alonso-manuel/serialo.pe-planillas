// Video vertical para TikTok: «¿Construcción civil y minería?» (solo textos, sin grabar el sistema).
// Uso: node regimenes.js                       → videos/regimenes-especiales.mp4
//      VOZ=regimenes-especiales.mp3 node regimenes.js   → además une la voz en off (mp3 en la carpeta videos/)
const { montar, aMp4, conVoz, archivo, SALIDA } = require('./anuncios');
const fs = require('fs');

void (async () => {
    fs.mkdirSync(SALIDA, { recursive: true });
    const webm = await montar('regimenes-especiales', 'regimenes.html', null);
    const mp4 = aMp4(webm);
    console.log('✓', mp4);
    if (process.env.VOZ) {
        console.log('✓', conVoz(mp4, archivo(process.env.VOZ)));
    }
})();
