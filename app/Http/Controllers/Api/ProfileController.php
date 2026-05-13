<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(new EmployeeResource($request->user()));
    }

    // PATCH /api/employee/profile
    // Only allows updating safe personal fields
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'phone'     => 'sometimes|string|max:30',
            'emergency' => 'sometimes|string|max:30',
            'pin'       => 'sometimes|digits:4',
        ]);

        $emp  = $request->user();
        $data = [];

        if ($request->has('phone'))     $data['phone']             = $request->phone;
        if ($request->has('emergency')) $data['emergency_contact'] = $request->emergency;
        if ($request->has('pin'))       $data['pin']               = Hash::make($request->pin);

        $emp->update($data);

        return response()->json(new EmployeeResource($emp->fresh()));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password'      => ['required', 'string'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $emp = $request->user();

        // Verify current password
        if (!Hash::check($data['current_password'], $emp->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $emp->update(['password' => Hash::make($data['password'])]);

        // Revoke all other tokens (force re-login on other devices)
        $emp->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password updated successfully.']);
    }

    // ── POST /api/employee/profile/avatar ─────────────────────────────────────
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp,gif',
                'max:5120', // 5 MB in kilobytes
            ],
        ]);

        $emp  = $request->user();
        $file = $request->file('avatar');

        // ── Delete old avatar file (if stored locally) ────────────────────
        if (
            $emp->getRawOriginal('avatar_url') &&
            !str_starts_with($emp->getRawOriginal('avatar_url'), 'http')
        ) {
            Storage::disk('public')->delete($emp->getRawOriginal('avatar_url'));
        }

        // ── Store new file ────────────────────────────────────────────────
        // Stored as: avatars/{employee_id}/{random}.{ext}
        // Public disk → accessible at /storage/avatars/...
        $extension = $file->getClientOriginalExtension();
        $filename  = \Illuminate\Support\Str::uuid() . '.' . strtolower($extension);
        $path      = $file->storeAs(
            "avatars/{$emp->id}",
            $filename,
            'public'
        );

        $emp->update(['avatar_url' => $path]);

        return response()->json([
            // Returns the full public URL → frontend avatarUrl field
            'avatarUrl' => Storage::url($path),
        ]);
    }

    // ── DELETE /api/employee/profile/avatar ───────────────────────────────────
    public function removeAvatar(Request $request): JsonResponse
    {
        $emp     = $request->user();
        $rawPath = $emp->getRawOriginal('avatar_url');

        if ($rawPath && !str_starts_with($rawPath, 'http')) {
            Storage::disk('public')->delete($rawPath);
        }

        $emp->update(['avatar_url' => null]);

        return response()->json(['message' => 'Avatar removed.']);
    }
}
