<?php

namespace App\Support;

use Illuminate\Support\Str;

class NewsletterMergeTags
{
    public const TAGS = [
        '{Name}',
        '{FirstName}',
        '{Email}',
        '{UnsubscribeURL}',
        '{WebsiteURL}',
    ];

    public function __construct(
        private readonly ?string $name,
        private readonly string $email,
        private readonly string $unsubscribeUrl,
    ) {}

    public function renderText(?string $value): string
    {
        return strtr((string) $value, $this->values());
    }

    public function renderHtml(?string $value): string
    {
        return strtr((string) $value, array_map(
            static fn (string $replacement): string => e($replacement),
            $this->values(),
        ));
    }

    /**
     * @return array<string, string>
     */
    private function values(): array
    {
        $name = trim((string) $this->name);
        $displayName = $name !== '' ? $name : 'there';
        $firstName = $name !== '' ? (string) Str::of($name)->squish()->before(' ') : 'there';

        return [
            '{Name}' => $displayName,
            '{FirstName}' => $firstName,
            '{Email}' => $this->email,
            '{UnsubscribeURL}' => $this->unsubscribeUrl,
            '{WebsiteURL}' => rtrim((string) config('app.url'), '/'),
        ];
    }
}
