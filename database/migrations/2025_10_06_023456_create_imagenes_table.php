<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imagenes', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Relación polimórfica
            $table->unsignedBigInteger('imageable_id');
            $table->string('imageable_type', 255);

            // Ubicación del archivo - tamaños ajustados para índices MySQL
            $table->string('disk', 64);                  // antes: 255
            $table->string('path', 512);                 // antes: 1024 (causaba error de índice)
            $table->string('url', 2048)->nullable();

            // Metadatos
            $table->string('titulo')->nullable();
            $table->enum('visibilidad', ['PUBLICA', 'PRIVADA', 'RESTRINGIDA'])->default('PRIVADA');

            // Autor
            $table->unsignedBigInteger('subido_por');
            $table->foreign('subido_por')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Índices
            $table->index(['imageable_type', 'imageable_id']);
            $table->index('disk');
            $table->index('visibilidad');

            // Índice único corregido (ya no rompe MySQL)
            $table->unique(['disk', 'path'], 'imagenes_disk_path_unique');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imagenes');
    }
};
