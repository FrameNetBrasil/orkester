@if(array_key_exists('_class', $docs))
"""
{{ $docs['_class'] }}
"""
@endif
type {{ $typename }} {
@foreach ($attributes as $attribute)
    @if(array_key_exists($attribute['name'], $docs))    """
        {{ $docs[$attribute['name']] }}
        """
    @endif
    {{$attribute['name']}}: {{$attribute['type']}}@if(!$attribute['nullable'])!@endif

@endforeach
@foreach ($associations as $association)
    @if(array_key_exists($association['name'], $docs))  """
        {{ $docs[$association['name']] }}
        """
    @endif
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
    ): [{{ $typename }}!]!
    find(
        id: ID
        where: {{ $typename }}Where
    ): {{ $typename }}
}
