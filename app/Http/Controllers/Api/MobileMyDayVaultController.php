<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserVault;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MobileMyDayVaultController extends Controller
{
    private function defaultVault(): array
    {
        return [
            'people' => [],
            'important_dates' => [],
            'documents' => [
                'aadhaar' => new \stdClass(),
                'pan' => new \stdClass(),
                'passport' => new \stdClass(),
                'insurance' => new \stdClass(),
                'custom' => [],
            ],
            'custom_data' => [],
        ];
    }

    public function show(Request $request)
    {
        $vault = UserVault::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['vault_json' => $this->defaultVault()]
        );

        return response()->json([
            'success' => true,
            'vault' => $vault->vault_json,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'vault_json' => ['required', 'array'],
        ]);

        $vault = UserVault::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['vault_json' => $data['vault_json']]
        );

        return response()->json([
            'success' => true,
            'message' => 'Vault saved successfully.',
            'vault' => $vault->vault_json,
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10 MB
            'folder' => ['nullable', 'string'],
        ]);

        $userId = $request->user()->id;
        $folder = $request->input('folder', 'misc');

        $file = $request->file('file');

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs(
            "vaults/{$userId}/{$folder}",
            $filename,
            'public'
        );

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => asset('storage/' . $path),
        ]);
    }
}
