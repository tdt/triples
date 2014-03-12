<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDepthSparqlSource extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		// Add a column depth to the sparql sources table
		Schema::table('sparqlsources', function($table){
			$table->integer('depth');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		// Drop the depth column from the sparql sources table
		Schema::table('sparqlsources', function($table){
			$table->dropColumn('depth');
		});
	}
}
