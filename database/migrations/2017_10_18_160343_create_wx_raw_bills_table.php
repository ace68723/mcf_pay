<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWxRawBillsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wx_raw_bills', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('transaction_time')->comment('Transaction Time');
            $table->string('appid', 32)->comment('Official account ID');
            $table->string('mch_id', 32)->comment('Vendor ID');
            $table->string('sub_mch_id', 32)->comment('Sub vendor ID');
            $table->string('device_info', 32)->comment('Device ID');
            $table->string('transaction_id', 32)->comment('Wechat order number');
            $table->string('out_transaction_id', 32)->comment('Vendor order number');
            $table->string('openid', 128)->comment('User tag');
            $table->string('trade_type', 16)->comment('Transaction type');
            $table->string('trade_state', 16)->comment('Transaction status');
            $table->string('bank_type', 16)->comment('Payment bank');
            $table->string('fee_type', 16)->comment('Currency type');
            $table->integer('total_fee')->comment('Total amount');
            $table->integer('coupon_amount');
            $table->string('refund_id', 32)->comment('Wechat refund number');
            $table->string('out_refund_no', 32)->comment('Vendor refund number');
            $table->integer('refund_fee')->comment('Refund amount');
            $table->integer('coupon_refund_amount');
            $table->string('refund_type', 16)->nullable();
            $table->string('refund_status_n', 16)->comment('Refund status')->nullable();
            $table->string('product_name', 256)->nullable();
            $table->string('attach', 127)->comment("Vendor's data package")->nullable();
            $table->integer('fee');
            $table->integer('rate');
            $table->string('cash_fee_type', 16)->comment('Payment Currency type');
            $table->integer('cash_fee')->comment('Cash payment amount');
            $table->string('settlement_currency_type', 16);
            $table->integer('settlement_currency_amount');
            $table->integer('exchange_rate')->comment('in e-8');
            $table->integer('refund_exchange_rate')->comment('in e-8');
            $table->integer("payers_refund_amount");
            $table->string("payers_refund_currency_type", 16)->nullable();
            $table->string("refund_currency_type", 16)->nullable();
            $table->string('refund_settlement_currency_type', 16)->nullable();
            $table->integer('refund_settlement_amount');

            $table->index(['transaction_time']);
            $table->index(['appid','mch_id','sub_mch_id']);
            $table->unique(['transaction_id','refund_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wx_raw_bills');
    }
}
