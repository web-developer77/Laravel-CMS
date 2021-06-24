<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user', function (Blueprint $table) {
            $table->increments('id');
            $table->string('firstName', 50)->nullable(false);
            $table->string('lastName', 50)->nullable(false);
            $table->string('mobile', 15)->nullable();
            $table->string('fax', 20)->nullable()->default(NULL);
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->enum('role', ['Admin', 'User Manager','Project Manager', 'Master Agent','Agent','Affiliate'])->nullable()->default(NULL);
            $table->string('email')->unique();
            $table->string('password', 100)->nullable(false);
            $table->boolean('isEnable')->default(1)->comment("if user not available:0");
            $table->string('image',100)->default(NULL)->nullable();
            $table->string('title', 50)->nullable();
            $table->text('about')->nullable();
            $table->string('office')->nullable();
            $table->string('companyName', 50)->nullable()->default(NULL);
            $table->string('confirmationCode', 10)->nullable();
            $table->string('projectId', 10)->nullable();
            $table->boolean('isEmailVerified')->default(0)->comment("if verified:1");
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
        Schema::dropIfExists('user');
    }
}
