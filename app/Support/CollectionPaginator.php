<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class CollectionPaginator
{
    public static function paginate(iterable $items, Request $request, int $defaultPerPage = 10): LengthAwarePaginator
    {
        $collection = $items instanceof Collection ? $items->values() : collect($items)->values();
        $requestedPerPage = (int) $request->query('per_page', $defaultPerPage);
        $perPage = in_array($requestedPerPage, [10, 25, 50], true) ? $requestedPerPage : $defaultPerPage;
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $collection->forPage($page, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }
}
