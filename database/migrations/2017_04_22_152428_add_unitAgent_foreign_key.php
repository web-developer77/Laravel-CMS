<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUnitAgentForeignKey extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('unitAgents', function(Blueprint $table)
        {
            $table->foreign('unitId')->references('id')->on('unit')->onDelete('cascade');
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
        Schema::table('unitAgents', function(Blueprint $table)
        {
            $table->dropForeign('unitId');
            $table->dropForeign('userId'); 
        });
    }
}
