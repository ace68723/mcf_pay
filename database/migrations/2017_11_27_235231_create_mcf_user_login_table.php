<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMcfUserLoginTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mcf_user_login', function (Blueprint $table) {
            $table->integer('uid');
            $table->string('version', 10);
            $table->timestamp('lastlogin');
            $table->string('lat',15);
            $table->string('lng',15);
            $table->string('merchant_id',45);

            $table->primary('uid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mcf_user_login');
    }
}
