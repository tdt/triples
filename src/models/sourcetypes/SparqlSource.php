<?php

class SparqlSource extends SemanticDataSource
{
    protected $table = 'sparqlsources';

    protected $fillable = array('endpoint', 'endpoint_password', 'endpoint_user', 'depth', 'named_graph');
}
