# tdt/triples

tdt/triples is a repository that hooks into [The DataTank core](https://github.com/tdt/core) application, and provides the functionality to build your URI space through the configuration of semantic resources, in addition to the URI space of The DataTank.

It specifically needs a MySQL database for optimization purposes, make sure your datatank project is configured with a MySQL connection.

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

This package works with version 4.3 or higher ( if 4.3 is not available, try the development branch) of [the datatank core](https://github.com/tdt/core), and is under active development. If you have remarks, suggestions, issues, etc. please don't hesitate to log an issue on the github repository.

1) Edit composer.json

Edit your composer.json file, and add tdt/triples as a dependency:

    "tdt/triples": "dev-master"

After that run the composer update command.

2) Migrate

The package needs a few extra datatables for its configuration, so go ahead and run the migration command!

    $ php artisan migrate --package=tdt/triples

3) Done!

You're ready to start using tdt/triples. Each api resource in the datatank is located under <root>/api, and triples is not exception. Hence, api/triples is the URI to which all the CRUD requests have to be done.


## Future work

At the time of writing, this should be used for proof-of-concept and demo purposes. A next step in this additional functionality is adding optimization techniques such as caching, indexing semantic sources to subject URIs, ...
