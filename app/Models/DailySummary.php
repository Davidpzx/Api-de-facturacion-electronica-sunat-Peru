<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailySummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'correlativo',
        'numero_completo',
        'fecha_generacion',
        'fecha_resumen',
        'ubl_version',
        'moneda',
        'detalles',
        'estado_proceso',
        'estado_sunat',
        'ticket',
        'codigo_hash',
        'xml_path',
        'cdr_path',
        'respuesta_sunat',
        'usuario_creacion',
    ];

    protected $casts = [
        'fecha_generacion' => 'date',
        'fecha_resumen' => 'date',
        'detalles' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function boletas(): HasMany
    {
        return $this->hasMany(Boleta::class, 'daily_summary_id');
    }

    public function getEstadoSunatColorAttribute(): string
    {
        return match($this->estado_sunat) {
            'PENDIENTE' => 'warning',
            'PROCESANDO' => 'info',
            'ACEPTADO' => 'success',
            'RECHAZADO' => 'danger',
            default => 'secondary'
        };
    }

    public function scopePending($query)
    {
        return $query->where('estado_sunat', 'PENDIENTE');
    }

    public function scopeAccepted($query)
    {
        return $query->where('estado_sunat', 'ACEPTADO');
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('fecha_resumen', [$startDate, $endDate]);
    }
}
