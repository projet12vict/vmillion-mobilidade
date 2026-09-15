<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogAuditoria extends Model
{
    protected $table = 'logs_auditoria';

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = ['admin_id', 'acao', 'entidade', 'entidade_id', 'detalhes', 'ip'];

    protected function casts(): array
    {
        return ['criado_em' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Administrador::class, 'admin_id');
    }
}
