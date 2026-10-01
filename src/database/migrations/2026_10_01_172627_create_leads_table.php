<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 64)->unique();
            $table->dateTime('lead_created_at');//created_at csv
            $table->string('first_name')->nullable();
            $table->string('last_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('city')->index();
            $table->string('source')->index();
            $table->string('utm_campaign')->nullable();
            $table->string('product')->index();
            $table->unsignedInteger('budget_uah')->nullable();
            $table->string('status')->index();
            $table->string('manager')->nullable();
            $table->text('comment')->nullable();
            $table->dateTime('next_contact_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leads');
    }
}
