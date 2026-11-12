<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { DatosLegales } from '@/types/legal';

const props = defineProps<{ legal: DatosLegales }>();

const responsable =
    props.legal.responsable ??
    'la empresa o el estudio contable que usa este sistema';
</script>

<template>
    <Head title="Política de privacidad" />

    <article
        class="space-y-4 text-sm leading-relaxed [&_a]:underline [&_h1]:text-2xl [&_h1]:font-semibold [&_h2]:pt-4 [&_h2]:text-base [&_h2]:font-semibold [&_ul]:list-disc [&_ul]:space-y-2 [&_ul]:pl-5"
    >
        <h1>Política de privacidad y tratamiento de datos personales</h1>
        <p class="text-xs text-muted-foreground">
            Versión del {{ legal.version }} · Ley N.º 29733
        </p>

        <p>
            Serialo Planillas es un sistema de planillas que trata datos
            personales de trabajadores, prestadores de servicios y usuarios.
            Esta política explica qué datos se guardan, para qué, quién los
            recibe y cómo se ejercen los derechos que reconoce la Ley N.º 29733,
            Ley de Protección de Datos Personales, y su reglamento.
        </p>

        <h2>1. Responsable del tratamiento</h2>
        <p>
            El responsable de los datos de los trabajadores y prestadores es
            <strong>{{ responsable }}</strong
            >, que los registra en el sistema para cumplir sus obligaciones como
            empleador o como estudio contable de sus clientes.
            <template v-if="legal.saas">
                Serialo.pe aloja el sistema en sus servidores y actúa como
                <strong>encargado del tratamiento</strong>: trata los datos solo
                para prestar el servicio y por cuenta del responsable.
            </template>
            <template v-else>
                El sistema está instalado en el servidor que contrató el
                responsable; Serialo.pe, como proveedor del software, no accede
                a los datos.
            </template>
        </p>

        <h2>2. Datos que se tratan</h2>
        <ul>
            <li>
                <strong>Trabajadores:</strong> tipo y número de documento,
                nombres, fecha de nacimiento, sexo, correo y celular, datos del
                contrato, cargo, remuneración, sistema de pensiones y CUSPP,
                seguro de salud, cuentas de sueldo y de CTS, asistencia, faltas,
                licencias, vacaciones, préstamos, rentas de otros empleadores y
                los datos que pide SUNAT para el T-Registro.
            </li>
            <li>
                <strong>Datos sensibles:</strong> días de subsidio por
                incapacidad o maternidad, condición de discapacidad y afiliación
                sindical. Se registran solo porque las normas laborales y
                tributarias lo exigen.
            </li>
            <li>
                <strong>Derechohabientes</strong> (incluidos menores de edad):
                documento, nombres, fecha de nacimiento, sexo y vínculo, para su
                inscripción en EsSalud a través del T-Registro.
            </li>
            <li>
                <strong>Prestadores de servicios (cuarta categoría):</strong>
                RUC o documento, nombres y datos de sus recibos por honorarios.
            </li>
            <li>
                <strong>Usuarios del sistema:</strong> nombre, correo,
                contraseña (guardada cifrada, nunca en texto legible), datos del
                doble factor y llaves de acceso, y el registro de sus acciones
                con fecha, hora y dirección IP.
            </li>
            <li>
                <strong>Empresas:</strong> RUC, razón social, domicilio,
                representante legal, logo y, si se registra, su certificado de
                firma digital (guardado cifrado).
            </li>
        </ul>

        <h2>3. Finalidad</h2>
        <p>
            Los datos se usan solo para: calcular la planilla y los beneficios
            sociales; emitir boletas, liquidaciones, constancias y certificados;
            generar los archivos para SUNAT (PLAME y T-Registro), AFPnet y los
            bancos; preparar los reportes y asientos contables; y mantener la
            auditoría y la seguridad del sistema. No se usan para publicidad ni
            para elaborar perfiles comerciales.
        </p>

        <h2>4. Quién recibe los datos</h2>
        <p>
            <strong>Los datos no se venden ni se ceden a terceros</strong> con
            fines comerciales. Solo los reciben:
        </p>
        <ul>
            <li>
                Las entidades a las que el responsable debe declarar por ley
                (SUNAT, AFP, EsSalud, Ministerio de Trabajo y Sunafil) y los
                bancos donde se pagan los sueldos, cuando el propio usuario
                genera y presenta los archivos.
            </li>
            <li>Los trabajadores, cuando reciben su boleta por correo.</li>
            <li>
                Los proveedores que hacen funcionar el servicio, que actúan como
                encargados y solo para ese fin: el servidor donde se aloja el
                sistema, el servicio de correo y, si el responsable lo activa,
                el servicio de consulta de DNI y RUC (recibe solo el número
                consultado).
            </li>
            <li v-if="legal.saas">
                El equipo de soporte de Serialo.pe, solo cuando el administrador
                lo autoriza expresamente y por un máximo de 24 horas; todo lo
                que hace queda en la auditoría.
            </li>
        </ul>

        <h2>5. Seguridad y conservación</h2>
        <p>
            El sistema protege los datos con acceso por usuario y rol, doble
            factor de autenticación, contraseñas y certificados cifrados,
            periodos cerrados que no se pueden modificar, auditoría inalterable
            de cada cambio y respaldos diarios. Los datos laborales se conservan
            durante los plazos que exigen las normas laborales y tributarias; a
            partir de entonces el responsable puede eliminarlos.
        </p>

        <h2>6. Derechos ARCO</h2>
        <p>
            Toda persona puede ejercer sus derechos de
            <strong>acceso</strong>, <strong>rectificación</strong>,
            <strong>cancelación</strong> y <strong>oposición</strong> sobre sus
            datos personales:
        </p>
        <ul>
            <li>
                <strong>Trabajadores, derechohabientes y prestadores:</strong>
                ante su empleador o contratante, que es el responsable de sus
                datos y puede consultarlos, corregirlos o darlos de baja en el
                sistema.
            </li>
            <li>
                <strong>Usuarios:</strong> ante el administrador del sistema de
                su empresa o estudio.
            </li>
            <li>
                <template v-if="legal.correo">
                    Las solicitudes y consultas de privacidad se dirigen a
                    <a :href="`mailto:${legal.correo}`">{{ legal.correo }}</a
                    >, indicando nombre, documento de identidad y el derecho que
                    se quiere ejercer.
                </template>
                <template v-else>
                    Las solicitudes se presentan por escrito al responsable,
                    indicando nombre, documento de identidad y el derecho que se
                    quiere ejercer.
                </template>
            </li>
        </ul>
        <p>
            Las solicitudes se atienden en los plazos de la Ley N.º 29733 y su
            reglamento. Si no son atendidas, se puede presentar un reclamo ante
            la Autoridad Nacional de Protección de Datos Personales del
            Ministerio de Justicia y Derechos Humanos.
        </p>

        <h2>7. Cambios</h2>
        <p>
            Esta política puede actualizarse cuando cambien las normas o el
            sistema; la versión vigente es la que figura en esta página, con su
            fecha.
        </p>
    </article>
</template>
