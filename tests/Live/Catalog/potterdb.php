<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Tests\Live\Catalog\Potterdb;

use Sanchescom\Rest\Model;
use Sanchescom\Rest\Query\JsonApiGrammar;
use Sanchescom\Rest\Relations\HasMany;

final class CharacterList extends Model
{
    protected ?string $endpoint = 'characters';

    protected ?string $dataKey = 'data';

    protected ?string $grammar = JsonApiGrammar::class;
}

final class CharacterDetail extends Model
{
    protected ?string $endpoint = 'characters';

    protected ?string $dataKey = 'data';
}

final class Book extends Model
{
    protected ?string $endpoint = 'books';

    protected ?string $dataKey = 'data';

    protected ?string $grammar = JsonApiGrammar::class;

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->nested();
    }
}

final class Chapter extends Model
{
    protected ?string $endpoint = 'chapters';

    protected ?string $dataKey = 'data';
}

return [
    'name' => 'PotterDB',
    'docs' => 'https://docs.potterdb.com/',
    'base_uri' => 'https://api.potterdb.com/v1/',
    'traits' => [
        'response' => 'jsonapi (data[{id,type,attributes,relationships,links}])',
        'pagination' => 'page[size] + page[number]; total in meta.pagination.records',
        'filters' => 'filter[<field>_<ransack predicate>]; __in needs indexed array params, comma/indexed lists are ignored',
        'sort' => 'sort=-field',
        'keys' => 'uuid',
        'relations' => 'books/{id}/chapters nested; other relations live under relationships.<name>.data, not a flat attribute',
        'errors' => '404 jsonapi errors[]',
    ],
    'client' => [
        'pagination' => ['style' => 'page', 'total' => 'meta.pagination.records', 'next' => 'links.next'],
    ],
    'scenarios' => [
        'list characters' => ['probe' => 'list', 'model' => CharacterList::class, 'min' => 50, 'fields' => ['id', 'attributes.name']],
        'find book' => ['probe' => 'find', 'model' => Book::class, 'id' => '9e74d8ae-6164-4a48-9b89-763abb0af154', 'fields' => ['attributes.title']],
        'get many books' => ['probe' => 'get-many', 'model' => Book::class, 'ids' => ['9e74d8ae-6164-4a48-9b89-763abb0af154', '97d73411-7805-432e-8571-31125e88ac52', 'bd7ef3ef-9260-4f22-ad33-a9a717701a01']],
        'filter house' => ['probe' => 'filter', 'model' => CharacterList::class, 'field' => 'house_eq', 'attribute' => 'attributes.house', 'value' => 'Gryffindor', 'min' => 25],
        'sort books by pages' => ['probe' => 'sort', 'model' => Book::class, 'field' => 'pages', 'attribute' => 'attributes.pages', 'direction' => 'desc'],
        'paginate characters' => ['probe' => 'paginate', 'model' => CharacterList::class, 'per_page' => 25],
        'simple paginate characters' => ['probe' => 'simple-paginate', 'model' => CharacterList::class, 'per_page' => 25],
        'lazy walk characters' => ['probe' => 'lazy', 'model' => CharacterList::class, 'chunk' => 50, 'take' => 150],
        'book chapters' => ['probe' => 'has-many', 'model' => Book::class, 'id' => '9e74d8ae-6164-4a48-9b89-763abb0af154', 'relation' => 'chapters', 'nested' => true, 'path_suffix' => 'books/9e74d8ae-6164-4a48-9b89-763abb0af154/chapters'],
        'eager chapters' => ['probe' => 'eager', 'model' => Book::class, 'relation' => 'chapters', 'mode' => 'concurrent'],
        'missing character' => ['probe' => 'not-found', 'model' => CharacterDetail::class, 'id' => 'doesnotexist'],
        'membership' => [
            'probe' => 'unsupported',
            'features' => ['query.where-in', 'eager.batch'],
            'reason' => 'Ransack _in needs filter[slug_in][]=a&filter[slug_in][]=b; comma lists and indexed arrays (filter[slug_in][0]=a) are ignored and return the unfiltered collection (verified on slug_in; house_in happens to accept a comma list).',
            'attempt' => ['probe' => 'where-in', 'model' => CharacterList::class, 'field' => 'slug_in', 'attribute' => 'attributes.slug', 'values' => ['1980s-hogwarts-gobstones-tournament-champion', '1980s-hogwarts-gobstones-tournament-champion-s-parents']],
        ],
        'belongs-to' => [
            'probe' => 'unsupported',
            'features' => ['relation.belongs-to'],
            'reason' => 'Related ids live under relationships.<name>.data.id (or as an array of {id,type} for to-many), not a flat top-level attribute; belongsTo reads a flat foreign key attribute on the parent.',
        ],
    ],
];
