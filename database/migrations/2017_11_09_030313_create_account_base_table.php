<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountBaseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_base', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('account_id');
            $table->integer('is_deleted')->default(0);
            $table->string('ref_id', 8);
            $table->string('currency_type', 16);
            $table->string('name',128);
            $table->string('status',16);
            $table->string('changed_by', 32);
            $table->bigInteger('changed_at');

            $table->unique('account_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account_base');
    }
}
