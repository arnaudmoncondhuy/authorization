# Ce qui casse, et ce que le docteur pourrait en voir

Ce document est un **carnet de travail**, pas une liste de mises en garde. Chaque risque
répond à une question : *le docteur pourrait-il le détecter ?* — il le détecte déjà, il
pourrait et voici comment, ou il ne peut pas et voici pourquoi.

Ce qui reste en prose y reste **faute de savoir l'outiller**, jamais par commodité. Une règle
qu'aucun fichier ne vérifie finit par être enfreinte sans que personne le voie.

Tout ce qui suit a été observé sur une application réelle bâtie sur ce paquet.

## Les limites du paquet

Ce qu'il ne sait pas faire, et ne saura pas.

**1. Le paquet ne stocke rien.** Ni table de droits, ni modèle de rôles, ni écran. Tout
cela vous appartient, et c'est la moitié du travail.

**2. Il ne connaît pas la notion de regroupement.** Ni celle de hiérarchie, ni celle de
droit implicite. `PermissionCatalog` ne contient que ce qu'un cas d'usage exige. Tout ce qui
se déplie est à vous, et rien ne relie ce que vous dépliez à ce que le code exige.

**3. Un droit ne porte pas de libellé.** `Permission` n'a qu'une identité. C'est ce qui la
garde citable depuis un domaine pur, et cela vous laisse la traduction à faire.

**4. Un droit ne porte pas de sujet.** `require()` prend un droit, jamais un objet. « Peut
modifier *cette* facture-ci » n'existe pas dans le contrat : le paramètre `$subject` du voter
Symfony reste toujours `null`. Une règle qui dépend de l'objet se traite dans le corps du cas
d'usage, à la main, après le `require()` — et rien ne la vérifiera.

**5. Un refus est toujours un 403, jamais un 401.** Le paquet traduit `MissingPermission`
en `AccessDeniedHttpException`, ce qui court-circuite le point d'entrée du pare-feu. Un appel
d'API sans jeton reçoit donc `403 {"error":{"permission":"invoice.view"}}` et non un défi
d'authentification. Vérifié.

**6. Il n'y a pas d'auto-remédiation.** Aucune commande n'accorde, ne révoque, ne migre une
identité renommée. Ce que vous cassez, vous le réparez.

**7. `PermissionUsage` ne lit que ce qui est écrit en toutes lettres.** Un droit choisi par
une variable est signalé pour ce qu'il est — illisible — plutôt que lu, et une énumération
importée sous un autre nom lui échappe sans un mot. Ce sont ses limites déclarées (§ C.8, § 8).

**8. Les trois contrôles de déclaration ne jugent que ce qui implémente `UseCase`.** Une
classe qui oublie l'interface, ou un service soustrait à l'autoconfiguration, leur échappe
entièrement (§ C.6, § C.7).

**9. Le paquet ne configure pas la stratégie de décision.** Elle reste celle de Symfony,
et elle change tout (§ C.3).

**10. `UserAuthorizer` exige un fournisseur de comptes.** Il désigne l'autre par un
identifiant, et c'est votre `security.providers` qui le rapporte à un compte. Une application
qui n'en déclare aucun ne peut pas obtenir ce contrat : la compilation s'arrête si elle
l'injecte, et ne dit rien si elle ne l'injecte pas. Répondre `false` faute de fournisseur
aurait fermé aussi — donc sans faille — mais aurait fait passer une absence de réponse pour un
refus de droit, et c'est la seule chose que ce contrat sache dire.

**11. `PermissionUsage` ne lit que ce que les chemins nomment.** Il déduit un type de
l'emplacement de chaque fichier, selon PSR-4. Un fichier qui ne déclare pas ce que sa place
annonce est **rapporté comme faute**, jamais sauté : une classe renommée sans que son fichier
suive échapperait sinon à toute lecture tout en portant du métier. Un fichier de fonctions
logé dans l'arborescence balayée est donc signalé lui aussi — le sortir de là, ou pointer le
contrôle plus bas, est la réponse.

---

## Les risques de casse

Chaque risque ci-dessous a été **provoqué** dans cette application, pour observer ce qui se
plaint. La colonne qui compte est « comment ça se manifeste » : un défaut visible est un défaut
qu'on corrige.

Pour chacun, la question posée est : **`authorization:doctor` pourrait-il le détecter ?**
Trois réponses seulement — *il le détecte déjà*, *il pourrait, et voici comment*, *il ne peut
pas, et voici pourquoi*.

---

### 1. Renommer l'identité d'un droit

**Ce qui casse.** La valeur de l'énumération change ; les lignes déjà accordées en base portent
l'ancienne. Tous ceux qui détenaient le droit le perdent.

**Comment ça se manifeste. Pas du tout.** Vérifié en renommant `invoice.finalize` en
`invoice.close` : le conteneur compile, `PermissionUsage` passe, PHPStan passe, les 34 tests
passent, `authorization:doctor` conclut au vert — et le compte qui finalisait reçoit
`Refusé — droit manquant : invoice.close`. C'est la faute la plus dangereuse du dispositif :
elle **retire des droits** sans un mot.

Le seul signal existe si vous l'avez écrit : l'écran d'attribution range alors `invoice.finalize`
dans les identités accordées que plus rien n'exige.

**Comment l'éviter.** Traitez une identité comme une clé de base de données : elle ne se
renomme pas, elle se migre. Si vous devez la changer, la migration qui renomme l'énumération
écrit dans le même commit le `UPDATE` qui reprend les lignes accordées.

**Le docteur ? Il pourrait le détecter.** Il connaît le catalogue ; il lui manque la liste des
identités **stockées**. Le paquet ne peut pas la deviner — mais il pourrait l'accepter : un
contrat facultatif à une méthode, que l'application implémente si elle veut être examinée.

```php
interface GrantedIdentities   // à écrire dans le paquet
{
    /** @return iterable<string> les identités que le stockage porte, doublons compris */
    public function stored(): iterable;
}
```

Le docteur, s'il trouve une implémentation, signalerait toute identité stockée absente du
catalogue. C'est exactement le contrôle que cette application a dû écrire dans son écran, et
qui manque à toute application qui n'y pense pas.

---

### 2. `require()` ailleurs qu'en tête

**Ce qui casse.** Un refus qui arrive après une lecture a déjà lu. Le verbe rend une erreur,
mais la requête a tourné, la ligne a été chargée, et l'objet a pu être journalisé ou mis en
cache en chemin.

**Comment ça se manifeste. Pas du tout.** Vérifié en déplaçant `require()` après la lecture :
34 tests verts, docteur vert, PHPStan vert, style vert. Rien.

**Comment l'éviter.** La relecture, et rien d'autre aujourd'hui.

**Le docteur ? Il ne peut pas.** Il ne voit que le conteneur compilé et les voters ; il n'ouvre
aucun fichier. C'est structurel, pas un manque d'effort.

**Mais `PermissionUsage` le pourrait**, et c'est lui qui devrait apprendre : il lit déjà le
corps de `__invoke()`. Vérifier que le premier appel de méthode du corps est un `require()` est
une regex sur le même texte, avec un seul faux positif prévisible — une garde de validation
posée avant. On peut aussi l'écrire plus strictement : refuser toute ligne de corps avant le
premier `require()` qui ne soit ni un commentaire, ni une accolade, ni un autre `require()`.

C'est une **règle en prose faute d'outillage**, et l'outillage existe déjà à portée.

---

### 3. Deux voters qui se prononcent sur la même identité

**Ce qui casse.** Le README du paquet annonce que « les deux se prononcent, et le premier refus
l'emporte ». **C'est faux sous la configuration par défaut de Symfony**, et l'erreur va dans le
mauvais sens.

**Comment ça se manifeste.** Vérifié deux fois :

- en élargissant le `supports()` du voter par rôle aux identités de la facturation, le compte
  qui tient ses droits d'un groupe finalise toujours : le refus du voter par rôle **ne compte
  pas** ;
- en élargissant le `supports()` du voter par groupe aux identités du stock, et en posant
  `stock.view` sur un groupe, un compte **sans aucun rôle** voit le stock : le voter de trop a
  **accordé** ce que le modèle prévu refuse.

La stratégie par défaut de Symfony est `affirmative` : un seul avis favorable suffit. Deux
voters qui se recouvrent **élargissent** donc les droits, ils ne les restreignent pas. Ni les
tests ni l'analyse statique ne le voient — le docteur, seul, le dit.

**Comment l'éviter.** Ne recopiez pas un préfixe dans chaque `supports()`. Faites dépendre les
trois `supports()` d'une seule répartition, écrite dans le domaine (§ A.9) : la disjonction
devient une propriété du code, pas une consigne.

**Le docteur ? Il le détecte déjà.** Il **compte** les voters qui se prononcent sur chaque
droit au lieu de s'arrêter au premier : zéro juge est l'erreur qu'il rend depuis toujours,
deux et plus est signalé — nommément, voter par voter. Un recouvrement n'étant pas toujours
une faute — un voter qui ouvre tout à une poignée d'administrateurs en est un usage légitime —
le signalement ne fait échouer l'examen que sous `--strict`.

Il gagnerait encore à afficher la stratégie en tête de son rapport, à côté du contrat et du
nombre de voters : c'est une ligne, et elle change l'interprétation de tout le reste (§ C.14).

---

### 4. Un contexte qu'aucun modèle d'attribution ne gouverne

**Ce qui casse.** Vous ajoutez `ReportPermission: 'report.export'`. Aucun voter ne le supporte,
aucun écran ne le propose. Le droit est refusé à tout le monde, définitivement.

**Comment ça se manifeste.** Deux cas, très différents :

- si **aucun** voter ne le supporte, `authorization:doctor` échoue et le nomme. Vérifié en
  neutralisant le `supports()` du voter par rôle : `stock.view` et `stock.adjust` sont
  immédiatement rapportés comme jugés par personne ;
- si un voter le supporte **mais qu'aucun modèle ne l'accorde** — parce que la répartition
  `GrantModel` ignore son contexte — alors le docteur reste vert, et le droit est inaccordable
  quand même. Vérifié : le conteneur de test de cette application porte un droit de fixture
  dont le contexte n'est réparti nulle part. Il n'apparaît dans aucune des deux listes
  cochables, et rien n'aurait signalé son absence si l'écran ne le nommait pas à part.

**Comment l'éviter.** Faites nommer par l'écran d'attribution tout droit du catalogue
qu'aucun modèle ne gouverne. C'est trois lignes, et c'est ce que fait
`ShowAccessModelUseCase::ungoverned()`. Un test unitaire ferait aussi bien, si votre catalogue
de test ne porte pas de droits étrangers.

**Le docteur ? Il ne peut pas.** La répartition entre modèles est une notion de l'application ;
le paquet ne sait pas qu'elle existe. La moitié qu'il **peut** voir — aucun voter du tout — il
la voit déjà, et bien.

---

### 5. Un voter qui s'abstient faute d'utilisateur

**Ce qui casse.** Le verbe se ferme à toute surface sans appelant : console, worker, tâche
planifiée. La stratégie `affirmative` avec `allow_if_all_abstain: false` refuse quand tout le
monde s'abstient.

**Comment ça se manifeste. Le docteur le voit.** Vérifié en posant un voter qui rend
`ACCESS_ABSTAIN` quand le jeton n'a pas d'utilisateur : les droits qu'il gouvernait sont
rapportés comme jugés par personne.

**Il le détecte déjà**, avec la réserve que le paquet écrit lui-même : la mesure est indirecte.
Un voter qui jugerait très bien un vrai compte, mais s'abstient sur un jeton nul, est compté
absent **à tort**. C'est un faux positif, donc l'erreur va dans le bon sens — on regarde, et on
constate.

Le voisin de ce cas est celui qui ne s'abstient pas mais **lève** : il prend le droit en charge,
puis lit l'utilisateur sans se demander s'il y en a un. Celui-là n'est compté ni juge ni absent,
mais nommé pour ce qu'il est (§ C.11) — et ce qu'il annonce dépasse le diagnostic, puisqu'une
requête anonyme lui fera rendre une erreur serveur au lieu d'un refus.

Sur un voter bâti sur la classe abstraite `Voter` de Symfony, le cas ne peut pas se produire :
`supports()` ne reçoit pas le jeton, et `voteOnAttribute()` rend un booléen. **Refusez
explicitement**, comme dans l'exemple du § A.3, et le problème n'existe pas.

---

### 6. Un service soustrait à l'autoconfiguration

**Ce qui casse.** `autoconfigure: false` retire le tag que le bundle pose. Les trois contrôles
de déclaration ne voient plus le service — tout en le laissant parfaitement injectable.

**Comment ça se manifeste. Pas du tout, du côté du paquet.** Vérifié : un cas d'usage sans
aucun `#[RequiresPermission]`, déclaré avec `autoconfigure: false`, laisse le conteneur
compiler au vert. Le même cas d'usage, autoconfiguré, arrête la compilation.

Le seul filet est extérieur au paquet : la routine qualité de ce dépôt cherche
`autoconfigure: *false` dans `config/` et échoue. Vérifié : elle mord.

**Comment l'éviter.** Interdisez le motif plutôt que de surveiller ses usages. Un service qui a
besoin d'un tag précis se le pose en toutes lettres ; il n'a jamais besoin de perdre tous les
autres.

**Le docteur ? Il pourrait le détecter.** Il tourne dans le conteneur compilé et pourrait
parcourir toutes les définitions, retenir celles dont la classe implémente `UseCase`, et
signaler celles auxquelles le tag manque. C'est l'exact miroir de
`CheckOnlyUseCasesDeclarePermissionsPass`, qui parcourt déjà toutes les définitions.

Mieux encore : cette passe-là pourrait le faire elle-même, à la compilation, et **arrêter le
démarrage**. Elle regarde déjà chaque définition ; il lui suffirait de vérifier que toute
classe implémentant `UseCase` porte le tag. Le contrôle passerait alors d'une routine qu'on
peut oublier à un refus de démarrer.

---

### 7. Une classe qui réclame un droit sans être un cas d'usage

**Ce qui casse.** Du métier gouverné par rien, hors de tout inventaire.

**Comment ça se manifeste.** Vérifié : le conteneur compile sans broncher ; `PermissionUsage`
rend `TricheController réclame un droit sans être un cas d'usage`. Le contrôle mord, mais
seulement en test.

**Comment l'éviter.** Rien à faire de plus : ce contrôle est celui du paquet, et il faut
l'appeler. Une application qui monte le paquet sans écrire le test de la § 5 du README perd
cette garantie.

**Le docteur ? Il ne peut pas.** Il faut lire des corps de méthode ; il ne lit rien.

---

### 8. Un droit choisi par une variable

**Ce qui casse.** Un corps qui écrit `require($permission)` réclame peut-être exactement ce
qu'il déclare — mais aucune lecture de texte ne sait le dire, et un droit réclamé par variable
sans être déclaré fermerait le verbe à tout le monde sans qu'aucun inventaire le propose.

**Comment ça se manifeste.** `PermissionUsage` signale tout `->require(` suivi d'une valeur :
« réclame un droit par une valeur, que ce contrôle ne sait pas rapprocher de ses
déclarations ». Le test est rouge, la faute est nommée pour ce qu'elle est — illisible — et
l'examen s'arrête là pour cette classe : accuser en plus le droit déclaré de n'être « jamais
réclamé » serait peut-être faux, et enverrait chercher au mauvais endroit.

**Comment l'éviter.** Écrivez le cas en toutes lettres, toujours. C'est aussi ce qui rend le
corps lisible.

**Le docteur ? Il ne peut pas** — pas de lecture de corps. `PermissionUsage` ne peut pas
davantage **lire** la valeur : distinguer `require($x)` d'un appel légitime demanderait de
résoudre `$x`, donc d'analyser le flot. Il la **signale**, et c'est le niveau juste : un
avertissement qui contraint l'écriture vaut mieux qu'un silence qui ferme.

---

### 9. Prendre `can()` pour une protection

**Ce qui casse.** Une surface cache un bouton, le verbe n'exige rien, et l'adresse reste
ouverte à qui la devine.

**Comment ça se manifeste.** Le cas pur ne survit pas aux contrôles : un droit déclaré que le
corps se contente de tester est signalé « déclaré sans jamais être réclamé ». Vérifié. Reste le
cas où le verbe n'a **aucun** droit : la compilation s'arrête.

**Comment l'éviter.** Cachez avec `can()`, refusez avec `require()`. Jamais l'un sans l'autre.

**Le docteur ? Il ne peut pas** — même raison. Le contrôle qui mord ici est `PermissionUsage`,
et il mord déjà.

---

### 10. Une surface qui atteint la base sans passer par un verbe

**Ce qui casse.** L'architecture ferme `Repository` aux surfaces et coupe la résolution
implicite d'entités. Mais une console et un worker qui se donnent une identité doivent charger
un compte, et le chemin recommandé est `UserProviderInterface::loadUserByIdentifier()`. Une
surface tient donc, légitimement, un service qui lit la base.

**Comment ça se manifeste. Pas du tout.** Deptrac ne classe pas les classes de Symfony : la
dépendance est autorisée par défaut. Aucune violation n'est levée.

**Comment l'éviter.** Ne vous en servez que pour construire une identité, jamais pour lire du
métier. Le fournisseur ne rend qu'un compte : la tentation est bornée, mais elle existe.

Si vous voulez la fermer, déclarez `Symfony\Component\Security\Core\User\UserProviderInterface`
dans une couche de `deptrac.yaml` et ouvrez-la aux seules surfaces qui n'ont pas d'appelant —
ni `Web`, ni `Api`, ni `Mcp`.

**Le docteur ? Il ne peut pas.** C'est une affaire de couches, et le paquet dit lui-même que
c'est ce qui reste au projet.

---

### 10 bis. Une **route** qui n'appelle aucun verbe, dans une classe qui en appelle

**Ce qui casse.** Le quatrième refus de compilation juge une **classe**, pas une route. Un
contrôleur dont trois routes appellent des cas d'usage et dont la quatrième se contente de
poster un message passe le contrôle sans réserve : la classe atteint bien un verbe.

C'est exactement la forme qu'avait la faille la plus grave trouvée sur une application de ce
paquet — une route d'API qui acceptait une demande, la postait dans une file, et lisait dans le
corps de la requête le compte au nom duquel agir. Un inconnu sans compte finalisait des
factures.

**Il faut donc le dire sans détour : la passe n'aurait pas attrapé la faute qui l'a fait
naître.** Ce qu'elle attrape, c'est la porte entièrement non gardée — une surface qui ne
délègue jamais, celle qu'on ajoute en oubliant le dispositif. Sur une application réelle, ce
genre-là existe aussi : une passe équivalente en a trouvé huit d'un coup.

**Comment ça se manifeste. Pas du tout.** Aucune compilation ne bronche, aucun test ne tombe,
le docteur reste vert. La route répond, et elle répond à tout le monde.

**Comment l'éviter.** La règle 1 de [ce qui reste au projet](ce-qui-reste-au-projet.md) :
réclamer le droit **là où la demande est acceptée**, pas seulement là où elle est traitée.
Toute route qui appelle `dispatch()` appelle d'abord `require()`.

**Le docteur ? Il pourrait, et voici comment.** Le contrôle existe déjà pour les cas d'usage :
`PermissionUsage` lit les corps de méthode et y cherche l'appel. Le même mécanisme, appliqué
aux méthodes d'une surface, dirait « cette route poste un message sans qu'aucun droit soit
réclamé ». Ce n'est pas écrit, et c'est le premier contrôle à ajouter.

---

### 10 ter. Une porte que la passe croit servie, ou qu'elle ne voit pas

**Ce qui casse.** Deux formes, cousines de la précédente.

- **Le verbe reçu jamais appelé.** La passe juge ce qu'une porte **reçoit** — son
  constructeur, ses méthodes publiques, ce que le conteneur injecte pour chaque type. Un
  contrôleur qui injecte un cas d'usage et écrit à côté, directement dans le dépôt, passe au
  vert : la réception n'est pas l'appel, et aucune lecture de signatures ne fera la
  différence.
- **La porte que Symfony ne marque pas.** La passe examine les tags que le framework pose de
  lui-même — contrôleurs, commandes, consommateurs de messages. Or ce marquage est un opt-in :
  un contrôleur routé en YAML qui n'étend pas `AbstractController` et ne porte ni
  `#[AsController]` ni `#[Route]` n'est jamais tagué, donc jamais examiné. Un écouteur
  d'événement qui agit sur `kernel.request` est une porte au même titre, et son tag n'est pas
  dans la liste.

**Comment ça se manifeste. Pas du tout.** C'est le propre des deux formes : la première passe
au vert, la seconde n'est pas regardée.

**Comment l'éviter.** Pour la première : la règle 1 de [ce qui reste au
projet](ce-qui-reste-au-projet.md), encore elle — réclamer le droit là où la demande est
acceptée. Pour la seconde : router sur des contrôleurs que Symfony marque — l'attribut coûte
une ligne — et déclarer dans `authorization.surface_tags` les tags de vos portes d'un autre
genre : écouteurs qui agissent, outils d'assistant, points d'entrée JSON-RPC.

**Le docteur ? Il ne peut pas, et la passe non plus** — pas sans lire les corps pour la
première forme, pas sans liste tenue pour la seconde. C'est le niveau réel de la quatrième
règle, et le README la décrit à ce niveau : un garde-fou contre l'oubli du dispositif, pas une
preuve que chaque porte traverse un verbe.

---

### 11. Le docteur exécute vos voters pour de vrai

**Ce qui casse.** Rien, mais il faut le savoir. Le docteur appelle `vote()` sur chaque voter
avec un jeton nul. Si un voter interroge la base — ce que fait le nôtre pour le tout premier
droit — le docteur a besoin d'une base **et d'un schéma**.

**Comment ça se manifeste.** Vérifié avant la migration : le docteur affiche son en-tête, puis
crache une trace `relation "users" does not exist` et sort en code 7. Sur une intégration
continue sans base, l'étape échoue pour une raison qui n'a rien à voir avec les autorisations.

**Comment l'éviter.** Faites tourner le docteur après les migrations, jamais avant. Et gardez
les voters qui ne dépendent d'aucun compte aussi peu bavards que possible : refuser avant toute
requête quand le jeton n'a pas d'utilisateur suffit à la plupart.

**Le docteur ? Il le détecte, et il le rapporte.** Il attrape par voter, le nomme avec son
exception, et poursuit l'examen des autres droits. Le bilan reste rouge — un examen qui n'a pas
eu lieu ne se conclut pas au vert — mais il dit ce qui n'a pas pu être regardé au lieu de
s'interrompre sur une trace.

Un voter qui s'était déjà effondré n'est pas réinterrogé sur les droits suivants : le premier
incident suffit à le disqualifier, et répéter la même trace n'apprendrait rien.

Et quand un droit apparaît orphelin **à côté** d'un voter qui a levé, la réserve est écrite : ce
droit est peut-être le sien. Conclure sans elle enverrait écrire un voter qui existe déjà.

---

### 12. Ce qu'un regroupement de droits coûte

Le mécanisme marche, et il est utile. Ses quatre coûts, tous vérifiés :

**1. Il est absent de l'inventaire.** Aucun cas d'usage n'exige `invoice.manage`, donc
`authorization:permissions` ne le montre pas et `PermissionCatalog::isRequired()` rend `false`.
Un écran qui confronte ses lignes accordées au catalogue le signale donc comme « sans effet »
alors qu'il accorde deux droits. **Il faut l'écarter à part**, explicitement, dans l'écran
comme dans le verbe qui enregistre.

**2. L'élargir accorde rétroactivement, sans écriture ni trace.** Vérifié :

```
ligne en base pour le groupe          ["invoice.manage"]
avant, POST /api/factures/…/finalisation   → 403
+ une ligne dans unfolds()            InvoicePermission::Finalize->id()
après, la même requête                     → 200
ligne en base                          inchangée
docteur                                    vert
```

Un droit s'est accordé à tous les détenteurs du regroupement par un commit de trois mots, sans
qu'aucune ligne ne bouge et sans qu'aucun journal ne le dise. C'est le prix réel du confort :
**le contenu d'un regroupement est du code, et il doit être relu comme un changement de
droits.**

**3. L'écran ment par omission.** Le groupe qui tient la facturation affiche `invoice.create` et
`invoice.edit` **décochés**, et ses membres les détiennent. Un administrateur qui lit l'écran
conclut le contraire de la vérité. Il faut, au minimum, dire dans le libellé du regroupement ce
qu'il ouvre — c'est ce que fait ici « Gérer les factures (créer et modifier) », et cette
parenthèse est une duplication qu'aucun contrôle ne tient à jour.

**4. Rien ne relie ce qu'il déplie à ce que le code exige.** Renommez `invoice.edit`, et le
regroupement continue d'ouvrir une identité que plus personne n'exige. Le paquet ne peut pas le
voir : il ne connaît pas les regroupements.

**Comment l'éviter.** Un test, qu'il faut écrire soi-même, et qui tient le quatrième point :

```php
foreach (PermissionBundle::cases() as $bundle) {
    foreach ($bundle->unfolds() as $opened) {
        self::assertTrue($catalog->isRequired($opened));
    }
}
```

**Le docteur ? Il ne peut pas**, faute de connaître la notion. Il le pourrait si le paquet
l'introduisait — mais ce serait un autre paquet : `PermissionCatalog` tire sa force d'être
dérivé du code et de rien d'autre, et y verser une notion que le code n'exige pas lui ferait
perdre exactement ce qui le rend fiable. **Le contrôle appartient à l'application**, et le
paquet devrait au moins le dire dans son README : trois lignes de test, une fois.

---

### 13. Un droit accordé sous le mauvais modèle

**Ce qui casse.** `stock.view` posé sur un groupe alors que seuls les rôles l'accordent. La
ligne existe, elle n'accorde rien.

**Comment ça se manifeste.** Rien du côté du paquet — le droit a bien un juge, il est
seulement accordé au mauvais endroit, et le README le dit. Vérifié : la ligne était en base, la
réponse était 403.

Ici, l'écran le signale, parce que `array_diff(accordé, cochable)` attrape ce cas en même temps
que l'identité renommée. C'est une propriété heureuse de cette écriture : un seul contrôle,
deux fautes.

**Comment l'éviter.** Validez à l'écriture, pas seulement à l'affichage : le verbe qui
enregistre refuse une identité que le modèle ne gouverne pas (§ A.8). Ce qui est déjà en base,
lui, ne se corrige qu'en le voyant.

**Le docteur ? Il ne peut pas** — même raison qu'en C.4, et le README du paquet le reconnaît
franchement.

---

### 14. Changer la stratégie de décision

**Ce qui casse.** Passer en `unanimous` inverse tout : un voter qui refuse ferme le droit,
même si un autre l'accorde. Des `supports()` qui se recouvrent, inoffensifs en `affirmative`,
ferment alors des verbes.

**Comment ça se manifeste.** Brutalement et largement : beaucoup de refus d'un coup. C'est
visible, et c'est le bon côté.

**Comment l'éviter.** Sachez laquelle vous utilisez, et écrivez-la dans `security.yaml` même
quand c'est la valeur par défaut. Une valeur implicite est une valeur qu'on découvre le jour où
elle change.

**Le docteur ? Il pourrait le détecter** — au sens où il pourrait l'afficher. Il n'a pas à
juger d'une stratégie, mais la taire rend son verdict ambigu : « ce droit a un juge » ne dit
pas la même chose selon qu'un seul avis suffit ou qu'il les faut tous.

---

### 15. Le tout premier droit mal fermé

**Ce qui casse.** La condition qui ouvre le droit d'installation est votre seule serrure. Si
elle porte sur quelque chose de réversible — « aucun rôle en base », alors qu'un rôle peut être
supprimé — la porte se rouvre un jour.

**Comment ça se manifeste. Pas du tout**, jusqu'au jour où quelqu'un rappelle le verbe et
remet les droits à leur état d'usine.

**Comment l'éviter.** Trois règles :

- faites porter la condition sur ce que le verbe **crée**, pas sur ce qu'il utilise ;
- choisissez l'état le moins réversible qui existe — ici, « aucun compte » ;
- que le verbe **refuse** plutôt que de réinstaller, et écrivez-le en test.

```php
public function testInstallingTwiceIsRefusedRatherThanReset(): void
{
    $this->expectException(MissingPermission::class);

    $this->asNobodyInParticular(static fn () => $installDemo());
}
```

**Le docteur ? Il ne peut pas.** Il constate qu'un voter se prononce ; il ne sait rien de la
condition. Et le jour où la base est vide, il constaterait même un `ACCESS_GRANTED` sans que
cela lui pose la moindre question.

---

## Les contrôles qui restent à apprendre au docteur

**Le premier de la liste**, et il vient du risque 10 bis : une route qui poste un message ou
écrit en base sans qu'aucun droit soit réclamé. Le mécanisme existe déjà — `PermissionUsage`
lit les corps de méthode — il n'est pas encore braqué sur les surfaces.

Résumé de la section C, dans l'ordre du rapport bénéfice / coût.

| # | Contrôle | Verdict |
|---|---|---|
| C.3 | compter les voters qui jugent chaque droit, avertir au-delà de un | **le fait déjà** — signalé par défaut, échec sous `--strict` |
| C.14 | afficher la stratégie de décision en tête du rapport | pourrait — une ligne |
| C.11 | attraper l'exception d'un voter et la rapporter au lieu de la laisser fuir | **le fait déjà** |
| C.6 | signaler une classe qui implémente `UseCase` sans porter le tag | pourrait — mieux encore dans la passe de compilation |
| C.1 | confronter les identités stockées au catalogue | pourrait, si l'application lui fournit ses identités stockées |
| C.5 | un droit qu'aucun voter ne prend en charge | **détecte déjà** |
| C.2 | `require()` en tête de corps | pas le docteur — mais `PermissionUsage` le pourrait |
| C.8 | `require()` sur une valeur que rien ne sait lire | pas le docteur — mais `PermissionUsage` **le signale déjà**, sans prétendre le lire |
| C.4, C.13 | droit sans modèle, droit accordé sous le mauvais modèle | ne peut pas — notion de l'application |
| C.12 | intégrité d'un regroupement de droits | ne peut pas — notion de l'application |
| C.7, C.9 | droit réclamé hors d'un cas d'usage, `can()` sans `require()` | ne peut pas — demande de lire des corps ; `PermissionUsage` le fait |
| C.10 | une surface qui atteint la base | ne peut pas — affaire de couches |
| C.15 | la serrure du tout premier droit | ne peut pas — il ne sait rien de la condition |

Quatre règles restent en prose **faute d'outillage possible** : C.4, C.13, C.12 et C.15
reposent sur des notions que le paquet ne connaît pas et n'a pas à connaître. Ce sont des
contrôles que **chaque application doit écrire**, et le README du paquet gagnerait à les
énumérer plutôt qu'à les laisser découvrir.

Toutes les autres sont en prose **faute d'avoir été outillées**, et pas faute de pouvoir
l'être.
