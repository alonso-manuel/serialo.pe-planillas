<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { privacidad } from '@/routes/legal';
import type { DatosLegales } from '@/types/legal';

defineProps<{ legal: DatosLegales }>();
</script>

<template>
    <Head title="Términos del servicio" />

    <article
        class="space-y-4 text-sm leading-relaxed [&_a]:underline [&_h1]:text-2xl [&_h1]:font-semibold [&_h2]:pt-4 [&_h2]:text-base [&_h2]:font-semibold [&_ul]:list-disc [&_ul]:space-y-2 [&_ul]:pl-5"
    >
        <h1>Términos del servicio</h1>
        <p class="text-xs text-muted-foreground">
            Versión del {{ legal.version }}
        </p>

        <p>
            Estos términos regulan el uso de Serialo Planillas, un software de
            Serialo.pe para calcular planillas en el Perú. Al ingresar o usar el
            sistema, la empresa o el estudio contable que lo usa (el «cliente»)
            y sus usuarios aceptan estos términos y la
            <Link :href="privacidad()">Política de privacidad</Link>.
        </p>

        <h2>1. Qué es el sistema</h2>
        <p>
            Serialo Planillas es una <strong>herramienta de software</strong>:
            ejecuta cálculos a partir de la normativa laboral y tributaria
            vigente, de los parámetros legales configurados (RMV, UIT, tasas,
            códigos de SUNAT) y de los datos que ingresan los usuarios. No es un
            servicio de asesoría legal, contable ni tributaria, y su uso no
            reemplaza el criterio de un profesional.
        </p>

        <template v-if="$page.props.beta">
            <h2>Versión beta</h2>
            <p>
                Serialo Planillas está en <strong>etapa beta</strong>: sus
                funciones principales están disponibles, pero el sistema sigue
                en evaluación y puede cambiar, mejorar o corregirse sin previo
                aviso. Durante la beta es aún más importante revisar los
                cálculos y los archivos antes de pagar o declarar, y reportar a
                Serialo.pe cualquier diferencia que se encuentre. Lo dicho en
                estos términos sobre la responsabilidad del cliente aplica
                plenamente durante la beta.
            </p>
        </template>

        <h2>2. Responsabilidad del cliente</h2>
        <p>
            <strong
                >La revisión, la validación y la declaración de la información
                son responsabilidad exclusiva del cliente.</strong
            >
            En particular, el cliente se encarga de:
        </p>
        <ul>
            <li>
                Que los datos que ingresa sean correctos y completos:
                trabajadores, remuneraciones, asistencia, faltas, novedades,
                ceses y conceptos.
            </li>
            <li>
                Revisar los cálculos, las boletas, las liquidaciones y los
                archivos antes de cerrar un periodo, pagar o declarar.
            </li>
            <li>
                Presentar las declaraciones y pagos ante SUNAT, AFPnet, EsSalud
                y demás entidades, en los plazos legales, y atender a Sunafil u
                otra autoridad.
            </li>
            <li>
                Revisar los parámetros legales y los puntos que el sistema marca
                «por verificar», y actualizar los valores cuando cambien las
                normas.
            </li>
            <li>
                Cumplir la Ley N.º 29733 como responsable de los datos de sus
                trabajadores, y custodiar sus usuarios, contraseñas y
                certificados digitales.
            </li>
        </ul>

        <h2>3. Límite de responsabilidad de Serialo.pe</h2>
        <p>
            Serialo.pe pone todo su cuidado en que los cálculos sigan la norma
            vigente, pero no responde por multas, intereses, reparos, reclamos
            ni daños que resulten de datos ingresados de forma incorrecta o
            incompleta, de conceptos omitidos, de cálculos no revisados, de
            declaraciones o pagos fuera de plazo, ni de interpretaciones de la
            norma distintas a las aplicadas. En ningún caso la responsabilidad
            de Serialo.pe excederá el monto pagado por el cliente por el
            servicio en los doce meses anteriores al hecho que la origine.
        </p>

        <h2>4. Licencia y uso aceptable</h2>
        <p>
            El cliente recibe una licencia de uso no exclusiva e intransferible,
            según el plan contratado y sus límites. No está permitido copiar,
            revender, descompilar ni modificar el software, ni usarlo para fines
            ilícitos o para tratar datos sin el consentimiento o la base legal
            que exige la ley.
        </p>

        <h2>5. Disponibilidad y respaldos</h2>
        <p>
            El sistema realiza respaldos automáticos diarios de la base de
            datos. Aun así, el cliente debe descargar periódicamente una copia y
            guardarla fuera del servidor. Si la licencia o el plan vencen, el
            sistema pasa a solo lectura: el cliente puede consultar y descargar
            su información, que no se elimina.
        </p>

        <h2>6. Cambios en el sistema y en estos términos</h2>
        <p>
            Serialo.pe puede actualizar el sistema para adecuarlo a cambios
            normativos o mejorarlo, y modificar estos términos; la versión
            vigente es la publicada en esta página, con su fecha.
        </p>

        <h2>7. Ley aplicable</h2>
        <p>Estos términos se rigen por las leyes de la República del Perú.</p>
    </article>
</template>
