<?php

declare(strict_types=1);

/**********************************************************************
 * Helper Object
 * --------------------------------------------------------------------
 * File: Helper.php
 * Author: Julmer Olivero <jolivero.03@gmail.com>
 * Licence: MIT
 * --------------------------------------------------------------------
 * El archivo Helper.php Contiene varias funciones con las cuales se
 * puede obtener el resultado deseado de algunos Objetos.
 **********************************************************************/

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Exception;

class Helper extends Model
{
    public static function successMessage($string = null)
    {
        $type = 'success';
        $text = is_string($string) ? $string : 'Request successful';

        return compact('type', 'text');
    }

    public static function errorMessage($string = null)
    {
        $type = 'success';
        $text = is_string($string) ? $string : 'Request unsuccessful';

        return compact('type', 'text');
    }

    public static function bbcodeToHtml($string)
    {
        $bbcode = new \ChrisKonnertz\BBCode\BBCode();
        return $bbcode->render($string);
    }

    private static function ConSoSinS($val, $sentence)
    {
        if ($val > 1) {
            return $val . str_replace(['(s)', '(es)'], ['s', 'es'], $sentence);
        } else {
            return $val . str_replace('(s)', '', $sentence);
        }
    }

    public static function getDate($value)
    {
        $time = time() - $value;

        if ($time <= 0) {
            return 'Ahora';
        } elseif ($time < 60) {
            return 'Hace '.self::ConSoSinS(floor($time), ' Segundo(s)');
        } elseif ($time < 60 * 60) {
            return 'Hace '.self::ConSoSinS(floor($time / 60), ' Minuto(s)');
        } elseif ($time < 60 * 60 * 24) {
            return 'Hace '.self::ConSoSinS(floor($time / (60 * 60)), ' Hora(s)');
        } elseif ($time < 60 * 60 * 24 * 30) {
            return 'Hace '.self::ConSoSinS(floor($time / (60 * 60 * 24)), ' Día(s)');
        } elseif ($time < 60 * 60 * 24 * 30 * 12) {
            return 'Hace '.self::ConSoSinS(floor($time / (60 * 60 * 24 * 30)), ' Mes(es)');
        } else {
            return 'Hace '.self::ConSoSinS(floor($time / (60 * 60 * 24 * 30 * 12)), ' Año(s)');
        }
    }

    public static function setSpanishDate($formato)
    {
        $trad = [
            'Sunday' => 'Domingo',  'Monday' => 'Lunes',
            'Tuesday' => 'Martes',  'Wednesday' => 'Mi&eacute;rcoles',
            'Thursday' => 'Jueves', 'Friday' => 'Viernes',
            'Saturday' => 'S&aacute;bado',
            'Sun' => 'Dom', 'Mon' => 'Lun',
            'Tue' => 'Mar', 'Wed' => 'Mie',
            'Thu' => 'Jue', 'Fri' => 'Vie', 'Sat' => 'Sab',
            'January' => 'Enero',   'February' => 'Febrero',
            'March' => 'Marzo',   'April' => 'Abril',
            'May' => 'Mayo',    'June' => 'Junio',
            'July' => 'Julio',   'August' => 'Agosto',
            'September' => 'Septiembre',  'October' => 'Octubre',
            'November' => 'Noviembre',   'December' => 'Diciembre',
            'Jan' => 'Ene', 'Feb' => 'Feb', 'Mar' => 'Mar',
            'Apr' => 'Abr', 'May' => 'May', 'Jun' => 'Jun',
            'Jul' => 'Jul', 'Aug' => 'Ago', 'Sep' => 'Sep',
            'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Dic',
        ];

        return strtr($formato, $trad);
    }

    public static function parseBBCode($string)
    {
        if (empty($string)) {
            return '';
        }
        // proceed to the replacement of all self-closing tags first
        $result = preg_replace('~\[ (br|hr|img)\b ([^]]*) ]~xi', '<$1$2/>', $string);


        // then replace the innermost tags until there's nothing to replace
        $count = 0;
        do {
            $result = preg_replace('~
                \[ ( (\w+) [^]]* ) ]     # opening tag
                ( [^[]*+ )               # content without other bracketed tags
                \[/ \2 ]                 # closing tag
            ~xi', '<$1>$3</$2>', $result, -1, $count);
        } while ($count);

        return $result;

    }

    public static function split_str($value)
    {
        $change = [',' => '<br />'];

        return strtr($value, $change);
    }

    public static function removePTagsOnImages($content)
    {
        return preg_replace('​/<p>\s*(<a .*>)?\s*(<img .* \/>)\s*(<\/a>)?\s*<\/p>/iU', '\1\2\3', $content);
    }

    public static function img_post($string)
    {
        $string = htmlentities($string);

        $imageTag = '(<img.+?/>)';
        $cleanImage = preg_replace($imageTag, $imageTag, $string);

        preg_match_all('@src="([^"]+)"@', $string, $images);

        $images = array_pop($images);
        //dd($images);
        if (! empty($images[0])) {
            return $images[0];
        } else {
            return 'https://coanime.net/images/no_post_image.jpg';
        }
    }

    public static function excerpt($value, $words = 20, $end = '...')
    {
        return \Illuminate\Support\Str::words($value, $words, $end);
    }

    public static function textWithoutImage($string)
    {
        $imageTag = '(<img.+?/>)';

        $cleanText = preg_replace($imageTag, ' ', $string);

        return $cleanText;
    }

    public static function getVideoLink($videoString)
    {
        // return data
        $videos = [];
        if (! empty($videoString)) {
            // split on line breaks
            $videoString = stripslashes(trim($videoString));
            $videoString = explode("\n", $videoString);
            $videoString = array_filter($videoString, 'trim');
            // check each video for proper formatting
            foreach ($videoString as $video) {
                // check for iframe to get the video url
                if (strpos($video, 'iframe') !== false) {
                    // retrieve the video url
                    //$anchorRegex = '/src="(.*)?"/isU';
                    $anchorRegex = '/<iframe.*src=\"(.*)\".*><\/iframe>/isU';
                    $results = [];
                    if (preg_match($anchorRegex, $video, $results)) {
                        //dd($results);
                        $link = trim($results[1]);
                    }
                } else {
                    // we already have a url
                    $link = $video;
                }
                // if we have a URL, parse it down
                if (! empty($link)) {
                    // initial values
                    $video_id = null;
                    $videoIdRegex = null;
                    $results = [];
                    // check for type of youtube link
                    if (strpos($link, 'youtu') !== false) {
                        if (strpos($link, 'youtube.com') !== false) {
                            // works on:
                            // http://www.youtube.com/embed/VIDEOID
                            // http://www.youtube.com/embed/VIDEOID?modestbranding=1&amp;rel=0
                            // http://www.youtube.com/v/VIDEO-ID?fs=1&amp;hl=en_US
                            $videoIdRegex = '/youtube.com\/(?:embed|v){1}\/([a-zA-Z0-9_\-\.]+)\??/i';
                        } elseif (strpos($link, 'youtu.be') !== false) {
                            // works on:
                            // http://youtu.be/daro6K6mym8
                            $videoIdRegex = '/youtu.be\/([a-zA-Z0-9_-]+)\??/i';
                        }
                        if ($videoIdRegex !== null) {
                            if (preg_match($videoIdRegex, $link, $results)) {
                                $video_str = 'https://youtube.com/embed/%s';
                                $thumbnail_str = 'https://img.youtube.com/vi/%s/hqdefault.jpg';
                                $fullsize_str = 'https://img.youtube.com/vi/%s/maxresdefault.jpg';
                                $video_id = $results[1];
                            }
                        }
                    }
                    // handle vimeo videos
                    elseif (strpos($video, 'vimeo') !== false) {
                        if (strpos($video, 'player.vimeo.com') !== false) {
                            // works on:
                            // http://player.vimeo.com/video/37985580?title=0&amp;byline=0&amp;portrait=0
                            $videoIdRegex = '/player.vimeo.com\/video\/([0-9]+)\??/i';
                        } else {
                            // works on:
                            // http://vimeo.com/37985580
                            $videoIdRegex = '/vimeo.com\/([0-9]+)\??/i';
                        }
                        if ($videoIdRegex !== null) {
                            if (preg_match($videoIdRegex, $link, $results)) {
                                $video_id = $results[1];
                                // get the thumbnail
                                try {
                                    $response = Http::timeout(10)->get("https://vimeo.com/api/v2/video/$video_id.php");
                                    if ($response->successful()) {
                                        $hash = unserialize($response->body());
                                        if (! empty($hash) && is_array($hash)) {
                                            $video_str = 'https://vimeo.com/moogaloop.swf?clip_id=%s';
                                            $thumbnail_str = $hash[0]['thumbnail_small'];
                                            $fullsize_str = $hash[0]['thumbnail_large'];
                                        } else {
                                            // don't use, couldn't find what we need
                                            unset($video_id);
                                        }
                                    } else {
                                        unset($video_id);
                                    }
                                } catch (Exception $e) {
                                    \Log::warning('Error fetching Vimeo video data', ['video_id' => $video_id, 'error' => $e->getMessage()]);
                                    unset($video_id);
                                }
                            }
                        }
                    }
                    // check if we have a video id, if so, add the video metadata
                    if (! empty($video_id)) {
                        // add to return
                        $videos = [
                            'url' => sprintf($video_str, $video_id),
                            'thumbnail' => sprintf($thumbnail_str, $video_id),
                            'fullsize' => sprintf($fullsize_str, $video_id),
                        ];
                    }
                }
            }
        }
        // return array of parsed videos
        return $videos;
        //dd($videos);
    }

    public static function getAllImages()
    {
    }

    public static function getTitleImage($string)
    {
        $server = 'https://'.$_SERVER['HTTP_HOST'];

        $path = '/images/encyclopedia/titles/';

        $file = $server.$path.$string;

        return $file;
    }

    public static function getTitleImageWithTags($string)
    {
        $server = 'https://'.$_SERVER['HTTP_HOST'];

        $path = '/images/encyclopedia/titles/';

        $file = $server.$path.$string;

        return '<img src="'.$file.'" />';
    }

    public static function getLastsTvShows()
    {
        $url = 'https://www.ecma.animekaigen.xyz/api/content?cuantos=200&buscar=&ordenado=0&iniciar=2600';
        try {
            $response = Http::timeout(15)->get($url);
            if (!$response->successful()) {
                \Log::error('Error fetching TV shows from external API', ['url' => $url, 'status' => $response->status()]);
                return [];
            }
            $json = $response->json();
        } catch (Exception $e) {
            \Log::error('Error fetching TV shows from external API', ['url' => $url, 'error' => $e->getMessage()]);
            return [];
        }

        //dd($json);
        $data = [];
        foreach ($json as $j) {
            $jdata = $j['response']['anime'];
            $data['name'] = $jdata['nombre'];
            $data['slug'] = Str::slug($jdata['nombre']);
            $data['sinopsis'] = $jdata['sinopsis'];
            $data['episodies'] = $jdata['episodios'];
            $data['user_id'] = 1;
            if ($jdata['tipo'] == 'Anime') {
                $data['type_id'] = 1;
            }
            if ($jdata['tipo'] == 'Película') {
                $data['type_id'] = 3;
            }
            if ($jdata['tipo'] == 'Ova') {
                $data['type_id'] = 4;
            }
            if ($jdata['tipo'] == 'Ona') {
                $data['type_id'] = 10;
            }

            if (Title::where('name', 'like', $data['name'])->where('type_id', 'like', $data['type_id'])->count() > 0) {
                echo $data['name'].': Data Existente </br>';
            } else {
                Title::create($data);
                echo $data['name'].': Data Creada </br>';
            }
        }
    }

    protected function getRelatedSlugs($slug, $id = 0)
    {
        return Post::select('slug')->where('slug', 'like', $slug.'%')
            ->whereNotIn($id)
            ->get();
    }

    public static function createSlug($title, $id = 0)
    {
        // Normalize the title
        $slug = Str::slug($title);
        // Get any that could possibly be related.
        // This cuts the queries down by doing it once.
        $allSlugs = self::getRelatedSlugs($slug, $id);
        // If we haven't used it before then we are all good.
        if (! $allSlugs->contains('slug', $slug)) {
            return $slug;
        }
        // Just append numbers like a savage until we find not used.
        for ($i = 1; $i <= 10; $i++) {
            $newSlug = $slug.'-'.$i;
            if (! $allSlugs->contains('slug', $newSlug)) {
                return $newSlug;
            }
        }
        throw new \Exception('Can not create a unique slug');
    }
}