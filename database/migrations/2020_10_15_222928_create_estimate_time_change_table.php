<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEstimateTimeChangeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('estimate_time_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('task_id');
            $table->unsignedInteger('changed_by');
            $table->string('estimate_time');
            $table->string('reason', 500);
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
        Schema::dropIfExists('estimate_time_changes');
    }
}
