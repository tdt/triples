<?php

namespace Tdt\Triples\Tests\Repositories;

/**
 * @backupGlobals disabled
 */
class CustomRequestTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $uri = 'limit=20&predicate=http%3A%2F%2Ffoo.bar%2Fns%23predicate&offset=1&object=42';

        $_SERVER['QUERY_STRING'] = $uri;
    }

    public function tearDown()
    {
        unset($_SERVER['QUERY_STRING']);
    }

    /**
     * Test the raw query for consistency
     * (Symfony Requests url decodes everything + loses order of the query string parameters)
     */
    public function testRawQuery()
    {
        $triples = \Mockery::mock('Tdt\Triples\Repositories\Interfaces\TripleRepositoryInterface');
        $definitions = \Mockery::mock('Tdt\Core\Repositories\Interfaces\DefinitionRepositoryInterface');

        \Mockery::mock('Controller');

        $data_controller = new \Tdt\Triples\Controllers\DataController($triples, $definitions);

        $rebuilt_uri = $data_controller->getRawRequestURI('http://localhost/foo/bar');

        $original_uri = 'http://localhost/foo/bar?limit=20&predicate=http%3A%2F%2Ffoo.bar%2Fns%23predicate&offset=1&object=42';

        $this->assertEquals($original_uri, $rebuilt_uri);
    }
}
