<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatesAdditionalProjectDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('additionalProjectDetails', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('projectId')->unsigned();

             //additional Developer details
             $table->string('additionalDeveloperName', 50)->nullable();
             $table->string('additionalDeveloperAuthority', 50)->nullable();
             $table->string('additionalDeveloperIncharge', 50)->nullable();
             $table->string('additionalDeveloperMobile', 15)->nullable();
             $table->string('additionalDeveloperEmail', 50)->nullable();
             $table->string('additionalDeveloperAddress')->default(NULL)->nullable();
             $table->string('additionalDeveloperPostalCode')->nullable();
             $table->string('additionalDeveloperState')->nullable();
             $table->string('additionalDeveloperCountry')->nullable();

             //additional Architect details
             $table->string('additionalArchitectName', 50)->nullable();
             $table->string('additionalArchitectAuthority', 50)->nullable();
             $table->string('additionalArchitectIncharge', 50)->nullable();
             $table->string('additionalArchitectMobile', 15)->nullable();
             $table->string('additionalArchitectEmail', 50)->nullable();
             $table->string('additionalArchitectAddress')->default(NULL)->nullable();
             $table->string('additionalArchitectPostalCode')->nullable();
             $table->string('additionalArchitectState')->nullable();
             $table->string('additionalArchitectCountry')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $table->dropColumn('additionalProjectDetails');
    }
}
