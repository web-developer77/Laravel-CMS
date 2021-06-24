<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCountryToProjectTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('project', function (Blueprint $table) {
            $table->string('threeDUrl', 50)->nullable()->after('address');
            $table->string('country', 50)->nullable()->after('address');
            $table->string('state', 50)->nullable()->after('address');
            $table->string('postalCode', 20)->nullable()->after('address');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('project', function (Blueprint $table) {
            $table->dropColumn('postalCode');
            $table->dropColumn('state');
            $table->dropColumn('country');
            $table->dropColumn('threeDUrl');
        });
    }
}
