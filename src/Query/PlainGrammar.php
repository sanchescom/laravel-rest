<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Query;

class PlainGrammar implements Grammar
{
    /**
     * @return array<string, mixed>
     */
    public function compile(QueryState $state): array
    {
        $query = $state->extra;

        foreach ($state->wheres as $where) {
            if ($where['operator'] === 'in') {
                $query[$where['field']] = is_array($where['value']) ? implode(',', $where['value']) : $where['value'];

                continue;
            }

            $key = $where['operator'] === '='
                ? $where['field']
                : "{$where['field']}[{$where['operator']}]";
            $query[$key] = $where['value'];
        }

        if ($state->orders !== []) {
            $query['sort'] = implode(',', array_map(
                fn (array $order) => $order['direction'] === 'desc' ? "-{$order['field']}" : $order['field'],
                $state->orders,
            ));
        }

        if ($state->limit !== null) {
            $query['limit'] = $state->limit;
        }

        if ($state->offset !== null) {
            $query['offset'] = $state->offset;
        }

        if ($state->page !== null) {
            $query['page'] = $state->page;
        }

        return $query;
    }
}
