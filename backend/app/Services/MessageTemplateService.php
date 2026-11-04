<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\MessageTemplate;
use App\Models\School;

/**
 * The messages the system sends itself (admission fees, invoice notice, login
 * details). Their wording lives in message_templates so a school can edit it;
 * config/message_templates.php holds the defaults every school starts from.
 */
class MessageTemplateService
{
    public function supportedLocales(): array
    {
        return array_keys(config('app.supported_locales'));
    }

    /** Creates any system template the school does not have yet (never overwrites an edited one). */
    public function ensureDefaults(School $school): void
    {
        foreach (config('message_templates.events') as $event => $definition) {
            foreach ($this->supportedLocales() as $locale) {
                $body = $definition['bodies'][$locale] ?? null;

                if ($body === null) {
                    continue;
                }

                MessageTemplate::withoutGlobalScopes()->withTrashed()->firstOrCreate(
                    ['school_id' => $school->id, 'event' => $event, 'locale' => $locale],
                    [
                        'name' => $definition['name'].' ('.config('app.supported_locales')[$locale].')',
                        'category' => $definition['category'],
                        'body' => $body,
                    ],
                );
            }
        }
    }

    /** The wording to use: the school's edited template, or the built-in default. */
    public function body(School $school, string $event, string $locale): string
    {
        $locale = in_array($locale, $this->supportedLocales(), true) ? $locale : config('app.locale');

        $row = MessageTemplate::withoutGlobalScopes()
            ->where('school_id', $school->id)->where('event', $event)->where('locale', $locale)
            ->first();

        return $row?->body
            ?? config("message_templates.events.$event.bodies.$locale")
            ?? config("message_templates.events.$event.bodies.en");
    }

    /**
     * @param  array<string, scalar|null>  $data
     */
    public function render(School $school, string $event, string $locale, array $data): string
    {
        $text = preg_replace_callback('/\{(\w+)\}/', function ($m) use ($data) {
            return array_key_exists($m[1], $data) ? (string) $data[$m[1]] : $m[0];
        }, $this->body($school, $event, $locale));

        // An empty token (no due date, no bank account) shouldn't leave doubled spaces or a dangling ": ."
        return trim(preg_replace(['/[ \t]{2,}/', '/\s+([.,])/', '/([.,])\s*\1+/'], [' ', '$1', '$1'], $text));
    }

    /** Which language to write to this guardian in: theirs, else the school's message language, else the app's. */
    public function localeFor(School $school, ?Guardian $guardian): string
    {
        $candidates = [$guardian?->locale, $school->settings['message_locale'] ?? null, config('app.locale')];

        foreach ($candidates as $locale) {
            if ($locale && in_array($locale, $this->supportedLocales(), true)) {
                return $locale;
            }
        }

        return 'en';
    }
}
