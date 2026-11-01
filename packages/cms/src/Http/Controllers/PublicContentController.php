<?php

declare(strict_types=1);

namespace Shopper\Cms\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Cms\Models\PostTag;
use Shopper\Cms\Permalinks\PermalinkGenerator;
use Shopper\Cms\Permalinks\PermalinkResolver;
use Shopper\Cms\Support\ContentVisibility;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional server-rendered pages for apps without a separate storefront (`shopper.cms.routes.enabled`).
 * Views live in `shopper-cms-public::` and can be published with `--tag=shopper-cms-views`.
 */
final class PublicContentController
{
    public function __invoke(Request $request, PermalinkResolver $resolver): View|RedirectResponse|Response
    {
        $resolved = $resolver->resolve($request->getRequestUri());

        abort_if($resolved === null, 404);

        if ($resolved->isRedirect()) {
            return redirect()->to((string) $resolved->redirectTo, $resolved->status);
        }

        abort_if($resolved->type === 'gone', 410);

        $model = $resolved->model;

        if ($model instanceof Post) {
            abort_unless(ContentVisibility::isPublic($model), 404);

            $model->load(['author', 'featuredImage', 'categories', 'tags']);

            if (! ContentVisibility::unlocks($model, $request->session()->get("cms-post-password.{$model->id}"))) {
                return view('shopper-cms-public::password', ['post' => $model, 'seoSubject' => $model]);
            }

            return view($model->postType()->hierarchical ? 'shopper-cms-public::page' : 'shopper-cms-public::post', [
                'post' => $model,
                'seoSubject' => $model,
            ]);
        }

        /** @var PostCategory|PostTag $model */
        $posts = $model->posts()->published()->with(['author', 'featuredImage'])->latest('published_at')->paginate(10);

        // Pages past the last one are empty duplicates.
        abort_if($posts->currentPage() > max(1, $posts->lastPage()), 404);

        return view('shopper-cms-public::archive', [
            'term' => $model,
            'posts' => $posts,
            // Explicit: the archive's post loop would otherwise leave a `$post` behind for the layout.
            'seoSubject' => $model,
            'seoContext' => ['page' => $posts->currentPage(), 'last_page' => $posts->lastPage()],
        ]);
    }

    /**
     * WordPress's "This content is password protected" form posts here.
     */
    public function unlock(Request $request, PermalinkGenerator $generator, int $post): RedirectResponse
    {
        $request->validate(['post_password' => ['required', 'string']]);

        $post = Post::configuredClass()::query()->find($post);

        abort_unless($post instanceof Post && ContentVisibility::isPublic($post) && $post->isPasswordProtected(), 404);

        if (! Hash::check((string) $request->input('post_password'), (string) $post->password)) {
            return back()->withErrors(['post_password' => __('shopper-cms::public.wrong_password')]);
        }

        $request->session()->put("cms-post-password.{$post->id}", $request->input('post_password'));

        return redirect()->to($generator->path($post));
    }
}
