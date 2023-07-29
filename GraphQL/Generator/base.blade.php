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

enum AssociationOperationMode {
    insert
    upsert
    update
}

enum AssociativeOperationMode {
    append
    delete
    replace
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
}

type Query {
@foreach ($resources ?? [] as $resource)
    {{ $resource['name'] }}: {{ $resource['typename'] }}Resource
@endforeach
    _total(operation: String!): Int
}

type Mutation {
@foreach ($writableResources ?? [] as $resource)
    {{ $resource['name'] }}: {{ $resource['typename'] }}Mutation
@endforeach
}
