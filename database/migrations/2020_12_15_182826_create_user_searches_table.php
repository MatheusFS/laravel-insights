<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserSearchesTable extends Migration {
    
    public function up() {

        Schema::create('user_searches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('guard');
            $table->unsignedBigInteger('user_id');
            $table->string('query_string');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down() {

        Schema::dropIfExists('user_searches');
    }
}
