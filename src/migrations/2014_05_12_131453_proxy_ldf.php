<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ProxyLdf extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		// Create the proxy ldf configuration table
		Schema::create('ldfsources', function($table){
			$table->increments('id');
			$table->string('startfragment', 255);
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
		// Drop the proxy ldf configuration table
		Schema::drop('ldfsources');
	}

}
