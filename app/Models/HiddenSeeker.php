<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Goutte\Client as GoutteClient;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Jikan\JikanPHP\Client;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Intervention\Image\Facades\Image;
use App\Models\Title;

class HiddenSeeker extends Model
{
  private static $genres = [
        'adventure' => 1,
        'comedy' => 2,
        'romance' => 3,
        'drama' => 4,
        'sci-fi' => 5,
        'action' => 7,
        'magic' => 8,
        'psychological' => 9,
        'horror' => 10,
        'mystery' => 11,
        'supernatural' => 12,
        'erotica' => 13,
        'fantasy' => 14,
        'slice of life' => 15,
        'thriller' => 16,
        'suspense' => 16,
        'mecha' => 17,
        'historical' => 18,
        'ecchi' => 19,
        'cooking' => 20,
        'shoujo' => 21,
        'detectives' => 22,
        'seinen' => 23,
        'maids' => 24,
        'moe' => 25,
        'shounen' => 26,
        'school life' => 27,
        'school' => 27,
        'gore' => 28,
        'harem' => 29,
        'yuri' => 30,
        'girls love' => 30,
        'yaoi' => 31,
        'boys love' => 31,
        'sports' => 32,
        'team sports' => 32,
        'strategy game' => 40,
        'martial arts' => 43,
        'survival' => 45,
        'shounen ai' => 50,
        'shoujo ai' => 51,
        'josei' => 52,
        'doujinshi' => 53,
        'music' => 54,
        'spacial' => 55,
        'gothic' => 56,
        'dark fantasy' => 57,
        'demons' => 58,
        'smut' => 59,
        'sentai' => 60,
        'parody' => 61,
        'super powers' => 62,
        'super power' => 62,
        'superhero' => 62,
        'military' => 63,
        'samurai' => 64,
        'childs' => 65,
        'video games' => 66,
        'video game' => 66,
        'police' => 67,
        'vampires' => 68,
        'racing' => 69,
        'monsters' => 70,
        'isekai' => 71,
        'monster girls' => 72,
        'delinquents' => 74,
        'reverse harem' => 75,
        'office workers' => 76,
        'tragedy' => 77,
        'crime' => 78,
        'magical girls' => 79,
        'medical' => 80,
        'philosophical' => 81,
        'wuxia' => 82,
        'aliens' => 83,
        'animals' => 84,
        'crossdressing' => 85,
        'genderswap' => 86,
        'ghosts' => 87,
        'gyaru' => 88,
        'incest' => 89,
        'loli' => 90,
        'mafia' => 91,
        'ninja' => 92,
        'post-apocalyptic' => 93,
        'music' => 94,
        'reincarnation' => 95,
        'shota' => 96,
        'time travel' => 97,
        'traditional games' => 98,
        'villainess' => 99,
        'virtual reality' => 100,
        'zombies' => 101,
        'romantic subtext' => 102,
        'mythology' => 103,
        'high stakes game' => 104,
        'love polygon' => 105,
        'avant garde' => 106,
        'otaku culture' => 107,
        'gourmet' => 108,
        'hentai' => 109,
    ];
      private static $typeInCloud = [
        'tv',
        'manga',
        'movie',
        'ova',
        'manhwa',
        'manhua',
        'ona',
        'light novel',
        'special',
        'one-shot',
        'doujinshi',
        'novel',
    ];
    private static $rating = [
        'g - all ages'                    => 1,
        'g'                               => 1,
        'pg - children'                   => 2,
        'pg'                              => 2,
        'pg-13 - teens 13 or older'       => 3,
        'pg-13'                           => 3,
        'r - 17+ (violence & profanity)'  => 4,
        'r'                               => 4,
        'r+ - mild nudity'                => 5,
        'r+'                              => 5,
        'rx - hentai'                     => 6,
        'rx'                              => 6,
    ];
    private static $typeTranslations = [
        'tv'            => 'tv',
        'manga'         => 'manga',
        'pelicula'      => 'movie',
        'ova'           => 'ova',
        'manhwa'        => 'manhwa',
        'manhua'        => 'manhua',
        'ona'           => 'ona',
        'novela-ligera' => 'light novel',
        'especial'      => 'special',
        'one-shot'      => 'one-shot',
        'doujinshi'     => 'doujinshi',
        'novela'        => 'novel',
    ];

    private static $status = [
        'Currently Airing'  => 'En emisión',
        'Finished Airing'   => 'Finalizado',
        'Not yet aired'     => 'Estreno',
        'Finished'          => 'Finalizado',
        'Publishing'        => 'Publicándose',
        'Discontinued'      => 'Descontinuado',
        'On Hiatus'         => 'En espera',
    ];

    private static $typeById = [
        'tv' => 1,
        'manga' => 2,
        'movie' => 3,
        'ova' => 4,
        'manhwa' => 5,
        'manhua' => 6,
        'ona' => 10,
        'light novel' => 11,
        'special' => 13,
        'one-shot' => 14,
        'doujinshi' => 15,
        'novel' => 16,
    ];

    public static function getType($type)
    {
        return self::$typeTranslations[$type] ?? null;
    }

    public static function getRatingId($rating)
    {
        return self::$rating[$rating] ?? null;
    }

    public static function getStatus($status)
    {
        return self::$status[$status] ?? null;
    }

    public static function getTypeById($id)
    {
        return self::$typeById[$id] ?? null;
    }

    public static function getGenres($genre)
    {
        return self::$genres[$genre] ?? null;
    }

    /**
     * A method to get the series by title and type and update the title object
     * $title object
     * $type string
     */
    public static function updateSeriesByTitle($title, $type)
    {
        $jikan = Client::create();
        $isManga = self::getType($type) === 'manga' || self::getType($type) === 'manhwa' || self::getType($type) === 'manhua' || self::getType($type) === 'novel' || self::getType($type) === 'one-shot' || self::getType($type) === 'doujinshi' || self::getType($type) === 'light novel' ?: false;

        if ($title?->id && self::getType($type)) {
            $localTitle = Title::find($title->id);
            $cloudTitlesTemp = $isManga ? collect($jikan->getMangaSearch(['q' => $title->name, 'type' => $type])->getData()) : collect($jikan->getAnimeSearch(['q' => $title->name, 'type' => $type])->getData());

            $cloudTitlesTemp = $cloudTitlesTemp->filter(function ($value) use ($type) {
                return strtolower($value->getType()) === self::getType($type);
            });

            $cloudTitlesTemp = $cloudTitlesTemp->filter(function ($value) use ($title) {
                return strtolower($value->getTitle()) === strtolower($title->name);
            });

            $cloudTitle = $cloudTitlesTemp?->first() ?: null;

            //dd($cloudTitle);

            if ($cloudTitle?->getTitles() !== null) {
                $otherTitles = [];
                foreach ($cloudTitle->getTitles() as $value) {
                    if (strtolower($value->getType()) === 'english') {
                        $titleEnglish = $value->getTitle().' (Inglés)';
                        if (!in_array($titleEnglish, $otherTitles)) {
                            $otherTitles[] = $titleEnglish;
                        }
                    }
                    if (strtolower($value->getType()) === 'japanese') {
                        $titleJapanese = $value->getTitle().' (Japonés)';
                        if (!in_array($titleJapanese, $otherTitles)) {
                            $otherTitles[] = $titleJapanese;
                        }
                    }
                    $localTitle->other_titles = implode(', ', $otherTitles);
                    $localTitle->save();
                }
            }

            if ((empty($title->sinopsis) || $localTitle->sinopsis == 'Sinopsis no disponible' || $localTitle->sinopsis == 'Pendiente de agregar sinopsis...' || $localTitle->sinopsis == 'Sinopsis no disponible.' || $localTitle->sinopsis == 'Sinopsis en Proceso') && $cloudTitle->getSynopsis() !== null) {
                $localTitle->sinopsis = GoogleTranslate::trans(str_replace('[Written by MAL Rewrite]', '', $cloudTitle->getSynopsis()), 'es');
                $localTitle->save();
            }


            if (!$isManga) {
                //dd($cloudTitle->getAired());
                if ((empty($localTitle->trailer_url) || $localTitle->trailer_url === null || $localTitle->trailer_url === '') && $cloudTitle?->getTrailer()?->getUrl() !== null) {
                    $localTitle->trailer_url = $cloudTitle->getTrailer()->getUrl();
                    $localTitle->save();
                }

                if (! $localTitle->rating_id || $localTitle->rating_id === 7) {
                    $localTitle->rating_id = self::getRatingId(strtolower($cloudTitle->getRating())) ?? 7;
                    $localTitle->save();
                }

                if ($localTitle->episodies === 0 || $localTitle->episodies === null || empty($localTitle->episodies)) {
                    $localTitle->episodies = $cloudTitle->getEpisodes();
                    $localTitle->save();
                }

                if ($localTitle->broad_time === null || $localTitle->broad_time === '0000-00-00 00:00:00' || $localTitle->broad_time !== $cloudTitle?->getAired()?->getFrom()) {
                    $localTitle->broad_time = Carbon::create($cloudTitle->getAired()->getFrom())->format('Y-m-d');
                    $localTitle->save();
                }

                if ($localTitle->broad_finish === null || $localTitle->broad_finish === '0000-00-00 00:00:00' || $localTitle->broad_finish !== $cloudTitle?->getAired()?->getTo()) {
                    $localTitle->broad_finish = Carbon::create($cloudTitle->getAired()->getTo())->format('Y-m-d');
                    $localTitle->save();
                }
            }

            if ($isManga) {
                if ($localTitle->broad_time === null || $localTitle->broad_time === '0000-00-00 00:00:00' || $localTitle->broad_time !== $cloudTitle?->getPublished()?->getFrom()) {
                    $localTitle->broad_time = Carbon::create($cloudTitle->getPublished()->getFrom())->format('Y-m-d');
                    $localTitle->save();
                }

                if ($localTitle->broad_finish === null || $localTitle->broad_finish === '0000-00-00 00:00:00' || $localTitle->broad_finish !== $cloudTitle?->getPublished()?->getTo()) {
                    $localTitle->broad_finish = Carbon::create($cloudTitle->getPublished()->getTo())->format('Y-m-d');
                    $localTitle->save();
                }

                if ($localTitle->episodies === 0 || $localTitle->episodies === null || empty($localTitle->episodies)) {
                    $localTitle->episodies = $cloudTitle->getChapters();
                    $localTitle->save();
                }
            }

            if (!$localTitle->status || self::getStatus($cloudTitle->getStatus()) !== $localTitle->status) {
                $localTitle->status = self::getStatus($cloudTitle->getStatus());
                $localTitle->save();
            }

            if (!$title?->images || $title?->images?->name === null || $title?->images?->name === '') {
                $imageUrl = $cloudTitle->getImages()->getWebp()->getLargeImageUrl() === 'https://cdn.myanimelist.net/img/sp/icon/apple-touch-icon-256.png' ? null : $cloudTitle->getImages()->getWebp()->getLargeImageUrl();
                if ($imageUrl) {
                    $processingImage = file_get_contents($imageUrl);
                    $image = Image::make($processingImage);
                    $fileName = hash('sha256', strval(time()));
                    $image->encode('webp', 100);

                    if ($image->width() > 2560) {
                        $image->resize(2560, null, function ($constraint) {
                            $constraint->aspectRatio();
                        });
                    }

                    $path = '/titles/';

                    $filePath = $path.$fileName.'.webp';
                    Storage::disk('s3')->put($filePath, $image);
                    $imageUrl = Storage::disk('s3')->url($filePath);
                    $images = new TitleImage();
                    $images->create([
                        'title_id' => $localTitle->id,
                        'name' => $imageUrl,
                        'thumbnail' => $imageUrl,
                    ]);
                }
            }

            if ($title->genres->count() === 0) {
                $newGenres = [];
                foreach ($cloudTitle->getGenres() as $gen) {
                    $newGenres[] = self::$genres[strtolower($gen->getName())];
                }
                $title->genres()->sync($newGenres);
            }
            }
        }
    }