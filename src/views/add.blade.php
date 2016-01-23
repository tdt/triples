@extends('layouts.admin')

@section('content')
    <div class='row header'>
        <div class="col-sm-10">
            <h3>
                <a href='{{ URL::to('api/admin/triples') }}' class='back'>
                    <i class='fa fa-angle-left'></i>
                </a>
                Add a triple
            </h3>
        </div>
        <div class='col-sm-2 text-right'>
            <button type='submit' class='btn btn-cta btn-add-triple margin-left'><i class='fa fa-plus'></i> Add</button>
        </div>
    </div>

    <br/>

    <div class='row'>
        <div class="col-sm-12">
            <div class="alert alert-danger error hide">
                <i class='fa fa-2x fa-exclamation-circle'></i> <span class='text'></span>
            </div>
        </div>
    </div>

    <div class="col-sm-12">
        <form class='form form-horizontal add-triple'>

            <ul class="nav nav-tabs">
                <?php $i = 0 ?>
                @foreach($triples_spec as $type => $type_options)
                    <li @if($i == 0) class='active' @endif><a href="#{{ $type }}" data-toggle="tab">{{ strtoupper($type) }}</a></li>
                    <?php $i++ ?>
                @endforeach
            </ul>

            <div class='panel'>
                <div class='panel-body'>
                    <div class="tab-content">
                        <br/>
                        <?php $i = 0 ?>
                        @foreach($triples_spec as $type => $type_options)
                        <div class="tab-pane fade in @if($i == 0){{ 'active' }}@endif" id="{{ $type }}" data-type='{{ $type }}'>
                            @foreach($type_options->parameters as $param => $param_options)
                                @if($param != 'type')
                                    <div class='row'>
                                        <div class="form-group">
                                            <label class="col-sm-2 control-label">
                                                {{ $param_options->name }}
                                            </label>
                                            <div class="col-sm-10">
                                                @if($param_options->type == 'string')
                                                    <input type="text" class="form-control" id="{{ $param }}" name="{{ $param }}" placeholder="" @if(isset($param_options->default_value)) value='{{ $param_options->default_value }}' @endif>
                                                @elseif($param_options->type == 'text')
                                                    <textarea class="form-control" id="{{ $param }}" name="{{ $param }}"> @if (isset($param_options->default_value)) {{ $param_options->default_value }}@endif</textarea>
                                                @elseif($param_options->type == 'integer')
                                                    <input type="number" class="form-control" id="{{ $param }}" name="{{ $param }}" placeholder="" @if(isset($param_options->default_value)) value='{{ $param_options->default_value }}' @endif>
                                                @elseif($param_options->type == 'boolean')
                                                    <input type='checkbox' class="form-control" id="{{ $param }}" name="{{ $param }}" checked='checked'/>
                                                @endif
                                                <div class='help-block'>
                                                    {{{ $param_options->description }}}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <?php $i++ ?>
                        @endforeach
                    </div>
                </div>
            </div>

        </form>
    </div>
    <script type="text/javascript" src="{{ URL::to('packages/triples/triples.min.js') }}"></script>
@stop