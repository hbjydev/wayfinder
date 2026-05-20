<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Inertia\Inertia;
use Inertia\Response;

class PaginatedProductController
{
    public function index(): Response
    {
        /** @var LengthAwarePaginator<Product> $products */
        $products = Product::query()->paginate(15);

        return Inertia::render('PaginatedProducts/Index', [
            'products' => $products,
        ]);
    }

    public function simple(): Response
    {
        /** @var Paginator<Product> $products */
        $products = Product::query()->simplePaginate(15);

        return Inertia::render('PaginatedProducts/Simple', [
            'products' => $products,
        ]);
    }

    public function cursor(): Response
    {
        /** @var CursorPaginator<Product> $products */
        $products = Product::query()->cursorPaginate(15);

        return Inertia::render('PaginatedProducts/Cursor', [
            'products' => $products,
        ]);
    }
}
