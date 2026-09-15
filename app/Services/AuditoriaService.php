<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LogAuditoria;
use Illuminate\Support\Facades\Request;

/**
 * Registo de auditoria das ações de backoffice. Porta de kg_log_auditoria().
 */
class AuditoriaService
{
    public function registar(
        ?int $adminId,
        string $acao,
        ?string $entidade = null,
        ?int $entidadeId = null,
        ?string $detalhes = null
    ): void {
        LogAuditoria::query()->create([
            'admin_id' => $adminId,
            'acao' => $acao,
            'entidade' => $entidade,
            'entidade_id' => $entidadeId,
            'detalhes' => $detalhes,
            'ip' => Request::ip(),
        ]);
    }
}
