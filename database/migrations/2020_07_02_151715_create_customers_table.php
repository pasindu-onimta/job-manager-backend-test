<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code');
            $table->string('customer_name');
            $table->integer('last_id')->nullable();

            /*
            * Date 2020/07/15 10.35 AM
            * Dilan Jayamuni
            *
            * add new columns to customer table
            */
            $table->string('address')->nullable();
            $table->string('owner')->nullable();
            $table->string('contact_person')->nullable();
            $table->integer('mobile_number')->nullable();
            $table->integer('official_email')->nullable();
            $table->integer('site')->nullable();
            $table->string('software_coordinator')->nullable();
            $table->string('marketing_coordinator')->nullable();
            $table->string('account_coordinator')->nullable();
            $table->string('customer_logo')->nullable();
            $table->boolean('agreement')->default(1);
            $table->date('expire_date')->nullable();
            /*
            * Date 2020/07/15 10.35 AM
            * Dilan Jayamuni
            *
            * end
            */
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customers');
    }
}
