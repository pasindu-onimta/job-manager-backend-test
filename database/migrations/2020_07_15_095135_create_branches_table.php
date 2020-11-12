<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBranchesTable extends Migration
{
    /*
     * Date 2020/07/15 10.41 AM
     * Dilan Jayamuni
     *
     * create branch customer table
    */
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('agreement_number');
            $table->integer('agreement_type');
            $table->boolean('warranty_period');
            $table->date('valid_from');
            $table->date('valid_to');
            $table->string('branch_code')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('branch_address')->nullable();
            $table->string('branch_email')->nullable();
            $table->string('branch_contact_person')->nullable();
            $table->string('branch_mobile_no')->nullable();
            $table->string('branch_phone_no')->nullable();
            $table->integer('pos_count')->nullable();
            $table->integer('server_count')->nullable();
            $table->integer('terminal_count')->nullable();
            $table->integer('status');
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
        Schema::dropIfExists('branches');
    }
}
