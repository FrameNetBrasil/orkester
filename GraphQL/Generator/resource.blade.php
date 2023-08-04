type {{ $typename }} {
@foreach ($attributes as $attribute)
    {{$attribute['name']}}: {{$attribute['type']}}@if(!$attribute['nullable'])!@endif

@endforeach
@foreach ($associations as $association)
    {{$association['name']}}(where: {{$association['type']}}Where pluck: String): @if ($association['cardinality'] == "one") {{ $association['type'] }}@if(!$association['nullable'])!@endif @else[{{ $association['type'] }}!]@endif

@endforeach
}

input {{ $typename }}Where {
    or: [{{ $typename }}Where!]
    and: [{{ $typename }}Where!]
    _condition: [CustomCondition!]
@foreach ($attributes as $attribute)
    {{$attribute['name']}}: WhereCondition
@endforeach
@foreach ($associations as $association)
    {{$association['name']}}: {{$association['type']}}Where
@endforeach
}

type {{ $typename }}Resource {
    list(
        id: ID
        where: {{ $typename }}Where
        limit: Int
        offset: Int
        group: [String!]
        order: [Order!]
        pluck: String
        having: {{ $typename }}Where
        join: [Join!]
        distinct: Boolean
    ): [{{ $typename }}!]!
    find(
        id: ID
        pluck: String
        where: {{ $typename }}Where
    ): {{ $typename }}
@foreach ($operations['query'] as $operation)
    {{ $operation['name'] }}@if(count($operation['parameters']) > 0)(
    @foreach($operation['parameters'] as $parameter)
        {{$parameter['name']}}: {{$parameter['type']}}{{$parameter['nullable'] ? '' : '!'}}
    @endforeach{{')'}}
    @endif: {{$operation['return']['type']}}{{$operation['return']['nullable'] ? '' : '!'}}
@endforeach
}


input {{ $typename }}MutationInput {
@foreach ($attributes as $attribute)
    {{$attribute['name']}}: {{$attribute['type']}}
@endforeach
@foreach ($associations as $association)
    {{$association['name']}}: {{ $typename }}{{ $association['type'] }}Association
@endforeach
}

@foreach ($associations as $association)
input {{ $typename }}{{ $association['type'] }}Association {
@if($association['cardinality']->value == "associative")
    append: [ID!]
    delete: [ID!]
    replace: [ID!]
@elseif($association['cardinality']->value == "many")
    upsert: [{{ $association['type'] }}MutationInput!]
    insert: [{{ $association['type'] }}MutationInput!]
    update: [ID!]
    delete: [ID!]
@else
    id: ID{{$association['nullable'] ? '' : '!'}}
@endif
}
@endforeach

type {{ $typename }}Mutation {
@if(method_exists($resource, 'insert'))
    insert(object: {{ $typename }}MutationInput!): {{ $typename }}
    insert_batch(objects: [{{ $typename }}MutationInput!]!): [{{ $typename }}!]!
@endif
@if(method_exists($resource, 'upsert'))
    upsert(object: {{ $typename }}MutationInput!): {{ $typename }}
    upsert_batch(objects: [{{ $typename }}MutationInput!]): [{{ $typename }}!]!
@endif
@if(method_exists($resource, 'update'))
    update(id: ID! set: {{ $typename }}MutationInput!): {{ $typename }}
    update_batch(where: {{ $typename }}Where! set: {{ $typename }}MutationInput!): {{ $typename }}
@endif
@if(method_exists($resource, 'delete'))
    delete(id: ID!): Int
    delete_batch(where: {{ $typename }}Where!): Int
@endif
@foreach ($operations['mutation'] as $operation)
    {{ $operation['name'] }}@if(count($operation['parameters']) > 0)(
    @foreach($operation['parameters'] as $parameter)
        {{$parameter['name']}}: {{$parameter['type']}}{{$parameter['nullable'] ? '' : '!'}}
    @endforeach{{')'}}@endif: {{$operation['return']['type']}}{{$operation['return']['nullable'] ? '' : '!'}}
@endforeach
}
