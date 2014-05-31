<?php

namespace Tdt\Triples\Repositories;

use Illuminate\Http\Request;

class SparqlQueryBuilder
{

    private static $query_string_params;

    public function __construct(array $query_string_params = array('?s', '?p', '?o'))
    {
        self::$query_string_params = $query_string_params;
    }

    /**
     * Set the paramaters for the request
     *
     * @param array $parameters
     */
    public static function setParameters(array $parameters)
    {
        self::$query_string_params = $parameters;
    }

    /**
     * Return the parameters for the request
     *
     * @return array
     */
    public static function getParameters()
    {
        return self::$query_string_params;
    }

    /**
     * Make and return a SPARQL count query, taken into account the passed query string parameters
     *
     * @param string  $uri          The uri that will serve as a subject in the query
     * @param string  $root         The base host name
     * @param string  $graph_name   The name of the graph to take into account for the query
     * @param integer $depth        The depth of a subjects propagation
     * @param boolean $hash_variant Take hash variants of the subject into account
     *
     * @return string
     */
    public function createCountQuery($uri, $root, $graph_name = null, $depth = 1, $hash_variant = false)
    {
        list($s, $p, $o) = self::$query_string_params;

        $subject = '<' . $uri . '>';

        if (empty($uri) || $uri == $root) {
            // If the URI is the same as the root, we have to check all subjects (== /all)
            $subject = $s;
        }

        // If hash variants are required, we have to create our pattern as such that it fits into a regex filter pattern
        if ($hash_variant) {
            $subject = '?s';
        }

        $vars = $subject . ' ' . $p . ' ' . $o . '.';

        $last_object = $o;
        $depth_vars = '';

        $construct_statement = '';
        $filter_statement = '';

        for ($i = 2; $i <= $depth; $i++) {

            $depth_vars .= $last_object . ' ?p' . $i . ' ?o' . $i . '. ';

            $last_object = '?o' . $i;
        }

        $filter_statement = '{ '. $vars;

        if ($hash_variant) {

            // We've set the subject to '?s' before, so we know ?s is our subject's variable name

            $filter_statement .= ' FILTER( regex(?s, "^' . $uri . '#.*", "i" ) ';
            $filter_statement .= '|| regex(?s, "^' . $uri . '$", "i" ) ). ';
        }

        if (!empty($depth_vars)) {
            $filter_statement .= 'OPTIONAL { ' . $depth_vars . '}';
        }

        $filter_statement .= '}';

        if (!empty($graph_name)) {
            $select_statement = 'select (count(*) as ?count) FROM <' . $graph_name . '> ';
        } else {
            $select_statement = 'select (count(*) as ?count) ';
        }

        return $select_statement . $filter_statement;
    }

    /**
     * Creates a query that fetches all of the triples
     * of which the subject matches the base uri
     *
     * @param string  $uri          The uri that will serve as a subject in the query
     * @param string  $root         The base host name
     * @param string  $graph_name   The name of the graph to take into account for the query
     * @param integer $depth        The depth of a subjects propagation
     * @param boolean $hash_variant Take hash variants of the subject into account
     *
     * @return string
     */
    public function createFetchQuery($uri, $root, $graph_name = null, $limit = 100, $offset = 0, $depth = 1, $hash_variant = false)
    {
        list($s, $p, $o) = self::$query_string_params;

        $subject = '<' . $uri . '>';

        if (empty($uri) || $uri == $root) {
            // If the URI is the same as the root, we have to check all subjects (== /all)
            $subject = $s;
        }

         // If hash variants are required, we have to create our pattern as such that it fits into a regex filter pattern
        if ($hash_variant) {
            $subject = '?s';
        }

        $vars = $subject . ' ' . $p . ' ' . $o . '.';

        $last_object = $o;

        $depth_vars = '';

        $construct_statement = '';

        $filter_statement = '';

        for ($i = 2; $i <= $depth; $i++) {

            $depth_vars .= $last_object . ' ?p' . $i . ' ?o' . $i . '. ';

            $last_object = '?o' . $i;
        }

        if (!empty($graph_name)) {
            $construct_statement = 'construct {' . $vars . $depth_vars . '} FROM <' . $graph_name . '>';
        } else {
            $construct_statement = 'construct {' . $vars . $depth_vars . '}';
        }

        $filter_statement = '{ '. $vars;

        if ($hash_variant) {

            // We've set the subject to '?s' before, so we know ?s is our subject's variable name

            $filter_statement .= ' FILTER( regex(?s, "^' . $uri . '#.*", "i" ) ';
            $filter_statement .= '|| regex(?s, "^' . $uri . '$", "i" ) ). ';
        }

        if (!empty($depth_vars)) {
            $filter_statement .= 'OPTIONAL { ' . $depth_vars . '}';
        }

        $filter_statement .= '}';

        return $construct_statement . $filter_statement . ' offset ' . $offset . ' limit ' . $limit;
    }

    /**
     * Make and return a SPARQL count query, taken into account the passed query string parameters
     *
     * @param string $graph_name The graph_name that will be taken into account in the query
     *
     * @return string
     */
    public function createParameterCountQuery($graph_name = null)
    {
        list($s, $p, $o) = self::$query_string_params;

        if (!empty($graph_name)) {
            $select_statement = 'select (count(*) as ?count) FROM <' . $graph_name . '> ';
        } else {
            $select_statement = 'select (count(*) as ?count) ';
        }

        $filter_statement = "{ $s $p $o }";

        return $select_statement . $filter_statement;
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

    /**
     * Check if the request had any valuable request parameters
     */
    private function hasParameters()
    {
        $sparql_param_defaults = array('?s', '?p', '?o');

        foreach (self::$query_string_params as $param) {
            if (!in_array($param, $sparql_param_defaults)) {
                return true;
            }
        }

        return false;
    }
}
