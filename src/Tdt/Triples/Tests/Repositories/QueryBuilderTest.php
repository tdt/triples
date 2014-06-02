<?php

namespace Tdt\Triples\Tests\Repositories;

use Tdt\Triples\Repositories\ARC2\TripleRepository;
use Tdt\Triples\Repositories\SparqlQueryBuilder as QueryBuilder;

class QueryBuilderTest extends \PHPUnit_Framework_TestCase
{

    public function testCountQueryWithoutParameters()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'select (count(*) as ?count) { ?s ?p ?o.}';

        $this->assertEquals($expected_query, $count_query);
    }

    public function testCountQueryWithSubjectParameter()
    {
        $query_builder = new QueryBuilder(array('<http://foobar.test>', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'select (count(*) as ?count) { <http://foobar.test> ?p ?o.}';

        $this->assertEquals($expected_query, $count_query);
    }

    public function testCountQueryWithPredicateParameter()
    {
        $query_builder = new QueryBuilder(array('?s', '<http://foobar/predicate#relationship>', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'select (count(*) as ?count) { ?s <http://foobar/predicate#relationship> ?o.}';

        $this->assertEquals($expected_query, $count_query);
    }

    public function testCountQueryWithObjectParameter()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '42'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'select (count(*) as ?count) { ?s ?p 42.}';

        $this->assertEquals($expected_query, $count_query);
    }

    public function testConstructQueryWithoutParameters()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', null, 150);

        $expected_query = 'construct {?s ?p ?o.}{ ?s ?p ?o.' .
                        '} offset 0 limit 150';

        $this->assertEquals($expected_query, $construct_query);
    }

    public function testConstructQueryWithSubject()
    {
        $query_builder = new QueryBuilder(array('<http://foobar.test>', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'construct {<http://foobar.test> ?p ?o.}{ <http://foobar.test> ?p ?o.'.
        '} offset 0 limit 100';

        $this->assertEquals($expected_query, $construct_query);
    }

    public function testConstructQueryWithPredicate()
    {
        $query_builder = new QueryBuilder(array('?s', '<http://foobar/predicate#relationship>', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'construct {?s <http://foobar/predicate#relationship> ?o.}{ ?s <http://foobar/predicate#relationship> ?o.'.
        '} offset 0 limit 100';

        $this->assertEquals($expected_query, $construct_query);
    }

    public function testConstructQueryWithObject()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '42'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'construct {?s ?p 42.}{ ?s ?p 42.} offset 0 limit 100';

        $this->assertEquals($expected_query, $construct_query);
    }

    public function testConstructQueryWithNamedGraph()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', 'http://foo.test/namedgraph#version1');

        $expected_query = 'construct {?s ?p ?o.} '.
        'FROM <http://foo.test/namedgraph#version1>{ ?s ?p ?o.'.
        '} offset 0 limit 100';

        $this->assertEquals($expected_query, $construct_query);
    }

    public function testCountQueryWithNamedGraph()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test', 'http://foo.test/namedgraph#version1');

        $expected_query = 'select (count(*) as ?count) FROM <http://foo.test/namedgraph#version1> { ?s ?p ?o.}';

        $this->assertEquals($expected_query, $count_query);
    }
}
