<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddingAdditionalCoulmnToTheJobsTable1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->integer('system_id');
            $table->integer('location_id');
            $table->integer('division_id');
            $table->integer("employee_id");
            $table->integer("jobCoordinator_id");
            $table->integer("requestedEmployee_id");
            $table->string("remarks");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table::dropColumn(['system_id', 'location_id', 'division_id', "employee_id", "jobCoordinator_id", "requestedEmployee_id", "remarks"]);
        });
    }
}
