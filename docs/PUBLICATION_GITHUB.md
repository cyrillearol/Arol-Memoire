# Publication GitHub sécurisée

Ce projet peut être publié sur GitHub, mais il ne faut pas pousser l’historique Git actuel si celui-ci a déjà contenu des identifiants ou des mots de passe.

## Pourquoi créer un historique propre ?

Git conserve les anciennes versions des fichiers. Si un mot de passe a été présent dans un ancien commit, il peut rester visible sur GitHub même si le fichier actuel a été corrigé.

La méthode la plus sûre consiste à créer une copie propre du projet, sans dossier `.git`, puis à initialiser un nouveau dépôt Git.

## Étapes recommandées

Depuis PowerShell :

```powershell
cd C:\xampp\htdocs\ELLIRYC_PLATEFORME
.\scripts\create-public-release.ps1
```

Le script crée un dossier voisin nommé `ELLIRYC_PLATEFORME_PUBLIC`, sans `.env`, sans historique Git, sans dépendances installées et sans fichiers générés.

Ensuite :

```powershell
cd C:\xampp\htdocs\ELLIRYC_PLATEFORME_PUBLIC
git remote add origin https://github.com/VOTRE_COMPTE/VOTRE_REPO.git
git branch -M main
git push -u origin main
```

## Avant de pousser

Vérifiez toujours :

```powershell
git status --short
git ls-files | Select-String -Pattern "\.env$|storage/logs|node_modules|vendor|public/build|public/hot"
```

Aucun fichier sensible ne doit apparaître.

## Secrets à ne jamais mettre dans GitHub

- `.env`
- mots de passe administrateur
- clés Pusher
- clés Brevo
- clés Kkiapay
- clés Resend
- dumps SQL
- logs Laravel
- fichiers uploadés par les utilisateurs

## Déploiement depuis GitHub

Un dépôt privé peut aussi être déployé depuis GitHub sur beaucoup de plateformes si vous connectez votre compte GitHub. Le dépôt public n’est donc pas obligatoire pour tous les hébergeurs.

Si vous choisissez un dépôt public, gardez les secrets uniquement dans les variables d’environnement de l’hébergeur.

## Licence

Le dépôt public reste protégé par la licence `Tous droits réservés`. Le fait que le code soit visible sur GitHub ne donne pas le droit de le copier ou de l’exploiter.