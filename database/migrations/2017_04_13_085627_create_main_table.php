<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMainTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('unit', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('projectId')->unsigned();
            $table->string('unitNo')->nullable();
            $table->string('floor')->nullable();
            $table->integer('beds')->default(0);
            $table->integer('baths')->default(0);
            $table->integer('parking')->default(0);
            $table->integer('storage')->default(0);
            $table->float('totalArea')->nullable()->default(0);
            $table->integer('internalArea')->default(0);
            $table->integer('externalArea')->default(0);
            $table->string('floorPlan')->nullable();
            $table->string('marketing')->nullable();
            $table->integer('price')->default(0);
            $table->enum('status', ['available','reserved', 'sold'])->nullable();
            $table->enum('publish', ['yes','no'])->nullable(false)->default('no');
            $table->enum('settled', ['yes','no'])->nullable(false)->default('no');
            $table->string('link')->nullable();
            $table->integer('agentId');
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
        Schema::dropIfExists('unit');
    }
}
