<?php

namespace Tdt\Triples\Repositories;

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
     * @param string  $base_uri    The base_uri that will serve as a subject in the query
     * @param string  $graph_name  The name of the graph to take into account for the query
     * @param integer $depth       The depth of a subjects propagation
     *
     * @return string
     */
    public function createCountQuery($base_uri = null, $graph_name = null, $depth = 3)
    {
        list($s, $p, $o) = self::$query_string_params;

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

        if ($s == '?s' && $p == '?p' && $o == '?o') {

            for ($i = 2; $i <= $depth; $i++) {

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
     * Make and return a SPARQL count query, the base URI forms that start of eligible subjects
     *
     * @param string  $base_uri    The base_uri that will serve as a subject in the query
     * @param string  $graph_name  The name of the graph to take into account for the query
     * @param integer $depth       The depth of a subjects propagation
     *
     * @return string
     */
    public function createCountAllQuery($base_uri, $graph_name = null, $depth = 3)
    {
        $vars = '?s ?p ?o. ';

        $last_object = '?o';
        $depth_vars = '';

        $construct_statement = '';
        $filter_statement = '';

        for ($i = 2; $i <= $depth; $i++) {

            $depth_vars .= $last_object . ' ?p' . $i . ' ?o' . $i . '. ';

            $last_object = '?o' . $i;
        }

        if (!empty($graph_name)) {
            $select_statement = 'select (count(*) as ?count) FROM <' . $graph_name . '> ';
        } else {
            $select_statement = 'select (count(*) as ?count) ';
        }

        $filter_statement = '{ '. $vars ;

        if (!empty($base_uri)) {
            $filter_statement .= 'FILTER( regex(?s, "^' . $base_uri . '.*", "i" )). ';
        }


        if (!empty($depth_vars)) {
            $filter_statement .= 'OPTIONAL { ' . $depth_vars . '}';
        }

        $filter_statement .= '}';

        return $select_statement . $filter_statement;
    }

    /**
     * Make and return a SPARQL query, the base URI forms that start of eligible subjects and its triples
     *
     * @param string  $base_uri    The base_uri that will serve as a subject in the query
     * @param string  $graph_name  The name of the graph to take into account for the query
     * @param integer $depth       The depth of a subjects propagation
     *
     * @return string
     */
    public function createFetchAllQuery($base_uri, $graph_name = null, $limit = 100, $offset = 0, $depth = 3)
    {
        $vars = '?s ?p ?o. ';

        $last_object = '?o';
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

        $filter_statement = '{ '. $vars .
        ' FILTER( regex(?s, "^' . $base_uri . '.*", "i" )). ';

        if (!empty($depth_vars)) {
            $filter_statement .= 'OPTIONAL { ' . $depth_vars . '}';
        }

        $filter_statement .= '}';

        return $construct_statement . $filter_statement . ' offset ' . $offset . ' limit ' . $limit;
    }

    /**
     * Creates a query that fetches all of the triples
     * of which the subject matches the base uri
     *
     * @param string $base_uri
     *
     * @return string
     */
    public function createFetchQuery($base_uri = null, $graph_name = null, $limit = 100, $offset = 0, $depth = 3)
    {
        list($s, $p, $o) = self::$query_string_params;

        if (empty($base_uri)) {
            return $this->createVariableFetchQuery($graph_name, $limit, $offset);
        }

        $vars = $s . ' ' . $p . ' ' . $o . '.';

        $last_object = $o;
        $depth_vars = '';

        $construct_statement = '';
        $filter_statement = '';

        // Only when no template parameter is given, add the depth parameters
        if ($s == '?s' && $p == '?p' && $o == '?o') {

            for ($i = 2; $i <= $depth; $i++) {

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
     * Creates a query that fetches all of the triples that match with the query sting parameters
     *
     * @return string
     */
    public function createVariableFetchQuery($graph_name = null, $limit = 100, $offset = 0, $depth = 3)
    {
        list($s, $p, $o) = self::$query_string_params;

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
