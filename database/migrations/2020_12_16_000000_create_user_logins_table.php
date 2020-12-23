<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserLoginsTable extends Migration {

    const TABLE_NAME = 'user_logins';
    
    public function up() {

        if (!Schema::hasTable(self::TABLE_NAME)) {
            
            Schema::create(self::TABLE_NAME, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('guard');
                $table->unsignedBigInteger('user_id');
                $table->string('ip_address');
                $table->string('browser');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down() {

        Schema::dropIfExists(self::TABLE_NAME);
    }
}
