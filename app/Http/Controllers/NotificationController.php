<?php

namespace App\Http\Controllers;

use App\Models\notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $notifications = auth()->user()->notification()
            ->orderByDesc('created_at') // الأحدث يظهر فوق
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function unreadCount()
    {
        $count = auth()->user()->notification()
            ->where('is_read', 0)
            ->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function markAsRead($id)
    {
        // بنجيب الإشعار بشرط يكون يخص اليوزر اللي عامل login
        $notification = auth()->user()->notification()->findOrFail($id);

        $notification->update([
            'is_read' => 1
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function markAllAsRead()
    {
        auth()->user()->notification()
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    }
    public function destroy($id)
    {
        // بنجيب الإشعار اللي يخص اليوزر ده بالذات عشان محدش يمسح إشعار غيره
        $notification = auth()->user()->notification()->findOrFail($id);

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully'
        ]);
    }
    public function clearAll()
    {
        // بيمسح كل الإشعارات المرتبطة باليوزر ده
        auth()->user()->notification()->delete();

        return response()->json([
            'success' => true,
            'message' => 'All notifications cleared'
        ]);
    }

}
