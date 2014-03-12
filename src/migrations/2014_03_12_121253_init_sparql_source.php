<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InitSparqlSource extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		// Create the table to store the sparql source
		Schema::create('sparqlsources', function($table){
			$table->increments('id');
			$table->string('endpoint', 255);
			$table->string('endpoint_password', 255);
			$table->string('endpoint_user', 255);
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
		// Drop the table for the sparql source
		Schema::drop('sparqlsources');
	}
}
