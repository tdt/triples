@extends('layouts.admin')

@section('content')

    <div class='row header'>
        <div class="col-sm-7">
            <h3>Manage your triples</h3>
        </div>
        <div class="col-sm-5 text-right">
            <a href='{{ URL::to('api/admin/triples/add') }}' class='btn btn-primary margin-left'>
                <i class='fa fa-plus'></i> Add
            </a>
        </div>
    </div>

    <div class="col-sm-12">

        <br/>

        @foreach($triples as $triple)

            <div class="panel dataset dataset-link button-row panel-default clickable-row" data-href='{{ URL::to('api/triples/' . $triple->id) }}'>
                <div class="panel-body">
                    <div class='icon'>
                        <i class='fa fa-lg fa-share-alt'></i>
                    </div>
                    <div>
                        <div class='row'>
                            <div class='col-sm-2'>
                                <h4 class='dataset-title'>
                                    {{ $triple->id  }}
                                </h4>
                            </div>
                            <div class='col-sm-2'>
                                {{ $triple->type  }}
                            </div>
                            <div class='col-sm-6'>
                                @if(!empty($triple->uri))
                                    {{ $triple->uri }}
                                @elseif(!empty($triple->startfragment))
                                    {{ $triple->startfragment }}
                                @elseif(!empty($triple->endpoint))
                                    @if(!empty($triple->named_graph))
                                        {{ $triple->named_graph }}&nbsp;&ndash;&nbsp;
                                    @endif
                                    {{ $triple->endpoint }}
                                @endif
                            </div>
                            <div class='col-sm-2 text-right'>
                                <div class='btn-group'>
                                    @if(Tdt\Core\Auth\Auth::hasAccess('tdt.triples.delete'))
                                        <a href='{{ URL::to('api/admin/triples/delete/'. $triple->id) }}' class='btn delete' title='Delete this dataset'>
                                            <i class='fa fa-times icon-only'></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

     <div class='col-sm-12 empty'>
        <div class='panel panel-default @if(count($triples) > 0) hide @endif'>
            <div class="panel-body note">
                <i class='fa fa-lg fa-warning'></i>&nbsp;&nbsp;
                @if(count($triples) === 0)
                    This datatank has no configured triples yet.
                @else
                    No triple(s) found with the filter <strong>'<span class='dataset-filter'></span>'</strong>
                @endif
            </div>
        </div>
    </div>

@stop

@section('navigation')
     @if(count($triples) > 0)
        <div class="search pull-right hidden-xs">
            <input id='dataset-filter' type="text" placeholder='Search for triples' spellcheck='false'>
            <i class='fa fa-search'></i>
        </div>
    @endif
@stop