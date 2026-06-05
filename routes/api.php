<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\SustainabilityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\VendorDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;







Route::middleware('auth:sanctum')->group(function () {
    Route::post('/vendor/complete-setup', [VendorController::class, 'completesetup']);
    Route::get('/myprofile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::get('/offers/smart-recommendations', [OfferController::class, 'getSmartRecommendations']);


    Route::middleware('vendoractive')->group(function () {
        Route::post('vendor/myprofile/update', [VendorController::class, 'update']);
        Route::put('/vendor/change-password', [VendorController::class, 'changePassword']);
        Route::delete('/vendor/delete-account', [VendorController::class, 'destroy']);
        Route::get('vendor/myoffers', [OfferController::class, 'myOffers']); 
        Route::get('vendor/offers/{id}', [OfferController::class, 'showVendorOffer']); 
        Route::post('/vendor/offers', [OfferController::class, 'store']);      
        Route::put('/vendor/offers/{id}', [OfferController::class, 'update']); 
        Route::patch('vendor/offers/{id}/status/', [OfferController::class, 'updateStatus']); 
        Route::delete('/vendor/offers/{id}', [OfferController::class, 'destroy']); 
        Route::get('/vendor/orders', [VendorController::class, 'getOrdersForMe']);
        Route::patch('/vendor/orders/{id}/status', [OrderController::class, 'updateStatus']);
        Route::get('/vendor/branches', [VendorDashboardController::class, 'getVendorBranches']);
        Route::get('/vendor/dashboard/orders-chart', [VendorDashboardController::class, 'ordersChart']);
        Route::get('/vendor/dashboard/overview', [VendorDashboardController::class, 'getOverviewStats']);
        Route::get('/vendor/dashboard/monthly-chart', [VendorDashboardController::class, 'getMonthlySalesChart']);

        Route::get('/vendor/dashboard/top-selling', [VendorDashboardController::class, 'topSelling']);
        Route::get('/vendor/dashboard/sales', [VendorDashboardController::class, 'vendorSales']);
        Route::get('/vendor/dashboard/sales/{id}', [VendorDashboardController::class, 'showSoldItem']);
        Route::get('/vendor/reviews', [VendorController::class, 'offerReviews']);
        Route::get('/vendor/offers/{offer_id}/reviews', [VendorController::class, 'showOfferReviews']);
        Route::get('/my-branches', [BranchController::class, 'index']);      
        Route::post('/branches', [BranchController::class, 'store']);        
        Route::get('/branches/{id}', [BranchController::class, 'show']);     
        Route::put('/branches/{id}', [BranchController::class, 'update']);   
        Route::delete('/branches/{id}', [BranchController::class, 'destroy']); 
        Route::get('/branches/{id}/all-orders', [BranchController::class, 'allOrders']); 
        Route::get('/vendor/sustainability/metrics', [SustainabilityController::class, 'getVendorMetrics']);


    });
});



Route::middleware(['auth:sanctum', 'checkadmin'])->group(function () {
    Route::get('/dashboard/stats', [AdminDashboardController::class, 'getOverviewStats']);
    Route::get('/dashboard/earnings', [AdminDashboardController::class, 'getMonthlyEarningsChart']);
    Route::get('/dashboard/activity', [AdminDashboardController::class, 'getRecentActivity']);
    Route::get('/admin/customers', [AdminController::class, 'index']);
    Route::get('/admin/customers/{id}', [AdminController::class, 'show']);
    Route::get('/admin/all-orders', [AdminController::class, 'listAllOrders']);
    Route::get('/admin/sustainability/metrics', [SustainabilityController::class, 'getAdminMetrics']);


    Route::middleware(['checkadmin:super_admin,manager'])->group(function () {
        Route::put('/profile', [AdminController::class, 'updateProfile']);
        Route::put('/admin/change-password', [AdminController::class, 'changePassword']);
        Route::get('/admin/vendor/pending', [AdminController::class, 'pendingVendors']);  
        Route::get('/admin/vendor/{id}', [AdminController::class, 'showPendingDocs']); 
        Route::post('/admin/vendor/{id}/accept', [AdminController::class, 'accept']);  
        Route::post('/admin/vendor/{id}/reject', [AdminController::class, 'reject']);  
        Route::patch('/admin/users/{id}/block', [AdminController::class, 'blockUser']);
        Route::patch('/admin/users/{id}/unblock', [AdminController::class, 'unblockUser']);
        Route::get('/admin/reviews', [AdminController::class, 'listAllReviews']);
        Route::patch('/reviews/{id}/toggle-visibility', [AdminController::class, 'toggleVisibility']);

        Route::middleware(['checkadmin:super_admin'])->group(function () {
            Route::delete('/admin/customers/{id}', [AdminController::class, 'destroy']);
            Route::delete('/admin/offers/{id}', [AdminController::class, 'deleteOfferByAdmin']);
            Route::delete('/profile', [AdminController::class, 'deleteAccount']);
            Route::delete('/admin/vendor/{id}', [AdminController::class, 'destroyVendor']); 
            Route::delete('/admin/customers/{id}', [AdminController::class, 'delete']);
        });
    });
});









Route::middleware(['auth:sanctum', 'checkcustomer'])->group(function () {
    Route::put('/customer/profile', [CustomerController::class, 'update']);
    Route::put('/profile/change-password', [CustomerController::class, 'changePassword']);
    Route::delete('customer/delete-profile', [CustomerController::class, 'destroy']);
    Route::post('/orders', [OrderController::class, 'store']);  
    Route::post('/orders/calculate-fee', [OrderController::class, 'calculateFee']);
    Route::get('/my-orders', [OrderController::class, 'index']); 
    
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::get('/my-reviews', [ReviewController::class, 'myReviews']); 
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
    Route::post('/favorites/toggle', [FavoriteController::class, 'toggleFavorite']);
    Route::get('/favorites', [FavoriteController::class, 'getFavorites']);
    Route::get('/customer/sustainability/metrics', [SustainabilityController::class, 'getCustomerMetrics']);

});


Route::post('/stripe/webhook', [PaymentController::class, 'handleWebhook']);
Route::get('/payment/success', [PaymentController::class, 'paymentSuccess']);
Route::get('/payment/cancel', [PaymentController::class, 'paymentCancel']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-reset-code', [AuthController::class, 'verifyCode']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/vendors', [VendorController::class, 'index']);  
Route::get('/branches/nearby', [BranchController::class, 'nearby']); 
Route::get('/vendors/search', [VendorController::class, 'index']); 
Route::get('/vendor/{id}', [VendorController::class, 'show']); 
Route::get('/offers', [OfferController::class, 'index']);
Route::get('/offers/{id}', [OfferController::class, 'show']);
Route::get('/offers/{offer_id}/reviews', [ReviewController::class, 'index']);
Route::get('/branches/{id}/details', [BranchController::class, 'getBranchDetails']);