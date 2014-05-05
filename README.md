[![Build Status](https://travis-ci.org/tdt/triples.png?branch=master)](https://travis-ci.org/tdt/triples)
# tdt/triples

tdt/triples is a repository that hooks into [The DataTank core](https://github.com/tdt/core) application, and provides the functionality to build your URI space through the configuration of semantic resources, in addition to the URI space of The DataTank.

It reads the triples from the semantic sources, and by default it stores them into a local simulated triple store based on MySQL. Therefore it needs a MySQL database, so make sure your datatank project is configured with a MySQL connection.

Also note that for this kind of "triple caching", it uses the semsol/arc2 library which works on PHP 5.3 and 5.4. From 5.5 the MySQL driver that semsol/arc2 uses is [deprecated](https://github.com/semsol/arc2/issues/58). This can be solved by creating a different TriplesRepository instance that uses a genuine triplestore (or other solutions) to store triples in. Because we've built this type of caching with dependency injection, it makes it easy to provide your own triple caching.

## Purpose

The core application allows for the RESTful publication of data sources, from any machine readable format, to web-ready formats. (e.g. SPARQL, SHP, CSV, XLS, ... to JSON-LD, JSON, XML, PHP, RDF/XML, RDF/JSON, and even map visualizations)

Each datasource that is published by the core application has its own URI, and represents a certain chunk of data. Now, to allow for automatic configuration of your URI space and take a step towards the semantic web, the tdt/triples package was created.

This package allows for a set of semantic sources to be added to the datatank, from which the subject of the triples are used to fill in URIs that have not been used in the datatank core. Let's take a look at an example.

The DataTank has 2 published datasources:

http://foo.bar/first/datasource
http://foo.bar/second/datasource

Now if an organization has a set of semantic data that is pointing towards their domain, they would have to manually publish the different semantic sources under corresponding (or not) URIs. This is where tdt/triples comes in. By adding the semantic datasources to the datatank, while the package is installed, the subject URIs of the triples that are present in the semantic sources will be dereferenced automatically.

For example, if you configure a turtle file that has triples with a subject of http://foo.bar/demography/2013, then that URI will automatically be dereferenced by the datatank. Upon making a request all triples, that can be found in the configured semantic sources, with a subject similar to the URI of the request will be returned.

## How it works

The current supported semantic sources are Turtle files, RDF files and SPARQL-endpoints. When tdt/triples is installed the following workflow is applied:

1) Request URI serves as an identifier
2) The datatank checks if no datasource is published on the identifier by the main (core) application
3) If the identifier is not used by core, then all semantic sources are scanned for triples with a subject matching the URI
4) If triples are found with the subject, they are returned, if not a 404 is given

## Installation

This package works with version 4.3 or higher ( if 4.3 is not available, try the development branch) of [the datatank core](https://github.com/tdt/core), and is under active development. If you have remarks, suggestions, issues, etc. please don't hesitate to log it on the github repository.

1) Edit composer.json

Edit your composer.json file, and add tdt/triples as a dependency:

    "tdt/triples": "dev-master"

After that run the composer update command.

2) Migrate

The package needs a few extra datatables for its configuration, so go ahead and run the migration command!

    $ php artisan migrate --package=tdt/triples

3) Notify core

Let the core application know you have added functionality it should take into account. Do this by adding 'Tdt\Triples\TriplesServiceProvider' to the app.php file located in the app/config folder.

4) Update

Run composer update, and you're done!

You're ready to start using tdt/triples. Each api resource in the datatank is located under <root>/api, and triples is not exception. Hence, api/triples is the URI to which all the CRUD requests have to be done.

