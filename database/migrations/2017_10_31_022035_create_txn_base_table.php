<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTxnBaseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('txn_base', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ref_id',32);
            $table->boolean('is_refund');
            $table->integer('account_id');
            $table->integer('vendor_channel');
            $table->string('vendor_txn_id', 64);
            $table->bigInteger('vendor_txn_time');
            $table->string('txn_scenario', 16);
            $table->integer('txn_fee_in_cent');
            $table->string('txn_fee_currency', 16);
            $table->integer('paid_fee_in_cent');
            $table->string('paid_fee_currency', 16);
            $table->string('exchange_rate',16);
            $table->string('customer_id', 128);
            //$table->integer('refund_no');
            $table->string('txn_link_id',32);
            $table->string('status', 16);

            $table->unique(['ref_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('txn_base');
    }
}
