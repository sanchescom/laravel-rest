<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Query;

class JsonApiGrammar implements Grammar
{
    /**
     * @return array<string, mixed>
     */
    public function compile(QueryState $state): array
    {
        $query = $state->extra;

        foreach ($state->wheres as $where) {
            if ($where['operator'] === '=') {
                $query['filter'][$where['field']] = $where['value'];
            } else {
                $query['filter'][$where['field']][$where['operator']] = $where['value'];
            }
        }

        if ($state->orders !== []) {
            $query['sort'] = implode(',', array_map(
                fn (array $order) => $order['direction'] === 'desc' ? "-{$order['field']}" : $order['field'],
                $state->orders,
            ));
        }

        if ($state->limit !== null) {
            $query['page']['size'] = $state->limit;
        }

        if ($state->offset !== null) {
            $query['page']['offset'] = $state->offset;
        }

        if ($state->page !== null) {
            $query['page']['number'] = $state->page;
        }

        return $query;
    }
}
