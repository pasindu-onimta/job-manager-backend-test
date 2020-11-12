<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('job_user_id')->nullable();
            $table->unsignedInteger('task_user_id')->nullable();
            $table->unsignedInteger('job_id')->nullable();
            $table->unsignedInteger('assigend_by')->nullable();
            $table->integer('notification_type');
            $table->string('title')->nullable();
            $table->integer('count')->nullable();
            $table->text('description')->nullable();
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
        Schema::dropIfExists('notifications');
    }
}
