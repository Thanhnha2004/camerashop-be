<?php

use App\Http\Controllers\Admin\MailController;
use App\Http\Controllers\Api\AuthController;
use App\Mail\VerifyMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sendEmail', function(){
    $message = 'Hello';
    Mail::to('DH52201132@student.stu.edu.vn')->send(new VerifyMail($message));
});

Route::get('/login/google', [AuthController::class, 'redirect']);
Route::get('/login/google/callback', [AuthController::class, 'googleAuthCallback']);