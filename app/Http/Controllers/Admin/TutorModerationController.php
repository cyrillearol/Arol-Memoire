<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\TutorDocument;
use App\Models\User;
use App\Support\PlatformNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TutorModerationController extends Controller
{
    public function accept(Request $request, User $tutor): RedirectResponse
    {
        $this->ensureAdmin($request);
        abort_unless($tutor->role === 'tuteur', 404);

        try {
            $tutor->update(['status' => 'actif']);

            $conversation = $this->directConversation($request, $tutor);

            PlatformNotifier::send(
                $tutor,
                'Profil tuteur validé',
                'Votre profil est maintenant visible publiquement sur TutorLink. L\'administration peut désormais vous contacter directement.',
                route('messages.index', $conversation),
                'success'
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Impossible de valider ce profil tuteur pour le moment. Réessayez plus tard.');
        }

        return back()->with('success', 'Tuteur validé et rendu visible publiquement.');
    }

    public function reject(Request $request, User $tutor): RedirectResponse
    {
        $this->ensureAdmin($request);
        abort_unless($tutor->role === 'tuteur', 404);

        try {
            $tutor->update(['status' => 'rejete']);

            PlatformNotifier::send(
                $tutor,
                'Candidature tuteur refusée',
                'Votre candidature tuteur n’a pas été acceptée. Contactez l’administration pour plus de détails.',
                route('dashboard'),
                'danger'
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Impossible de rejeter cette candidature pour le moment. Réessayez plus tard.');
        }

        return back()->with('success', 'Candidature tuteur rejetée.');
    }

    public function suspend(Request $request, User $tutor): RedirectResponse
    {
        $this->ensureAdmin($request);
        abort_unless($tutor->role === 'tuteur', 404);

        try {
            $tutor->update(['status' => 'suspendu']);

            PlatformNotifier::send(
                $tutor,
                'Profil tuteur suspendu',
                'Votre profil n’est plus visible publiquement. Contactez l’administration.',
                route('dashboard'),
                'danger'
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Impossible de suspendre ce tuteur pour le moment. Réessayez plus tard.');
        }

        return back()->with('success', 'Tuteur suspendu.');
    }

    public function message(Request $request, User $tutor): RedirectResponse
    {
        $this->ensureAdmin($request);
        abort_unless($tutor->role === 'tuteur' && $tutor->status === 'actif', 404);

        return redirect()->route('messages.index', $this->directConversation($request, $tutor));
    }

    public function downloadDocument(Request $request, TutorDocument $document): StreamedResponse
    {
        $this->ensureAdmin($request);

        return Storage::disk('public')->download($document->path, $document->original_name);
    }

    private function directConversation(Request $request, User $tutor): Conversation
    {
        return Conversation::query()->firstOrCreate([
            'booking_id' => null,
            'student_id' => $request->user()->id,
            'tutor_id' => $tutor->id,
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }
}
