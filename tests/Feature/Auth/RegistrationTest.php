<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'etudiant',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => 'etudiant',
            'status' => 'actif',
        ]);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_tutors_can_register_with_profile_and_documents(): void
    {
        Storage::fake('public');

        $response = $this->post('/register', [
            'name' => 'Tutor User',
            'email' => 'tutor@example.com',
            'role' => 'tuteur',
            'domain' => 'Mathematiques',
            'subjects' => 'Algebre, Statistiques',
            'hourly_rate' => '5000',
            'bio' => 'Je propose un accompagnement clair avec des exercices progressifs pour aider les etudiants.',
            'documents' => [UploadedFile::fake()->create('diplome.pdf', 128, 'application/pdf')],
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'tutor@example.com',
            'role' => 'tuteur',
            'status' => 'en_attente',
        ]);
        $this->assertDatabaseHas('tutor_profiles', [
            'domain' => 'Mathematiques',
            'hourly_rate' => '5000.00',
        ]);
        $this->assertDatabaseHas('tutor_documents', [
            'original_name' => 'diplome.pdf',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
