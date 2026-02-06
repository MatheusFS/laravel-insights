<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Upgrade existing user_logins table to v1.1
     * 
     * Adiciona campos para tracking de failed logins e device detection
     * 100% backward compatible - mantém todos os campos v1.0
     */
    public function up(): void
    {
        if (!Schema::hasTable('user_logins')) {
            throw new \Exception('Table user_logins does not exist. Run base migration first.');
        }

        Schema::table('user_logins', function (Blueprint $table) {
            // Novos campos v2.0
            if (!Schema::hasColumn('user_logins', 'email')) {
                $table->string('email')->nullable()->after('user_id');
            }
            
            if (!Schema::hasColumn('user_logins', 'success')) {
                $table->boolean('success')->default(true)->after('email');
            }
            
            if (!Schema::hasColumn('user_logins', 'failure_reason')) {
                $table->string('failure_reason')->nullable()->after('success');
            }
            
            if (!Schema::hasColumn('user_logins', 'device_type')) {
                $table->string('device_type', 50)->nullable()->after('browser');
            }

            // Tornar user_id nullable (para failed logins sem autenticação)
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        // Adicionar aliases de colunas via computed columns (MySQL 5.7+)
        // Permite usar tanto os nomes antigos quanto novos
        try {
            // ip_address → ip (alias)
            if (!Schema::hasColumn('user_logins', 'ip')) {
                DB::statement('ALTER TABLE user_logins ADD COLUMN ip VARCHAR(45) GENERATED ALWAYS AS (ip_address) STORED');
            }
            
            // browser → user_agent (alias) 
            if (!Schema::hasColumn('user_logins', 'user_agent')) {
                DB::statement('ALTER TABLE user_logins ADD COLUMN user_agent VARCHAR(255) GENERATED ALWAYS AS (browser) STORED');
            }
        } catch (\Exception $e) {
            // Se falhar (ex: MySQL < 5.7), apenas loga warning
            // Apps continuarão usando os campos originais
            logger()->warning('Could not create computed columns for user_logins: ' . $e->getMessage());
        }

        // Adicionar indexes para performance
        Schema::table('user_logins', function (Blueprint $table) {
            if (!$this->indexExists('user_logins', 'user_logins_user_id_created_at_index')) {
                $table->index(['user_id', 'created_at']);
            }
            
            if (!$this->indexExists('user_logins', 'user_logins_ip_address_created_at_index')) {
                $table->index(['ip_address', 'created_at']);
            }
            
            if (!$this->indexExists('user_logins', 'user_logins_email_success_index')) {
                $table->index(['email', 'success']);
            }
            
            if (!$this->indexExists('user_logins', 'user_logins_created_at_index')) {
                $table->index('created_at');
            }
        });

        // Foreign key opcional (se configurado)
        if (config('insights.foreign_keys.enabled', false)) {
            Schema::table('user_logins', function (Blueprint $table) {
                if (!$this->foreignKeyExists('user_logins', 'user_logins_user_id_foreign')) {
                    $table->foreign('user_id', 'user_logins_user_id_foreign')
                        ->references('id')
                        ->on(config('insights.users_table', 'users'))
                        ->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_logins', function (Blueprint $table) {
            // Remover foreign key
            if ($this->foreignKeyExists('user_logins', 'user_logins_user_id_foreign')) {
                $table->dropForeign('user_logins_user_id_foreign');
            }

            // Remover indexes
            if ($this->indexExists('user_logins', 'user_logins_user_id_created_at_index')) {
                $table->dropIndex('user_logins_user_id_created_at_index');
            }
            if ($this->indexExists('user_logins', 'user_logins_ip_address_created_at_index')) {
                $table->dropIndex('user_logins_ip_address_created_at_index');
            }
            if ($this->indexExists('user_logins', 'user_logins_email_success_index')) {
                $table->dropIndex('user_logins_email_success_index');
            }
            if ($this->indexExists('user_logins', 'user_logins_created_at_index')) {
                $table->dropIndex('user_logins_created_at_index');
            }

            // Remover computed columns
            if (Schema::hasColumn('user_logins', 'ip')) {
                DB::statement('ALTER TABLE user_logins DROP COLUMN ip');
            }
            if (Schema::hasColumn('user_logins', 'user_agent')) {
                DB::statement('ALTER TABLE user_logins DROP COLUMN user_agent');
            }

            // Remover novos campos
            $table->dropColumn(['email', 'success', 'failure_reason', 'device_type']);
        });
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);
        return !empty($indexes);
    }

    /**
     * Check if foreign key exists
     */
    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $database = DB::getDatabaseName();
        $exists = DB::select(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$database, $table, $foreignKey]
        );
        return !empty($exists);
    }
};
