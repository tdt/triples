<?php

class TurtleSource extends SemanticDataSource
{
    protected $table = 'turtle_sources';

    protected $fillable = array('uri');

    public function delete()
    {
        $source_type = $this->source()->first();
        $source_type->delete();

        parent::delete();
    }
}
