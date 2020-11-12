<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskAuthorizationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('task_authorizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_user_id')->nullable();
            $table->unsignedBigInteger('task_user_id');
            $table->integer('authorized_by');
            $table->integer('entire_job')->default(0);
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
        Schema::dropIfExists('task_authorizations');
    }
}
