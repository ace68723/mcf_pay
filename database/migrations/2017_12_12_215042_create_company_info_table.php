<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCompanyInfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('company_info', function (Blueprint $table) {
            $table->integer('account_id');
            $table->string('display_name');
            $table->string('legal_name');
            $table->string('contact_person');
            $table->string('email');
            $table->string('cell');
            $table->string('address');
            $table->string('city', 32);
            $table->string('province', 16);
            $table->string('postal', 16);
            $table->string('timezone', 16);
            $table->integer('is_deleted')->default(0);
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
        Schema::dropIfExists('company_info');
    }
}
