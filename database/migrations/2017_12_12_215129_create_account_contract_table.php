<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountContractTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_contract', function (Blueprint $table) {
            $table->integer('account_id');
            $table->integer('device_amount');
            $table->string('tip_mode');
            $table->string('contract_price');
            $table->integer('remit_min_in_cent');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('is_deleted')->default(0);
            $table->string('note');
            $table->string('bank_instit');
            $table->string('bank_transit');
            $table->string('bank_account');
            $table->timestamp('updated_at')->useCurrent();

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
        Schema::dropIfExists('account_contract');
    }
}
