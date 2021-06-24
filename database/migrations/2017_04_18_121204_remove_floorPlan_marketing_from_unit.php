<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveFloorPlanMarketingFromUnit extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('unit', function(Blueprint $table){
            $table->dropColumn('floorPlan');
            $table->dropColumn('marketing');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('unit', function(Blueprint $table)
        {
            $table->string('floorPlan')->nullable();
            $table->string('marketing')->nullable();
        });
    }
}
