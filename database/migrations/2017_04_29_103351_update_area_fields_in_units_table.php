<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateAreaFieldsInUnitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        app('db')->unprepared("ALTER TABLE `unit` CHANGE `internalArea` `internalArea` DOUBLE(8,2) NOT NULL DEFAULT '0';");
        app('db')->unprepared("ALTER TABLE `unit` CHANGE `externalArea` `externalArea` DOUBLE(8,2) NOT NULL DEFAULT '0';");
        app('db')->unprepared("ALTER TABLE `unit` CHANGE `price` `price` DOUBLE(8,2) NOT NULL DEFAULT '0';");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
