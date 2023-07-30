scalar Mixed

schema {
    query: Query
    mutation: Mutation
}

input Order {
    asc: String
    desc: String
}

input Join {
    LEFT: String
    RIGHT: String
    INNER: String
}

input CustomCondition {
    expr: String!
    where: WhereCondition!
}

"""
Only one condition should be used per field.

Multiple conditions on the same field should be wrapped on `and` or `or` groups.
"""
input WhereCondition {
    eq: Mixed
    neq: Mixed
    lt: Int
    lte: Int
    gt: Int
    gte: Int
    in: [Mixed!]
    nin: [Mixed!]
    contains: String
    startsWith: String
    endsWith: String
    nlike: String
    like: String
    regex: String
    result_in: String
    result_nin: String
}

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
