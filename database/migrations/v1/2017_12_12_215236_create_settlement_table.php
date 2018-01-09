<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSettlementTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('settlement', function (Blueprint $table) {
            $table->increments('settle_id');
            $table->integer('account_id');
            $table->bigInteger('start_time');
            $table->bigInteger('end_time');
            $table->bigInteger('settle_time');
            $table->integer('amount_in_cent');
            $table->integer('comm_in_cent');
            $table->string('currency', 16);
            $table->integer('txn_num');
            $table->integer('is_remitted');
            $table->bigInteger('remitted_at');
            $table->string('remitted_by', 16);
            $table->string('notes')->nullable();
            $table->bigInteger('updated_at');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('settlement');
    }
}
