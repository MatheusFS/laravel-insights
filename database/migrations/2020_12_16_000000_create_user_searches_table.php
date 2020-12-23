<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserSearchesTable extends Migration {
    
    const TABLE_NAME = 'user_searches';

    public function up() {

        if (!Schema::hasTable(self::TABLE_NAME)) {

            Schema::create(self::TABLE_NAME, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('guard');
                $table->unsignedBigInteger('user_id');
                $table->string('query_string');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down() {

        Schema::dropIfExists(self::TABLE_NAME);
    }
}
