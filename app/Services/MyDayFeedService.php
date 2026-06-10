<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MyDayFeedService
{
    public function makeFeedItem(string $key): array
    {
        return match ($key) {
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
            'health' => $this->getHealthTip(),
            'recipe' => $this->getRecipe(),
            'fun_fact' => $this->getFunFact(),
            default => $this->fallback($key),
        };
    }

    public function getWeather(): array
    {
        try {
            $response = Http::timeout(8)->get('https://api.open-meteo.com/v1/forecast', [
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
                'payload_json' => $data,
            ];
        } catch (\Throwable $e) {
            return $this->fallbackWeather();
        }
    }

    public function getPanchang(): array
    {
        return [
            'title' => 'Today Panchang',
            'subtitle' => 'Good time for prayers',
            'body' => 'Panchang calculation/API will be connected soon.',
            'payload_json' => null,
        ];
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
        ])->get('https://api.dailyquotes.dev/api/quote');

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
        return [
            'title' => 'Movies & OTT',
            'subtitle' => 'New releases',
            'body' => 'TMDB movie and OTT updates will be connected soon.',
            'payload_json' => null,
        ];
    }

    public function getMusic(): array
    {
        return [
            'title' => 'New Music',
            'subtitle' => 'Fresh releases',
            'body' => 'Spotify music releases will be connected soon.',
            'payload_json' => null,
        ];
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

        $response = Http::get('https://gnews.io/api/v4/top-headlines', [
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
        return [
            'title' => 'Cricket',
            'subtitle' => 'Match updates',
            'body' => 'Cricket scores and upcoming matches will be connected soon.',
            'payload_json' => null,
        ];
    }

    public function getGoldPrice(): array
    {
        return [
            'title' => 'Gold Price',
            'subtitle' => 'Finance update',
            'body' => 'Gold price API will be connected soon.',
            'payload_json' => null,
        ];
    }

    public function getSilverPrice(): array
    {
        return [
            'title' => 'Silver Price',
            'subtitle' => 'Finance update',
            'body' => 'Silver price API will be connected soon.',
            'payload_json' => null,
        ];
    }

    public function getPetrolPrice(): array
    {
        return [
            'title' => 'Petrol Price',
            'subtitle' => 'Fuel update',
            'body' => 'Petrol price data will be connected soon.',
            'payload_json' => null,
        ];
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
        return [
            'title' => 'Recipe',
            'subtitle' => 'Today special',
            'body' => 'Simple daily recipe suggestions will appear here.',
            'payload_json' => ['source' => 'local'],
        ];
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
            'payload_json' => null,
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
