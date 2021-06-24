<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFieldToUnitTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('unit', function (Blueprint $table) {
            $table->double('masterUnitNo', 8, 2)->nullable()->after('unitNo');
            $table->integer('cars')->nullable()->after('price');
            $table->enum('exchanged', ['yes','no'])->nullable(false)->after('link');
            $table->string('share')->nullable()->after('link');
            $table->string('orientation')->nullable()->after('price');
            $table->string('stats')->nullable()->after('price');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('unit', function (Blueprint $table) {
            //
        });
    }
}
