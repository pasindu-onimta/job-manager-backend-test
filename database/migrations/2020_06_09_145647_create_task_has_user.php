<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskHasUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('task_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('job_user_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('task_id');
            $table->unsignedInteger('qc_id');
            $table->string('priority');
            $table->date('assign_date');
            $table->integer('assign_by');
            $table->date('due_date');
            $table->date('plan_date')->nullable();
            $table->integer('status')->default(1);
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
        Schema::dropIfExists('task_has_user');
    }
}
