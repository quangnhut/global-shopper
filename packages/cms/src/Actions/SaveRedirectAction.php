<?php

declare(strict_types=1);

namespace Shopper\Cms\Actions;

use Illuminate\Validation\ValidationException;
use Shopper\Cms\Models\Redirect;

final class SaveRedirectAction
{
    /**
     * Create or update a redirect. Chains are collapsed (A→B + B→C = A→C) and loops are refused.
     * A `null` target with status 410 marks the path as gone.
     */
    public function execute(
        string $from,
        ?string $to,
        int $status = 301,
        string $source = Redirect::SOURCE_MANUAL,
        ?Redirect $redirect = null,
    ): Redirect {
        $from = (string) Redirect::normalize($from);
        $to = $status === 410 ? null : Redirect::target($to);

        if ($to !== null && Redirect::normalize($to) === $from) {
            throw ValidationException::withMessages(['to_path' => __('shopper-cms::permalinks.redirects.same')]);
        }

        // Point straight at the end of an existing chain.
        $to = $to === null ? null : $this->finalTarget($to, $from);

        $redirect ??= Redirect::query()->firstOrNew(['from_path' => $from]);
        $redirect->fill([
            'from_path' => $from,
            'to_path' => $to,
            'status_code' => $status,
            'source' => $redirect->exists ? $redirect->source : $source,
        ])->save();

        // Redirects that pointed at this path now skip it.
        Redirect::query()
            ->whereIn('to_path', [$from, $from.'/'])
            ->whereKeyNot($redirect->id)
            ->update(['to_path' => $to, 'status_code' => $to === null ? 410 : $status]);

        return $redirect;
    }

    /**
     * @throws ValidationException when following the chain comes back to `$from`
     */
    private function finalTarget(string $to, string $from): string
    {
        $seen = [$from => true];

        while (($next = Redirect::query()->where('from_path', Redirect::normalize($to))->first()) !== null && $next->to_path !== null) {
            if (isset($seen[Redirect::normalize($next->to_path)]) || isset($seen[Redirect::normalize($to)])) {
                throw ValidationException::withMessages(['to_path' => __('shopper-cms::permalinks.redirects.loop')]);
            }

            $seen[(string) Redirect::normalize($to)] = true;
            $to = $next->to_path;
        }

        if (Redirect::normalize($to) === $from) {
            throw ValidationException::withMessages(['to_path' => __('shopper-cms::permalinks.redirects.loop')]);
        }

        return $to;
    }
}
