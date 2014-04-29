<?php

namespace Tdt\Triples\Tests\Repositories;

use Tdt\Triples\Repositories\ARC2\TripleRepository;
use Tdt\Triples\Repositories\QueryBuilder;

class QueryBuilderTest extends \PHPUnit_Framework_TestCase
{

    public function testCountQueryWithoutParameters()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test/');

        $expected_query = 'select (count(*) as ?count) { {?s ?p ?o. FILTER( regex(?s, "http://foo.test/#.*", "i" ) ). ' .
        'OPTIONAL { ?o ?p2 ?o2. ?o2 ?p3 ?o3. }}  UNION { ?s ?p ?o. FILTER( regex(?s, "http://foo.test/", "i" )). ' .
        'OPTIONAL { ?o ?p2 ?o2. ?o2 ?p3 ?o3. }}  }';

        $this->assertEquals($expected_query, $count_query);
    }

    public function testCountQueryWithSubjectParameter()
    {
        $query_builder = new QueryBuilder(array('<http://foobar.test/>', '?p', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test/');

        $expected_query = 'select (count(*) as ?count) { {<http://foobar.test/> ?p ?o. FILTER( regex(?s, "http://foo.test/#.*", "i" ) ). }  '.
        'UNION { <http://foobar.test/> ?p ?o. FILTER( regex(?s, "http://foo.test/", "i" )).  }}';

        $this->assertEquals($expected_query, $count_query);
    }

    public function testCountQueryWithPredicateParameter()
    {
        $query_builder = new QueryBuilder(array('?s', '<http://foobar/predicate#relationship', '?o'));

        $count_query = $query_builder->createCountQuery('http://foo.test/');

        $expected_query = 'select (count(*) as ?count) { {?s <http://foobar/predicate#relationship ?o. FILTER( regex(?s, "http://foo.test/#.*", "i" ) ). }  '.
        'UNION { ?s <http://foobar/predicate#relationship ?o. FILTER( regex(?s, "http://foo.test/", "i" )).  }}';

        $this->assertEquals($expected_query, $count_query);
    }

    public function testCountQueryWithObjectParameter()
    {
        $query_builder = new QueryBuilder(array('?s', '?p', '42'));

        $count_query = $query_builder->createCountQuery('http://foo.test/');

        $expected_query = 'select (count(*) as ?count) { {?s ?p 42. FILTER( regex(?s, "http://foo.test/#.*", "i" ) ). }  '.
        'UNION { ?s ?p 42. FILTER( regex(?s, "http://foo.test/", "i" )).  }}';

        $this->assertEquals($expected_query, $count_query);
    }
}
