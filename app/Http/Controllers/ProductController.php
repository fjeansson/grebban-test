<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Pagination\Paginator;

class ProductController extends Controller
{
    protected $products;
    protected $attributes;

    public function __construct(Request $request)
    {
        # fetch input data
        $this->products = Http::get('https://draft.grebban.com/backend/products.json')->collect()->sortBy('name')->toArray();
        $this->attributes = Http::get('https://draft.grebban.com/backend/attribute_meta.json')->collect()->keyBy('code')->toArray();

        # merge to request (for validation)
        $request->merge([
            'products' => $this->products,
            'attributes' => $this->attributes
        ]);
    }

    public function index(Request $request)
    {
        # validate
        $request->validate([
            # parameters
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1'],
            # input data
            'products' => ['array', 'min:1'],
            'products.*.id' => ['integer', 'distinct'],
            'products.*.name' => ['string'],
            'products.*.attributes' => ['array'],
            'attributes' => ['array', 'min:1'],
            'attributes.*.name' => ['required', 'string'],
            'attributes.*.code' => ['required', 'alpha'],
            'attributes.*.values' => ['required', 'array'],
        ]);

        # iterate products
        foreach ($this->products as $pkey => $product) {
            $attributes = [];
            # iterate product attributes
            foreach ($product['attributes'] as $key => $value) {
                $values = explode(',', $value);
                # iterate attribute values
                foreach ($values as $value) {
                    # handle category
                    if ($key == 'cat') {
                        # subcategory
                        if (substr_count($value, '_') == 2) {
                            $parts = explode('_', $value);
                            $sub_cat_key = implode('_', array_slice($parts, 0, 2));
                            $attributes[] = [
                                'name' => $this->attributes[$key]['name'],
                                'value' => collect($this->attributes[$key]['values'])->keyBy('code')[$sub_cat_key]['name'] . ' > ' . collect($this->attributes[$key]['values'])->keyBy('code')[$value]['name']
                            ];
                            continue;
                        }
                        # single category
                        $attributes[] = [
                            'name' => $this->attributes[$key]['name'],
                            'value' => collect($this->attributes[$key]['values'])->keyBy('code')[$value]['name']
                        ];
                    } else {
                        # handle other attributes
                        $attributes[] = [
                            'name' => $this->attributes[$key]['name'],
                            'value' => collect($this->attributes[$key]['values'])->keyBy('code')[$value]['name']
                        ];
                    }
                }
            }
            # replace product attributes
            $this->products[$pkey]['attributes'] = $attributes;
        }

        # pagination
        $currentPage = Paginator::resolveCurrentPage();
        $perPage = request()->page_size ?? count($this->products);
        $totalPages = ceil(count($this->products) / $perPage);
        $reduced = array_slice($this->products, ($currentPage - 1) * $perPage, $perPage);

        return response()->json([
            'products' => $reduced,
            'page' =>  $currentPage,
            'totalPages' => $totalPages
        ]);
    }
}
