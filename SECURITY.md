# Sécurité

Ce dépôt ne doit contenir aucun secret réel.

Ne publiez jamais :

- le fichier `.env` ;
- les clés Brevo, Pusher, Kkiapay, Resend ou autres services ;
- les mots de passe administrateur ;
- les dumps SQL ;
- les documents envoyés par les tuteurs ;
- les logs Laravel ;
- les fichiers générés dans `vendor`, `node_modules`, `public/build` ou `storage`.

Si un secret a déjà été poussé sur GitHub, il faut le considérer comme compromis et le régénérer dans le service concerné.

Pour signaler une faille ou une fuite de secret, contactez directement le mainteneur du projet. N’ouvrez pas d’issue publique contenant une clé, un mot de passe ou une donnée personnelle.