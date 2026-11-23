<?php

namespace App\Services\Opiniones;

use App\Models\Central\Estudio;
use App\Models\Central\Opinion;
use App\Models\User;
use App\Support\ModoSistema;

/**
 * Modo SaaS: la ventana que pide al usuario su comentario sobre el sistema. Aparece UNA sola vez por usuario
 * (se marca al mostrarse, la responda o no) y solo cuando ya lleva unos días con su cuenta, para que opine
 * con algo de uso. Después puede escribir cuando quiera desde el menú de su usuario.
 * El acceso de soporte de Serialo.pe no la ve ni envía comentarios.
 */
class PedidoOpinion
{
    /** Días desde que se creó el usuario antes de pedirle su opinión. */
    public const DIAS_DE_USO = 3;

    /** ¿Puede enviar comentarios? (modo SaaS, dentro de un estudio, no es soporte). */
    public function habilitada(?User $usuario): bool
    {
        return $usuario !== null && ModoSistema::saas() && tenant() instanceof Estudio && ! $usuario->es_soporte;
    }

    /** ¿Hay que abrirle la ventana ahora? */
    public function debePedir(?User $usuario): bool
    {
        return $this->habilitada($usuario)
            && $usuario->opinion_pedida_en === null
            && ($usuario->created_at === null || $usuario->created_at->lte(now()->subDays(self::DIAS_DE_USO)));
    }

    /** La ventana ya se mostró: no vuelve a aparecer sola. */
    public function marcarMostrada(User $usuario): void
    {
        if ($usuario->opinion_pedida_en !== null) {
            return;
        }

        // Directo, sin auditoría ni updated_at: no es un cambio de datos del usuario.
        User::query()->whereKey($usuario->id)->toBase()->update(['opinion_pedida_en' => now()]);
        $usuario->opinion_pedida_en = now()->toImmutable();
    }

    /** Guarda el comentario en la base central, con copia de quién lo envió y desde qué cuenta. */
    public function guardar(User $usuario, string $comentario, ?int $calificacion): Opinion
    {
        /** @var Estudio $estudio */
        $estudio = tenant();

        $opinion = Opinion::query()->create([
            'estudio_id' => $estudio->getTenantKey(),
            'estudio_nombre' => $estudio->nombre,
            'usuario_id' => $usuario->id,
            'usuario_nombre' => $usuario->name,
            'usuario_correo' => $usuario->email,
            'usuario_rol' => $usuario->rol->value,
            'calificacion' => $calificacion,
            'comentario' => $comentario,
        ]);

        $this->marcarMostrada($usuario);

        return $opinion;
    }
}
