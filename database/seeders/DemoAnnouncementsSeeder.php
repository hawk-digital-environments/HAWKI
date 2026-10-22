<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Announcements\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the six demo announcements . It writes their markdown
 * content files under `resources/announcements/{view}/{de_DE,en_US}.md`, 
 * so no demo content ships with the repository.
 *
 * Deliberately NOT called by {@see DatabaseSeeder}: the scenarios include a
 * forced policy dialog and must never reach a real installation implicitly.
 * Run explicitly:
 *
 * php artisan db:seed --class=DemoAnnouncementsSeeder --force
 *
 * Idempotent — existing database rows and content files are never touched.
 * Dates are relative ("a day ago", "in six months", …) so the scenarios never
 * go stale.
 */
class DemoAnnouncementsSeeder extends Seeder
{
    public function run(): void
    {
        $this->writeContentFiles();

        $targetUsers = User::query()->orderBy('id')->limit(2)->pluck('id')->all();

        if ([] === $targetUsers) {
            $this->command?->warn(
                'No users exist yet — the targeted scenario (only-you) is seeded without '
                . 'targets and is invisible to everyone. Re-run the seeder once users exist.',
            );
        }

        $rows = [];

        foreach ($this->announcements($targetUsers) as $attributes) {
            $announcement = Announcement::query()->firstOrCreate(
                ['view' => $attributes['view']],
                $attributes,
            );

            $rows[] = [
                'view' => $announcement->view,
                'id' => (string) $announcement->id,
                'type' => $announcement->type,
                'targeting' => $announcement->is_global
                    ? 'global'
                    : ([] === $announcement->target_users ? 'nobody' : implode(', ', $announcement->target_users ?? [])),
                'anchor' => $announcement->anchor ?? '—',
            ];
        }

        $this->command?->table(
            ['View', 'ID', 'Type', 'Targeting', 'Anchor'],
            $rows,
        );
    }

    /**
     * Writes the markdown content file per view and locale. Existing files are
     * never overwritten — same policy as `announcement:make`.
     */
    private function writeContentFiles(): void
    {
        foreach ($this->content() as $view => $locales) {
            $folder = resource_path("announcements/{$view}");

            if (!is_dir($folder)) {
                mkdir($folder, 0o755, true);
            }

            foreach ($locales as $locale => $markdown) {
                $file = "{$folder}/{$locale}.md";

                if (!is_file($file)) {
                    file_put_contents($file, $markdown);
                }
            }
        }
    }

    /**
     * The six scenarios as database rows. Dates are relative so the seeder can
     * be re-run at any time without the scheduled/expired scenarios flipping
     * their state.
     *
     * @param list<int> $targetUsers ids for scenario D (targeted)
     *
     * @return list<array<string, mixed>>
     */
    private function announcements(array $targetUsers): array
    {
        return [
            // A) Global news — dismissable dialog on load, listed on the news page.
            [
                'title' => 'Maintenance Window',
                'view' => 'maintenance-window',
                'type' => 'news',
                'is_forced' => false,
                'is_global' => true,
                'target_users' => null,
                'anchor' => null,
                'starts_at' => now()->subDay(),
                'expires_at' => null,
            ],
            // B) Forced policy — unclosable dialog, decline offers logout.
            [
                'title' => 'New Usage Policy',
                'view' => 'new-usage-policy',
                'type' => 'policy',
                'is_forced' => true,
                'is_global' => true,
                'target_users' => null,
                'anchor' => null,
                'starts_at' => now()->subDay(),
                'expires_at' => null,
            ],
            // C) Anchored notice — pops up on the first file upload instead of on load.
            [
                'title' => 'First Upload Hint',
                'view' => 'my-first-upload',
                'type' => 'system',
                'is_forced' => true,
                'is_global' => true,
                'target_users' => null,
                'anchor' => 'FileUpload',
                'starts_at' => now()->subDay(),
                'expires_at' => null,
            ],
            // D) Targeted at the two lowest user ids — invisible to everyone else.
            [
                'title' => 'Only You',
                'view' => 'only-you',
                'type' => 'info',
                'is_forced' => false,
                'is_global' => false,
                'target_users' => $targetUsers,
                'anchor' => null,
                'starts_at' => now()->subDay(),
                'expires_at' => null,
            ],
            // E) Scheduled — invisible until it starts (six months from seeding).
            [
                'title' => 'Coming Soon',
                'view' => 'coming-soon',
                'type' => 'news',
                'is_forced' => false,
                'is_global' => true,
                'target_users' => null,
                'anchor' => null,
                'starts_at' => now()->addMonths(6),
                'expires_at' => null,
            ],
            // F) Expired — no dialog, only the history list on the news page.
            [
                'title' => 'Old News',
                'view' => 'old-news',
                'type' => 'news',
                'is_forced' => false,
                'is_global' => true,
                'target_users' => null,
                'anchor' => null,
                'starts_at' => now()->subYear(),
                'expires_at' => now()->subMonths(6),
            ],
        ];
    }

    /**
     * Markdown bodies per view and locale. The first heading doubles as the
     * display title; `[CONFIRM](…)` / `[DECLINE](…)` tags customize the dialog
     * buttons (B exercises both, C only confirm, A/D/E/F the defaults).
     *
     * @return array<string, array<string, string>>
     */
    private function content(): array
    {
        return [
            'maintenance-window' => [
                'en_US' => <<<'MD'
                    # Planned Maintenance Window

                    HAWKI will be unavailable on **Sunday, 02:00–04:00 (CET)** while the model backend is upgraded.

                    - Running chats pause automatically and resume afterwards.
                    - Uploaded files are not affected.

                    You can simply dismiss this notice — it also appears on the news page.
                    MD,
                'de_DE' => <<<'MD'
                    # Geplantes Wartungsfenster

                    HAWKI steht am **Sonntag von 02:00–04:00 Uhr (MEZ)** nicht zur Verfügung, während das Modell-Backend aktualisiert wird.

                    - Laufende Chats werden automatisch pausiert und danach fortgesetzt.
                    - Hochgeladene Dateien sind nicht betroffen.

                    Diese Mitteilung kann einfach geschlossen werden — sie erscheint zusätzlich auf der Aktuelles-Seite.
                    MD,
            ],
            'new-usage-policy' => [
                'en_US' => <<<'MD'
                    # Updated Usage Policy

                    The usage policy has been revised. Key changes:

                    - Uploaded files are now deleted after **three** months instead of six.
                    - Automated grading of examinations with AI remains prohibited.
                    - Entering personal data continues to be strictly prohibited.

                    The updated policy applies immediately and must be accepted to continue using HAWKI.

                    [CONFIRM](I have read and accept the updated policy)
                    [DECLINE](Decline and log out)
                    MD,
                'de_DE' => <<<'MD'
                    # Aktualisierte Nutzungsordnung

                    Die Nutzungsordnung wurde überarbeitet. Wesentliche Änderungen:

                    - Hochgeladene Dateien werden künftig nach **drei** statt sechs Monaten gelöscht.
                    - Die automatisierte Korrektur von Prüfungsleistungen mit KI bleibt untersagt.
                    - Die Eingabe personenbezogener Daten bleibt strengstens untersagt.

                    Die aktualisierte Nutzungsordnung gilt sofort und muss akzeptiert werden, um HAWKI weiter nutzen zu können.

                    [CONFIRM](Ich habe die aktualisierte Nutzungsordnung gelesen und akzeptiere sie)
                    [DECLINE](Ablehnen und abmelden)
                    MD,
            ],
            'my-first-upload' => [
                'en_US' => <<<'MD'
                    ### Tips for your first file upload

                    - Supported formats: PDF, text, Office documents and images.
                    - Files are processed only for this chat and deleted automatically.
                    - You remain responsible for the content you upload.

                    [CONFIRM](Got it)
                    MD,
                'de_DE' => <<<'MD'
                    ### Tipps für Ihren ersten Datei-Upload

                    - Unterstützte Formate: PDF, Text, Office-Dokumente und Bilder.
                    - Dateien werden ausschließlich für diesen Chat verarbeitet und automatisch gelöscht.
                    - Sie bleiben für die von Ihnen hochgeladenen Inhalte verantwortlich.

                    [CONFIRM](Verstanden)
                    MD,
            ],
            'only-you' => [
                'en_US' => <<<'MD'
                    # Personal Notice

                    This notice is addressed directly to your account — other users cannot see it.

                    Use it to verify audience targeting: log in with a different account and this
                    announcement must not appear anywhere.
                    MD,
                'de_DE' => <<<'MD'
                    # Persönliche Mitteilung

                    Diese Mitteilung richtet sich direkt an Ihr Konto — andere Nutzer*innen können sie nicht sehen.

                    Nutzen Sie sie zur Prüfung des Targetings: Melden Sie sich mit einem anderen Konto an,
                    diese Ankündigung darf dort nirgendwo erscheinen.
                    MD,
            ],
            'coming-soon' => [
                'en_US' => <<<'MD'
                    # New Feature Coming Soon

                    From its start date, HAWKI introduces **saved prompts**: reusable prompt templates
                    for recurring tasks.

                    This announcement is scheduled — it stays invisible until then and appears
                    automatically on that day.
                    MD,
                'de_DE' => <<<'MD'
                    # Neues Feature in Vorbereitung

                    Ab dem Starttermin führt HAWKI **gespeicherte Prompts** ein: wiederverwendbare
                    Prompt-Vorlagen für wiederkehrende Aufgaben.

                    Diese Ankündigung ist terminiert — sie bleibt bis dahin unsichtbar und erscheint
                    an diesem Tag automatisch.
                    MD,
            ],
            'old-news' => [
                'en_US' => <<<'MD'
                    # Winter Term Recap

                    Looking back at the winter term: HAWKI was used in over 40 courses, and file
                    uploads became the most-requested feature.

                    This is an expired announcement — it no longer pops up and only lives on the
                    news page history.
                    MD,
                'de_DE' => <<<'MD'
                    # Rückblick aufs Wintersemester

                    Ein Rückblick auf das Wintersemester: HAWKI wurde in über 40 Kursen genutzt, und
                    der Datei-Upload war das am häufigsten gewünschte Feature.

                    Dies ist eine abgelaufene Ankündigung — sie erscheint nicht mehr als Dialog und
                    ist nur noch in der Verlaufsliste der Aktuelles-Seite sichtbar.
                    MD,
            ],
        ];
    }
}
