<?php

namespace App\Http\Controllers;

use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Tampilkan formulir edit profil & unggah foto pengguna
     */
    public function edit()
    {
        $user = Auth::user()->load('role.parent');
        return view('profile.edit', compact('user'));
    }

    /**
     * Simpan perubahan profil & unggahan foto avatar
     */
    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:25'],
            'department' => ['nullable', 'string', 'max:100'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'keahlian' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:2048'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email ini sudah digunakan oleh akun lain.',
            'avatar.image' => 'File foto profil harus berupa gambar.',
            'avatar.mimes' => 'Format foto harus berupa JPG, PNG, WEBP, atau GIF.',
            'avatar.max' => 'Ukuran foto maksimal adalah 2MB.',
            'password.min' => 'Password minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? $user->phone;
        $user->department = $validated['department'] ?? $user->department;
        $user->jabatan = $validated['jabatan'] ?? $user->jabatan;
        $user->keahlian = $validated['keahlian'] ?? $user->keahlian;

        // Opsi hapus foto jika checkbox remove_avatar dicentang
        if ($request->boolean('remove_avatar')) {
            Uploads::delete($user->avatar_path);
            $user->avatar_path = null;
        }

        // Upload foto baru
        if ($request->hasFile('avatar')) {
            Uploads::delete($user->avatar_path);
            $user->avatar_path = Uploads::store($request->file('avatar'), 'avatars');
        }

        // Update password jika diisi
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('profile.edit')->with('success', 'Profil dan foto akun berhasil diperbarui!');
    }

    /**
     * Sajikan file foto avatar pengguna secara langsung dan aman
     */
    public function avatar(\App\Models\User $user)
    {
        if (! Uploads::exists($user->avatar_path)) {
            abort(404);
        }

        return Uploads::inline($user->avatar_path, basename($user->avatar_path));
    }
}
