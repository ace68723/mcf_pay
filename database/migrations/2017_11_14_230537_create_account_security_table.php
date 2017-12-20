<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountSecurityTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_security', function (Blueprint $table) {
            $table->integer('account_id');
            $table->string('account_key');
            $table->string('account_secret');
            $table->integer('is_deleted')->default(0);
            $table->string('changed_by', 32);
            $table->bigInteger('changed_at');

            $table->primary('account_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account_security');
    }
}
