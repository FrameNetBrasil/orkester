type ServiceQuery {
    _total(operation: String!): Int
@foreach ($services['query'] as $service)
    {{ $service['name'] }}@if(count($service['parameters']) > 0)(
    @foreach($service['parameters'] as $parameter)
        {{$parameter['name']}}: {{$parameter['type']}}{{$parameter['nullable'] ? '' : '!'}}
    @endforeach{{')'}}
    @endif: {{$service['return']['type']}}{{$service['return']['nullable'] ? '' : '!'}}
@endforeach
}

type ServiceMutation {
@foreach ($services['mutation'] as $service)
    {{ $service['name'] }}(
    @foreach($service['parameters'] as $parameter)
    {{$parameter['name']}}: {{$parameter['type']}}{{$parameter['nullable'] ? '' : '!'}}
    @endforeach{{')'}}: {{$service['return']['type']}}{{$service['return']['nullable'] ? '' : '!'}}
@endforeach
}
