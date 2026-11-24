<?php

namespace App\Domains\Auth\Services;

use Illuminate\Validation\ValidationException;

class RegistrationEmailPolicy
{
    private ?array $blockedDomains = null;

    public function assertAllowed(string $email): void
    {
        $separator = strrpos($email, '@');
        if ($separator === false) {
            return;
        }
        $domain = strtolower(rtrim(trim(substr($email, $separator + 1)), '.'));
        $blocked = $this->domains();
        while (str_contains($domain, '.')) {
            if (isset($blocked[$domain])) {
                throw ValidationException::withMessages([
                    'email' => 'Регистрация с временной почтой запрещена. Используйте постоянный почтовый адрес.',
                ]);
            }
            $domain = substr($domain, strpos($domain, '.') + 1);
        }
    }

    private function domains(): array
    {
        if ($this->blockedDomains === null) {
            $upstream = file(resource_path('security/disposable-email-domains.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $this->blockedDomains = array_fill_keys(array_merge($upstream, config('registration.blocked_email_domains', [])), true);
        }

        return $this->blockedDomains;
    }
}
