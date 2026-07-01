<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $usedCodes = [];

        DB::table('brands')
            ->select(['id', 'name', 'code'])
            ->orderBy('id')
            ->chunkById(100, function ($brands) use (&$usedCodes): void {
                foreach ($brands as $brand) {
                    $base = $this->cleanCode((string) ($brand->code ?: $brand->name));
                    $code = $this->uniqueCode($base, $usedCodes);

                    DB::table('brands')
                        ->where('id', $brand->id)
                        ->update(['code' => $code]);

                    $usedCodes[] = $code;
                }
            });
    }

    public function down(): void
    {
        //
    }

    private function cleanCode(string $code): string
    {
        $code = Str::of($code)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->substr(0, 4)
            ->toString();

        return $code !== '' ? $code : 'GEN';
    }

    private function uniqueCode(string $code, array $usedCodes): string
    {
        $candidate = $code;
        $suffix = 2;

        while (in_array($candidate, $usedCodes, true)) {
            $suffixText = (string) $suffix;
            $candidate = substr($code, 0, max(1, 4 - strlen($suffixText))).$suffixText;
            $suffix++;
        }

        return $candidate;
    }
};
