<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('job_id');
            $table->string('task_no');
            $table->string('task_name');
            $table->string('task_description');
            $table->string('Time01');
            $table->string('Time02');
            $table->string('Time03');
            $table->string('TotTime01');
            $table->string('TotTime02');
            $table->string('TotTime03');
            $table->integer('last_id')->nullable();
            $table->integer('isAssigned')->default(0);
            $table->integer('current_status')->default(1);
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
        Schema::dropIfExists('tasks');
    }
}
