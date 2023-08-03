type Query {
@foreach ($resources ?? [] as $resource)
    {{ $resource['name'] }}: {{ $resource['typename'] }}Resource
@endforeach
    service: ServiceQuery
}

type Mutation {
@foreach ($writableResources ?? [] as $resource)
    {{ $resource['name'] }}: {{ $resource['typename'] }}Mutation
@endforeach
    service: ServiceMutation
}
