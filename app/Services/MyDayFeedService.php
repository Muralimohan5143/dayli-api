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
        $latitude = (float) env('MYDAY_LATITUDE', 15.4777);
        $longitude = (float) env('MYDAY_LONGITUDE', 78.4836);
        $city = env('MYDAY_CITY', 'Nandyal');

        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'timezone' => 'Asia/Kolkata',
                    'forecast_days' => 7,
                    'current' => implode(',', [
                        'temperature_2m',
                        'apparent_temperature',
                        'relative_humidity_2m',
                        'is_day',
                        'precipitation',
                        'rain',
                        'weather_code',
                        'cloud_cover',
                        'pressure_msl',
                        'surface_pressure',
                        'wind_speed_10m',
                        'wind_direction_10m',
                        'wind_gusts_10m',
                    ]),
                    'hourly' => implode(',', [
                        'temperature_2m',
                        'apparent_temperature',
                        'precipitation_probability',
                        'precipitation',
                        'weather_code',
                        'relative_humidity_2m',
                        'wind_speed_10m',
                        'uv_index',
                        'visibility',
                    ]),
                    'daily' => implode(',', [
                        'weather_code',
                        'temperature_2m_max',
                        'temperature_2m_min',
                        'apparent_temperature_max',
                        'apparent_temperature_min',
                        'sunrise',
                        'sunset',
                        'uv_index_max',
                        'precipitation_sum',
                        'rain_sum',
                        'precipitation_probability_max',
                        'wind_speed_10m_max',
                        'wind_gusts_10m_max',
                    ]),
                ]);

            if (!$response->successful()) {
                return $this->fallbackWeather();
            }

            $data = $response->json();
            $current = is_array($data['current'] ?? null) ? $data['current'] : [];
            $hourly = is_array($data['hourly'] ?? null) ? $data['hourly'] : [];
            $daily = is_array($data['daily'] ?? null) ? $data['daily'] : [];

            $currentTime = $current['time'] ?? null;
            $currentHourIndex = 0;
            if ($currentTime && !empty($hourly['time']) && is_array($hourly['time'])) {
                $exactIndex = array_search($currentTime, $hourly['time'], true);
                if ($exactIndex !== false) $currentHourIndex = (int) $exactIndex;
            }

            $temperature = $current['temperature_2m'] ?? null;
            $feelsLike = $current['apparent_temperature'] ?? null;
            $humidity = $current['relative_humidity_2m'] ?? null;
            $weatherCode = $current['weather_code'] ?? null;
            $condition = $this->weatherCondition($weatherCode);
            $windSpeed = $current['wind_speed_10m'] ?? null;
            $windDirection = $current['wind_direction_10m'] ?? null;
            $pressure = $current['pressure_msl'] ?? $current['surface_pressure'] ?? null;
            $visibility = $hourly['visibility'][$currentHourIndex] ?? null;
            $uvIndex = $hourly['uv_index'][$currentHourIndex] ?? ($daily['uv_index_max'][0] ?? null);
            $rainChance = $hourly['precipitation_probability'][$currentHourIndex]
                ?? ($daily['precipitation_probability_max'][0] ?? null);

            $hourlyForecast = [];
            $hourlyTimes = is_array($hourly['time'] ?? null) ? $hourly['time'] : [];
            $hourlyEnd = min(count($hourlyTimes), $currentHourIndex + 24);
            for ($i = $currentHourIndex; $i < $hourlyEnd; $i++) {
                $time = $hourlyTimes[$i] ?? null;
                if (!$time) continue;
                $hourlyForecast[] = array_filter([
                    'time' => Carbon::parse($time, 'Asia/Kolkata')->format('h A'),
                    'datetime' => $time,
                    'temperature' => $hourly['temperature_2m'][$i] ?? null,
                    'feels_like' => $hourly['apparent_temperature'][$i] ?? null,
                    'condition' => $this->weatherCondition($hourly['weather_code'][$i] ?? null),
                    'rain_chance' => $hourly['precipitation_probability'][$i] ?? null,
                    'precipitation' => $hourly['precipitation'][$i] ?? null,
                    'humidity' => $hourly['relative_humidity_2m'][$i] ?? null,
                    'wind_speed' => $hourly['wind_speed_10m'][$i] ?? null,
                    'uv_index' => $hourly['uv_index'][$i] ?? null,
                ], fn($value) => $value !== null && $value !== '');
            }

            $dailyForecast = [];
            $dailyTimes = is_array($daily['time'] ?? null) ? $daily['time'] : [];
            foreach ($dailyTimes as $i => $date) {
                $dailyForecast[] = array_filter([
                    'date' => $date,
                    'day' => Carbon::parse($date, 'Asia/Kolkata')->format('D'),
                    'date_text' => Carbon::parse($date, 'Asia/Kolkata')->format('d M'),
                    'condition' => $this->weatherCondition($daily['weather_code'][$i] ?? null),
                    'max_temperature' => $daily['temperature_2m_max'][$i] ?? null,
                    'min_temperature' => $daily['temperature_2m_min'][$i] ?? null,
                    'max_feels_like' => $daily['apparent_temperature_max'][$i] ?? null,
                    'min_feels_like' => $daily['apparent_temperature_min'][$i] ?? null,
                    'sunrise' => $this->formatWeatherTime($daily['sunrise'][$i] ?? null),
                    'sunset' => $this->formatWeatherTime($daily['sunset'][$i] ?? null),
                    'uv_index' => $daily['uv_index_max'][$i] ?? null,
                    'rain_chance' => $daily['precipitation_probability_max'][$i] ?? null,
                    'precipitation' => $daily['precipitation_sum'][$i] ?? null,
                    'rain' => $daily['rain_sum'][$i] ?? null,
                    'max_wind_speed' => $daily['wind_speed_10m_max'][$i] ?? null,
                    'max_wind_gust' => $daily['wind_gusts_10m_max'][$i] ?? null,
                ], fn($value) => $value !== null && $value !== '');
            }

            $today = $dailyForecast[0] ?? [];
            $tomorrow = $dailyForecast[1] ?? null;
            $advice = $this->weatherAdvice(
                condition: $condition,
                temperature: $temperature,
                feelsLike: $feelsLike,
                humidity: $humidity,
                uvIndex: $uvIndex,
                rainChance: $rainChance,
                windSpeed: $windSpeed,
            );

            $body = implode(' • ', array_filter([
                $temperature !== null ? "{$temperature}°C" : null,
                $condition,
                isset($today['max_temperature'], $today['min_temperature'])
                    ? "H: {$today['max_temperature']}° L: {$today['min_temperature']}°"
                    : null,
                $rainChance !== null ? "Rain {$rainChance}%" : null,
            ]));

            $payload = array_filter([
                'date' => now('Asia/Kolkata')->format('l, d F Y'),
                'location' => $city,
                'updated_at' => $currentTime,
                'condition' => $condition,
                'weather_code' => $weatherCode,
                'temperature' => $temperature,
                'feels_like' => $feelsLike,
                'humidity' => $humidity,
                'cloud_cover' => $current['cloud_cover'] ?? null,
                'pressure' => $pressure,
                'visibility' => $visibility,
                'uv_index' => $uvIndex,
                'rain_chance' => $rainChance,
                'precipitation' => $current['precipitation'] ?? null,
                'rain' => $current['rain'] ?? null,
                'wind_speed' => $windSpeed,
                'wind_direction' => $windDirection,
                'wind_direction_text' => $this->windDirectionText($windDirection),
                'wind_gusts' => $current['wind_gusts_10m'] ?? null,
                'is_day' => $current['is_day'] ?? null,
                'sunrise' => $today['sunrise'] ?? null,
                'sunset' => $today['sunset'] ?? null,
                'today' => $today,
                'tomorrow' => $tomorrow,
                'hourly_forecast' => $hourlyForecast,
                'daily_forecast' => $dailyForecast,
                'weather_advice' => $advice,
                '_image_variant' => $condition,
            ], fn($value) => $value !== null && $value !== '');

            return [
                'title' => 'Today Weather',
                'subtitle' => $city,
                'body' => $body,
                'payload_json' => $payload,
            ];
        } catch (\Throwable $e) {
            report($e);
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
        $quotes = [
            [
                'quote' => 'The future depends on what you do today.',
                'author' => 'Mahatma Gandhi',
                'about_author' => 'Mahatma Gandhi was a leader of India’s freedom movement and an advocate of non-violence.',
                'explanation' => 'Large results are built from small actions repeated consistently. Today’s effort shapes tomorrow’s opportunities.',
                'reflection' => 'What is one useful action you can complete today instead of postponing it?',
            ],
            [
                'quote' => 'Success is the sum of small efforts, repeated day in and day out.',
                'author' => 'Robert Collier',
                'about_author' => 'Robert Collier was an American author known for practical writing on motivation and personal development.',
                'explanation' => 'Progress rarely comes from one dramatic event. It comes from steady habits and regular effort.',
                'reflection' => 'Choose one small habit you can repeat today without fail.',
            ],
            [
                'quote' => 'Do what you can, with what you have, where you are.',
                'author' => 'Theodore Roosevelt',
                'about_author' => 'Theodore Roosevelt was the 26th president of the United States and a writer on leadership and public life.',
                'explanation' => 'Waiting for perfect conditions delays progress. Begin with the resources and opportunities already available.',
                'reflection' => 'Which task can you start immediately using what you already have?',
            ],
        ];

        $item = $quotes[now('Asia/Kolkata')->dayOfYear % count($quotes)];

        return [
            'title' => 'Quote of the Day',
            'subtitle' => $item['author'],
            'body' => $item['quote'],
            'payload_json' => $item + [
                'date' => now('Asia/Kolkata')->format('l, d F Y'),
                'source' => 'Dayli local collection',
            ],
        ];
    }

    public function getGitaVerse(): array
    {
        $verses = [
            [
                'chapter' => 2,
                'verse_number' => 47,
                'reference' => 'Bhagavad Gita 2.47',
                'sanskrit' => 'कर्मण्येवाधिकारस्ते मा फलेषु कदाचन।
मा कर्मफलहेतुर्भूर्मा ते सङ्गोऽस्त्वकर्मणि॥',
                'transliteration' => 'Karmanye vadhikaraste ma phaleshu kadachana, ma karma-phala-hetur bhur ma te sango’stvakarmani.',
                'meaning' => 'You have a right to perform your duty, but never to the fruits of action. Do not act only for reward, and do not become attached to inaction.',
                'explanation' => 'The verse asks us to focus on sincere effort and disciplined action. Results depend on many factors, but our preparation, honesty and consistency remain within our control.',
                'life_lesson' => 'Give full attention to the work in front of you without allowing anxiety about the outcome to weaken your effort.',
                'todays_practice' => 'Choose one important task. Work on it for thirty focused minutes without checking the result, notifications or praise.',
            ],
            [
                'chapter' => 6,
                'verse_number' => 5,
                'reference' => 'Bhagavad Gita 6.5',
                'sanskrit' => 'उद्धरेदात्मनात्मानं नात्मानमवसादयेत्।
आत्मैव ह्यात्मनो बन्धुरात्मैव रिपुरात्मनः॥',
                'transliteration' => 'Uddhared atmanatmanam natmanam avasadayet, atmaiva hy atmano bandhur atmaiva ripur atmanah.',
                'meaning' => 'Lift yourself through your own mind and do not degrade yourself. The mind can be your friend, and the mind can also be your enemy.',
                'explanation' => 'Our inner habits influence how we respond to difficulty. A trained mind supports discipline and clarity, while an uncontrolled mind can strengthen fear and avoidance.',
                'life_lesson' => 'Speak to yourself with firmness and compassion. Your inner voice should guide you, not defeat you.',
                'todays_practice' => 'Whenever a negative thought appears, replace it with one realistic and constructive next step.',
            ],
            [
                'chapter' => 4,
                'verse_number' => 7,
                'reference' => 'Bhagavad Gita 4.7',
                'sanskrit' => 'यदा यदा हि धर्मस्य ग्लानिर्भवति भारत।
अभ्युत्थानमधर्मस्य तदात्मानं सृजाम्यहम्॥',
                'transliteration' => 'Yada yada hi dharmasya glanir bhavati Bharata, abhyutthanam adharmasya tadatmanam srijamy aham.',
                'meaning' => 'Whenever righteousness declines and unrighteousness rises, I manifest Myself.',
                'explanation' => 'The verse expresses the restoration of balance when disorder grows. It also reminds individuals to protect truth, fairness and responsibility in their own sphere.',
                'life_lesson' => 'Do not wait for others to correct every wrong. Practice integrity in the decisions that are yours to make.',
                'todays_practice' => 'Correct one small unfairness, neglected responsibility or dishonest shortcut in your day.',
            ],
        ];

        $verse = $verses[now('Asia/Kolkata')->dayOfYear % count($verses)];

        return [
            'title' => 'Gita Verse',
            'subtitle' => $verse['reference'],
            'body' => $verse['meaning'],
            'payload_json' => $verse + [
                'date' => now('Asia/Kolkata')->format('l, d F Y'),
                'source' => 'Dayli local Gita collection',
            ],
        ];
    }

    public function getStory(): array
    {
        $stories = [
            [
                'title' => 'The Two Seeds',
                'reading_time' => '2 min',
                'story' => 'Two seeds rested in fertile soil. The first seed said, “I want to grow. I want to send my roots deep and my shoots toward the sun.” It accepted the darkness, pushed through the soil and became a healthy plant. The second seed feared the stones, insects, wind and rain. It decided to wait until conditions were completely safe. While it waited, a bird found it and ate it.',
                'moral' => 'Growth requires courage before certainty.',
                'life_lesson' => 'Perfect conditions rarely arrive. Thoughtful action is safer than endless delay.',
                'todays_action' => 'Begin one useful task you have been postponing because you do not feel completely ready.',
            ],
            [
                'title' => 'The Cracked Pot',
                'reading_time' => '3 min',
                'story' => 'A water bearer carried two pots each day. One pot was perfect, while the other had a crack and reached home only half full. The cracked pot felt ashamed. The bearer showed it a line of flowers growing along its side of the path. He had planted seeds there, knowing the leaking water would nourish them. What seemed like a flaw had quietly created beauty.',
                'moral' => 'A weakness can still become a source of value.',
                'life_lesson' => 'Do not judge yourself only by what is missing. Notice what your experience enables you to contribute.',
                'todays_action' => 'Use one limitation creatively instead of treating it only as a disadvantage.',
            ],
            [
                'title' => 'The Empty Cup',
                'reading_time' => '2 min',
                'story' => 'A learned visitor came to a teacher and spoke continuously about everything he knew. The teacher poured tea into the visitor’s cup and kept pouring after it was full. Tea spilled across the table. The visitor protested. The teacher replied, “Like this cup, you are full of your own conclusions. How can you learn until you make some space?”',
                'moral' => 'Learning begins with humility.',
                'life_lesson' => 'Experience is valuable, but certainty can prevent us from seeing new information.',
                'todays_action' => 'In one conversation today, listen fully before preparing your response.',
            ],
        ];

        $story = $stories[now('Asia/Kolkata')->dayOfYear % count($stories)];

        return [
            'title' => 'Daily Story',
            'subtitle' => $story['title'],
            'body' => $story['moral'],
            'payload_json' => $story + [
                'date' => now('Asia/Kolkata')->format('l, d F Y'),
                'source' => 'Dayli local story collection',
            ],
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
            $response = Http::connectTimeout(3)->timeout(8)->get(
                'https://api.themoviedb.org/3/movie/now_playing',
                [
                    'api_key' => $apiKey,
                    'language' => 'en-IN',
                    'region' => 'IN',
                    'page' => 1,
                ]
            );

            if (!$response->successful()) {
                return [
                    'title' => 'Movies & OTT',
                    'subtitle' => 'New releases',
                    'body' => 'Movie updates are temporarily unavailable.',
                    'payload_json' => null,
                ];
            }

            $data = $response->json();
            $movies = collect($data['results'] ?? [])
                ->take(6)
                ->map(function (array $movie) {
                    $posterPath = $movie['poster_path'] ?? null;
                    $backdropPath = $movie['backdrop_path'] ?? null;

                    return array_filter([
                        'id' => $movie['id'] ?? null,
                        'title' => $movie['title']
                            ?? $movie['original_title']
                            ?? 'Untitled movie',
                        'original_title' => $movie['original_title'] ?? null,
                        'overview' => $movie['overview'] ?? null,
                        'release_date' => $movie['release_date'] ?? null,
                        'rating' => $movie['vote_average'] ?? null,
                        'vote_count' => $movie['vote_count'] ?? null,
                        'popularity' => $movie['popularity'] ?? null,
                        'language' => strtoupper((string) ($movie['original_language'] ?? '')),
                        'poster_url' => $posterPath
                            ? "https://image.tmdb.org/t/p/w500{$posterPath}"
                            : null,
                        'backdrop_url' => $backdropPath
                            ? "https://image.tmdb.org/t/p/w780{$backdropPath}"
                            : null,
                        'adult' => $movie['adult'] ?? false,
                    ], fn($value) => $value !== null && $value !== '');
                })
                ->values()
                ->all();

            if (empty($movies)) {
                return [
                    'title' => 'Movies & OTT',
                    'subtitle' => 'New releases',
                    'body' => 'No new movie releases found right now.',
                    'payload_json' => null,
                ];
            }

            $featured = $movies[0];
            $body = collect($movies)
                ->take(3)
                ->map(fn(array $movie) => '• ' . $movie['title'])
                ->implode("\n");

            return [
                'title' => 'Movies & OTT',
                'subtitle' => 'Now playing in India',
                'body' => $body,
                'payload_json' => [
                    'date' => now('Asia/Kolkata')->format('l, d F Y'),
                    'region' => 'India',
                    'featured_movie' => $featured,
                    'movies' => $movies,
                    'total_results' => $data['total_results'] ?? count($movies),
                    'source' => 'TMDB',
                ],
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'title' => 'Movies & OTT',
                'subtitle' => 'New releases',
                'body' => 'Movie updates are temporarily unavailable.',
                'payload_json' => null,
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
            $response = Http::connectTimeout(3)->timeout(8)->get(
                'https://ws.audioscrobbler.com/2.0/',
                [
                    'method' => 'geo.gettoptracks',
                    'country' => 'India',
                    'api_key' => $apiKey,
                    'format' => 'json',
                    'limit' => 8,
                ]
            );

            if (!$response->successful()) {
                return [
                    'title' => 'New Music',
                    'subtitle' => 'Trending tracks',
                    'body' => 'Music updates are temporarily unavailable.',
                    'payload_json' => null,
                ];
            }

            $data = $response->json();
            $tracks = collect(data_get($data, 'tracks.track', []))
                ->take(8)
                ->map(function (array $track) {
                    $images = is_array($track['image'] ?? null)
                        ? $track['image']
                        : [];
                    $imageUrl = collect($images)
                        ->pluck('#text')
                        ->filter()
                        ->last();

                    return array_filter([
                        'name' => $track['name'] ?? null,
                        'artist' => data_get($track, 'artist.name'),
                        'listeners' => isset($track['listeners'])
                            ? (int) $track['listeners']
                            : null,
                        'rank' => data_get($track, '@attr.rank'),
                        'url' => $track['url'] ?? null,
                        'image_url' => $imageUrl ?: null,
                        'streamable' => data_get($track, 'streamable.fulltrack')
                            ?? data_get($track, 'streamable.#text'),
                    ], fn($value) => $value !== null && $value !== '');
                })
                ->filter(fn(array $track) => !empty($track['name']))
                ->values()
                ->all();

            if (empty($tracks)) {
                return [
                    'title' => 'New Music',
                    'subtitle' => 'Trending tracks',
                    'body' => 'No trending tracks found right now.',
                    'payload_json' => null,
                ];
            }

            $featured = $tracks[0];
            $body = collect($tracks)
                ->take(3)
                ->map(function (array $track) {
                    $artist = $track['artist'] ?? 'Unknown artist';
                    return '• ' . $track['name'] . ' — ' . $artist;
                })
                ->implode("\n");

            return [
                'title' => 'New Music',
                'subtitle' => 'Trending in India',
                'body' => $body,
                'payload_json' => [
                    'date' => now('Asia/Kolkata')->format('l, d F Y'),
                    'country' => 'India',
                    'featured_track' => $featured,
                    'tracks' => $tracks,
                    'source' => 'Last.fm',
                ],
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'title' => 'New Music',
                'subtitle' => 'Trending tracks',
                'body' => 'Music updates are temporarily unavailable.',
                'payload_json' => null,
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

        try {
            $response = Http::connectTimeout(3)->timeout(8)->get(
                'https://gnews.io/api/v4/top-headlines',
                [
                    'category' => 'general',
                    'lang' => 'en',
                    'country' => 'in',
                    'max' => 8,
                    'apikey' => $apiKey,
                ]
            );

            if (!$response->successful()) {
                return [
                    'title' => 'Today News',
                    'subtitle' => 'Top headlines',
                    'body' => 'News update is temporarily unavailable.',
                    'payload_json' => null,
                ];
            }

            $data = $response->json();
            $articles = collect($data['articles'] ?? [])
                ->take(8)
                ->map(function (array $article) {
                    return array_filter([
                        'title' => $article['title'] ?? null,
                        'description' => $article['description'] ?? null,
                        'content' => $article['content'] ?? null,
                        'url' => $article['url'] ?? null,
                        'image_url' => $article['image'] ?? null,
                        'published_at' => $this->formatNewsDate(
                            $article['publishedAt'] ?? null
                        ),
                        'published_at_raw' => $article['publishedAt'] ?? null,
                        'source_name' => data_get($article, 'source.name'),
                        'source_url' => data_get($article, 'source.url'),
                    ], fn($value) => $value !== null && $value !== '');
                })
                ->filter(fn(array $article) => !empty($article['title']))
                ->values()
                ->all();

            if (empty($articles)) {
                return [
                    'title' => 'Today News',
                    'subtitle' => 'Top headlines',
                    'body' => 'No major headlines found right now.',
                    'payload_json' => null,
                ];
            }

            $featured = $articles[0];
            $body = collect($articles)
                ->take(3)
                ->map(fn(array $article) => '• ' . $article['title'])
                ->implode("\n");

            return [
                'title' => 'Today News',
                'subtitle' => 'India headlines',
                'body' => $body,
                'payload_json' => [
                    'date' => now('Asia/Kolkata')->format('l, d F Y'),
                    'country' => 'India',
                    'category' => 'General',
                    'featured_article' => $featured,
                    'articles' => $articles,
                    'total_articles' => $data['totalArticles'] ?? count($articles),
                    'source' => 'GNews',
                ],
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'title' => 'Today News',
                'subtitle' => 'Top headlines',
                'body' => 'News update is temporarily unavailable.',
                'payload_json' => null,
            ];
        }
    }

    public function getCricket(): array
    {
        $apiKey = env('CRICKET_API_KEY');
        if (!$apiKey) return ['title' => 'Cricket', 'subtitle' => 'Match updates', 'body' => 'Cricket API key is not configured.', 'payload_json' => null];
        try {
            $response = Http::connectTimeout(3)->timeout(8)->get('https://api.cricapi.com/v1/currentMatches', ['apikey' => $apiKey, 'offset' => 0]);
            if (!$response->successful()) return ['title' => 'Cricket', 'subtitle' => 'Match updates', 'body' => 'Cricket updates are temporarily unavailable.', 'payload_json' => null];
            $data = $response->json();
            $matches = collect($data['data'] ?? [])->take(5)->map(function ($match) {
                $teams = is_array($match['teams'] ?? null) ? $match['teams'] : [];
                $scores = collect($match['score'] ?? [])->map(fn($score) => array_filter([
                    'inning' => $score['inning'] ?? null,
                    'runs' => $score['r'] ?? null,
                    'wickets' => $score['w'] ?? null,
                    'overs' => $score['o'] ?? null,
                ], fn($v) => $v !== null && $v !== ''))->values()->all();
                return array_filter([
                    'id' => $match['id'] ?? null,
                    'name' => $match['name'] ?? (!empty($teams) ? implode(' vs ', $teams) : null),
                    'match_type' => $match['matchType'] ?? null,
                    'status' => $match['status'] ?? null,
                    'venue' => $match['venue'] ?? null,
                    'date' => $match['date'] ?? null,
                    'date_time_gmt' => $match['dateTimeGMT'] ?? null,
                    'teams' => $teams,
                    'team_info' => $match['teamInfo'] ?? [],
                    'scores' => $scores,
                    'match_started' => $match['matchStarted'] ?? null,
                    'match_ended' => $match['matchEnded'] ?? null,
                ], fn($v) => $v !== null && $v !== '' && $v !== []);
            })->values()->all();
            if (empty($matches)) return ['title' => 'Cricket', 'subtitle' => 'Match updates', 'body' => 'No current cricket matches found.', 'payload_json' => ['matches' => []]];
            $featured = $matches[0];
            $scoreText = collect($featured['scores'] ?? [])->map(function ($score) {
                $line = $score['inning'] ?? '';
                if (isset($score['runs'])) $line .= ': ' . $score['runs'] . (isset($score['wickets']) ? '/' . $score['wickets'] : '');
                if (isset($score['overs'])) $line .= ' (' . $score['overs'] . ' ov)';
                return trim($line);
            })->filter()->implode("
");
            return [
                'title' => 'Cricket',
                'subtitle' => $featured['status'] ?? 'Live / current matches',
                'body' => trim(($featured['name'] ?? 'Cricket match') . ($scoreText ? "
{$scoreText}" : '')),
                'payload_json' => [
                    'featured_match' => $featured,
                    'matches' => $matches,
                    'match_count' => count($matches),
                    'updated_at' => now('Asia/Kolkata')->toIso8601String(),
                    'source' => 'CricAPI'
                ],
            ];
        } catch (\Throwable $e) {
            report($e);
            return ['title' => 'Cricket', 'subtitle' => 'Match updates', 'body' => 'Cricket updates are temporarily unavailable.', 'payload_json' => null];
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
            [
                'tip' => 'Take a gentle 10–15 minute walk after a meal.',
                'benefits' => [
                    'Supports digestion and reduces post-meal sluggishness.',
                    'Helps the body use circulating glucose more efficiently.',
                    'Adds light daily movement without requiring a full workout.',
                ],
                'science' => 'Light muscular activity after eating increases glucose uptake and may reduce the size of the post-meal blood-sugar rise.',
                'what_to_do' => [
                    'Wait a few minutes after eating.',
                    'Walk at a comfortable pace for 10–15 minutes.',
                    'Keep the pace easy enough to speak normally.',
                ],
                'foods' => 'Prefer balanced meals containing vegetables, protein and moderate portions of carbohydrates.',
                'exercise' => 'This walk complements regular exercise; it does not replace strength, mobility and aerobic activity.',
                'hydration' => 'Drink water according to thirst and weather conditions. Avoid forcing excessive water immediately after a large meal.',
                'sleep' => 'For evening meals, finish eating early enough to allow comfortable digestion before sleep.',
                'precautions' => 'People with chest pain, dizziness, severe breathlessness, unstable blood sugar, recent surgery or mobility limitations should follow medical advice.',
                'references' => 'General wellness guidance based on established physical-activity and diabetes-management principles.',
            ],
            [
                'tip' => 'Protect the final 30 minutes before sleep from bright screens and work messages.',
                'benefits' => [
                    'Creates a predictable transition from activity to rest.',
                    'May reduce mental stimulation and bedtime delay.',
                    'Supports a more consistent sleep routine.',
                ],
                'science' => 'Light exposure and stimulating content near bedtime can delay sleepiness and keep the brain alert when it should be winding down.',
                'what_to_do' => [
                    'Dim room lights.',
                    'Place the phone away from the bed.',
                    'Choose reading, prayer, gentle stretching or quiet music.',
                ],
                'foods' => 'Avoid heavy meals, excessive caffeine and large quantities of alcohol close to bedtime.',
                'exercise' => 'Gentle stretching is suitable; intense exercise may be better earlier for people who feel activated afterward.',
                'hydration' => 'Hydrate during the day and reduce large fluid intake just before bed if it causes repeated waking.',
                'sleep' => 'Keep a regular sleep and wake time, including weekends where practical.',
                'precautions' => 'Persistent insomnia, loud snoring, choking during sleep or severe daytime sleepiness requires professional assessment.',
                'references' => 'General sleep-hygiene guidance used in behavioural sleep medicine.',
            ],
            [
                'tip' => 'Use a water bottle or glass as a visual reminder to hydrate through the day.',
                'benefits' => [
                    'Supports temperature regulation and normal body function.',
                    'May prevent headaches and fatigue related to inadequate fluid intake.',
                    'Makes hydration easier to remember during busy work.',
                ],
                'science' => 'Fluid needs vary with body size, climate, activity, pregnancy, illness and diet. Thirst and urine colour are useful everyday signals for many healthy adults.',
                'what_to_do' => [
                    'Keep water visible and accessible.',
                    'Drink regularly, especially in hot weather or during activity.',
                    'Increase fluids when sweating more than usual.',
                ],
                'foods' => 'Fruits, vegetables, soups, curd and other water-rich foods also contribute to fluid intake.',
                'exercise' => 'Drink before, during and after prolonged exercise according to heat and sweat loss.',
                'hydration' => 'Pale-yellow urine commonly suggests adequate hydration, though medicines and supplements can change colour.',
                'sleep' => 'Spread fluids through the day rather than drinking most of them late at night.',
                'precautions' => 'People with heart failure, kidney disease, liver disease or prescribed fluid restrictions must follow their clinician’s advice.',
                'references' => 'General hydration guidance for healthy adults; individual medical needs may differ.',
            ],
        ];

        $tip = $tips[now('Asia/Kolkata')->dayOfYear % count($tips)];

        return [
            'title' => 'Health Tip',
            'subtitle' => 'Daily Wellness',
            'body' => $tip['tip'],
            'payload_json' => $tip + [
                'date' => now('Asia/Kolkata')->format('l, d F Y'),
                'disclaimer' => 'This is general educational information and is not a diagnosis or substitute for personal medical care.',
                'source' => 'Dayli local wellness collection',
            ],
        ];
    }

    public function getRecipe(): array
    {
        $apiKey = env('THEMEALDB_API_KEY', '1');

        try {
            $response = Http::connectTimeout(3)
                ->timeout(8)
                ->get("https://www.themealdb.com/api/json/v1/{$apiKey}/random.php");

            if (!$response->successful()) {
                return [
                    'title' => 'Recipe',
                    'subtitle' => 'Today special',
                    'body' => 'Recipe update is temporarily unavailable.',
                    'payload_json' => null,
                ];
            }

            $data = $response->json();
            $meal = $data['meals'][0] ?? null;

            if (!is_array($meal)) {
                return [
                    'title' => 'Recipe',
                    'subtitle' => 'Today special',
                    'body' => 'No recipe found right now.',
                    'payload_json' => null,
                ];
            }

            $ingredients = [];
            for ($i = 1; $i <= 20; $i++) {
                $ingredient = trim((string) ($meal["strIngredient{$i}"] ?? ''));
                $measure = trim((string) ($meal["strMeasure{$i}"] ?? ''));

                if ($ingredient === '') continue;

                $ingredients[] = [
                    'ingredient' => $ingredient,
                    'measurement' => $measure,
                    'display' => trim("{$measure} {$ingredient}"),
                ];
            }

            $instructions = trim((string) ($meal['strInstructions'] ?? ''));
            $steps = collect(preg_split('/(?:\r?\n)+|(?<=\.)\s+(?=[A-Z])/', $instructions))
                ->map(fn($step) => trim((string) $step))
                ->filter()
                ->values()
                ->all();

            $name = $meal['strMeal'] ?? 'Today Recipe';
            $category = $meal['strCategory'] ?? 'Recipe';
            $area = $meal['strArea'] ?? null;

            return [
                'title' => 'Recipe',
                'subtitle' => trim($category . ($area ? " • {$area}" : '')),
                'body' => $name . "\n" . collect($ingredients)
                    ->take(5)
                    ->pluck('display')
                    ->implode(', '),
                'payload_json' => array_filter([
                    'recipe_name' => $name,
                    'category' => $category,
                    'cuisine' => $area,
                    'image_url' => $meal['strMealThumb'] ?? null,
                    'ingredients' => $ingredients,
                    'ingredient_count' => count($ingredients),
                    'instructions' => $instructions,
                    'steps' => $steps,
                    'tags' => array_values(array_filter(array_map(
                        'trim',
                        explode(',', (string) ($meal['strTags'] ?? ''))
                    ))),
                    'video_url' => $meal['strYoutube'] ?? null,
                    'source_url' => $meal['strSource'] ?? null,
                    'mealdb_id' => $meal['idMeal'] ?? null,
                    'date' => now('Asia/Kolkata')->format('l, d F Y'),
                    'source' => 'TheMealDB',
                ], fn($value) => $value !== null && $value !== '' && $value !== []),
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'title' => 'Recipe',
                'subtitle' => 'Today special',
                'body' => 'Recipe update is temporarily unavailable.',
                'payload_json' => null,
            ];
        }
    }

    public function getFunFact(): array
    {
        $facts = [
            ['fact' => 'Honey can remain edible for extremely long periods when sealed and stored properly.', 'category' => 'Food science', 'explanation' => 'Honey contains very little available water, is acidic, and naturally resists many microorganisms.', 'did_you_know' => 'Archaeologists have found ancient honey preserved inside sealed containers.', 'learn_more' => 'Moisture and contamination can still spoil honey, so clean, dry storage matters.'],
            ['fact' => 'Bananas are botanical berries, while strawberries are not true berries.', 'category' => 'Botany', 'explanation' => 'Botanical classification depends on how a fruit develops from a flower and how its seeds are arranged.', 'did_you_know' => 'Tomatoes, grapes and kiwifruit are also botanical berries.', 'learn_more' => 'Common food names and botanical names often follow different rules.'],
            ['fact' => 'A single day on Venus lasts longer than one Venus year.', 'category' => 'Space', 'explanation' => 'Venus rotates very slowly on its axis but travels around the Sun faster than it completes one rotation.', 'did_you_know' => 'Venus also rotates in the opposite direction to most planets.', 'learn_more' => 'Its slow retrograde rotation makes sunrise and sunset unusual compared with Earth.'],
            ['fact' => 'Octopuses have three hearts and blue blood.', 'category' => 'Animals', 'explanation' => 'Two hearts pump blood through the gills, while the third circulates it through the rest of the body.', 'did_you_know' => 'Their blood is blue because it uses copper-rich hemocyanin.', 'learn_more' => 'This chemistry helps transport oxygen in cold, low-oxygen water.'],
        ];
        $fact = $facts[now('Asia/Kolkata')->dayOfYear % count($facts)];
        return ['title' => 'Fun Fact', 'subtitle' => $fact['category'], 'body' => $fact['fact'], 'payload_json' => $fact + ['date' => now('Asia/Kolkata')->format('l, d F Y'), 'source' => 'Dayli local knowledge collection']];
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
                'payload_json' => [
                    'metal' => $metal,
                    'current_price' => (float) $price,
                    'price' => (float) $price,
                    'currency' => 'INR',
                    'unit' => 'gram',
                    'formatted_price' => '₹' . $formatted . '/g',
                    'location' => env('METAL_CITY', 'India'),
                    'updated_at' => now('Asia/Kolkata')->toIso8601String(),
                    'trend_note' => 'Compare with jeweller rates before buying; taxes and making charges may differ.',
                    'raw' => $data,
                    'source' => 'Metals.dev',
                ],
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
                'payload_json' => [
                    'fuel' => $fuel,
                    'current_price' => (float) $price,
                    'price' => (float) $price,
                    'currency' => $currency,
                    'unit' => $unit,
                    'city' => $city,
                    'location' => $city,
                    'date' => $date,
                    'updated_at' => now('Asia/Kolkata')->toIso8601String(),
                    'nearby_cities' => [],
                    'trend_note' => 'Retail fuel prices can vary by city and may change when taxes or dealer rates are revised.',
                    'fuel_saving_tip' => 'Maintain correct tyre pressure and avoid sudden acceleration to improve fuel efficiency.',
                    'raw' => $data,
                    'source' => 'RapidAPI fuel price service',
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

    private function formatNewsDate(mixed $value): ?string
    {
        if (!$value) return null;

        try {
            return Carbon::parse((string) $value)
                ->timezone('Asia/Kolkata')
                ->format('d M Y, h:i A');
        } catch (\Throwable $e) {
            return trim((string) $value);
        }
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


    private function formatWeatherTime(mixed $value): ?string
    {
        if (!$value) return null;

        try {
            return Carbon::parse((string) $value, 'Asia/Kolkata')
                ->format('h:i A');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function windDirectionText(mixed $degrees): ?string
    {
        if (!is_numeric($degrees)) return null;

        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $index = (int) round(((float) $degrees) / 45) % 8;

        return $directions[$index];
    }

    private function weatherAdvice(
        string $condition,
        mixed $temperature,
        mixed $feelsLike,
        mixed $humidity,
        mixed $uvIndex,
        mixed $rainChance,
        mixed $windSpeed,
    ): array {
        $advice = [];

        if (is_numeric($rainChance) && (float) $rainChance >= 50) {
            $advice[] = 'Carry an umbrella or raincoat before going out.';
        }

        if (is_numeric($uvIndex) && (float) $uvIndex >= 6) {
            $advice[] = 'UV levels are high. Prefer shade and use sun protection.';
        }

        if (is_numeric($feelsLike) && (float) $feelsLike >= 36) {
            $advice[] = 'It may feel very hot. Hydrate well and avoid strenuous activity in the afternoon.';
        } elseif (is_numeric($temperature) && (float) $temperature <= 18) {
            $advice[] = 'The weather is cool. Carry a light layer if you are outdoors early or late.';
        }

        if (is_numeric($humidity) && (float) $humidity >= 75) {
            $advice[] = 'Humidity is high. Take breaks and drink water regularly.';
        }

        if (is_numeric($windSpeed) && (float) $windSpeed >= 35) {
            $advice[] = 'Strong winds are possible. Secure loose outdoor items.';
        }

        if (str_contains(strtolower($condition), 'thunder')) {
            $advice[] = 'Avoid open fields and isolated trees during thunderstorms.';
        }

        if (empty($advice)) {
            $advice[] = 'Conditions look comfortable for normal daily activities.';
        }

        return $advice;
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
