<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProjectAgentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('projectAgents', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('role', ['User Manager','Project Manager', 'Master Agent','Agent','Affiliate'])->nullable(false);
            $table->integer('userId')->unsigned();
            $table->integer('projectId')->unsigned();
            $table->timestamps();
            $table->softDeletes();
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
        Schema::dropIfExists('projectAgents');
    }
}
