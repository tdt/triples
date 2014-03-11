<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InitTurtleSource extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		// Create the table to store turtle file configurations
		Schema::create('turtle_sources', function($table){
			$table->increments('id');
			$table->string('uri', 255);
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
		// Drop the turtle_sources table
		Schema::drop('turtle_sources');
	}
}
