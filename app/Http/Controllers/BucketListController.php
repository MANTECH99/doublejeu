<?php

namespace App\Http\Controllers;

use App\Models\BucketListItem;
use App\Services\ActivityService;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BucketListController extends Controller
{
    const CATEGORIES_LABELS = [
        'voyages' => 'Voyages & Évasions',
        'activites' => 'Activités & Aventures',
        'gastronomie' => 'Gastronomie & Cuisine',
        'projets' => 'Projets & Moments',
    ];

    const CATEGORIES_ICONS = [
        'voyages' => '✈️',
        'activites' => '🎢',
        'gastronomie' => '🍽️',
        'projets' => '🛠️',
    ];

    protected function photoUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return parse_url(Storage::disk('public')->url($path), PHP_URL_PATH) ?: null;
    }

    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;

        return view('jeux.bucket-list.index', [
            'couple' => $couple,
            'categories' => self::CATEGORIES_LABELS,
        ]);
    }

    public function state(Request $request): JsonResponse
    {
        ActivityService::touch($request->user());

        $couple = $request->user()->coupleModel;

        $items = $couple->bucketListItems()->with('createur')->orderBy('id')->get();

        return response()->json(['items' => $items->map(fn (BucketListItem $i) => $this->serialiser($i))]);
    }

    public function creer(Request $request): JsonResponse
    {
        $couple = $request->user()->coupleModel;

        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'categorie' => ['required', 'string', 'in:'.implode(',', array_keys(self::CATEGORIES_LABELS))],
            'lieu' => ['nullable', 'string', 'max:255'],
        ]);

        $item = $couple->bucketListItems()->create([
            'titre' => $data['titre'],
            'categorie' => $data['categorie'],
            'lieu' => $data['lieu'] ?? null,
            'realise' => false,
            'cree_par' => $request->user()->id,
        ]);

        ActivityService::touch($request->user());

        $partner = $couple->partnerOf($request->user());
        if ($partner) {
            app(PushService::class)->sendToUser($partner, [
                'title' => '🧳 Nouvelle idée sur la Bucket List !',
                'body' => $request->user()->name.' a ajouté « '.$data['titre'].' »',
                'url' => route('bucket-list.index'),
            ]);
        }

        return response()->json(['ok' => true, 'item' => $this->serialiser($item)]);
    }

    /**
     * Attache une photo souvenir à une activité. Le fichier est stocké sur le
     * disque public puis référencé dans la liste `photos` de l'activité.
     */
    public function photo(Request $request, BucketListItem $item): JsonResponse
    {
        $couple = $request->user()->coupleModel;
        abort_if($item->couple_id !== $couple->id, 403);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('photo');
        $path = $file->store('bucket-list-photos', 'public');

        $photos = $item->photos ?? [];
        $photos[] = $path;
        $item->forceFill(['photos' => $photos])->save();

        ActivityService::touch($request->user());

        return response()->json(['ok' => true, 'url' => $this->photoUrl($path)]);
    }

    public function realiser(Request $request, BucketListItem $item): JsonResponse
    {
        $couple = $request->user()->coupleModel;
        abort_if($item->couple_id !== $couple->id, 403);

        if ($item->realise) {
            return response()->json(['error' => 'Déjà réalisé.'], 422);
        }

        $item->forceFill(['realise' => true, 'realise_at' => now()])->save();

        ActivityService::touch($request->user());

        $partner = $couple->partnerOf($request->user());
        if ($partner) {
            app(PushService::class)->sendToUser($partner, [
                'title' => '🎉 Activité réalisée !',
                'body' => $request->user()->name.' a coché « '.$item->titre.' »',
                'url' => route('bucket-list.index'),
            ]);
        }

        return response()->json(['ok' => true, 'item' => $this->serialiser($item)]);
    }

    public function reouvrir(Request $request, BucketListItem $item): JsonResponse
    {
        $couple = $request->user()->coupleModel;
        abort_if($item->couple_id !== $couple->id, 403);

        if (! $item->realise) {
            return response()->json(['error' => 'Pas encore réalisé.'], 422);
        }

        $item->forceFill(['realise' => false, 'realise_at' => null])->save();

        ActivityService::touch($request->user());

        return response()->json(['ok' => true, 'item' => $this->serialiser($item)]);
    }

    public function detruire(Request $request, BucketListItem $item): JsonResponse
    {
        $couple = $request->user()->coupleModel;
        abort_if($item->couple_id !== $couple->id, 403);

        foreach (($item->photos ?? []) as $p) {
            if (Storage::disk('public')->exists($p)) {
                Storage::disk('public')->delete($p);
            }
        }
        $item->delete();

        ActivityService::touch($request->user());

        return response()->json(['ok' => true]);
    }

    protected function serialiser(BucketListItem $item): array
    {
        return [
            'id' => $item->id,
            'titre' => $item->titre,
            'categorie' => $item->categorie,
            'lieu' => $item->lieu,
            'realise' => $item->realise,
            'realise_at' => $item->realise_at?->format('Y-m-d H:i'),
            'cree_par' => $item->createur?->name,
            'photos' => collect($item->photos ?? [])->map(fn ($p) => $this->photoUrl($p))->values()->all(),
        ];
    }
}
