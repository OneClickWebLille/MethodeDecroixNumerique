# MethodeDecroixNumerique
Version numerique de la méthode. Pour le moment sur le sous domaine : https://exercice.lamethodedecroix.fr/login 

## Guide d'installation pour développeurs
### Prérequis
- php >= 8.3
- composer
- symfonyCLI >= 5.11.0
- nodejs >= 22.15.1
- npm >= 11.4.0
- mysql >= 8.0
- git
- un serveur web (apache)

### Installation
1. Cloner le dépôt [MethodeDecroixNumerique](https://github.com/OneClickWebLille/MethodeDecroixNumerique)

```bash
git clone https://github.com/OneClickWebLille/MethodeDecroixNumerique
cd MethodeDecroixNumerique
```

<br>

2. Adapter les variables d'environnement sont dans le fichier `.env.dev` :

    Ces variables sont à adapter selon votre environnement mais des valeurs ont été mises par défaut pour le développement :
    
   - `DATABASE_URL` : URL de la base de données (à adapter en fonction de votre environnement)
   - `MAILER_DSN` : URL du serveur SMTP pour l'envoi des mails
   - `APP_ENV` : environnement (dev)
   - `APP_SECRET` : clé secrète pour le chiffrement des données
   - `STRIPE_PUBLIC_KEY` : clé publique pour Stripe
   - `STRIPE_SECRET_KEY` : clé secrète pour Stripe
   - `STRIPE_WEBHOOK_SECRET` : secret du webhook Stripe

<br>

3. Installer les dépendances
```bash
composer install
npm install
npm run dev
```

4. Créer la base de données
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

<br>

5. Lancer le serveur
```bash
symfony serve
```
6. Accéder à l'application
```bash
http://127.0.0.1:8000
```
