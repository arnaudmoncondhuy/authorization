# Ce qui reste au projet

Le paquet livre le mécanisme, jamais les garanties d'ensemble. Ce qui suit ne peut pas voyager,
vous appartient, et **rien du paquet ne vous préviendra**. La liste vient d'un audit mené sur
une application réelle bâtie dessus : chaque règle a été enfreinte pour de bon, et chaque
formulation est faite pour être vérifiable à la lecture ou au `grep`.

**1. Réclamer le droit là où la demande est acceptée, pas seulement là où elle est traitée.**
Une surface qui poste un message au lieu d'appeler un cas d'usage ne traverse aucun
`require()`. Le paquet n'a rien vu passer et ne le signalera pas.
*Vérifiable :* toute route qui appelle `dispatch()` appelle d'abord `require()`.

**2. L'identité qui traverse une file vient du jeton de sécurité, jamais de la requête.** Un
identifiant lu dans un corps JSON est un droit d'emprunt offert au client — y compris sur vos
comptes de service, ceux qu'aucune connexion humaine n'atteint.
*Vérifiable :* aucun champ d'un message n'est alimenté depuis la requête.

**3. Une identité de service se construit dans l'application.** `SystemIdentity::run()` accepte
le jeton qu'on lui donne et ne demandera **jamais** d'où il vient : c'est son rôle, et c'est
aussi par là qu'on se fait emprunter une identité.
*Vérifiable :* tout appel construit son jeton depuis une valeur d'origine interne.

**4. Le paquet ne connaît pas d'objet.** `can()` et `require()` prennent une `Permission` et
rien d'autre : « puis-je agir sur *cette* facture-ci » ne peut pas leur être demandé.
L'autorisation d'objet est entièrement à vous, et il y a **deux façons de la tenir** — voir la
recette « L'objet interdit » plus bas.
*Vérifiable :* tout cas d'usage qui reçoit un identifiant d'objet porte ce second contrôle,
ou bien la lecture qui le lui apporte est bornée en amont.

**5. Si vous posez des regroupements de droits, l'écran qui accorde déplie ce que le voter
déplie.** Sinon l'écran affiche un état que la réalité contredit, et l'erreur va dans le sens
permissif : des cases décochées sont pourtant accordées.
*Vérifiable :* le calcul du « coché » et le voter passent par la même fonction de dépliage.

**6. Garder l'invariant du dernier titulaire.** Le paquet laissera vider le rôle qui accorde.
Un formulaire de cases à cocher renvoyé vide révoque tout, et referme l'application sur
elle-même sans retour possible.
*Vérifiable :* le verbe qui fixe les droits examine l'état résultant, pas chaque ligne postée.

**7. Choisir, et écrire, ce qu'un refus révèle.** `MissingPermission` porte l'identité du
droit ; la rendre au client donne à un compte sans droit l'inventaire de vos permissions. C'est
acceptable, à condition d'être décidé.
*Vérifiable :* chaque écouteur de refus dit ce qu'il divulgue, et pourquoi.

**8. Traduire aussi ce qui n'est pas un refus.** Un écouteur qui ne connaît que
`MissingPermission` laisse une exception métier rendre une page de débogage à un client JSON.
*Vérifiable :* chaque surface a une réponse pour `\Throwable`, pas seulement pour le refus.

**9. L'authentification est hors du paquet, en entier.** Limitation des essais, uniformité du
temps de réponse entre compte connu et inconnu — un compte sans mot de passe répond cinq fois
plus vite —, durée de vie des jetons d'API, jeton de formulaire sur la déconnexion : rien de
tout cela n'est couvert, et rien ne le signalera.

**9 bis. Un niveau de preuve exigé engage un autre paquet, et deux choses lui échappent.** Le
conteneur refuse de compiler si personne ne juge (`ProofOfIdentity`), mais il ne sait pas si ce
juge peut répondre oui un jour : une politique d'authentification qui n'exige aucun moyen rend
l'action inatteignable pour tout le monde, et c'est l'examen de *ce* paquet-là qui le dira.
L'autre point est la traduction du refus : `InsufficientProof` n'est pas un 403 mais un détour,
et sans écouteur pour l'envoyer vers l'écran qui redemande, la surface rend une erreur serveur —
elle ferme, elle n'ouvre pas, mais elle ne sert plus.
*Vérifiable :* `authorization:doctor` liste ce qui exige et ce qui juge ; le docteur du paquet
qui juge dit si un compte peut l'atteindre.

**10. Une application de développement joignable ailleurs qu'en local publie ses secrets.** Le
profileur rend la clé d'application, les identifiants de base, les jetons et les sessions à qui
les demande. `authorization:doctor` ne dira rien de votre environnement.

Et trois règles d'architecture, si votre projet en tient une : **empêcher une surface
d'atteindre la base sans passer par un cas d'usage** ; **couper la résolution implicite
d'entités depuis les arguments de contrôleur** (`doctrine.orm.controller_resolver.enabled:
false`), qui charge une table sans qu'aucun droit ne soit réclamé ; **surveiller
`autoconfigure: false`**, seule façon de soustraire un service au tag posé par le bundle, donc
aux trois contrôles de déclaration.
