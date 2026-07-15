<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class MyDayFeedService
{
    public function __construct(
        private readonly MyDayImageService $imageService,
    ) {}
    public function makeFeedItem(string $key): array
    {
        $item = match ($key) {
            'weather' => $this->getWeather(),
            'astro' => $this->getPanchang(),
            'quote' => $this->getQuote(),
            'gita' => $this->getGitaVerse(),
            'story' => $this->getStory(),
            'movies' => $this->getMovies(),
            'music' => $this->getMusic(),
            'news' => $this->getNews(),
            'cricket' => $this->getCricket(),
            'gold' => $this->getGoldPrice(),
            'silver' => $this->getSilverPrice(),
            'petrol' => $this->getPetrolPrice(),
            'diesel' => $this->getDieselPrice(),
            'health' => $this->getHealthTip(),
            'recipe' => $this->getRecipe(),
            'fun_fact' => $this->getFunFact(),
            default => $this->fallback($key),
        };

        $variant = null;

        if ($key === 'weather') {
            $variant = data_get(
                $item,
                'payload_json._image_variant',
                'default',
            );
        }

        $image = $this->imageService->feedImage(
            key: $key,
            variant: $variant,
        );

        return $item + [
            'image_path' => $image['path'],
            'image_url' => $image['url'],
        ];
    }

    public function getWeather(): array
    {
        try {
            $response = Http::connectTimeout(3)->timeout(5)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => 15.4777,
                'longitude' => 78.4836,
                'current' => 'temperature_2m,weather_code',
                'daily' => 'temperature_2m_max,temperature_2m_min',
                'timezone' => 'Asia/Kolkata',
            ]);

            if (!$response->successful()) {
                return $this->fallbackWeather();
            }

            $data = $response->json();

            $temp = $data['current']['temperature_2m'] ?? null;
            $code = $data['current']['weather_code'] ?? null;
            $max = $data['daily']['temperature_2m_max'][0] ?? null;
            $min = $data['daily']['temperature_2m_min'][0] ?? null;

            $condition = $this->weatherCondition($code);

            return [
                'title' => 'Today Weather',
                'subtitle' => 'Nandyal',
                'body' => "{$temp}°C • {$condition} | H: {$max}°C L: {$min}°C",
                'payload_json' => $data + [
                    '_image_variant' => $condition,
                ],
            ];
        } catch (\Throwable $e) {
            return $this->fallbackWeather();
        }
    }

    public function getPanchang(): array
    {
        $clientId = env('PROKERALA_CLIENT_ID');
        $clientSecret = env('PROKERALA_CLIENT_SECRET');

        if (!$clientId || !$clientSecret) {
            return [
                'title' => 'Today Panchang',
                'subtitle' => 'Astro update',
                'body' => 'Prokerala credentials are not configured.',
                'payload_json' => null,
            ];
        }

        try {
            $tokenResponse = Http::asForm()
                ->connectTimeout(3)
                ->timeout(8)
                ->post('https://api.prokerala.com/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);

            if (!$tokenResponse->successful()) {
                return [
                    'title' => 'Today Panchang',
                    'subtitle' => 'Astro update',
                    'body' => 'Panchang token request failed.',
                    'payload_json' => null,
                ];
            }

            $token = $tokenResponse->json('access_token');

            if (!$token) {
                return [
                    'title' => 'Today Panchang',
                    'subtitle' => 'Astro update',
                    'body' => 'Panchang access token was not received.',
                    'payload_json' => null,
                ];
            }

            $response = Http::withToken($token)
                ->connectTimeout(3)
                ->timeout(10)
                ->get('https://api.prokerala.com/v2/astrology/panchang', [
                    'ayanamsa' => 1,
                    'coordinates' => env(
                        'MYDAY_COORDINATES',
                        '15.4777,78.4836'
                    ),
                    'datetime' => now('Asia/Kolkata')
                        ->format('Y-m-d\TH:i:sP'),
                ]);

            if (!$response->successful()) {
                return [
                    'title' => 'Today Panchang',
                    'subtitle' => 'Astro update',
                    'body' => 'Panchang update is temporarily unavailable.',
                    'payload_json' => null,
                ];
            }

            $data = $response->json();
            $panchang = $data['data'] ?? $data;

            logger()->info('PROKERALA PANCHANG RAW RESPONSE', [
                'response' => $data,
            ]);

            // $tithiItems = is_array($panchang['tithi'] ?? null)
            //     ? $panchang['tithi']
            //     : [];

            // $nakshatraItems = is_array($panchang['nakshatra'] ?? null)
            //     ? $panchang['nakshatra']
            //     : [];

            // $yogaItems = is_array($panchang['yoga'] ?? null)
            //     ? $panchang['yoga']
            //     : [];

            // $karanaItems = is_array($panchang['karana'] ?? null)
            //     ? $panchang['karana']
            //     : [];

            // $currentTithi = $this->activePanchangItem($tithiItems);
            // $nextTithi = $this->nextPanchangItem($tithiItems, $currentTithi);

            // $currentNakshatra = $this->activePanchangItem($nakshatraItems);
            // $nextNakshatra = $this->nextPanchangItem(
            //     $nakshatraItems,
            //     $currentNakshatra
            // );

            // $currentYoga = $this->activePanchangItem($yogaItems);
            // $nextYogaItem = $this->nextPanchangItem(
            //     $yogaItems,
            //     $currentYoga
            // );

            // $currentKarana = $this->activePanchangItem($karanaItems);
            // $nextKaranaItem = $this->nextPanchangItem(
            //     $karanaItems,
            //     $currentKarana
            // );

            // $tithi = $currentTithi['name'] ?? 'N/A';
            // $paksha = $currentTithi['paksha'] ?? null;

            // $nakshatra = $currentNakshatra['name'] ?? 'N/A';

            // $yoga = $currentYoga['name'] ?? 'N/A';

            // $karana = $currentKarana['name'] ?? 'N/A';

            // $weekday = $panchang['vaara'] ?? null;


            //     $sunrise = $this->formatPanchangTime(
            //         data_get($panchang, 'sunrise')
            //     );

            //     $sunset = $this->formatPanchangTime(
            //         data_get($panchang, 'sunset')
            //     );

            //     $moonrise = $this->formatPanchangTime(
            //         data_get($panchang, 'moonrise')
            //     );

            //     $moonset = $this->formatPanchangTime(
            //         data_get($panchang, 'moonset')
            //     );

            //     /*
            //  * Paksha and rashi
            //  */
            //     $paksha = $this->firstPanchangValue($panchang, [
            //         'paksha.name',
            //         'paksha',
            //         'tithi.0.paksha',
            //     ]);

            //     $rashi = $this->firstPanchangValue($panchang, [
            //         'rashi.name',
            //         'rashi',
            //         'moon_sign.name',
            //         'moon_sign',
            //     ]);

            //     $weekday = $this->firstPanchangValue($panchang, [
            //         'weekday',
            //         'weekday.name',
            //         'vaara',
            //         'vaara.name',
            //     ]);

            //     $amantaMonth = $this->firstPanchangValue($panchang, [
            //         'amanta_month.name',
            //         'amanta_month',
            //         'lunar_month.amanta.name',
            //     ]);

            //     $purnimantaMonth = $this->firstPanchangValue($panchang, [
            //         'purnimanta_month.name',
            //         'purnimanta_month',
            //         'lunar_month.purnimanta.name',
            //     ]);

            //     $sunSign = $this->firstPanchangValue($panchang, [
            //         'sun_sign.name',
            //         'sun_sign',
            //         'solar_sign.name',
            //         'solar_sign',
            //     ]);

            //     $pravishte = $this->firstPanchangValue($panchang, [
            //         'pravishte',
            //         'gate',
            //         'pravishte_gate',
            //     ]);

            //     $shakaSamvat = $this->firstPanchangValue($panchang, [
            //         'shaka_samvat',
            //         'samvat.shaka',
            //     ]);

            //     $vikramSamvat = $this->firstPanchangValue($panchang, [
            //         'vikram_samvat',
            //         'samvat.vikram',
            //     ]);

            //     $gujaratiSamvat = $this->firstPanchangValue($panchang, [
            //         'gujarati_samvat',
            //         'samvat.gujarati',
            //     ]);

            /*
 * Actual Prokerala Panchang response values.
 */
            $tithiItems = is_array($panchang['tithi'] ?? null)
                ? $panchang['tithi']
                : [];

            $nakshatraItems = is_array($panchang['nakshatra'] ?? null)
                ? $panchang['nakshatra']
                : [];

            $yogaItems = is_array($panchang['yoga'] ?? null)
                ? $panchang['yoga']
                : [];

            $karanaItems = is_array($panchang['karana'] ?? null)
                ? $panchang['karana']
                : [];

            $currentTithi = $this->activePanchangItem($tithiItems);
            $nextTithi = $this->nextPanchangItem(
                $tithiItems,
                $currentTithi,
            );

            $currentNakshatra = $this->activePanchangItem($nakshatraItems);
            $nextNakshatra = $this->nextPanchangItem(
                $nakshatraItems,
                $currentNakshatra,
            );

            $currentYoga = $this->activePanchangItem($yogaItems);
            $nextYogaItem = $this->nextPanchangItem(
                $yogaItems,
                $currentYoga,
            );

            $currentKarana = $this->activePanchangItem($karanaItems);
            $nextKaranaItem = $this->nextPanchangItem(
                $karanaItems,
                $currentKarana,
            );

            $tithi = $currentTithi['name'] ?? 'N/A';
            $paksha = $currentTithi['paksha'] ?? null;

            $nakshatra = $currentNakshatra['name'] ?? 'N/A';

            $yoga = $currentYoga['name'] ?? 'N/A';

            $karana = $currentKarana['name'] ?? 'N/A';

            $weekday = $panchang['vaara'] ?? null;

            $sunrise = $this->formatPanchangTime(
                $panchang['sunrise'] ?? null
            );

            $sunset = $this->formatPanchangTime(
                $panchang['sunset'] ?? null
            );

            $moonrise = $this->formatPanchangTime(
                $panchang['moonrise'] ?? null
            );

            $moonset = $this->formatPanchangTime(
                $panchang['moonset'] ?? null
            );


            /*
         * Short preview shown on main feed card.
         */
            $body = implode("\n", array_filter([
                "Tithi: {$tithi}",
                "Nakshatra: {$nakshatra}",
                "Yoga: {$yoga}",
                "Karana: {$karana}",
            ]));

            /*
         * Clean structured data for Flutter modal.
         */
            $payload = array_filter([
                'date' => now('Asia/Kolkata')->format('l, d F Y'),
                'location' => 'Nandyal',

                'sunrise' => $sunrise,
                'sunset' => $sunset,
                'moonrise' => $moonrise,
                'moonset' => $moonset,

                'weekday' => $weekday,

                'tithi' => $tithi,
                'tithi_start' => $this->formatPanchangTime(
                    $currentTithi['start'] ?? null
                ),
                'tithi_end' => $this->formatPanchangTime(
                    $currentTithi['end'] ?? null
                ),

                'next_tithi' => $nextTithi['name'] ?? null,
                'next_tithi_end' => $this->formatPanchangTime(
                    $nextTithi['end'] ?? null
                ),
                'paksha' => $paksha,


                'nakshatra' => $nakshatra,
                'nakshatra_start' => $this->formatPanchangTime(
                    $currentNakshatra['start'] ?? null
                ),
                'nakshatra_end' => $this->formatPanchangTime(
                    $currentNakshatra['end'] ?? null
                ),

                'next_nakshatra' => $nextNakshatra['name'] ?? null,
                'next_nakshatra_end' => $this->formatPanchangTime(
                    $nextNakshatra['end'] ?? null
                ),

                'nakshatra_lord' =>
                $currentNakshatra['lord']['name'] ?? null,

                'nakshatra_lord_vedic_name' =>
                $currentNakshatra['lord']['vedic_name'] ?? null,

                'yoga' => $yoga,
                'yoga_start' => $this->formatPanchangTime(
                    $currentYoga['start'] ?? null
                ),
                'yoga_end' => $this->formatPanchangTime(
                    $currentYoga['end'] ?? null
                ),

                'next_yoga' => $nextYogaItem['name'] ?? null,
                'next_yoga_end' => $this->formatPanchangTime(
                    $nextYogaItem['end'] ?? null
                ),

                'karana' => $karana,
                'karana_start' => $this->formatPanchangTime(
                    $currentKarana['start'] ?? null
                ),
                'karana_end' => $this->formatPanchangTime(
                    $currentKarana['end'] ?? null
                ),

                'next_karana' => $nextKaranaItem['name'] ?? null,
                'next_karana_end' => $this->formatPanchangTime(
                    $nextKaranaItem['end'] ?? null
                ),

                'daily_guidance' =>
                'Use today’s Panchang as a traditional reference '
                    . 'for prayer, meditation and planning the day.',
            ], fn($value) => $value !== null && $value !== '');

            return [
                'title' => 'Today Panchang',
                'subtitle' => 'Nandyal',
                'body' => $body,
                'payload_json' => $payload,
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'title' => 'Today Panchang',
                'subtitle' => 'Astro update',
                'body' => 'Panchang update is temporarily unavailable.',
                'payload_json' => null,
            ];
        }
    }



    public function getQuote(): array
    {
        $apiKey = env('DAILY_QUOTES_API_KEY');

        if (!$apiKey) {
            return [
                'title' => 'Quote of the Day',
                'subtitle' => 'Daily Inspiration',
                'body' => 'No quote API key configured.',
                'payload_json' => null,
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept' => 'application/json',
        ])->connectTimeout(3)->timeout(5)->get('https://api.dailyquotes.dev/api/quote');

        if (!$response->successful()) {
            return [
                'title' => 'Quote of the Day',
                'subtitle' => 'Daily Inspiration',
                'body' => 'Quote service temporarily unavailable.',
                'payload_json' => $response->json(),
            ];
        }

        $data = $response->json();

        return [
            'title' => 'Quote of the Day',
            'subtitle' => $data['author'] ?? 'Daily Quotes',
            'body' => $data['quote'] ?? 'Stay positive and keep going.',
            'payload_json' => $data,
        ];
    }

    public function getGitaVerse(): array
    {
        $verses = [
            ['Bhagavad Gita 2.47', 'You have the right to perform your duty, but not to the fruits of action.'],
            ['Bhagavad Gita 4.7', 'Whenever dharma declines and adharma rises, I manifest myself.'],
            ['Bhagavad Gita 6.5', 'Let a person lift themselves by their own self.'],
        ];

        $verse = $verses[now('Asia/Kolkata')->dayOfYear % count($verses)];

        return [
            'title' => 'Gita Verse',
            'subtitle' => $verse[0],
            'body' => $verse[1],
            'payload_json' => ['source' => 'local'],
        ];
    }

    public function getStory(): array
    {
        return [
            'title' => 'Daily Story',
            'subtitle' => 'Spiritual story',
            'body' => 'A short inspiring story will appear here from Dayli local content.',
            'payload_json' => ['source' => 'local'],
        ];
    }

    public function getMovies(): array
    {
        $apiKey = env('TMDB_API_KEY');

        if (!$apiKey) {
            return [
                'title' => 'Movies & OTT',
                'subtitle' => 'New releases',
                'body' => 'TMDB API key is not configured.',
                'payload_json' => null,
            ];
        }

        try {
            $response = Http::connectTimeout(3)->timeout(5)->get('https://api.themoviedb.org/3/movie/now_playing', [
                'api_key' => $apiKey,
                'language' => 'en-IN',
                'region' => 'IN',
                'page' => 1,
            ]);

            if (!$response->successful()) {
                return [
                    'title' => 'Movies & OTT',
                    'subtitle' => 'New releases',
                    'body' => 'Movie updates are temporarily unavailable.',
                    'payload_json' => $response->json(),
                ];
            }

            $data = $response->json();
            $movies = collect($data['results'] ?? [])
                ->take(3)
                ->map(function ($movie) {
                    $title = $movie['title'] ?? $movie['original_title'] ?? null;
                    $date = $movie['release_date'] ?? null;

                    return trim($title . ($date ? ' (' . $date . ')' : ''));
                })
                ->filter()
                ->values()
                ->all();

            if (empty($movies)) {
                return [
                    'title' => 'Movies & OTT',
                    'subtitle' => 'New releases',
                    'body' => 'No new movie releases found right now.',
                    'payload_json' => $data,
                ];
            }

            return [
                'title' => 'Movies & OTT',
                'subtitle' => 'Now playing in India',
                'body' => implode("\n", array_map(fn($movie) => '• ' . $movie, $movies)),
                'payload_json' => $data,
            ];
        } catch (\Throwable $e) {
            return [
                'title' => 'Movies & OTT',
                'subtitle' => 'New releases',
                'body' => 'Movie updates are temporarily unavailable.',
                'payload_json' => ['error' => $e->getMessage()],
            ];
        }
    }

    public function getMusic(): array
    {
        $apiKey = env('LASTFM_API_KEY');

        if (!$apiKey) {
            return [
                'title' => 'New Music',
                'subtitle' => 'Fresh releases',
                'body' => 'Last.fm API key is not configured.',
                'payload_json' => null,
            ];
        }

        try {
            $response = Http::connectTimeout(3)->timeout(5)->get('https://ws.audioscrobbler.com/2.0/', [
                'method' => 'geo.gettoptracks',
                'country' => 'India',
                'api_key' => $apiKey,
                'format' => 'json',
                'limit' => 3,
            ]);

            if (!$response->successful()) {
                return [
                    'title' => 'New Music',
                    'subtitle' => 'Trending tracks',
                    'body' => 'Music updates are temporarily unavailable.',
                    'payload_json' => $response->json(),
                ];
            }

            $data = $response->json();
            $tracks = collect($data['tracks']['track'] ?? [])
                ->take(3)
                ->map(function ($track) {
                    $name = $track['name'] ?? null;
                    $artist = $track['artist']['name'] ?? null;

                    return trim($name . ($artist ? ' - ' . $artist : ''));
                })
                ->filter()
                ->values()
                ->all();

            if (empty($tracks)) {
                return [
                    'title' => 'New Music',
                    'subtitle' => 'Trending tracks',
                    'body' => 'No trending tracks found right now.',
                    'payload_json' => $data,
                ];
            }

            return [
                'title' => 'New Music',
                'subtitle' => 'Trending in India',
                'body' => implode("\n", array_map(fn($track) => '• ' . $track, $tracks)),
                'payload_json' => $data,
            ];
        } catch (\Throwable $e) {
            return [
                'title' => 'New Music',
                'subtitle' => 'Trending tracks',
                'body' => 'Music updates are temporarily unavailable.',
                'payload_json' => ['error' => $e->getMessage()],
            ];
        }
    }

    public function getNews(): array
    {
        $apiKey = env('GNEWS_API_KEY');

        if (!$apiKey) {
            return [
                'title' => 'Today News',
                'subtitle' => 'Top headlines',
                'body' => 'News updates will appear after GNews API key setup.',
                'payload_json' => null,
            ];
        }

        $response = Http::connectTimeout(3)->timeout(5)->get('https://gnews.io/api/v4/top-headlines', [
            'category' => 'general',
            'lang' => 'en',
            'country' => 'in',
            'max' => 3,
            'apikey' => $apiKey,
        ]);

        if (!$response->successful()) {
            return [
                'title' => 'Today News',
                'subtitle' => 'Top headlines',
                'body' => 'News update is temporarily unavailable.',
                'payload_json' => $response->json(),
            ];
        }

        $data = $response->json();
        $articles = $data['articles'] ?? [];

        if (empty($articles)) {
            return [
                'title' => 'Today News',
                'subtitle' => 'Top headlines',
                'body' => 'No major headlines found right now.',
                'payload_json' => $data,
            ];
        }

        $titles = collect($articles)
            ->take(3)
            ->pluck('title')
            ->filter()
            ->values()
            ->all();

        return [
            'title' => 'Today News',
            'subtitle' => 'India headlines',
            'body' => implode("\n", array_map(fn($t) => '• ' . $t, $titles)),
            'payload_json' => $data,
        ];
    }

    public function getCricket(): array
    {
        $apiKey = env('CRICKET_API_KEY');

        if (!$apiKey) {
            return [
                'title' => 'Cricket',
                'subtitle' => 'Match updates',
                'body' => 'Cricket API key is not configured.',
                'payload_json' => null,
            ];
        }

        try {
            $response = Http::connectTimeout(3)->timeout(5)->get('https://api.cricapi.com/v1/currentMatches', [
                'apikey' => $apiKey,
                'offset' => 0,
            ]);

            if (!$response->successful()) {
                return [
                    'title' => 'Cricket',
                    'subtitle' => 'Match updates',
                    'body' => 'Cricket updates are temporarily unavailable.',
                    'payload_json' => $response->json(),
                ];
            }

            $data = $response->json();
            $matches = collect($data['data'] ?? [])
                ->take(3)
                ->map(function ($match) {
                    $name = $match['name'] ?? null;
                    $status = $match['status'] ?? null;

                    if (!$name && !empty($match['teams']) && is_array($match['teams'])) {
                        $name = implode(' vs ', $match['teams']);
                    }

                    return trim($name . ($status ? ' - ' . $status : ''));
                })
                ->filter()
                ->values()
                ->all();

            if (empty($matches)) {
                return [
                    'title' => 'Cricket',
                    'subtitle' => 'Match updates',
                    'body' => 'No live cricket updates found right now.',
                    'payload_json' => $data,
                ];
            }

            return [
                'title' => 'Cricket',
                'subtitle' => 'Live / current matches',
                'body' => implode("\n", array_map(fn($match) => '• ' . $match, $matches)),
                'payload_json' => $data,
            ];
        } catch (\Throwable $e) {
            return [
                'title' => 'Cricket',
                'subtitle' => 'Match updates',
                'body' => 'Cricket updates are temporarily unavailable.',
                'payload_json' => ['error' => $e->getMessage()],
            ];
        }
    }

    public function getGoldPrice(): array
    {
        return $this->getMetalPrice('gold');
    }



    public function getSilverPrice(): array
    {
        return $this->getMetalPrice('silver');
    }



    public function getPetrolPrice(): array
    {
        return $this->getFuelPrice('petrol');
    }

    public function getDieselPrice(): array
    {
        return $this->getFuelPrice('diesel');
    }



    public function getHealthTip(): array
    {
        $tips = [
            'Drink enough water and keep your day balanced.',
            'Take a short walk after meals for better digestion.',
            'Sleep well and avoid screens before bed.',
        ];

        return [
            'title' => 'Health Tip',
            'subtitle' => 'Wellness',
            'body' => $tips[now('Asia/Kolkata')->dayOfYear % count($tips)],
            'payload_json' => ['source' => 'local'],
        ];
    }

    public function getRecipe(): array
    {
        $apiKey = env('THEMEALDB_API_KEY', '1');

        try {
            $response = Http::connectTimeout(3)->timeout(5)->get("https://www.themealdb.com/api/json/v1/{$apiKey}/random.php");

            if (!$response->successful()) {
                return [
                    'title' => 'Recipe',
                    'subtitle' => 'Today special',
                    'body' => 'Recipe update is temporarily unavailable.',
                    'payload_json' => $response->json(),
                ];
            }

            $data = $response->json();
            $meal = $data['meals'][0] ?? null;

            if (!$meal) {
                return [
                    'title' => 'Recipe',
                    'subtitle' => 'Today special',
                    'body' => 'No recipe found right now.',
                    'payload_json' => $data,
                ];
            }

            $name = $meal['strMeal'] ?? 'Today Recipe';
            $category = $meal['strCategory'] ?? 'Recipe';
            $area = $meal['strArea'] ?? null;

            $ingredients = [];
            for ($i = 1; $i <= 20; $i++) {
                $ingredient = trim((string) ($meal["strIngredient{$i}"] ?? ''));
                $measure = trim((string) ($meal["strMeasure{$i}"] ?? ''));

                if ($ingredient !== '') {
                    $ingredients[] = trim($measure . ' ' . $ingredient);
                }
            }

            $bodyLines = array_filter([
                $name,
                !empty($ingredients) ? 'Ingredients: ' . implode(', ', array_slice($ingredients, 0, 5)) : null,
            ]);

            return [
                'title' => 'Recipe',
                'subtitle' => trim($category . ($area ? " • {$area}" : '')),
                'body' => implode("\n", $bodyLines),
                'payload_json' => $data,
            ];
        } catch (\Throwable $e) {
            return [
                'title' => 'Recipe',
                'subtitle' => 'Today special',
                'body' => 'Recipe update is temporarily unavailable.',
                'payload_json' => ['error' => $e->getMessage()],
            ];
        }
    }



    public function getFunFact(): array
    {
        $facts = [
            'Honey never spoils when stored properly.',
            'Bananas are berries, but strawberries are not true berries.',
            'A day on Venus is longer than a year on Venus.',
        ];

        return [
            'title' => 'Fun Fact',
            'subtitle' => 'Learn something new',
            'body' => $facts[now('Asia/Kolkata')->dayOfYear % count($facts)],
            'payload_json' => ['source' => 'local'],
        ];
    }


    private function getMetalPrice(string $metal): array
    {
        $apiKey = env('METALS_API_KEY');

        if (!$apiKey) {
            return [
                'title' => ucfirst($metal) . ' Price',
                'subtitle' => 'Finance update',
                'body' => 'Metals API key is not configured.',
                'payload_json' => null,
            ];
        }

        try {
            $response = Http::connectTimeout(3)->timeout(5)->get('https://api.metals.dev/v1/latest', [
                'api_key' => $apiKey,
                'currency' => 'INR',
                'unit' => 'g',
            ]);

            if (!$response->successful()) {
                return [
                    'title' => ucfirst($metal) . ' Price',
                    'subtitle' => 'Finance update',
                    'body' => ucfirst($metal) . ' price update is temporarily unavailable.',
                    'payload_json' => $response->json(),
                ];
            }

            $data = $response->json();
            $price = $this->extractMetalValue($data, $metal);

            if ($price === null) {
                return [
                    'title' => ucfirst($metal) . ' Price',
                    'subtitle' => 'Finance update',
                    'body' => ucfirst($metal) . ' price response received, but price value was not found.',
                    'payload_json' => $data,
                ];
            }

            $formatted = number_format((float) $price, 2);

            return [
                'title' => ucfirst($metal) . ' Price',
                'subtitle' => 'INR per gram',
                'body' => ucfirst($metal) . ": ₹{$formatted}/g",
                'payload_json' => $data,
            ];
        } catch (\Throwable $e) {
            return [
                'title' => ucfirst($metal) . ' Price',
                'subtitle' => 'Finance update',
                'body' => ucfirst($metal) . ' price update is temporarily unavailable.',
                'payload_json' => ['error' => $e->getMessage()],
            ];
        }
    }

    private function extractMetalValue(array $data, string $metal): mixed
    {
        $symbols = $metal === 'gold'
            ? ['gold', 'Gold', 'XAU', 'xau']
            : ['silver', 'Silver', 'XAG', 'xag'];

        $paths = [];

        foreach ($symbols as $symbol) {
            $paths[] = "metals.{$symbol}";
            $paths[] = "rates.{$symbol}";
            $paths[] = "data.{$symbol}";
            $paths[] = $symbol;
        }

        foreach ($paths as $path) {
            $value = data_get($data, $path);

            if (is_numeric($value)) {
                return $value;
            }
        }

        return null;
    }

    private function getFuelPrice(string $fuel): array
    {
        $apiKey = env('RAPIDAPI_KEY');
        $host = env('RAPIDAPI_HOST', 'fuel-petrol-diesel-live-price-india.p.rapidapi.com');
        $city = env('FUEL_CITY', 'Chennai');

        if (!$apiKey) {
            return [
                'title' => ucfirst($fuel) . ' Price',
                'subtitle' => 'Fuel update',
                'body' => 'RapidAPI key is not configured.',
                'payload_json' => null,
            ];
        }

        try {
            $endpoint = $fuel === 'diesel'
                ? 'diesel_price_india_city_value'
                : 'petrol_price_india_city_value';

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'city' => $city,
                'x-rapidapi-host' => $host,
                'x-rapidapi-key' => $apiKey,
            ])->connectTimeout(3)->timeout(5)->get("https://{$host}/{$endpoint}/");

            if (!$response->successful()) {
                return [
                    'title' => ucfirst($fuel) . ' Price',
                    'subtitle' => $city,
                    'body' => ucfirst($fuel) . ' price update is temporarily unavailable.',
                    'payload_json' => $response->json(),
                ];
            }

            $data = $response->json();
            $price = $this->extractFuelValue($data, $city);
            $currency = $data['Currency'] ?? 'INR';
            $unit = $data['Unit'] ?? '1 Litre';
            $date = $data['Price_Date(Today)'] ?? $data['Requested_Date'] ?? now('Asia/Kolkata')->toDateString();

            if ($price === null) {
                return [
                    'title' => ucfirst($fuel) . ' Price',
                    'subtitle' => $city,
                    'body' => ucfirst($fuel) . ' price response received, but price value was not found.',
                    'payload_json' => $data,
                ];
            }

            return [
                'title' => ucfirst($fuel) . ' Price',
                'subtitle' => "{$city} • {$date}",
                'body' => ucfirst($fuel) . ": ₹" . number_format((float) $price, 2) . "/L",
                'payload_json' => $data + [
                    '_currency' => $currency,
                    '_unit' => $unit,
                    '_city' => $city,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'title' => ucfirst($fuel) . ' Price',
                'subtitle' => 'Fuel update',
                'body' => ucfirst($fuel) . ' price update is temporarily unavailable.',
                'payload_json' => ['error' => $e->getMessage()],
            ];
        }
    }

    private function extractFuelValue(array $data, string $city): mixed
    {
        $direct = data_get($data, $city);

        if (is_numeric($direct)) {
            return $direct;
        }

        foreach ($data as $key => $value) {
            if (is_numeric($value) && !in_array($key, ['Status_code'], true)) {
                return $value;
            }
        }

        return null;
    }

    private function activePanchangItem(array $items): ?array
    {
        if (empty($items)) {
            return null;
        }

        $now = Carbon::now('Asia/Kolkata');

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $start = $item['start'] ?? null;
            $end = $item['end'] ?? null;

            if (!$start || !$end) {
                continue;
            }

            try {
                $startTime = Carbon::parse($start)->timezone('Asia/Kolkata');
                $endTime = Carbon::parse($end)->timezone('Asia/Kolkata');

                if ($now->betweenIncluded($startTime, $endTime)) {
                    return $item;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $items[0] ?? null;
    }

    private function nextPanchangItem(
        array $items,
        ?array $currentItem,
    ): ?array {
        if (!$currentItem || empty($items)) {
            return null;
        }

        foreach ($items as $index => $item) {
            if ($item === $currentItem) {
                return $items[$index + 1] ?? null;
            }
        }

        return null;
    }



    private function firstPanchangValue(
        array $data,
        array $paths
    ): mixed {
        foreach ($paths as $path) {
            $value = data_get($data, $path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function formatPanchangTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            $value = $value['datetime']
                ?? $value['time']
                ?? $value['start']
                ?? $value['end']
                ?? null;
        }

        if (!$value || !is_scalar($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)
                ->timezone('Asia/Kolkata')
                ->format('h:i A');
        } catch (\Throwable $e) {
            return trim((string) $value);
        }
    }

    private function extractPanchangPeriod(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }

        if (!is_array($value)) {
            return null;
        }

        /*
     * Some APIs return a list containing the first period.
     */
        if (array_is_list($value) && isset($value[0])) {
            $value = $value[0];

            if (!is_array($value)) {
                return trim((string) $value);
            }
        }

        $start = $value['start']
            ?? $value['start_time']
            ?? $value['from']
            ?? null;

        $end = $value['end']
            ?? $value['end_time']
            ?? $value['to']
            ?? null;

        $formattedStart = $this->formatPanchangTime($start);
        $formattedEnd = $this->formatPanchangTime($end);

        if ($formattedStart && $formattedEnd) {
            return "{$formattedStart} – {$formattedEnd}";
        }

        return $formattedStart
            ?? $formattedEnd
            ?? ($value['name'] ?? null);
    }

    private function fallback(string $key): array
    {
        return [
            'title' => ucfirst(str_replace('_', ' ', $key)),
            'subtitle' => 'MyDay update',
            'body' => 'This feed item will be connected soon.',
            'payload_json' => null,
        ];
    }

    private function fallbackWeather(): array
    {
        return [
            'title' => 'Today Weather',
            'subtitle' => 'Nandyal',
            'body' => 'Weather update is temporarily unavailable.',
            'payload_json' => [
                '_image_variant' => 'default',
            ],
        ];
    }

    private function weatherCondition($code): string
    {
        return match ((int) $code) {
            0 => 'Clear',
            1, 2, 3 => 'Partly cloudy',
            45, 48 => 'Fog',
            51, 53, 55 => 'Drizzle',
            61, 63, 65 => 'Rain',
            80, 81, 82 => 'Showers',
            95, 96, 99 => 'Thunderstorm',
            default => 'Weather update',
        };
    }
}
