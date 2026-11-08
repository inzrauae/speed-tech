<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\FinderApiController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RepairController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SparePartController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------- Storefront
Route::get('/', HomeController::class)->name('home');
Route::get('/shop', ShopController::class)->name('shop');
Route::get('/spare-parts', SparePartController::class)->name('spare-parts');
Route::view('/about', 'about')->name('about');
Route::view('/contact', 'contact')->name('contact');
Route::get('/repairs', [RepairController::class, 'index'])->name('repairs');
Route::get('/repairs/{service}', [RepairController::class, 'show'])->name('repairs.show');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');

Route::get('/cart', [CartController::class, 'show'])->name('cart');
Route::post('/cart/items', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/items/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/items/{product}', [CartController::class, 'remove'])->name('cart.remove');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:6,1')->name('checkout.store');
Route::get('/orders/{order:number}', [CheckoutController::class, 'confirmation'])->middleware('signed')->name('orders.confirmation');

Route::post('/enquiries', [EnquiryController::class, 'store'])->middleware('throttle:8,1')->name('enquiries.store');

// One-time browser installer (only active while INSTALL_TOKEN is set in .env)
Route::get('/install/{token}', InstallController::class)->middleware('throttle:5,1')->name('install');

// Cascading laptop finder (Brand → Model → Year → Part)
Route::prefix('api/finder')->name('api.finder.')->group(function () {
    Route::get('brands', [FinderApiController::class, 'brands'])->name('brands');
    Route::get('models', [FinderApiController::class, 'models'])->name('models');
    Route::get('generations', [FinderApiController::class, 'generations'])->name('generations');
    Route::get('parts', [FinderApiController::class, 'parts'])->name('parts');
});

// ---------------------------------------------------------------- Admin
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:5,1');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');
        Route::get('account', [Admin\AuthController::class, 'account'])->name('account');
        Route::put('account', [Admin\AuthController::class, 'updatePassword'])->name('account.update');

        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::resource('products', Admin\ProductController::class)->except('show');

        Route::get('catalog/{type}', [Admin\TaxonomyController::class, 'index'])->name('catalog.index');
        Route::post('catalog/{type}', [Admin\TaxonomyController::class, 'store'])->name('catalog.store');
        Route::put('catalog/{type}/{id}', [Admin\TaxonomyController::class, 'update'])->name('catalog.update');
        Route::delete('catalog/{type}/{id}', [Admin\TaxonomyController::class, 'destroy'])->name('catalog.destroy');

        Route::resource('models', Admin\LaptopModelController::class)->except('show')->parameters(['models' => 'laptopModel']);
        Route::post('models/{laptopModel}/generations', [Admin\LaptopModelController::class, 'storeGeneration'])->name('generations.store');
        Route::put('generations/{generation}', [Admin\LaptopModelController::class, 'updateGeneration'])->name('generations.update');
        Route::delete('generations/{generation}', [Admin\LaptopModelController::class, 'destroyGeneration'])->name('generations.destroy');

        Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}', [Admin\OrderController::class, 'update'])->name('orders.update');

        Route::get('enquiries', [Admin\EnquiryController::class, 'index'])->name('enquiries.index');
        Route::get('enquiries/{enquiry}', [Admin\EnquiryController::class, 'show'])->name('enquiries.show');
        Route::patch('enquiries/{enquiry}', [Admin\EnquiryController::class, 'update'])->name('enquiries.update');
    });
});
