<?php

namespace App\Http\Controllers\Admin; // Thường đặt trong thư mục Admin

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminProductController extends Controller
{
    /**
     * Lấy danh sách sản phẩm cho Admin, bao gồm phân trang và bộ lọc.
     */
    public function show(Request $request)
    {
        // 1. Lấy tham số phân trang và tìm kiếm/lọc
        $perPage = $request->query('per_page', 10);
        $search = $request->query('search'); // Lọc theo tên hoặc SKU
        $status = $request->query('status'); // Lọc theo trạng thái
        $brandId = $request->query('brand_id'); // Lọc theo thương hiệu

        // 2. Bắt đầu truy vấn
        $query = Product::with(['brand', 'category']); // Eager load các mối quan hệ cần thiết

        // 3. Áp dụng bộ lọc (Filters)
        if ($search) {
            $query->where(function ($q) use ($search) {
                // Tìm kiếm không phân biệt chữ hoa/thường trong tên và SKU
                $q->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('sku', 'LIKE', '%' . $search . '%');
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($brandId) {
            $query->where('brand_id', $brandId);
        }

        // 4. Phân trang và sắp xếp (Sắp xếp theo ID mới nhất)
        $products = $query->latest('id')->paginate($perPage);

        // 5. Trả về kết quả
        return response()->json([
            'message' => 'Lấy danh sách sản phẩm cho admin thành công.',
            'data' => $products
        ]);
    }

    /**
     * [POST] Tạo mới một sản phẩm.
     */
    public function create(Request $request)
    {
        // 1. Validation
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'name' => 'required|string|max:500',
            'slug' => 'nullable|string|unique:products,slug|max:500',
            'sku' => 'required|string|unique:products,sku|max:500', // SKU phải là duy nhất
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|gt:price', // Lớn hơn giá bán
            'stock_quantity' => 'required|integer|min:0',
            'product_condition' => ['required', Rule::in(['new', 'used'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_featured' => 'boolean',

            // Validation cho hình ảnh (Giả định: primary_image là file)
            'primary_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Ảnh chính (file)
            'secondary_images' => 'nullable|array',
            'secondary_images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Các ảnh phụ (mảng file)

            // Validation cho Specifications (Giả định nhận mảng key-value)
            'specifications' => 'nullable|array',
            'specifications.*.spec_key' => 'required|string|max:255',
            'specifications.*.spec_value' => 'required|string|max:255',
        ]);

        // 2. Tạo slug nếu không được cung cấp
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
            // Đảm bảo slug vẫn duy nhất (ví dụ: thêm ID nếu trùng)
            $i = 1;
            while (Product::where('slug', $data['slug'])->exists()) {
                $data['slug'] = Str::slug($data['name']) . '-' . $i++;
            }
        }

        // 3. Xử lý logic tạo sản phẩm và hình ảnh trong Transaction
        DB::beginTransaction();
        try {
            // Tạo sản phẩm
            $product = Product::create($data);

            // 4. Xử lý Tải lên Hình ảnh (Chính và Phụ)
            $imageRecords = [];
            $disk = 'public'; // Chọn disk lưu trữ

            // Xử lý Primary Image (Ảnh chính)
            $path = $request->file('primary_image')->store('products', $disk);
            $imageRecords[] = [
                'product_id' => $product->id,
                'image_url' => Storage::disk($disk)->url($path),
                'is_primary' => 1,
                'sort_order' => 0,
                'created_at' => now(),
            ];

            // Xử lý Secondary Images (Ảnh phụ)
            if ($request->hasFile('secondary_images')) {
                foreach ($request->file('secondary_images') as $index => $image) {
                    $path = $image->store('products/images', $disk);
                    $imageRecords[] = [
                        'product_id' => $product->id,
                        'image_url' => Storage::disk($disk)->url($path),
                        'is_primary' => 0,
                        'sort_order' => $index + 1,
                        'created_at' => now(),
                    ];
                }
            }

            // 5. Lưu tất cả bản ghi hình ảnh vào DB
            if (!empty($imageRecords)) {
                $product->images()->insert($imageRecords);
            }

            // 6. Xử lý Specifications
            if (isset($data['specifications'])) {
                $specsData = [];
                foreach ($data['specifications'] as $spec) {
                    $specsData[] = [
                        'product_id' => $product->id,
                        'spec_key' => $spec['spec_key'],
                        'spec_value' => $spec['spec_value'],
                        'created_at' => now(),
                    ];
                }
                $product->specifications()->insert($specsData);
            }


            DB::commit();

            // Tải lại sản phẩm để bao gồm các mối quan hệ (images, specs)
            $product->load(['images', 'specifications']);

            return response()->json([
                'message' => 'Product created successfully',
                'data' => $product
            ], 201); // 201 Created

        } catch (\Exception $e) {
            DB::rollBack();
            // Xử lý lỗi (ví dụ: xóa file đã tải lên nếu transaction thất bại)
            return response()->json([
                'message' => 'Failed to create product.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        // 1. Tải sản phẩm cùng các quan hệ
        $product = Product::with(['images', 'specifications'])->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        // 2. Validation
        $data = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'brand_id' => 'sometimes|exists:brands,id',
            'name' => 'sometimes|string|max:500',
            // Sử dụng Rule::unique để loại trừ chính sản phẩm đang cập nhật
            'slug' => ['sometimes', 'string', 'max:500', Rule::unique('products')->ignore($product->id)],
            'sku' => ['sometimes', 'string', 'max:500', Rule::unique('products')->ignore($product->id)],
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            // Nếu compare_price được gửi, nó phải lớn hơn price (hoặc giá price hiện tại nếu price không được gửi)
            'compare_price' => 'nullable|numeric|gt:price',
            'stock_quantity' => 'sometimes|integer|min:0',
            'product_condition' => ['sometimes', Rule::in(['new', 'used'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'is_featured' => 'sometimes|boolean', // Thêm 'sometimes' để không bắt buộc gửi
            'primary_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'secondary_images' => 'nullable|array',
            'secondary_images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'specifications' => 'nullable|array',
            'specifications.*.spec_key' => 'required|string|max:255',
            'specifications.*.spec_value' => 'required|string|max:255',
        ]);

        // 3. Thực hiện Transaction
        DB::beginTransaction();
        try {
            // Cập nhật các thuộc tính chính của sản phẩm
            $product->update($data);

            $disk = 'public';
            /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
            $storage = Storage::disk($disk);

            // --- Xử lý primary image ---
            if ($request->hasFile('primary_image')) {
                $oldPrimary = $product->images()->where('is_primary', 1)->first();
                if ($oldPrimary) {
                    // Xóa file cũ khỏi storage và record trong DB
                    $oldPath = str_replace($storage->url(''), '', $oldPrimary->image_url);
                    $storage->delete($oldPath);
                    $oldPrimary->delete();
                }

                // Lưu file mới và tạo record
                $path = $request->file('primary_image')->store('products/images', $disk);
                $product->images()->create([
                    'image_url' => $storage->url($path),
                    'is_primary' => 1,
                    'sort_order' => 0,
                ]);
            }

            // --- Xử lý secondary images ---
            if ($request->hasFile('secondary_images')) {
                // Xóa tất cả hình ảnh phụ cũ (cả file và record)
                $secondaryOld = $product->images()->where('is_primary', 0)->get();
                foreach ($secondaryOld as $img) {
                    $oldPath = str_replace($storage->url(''), '', $img->image_url);
                    $storage->delete($oldPath);
                    $img->delete();
                }

                // Lưu file mới và tạo record
                foreach ($request->file('secondary_images') as $index => $image) {
                    $path = $image->store('products/images', $disk);
                    $product->images()->create([
                        'image_url' => $storage->url($path),
                        'is_primary' => 0,
                        'sort_order' => $index + 1,
                    ]);
                }
            }

            // --- Xử lý specifications ---
            if (isset($data['specifications'])) {
                // Xóa tất cả spec cũ và chèn lại
                $product->specifications()->delete();
                $specsData = [];
                foreach ($data['specifications'] as $spec) {
                    $specsData[] = [
                        'spec_key' => $spec['spec_key'],
                        'spec_value' => $spec['spec_value'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                $product->specifications()->insert($specsData);
            }

            DB::commit();

            // *** Dòng quan trọng đã được thêm: REFRESH ***
            // Tải lại model từ DB để lấy các giá trị mới nhất (name, price, stock_quantity,...)
            // và tải lại các quan hệ (images, specifications) đã được cập nhật.
            $product->refresh()->load(['images', 'specifications']);
            // ********************************************

            return response()->json([
                'message' => 'Product updated successfully',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            // LƯU Ý: Nếu lỗi xảy ra sau khi upload file, các file đã upload sẽ vẫn còn trên storage
            // Cần thêm logic để dọn dẹp (tùy chọn)
            return response()->json([
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function delete($id)
    {
        $product = Product::with(['images', 'specifications'])->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        DB::beginTransaction();
        try {
            $disk = 'public';
            /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
            $storage = Storage::disk($disk);

            // Xóa tất cả ảnh (primary + secondary)
            foreach ($product->images as $img) {
                $oldPath = str_replace($storage->url(''), '', $img->image_url);
                $storage->delete($oldPath);
            }

            // Xóa ảnh và specifications trong DB
            $product->images()->delete();
            $product->specifications()->delete();

            // Xóa sản phẩm
            $product->delete();

            DB::commit();
            return response()->json([
                'message' => 'Product deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete product',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}