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
            $table->string('vendor_txn_id', 64)->nullable(true);
            $table->bigInteger('vendor_txn_time');
            $table->string('txn_scenario', 16)->nullable(true);;
            $table->integer('txn_fee_in_cent');
            $table->string('txn_fee_currency', 16);
            $table->integer('paid_fee_in_cent');
            $table->string('paid_fee_currency', 16);
            $table->string('exchange_rate',16)->nullable(true);
            $table->string('customer_id', 128)->nullable(true);
            //$table->integer('refund_no');
            $table->string('txn_link_id',32)->nullable(true);
            $table->string('status', 16);
            $table->string('device_id', 64)->nullable(true);
            $table->integer('user_id');

            $table->unique(['ref_id']);
            $table->index('account_id');
            $table->index('vendor_txn_time');
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
