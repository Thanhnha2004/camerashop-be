<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{

    public function createCategory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:categories,slug|max:255',
            'parent_id' => 'nullable|exists:categories,id'
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);

        }

        $category = Category::create($data);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    public function updateCategory(Request $request, $id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('categories')->ignore($category->id)],
            'parent_id' => 'nullable|exists:categories,id'
        ]);

        $category->update($data);

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    public function deleteCategory($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }
}
