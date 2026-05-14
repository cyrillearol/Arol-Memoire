# Déploiement Railway - TutorLinks

## 1. Créer le projet Railway

1. Va sur https://railway.app
2. Connecte ton compte GitHub
3. Clique sur **New Project**
4. Choisis **Deploy from GitHub repo**
5. Sélectionne le dépôt du projet

Si le projet n'est pas encore sur GitHub, crée d'abord un dépôt GitHub puis pousse le dossier `ELLIRYC_PLATEFORME`.

## 2. Ajouter MySQL

Dans Railway :

1. Clique sur **New**
2. Choisis **Database**
3. Choisis **MySQL**
4. Ouvre le service Laravel
5. Va dans **Variables**
6. Ajoute les variables Laravel de connexion MySQL avec les valeurs fournies par Railway

Variables à créer :

```env
DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
```

Si Railway affiche des noms différents, utilise les noms exacts affichés dans l'onglet Variables du service MySQL.

## 3. Variables obligatoires de l'application

Dans le service Laravel, ajoute :

```env
APP_NAME=TutorLinks
APP_ENV=production
APP_KEY=base64:REMPLACER_PAR_LA_CLE_GENEREE
APP_DEBUG=false
APP_URL=https://TON-DOMAINE-RAILWAY.up.railway.app
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_FAKER_LOCALE=fr_FR

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public
LOG_CHANNEL=stack
LOG_LEVEL=debug

BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=REMPLACER
PUSHER_APP_KEY=REMPLACER
PUSHER_APP_SECRET=REMPLACER
PUSHER_APP_CLUSTER=eu
VITE_PUSHER_APP_KEY=REMPLACER
VITE_PUSHER_APP_CLUSTER=eu

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=REMPLACER
MAIL_PASSWORD=REMPLACER
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=REMPLACER
MAIL_FROM_NAME=TutorLinks

KKIAPAY_PUBLIC_KEY=REMPLACER
KKIAPAY_PRIVATE_KEY=REMPLACER
KKIAPAY_SECRET=REMPLACER
KKIAPAY_SANDBOX=false

ADMIN_NAME=Administrateur TutorLinks
ADMIN_EMAIL=REMPLACER
ADMIN_PASSWORD=REMPLACER_PAR_UN_MOT_DE_PASSE_SOLIDE
```

Pour générer `APP_KEY` en local :

```bash
php artisan key:generate --show
```

Copie la valeur complète qui commence par `base64:`.

## 4. Déploiement

Après avoir mis les variables :

1. Railway va lancer le build automatiquement
2. La commande de démarrage exécutera les migrations et créera l'administrateur
3. Ouvre l'URL Railway du service Laravel

## Notes importantes

- WebRTC demande HTTPS : Railway fournit HTTPS, donc le micro et la caméra peuvent fonctionner.
- Pusher sert uniquement à la signalisation et aux notifications.
- Sur l'offre gratuite, les fichiers stockés localement peuvent être perdus après redémarrage/redeploy. Pour une démo, c'est acceptable. Pour une vraie mise en production, il faudra un stockage externe compatible S3.
- Pour les appels WebRTC fiables sur tous les réseaux, il faudra ajouter un serveur TURN plus tard.