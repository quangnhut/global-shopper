<?php

declare(strict_types=1);

namespace Shopper\Cms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Shopper\Core\Actions\StoreUploadAction;

/**
 * TinyMCE's `images_upload_handler` target: pasted, dropped and dialog images go to the Uploads library.
 */
final class EditorUploadController
{
    public function __invoke(Request $request, StoreUploadAction $storeUpload): JsonResponse
    {
        // A JSON 403, not abort(): the admin turns 403s under its prefix into a redirect to its
        // Forbidden page, which TinyMCE's upload handler cannot read.
        if (! shopper()->auth()->user()?->can('add_uploads')) {
            return response()->json(['message' => __('shopper::notifications.unauthorized.title')], 403);
        }

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimetypes:image/png,image/jpeg,image/webp,image/gif',
                'max:'.(int) config('shopper.media.uploads.max_size'),
            ],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        try {
            $upload = $storeUpload->execute($file, shopper()->auth()->id());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['location' => $upload->url]);
    }
}
