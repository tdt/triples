<?php

class RdfSource extends SemanticDataSource
{
    protected $table = 'rdfsources';

    protected $fillable = array('uri');
}
