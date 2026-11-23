import { ref } from 'vue';

/** Ventana de comentarios: se abre sola una vez (VentanaOpinion) o desde el menú del usuario. */
export const ventanaOpinionAbierta = ref(false);

export function abrirVentanaOpinion(): void {
    ventanaOpinionAbierta.value = true;
}
