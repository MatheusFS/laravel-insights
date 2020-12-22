<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserLoginsTable extends Migration {
    
    public function up() {

        Schema::create('user_logins', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('guard');
            $table->unsignedBigInteger('user_id');
            $table->string('ip_address');
            $table->string('browser');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down() {

        Schema::dropIfExists('user_logins');
    }
}
