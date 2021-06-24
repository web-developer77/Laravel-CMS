<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesInTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        app('db')->unprepared("ALTER TABLE `message` ADD INDEX(`senderId`);");
        app('db')->unprepared("ALTER TABLE `message` ADD INDEX(`receiverId`);");
        app('db')->unprepared("ALTER TABLE `images` ADD INDEX(`unitId`);");
        app('db')->unprepared("ALTER TABLE `images` ADD INDEX(`projectId`);");
        app('db')->unprepared("ALTER TABLE `projectAgents` ADD INDEX(`userId`);");
        app('db')->unprepared("ALTER TABLE `projectAgents` ADD INDEX(`projectId`);");
        app('db')->unprepared("ALTER TABLE `unit` ADD INDEX(`projectId`);");
        app('db')->unprepared("ALTER TABLE `unitAgents` ADD INDEX(`userId`);");
        app('db')->unprepared("ALTER TABLE `unitAgents` ADD INDEX(`projectId`);");
        app('db')->unprepared("ALTER TABLE `unitAgents` ADD INDEX(`unitId`);");
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
