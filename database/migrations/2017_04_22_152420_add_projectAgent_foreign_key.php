<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddProjectAgentForeignKey extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('projectAgents', function(Blueprint $table)
        {
            $table->foreign('projectId')->references('id')->on('project')->onDelete('cascade');
            $table->foreign('userId')->references('id')->on('user')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('projectAgents', function(Blueprint $table)
        {
            $table->dropForeign('projectId');
            $table->dropForeign('userId'); 
        });
    }
}
