<?php

namespace App\Services\Licencia;

/** Se quiso crear o reactivar algo por encima del límite del plan. Se muestra como aviso, no rompe nada. */
class LimiteLicenciaExcedido extends \DomainException {}
