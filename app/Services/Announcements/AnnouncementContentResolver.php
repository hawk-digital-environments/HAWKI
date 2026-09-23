<?php
declare(strict_types=1);


namespace App\Services\Announcements;


use App\Models\Announcements\Announcement;
use App\Services\Announcements\Values\AnnouncementContent;
use App\Services\Translation\LocaleService;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Foundation\Application;

/**
 * Loads the markdown body of an announcement from
 * `resources/announcements/{view}/{locale}.md`.
 *
 * Announcement content lives in files, not in the database, so "which text does this
 * announcement have right now" is a question with a locale in it. The current locale wins; the
 * default locale is the fallback. When neither file exists the announcement has no content, and
 * callers decide what that means — the history list renders it as empty, the registration policy
 * refuses to let anybody consent to nothing.
 */
#[Singleton]
readonly class AnnouncementContentResolver
{
    public function __construct(
        private Application   $application,
        private LocaleService $localeService
    )
    {
    }

    /**
     * Returns the content for the current locale, falling back to the default locale, or null
     * when the announcement has no content file for either.
     */
    public function resolve(Announcement $announcement, ?string $requestedLocale = null): ?AnnouncementContent
    {
        foreach ($this->candidateLocales($requestedLocale) as $locale) {
            $content = $announcement->content[$locale] ?? null;
            if (is_string($content) && trim($content) !== '') return new AnnouncementContent($locale, $content);
            $file = $this->application->resourcePath("announcements/$announcement->view/$locale.md");

            if (is_file($file)) {
                return new AnnouncementContent($locale, (string)file_get_contents($file));
            }
        }

        return null;
    }

    /**
     * Returns the hand-written list teaser (the `excerpt` column, keyed by locale) with the same
     * locale fallback as the content, or null when none is set — the frontend then derives one
     * from the body.
     */
    public function resolveExcerpt(Announcement $announcement, ?string $requestedLocale = null): ?string
    {
        $excerpts = $announcement->excerpt ?? [];

        foreach ($this->candidateLocales($requestedLocale) as $locale) {
            $excerpt = trim((string)($excerpts[$locale] ?? ''));

            if ($excerpt !== '') {
                return $excerpt;
            }
        }

        return null;
    }

    /**
     * The requested (or current) locale first, then the default locale.
     *
     * @return array<int, string>
     */
    private function candidateLocales(?string $requestedLocale): array
    {
        $requested = $requestedLocale === null
            ? $this->localeService->getCurrentLocale()->lang
            : $this->localeService->getLocale($requestedLocale)?->lang;

        return array_values(array_unique(array_filter([
            $requested,
            $this->localeService->getDefaultLocale()->lang,
        ])));
    }
}
