<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\VendorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//dol public ay hd y2dr yshofhom fe elhome 





Route::middleware('auth:sanctum')->group(function () {
    Route::post('/vendor/complete-setup', [VendorController::class, 'completesetup']);
    Route::get('/myprofile', [AuthController::class, 'profile']);//de lel cust we el vendor h7otha tht el auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);


    Route::middleware('vendoractive')->group(function () {
        Route::post('vendor/myprofile/update', [VendorController::class, 'update']);//y3ml update le el basic information
        Route::delete('/vendor/delete-account', [VendorController::class, 'destroy']);
        Route::get('vendor/myoffers', [OfferController::class, 'myOffers']); //yshof el offers bt3to bs
        Route::get('vendor/offers/{id}', [OfferController::class, 'showVendorOffer']); //yshof el offer mo3yn 3ndo 
        Route::post('/vendor/offers', [OfferController::class, 'store']);      // إضافة عرض جديد
        Route::put('/vendor/offers/{id}', [OfferController::class, 'update']); // تعديل عرض
        Route::patch('vendor/offers/{id}/status/', [OfferController::class, 'updateStatus']); //y3del el status bta3et offer mo3yn
        Route::delete('/vendor/offers/{id}', [OfferController::class, 'destroy']); // مسح عرض
        Route::get('/vendor/orders', [VendorController::class, 'getOrdersForMe']);
        Route::patch('/vendor/orders/{id}/status', [OrderController::class, 'updateStatus']);
        // عرض قائمة المبيعات (Order Items)
        Route::get('/sales', [OrderItemController::class, 'vendorSales']);

        // عرض تفاصيل "قطعة" مبيوعة معينة
        Route::get('/sales/{id}', [OrderItemController::class, 'showSoldItem']);

        // تقرير الأرباح والإحصائيات
        Route::get('/sales-report', [OrderItemController::class, 'salesReport']);

        // أكثر 5 منتجات مبيعاً
        Route::get('/top-selling', [OrderItemController::class, 'topSelling']);
        Route::get('/vendor/reviews', [VendorController::class, 'offerReviews']);
        Route::get('/vendor/offers/{offer_id}/reviews', [VendorController::class, 'showOfferReviews']);
        Route::get('/my-branches', [BranchController::class, 'index']);      // عرض كل فروعي
        Route::post('/branches', [BranchController::class, 'store']);        // إضافة فرع
        Route::get('/branches/{id}', [BranchController::class, 'show']);     // تفاصيل الفرع (العروض + الطلبات)
        Route::put('/branches/{id}', [BranchController::class, 'update']);   // تحديث بيانات فرع
        Route::delete('/branches/{id}', [BranchController::class, 'destroy']); // حذف فرع
        Route::get('/branches/{id}/all-orders', [BranchController::class, 'allOrders']); // روت إضافي لرؤية كل الطلبات بالتفصيل

    });
});



Route::middleware(['auth:sanctum', 'checkadmin'])->group(function () {
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/dashboard/activity', [DashboardController::class, 'getRecentActivity']);
    Route::get('/admin/customers', [AdminController::class, 'index']);
    Route::get('/admin/customers/{id}', [AdminController::class, 'show']);
    Route::get('/admin/all-orders', [AdminController::class, 'listAllOrders']);

    Route::middleware(['checkadmin:super_admin,manager'])->group(function () {
        Route::put('/profile', [AdminController::class, 'updateProfile']);
        Route::get('/admin/vendor/pending', [AdminController::class, 'pendingVendors']);  //yshof el vendors el status bt3ethom pending
        Route::get('/admin/vendor/{id}', [AdminController::class, 'showPendingDocs']); //yshof el docs bta3t el vendor 3shan yt2kd enhom tmamabl ma y3ml approve
        Route::post('/admin/vendor/{id}/accept', [AdminController::class, 'accept']);  //ywaf2 3la vendor
        Route::post('/admin/vendor/{id}/reject', [AdminController::class, 'reject']);  //yrfod vendor
        Route::patch('/admin/users/{id}/block', [AdminController::class, 'blockUser']);
        Route::patch('/admin/users/{id}/unblock', [AdminController::class, 'unblockUser']);
        Route::patch('/reviews/{id}/toggle-visibility', [AdminController::class, 'toggleVisibility']);

        Route::middleware(['checkadmin:super_admin'])->group(function () {
            Route::delete('/admin/customers/{id}', [AdminController::class, 'destroy']);
            Route::delete('/admin/offers/{id}', [AdminController::class, 'deleteOfferByAdmin']);
            Route::delete('/profile', [AdminController::class, 'deleteAccount']);
            Route::delete('/admin/vendor/{id}', [AdminController::class, 'destroyVendor']); //yms7 vendor
            Route::delete('/admin/customers/{id}', [AdminController::class, 'delete']);
        });
    });
});









Route::middleware(['auth:sanctum', 'checkcustomer'])->group(function () {
    Route::put('/customer/profile', [CustomerController::class, 'update']);
    Route::delete('customer/delete-profile', [CustomerController::class, 'destroy']);
    Route::post('/orders', [OrderController::class, 'store']); // إنشاء أوردر    //ana wa2fa hnaa 
    Route::get('/my-orders', [OrderController::class, 'index']); // عرض طلباته هو بس
    // عرض تفاصيل أوردر واحد محدد
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::get('/my-reviews', [ReviewController::class, 'myReviews']); // تقييماتي أنا ككاستمر
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
});


Route::post('/stripe/webhook', [PaymentController::class, 'handleWebhook']);
Route::get('/payment/success', [PaymentController::class, 'paymentSuccess']);
Route::get('/payment/cancel', [PaymentController::class, 'paymentCancel']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/vendors', [VendorController::class, 'index']);  //user ygeb kol el vendors esm we no3 we logo
Route::get('/branches/nearby', [BranchController::class, 'nearby']); //hygeb el branches el oryben mno 3n tare2 el lat we long
Route::get('/vendors/search', [VendorController::class, 'index']); //bel esm aw el no3
Route::get('/vendor/{id}', [VendorController::class, 'show']); //ygeb vendor mo3yn
Route::get('/offers', [OfferController::class, 'index']);
Route::get('/offers/{id}', [OfferController::class, 'show']);
Route::get('/offers/{offer_id}/reviews', [ReviewController::class, 'index']);