<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewColumnsToCustomerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->String('owner');
            $table->mediumText('address');
            $table->String('contact_person');
            $table->integer('mobile_no');
            $table->String('email', 255);
            $table->String('website', 255);
            $table->unsignedInteger('software_coordinator');
            $table->unsignedInteger('marketing_coordinator');
            $table->unsignedInteger('account_coordinator');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('owner');
            $table->dropColumn('address');
            $table->dropColumn('contact_person');
            $table->dropColumn('mobile_no');
            $table->dropColumn('email', 255);
            $table->dropColumn('website', 255);
            $table->dropColumn('software_coordinator');
            $table->dropColumn('marketing_coordinator');
            $table->dropColumn('account_coordinator');
        });
    }
}
