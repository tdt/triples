<?php

namespace Tdt\Triples\Repositories;

class QueryBuilder
{
    private static $depth = 3;

    private $query_string_params;

    public function __construct(array $query_string_params = array('?s', '?p', '?o'))
    {
        $this->query_string_params = $query_string_params;
    }

    /**
     * Set the paramaters for the request
     *
     * @param array $parameters
     */
    public function setParameters(array $parameters)
    {
        $this->query_string_params = $parameters;
    }

    /**
     * Make and return a SPARQL count query, taken into account the passed query string parameters
     *
     * @return string
     */
    public function createCountQuery($base_uri)
    {
        list($s, $p, $o) = $this->query_string_params;

        $vars = $s . ' ' . $p . ' ' . $o . '.';

        $last_object = $o;
        $depth_vars = '';

        $construct_statement = '';
        $filter_statement = '';

        $count_query = '';

        if ($s == '?s' && $p == '?p' && $o == '?o') {

            for ($i = 2; $i <= self::$depth; $i++) {

                $depth_vars .= $last_object . ' ?p' . $i . ' ?o' . $i . '. ';

                $last_object = '?o' . $i;
            }

            $select_statement = 'select (count(*) as ?count) ';
            $filter_statement = '{ {'. $vars .
            ' FILTER( regex(?s, "' . $base_uri . '#.*", "i" ) ). ' .
            'OPTIONAL { ' . $depth_vars . '}} ' .
            ' UNION { '. $vars .
            ' FILTER( regex(?s, "' . $base_uri . '", "i" )). ' .
            'OPTIONAL { ' . $depth_vars . '}} ' .
            ' }';
        } else {
            $select_statement = 'select (count(*) as ?count) ';
            $filter_statement = '{ {'. $vars .
            ' FILTER( regex(?s, "' . $base_uri . '#.*", "i" ) ). } ' .
            ' UNION { '. $vars .
            ' FILTER( regex(?s, "' . $base_uri . '", "i" )). ' .
            ' }}';
        }

        return $select_statement . $filter_statement;
    }


    /**
     * Creates a query that fetches all of the triples
     * of which the subject matches the base uri
     *
     * @param string $base_uri
     *
     * @return string
     */
    public function createConstructSparqlQuery($base_uri, $limit = 5000, $offset = 0, $depth = 3)
    {
        list($s, $p, $o) = $this->query_string_params;

        $vars = $s . ' ' . $p . ' ' . $o . '.';

        $last_object = $o;
        $depth_vars = '';

        $construct_statement = '';
        $filter_statement = '';

        // Only when no template parameter is given, add the depth parameters
        if ($s == '?s' && $p == '?p' && $o == '?o') {

            for ($i = 2; $i <= self::$depth; $i++) {

                $depth_vars .= $last_object . ' ?p' . $i . ' ?o' . $i . '. ';

                $last_object = '?o' . $i;
            }

            $construct_statement = 'construct {' . $vars . $depth_vars . '}';
            $filter_statement = '{ {'. $vars .
                                ' FILTER( regex(?s, "' . $base_uri . '#.*", "i" ) ). ' .
                                'OPTIONAL { ' . $depth_vars . '}} ' .
                                ' UNION { '. $vars .
                                ' FILTER( regex(?s, "' . $base_uri . '", "i" )). ' .
                                'OPTIONAL { ' . $depth_vars . '}} ' .
                                ' }';
        } else {

            $construct_statement = 'construct {' . $vars . ' }';
            $filter_statement = '{ {'. $vars .
                                ' FILTER( regex(?s, "' . $base_uri . '#.*", "i" ) ). } ' .
                                ' UNION { '. $vars .
                                ' FILTER( regex(?s, "' . $base_uri . '", "i" )). ' .
                                ' }}';
        }

        return $construct_statement . $filter_statement . ' offset ' . $offset . ' limit ' . $limit;
    }

    /**
     * Create an insert SPARQL query based on the graph id
     *
     * @param string $graph_name The graph in which the triples will go
     * @param string $triples    The triples that need to be stored
     *
     * @return string
     */
    public function createInsertQuery($graph_name, $triples)
    {
        $query = "INSERT INTO <$graph_name> {";
        $query .= $triples;
        $query .= ' }';

        return $query;
    }
}
