<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AccessCodeGenerator
{
    public function generate(): string
    {
        return str_pad(
            (string) random_int(0, 999999),
            6,
            '0',
            STR_PAD_LEFT,
        );
    }

    public function generateUnique(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = $this->generate();
            $alreadyExists = User::query()
                ->get(['access_code'])
                ->contains(
                    fn (User $user): bool => Hash::check($code, $user->access_code),
                );

            if (! $alreadyExists) {
                return $code;
            }
        }

        throw new RuntimeException('Não foi possível gerar um código de acesso único.');
    }
}
