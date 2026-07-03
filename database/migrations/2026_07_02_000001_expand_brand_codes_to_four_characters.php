<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $usedCodes = [];
        $brandUpdates = [];

        $brands = DB::table('brands')
            ->select(['id', 'name', 'code'])
            ->orderBy('id')
            ->get();

        foreach ($brands as $brand) {
            $source = strlen((string) $brand->code) >= 4 ? (string) $brand->code : (string) $brand->name;
            $code = $this->uniqueCode($this->cleanCode($source), $usedCodes);

            $brandUpdates[] = ['id' => $brand->id, 'code' => $code];
            $usedCodes[] = $code;
        }

        foreach ($brandUpdates as $brandUpdate) {
            DB::table('brands')
                ->where('id', $brandUpdate['id'])
                ->update(['code' => '__TMP_BRAND_'.$brandUpdate['id']]);
        }

        foreach ($brandUpdates as $brandUpdate) {
            DB::table('brands')
                ->where('id', $brandUpdate['id'])
                ->update(['code' => $brandUpdate['code']]);
        }
    }

    public function down(): void
    {
        //
    }

    private function cleanCode(string $code): string
    {
        return Str::of($code ?: 'GENX')
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->substr(0, 4)
            ->padRight(4, 'X')
            ->toString();
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
