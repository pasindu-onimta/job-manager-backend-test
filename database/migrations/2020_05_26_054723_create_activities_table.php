<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActivitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('task_user_id');
            $table->unsignedInteger('task_id');
            $table->unsignedInteger('user_id');
            $table->integer('prev_section_id');
            $table->integer('section_id');
            $table->integer('qc_status')->default(0);
            $table->integer('is_qc_task')->default(0);
            $table->string('description')->nullable();
            $table->string('job_description')->nullable();
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
        Schema::dropIfExists('activities');
    }
}
