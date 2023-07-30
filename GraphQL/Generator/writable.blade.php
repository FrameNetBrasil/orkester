
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
    insert(object: {{ $typename }}MutationInput!): {{ $typename }}
    insert_batch(objects: [{{ $typename }}MutationInput!]!): [{{ $typename }}!]!
    upsert(object: {{ $typename }}MutationInput!): {{ $typename }}
    upsert_batch(objects: [{{ $typename }}MutationInput!]): [{{ $typename }}!]!
    update(id: ID! set: {{ $typename }}MutationInput!): {{ $typename }}
    update_batch(where: {{ $typename }}Where! set: {{ $typename }}MutationInput!): {{ $typename }}
    delete(id: ID!): Int
    delete_batch(where: {{ $typename }}Where!): Int
@foreach ($operations as $operation)
    {{ $operation['name'] }}@if(count($operation['parameters']) > 0)(
    @foreach($operation['parameters'] as $parameter)
    {{$parameter['name']}}: {{$parameter['type']}}{{$parameter['nullable'] ? '' : '!'}}
    @endforeach{{')'}}@endif: {{$operation['return']['type']}}{{$operation['return']['nullable'] ? '' : '!'}}
@endforeach
}
