<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmailMailable;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * POST /register
     */
    public function register(Request $request)
    {
        // 1. Validation rules
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // 2. Tạo User mới
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // 3. Tạo verification URL
        $verificationUrl = $this->generateVerificationUrl($user);

        // 4. Gửi email xác thực
        try {
            Mail::to($user->email)->send(new VerifyEmailMailable($verificationUrl, $user->name));
        } catch (Exception $e) {
            // Log lỗi nhưng vẫn cho phép đăng ký thành công
            \Log::error('Failed to send verification email: ' . $e->getMessage());
        }

        // 5. Tạo Sanctum Token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng ký thành công! Vui lòng kiểm tra email để xác thực tài khoản.',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
            'email_verified' => false,
        ], 201);
    }

    /**
     * GET /email/verify/{id}/{hash}
     * Route xác thực email
     */
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        // Kiểm tra hash có hợp lệ không
        if (!hash_equals((string) $hash, sha1($user->email))) {
            return response()->json([
                'message' => 'Link xác thực không hợp lệ.'
            ], 403);
        }

        // Kiểm tra signature và expiration
        if (!$request->hasValidSignature()) {
            return response()->json([
                'message' => 'Link xác thực đã hết hạn hoặc không hợp lệ.'
            ], 403);
        }

        // Kiểm tra đã verify chưa
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email đã được xác thực trước đó.'
            ], 200);
        }

        // Xác thực email
        $user->markEmailAsVerified();

        return response()->json([
            'message' => 'Xác thực email thành công!',
            'email_verified' => true,
        ], 200);
    }

    /**
     * POST /email/resend
     * Gửi lại email xác thực
     */
    public function resendVerificationEmail(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email đã được xác thực.'
            ], 200);
        }

        $verificationUrl = $this->generateVerificationUrl($user);

        try {
            Mail::to($user->email)->send(new VerifyEmailMailable($verificationUrl, $user->name));
            
            return response()->json([
                'message' => 'Email xác thực đã được gửi lại!'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Không thể gửi email. Vui lòng thử lại sau.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo signed URL cho việc xác thực email
     */
    protected function generateVerificationUrl(User $user)
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60), // Link hết hạn sau 60 phút
            [
                'id' => $user->id,
                'hash' => sha1($user->email)
            ]
        );
    }

    /**
     * POST /login
     */
    public function login(Request $request)
    {
        // 1. Validation rules
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // 2. Xác thực (Attempt Authentication)
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không hợp lệ.'],
            ]);
        }

        $user = Auth::user();

        // Kiểm tra email đã được xác thực chưa
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Vui lòng xác thực email trước khi đăng nhập.',
                'email_verified' => false,
            ], 403);
        }

        // Xóa các token cũ (tùy chọn)
        $user->tokens()->delete();

        // 3. Tạo Sanctum Token mới
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công!',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
            'email_verified' => true,
        ]);
    }

    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * POST /google/callback
     */
    public function googleAuthCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if (!$user) {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'password' => Hash::make(uniqid()),
                    'email_verified_at' => now(), // Google đã verify email
                ]);
            } else {
                if (!$user->google_id) {
                    $user->google_id = $googleUser->getId();
                }
                // Đánh dấu email đã verify vì Google đã verify
                if (!$user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }
                $user->save();
            }

            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Đăng nhập Google thành công',
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'email_verified' => true,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Lỗi Google OAuth',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công.'
        ], 200);
    }
}