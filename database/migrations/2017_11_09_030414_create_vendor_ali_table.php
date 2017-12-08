<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVendorAliTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendor_ali', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('account_id');
            $table->integer('rate');
            $table->string('sub_mch_id',32);
            $table->string('sub_mch_name',128);
            $table->string('sub_mch_industry',4);
            $table->bigInteger('valid_from');
            $table->bigInteger('valid_to');
            $table->string('status', 16);
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
        Schema::dropIfExists('vendor_ali');
    }
}
