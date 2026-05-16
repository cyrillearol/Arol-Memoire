<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PlatformNotifier;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'role' => 'required|string|in:etudiant,tuteur',
            'password' => ['required', 'confirmed', Rules\Password::defaults(), 'max:15'],
            'domain' => 'required_if:role,tuteur|nullable|string|max:255',
            'subjects' => 'required_if:role,tuteur|nullable|string|max:1000',
            'hourly_rate' => 'required_if:role,tuteur|nullable|numeric|min:100|max:999999.99',
            'bio' => 'required_if:role,tuteur|nullable|string|min:30|max:2000',
            'documents' => 'required_if:role,tuteur|nullable|array|min:1|max:5',
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ], [
            'bio.min' => 'La présentation professionnelle doit contenir au moins 30 caractères.',
            'documents.required_if' => 'Ajoutez au moins un document justificatif pour la candidature tuteur.',
            'hourly_rate.min' => 'Le tarif horaire doit être supérieur à 0 FCFA.',
        ]);

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->role,
                'status' => $request->role === 'tuteur' ? 'en_attente' : 'actif',
                'password' => Hash::make($request->password),
            ]);

            if ($request->role === 'tuteur') {
                $subjects = collect(explode(',', (string) $request->subjects))
                    ->map(fn (string $subject) => trim($subject))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($subjects === []) {
                    throw ValidationException::withMessages([
                        'subjects' => 'Indiquez au moins une matière enseignée.',
                    ]);
                }

                $profile = $user->tutorProfile()->create([
                    'domain' => $request->domain,
                    'subjects' => $subjects,
                    'hourly_rate' => $request->hourly_rate,
                    'bio' => $request->bio,
                ]);

                foreach ($request->file('documents', []) as $document) {
                    $path = $document->store('tutor-documents', 'public');

                    $profile->documents()->create([
                        'original_name' => $document->getClientOriginalName(),
                        'path' => $path,
                        'mime_type' => $document->getClientMimeType(),
                        'size' => $document->getSize(),
                    ]);
                }
            }

            return $user;
        });

        if ($user->role === 'tuteur') {
            PlatformNotifier::sendToAdmins(
                'Nouvelle candidature tuteur',
                $user->name.' a soumis une candidature tuteur à valider.',
                route('dashboard'),
                'warning'
            );
        }

        event(new Registered($user));

        $message = $user->role === 'tuteur'
            ? 'Votre candidature a été envoyée. Connectez-vous pour suivre sa validation.'
            : 'Votre compte a été créé. Connectez-vous pour continuer.';

        return redirect()->route('login')->with('status', $message);
    }
}
