<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Magazine;
use App\Models\People;
use App\Models\Title;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class NewsEntityFinder
{
    public function similarityThreshold(): int
    {
        return (int) config('services.news_links.similarity', 90);
    }

    public function findTitle(string $name, ?int $typeId = null): ?Title
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $slug = Str::slug($name);
        $query = Title::query();
        if ($typeId !== null) {
            $query->where('type_id', $typeId);
        }

        $exact = (clone $query)
            ->where(function ($q) use ($name, $slug) {
                $q->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->orWhere('slug', $slug);
            })
            ->first();
        if ($exact instanceof Title) {
            return $exact;
        }

        $like = (clone $query)
            ->where(function ($q) use ($name) {
                $q->where('other_titles', 'like', '%'.$name.'%')
                    ->orWhere('name', 'like', $name.'%');
            })
            ->limit(25)
            ->get();

        return $this->bestSimilar($like, $name, fn (Title $t) => (string) $t->name);
    }

    public function findPerson(string $name): ?People
    {
        return $this->findNamed(People::query(), $name, true);
    }

    public function findCompany(string $name): ?Company
    {
        return $this->findNamed(Company::query(), $name, false);
    }

    public function findMagazine(string $name): ?Magazine
    {
        return $this->findNamed(Magazine::query(), $name, false);
    }

    public function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function findNamed($query, string $name, bool $trySwap): ?Model
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $slug = Str::slug($name);
        $exact = (clone $query)
            ->where(function ($q) use ($name, $slug) {
                $q->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->orWhere('slug', $slug);
            })
            ->first();
        if ($exact !== null) {
            return $exact;
        }

        if ($trySwap) {
            $parts = preg_split('/\s+/u', $name) ?: [];
            if (count($parts) >= 2) {
                $swapped = $parts[count($parts) - 1].' '.implode(' ', array_slice($parts, 0, -1));
                $swap = (clone $query)
                    ->where(function ($q) use ($swapped) {
                        $q->whereRaw('LOWER(name) = ?', [mb_strtolower($swapped)])
                            ->orWhere('slug', Str::slug($swapped));
                    })
                    ->first();
                if ($swap !== null) {
                    return $swap;
                }
                $nameVariants = [$name, $swapped];
            } else {
                $nameVariants = [$name];
            }
        } else {
            $nameVariants = [$name];
        }

        $candidates = (clone $query)
            ->where(function ($q) use ($nameVariants) {
                foreach ($nameVariants as $variant) {
                    $q->orWhere('name', 'like', '%'.$variant.'%');
                }
            })
            ->limit(25)
            ->get();

        foreach ($nameVariants as $variant) {
            $best = $this->bestSimilar($candidates, $variant, fn (Model $m) => (string) $m->getAttribute('name'));
            if ($best !== null) {
                return $best;
            }
        }

        return null;
    }

    /**
     * @param  iterable<int, Model>  $candidates
     * @param  callable(Model): string  $nameOf
     */
    private function bestSimilar(iterable $candidates, string $needle, callable $nameOf): ?Model
    {
        $needleNorm = $this->normalize($needle);
        if ($needleNorm === '') {
            return null;
        }
        $threshold = $this->similarityThreshold();
        $best = null;
        $bestScore = 0;
        foreach ($candidates as $candidate) {
            $candidateName = $nameOf($candidate);
            $score = 0;
            similar_text($needleNorm, $this->normalize($candidateName), $score);
            if ($score >= $threshold && $score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $best;
    }
}
