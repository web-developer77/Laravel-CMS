<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUnitAgentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('unitAgents', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('role', ['Agent','Affiliate'])->nullable(false);
            $table->integer('userId')->unsigned();
            $table->integer('projectId')->unsigned();
            $table->integer('unitId')->unsigned();
            $table->timestamps();
            $table->integer('createdBy')->unsigned();
            $table->integer('updatedBy')->default(0)->unsigned();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('unitAgents');
    }
}
