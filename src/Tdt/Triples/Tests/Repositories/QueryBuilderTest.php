<?php

namespace Tdt\Triples\Tests\Repositories;

use Tdt\Triples\Repositories\ARC2\TripleRepository;
use Tdt\Triples\Repositories\SparqlQueryBuilder as QueryBuilder;

class QueryBuilderTest extends \PHPUnit_Framework_TestCase
{

    // Count for host/all
    public function testCountQueryWithoutParameters()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'select (count(*) as ?count) { ?s ?p ?o.}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Count for host/all?subject=http://foo.test
    public function testCountQueryWithSubjectParameter()
    {
        $query_builder = new QueryBuilder(array('<http://foobar.test>', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'select (count(*) as ?count) { <http://foobar.test> ?p ?o.}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Count for host/all?predicate=http://foobar/predicate#relationship
    public function testCountQueryWithPredicateParameter()
    {
        $query_builder = new QueryBuilder(array('?s', '<http://foobar/predicate#relationship>', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'select (count(*) as ?count) { ?s <http://foobar/predicate#relationship> ?o.}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Count for host/all?object=42
    public function testCountQueryWithObjectParameter()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '42'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'select (count(*) as ?count) { ?s ?p 42.}';

        $this->assertEquals($expected_query, $count_query);
    }


    // Construct for host/all
    public function testConstructQueryWithoutParameters()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', null, 150);

        $expected_query = 'construct {?s ?p ?o.}{ ?s ?p ?o.' .
                        '} offset 0 limit 150';

        $this->assertEquals($expected_query, $construct_query);
    }


    // Construct for host/all?subject=http://foobar.test
    public function testConstructQueryWithSubject()
    {
        $query_builder = new QueryBuilder(array('<http://foobar.test>', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'construct {<http://foobar.test> ?p ?o.}{ <http://foobar.test> ?p ?o.'.
        '} offset 0 limit 100';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Construct for host/all?predicate=http://foobar/predicate#relationship
    public function testConstructQueryWithPredicate()
    {
        $query_builder = new QueryBuilder(array('?s', '<http://foobar/predicate#relationship>', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'construct {?s <http://foobar/predicate#relationship> ?o.}{ ?s <http://foobar/predicate#relationship> ?o.'.
        '} offset 0 limit 100';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Construct for host/all?object=42
    public function testConstructQueryWithObject()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '42'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'construct {?s ?p 42.}{ ?s ?p 42.} offset 0 limit 100';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Construct for host/all with a named graph configured
    public function testConstructQueryWithNamedGraph()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', 'http://foo.test/namedgraph#version1');

        $expected_query = 'construct {?s ?p ?o.} '.
        'FROM <http://foo.test/namedgraph#version1>{ ?s ?p ?o.'.
        '} offset 0 limit 100';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Count for host/all with a named graph configured
    public function testCountQueryWithNamedGraph()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test', 'http://foo.test/namedgraph#version1');

        $expected_query = 'select (count(*) as ?count) FROM <http://foo.test/namedgraph#version1> { ?s ?p ?o.}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Count for host/all?use_hashes=true
    public function testCountQueryWithHash()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'select (count(*) as ?count) { ?s ?p ?o.}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Count for host/bar?use_hashes=true
    public function testCountQueryWithHashAndSubject()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test/bar', 'http://foo.test');

        $expected_query = 'select (count(*) as ?count) { ?s ?p ?o. FILTER( regex(?s, "^http://foo.test/bar#.*", "i" ) || regex(?s, "^http://foo.test/bar$", "i" ) ). }';

        $this->assertEquals($expected_query, $count_query);
    }

    // Count for host/all?predicate=http://foobar/predicate#relationship
    public function testCountQueryWithHashAndWithPredicateParameter()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '<http://foobar/predicate#relationship>', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'select (count(*) as ?count) { ?s <http://foobar/predicate#relationship> ?o.}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Count for host/all?object=42
    public function testCountQueryWithHashAndWithObjectParameter()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '?p', '42'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'select (count(*) as ?count) { ?s ?p 42.}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Construct for host/all?use_hashes=true and limit set to 150
    public function testConstructQueryWithHashAndWithoutParameters()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', null, 150);

        $expected_query = 'construct {?s ?p ?o.}{ ?s ?p ?o.' .
                        '} offset 0 limit 150';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Construct for host/all?subject=http://foobar.test&use_hashes=true
    public function testConstructQueryWithHashAndWithSubject()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('<http://foobar.test>', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'construct {?s ?p ?o.}{ ?s ?p ?o. FILTER( regex(?s, "^http://foobar.test#.*", "i" )'.
            ' || regex(?s, "^http://foobar.test$", "i" ) ). } offset 0 limit 100';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Construct for host/all?predicate=http://foobar/predicate#relationship&use_hashes=true
    public function testConstructQueryWithHashAndWithPredicate()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '<http://foobar/predicate#relationship>', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'construct {?s <http://foobar/predicate#relationship> ?o.}{ ?s <http://foobar/predicate#relationship> ?o.'.
        '} offset 0 limit 100';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Construct for host/all?object=42&use_hashes=true
    public function testConstructQueryWithHashAndWithObject()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '?p', '42'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test');

        $expected_query = 'construct {?s ?p 42.}{ ?s ?p 42.} offset 0 limit 100';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Construct for host/all?use_hashes=true and with a named graph configured
    public function testConstructQueryWithHashAndWithNamedGraph()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', 'http://foo.test/namedgraph#version1');

        $expected_query = 'construct {?s ?p ?o.} '.
        'FROM <http://foo.test/namedgraph#version1>{ ?s ?p ?o.'.
        '} offset 0 limit 100';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Count for host/all?use_hashes=true and with a named graph configured
    public function testCountQueryWithHashAndWithNamedGraph()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test', 'http://foo.test/namedgraph#version1');

        $expected_query = 'select (count(*) as ?count) FROM <http://foo.test/namedgraph#version1> { ?s ?p ?o.}';

        $this->assertEquals($expected_query, $count_query);
    }

    /**
     * Same tests as above, but with a set depth
     */

    // Count with a given depth for host/all
    public function testDepthCountQueryWithoutParameters()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test', null, 3);

        $expected_query = 'select (count(*) as ?count) { ?s ?p ?o.}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Count with a given depth for host/all?subject=http://foobar.test
    public function testDepthCountQueryWithSubjectParameter()
    {
        $query_builder = new QueryBuilder(array('<http://foobar.test>', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test', null, 3);

        $expected_query = 'select (count(*) as ?count) { <http://foobar.test> ?p ?o.OPTIONAL { ?o ?p2 ?o2. ?o2 ?p3 ?o3. }}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Count with a given depth for host/all?predicate=http://foobar/predicate#relationship
    public function testDepthCountQueryWithPredicateParameter()
    {
        $query_builder = new QueryBuilder(array('?s', '<http://foobar/predicate#relationship>', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test', null, 3);

        $expected_query = 'select (count(*) as ?count) { ?s <http://foobar/predicate#relationship> ?o.'.
                        'OPTIONAL { ?o ?p2 ?o2. ?o2 ?p3 ?o3. }}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Count with a given depth for host/all?object=42
    public function testDepthCountQueryWithObjectParameter()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '42'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test', null, 3);

        $expected_query = 'select (count(*) as ?count) { ?s ?p 42.OPTIONAL { 42 ?p2 ?o2. ?o2 ?p3 ?o3. }}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Construct with a given depth for host/all
    public function testDepthConstructQueryWithoutParameters()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', null, 150, 0, 3);

        $expected_query = 'construct {?s ?p ?o.?o ?p2 ?o2. ?o2 ?p3 ?o3. }{ ?s ?p ?o.OPTIONAL { ?o ?p2 ?o2. ?o2 ?p3 ?o3. }}'.
                        ' offset 0 limit 150';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Construct with a given depth for host/all?subject=http://foobar.test
    public function testDepthConstructQueryWithSubject()
    {
        $query_builder = new QueryBuilder(array('<http://foobar.test>', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', null, 150, 0, 3);

        $expected_query = 'construct {<http://foobar.test> ?p ?o.?o ?p2 ?o2. ?o2 ?p3 ?o3. }{ <http://foobar.test> ?p ?o.'.
                        'OPTIONAL { ?o ?p2 ?o2. ?o2 ?p3 ?o3. }} offset 0 limit 150';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Construct with a given depth for host/all?predicate=http://foobar/predicate#relationship
    public function testDepthConstructQueryWithPredicate()
    {
        $query_builder = new QueryBuilder(array('?s', '<http://foobar/predicate#relationship>', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', null, 150, 0, 3);

        $expected_query = 'construct {?s <http://foobar/predicate#relationship> ?o.?o ?p2 ?o2. ?o2 ?p3 ?o3. }'.
                        '{ ?s <http://foobar/predicate#relationship> ?o.OPTIONAL { ?o ?p2 ?o2. ?o2 ?p3 ?o3. }} '.
                        'offset 0 limit 150';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Construct with a given depth for host/all?object=42
    public function testDepthConstructQueryWithObject()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '42'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', null, 150, 0, 3);

        $expected_query = 'construct {?s ?p 42.42 ?p2 ?o2. ?o2 ?p3 ?o3. }{ ?s ?p 42.OPTIONAL { 42 ?p2 ?o2. ?o2 ?p3 ?o3. }}'.
                    ' offset 0 limit 150';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Construct with a given depth for host/all with a named graph configured
    public function testDepthConstructQueryWithNamedGraph()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', 'http://foo.test/namedgraph#version1', 100, 0, 3);

        $expected_query = 'construct {?s ?p ?o.?o ?p2 ?o2. ?o2 ?p3 ?o3. } FROM <http://foo.test/namedgraph#version1>{ ?s ?p ?o.OPTIONAL '.
                    '{ ?o ?p2 ?o2. ?o2 ?p3 ?o3. }} offset 0 limit 100';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Count with a given depth for host/all with a named graph configured
    public function testDepthCountQueryWithNamedGraph()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test', 'http://foo.test/namedgraph#version1', 3);

        $expected_query = 'select (count(*) as ?count) FROM <http://foo.test/namedgraph#version1> { ?s ?p ?o.}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Count with a given depth for host/all
    public function testDepthCountQueryWithHash()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test', null, 3);

        $expected_query = 'select (count(*) as ?count) { ?s ?p ?o.}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Count with a given depth for host/bar?use_hashes=true
    public function testDepthCountQueryWithHashAndSubject()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test/bar', 'http://foo.test', null, 3);

        $expected_query = 'select (count(*) as ?count) { ?s ?p ?o. FILTER( regex(?s, "^http://foo.test/bar#.*", "i" )'.
                    ' || regex(?s, "^http://foo.test/bar$", "i" ) ). }';

        $this->assertEquals($expected_query, $count_query);
    }

    // Count with a given depth for host/all?predicate=http://foobar/predicate#relationship&use_hashes=true
    public function testDepthCountQueryWithHashAndWithPredicateParameter()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '<http://foobar/predicate#relationship>', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test', null, 3);

        $expected_query = 'select (count(*) as ?count) { ?s <http://foobar/predicate#relationship> ?o.OPTIONAL { ?o ?p2 ?o2. ?o2 ?p3 ?o3. }}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Count with a given depth for host/all?object=42&use_hashes=true
    public function testDepthCountQueryWithHashAndWithObjectParameter()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '?p', '42'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test', null, 3);

        $expected_query = 'select (count(*) as ?count) { ?s ?p 42.OPTIONAL { 42 ?p2 ?o2. ?o2 ?p3 ?o3. }}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Construct with a given depth for host/all&use_hashes=true
    public function testDepthConstructQueryWithHashAndWithoutParameters()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', null, 150, 0, 3);

        $expected_query = 'construct {?s ?p ?o.?o ?p2 ?o2. ?o2 ?p3 ?o3. }{ ?s ?p ?o.OPTIONAL { ?o ?p2 ?o2. ?o2 ?p3 ?o3. }} offset 0 limit 150';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Construct with a given depth for host/all?subject=http://foobar.test&use_hashes=true and a limit of 150
    public function testDepthConstructQueryWithHashAndWithSubject()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('<http://foobar.test>', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', null, 150, 0, 3);

        $expected_query = 'construct {?s ?p ?o.?o ?p2 ?o2. ?o2 ?p3 ?o3. }{ ?s ?p ?o. FILTER( regex(?s, "^http://foobar.test#.*", "i" )'.
                    ' || regex(?s, "^http://foobar.test$", "i" ) ). OPTIONAL { ?o ?p2 ?o2. ?o2 ?p3 ?o3. }} offset 0 limit 150';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Construct with a given depth for host/all?predicate=http://foobar/predicate#relationship and a limit of 150
    public function testDepthConstructQueryWithHashAndWithPredicate()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '<http://foobar/predicate#relationship>', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', null, 150, 0, 3);

        $expected_query = 'construct {?s <http://foobar/predicate#relationship> ?o.?o ?p2 ?o2. ?o2 ?p3 ?o3. }'.
                    '{ ?s <http://foobar/predicate#relationship> ?o.OPTIONAL { ?o ?p2 ?o2. ?o2 ?p3 ?o3. }} offset 0 limit 150';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Construct with a given depth for host/all?object=42
    public function testDepthConstructQueryWithHashAndWithObject()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '?p', '42'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', null, 150, 0, 3);

        $expected_query = 'construct {?s ?p 42.42 ?p2 ?o2. ?o2 ?p3 ?o3. }{ ?s ?p 42.OPTIONAL { 42 ?p2 ?o2. ?o2 ?p3 ?o3. }} offset 0 limit 150';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Construct with a given depth for host/all?use_hashes with a named graph configured
    public function testDepthConstructQueryWithHashAndWithNamedGraph()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test', 'http://foo.test', 'http://foo.test/namedgraph#version1', 150, 0, 3);

        $expected_query = 'construct {?s ?p ?o.?o ?p2 ?o2. ?o2 ?p3 ?o3. } FROM <http://foo.test/namedgraph#version1>{ ?s ?p ?o.OPTIONAL { ?o ?p2 ?o2. ?o2 ?p3 ?o3. }}'
                        .' offset 0 limit 150';

        $this->assertEquals($expected_query, $construct_query);
    }

    // Count with a given depth for host/all&use_hashes with a named graph configured
    public function testDepthCountQueryWithHashAndWithNamedGraph()
    {
        QueryBuilder::setHashVariant(true);

        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test', 'http://foo.test', 'http://foo.test/namedgraph#version1', 3);

        $expected_query = 'select (count(*) as ?count) FROM <http://foo.test/namedgraph#version1> { ?s ?p ?o.}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Count for host/sub/sub.space
    public function testCountQueryWithDottedURI()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test/sub/sub.space', 'http://foo.test', 'http://foo.test/namedgraph#version1', 3);

        $expected_query = 'select (count(*) as ?count) FROM <http://foo.test/namedgraph#version1>'.
                    ' { <http://foo.test/sub/sub.space> ?p ?o.OPTIONAL { ?o ?p2 ?o2. ?o2 ?p3 ?o3. }}';

        $this->assertEquals($expected_query, $count_query);
    }

    // Construct for host/sub/sub.space
    public function testConstructQueryWithDottedURI()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $construct_query = $query_builder->createFetchQuery('http://foo.test/sub/sub.space', 'http://foo.test', 'http://foo.test/namedgraph#version1', 150, 0, 3);

        $expected_query = 'construct {<http://foo.test/sub/sub.space> ?p ?o.?o ?p2 ?o2. ?o2 ?p3 ?o3. } FROM <http://foo.test/namedgraph#version1>{ <http://foo.test/sub/sub.space> ?p ?o.OPTIONAL { ?o ?p2 ?o2. ?o2 ?p3 ?o3. }}'
                        .' offset 0 limit 150';

        $this->assertEquals($expected_query, $construct_query);
    }
}
