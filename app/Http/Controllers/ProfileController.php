<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use App\Models\User;

class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        return view('profile.show', compact('user'));
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        //Para que funcione lo de user de la linea 38
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 1. Verificar contraseña actual
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.']);
        }

        // 2. Que la nueva no sea otra vez la de arranque: si vuelve a ser su
        // matrícula, su RFC o su usuario, sigue siendo adivinable por cualquiera.
        if ($user->recibeAvisoDeContrasena() && $user->esContrasenaPorDefecto($request->new_password)) {
            return back()->withErrors([
                'new_password' => 'No uses tu matrícula, tu RFC ni tu usuario como contraseña. Elige una distinta.',
            ]);
        }

        // 3. Actualizar
        $user->password = Hash::make($request->new_password);

        // Ahora Intelephense ya sabe que $user es un Modelo y tiene save()
        $user->save();

        // Ya no hace falta recordarle que la cambie: se apaga el aviso del menú.
        $request->session()->forget('password_por_defecto');

        return back()->with('success', '¡Contraseña actualizada correctamente!');
    }

    /**
     * Actualiza la información del perfil (Foto).
     */
    public function update(Request $request)
    {
        $request->validate([
            // Validamos imagen. Max 10MB (10240 KB)
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($request->hasFile('photo')) {
            // 1. La foto anterior se borra hasta que la nueva quedó guardada: antes se
            //    borraba primero, y si la nueva fallaba el usuario se quedaba sin foto
            //    y con la base apuntando a un archivo que ya no existía.
            $fotoAnterior = $user->profile_photo_path;

            // 2. Procesar imagen (CÓDIGO VERSIÓN 2)
            $file = $request->file('photo');
            $filename = uniqid() . '.webp';

            try {
                // En V2 se usa 'make'
                $img = Image::make($file);

                // Redimensionar a 400x400 (fit recorta y centra automáticamente)
                $img->fit(400, 400);

                // Convertir a WebP con 80% calidad
                $img->encode('webp', 80);

                // 3. Guardar en Storage
                $path = 'profile-photos/' . $filename;
                Storage::disk('public')->put($path, (string) $img);

                // 4. Actualizar Base de Datos
                $user->profile_photo_path = $path;
                $user->save();

                if ($fotoAnterior && $fotoAnterior !== $path) {
                    Storage::disk('public')->delete($fotoAnterior);
                }
            } catch (\Exception $e) {
                return back()->withErrors(['photo' => 'Error al procesar imagen: ' . $e->getMessage()]);
            }
        }

        return back()->with('success', '¡Foto actualizada correctamente!');
    }

    /**
     * Elimina la foto de perfil actual y vuelve a las iniciales.
     */
    public function deletePhoto()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->profile_photo_path) {
            // 1. Borrar el archivo físico del storage
            Storage::disk('public')->delete($user->profile_photo_path);

            // 2. Poner el campo en null en la BD
            $user->profile_photo_path = null;
            $user->save();

            return back()->with('success', 'Foto de perfil eliminada. Se usarán tus iniciales.');
        }

        return back(); // Si no tenía foto, solo regresamos.
    }
}
