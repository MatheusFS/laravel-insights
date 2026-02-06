<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('ip', 45);
            $table->string('method', 10);
            $table->string('path', 500);
            $table->unsignedSmallInteger('status_code');
            $table->float('response_time_ms')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes para performance e análise de incidentes
            $table->index(['user_id', 'created_at']);
            $table->index(['ip', 'created_at']);
            $table->index(['status_code', 'created_at']);
            $table->index(['path', 'created_at']);
            $table->index('created_at');

            // Foreign key (opcional - configurável via config)
            if (config('insights.foreign_keys.enabled', false)) {
                $table->foreign('user_id')
                    ->references('id')
                    ->on(config('insights.users_table', 'users'))
                    ->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_requests');
    }
};
