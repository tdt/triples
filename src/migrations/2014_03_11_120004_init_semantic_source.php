<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InitSemanticSource extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		// Create the semantic source table
		Schema::create('semantic_sources', function($table){

			$table->increments('id');
			$table->integer('source_id');
			$table->string('source_type', 255);
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
		// Drop the semantic source table
		Schema::drop('semantic_sources');
	}
}
