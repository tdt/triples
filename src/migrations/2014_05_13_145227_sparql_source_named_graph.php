<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SparqlSourceNamedGraph extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		// Add a named graph column to the sparql sources
		Schema::table('sparqlsources', function($table){
			$table->string('named_graph', 255)->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		// Drop the named graph columns from the sparql sources
		Schema::table('sparqlsources', function($table){
			$table->dropColumn('named_graph');
		});
	}
}
