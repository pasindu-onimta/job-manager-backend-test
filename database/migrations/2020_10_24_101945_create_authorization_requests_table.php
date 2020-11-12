<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuthorizationRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('authorization_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_by');
            $table->unsignedBigInteger('request_to');
            $table->unsignedBigInteger('task_user_id');
            $table->unsignedBigInteger('job_user_id');
            $table->unsignedBigInteger('job_id');
            $table->boolean('all_tasks')->default(0);
            $table->boolean('status')->default(15);
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
        Schema::dropIfExists('authorization_requests');
    }
}
