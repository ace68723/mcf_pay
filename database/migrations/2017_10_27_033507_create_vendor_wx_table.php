<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVendorWxTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendor_wx', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->integer('account_id');
            $table->integer('rate');
            $table->string('sub_mch_id',32);
            $table->timestamp('valid_from');
            $table->timestamp('valid_to');
            $table->string('status', 16);
            $table->string('changed_by', 32);
            $table->timestamp('changed_at');

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
        Schema::dropIfExists('vendor_wx');
    }
}
