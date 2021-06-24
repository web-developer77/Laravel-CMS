<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePanoramaLinksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('panoramaLinks', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('origin')->unsigned();
            $table->foreign('origin')->references('id')->on('panoramas')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('destination')->unsigned();
            $table->foreign('destination')->references('id')->on('panoramas')->onDelete('cascade')->onUpdate('cascade');
            $table->decimal('x', 4, 2);
            $table->decimal('y', 4, 2);
            $table->decimal('z', 4, 2);
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
        //
    }
}
