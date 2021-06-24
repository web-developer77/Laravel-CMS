<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProjectTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('project', function (Blueprint $table) {
            $table->increments('id');
            //normal project details
            $table->string('name');
            $table->text('address')->default(NULL)->nullable();
            $table->string('client')->nullable();
            $table->integer('noOfUnits')->unsigned();
            $table->integer('commercialSpace');
            $table->text('projectDescription')->default(NULL)->nullable();
            $table->string('image')->default(NULL)->nullable();

            //developer details
            $table->string('developerCompany', 50)->nullable();
            $table->string('developerIncharge', 50)->nullable();
            $table->string('developerMobile', 15)->nullable();
            $table->string('developerEmail', 50)->nullable();
            $table->string('developerAddress')->nullable();
            $table->string('developerPostalCode')->nullable();
            $table->string('developerState')->nullable();
            $table->string('developerCountry')->nullable();

            //architect details
            $table->string('architectCompany', 50)->nullable();
            $table->string('architectIncharge', 50)->nullable();
            $table->string('architectMobile', 15)->nullable();
            $table->string('architectEmail', 50)->nullable();
            $table->string('architectAddress')->default(NULL)->nullable();
            $table->string('architectPostalCode')->nullable();
            $table->string('architectState')->nullable();
            $table->string('architectCountry')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->dateTime('deployedAt')->nullable()->default(NULL);
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
        Schema::dropIfExists('project');
    }
}
