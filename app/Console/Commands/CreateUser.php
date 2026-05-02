<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateUser extends Command
{
    protected $signature = 'user:create
                            {--name= : Nombre del usuario}
                            {--email= : Correo del usuario}
                            {--password= : Contraseña (si se omite se pedirá interactivamente)}
                            {--admin : Crear como administrador (acceso al panel /admin)}';

    protected $description = 'Crear un nuevo usuario (registro cerrado, solo admin)';

    public function handle(): int
    {
        $name = $this->option('name') ?: text(
            label: 'Nombre',
            required: true,
        );

        $email = $this->option('email') ?: text(
            label: 'Correo',
            required: true,
        );

        $plain = $this->option('password') ?: password(
            label: 'Contraseña',
            required: true,
        );

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $plain,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plain),
            'email_verified_at' => now(),
            'is_admin' => (bool) $this->option('admin'),
        ]);

        $role = $user->is_admin ? ' [ADMIN]' : '';
        $this->info("Usuario creado: {$user->email} (id: {$user->id}){$role}");
        return self::SUCCESS;
    }
}
