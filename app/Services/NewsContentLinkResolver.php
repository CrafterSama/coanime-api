<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\HiddenSeeker;
use App\Models\Magazine;
use App\Models\People;
use App\Models\Title;
use App\Models\TitleType;
use App\Support\NewsContentCleanup;
use App\Support\TitleCatalog;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class NewsContentLinkResolver
{
    private const USER_ID = 1;

    /** @var list<int> */
    private array $titleIds = [];

    public function __construct(private readonly NewsEntityFinder $finder) {}

    /**
     * @return array{html: string, title_ids: list<int>}
     */
    public function resolve(string $html): array
    {
        $this->titleIds = [];
        $html = NewsContentCleanup::stripNoise($html);
        if (! str_contains($html, '<a ')) {
            return ['html' => $html, 'title_ids' => []];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="UTF-8"><div id="coanime-root">'.$html.'</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $anchors = $dom->getElementsByTagName('a');
        /** @var list<DOMElement> $nodes */
        $nodes = [];
        foreach ($anchors as $anchor) {
            if ($anchor instanceof DOMElement) {
                $nodes[] = $anchor;
            }
        }

        foreach ($nodes as $anchor) {
            $href = trim((string) $anchor->getAttribute('href'));
            if ($href === '') {
                continue;
            }
            $text = trim($anchor->textContent ?? '');
            $resolved = $this->resolveHref($href, $text);
            if ($resolved !== null) {
                $anchor->setAttribute('href', $resolved);
            }
        }

        $root = $dom->getElementById('coanime-root');
        $out = '';
        if ($root !== null) {
            foreach ($root->childNodes as $child) {
                $out .= $dom->saveHTML($child);
            }
        }

        return [
            'html' => NewsContentCleanup::stripNoise($out !== '' ? $out : $html),
            'title_ids' => array_values(array_unique($this->titleIds)),
        ];
    }

    private function resolveHref(string $href, string $anchorText): ?string
    {
        $parts = parse_url($href);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        if ($host !== '' && ! str_contains($host, 'myanimelist.net')) {
            return null;
        }

        if (preg_match('~/(anime|manga)/(\d+)(?:/([^/?]*))?~i', $path, $m) === 1) {
            return $this->resolveTitle($m[1] === 'manga', (int) $m[2], $anchorText !== '' ? $anchorText : rawurldecode(str_replace('_', ' ', (string) ($m[3] ?? ''))));
        }
        if (preg_match('~/people/(\d+)(?:/([^/?]*))?~i', $path, $m) === 1) {
            return $this->resolvePerson($anchorText !== '' ? $anchorText : rawurldecode(str_replace('_', ' ', (string) ($m[2] ?? ''))));
        }
        if (preg_match('~/(?:magazine|magazines)/(\d+)(?:/([^/?]*))?~i', $path, $m) === 1) {
            return $this->resolveMagazine($anchorText !== '' ? $anchorText : rawurldecode(str_replace('_', ' ', (string) ($m[2] ?? ''))));
        }
        if (preg_match('~/(?:producer|producers|studio|studios|company|companies)/(\d+)(?:/([^/?]*))?~i', $path, $m) === 1) {
            return $this->resolveCompany($anchorText !== '' ? $anchorText : rawurldecode(str_replace('_', ' ', (string) ($m[2] ?? ''))));
        }
        if (preg_match('~/character/~i', $path) === 1) {
            return null;
        }

        // Non-MAL or unknown path: try entity by anchor text only when href looks like MAL.
        if (str_contains($host, 'myanimelist.net') && $anchorText !== '') {
            $person = $this->finder->findPerson($anchorText);
            if ($person !== null) {
                return $this->url('person', ['slug' => $person->slug]);
            }
            $company = $this->finder->findCompany($anchorText);
            if ($company !== null) {
                return $this->url('company', ['slug' => $company->slug]);
            }
            $magazine = $this->finder->findMagazine($anchorText);
            if ($magazine !== null) {
                return $this->url('magazine', ['slug' => $magazine->slug]);
            }
        }

        return null;
    }

    private function resolveTitle(bool $manga, int $malId, string $fallbackName): ?string
    {
        $cloud = null;
        try {
            $cloud = TitleCatalog::byMalId($malId, $manga);
        } catch (Throwable $e) {
            Log::warning('NewsContentLinkResolver: catalog title fetch failed', [
                'mal_id' => $malId,
                'error' => $e->getMessage(),
            ]);
        }

        $name = is_array($cloud) ? (string) ($cloud['title'] ?? $fallbackName) : $fallbackName;
        $name = NewsContentCleanup::stripPlain($name);
        if ($name === '') {
            return null;
        }

        $malType = is_array($cloud) ? strtolower((string) ($cloud['type'] ?? ($manga ? 'manga' : 'tv'))) : ($manga ? 'manga' : 'tv');
        $typeId = HiddenSeeker::getTypeById($malType);
        $typeSlug = $this->typeSlugFromMal($malType, $typeId);

        $title = $this->finder->findTitle($name, $typeId)
            ?? $this->finder->findTitle($name, null);

        if ($title === null && is_array($cloud)) {
            try {
                $title = $this->createTitleStub($cloud, $manga, $typeId);
            } catch (Throwable $e) {
                Log::warning('NewsContentLinkResolver: title stub failed', [
                    'name' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($title === null && $fallbackName !== '') {
            try {
                $title = $this->createMinimalTitle($name, $typeId);
            } catch (Throwable $e) {
                Log::warning('NewsContentLinkResolver: minimal title failed', [
                    'name' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($title === null) {
            return null;
        }

        $this->titleIds[] = (int) $title->id;
        $title->loadMissing('type');
        $slugType = $title->type?->slug ?: $typeSlug;

        return $this->url('title', ['type' => $slugType, 'slug' => $title->slug]);
    }

    private function resolvePerson(string $name): ?string
    {
        $name = NewsContentCleanup::stripPlain($name);
        if ($name === '') {
            return null;
        }
        $person = $this->finder->findPerson($name);
        if ($person === null) {
            try {
                $person = $this->createPersonStub($name);
            } catch (Throwable $e) {
                Log::warning('NewsContentLinkResolver: person stub failed', [
                    'name' => $name,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return $this->url('person', ['slug' => $person->slug]);
    }

    private function resolveCompany(string $name): ?string
    {
        $name = NewsContentCleanup::stripPlain($name);
        if ($name === '') {
            return null;
        }
        $company = $this->finder->findCompany($name);
        if ($company === null) {
            try {
                $company = $this->createCompanyStub($name);
            } catch (Throwable $e) {
                Log::warning('NewsContentLinkResolver: company stub failed', [
                    'name' => $name,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return $this->url('company', ['slug' => $company->slug]);
    }

    private function resolveMagazine(string $name): ?string
    {
        $name = NewsContentCleanup::stripPlain($name);
        if ($name === '') {
            return null;
        }
        $magazine = $this->finder->findMagazine($name);
        if ($magazine === null) {
            try {
                $magazine = $this->createMagazineStub($name);
            } catch (Throwable $e) {
                Log::warning('NewsContentLinkResolver: magazine stub failed', [
                    'name' => $name,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return $this->url('magazine', ['slug' => $magazine->slug]);
    }

    /**
     * @param  array<string, mixed>  $cloud
     */
    private function createTitleStub(array $cloud, bool $manga, int $typeId): Title
    {
        $name = NewsContentCleanup::stripPlain((string) ($cloud['title'] ?? ''));
        $existing = $this->finder->findTitle($name, $typeId) ?? $this->finder->findTitle($name, null);
        if ($existing !== null) {
            return $existing;
        }

        $title = new Title;
        $title->name = $name;
        $title->slug = $this->uniqueSlug(Title::class, Str::slug($name));
        $title->user_id = self::USER_ID;
        $title->just_year = 'false';
        $title->type_id = $typeId;
        $title->rating_id = 7;
        $title->episodies = TitleCatalog::episodes($cloud, $manga) ?? 0;
        $title->status = HiddenSeeker::getStatus(TitleCatalog::status($cloud)) ?? 'Finalizado';
        $title->other_titles = $this->collectOtherTitles($cloud) ?: '';

        $synopsis = $cloud['synopsis'] ?? null;
        if (is_string($synopsis) && $synopsis !== '') {
            $title->sinopsis = NewsContentCleanup::stripPlain($synopsis);
        }

        $from = TitleCatalog::dateFrom($cloud);
        if ($from !== null) {
            $title->broad_time = Carbon::parse($from)->format('Y-m-d');
        }
        $to = TitleCatalog::dateTo($cloud);
        if ($to !== null) {
            $title->broad_finish = Carbon::parse($to)->format('Y-m-d');
        }

        Title::withoutEvents(fn () => $title->save());

        return $title;
    }

    private function createMinimalTitle(string $name, int $typeId): Title
    {
        $existing = $this->finder->findTitle($name, $typeId) ?? $this->finder->findTitle($name, null);
        if ($existing !== null) {
            return $existing;
        }

        $title = new Title;
        $title->name = $name;
        $title->slug = $this->uniqueSlug(Title::class, Str::slug($name));
        $title->user_id = self::USER_ID;
        $title->just_year = 'false';
        $title->type_id = $typeId;
        $title->rating_id = 7;
        $title->episodies = 0;
        $title->status = 'Finalizado';
        $title->other_titles = '';
        Title::withoutEvents(fn () => $title->save());

        return $title;
    }

    private function createPersonStub(string $name): People
    {
        $existing = $this->finder->findPerson($name);
        if ($existing !== null) {
            return $existing;
        }

        return People::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug(People::class, Str::slug($name)),
            'japanese_name' => '',
            'areas_skills_hobbies' => '',
            'about' => '',
            'city_id' => 0,
            'country_code' => 'JPN',
            'falldown' => 'no',
            'user_id' => (string) self::USER_ID,
        ]);
    }

    private function createCompanyStub(string $name): Company
    {
        $existing = $this->finder->findCompany($name);
        if ($existing !== null) {
            return $existing;
        }

        return Company::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug(Company::class, Str::slug($name)),
            'website' => '',
            'country_code' => 'JPN',
            'about' => '',
            'user_id' => (string) self::USER_ID,
            'foundation_date' => null,
        ]);
    }

    private function createMagazineStub(string $name): Magazine
    {
        $existing = $this->finder->findMagazine($name);
        if ($existing !== null) {
            return $existing;
        }

        return Magazine::query()->create([
            'name' => Str::limit($name, 60, ''),
            'slug' => $this->uniqueSlug(Magazine::class, Str::slug($name), '-01'),
            'website' => '',
            'about' => '',
            'user_id' => (string) self::USER_ID,
            'country_code' => 'JPN',
            'type_id' => 1,
            'release_id' => 1,
            'foundation_date' => Carbon::now(),
        ]);
    }

    /**
     * @param  class-string<Title|People|Company|Magazine>  $model
     */
    private function uniqueSlug(string $model, string $base, string $suffixStyle = '1'): string
    {
        $base = $base !== '' ? $base : 'item';
        $slug = $base;
        $i = 1;
        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = $suffixStyle === '-01'
                ? $base.'-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)
                : $base.$i;
            $i++;
            if ($i > 50) {
                $slug = $base.'-'.Str::random(4);
                break;
            }
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $cloud
     */
    private function collectOtherTitles(array $cloud): string
    {
        $other = [];
        foreach ($cloud['titles'] ?? [] as $t) {
            if (! is_array($t)) {
                continue;
            }
            $type = strtolower((string) ($t['type'] ?? ''));
            $name = NewsContentCleanup::stripPlain((string) ($t['title'] ?? ''));
            if ($name === '') {
                continue;
            }
            if ($type === 'english') {
                $other[] = $name.' (Inglés)';
            }
            if ($type === 'japanese') {
                $other[] = $name.' (Japonés)';
            }
        }

        return implode(', ', array_values(array_unique($other)));
    }

    private function typeSlugFromMal(string $malType, int $typeId): string
    {
        $map = [
            'tv' => 'tv',
            'movie' => 'pelicula',
            'ova' => 'ova',
            'ona' => 'ona',
            'special' => 'especial',
            'tv special' => 'especial',
            'manga' => 'manga',
            'manhwa' => 'manhwa',
            'manhua' => 'manhua',
            'light novel' => 'novela-ligera',
            'novel' => 'novela',
        ];
        if (isset($map[$malType])) {
            return $map[$malType];
        }

        try {
            $type = TitleType::query()->find($typeId);
            if ($type !== null && is_string($type->slug) && $type->slug !== '') {
                return $type->slug;
            }
        } catch (Throwable) {
        }

        return 'tv';
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function url(string $kind, array $vars): string
    {
        $base = rtrim((string) config('services.news_links.frontend_base', config('app.frontend_url', 'https://coanime.net')), '/');
        $path = (string) config('services.news_links.'.$kind, '');
        foreach ($vars as $key => $value) {
            $path = str_replace('{'.$key.'}', (string) $value, $path);
        }

        return $base.$path;
    }
}
