<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserPageviewsTable extends Migration {
    
    public function up() {

        Schema::create('user_pageviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('guard');
            $table->string('user_id');
            $table->string('ip_address');
            $table->string('browser');
            $table->integer('screen_width');
            $table->integer('screen_height');
            $table->string('page');
            $table->string('referrer');
            $table->integer('seconds_spent');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down() {

        Schema::dropIfExists('user_pageviews');
    }
}
