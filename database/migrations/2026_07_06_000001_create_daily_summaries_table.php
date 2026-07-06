<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('branch_id')->index();

            // Identificación del resumen
            $table->string('correlativo', 8);
            $table->string('numero_completo')->nullable();

            // Fechas
            $table->date('fecha_generacion');
            $table->date('fecha_resumen');

            // Configuración
            $table->string('ubl_version', 3)->default('2.1');
            $table->string('moneda', 3)->default('PEN');

            // Detalle de comprobantes incluidos en el resumen
            $table->json('detalles');

            // Estado del proceso interno (GENERADO, ENVIADO, COMPLETADO, ERROR)
            $table->string('estado_proceso', 20)->default('GENERADO');

            // Estado SUNAT
            $table->string('estado_sunat', 20)->default('PENDIENTE');
            $table->string('ticket')->nullable();
            $table->string('codigo_hash')->nullable();

            // Archivos generados
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();

            $table->text('respuesta_sunat')->nullable();

            // Auditoría
            $table->string('usuario_creacion')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'fecha_resumen', 'correlativo']);
            $table->index(['company_id', 'branch_id']);
            $table->index(['fecha_resumen']);
            $table->index(['estado_sunat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_summaries');
    }
};
