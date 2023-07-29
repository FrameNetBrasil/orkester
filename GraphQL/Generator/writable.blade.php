input {{ $typename }}MutationInput {
@foreach ($attributes as $attribute)
    {{$attribute['name']}}: {{$attribute['type']}}
@endforeach
@foreach ($associations as $association)
    {{$association['name']}}: {{$association['type']}}MutationInput
@endforeach
}

@foreach ($associations as $association)
input {{ $typename }}{{ $association['type'] }}Association {
    mode:@if($association['cardinality']->value == "associative") AssociativeOperationMode @else AssociationOperationMode @endif

    data: [{{ $association['type'] }}MutationInput!]
}
@endforeach

type {{ $typename }}Mutation {
    insert(object: {{ $typename }}MutationInput): {{ $typename }}
    insert_batch(objects: [{{ $typename }}MutationInput!]): [{{ $typename }}!]!
}
