# ELLIRYC Plateforme de tutorat

ELLIRYC est une plateforme web de tutorat en ligne développée avec Laravel, Vue.js, Inertia.js, Tailwind CSS et MySQL.

Le projet permet de gérer l’inscription des étudiants et des tuteurs, la validation administrative des tuteurs, les disponibilités, les réservations, les paiements, la messagerie, les notifications et les appels audio/vidéo.

## Fonctionnalités principales

- Authentification avec rôles : étudiant, tuteur, administrateur.
- Inscription tuteur avec domaine, matières, tarif, biographie et documents justificatifs.
- Validation, rejet et suspension des tuteurs par l’administrateur.
- Dashboard étudiant, tuteur et administrateur.
- Réservation selon les disponibilités du tuteur.
- Paiement via Kkiapay.
- Messagerie après réservation acceptée.
- Notifications internes en temps réel avec Pusher.
- Appels audio et vidéo avec WebRTC.
- Évaluations et avis après les séances.
- Signalements et gestion administrative.

## Technologies

- Laravel 12
- Vue.js 3
- Inertia.js
- Tailwind CSS
- MySQL
- Pusher / Laravel Echo
- WebRTC
- Kkiapay
- Brevo SMTP

## Installation locale

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Configurez ensuite `.env` avec vos propres accès : base de données, mail, Pusher, Kkiapay et administrateur.

## Variables importantes

Les clés réelles doivent rester uniquement dans `.env` ou dans les variables d’environnement du serveur de production.

```env
APP_URL=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=
VITE_PUSHER_APP_KEY=
VITE_PUSHER_APP_CLUSTER=

KKIAPAY_PUBLIC_KEY=
KKIAPAY_PRIVATE_KEY=
KKIAPAY_SECRET=
KKIAPAY_SANDBOX=true

ADMIN_NAME=
ADMIN_EMAIL=
ADMIN_PASSWORD=
```

## Tests

```bash
php artisan test
npm run build
```

## Publication GitHub

Avant de publier le projet, lisez [docs/PUBLICATION_GITHUB.md](docs/PUBLICATION_GITHUB.md).

Le dépôt public ne doit pas reprendre un ancien historique Git contenant des secrets ou des identifiants de test. Pour publier proprement, créez un nouveau dépôt Git à partir d’une copie exportée du projet.

## Licence

Ce projet n’est pas open source. Consultez [LICENSE](LICENSE).