<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Brand;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class AdminBrandController extends Controller
{
    public function createBrand(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:brands,slug|max:255',
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);

        }

        $brand = Brand::create($data);

        return response()->json([
            'message' => 'Brand created successfully',
            'data' => $brand
        ], 201);
    }

    public function updateBrand(Request $request, $id)
    {
        $brand = Brand::find($id);
        if (!$brand) {
            return response()->json(['message' => 'Brand not found'], 404);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('brands')->ignore($brand->id)],
        ]);

        $brand->update($data);

        return response()->json([
            'message' => 'Brand updated successfully',
            'data' => $brand
        ]);
    }

    public function deleteBrand($id)
    {
        $brand = Brand::find($id);
        if (!$brand) {
            return response()->json(['message' => 'Brand not found'], 404);
        }

        $brand->delete();

        return response()->json([
            'message' => 'Brand deleted successfully'
        ]);
    }
}
