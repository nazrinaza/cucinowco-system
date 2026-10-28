<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DocumentEmailRecipients
{
    /** @return list<string> */
    public function parse(?string $input, ?string $fallback = null): array
    {
        $value = filled($input) ? $input : $fallback;
        $recipients = collect(preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages(['recipients' => 'Enter at least one recipient email address.']);
        }

        if ($recipients->count() > 10) {
            throw ValidationException::withMessages(['recipients' => 'You can send to a maximum of 10 recipients at one time.']);
        }

        foreach ($recipients as $email) {
            $validator = Validator::make(['email' => $email], ['email' => ['required', 'email:rfc', 'max:160']]);
            if ($validator->fails()) {
                throw ValidationException::withMessages(['recipients' => "The email address {$email} is not valid."]);
            }
        }

        return $recipients->all();
    }
}
