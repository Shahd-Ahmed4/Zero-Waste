<?php

namespace App\Http\Controllers;

use App\Models\branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class BranchController extends Controller
{
    
    
    private function getVendorOrFail()
    {
        $vendor = Auth::user()->vendor;

        if (!$vendor) {
            abort(404, 'Vendor profile not found');
        }

        return $vendor;
    }

    
    public function index()
    {
        $vendor = $this->getVendorOrFail();
        $branches = $vendor->branches()->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $branches
        ]);
    }

    
    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_name' => 'required|string|max:255',
            'opening_hours' => 'required|string',
            'store_address' => 'required|string',
            'contact_email' => 'required|email',
            'contact_phone' => 'required|string',
            'lat' => 'required|numeric|between:-90,90',
            'long' => 'required|numeric|between:-180,180',
        ]);

        $vendor = $this->getVendorOrFail();

        $branch = $vendor->branches()->create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Branch added successfully!',
            'data' => $branch
        ], 201);
    }

   
    public function show($id)
    {
        $vendor = $this->getVendorOrFail();

        $branch = $vendor->branches()->withCount(['offers', 'orders'])->with([
            'offers' => function ($q) {
                $q->where('status', 'active')
                    ->where('expiration_time', '>', now())
                    ->latest()
                    ->limit(5);
            },
            'orders' => function ($q) {
                $q->latest()
                    ->limit(5);
            }
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'branch_details' => $branch,
                'stats' => [
                    'total_offers' => $branch->offers_count,
                    'total_orders' => $branch->orders_count,
                ]
            ]
        ]);
    }
    public function allOrders(Request $request, $id)
    {
        $vendor = $this->getVendorOrFail();
        $branch = $vendor->branches()->findOrFail($id);

        $query = $branch->orders();

       
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        
        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest()->paginate(10)
        ]);
    }

    /**
     * 4. تحديث بيانات فرع
     */
    public function update(Request $request, $id)
    {
        $vendor = $this->getVendorOrFail();
        $branch = $vendor->branches()->findOrFail($id);

        $data = $request->validate([
            'branch_name' => 'sometimes|string',
            'opening_hours' => 'sometimes|string',
            'store_address' => 'sometimes|string',
            'contact_phone' => 'sometimes|string',
            'status' => 'sometimes|in:active,inactive',
            'lat' => 'sometimes|numeric|between:-90,90',
            'long' => 'sometimes|numeric|between:-180,180',
        ]);

        $branch->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Branch updated successfully',
            'data' => $branch
        ]);
    }

    
    public function destroy($id)
    {
        $vendor = $this->getVendorOrFail();
        $branch = $vendor->branches()->findOrFail($id);

        
        $branch->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Branch deleted successfully'
        ]);
    }

    
    public function nearby(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'long' => 'required|numeric',
            'radius' => 'nullable|numeric|min:1',
        ]);

        $lat = $request->lat;
        $lon = $request->long;
        $radius = $request->radius ?? 10;

        $branches = branch::selectRaw(
            "*, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(long) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance",
            [$lat, $lon, $lat]
        )
            ->where('status', 'active')
            ->having('distance', '<=', $radius)
            ->orderBy('distance')
            ->with('vendor:id,business_name,logo')
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $branches
        ]);
    }
    public function getBranchDetails($id)
    {
        
        $branch = branch::with(['offers', 'vendor:id,business_name,logo'])->find($id);

        if (!$branch) {
            return response()->json([
                'status' => 'error',
                'message' => 'Branch not found!'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $branch
        ], 200);
    }
}
