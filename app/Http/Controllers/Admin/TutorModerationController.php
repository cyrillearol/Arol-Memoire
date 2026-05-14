<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TutorDocument;
use App\Models\User;
use App\Support\PlatformNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TutorModerationController extends Controller
{
    public function accept(Request $request, User $tutor): RedirectResponse
    {
        $this->ensureAdmin($request);
        abort_unless($tutor->role === 'tuteur', 404);

        $tutor->update(['status' => 'actif']);

        PlatformNotifier::send(
            $tutor,
            'Profil tuteur validé',
            'Votre profil est maintenant visible publiquement sur TutorLink.',
            route('dashboard'),
            'success'
        );

        return back()->with('success', 'Tuteur validé et rendu visible publiquement.');
    }

    public function reject(Request $request, User $tutor): RedirectResponse
    {
        $this->ensureAdmin($request);
        abort_unless($tutor->role === 'tuteur', 404);

        $tutor->update(['status' => 'rejete']);

        PlatformNotifier::send(
            $tutor,
            'Candidature tuteur refusée',
            'Votre candidature tuteur n’a pas été acceptée. Contactez l’administration pour plus de détails.',
            route('dashboard'),
            'danger'
        );

        return back()->with('success', 'Candidature tuteur rejetée.');
    }

    public function suspend(Request $request, User $tutor): RedirectResponse
    {
        $this->ensureAdmin($request);
        abort_unless($tutor->role === 'tuteur', 404);

        $tutor->update(['status' => 'suspendu']);

        PlatformNotifier::send(
            $tutor,
            'Profil tuteur suspendu',
            'Votre profil n’est plus visible publiquement. Contactez l’administration.',
            route('dashboard'),
            'danger'
        );

        return back()->with('success', 'Tuteur suspendu.');
    }

    public function downloadDocument(Request $request, TutorDocument $document): StreamedResponse
    {
        $this->ensureAdmin($request);

        return Storage::disk('public')->download($document->path, $document->original_name);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }
}