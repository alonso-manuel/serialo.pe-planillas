/** Datos de contacto y textos compartidos de la web. */
export const WHATSAPP = '51922475154';
export const WHATSAPP_VISIBLE = '+51 922 475 154';

export function enlaceWhatsapp(mensaje: string): string {
    return `https://wa.me/${WHATSAPP}?text=${encodeURIComponent(mensaje)}`;
}

export const MENSAJE_DEMO = 'Hola, quiero probar Serialo Planillas. ¿Me pueden crear una cuenta de prueba?';
