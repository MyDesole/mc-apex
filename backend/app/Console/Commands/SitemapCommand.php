<?php

namespace App\Console\Commands;

use App\Domains\Clan\Models\Clan;
use App\Domains\News\Models\News;
use App\Domains\Project\Models\ChangelogEntry;
use Illuminate\Console\Command;

/**
 * Карта сайта для поисковиков: открытые разделы, новости, обновления
 * и кланы. Пишется в сборку фронтенда (frontend/dist/sitemap.xml) — её
 * отдаёт nginx как обычный файл. Запускается при выкатке и раз в сутки.
 * Профили игроков закрыты входом — в карту не попадают.
 */
class SitemapCommand extends Command
{
    protected $signature = 'sitemap:generate {--path= : Куда записать (по умолчанию frontend/dist/sitemap.xml)}';

    protected $description = 'Собрать sitemap.xml для поисковиков';

    /** Открытые разделы: путь => [как часто меняется, приоритет]. */
    private const PAGES = [
        '/' => ['daily', '1.0'],
        '/news' => ['daily', '0.9'],
        '/clans' => ['daily', '0.8'],
        '/tournaments' => ['daily', '0.8'],
        '/respects' => ['daily', '0.7'],
        '/shop' => ['weekly', '0.7'],
        '/cases' => ['weekly', '0.6'],
        '/auction' => ['hourly', '0.6'],
        '/changelog' => ['weekly', '0.6'],
        '/stats' => ['daily', '0.5'],
    ];

    public function handle(): int
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $path = $this->option('path') ?: base_path('../frontend/dist/sitemap.xml');

        if (! is_dir(dirname($path))) {
            $this->warn('Нет папки ' . dirname($path) . ' — фронтенд не собран, пропускаю.');

            return self::SUCCESS;
        }

        $urls = [];

        foreach (self::PAGES as $page => [$freq, $priority]) {
            $urls[] = [$base . $page, null, $freq, $priority];
        }

        News::query()
            ->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->latest('published_at')
            ->limit(500)
            ->get(['id', 'updated_at'])
            ->each(function (News $news) use (&$urls, $base) {
                $urls[] = [$base . '/news/' . $news->id, $news->updated_at, 'monthly', '0.7'];
            });

        ChangelogEntry::query()
            ->where('is_published', true)
            ->latest('published_at')
            ->limit(300)
            ->get(['slug', 'updated_at'])
            ->each(function (ChangelogEntry $entry) use (&$urls, $base) {
                $urls[] = [$base . '/changelog/' . $entry->slug, $entry->updated_at, 'monthly', '0.5'];
            });

        Clan::query()
            ->where('is_banned', false)
            ->limit(1000)
            ->get(['name', 'updated_at'])
            ->each(function (Clan $clan) use (&$urls, $base) {
                $urls[] = [$base . '/clan/' . rawurlencode($clan->name), $clan->updated_at, 'weekly', '0.5'];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as [$loc, $updated, $freq, $priority]) {
            $xml .= '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>'
                . ($updated ? '<lastmod>' . $updated->toAtomString() . '</lastmod>' : '')
                . "<changefreq>{$freq}</changefreq><priority>{$priority}</priority></url>\n";
        }

        $xml .= "</urlset>\n";

        file_put_contents($path, $xml);
        $this->info('sitemap: ' . count($urls) . ' адресов → ' . $path);

        return self::SUCCESS;
    }
}
