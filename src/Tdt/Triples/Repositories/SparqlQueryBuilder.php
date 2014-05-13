<?php

namespace Tdt\Triples\Repositories;

class SparqlQueryBuilder
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
     * @param string $base_uri    The base_uri that will serve as a subject in the query
     * @param string $graph_name The name of the graph to take into account for the query
     *
     * @return string
     */
    public function createCountQuery($base_uri = null, $graph_name = null)
    {
        list($s, $p, $o) = $this->query_string_params;

        if (empty($base_uri)) {
            return $this->createVariableCountQuery($graph_name);
        }

        // If subject has been passed, it should be the same as the base_uri
        if (substr($s, 0, 4) == "http") {
            $s = $base_uri;
        }

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

            if (!empty($graph_name)) {
                $select_statement = 'select (count(*) as ?count) FROM <' . $graph_name . '> ';
            } else {
                $select_statement = 'select (count(*) as ?count) ';
            }

            $filter_statement = '{ {'. $vars .
            ' FILTER( regex(?s, "^' . $base_uri . '#.*", "i" ) || regex(?s, "^' . $base_uri . '$", "i" ) ). ' .
            'OPTIONAL { ' . $depth_vars . '}}}';
        } else {
            $select_statement = 'select (count(*) as ?count) ';

            $filter_statement = '{ '. $vars .
            ' FILTER( regex(?s, "^' . $base_uri . '#.*", "i" ) || regex(?s, "^' . $base_uri . '$", "i" )). }';
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
    public function createConstructSparqlQuery($base_uri = null, $graph_name = null, $limit = 100, $offset = 0, $depth = 3)
    {
        list($s, $p, $o) = $this->query_string_params;

        if (empty($base_uri)) {
            return $this->createVariableConstructSparqlQuery($graph_name, $limit, $offset);
        }

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

            if (!empty($graph_name)) {
                $construct_statement = 'construct {' . $vars . $depth_vars . '} FROM <' . $graph_name . '>';
            } else {
                $construct_statement = 'construct {' . $vars . $depth_vars . '}';
            }

            $filter_statement = '{ '. $vars .
                                ' FILTER( regex(?s, "^' . $base_uri . '#.*", "i" ) || regex(?s, "^' . $base_uri . '$", "i" )). ' .
                                'OPTIONAL { ' . $depth_vars . '}}';
        } else {

            $construct_statement = 'construct {' . $vars . ' }';
            $filter_statement = '{ '. $vars .
                                ' FILTER( regex(?s, "^' . $base_uri . '#.*", "i" ) || regex(?s, "^' . $base_uri . '$", "i" )). }';
        }

        return $construct_statement . $filter_statement . ' offset ' . $offset . ' limit ' . $limit;
    }

    /**
     * Make and return a SPARQL count query, taken into account the passed query string parameters
     *
     * @param string $graph_name The graph_name that will be taken into account in the query
     *
     * @return string
     */
    public function createVariableCountQuery($graph_name = null)
    {
        list($s, $p, $o) = $this->query_string_params;

        if (!empty($graph_name)) {
            $select_statement = 'select (count(*) as ?count) FROM <' . $graph_name . '> ';
        } else {
            $select_statement = 'select (count(*) as ?count) ';
        }

        $filter_statement = "{ $s $p $o }";

        return $select_statement . $filter_statement;
    }

    /**
     * Creates a query that fetches all of the triples that match with the query sting parameters
     *
     * @return string
     */
    public function createVariableConstructSparqlQuery($graph_name = null, $limit = 100, $offset = 0, $depth = 3)
    {
        list($s, $p, $o) = $this->query_string_params;

        if (!empty($graph_name)) {
            $construct_statement = "construct { $s $p $o } FROM <" . $graph_name . ">";
        } else {
            $construct_statement = "construct { $s $p $o }";
        }

        $filter_statement = "{ $s $p $o }";

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