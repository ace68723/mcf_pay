<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMcfUserBaseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mcf_user_base', function (Blueprint $table) {
            $table->increments('uid');
            $table->string('username', 45);
            $table->string('password', 256);
            $table->integer('account_id')->nullable();
            $table->integer('role');
            $table->integer('is_deleted')->default(0);
            $table->string('saltstring', 64);
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['account_id','username']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mcf_user_base');
    }
}
