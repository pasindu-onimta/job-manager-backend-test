<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_no');
            $table->string('job')->nullable();
            $table->longText('job_description');
            $table->string('customer_name')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('system')->nullable();
            $table->string('system_id')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('priority')->nullable();
            $table->date('due_date');
            $table->integer('status')->default(1);
            $table->integer('last_id')->nullable();
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
        Schema::dropIfExists('jobs');
    }
}
