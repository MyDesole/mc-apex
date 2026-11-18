<?php

namespace App\Domains\Minecraft\Services;

use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MinecraftModesService
{
    public function store(array $payload): void
    {
        DB::transaction(function () use ($payload): void {
            foreach (['bedwars', 'fastbridge', 'duels'] as $mode) {
                if (! ($payload[$mode]['configured'] ?? false)) {
                    continue;
                }
                $table = DB::table('minecraft_mode_snapshots');
                $old = $table->where('mode', $mode)->lockForUpdate()->first();
                if ($old) {
                    $previous = json_decode($old->data, true, flags: JSON_THROW_ON_ERROR);
                    if (CarbonImmutable::parse($previous['captured_at'])->greaterThan(CarbonImmutable::parse($payload['captured_at']))) {
                        continue;
                    }
                }
                $data = $payload[$mode];
                $data['captured_at'] = $payload['captured_at'];
                DB::table('minecraft_mode_snapshots')->updateOrInsert(['mode' => $mode], ['data' => json_encode($data, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            }
        });
    }

    public function read(string $mode, array $filters, ?User $viewer): array
    {
        $row = DB::table('minecraft_mode_snapshots')->where('mode', $mode)->first();
        $empty = ['configured' => false, 'updated_at' => null, 'stale' => false, 'summary' => [], 'players' => [], 'arenas' => [], 'personal' => null, 'history' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0], 'tables' => []];
        if (! $row) {
            return $empty;
        }
        $data = json_decode($row->data, true, flags: JSON_THROW_ON_ERROR);
        $time = CarbonImmutable::parse($data['captured_at']);
        $out = array_replace($empty, ['configured' => true, 'updated_at' => $time->toIso8601String(), 'stale' => $time->lessThan(now()->subMinutes(3))]);
        $target = isset($filters['user']) ? User::query()->find($filters['user']) : null;
        $own = $target && $viewer && $target->id === $viewer->id;
        if ($mode === 'bedwars') {
            $all = collect($data['players'] ?? []);
            $out['summary'] = ['players' => $all->count(), 'beds' => $all->sum('beds'), 'final_kills' => $all->sum('final_kills'), 'arenas' => count($data['arenas'] ?? []), 'online' => $data['online'] ?? 0];
            $metric = $filters['metric'] ?? 'wins';
            $players = $all->sort(fn ($a, $b) => ($b[$metric] <=> $a[$metric]) ?: strcasecmp($a['name'], $b['name']))->values();
            $out['arenas'] = $data['arenas'] ?? [];
            $personal = $target ? $all->first(fn ($p) => $this->belongs($p, $target)) : null;
        } else {
            $table = $filters['table'] ?? 'current';
            $map = $filters['distance'] ?? '32';
            $all = collect($data['tables'][$table] ?? [])->where('map', $map)->values();
            $records = $all->filter(fn ($p) => $p['best'] >= 0);
            $out['summary'] = ['players' => $all->count(), 'records' => $records->count(), 'attempts24' => $all->sum('attempts24'), 'finishes24' => $all->sum('finishes24'), 'online' => $data['online'] ?? 0];
            $players = $records->sort(fn ($a, $b) => ($map === 'inf' ? ($b['best'] <=> $a['best']) : ($a['best'] <=> $b['best'])) ?: strcasecmp($a['name'], $b['name']))->values();
            $out['tables'] = collect(['current' => 'Новая карта · золотые плиты', 'islands' => 'Архив · Akina до плит', 'classic' => 'Архив · старые платформы'])->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'records' => collect($data['tables'][$key] ?? [])->filter(fn ($p) => $p['best'] >= 0)->count()])->values()->all();
            $personal = $target ? $all->first(fn ($p) => $this->belongs($p, $target)) : null;
            if ($own) {
                $uuids = collect($data['tables'] ?? [])->flatten(1)->filter(fn ($p) => is_array($p) && $this->belongs($p, $target))->pluck('uuid')->unique()->all();
                if ($target->minecraft_uuid) {
                    $uuids[] = $target->minecraft_uuid;
                }
                $out['history'] = collect($data['history'] ?? [])->filter(fn ($h) => ($h['public'] ?? false) && in_array($h['uuid'], $uuids, true) && $h['map'] === $map)->sortByDesc('at')->take(20)->map(fn ($h) => array_diff_key($h, array_flip(['uuid', 'public'])))->values()->all();
            }
        }
        $out['personal'] = $personal ? array_diff_key($personal, ['uuid' => true]) : null;
        $page = (int) ($filters['page'] ?? 1);
        $out['meta'] = ['current_page' => $page, 'last_page' => max(1, (int) ceil($players->count() / 20)), 'total' => $players->count()];
        $slice = $players->slice(($page - 1) * 20, 20)->values();
        $users = User::query()->whereIn(DB::raw('lower(minecraft_username)'), $slice->pluck('name')->map(fn ($name) => mb_strtolower($name)))->get(['id', 'minecraft_username'])->keyBy(fn ($u) => mb_strtolower($u->minecraft_username));
        $out['players'] = $slice->map(function ($p, $i) use ($users, $page) {
            unset($p['uuid']);
            $p['user_id'] = $users->get(mb_strtolower($p['name']))?->id;
            $p['place'] = ($page - 1) * 20 + $i + 1;

            return $p;
        })->all();

        return $out;
    }

    private function belongs(array $player, User $user): bool
    {
        return ($user->minecraft_uuid && strcasecmp($player['uuid'], $user->minecraft_uuid) === 0)
            || ($user->minecraft_username && strcasecmp($player['name'], $user->minecraft_username) === 0);
    }
}
