<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSettleDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('settle_detail', function (Blueprint $table) {
            $table->integer('settle_id');
            $table->integer('vendor_channel');
            $table->integer('amount_in_cent');
            $table->string('amount_currency',16);
            $table->integer('txn_nums');

            $table->unique(['settle_id','vendor_channel']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('settle_detail');
    }
}
